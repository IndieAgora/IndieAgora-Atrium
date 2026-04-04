<?php
/**
 * Plugin Name: IA Goodbye
 * Description: Central account lifecycle authority for Atrium. Enforces delete/deactivate rules, blocks deleted re-login, and neutralises PeerTube→phpBB resurrection paths.
 * Version: 0.1.2
 * Author: IndieAgora
 * Text Domain: ia-goodbye
 */

if (!defined('ABSPATH')) { exit; }

define('IA_GOODBYE_VERSION', '0.1.2');
define('IA_GOODBYE_PATH', plugin_dir_path(__FILE__));

require_once IA_GOODBYE_PATH . 'includes/class-ia-goodbye.php';

add_action('plugins_loaded', function () {
  if (class_exists('IA_Goodbye')) {
    IA_Goodbye::instance();
  }
}, 1);

register_activation_hook(__FILE__, function () {
  if (class_exists('IA_Goodbye')) {
    IA_Goodbye::instance()->activate();
  }
});
