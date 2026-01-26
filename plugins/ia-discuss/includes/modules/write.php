<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Module_Write implements IA_Discuss_Module_Interface {

  private $phpbb;
  private $bbcode;
  private $auth;
  private $write;
  private $notify;
  private $membership;

  public function __construct(
    IA_Discuss_Service_PhpBB $phpbb,
    IA_Discuss_Render_BBCode $bbcode,
    IA_Discuss_Service_Auth $auth,
    IA_Discuss_Service_PhpBB_Write $write,
    IA_Discuss_Service_Notify $notify,
    IA_Discuss_Service_Membership $membership
  ) {
    $this->phpbb  = $phpbb;
    $this->bbcode = $bbcode;
    $this->auth   = $auth;
    $this->write  = $write;
    $this->notify = $notify;
    $this->membership = $membership;
  }

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_discuss_forum_meta' => ['method' => 'ajax_forum_meta', 'public' => true],
      'ia_discuss_new_topic'  => ['method' => 'ajax_new_topic',  'public' => false],
      'ia_discuss_reply'      => ['method' => 'ajax_reply',      'public' => false],
      'ia_discuss_topic_notify_set' => ['method' => 'ajax_topic_notify_set', 'public' => false],
          'ia_discuss_edit_post' => ['method' => 'ajax_edit_post', 'public' => false],
      'ia_discuss_delete_post' => ['method' => 'ajax_delete_post', 'public' => false],
      'ia_discuss_ban_user' => ['method' => 'ajax_ban_user', 'public' => false],
      'ia_discuss_unban_user' => ['method' => 'ajax_unban_user', 'public' => false],
    ];
  }

  public function ajax_forum_meta(): void {
    $forum_id = isset($_POST['forum_id']) ? (int)$_POST['forum_id'] : 0;
    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    try {
      $row = $this->phpbb->get_forum_row($forum_id);

      $viewer = (int)$this->auth->current_phpbb_user_id();
      $joined = ($viewer > 0) ? ($this->membership->is_joined($viewer, $forum_id) ? 1 : 0) : 0;
      $bell = ($viewer > 0) ? ((int)$this->membership->get_notify_agora($viewer, $forum_id) ? 1 : 0) : 0;
      $cover = (string) $this->membership->cover_url($forum_id);
      $banned = 0;
      if ($viewer > 0) {
        try { $banned = $this->write->is_user_banned($forum_id, $viewer) ? 1 : 0; } catch (Throwable $e) { $banned = 0; }
      }

      // Record interaction for inactivity pings.
      if ($viewer > 0 && $joined === 1) {
        try { $this->membership->touch($viewer, $forum_id); } catch (Throwable $e) {}
      }
      $can_edit_cover = 0;
      if ($viewer > 0) {
        $is_admin = function_exists('current_user_can') && current_user_can('manage_options');
        $is_mod = $this->phpbb->user_is_forum_moderator($viewer, $forum_id);
        if ($is_admin) $is_mod = true;
        $can_edit_cover = $is_mod ? 1 : 0;
      }

      ia_discuss_json_ok([
        'forum_id' => (int)$row['forum_id'],
        'forum_name' => (string)$row['forum_name'],
        'forum_desc_html' => $this->bbcode->format_post_html((string)($row['forum_desc'] ?? '')),
        'forum_posts' => (int)($row['forum_posts'] ?? 0),
        'forum_topics' => (int)($row['forum_topics_real'] ?? ($row['forum_topics'] ?? 0)),
        'joined' => $joined,
        'bell' => $bell,
        'banned' => $banned,
        'cover_url' => $cover,
        'can_edit_cover' => $can_edit_cover,
      ]);
    } catch (Throwable $e) {
      ia_discuss_json_err('Forum meta error: ' . $e->getMessage(), 500);
    }
  }

  public function ajax_new_topic(): void {
    if (!$this->auth->is_logged_in()) ia_discuss_json_err('Login required', 401);

    $forum_id = isset($_POST['forum_id']) ? (int)$_POST['forum_id'] : 0;
    $title    = isset($_POST['title']) ? (string)wp_unslash($_POST['title']) : '';
    $body     = isset($_POST['body']) ? (string)wp_unslash($_POST['body']) : '';
    $notify   = isset($_POST['notify']) ? (int)$_POST['notify'] : 1;

    $title = sanitize_text_field($title);
    $body  = trim($body); // keep formatting

    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    try {
      $out = $this->write->create_topic($forum_id, $title, $body);
      // Topic-level email notifications (default ON). If user unchecks, persist opt-out.
      $new_topic_id = isset($out['topic_id']) ? (int)$out['topic_id'] : 0;
      $actor = (int)$this->auth->current_phpbb_user_id();
      if ($new_topic_id > 0 && $actor > 0 && (int)$notify === 0) {
        $this->notify->set_topic_notify($actor, $new_topic_id, false);
      }

      // Agora interaction (for inactivity pings) and emit signals for the upcoming notifications system.
      if ($actor > 0 && $forum_id > 0) {
        try { $this->membership->touch($actor, $forum_id); } catch (Throwable $e) {}
      }
      do_action('ia_discuss_topic_created', $new_topic_id, $forum_id, $actor, (int)($out['post_id'] ?? 0));
      ia_discuss_json_ok($out);
    } catch (Throwable $e) {
      ia_discuss_json_err('New topic error: ' . $e->getMessage(), 500);
    }
  }

  public function ajax_reply(): void {
    if (!$this->auth->is_logged_in()) ia_discuss_json_err('Login required', 401);

    $topic_id = isset($_POST['topic_id']) ? (int)$_POST['topic_id'] : 0;
    $body     = isset($_POST['body']) ? (string)wp_unslash($_POST['body']) : '';
    $body     = trim($body);

    if ($topic_id <= 0) ia_discuss_json_err('Missing topic_id', 400);
    if ($body === '') ia_discuss_json_err('Missing reply body', 400);

    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    try {
      $out = $this->write->reply($topic_id, $body);
      // Email notifications: notify participants (starter + anyone who has posted), respecting opt-out.
      $post_id = isset($out['post_id']) ? (int)$out['post_id'] : 0;
      $actor = (int)$this->auth->current_phpbb_user_id();
      if ($post_id > 0 && $actor > 0) {
        $this->notify->ensure_actor_subscribed($actor, $topic_id);
        $this->notify->notify_reply($topic_id, $post_id, $actor);
      }

      // Touch Agora interaction and emit signal.
      $forum_id = (int)($out['forum_id'] ?? 0);
      if ($actor > 0 && $forum_id > 0) {
        try { $this->membership->touch($actor, $forum_id); } catch (Throwable $e) {}
      }
      do_action('ia_discuss_post_replied', $topic_id, (int)($out['post_id'] ?? 0), $forum_id, $actor);
      ia_discuss_json_ok($out);
    } catch (Throwable $e) {
      ia_discuss_json_err('Reply error: ' . $e->getMessage(), 500);
    }
  }


  
  public function ajax_topic_notify_set(): void {
    if (!$this->auth->is_logged_in()) ia_discuss_json_err('Login required', 401);

    $topic_id = isset($_POST['topic_id']) ? (int)$_POST['topic_id'] : 0;
    $enabled  = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 1;

    if ($topic_id <= 0) ia_discuss_json_err('Missing topic_id', 400);

    $actor = (int)$this->auth->current_phpbb_user_id();
    if ($actor <= 0) ia_discuss_json_err('Not logged in', 401);

    try {
      $this->notify->set_topic_notify($actor, $topic_id, $enabled ? true : false);
      do_action('ia_discuss_topic_notify_set', $actor, $topic_id, $enabled ? 1 : 0);
      ia_discuss_json_ok(['topic_id' => $topic_id, 'enabled' => $enabled ? 1 : 0]);
    } catch (Throwable $e) {
      ia_discuss_json_err('Notify preference error: ' . $e->getMessage(), 500);
    }
  }


