<?php
if (!defined('ABSPATH')) exit;

final class IA_Auth {

    private static $instance = null;
    const OPT_KEY = 'ia_auth_options';

    public $log;
    public $db;
    public $crypto;
    public $phpbb;
    public $peertube;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {

        require_once IA_AUTH_PATH . 'includes/class-ia-auth-logger.php';
        require_once IA_AUTH_PATH . 'includes/class-ia-auth-db.php';
        require_once IA_AUTH_PATH . 'includes/class-ia-auth-crypto.php';
        require_once IA_AUTH_PATH . 'includes/class-ia-auth-phpbb.php';
        require_once IA_AUTH_PATH . 'includes/class-ia-auth-peertube.php';

        $this->log      = new IA_Auth_Logger();
        $this->crypto   = new IA_Auth_Crypto();
        $this->db       = new IA_Auth_DB($this->log);
        $this->phpbb    = new IA_Auth_PHPBB($this->log);
        $this->peertube = new IA_Auth_PeerTube($this->log, $this->crypto);

        // NO register_activation_hook() in this file.
        // Hooks are registered in ia-auth.php (plugin entry file).

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action('init', [$this, 'register_rewrites']);
        add_action('template_redirect', [$this, 'handle_routes']);

        add_action('wp_ajax_ia_auth_login', [$this, 'ajax_login']);
        add_action('wp_ajax_nopriv_ia_auth_login', [$this, 'ajax_login']);

        // Register (NEW)
        add_action('wp_ajax_ia_auth_register', [$this, 'ajax_register']);
        add_action('wp_ajax_nopriv_ia_auth_register', [$this, 'ajax_register']);

        add_action('wp_ajax_ia_auth_logout', [$this, 'ajax_logout']);
        add_action('wp_ajax_nopriv_ia_auth_logout', [$this, 'ajax_logout']);

        // Forgot password (modal flow): send WP reset email.
        // Always return a generic success message to avoid user enumeration.
        add_action('wp_ajax_ia_auth_forgot', [$this, 'ajax_forgot']);
        add_action('wp_ajax_nopriv_ia_auth_forgot', [$this, 'ajax_forgot']);

        add_action('wp_loaded', [$this, 'maybe_redirect_wp_login']);

        // Password sync (Option A): WordPress is source of truth.
        // Push the new WP password to the linked PeerTube user when it changes.
        // - after_password_reset provides the plaintext password (email reset flow)
        // - profile_update is a best-effort for profile/admin changes (requires pass1 in POST)
        add_action('after_password_reset', [$this, 'sync_peertube_password_after_reset'], 10, 2);
        add_action('profile_update', [$this, 'maybe_sync_peertube_password_on_profile_update'], 10, 2);

        if (is_admin()) {
            require_once IA_AUTH_PATH . 'includes/admin/class-ia-auth-admin.php';
            new IA_Auth_Admin($this);
        }
    }

