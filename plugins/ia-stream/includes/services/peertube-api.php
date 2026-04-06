<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Stream PeerTube API Service (HTTP client)
 *
 * Contract:
 * - Prefer IA_Engine as source of truth:
 *     IA_Engine::peertube_internal_base_url()
 *     IA_Engine::peertube_public_base_url()
 *     IA_Engine::peertube_api_token()
 * - Fallback to constants if engine missing:
 *     IA_PEERTUBE_INTERNAL_BASE   (optional)
 *     IA_PEERTUBE_PUBLIC_BASE     (optional)
 *     IA_PEERTUBE_BASE            (legacy: treated as public base)
 *     IA_PEERTUBE_TOKEN           (optional)
 *
 * Returns consistent arrays:
 *   ['ok'=>true,'data'=>...]
 *   ['ok'=>false,'error'=>..., 'body'=>...]
 */
final class IA_Stream_Service_PeerTube_API {

  /** @var string */
  private $internal_base = '';

  /** @var string */
  private $public_base = '';

  /** @var string */
  private $token = '';

  public function __construct() {
    $cfg = $this->resolve_config();
    $this->internal_base = $cfg['internal_base'];
    $this->public_base   = $cfg['public_base'];
    $this->token         = $cfg['token'];
  }

  /**
   * Override the bearer token used for subsequent requests.
   *
   * Used for write actions (comments) so we can post as the currently logged-in
   * Atrium user when that user has a minted PeerTube token in IA Auth.
   */
  public function set_token(string $token): void {
    $this->token = trim((string)$token);
  }

  /* ---------------------------
   * Configuration
   * ------------------------- */

  public function is_configured(): bool {
    // For read-only Stream, public base is the minimum requirement.
    return is_string($this->public_base) && $this->public_base !== '';
  }

  public function internal_base(): string { return $this->internal_base; }
  public function public_base(): string { return $this->public_base; }
  public function token(): string { return $this->token; }

  private function resolve_config(): array {
    $internal = '';
    $public   = '';
    $token    = '';

    // 1) IA Engine (canonical)
    if (class_exists('IA_Engine')) {
      if (method_exists('IA_Engine', 'peertube_internal_base_url')) {
        $internal = (string) IA_Engine::peertube_internal_base_url();
      }
      if (method_exists('IA_Engine', 'peertube_public_base_url')) {
        $public = (string) IA_Engine::peertube_public_base_url();
      }
      if (method_exists('IA_Engine', 'peertube_api_token')) {
        $token = (string) IA_Engine::peertube_api_token();
      }
    }

    // 2) Constants fallback (support legacy)
    if ($internal === '' && defined('IA_PEERTUBE_INTERNAL_BASE')) {
      $internal = (string) IA_PEERTUBE_INTERNAL_BASE;
    }
    if ($public === '' && defined('IA_PEERTUBE_PUBLIC_BASE')) {
      $public = (string) IA_PEERTUBE_PUBLIC_BASE;
    }

    // Legacy: IA_PEERTUBE_BASE treated as public base
    if ($public === '' && defined('IA_PEERTUBE_BASE')) {
      $public = (string) IA_PEERTUBE_BASE;
    }

    if ($token === '' && defined('IA_PEERTUBE_TOKEN')) {
      $token = (string) IA_PEERTUBE_TOKEN;
    }

    $internal = rtrim(trim($internal), '/');
    $public   = rtrim(trim($public), '/');

    // If only one is set, reuse it for the other (avoid partial config footguns)
    if ($public === '' && $internal !== '') $public = $internal;
    if ($internal === '' && $public !== '') $internal = $public;

    return [
      'internal_base' => $internal,
      'public_base'   => $public,
      'token'         => $token,
    ];
  }

  /* ---------------------------
   * Public API (read)
   * ------------------------- */

