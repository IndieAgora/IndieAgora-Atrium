<?php
if (!defined('ABSPATH')) exit;

/**
 * Normalize the "to" field from wp_mail args into an array of email strings.
 */
function ia_notify_mail_to_list($to): array {
  if (is_array($to)) {
    $list = $to;
  } else {
    $list = preg_split('/[,;]+/', (string)$to);
  }

  $out = [];
  foreach ($list as $addr) {
    $addr = trim((string)$addr);
    if ($addr === '') continue;

    // Support "Name <email@domain>".
    if (preg_match('/<([^>]+)>/', $addr, $m)) {
      $addr = trim($m[1]);
    }

    $addr = sanitize_email($addr);
    if ($addr === '' || !is_email($addr)) continue;
    $out[] = $addr;
  }
  // De-dup
  $out = array_values(array_unique($out));
  return $out;
}

/**
 * Given a list of recipient emails, drop any recipients who have IA Notify emails disabled.
 * Returns the filtered list.
 */
function ia_notify_filter_recipients_by_prefs(array $emails): array {
  $kept = [];

  foreach ($emails as $email) {
    $u = get_user_by('email', $email);
    if (!$u || empty($u->ID)) {
      // Not a WP user email; allow by default.
      $kept[] = $email;
      continue;
    }

    if (function_exists('ia_notify_emails_enabled_for_wp') && !ia_notify_emails_enabled_for_wp((int)$u->ID)) {
      // Drop
      continue;
    }

    $kept[] = $email;
  }

  return array_values(array_unique($kept));
}

/**
 * Modify wp_mail args to remove opted-out recipients.
 */
function ia_notify_wp_mail_args_filter(array $args): array {
  // Only enforce when ia-notify preference system is available.
  if (!function_exists('ia_notify_emails_enabled_for_wp')) return $args;

  $emails = ia_notify_mail_to_list($args['to'] ?? '');
  if (!$emails) return $args;

  $kept = ia_notify_filter_recipients_by_prefs($emails);

  // Preserve original formatting: wp_mail accepts array or string.
  if (is_array($args['to'])) {
    $args['to'] = $kept;
  } else {
    $args['to'] = implode(', ', $kept);
  }

  return $args;
}

/**
 * Short-circuit wp_mail if all recipients were filtered out.
 */
function ia_notify_pre_wp_mail_gate($return, array $args) {
  if (!function_exists('ia_notify_emails_enabled_for_wp')) return $return;

  $emails = ia_notify_mail_to_list($args['to'] ?? '');
  if (!$emails) {
    // No recipients at all: let WP handle.
    return $return;
  }

  $kept = ia_notify_filter_recipients_by_prefs($emails);
  if (!$kept) {
    // Cancel send.
    return false;
  }

  return $return; // continue normal flow
}

add_filter('wp_mail', 'ia_notify_wp_mail_args_filter', 1);
add_filter('pre_wp_mail', 'ia_notify_pre_wp_mail_gate', 1, 2);
