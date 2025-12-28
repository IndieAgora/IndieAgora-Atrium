<?php
if (!defined('ABSPATH')) exit;

/**
 * Diagnostics module
 * - Provides simple health/boot markers
 * - Optional debug panel output (only if you call it)
 */
final class IA_Stream_Module_Diag implements IA_Stream_Module_Interface {

  public static function boot(): void {
    // Nothing required yet.
  }

  public static function info(): array {
    return [
      'booted'   => defined('IA_STREAM_BOOTED') ? (bool)IA_STREAM_BOOTED : false,
      'version'  => defined('IA_STREAM_VERSION') ? IA_STREAM_VERSION : 'unknown',
      'wp'       => defined('ABSPATH'),
      'php'      => PHP_VERSION,
      'time'     => gmdate('c'),
    ];
  }

  public static function render_box(): void {
    $i = self::info();
    echo '<div class="ia-stream-card" style="padding:12px">';
    echo '<div style="font-weight:700;margin-bottom:6px">IA Stream Diagnostics</div>';
    echo '<pre style="white-space:pre-wrap;margin:0;color:#9aa3b2">' . esc_html(print_r($i, true)) . '</pre>';
    echo '</div>';
  }
}
