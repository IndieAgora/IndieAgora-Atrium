<?php
if (!defined('ABSPATH')) exit;

function ia_notify_register_ajax(): void {
  add_action('wp_ajax_ia_notify_sync', 'ia_notify_ajax_sync');
  add_action('wp_ajax_ia_notify_list', 'ia_notify_ajax_list');
  add_action('wp_ajax_ia_notify_mark_read', 'ia_notify_ajax_mark_read');
  add_action('wp_ajax_ia_notify_clear', 'ia_notify_ajax_clear');
  add_action('wp_ajax_ia_notify_prefs_save', 'ia_notify_ajax_prefs_save');
}

function ia_notify_ajax_merge_items(array $local_items, array $pt_items): array {
  $items = array_merge($local_items, $pt_items);
  usort($items, function($a, $b) {
    $ta = strtotime((string)($a['created_at'] ?? '')) ?: 0;
    $tb = strtotime((string)($b['created_at'] ?? '')) ?: 0;
    if ($ta === $tb) return strcmp((string)($b['id'] ?? ''), (string)($a['id'] ?? ''));
    return $tb <=> $ta;
  });
  return $items;
}

function ia_notify_ajax_sync(): void {
  ia_notify_ajax_guard();
  $phpbb_id = ia_notify_current_phpbb_id();
  if ($phpbb_id <= 0) wp_send_json_error(['message'=>'not logged in']);

  $after_id = max(0, (int) ($_POST['after_id'] ?? 0));
  $items = ia_notify_fetch_latest($phpbb_id, 10, $after_id);
  $payload = array_map('ia_notify_row_to_payload', $items);

  $pt = ia_notify_peertube_notifications(10);
  $merged = ia_notify_ajax_merge_items($payload, (array)($pt['items'] ?? []));

  wp_send_json_success([
    'unread_count' => ia_notify_unread_count($phpbb_id) + (int)($pt['unread_count'] ?? 0),
    'items' => $merged,
  ]);
}

function ia_notify_ajax_list(): void {
  ia_notify_ajax_guard();
  $phpbb_id = ia_notify_current_phpbb_id();
  if ($phpbb_id <= 0) wp_send_json_error(['message'=>'not logged in']);

  $offset = max(0, (int) ($_POST['offset'] ?? 0));
  $limit = max(1, min(50, (int) ($_POST['limit'] ?? 30)));
  $items = ia_notify_fetch_page($phpbb_id, $offset, $limit);
  $payload = array_map('ia_notify_row_to_payload', $items);

  $pt = ($offset === 0) ? ia_notify_peertube_notifications($limit) : ['items' => [], 'unread_count' => 0];
  $merged = ia_notify_ajax_merge_items($payload, (array)($pt['items'] ?? []));

  $wp_id = ia_notify_current_wp_id();
  $prefs = $wp_id ? ia_notify_get_prefs($wp_id) : ia_notify_default_prefs();

  wp_send_json_success([
    'unread_count' => ia_notify_unread_count($phpbb_id) + (int)($pt['unread_count'] ?? 0),
    'items' => $merged,
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

  $local_ids = [];
  $pt_ids = [];
  foreach ($ids as $raw) {
    if (is_string($raw) && str_starts_with($raw, 'pt:')) {
      $pt_ids[] = (int) substr($raw, 3);
    } else {
      $local_ids[] = (int) $raw;
    }
  }

  $n = ia_notify_mark_read($phpbb_id, $all ? [] : $local_ids);
  ia_notify_peertube_mark_read($pt_ids, $all);
  $pt = ia_notify_peertube_notifications(10);

  wp_send_json_success([
    'updated' => $n + ($all ? 0 : count(array_filter($pt_ids))),
    'unread_count' => ia_notify_unread_count($phpbb_id) + (int)($pt['unread_count'] ?? 0),
  ]);
}

function ia_notify_ajax_clear(): void {
  ia_notify_ajax_guard();
  $phpbb_id = ia_notify_current_phpbb_id();
  if ($phpbb_id <= 0) wp_send_json_error(['message'=>'not logged in']);

  $deleted = ia_notify_clear_all($phpbb_id);
  ia_notify_peertube_mark_read([], true);
  wp_send_json_success([
    'deleted' => $deleted,
    'unread_count' => 0,
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
