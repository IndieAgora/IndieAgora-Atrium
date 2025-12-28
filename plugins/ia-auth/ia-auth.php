<?php
/**
 * Plugin Name: IA Auth
 * Description: Atrium identity + session layer. phpBB is canonical. WP is shadow sessions. PeerTube tokens via API.
 * Version: 0.1.0
 * Author: IndieAgora
 * Text Domain: ia-auth
 */

if (!defined('ABSPATH')) { exit; }

define('IA_AUTH_VERSION', '0.1.0');
define('IA_AUTH_PATH', plugin_dir_path(__FILE__));
define('IA_AUTH_URL', plugin_dir_url(__FILE__));

require_once IA_AUTH_PATH . 'includes/class-ia-auth.php';

function ia_auth_bootstrap() {
    return IA_Auth::instance();
}
add_action('plugins_loaded', 'ia_auth_bootstrap');

/**
 * Activation / deactivation hooks MUST be registered on the plugin's main file.
 * (Not from an included file.)
 */
register_activation_hook(__FILE__, function () {
    if (class_exists('IA_Auth')) {
        IA_Auth::instance()->activate();
    }
});

register_deactivation_hook(__FILE__, function () {
    if (class_exists('IA_Auth')) {
        IA_Auth::instance()->deactivate();
    }
});
