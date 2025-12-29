<?php
if (!defined('ABSPATH')) exit;

function ia_message_messages_in_thread(int $thread_id, int $limit = 100, int $offset = 0): array {
  global $wpdb;

  $messages = ia_message_tbl('ia_msg_messages');

  $limit  = min(500, max(1, (int)$limit));
  $offset = max(0, (int)$offset);

  $sql = "
    SELECT *
    FROM {$messages}
    WHERE thread_id = %d AND deleted_at IS NULL
    ORDER BY created_at DESC
    LIMIT %d OFFSET %d
  ";

  return (array) $wpdb->get_results($wpdb->prepare($sql, $thread_id, $limit, $offset), ARRAY_A);
}

function ia_message_insert_message(int $thread_id, int $author_phpbb_user_id, string $body, string $format = 'plain'): int {
  global $wpdb;

  $messages = ia_message_tbl('ia_msg_messages');
  $now = ia_message_now_sql();

  $ok = $wpdb->insert($messages, [
    'thread_id'             => $thread_id,
    'author_phpbb_user_id'  => $author_phpbb_user_id,
    'body'                  => $body,
    'body_format'           => $format,
    'created_at'            => $now,
    'edited_at'             => null,
    'deleted_at'            => null,
  ], ['%d','%d','%s','%s','%s','%s','%s']);

  if (!$ok) return 0;
  return (int) $wpdb->insert_id;
}

/**
 * Back-compat wrappers (Support/AJAX used older names in early drafts)
 */
function ia_message_get_messages(int $thread_id, int $limit = 100, int $offset = 0): array {
  return ia_message_messages_in_thread($thread_id, $limit, $offset);
}

function ia_message_add_message(int $thread_id, int $author_phpbb_user_id, string $body, string $format = 'plain'): int {
  return ia_message_insert_message($thread_id, $author_phpbb_user_id, $body, $format);
}
