<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('ia_seo_opt_key')) {
  function ia_seo_opt_key(): string { return 'ia_seo_settings_v1'; }
}

if (!function_exists('ia_seo_defaults')) {
  function ia_seo_defaults(): array {
    return [
      'enabled' => 1,
      'disable_wp_core_sitemap' => 1,
      'redirect_wp_sitemap' => 1,
      'include_home' => 0,
      'atr_base' => '',
      'cache_ttl' => 300,
      'include_connect_surface' => 1,
      'include_discuss_surface' => 1,
      'include_stream_surface' => 1,
      'include_connect_profiles' => 1,
      'include_discuss_agoras' => 1,
      'include_discuss_topics' => 1,
      'include_discuss_posts' => 0,
      'include_connect_posts' => 1,
      'include_stream_channels' => 1,
      'include_stream_videos' => 1,
      'max_connect_profiles' => 5000,
      'max_discuss_agoras' => 5000,
      'max_discuss_topics' => 5000,
      'max_discuss_posts' => 2000,
      'max_connect_posts' => 5000,
      'max_stream_channels' => 5000,
      'max_stream_videos' => 5000,
      'priority_home' => '0.8',
      'priority_connect_surface' => '0.7',
      'priority_discuss_surface' => '0.7',
      'priority_connect_profile' => '0.7',
      'priority_discuss_agora' => '0.7',
      'priority_discuss_topic' => '0.7',
      'priority_discuss_post' => '0.4',
      'priority_connect_post' => '0.6',
      'priority_stream_surface' => '0.7',
      'priority_stream_channel' => '0.7',
      'priority_stream_video' => '0.7',
      'changefreq_home' => 'daily',
      'changefreq_discuss' => 'hourly',
      'changefreq_connect' => 'hourly',
      'changefreq_stream' => 'hourly',
      'changefreq_profiles' => 'daily',
      'changefreq_stream_channels' => 'daily',
      'changefreq_stream_videos' => 'daily',
      'changefreq_agoras' => 'daily',
      'metadata_enabled' => 1,
      'metadata_jsonld_enabled' => 1,
      'metadata_connect_enabled' => 1,
      'metadata_discuss_enabled' => 1,
      'metadata_stream_enabled' => 1,
      'metadata_include_discuss_replies' => 1,
      'metadata_include_stream_comments' => 1,
      'metadata_max_description_chars' => 320,
      'metadata_stream_comment_limit' => 5,
      'metadata_site_name' => '',
      'sitemap_stream_video_metadata' => 1,
    ];
  }
}

if (!function_exists('ia_seo_get_settings')) {
  function ia_seo_get_settings(): array {
    $raw = get_option(ia_seo_opt_key(), []);
    if (!is_array($raw)) $raw = [];
    $s = array_merge(ia_seo_defaults(), $raw);
    if (trim((string)$s['atr_base']) === '') {
      $s['atr_base'] = ia_seo_detect_atrium_base_url();
    }
    if (trim((string)$s['metadata_site_name']) === '') {
      $s['metadata_site_name'] = (string)get_bloginfo('name');
    }
    return $s;
  }
}

if (!function_exists('ia_seo_detect_atrium_base_url')) {
  function ia_seo_detect_atrium_base_url(): string {
    $pages = get_posts([
      'post_type' => 'page',
      'post_status' => 'publish',
      'numberposts' => 50,
      's' => 'ia-atrium',
    ]);
    foreach ($pages as $p) {
      $content = (string)($p->post_content ?? '');
      if (stripos($content, '[ia-atrium') !== false) {
        return get_permalink($p);
      }
    }
    return '';
  }
}

