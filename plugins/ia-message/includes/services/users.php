<?php
if (!defined('ABSPATH')) exit;

function ia_message_phpbb_users_table(): string {
  // Prefer explicit filter, else attempt detection (same-db installs).
  $t = apply_filters('ia_message_phpbb_users_table', '');
  $t = is_string($t) ? trim($t) : '';

  if ($t !== '') return $t;

  // Reuse the detector from identity.php if available
  if (function_exists('ia_message_detect_phpbb_users_table')) {
    $det = ia_message_detect_phpbb_users_table();
    if (is_string($det) && $det !== '') return $det;
  }

  // Final fallback
  return 'phpbb_users';
}

/**
 * Search users by username-like query.
 * Returns phpbb_user_id + label for UI.
 */
function ia_message_search_users(string $q, int $limit = 8): array {
  global $wpdb;

  $q = trim((string)$q);
  if ($q === '' || $limit < 1) return [];

  $limit = min(20, max(1, (int)$limit));
  $table = ia_message_phpbb_users_table();

  $like = '%' . $wpdb->esc_like($q) . '%';

  $sql = "
    SELECT user_id, username, user_email
    FROM {$table}
    WHERE user_email <> ''
      AND (
        username LIKE %s
        OR user_email LIKE %s
      )
    ORDER BY user_id DESC
    LIMIT {$limit}
  ";

  $rows = $wpdb->get_results($wpdb->prepare($sql, $like, $like), ARRAY_A);
  if (!$rows) return [];

  $out = [];
  foreach ($rows as $r) {
    $uid = isset($r['user_id']) ? (int)$r['user_id'] : 0;
    if ($uid <= 0) continue;

    $email = isset($r['user_email']) ? (string)$r['user_email'] : '';
    $username = isset($r['username']) ? (string)$r['username'] : '';

    $out[] = [
      'phpbb_user_id' => $uid,
      'email' => (is_email($email) ? $email : ''),
      'username' => $username,
      'label' => ($username !== '') ? $username : ($email !== '' ? $email : ('User #' . $uid)),
    ];
  }
  return $out;
}

/**
 * Legacy name kept stable (older code calls this).
 */
function ia_message_search_users_by_email(string $q, int $limit = 8): array {
  return ia_message_search_users($q, $limit);
}

/**
 * Label for phpbb_user_id (used in thread title derivation).
 */
function ia_message_display_label_for_phpbb_id(int $phpbb_user_id): string {
  $phpbb_user_id = (int)$phpbb_user_id;
  if ($phpbb_user_id <= 0) return 'User';

  $table = ia_message_phpbb_users_table();
  global $wpdb;

  $name = $wpdb->get_var($wpdb->prepare(
    "SELECT username FROM {$table} WHERE user_id = %d LIMIT 1",
    $phpbb_user_id
  ));

  $name = is_string($name) ? trim($name) : '';
  if ($name !== '') return $name;

  return 'User #' . $phpbb_user_id;
}
