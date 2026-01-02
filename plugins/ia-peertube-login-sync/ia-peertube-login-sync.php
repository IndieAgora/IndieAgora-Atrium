<?php
/**
 * Plugin Name: IA PeerTube Login Sync
 * Description: Allows local PeerTube users to log in to Atrium without separate signup by auto-creating/linking phpBB canonical users + WP shadow users.
 * Version: 0.1.1
 * Author: IndieAgora
 * Text Domain: ia-peertube-login-sync
 */

if (!defined('ABSPATH')) exit;

define('IA_PTLS_VERSION', '0.1.1');
define('IA_PTLS_PATH', plugin_dir_path(__FILE__));
define('IA_PTLS_URL', plugin_dir_url(__FILE__));

require_once IA_PTLS_PATH . 'includes/class-ia-ptls.php';
require_once IA_PTLS_PATH . 'includes/admin/class-ia-ptls-admin.php';

add_action('plugins_loaded', function () {
    if (!class_exists('IA_Engine')) {
        // IA Engine must be active for credentials.
        return;
    }
    IA_PTLS::instance();
    IA_PTLS_Admin::init();
});
