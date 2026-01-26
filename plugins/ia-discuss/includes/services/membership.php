<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Discuss — Agora memberships + per-Agora notification preferences + cover photos.
 *
 * Discuss is phpBB-backed, but memberships and notification prefs are Atrium UX concerns.
 * We store them in WP-owned tables keyed by canonical phpBB user_id.
 */
final class IA_Discuss_Service_Membership {

  /** @var IA_Discuss_Service_PhpBB */
  private $phpbb;

  /** @var IA_Discuss_Service_Auth */
  private $auth;

  public function __construct(IA_Discuss_Service_PhpBB $phpbb, IA_Discuss_Service_Auth $auth) {
    $this->phpbb = $phpbb;
    $this->auth  = $auth;
  }

  public function boot(): void {
    $this->ensure_tables();
    $this->ensure_cron();
  }

  // -------------------------
  // Storage
  // -------------------------

  private function t_members(): string {
    global $wpdb;
    return $wpdb->prefix . 'ia_discuss_agora_members';
  }

  private function t_covers(): string {
    global $wpdb;
    return $wpdb->prefix . 'ia_discuss_agora_covers';
  }

  public function ensure_tables(): void {
    global $wpdb;
    if (!$wpdb) return;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    $members = $this->t_members();
    $sql1 = "CREATE TABLE {$members} (
      phpbb_user_id BIGINT(20) UNSIGNED NOT NULL,
      forum_id BIGINT(20) UNSIGNED NOT NULL,
      joined_at INT(11) UNSIGNED NOT NULL DEFAULT 0,
      last_interaction INT(11) UNSIGNED NOT NULL DEFAULT 0,
      notify_agora TINYINT(1) NOT NULL DEFAULT 0,
      PRIMARY KEY  (phpbb_user_id, forum_id),
      KEY forum_id (forum_id),
      KEY last_interaction (last_interaction)
    ) {$charset};";
    dbDelta($sql1);

