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
  add_action('wp_ajax_ia_connect_comment_update', 'ia_connect_ajax_comment_update');
  add_action('wp_ajax_ia_connect_comment_delete', 'ia_connect_ajax_comment_delete');
  add_action('wp_ajax_ia_connect_post_get', 'ia_connect_ajax_post_get');
  add_action('wp_ajax_ia_connect_post_share', 'ia_connect_ajax_post_share');
  add_action('wp_ajax_ia_connect_wall_search', 'ia_connect_ajax_wall_search');
  add_action('wp_ajax_ia_connect_mention_suggest', 'ia_connect_ajax_mention_suggest');

  // Settings
  add_action('wp_ajax_ia_connect_settings_update', 'ia_connect_ajax_settings_update');
add_action('wp_ajax_ia_connect_display_name_update', 'ia_connect_ajax_display_name_update');
  add_action('wp_ajax_ia_connect_signature_update', 'ia_connect_ajax_signature_update');
  add_action('wp_ajax_ia_connect_home_tab_update', 'ia_connect_ajax_home_tab_update');
  add_action('wp_ajax_ia_connect_style_update', 'ia_connect_ajax_style_update');

  // Privacy + read-only activity
  add_action('wp_ajax_ia_connect_privacy_get', 'ia_connect_ajax_privacy_get');
  add_action('wp_ajax_ia_connect_privacy_update', 'ia_connect_ajax_privacy_update');
  add_action('wp_ajax_ia_connect_discuss_activity', 'ia_connect_ajax_discuss_activity');
  add_action('wp_ajax_ia_connect_account_deactivate', 'ia_connect_ajax_account_deactivate');
  add_action('wp_ajax_ia_connect_account_delete', 'ia_connect_ajax_account_delete');
  add_action('wp_ajax_ia_connect_export_data', 'ia_connect_ajax_export_data');
  add_action('wp_ajax_ia_connect_stream_activity', 'ia_connect_ajax_stream_activity');

add_action('wp_ajax_ia_connect_comments_page', 'ia_connect_ajax_comments_page');
add_action('wp_ajax_ia_connect_follow_toggle', 'ia_connect_ajax_follow_toggle');
add_action('wp_ajax_ia_connect_post_update', 'ia_connect_ajax_post_update');
add_action('wp_ajax_ia_connect_post_delete', 'ia_connect_ajax_post_delete');

  // User relationships (follow/block)
  add_action('wp_ajax_ia_connect_user_rel_status', 'ia_connect_ajax_user_rel_status');
  add_action('wp_ajax_ia_connect_user_follow_toggle', 'ia_connect_ajax_user_follow_toggle');
  add_action('wp_ajax_ia_connect_user_block_toggle', 'ia_connect_ajax_user_block_toggle');

  // Followers tab
  add_action('wp_ajax_ia_connect_followers_list', 'ia_connect_ajax_followers_list');
  add_action('wp_ajax_ia_connect_followers_search', 'ia_connect_ajax_followers_search');

}

function ia_connect_ajax_followers_list(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'user_search');
  $me_phpbb = ia_connect_user_phpbb_id($me);
  $target_phpbb = isset($_POST['target_phpbb']) ? (int) $_POST['target_phpbb'] : 0;
  if ($target_phpbb <= 0) $target_phpbb = $me_phpbb;
  if ($target_phpbb <= 0) wp_send_json_success(['total' => 0, 'results' => []]);
  $data = ia_connect_followers_query($target_phpbb, '');
  wp_send_json_success($data);
}

function ia_connect_ajax_followers_search(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'user_search');
  $q = isset($_POST['q']) ? trim((string) wp_unslash($_POST['q'])) : '';
  $q = sanitize_text_field($q);
  $me_phpbb = ia_connect_user_phpbb_id($me);
  $target_phpbb = isset($_POST['target_phpbb']) ? (int) $_POST['target_phpbb'] : 0;
  if ($target_phpbb <= 0) $target_phpbb = $me_phpbb;
  if ($target_phpbb <= 0) wp_send_json_success(['total' => 0, 'results' => []]);
  $data = ia_connect_followers_query($target_phpbb, $q);
  wp_send_json_success($data);
}

/**
 * Fetch followers (phpBB ids) for a user, with optional search query.
 */
