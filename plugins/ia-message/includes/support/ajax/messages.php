<?php
if (!defined('ABSPATH')) exit;

function ia_message_ajax_send(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $thread_id = isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : 0;
  $body = isset($_POST['body']) ? trim((string) wp_unslash($_POST['body'])) : '';
  $reply_to = isset($_POST['reply_to_message_id']) ? (int)$_POST['reply_to_message_id'] : 0;
  if ($thread_id <= 0 || $body === '') ia_message_json_err('bad_request', 400);

  if (!ia_message_user_in_thread($thread_id, $me)) {
    ia_message_json_err('forbidden', 403);
  }

  $trow = ia_message_get_thread($thread_id);
  $ttype = isset($trow['type']) ? (string)$trow['type'] : '';

  // Cross-platform block: deny viewing DM thread if either user has blocked the other.
  if ($ttype === 'dm') {
    $members = function_exists('ia_message_thread_member_phpbb_ids') ? ia_message_thread_member_phpbb_ids($thread_id) : [];
    foreach ($members as $pid) {
      $pid=(int)$pid; if ($pid>0 && $pid!==$me && ia_user_rel_is_blocked_any($me, $pid)) { ia_message_json_err('blocked', 403); }
    }
  }

  // DM privacy: block sending if the other party disallows messages (admin bypass).
  if ($ttype === 'dm') {
    $members = function_exists('ia_message_thread_member_phpbb_ids') ? ia_message_thread_member_phpbb_ids($thread_id) : [];
    foreach ($members as $pid) {
      $pid = (int)$pid;
      if ($pid > 0 && $pid !== $me) {
        if (!ia_message_recipient_allows_messages($pid, $me)) {
          ia_message_json_err('recipient_privacy', 403, ['message' => 'User privacy settings prohibit messaging this user.']);
        }
      }
    }
  }

  $mid = ia_message_add_message($thread_id, $me, $body, 'plain', ($reply_to > 0 ? $reply_to : null));
  if ($mid <= 0) ia_message_json_err('write_failed', 500);

  ia_message_touch_thread($thread_id, $mid);

  if (function_exists('ia_message_set_last_read')) {
    ia_message_set_last_read($thread_id, $me, $mid);
  }

  // Email notification to other members (unless disabled)
  if (function_exists('ia_message_notify_thread_members')) {
    ia_message_notify_thread_members($thread_id, $me, $body);
  }

  $members = function_exists('ia_message_thread_member_phpbb_ids') ? ia_message_thread_member_phpbb_ids($thread_id) : [];

  do_action('ia_message_sent', $mid, $thread_id, $me, $members);


  ia_message_json_ok(['message_id' => $mid]);
}

/**
 * Create DM by canonical phpbb_user_id (preferred).
 * Optionally send first message body in same action.
 */


/**
 * Resolve a DM target id coming from other plugins.
 *
 * Primary ID in IA Message is phpBB user id (stored on WP user meta).
 * However some surfaces may only know the WP user id. This resolver allows
 * callers to pass either:
 *  - a phpBB user id, or
 *  - a WP user id (fallback), which will be mapped to phpBB id via user meta.
 */

function ia_message_resolve_target_phpbb(int $maybe_id): int {
  if ($maybe_id <= 0) return 0;

  global $wpdb;

$phpbb_map = $wpdb->prefix . 'phpbb_user_map';

// 0) If phpbb_user_map exists, prefer it (canonical in this stack).
$has_phpbb_map = false;
try {
  $has_phpbb_map = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $phpbb_map)) === $phpbb_map);
} catch (Throwable $e) { $has_phpbb_map = false; }

