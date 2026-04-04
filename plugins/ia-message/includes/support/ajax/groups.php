<?php
if (!defined('ABSPATH')) exit;

function ia_message_ajax_new_group(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $title = isset($_POST['title']) ? trim((string) wp_unslash($_POST['title'])) : '';
  $avatar_url = isset($_POST['avatar_url']) ? trim((string) wp_unslash($_POST['avatar_url'])) : '';
  $members_raw = isset($_POST['members']) ? (string) wp_unslash($_POST['members']) : '';
  $body = isset($_POST['body']) ? trim((string) wp_unslash($_POST['body'])) : '';

  $ids = [];
  foreach (preg_split('/\s*,\s*/', $members_raw, -1, PREG_SPLIT_NO_EMPTY) as $p) {
    $pid = (int)$p;
    if ($pid > 0) $ids[$pid] = true;
  }

  // Include creator and filter invalid/self.
  $ids[$me] = true;
  $members = array_keys($ids);

  // Filter by privacy + block rules (best effort; search already applies allow_messages).
  $final = [];
  foreach ($members as $pid) {
    $pid = (int)$pid;
    if ($pid <= 0) continue;
    if ($pid !== $me) {
      if (function_exists('ia_user_rel_is_blocked_any') && ia_user_rel_is_blocked_any($me, $pid)) continue;
      if (!ia_message_recipient_allows_messages($pid, $me)) continue;
    }
    $final[$pid] = true;
  }
  $members = array_keys($final);

  if (count($members) < 2) {
    ia_message_json_err('not_enough_members', 400, ['message' => 'Select at least one other user for a group chat.']);
  }
  if (count($members) > 60) {
    ia_message_json_err('too_many_members', 400, ['message' => 'Too many users selected.']);
  }

  $tid = function_exists('ia_message_create_group_thread')
    ? ia_message_create_group_thread($me, $members, $title, $avatar_url)
    : 0;
  if ($tid <= 0) ia_message_json_err('create_failed', 500);

  // Notify (added to group) for all members except creator.
  foreach ($members as $pid) {
    $pid = (int)$pid;
    if ($pid > 0 && $pid !== $me) {
      do_action('ia_message_group_member_added', (int)$tid, (int)$me, (int)$pid);
    }
  }

  // Optional first message
  if ($body !== '') {
    $mid = ia_message_add_message($tid, $me, $body, 'plain', null);
    if ($mid > 0) {
      ia_message_touch_thread($tid, $mid);
      if (function_exists('ia_message_set_last_read')) {
        ia_message_set_last_read($tid, $me, $mid);
      }
      if (function_exists('ia_message_notify_thread_members')) {
        ia_message_notify_thread_members($tid, $me, $body);
      }
      do_action('ia_message_sent', $mid, $tid, $me, $members);
    }
  }

  ia_message_json_ok(['thread_id' => $tid]);
}

function ia_message_ajax_group_invites(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');
  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $rows = function_exists('ia_message_get_pending_invites_for_user')
    ? ia_message_get_pending_invites_for_user($me)
    : [];

  $out = [];
  foreach ((array)$rows as $r) {
    $iid = isset($r['id']) ? (int)$r['id'] : 0;
    $tid = isset($r['thread_id']) ? (int)$r['thread_id'] : 0;
    if ($iid <= 0 || $tid <= 0) continue;
    $title = isset($r['thread_title']) ? (string)$r['thread_title'] : '';
    $avatar = isset($r['thread_avatar_url']) ? (string)$r['thread_avatar_url'] : '';
    $inviter = isset($r['inviter_phpbb_user_id']) ? (int)$r['inviter_phpbb_user_id'] : 0;
    $inviter_name = function_exists('ia_message_display_ui_name_for_phpbb_id') ? (string) ia_message_display_ui_name_for_phpbb_id($inviter) : ('User #' . $inviter);
    $out[] = [
      'invite_id' => $iid,
      'thread_id' => $tid,
      'title' => $title,
      'avatarUrl' => $avatar,
      'inviter_phpbb_user_id' => $inviter,
      'inviter_name' => $inviter_name,
    ];
  }

  ia_message_json_ok(['invites' => $out]);
}

function ia_message_ajax_group_invite_send(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');
  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $thread_id = isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : 0;
  $to_raw = isset($_POST['to_phpbb']) ? (string) wp_unslash($_POST['to_phpbb']) : '';
  $to = (int)$to_raw;
  if ($thread_id <= 0 || $to <= 0) ia_message_json_err('bad_request', 400);
  if (!ia_message_user_in_thread($thread_id, $me)) ia_message_json_err('forbidden', 403);

  // Only group mods may invite.
  if (function_exists('ia_message_member_is_mod') && !ia_message_member_is_mod($thread_id, $me)) {
    ia_message_json_err('forbidden', 403);
  }

  // Prevent inviting existing members.
  if (ia_message_user_in_thread($thread_id, $to)) {
    ia_message_json_ok(['invite_id' => 0, 'already_member' => 1]);
  }

  $iid = function_exists('ia_message_create_invite') ? (int) ia_message_create_invite($thread_id, $me, $to) : 0;
  if ($iid <= 0) ia_message_json_err('invite_failed', 500);

  do_action('ia_message_group_invited', (int)$thread_id, (int)$me, (int)$to, (int)$iid);
  ia_message_json_ok(['invite_id' => $iid]);
}

