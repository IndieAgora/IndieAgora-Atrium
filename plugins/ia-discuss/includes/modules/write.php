<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Module_Write implements IA_Discuss_Module_Interface {

  private $phpbb;
  private $bbcode;
  private $auth;
  private $write;

  public function __construct(
    IA_Discuss_Service_PhpBB $phpbb,
    IA_Discuss_Render_BBCode $bbcode,
    IA_Discuss_Service_Auth $auth,
    IA_Discuss_Service_PhpBB_Write $write
  ) {
    $this->phpbb  = $phpbb;
    $this->bbcode = $bbcode;
    $this->auth   = $auth;
    $this->write  = $write;
  }

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_discuss_forum_meta' => ['method' => 'ajax_forum_meta', 'public' => true],
      'ia_discuss_new_topic'  => ['method' => 'ajax_new_topic',  'public' => false],
      'ia_discuss_reply'      => ['method' => 'ajax_reply',      'public' => false],
    ];
  }

  public function ajax_forum_meta(): void {
    $forum_id = isset($_POST['forum_id']) ? (int)$_POST['forum_id'] : 0;
    if ($forum_id <= 0) ia_discuss_json_err('Missing forum_id', 400);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    try {
      $row = $this->phpbb->get_forum_row($forum_id);

      ia_discuss_json_ok([
        'forum_id' => (int)$row['forum_id'],
        'forum_name' => (string)$row['forum_name'],
        'forum_desc_html' => $this->bbcode->format_post_html((string)($row['forum_desc'] ?? '')),
        'forum_posts' => (int)($row['forum_posts'] ?? 0),
        'forum_topics' => (int)($row['forum_topics_real'] ?? ($row['forum_topics'] ?? 0)),
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

    $title = sanitize_text_field($title);
    $body  = trim($body); // keep formatting

    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    try {
      $out = $this->write->create_topic($forum_id, $title, $body);
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
      ia_discuss_json_ok($out);
    } catch (Throwable $e) {
      ia_discuss_json_err('Reply error: ' . $e->getMessage(), 500);
    }
  }
}
