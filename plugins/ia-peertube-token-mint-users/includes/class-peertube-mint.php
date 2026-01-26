<?php
if (!defined('ABSPATH')) exit;

class IA_PT_PeerTube_Mint {

    public static function try_mint(int $wp_user_id, int $phpbb_user_id): array {
        // Returns ['ok'=>bool,'error'=>string,'expires_at'=>?string,'access_enc'=>?string,'refresh_enc'=>?string]
        $pw = IA_PT_Password_Capture::get_for_wp_user($wp_user_id);
        $identifier = IA_PT_Password_Capture::get_identifier_for_wp_user($wp_user_id);

        if (!$pw) {
            return ['ok'=>false,'error'=>'Password not available in this request. Your login handler is bypassing wp_authenticate. Add do_action(\'ia_pt_user_password\', $wp_user_id, $password, $identifier) inside the login flow.'];
        }

        $u = get_user_by('id', $wp_user_id);
        $wp_login = ($u && $u->user_login) ? (string)$u->user_login : '';
        $wp_email = ($u && $u->user_email) ? (string)$u->user_email : '';

        // IMPORTANT (Atrium): Never fall back to WP email for password grant.
        // In your stack, emails can collide/alias (e.g. sandbox@atreestump.online) which mints the wrong user.
        $cands = [];
        if ($identifier) $cands[] = (string)$identifier;
        if ($wp_login) $cands[] = $wp_login;
        $cands = array_values(array_unique(array_filter($cands)));

        $base = self::peertube_base_url();
        if (!$base) return ['ok'=>false,'error'=>'PeerTube base URL not available from ia-engine.'];

        $client = self::oauth_client();
        if (!$client['client_id'] || !$client['client_secret']) {
            return ['ok'=>false,'error'=>'PeerTube OAuth client_id/client_secret missing in ia-engine config.'];
        }

        $last_err = '';
        foreach ($cands as $cand) {
            $res = self::password_grant($base, $client, $cand, $pw);
            if ($res['ok']) return $res;
            $last_err = $res['error'] ?? $last_err;
        }

        // Retro-user path: if the PeerTube user doesn't exist yet (or mapping is wrong), provision it server-side
        // using the admin bearer token stored in ia-engine, then retry mint once.
        $admin = self::admin_bearer_token();
        if ($admin && $wp_login) {
            $prov = self::ensure_peertube_user_exists($base, $admin, $wp_login, $wp_email, $pw, $phpbb_user_id);
            if ($prov['ok']) {
                // Retry password-grant mint as the *username* only.
                $res2 = self::password_grant($base, $client, $wp_login, $pw);
                if ($res2['ok']) {
                    // Update identity map with the resolved user id if we have it.
                    if (!empty($prov['peertube_user_id'])) {
                        self::sync_identity_map_peertube_user_id($wp_user_id, (int)$prov['peertube_user_id']);
                    }
                    return $res2;
                }
                $last_err = $res2['error'] ?? $last_err;
            } else {
                $last_err = $prov['error'] ?? $last_err;
            }
        }

        return ['ok'=>false,'error'=>($last_err ?: 'Unable to mint PeerTube user token (password grant).')];
    }

    private static function admin_bearer_token(): ?string {
        // ia-engine stores a server-side PeerTube API bearer token as peertube_api.token
        $cfg = self::engine_peertube_cfg();
        $tok = is_array($cfg) ? (string)($cfg['token'] ?? '') : '';
        $tok = trim($tok);
        if ($tok !== '') return $tok;

        // Fallback: some setups store admin access token explicitly.
        $tok = is_array($cfg) ? (string)($cfg['admin_access_token'] ?? '') : '';
        $tok = trim($tok);
        return $tok !== '' ? $tok : null;
    }

