<?php
if (!defined('ABSPATH')) exit;

/**
 * Comments module (wired)
 * - Calls PeerTube /api/v1/videos/{id}/comment-threads
 * - Normalizes thread objects to reply cards
 */
final class IA_Stream_Module_Comments implements IA_Stream_Module_Interface {

  private static function current_phpbb_user_id(): int {
    if (!is_user_logged_in()) return 0;
    $wp_user_id = (int) get_current_user_id();
    if ($wp_user_id <= 0) return 0;

    global $wpdb;
    if (!($wpdb instanceof wpdb)) return 0;

    $t = $wpdb->prefix . 'ia_identity_map';
    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT phpbb_user_id FROM {$t} WHERE wp_user_id=%d LIMIT 1", $wp_user_id),
      ARRAY_A
    );
    if (is_array($row) && isset($row['phpbb_user_id'])) return (int)$row['phpbb_user_id'];

    return (int) get_user_meta($wp_user_id, 'ia_phpbb_user_id', true);
  }

  private static function collect_tree_ids(array $node, array &$ids): void {
    if (isset($node['id'])) {
      $cid = trim((string)$node['id']);
      if ($cid !== '') $ids[] = $cid;
    }
    if (isset($node['children']) && is_array($node['children'])) {
      foreach ($node['children'] as $ch) {
        if (is_array($ch)) self::collect_tree_ids($ch, $ids);
      }
    }
  }

  private static function apply_votes_to_threads(array $threads, int $phpbb_user_id): array {
    if (!class_exists('IA_Stream_Service_Comment_Votes')) return $threads;

    $ids = [];
    foreach ($threads as $t) {
      if (!is_array($t)) continue;
      $cid = isset($t['comment_id']) ? trim((string)$t['comment_id']) : '';
      if ($cid !== '') $ids[] = $cid;
    }

    $counts = IA_Stream_Service_Comment_Votes::counts($ids);
    $mine = $phpbb_user_id > 0 ? IA_Stream_Service_Comment_Votes::user_votes($phpbb_user_id, $ids) : [];

    foreach ($threads as &$t) {
      if (!is_array($t)) continue;
      $cid = isset($t['comment_id']) ? trim((string)$t['comment_id']) : '';
      $t['votes'] = [
        'up' => isset($counts[$cid]) ? (int)$counts[$cid]['up'] : 0,
        'down' => isset($counts[$cid]) ? (int)$counts[$cid]['down'] : 0,
        'my' => isset($mine[$cid]) ? (int)$mine[$cid] : 0,
      ];
    }
    unset($t);

    return $threads;
  }

  private static function apply_votes_to_tree(array $tree, int $phpbb_user_id): array {
    if (!class_exists('IA_Stream_Service_Comment_Votes')) return $tree;
    if (!isset($tree['root']) || !is_array($tree['root'])) return $tree;

    $ids = [];
    self::collect_tree_ids($tree['root'], $ids);
    $counts = IA_Stream_Service_Comment_Votes::counts($ids);
    $mine = $phpbb_user_id > 0 ? IA_Stream_Service_Comment_Votes::user_votes($phpbb_user_id, $ids) : [];

    $apply = function (array $n) use (&$apply, $counts, $mine) {
      $cid = isset($n['id']) ? trim((string)$n['id']) : '';
      $n['votes'] = [
        'up' => ($cid !== '' && isset($counts[$cid])) ? (int)$counts[$cid]['up'] : 0,
        'down' => ($cid !== '' && isset($counts[$cid])) ? (int)$counts[$cid]['down'] : 0,
        'my' => ($cid !== '' && isset($mine[$cid])) ? (int)$mine[$cid] : 0,
      ];
      if (isset($n['children']) && is_array($n['children'])) {
        $out = [];
        foreach ($n['children'] as $ch) {
          $out[] = is_array($ch) ? $apply($ch) : $ch;
        }
        $n['children'] = $out;
      }
      return $n;
    };

    $tree['root'] = $apply($tree['root']);
    return $tree;
  }

  public static function boot(): void {}

  public static function normalize_query(array $q): array {
    $video_id = isset($q['video_id']) ? trim((string)$q['video_id']) : '';
    $video_id = mb_substr($video_id, 0, 64);

    $page = isset($q['page']) ? (int)$q['page'] : 1;
    $page = max(1, $page);

    $per_page = isset($q['per_page']) ? (int)$q['per_page'] : 20;
    $per_page = min(100, max(1, $per_page));

    return [
      'video_id' => $video_id,
      'page'     => $page,
      'per_page' => $per_page,
    ];
  }

  public static function get_comments(array $q): array {
    $q = self::normalize_query($q);

    if ($q['video_id'] === '') {
      return ['ok' => false, 'error' => 'Missing video_id'];
    }

    if (!class_exists('IA_Stream_Service_PeerTube_API')) {
      return ['ok' => false, 'error' => 'PeerTube API service missing'];
    }

    $api = new IA_Stream_Service_PeerTube_API();

    if (!$api->is_configured()) {
      return ['ok' => true, 'meta' => ['note' => 'PeerTube not configured (missing base URL)'], 'items' => []];
    }

    $raw = $api->get_comments($q['video_id'], $q);

    if (!$raw['ok']) {
      return [
        'ok' => false,
        'error' => $raw['error'] ?? 'PeerTube error',
        'body' => $raw['body'] ?? null,
      ];
    }

    $data = $raw['data'] ?? [];
    $items = [];

    // comment-threads returns { total, data: [...] }
    $list = [];
    if (is_array($data) && isset($data['data']) && is_array($data['data'])) $list = $data['data'];
    elseif (is_array($data)) $list = $data;

    // BUGFIX: Use PeerTube public base from the service (IA Engine config).
    $base = '';
    if (method_exists($api, 'public_base')) {
      $base = rtrim((string)$api->public_base(), '/');
    }
    if ($base === '' && defined('IA_PEERTUBE_BASE')) {
      $base = rtrim((string)IA_PEERTUBE_BASE, '/');
    }

    foreach ($list as $t) {
      if (!is_array($t)) continue;
      $norm = function_exists('ia_stream_norm_comment_thread') ? ia_stream_norm_comment_thread($t, $base) : $t;
      // Hide deleted/tombstone comments (Atrium requirement: deleted comments vanish).
      if (is_array($norm) && !empty($norm['is_deleted'])) continue;
      $items[] = $norm;
    }

    // Attach local comment vote counts + current user's vote.
    $phpbb_user_id = self::current_phpbb_user_id();
    $items = self::apply_votes_to_threads($items, $phpbb_user_id);

    return [
      'ok' => true,
      'meta' => [
        'video_id' => $q['video_id'],
        'page' => $q['page'],
        'per_page' => $q['per_page'],
        'total' => isset($data['total']) ? (int)$data['total'] : null,
      ],
      'items' => $items,
    ];
  }

  public static function get_thread(array $q): array {
    $video_id = isset($q['video_id']) ? trim((string)$q['video_id']) : '';
    $thread_id = isset($q['thread_id']) ? trim((string)$q['thread_id']) : '';
    $video_id = mb_substr($video_id, 0, 64);
    $thread_id = mb_substr($thread_id, 0, 64);

    if ($video_id === '' || $thread_id === '') {
      return ['ok' => false, 'error' => 'Missing video_id or thread_id'];
    }

    if (!class_exists('IA_Stream_Service_PeerTube_API')) {
      return ['ok' => false, 'error' => 'PeerTube API service missing'];
    }

    $api = new IA_Stream_Service_PeerTube_API();
    if (!$api->is_configured()) {
      return ['ok' => true, 'meta' => ['note' => 'PeerTube not configured (missing base URL)'], 'item' => null];
    }

    $raw = $api->get_comment_thread($video_id, $thread_id);

    if (!$raw['ok']) {
      return [
        'ok' => false,
        'error' => $raw['error'] ?? 'PeerTube error',
        'body' => $raw['body'] ?? null,
      ];
    }

    $data = $raw['data'] ?? [];

    $base = '';
    if (method_exists($api, 'public_base')) {
      $base = rtrim((string)$api->public_base(), '/');
    }
    if ($base === '' && defined('IA_PEERTUBE_BASE')) {
      $base = rtrim((string)IA_PEERTUBE_BASE, '/');
    }

    $item = function_exists('ia_stream_norm_comment_thread_tree')
      ? ia_stream_norm_comment_thread_tree(is_array($data) ? $data : [], $base)
      : $data;

    // Prune deleted/tombstone nodes recursively.
    $prune = function ($n) use (&$prune) {
      if (!is_array($n)) return $n;
      if (!empty($n['is_deleted'])) return null;
      if (isset($n['children']) && is_array($n['children'])) {
        $out = [];
        foreach ($n['children'] as $ch) {
          $p = $prune($ch);
          if ($p !== null) $out[] = $p;
        }
        $n['children'] = $out;
      }
      return $n;
    };
    if (is_array($item) && isset($item['root'])) {
      $r = $prune($item['root']);
      if ($r === null) {
        // Thread root deleted -> nothing to show
        $item['root'] = ['id' => '', 'text' => '', 'created_at' => '', 'created_ago' => '', 'is_deleted' => true, 'author' => ['id'=>0,'name'=>'','url'=>'','avatar'=>''], 'children' => []];
      } else {
        $item['root'] = $r;
      }
    }

    // Prune deleted/tombstone nodes from the tree so they vanish.
    $prune = function ($node) use (&$prune) {
      if (!is_array($node)) return $node;
      if (!empty($node['is_deleted'])) return null;
      if (isset($node['children']) && is_array($node['children'])) {
        $out = [];
        foreach ($node['children'] as $ch) {
          $p = $prune($ch);
          if (is_array($p)) $out[] = $p;
        }
        $node['children'] = $out;
      }
      return $node;
    };
    if (is_array($item) && isset($item['root']) && is_array($item['root'])) {
      $root = $prune($item['root']);
      $item['root'] = is_array($root) ? $root : ['id' => '', 'text' => '', 'is_deleted' => true, 'children' => []];
    }

    // Attach local comment vote counts + current user's vote to every node.
    $phpbb_user_id = self::current_phpbb_user_id();
    if (is_array($item)) $item = self::apply_votes_to_tree($item, $phpbb_user_id);

    return [
      'ok' => true,
      'meta' => [
        'video_id' => $video_id,
        'thread_id' => $thread_id,
      ],
      'item' => $item,
    ];
  }

  public static function create_thread(array $q): array {
    $video_id = isset($q['video_id']) ? trim((string)$q['video_id']) : '';
    $text = isset($q['text']) ? trim((string)$q['text']) : '';
    $video_id = mb_substr($video_id, 0, 64);
    $text = mb_substr($text, 0, 10000);

    if ($video_id === '' || $text === '') {
      return ['ok' => false, 'error' => 'Missing video_id or text'];
    }

    if (!class_exists('IA_Stream_Service_PeerTube_API')) {
      return ['ok' => false, 'error' => 'PeerTube API service missing'];
    }

    $api = new IA_Stream_Service_PeerTube_API();
    if (!$api->is_configured()) {
      return ['ok' => false, 'error' => 'PeerTube not configured'];
    }

    // PeerTube write actions MUST be performed as the current Atrium user.
    // Use the canonical token helper (lazy-mints and stores per phpBB user id).
    $tok = '';
    if (class_exists('IA_PeerTube_Token_Helper') && method_exists('IA_PeerTube_Token_Helper', 'get_token_for_current_user')) {
      try {
        $tok = (string) IA_PeerTube_Token_Helper::get_token_for_current_user();
      } catch (Throwable $e) {
        $tok = '';
      }
    }

    if (trim($tok) === '') {
      return [
        'ok' => false,
        'error' => 'PeerTube token missing for this account.',
        'code' => 'missing_user_token',
      ];
    }

    // Override any server/admin token configured for read-only calls.
    $api->set_token($tok);

    $raw = $api->create_comment_thread($video_id, $text);
    if (!$raw['ok']) {
      return [
        'ok' => false,
        'error' => $raw['error'] ?? 'PeerTube error',
        'body' => $raw['body'] ?? null,
      ];
    }

    return ['ok' => true, 'data' => $raw['data'] ?? null];
  }

  public static function reply(array $q): array {
    $video_id = isset($q['video_id']) ? trim((string)$q['video_id']) : '';
    $comment_id = isset($q['comment_id']) ? trim((string)$q['comment_id']) : '';
    $text = isset($q['text']) ? trim((string)$q['text']) : '';
    $video_id = mb_substr($video_id, 0, 64);
    $comment_id = mb_substr($comment_id, 0, 64);
    $text = mb_substr($text, 0, 10000);

    if ($video_id === '' || $comment_id === '' || $text === '') {
      return ['ok' => false, 'error' => 'Missing video_id, comment_id or text'];
    }

    if (!class_exists('IA_Stream_Service_PeerTube_API')) {
      return ['ok' => false, 'error' => 'PeerTube API service missing'];
    }

    $api = new IA_Stream_Service_PeerTube_API();
    if (!$api->is_configured()) {
      return ['ok' => false, 'error' => 'PeerTube not configured'];
    }

    // PeerTube write actions MUST be performed as the current Atrium user.
    // Use the canonical token helper (lazy-mints and stores per phpBB user id).
    $tok = '';
    if (class_exists('IA_PeerTube_Token_Helper') && method_exists('IA_PeerTube_Token_Helper', 'get_token_for_current_user')) {
      try {
        $tok = (string) IA_PeerTube_Token_Helper::get_token_for_current_user();
      } catch (Throwable $e) {
        $tok = '';
      }
    }

    if (trim($tok) === '') {
      return [
        'ok' => false,
        'error' => 'PeerTube token missing for this account.',
        'code' => 'missing_user_token',
      ];
    }

    // Override any server/admin token configured for read-only calls.
    $api->set_token($tok);

    $raw = $api->reply_to_comment($video_id, $comment_id, $text);
    if (!$raw['ok']) {
      return [
        'ok' => false,
        'error' => $raw['error'] ?? 'PeerTube error',
        'body' => $raw['body'] ?? null,
      ];
    }

    return ['ok' => true, 'data' => $raw['data'] ?? null];
  }
}
