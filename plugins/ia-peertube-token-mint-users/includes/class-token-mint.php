<?php
if (!defined('ABSPATH')) exit;

class IA_PeerTube_Token_Mint {

    private static function api_base(): string {
        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'peertube_internal_base_url')) {
            return IA_Engine::peertube_internal_base_url();
        }
        return '';
    }

    private static function peertube_cfg(): array {
        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'peertube_api')) {
            return IA_Engine::peertube_api();
        }
        return [];
    }

    private static function admin_bearer(): string {
        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'peertube_api_token')) {
            return (string)IA_Engine::peertube_api_token();
        }
        return '';
    }

    private static function fetch_oauth_client(string $apiBase): array {
        // PeerTube local client endpoint (no auth on many installs, but we try without first).
        $url = rtrim($apiBase, '/') . '/api/v1/oauth-clients/local';
        $resp = wp_remote_get($url, ['timeout' => 15]);
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];
        $code = (int)wp_remote_retrieve_response_code($resp);
        $body = (string)wp_remote_retrieve_body($resp);
        if ($code < 200 || $code >= 300) return ['ok' => false, 'message' => 'OAuth client fetch failed (HTTP ' . $code . ').'];
        $json = json_decode($body, true);
        if (!is_array($json) || empty($json['client_id']) || empty($json['client_secret'])) {
            return ['ok' => false, 'message' => 'OAuth client fetch returned invalid JSON.'];
        }
        return ['ok' => true, 'client_id' => (string)$json['client_id'], 'client_secret' => (string)$json['client_secret']];
    }

    private static function grant_password(string $apiBase, string $clientId, string $clientSecret, string $username, string $password): array {
        $url = rtrim($apiBase, '/') . '/users/token';
        $resp = wp_remote_post($url, [
            'timeout' => 15,
            'body' => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'password',
                'username' => $username,
                'password' => $password,
            ],
        ]);
        if (is_wp_error($resp)) {
            return ['ok' => false, 'message' => 'Password grant request failed: ' . $resp->get_error_message()];
        }
        $code = (int)wp_remote_retrieve_response_code($resp);
        $body = (string)wp_remote_retrieve_body($resp);
        if ($code < 200 || $code >= 300) {
            $snippet = trim(preg_replace('/\s+/', ' ', $body));
            if (strlen($snippet) > 400) $snippet = substr($snippet, 0, 400) . '…';
            return ['ok' => false, 'message' => 'Password grant failed (HTTP ' . $code . '): ' . $snippet];
        }
        $json = json_decode($body, true);
        if (!is_array($json) || empty($json['access_token'])) return ['ok' => false, 'message' => 'Password grant returned invalid JSON.'];
        return ['ok' => true, 'json' => $json];
    }

    private static function admin_find_user(string $apiBase, string $search): ?array {
        $bearer = self::admin_bearer();
        if ($bearer === '') return null;

        // Search can return partial matches; we select an exact username match if present.
        $url = rtrim($apiBase, '/') . '/api/v1/users?search=' . rawurlencode($search) . '&count=10&start=0';
        $resp = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Bearer ' . $bearer],
        ]);
        if (is_wp_error($resp)) return null;
        $code = (int)wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) return null;
        $json = json_decode((string)wp_remote_retrieve_body($resp), true);
        if (!is_array($json) || empty($json['data']) || !is_array($json['data'])) return null;

        foreach ($json['data'] as $u) {
            if (is_array($u) && isset($u['username']) && (string)$u['username'] === (string)$search) {
                return $u;
            }
        }
        return null;
    }

    private static function admin_get_user_by_id(string $apiBase, int $userId): ?array {
        $bearer = self::admin_bearer();
        if ($bearer === '') return null;

        $url = rtrim($apiBase, '/') . '/api/v1/users/' . intval($userId);
        $resp = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Bearer ' . $bearer],
        ]);
        if (is_wp_error($resp)) return null;
        $code = (int)wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) return null;
        $json = json_decode((string)wp_remote_retrieve_body($resp), true);
        return is_array($json) ? $json : null;
    }

    private static function admin_create_user(string $apiBase, string $username, string $email, string $password): ?array {
        $bearer = self::admin_bearer();
        if ($bearer === '') return null;

        $url = rtrim($apiBase, '/') . '/api/v1/users';
        $payload = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'videoQuota' => 0,
        ];
        $resp = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);
        if (is_wp_error($resp)) return null;
        $code = (int)wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) return null;
        $json = json_decode((string)wp_remote_retrieve_body($resp), true);
        return is_array($json) ? $json : null;
    }

    private static function admin_set_password(string $apiBase, int $userId, string $password): bool {
        $bearer = self::admin_bearer();
        if ($bearer === '') return false;

        // PeerTube admin update endpoint: /api/v1/users/{id}
        $url = rtrim($apiBase, '/') . '/api/v1/users/' . intval($userId);
        $resp = wp_remote_request($url, [
            'method' => 'PUT',
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['password' => $password]),
        ]);
        if (is_wp_error($resp)) return false;
        $code = (int)wp_remote_retrieve_response_code($resp);
        return ($code >= 200 && $code < 300);
    }

    private static function encrypt(string $plain): string {
        if (class_exists('IA_Engine_Crypto') && method_exists('IA_Engine_Crypto', 'encrypt')) {
            return (string)IA_Engine_Crypto::encrypt($plain);
        }
        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'encrypt')) {
            return (string)IA_Engine::encrypt($plain);
        }
        return $plain; // fallback (not ideal) — but avoids fatal if crypto unavailable
    }

    public static function mint_and_store_for_wp_user(int $wp_user_id, string $password): array {
        $wp_user = get_user_by('id', $wp_user_id);
        if (!$wp_user) return ['ok' => false, 'message' => 'WP user not found.'];

        $ident = IA_PT_Identity_Resolver::identity_from_wp($wp_user_id);
        if (!$ident || empty($ident['phpbb_user_id'])) {
            return ['ok' => false, 'message' => 'No phpBB mapping for this user.'];
        }
        $phpbb_id = (int)$ident['phpbb_user_id'];

        $apiBase = self::api_base();
        if ($apiBase === '') return ['ok' => false, 'message' => 'PeerTube API base URL not configured.'];

        $cfg = self::peertube_cfg();
        $clientId = (string)($cfg['oauth_client_id'] ?? '');
        $clientSecret = (string)($cfg['oauth_client_secret'] ?? '');

        if ($clientId === '' || $clientSecret === '') {
            $c = self::fetch_oauth_client($apiBase);
            if (!$c['ok']) return ['ok' => false, 'message' => $c['message']];
            $clientId = $c['client_id'];
            $clientSecret = $c['client_secret'];
        }

        // IMPORTANT: do not fall back to WP email here.
        // In Atrium's retro-user case, email can collide and cause minting the wrong PeerTube identity.
        $username = (string)$wp_user->user_login;
        $candidates = array_values(array_filter([$username]));

        $tokenJson = null;
        $lastGrantMsg = null;
        $ptUserId = null;

        // First attempt: password grant for username only.
        foreach ($candidates as $cand) {
            $g = self::grant_password($apiBase, $clientId, $clientSecret, $cand, $password);
            if ($g['ok']) { $tokenJson = $g['json']; break; }
            $lastGrantMsg = (string)($g['message'] ?? 'Password grant failed.');
        }

        if (!$tokenJson) {
            // Provision/repair: ensure PeerTube user exists and password matches, then retry once.
            $bearer = self::admin_bearer();
            if ($bearer === '') {
                IA_PeerTube_Token_Store::touch_mint_error($phpbb_id, 'PeerTube admin bearer token missing (ia-engine peertube_api_token).');
                return ['ok' => false, 'message' => 'PeerTube admin bearer token missing.'];
            }

            // 1) If identity map already has a PeerTube user id, verify it matches the expected username.
            $mappedId = isset($ident['peertube_user_id']) ? (int)$ident['peertube_user_id'] : 0;
            if ($mappedId > 0) {
                $u = self::admin_get_user_by_id($apiBase, $mappedId);
                if (is_array($u) && isset($u['username']) && (string)$u['username'] === $username) {
                    $ptUserId = $mappedId;
                } else {
                    // Mismatch (common in legacy mappings): ignore it and proceed to create/find correct user.
                    $ptUserId = null;
                }
            }

            // 2) Find exact username match.
            if (!$ptUserId) {
                $found = self::admin_find_user($apiBase, $username);
                if ($found && !empty($found['id'])) {
                    $ptUserId = (int)$found['id'];
                }
            }

            // 3) Create if missing.
            if (!$ptUserId) {
                $email = (string)$wp_user->user_email;
                if ($email === '') {
                    $email = $username . '+' . $phpbb_id . '@example.invalid';
                }
                $created = self::admin_create_user($apiBase, $username, $email, $password);

                if (is_array($created)) {
                    // PeerTube can return { user: { id: ... } } or { id: ... } depending on version.
                    if (isset($created['user']['id'])) $ptUserId = (int)$created['user']['id'];
                    elseif (isset($created['id'])) $ptUserId = (int)$created['id'];
                }

                // If create failed due to email collision, retry with a synthetic unique email.
                if (!$ptUserId) {
                    $email2 = $username . '+' . $phpbb_id . '@example.invalid';
                    $created2 = self::admin_create_user($apiBase, $username, $email2, $password);
                    if (is_array($created2)) {
                        if (isset($created2['user']['id'])) $ptUserId = (int)$created2['user']['id'];
                        elseif (isset($created2['id'])) $ptUserId = (int)$created2['id'];
                    }
                }
            }

            if ($ptUserId) {
                // Ensure password is set to the provided password (admin action).
                self::admin_set_password($apiBase, $ptUserId, $password);

                // Update identity map to the verified/correct PeerTube user id.
                global $wpdb;
                $wpdb->update(IA_PT_IDENTITY_TABLE, [
                    'peertube_user_id' => $ptUserId,
                    'status' => 'linked',
                ], ['wp_user_id' => $wp_user_id], ['%d','%s'], ['%d']);
            }

            // Retry password grant (username only).
            foreach ($candidates as $cand) {
                $g = self::grant_password($apiBase, $clientId, $clientSecret, $cand, $password);
                if ($g['ok']) { $tokenJson = $g['json']; break; }
                $lastGrantMsg = (string)($g['message'] ?? 'Password grant failed.');
            }
        }

        if (!$tokenJson) {
            $msg = 'Unable to mint PeerTube user token (password grant).';
            if ($lastGrantMsg) $msg .= ' Last error: ' . $lastGrantMsg;
            IA_PeerTube_Token_Store::touch_mint_error($phpbb_id, $msg);
            return ['ok' => false, 'message' => $msg];
        }

        $access = (string)$tokenJson['access_token'];
        $refresh = (string)($tokenJson['refresh_token'] ?? '');
        $expiresIn = (int)($tokenJson['expires_in'] ?? 0);
        $expiresAt = $expiresIn > 0 ? gmdate('Y-m-d H:i:s', time() + $expiresIn) : null;

        $accessEnc = self::encrypt($access);
        $refreshEnc = self::encrypt($refresh);

        if (!IA_PeerTube_Token_Store::save($phpbb_id, $ptUserId, $accessEnc, $refreshEnc, $expiresAt)) {
            return ['ok' => false, 'message' => 'Failed to persist token row.'];
        }

        // Read-back verify
        $row = IA_PeerTube_Token_Store::get($phpbb_id);
        if (!$row || empty($row['access_token_enc'])) {
            return ['ok' => false, 'message' => 'Token row write verification failed.'];
        }

        return ['ok' => true, 'phpbb_user_id' => $phpbb_id, 'expires_at' => $expiresAt];
    }
}
