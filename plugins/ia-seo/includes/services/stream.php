<?php
if (!defined('ABSPATH')) exit;

class IA_SEO_Stream_Service {
  private $public_base = '';

  public function __construct() {
    if (class_exists('IA_Engine') && method_exists('IA_Engine', 'peertube_public_base_url')) {
      try {
        $this->public_base = rtrim(trim((string) IA_Engine::peertube_public_base_url()), '/');
      } catch (Throwable $e) {
        $this->public_base = '';
      }
    }

    if ($this->public_base === '' && defined('IA_PEERTUBE_PUBLIC_BASE')) {
      $this->public_base = rtrim(trim((string) IA_PEERTUBE_PUBLIC_BASE), '/');
    }
    if ($this->public_base === '' && defined('IA_PEERTUBE_BASE')) {
      $this->public_base = rtrim(trim((string) IA_PEERTUBE_BASE), '/');
    }
  }

  public function ok(): bool {
    return $this->public_base !== '';
  }

  public function public_base(): string {
    return $this->public_base;
  }

  public function get_channels(int $limit): array {
    return $this->collect('/api/v1/video-channels', $limit, function (array $row): ?array {
      $handle = '';
      if (isset($row['name']) && is_string($row['name'])) {
        $handle = trim((string) $row['name']);
      }
      if ($handle === '' && !empty($row['url'])) {
        $path = trim((string) wp_parse_url((string) $row['url'], PHP_URL_PATH), '/');
        if ($path !== '') {
          $bits = explode('/', $path);
          $handle = trim((string) end($bits));
        }
      }
      if ($handle === '') return null;

      $lastmod = ia_seo_stream_pick_time($row, ['updatedAt', 'createdAt']);

      return [
        'handle' => $handle,
        'display_name' => trim((string) ($row['displayName'] ?? $row['display_name'] ?? '')),
        'lastmod_unix' => $lastmod,
      ];
    });
  }

  public function get_channel_by_handle(string $handle): ?array {
    $handle = trim((string) $handle);
    if ($handle === '') return null;
    $res = $this->request('/api/v1/video-channels/' . rawurlencode($handle));
    if (empty($res['ok']) || !is_array($res['data'])) return null;
    $row = $res['data'];
    return [
      'handle' => $handle,
      'display_name' => trim((string) ($row['displayName'] ?? $row['display_name'] ?? $row['name'] ?? '')),
      'description' => trim((string) ($row['description'] ?? '')),
      'lastmod_unix' => ia_seo_stream_pick_time($row, ['updatedAt', 'createdAt']),
      'video_count' => (int)($row['videosCount'] ?? $row['videos_count'] ?? 0),
    ];
  }

  public function get_video_by_id(string $id): ?array {
    $id = trim((string) $id);
    if ($id === '') return null;
    $res = $this->request('/api/v1/videos/' . rawurlencode($id));
    if (empty($res['ok']) || !is_array($res['data'])) return null;
    $row = $res['data'];
    $desc = trim((string) ($row['description'] ?? ''));
    $thumb = '';
    if (!empty($row['thumbnailPath'])) {
      $thumb = $this->public_base . (strpos((string)$row['thumbnailPath'], '/') === 0 ? '' : '/') . ltrim((string)$row['thumbnailPath'], '/');
    } elseif (!empty($row['previewPath'])) {
      $thumb = $this->public_base . (strpos((string)$row['previewPath'], '/') === 0 ? '' : '/') . ltrim((string)$row['previewPath'], '/');
    }
    $channel = '';
    if (isset($row['channel']) && is_array($row['channel'])) {
      $channel = trim((string) ($row['channel']['name'] ?? ''));
    }
    return [
      'id' => trim((string) ($row['uuid'] ?? $row['id'] ?? $id)),
      'name' => trim((string) ($row['name'] ?? '')),
      'description' => $desc,
      'thumbnail_url' => $thumb,
      'published_at' => trim((string) ($row['publishedAt'] ?? $row['createdAt'] ?? '')),
      'lastmod_unix' => ia_seo_stream_pick_time($row, ['updatedAt', 'publishedAt', 'createdAt']),
      'duration' => (int)($row['duration'] ?? 0),
      'channel_handle' => $channel,
      'tags' => isset($row['tags']) && is_array($row['tags']) ? $row['tags'] : [],
      'language' => is_array($row['language'] ?? null) ? (string)($row['language']['label'] ?? '') : '',
      'category' => is_array($row['category'] ?? null) ? (string)($row['category']['label'] ?? '') : '',
      'licence' => is_array($row['licence'] ?? null) ? (string)($row['licence']['label'] ?? '') : '',
    ];
  }

