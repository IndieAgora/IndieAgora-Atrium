<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Service_PhpBB {

  /** @var wpdb|null */
  private $db = null;

  /** @var string */
  private $prefix = 'phpbb_';

  /** @var string */
  public $last_query = '';

  /** @var array<string,mixed> Per-request cache (cheap). */
  private $cache = [];
  private $forum_counter_mode = null; // 'approved' | 'legacy'
  private $topic_last_post_id_mode = null; // 'supported' | 'unsupported'

  public function __construct() {
    $creds = $this->get_phpbb_creds();
    if (!$creds) return;

    $host = (string)($creds['host'] ?? DB_HOST);
    $user = (string)($creds['user'] ?? DB_USER);
    $pass = (string)($creds['pass'] ?? DB_PASSWORD);
    $name = (string)($creds['name'] ?? DB_NAME);

    $this->prefix = (string)($creds['prefix'] ?? 'phpbb_');

    $this->db = new wpdb($user, $pass, $name, $host);
    $this->db->show_errors(false);
    $this->db->suppress_errors(true);
    $this->db->query("SET NAMES utf8mb4");
  }

  public function is_ready(): bool {
    return ($this->db instanceof wpdb);
  }

  public function db(): ?wpdb {
    return ($this->db instanceof wpdb) ? $this->db : null;
  }

  public function prefix(): string {
    return (string) $this->prefix;
  }
  /**
   * Convenience helper used by write modules.
   * Returns a fully-qualified phpBB table name.
   */
  public function table(string $name): string {
    $name = trim($name);
    $name = ltrim($name, '_');
    return (string)$this->prefix . $name;
  }

  /**
   * Some phpBB installs expose forum counters as forum_posts/forum_topics,
   * others as forum_posts_approved/forum_topics_approved. Detect once.
   */
  private function detect_forum_counter_mode(): string {
    if ($this->forum_counter_mode === 'approved' || $this->forum_counter_mode === 'legacy') {
      return $this->forum_counter_mode;
    }
    if (!$this->db) {
      $this->forum_counter_mode = 'legacy';
      return $this->forum_counter_mode;
    }

    $f = $this->prefix . 'forums';
    try {
      $col = $this->db->get_var("SHOW COLUMNS FROM {$f} LIKE 'forum_posts_approved'");
      if (!empty($this->db->last_error)) {
        $this->forum_counter_mode = 'legacy';
        return $this->forum_counter_mode;
      }
      $this->forum_counter_mode = $col ? 'approved' : 'legacy';
    } catch (Throwable $e) {
      $this->forum_counter_mode = 'legacy';
    }
    return $this->forum_counter_mode;
  }

  /**
   * Some phpBB builds expose topics.topic_last_post_id.
   * Detect once and fall back when absent.
   */
  private function detect_topic_last_post_id_mode(): string {
    if ($this->topic_last_post_id_mode === 'supported' || $this->topic_last_post_id_mode === 'unsupported') {
      return $this->topic_last_post_id_mode;
    }
    if (!$this->db) {
      $this->topic_last_post_id_mode = 'unsupported';
      return $this->topic_last_post_id_mode;
    }

    $t = $this->prefix . 'topics';
    try {
      $col = $this->db->get_var("SHOW COLUMNS FROM {$t} LIKE 'topic_last_post_id'");
      if (!empty($this->db->last_error)) {
        $this->topic_last_post_id_mode = 'unsupported';
        return $this->topic_last_post_id_mode;
      }
      $this->topic_last_post_id_mode = $col ? 'supported' : 'unsupported';
    } catch (Throwable $e) {
      $this->topic_last_post_id_mode = 'unsupported';
    }
    return $this->topic_last_post_id_mode;
  }

  /**
   * SQL fragment for selecting forum counters as forum_posts/forum_topics.
   */
  public function forum_counter_select_sql(): string {
    $mode = $this->detect_forum_counter_mode();
    if ($mode === 'approved') {
      return "forum_posts_approved AS forum_posts, forum_topics_approved AS forum_topics";
    }
    return "forum_posts AS forum_posts, forum_topics AS forum_topics";
  }

  public function diagnostics(): array {
    return [
      'prefix' => $this->prefix,
      'db' => [
        'last_error' => $this->db ? $this->db->last_error : 'no-db',
      ],
    ];
  }

  public function probe(): array {
    if (!$this->db) return ['tables' => [], 'last_error' => 'no-db'];

    $t = $this->prefix . 'topics';
    $f = $this->prefix . 'forums';
    $p = $this->prefix . 'posts';
    $u = $this->prefix . 'users';

    return [
      'tables' => [
        'topics' => (bool)$this->db->get_var("SHOW TABLES LIKE '{$t}'"),
        'forums' => (bool)$this->db->get_var("SHOW TABLES LIKE '{$f}'"),
        'posts'  => (bool)$this->db->get_var("SHOW TABLES LIKE '{$p}'"),
        'users'  => (bool)$this->db->get_var("SHOW TABLES LIKE '{$u}'"),
      ],
      'last_error' => $this->db->last_error,
    ];
  }

  private function current_viewer_phpbb_user_id(): int {
    if (!is_user_logged_in()) return 0;

    $wp_uid = (int) get_current_user_id();
    if ($wp_uid > 0) {
      $meta = (int) get_user_meta($wp_uid, 'ia_phpbb_user_id', true);
      if ($meta > 0) return $meta;
    }

    $filtered = (int) apply_filters('ia_current_phpbb_user_id', 0, $wp_uid);
    if ($filtered > 0) return $filtered;

    return 0;
  }

  private function current_viewer_wp_user_id(): int {
    return is_user_logged_in() ? (int) get_current_user_id() : 0;
  }

  public function get_forum_row(int $forum_id): array {
    if (!$this->db) throw new Exception('phpBB adapter not available');

    $f = $this->prefix . 'forums';

    $counters = $this->forum_counter_select_sql();
    $sql = "
      SELECT forum_id, forum_name, forum_desc, forum_rules,
             {$counters}
      FROM {$f}
      WHERE forum_id = %d
      LIMIT 1
    ";

    $row = $this->db->get_row($this->db->prepare($sql, $forum_id), ARRAY_A);
    if (!empty($this->db->last_error)) throw new Exception('phpBB SQL error: ' . $this->db->last_error);
    if (!$row) throw new Exception('Forum not found');
    return $row;
  }


  public function list_forum_ids(): array {
    if (!$this->db) return [];
    $ck = 'forum_ids';
    if (isset($this->cache[$ck]) && is_array($this->cache[$ck])) return $this->cache[$ck];

    $f = $this->prefix . 'forums';
    $rows = $this->db->get_col("SELECT forum_id FROM {$f} WHERE forum_id > 0 ORDER BY left_id ASC");
    if (!empty($this->db->last_error)) return [];
    $ids = array_values(array_filter(array_map('intval', is_array($rows) ? $rows : []), function($n){ return $n > 0; }));
    $this->cache[$ck] = $ids;
    return $ids;
  }

  private function build_feed_query_parts(string $tab, string $q = '', int $forum_id = 0, string $order_key = '', array $allowed_forum_ids = [], array $excluded_topic_poster_ids = []): array {
    $tab      = $tab ?: 'new_posts';
    $forum_id = max(0, (int)$forum_id);

    $t = $this->prefix . 'topics';
    $p = $this->prefix . 'posts';

    $viewer_phpbb_id = $this->current_viewer_phpbb_user_id();
    $viewer_wp_user_id = $this->current_viewer_wp_user_id();

    $where = "WHERE 1=1";
    $args = [];
    $extra_joins = '';

    $where .= " AND t.topic_visibility = 1";

    if ($forum_id > 0) {
      $where .= " AND t.forum_id = %d";
      $args[] = $forum_id;
    } elseif (!empty($allowed_forum_ids)) {
      $allowed_forum_ids = array_values(array_filter(array_map('intval', $allowed_forum_ids), function($n){ return $n > 0; }));
      if (empty($allowed_forum_ids)) {
        $where .= " AND 1=0";
      } else {
        $placeholders = implode(',', array_fill(0, count($allowed_forum_ids), '%d'));
        $where .= " AND t.forum_id IN ({$placeholders})";
        foreach ($allowed_forum_ids as $allowed_forum_id) {
          $args[] = (int)$allowed_forum_id;
        }
      }
    }

    if (!empty($excluded_topic_poster_ids)) {
      $excluded_topic_poster_ids = array_values(array_filter(array_map('intval', $excluded_topic_poster_ids), function($n){ return $n > 0; }));
      if (!empty($excluded_topic_poster_ids)) {
        $placeholders = implode(',', array_fill(0, count($excluded_topic_poster_ids), '%d'));
        $where .= " AND t.topic_poster NOT IN ({$placeholders})";
        foreach ($excluded_topic_poster_ids as $excluded_topic_poster_id) {
          $args[] = (int)$excluded_topic_poster_id;
        }
      }
    }

    $last_post_supported = ($this->detect_topic_last_post_id_mode() === 'supported');
    $last_post_id_sql = $last_post_supported
      ? "t.topic_last_post_id"
      : "(SELECT MAX(p3.post_id) FROM {$p} p3 WHERE p3.topic_id = t.topic_id AND p3.post_visibility = 1)";

    $join_post_id_sql = ($tab === 'latest_replies') ? $last_post_id_sql : "t.topic_first_post_id";
    $order = "ORDER BY t.topic_last_post_time DESC";

    if ($tab === 'new_topics') {
      $order = "ORDER BY t.topic_time DESC";
    }

    if ($tab === 'no_replies') {
      $where .= " AND t.topic_posts_approved <= 1";
    }

    if ($tab === 'latest_replies') {
      $where .= " AND t.topic_posts_approved > 1";
    }

    if ($tab === 'my_topics') {
      if ($viewer_phpbb_id <= 0) {
        $where .= " AND 1=0";
      } else {
        $where .= " AND t.topic_poster = %d";
        $args[] = $viewer_phpbb_id;
        $order = "ORDER BY t.topic_time DESC";
      }
    }

    if ($tab === 'my_replies') {
      if ($viewer_phpbb_id <= 0) {
        $where .= " AND 1=0";
      } else {
        $reply_join = $this->db->prepare(
          "LEFT JOIN (
"
          . "  SELECT rp.topic_id, MAX(rp.post_id) AS user_last_post_id, MAX(rp.post_time) AS user_last_post_time
"
          . "  FROM {$p} rp
"
          . "  WHERE rp.poster_id = %d AND rp.post_visibility = 1
"
          . "  GROUP BY rp.topic_id
"
          . ") upr ON upr.topic_id = t.topic_id
",
          $viewer_phpbb_id
        );
        $extra_joins .= $reply_join;
        $where .= " AND upr.user_last_post_id IS NOT NULL AND upr.user_last_post_id <> t.topic_first_post_id";
        $join_post_id_sql = "upr.user_last_post_id";
        $order = "ORDER BY upr.user_last_post_time DESC";
      }
    }

    if ($tab === 'my_history') {
      if ($viewer_wp_user_id <= 0) {
        $where .= " AND 1=0";
      } else {
        $history_ids = ia_discuss_topic_history_ids($viewer_wp_user_id, 0, 500);
        if (empty($history_ids)) {
          $where .= " AND 1=0";
        } else {
          $placeholders = implode(',', array_fill(0, count($history_ids), '%d'));
          $where .= " AND t.topic_id IN ({$placeholders})";
          foreach ($history_ids as $history_id) {
            $args[] = (int)$history_id;
          }
          $order = 'ORDER BY FIELD(t.topic_id,' . implode(',', array_map('intval', $history_ids)) . ')';
        }
      }
    }

    if ($q !== '') {
      $where .= " AND (t.topic_title LIKE %s OR p.post_text LIKE %s)";
      $like = '%' . $this->db->esc_like($q) . '%';
      $args[] = $like;
      $args[] = $like;
    }

    $order_key = sanitize_key($order_key);
    if ($order_key === 'oldest') {
      $order = ($tab === 'my_topics') ? "ORDER BY t.topic_time ASC" : "ORDER BY t.topic_last_post_time ASC";
    } elseif ($order_key === 'most_replies') {
      $order = "ORDER BY t.topic_posts_approved DESC, t.topic_last_post_time DESC";
    } elseif ($order_key === 'least_replies') {
      $order = "ORDER BY t.topic_posts_approved ASC, t.topic_last_post_time DESC";
    } elseif ($order_key === 'created') {
      $order = "ORDER BY t.topic_time DESC";
    }

    return [
      'where' => $where,
      'args' => $args,
      'extra_joins' => $extra_joins,
      'join_post_id_sql' => $join_post_id_sql,
      'last_post_id_sql' => $last_post_id_sql,
      'order' => $order,
    ];
  }

  public function count_feed_rows(string $tab, string $q = '', int $forum_id = 0, string $order_key = '', array $allowed_forum_ids = [], array $excluded_topic_poster_ids = []): int {
    if (!$this->db) throw new Exception('phpBB adapter not available');

    $t = $this->prefix . 'topics';
    $p = $this->prefix . 'posts';

    $parts = $this->build_feed_query_parts($tab, $q, $forum_id, $order_key, $allowed_forum_ids, $excluded_topic_poster_ids);
    $where = $parts['where'];
    $args = $parts['args'];
    $extra_joins = $parts['extra_joins'];

    $sql = "
      SELECT COUNT(DISTINCT t.topic_id)
      FROM {$t} t
      {$extra_joins}
      LEFT JOIN {$p} p ON p.post_id = (" . $parts['join_post_id_sql'] . ")
      {$where}
    ";

    $prepared = $this->db->prepare($sql, ...$args);
    $count = (int)$this->db->get_var($prepared);
    if (!empty($this->db->last_error)) throw new Exception('phpBB SQL error: ' . $this->db->last_error);

    return max(0, $count);
  }

  public function get_feed_rows(string $tab, int $offset, int $limit, string $q = '', int $forum_id = 0, string $order_key = '', array $allowed_forum_ids = [], array $excluded_topic_poster_ids = []): array {
    if (!$this->db) throw new Exception('phpBB adapter not available');

    $offset = max(0, $offset);
    $limit = max(1, min(50, $limit));

    $t = $this->prefix . 'topics';
    $f = $this->prefix . 'forums';
    $p = $this->prefix . 'posts';
    $u = $this->prefix . 'users';

    $parts = $this->build_feed_query_parts($tab, $q, $forum_id, $order_key, $allowed_forum_ids, $excluded_topic_poster_ids);
    $where = $parts['where'];
    $args = $parts['args'];
    $extra_joins = $parts['extra_joins'];
    $join_post_id_sql = $parts['join_post_id_sql'];
    $last_post_id_sql = $parts['last_post_id_sql'];
    $order = $parts['order'];

    $sql = "
      SELECT
        t.topic_id,
        t.forum_id,
        f.forum_name,
        t.topic_title,
        t.topic_time,
        t.topic_last_post_time,
        t.topic_views,
        t.topic_posts_approved,
        t.topic_first_post_id,
        t.topic_poster,
        up.username AS topic_poster_username,
        t.topic_last_poster_id,
        ul.username AS last_poster_username,
        ({$last_post_id_sql}) AS topic_last_post_id,
        p.post_text
      FROM {$t} t
      LEFT JOIN {$f} f ON f.forum_id = t.forum_id
      {$extra_joins}
      LEFT JOIN {$p} p ON p.post_id = ({$join_post_id_sql})
      LEFT JOIN {$u} up ON up.user_id = t.topic_poster
      LEFT JOIN {$u} ul ON ul.user_id = t.topic_last_poster_id
      {$where}
      {$order}
      LIMIT %d OFFSET %d
    ";

    $args[] = $limit;
    $args[] = $offset;

    $prepared = $this->db->prepare($sql, ...$args);
    $this->last_query = $prepared;

    $rows = $this->db->get_results($prepared, ARRAY_A);
    if (!empty($this->db->last_error)) {
      throw new Exception('phpBB SQL error: ' . $this->db->last_error . ' | ' . $this->last_query);
    }

    if (is_array($rows)) {
      foreach ($rows as &$r) {
        if (isset($r['post_text']) && is_string($r['post_text'])) {
          $r['post_text'] = function_exists('wp_unslash') ? wp_unslash($r['post_text']) : stripslashes($r['post_text']);
        }
      }
      unset($r);
    }

    return is_array($rows) ? $rows : [];
  }

  public function get_random_topic_id(string $tab, string $q = '', int $forum_id = 0): int {
    if (!$this->db) throw new Exception('phpBB adapter not available');

    $tab = $tab ?: 'new_posts';
    $forum_id = max(0, (int)$forum_id);

    $t = $this->prefix . 'topics';
    $p = $this->prefix . 'posts';

    $where = "WHERE 1=1";
    $args = [];

    $where .= " AND t.topic_visibility = 1";

    if ($forum_id > 0) {
      $where .= " AND t.forum_id = %d";
      $args[] = $forum_id;
    }

    if ($q !== '') {
      // Join to first post text for simple keyword matching.
      $where .= " AND (t.topic_title LIKE %s OR p.post_text LIKE %s)";
      $like = '%' . $this->db->esc_like($q) . '%';
      $args[] = $like;
      $args[] = $like;
    }

    if ($tab === 'no_replies') {
      $where .= " AND t.topic_posts_approved <= 1";
    }

    $order = "ORDER BY t.topic_last_post_time DESC";
    if ($tab === 'new_topics') $order = "ORDER BY t.topic_time DESC";

    // Pull a reasonably small candidate set (latest N) and pick randomly in PHP.
    // This avoids expensive ORDER BY RAND() on large tables.
    $limit = 500;

    $sql = "
      SELECT t.topic_id
      FROM {$t} t
      LEFT JOIN {$p} p ON p.post_id = t.topic_first_post_id
      {$where}
      {$order}
      LIMIT %d
    ";

    $args[] = $limit;
    $prepared = $this->db->prepare($sql, ...$args);
    $this->last_query = $prepared;

    $rows = $this->db->get_results($prepared, ARRAY_A);
    if (!empty($this->db->last_error)) {
      throw new Exception('phpBB SQL error: ' . $this->db->last_error . ' | ' . $this->last_query);
    }

    if (!is_array($rows) || !count($rows)) return 0;

    $idx = random_int(0, count($rows) - 1);
    return (int)($rows[$idx]['topic_id'] ?? 0);
  }

  public function get_agoras_rows(int $offset, int $limit, string $q = '', string $order = ''): array {
    if (!$this->db) throw new Exception('phpBB adapter not available');

    $offset = max(0, $offset);
    $limit  = max(1, min(120, $limit));

    $f = $this->prefix . 'forums';

    $where = "WHERE 1=1";
    $args = [];

    if ($q !== '') {
      $where .= " AND forum_name LIKE %s";
      $args[] = '%' . $this->db->esc_like($q) . '%';
    }

    // Optional ordering for the Agoras list.
    // Default remains the existing left_id order unless an explicit order key is provided.
    $order = trim((string)$order);
    $ok = ['' => 1, 'newest' => 1, 'oldest' => 1, 'latest_topics' => 1, 'latest_replies' => 1, 'empty' => 1];
    if (!isset($ok[$order])) $order = '';

    if ($order === 'empty') {
      // Only forums with zero visible topics.
      $where .= " AND (SELECT COUNT(*) FROM {$this->prefix}topics tt WHERE tt.forum_id = {$f}.forum_id AND tt.topic_visibility = 1) = 0";
    }

    if ($order === 'latest_replies') {
      // Only forums that have at least one reply (exclude topics that only have the opening post).
      $where .= " AND EXISTS ("
        . "SELECT 1 "
        . "FROM {$this->prefix}posts pp2 "
        . "JOIN {$this->prefix}topics tt4 ON tt4.topic_id = pp2.topic_id "
        . "WHERE tt4.forum_id = {$f}.forum_id "
        . "AND tt4.topic_visibility = 1 "
        . "AND pp2.post_visibility = 1 "
        . "AND pp2.post_id <> tt4.topic_first_post_id "
        . "LIMIT 1"
        . ")";
    }

    $sql = "
      SELECT
        forum_id,
        parent_id,
        forum_type,
        forum_name,
        forum_desc,
        left_id,
        (
          SELECT COUNT(*) FROM {$this->prefix}topics tt WHERE tt.forum_id = {$f}.forum_id AND tt.topic_visibility = 1
        ) AS topics_count,
        (
          SELECT COUNT(*)
          FROM {$this->prefix}posts pp
          JOIN {$this->prefix}topics tt2 ON tt2.topic_id = pp.topic_id
          WHERE tt2.forum_id = {$f}.forum_id AND tt2.topic_visibility = 1
        ) AS posts_count,
        COALESCE((
          SELECT MAX(tt3.topic_time) FROM {$this->prefix}topics tt3 WHERE tt3.forum_id = {$f}.forum_id AND tt3.topic_visibility = 1
        ), 0) AS latest_topic_time,
        COALESCE((
          SELECT MAX(pp2.post_time)
          FROM {$this->prefix}posts pp2
          JOIN {$this->prefix}topics tt4 ON tt4.topic_id = pp2.topic_id
          WHERE tt4.forum_id = {$f}.forum_id AND tt4.topic_visibility = 1 AND pp2.post_visibility = 1
            AND pp2.post_id <> tt4.topic_first_post_id
        ), 0) AS latest_reply_time
      FROM {$f}
      {$where}
      ORDER BY
        CASE WHEN %s = 'latest_topics' THEN latest_topic_time END DESC,
        CASE WHEN %s = 'latest_replies' THEN latest_reply_time END DESC,
        CASE WHEN %s = 'newest' THEN forum_id END DESC,
        CASE WHEN %s = 'oldest' THEN forum_id END ASC,
        left_id ASC
      LIMIT %d OFFSET %d
    ";

    $args[] = $order;
    $args[] = $order;
    $args[] = $order;
    $args[] = $order;

    $args[] = $limit;
    $args[] = $offset;

    $prepared = $this->db->prepare($sql, ...$args);
    $this->last_query = $prepared;

    $rows = $this->db->get_results($prepared, ARRAY_A);
    if (!empty($this->db->last_error)) {
      throw new Exception('phpBB SQL error: ' . $this->db->last_error . ' | ' . $this->last_query);
    }

    return is_array($rows) ? $rows : [];
  }

  public function get_topic_row(int $topic_id): array {
    if (!$this->db) throw new Exception('phpBB adapter not available');

    $t = $this->prefix . 'topics';
    $f = $this->prefix . 'forums';

    $topicSql = "
      SELECT t.topic_id, t.forum_id, f.forum_name, t.topic_title, t.topic_time, t.topic_last_post_time
      FROM {$t} t
      LEFT JOIN {$f} f ON f.forum_id = t.forum_id
      WHERE t.topic_id = %d AND t.topic_visibility = 1
      LIMIT 1
    ";

    $topic = $this->db->get_row($this->db->prepare($topicSql, $topic_id), ARRAY_A);
    if (!empty($this->db->last_error)) throw new Exception('phpBB SQL error: ' . $this->db->last_error);
    if (!$topic) throw new Exception('Topic not found');
    return $topic;
  }

  public function get_topic_posts_rows(int $topic_id, int $offset, int $limit_plus_one): array {
    if (!$this->db) throw new Exception('phpBB adapter not available');

    $p = $this->prefix . 'posts';
    $u = $this->prefix . 'users';

    $postsSql = "
      SELECT
        p.post_id,
        p.poster_id,
        u.username AS poster_username,
        p.post_time,
        p.post_text
      FROM {$p} p
      LEFT JOIN {$u} u ON u.user_id = p.poster_id
      WHERE p.topic_id = %d AND p.post_visibility = 1
      ORDER BY p.post_time ASC
      LIMIT %d OFFSET %d
    ";

    $rows = $this->db->get_results(
      $this->db->prepare($postsSql, $topic_id, $limit_plus_one, $offset),
      ARRAY_A
    );

    if (!empty($this->db->last_error)) throw new Exception('phpBB SQL error: ' . $this->db->last_error);

    if (is_array($rows)) {
      foreach ($rows as &$r) {
        if (isset($r['post_text']) && is_string($r['post_text'])) {
          $r['post_text'] = function_exists('wp_unslash') ? wp_unslash($r['post_text']) : stripslashes($r['post_text']);
        }
      }
      unset($r);
    }

    return is_array($rows) ? $rows : [];
  }

  /**
   * True if user is a moderator for the given forum.
   *
   * IMPORTANT: We only rely on phpBB schema tables (no phpBB app/runtime).
   * We prefer phpbb_moderator_cache (fast path), but fall back to ACL (m_* on that forum).
   */
  public function user_is_forum_moderator(int $user_id, int $forum_id): bool {
    if (!$this->db) return false;
    $user_id = (int)$user_id;
    $forum_id = (int)$forum_id;
    if ($user_id <= 0 || $forum_id <= 0) return false;

    $ck = 'is_mod:' . $user_id . ':' . $forum_id;
    if (array_key_exists($ck, $this->cache)) return (bool)$this->cache[$ck];

    $m = $this->prefix . 'moderator_cache';
    $sql = "SELECT 1 FROM {$m} WHERE forum_id = %d AND user_id = %d LIMIT 1";
    $v = $this->db->get_var($this->db->prepare($sql, $forum_id, $user_id));

    if (!empty($this->db->last_error)) {
      $this->cache[$ck] = false;
      return false;
    }

    if ((string)$v === '1') {
      $this->cache[$ck] = true;
      return true;
    }

    // ACL fallback: any local moderator permission (m_*) with auth_setting <> 0.
    $ok = $this->user_has_local_acl_like($user_id, $forum_id, 'm_');
    $this->cache[$ck] = $ok ? true : false;
    return $ok;
  }

  /**
   * Return distinct phpBB user ids that can moderate a specific forum.
   *
   * This mirrors `user_is_forum_moderator()` in reverse: fast-path via
   * `moderator_cache`, then ACL-based fallbacks for local `m_*` options
   * granted directly or through group membership.
   */
  public function list_forum_moderator_ids(int $forum_id): array {
    if (!$this->db) return [];
    $forum_id = (int)$forum_id;
    if ($forum_id <= 0) return [];

    $ck = 'mod_ids:' . $forum_id;
    if (isset($this->cache[$ck]) && is_array($this->cache[$ck])) return $this->cache[$ck];

    $m   = $this->prefix . 'moderator_cache';
    $au  = $this->prefix . 'acl_users';
    $ag  = $this->prefix . 'acl_groups';
    $ao  = $this->prefix . 'acl_options';
    $ug  = $this->prefix . 'user_group';
    $ard = $this->prefix . 'acl_roles_data';
    $like = 'm_%';

    $ids = [];

    $rowsA = $this->db->get_col($this->db->prepare("SELECT DISTINCT user_id FROM {$m} WHERE forum_id=%d", $forum_id));
    if (!empty($this->db->last_error)) {
      $this->cache[$ck] = [];
      return [];
    }
    foreach ((array)$rowsA as $id) { $id = (int)$id; if ($id > 0) $ids[$id] = true; }

    $sqlUser = "
      SELECT DISTINCT au.user_id
      FROM {$au} au
      INNER JOIN {$ao} ao ON ao.auth_option_id = au.auth_option_id
      WHERE au.forum_id = %d
        AND au.auth_setting <> 0
        AND ao.auth_option LIKE %s
    ";
    $rowsB = $this->db->get_col($this->db->prepare($sqlUser, $forum_id, $like));
    if (!empty($this->db->last_error)) {
      $this->cache[$ck] = array_map('intval', array_keys($ids));
      return $this->cache[$ck];
    }
    foreach ((array)$rowsB as $id) { $id = (int)$id; if ($id > 0) $ids[$id] = true; }

    $sqlUserRole = "
      SELECT DISTINCT au.user_id
      FROM {$au} au
      INNER JOIN {$ard} ard ON ard.role_id = au.auth_role_id
      INNER JOIN {$ao} ao ON ao.auth_option_id = ard.auth_option_id
      WHERE au.forum_id = %d
        AND au.auth_role_id <> 0
        AND ard.auth_setting <> 0
        AND ao.auth_option LIKE %s
    ";
    $rowsC = $this->db->get_col($this->db->prepare($sqlUserRole, $forum_id, $like));
    if (!empty($this->db->last_error)) {
      $this->cache[$ck] = array_map('intval', array_keys($ids));
      return $this->cache[$ck];
    }
    foreach ((array)$rowsC as $id) { $id = (int)$id; if ($id > 0) $ids[$id] = true; }

    $sqlGroup = "
      SELECT DISTINCT ug.user_id
      FROM {$ag} ag
      INNER JOIN {$ao} ao ON ao.auth_option_id = ag.auth_option_id
      INNER JOIN {$ug} ug ON ug.group_id = ag.group_id
      WHERE ag.forum_id = %d
        AND ug.user_pending = 0
        AND ag.auth_setting <> 0
        AND ao.auth_option LIKE %s
    ";
    $rowsD = $this->db->get_col($this->db->prepare($sqlGroup, $forum_id, $like));
    if (!empty($this->db->last_error)) {
      $this->cache[$ck] = array_map('intval', array_keys($ids));
      return $this->cache[$ck];
    }
    foreach ((array)$rowsD as $id) { $id = (int)$id; if ($id > 0) $ids[$id] = true; }

    $sqlGroupRole = "
      SELECT DISTINCT ug.user_id
      FROM {$ag} ag
      INNER JOIN {$ug} ug ON ug.group_id = ag.group_id
      INNER JOIN {$ard} ard ON ard.role_id = ag.auth_role_id
      INNER JOIN {$ao} ao ON ao.auth_option_id = ard.auth_option_id
      WHERE ag.forum_id = %d
        AND ug.user_pending = 0
        AND ag.auth_role_id <> 0
        AND ard.auth_setting <> 0
        AND ao.auth_option LIKE %s
    ";
    $rowsE = $this->db->get_col($this->db->prepare($sqlGroupRole, $forum_id, $like));
    if (!empty($this->db->last_error)) {
      $this->cache[$ck] = array_map('intval', array_keys($ids));
      return $this->cache[$ck];
    }
    foreach ((array)$rowsE as $id) { $id = (int)$id; if ($id > 0) $ids[$id] = true; }

    $out = array_map('intval', array_keys($ids));
    sort($out);
    $this->cache[$ck] = $out;
    return $out;
  }

  /**
   * List forums where user has moderation rights.
   *
   * Matches Atrium's existing behaviour:
   *  - Step A: moderator_cache join forums (ordered by left_id)
   *  - Step B: ACL fallback (any m_* auth_option) for local forums (forum_id <> 0)
   */
  public function list_moderated_forums(int $user_id): array {
    $counters = $this->forum_counter_select_sql();
    if (!$this->db) return [];
    $user_id = (int)$user_id;
    if ($user_id <= 0) return [];

    $ck = 'mods_forums:' . $user_id;
    if (isset($this->cache[$ck]) && is_array($this->cache[$ck])) return $this->cache[$ck];

    $f = $this->prefix . 'forums';
    $m = $this->prefix . 'moderator_cache';

    // Fast path: moderator_cache (already maintained by phpBB and by Discuss create flow).
    $sqlA = "
      SELECT f.forum_id, f.forum_name, f.forum_desc, f.forum_rules,
             {$counters}
      FROM {$m} mc
      INNER JOIN {$f} f ON f.forum_id = mc.forum_id
      WHERE mc.user_id = %d
      ORDER BY f.left_id ASC
    ";
    $rows = $this->db->get_results($this->db->prepare($sqlA, $user_id), ARRAY_A);
    if (!empty($this->db->last_error)) {
      $this->cache[$ck] = [];
      return [];
    }
    if (is_array($rows) && count($rows)) {
      $this->cache[$ck] = $rows;
      return $rows;
    }

    // ACL fallback: compute effective local m_* on forums.
    $ids = $this->list_acl_forum_ids_like($user_id, 'm_', false);
    if (!$ids) {
      $this->cache[$ck] = [];
      return [];
    }

    $place = implode(',', array_fill(0, count($ids), '%d'));
    $args = $ids;
    $sqlB = "
      SELECT forum_id, forum_name, forum_desc, forum_rules,
             {$counters}
      FROM {$f}
      WHERE forum_id IN ({$place})
      ORDER BY left_id ASC
    ";
    $prepared = $this->db->prepare($sqlB, ...$args);
    $rows2 = $this->db->get_results($prepared, ARRAY_A);
    if (!empty($this->db->last_error)) {
      $this->cache[$ck] = [];
      return [];
    }
    $this->cache[$ck] = is_array($rows2) ? $rows2 : [];
    return $this->cache[$ck];
  }

  /**
   * True if user has any global (forum_id=0) ACL option matching a_* or m_*.
   */
  public function user_has_global_acl_like(int $user_id, string $prefixLike): bool {
    $user_id = (int)$user_id;
    if ($user_id <= 0) return false;
    $ck = 'glob_acl:' . $user_id . ':' . $prefixLike;
    if (array_key_exists($ck, $this->cache)) return (bool)$this->cache[$ck];

    $ids = $this->list_acl_forum_ids_like($user_id, $prefixLike, true);
    $ok = in_array(0, $ids, true);
    $this->cache[$ck] = $ok ? true : false;
    return $ok;
  }

  /**
   * Internal: true if user has any ACL option like (m_*) on a specific forum.
   */
  private function user_has_local_acl_like(int $user_id, int $forum_id, string $prefixLike): bool {
    $ids = $this->list_acl_forum_ids_like($user_id, $prefixLike, false);
    return in_array((int)$forum_id, $ids, true);
  }

  /**
   * Internal: returns unique forum_ids where user has auth_option LIKE '{prefixLike}%'
   * with auth_setting <> 0, derived from both direct user ACLs and group ACLs.
   *
   * If $globalOnly is true, restrict to forum_id = 0.
   * If $globalOnly is false, restrict to forum_id <> 0.
   */
  private function list_acl_forum_ids_like(int $user_id, string $prefixLike, bool $globalOnly): array {
    if (!$this->db) return [];
    $user_id = (int)$user_id;
    if ($user_id <= 0) return [];
    $prefixLike = (string)$prefixLike;
    $like = $prefixLike . '%';

    $ck = 'acl_ids:' . $user_id . ':' . ($globalOnly ? 'g' : 'l') . ':' . $prefixLike;
    if (isset($this->cache[$ck]) && is_array($this->cache[$ck])) return $this->cache[$ck];

    $au  = $this->prefix . 'acl_users';
    $ag  = $this->prefix . 'acl_groups';
    $ao  = $this->prefix . 'acl_options';
    $ug  = $this->prefix . 'user_group';
    $ard = $this->prefix . 'acl_roles_data';

    $forumCond = $globalOnly ? 'forum_id = 0' : 'forum_id <> 0';

    // Direct user ACLs (explicit options)
    $sqlUser = "
      SELECT DISTINCT au.forum_id
      FROM {$au} au
      INNER JOIN {$ao} ao ON ao.auth_option_id = au.auth_option_id
      WHERE au.user_id = %d
        AND au.auth_setting <> 0
        AND au.{$forumCond}
        AND ao.auth_option LIKE %s
    ";

    // Direct user ACLs (roles -> options)
    $sqlUserRole = "
      SELECT DISTINCT au.forum_id
      FROM {$au} au
      INNER JOIN {$ard} ard ON ard.role_id = au.auth_role_id
      INNER JOIN {$ao} ao ON ao.auth_option_id = ard.auth_option_id
      WHERE au.user_id = %d
        AND au.auth_role_id <> 0
        AND ard.auth_setting <> 0
        AND au.{$forumCond}
        AND ao.auth_option LIKE %s
    ";

    // Group ACLs via user_group membership (explicit options)
    $sqlGroup = "
      SELECT DISTINCT ag.forum_id
      FROM {$ag} ag
      INNER JOIN {$ao} ao ON ao.auth_option_id = ag.auth_option_id
      INNER JOIN {$ug} ug ON ug.group_id = ag.group_id
      WHERE ug.user_id = %d
        AND ug.user_pending = 0
        AND ag.auth_setting <> 0
        AND ag.{$forumCond}
        AND ao.auth_option LIKE %s
    ";

    // Group ACLs via roles -> options
    $sqlGroupRole = "
      SELECT DISTINCT ag.forum_id
      FROM {$ag} ag
      INNER JOIN {$ug} ug ON ug.group_id = ag.group_id
      INNER JOIN {$ard} ard ON ard.role_id = ag.auth_role_id
      INNER JOIN {$ao} ao ON ao.auth_option_id = ard.auth_option_id
      WHERE ug.user_id = %d
        AND ug.user_pending = 0
        AND ag.auth_role_id <> 0
        AND ard.auth_setting <> 0
        AND ag.{$forumCond}
        AND ao.auth_option LIKE %s
    ";

    $ids = [];
    $r1 = $this->db->get_col($this->db->prepare($sqlUser, $user_id, $like));
    if (!empty($this->db->last_error)) {
      $this->cache[$ck] = [];
      return [];
    }
    $r1b = $this->db->get_col($this->db->prepare($sqlUserRole, $user_id, $like));
    if (!empty($this->db->last_error)) {
      $this->cache[$ck] = [];
      return [];
    }

    $r2 = $this->db->get_col($this->db->prepare($sqlGroup, $user_id, $like));
    if (!empty($this->db->last_error)) {
      $this->cache[$ck] = [];
      return [];
    }
    $r2b = $this->db->get_col($this->db->prepare($sqlGroupRole, $user_id, $like));
    if (!empty($this->db->last_error)) {
      $this->cache[$ck] = [];
      return [];
    }
    foreach ((array)$r1 as $v) { $ids[] = (int)$v; }
    foreach ((array)$r1b as $v) { $ids[] = (int)$v; }
    foreach ((array)$r2 as $v) { $ids[] = (int)$v; }
    foreach ((array)$r2b as $v) { $ids[] = (int)$v; }
    $ids = array_values(array_unique(array_filter($ids, function($x){ return is_int($x); })));

    $this->cache[$ck] = $ids;
    return $ids;
  }

  /**
   * Update Agora meta stored on phpBB's forums row.
   * Stored as plain text / BBCode; uid/bitfield are cleared to avoid formatter issues.
   */
  public function update_forum_meta(int $forum_id, array $fields): void {
    if (!$this->db) throw new Exception('phpBB adapter not available');
    $forum_id = (int)$forum_id;
    if ($forum_id <= 0) throw new Exception('Invalid forum_id');

    $f = $this->prefix . 'forums';
    $data = [];
    $fmt  = [];

    if (array_key_exists('forum_name', $fields)) {
      $data['forum_name'] = trim((string)$fields['forum_name']);
      $fmt[] = '%s';
    }
    if (array_key_exists('forum_desc', $fields)) {
      $data['forum_desc'] = (string)$fields['forum_desc'];
      $data['forum_desc_uid'] = '';
      $data['forum_desc_bitfield'] = '';
      // Keep default options (bbcode/smilies/links) on.
      $data['forum_desc_options'] = 7;
      $fmt = array_merge($fmt, ['%s','%s','%s','%d']);
    }
    if (array_key_exists('forum_rules', $fields)) {
      $data['forum_rules'] = (string)$fields['forum_rules'];
      $data['forum_rules_uid'] = '';
      $data['forum_rules_bitfield'] = '';
      $data['forum_rules_options'] = 7;
      $fmt = array_merge($fmt, ['%s','%s','%s','%d']);
    }

    if (!$data) return;

    $ok = $this->db->update($f, $data, ['forum_id' => $forum_id]);
    if ($ok === false || !empty($this->db->last_error)) {
      throw new Exception('phpBB update failed: ' . ($this->db->last_error ?: 'unknown'));
    }
  }

  /**
   * Hard-delete an Agora (forum) and its core related rows.
   * This is intentionally conservative: remove topics/posts, watches, moderator cache.
   */
  public function delete_forum_hard(int $forum_id): void {
    if (!$this->db) throw new Exception('phpBB adapter not available');
    $forum_id = (int)$forum_id;
    if ($forum_id <= 0) throw new Exception('Invalid forum_id');

    $forums = $this->prefix . 'forums';
    $topics = $this->prefix . 'topics';
    $posts  = $this->prefix . 'posts';
    $modc   = $this->prefix . 'moderator_cache';
    $fw     = $this->prefix . 'forums_watch';
    $ft     = $this->prefix . 'forums_track';

    // Delete posts + topics first.
    $this->db->query($this->db->prepare("DELETE FROM {$posts} WHERE forum_id = %d", $forum_id));
    $this->db->query($this->db->prepare("DELETE FROM {$topics} WHERE forum_id = %d", $forum_id));

    // Remove ancillary forum rows.
    $this->db->query($this->db->prepare("DELETE FROM {$fw} WHERE forum_id = %d", $forum_id));
    $this->db->query($this->db->prepare("DELETE FROM {$ft} WHERE forum_id = %d", $forum_id));
    $this->db->query($this->db->prepare("DELETE FROM {$modc} WHERE forum_id = %d", $forum_id));

    // Finally remove the forum itself.
    $this->db->query($this->db->prepare("DELETE FROM {$forums} WHERE forum_id = %d", $forum_id));

    if (!empty($this->db->last_error)) {
      throw new Exception('phpBB delete failed: ' . $this->db->last_error);
    }
  }


  /**
   * Discuss-only: check whether a phpBB user is banned from posting in a forum (Agora).
   * Stored in WP table: {$wpdb->prefix}ia_discuss_forum_bans
   */
  public function discuss_is_user_banned(int $forum_id, int $user_id): bool {
    $forum_id = (int)$forum_id;
    $user_id  = (int)$user_id;
    if ($forum_id <= 0 || $user_id <= 0) return false;

    global $wpdb;
    if (!$wpdb) return false;

    $t = $wpdb->prefix . 'ia_discuss_forum_bans';
    // If the table doesn't exist yet, treat as not banned.
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
    if ((string)$exists !== (string)$t) return false;

    $v = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$t} WHERE forum_id = %d AND user_id = %d LIMIT 1", $forum_id, $user_id));
    return (string)$v === '1';
  }

  private function get_phpbb_creds(): ?array {
    if (class_exists('IA_Engine') && method_exists('IA_Engine', 'phpbb_db')) {
      $c = IA_Engine::phpbb_db();
      return is_array($c) ? $c : null;
    }
    if (class_exists('IA_Engine') && method_exists('IA_Engine', 'get')) {
      $c = IA_Engine::get('phpbb');
      return is_array($c) ? $c : null;
    }
    return null;
  }
}
