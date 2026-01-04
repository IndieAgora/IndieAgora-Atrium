<?php
if (!defined('ABSPATH')) { exit; }

class IA_Auth_Admin {

    private $ia;

    const OPT_PRIMARY_ADMIN        = 'ia_auth_primary_admin';
    const OPT_ADMIN_NAG_DISMISSED  = 'ia_auth_admin_nag_dismissed';
    const OPT_LAST_SCAN            = 'ia_auth_last_scan';

    public function __construct($ia_auth) {
        $this->ia = $ia_auth;

        add_action('admin_menu', [$this, 'menu']);

        add_action('admin_post_ia_auth_save_settings', [$this, 'save_settings']);

        add_action('admin_post_ia_auth_define_admin', [$this, 'define_admin']);
        add_action('admin_post_ia_auth_dismiss_define_admin', [$this, 'dismiss_define_admin']);

        add_action('admin_post_ia_auth_migration_scan', [$this, 'migration_scan']);
        add_action('admin_post_ia_auth_migration_apply', [$this, 'migration_apply']);
        add_action('admin_post_ia_auth_cleanup_legacy_shadow_users', [$this, 'cleanup_legacy_shadow_users']);
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

        add_submenu_page('ia-auth', 'Users', 'Users', 'manage_options', 'ia-auth-users', [$this, 'render_users']);

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

        // Optional PeerTube API credentials (used for privileged API calls).
        // NOTE: These are stored in wp_options (not encrypted).
        $new['peertube_api_base'] = esc_url_raw($_POST['peertube_api_base'] ?? '');
        $new['peertube_oauth_client_id'] = sanitize_text_field($_POST['peertube_oauth_client_id'] ?? '');
        $new['peertube_oauth_client_secret'] = sanitize_text_field($_POST['peertube_oauth_client_secret'] ?? '');
        $new['peertube_api_username'] = sanitize_text_field($_POST['peertube_api_username'] ?? '');
        // Keep as-is (sanitize_text_field is fine, but we don't want to trim special chars unexpectedly).
        $new['peertube_api_password'] = isset($_POST['peertube_api_password']) ? (string) $_POST['peertube_api_password'] : '';
        $new['peertube_api_access_token'] = preg_replace('/[^a-f0-9]/i', '', (string) ($_POST['peertube_api_access_token'] ?? ''));
        $new['peertube_api_refresh_token'] = preg_replace('/[^a-f0-9]/i', '', (string) ($_POST['peertube_api_refresh_token'] ?? ''));

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

        // This must be allowed BEFORE a primary admin is defined.
        // Reason: first-run bootstrap needs to scan phpBB to create shadow users
        // so the admin can be selected from phpBB-linked users.

        $cfg = [];
        if (class_exists('IA_Auth') && method_exists('IA_Auth', 'engine_normalized')) {
            $cfg = IA_Auth::engine_normalized();
        }

        $phpbb_cfg = (array)($cfg['phpbb'] ?? []);
        $limit     = 200;
        $offset    = 0;

        $res = (isset($this->ia->phpbb) && method_exists($this->ia->phpbb, 'list_users'))
            ? $this->ia->phpbb->list_users($phpbb_cfg, $limit, $offset)
            : ['ok' => false, 'message' => 'phpBB helper missing.'];

        if (empty($res['ok'])) {
            $msg = sanitize_text_field((string)($res['message'] ?? 'Scan failed.'));
            update_option(self::OPT_LAST_SCAN, [
                'ts' => time(),
                'ok' => 0,
                'message' => $msg,
                'created' => 0,
                'seen' => 0,
                'sample' => [],
            ], false);
            wp_safe_redirect(admin_url('admin.php?page=ia-auth&tab=migration&scanned=0&err=1'));
            exit;
        }

        $rows = (array)($res['rows'] ?? []);
        $seen = count($rows);
        $created = 0;

        // Create/ensure WP shadow users for phpBB accounts (no identity-map writes here).
        // IMPORTANT: match-first semantics.
        // If a matching WP user already exists (email/username), we LINK it (by writing ia_phpbb_user_id meta)
        // and we DO NOT create a duplicate WP user.
        // Only truly missing users are created.
        $opt = $this->opts();

        foreach ($rows as $r) {
            $phpbb_uid = (int)($r['user_id'] ?? 0);
            if ($phpbb_uid <= 0) continue;

            // Skip bots.
            $user_type = (int)($r['user_type'] ?? 0);
            if ($user_type === 2) continue;

            if (!isset($this->ia->db) || !method_exists($this->ia->db, 'ensure_wp_shadow_user')) {
                continue;
            }

            $was_created = null;
            $wp_id = (int)$this->ia->db->ensure_wp_shadow_user((array)$r, (array)$opt, $was_created);
            if ($wp_id > 0 && $was_created === true) {
                $created++;
            }
        }

        // Store a short preview in options for the UI.
        $sample = array_slice($rows, 0, 50);
        update_option(self::OPT_LAST_SCAN, [
            'ts' => time(),
            'ok' => 1,
            'message' => 'Scan complete.',
            'created' => $created,
            'seen' => $seen,
            'sample' => $sample,
        ], false);

        if (isset($this->ia->db) && method_exists($this->ia->db, 'audit')) {
            $this->ia->db->audit('migration_scan', null, [
                'seen' => $seen,
                'created_shadow_users' => $created,
            ]);
        }

        wp_safe_redirect(admin_url('admin.php?page=ia-auth&tab=migration&scanned=1&created=' . (int)$created));
        exit;
    }

