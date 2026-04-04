<?php
if (!defined('ABSPATH')) exit;

/**
 * Internal logger (kept intentionally simple).
 */
if (!function_exists('ia_discuss_log')) {
  function ia_discuss_log(string $msg): void {
    try {
      error_log('[ia-discuss] ' . $msg);
    } catch (Throwable $e) {}
  }
}

/**
 * Repair missing moderator_cache rows for agoras.
 *
 * Convention used across Atrium:
 * - A user "created" an Agora if they are a moderator for that forum.
 * - The marker is a row in phpBB's {prefix}moderator_cache.
 *
 * In a backup/injected phpBB dataset, it's possible to end up with forums
 * that exist but have no moderator_cache rows. This routine backfills those
 * rows using the earliest topic poster in the forum as a deterministic proxy.
 */
if (!function_exists('ia_discuss_repair_agora_moderator_cache')) {
  function ia_discuss_repair_agora_moderator_cache(IA_Discuss_Service_PhpBB $phpbb, int $max = 200): array {
    if (!$phpbb->is_ready()) return ['ok' => false, 'message' => 'phpbb adapter not available'];

    $db = $phpbb->db();
    $p  = $phpbb->prefix();
    if (!$db) return ['ok' => false, 'message' => 'no db'];

    $max = max(1, min(1000, (int)$max));

    $forums = $p . 'forums';
    $mods   = $p . 'moderator_cache';
    $topics = $p . 'topics';
    $users  = $p . 'users';

    // Find forums that look like agoras (forum_type=1 + parent_id=0) but have no moderator cache rows.
    $missing = $db->get_results(
      $db->prepare(
        "SELECT f.forum_id\n         FROM {$forums} f\n         LEFT JOIN {$mods} m ON m.forum_id = f.forum_id\n         WHERE f.forum_type = 1 AND f.parent_id = 0\n         GROUP BY f.forum_id\n         HAVING COUNT(m.forum_id) = 0\n         ORDER BY f.forum_id ASC\n         LIMIT %d",
        $max
      ),
      ARRAY_A
    );

    if (!empty($db->last_error)) {
      ia_discuss_log('Repair query failed: ' . $db->last_error);
      return ['ok' => false, 'message' => 'sql error', 'error' => $db->last_error];
    }

    $inserted = 0;
    $skipped  = 0;
    $details  = [];

    if (is_array($missing)) {
      foreach ($missing as $row) {
        $forum_id = (int)($row['forum_id'] ?? 0);
        if ($forum_id <= 0) { $skipped++; continue; }

        // Infer creator as earliest topic poster.
        $poster_id = (int)$db->get_var(
          $db->prepare(
            "SELECT topic_poster FROM {$topics} WHERE forum_id = %d ORDER BY topic_time ASC LIMIT 1",
            $forum_id
          )
        );

        if ($poster_id <= 0) {
          $skipped++;
          $details[] = ['forum_id' => $forum_id, 'status' => 'skipped', 'reason' => 'no topics'];
          continue;
        }

        $username = (string)$db->get_var(
          $db->prepare("SELECT username FROM {$users} WHERE user_id = %d LIMIT 1", $poster_id)
        );

        $ok = $db->insert(
          $mods,
          [
            'forum_id' => $forum_id,
            'user_id'  => $poster_id,
            'username' => $username ?: '',
            'group_id' => 0,
            'group_name' => '',
            'display_on_index' => 1,
          ],
          ['%d','%d','%s','%d','%s','%d']
        );

        if (!$ok || !empty($db->last_error)) {
          ia_discuss_log('Repair insert failed for forum_id=' . $forum_id . ': ' . $db->last_error);
          $skipped++;
          $details[] = ['forum_id' => $forum_id, 'status' => 'failed', 'error' => $db->last_error];
          // keep going
          continue;
        }

        $inserted++;
        $details[] = ['forum_id' => $forum_id, 'status' => 'inserted', 'user_id' => $poster_id];
      }
    }

    return [
      'ok' => true,
      'checked' => is_array($missing) ? count($missing) : 0,
      'inserted' => $inserted,
      'skipped' => $skipped,
      'details' => $details,
    ];
  }
}

function ia_discuss_is_atrium_page(): bool {
  // Mirrors the “detect [ia-atrium]” pattern used elsewhere.
  global $post;
  if ($post && isset($post->post_content) && has_shortcode($post->post_content, 'ia-atrium')) return true;
  return (bool) apply_filters('ia_discuss_should_enqueue', false);
}

