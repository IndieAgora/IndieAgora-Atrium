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

    $search = isset($q['search']) ? trim((string)$q['search']) : '';
    $search = mb_substr($search, 0, 120);

    $sort = isset($q['sort']) ? trim((string)$q['sort']) : '-publishedAt';
    $allowed_sort = ['-publishedAt', '-views', '-likes', 'name'];
    if (!in_array($sort, $allowed_sort, true)) $sort = '-publishedAt';

    $mode = isset($q['mode']) ? trim((string)$q['mode']) : '';
    if (!in_array($mode, ['subscriptions', 'channel'], true)) $mode = '';

    $channel_handle = isset($q['channel_handle']) ? trim((string)$q['channel_handle']) : '';
    $channel_handle = mb_substr($channel_handle, 0, 160);

    return [
      'page'     => $page,
      'per_page' => $per_page,
      'search'   => $search,
      'sort'     => $sort,
      'mode'     => $mode,
      'channel_handle' => $channel_handle,
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
          'search' => $q['search'],
          'sort' => $q['sort'],
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
          'sort' => $q['sort'],
          'note' => 'PeerTube not configured (missing base URL)',
        ],
        'items' => [],
      ];
    }

    $raw = null;

    if ($q['mode'] === 'subscriptions') {
      if (class_exists('IA_PeerTube_Token_Helper')) {
        try {
          if (method_exists('IA_PeerTube_Token_Helper', 'get_token_status_for_current_user')) {
            $status = (array) IA_PeerTube_Token_Helper::get_token_status_for_current_user();
            $tok = trim((string) ($status['token'] ?? ''));
            if (!empty($status['ok']) && $tok !== '') {
              $api->set_token($tok);
            }
          } elseif (method_exists('IA_PeerTube_Token_Helper', 'get_token_for_current_user')) {
            $tok = (string) IA_PeerTube_Token_Helper::get_token_for_current_user();
            if ($tok !== '') $api->set_token($tok);
          }
        } catch (Throwable $e) {
          // Keep browse behaviour stable; subscriptions will simply behave as unauthenticated.
        }
      }
      $raw = $api->get_subscription_videos($q);
    } elseif ($q['mode'] === 'channel' && $q['channel_handle'] !== '') {
      $raw = $api->get_channel_videos($q['channel_handle'], $q);
    } else {
      $raw = $api->get_videos($q);
    }

    if (!$raw['ok']) {
      return [
        'ok' => false,
        'error' => $raw['error'] ?? 'PeerTube error',
        'meta' => [
          'page' => $q['page'],
          'per_page' => $q['per_page'],
          'search' => $q['search'],
          'sort' => $q['sort'],
          'mode' => $q['mode'],
          'channel_handle' => $q['channel_handle'],
        ],
      ];
    }

    $data = $raw['data'] ?? [];
    $items = [];

    $list = [];
    if (is_array($data) && isset($data['data']) && is_array($data['data'])) $list = $data['data'];
    elseif (is_array($data)) $list = $data;

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
        'search' => $q['search'],
        'sort' => $q['sort'],
        'mode' => $q['mode'],
        'channel_handle' => $q['channel_handle'],
        'total' => isset($data['total']) ? (int)$data['total'] : null,
      ],
      'items' => $items,
    ];
  }
}
