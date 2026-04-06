<?php
if (!defined('ABSPATH')) exit;

function ia_notify_table(): string {
  global $wpdb;
  return $wpdb->prefix . IA_NOTIFY_TABLE;
}

function ia_notify_install(): void {
  global $wpdb;
  $t = ia_notify_table();
  $charset = $wpdb->get_charset_collate();

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  $sql = "CREATE TABLE {$t} (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient_phpbb_id INT(10) UNSIGNED NOT NULL,
    actor_phpbb_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
    type VARCHAR(64) NOT NULL,
    object_type VARCHAR(32) NOT NULL DEFAULT '',
    object_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    url TEXT NULL,
    text VARCHAR(255) NOT NULL DEFAULT '',
    meta LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    read_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY recipient_unread (recipient_phpbb_id, read_at),
    KEY recipient_created (recipient_phpbb_id, created_at),
    KEY recipient_dedupe (recipient_phpbb_id, type, object_type, object_id)
  ) {$charset};";

  dbDelta($sql);

  // One-time cleanup of legacy/experimental notification types that caused duplicates.
  ia_notify_cleanup_legacy();
}

/**
 * Ensure the notifications table exists.
 *
 * Activation hooks are not guaranteed to run in all workflows (for example,
 * when a zip is replaced while the plugin is already active).
 */
function ia_notify_ensure_tables(): void {
  global $wpdb;
  if (!$wpdb) return;
  $t = ia_notify_table();
  $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
  if ((string)$exists === (string)$t) return;
  ia_notify_install();
}

function ia_notify_cleanup_legacy(): void {
  global $wpdb;
  $t = ia_notify_table();
  
  // Earlier iterations emitted a generic "posted in Connect" notification alongside
  // a wall-post notification for the same Connect post. We no longer emit the generic
  // one, so remove legacy rows if present.
  $wpdb->query("DELETE FROM {$t} WHERE type IN ('connect_post','connect_post_created')");
}


function ia_notify_insert(array $row): int {
  global $wpdb;

  $t = ia_notify_table();

  $recipient = (int)($row['recipient_phpbb_id'] ?? 0);
  if ($recipient <= 0) return 0;

  $type = sanitize_key((string)($row['type'] ?? ''));
  if ($type === '') return 0;

  $object_type = sanitize_key((string)($row['object_type'] ?? ''));
  $object_id = (int)($row['object_id'] ?? 0);

  // Hard dedupe: if same recipient+type+object, don't create another.
  // Exception: for chat/messages we want a notification for *every* new message
  // (not just the first unread per thread), so do not dedupe message_received.
  if ($type !== 'message_received' && $object_type !== '' && $object_id > 0) {
    $existing = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT id FROM {$t} WHERE recipient_phpbb_id=%d AND type=%s AND object_type=%s AND object_id=%d LIMIT 1",
      $recipient, $type, $object_type, $object_id
    ));
    if ($existing > 0) return 0;
  }

  $now = current_time('mysql');

  $data = [
    'recipient_phpbb_id' => $recipient,
    'actor_phpbb_id' => (int)($row['actor_phpbb_id'] ?? 0),
    'type' => $type,
    'object_type' => $object_type,
    'object_id' => $object_id,
    'url' => isset($row['url']) ? (string)$row['url'] : '',
    'text' => isset($row['text']) ? wp_strip_all_tags((string)$row['text']) : '',
    'meta' => isset($row['meta']) ? wp_json_encode($row['meta']) : null,
    'created_at' => $now,
    'read_at' => null,
  ];

  $formats = ['%d','%d','%s','%s','%d','%s','%s','%s','%s','%s'];
  $ok = $wpdb->insert($t, $data, $formats);
  if (!$ok) return 0;
  return (int)$wpdb->insert_id;
}

function ia_notify_unread_count(int $recipient_phpbb_id): int {
  global $wpdb;
  $t = ia_notify_table();
  return (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$t} WHERE recipient_phpbb_id=%d AND read_at IS NULL",
    $recipient_phpbb_id
  ));
}

function ia_notify_fetch_latest(int $recipient_phpbb_id, int $limit = 25, int $after_id = 0): array {
  global $wpdb;
  $t = ia_notify_table();
  $limit = max(1, min(100, $limit));
  $after_id = max(0, (int)$after_id);

  if ($after_id > 0) {
    return (array) $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM {$t} WHERE recipient_phpbb_id=%d AND id>%d ORDER BY id DESC LIMIT %d",
      $recipient_phpbb_id, $after_id, $limit
    ), ARRAY_A);
  }

  return (array) $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$t} WHERE recipient_phpbb_id=%d ORDER BY id DESC LIMIT %d",
    $recipient_phpbb_id, $limit
  ), ARRAY_A);
}

