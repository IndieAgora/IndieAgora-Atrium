<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Module_Write implements IA_Discuss_Module_Interface {

  private $phpbb;
  private $bbcode;
  private $auth;
  private $write;
  private $notify;
  private $membership;
  private $privacy;
  private $reports;

  public function __construct(
    IA_Discuss_Service_PhpBB $phpbb,
    IA_Discuss_Render_BBCode $bbcode,
    IA_Discuss_Service_Auth $auth,
    IA_Discuss_Service_PhpBB_Write $write,
    IA_Discuss_Service_Notify $notify,
    IA_Discuss_Service_Membership $membership,
    IA_Discuss_Service_Agora_Privacy $privacy,
    IA_Discuss_Service_Reports $reports
  ) {
    $this->phpbb  = $phpbb;
    $this->bbcode = $bbcode;
    $this->auth   = $auth;
    $this->write  = $write;
    $this->notify = $notify;
    $this->membership = $membership;
    $this->privacy = $privacy;
    $this->reports = $reports;
  }

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_discuss_forum_meta' => ['method' => 'ajax_forum_meta', 'public' => true],
      'ia_discuss_new_topic'  => ['method' => 'ajax_new_topic',  'public' => false],
      'ia_discuss_reply'      => ['method' => 'ajax_reply',      'public' => false],
      'ia_discuss_share_to_connect' => ['method' => 'ajax_share_to_connect', 'public' => false],
      'ia_discuss_report_post' => ['method' => 'ajax_report_post', 'public' => false],
      'ia_discuss_topic_notify_set' => ['method' => 'ajax_topic_notify_set', 'public' => false],
          'ia_discuss_edit_post' => ['method' => 'ajax_edit_post', 'public' => false],
      'ia_discuss_delete_post' => ['method' => 'ajax_delete_post', 'public' => false],
      'ia_discuss_ban_user' => ['method' => 'ajax_ban_user', 'public' => false],
      'ia_discuss_unban_user' => ['method' => 'ajax_unban_user', 'public' => false],
    ];
  }

  private function extract_mentions(string $text): array {
    $text = (string)$text;
    if ($text === '' || stripos($text, '@') === false) return [];
    if (!preg_match_all('/(^|[^a-zA-Z0-9_])@([a-zA-Z0-9_\-\.]{2,40})/u', $text, $m)) return [];
    $out = [];
    foreach ((array)$m[2] as $u) {
      $u = (string)$u;
      if ($u === '') continue;
      $out[] = $u;
    }
    return array_values(array_unique($out));
  }

  private function emit_mentions(string $text, int $actor_phpbb, int $topic_id, int $post_id): void {
    $actor_phpbb = (int)$actor_phpbb;
    $topic_id = (int)$topic_id;
    $post_id = (int)$post_id;
    if ($actor_phpbb <= 0 || $topic_id <= 0 || $post_id <= 0) return;

    $mentions = $this->extract_mentions($text);
    if (empty($mentions)) return;

    foreach ($mentions as $uname) {
      do_action('ia_discuss_user_mentioned', (string)$uname, $actor_phpbb, $topic_id, $post_id);
    }
  }

  public function ajax_forum_meta(): void {
    $forum_id = isset($_POST['forum_id']) ? (int)$_POST['forum_id'] : 0;
    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    try {
      $viewer = (int)$this->auth->current_phpbb_user_id();
      if (!$this->privacy->user_has_access($viewer, $forum_id)) ia_discuss_json_err('Private Agora', 403);
      $row = $this->phpbb->get_forum_row($forum_id);

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
    // Topic-level email preference selected at creation.
    // 1 = enabled, 0 = disabled
    $notify   = isset($_POST['notify']) ? (int)$_POST['notify'] : 1;

    $title = sanitize_text_field($title);
    $body  = trim($body); // keep formatting

    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    try {
      $actor = (int)$this->auth->current_phpbb_user_id();
      if (!$this->privacy->user_has_access($actor, $forum_id)) ia_discuss_json_err('Invite required for this private Agora', 403);
      $out = $this->write->create_topic($forum_id, $title, $body);
      // Topic-level email notifications (default ON). If user unchecks, persist opt-out.
      $new_topic_id = isset($out['topic_id']) ? (int)$out['topic_id'] : 0;
      $actor = (int)$this->auth->current_phpbb_user_id();
      if ($new_topic_id > 0 && $actor > 0) {
        if ((int)$notify === 0) {
          $this->notify->set_topic_notify($actor, $new_topic_id, false);
        } else {
          // Persist explicit opt-in so topic notifications can override bell-off.
          $this->notify->set_topic_notify($actor, $new_topic_id, true);
        }
      }

      // Agora interaction (for inactivity pings) and emit signals for the upcoming notifications system.
      if ($actor > 0 && $forum_id > 0) {
        try { $this->membership->touch($actor, $forum_id); } catch (Throwable $e) {}
      }
      do_action('ia_discuss_topic_created', $new_topic_id, $forum_id, $actor, (int)($out['post_id'] ?? 0));

      // @mentions: notify + Connect wall embed (handled by ia-notify/ia-connect).
      $created_post_id = (int)($out['post_id'] ?? 0);
      if ($new_topic_id > 0 && $created_post_id > 0) {
        $this->emit_mentions($title . "\n" . $body, $actor, $new_topic_id, $created_post_id);
      }

      // Cross-platform activity signal (followers/notifications).
      $actor_phpbb = (int)$actor; // already a phpBB user id
      if ($actor_phpbb > 0) {
        do_action('ia_user_activity', $actor_phpbb, 'discuss_topic', [
          'topic_id' => $new_topic_id,
          'forum_id' => $forum_id,
          'post_id' => (int)($out['post_id'] ?? 0),
        ]);
      }
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
      $topic = $this->phpbb->get_topic_row($topic_id);
      $forum_id = (int)($topic['forum_id'] ?? 0);
      $actor = (int)$this->auth->current_phpbb_user_id();
      if (!$this->privacy->user_has_access($actor, $forum_id)) ia_discuss_json_err('Invite required for this private Agora', 403);
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

      // @mentions: notify + Connect wall embed (handled by ia-notify/ia-connect).
      $created_post_id = (int)($out['post_id'] ?? 0);
      if ($created_post_id > 0) {
        $this->emit_mentions($body, $actor, $topic_id, $created_post_id);
      }

      // Cross-platform activity signal (followers/notifications).
      $actor_phpbb = (int)$actor; // already a phpBB user id
      if ($actor_phpbb > 0) {
        do_action('ia_user_activity', $actor_phpbb, 'discuss_reply', [
          'topic_id' => $topic_id,
          'forum_id' => $forum_id,
          'post_id' => (int)($out['post_id'] ?? 0),
        ]);
      }
      ia_discuss_json_ok($out);
    } catch (Throwable $e) {
      ia_discuss_json_err('Reply error: ' . $e->getMessage(), 500);
    }
  }

  public function ajax_share_to_connect(): void {
    if (!$this->auth->is_logged_in()) ia_discuss_json_err('Login required', 401);

    $topic_id = isset($_POST['topic_id']) ? (int)$_POST['topic_id'] : 0;
    $post_id  = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    if ($topic_id <= 0) ia_discuss_json_err('Missing fields', 400);

    $actor_wp = (int) get_current_user_id();
    $actor_phpbb = (int) $this->auth->current_phpbb_user_id();
    if ($actor_wp <= 0 || $actor_phpbb <= 0) ia_discuss_json_err('Login required', 401);

    $topic = $this->phpbb->get_topic_row($topic_id);
    $forum_id = (int)($topic['forum_id'] ?? 0);
    if ($this->privacy->is_private($forum_id)) ia_discuss_json_err('Share to Connect is disabled in private Agoras', 403);

    global $wpdb;
    if (!$wpdb) ia_discuss_json_err('DB not available', 500);

    $connect_posts = $wpdb->prefix . 'ia_connect_posts';
    $has = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $connect_posts));
    if (!$has) ia_discuss_json_err('Connect not available', 503);

    // Resolve post_id: allow topic-level share (post_id=0) by selecting the topic's first visible post.
    $topic = null;
    $topic_title = '';
    $first_post_id = 0;
    try {
      $topic = $this->phpbb->get_topic_row($topic_id);
      $topic_title = (string)($topic['topic_title'] ?? '');
    } catch (Throwable $e) {
      $topic = null;
      $topic_title = '';
    }
    try {
      $db = $this->phpbb->db();
      if ($db) {
        $posts_t = $this->phpbb->table('posts');
        $first_post_id = (int)$db->get_var($db->prepare(
          "SELECT post_id FROM {$posts_t} WHERE topic_id=%d AND post_visibility=1 ORDER BY post_time ASC, post_id ASC LIMIT 1",
          $topic_id
        ));
      }
    } catch (Throwable $e) {
      $first_post_id = 0;
    }
    if ($post_id <= 0) $post_id = (int)$first_post_id;
    if ($post_id <= 0) ia_discuss_json_err('Post not found', 404);

    $comment = isset($_POST['comment']) ? (string) wp_unslash($_POST['comment']) : '';
    $comment = trim((string)wp_kses_post($comment));

    $wall_wp_ids = isset($_POST['wall_wp_ids']) ? (string) wp_unslash($_POST['wall_wp_ids']) : '';
    $wall_phpbb_ids = isset($_POST['wall_phpbb_ids']) ? (string) wp_unslash($_POST['wall_phpbb_ids']) : '';
    $share_to_self = isset($_POST['share_to_self']) ? (int)$_POST['share_to_self'] : 1;

    // Pull a safe excerpt of the post for the Connect body (fallback text).
    $excerpt = '';
    $orig_author_phpbb = 0;
    try {
      $db = $this->phpbb->db();
      if ($db) {
        $posts_t = $this->phpbb->table('posts');
        $row = $db->get_row($db->prepare(
          "SELECT post_text, poster_id FROM {$posts_t} WHERE post_id=%d AND topic_id=%d LIMIT 1",
          $post_id,
          $topic_id
        ), ARRAY_A);
        if ($row) {
          $orig_author_phpbb = (int)($row['poster_id'] ?? 0);
          $excerpt = (string)($row['post_text'] ?? '');
          $excerpt = trim(preg_replace('~\s+~', ' ', wp_strip_all_tags($excerpt)));
          // Limit to 200 words (Connect embeds show an extract, not the full post).
          if ($excerpt !== '') {
            $words = preg_split('~\s+~u', $excerpt, -1, PREG_SPLIT_NO_EMPTY);
            if (is_array($words) && count($words) > 200) {
              $excerpt = implode(' ', array_slice($words, 0, 200)) . '…';
            }
          }
        }
      }
    } catch (Throwable $e) {
      // ignore
    }

    // Build a Discuss deep link.
    $discuss_url = (string) apply_filters('ia_discuss_topic_url', '', $topic_id, $post_id);
    if ($discuss_url === '') {
      $discuss_url = add_query_arg([
        'tab' => 'discuss',
        'ia_tab' => 'discuss',
        'iad_topic' => $topic_id,
        'iad_view' => 'topic',
        'iad_post' => $post_id,
      ], home_url('/'));
    }

    // Store the optional share comment as the Connect post body.
    // Discuss preview rendering is handled via Connect using shared_tab/shared_ref.
    $body = $comment;
    if ($body === '' && $excerpt !== '') $body = $excerpt;

    // Encode share kind into shared_ref for backward compatibility:
    //  tid:pid[:topic|reply]
    $kind = ($first_post_id > 0 && (int)$post_id === (int)$first_post_id) ? 'topic' : 'reply';
    $shared_ref = ((int)$topic_id . ':' . (int)$post_id . ':' . $kind);

    // Resolve target walls.
    $targets_wp = [];
    $targets_phpbb = [];
    foreach (explode(',', $wall_wp_ids) as $v) {
      $id = (int)trim((string)$v);
      if ($id > 0) $targets_wp[] = $id;
    }
    foreach (explode(',', $wall_phpbb_ids) as $v) {
      $id = (int)trim((string)$v);
      if ($id > 0) $targets_phpbb[] = $id;
    }
    $targets_wp = array_values(array_unique($targets_wp));
    $targets_phpbb = array_values(array_unique($targets_phpbb));

    // Map phpBB targets to WP ids if needed.
    if (!empty($targets_phpbb)) {
      $um = $wpdb->usermeta;
      foreach ($targets_phpbb as $phpbb_id) {
        $wp_id = 0;
        try {
          $wp_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$um} WHERE (meta_key='ia_phpbb_user_id' OR meta_key='phpbb_user_id') AND meta_value=%s LIMIT 1",
            (string)$phpbb_id
          ));
        } catch (Throwable $e) { $wp_id = 0; }
        if ($wp_id > 0) $targets_wp[] = $wp_id;
      }
      $targets_wp = array_values(array_unique(array_filter(array_map('intval', $targets_wp))));
    }

    if ($share_to_self) {
      $targets_wp[] = $actor_wp;
    }
    $targets_wp = array_values(array_unique(array_filter(array_map('intval', $targets_wp))));
    if (empty($targets_wp)) ia_discuss_json_err('No targets selected', 400);

    
    // Apply privacy/block guards to build the final allowed target list (and compute shared-with count).
    // NOTE: this must iterate the requested target list (targets_wp). A regression accidentally iterated
    // the empty allowed_targets array, causing shares to fail with "No targets selected".
    $allowed_targets = [];
    foreach ($targets_wp as $wall_wp) {
      $wall_wp = (int)$wall_wp;
      if ($wall_wp <= 0) continue;

      // Respect Connect privacy searchable gate if available (wall targeting).
      if ($wall_wp !== $actor_wp && function_exists('ia_connect_user_profile_searchable') && function_exists('ia_connect_viewer_is_admin')) {
        try {
          if (!ia_connect_viewer_is_admin($actor_wp) && !ia_connect_user_profile_searchable($wall_wp)) {
            continue;
          }
        } catch (Throwable $e) {}
      }

      // Block guard if relationship helpers exist.
      if (function_exists('ia_connect_user_phpbb_id') && function_exists('ia_user_rel_is_blocked_any')) {
        try {
          $dst_phpbb = (int)ia_connect_user_phpbb_id($wall_wp);
          if ($dst_phpbb > 0 && ia_user_rel_is_blocked_any($actor_phpbb, $dst_phpbb)) continue;
        } catch (Throwable $e) {}
      }

      $allowed_targets[] = $wall_wp;
    }
    $allowed_targets = array_values(array_unique(array_filter(array_map('intval', $allowed_targets))));
    if (empty($allowed_targets)) ia_discuss_json_err('No targets selected', 400);

    $shared_with_count = 0;
    foreach ($allowed_targets as $wall_wp) {
      if ((int)$wall_wp !== (int)$actor_wp) $shared_with_count++;
    }

    // Extend shared_ref with a 4th field for "shared with" count: tid:pid:kind:count
    $shared_ref = ((int)$topic_id . ':' . (int)$post_id . ':' . $kind . ':' . (int)$shared_with_count);

