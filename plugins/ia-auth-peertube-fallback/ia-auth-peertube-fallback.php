<?php
/**
 * Plugin Name: IA Auth PeerTube Fallback
 * Description: Adds transparent PeerTube credential fallback to IA Auth login (phpBB first, then PeerTube; auto-create/link phpBB + WP shadow).
 * Version: 0.1.0
 * Author: IndieAgora
 */

if (!defined('ABSPATH')) { exit; }

final class IA_Auth_PeerTube_Fallback {

    public static function boot() : void {
        // Ensure IA Auth is loaded first.
        add_action('plugins_loaded', [__CLASS__, 'swap_login_handler'], 30);
    }

    public static function swap_login_handler() : void {
        if (!class_exists('IA_Auth')) return;

        $ia = IA_Auth::instance();

        // Remove IA Auth's original AJAX login handler and replace it with ours.
        // IA Auth registers these in its constructor/bootstrap.
        remove_action('wp_ajax_ia_auth_login', [$ia, 'ajax_login']);
        remove_action('wp_ajax_nopriv_ia_auth_login', [$ia, 'ajax_login']);

        add_action('wp_ajax_ia_auth_login', [__CLASS__, 'ajax_login']);
        add_action('wp_ajax_nopriv_ia_auth_login', [__CLASS__, 'ajax_login']);
    }

    private static function guard_ajax_nonce() : void {
        $nonce = (string)($_POST['nonce'] ?? '');
        if ($nonce === '' || !wp_verify_nonce($nonce, 'ia_auth_nonce')) {
            wp_send_json_error(['message' => 'Invalid session. Refresh and try again.'], 403);
        }
    }

    private static function peertube_me(string $access_token, array $engine_peertube_api) : array {
        $base = rtrim((string)($engine_peertube_api['internal_base_url'] ?? ''), '/');
        if ($base === '') {
            return ['ok' => false, 'message' => 'PeerTube API base URL not configured.'];
        }

        $url = $base . '/api/v1/users/me';
        $res = wp_remote_get($url, [
            'timeout' => 12,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept'        => 'application/json',
                'User-Agent'    => 'IA-Auth-PeerTube-Fallback',
            ],
        ]);

        if (is_wp_error($res)) {
            return ['ok' => false, 'message' => $res->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);

        if ($code < 200 || $code >= 300) {
            return ['ok' => false, 'message' => 'PeerTube rejected token (me).'];
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return ['ok' => false, 'message' => 'PeerTube returned invalid JSON (me).'];
        }

        return ['ok' => true, 'me' => $json];
    }

