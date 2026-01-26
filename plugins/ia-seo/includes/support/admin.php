<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('ia_seo_opt_key')) {
  function ia_seo_opt_key(): string { return 'ia_seo_settings_v1'; }
}

if (!function_exists('ia_seo_defaults')) {
  function ia_seo_defaults(): array {
    return [
      'enabled' => 1,
      // This plugin generates an Atrium-only sitemap. Disable WP core sitemap by default.
      'disable_wp_core_sitemap' => 1,
      // Redirect /wp-sitemap.xml to /sitemap.xml (optional convenience).
      'redirect_wp_sitemap' => 1,
      // Do not include the WordPress home URL unless explicitly enabled.
      'include_home' => 0,
      'atr_base' => '',
      'cache_ttl' => 300,
      'include_discuss_topics' => 1,
      'include_discuss_posts' => 1,
      'include_connect_posts' => 1,
      'max_discuss_topics' => 5000,
      'max_discuss_posts' => 5000,
      'max_connect_posts' => 5000,
      'priority_home' => '0.8',
      'priority_discuss_topic' => '0.7',
      'priority_discuss_post' => '0.5',
      'priority_connect_post' => '0.6',
      'changefreq_home' => 'daily',
      'changefreq_discuss' => 'hourly',
      'changefreq_connect' => 'hourly',
    ];
  }
}

if (!function_exists('ia_seo_get_settings')) {
  function ia_seo_get_settings(): array {
    $raw = get_option(ia_seo_opt_key(), []);
    if (!is_array($raw)) $raw = [];
    $s = array_merge(ia_seo_defaults(), $raw);
    // Auto-detect Atrium base page if empty.
    if (trim((string)$s['atr_base']) === '') {
      $s['atr_base'] = ia_seo_detect_atrium_base_url();
    }
    return $s;
  }
}

if (!function_exists('ia_seo_detect_atrium_base_url')) {
  function ia_seo_detect_atrium_base_url(): string {
    // Find first published page containing [ia-atrium] shortcode.
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
    if ($ttl < 0) $ttl = 0;
    if ($ttl > 86400) $ttl = 86400;
    $out['cache_ttl'] = $ttl;

    $out['include_discuss_topics'] = !empty($in['include_discuss_topics']) ? 1 : 0;
    $out['include_discuss_posts']  = !empty($in['include_discuss_posts']) ? 1 : 0;
    $out['include_connect_posts']  = !empty($in['include_connect_posts']) ? 1 : 0;

    $out['max_discuss_topics'] = max(0, min(200000, (int)($in['max_discuss_topics'] ?? $d['max_discuss_topics'])));
    $out['max_discuss_posts']  = max(0, min(200000, (int)($in['max_discuss_posts'] ?? $d['max_discuss_posts'])));
    $out['max_connect_posts']  = max(0, min(200000, (int)($in['max_connect_posts'] ?? $d['max_connect_posts'])));

    foreach (['priority_home','priority_discuss_topic','priority_discuss_post','priority_connect_post'] as $k) {
      $v = (string)($in[$k] ?? $d[$k]);
      $v = preg_replace('/[^0-9.]/', '', $v);
      if ($v === '') $v = (string)$d[$k];
      $f = (float)$v;
      if ($f < 0.0) $f = 0.0;
      if ($f > 1.0) $f = 1.0;
      $out[$k] = rtrim(rtrim(sprintf('%.2f', $f), '0'), '.');
    }

    $allowed_freq = ['always','hourly','daily','weekly','monthly','yearly','never'];
    foreach (['changefreq_home','changefreq_discuss','changefreq_connect'] as $k) {
      $v = strtolower(trim((string)($in[$k] ?? $d[$k])));
      if (!in_array($v, $allowed_freq, true)) $v = (string)$d[$k];
      $out[$k] = $v;
    }

    return $out;
  }
}

