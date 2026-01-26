<?php
if (!defined('ABSPATH')) exit;

function ia_connect_ajax_boot(): void {
  // Profile media
  add_action('wp_ajax_ia_connect_upload_profile', 'ia_connect_ajax_upload_profile');
  add_action('wp_ajax_ia_connect_upload_cover', 'ia_connect_ajax_upload_cover');

  // Search
  add_action('wp_ajax_ia_connect_user_search', 'ia_connect_ajax_user_search');
  add_action('wp_ajax_ia_connect_mention_suggest', 'ia_connect_ajax_mention_suggest');

  // Wall
  add_action('wp_ajax_ia_connect_post_create', 'ia_connect_ajax_post_create');
  add_action('wp_ajax_ia_connect_post_list', 'ia_connect_ajax_post_list');
  add_action('wp_ajax_ia_connect_comment_create', 'ia_connect_ajax_comment_create');
  add_action('wp_ajax_ia_connect_post_get', 'ia_connect_ajax_post_get');
  add_action('wp_ajax_ia_connect_post_share', 'ia_connect_ajax_post_share');
  add_action('wp_ajax_ia_connect_wall_search', 'ia_connect_ajax_wall_search');
  add_action('wp_ajax_ia_connect_mention_suggest', 'ia_connect_ajax_mention_suggest');

  // Settings
  add_action('wp_ajax_ia_connect_settings_update', 'ia_connect_ajax_settings_update');

  // Privacy + read-only activity
  add_action('wp_ajax_ia_connect_privacy_get', 'ia_connect_ajax_privacy_get');
  add_action('wp_ajax_ia_connect_privacy_update', 'ia_connect_ajax_privacy_update');
  add_action('wp_ajax_ia_connect_discuss_activity', 'ia_connect_ajax_discuss_activity');
  add_action('wp_ajax_ia_connect_account_deactivate', 'ia_connect_ajax_account_deactivate');
  add_action('wp_ajax_ia_connect_account_delete', 'ia_connect_ajax_account_delete');
  add_action('wp_ajax_ia_connect_export_data', 'ia_connect_ajax_export_data');
  add_action('wp_ajax_ia_connect_stream_activity', 'ia_connect_ajax_stream_activity');
}

function ia_connect_ajax_require_login(): int {
  $uid = (int) get_current_user_id();
  if ($uid <= 0) {
    wp_send_json_error(['message' => 'Not logged in.'], 401);
  }
  return $uid;
}

function ia_connect_ajax_check_nonce(string $key, string $action): void {
  $n = isset($_POST[$key]) ? (string) wp_unslash($_POST[$key]) : '';
  if (!ia_connect_verify_nonce($action, $n)) {
    wp_send_json_error(['message' => 'Bad nonce.'], 403);
  }
}

function ia_connect_ajax_user_search(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'user_search');

  $q = isset($_POST['q']) ? trim((string) wp_unslash($_POST['q'])) : '';
  $q = sanitize_text_field($q);
  if ($q === '') {
    wp_send_json_success(['results' => []]);
  }

  // Prefer searching canonical phpBB users (source of truth) using IA Engine credentials.
  $out = [];
  $phpbb_db = ia_connect_phpbb_db();
  $phpbb_prefix = ia_connect_phpbb_prefix();

  if ($phpbb_db) {
    $users_tbl = $phpbb_prefix . 'users';
    $like = '%' . $phpbb_db->esc_like($q) . '%';
    $rows = $phpbb_db->get_results(
      $phpbb_db->prepare(
        "SELECT user_id, username FROM {$users_tbl} WHERE username LIKE %s AND user_type <> 2 ORDER BY username_clean ASC LIMIT 10",
        $like
      ),
      ARRAY_A
    );

    foreach (($rows ?: []) as $r) {
      $phpbb_id = (int)($r['user_id'] ?? 0);
      $uname = (string)($r['username'] ?? '');
      if ($phpbb_id <= 0 || $uname === '') continue;

      $wp_id = ia_connect_map_phpbb_to_wp_id($phpbb_id);
      
      // Respect Connect profile privacy (searchable) unless admin.
      if ($wp_id > 0 && $wp_id !== $me && !ia_connect_viewer_is_admin($me) && !ia_connect_user_profile_searchable($wp_id)) {
        continue;
      }
$display = $uname;
      if ($wp_id > 0) {
        $wu = get_userdata($wp_id);
        if ($wu) $display = (string)($wu->display_name ?: $wu->user_login);
      }

      $out[] = [
        'wp_user_id'    => (int)$wp_id,
        'username'      => $uname,
        'display'       => $display,
        'phpbb_user_id' => $phpbb_id,
        'avatarUrl'     => $wp_id > 0 ? ia_connect_avatar_url($wp_id, 64) : '',
      ];
    }
  }

  // Fallback: search WP shadow users.
  if (empty($out)) {
    $query = new WP_User_Query([
      'search'         => '*' . $q . '*',
      'search_columns' => ['user_login', 'user_nicename', 'display_name'],
      'number'         => 10,
      'fields'         => ['ID', 'user_login', 'display_name'],
    ]);

    foreach ($query->get_results() as $u) {
      $uid = (int) $u->ID;
      
      if ($uid !== $me && !ia_connect_viewer_is_admin($me) && !ia_connect_user_profile_searchable($uid)) {
        continue;
      }
$phpbb = (int) get_user_meta($uid, 'ia_phpbb_user_id', true);
      if ($phpbb <= 0) $phpbb = (int) get_user_meta($uid, 'phpbb_user_id', true);

      $out[] = [
        'wp_user_id'    => $uid,
        'username'      => (string) $u->user_login,
        'display'       => (string) ($u->display_name ?: $u->user_login),
        'phpbb_user_id' => (int) $phpbb,
        'avatarUrl'     => ia_connect_avatar_url($uid, 64),
      ];
    }
  }

  wp_send_json_success(['results' => $out]);
}

function ia_connect_ajax_upload_profile(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'profile_photo');
  ia_connect_handle_profile_upload($me, 'file');
}

function ia_connect_ajax_upload_cover(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'cover_photo');
  ia_connect_handle_cover_upload($me, 'file');
}

function ia_connect_handle_profile_upload(int $wp_user_id, string $file_key): void {
  if (empty($_FILES[$file_key])) {
    wp_send_json_error(['message' => 'No file.'], 400);
  }
  $url = ia_connect_process_upload($wp_user_id, $_FILES[$file_key], 'profile');
  if (!$url) wp_send_json_error(['message' => 'Upload failed.'], 500);

  update_user_meta($wp_user_id, IA_CONNECT_META_PROFILE, $url);
  wp_send_json_success(['url' => $url]);
}

function ia_connect_handle_cover_upload(int $wp_user_id, string $file_key): void {
  if (empty($_FILES[$file_key])) {
    wp_send_json_error(['message' => 'No file.'], 400);
  }
  $url = ia_connect_process_upload($wp_user_id, $_FILES[$file_key], 'cover');
  if (!$url) wp_send_json_error(['message' => 'Upload failed.'], 500);

  update_user_meta($wp_user_id, IA_CONNECT_META_COVER, $url);
  wp_send_json_success(['url' => $url]);
}

function ia_connect_process_upload(int $wp_user_id, array $file, string $bucket): ?string {
  require_once ABSPATH . 'wp-admin/includes/file.php';
  require_once ABSPATH . 'wp-admin/includes/image.php';

  [$base, $baseurl] = ia_connect_upload_dir();
  $dir = trailingslashit($base) . 'user-' . $wp_user_id . '/' . $bucket;
  $dirurl = trailingslashit($baseurl) . 'user-' . $wp_user_id . '/' . $bucket;

  if (!wp_mkdir_p($dir)) {
    return null;
  }

  $filter = function ($u) use ($dir, $dirurl) {
    $u['path'] = $dir;
    $u['url']  = $dirurl;
    $u['subdir'] = '';
    return $u;
  };
  add_filter('upload_dir', $filter);

  $overrides = ['test_form' => false];
  $r = wp_handle_upload($file, $overrides);

  remove_filter('upload_dir', $filter);

  if (!is_array($r) || !empty($r['error']) || empty($r['url'])) {
    return null;
  }

  return esc_url_raw($r['url']);
}



// ---------- Privacy + Activity (read-only) ----------

function ia_connect_ajax_privacy_get(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'privacy_get');

  $target = isset($_POST['target_wp']) ? (int) $_POST['target_wp'] : 0;
  if ($target <= 0) $target = $me;

  if ($target !== $me && !ia_connect_viewer_is_admin($me)) {
    wp_send_json_error(['message' => 'Forbidden.'], 403);
  }

  $p = ia_connect_get_user_privacy($target);
  wp_send_json_success(['privacy' => $p]);
}

function ia_connect_ajax_privacy_update(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'privacy_update');

  $target = isset($_POST['target_wp']) ? (int) $_POST['target_wp'] : 0;
  if ($target <= 0) $target = $me;

  if ($target !== $me && !ia_connect_viewer_is_admin($me)) {
    wp_send_json_error(['message' => 'Forbidden.'], 403);
  }

  $incoming = isset($_POST['privacy']) ? $_POST['privacy'] : [];
  if (is_string($incoming)) {
    $j = json_decode(wp_unslash($incoming), true);
    if (is_array($j)) $incoming = $j;
  }
  if (!is_array($incoming)) $incoming = [];

  $p = ia_connect_update_user_privacy($target, $incoming);
  wp_send_json_success(['privacy' => $p]);
}

