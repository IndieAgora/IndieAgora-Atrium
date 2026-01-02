<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Module_Agoras implements IA_Discuss_Module_Interface {

  private $phpbb;
  private $bbcode;

  public function __construct(IA_Discuss_Service_PhpBB $phpbb, IA_Discuss_Render_BBCode $bbcode) {
    $this->phpbb  = $phpbb;
    $this->bbcode = $bbcode;
  }

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_discuss_agoras' => ['method' => 'ajax_agoras', 'public' => true],
    ];
  }

  public function ajax_agoras(): void {
    $offset = isset($_POST['offset']) ? max(0, (int)$_POST['offset']) : 0;
    $q      = isset($_POST['q']) ? sanitize_text_field((string)$_POST['q']) : '';

    if (!$this->phpbb->is_ready()) {
      ia_discuss_json_err('phpBB adapter not available (check IA Engine creds)', 503);
    }

    $limit = 50;
    $rows = $this->phpbb->get_agoras_rows($offset, $limit + 1, $q);

    $has_more = count($rows) > $limit;
    if ($has_more) $rows = array_slice($rows, 0, $limit);

    $items = [];
    foreach ($rows as $r) {
      $items[] = [
        'forum_id'        => (int)($r['forum_id'] ?? 0),
        'parent_id'       => (int)($r['parent_id'] ?? 0),
        'forum_type'      => (int)($r['forum_type'] ?? 0),
        'forum_name'      => (string)($r['forum_name'] ?? ''),
        'forum_desc_html' => $this->bbcode->excerpt_html((string)($r['forum_desc'] ?? ''), 170),
        'topics'          => (int)($r['topics_count'] ?? 0),
        'posts'           => (int)($r['posts_count'] ?? 0),
      ];
    }

    ia_discuss_json_ok([
      'offset'      => $offset,
      'limit'       => $limit,
      'next_offset' => $offset + count($items),
      'has_more'    => $has_more ? 1 : 0,
      'items'       => $items,
    ]);
  }
}