    public function migration_apply() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ia_auth_migration_apply');

        $dry = !empty($_POST['dry_run']);

        $cfg = [];
        if (class_exists('IA_Auth') && method_exists('IA_Auth', 'engine_normalized')) {
            $cfg = IA_Auth::engine_normalized();
        }
        $phpbb_cfg = (array)($cfg['phpbb'] ?? []);

        $limit  = 200;
        $offset = 0;

        $res = (isset($this->ia->phpbb) && method_exists($this->ia->phpbb, 'list_users'))
            ? $this->ia->phpbb->list_users($phpbb_cfg, $limit, $offset)
            : ['ok' => false, 'message' => 'phpBB helper missing.'];

        if (empty($res['ok'])) {
            $msg = sanitize_text_field((string)($res['message'] ?? 'Apply failed.'));
            update_option(self::OPT_LAST_SCAN, [
                'ts' => time(),
                'ok' => 0,
                'message' => $msg,
                'created' => 0,
                'seen' => 0,
                'sample' => [],
                'applied' => 0,
                'dry' => $dry ? 1 : 0,
            ], false);
            wp_safe_redirect(admin_url('admin.php?page=ia-auth&tab=migration&applied=0&err=1'));
            exit;
        }

        if (!isset($this->ia->db) || !method_exists($this->ia->db, 'upsert_identity')) {
            update_option(self::OPT_LAST_SCAN, [
                'ts' => time(),
                'ok' => 0,
                'message' => 'DB helper missing.',
                'created' => 0,
                'seen' => 0,
                'sample' => [],
                'applied' => 0,
                'dry' => $dry ? 1 : 0,
            ], false);
            wp_safe_redirect(admin_url('admin.php?page=ia-auth&tab=migration&applied=0&err=1'));
            exit;
        }

        $rows = (array)($res['rows'] ?? []);
        $seen = count($rows);
        $applied = 0;

        $opt = $this->opts();

        foreach ($rows as $r) {
            $phpbb_uid = (int)($r['user_id'] ?? 0);
            if ($phpbb_uid <= 0) continue;

            $user_type = (int)($r['user_type'] ?? 0);
            if ($user_type === 2) continue; // bots

            $wp_user_id = 0;
            if (method_exists($this->ia->db, 'ensure_wp_shadow_user')) {
                $wp_user_id = (int)$this->ia->db->ensure_wp_shadow_user($r, $opt);
            }

            if ($dry) {
                $applied++;
                continue;
            }

            $ok = $this->ia->db->upsert_identity([
                'phpbb_user_id'        => $phpbb_uid,
                'phpbb_username_clean' => (string)($r['username_clean'] ?? ''),
                'email'                => (string)($r['user_email'] ?? ''),
                'wp_user_id'           => $wp_user_id > 0 ? $wp_user_id : null,
                'status'               => $wp_user_id > 0 ? 'linked' : 'partial',
                'last_error'           => '',
            ]);

            if ($ok) $applied++;
        }

