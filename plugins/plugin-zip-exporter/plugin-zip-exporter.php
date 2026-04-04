<?php
/**
 * Plugin Name: Plugin Zip Exporter
 * Plugin URI: https://example.com/
 * Description: Download individual WordPress plugins as zip files or export the entire plugins directory from the admin area.
 * Version: 1.0.1
 * Author: OpenAI
 * License: GPLv2 or later
 * Text Domain: plugin-zip-exporter
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PZE_VERSION', '1.0.1');
define('PZE_FILE', __FILE__);
define('PZE_DIR', plugin_dir_path(__FILE__));
define('PZE_URL', plugin_dir_url(__FILE__));

require_once PZE_DIR . 'includes/class-plugin-zip-exporter.php';

Plugin_Zip_Exporter::init();