if ($has_phpbb_map) {
  // a) If $maybe_id matches a phpBB user id in the map, treat as phpBB id.
  $wp_for_phpbb = (int) $wpdb->get_var($wpdb->prepare("SELECT wp_user_id FROM {$phpbb_map} WHERE phpbb_user_id = %d LIMIT 1", $maybe_id));
  if ($wp_for_phpbb > 0) return $maybe_id;

  // b) If $maybe_id matches a WP user id in the map, map to its phpBB user id.
  $phpbb_for_wp = (int) $wpdb->get_var($wpdb->prepare("SELECT phpbb_user_id FROM {$phpbb_map} WHERE wp_user_id = %d LIMIT 1", $maybe_id));
  if ($phpbb_for_wp > 0) return $phpbb_for_wp;
}

  $map_table = $wpdb->prefix . 'ia_identity_map';

  // 0) If Atrium identity map table exists, prefer it.
  $has_map = false;
  try {
    $has_map = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $map_table)) === $map_table);
  } catch (Throwable $e) { $has_map = false; }

  if ($has_map) {
    // a) If $maybe_id matches a phpBB user id in the map, treat as phpBB id.
    $wp_for_phpbb = (int) $wpdb->get_var($wpdb->prepare("SELECT wp_user_id FROM {$map_table} WHERE phpbb_user_id = %d LIMIT 1", $maybe_id));
    if ($wp_for_phpbb > 0) return $maybe_id;

    // b) If $maybe_id matches a WP user id in the map, map to its phpBB user id.
    $phpbb_for_wp = (int) $wpdb->get_var($wpdb->prepare("SELECT phpbb_user_id FROM {$map_table} WHERE wp_user_id = %d LIMIT 1", $maybe_id));
    if ($phpbb_for_wp > 0) return $phpbb_for_wp;
  }

  // 1) Back-compat: if any WP user already has this stored as their phpBB id, treat as phpBB id.
  $users = get_users([
    'fields'     => 'ids',
    'meta_query' => [
      'relation' => 'OR',
      [ 'key' => 'ia_phpbb_user_id', 'value' => (string)$maybe_id, 'compare' => '=' ],
      [ 'key' => 'phpbb_user_id',    'value' => (string)$maybe_id, 'compare' => '=' ],
      [ 'key' => 'ia_phpbb_uid',     'value' => (string)$maybe_id, 'compare' => '=' ],
      [ 'key' => 'phpbb_uid',        'value' => (string)$maybe_id, 'compare' => '=' ],
    ],
    'number' => 1,
  ]);
  if (!empty($users)) return $maybe_id;

  // 2) Otherwise, treat as a WP user id and map to their stored phpBB id.
  $u = get_user_by('id', $maybe_id);
  if ($u && isset($u->ID)) {
    $phpbb = (int) get_user_meta((int)$u->ID, 'ia_phpbb_user_id', true);
    if ($phpbb <= 0) $phpbb = (int) get_user_meta((int)$u->ID, 'phpbb_user_id', true);
    if ($phpbb <= 0) $phpbb = (int) get_user_meta((int)$u->ID, 'ia_phpbb_uid', true);
    if ($phpbb <= 0) $phpbb = (int) get_user_meta((int)$u->ID, 'phpbb_uid', true);
    return ($phpbb > 0) ? $phpbb : 0;
  }

  return 0;
}

function ia_message_ajax_new_dm(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $to_phpbb = isset($_POST['to_phpbb']) ? (int)$_POST['to_phpbb'] : 0;

  // Allow callers to pass either phpBB user id or WP user id.
  $to_phpbb = ia_message_resolve_target_phpbb($to_phpbb);
  $body = isset($_POST['body']) ? trim((string) wp_unslash($_POST['body'])) : '';

  if ($to_phpbb <= 0) ia_message_json_err('bad_target', 400);

  if (!ia_message_recipient_allows_messages($to_phpbb, $me)) {
    ia_message_json_err('recipient_privacy', 403, ['message' => 'User privacy settings prohibit messaging this user.']);
  }

  $tid = ia_message_get_or_create_dm($me, $to_phpbb);
  if ($tid <= 0) ia_message_json_err('create_failed', 500);

  // If body provided, send first message immediately (good UX)
  if ($body !== '') {
    $mid = ia_message_add_message($tid, $me, $body, 'plain');
    if ($mid > 0) {
      ia_message_touch_thread($tid, $mid);
      if (function_exists('ia_message_notify_thread_members')) {
        ia_message_notify_thread_members($tid, $me, $body);
      }

      $members = function_exists('ia_message_thread_member_phpbb_ids') ? ia_message_thread_member_phpbb_ids($tid) : [];
      do_action('ia_message_sent', $mid, $tid, $me, $members);
    }
  }

  ia_message_json_ok(['thread_id' => $tid]);

}

