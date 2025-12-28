<?php
/**
 * Plugin Name: IA User PeerTube Fallback (Clean)
 * Description: phpBB first, then PeerTube fallback for IA User modal login. On PeerTube success, auto-create/link phpBB + WP shadow + identity map and log in.
 * Version: 0.2.3
 * Author: IndieAgora
 */

if (!defined('ABSPATH')) exit;

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
     * Mint/store PeerTube token if IA Auth DB supports it.
     * (Your live IA_Auth_DB does NOT have maybe_mint_peertube_token, so we must be defensive.)
     */
    private static function maybe_store_peertube_token($ia, string $identifier, string $password, int $phpbb_uid): void {
        try {
            if (is_object($ia) && isset($ia->db) && is_object($ia->db)) {
                // Older/newer builds may use different method names. Try a few safely.
                foreach ([
                    'maybe_mint_peertube_token',
                    'mint_peertube_token',
                    'store_peertube_token',
                    'upsert_peertube_token',
                    'ensure_peertube_token',
                ] as $meth) {
                    if (method_exists($ia->db, $meth)) {
                        $ia->db->$meth($identifier, $password, $phpbb_uid);
                        return;
                    }
                }
            }
        } catch (\Throwable $e) {
            self::debug_log('Token store skipped (exception): ' . $e->getMessage());
        }

        // No supported method: skip silently (login still works).
        self::debug_log('Token store skipped (no supported IA_Auth_DB method).');
    }

    public static function ajax_login(): void {
        self::guard_nonce();

        $identifier  = trim((string)($_POST['identifier'] ?? ''));
        $password    = (string)($_POST['password'] ?? '');
        $redirect_to = !empty($_POST['redirect_to'])
            ? esc_url_raw((string)$_POST['redirect_to'])
            : home_url('/');

        if ($identifier === '' || $password === '') {
            wp_send_json_error(['message' => 'Missing username/email or password.'], 400);
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
                wp_send_json_error(['message' => 'Could not create WP shadow user.'], 500);
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
            self::maybe_store_peertube_token($ia, $identifier, $password, $phpbb_uid);

            wp_send_json_success([
                'message'     => 'OK',
                'redirect_to' => $redirect_to,
            ]);
        }

        /**
         * 2) phpBB failed -> try PeerTube password grant
         */
        $pt = $ia->peertube->password_grant($identifier, $password, $engine['peertube_api']);

        if (empty($pt['ok']) || empty($pt['token']['access_token'])) {
            self::debug_log('PeerTube password_grant failed for identifier=' . $identifier);
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
            wp_send_json_error(['message' => 'Could not create WP shadow user.'], 500);
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
        self::maybe_store_peertube_token($ia, $pt_username, $password, $phpbb_uid);

        wp_send_json_success([
            'message'     => 'OK',
            'redirect_to' => $redirect_to,
        ]);
    }
}

IA_User_PeerTube_Fallback_Clean::boot();
