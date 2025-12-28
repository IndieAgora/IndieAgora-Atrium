<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Stream Text Service
 *
 * Keep formatting rules centralized (excerpt, safe text, etc).
 */
final class IA_Stream_Service_Text {

  public function excerpt(string $text, int $max = 220): string {
    $t = trim(wp_strip_all_tags($text));
    if ($t === '') return '';
    if (mb_strlen($t) <= $max) return $t;
    return rtrim(mb_substr($t, 0, $max)) . '…';
  }

  public function safe(string $text): string {
    // For plain text contexts (never return raw HTML)
    return trim(wp_strip_all_tags($text));
  }

  public function normalize_search(string $q): string {
    $q = trim((string)$q);
    $q = wp_strip_all_tags($q);
    $q = mb_substr($q, 0, 80);
    return $q;
  }
}
