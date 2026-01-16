<?php
/**
 * Plugin Name: IA Message
 * Description: Atrium-native messaging module (DM + group) with email-first import adapters.
 * Version: 1.0.2
 * Author: IndieAgora
 * Text Domain: ia-message
 */

if (!defined('ABSPATH')) exit;

define('IA_MESSAGE_VERSION', '0.1.1');
define('IA_MESSAGE_PATH', plugin_dir_path(__FILE__));
define('IA_MESSAGE_URL', plugin_dir_url(__FILE__));

require_once IA_MESSAGE_PATH . 'includes/constants.php';
require_once IA_MESSAGE_PATH . 'includes/functions.php';
require_once IA_MESSAGE_PATH . 'includes/ia-message.php';

add_action('plugins_loaded', function () {
  if (function_exists('ia_message_boot')) {
    ia_message_boot();
  }
});
