<?php
if (!defined('ABSPATH')) exit;

/**
 * Master email kill-switch (driven by IA Notify preferences).
 *
 * Rule: if mute_all is ON OR emails is OFF, do not send notification emails.
 */
function ia_notify_emails_enabled_for_wp(int $wp_user_id): bool {
  $wp_user_id = (int)$wp_user_id;
  if ($wp_user_id <= 0) return true; // unknown recipient: do not block

  if (!function_exists('ia_notify_get_prefs')) return true;
  $prefs = ia_notify_get_prefs($wp_user_id);

  if (!empty($prefs['mute_all'])) return false;
  if (isset($prefs['emails']) && !$prefs['emails']) return false;

  return true;
}

/**
 * Convenience helper: resolve phpBB user id -> WP shadow id and apply the same gate.
 */
function ia_notify_emails_enabled_for_phpbb(int $phpbb_user_id): bool {
  $phpbb_user_id = (int)$phpbb_user_id;
  if ($phpbb_user_id <= 0) return true;

  if (!function_exists('ia_notify_wp_id_from_phpbb')) return true;
  $wp_id = (int) ia_notify_wp_id_from_phpbb($phpbb_user_id);
  if ($wp_id <= 0) return true; // no shadow yet => cannot have opted out via UI

  return ia_notify_emails_enabled_for_wp($wp_id);
}
