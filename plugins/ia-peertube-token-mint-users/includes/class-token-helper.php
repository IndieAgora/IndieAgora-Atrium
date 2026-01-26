<?php
if (!defined('ABSPATH')) exit;

class IA_PeerTube_Token_Helper {

    public static function boot() {
        IA_PT_Password_Capture::boot();
    }

    /**
     * Lazily mint or return a PeerTube user token for the current user.
     * This is the ONLY mint trigger in Atrium.
     */
    public static function get_token_for_current_user(): ?string {
        if (!is_user_logged_in()) return null;

        $wp_user_id = (int)get_current_user_id();
        $phpbb_user_id = IA_PT_Identity_Resolver::phpbb_id_from_wp($wp_user_id);
        if (!$phpbb_user_id) {
            self::set_identity_error($wp_user_id, 'No phpBB mapping found.');
            return null;
        }

        // Existing token?
        $row = IA_PeerTube_Token_Store::get($phpbb_user_id);
        if ($row && !empty($row['access_token_enc'])) {
            // Automatic refresh (just-in-time) to prevent expired-token 401s on write actions.
            if (class_exists('IA_PeerTube_Token_Refresh') && method_exists('IA_PeerTube_Token_Refresh', 'maybe_refresh')) {
                $r = IA_PeerTube_Token_Refresh::maybe_refresh($phpbb_user_id, $row, 120);

                // If the refresh token is no longer valid (typical after ~2 weeks), fall back to the existing mint flow.
                // This keeps behaviour aligned with the original design: lazy mint, no cron.
                if (is_array($r) && empty($r['ok']) && (($r['code'] ?? '') === 'invalid_grant')) {
                    $res = IA_PT_PeerTube_Mint::try_mint($wp_user_id, $phpbb_user_id);
                    if (!empty($res['ok'])) {
                        IA_PeerTube_Token_Store::save_token_row(
                            $phpbb_user_id,
                            $res['access_enc'],
                            $res['refresh_enc'] ?? null,
                            $res['expires_at']
                        );
                        return self::decrypt($res['access_enc']);
                    }

                    // Mint failed: keep returning the old token so Stream yields a clear 401.
                    $err = (string)($res['error'] ?? 'Mint failed after invalid_grant refresh.');
                    self::set_identity_error($wp_user_id, $err);
                    IA_PeerTube_Token_Store::touch_mint_error($phpbb_user_id, $err);
                }

                // Re-read in case the access token was updated.
                $row2 = IA_PeerTube_Token_Store::get($phpbb_user_id);
                if ($row2 && !empty($row2['access_token_enc'])) {
                    return self::decrypt($row2['access_token_enc']);
                }
            }
            return self::decrypt($row['access_token_enc']);
        }

        // Lazy mint
        $res = IA_PT_PeerTube_Mint::try_mint($wp_user_id, $phpbb_user_id);
        if (!$res['ok']) {
            self::set_identity_error($wp_user_id, $res['error']);
            IA_PeerTube_Token_Store::touch_mint_error($phpbb_user_id, $res['error']);
            return null;
        }

        IA_PeerTube_Token_Store::save_token_row(
            $phpbb_user_id,
            $res['access_enc'],
            $res['refresh_enc'] ?? null,
            $res['expires_at']
        );

        return self::decrypt($res['access_enc']);
    }

    private static function decrypt(string $enc): ?string {
        if (class_exists('IA_Engine_Crypto') && method_exists('IA_Engine_Crypto', 'decrypt')) {
            return IA_Engine_Crypto::decrypt($enc);
        }
        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'decrypt')) {
            return IA_Engine::decrypt($enc);
        }
        return $enc;
    }

    private static function api_base(): string {
        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'peertube_public_base_url')) {
            try {
                $u = IA_Engine::peertube_public_base_url();
                $u = is_string($u) ? trim($u) : '';
                if ($u !== '') return rtrim($u, '/');
            } catch (Throwable $e) {
                // ignore
            }
        }
        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'peertube_internal_base_url')) {
            try {
                $u = IA_Engine::peertube_internal_base_url();
                $u = is_string($u) ? trim($u) : '';
                if ($u !== '') return rtrim($u, '/');
            } catch (Throwable $e) {
                // ignore
            }
        }
        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'peertube_api')) {
            try {
                $cfg = IA_Engine::peertube_api();
                if (is_array($cfg)) {
                    $u = trim((string)($cfg['public_url'] ?? ''));
                    if ($u !== '') return rtrim($u, '/');
                }
            } catch (Throwable $e) {
                // ignore
            }
        }
        return '';
    }

    /**
     * Validate a stored token row by calling /api/v1/users/me.
     * This is used for admin bulk checks.
     */
    public static function validate_access_token_row(array $row): array {
        $accessEnc = (string)($row['access_token_enc'] ?? '');
        if ($accessEnc === '') return ['ok' => false, 'message' => 'No access token stored.'];

        $access = self::decrypt($accessEnc);
        if (!is_string($access) || $access === '') return ['ok' => false, 'message' => 'Access token decrypt failed.'];

        $base = self::api_base();
        if ($base === '') return ['ok' => false, 'message' => 'PeerTube API base URL not configured.'];

        $url = rtrim($base, '/') . '/api/v1/users/me';
        $resp = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $access,
                'Accept' => 'application/json',
            ],
        ]);
        if (is_wp_error($resp)) {
            return ['ok' => false, 'message' => $resp->get_error_message()];
        }
        $code = (int)wp_remote_retrieve_response_code($resp);
        if ($code >= 200 && $code < 300) {
            return ['ok' => true];
        }
        $body = (string)wp_remote_retrieve_body($resp);
        $snippet = trim(preg_replace('/\s+/', ' ', $body));
        if (strlen($snippet) > 240) $snippet = substr($snippet, 0, 240) . '…';
        return ['ok' => false, 'message' => 'HTTP ' . $code . ($snippet ? (': ' . $snippet) : '')];
    }

    private static function set_identity_error(int $wp_user_id, string $msg): void {
        global $wpdb;
        $t = IA_PT_IDENTITY_TABLE;
        $wpdb->update($t, [
            'last_error' => $msg ? substr($msg, 0, 1000) : null,
            'updated_at' => current_time('mysql', 1),
        ], ['wp_user_id' => $wp_user_id]);
        if (defined('WP_DEBUG') && WP_DEBUG && $msg) {
            error_log('[ia-peertube-token-mint-users] '.$msg);
        }
    }
}
