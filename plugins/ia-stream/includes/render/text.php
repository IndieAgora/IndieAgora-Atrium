<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Stream Render: Text
 *
 * Stateless text helpers used when transforming PeerTube API payloads.
 * Keep this pure (no WP hooks).
 */

/**
 * Return first non-empty string from a list of candidates.
 */
function ia_stream_first_str(...$candidates): string {
  foreach ($candidates as $c) {
    if (!is_string($c)) continue;
    $t = trim($c);
    if ($t !== '') return $t;
  }
  return '';
}

/**
 * Safe excerpt (strip tags, collapse whitespace).
 */
function ia_stream_excerpt(string $text, int $max = 220): string {
  $t = wp_strip_all_tags((string)$text);
  $t = trim(preg_replace('/\s+/u', ' ', $t));
  if ($t === '') return '';
  if (mb_strlen($t) <= $max) return $t;
  return rtrim(mb_substr($t, 0, $max)) . '…';
}

/**
 * Convert an ISO date/time into a “time ago” string (rough).
 * Keep it simple and predictable.
 */
function ia_stream_time_ago(?string $iso): string {
  $iso = trim((string)$iso);
  if ($iso === '') return '';

  $ts = strtotime($iso);
  if (!$ts) return '';

  $d = time() - $ts;
  if ($d < 60) return 'just now';
  if ($d < 3600) return floor($d / 60) . 'm';
  if ($d < 86400) return floor($d / 3600) . 'h';
  if ($d < 86400 * 30) return floor($d / 86400) . 'd';
  if ($d < 86400 * 365) return floor($d / (86400 * 30)) . 'mo';
  return floor($d / (86400 * 365)) . 'y';
}