function ia_discuss_clean_out_buffer(): void {
  if (ob_get_length()) { @ob_clean(); }
}

function ia_discuss_json_ok($data = []): void {
  ia_discuss_clean_out_buffer();
  nocache_headers();
  wp_send_json_success($data);
  wp_die();
}

function ia_discuss_json_err(string $message, int $status = 400, array $extra = []): void {
  ia_discuss_clean_out_buffer();
  nocache_headers();
  if ($status >= 500 && function_exists('error_log')) {
    error_log('[ia-discuss][json_err] status=' . $status . ' msg=' . $message);
  }
  wp_send_json_error(['message' => $message] + $extra, $status);
  wp_die();
}

// ---------- Connect avatar bridge ----------

/**
 * Map a phpBB user_id to a WP user ID via the shadow-account meta.
 *
 * The wider Atrium stack stores the canonical phpBB id on WP users as:
 *   usermeta(meta_key = 'ia_phpbb_user_id', meta_value = <phpbb_user_id>)
 */
function ia_discuss_wp_user_id_from_phpbb(int $phpbb_user_id): int {
  static $cache = [];
  $phpbb_user_id = (int)$phpbb_user_id;
  if ($phpbb_user_id <= 0) return 0;
  if (array_key_exists($phpbb_user_id, $cache)) return (int)$cache[$phpbb_user_id];

  // Best-effort: look up common shadow-account meta keys directly.
  // (Keeps behaviour compatible across different Atrium installs where the key name varied.)
  global $wpdb;
  if (isset($wpdb) && isset($wpdb->usermeta)) {
    $keys = ['ia_phpbb_user_id', 'phpbb_user_id', 'ia_phpbb_uid', 'phpbb_uid'];
    foreach ($keys as $k) {
      try {
        $uid = (int) $wpdb->get_var($wpdb->prepare(
          "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
          $k,
          (string) $phpbb_user_id
        ));
        if ($uid > 0) {
          $cache[$phpbb_user_id] = $uid;
          return $uid;
        }
      } catch (Throwable $e) {
        // ignore and continue
      }
    }

    // Canonical mapping tables used elsewhere in Atrium.
    $map = $wpdb->prefix . 'phpbb_user_map';
    try {
      $has_map = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $map));
      if ((string)$has_map === (string)$map) {
        $uid = (int) $wpdb->get_var($wpdb->prepare("SELECT wp_user_id FROM $map WHERE phpbb_user_id=%d LIMIT 1", $phpbb_user_id));
        if ($uid > 0) {
          $cache[$phpbb_user_id] = $uid;
          return $uid;
        }
      }
    } catch (Throwable $e) {
      // ignore
    }

    $imap = $wpdb->prefix . 'ia_identity_map';
    try {
      $has_imap = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $imap));
      if ((string)$has_imap === (string)$imap) {
        $uid = (int) $wpdb->get_var($wpdb->prepare("SELECT wp_user_id FROM $imap WHERE phpbb_user_id=%d LIMIT 1", $phpbb_user_id));
        if ($uid > 0) {
          $cache[$phpbb_user_id] = $uid;
          return $uid;
        }
      }
    } catch (Throwable $e) {
      // ignore
    }
  }

  if (!function_exists('get_users')) {
    $cache[$phpbb_user_id] = 0;
    return 0;
  }

  try {
    $users = get_users([
      'fields'     => 'ID',
      'number'     => 1,
      'meta_key'   => 'ia_phpbb_user_id',
      'meta_value' => (string)$phpbb_user_id,
    ]);
    $wp_id = 0;
    if (is_array($users) && !empty($users)) {
      $first = $users[0];
      $wp_id = is_numeric($first) ? (int)$first : (is_object($first) && isset($first->ID) ? (int)$first->ID : 0);
    }
    $cache[$phpbb_user_id] = $wp_id;
    return $wp_id;
  } catch (Throwable $e) {
    $cache[$phpbb_user_id] = 0;
    return 0;
  }
}


/**
 * Inverse map: WP user_id -> canonical phpbb_user_id (best-effort).
 */
