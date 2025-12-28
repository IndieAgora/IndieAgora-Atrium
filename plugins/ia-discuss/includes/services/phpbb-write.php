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
      'forum_id' => $forum_id,
      'topic_title' => $title,
      'topic_poster' => $poster_id,
      'topic_time' => $now,
      'topic_views' => 0,
      'topic_status' => 0,
      'topic_type' => 0,
      'topic_first_poster_name' => $poster_name,
      'topic_last_poster_name'  => $poster_name,
      'topic_posts_approved' => 1,
      'topic_replies' => 0,
      'topic_replies_real' => 0,
      'topic_visibility' => 1,
      'topic_first_post_time' => $now,
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
    $db->query($db->prepare("UPDATE {$forums} SET forum_topics = forum_topics + 1, forum_topics_real = forum_topics_real + 1, forum_posts = forum_posts + 1 WHERE forum_id = %d", $forum_id));

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

    // Update topic last fields + counts
    $db->update($topics, [
      'topic_last_post_id' => $post_id,
      'topic_last_post_time' => $now,
      'topic_last_poster_id' => $poster_id,
      'topic_last_poster_name' => $poster_name,
      'topic_last_post_subject' => $subject,
      'topic_posts_approved' => (int)$db->get_var($db->prepare("SELECT topic_posts_approved FROM {$topics} WHERE topic_id = %d", $topic_id)) + 1,
    ], ['topic_id' => $topic_id]);

    // Bump forum posts
    if ($forum_id > 0) {
      $db->query($db->prepare("UPDATE {$forums} SET forum_posts = forum_posts + 1 WHERE forum_id = %d", $forum_id));
    }

    return [
      'post_id' => $post_id,
      'topic_id' => $topic_id,
    ];
  }
}