if (!function_exists('ia_seo_sanitize_settings')) {
  function ia_seo_sanitize_settings(array $in): array {
    $d = ia_seo_defaults();
    $out = [];
    $out['enabled'] = !empty($in['enabled']) ? 1 : 0;
    $out['disable_wp_core_sitemap'] = !empty($in['disable_wp_core_sitemap']) ? 1 : 0;
    $out['redirect_wp_sitemap'] = !empty($in['redirect_wp_sitemap']) ? 1 : 0;
    $out['include_home'] = !empty($in['include_home']) ? 1 : 0;
    $out['atr_base'] = esc_url_raw(trim((string)($in['atr_base'] ?? '')));
    $ttl = (int)($in['cache_ttl'] ?? $d['cache_ttl']);
    $out['cache_ttl'] = max(0, min(86400, $ttl));

    foreach (['include_connect_surface','include_discuss_surface','include_stream_surface','include_connect_profiles','include_discuss_agoras','include_discuss_topics','include_discuss_posts','include_connect_posts','include_stream_channels','include_stream_videos','metadata_enabled','metadata_jsonld_enabled','metadata_connect_enabled','metadata_discuss_enabled','metadata_stream_enabled','metadata_include_discuss_replies','metadata_include_stream_comments','sitemap_stream_video_metadata'] as $k) {
      $out[$k] = !empty($in[$k]) ? 1 : 0;
    }
    foreach (['max_connect_profiles','max_discuss_agoras','max_discuss_topics','max_discuss_posts','max_connect_posts','max_stream_channels','max_stream_videos'] as $k) {
      $out[$k] = max(0, min(200000, (int)($in[$k] ?? $d[$k])));
    }
    foreach (['priority_home','priority_connect_surface','priority_discuss_surface','priority_connect_profile','priority_discuss_agora','priority_discuss_topic','priority_discuss_post','priority_connect_post','priority_stream_surface','priority_stream_channel','priority_stream_video'] as $k) {
      $v = preg_replace('/[^0-9.]/', '', (string)($in[$k] ?? $d[$k]));
      if ($v === '') $v = (string)$d[$k];
      $f = max(0.0, min(1.0, (float)$v));
      $out[$k] = rtrim(rtrim(sprintf('%.2f', $f), '0'), '.');
    }
    $allowed_freq = ['always','hourly','daily','weekly','monthly','yearly','never'];
    foreach (['changefreq_home','changefreq_discuss','changefreq_connect','changefreq_stream','changefreq_profiles','changefreq_agoras','changefreq_stream_channels','changefreq_stream_videos'] as $k) {
      $v = strtolower(trim((string)($in[$k] ?? $d[$k])));
      $out[$k] = in_array($v, $allowed_freq, true) ? $v : (string)$d[$k];
    }
    $out['metadata_max_description_chars'] = max(80, min(1000, (int)($in['metadata_max_description_chars'] ?? $d['metadata_max_description_chars'])));
    $out['metadata_stream_comment_limit'] = max(0, min(20, (int)($in['metadata_stream_comment_limit'] ?? $d['metadata_stream_comment_limit'])));
    $out['metadata_site_name'] = sanitize_text_field((string)($in['metadata_site_name'] ?? ''));
    return $out;
  }
}

if (!function_exists('ia_seo_admin_boot')) {
  function ia_seo_admin_boot(): void {
    add_action('admin_menu', function () {
      add_options_page('IA SEO', 'IA SEO', 'manage_options', 'ia-seo', 'ia_seo_admin_page');
      add_submenu_page('options-general.php', 'IA SEO Metadata', 'IA SEO Metadata', 'manage_options', 'ia-seo-metadata', 'ia_seo_metadata_admin_page');
    });

    add_action('admin_init', function () {
      if (!is_admin() || !current_user_can('manage_options')) return;
      if (!isset($_POST['ia_seo_action'])) return;
      $action = (string)$_POST['ia_seo_action'];
      if (!in_array($action, ['save','flush'], true)) return;
      check_admin_referer('ia_seo_save_settings');
      if ($action === 'flush') {
        ia_seo_sitemap_flush_cache();
        add_settings_error('ia_seo', 'flushed', 'Sitemap cache flushed.', 'updated');
        return;
      }
      $new = ia_seo_sanitize_settings($_POST['ia_seo'] ?? []);
      update_option(ia_seo_opt_key(), $new, false);
      ia_seo_sitemap_flush_cache();
      add_settings_error('ia_seo', 'saved', 'Settings saved.', 'updated');
    });
  }
}

