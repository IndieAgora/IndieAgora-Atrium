<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Module_Feed implements IA_Discuss_Module_Interface {

  private $phpbb;
  private $bbcode;
  private $media;
  private $atts;

  public function __construct(
    IA_Discuss_Service_PhpBB $phpbb,
    IA_Discuss_Render_BBCode $bbcode,
    IA_Discuss_Render_Media $media,
    IA_Discuss_Render_Attachments $atts
  ) {
    $this->phpbb  = $phpbb;
    $this->bbcode = $bbcode;
    $this->media  = $media;
    $this->atts   = $atts;
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
      $tid = $this->phpbb->get_random_topic_id($tab, $q, $forum);
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
    $offset = isset($_POST['offset']) ? max(0, (int)$_POST['offset']) : 0;
    $q      = isset($_POST['q']) ? sanitize_text_field((string)$_POST['q']) : '';
    $forum  = isset($_POST['forum_id']) ? max(0, (int)$_POST['forum_id']) : 0;

    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available (check IA Engine creds)', 503);

    $limit = 20;
    $rows = $this->phpbb->get_feed_rows($tab, $offset, $limit + 1, $q, $forum);

    $has_more = count($rows) > $limit;
    if ($has_more) $rows = array_slice($rows, 0, $limit);

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
        'forum_id'        => (int)($r['forum_id'] ?? 0),
        'forum_name'      => (string)($r['forum_name'] ?? ''),
        'topic_title'     => (string)($r['topic_title'] ?? ''),
        'topic_time'      => (int)($r['topic_time'] ?? 0),
        'last_post_time'  => (int)($r['topic_last_post_time'] ?? 0),
        'replies'         => $replies,
        'views'           => (int)($r['topic_views'] ?? 0),

        'topic_poster_id'       => (int)($r['topic_poster'] ?? 0),
        'topic_poster_username' => (string)($r['topic_poster_username'] ?? ''),

        'last_poster_id'        => (int)($r['topic_last_poster_id'] ?? 0),
        'last_poster_username'  => (string)($r['last_poster_username'] ?? ''),

        'excerpt_html'    => $this->bbcode->excerpt_html($text, 260),
        'media'           => [
          'video_url'   => $media['video_url'],
          'urls'        => $media['urls'],
          'attachments' => $attachments,
        ],
      ];
    }

    ia_discuss_json_ok([
      'tab'       => $tab,
      'offset'    => $offset,
      'limit'     => $limit,
      'next_offset' => $offset + count($items),
      'has_more'  => $has_more ? 1 : 0,
      'forum_id'  => $forum,
      'items'     => $items,
    ]);
  }
}
