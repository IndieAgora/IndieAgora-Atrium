<?php
if (!defined('ABSPATH')) exit;

final class IA_Cache_Control_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);

        add_action('admin_post_ia_cache_bump_me',      [$this, 'bump_me']);
        add_action('admin_post_ia_cache_bump_global',  [$this, 'bump_global']);
        add_action('admin_post_ia_cache_save_settings',[$this, 'save_settings']);
    }

    public function menu(): void {
        add_menu_page(
            'IA Cache',
            'IA Cache',
            'manage_options',
            'ia-cache',
            [$this, 'render'],
            'dashicons-update',
            59
        );
    }

    private function tab(): string {
        return sanitize_key($_GET['tab'] ?? 'status');
    }

    public function assets($hook): void {
        if ($hook !== 'toplevel_page_ia-cache') return;

        wp_register_style('ia-cache-admin', plugins_url('../../assets/css/admin.css', __FILE__), [], IA_CACHE_CONTROL_VERSION);
        wp_enqueue_style('ia-cache-admin');
    }

    public function render(): void {
        if (!current_user_can('manage_options')) return;

        $tab = $this->tab();
        $tabs = [
            'status'   => 'Status',
            'controls' => 'Controls',
            'advanced' => 'Advanced',
        ];

        echo '<div class="wrap"><h1>IA Cache Control</h1>';
        echo '<nav class="nav-tab-wrapper">';
        foreach ($tabs as $k => $label) {
            $cls = ($tab === $k) ? ' nav-tab-active' : '';
            echo '<a class="nav-tab' . esc_attr($cls) . '" href="' . esc_url(admin_url('admin.php?page=ia-cache&tab=' . $k)) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';

        if ($tab === 'controls') $this->render_controls();
        elseif ($tab === 'advanced') $this->render_advanced();
        else $this->render_status();

        echo '</div>';
    }

    private function render_status(): void {
        $uid = get_current_user_id();
        $ue  = IA_Cache_Control::get_user_epoch($uid);
        $ge  = IA_Cache_Control::get_global_epoch();
        $s   = IA_Cache_Control::get_settings();

        echo '<div class="ia-box">';
        echo '<h2>Current epochs</h2>';
        echo '<table class="widefat striped">';
        echo '<tbody>';
        echo '<tr><th style="width:220px;">Your user epoch</th><td><code>' . esc_html((string)$ue) . '</code></td></tr>';
        echo '<tr><th>Global epoch</th><td><code>' . esc_html((string)$ge) . '</code></td></tr>';
        echo '</tbody></table>';
        echo '<p class="description">Epochs are appended to IA plugin asset URLs on Atrium pages as <code>iaue</code> (user) and <code>iage</code> (global).</p>';
        echo '</div>';

        echo '<div class="ia-box">';
        echo '<h2>Active safeguards</h2>';
        echo '<ul class="ia-list">';
        echo '<li><strong>No-store headers for admins on Atrium pages:</strong> ' . (!empty($s['no_store_admin']) ? 'Enabled' : 'Disabled') . '</li>';
        echo '<li><strong>Front-end diagnostics footer (Atrium pages):</strong> ' . (!empty($s['diag_footer']) ? 'Enabled' : 'Disabled') . '</li>';
        echo '</ul>';
        echo '</div>';
    }

    private function render_controls(): void {
        $me = wp_get_current_user();
        $bump_me_url = esc_url(admin_url('admin-post.php'));
        $bump_global_url = esc_url(admin_url('admin-post.php'));

        echo '<div class="ia-box">';
        echo '<h2>Immediate cache bust</h2>';
        echo '<p>Use these when you have deployed new zips and want to eliminate stale JS/CSS for the admin session.</p>';

        // Bump me
        echo '<form method="post" action="' . $bump_me_url . '">';
        echo '<input type="hidden" name="action" value="ia_cache_bump_me" />';
        wp_nonce_field('ia_cache_bump_me');
        echo '<p><button class="button button-primary" type="submit">Bump my Atrium cache</button> <span class="description">For <strong>' . esc_html($me->user_login) . '</strong> only.</span></p>';
        echo '</form>';

        // Bump global
        echo '<form method="post" action="' . $bump_global_url . '">';
        echo '<input type="hidden" name="action" value="ia_cache_bump_global" />';
        wp_nonce_field('ia_cache_bump_global');
        echo '<p><button class="button" type="submit">Bump global Atrium cache</button> <span class="description">For all users (next reload).</span></p>';
        echo '</form>';

        echo '<hr/>';

        echo '<h3>Browser-level reset (this computer)</h3>';
        echo '<p class="description">If you are running Atrium as an installed PWA, a service worker may continue serving stale bundles even after versioned URLs. In that case, disable the SW in the browser (Application → Service Workers) or uninstall/reinstall the PWA. This plugin can show whether the current Atrium page is controlled (see Advanced tab).</p>';

        echo '</div>';
    }

    private function render_advanced(): void {
        $s = IA_Cache_Control::get_settings();

        echo '<div class="ia-box">';
        echo '<h2>Advanced</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="ia_cache_save_settings" />';
        wp_nonce_field('ia_cache_save_settings');

        $no_store = !empty($s['no_store_admin']) ? 'checked' : '';
        $diag     = !empty($s['diag_footer']) ? 'checked' : '';

        echo '<label class="ia-toggle"><input type="checkbox" name="no_store_admin" value="1" ' . $no_store . ' /> Add <code>no-store</code> headers for admins on Atrium pages</label><br/>';
        echo '<label class="ia-toggle"><input type="checkbox" name="diag_footer" value="1" ' . $diag . ' /> Show diagnostics footer on Atrium pages (admins only)</label>';

        echo '<p><button class="button button-primary" type="submit">Save</button></p>';
        echo '</form>';
        echo '</div>';

        echo '<div class="ia-box">';
        echo '<h2>What this plugin touches</h2>';
        echo '<p class="description">Only front-end pages containing the <code>[ia-atrium]</code> shortcode. Only assets that belong to IA plugins (handle prefix <code>ia-</code>/<code>ia_</code> or plugin path under <code>wp-content/plugins/ia-*</code>).</p>';
        echo '</div>';
    }

    public function bump_me(): void {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ia_cache_bump_me');

        $uid = get_current_user_id();
        IA_Cache_Control::bump_user_epoch($uid);

        wp_safe_redirect(admin_url('admin.php?page=ia-cache&tab=status&bumped=me'));
        exit;
    }

    public function bump_global(): void {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ia_cache_bump_global');

        IA_Cache_Control::bump_global_epoch();

        wp_safe_redirect(admin_url('admin.php?page=ia-cache&tab=status&bumped=global'));
        exit;
    }

    public function save_settings(): void {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ia_cache_save_settings');

        IA_Cache_Control::update_settings([
            'no_store_admin' => !empty($_POST['no_store_admin']) ? 1 : 0,
            'diag_footer'    => !empty($_POST['diag_footer']) ? 1 : 0,
        ]);

        wp_safe_redirect(admin_url('admin.php?page=ia-cache&tab=advanced&saved=1'));
        exit;
    }
}