public function ajax_edit_post(): void {
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    $post_id = (int)($_POST['post_id'] ?? 0);
    $body = trim((string)($_POST['body'] ?? ''));

    if ($post_id <= 0) ia_discuss_json_err('Missing post_id', 400);
    if ($body === '') ia_discuss_json_err('Missing body', 400);

    $viewer = (int)$this->auth->current_phpbb_user_id();
    if ($viewer <= 0) ia_discuss_json_err('Not logged in', 401);

    try {
      // Lookup post to verify permissions
      $db = $this->phpbb->db();
      $posts = $this->phpbb->table('posts');
      $row = $db ? $db->get_row($db->prepare("SELECT post_id, forum_id, poster_id FROM {$posts} WHERE post_id = %d LIMIT 1", $post_id), ARRAY_A) : null;
      if (!$row) ia_discuss_json_err('Post not found', 404);

      $forum_id = (int)($row['forum_id'] ?? 0);
      $poster_id = (int)($row['poster_id'] ?? 0);

      $is_admin = function_exists('current_user_can') && current_user_can('manage_options');
      $is_mod = ($forum_id > 0) ? $this->phpbb->user_is_forum_moderator($viewer, $forum_id) : false;
      if ($is_admin) $is_mod = true;

      $can_edit = $is_admin || $is_mod || ($viewer === $poster_id);
      if (!$can_edit) ia_discuss_json_err('Forbidden', 403);

      $out = $this->write->edit_post($post_id, $body, $viewer);
      ia_discuss_json_ok($out);
    } catch (Throwable $e) {
      ia_discuss_json_err('Edit error: ' . $e->getMessage(), 500);
    }
  }

  public function ajax_delete_post(): void {
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    $post_id = (int)($_POST['post_id'] ?? 0);
    $reason = (string)($_POST['reason'] ?? '');

    if ($post_id <= 0) ia_discuss_json_err('Missing post_id', 400);

    $viewer = (int)$this->auth->current_phpbb_user_id();
    if ($viewer <= 0) ia_discuss_json_err('Not logged in', 401);

    try {
      $db = $this->phpbb->db();
      $posts = $this->phpbb->table('posts');
      $row = $db ? $db->get_row($db->prepare("SELECT post_id, forum_id, poster_id FROM {$posts} WHERE post_id = %d LIMIT 1", $post_id), ARRAY_A) : null;
      if (!$row) ia_discuss_json_err('Post not found', 404);

      $forum_id = (int)($row['forum_id'] ?? 0);

      $is_admin = function_exists('current_user_can') && current_user_can('manage_options');
      $is_mod = ($forum_id > 0) ? $this->phpbb->user_is_forum_moderator($viewer, $forum_id) : false;
      if ($is_admin) $is_mod = true;

      if (!($is_admin || $is_mod)) ia_discuss_json_err('Forbidden', 403);

      $out = $this->write->delete_post($post_id, $viewer, $reason);
      ia_discuss_json_ok($out);
    } catch (Throwable $e) {
      ia_discuss_json_err('Delete error: ' . $e->getMessage(), 500);
    }
  }

  public function ajax_ban_user(): void {
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    $forum_id = (int)($_POST['forum_id'] ?? 0);
    $user_id  = (int)($_POST['user_id'] ?? 0);

    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);
    if ($user_id <= 0) ia_discuss_json_err('Missing user_id', 400);

    $viewer = (int)$this->auth->current_phpbb_user_id();
    if ($viewer <= 0) ia_discuss_json_err('Not logged in', 401);

    try {
      $is_admin = function_exists('current_user_can') && current_user_can('manage_options');
      $is_mod = $this->phpbb->user_is_forum_moderator($viewer, $forum_id);
      if ($is_admin) $is_mod = true;

      if (!($is_admin || $is_mod)) ia_discuss_json_err('Forbidden', 403);
      if ($user_id === $viewer) ia_discuss_json_err('Cannot ban self', 400);

      $this->write->ban_user_in_forum($forum_id, $user_id, $viewer);
      // Kicked users should no longer be joined.
      try { $this->membership->leave($user_id, $forum_id); } catch (Throwable $e) {}
      do_action('ia_discuss_agora_kicked', $user_id, $forum_id, $viewer);
      ia_discuss_json_ok(['banned' => 1, 'forum_id' => $forum_id, 'user_id' => $user_id]);
    } catch (Throwable $e) {
      ia_discuss_json_err('Ban error: ' . $e->getMessage(), 500);
    }
  }

  public function ajax_unban_user(): void {
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    $forum_id = (int)($_POST['forum_id'] ?? 0);
    $user_id  = (int)($_POST['user_id'] ?? 0);

    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);
    if ($user_id <= 0) ia_discuss_json_err('Missing user_id', 400);

    $viewer = (int)$this->auth->current_phpbb_user_id();
    if ($viewer <= 0) ia_discuss_json_err('Not logged in', 401);

    try {
      $is_admin = function_exists('current_user_can') && current_user_can('manage_options');
      $is_mod = $this->phpbb->user_is_forum_moderator($viewer, $forum_id);
      if ($is_admin) $is_mod = true;

      if (!($is_admin || $is_mod)) ia_discuss_json_err('Forbidden', 403);

      $this->write->unban_user_in_forum($forum_id, $user_id);
      do_action('ia_discuss_agora_readded', $user_id, $forum_id, $viewer);
      ia_discuss_json_ok(['banned' => 0, 'forum_id' => $forum_id, 'user_id' => $user_id]);
    } catch (Throwable $e) {
      ia_discuss_json_err('Unban error: ' . $e->getMessage(), 500);
    }
  }

}