function ia_connect_ajax_discuss_activity(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'discuss_activity');

  $target_wp = isset($_POST['target_wp']) ? (int) $_POST['target_wp'] : 0;
  if ($target_wp <= 0) $target_wp = $me;

  $type = isset($_POST['type']) ? sanitize_key((string) wp_unslash($_POST['type'])) : 'topics_created';
  $q = isset($_POST['q']) ? sanitize_text_field((string) wp_unslash($_POST['q'])) : '';
  $page = max(1, (int)($_POST['page'] ?? 1));
  $per = max(5, min(25, (int)($_POST['per_page'] ?? 10)));

  $is_admin = ia_connect_viewer_is_admin($me);
  if ($target_wp !== $me && !$is_admin) {
    if (!ia_connect_user_profile_searchable($target_wp)) {
      wp_send_json_error(['message' => 'User privacy settings prohibit you to see this profile.'], 403);
    }
    if (!ia_connect_user_discuss_visible($target_wp)) {
      wp_send_json_success(['items' => [], 'has_more' => false]);
    }
  }

  $phpbb_id = ia_connect_user_phpbb_id($target_wp);
  if ($phpbb_id <= 0) wp_send_json_success(['items' => [], 'has_more' => false]);

  $db = ia_connect_phpbb_db();
  $px = ia_connect_phpbb_prefix();
  if (!$db) wp_send_json_error(['message' => 'Discuss DB unavailable.'], 500);

  $items = [];
  $has_more = false;
  $offset = ($page - 1) * $per;
  $limit = $per + 1;

  try {
    if ($type === 'agoras_created') {
      // Forums the user moderates (moderator_cache contains "u_<id>" tokens).
      $forums = $px . 'forums';
      $token = 'u_' . (int)$phpbb_id;
      $like = '%' . $db->esc_like($token) . '%';
      $rows = $db->get_results($db->prepare(
        "SELECT forum_id, forum_name FROM {$forums} WHERE moderator_cache LIKE %s ORDER BY forum_name ASC LIMIT %d OFFSET %d",
        $like, $limit, $offset
      ), ARRAY_A);

      foreach (($rows ?: []) as $r) {
        $fid = (int)($r['forum_id'] ?? 0);
        $name = (string)($r['forum_name'] ?? '');
        if ($fid <= 0) continue;
        $items[] = [
          'title' => $name,
          'excerpt' => '',
          'url' => ia_connect_discuss_url_agora($fid),
        ];
      }
    } elseif ($type === 'agoras_joined') {
      global $wpdb;
      $m = $wpdb->prefix . 'ia_discuss_agora_members';
      $has = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $m));
      if ($has) {
        $ids = $wpdb->get_results($wpdb->prepare(
          "SELECT forum_id FROM {$m} WHERE phpbb_user_id=%d ORDER BY joined_at DESC LIMIT %d OFFSET %d",
          $phpbb_id, $limit, $offset
        ), ARRAY_A);

        $forum_ids = [];
        foreach (($ids ?: []) as $r) {
          $fid = (int)($r['forum_id'] ?? 0);
          if ($fid > 0) $forum_ids[] = $fid;
        }

        if (!empty($forum_ids)) {
          $forums = $px . 'forums';
          $in = implode(',', array_map('intval', $forum_ids));
          $rows = $db->get_results("SELECT forum_id, forum_name FROM {$forums} WHERE forum_id IN ({$in})", ARRAY_A);
          $name_by = [];
          foreach (($rows ?: []) as $rr) $name_by[(int)$rr['forum_id']] = (string)$rr['forum_name'];

          foreach ($forum_ids as $fid) {
            $items[] = [
              'title' => $name_by[$fid] ?? ('Agora ' . $fid),
              'excerpt' => '',
              'url' => ia_connect_discuss_url_agora($fid),
            ];
          }
        }
      }
    } elseif ($type === 'topics_created') {
      $topics = $px . 'topics';
      $posts = $px . 'posts';

      $like = $q !== '' ? '%' . $db->esc_like($q) . '%' : '';
      if ($q !== '') {
        $rows = $db->get_results($db->prepare(
          "SELECT t.topic_id, t.topic_title, t.forum_id, p.post_text
           FROM {$topics} t
           LEFT JOIN {$posts} p ON p.post_id = t.topic_first_post_id
           WHERE t.topic_poster=%d AND t.topic_title LIKE %s
           ORDER BY t.topic_time DESC LIMIT %d OFFSET %d",
          $phpbb_id, $like, $limit, $offset
        ), ARRAY_A);
      } else {
        $rows = $db->get_results($db->prepare(
          "SELECT t.topic_id, t.topic_title, t.forum_id, p.post_text
           FROM {$topics} t
           LEFT JOIN {$posts} p ON p.post_id = t.topic_first_post_id
           WHERE t.topic_poster=%d
           ORDER BY t.topic_time DESC LIMIT %d OFFSET %d",
          $phpbb_id, $limit, $offset
        ), ARRAY_A);
      }

      foreach (($rows ?: []) as $r) {
        $tid = (int)($r['topic_id'] ?? 0);
        if ($tid <= 0) continue;
        $items[] = [
          'title' => (string)($r['topic_title'] ?? ''),
          'excerpt' => ia_connect_excerpt_phpbb((string)($r['post_text'] ?? '')),
          'url' => ia_connect_discuss_url_topic($tid),
        ];
      }
    } else { // replies
      $posts = $px . 'posts';
      $topics = $px . 'topics';

      $like = $q !== '' ? '%' . $db->esc_like($q) . '%' : '';
      if ($q !== '') {
        $rows = $db->get_results($db->prepare(
          "SELECT p.post_id, p.topic_id, p.post_text, t.topic_title
           FROM {$posts} p
           LEFT JOIN {$topics} t ON t.topic_id = p.topic_id
           WHERE p.poster_id=%d AND p.post_id <> t.topic_first_post_id AND p.post_text LIKE %s
           ORDER BY p.post_time DESC LIMIT %d OFFSET %d",
          $phpbb_id, $like, $limit, $offset
        ), ARRAY_A);
      } else {
        $rows = $db->get_results($db->prepare(
          "SELECT p.post_id, p.topic_id, p.post_text, t.topic_title
           FROM {$posts} p
           LEFT JOIN {$topics} t ON t.topic_id = p.topic_id
           WHERE p.poster_id=%d AND p.post_id <> t.topic_first_post_id
           ORDER BY p.post_time DESC LIMIT %d OFFSET %d",
          $phpbb_id, $limit, $offset
        ), ARRAY_A);
      }

      foreach (($rows ?: []) as $r) {
        $pid = (int)($r['post_id'] ?? 0);
        $tid = (int)($r['topic_id'] ?? 0);
        if ($pid <= 0 || $tid <= 0) continue;
        $items[] = [
          'title' => (string)($r['topic_title'] ?? ''),
          'excerpt' => ia_connect_excerpt_phpbb((string)($r['post_text'] ?? '')),
          'url' => ia_connect_discuss_url_reply($tid, $pid),
        ];
      }
    }
  } catch (Throwable $e) {
    wp_send_json_error(['message' => 'Discuss query failed.'], 500);
  }

  if (count($items) > $per) {
    $has_more = true;
    $items = array_slice($items, 0, $per);
  }

  wp_send_json_success(['items' => $items, 'has_more' => $has_more]);
}