function ia_notify_fetch_page(int $recipient_phpbb_id, int $offset = 0, int $limit = 50): array {
  global $wpdb;
  $t = ia_notify_table();
  $limit = max(1, min(100, $limit));
  $offset = max(0, (int)$offset);

  return (array) $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$t} WHERE recipient_phpbb_id=%d ORDER BY id DESC LIMIT %d OFFSET %d",
    $recipient_phpbb_id, $limit, $offset
  ), ARRAY_A);
}


function ia_notify_clear_all(int $recipient_phpbb_id): int {
  global $wpdb;
  $t = ia_notify_table();
  $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE recipient_phpbb_id=%d", $recipient_phpbb_id));
  return (int)$wpdb->rows_affected;
}

function ia_notify_mark_read(int $recipient_phpbb_id, array $ids = []): int {
  global $wpdb;
  $t = ia_notify_table();
  $now = current_time('mysql');

  if (empty($ids)) {
    $wpdb->query($wpdb->prepare(
      "UPDATE {$t} SET read_at=%s WHERE recipient_phpbb_id=%d AND read_at IS NULL",
      $now, $recipient_phpbb_id
    ));
    return (int)$wpdb->rows_affected;
  }

  $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
  if (empty($ids)) return 0;

  $placeholders = implode(',', array_fill(0, count($ids), '%d'));
  $sql = "UPDATE {$t} SET read_at=%s WHERE recipient_phpbb_id=%d AND id IN ({$placeholders})";
  $params = array_merge([$now, $recipient_phpbb_id], $ids);
  $wpdb->query($wpdb->prepare($sql, ...$params));
  return (int)$wpdb->rows_affected;
}

function ia_notify_row_to_payload(array $r): array {
  $meta = [];
  if (!empty($r['meta'])) {
    $decoded = json_decode((string)$r['meta'], true);
    if (is_array($decoded)) $meta = $decoded;
  }

  $url = (string)($r['url'] ?? '');

  // Backfill URL for legacy rows that predate URL wiring
  if ($url === '') {
    $type = (string)($r['type'] ?? '');
    $objId = (int)($r['object_id'] ?? 0);

    switch ($type) {
      case 'connect_wall_post':
        if ($objId > 0) $url = ia_notify_url_connect_post($objId, 0);
        break;
      case 'connect_post_reply':
        if ($objId > 0) {
          $cid = isset($meta['comment_id']) ? (int)$meta['comment_id'] : 0;
          $url = ia_notify_url_connect_post($objId, $cid);
        }
        break;
      case 'message_received':
        if ($objId > 0) {
          $mid = isset($meta['message_id']) ? (int)$meta['message_id'] : 0;
          $url = ia_notify_url_messages_thread($objId, $mid);
        }
        break;
      case 'discuss_new_topic':
        if ($objId > 0) {
          $pid = isset($meta['post_id']) ? (int)$meta['post_id'] : 0;
          $url = ia_notify_url_discuss_topic($objId, $pid);
        }
        break;
      case 'discuss_new_reply':
        if ($objId > 0) {
          $pid = isset($meta['post_id']) ? (int)$meta['post_id'] : 0;
          $url = ia_notify_url_discuss_topic($objId, $pid);
        }
        break;
      case 'followed_you':
        if (!empty($meta['actor_username'])) {
          $url = ia_notify_url_connect_profile((string)$meta['actor_username']);
        }
        break;
      case 'discuss_kicked':
      case 'agora_kicked':
      case 'discuss_unbanned':
        if (!empty($meta['actor_username'])) {
          $url = ia_notify_url_connect_profile((string)$meta['actor_username']);
        }
        break;
      default:
        // leave empty
        break;
    }
  }

  $payload = [
    'id' => (int)($r['id'] ?? 0),
    'recipient_phpbb_id' => (int)($r['recipient_phpbb_id'] ?? 0),
    'actor_phpbb_id' => (int)($r['actor_phpbb_id'] ?? 0),
    'type' => (string)($r['type'] ?? ''),
    'object_type' => (string)($r['object_type'] ?? ''),
    'object_id' => (int)($r['object_id'] ?? 0),
    'url' => $url,
    'text' => (string)($r['text'] ?? ''),
    'created_at' => (string)($r['created_at'] ?? ''),
    'read_at' => $r['read_at'] ? (string)$r['read_at'] : null,
    'meta' => $meta,
  ];

  if (function_exists('ia_notify_enrich_payload')) {
    $payload = ia_notify_enrich_payload($payload);
  }

  return $payload;
}