    $covers = $this->t_covers();
    $sql2 = "CREATE TABLE {$covers} (
      forum_id BIGINT(20) UNSIGNED NOT NULL,
      cover_url TEXT NOT NULL,
      updated_at INT(11) UNSIGNED NOT NULL DEFAULT 0,
      updated_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY (forum_id)
    ) {$charset};";
    dbDelta($sql2);
  }

  // -------------------------
  // Membership
  // -------------------------

  public function current_user_phpbb_id(): int {
    return (int) $this->auth->current_phpbb_user_id();
  }

  public function is_joined(int $phpbb_user_id, int $forum_id): bool {
    $phpbb_user_id = (int)$phpbb_user_id;
    $forum_id = (int)$forum_id;
    if ($phpbb_user_id <= 0 || $forum_id <= 0) return false;
    global $wpdb;
    if (!$wpdb) return false;
    $t = $this->t_members();
    $v = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$t} WHERE phpbb_user_id=%d AND forum_id=%d LIMIT 1", $phpbb_user_id, $forum_id));
    return (string)$v === '1';
  }

  public function join(int $phpbb_user_id, int $forum_id): void {
    $phpbb_user_id = (int)$phpbb_user_id;
    $forum_id = (int)$forum_id;
    if ($phpbb_user_id <= 0 || $forum_id <= 0) return;

    global $wpdb;
    if (!$wpdb) return;
    $t = $this->t_members();
    $now = time();
    $wpdb->query($wpdb->prepare(
      "INSERT INTO {$t} (phpbb_user_id, forum_id, joined_at, last_interaction, notify_agora)
       VALUES (%d,%d,%d,%d,0)
       ON DUPLICATE KEY UPDATE last_interaction=VALUES(last_interaction)",
      $phpbb_user_id, $forum_id, $now, $now
    ));
  }

  public function leave(int $phpbb_user_id, int $forum_id): void {
    $phpbb_user_id = (int)$phpbb_user_id;
    $forum_id = (int)$forum_id;
    if ($phpbb_user_id <= 0 || $forum_id <= 0) return;
    global $wpdb;
    if (!$wpdb) return;
    $t = $this->t_members();
    $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE phpbb_user_id=%d AND forum_id=%d", $phpbb_user_id, $forum_id));
  }

  public function touch(int $phpbb_user_id, int $forum_id): void {
    $phpbb_user_id = (int)$phpbb_user_id;
    $forum_id = (int)$forum_id;
    if ($phpbb_user_id <= 0 || $forum_id <= 0) return;
    global $wpdb;
    if (!$wpdb) return;
    $t = $this->t_members();
    $now = time();
    $wpdb->query($wpdb->prepare(
      "INSERT INTO {$t} (phpbb_user_id, forum_id, joined_at, last_interaction, notify_agora)
       VALUES (%d,%d,0,%d,0)
       ON DUPLICATE KEY UPDATE last_interaction=VALUES(last_interaction)",
      $phpbb_user_id, $forum_id, $now
    ));
  }

  public function get_notify_agora(int $phpbb_user_id, int $forum_id): int {
    $phpbb_user_id = (int)$phpbb_user_id;
    $forum_id = (int)$forum_id;
    if ($phpbb_user_id <= 0 || $forum_id <= 0) return 0;
    global $wpdb;
    if (!$wpdb) return 0;
    $t = $this->t_members();
    $v = $wpdb->get_var($wpdb->prepare("SELECT notify_agora FROM {$t} WHERE phpbb_user_id=%d AND forum_id=%d LIMIT 1", $phpbb_user_id, $forum_id));
    return ((int)$v) ? 1 : 0;
  }

  public function set_notify_agora(int $phpbb_user_id, int $forum_id, bool $enabled): void {
    $phpbb_user_id = (int)$phpbb_user_id;
    $forum_id = (int)$forum_id;
    if ($phpbb_user_id <= 0 || $forum_id <= 0) return;
    global $wpdb;
    if (!$wpdb) return;
    $t = $this->t_members();
    $now = time();
    $wpdb->query($wpdb->prepare(
      "INSERT INTO {$t} (phpbb_user_id, forum_id, joined_at, last_interaction, notify_agora)
       VALUES (%d,%d,0,%d,%d)
       ON DUPLICATE KEY UPDATE notify_agora=VALUES(notify_agora), last_interaction=VALUES(last_interaction)",
      $phpbb_user_id, $forum_id, $now, $enabled ? 1 : 0
    ));
  }

  public function cover_url(int $forum_id): string {
    $forum_id = (int)$forum_id;
    if ($forum_id <= 0) return '';
    global $wpdb;
    if (!$wpdb) return '';
    $t = $this->t_covers();
    $url = (string) $wpdb->get_var($wpdb->prepare("SELECT cover_url FROM {$t} WHERE forum_id=%d LIMIT 1", $forum_id));
    return $url;
  }

  public function set_cover_url(int $forum_id, string $cover_url, int $actor_phpbb_user_id = 0): void {
    $forum_id = (int)$forum_id;
    $cover_url = trim((string)$cover_url);
    if ($forum_id <= 0) return;

    // Accept empty to clear.
    if ($cover_url !== '' && !preg_match('~^https?://~i', $cover_url)) return;

    global $wpdb;
    if (!$wpdb) return;
    $t = $this->t_covers();
    if ($cover_url === '') {
      $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE forum_id=%d", $forum_id));
      return;
    }
    $wpdb->query($wpdb->prepare(
      "INSERT INTO {$t} (forum_id, cover_url, updated_at, updated_by)
       VALUES (%d,%s,%d,%d)
       ON DUPLICATE KEY UPDATE cover_url=VALUES(cover_url), updated_at=VALUES(updated_at), updated_by=VALUES(updated_by)",
      $forum_id, $cover_url, time(), (int)$actor_phpbb_user_id
    ));
  }

  // -------------------------
  // Cron: 28-day inactivity ping
  // -------------------------

  private function ensure_cron(): void {
    if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) return;
    if (!wp_next_scheduled('ia_discuss_agora_inactivity_tick')) {
      // Run daily; the handler will do the date math.
      wp_schedule_event(time() + 120, 'daily', 'ia_discuss_agora_inactivity_tick');
    }
  }

  /**
   * Process inactivity pings.
   *
   * NOTE: called by WP-Cron.
   */
  public function cron_inactivity_tick(IA_Discuss_Service_Notify $notify): void {
    global $wpdb;
    if (!$wpdb) return;
    if (!$this->phpbb->is_ready()) return;

    $t = $this->t_members();
    $cutoff = time() - (28 * 86400);

    // Only members who are still joined.
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT phpbb_user_id, forum_id, last_interaction FROM {$t} WHERE last_interaction > 0 AND last_interaction < %d",
      $cutoff
    ), ARRAY_A);
    if (!$rows || !is_array($rows)) return;

    foreach ($rows as $r) {
      $uid = (int)($r['phpbb_user_id'] ?? 0);
      $fid = (int)($r['forum_id'] ?? 0);
      if ($uid <= 0 || $fid <= 0) continue;

      // Find most active topic in this forum within the last 30 days.
      $topic_id = $this->most_active_topic_last_month($fid);
      if ($topic_id <= 0) continue;

      do_action('ia_discuss_agora_inactivity_ping', $uid, $fid, $topic_id);

      // Send best-effort email even if notifications are disabled.
      $notify->send_agora_inactivity_popular($uid, $fid, $topic_id);

      // Touch so we don't spam daily.
      $this->touch($uid, $fid);
    }
  }

  private function most_active_topic_last_month(int $forum_id): int {
    $forum_id = (int)$forum_id;
    if ($forum_id <= 0) return 0;
    $db = $this->phpbb->db();
    if (!$db) return 0;
    $posts = $this->phpbb->table('posts');
    $since = time() - (30 * 86400);
    $sql = "SELECT topic_id, COUNT(*) AS c
            FROM {$posts}
            WHERE forum_id=%d AND post_visibility=1 AND post_time >= %d
            GROUP BY topic_id
            ORDER BY c DESC
            LIMIT 1";
    $row = $db->get_row($db->prepare($sql, $forum_id, $since), ARRAY_A);
    return (int)($row['topic_id'] ?? 0);
  }
}
