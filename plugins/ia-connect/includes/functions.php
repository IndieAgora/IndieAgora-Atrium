<?php
if (!defined('ABSPATH')) exit;

function ia_connect_require(string $rel): void {
  $path = IA_CONNECT_PATH . ltrim($rel, '/');
  if (file_exists($path)) require_once $path;
}

function ia_connect_atrium_present(): bool {
  // Atrium typically injects a wrapper and uses ?tab=... routing.
  // We just ensure we're on the front-end.
  return !is_admin();
}

function ia_connect_nonce(string $action): string {
  return wp_create_nonce('ia_connect:' . $action);
}

function ia_connect_verify_nonce(string $action, string $nonce): bool {
  return (bool) wp_verify_nonce($nonce, 'ia_connect:' . $action);
}

function ia_connect_upload_dir(): array {
  $u = wp_upload_dir();
  $base = trailingslashit($u['basedir']) . IA_CONNECT_UPLOAD_SUBDIR;
  $baseurl = trailingslashit($u['baseurl']) . IA_CONNECT_UPLOAD_SUBDIR;
  return [$base, $baseurl];
}

function ia_connect_user_phpbb_id(int $wp_user_id): int {
  // Prefer a canonical mapping if your stack provides it.
  // Fallback: store phpbb id in user meta 'ia_phpbb_user_id' if present.
  $mapped = (int) get_user_meta($wp_user_id, 'ia_phpbb_user_id', true);
  if ($mapped > 0) return $mapped;
  // Some installs keep phpbb id in 'phpbb_user_id'
  $mapped = (int) get_user_meta($wp_user_id, 'phpbb_user_id', true);
  if ($mapped > 0) return $mapped;


  // Canonical mapping: wp_phpbb_user_map (Atrium schema)
  global $wpdb;
  $map = $wpdb->prefix . 'phpbb_user_map';
  $has_map = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $map));
  if ($has_map) {
    $pid = (int)$wpdb->get_var($wpdb->prepare("SELECT phpbb_user_id FROM $map WHERE wp_user_id=%d LIMIT 1", $wp_user_id));
    if ($pid > 0) return $pid;
  }

  // If IA Auth is present, prefer its identity map (wp_ia_identity_map).
  if (class_exists('IA_Auth') && method_exists('IA_Auth', 'instance')) {
    try {
      $ia = IA_Auth::instance();
      if (is_object($ia) && isset($ia->db) && is_object($ia->db) && method_exists($ia->db, 'get_identity_by_wp_user_id')) {
        $row = $ia->db->get_identity_by_wp_user_id($wp_user_id);
        $pid = (int)($row['phpbb_user_id'] ?? 0);
        if ($pid > 0) return $pid;
      }
    } catch (Throwable $e) {
      // ignore
    }
  }

  // Direct fallback: identity map table (if it exists).
  $t = $wpdb->prefix . 'ia_identity_map';
  $has = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
  if ($has) {
    $pid = (int)$wpdb->get_var($wpdb->prepare("SELECT phpbb_user_id FROM $t WHERE wp_user_id=%d LIMIT 1", $wp_user_id));
    if ($pid > 0) return $pid;
  }
  return 0;
}

/**
 * Get a wpdb connection to the phpBB database using IA Engine credentials.
 *
 * IA Engine exposes phpbb_db() as an array of credentials (host/port/name/user/password/prefix).
 * This plugin creates a dedicated wpdb connection on-demand for read-only lookups.
 */
function ia_connect_phpbb_db(): ?wpdb {
  static $conn = null;
  static $attempted = false;
  if ($attempted) return $conn;
  $attempted = true;

  if (!class_exists('IA_Engine') || !method_exists('IA_Engine', 'phpbb_db')) {
    return null;
  }

  $c = IA_Engine::phpbb_db();
  $name = (string)($c['name'] ?? '');
  $user = (string)($c['user'] ?? '');
  $pass = (string)($c['pass'] ?? '');
  $host = (string)($c['host'] ?? 'localhost');
  $port = (int)($c['port'] ?? 3306);

  if ($name === '' || $user === '') return null;

  // wpdb expects host as host:port when using a non-default port.
  $dbhost = $host;
  if ($port && $port !== 3306) $dbhost = $host . ':' . $port;

  // Create a separate connection.
  $conn = new wpdb($user, $pass, $name, $dbhost);
  $conn->set_prefix('');
  // Silence connection errors; callers should fall back.
  $conn->show_errors(false);
  $conn->suppress_errors(true);

  return $conn;
}