if (!function_exists('ia_seo_admin_page')) {
  function ia_seo_admin_page(): void {
    if (!current_user_can('manage_options')) {
      echo '<div class="wrap"><h1>IA SEO</h1><p>You do not have permission.</p></div>';
      return;
    }
    $s = ia_seo_get_settings();
    settings_errors('ia_seo');
    $sitemap_url = home_url('/sitemap.xml');
    $sitemap_fallback_url = add_query_arg([ ia_seo_qv() => 1 ], home_url('/'));
    $wp_sitemap_url = home_url('/wp-sitemap.xml');

    echo '<div class="wrap"><h1>IA SEO</h1>';
    echo '<p><strong>Sitemap URL:</strong> <code>' . esc_html($sitemap_url) . '</code></p>';
    echo '<p><strong>Fallback sitemap URL:</strong> <code>' . esc_html($sitemap_fallback_url) . '</code></p>';
    echo '<p class="description">If your server or a security layer causes redirect loops on <code>/sitemap.xml</code>, the fallback URL should still work because it does not depend on rewrite rules.</p>';
    echo '<p><strong>WordPress core sitemap:</strong> <code>' . esc_html($wp_sitemap_url) . '</code> (disabled by IA SEO when enabled)</p>';
    echo '<p><a class="button" href="' . esc_url(admin_url('options-general.php?page=ia-seo-metadata')) . '">Open metadata controls</a></p>';
    echo '<form method="post" action="">';
    wp_nonce_field('ia_seo_save_settings');
    echo '<input type="hidden" name="ia_seo_action" value="save">';
    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row">Enable sitemap</th><td><label><input type="checkbox" name="ia_seo[enabled]" value="1" ' . checked(1, (int)$s['enabled'], false) . '> Enabled</label></td></tr>';
    echo '<tr><th scope="row">WordPress sitemap</th><td>';
    echo '<label style="display:block"><input type="checkbox" name="ia_seo[disable_wp_core_sitemap]" value="1" ' . checked(1, (int)$s['disable_wp_core_sitemap'], false) . '> Disable WordPress core sitemap (<code>/wp-sitemap.xml</code>)</label>';
    echo '<label style="display:block"><input type="checkbox" name="ia_seo[redirect_wp_sitemap]" value="1" ' . checked(1, (int)$s['redirect_wp_sitemap'], false) . '> Redirect <code>/wp-sitemap.xml</code> → <code>/sitemap.xml</code></label>';
    echo '<p class="description">IA SEO is intended to expose Atrium URLs only, not WordPress posts/pages/taxonomies.</p></td></tr>';
    echo '<tr><th scope="row">Atrium base URL</th><td><input type="url" class="regular-text" name="ia_seo[atr_base]" value="' . esc_attr((string)$s['atr_base']) . '" placeholder="https://example.com/"><p class="description">This should be the page URL that hosts Atrium. If blank, IA SEO attempts to auto-detect.</p></td></tr>';
    echo '<tr><th scope="row">Cache TTL (seconds)</th><td><input type="number" min="0" max="86400" name="ia_seo[cache_ttl]" value="' . esc_attr((string)$s['cache_ttl']) . '"><p class="description">0 disables caching. Recommended: 300.</p></td></tr>';

    echo '<tr><th scope="row">Include</th><td>';
    foreach ([
      'include_home' => 'Home URL',
      'include_connect_surface' => 'Connect landing surface',
      'include_discuss_surface' => 'Discuss landing surface',
      'include_stream_surface' => 'Stream landing surface (Discover)',
      'include_connect_profiles' => 'Connect public profiles',
      'include_discuss_agoras' => 'Discuss public Agoras',
      'include_discuss_topics' => 'Discuss topics',
      'include_discuss_posts' => 'Discuss reply deep-links',
      'include_connect_posts' => 'Connect wall posts',
      'include_stream_channels' => 'Stream public channels',
      'include_stream_videos' => 'Stream public videos',
    ] as $key => $label) {
      echo '<label style="display:block"><input type="checkbox" name="ia_seo[' . esc_attr($key) . ']" value="1" ' . checked(1, (int)$s[$key], false) . '> ' . esc_html($label) . '</label>';
    }
    echo '<p class="description">Search, subscription, history, playlist, comment, reply, and other modal-only or logged-in deep-links are intentionally excluded.</p></td></tr>';

    echo '<tr><th scope="row">Limits</th><td>';
    foreach ([
      'max_connect_profiles' => 'Connect profiles max',
      'max_discuss_agoras' => 'Discuss Agoras max',
      'max_discuss_topics' => 'Discuss topics max',
      'max_discuss_posts' => 'Discuss replies max',
      'max_connect_posts' => 'Connect posts max',
      'max_stream_channels' => 'Stream channels max',
      'max_stream_videos' => 'Stream videos max',
    ] as $key => $label) {
      echo '<p><label>' . esc_html($label) . ': <input type="number" min="0" max="200000" name="ia_seo[' . esc_attr($key) . ']" value="' . esc_attr((string)$s[$key]) . '"></label></p>';
    }
    echo '<p class="description">These caps prevent huge XML output.</p></td></tr>';

    echo '<tr><th scope="row">Changefreq</th><td>';
    foreach ([
      'changefreq_home' => 'Home',
      'changefreq_connect' => 'Connect surface/posts',
      'changefreq_discuss' => 'Discuss surface/topics',
      'changefreq_stream' => 'Stream surface',
      'changefreq_profiles' => 'Profiles',
      'changefreq_agoras' => 'Agoras',
      'changefreq_stream_channels' => 'Stream channels',
      'changefreq_stream_videos' => 'Stream videos',
    ] as $key => $label) {
      echo '<p><label>' . esc_html($label) . ': <select name="ia_seo[' . esc_attr($key) . ']">' . ia_seo_admin_freq_opts((string)$s[$key]) . '</select></label></p>';
    }
    echo '</td></tr>';

    echo '<tr><th scope="row">Priority</th><td>';
    foreach ([
      'priority_home' => 'Home',
      'priority_connect_surface' => 'Connect surface',
      'priority_discuss_surface' => 'Discuss surface',
      'priority_connect_profile' => 'Connect profile',
      'priority_discuss_agora' => 'Discuss Agora',
      'priority_discuss_topic' => 'Discuss topic',
      'priority_discuss_post' => 'Discuss reply',
      'priority_connect_post' => 'Connect post',
      'priority_stream_surface' => 'Stream surface',
      'priority_stream_channel' => 'Stream channel',
      'priority_stream_video' => 'Stream video',
    ] as $key => $label) {
      echo '<p><label>' . esc_html($label) . ': <input type="text" name="ia_seo[' . esc_attr($key) . ']" value="' . esc_attr((string)$s[$key]) . '"></label></p>';
    }
    echo '<p class="description">Values must be between 0.0 and 1.0.</p></td></tr>';
    echo '</table>';
    submit_button('Save settings');
    echo '</form>';
    echo '<form method="post" action="" style="margin-top:1em;">';
    wp_nonce_field('ia_seo_save_settings');
    echo '<input type="hidden" name="ia_seo_action" value="flush">';
    submit_button('Flush sitemap cache', 'secondary', 'submit', false);
    echo '</form></div>';
  }
}

