<?php
if (!defined('ABSPATH')) exit;

/**
 * Safe require (never fatal if missing; returns bool).
 */
function ia_message_require(string $rel): bool {
  $path = IA_MESSAGE_PATH . ltrim($rel, '/');
  if (file_exists($path)) {
    require_once $path;
    return true;
  }
  return false;
}

/**
 * Admin-only error notice helper (non-fatal doctrine).
 */
function ia_message_admin_notice(string $msg): void {
  if (!is_admin()) return;
  add_action('admin_notices', function () use ($msg) {
    echo '<div class="notice notice-error"><p><strong>IA Message:</strong> ' . esc_html($msg) . '</p></div>';
  });
}

/**
 * Simple feature gate: only enqueue when Atrium is present.
 * We avoid guessing URLs; instead we require Atrium to be active (hooks exist).
 */
function ia_message_atrium_present(): bool {
  // If Atrium exposes a known function/hook, we key off that.
  // This keeps ia-message dormant unless Atrium is installed.
  return has_action('ia_atrium_panel_' . IA_MESSAGE_PANEL_KEY)
      || did_action('ia_atrium_boot')
      || function_exists('ia_atrium_boot')
      || defined('IA_ATRIUM_VERSION');
}