  public function get_videos(array $q): array {
    if (!$this->is_configured()) return $this->not_configured();

    $page = max(1, (int)($q['page'] ?? 1));
    $per  = min(50, max(1, (int)($q['per_page'] ?? 20)));

    // PeerTube uses start/count
    $start = ($page - 1) * $per;

    $params = [
      'start' => $start,
      'count' => $per,
    ];

    // Optional common knobs
    if (!empty($q['sort']))   $params['sort'] = (string)$q['sort'];
    if (!empty($q['search'])) $params['search'] = (string)$q['search'];
    if (!empty($q['nsfw']))   $params['nsfw'] = (string)$q['nsfw'];

    $path = '/api/v1/videos';
    return $this->request('GET', $path, $params);
  }

  public function get_channels(array $q): array {
    if (!$this->is_configured()) return $this->not_configured();

    $page = max(1, (int)($q['page'] ?? 1));
    $per  = min(50, max(1, (int)($q['per_page'] ?? 20)));
    $start = ($page - 1) * $per;

    $params = [
      'start' => $start,
      'count' => $per,
    ];

    if (!empty($q['search'])) $params['search'] = (string)$q['search'];

    $path = '/api/v1/video-channels';
    return $this->request('GET', $path, $params);
  }

  public function get_channel_videos(string $handle, array $q): array {
    if (!$this->is_configured()) return $this->not_configured();

    $handle = trim((string)$handle);
    if ($handle === '') return ['ok' => false, 'error' => 'Missing channel handle'];

    $page = max(1, (int)($q['page'] ?? 1));
    $per  = min(50, max(1, (int)($q['per_page'] ?? 20)));
    $start = ($page - 1) * $per;

    $params = [
      'start' => $start,
      'count' => $per,
    ];

    if (!empty($q['sort']))   $params['sort'] = (string)$q['sort'];
    if (!empty($q['search'])) $params['search'] = (string)$q['search'];
    if (!empty($q['nsfw']))   $params['nsfw'] = (string)$q['nsfw'];

    $path = '/api/v1/video-channels/' . rawurlencode($handle) . '/videos';
    return $this->request('GET', $path, $params);
  }

  public function get_subscription_videos(array $q): array {
    if (!$this->is_configured()) return $this->not_configured();
    if ($this->token === '') return ['ok' => false, 'error' => 'Missing user token'];

    $page = max(1, (int)($q['page'] ?? 1));
    $per  = min(50, max(1, (int)($q['per_page'] ?? 20)));
    $start = ($page - 1) * $per;

    $params = [
      'start' => $start,
      'count' => $per,
    ];

    if (!empty($q['sort']))   $params['sort'] = (string)$q['sort'];
    if (!empty($q['search'])) $params['search'] = (string)$q['search'];
    if (!empty($q['nsfw']))   $params['nsfw'] = (string)$q['nsfw'];

    $path = '/api/v1/users/me/subscriptions/videos';
    return $this->request('GET', $path, $params);
  }

  public function get_video(string $uuid): array {
    if (!$this->is_configured()) return $this->not_configured();

    $uuid = trim((string)$uuid);
    if ($uuid === '') return ['ok' => false, 'error' => 'Missing video id'];

    $path = '/api/v1/videos/' . rawurlencode($uuid);
    return $this->request('GET', $path);
  }

  /**
   * Like/dislike a video.
   *
   * OpenAPI: PUT /api/v1/videos/{id}/rate  { rating: like|dislike }
   * Requires an OAuth user token.
   */
  public function rate_video(string $id_or_uuid, string $rating): array {
    if (!$this->is_configured()) return $this->not_configured();

    $id_or_uuid = trim((string) $id_or_uuid);
    if ($id_or_uuid === '') return ['ok' => false, 'error' => 'Missing video id'];

    $rating = trim((string) $rating);
    if ($rating !== 'like' && $rating !== 'dislike') {
      return ['ok' => false, 'error' => 'Invalid rating'];
    }

    $path = '/api/v1/videos/' . rawurlencode($id_or_uuid) . '/rate';
    return $this->request('PUT', $path, [], ['rating' => $rating]);
  }

