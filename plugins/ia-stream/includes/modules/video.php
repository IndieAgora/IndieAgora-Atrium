<?php
if (!defined('ABSPATH')) exit;

/**
 * Video module (wired)
 * - Calls PeerTube /api/v1/videos/{id}
 * - Normalizes to video card object
 */
final class IA_Stream_Module_Video implements IA_Stream_Module_Interface {

  public static function boot(): void {}

  public static function normalize_id($id): string {
    $id = trim((string)$id);
    return mb_substr($id, 0, 64);
  }

  public static function get_video($id): array {
    $id = self::normalize_id($id);

    if ($id === '') {
      return ['ok' => false, 'error' => 'Missing video id'];
    }

    if (!class_exists('IA_Stream_Service_PeerTube_API')) {
      return ['ok' => false, 'error' => 'PeerTube API service missing'];
    }

    $api = new IA_Stream_Service_PeerTube_API();

    if (!$api->is_configured()) {
      return ['ok' => false, 'error' => 'PeerTube not configured (missing base URL)'];
    }

    $raw = $api->get_video($id);

    if (!$raw['ok']) {
      return ['ok' => false, 'error' => $raw['error'] ?? 'PeerTube error', 'body' => $raw['body'] ?? null];
    }

    // BUGFIX: Use PeerTube public base from the service (IA Engine config).
    $base = '';
    if (method_exists($api, 'public_base')) {
      $base = rtrim((string)$api->public_base(), '/');
    }
    if ($base === '' && defined('IA_PEERTUBE_BASE')) {
      $base = rtrim((string)IA_PEERTUBE_BASE, '/');
    }

    $v = $raw['data'] ?? null;
    $item = (is_array($v) && function_exists('ia_stream_norm_video')) ? ia_stream_norm_video($v, $base) : $v;

    return [
      'ok' => true,
      'item' => $item,
    ];
  }
}
