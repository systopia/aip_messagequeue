<?php

require_once 'aip_messagequeue.civix.php';
require_once(__DIR__.'/vendor/autoload.php');
// phpcs:disable
use CRM_AipMessagequeue_ExtensionUtil as E;
// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function aip_messagequeue_civicrm_config(&$config): void {
  _aip_messagequeue_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function aip_messagequeue_civicrm_install(): void {
  _aip_messagequeue_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function aip_messagequeue_civicrm_enable(): void {
  _aip_messagequeue_civix_civicrm_enable();
}
