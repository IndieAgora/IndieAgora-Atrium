<?php
/**
 * Plugin Name: IA Notify
 * Description: In-app notifications for Atrium (bell badge + toast + fullscreen inbox).
 * Version: 0.1.19
 * Author: IndieAgora
 * Text Domain: ia-notify
 */

if (!defined('ABSPATH')) exit;

define('IA_NOTIFY_VERSION', '0.1.19');
define('IA_NOTIFY_PATH', plugin_dir_path(__FILE__));
define('IA_NOTIFY_URL', plugin_dir_url(__FILE__));

define('IA_NOTIFY_TABLE', 'ia_notify_items');

define('IA_NOTIFY_AJAX_NS', 'ia_notify');

define('IA_NOTIFY_PREFS_META', 'ia_notify_prefs');

require_once IA_NOTIFY_PATH . 'includes/db.php';
require_once IA_NOTIFY_PATH . 'includes/identity.php';
require_once IA_NOTIFY_PATH . 'includes/prefs.php';
require_once IA_NOTIFY_PATH . 'includes/enrich.php';
require_once IA_NOTIFY_PATH . 'includes/peertube.php';
require_once IA_NOTIFY_PATH . 'includes/email-gate.php';
require_once IA_NOTIFY_PATH . 'includes/hooks.php';
require_once IA_NOTIFY_PATH . 'includes/ajax.php';
require_once IA_NOTIFY_PATH . 'includes/assets.php';

add_action('plugins_loaded', function () {
  try {
    // Activation hooks are not guaranteed to run in all workflows (e.g. plugin zip replaced while active).
    // Ensure the DB table exists before we begin inserting/reading.
    if (function_exists('ia_notify_ensure_tables')) {
      ia_notify_ensure_tables();
    }
    ia_notify_register_hooks();
    ia_notify_register_ajax();
    ia_notify_register_assets();
  } catch (Throwable $e) {
    error_log('[IA_NOTIFY] boot error: ' . $e->getMessage());
  }
}, 40);

register_activation_hook(__FILE__, function () {
  ia_notify_install();
});
