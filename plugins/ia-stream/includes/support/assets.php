<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Stream Assets
 *
 * Loads CSS/JS for the Stream surface and injects IA_STREAM_CFG.
 *
 * IMPORTANT:
 * Do NOT hard-gate on did_action('ia_atrium_loaded') at enqueue time.
 * That marker may fire after wp_enqueue_scripts depending on plugin order,
 * which causes Stream JS to never load and leaves the placeholder forever.
 */
function ia_stream_assets_boot(): void {

  if (is_admin()) return;

  // Run late to avoid ordering races with Atrium.
  add_action('wp_enqueue_scripts', function () {

    $ver = defined('IA_STREAM_VERSION') ? IA_STREAM_VERSION : '0.0.0';

    /* ---------- CSS ---------- */
    wp_enqueue_style('ia-stream-base', IA_STREAM_URL . 'assets/css/ia-stream.base.css', [], $ver);
    wp_enqueue_style('ia-stream-layout', IA_STREAM_URL . 'assets/css/ia-stream.layout.css', ['ia-stream-base'], $ver);
    wp_enqueue_style('ia-stream-cards', IA_STREAM_URL . 'assets/css/ia-stream.cards.css', ['ia-stream-layout'], $ver);
    wp_enqueue_style('ia-stream-channels', IA_STREAM_URL . 'assets/css/ia-stream.channels.css', ['ia-stream-layout'], $ver);
    wp_enqueue_style('ia-stream-player', IA_STREAM_URL . 'assets/css/ia-stream.player.css', ['ia-stream-cards'], $ver);
    wp_enqueue_style('ia-stream-modal', IA_STREAM_URL . 'assets/css/ia-stream.modal.css', ['ia-stream-layout'], $ver);

    /* ---------- JS ---------- */
    wp_enqueue_script('ia-stream-core', IA_STREAM_URL . 'assets/js/ia-stream.core.js', [], $ver, true);
    wp_enqueue_script('ia-stream-api', IA_STREAM_URL . 'assets/js/ia-stream.api.js', ['ia-stream-core'], $ver, true);
    wp_enqueue_script('ia-stream-state', IA_STREAM_URL . 'assets/js/ia-stream.state.js', ['ia-stream-core'], $ver, true);

    // UI scripts (explicit order)
    wp_enqueue_script('ia-stream-ui-shell', IA_STREAM_URL . 'assets/js/ia-stream.ui.shell.js', ['ia-stream-core'], $ver, true);
    wp_enqueue_script('ia-stream-ui-feed', IA_STREAM_URL . 'assets/js/ia-stream.ui.feed.js', ['ia-stream-api'], $ver, true);
    wp_enqueue_script('ia-stream-ui-channels', IA_STREAM_URL . 'assets/js/ia-stream.ui.channels.js', ['ia-stream-api'], $ver, true);
    wp_enqueue_script('ia-stream-ui-video', IA_STREAM_URL . 'assets/js/ia-stream.ui.video.js', ['ia-stream-ui-feed'], $ver, true);
    wp_enqueue_script('ia-stream-ui-comments', IA_STREAM_URL . 'assets/js/ia-stream.ui.comments.js', ['ia-stream-ui-video'], $ver, true);

    // Boot waits for everything (including state + UI modules)
    wp_enqueue_script('ia-stream-boot', IA_STREAM_URL . 'assets/js/ia-stream.boot.js', [
      'ia-stream-ui-shell',
      'ia-stream-ui-feed',
      'ia-stream-ui-channels',
      'ia-stream-ui-video',
      'ia-stream-ui-comments',
      'ia-stream-state',
    ], $ver, true);

    // Inject config for JS API layer
    wp_add_inline_script('ia-stream-api', 'window.IA_STREAM_CFG = ' . wp_json_encode([
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce'   => function_exists('ia_stream_create_nonce') ? ia_stream_create_nonce() : '',
      'ver'     => $ver,
    ]) . ';', 'before');

    // Optional: tiny boot trace (remove later)
    wp_add_inline_script('ia-stream-boot', 'try{console.log("[IA_STREAM] boot enqueued");}catch(e){}', 'after');

  }, 99); // run late
}
