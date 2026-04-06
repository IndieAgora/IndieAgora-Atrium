<?php
if (!defined('ABSPATH')) exit;

class IA_SEO_PHPBB_DB {
  private $db = null; // wpdb
  private $prefix = 'phpbb_';

  public function __construct() {
    $c = $this->get_creds();
    if (!$c) return;

    $name = (string)($c['name'] ?? '');
    $user = (string)($c['user'] ?? '');
    $pass = (string)($c['pass'] ?? '');
    $host = (string)($c['host'] ?? 'localhost');
    $port = (int)($c['port'] ?? 3306);
    $this->prefix = (string)($c['prefix'] ?? 'phpbb_');
    if ($this->prefix === '') $this->prefix = 'phpbb_';

    if ($name === '' || $user === '') return;

    $dbhost = $host;
    if ($port && $port !== 3306) $dbhost = $host . ':' . $port;

    $this->db = new wpdb($user, $pass, $name, $dbhost);
    $this->db->set_prefix('');
    $this->db->show_errors(false);
    $this->db->suppress_errors(true);

    // Validate prefix quickly.
    $users_tbl = $this->prefix . 'users';
    $ok = $this->db->get_var("SHOW TABLES LIKE '" . esc_sql($users_tbl) . "'");
    if (!$ok) {
      // Try auto-detect: look for %users containing 'phpbb_'
      $cand = $this->db->get_col("SHOW TABLES LIKE '%users'");
      if (is_array($cand)) {
        foreach ($cand as $t) {
          if (!is_string($t)) continue;
          if (substr($t, -5) !== 'users') continue;
          $p = substr($t, 0, -5);
          if ($p === '') continue;
          $this->prefix = $p;
          break;
        }
      }
    }
  }

  private function get_creds(): ?array {
    if (class_exists('IA_Engine') && method_exists('IA_Engine', 'phpbb_db')) {
      try {
        $c = IA_Engine::phpbb_db();
        return is_array($c) ? $c : null;
      } catch (Throwable $e) {
        return null;
      }
    }
    return null;
  }

  public function ok(): bool {
    return is_object($this->db);
  }

  public function prefix(): string {
    return $this->prefix;
  }

  public function db(): ?wpdb {
    return $this->db;
  }

  private function privacy_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'ia_discuss_agora_privacy';
  }

  private function privacy_table_exists(): bool {
    global $wpdb;
    $tbl = $this->privacy_table();
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tbl));
    return is_string($found) && $found === $tbl;
  }

  /**
   * Fetch latest visible topics in public agoras/forums.
   * Returns rows: topic_id, forum_id, forum_name, topic_last_post_time, topic_time
   */
  public function get_topics(int $limit): array {
    if (!$this->ok() || $limit <= 0) return [];
    $t = $this->prefix . 'topics';
    $f = $this->prefix . 'forums';

    $join_priv = '';
    $where_priv = '';
    if ($this->privacy_table_exists()) {
      $priv = $this->privacy_table();
      $join_priv = " LEFT JOIN {$priv} ip ON ip.forum_id = t.forum_id ";
      $where_priv = " AND IFNULL(ip.is_private, 0) = 0 ";
    }

    $sql = "
      SELECT t.topic_id, t.forum_id, f.forum_name, t.topic_time, t.topic_last_post_time
      FROM {$t} t
      INNER JOIN {$f} f ON f.forum_id = t.forum_id
      {$join_priv}
      WHERE t.topic_visibility = 1
      {$where_priv}
      ORDER BY t.topic_last_post_time DESC
      LIMIT %d";
    return (array)$this->db->get_results($this->db->prepare($sql, $limit), ARRAY_A);
  }

  /**
   * Fetch latest visible posts (replies) for deep-links in public agoras/forums.
   * Returns rows: post_id, topic_id, forum_id, post_time
   */
  public function get_posts(int $limit): array {
    if (!$this->ok() || $limit <= 0) return [];
    $p = $this->prefix . 'posts';
    $t = $this->prefix . 'topics';

    $join_priv = '';
    $where_priv = '';
    if ($this->privacy_table_exists()) {
      $priv = $this->privacy_table();
      $join_priv = " LEFT JOIN {$priv} ip ON ip.forum_id = t.forum_id ";
      $where_priv = " AND IFNULL(ip.is_private, 0) = 0 ";
    }

    $sql = "
      SELECT p.post_id, p.topic_id, p.forum_id, p.post_time
      FROM {$p} p
      INNER JOIN {$t} t ON t.topic_id = p.topic_id
      {$join_priv}
      WHERE p.post_visibility = 1 AND t.topic_visibility = 1
      {$where_priv}
      ORDER BY p.post_time DESC
      LIMIT %d";
    return (array)$this->db->get_results($this->db->prepare($sql, $limit), ARRAY_A);
  }

  /**
   * Fetch public agoras/forums only.
   * Returns rows: forum_id, forum_name, forum_last_post_time, forum_last_post_id
   */
  public function get_public_forums(int $limit): array {
    if (!$this->ok() || $limit <= 0) return [];
    $f = $this->prefix . 'forums';

    $join_priv = '';
    $where_priv = '';
    if ($this->privacy_table_exists()) {
      $priv = $this->privacy_table();
      $join_priv = " LEFT JOIN {$priv} ip ON ip.forum_id = f.forum_id ";
      $where_priv = " AND IFNULL(ip.is_private, 0) = 0 ";
    }

    $sql = "
      SELECT f.forum_id, f.forum_name, f.forum_last_post_time, f.forum_last_post_id
      FROM {$f} f
      {$join_priv}
      WHERE f.forum_id > 0
        AND f.parent_id > 0
        AND (f.forum_posts_approved > 0 OR f.forum_topics_approved > 0)
      {$where_priv}
      ORDER BY f.forum_last_post_time DESC, f.forum_id DESC
      LIMIT %d";
    return (array)$this->db->get_results($this->db->prepare($sql, $limit), ARRAY_A);
  }
}


