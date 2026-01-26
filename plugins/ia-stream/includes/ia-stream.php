<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Stream Core Orchestrator
 *
 * Loads support/services/modules in a stable order and exposes ia_stream_core_boot().
 * The root loader (ia-stream.php) calls ia_stream_core_boot() inside a try/catch.
 */

/* ---------------------------------------------
 * 1) Require map (stable, explicit)
 * ------------------------------------------- */

$req = [
  // Support (WP-touching)
  IA_STREAM_PATH . 'includes/support/security.php',
  IA_STREAM_PATH . 'includes/support/assets.php',
  IA_STREAM_PATH . 'includes/support/ajax.php',

  // Services (PeerTube API-first)
  IA_STREAM_PATH . 'includes/services/peertube-api.php',
  IA_STREAM_PATH . 'includes/services/comment-votes.php',
  IA_STREAM_PATH . 'includes/services/auth.php',
  IA_STREAM_PATH . 'includes/services/text.php',

  // Render helpers (stateless)
  IA_STREAM_PATH . 'includes/render/text.php',
  IA_STREAM_PATH . 'includes/render/media.php',

  // Modules (bounded capability)
  IA_STREAM_PATH . 'includes/modules/module-interface.php',
  IA_STREAM_PATH . 'includes/modules/diag.php',
  IA_STREAM_PATH . 'includes/modules/feed.php',
  IA_STREAM_PATH . 'includes/modules/channels.php',
  IA_STREAM_PATH . 'includes/modules/video.php',
  IA_STREAM_PATH . 'includes/modules/comments.php',
  IA_STREAM_PATH . 'includes/modules/panel.php',

  // Optional general helpers
  IA_STREAM_PATH . 'includes/functions.php',
];

foreach ($req as $file) {
  if (file_exists($file)) require_once $file;
}

/* ---------------------------------------------
 * 2) Boot entry for root loader
 * ------------------------------------------- */

if (!function_exists('ia_stream_core_boot')) {

  function ia_stream_core_boot(): void {

    // Boot support layers if present.
    if (function_exists('ia_stream_security_boot')) ia_stream_security_boot();
    if (function_exists('ia_stream_assets_boot'))   ia_stream_assets_boot();
    if (function_exists('ia_stream_ajax_boot'))     ia_stream_ajax_boot();

    // Register panel render hook (Atrium shell calls this).
    add_action('ia_atrium_panel_stream', function () {
      if (class_exists('IA_Stream_Module_Panel')) {
        try {
          IA_Stream_Module_Panel::render();
        } catch (Throwable $e) {
          echo '<div class="ia-stream-error">IA Stream render error: ' . esc_html($e->getMessage()) . '</div>';
        }
        return;
      }

      // Hard fallback (should never happen once panel module is filled)
      echo '<div class="ia-stream-shell"><div class="ia-stream-error">IA Stream panel module missing.</div></div>';
    });

    // Optional: a simple marker for diagnostics and other plugins
    if (!defined('IA_STREAM_BOOTED')) define('IA_STREAM_BOOTED', true);
  }
}
