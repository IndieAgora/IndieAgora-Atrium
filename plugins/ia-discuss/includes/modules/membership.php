<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Discuss — Agora membership + bell + cover controls.
 */
final class IA_Discuss_Module_Membership implements IA_Discuss_Module_Interface {

  /** @var IA_Discuss_Service_Auth */
  private $auth;

  /** @var IA_Discuss_Service_PhpBB */
  private $phpbb;

  /** @var IA_Discuss_Service_PhpBB_Write */
  private $write;

  /** @var IA_Discuss_Service_Membership */
  private $membership;

  public function __construct(
    IA_Discuss_Service_Auth $auth,
    IA_Discuss_Service_PhpBB $phpbb,
    IA_Discuss_Service_PhpBB_Write $write,
    IA_Discuss_Service_Membership $membership
  ) {
    $this->auth = $auth;
    $this->phpbb = $phpbb;
    $this->write = $write;
    $this->membership = $membership;
  }

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_discuss_agora_join' => ['method' => 'ajax_join', 'public' => false],
      'ia_discuss_agora_leave' => ['method' => 'ajax_leave', 'public' => false],
      'ia_discuss_agora_notify_set' => ['method' => 'ajax_notify_set', 'public' => false],
      'ia_discuss_agora_cover_set' => ['method' => 'ajax_cover_set', 'public' => false],
    ];
  }

  public function ajax_join(): void {
    if (!$this->auth->is_logged_in()) ia_discuss_json_err('Login required', 401);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    $forum_id = (int)($_POST['forum_id'] ?? 0);
    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);

    $uid = (int)$this->auth->current_phpbb_user_id();
    if ($uid <= 0) ia_discuss_json_err('No phpBB identity for this user', 401);

    // If banned, they can view but cannot join.
    if ($this->write->is_user_banned($forum_id, $uid)) {
      ia_discuss_json_err('You have been kicked from this Agora', 403);
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

    $this->membership->leave($uid, $forum_id);

    do_action('ia_discuss_agora_left', $uid, $forum_id);

    ia_discuss_json_ok([
      'joined' => 0,
      'forum_id' => $forum_id,
    ]);
  }

  public function ajax_notify_set(): void {
    if (!$this->auth->is_logged_in()) ia_discuss_json_err('Login required', 401);
    $forum_id = (int)($_POST['forum_id'] ?? 0);
    $enabled = (int)($_POST['enabled'] ?? 0);
    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);

    $uid = (int)$this->auth->current_phpbb_user_id();
    if ($uid <= 0) ia_discuss_json_err('No phpBB identity for this user', 401);

    // Can't enable bell if they are banned.
    if ($enabled && $this->write->is_user_banned($forum_id, $uid)) {
      ia_discuss_json_err('You have been kicked from this Agora', 403);
    }

    // Keep joined state unchanged; allow bell toggle even when not joined (will upsert a row).
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

    // Admins and forum moderators only.
    $is_admin = function_exists('current_user_can') && current_user_can('manage_options');
    $is_mod = $this->phpbb->user_is_forum_moderator($actor, $forum_id);
    if ($is_admin) $is_mod = true;
    if (!$is_mod) ia_discuss_json_err('Forbidden', 403);

    $this->membership->set_cover_url($forum_id, $url, $actor);
    do_action('ia_discuss_agora_cover_updated', $forum_id, $actor, $url);

    ia_discuss_json_ok([
      'forum_id' => $forum_id,
      'cover_url' => $this->membership->cover_url($forum_id),
    ]);
  }
}