function ia_discuss_phpbb_user_id_from_wp(int $wp_user_id): int {
  static $cache = [];
  $wp_user_id = (int)$wp_user_id;
  if ($wp_user_id <= 0) return 0;
  if (array_key_exists($wp_user_id, $cache)) return (int)$cache[$wp_user_id];

  $pid = (int) get_user_meta($wp_user_id, 'ia_phpbb_user_id', true);
  if ($pid <= 0) $pid = (int) get_user_meta($wp_user_id, 'phpbb_user_id', true);
  if ($pid > 0) { $cache[$wp_user_id] = $pid; return $pid; }

  global $wpdb;

  // Canonical mapping: wp_phpbb_user_map (Atrium schema)
  $map = $wpdb->prefix . 'phpbb_user_map';
  $has_map = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $map));
  if ($has_map) {
    $pid = (int)$wpdb->get_var($wpdb->prepare("SELECT phpbb_user_id FROM $map WHERE wp_user_id=%d LIMIT 1", $wp_user_id));
    if ($pid > 0) { $cache[$wp_user_id] = $pid; return $pid; }
  }

  // IA Auth identity map
  $imap = $wpdb->prefix . 'ia_identity_map';
  $has_imap = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $imap));
  if ($has_imap) {
    $pid = (int)$wpdb->get_var($wpdb->prepare("SELECT phpbb_user_id FROM $imap WHERE wp_user_id=%d LIMIT 1", $wp_user_id));
    if ($pid > 0) { $cache[$wp_user_id] = $pid; return $pid; }
  }

  $cache[$wp_user_id] = 0;
  return 0;
}

/**
 * Prefer WP display_name (user-set) when available, else fall back to phpBB username.
 */
function ia_discuss_display_name_from_phpbb(int $phpbb_user_id, string $fallback_username = ''): string {
  $phpbb_user_id = (int)$phpbb_user_id;
  $fallback_username = (string)$fallback_username;
  $wp_id = ia_discuss_wp_user_id_from_phpbb($phpbb_user_id);
  if ($wp_id > 0) {
    $u = get_userdata($wp_id);
    if ($u) {
      $dn = (string)($u->display_name ?: $u->user_login);
      if ($dn !== '') return $dn;
    }
  }
  return $fallback_username !== '' ? $fallback_username : ('user#' . $phpbb_user_id);
}


/**
 * Resolve a Connect profile photo (if available) for a phpBB user.
 * Falls back to WP avatar if Connect hasn't stored a custom photo.
 */
function ia_discuss_avatar_url_from_phpbb(int $phpbb_user_id, int $size = 48): string {
  $wp_id = ia_discuss_wp_user_id_from_phpbb((int)$phpbb_user_id);
  if ($wp_id <= 0) return '';

  // Prefer the Connect helper if it exists.
  if (function_exists('ia_connect_avatar_url')) {
    try {
      $u = (string) ia_connect_avatar_url($wp_id, (int)$size);
      return $u;
    } catch (Throwable $e) {
      // fall through
    }
  }

  // Otherwise read the same meta key Connect uses.
  $meta = '';
  if (function_exists('get_user_meta')) {
    $meta = (string) get_user_meta($wp_id, 'ia_connect_profile_photo', true);
  }
  if ($meta !== '') return $meta;

  if (function_exists('get_avatar_url')) {
    return (string) get_avatar_url($wp_id, ['size' => (int)$size]);
  }
  return '';
}


/**
 * Get a formatted signature (bio) for a phpBB user, if enabled by the user.
 *
 * Stored by IA Connect as WP user meta. We keep this lookup resilient so Discuss
 * can still render even if Connect is inactive.
 */
function ia_discuss_signature_html_from_phpbb(int $phpbb_user_id): string {
  $phpbb_user_id = (int) $phpbb_user_id;
  if ($phpbb_user_id <= 0) return '';

  $wp_id = ia_discuss_wp_user_id_from_phpbb($phpbb_user_id);
  if ($wp_id <= 0) return '';

  $show = (int) get_user_meta($wp_id, 'ia_connect_signature_show_discuss', true);
  if ($show !== 1) return '';

  $sig = (string) get_user_meta($wp_id, 'ia_connect_signature', true);
  $sig = trim($sig);
  if ($sig === '') return '';

  return nl2br(esc_html($sig));
}


