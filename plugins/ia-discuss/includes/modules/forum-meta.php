<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Discuss — Forum/Agora meta endpoint for Agora view.
 *
 * The Agora view (assets/js/ia-discuss.ui.agora.js) expects `ia_discuss_forum_meta`.
 * This must return membership + bell state consistently with the Agora list.
 */
final class IA_Discuss_Module_Forum_Meta implements IA_Discuss_Module_Interface {

  private $phpbb;
  private $bbcode;
  private $auth;
  private $membership;
  private $write;

  public function __construct(
    IA_Discuss_Service_PhpBB $phpbb,
    IA_Discuss_Render_BBCode $bbcode,
    IA_Discuss_Service_Auth $auth,
    IA_Discuss_Service_Membership $membership,
    IA_Discuss_Service_PhpBB_Write $write
  ) {
    $this->phpbb = $phpbb;
    $this->bbcode = $bbcode;
    $this->auth = $auth;
    $this->membership = $membership;
    $this->write = $write;
  }

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      // Public view; joined/bell/banned are viewer-relative.
      'ia_discuss_forum_meta' => ['method' => 'ajax_forum_meta', 'public' => true],
    ];
  }

  public function ajax_forum_meta(): void {
    $forum_id = isset($_POST['forum_id']) ? (int)$_POST['forum_id'] : 0;
    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    try {
      $row = $this->phpbb->get_forum_row($forum_id);
    } catch (Throwable $e) {
      ia_discuss_json_err($e->getMessage(), 404);
    }

    $viewer = (int) $this->auth->current_phpbb_user_id();

    $joined = 0;
    $bell = 0;
    $banned = 0;

    if ($viewer > 0) {
      try { $joined = $this->membership->is_joined($viewer, $forum_id) ? 1 : 0; } catch (Throwable $e) { $joined = 0; }
      try { $bell = (int) $this->membership->get_notify_agora($viewer, $forum_id) ? 1 : 0; } catch (Throwable $e) { $bell = 0; }
      try { $banned = $this->write->is_user_banned($forum_id, $viewer) ? 1 : 0; } catch (Throwable $e) { $banned = 0; }
    }

    // Cover edit permission: WP admin or forum moderator.
    $can_edit_cover = 0;
    if ($viewer > 0) {
      $is_admin = function_exists('current_user_can') && current_user_can('manage_options');
      $is_mod = $this->phpbb->user_is_forum_moderator($viewer, $forum_id);
      if ($is_admin) $is_mod = true;
      $can_edit_cover = $is_mod ? 1 : 0;
    }

    $desc_html = $this->bbcode->excerpt_html((string)($row['forum_desc'] ?? ''), 600);
    $cover = (string) $this->membership->cover_url($forum_id);

    ia_discuss_json_ok([
      'forum_id' => $forum_id,
      'forum_name' => (string)($row['forum_name'] ?? ''),
      'forum_desc_html' => $desc_html,
      'forum_topics' => (int)($row['forum_topics'] ?? 0),
      'forum_posts' => (int)($row['forum_posts'] ?? 0),

      // Viewer-relative
      'joined' => $joined,
      'bell' => $bell,
      'banned' => $banned,

      // Cover
      'cover_url' => $cover,
      'can_edit_cover' => $can_edit_cover,
    ]);
  }
}
