<?php
if (!defined('ABSPATH')) exit;

/**
 * IA User -> IA Message Bridge
 * Supplies user labels by phpbb_user_id.
 * (No usernames matching for identity; labels are for display only.)
 */

add_filter('ia_message_user_label', function(string $label, int $phpbb_user_id): string {
  $phpbb_user_id = (int)$phpbb_user_id;
  if ($phpbb_user_id <= 0) return $label;

  // Preferred: if IA_User exposes a profile lookup
  if (class_exists('IA_User') && method_exists('IA_User', 'instance')) {
    $iu = IA_User::instance();

    // If you already have a helper like this, use it:
    if (is_object($iu) && method_exists($iu, 'phpbb_user_display_name')) {
      $name = (string) $iu->phpbb_user_display_name($phpbb_user_id);
      $name = trim($name);
      if ($name !== '') return $name;
    }
  }

  // Fallback: try direct phpbb_users lookup ONLY if ia-user already does it elsewhere.
  // If you do NOT want any DB here, remove this block and implement via your existing ia-user resolver.
  global $wpdb;
  $t = $wpdb->get_var("SHOW TABLES LIKE 'phpbb_users'");
  if ($t === 'phpbb_users') {
    $name = (string) $wpdb->get_var($wpdb->prepare(
      "SELECT username FROM phpbb_users WHERE user_id = %d LIMIT 1",
      $phpbb_user_id
    ));
    $name = trim($name);
    if ($name !== '') return $name;
  }

  return $label;
}, 10, 2);