// Cross-platform user relationships (follow/block). Guarded to avoid redeclare if another IA plugin loaded it.
if (!function_exists('ia_user_rel_table')) {
  function ia_user_rel_table(): string { global $wpdb; return $wpdb->prefix . 'ia_user_relations'; }
}
if (!function_exists('ia_user_rel_ensure_table')) {
  function ia_user_rel_ensure_table(): void {
    global $wpdb;
    $t = ia_user_rel_table();
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
    if ((string)$exists === (string)$t) return;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$t} (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      rel_type VARCHAR(20) NOT NULL,
      src_phpbb_id BIGINT(20) UNSIGNED NOT NULL,
      dst_phpbb_id BIGINT(20) UNSIGNED NOT NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_rel (rel_type, src_phpbb_id, dst_phpbb_id),
      KEY src (src_phpbb_id, rel_type),
      KEY dst (dst_phpbb_id, rel_type)
    ) {$charset};";
    dbDelta($sql);
  }
}
if (!function_exists('ia_user_rel_is_following')) {
  function ia_user_rel_is_following(int $src_phpbb, int $dst_phpbb): bool {
    $src_phpbb=(int)$src_phpbb; $dst_phpbb=(int)$dst_phpbb;
    if ($src_phpbb<=0||$dst_phpbb<=0||$src_phpbb===$dst_phpbb) return false;
    ia_user_rel_ensure_table(); global $wpdb; $t=ia_user_rel_table();
    $v=$wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$t} WHERE rel_type='follow' AND src_phpbb_id=%d AND dst_phpbb_id=%d LIMIT 1",$src_phpbb,$dst_phpbb));
    return (string)$v==='1';
  }
}
if (!function_exists('ia_user_rel_toggle_follow')) {
  function ia_user_rel_toggle_follow(int $src_phpbb, int $dst_phpbb): bool {
    $src_phpbb=(int)$src_phpbb; $dst_phpbb=(int)$dst_phpbb;
    if ($src_phpbb<=0||$dst_phpbb<=0||$src_phpbb===$dst_phpbb) return false;
    ia_user_rel_ensure_table(); global $wpdb; $t=ia_user_rel_table();
    if (ia_user_rel_is_following($src_phpbb,$dst_phpbb)) { $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE rel_type='follow' AND src_phpbb_id=%d AND dst_phpbb_id=%d",$src_phpbb,$dst_phpbb)); return false; }
    $now=current_time('mysql');
    $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$t}(rel_type,src_phpbb_id,dst_phpbb_id,created_at) VALUES('follow',%d,%d,%s)",$src_phpbb,$dst_phpbb,$now));

    /**
     * Signal: a follow relationship was created.
     * Used by ia-mail-suite now, and ia-notifications later.
     */
    do_action('ia_user_follow_created', $src_phpbb, $dst_phpbb, ['source' => 'discuss']);

    return true;
  }
}
if (!function_exists('ia_user_rel_is_blocked_any')) {
  function ia_user_rel_is_blocked_any(int $a_phpbb, int $b_phpbb): bool {
    $a_phpbb=(int)$a_phpbb; $b_phpbb=(int)$b_phpbb;
    if ($a_phpbb<=0||$b_phpbb<=0||$a_phpbb===$b_phpbb) return false;
    ia_user_rel_ensure_table(); global $wpdb; $t=ia_user_rel_table();
    $v=$wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$t} WHERE rel_type='block' AND ((src_phpbb_id=%d AND dst_phpbb_id=%d) OR (src_phpbb_id=%d AND dst_phpbb_id=%d)) LIMIT 1",$a_phpbb,$b_phpbb,$b_phpbb,$a_phpbb));
    return (string)$v==='1';
  }
}
if (!function_exists('ia_user_rel_is_blocked_by_me')) {
  function ia_user_rel_is_blocked_by_me(int $me_phpbb, int $other_phpbb): bool {
    $me_phpbb=(int)$me_phpbb; $other_phpbb=(int)$other_phpbb;
    if ($me_phpbb<=0||$other_phpbb<=0||$me_phpbb===$other_phpbb) return false;
    ia_user_rel_ensure_table(); global $wpdb; $t=ia_user_rel_table();
    $v=$wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$t} WHERE rel_type='block' AND src_phpbb_id=%d AND dst_phpbb_id=%d LIMIT 1",$me_phpbb,$other_phpbb));
    return (string)$v==='1';
  }
}
if (!function_exists('ia_user_rel_toggle_block')) {
  function ia_user_rel_toggle_block(int $src_phpbb, int $dst_phpbb): bool {
    $src_phpbb=(int)$src_phpbb; $dst_phpbb=(int)$dst_phpbb;
    if ($src_phpbb<=0||$dst_phpbb<=0||$src_phpbb===$dst_phpbb) return false;
    ia_user_rel_ensure_table(); global $wpdb; $t=ia_user_rel_table();
    if (ia_user_rel_is_blocked_by_me($src_phpbb,$dst_phpbb)) { $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE rel_type='block' AND src_phpbb_id=%d AND dst_phpbb_id=%d",$src_phpbb,$dst_phpbb)); return false; }
    $now=current_time('mysql'); $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$t}(rel_type,src_phpbb_id,dst_phpbb_id,created_at) VALUES('block',%d,%d,%s)",$src_phpbb,$dst_phpbb,$now)); return true;
  }
}

