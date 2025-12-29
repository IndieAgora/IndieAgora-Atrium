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
