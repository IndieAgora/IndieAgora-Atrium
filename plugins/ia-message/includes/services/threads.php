<?php
if (!defined('ABSPATH')) exit;

function ia_message_threads_for_user(int $phpbb_user_id, int $limit = 50, int $offset = 0): array {
  global $wpdb;

  $threads = ia_message_tbl('ia_msg_threads');
  $members = ia_message_tbl('ia_msg_thread_members');
  $msgs    = ia_message_tbl('ia_msg_messages');

  $limit  = min(100, max(1, (int)$limit));
  $offset = max(0, (int)$offset);

  // Pull last message preview via last_message_id
  $sql = "
    SELECT
      t.*,
      lm.body AS last_body
    FROM {$threads} t
    INNER JOIN {$members} m ON m.thread_id = t.id
    LEFT JOIN {$msgs} lm ON lm.id = t.last_message_id
    WHERE m.phpbb_user_id = %d
    ORDER BY COALESCE(t.last_activity_at, t.updated_at) DESC
    LIMIT %d OFFSET %d
  ";

  return (array) $wpdb->get_results($wpdb->prepare($sql, $phpbb_user_id, $limit, $offset), ARRAY_A);
}

function ia_message_get_thread(int $thread_id): array {
  global $wpdb;
  $threads = ia_message_tbl('ia_msg_threads');

  $row = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM {$threads} WHERE id = %d LIMIT 1", $thread_id),
    ARRAY_A
  );
  return is_array($row) ? $row : [];
}

function ia_message_user_in_thread(int $thread_id, int $phpbb_user_id): bool {
  global $wpdb;
  $members = ia_message_tbl('ia_msg_thread_members');

  $sql = "SELECT id FROM {$members} WHERE thread_id = %d AND phpbb_user_id = %d LIMIT 1";
  $id = $wpdb->get_var($wpdb->prepare($sql, $thread_id, $phpbb_user_id));

  return !empty($id);
}


function ia_message_thread_member_phpbb_ids(int $thread_id): array {
  global $wpdb;
  $members = ia_message_tbl('ia_msg_thread_members');
  $rows = $wpdb->get_col($wpdb->prepare(
    "SELECT phpbb_user_id FROM {$members} WHERE thread_id = %d",
    $thread_id
  ));
  $out = [];
  if ($rows) {
    foreach ($rows as $r) {
      $id = (int)$r;
      if ($id > 0) $out[] = $id;
    }
  }
  return array_values(array_unique($out));
}


function ia_message_touch_thread(int $thread_id, int $last_message_id): void {
  global $wpdb;
  $threads = ia_message_tbl('ia_msg_threads');

  $now = ia_message_now_sql();

  $wpdb->update(
    $threads,
    [
      'last_message_id'  => $last_message_id,
      'last_activity_at' => $now,
      'updated_at'       => $now,
    ],
    ['id' => $thread_id],
    ['%d','%s','%s'],
    ['%d']
  );
}

/**
 * Create or get a DM thread by two phpbb ids (allows messaging self).
 * thread_key = dm:min:max
 */
function ia_message_get_or_create_dm(int $a, int $b): int {
  global $wpdb;

  $threads = ia_message_tbl('ia_msg_threads');
  $members = ia_message_tbl('ia_msg_thread_members');

  $min = min($a, $b);
  $max = max($a, $b);
  $key = "dm:$min:$max";

  $existing = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$threads} WHERE thread_key = %s LIMIT 1", $key));
  if ($existing > 0) return $existing;

  $now = ia_message_now_sql();
  $wpdb->insert($threads, [
    'thread_key'       => $key,
    'type'             => 'dm',
    'last_message_id'  => null,
    'last_activity_at' => $now,
    'created_at'       => $now,
    'updated_at'       => $now,
  ], ['%s','%s','%d','%s','%s','%s']);

  $tid = (int) $wpdb->insert_id;
  if ($tid <= 0) return 0;

  ia_message_upsert_member($tid, $a);
  ia_message_upsert_member($tid, $b);

  return $tid;
}

/**
 * Create a new group thread.
 * thread_key = grp:<uuid>
 */