function ia_connect_phpbb_prefix(): string {
  static $cached = null;
  if (is_string($cached) && $cached !== '') return $cached;

  $p = 'phpbb_';
  if (class_exists('IA_Engine') && method_exists('IA_Engine', 'phpbb_db')) {
    $c = IA_Engine::phpbb_db();
    $tmp = (string)($c['prefix'] ?? 'phpbb_');
    if ($tmp !== '') $p = $tmp;
  }

  // Validate prefix against actual tables; if wrong, auto-detect.
  $db = ia_connect_phpbb_db();
  if (!$db) { $cached = $p; return $cached; }

  $users_tbl = $p . 'users';
  $ok = $db->get_var("SHOW TABLES LIKE '" . esc_sql($users_tbl) . "'");
  if ($ok) { $cached = $p; return $cached; }

  // Auto-detect by looking for *users table.
  $candidates = $db->get_col("SHOW TABLES LIKE '%users'");
  if ($candidates) {
    // Prefer tables that end exactly with 'users' and contain 'phpbb'.
    $best = '';
    foreach ($candidates as $t) {
      $t = (string)$t;
      if (!preg_match('/users$/', $t)) continue;
      if ($best === '') $best = $t;
      if (stripos($t, 'phpbb') !== false) { $best = $t; break; }
    }
    if ($best !== '' && preg_match('/users$/', $best)) {
      $cached = substr($best, 0, -strlen('users'));
      return $cached;
    }
  }

  $cached = $p;
  return $cached;
}

/**
 * Connect avatar URL (prefers IA Connect profile photo stored in user meta).
 */
function ia_connect_avatar_url(int $wp_user_id, int $size = 64): string {
  if ($wp_user_id <= 0) return '';
  $u = (string) get_user_meta($wp_user_id, IA_CONNECT_META_PROFILE, true);
  if ($u !== '') return $u;
  return (string) get_avatar_url($wp_user_id, ['size' => $size]);
}

function ia_connect_normalize_home_tab($tab): string {
  $tab = sanitize_key((string) $tab);
  if (!in_array($tab, ['connect', 'discuss', 'stream'], true)) {
    $tab = 'connect';
  }
  return $tab;
}

function ia_connect_get_user_home_tab(int $wp_user_id): string {
  if ($wp_user_id <= 0) return 'connect';
  $raw = (string) get_user_meta($wp_user_id, IA_CONNECT_META_HOME_TAB, true);
  return ia_connect_normalize_home_tab($raw ?: 'connect');
}

function ia_connect_set_user_home_tab(int $wp_user_id, $tab): string {
  $tab = ia_connect_normalize_home_tab($tab);
  if ($wp_user_id > 0) {
    update_user_meta($wp_user_id, IA_CONNECT_META_HOME_TAB, $tab);
  }
  return $tab;
}

function ia_connect_normalize_style($style): string {
  $style = sanitize_key((string) $style);
  if (!in_array($style, ['default', 'black', 'calm', 'dawn', 'earth', 'flame', 'leaf', 'night', 'sun', 'twilight', 'water'], true)) {
    $style = 'default';
  }
  return $style;
}

function ia_connect_get_user_style(int $wp_user_id): string {
  if ($wp_user_id <= 0) return 'default';
  $raw = (string) get_user_meta($wp_user_id, IA_CONNECT_META_STYLE, true);
  return ia_connect_normalize_style($raw ?: 'default');
}

function ia_connect_set_user_style(int $wp_user_id, $style): string {
  $style = ia_connect_normalize_style($style);
  if ($wp_user_id > 0) {
    update_user_meta($wp_user_id, IA_CONNECT_META_STYLE, $style);
  }
  return $style;
}

