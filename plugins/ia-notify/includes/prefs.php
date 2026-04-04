<?php
if (!defined('ABSPATH')) exit;

function ia_notify_default_prefs(): array {
  return [
    'popups' => true,
    'emails' => true,
    'mute_all' => false,
  ];
}

function ia_notify_get_prefs(int $wp_user_id): array {
  $raw = get_user_meta($wp_user_id, IA_NOTIFY_PREFS_META, true);
  $prefs = ia_notify_default_prefs();
  if (is_array($raw)) {
    $prefs = array_merge($prefs, $raw);
  } elseif (is_string($raw) && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $prefs = array_merge($prefs, $decoded);
  }
  // Normalize
  $prefs['popups'] = !empty($prefs['popups']);
  $prefs['emails'] = !empty($prefs['emails']);
  $prefs['mute_all'] = !empty($prefs['mute_all']);
  return $prefs;
}

function ia_notify_set_prefs(int $wp_user_id, array $prefs): bool {
  $curr = ia_notify_default_prefs();
  $next = [
    'popups' => !empty($prefs['popups']),
    'emails' => !empty($prefs['emails']),
    'mute_all' => !empty($prefs['mute_all']),
  ];
  $merged = array_merge($curr, $next);
  return (bool) update_user_meta($wp_user_id, IA_NOTIFY_PREFS_META, $merged);
}
