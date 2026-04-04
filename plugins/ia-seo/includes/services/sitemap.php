<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('ia_seo_sitemap_cache_key')) {
  function ia_seo_sitemap_cache_key(): string {
    return 'ia_seo_sitemap_xml_v1';
  }
}

if (!function_exists('ia_seo_sitemap_flush_cache')) {
  function ia_seo_sitemap_flush_cache(): void {
    delete_transient(ia_seo_sitemap_cache_key());
  }
}

if (!function_exists('ia_seo_sitemap_render')) {
  function ia_seo_sitemap_render(): string {
    $s = function_exists('ia_seo_get_settings') ? ia_seo_get_settings() : [];

    $enabled = (int)($s['enabled'] ?? 1);
    if (!$enabled) {
      return ia_seo_sitemap_xml_wrap([]);
    }

    $ttl = (int)($s['cache_ttl'] ?? 300);
    if ($ttl > 0) {
      $cached = get_transient(ia_seo_sitemap_cache_key());
      if (is_string($cached) && $cached !== '') return $cached;
    }

    $items = ia_seo_sitemap_build_items($s);
    $xml = ia_seo_sitemap_xml_wrap($items);

    if ($ttl > 0) set_transient(ia_seo_sitemap_cache_key(), $xml, $ttl);
    return $xml;
  }
}

if (!function_exists('ia_seo_sitemap_build_items')) {
  function ia_seo_sitemap_build_items(array $s): array {
    $items = [];

    if (!empty($s['include_home'])) {
      $home = home_url('/');
      $items[] = [
        'loc' => $home,
        'lastmod' => gmdate('c', time()),
        'changefreq' => (string)($s['changefreq_home'] ?? 'daily'),
        'priority' => (string)($s['priority_home'] ?? '0.8'),
      ];
    }

    $base = trim((string)($s['atr_base'] ?? ''));
    if ($base !== '') {
      // Add the Atrium landing tabs as surfaces.
      $items[] = [
        'loc' => ia_seo_url_add_query($base, ['tab' => 'connect']),
        'lastmod' => gmdate('c', time()),
        'changefreq' => (string)($s['changefreq_connect'] ?? 'hourly'),
        'priority' => '0.6',
      ];
      $items[] = [
        'loc' => ia_seo_url_add_query($base, ['tab' => 'discuss']),
        'lastmod' => gmdate('c', time()),
        'changefreq' => (string)($s['changefreq_discuss'] ?? 'hourly'),
        'priority' => '0.7',
      ];
    }

    // Connect posts
    if (!empty($s['include_connect_posts']) && $base !== '') {
      $limit = (int)($s['max_connect_posts'] ?? 0);
      $db = new IA_SEO_Connect_DB();
      foreach ($db->get_posts($limit) as $r) {
        $pid = (int)($r['id'] ?? 0);
        if (!$pid) continue;
        $lm = (int)($r['lastmod_unix'] ?? 0);
        if ($lm <= 0) $lm = time();
        $items[] = [
          'loc' => ia_seo_url_add_query($base, ['tab' => 'connect', 'ia_post' => $pid]),
          'lastmod' => gmdate('c', $lm),
          'changefreq' => (string)($s['changefreq_connect'] ?? 'hourly'),
          'priority' => (string)($s['priority_connect_post'] ?? '0.6'),
        ];
      }
    }

    // Discuss (phpBB)
    if ($base !== '' && ( !empty($s['include_discuss_topics']) || !empty($s['include_discuss_posts']) )) {
      $phpbb = new IA_SEO_PHPBB_DB();
      if ($phpbb->ok()) {
        if (!empty($s['include_discuss_topics'])) {
          $limit = (int)($s['max_discuss_topics'] ?? 0);
          foreach ($phpbb->get_topics($limit) as $r) {
            $tid = (int)($r['topic_id'] ?? 0);
            if (!$tid) continue;
            $lm = (int)($r['topic_last_post_time'] ?? 0);
            if ($lm <= 0) $lm = (int)($r['topic_time'] ?? time());
            $items[] = [
              'loc' => ia_seo_url_add_query($base, ['tab' => 'discuss', 'iad_topic' => $tid]),
              'lastmod' => gmdate('c', $lm),
              'changefreq' => (string)($s['changefreq_discuss'] ?? 'hourly'),
              'priority' => (string)($s['priority_discuss_topic'] ?? '0.7'),
            ];
          }
        }

        if (!empty($s['include_discuss_posts'])) {
          $limit = (int)($s['max_discuss_posts'] ?? 0);
          foreach ($phpbb->get_posts($limit) as $r) {
            $pid = (int)($r['post_id'] ?? 0);
            $tid = (int)($r['topic_id'] ?? 0);
            if (!$pid || !$tid) continue;
            $lm = (int)($r['post_time'] ?? time());
            $items[] = [
              'loc' => ia_seo_url_add_query($base, ['tab' => 'discuss', 'iad_topic' => $tid, 'iad_post' => $pid]),
              'lastmod' => gmdate('c', $lm),
              'changefreq' => (string)($s['changefreq_discuss'] ?? 'hourly'),
              'priority' => (string)($s['priority_discuss_post'] ?? '0.5'),
            ];
          }
        }
      }
    }

    // Ensure unique locs.
    $seen = [];
    $uniq = [];
    foreach ($items as $it) {
      $loc = (string)($it['loc'] ?? '');
      if ($loc === '') continue;
      if (isset($seen[$loc])) continue;
      $seen[$loc] = 1;
      $uniq[] = $it;
    }

    return $uniq;
  }
}

if (!function_exists('ia_seo_url_add_query')) {
  function ia_seo_url_add_query(string $base, array $q): string {
    $u = $base;    // Use WP helpers where possible.
    foreach ($q as $k => $v) {
      $u = add_query_arg($k, $v, $u);
    }
    return $u;
  }
}

if (!function_exists('ia_seo_sitemap_xml_wrap')) {
  function ia_seo_sitemap_xml_wrap(array $items): string {
    $out = [];
    $out[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $out[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    foreach ($items as $it) {
      $loc = esc_url_raw((string)($it['loc'] ?? ''));
      if ($loc === '') continue;
      $lastmod = (string)($it['lastmod'] ?? '');
      $changefreq = strtolower((string)($it['changefreq'] ?? ''));
      $priority = (string)($it['priority'] ?? '');

      $out[] = '  <url>';
      $out[] = '    <loc>' . esc_html($loc) . '</loc>';
      if ($lastmod !== '') $out[] = '    <lastmod>' . esc_html($lastmod) . '</lastmod>';
      if ($changefreq !== '') $out[] = '    <changefreq>' . esc_html($changefreq) . '</changefreq>';
      if ($priority !== '') $out[] = '    <priority>' . esc_html($priority) . '</priority>';
      $out[] = '  </url>';
    }

    $out[] = '</urlset>';
    return implode("\n", $out) . "\n";
  }
}
