<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Module_Agoras implements IA_Discuss_Module_Interface {

  private $phpbb;
  private $bbcode;
  private $auth;
  private $membership;
  private $write;
  private $privacy;

  public function __construct(
    IA_Discuss_Service_PhpBB $phpbb,
    IA_Discuss_Render_BBCode $bbcode,
    IA_Discuss_Service_Auth $auth,
    IA_Discuss_Service_Membership $membership,
    IA_Discuss_Service_PhpBB_Write $write,
    IA_Discuss_Service_Agora_Privacy $privacy
  ) {
    $this->phpbb  = $phpbb;
    $this->bbcode = $bbcode;
    $this->auth = $auth;
    $this->membership = $membership;
    $this->write = $write;
    $this->privacy = $privacy;
  }

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_discuss_agoras' => ['method' => 'ajax_agoras', 'public' => true],
      'ia_discuss_agora_meta' => ['method' => 'ajax_agora_meta', 'public' => true],
    ];
  }

  public function ajax_agoras(): void {
    $offset = isset($_POST['offset']) ? max(0, (int)$_POST['offset']) : 0;
    $q      = isset($_POST['q']) ? sanitize_text_field((string)$_POST['q']) : '';
    $order  = isset($_POST['order']) ? sanitize_key((string)$_POST['order']) : '';

    if (!$this->phpbb->is_ready()) {
      ia_discuss_json_err('phpBB adapter not available (check IA Engine creds)', 503);
    }

    $limit = 20;
    $rows = $this->phpbb->get_agoras_rows($offset, $limit + 1, $q, $order);

    $has_more = count($rows) > $limit;
    if ($has_more) $rows = array_slice($rows, 0, $limit);

    $items = [];
    $viewer = (int) $this->auth->current_phpbb_user_id();
    foreach ($rows as $r) {
      $fid = (int)($r['forum_id'] ?? 0);
      if ($fid > 0 && !$this->privacy->user_has_access((int)$viewer, $fid)) continue;
      $fid = (int)($r['forum_id'] ?? 0);
      $joined = ($viewer > 0 && $fid > 0) ? ($this->membership->is_joined($viewer, $fid) ? 1 : 0) : 0;
      $bell = ($viewer > 0 && $fid > 0) ? ((int)$this->membership->get_notify_agora($viewer, $fid) ? 1 : 0) : 0;
      $banned = ($viewer > 0 && $fid > 0) ? ($this->write->is_user_banned($fid, $viewer) ? 1 : 0) : 0;
      $cover = ($fid > 0) ? (string) $this->membership->cover_url($fid) : '';
      $items[] = [
        'forum_id'        => $fid,
        'parent_id'       => (int)($r['parent_id'] ?? 0),
        'forum_type'      => (int)($r['forum_type'] ?? 0),
        'forum_name'      => (string)($r['forum_name'] ?? ''),
        'forum_desc_html' => $this->bbcode->excerpt_html((string)($r['forum_desc'] ?? ''), 170),
        'topics'          => (int)($r['topics_count'] ?? 0),
        'posts'           => (int)($r['posts_count'] ?? 0),
        'joined'          => $joined,
        'bell'            => $bell,
        'banned'          => $banned,
        'cover_url'       => $cover,
        'is_private'      => $this->privacy->is_private($fid) ? 1 : 0,
      ];
    }

    
    // Optional: latest topic + latest reply previews for Agoras list.
    // Kept lightweight: one bulk query per preview type (per page of Agoras).
    try {
      $db = $this->phpbb->db();
      $prefix = $this->phpbb->prefix();
      if ($db && !empty($items)) {
        $fids = array_values(array_filter(array_map(function($it){ return (int)($it['forum_id'] ?? 0); }, $items)));
        $fids = array_values(array_unique(array_filter($fids)));
        if (!empty($fids)) {
          $in = implode(',', array_map('intval', $fids));
          $topics = $prefix . 'topics';
          $postsT = $prefix . 'posts';
          $users  = $prefix . 'users';

          // Latest topic per forum (by highest topic_id; good enough and stable for imported content).
          $sql_latest_topic = "
            SELECT t.forum_id, t.topic_id, t.topic_title, t.topic_time, t.topic_poster, u.username
            FROM {$topics} t
            LEFT JOIN {$users} u ON u.user_id = t.topic_poster
            INNER JOIN (
              SELECT forum_id, MAX(topic_id) AS topic_id
              FROM {$topics}
              WHERE forum_id IN ({$in}) AND topic_visibility = 1
              GROUP BY forum_id
            ) x ON x.forum_id = t.forum_id AND x.topic_id = t.topic_id
          ";
          $rows_topic = $db->get_results($sql_latest_topic, ARRAY_A);
          $map_topic = [];
          if (is_array($rows_topic)) {
            foreach ($rows_topic as $r) {
              $fid = (int)($r['forum_id'] ?? 0);
              if ($fid <= 0) continue;
              $aid = (int)($r['topic_poster'] ?? 0);
              $uname = (string)($r['username'] ?? '');
              $map_topic[$fid] = [
                'topic_id' => (int)($r['topic_id'] ?? 0),
                'title'    => (string)($r['topic_title'] ?? ''),
                'time'     => (int)($r['topic_time'] ?? 0),
                'author_id' => $aid,
                'author_name' => function_exists('ia_discuss_display_name_from_phpbb') ? ia_discuss_display_name_from_phpbb($aid, $uname) : ($uname !== '' ? $uname : ('user#'.$aid)),
                'author_avatar' => function_exists('ia_discuss_avatar_url_from_phpbb') ? ia_discuss_avatar_url_from_phpbb($aid, 28) : '',
              ];
            }
          }

          // Latest reply per forum (by highest post_id).
          $sql_latest_reply = "
            SELECT t.forum_id, p.post_id, p.topic_id, t.topic_title, p.poster_id, p.post_time, u.username
            FROM {$postsT} p
            INNER JOIN {$topics} t ON t.topic_id = p.topic_id
            LEFT JOIN {$users} u ON u.user_id = p.poster_id
            INNER JOIN (
              SELECT t2.forum_id, MAX(p2.post_id) AS post_id
              FROM {$topics} t2
              INNER JOIN {$postsT} p2 ON p2.topic_id = t2.topic_id
              WHERE t2.forum_id IN ({$in}) AND t2.topic_visibility = 1 AND p2.post_visibility = 1
                AND p2.post_id <> t2.topic_first_post_id
              GROUP BY t2.forum_id
            ) y ON y.forum_id = t.forum_id AND y.post_id = p.post_id
          ";
          $rows_reply = $db->get_results($sql_latest_reply, ARRAY_A);
          $map_reply = [];
          if (is_array($rows_reply)) {
            foreach ($rows_reply as $r) {
              $fid = (int)($r['forum_id'] ?? 0);
              if ($fid <= 0) continue;
              $aid = (int)($r['poster_id'] ?? 0);
              $uname = (string)($r['username'] ?? '');
              $map_reply[$fid] = [
                'topic_id' => (int)($r['topic_id'] ?? 0),
                'post_id'  => (int)($r['post_id'] ?? 0),
                'title'    => (string)($r['topic_title'] ?? ''),
                'time'     => (int)($r['post_time'] ?? 0),
                'author_id' => $aid,
                'author_name' => function_exists('ia_discuss_display_name_from_phpbb') ? ia_discuss_display_name_from_phpbb($aid, $uname) : ($uname !== '' ? $uname : ('user#'.$aid)),
                'author_avatar' => function_exists('ia_discuss_avatar_url_from_phpbb') ? ia_discuss_avatar_url_from_phpbb($aid, 28) : '',
              ];
            }
          }

          // Attach to items (optional; UI tolerates missing).
          foreach ($items as $k => $it) {
            $fid = (int)($it['forum_id'] ?? 0);
            if ($fid <= 0) continue;
            if (isset($map_topic[$fid])) $items[$k]['latest_topic'] = $map_topic[$fid];
            if (isset($map_reply[$fid])) $items[$k]['latest_reply'] = $map_reply[$fid];
          }
        }
      }
    } catch (Throwable $e) {
      // Silent: previews are optional; do not break Agoras list.
    }

ia_discuss_json_ok([
      'offset'      => $offset,
      'limit'       => $limit,
      'next_offset' => $offset + count($items),
      'has_more'    => $has_more ? 1 : 0,
      'items'       => $items,
    ]);
  }


  public function ajax_agora_meta(): void {
    $fid = isset($_POST['forum_id']) ? (int)$_POST['forum_id'] : 0;
    if ($fid <= 0) ia_discuss_json_err('Missing forum_id', 400);

    if (!$this->phpbb->is_ready()) {
      ia_discuss_json_err('phpBB adapter not available (check IA Engine creds)', 503);
    }

    try {
      if (!$this->privacy->user_has_access((int)$this->auth->current_phpbb_user_id(), $fid)) ia_discuss_json_err('Private Agora', 403);
      $row = $this->phpbb->get_forum_row($fid);
      if (empty($row) || empty($row['forum_id'])) {
        ia_discuss_json_err('Agora not found', 404);
      }

      $viewer = (int) $this->auth->current_phpbb_user_id();
      $joined = ($viewer > 0) ? ($this->membership->is_joined($viewer, $fid) ? 1 : 0) : 0;
      $bell   = ($viewer > 0) ? ((int)$this->membership->get_notify_agora($viewer, $fid) ? 1 : 0) : 0;
      $cover  = (string) $this->membership->cover_url($fid);
      $is_private = $this->privacy->is_private($fid) ? 1 : 0;

      ia_discuss_json_ok([
        'forum_id'   => (int) $row['forum_id'],
        'forum_name' => (string) ($row['forum_name'] ?? ''),
        'forum_desc' => (string) ($row['forum_desc'] ?? ''),
        'joined'     => $joined,
        'bell'       => $bell,
        'cover'      => $cover,
        'is_private' => $is_private,
      ]);
    } catch (\Throwable $e) {
      ia_discuss_json_err('Failed to load agora meta', 500);
    }
  }

}
