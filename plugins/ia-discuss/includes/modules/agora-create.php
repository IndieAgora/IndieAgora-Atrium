<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Module_Agora_Create implements IA_Discuss_Module_Interface {

  private $phpbb;
  private $bbcode;
  private $auth;

  public function __construct(
    IA_Discuss_Service_PhpBB $phpbb,
    IA_Discuss_Render_BBCode $bbcode,
    IA_Discuss_Service_Auth $auth
  ) {
    $this->phpbb  = $phpbb;
    $this->bbcode = $bbcode;
    $this->auth   = $auth;
  }

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_discuss_create_agora' => ['method' => 'ajax_create_agora', 'public' => false],
    ];
  }

  public function ajax_create_agora(): void {
    if (!is_user_logged_in()) ia_discuss_json_err('Login required', 403);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    $title = isset($_POST['title']) ? (string) wp_unslash($_POST['title']) : '';
    $desc  = isset($_POST['desc'])  ? (string) wp_unslash($_POST['desc'])  : '';

    $title = trim($title);
    $desc  = trim($desc);

    if ($title === '') ia_discuss_json_err('Missing title', 400);
    if (mb_strlen($title) > 120) ia_discuss_json_err('Title too long', 400);
    if (mb_strlen($desc) > 4000) ia_discuss_json_err('Description too long', 400);

    // phpBB identity for current user
    $creator_id = (int) $this->auth->current_phpbb_user_id();
    if ($creator_id <= 0) ia_discuss_json_err('No phpBB identity for this user', 403);

    $db = $this->phpbb->db();
    $p  = $this->phpbb->prefix();

    // creator username for moderator cache
    $creator_name = (string) $db->get_var(
      $db->prepare("SELECT username FROM {$p}users WHERE user_id = %d LIMIT 1", $creator_id)
    );

    // ---- choose left/right ids (append to end of current tree) ----
    // This avoids 0/0 which can break any ORDER BY left_id usage.
    // We are NOT implementing full phpBB nested-set rebuild; just a safe append.
    $max_right = (int) $db->get_var("SELECT MAX(right_id) FROM {$p}forums");
    if ($max_right < 2) $max_right = 2;
    $left_id  = $max_right + 1;
    $right_id = $max_right + 2;

    // ---- build insert data ----
    $data = [
      'parent_id'     => 0,
      'left_id'       => $left_id,
      'right_id'      => $right_id,
      'forum_parents' => '',
      'forum_name'    => $title,
      'forum_desc'    => $desc,
      'forum_rules'   => '',
      'forum_type'    => 1, // normal forum (agora)
    ];

    // If schema has forum_desc_html, populate it too (best for your UI rendering).
    // (No fatal if column missing.)
    $has_desc_html = false;
    try {
      $cols = $db->get_col("SHOW COLUMNS FROM {$p}forums", 0);
      $has_desc_html = is_array($cols) && in_array('forum_desc_html', $cols, true);
    } catch (\Throwable $e) {}
    if ($has_desc_html) {
      $data['forum_desc_html'] = $this->bbcode->format_post_html($desc);
    }

    // Insert forum
    $ok = $db->insert("{$p}forums", $data);
    if (!$ok) ia_discuss_json_err('Failed to create agora', 500);

    $forum_id = (int) $db->insert_id;
    if ($forum_id <= 0) ia_discuss_json_err('Failed to create agora (no id)', 500);

    // Make creator a moderator (display + badge) via phpBB moderator cache
    $db->insert(
      "{$p}moderator_cache",
      [
        'forum_id' => $forum_id,
        'user_id'  => $creator_id,
        'username' => $creator_name ?: '',
      ],
      ['%d','%d','%s']
    );

    ia_discuss_json_ok([
      'forum_id'        => $forum_id,
      'forum_name'      => $title,
      'forum_desc_html' => $has_desc_html ? (string)$data['forum_desc_html'] : $this->bbcode->format_post_html($desc),
    ]);
  }
}