function ia_connect_ajax_stream_activity(): void {
  // This endpoint must ALWAYS return JSON.
  // Any PHP fatals will surface to the browser as HTML, which the front-end reports as "Non-JSON response".
  // Keep this function defensive and never echo.
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'stream_activity');

  $target_wp = isset($_POST['target_wp']) ? (int) $_POST['target_wp'] : 0;
  if ($target_wp <= 0) $target_wp = $me;

  $type = isset($_POST['type']) ? sanitize_key((string) wp_unslash($_POST['type'])) : 'videos';
  $q = isset($_POST['q']) ? sanitize_text_field((string) wp_unslash($_POST['q'])) : '';
  $q_like = $q !== '' ? ('%' . $q . '%') : '';
  $page = max(1, (int)($_POST['page'] ?? 1));
  $per = max(5, min(25, (int)($_POST['per_page'] ?? 10)));

  $is_admin = ia_connect_viewer_is_admin($me);
  if ($target_wp !== $me && !$is_admin) {
    if (!ia_connect_user_profile_searchable($target_wp)) {
      wp_send_json_error(['message' => 'User privacy settings prohibit you to see this profile.'], 403);
    }
    if (!ia_connect_user_stream_visible($target_wp)) {
      wp_send_json_success(['items' => [], 'has_more' => false]);
    }
  }

  // Resolve PeerTube account/actor ids using identity map when available.
  // IMPORTANT: This is read-only profile activity. We do NOT need (or want) OAuth tokens here.
  // We DO need a correct accountId/actorId for the user, because most PeerTube activity tables key off those.
  $phpbb_id = ia_connect_user_phpbb_id($target_wp);
  if ($phpbb_id <= 0) wp_send_json_success(['items' => [], 'has_more' => false]);

  global $wpdb;
  $imap = $wpdb->prefix . 'ia_identity_map';
  $has_imap = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $imap));
  if (!$has_imap) wp_send_json_success(['items' => [], 'has_more' => false]);

  // Prefer IA Auth DB helper if available.
  $row = null;
  if (class_exists('IA_Auth') && method_exists('IA_Auth', 'instance')) {
    try {
      $ia = IA_Auth::instance();
      if (is_object($ia) && isset($ia->db) && is_object($ia->db) && method_exists($ia->db, 'get_identity_by_wp_user_id')) {
        $row = $ia->db->get_identity_by_wp_user_id($target_wp);
      }
    } catch (Throwable $e) {
      $row = null;
    }
  }
  if (!$row) {
    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT peertube_user_id, peertube_account_id, peertube_actor_id FROM {$imap} WHERE phpbb_user_id=%d LIMIT 1",
      $phpbb_id
    ), ARRAY_A);
  }

  $pt_user = (int)($row['peertube_user_id'] ?? 0);
  $pt_account = (int)($row['peertube_account_id'] ?? 0);
  $pt_actor = (int)($row['peertube_actor_id'] ?? 0);

  $pdo = ia_connect_peertube_pdo();
  $base = ia_connect_peertube_public_url();
  if (!$pdo || $base === '') {
    wp_send_json_error(['message' => 'PeerTube DB unavailable.'], 500);
  }

  // If the identity map does not yet have account/actor ids, derive them deterministically from PeerTube DB.
  // PeerTube guarantees 1 user -> 1 account -> 1 actor.
  if ($pt_user > 0 && ($pt_account <= 0 || $pt_actor <= 0)) {
    try {
      $st = $pdo->prepare('SELECT id, "actorId" FROM public.account WHERE "userId" = :uid LIMIT 1');
      $st->execute([':uid' => $pt_user]);
      $acct = $st->fetch();
      if (is_array($acct) && !empty($acct)) {
        $derived_account = (int)($acct['id'] ?? 0);
        $derived_actor = (int)($acct['actorId'] ?? 0);
        if ($pt_account <= 0 && $derived_account > 0) $pt_account = $derived_account;
        if ($pt_actor <= 0 && $derived_actor > 0) $pt_actor = $derived_actor;

        // Persist derived ids back to identity map to avoid future misses.
        if ($derived_account > 0 || $derived_actor > 0) {
          $wpdb->update(
            $imap,
            [
              'peertube_account_id' => ($pt_account > 0 ? $pt_account : null),
              'peertube_actor_id'   => ($pt_actor > 0 ? $pt_actor : null),
              'updated_at'          => gmdate('Y-m-d H:i:s'),
            ],
            ['phpbb_user_id' => $phpbb_id]
          );
        }
      }
    } catch (Throwable $e) {
      // If derivation fails, we'll just return empty results rather than fatal.
    }
  }

  $items = [];
  $has_more = false;
  $offset = ($page - 1) * $per;
  $limit = $per + 1;

  try {
    if ($type === 'videos') {
      if ($pt_account <= 0) wp_send_json_success(['items' => [], 'has_more' => false]);
      // video rows key off channelId -> videoChannel.accountId
      $sql = "SELECT v.id, v.uuid, v.name, v.url, v.description, th.\"fileUrl\" AS thumb_url, th.filename AS thumb_filename
              FROM public.video v
              JOIN public.\"videoChannel\" ch ON ch.id = v.\"channelId\"
              LEFT JOIN LATERAL (
                SELECT t.\"fileUrl\", t.filename
                FROM public.thumbnail t
                WHERE t.\"videoId\" = v.id
                ORDER BY (t.\"fileUrl\" IS NULL) ASC, t.type ASC, t.height DESC NULLS LAST
                LIMIT 1
              ) th ON TRUE
              WHERE ch.\"accountId\" = :acct
                AND (:q = '' OR v.name ILIKE :q OR v.description ILIKE :q)
              ORDER BY v.\"publishedAt\" DESC
              LIMIT {$limit} OFFSET {$offset}";
      $st = $pdo->prepare($sql);
      $st->execute([':acct' => $pt_account, ':q' => $q_like]);
      $rows = $st->fetchAll();
      foreach (($rows ?: []) as $r) {
        $thumb_url = (string)($r['thumb_url'] ?? '');
        $thumb_filename = (string)($r['thumb_filename'] ?? '');

        // PeerTube may leave thumbnail.fileUrl NULL depending on version/config.
        // If so, fall back to common static routes using filename.
        $thumb = $thumb_url !== '' ? ia_connect_join_url($base, $thumb_url) : '';
        $thumb_fallbacks = [];
        if ($thumb === '' && $thumb_filename !== '') {
          $thumb_fallbacks[] = ia_connect_join_url($base, '/static/thumbnails/' . ltrim($thumb_filename, '/'));
          $thumb_fallbacks[] = ia_connect_join_url($base, '/lazy-static/thumbnails/' . ltrim($thumb_filename, '/'));
          $thumb_fallbacks[] = ia_connect_join_url($base, '/lazy-static/previews/' . ltrim($thumb_filename, '/'));
          // As a last resort, try the same filename at root.
          $thumb_fallbacks[] = ia_connect_join_url($base, '/' . ltrim($thumb_filename, '/'));
          $thumb = $thumb_fallbacks[0];
        }
        $items[] = [
          'title' => (string)($r['name'] ?? ''),
          'excerpt' => ia_connect_excerpt_text((string)($r['description'] ?? '')),
          'url' => ia_connect_join_url($base, (string)($r['url'] ?? '')),
          'thumb' => $thumb,
          'thumb_fallbacks' => $thumb_fallbacks,
        ];
      }
    } elseif ($type === 'comments') {
      if ($pt_account <= 0) wp_send_json_success(['items' => [], 'has_more' => false]);
      $sql = "SELECT vc.url, vc.text, v.name AS video_name, th.\"fileUrl\" AS thumb_url, th.filename AS thumb_filename
              FROM public.\"videoComment\" vc
              JOIN public.video v ON v.id = vc.\"videoId\"
              LEFT JOIN LATERAL (
                SELECT t.\"fileUrl\", t.filename
                FROM public.thumbnail t
                WHERE t.\"videoId\" = v.id
                ORDER BY (t.\"fileUrl\" IS NULL) ASC, t.type ASC, t.height DESC NULLS LAST
                LIMIT 1
              ) th ON TRUE
              WHERE vc.\"accountId\" = :acct
                AND (:q = '' OR vc.text ILIKE :q OR v.name ILIKE :q)
              ORDER BY vc.\"createdAt\" DESC
              LIMIT {$limit} OFFSET {$offset}";
      $st = $pdo->prepare($sql);
      $st->execute([':acct' => $pt_account, ':q' => $q_like]);
      $rows = $st->fetchAll();
      foreach (($rows ?: []) as $r) {
        $thumb_url = (string)($r['thumb_url'] ?? '');
        $thumb_filename = (string)($r['thumb_filename'] ?? '');
        $thumb = $thumb_url !== '' ? ia_connect_join_url($base, $thumb_url) : '';
        $thumb_fallbacks = [];
        if ($thumb === '' && $thumb_filename !== '') {
          $thumb_fallbacks[] = ia_connect_join_url($base, '/static/thumbnails/' . ltrim($thumb_filename, '/'));
          $thumb_fallbacks[] = ia_connect_join_url($base, '/lazy-static/thumbnails/' . ltrim($thumb_filename, '/'));
          $thumb_fallbacks[] = ia_connect_join_url($base, '/lazy-static/previews/' . ltrim($thumb_filename, '/'));
          $thumb_fallbacks[] = ia_connect_join_url($base, '/' . ltrim($thumb_filename, '/'));
          $thumb = $thumb_fallbacks[0];
        }
        $items[] = [
          'title' => (string)($r['video_name'] ?? 'Comment'),
          'excerpt' => ia_connect_excerpt_text((string)($r['text'] ?? '')),
          'url' => ia_connect_join_url($base, (string)($r['url'] ?? '')),
          'thumb' => $thumb,
          'thumb_fallbacks' => $thumb_fallbacks,
        ];
      }
    } elseif ($type === 'likes') {
      if ($pt_account <= 0) wp_send_json_success(['items' => [], 'has_more' => false]);
      $sql = "SELECT v.id, v.name, v.url, th.\"fileUrl\" AS thumb_url, th.filename AS thumb_filename
              FROM public.\"accountVideoRate\" avr
              JOIN public.video v ON v.id = avr.\"videoId\"
              LEFT JOIN LATERAL (
                SELECT t.\"fileUrl\", t.filename
                FROM public.thumbnail t
                WHERE t.\"videoId\" = v.id
                ORDER BY (t.\"fileUrl\" IS NULL) ASC, t.type ASC, t.height DESC NULLS LAST
                LIMIT 1
              ) th ON TRUE
              WHERE avr.\"accountId\" = :acct
                AND (:q = '' OR v.name ILIKE :q)
              ORDER BY avr.\"createdAt\" DESC
              LIMIT {$limit} OFFSET {$offset}";
      $st = $pdo->prepare($sql);
      $st->execute([':acct' => $pt_account, ':q' => $q_like]);
      $rows = $st->fetchAll();
      foreach (($rows ?: []) as $r) {
        $thumb_url = (string)($r['thumb_url'] ?? '');
        $thumb_filename = (string)($r['thumb_filename'] ?? '');
        $thumb = $thumb_url !== '' ? ia_connect_join_url($base, $thumb_url) : '';
        $thumb_fallbacks = [];
        if ($thumb === '' && $thumb_filename !== '') {
          $thumb_fallbacks[] = ia_connect_join_url($base, '/static/thumbnails/' . ltrim($thumb_filename, '/'));
          $thumb_fallbacks[] = ia_connect_join_url($base, '/lazy-static/thumbnails/' . ltrim($thumb_filename, '/'));
          $thumb_fallbacks[] = ia_connect_join_url($base, '/lazy-static/previews/' . ltrim($thumb_filename, '/'));
          $thumb_fallbacks[] = ia_connect_join_url($base, '/' . ltrim($thumb_filename, '/'));
          $thumb = $thumb_fallbacks[0];
        }
        $items[] = [
          'title' => (string)($r['name'] ?? ''),
          'excerpt' => 'Liked video',
          'url' => ia_connect_join_url($base, (string)($r['url'] ?? '')),
          'thumb' => $thumb,
          'thumb_fallbacks' => $thumb_fallbacks,
        ];
      }
    } elseif ($type === 'subscriptions') {
      if ($pt_actor <= 0) wp_send_json_success(['items' => [], 'has_more' => false]);
      $sql = "SELECT a.\"preferredUsername\" AS preferred_username, a.url
              FROM public.\"actorFollow\" af
              JOIN public.actor a ON a.id = af.\"targetActorId\"
              WHERE af.\"actorId\" = :actor
                AND (:q = '' OR a.\"preferredUsername\" ILIKE :q)
              ORDER BY af.\"createdAt\" DESC
              LIMIT {$limit} OFFSET {$offset}";
      $st = $pdo->prepare($sql);
      $st->execute([':actor' => $pt_actor, ':q' => $q_like]);
      $rows = $st->fetchAll();
      foreach (($rows ?: []) as $r) {
        $items[] = [
          'title' => (string)($r['preferred_username'] ?? 'Subscription'),
          'excerpt' => 'Subscribed',
          'url' => ia_connect_join_url($base, (string)($r['url'] ?? '')),
        ];
      }
    } elseif ($type === 'playlists') {
      if ($pt_account <= 0) wp_send_json_success(['items' => [], 'has_more' => false]);
      $sql = "SELECT vp.id, vp.name, vp.url, vp.description, th.\"fileUrl\" AS thumb_url, th.filename AS thumb_filename
              FROM public.\"videoPlaylist\" vp
              LEFT JOIN LATERAL (
                SELECT t.\"fileUrl\", t.filename
                FROM public.thumbnail t
                WHERE t.\"videoPlaylistId\" = vp.id
                ORDER BY (t.\"fileUrl\" IS NULL) ASC, t.type ASC, t.height DESC NULLS LAST
                LIMIT 1
              ) th ON TRUE
              WHERE vp.\"ownerAccountId\" = :acct
                AND (:q = '' OR vp.name ILIKE :q OR vp.description ILIKE :q)
              ORDER BY vp.\"createdAt\" DESC
              LIMIT {$limit} OFFSET {$offset}";
      $st = $pdo->prepare($sql);
      $st->execute([':acct' => $pt_account, ':q' => $q_like]);
      $rows = $st->fetchAll();
      foreach (($rows ?: []) as $r) {
        $thumb_url = (string)($r['thumb_url'] ?? '');
        $thumb_filename = (string)($r['thumb_filename'] ?? '');
        $thumb = $thumb_url !== '' ? ia_connect_join_url($base, $thumb_url) : '';
        $thumb_fallbacks = [];
        if ($thumb === '' && $thumb_filename !== '') {
          $thumb_fallbacks[] = ia_connect_join_url($base, '/static/thumbnails/' . ltrim($thumb_filename, '/'));
          $thumb_fallbacks[] = ia_connect_join_url($base, '/lazy-static/thumbnails/' . ltrim($thumb_filename, '/'));
          $thumb_fallbacks[] = ia_connect_join_url($base, '/lazy-static/previews/' . ltrim($thumb_filename, '/'));
          $thumb_fallbacks[] = ia_connect_join_url($base, '/' . ltrim($thumb_filename, '/'));
        }
        $items[] = [
          'title' => (string)($r['name'] ?? ''),
          'excerpt' => ia_connect_excerpt_text((string)($r['description'] ?? '')),
          'url' => ia_connect_join_url($base, (string)($r['url'] ?? '')),
          'thumb' => $thumb,
          'thumb_fallbacks' => $thumb_fallbacks,
        ];
      }
    } else { // history
      if ($pt_user <= 0) wp_send_json_success(['items' => [], 'has_more' => false]);
      $sql = "SELECT v.id, v.name, v.url, th.\"fileUrl\" AS thumb_url, th.filename AS thumb_filename
              FROM public.\"userVideoHistory\" uvh
              JOIN public.video v ON v.id = uvh.\"videoId\"
              LEFT JOIN LATERAL (
                SELECT t.\"fileUrl\", t.filename
                FROM public.thumbnail t
                WHERE t.\"videoId\" = v.id
                ORDER BY (t.\"fileUrl\" IS NULL) ASC, t.type ASC, t.height DESC NULLS LAST
                LIMIT 1
              ) th ON TRUE
              WHERE uvh.\"userId\" = :uid
                AND (:q = '' OR v.name ILIKE :q)
              ORDER BY uvh.\"updatedAt\" DESC
              LIMIT {$limit} OFFSET {$offset}";
      $st = $pdo->prepare($sql);
      $st->execute([':uid' => $pt_user, ':q' => $q_like]);
      $rows = $st->fetchAll();
      foreach (($rows ?: []) as $r) {
        $thumb_url = (string)($r['thumb_url'] ?? '');
        $thumb_filename = (string)($r['thumb_filename'] ?? '');
        $thumb = $thumb_url !== '' ? ia_connect_join_url($base, $thumb_url) : '';
        $thumb_fallbacks = [];
        if ($thumb === '' && $thumb_filename !== '') {
          $thumb_fallbacks[] = ia_connect_join_url($base, '/static/thumbnails/' . ltrim($thumb_filename, '/'));
          $thumb_fallbacks[] = ia_connect_join_url($base, '/lazy-static/thumbnails/' . ltrim($thumb_filename, '/'));
          $thumb_fallbacks[] = ia_connect_join_url($base, '/lazy-static/previews/' . ltrim($thumb_filename, '/'));
          $thumb_fallbacks[] = ia_connect_join_url($base, '/' . ltrim($thumb_filename, '/'));
          $thumb = $thumb_fallbacks[0];
        }
        $items[] = [
          'title' => (string)($r['name'] ?? ''),
          'excerpt' => 'Watched',
          'url' => ia_connect_join_url($base, (string)($r['url'] ?? '')),
          'thumb' => $thumb,
          'thumb_fallbacks' => $thumb_fallbacks,
        ];
      }
    }
  } catch (Throwable $e) {
    wp_send_json_error(['message' => 'PeerTube query failed.'], 500);
  }

  if (count($items) > $per) {
    $has_more = true;
    $items = array_slice($items, 0, $per);
  }

  wp_send_json_success(['items' => $items, 'has_more' => $has_more]);
}

function ia_connect_excerpt_phpbb(string $text, int $max = 220): string {
  // phpBB post_text may contain BBCode tags; strip the common ones lightly.
  $t = preg_replace('/<[^>]+>/', '', $text);
  $t = preg_replace('/\[\/?[a-zA-Z0-9_=\-:;#]+\]/', '', $t);
  $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $t = trim(preg_replace('/\s+/', ' ', $t));
  if (mb_strlen($t) > $max) $t = mb_substr($t, 0, $max - 1) . '…';
  return $t;
}

function ia_connect_excerpt_text(string $text, int $max = 220): string {
  $t = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $t = trim(preg_replace('/\s+/', ' ', strip_tags($t)));
  if (mb_strlen($t) > $max) $t = mb_substr($t, 0, $max - 1) . '…';
  return $t;
}

function ia_connect_discuss_url_agora(int $forum_id): string {
  return add_query_arg([
    'tab' => 'discuss',
    'ia_tab' => 'discuss',
    'iad_view' => 'agora',
    'iad_forum' => $forum_id,
  ], home_url('/'));
}

function ia_connect_discuss_url_topic(int $topic_id): string {
  return add_query_arg([
    'tab' => 'discuss',
    'ia_tab' => 'discuss',
    'iad_view' => 'topic',
    'iad_topic' => $topic_id,
  ], home_url('/'));
}

function ia_connect_discuss_url_reply(int $topic_id, int $post_id): string {
  return add_query_arg([
    'tab' => 'discuss',
    'ia_tab' => 'discuss',
    'iad_view' => 'topic',
    'iad_topic' => $topic_id,
    'iad_post' => $post_id,
  ], home_url('/'));
}


// ---------- Wall: posts, attachments, comments ----------

function ia_connect_ajax_post_create(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'post_create');

  $wall_phpbb = isset($_POST['wall_phpbb']) ? (int) $_POST['wall_phpbb'] : 0;
  $wall_wp = isset($_POST['wall_wp']) ? (int) $_POST['wall_wp'] : 0;

  $title = isset($_POST['title']) ? sanitize_text_field((string) wp_unslash($_POST['title'])) : '';
  $body  = isset($_POST['body']) ? wp_kses_post((string) wp_unslash($_POST['body'])) : '';

  global $wpdb;
  $posts = $wpdb->prefix . 'ia_connect_posts';
  $atts  = $wpdb->prefix . 'ia_connect_attachments';

  $now = current_time('mysql');
  $author_phpbb = ia_connect_user_phpbb_id($me);

  $wpdb->insert($posts, [
    'wall_owner_wp_id' => $wall_wp,
    'wall_owner_phpbb_id' => $wall_phpbb,
    'author_wp_id' => $me,
    'author_phpbb_id' => $author_phpbb,
    'type' => 'status',
    'parent_post_id' => 0,
    'shared_tab' => '',
    'shared_ref' => '',
    'title' => $title,
    'body' => $body,
    'created_at' => $now,
    'updated_at' => $now,
    'status' => 'publish',
  ], ['%d','%d','%d','%d','%s','%d','%s','%s','%s','%s','%s','%s','%s']);

  $post_id = (int) $wpdb->insert_id;
  if ($post_id <= 0) {
    wp_send_json_error(['message' => 'Insert failed.'], 500);
  }

  // Multi attachments
  $files = $_FILES['files'] ?? null;
  if ($files && is_array($files) && isset($files['name']) && is_array($files['name'])) {
    $count = count($files['name']);
    for ($i=0; $i<$count; $i++) {
      if (empty($files['name'][$i])) continue;
      $one = [
        'name' => $files['name'][$i],
        'type' => $files['type'][$i] ?? '',
        'tmp_name' => $files['tmp_name'][$i],
        'error' => $files['error'][$i],
        'size' => $files['size'][$i] ?? 0,
      ];
      $url = ia_connect_process_wall_upload($me, $post_id, $one);
      if (!$url) continue;
      $mime = (string) ($one['type'] ?? '');
      $kind = ia_connect_kind_from_mime($mime, (string)$one['name']);
      $wpdb->insert($atts, [
        'post_id' => $post_id,
        'sort_order' => $i,
        'url' => $url,
        'mime' => $mime,
        'kind' => $kind,
        'file_name' => sanitize_text_field((string)$one['name']),
        'created_at' => $now,
      ], ['%d','%d','%s','%s','%s','%s','%s']);
    }
  }

  $payload = ia_connect_build_post_payload($post_id);

  /**
   * Notification hook for future ia-notifications integration.
   *
   * @param int   $post_id
   * @param int   $actor_wp_id
   * @param int   $wall_owner_phpbb_id
   */
  do_action('ia_connect_post_created', $post_id, $me, $wall_phpbb);

  wp_send_json_success(['post' => $payload]);
}

function ia_connect_process_wall_upload(int $wp_user_id, int $post_id, array $file): ?string {
  require_once ABSPATH . 'wp-admin/includes/file.php';

  [$base, $baseurl] = ia_connect_upload_dir();
  $dir = trailingslashit($base) . 'user-' . $wp_user_id . '/wall/post-' . $post_id;
  $dirurl = trailingslashit($baseurl) . 'user-' . $wp_user_id . '/wall/post-' . $post_id;

  if (!wp_mkdir_p($dir)) return null;

  $filter = function ($u) use ($dir, $dirurl) {
    $u['path'] = $dir;
    $u['url']  = $dirurl;
    $u['subdir'] = '';
    return $u;
  };
  add_filter('upload_dir', $filter);

  $overrides = ['test_form' => false];
  $r = wp_handle_upload($file, $overrides);

  remove_filter('upload_dir', $filter);

  if (!is_array($r) || !empty($r['error']) || empty($r['url'])) return null;
  return esc_url_raw($r['url']);
}

function ia_connect_kind_from_mime(string $mime, string $name): string {
  $m = strtolower($mime);
  if (strpos($m, 'image/') === 0) return 'image';
  if (strpos($m, 'video/') === 0) return 'video';
  if (strpos($m, 'audio/') === 0) return 'audio';
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if ($ext === 'pdf') return 'pdf';
  return 'file';
}

function ia_connect_ajax_post_list(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'post_list');

  $wall_phpbb = isset($_POST['wall_phpbb']) ? (int) $_POST['wall_phpbb'] : 0;
  $wall_wp = isset($_POST['wall_wp']) ? (int) $_POST['wall_wp'] : 0;
  $before_id = isset($_POST['before_id']) ? (int) $_POST['before_id'] : 0;
  $limit = isset($_POST['limit']) ? max(5, min(25, (int) $_POST['limit'])) : 10;

  global $wpdb;
  $posts = $wpdb->prefix . 'ia_connect_posts';

  $where = [];
  $args = [];

  if ($wall_phpbb > 0) {
    $where[] = 'wall_owner_phpbb_id = %d';
    $args[] = $wall_phpbb;
  } else {
    $where[] = 'wall_owner_wp_id = %d';
    $args[] = $wall_wp;
  }

  $where[] = "status='publish'";

  if ($before_id > 0) {
    $where[] = 'id < %d';
    $args[] = $before_id;
  }

  $sql = "SELECT id FROM $posts WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT $limit";
  $ids = $wpdb->get_col($wpdb->prepare($sql, $args));

  $out = [];
  foreach ($ids as $id) {
    $out[] = ia_connect_build_post_payload((int)$id);
  }

  // Optional BuddyPress activity merge (read-only) – minimal support.
  $settings = ia_connect_get_settings();
  $bp_items = [];
  if (!empty($settings['show_buddypress_activity']) && function_exists('bp_is_active')) {
    $bp_items = ia_connect_fetch_buddypress_items($wall_wp, $limit, $before_id);
  }

  wp_send_json_success(['posts' => $out, 'bp' => $bp_items]);
}

function ia_connect_build_post_payload(int $post_id): array {
  global $wpdb;
  $posts = $wpdb->prefix . 'ia_connect_posts';
  $atts  = $wpdb->prefix . 'ia_connect_attachments';
  $comms = $wpdb->prefix . 'ia_connect_comments';

  $p = $wpdb->get_row($wpdb->prepare("SELECT * FROM $posts WHERE id=%d", $post_id), ARRAY_A);
  if (!$p) return [];

  $author_wp = (int)($p['author_wp_id'] ?? 0);
  $author_u = $author_wp > 0 ? get_userdata($author_wp) : null;
  $author_name = $author_u ? ($author_u->display_name ?: $author_u->user_login) : 'deleted user';
  $author_login = $author_u ? (string)$author_u->user_login : '';
  $author_phpbb = $author_wp > 0 ? ia_connect_user_phpbb_id($author_wp) : 0;

  $atts_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $atts WHERE post_id=%d ORDER BY sort_order ASC", $post_id), ARRAY_A);
  $attachments = [];
  foreach ($atts_rows as $a) {
    $attachments[] = [
      'id' => (int)$a['id'],
      'url' => (string)$a['url'],
      'mime' => (string)$a['mime'],
      'kind' => (string)$a['kind'],
      'name' => (string)$a['file_name'],
    ];
  }

  $comment_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM $comms WHERE post_id=%d AND is_deleted=0", $post_id));

  // Load top-level comments (first 3) for card preview.
  $comments = [];
  $crows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $comms WHERE post_id=%d AND parent_comment_id=0 AND is_deleted=0 ORDER BY id ASC LIMIT 3", $post_id), ARRAY_A);
  foreach ($crows as $c) {
    $cwp = (int)$c['author_wp_id'];
    $cu = $cwp>0 ? get_userdata($cwp) : null;
    $cname = $cu ? ($cu->display_name ?: $cu->user_login) : 'deleted user';
    $clogin = $cu ? (string)$cu->user_login : '';
    $cphpbb = (int)($c['author_phpbb_id'] ?? 0);
    $comments[] = [
      'id' => (int)$c['id'],
      'author_wp_id' => $cwp,
      'author_phpbb_id' => $cphpbb,
      'author_username' => $clogin,
      'author' => $cname,
      'author_avatar' => $cwp>0 ? ia_connect_avatar_url($cwp, 48) : '',
      'body' => (string)$c['body'],
      'created_at' => (string)$c['created_at'],
    ];
  }

  $parent = (int)($p['parent_post_id'] ?? 0);
  $parent_payload = $parent > 0 ? ia_connect_build_post_payload($parent) : null;

  return [
    'id' => (int)$p['id'],
    'type' => (string)$p['type'],
    'parent_post' => $parent_payload,
    'wall_owner_wp_id' => (int)$p['wall_owner_wp_id'],
    'wall_owner_phpbb_id' => (int)$p['wall_owner_phpbb_id'],
    'author_wp_id' => $author_wp,
    'author_phpbb_id' => $author_phpbb,
    'author_username' => $author_login,
    'author' => $author_name,
    'author_avatar' => $author_wp>0 ? ia_connect_avatar_url($author_wp, 64) : '',
    'title' => (string)$p['title'],
    'body' => (string)$p['body'],
    'created_at' => (string)$p['created_at'],
    'attachments' => $attachments,
    'comment_count' => $comment_count,
    'comments_preview' => $comments,
    'shared_tab' => (string)$p['shared_tab'],
    'shared_ref' => (string)$p['shared_ref'],
  ];
}

function ia_connect_ajax_comment_create(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'comment_create');

  $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
  $parent_comment_id = isset($_POST['parent_comment_id']) ? (int) $_POST['parent_comment_id'] : 0;
  $body = isset($_POST['body']) ? wp_kses_post((string) wp_unslash($_POST['body'])) : '';
  if ($post_id <= 0 || trim(wp_strip_all_tags($body)) === '') {
    wp_send_json_error(['message' => 'Missing fields.'], 400);
  }

  global $wpdb;
  $comms = $wpdb->prefix . 'ia_connect_comments';

  $now = current_time('mysql');
  $author_phpbb = ia_connect_user_phpbb_id($me);

  $wpdb->insert($comms, [
    'post_id' => $post_id,
    'parent_comment_id' => $parent_comment_id,
    'author_wp_id' => $me,
    'author_phpbb_id' => $author_phpbb,
    'body' => $body,
    'created_at' => $now,
    'updated_at' => $now,
    'is_deleted' => 0,
  ], ['%d','%d','%d','%d','%s','%s','%s','%d']);

  $cid = (int) $wpdb->insert_id;
  if ($cid <= 0) wp_send_json_error(['message' => 'Insert failed.'], 500);

  /**
   * Notification hook for future ia-notifications integration.
   *
   * @param int $comment_id
   * @param int $post_id
   * @param int $actor_wp_id
   */
  do_action('ia_connect_comment_created', $cid, $post_id, $me);

  $u = get_userdata($me);
  wp_send_json_success([
    'comment' => [
      'id' => $cid,
      'post_id' => $post_id,
      'parent_comment_id' => $parent_comment_id,
      'author_wp_id' => $me,
      'author_phpbb_id' => $author_phpbb,
      'author_username' => $u ? (string)$u->user_login : '',
      'author' => ($u->display_name ?: $u->user_login),
      'author_avatar' => ia_connect_avatar_url($me, 48),
      'body' => (string)$body,
      'created_at' => $now,
    ]
  ]);
}

function ia_connect_ajax_post_get(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'post_get');

  $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
  if ($post_id <= 0) wp_send_json_error(['message' => 'Bad post.'], 400);

  $payload = ia_connect_build_post_payload($post_id);
  if (empty($payload)) wp_send_json_error(['message' => 'Not found.'], 404);

  global $wpdb;
  $comms = $wpdb->prefix . 'ia_connect_comments';

  // Load a reasonable amount for modal view (threaded rendering happens client-side).
  $rows = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $comms WHERE post_id=%d AND is_deleted=0 ORDER BY id ASC LIMIT 200",
    $post_id
  ), ARRAY_A);

  $comments = [];
  foreach ($rows as $c) {
    $cwp = (int)($c['author_wp_id'] ?? 0);
    $u = $cwp > 0 ? get_userdata($cwp) : null;
    $cphpbb = (int)($c['author_phpbb_id'] ?? 0);
    $comments[] = [
      'id' => (int)$c['id'],
      'post_id' => (int)$c['post_id'],
      'parent_comment_id' => (int)$c['parent_comment_id'],
      'author_wp_id' => $cwp,
      'author_phpbb_id' => $cphpbb,
      'author_username' => $u ? (string)$u->user_login : '',
      'author' => $u ? ($u->display_name ?: $u->user_login) : 'User',
      'author_avatar' => $cwp > 0 ? ia_connect_avatar_url($cwp, 48) : '',
      'body' => (string)$c['body'],
      'created_at' => (string)$c['created_at'],
    ];
  }

  wp_send_json_success(['post' => $payload, 'comments' => $comments]);
}

