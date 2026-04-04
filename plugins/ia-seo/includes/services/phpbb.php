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

  /**
   * Fetch latest visible topics.
   * Returns rows: topic_id, last_post_time (unix), topic_time (unix)
   */
  public function get_topics(int $limit): array {
    if (!$this->ok() || $limit <= 0) return [];
    $t = $this->prefix . 'topics';
    $sql = "SELECT topic_id, topic_time, topic_last_post_time FROM $t WHERE topic_visibility = 1 ORDER BY topic_last_post_time DESC LIMIT %d";
    return (array)$this->db->get_results($this->db->prepare($sql, $limit), ARRAY_A);
  }

  /**
   * Fetch latest visible posts (replies) for deep-links.
   * Returns rows: post_id, topic_id, post_time (unix)
   */
  public function get_posts(int $limit): array {
    if (!$this->ok() || $limit <= 0) return [];
    $p = $this->prefix . 'posts';
    $sql = "SELECT post_id, topic_id, post_time FROM $p WHERE post_visibility = 1 ORDER BY post_time DESC LIMIT %d";
    return (array)$this->db->get_results($this->db->prepare($sql, $limit), ARRAY_A);
  }
}
