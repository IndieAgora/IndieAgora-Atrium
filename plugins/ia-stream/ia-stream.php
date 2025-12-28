<?php
/**
 * Plugin Name: IA Stream
 * Description: Atrium Stream panel (PeerTube-backed) with mobile-first video feed + channels + modal video view + PeerTube comments.
 * Version: 0.1.0
 * Author: IndieAgora
 * Text Domain: ia-stream
 */

if (!defined('ABSPATH')) exit;

define('IA_STREAM_VERSION', '0.1.0');
define('IA_STREAM_PATH', plugin_dir_path(__FILE__));
define('IA_STREAM_URL', plugin_dir_url(__FILE__));

/**
 * IA Stream Orchestrator (Safe Boot)
 *
 * Goals:
 * - Never fatal the whole site.
 * - Require known support/service/module files in a stable order.
 * - Boot the core only if it exists.
 *
 * This matches the Atrium surface protocol used by Connect/Discuss:
 * root loader -> includes/ orchestrator -> support/services/modules/render -> panel hook.
 */
if (!function_exists('ia_stream_boot')) {

  // ---------- helpers ----------
  function ia_stream_require_if_exists(string $path): bool {
    if (file_exists($path)) {
      require_once $path;
      return true;
    }
    return false;
  }

  function ia_stream_admin_notice(string $msg): void {
    if (!is_admin()) return;
    add_action('admin_notices', function () use ($msg) {
      echo '<div class="notice notice-error"><p><strong>IA Stream:</strong> ' . esc_html($msg) . '</p></div>';
    });
  }

  // ---------- boot ----------
  function ia_stream_boot(): void {

    // Load the orchestrator (which loads the rest).
    $ok = ia_stream_require_if_exists(IA_STREAM_PATH . 'includes/ia-stream.php');

    if (!$ok) {
      ia_stream_admin_notice('Missing required file: includes/ia-stream.php');
      return;
    }

    // Boot core if the orchestrator exposed a boot function.
    if (function_exists('ia_stream_core_boot')) {
      try {
        ia_stream_core_boot();
      } catch (Throwable $e) {
        ia_stream_admin_notice('Boot error: ' . $e->getMessage());
        return;
      }
    } else {
      // Orchestrator loaded but didn’t expose the expected boot.
      ia_stream_admin_notice('Orchestrator loaded but ia_stream_core_boot() not found.');
    }
  }
}

add_action('plugins_loaded', 'ia_stream_boot', 20);