function ia_message_create_group_thread(int $creator_phpbb_id, array $member_phpbb_ids, string $title = '', string $avatar_url = ''): int {
  global $wpdb;

  $threads = ia_message_tbl('ia_msg_threads');
  $creator_phpbb_id = (int)$creator_phpbb_id;
  if ($creator_phpbb_id <= 0) return 0;

  $clean = [];
  foreach ((array)$member_phpbb_ids as $id) {
    $pid = (int)$id;
    if ($pid > 0) $clean[$pid] = true;
  }
  $clean[$creator_phpbb_id] = true;
  $members_list = array_keys($clean);

  // Require at least 2 members for a group (including creator).
  if (count($members_list) < 2) return 0;

  $now = ia_message_now_sql();
  $uuid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : (string)uniqid('g', true);
  $key = 'grp:' . $uuid;

  $title = trim((string)$title);
  if ($title !== '') $title = mb_substr($title, 0, 190);
  $avatar_url = trim((string)$avatar_url);
  if ($avatar_url !== '' && strlen($avatar_url) > 2048) $avatar_url = substr($avatar_url, 0, 2048);

  $wpdb->insert($threads, [
    'thread_key'       => $key,
    'type'             => 'group',
    'title'            => ($title !== '' ? $title : null),
    'avatar_url'       => ($avatar_url !== '' ? $avatar_url : null),
    'last_message_id'  => null,
    'last_activity_at' => $now,
    'created_at'       => $now,
    'updated_at'       => $now,
  ], ['%s','%s','%s','%s','%d','%s','%s','%s']);

  $tid = (int)$wpdb->insert_id;
  if ($tid <= 0) return 0;

  foreach ($members_list as $pid) {
    ia_message_upsert_member($tid, (int)$pid);
  }

  // Creator is group moderator.
  if (function_exists('ia_message_set_member_mod')) {
    ia_message_set_member_mod($tid, $creator_phpbb_id, 1);
  }

  return $tid;
}

function ia_message_upsert_member(int $thread_id, int $phpbb_user_id): void {
  global $wpdb;
  $members = ia_message_tbl('ia_msg_thread_members');
  $now = ia_message_now_sql();

  $wpdb->query($wpdb->prepare(
    "INSERT IGNORE INTO {$members}
      (thread_id, phpbb_user_id, last_read_at, last_read_message_id, is_muted, is_pinned, is_mod, created_at, updated_at)
     VALUES (%d, %d, %s, NULL, 0, 0, 0, %s, %s)",
    $thread_id, $phpbb_user_id, '1970-01-01 00:00:00', $now, $now
  ));
}

function ia_message_set_member_mod(int $thread_id, int $phpbb_user_id, int $is_mod): void {
  global $wpdb;
  $members = ia_message_tbl('ia_msg_thread_members');
  $thread_id = (int)$thread_id;
  $phpbb_user_id = (int)$phpbb_user_id;
  if ($thread_id <= 0 || $phpbb_user_id <= 0) return;
  $is_mod = $is_mod ? 1 : 0;
  $now = ia_message_now_sql();
  $wpdb->query($wpdb->prepare(
    "UPDATE {$members} SET is_mod=%d, updated_at=%s WHERE thread_id=%d AND phpbb_user_id=%d",
    $is_mod, $now, $thread_id, $phpbb_user_id
  ));
}

function ia_message_member_is_mod(int $thread_id, int $phpbb_user_id): bool {
  global $wpdb;
  $members = ia_message_tbl('ia_msg_thread_members');
  $thread_id = (int)$thread_id;
  $phpbb_user_id = (int)$phpbb_user_id;
  if ($thread_id <= 0 || $phpbb_user_id <= 0) return false;
  $v = $wpdb->get_var($wpdb->prepare(
    "SELECT is_mod FROM {$members} WHERE thread_id=%d AND phpbb_user_id=%d LIMIT 1",
    $thread_id, $phpbb_user_id
  ));
  return ((int)$v) === 1;
}

function ia_message_remove_member(int $thread_id, int $phpbb_user_id): void {
  global $wpdb;
  $members = ia_message_tbl('ia_msg_thread_members');
  $thread_id = (int)$thread_id;
  $phpbb_user_id = (int)$phpbb_user_id;
  if ($thread_id <= 0 || $phpbb_user_id <= 0) return;
  $wpdb->delete($members, ['thread_id' => $thread_id, 'phpbb_user_id' => $phpbb_user_id], ['%d','%d']);
}

function ia_message_get_thread_members(int $thread_id): array {
  global $wpdb;
  $members = ia_message_tbl('ia_msg_thread_members');
  $thread_id = (int)$thread_id;
  if ($thread_id <= 0) return [];
  $rows = $wpdb->get_results($wpdb->prepare(
    "SELECT phpbb_user_id, is_mod FROM {$members} WHERE thread_id=%d ORDER BY is_mod DESC, phpbb_user_id ASC",
    $thread_id
  ), ARRAY_A);
  return is_array($rows) ? $rows : [];
}

