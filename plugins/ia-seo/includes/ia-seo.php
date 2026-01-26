<?php
if (!defined('ABSPATH')) exit;

// Support
require_once IA_SEO_PATH . 'includes/support/rewrite.php';
require_once IA_SEO_PATH . 'includes/support/admin.php';

// Services
require_once IA_SEO_PATH . 'includes/services/phpbb.php';
require_once IA_SEO_PATH . 'includes/services/connect.php';
require_once IA_SEO_PATH . 'includes/services/sitemap.php';

if (!function_exists('ia_seo_core_boot')) {
  function ia_seo_core_boot(): void {
    if (function_exists('ia_seo_rewrite_boot')) ia_seo_rewrite_boot();
    if (function_exists('ia_seo_admin_boot')) ia_seo_admin_boot();

    // Disable WordPress core sitemaps (wp-sitemap.xml) by default.
    // This plugin is intended to generate an Atrium-only sitemap.
    add_filter('wp_sitemaps_enabled', function ($enabled) {
      $s = function_exists('ia_seo_get_settings') ? ia_seo_get_settings() : [];
      return !empty($s['disable_wp_core_sitemap']) ? false : $enabled;
    }, 20, 1);

    // Optional redirect from /wp-sitemap.xml to /sitemap.xml for consistency.
    add_action('template_redirect', function () {
      $s = function_exists('ia_seo_get_settings') ? ia_seo_get_settings() : [];
      if (empty($s['redirect_wp_sitemap'])) return;
      $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
      if ($uri === '') return;
      $path = parse_url($uri, PHP_URL_PATH);
      if ($path !== '/wp-sitemap.xml') return;

      // If upstream rules are bouncing between sitemap endpoints, avoid loops.
      if (get_query_var(function_exists('ia_seo_qv') ? ia_seo_qv() : 'ia_sitemap')) return;

      wp_redirect(home_url('/sitemap.xml'), 301);
      exit;
    }, 1);

    // Cache invalidation hooks (Connect emits these).
    add_action('ia_connect_post_created', function () { ia_seo_sitemap_flush_cache(); }, 10, 0);
    add_action('ia_connect_comment_created', function () { ia_seo_sitemap_flush_cache(); }, 10, 0);
    add_action('ia_connect_share_created', function () { ia_seo_sitemap_flush_cache(); }, 10, 0);

    // Intentionally do NOT hook WordPress content changes.
    // The sitemap should not reflect WordPress posts/pages/taxonomies.
  }
}
