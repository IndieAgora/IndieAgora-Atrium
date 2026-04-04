<?php
/**
 * Plugin Name: IA Online
 * Description: Atrium-aware online presence tracker for users and guests, with IPs and live admin visibility.
 * Version: 0.2.6
 * Author: IndieAgora
 * Text Domain: ia-online
 */

if (!defined('ABSPATH')) exit;

define('IA_ONLINE_VERSION', '0.2.6');
define('IA_ONLINE_PATH', plugin_dir_path(__FILE__));
define('IA_ONLINE_URL', plugin_dir_url(__FILE__));
define('IA_ONLINE_DB_VERSION', '2');
define('IA_ONLINE_TABLE_SUFFIX', 'ia_online_presence');
define('IA_ONLINE_COOKIE', 'ia_online_guest');
define('IA_ONLINE_OPTION_DB_VERSION', 'ia_online_db_version');
define('IA_ONLINE_OPTION_SETTINGS', 'ia_online_settings');
define('IA_ONLINE_NONCE_ACTION', 'ia_online_ping');

require_once IA_ONLINE_PATH . 'includes/bootstrap.php';

register_activation_hook(__FILE__, function () {
    ia_online_require('includes/db/schema.php');
    if (function_exists('ia_online_install_schema')) {
        ia_online_install_schema();
    }
    if (!wp_next_scheduled('ia_online_cleanup_event')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'ia_online_cleanup_event');
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('ia_online_cleanup_event');
});

add_action('plugins_loaded', function () {
    ia_online_require('includes/db/schema.php');
    ia_online_require('includes/runtime/presence.php');
    ia_online_require('includes/runtime/ajax.php');
    ia_online_require('includes/analytics/history.php');
    ia_online_require('includes/admin/analytics.php');
    ia_online_require('includes/admin/page.php');

    ia_online_install_schema();

    if (function_exists('ia_online_runtime_boot')) {
        ia_online_runtime_boot();
    }
    if (function_exists('ia_online_ajax_boot')) {
        ia_online_ajax_boot();
    }
    if (function_exists('ia_online_admin_boot')) {
        ia_online_admin_boot();
    }
}, 30);