  public function get_comments(string $uuid, array $q = []): array {
    if (!$this->is_configured()) return $this->not_configured();

    $uuid = trim((string)$uuid);
    if ($uuid === '') return ['ok' => false, 'error' => 'Missing video id'];

    $page = max(1, (int)($q['page'] ?? 1));
    $per  = min(50, max(1, (int)($q['per_page'] ?? 20)));
    $start = ($page - 1) * $per;

    $params = [
      'start' => $start,
      'count' => $per,
    ];

    $path = '/api/v1/videos/' . rawurlencode($uuid) . '/comment-threads';
    return $this->request('GET', $path, $params);
  }

  public function get_comment_thread(string $uuid, string $thread_id): array {
    if (!$this->is_configured()) return $this->not_configured();

    $uuid = trim((string)$uuid);
    $thread_id = trim((string)$thread_id);

    if ($uuid === '') return ['ok' => false, 'error' => 'Missing video id'];
    if ($thread_id === '') return ['ok' => false, 'error' => 'Missing thread id'];

    $path = '/api/v1/videos/' . rawurlencode($uuid) . '/comment-threads/' . rawurlencode($thread_id);
    return $this->request('GET', $path);
  }

  public function create_comment_thread(string $uuid, string $text): array {
    if (!$this->is_configured()) return $this->not_configured();

    $uuid = trim((string)$uuid);
    $text = trim((string)$text);

    if ($uuid === '') return ['ok' => false, 'error' => 'Missing video id'];
    if ($text === '') return ['ok' => false, 'error' => 'Missing text'];

    $path = '/api/v1/videos/' . rawurlencode($uuid) . '/comment-threads';
    return $this->request('POST', $path, [], ['text' => $text]);
  }

  public function reply_to_comment(string $uuid, string $comment_id, string $text): array {
    if (!$this->is_configured()) return $this->not_configured();

    $uuid = trim((string)$uuid);
    $comment_id = trim((string)$comment_id);
    $text = trim((string)$text);

    if ($uuid === '') return ['ok' => false, 'error' => 'Missing video id'];
    if ($comment_id === '') return ['ok' => false, 'error' => 'Missing comment id'];
    if ($text === '') return ['ok' => false, 'error' => 'Missing text'];

    $path = '/api/v1/videos/' . rawurlencode($uuid) . '/comments/' . rawurlencode($comment_id);
    return $this->request('POST', $path, [], ['text' => $text]);
  }

  /**
   * Delete a comment or reply.
   * OpenAPI: DELETE /api/v1/videos/{id}/comments/{commentId}
   * Requires an OAuth user token.
   */
  public function delete_comment(string $id_or_uuid, string $comment_id): array {
    if (!$this->is_configured()) return $this->not_configured();

    $id_or_uuid = trim((string)$id_or_uuid);
    $comment_id = trim((string)$comment_id);
    if ($id_or_uuid === '') return ['ok' => false, 'error' => 'Missing video id'];
    if ($comment_id === '') return ['ok' => false, 'error' => 'Missing comment id'];

    $path = '/api/v1/videos/' . rawurlencode($id_or_uuid) . '/comments/' . rawurlencode($comment_id);
    $r = $this->request('DELETE', $path);

    // PeerTube may return 409 Conflict when attempting to delete an already-deleted comment.
    // Atrium UX: treat repeat deletion as idempotent success.
    if (!is_array($r) || !empty($r['ok'])) return $r;
    $err = isset($r['error']) ? (string)$r['error'] : '';
    if (strpos($err, 'PeerTube HTTP 409') !== false) {
      return ['ok' => true, 'already_deleted' => true, 'raw' => $r];
    }

    return $r;
  }

  public function get_me(): array {
    if (!$this->is_configured()) return $this->not_configured();
    if ($this->token === '') return ['ok' => false, 'error' => 'Missing user token'];
    return $this->request('GET', '/api/v1/users/me');
  }

