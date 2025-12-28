<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Module_Topic implements IA_Discuss_Module_Interface {

  private $phpbb;
  private $bbcode;
  private $media;
  private $atts;

  public function __construct(
    IA_Discuss_Service_PhpBB $phpbb,
    IA_Discuss_Render_BBCode $bbcode,
    IA_Discuss_Render_Media $media
  ) {
    $this->phpbb  = $phpbb;
    $this->bbcode = $bbcode;
    $this->media  = $media;
    $this->atts   = new IA_Discuss_Render_Attachments();
  }

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_discuss_topic'     => ['method' => 'ajax_topic', 'public' => true],
      'ia_discuss_mark_read' => ['method' => 'ajax_mark_read', 'public' => true],
    ];
  }

  public function ajax_topic(): void {
    $topic_id = isset($_POST['topic_id']) ? (int)$_POST['topic_id'] : 0;
    $offset   = isset($_POST['offset']) ? max(0, (int)$_POST['offset']) : 0;

    if ($topic_id <= 0) ia_discuss_json_err('Missing topic_id', 400);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    $topic = $this->phpbb->get_topic_row($topic_id);

    $limit = 25;
    $rows = $this->phpbb->get_topic_posts_rows($topic_id, $offset, $limit + 1);

    $has_more = count($rows) > $limit;
    if ($has_more) $rows = array_slice($rows, 0, $limit);

    $posts = [];
    foreach ($rows as $idx => $r) {
      $text_raw = (string)($r['post_text'] ?? '');
      $text     = $this->atts->strip_payload($text_raw);

      $posts[] = [
        'post_id'         => (int)($r['post_id'] ?? 0),
        'poster_id'       => (int)($r['poster_id'] ?? 0),
        'poster_username' => (string)($r['poster_username'] ?? ''),
        'post_time'       => (int)($r['post_time'] ?? 0),
        'content_html'    => $this->bbcode->format_post_html($text),
        'media'           => $this->media->extract_media($text),
        'collapsed_default' => ($offset === 0 && $idx >= 3) ? 1 : 0, // opening post + first 2 replies visible
      ];
    }

    ia_discuss_json_ok([
      'topic_id'       => (int)($topic['topic_id'] ?? 0),
      'topic_title'    => (string)($topic['topic_title'] ?? ''),
      'forum_id'       => (int)($topic['forum_id'] ?? 0),
      'forum_name'     => (string)($topic['forum_name'] ?? ''),
      'topic_time'     => (int)($topic['topic_time'] ?? 0),
      'last_post_time' => (int)($topic['topic_last_post_time'] ?? 0),
      'posts'          => $posts,
      'has_more'       => $has_more,
    ]);
  }

  public function ajax_mark_read(): void {
    ia_discuss_json_ok(['enabled' => false]);
  }
}
