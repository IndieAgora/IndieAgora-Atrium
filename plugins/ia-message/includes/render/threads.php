<?php
if (!defined('ABSPATH')) exit;

function ia_message_render_threads(array $rows, int $me_phpbb): array {
  $out = [];
  foreach ($rows as $t) {
    $thread_key = (string)($t['thread_key'] ?? '');

    // DM convenience meta (for profile deep-links)
    $dm_other_id = 0;
    $dm_other_username = "";

    // Avatar only really matters for DMs; group/thread avatars can be added later.
    $avatar = '';
    if (strpos($thread_key, 'dm:') === 0) {
      $parts = explode(':', $thread_key);
      if (count($parts) === 3) {
        $a = (int)$parts[1];
        $b = (int)$parts[2];
        $other = ($a === $me_phpbb) ? $b : $a;
        if ($other > 0 && $other !== $me_phpbb) {
          $dm_other_id = $other;
          $dm_other_username = function_exists('ia_message_display_label_for_phpbb_id') ? (string) ia_message_display_label_for_phpbb_id($other) : ('User #' . $other);
          if (function_exists('ia_message_avatar_url_for_phpbb_id')) {
            $avatar = (string) ia_message_avatar_url_for_phpbb_id($other, 64);
          }
        }
      }
    }

    $out[] = [
      'id' => (int)$t['id'],
      'type' => (string)$t['type'],
      'thread_key' => $thread_key,
      'last_activity_at' => (string)($t['last_activity_at'] ?? ''),
      'last_message_id' => isset($t['last_message_id']) ? (int)$t['last_message_id'] : 0,
      // For v1 we keep title minimal; later we derive names from identity service
      'title' => ia_message_thread_title_from_key($thread_key, $me_phpbb),
      'avatarUrl' => $avatar,
      'dm_other_phpbb_user_id' => (int)$dm_other_id,
      'dm_other_username' => (string)$dm_other_username,
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