if (!function_exists('ia_seo_admin_boot')) {
  function ia_seo_admin_boot(): void {
    add_action('admin_menu', function () {
      add_options_page('IA SEO', 'IA SEO', 'manage_options', 'ia-seo', 'ia_seo_admin_page');
    });

    add_action('admin_init', function () {
      if (!is_admin()) return;
      if (!current_user_can('manage_options')) return;

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

    echo '<div class="wrap">';
    echo '<h1>IA SEO</h1>';

    echo '<p><strong>Sitemap URL:</strong> <code>' . esc_html($sitemap_url) . '</code></p>';
    echo '<p><strong>Fallback sitemap URL:</strong> <code>' . esc_html($sitemap_fallback_url) . '</code></p>';
    echo '<p class="description">If your server or a security layer causes redirect loops on <code>/sitemap.xml</code>, the fallback URL should still work because it does not depend on rewrite rules.</p>';
    echo '<p><strong>WordPress core sitemap:</strong> <code>' . esc_html($wp_sitemap_url) . '</code> (disabled by IA SEO when enabled)</p>';

    echo '<form method="post" action="">';
    wp_nonce_field('ia_seo_save_settings');
    echo '<input type="hidden" name="ia_seo_action" value="save">';

    echo '<table class="form-table" role="presentation">';

    echo '<tr><th scope="row">Enable sitemap</th><td>';
    echo '<label><input type="checkbox" name="ia_seo[enabled]" value="1" ' . checked(1, (int)$s['enabled'], false) . '> Enabled</label>';
    echo '</td></tr>';

    echo '<tr><th scope="row">WordPress sitemap</th><td>';
    echo '<label style="display:block"><input type="checkbox" name="ia_seo[disable_wp_core_sitemap]" value="1" ' . checked(1, (int)$s['disable_wp_core_sitemap'], false) . '> Disable WordPress core sitemap (<code>/wp-sitemap.xml</code>)</label>';
    echo '<label style="display:block"><input type="checkbox" name="ia_seo[redirect_wp_sitemap]" value="1" ' . checked(1, (int)$s['redirect_wp_sitemap'], false) . '> Redirect <code>/wp-sitemap.xml</code> → <code>/sitemap.xml</code></label>';
    echo '<p class="description">IA SEO is intended to expose Atrium URLs only (Connect + Discuss), not WordPress posts/pages/taxonomies.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Atrium base URL</th><td>';
    echo '<input type="url" class="regular-text" name="ia_seo[atr_base]" value="' . esc_attr((string)$s['atr_base']) . '" placeholder="https://example.com/atrium/">';
    echo '<p class="description">This should be the page URL that hosts Atrium (the page containing the [ia-atrium] shortcode). If blank, IA SEO attempts to auto-detect.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Cache TTL (seconds)</th><td>';
    echo '<input type="number" min="0" max="86400" name="ia_seo[cache_ttl]" value="' . esc_attr((string)$s['cache_ttl']) . '">';
    echo '<p class="description">0 disables caching (slower). Recommended: 300.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Include</th><td>';
    echo '<label style="display:block"><input type="checkbox" name="ia_seo[include_home]" value="1" ' . checked(1, (int)$s['include_home'], false) . '> Home URL (<code>' . esc_html(home_url('/')) . '</code>)</label>';
    echo '<label style="display:block"><input type="checkbox" name="ia_seo[include_discuss_topics]" value="1" ' . checked(1, (int)$s['include_discuss_topics'], false) . '> Discuss topics</label>';
    echo '<label style="display:block"><input type="checkbox" name="ia_seo[include_discuss_posts]" value="1" ' . checked(1, (int)$s['include_discuss_posts'], false) . '> Discuss replies (post deep-links)</label>';
    echo '<label style="display:block"><input type="checkbox" name="ia_seo[include_connect_posts]" value="1" ' . checked(1, (int)$s['include_connect_posts'], false) . '> Connect wall posts</label>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Limits</th><td>';
    echo '<p><label>Discuss topics max: <input type="number" min="0" max="200000" name="ia_seo[max_discuss_topics]" value="' . esc_attr((string)$s['max_discuss_topics']) . '"></label></p>';
    echo '<p><label>Discuss replies max: <input type="number" min="0" max="200000" name="ia_seo[max_discuss_posts]" value="' . esc_attr((string)$s['max_discuss_posts']) . '"></label></p>';
    echo '<p><label>Connect posts max: <input type="number" min="0" max="200000" name="ia_seo[max_connect_posts]" value="' . esc_attr((string)$s['max_connect_posts']) . '"></label></p>';
    echo '<p class="description">These caps prevent huge XML output.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Changefreq</th><td>';
    echo '<p><label>Home: <select name="ia_seo[changefreq_home]">' . ia_seo_admin_freq_opts($s['changefreq_home']) . '</select></label></p>';
    echo '<p><label>Discuss: <select name="ia_seo[changefreq_discuss]">' . ia_seo_admin_freq_opts($s['changefreq_discuss']) . '</select></label></p>';
    echo '<p><label>Connect: <select name="ia_seo[changefreq_connect]">' . ia_seo_admin_freq_opts($s['changefreq_connect']) . '</select></label></p>';
    echo '</td></tr>';

    echo '<tr><th scope="row">Priority</th><td>';
    echo '<p><label>Home: <input type="text" name="ia_seo[priority_home]" value="' . esc_attr((string)$s['priority_home']) . '"></label></p>';
    echo '<p><label>Discuss topic: <input type="text" name="ia_seo[priority_discuss_topic]" value="' . esc_attr((string)$s['priority_discuss_topic']) . '"></label></p>';
    echo '<p><label>Discuss reply: <input type="text" name="ia_seo[priority_discuss_post]" value="' . esc_attr((string)$s['priority_discuss_post']) . '"></label></p>';
    echo '<p><label>Connect post: <input type="text" name="ia_seo[priority_connect_post]" value="' . esc_attr((string)$s['priority_connect_post']) . '"></label></p>';
    echo '<p class="description">Values must be between 0.0 and 1.0.</p>';
    echo '</td></tr>';

    echo '</table>';

    submit_button('Save changes');
    echo '</form>';

    echo '<hr />';
    echo '<form method="post" action="" style="margin-top:12px">';
    wp_nonce_field('ia_seo_save_settings');
    echo '<input type="hidden" name="ia_seo_action" value="flush">';
    submit_button('Flush sitemap cache', 'secondary');
    echo '</form>';

    echo '</div>';
  }
}

if (!function_exists('ia_seo_admin_freq_opts')) {
  function ia_seo_admin_freq_opts(string $cur): string {
    $opts = ['always','hourly','daily','weekly','monthly','yearly','never'];
    $html = '';
    foreach ($opts as $o) {
      $html .= '<option value="' . esc_attr($o) . '" ' . selected($cur, $o, false) . '>' . esc_html($o) . '</option>';
    }
    return $html;
  }
}