function ia_message_create_invite(int $thread_id, int $inviter_phpbb, int $invitee_phpbb): int {
  global $wpdb;
  $invites = ia_message_tbl('ia_msg_thread_invites');
  $thread_id = (int)$thread_id;
  $inviter_phpbb = (int)$inviter_phpbb;
  $invitee_phpbb = (int)$invitee_phpbb;
  if ($thread_id <= 0 || $inviter_phpbb <= 0 || $invitee_phpbb <= 0) return 0;
  $now = ia_message_now_sql();
  // Best effort: avoid duplicate pending invites.
  $wpdb->query($wpdb->prepare(
    "INSERT INTO {$invites} (thread_id, inviter_phpbb_user_id, invitee_phpbb_user_id, status, created_at, responded_at)
     VALUES (%d,%d,%d,'pending',%s,NULL)",
    $thread_id, $inviter_phpbb, $invitee_phpbb, $now
  ));
  return (int)$wpdb->insert_id;
}

function ia_message_get_invite(int $invite_id): array {
  global $wpdb;
  $invites = ia_message_tbl('ia_msg_thread_invites');
  $invite_id = (int)$invite_id;
  if ($invite_id <= 0) return [];
  $row = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$invites} WHERE id=%d LIMIT 1",
    $invite_id
  ), ARRAY_A);
  return is_array($row) ? $row : [];
}

function ia_message_accept_invite(int $invite_id): void {
  global $wpdb;
  $invites = ia_message_tbl('ia_msg_thread_invites');
  $invite_id = (int)$invite_id;
  if ($invite_id <= 0) return;
  $now = ia_message_now_sql();
  $wpdb->query($wpdb->prepare(
    "UPDATE {$invites} SET status='accepted', responded_at=%s WHERE id=%d AND status='pending'",
    $now, $invite_id
  ));
}

function ia_message_ignore_invite(int $invite_id): void {
  global $wpdb;
  $invites = ia_message_tbl('ia_msg_thread_invites');
  $invite_id = (int)$invite_id;
  if ($invite_id <= 0) return;
  $now = ia_message_now_sql();
  $wpdb->query($wpdb->prepare(
    "UPDATE {$invites} SET status='ignored', responded_at=%s WHERE id=%d AND status='pending'",
    $now, $invite_id
  ));
}

function ia_message_get_pending_invites_for_user(int $invitee_phpbb): array {
  global $wpdb;
  $invites = ia_message_tbl('ia_msg_thread_invites');
  $threads = ia_message_tbl('ia_msg_threads');
  $invitee_phpbb = (int)$invitee_phpbb;
  if ($invitee_phpbb <= 0) return [];
  $rows = $wpdb->get_results($wpdb->prepare(
    "SELECT i.id, i.thread_id, i.inviter_phpbb_user_id, t.title AS thread_title, t.avatar_url AS thread_avatar_url
     FROM {$invites} i
     INNER JOIN {$threads} t ON t.id=i.thread_id
     WHERE i.invitee_phpbb_user_id=%d AND i.status='pending'
     ORDER BY i.created_at DESC",
    $invitee_phpbb
  ), ARRAY_A);
  return is_array($rows) ? $rows : [];
}


/**
 * Total unread messages for a phpbb_user_id.
 * Unread = messages with id > last_read_message_id per thread membership.
 */
function ia_message_total_unread(int $phpbb_user_id): int {
  global $wpdb;
  $phpbb_user_id = (int)$phpbb_user_id;
  if ($phpbb_user_id <= 0) return 0;

  $members = ia_message_tbl('ia_msg_thread_members');
  $threads = ia_message_tbl('ia_msg_threads');
  $msgs    = ia_message_tbl('ia_msg_messages');

  // last_read_message_id may be NULL => treat as 0
  $sql = "
    SELECT SUM(GREATEST(0, (
      SELECT COUNT(1)
      FROM {$msgs} mm
      WHERE mm.thread_id = m.thread_id
        AND mm.id > COALESCE(m.last_read_message_id, 0)
    ))) AS c
    FROM {$members} m
    INNER JOIN {$threads} t ON t.id = m.thread_id
    WHERE m.phpbb_user_id = %d
  ";

  $c = $wpdb->get_var($wpdb->prepare($sql, $phpbb_user_id));
  return (int)($c ?: 0);
}


function ia_message_set_last_read(int $thread_id, int $phpbb_user_id, int $last_message_id): void {
  global $wpdb;
  $thread_id = (int)$thread_id;
  $phpbb_user_id = (int)$phpbb_user_id;
  $last_message_id = (int)$last_message_id;
  if ($thread_id <= 0 || $phpbb_user_id <= 0 || $last_message_id <= 0) return;

  $members = ia_message_tbl('ia_msg_thread_members');
  $now = current_time('mysql');

  // Only advance the pointer (never move backwards)
  $wpdb->query(
    $wpdb->prepare(
      "UPDATE {$members}
       SET last_read_at = %s,
           last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), %d),
           updated_at = %s
       WHERE thread_id = %d AND phpbb_user_id = %d",
      $now, $last_message_id, $now, $thread_id, $phpbb_user_id
    )
  );
}
