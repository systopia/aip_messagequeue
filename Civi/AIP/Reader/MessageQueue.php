<?php
/*-------------------------------------------------------+
| SYSTOPIA Automatic Input Processing (AIP) Framework    |
| Copyright (C) 2025 SYSTOPIA                            |
| Author: J. Margraf (margraf@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

namespace Civi\AIP\Reader;


use Civi\AIP\Process\TimeoutException;
use Civi\FormProcessor\API\Exception;
use CRM_Aip_ExtensionUtil as E;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

class MessageQueue extends Base
{
    public AMQPChannel $channel;
    public array $receivedMessages = [];
    public AMQPMessage $currentMessage;
    public AMQPStreamConnection $connection;

    public function __construct() {
        parent::__construct();
    }

    /**
     * The file this is working on
     *
     * @var resource $current_file_handle
     */
    protected $current_file_handle = null;

    /**
     * The headers of the current CSV file
     *
     * @var ?array $current_file_headers
     */
    protected ?array $current_file_headers = null;

    /**
     * The record currently being processed
     *
     * @var ?array
     */
    protected ?array $current_record = null;

    /**
     * The record to be processed next
     *
     * @var ?array
     */
    protected ?array $lookahead_record = null;

    /**
     * The record that was processed last
     *
     * @var ?array
     */
    protected ?array $last_processed_record = null;

    /**
     * Check if the component is ready,
     *   i.e. configured correctly.
     *
     * @throws \Exception
     *   an exception will be thrown if something's wrong with the
     *     configuration or state
     */
    public function verifyConfiguration()
    {
        # read config values
        $requiredConfigParams = ['host', 'port', 'vhost', 'queue'];
        $optionalConfigParams = ['user', 'pass', 'consumerTag', 'exchange', 'exchange_type','routing_key','secure', 'cafile', 'local_cert', 'local_pk', 'verify_peer', 'verify_peer_name', 'login_method'];
        // get required config params
        foreach ($requiredConfigParams as $param){
            $this->config[$param] = $this->getConfigValue($param);
            if (empty($this->config[$param])) {
                throw new \Exception("No '".$param."' set");
            }
        }
        // get optional params
        foreach ($optionalConfigParams as $param){
            $this->config[$param] = $this->getConfigValue($param);
            if (!array_key_exists($param,$this->config))
                $this->config[$param] = '';
        }
    }

    protected function connect(): ?AMQPStreamConnection
    {
        // try to create connection
        $this->log('connect to AMQP', 'info');
        try {
            // connect to AMQP
            $config = new AMQPConnectionConfig();
            $config->setHost($this->config['host']);
            $config->setPort($this->config['port']);
            $config->setUser($this->config['user'] ?? '');
            $config->setPassword($this->config['pass'] ?? '');
            $config->setVhost($this->config['vhost']);
            if(!is_null($this->config['secure']))
                $config->setIsSecure(True);
            $config->setSslCaCert($this->config['cafile']);
            $config->setSslKey($this->config['local_pk']);
            $config->setSslCert($this->config['local_cert']);
            $config->setConnectionTimeout(10);
            if($this->config['login_method'] == 'external')
                $config->setLoginMethod(AMQPConnectionConfig::AUTH_EXTERNAL);

            $this->connection = AMQPConnectionFactory::create($config);

            // return connection so Reader can work with  it.
        } catch (AMQPRuntimeException $e) {
            $this->log('AMQPRuntimeException Error encountered: ' . $e->getMessage(), 'error');
            $this->cleanup_connection();
            return null;
        } catch (\RuntimeException $e) {
            $this->log('RuntimeException Error encountered: ' . $e->getMessage(), 'error');
            $this->cleanup_connection();
            return null;
        } catch (\ErrorException $e) {
            $this->log('ErrorException Error encountered: ' . $e->getMessage(), 'error');
            $this->cleanup_connection();
            return null;
        }

        try {
            // declare and bind queue
            $this->channel = $this->connection->channel();
            $this->channel->queue_declare($this->config['queue'], false, true, false, false);
            $this->channel->exchange_declare($this->config['exchange'], $this->config['exchange_type'], false, true, false);
            $this->channel->queue_bind($this->queue, $this->config['exchange'], $this->config['routing_key']);
        } catch (AMQPTimeoutException $ex) {
            $this->log('AMQPTimeoutException encountered: ' . $ex->getMessage(), 'error');
            return null;
        } catch (Exception $ex) {
            $this->log('Error encountered: ' . $ex->getMessage(), 'error');
            return null;
        }
        return $this->connection;
    }

    public function canReadSource(string $source): bool
    {
        // connect to the AMQP Message Queue
        $connection = $this->connect();
        if (!$connection){
            return false;
        }else {
            // Conection was successful
            $this->connection = $connection;
            return true;
        }
    }


    function shutdown($channel, $connection)
    {
        $channel->close();
        $connection->close();
    }

    public function process_message(AMQPMessage $msg)
    {
        // push message to internal Queue
        $this->log("MessageQueue received message ".$msg->getBody());

        //TODO: TEST: early ACK message
        $msg->ack();
        //Todo: Maybe a requirement will come up, that one message can contain several records.
        // in this case we could decode message already here
        // and then create several records from the received message
        array_push($this->receivedMessages,$msg);
    }

    public function get_process_function() {
        return [$this, 'process_message'];
    }

    /**
     * Open and init the CSV file
     *
     * @throws \Exception
     *   any issues with opening/reading the file
     */
    public function initialiseWithSource($source)
    {
        parent::initialiseWithSource($source);
    }

    public function hasMoreRecords(): bool
    {
        // always true because we want the reader to listen to the queue all the time
        return true;
    }

    /**
     * Get the next record from the file
     *
     * @return array|null
     *   a record, or null if there are no more records
     *
     * @throws \Exception
     *   if there is a read error
     */
    public function getNextRecord(): ?array
    {
        // create connection right here
        if(!$this->connection->isConnected())
            $this->connect();

        // consume
        if(!count($this->channel->callbacks)){
            $callback = $this->get_process_function();
            $this->channel->basic_consume($this->config['queue'], $this->config['consumerTag'], false, false, false, false, $callback);
            // register shutdown callback
            register_shutdown_function([$this, 'shutdown'], $this->channel, $this->connection);
        }
        $timeout = $this->getConfigValue('timeout');
        while(count($this->channel->callbacks)) {
            // Todo: Wait only until callback function was called
            // Currently this is processing only one Message
            // maybe this is ok, because getNextRecord is supposed to deliver only one record
            // But maybe we should check if there are already messages in receiveMessages before we wait for new messages and after waiting for new messages

            $this->channel->wait(null, false, $timeout);

            if (count($this->receivedMessages)>0) {
                // get received message
                $this->currentMessage = array_shift($this->receivedMessages);

                // decode Message
                $this->current_record = json_decode($this->currentMessage->getBody(), true);

                // return record
                return $this->current_record;
            }
        }

        // if timed out throw TimeOutException
        throw new TimeoutException("Listening to Messages timed out.");
    }

    /**
     * Read the next record from the open file
     *
     * @todo needed?
     */
    public function skipNextRecord() {

    }

    public function markLastRecordProcessed()
    {
        // send ack to message broker
        //$this->currentMessage->ack();

        // calculate internal countings
        $this->records_processed_in_this_session++;
        $this->setProcessedRecordCount($this->getProcessedRecordCount() + 1);
        $this->current_record = $this->lookahead_record;
    }

    public function markLastRecordFailed()
    {
        $this->records_processed_in_this_session++;
        $this->setFailedRecordCount($this->getFailedRecordCount() + 1);
        $this->current_record = $this->lookahead_record;
    }

    /**
     * The file this is working on
     *
     * @return string the current file path/url
     */
    public function getCurrentFile() : ?string
    {
        return "AMQP Message Broker";
    }

    /**
     * The file this is working on
     *
     * @param $file string the current file path/url
     */
    protected function setCurrentFile($file)
    {
        return $this->setStateValue('current_file', $file);
    }

    public function resetState()
    {
        $this->setStateValue('current_file', null);
        parent::resetState();
    }

    /**
     * Mark the given resource as processed/completed
     *
     * @param string $uri
     *   an URI to marked processed/completed
     */
    public function markSourceProcessed(string $uri)
    {
        $this->setStateValue('current_file', null);
    }

    /**
     * Mark the given resource as failed
     *
     * @param string $uri
     *   an URI to marked as FAILED
     */
    public function markSourceFailed(string $uri)
    {
        $this->setStateValue('current_file', null);
    }

    /**
     * Mark the given resource as failed
     *
     **/
    public function cleanup_connection() {
        // Connection might already be closed.
        try {
            if(isset($this->connection)) {
                $this->connection->close();
            }
        } catch (\ErrorException $e) {
            $this->log('Error closing connection: ' . $e->getMessage(), 'error');
        }
    }
}