if (!function_exists('ia_user_rel_blocked_ids_for')) {
  function ia_user_rel_blocked_ids_for(int $me_phpbb): array {
    $me_phpbb=(int)$me_phpbb; if ($me_phpbb<=0) return [];
    ia_user_rel_ensure_table(); global $wpdb; $t=ia_user_rel_table();
    $rows=$wpdb->get_results($wpdb->prepare("SELECT src_phpbb_id,dst_phpbb_id FROM {$t} WHERE rel_type='block' AND (src_phpbb_id=%d OR dst_phpbb_id=%d)",$me_phpbb,$me_phpbb), ARRAY_A) ?: [];
    $out=[];
    foreach($rows as $r){
      $a=(int)($r['src_phpbb_id']??0); $b=(int)($r['dst_phpbb_id']??0);
      if ($a===$me_phpbb && $b>0) $out[$b]=true;
      if ($b===$me_phpbb && $a>0) $out[$a]=true;
    }
    return array_map('intval', array_keys($out));
  }
}


if (!function_exists('ia_discuss_topic_history_meta_key')) {
  function ia_discuss_topic_history_meta_key(): string {
    return 'ia_discuss_topic_history';
  }
}

if (!function_exists('ia_discuss_topic_history_record')) {
  function ia_discuss_topic_history_record(int $wp_user_id, int $topic_id, int $visited_at = 0): void {
    $wp_user_id = (int)$wp_user_id;
    $topic_id = (int)$topic_id;
    $visited_at = $visited_at > 0 ? (int)$visited_at : time();
    if ($wp_user_id <= 0 || $topic_id <= 0) return;

    $history = get_user_meta($wp_user_id, ia_discuss_topic_history_meta_key(), true);
    if (!is_array($history)) $history = [];

    $history[(string)$topic_id] = $visited_at;
    arsort($history, SORT_NUMERIC);

    if (count($history) > 500) {
      $history = array_slice($history, 0, 500, true);
    }

    update_user_meta($wp_user_id, ia_discuss_topic_history_meta_key(), $history);
  }
}

if (!function_exists('ia_discuss_topic_history_ids')) {
  function ia_discuss_topic_history_ids(int $wp_user_id, int $offset = 0, int $limit = 50): array {
    $wp_user_id = (int)$wp_user_id;
    $offset = max(0, (int)$offset);
    $limit = max(1, min(500, (int)$limit));
    if ($wp_user_id <= 0) return [];

    $history = get_user_meta($wp_user_id, ia_discuss_topic_history_meta_key(), true);
    if (!is_array($history) || empty($history)) return [];

    arsort($history, SORT_NUMERIC);
    $ids = [];
    foreach ($history as $topic_id => $visited_at) {
      $topic_id = (int)$topic_id;
      if ($topic_id > 0) $ids[] = $topic_id;
    }

    if (!$ids) return [];
    return array_slice($ids, $offset, $limit);
  }
}


function ia_discuss_current_page_context(): array {
  $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : '';
  $ia_tab = isset($_GET['ia_tab']) ? sanitize_key((string) wp_unslash($_GET['ia_tab'])) : '';
  $is_discuss = ($tab === 'discuss' || $ia_tab === 'discuss');
  if (!$is_discuss) {
    return ['is_discuss' => false];
  }

  $view = isset($_GET['iad_view']) ? sanitize_key((string) wp_unslash($_GET['iad_view'])) : '';
  $topic_id = isset($_GET['iad_topic']) ? (int) $_GET['iad_topic'] : 0;
  $forum_id = isset($_GET['iad_forum']) ? (int) $_GET['iad_forum'] : 0;
  $order = isset($_GET['iad_order']) ? sanitize_key((string) wp_unslash($_GET['iad_order'])) : '';

  if ($topic_id > 0 && $view === '') {
    $view = 'topic';
  }

  return [
    'is_discuss' => true,
    'view' => $view,
    'topic_id' => $topic_id,
    'forum_id' => $forum_id,
    'order' => $order,
  ];
}