function ia_connect_ajax_mention_suggest(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'mention_suggest');

  $q = isset($_POST['q']) ? trim((string) wp_unslash($_POST['q'])) : '';
  $q = sanitize_text_field($q);
  if ($q === '') wp_send_json_success(['results' => []]);

  // Prefer canonical phpBB users.
  $out = [];
  $phpbb_db = ia_connect_phpbb_db();
  $phpbb_prefix = ia_connect_phpbb_prefix();
  if ($phpbb_db) {
    $users_tbl = $phpbb_prefix . 'users';
    $like = '%' . $phpbb_db->esc_like($q) . '%';
    $rows = $phpbb_db->get_results(
      $phpbb_db->prepare(
        "SELECT user_id, username FROM {$users_tbl} WHERE username LIKE %s AND user_type <> 2 ORDER BY username_clean ASC LIMIT 8",
        $like
      ),
      ARRAY_A
    );

    foreach (($rows ?: []) as $r) {
      $phpbb_id = (int)($r['user_id'] ?? 0);
      $uname = (string)($r['username'] ?? '');
      if ($phpbb_id <= 0 || $uname === '') continue;
      $wp_id = ia_connect_map_phpbb_to_wp_id($phpbb_id);
      $display = $uname;
      if ($wp_id > 0) {
        $wu = get_userdata($wp_id);
        if ($wu) $display = (string)($wu->display_name ?: $wu->user_login);
      }
      $out[] = [
        'wp_user_id'    => (int)$wp_id,
        'username'      => $uname,
        'display'       => $display,
        'phpbb_user_id' => $phpbb_id,
        'avatarUrl'     => $wp_id > 0 ? ia_connect_avatar_url($wp_id, 48) : '',
      ];
    }
  }

  // Fallback: WP shadow users.
  if (empty($out)) {
    $query = new WP_User_Query([
      'search'         => '*' . $q . '*',
      'search_columns' => ['user_login', 'user_nicename', 'display_name'],
      'number'         => 8,
      'fields'         => ['ID', 'user_login', 'display_name'],
    ]);

    foreach ($query->get_results() as $u) {
      $uid = (int) $u->ID;
      $phpbb = (int) get_user_meta($uid, 'ia_phpbb_user_id', true);
      if ($phpbb <= 0) $phpbb = (int) get_user_meta($uid, 'phpbb_user_id', true);

      $out[] = [
        'wp_user_id'    => $uid,
        'username'      => (string) $u->user_login,
        'display'       => (string) ($u->display_name ?: $u->user_login),
        'phpbb_user_id' => (int) $phpbb,
      'avatarUrl'     => ia_connect_avatar_url($uid, 48),
      ];
    }
  }

  wp_send_json_success(['results' => $out]);
}