    private static function ensure_peertube_user_exists(string $base, string $adminBearer, string $username, string $email, string $password, int $phpbb_user_id): array {
        $username = trim($username);
        if ($username === '') return ['ok'=>false,'error'=>'Cannot provision PeerTube user: missing username.'];

        // 1) If identity map already has a peertube_user_id, verify it matches this username.
        $mapped_id = self::identity_map_peertube_user_id_for_wp();
        if ($mapped_id) {
            $who = self::admin_get_user($base, $adminBearer, $mapped_id);
            if ($who['ok'] && !empty($who['username'])) {
                if (strcasecmp((string)$who['username'], $username) === 0) {
                    // Good mapping; ensure password is set.
                    $set = self::admin_set_user_password($base, $adminBearer, $mapped_id, $password);
                    return $set['ok'] ? ['ok'=>true,'peertube_user_id'=>$mapped_id] : $set;
                }
            }
            // Mapping is wrong or unverifiable; ignore it (do NOT post as someone else).
        }

        // 2) Try find exact user by username via admin search.
        $found = self::admin_find_user_by_username($base, $adminBearer, $username);
        if ($found['ok'] && !empty($found['peertube_user_id'])) {
            $pid = (int)$found['peertube_user_id'];
            $set = self::admin_set_user_password($base, $adminBearer, $pid, $password);
            if (!$set['ok']) return $set;
            return ['ok'=>true,'peertube_user_id'=>$pid];
        }

        // 3) Create the user.
        $email_use = trim($email);
        if ($email_use === '') {
            // PeerTube requires an email; synthesize a stable one for legacy users.
            $email_use = strtolower($username)."+".$phpbb_user_id."@indieagora.local";
        }

        $created = self::admin_create_user($base, $adminBearer, $username, $email_use, $password);
        if (!$created['ok']) {
            // If email collision, retry with synthetic email.
            if (!empty($created['code']) && (int)$created['code'] === 409) {
                $email_use = strtolower($username)."+".$phpbb_user_id."@indieagora.local";
                $created = self::admin_create_user($base, $adminBearer, $username, $email_use, $password);
            }
        }
        if (!$created['ok']) return $created;

        $pid = (int)($created['peertube_user_id'] ?? 0);
        if ($pid > 0) {
            return ['ok'=>true,'peertube_user_id'=>$pid];
        }

        // If create response didn't include id, attempt to find it.
        $found2 = self::admin_find_user_by_username($base, $adminBearer, $username);
        if ($found2['ok'] && !empty($found2['peertube_user_id'])) {
            return ['ok'=>true,'peertube_user_id'=>(int)$found2['peertube_user_id']];
        }

        return ['ok'=>false,'error'=>'PeerTube user created but could not resolve user id.'];
    }

    private static function identity_map_peertube_user_id_for_wp(): ?int {
        $wp_user_id = (int)get_current_user_id();
        if ($wp_user_id <= 0) return null;
        global $wpdb;
        $t = defined('IA_PT_IDENTITY_TABLE') ? IA_PT_IDENTITY_TABLE : $wpdb->prefix.'ia_identity_map';
        $val = $wpdb->get_var($wpdb->prepare("SELECT peertube_user_id FROM {$t} WHERE wp_user_id = %d LIMIT 1", $wp_user_id));
        $id = (int)$val;
        return $id > 0 ? $id : null;
    }

    private static function sync_identity_map_peertube_user_id(int $wp_user_id, int $peertube_user_id): void {
        if ($wp_user_id <= 0 || $peertube_user_id <= 0) return;
        global $wpdb;
        $t = IA_PT_IDENTITY_TABLE;
        $wpdb->update($t, [
            'peertube_user_id' => $peertube_user_id,
            'updated_at' => current_time('mysql', 1),
        ], ['wp_user_id' => $wp_user_id]);
    }