function ia_connect_followers_query(int $dst_phpbb_id, string $q = ''): array {
  $dst_phpbb_id = (int)$dst_phpbb_id;
  $q = trim((string)$q);
  if ($dst_phpbb_id <= 0) return ['total' => 0, 'results' => []];

  global $wpdb;
  $t = ia_user_rel_table();
  ia_user_rel_ensure_table();

  $ids = $wpdb->get_col($wpdb->prepare(
    "SELECT src_phpbb_id FROM {$t} WHERE rel_type='follow' AND dst_phpbb_id=%d ORDER BY created_at DESC LIMIT 500",
    $dst_phpbb_id
  )) ?: [];
  $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
  $total = count($ids);
  if ($total === 0) return ['total' => 0, 'results' => []];

  $phpbb_db = ia_connect_phpbb_db();
  $phpbb_prefix = ia_connect_phpbb_prefix();

  $rows = [];
  if ($phpbb_db && $phpbb_prefix) {
    $users_tbl = $phpbb_prefix . 'users';
    $like = $q !== '' ? ('%' . $phpbb_db->esc_like($q) . '%') : '';

    // Build IN (...) safely.
    $slice = array_slice($ids, 0, 500);
    $placeholders = implode(',', array_fill(0, count($slice), '%d'));
    $sql = "SELECT user_id, username FROM {$users_tbl} WHERE user_id IN ({$placeholders})";
    $params = $slice;
    if ($q !== '') {
      $sql .= " AND username LIKE %s";
      $params[] = $like;
    }
    $sql .= " ORDER BY username_clean ASC LIMIT 50";
    $rows = $phpbb_db->get_results($phpbb_db->prepare($sql, $params), ARRAY_A) ?: [];
  }

  $out = [];
  foreach ($rows as $r) {
    $phpbb_id = (int)($r['user_id'] ?? 0);
    $uname = (string)($r['username'] ?? '');
    if ($phpbb_id <= 0) continue;
    $wp_id = ia_connect_map_phpbb_to_wp_id($phpbb_id);
    $display = $uname;
    if ($wp_id > 0) {
      $wu = get_userdata($wp_id);
      if ($wu) $display = (string)($wu->display_name ?: $wu->user_login);
    }
    $out[] = [
      'phpbb_user_id' => $phpbb_id,
      'wp_user_id'    => (int)$wp_id,
      'username'      => $uname,
      'display'       => $display,
      'avatarUrl'     => $wp_id > 0 ? ia_connect_avatar_url($wp_id, 48) : '',
    ];
  }

  return ['total' => $total, 'results' => $out];
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
  $seen = [];

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

      $k = ($wp_id > 0) ? ('wp:' . (int)$wp_id) : ('phpbb:' . (int)$phpbb_id);
      if (isset($seen[$k])) continue;
      $seen[$k] = 1;

      $out[] = [
        'wp_user_id'    => (int)$wp_id,
        'username'      => $uname,
        'display'       => $display,
        'phpbb_user_id' => $phpbb_id,
        'avatarUrl'     => $wp_id > 0 ? ia_connect_avatar_url($wp_id, 64) : '',
      ];
    }
  }

  // Also search WP shadow users (display_name/user_login) and merge.
  {
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

      $k = 'wp:' . (int)$uid;
      if (isset($seen[$k])) continue;
      $seen[$k] = 1;

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
  $is_self_stream = ($target_wp === $me);
  $access_token = $is_self_stream ? ia_connect_stream_user_access_token($phpbb_id) : '';
  if ($is_self_stream && $access_token === '' && class_exists('IA_PeerTube_Token_Helper') && method_exists('IA_PeerTube_Token_Helper', 'get_token_status_for_current_user')) {
    try {
      $token_status = IA_PeerTube_Token_Helper::get_token_status_for_current_user();
      error_log('[ia-connect][stream_activity] self stream token unavailable: ' . (string)($token_status['code'] ?? 'unknown'));
    } catch (Throwable $e) {
      // ignore
    }
  }
  $account_name = ia_connect_stream_account_name($target_wp, $phpbb_id, $access_token);

  try {
    if ($type === 'agoras_created') {
      // Agoras the user "created": in this project, that maps to being the forum moderator.
      // IMPORTANT: phpBB stores moderator assignments in the dedicated {prefix}moderator_cache table.
      // (Do NOT use forums.moderator_cache; it is unreliable / often empty.)
      $forums = $px . 'forums';
      $mods   = $px . 'moderator_cache';

	      // IA Discuss treats "creator" as "forum moderator" and stores that mapping in {prefix}moderator_cache.
	      // Do NOT assume agoras are parent_id=0; in your dataset they can be nested under a category.
	      // Do NOT filter forum_type here either; let the Discuss UI decide what to show.
	      $rows = $db->get_results($db->prepare(
	        "SELECT f.forum_id, f.forum_name, f.left_id
	         FROM {$mods} m
	         JOIN {$forums} f ON f.forum_id = m.forum_id
	         WHERE m.user_id = %d
	         ORDER BY f.left_id ASC
	         LIMIT %d, %d",
	        $phpbb_id, $offset, $limit
	      ), ARRAY_A);

      // If moderator_cache is empty (it is a cache table, not the canonical ACL source),
      // fall back to phpBB ACL tables to determine moderation rights.
      // This ensures "Agoras created" still works even when cache is not populated.
      if (empty($rows)) {
        $acl_users   = $px . 'acl_users';
        $acl_groups  = $px . 'acl_groups';
        $acl_options = $px . 'acl_options';
        $ug          = $px . 'user_group';

        $sql = "SELECT DISTINCT f.forum_id, f.forum_name, f.left_id
                FROM {$forums} f
                JOIN (
                  SELECT au.forum_id
                  FROM {$acl_users} au
                  JOIN {$acl_options} ao ON ao.auth_option_id = au.auth_option_id
                  WHERE au.user_id = %d
                    AND au.forum_id <> 0
                    AND au.auth_setting <> 0
                    AND ao.auth_option LIKE 'm\\_%'
                  UNION
                  SELECT ag.forum_id
                  FROM {$acl_groups} ag
                  JOIN {$acl_options} ao ON ao.auth_option_id = ag.auth_option_id
                  JOIN {$ug} ug2 ON ug2.group_id = ag.group_id AND ug2.user_id = %d AND ug2.user_pending = 0
                  WHERE ag.forum_id <> 0
                    AND ag.auth_setting <> 0
                    AND ao.auth_option LIKE 'm\\_%'
                ) x ON x.forum_id = f.forum_id
                ORDER BY f.forum_name ASC
                LIMIT %d, %d";

        $rows = $db->get_results($db->prepare($sql, $phpbb_id, $phpbb_id, $offset, $limit), ARRAY_A);
      }

      // Join/bell state is about the *viewer* (not the profile owner).
      $me_phpbb = ia_connect_user_phpbb_id($me);
      $joined_map = [];
      $bell_map = [];
      $cover_map = [];
      if ($me_phpbb > 0) {
        global $wpdb;
        $members = $wpdb->prefix . 'ia_discuss_agora_members';
        $covers  = $wpdb->prefix . 'ia_discuss_agora_covers';
        $has_members = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $members));
        $has_covers  = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $covers));

        $forum_ids = [];
        foreach (($rows ?: []) as $r) {
          $fid = (int)($r['forum_id'] ?? 0);
          if ($fid > 0) $forum_ids[$fid] = 1;
        }
        $forum_ids = array_keys($forum_ids);

        if (!empty($forum_ids) && $has_members) {
          $in = implode(',', array_map('intval', $forum_ids));
          $mrows = $wpdb->get_results(
            $wpdb->prepare(
              "SELECT forum_id, notify_agora FROM {$members} WHERE phpbb_user_id = %d AND forum_id IN ({$in})",
              $me_phpbb
            ),
            ARRAY_A
          );
          foreach (($mrows ?: []) as $mr) {
            $fid = (int)($mr['forum_id'] ?? 0);
            if ($fid <= 0) continue;
            $joined_map[$fid] = 1;
            $bell_map[$fid] = ((int)($mr['notify_agora'] ?? 0)) ? 1 : 0;
          }
        }

        if (!empty($forum_ids) && $has_covers) {
          $in = implode(',', array_map('intval', $forum_ids));
          $crows = $wpdb->get_results("SELECT forum_id, cover_url FROM {$covers} WHERE forum_id IN ({$in})", ARRAY_A);
          foreach (($crows ?: []) as $cr) {
            $fid = (int)($cr['forum_id'] ?? 0);
            if ($fid <= 0) continue;
            $cover_map[$fid] = (string)($cr['cover_url'] ?? '');
          }
        }
      }

      foreach (($rows ?: []) as $r) {
        $fid = (int)($r['forum_id'] ?? 0);
        $name = (string)($r['forum_name'] ?? '');
        if ($fid <= 0) continue;
        $items[] = [
          'kind' => 'agora',
          'forum_id' => $fid,
          'forum_name' => $name,
          'topics' => (int)($r['forum_topics'] ?? 0),
          'posts' => (int)($r['forum_posts'] ?? 0),
          'joined' => !empty($joined_map[$fid]) ? 1 : 0,
          'bell' => !empty($bell_map[$fid]) ? 1 : 0,
          'cover_url' => isset($cover_map[$fid]) ? $cover_map[$fid] : '',
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
          "SELECT forum_id FROM {$m} WHERE phpbb_user_id=%d ORDER BY joined_at DESC LIMIT %d, %d",
          $phpbb_id, $offset, $limit
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

          // Join/bell state for the viewer.
          $me_phpbb = ia_connect_user_phpbb_id($me);
          $bell_by = [];
          $joined_by = [];
          $cover_by = [];
          if ($me_phpbb > 0) {
            $c = $wpdb->prefix . 'ia_discuss_agora_covers';
            $has_c = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $c));

            // The membership rows we already fetched for the profile owner include notify_agora; but we need it for the viewer.
            $bell_rows = $wpdb->get_results($wpdb->prepare(
              "SELECT forum_id, notify_agora FROM {$m} WHERE phpbb_user_id=%d AND forum_id IN (" . implode(',', array_map('intval', $forum_ids)) . ")",
              $me_phpbb
            ), ARRAY_A);
            foreach (($bell_rows ?: []) as $br) {
              $fid = (int)($br['forum_id'] ?? 0);
              if ($fid <= 0) continue;
              $joined_by[$fid] = 1;
              $bell_by[$fid] = ((int)($br['notify_agora'] ?? 0)) ? 1 : 0;
            }
            if ($has_c) {
              $crows = $wpdb->get_results("SELECT forum_id, cover_url FROM {$c} WHERE forum_id IN (" . implode(',', array_map('intval', $forum_ids)) . ")", ARRAY_A);
              foreach (($crows ?: []) as $cr) {
                $fid = (int)($cr['forum_id'] ?? 0);
                if ($fid <= 0) continue;
                $cover_by[$fid] = (string)($cr['cover_url'] ?? '');
              }
            }
          }

          foreach ($forum_ids as $fid) {
            $items[] = [
              'kind' => 'agora',
              'forum_id' => (int)$fid,
              'forum_name' => $name_by[$fid] ?? ('Agora ' . $fid),
              'topics' => 0,
              'posts' => 0,
              // Viewer state
              'joined' => !empty($joined_by[$fid]) ? 1 : 0,
              'bell' => !empty($bell_by[$fid]) ? 1 : 0,
              'cover_url' => isset($cover_by[$fid]) ? $cover_by[$fid] : '',
              'excerpt' => '',
              'url' => ia_connect_discuss_url_agora($fid),
            ];
          }
        }
      }
    } elseif ($type === 'topics_created') {
      $topics = $px . 'topics';
      $posts  = $px . 'posts';
      $forums = $px . 'forums';
      $users  = $px . 'users';

      // Resolve author info once.
      $author_username = '';
      try {
        $author_username = (string)($db->get_var($db->prepare(
          "SELECT username FROM {$users} WHERE user_id=%d LIMIT 1",
          $phpbb_id
        )) ?? '');
      } catch (Throwable $e) {
        $author_username = '';
      }
      $author_avatar = ia_connect_avatar_url($target_wp, 48);

      $like = $q !== '' ? '%' . $db->esc_like($q) . '%' : '';
      if ($q !== '') {
        $rows = $db->get_results($db->prepare(
          "SELECT t.topic_id, t.topic_title, t.forum_id, t.topic_time, t.topic_views, t.topic_first_post_id,
                  p.post_text, f.forum_name
           FROM {$topics} t
           LEFT JOIN {$posts} p ON p.post_id = t.topic_first_post_id
           LEFT JOIN {$forums} f ON f.forum_id = t.forum_id
           WHERE t.topic_poster=%d AND t.topic_title LIKE %s
           ORDER BY t.topic_time DESC LIMIT %d, %d",
          $phpbb_id, $like, $offset, $limit
        ), ARRAY_A);
      } else {
        $rows = $db->get_results($db->prepare(
          "SELECT t.topic_id, t.topic_title, t.forum_id, t.topic_time, t.topic_views, t.topic_first_post_id,
                  p.post_text, f.forum_name
           FROM {$topics} t
           LEFT JOIN {$posts} p ON p.post_id = t.topic_first_post_id
           LEFT JOIN {$forums} f ON f.forum_id = t.forum_id
           WHERE t.topic_poster=%d
           ORDER BY t.topic_time DESC LIMIT %d, %d",
          $phpbb_id, $offset, $limit
        ), ARRAY_A);
      }

      foreach (($rows ?: []) as $r) {
        $tid = (int)($r['topic_id'] ?? 0);
        if ($tid <= 0) continue;
        $items[] = [
          'kind' => 'topic',
          'topic_id' => $tid,
          'first_post_id' => (int)($r['topic_first_post_id'] ?? 0),
          'forum_id' => (int)($r['forum_id'] ?? 0),
          'forum_name' => (string)($r['forum_name'] ?? ''),
          'author' => $author_username,
          'author_id' => $phpbb_id,
          'author_avatar' => $author_avatar,
          'time' => (int)($r['topic_time'] ?? 0),
          'views' => (int)($r['topic_views'] ?? 0),
          'title' => (string)($r['topic_title'] ?? ''),
          'excerpt' => ia_connect_excerpt_phpbb((string)($r['post_text'] ?? '')),
          'url' => ia_connect_discuss_url_topic($tid),
        ];
      }
    } else { // replies
      $posts = $px . 'posts';
      $topics = $px . 'topics';
      $forums = $px . 'forums';
      $users  = $px . 'users';

      $author_username = '';
      try {
        $author_username = (string)($db->get_var($db->prepare(
          "SELECT username FROM {$users} WHERE user_id=%d LIMIT 1",
          $phpbb_id
        )) ?? '');
      } catch (Throwable $e) {
        $author_username = '';
      }
      $author_avatar = ia_connect_avatar_url($target_wp, 48);

      $like = $q !== '' ? '%' . $db->esc_like($q) . '%' : '';
      if ($q !== '') {
        $rows = $db->get_results($db->prepare(
          "SELECT p.post_id, p.topic_id, p.post_text, p.post_time,
                  t.topic_title, t.forum_id, t.topic_views, t.topic_first_post_id,
                  f.forum_name
           FROM {$posts} p
           LEFT JOIN {$topics} t ON t.topic_id = p.topic_id
           LEFT JOIN {$forums} f ON f.forum_id = t.forum_id
           WHERE p.poster_id=%d AND p.post_id <> t.topic_first_post_id AND p.post_text LIKE %s
           ORDER BY p.post_time DESC LIMIT %d, %d",
          $phpbb_id, $like, $offset, $limit
        ), ARRAY_A);
      } else {
        $rows = $db->get_results($db->prepare(
          "SELECT p.post_id, p.topic_id, p.post_text, p.post_time,
                  t.topic_title, t.forum_id, t.topic_views, t.topic_first_post_id,
                  f.forum_name
           FROM {$posts} p
           LEFT JOIN {$topics} t ON t.topic_id = p.topic_id
           LEFT JOIN {$forums} f ON f.forum_id = t.forum_id
           WHERE p.poster_id=%d AND p.post_id <> t.topic_first_post_id
           ORDER BY p.post_time DESC LIMIT %d, %d",
          $phpbb_id, $offset, $limit
        ), ARRAY_A);
      }

      foreach (($rows ?: []) as $r) {
        $pid = (int)($r['post_id'] ?? 0);
        $tid = (int)($r['topic_id'] ?? 0);
        if ($pid <= 0 || $tid <= 0) continue;
        $items[] = [
          'kind' => 'reply',
          'topic_id' => $tid,
          'post_id' => $pid,
          'first_post_id' => (int)($r['topic_first_post_id'] ?? 0),
          'forum_id' => (int)($r['forum_id'] ?? 0),
          'forum_name' => (string)($r['forum_name'] ?? ''),
          'author' => $author_username,
          'author_id' => $phpbb_id,
          'author_avatar' => $author_avatar,
          'time' => (int)($r['post_time'] ?? 0),
          'views' => (int)($r['topic_views'] ?? 0),
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


function ia_connect_pg_limit_offset_sql(int $offset, int $limit): string {
  $offset = max(0, $offset);
  $limit = max(1, $limit);
  // PeerTube runs on PostgreSQL, so do not use MySQL's LIMIT offset,count form here.
  return ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
}


function ia_connect_pg_search_condition(string $term, array $columns, string $prefix, array &$params): string {
  $term = trim($term);
  if ($term === '' || empty($columns)) {
    return '1=1';
  }

  $parts = [];
  foreach (array_values($columns) as $idx => $column) {
    $key = ':' . $prefix . '_' . $idx;
    $parts[] = $column . ' ILIKE ' . $key;
    $params[$key] = '%' . $term . '%';
  }

  return '(' . implode(' OR ', $parts) . ')';
}

function ia_connect_stream_resolve_identity_ids(\PDO $pdo, array $row): array {
  $ids = [
    'pt_user' => (int)($row['peertube_user_id'] ?? 0),
    'pt_account' => (int)($row['peertube_account_id'] ?? 0),
    'pt_actor' => (int)($row['peertube_actor_id'] ?? 0),
  ];

  try {
    if ($ids['pt_user'] > 0 && ($ids['pt_account'] <= 0 || $ids['pt_actor'] <= 0)) {
      $st = $pdo->prepare('SELECT id, "actorId" FROM public.account WHERE "userId" = :uid LIMIT 1');
      $st->execute([':uid' => $ids['pt_user']]);
      $acct = $st->fetch();
      if (is_array($acct) && !empty($acct)) {
        if ($ids['pt_account'] <= 0) $ids['pt_account'] = (int)($acct['id'] ?? 0);
        if ($ids['pt_actor'] <= 0) $ids['pt_actor'] = (int)($acct['actorId'] ?? 0);
      }
    }

    if ($ids['pt_account'] > 0 && ($ids['pt_user'] <= 0 || $ids['pt_actor'] <= 0)) {
      $st = $pdo->prepare('SELECT "userId", "actorId" FROM public.account WHERE id = :acct LIMIT 1');
      $st->execute([':acct' => $ids['pt_account']]);
      $acct = $st->fetch();
      if (is_array($acct) && !empty($acct)) {
        if ($ids['pt_user'] <= 0) $ids['pt_user'] = (int)($acct['userId'] ?? 0);
        if ($ids['pt_actor'] <= 0) $ids['pt_actor'] = (int)($acct['actorId'] ?? 0);
      }
    }

    if ($ids['pt_actor'] > 0 && ($ids['pt_account'] <= 0 || $ids['pt_user'] <= 0)) {
      $st = $pdo->prepare('SELECT id, "userId" FROM public.account WHERE "actorId" = :actor LIMIT 1');
      $st->execute([':actor' => $ids['pt_actor']]);
      $acct = $st->fetch();
      if (is_array($acct) && !empty($acct)) {
        if ($ids['pt_account'] <= 0) $ids['pt_account'] = (int)($acct['id'] ?? 0);
        if ($ids['pt_user'] <= 0) $ids['pt_user'] = (int)($acct['userId'] ?? 0);
      }
    }
  } catch (Throwable $e) {
    // Keep profile activity read-only and fail-soft.
  }

  return $ids;
}


function ia_connect_stream_user_access_token(int $phpbb_user_id): string {
  if ($phpbb_user_id <= 0) return '';

  if (is_user_logged_in() && class_exists('IA_PeerTube_Token_Helper') && method_exists('IA_PeerTube_Token_Helper', 'get_token_status_for_current_user')) {
    try {
      $status = IA_PeerTube_Token_Helper::get_token_status_for_current_user();
      if (is_array($status) && (int)($status['phpbb_user_id'] ?? 0) === $phpbb_user_id && !empty($status['ok'])) {
        $token = trim((string)($status['token'] ?? ''));
        if ($token !== '') return $token;
      }
    } catch (Throwable $e) {
      // Fall back to legacy IA_Auth storage below.
    }
  }

  if (!class_exists('IA_Auth') || !method_exists('IA_Auth', 'instance')) return '';

  try {
    $ia = IA_Auth::instance();
    if (!is_object($ia) || !isset($ia->db) || !is_object($ia->db) || !method_exists($ia->db, 'get_tokens_by_phpbb_user_id')) return '';
    if (!isset($ia->crypto) || !is_object($ia->crypto) || !method_exists($ia->crypto, 'decrypt')) return '';

    $tok = $ia->db->get_tokens_by_phpbb_user_id($phpbb_user_id);
    if (!is_array($tok) || empty($tok['access_token_enc'])) return '';

    return (string)$ia->crypto->decrypt((string)$tok['access_token_enc']);
  } catch (Throwable $e) {
    return '';
  }
}

function ia_connect_stream_api_get(string $path, array $query = [], string $access_token = ''): array {
  $base = untrailingslashit(ia_connect_peertube_public_url());
  if ($base === '') {
    return ['ok' => false, 'status' => 0, 'message' => 'Missing PeerTube base URL.', 'json' => null];
  }

  $url = $base . $path;
  if (!empty($query)) {
    $url = add_query_arg(array_filter($query, static function ($value) {
      return $value !== null && $value !== '';
    }), $url);
  }

  $headers = [ 'Accept' => 'application/json' ];
  if ($access_token !== '') {
    $headers['Authorization'] = 'Bearer ' . $access_token;
  }

  $res = wp_remote_get($url, [
    'timeout' => 20,
    'redirection' => 3,
    'headers' => $headers,
  ]);

  if (is_wp_error($res)) {
    return ['ok' => false, 'status' => 0, 'message' => $res->get_error_message(), 'json' => null];
  }

  $code = (int) wp_remote_retrieve_response_code($res);
  $body = (string) wp_remote_retrieve_body($res);
  $json = json_decode($body, true);

  if ($code < 200 || $code >= 300) {
    $msg = is_array($json) ? (string)($json['detail'] ?? $json['message'] ?? '') : '';
    if ($msg === '') $msg = 'HTTP ' . $code;
    return ['ok' => false, 'status' => $code, 'message' => $msg, 'json' => is_array($json) ? $json : null];
  }

  return ['ok' => true, 'status' => $code, 'message' => '', 'json' => is_array($json) ? $json : []];
}

function ia_connect_stream_account_name(int $target_wp, int $phpbb_id, string $access_token = ''): string {
  if ($access_token !== '') {
    $me = ia_connect_stream_api_get('/api/v1/users/me', [], $access_token);
    if (!empty($me['ok'])) {
      $json = $me['json'];
      if (is_array($json) && isset($json[0]) && is_array($json[0])) $json = $json[0];
      $name = (string)($json['account']['name'] ?? '');
      if ($name !== '') return $name;
    }
  }

  global $wpdb;
  $imap = $wpdb->prefix . 'ia_identity_map';
  $row = $wpdb->get_row($wpdb->prepare("SELECT phpbb_username_clean FROM {$imap} WHERE phpbb_user_id=%d LIMIT 1", $phpbb_id), ARRAY_A);
  $name = (string)($row['phpbb_username_clean'] ?? '');
  if ($name !== '') return $name;

  $user = get_userdata($target_wp);
  if ($user && !empty($user->user_login)) return (string)$user->user_login;

  return '';
}

function ia_connect_stream_video_meta_map(\PDO $pdo, array $video_ids): array {
  $video_ids = array_values(array_unique(array_filter(array_map('intval', $video_ids))));
  if (empty($video_ids)) return [];

  $placeholders = implode(',', array_fill(0, count($video_ids), '?'));
  $sql = 'SELECT v.id, v.url, v.description, th."fileUrl" AS thumb_url, th.filename AS thumb_filename
          FROM public.video v
          LEFT JOIN LATERAL (
            SELECT t."fileUrl", t.filename
            FROM public.thumbnail t
            WHERE t."videoId" = v.id
            ORDER BY (t."fileUrl" IS NULL) ASC, t.type ASC, t.height DESC NULLS LAST
            LIMIT 1
          ) th ON TRUE
          WHERE v.id IN ({$placeholders})';
  $st = $pdo->prepare($sql);
  $st->execute($video_ids);
  $rows = $st->fetchAll();
  $out = [];
  foreach (($rows ?: []) as $r) {
    $out[(int)$r['id']] = $r;
  }
  return $out;
}

function ia_connect_stream_playlist_meta_map(\PDO $pdo, array $playlist_ids): array {
  $playlist_ids = array_values(array_unique(array_filter(array_map('intval', $playlist_ids))));
  if (empty($playlist_ids)) return [];

  $placeholders = implode(',', array_fill(0, count($playlist_ids), '?'));
  $sql = 'SELECT vp.id, vp.url, vp.description, th."fileUrl" AS thumb_url, th.filename AS thumb_filename
          FROM public."videoPlaylist" vp
          LEFT JOIN LATERAL (
            SELECT t."fileUrl", t.filename
            FROM public.thumbnail t
            WHERE t."videoPlaylistId" = vp.id
            ORDER BY (t."fileUrl" IS NULL) ASC, t.type ASC, t.height DESC NULLS LAST
            LIMIT 1
          ) th ON TRUE
          WHERE vp.id IN ({$placeholders})';
  $st = $pdo->prepare($sql);
  $st->execute($playlist_ids);
  $rows = $st->fetchAll();
  $out = [];
  foreach (($rows ?: []) as $r) {
    $out[(int)$r['id']] = $r;
  }
  return $out;
}

function ia_connect_stream_thumb_payload(string $base, string $thumb_url = '', string $thumb_filename = '', array $api_thumbnails = [], string $api_thumbnail_path = '', string $api_preview_path = ''): array {
  $thumb = $thumb_url !== '' ? ia_connect_join_url($base, $thumb_url) : '';
  $fallbacks = [];

  if ($thumb_filename !== '') {
    $fallbacks[] = ia_connect_join_url($base, '/static/thumbnails/' . ltrim($thumb_filename, '/'));
    $fallbacks[] = ia_connect_join_url($base, '/lazy-static/thumbnails/' . ltrim($thumb_filename, '/'));
    $fallbacks[] = ia_connect_join_url($base, '/lazy-static/previews/' . ltrim($thumb_filename, '/'));
    $fallbacks[] = ia_connect_join_url($base, '/' . ltrim($thumb_filename, '/'));
  }

  foreach ($api_thumbnails as $t) {
    if (!is_array($t)) continue;
    $path = (string)($t['path'] ?? $t['url'] ?? '');
    if ($path !== '') $fallbacks[] = ia_connect_join_url($base, $path);
  }
  if ($api_thumbnail_path !== '') $fallbacks[] = ia_connect_join_url($base, $api_thumbnail_path);
  if ($api_preview_path !== '') $fallbacks[] = ia_connect_join_url($base, $api_preview_path);

  $fallbacks = array_values(array_unique(array_filter($fallbacks)));
  if ($thumb === '' && !empty($fallbacks)) $thumb = $fallbacks[0];

  return ['thumb' => $thumb, 'thumb_fallbacks' => $fallbacks];
}


function ia_connect_stream_api_rows(array $response): array {
  if (empty($response['ok']) || !is_array($response['json'])) return [];
  $json = $response['json'];
  if (isset($json['data']) && is_array($json['data'])) return $json['data'];
  if (isset($json['items']) && is_array($json['items'])) return $json['items'];
  if (array_keys($json) === range(0, count($json) - 1)) return $json;
  return [];
}

function ia_connect_stream_map_video_item_from_api(array $video, string $base, string $fallback_excerpt = ''): array {
  $thumb_payload = ia_connect_stream_thumb_payload(
    $base,
    '',
    '',
    (array)($video['thumbnails'] ?? []),
    (string)($video['thumbnailPath'] ?? ''),
    (string)($video['previewPath'] ?? '')
  );

  $url = '';
  if (!empty($video['url'])) {
    $url = ia_connect_join_url($base, (string)$video['url']);
  } elseif (!empty($video['embedPath'])) {
    $url = ia_connect_join_url($base, (string)$video['embedPath']);
  } elseif (!empty($video['shortUUID'])) {
    $url = ia_connect_join_url($base, '/w/' . rawurlencode((string)$video['shortUUID']));
  } elseif (!empty($video['uuid'])) {
    $url = ia_connect_join_url($base, '/w/' . rawurlencode((string)$video['uuid']));
  }

  $excerpt = (string)($video['truncatedDescription'] ?? $video['description'] ?? $fallback_excerpt);

  return [
    'title' => (string)($video['name'] ?? ''),
    'excerpt' => ia_connect_excerpt_text($excerpt),
    'url' => $url,
    'thumb' => $thumb_payload['thumb'],
    'thumb_fallbacks' => $thumb_payload['thumb_fallbacks'],
  ];
}

function ia_connect_stream_map_playlist_item_from_api(array $playlist, string $base): array {
  $thumb_payload = ia_connect_stream_thumb_payload(
    $base,
    '',
    '',
    (array)($playlist['thumbnails'] ?? []),
    (string)($playlist['thumbnailPath'] ?? ''),
    ''
  );

  $url = '';
  if (!empty($playlist['url'])) {
    $url = ia_connect_join_url($base, (string)$playlist['url']);
  } elseif (!empty($playlist['shortUUID'])) {
    $url = ia_connect_join_url($base, '/w/p/' . rawurlencode((string)$playlist['shortUUID']));
  } elseif (!empty($playlist['uuid'])) {
    $url = ia_connect_join_url($base, '/w/p/' . rawurlencode((string)$playlist['uuid']));
  }

  return [
    'title' => (string)($playlist['displayName'] ?? $playlist['name'] ?? ''),
    'excerpt' => ia_connect_excerpt_text((string)($playlist['description'] ?? '')),
    'url' => $url,
    'thumb' => $thumb_payload['thumb'],
    'thumb_fallbacks' => $thumb_payload['thumb_fallbacks'],
  ];
}

function ia_connect_stream_map_subscription_item_from_api(array $channel, string $base): array {
  $avatars = [];
  if (!empty($channel['avatars']) && is_array($channel['avatars'])) {
    $avatars = (array) $channel['avatars'];
  } elseif (!empty($channel['avatar']) && is_array($channel['avatar'])) {
    $avatars = [ (array) $channel['avatar'] ];
  }

  $thumb_payload = ia_connect_stream_thumb_payload(
    $base,
    '',
    '',
    $avatars,
    (string)($channel['avatarPath'] ?? ''),
    ''
  );

  $url = '';
  if (!empty($channel['url'])) {
    $url = ia_connect_join_url($base, (string)$channel['url']);
  } elseif (!empty($channel['name'])) {
    $url = ia_connect_join_url($base, '/c/' . rawurlencode((string)$channel['name']) . '/videos');
  }

  $owner = '';
  if (!empty($channel['ownerAccount']['displayName'])) {
    $owner = (string) $channel['ownerAccount']['displayName'];
  } elseif (!empty($channel['ownerAccount']['name'])) {
    $owner = (string) $channel['ownerAccount']['name'];
  }

  $excerpt = (string)($channel['description'] ?? '');
  if ($excerpt === '') {
    $excerpt = $owner !== '' ? ('Subscribed channel' . ' · ' . $owner) : 'Subscribed channel';
  }

  return [
    'title' => (string)($channel['displayName'] ?? $channel['name'] ?? ''),
    'excerpt' => ia_connect_excerpt_text($excerpt),
    'url' => $url,
    'thumb' => $thumb_payload['thumb'],
    'thumb_fallbacks' => $thumb_payload['thumb_fallbacks'],
  ];
}

function ia_connect_stream_map_comment_item_from_feed(array $item, string $base): array {
  $url = (string)($item['url'] ?? $item['external_url'] ?? '');
  $title = (string)($item['title'] ?? 'Comment');
  $excerpt = (string)($item['content_text'] ?? $item['summary'] ?? '');
  if ($excerpt === '' && !empty($item['content_html'])) {
    $excerpt = wp_strip_all_tags((string)$item['content_html']);
  }

  return [
    'title' => $title,
    'excerpt' => ia_connect_excerpt_text($excerpt),
    'url' => ia_connect_join_url($base, $url),
    'thumb' => '',
    'thumb_fallbacks' => [],
  ];
}

function ia_connect_stream_comment_like_items(\PDO $pdo, int $phpbb_user_id, string $base, string $q = '', int $offset = 0, int $limit = 0): array {
  global $wpdb;
  if ($phpbb_user_id <= 0 || !($wpdb instanceof wpdb)) return [];

  $table = $wpdb->prefix . 'ia_stream_comment_votes';
  $has_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
  if (!$has_table) return [];

  $vote_rows = $wpdb->get_results($wpdb->prepare(
    "SELECT comment_id, updated_at FROM {$table} WHERE phpbb_user_id=%d AND rating=1 ORDER BY updated_at DESC, id DESC",
    $phpbb_user_id
  ), ARRAY_A);
  if (!is_array($vote_rows) || empty($vote_rows)) return [];

  $comment_ids = [];
  $liked_at = [];
  foreach ($vote_rows as $row) {
    $comment_id = trim((string)($row['comment_id'] ?? ''));
    if ($comment_id === '' || isset($liked_at[$comment_id])) continue;
    $comment_ids[] = $comment_id;
    $liked_at[$comment_id] = (string)($row['updated_at'] ?? '');
  }
  if (empty($comment_ids)) return [];

  $placeholders = implode(',', array_fill(0, count($comment_ids), '?'));
  $sql = "SELECT vc.id, vc.text, v.name AS video_name, v.url AS video_url
          FROM public.\"videoComment\" vc
          JOIN public.video v ON v.id = vc.\"videoId\"
          WHERE vc.id IN ({$placeholders})";
  $st = $pdo->prepare($sql);
  $st->execute($comment_ids);
  $rows = $st->fetchAll();
  if (!is_array($rows) || empty($rows)) return [];

  $meta = [];
  foreach ($rows as $row) {
    $meta[(string)($row['id'] ?? '')] = $row;
  }

  $items = [];
  foreach ($comment_ids as $comment_id) {
    if (!isset($meta[$comment_id])) continue;
    $row = $meta[$comment_id];
    $title = trim((string)($row['video_name'] ?? ''));
    if ($title === '') $title = 'Liked comment';
    else $title .= ' - liked comment';

    $excerpt = ia_connect_excerpt_text((string)($row['text'] ?? ''));
    if ($excerpt === '') $excerpt = 'Liked comment';

    if ($q !== '') {
      $hay = strtolower($title . ' ' . $excerpt);
      if (strpos($hay, strtolower($q)) === false) continue;
    }

    $items[] = [
      'title' => $title,
      'excerpt' => $excerpt,
      'url' => ia_connect_join_url($base, (string)($row['video_url'] ?? '')),
      'thumb' => '',
      'thumb_fallbacks' => [],
      '_liked_at' => $liked_at[$comment_id] ?? '',
    ];
  }

  usort($items, static function (array $a, array $b): int {
    return strcmp((string)($b['_liked_at'] ?? ''), (string)($a['_liked_at'] ?? ''));
  });

  if ($offset > 0) $items = array_slice($items, $offset);
  if ($limit > 0) $items = array_slice($items, 0, $limit);

  foreach ($items as &$item) {
    unset($item['_liked_at']);
  }
  unset($item);

  return $items;
}
function ia_connect_stream_map_video_item(array $video, array $meta, string $base, string $fallback_excerpt = ''): array {
  $thumb_payload = ia_connect_stream_thumb_payload(
    $base,
    (string)($meta['thumb_url'] ?? ''),
    (string)($meta['thumb_filename'] ?? ''),
    (array)($video['thumbnails'] ?? []),
    (string)($video['thumbnailPath'] ?? ''),
    (string)($video['previewPath'] ?? '')
  );

  $url = (string)($meta['url'] ?? '');
  if ($url === '' && !empty($video['embedPath'])) {
    $url = ia_connect_join_url($base, (string)$video['embedPath']);
  } else {
    $url = ia_connect_join_url($base, $url);
  }

  $excerpt = (string)($video['truncatedDescription'] ?? $video['description'] ?? $meta['description'] ?? $fallback_excerpt);

  return [
    'title' => (string)($video['name'] ?? ''),
    'excerpt' => ia_connect_excerpt_text($excerpt),
    'url' => $url,
    'thumb' => $thumb_payload['thumb'],
    'thumb_fallbacks' => $thumb_payload['thumb_fallbacks'],
  ];
}

function ia_connect_stream_map_playlist_item(array $playlist, array $meta, string $base): array {
  $thumb_payload = ia_connect_stream_thumb_payload(
    $base,
    (string)($meta['thumb_url'] ?? ''),
    (string)($meta['thumb_filename'] ?? ''),
    (array)($playlist['thumbnails'] ?? []),
    (string)($playlist['thumbnailPath'] ?? ''),
    ''
  );

  return [
    'title' => (string)($playlist['displayName'] ?? ''),
    'excerpt' => ia_connect_excerpt_text((string)($playlist['description'] ?? $meta['description'] ?? '')),
    'url' => ia_connect_join_url($base, (string)($meta['url'] ?? '')),
    'thumb' => $thumb_payload['thumb'],
    'thumb_fallbacks' => $thumb_payload['thumb_fallbacks'],
  ];
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
  if ($base === '') {
    wp_send_json_error(['message' => 'PeerTube base URL unavailable.'], 500);
  }

  if ($pdo) {
    // PeerTube profile activity keys can be partially populated in older identity rows.
    // Resolve all three ids from whichever one we have, then persist the repaired values.
    $resolved_ids = ia_connect_stream_resolve_identity_ids($pdo, [
      'peertube_user_id' => $pt_user,
      'peertube_account_id' => $pt_account,
      'peertube_actor_id' => $pt_actor,
    ]);
    $pt_user = (int)($resolved_ids['pt_user'] ?? 0);
    $pt_account = (int)($resolved_ids['pt_account'] ?? 0);
    $pt_actor = (int)($resolved_ids['pt_actor'] ?? 0);

    if ($pt_user > 0 || $pt_account > 0 || $pt_actor > 0) {
      $wpdb->update(
        $imap,
        [
          'peertube_user_id'    => ($pt_user > 0 ? $pt_user : null),
          'peertube_account_id' => ($pt_account > 0 ? $pt_account : null),
          'peertube_actor_id'   => ($pt_actor > 0 ? $pt_actor : null),
          'updated_at'          => gmdate('Y-m-d H:i:s'),
        ],
        ['phpbb_user_id' => $phpbb_id]
      );
    }
  }

  $items = [];
  $has_more = false;
  $offset = ($page - 1) * $per;
  $limit = $per + 1;
  $is_self_stream = ($target_wp === $me);
  $access_token = $is_self_stream ? ia_connect_stream_user_access_token($phpbb_id) : '';
  if ($is_self_stream && $access_token === '' && class_exists('IA_PeerTube_Token_Helper') && method_exists('IA_PeerTube_Token_Helper', 'get_token_status_for_current_user')) {
    try {
      $token_status = IA_PeerTube_Token_Helper::get_token_status_for_current_user();
      error_log('[ia-connect][stream_activity] self stream token unavailable: ' . (string)($token_status['code'] ?? 'unknown'));
    } catch (Throwable $e) {
      // ignore
    }
  }
  $account_name = ia_connect_stream_account_name($target_wp, $phpbb_id, $access_token);

  try {
    if ($type === 'videos') {
      $api_rows = null;
      if ($is_self_stream && $access_token !== '') {
        $api = ia_connect_stream_api_get('/api/v1/users/me/videos', [
          'start' => $offset,
          'count' => $limit,
          'search' => $q,
          'sort' => '-publishedAt',
        ], $access_token);
        $api_rows = ia_connect_stream_api_rows($api);
      }
      if ((!is_array($api_rows) || empty($api_rows)) && $account_name !== '') {
        $api = ia_connect_stream_api_get('/api/v1/accounts/' . rawurlencode($account_name) . '/videos', [
          'start' => $offset,
          'count' => $limit,
          'search' => $q,
          'sort' => '-publishedAt',
        ]);
        $api_rows = ia_connect_stream_api_rows($api);
      }

      if (is_array($api_rows)) {
        foreach ($api_rows as $row) {
          $items[] = ia_connect_stream_map_video_item_from_api($row, $base);
        }
      } else {
        if ($pt_account <= 0) wp_send_json_success(['items' => [], 'has_more' => false]);
        $params = [':acct' => $pt_account];
        $search_sql = ia_connect_pg_search_condition($q, ['v.name', 'v.description'], 'videos_q', $params);
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
                  AND " . $search_sql . "
                ORDER BY v.\"publishedAt\" DESC
                " . ia_connect_pg_limit_offset_sql($offset, $limit) . "";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        foreach (($rows ?: []) as $r) {
          $thumb_payload = ia_connect_stream_thumb_payload($base, (string)($r['thumb_url'] ?? ''), (string)($r['thumb_filename'] ?? ''));
          $items[] = [
            'title' => (string)($r['name'] ?? ''),
            'excerpt' => ia_connect_excerpt_text((string)($r['description'] ?? '')),
            'url' => ia_connect_join_url($base, (string)($r['url'] ?? '')),
            'thumb' => $thumb_payload['thumb'],
            'thumb_fallbacks' => $thumb_payload['thumb_fallbacks'],
          ];
        }
      }
    } elseif ($type === 'comments') {
      if ($account_name === '') wp_send_json_success(['items' => [], 'has_more' => false]);
      $feed = ia_connect_stream_api_get('/feeds/video-comments.json', [
        'accountName' => $account_name,
      ], $access_token);
      $feed_rows = ia_connect_stream_api_rows($feed);
      if ($q !== '') {
        $feed_rows = array_values(array_filter($feed_rows, static function ($row) use ($q) {
          $hay = strtolower((string)($row['title'] ?? '') . ' ' . (string)($row['content_text'] ?? '') . ' ' . (string)($row['summary'] ?? ''));
          return strpos($hay, strtolower($q)) !== false;
        }));
      }
      $feed_rows = array_slice($feed_rows, $offset, $limit);
      foreach ($feed_rows as $row) {
        $items[] = ia_connect_stream_map_comment_item_from_feed($row, $base);
      }
    } elseif ($type === 'likes') {
      $api_rows = null;
      if ($account_name !== '') {
        $api = ia_connect_stream_api_get('/api/v1/accounts/' . rawurlencode($account_name) . '/ratings', [
          'start' => $offset,
          'count' => $limit,
          'rating' => 'like',
          'sort' => '-createdAt',
        ], $access_token);
        $api_rows = ia_connect_stream_api_rows($api);
      }

      if (is_array($api_rows)) {
        if ($q !== '') {
          $api_rows = array_values(array_filter($api_rows, static function ($row) use ($q) {
            $name = (string)($row['video']['name'] ?? '');
            return stripos($name, $q) !== false;
          }));
        }
        foreach ($api_rows as $row) {
          $video = is_array($row['video'] ?? null) ? $row['video'] : [];
          $items[] = ia_connect_stream_map_video_item_from_api($video, $base, 'Liked video');
        }
      } else {
        if ($pt_account <= 0) wp_send_json_success(['items' => [], 'has_more' => false]);
        $params = [':acct' => $pt_account];
        $search_sql = ia_connect_pg_search_condition($q, ['v.name'], 'likes_q', $params);
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
                  AND " . $search_sql . "
                ORDER BY avr.\"createdAt\" DESC
                " . ia_connect_pg_limit_offset_sql($offset, $limit) . "";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        foreach (($rows ?: []) as $r) {
          $thumb_payload = ia_connect_stream_thumb_payload($base, (string)($r['thumb_url'] ?? ''), (string)($r['thumb_filename'] ?? ''));
          $items[] = [
            'title' => (string)($r['name'] ?? ''),
            'excerpt' => 'Liked video',
            'url' => ia_connect_join_url($base, (string)($r['url'] ?? '')),
            'thumb' => $thumb_payload['thumb'],
            'thumb_fallbacks' => $thumb_payload['thumb_fallbacks'],
          ];
        }
      }
    } elseif ($type === 'subscriptions') {
      if ($is_self_stream && $access_token !== '') {
        $api = ia_connect_stream_api_get('/api/v1/users/me/subscriptions', [
          'start' => $offset,
          'count' => $limit,
          'sort' => '-createdAt',
        ], $access_token);
        $subscription_rows = ia_connect_stream_api_rows($api);
        if ($q !== '') {
          $subscription_rows = array_values(array_filter($subscription_rows, static function ($row) use ($q) {
            $hay = strtolower(
              (string)($row['displayName'] ?? '') . ' ' .
              (string)($row['name'] ?? '') . ' ' .
              (string)($row['description'] ?? '') . ' ' .
              (string)($row['ownerAccount']['displayName'] ?? '') . ' ' .
              (string)($row['ownerAccount']['name'] ?? '')
            );
            return strpos($hay, strtolower($q)) !== false;
          }));
        }
        foreach ($subscription_rows as $row) {
          $items[] = ia_connect_stream_map_subscription_item_from_api((array)$row, $base);
        }
      }
    } elseif ($type === 'playlists') {
      $api_rows = null;
      if ($account_name !== '') {
        $api = ia_connect_stream_api_get('/api/v1/accounts/' . rawurlencode($account_name) . '/video-playlists', [
          'start' => $offset,
          'count' => $limit,
          'search' => $q,
          'sort' => '-createdAt',
        ], $access_token);
        $api_rows = ia_connect_stream_api_rows($api);
      }

      if (is_array($api_rows)) {
        foreach ($api_rows as $row) {
          $items[] = ia_connect_stream_map_playlist_item_from_api($row, $base);
        }
      } else {
        if ($pt_account <= 0) wp_send_json_success(['items' => [], 'has_more' => false]);
        $params = [':acct' => $pt_account];
        $search_sql = ia_connect_pg_search_condition($q, ['vp.name', 'vp.description'], 'playlists_q', $params);
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
                  AND " . $search_sql . "
                ORDER BY vp.\"createdAt\" DESC
                " . ia_connect_pg_limit_offset_sql($offset, $limit) . "";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        foreach (($rows ?: []) as $r) {
          $thumb_payload = ia_connect_stream_thumb_payload($base, (string)($r['thumb_url'] ?? ''), (string)($r['thumb_filename'] ?? ''));
          $items[] = [
            'title' => (string)($r['name'] ?? ''),
            'excerpt' => ia_connect_excerpt_text((string)($r['description'] ?? '')),
            'url' => ia_connect_join_url($base, (string)($r['url'] ?? '')),
            'thumb' => $thumb_payload['thumb'],
            'thumb_fallbacks' => $thumb_payload['thumb_fallbacks'],
          ];
        }
      }
    } else { // history
      if ($is_self_stream && $access_token !== '') {
        $api = ia_connect_stream_api_get('/api/v1/users/me/history/videos', [
          'start' => $offset,
          'count' => $limit,
          'search' => $q,
        ], $access_token);
        $video_rows = ia_connect_stream_api_rows($api);
        foreach ($video_rows as $row) {
          $items[] = ia_connect_stream_map_video_item_from_api($row, $base, 'Watched');
        }
      } else {
        if ($pt_user <= 0) wp_send_json_success(['items' => [], 'has_more' => false]);
        $params = [':uid' => $pt_user];
        $search_sql = ia_connect_pg_search_condition($q, ['v.name'], 'history_q', $params);
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
                  AND " . $search_sql . "
                ORDER BY uvh.\"updatedAt\" DESC
                " . ia_connect_pg_limit_offset_sql($offset, $limit) . "";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        foreach (($rows ?: []) as $r) {
          $thumb_payload = ia_connect_stream_thumb_payload($base, (string)($r['thumb_url'] ?? ''), (string)($r['thumb_filename'] ?? ''));
          $items[] = [
            'title' => (string)($r['name'] ?? ''),
            'excerpt' => 'Watched',
            'url' => ia_connect_join_url($base, (string)($r['url'] ?? '')),
            'thumb' => $thumb_payload['thumb'],
            'thumb_fallbacks' => $thumb_payload['thumb_fallbacks'],
          ];
        }
      }
    }
  } catch (Throwable $e) {
    error_log('[ia-connect][stream_activity] PeerTube query failed for type ' . $type . ': ' . $e->getMessage());
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

  // Block guard: do not allow posting between blocked users.
  $wall_phpbb_resolved = $wall_phpbb > 0 ? $wall_phpbb : ($wall_wp > 0 ? ia_connect_user_phpbb_id($wall_wp) : 0);
  if ($wall_phpbb_resolved > 0 && ia_user_rel_is_blocked_any($author_phpbb, $wall_phpbb_resolved)) {
    wp_send_json_error(['message' => 'Blocked.'], 403);
  }

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

  /**
   * Cross-platform activity signal (followers/notifications).
   *
   * @param int    $actor_phpbb_id
   * @param string $type
   * @param array  $payload
   */
  do_action('ia_user_activity', $author_phpbb, 'connect_post', [
    'post_id' => $post_id,
    'wall_owner_phpbb_id' => $wall_phpbb_resolved,
  ]);

  // Author auto-follows their own post.
  ia_connect_follow_set($post_id, $me, true);

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

  $me_phpbb = ia_connect_user_phpbb_id($me);
  // Resolve wall phpBB id (canonical). If blocked either way, return empty wall.
  $wall_phpbb_resolved = $wall_phpbb > 0 ? $wall_phpbb : ($wall_wp > 0 ? ia_connect_user_phpbb_id($wall_wp) : 0);
  if ($wall_phpbb_resolved > 0 && ia_user_rel_is_blocked_any($me_phpbb, $wall_phpbb_resolved)) {
    wp_send_json_success(['posts' => [], 'bp' => [], 'blocked' => true]);
  }

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

  // Filter out posts authored by blocked users.
  $blocked = ia_user_rel_blocked_ids_for($me_phpbb);
  if (!empty($blocked)) {
    $blocked_set = array_fill_keys($blocked, true);
    $ids = array_values(array_filter($ids, function($pid) use ($wpdb, $posts, $blocked_set){
      $a = (int) $wpdb->get_var($wpdb->prepare("SELECT author_phpbb_id FROM {$posts} WHERE id=%d LIMIT 1", (int)$pid));
      return $a <= 0 || empty($blocked_set[$a]);
    }));
  }

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

  // Block guard: never expose payload between blocked users.
  $me_wp = (int) get_current_user_id();
  $me_phpbb = $me_wp > 0 ? ia_connect_user_phpbb_id($me_wp) : 0;

  $author_wp = (int)($p['author_wp_id'] ?? 0);
  // Prefer the stored phpBB author id from the row (can be present even when author_wp_id is 0).
  $author_phpbb = (int)($p['author_phpbb_id'] ?? 0);
  if ($author_phpbb <= 0 && $author_wp > 0) {
    $author_phpbb = ia_connect_user_phpbb_id($author_wp);
  }

  $author_u = $author_wp > 0 ? get_userdata($author_wp) : null;
  $author_name = $author_u ? ($author_u->display_name ?: $author_u->user_login) : 'deleted user';
  $author_login = $author_u ? (string)$author_u->user_login : '';
  // $author_phpbb resolved above.
  if ($me_phpbb > 0 && $author_phpbb > 0 && ia_user_rel_is_blocked_any($me_phpbb, $author_phpbb)) return [];

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

  // Permission flags (UI should prefer these instead of recalculating).
  $can_edit_post = ($me_wp > 0) && (
    $me_wp === $author_wp ||
    ia_connect_viewer_is_admin($me_wp) ||
    ($me_phpbb > 0 && $author_phpbb > 0 && $me_phpbb === $author_phpbb)
  );

  // Load top-level comments (first 3) for card preview.
  $me = get_current_user_id();
  $comments = [];
  $crows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $comms WHERE post_id=%d AND parent_comment_id=0 AND is_deleted=0 ORDER BY id ASC LIMIT 3", $post_id), ARRAY_A);
  // Filter out blocked comment authors.
  $blocked_commenters = $me_phpbb > 0 ? array_fill_keys(ia_user_rel_blocked_ids_for($me_phpbb), true) : [];
  foreach ($crows as $c) {
    $cwp = (int)$c['author_wp_id'];
    $cu = $cwp>0 ? get_userdata($cwp) : null;
    $cname = $cu ? ($cu->display_name ?: $cu->user_login) : 'deleted user';
    $clogin = $cu ? (string)$cu->user_login : '';
    $cphpbb = (int)($c['author_phpbb_id'] ?? 0);
    if ($cphpbb > 0 && !empty($blocked_commenters[$cphpbb])) continue;
    $can_edit_comment = (
      ($me > 0) && ($me === $cwp || ia_connect_viewer_is_admin($me))
    ) || (
      ($me_phpbb > 0) && ($cphpbb > 0) && ($me_phpbb === $cphpbb)
    );
    $comments[] = [
      'id' => (int)$c['id'],
      'author_wp_id' => $cwp,
      'author_phpbb_id' => $cphpbb,
      'author_username' => $clogin,
      'author' => $cname,
      'author_avatar' => $cwp>0 ? ia_connect_avatar_url($cwp, 48) : '',
      'body' => (string)$c['body'],
      'created_at' => (string)$c['created_at'],
      'can_edit' => $can_edit_comment,
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
    'i_following' => ($me>0 ? ia_connect_is_following($post_id, $me) : false),
    'can_edit' => $can_edit_post,
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

  // Block guard: do not allow commenting between blocked users.
  $posts_t = $wpdb->prefix . 'ia_connect_posts';
  $pa = $wpdb->get_row($wpdb->prepare("SELECT author_phpbb_id, wall_owner_phpbb_id FROM {$posts_t} WHERE id=%d LIMIT 1", $post_id), ARRAY_A);
  $post_author_phpbb = (int)($pa['author_phpbb_id'] ?? 0);
  $wall_owner_phpbb = (int)($pa['wall_owner_phpbb_id'] ?? 0);
  if (($post_author_phpbb>0 && ia_user_rel_is_blocked_any($author_phpbb, $post_author_phpbb)) || ($wall_owner_phpbb>0 && ia_user_rel_is_blocked_any($author_phpbb, $wall_owner_phpbb))) {
    wp_send_json_error(['message' => 'Blocked.'], 403);
  }

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

  /**
   * Cross-platform activity signal (followers/notifications).
   */
  do_action('ia_user_activity', $author_phpbb, 'connect_reply', [
    'post_id' => $post_id,
    'comment_id' => $cid,
  ]);

  // Replier auto-follows.
  ia_connect_follow_set($post_id, $me, true);

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

  // For permission + block filtering fallbacks.
  $me_phpbb = $me > 0 ? ia_connect_user_phpbb_id($me) : 0;
  $blocked_commenters = $me_phpbb > 0 ? array_fill_keys(ia_user_rel_blocked_ids_for($me_phpbb), true) : [];

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
    if ($cphpbb > 0 && !empty($blocked_commenters[$cphpbb])) continue;
    $can_edit_comment = (
      ($me > 0) && ($me === $cwp || ia_connect_viewer_is_admin($me))
    ) || (
      ($me_phpbb > 0) && ($cphpbb > 0) && ($me_phpbb === $cphpbb)
    );
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
      'can_edit' => $can_edit_comment,
    ];
  }

  wp_send_json_success(['post' => $payload, 'comments' => $comments]);
}



