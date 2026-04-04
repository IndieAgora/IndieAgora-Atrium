<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Discuss — Agora membership + bell + cover controls.
 */
final class IA_Discuss_Module_Membership implements IA_Discuss_Module_Interface {

  private $auth;
  private $phpbb;
  private $write;
  private $membership;
  private $privacy;

  public function __construct(
    IA_Discuss_Service_Auth $auth,
    IA_Discuss_Service_PhpBB $phpbb,
    IA_Discuss_Service_PhpBB_Write $write,
    IA_Discuss_Service_Membership $membership,
    IA_Discuss_Service_Agora_Privacy $privacy
  ) {
    $this->auth = $auth;
    $this->phpbb = $phpbb;
    $this->write = $write;
    $this->membership = $membership;
    $this->privacy = $privacy;
  }

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_discuss_agora_join' => ['method' => 'ajax_join', 'public' => false],
      'ia_discuss_agora_leave' => ['method' => 'ajax_leave', 'public' => false],
      'ia_discuss_agora_notify_set' => ['method' => 'ajax_notify_set', 'public' => false],
      'ia_discuss_agora_cover_set' => ['method' => 'ajax_cover_set', 'public' => false],
      'ia_discuss_agora_invite_get' => ['method' => 'ajax_invite_get', 'public' => false],
      'ia_discuss_agora_invite_respond' => ['method' => 'ajax_invite_respond', 'public' => false],
    ];
  }

  public function ajax_join(): void {
    if (!$this->auth->is_logged_in()) ia_discuss_json_err('Login required', 401);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    $forum_id = (int)($_POST['forum_id'] ?? 0);
    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);

    $uid = (int)$this->auth->current_phpbb_user_id();
    if ($uid <= 0) ia_discuss_json_err('No phpBB identity for this user', 401);

    if ($this->write->is_user_banned($forum_id, $uid)) {
      ia_discuss_json_err('You have been kicked from this Agora', 403);
    }
    if ($this->privacy->is_private($forum_id) && !$this->privacy->user_has_access($uid, $forum_id)) {
      ia_discuss_json_err('Invite required for this private Agora', 403);
    }

    $already = $this->membership->is_joined($uid, $forum_id);
    $this->membership->join($uid, $forum_id);

    do_action('ia_discuss_agora_joined', $uid, $forum_id);

    ia_discuss_json_ok([
      'joined' => 1,
      'already' => $already ? 1 : 0,
      'forum_id' => $forum_id,
    ]);
  }

  public function ajax_leave(): void {
    if (!$this->auth->is_logged_in()) ia_discuss_json_err('Login required', 401);
    $forum_id = (int)($_POST['forum_id'] ?? 0);
    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);

    $uid = (int)$this->auth->current_phpbb_user_id();
    if ($uid <= 0) ia_discuss_json_err('No phpBB identity for this user', 401);

    $is_private = $this->privacy->is_private($forum_id);
    $is_mod = false;
    try { $is_mod = $this->phpbb->user_is_forum_moderator($uid, $forum_id); } catch (Throwable $e) { $is_mod = false; }

    $this->membership->leave($uid, $forum_id);
    if ($is_private && !$is_mod) {
      $this->privacy->revoke_user_access($forum_id, $uid);
    }
    do_action('ia_discuss_agora_left', $uid, $forum_id);

    ia_discuss_json_ok([
      'joined' => 0,
      'forum_id' => $forum_id,
      'is_private' => $is_private ? 1 : 0,
      'access_revoked' => ($is_private && !$is_mod) ? 1 : 0,
    ]);
  }

  public function ajax_notify_set(): void {
    if (!$this->auth->is_logged_in()) ia_discuss_json_err('Login required', 401);
    $forum_id = (int)($_POST['forum_id'] ?? 0);
    $enabled = (int)($_POST['enabled'] ?? 0);
    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);

    $uid = (int)$this->auth->current_phpbb_user_id();
    if ($uid <= 0) ia_discuss_json_err('No phpBB identity for this user', 401);

    if ($enabled && $this->write->is_user_banned($forum_id, $uid)) {
      ia_discuss_json_err('You have been kicked from this Agora', 403);
    }
    if ($enabled && $this->privacy->is_private($forum_id) && !$this->privacy->user_has_access($uid, $forum_id)) {
      ia_discuss_json_err('Invite required for this private Agora', 403);
    }

    $this->membership->set_notify_agora($uid, $forum_id, $enabled === 1);
    do_action('ia_discuss_agora_notify_set', $uid, $forum_id, $enabled ? 1 : 0);

    ia_discuss_json_ok([
      'enabled' => $enabled ? 1 : 0,
      'forum_id' => $forum_id,
    ]);
  }

  public function ajax_cover_set(): void {
    if (!$this->auth->is_logged_in()) ia_discuss_json_err('Login required', 401);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    $forum_id = (int)($_POST['forum_id'] ?? 0);
    $url = isset($_POST['cover_url']) ? (string)wp_unslash($_POST['cover_url']) : '';
    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);

    $actor = (int)$this->auth->current_phpbb_user_id();
    if ($actor <= 0) ia_discuss_json_err('No phpBB identity for this user', 401);

    $is_mod = $this->phpbb->user_is_forum_moderator($actor, $forum_id);
    if (!$is_mod) ia_discuss_json_err('Forbidden', 403);

    $this->membership->set_cover_url($forum_id, $url, $actor);
    do_action('ia_discuss_agora_cover_updated', $forum_id, $actor, $url);

    ia_discuss_json_ok([
      'forum_id' => $forum_id,
      'cover_url' => $this->membership->cover_url($forum_id),
    ]);
  }

  public function ajax_invite_get(): void {
    if (!$this->auth->is_logged_in()) ia_discuss_json_err('Login required', 401);
    $invite_id = (int)($_POST['invite_id'] ?? 0);
    if ($invite_id <= 0) ia_discuss_json_err('Missing invite_id', 400);

    $uid = (int)$this->auth->current_phpbb_user_id();
    if ($uid <= 0) ia_discuss_json_err('No phpBB identity for this user', 401);

    $invite = $this->privacy->get_invite_for_user($invite_id, $uid);
    if (!$invite) ia_discuss_json_err('Invite not found', 404);

    $forum_id = (int)($invite['forum_id'] ?? 0);
    $forum_name = '';
    try {
      $forum = $this->phpbb->get_forum_row($forum_id);
      $forum_name = (string)($forum['forum_name'] ?? '');
    } catch (Throwable $e) {}

    ia_discuss_json_ok([
      'invite_id' => $invite_id,
      'forum_id' => $forum_id,
      'forum_name' => $forum_name,
      'status' => (string)($invite['status'] ?? ''),
    ]);
  }

  public function ajax_invite_respond(): void {
    if (!$this->auth->is_logged_in()) ia_discuss_json_err('Login required', 401);
    $invite_id = (int)($_POST['invite_id'] ?? 0);
    $decision = sanitize_key((string)($_POST['decision'] ?? ''));
    if ($invite_id <= 0) ia_discuss_json_err('Missing invite_id', 400);
    if (!in_array($decision, ['accept', 'decline'], true)) ia_discuss_json_err('Invalid decision', 400);

    $uid = (int)$this->auth->current_phpbb_user_id();
    if ($uid <= 0) ia_discuss_json_err('No phpBB identity for this user', 401);

    $invite = $this->privacy->respond_to_invite($invite_id, $uid, $decision);
    if (!$invite) ia_discuss_json_err('Invite not found', 404);

    $forum_id = (int)($invite['forum_id'] ?? 0);
    if ($decision === 'accept') {
      $this->membership->join($uid, $forum_id);
    } else {
      $this->membership->leave($uid, $forum_id);
    }

    ia_discuss_json_ok([
      'invite_id' => $invite_id,
      'forum_id' => $forum_id,
      'status' => (string)($invite['status'] ?? ''),
    ]);
  }
}
