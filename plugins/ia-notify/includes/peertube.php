<?php
if (!defined('ABSPATH')) exit;

function ia_notify_peertube_base_url(): string {
  if (class_exists('IA_Engine') && method_exists('IA_Engine', 'peertube_public_base_url')) {
    try {
      $u = trim((string) IA_Engine::peertube_public_base_url());
      if ($u !== '') return rtrim($u, '/');
    } catch (Throwable $e) {}
  }
  if (defined('IA_ENGINE_PEERTUBE_PUBLIC_BASE_URL')) {
    return rtrim((string) IA_ENGINE_PEERTUBE_PUBLIC_BASE_URL, '/');
  }
  return '';
}

function ia_notify_peertube_token_status(): array {
  if (!(class_exists('IA_PeerTube_Token_Helper') && method_exists('IA_PeerTube_Token_Helper', 'get_token_status_for_current_user'))) {
    return ['ok' => false, 'token' => '', 'code' => 'token_helper_missing'];
  }
  try {
    $status = IA_PeerTube_Token_Helper::get_token_status_for_current_user();
    return is_array($status) ? $status : ['ok' => false, 'token' => '', 'code' => 'bad_token_status'];
  } catch (Throwable $e) {
    return ['ok' => false, 'token' => '', 'code' => 'token_exception'];
  }
}

function ia_notify_peertube_request(string $method, string $path, array $args = []): array {
  $base = ia_notify_peertube_base_url();
  $status = ia_notify_peertube_token_status();
  $token = trim((string)($status['token'] ?? ''));
  if ($base === '' || empty($status['ok']) || $token === '') {
    return ['ok' => false, 'status' => 0, 'json' => null, 'code' => (string)($status['code'] ?? 'unavailable')];
  }

  $url = $base . $path;
  if (!empty($args['query']) && is_array($args['query'])) {
    $url = add_query_arg($args['query'], $url);
  }

  $req = [
    'method' => strtoupper($method),
    'timeout' => 12,
    'headers' => [
      'Authorization' => 'Bearer ' . $token,
      'Accept' => 'application/json',
    ],
  ];

  if (array_key_exists('body', $args)) {
    $req['headers']['Content-Type'] = 'application/json';
    $req['body'] = wp_json_encode($args['body']);
  }

  $res = wp_remote_request($url, $req);
  if (is_wp_error($res)) {
    return ['ok' => false, 'status' => 0, 'json' => null, 'code' => 'wp_error'];
  }

  $code = (int) wp_remote_retrieve_response_code($res);
  $body = wp_remote_retrieve_body($res);
  $json = null;
  if (is_string($body) && $body !== '') {
    $decoded = json_decode($body, true);
    if (is_array($decoded)) $json = $decoded;
  }

  return ['ok' => $code >= 200 && $code < 300, 'status' => $code, 'json' => $json, 'code' => ''];
}

function ia_notify_pt_pick_actor(array $row): array {
  $candidates = [
    $row['byAccount'] ?? null,
    $row['fromAccount'] ?? null,
    $row['account'] ?? null,
    $row['video']['account'] ?? null,
    $row['videoComment']['account'] ?? null,
    $row['comment']['account'] ?? null,
  ];
  foreach ($candidates as $cand) {
    if (!is_array($cand)) continue;
    $name = trim((string)($cand['displayName'] ?? $cand['name'] ?? ''));
    $avatar = '';
    if (!empty($cand['avatar']) && is_array($cand['avatar'])) {
      $avatar = (string)($cand['avatar']['path'] ?? $cand['avatar']['url'] ?? '');
      if ($avatar !== '' && str_starts_with($avatar, '/')) {
        $base = ia_notify_peertube_base_url();
        if ($base !== '') $avatar = $base . $avatar;
      }
    }
    if ($name !== '' || $avatar !== '') {
      return ['name' => $name, 'avatar' => $avatar];
    }
  }
  return ['name' => 'Stream', 'avatar' => ''];
}

function ia_notify_pt_extract_video_id(array $row): string {
  $candidates = [
    $row['video'] ?? null,
    $row['videoComment']['video'] ?? null,
    $row['comment']['video'] ?? null,
  ];
  foreach ($candidates as $cand) {
    if (!is_array($cand)) continue;
    foreach (['uuid', 'shortUUID', 'shortUuid', 'id'] as $k) {
      if (!empty($cand[$k])) return trim((string)$cand[$k]);
    }
  }

  foreach (['url', 'watchUrl'] as $k) {
    $url = '';
    if (!empty($row[$k]) && is_string($row[$k])) $url = (string)$row[$k];
    if ($url === '') {
      foreach (['video', 'videoComment', 'comment'] as $kk) {
        if (!empty($row[$kk]) && is_array($row[$kk]) && !empty($row[$kk][$k]) && is_string($row[$kk][$k])) {
          $url = (string)$row[$kk][$k];
          break;
        }
      }
    }
    if ($url === '') continue;
    if (preg_match('~/w/([^/?#;]+)~', $url, $m)) return trim((string)$m[1]);
    if (preg_match('~/videos/watch/([^/?#;]+)~', $url, $m)) return trim((string)$m[1]);
    if (preg_match('~/videos/([^/?#;]+)~', $url, $m)) return trim((string)$m[1]);
  }

  return '';
}

