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


  /** @var array<int,array<int,int>> topic_id => [phpbb_user_id=>1] */
  private $participant_cache = [];

  /** @var array<int,?array> phpbb_user_id => row|null */
  private $user_cache = [];

  public function __construct(IA_Discuss_Service_PhpBB $phpbb, IA_Discuss_Service_Auth $auth) {
    $this->phpbb = $phpbb;
    $this->auth = $auth;

    // Hook Agora membership change events (emitted elsewhere) -> email.
    add_action('ia_discuss_agora_kicked',  [$this, 'on_agora_kicked'], 10, 3);
    add_action('ia_discuss_agora_readded', [$this, 'on_agora_readded'], 10, 3);

    // New topic notification (created in an Agora).
    // Fired by IA_Discuss_Module_Write::ajax_new_topic().
    add_action('ia_discuss_topic_created', [$this, 'on_topic_created'], 10, 4);
  }

  /**
   * Action handler for a newly created topic.
   *
   * @param int $topic_id
   * @param int $forum_id
   * @param int $actor_phpbb_user_id
   * @param int $first_post_id
   */
  public function on_topic_created(int $topic_id, int $forum_id, int $actor_phpbb_user_id, int $first_post_id = 0): void {
    $this->notify_new_topic($topic_id, $forum_id, $first_post_id, $actor_phpbb_user_id);
  }

  /**
   * Send "new topic" notifications to users following the Agora (bell on),
   * respecting per-topic override, and optionally including the author unless they opted out at creation.
   */
  public function notify_new_topic(int $topic_id, int $forum_id, int $first_post_id, int $actor_phpbb_user_id): void {
    $topic_id = (int)$topic_id;
    $forum_id = (int)$forum_id;
    $first_post_id = (int)$first_post_id;
    if ($topic_id <= 0 || $forum_id <= 0) return;
    if (!function_exists('ia_mail_suite')) return;
    if (!$this->phpbb->is_ready()) return;

    // membership service is required for bell-followers.
    if (!$this->membership || !method_exists($this->membership, 'list_notify_users')) return;

    $actor_phpbb_user_id = $this->resolve_canonical_phpbb_user_id($actor_phpbb_user_id);
    if ($actor_phpbb_user_id <= 0) return;

    try {
      $topic = $this->phpbb->get_topic_row($topic_id);
    } catch (Throwable $e) {
      return;
    }

    $topic_title = (string)($topic['topic_title'] ?? 'Topic');
    $actor = $this->get_phpbb_user_row($actor_phpbb_user_id);
    $actor_name = $actor ? (string)($actor['username'] ?? '') : '';

    $topic_url = $this->make_topic_url($topic_id, 0);
    $post_url = $this->make_topic_url($topic_id, $first_post_id);

    // Start with bell followers for this Agora.
    $candidate = [];
    foreach ((array)$this->membership->list_notify_users($forum_id) as $uid) {
      $uid = (int)$uid;
      if ($uid > 0) $candidate[$uid] = 1;
    }

    // Include author if they did not explicitly opt out for this topic at creation.
    // (Write module sets topic_notify=false when they uncheck the box.)
    if ($this->topic_notify_state($actor_phpbb_user_id, $topic_id) !== 0) {
      $candidate[$actor_phpbb_user_id] = 1;
    }

    if (!$candidate) return;

    // Resolve recipients using the same precedence rules as replies:
    // topic OFF blocks; topic ON allows; null falls back to bell.
    foreach (array_keys($candidate) as $uid) {
      $uid = (int)$uid;
      if ($uid <= 0) continue;

      // Master kill-switch: IA Notify "emails" preference (and mute_all) disables ALL notification emails.
      if (function_exists('ia_notify_emails_enabled_for_phpbb') && !ia_notify_emails_enabled_for_phpbb($uid)) {
        continue;
      }

      $topic_state = $this->topic_notify_state($uid, $topic_id); // null|1|0
      if ($topic_state === 0) continue;

      $bell_on = (int)$this->membership->get_notify_agora($uid, $forum_id);
      if ($topic_state === null && $bell_on !== 1) continue;

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
        'post_id' => (string)$first_post_id,
        'post_url' => $post_url,
        'topic_url' => $topic_url,
      ];

      try {
        // Master email kill-switch: IA Notify preference
        if (function_exists('ia_notify_emails_enabled_for_phpbb') && !ia_notify_emails_enabled_for_phpbb((int)$uid)) {
          continue;
        }

        ia_mail_suite()->send_template('ia_discuss_new_topic', $email, $ctx);
      } catch (Throwable $e) {
        // ignore
      }
    }
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
    if ($topic_id <= 0 || $new_post_id <= 0) return;
    if (!function_exists('ia_mail_suite')) return;
    if (!$this->phpbb->is_ready()) return;

    try {
      $topic = $this->phpbb->get_topic_row($topic_id);
    } catch (Throwable $e) {
      return;
    }

    $forum_id = (int)($topic['forum_id'] ?? 0);

    // Candidates = participants ∪ bell-followers ∪ explicit topic opt-ins.
    $candidate = [];

    foreach ($this->get_topic_participant_ids($topic_id) as $uid) {
      $uid = (int)$uid;
      if ($uid > 0) $candidate[$uid] = 1;
    }

    if ($this->membership && $forum_id > 0 && method_exists($this->membership, 'list_notify_users')) {
      foreach ((array)$this->membership->list_notify_users($forum_id) as $uid) {
        $uid = (int)$uid;
        if ($uid > 0) $candidate[$uid] = 1;
      }
    }

    foreach ($this->get_topic_optin_ids($topic_id) as $uid) {
      $uid = (int)$uid;
      if ($uid > 0) $candidate[$uid] = 1;
    }

    if (!$candidate) return;

    // actor
    $actor_phpbb_user_id = $this->resolve_canonical_phpbb_user_id($actor_phpbb_user_id);
    if ($actor_phpbb_user_id <= 0) return;

    $actor = $this->get_phpbb_user_row($actor_phpbb_user_id);
    $actor_name = $actor ? (string)($actor['username'] ?? '') : '';

    $post_url = $this->make_topic_url($topic_id, $new_post_id);
    $topic_title = (string)($topic['topic_title'] ?? 'Topic');

    foreach (array_keys($candidate) as $uid) {
      $uid = (int)$uid;
      if ($uid <= 0) continue;
      if ($uid === $actor_phpbb_user_id) continue;

      // Master kill-switch: IA Notify "emails" preference (and mute_all) disables ALL notification emails.
      if (function_exists('ia_notify_emails_enabled_for_phpbb') && !ia_notify_emails_enabled_for_phpbb($uid)) {
        continue;
      }

      // Resolve per-user preference.
      // 1) Explicit per-topic OFF always blocks.
      // 2) Explicit per-topic ON always allows.
      // 3) No explicit per-topic preference:
      //    - allow if user is a participant OR has Agora bell on.
      $topic_state = $this->topic_notify_state($uid, $topic_id); // null|1|0
      if ($topic_state === 0) continue;

      $is_participant = $this->is_topic_participant($uid, $topic_id);
      $bell_on = 0;
      if ($this->membership && $forum_id > 0) {
        $bell_on = (int) $this->membership->get_notify_agora($uid, $forum_id);
      }

      if ($topic_state === null) {
        if (!$is_participant && $bell_on !== 1) continue;
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

      try {
        // Master email kill-switch: IA Notify preference
        if (function_exists('ia_notify_emails_enabled_for_phpbb') && !ia_notify_emails_enabled_for_phpbb((int)$uid)) {
          return;
        }

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

    // Master kill-switch: IA Notify "emails" preference (and mute_all) disables ALL notification emails.
    if (function_exists('ia_notify_emails_enabled_for_phpbb') && !ia_notify_emails_enabled_for_phpbb($phpbb_user_id)) {
      return;
    }
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
      // Master email kill-switch: IA Notify preference
      if (function_exists('ia_notify_emails_enabled_for_phpbb') && !ia_notify_emails_enabled_for_phpbb((int)$uid)) {
        return;
      }

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
    $topic_id = (int)$topic_id;
    if ($topic_id <= 0) return [];

    if (isset($this->participant_cache[$topic_id])) {
      return array_keys($this->participant_cache[$topic_id]);
    }

    $this->participant_cache[$topic_id] = [];

    $db = $this->phpbb->db();
    if (!$db) return [];

    $posts = $this->phpbb->table('posts');

    $sql = "SELECT DISTINCT poster_id FROM {$posts}
            WHERE topic_id=%d AND post_visibility=1 AND poster_id > 0";
    $rows = $db->get_col($db->prepare($sql, $topic_id));
    if (!is_array($rows)) return [];

    foreach ($rows as $id) {
      $id = (int)$id;
      $canon = $this->resolve_canonical_phpbb_user_id($id);
      if ($canon > 0) $this->participant_cache[$topic_id][$canon] = 1;
    }

    return array_keys($this->participant_cache[$topic_id]);
  }

  /**
   * Public accessor for topic participant phpBB user IDs.
   * Used by IA Notify (in-app notifications) to determine topic subscriptions.
   */
  public function list_topic_participants(int $topic_id): array {
    return $this->get_topic_participant_ids((int)$topic_id);
  }


  private function is_topic_participant(int $phpbb_user_id, int $topic_id): bool {
    $phpbb_user_id = $this->resolve_canonical_phpbb_user_id((int)$phpbb_user_id);
    $topic_id = (int)$topic_id;
    if ($phpbb_user_id <= 0 || $topic_id <= 0) return false;

    $ids = $this->get_topic_participant_ids($topic_id);
    return in_array($phpbb_user_id, $ids, true);
  }

  private function get_topic_optin_ids(int $topic_id): array {
    $topic_id = (int)$topic_id;
    if ($topic_id <= 0) return [];
    global $wpdb;
    if (!$wpdb) return [];
    $table = $this->table_topic_notify();
    // Users with explicit ON for this topic.
    $rows = $wpdb->get_col($wpdb->prepare(
      "SELECT phpbb_user_id FROM {$table} WHERE topic_id=%d AND enabled=1",
      $topic_id
    ));
    if (!$rows) return [];
    $out = [];
    foreach ($rows as $r) {
      $id = (int)$r;
      if ($id > 0) $out[$id] = 1;
    }
    return array_keys($out);
  }

  private function resolve_canonical_phpbb_user_id(int $id): int {
    $id = (int)$id;
    if ($id <= 0) return 0;

    // If this ID exists in phpBB users, treat it as canonical.
    $db = $this->phpbb->db();
    if ($db) {
      $users = $this->phpbb->table('users');
      $v = $db->get_var($db->prepare("SELECT user_id FROM {$users} WHERE user_id=%d LIMIT 1", $id));
      if ($v) return (int)$v;
    }

    // Fallback: some stacks accidentally store WP user IDs in poster_id.
    // If $id is a WP user ID and it has ia_phpbb_user_id, map it.
    if (function_exists('get_user_meta')) {
      $mapped = (int) get_user_meta($id, 'ia_phpbb_user_id', true);
      if ($mapped > 0) return $mapped;
    }

    return 0;
  }

  function get_phpbb_user_row(int $phpbb_user_id): ?array {
    $phpbb_user_id = $this->resolve_canonical_phpbb_user_id((int)$phpbb_user_id);
    if ($phpbb_user_id <= 0) return null;

    if (array_key_exists($phpbb_user_id, $this->user_cache)) {
      return $this->user_cache[$phpbb_user_id];
    }

    $db = $this->phpbb->db();
    if (!$db) {
      $this->user_cache[$phpbb_user_id] = null;
      return null;
    }

    $users = $this->phpbb->table('users');
    $row = $db->get_row($db->prepare("SELECT user_id, username, user_email FROM {$users} WHERE user_id=%d LIMIT 1", $phpbb_user_id), ARRAY_A);
    if (!is_array($row)) {
      $this->user_cache[$phpbb_user_id] = null;
      return null;
    }

    $this->user_cache[$phpbb_user_id] = $row;
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

    // Master kill-switch: IA Notify "emails" preference (and mute_all) disables ALL notification emails.
    if (function_exists('ia_notify_emails_enabled_for_phpbb') && !ia_notify_emails_enabled_for_phpbb($phpbb_user_id)) {
      return;
    }
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
      // Master email kill-switch: IA Notify preference
      if (function_exists('ia_notify_emails_enabled_for_phpbb') && !ia_notify_emails_enabled_for_phpbb((int)$uid)) {
        return;
      }

      ia_mail_suite()->send_template('ia_discuss_agora_kicked', $email, $ctx);
    } catch (Throwable $e) {}
  }

  public function on_agora_readded($phpbb_user_id, $forum_id, $actor_phpbb_user_id = 0): void {
    $phpbb_user_id = (int)$phpbb_user_id;
    $forum_id = (int)$forum_id;
    if ($phpbb_user_id <= 0 || $forum_id <= 0) return;

    // Master kill-switch: IA Notify "emails" preference (and mute_all) disables ALL notification emails.
    if (function_exists('ia_notify_emails_enabled_for_phpbb') && !ia_notify_emails_enabled_for_phpbb($phpbb_user_id)) {
      return;
    }
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
      // Master email kill-switch: IA Notify preference
      if (function_exists('ia_notify_emails_enabled_for_phpbb') && !ia_notify_emails_enabled_for_phpbb((int)$uid)) {
        return;
      }

      ia_mail_suite()->send_template('ia_discuss_agora_readded', $email, $ctx);
    } catch (Throwable $e) {}
  }

}
