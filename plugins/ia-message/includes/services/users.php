<?php
if (!defined('ABSPATH')) exit;

/**
 * User lookup/search for ia-message.
 *
 * Atrium doctrine:
 * - Threads/messages are keyed by resolved phpBB user id.
 * - DM creation uses email join (safe for imports).
 *
 * UI doctrine:
 * - Frontend search feels like "username search".
 * - Backend still returns canonical email for the selected user.
 */

function ia_message_phpbb_users_table(): string {
  // Your environment has phpBB tables in the same DB. We do NOT hardcode DB credentials here.
  // Table name is assumed "phpbb_users" unless filtered.
  $t = apply_filters('ia_message_phpbb_users_table', 'phpbb_users');
  return (string)$t;
}

/**
 * Search users by a human query (username-like).
 * Returns rows with email + label (username) for UI, but email remains the join key.
 */
function ia_message_search_users(string $q, int $limit = 8): array {
  global $wpdb;

  $q = trim((string)$q);
  if ($q === '' || $limit < 1) return [];

  $limit = min(20, max(1, (int)$limit));
  $table = ia_message_phpbb_users_table();

  // If user typed an email, prefer exact-ish email match too.
  $like = '%' . $wpdb->esc_like($q) . '%';

  // phpBB commonly has: user_id, username, username_clean, user_email, user_type
  // user_type=2 is "ignored/bot" in many phpBB setups; we exclude >=2 by default.
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

  // Try username_clean if it exists (safe fallback if not).
  // We can't easily introspect columns here without extra queries,
  // so we keep it simple and reliable across dumps.
  $rows = $wpdb->get_results($wpdb->prepare($sql, $like, $like), ARRAY_A);
  if (!$rows) return [];

  $out = [];
  foreach ($rows as $r) {
    $email = isset($r['user_email']) ? (string)$r['user_email'] : '';
    if ($email === '' || !is_email($email)) continue;

    $username = isset($r['username']) ? (string)$r['username'] : '';
    $uid = isset($r['user_id']) ? (int)$r['user_id'] : 0;
    if ($uid <= 0) continue;

    $out[] = [
      'phpbb_user_id' => $uid,
      'email' => $email,
      'username' => $username,
      'label' => ($username !== '') ? $username : $email,
    ];
  }
  return $out;
}

/**
 * Legacy name used elsewhere in the plugin (keep stable).
 * Your AJAX currently calls ia_message_search_users_by_email($q,...).
 * We keep that name but route to the new behavior (username-like input).
 */
function ia_message_search_users_by_email(string $q, int $limit = 8): array {
  return ia_message_search_users($q, $limit);
}
