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

	/**
	 * Resolve the PeerTube API bearer token.
	 * Priority:
	 *  1) IA Auth settings (ia_auth_options.peertube_api_token)
	 *  2) IA Engine settings (ia_engine_peertube_api.token)
	 */
	private function resolve_bearer_token(array $engine_peertube_api): string {
		$opt = get_option(IA_Auth::OPT_KEY, []);
		if (is_array($opt)) {
			$t = trim((string)($opt['peertube_api_token'] ?? ''));
			if ($t !== '') return $t;
		}

		// 1) Explicit token/access token field in IA Engine (manual paste)
		$t = trim((string)($engine_peertube_api['token'] ?? ''));
		if ($t !== '') return $t;

		// 2) Admin access token minted via IA Engine refresh flow
		$t = trim((string)($engine_peertube_api['admin_access_token'] ?? ''));
		if ($t !== '') return $t;

		return '';
	}

    private function internal_base(array $engine_peertube_api): string {
        $b = rtrim(trim((string)($engine_peertube_api['internal_base_url'] ?? '')), '/');
        if ($b !== '') return $b;

        // Fallback: if IA Engine didn't inject normalized URLs, compute them.
        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'peertube_internal_base_url')) {
            $b = rtrim(trim((string) IA_Engine::peertube_internal_base_url()), '/');
            if ($b !== '') return $b;
        }

        // Last resort: compute from raw stored config keys.
        $raw = is_array($engine_peertube_api) ? $engine_peertube_api : [];
        $scheme = trim((string)($raw['scheme'] ?? $raw['peertube_scheme'] ?? ''));
        $host   = trim((string)($raw['internal_host'] ?? $raw['peertube_internal_host'] ?? ''));
        $port   = trim((string)($raw['internal_port'] ?? $raw['peertube_internal_port'] ?? ''));
        $path   = trim((string)($raw['base_path'] ?? $raw['peertube_base_path'] ?? ''));
        if ($scheme && $host) {
            $u = $scheme . '://' . $host;
            if ($port !== '' && $port !== '80' && $port !== '443') $u .= ':' . $port;
            if ($path !== '') $u .= '/' . ltrim($path, '/');
            return rtrim($u, '/');
        }

        return '';
    }

    private function public_base(array $engine_peertube_api): string {
        // IA Engine provides this (or IA Auth config can store it).
        $b = rtrim(trim((string)($engine_peertube_api['public_base_url'] ?? '')), '/');
        if ($b !== '') return $b;

        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'peertube_public_base_url')) {
            $b = rtrim(trim((string) IA_Engine::peertube_public_base_url()), '/');
            if ($b !== '') return $b;
        }

        $raw = is_array($engine_peertube_api) ? $engine_peertube_api : [];
        $pub = trim((string)($raw['public_url'] ?? $raw['peertube_public_url'] ?? ''));
        return rtrim($pub, '/');
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
			$token = $this->resolve_bearer_token($engine_peertube_api);
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

    /**
     * Admin: Find a PeerTube user by username or email.
     * Uses GET /api/v1/users?search=...
     * Returns the first exact match (username/email) when available,
     * otherwise falls back to the first result.
     */
    public function admin_find_user(string $search, array $engine_peertube_api): array {
        $search = trim((string)$search);
        if ($search === '') return ['ok' => false, 'message' => 'Missing search string.'];

        // Requires admin token.
        $token = $this->resolve_bearer_token($engine_peertube_api);
        if ($token === '') {
            return ['ok' => false, 'message' => 'PeerTube admin token not available.'];
        }

        $res = $this->req('GET', '/api/v1/users?search=' . rawurlencode($search) . '&count=10', $engine_peertube_api, [], false, true);
        if (!$res['ok']) {
            $msg = 'PeerTube user search failed.';
            if (!empty($res['code'])) $msg .= ' HTTP ' . (int)$res['code'] . '.';
            return ['ok' => false, 'message' => $msg, 'debug' => $res];
        }

        $list = $res['json'];
        if (!is_array($list) || empty($list)) {
            return ['ok' => false, 'message' => 'User not found.'];
        }

        $needle = strtolower($search);
        $pick = null;
        foreach ($list as $u) {
            if (!is_array($u)) continue;
            $un = strtolower((string)($u['username'] ?? ''));
            $em = strtolower((string)($u['email'] ?? ''));
            if ($un === $needle || $em === $needle) {
                $pick = $u;
                break;
            }
        }

        if (!$pick) $pick = is_array($list[0]) ? $list[0] : null;
        if (!$pick) return ['ok' => false, 'message' => 'User not found.'];

        return ['ok' => true, 'user' => $pick];
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

    /**
     * Admin: Create a PeerTube user (and optionally a channelName).
     * Requires an admin token in IA Engine peertube_api config.
     */
    public function admin_create_user(string $username, string $email, string $password, string $channel_name, array $engine_peertube_api): array {
		$bearer = $this->resolve_bearer_token($engine_peertube_api);
		if (!$bearer) {
			return ['ok' => false, 'message' => 'PeerTube admin token not configured. Set it in IA Auth → Config (PeerTube API credentials) or IA Engine → PeerTube API.'];
		}

        $body = [
            'username'    => $username,
            'password'    => $password,
            'email'       => $email,
            'role'        => 2,
        ];
        if ($channel_name !== '') $body['channelName'] = $channel_name;

		$res = $this->req('POST', '/api/v1/users', $engine_peertube_api, [
            'headers' => [
                'Accept'       => 'application/json',
                'User-Agent'   => 'IA-Auth',
                'Content-Type' => 'application/json',
				'Authorization'=> 'Bearer ' . $bearer,
            ],
            'body' => wp_json_encode($body),
            'timeout' => 20,
        ], true, false);

        if (empty($res['ok'])) {
            // Normalise error message so admin UI doesn't show "Unknown error".
            $msg = 'PeerTube user create failed.';
            if (!empty($res['code'])) $msg .= ' HTTP ' . (int)$res['code'] . '.';
            if (is_array($res['json'])) {
                if (!empty($res['json']['detail'])) {
                    $msg .= ' ' . (string)$res['json']['detail'];
                } elseif (!empty($res['json']['message'])) {
                    $msg .= ' ' . (string)$res['json']['message'];
                } elseif (!empty($res['json']['error'])) {
                    $msg .= ' ' . (string)$res['json']['error'];
                }
            }

            // Keep debug payload for error_log / troubleshooting.
            $res['message'] = $msg;
            return $res;
        }

        $json = $res['json'] ?? null;
        $uid  = (int)($json['user']['id'] ?? 0);
        $aid  = (int)($json['user']['account']['id'] ?? 0);

        if ($uid <= 0) {
            return ['ok' => false, 'message' => 'PeerTube create user returned no id.', 'json' => $json];
        }

        return ['ok' => true, 'peertube_user_id' => $uid, 'peertube_account_id' => $aid, 'json' => $json];
    }

    /**
     * Admin: Update a PeerTube user's password.
     * Uses PUT /api/v1/users/{id} with body { password: "..." }.
     */
    public function admin_update_user_password(int $peertube_user_id, string $new_password, array $engine_peertube_api): array {
        $peertube_user_id = (int) $peertube_user_id;
        $new_password = (string) $new_password;
        if ($peertube_user_id <= 0 || $new_password === '') {
            return ['ok' => false, 'message' => 'Bad input (missing user id or password).'];
        }

        $bearer = $this->resolve_bearer_token($engine_peertube_api);
        if (!$bearer) {
            return ['ok' => false, 'message' => 'PeerTube admin token not configured.'];
        }

        $body = [ 'password' => $new_password ];
        $res = $this->req('PUT', '/api/v1/users/' . $peertube_user_id, $engine_peertube_api, [
            'headers' => [
                'Accept'        => 'application/json',
                'User-Agent'    => 'IA-Auth',
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $bearer,
            ],
            'body' => wp_json_encode($body),
            'timeout' => 20,
        ], true, false);

        if (empty($res['ok'])) {
            $msg = 'PeerTube password update failed.';
            if (!empty($res['code'])) $msg .= ' HTTP ' . (int)$res['code'] . '.';
            if (is_array($res['json'])) {
                if (!empty($res['json']['detail'])) $msg .= ' ' . (string)$res['json']['detail'];
                elseif (!empty($res['json']['message'])) $msg .= ' ' . (string)$res['json']['message'];
                elseif (!empty($res['json']['error'])) $msg .= ' ' . (string)$res['json']['error'];
            }
            $res['message'] = $msg;
            return $res;
        }

        return ['ok' => true, 'code' => (int)($res['code'] ?? 0)];
    }


    /**
 * Admin: Update a PeerTube user's email.
 * Uses PUT /api/v1/users/{id} with body { email: "..." }.
 */
public function admin_update_user_email(int $peertube_user_id, string $new_email, array $engine_peertube_api): array {
    $peertube_user_id = (int)$peertube_user_id;
    $new_email = trim((string)$new_email);
    if ($peertube_user_id <= 0 || $new_email === '') {
        return ['ok' => false, 'message' => 'Bad input (missing user id or email).'];
    }

    $bearer = $this->resolve_bearer_token($engine_peertube_api);
    if (!$bearer) {
        return ['ok' => false, 'message' => 'PeerTube admin token not configured.'];
    }

    $body = [ 'email' => $new_email ];
    $res = $this->req('PUT', '/api/v1/users/' . $peertube_user_id, $engine_peertube_api, [
        'headers' => [
            'Accept'        => 'application/json',
            'User-Agent'    => 'IA-Auth',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $bearer,
        ],
        'body' => wp_json_encode($body),
        'timeout' => 20,
    ], true, false);

    if (empty($res['ok'])) {
        $msg = 'PeerTube email update failed.';
        if (!empty($res['code'])) $msg .= ' HTTP ' . (int)$res['code'] . '.';
        if (is_array($res['json'])) {
            if (!empty($res['json']['detail'])) $msg .= ' ' . (string)$res['json']['detail'];
            elseif (!empty($res['json']['message'])) $msg .= ' ' . (string)$res['json']['message'];
            elseif (!empty($res['json']['error'])) $msg .= ' ' . (string)$res['json']['error'];
        }
        $res['message'] = $msg;
        return $res;
    }

    return ['ok' => true, 'code' => (int)($res['code'] ?? 0)];
}

/**
 * Admin: Delete a PeerTube user.
 * Uses DELETE /api/v1/users/{id}.
 */
public function admin_delete_user(int $peertube_user_id, array $engine_peertube_api): array {
    $peertube_user_id = (int)$peertube_user_id;
    if ($peertube_user_id <= 0) {
        return ['ok' => false, 'message' => 'Bad input (missing user id).'];
    }

    $bearer = $this->resolve_bearer_token($engine_peertube_api);
    if (!$bearer) {
        return ['ok' => false, 'message' => 'PeerTube admin token not configured.'];
    }

    $res = $this->req('DELETE', '/api/v1/users/' . $peertube_user_id, $engine_peertube_api, [
        'headers' => [
            'Accept'        => 'application/json',
            'User-Agent'    => 'IA-Auth',
            'Authorization' => 'Bearer ' . $bearer,
        ],
        'timeout' => 20,
    ], true, false);

    if (empty($res['ok'])) {
        $msg = 'PeerTube delete failed.';
        if (!empty($res['code'])) $msg .= ' HTTP ' . (int)$res['code'] . '.';
        if (is_array($res['json'])) {
            if (!empty($res['json']['detail'])) $msg .= ' ' . (string)$res['json']['detail'];
            elseif (!empty($res['json']['message'])) $msg .= ' ' . (string)$res['json']['message'];
            elseif (!empty($res['json']['error'])) $msg .= ' ' . (string)$res['json']['error'];
        }
        $res['message'] = $msg;
        return $res;
    }

    return ['ok' => true, 'code' => (int)($res['code'] ?? 0)];
}

/**
 * User: Update displayName via /api/v1/users/me (requires user access token).
 */
public function user_update_me_display_name(string $access_token, string $display_name, array $engine_peertube_api): array {
    $access_token = trim((string)$access_token);
    $display_name = trim((string)$display_name);
    if ($access_token === '' || $display_name === '') {
        return ['ok' => false, 'message' => 'Bad input (missing token or display name).'];
    }

    // For this request, bearer must be the user token.
    $body = [ 'displayName' => $display_name ];
    $engine = $engine_peertube_api;
    $res = $this->req('PUT', '/api/v1/users/me', $engine, [
        'headers' => [
            'Accept'        => 'application/json',
            'User-Agent'    => 'IA-Auth',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ],
        'body' => wp_json_encode($body),
        'timeout' => 20,
    ], true, false);

    if (empty($res['ok'])) {
        $msg = 'PeerTube update failed.';
        if (!empty($res['code'])) $msg .= ' HTTP ' . (int)$res['code'] . '.';
        if (is_array($res['json'])) {
            if (!empty($res['json']['detail'])) $msg .= ' ' . (string)$res['json']['detail'];
            elseif (!empty($res['json']['message'])) $msg .= ' ' . (string)$res['json']['message'];
            elseif (!empty($res['json']['error'])) $msg .= ' ' . (string)$res['json']['error'];
        }
        $res['message'] = $msg;
        return $res;
    }

    return ['ok' => true, 'code' => (int)($res['code'] ?? 0)];
}
}
