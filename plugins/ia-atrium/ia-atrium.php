<?php
/**
 * Plugin Name: IA Atrium
 * Description: Core Atrium shell (Connect / Discuss / Stream tabs + bottom navigation). All features are added via micro-plugins.
 * Version: 0.1.0
 * Author: IndieAgora
 * Text Domain: ia-atrium
 */

if (!defined('ABSPATH')) exit;

define('IA_ATRIUM_VERSION', '0.1.0');
define('IA_ATRIUM_PATH', plugin_dir_path(__FILE__));
define('IA_ATRIUM_URL', plugin_dir_url(__FILE__));

require_once IA_ATRIUM_PATH . 'includes/class-ia-atrium-assets.php';
require_once IA_ATRIUM_PATH . 'includes/class-ia-atrium.php';

add_action('plugins_loaded', function () {
    IA_Atrium::instance();
});
