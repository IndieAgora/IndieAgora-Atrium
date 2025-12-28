<?php
if (!defined('ABSPATH')) exit;

/**
 * Feed module (wired)
 * - Calls PeerTube /api/v1/videos
 * - Normalizes to video card objects
 */
final class IA_Stream_Module_Feed implements IA_Stream_Module_Interface {

  public static function boot(): void {}

  public static function normalize_query(array $q): array {
    $page = isset($q['page']) ? (int)$q['page'] : 1;
    $page = max(1, $page);

    $per_page = isset($q['per_page']) ? (int)$q['per_page'] : 10;
    $per_page = min(50, max(1, $per_page));

    return [
      'page'     => $page,
      'per_page' => $per_page,
    ];
  }

  public static function get_feed(array $q): array {
    $q = self::normalize_query($q);

    if (!class_exists('IA_Stream_Service_PeerTube_API')) {
      return [
        'ok' => true,
        'meta' => [
          'page' => $q['page'],
          'per_page' => $q['per_page'],
          'note' => 'PeerTube API service missing',
        ],
        'items' => [],
      ];
    }

    $api = new IA_Stream_Service_PeerTube_API();

    if (!$api->is_configured()) {
      return [
        'ok' => true,
        'meta' => [
          'page' => $q['page'],
          'per_page' => $q['per_page'],
          'note' => 'PeerTube not configured (missing base URL)',
        ],
        'items' => [],
      ];
    }

    $raw = $api->get_videos($q);

    if (!$raw['ok']) {
      return [
        'ok' => false,
        'error' => $raw['error'] ?? 'PeerTube error',
        'meta' => [
          'page' => $q['page'],
          'per_page' => $q['per_page'],
        ],
      ];
    }

    $data = $raw['data'] ?? [];
    $items = [];

    // /api/v1/videos returns { total, data: [...] }
    $list = [];
    if (is_array($data) && isset($data['data']) && is_array($data['data'])) $list = $data['data'];
    elseif (is_array($data)) $list = $data;

    // BUGFIX: Use PeerTube public base from the service (IA Engine config),
    // not only the legacy IA_PEERTUBE_BASE constant.
    $base = '';
    if (method_exists($api, 'public_base')) {
      $base = rtrim((string)$api->public_base(), '/');
    }
    if ($base === '' && defined('IA_PEERTUBE_BASE')) {
      $base = rtrim((string)IA_PEERTUBE_BASE, '/');
    }

    foreach ($list as $v) {
      if (!is_array($v)) continue;
      $items[] = function_exists('ia_stream_norm_video') ? ia_stream_norm_video($v, $base) : $v;
    }

    return [
      'ok' => true,
      'meta' => [
        'page' => $q['page'],
        'per_page' => $q['per_page'],
        'total' => isset($data['total']) ? (int)$data['total'] : null,
      ],
      'items' => $items,
    ];
  }
}
