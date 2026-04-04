<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Service_Reports {

  private $phpbb;

  public function __construct(IA_Discuss_Service_PhpBB $phpbb) {
    $this->phpbb = $phpbb;
  }

  public function boot(): void {
    $this->ensure_table();
  }

  private function table(): string {
    global $wpdb;
    return $wpdb->prefix . 'ia_discuss_reports';
  }

  private function ensure_table(): void {
    global $wpdb;
    if (!$wpdb) return;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $t = $this->table();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$t} (
      report_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      forum_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
      topic_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
      post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
      reporter_phpbb_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
      reported_phpbb_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
      status VARCHAR(20) NOT NULL DEFAULT 'open',
      created_at INT(10) UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY (report_id),
      KEY forum_post (forum_id, post_id),
      KEY topic_post (topic_id, post_id),
      KEY status_created (status, created_at)
    ) {$charset};";
    dbDelta($sql);
  }

  public function create_report(int $forum_id, int $topic_id, int $post_id, int $reporter_phpbb_user_id, int $reported_phpbb_user_id = 0): int {
    global $wpdb;
    if (!$wpdb) return 0;
    $forum_id = (int)$forum_id;
    $topic_id = (int)$topic_id;
    $post_id = (int)$post_id;
    $reporter_phpbb_user_id = (int)$reporter_phpbb_user_id;
    $reported_phpbb_user_id = (int)$reported_phpbb_user_id;
    if ($forum_id <= 0 || $topic_id <= 0 || $post_id <= 0 || $reporter_phpbb_user_id <= 0) return 0;

    $wpdb->insert($this->table(), [
      'forum_id' => $forum_id,
      'topic_id' => $topic_id,
      'post_id' => $post_id,
      'reporter_phpbb_user_id' => $reporter_phpbb_user_id,
      'reported_phpbb_user_id' => $reported_phpbb_user_id,
      'status' => 'open',
      'created_at' => time(),
    ], ['%d','%d','%d','%d','%d','%s','%d']);
    return (int)$wpdb->insert_id;
  }

  public function get_report(int $report_id): array {
    global $wpdb;
    if (!$wpdb || $report_id <= 0) return [];
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE report_id=%d LIMIT 1", $report_id), ARRAY_A);
    return is_array($row) ? $row : [];
  }

  public function user_can_view_report(int $report_id, int $viewer_phpbb_user_id): bool {
    $viewer_phpbb_user_id = (int)$viewer_phpbb_user_id;
    if ($report_id <= 0) return false;
    if (function_exists('current_user_can') && current_user_can('manage_options')) return true;
    if ($viewer_phpbb_user_id <= 0) return false;
    $report = $this->get_report($report_id);
    if (!$report || (string)($report['status'] ?? '') !== 'open') return false;
    $forum_id = (int)($report['forum_id'] ?? 0);
    if ($forum_id <= 0) return false;
    if (function_exists('current_user_can') && current_user_can('manage_options')) return true;
    try {
      return $this->phpbb->user_is_forum_moderator($viewer_phpbb_user_id, $forum_id);
    } catch (Throwable $e) {
      return false;
    }
  }
}