    public function activate() {
        // Install tables (identity map, tokens, queue, audit) and flush rewrite rules.
        $this->db->install();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function enqueue_assets() {
        wp_enqueue_style('ia-auth', IA_AUTH_URL . 'assets/css/ia-auth.css', [], IA_AUTH_VERSION);
        wp_enqueue_script('ia-auth', IA_AUTH_URL . 'assets/js/ia-auth.js', ['jquery'], IA_AUTH_VERSION, true);

        wp_localize_script('ia-auth', 'IA_AUTH', [
            'ajax'           => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('ia_auth_nonce'),
            'login_redirect' => home_url('/'),
        ]);
    }

    public static function engine_normalized(): array {
        $out = [
            'phpbb' => [
                'host'   => '',
                'port'   => 3306,
                'name'   => '',
                'user'   => '',
                'pass'   => '',
                'prefix' => 'phpbb_',
            ],
            'peertube_api' => [
                'internal_base_url' => '',
                'public_base_url'   => '',
                'token'             => '',
            ],
        ];

        if (class_exists('IA_Engine')) {
            $p = IA_Engine::phpbb_db();
            $out['phpbb'] = [
                'host'   => (string)($p['host'] ?? ''),
                'port'   => (int)($p['port'] ?? 3306),
                'name'   => (string)($p['name'] ?? ''),
                'user'   => (string)($p['user'] ?? ''),
                'pass'   => (string)($p['pass'] ?? ''),
                'prefix' => (string)($p['prefix'] ?? 'phpbb_'),
            ];

            $out['peertube_api']['internal_base_url'] = (string) IA_Engine::peertube_internal_base_url();
            $out['peertube_api']['public_base_url']   = (string) IA_Engine::peertube_public_base_url();
            $out['peertube_api']['token']             = (string) IA_Engine::peertube_api_token();
        }

        return $out;
    }

    public function options(): array {
        $defaults = [
            'redirect_wp_login'       => 1,
            'disable_wp_registration' => 1,
            'match_policy'            => 'email_then_username',
            'wp_shadow_role'          => 'subscriber',
            'peertube_oauth_method'   => 'password_grant',
            'peertube_fail_policy'    => 'allow_login',
        ];

        $opt = get_option(self::OPT_KEY, []);
        if (!is_array($opt)) $opt = [];
        return array_merge($defaults, $opt);
    }

    public function update_options(array $new): void {
        $opt = $this->options();
        $opt = array_merge($opt, $new);
        update_option(self::OPT_KEY, $opt, false);
    }

    /**
     * Option A: WordPress is the password authority.
     * When WordPress resets a user's password, immediately push the new password to PeerTube.
     *
     * @param WP_User $user
     * @param string  $new_pass Plaintext password generated/chosen during reset
     */
    public function sync_peertube_password_after_reset($user, string $new_pass): void {
        if (!$user instanceof WP_User) return;
        $wp_user_id = (int) $user->ID;
        $new_pass = (string) $new_pass;
        if ($wp_user_id <= 0 || $new_pass === '') return;
        $this->sync_peertube_password_for_wp_user($wp_user_id, $new_pass, 'after_password_reset');
    }

    /**
     * Best-effort sync for password changes via wp-admin profile/user edit.
     * WordPress does not always provide plaintext here unless it came from a form.
     * We only attempt a sync when pass1 is present.
     *
     * @param int $user_id
     * @param WP_User $old_user_data
     */
    public function maybe_sync_peertube_password_on_profile_update(int $user_id, $old_user_data): void {
        $user_id = (int) $user_id;
        if ($user_id <= 0) return;

        // Only attempt when this request actually included a password field.
        $pass1 = isset($_POST['pass1']) ? (string) $_POST['pass1'] : '';
        if ($pass1 === '') return;
        if (isset($_POST['pass2']) && (string)$_POST['pass2'] !== '' && (string)$_POST['pass2'] !== $pass1) return;

        $this->sync_peertube_password_for_wp_user($user_id, $pass1, 'profile_update');
    }

    /**
     * Internal helper: resolve linked PeerTube user and update password via admin API.
     */
    private function sync_peertube_password_for_wp_user(int $wp_user_id, string $new_pass, string $source): void {
        $wp_user_id = (int) $wp_user_id;
        $new_pass = (string) $new_pass;
        if ($wp_user_id <= 0 || $new_pass === '') return;

        // Only sync if IA Engine is present (PeerTube config lives there).
        if (!class_exists('IA_Engine')) return;

        // Find identity mapping to PeerTube user id.
        $ident = $this->db->get_identity_by_wp_user_id($wp_user_id);
        if (!$ident || empty($ident['peertube_user_id'])) {
            return; // not provisioned/linked yet
        }
        $pt_user_id = (int) $ident['peertube_user_id'];
        if ($pt_user_id <= 0) return;

        $pt_cfg = IA_Engine::peertube_api();
        $res = $this->peertube->admin_update_user_password($pt_user_id, $new_pass, $pt_cfg);
        if (!empty($res['ok'])) {
            delete_user_meta($wp_user_id, 'ia_peertube_pw_sync_needed');
            $this->log->info('peertube_pw_sync_ok', ['wp_user_id' => $wp_user_id, 'pt_user_id' => $pt_user_id, 'source' => $source]);
            return;
        }

        // Mark for admin retry/diagnostics.
        update_user_meta($wp_user_id, 'ia_peertube_pw_sync_needed', 1);
        $this->log->error('peertube_pw_sync_failed', [
            'wp_user_id' => $wp_user_id,
            'pt_user_id' => $pt_user_id,
            'source'     => $source,
            'message'    => (string)($res['message'] ?? 'PeerTube password sync failed'),
            'code'       => (int)($res['code'] ?? 0),
        ]);
    }

    public function maybe_redirect_wp_login() {
        $opt = $this->options();
        if (empty($opt['redirect_wp_login'])) return;

        if (defined('DOING_AJAX') && DOING_AJAX) return;
        if (defined('DOING_CRON') && DOING_CRON) return;
        if (defined('WP_CLI') && WP_CLI) return;

        if (is_user_logged_in() && current_user_can('manage_options')) return;

        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');

        if (stripos($uri, 'wp-login.php') === false) return;

        if (!empty($_GET['ia_native']) || stripos($uri, 'ia_native=1') !== false) return;

        $action = (string)($_REQUEST['action'] ?? '');
        if ($action === 'logout' || $action === 'postpass') return;

        wp_safe_redirect(home_url('/ia-login/'));
        exit;
    }

    public function register_rewrites() {
        add_rewrite_rule('^ia-login/?$', 'index.php?ia_auth_route=login', 'top');
        add_rewrite_rule('^ia-register/?$', 'index.php?ia_auth_route=register', 'top');
        add_rewrite_rule('^ia-verify/([^/]+)/?$', 'index.php?ia_auth_route=verify&ia_auth_token=$matches[1]', 'top');
        // Password reset landing (we redirect into Atrium modal reset flow)
        add_rewrite_rule('^ia-reset/?$', 'index.php?ia_auth_route=reset', 'top');
        add_rewrite_rule('^ia-check-email/?$', 'index.php?ia_auth_route=check_email', 'top');
        add_rewrite_rule('^ia-check-reset/?$', 'index.php?ia_auth_route=check_reset', 'top');
        add_rewrite_tag('%ia_auth_route%', '([^&]+)');
        add_rewrite_tag('%ia_auth_token%', '([^&]+)');
    }

    public function handle_routes() {
        $route = get_query_var('ia_auth_route');
        if (!$route) return;

        // Dedicated password reset URL. We don't render WordPress reset forms here.
        // Instead, redirect to home with ia_reset=1&key=&login= so IA User opens its styled reset panel.
        if ($route === 'reset') {
            $key = isset($_GET['key']) ? trim((string)$_GET['key']) : '';
            $login = isset($_GET['login']) ? trim((string)$_GET['login']) : '';
            if ($key !== '' && $login !== '') {
                $url = add_query_arg([
                    'ia_reset' => '1',
                    'key'      => $key,
                    'login'    => $login,
                ], home_url('/'));
                wp_safe_redirect($url);
                exit;
            }
            // If missing params, just send them to login.
            wp_safe_redirect(home_url('/ia-login/'));
            exit;
        }

        status_header(200);
        nocache_headers();

        $title = ($route === 'register') ? 'Register' : (($route === 'verify') ? 'Verify email' : ((($route === 'check_email' || $route === 'check_reset') ? 'Check your email' : 'Login')));


        // IMPORTANT: the verify route may set auth cookies (headers).
        // Do NOT output anything (including wp_head styles) before processing verification.
        $verify_out = null;
        $verify_token = '';
        if ($route === 'verify') {
            $verify_token = (string) get_query_var('ia_auth_token');
            if ($verify_token === '' && isset($_GET['ia_auth_token'])) $verify_token = (string) $_GET['ia_auth_token'];
            if ($verify_token === '' && isset($_GET['token'])) $verify_token = (string) $_GET['token'];
            $verify_token = trim(urldecode($verify_token));
            if ($verify_token !== '') {
                $verify_out = $this->handle_email_verification($verify_token);
            } else {
                $verify_out = ['ok' => false, 'message' => 'Missing verification token (no token in URL).'];
            }
        }

        echo '<!doctype html><html><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . esc_html($title) . '</title>';
        wp_head();
        echo '</head><body class="ia-auth-page"><div class="ia-auth-page-wrap">';

        if ($route === 'register') {
            echo '<div class="ia-auth-card"><h2>Register</h2>';
            echo '<form class="ia-auth-form" data-action="ia_auth_register">';
            echo '<input type="hidden" name="nonce" value="' . esc_attr(wp_create_nonce('ia_auth_nonce')) . '">';
            echo '<label><span>Username</span><input type="text" name="username" autocomplete="username" required></label>';
            echo '<label><span>Email</span><input type="email" name="email" autocomplete="email" required></label>';
            echo '<label><span>Password</span><input type="password" name="password" autocomplete="new-password" required></label>';
            echo '<label><span>Confirm password</span><input type="password" name="password2" autocomplete="new-password" required></label>';
            echo '<input type="hidden" name="redirect_to" value="' . esc_attr(home_url('/')) . '">';
            echo '<button type="submit">Register</button>';
            echo '<div class="ia-auth-msg"></div>';
            echo '</form>';
            echo '<div class="ia-auth-alt"><a href="' . esc_url(home_url('/ia-login/')) . '">Back to login</a></div>';
            echo '</div>';
        } elseif ($route === 'verify') {
            $out = $verify_out ?: ['ok' => false, 'message' => 'Verification failed.'];
            if ($out['ok']) {
                echo '<div class="ia-auth-card"><h2>Email verified</h2><p>' . esc_html($out['message']) . '</p>';
                echo '<p><a href="' . esc_url(home_url('/')) . '">Continue</a></p></div>';
            } else {
                echo '<div class="ia-auth-card"><h2>Verification failed</h2><p>' . esc_html($out['message']) . '</p></div>';
            }
        } elseif ($route === 'check_email') {
            echo '<div class="ia-auth-card"><h2>Check your email</h2>';
            echo '<p>Your account has been created. Please check your email inbox for your activation link.</p>';
            echo '<p>You must activate your account before you can sign in.</p>';
            echo '<p><a href="' . esc_url(home_url('/')) . '">Return to homepage</a></p>';
            echo '</div>';
        } elseif ($route === 'check_reset') {
            echo '<div class="ia-auth-card"><h2>Check your email</h2>';
            echo '<p>If an account exists for that email address, a password reset link has been sent.</p>';
            echo '<p>Please check your inbox and follow the link to set a new password.</p>';
            echo '<p><a href="' . esc_url(home_url('/')) . '">Return to homepage</a></p>';
            echo '</div>';

        } else {
            echo '<div class="ia-auth-card"><h2>Login</h2>';
            echo '<form class="ia-auth-form" data-action="ia_auth_login">';
            echo '<input type="hidden" name="nonce" value="' . esc_attr(wp_create_nonce('ia_auth_nonce')) . '">';
            echo '<label><span>Username or Email</span><input type="text" name="identifier" autocomplete="username" required></label>';
            echo '<label><span>Password</span><input type="password" name="password" autocomplete="current-password" required></label>';
            echo '<input type="hidden" name="redirect_to" value="' . esc_attr(home_url('/')) . '">';
            echo '<button type="submit">Login</button>';
            echo '<div class="ia-auth-msg"></div>';
            echo '</form>';
            echo '<div class="ia-auth-alt">Admin? Use <code>/wp-login.php?ia_native=1</code></div>';
            echo '</div>';
        }

        echo '</div>';
        wp_footer();
        echo '</body></html>';
        exit;
    }

    private function guard_ajax_nonce(): void {
        $nonce = (string)($_POST['nonce'] ?? '');
        if ($nonce === '') $nonce = (string)($_POST['_wpnonce'] ?? '');

        if ($nonce === '' || !wp_verify_nonce($nonce, 'ia_auth_nonce')) {
            wp_send_json_error(['message' => 'Bad nonce. Refresh and try again.'], 403);
        }
    }

    public function ajax_login() {
    $this->guard_ajax_nonce();

    $id = trim((string)($_POST['identifier'] ?? ''));
    $pw = (string)($_POST['password'] ?? '');
    $redirect_to = !empty($_POST['redirect_to']) ? esc_url_raw((string)$_POST['redirect_to']) : home_url('/');

    if ($id === '' || $pw === '') {
        wp_send_json_error(['message' => 'Missing username/password.'], 400);
    }

    $engine = self::engine_normalized();
    $auth = $this->phpbb->authenticate($id, $pw, $engine['phpbb']);

    if (empty($auth['ok'])) {
        wp_send_json_error(['message' => $auth['message'] ?? 'Login failed.'], 401);
    }

    $phpbb_user = $auth['user'];

    $wp_user_id = $this->db->ensure_wp_shadow_user($phpbb_user, $this->options());
    if (!$wp_user_id) {
        wp_send_json_error(['message' => 'Could not create WP shadow user.'], 500);
    }

    // Block login until email is verified (Atrium signup path).
    $verified = (int) get_user_meta((int)$wp_user_id, 'ia_email_verified', true);
    if ($verified !== 1) {
        wp_send_json_error(['message' => 'Please verify your email to activate this account.'], 403);
    }

    wp_set_current_user((int)$wp_user_id);
    wp_set_auth_cookie((int)$wp_user_id, true);

    // --- PeerTube per-user token mint needs a one-time plaintext password bridge ---
    // Backstop + diagnostics: persist a marker and the password bridge directly
    // from the login POST. This proves the executing login path and avoids relying
    // on hooks that might be bypassed.
    try {
        $uid_dbg = (int) $wp_user_id;
        if ($uid_dbg > 0) {
            // Marker: lets you verify the login handler executed.
            update_user_meta($uid_dbg, 'ia_pt_pw_capture_at', (string) current_time('mysql', 1));

            // Persist the bridge keys used by ia-peertube-token-mint-users.
            if (class_exists('IA_PT_Password_Capture') && method_exists('IA_PT_Password_Capture', 'capture_for_user')) {
                IA_PT_Password_Capture::capture_for_user($uid_dbg, (string) $pw, (string) $id);
            } else {
                // Last resort: write the keys directly.
                update_user_meta($uid_dbg, 'ia_pt_identifier', (string) $id);
                update_user_meta($uid_dbg, 'ia_pt_pw_enc', (string) $pw);
            }
        }
    } catch (Throwable $e) {
        // Never block login.
    }

    // Atrium shadow-session logins bypass wp_signon/wp_authenticate.
    // Provide the plaintext password to the PeerTube per-user token mint helper for this request.
    // Then opportunistically mint a user token now, so later Stream write actions can attribute
    // comments/replies correctly without needing the password again.
    do_action('ia_pt_user_password', (int)$wp_user_id, (string)$pw, (string)$id);

    if (class_exists('IA_PeerTube_Token_Helper') && method_exists('IA_PeerTube_Token_Helper', 'get_token_for_current_user')) {
        // This is deliberately non-blocking: login should succeed even if PeerTube minting fails.
        try {
            IA_PeerTube_Token_Helper::get_token_for_current_user();
        } catch (Throwable $e) {
            // Non-fatal.
        }
    }

    $phpbb_user_id = (int)($phpbb_user['user_id'] ?? 0);
    $phpbb_email   = (string)($phpbb_user['user_email'] ?? '');
    $phpbb_un      = (string)($phpbb_user['username'] ?? '');
    $phpbb_uclean  = (string)($phpbb_user['username_clean'] ?? '');

    // Create/update identity map immediately, but do NOT mark linked until we have a user PeerTube token.
    $this->db->upsert_identity([
        'phpbb_user_id'        => $phpbb_user_id,
        'phpbb_username_clean' => $phpbb_uclean,
        'email'                => $phpbb_email,
        'wp_user_id'           => (int)$wp_user_id,
        'status'               => 'partial',
        'last_error'           => '',
    ]);

    // ===== PeerTube activation (required for Stream write actions) =====
    $opt = $this->options();
    $pt_user_id = 0;
    $pt_account_id = 0;
    $pt = ['ok' => false, 'message' => 'PeerTube token not attempted.'];

    if (($opt['peertube_oauth_method'] ?? 'password_grant') === 'password_grant' && $phpbb_user_id > 0) {

        // 1) Try password grant using the identifier the user typed.
        $pt = $this->peertube->password_grant($id, $pw, $engine['peertube_api']);

        // 2) If it fails, ensure PeerTube user exists + password matches, then retry password grant.
        if (empty($pt['ok'])) {
            $this->log->warn('peertube_token_failed_first', [
                'message' => $pt['message'] ?? 'PeerTube token failed.',
                'debug'   => $pt['debug'] ?? null,
            ]);

            $pt_cfg = $engine['peertube_api'];

            // Try find by identifier first (username/email), then by phpBB username/email.
            $needles = [];
            $needles[] = $id;
            if ($phpbb_un !== '' && strtolower($phpbb_un) !== strtolower($id)) $needles[] = $phpbb_un;
            if ($phpbb_email !== '' && strtolower($phpbb_email) !== strtolower($id)) $needles[] = $phpbb_email;

            $found = null;
            foreach ($needles as $needle) {
                $f = $this->peertube->admin_find_user((string)$needle, $pt_cfg);
                if (!empty($f['ok']) && !empty($f['user']) && is_array($f['user'])) {
                    $found = $f['user'];
                    break;
                }
            }

            if ($found) {
                $pt_user_id = (int)($found['id'] ?? 0);
                if (isset($found['account']) && is_array($found['account'])) {
                    $pt_account_id = (int)($found['account']['id'] ?? 0);
                }

                if ($pt_user_id > 0) {
                    $upd = $this->peertube->admin_update_user_password($pt_user_id, $pw, $pt_cfg);
                    if (empty($upd['ok'])) {
                        $this->log->error('peertube_pw_update_failed', [
                            'pt_user_id' => $pt_user_id,
                            'message'    => (string)($upd['message'] ?? ''),
                            'code'       => (int)($upd['code'] ?? 0),
                            'debug'      => $upd['debug'] ?? null,
                        ]);
                    }
                }
            } else {
                // Create PeerTube user if missing.
                $email_for_pt = $phpbb_email !== '' ? $phpbb_email : (is_email($id) ? $id : '');
                $username_for_pt = $phpbb_un !== '' ? $phpbb_un : (is_email($id) ? ('user' . $phpbb_user_id) : $id);

                if ($email_for_pt !== '') {
                    $chan_base = strtolower((string)$username_for_pt);
                    $chan_base = preg_replace('/[^a-z0-9_]+/', '_', $chan_base);
                    $chan_base = trim($chan_base, '_');
                    if ($chan_base === '') $chan_base = 'user';
                    $channel_name = substr($chan_base . '_channel', 0, 50);

                    $crt = $this->peertube->admin_create_user($username_for_pt, $email_for_pt, $pw, $channel_name, $pt_cfg);
                    if (!empty($crt['ok'])) {
                        $pt_user_id = (int)($crt['peertube_user_id'] ?? 0);
                        $pt_account_id = (int)($crt['peertube_account_id'] ?? 0);
                    } else {
                        $this->log->error('peertube_create_failed', [
                            'message' => (string)($crt['message'] ?? ''),
                            'code'    => (int)($crt['code'] ?? 0),
                            'debug'   => $crt['debug'] ?? null,
                        ]);
                    }
                } else {
                    $this->log->error('peertube_create_missing_email', [
                        'phpbb_user_id' => $phpbb_user_id,
                        'identifier'    => $id,
                    ]);
                }
            }

            // Persist what we know so far (still partial until we have a user token).
            $this->db->upsert_identity([
                'phpbb_user_id'        => $phpbb_user_id,
                'phpbb_username_clean' => $phpbb_uclean,
                'email'                => $phpbb_email,
                'wp_user_id'           => (int)$wp_user_id,
                'peertube_user_id'     => $pt_user_id,
                'peertube_account_id'  => $pt_account_id,
                'status'               => 'partial',
                'last_error'           => (string)($pt['message'] ?? 'PeerTube token failed.'),
            ]);

            // Retry password grant with a few candidates.
            $cands = [];
            $cands[] = $id;
            if ($phpbb_un !== '' && strtolower($phpbb_un) !== strtolower($id)) $cands[] = $phpbb_un;
            if ($phpbb_email !== '' && strtolower($phpbb_email) !== strtolower($id)) $cands[] = $phpbb_email;

            foreach ($cands as $cand) {
                $pt_try = $this->peertube->password_grant((string)$cand, $pw, $engine['peertube_api']);
                if (!empty($pt_try['ok'])) {
                    $pt = $pt_try;
                    break;
                }
            }
        }

        // 3) If still failing, decide whether to block login.
        if (empty($pt['ok'])) {
            $this->db->upsert_identity([
                'phpbb_user_id'        => $phpbb_user_id,
                'phpbb_username_clean' => $phpbb_uclean,
                'email'                => $phpbb_email,
                'wp_user_id'           => (int)$wp_user_id,
                'peertube_user_id'     => $pt_user_id,
                'peertube_account_id'  => $pt_account_id,
                'status'               => 'partial',
                'last_error'           => (string)($pt['message'] ?? 'PeerTube token failed.'),
            ]);

            if (($opt['peertube_fail_policy'] ?? 'allow_login') === 'block_login') {
                wp_clear_auth_cookie();
                wp_send_json_error(['message' => $pt['message'] ?? 'PeerTube auth failed.'], 502);
            }
        } else {
            // Success: store encrypted user token and mark identity fully linked.
            $tok = (array)($pt['token'] ?? []);
            $expires_in = (int)($tok['expires_in'] ?? 0);
            $expires_at_utc = $expires_in > 0 ? gmdate('Y-m-d H:i:s', time() + $expires_in) : null;

            $access_plain  = (string)($tok['access_token'] ?? '');
            $refresh_plain = (string)($tok['refresh_token'] ?? '');
            $access_enc    = $this->crypto->encrypt($access_plain);
            $refresh_enc   = $this->crypto->encrypt($refresh_plain);

            $stored_ok = false;
            if ($access_plain !== '' && $access_enc !== '') {
                $stored_ok = $this->db->store_peertube_token($phpbb_user_id, [
                    'access_token_enc'  => $access_enc,
                    'refresh_token_enc' => $refresh_enc,
                    'expires_at_utc'    => $expires_at_utc,
                    'scope'             => '',
                    'token_source'      => 'password_grant',
                ]);
            }

            // Read-back verification: some hosts/DB layers can silently fail inserts (permissions, wrong DB, etc.).
            // Only consider the account fully activated if we can read a non-empty token back.
            $readback_ok = false;
            if ($stored_ok) {
                global $wpdb;
                if (isset($wpdb) && is_object($wpdb)) {
                    $tok_table = $wpdb->prefix . 'ia_peertube_tokens';
                    $rb = (string)$wpdb->get_var(
                        $wpdb->prepare("SELECT access_token_enc FROM {$tok_table} WHERE phpbb_user_id=%d LIMIT 1", $phpbb_user_id)
                    );
                    $readback_ok = (trim($rb) !== '');
                }
            }

            if ($stored_ok && !$readback_ok) {
                $stored_ok = false;
            }

            if (!$stored_ok) {
                // We have a token but could not store it (DB error or crypto failure).
                $this->db->upsert_identity([
                    'phpbb_user_id'        => $phpbb_user_id,
                    'phpbb_username_clean' => $phpbb_uclean,
                    'email'                => $phpbb_email,
                    'wp_user_id'           => (int)$wp_user_id,
                    'peertube_user_id'     => $pt_user_id,
                    'peertube_account_id'  => $pt_account_id,
                    'status'               => 'partial',
                    'last_error'           => 'Could not persist PeerTube token (crypto/db/readback).',
                ]);
            } else {
                $this->db->upsert_identity([
                'phpbb_user_id'        => $phpbb_user_id,
                'phpbb_username_clean' => $phpbb_uclean,
                'email'                => $phpbb_email,
                'wp_user_id'           => (int)$wp_user_id,
                'peertube_user_id'     => $pt_user_id,
                'peertube_account_id'  => $pt_account_id,
                'status'               => 'linked',
                'last_error'           => '',
                ]);
            }
        }
    }

    wp_send_json_success([
        'message'     => 'OK',
        'redirect_to' => $redirect_to,
    ]);
}

public function ajax_register() {
        $this->guard_ajax_nonce();

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

        $engine = self::engine_normalized();

        $created = $this->phpbb->create_user($username, $email, $pw, $engine['phpbb']);
        if (empty($created['ok'])) {
            wp_send_json_error(['message' => (string)($created['message'] ?? 'Registration failed.')], 400);
        }

        $phpbb_user = (array)($created['user'] ?? []);
        $phpbb_user_id = (int)($phpbb_user['user_id'] ?? 0);
        if ($phpbb_user_id <= 0) {
            wp_send_json_error(['message' => 'Registration failed (no phpBB user id).'], 500);
        }

        $wp_user_id = $this->db->ensure_wp_shadow_user($phpbb_user, $this->options());
        if (!$wp_user_id) {
            wp_send_json_error(['message' => 'Could not create WP shadow user.'], 500);
        }

        wp_set_current_user($wp_user_id);
        wp_set_auth_cookie($wp_user_id, true);

        $this->db->upsert_identity([
            'phpbb_user_id'        => $phpbb_user_id,
            'phpbb_username_clean' => (string)($phpbb_user['username_clean'] ?? ''),
            'email'                => (string)($phpbb_user['user_email'] ?? ''),
            'wp_user_id'           => (int)$wp_user_id,
            'status'               => 'pending_email',
            'last_error'           => '',
        ]);

        
        // Mark as unverified until email confirmation
        update_user_meta((int)$wp_user_id, 'ia_email_verified', 0);

        // Create email verification token + store encrypted password temporarily (deleted after verify).
        $token = bin2hex(random_bytes(16));
        $pw_enc = $this->crypto->encrypt($pw);
        update_user_meta((int)$wp_user_id, 'ia_pw_enc_pending', $pw_enc);

        $payload = wp_json_encode([
            'token'    => $token,
            'username' => $username,
            'email'    => $email,
            'pw_enc'   => $pw_enc,
            'created'  => time(),
        ]);

        $ok = $this->db->create_email_verification_job($phpbb_user_id, $token, (string)$payload);
        if (!$ok) {
            wp_send_json_error(['message' => 'Could not create verification job.'], 500);
        }

        $verify_url = home_url('/ia-verify/' . $token);

        $subject = apply_filters('ia_auth_verify_email_subject', 'Activate your IndieAgora account');
        $body    = apply_filters('ia_auth_verify_email_body',
            "Hi {$username},\n\nPlease activate your account by clicking the link below:\n\n{$verify_url}\n\nIf you did not create this account, you can ignore this email.\n",
            $username, $verify_url
        );

        wp_mail($email, $subject, $body);

wp_send_json_success([
            'message'     => 'Account created. Please check your email to activate.',
            'redirect_to' => $redirect_to,
        ]);
    }

    public function ajax_logout() {
        wp_logout();
        wp_send_json_success(['message' => 'OK']);
    }

    /**
     * Forgot password (modal): triggers the standard WP reset email.
     *
     * Security: always returns a generic success message to prevent user enumeration.
     */
    public function ajax_forgot() {
        $this->guard_ajax_nonce();

        $login = isset($_POST['login']) ? trim((string)$_POST['login']) : '';
        $login = sanitize_text_field($login);

        // WP core reads from $_POST['user_login'].
        $_POST['user_login'] = $login;

        // This returns true or WP_Error. Do not leak errors to callers.
        $res = retrieve_password();
        if (is_wp_error($res)) {
            $this->log->warn('forgot_password_failed', [
                'code'    => $res->get_error_code(),
                'message' => $res->get_error_message(),
            ]);
        }

        wp_send_json_success([
            'message' => 'If that account exists, a reset email has been sent.',
        ]);
    }
}