if (!function_exists('ia_seo_metadata_admin_page')) {
  function ia_seo_metadata_admin_page(): void {
    if (!current_user_can('manage_options')) {
      echo '<div class="wrap"><h1>IA SEO Metadata</h1><p>You do not have permission.</p></div>';
      return;
    }
    $s = ia_seo_get_settings();
    settings_errors('ia_seo');
    $analytics = ia_seo_metadata_collect_analytics($s);
    echo '<div class="wrap"><h1>IA SEO Metadata</h1>';
    echo '<p>Controls for page-level meta description and JSON-LD, with a basic visibility summary for Connect, Discuss, and Stream.</p>';
    echo '<form method="post" action="">';
    wp_nonce_field('ia_seo_save_settings');
    echo '<input type="hidden" name="ia_seo_action" value="save">';
    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row">Metadata output</th><td>';
    foreach ([
      'metadata_enabled' => 'Enable page-level metadata output',
      'metadata_jsonld_enabled' => 'Emit JSON-LD structured data',
      'metadata_connect_enabled' => 'Connect metadata',
      'metadata_discuss_enabled' => 'Discuss metadata',
      'metadata_stream_enabled' => 'Stream metadata',
      'metadata_include_discuss_replies' => 'Include Discuss replies in topic descriptions',
      'metadata_include_stream_comments' => 'Include Stream comments/replies in video descriptions',
      'sitemap_stream_video_metadata' => 'Embed Stream video title/description/thumbnail in sitemap where available',
    ] as $key => $label) {
      echo '<label style="display:block"><input type="checkbox" name="ia_seo[' . esc_attr($key) . ']" value="1" ' . checked(1, (int)$s[$key], false) . '> ' . esc_html($label) . '</label>';
    }
    echo '</td></tr>';
    echo '<tr><th scope="row">Metadata shaping</th><td>';
    echo '<p><label>Site name override: <input type="text" class="regular-text" name="ia_seo[metadata_site_name]" value="' . esc_attr((string)$s['metadata_site_name']) . '"></label></p>';
    echo '<p><label>Max description chars: <input type="number" min="80" max="1000" name="ia_seo[metadata_max_description_chars]" value="' . esc_attr((string)$s['metadata_max_description_chars']) . '"></label></p>';
    echo '<p><label>Max Stream comment threads used in metadata: <input type="number" min="0" max="20" name="ia_seo[metadata_stream_comment_limit]" value="' . esc_attr((string)$s['metadata_stream_comment_limit']) . '"></label></p>';
    echo '</td></tr>';
    echo '</table>';
    submit_button('Save metadata settings');
    echo '</form>';

    echo '<h2 style="margin-top:2em;">Basic analytics</h2>';
    echo '<table class="widefat striped" style="max-width:1000px"><thead><tr><th>Surface</th><th>Enabled</th><th>Count within current cap</th><th>Notes</th></tr></thead><tbody>';
    foreach ($analytics['rows'] as $row) {
      echo '<tr><td>' . esc_html($row['label']) . '</td><td>' . esc_html($row['enabled']) . '</td><td>' . esc_html((string)$row['count']) . '</td><td>' . esc_html($row['note']) . '</td></tr>';
    }
    echo '</tbody></table>';

    if (!empty($analytics['previews'])) {
      echo '<h2 style="margin-top:2em;">Metadata previews</h2>';
      foreach ($analytics['previews'] as $preview) {
        echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:12px 14px;margin:0 0 12px 0;max-width:1000px">';
        echo '<p style="margin:0 0 6px 0;"><strong>' . esc_html($preview['label']) . '</strong></p>';
        echo '<p style="margin:0 0 6px 0;color:#1a0dab;font-size:18px;">' . esc_html($preview['title']) . '</p>';
        echo '<p style="margin:0 0 6px 0;color:#006621;">' . esc_html($preview['url']) . '</p>';
        echo '<p style="margin:0;">' . esc_html($preview['description']) . '</p>';
        echo '</div>';
      }
    }
    echo '</div>';
  }
}

