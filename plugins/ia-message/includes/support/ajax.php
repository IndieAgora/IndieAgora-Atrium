<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_ia_message_threads', 'ia_message_ajax_threads');
add_action('wp_ajax_ia_message_thread', 'ia_message_ajax_thread');
add_action('wp_ajax_ia_message_send', 'ia_message_ajax_send');
add_action('wp_ajax_ia_message_new_dm', 'ia_message_ajax_new_dm');
add_action('wp_ajax_ia_message_user_search', 'ia_message_ajax_user_search');

function ia_message_ajax_threads(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $limit  = isset($_POST['limit']) ? (int)$_POST['limit'] : 50;
  $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;

  $rows = ia_message_threads_for_user($me, $limit, $offset);

  // Render threads for UI (adds title from dm key, etc.)
  $threads = ia_message_render_threads($rows, $me);

  // Add last_preview (safe text) for list
  foreach ($threads as $i => $t) {
    $last_body = isset($rows[$i]['last_body']) ? (string)$rows[$i]['last_body'] : '';
    $last_body = trim(wp_strip_all_tags($last_body));
    if (mb_strlen($last_body) > 90) $last_body = rtrim(mb_substr($last_body, 0, 90)) . '…';
    $threads[$i]['last_preview'] = $last_body;
  }

  ia_message_json_ok(['threads' => $threads]);
}

function ia_message_ajax_thread(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $thread_id = isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : 0;
  if ($thread_id <= 0) ia_message_json_err('bad_thread', 400);

  if (!ia_message_user_in_thread($thread_id, $me)) {
    ia_message_json_err('forbidden', 403);
  }

  $trow = ia_message_get_thread($thread_id);
  if (!$trow) ia_message_json_err('thread_missing', 404);

  $rows = ia_message_get_messages($thread_id, 200, 0);
  $messages = ia_message_render_messages($rows, $me);

  $thread = [
    'id' => (int)$trow['id'],
    'type' => (string)$trow['type'],
    'thread_key' => (string)$trow['thread_key'],
    'title' => ia_message_thread_title_from_key((string)$trow['thread_key'], $me),
    'messages' => $messages,
  ];

  ia_message_json_ok(['thread' => $thread]);
}

function ia_message_ajax_send(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $thread_id = isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : 0;
  $body = isset($_POST['body']) ? trim((string)$_POST['body']) : '';
  if ($thread_id <= 0 || $body === '') ia_message_json_err('bad_request', 400);

  if (!ia_message_user_in_thread($thread_id, $me)) {
    ia_message_json_err('forbidden', 403);
  }

  $mid = ia_message_add_message($thread_id, $me, $body, 'plain');
  if ($mid <= 0) ia_message_json_err('write_failed', 500);

  ia_message_touch_thread($thread_id, $mid);

  ia_message_json_ok(['message_id' => $mid]);
}

/**
 * Create DM by canonical phpbb_user_id (preferred).
 * Optionally send first message body in same action.
 */


/**
 * Resolve a DM target id coming from other plugins.
 *
 * Primary ID in IA Message is phpBB user id (stored on WP user meta).
 * However some surfaces may only know the WP user id. This resolver allows
 * callers to pass either:
 *  - a phpBB user id, or
 *  - a WP user id (fallback), which will be mapped to phpBB id via user meta.
 */
function ia_message_resolve_target_phpbb(int $maybe_id): int {
  if ($maybe_id <= 0) return 0;

  // 1) If any WP user already has this as their stored phpBB id, treat as phpBB id.
  $users = get_users([
    'fields'     => 'ids',
    'meta_query' => [
      'relation' => 'OR',
      [ 'key' => 'ia_phpbb_user_id', 'value' => (string)$maybe_id, 'compare' => '=' ],
      [ 'key' => 'phpbb_user_id',    'value' => (string)$maybe_id, 'compare' => '=' ],
      [ 'key' => 'ia_phpbb_uid',     'value' => (string)$maybe_id, 'compare' => '=' ],
      [ 'key' => 'phpbb_uid',        'value' => (string)$maybe_id, 'compare' => '=' ],
    ],
    'number' => 1,
  ]);
  if (!empty($users)) return $maybe_id;

  // 2) Otherwise, treat as a WP user id and map to their stored phpBB id.
  $u = get_user_by('id', $maybe_id);
  if ($u && isset($u->ID)) {
    $phpbb = (int) get_user_meta((int)$u->ID, 'ia_phpbb_user_id', true);
    if ($phpbb <= 0) $phpbb = (int) get_user_meta((int)$u->ID, 'phpbb_user_id', true);
    if ($phpbb <= 0) $phpbb = (int) get_user_meta((int)$u->ID, 'ia_phpbb_uid', true);
    if ($phpbb <= 0) $phpbb = (int) get_user_meta((int)$u->ID, 'phpbb_uid', true);
    return $phpbb > 0 ? $phpbb : 0;
  }

  return 0;
}


function ia_message_ajax_new_dm(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $to_phpbb = isset($_POST['to_phpbb']) ? (int)$_POST['to_phpbb'] : 0;

  // Allow callers to pass either phpBB user id or WP user id.
  $to_phpbb = ia_message_resolve_target_phpbb($to_phpbb);
  $body     = isset($_POST['body']) ? trim((string)$_POST['body']) : '';

  if ($to_phpbb <= 0) ia_message_json_err('bad_target', 400);

  $tid = ia_message_get_or_create_dm($me, $to_phpbb);
  if ($tid <= 0) ia_message_json_err('create_failed', 500);

  // If body provided, send first message immediately (good UX)
  if ($body !== '') {
    $mid = ia_message_add_message($tid, $me, $body, 'plain');
    if ($mid > 0) ia_message_touch_thread($tid, $mid);
  }

  ia_message_json_ok(['thread_id' => $tid]);
}

function ia_message_ajax_user_search(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $q = isset($_POST['q']) ? (string)$_POST['q'] : '';
  $rows = ia_message_search_users_by_email($q, 10);

  ia_message_json_ok(['results' => $rows]);
}
