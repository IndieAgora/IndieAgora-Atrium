<?php
/**
 * Plugin Name: IA User PeerTube Fallback (Clean)
 * Description: phpBB first, then PeerTube fallback for IA User modal login. On PeerTube success, auto-create/link phpBB + WP shadow + identity map and log in.
 * Version: 0.2.5
 * Author: IndieAgora
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('ia_pt_trace_log')) {
    function ia_pt_trace_log(string $channel, array $context = []): void {
        if (!(defined('WP_DEBUG_LOG') && WP_DEBUG_LOG)) return;
        static $reqid = null;
        if ($reqid === null) {
            $seed = (string)($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)) . '|' . (string)($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string)($_SERVER['REQUEST_URI'] ?? '');
            $reqid = substr(md5($seed), 0, 12);
        }
        $bits = ['req=' . $reqid, 'ch=' . $channel];
        foreach ($context as $k => $v) {
            if (is_bool($v)) $v = $v ? '1' : '0';
            elseif (is_array($v)) $v = wp_json_encode($v);
            elseif ($v === null) $v = 'null';
            $v = preg_replace('/\s+/', ' ', (string)$v);
            if (strlen($v) > 240) $v = substr($v, 0, 240) . '…';
            $bits[] = $k . '=' . $v;
        }
        error_log('[IA_PT_TOKEN_TRACE] ' . implode(' | ', $bits));
    }
}


final class IA_User_PeerTube_Fallback_Clean {

    public static function boot(): void {
        add_action('plugins_loaded', [__CLASS__, 'swap_login_handler'], 60);
    }

    public static function swap_login_handler(): void {
        if (!class_exists('IA_User')) return;
        if (!class_exists('IA_Auth')) return;
        if (!class_exists('IA_PTLS')) return;

        $iu = IA_User::instance();

        remove_action('wp_ajax_ia_user_login', [$iu, 'ajax_login']);
        remove_action('wp_ajax_nopriv_ia_user_login', [$iu, 'ajax_login']);

        add_action('wp_ajax_ia_user_login', [__CLASS__, 'ajax_login']);
        add_action('wp_ajax_nopriv_ia_user_login', [__CLASS__, 'ajax_login']);
    }

    private static function debug_log(string $msg): void {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[IA_User_PeerTube_Fallback_Clean] ' . $msg);
        }
    }

    /**
     * Try to discover nonce action(s) used by the active modal.
     * We validate what the UI sends; we do not change UI/JS.
     */
    private static function detect_nonce_actions(): array {
        $actions = [];

        // Common candidates
        $actions[] = 'ia_user_nonce';
        $actions[] = 'ia_auth_nonce';
        $actions[] = 'ia_nonce';
        $actions[] = 'ia_user_login';
        $actions[] = 'ia_ptls_login_nonce';

        if (class_exists('IA_User')) {
            try {
                $iu = IA_User::instance();

                foreach (['nonce_action', 'nonce', 'nonce_key', 'nonceName', 'nonce_action_name'] as $prop) {
                    if (is_object($iu) && property_exists($iu, $prop)) {
                        $val = (string)($iu->$prop ?? '');
                        if ($val !== '') $actions[] = $val;
                    }
                }

                foreach (['nonce_action', 'get_nonce_action', 'nonce_key', 'get_nonce_key'] as $meth) {
                    if (is_object($iu) && method_exists($iu, $meth)) {
                        $val = (string)$iu->$meth();
                        if ($val !== '') $actions[] = $val;
                    }
                }

                foreach (['IA_USER_NONCE_ACTION', 'IA_USER_NONCE', 'IA_NONCE_ACTION'] as $const) {
                    if (defined($const)) {
                        $val = (string)constant($const);
                        if ($val !== '') $actions[] = $val;
                    }
                }
            } catch (\Throwable $e) {
                self::debug_log('Nonce introspection error: ' . $e->getMessage());
            }
        }

        $actions = array_values(array_unique(array_filter($actions, function($v){
            return is_string($v) && trim($v) !== '';
        })));

        return $actions;
    }

    private static function guard_nonce(): void {
        $nonce =
            (string)($_POST['nonce'] ?? '') ?:
            (string)($_POST['_wpnonce'] ?? '') ?:
            (string)($_POST['_ajax_nonce'] ?? '');

        if ($nonce === '') {
            wp_send_json_error(['message' => 'Invalid session. Refresh and try again.'], 403);
        }

        $actions = self::detect_nonce_actions();

        foreach ($actions as $action) {
            if (wp_verify_nonce($nonce, $action)) {
                return;
            }
        }

        self::debug_log('Nonce failed. nonce=' . $nonce . ' tried_actions=' . implode(',', $actions));
        wp_send_json_error(['message' => 'Invalid session. Refresh and try again.'], 403);
    }

    /**
     * Store PeerTube token when we already have one, or fall back cautiously to any mint helper
     * supported by the live IA Auth DB implementation.
     */
    private static function maybe_store_peertube_token($ia, string $identifier, string $password, int $phpbb_uid, ?array $token = null): void {
        try {
            if (!is_object($ia) || !isset($ia->db) || !is_object($ia->db) || $phpbb_uid <= 0) {
                self::debug_log('Token store skipped (missing IA Auth DB or invalid phpBB uid).');
                return;
            }

            if (is_array($token)
                && !empty($token['access_token'])
                && isset($ia->crypto)
                && is_object($ia->crypto)
                && method_exists($ia->crypto, 'encrypt')
                && method_exists($ia->db, 'store_peertube_token')) {
                $expires_in = (int)($token['expires_in'] ?? 0);
                $expires_at_utc = $expires_in > 0 ? gmdate('Y-m-d H:i:s', time() + $expires_in) : null;
                $access_plain  = (string)($token['access_token'] ?? '');
                $refresh_plain = (string)($token['refresh_token'] ?? '');
                $access_enc    = (string)$ia->crypto->encrypt($access_plain);
                $refresh_enc   = (string)$ia->crypto->encrypt($refresh_plain);

                if ($access_plain !== '' && $access_enc !== '') {
                    $ok = (bool)$ia->db->store_peertube_token((int)$phpbb_uid, [
                        'access_token_enc'  => $access_enc,
                        'refresh_token_enc' => $refresh_enc,
                        'expires_at_utc'    => $expires_at_utc,
                        'scope'             => '',
                        'token_source'      => 'password_grant',
                    ]);
                    self::debug_log('Token store direct path result=' . ($ok ? 'ok' : 'fail') . ' phpbb_uid=' . (int)$phpbb_uid);
                    return;
                }
            }

            foreach ([
                'maybe_mint_peertube_token',
                'mint_peertube_token',
                'upsert_peertube_token',
                'ensure_peertube_token',
            ] as $meth) {
                if (method_exists($ia->db, $meth)) {
                    $ia->db->$meth($identifier, $password, (int)$phpbb_uid);
                    self::debug_log('Token store helper path=' . $meth . ' phpbb_uid=' . (int)$phpbb_uid);
                    return;
                }
            }
        } catch (\Throwable $e) {
            self::debug_log('Token store skipped (exception): ' . $e->getMessage());
            return;
        }

        self::debug_log('Token store skipped (no supported IA_Auth_DB method).');
    }

    private static function rate_limit_key(string $identifier): string {
        return 'ia_uptfc_pt429_' . md5(strtolower(trim($identifier)));
    }

    private static function is_rate_limited(string $identifier): bool {
        return (bool) get_transient(self::rate_limit_key($identifier));
    }

    private static function mark_rate_limited(string $identifier): void {
        set_transient(self::rate_limit_key($identifier), 1, 5 * MINUTE_IN_SECONDS);
    }

    private static function maybe_wp_fallback_login(string $identifier, string $password, string $redirect_to): void {
        $login = $identifier;
        if (is_email($identifier)) {
            $u = get_user_by('email', $identifier);
            if ($u && !is_wp_error($u)) {
                $login = (string) $u->user_login;
            }
        }

        $user = wp_signon([
            'user_login'    => $login,
            'user_password' => $password,
            'remember'      => true,
        ], is_ssl());

        if (is_wp_error($user) || empty($user->ID)) {
            return;
        }

        $wp_uid = (int) $user->ID;
        $verified = (string) get_user_meta($wp_uid, 'ia_email_verified', true);
        if ($verified !== '' && $verified !== '1') {
            wp_send_json_error(['message' => 'Please verify your email to activate this account.'], 403);
        }

        wp_set_current_user($wp_uid);
        wp_set_auth_cookie($wp_uid, true);

        do_action('ia_pt_user_password', $wp_uid, $password, $identifier);

        wp_send_json_success([
            'message'     => 'OK',
            'redirect_to' => $redirect_to,
        ]);
    }

    private static function try_peertube_password_grant($ia, array $engine, string $identifier, string $password): array {
        ia_pt_trace_log('ia-user-fallback.password_grant.attempt', ['identifier' => $identifier, 'ajax_action' => (string)($_REQUEST['action'] ?? '')]);
        $pt = $ia->peertube->password_grant($identifier, $password, $engine['peertube_api']);
        if (!empty($pt['ok']) && !empty($pt['token']['access_token'])) {
            return $pt;
        }

        $http = (int) (($pt['debug']['code'] ?? 0));
        if ($http === 429) {
            self::mark_rate_limited($identifier);
        }

        return $pt;
    }

    public static function ajax_login(): void {
        ia_pt_trace_log('ia-user-fallback.ajax_login.enter', [
            'identifier' => (string)($_POST['identifier'] ?? ''),
            'uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
        ]);
        self::guard_nonce();

        $identifier  = trim((string)($_POST['identifier'] ?? ''));
        $password    = (string)($_POST['password'] ?? '');
        $redirect_to = !empty($_POST['redirect_to'])
            ? esc_url_raw((string)$_POST['redirect_to'])
            : home_url('/');

        if ($identifier === '' || $password === '') {
            wp_send_json_error(['message' => 'Missing username/email or password.'], 400);
        }
        if (function_exists('ia_goodbye_identifier_is_tombstoned') && ia_goodbye_identifier_is_tombstoned($identifier)) {
            wp_send_json_error(['message' => 'This account was deleted. Please register again with different credentials.'], 403);
        }
        if (self::is_rate_limited($identifier)) {
            wp_send_json_error(['message' => 'Too many login attempts reached the PeerTube token limit. Wait 5 minutes and try once.'], 429);
        }

        $ia     = IA_Auth::instance();
        $ptls   = IA_PTLS::instance();
        $engine = IA_Auth::engine_normalized();

        /**
         * 1) Try phpBB first
         */
        $auth = $ia->phpbb->authenticate($identifier, $password, $engine['phpbb']);

        if (!empty($auth['ok']) && !empty($auth['user'])) {
            $phpbb_user = (array)$auth['user'];
            $phpbb_uid  = (int)($phpbb_user['user_id'] ?? 0);

            $wp_uid = (int)$ia->db->ensure_wp_shadow_user($phpbb_user, $ia->options());
            if ($wp_uid <= 0) {
                wp_send_json_error(['message' => 'Could not complete sign-in.'], 500);
            }

            wp_set_current_user($wp_uid);
            wp_set_auth_cookie($wp_uid, true);

            $ia->db->upsert_identity([
                'phpbb_user_id'        => $phpbb_uid,
                'phpbb_username_clean' => (string)($phpbb_user['username_clean'] ?? ''),
                'email'                => (string)($phpbb_user['user_email'] ?? ''),
                'wp_user_id'           => $wp_uid,
                'status'               => 'linked',
                'last_error'           => '',
            ]);

            // OPTIONAL: token store only if supported
            self::maybe_store_peertube_token($ia, $identifier, $password, $phpbb_uid, null);

            wp_send_json_success([
                'message'     => 'OK',
                'redirect_to' => $redirect_to,
            ]);
        }

        /**
         * 2) phpBB failed -> try native WP auth before PeerTube.
         * This preserves WordPress-backed accounts while this plugin owns ia_user_login.
         */
        self::maybe_wp_fallback_login($identifier, $password, $redirect_to);

        /**
         * 3) WP failed -> try PeerTube password grant.
         * Deterministic ladder: user-submitted identifier first, then at most one exact canonical-username retry.
         * Never spray many candidates because /users/token is rate-limited.
         */
        $pt = self::try_peertube_password_grant($ia, $engine, $identifier, $password);

        if ((empty($pt['ok']) || empty($pt['token']['access_token'])) && is_email($identifier) && !empty($engine['peertube_api']['token'])) {
            $found = $ia->peertube->admin_find_user($identifier, $engine['peertube_api']);
            $canonical = '';
            if (!empty($found['ok']) && !empty($found['user']) && is_array($found['user'])) {
                $candidate_email = strtolower((string) ($found['user']['email'] ?? ''));
                $candidate_user  = trim((string) ($found['user']['username'] ?? ''));
                if ($candidate_email === strtolower($identifier) && $candidate_user !== '' && strtolower($candidate_user) !== strtolower($identifier)) {
                    $canonical = $candidate_user;
                }
            }

            if ($canonical !== '') {
                self::debug_log('Retrying PeerTube password_grant with canonical username=' . $canonical . ' for email=' . $identifier);
                $pt = self::try_peertube_password_grant($ia, $engine, $canonical, $password);
                if (!empty($pt['ok']) && !empty($pt['token']['access_token'])) {
                    $identifier = $canonical;
                }
            }
        }

        if (empty($pt['ok']) || empty($pt['token']['access_token'])) {
            $http = (int) (($pt['debug']['code'] ?? 0));
            self::debug_log('PeerTube password_grant failed for identifier=' . $identifier . ' http=' . $http . ' message=' . (string)($pt['message'] ?? ''));
            if ($http === 429) {
                wp_send_json_error(['message' => 'PeerTube rate limited the login attempt. Wait 5 minutes and try once.'], 429);
            }
            wp_send_json_error(['message' => 'Invalid username/email or password.'], 403);
        }

        $access_token = (string)$pt['token']['access_token'];

        // /users/me (internal base preferred)
        $base = rtrim((string)($engine['peertube_api']['internal_base_url'] ?? ''), '/');
        if ($base === '') $base = rtrim((string)($engine['peertube_api']['base_url'] ?? ''), '/');

        if ($base === '') {
            self::debug_log('PeerTube base URL missing');
            wp_send_json_error(['message' => 'Login failed.'], 500);
        }

        $me_res = wp_remote_get($base . '/api/v1/users/me', [
            'timeout' => 12,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept'        => 'application/json',
                'User-Agent'    => 'IA-User-PeerTube-Fallback',
            ],
        ]);

        if (is_wp_error($me_res)) {
            self::debug_log('PeerTube /users/me WP_Error: ' . $me_res->get_error_message());
            wp_send_json_error(['message' => 'Login failed.'], 500);
        }

        $code = (int)wp_remote_retrieve_response_code($me_res);
        $body = (string)wp_remote_retrieve_body($me_res);
        $json = json_decode($body, true);

        if ($code < 200 || $code >= 300 || !is_array($json)) {
            self::debug_log('PeerTube /users/me failed HTTP ' . $code . ' body=' . substr($body, 0, 200));
            wp_send_json_error(['message' => 'Login failed.'], 500);
        }

        $pt_username = trim((string)($json['username'] ?? ''));
        $pt_email    = trim((string)($json['email'] ?? ''));
        $pt_id       = (int)($json['id'] ?? 0);

        if ($pt_username === '' && !empty($json['account']) && is_array($json['account'])) {
            $pt_username = trim((string)($json['account']['name'] ?? ''));
        }
        if ($pt_email === '' && !empty($json['account']) && is_array($json['account'])) {
            $pt_email = trim((string)($json['account']['email'] ?? ''));
        }

        if ($pt_username === '' || $pt_email === '') {
            self::debug_log('PeerTube identity incomplete: ' . substr($body, 0, 200));
            wp_send_json_error(['message' => 'Login failed.'], 500);
        }
        if (function_exists('ia_goodbye_identifier_is_tombstoned')
            && (ia_goodbye_identifier_is_tombstoned($pt_username) || ia_goodbye_identifier_is_tombstoned($pt_email))) {
            wp_send_json_error(['message' => 'This account was deleted. Please register again with different credentials.'], 403);
        }

        /**
         * 3) Ensure canonical phpBB user exists
         */
        $phpbb_res = $ptls->phpbb_find_or_create_user($pt_username, $pt_email);

        if (empty($phpbb_res['ok']) || empty($phpbb_res['user'])) {
            self::debug_log('phpbb_find_or_create_user failed for ' . $pt_username . ' / ' . $pt_email);
            wp_send_json_error(['message' => 'Login failed.'], 500);
        }

        $phpbb_user = (array)$phpbb_res['user'];
        $phpbb_uid  = (int)($phpbb_user['user_id'] ?? 0);

        /**
         * 4) Ensure WP shadow + login
         */
        $ptls->ensure_wp_shadow_user($phpbb_user, $pt_id);

        $wp_uid = (int)$ia->db->ensure_wp_shadow_user($phpbb_user, $ia->options());
        if ($wp_uid <= 0) {
            wp_send_json_error(['message' => 'Could not complete sign-in.'], 500);
        }

        wp_set_current_user($wp_uid);
        wp_set_auth_cookie($wp_uid, true);

        $ia->db->upsert_identity([
            'phpbb_user_id'        => $phpbb_uid,
            'phpbb_username_clean' => (string)($phpbb_user['username_clean'] ?? ''),
            'email'                => (string)($phpbb_user['user_email'] ?? $pt_email),
            'wp_user_id'           => $wp_uid,
            'status'               => 'linked',
            'last_error'           => '',
        ]);

        // OPTIONAL: token store only if supported
        self::maybe_store_peertube_token($ia, $pt_username, $password, $phpbb_uid, (array)($pt['token'] ?? null));

        wp_send_json_success([
            'message'     => 'OK',
            'redirect_to' => $redirect_to,
        ]);
    }
}

IA_User_PeerTube_Fallback_Clean::boot();