function ia_connect_ajax_comments_page(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'comments_page');

  $me_phpbb = $me > 0 ? ia_connect_user_phpbb_id($me) : 0;

  $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
  $offset  = isset($_POST['offset']) ? max(0, (int) $_POST['offset']) : 0;
  $limit   = isset($_POST['limit']) ? max(1, min(50, (int) $_POST['limit'])) : 15;
  if ($post_id <= 0) wp_send_json_error(['message'=>'Bad post.'], 400);

  global $wpdb;
  $comms = $wpdb->prefix . 'ia_connect_comments';

  // Fetch next batch of top-level comments.
  $tops = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $comms WHERE post_id=%d AND parent_comment_id=0 AND is_deleted=0 ORDER BY id ASC LIMIT %d, %d",
    $post_id, $offset, $limit
  ), ARRAY_A);

  $top_ids = [];
  $top_payload = [];
  foreach (($tops ?: []) as $c) {
    $cid = (int)$c['id'];
    $top_ids[] = $cid;
    $cwp = (int)($c['author_wp_id'] ?? 0);
    $u = $cwp > 0 ? get_userdata($cwp) : null;
    $cphpbb = (int)($c['author_phpbb_id'] ?? 0);
    $can_edit_comment = (
      ($me > 0) && ($me === $cwp || ia_connect_viewer_is_admin($me))
    ) || (
      ($me_phpbb > 0) && ($cphpbb > 0) && ($me_phpbb === $cphpbb)
    );
    $top_payload[] = [
      'id' => $cid,
      'post_id' => (int)$c['post_id'],
      'parent_comment_id' => 0,
      'author_wp_id' => $cwp,
      'author_phpbb_id' => $cphpbb,
      'author_username' => $u ? (string)$u->user_login : '',
      'author' => $u ? ($u->display_name ?: $u->user_login) : 'User',
      'author_avatar' => $cwp > 0 ? ia_connect_avatar_url($cwp, 48) : '',
      'body' => (string)$c['body'],
      'created_at' => (string)$c['created_at'],
      'can_edit' => $can_edit_comment,
    ];
  }

  // Fetch replies for those top-level comments.
  $replies_by_parent = [];
  if (!empty($top_ids)) {
    $placeholders = implode(',', array_fill(0, count($top_ids), '%d'));
    $sql = "SELECT * FROM $comms WHERE post_id=%d AND parent_comment_id IN ($placeholders) AND is_deleted=0 ORDER BY id ASC";
    $params = array_merge([$post_id], $top_ids);
    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
    foreach (($rows ?: []) as $r) {
      $pid = (int)$r['parent_comment_id'];
      if (!isset($replies_by_parent[$pid])) $replies_by_parent[$pid] = [];
      $cwp = (int)($r['author_wp_id'] ?? 0);
      $u = $cwp > 0 ? get_userdata($cwp) : null;
      $cphpbb = (int)($r['author_phpbb_id'] ?? 0);
      $can_edit_comment = (
        ($me > 0) && ($me === $cwp || ia_connect_viewer_is_admin($me))
      ) || (
        ($me_phpbb > 0) && ($cphpbb > 0) && ($me_phpbb === $cphpbb)
      );
      $replies_by_parent[$pid][] = [
        'id' => (int)$r['id'],
        'post_id' => (int)$r['post_id'],
        'parent_comment_id' => $pid,
        'author_wp_id' => $cwp,
        'author_phpbb_id' => $cphpbb,
        'author_username' => $u ? (string)$u->user_login : '',
        'author' => $u ? ($u->display_name ?: $u->user_login) : 'User',
        'author_avatar' => $cwp > 0 ? ia_connect_avatar_url($cwp, 48) : '',
        'body' => (string)$r['body'],
        'created_at' => (string)$r['created_at'],
        'can_edit' => $can_edit_comment,
      ];
    }
  }

  wp_send_json_success([
    'top' => $top_payload,
    'replies_by_parent' => $replies_by_parent,
    'next_offset' => $offset + count($top_payload),
  ]);
}

