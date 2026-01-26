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

  private function table_members(): string {
    global $wpdb;
    return $wpdb->prefix . 'ia_discuss_agora_members';
  }

  private function table_covers(): string {
    global $wpdb;
    return $wpdb->prefix . 'ia_discuss_agora_covers';
  }

  public function ensure_tables(): void {
    global $wpdb;
    if (!$wpdb) return;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $m = $this->table_members();
    $sqlM = "CREATE TABLE {$m} (
      phpbb_user_id BIGINT(20) UNSIGNED NOT NULL,
      forum_id BIGINT(20) UNSIGNED NOT NULL,
      joined_at INT(11) UNSIGNED NOT NULL DEFAULT 0,
      last_interaction INT(11) UNSIGNED NOT NULL DEFAULT 0,
      notify_agora TINYINT(1) NOT NULL DEFAULT 0,
      PRIMARY KEY (phpbb_user_id, forum_id),
      KEY forum_id (forum_id),
      KEY last_interaction (last_interaction)
    ) {$charset};";
    dbDelta($sqlM);

    $c = $this->table_covers();
    $sqlC = "CREATE TABLE {$c} (
      forum_id BIGINT(20) UNSIGNED NOT NULL,
      cover_url TEXT NOT NULL,
      updated_at INT(11) UNSIGNED NOT NULL DEFAULT 0,
      updated_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY (forum_id)
    ) {$charset};";
    dbDelta($sqlC);
  }

  // --------------------
  // Membership API
  // --------------------

  public function current_phpbb_user_id(): int {
    return (int) $this->auth->current_phpbb_user_id();
  }

  public function is_joined(int $phpbb_user_id, int $forum_id): bool {
    if ($phpbb_user_id <= 0 || $forum_id <= 0) return false;
    global $wpdb;
    $t = $this->table_members();
    $v = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$t} WHERE phpbb_user_id=%d AND forum_id=%d LIMIT 1", $phpbb_user_id, $forum_id));
    return (string)$v === '1';
  }

  public function join(int $phpbb_user_id, int $forum_id): void {
    if ($phpbb_user_id <= 0 || $forum_id <= 0) return;
    global $wpdb;
    $t = $this->table_members();
    $now = time();
    $wpdb->query($wpdb->prepare(
      "INSERT INTO {$t} (phpbb_user_id, forum_id, joined_at, last_interaction, notify_agora)
       VALUES (%d,%d,%d,%d,0)
       ON DUPLICATE KEY UPDATE last_interaction=GREATEST(last_interaction, VALUES(last_interaction))",
      $phpbb_user_id, $forum_id, $now, $now
    ));
  }

  public function leave(int $phpbb_user_id, int $forum_id): void {
    if ($phpbb_user_id <= 0 || $forum_id <= 0) return;
    global $wpdb;
    $t = $this->table_members();
    $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE phpbb_user_id=%d AND forum_id=%d", $phpbb_user_id, $forum_id));
  }

  public function set_notify(int $phpbb_user_id, int $forum_id, bool $enabled): void {
    if ($phpbb_user_id <= 0 || $forum_id <= 0) return;
    global $wpdb;
    $t = $this->table_members();
    $now = time();
    // Ensure membership row exists; if user toggles bell first, treat it as join.
    $wpdb->query($wpdb->prepare(
      "INSERT INTO {$t} (phpbb_user_id, forum_id, joined_at, last_interaction, notify_agora)
       VALUES (%d,%d,%d,%d,%d)
       ON DUPLICATE KEY UPDATE notify_agora=VALUES(notify_agora), last_interaction=GREATEST(last_interaction, VALUES(last_interaction))",
      $phpbb_user_id, $forum_id, $now, $now, $enabled ? 1 : 0
    ));
  }

  public function get_notify(int $phpbb_user_id, int $forum_id): int {
    if ($phpbb_user_id <= 0 || $forum_id <= 0) return 0;
    global $wpdb;
    $t = $this->table_members();
    $v = $wpdb->get_var($wpdb->prepare("SELECT notify_agora FROM {$t} WHERE phpbb_user_id=%d AND forum_id=%d LIMIT 1", $phpbb_user_id, $forum_id));
    return (int)($v ?: 0);
  }

  public function touch_interaction(int $phpbb_user_id, int $forum_id): void {
    if ($phpbb_user_id <= 0 || $forum_id <= 0) return;
    global $wpdb;
    $t = $this->table_members();
    $now = time();
    // Update if member; if not member, no-op.
    $wpdb->query($wpdb->prepare(
      "UPDATE {$t} SET last_interaction=%d WHERE phpbb_user_id=%d AND forum_id=%d",
      $now, $phpbb_user_id, $forum_id
    ));
  }

  public function remove_membership_forum_user(int $forum_id, int $phpbb_user_id): void {
    $this->leave($phpbb_user_id, $forum_id);
  }

  // --------------------
  // Cover photos
  // --------------------

  public function get_cover_url(int $forum_id): string {
    if ($forum_id <= 0) return '';
    global $wpdb;
    $t = $this->table_covers();
    $v = $wpdb->get_var($wpdb->prepare("SELECT cover_url FROM {$t} WHERE forum_id=%d LIMIT 1", $forum_id));
    $v = is_string($v) ? trim($v) : '';
    return $v;
  }

  public function set_cover_url(int $forum_id, string $url, int $actor_phpbb_user_id = 0): void {
    $forum_id = (int)$forum_id;
    if ($forum_id <= 0) return;
    $url = trim((string)$url);
    if ($url === '') return;
    if (function_exists('esc_url_raw')) $url = esc_url_raw($url);
    global $wpdb;
    $t = $this->table_covers();
    $wpdb->query($wpdb->prepare(
      "INSERT INTO {$t} (forum_id, cover_url, updated_at, updated_by)
       VALUES (%d,%s,%d,%d)
       ON DUPLICATE KEY UPDATE cover_url=VALUES(cover_url), updated_at=VALUES(updated_at), updated_by=VALUES(updated_by)",
      $forum_id, $url, time(), (int)$actor_phpbb_user_id
    ));
  }

  // --------------------
  // Inactivity digest
  // --------------------

  private function ensure_cron(): void {
    // Daily is fine; the rule is “hasn't interacted for 28 days”.
    if (!wp_next_scheduled('ia_discuss_agora_inactivity_digest')) {
      wp_schedule_event(time() + 300, 'daily', 'ia_discuss_agora_inactivity_digest');
    }
    add_action('ia_discuss_agora_inactivity_digest', [$this, 'run_inactivity_digest']);
  }

  public function run_inactivity_digest(): void {
    if (!$this->phpbb->is_ready()) return;
    if (!function_exists('ia_mail_suite')) return;

    global $wpdb;
    $t = $this->table_members();
    if (!$wpdb) return;

    $now = time();
    $cut = $now - (28 * 24 * 60 * 60);

    $rows = $wpdb->get_results(
      $wpdb->prepare("SELECT phpbb_user_id, forum_id, last_interaction FROM {$t} WHERE last_interaction > 0 AND last_interaction < %d", $cut),
      ARRAY_A
    );
    if (!is_array($rows) || !$rows) return;

    $db = $this->phpbb->db();
    if (!$db) return;

    $posts = $this->phpbb->table('posts');

    foreach ($rows as $r) {
      $uid = (int)($r['phpbb_user_id'] ?? 0);
      $forum_id = (int)($r['forum_id'] ?? 0);
      if ($uid <= 0 || $forum_id <= 0) continue;

      // Find the most active topic in the last 30 days.
      $since = $now - (30 * 24 * 60 * 60);
      $topic_id = (int) $db->get_var(
        $db->prepare(
          "SELECT topic_id FROM {$posts}
           WHERE forum_id=%d AND post_visibility=1 AND post_time >= %d
           GROUP BY topic_id
           ORDER BY COUNT(*) DESC
           LIMIT 1",
          $forum_id, $since
        )
      );
      if ($topic_id <= 0) continue;

      $topic = null;
      try { $topic = $this->phpbb->get_topic_row($topic_id); } catch (Throwable $e) { $topic = null; }
      if (!$topic) continue;
      $topic_title = (string)($topic['topic_title'] ?? 'Topic');

      // Email user (even if notifications disabled).
      $u = $db->get_row($db->prepare("SELECT user_email, username FROM " . $this->phpbb->table('users') . " WHERE user_id=%d LIMIT 1", $uid), ARRAY_A);
      if (!is_array($u)) continue;
      $email = (string)($u['user_email'] ?? '');
      if (!$email || !is_email($email)) continue;

      $forum = null;
      try { $forum = $this->phpbb->get_forum_row($forum_id); } catch (Throwable $e) { $forum = null; }
      $agora_name = $forum ? (string)($forum['forum_name'] ?? '') : (string)$forum_id;

      $topic_url = home_url('/?tab=discuss&ia_tab=discuss&iad_topic=' . $topic_id . '&iad_view=topic');
      $topic_url = (string) apply_filters('ia_discuss_topic_url', $topic_url, $topic_id, 0);

      $ctx = [
        'display_name' => (string)($u['username'] ?? ('user#' . $uid)),
        'agora_name' => $agora_name,
        'forum_id' => (string)$forum_id,
        'topic_title' => $topic_title,
        'topic_id' => (string)$topic_id,
        'topic_url' => $topic_url,
      ];

      try {
        ia_mail_suite()->send_template('ia_discuss_agora_inactive_popular', $email, $ctx);
      } catch (Throwable $e) {}

      do_action('ia_discuss_agora_inactive_popular', $uid, $forum_id, $topic_id);
    }
  }
}
