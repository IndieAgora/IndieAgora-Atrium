<?php
/**
 * Plugin Name: IA Engine
 * Description: Central config + services for IndieAgora Atrium micro-plugins. Stores secrets encrypted.
 * Version: 0.2.1
 * Author: IndieAgora
 */

if (!defined('ABSPATH')) exit;

define('IA_ENGINE_VERSION', '0.2.1');
define('IA_ENGINE_PATH', plugin_dir_path(__FILE__));
define('IA_ENGINE_URL', plugin_dir_url(__FILE__));

require_once IA_ENGINE_PATH . 'includes/class-ia-engine-crypto.php';
require_once IA_ENGINE_PATH . 'includes/class-ia-engine.php';
require_once IA_ENGINE_PATH . 'includes/class-ia-engine-admin.php';
require_once IA_ENGINE_PATH . 'includes/class-ia-engine-pt-token.php';

add_action('plugins_loaded', function () {
    IA_Engine::instance();
    IA_Engine_PeerTube_Token::boot();
});