function ia_notify_pt_extract_comment_ids(array $row): array {
  $node = null;
  foreach (['videoComment', 'comment'] as $k) {
    if (!empty($row[$k]) && is_array($row[$k])) { $node = $row[$k]; break; }
  }
  if (!is_array($node)) return ['comment_id' => '', 'reply_id' => ''];

  $id = trim((string)($node['id'] ?? $node['commentId'] ?? ''));
  $thread = trim((string)($node['threadId'] ?? $node['thread_id'] ?? $node['rootId'] ?? $node['root_id'] ?? ''));
  $parent = trim((string)($node['inReplyToCommentId'] ?? $node['in_reply_to_comment_id'] ?? $node['parentId'] ?? $node['parent_id'] ?? ''));

  $reply_id = '';
  $comment_id = $id;
  if ($thread !== '' && $thread !== $id) {
    $comment_id = $thread;
    $reply_id = $id;
  } elseif ($parent !== '' && $parent !== $id) {
    $comment_id = $parent;
    $reply_id = $id;
  }

  return ['comment_id' => $comment_id, 'reply_id' => $reply_id];
}

function ia_notify_pt_local_url(array $row): string {
  $video_id = ia_notify_pt_extract_video_id($row);
  $ids = ia_notify_pt_extract_comment_ids($row);
  $args = ['tab' => 'stream'];
  if ($video_id !== '') $args['video'] = $video_id;
  if ($ids['comment_id'] !== '' || $ids['reply_id'] !== '') $args['focus'] = 'comments';
  if ($ids['comment_id'] !== '') $args['stream_comment'] = $ids['comment_id'];
  if ($ids['reply_id'] !== '') $args['stream_reply'] = $ids['reply_id'];
  return add_query_arg($args, home_url('/'));
}

function ia_notify_pt_pick_url(array $row): string {
  $local = ia_notify_pt_local_url($row);
  if (!empty($row['videoComment']) || !empty($row['comment']) || strpos($local, 'video=') !== false) return $local;
  foreach (['url', 'watchUrl'] as $k) {
    if (!empty($row[$k]) && is_string($row[$k])) return (string)$row[$k];
  }
  foreach (['video', 'videoComment', 'comment'] as $k) {
    if (empty($row[$k]) || !is_array($row[$k])) continue;
    $sub = $row[$k];
    foreach (['url', 'watchUrl'] as $kk) {
      if (!empty($sub[$kk]) && is_string($sub[$kk])) return (string)$sub[$kk];
    }
  }
  return $local;
}

function ia_notify_pt_pick_text(array $row): array {
  $type = trim((string)($row['type'] ?? 'stream_notification'));
  $videoName = '';
  if (!empty($row['video']) && is_array($row['video'])) {
    $videoName = trim((string)($row['video']['name'] ?? $row['video']['title'] ?? ''));
  }
  if ($videoName === '' && !empty($row['videoComment']) && is_array($row['videoComment'])) {
    $videoName = trim((string)($row['videoComment']['video']['name'] ?? $row['videoComment']['video']['title'] ?? ''));
  }

  $text = 'New Stream notification.';
  if ($videoName !== '') {
    $text = 'New Stream activity on ' . $videoName . '.';
  }

  $detail = '';
  foreach ([
    $row['videoComment']['text'] ?? null,
    $row['comment']['text'] ?? null,
    $row['video']['description'] ?? null,
  ] as $cand) {
    if (is_string($cand) && trim($cand) !== '') {
      $detail = ia_notify_text_excerpt($cand, 140);
      break;
    }
  }

  return ['type' => $type, 'text' => $text, 'detail' => $detail, 'context' => $videoName];
}

function ia_notify_peertube_notifications(int $limit = 20): array {
  $limit = max(1, min(50, $limit));
  $res = ia_notify_peertube_request('GET', '/api/v1/users/me/notifications', [
    'query' => ['start' => 0, 'count' => $limit]
  ]);
  if (empty($res['ok']) || !is_array($res['json'])) {
    return ['items' => [], 'unread_count' => 0];
  }

  $list = [];
  $json = $res['json'];
  $rows = [];
  foreach (['data', 'items'] as $k) {
    if (!empty($json[$k]) && is_array($json[$k])) { $rows = $json[$k]; break; }
  }
  if (!$rows && array_is_list($json)) $rows = $json;

  foreach ($rows as $row) {
    if (!is_array($row)) continue;
    $id = (int)($row['id'] ?? 0);
    if ($id <= 0) continue;
    $actor = ia_notify_pt_pick_actor($row);
    $txt = ia_notify_pt_pick_text($row);
    $created = (string)($row['createdAt'] ?? $row['created_at'] ?? '');
    if ($created !== '') {
      $created = str_replace('T', ' ', substr($created, 0, 19));
    }
    $list[] = [
      'id' => 'pt:' . $id,
      'recipient_phpbb_id' => 0,
      'actor_phpbb_id' => 0,
      'type' => 'stream_notification',
      'object_type' => 'stream_notification',
      'object_id' => $id,
      'url' => ia_notify_pt_pick_url($row),
      'text' => $txt['text'],
      'created_at' => $created,
      'read_at' => !empty($row['read']) ? $created : null,
      'meta' => [
        'actor_name' => $actor['name'],
        'actor_avatar' => $actor['avatar'],
        'stream_type' => $txt['type'],
      ],
      'source' => 'stream',
      'detail' => $txt['detail'],
      'context' => $txt['context'],
    ];
  }

  $unread = 0;
  if (isset($json['totalUnread']) && is_numeric($json['totalUnread'])) {
    $unread = (int)$json['totalUnread'];
  } else {
    foreach ($list as $it) if (empty($it['read_at'])) $unread++;
  }

  return ['items' => $list, 'unread_count' => $unread];
}

function ia_notify_peertube_mark_read(array $ids = [], bool $all = false): bool {
  if ($all) {
    $res = ia_notify_peertube_request('POST', '/api/v1/users/me/notifications/read-all');
    return !empty($res['ok']);
  }
  $ids = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
  if (!$ids) return true;
  $res = ia_notify_peertube_request('POST', '/api/v1/users/me/notifications/read', ['body' => ['ids' => $ids]]);
  return !empty($res['ok']);
}