function ia_connect_get_settings(): array {
  $s = get_option(IA_CONNECT_OPT_SETTINGS);
  if (!is_array($s)) $s = [];
  $s = array_merge([
    'show_buddypress_activity' => false,
  ], $s);
  return $s;
}

function ia_connect_set_settings(array $settings): void {
  $curr = ia_connect_get_settings();
  $merged = array_merge($curr, $settings);
  update_option(IA_CONNECT_OPT_SETTINGS, $merged, false);
}



// ---------- Privacy (profile-level) ----------

function ia_connect_viewer_is_admin(int $viewer_wp_user_id = 0): bool {
  // Admin bypass only: "manage_options" (site admin).
  return current_user_can('manage_options');
}

function ia_connect_privacy_defaults(): array {
  // Defaults should be ON/YES.
  return [
    'discuss_visible' => 1,
    'stream_visible'  => 1,
    'searchable'      => 1,
    'seo'             => 1,
    'allow_messages'  => 1,
  ];
}

function ia_connect_get_user_privacy(int $target_wp_user_id): array {
  $raw = get_user_meta($target_wp_user_id, IA_CONNECT_META_PRIVACY, true);
  $defaults = ia_connect_privacy_defaults();

  $arr = [];
  if (is_array($raw)) {
    $arr = $raw;
  } elseif (is_string($raw) && $raw !== '') {
    $j = json_decode($raw, true);
    if (is_array($j)) $arr = $j;
  }

  // Legacy bug fix: if a profile has all-off privacy (from a bad default) and has never been touched,
  // treat as defaults-on and repair the stored meta.
  $touched = (int) get_user_meta($target_wp_user_id, 'ia_connect_privacy_touched', true);
  $all_off = true;
  if (!empty($arr)) {
    foreach ($defaults as $k => $v) {
      if (!array_key_exists($k, $arr) || (int)!!$arr[$k] === 1) { $all_off = false; break; }
    }
  } else {
    $all_off = false;
  }
  if ($touched === 0 && $all_off) {
    update_user_meta($target_wp_user_id, IA_CONNECT_META_PRIVACY, $defaults);
    $arr = $defaults;
  }

  $out = $defaults;
  foreach ($defaults as $k => $v) {
    if (array_key_exists($k, $arr)) {
      $out[$k] = (int) !!$arr[$k];
    }
  }
  return $out;
}

function ia_connect_update_user_privacy(int $target_wp_user_id, array $new): array {
  $cur = ia_connect_get_user_privacy($target_wp_user_id);
  $defaults = ia_connect_privacy_defaults();
  foreach ($defaults as $k => $v) {
    if (array_key_exists($k, $new)) {
      $cur[$k] = (int) !!$new[$k];
    }
  }
  update_user_meta($target_wp_user_id, IA_CONNECT_META_PRIVACY, $cur);
  update_user_meta($target_wp_user_id, 'ia_connect_privacy_touched', 1);
  return $cur;
}

function ia_connect_user_profile_searchable(int $target_wp_user_id): bool {
  $p = ia_connect_get_user_privacy($target_wp_user_id);
  return !empty($p['searchable']);
}

function ia_connect_user_discuss_visible(int $target_wp_user_id): bool {
  $p = ia_connect_get_user_privacy($target_wp_user_id);
  return !empty($p['discuss_visible']);
}

function ia_connect_user_stream_visible(int $target_wp_user_id): bool {
  $p = ia_connect_get_user_privacy($target_wp_user_id);
  return !empty($p['stream_visible']);
}

function ia_connect_user_seo_visible(int $target_wp_user_id): bool {
  $p = ia_connect_get_user_privacy($target_wp_user_id);
  return !empty($p['seo']);
}

function ia_connect_user_allows_messages(int $target_wp_user_id): bool {
  $p = ia_connect_get_user_privacy($target_wp_user_id);
  return !empty($p['allow_messages']);
}

// ---------- PeerTube (read-only DB for profile activity) ----------

