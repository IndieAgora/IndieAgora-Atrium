<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_ia_message_threads', 'ia_message_ajax_threads');
add_action('wp_ajax_ia_message_thread', 'ia_message_ajax_thread');
add_action('wp_ajax_ia_message_send', 'ia_message_ajax_send');
add_action('wp_ajax_ia_message_new_dm', 'ia_message_ajax_new_dm');

// ✅ required for your username-suggest UI (still returns email for join)
add_action('wp_ajax_ia_message_user_search', 'ia_message_ajax_user_search');

function ia_message_ajax_threads(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $limit  = isset($_POST['limit']) ? (int)$_POST['limit'] : 50;
  $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;

  $threads = ia_message_get_threads_for_user($me, $limit, $offset);
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

  $rows = ia_message_get_messages($thread_id, 200, 0);
  $messages = ia_message_render_messages($rows, $me);

  ia_message_json_ok(['messages' => $messages]);
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

  $mid = ia_message_add_message($thread_id, $me, $body, 'text');
  if ($mid <= 0) ia_message_json_err('write_failed', 500);

  ia_message_touch_thread($thread_id, $mid);

  ia_message_json_ok(['message_id' => $mid]);
}

function ia_message_ajax_new_dm(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
  $self  = isset($_POST['self']) ? (int)$_POST['self'] : 0;

  if ($self === 1) {
    $tid = ia_message_get_or_create_dm($me, $me);
    if ($tid <= 0) ia_message_json_err('create_failed', 500);
    ia_message_json_ok(['thread_id' => $tid]);
  }

  if ($email === '' || !is_email($email)) ia_message_json_err('bad_email', 400);

  $other = ia_message_resolve_phpbb_user_id_by_email($email);
  if ($other <= 0) ia_message_json_err('email_not_found', 404);

  $tid = ia_message_get_or_create_dm($me, $other);
  if ($tid <= 0) ia_message_json_err('create_failed', 500);

  ia_message_json_ok(['thread_id' => $tid]);
}

function ia_message_ajax_user_search(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $q = isset($_POST['q']) ? (string)$_POST['q'] : '';
  $rows = ia_message_search_users_by_email($q, 8);

  ia_message_json_ok(['results' => $rows]);
}