function ia_connect_ajax_post_share(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'post_share');

  $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
  if ($post_id <= 0) wp_send_json_error(['message' => 'Bad post.'], 400);

  global $wpdb;
  $posts = $wpdb->prefix . 'ia_connect_posts';

  $orig = $wpdb->get_row($wpdb->prepare("SELECT * FROM $posts WHERE id=%d", $post_id), ARRAY_A);
  if (!$orig) wp_send_json_error(['message' => 'Not found.'], 404);

  $now = current_time('mysql');
  $author_phpbb = ia_connect_user_phpbb_id($me);

  // Targets are phpBB ids (preferred) so Connect can share across the canonical identity.
  $targets = [];
  if (isset($_POST['targets'])) {
    $t = $_POST['targets'];
    if (is_array($t)) {
      foreach ($t as $v) { $id = (int)$v; if ($id>0) $targets[] = $id; }
    } else {
      $parts = preg_split('/[^0-9]+/', (string)wp_unslash($t));
      foreach ($parts as $p) { $id = (int)$p; if ($id>0) $targets[] = $id; }
    }
  }

  $share_to_self = isset($_POST['share_to_self']) ? (int)$_POST['share_to_self'] : 0;
  // Always record the share on the sharer's own wall, so it appears in their feed too.
  // (If the UI is "share to my wall" only, this still behaves the same.)
  $targets[] = $author_phpbb;
  if ($share_to_self && empty($targets)) {
    $targets[] = $author_phpbb;
  }
  $targets = array_values(array_unique($targets));

  $created = [];
  $skipped = [];
  $created_map = [];

  foreach ($targets as $wall_phpbb) {
    $wall_wp = ia_connect_map_phpbb_to_wp_id($wall_phpbb);
    if ($wall_wp <= 0) { $skipped[] = $wall_phpbb; continue; }

    $wpdb->insert($posts, [
      'wall_owner_wp_id' => $wall_wp,
      'wall_owner_phpbb_id' => $wall_phpbb,
      'author_wp_id' => $me,
      'author_phpbb_id' => $author_phpbb,
      'type' => 'repost',
      'parent_post_id' => $post_id,
      'shared_tab' => '',
      'shared_ref' => '',
      'title' => '',
      'body' => '',
      'created_at' => $now,
      'updated_at' => $now,
      'status' => 'publish',
    ], ['%d','%d','%d','%d','%s','%d','%s','%s','%s','%s','%s','%s','%s']);

    $new_id = (int) $wpdb->insert_id;
    if ($new_id > 0) {
      $created[] = $new_id;
      $created_map[$wall_phpbb] = $new_id;
    }
  }

  if (empty($created)) {
    wp_send_json_error(['message' => 'Share failed.'], 500);
  }

  /**
   * Notification hook for future ia-notifications integration.
   *
   * @param int   $post_id      Original post id
   * @param int   $actor_wp_id  WP user id who shared
   * @param int   $actor_phpbb  phpBB user id who shared
   * @param int[] $targets      phpBB wall owner ids the share was created for (includes self)
   * @param int[] $created_ids  Newly created repost ids
   */
  do_action('ia_connect_share_created', $post_id, $me, $author_phpbb, $targets, $created, $created_map);

  // Return the first created post payload (useful when sharing to self), plus counts.
  $first = ia_connect_build_post_payload((int)$created[0]);
  wp_send_json_success([
    'post' => $first,
    'created_ids' => $created,
    'created_count' => count($created),
    'skipped_phpbb_ids' => $skipped,
  ]);
}

