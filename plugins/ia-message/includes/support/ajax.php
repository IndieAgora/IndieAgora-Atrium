<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_ia_message_threads', 'ia_message_ajax_threads');
add_action('wp_ajax_ia_message_thread', 'ia_message_ajax_thread');
add_action('wp_ajax_ia_message_send', 'ia_message_ajax_send');
add_action('wp_ajax_ia_message_new_dm', 'ia_message_ajax_new_dm');
add_action('wp_ajax_ia_message_user_search', 'ia_message_ajax_user_search');
add_action('wp_ajax_ia_message_unread_count', 'ia_message_ajax_unread_count');
add_action('wp_ajax_ia_message_prefs_get', 'ia_message_ajax_prefs_get');
add_action('wp_ajax_ia_message_prefs_set', 'ia_message_ajax_prefs_set');
add_action('wp_ajax_ia_message_upload', 'ia_message_ajax_upload');
add_action('wp_ajax_ia_message_forward', 'ia_message_ajax_forward');

function ia_message_ajax_threads(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $limit  = isset($_POST['limit']) ? (int)$_POST['limit'] : 50;
  $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;

  $rows = ia_message_threads_for_user($me, $limit, $offset);

  // Render threads for UI (adds title from dm key, etc.)
  $threads = ia_message_render_threads($rows, $me);

  // Add last_preview (safe text) for list
  foreach ($threads as $i => $t) {
    $last_body = isset($rows[$i]['last_body']) ? (string)$rows[$i]['last_body'] : '';
    $last_body = function_exists('ia_message_maybe_unslash') ? ia_message_maybe_unslash($last_body) : wp_unslash($last_body);
    $last_body = trim(wp_strip_all_tags($last_body));
    if (mb_strlen($last_body) > 90) $last_body = rtrim(mb_substr($last_body, 0, 90)) . '…';
    $threads[$i]['last_preview'] = $last_body;
  }

  ia_message_json_ok(['threads' => $threads]);
}

function ia_message_ajax_thread(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $thread_id = isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : 0;
  if ($thread_id <= 0) ia_message_json_err('bad_thread', 400);

  if (!ia_message_user_in_thread($thread_id, $me)) {
    ia_message_json_err('forbidden', 403);
  }

  // DM privacy: block sending if the other party disallows messages (admin bypass).
  $trow = ia_message_get_thread($thread_id);
  $ttype = isset($trow['type']) ? (string)$trow['type'] : '';
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

  $trow = ia_message_get_thread($thread_id);
  if (!$trow) ia_message_json_err('thread_missing', 404);

  $rows = ia_message_get_messages($thread_id, 200, 0);
  $messages = ia_message_render_messages($rows, $me);

  // DM convenience meta (for profile deep-links)
  $dm_other_id = 0;
  $dm_other_username = "";
  $tkey = (string)($trow["thread_key"] ?? "");
  if (strpos($tkey, "dm:") === 0) {
    $parts = explode(":", $tkey);
    if (count($parts) === 3) {
      $a = (int)$parts[1];
      $b = (int)$parts[2];
      $other = ($a === $me) ? $b : $a;
      if ($other > 0 && $other !== $me) {
        $dm_other_id = $other;
        $dm_other_username = function_exists("ia_message_display_label_for_phpbb_id") ? (string) ia_message_display_label_for_phpbb_id($other) : ("User #" . $other);
      }
    }
  }

  // Mark thread as read up to the latest message returned (for unread badge accuracy)
  $last_id = 0;
  if (!empty($rows)) {
    $last = end($rows);
    $last_id = isset($last['id']) ? (int)$last['id'] : 0;
    reset($rows);
  }
  if ($last_id > 0 && function_exists('ia_message_set_last_read')) {
    ia_message_set_last_read($thread_id, $me, $last_id);
  }

  $thread = [
    'id' => (int)$trow['id'],
    'type' => (string)$trow['type'],
    'thread_key' => (string)$trow['thread_key'],
    'title' => ia_message_thread_title_from_key((string)$trow['thread_key'], $me),
    'messages' => $messages,
    'dm_other_phpbb_user_id' => (int)$dm_other_id,
    'dm_other_username' => (string)$dm_other_username,
  ];

  ia_message_json_ok(['thread' => $thread]);
}

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

  // DM privacy: block sending if the other party disallows messages (admin bypass).
  $trow = ia_message_get_thread($thread_id);
  $ttype = isset($trow['type']) ? (string)$trow['type'] : '';
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
    }
  }

  if ($sent < 1) ia_message_json_err('send_failed', 500);

  ia_message_json_ok(['thread_ids' => $thread_ids]);
}


