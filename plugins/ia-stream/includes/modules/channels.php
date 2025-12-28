<?php
if (!defined('ABSPATH')) exit;

/**
 * Channels module (wired)
 * - Calls PeerTube /api/v1/video-channels
 * - Normalizes to channel card objects (avatar + cover)
 */
final class IA_Stream_Module_Channels implements IA_Stream_Module_Interface {

  public static function boot(): void {}

  public static function normalize_query(array $q): array {
    $page = isset($q['page']) ? (int)$q['page'] : 1;
    $page = max(1, $page);

    $per_page = isset($q['per_page']) ? (int)$q['per_page'] : 24;
    $per_page = min(60, max(1, $per_page));

    $search = isset($q['search']) ? trim((string)$q['search']) : '';
    $search = mb_substr($search, 0, 80);

    return [
      'page'     => $page,
      'per_page' => $per_page,
      'search'   => $search,
    ];
  }

  public static function get_channels(array $q): array {
    $q = self::normalize_query($q);

    if (!class_exists('IA_Stream_Service_PeerTube_API')) {
      return [
        'ok' => true,
        'meta' => [
          'page' => $q['page'],
          'per_page' => $q['per_page'],
          'search' => $q['search'],
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
          'search' => $q['search'],
          'note' => 'PeerTube not configured (missing base URL)',
        ],
        'items' => [],
      ];
    }

    $raw = $api->get_channels($q);

    if (!$raw['ok']) {
      return [
        'ok' => false,
        'error' => $raw['error'] ?? 'PeerTube error',
        'meta' => [
          'page' => $q['page'],
          'per_page' => $q['per_page'],
          'search' => $q['search'],
        ],
      ];
    }

    $data = $raw['data'] ?? [];
    $items = [];

    // /api/v1/video-channels returns { total, data: [...] }
    $list = [];
    if (is_array($data) && isset($data['data']) && is_array($data['data'])) $list = $data['data'];
    elseif (is_array($data)) $list = $data;

    // BUGFIX: Use PeerTube public base from the service (IA Engine config),
    // not only IA_PEERTUBE_BASE.
    $base = '';
    if (method_exists($api, 'public_base')) {
      $base = rtrim((string)$api->public_base(), '/');
    }
    if ($base === '' && defined('IA_PEERTUBE_BASE')) {
      $base = rtrim((string)IA_PEERTUBE_BASE, '/');
    }

    foreach ($list as $ch) {
      if (!is_array($ch)) continue;
      $items[] = function_exists('ia_stream_norm_channel') ? ia_stream_norm_channel($ch, $base) : $ch;
    }

    return [
      'ok' => true,
      'meta' => [
        'page' => $q['page'],
        'per_page' => $q['per_page'],
        'search' => $q['search'],
        'total' => isset($data['total']) ? (int)$data['total'] : null,
      ],
      'items' => $items,
    ];
  }
}