  public function get_account_channels(string $account_name, array $q = []): array {
    if (!$this->is_configured()) return $this->not_configured();
    $account_name = trim((string) $account_name);
    if ($account_name === '') return ['ok' => false, 'error' => 'Missing account name'];

    $params = [
      'start' => max(0, (int) ($q['start'] ?? 0)),
      'count' => min(100, max(1, (int) ($q['count'] ?? 100))),
      'sort'  => (string) ($q['sort'] ?? 'name'),
    ];
    if (isset($q['includeCollaborations'])) $params['includeCollaborations'] = !empty($q['includeCollaborations']) ? 'true' : 'false';
    if (!empty($q['search'])) $params['search'] = (string) $q['search'];

    return $this->request('GET', '/api/v1/accounts/' . rawurlencode($account_name) . '/video-channels', $params);
  }

  public function get_account_playlists(string $account_name, array $q = []): array {
    if (!$this->is_configured()) return $this->not_configured();
    $account_name = trim((string) $account_name);
    if ($account_name === '') return ['ok' => false, 'error' => 'Missing account name'];

    $params = [
      'start' => max(0, (int) ($q['start'] ?? 0)),
      'count' => min(100, max(1, (int) ($q['count'] ?? 100))),
      'sort'  => (string) ($q['sort'] ?? '-createdAt'),
      'playlistType' => (int) ($q['playlistType'] ?? 1),
    ];
    if (isset($q['includeCollaborations'])) $params['includeCollaborations'] = !empty($q['includeCollaborations']) ? 'true' : 'false';
    if (!empty($q['search'])) $params['search'] = (string) $q['search'];
    if (!empty($q['channelNameOneOf'])) $params['channelNameOneOf'] = (string) $q['channelNameOneOf'];

    return $this->request('GET', '/api/v1/accounts/' . rawurlencode($account_name) . '/video-playlists', $params);
  }

  public function get_video_categories(): array {
    if (!$this->is_configured()) return $this->not_configured();
    return $this->request('GET', '/api/v1/videos/categories');
  }

  public function get_video_licences(): array {
    if (!$this->is_configured()) return $this->not_configured();
    return $this->request('GET', '/api/v1/videos/licences');
  }

  public function get_video_languages(): array {
    if (!$this->is_configured()) return $this->not_configured();
    return $this->request('GET', '/api/v1/videos/languages');
  }

  public function get_video_privacies(): array {
    if (!$this->is_configured()) return $this->not_configured();
    return $this->request('GET', '/api/v1/videos/privacies');
  }

  public function upload_video(array $fields, string $tmp_path, string $filename, string $mime_type = ''): array {
    if (!$this->is_configured()) return $this->not_configured();
    if ($this->token === '') return ['ok' => false, 'error' => 'Missing user token'];
    if ($tmp_path === '' || !file_exists($tmp_path)) return ['ok' => false, 'error' => 'Missing upload file'];

    $multipart = $fields;
    if (class_exists('CURLFile')) {
      $multipart['videofile'] = new CURLFile($tmp_path, $mime_type ?: 'application/octet-stream', $filename ?: basename($tmp_path));
    } else {
      $multipart['videofile'] = '@' . $tmp_path;
    }

    return $this->request_multipart('POST', '/api/v1/videos/upload', $multipart);
  }

  public function add_video_to_playlist(int $playlist_id, $video_id): array {
    if (!$this->is_configured()) return $this->not_configured();
    if ($this->token === '') return ['ok' => false, 'error' => 'Missing user token'];
    if ($playlist_id <= 0) return ['ok' => false, 'error' => 'Missing playlist id'];
    if ($video_id === null || $video_id === '') return ['ok' => false, 'error' => 'Missing video id'];

    return $this->request('POST', '/api/v1/video-playlists/' . rawurlencode((string) $playlist_id) . '/videos', [], [
      'videoId' => is_numeric($video_id) ? (int) $video_id : (string) $video_id,
    ]);
  }

