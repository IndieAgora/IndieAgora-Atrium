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
    'last_activity_at' => null,
    'created_at'       => $now,
    'updated_at'       => $now,
  ], ['%s','%s','%d','%s','%s','%s']);

  $tid = (int) $wpdb->insert_id;
  if ($tid <= 0) return 0;

  ia_message_upsert_member($tid, $a);
  ia_message_upsert_member($tid, $b);

  return $tid;
}

function ia_message_upsert_member(int $thread_id, int $phpbb_user_id): void {
  global $wpdb;
  $members = ia_message_tbl('ia_msg_thread_members');
  $now = ia_message_now_sql();

  $wpdb->query($wpdb->prepare(
    "INSERT IGNORE INTO {$members}
      (thread_id, phpbb_user_id, last_read_at, last_read_message_id, is_muted, is_pinned, created_at, updated_at)
     VALUES (%d, %d, %s, NULL, 0, 0, %s, %s)",
    $thread_id, $phpbb_user_id, '1970-01-01 00:00:00', $now, $now
  ));
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