    public static function ajax_login() : void {
        self::guard_ajax_nonce();

        if (!class_exists('IA_Auth')) {
            wp_send_json_error(['message' => 'Authentication service unavailable.'], 503);
        }

        $ia = IA_Auth::instance();

        $id = trim((string)($_POST['identifier'] ?? ''));
        $pw = (string)($_POST['password'] ?? '');
        $redirect_to = !empty($_POST['redirect_to']) ? esc_url_raw((string)$_POST['redirect_to']) : home_url('/');

        if ($id === '' || $pw === '') {
            wp_send_json_error(['message' => 'Missing username/password.'], 400);
        }

        $engine = IA_Auth::engine_normalized();

        // 1) Try phpBB first (existing behaviour)
        $auth = $ia->phpbb->authenticate($id, $pw, $engine['phpbb']);

        if (empty($auth['ok'])) {
            // 2) If phpBB fails, transparently try PeerTube password grant
            $pt = $ia->peertube->password_grant($id, $pw, $engine['peertube_api']);
            if (empty($pt['ok']) || empty($pt['token']['access_token'])) {
                // Preserve IA Auth semantics: invalid creds
                wp_send_json_error(['message' => 'Invalid username/email or password.'], 401);
            }

            $access_token = (string)$pt['token']['access_token'];

            // Get PeerTube user identity
            $me = self::peertube_me($access_token, $engine['peertube_api']);
            if (empty($me['ok'])) {
                wp_send_json_error(['message' => $me['message'] ?? 'PeerTube identity lookup failed.'], 401);
            }

            $me_json = (array)($me['me'] ?? []);

            // PeerTube typically returns:
            // - username
            // - email
            $pt_username = trim((string)($me_json['username'] ?? ''));
            $pt_email    = trim((string)($me_json['email'] ?? ''));

            if ($pt_username === '') {
                // Some PeerTube builds may nest username under "account"
                if (!empty($me_json['account']) && is_array($me_json['account'])) {
                    $pt_username = trim((string)($me_json['account']['name'] ?? ''));
                    if ($pt_username === '') {
                        $pt_username = trim((string)($me_json['account']['displayName'] ?? ''));
                    }
                }
            }

            if ($pt_username === '') {
                wp_send_json_error(['message' => 'PeerTube user record missing username.'], 401);
            }

            // 3) Ensure phpBB user exists (create if missing)
            $phpbb_user = $ia->phpbb->find_user($pt_username, $engine['phpbb']);

            if (!$phpbb_user && $pt_email !== '') {
                // Try by email as a fallback
                $phpbb_user = $ia->phpbb->find_user($pt_email, $engine['phpbb']);
            }

            if (!$phpbb_user) {
                // Create phpBB user using the SAME password they used for PeerTube
                $created = $ia->phpbb->create_user($pt_username, ($pt_email !== '' ? $pt_email : ($pt_username . '@invalid.local')), $pw, $engine['phpbb']);
                if (empty($created['ok']) || empty($created['user'])) {
                    wp_send_json_error(['message' => $created['message'] ?? 'Could not create phpBB user.'], 500);
                }
                $phpbb_user = $created['user'];
            }

            // Continue with IA Auth’s normal WP shadow login flow
            $wp_user_id = $ia->db->ensure_wp_shadow_user($phpbb_user, $ia->options());
            if (!$wp_user_id) {
                wp_send_json_error(['message' => 'Could not create WP shadow user.'], 500);
            }

            wp_set_current_user((int)$wp_user_id);
            wp_set_auth_cookie((int)$wp_user_id, true);

            $ia->db->upsert_identity([
                'phpbb_user_id'        => (int)($phpbb_user['user_id'] ?? 0),
                'phpbb_username_clean' => (string)($phpbb_user['username_clean'] ?? ''),
                'email'                => (string)($phpbb_user['user_email'] ?? ''),
                'wp_user_id'           => (int)$wp_user_id,
                'status'               => 'linked',
                'last_error'           => '',
            ]);

            // Store/mint PeerTube token using IA Auth DB helper (keeps Stream behaviour consistent)
            // Use the PeerTube username (most reliable for password grant)
            $ia->db->maybe_mint_peertube_token($pt_username, $pw, (int)($phpbb_user['user_id'] ?? 0));

            wp_send_json_success([
                'message'     => 'OK',
                'redirect_to' => $redirect_to,
            ]);
        }

        // phpBB auth succeeded: replicate IA Auth’s original success path
        $phpbb_user = $auth['user'];

        $wp_user_id = $ia->db->ensure_wp_shadow_user($phpbb_user, $ia->options());
        if (!$wp_user_id) {
            wp_send_json_error(['message' => 'Could not create WP shadow user.'], 500);
        }

        wp_set_current_user((int)$wp_user_id);
        wp_set_auth_cookie((int)$wp_user_id, true);

        $ia->db->upsert_identity([
            'phpbb_user_id'        => (int)($phpbb_user['user_id'] ?? 0),
            'phpbb_username_clean' => (string)($phpbb_user['username_clean'] ?? ''),
            'email'                => (string)($phpbb_user['user_email'] ?? ''),
            'wp_user_id'           => (int)$wp_user_id,
            'status'               => 'linked',
            'last_error'           => '',
        ]);

        // Preserve IA Auth token behaviour
        $ia->db->maybe_mint_peertube_token($id, $pw, (int)($phpbb_user['user_id'] ?? 0));

        wp_send_json_success([
            'message'     => 'OK',
            'redirect_to' => $redirect_to,
        ]);
    }
}

IA_Auth_PeerTube_Fallback::boot();
