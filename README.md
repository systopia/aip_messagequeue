# aip_messagequeue

This extensions is only working with: https://github.com/systopia/aip .
It extends the Automated Import Processor with the functionality to Read JSON-Content from a MessageQueue (eg. RabbitMQ)

here is an example configuration to connect to an amqp-message-queue with username and password
```
{
  "finder": {
    "class": "Civi\\AIP\\Finder\\DummyFinder"
  },
  "reader": {
    "host": "www.hostname.de",
    "port": "5673",
    "vhost": "your_vhost",
    "user": "your_user_name",
    "pass": "your_password",
    "verify_peer": "true",
    "queue": "your-queue",
    "exchange": "your-exchange",
    "exchange_type": "topic",
    "class": "Civi\\AIP\\Reader\\MessageQueue"
  },
  "processor": {
    "api_entity": "FormProcessor",
    "api_action": "test",
    "class": "Civi\\AIP\\Processor\\Api3"
  },
  "process": {
    "log": {
      "file": "/srv/direktmarketing/aip/processing.log"
    },
    "processing_limit": {
      "php_process_time": 560
    }
  },
  "log": {
    "level": "info"
  }
}

```


more complex example to login with cert file:
```
{
  "finder": {
    "class": "Civi\\AIP\\Finder\\DummyFinder"
  },
  "reader": {
    "host": "www.hostname.de",
    "port": "5673",
    "vhost": "your_vhost",
    "secure": true,
    "cafile": "/path/to/your/cafile.crt",
    "local_cert": "/patch/to/certfile.pem",
    "local_pk": "/path/To/pkey.pem",
    "verify_peer": "true",
    "queue": "your-queue",
    "exchange": "your-exchange",
    "exchange_type": "topic",
    "login_method": "external",
    "class": "Civi\\AIP\\Reader\\MessageQueue"
  },
  "processor": {
    "api_entity": "FormProcessor",
    "api_action": "test",
    "class": "Civi\\AIP\\Processor\\Api3"
  },
  "process": {
    "log": {
      "file": "/srv/direktmarketing/aip/processing.log"
    },
    "processing_limit": {
      "php_process_time": 560
    }
  },
  "log": {
    "level": "info"
  }
}

```