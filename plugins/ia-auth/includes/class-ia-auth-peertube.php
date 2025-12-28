<?php
if (!defined('ABSPATH')) exit;

/**
 * PeerTube token minting without any user-facing OAuth screens.
 * Uses the built-in PeerTube endpoints:
 *  - GET  /api/v1/oauth-clients/local
 *  - POST /api/v1/users/token
 *
 * NOTE:
 * - /api/v1/oauth-clients/local should be PUBLIC (no auth) per PeerTube OpenAPI.
 * - In reverse-proxy setups, calling via 127.0.0.1 can yield HTTP 403 due to Host checks.
 *   So we fetch oauth client via the PUBLIC base URL, not the internal base URL.
 */
final class IA_Auth_PeerTube {
    private $log;
    private $crypto;

    public function __construct($logger, $crypto) {
        $this->log = $logger;
        $this->crypto = $crypto;
    }

    private function internal_base(array $engine_peertube_api): string {
        $b = trim((string)($engine_peertube_api['internal_base_url'] ?? ''));
        return rtrim($b, '/');
    }

    private function public_base(array $engine_peertube_api): string {
        // IA Engine provides this (or IA Auth config can store it).
        $b = trim((string)($engine_peertube_api['public_base_url'] ?? ''));
        return rtrim($b, '/');
    }

    private function public_host(array $engine_peertube_api): string {
        $pub = $this->public_base($engine_peertube_api);
        if ($pub === '') return '';
        $parts = @parse_url($pub);
        if (!is_array($parts)) return '';
        return (string)($parts['host'] ?? '');
    }

    /**
     * Generic request helper.
     *
     * @param bool $use_public_base If true, use public_base_url instead of internal_base_url.
     * @param bool $attach_token    If true, attach Bearer token (when available).
     */
    private function req(
        string $method,
        string $path,
        array $engine_peertube_api,
        array $args = [],
        bool $use_public_base = false,
        bool $attach_token = true
    ): array {
        $base = $use_public_base ? $this->public_base($engine_peertube_api) : $this->internal_base($engine_peertube_api);

        if ($base === '') {
            return ['ok' => false, 'message' => 'PeerTube base URL not available from IA Engine.'];
        }

        $url = $base . $path;

        $headers = [
            'Accept'     => 'application/json',
            'User-Agent' => 'IA-Auth',
        ];

        // If we are using internal base, many reverse proxies require the PUBLIC host header.
        $pubHost = $this->public_host($engine_peertube_api);
        if (!$use_public_base && $pubHost !== '') {
            $headers['Host'] = $pubHost;
        }

        // Attach token only when asked (oauth client endpoint should be public)
        if ($attach_token) {
            $token = trim((string)($engine_peertube_api['token'] ?? ''));
            if ($token !== '') {
                $headers['Authorization'] = 'Bearer ' . $token;
            }
        }

        $req = array_merge([
            'method'  => $method,
            'timeout' => 15,
            'headers' => $headers,
        ], $args);

        $resp = wp_remote_request($url, $req);

        if (is_wp_error($resp)) {
            return ['ok' => false, 'message' => $resp->get_error_message(), 'debug' => ['url' => $url]];
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = (string) wp_remote_retrieve_body($resp);
        $json = null;

        if ($body !== '') {
            $json = json_decode($body, true);
        }

        return [
            'ok'   => ($code >= 200 && $code < 300),
            'code' => $code,
            'url'  => $url,
            'body' => $body,
            'json' => $json,
        ];
    }

    /**
     * Fetch local OAuth client (built-in in PeerTube).
     * IMPORTANT: use PUBLIC base URL and DO NOT attach Bearer token.
     */
    public function get_oauth_client(array $engine_peertube_api): array {
        $res = $this->req('GET', '/api/v1/oauth-clients/local', $engine_peertube_api, [], true, false);

        if (!$res['ok']) {
            $msg = 'Could not fetch OAuth client.';
            if (!empty($res['code'])) $msg .= ' HTTP ' . (int)$res['code'] . '.';
            return ['ok' => false, 'message' => $msg, 'debug' => $res];
        }

        $cid  = (string)($res['json']['client_id'] ?? '');
        $csec = (string)($res['json']['client_secret'] ?? '');

        if ($cid === '' || $csec === '') {
            return ['ok' => false, 'message' => 'OAuth client response missing client_id/client_secret.', 'debug' => $res];
        }

        return ['ok' => true, 'client_id' => $cid, 'client_secret' => $csec];
    }

    public function password_grant(string $username_or_email, string $password, array $engine_peertube_api): array {
        $client = $this->get_oauth_client($engine_peertube_api);
        if (!$client['ok']) return $client;

        $res = $this->req('POST', '/api/v1/users/token', $engine_peertube_api, [
            'headers' => [
                'Accept'       => 'application/json',
                'User-Agent'   => 'IA-Auth',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'client_id'     => $client['client_id'],
                'client_secret' => $client['client_secret'],
                'grant_type'    => 'password',
                'username'      => $username_or_email,
                'password'      => $password,
            ],
        ], true, false); // token endpoint also doesn't need Bearer token; it returns one.

        if (!$res['ok']) {
            $msg = 'Token request failed.';
            if (is_array($res['json']) && !empty($res['json']['detail'])) $msg = (string)$res['json']['detail'];
            return ['ok' => false, 'message' => $msg, 'debug' => $res];
        }

        return ['ok' => true, 'token' => $this->normalize_token($res['json'])];
    }

    private function normalize_token($json): array {
        $access     = (string)($json['access_token'] ?? '');
        $refresh    = (string)($json['refresh_token'] ?? '');
        $expires_in = (int)($json['expires_in'] ?? 0);

        return [
            'access_token'  => $access,
            'refresh_token' => $refresh,
            'expires_in'    => $expires_in,
            'obtained_at'   => time(),
        ];
    }
}
