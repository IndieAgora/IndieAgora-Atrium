<?php
if (!defined('ABSPATH')) exit;

function ia_message_ajax_threads(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $limit  = isset($_POST['limit']) ? (int)$_POST['limit'] : 50;
  $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;

  $rows = ia_message_threads_for_user($me, $limit, $offset);

  // Render threads for UI (adds title from dm key, etc.)
  $threads = ia_message_render_threads($rows, $me);

  // Filter out blocked DMs.
  $threads = array_values(array_filter($threads, function($t) use ($me){
    $other = isset($t['dm_other_phpbb_user_id']) ? (int)$t['dm_other_phpbb_user_id'] : 0;
    if ($other > 0 && ia_user_rel_is_blocked_any($me, $other)) return false;
    return true;
  }));

  // Add last_preview (safe text) for list
  foreach ($threads as $i => $t) {
    $last_body = isset($rows[$i]['last_body']) ? (string)$rows[$i]['last_body'] : '';
    $last_body = function_exists('ia_message_maybe_unslash') ? ia_message_maybe_unslash($last_body) : wp_unslash($last_body);
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

  // Thread meta (type checks below rely on this)
  $trow = ia_message_get_thread($thread_id);
  $ttype = isset($trow['type']) ? (string)$trow['type'] : '';


  // Cross-platform block: deny viewing DM thread if either user has blocked the other.
  if ($ttype === 'dm') {
    $members = function_exists('ia_message_thread_member_phpbb_ids') ? ia_message_thread_member_phpbb_ids($thread_id) : [];
    foreach ($members as $pid) {
      $pid=(int)$pid; if ($pid>0 && $pid!==$me && ia_user_rel_is_blocked_any($me, $pid)) { ia_message_json_err('blocked', 403); }
    }
  }

  // DM privacy: block sending if the other party disallows messages (admin bypass).
  if ($ttype === 'dm') {
    $members = function_exists('ia_message_thread_member_phpbb_ids') ? ia_message_thread_member_phpbb_ids($thread_id) : [];
    foreach ($members as $pid) {
      $pid = (int)$pid;
      if ($pid > 0 && $pid !== $me) {
        if (!ia_message_recipient_allows_messages($pid, $me)) {
          ia_message_json_err('recipient_privacy', 403, ['message' => 'User privacy settings prohibit messaging this user.']);
        }
      }
    }
  }

  $trow = ia_message_get_thread($thread_id);
  if (!$trow) ia_message_json_err('thread_missing', 404);

  $limit  = isset($_POST['limit']) ? (int)$_POST['limit'] : 15;
  $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
  $limit  = min(50, max(1, $limit));
  $offset = max(0, $offset);

  // Fetch one extra row to determine has_more.
  $rows_all = ia_message_get_messages($thread_id, $limit + 1, $offset);
  $has_more = (count($rows_all) > $limit);
  if ($has_more) $rows_all = array_slice($rows_all, 0, $limit);

  $messages = ia_message_render_messages($rows_all, $me);

  // DM convenience meta (for profile deep-links)
  $dm_other_id = 0;
  $dm_other_username = "";
  $tkey = (string)($trow["thread_key"] ?? "");
  if (strpos($tkey, "dm:") === 0) {
    $parts = explode(":", $tkey);
    if (count($parts) === 3) {
      $a = (int)$parts[1];
      $b = (int)$parts[2];
      $other = ($a === $me) ? $b : $a;
      if ($other > 0 && $other !== $me) {
        $dm_other_id = $other;
        $dm_other_username = function_exists("ia_message_display_ui_name_for_phpbb_id") ? (string) ia_message_display_ui_name_for_phpbb_id($other) : (function_exists("ia_message_display_label_for_phpbb_id") ? (string) ia_message_display_label_for_phpbb_id($other) : ("User #" . $other));
      }
    }
  }

  // Mark thread as read up to the latest message returned (for unread badge accuracy)
  $last_id = 0;
  if (!empty($rows_all)) {
    $last = end($rows_all);
    $last_id = isset($last['id']) ? (int)$last['id'] : 0;
    reset($rows_all);
  }
  if ($last_id > 0 && function_exists('ia_message_set_last_read')) {
    ia_message_set_last_read($thread_id, $me, $last_id);
  }

  $thread = [
    'id' => (int)$trow['id'],
    'type' => (string)$trow['type'],
    'thread_key' => (string)$trow['thread_key'],
    'title' => (isset($trow['title']) && trim((string)$trow['title']) !== '') ? trim((string)$trow['title']) : ia_message_thread_title_from_key((string)$trow['thread_key'], $me),
    'avatarUrl' => isset($trow['avatar_url']) ? (string)$trow['avatar_url'] : '',
    'messages' => $messages,
    'dm_other_phpbb_user_id' => (int)$dm_other_id,
    'dm_other_username' => (string)$dm_other_username,
    'has_more' => $has_more,
    'next_offset' => $has_more ? ($offset + $limit) : null,
    'limit' => $limit,
    'offset' => $offset,
  ];

  ia_message_json_ok(['thread' => $thread]);
}