function ia_connect_peertube_pdo(): ?PDO {
  static $pdo = null;
  static $attempted = false;
  if ($attempted) return $pdo;
  $attempted = true;

  if (!class_exists('IA_Engine') || !method_exists('IA_Engine', 'peertube_db')) return null;
  $c = IA_Engine::peertube_db();

  $host = (string)($c['host'] ?? 'localhost');
  $port = (int)($c['port'] ?? 5432);
  $name = (string)($c['name'] ?? '');
  $user = (string)($c['user'] ?? '');
  $pass = (string)($c['pass'] ?? '');

  if ($name === '' || $user === '') return null;

  try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$name}";
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  } catch (Throwable $e) {
    $pdo = null;
  }

  return $pdo;
}

function ia_connect_peertube_public_url(): string {
  if (class_exists('IA_Engine') && method_exists('IA_Engine', 'peertube_api')) {
    $a = IA_Engine::peertube_api();
    $u = (string)($a['public_url'] ?? '');
    $u = trim($u);
    if ($u !== '') {
      // Normalise the base URL. Misconfigured values here tend to create broken links like
      // "stream.example.comhttps" when concatenated.
      // - ensure a scheme exists
      // - strip accidental trailing "http"/"https" fragments
      // - remove trailing slash
      $u = preg_replace('/(https?)$/i', '', $u);
      $u = rtrim($u);
      if (!preg_match('~^https?://~i', $u)) {
        $u = (is_ssl() ? 'https://' : 'http://') . ltrim($u, '/');
      }
      return rtrim($u, '/');
    }
  }
  return '';
}

/**
 * Join a base URL and a URL/path from PeerTube safely.
 * - If $maybe_url is already absolute, return it.
 * - If it's a relative path ("/w/.."), prefix with base.
 */
function ia_connect_join_url(string $base, string $maybe_url): string {
  $maybe_url = trim($maybe_url);
  if ($maybe_url === '') return '';
  if (preg_match('~^https?://~i', $maybe_url)) return $maybe_url;
  if (strpos($maybe_url, '//') === 0) {
    $scheme = is_ssl() ? 'https:' : 'http:';
    return $scheme . $maybe_url;
  }

  $base = trim($base);
  if ($base === '') return $maybe_url;
  $base = rtrim($base, '/');
  if (!preg_match('~^https?://~i', $base)) {
    $base = (is_ssl() ? 'https://' : 'http://') . ltrim($base, '/');
  }

  if ($maybe_url[0] !== '/') $maybe_url = '/' . $maybe_url;
  return $base . $maybe_url;
}



// --- Post follows (for email notifications) ---
function ia_connect_follow_set(int $post_id, int $wp_user_id, bool $follow): void {
  if ($post_id <= 0 || $wp_user_id <= 0) return;
  global $wpdb;
  $tbl = $wpdb->prefix . 'ia_connect_follows';
  $now = current_time('mysql');
  if ($follow) {
    $wpdb->query($wpdb->prepare(
      "INSERT INTO $tbl (post_id, follower_wp_id, follower_phpbb_id, created_at)
       VALUES (%d,%d,%d,%s)
       ON DUPLICATE KEY UPDATE created_at=VALUES(created_at)",
      $post_id, $wp_user_id, ia_connect_user_phpbb_id($wp_user_id), $now
    ));
  } else {
    $wpdb->delete($tbl, ['post_id'=>$post_id, 'follower_wp_id'=>$wp_user_id], ['%d','%d']);
  }
}

function ia_connect_is_following(int $post_id, int $wp_user_id): bool {
  if ($post_id <= 0 || $wp_user_id <= 0) return false;
  global $wpdb;
  $tbl = $wpdb->prefix . 'ia_connect_follows';
  $x = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT 1 FROM $tbl WHERE post_id=%d AND follower_wp_id=%d LIMIT 1",
    $post_id, $wp_user_id
  ));
  return $x === 1;
}

function ia_connect_followers_wp_ids(int $post_id): array {
  if ($post_id <= 0) return [];
  global $wpdb;
  $tbl = $wpdb->prefix . 'ia_connect_follows';
  $ids = $wpdb->get_col($wpdb->prepare(
    "SELECT follower_wp_id FROM $tbl WHERE post_id=%d",
    $post_id
  ));
  return array_values(array_unique(array_filter(array_map('intval', $ids ?: []))));
}