  public function get_video_comments(string $id, int $limit = 5): array {
    $id = trim((string) $id);
    $limit = max(0, min(20, $limit));
    if ($id === '' || $limit <= 0) return [];
    $res = $this->request('/api/v1/videos/' . rawurlencode($id) . '/comment-threads', ['start' => 0, 'count' => $limit]);
    if (empty($res['ok']) || !is_array($res['data'])) return [];
    $rows = [];
    if (isset($res['data']['data']) && is_array($res['data']['data'])) $rows = $res['data']['data'];
    $out = [];
    foreach ($rows as $row) {
      if (!is_array($row)) continue;
      $text = trim((string) ($row['text'] ?? ''));
      if ($text === '' and isset($row['comment']) and is_array($row['comment'])) {
        $text = trim((string) ($row['comment']['text'] ?? ''));
      }
      $item = ['text' => $text, 'replies' => []];
      $children = [];
      if (isset($row['children']) && is_array($row['children'])) $children = $row['children'];
      elseif (isset($row['comment']) && is_array($row['comment']) && isset($row['comment']['children']) && is_array($row['comment']['children'])) $children = $row['comment']['children'];
      foreach ($children as $child) {
        if (!is_array($child)) continue;
        $reply = trim((string) ($child['text'] ?? ''));
        if ($reply !== '') $item['replies'][] = $reply;
      }
      if ($item['text'] !== '' || $item['replies']) $out[] = $item;
    }
    return $out;
  }

  public function get_videos(int $limit): array {
    return $this->collect('/api/v1/videos', $limit, function (array $row): ?array {
      $id = '';
      if (isset($row['uuid']) && is_string($row['uuid'])) {
        $id = trim((string) $row['uuid']);
      }
      if ($id === '' && isset($row['id']) && is_scalar($row['id'])) {
        $id = trim((string) $row['id']);
      }
      if ($id === '') return null;

      $lastmod = ia_seo_stream_pick_time($row, ['updatedAt', 'publishedAt', 'createdAt']);

      return [
        'id' => $id,
        'lastmod_unix' => $lastmod,
      ];
    });
  }

  private function collect(string $path, int $limit, callable $map): array {
    $limit = max(0, $limit);
    if (!$this->ok() || $limit <= 0) return [];

    $out = [];
    $start = 0;
    $count = min(100, $limit);
    $seen = [];

    while (count($out) < $limit) {
      $res = $this->request($path, [
        'start' => $start,
        'count' => $count,
        'sort'  => '-publishedAt',
      ]);
      if (empty($res['ok'])) break;

      $data = $res['data'] ?? [];
      $rows = [];
      if (is_array($data) && isset($data['data']) && is_array($data['data'])) {
        $rows = $data['data'];
      } elseif (is_array($data)) {
        $rows = $data;
      }
      if (!$rows) break;

      $added = 0;
      foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $item = $map($row);
        if (!is_array($item)) continue;
        $key = md5(wp_json_encode($item));
        if (isset($seen[$key])) continue;
        $seen[$key] = 1;
        $out[] = $item;
        $added++;
        if (count($out) >= $limit) break;
      }

      if ($added <= 0 || count($rows) < $count) break;
      $start += $count;
    }

    return $out;
  }

  private function request(string $path, array $params = []): array {
    if (!$this->ok()) return ['ok' => false, 'error' => 'not_configured'];

    $url = $this->public_base . $path;
    if ($params) {
      $url = add_query_arg($params, $url);
    }

    $res = wp_remote_get($url, [
      'timeout' => 20,
      'redirection' => 3,
      'headers' => [
        'Accept' => 'application/json',
      ],
    ]);

    if (is_wp_error($res)) {
      return ['ok' => false, 'error' => $res->get_error_message()];
    }

    $code = (int) wp_remote_retrieve_response_code($res);
    $body = (string) wp_remote_retrieve_body($res);
    $data = json_decode($body, true);

    if ($code < 200 || $code >= 300 || !is_array($data)) {
      return ['ok' => false, 'error' => 'http_' . $code, 'body' => $body];
    }

    return ['ok' => true, 'data' => $data];
  }
}

if (!function_exists('ia_seo_stream_pick_time')) {
  function ia_seo_stream_pick_time(array $row, array $keys): int {
    foreach ($keys as $key) {
      if (!isset($row[$key])) continue;
      $raw = trim((string) $row[$key]);
      if ($raw === '') continue;
      $ts = strtotime($raw);
      if (is_int($ts) && $ts > 0) return $ts;
    }
    return time();
  }
}