  /* ---------------------------
   * HTTP
   * ------------------------- */

  private function request_multipart(string $method, string $path, array $fields = [], array $query = []): array {
    $method = strtoupper(trim((string) $method));
    $path   = '/' . ltrim((string) $path, '/');

    $base = $this->internal_base !== '' ? $this->internal_base : $this->public_base;
    if ($base === '') return $this->not_configured();
    if (!function_exists('curl_init')) {
      return ['ok' => false, 'error' => 'cURL is not available for multipart upload'];
    }

    $url = rtrim($base, '/') . $path;
    if (!empty($query)) {
      $qs = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
      $url .= (strpos($url, '?') === false ? '?' : '&') . $qs;
    }

    $ch = curl_init($url);
    if ($ch === false) return ['ok' => false, 'error' => 'Unable to initialize cURL'];

    $headers = ['Accept: application/json'];
    if ($this->token !== '') $headers[] = 'Authorization: Bearer ' . $this->token;

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 900);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

    $txt = curl_exec($ch);
    if ($txt === false) {
      $err = curl_error($ch);
      curl_close($ch);
      return ['ok' => false, 'error' => $err ?: 'cURL multipart request failed'];
    }

    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $json = json_decode((string) $txt, true);
    if ($code < 200 || $code >= 300) {
      return ['ok' => false, 'error' => 'PeerTube HTTP ' . $code, 'body' => is_array($json) ? $json : $txt];
    }
    if ($json === null && $txt !== '' && $txt !== 'null') {
      return ['ok' => false, 'error' => 'Invalid JSON from PeerTube', 'body' => $txt];
    }

    return ['ok' => true, 'data' => $json];
  }

  private function not_configured(): array {
    return [
      'ok'    => false,
      'error' => 'PeerTube not configured (missing base URL). Configure in IA Engine or define IA_PEERTUBE_BASE.',
    ];
  }

  private function request(string $method, string $path, array $query = [], $body = null): array {
    $method = strtoupper(trim((string)$method));
    $path   = '/' . ltrim((string)$path, '/');

    // For server-to-server calls, prefer internal base
    $base = $this->internal_base !== '' ? $this->internal_base : $this->public_base;
    if ($base === '') return $this->not_configured();

    $url = rtrim($base, '/') . $path;

    if (!empty($query)) {
      $qs = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
      $url .= (strpos($url, '?') === false ? '?' : '&') . $qs;
    }

    $args = [
      'method'  => $method,
      'timeout' => 8,
      'headers' => [
        'Accept' => 'application/json',
      ],
    ];

    // Optional token (some endpoints don’t require auth; token helps for privileged views)
    if ($this->token !== '') {
      $args['headers']['Authorization'] = 'Bearer ' . $this->token;
    }

    if ($body !== null) {
      $args['headers']['Content-Type'] = 'application/json; charset=UTF-8';
      $args['body'] = wp_json_encode($body);
    }

    try {
      $res = wp_remote_request($url, $args);

      if (is_wp_error($res)) {
        return ['ok' => false, 'error' => $res->get_error_message()];
      }

      $code = (int) wp_remote_retrieve_response_code($res);
      $txt  = (string) wp_remote_retrieve_body($res);

      // If PeerTube/proxy returned HTML, bubble it explicitly (this is what breaks JSON parsing downstream)
      $json = json_decode($txt, true);

      if ($code < 200 || $code >= 300) {
        return [
          'ok'    => false,
          'error' => 'PeerTube HTTP ' . $code,
          'body'  => is_array($json) ? $json : $txt,
        ];
      }

      if ($json === null && $txt !== '' && $txt !== 'null') {
        return ['ok' => false, 'error' => 'Invalid JSON from PeerTube', 'body' => $txt];
      }

      return ['ok' => true, 'data' => $json];
    } catch (Throwable $e) {
      return ['ok' => false, 'error' => $e->getMessage()];
    }
  }
}
