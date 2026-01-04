<?php
/**
 * Plugin Name: IA Reset Modal Fix
 * Description: Ensures /ia-reset/ links reliably open a password reset panel in the Atrium auth modal.
 * Version: 0.1.1
 * Author: IndieAgora
 */

if (!defined('ABSPATH')) exit;

define('IA_RESET_FIX_VER', '0.1.1');

define('IA_RESET_FIX_PATH', plugin_dir_path(__FILE__));

define('IA_RESET_FIX_URL', plugin_dir_url(__FILE__));

// -------------------------
// Rewrite: /ia-reset/?key=...&login=...
// -------------------------
add_action('init', function () {
  add_rewrite_rule('^ia-reset/?$', 'index.php?ia_reset_route=1', 'top');
  add_rewrite_tag('%ia_reset_route%', '([^&]+)');
}, 0);

add_action('template_redirect', function () {
  $route = get_query_var('ia_reset_route');
  if (!$route) return;

  $key = isset($_GET['key']) ? trim((string)$_GET['key']) : '';
  $login = isset($_GET['login']) ? trim((string)$_GET['login']) : '';

  if ($key !== '' && $login !== '') {
    $url = add_query_arg([
      'ia_reset' => '1',
      'key'      => $key,
      'login'    => $login,
    ], home_url('/'));
    wp_safe_redirect($url);
    exit;
  }

  wp_safe_redirect(home_url('/'));
  exit;
});

register_activation_hook(__FILE__, function () {
  // Ensure rewrite rule exists immediately.
  add_rewrite_rule('^ia-reset/?$', 'index.php?ia_reset_route=1', 'top');
  add_rewrite_tag('%ia_reset_route%', '([^&]+)');
  flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
  flush_rewrite_rules();
});

// -------------------------
// Frontend JS: open modal + inject reset panel
// -------------------------
add_action('wp_enqueue_scripts', function () {
  if (is_admin()) return;

  wp_register_style('ia-reset-fix', IA_RESET_FIX_URL . 'assets/css/ia-reset-fix.css', [], IA_RESET_FIX_VER);
  wp_enqueue_style('ia-reset-fix');

  wp_register_script('ia-reset-fix', IA_RESET_FIX_URL . 'assets/js/ia-reset-fix.js', [], IA_RESET_FIX_VER, true);
  wp_enqueue_script('ia-reset-fix');

  wp_localize_script('ia-reset-fix', 'IA_RESET_FIX', [
    'ajax'  => admin_url('admin-ajax.php'),
    // Must match IA_User::guard_nonce() expected action string
    'nonce' => wp_create_nonce('ia_user_nonce'),
    'home'  => home_url('/'),
  ]);
}, 30);
