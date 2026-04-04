<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Module_Moderation implements IA_Discuss_Module_Interface {

  private $phpbb;
  private $bbcode;
  private $auth;
  private $membership;
  private $write;
  private $privacy;

  public function __construct(
    IA_Discuss_Service_PhpBB $phpbb,
    IA_Discuss_Render_BBCode $bbcode,
    IA_Discuss_Service_Auth $auth,
    IA_Discuss_Service_Membership $membership,
    IA_Discuss_Service_PhpBB_Write $write,
    IA_Discuss_Service_Agora_Privacy $privacy
  ) {
    $this->phpbb = $phpbb;
    $this->bbcode = $bbcode;
    $this->auth = $auth;
    $this->membership = $membership;
    $this->write = $write;
    $this->privacy = $privacy;
  }

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_discuss_my_moderation' => ['method' => 'ajax_my_moderation', 'public' => false],
      'ia_discuss_agora_settings_get' => ['method' => 'ajax_agora_settings_get', 'public' => false],
      'ia_discuss_agora_settings_save' => ['method' => 'ajax_agora_settings_save', 'public' => false],
      'ia_discuss_agora_setting_save_one' => ['method' => 'ajax_agora_setting_save_one', 'public' => false],
      'ia_discuss_cover_set' => ['method' => 'ajax_cover_set', 'public' => false],
      'ia_discuss_agora_unban' => ['method' => 'ajax_agora_unban', 'public' => false],
      'ia_discuss_agora_delete' => ['method' => 'ajax_agora_delete', 'public' => false],
      'ia_discuss_agora_privacy_set' => ['method' => 'ajax_agora_privacy_set', 'public' => false],
      'ia_discuss_agora_invite_user' => ['method' => 'ajax_agora_invite_user', 'public' => false],
    ];
  }

  private function current_phpbb_id(): int {
    return (int)$this->auth->current_phpbb_user_id();
  }

  private function can_manage_forum(int $phpbb_user_id, int $forum_id): bool {
    return ($phpbb_user_id > 0 && $forum_id > 0) ? $this->phpbb->user_is_forum_moderator($phpbb_user_id, $forum_id) : false;
  }

  private function list_kicked_users(int $forum_id): array {
    global $wpdb;
    if (!$wpdb) return [];
    $t = $wpdb->prefix . 'ia_discuss_forum_bans';
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
    if ((string)$exists !== (string)$t) return [];
    $users = $this->phpbb->table('users');
    $sql = "SELECT b.user_id, b.banned_by, b.created_at, u.username FROM {$t} b LEFT JOIN {$users} u ON u.user_id = b.user_id WHERE b.forum_id = %d ORDER BY b.created_at DESC LIMIT 500";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $forum_id), ARRAY_A);
    return is_array($rows) ? $rows : [];
  }

  public function ajax_my_moderation(): void {
    if (!is_user_logged_in()) ia_discuss_json_err('Login required', 401);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);
    $uid = $this->current_phpbb_id();
    if ($uid <= 0) ia_discuss_json_err('No phpBB identity for this user', 403);

    $forums = (array)$this->phpbb->list_moderated_forums($uid);
    $items = [];
    foreach ($forums as $r) {
      $fid = (int)($r['forum_id'] ?? 0);
      if ($fid <= 0) continue;
      $items[] = [
        'forum_id' => $fid,
        'forum_name' => (string)($r['forum_name'] ?? ''),
        'forum_desc_html' => $this->bbcode->excerpt_html((string)($r['forum_desc'] ?? ''), 260),
        'has_rules' => ((string)($r['forum_rules'] ?? '') !== '') ? 1 : 0,
        'cover_url' => (string)$this->membership->cover_url($fid),
        'is_private' => $this->privacy->is_private($fid) ? 1 : 0,
      ];
    }

    ia_discuss_json_ok(['global_admin' => 0, 'items' => $items, 'count' => count($items)]);
  }

  public function ajax_cover_set(): void {
    if (!is_user_logged_in()) ia_discuss_json_err('Login required', 401);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);
    $forum_id = isset($_POST['forum_id']) ? (int)$_POST['forum_id'] : 0;
    $cover_url = isset($_POST['cover_url']) ? (string)$_POST['cover_url'] : '';
    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);
    $uid = $this->current_phpbb_id();
    if ($uid <= 0) ia_discuss_json_err('No phpBB identity for this user', 403);
    if (!$this->can_manage_forum($uid, $forum_id)) ia_discuss_json_err('Not allowed', 403);
    $this->membership->set_cover_url($forum_id, $cover_url, $uid);
    ia_discuss_json_ok(['forum_id' => $forum_id, 'cover_url' => (string)$this->membership->cover_url($forum_id)]);
  }

  public function ajax_agora_settings_get(): void {
    if (!is_user_logged_in()) ia_discuss_json_err('Login required', 401);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);
    $forum_id = isset($_POST['forum_id']) ? (int)$_POST['forum_id'] : 0;
    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);
    $uid = $this->current_phpbb_id();
    if ($uid <= 0) ia_discuss_json_err('No phpBB identity for this user', 403);
    if (!$this->can_manage_forum($uid, $forum_id)) ia_discuss_json_err('Not allowed', 403);

    $row = $this->phpbb->get_forum_row($forum_id);
    $kicked = [];
    foreach ($this->list_kicked_users($forum_id) as $r) {
      $kicked[] = [
        'user_id' => (int)($r['user_id'] ?? 0),
        'username' => (string)($r['username'] ?? ''),
        'banned_by' => (int)($r['banned_by'] ?? 0),
        'created_at' => (int)($r['created_at'] ?? 0),
      ];
    }

    $invites = [];
    foreach ($this->privacy->list_invites($forum_id) as $invite) {
      $invitee_id = (int)($invite['invitee_phpbb_user_id'] ?? 0);
      $invites[] = [
        'invite_id' => (int)($invite['invite_id'] ?? 0),
        'user_id' => $invitee_id,
        'display' => ia_discuss_display_name_from_phpbb($invitee_id, ''),
        'username' => ia_discuss_display_name_from_phpbb($invitee_id, ''),
        'status' => (string)($invite['status'] ?? ''),
      ];
    }

    ia_discuss_json_ok([
      'forum_id' => $forum_id,
      'forum_name' => (string)($row['forum_name'] ?? ''),
      'forum_desc' => (string)($row['forum_desc'] ?? ''),
      'forum_rules' => (string)($row['forum_rules'] ?? ''),
      'cover_url' => (string)$this->membership->cover_url($forum_id),
      'is_private' => $this->privacy->is_private($forum_id) ? 1 : 0,
      'kicked' => $kicked,
      'invites' => $invites,
    ]);
  }

  public function ajax_agora_settings_save(): void {
    if (!is_user_logged_in()) ia_discuss_json_err('Login required', 401);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);
    $forum_id = isset($_POST['forum_id']) ? (int)$_POST['forum_id'] : 0;
    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);
    $uid = $this->current_phpbb_id();
    if ($uid <= 0) ia_discuss_json_err('No phpBB identity for this user', 403);
    if (!$this->can_manage_forum($uid, $forum_id)) ia_discuss_json_err('Not allowed', 403);

    $name = isset($_POST['forum_name']) ? trim((string)wp_unslash($_POST['forum_name'])) : '';
    $desc = isset($_POST['forum_desc']) ? (string)wp_unslash($_POST['forum_desc']) : '';
    $rules = isset($_POST['forum_rules']) ? (string)wp_unslash($_POST['forum_rules']) : '';
    if ($name === '') ia_discuss_json_err('Missing name', 400);
    if (mb_strlen($name) > 120) ia_discuss_json_err('Name too long', 400);
    if (mb_strlen($desc) > 8000) ia_discuss_json_err('Description too long', 400);
    if (mb_strlen($rules) > 12000) ia_discuss_json_err('Rules too long', 400);

    $this->phpbb->update_forum_meta($forum_id, ['forum_name' => $name, 'forum_desc' => $desc, 'forum_rules' => $rules]);

    ia_discuss_json_ok([
      'forum_id' => $forum_id,
      'forum_name' => $name,
      'forum_desc_html' => $this->bbcode->excerpt_html($desc, 600),
      'forum_rules_html' => $this->bbcode->format_post_html($rules),
    ]);
  }

  public function ajax_agora_setting_save_one(): void {
    if (!is_user_logged_in()) ia_discuss_json_err('Login required', 401);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);
    $forum_id = isset($_POST['forum_id']) ? (int)$_POST['forum_id'] : 0;
    $field = isset($_POST['field']) ? (string)$_POST['field'] : '';
    $value = isset($_POST['value']) ? (string)wp_unslash($_POST['value']) : '';
    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);
    $uid = $this->current_phpbb_id();
    if ($uid <= 0) ia_discuss_json_err('No phpBB identity for this user', 403);
    if (!$this->can_manage_forum($uid, $forum_id)) ia_discuss_json_err('Not allowed', 403);
    if (!in_array($field, ['forum_name', 'forum_desc', 'forum_rules'], true)) ia_discuss_json_err('Invalid field', 400);

    if ($field === 'forum_name') {
      $name = trim($value);
      if ($name === '') ia_discuss_json_err('Missing name', 400);
      if (mb_strlen($name) > 120) ia_discuss_json_err('Name too long', 400);
      $this->phpbb->update_forum_meta($forum_id, ['forum_name' => $name]);
      ia_discuss_json_ok(['forum_id' => $forum_id, 'field' => 'forum_name', 'value' => $name]);
    }
    if ($field === 'forum_desc') {
      if (mb_strlen($value) > 8000) ia_discuss_json_err('Description too long', 400);
      $this->phpbb->update_forum_meta($forum_id, ['forum_desc' => $value]);
      ia_discuss_json_ok(['forum_id' => $forum_id, 'field' => 'forum_desc', 'value' => $value, 'forum_desc_html' => $this->bbcode->excerpt_html($value, 600)]);
    }
    if ($field === 'forum_rules') {
      if (mb_strlen($value) > 12000) ia_discuss_json_err('Rules too long', 400);
      $this->phpbb->update_forum_meta($forum_id, ['forum_rules' => $value]);
      ia_discuss_json_ok(['forum_id' => $forum_id, 'field' => 'forum_rules', 'value' => $value, 'forum_rules_html' => $this->bbcode->format_post_html($value)]);
    }
  }

  public function ajax_agora_privacy_set(): void {
    if (!is_user_logged_in()) ia_discuss_json_err('Login required', 401);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);
    $forum_id = isset($_POST['forum_id']) ? (int)$_POST['forum_id'] : 0;
    $is_private = isset($_POST['is_private']) ? (int)$_POST['is_private'] : 0;
    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);
    $uid = $this->current_phpbb_id();
    if ($uid <= 0) ia_discuss_json_err('No phpBB identity for this user', 403);
    if (!$this->can_manage_forum($uid, $forum_id)) ia_discuss_json_err('Not allowed', 403);
    $this->privacy->set_private($forum_id, $is_private === 1, $uid);
    ia_discuss_json_ok(['forum_id' => $forum_id, 'is_private' => $is_private === 1 ? 1 : 0]);
  }

  public function ajax_agora_invite_user(): void {
    if (!is_user_logged_in()) ia_discuss_json_err('Login required', 401);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);
    $forum_id = isset($_POST['forum_id']) ? (int)$_POST['forum_id'] : 0;
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    if ($forum_id <= 0 || $user_id <= 0) ia_discuss_json_err('Missing parameters', 400);
    $uid = $this->current_phpbb_id();
    if ($uid <= 0) ia_discuss_json_err('No phpBB identity for this user', 403);
    if (!$this->can_manage_forum($uid, $forum_id)) ia_discuss_json_err('Not allowed', 403);
    if ($user_id === $uid) ia_discuss_json_err('Cannot invite self', 400);

    $invite_id = $this->privacy->invite_user($forum_id, $user_id, $uid);
    if ($invite_id <= 0) ia_discuss_json_err('Invite failed', 500);

    if (function_exists('ia_notify_insert')) {
      $forum = $this->phpbb->get_forum_row($forum_id);
      $actor_name = ia_discuss_display_name_from_phpbb($uid, 'Moderator');
      ia_notify_insert([
        'recipient_phpbb_id' => $user_id,
        'actor_phpbb_id' => $uid,
        'type' => 'discuss_agora_invite',
        'object_type' => 'discuss_agora_invite',
        'object_id' => $invite_id,
        'url' => home_url('/?tab=discuss&ia_tab=discuss&iad_view=agora&iad_forum=' . $forum_id . '&iad_invite=' . $invite_id),
        'text' => $actor_name . ' invited you to ' . (string)($forum['forum_name'] ?? 'an Agora') . '.',
        'meta' => ['forum_id' => $forum_id, 'invite_id' => $invite_id],
      ]);
    }

    ia_discuss_json_ok(['forum_id' => $forum_id, 'invite_id' => $invite_id]);
  }

  public function ajax_agora_unban(): void {
    if (!is_user_logged_in()) ia_discuss_json_err('Login required', 401);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);
    $forum_id = isset($_POST['forum_id']) ? (int)$_POST['forum_id'] : 0;
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    if ($forum_id <= 0 || $user_id <= 0) ia_discuss_json_err('Missing parameters', 400);
    $uid = $this->current_phpbb_id();
    if ($uid <= 0) ia_discuss_json_err('No phpBB identity for this user', 403);
    if (!$this->can_manage_forum($uid, $forum_id)) ia_discuss_json_err('Not allowed', 403);
    $this->write->unban_user_in_forum($forum_id, $user_id);
    ia_discuss_json_ok(['forum_id' => $forum_id, 'user_id' => $user_id, 'kicked' => $this->list_kicked_users($forum_id)]);
  }

  public function ajax_agora_delete(): void {
    if (!is_user_logged_in()) ia_discuss_json_err('Login required', 401);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);
    $forum_id = isset($_POST['forum_id']) ? (int)$_POST['forum_id'] : 0;
    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);
    $uid = $this->current_phpbb_id();
    if ($uid <= 0) ia_discuss_json_err('No phpBB identity for this user', 403);
    if (!$this->can_manage_forum($uid, $forum_id)) ia_discuss_json_err('Not allowed', 403);

    $this->phpbb->delete_forum_hard($forum_id);
    global $wpdb;
    if ($wpdb) {
      $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}ia_discuss_agora_members WHERE forum_id=%d", $forum_id));
      $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}ia_discuss_agora_covers WHERE forum_id=%d", $forum_id));
      $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}ia_discuss_forum_bans WHERE forum_id=%d", $forum_id));
      $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}ia_discuss_agora_privacy WHERE forum_id=%d", $forum_id));
      $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}ia_discuss_agora_invites WHERE forum_id=%d", $forum_id));
    }
    do_action('ia_discuss_agora_deleted', $forum_id, $uid);
    ia_discuss_json_ok(['deleted' => 1, 'forum_id' => $forum_id]);
  }
}
