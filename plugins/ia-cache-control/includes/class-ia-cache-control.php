<?php
if (!defined('ABSPATH')) exit;

final class IA_Cache_Control {

    const OPT_GLOBAL_EPOCH   = 'ia_cache_global_epoch';
    const OPT_SETTINGS       = 'ia_cache_control_settings';

    const UMETA_USER_EPOCH   = 'ia_cache_user_epoch';

    public static function boot(): void {
        // Front-end only behaviour (Atrium pages)
        add_action('wp', [__CLASS__, 'maybe_boot_frontend'], 1);

        // Admin UI
        if (is_admin()) {
            new IA_Cache_Control_Admin();
        }
    }

    public static function maybe_boot_frontend(): void {
        if (is_admin()) return;
        if (!self::is_atrium_page()) return;

        // Append epoch parameters to IA plugin assets only.
        add_filter('script_loader_src', [__CLASS__, 'filter_asset_src'], 1000, 2);
        add_filter('style_loader_src',  [__CLASS__, 'filter_asset_src'], 1000, 2);

        // Optionally add no-store headers for admins.
        add_action('send_headers', [__CLASS__, 'maybe_send_no_store_headers'], 0);

        // Diagnostics overlay for admins (optional).
        add_action('wp_footer', [__CLASS__, 'maybe_render_diag_footer'], 999);
    }

    public static function is_atrium_page(): bool {
        if (!is_singular()) return false;

        global $post;
        if (!$post) return false;

        $content = $post->post_content ?? '';
        return is_string($content) && has_shortcode($content, 'ia-atrium');
    }

    public static function get_settings(): array {
        $opt = get_option(self::OPT_SETTINGS, []);
        return is_array($opt) ? $opt : [];
    }

    public static function update_settings(array $patch): void {
        $s = self::get_settings();
        $s = array_merge($s, $patch);
        update_option(self::OPT_SETTINGS, $s, false);
    }

    public static function get_global_epoch(): int {
        $v = (int) get_option(self::OPT_GLOBAL_EPOCH, 0);
        if ($v > 0) return $v;

        // Seed once; keep deterministic.
        $v = time();
        update_option(self::OPT_GLOBAL_EPOCH, $v, false);
        return $v;
    }

    public static function bump_global_epoch(): int {
        $v = time();
        update_option(self::OPT_GLOBAL_EPOCH, $v, false);
        return $v;
    }

    public static function get_user_epoch(int $user_id): int {
        $v = (int) get_user_meta($user_id, self::UMETA_USER_EPOCH, true);
        if ($v > 0) return $v;

        $v = time();
        update_user_meta($user_id, self::UMETA_USER_EPOCH, $v);
        return $v;
    }

    public static function bump_user_epoch(int $user_id): int {
        $v = time();
        update_user_meta($user_id, self::UMETA_USER_EPOCH, $v);
        return $v;
    }

    public static function filter_asset_src(string $src, string $handle): string {
        // Guard: only touch Atrium surface assets (IA plugins).
        // 1) Handle prefix guard.
        $is_ia_handle = (strpos($handle, 'ia-') === 0) || (strpos($handle, 'ia_') === 0);

        // 2) URL path guard.
        $is_ia_path = (strpos($src, '/wp-content/plugins/ia-') !== false)
                   || (strpos($src, '/wp-content/plugins/ia_') !== false);

        if (!$is_ia_handle && !$is_ia_path) return $src;

        $uid = get_current_user_id();
        $ue = ($uid > 0) ? self::get_user_epoch($uid) : 0;
        $ge = self::get_global_epoch();

        // Keep existing ver=... if present; just add epochs.
        $src = add_query_arg('iaue', (string)$ue, $src);
        $src = add_query_arg('iage', (string)$ge, $src);

        return $src;
    }

    public static function maybe_send_no_store_headers(): void {
        $s = self::get_settings();
        $enabled = !empty($s['no_store_admin']);
        if (!$enabled) return;

        if (!current_user_can('manage_options')) return;

        // Defensive: avoid touching non-Atrium.
        if (!self::is_atrium_page()) return;

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    public static function maybe_render_diag_footer(): void {
        $s = self::get_settings();
        if (empty($s['diag_footer'])) return;
        if (!current_user_can('manage_options')) return;

        $uid = get_current_user_id();
        $ue  = ($uid > 0) ? self::get_user_epoch($uid) : 0;
        $ge  = self::get_global_epoch();

        $sw = '<em>unknown</em>';
        // We can't know SW status server-side; expose a target element for JS.
        echo '<div id="ia-cache-diag" style="position:fixed;left:10px;bottom:10px;z-index:999999;background:#111;color:#fff;padding:8px 10px;border-radius:10px;font:12px/1.4 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;opacity:.9">';
        echo '<div><strong>IA Cache</strong></div>';
        echo '<div>User epoch: <span data-ia-ue>' . esc_html((string)$ue) . '</span></div>';
        echo '<div>Global epoch: <span data-ia-ge>' . esc_html((string)$ge) . '</span></div>';
        echo '<div>SW control: <span data-ia-sw>' . $sw . '</span></div>';
        echo '</div>';

        // Inline JS: SW status only. Keep tiny.
        echo "<script>
        (function(){
          try{
            var el=document.querySelector('#ia-cache-diag [data-ia-sw]');
            if(!el) return;
            if(!('serviceWorker' in navigator)) { el.textContent='not supported'; return; }
            if(!navigator.serviceWorker.controller) { el.textContent='no'; return; }
            el.textContent='yes';
          }catch(e){}
        })();
        </script>";
    }
}
