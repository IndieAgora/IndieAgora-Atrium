<?php
if (!defined('ABSPATH')) exit;

// --- Query var ---
if (!function_exists('ia_seo_qv')) {
  function ia_seo_qv(): string { return 'ia_sitemap'; }
}

if (!function_exists('ia_seo_rewrite_boot')) {
  function ia_seo_rewrite_boot(): void {
    add_filter('query_vars', function ($vars) {
      $vars[] = ia_seo_qv();
      return $vars;
    });

    add_action('init', function () {
      // /sitemap.xml
      add_rewrite_rule('^sitemap\\.xml$', 'index.php?' . ia_seo_qv() . '=1', 'top');
      // Fallback endpoint that does not rely on webserver rewrite for .xml
      // /?ia_sitemap=1 (handled by query_var)
    }, 20);

    // Prevent WordPress canonical redirects from interfering with /sitemap.xml
    add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
      $is = get_query_var(ia_seo_qv());
      if ($is) return false;
      $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
      if (strpos($uri, '/sitemap.xml') !== false) return false;
      return $redirect_url;
    }, 0, 2);

    // Serve sitemap as early as possible to avoid redirect loops caused by upstream rules.
    add_action('parse_request', function ($wp) {
      $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
      $path = $uri ? parse_url($uri, PHP_URL_PATH) : '';
      if ($path !== '/sitemap.xml') return;

      // If the rewrite rule did not run, force the flag.
      if (!get_query_var(ia_seo_qv())) {
        set_query_var(ia_seo_qv(), 1);
      }

      $xml = ia_seo_sitemap_render();
      nocache_headers();
      header('Content-Type: application/xml; charset=UTF-8');
      echo $xml;
      exit;
    }, 0, 1);

    // Ensure rules exist on activation.
    register_activation_hook(IA_SEO_PATH . 'ia-seo.php', function () {
      // Re-register and flush.
      add_rewrite_rule('^sitemap\\.xml$', 'index.php?' . ia_seo_qv() . '=1', 'top');
      flush_rewrite_rules(false);
    });

    register_deactivation_hook(IA_SEO_PATH . 'ia-seo.php', function () {
      flush_rewrite_rules(false);
    });

    add_action('template_redirect', function () {
      $is = get_query_var(ia_seo_qv());
      if (!$is) return;

      // Generate and output sitemap.
      $xml = ia_seo_sitemap_render();
      nocache_headers();
      header('Content-Type: application/xml; charset=UTF-8');
      echo $xml;
      exit;
    }, 0);
  }
}
