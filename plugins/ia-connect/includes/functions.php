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

