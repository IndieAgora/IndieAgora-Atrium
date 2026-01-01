<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Service_PhpBB_Write {

  /** @var IA_Discuss_Service_PhpBB */
  private $phpbb;

  /** @var IA_Discuss_Service_Auth */
  private $auth;

  public function __construct(IA_Discuss_Service_PhpBB $phpbb, IA_Discuss_Service_Auth $auth) {
    $this->phpbb = $phpbb;
    $this->auth  = $auth;
  }


  public function boot(): void {
    // Ensure bans table exists (idempotent).
    $this->ensure_bans_table();
  }

  private function ensure_bans_table(): void {
    global $wpdb;
    if (!$wpdb) return;

    $t = $wpdb->prefix . 'ia_discuss_forum_bans';
    $charset = $wpdb->get_charset_collate();

    // Create a simple ban table. This is Discuss-owned (not phpBB-owned).
    $sql = "CREATE TABLE IF NOT EXISTS {$t} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      forum_id INT(10) UNSIGNED NOT NULL,
      user_id INT(10) UNSIGNED NOT NULL,
      banned_by INT(10) UNSIGNED NOT NULL DEFAULT 0,
      created_at INT(11) UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY (id),
      UNIQUE KEY forum_user (forum_id, user_id),
      KEY forum_id (forum_id),
      KEY user_id (user_id)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
  }

  public function ban_user_in_forum(int $forum_id, int $user_id, int $banned_by): void {
    $this->ensure_bans_table();
    global $wpdb;
    if (!$wpdb) throw new Exception('WP DB not available');

    $forum_id = (int)$forum_id;
    $user_id  = (int)$user_id;
    $banned_by = (int)$banned_by;
    if ($forum_id <= 0 || $user_id <= 0) throw new Exception('Invalid ban parameters');

    $t = $wpdb->prefix . 'ia_discuss_forum_bans';
    $wpdb->query($wpdb->prepare(
      "INSERT INTO {$t} (forum_id, user_id, banned_by, created_at)
       VALUES (%d, %d, %d, %d)
       ON DUPLICATE KEY UPDATE banned_by = VALUES(banned_by), created_at = VALUES(created_at)",
      $forum_id, $user_id, $banned_by, time()
    ));
    if (!empty($wpdb->last_error)) throw new Exception($wpdb->last_error);
  }

  public function unban_user_in_forum(int $forum_id, int $user_id): void {
    global $wpdb;
    if (!$wpdb) throw new Exception('WP DB not available');
    $t = $wpdb->prefix . 'ia_discuss_forum_bans';
    $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE forum_id = %d AND user_id = %d", (int)$forum_id, (int)$user_id));
    if (!empty($wpdb->last_error)) throw new Exception($wpdb->last_error);
  }

  public function is_user_banned(int $forum_id, int $user_id): bool {
    // Prefer phpbb service helper (checks existence), else query directly
    if ($this->phpbb && method_exists($this->phpbb, 'discuss_is_user_banned')) {
      try { return (bool)$this->phpbb->discuss_is_user_banned($forum_id, $user_id); } catch (Throwable $e) {}
    }
    global $wpdb;
    if (!$wpdb) return false;
    $t = $wpdb->prefix . 'ia_discuss_forum_bans';
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
    if ((string)$exists !== (string)$t) return false;
    $v = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$t} WHERE forum_id = %d AND user_id = %d LIMIT 1", (int)$forum_id, (int)$user_id));
    return (string)$v === '1';
  }

  public function edit_post(int $post_id, string $new_body, int $editor_user_id): array {
    if (!$this->phpbb->is_ready()) throw new Exception('phpBB adapter not available');
    $db = $this->phpbb->db();
    if (!$db) throw new Exception('phpBB DB unavailable');

    $post_id = (int)$post_id;
    $editor_user_id = (int)$editor_user_id;
    $new_body = trim((string)$new_body);
    if ($post_id <= 0) throw new Exception('Invalid post_id');
    if ($new_body === '') throw new Exception('Empty body');

    $posts = $this->phpbb->table('posts');
    $row = $db->get_row($db->prepare("SELECT post_id, topic_id, forum_id, poster_id, post_edit_count FROM {$posts} WHERE post_id = %d LIMIT 1", $post_id), ARRAY_A);
    if (!$row) throw new Exception('Post not found');

    $now = time();
    $checksum = md5($new_body);
    $edit_count = (int)($row['post_edit_count'] ?? 0) + 1;

    $ok = $db->update($posts, [
      'post_text' => $new_body,
      'post_checksum' => $checksum,
      'post_edit_time' => $now,
      'post_edit_user' => $editor_user_id,
      'post_edit_count' => $edit_count,
    ], ['post_id' => $post_id], ['%s','%s','%d','%d','%d'], ['%d']);
    if (!$ok) throw new Exception($db->last_error ?: 'Edit failed');

    return [
      'post_id' => $post_id,
      'topic_id' => (int)($row['topic_id'] ?? 0),
      'forum_id' => (int)($row['forum_id'] ?? 0),
      'edited' => 1,
    ];
  }

  public function delete_post(int $post_id, int $deleter_user_id, string $reason = ''): array {
    if (!$this->phpbb->is_ready()) throw new Exception('phpBB adapter not available');
    $db = $this->phpbb->db();
    if (!$db) throw new Exception('phpBB DB unavailable');

    $post_id = (int)$post_id;
    $deleter_user_id = (int)$deleter_user_id;
    if ($post_id <= 0) throw new Exception('Invalid post_id');

    $posts  = $this->phpbb->table('posts');
    $topics = $this->phpbb->table('topics');

    $row = $db->get_row($db->prepare(
      "SELECT post_id, topic_id, forum_id, poster_id, post_visibility FROM {$posts} WHERE post_id = %d LIMIT 1",
      $post_id
    ), ARRAY_A);
    if (!$row) throw new Exception('Post not found');

    $topic_id = (int)($row['topic_id'] ?? 0);
    $forum_id = (int)($row['forum_id'] ?? 0);

    $trow = $db->get_row($db->prepare(
      "SELECT topic_id, topic_first_post_id, topic_last_post_id, topic_last_post_time FROM {$topics} WHERE topic_id = %d LIMIT 1",
      $topic_id
    ), ARRAY_A);
    if (!$trow) throw new Exception('Topic not found');

    $now = time();

    // Soft-delete the post.
    $ok = $db->update($posts, [
      'post_visibility' => -1,
      'post_delete_time' => $now,
      'post_delete_reason' => (string)$reason,
      'post_delete_user' => $deleter_user_id,
    ], ['post_id' => $post_id], ['%d','%d','%s','%d'], ['%d']);
    if (!$ok) throw new Exception($db->last_error ?: 'Delete failed');

    $first_id = (int)($trow['topic_first_post_id'] ?? 0);
    $last_id  = (int)($trow['topic_last_post_id'] ?? 0);

    // If first post deleted -> delete topic (soft)
    if ($post_id === $first_id) {
      // Try to set topic_visibility=-1 if column exists.
      $cols = $db->get_col("SHOW COLUMNS FROM {$topics}", 0);
      if (is_array($cols) && in_array('topic_visibility', $cols, true)) {
        $update = [ 'topic_visibility' => -1 ];
        if (in_array('topic_delete_time', $cols, true)) $update['topic_delete_time'] = $now;
        if (in_array('topic_delete_reason', $cols, true)) $update['topic_delete_reason'] = (string)$reason;
        if (in_array('topic_delete_user', $cols, true)) $update['topic_delete_user'] = $deleter_user_id;
        $db->update($topics, $update, ['topic_id' => $topic_id]);
      }
    } else if ($post_id === $last_id) {
      // Recompute last visible post in topic
      $new_last = $db->get_row($db->prepare(
        "SELECT post_id, post_time FROM {$posts}
         WHERE topic_id = %d AND post_visibility = 1
         ORDER BY post_time DESC, post_id DESC LIMIT 1",
        $topic_id
      ), ARRAY_A);
      if ($new_last && isset($new_last['post_id'])) {
        $db->update($topics, [
          'topic_last_post_id' => (int)$new_last['post_id'],
          'topic_last_post_time' => (int)($new_last['post_time'] ?? 0),
        ], ['topic_id' => $topic_id], ['%d','%d'], ['%d']);
      }
    }

    return [
      'post_id' => $post_id,
      'topic_id' => $topic_id,
      'forum_id' => $forum_id,
      'deleted' => 1,
    ];
  }


  public function create_topic(int $forum_id, string $title, string $body): array {
    $forum_id = max(0, (int)$forum_id);
    $title = trim((string)$title);
    $body  = trim((string)$body);

    if ($forum_id <= 0) throw new Exception('Missing forum_id');
    if ($title === '') throw new Exception('Missing title');
    if ($body === '') throw new Exception('Missing body');

    $poster_id = $this->auth->current_phpbb_user_id();
    if ($poster_id <= 0) throw new Exception('No phpBB identity for this user');

    $db = $this->phpbb->db();
    if (!$db) throw new Exception('phpBB db not available');

    $prefix = $this->phpbb->prefix();
    $topics = $prefix . 'topics';
    $posts  = $prefix . 'posts';
    $forums = $prefix . 'forums';
    $users  = $prefix . 'users';

    $now = time();
    $ip  = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '127.0.0.1';

    // Fetch poster username for denormalized topic fields
    $poster_name = (string) $db->get_var($db->prepare("SELECT username FROM {$users} WHERE user_id = %d", $poster_id));
    if ($poster_name === '') $poster_name = 'agorian';

    $bbcode_uid = substr(md5(uniqid('', true)), 0, 8);
    $checksum = md5($body);

    // 1) Insert topic (shell) – we’ll set last_* after post insert
    $topic_data = [
      // phpBB 3.3 schema: no topic_replies/topic_replies_real/topic_first_post_time columns.
      'forum_id' => $forum_id,
      'topic_title' => $title,
      'topic_poster' => $poster_id,
      'topic_time' => $now,
      'topic_views' => 0,
      'topic_status' => 0,
      'topic_type' => 0,

      // denormalized names (used in listings)
      'topic_first_poster_name' => $poster_name,
      'topic_last_poster_name'  => $poster_name,

      // visibility + counters
      'topic_visibility' => 1,
      'topic_posts_approved' => 1,
      'topic_posts_unapproved' => 0,
      'topic_posts_softdeleted' => 0,
      'topic_approved' => 1,

      // last post time starts at creation time (will be updated after post insert)
      'topic_last_post_time'  => $now,
    ];

    $ok = $db->insert($topics, $topic_data);
    if (!$ok) throw new Exception('Failed to insert topic: ' . $db->last_error);
    $topic_id = (int) $db->insert_id;
    if ($topic_id <= 0) throw new Exception('Topic insert_id missing');

    // 2) Insert first post
    $post_data = [
      'topic_id' => $topic_id,
      'forum_id' => $forum_id,
      'poster_id' => $poster_id,
      'poster_ip' => $ip,
      'post_time' => $now,
      'post_subject' => $title,
      'post_text' => $body,
      'post_checksum' => $checksum,
      'bbcode_uid' => $bbcode_uid,
      'bbcode_bitfield' => '',
      'enable_bbcode' => 1,
      'enable_smilies' => 1,
      'enable_magic_url' => 1,
      'post_visibility' => 1,
      'post_postcount' => 1,
    ];

    $ok2 = $db->insert($posts, $post_data);
    if (!$ok2) throw new Exception('Failed to insert post: ' . $db->last_error);
    $post_id = (int) $db->insert_id;
    if ($post_id <= 0) throw new Exception('Post insert_id missing');

    // 3) Update topic with first/last post ids
    $db->update($topics, [
      'topic_first_post_id' => $post_id,
      'topic_last_post_id'  => $post_id,
      'topic_last_poster_id' => $poster_id,
      'topic_last_post_subject' => $title,
    ], ['topic_id' => $topic_id]);

    // 4) Bump forum counters (simple)
    // phpBB 3.3 schema uses *_approved counters (no forum_topics/forum_posts/forum_topics_real)
    $db->query($db->prepare(
      "UPDATE {$forums}
       SET forum_topics_approved = forum_topics_approved + 1,
           forum_posts_approved  = forum_posts_approved  + 1,
           forum_last_post_id    = %d,
           forum_last_poster_id  = %d,
           forum_last_post_subject = %s,
           forum_last_post_time  = %d,
           forum_last_poster_name = %s
       WHERE forum_id = %d",
      $post_id, $poster_id, $title, $now, $poster_name, $forum_id
    ));

    return [
      'topic_id' => $topic_id,
      'post_id'  => $post_id,
    ];
  }

  public function reply(int $topic_id, string $body): array {
    $topic_id = max(0, (int)$topic_id);
    $body = trim((string)$body);

    if ($topic_id <= 0) throw new Exception('Missing topic_id');
    if ($body === '') throw new Exception('Missing body');

    $poster_id = $this->auth->current_phpbb_user_id();
    if ($poster_id <= 0) throw new Exception('No phpBB identity for this user');

    $db = $this->phpbb->db();
    if (!$db) throw new Exception('phpBB db not available');

    $prefix = $this->phpbb->prefix();
    $topics = $prefix . 'topics';
    $posts  = $prefix . 'posts';
    $forums = $prefix . 'forums';
    $users  = $prefix . 'users';

    // Hard fail early with a useful message if the configured prefix/db is wrong.
    // This prevents "silent success" feelings when writes are hitting non-existent tables.
    $missing = [];
    foreach (['topics' => $topics, 'posts' => $posts, 'forums' => $forums, 'users' => $users] as $k => $t) {
      $ok = (bool) $db->get_var($db->prepare('SHOW TABLES LIKE %s', $t));
      if (!$ok) $missing[] = $k . ':' . $t;
    }
    if (!empty($missing)) {
      throw new Exception('phpBB tables missing for prefix/db: ' . implode(', ', $missing));
    }

    $now = time();
    $ip  = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '127.0.0.1';

    // Topic info needed for forum_id + subject
    $trow = $db->get_row($db->prepare("SELECT topic_id, forum_id, topic_title FROM {$topics} WHERE topic_id = %d LIMIT 1", $topic_id), ARRAY_A);
    if (!$trow) throw new Exception('Topic not found');
    $forum_id = (int)($trow['forum_id'] ?? 0);
    $subject  = (string)($trow['topic_title'] ?? 'Reply');

    $poster_name = (string) $db->get_var($db->prepare("SELECT username FROM {$users} WHERE user_id = %d", $poster_id));
    if ($poster_name === '') $poster_name = 'agorian';

    $bbcode_uid = substr(md5(uniqid('', true)), 0, 8);
    $checksum = md5($body);

    // ------------------------------------------------------------
    // MERGE (DB-only phpBB): If the current user is already the last
    // poster in this topic, merge the new reply into that last post
    // instead of inserting a new row. This keeps testing possible
    // and prevents an internal "double-post" guard from blocking.
    //
    // Default behavior: ALWAYS merge when last poster matches.
    // You can disable or change this via filter:
    //   add_filter('ia_discuss_merge_consecutive_posts', fn($on)=>false);
    // ------------------------------------------------------------
    $do_merge = (bool) apply_filters('ia_discuss_merge_consecutive_posts', true);
    if ($do_merge) {
      $last = $db->get_row(
        $db->prepare(
          "SELECT post_id, poster_id, post_text, post_edit_count FROM {$posts} WHERE topic_id = %d ORDER BY post_time DESC, post_id DESC LIMIT 1",
          $topic_id
        ),
        ARRAY_A
      );

      $last_post_id = (int)($last['post_id'] ?? 0);
      $last_poster  = (int)($last['poster_id'] ?? 0);

      if ($last_post_id > 0 && $last_poster === $poster_id) {
        $old_text = (string)($last['post_text'] ?? '');

        // Keep it simple: append with spacing, no formatting assumptions.
        $merged_text = rtrim($old_text) . "\n\n" . $body;
        $merged_checksum = md5($merged_text);
        $edit_count = (int)($last['post_edit_count'] ?? 0);

        $okm = $db->update($posts, [
          'post_text'       => $merged_text,
          'post_checksum'   => $merged_checksum,
          'post_edit_time'  => $now,
          'post_edit_user'  => $poster_id,
          'post_edit_count' => $edit_count + 1,
          'post_modified'   => $now,
        ], ['post_id' => $last_post_id]);

        if (!$okm) throw new Exception('Failed to merge reply: ' . $db->last_error);

        // Verify write happened (defensive against no-op updates).
        $check = (string) $db->get_var($db->prepare("SELECT post_text FROM {$posts} WHERE post_id = %d", $last_post_id));
        if ($check === '') {
          throw new Exception('Merge verification failed (post not readable after update)');
        }

        // Bump topic/forum last_* timestamps so the topic still rises.
        // Keep topic's last_* fields consistent (even though we're editing an existing post).
        $db->update($topics, [
          'topic_last_post_id'   => $last_post_id,
          'topic_last_post_time' => $now,
          'topic_last_poster_id' => $poster_id,
          'topic_last_poster_name' => $poster_name,
          'topic_last_post_subject' => $subject,
        ], ['topic_id' => $topic_id]);

        if ($forum_id > 0) {
          $db->query($db->prepare(
            "UPDATE {$forums}
             SET forum_last_post_time = %d,
                 forum_last_poster_id = %d,
                 forum_last_post_subject = %s,
                 forum_last_poster_name = %s
             WHERE forum_id = %d",
            $now, $poster_id, $subject, $poster_name, $forum_id
          ));
        }

        return [
          'post_id' => $last_post_id,
          'topic_id' => $topic_id,
          'merged' => true,
        ];
      }
    }

    $post_data = [
      'topic_id' => $topic_id,
      'forum_id' => $forum_id,
      'poster_id' => $poster_id,
      'poster_ip' => $ip,
      'post_time' => $now,
      'post_subject' => $subject,
      'post_text' => $body,
      'post_checksum' => $checksum,
      'bbcode_uid' => $bbcode_uid,
      'bbcode_bitfield' => '',
      'enable_bbcode' => 1,
      'enable_smilies' => 1,
      'enable_magic_url' => 1,
      'post_visibility' => 1,
      'post_postcount' => 1,
    ];

    $ok = $db->insert($posts, $post_data);
    if (!$ok) throw new Exception('Failed to insert reply: ' . $db->last_error);
    $post_id = (int) $db->insert_id;
    if ($post_id <= 0) throw new Exception('Reply insert_id missing');

    // Verify insert is actually readable and visible.
    $vis = (int) $db->get_var($db->prepare("SELECT post_visibility FROM {$posts} WHERE post_id = %d", $post_id));
    if ($vis !== 1) {
      throw new Exception('Reply inserted but not visible (post_visibility=' . $vis . ')');
    }

    // Update topic last fields + counts (atomic increment)
    $db->query($db->prepare(
      "UPDATE {$topics}
       SET topic_last_post_id = %d,
           topic_last_post_time = %d,
           topic_last_poster_id = %d,
           topic_last_poster_name = %s,
           topic_last_post_subject = %s,
           topic_posts_approved = topic_posts_approved + 1
       WHERE topic_id = %d",
      $post_id, $now, $poster_id, $poster_name, $subject, $topic_id
    ));

    // Bump forum posts
    if ($forum_id > 0) {
      $db->query($db->prepare(
        "UPDATE {$forums}
         SET forum_posts_approved = forum_posts_approved + 1,
             forum_last_post_id = %d,
             forum_last_poster_id = %d,
             forum_last_post_subject = %s,
             forum_last_post_time = %d,
             forum_last_poster_name = %s
         WHERE forum_id = %d",
        $post_id, $poster_id, $subject, $now, $poster_name, $forum_id
      ));
    }

    return [
      'post_id' => $post_id,
      'topic_id' => $topic_id,
    ];
  }
}