if (!class_exists('IA_SEO_PHPBB_Metadata_DB')) {
class IA_SEO_PHPBB_Metadata_DB extends IA_SEO_PHPBB_DB {
  public function get_forum_by_id(int $forum_id): ?array {
    if (!$this->ok() || $forum_id <= 0) return null;
    $f = $this->prefix() . 'forums';
    $join_priv = '';
    $where_priv = '';
    global $wpdb;
    $priv = $wpdb->prefix . 'ia_discuss_agora_privacy';
    $has_priv = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $priv));
    if (is_string($has_priv) && $has_priv === $priv) {
      $join_priv = " LEFT JOIN {$priv} ip ON ip.forum_id = f.forum_id ";
      $where_priv = " AND IFNULL(ip.is_private, 0) = 0 ";
    }
    $sql = "SELECT f.forum_id, f.forum_name, f.forum_desc, f.forum_posts_approved, f.forum_topics_approved, f.forum_last_post_time FROM {$f} f {$join_priv} WHERE f.forum_id=%d {$where_priv} LIMIT 1";
    $row = $this->db()->get_row($this->db()->prepare($sql, $forum_id), ARRAY_A);
    return is_array($row) ? $row : null;
  }

  public function get_topic_by_id(int $topic_id): ?array {
    if (!$this->ok() || $topic_id <= 0) return null;
    $t = $this->prefix() . 'topics';
    $f = $this->prefix() . 'forums';
    $p = $this->prefix() . 'posts';
    global $wpdb;
    $join_priv = '';
    $where_priv = '';
    $priv = $wpdb->prefix . 'ia_discuss_agora_privacy';
    $has_priv = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $priv));
    if (is_string($has_priv) && $has_priv === $priv) {
      $join_priv = " LEFT JOIN {$priv} ip ON ip.forum_id = t.forum_id ";
      $where_priv = " AND IFNULL(ip.is_private, 0) = 0 ";
    }
    $sql = "SELECT t.topic_id, t.forum_id, t.topic_title, t.topic_time, t.topic_last_post_time, t.topic_replies, f.forum_name, fp.post_text AS first_post_text
            FROM {$t} t
            INNER JOIN {$f} f ON f.forum_id=t.forum_id
            LEFT JOIN {$p} fp ON fp.post_id=t.topic_first_post_id
            {$join_priv}
            WHERE t.topic_id=%d AND t.topic_visibility=1 {$where_priv}
            LIMIT 1";
    $row = $this->db()->get_row($this->db()->prepare($sql, $topic_id), ARRAY_A);
    if (!is_array($row)) return null;
    $reply_sql = "SELECT post_text FROM {$p} WHERE topic_id=%d AND post_visibility=1 ORDER BY post_time ASC LIMIT 6";
    $reply_rows = (array)$this->db()->get_results($this->db()->prepare($reply_sql, $topic_id), ARRAY_A);
    $bodies = [];
    foreach ($reply_rows as $rr) {
      $txt = trim((string)($rr['post_text'] ?? ''));
      if ($txt !== '') $bodies[] = $txt;
    }
    $row['reply_bodies'] = $bodies;
    return $row;
  }

  public function get_post_by_id(int $post_id): ?array {
    if (!$this->ok() || $post_id <= 0) return null;
    $p = $this->prefix() . 'posts';
    $t = $this->prefix() . 'topics';
    $f = $this->prefix() . 'forums';
    global $wpdb;
    $join_priv = '';
    $where_priv = '';
    $priv = $wpdb->prefix . 'ia_discuss_agora_privacy';
    $has_priv = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $priv));
    if (is_string($has_priv) && $has_priv === $priv) {
      $join_priv = " LEFT JOIN {$priv} ip ON ip.forum_id = p.forum_id ";
      $where_priv = " AND IFNULL(ip.is_private, 0) = 0 ";
    }
    $sql = "SELECT p.post_id, p.topic_id, p.forum_id, p.post_text, p.post_time, t.topic_title, f.forum_name
            FROM {$p} p
            INNER JOIN {$t} t ON t.topic_id=p.topic_id
            INNER JOIN {$f} f ON f.forum_id=p.forum_id
            {$join_priv}
            WHERE p.post_id=%d AND p.post_visibility=1 AND t.topic_visibility=1 {$where_priv}
            LIMIT 1";
    $row = $this->db()->get_row($this->db()->prepare($sql, $post_id), ARRAY_A);
    return is_array($row) ? $row : null;
  }
}
}
