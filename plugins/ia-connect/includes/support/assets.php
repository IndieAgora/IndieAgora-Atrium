<?php
if (!defined('ABSPATH')) exit;

function ia_connect_assets_boot(): void {
  add_action('wp_enqueue_scripts', 'ia_connect_enqueue_assets', 999);
}

function ia_connect_enqueue_assets(): void {
  if (is_admin()) return;
  if (!ia_connect_atrium_present()) return;

  wp_enqueue_style('ia-connect', IA_CONNECT_URL . 'assets/css/ia-connect.css', [], IA_CONNECT_VERSION);
  wp_enqueue_script('ia-connect', IA_CONNECT_URL . 'assets/js/ia-connect.js', [], IA_CONNECT_VERSION, true);
  wp_enqueue_style('ia-connect-activity', IA_CONNECT_URL . 'assets/css/ia-connect.activity.css', ['ia-connect'], IA_CONNECT_VERSION);
  // Styling layer for the profile activity tabs and privacy switches.
  wp_enqueue_style('ia-connect-fb', IA_CONNECT_URL . 'assets/css/ia-connect.fb.css', ['ia-connect-activity'], IA_CONNECT_VERSION);
  wp_enqueue_script('ia-connect-activity', IA_CONNECT_URL . 'assets/js/ia-connect.activity.js', ['ia-connect'], IA_CONNECT_VERSION, true);

  // Styling layer for the profile activity tabs and privacy switches.
  // Enqueue after the base + activity CSS so it can override cleanly.
  wp_enqueue_style('ia-connect-fb', IA_CONNECT_URL . 'assets/css/ia-connect.fb.css', ['ia-connect-activity'], IA_CONNECT_VERSION);


  wp_localize_script('ia-connect', 'IA_CONNECT', [
    'version' => IA_CONNECT_VERSION,
    'panelKey' => IA_CONNECT_PANEL_KEY,
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'me' => [
      'id' => (int) get_current_user_id(),
      'login' => (string) (is_user_logged_in() ? wp_get_current_user()->user_login : ''),
      'email' => (string) (is_user_logged_in() ? wp_get_current_user()->user_email : ''),
    ],
    'nonces' => [
      'profile_photo' => ia_connect_nonce('profile_photo'),
      'cover_photo' => ia_connect_nonce('cover_photo'),
      'user_search' => ia_connect_nonce('user_search'),
      'post_create' => ia_connect_nonce('post_create'),
      'post_list' => ia_connect_nonce('post_list'),
      'comment_create' => ia_connect_nonce('comment_create'),
      'post_get' => ia_connect_nonce('post_get'),
      'post_share' => ia_connect_nonce('post_share'),
      'mention_suggest' => ia_connect_nonce('mention_suggest'),
      'wall_search' => ia_connect_nonce('wall_search'),
      'settings_update' => ia_connect_nonce('settings_update'),
      'privacy_get' => ia_connect_nonce('privacy_get'),
      'privacy_update' => ia_connect_nonce('privacy_update'),
      'discuss_activity' => ia_connect_nonce('discuss_activity'),
      'stream_activity' => ia_connect_nonce('stream_activity'),
      'account_deactivate' => ia_connect_nonce('account_deactivate'),
      'account_delete' => ia_connect_nonce('account_delete'),
      'export_data' => ia_connect_nonce('export_data'),
    ],
  ]);
}