function ia_message_ajax_group_invite_respond(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');
  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $invite_id = isset($_POST['invite_id']) ? (int)$_POST['invite_id'] : 0;
  $resp = isset($_POST['response']) ? (string) wp_unslash($_POST['response']) : '';
  $resp = ($resp === 'accept') ? 'accept' : (($resp === 'ignore') ? 'ignore' : '');
  if ($invite_id <= 0 || $resp === '') ia_message_json_err('bad_request', 400);

  $invite = function_exists('ia_message_get_invite') ? (array) ia_message_get_invite($invite_id) : [];
  if (!$invite) ia_message_json_err('missing', 404);
  $to = isset($invite['invitee_phpbb_user_id']) ? (int)$invite['invitee_phpbb_user_id'] : 0;
  if ($to !== $me) ia_message_json_err('forbidden', 403);

  $thread_id = isset($invite['thread_id']) ? (int)$invite['thread_id'] : 0;
  if ($thread_id <= 0) ia_message_json_err('bad_thread', 400);

  if ($resp === 'accept') {
    if (function_exists('ia_message_accept_invite')) {
      ia_message_accept_invite($invite_id);
    }
    // Add membership.
    if (function_exists('ia_message_upsert_member')) {
      ia_message_upsert_member($thread_id, $me);
    }
    do_action('ia_message_group_invite_accepted', (int)$thread_id, (int)$me, (int)($invite['inviter_phpbb_user_id'] ?? 0), (int)$invite_id);
    ia_message_json_ok(['accepted' => 1, 'thread_id' => $thread_id]);
  }

  if (function_exists('ia_message_ignore_invite')) {
    ia_message_ignore_invite($invite_id);
  }
  do_action('ia_message_group_invite_ignored', (int)$thread_id, (int)$me, (int)($invite['inviter_phpbb_user_id'] ?? 0), (int)$invite_id);
  ia_message_json_ok(['ignored' => 1]);
}

function ia_message_ajax_group_members(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');
  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $thread_id = isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : 0;
  if ($thread_id <= 0) ia_message_json_err('bad_thread', 400);
  if (!ia_message_user_in_thread($thread_id, $me)) ia_message_json_err('forbidden', 403);

  $rows = function_exists('ia_message_get_thread_members') ? (array) ia_message_get_thread_members($thread_id) : [];
  $out = [];
  foreach ($rows as $r) {
    $pid = isset($r['phpbb_user_id']) ? (int)$r['phpbb_user_id'] : 0;
    if ($pid <= 0) continue;
    $out[] = [
      'phpbb_user_id' => $pid,
      'display' => function_exists('ia_message_display_ui_name_for_phpbb_id') ? (string) ia_message_display_ui_name_for_phpbb_id($pid) : ('User #' . $pid),
      'username' => function_exists('ia_message_display_username_for_phpbb_id') ? (string) ia_message_display_username_for_phpbb_id($pid) : '',
      'avatarUrl' => function_exists('ia_message_avatar_url_for_phpbb_id') ? (string) ia_message_avatar_url_for_phpbb_id($pid, 64) : '',
      'is_mod' => !empty($r['is_mod']) ? 1 : 0,
    ];
  }

  $is_mod = function_exists('ia_message_member_is_mod') ? (ia_message_member_is_mod($thread_id, $me) ? 1 : 0) : 0;
  ia_message_json_ok(['members' => $out, 'me_is_mod' => $is_mod]);
}

function ia_message_ajax_group_kick(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');
  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $thread_id = isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : 0;
  $kick_phpbb = isset($_POST['kick_phpbb']) ? (int)$_POST['kick_phpbb'] : 0;
  if ($thread_id <= 0 || $kick_phpbb <= 0) ia_message_json_err('bad_request', 400);
  if (!ia_message_user_in_thread($thread_id, $me)) ia_message_json_err('forbidden', 403);
  if ($kick_phpbb === $me) ia_message_json_err('cannot_kick_self', 400);

  if (function_exists('ia_message_member_is_mod') && !ia_message_member_is_mod($thread_id, $me)) {
    ia_message_json_err('forbidden', 403);
  }

  if (function_exists('ia_message_remove_member')) {
    ia_message_remove_member($thread_id, $kick_phpbb);
  }

  do_action('ia_message_group_member_kicked', (int)$thread_id, (int)$me, (int)$kick_phpbb);
  ia_message_json_ok(['kicked' => 1]);
}
