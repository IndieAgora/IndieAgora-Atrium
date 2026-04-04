<?php
if (!defined('ABSPATH')) exit;

function ia_notify_register_assets(): void {
  add_action('wp_enqueue_scripts', 'ia_notify_enqueue_assets', 20);
}

function ia_notify_enqueue_assets(): void {
  if (!is_user_logged_in()) return;

  // Only load when Atrium shell exists (Atrium loads on its page template).
  // We can't reliably detect server-side, so load on front-end and bail in JS if shell missing.

  wp_enqueue_style(
    'ia-notify',
    IA_NOTIFY_URL . 'assets/css/ia-notify.css',
    array(),
    IA_NOTIFY_VERSION
  );

  wp_enqueue_script(
    'ia-notify',
    IA_NOTIFY_URL . 'assets/js/ia-notify.js',
    array(),
    IA_NOTIFY_VERSION,
    true
  );

  wp_localize_script('ia-notify', 'IA_NOTIFY', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('ia_notify_nonce'),
    'wpUserId' => get_current_user_id(),
  ));
}