/**
 * Cross-platform user relationships (follow/block) keyed by phpBB user ids.
 *
 * Table: {$wpdb->prefix}ia_user_relations
 * rel_type: 'follow' | 'block'
 *
 * IMPORTANT: These helpers are shared across IA plugins. They MUST be guarded
 * to avoid fatal "Cannot redeclare" errors when multiple plugins load them.
 */
if (!function_exists('ia_user_rel_table')) {
  function ia_user_rel_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'ia_user_relations';
  }
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
    $src_phpbb = (int)$src_phpbb; $dst_phpbb = (int)$dst_phpbb;
    if ($src_phpbb <= 0 || $dst_phpbb <= 0 || $src_phpbb === $dst_phpbb) return false;

    ia_user_rel_ensure_table();
    global $wpdb; $t = ia_user_rel_table();
    $v = $wpdb->get_var($wpdb->prepare(
      "SELECT 1 FROM {$t} WHERE rel_type='follow' AND src_phpbb_id=%d AND dst_phpbb_id=%d LIMIT 1",
      $src_phpbb, $dst_phpbb
    ));
    return (string)$v === '1';
  }
}

if (!function_exists('ia_user_rel_toggle_follow')) {
  function ia_user_rel_toggle_follow(int $src_phpbb, int $dst_phpbb): bool {
    $src_phpbb = (int)$src_phpbb; $dst_phpbb = (int)$dst_phpbb;
    if ($src_phpbb <= 0 || $dst_phpbb <= 0 || $src_phpbb === $dst_phpbb) return false;

    ia_user_rel_ensure_table();
    global $wpdb; $t = ia_user_rel_table();

    if (ia_user_rel_is_following($src_phpbb, $dst_phpbb)) {
      $wpdb->query($wpdb->prepare(
        "DELETE FROM {$t} WHERE rel_type='follow' AND src_phpbb_id=%d AND dst_phpbb_id=%d",
        $src_phpbb, $dst_phpbb
      ));
      return false;
    }

    $now = current_time('mysql');
    $wpdb->query($wpdb->prepare(
      "INSERT IGNORE INTO {$t}(rel_type,src_phpbb_id,dst_phpbb_id,created_at) VALUES('follow',%d,%d,%s)",
      $src_phpbb, $dst_phpbb, $now
    ));

    // Signal: a follow relationship was created (used by ia-mail-suite now, ia-notifications later).
    do_action('ia_user_follow_created', $src_phpbb, $dst_phpbb, ['source' => 'connect']);

    return true;
  }
}

if (!function_exists('ia_user_rel_is_blocked_any')) {
  function ia_user_rel_is_blocked_any(int $a_phpbb, int $b_phpbb): bool {
    $a_phpbb = (int)$a_phpbb; $b_phpbb = (int)$b_phpbb;
    if ($a_phpbb <= 0 || $b_phpbb <= 0 || $a_phpbb === $b_phpbb) return false;

    ia_user_rel_ensure_table();
    global $wpdb; $t = ia_user_rel_table();
    $v = $wpdb->get_var($wpdb->prepare(
      "SELECT 1 FROM {$t} WHERE rel_type='block' AND ((src_phpbb_id=%d AND dst_phpbb_id=%d) OR (src_phpbb_id=%d AND dst_phpbb_id=%d)) LIMIT 1",
      $a_phpbb, $b_phpbb, $b_phpbb, $a_phpbb
    ));
    return (string)$v === '1';
  }
}

if (!function_exists('ia_user_rel_is_blocked_by_me')) {
  function ia_user_rel_is_blocked_by_me(int $me_phpbb, int $other_phpbb): bool {
    $me_phpbb = (int)$me_phpbb; $other_phpbb = (int)$other_phpbb;
    if ($me_phpbb <= 0 || $other_phpbb <= 0 || $me_phpbb === $other_phpbb) return false;

    ia_user_rel_ensure_table();
    global $wpdb; $t = ia_user_rel_table();
    $v = $wpdb->get_var($wpdb->prepare(
      "SELECT 1 FROM {$t} WHERE rel_type='block' AND src_phpbb_id=%d AND dst_phpbb_id=%d LIMIT 1",
      $me_phpbb, $other_phpbb
    ));
    return (string)$v === '1';
  }
}