function ia_message_ajax_user_search(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $q = isset($_POST['q']) ? trim((string) wp_unslash($_POST['q'])) : '';
  if ($q === '') {
    ia_message_json_ok(['results' => []]);
  }

  $q_s = sanitize_text_field($q);

  // Prefer the same approach as Connect: search WP shadow users, then map to phpBB ids.
  $results = [];
  if (class_exists('WP_User_Query')) {
    $query = new WP_User_Query([
      'search'         => '*' . $q_s . '*',
      'search_columns' => ['user_login', 'user_nicename', 'display_name'],
      'number'         => 10,
      'fields'         => ['ID', 'user_login', 'display_name'],
    ]);

    $meta_keys = [
      'ia_phpbb_user_id',
      'phpbb_user_id',
      'ia_phpbb_uid',
      'phpbb_uid',
      'ia_identity_phpbb',
    ];

    foreach ($query->get_results() as $u) {
      $uid = (int) $u->ID;
      if ($uid <= 0) continue;

      $phpbb = '';
      foreach ($meta_keys as $k) {
        $v = (string) get_user_meta($uid, $k, true);
        if ($v !== '') { $phpbb = $v; break; }
      }

      $phpbb_id = (int) ($phpbb ?: 0);
      if ($phpbb_id <= 0) continue;


      // Respect Connect privacy: allow_messages (admin bypass).
      if (!ia_message_recipient_allows_messages($phpbb_id, $me)) {
        continue;
      }

      $results[] = [
        'wp_user_id'    => $uid,
        'username'      => (string) $u->user_login,
        'display'       => (string) ($u->display_name ?: $u->user_login),
        'phpbb_user_id' => $phpbb_id,
        'avatarUrl'     => function_exists('ia_message_avatar_url_for_wp_user_id')
          ? ia_message_avatar_url_for_wp_user_id($uid, 64)
          : get_avatar_url($uid, ['size' => 64]),
      ];
    }
  }

  // Fallback: direct phpBB table search (less precise, but better than nothing).
  if (!$results) {
    $rows = ia_message_search_users_by_email($q_s, 10);
    foreach ($rows as $r) {
      $phpbb_id = isset($r['phpbb_user_id']) ? (int) $r['phpbb_user_id'] : 0;
      if ($phpbb_id <= 0) continue;

      if (!ia_message_recipient_allows_messages($phpbb_id, $me)) {
        continue;
      }
      $username = isset($r['username']) ? (string) $r['username'] : '';
      $display  = isset($r['label']) ? (string) $r['label'] : ($username !== '' ? $username : ('User #' . $phpbb_id));

      $results[] = [
        'username'      => $username,
        'display'       => $display,
        'phpbb_user_id' => $phpbb_id,
        'avatarUrl'     => '',
      ];
    }
  }

  ia_message_json_ok(['results' => $results]);
}


function ia_message_ajax_unread_count(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $count = function_exists('ia_message_total_unread') ? (int) ia_message_total_unread($me) : 0;
  ia_message_json_ok(['count' => $count]);
}


function ia_message_ajax_prefs_get(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');
  if (!is_user_logged_in()) ia_message_json_err('not_authenticated', 401);

  $uid = get_current_user_id();
  $prefs = get_user_meta($uid, 'ia_message_prefs', true);
  if (!is_array($prefs)) $prefs = [];

  $out = [
    'email' => array_key_exists('email', $prefs) ? (bool)$prefs['email'] : true,
    'popup' => array_key_exists('popup', $prefs) ? (bool)$prefs['popup'] : true,
  ];

  ia_message_json_ok($out);
}

function ia_message_ajax_prefs_set(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');
  if (!is_user_logged_in()) ia_message_json_err('not_authenticated', 401);

  $raw = isset($_POST['prefs']) ? (string)$_POST['prefs'] : '';
  $arr = json_decode($raw, true);
  if (!is_array($arr)) $arr = [];

  $prefs = [
    'email' => array_key_exists('email', $arr) ? (bool)$arr['email'] : true,
    'popup' => array_key_exists('popup', $arr) ? (bool)$arr['popup'] : true,
  ];

  update_user_meta(get_current_user_id(), 'ia_message_prefs', $prefs);
  ia_message_json_ok(['saved' => 1]);
}


/**
 * Upload a file for message composer.
 * Returns a public URL which the client embeds into message body.
 */
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