function ia_discuss_site_title(): string {
  return 'IndieAgora';
}

function ia_discuss_resolve_page_title(): string {
  $ctx = ia_discuss_current_page_context();
  if (empty($ctx['is_discuss'])) return '';

  $site = ia_discuss_site_title();
  $view = (string) ($ctx['view'] ?? '');
  $topic_id = (int) ($ctx['topic_id'] ?? 0);
  $forum_id = (int) ($ctx['forum_id'] ?? 0);
  $order = sanitize_key((string) ($ctx['order'] ?? ''));

  $title = 'Discuss';

  if ($view === 'topic' && $topic_id > 0 && class_exists('IA_Discuss_Service_PhpBB')) {
    try {
      $phpbb = new IA_Discuss_Service_PhpBB();
      if ($phpbb->is_ready()) {
        $row = $phpbb->get_topic_row($topic_id);
        $topic_title = trim((string) ($row['topic_title'] ?? ''));
        if ($topic_title !== '') {
          $title = wp_strip_all_tags($topic_title);
        }
      }
    } catch (Throwable $e) {
      // Keep the title fallback narrow and non-fatal.
    }
    if ($title === 'Discuss') {
      $title = 'Topic';
    }
  } elseif ($view === 'new') {
    $title = 'Latest Posts';
  } elseif ($view === 'replies') {
    $title = 'Latest Replies';
  } elseif ($view === 'noreplies') {
    $title = '0 Replies';
  } elseif ($view === 'mytopics') {
    $title = 'My Topics';
  } elseif ($view === 'myreplies') {
    $title = 'My Replies';
  } elseif ($view === 'myhistory') {
    $title = 'My History';
  } elseif ($view === 'agoras') {
    $title = 'Agoras';
  } elseif ($view === 'agora') {
    $forum_title = 'Agora';
    if ($forum_id > 0 && class_exists('IA_Discuss_Service_PhpBB')) {
      try {
        $phpbb = new IA_Discuss_Service_PhpBB();
        if ($phpbb->is_ready()) {
          $row = $phpbb->get_forum_row($forum_id);
          $forum_name = trim((string) ($row['forum_name'] ?? ''));
          if ($forum_name !== '') {
            $forum_title = wp_strip_all_tags($forum_name);
          }
        }
      } catch (Throwable $e) {
        $forum_title = 'Agora';
      }
    }
    $sort_title = 'Latest Posts';
    if ($order === 'most_replies') {
      $sort_title = 'Most Replies';
    } elseif ($order === 'least_replies') {
      $sort_title = 'Least Replies';
    } elseif ($order === 'oldest') {
      $sort_title = 'Oldest Posts';
    } elseif ($order === 'created') {
      $sort_title = 'Date Created';
    }
    $title = $forum_title . ' | ' . $sort_title;
  } elseif ($view === 'search') {
    $title = 'Search';
  } elseif ($view === 'moderation') {
    $title = 'Moderation';
  }

  $title = trim(wp_strip_all_tags((string) $title));
  if ($title === '') return $site;
  if ($site === '') return $title;
  return $title . ' | ' . $site;
}

function ia_discuss_filter_document_title(string $title): string {
  $resolved = ia_discuss_resolve_page_title();
  return ($resolved !== '') ? $resolved : $title;
}

function ia_discuss_print_meta_tags(): void {
  $resolved = ia_discuss_resolve_page_title();
  if ($resolved === '') return;
  echo "\n" . '<meta property="og:title" content="' . esc_attr($resolved) . '" />' . "\n";
  echo '<meta name="twitter:title" content="' . esc_attr($resolved) . '" />' . "\n";
}

function ia_discuss_meta_boot(): void {
  add_filter('pre_get_document_title', 'ia_discuss_filter_document_title', 99);
  add_action('wp_head', 'ia_discuss_print_meta_tags', 1);
}