if (!function_exists('ia_user_rel_toggle_block')) {
  function ia_user_rel_toggle_block(int $src_phpbb, int $dst_phpbb): bool {
    $src_phpbb = (int)$src_phpbb; $dst_phpbb = (int)$dst_phpbb;
    if ($src_phpbb <= 0 || $dst_phpbb <= 0 || $src_phpbb === $dst_phpbb) return false;

    ia_user_rel_ensure_table();
    global $wpdb; $t = ia_user_rel_table();

    if (ia_user_rel_is_blocked_by_me($src_phpbb, $dst_phpbb)) {
      $wpdb->query($wpdb->prepare(
        "DELETE FROM {$t} WHERE rel_type='block' AND src_phpbb_id=%d AND dst_phpbb_id=%d",
        $src_phpbb, $dst_phpbb
      ));
      return false;
    }

    $now = current_time('mysql');
    $wpdb->query($wpdb->prepare(
      "INSERT IGNORE INTO {$t}(rel_type,src_phpbb_id,dst_phpbb_id,created_at) VALUES('block',%d,%d,%s)",
      $src_phpbb, $dst_phpbb, $now
    ));
    do_action('ia_user_block_created', $src_phpbb, $dst_phpbb, ['source' => 'connect']);
    return true;
  }
}

if (!function_exists('ia_user_rel_blocked_ids_for')) {
  function ia_user_rel_blocked_ids_for(int $me_phpbb): array {
    $me_phpbb = (int)$me_phpbb;
    if ($me_phpbb <= 0) return [];

    ia_user_rel_ensure_table();
    global $wpdb; $t = ia_user_rel_table();
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT src_phpbb_id,dst_phpbb_id FROM {$t} WHERE rel_type='block' AND (src_phpbb_id=%d OR dst_phpbb_id=%d)",
      $me_phpbb, $me_phpbb
    ));
    if (!$rows) return [];

    $ids = [];
    foreach ($rows as $r) {
      $a = (int)$r->src_phpbb_id; $b = (int)$r->dst_phpbb_id;
      $ids[] = ($a === $me_phpbb) ? $b : $a;
    }
    return array_values(array_unique(array_filter($ids)));
  }
}



function ia_connect_current_page_context(): array {
  $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : '';
  if ($tab !== 'connect') {
    return ['is_connect' => false];
  }

  $phpbb_id = isset($_GET['ia_profile']) ? (int) $_GET['ia_profile'] : 0;
  $profile_name = isset($_GET['ia_profile_name']) ? sanitize_user((string) wp_unslash($_GET['ia_profile_name']), true) : '';
  $post_id = isset($_GET['ia_post']) ? (int) $_GET['ia_post'] : 0;
  $comment_id = isset($_GET['ia_comment']) ? (int) $_GET['ia_comment'] : 0;

  return [
    'is_connect' => true,
    'phpbb_id' => $phpbb_id,
    'profile_name' => $profile_name,
    'post_id' => $post_id,
    'comment_id' => $comment_id,
  ];
}

function ia_connect_resolve_profile_wp_user_id(int $phpbb_id, string $profile_name = ''): int {
  if ($phpbb_id > 0) {
    $users = get_users([
      'number' => 1,
      'fields' => 'ids',
      'meta_query' => [
        'relation' => 'OR',
        [ 'key' => 'ia_phpbb_user_id', 'value' => $phpbb_id, 'compare' => '=' ],
        [ 'key' => 'phpbb_user_id', 'value' => $phpbb_id, 'compare' => '=' ],
      ],
    ]);
    if (!empty($users[0])) {
      return (int) $users[0];
    }
  }

  if ($profile_name !== '') {
    $user = get_user_by('login', $profile_name);
    if (!($user instanceof WP_User)) {
      $user = get_user_by('slug', $profile_name);
    }
    if ($user instanceof WP_User) {
      return (int) $user->ID;
    }
  }

  return 0;
}

