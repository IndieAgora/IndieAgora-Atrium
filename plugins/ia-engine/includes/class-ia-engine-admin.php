<?php
if (!defined('ABSPATH')) exit;

final class IA_Engine_Admin {

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_init', [__CLASS__, 'handle_save']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function admin_menu(): void {
        add_menu_page(
            'IA Engine',
            'IA Engine',
            'manage_options',
            'ia-engine',
            [__CLASS__, 'render'],
            'dashicons-admin-generic',
            58
        );
    }

    public static function enqueue_assets(string $hook): void {
        if ($hook !== 'toplevel_page_ia-engine') return;

        wp_enqueue_style('ia-engine-admin', IA_ENGINE_URL . 'assets/ia-engine-admin.css', [], IA_ENGINE_VERSION);
        wp_enqueue_script('ia-engine-admin', IA_ENGINE_URL . 'assets/ia-engine-admin.js', ['jquery'], IA_ENGINE_VERSION, true);

        wp_localize_script('ia-engine-admin', 'IAEngine', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ia_engine_nonce'),
        ]);
    }

    public static function handle_save(): void {
        if (!is_admin()) return;
        if (!current_user_can('manage_options')) return;
        if (!isset($_POST['ia_engine_save'])) return;

        check_admin_referer('ia_engine_save_action', 'ia_engine_save_nonce');

        // ---- phpBB (MySQL/MariaDB) ----
        $phpbb = [
            'host'   => sanitize_text_field($_POST['phpbb_host'] ?? ''),
            'port'   => (int)($_POST['phpbb_port'] ?? 3306),
            'name'   => sanitize_text_field($_POST['phpbb_name'] ?? ''),
            'user'   => sanitize_text_field($_POST['phpbb_user'] ?? ''),
            'password' => (string)($_POST['phpbb_pass'] ?? ''), // blank => keep existing
            'prefix' => sanitize_text_field($_POST['phpbb_prefix'] ?? 'phpbb_'),
        ];

        // ---- PeerTube DB (Postgres) ----
        $peertube = [
            'host'     => sanitize_text_field($_POST['pt_db_host'] ?? ''),
            'port'     => (int)($_POST['pt_db_port'] ?? 5432),
            'name'     => sanitize_text_field($_POST['pt_db_name'] ?? ''),
            'user'     => sanitize_text_field($_POST['pt_db_user'] ?? ''),
            'password' => (string)($_POST['pt_db_pass'] ?? ''), // blank => keep existing
        ];

        // ---- PeerTube API (Internal + Public) ----
        $scheme = sanitize_text_field($_POST['pt_api_scheme'] ?? 'http');
        $scheme = in_array($scheme, ['http', 'https'], true) ? $scheme : 'http';

        $basePath = sanitize_text_field($_POST['pt_api_base_path'] ?? '');
        $basePath = trim($basePath);
        $basePath = $basePath !== '' ? ltrim($basePath, '/') : '';

        // Keep non-secret settings in the main settings store.
        $existing_ptapi = IA_Engine::peertube_api();

        $oauth_client_id = sanitize_text_field($_POST['pt_oauth_client_id'] ?? '');
        if ($oauth_client_id === '') $oauth_client_id = (string)($existing_ptapi['oauth_client_id'] ?? '');

        $admin_username = sanitize_text_field($_POST['pt_admin_username'] ?? '');
        if ($admin_username === '' && empty($_POST['pt_admin_username_clear'])) {
            $admin_username = (string)($existing_ptapi['admin_username'] ?? '');
        }
        if (!empty($_POST['pt_admin_username_clear'])) {
            $admin_username = '';
        }

        $peertube_api = [
            'scheme'     => $scheme,
            'host'       => sanitize_text_field($_POST['pt_api_host'] ?? '127.0.0.1'),
            'port'       => (int)($_POST['pt_api_port'] ?? 9000),
            'base_path'  => $basePath,
            'public_url' => esc_url_raw($_POST['pt_public_url'] ?? ''),
            // Optional OAuth client id (secret stored separately)
            'oauth_client_id' => $oauth_client_id,
            // Optional admin username (password secret stored separately)
            'admin_username'  => $admin_username,
        ];

        IA_Engine::set('phpbb', $phpbb);
        IA_Engine::set('peertube', $peertube);
        IA_Engine::set('peertube_api', $peertube_api);

        // Store secrets encrypted (kept if blank).
        // Token / access token
        if (!empty($_POST['pt_api_token_clear'])) {
            IA_Engine::set('peertube_api.token', '__CLEAR__', true);
        } else {
            IA_Engine::set('peertube_api.token', (string)($_POST['pt_api_token'] ?? ''), true);
        }

        // OAuth client secret
        if (!empty($_POST['pt_oauth_client_secret_clear'])) {
            IA_Engine::set('peertube_api.oauth_client_secret', '__CLEAR__', true);
        } else {
            IA_Engine::set('peertube_api.oauth_client_secret', (string)($_POST['pt_oauth_client_secret'] ?? ''), true);
        }

        // Admin password
        if (!empty($_POST['pt_admin_password_clear'])) {
            IA_Engine::set('peertube_api.admin_password', '__CLEAR__', true);
        } else {
            IA_Engine::set('peertube_api.admin_password', (string)($_POST['pt_admin_password'] ?? ''), true);
        }

        // Clear minted tokens if requested
        if (!empty($_POST['pt_admin_access_token_clear']))  IA_Engine::set('peertube_api.admin_access_token', '__CLEAR__', true);
        if (!empty($_POST['pt_admin_refresh_token_clear'])) IA_Engine::set('peertube_api.admin_refresh_token', '__CLEAR__', true);

        wp_safe_redirect(admin_url('admin.php?page=ia-engine&saved=1'));
        exit;
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) return;

        $saved = isset($_GET['saved']) && $_GET['saved'] === '1';

        $phpbb_safe = IA_Engine::get_safe('phpbb');
        $ptdb_safe  = IA_Engine::get_safe('peertube');
        $ptapi_safe = IA_Engine::get_safe('peertube_api');

        // For non-secret fields we can show actual stored values:
        $phpbb_raw = IA_Engine::get_all()['phpbb'] ?? [];
        if (!is_array($phpbb_raw)) $phpbb_raw = [];

        $ptdb_raw = IA_Engine::get_all()['peertube'] ?? [];
        if (!is_array($ptdb_raw)) $ptdb_raw = [];

        $ptapi_raw = IA_Engine::get_all()['peertube_api'] ?? [];
        if (!is_array($ptapi_raw)) $ptapi_raw = [];

        ?>
        <div class="wrap ia-engine-wrap">
            <h1>IA Engine</h1>
            <p class="description">Central config + services for IndieAgora Atrium micro-plugins. Passwords/tokens are stored encrypted.</p>

            <?php if ($saved): ?>
                <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('ia_engine_save_action', 'ia_engine_save_nonce'); ?>

                <div class="ia-engine-card">
                    <h2>phpBB (MySQL/MariaDB)</h2>

                    <div class="ia-engine-grid">
                        <label>Host</label>
                        <input type="text" name="phpbb_host" value="<?php echo esc_attr($phpbb_raw['host'] ?? 'localhost'); ?>"/>

                        <label>Port</label>
                        <input type="number" name="phpbb_port" value="<?php echo esc_attr((string)($phpbb_raw['port'] ?? 3306)); ?>"/>

                        <label>Database</label>
                        <input type="text" name="phpbb_name" value="<?php echo esc_attr($phpbb_raw['name'] ?? ''); ?>"/>

                        <label>User</label>
                        <input type="text" name="phpbb_user" value="<?php echo esc_attr($phpbb_raw['user'] ?? ''); ?>"/>

                        <label>Password</label>
                        <div class="ia-engine-secret">
                            <input type="password" name="phpbb_pass" placeholder="Leave blank to keep existing"/>
                            <div class="ia-engine-secret-meta">Stored encrypted. Existing: <strong><?php echo esc_html($phpbb_safe['password'] ?? 'not set'); ?></strong></div>
                        </div>

                        <label>Table prefix</label>
                        <input type="text" name="phpbb_prefix" value="<?php echo esc_attr($phpbb_raw['prefix'] ?? 'phpbb_'); ?>"/>
                    </div>

                    <div class="ia-engine-tests">
                        <span class="ia-engine-test-label">Connection test</span>
                        <button type="button" class="button ia-engine-test" data-action="ia_engine_test_phpbb">Test phpBB DB</button>
                        <span class="ia-engine-test-result" data-result-for="ia_engine_test_phpbb"></span>
                    </div>
                </div>

                <div class="ia-engine-card">
                    <h2>PeerTube DB (Postgres)</h2>

                    <div class="ia-engine-grid">
                        <label>Host</label>
                        <input type="text" name="pt_db_host" value="<?php echo esc_attr($ptdb_raw['host'] ?? '127.0.0.1'); ?>"/>

                        <label>Port</label>
                        <input type="number" name="pt_db_port" value="<?php echo esc_attr((string)($ptdb_raw['port'] ?? 5432)); ?>"/>

                        <label>Database</label>
                        <input type="text" name="pt_db_name" value="<?php echo esc_attr($ptdb_raw['name'] ?? ''); ?>"/>

                        <label>User</label>
                        <input type="text" name="pt_db_user" value="<?php echo esc_attr($ptdb_raw['user'] ?? ''); ?>"/>

                        <label>Password</label>
                        <div class="ia-engine-secret">
                            <input type="password" name="pt_db_pass" placeholder="Leave blank to keep existing"/>
                            <div class="ia-engine-secret-meta">Stored encrypted. Existing: <strong><?php echo esc_html($ptdb_safe['password'] ?? 'not set'); ?></strong></div>
                        </div>
                    </div>

                    <div class="ia-engine-tests">
                        <span class="ia-engine-test-label">Connection test</span>
                        <button type="button" class="button ia-engine-test" data-action="ia_engine_test_peertube_db">Test PeerTube DB</button>
                        <span class="ia-engine-test-result" data-result-for="ia_engine_test_peertube_db"></span>
                    </div>
                </div>

                <div class="ia-engine-card">
                    <h2>PeerTube API</h2>
                    <p class="description">Used by IA Stream and any plugin that needs to fetch videos/channels via HTTP. Prefer internal access (127.0.0.1) for server-side calls.</p>

                    <div class="ia-engine-grid">
                        <label>Scheme</label>
                        <select name="pt_api_scheme">
                            <?php $curScheme = $ptapi_raw['scheme'] ?? 'http'; ?>
                            <option value="http" <?php selected($curScheme, 'http'); ?>>http</option>
                            <option value="https" <?php selected($curScheme, 'https'); ?>>https</option>
                        </select>

                        <label>Internal host</label>
                        <input type="text" name="pt_api_host" value="<?php echo esc_attr($ptapi_raw['host'] ?? '127.0.0.1'); ?>"/>

                        <label>Internal port</label>
                        <input type="number" name="pt_api_port" value="<?php echo esc_attr((string)($ptapi_raw['port'] ?? 9000)); ?>"/>

                        <label>Base path (optional)</label>
                        <input type="text" name="pt_api_base_path" value="<?php echo esc_attr($ptapi_raw['base_path'] ?? ''); ?>" placeholder="e.g. peertube (leave blank if none)"/>

                        <label>Public URL</label>
                        <input type="text" name="pt_public_url" value="<?php echo esc_attr($ptapi_raw['public_url'] ?? 'https://stream.indieagora.com'); ?>"/>

                        <label>API token / access token (optional)</label>
                        <div class="ia-engine-secret">
                            <input type="password" name="pt_api_token" placeholder="Paste a token, or leave blank to keep existing"/>
                            <label class="ia-engine-inline">
                              <input type="checkbox" name="pt_api_token_clear" value="1" /> Clear stored token
                            </label>
                            <div class="ia-engine-secret-meta">Stored encrypted. Existing: <strong><?php echo esc_html($ptapi_safe['token'] ?? 'not set'); ?></strong></div>
                        </div>

                        <div class="ia-engine-grid-sep"></div>

                        <label>OAuth client id (optional)</label>
                        <input type="text" name="pt_oauth_client_id" value="<?php echo esc_attr($ptapi_raw['oauth_client_id'] ?? ''); ?>" placeholder="Leave blank to auto-discover"/>

                        <label>OAuth client secret (optional)</label>
                        <div class="ia-engine-secret">
                            <input type="password" name="pt_oauth_client_secret" placeholder="Leave blank to keep existing"/>
                            <label class="ia-engine-inline">
                              <input type="checkbox" name="pt_oauth_client_secret_clear" value="1" /> Clear stored secret
                            </label>
                            <div class="ia-engine-secret-meta">Stored encrypted. Existing: <strong><?php echo esc_html($ptapi_safe['oauth_client_secret'] ?? 'not set'); ?></strong></div>
                        </div>

                        <label>PeerTube admin username (for token refresh)</label>
                        <div class="ia-engine-secret">
                            <input type="text" name="pt_admin_username" value="<?php echo esc_attr($ptapi_raw['admin_username'] ?? ''); ?>" placeholder="e.g. root"/>
                            <label class="ia-engine-inline">
                              <input type="checkbox" name="pt_admin_username_clear" value="1" /> Clear stored username
                            </label>
                        </div>

                        <label>PeerTube admin password (for token refresh)</label>
                        <div class="ia-engine-secret">
                            <input type="password" name="pt_admin_password" placeholder="Leave blank to keep existing"/>
                            <label class="ia-engine-inline">
                              <input type="checkbox" name="pt_admin_password_clear" value="1" /> Clear stored password
                            </label>
                            <div class="ia-engine-secret-meta">Stored encrypted. Existing: <strong><?php echo esc_html($ptapi_safe['admin_password'] ?? 'not set'); ?></strong></div>
                        </div>

                        <div class="ia-engine-pt-token-status">
                            <strong>Admin access token:</strong>
                            <span><?php echo esc_html(!empty($ptapi_safe['admin_access_token']) ? $ptapi_safe['admin_access_token'] : 'not set'); ?></span>
                        </div>
                        <div class="ia-engine-pt-token-status">
                            <strong>Admin refresh token:</strong>
                            <span><?php echo esc_html(!empty($ptapi_safe['admin_refresh_token']) ? $ptapi_safe['admin_refresh_token'] : 'not set'); ?></span>
                        </div>

                        <button id="ia-engine-pt-refresh-btn" type="button" class="button ia-engine-pt-refresh" data-action="ia_engine_pt_refresh_now">Refresh admin token now</button>
                        <span class="ia-engine-pt-refresh-result" aria-live="polite"></span>
                    </div>

                    <div class="ia-engine-tests">
                        <span class="ia-engine-test-label">Connection test</span>
                        <button type="button" class="button ia-engine-test" data-action="ia_engine_test_peertube_api">Test PeerTube API</button>
                        <span class="ia-engine-test-result" data-result-for="ia_engine_test_peertube_api"></span>
                    </div>

                    <div class="ia-engine-hint">
                        <strong>Internal base URL:</strong>
                        <code><?php echo esc_html(IA_Engine::peertube_internal_base_url()); ?></code>
                        <br/>
                        <strong>Public base URL:</strong>
                        <code><?php echo esc_html(IA_Engine::peertube_public_base_url()); ?></code>
                    </div>
                </div>

                <p>
                    <button class="button button-primary" type="submit" name="ia_engine_save" value="1">Save settings</button>
                </p>
            </form>
        </div>
        <?php
    }
}
