<?php
if (!defined('ABSPATH')) exit;

/**
 * Subscriptions module (user-scoped)
 * - Lists my subscriptions (channels)
 * - Lists videos from my subscriptions
 */
final class IA_Stream_Module_Subscriptions implements IA_Stream_Module_Interface {

  public static function boot(): void {}

  public static function normalize_query(array $q): array {
    $page = isset($q['page']) ? (int)$q['page'] : 1;
    $page = max(1, $page);

    $per_page = isset($q['per_page']) ? (int)$q['per_page'] : 24;
    $per_page = min(50, max(1, $per_page));

    $sort = isset($q['sort']) ? trim((string)$q['sort']) : '-publishedAt';

    return [
      'page' => $page,
      'per_page' => $per_page,
      'sort' => $sort,
    ];
  }

  public static function get_my_subscriptions(array $q, string $bearer): array {
    $q = self::normalize_query($q);

    if (!class_exists('IA_Stream_Service_PeerTube_API')) {
      return ['ok' => false, 'error' => 'PeerTube API service missing'];
    }

    $api = new IA_Stream_Service_PeerTube_API();
    if (!$api->is_configured()) {
      return ['ok' => true, 'meta' => ['note' => 'PeerTube not configured'], 'channels' => [], 'videos' => []];
    }

    $rawSubs = $api->list_my_subscriptions($q, $bearer);
    if (!$rawSubs['ok']) {
      return ['ok' => false, 'error' => $rawSubs['error'] ?? 'PeerTube error', 'body' => $rawSubs['body'] ?? null];
    }

    $rawVids = $api->list_my_subscription_videos($q, $bearer);
    if (!$rawVids['ok']) {
      return ['ok' => false, 'error' => $rawVids['error'] ?? 'PeerTube error', 'body' => $rawVids['body'] ?? null];
    }

    $subsData = $rawSubs['data'] ?? [];
    $vidsData = $rawVids['data'] ?? [];

    $subsList = (is_array($subsData) && isset($subsData['data']) && is_array($subsData['data'])) ? $subsData['data'] : (is_array($subsData) ? $subsData : []);
    $vidList  = (is_array($vidsData) && isset($vidsData['data']) && is_array($vidsData['data'])) ? $vidsData['data'] : (is_array($vidsData) ? $vidsData : []);

    $base = '';
    if (method_exists($api, 'public_base')) $base = rtrim((string)$api->public_base(), '/');
    if ($base === '' && defined('IA_PEERTUBE_BASE')) $base = rtrim((string)IA_PEERTUBE_BASE, '/');

    $channels = [];
    foreach ($subsList as $s) {
      if (!is_array($s)) continue;

      // PeerTube: subscription object often has "videoChannel"
      $ch = null;
      if (isset($s['videoChannel']) && is_array($s['videoChannel'])) $ch = $s['videoChannel'];
      elseif (isset($s['channel']) && is_array($s['channel'])) $ch = $s['channel'];

      if ($ch && function_exists('ia_stream_norm_channel')) {
        $norm = ia_stream_norm_channel($ch, $base);
        // add a stable handle if possible
        $name = isset($norm['name']) ? (string)$norm['name'] : '';
        $host = '';
        if (!empty($norm['url'])) {
          $u = wp_parse_url((string)$norm['url']);
          if (!empty($u['host'])) $host = (string)$u['host'];
        }
        $norm['handle'] = ($name !== '' && $host !== '') ? ('@' . ltrim($name,'@') . '@' . $host) : $name;
        $channels[] = $norm;
      }
    }

    $videos = [];
    foreach ($vidList as $v) {
      if (!is_array($v)) continue;
      $videos[] = function_exists('ia_stream_norm_video') ? ia_stream_norm_video($v, $base) : $v;
    }

    return [
      'ok' => true,
      'meta' => [
        'page' => $q['page'],
        'per_page' => $q['per_page'],
        'subs_total' => isset($subsData['total']) ? (int)$subsData['total'] : null,
        'videos_total' => isset($vidsData['total']) ? (int)$vidsData['total'] : null,
      ],
      'channels' => $channels,
      'videos' => $videos,
    ];
  }
}
