<?php
/**
 * Plugin Name: IA Login
 * Description: Provides the Atrium auth modal (login/register) markup via the ia_atrium_auth_modal hook.
 * Version: 0.1.0
 * Author: IndieAgora
 */
if (!defined('ABSPATH')) exit;

define('IA_LOGIN_PATH', plugin_dir_path(__FILE__));
define('IA_LOGIN_URL', plugin_dir_url(__FILE__));

add_action('wp_enqueue_scripts', function () {
  // Optional tiny CSS to ensure modal container exists; main styling is in ia-atrium/ia-user.
  wp_register_style('ia-login', IA_LOGIN_URL . 'assets/css/ia-login.css', [], '0.1.0');
  wp_enqueue_style('ia-login');
}, 20);

function ia_login_render_modal(): void {
  include IA_LOGIN_PATH . 'templates/auth-modal.php';
}

add_action('ia_atrium_auth_modal', 'ia_login_render_modal', 10);
