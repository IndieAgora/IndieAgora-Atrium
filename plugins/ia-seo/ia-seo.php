<?php
/**
 * Plugin Name: IA SEO
 * Description: Dynamic sitemap.xml generator for Atrium (Connect + Discuss).
 * Version: 0.1.1
 * Author: IndieAgora
 * Text Domain: ia-seo
 */

if (!defined('ABSPATH')) exit;

define('IA_SEO_VERSION', '0.1.1');
define('IA_SEO_PATH', plugin_dir_path(__FILE__));
define('IA_SEO_URL', plugin_dir_url(__FILE__));

if (!function_exists('ia_seo_require_if_exists')) {
  function ia_seo_require_if_exists(string $path): bool {
    if (file_exists($path)) { require_once $path; return true; }
    return false;
  }
}

if (!function_exists('ia_seo_admin_notice')) {
  function ia_seo_admin_notice(string $msg): void {
    if (!is_admin()) return;
    add_action('admin_notices', function () use ($msg) {
      echo '<div class="notice notice-error"><p><strong>IA SEO:</strong> ' . esc_html($msg) . '</p></div>';
    });
  }
}

if (!function_exists('ia_seo_boot')) {
  function ia_seo_boot(): void {
    $ok = ia_seo_require_if_exists(IA_SEO_PATH . 'includes/ia-seo.php');
    if (!$ok) { ia_seo_admin_notice('Missing required file: includes/ia-seo.php'); return; }

    if (function_exists('ia_seo_core_boot')) {
      try {
        ia_seo_core_boot();
      } catch (Throwable $e) {
        ia_seo_admin_notice('Boot error: ' . $e->getMessage());
      }
    }
  }
}

add_action('plugins_loaded', 'ia_seo_boot', 20);
