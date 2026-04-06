<?php
if (!defined('ABSPATH')) exit;

function ia_notify_text_excerpt(string $text, int $limit = 140): string {
  $text = trim(wp_strip_all_tags(html_entity_decode($text, ENT_QUOTES, 'UTF-8')));
  $text = preg_replace('/\s+/u', ' ', $text);
  if ($text === '') return '';
  if (function_exists('mb_strlen') && function_exists('mb_substr')) {
    if (mb_strlen($text) <= $limit) return $text;
    return rtrim(mb_substr($text, 0, $limit - 1)) . '…';
  }
  if (strlen($text) <= $limit) return $text;
  return rtrim(substr($text, 0, $limit - 1)) . '…';
}

function ia_notify_source_from_type(string $type): string {
  if (strpos($type, 'message_') === 0) return 'messages';
  if (strpos($type, 'connect_') === 0 || $type === 'followed_you') return 'connect';
  if (strpos($type, 'discuss_') === 0 || strpos($type, 'agora_') === 0) return 'discuss';
  if (strpos($type, 'stream_') === 0) return 'stream';
  return 'system';
}

function ia_notify_connect_post_excerpt(int $post_id): string {
  global $wpdb;
  if ($post_id <= 0 || !$wpdb) return '';
  $t = $wpdb->prefix . 'ia_connect_posts';
  $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
  if ((string)$exists !== (string)$t) return '';
  $row = $wpdb->get_row($wpdb->prepare("SELECT title, body FROM {$t} WHERE id=%d LIMIT 1", $post_id), ARRAY_A);
  if (!is_array($row)) return '';
  $title = ia_notify_text_excerpt((string)($row['title'] ?? ''), 100);
  if ($title !== '') return $title;
  return ia_notify_text_excerpt((string)($row['body'] ?? ''), 140);
}

function ia_notify_connect_comment_excerpt(int $comment_id): string {
  global $wpdb;
  if ($comment_id <= 0 || !$wpdb) return '';
  $t = $wpdb->prefix . 'ia_connect_comments';
  $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
  if ((string)$exists !== (string)$t) return '';
  $body = $wpdb->get_var($wpdb->prepare("SELECT body FROM {$t} WHERE id=%d LIMIT 1", $comment_id));
  return ia_notify_text_excerpt((string)$body, 140);
}

function ia_notify_message_preview(int $thread_id, int $message_id = 0): array {
  $out = ['thread_title' => '', 'preview' => ''];
  if ($thread_id <= 0) return $out;

  if (function_exists('ia_message_get_thread')) {
    try {
      $thread = ia_message_get_thread($thread_id);
      if (is_array($thread)) {
        $out['thread_title'] = ia_notify_text_excerpt((string)($thread['title'] ?? ''), 80);
      }
    } catch (Throwable $e) {}
  }

  global $wpdb;
  if (!$wpdb || !function_exists('ia_message_tbl')) return $out;

  $messages = ia_message_tbl('ia_msg_messages');
  $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $messages));
  if ((string)$exists !== (string)$messages) return $out;

  $body = '';
  if ($message_id > 0) {
    $body = (string) $wpdb->get_var($wpdb->prepare(
      "SELECT body FROM {$messages} WHERE id=%d AND thread_id=%d AND deleted_at IS NULL LIMIT 1",
      $message_id,
      $thread_id
    ));
  }
  if ($body === '') {
    $body = (string) $wpdb->get_var($wpdb->prepare(
      "SELECT body FROM {$messages} WHERE thread_id=%d AND deleted_at IS NULL ORDER BY id DESC LIMIT 1",
      $thread_id
    ));
  }
  $out['preview'] = ia_notify_text_excerpt($body, 140);
  return $out;
}

function ia_notify_enrich_payload(array $payload): array {
  $type = (string)($payload['type'] ?? '');
  $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
  $payload['source'] = ia_notify_source_from_type($type);
  $payload['actor_name'] = (string)($meta['actor_name'] ?? '');
  $payload['actor_avatar'] = (string)($meta['actor_avatar'] ?? '');
  $payload['detail'] = '';
  $payload['title'] = (string)($payload['text'] ?? '');

  switch ($type) {
    case 'message_received':
      $thread_id = (int)($meta['thread_id'] ?? ($payload['object_id'] ?? 0));
      $message_id = (int)($meta['message_id'] ?? 0);
      $msg = ia_notify_message_preview($thread_id, $message_id);
      $payload['thread_title'] = (string)($msg['thread_title'] ?? '');
      $payload['detail'] = (string)($msg['preview'] ?? '');
      if ($payload['thread_title'] !== '') {
        $payload['context'] = $payload['thread_title'];
      }
      break;

    case 'connect_wall_post':
    case 'connect_follow_post':
    case 'discuss_shared_to_connect':
      $payload['detail'] = ia_notify_connect_post_excerpt((int)($payload['object_id'] ?? 0));
      break;

    case 'connect_post_reply':
      $payload['detail'] = ia_notify_connect_comment_excerpt((int)($meta['comment_id'] ?? 0));
      if ($payload['detail'] === '') {
        $payload['detail'] = ia_notify_connect_post_excerpt((int)($payload['object_id'] ?? 0));
      }
      break;

    case 'discuss_new_topic':
    case 'discuss_new_reply':
    case 'discuss_mention':
      $topic_id = (int)($meta['topic_id'] ?? ($payload['object_id'] ?? 0));
      $post_id = (int)($meta['post_id'] ?? 0);
      if ($topic_id > 0) $payload['context'] = 'Topic #' . $topic_id;
      if ($post_id > 0) $payload['detail'] = 'Post #' . $post_id;
      break;

    case 'discuss_agora_joined':
    case 'discuss_kicked':
    case 'agora_kicked':
    case 'discuss_unbanned':
    case 'agora_unbanned':
      $forum_id = (int)($meta['forum_id'] ?? ($payload['object_id'] ?? 0));
      if ($forum_id > 0) $payload['context'] = 'Agora #' . $forum_id;
      break;
  }

  return $payload;
}