    private static function admin_headers(string $adminBearer): array {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$adminBearer,
        ];
    }

    private static function admin_get_user(string $base, string $adminBearer, int $userId): array {
        $url = rtrim($base,'/').'/api/v1/users/'.rawurlencode((string)$userId);
        $resp = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => self::admin_headers($adminBearer),
        ]);
        if (is_wp_error($resp)) return ['ok'=>false,'error'=>'Admin GET user failed: '.$resp->get_error_message()];
        $code = (int)wp_remote_retrieve_response_code($resp);
        $raw = (string)wp_remote_retrieve_body($resp);
        $json = json_decode($raw, true);
        if ($code >= 200 && $code < 300 && is_array($json)) {
            return ['ok'=>true,'username'=>(string)($json['username'] ?? ''), 'data'=>$json];
        }
        return ['ok'=>false,'error'=>'Admin GET user failed (HTTP '.$code.'): '.substr($raw,0,300), 'code'=>$code];
    }

    private static function admin_find_user_by_username(string $base, string $adminBearer, string $username): array {
        $q = rawurlencode($username);
        $url = rtrim($base,'/').'/api/v1/users?search='.$q.'&count=10&start=0';
        $resp = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => self::admin_headers($adminBearer),
        ]);
        if (is_wp_error($resp)) return ['ok'=>false,'error'=>'Admin search failed: '.$resp->get_error_message()];
        $code = (int)wp_remote_retrieve_response_code($resp);
        $raw = (string)wp_remote_retrieve_body($resp);
        $json = json_decode($raw, true);
        if ($code >= 200 && $code < 300 && is_array($json)) {
            $list = $json['data'] ?? $json;
            if (is_array($list)) {
                foreach ($list as $row) {
                    if (!is_array($row)) continue;
                    $un = (string)($row['username'] ?? '');
                    if ($un !== '' && strcasecmp($un, $username) === 0) {
                        $id = (int)($row['id'] ?? 0);
                        if ($id > 0) return ['ok'=>true,'peertube_user_id'=>$id];
                    }
                }
            }
            return ['ok'=>false,'error'=>'Admin search returned no exact match for '.$username];
        }
        return ['ok'=>false,'error'=>'Admin search failed (HTTP '.$code.'): '.substr($raw,0,300), 'code'=>$code];
    }

    private static function admin_create_user(string $base, string $adminBearer, string $username, string $email, string $password): array {
        $url = rtrim($base,'/').'/api/v1/users';
        $payload = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            // PeerTube OpenAPI: AddUser.role is REQUIRED and is an integer enum (Admin=0, Moderator=1, User=2).
            'role' => 2,
        ];
        $resp = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => self::admin_headers($adminBearer),
            'body' => wp_json_encode($payload),
        ]);
        if (is_wp_error($resp)) return ['ok'=>false,'error'=>'Admin create user failed: '.$resp->get_error_message()];
        $code = (int)wp_remote_retrieve_response_code($resp);
        $raw = (string)wp_remote_retrieve_body($resp);
        $json = json_decode($raw, true);
        if ($code >= 200 && $code < 300) {
            $id = 0;
            if (is_array($json)) {
                $id = (int)($json['userId'] ?? ($json['id'] ?? 0));
            }
            return ['ok'=>true,'peertube_user_id'=>$id, 'data'=>$json];
        }
        return ['ok'=>false,'error'=>'Admin create user failed (HTTP '.$code.'): '.substr($raw,0,300), 'code'=>$code];
    }

    private static function admin_set_user_password(string $base, string $adminBearer, int $userId, string $password): array {
        $url = rtrim($base,'/').'/api/v1/users/'.rawurlencode((string)$userId);
        $payload = ['password' => $password];
        $resp = wp_remote_request($url, [
            'method' => 'PUT',
            'timeout' => 15,
            'headers' => self::admin_headers($adminBearer),
            'body' => wp_json_encode($payload),
        ]);
        if (is_wp_error($resp)) return ['ok'=>false,'error'=>'Admin set password failed: '.$resp->get_error_message()];
        $code = (int)wp_remote_retrieve_response_code($resp);
        $raw = (string)wp_remote_retrieve_body($resp);
        if ($code >= 200 && $code < 300) {
            return ['ok'=>true];
        }
        return ['ok'=>false,'error'=>'Admin set password failed (HTTP '.$code.'): '.substr($raw,0,300), 'code'=>$code];
    }

    private static function password_grant(string $base, array $client, string $username, string $password): array {
        $url = rtrim($base,'/') . '/api/v1/users/token';
        $body = [
            'client_id' => $client['client_id'],
            'client_secret' => $client['client_secret'],
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
        ];
        $resp = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => ['Accept'=>'application/json'],
            'body' => $body,
        ]);
        if (is_wp_error($resp)) {
            return ['ok'=>false,'error'=>'HTTP error: '.$resp->get_error_message()];
        }
        $code = (int)wp_remote_retrieve_response_code($resp);
        $raw = (string)wp_remote_retrieve_body($resp);
        $json = json_decode($raw, true);

        if ($code >= 200 && $code < 300 && is_array($json) && !empty($json['access_token'])) {
            $access = (string)$json['access_token'];
            $refresh = (string)($json['refresh_token'] ?? '');
            $expires_in = (int)($json['expires_in'] ?? 0);
            $expires_at = $expires_in ? gmdate('Y-m-d H:i:s', time() + $expires_in) : null;

            $access_enc = self::encrypt($access);
            $refresh_enc = $refresh ? self::encrypt($refresh) : null;
            return ['ok'=>true,'expires_at'=>$expires_at,'access_enc'=>$access_enc,'refresh_enc'=>$refresh_enc,'error'=>''];
        }

        $msg = '';
        if (is_array($json)) {
            $msg = $json['error_description'] ?? ($json['error'] ?? '');
        }
        if (!$msg) $msg = 'HTTP '.$code.' response from PeerTube token endpoint.';
        return ['ok'=>false,'error'=>"Password grant failed for {$username}: {$msg}"];
    }

    private static function encrypt(string $plain): string {
        if (class_exists('IA_Engine_Crypto') && method_exists('IA_Engine_Crypto','encrypt')) {
            return IA_Engine_Crypto::encrypt($plain);
        }
        if (class_exists('IA_Engine') && method_exists('IA_Engine','encrypt')) {
            return IA_Engine::encrypt($plain);
        }
        // As a last resort, store plain (not recommended). We'll mark error elsewhere.
        return $plain;
    }

    private static function peertube_base_url(): ?string {
        // Prefer ia-engine convenience getters (Atrium ia-engine)
        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'peertube_public_base_url')) {
            try {
                $u = IA_Engine::peertube_public_base_url();
                $u = is_string($u) ? trim($u) : '';
                if ($u !== '') return rtrim($u, '/');
            } catch (Throwable $e) { /* ignore */ }
        }

        // Fall back to peertube_api() config (ia-engine stores 'public_url')
        $cfg = self::engine_peertube_cfg();
        if (is_array($cfg)) {
            $u = $cfg['public_url'] ?? ($cfg['public_base_url'] ?? ($cfg['peertube_public_base_url'] ?? null));
            if (!$u && isset($cfg['peertube']) && is_array($cfg['peertube'])) {
                $u = $cfg['peertube']['public_url'] ?? ($cfg['peertube']['public_base_url'] ?? null);
            }
            if ($u) return rtrim((string)$u, '/');
        }

        if (defined('IA_ENGINE_PEERTUBE_PUBLIC_BASE_URL')) {
            return rtrim((string)IA_ENGINE_PEERTUBE_PUBLIC_BASE_URL, '/');
        }

        // Last resort: build from internal base URL
        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'peertube_internal_base_url')) {
            try {
                $u = IA_Engine::peertube_internal_base_url();
                $u = is_string($u) ? trim($u) : '';
                if ($u !== '') return rtrim($u, '/');
            } catch (Throwable $e) { /* ignore */ }
        }

        return null;
    }

    private static function oauth_client(): array {
        $out = ['client_id' => null, 'client_secret' => null];
        $cfg = self::engine_peertube_cfg();
        if (is_array($cfg)) {
            // ia-engine uses oauth_client_id / oauth_client_secret
            $out['client_id'] = $cfg['oauth_client_id'] ?? ($cfg['peertube_client_id'] ?? ($cfg['client_id'] ?? null));
            $out['client_secret'] = $cfg['oauth_client_secret'] ?? ($cfg['peertube_client_secret'] ?? ($cfg['client_secret'] ?? null));

            if (isset($cfg['peertube']) && is_array($cfg['peertube'])) {
                $out['client_id'] = $out['client_id'] ?? ($cfg['peertube']['oauth_client_id'] ?? ($cfg['peertube']['client_id'] ?? null));
                $out['client_secret'] = $out['client_secret'] ?? ($cfg['peertube']['oauth_client_secret'] ?? ($cfg['peertube']['client_secret'] ?? null));
            }
        }
        return $out;
    }

	    /**
	     * Fetch PeerTube-related config from ia-engine without assuming IA_Engine::get() is zero-arg.
	     */
	    private static function engine_peertube_cfg() {
	        // Preferred explicit getter if present
	        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'peertube_api')) {
	            try {
	                $cfg = IA_Engine::peertube_api();
	                if (is_array($cfg)) return $cfg;
	            } catch (\Throwable $e) { /* ignore */ }
	        }
	        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'get')) {
	            foreach (['peertube_api', 'peertube'] as $key) {
	                try {
	                    $cfg = IA_Engine::get($key);
	                    if (is_array($cfg)) return $cfg;
	                } catch (\Throwable $e) { /* ignore */ }
	            }
	        }
	        return null;
	    }
}
