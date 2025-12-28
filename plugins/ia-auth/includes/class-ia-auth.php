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

        add_action('wp_loaded', [$this, 'maybe_redirect_wp_login']);

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
        add_rewrite_tag('%ia_auth_route%', '([^&]+)');
    }

    public function handle_routes() {
        $route = get_query_var('ia_auth_route');
        if (!$route) return;

        status_header(200);
        nocache_headers();

        $title = ($route === 'register') ? 'Register' : 'Login';

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

        wp_set_current_user($wp_user_id);
        wp_set_auth_cookie($wp_user_id, true);

        $this->db->upsert_identity([
            'phpbb_user_id'        => (int)($phpbb_user['user_id'] ?? 0),
            'phpbb_username_clean' => (string)($phpbb_user['username_clean'] ?? ''),
            'email'                => (string)($phpbb_user['user_email'] ?? ''),
            'wp_user_id'           => (int)$wp_user_id,
            'status'               => 'linked',
            'last_error'           => '',
        ]);

        // Best-effort PeerTube token minting (unchanged behavior)
        $opt = $this->options();
        if (($opt['peertube_oauth_method'] ?? 'password_grant') === 'password_grant') {
            $pt = $this->peertube->password_grant($id, $pw, $engine['peertube_api']);

            if (empty($pt['ok'])) {
                $this->log->warn('peertube_token_failed', [
                    'message' => $pt['message'] ?? 'PeerTube token failed.',
                    'debug'   => $pt['debug'] ?? null,
                ]);

                if (($opt['peertube_fail_policy'] ?? 'allow_login') === 'block_login') {
                    wp_clear_auth_cookie();
                    wp_send_json_error(['message' => $pt['message'] ?? 'PeerTube auth failed.'], 502);
                }
            } else {
                $tok = (array)($pt['token'] ?? []);
                $expires_in = (int)($tok['expires_in'] ?? 0);
                $expires_at_utc = $expires_in > 0 ? gmdate('Y-m-d H:i:s', time() + $expires_in) : null;

                $this->db->store_peertube_token((int)($phpbb_user['user_id'] ?? 0), [
                    'access_token_enc'  => $this->crypto->encrypt((string)($tok['access_token'] ?? '')),
                    'refresh_token_enc' => $this->crypto->encrypt((string)($tok['refresh_token'] ?? '')),
                    'expires_at_utc'    => $expires_at_utc,
                    'scope'             => '',
                    'token_source'      => 'password_grant',
                ]);
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
            'status'               => 'linked',
            'last_error'           => '',
        ]);

        wp_send_json_success([
            'message'     => 'Account created. Logged in.',
            'redirect_to' => $redirect_to,
        ]);
    }

    public function ajax_logout() {
        wp_logout();
        wp_send_json_success(['message' => 'OK']);
    }
}