$now = current_time('mysql');
    $first_created = 0;
    foreach ($allowed_targets as $wall_wp) {
      $wall_wp = (int)$wall_wp;
      if ($wall_wp <= 0) continue;

      // Respect Connect privacy searchable gate if available (wall targeting).
      if ($wall_wp !== $actor_wp && function_exists('ia_connect_user_profile_searchable') && function_exists('ia_connect_viewer_is_admin')) {
        try {
          if (!ia_connect_viewer_is_admin($actor_wp) && !ia_connect_user_profile_searchable($wall_wp)) {
            continue;
          }
        } catch (Throwable $e) {}
      }

      // Block guard if relationship helpers exist.
      if (function_exists('ia_connect_user_phpbb_id') && function_exists('ia_user_rel_is_blocked_any')) {
        try {
          $dst_phpbb = (int)ia_connect_user_phpbb_id($wall_wp);
          if ($dst_phpbb > 0 && ia_user_rel_is_blocked_any($actor_phpbb, $dst_phpbb)) continue;
        } catch (Throwable $e) {}
      }

      $wall_phpbb = 0;
      if (function_exists('ia_connect_user_phpbb_id')) {
        try { $wall_phpbb = (int)ia_connect_user_phpbb_id($wall_wp); } catch (Throwable $e) { $wall_phpbb = 0; }
      }

      $wpdb->insert($connect_posts, [
        'wall_owner_wp_id' => $wall_wp,
        'wall_owner_phpbb_id' => $wall_phpbb,
        'author_wp_id' => $actor_wp,
        'author_phpbb_id' => $actor_phpbb,
        'type' => 'status',
        'parent_post_id' => 0,
        'shared_tab' => 'discuss',
        'shared_ref' => $shared_ref,
        'title' => $topic_title,
        'body' => $body,
        'created_at' => $now,
        'updated_at' => $now,
        'status' => 'publish',
      ], ['%d','%d','%d','%d','%s','%d','%s','%s','%s','%s','%s','%s','%s']);

      $pid = (int)$wpdb->insert_id;
      if ($pid > 0 && $first_created <= 0) $first_created = $pid;
    }

    $connect_post_id = (int)$first_created;
    if ($connect_post_id <= 0) ia_discuss_json_err('Share failed', 500);

    // Fire a notification hook for the original Discuss author.
    try {
      do_action('ia_discuss_post_shared_to_connect', $topic_id, $post_id, $connect_post_id, $actor_phpbb, $orig_author_phpbb);
    } catch (Throwable $e) {}

    ia_discuss_json_ok(['connect_post_id' => $connect_post_id, 'discuss_url' => $discuss_url]);
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


  public function ajax_report_post(): void {
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    $post_id = (int)($_POST['post_id'] ?? 0);
    if ($post_id <= 0) ia_discuss_json_err('Missing post_id', 400);

    $viewer = (int)$this->auth->current_phpbb_user_id();
    if ($viewer <= 0) ia_discuss_json_err('Not logged in', 401);

    try {
      $db = $this->phpbb->db();
      $posts = $this->phpbb->table('posts');
      $topics = $this->phpbb->table('topics');
      $forums = $this->phpbb->table('forums');
      $sql = "SELECT p.post_id, p.topic_id, p.forum_id, p.poster_id, t.topic_title, f.forum_name
              FROM {$posts} p
              LEFT JOIN {$topics} t ON t.topic_id = p.topic_id
              LEFT JOIN {$forums} f ON f.forum_id = p.forum_id
              WHERE p.post_id = %d LIMIT 1";
      $row = $db ? $db->get_row($db->prepare($sql, $post_id), ARRAY_A) : null;
      if (!$row) ia_discuss_json_err('Post not found', 404);

      $forum_id = (int)($row['forum_id'] ?? 0);
      $topic_id = (int)($row['topic_id'] ?? 0);
      $poster_id = (int)($row['poster_id'] ?? 0);
      if ($forum_id <= 0 || $topic_id <= 0) ia_discuss_json_err('Post not found', 404);
      if (!$this->privacy->user_has_access($viewer, $forum_id)) ia_discuss_json_err('Forbidden', 403);

      $report_id = $this->reports->create_report($forum_id, $topic_id, $post_id, $viewer, $poster_id);
      if ($report_id <= 0) ia_discuss_json_err('Report failed', 500);

      $forum_name = (string)($row['forum_name'] ?? 'an Agora');
      $topic_title = (string)($row['topic_title'] ?? 'a topic');
      $reporter_name = ia_discuss_display_name_from_phpbb($viewer, 'A user');
      $url = home_url('/?tab=discuss&ia_tab=discuss&iad_view=topic&iad_topic=' . $topic_id . '&iad_post=' . $post_id . '&iad_report=' . $report_id);
      $meta = ['forum_id' => $forum_id, 'topic_id' => $topic_id, 'post_id' => $post_id, 'report_id' => $report_id];

      $recipients = [];
      if (function_exists('current_user_can') && current_user_can('manage_options')) {
        // no-op; recipient collection is based on WP admins below
      }
      try {
        $admins = get_users(['role' => 'administrator', 'fields' => ['user_email']]);
        $emails = [];
        foreach ((array)$admins as $u) {
          if (is_object($u) && !empty($u->user_email)) $emails[] = (string)$u->user_email;
        }
        $emails = array_values(array_unique(array_filter($emails)));
        if ($emails && $db) {
          $place = implode(',', array_fill(0, count($emails), '%s'));
          $ids = $db->get_col($db->prepare("SELECT user_id FROM " . $this->phpbb->prefix() . "users WHERE user_email IN ({$place})", ...$emails));
          foreach ((array)$ids as $id) { $id = (int)$id; if ($id > 0) $recipients[$id] = true; }
        }
      } catch (Throwable $e) {}
      try {
        $mods = method_exists($this->phpbb, 'list_forum_moderator_ids') ? $this->phpbb->list_forum_moderator_ids($forum_id) : [];
        foreach ((array)$mods as $id) { $id = (int)$id; if ($id > 0) $recipients[$id] = true; }
      } catch (Throwable $e) {}
      unset($recipients[$viewer]);

      if (function_exists('ia_notify_insert')) {
        foreach (array_keys($recipients) as $recipient_id) {
          ia_notify_insert([
            'recipient_phpbb_id' => (int)$recipient_id,
            'actor_phpbb_id' => $viewer,
            'type' => 'discuss_post_reported',
            'object_type' => 'discuss_post_reported',
            'object_id' => $report_id,
            'url' => $url,
            'text' => $reporter_name . ' reported a post in ' . $forum_name . '. Admin intervention is for spam, gore or porn only.',
            'meta' => $meta,
          ]);
        }
      }

      ia_discuss_json_ok(['report_id' => $report_id, 'forum_id' => $forum_id, 'topic_id' => $topic_id, 'post_id' => $post_id, 'topic_title' => $topic_title]);
    } catch (Throwable $e) {
      ia_discuss_json_err('Report error: ' . $e->getMessage(), 500);
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
