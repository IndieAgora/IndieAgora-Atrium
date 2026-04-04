<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Module_Feed implements IA_Discuss_Module_Interface {

  private $phpbb;
  private $bbcode;
  private $media;
  private $atts;
  private $privacy;
  private $auth;

  public function __construct(
    IA_Discuss_Service_PhpBB $phpbb,
    IA_Discuss_Render_BBCode $bbcode,
    IA_Discuss_Render_Media $media,
    IA_Discuss_Render_Attachments $atts,
    IA_Discuss_Service_Auth $auth,
    IA_Discuss_Service_Agora_Privacy $privacy
  ) {
    $this->phpbb  = $phpbb;
    $this->bbcode = $bbcode;
    $this->media  = $media;
    $this->atts   = $atts;
    $this->auth   = $auth;
    $this->privacy = $privacy;
  }

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_discuss_feed' => ['method' => 'ajax_feed', 'public' => true],
      'ia_discuss_random_topic' => ['method' => 'ajax_random_topic', 'public' => true],
    ];
  }

  public function ajax_random_topic(): void {
    $tab   = isset($_POST['tab']) ? sanitize_key((string)$_POST['tab']) : 'new_posts';
    $q     = isset($_POST['q']) ? sanitize_text_field((string)$_POST['q']) : '';
    $forum = isset($_POST['forum_id']) ? max(0, (int)$_POST['forum_id']) : 0;

    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available (check IA Engine creds)', 503);

    try {
      $tries = 0;
      $tid = 0;
      while ($tries < 12) {
        $cand = (int)$this->phpbb->get_random_topic_id($tab, $q, $forum);
        if ($cand <= 0) break;
        try {
          $topic = $this->phpbb->get_topic_row($cand);
          $cand_forum_id = (int)($topic['forum_id'] ?? 0);
          $viewer = (int)$this->auth->current_phpbb_user_id();
          if ($this->privacy->user_has_access($viewer, $cand_forum_id)) { $tid = $cand; break; }
        } catch (Throwable $e2) {}
        $tries++;
      }
    } catch (Throwable $e) {
      ia_discuss_json_err($e->getMessage(), 500);
    }

    if (!$tid) ia_discuss_json_err('No matching topic found', 404);

    ia_discuss_json_ok([
      'topic_id' => (int)$tid,
    ]);
  }

  public function ajax_feed(): void {
    $tab    = isset($_POST['tab']) ? sanitize_key((string)$_POST['tab']) : 'new_posts';
    $page   = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 0;
    $offset = isset($_POST['offset']) ? max(0, (int)$_POST['offset']) : 0;
    $q      = isset($_POST['q']) ? sanitize_text_field((string)$_POST['q']) : '';
    $forum  = isset($_POST['forum_id']) ? max(0, (int)$_POST['forum_id']) : 0;
    $order  = isset($_POST['order']) ? sanitize_key((string)$_POST['order']) : '';

    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available (check IA Engine creds)', 503);

    $limit = 20;
    if ($page > 0) {
      $offset = max(0, ($page - 1) * $limit);
    } else {
      $page = (int) floor($offset / $limit) + 1;
    }

    $viewer = (int)$this->auth->current_phpbb_user_id();
    $blocked_ids = $viewer > 0 ? ia_user_rel_blocked_ids_for($viewer) : [];

    $allowed_forum_ids = [];
    if ($forum <= 0) {
      foreach ((array)$this->phpbb->list_forum_ids() as $candidate_forum_id) {
        $candidate_forum_id = (int)$candidate_forum_id;
        if ($candidate_forum_id > 0 && $this->privacy->user_has_access($viewer, $candidate_forum_id)) {
          $allowed_forum_ids[] = $candidate_forum_id;
        }
      }
    }

    $total_count = $this->phpbb->count_feed_rows($tab, $q, $forum, $order, $allowed_forum_ids, $blocked_ids);
    $total_pages = $total_count > 0 ? (int) ceil($total_count / $limit) : 0;

    if ($total_pages > 0 && $page > $total_pages) {
      $page = $total_pages;
      $offset = max(0, ($page - 1) * $limit);
    }

    $rows = $this->phpbb->get_feed_rows($tab, $offset, $limit, $q, $forum, $order, $allowed_forum_ids, $blocked_ids);

    $items = [];
    foreach ($rows as $r) {
      $approved = (int)($r['topic_posts_approved'] ?? 0);
      $replies  = max(0, $approved - 1);

      $text_raw = (string)($r['post_text'] ?? '');
      $text     = $this->atts->strip_payload($text_raw);

      $attachments = $this->atts->extract($text_raw);
      $media = $this->media->extract_media($text);

      $items[] = [
        'topic_id'        => (int)($r['topic_id'] ?? 0),
        'first_post_id'   => (int)($r['topic_first_post_id'] ?? 0),
        'last_post_id'    => (int)($r['topic_last_post_id'] ?? 0),
        'forum_id'        => (int)($r['forum_id'] ?? 0),
        'forum_name'      => (string)($r['forum_name'] ?? ''),
        'topic_title'     => (string)($r['topic_title'] ?? ''),
        'topic_time'      => (int)($r['topic_time'] ?? 0),
        'last_post_time'  => (int)($r['topic_last_post_time'] ?? 0),
        'replies'         => $replies,
        'views'           => (int)($r['topic_views'] ?? 0),

        'topic_poster_id'       => (int)($r['topic_poster'] ?? 0),
        'topic_poster_username' => (string)($r['topic_poster_username'] ?? ''),
        'topic_poster_display'  => ia_discuss_display_name_from_phpbb((int)($r['topic_poster'] ?? 0), (string)($r['topic_poster_username'] ?? '')),

        'topic_poster_avatar_url' => ia_discuss_avatar_url_from_phpbb((int)($r['topic_poster'] ?? 0), 34),

        'last_poster_id'        => (int)($r['topic_last_poster_id'] ?? 0),
        'last_poster_username'  => (string)($r['last_poster_username'] ?? ''),
        'last_poster_display'   => ia_discuss_display_name_from_phpbb((int)($r['topic_last_poster_id'] ?? 0), (string)($r['last_poster_username'] ?? '')),

        'last_poster_avatar_url'  => ia_discuss_avatar_url_from_phpbb((int)($r['topic_last_poster_id'] ?? 0), 34),

        'excerpt_html'    => $this->bbcode->excerpt_html($text, 260),
        'forum_is_private' => $this->privacy->is_private((int)($r['forum_id'] ?? 0)) ? 1 : 0,
        'media'           => [
          'video_url'   => $media['video_url'],
          'urls'        => $media['urls'],
          'attachments' => $attachments,
        ],
      ];
    }

    $has_more = ($offset + count($items)) < $total_count;

    ia_discuss_json_ok([
      'tab'         => $tab,
      'page'        => $page,
      'current_page'=> $page,
      'offset'      => $offset,
      'limit'       => $limit,
      'next_offset' => $offset + count($items),
      'has_more'    => $has_more ? 1 : 0,
      'forum_id'    => $forum,
      'total_count' => $total_count,
      'total_pages' => $total_pages,
      'items'       => $items,
    ]);
  }
}