if (!function_exists('ia_seo_metadata_collect_analytics')) {
  function ia_seo_metadata_collect_analytics(array $s): array {
    $rows = [];
    $previews = [];
    $connect = new IA_SEO_Connect_Metadata_DB();
    $phpbb = new IA_SEO_PHPBB_Metadata_DB();
    $stream = new IA_SEO_Stream_Service();

    $profiles = $connect->get_public_profiles(min(200, (int)$s['max_connect_profiles']));
    $rows[] = ['label' => 'Connect profiles', 'enabled' => !empty($s['metadata_connect_enabled']) ? 'Yes' : 'No', 'count' => count($profiles), 'note' => 'Profiles visible to SEO under current Connect privacy rules.'];
    if (!empty($profiles[0])) {
      $meta = ia_seo_metadata_for_preview(['tab' => 'connect', 'ia_profile_name' => $profiles[0]['slug']], $s);
      if ($meta) $previews[] = ['label' => 'Connect profile preview', 'title' => $meta['title'], 'url' => $meta['url'], 'description' => $meta['description']];
    }

    $posts = $connect->get_posts(min(200, (int)$s['max_connect_posts']));
    $rows[] = ['label' => 'Connect posts', 'enabled' => !empty($s['metadata_connect_enabled']) ? 'Yes' : 'No', 'count' => count($posts), 'note' => 'Published wall posts under current sitemap cap.'];
    if (!empty($posts[0]['id'])) {
      $meta = ia_seo_metadata_for_preview(['tab' => 'connect', 'ia_post' => (int)$posts[0]['id']], $s);
      if ($meta) $previews[] = ['label' => 'Connect post preview', 'title' => $meta['title'], 'url' => $meta['url'], 'description' => $meta['description']];
    }

    $forums = $phpbb->ok() ? $phpbb->get_public_forums(min(200, (int)$s['max_discuss_agoras'])) : [];
    $rows[] = ['label' => 'Discuss Agoras', 'enabled' => !empty($s['metadata_discuss_enabled']) ? 'Yes' : 'No', 'count' => count($forums), 'note' => 'Public Agoras only.'];
    if (!empty($forums[0]['forum_id'])) {
      $meta = ia_seo_metadata_for_preview(['tab' => 'discuss', 'iad_view' => 'agora', 'iad_forum' => (int)$forums[0]['forum_id']], $s);
      if ($meta) $previews[] = ['label' => 'Discuss Agora preview', 'title' => $meta['title'], 'url' => $meta['url'], 'description' => $meta['description']];
    }

    $topics = $phpbb->ok() ? $phpbb->get_topics(min(200, (int)$s['max_discuss_topics'])) : [];
    $rows[] = ['label' => 'Discuss topics', 'enabled' => !empty($s['metadata_discuss_enabled']) ? 'Yes' : 'No', 'count' => count($topics), 'note' => 'Visible topics in public Agoras.'];
    if (!empty($topics[0]['topic_id'])) {
      $meta = ia_seo_metadata_for_preview(['tab' => 'discuss', 'iad_topic' => (int)$topics[0]['topic_id']], $s);
      if ($meta) $previews[] = ['label' => 'Discuss topic preview', 'title' => $meta['title'], 'url' => $meta['url'], 'description' => $meta['description']];
    }

    $channels = $stream->ok() ? $stream->get_channels(min(50, (int)$s['max_stream_channels'])) : [];
    $rows[] = ['label' => 'Stream channels', 'enabled' => !empty($s['metadata_stream_enabled']) ? 'Yes' : 'No', 'count' => count($channels), 'note' => 'Public channels fetched from PeerTube public API.'];
    if (!empty($channels[0]['handle'])) {
      $meta = ia_seo_metadata_for_preview(['tab' => 'stream', 'stream_channel' => (string)$channels[0]['handle']], $s);
      if ($meta) $previews[] = ['label' => 'Stream channel preview', 'title' => $meta['title'], 'url' => $meta['url'], 'description' => $meta['description']];
    }

    $videos = $stream->ok() ? $stream->get_videos(min(50, (int)$s['max_stream_videos'])) : [];
    $rows[] = ['label' => 'Stream videos', 'enabled' => !empty($s['metadata_stream_enabled']) ? 'Yes' : 'No', 'count' => count($videos), 'note' => 'Public videos fetched from PeerTube public API.'];
    if (!empty($videos[0]['id'])) {
      $meta = ia_seo_metadata_for_preview(['tab' => 'stream', 'video' => (string)$videos[0]['id']], $s);
      if ($meta) $previews[] = ['label' => 'Stream video preview', 'title' => $meta['title'], 'url' => $meta['url'], 'description' => $meta['description']];
    }

    return ['rows' => $rows, 'previews' => $previews];
  }
}

if (!function_exists('ia_seo_metadata_for_preview')) {
  function ia_seo_metadata_for_preview(array $fake_get, array $settings): ?array {
    $old = $_GET;
    $_GET = $fake_get;
    try {
      $meta = ia_seo_metadata_for_request($settings);
    } finally {
      $_GET = $old;
    }
    return $meta;
  }
}

if (!function_exists('ia_seo_admin_freq_opts')) {
  function ia_seo_admin_freq_opts(string $selected): string {
    $out = '';
    foreach (['always','hourly','daily','weekly','monthly','yearly','never'] as $v) {
      $out .= '<option value="' . esc_attr($v) . '" ' . selected($selected, $v, false) . '>' . esc_html(ucfirst($v)) . '</option>';
    }
    return $out;
  }
}
