<?php
/**
 * Plugin Name: IA Connect
 * Description: Atrium Connect surface (profile shell + viewer + bio + privacy toggles). Renders only inside Atrium Connect panel.
 * Version: 1.0.0
 * Author: IndieAgora
 * Text Domain: ia-connect
 */

if (!defined('ABSPATH')) exit;

define('IA_CONNECT_VERSION', '1.0.0');
define('IA_CONNECT_PATH', plugin_dir_path(__FILE__));
define('IA_CONNECT_URL', plugin_dir_url(__FILE__));

require_once IA_CONNECT_PATH . 'includes/ia-connect.php';

add_action('plugins_loaded', function () {
  ia_connect_boot();
}, 20);