function ia_connect_ajax_follow_toggle(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'follow_toggle');
  $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
  $follow  = isset($_POST['follow']) ? (int) $_POST['follow'] : 0;
  if ($post_id <= 0) wp_send_json_error(['message'=>'Bad post.'], 400);
  ia_connect_follow_set($post_id, $me, ($follow === 1));
  wp_send_json_success(['post_id'=>$post_id,'following'=>ia_connect_is_following($post_id, $me)]);
}

function ia_connect_ajax_post_update(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'post_update');
  $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
  $title = isset($_POST['title']) ? sanitize_text_field((string) wp_unslash($_POST['title'])) : '';
  $body  = isset($_POST['body']) ? wp_kses_post((string) wp_unslash($_POST['body'])) : '';
  if ($post_id <= 0) wp_send_json_error(['message'=>'Bad post.'], 400);

  global $wpdb;
  $posts = $wpdb->prefix . 'ia_connect_posts';
  $p = $wpdb->get_row($wpdb->prepare("SELECT id, author_wp_id FROM $posts WHERE id=%d", $post_id), ARRAY_A);
  if (!$p) wp_send_json_error(['message'=>'Not found.'], 404);
  $author = (int)($p['author_wp_id'] ?? 0);
  if ($me !== $author && !current_user_can('manage_options')) {
    wp_send_json_error(['message'=>'Forbidden.'], 403);
  }
  $wpdb->update($posts, [
    'title'=>$title,
    'body'=>$body,
    'updated_at'=>current_time('mysql'),
  ], ['id'=>$post_id], ['%s','%s','%s'], ['%d']);

  wp_send_json_success(['post'=>ia_connect_build_post_payload($post_id)]);
}