function ia_connect_resolve_discuss_share_title_from_ref(string $shared_ref): string {
  $shared_ref = trim($shared_ref);
  if ($shared_ref === '' || !class_exists('IA_Discuss_Service_PhpBB')) return '';

  $parts = explode(':', $shared_ref);
  $topic_id = isset($parts[0]) ? (int) $parts[0] : 0;
  if ($topic_id <= 0) return '';

  try {
    $phpbb = new IA_Discuss_Service_PhpBB();
    if (!$phpbb->is_ready()) return '';
    $row = $phpbb->get_topic_row($topic_id);
    $topic_title = trim((string) ($row['topic_title'] ?? ''));
    return $topic_title !== '' ? wp_strip_all_tags($topic_title) : '';
  } catch (Throwable $e) {
    return '';
  }
}

function ia_connect_site_title(): string {
  return 'IndieAgora';
}

function ia_connect_resolve_page_title(): string {
  $ctx = ia_connect_current_page_context();
  if (empty($ctx['is_connect'])) return '';

  $site = ia_connect_site_title();
  $title = 'Connect';
  $phpbb_id = (int) ($ctx['phpbb_id'] ?? 0);
  $profile_name = (string) ($ctx['profile_name'] ?? '');
  $post_id = (int) ($ctx['post_id'] ?? 0);
  $comment_id = (int) ($ctx['comment_id'] ?? 0);

  $target_wp = ia_connect_resolve_profile_wp_user_id($phpbb_id, $profile_name);
  if ($target_wp > 0) {
    $user = get_userdata($target_wp);
    if ($user instanceof WP_User) {
      $display = trim((string) ($user->display_name ?: $user->user_login));
      if ($display !== '') {
        $title = $display;
      }
    }
  } elseif ($profile_name !== '') {
    $title = $profile_name;
  }

  if ($post_id > 0) {
    global $wpdb;
    $posts = $wpdb->prefix . 'ia_connect_posts';
    $table_ok = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $posts));
    if ($table_ok) {
      $row = $wpdb->get_row($wpdb->prepare("SELECT title, body, shared_tab, shared_ref FROM {$posts} WHERE id = %d LIMIT 1", $post_id), ARRAY_A);
      if (is_array($row)) {
        $post_title = trim((string) ($row['title'] ?? ''));
        if ($post_title === '') {
          $shared_tab = trim((string) ($row['shared_tab'] ?? ''));
          $shared_ref = trim((string) ($row['shared_ref'] ?? ''));
          if ($shared_tab === 'discuss' && $shared_ref !== '') {
            $post_title = ia_connect_resolve_discuss_share_title_from_ref($shared_ref);
          }
        }
        if ($post_title === '') {
          $body = trim(wp_strip_all_tags((string) ($row['body'] ?? '')));
          if ($body !== '') {
            $post_title = function_exists('mb_substr') ? mb_substr($body, 0, 80) : substr($body, 0, 80);
          }
        }
        if ($post_title !== '') {
          $title = $post_title;
        } elseif ($comment_id > 0) {
          $title = 'Comment';
        } else {
          $title = 'Post';
        }
      }
    }
  }

  $title = trim(wp_strip_all_tags((string) $title));
  if ($title === '') return $site;
  if ($site === '') return $title;
  return $title . ' | ' . $site;
}

function ia_connect_filter_document_title(string $title): string {
  $resolved = ia_connect_resolve_page_title();
  return ($resolved !== '') ? $resolved : $title;
}

function ia_connect_print_meta_tags(): void {
  $resolved = ia_connect_resolve_page_title();
  if ($resolved === '') return;
  echo "\n" . '<meta property="og:title" content="' . esc_attr($resolved) . '" />' . "\n";
  echo '<meta name="twitter:title" content="' . esc_attr($resolved) . '" />' . "\n";
}

function ia_connect_meta_boot(): void {
  add_filter('pre_get_document_title', 'ia_connect_filter_document_title', 99);
  add_action('wp_head', 'ia_connect_print_meta_tags', 1);
}
