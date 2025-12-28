<?php
if (!defined('ABSPATH')) exit;

final class IA_User {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action('wp_ajax_ia_user_login', [$this, 'ajax_login']);
        add_action('wp_ajax_nopriv_ia_user_login', [$this, 'ajax_login']);

        add_action('wp_ajax_ia_user_register', [$this, 'ajax_register']);
        add_action('wp_ajax_nopriv_ia_user_register', [$this, 'ajax_register']);

        add_action('wp_ajax_ia_user_logout', [$this, 'ajax_logout']);
        add_action('wp_ajax_nopriv_ia_user_logout', [$this, 'ajax_logout']);
    }

  public function enqueue_assets() {
    if (is_admin()) return;

    // We are already inside wp_enqueue_scripts.
    // Adding a later 'wp' hook here is too late (it will never fire on this request).
    // So: detect Atrium right now and enqueue immediately.
    global $post;

    $is_atrium = false;

    if ($post && isset($post->post_content) && has_shortcode($post->post_content, 'ia-atrium')) {
        $is_atrium = true;
    }

    /**
     * Fallback: if for some reason $post isn't available (edge cases),
     * allow themes/plugins to force-load IA User assets.
     */
    $is_atrium = (bool) apply_filters('ia_user_should_enqueue', $is_atrium);

    if (!$is_atrium) return;

    wp_enqueue_style('ia-user', IA_USER_URL . 'assets/css/ia-user.css', [], IA_USER_VERSION);

    // Vanilla JS (no jQuery dependency)
    wp_enqueue_script('ia-user', IA_USER_URL . 'assets/js/ia-user.js', [], IA_USER_VERSION, true);

    wp_localize_script('ia-user', 'IA_USER', [
        'ajax'       => admin_url('admin-ajax.php'),
        'nonce'      => wp_create_nonce('ia_user_nonce'),
        'home'       => home_url('/'),
        'has_auth'   => class_exists('IA_Auth'),
        'has_engine' => class_exists('IA_Engine'),
    ]);
}

    private function guard_nonce(): void {
        $nonce = (string)($_POST['nonce'] ?? '');
        if ($nonce === '') $nonce = (string)($_POST['_wpnonce'] ?? '');

        if ($nonce === '' || !wp_verify_nonce($nonce, 'ia_user_nonce')) {
            wp_send_json_error(['message' => 'Bad nonce. Refresh and try again.'], 403);
        }
    }

    private function phpbb_cfg(): array {
        // Prefer ia-engine as the source of truth for credentials.
        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'phpbb_db')) {
            $p = IA_Engine::phpbb_db();
            return [
                'host'   => (string)($p['host'] ?? ''),
                'port'   => (int)($p['port'] ?? 3306),
                'name'   => (string)($p['name'] ?? ''),
                'user'   => (string)($p['user'] ?? ''),
                'pass'   => (string)($p['pass'] ?? ''),
                'prefix' => (string)($p['prefix'] ?? 'phpbb_'),
            ];
        }

        /**
         * Fallback: allow providing phpBB DB details via a filter.
         * Return format:
         * [
         *   'host'=>'127.0.0.1','port'=>3306,'name'=>'db','user'=>'u','pass'=>'p','prefix'=>'phpbb_'
         * ]
         */
        $cfg = apply_filters('ia_user_phpbb_cfg', []);
        if (!is_array($cfg)) $cfg = [];
        return array_merge([
            'host' => '',
            'port' => 3306,
            'name' => '',
            'user' => '',
            'pass' => '',
            'prefix' => 'phpbb_',
        ], $cfg);
    }

    private function peertube_api_cfg(): array {
        if (class_exists('IA_Engine')) {
            return [
                'internal_base_url' => (string) IA_Engine::peertube_internal_base_url(),
                'public_base_url'   => (string) IA_Engine::peertube_public_base_url(),
                'token'             => (string) IA_Engine::peertube_api_token(),
            ];
        }
        return [
            'internal_base_url' => '',
            'public_base_url'   => '',
            'token'             => '',
        ];
    }

    private function ensure_wp_shadow_user(array $phpbb_user): int {
        // If ia-auth is active, use its battle-tested shadow user builder + identity storage.
        if (class_exists('IA_Auth')) {
            $auth = IA_Auth::instance();
            $wp_user_id = (int)$auth->db->ensure_wp_shadow_user($phpbb_user, $auth->options());

            if ($wp_user_id > 0) {
                $auth->db->upsert_identity([
                    'phpbb_user_id'        => (int)($phpbb_user['user_id'] ?? 0),
                    'phpbb_username_clean' => (string)($phpbb_user['username_clean'] ?? ''),
                    'email'                => (string)($phpbb_user['user_email'] ?? ''),
                    'wp_user_id'           => $wp_user_id,
                    'status'               => 'linked',
                    'last_error'           => '',
                ]);
            }

            return $wp_user_id;
        }

        // Minimal fallback: create or find a WP user by meta ia_phpbb_user_id.
        $phpbb_id = (int)($phpbb_user['user_id'] ?? 0);
        if ($phpbb_id <= 0) return 0;

        $existing = get_users([
            'meta_key'   => 'ia_phpbb_user_id',
            'meta_value' => (string)$phpbb_id,
            'fields'     => 'ID',
            'number'     => 1,
        ]);
        if (!empty($existing) && is_array($existing)) {
            return (int)$existing[0];
        }

        $login = (string)($phpbb_user['username_clean'] ?? '');
        if ($login === '') $login = (string)($phpbb_user['username'] ?? '');
        if ($login === '') $login = 'phpbb_' . $phpbb_id;

        $email = (string)($phpbb_user['user_email'] ?? '');
        if ($email === '') $email = 'phpbb_' . $phpbb_id . '@example.invalid';

        // Ensure unique user_login
        $base = sanitize_user($login, true);
        if ($base === '') $base = 'phpbb_' . $phpbb_id;
        $try = $base;
        $n = 0;
        while (username_exists($try)) {
            $n++;
            $try = $base . '_' . $n;
            if ($n > 50) return 0;
        }

        $new_id = wp_insert_user([
            'user_login'   => $try,
            'user_pass'    => wp_generate_password(24, true),
            'user_email'   => $email,
            'display_name' => (string)($phpbb_user['username'] ?? $try),
            'role'         => 'subscriber',
        ]);
        if (is_wp_error($new_id)) return 0;

        update_user_meta((int)$new_id, 'ia_phpbb_user_id', (string)$phpbb_id);
        return (int)$new_id;
    }

    private function maybe_mint_peertube_token(string $identifier, string $password, int $phpbb_user_id): void {
        if (!class_exists('IA_Auth')) return;

        $auth = IA_Auth::instance();
        $opt = $auth->options();
        if (($opt['peertube_oauth_method'] ?? 'password_grant') !== 'password_grant') return;

        $pt = $auth->peertube->password_grant($identifier, $password, $this->peertube_api_cfg());
        if (empty($pt['ok'])) return;

        $tok = (array)($pt['token'] ?? []);
        $expires_in = (int)($tok['expires_in'] ?? 0);
        $expires_at_utc = $expires_in > 0 ? gmdate('Y-m-d H:i:s', time() + $expires_in) : null;

        $auth->db->store_peertube_token($phpbb_user_id, [
            'access_token_enc'  => $auth->crypto->encrypt((string)($tok['access_token'] ?? '')),
            'refresh_token_enc' => $auth->crypto->encrypt((string)($tok['refresh_token'] ?? '')),
            'expires_at_utc'    => $expires_at_utc,
            'scope'             => '',
            'token_source'      => 'password_grant',
        ]);
    }

    public function ajax_login() {
        $this->guard_nonce();

        $id = trim((string)($_POST['identifier'] ?? ''));
        $pw = (string)($_POST['password'] ?? '');
        $redirect_to = !empty($_POST['redirect_to']) ? esc_url_raw((string)$_POST['redirect_to']) : home_url('/');

        if ($id === '' || $pw === '') {
            wp_send_json_error(['message' => 'Missing username/password.'], 400);
        }

        $cfg = $this->phpbb_cfg();
        if (empty($cfg['host']) || empty($cfg['name']) || empty($cfg['user'])) {
            wp_send_json_error(['message' => 'phpBB database is not configured (ia-engine).'], 500);
        }

        $phpbb = new IA_User_PHPBB();
        $auth = $phpbb->authenticate($id, $pw, $cfg);

        if (empty($auth['ok'])) {
            wp_send_json_error(['message' => $auth['message'] ?? 'Login failed.'], 401);
        }

        $phpbb_user = (array)($auth['user'] ?? []);
        $wp_user_id = $this->ensure_wp_shadow_user($phpbb_user);
        if ($wp_user_id <= 0) {
            wp_send_json_error(['message' => 'Could not create WP shadow user.'], 500);
        }

        wp_set_current_user($wp_user_id);
        wp_set_auth_cookie($wp_user_id, true);

        $this->maybe_mint_peertube_token($id, $pw, (int)($phpbb_user['user_id'] ?? 0));

        do_action('ia_user_after_login', $phpbb_user, $wp_user_id);

        wp_send_json_success([
            'message'     => 'OK',
            'redirect_to' => $redirect_to,
        ]);
    }

    public function ajax_register() {
        $this->guard_nonce();

        $username = trim((string)($_POST['username'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $pw       = (string)($_POST['password'] ?? '');
        $pw2      = (string)($_POST['password2'] ?? '');
        $redirect_to = !empty($_POST['redirect_to']) ? esc_url_raw((string)$_POST['redirect_to']) : home_url('/');

        if ($username === '' || $email === '' || $pw === '' || $pw2 === '') {
            wp_send_json_error(['message' => 'All fields are required.'], 400);
        }
        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Invalid email address.'], 400);
        }
        if ($pw !== $pw2) {
            wp_send_json_error(['message' => 'Passwords do not match.'], 400);
        }

        $cfg = $this->phpbb_cfg();
        if (empty($cfg['host']) || empty($cfg['name']) || empty($cfg['user'])) {
            wp_send_json_error(['message' => 'phpBB database is not configured (ia-engine).'], 500);
        }

        $phpbb = new IA_User_PHPBB();
        $created = $phpbb->create_user($username, $email, $pw, $cfg);
        if (empty($created['ok'])) {
            wp_send_json_error(['message' => (string)($created['message'] ?? 'Registration failed.')], 400);
        }

        $phpbb_user = (array)($created['user'] ?? []);
        $phpbb_user_id = (int)($phpbb_user['user_id'] ?? 0);
        if ($phpbb_user_id <= 0) {
            wp_send_json_error(['message' => 'Registration failed (no phpBB user id).'], 500);
        }

        $wp_user_id = $this->ensure_wp_shadow_user($phpbb_user);
        if ($wp_user_id <= 0) {
            wp_send_json_error(['message' => 'Could not create WP shadow user.'], 500);
        }

        wp_set_current_user($wp_user_id);
        wp_set_auth_cookie($wp_user_id, true);

        // Mint PeerTube token using the new account credentials.
        $this->maybe_mint_peertube_token($username, $pw, $phpbb_user_id);

        do_action('ia_user_after_register', $phpbb_user, $wp_user_id);

        wp_send_json_success([
            'message'     => 'OK',
            'redirect_to' => $redirect_to,
        ]);
    }

    public function ajax_logout() {
        $this->guard_nonce();
        wp_logout();
        wp_send_json_success([
            'message'     => 'OK',
            'redirect_to' => home_url('/'),
        ]);
    }
}
