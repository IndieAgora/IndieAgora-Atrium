<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Discuss email notifications + topic-level preferences.
 *
 * Stores per-topic opt-out in a WP table keyed by phpBB user_id + topic_id.
 * Missing row == enabled (default on).
 */
final class IA_Discuss_Service_Notify {

  /** @var IA_Discuss_Service_PhpBB */
  private $phpbb;

  /** @var IA_Discuss_Service_Auth */
  private $auth;

  /** @var IA_Discuss_Service_Membership|null */
  private $membership = null;

  public function __construct(IA_Discuss_Service_PhpBB $phpbb, IA_Discuss_Service_Auth $auth) {
    $this->phpbb = $phpbb;
    $this->auth = $auth;

    // Hook Agora membership change events (emitted elsewhere) -> email.
    add_action('ia_discuss_agora_kicked',  [$this, 'on_agora_kicked'], 10, 3);
    add_action('ia_discuss_agora_readded', [$this, 'on_agora_readded'], 10, 3);
  }

  /**
   * Optional wiring: Agora membership/notification preferences.
   */
  public function set_membership_service(IA_Discuss_Service_Membership $membership): void {
    $this->membership = $membership;
  }

  public function boot(): void {
    // Ensure table exists (idempotent). Activation is nice-to-have but not guaranteed in your workflow.
    $this->ensure_tables();
  }

  public function ensure_tables(): void {
    global $wpdb;
    $table = $this->table_topic_notify();
    $charset = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table} (
      phpbb_user_id BIGINT(20) UNSIGNED NOT NULL,
      topic_id BIGINT(20) UNSIGNED NOT NULL,
      enabled TINYINT(1) NOT NULL DEFAULT 1,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY  (phpbb_user_id, topic_id),
      KEY topic_id (topic_id)
    ) {$charset};";

