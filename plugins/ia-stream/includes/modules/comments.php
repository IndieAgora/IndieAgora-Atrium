<?php
if (!defined('ABSPATH')) exit;

/**
 * Comments module (wired)
 * - Calls PeerTube /api/v1/videos/{id}/comment-threads
 * - Normalizes thread objects to reply cards
 */
final class IA_Stream_Module_Comments implements IA_Stream_Module_Interface {

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
      $items[] = function_exists('ia_stream_norm_comment_thread') ? ia_stream_norm_comment_thread($t, $base) : $t;
    }

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
}
