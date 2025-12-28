<?php
if (!defined('ABSPATH')) exit;

function ia_message_render_messages(array $rows, int $me_phpbb): array {
  $out = [];
  foreach ($rows as $m) {
    $author = (int)$m['author_phpbb_user_id'];
    $out[] = [
      'id' => (int)$m['id'],
      'thread_id' => (int)$m['thread_id'],
      'author_phpbb_user_id' => $author,
      'is_mine' => ($author === $me_phpbb),
      'body' => (string)$m['body'],
      'body_format' => (string)$m['body_format'],
      'created_at' => (string)$m['created_at'],
    ];
  }
  // We selected DESC; UI usually wants oldest->newest. Reverse for UI.
  return array_reverse($out);
}
