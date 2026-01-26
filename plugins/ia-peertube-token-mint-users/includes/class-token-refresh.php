<?php
if (!defined('ABSPATH')) exit;

class IA_PeerTube_Token_Refresh {

    private static function api_base(): string {
        // IMPORTANT: user-token refresh must use a URL reachable from the WP host.
        // Prefer the public base URL (often different host), then fall back to internal.
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

        // Last resort: public_url from peertube_api config
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

    private static function peertube_cfg(): array {
        return (class_exists('IA_Engine') && method_exists('IA_Engine', 'peertube_api'))
            ? IA_Engine::peertube_api()
            : [];
    }

    private static function decrypt(string $enc): string {
        if (class_exists('IA_Engine_Crypto') && method_exists('IA_Engine_Crypto', 'decrypt')) {
            return (string)IA_Engine_Crypto::decrypt($enc);
        }
        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'decrypt')) {
            return (string)IA_Engine::decrypt($enc);
        }
        return $enc;
    }

    private static function encrypt(string $plain): string {
        if (class_exists('IA_Engine_Crypto') && method_exists('IA_Engine_Crypto', 'encrypt')) {
            return (string)IA_Engine_Crypto::encrypt($plain);
        }
        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'encrypt')) {
            return (string)IA_Engine::encrypt($plain);
        }
        return $plain;
    }

    // Returns: ok(bool), did_refresh(bool), message(string), code?(string)
    public static function maybe_refresh(int $phpbb_user_id, array $row, int $skewSeconds = 120): array {
        $expiresAt = (string)($row['expires_at'] ?? '');
        if ($expiresAt === '') {
            // Some legacy rows didn’t persist expires_at. In that case, treat the token as expiring on a sane default TTL
            // from the last refresh/mint timestamp (PeerTube access tokens are typically ~1 hour).
            $defaultTtl = 3600;
            $anchor = (string)($row['last_refresh_at'] ?? ($row['last_mint_at'] ?? ($row['updated_at'] ?? '')));
            $anchorTs = $anchor !== '' ? strtotime($anchor . ' UTC') : 0;
            if ($anchorTs > 0 && $anchorTs > (time() - ($defaultTtl - $skewSeconds))) {
                return ['ok' => true, 'did_refresh' => false, 'message' => 'Not expired (legacy TTL).'];
            }
            // If we have a refresh token, attempt refresh; otherwise, we can’t improve the situation.
            // (If no refresh token exists, callers will still receive 401 and should re-mint.)
        }

        $expTs = $expiresAt !== '' ? strtotime($expiresAt . ' UTC') : 0;
        if ($expiresAt !== '' && !$expTs) return ['ok' => true, 'did_refresh' => false, 'message' => 'Expiry parse failed.'];

        if ($expTs > (time() + $skewSeconds)) {
            return ['ok' => true, 'did_refresh' => false, 'message' => 'Not expired.'];
        }

        $refreshEnc = (string)($row['refresh_token_enc'] ?? '');
        if ($refreshEnc === '') return ['ok' => false, 'did_refresh' => false, 'message' => 'No refresh token stored.'];

        $refresh = self::decrypt($refreshEnc);
        if ($refresh === '') return ['ok' => false, 'did_refresh' => false, 'message' => 'Refresh token decrypt failed.'];

        $apiBase = self::api_base();
        if ($apiBase === '') return ['ok' => false, 'did_refresh' => false, 'message' => 'PeerTube API base URL not configured.'];

        $cfg = self::peertube_cfg();
        // Support both canonical keys and legacy keys.
        $clientId = (string)($cfg['oauth_client_id'] ?? ($cfg['client_id'] ?? ''));
        $clientSecret = (string)($cfg['oauth_client_secret'] ?? ($cfg['client_secret'] ?? ''));
        if ($clientId === '' || $clientSecret === '') {
            $msg = 'OAuth client missing in ia-engine config.';
            if (class_exists('IA_PeerTube_Token_Store') && method_exists('IA_PeerTube_Token_Store', 'touch_mint_error')) {
                IA_PeerTube_Token_Store::touch_mint_error($phpbb_user_id, 'refresh: ' . $msg);
            }
            return ['ok' => false, 'did_refresh' => false, 'message' => $msg];
        }

        $url = rtrim($apiBase, '/') . '/api/v1/users/token';
        $resp = wp_remote_post($url, [
            'timeout' => 15,
            'body' => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh,
            ],
        ]);
        if (is_wp_error($resp)) {
            $msg = $resp->get_error_message();
            if (class_exists('IA_PeerTube_Token_Store') && method_exists('IA_PeerTube_Token_Store', 'touch_mint_error')) {
                IA_PeerTube_Token_Store::touch_mint_error($phpbb_user_id, 'refresh: ' . $msg);
            }
            return ['ok' => false, 'did_refresh' => false, 'message' => $msg];
        }

        $code = (int)wp_remote_retrieve_response_code($resp);
        $body = (string)wp_remote_retrieve_body($resp);
        if ($code < 200 || $code >= 300) {
            // PeerTube returns 400 with a JSON body containing `code` (invalid_client / invalid_grant).
            $errCode = '';
            $errMsg = '';
            $j = json_decode($body, true);
            if (is_array($j)) {
                $errCode = (string)($j['code'] ?? '');
                $errMsg = (string)($j['message'] ?? '');
            }

            $msg = 'Refresh grant failed (HTTP ' . $code . ')'
                . ($errCode !== '' ? ' code=' . $errCode : '')
                . ($errMsg !== '' ? ' msg=' . substr($errMsg, 0, 160) : '')
                . '.';

            if (class_exists('IA_PeerTube_Token_Store') && method_exists('IA_PeerTube_Token_Store', 'touch_mint_error')) {
                IA_PeerTube_Token_Store::touch_mint_error($phpbb_user_id, 'refresh: ' . $msg);
            }
            return ['ok' => false, 'did_refresh' => false, 'message' => $msg, 'code' => ($errCode !== '' ? $errCode : null)];
        }

        $json = json_decode($body, true);
        if (!is_array($json) || empty($json['access_token'])) {
            $msg = 'Refresh grant returned invalid JSON.';
            if (class_exists('IA_PeerTube_Token_Store') && method_exists('IA_PeerTube_Token_Store', 'touch_mint_error')) {
                IA_PeerTube_Token_Store::touch_mint_error($phpbb_user_id, 'refresh: ' . $msg);
            }
            return ['ok' => false, 'did_refresh' => false, 'message' => $msg];
        }

        $access = (string)$json['access_token'];
        $expiresIn = (int)($json['expires_in'] ?? 0);
        $expiresAtNew = $expiresIn > 0 ? gmdate('Y-m-d H:i:s', time() + $expiresIn) : null;

        $accessEncNew = self::encrypt($access);

        $refreshEncNew = null;
        if (!empty($json['refresh_token']) && is_string($json['refresh_token'])) {
            $refreshEncNew = self::encrypt((string)$json['refresh_token']);
        }

        IA_PeerTube_Token_Store::update_access($phpbb_user_id, $accessEncNew, $expiresAtNew, $refreshEncNew);

        // Sanity check: immediately validate the new token. If it still 401s, treat refresh as failed.
        if (class_exists('IA_PeerTube_Token_Helper') && method_exists('IA_PeerTube_Token_Helper', 'validate_access_token_row')) {
            $row2 = IA_PeerTube_Token_Store::get($phpbb_user_id);
            if ($row2) {
                $v = IA_PeerTube_Token_Helper::validate_access_token_row($row2);
                if (empty($v['ok'])) {
                    $msg = 'Refreshed but validate failed: ' . (string)($v['message'] ?? 'unknown');
                    IA_PeerTube_Token_Store::touch_mint_error($phpbb_user_id, 'refresh: ' . $msg);
                    return ['ok' => false, 'did_refresh' => true, 'message' => $msg];
                }
            }
        }

        return ['ok' => true, 'did_refresh' => true, 'message' => 'Refreshed.'];
    }
}
