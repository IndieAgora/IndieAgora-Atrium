<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Service_Agora_Privacy {

  private $phpbb;

  public function __construct(IA_Discuss_Service_PhpBB $phpbb) {
    $this->phpbb = $phpbb;
  }

  public function boot(): void {
    $this->ensure_tables();
  }

  private function t_privacy(): string {
    global $wpdb;
    return $wpdb->prefix . 'ia_discuss_agora_privacy';
  }

  private function t_invites(): string {
    global $wpdb;
    return $wpdb->prefix . 'ia_discuss_agora_invites';
  }

  public function ensure_tables(): void {
    global $wpdb;
    if (!$wpdb) return;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    $privacy = $this->t_privacy();
    $sql1 = "CREATE TABLE {$privacy} (
      forum_id BIGINT(20) UNSIGNED NOT NULL,
      is_private TINYINT(1) NOT NULL DEFAULT 0,
      updated_at INT(11) UNSIGNED NOT NULL DEFAULT 0,
      updated_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY (forum_id),
      KEY is_private (is_private)
    ) {$charset};";
    dbDelta($sql1);

    $invites = $this->t_invites();
    $sql2 = "CREATE TABLE {$invites} (
      invite_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      forum_id BIGINT(20) UNSIGNED NOT NULL,
      invitee_phpbb_user_id BIGINT(20) UNSIGNED NOT NULL,
      invited_by_phpbb_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
      status VARCHAR(16) NOT NULL DEFAULT 'pending',
      created_at INT(11) UNSIGNED NOT NULL DEFAULT 0,
      responded_at INT(11) UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY (invite_id),
      UNIQUE KEY forum_user (forum_id, invitee_phpbb_user_id),
      KEY invitee_status (invitee_phpbb_user_id, status),
      KEY forum_status (forum_id, status)
    ) {$charset};";
    dbDelta($sql2);
  }

  public function is_private(int $forum_id): bool {
    $forum_id = (int)$forum_id;
    if ($forum_id <= 0) return false;
    global $wpdb;
    if (!$wpdb) return false;
    $v = $wpdb->get_var($wpdb->prepare("SELECT is_private FROM {$this->t_privacy()} WHERE forum_id=%d LIMIT 1", $forum_id));
    return ((int)$v) === 1;
  }

  public function set_private(int $forum_id, bool $is_private, int $actor_phpbb_user_id = 0): void {
    $forum_id = (int)$forum_id;
    if ($forum_id <= 0) return;
    global $wpdb;
    if (!$wpdb) return;
    $wpdb->query($wpdb->prepare(
      "INSERT INTO {$this->t_privacy()} (forum_id, is_private, updated_at, updated_by)
       VALUES (%d,%d,%d,%d)
       ON DUPLICATE KEY UPDATE is_private=VALUES(is_private), updated_at=VALUES(updated_at), updated_by=VALUES(updated_by)",
      $forum_id,
      $is_private ? 1 : 0,
      time(),
      (int)$actor_phpbb_user_id
    ));
  }

  public function user_has_access(int $phpbb_user_id, int $forum_id): bool {
    $forum_id = (int)$forum_id;
    $phpbb_user_id = (int)$phpbb_user_id;
    if ($forum_id <= 0) return false;
    if (!$this->is_private($forum_id)) return true;
    if ($phpbb_user_id <= 0) return false;
    if ($this->phpbb->user_is_forum_moderator($phpbb_user_id, $forum_id)) return true;
    return $this->has_accepted_invite($forum_id, $phpbb_user_id);
  }

  public function has_accepted_invite(int $forum_id, int $phpbb_user_id): bool {
    $forum_id = (int)$forum_id;
    $phpbb_user_id = (int)$phpbb_user_id;
    if ($forum_id <= 0 || $phpbb_user_id <= 0) return false;
    global $wpdb;
    if (!$wpdb) return false;
    $v = $wpdb->get_var($wpdb->prepare(
      "SELECT invite_id FROM {$this->t_invites()} WHERE forum_id=%d AND invitee_phpbb_user_id=%d AND status='accepted' LIMIT 1",
      $forum_id,
      $phpbb_user_id
    ));
    return ((int)$v) > 0;
  }

  public function invite_user(int $forum_id, int $invitee_phpbb_user_id, int $actor_phpbb_user_id): int {
    $forum_id = (int)$forum_id;
    $invitee_phpbb_user_id = (int)$invitee_phpbb_user_id;
    $actor_phpbb_user_id = (int)$actor_phpbb_user_id;
    if ($forum_id <= 0 || $invitee_phpbb_user_id <= 0 || $actor_phpbb_user_id <= 0) return 0;
    global $wpdb;
    if (!$wpdb) return 0;
    $now = time();

    // Invalidate any prior invite row first so every reinvite gets a fresh invite_id.
    // This prevents stale links from becoming valid again and also avoids Notify deduping
    // a reinvite onto an older notification with the same object_id.
    $wpdb->delete(
      $this->t_invites(),
      [
        'forum_id' => $forum_id,
        'invitee_phpbb_user_id' => $invitee_phpbb_user_id,
      ],
      ['%d', '%d']
    );

    $wpdb->insert(
      $this->t_invites(),
      [
        'forum_id' => $forum_id,
        'invitee_phpbb_user_id' => $invitee_phpbb_user_id,
        'invited_by_phpbb_user_id' => $actor_phpbb_user_id,
        'status' => 'pending',
        'created_at' => $now,
        'responded_at' => 0,
      ],
      ['%d', '%d', '%d', '%s', '%d', '%d']
    );

    return (int)$wpdb->insert_id;
  }

  public function get_invite(int $invite_id): array {
    $invite_id = (int)$invite_id;
    if ($invite_id <= 0) return [];
    global $wpdb;
    if (!$wpdb) return [];
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->t_invites()} WHERE invite_id=%d LIMIT 1", $invite_id), ARRAY_A);
    return is_array($row) ? $row : [];
  }

  public function get_invite_for_user(int $invite_id, int $phpbb_user_id): array {
    $invite = $this->get_invite($invite_id);
    if (!$invite) return [];
    return ((int)($invite['invitee_phpbb_user_id'] ?? 0) === (int)$phpbb_user_id) ? $invite : [];
  }

  public function respond_to_invite(int $invite_id, int $phpbb_user_id, string $decision): array {
    $invite = $this->get_invite_for_user($invite_id, $phpbb_user_id);
    if (!$invite) return [];
    if ((string)($invite['status'] ?? '') !== 'pending') return [];
    $decision = ($decision === 'accept') ? 'accepted' : 'declined';
    global $wpdb;
    if (!$wpdb) return [];
    $wpdb->update(
      $this->t_invites(),
      ['status' => $decision, 'responded_at' => time()],
      ['invite_id' => (int)$invite_id],
      ['%s', '%d'],
      ['%d']
    );
    $invite['status'] = $decision;
    $invite['responded_at'] = time();
    return $invite;
  }

  public function revoke_user_access(int $forum_id, int $phpbb_user_id): void {
    $forum_id = (int)$forum_id;
    $phpbb_user_id = (int)$phpbb_user_id;
    if ($forum_id <= 0 || $phpbb_user_id <= 0) return;
    global $wpdb;
    if (!$wpdb) return;
    $wpdb->query($wpdb->prepare(
      "UPDATE {$this->t_invites()} SET status='declined', responded_at=%d WHERE forum_id=%d AND invitee_phpbb_user_id=%d AND status='accepted'",
      time(),
      $forum_id,
      $phpbb_user_id
    ));
  }

  public function list_invites(int $forum_id, string $status = ''): array {
    $forum_id = (int)$forum_id;
    if ($forum_id <= 0) return [];
    global $wpdb;
    if (!$wpdb) return [];
    $sql = "SELECT * FROM {$this->t_invites()} WHERE forum_id=%d";
    $args = [$forum_id];
    if ($status !== '') {
      $sql .= " AND status=%s";
      $args[] = $status;
    }
    $sql .= " ORDER BY created_at DESC LIMIT 200";
    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
    return is_array($rows) ? $rows : [];
  }
}
