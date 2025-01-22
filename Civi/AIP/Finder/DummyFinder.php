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

namespace Civi\AIP\Finder;

use CRM_Aip_ExtensionUtil as E;
use PHPUnit\Exception;


/**
 * A FINDER that monitors a fixed file (online or local) for changes
 *
 * It has the following configuration options:
 *  url            - url of the file
 *  detect_changes - discard the source file if it has already been processed
 *                     (using checksum)
 **/
class DummyFinder extends Base
{
    public static string $connection_label = "dummy";


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
        // by default, we're ready :)
    }

    public function getTypeName() : string
    {
        return E::ts("Dummy Finder");
    }

    /**
     * See if there is a new file in the dropbox
     *
     * @return ?string
     */
    public function findNextSource(): ?string
    {
        return self::$connection_label;
    }

    /**
     * This function claims the source file by moving it to the 'processing' folder
     *
     * @param string $file_path
     *   this should be the file path
     *
     * @return string $uri
     *    the resulting URI (likely the same)
     */
    public function claimSource(string $file_path)
    {
        // nothing to do here
        return $file_path;
    }

    /**
     * This function marks the resource as processed by moving it into the respective folder
     *
     * @param string $file_path
     *   this should be the file path
     */
    public function markSourceProcessed(string $file_path)
    {
        // nothing to do here
        return true;
    }

    /**
     * This function marks the resource as processed by moving it into the respective folder
     *
     * @param string $file_path
     *   this should be the file path
     */
    public function markSourceFailed(string $file_path)
    {
        // nothing to do here
        return true;
    }

}