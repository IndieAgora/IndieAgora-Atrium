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

  public function get_video(string $uuid): array {
    if (!$this->is_configured()) return $this->not_configured();

    $uuid = trim((string)$uuid);
    if ($uuid === '') return ['ok' => false, 'error' => 'Missing video id'];

    $path = '/api/v1/videos/' . rawurlencode($uuid);
    return $this->request('GET', $path);
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

  /* ---------------------------
   * HTTP
   * ------------------------- */

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