function ia_connect_ajax_post_delete(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'post_delete');
  $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
  if ($post_id <= 0) wp_send_json_error(['message'=>'Bad post.'], 400);

  global $wpdb;
  $posts = $wpdb->prefix . 'ia_connect_posts';
  $p = $wpdb->get_row($wpdb->prepare("SELECT id, author_wp_id FROM $posts WHERE id=%d", $post_id), ARRAY_A);
  if (!$p) wp_send_json_error(['message'=>'Not found.'], 404);
  $author = (int)($p['author_wp_id'] ?? 0);
  if ($me !== $author && !current_user_can('manage_options')) {
    wp_send_json_error(['message'=>'Forbidden.'], 403);
  }

  $wpdb->update($posts, [
    'status'=>'trash',
    'updated_at'=>current_time('mysql'),
  ], ['id'=>$post_id], ['%s','%s'], ['%d']);

  wp_send_json_success(['deleted'=>true,'post_id'=>$post_id]);
}

function ia_connect_ajax_comment_update(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'comment_update');

  $comment_id = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;
  $body = isset($_POST['body']) ? wp_kses_post((string) wp_unslash($_POST['body'])) : '';
  $body = trim((string)$body);
  if ($comment_id <= 0) wp_send_json_error(['message' => 'Bad comment.'], 400);
  if ($body === '') wp_send_json_error(['message' => 'Empty body.'], 400);

  global $wpdb;
  $comms = $wpdb->prefix . 'ia_connect_comments';
  $row = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $comms WHERE id=%d",
    $comment_id
  ), ARRAY_A);
  if (!$row || (int)($row['id'] ?? 0) <= 0) wp_send_json_error(['message' => 'Not found.'], 404);
  if ((int)($row['is_deleted'] ?? 0) === 1) wp_send_json_error(['message' => 'Deleted.'], 410);

  $author = (int)($row['author_wp_id'] ?? 0);
  if ($me !== $author && !current_user_can('manage_options')) {
    wp_send_json_error(['message' => 'Forbidden.'], 403);
  }

  $now = current_time('mysql');
  $wpdb->update($comms, [
    'body' => $body,
    'updated_at' => $now,
  ], ['id' => $comment_id], ['%s','%s'], ['%d']);

  $u = $author > 0 ? get_userdata($author) : null;
  wp_send_json_success([
    'comment' => [
      'id' => (int)($row['id'] ?? 0),
      'post_id' => (int)($row['post_id'] ?? 0),
      'parent_comment_id' => (int)($row['parent_comment_id'] ?? 0),
      'author_wp_id' => $author,
      'author_phpbb_id' => (int)($row['author_phpbb_id'] ?? 0),
      'author_username' => $u ? (string)$u->user_login : '',
      'author' => $u ? (string)($u->display_name ?: $u->user_login) : 'User',
      'author_avatar' => $author > 0 ? ia_connect_avatar_url($author, 48) : '',
      'body' => (string)$body,
      'created_at' => (string)($row['created_at'] ?? ''),
      'updated_at' => $now,
    ]
  ]);
}

