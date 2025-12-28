<?php
if (!defined('ABSPATH')) exit;

function ia_message_render_threads(array $rows, int $me_phpbb): array {
  $out = [];
  foreach ($rows as $t) {
    $out[] = [
      'id' => (int)$t['id'],
      'type' => (string)$t['type'],
      'thread_key' => (string)$t['thread_key'],
      'last_activity_at' => (string)($t['last_activity_at'] ?? ''),
      'last_message_id' => isset($t['last_message_id']) ? (int)$t['last_message_id'] : 0,
      // For v1 we keep title minimal; later we derive names from identity service
      'title' => ia_message_thread_title_from_key((string)$t['thread_key'], $me_phpbb),
    ];
  }
  return $out;
}

function ia_message_thread_title_from_key(string $key, int $me_phpbb): string {
  if (strpos($key, 'dm:') === 0) {
    $parts = explode(':', $key);
    if (count($parts) === 3) {
      $a = (int)$parts[1];
      $b = (int)$parts[2];
      $other = ($a === $me_phpbb) ? $b : $a;
      if ($other === $me_phpbb) return 'Notes (Self)';
      return ia_message_display_label_for_phpbb_id($other);
    }
  }
  return 'Thread';
}
