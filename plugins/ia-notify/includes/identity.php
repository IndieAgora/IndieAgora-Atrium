<?php
if (!defined('ABSPATH')) exit;

function ia_notify_current_wp_id(): int {
  return (int) get_current_user_id();
}

function ia_notify_current_phpbb_id(): int {
  $wp_id = ia_notify_current_wp_id();
  if ($wp_id <= 0) return 0;
  return (int) get_user_meta($wp_id, 'ia_phpbb_user_id', true);
}

function ia_notify_wp_id_from_phpbb(int $phpbb_id): int {
  global $wpdb;
  $phpbb_id = (int)$phpbb_id;
  if ($phpbb_id <= 0) return 0;
  $um = $wpdb->usermeta;
  $user_id = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT user_id FROM {$um} WHERE meta_key='ia_phpbb_user_id' AND meta_value=%s LIMIT 1",
    (string)$phpbb_id
  ));
  return $user_id;
}

function ia_notify_user_display_from_phpbb(int $phpbb_id): array {
  $wp_id = ia_notify_wp_id_from_phpbb($phpbb_id);
  $u = $wp_id ? get_userdata($wp_id) : null;
  $name = ($u && isset($u->display_name)) ? (string)$u->display_name : '';
  if ($name === '') $name = 'user-' . (int)$phpbb_id;

  $username = ($u && isset($u->user_login)) ? (string)$u->user_login : '';

  $avatar = '';
  if ($wp_id) {
    $avatar = get_avatar_url($wp_id, ['size' => 64]);
  }

  return [
    'wp_user_id' => $wp_id,
    'name' => $name,
    'username' => $username,
    'avatar' => $avatar,
  ];
}
