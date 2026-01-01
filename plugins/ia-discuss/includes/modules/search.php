<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Module_Search implements IA_Discuss_Module_Interface {

  private $phpbb;

  public function __construct(IA_Discuss_Service_PhpBB $phpbb) {
    $this->phpbb = $phpbb;
  }

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_discuss_search_suggest' => ['method' => 'ajax_suggest', 'public' => true],
      'ia_discuss_search'         => ['method' => 'ajax_search',  'public' => true],
    ];
  }

  private function norm_q(string $q): string {
    $q = trim($q);
    $q = preg_replace('/\s+/', ' ', $q);
    return (string)$q;
  }

  private function clip(string $s, int $n = 160): string {
    $s = trim(preg_replace('/\s+/', ' ', $s));
    if (function_exists('mb_substr')) {
      return (mb_strlen($s) > $n) ? (mb_substr($s, 0, $n) . '…') : $s;
    }
    return (strlen($s) > $n) ? (substr($s, 0, $n) . '…') : $s;
  }

  /**
   * Suggestions dropdown (fast, small limits).
   */
  public function ajax_suggest(): void {
    $q = isset($_POST['q']) ? (string)$_POST['q'] : '';
    $q = $this->norm_q($q);

    if ($q === '' || strlen($q) < 2) {
      ia_discuss_json_ok([
        'q' => $q,
        'users'  => [],
        'agoras' => [],
        'topics' => [],
        'replies'=> [],
      ]);
    }

    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    $db = $this->phpbb->db();
    $p  = $this->phpbb->prefix();
    if (!$db) ia_discuss_json_err('phpBB DB not available', 503);

    $like = '%' . $db->esc_like($q) . '%';

    // USERS
    $users = [];
    try {
      $sql = "SELECT user_id, username
              FROM {$p}users
              WHERE username LIKE %s
              ORDER BY username ASC
              LIMIT 6";
      $rows = $db->get_results($db->prepare($sql, $like), ARRAY_A);
      if (is_array($rows)) {
        foreach ($rows as $r) {
          $users[] = [
            'user_id' => (int)($r['user_id'] ?? 0),
            'username'=> (string)($r['username'] ?? ''),
            'avatar_url' => '', // Connect can supply later
          ];
        }
      }
    } catch (\Throwable $e) {}

    // AGORAS (forums)
    $agoras = [];
    try {
      $sql = "SELECT forum_id, forum_name
              FROM {$p}forums
              WHERE forum_name LIKE %s
              ORDER BY forum_name ASC
              LIMIT 6";
      $rows = $db->get_results($db->prepare($sql, $like), ARRAY_A);
      if (is_array($rows)) {
        foreach ($rows as $r) {
          $agoras[] = [
            'forum_id' => (int)($r['forum_id'] ?? 0),
            'forum_name' => (string)($r['forum_name'] ?? ''),
          ];
        }
      }
    } catch (\Throwable $e) {}

    // TOPICS
    $topics = [];
    try {
      $sql = "SELECT t.topic_id, t.forum_id, f.forum_name, t.topic_title, t.topic_time
              FROM {$p}topics t
              LEFT JOIN {$p}forums f ON f.forum_id = t.forum_id
              WHERE t.topic_visibility = 1
                AND t.topic_title LIKE %s
              ORDER BY t.topic_last_post_time DESC
              LIMIT 6";
      $rows = $db->get_results($db->prepare($sql, $like), ARRAY_A);
      if (is_array($rows)) {
        foreach ($rows as $r) {
          $topics[] = [
            'topic_id'    => (int)($r['topic_id'] ?? 0),
            'forum_id'    => (int)($r['forum_id'] ?? 0),
            'forum_name'  => (string)($r['forum_name'] ?? ''),
            'topic_title' => (string)($r['topic_title'] ?? ''),
            'topic_time'  => (int)($r['topic_time'] ?? 0),
          ];
        }
      }
    } catch (\Throwable $e) {}

    // REPLIES (posts)
    $replies = [];
    try {
      $sql = "SELECT p.post_id, p.topic_id, t.forum_id, f.forum_name, t.topic_title,
                     p.post_time, u.username, p.post_text
              FROM {$p}posts p
              JOIN {$p}topics t ON t.topic_id = p.topic_id
              LEFT JOIN {$p}forums f ON f.forum_id = t.forum_id
              LEFT JOIN {$p}users u ON u.user_id = p.poster_id
              WHERE t.topic_visibility = 1
                AND p.post_text LIKE %s
              ORDER BY p.post_time DESC
              LIMIT 6";
      $rows = $db->get_results($db->prepare($sql, $like), ARRAY_A);
      if (is_array($rows)) {
        foreach ($rows as $r) {
          $replies[] = [
            'post_id'     => (int)($r['post_id'] ?? 0),
            'topic_id'    => (int)($r['topic_id'] ?? 0),
            'forum_id'    => (int)($r['forum_id'] ?? 0),
            'forum_name'  => (string)($r['forum_name'] ?? ''),
            'topic_title' => (string)($r['topic_title'] ?? ''),
            'username'    => (string)($r['username'] ?? ''),
            'post_time'   => (int)($r['post_time'] ?? 0),
            'snippet'     => $this->clip((string)($r['post_text'] ?? ''), 140),
          ];
        }
      }
    } catch (\Throwable $e) {}

    ia_discuss_json_ok([
      'q'       => $q,
      'users'   => $users,
      'agoras'  => $agoras,
      'topics'  => $topics,
      'replies' => $replies,
    ]);
  }

  /**
   * Results page (tabbed). Returns one type at a time.
   * type: users|agoras|topics|replies
   */
  public function ajax_search(): void {
    $q = isset($_POST['q']) ? (string)$_POST['q'] : '';
    $q = $this->norm_q($q);

    $type   = isset($_POST['type']) ? (string)$_POST['type'] : 'topics';
    $offset = isset($_POST['offset']) ? max(0, (int)$_POST['offset']) : 0;
    $limit  = isset($_POST['limit']) ? max(1, min(50, (int)$_POST['limit'])) : 25;

    if ($q === '' || strlen($q) < 2) ia_discuss_json_err('Query too short', 400);
    if (!$this->phpbb->is_ready()) ia_discuss_json_err('phpBB adapter not available', 503);

    $db = $this->phpbb->db();
    $p  = $this->phpbb->prefix();
    if (!$db) ia_discuss_json_err('phpBB DB not available', 503);

    $like = '%' . $db->esc_like($q) . '%';

    $items = [];
    $has_more = 0;

    try {

      if ($type === 'users') {
        $sql = "SELECT user_id, username
                FROM {$p}users
                WHERE username LIKE %s
                ORDER BY username ASC
                LIMIT %d OFFSET %d";
        $rows = $db->get_results($db->prepare($sql, $like, $limit + 1, $offset), ARRAY_A);
        if (is_array($rows) && count($rows) > $limit) { $has_more = 1; $rows = array_slice($rows, 0, $limit); }
        foreach (($rows ?: []) as $r) {
          $items[] = [
            'user_id' => (int)($r['user_id'] ?? 0),
            'username'=> (string)($r['username'] ?? ''),
            'avatar_url' => '',
          ];
        }
      }

      else if ($type === 'agoras') {
        $sql = "SELECT forum_id, forum_name, forum_desc
                FROM {$p}forums
                WHERE forum_name LIKE %s
                ORDER BY forum_name ASC
                LIMIT %d OFFSET %d";
        $rows = $db->get_results($db->prepare($sql, $like, $limit + 1, $offset), ARRAY_A);
        if (is_array($rows) && count($rows) > $limit) { $has_more = 1; $rows = array_slice($rows, 0, $limit); }
        foreach (($rows ?: []) as $r) {
          $items[] = [
            'forum_id'   => (int)($r['forum_id'] ?? 0),
            'forum_name' => (string)($r['forum_name'] ?? ''),
            'forum_desc' => $this->clip((string)($r['forum_desc'] ?? ''), 160),
          ];
        }
      }

      else if ($type === 'topics') {
        $sql = "SELECT t.topic_id, t.forum_id, f.forum_name, t.topic_title, t.topic_time,
                       u.username AS topic_poster_username,
                       p1.post_text
                FROM {$p}topics t
                LEFT JOIN {$p}forums f ON f.forum_id = t.forum_id
                LEFT JOIN {$p}users u ON u.user_id = t.topic_poster
                LEFT JOIN {$p}posts p1 ON p1.post_id = t.topic_first_post_id
                WHERE t.topic_visibility = 1
                  AND (t.topic_title LIKE %s OR p1.post_text LIKE %s)
                ORDER BY t.topic_last_post_time DESC
                LIMIT %d OFFSET %d";
        $rows = $db->get_results($db->prepare($sql, $like, $like, $limit + 1, $offset), ARRAY_A);
        if (is_array($rows) && count($rows) > $limit) { $has_more = 1; $rows = array_slice($rows, 0, $limit); }
        foreach (($rows ?: []) as $r) {
          $items[] = [
            'topic_id'    => (int)($r['topic_id'] ?? 0),
            'forum_id'    => (int)($r['forum_id'] ?? 0),
            'forum_name'  => (string)($r['forum_name'] ?? ''),
            'topic_title' => (string)($r['topic_title'] ?? ''),
            'username'    => (string)($r['topic_poster_username'] ?? ''),
            'topic_time'  => (int)($r['topic_time'] ?? 0),
            'snippet'     => $this->clip((string)($r['post_text'] ?? ''), 180),
          ];
        }
      }

      else { // replies
        $sql = "SELECT p.post_id, p.topic_id, t.forum_id, f.forum_name, t.topic_title,
                       p.post_time, u.username, p.post_text
                FROM {$p}posts p
                JOIN {$p}topics t ON t.topic_id = p.topic_id
                LEFT JOIN {$p}forums f ON f.forum_id = t.forum_id
                LEFT JOIN {$p}users u ON u.user_id = p.poster_id
                WHERE t.topic_visibility = 1
                  AND p.post_text LIKE %s
                ORDER BY p.post_time DESC
                LIMIT %d OFFSET %d";
        $rows = $db->get_results($db->prepare($sql, $like, $limit + 1, $offset), ARRAY_A);
        if (is_array($rows) && count($rows) > $limit) { $has_more = 1; $rows = array_slice($rows, 0, $limit); }
        foreach (($rows ?: []) as $r) {
          $items[] = [
            'post_id'     => (int)($r['post_id'] ?? 0),
            'topic_id'    => (int)($r['topic_id'] ?? 0),
            'forum_id'    => (int)($r['forum_id'] ?? 0),
            'forum_name'  => (string)($r['forum_name'] ?? ''),
            'topic_title' => (string)($r['topic_title'] ?? ''),
            'username'    => (string)($r['username'] ?? ''),
            'post_time'   => (int)($r['post_time'] ?? 0),
            'snippet'     => $this->clip((string)($r['post_text'] ?? ''), 180),
          ];
        }
      }

    } catch (\Throwable $e) {
      ia_discuss_json_err('Search error: ' . $e->getMessage(), 500);
    }

    ia_discuss_json_ok([
      'q'        => $q,
      'type'     => $type,
      'offset'   => $offset,
      'limit'    => $limit,
      'has_more' => $has_more ? 1 : 0,
      'items'    => $items,
    ]);
  }
}
