<?php
if (!defined('ABSPATH')) exit;

function ia_message_render_messages(array $rows, int $me_phpbb): array {
  global $wpdb;

  $out = [];
  $reply_ids = [];

  foreach ($rows as $m) {
    $rid = isset($m['reply_to_message_id']) ? (int)$m['reply_to_message_id'] : 0;
    if ($rid > 0) $reply_ids[$rid] = true;
  }

  $reply_map = [];
  if (!empty($reply_ids)) {
    $messages = ia_message_tbl('ia_msg_messages');
    $ids = array_keys($reply_ids);
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));

    // Fetch replied-to messages in one query (best effort).
    $sql = "SELECT id, author_phpbb_user_id, body, body_format, created_at
            FROM {$messages}
            WHERE id IN ({$placeholders}) AND deleted_at IS NULL";
    $prep = $wpdb->prepare($sql, ...$ids);
    $rrows = $wpdb->get_results($prep, ARRAY_A);

    foreach ((array)$rrows as $r) {
      $id = (int)($r['id'] ?? 0);
      if ($id <= 0) continue;

      $author = (int)($r['author_phpbb_user_id'] ?? 0);
      $body = (string)($r['body'] ?? '');
      $body = function_exists('ia_message_maybe_unslash') ? ia_message_maybe_unslash($body) : wp_unslash($body);
      $plain = trim(wp_strip_all_tags($body));
      if (mb_strlen($plain) > 140) $plain = rtrim(mb_substr($plain, 0, 140)) . '…';

      $reply_map[$id] = [
        'id' => $id,
        'author_phpbb_user_id' => $author,
        'author_label' => function_exists('ia_message_display_label_for_phpbb_id') ? ia_message_display_label_for_phpbb_id($author) : ('User #' . $author),
        'excerpt' => $plain,
        'created_at' => (string)($r['created_at'] ?? ''),
      ];
    }
  }

  foreach ($rows as $m) {
    $author = (int)$m['author_phpbb_user_id'];
    $rid = isset($m['reply_to_message_id']) ? (int)$m['reply_to_message_id'] : 0;

    $out[] = [
      'id' => (int)$m['id'],
      'thread_id' => (int)$m['thread_id'],
      'author_phpbb_user_id' => $author,
      'is_mine' => ($author === $me_phpbb),
      'body' => function_exists('ia_message_maybe_unslash') ? ia_message_maybe_unslash((string)$m['body']) : wp_unslash((string)$m['body']),
      'body_format' => (string)$m['body_format'],
      'created_at' => (string)$m['created_at'],
      'reply_to_message_id' => $rid > 0 ? $rid : 0,
      'reply' => ($rid > 0 && isset($reply_map[$rid])) ? $reply_map[$rid] : null,
    ];
  }

  // We selected DESC; UI usually wants oldest->newest. Reverse for UI.
  return array_reverse($out);
}
