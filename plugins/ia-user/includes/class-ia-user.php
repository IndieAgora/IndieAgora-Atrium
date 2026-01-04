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

        // Forgot password (modal flow): send WordPress reset email.
        // Always return generic success to avoid user enumeration.
        add_action('wp_ajax_ia_user_forgot', [$this, 'ajax_forgot']);
        add_action('wp_ajax_nopriv_ia_user_forgot', [$this, 'ajax_forgot']);

        // Password reset (from email link -> Atrium modal)
        add_action('wp_ajax_ia_user_reset', [$this, 'ajax_reset']);
        add_action('wp_ajax_nopriv_ia_user_reset', [$this, 'ajax_reset']);

        // Rewrite WP reset email links to Atrium modal route
        add_filter('retrieve_password_message', [$this, 'filter_retrieve_password_message'], 10, 4);

        add_action('wp_ajax_ia_user_logout', [$this, 'ajax_logout']);
        add_action('wp_ajax_nopriv_ia_user_logout', [$this, 'ajax_logout']);
    }

    public function ajax_forgot() {
        $this->guard_nonce();

        $login = isset($_POST['login']) ? trim((string)$_POST['login']) : '';
        $login = sanitize_text_field($login);

        // WP core reads from $_POST['user_login'].
        $_POST['user_login'] = $login;

        $res = retrieve_password();
        if (is_wp_error($res)) {
            error_log('[IA_USER] retrieve_password failed: ' . $res->get_error_code() . ' ' . $res->get_error_message());
        }

		wp_send_json_success([
			'message' => 'If that account exists, a reset email has been sent.',
		]);
	}

	public function filter_retrieve_password_message($message, $key, $user_login, $user_data) {
		// Send users to a dedicated IA Auth route. IA Auth will redirect into the Atrium modal reset flow.
		$url = add_query_arg([
			'key'   => $key,
			'login' => $user_login,
		], home_url('/ia-reset/'));

		// Replace the default wp-login reset link with our Atrium reset route.
		// Replace any wp-login reset URL (WordPress can reorder query params).
		$message2 = preg_replace('#https?://[^\s]+/wp-login\.php\?[^\s]*#', $url, $message, 1);
		$message2 = preg_replace('#/wp-login\.php\?[^\s]*#', $url, $message2, 1);

		// Fallback: append our URL if we didn't find/replace anything.
		if (strpos($message2, $url) === false) {
			$message2 .= "\n\n" . $url . "\n";
		}

		return $message2;
	}

	public function ajax_reset() {
		$this->guard_nonce();

		$login = isset($_POST['login']) ? sanitize_text_field((string)$_POST['login']) : '';
		$key   = isset($_POST['key']) ? sanitize_text_field((string)$_POST['key']) : '';
		$pass1 = isset($_POST['pass1']) ? (string)$_POST['pass1'] : '';
		$pass2 = isset($_POST['pass2']) ? (string)$_POST['pass2'] : '';

		if ($login === '' || $key === '' || $pass1 === '' || $pass2 === '') {
			wp_send_json_error(['message' => 'Missing required fields.'], 400);
		}
		if ($pass1 !== $pass2) {
			wp_send_json_error(['message' => 'Passwords do not match.'], 400);
		}

		$user = check_password_reset_key($key, $login);
		if (is_wp_error($user)) {
			wp_send_json_error(['message' => 'Reset link is invalid or expired. Please request a new one.'], 400);
		}

		reset_password($user, $pass1);

		wp_send_json_success([
			'message' => 'Password reset successful. You can now log in.',
			'login'   => $user->user_login,
		]);
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

// Prefer phpBB auth when configured, but fall back to native WP auth for WP-only accounts.
$phpbb_user = [];
$wp_user_id = 0;

$phpbb_configured = !empty($cfg['host']) && !empty($cfg['name']) && !empty($cfg['user']);
if ($phpbb_configured) {
    $phpbb = new IA_User_PHPBB();
    $auth = $phpbb->authenticate($id, $pw, $cfg);

    if (!empty($auth['ok'])) {
        $phpbb_user = (array)($auth['user'] ?? []);
        $wp_user_id = $this->ensure_wp_shadow_user($phpbb_user);
        if ($wp_user_id <= 0) {
            wp_send_json_error(['message' => 'Could not create WP shadow user.'], 500);
        }
    }
}

// Fallback: try WordPress auth (covers users created by Atrium/WP that do not exist in phpBB).
if ($wp_user_id <= 0) {
    $login = $id;
    if (is_email($id)) {
        $u = get_user_by('email', $id);
        if ($u && !is_wp_error($u)) $login = $u->user_login;
    }

    $user = wp_signon([
        'user_login'    => $login,
        'user_password' => $pw,
        'remember'      => true,
    ], is_ssl());

    if (is_wp_error($user) || empty($user->ID)) {
        wp_send_json_error(['message' => 'Invalid username/email or password.'], 401);
    }

    $wp_user_id = (int)$user->ID;

    // If we have a mapped phpBB id, keep the do_action payload consistent.
    $maybe_phpbb_id = (int)get_user_meta($wp_user_id, 'ia_phpbb_user_id', true);
    if ($maybe_phpbb_id > 0) {
        $phpbb_user = ['user_id' => $maybe_phpbb_id, 'username' => $user->user_login, 'user_email' => $user->user_email];
    } else {
        $phpbb_user = ['user_id' => 0, 'username' => $user->user_login, 'user_email' => $user->user_email];
    }
}

$verified = (string)get_user_meta($wp_user_id, self::META_EMAIL_VERIFIED, true);
if ($verified !== '1') {
    wp_send_json_error(['message' => 'Please verify your email to activate this account.'], 403);
}

wp_set_current_user($wp_user_id);
wp_set_auth_cookie($wp_user_id, true);

// If PeerTube provisioning is enabled, mint/refresh an admin token for follow-on calls when needed.
$this->maybe_mint_peertube_token((string)($phpbb_user['username'] ?? $id), $pw, (int)($phpbb_user['user_id'] ?? 0));

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
// Registration now requires email verification.
update_user_meta($wp_user_id, self::META_EMAIL_VERIFIED, '0');

// Delegate verification + PeerTube provisioning to IA Auth (it owns /ia-verify/{token})
if (class_exists('IA_Auth') && method_exists(IA_Auth::instance(), 'create_verification_for_existing')) {
    $res = IA_Auth::instance()->create_verification_for_existing(
        $phpbb_user_id,
        $wp_user_id,
        $username,
        $email,
        $pw
    );
    if (empty($res['ok'])) {
        wp_send_json_error(['message' => 'Could not send verification email: ' . ($res['message'] ?? 'Unknown error')], 500);
    }
} else {
    wp_send_json_error(['message' => 'IA Auth is required for email verification.'], 500);
}

        // Do NOT auto-login until verified.
do_action('ia_user_after_register', $phpbb_user, $wp_user_id);

        wp_send_json_success([
            'message'     => 'OK',
            'redirect_to' => $redirect_to,
        ]);
    }

    
    // ===========================
    // Email verification + PeerTube provisioning
    // ===========================
    private const META_EMAIL_VERIFIED = 'ia_email_verified';
    private const META_VERIFY_TOKEN   = 'ia_verify_token';
    private const META_VERIFY_EXPIRES = 'ia_verify_expires';
    private const META_PENDING_PW     = 'ia_pending_pw_enc';

    public function boot_email_verify_routes(): void {
        add_action('init', [$this, 'register_verify_rewrite']);
        add_action('template_redirect', [$this, 'maybe_handle_verify']);
        add_filter('query_vars', function($vars){ $vars[]='ia_verify'; return $vars; });
    }

    public function register_verify_rewrite(): void {
        add_rewrite_rule('^ia-verify/([A-Za-z0-9_-]+)/?$', 'index.php?ia_verify=$matches[1]', 'top');
        add_rewrite_tag('%ia_verify%', '([A-Za-z0-9_-]+)');
    }

    private function new_verify_token(): string {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }

    private function send_verify_email(int $wp_user_id, string $email): bool {
        $token = get_user_meta($wp_user_id, self::META_VERIFY_TOKEN, true);
        if (!$token) return false;

        $url = home_url('/ia-verify/' . rawurlencode($token) . '/');
        $subject = apply_filters('ia_user_verify_email_subject', 'Verify your IndieAgora account', $wp_user_id, $url);
        $body = apply_filters('ia_user_verify_email_body',
            "Hi\n\nPlease verify your email to activate your account:\n{$url}\n\nIf you didn't create this account, you can ignore this email.",
            $wp_user_id, $url
        );

        return wp_mail($email, $subject, $body);
    }

    private function provision_peertube_user(string $username, string $email, string $password): array {
        if (!class_exists('IA_Engine')) {
            return [false, 'IA Engine not available'];
        }

        $token = IA_Engine::peertube_api_token();
        if (!$token) {
            return [false, 'PeerTube API token not configured'];
        }

        $base = rtrim(IA_Engine::peertube_internal_base_url(), '/');
        if (!$base) {
            return [false, 'PeerTube base URL not configured'];
        }

        $endpoint = $base . '/api/v1/users';

        // PeerTube: create user (admin)
        $payload = [
            'username'    => $username,
            'email'       => $email,
            'password'    => $password,
            'role'        => 2, // typically USER; admin token decides
            'videoQuota'  => -1,
            'videoQuotaDaily' => -1,
            'channelName' => $username,
            'displayName' => $username,
        ];

        $res = wp_remote_post($endpoint, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($res)) {
            return [false, $res->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);
        $data = json_decode($body, true);

        if ($code < 200 || $code >= 300) {
            $msg = 'PeerTube create user failed';
            if (is_array($data)) {
                $msg = $data['message'] ?? $data['error'] ?? $msg;
            }
            return [false, $msg . ' (HTTP ' . $code . ')'];
        }

        // Response usually includes the created user info.
        $pt_user_id = 0;
        if (is_array($data)) {
            $pt_user_id = (int)($data['user']['id'] ?? $data['id'] ?? 0);
        }

        return [true, $pt_user_id];
    }

    public function maybe_handle_verify(): void {
        $token = get_query_var('ia_verify');
        if (!$token) return;

        // Find user by token
        $users = get_users([
            'meta_key'   => self::META_VERIFY_TOKEN,
            'meta_value' => sanitize_text_field((string)$token),
            'number'     => 1,
            'fields'     => 'ID',
        ]);

        if (empty($users)) {
            wp_die('Invalid or expired verification link.', 'Verification', 400);
        }

        $wp_user_id = (int)$users[0];
        $expires = (int) get_user_meta($wp_user_id, self::META_VERIFY_EXPIRES, true);
        if ($expires && time() > $expires) {
            wp_die('This verification link has expired. Please register again.', 'Verification', 400);
        }

        $email = (string) get_userdata($wp_user_id)->user_email;
        $username = (string) get_userdata($wp_user_id)->user_login;

        $enc = (string) get_user_meta($wp_user_id, self::META_PENDING_PW, true);
        $pw = '';
        if ($enc && class_exists('IA_Engine_Crypto')) {
            $pw = IA_Engine_Crypto::decrypt($enc);
        }

        if (!$pw) {
            wp_die('Verification failed (missing pending password). Please register again.', 'Verification', 500);
        }

        // Provision PeerTube user now
        [$ok, $pt_user_id_or_msg] = $this->provision_peertube_user($username, $email, $pw);
        if (!$ok) {
            wp_die('Verification failed: ' . esc_html((string)$pt_user_id_or_msg), 'Verification', 500);
        }

        // Mark verified + cleanup token + pending pw
        update_user_meta($wp_user_id, self::META_EMAIL_VERIFIED, '1');
        delete_user_meta($wp_user_id, self::META_VERIFY_TOKEN);
        delete_user_meta($wp_user_id, self::META_VERIFY_EXPIRES);
        delete_user_meta($wp_user_id, self::META_PENDING_PW);

        // Store peertube_user_id in identity map if available
        if (method_exists($this, 'identity_set_peertube_user_id')) {
            $this->identity_set_peertube_user_id($wp_user_id, (int)$pt_user_id_or_msg);
        }

        // Log them in
        wp_set_current_user($wp_user_id);
        wp_set_auth_cookie($wp_user_id, true);

        wp_safe_redirect(home_url('/?verified=1'));
        exit;
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