function ia_connect_map_phpbb_to_wp_id(int $phpbb_id): int {
  if ($phpbb_id <= 0) return 0;

  // Prefer IA Auth mapping (identity map + meta) if available.
  if (class_exists('IA_Auth') && method_exists('IA_Auth', 'instance')) {
    try {
      $ia = IA_Auth::instance();
      if (is_object($ia) && isset($ia->db) && is_object($ia->db) && method_exists($ia->db, 'find_wp_user_by_phpbb_id')) {
        $wpid = (int)$ia->db->find_wp_user_by_phpbb_id($phpbb_id);
        if ($wpid > 0) return $wpid;
      }

      // If meta isn't present yet, identity map table may still have wp_user_id.
      if (is_object($ia) && isset($ia->db) && is_object($ia->db)) {
        global $wpdb;
        $t = $wpdb->prefix . 'ia_identity_map';
        $has = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
        if ($has) {
          $wpid = (int)$wpdb->get_var($wpdb->prepare("SELECT wp_user_id FROM $t WHERE phpbb_user_id=%d LIMIT 1", $phpbb_id));
          if ($wpid > 0) return $wpid;
        }
      }
    } catch (Throwable $e) {
      // ignore
    }
  }

  $q = new WP_User_Query([
    'meta_query' => [
      'relation' => 'OR',
      [ 'key' => 'ia_phpbb_user_id', 'value' => $phpbb_id, 'compare' => '=' ],
      [ 'key' => 'phpbb_user_id', 'value' => $phpbb_id, 'compare' => '=' ],
    ],
    'number' => 1,
    'fields' => ['ID'],
  ]);
  $r = $q->get_results();
  return !empty($r) ? (int)$r[0]->ID : 0;
}

function ia_connect_ajax_wall_search(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'wall_search');

  $q = isset($_POST['q']) ? trim((string) wp_unslash($_POST['q'])) : '';
  $q = sanitize_text_field($q);
  if ($q === '') wp_send_json_success(['posts' => [], 'comments' => []]);

  global $wpdb;
  $posts = $wpdb->prefix . 'ia_connect_posts';
  $comms = $wpdb->prefix . 'ia_connect_comments';

  $like = '%' . $wpdb->esc_like($q) . '%';

  $pids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $posts WHERE status='publish' AND (title LIKE %s OR body LIKE %s) ORDER BY id DESC LIMIT 10", $like, $like));
  $outp = [];
  foreach ($pids as $id) $outp[] = ia_connect_build_post_payload((int)$id);

  $crows = $wpdb->get_results($wpdb->prepare("SELECT id, post_id, author_wp_id, body, created_at FROM $comms WHERE is_deleted=0 AND body LIKE %s ORDER BY id DESC LIMIT 10", $like), ARRAY_A);
  $outc = [];
  foreach ($crows as $c) {
    $awp = (int)$c['author_wp_id'];
    $u = $awp>0 ? get_userdata($awp) : null;
    $outc[] = [
      'id' => (int)$c['id'],
      'post_id' => (int)$c['post_id'],
      'author' => $u ? ($u->display_name ?: $u->user_login) : 'User',
      'author_avatar' => $awp>0 ? ia_connect_avatar_url($awp, 48) : '',
      'body' => (string)$c['body'],
      'created_at' => (string)$c['created_at'],
    ];
  }

  wp_send_json_success(['posts' => $outp, 'comments' => $outc]);
}

function ia_connect_ajax_settings_update(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'settings_update');

  // Only allow admins to change global settings for now.
  if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => 'Not allowed.'], 403);
  }

  $bp = isset($_POST['show_buddypress_activity']) ? (int) $_POST['show_buddypress_activity'] : 0;
  ia_connect_set_settings([
    'show_buddypress_activity' => ($bp === 1),
  ]);

  wp_send_json_success(['settings' => ia_connect_get_settings()]);
}

function ia_connect_fetch_buddypress_items(int $wall_wp_id, int $limit, int $before_id): array {
  if ($wall_wp_id <= 0) return [];
  global $wpdb;
  $tbl = $wpdb->prefix . 'bp_activity';
  // Minimal: latest items for the user.
  $rows = $wpdb->get_results($wpdb->prepare(
    "SELECT id, user_id, content, date_recorded, type FROM $tbl WHERE user_id=%d AND is_spam=0 AND hide_sitewide=0 ORDER BY id DESC LIMIT %d",
    $wall_wp_id,
    $limit
  ), ARRAY_A);

  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      'id' => (int)$r['id'],
      'user_id' => (int)$r['user_id'],
      'type' => (string)$r['type'],
      'content' => (string)$r['content'],
      'created_at' => (string)$r['date_recorded'],
    ];
  }
  return $out;
}

// NOTE: settings_update handler is defined once above.

// =========================
// Account management
// =========================

function ia_connect_ajax_account_change_name(): void {
  $wp_user_id = ia_connect_ajax_require_login();
  $nonce = (string)($_POST['nonce'] ?? '');
  if (!ia_connect_verify_nonce('account_change_name', $nonce)) {
    wp_send_json_error(['message' => 'Bad nonce.'], 403);
  }
  $new_name = trim((string)($_POST['display_name'] ?? ''));
  $current_password = (string)($_POST['current_password'] ?? '');
  if (strlen($new_name) < 3) {
    wp_send_json_error(['message' => 'Name must be at least 3 characters.'], 400);
  }
  $phpbb_user_id = ia_connect_user_phpbb_id($wp_user_id);
  if ($phpbb_user_id <= 0) {
    wp_send_json_error(['message' => 'No phpBB mapping for this user.'], 500);
  }
  $wp_user = get_user_by('id', $wp_user_id);
  if (!$wp_user || !ia_connect_verify_current_password($wp_user_id, $phpbb_user_id, $current_password)) {
    wp_send_json_error(['message' => 'Current password is incorrect.'], 403);
  }

  if (!class_exists('IA_Auth') || !method_exists('IA_Auth','instance')) {
    wp_send_json_error(['message' => 'IA Auth not available.'], 500);
  }
  $ia = IA_Auth::instance();
  $clean = strtolower(preg_replace('/[^a-z0-9_\-]+/i', '', $new_name));

  // phpBB username + clean
  $r = $ia->phpbb->update_user_fields($phpbb_user_id, [
    'username' => $new_name,
    'username_clean' => $clean,
  ]);
  if (empty($r['ok'])) {
    wp_send_json_error(['message' => $r['message'] ?? 'phpBB update failed.'], 500);
  }

  // WP shadow user: display_name, nickname, user_nicename
  wp_update_user([
    'ID' => $wp_user_id,
    'display_name' => $new_name,
    'user_nicename' => sanitize_title($new_name),
  ]);
  update_user_meta($wp_user_id, 'nickname', $new_name);

  // Keep WP user_login aligned with phpBB username_clean for Discuss fallback mapping.
  global $wpdb;
  $users_t = $wpdb->users;
  $new_login = $clean;
  if ($new_login !== '') {
    $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$users_t} WHERE user_login=%s AND ID<>%d LIMIT 1", $new_login, $wp_user_id));
    if ($exists > 0) {
      wp_send_json_error(['message' => 'That username is already taken.'], 409);
    }
    $wpdb->update($users_t, ['user_login' => $new_login, 'user_nicename' => $new_login], ['ID' => $wp_user_id]);
    clean_user_cache($wp_user_id);
  }

  // Update IA identity map cache of phpbb_username_clean so other modules stay in sync.
  $imap = $wpdb->prefix . 'ia_identity_map';
  if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($imap))) === $imap) {
    $wpdb->update($imap, ['phpbb_username_clean' => $clean, 'updated_at' => current_time('mysql')], ['phpbb_user_id' => $phpbb_user_id]);
  }


  // Best-effort: PeerTube displayName using stored user token (if present)
  $pt_cfg = class_exists('IA_Engine') ? (array)IA_Engine::peertube_api() : [];
  $identity = is_object($ia->db) ? $ia->db->get_identity_by_wp_user_id($wp_user_id) : null;
  $phpbb_id = (int)($identity['phpbb_user_id'] ?? $phpbb_user_id);
  $tok = is_object($ia->db) ? $ia->db->get_tokens_by_phpbb_user_id($phpbb_id) : null;
  if (is_array($tok) && !empty($tok['access_token_enc'])) {
    $access = $ia->crypto->decrypt((string)$tok['access_token_enc']);
    if ($access) {
      $ia->peertube->user_update_me_display_name($access, $new_name, $pt_cfg);
    }
  }

  wp_send_json_success(['message' => 'Name updated.']);
}

function ia_connect_ajax_account_change_email(): void {
  $wp_user_id = ia_connect_ajax_require_login();
  $nonce = (string)($_POST['nonce'] ?? '');
  if (!ia_connect_verify_nonce('account_change_email', $nonce)) {
    wp_send_json_error(['message' => 'Bad nonce.'], 403);
  }
  $new_email = trim((string)($_POST['email'] ?? ''));
  $current_password = (string)($_POST['current_password'] ?? '');
  if (!is_email($new_email)) {
    wp_send_json_error(['message' => 'Enter a valid email address.'], 400);
  }
  $phpbb_user_id = ia_connect_user_phpbb_id($wp_user_id);
  if ($phpbb_user_id <= 0) {
    wp_send_json_error(['message' => 'No phpBB mapping for this user.'], 500);
  }
  $wp_user = get_user_by('id', $wp_user_id);
  if (!$wp_user || !ia_connect_verify_current_password($wp_user_id, $phpbb_user_id, $current_password)) {
    wp_send_json_error(['message' => 'Current password is incorrect.'], 403);
  }

  if (!class_exists('IA_Auth') || !method_exists('IA_Auth','instance')) {
    wp_send_json_error(['message' => 'IA Auth not available.'], 500);
  }
  $ia = IA_Auth::instance();

  $r = $ia->phpbb->update_user_fields($phpbb_user_id, ['user_email' => $new_email]);
  if (empty($r['ok'])) {
    wp_send_json_error(['message' => $r['message'] ?? 'phpBB update failed.'], 500);
  }

  wp_update_user(['ID' => $wp_user_id, 'user_email' => $new_email]);
  update_user_meta($wp_user_id, 'ia_email', $new_email);

  $pt_cfg = class_exists('IA_Engine') ? (array)IA_Engine::peertube_api() : [];
  $identity = is_object($ia->db) ? $ia->db->get_identity_by_wp_user_id($wp_user_id) : null;
  $pt_user_id = (int)($identity['peertube_user_id'] ?? 0);
  if ($pt_user_id > 0) {
    $ia->peertube->admin_update_user_email($pt_user_id, $new_email, $pt_cfg);
  }

  wp_send_json_success(['message' => 'Email updated.']);
}

function ia_connect_ajax_account_change_password(): void {
  $wp_user_id = ia_connect_ajax_require_login();
  $nonce = (string)($_POST['nonce'] ?? '');
  if (!ia_connect_verify_nonce('account_change_password', $nonce)) {
    wp_send_json_error(['message' => 'Bad nonce.'], 403);
  }
  $cur = (string)($_POST['current_password'] ?? '');
  $new = (string)($_POST['new_password'] ?? '');
  if (strlen($new) < 8) {
    wp_send_json_error(['message' => 'New password must be at least 8 characters.'], 400);
  }
  $phpbb_user_id = ia_connect_user_phpbb_id($wp_user_id);
  if ($phpbb_user_id <= 0) {
    wp_send_json_error(['message' => 'No phpBB mapping for this user.'], 500);
  }
  $wp_user = get_user_by('id', $wp_user_id);
  if (!$wp_user || !ia_connect_verify_current_password($wp_user_id, $phpbb_user_id, $cur)) {
    wp_send_json_error(['message' => 'Current password is incorrect.'], 403);
  }
  if ($phpbb_user_id <= 0) {
    wp_send_json_error(['message' => 'No phpBB mapping for this user.'], 500);
  }

  if (!class_exists('IA_Auth') || !method_exists('IA_Auth','instance')) {
    wp_send_json_error(['message' => 'IA Auth not available.'], 500);
  }
  $ia = IA_Auth::instance();

  // phpBB uses bcrypt by default in modern versions.
  $hash = password_hash($new, PASSWORD_BCRYPT);
  $r = $ia->phpbb->update_user_fields($phpbb_user_id, ['user_password' => $hash]);
  if (empty($r['ok'])) {
    wp_send_json_error(['message' => $r['message'] ?? 'phpBB update failed.'], 500);
  }

  wp_set_password($new, $wp_user_id);

  $pt_cfg = class_exists('IA_Engine') ? (array)IA_Engine::peertube_api() : [];
  $identity = is_object($ia->db) ? $ia->db->get_identity_by_wp_user_id($wp_user_id) : null;
  $pt_user_id = (int)($identity['peertube_user_id'] ?? 0);
  if ($pt_user_id > 0) {
    $ia->peertube->admin_update_user_password($pt_user_id, $new, $pt_cfg);
  }

  wp_send_json_success(['message' => 'Password updated. You may need to log in again.']);
}

