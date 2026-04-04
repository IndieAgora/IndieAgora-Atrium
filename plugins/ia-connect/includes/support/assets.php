<?php
if (!defined('ABSPATH')) exit;

function ia_connect_assets_boot(): void {
  add_action('wp_enqueue_scripts', 'ia_connect_enqueue_assets', 999);
}

function ia_connect_enqueue_assets(): void {
  if (is_admin()) return;
  if (!ia_connect_atrium_present()) return;

  // If IA Discuss is active, borrow its UI assets for the Discuss activity tab inside Connect.
  // This keeps the Agora list and feed cards visually consistent without duplicating CSS/JS.
  if (function_exists('ia_discuss_register_assets')) {
    // Register (but do not boot) Discuss assets.
    ia_discuss_register_assets();

    // Minimal CSS needed for agora rows + feed cards.
    wp_enqueue_style('ia-discuss-base');
    wp_enqueue_style('ia-discuss-layout');
    wp_enqueue_style('ia-discuss-light');
    // Split CSS endcaps (deps pull the rest).
    if (wp_style_is('ia-discuss-cards-icon-buttons', 'registered')) {
      wp_enqueue_style('ia-discuss-cards-icon-buttons');
    }
    if (wp_style_is('ia-discuss-search-results-row-details', 'registered')) {
      wp_enqueue_style('ia-discuss-search-results-row-details');
    }
    wp_enqueue_style('ia-discuss-agora');

    // Topic view styling + renderer (used for embedded Discuss shares inside Connect posts).
    // Safe to enqueue: purely additive and uses existing registered handles.
    if (wp_style_is('ia-discuss-modal', 'registered')) {
      wp_enqueue_style('ia-discuss-modal');
    }
    if (wp_style_is('ia-discuss-topic', 'registered')) {
      wp_enqueue_style('ia-discuss-topic');
    }

    if (wp_script_is('ia-discuss-topic-render', 'registered')) {
      wp_enqueue_script('ia-discuss-topic-utils');
      wp_enqueue_script('ia-discuss-topic-media');
      wp_enqueue_script('ia-discuss-topic-render');
    }

    // JS needed for bell/join buttons.
    wp_enqueue_script('ia-discuss-core');
    wp_enqueue_script('ia-discuss-api');
    wp_enqueue_script('ia-discuss-ui-agora-membership');
  }

  wp_enqueue_style('ia-connect', IA_CONNECT_URL . 'assets/css/ia-connect.css', [], IA_CONNECT_VERSION);
  wp_enqueue_style('ia-connect-typography', IA_CONNECT_URL . 'assets/css/ia-connect-typography.css', ['ia-connect'], IA_CONNECT_VERSION);
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
    'siteTitle' => 'IndieAgora',
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'me' => [
      'id' => (int) get_current_user_id(),
      'login' => (string) (is_user_logged_in() ? wp_get_current_user()->user_login : ''),
      'display' => (string) (is_user_logged_in() ? (wp_get_current_user()->display_name ?: wp_get_current_user()->user_login) : ''),
      'email' => (string) (is_user_logged_in() ? wp_get_current_user()->user_email : ''),
      'is_admin' => (bool) current_user_can('manage_options'),
      'signature' => (string) (is_user_logged_in() ? (string) get_user_meta(get_current_user_id(), IA_CONNECT_META_SIGNATURE, true) : ''),
      'signature_show_discuss' => (int) (is_user_logged_in() ? (int) get_user_meta(get_current_user_id(), IA_CONNECT_META_SIGNATURE_SHOW_DISCUSS, true) : 0),
    ],
    'nonces' => [
      'profile_photo' => ia_connect_nonce('profile_photo'),
      'cover_photo' => ia_connect_nonce('cover_photo'),
      'user_search' => ia_connect_nonce('user_search'),
      'post_create' => ia_connect_nonce('post_create'),
      'post_list' => ia_connect_nonce('post_list'),
      'comment_create' => ia_connect_nonce('comment_create'),
      'comment_update' => ia_connect_nonce('comment_update'),
      'comment_delete' => ia_connect_nonce('comment_delete'),
      'comments_page' => ia_connect_nonce('comments_page'),
      'follow_toggle' => ia_connect_nonce('follow_toggle'),
      'post_update' => ia_connect_nonce('post_update'),
      'post_delete' => ia_connect_nonce('post_delete'),
      'post_get' => ia_connect_nonce('post_get'),
      'post_share' => ia_connect_nonce('post_share'),
      'mention_suggest' => ia_connect_nonce('mention_suggest'),
      'display_name_update' => ia_connect_nonce('display_name_update'),
      'signature_update' => ia_connect_nonce('signature_update'),
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