<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Module_Topic implements IA_Discuss_Module_Interface {

  private $phpbb;
  private $bbcode;
  private $media;
  private $auth;
  private $atts;

  public function __construct(
    IA_Discuss_Service_PhpBB $phpbb,
    IA_Discuss_Render_BBCode $bbcode,
    IA_Discuss_Render_Media $media,
    IA_Discuss_Service_Auth $auth
  ) {
    $this->phpbb  = $phpbb;
    $this->bbcode = $bbcode;
    $this->media  = $media;
    $this->auth   = $auth;

    // stable attachments API
    $this->atts = new IA_Discuss_Render_Attachments();
  }

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_discuss_topic'     => ['method' => 'ajax_topic',     'public' => true],
      'ia_discuss_mark_read' => ['method' => 'ajax_mark_read', 'public' => true],
    ];
  }

  public function ajax_topic(): void {
    $topic_id = isset($_POST['topic_id']) ? (int)$_POST['topic_id'] : 0;
    // Topic view should load ALL posts in one go (no pagination / offsets).
    // Keep reading the legacy 'offset' param for backward compatibility, but ignore it.
    // (Some older JS still sends it.)
    $offset   = isset($_POST['offset']) ? max(0, (int)$_POST['offset']) : 0;

    if ($topic_id <= 0) ia_discuss_json_err('Missing topic_id', 400);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    $topic   = $this->phpbb->get_topic_row($topic_id);
    $forum_id = (int)($topic['forum_id'] ?? 0);

    // Fetch all topic posts. We page internally to avoid huge single queries,
    // but we return everything to the client.
    $rows = [];
    $chunk = 500;
    $max_posts = 20000; // safety cap to prevent runaway memory on pathological topics
    $off = 0;
    while (count($rows) < $max_posts) {
      $batch = $this->phpbb->get_topic_posts_rows($topic_id, $off, $chunk);
      if (!$batch || !is_array($batch) || count($batch) === 0) break;
      foreach ($batch as $br) {
        $rows[] = $br;
        if (count($rows) >= $max_posts) break;
      }
      if (count($batch) < $chunk) break;
      $off += $chunk;
    }

    $has_more = 0;

    // Total posts for pager / "last reply" jump
    $posts_total = 0;
    try {
      $db = $this->phpbb->db();
      $p  = $this->phpbb->prefix() . 'posts';
      if ($db) {
        $posts_total = (int)$db->get_var($db->prepare(
          "SELECT COUNT(*) FROM {$p} WHERE topic_id = %d AND post_visibility = 1",
          $topic_id
        ));
      }
    } catch (\Throwable $e) {
      $posts_total = 0;
    }

    // Viewer context
    $viewer_phpbb_id = (int)$this->auth->current_phpbb_user_id();
    $viewer_is_admin = (function_exists('current_user_can') && current_user_can('manage_options')) ? 1 : 0;

    // Moderator scope: forum-only unless WP admin
    $viewer_is_mod = ($viewer_phpbb_id > 0 && $forum_id > 0)
      ? ($this->phpbb->user_is_forum_moderator($viewer_phpbb_id, $forum_id) ? 1 : 0)
      : 0;
    if ($viewer_is_admin) $viewer_is_mod = 1;

    // Stable-style: map WP administrators -> phpBB user_ids by email
    $admin_phpbb_ids = [];
    try {
      $admins = get_users(['role' => 'administrator', 'fields' => ['user_email']]);
      $emails = [];
      if (is_array($admins)) {
        foreach ($admins as $u) {
          if (is_object($u) && !empty($u->user_email)) $emails[] = (string)$u->user_email;
        }
      }
      $emails = array_values(array_unique(array_filter($emails)));

      if ($emails) {
        $db = $this->phpbb->db();
        $p  = $this->phpbb->prefix();
        if ($db) {
          $place = implode(',', array_fill(0, count($emails), '%s'));
          $sql = "SELECT user_id FROM {$p}users WHERE user_email IN ({$place})";
          $prep = $db->prepare($sql, ...$emails);
          $ids = $db->get_col($prep);
          if (is_array($ids)) {
            foreach ($ids as $id) {
              $id = (int)$id;
              if ($id > 0) $admin_phpbb_ids[$id] = true;
            }
          }
        }
      }
    } catch (\Throwable $e) {
      $admin_phpbb_ids = [];
    }

    $posts = [];
    foreach ($rows as $r) {
      $post_id   = (int)($r['post_id'] ?? 0);
      $poster_id = (int)($r['poster_id'] ?? 0);
      $username  = (string)($r['poster_username'] ?? '');
      $post_time = (int)($r['post_time'] ?? 0);

      $text_raw = (string)($r['post_text'] ?? '');

      // attachments + strip payload markers (stable)
      $attachments = $this->atts->extract($text_raw);
      $text        = $this->atts->strip_payload($text_raw);

      // media from stripped text + attach attachments
      $media = $this->media->extract_media($text);
      if (is_array($media)) $media['attachments'] = $attachments;

      // badges for the AUTHOR of this post
      $is_admin_author = isset($admin_phpbb_ids[$poster_id]);
      $is_mod_author = ($forum_id > 0)
        ? $this->phpbb->user_is_forum_moderator($poster_id, $forum_id)
        : false;
      if ($is_admin_author) $is_mod_author = true;

      // viewer-relative permissions
      $viewer_can_moderate = ($viewer_is_admin || $viewer_is_mod) ? 1 : 0;
      $viewer_is_author    = ($viewer_phpbb_id > 0 && $viewer_phpbb_id === $poster_id) ? 1 : 0;

      $can_edit   = ($viewer_can_moderate || $viewer_is_author) ? 1 : 0;
      $can_delete = ($viewer_can_moderate) ? 1 : 0;
      $can_ban    = ($viewer_can_moderate && $poster_id > 0 && $poster_id !== $viewer_phpbb_id) ? 1 : 0;

      // Discuss-only: forum-level ban state (stored in WP table)
      // Used to toggle the moderator icon between "block" and "reinstate".
      $is_banned = 0;
      if ($forum_id > 0 && $poster_id > 0) {
        try {
          $is_banned = $this->phpbb->discuss_is_user_banned($forum_id, $poster_id) ? 1 : 0;
        } catch (\Throwable $e) {
          $is_banned = 0;
        }
      }

      $posts[] = [
        'post_id'         => $post_id,
        'poster_id'       => $poster_id,
        'poster_username' => $username,
        'post_time'       => $post_time,

        'content_html'    => $this->bbcode->format_post_html($text),
        'raw_text'        => $text_raw,
        'media'           => $media,

        'is_admin'        => $is_admin_author ? 1 : 0,
        'is_moderator'    => $is_mod_author ? 1 : 0,

        // viewer perms
        'can_edit'        => $can_edit,
        'can_delete'      => $can_delete,
        'can_ban'         => $can_ban,

        // viewer-relative moderation state for this Agora
        'is_banned'       => $is_banned,

        // needed for ban endpoint
        'forum_id'        => $forum_id,
      ];
    }

    ia_discuss_json_ok([
      'topic_id'       => (int)($topic['topic_id'] ?? $topic_id),
      'topic_title'    => (string)($topic['topic_title'] ?? ''),
      'forum_id'       => $forum_id,
      'forum_name'     => (string)($topic['forum_name'] ?? ''),
      'topic_time'     => (int)($topic['topic_time'] ?? 0),
      'last_post_time' => (int)($topic['topic_last_post_time'] ?? 0),

      'posts'          => $posts,
      'has_more'       => $has_more ? 1 : 0,
      'posts_total'    => $posts_total,

      'viewer'         => [
        'phpbb_user_id' => $viewer_phpbb_id,
        'is_admin'      => $viewer_is_admin,
        'is_mod'        => $viewer_is_mod,
      ],
    ]);
  }

  public function ajax_mark_read(): void {
    ia_discuss_json_ok(['enabled' => false]);
  }
}
