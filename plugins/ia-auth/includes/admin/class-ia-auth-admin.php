<?php
if (!defined('ABSPATH')) { exit; }

class IA_Auth_Admin {

    private $ia;

    const OPT_PRIMARY_ADMIN        = 'ia_auth_primary_admin';
    const OPT_ADMIN_NAG_DISMISSED  = 'ia_auth_admin_nag_dismissed';

    public function __construct($ia_auth) {
        $this->ia = $ia_auth;

        add_action('admin_menu', [$this, 'menu']);

        add_action('admin_post_ia_auth_save_settings', [$this, 'save_settings']);

        add_action('admin_post_ia_auth_define_admin', [$this, 'define_admin']);
        add_action('admin_post_ia_auth_dismiss_define_admin', [$this, 'dismiss_define_admin']);

        add_action('admin_post_ia_auth_migration_scan', [$this, 'migration_scan']);
        add_action('admin_post_ia_auth_migration_apply', [$this, 'migration_apply']);
    }

    public function menu() {
        add_menu_page(
            'IA Auth',
            'IA Auth',
            'manage_options',
            'ia-auth',
            [$this, 'render'],
            'dashicons-shield',
            58
        );
    }

    private function tab() {
        return sanitize_key($_GET['tab'] ?? 'status');
    }

