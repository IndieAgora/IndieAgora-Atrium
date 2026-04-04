<?php
if (!defined('ABSPATH')) exit;

function ia_notify_register_ajax(): void {
  add_action('wp_ajax_ia_notify_sync', 'ia_notify_ajax_sync');
  add_action('wp_ajax_ia_notify_list', 'ia_notify_ajax_list');
  add_action('wp_ajax_ia_notify_mark_read', 'ia_notify_ajax_mark_read');
  add_action('wp_ajax_ia_notify_prefs_save', 'ia_notify_ajax_prefs_save');
}

function ia_notify_ajax_sync(): void {
  ia_notify_ajax_guard();
  $phpbb_id = ia_notify_current_phpbb_id();
  if ($phpbb_id <= 0) wp_send_json_error(['message'=>'not logged in']);

  $after_id = (int) ($_POST['after_id'] ?? 0);
  $items = ia_notify_fetch_latest($phpbb_id, 10, $after_id);
  $payload = array_map('ia_notify_row_to_payload', $items);

  wp_send_json_success([
    'unread_count' => ia_notify_unread_count($phpbb_id),
    'items' => $payload,
  ]);
}

function ia_notify_ajax_list(): void {
  ia_notify_ajax_guard();
  $phpbb_id = ia_notify_current_phpbb_id();
  if ($phpbb_id <= 0) wp_send_json_error(['message'=>'not logged in']);

  $offset = (int) ($_POST['offset'] ?? 0);
  $limit = (int) ($_POST['limit'] ?? 50);
  $items = ia_notify_fetch_page($phpbb_id, $offset, $limit);
  $payload = array_map('ia_notify_row_to_payload', $items);

  $wp_id = ia_notify_current_wp_id();
  $prefs = $wp_id ? ia_notify_get_prefs($wp_id) : ia_notify_default_prefs();

  wp_send_json_success([
    'unread_count' => ia_notify_unread_count($phpbb_id),
    'items' => $payload,
    'prefs' => $prefs,
  ]);
}

function ia_notify_ajax_mark_read(): void {
  ia_notify_ajax_guard();
  $phpbb_id = ia_notify_current_phpbb_id();
  if ($phpbb_id <= 0) wp_send_json_error(['message'=>'not logged in']);

  $ids = $_POST['ids'] ?? [];
  if (!is_array($ids)) $ids = [];
  $all = !empty($_POST['all']);

  $n = ia_notify_mark_read($phpbb_id, $all ? [] : $ids);

  wp_send_json_success([
    'updated' => $n,
    'unread_count' => ia_notify_unread_count($phpbb_id),
  ]);
}

function ia_notify_ajax_prefs_save(): void {
  ia_notify_ajax_guard();
  $wp_id = ia_notify_current_wp_id();
  if ($wp_id <= 0) wp_send_json_error(['message'=>'not logged in']);

  $prefs = [
    'popups' => !empty($_POST['popups']),
    'emails' => !empty($_POST['emails']),
    'mute_all' => !empty($_POST['mute_all']),
  ];

  ia_notify_set_prefs($wp_id, $prefs);
  wp_send_json_success(['prefs' => ia_notify_get_prefs($wp_id)]);
}

function ia_notify_ajax_guard(): void {
  if (!is_user_logged_in()) wp_send_json_error(['message'=>'not logged in']);
  $nonce = (string) ($_POST['nonce'] ?? '');
  if (!wp_verify_nonce($nonce, 'ia_notify_nonce')) {
    wp_send_json_error(['message'=>'bad nonce']);
  }
}
