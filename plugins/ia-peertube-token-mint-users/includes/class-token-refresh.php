<?php
if (!defined('ABSPATH')) exit;

class IA_PeerTube_Token_Refresh {

    private static function api_base(): string {
        return (class_exists('IA_Engine') && method_exists('IA_Engine', 'peertube_internal_base_url'))
            ? IA_Engine::peertube_internal_base_url()
            : '';
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

    public static function maybe_refresh(int $phpbb_user_id, array $row, int $skewSeconds = 120): array {
        $expiresAt = (string)($row['expires_at'] ?? '');
        if ($expiresAt === '') {
            // Some legacy rows didn’t persist expires_at. In that case, treat the token as expiring on a sane default TTL
            // from the last refresh/mint timestamp (PeerTube access tokens are typically ~1 hour).
            $defaultTtl = 3600;
            $anchor = (string)($row['last_refresh_at'] ?? ($row['last_mint_at'] ?? ($row['updated_at'] ?? '')));
            $anchorTs = $anchor !== '' ? strtotime($anchor . ' UTC') : 0;
            if ($anchorTs > 0 && $anchorTs > (time() - ($defaultTtl - $skewSeconds))) {
                return ['ok' => true, 'message' => 'Not expired (legacy TTL).'];
            }
            // If we have a refresh token, attempt refresh; otherwise, we can’t improve the situation.
            // (If no refresh token exists, callers will still receive 401 and should re-mint.)
        }

        $expTs = $expiresAt !== '' ? strtotime($expiresAt . ' UTC') : 0;
        if ($expiresAt !== '' && !$expTs) return ['ok' => true, 'message' => 'Expiry parse failed.'];

        if ($expTs > (time() + $skewSeconds)) {
            return ['ok' => true, 'message' => 'Not expired.'];
        }

        $refreshEnc = (string)($row['refresh_token_enc'] ?? '');
        if ($refreshEnc === '') return ['ok' => false, 'message' => 'No refresh token stored.'];

        $refresh = self::decrypt($refreshEnc);
        if ($refresh === '') return ['ok' => false, 'message' => 'Refresh token decrypt failed.'];

        $apiBase = self::api_base();
        if ($apiBase === '') return ['ok' => false, 'message' => 'PeerTube API base URL not configured.'];

        $cfg = self::peertube_cfg();
        $clientId = (string)($cfg['oauth_client_id'] ?? '');
        $clientSecret = (string)($cfg['oauth_client_secret'] ?? '');
        if ($clientId === '' || $clientSecret === '') {
            return ['ok' => false, 'message' => 'OAuth client missing in ia-engine config.'];
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
        if (is_wp_error($resp)) return ['ok' => false, 'message' => $resp->get_error_message()];

        $code = (int)wp_remote_retrieve_response_code($resp);
        $body = (string)wp_remote_retrieve_body($resp);
        if ($code < 200 || $code >= 300) return ['ok' => false, 'message' => 'Refresh grant failed (HTTP ' . $code . ').'];

        $json = json_decode($body, true);
        if (!is_array($json) || empty($json['access_token'])) return ['ok' => false, 'message' => 'Refresh grant returned invalid JSON.'];

        $access = (string)$json['access_token'];
        $expiresIn = (int)($json['expires_in'] ?? 0);
        $expiresAtNew = $expiresIn > 0 ? gmdate('Y-m-d H:i:s', time() + $expiresIn) : null;

        $accessEncNew = self::encrypt($access);
        IA_PeerTube_Token_Store::update_access($phpbb_user_id, $accessEncNew, $expiresAtNew);

        return ['ok' => true, 'message' => 'Refreshed.'];
    }
}
