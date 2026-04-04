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
        $status = self::get_token_status_for_current_user();
        return !empty($status['ok']) ? (string)($status['token'] ?? '') : null;
    }

    /**
     * Canonical token resolution contract for Stream and related write actions.
     *
     * Returns a small structured status so callers do not need to infer cause from
     * a null token or re-read mixed token tables.
     */
    public static function get_token_status_for_current_user(): array {
        if (!is_user_logged_in()) {
            return self::status('not_logged_in', '', 0, 'Login required.', false);
        }

        $wp_user_id = (int)get_current_user_id();
        $phpbb_user_id = IA_PT_Identity_Resolver::phpbb_id_from_wp($wp_user_id);
        if (!$phpbb_user_id) {
            $msg = 'No phpBB mapping found.';
            self::set_identity_error($wp_user_id, $msg);
            return self::status('identity_missing', '', 0, $msg, false);
        }

        // Canonical read path: per-user token store only.
        $row = IA_PeerTube_Token_Store::get($phpbb_user_id);
        if (!$row) {
            $row = self::maybe_adopt_legacy_row($phpbb_user_id);
        }
        if ($row && !empty($row['access_token_enc'])) {
            if (class_exists('IA_PeerTube_Token_Refresh') && method_exists('IA_PeerTube_Token_Refresh', 'maybe_refresh')) {
                $r = IA_PeerTube_Token_Refresh::maybe_refresh($phpbb_user_id, $row, 120);

                if (is_array($r) && empty($r['ok']) && (($r['code'] ?? '') === 'invalid_grant')) {
                    $res = IA_PT_PeerTube_Mint::try_mint($wp_user_id, $phpbb_user_id);
                    if (!empty($res['ok'])) {
                        IA_PeerTube_Token_Store::save_token_row(
                            $phpbb_user_id,
                            $res['access_enc'],
                            $res['refresh_enc'] ?? null,
                            $res['expires_at']
                        );
                        return self::status('valid_token', self::decrypt($res['access_enc']), $phpbb_user_id, '', true);
                    }

                    $err = (string)($res['error'] ?? 'Mint failed after invalid_grant refresh.');
                    self::set_identity_error($wp_user_id, $err);
                    IA_PeerTube_Token_Store::touch_mint_error($phpbb_user_id, $err);
                    $code = self::status_code_from_mint_error($err, 'password_required');
                    return self::status($code, '', $phpbb_user_id, $err, false);
                }

                $row2 = IA_PeerTube_Token_Store::get($phpbb_user_id);
                if ($row2 && !empty($row2['access_token_enc'])) {
                    return self::status('valid_token', self::decrypt($row2['access_token_enc']), $phpbb_user_id, '', true);
                }
            }
            return self::status('valid_token', self::decrypt($row['access_token_enc']), $phpbb_user_id, '', true);
        }

        // Lazy mint
        $res = IA_PT_PeerTube_Mint::try_mint($wp_user_id, $phpbb_user_id);
        if (empty($res['ok'])) {
            $err = (string)($res['error'] ?? 'Unable to mint PeerTube user token.');
            self::set_identity_error($wp_user_id, $err);
            IA_PeerTube_Token_Store::touch_mint_error($phpbb_user_id, $err);
            $code = self::status_code_from_mint_error($err, 'mint_failed');
            return self::status($code, '', $phpbb_user_id, $err, false);
        }

        IA_PeerTube_Token_Store::save_token_row(
            $phpbb_user_id,
            $res['access_enc'],
            $res['refresh_enc'] ?? null,
            $res['expires_at']
        );

        return self::status('valid_token', self::decrypt($res['access_enc']), $phpbb_user_id, '', true);
    }


    private static function maybe_adopt_legacy_row(int $phpbb_user_id): ?array {
        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb) || $phpbb_user_id <= 0) return null;

        $legacy_table = $wpdb->prefix . 'ia_peertube_tokens';
        $legacy = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT access_token_enc, refresh_token_enc, expires_at_utc FROM {$legacy_table} WHERE phpbb_user_id=%d LIMIT 1",
                $phpbb_user_id
            ),
            ARRAY_A
        );

        if (!is_array($legacy) || trim((string)($legacy['access_token_enc'] ?? '')) === '') {
            return null;
        }

        if (!IA_PeerTube_Token_Store::import_legacy_row($phpbb_user_id, $legacy)) {
            return null;
        }

        if (function_exists('ia_pt_trace_log')) {
            ia_pt_trace_log('ia-pt-token-helper.legacy_row_adopted', [
                'phpbb_user_id' => $phpbb_user_id,
                'source' => 'ia_peertube_tokens',
            ]);
        }

        return IA_PeerTube_Token_Store::get($phpbb_user_id);
    }

    private static function status(string $code, string $token, int $phpbb_user_id, string $error, bool $ok): array {
        return [
            'ok' => $ok,
            'code' => $code,
            'token' => $token,
            'phpbb_user_id' => $phpbb_user_id,
            'error' => $error,
            'token_source' => 'ia_peertube_user_tokens',
        ];
    }

    private static function status_code_from_mint_error(string $err, string $fallback = 'mint_failed'): string {
        $err_l = strtolower(trim($err));
        if ($err_l === '') return $fallback;
        if (strpos($err_l, 'password not available in this request') !== false) return 'password_required';
        if (strpos($err_l, 'no phpbb mapping found') !== false) return 'identity_missing';
        return $fallback;
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
