<?php
if (!defined('ABSPATH')) exit;

/**
 * Send an email notification for a new message.
 * Uses IA Mail Suite template if available; otherwise falls back to wp_mail().
 */
function ia_message_notify_email(int $to_phpbb_user_id, int $from_phpbb_user_id, int $thread_id, string $body): void {
  $to_phpbb_user_id   = (int)$to_phpbb_user_id;
  $from_phpbb_user_id = (int)$from_phpbb_user_id;
  $thread_id          = (int)$thread_id;

  if ($to_phpbb_user_id <= 0 || $from_phpbb_user_id <= 0 || $thread_id <= 0) return;
  if ($to_phpbb_user_id === $from_phpbb_user_id) return;

  // Respect recipient prefs (default ON)
  $prefs = function_exists('ia_message_prefs_for_phpbb') ? ia_message_prefs_for_phpbb($to_phpbb_user_id) : ['email'=>true];
  if (isset($prefs['email']) && !$prefs['email']) return;

  $to_email = function_exists('ia_message_phpbb_email_for_id') ? ia_message_phpbb_email_for_id($to_phpbb_user_id) : '';
  if ($to_email === '') return;

  $from_name = function_exists('ia_message_display_label_for_phpbb_id')
    ? ia_message_display_label_for_phpbb_id($from_phpbb_user_id)
    : ('User #' . $from_phpbb_user_id);

  $preview = wp_strip_all_tags($body);
  $preview = preg_replace('/\s+/', ' ', $preview);
  $preview = trim($preview);
  if (strlen($preview) > 160) $preview = substr($preview, 0, 157) . '...';

  // Messages tab URL
  $messages_url = home_url('/?tab=messages');
  $messages_url = add_query_arg(['ia_msg_thread' => $thread_id], $messages_url);

  // If ia-mail-suite exposes a template sender, prefer it.
  if (function_exists('ia_mail_suite_send_template')) {
    ia_mail_suite_send_template('ia_message_new_message', $to_email, [
      'from_username'   => $from_name,
      'message_preview' => $preview,
      'messages_url'    => $messages_url,
    ]);
    return;
  }

  // Filterable fallback subject/body
  $subject = sprintf('New message from %s', $from_name);
  $subject = apply_filters('ia_message_email_subject', $subject, $to_phpbb_user_id, $from_phpbb_user_id, $thread_id);

  $body_txt = "You have a new message from {$from_name}.

";
  if ($preview !== '') $body_txt .= "Preview: {$preview}

";
  $body_txt .= "Open messages: {$messages_url}
";

  $body_txt = apply_filters('ia_message_email_body', $body_txt, $to_phpbb_user_id, $from_phpbb_user_id, $thread_id);

  @wp_mail($to_email, $subject, $body_txt);
}

/**
 * Notify all other thread members by email.
 */
function ia_message_notify_thread_members(int $thread_id, int $from_phpbb_user_id, string $body): void {
  $thread_id = (int)$thread_id;
  $from_phpbb_user_id = (int)$from_phpbb_user_id;
  if ($thread_id <= 0 || $from_phpbb_user_id <= 0) return;

  if (!function_exists('ia_message_thread_member_phpbb_ids')) return;

  $members = ia_message_thread_member_phpbb_ids($thread_id);
  foreach ($members as $to) {
    $to = (int)$to;
    if ($to <= 0 || $to === $from_phpbb_user_id) continue;
    ia_message_notify_email($to, $from_phpbb_user_id, $thread_id, $body);
  }
}
