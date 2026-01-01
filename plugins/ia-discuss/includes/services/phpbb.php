<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Service_PhpBB {

  /** @var wpdb|null */
  private $db = null;

  /** @var string */
  private $prefix = 'phpbb_';

  /** @var string */
  public $last_query = '';

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

  public function get_forum_row(int $forum_id): array {
    if (!$this->db) throw new Exception('phpBB adapter not available');

    $f = $this->prefix . 'forums';

    $sql = "
      SELECT forum_id, forum_name, forum_desc, forum_posts, forum_topics, forum_topics_real
      FROM {$f}
      WHERE forum_id = %d
      LIMIT 1
    ";

    $row = $this->db->get_row($this->db->prepare($sql, $forum_id), ARRAY_A);
    if (!empty($this->db->last_error)) throw new Exception('phpBB SQL error: ' . $this->db->last_error);
    if (!$row) throw new Exception('Forum not found');
    return $row;
  }

  public function get_feed_rows(string $tab, int $offset, int $limit, string $q = '', int $forum_id = 0): array {
    if (!$this->db) throw new Exception('phpBB adapter not available');

    $offset  = max(0, $offset);
    $limit   = max(1, min(50, $limit));
    $tab     = $tab ?: 'new_posts';
    $forum_id = max(0, (int)$forum_id);

    $t = $this->prefix . 'topics';
    $f = $this->prefix . 'forums';
    $p = $this->prefix . 'posts';
    $u = $this->prefix . 'users';

    $where = "WHERE 1=1";
    $args = [];

    // visibility guard (cheap)
    $where .= " AND t.topic_visibility = 1";

    if ($forum_id > 0) {
      $where .= " AND t.forum_id = %d";
      $args[] = $forum_id;
    }

    if ($q !== '') {
      $where .= " AND (t.topic_title LIKE %s OR p.post_text LIKE %s)";
      $like = '%' . $this->db->esc_like($q) . '%';
      $args[] = $like;
      $args[] = $like;
    }

    $order = "ORDER BY t.topic_last_post_time DESC";
    if ($tab === 'new_topics') $order = "ORDER BY t.topic_time DESC";

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
        t.topic_poster,
        up.username AS topic_poster_username,
        t.topic_last_poster_id,
        ul.username AS last_poster_username,
        p.post_text
      FROM {$t} t
      LEFT JOIN {$f} f ON f.forum_id = t.forum_id
      LEFT JOIN {$p} p ON p.post_id = t.topic_first_post_id
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

    return is_array($rows) ? $rows : [];
  }

  public function get_agoras_rows(int $offset, int $limit, string $q = ''): array {
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
        ) AS posts_count
      FROM {$f}
      {$where}
      ORDER BY left_id ASC
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
    return is_array($rows) ? $rows : [];
  }

  /**
   * True if user is a moderator for the given forum.
   *
   * IMPORTANT: We only rely on phpBB schema tables (no phpBB app/runtime).
   * For this project, moderator status is represented in phpbb_moderator_cache.
   */
  public function user_is_forum_moderator(int $user_id, int $forum_id): bool {
    if (!$this->db) return false;
    $user_id = (int)$user_id;
    $forum_id = (int)$forum_id;
    if ($user_id <= 0 || $forum_id <= 0) return false;

    $m = $this->prefix . 'moderator_cache';
    $sql = "SELECT 1 FROM {$m} WHERE forum_id = %d AND user_id = %d LIMIT 1";
    $v = $this->db->get_var($this->db->prepare($sql, $forum_id, $user_id));

    if (!empty($this->db->last_error)) {
      // Fail closed.
      return false;
    }
    return (string)$v === '1';
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