function ia_message_ajax_forward(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $source_thread_id = isset($_POST['source_thread_id']) ? (int) $_POST['source_thread_id'] : 0;
  $message_id       = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;
  $to_raw           = isset($_POST['to']) ? (string) $_POST['to'] : '';

  if ($source_thread_id <= 0 || $message_id <= 0) ia_message_json_err('bad_request', 400);

  // Security: user must be in the source thread.
  if (!function_exists('ia_message_user_in_thread') || !ia_message_user_in_thread($source_thread_id, $me)) {
    ia_message_json_err('forbidden', 403);
  }

  // Fetch the message being forwarded (must belong to the source thread).
  global $wpdb;
  $messages = ia_message_tbl('ia_msg_messages');

  $row = $wpdb->get_row(
    $wpdb->prepare(
      "SELECT id, thread_id, author_phpbb_user_id, body, body_format, created_at
       FROM {$messages}
       WHERE id = %d AND thread_id = %d AND deleted_at IS NULL
       LIMIT 1",
      $message_id,
      $source_thread_id
    ),
    ARRAY_A
  );

  if (!is_array($row) || empty($row)) ia_message_json_err('not_found', 404);

  $body = isset($row['body']) ? (string) $row['body'] : '';
  $body = function_exists('ia_message_maybe_unslash') ? ia_message_maybe_unslash($body) : wp_unslash($body);
  if (trim($body) === '') ia_message_json_err('empty_message', 400);

  // Parse recipients (comma-separated phpBB user IDs or WP user IDs).
  $ids = [];
  foreach (preg_split('/\s*,\s*/', trim($to_raw)) as $p) {
    if ($p === '') continue;
    $v = (int) $p;
    if ($v === 0 && $p !== '0') continue;
    $ids[] = $v;
  }
  $ids = array_values(array_unique($ids));
  if (count($ids) < 1) ia_message_json_err('bad_target', 400);
  if (count($ids) > 25) $ids = array_slice($ids, 0, 25);

  $thread_ids = [];
  $sent = 0;

  foreach ($ids as $t) {
    $to_phpbb = ia_message_resolve_target_phpbb((int)$t);
    if ($to_phpbb <= 0) continue;

    $tid = ia_message_get_or_create_dm($me, $to_phpbb);
    if ($tid <= 0) continue;

    $mid = ia_message_add_message($tid, $me, $body, 'forward');
    if ($mid > 0) {
      ia_message_touch_thread($tid, $mid);
      $sent++;
      $thread_ids[(string)$to_phpbb] = $tid;

      // Notify thread members as with a normal send.
      if (function_exists('ia_message_notify_thread_members')) {
        ia_message_notify_thread_members($tid, $me, $body);


      }

      $members = function_exists('ia_message_thread_member_phpbb_ids') ? ia_message_thread_member_phpbb_ids($tid) : [];
      do_action('ia_message_sent', $mid, $tid, $me, $members);
    }
  }

  if ($sent < 1) ia_message_json_err('send_failed', 500);

  ia_message_json_ok(['thread_ids' => $thread_ids]);
}

function ia_message_ajax_upload(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  if (!is_user_logged_in()) {
    ia_message_json_err('not_logged_in', 403);
  }

  if (!isset($_FILES['file'])) {
    ia_message_json_err('file_missing', 400);
  }

  require_once ABSPATH . 'wp-admin/includes/file.php';
  require_once ABSPATH . 'wp-admin/includes/media.php';
  require_once ABSPATH . 'wp-admin/includes/image.php';

  $file = $_FILES['file'];

  // Allow WP's standard upload handling (uploads go to wp-content/uploads by default).
  $overrides = [
    'test_form' => false,
    // 'mimes' => ... (leave to WP/site config)
  ];

  $move = wp_handle_upload($file, $overrides);
  if (!is_array($move) || empty($move['url'])) {
    $err = is_array($move) && !empty($move['error']) ? (string)$move['error'] : 'upload_failed';
    ia_message_json_err($err, 400);
  }

  $url  = (string) $move['url'];
  $type = isset($move['type']) ? (string)$move['type'] : '';
  $name = isset($file['name']) ? (string)$file['name'] : basename(parse_url($url, PHP_URL_PATH) ?: '');

  // Create an attachment post (helps with media management; safe even if unused later)
  $attachment_id = 0;
  $upload_dir = wp_upload_dir();
  $file_path = isset($move['file']) ? (string)$move['file'] : '';
  if ($file_path && is_array($upload_dir) && empty($upload_dir['error'])) {
    $attachment = [
      'post_mime_type' => $type,
      'post_title'     => sanitize_file_name($name),
      'post_content'   => '',
      'post_status'    => 'inherit',
    ];
    $attachment_id = wp_insert_attachment($attachment, $file_path);
    if ($attachment_id && !is_wp_error($attachment_id)) {
      $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
      if (is_array($attach_data)) {
        wp_update_attachment_metadata($attachment_id, $attach_data);
      }
    } else {
      $attachment_id = 0;
    }
  }

  $kind = 'file';
  if (strpos($type, 'image/') === 0) $kind = 'image';
  else if (strpos($type, 'video/') === 0) $kind = 'video';
  else if (strpos($type, 'audio/') === 0) $kind = 'audio';
  else if ($type === 'application/pdf' || strpos($type, 'text/') === 0 || strpos($type, 'application/') === 0) $kind = 'doc';

  ia_message_json_ok([
    'url' => $url,
    'mime' => $type,
    'kind' => $kind,
    'name' => $name,
    'attachment_id' => (int)$attachment_id,
  ]);
}

// -------------------------------------------------
// User relationships AJAX (follow/block)
// -------------------------------------------------
