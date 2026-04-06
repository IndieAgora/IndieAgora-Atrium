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


if (!function_exists('ia_seo_sitemap_store_stats')) {
  function ia_seo_sitemap_store_stats(array $items): void {
    $stats = [
      'rendered_at' => gmdate('c'),
      'total' => count($items),
      'counts' => [],
    ];
    foreach ($items as $item) {
      $kind = isset($item['kind']) ? (string)$item['kind'] : 'unknown';
      if (!isset($stats['counts'][$kind])) $stats['counts'][$kind] = 0;
      $stats['counts'][$kind]++;
    }
    update_option('ia_seo_sitemap_stats_v1', $stats, false);
  }
}

if (!function_exists('ia_seo_sitemap_get_stats')) {
  function ia_seo_sitemap_get_stats(): array {
    $stats = get_option('ia_seo_sitemap_stats_v1', []);
    return is_array($stats) ? $stats : [];
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
    ia_seo_sitemap_store_stats($items);
    $xml = ia_seo_sitemap_xml_wrap($items);

    if ($ttl > 0) set_transient(ia_seo_sitemap_cache_key(), $xml, $ttl);
    return $xml;
  }
}

if (!function_exists('ia_seo_sitemap_build_items')) {
  function ia_seo_sitemap_build_items(array $s): array {
    $items = [];
    $now = time();

    if (!empty($s['include_home'])) {
      $items[] = [
        'loc' => home_url('/'),
        'lastmod' => gmdate('c', $now),
        'changefreq' => (string)($s['changefreq_home'] ?? 'daily'),
        'priority' => (string)($s['priority_home'] ?? '0.8'),
      ];
    }

    $base = trim((string)($s['atr_base'] ?? ''));
    $base = $base !== '' ? ia_seo_route_url($base, []) : '';
    if ($base !== '') {
      if (!empty($s['include_connect_surface'])) {
        $items[] = [
          'kind' => 'connect_surface',
          'loc' => ia_seo_route_url($base, ['tab' => 'connect']),
          'lastmod' => gmdate('c', $now),
          'changefreq' => (string)($s['changefreq_connect'] ?? 'hourly'),
          'priority' => (string)($s['priority_connect_surface'] ?? '0.7'),
        ];
      }
      if (!empty($s['include_discuss_surface'])) {
        $items[] = [
          'kind' => 'discuss_surface',
          'loc' => ia_seo_route_url($base, ['tab' => 'discuss']),
          'lastmod' => gmdate('c', $now),
          'changefreq' => (string)($s['changefreq_discuss'] ?? 'hourly'),
          'priority' => (string)($s['priority_discuss_surface'] ?? '0.7'),
        ];
        $items[] = [
          'kind' => 'discuss_agoras_surface',
          'loc' => ia_seo_route_url($base, ['tab' => 'discuss', 'iad_view' => 'agoras']),
          'lastmod' => gmdate('c', $now),
          'changefreq' => (string)($s['changefreq_agoras'] ?? 'daily'),
          'priority' => (string)($s['priority_discuss_agora'] ?? '0.7'),
        ];
      }
      if (!empty($s['include_stream_surface'])) {
        $items[] = [
          'kind' => 'stream_surface',
          'loc' => ia_seo_route_url($base, ['tab' => 'stream']),
          'lastmod' => gmdate('c', $now),
          'changefreq' => (string)($s['changefreq_stream'] ?? 'hourly'),
          'priority' => (string)($s['priority_stream_surface'] ?? '0.7'),
        ];
      }
    }

    // Connect public profiles.
    if (!empty($s['include_connect_profiles']) && $base !== '') {
      $limit = (int)($s['max_connect_profiles'] ?? 0);
      $db = new IA_SEO_Connect_DB();
      foreach ($db->get_public_profiles($limit) as $r) {
        $slug = sanitize_user((string)($r['slug'] ?? ''), true);
        if ($slug === '') continue;
        $lm = (int)($r['lastmod_unix'] ?? 0);
        if ($lm <= 0) $lm = $now;
        $items[] = [
          'kind' => 'connect_profile',
          'loc' => ia_seo_route_url($base, ['tab' => 'connect', 'ia_profile_name' => $slug]),
          'lastmod' => gmdate('c', $lm),
          'changefreq' => (string)($s['changefreq_profiles'] ?? 'daily'),
          'priority' => (string)($s['priority_connect_profile'] ?? '0.7'),
        ];
      }
    }

    // Connect posts.
    if (!empty($s['include_connect_posts']) && $base !== '') {
      $limit = (int)($s['max_connect_posts'] ?? 0);
      $db = isset($db) && $db instanceof IA_SEO_Connect_DB ? $db : new IA_SEO_Connect_DB();
      foreach ($db->get_posts($limit) as $r) {
        $pid = (int)($r['id'] ?? 0);
        if (!$pid) continue;

        $wall_owner_wp_id = (int)($r['wall_owner_wp_id'] ?? 0);
        if ($wall_owner_wp_id > 0 && function_exists('ia_connect_user_seo_visible') && !ia_connect_user_seo_visible($wall_owner_wp_id)) {
          continue;
        }

        $lm = (int)($r['lastmod_unix'] ?? 0);
        if ($lm <= 0) $lm = $now;
        $items[] = [
          'kind' => 'connect_post',
          'loc' => ia_seo_route_url($base, ['tab' => 'connect', 'ia_post' => $pid]),
          'lastmod' => gmdate('c', $lm),
          'changefreq' => (string)($s['changefreq_connect'] ?? 'hourly'),
          'priority' => (string)($s['priority_connect_post'] ?? '0.6'),
        ];
      }
    }

    // Discuss public agoras/topics/replies only.
    if ($base !== '' && (!empty($s['include_discuss_agoras']) || !empty($s['include_discuss_topics']) || !empty($s['include_discuss_posts']))) {
      $phpbb = new IA_SEO_PHPBB_DB();
      if ($phpbb->ok()) {
        if (!empty($s['include_discuss_agoras'])) {
          $limit = (int)($s['max_discuss_agoras'] ?? 0);
          foreach ($phpbb->get_public_forums($limit) as $r) {
            $fid = (int)($r['forum_id'] ?? 0);
            if ($fid <= 0) continue;
            $forum_name = sanitize_title((string)($r['forum_name'] ?? ''));
            $lm = (int)($r['forum_last_post_time'] ?? 0);
            if ($lm <= 0) $lm = $now;
            $args = ['tab' => 'discuss', 'iad_view' => 'agora', 'iad_forum' => $fid];
            if ($forum_name !== '') $args['iad_forum_name'] = $forum_name;
            $items[] = [
              'kind' => 'discuss_agora',
              'loc' => ia_seo_route_url($base, $args),
              'lastmod' => gmdate('c', $lm),
              'changefreq' => (string)($s['changefreq_agoras'] ?? 'daily'),
              'priority' => (string)($s['priority_discuss_agora'] ?? '0.7'),
            ];
          }
        }

        if (!empty($s['include_discuss_topics'])) {
          $limit = (int)($s['max_discuss_topics'] ?? 0);
          foreach ($phpbb->get_topics($limit) as $r) {
            $tid = (int)($r['topic_id'] ?? 0);
            if (!$tid) continue;
            $lm = (int)($r['topic_last_post_time'] ?? 0);
            if ($lm <= 0) $lm = (int)($r['topic_time'] ?? $now);
            $items[] = [
              'kind' => 'discuss_topic',
              'loc' => ia_seo_route_url($base, ['tab' => 'discuss', 'iad_topic' => $tid]),
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
            $lm = (int)($r['post_time'] ?? $now);
            $items[] = [
              'kind' => 'discuss_post',
              'loc' => ia_seo_route_url($base, ['tab' => 'discuss', 'iad_topic' => $tid, 'iad_post' => $pid]),
              'lastmod' => gmdate('c', $lm),
              'changefreq' => (string)($s['changefreq_discuss'] ?? 'hourly'),
              'priority' => (string)($s['priority_discuss_post'] ?? '0.4'),
            ];
          }
        }
      }
    }


    // Stream public channels/videos only.
    if ($base !== '' && (!empty($s['include_stream_channels']) || !empty($s['include_stream_videos']))) {
      $stream = new IA_SEO_Stream_Service();
      if ($stream->ok()) {
        if (!empty($s['include_stream_channels'])) {
          $limit = (int)($s['max_stream_channels'] ?? 0);
          foreach ($stream->get_channels($limit) as $r) {
            $handle = trim((string)($r['handle'] ?? ''));
            if ($handle === '') continue;
            $lm = (int)($r['lastmod_unix'] ?? 0);
            if ($lm <= 0) $lm = $now;
            $items[] = [
              'kind' => 'stream_channel',
              'loc' => ia_seo_route_url($base, ['tab' => 'stream', 'stream_channel' => $handle]),
              'lastmod' => gmdate('c', $lm),
              'changefreq' => (string)($s['changefreq_stream_channels'] ?? 'daily'),
              'priority' => (string)($s['priority_stream_channel'] ?? '0.7'),
            ];
          }
        }

        if (!empty($s['include_stream_videos'])) {
          $limit = (int)($s['max_stream_videos'] ?? 0);
          foreach ($stream->get_videos($limit) as $r) {
            $id = trim((string)($r['id'] ?? ''));
            if ($id === '') continue;
            $lm = (int)($r['lastmod_unix'] ?? 0);
            if ($lm <= 0) $lm = $now;
            $item = [
              'kind' => 'stream_video',
              'loc' => ia_seo_route_url($base, ['tab' => 'stream', 'video' => $id]),
              'lastmod' => gmdate('c', $lm),
              'changefreq' => (string)($s['changefreq_stream_videos'] ?? 'daily'),
              'priority' => (string)($s['priority_stream_video'] ?? '0.7'),
            ];
            if (!empty($s['sitemap_stream_video_metadata'])) {
              $full = $stream->get_video_by_id($id);
              if (is_array($full)) {
                $item['video'] = [
                  'title' => (string)($full['name'] ?? ''),
                  'description' => ia_seo_metadata_trim_text((string)($full['description'] ?? ''), 1000),
                  'thumbnail' => (string)($full['thumbnail_url'] ?? ''),
                  'publication_date' => (string)($full['published_at'] ?? ''),
                ];
              }
            }
            $items[] = $item;
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

if (!function_exists('ia_seo_route_url')) {
  function ia_seo_route_url(string $base, array $q): string {
    $base = trim($base);
    if ($base === '') return '';

    $parsed = wp_parse_url($base);
    $clean = [];
    if (!empty($parsed['scheme']) && !empty($parsed['host'])) {
      $clean = $parsed;
      $clean['path'] = isset($parsed['path']) ? $parsed['path'] : '/';
      unset($clean['query'], $clean['fragment']);
      $base = (string)(isset($clean['scheme']) ? $clean['scheme'] . '://' : '') . (string)$clean['host'];
      if (!empty($clean['port'])) $base .= ':' . $clean['port'];
      $base .= (string)$clean['path'];
    }

    $tab = isset($q['tab']) ? (string)$q['tab'] : '';
    $allowed = ['tab'];
    if ($tab === 'connect') {
      $allowed = ['tab','ia_profile_name','ia_post'];
    } elseif ($tab === 'discuss') {
      $allowed = ['tab','iad_view','iad_forum','iad_forum_name','iad_topic','iad_post'];
    } elseif ($tab === 'stream') {
      $allowed = ['tab','stream_channel','stream_channel_name','video'];
    }

    foreach ($q as $k => $v) {
      if (!in_array((string)$k, $allowed, true)) continue;
      if ($v === null) continue;
      $v = is_scalar($v) ? trim((string)$v) : '';
      if ($v === '') continue;
      $base = add_query_arg($k, $v, $base);
    }
    return $base;
  }
}

if (!function_exists('ia_seo_sitemap_xml_wrap')) {
  function ia_seo_sitemap_xml_wrap(array $items): string {
    $has_video = false;
    foreach ($items as $it) {
      if (!empty($it['video']) && is_array($it['video'])) { $has_video = true; break; }
    }
    $out = [];
    $out[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $root = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
    if ($has_video) $root .= ' xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"';
    $root .= '>';
    $out[] = $root;

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
      if (!empty($it['video']) && is_array($it['video'])) {
        $video = $it['video'];
        $title = ia_seo_metadata_trim_text((string)($video['title'] ?? ''), 100);
        $description = ia_seo_metadata_trim_text((string)($video['description'] ?? ''), 1000);
        $thumb = esc_url_raw((string)($video['thumbnail'] ?? ''));
        $pub = (string)($video['publication_date'] ?? '');
        if ($title !== '' && $description !== '' && $thumb !== '') {
          $out[] = '    <video:video>';
          $out[] = '      <video:thumbnail_loc>' . esc_html($thumb) . '</video:thumbnail_loc>';
          $out[] = '      <video:title>' . esc_html($title) . '</video:title>';
          $out[] = '      <video:description>' . esc_html($description) . '</video:description>';
          if ($pub !== '') $out[] = '      <video:publication_date>' . esc_html($pub) . '</video:publication_date>';
          $out[] = '    </video:video>';
        }
      }
      $out[] = '  </url>';
    }

    $out[] = '</urlset>';
    return implode("
", $out) . "
";
  }
}