    dbDelta($sql);
  }

  private function table_topic_notify(): string {
    global $wpdb;
    return $wpdb->prefix . 'ia_discuss_topic_notify';
  }

  public function topic_notify_enabled(int $phpbb_user_id, int $topic_id): bool {
    if ($phpbb_user_id <= 0 || $topic_id <= 0) return true; // default on
    global $wpdb;
    $table = $this->table_topic_notify();
    $row = $wpdb->get_row($wpdb->prepare("SELECT enabled FROM {$table} WHERE phpbb_user_id=%d AND topic_id=%d LIMIT 1", $phpbb_user_id, $topic_id), ARRAY_A);
    if (!$row) return true;
    return !empty($row['enabled']);
  }

  /**
   * Returns null when the user has never explicitly set a preference for this topic.
   * When a row exists, returns 1/0.
   */
  public function topic_notify_state(int $phpbb_user_id, int $topic_id): ?int {
    if ($phpbb_user_id <= 0 || $topic_id <= 0) return null;
    global $wpdb;
    $table = $this->table_topic_notify();
    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT enabled FROM {$table} WHERE phpbb_user_id=%d AND topic_id=%d LIMIT 1", $phpbb_user_id, $topic_id),
      ARRAY_A
    );
    if (!$row) return null;
    return !empty($row['enabled']) ? 1 : 0;
  }

  public function set_topic_notify(int $phpbb_user_id, int $topic_id, bool $enabled): void {
    if ($phpbb_user_id <= 0 || $topic_id <= 0) return;
    global $wpdb;
    $table = $this->table_topic_notify();
    $wpdb->query(
      $wpdb->prepare(
        "INSERT INTO {$table} (phpbb_user_id, topic_id, enabled, updated_at)
         VALUES (%d,%d,%d,UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE enabled=VALUES(enabled), updated_at=VALUES(updated_at)",
        $phpbb_user_id, $topic_id, $enabled ? 1 : 0
      )
    );
  }

  /**
   * Send reply notifications to topic participants (starter + anyone who has posted),
   * excluding the author of the new reply, respecting per-topic opt-out.
   *
   * @param int $topic_id
   * @param int $new_post_id
   * @param int $actor_phpbb_user_id
   */
  public function notify_reply(int $topic_id, int $new_post_id, int $actor_phpbb_user_id): void {
    if ($topic_id <= 0 || $new_post_id <= 0 || $actor_phpbb_user_id <= 0) return;
    if (!function_exists('ia_mail_suite')) return;

    if (!$this->phpbb->is_ready()) return;

    try {
      $topic = $this->phpbb->get_topic_row($topic_id);
    } catch (Throwable $e) {
      return;
    }

    $recipients = $this->get_topic_participant_ids($topic_id);
    if (!$recipients) return;

    // actor
    $actor = $this->get_phpbb_user_row($actor_phpbb_user_id);
    $actor_name = $actor ? (string)($actor['username'] ?? '') : '';

    $post_url = $this->make_topic_url($topic_id, $new_post_id);
    $topic_title = (string)($topic['topic_title'] ?? 'Topic');

    $forum_id = (int)($topic['forum_id'] ?? 0);
    foreach ($recipients as $uid) {
      $uid = (int)$uid;
      if ($uid <= 0) continue;
      if ($uid === $actor_phpbb_user_id) continue;

      // Preference resolution order:
      // 1) If Agora bell is enabled => default topic enabled, unless explicitly opted-out.
      // 2) If Agora bell is disabled => default topic disabled, unless explicitly opted-in.
      $topic_state = $this->topic_notify_state($uid, $topic_id); // null|1|0
      $agora_bell = 0;
      if ($this->membership && $forum_id > 0) {
        $agora_bell = (int) $this->membership->get_notify_agora($uid, $forum_id);
      }

      if ($agora_bell === 1) {
        // default ON, but explicit 0 overrides.
        if ($topic_state === 0) continue;
      } else {
        // default OFF, only explicit 1 allows.
        if ($topic_state !== 1) continue;
      }

      $u = $this->get_phpbb_user_row($uid);
      if (!$u) continue;

      $email = (string)($u['user_email'] ?? '');
      if (!$email || !is_email($email)) continue;

      $display = (string)($u['username'] ?? '');
      $ctx = [
        'display_name' => $display ?: ('user#' . $uid),
        'actor_name' => $actor_name ?: ('user#' . $actor_phpbb_user_id),
        'topic_title' => $topic_title,
        'topic_id' => (string)$topic_id,
        'post_id' => (string)$new_post_id,
        'post_url' => $post_url,
        'topic_url' => $this->make_topic_url($topic_id, 0),
      ];

      // Best-effort.
      try {
        ia_mail_suite()->send_template('ia_discuss_reply', $email, $ctx);
      } catch (Throwable $e) {
        // ignore
      }
    }
  }

  /**
   * 28-day inactivity ping (always sends, ignores bell + topic overrides).
   */
  public function send_agora_inactivity_popular(int $phpbb_user_id, int $forum_id, int $topic_id): void {
    if ($phpbb_user_id <= 0 || $forum_id <= 0 || $topic_id <= 0) return;
    if (!function_exists('ia_mail_suite')) return;
    if (!$this->phpbb->is_ready()) return;

    $u = $this->get_phpbb_user_row($phpbb_user_id);
    if (!$u) return;
    $email = (string)($u['user_email'] ?? '');
    if (!$email || !is_email($email)) return;

    try {
      $topic = $this->phpbb->get_topic_row($topic_id);
    } catch (Throwable $e) {
      return;
    }

    $agora_name = $this->get_forum_name($forum_id);
    $topic_title = (string)($topic['topic_title'] ?? 'Topic');
    $topic_url = $this->make_topic_url($topic_id, 0);

    $ctx = [
      'display_name' => (string)($u['username'] ?? ('user#' . $phpbb_user_id)),
      'agora_name' => $agora_name ?: (string)$forum_id,
      'forum_id' => (string)$forum_id,
      'topic_id' => (string)$topic_id,
      'topic_title' => $topic_title,
      'topic_url' => $topic_url,
      'agora_url' => $this->make_agora_url($forum_id),
    ];

    try {
      ia_mail_suite()->send_template('ia_discuss_agora_inactive_popular', $email, $ctx);
    } catch (Throwable $e) {}
  }

  /**
   * Attempt to auto-subscribe the actor to the topic unless they explicitly opted out.
   * (Default state is enabled; this only protects against accidental re-enabling.)
   */
  public function ensure_actor_subscribed(int $actor_phpbb_user_id, int $topic_id): void {
    if ($actor_phpbb_user_id <= 0 || $topic_id <= 0) return;
    // If user has an explicit row with enabled=0, keep it. Otherwise do nothing (default enabled).
    if ($this->topic_notify_enabled($actor_phpbb_user_id, $topic_id) === false) return;
    // No-op (default enabled). Kept for future extension.
  }

  private function get_topic_participant_ids(int $topic_id): array {
    $db = $this->phpbb->db();
    if (!$db) return [];

    $posts = $this->phpbb->table('posts');

    $sql = "SELECT DISTINCT poster_id FROM {$posts}
            WHERE topic_id=%d AND post_visibility=1 AND poster_id > 0";
    $rows = $db->get_col($db->prepare($sql, $topic_id));
    if (!is_array($rows)) return [];
    $out = [];
    foreach ($rows as $r) {
      $id = (int)$r;
      if ($id > 0) $out[$id] = 1;
    }
    return array_keys($out);
  }

  private function get_phpbb_user_row(int $phpbb_user_id): ?array {
    if ($phpbb_user_id <= 0) return null;
    $db = $this->phpbb->db();
    if (!$db) return null;

    $users = $this->phpbb->table('users');
    $row = $db->get_row($db->prepare("SELECT user_id, username, user_email FROM {$users} WHERE user_id=%d LIMIT 1", $phpbb_user_id), ARRAY_A);
    if (!is_array($row)) return null;
    return $row;
  }

  private function make_topic_url(int $topic_id, int $post_id = 0): string {
    $topic_id = max(0, (int)$topic_id);
    $post_id  = max(0, (int)$post_id);

    // Default: current site root + query params understood by the Discuss router.
    $qs = 'tab=discuss&ia_tab=discuss&iad_topic=' . $topic_id . ($post_id ? '&iad_post=' . $post_id : '') . '&iad_view=topic';
    $url = home_url('/?' . $qs);
/**
     * Allow Atrium shell (or other routing layer) to generate the canonical Discuss deep link.
     *
     * @param string $url
     * @param int $topic_id
     * @param int $post_id
     */
    return (string) apply_filters('ia_discuss_topic_url', $url, $topic_id, $post_id);
  }

  private function make_agora_url(int $forum_id): string {
    $forum_id = max(0, (int)$forum_id);
    $url = home_url('/?tab=discuss&ia_tab=discuss&iad_forum=' . $forum_id . '&iad_view=agora');
    return (string) apply_filters('ia_discuss_agora_url', $url, $forum_id);
  }

  private function get_forum_name(int $forum_id): string {
    if ($forum_id <= 0) return '';
    try {
      $row = $this->phpbb->get_forum_row($forum_id);
      return (string)($row['forum_name'] ?? '');
    } catch (Throwable $e) {
      return '';
    }
  }

  // -------------------------
  // Agora membership emails
  // -------------------------

  public function on_agora_kicked($phpbb_user_id, $forum_id, $actor_phpbb_user_id = 0): void {
    $phpbb_user_id = (int)$phpbb_user_id;
    $forum_id = (int)$forum_id;
    if ($phpbb_user_id <= 0 || $forum_id <= 0) return;
    if (!function_exists('ia_mail_suite')) return;

    $u = $this->get_phpbb_user_row($phpbb_user_id);
    if (!$u) return;

    $email = (string)($u['user_email'] ?? '');
    if (!$email || !is_email($email)) return;

    $agora_name = $this->get_forum_name($forum_id);
    $ctx = [
      'display_name' => (string)($u['username'] ?? ('user#' . $phpbb_user_id)),
      'agora_name' => $agora_name ?: (string)$forum_id,
      'forum_id' => (string)$forum_id,
      'agora_url' => $this->make_agora_url($forum_id),
    ];

    try {
      ia_mail_suite()->send_template('ia_discuss_agora_kicked', $email, $ctx);
    } catch (Throwable $e) {}
  }

  public function on_agora_readded($phpbb_user_id, $forum_id, $actor_phpbb_user_id = 0): void {
    $phpbb_user_id = (int)$phpbb_user_id;
    $forum_id = (int)$forum_id;
    if ($phpbb_user_id <= 0 || $forum_id <= 0) return;
    if (!function_exists('ia_mail_suite')) return;

    $u = $this->get_phpbb_user_row($phpbb_user_id);
    if (!$u) return;

    $email = (string)($u['user_email'] ?? '');
    if (!$email || !is_email($email)) return;

    $agora_name = $this->get_forum_name($forum_id);
    $ctx = [
      'display_name' => (string)($u['username'] ?? ('user#' . $phpbb_user_id)),
      'agora_name' => $agora_name ?: (string)$forum_id,
      'forum_id' => (string)$forum_id,
      'agora_url' => $this->make_agora_url($forum_id),
    ];

    try {
      ia_mail_suite()->send_template('ia_discuss_agora_readded', $email, $ctx);
    } catch (Throwable $e) {}
  }

}