        // Update last scan summary (keep the previous sample if present)
        $prev = get_option(self::OPT_LAST_SCAN, []);
        if (!is_array($prev)) $prev = [];

        $prev['ts'] = time();
        $prev['ok'] = 1;
        $prev['message'] = $dry ? 'Dry-run complete (no data written).' : 'Apply complete.';
        $prev['seen'] = $seen;
        $prev['applied'] = $applied;
        $prev['dry'] = $dry ? 1 : 0;
        update_option(self::OPT_LAST_SCAN, $prev, false);

        if (isset($this->ia->db) && method_exists($this->ia->db, 'audit')) {
            $this->ia->db->audit('migration_apply', null, [
                'seen' => $seen,
                'applied' => $applied,
                'dry_run' => $dry ? 1 : 0,
            ]);
        }

        wp_safe_redirect(admin_url('admin.php?page=ia-auth&tab=migration&applied=1&dry=' . ($dry ? '1' : '0') . '&count=' . (int)$applied));
        exit;
    }

    /**
     * Cleanup legacy shadow users created by the earlier bootstrap implementation.
     *
     * Targets ONLY users whose login begins with "phpbb_" AND who carry ia_shadow_user=1.
     * This avoids touching real/handmade WP users.
     */
    public function cleanup_legacy_shadow_users() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ia_auth_cleanup_legacy_shadow_users');

        require_once ABSPATH . 'wp-admin/includes/user.php';

        $q = new WP_User_Query([
            'number' => 5000,
            'fields' => 'ids',
            'meta_key' => 'ia_shadow_user',
            'meta_value' => '1',
        ]);

        $ids = (array)$q->get_results();
        $deleted = 0;

        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;
            $u = get_user_by('id', $id);
            if (!$u || !$u->exists()) continue;
            if (strpos($u->user_login, 'phpbb_') !== 0) continue;
            // Extra guard: never delete the current user.
            if (get_current_user_id() === $id) continue;
            wp_delete_user($id);
            $deleted++;
        }

        // Clear last scan preview so the UI doesn't point at removed accounts.
        delete_option(self::OPT_LAST_SCAN);

        if (isset($this->ia->db) && method_exists($this->ia->db, 'audit')) {
            $this->ia->db->audit('cleanup_legacy_shadow_users', null, [
                'deleted' => $deleted,
            ]);
        }

        wp_safe_redirect(admin_url('admin.php?page=ia-auth&tab=migration&cleaned=1&deleted=' . (int)$deleted));
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

        if (empty($candidates)) {
            echo '<div class="notice notice-warning inline" style="padding:12px 14px;margin:12px 0;">';
            echo '<strong>No phpBB-linked shadow users found yet.</strong> ';
            echo 'Run <a href="' . esc_url(admin_url('admin.php?page=ia-auth&tab=migration')) . '">Migration tools → Scan &amp; Preview</a> first to pull phpBB users and create shadow users. ';
            echo 'Once scanned, come back here to select the admin.';
            echo '</div>';
        }

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

        echo '<h4>PeerTube API credentials (optional)</h4>';
        echo '<p style="max-width:760px;">If you want IA Auth to make privileged PeerTube API calls (for example creating/syncing accounts), paste your OAuth client credentials and/or an API access token here. These are stored in <code>wp_options</code> (not encrypted), so only use this on a trusted server.</p>';

        echo '<p><label>OAuth client id</label><br>';
        echo '<input type="text" name="peertube_client_id" style="width:420px" value="' . esc_attr((string)($opt['peertube_client_id'] ?? '')) . '" placeholder="e.g. lfq72p6fbgsvxo9bsx3h6srolvhhtp7b"></p>';

        echo '<p><label>OAuth client secret</label><br>';
        echo '<input type="text" name="peertube_client_secret" style="width:420px" value="' . esc_attr((string)($opt['peertube_client_secret'] ?? '')) . '" placeholder="e.g. Wxo4tyJiegJKXIcLsYOBMVBbp0IQSDPk"></p>';

        echo '<p><label>API access token (Bearer)</label><br>';
        echo '<input type="text" name="peertube_api_token" style="width:760px" value="' . esc_attr((string)($opt['peertube_api_token'] ?? '')) . '" placeholder="e.g. 8574..."></p>';

        echo '<p><label>API refresh token (optional)</label><br>';
        echo '<input type="text" name="peertube_refresh_token" style="width:760px" value="' . esc_attr((string)($opt['peertube_refresh_token'] ?? '')) . '" placeholder="e.g. 1d29..."></p>';

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

        $last = get_option(self::OPT_LAST_SCAN, []);
        if (is_array($last) && !empty($last)) {
            $ts = !empty($last['ts']) ? (int)$last['ts'] : 0;
            $when = $ts ? date_i18n('Y-m-d H:i:s', $ts) : '';
            $ok = !empty($last['ok']);
            $msg = (string)($last['message'] ?? '');
            echo '<div class="notice ' . ($ok ? 'notice-success' : 'notice-error') . '" style="padding:10px 12px;margin-top:12px;">';
            echo '<p style="margin:0"><strong>Last run:</strong> ' . esc_html($when) . ' — ' . esc_html($msg) . '</p>';
            if (isset($last['seen']))   echo '<p style="margin:6px 0 0 0">phpBB users seen: <strong>' . (int)$last['seen'] . '</strong></p>';
            if (isset($last['created'])) echo '<p style="margin:6px 0 0 0">WP shadow users created: <strong>' . (int)$last['created'] . '</strong></p>';
            if (isset($last['applied'])) echo '<p style="margin:6px 0 0 0">Identity rows ' . (!empty($last['dry']) ? 'would be written (dry run)' : 'written') . ': <strong>' . (int)$last['applied'] . '</strong></p>';
            echo '</div>';

            if (!empty($last['sample']) && is_array($last['sample'])) {
                echo '<h3 style="margin-top:18px;">Preview sample (first ' . count($last['sample']) . ')</h3>';
                echo '<table class="widefat striped" style="max-width:1100px">';
                echo '<thead><tr><th>phpBB ID</th><th>Username</th><th>Email</th><th>Shadow WP user</th></tr></thead><tbody>';
                foreach ($last['sample'] as $r) {
                    $pid = (int)($r['user_id'] ?? 0);
                    $uname = (string)($r['username'] ?? '');
                    $email = (string)($r['user_email'] ?? '');
                    $shadow = '';
                    if ($pid > 0) {
                        $ids = get_users([
                            'number' => 1,
                            'fields' => 'ids',
                            'meta_key' => 'ia_phpbb_user_id',
                            'meta_value' => $pid,
                        ]);
                        if (!empty($ids)) {
                            $u = get_user_by('id', (int)$ids[0]);
                            if ($u && $u->exists()) $shadow = $u->user_login . ' (WP ' . (int)$u->ID . ')';
                        }
                    }

                    echo '<tr>';
                    echo '<td>' . (int)$pid . '</td>';
                    echo '<td>' . esc_html($uname) . '</td>';
                    echo '<td>' . esc_html($email) . '</td>';
                    echo '<td>' . esc_html($shadow) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:10px;">';
        echo '<input type="hidden" name="action" value="ia_auth_migration_scan">';
        wp_nonce_field('ia_auth_migration_scan');
        echo '<button class="button button-primary" type="submit">Scan &amp; Preview</button>';
        echo '</form>';

        // Legacy cleanup (only shows if there are users that match the legacy prefix)
        $legacy = new WP_User_Query([
            'number' => 1,
            'fields' => 'ids',
            'meta_key' => 'ia_shadow_user',
            'meta_value' => '1',
            'search' => 'phpbb_',
            'search_columns' => ['user_login'],
        ]);
        if (!empty((array)$legacy->get_results())) {
            echo '<hr style="margin:18px 0;">';
            echo '<h3>Cleanup legacy shadow users</h3>';
            echo '<p style="max-width:900px;color:#555">If you previously ran a bootstrap build that created WP users with a <code>phpbb_*</code> prefix, you can remove them safely here. This does not touch real WP accounts.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'Delete legacy phpbb_* shadow users? This cannot be undone.\');">';
            echo '<input type="hidden" name="action" value="ia_auth_cleanup_legacy_shadow_users">';
            wp_nonce_field('ia_auth_cleanup_legacy_shadow_users');
            echo '<button class="button" type="submit">Delete legacy phpbb_* shadow users</button>';
            echo '</form>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:10px;">';
        echo '<input type="hidden" name="action" value="ia_auth_migration_apply">';
        wp_nonce_field('ia_auth_migration_apply');
        echo '<label style="margin-right:10px;"><input type="checkbox" name="dry_run" value="1" checked> Dry run only</label>';
        echo '<button class="button button-primary" type="submit">Apply in batches</button>';
        echo '</form>';

        echo '<p style="margin-top:14px;color:#555;max-width:900px">';
        echo 'Bootstrap note: you can run <strong>Scan &amp; Preview</strong> before defining the IA Auth admin. '; 
        echo 'Scan creates WP shadow users for phpBB accounts so the <em>Define Admin</em> dropdown can be populated.';
        echo '</p>';
    }

    private function render_security() {
        echo '<h2>Security</h2>';
        echo '<p>No audit events yet.</p>';
    }

    private function render_logs() {
        echo '<h2>Logs</h2>';
        echo '<p>No logs yet.</p>';
    }

    public function render_users() {
        if (!current_user_can('manage_options')) return;

        global $wpdb;

        // Handle actions
        if (!empty($_POST['ia_auth_action']) && check_admin_referer('ia_auth_users_action', 'ia_auth_nonce')) {
            $action = sanitize_text_field((string)$_POST['ia_auth_action']);
            $phpbb_user_id = (int)($_POST['phpbb_user_id'] ?? 0);

            $identity = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ia_identity_map WHERE phpbb_user_id=%d",
                $phpbb_user_id
            ), ARRAY_A);

            if ($identity) {
                $wp_user_id = (int)($identity['wp_user_id'] ?? 0);
                $email = (string)($identity['email'] ?? '');

                if ($action === 'resend') {
                    $user = $wp_user_id ? get_userdata($wp_user_id) : null;
                    $username = $user ? $user->user_login : '';
                    $pw_enc = $wp_user_id ? (string)get_user_meta($wp_user_id, 'ia_pw_enc_pending', true) : '';
                    if ($username && $email && $pw_enc) {
                        $token = bin2hex(random_bytes(16));
                        $payload = wp_json_encode([
                            'token'    => $token,
                            'username' => $username,
                            'email'    => $email,
                            'pw_enc'   => $pw_enc,
                            'created'  => time(),
                        ]);
                        $this->ia->db->create_email_verification_job($phpbb_user_id, $token, (string)$payload);
                        $verify_url = home_url('/ia-verify/' . $token);
                        $subject = apply_filters('ia_auth_verify_email_subject', 'Activate your IndieAgora account');
                        $body    = apply_filters('ia_auth_verify_email_body',
                            "Hi {$username},\n\nPlease activate your account by clicking the link below:\n\n{$verify_url}\n",
                            $username, $verify_url
                        );
                        $sent = wp_mail($email, $subject, $body);
                        if (!$sent) {
                            error_log('[IA Auth] wp_mail returned false when resending verification email to ' . $email);
                            echo '<div class="notice notice-error"><p>Could not send email (wp_mail failed). Check your SMTP / mail setup.</p></div>';
                        } else {
                            echo '<div class="notice notice-success"><p>Verification email resent.</p></div>';
                        }
                    } else {
                        echo '<div class="notice notice-error"><p>Could not resend (missing pending password or email).</p></div>';
                    }
                }

                if ($action === 'approve') {
                    // Manual approve = provision PeerTube now using pending password, then mark verified.
                    if ($wp_user_id) {
                        $user = get_userdata($wp_user_id);
                        $username = $user ? $user->user_login : '';
                        $pw_enc = (string)get_user_meta($wp_user_id, 'ia_pw_enc_pending', true);
                        $pw = $this->ia->crypto->decrypt($pw_enc);

                        if ($username && $email && $pw) {
                            $pt_cfg = class_exists('IA_Engine') ? IA_Engine::peertube_api() : [];
                            // PeerTube constraint: channelName must NOT be the same as username.
                            // Build a deterministic channel slug from the username.
                            $chan_base = strtolower((string)$username);
                            $chan_base = preg_replace('/[^a-z0-9_]+/', '_', $chan_base);
                            $chan_base = trim($chan_base, '_');
                            if ($chan_base === '') $chan_base = 'user';
                            $channel_name = substr($chan_base . '_channel', 0, 50);

                            $pt_create = $this->ia->peertube->admin_create_user($username, $email, $pw, $channel_name, $pt_cfg);

                            if (!empty($pt_create['ok'])) {
                                update_user_meta($wp_user_id, 'ia_email_verified', 1);
                                delete_user_meta($wp_user_id, 'ia_pw_enc_pending');

                                $this->ia->db->upsert_identity([
                                    'phpbb_user_id'        => $phpbb_user_id,
                                    'phpbb_username_clean' => (string)($identity['phpbb_username_clean'] ?? ''),
                                    'email'                => $email,
                                    'wp_user_id'           => $wp_user_id,
                                    'peertube_user_id'     => (int)$pt_create['peertube_user_id'],
                                    'peertube_account_id'  => (int)$pt_create['peertube_account_id'],
                                    'status'               => 'linked',
                                    'last_error'           => '',
                                ]);

                                echo '<div class="notice notice-success"><p>User approved and PeerTube account provisioned.</p></div>';
                            } else {
                                $msg = (string)($pt_create['message'] ?? '');
                                if ($msg === '') $msg = 'Unknown error';
                                error_log('[IA Auth] PeerTube provisioning failed: ' . $msg);
                                echo '<div class="notice notice-error"><p>PeerTube provisioning failed: ' . esc_html($msg) . '</p></div>';
                            }
                        } else {
                            echo '<div class="notice notice-error"><p>Could not approve (missing pending password).</p></div>';
                        }
                    }
                }
            }
        }

        $rows = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ia_identity_map WHERE status='pending_email' ORDER BY created_at DESC LIMIT 500",
            ARRAY_A
        );

        echo '<div class="wrap"><h1>IA Users — Pending Email Verification</h1>';
        echo '<p>These accounts exist in phpBB/WP but cannot log in until verified.</p>';

        if (empty($rows)) {
            echo '<p><em>No pending users.</em></p></div>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>phpBB ID</th><th>WP User</th><th>Email</th><th>Created</th><th>Actions</th>';
        echo '</tr></thead><tbody>';

        foreach ($rows as $r) {
            $phpbb_id = (int)$r['phpbb_user_id'];
            $wp_id = (int)($r['wp_user_id'] ?? 0);
            $email = esc_html((string)($r['email'] ?? ''));
            $created = esc_html((string)($r['created_at'] ?? ''));
            $wp_label = $wp_id ? ('#' . $wp_id . ' ' . esc_html((string)(get_userdata($wp_id)->user_login ?? ''))) : '—';

            echo '<tr>';
            echo '<td>' . $phpbb_id . '</td>';
            echo '<td>' . $wp_label . '</td>';
            echo '<td>' . $email . '</td>';
            echo '<td>' . $created . '</td>';
            echo '<td>';
            echo '<form method="post" style="display:inline-block;margin-right:6px;">';
            wp_nonce_field('ia_auth_users_action', 'ia_auth_nonce');
            echo '<input type="hidden" name="phpbb_user_id" value="' . esc_attr((string)$phpbb_id) . '">';
            echo '<input type="hidden" name="ia_auth_action" value="resend">';
            echo '<button class="button">Resend email</button>';
            echo '</form>';

            echo '<form method="post" style="display:inline-block;">';
            wp_nonce_field('ia_auth_users_action', 'ia_auth_nonce');
            echo '<input type="hidden" name="phpbb_user_id" value="' . esc_attr((string)$phpbb_id) . '">';
            echo '<input type="hidden" name="ia_auth_action" value="approve">';
            echo '<button class="button button-primary">Approve</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

}