    public function render() {
        if (!current_user_can('manage_options')) return;

        $tab = $this->tab();
        $tabs = [
            'status'     => 'Status',
            'config'     => 'Configuration',
            'identities' => 'Identity map',
            'migration'  => 'Migration tools',
            'security'   => 'Security',
            'logs'       => 'Logs',
        ];

        echo '<div class="wrap"><h1>IA Auth</h1>';
        echo '<nav class="nav-tab-wrapper">';
        foreach ($tabs as $k => $label) {
            $cls = ($tab === $k) ? ' nav-tab-active' : '';
            echo '<a class="nav-tab' . esc_attr($cls) . '" href="' . esc_url(admin_url('admin.php?page=ia-auth&tab=' . $k)) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';

        // Global “Define Admin” urgent notice (shows until set or dismissed)
        $this->render_define_admin_notice_if_needed();

        if ($tab === 'config') $this->render_config();
        elseif ($tab === 'identities') $this->render_identities();
        elseif ($tab === 'migration') $this->render_migration();
        elseif ($tab === 'security') $this->render_security();
        elseif ($tab === 'logs') $this->render_logs();
        else $this->render_status();

        echo '</div>';
    }

    /* =========================================================================
     * OPTIONS
     * ========================================================================= */

    private function opts(): array {
        if (method_exists($this->ia, 'options')) return (array)$this->ia->options();
        $opt = get_option('ia_auth_options', []);
        return is_array($opt) ? $opt : [];
    }

    private function update_opts(array $new): void {
        if (method_exists($this->ia, 'update_options')) {
            $this->ia->update_options($new);
            return;
        }
        $opt = $this->opts();
        $opt = array_merge($opt, $new);
        update_option('ia_auth_options', $opt, false);
    }

    private function get_primary_admin_id(): int {
        $opt = $this->opts();
        return (int)($opt[self::OPT_PRIMARY_ADMIN] ?? 0);
    }

    private function has_primary_admin(): bool {
        $opt = $this->opts();
        if (!empty($opt[self::OPT_PRIMARY_ADMIN])) return true;
        return false;
    }

    private function admin_nag_dismissed(): bool {
        $opt = $this->opts();
        return !empty($opt[self::OPT_ADMIN_NAG_DISMISSED]);
    }

    private function auto_detect_primary_admin(): int {
        // If user already elevated a phpBB-linked shadow user via SQL,
        // detect a WP administrator that has ia_phpbb_user_id meta.
        $cands = $this->candidate_shadow_users(200);
        foreach ($cands as $u) {
            if (user_can($u, 'manage_options')) {
                $this->update_opts([ self::OPT_PRIMARY_ADMIN => (int)$u->ID ]);
                return (int)$u->ID;
            }
        }
        return 0;
    }

    private function candidate_shadow_users(int $limit = 200): array {
        $args = [
            'number' => $limit,
            'orderby' => 'ID',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => 'ia_phpbb_user_id',
                    'compare' => 'EXISTS',
                ],
            ],
        ];
        return get_users($args);
    }

    private function render_define_admin_notice_if_needed(): void {
        // If they already have primary admin, nothing to nag about.
        $primary = $this->get_primary_admin_id();
        if ($primary <= 0) {
            $primary = $this->auto_detect_primary_admin();
        }

        if ($primary > 0) return;

        // Allow dismissal (“I already nominated an admin”)
        if ($this->admin_nag_dismissed()) return;

        $define_url = esc_url(admin_url('admin.php?page=ia-auth&tab=config#ia-auth-define-admin'));

        echo '<div class="notice notice-error" style="border-left-color:#d63638;">';
        echo '<p><strong>IA Auth requires an admin to be defined.</strong></p>';
        echo '<p><a class="button button-primary" href="' . $define_url . '">Define Admin</a></p>';
        echo '</div>';
    }

    /* =========================================================================
     * ACTIONS
     * ========================================================================= */

    public function define_admin() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ia_auth_define_admin');

        $id = (int)($_POST['wp_user_id'] ?? 0);
        if ($id <= 0) {
            wp_safe_redirect(admin_url('admin.php?page=ia-auth&tab=config#ia-auth-define-admin'));
            exit;
        }

        $u = get_user_by('id', $id);
        if (!$u || !$u->exists()) {
            wp_safe_redirect(admin_url('admin.php?page=ia-auth&tab=config#ia-auth-define-admin'));
            exit;
        }

        // Elevate chosen shadow user to admin
        $u->set_role('administrator');

        $this->update_opts([
            self::OPT_PRIMARY_ADMIN => $id,
            self::OPT_ADMIN_NAG_DISMISSED => 0,
        ]);

        if (isset($this->ia->db) && method_exists($this->ia->db, 'audit')) {
            $this->ia->db->audit('define_admin', null, [
                'wp_user_id' => $id,
                'user_login' => $u->user_login,
            ]);
        }

        wp_safe_redirect(admin_url('admin.php?page=ia-auth&tab=config&defined_admin=1#ia-auth-define-admin'));
        exit;
    }

    public function dismiss_define_admin() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ia_auth_dismiss_define_admin');

        $this->update_opts([
            self::OPT_ADMIN_NAG_DISMISSED => 1,
        ]);

        wp_safe_redirect(admin_url('admin.php?page=ia-auth&tab=status&dismissed_admin_notice=1'));
        exit;
    }

    public function save_settings() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ia_auth_save_settings');

        $new = [];

        $new['redirect_wp_login'] = empty($_POST['redirect_wp_login']) ? 0 : 1;
        $new['disable_wp_registration'] = empty($_POST['disable_wp_registration']) ? 0 : 1;

        $new['match_policy'] = sanitize_key($_POST['match_policy'] ?? 'email_then_username');
        $new['wp_shadow_role'] = sanitize_text_field($_POST['wp_shadow_role'] ?? 'subscriber');

        $new['peertube_oauth_method'] = sanitize_key($_POST['peertube_oauth_method'] ?? 'password_grant');
        $new['peertube_fail_policy'] = sanitize_key($_POST['peertube_fail_policy'] ?? 'allow_login');

        // Keep any existing primary-admin/dismiss flags
        $opt = $this->opts();
        if (isset($opt[self::OPT_PRIMARY_ADMIN])) $new[self::OPT_PRIMARY_ADMIN] = (int)$opt[self::OPT_PRIMARY_ADMIN];
        if (isset($opt[self::OPT_ADMIN_NAG_DISMISSED])) $new[self::OPT_ADMIN_NAG_DISMISSED] = (int)$opt[self::OPT_ADMIN_NAG_DISMISSED];

        $this->update_opts($new);

        wp_safe_redirect(admin_url('admin.php?page=ia-auth&tab=config&saved=1'));
        exit;
    }

    public function migration_scan() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ia_auth_migration_scan');

        // Placeholder: scan preview UI already exists; actual scan logic lives in DB/phpbb helper later.
        wp_safe_redirect(admin_url('admin.php?page=ia-auth&tab=migration&scanned=1'));
        exit;
    }

    public function migration_apply() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ia_auth_migration_apply');

        // Placeholder: batch apply wiring later (dry-run first).
        wp_safe_redirect(admin_url('admin.php?page=ia-auth&tab=migration&applied=1'));
        exit;
    }

    /* =========================================================================
     * STATUS TAB  (RESTORED)
     * ========================================================================= */

    private function render_status() {
        echo '<h2>Status dashboard</h2>';

        // Show defined admin if we have one (or can detect)
        $primary = $this->get_primary_admin_id();
        if ($primary <= 0) $primary = $this->auto_detect_primary_admin();
        if ($primary > 0) {
            $u = get_user_by('id', $primary);
            if ($u && $u->exists()) {
                echo '<p><strong>Defined Admin:</strong> ' . esc_html($u->user_login) . ' (WP ID ' . (int)$primary . ')</p>';
            }
        }

        // IMPORTANT: status checks must use IA Engine normalized config (source of truth)
        $engine = (class_exists('IA_Auth') && method_exists('IA_Auth', 'engine_normalized'))
            ? IA_Auth::engine_normalized()
            : ['phpbb' => [], 'peertube_api' => []];

        // phpBB DB test
        $phpbb_ok = false;
        $phpbb_err = '';
        try {
            if (!isset($this->ia->phpbb) || !is_object($this->ia->phpbb)) {
                throw new Exception('phpBB adapter not available.');
            }
            // find_user() should return null (not found) or array (found) if DB connect works
            $u = $this->ia->phpbb->find_user('nonexistent@example.com', (array)($engine['phpbb'] ?? []));
            $phpbb_ok = ($u === null || is_array($u));
        } catch (Throwable $e) {
            $phpbb_err = $e->getMessage();
        }

        // PeerTube OAuth client test
        $pt_ok = false;
        $pt_msg = '';
        try {
            if (!isset($this->ia->peertube) || !is_object($this->ia->peertube)) {
                throw new Exception('PeerTube adapter not available.');
            }
            $pt = $this->ia->peertube->get_oauth_client((array)($engine['peertube_api'] ?? []));
            if (!empty($pt['ok'])) {
                $pt_ok = true;
            } else {
                $pt_msg = (string)($pt['message'] ?? 'Could not fetch OAuth client.');
            }
        } catch (Throwable $e) {
            $pt_msg = $e->getMessage();
        }

        echo '<table class="widefat striped" style="max-width:900px">';
        echo '<thead><tr><th>Component</th><th>Status</th><th>Notes</th></tr></thead><tbody>';
        echo '<tr><td>phpBB DB</td><td>' . ($phpbb_ok ? 'OK' : 'FAIL') . '</td><td>' . esc_html($phpbb_err) . '</td></tr>';
        echo '<tr><td>PeerTube API</td><td>' . ($pt_ok ? 'OK' : 'FAIL') . '</td><td>' . esc_html($pt_msg) . '</td></tr>';
        echo '</tbody></table>';

        $counts = $this->counts();
        echo '<h3>Counts</h3>';
        echo '<ul>';
        echo '<li>Linked identities: <strong>' . (int)$counts['linked'] . '</strong></li>';
        echo '<li>Partial identities: <strong>' . (int)$counts['partial'] . '</strong></li>';
        echo '<li>Disabled identities: <strong>' . (int)$counts['disabled'] . '</strong></li>';
        echo '</ul>';
    }

    private function counts(): array {
        global $wpdb;
        $t = $wpdb->prefix . 'ia_identity_map';

        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
        if (empty($exists)) {
            return ['linked' => 0, 'partial' => 0, 'disabled' => 0];
        }

        $rows = $wpdb->get_results("SELECT status, COUNT(*) AS c FROM `$t` GROUP BY status", ARRAY_A);
        $out = ['linked' => 0, 'partial' => 0, 'disabled' => 0];

        if (is_array($rows)) {
            foreach ($rows as $r) {
                $k = (string)($r['status'] ?? '');
                $c = (int)($r['c'] ?? 0);
                if (isset($out[$k])) $out[$k] = $c;
            }
        }
        return $out;
    }

    /* =========================================================================
     * CONFIG TAB
     * ========================================================================= */

    private function render_config() {
        echo '<h2>Configuration</h2>';

        // --- DEFINE ADMIN PANEL ---
        echo '<hr>';
        echo '<h3 id="ia-auth-define-admin">Define Admin</h3>';

        $primary = $this->get_primary_admin_id();
        if ($primary <= 0) $primary = $this->auto_detect_primary_admin();

        if ($primary > 0) {
            $u = get_user_by('id', $primary);
            echo '<div class="notice notice-success inline" style="padding:12px 14px;margin:12px 0;">';
            if ($u && $u->exists()) {
                echo '<strong>Admin defined:</strong> ' . esc_html($u->user_login) . ' (WP ID ' . (int)$u->ID . ').';
            } else {
                echo '<strong>Admin defined.</strong>';
            }
            echo '</div>';
        } else {
            echo '<div class="notice notice-error inline" style="padding:12px 14px;margin:12px 0;">';
            echo '<strong>Action required:</strong> Select which phpBB-linked shadow user should be the Administrator.';
            echo '</div>';
        }

        $candidates = $this->candidate_shadow_users(300);

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:900px">';
        echo '<input type="hidden" name="action" value="ia_auth_define_admin">';
        wp_nonce_field('ia_auth_define_admin');

        echo '<p><label><strong>Choose admin (phpBB-linked shadow user)</strong></label></p>';
        echo '<select name="wp_user_id" style="min-width:360px;">';
        echo '<option value="0">— Select —</option>';
        foreach ($candidates as $u) {
            $label = $u->user_login . ' (WP ID ' . $u->ID . ')';
            echo '<option value="' . (int)$u->ID . '">' . esc_html($label) . '</option>';
        }
        echo '</select> ';
        echo '<button class="button button-primary" type="submit">Define Admin</button>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:10px;">';
        echo '<input type="hidden" name="action" value="ia_auth_dismiss_define_admin">';
        wp_nonce_field('ia_auth_dismiss_define_admin');
        echo '<button class="button" type="submit">I already nominated an admin</button>';
        echo '</form>';

        // Settings form (keeps your existing fields)
        $opt = $this->opts();

        echo '<hr>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:900px">';
        echo '<input type="hidden" name="action" value="ia_auth_save_settings">';
        wp_nonce_field('ia_auth_save_settings');

        echo '<h3>Matching policy</h3>';
        echo '<p><label>How to match users</label><br>';
        echo '<select name="match_policy">';
        $mp = (string)($opt['match_policy'] ?? 'email_then_username');
        $sel = function($v) use ($mp) { return selected($mp, $v, false); };
        echo '<option value="email_then_username" ' . $sel('email_then_username') . '>Email, then username</option>';
        echo '<option value="username_only" ' . $sel('username_only') . '>Username only</option>';
        echo '</select></p>';

        echo '<h3>WordPress shadow users</h3>';
        echo '<p><label>Shadow role</label><br>';
        echo '<input type="text" name="wp_shadow_role" value="' . esc_attr((string)($opt['wp_shadow_role'] ?? 'subscriber')) . '"></p>';

        echo '<h3>PeerTube</h3>';
        echo '<p><label>OAuth method</label><br>';
        $om = (string)($opt['peertube_oauth_method'] ?? 'password_grant');
        echo '<select name="peertube_oauth_method">';
        echo '<option value="password_grant" ' . selected($om, 'password_grant', false) . '>Password grant (default)</option>';
        echo '</select></p>';

        echo '<p><label>If PeerTube token mint fails</label><br>';
        $fp = (string)($opt['peertube_fail_policy'] ?? 'allow_login');
        echo '<select name="peertube_fail_policy">';
        echo '<option value="allow_login" ' . selected($fp, 'allow_login', false) . '>Allow Atrium login, disable Stream actions</option>';
        echo '<option value="block_login" ' . selected($fp, 'block_login', false) . '>Block login</option>';
        echo '</select></p>';

        echo '<h3>Entry points</h3>';
        $rwl = !empty($opt['redirect_wp_login']);
        $dwr = !empty($opt['disable_wp_registration']);

        echo '<p><label><input type="checkbox" name="redirect_wp_login" value="1" ' . checked($rwl, true, false) . '> Redirect wp-login.php to Atrium login</label></p>';
        echo '<p><label><input type="checkbox" name="disable_wp_registration" value="1" ' . checked($dwr, true, false) . '> Disable native WP registration</label></p>';

        echo '<p><button class="button button-primary" type="submit">Save settings</button></p>';
        echo '</form>';
    }

    /* =========================================================================
     * IDENTITY MAP TAB
     * ========================================================================= */

    private function render_identities() {
        echo '<h2>Identity map</h2>';

        global $wpdb;
        $t = $wpdb->prefix . 'ia_identity_map';

        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
        if (empty($exists)) {
            echo '<p>No identity map table found yet.</p>';
            echo '<p><em>If you just installed the plugin:</em> deactivate → activate once after replacing the bootstrap file, then log in via /ia-login/ to generate rows.</p>';
            return;
        }

        $rows = $wpdb->get_results(
            "SELECT phpbb_user_id, phpbb_username_clean, email, wp_user_id,
                    peertube_user_id, peertube_account_id, peertube_actor_id,
                    status, last_error, updated_at
             FROM `$t`
             ORDER BY updated_at DESC
             LIMIT 100",
            ARRAY_A
        );

        echo '<table class="widefat striped" style="max-width:1100px;">';
        echo '<thead><tr>';
        echo '<th>phpBB ID</th><th>Username</th><th>Email</th><th>WP User</th><th>PeerTube IDs</th><th>Status</th><th>Last error</th>';
        echo '</tr></thead><tbody>';

        if (!$rows) {
            echo '<tr><td colspan="7">No identities yet.</td></tr>';
        } else {
            foreach ($rows as $r) {
                $wp_label = '';
                if (!empty($r['wp_user_id'])) {
                    $u = get_user_by('id', (int)$r['wp_user_id']);
                    $wp_label = $u && $u->exists() ? $u->user_login . ' (ID ' . $u->ID . ')' : 'ID ' . (int)$r['wp_user_id'];
                }

                $pt = [];
                if (!empty($r['peertube_user_id']))    $pt[] = 'u:' . (int)$r['peertube_user_id'];
                if (!empty($r['peertube_account_id'])) $pt[] = 'a:' . (int)$r['peertube_account_id'];
                if (!empty($r['peertube_actor_id']))   $pt[] = 'actor:' . (int)$r['peertube_actor_id'];

                echo '<tr>';
                echo '<td>' . (int)$r['phpbb_user_id'] . '</td>';
                echo '<td>' . esc_html((string)$r['phpbb_username_clean']) . '</td>';
                echo '<td>' . esc_html((string)$r['email']) . '</td>';
                echo '<td>' . esc_html($wp_label) . '</td>';
                echo '<td>' . esc_html(implode(', ', $pt)) . '</td>';
                echo '<td>' . esc_html((string)$r['status']) . '</td>';
                echo '<td>' . esc_html((string)$r['last_error']) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    /* =========================================================================
     * MIGRATION / SECURITY / LOGS  (placeholders remain as-is)
     * ========================================================================= */

    private function render_migration() {
        echo '<h2>Migration tools</h2>';
        echo '<p>Scan phpBB users and apply changes in batches.</p>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:10px;">';
        echo '<input type="hidden" name="action" value="ia_auth_migration_scan">';
        wp_nonce_field('ia_auth_migration_scan');
        echo '<button class="button button-primary" type="submit">Scan &amp; Preview</button>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:10px;">';
        echo '<input type="hidden" name="action" value="ia_auth_migration_apply">';
        wp_nonce_field('ia_auth_migration_apply');
        echo '<label style="margin-right:10px;"><input type="checkbox" name="dry_run" value="1" checked> Dry run only</label>';
        echo '<button class="button button-primary" type="submit">Apply in batches</button>';
        echo '</form>';
    }

    private function render_security() {
        echo '<h2>Security</h2>';
        echo '<p>No audit events yet.</p>';
    }

    private function render_logs() {
        echo '<h2>Logs</h2>';
        echo '<p>No logs yet.</p>';
    }
}