function ia_connect_ajax_comment_delete(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'comment_delete');

  $comment_id = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;
  if ($comment_id <= 0) wp_send_json_error(['message' => 'Bad comment.'], 400);

  global $wpdb;
  $comms = $wpdb->prefix . 'ia_connect_comments';
  $row = $wpdb->get_row($wpdb->prepare(
    "SELECT id, author_wp_id, is_deleted FROM $comms WHERE id=%d",
    $comment_id
  ), ARRAY_A);
  if (!$row || (int)($row['id'] ?? 0) <= 0) wp_send_json_error(['message' => 'Not found.'], 404);
  if ((int)($row['is_deleted'] ?? 0) === 1) wp_send_json_success(['deleted' => true, 'comment_id' => $comment_id]);

  $author = (int)($row['author_wp_id'] ?? 0);
  if ($me !== $author && !current_user_can('manage_options')) {
    wp_send_json_error(['message' => 'Forbidden.'], 403);
  }

  $wpdb->update($comms, [
    'is_deleted' => 1,
    'body' => '',
    'updated_at' => current_time('mysql'),
  ], ['id' => $comment_id], ['%d','%s','%s'], ['%d']);

  wp_send_json_success(['deleted' => true, 'comment_id' => $comment_id]);
}


function ia_connect_ajax_mention_suggest(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'mention_suggest');

  $q = isset($_POST['q']) ? trim((string) wp_unslash($_POST['q'])) : '';
  $q = sanitize_text_field($q);
  if ($q === '') wp_send_json_success(['results' => []]);

  $out = [];
  $seen = [];
  $q_lc = function_exists('mb_strtolower') ? mb_strtolower($q) : strtolower($q);

  $score_user = static function (string $username, string $display) use ($q_lc): int {
    $u = function_exists('mb_strtolower') ? mb_strtolower($username) : strtolower($username);
    $d = function_exists('mb_strtolower') ? mb_strtolower($display) : strtolower($display);
    $score = 0;

    if ($u === $q_lc) $score += 500;
    elseif (strpos($u, $q_lc) === 0) $score += 350;
    elseif (strpos($u, $q_lc) !== false) $score += 220;

    if ($d === $q_lc) $score += 450;
    elseif (strpos($d, $q_lc) === 0) $score += 300;
    elseif (strpos($d, $q_lc) !== false) $score += 180;

    if ($u !== '' && $d !== '' && $u === $d) $score -= 10;

    return $score;
  };

  $phpbb_db = ia_connect_phpbb_db();
  $phpbb_prefix = ia_connect_phpbb_prefix();
  if ($phpbb_db) {
    $users_tbl = $phpbb_prefix . 'users';
    $like = '%' . $phpbb_db->esc_like($q) . '%';
    $clean = function_exists('utf8_clean_string') ? utf8_clean_string($q) : strtolower($q);
    $clean_like = $clean !== '' ? ($phpbb_db->esc_like($clean) . '%') : '';
    $rows = $phpbb_db->get_results(
      $phpbb_db->prepare(
        "SELECT user_id, username FROM {$users_tbl} WHERE (username LIKE %s OR username_clean LIKE %s) AND user_type <> 2 ORDER BY CASE WHEN username_clean = %s THEN 0 WHEN username_clean LIKE %s THEN 1 WHEN username LIKE %s THEN 2 ELSE 3 END, username_clean ASC LIMIT 12",
        $like,
        $clean_like,
        $clean,
        $clean_like,
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

      if ($wp_id > 0 && $wp_id !== $me && !ia_connect_viewer_is_admin($me) && !ia_connect_user_profile_searchable($wp_id)) {
        continue;
      }

      $k = ($wp_id > 0) ? ('wp:' . (int)$wp_id) : ('phpbb:' . (int)$phpbb_id);
      if (isset($seen[$k])) continue;
      $seen[$k] = 1;

      $out[] = [
        'wp_user_id'    => (int)$wp_id,
        'username'      => $uname,
        'display'       => $display,
        'phpbb_user_id' => $phpbb_id,
        'avatarUrl'     => $wp_id > 0 ? ia_connect_avatar_url($wp_id, 48) : '',
        '_score'        => $score_user($uname, $display),
      ];
    }
  }

  // Also search WP shadow users so full display-name queries still resolve.
  $query = new WP_User_Query([
    'search'         => '*' . $q . '*',
    'search_columns' => ['user_login', 'user_nicename', 'display_name'],
    'number'         => 12,
    'fields'         => ['ID', 'user_login', 'display_name'],
  ]);

  foreach ($query->get_results() as $u) {
    $uid = (int) $u->ID;
    if ($uid <= 0) continue;

    if ($uid !== $me && !ia_connect_viewer_is_admin($me) && !ia_connect_user_profile_searchable($uid)) {
      continue;
    }

    $phpbb = (int) get_user_meta($uid, 'ia_phpbb_user_id', true);
    if ($phpbb <= 0) $phpbb = (int) get_user_meta($uid, 'phpbb_user_id', true);

    $k = 'wp:' . (int)$uid;
    if (isset($seen[$k])) continue;
    $seen[$k] = 1;

    $display = (string) ($u->display_name ?: $u->user_login);
    $out[] = [
      'wp_user_id'    => $uid,
      'username'      => (string) $u->user_login,
      'display'       => $display,
      'phpbb_user_id' => (int) $phpbb,
      'avatarUrl'     => ia_connect_avatar_url($uid, 48),
      '_score'        => $score_user((string) $u->user_login, $display),
    ];
  }

  usort($out, static function (array $a, array $b): int {
    $sa = (int)($a['_score'] ?? 0);
    $sb = (int)($b['_score'] ?? 0);
    if ($sa !== $sb) return $sb <=> $sa;

    $ua = (string)($a['username'] ?? '');
    $ub = (string)($b['username'] ?? '');
    return strcasecmp($ua, $ub);
  });

  $out = array_slice(array_map(static function (array $row): array {
    unset($row['_score']);
    return $row;
  }, $out), 0, 8);

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

function ia_connect_search_excerpt(string $text, string $q, int $radius = 90): string {
  $text = trim(wp_strip_all_tags($text));
  $text = preg_replace('/\s+/u', ' ', $text);
  if ($text === '') return '';
  $q = trim($q);
  if ($q === '') {
    return mb_strlen($text) > ($radius * 2) ? mb_substr($text, 0, $radius * 2) . '…' : $text;
  }
  $pos = mb_stripos($text, $q);
  if ($pos === false) {
    return mb_strlen($text) > ($radius * 2) ? mb_substr($text, 0, $radius * 2) . '…' : $text;
  }
  $start = max(0, $pos - $radius);
  $len = mb_strlen($q) + ($radius * 2);
  $snippet = mb_substr($text, $start, $len);
  if ($start > 0) $snippet = '…' . ltrim($snippet);
  if (($start + $len) < mb_strlen($text)) $snippet = rtrim($snippet) . '…';
  return $snippet;
}


function ia_connect_ajax_wall_search(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'wall_search');

  $q = isset($_POST['q']) ? trim((string) wp_unslash($_POST['q'])) : '';
  $q = sanitize_text_field($q);
  if ($q === '') wp_send_json_success(['posts' => [], 'comments' => []]);

  $me_phpbb = ia_connect_user_phpbb_id($me);

  global $wpdb;
  $posts = $wpdb->prefix . 'ia_connect_posts';
  $comms = $wpdb->prefix . 'ia_connect_comments';

  $like = '%' . $wpdb->esc_like($q) . '%';

  $prows = $wpdb->get_results($wpdb->prepare("SELECT id, body FROM $posts WHERE status='publish' AND (title LIKE %s OR body LIKE %s) ORDER BY id DESC LIMIT 10", $like, $like), ARRAY_A);
  $outp = [];
  foreach ($prows as $prow) {
    $payload = ia_connect_build_post_payload((int)($prow['id'] ?? 0));
    if (empty($payload)) continue;
    $payload['snippet'] = ia_connect_search_excerpt((string)($prow['body'] ?? ''), $q);
    $outp[] = $payload;
  }

  $crows = $wpdb->get_results($wpdb->prepare("SELECT id, post_id, author_wp_id, body, created_at FROM $comms WHERE is_deleted=0 AND body LIKE %s ORDER BY id DESC LIMIT 10", $like), ARRAY_A);
  $outc = [];
  $blocked_commenters = $me_phpbb > 0 ? array_fill_keys(ia_user_rel_blocked_ids_for($me_phpbb), true) : [];
  foreach ($crows as $c) {
    $awp = (int)$c['author_wp_id'];
    $author_phpbb = $awp > 0 ? ia_connect_user_phpbb_id($awp) : 0;
    if ($author_phpbb > 0 && !empty($blocked_commenters[$author_phpbb])) continue;
    $u = $awp>0 ? get_userdata($awp) : null;
    $outc[] = [
      'id' => (int)$c['id'],
      'post_id' => (int)$c['post_id'],
      'author' => $u ? ($u->display_name ?: $u->user_login) : 'User',
      'author_avatar' => $awp>0 ? ia_connect_avatar_url($awp, 48) : '',
      'body' => (string)$c['body'],
      'snippet' => ia_connect_search_excerpt((string)$c['body'], $q),
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


function ia_connect_ajax_display_name_update(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'display_name_update');

  $dn = isset($_POST['display_name']) ? trim((string) wp_unslash($_POST['display_name'])) : '';
  $dn = sanitize_text_field($dn);

  // Blank = revert to username (default).
  if ($dn === '') {
    $u = get_userdata($me);
    $fallback = $u ? (string)($u->user_login ?: '') : '';
    if ($fallback === '') wp_send_json_error(['message' => 'Unable to resolve username.'], 500);
    $res = wp_update_user([
      'ID' => $me,
      'display_name' => $fallback,
    ]);
    if (is_wp_error($res)) wp_send_json_error(['message' => $res->get_error_message()], 400);
    wp_send_json_success(['display' => $fallback]);
  }

  // Display name. Allow spaces; keep characters safe for UI + search.
  // Blank is handled above.
  if (!preg_match('/^[a-zA-Z0-9_\-\. ]{2,40}$/', $dn)) {
    wp_send_json_error(['message' => 'Invalid display name. Use 2–40 chars: letters, numbers, spaces, underscore, dash, dot.'], 400);
  }

  $res = wp_update_user([
    'ID' => $me,
    'display_name' => $dn,
  ]);
  if (is_wp_error($res)) wp_send_json_error(['message' => $res->get_error_message()], 400);

  wp_send_json_success(['display' => $dn]);
}


function ia_connect_ajax_home_tab_update(): void {
  if (!is_user_logged_in()) wp_send_json_error(['message' => 'Login required.'], 401);
  ia_connect_ajax_check_nonce('nonce', 'home_tab_update');

  $wp_user_id = get_current_user_id();
  $tab = isset($_POST['home_tab']) ? (string) wp_unslash($_POST['home_tab']) : 'connect';
  $saved = ia_connect_set_user_home_tab((int) $wp_user_id, $tab);

  wp_send_json_success([
    'home_tab' => $saved,
  ]);
}

function ia_connect_ajax_style_update(): void {
  if (!is_user_logged_in()) wp_send_json_error(['message' => 'Login required.'], 401);
  ia_connect_ajax_check_nonce('nonce', 'style_update');

  $wp_user_id = get_current_user_id();
  $style = isset($_POST['style']) ? (string) wp_unslash($_POST['style']) : 'default';
  $saved = ia_connect_set_user_style((int) $wp_user_id, $style);

  wp_send_json_success([
    'style' => $saved,
  ]);
}

function ia_connect_ajax_signature_update(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'signature_update');

  $sig = isset($_POST['signature']) ? (string) wp_unslash($_POST['signature']) : '';
  $sig = sanitize_textarea_field($sig);
  $sig = trim($sig);
  if (strlen($sig) > 500) {
    $sig = substr($sig, 0, 500);
  }

  $show = isset($_POST['show_discuss']) ? (int) $_POST['show_discuss'] : 0;
  $show = ($show === 1) ? 1 : 0;

  update_user_meta($me, IA_CONNECT_META_SIGNATURE, $sig);
  update_user_meta($me, IA_CONNECT_META_SIGNATURE_SHOW_DISCUSS, $show);

  wp_send_json_success([
    'signature' => $sig,
    'show_discuss' => $show,
  ]);
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
  // Delegate lifecycle enforcement to IA Goodbye when available.
  if (class_exists('IA_Goodbye')) {
    $gb = IA_Goodbye::instance();
    $res = $gb->deactivate_account((int)$wp_user_id);
    if (empty($res['ok'])) {
      wp_send_json_error(['message' => $res['message'] ?? 'Deactivation failed.'], 500);
    }
  } else {
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
  }

  // Email
  if (function_exists('ia_mail_suite')) {
    try {
      $u = get_user_by('id', $wp_user_id);
      if ($u && $u->user_email) {
        if (function_exists('ia_notify_emails_enabled_for_wp') && !ia_notify_emails_enabled_for_wp((int)$wp_user_id)) {
          // user opted out of notification emails
        } else {
        ia_mail_suite()->send_template('ia_connect_account_deactivated', $u->user_email, [
          'site_name' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
          'display_name' => (string)($u->display_name ?: $u->user_login),
          'user_login' => (string)$u->user_login,
          'user_email' => (string)$u->user_email,
          'reactivate_url' => home_url('/?tab=connect'),
        ]);
        }
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

  try {
    // Delegate lifecycle enforcement to IA Goodbye when available.
    if (class_exists('IA_Goodbye')) {
      $gb = IA_Goodbye::instance();
      $res = $gb->delete_account((int)$wp_user_id, 'connect_delete');
      if (empty($res['ok'])) {
        wp_send_json_error(['message' => $res['message'] ?? 'Account delete failed.'], 500);
      }
    } else {
      if (!class_exists('IA_Auth') || !method_exists('IA_Auth','instance')) {
        wp_send_json_error(['message' => 'IA Auth not available.'], 500);
      }
      $ia = IA_Auth::instance();
      $r = $ia->phpbb->delete_user_preserve_posts($phpbb_user_id, 'deleted user');
      if (!is_array($r) || empty($r['ok'])) {
        wp_send_json_error(['message' => $r['message'] ?? 'phpBB delete failed.'], 500);
      }
      global $wpdb;
      $map_t = $wpdb->prefix . 'ia_identity_map';
      $tok_t = $wpdb->prefix . 'ia_peertube_user_tokens';
      $wpdb->delete($map_t, ['phpbb_user_id' => $phpbb_user_id]);
      $wpdb->delete($tok_t, ['phpbb_user_id' => $phpbb_user_id]);
      require_once ABSPATH . 'wp-admin/includes/user.php';
      wp_delete_user($wp_user_id);
    }

    // Also ensure current session is terminated.
    wp_logout();

    wp_send_json_success(['message' => 'Account deleted.']);
  } catch (Throwable $e) {
    error_log('[ia_connect_ajax_account_delete] ' . $e->getMessage());
    wp_send_json_error(['message' => 'Account delete failed.'], 500);
  }
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



/**
 * User relationship endpoints (follow/block), keyed by phpBB ids.
 */
function ia_connect_ajax_user_rel_status(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'user_search'); // reuse a stable nonce already on the page
  $target_phpbb = isset($_POST['target_phpbb']) ? (int) $_POST['target_phpbb'] : 0;
  $me_phpbb = ia_connect_user_phpbb_id($me);
  if ($target_phpbb <= 0 || $me_phpbb <= 0) wp_send_json_success(['following' => false, 'blocked_any' => false, 'blocked_by_me' => false]);
  wp_send_json_success([
    'following' => ia_user_rel_is_following($me_phpbb, $target_phpbb),
    'blocked_any' => ia_user_rel_is_blocked_any($me_phpbb, $target_phpbb),
    'blocked_by_me' => ia_user_rel_is_blocked_by_me($me_phpbb, $target_phpbb),
  ]);
}

function ia_connect_ajax_user_follow_toggle(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'user_search');
  $target_phpbb = isset($_POST['target_phpbb']) ? (int) $_POST['target_phpbb'] : 0;
  $me_phpbb = ia_connect_user_phpbb_id($me);
  if ($target_phpbb <= 0 || $me_phpbb <= 0) wp_send_json_error(['message' => 'Bad target'], 400);
  if (ia_user_rel_is_blocked_any($me_phpbb, $target_phpbb)) wp_send_json_error(['message' => 'Blocked'], 403);

  $following = ia_user_rel_toggle_follow($me_phpbb, $target_phpbb);
  wp_send_json_success(['following' => $following]);
}

function ia_connect_ajax_user_block_toggle(): void {
  $me = ia_connect_ajax_require_login();
  ia_connect_ajax_check_nonce('nonce', 'user_search');
  $target_phpbb = isset($_POST['target_phpbb']) ? (int) $_POST['target_phpbb'] : 0;
  $me_phpbb = ia_connect_user_phpbb_id($me);
  if ($target_phpbb <= 0 || $me_phpbb <= 0) wp_send_json_error(['message' => 'Bad target'], 400);

  $blocked_by_me = ia_user_rel_toggle_block($me_phpbb, $target_phpbb);
  wp_send_json_success([
    'blocked_by_me' => $blocked_by_me,
    'blocked_any' => ia_user_rel_is_blocked_any($me_phpbb, $target_phpbb),
  ]);
}