function ia_connect_ajax_account_deactivate(): void {
  $wp_user_id = ia_connect_ajax_require_login();
  $nonce = (string)($_POST['nonce'] ?? '');
  if (!ia_connect_verify_nonce('account_deactivate', $nonce)) {
    wp_send_json_error(['message' => 'Bad nonce.'], 403);
  }
  $phpbb_user_id = ia_connect_user_phpbb_id($wp_user_id);
  if ($phpbb_user_id <= 0) {
    wp_send_json_error(['message' => 'No phpBB mapping for this user.'], 500);
  }
  if (!class_exists('IA_Auth') || !method_exists('IA_Auth','instance')) {
    wp_send_json_error(['message' => 'IA Auth not available.'], 500);
  }
  $ia = IA_Auth::instance();

  $r = $ia->phpbb->deactivate_user($phpbb_user_id);
  if (empty($r['ok'])) {
    wp_send_json_error(['message' => $r['message'] ?? 'Deactivation failed.'], 500);
  }

  update_user_meta($wp_user_id, 'ia_deactivated', 1);
  wp_update_user(['ID' => $wp_user_id, 'user_status' => 1]);

  // Email
  if (function_exists('ia_mail_suite')) {
    try {
      $u = get_user_by('id', $wp_user_id);
      if ($u && $u->user_email) {
        ia_mail_suite()->send_template('ia_connect_account_deactivated', $u->user_email, [
          'site_name' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
          'display_name' => (string)($u->display_name ?: $u->user_login),
          'user_login' => (string)$u->user_login,
          'user_email' => (string)$u->user_email,
          'reactivate_url' => home_url('/?tab=connect'),
        ]);
      }
    } catch (Throwable $e) {
      // ignore
    }
  }

  // Log out WP session
  wp_logout();
  wp_send_json_success(['message' => 'Account deactivated.']);
}

function ia_connect_ajax_account_delete(): void {
  $wp_user_id = ia_connect_ajax_require_login();
  $nonce = (string)($_POST['nonce'] ?? '');
  if (!ia_connect_verify_nonce('account_delete', $nonce)) {
    wp_send_json_error(['message' => 'Bad nonce.'], 403);
  }
  $cur = (string)($_POST['current_password'] ?? '');

  $phpbb_user_id = ia_connect_user_phpbb_id($wp_user_id);
  if ($phpbb_user_id <= 0) {
    wp_send_json_error(['message' => 'No phpBB mapping for this user.'], 500);
  }

  $wp_user = get_user_by('id', $wp_user_id);
  if (!$wp_user || !ia_connect_verify_current_password($wp_user_id, $phpbb_user_id, $cur)) {
    wp_send_json_error(['message' => 'Current password is incorrect.'], 403);
  }

  if (!class_exists('IA_Auth') || !method_exists('IA_Auth','instance')) {
    wp_send_json_error(['message' => 'IA Auth not available.'], 500);
  }
  $ia = IA_Auth::instance();

  // Delete in phpBB first. If this fails, do NOT delete the WP user (otherwise they can just log back in).
  $r = $ia->phpbb->delete_user_preserve_posts($phpbb_user_id, 'deleted user');
  if (!is_array($r) || empty($r['ok'])) {
    wp_send_json_error(['message' => $r['message'] ?? 'phpBB delete failed.'], 500);
  }

  // Remove identity mapping + any stored PeerTube tokens for this phpBB id (Atrium-side only).
  global $wpdb;
  $map_t = $wpdb->prefix . 'ia_identity_map';
  $tok_t = $wpdb->prefix . 'ia_peertube_user_tokens';
  // Best-effort cleanup; ignore errors.
  $wpdb->delete($map_t, ['phpbb_user_id' => $phpbb_user_id]);
  $wpdb->delete($tok_t, ['phpbb_user_id' => $phpbb_user_id]);

  // Delete WP user row
  require_once ABSPATH . 'wp-admin/includes/user.php';
  wp_delete_user($wp_user_id);

  // Also ensure current session is terminated.
  wp_logout();

  wp_send_json_success(['message' => 'Account deleted.']);
}

