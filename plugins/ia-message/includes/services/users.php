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
        LOWER(username) LIKE LOWER(%s)
        OR LOWER(user_email) LIKE LOWER(%s)
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


/**
 * Email address for a phpbb_user_id (empty string if missing).
 */
function ia_message_phpbb_email_for_id(int $phpbb_user_id): string {
  $phpbb_user_id = (int)$phpbb_user_id;
  if ($phpbb_user_id <= 0) return '';

  $table = ia_message_phpbb_users_table();
  global $wpdb;

  $email = $wpdb->get_var($wpdb->prepare(
    "SELECT user_email FROM {$table} WHERE user_id = %d LIMIT 1",
    $phpbb_user_id
  ));

  $email = is_string($email) ? trim($email) : '';
  return is_email($email) ? $email : '';
}

/**
 * Best-effort map from phpBB user id -> WP user id (via common meta keys).
 */
function ia_message_wp_user_id_from_phpbb(int $phpbb_user_id): int {
  global $wpdb;
  $phpbb_user_id = (int)$phpbb_user_id;
  if ($phpbb_user_id <= 0) return 0;

  $keys = ['ia_phpbb_user_id', 'phpbb_user_id', 'ia_phpbb_uid', 'phpbb_uid'];
  foreach ($keys as $k) {
    $uid = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
      $k, (string)$phpbb_user_id
    ));
    if ($uid > 0) return $uid;
  }
  return 0;
}

/**
 * Best-effort avatar URL for a WP user id.
 * Prefers IA Connect profile photo when available.
 */
function ia_message_avatar_url_for_wp_user_id(int $wp_user_id, int $size = 64): string {
  $wp_user_id = (int)$wp_user_id;
  if ($wp_user_id <= 0) return '';

  // Prefer IA Connect helper if present.
  if (function_exists('ia_connect_avatar_url')) {
    try {
      $u = (string) ia_connect_avatar_url($wp_user_id, $size);
      if (trim($u) !== '') return $u;
    } catch (Throwable $e) {
      // ignore
    }
  }

  // Direct meta fallback (in case Connect is inactive but meta remains).
  if (defined('IA_CONNECT_META_PROFILE')) {
    $u = (string) get_user_meta($wp_user_id, IA_CONNECT_META_PROFILE, true);
    if (trim($u) !== '') return $u;
  }

  return (string) get_avatar_url($wp_user_id, ['size' => $size]);
}

/**
 * Best-effort avatar URL for a phpBB user id.
 * Maps phpBB -> WP user id then uses ia_message_avatar_url_for_wp_user_id().
 */
function ia_message_avatar_url_for_phpbb_id(int $phpbb_user_id, int $size = 64): string {
  $wpuid = ia_message_wp_user_id_from_phpbb((int)$phpbb_user_id);
  if ($wpuid <= 0) return '';
  return ia_message_avatar_url_for_wp_user_id($wpuid, $size);
}

/**
 * Recipient prefs (default: both on). Uses WP usermeta if mapping exists.
 */
function ia_message_prefs_for_phpbb(int $phpbb_user_id): array {
  $wpuid = ia_message_wp_user_id_from_phpbb($phpbb_user_id);
  if ($wpuid <= 0) return ['email' => true, 'popup' => true];

  $prefs = get_user_meta($wpuid, 'ia_message_prefs', true);
  if (!is_array($prefs)) $prefs = [];

  return [
    'email' => array_key_exists('email', $prefs) ? (bool)$prefs['email'] : true,
    'popup' => array_key_exists('popup', $prefs) ? (bool)$prefs['popup'] : true,
  ];
}
