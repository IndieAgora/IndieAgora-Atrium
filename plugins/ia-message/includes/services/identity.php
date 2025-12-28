<?php
if (!defined('ABSPATH')) exit;

/**
 * Current WP session -> canonical phpbb_user_id.
 *
 * Doctrine:
 * - ia-message does NOT implement identity.
 * - but it may *resolve by email* as a safe fallback.
 * - never match by username.
 */
function ia_message_current_phpbb_user_id(): int {
  // 1) Platform-injected (preferred)
  $via_filter = apply_filters('ia_message_current_phpbb_user_id', 0);
  if (is_numeric($via_filter) && (int)$via_filter > 0) return (int)$via_filter;

  // 2) ia-auth helper (if present)
  if (function_exists('ia_auth_current_phpbb_user_id')) {
    $id = (int) ia_auth_current_phpbb_user_id();
    if ($id > 0) return $id;
  }

  // 3) ia-user helper (if present)
  if (class_exists('IA_User') && method_exists('IA_User', 'instance')) {
    $iu = IA_User::instance();
    if (is_object($iu) && method_exists($iu, 'current_phpbb_user_id')) {
      $id = (int) $iu->current_phpbb_user_id();
      if ($id > 0) return $id;
    }
  }

  // 4) WP session fallback (email-only)
  if (is_user_logged_in()) {
    $u = wp_get_current_user();
    $email = isset($u->user_email) ? trim((string)$u->user_email) : '';
    if ($email !== '' && is_email($email)) {
      $id = ia_message_resolve_phpbb_user_id_by_email($email);
      if ($id > 0) return $id;
    }
  }

  return 0;
}

/**
 * Email -> phpbb_user_id (Atrium doctrine: email is the only safe join key).
 *
 * Resolution order:
 * 1) Filter hook (lets ia-engine/ia-auth supply truth)
 * 2) WP-side identity map (if present): {$wpdb->prefix}ia_identity_map
 * 3) Direct phpBB table lookup (ONLY if phpbb_users exists in same DB)
 */
function ia_message_resolve_phpbb_user_id_by_email(string $email): int {
  $email = strtolower(trim($email));
  if ($email === '' || !is_email($email)) return 0;

  // 1) Platform-provided resolver
  $via_filter = apply_filters('ia_message_phpbb_user_id_by_email', 0, $email);
  if (is_numeric($via_filter) && (int)$via_filter > 0) return (int)$via_filter;

  global $wpdb;

  // 2) WP identity map (common Atrium pattern)
  $map = $wpdb->prefix . 'ia_identity_map';
  $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $map));
  if ($exists === $map) {
    $id = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT phpbb_user_id FROM {$map} WHERE email = %s LIMIT 1",
      $email
    ));
    if ($id > 0) return $id;
  }

  // 3) Direct phpBB lookup (same DB only)
  $phpbb_users = ia_message_detect_phpbb_users_table();
  if ($phpbb_users) {
    // phpBB column name is user_email in standard schema
    $id = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT user_id FROM {$phpbb_users} WHERE user_email = %s LIMIT 1",
      $email
    ));
    if ($id > 0) return $id;
  }

  // Optional: allow a callable resolver if phpBB lives in another DB/connection
  $resolver = apply_filters('ia_message_phpbb_lookup_email_callable', null);
  if (is_callable($resolver)) {
    $id = (int) call_user_func($resolver, $email);
    if ($id > 0) return $id;
  }

  return 0;
}

/**
 * Detect phpbb_users table name in the *current WP DB*.
 * - Prefers exact 'phpbb_users'
 * - Else searches for '%phpbb_users'
 * Returns table name or ''.
 */
function ia_message_detect_phpbb_users_table(): string {
  global $wpdb;

  // Fast path
  $t = $wpdb->get_var("SHOW TABLES LIKE 'phpbb_users'");
  if ($t === 'phpbb_users') return 'phpbb_users';

  // Common case: phpbb_users exists exactly with prefix phpbb_
  $t = $wpdb->get_var("SHOW TABLES LIKE 'phpbb\\_users'");
  if ($t) return (string)$t;

  // Fallback: any table ending in phpbb_users
  $t = $wpdb->get_var("SHOW TABLES LIKE '%phpbb\\_users'");
  if ($t) return (string)$t;

  return '';
}