function ia_connect_ajax_export_data(): void {
  $wp_user_id = ia_connect_ajax_require_login();
  $nonce = (string)($_POST['nonce'] ?? '');
  if (!ia_connect_verify_nonce('export_data', $nonce)) {
    wp_send_json_error(['message' => 'Bad nonce.'], 403);
  }

  $phpbb_user_id = ia_connect_user_phpbb_id($wp_user_id);
  if ($phpbb_user_id <= 0) {
    wp_send_json_error(['message' => 'No phpBB mapping for this user.'], 500);
  }

  global $wpdb;
  $posts_t    = $wpdb->prefix . 'ia_connect_posts';
  $atts_t     = $wpdb->prefix . 'ia_connect_attachments';
  $comments_t = $wpdb->prefix . 'ia_connect_comments';

  $posts = $wpdb->get_results($wpdb->prepare("SELECT * FROM $posts_t WHERE author_phpbb_id=%d ORDER BY id ASC", $phpbb_user_id), ARRAY_A) ?: [];
  $comments = $wpdb->get_results($wpdb->prepare("SELECT * FROM $comments_t WHERE author_phpbb_id=%d OR wall_phpbb_id=%d ORDER BY id ASC", $phpbb_user_id, $phpbb_user_id), ARRAY_A) ?: [];

  // Attachments for the user's posts
  $post_ids = array_values(array_filter(array_map(static function($r){ return (int)($r['id'] ?? 0); }, $posts), static function($v){ return $v > 0; }));
  $atts = [];
  if (!empty($post_ids)) {
    $in = implode(',', array_fill(0, count($post_ids), '%d'));
    $sql = $wpdb->prepare("SELECT * FROM $atts_t WHERE post_id IN ($in) ORDER BY post_id ASC, sort_order ASC, id ASC", ...$post_ids);
    $atts = $wpdb->get_results($sql, ARRAY_A) ?: [];
  }

  // Discuss (phpBB): only topics that have posts by this user
  $phpbb_db = ia_connect_phpbb_db();
  $phpbb_prefix = ia_connect_phpbb_prefix();

  $replies = [];
  $topics_by_id = [];
  if ($phpbb_db) {
    $posts_p  = $phpbb_prefix . 'posts';
    $topics_t = $phpbb_prefix . 'topics';
    $users_t  = $phpbb_prefix . 'users';

    try {
      $replies = $phpbb_db->get_results(
        $phpbb_db->prepare("SELECT post_id, topic_id, post_time, post_text FROM {$posts_p} WHERE poster_id=%d ORDER BY post_id ASC", $phpbb_user_id),
        ARRAY_A
      ) ?: [];

      $topic_ids = [];
      foreach ($replies as $r) {
        $tid = (int)($r['topic_id'] ?? 0);
        if ($tid > 0) $topic_ids[$tid] = true;
      }
      $topic_ids = array_keys($topic_ids);

      if (!empty($topic_ids)) {
        $in = implode(',', array_fill(0, count($topic_ids), '%d'));
        $sql = $phpbb_db->prepare("SELECT topic_id, topic_title, topic_time FROM {$topics_t} WHERE topic_id IN ($in)", ...$topic_ids);
        $trows = $phpbb_db->get_results($sql, ARRAY_A) ?: [];
        foreach ($trows as $tr) {
          $tid = (int)($tr['topic_id'] ?? 0);
          if ($tid > 0) $topics_by_id[$tid] = $tr;
        }
      }
    } catch (Throwable $e) {
      $replies = [];
      $topics_by_id = [];
    }
  }

  $u = wp_upload_dir();
  $outdir = trailingslashit($u['basedir']) . 'ia-connect-exports';
  if (!file_exists($outdir)) wp_mkdir_p($outdir);

  $zip_path = $outdir . '/export-' . $wp_user_id . '-' . time() . '.zip';
  $zip_url = trailingslashit($u['baseurl']) . 'ia-connect-exports/' . basename($zip_path);

  $z = new ZipArchive();
  if (true !== $z->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
    wp_send_json_error(['message' => 'Could not create ZIP.'], 500);
  }

  $uploads_baseurl = trailingslashit($u['baseurl']);
  $uploads_basedir = trailingslashit($u['basedir']);
  $media_added = [];

  foreach ($atts as $a) {
    $url = (string)($a['url'] ?? '');
    if ($url === '' || strpos($url, $uploads_baseurl) !== 0) continue;

    $rel = ltrim(substr($url, strlen($uploads_baseurl)), '/');
    $src = $uploads_basedir . $rel;

    if (!file_exists($src) || !is_file($src)) continue;
    if (isset($media_added[$rel])) continue;
    $media_added[$rel] = true;

    $z->addFile($src, 'media/' . $rel);
  }

  // User labels
  $wp_u = get_userdata($wp_user_id);
  $wp_display = $wp_u ? (string)($wp_u->display_name ?: $wp_u->user_login) : 'User';
  $wp_login = $wp_u ? (string)$wp_u->user_login : '';

  $phpbb_username = '';
  if ($phpbb_db) {
    try {
      $users_t = $phpbb_prefix . 'users';
      $phpbb_username = (string)($phpbb_db->get_var($phpbb_db->prepare("SELECT username FROM {$users_t} WHERE user_id=%d", $phpbb_user_id)) ?? '');
    } catch (Throwable $e) {
      $phpbb_username = '';
    }
  }

  // Shared stylesheet for all HTML files
  $css = 'html,body{margin:0;padding:0}body{font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Arial;line-height:1.45;background:#0b0d10;color:#e8e8e8;padding:24px}'
    .'.wrap{max-width:980px;margin:0 auto}'
    .'.hdr{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid rgba(255,255,255,.10)}'
    .'.brand{font-size:20px;font-weight:700;letter-spacing:.2px}'
    .'.meta{font-size:13px;color:rgba(255,255,255,.70)}'
    .'.pill{display:inline-block;padding:3px 10px;border:1px solid rgba(255,255,255,.14);border-radius:999px;margin-right:6px;margin-top:6px}'
    .'.sec{margin-top:22px}'
    .'.sec h2{font-size:15px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.70);margin:0 0 10px 0}'
    .'.card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);border-radius:14px;padding:14px 14px;margin:10px 0}'
    .'.card h3{margin:0 0 6px 0;font-size:15px}'
    .'.sub{font-size:12px;color:rgba(255,255,255,.65);margin-bottom:10px}'
    .'.body{white-space:pre-wrap;word-wrap:break-word;font-size:14px}'
    .'.list{list-style:none;padding:0;margin:0}'
    .'.list li{padding:10px 0;border-bottom:1px solid rgba(255,255,255,.08)}'
    .'.list a{display:flex;justify-content:space-between;gap:16px}'
    .'.muted{color:rgba(255,255,255,.65);font-size:12px}'
    .'a{color:#8ab4ff;text-decoration:none}a:hover{text-decoration:underline}';
  $z->addFromString('style.css', $css);

  $who = $wp_display;
  $handle = $phpbb_username !== '' ? $phpbb_username : $wp_login;

  // Index HTML
  $idx = "<!doctype html><html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">";
  $idx .= "<title>IndieAgora export</title><link rel=\"stylesheet\" href=\"style.css\"></head><body><div class=\"wrap\">";
  $idx .= "<div class=\"hdr\"><div><div class=\"brand\">IndieAgora export</div><div class=\"meta\">Generated: " . esc_html(gmdate('Y-m-d H:i:s')) . " UTC</div></div>";
  $idx .= "<div class=\"meta\"><div class=\"pill\">" . esc_html($who) . "</div>";
  if ($handle !== '') $idx .= "<div class=\"pill\">@" . esc_html($handle) . "</div>";
  $idx .= "</div></div>";

  // Connect posts list
  $idx .= "<div class=\"sec\"><h2>Connect posts</h2>";
  if (empty($posts)) {
    $idx .= "<div class=\"muted\">No Connect posts found.</div>";
  } else {
    $idx .= "<ul class=\"list\">";
    foreach ($posts as $p) {
      $pid = (int)($p['id'] ?? 0);
      $created = (string)($p['created_at'] ?? $p['created'] ?? '');
      $body = (string)($p['body'] ?? '');
      $excerpt = trim(preg_replace('/\s+/', ' ', $body));
      if (strlen($excerpt) > 120) $excerpt = substr($excerpt, 0, 120) . '…';
      $href = 'connect/post-' . $pid . '.html';
      $idx .= "<li><a href=\"" . esc_attr($href) . "\"><span>Post #" . $pid . " — " . esc_html($excerpt) . "</span><span class=\"muted\">" . esc_html($created) . "</span></a></li>";
    }
    $idx .= "</ul>";
  }
  $idx .= "</div>";

  // Discuss replies list
  $idx .= "<div class=\"sec\"><h2>Discuss replies</h2>";
  if (empty($replies)) {
    $idx .= "<div class=\"muted\">No Discuss replies found.</div>";
  } else {
    $idx .= "<ul class=\"list\">";
    foreach ($replies as $r) {
      $pid = (int)($r['post_id'] ?? 0);
      $tid = (int)($r['topic_id'] ?? 0);
      $t = $topics_by_id[$tid] ?? [];
      $title = (string)($t['topic_title'] ?? ('Topic #' . $tid));
      $ts = (int)($r['post_time'] ?? 0);
      $when = $ts > 0 ? gmdate('Y-m-d H:i:s', $ts) . ' UTC' : '';
      $href = 'discuss/post-' . $pid . '.html';
      $idx .= "<li><a href=\"" . esc_attr($href) . "\"><span>" . esc_html($title) . " — Reply #" . $pid . "</span><span class=\"muted\">" . esc_html($when) . "</span></a></li>";
    }
    $idx .= "</ul>";
  }
  $idx .= "</div>";

  $idx .= "</div></body></html>";
  $z->addFromString('index.html', $idx);

  // Build maps for Connect
  $atts_by_post = [];
  foreach ($atts as $a) {
    $pid = (int)($a['post_id'] ?? 0);
    if ($pid <= 0) continue;
    if (!isset($atts_by_post[$pid])) $atts_by_post[$pid] = [];
    $atts_by_post[$pid][] = $a;
  }

  $comments_by_post = [];
  foreach ($comments as $c) {
    $pid = (int)($c['post_id'] ?? 0);
    if ($pid <= 0) continue;
    if (!isset($comments_by_post[$pid])) $comments_by_post[$pid] = [];
    $comments_by_post[$pid][] = $c;
  }

  // Connect per-post HTML files
  foreach ($posts as $p) {
    $pid = (int)($p['id'] ?? 0);
    if ($pid <= 0) continue;

    $created = (string)($p['created_at'] ?? $p['created'] ?? '');
    $body = (string)($p['body'] ?? '');
    $plist = $atts_by_post[$pid] ?? [];
    $clist = $comments_by_post[$pid] ?? [];

    $h = "<!doctype html><html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">";
    $h .= "<title>Connect Post #" . $pid . "</title><link rel=\"stylesheet\" href=\"../style.css\"></head><body><div class=\"wrap\">";
    $h .= "<div class=\"hdr\"><div><div class=\"brand\">Connect Post #" . $pid . "</div><div class=\"meta\">" . esc_html($created) . "</div></div>";
    $h .= "<div class=\"meta\"><a href=\"../index.html\">Back to index</a></div></div>";

    $h .= "<div class=\"card\"><div class=\"sub\">" . esc_html($who) . "</div><div class=\"body\">" . esc_html($body) . "</div>";

    if (!empty($plist)) {
      $h .= "<div class=\"sub\" style=\"margin-top:10px\">Attachments</div><ul class=\"list\">";
      foreach ($plist as $a) {
        $url = (string)($a['url'] ?? '');
        $fname = (string)($a['file_name'] ?? basename($url));
        $link = $url;
        if ($url && strpos($url, $uploads_baseurl) === 0) {
          $rel = ltrim(substr($url, strlen($uploads_baseurl)), '/');
          if (isset($media_added[$rel])) $link = '../media/' . $rel;
        }
        $h .= "<li><a href=\"" . esc_attr($link) . "\">" . esc_html($fname) . "</a></li>";
      }
      $h .= "</ul>";
    }

    $h .= "</div>";

    if (!empty($clist)) {
      $h .= "<div class=\"sec\"><h2>Comments</h2>";
      foreach ($clist as $c) {
        $cid = (int)($c['id'] ?? 0);
        $cbody = (string)($c['body'] ?? '');
        $ctime = (string)($c['created_at'] ?? $c['created'] ?? '');
        $h .= "<div class=\"card\"><h3>Comment #" . $cid . "</h3><div class=\"sub\">" . esc_html($ctime) . "</div><div class=\"body\">" . esc_html($cbody) . "</div></div>";
      }
      $h .= "</div>";
    }

    $h .= "</div></body></html>";
    $z->addFromString('connect/post-' . $pid . '.html', $h);
  }

  // Discuss per-reply HTML files
  foreach ($replies as $r) {
    $pid = (int)($r['post_id'] ?? 0);
    if ($pid <= 0) continue;
    $tid = (int)($r['topic_id'] ?? 0);
    $t = $topics_by_id[$tid] ?? [];
    $title = (string)($t['topic_title'] ?? ('Topic #' . $tid));
    $ts = (int)($r['post_time'] ?? 0);
    $when = $ts > 0 ? gmdate('Y-m-d H:i:s', $ts) . ' UTC' : '';
    $text = (string)($r['post_text'] ?? '');
    // Minimal BBCode strip (keep readable export)
    $text_plain = preg_replace('/\[(\/?)([a-zA-Z0-9]+)(?:=[^\]]+)?\]/', '', $text);

    $h = "<!doctype html><html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">";
    $h .= "<title>Discuss Reply #" . $pid . "</title><link rel=\"stylesheet\" href=\"../style.css\"></head><body><div class=\"wrap\">";
    $h .= "<div class=\"hdr\"><div><div class=\"brand\">" . esc_html($title) . "</div><div class=\"meta\">Reply #" . $pid . " — " . esc_html($when) . "</div></div>";
    $h .= "<div class=\"meta\"><a href=\"../index.html\">Back to index</a></div></div>";
    $h .= "<div class=\"card\"><div class=\"sub\">" . esc_html($who) . "</div><div class=\"body\">" . esc_html($text_plain) . "</div></div>";
    $h .= "</div></body></html>";

    $z->addFromString('discuss/post-' . $pid . '.html', $h);
  }

  $z->close();
  wp_send_json_success(['url' => $zip_url, 'message' => 'Export ready.']);
}


/**
 * Verify the user's current password against the canonical phpBB password hash (preferred),
 * with a fallback to WP shadow password (some installs keep them in sync).
 */
function ia_connect_verify_current_password(int $wp_user_id, int $phpbb_user_id, string $password): bool {
  $password = (string)$password;
  if ($password === '') return false;

  $phpbb_db = ia_connect_phpbb_db();
  $phpbb_prefix = ia_connect_phpbb_prefix();

  if ($phpbb_db && $phpbb_user_id > 0) {
    try {
      $users_t = $phpbb_prefix . 'users';
      $hash = (string)($phpbb_db->get_var($phpbb_db->prepare("SELECT user_password FROM {$users_t} WHERE user_id=%d", $phpbb_user_id)) ?? '');
      if ($hash !== '' && ia_connect_verify_phpbb_password($password, $hash)) {
        return true;
      }
    } catch (Throwable $e) {
      // fall through
    }
  }

  // Fallback: WP shadow password
  $wp_user = get_user_by('id', $wp_user_id);
  if ($wp_user && wp_check_password($password, (string)$wp_user->user_pass, $wp_user_id)) return true;

  return false;
}

function ia_connect_verify_phpbb_password(string $password, string $hash): bool {
  if ($hash === '') return false;

  // Modern hashes (argon2/bcrypt)
  if (strpos($hash, '$argon2') === 0 || strpos($hash, '$2') === 0) {
    return password_verify($password, $hash);
  }

  // phpBB portable hashes ($H$ / $P$)
  if (strpos($hash, '$H$') === 0 || strpos($hash, '$P$') === 0) {
    $calc = ia_connect_phpbb_hash($password, $hash);
    return $calc !== '' && hash_equals($calc, $hash);
  }

  // legacy md5
  if (preg_match('/^[a-f0-9]{32}$/i', $hash)) {
    return hash_equals(md5($password), strtolower($hash));
  }

  return false;
}

function ia_connect_phpbb_hash(string $password, string $setting): string {
  $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

  if (substr($setting, 0, 3) !== '$H$' && substr($setting, 0, 3) !== '$P$') {
    return '';
  }

  $count_log2 = strpos($itoa64, $setting[3]);
  if ($count_log2 < 7 || $count_log2 > 30) return '';

  $count = 1 << $count_log2;
  $salt = substr($setting, 4, 8);
  if (strlen($salt) !== 8) return '';

  $hash = md5($salt . $password, true);
  do {
    $hash = md5($hash . $password, true);
  } while (--$count);

  $output = substr($setting, 0, 12);
  $output .= ia_connect_encode64($hash, 16, $itoa64);

  return $output;
}

function ia_connect_encode64(string $input, int $count, string $itoa64): string {
  $output = '';
  $i = 0;
  do {
    $value = ord($input[$i++]);
    $output .= $itoa64[$value & 0x3f];

    if ($i < $count) $value |= ord($input[$i]) << 8;
    $output .= $itoa64[($value >> 6) & 0x3f];

    if ($i++ >= $count) break;
    if ($i < $count) $value |= ord($input[$i]) << 16;
    $output .= $itoa64[($value >> 12) & 0x3f];

    if ($i++ >= $count) break;
    $output .= $itoa64[($value >> 18) & 0x3f];
  } while ($i < $count);

  return $output;
}

