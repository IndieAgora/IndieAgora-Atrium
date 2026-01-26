<?php
if (!defined('ABSPATH')) exit;

final class IA_Engine {
    private static $instance = null;

    // Single option storage for everything
    private const OPTION_KEY = 'ia_engine_config_v1';

    public static function instance(): self {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        IA_Engine_Admin::init();

        // AJAX test handlers (admin only)
        add_action('wp_ajax_ia_engine_test_phpbb',      [__CLASS__, 'ajax_test_phpbb']);
        add_action('wp_ajax_ia_engine_test_peertube_db',[__CLASS__, 'ajax_test_peertube_db']);
        add_action('wp_ajax_ia_engine_test_peertube_api',[__CLASS__, 'ajax_test_peertube_api']);
    }

    public static function option_key(): string {
        return self::OPTION_KEY;
    }

    public static function get_all(): array {
        $opt = get_option(self::OPTION_KEY, []);
        return is_array($opt) ? $opt : [];
    }

    /**
     * Returns a service config with secrets decrypted.
     * Services:
     * - phpbb
     * - peertube (DB)
     * - peertube_api (API)
     */
    public static function get(string $service): array {
        $all = self::get_all();
        $service = strtolower(trim($service));
        $cfg = $all[$service] ?? [];
        if (!is_array($cfg)) $cfg = [];

        foreach (self::secret_fields($service) as $field) {
            $cfg[$field] = !empty($cfg[$field]) ? IA_Engine_Crypto::decrypt((string)$cfg[$field]) : '';
        }
        return $cfg;
    }

    /**
     * Returns a service config but replaces secrets with 'set'/'not set'.
     */
    public static function get_safe(string $service): array {
        $all = self::get_all();
        $service = strtolower(trim($service));
        $cfg = $all[$service] ?? [];
        if (!is_array($cfg)) $cfg = [];

        foreach (self::secret_fields($service) as $field) {
            $cfg[$field] = !empty($cfg[$field]) ? 'set' : 'not set';
        }
        return $cfg;
    }

    /**
     * Save a service config.
     *
     * Normal usage (service-level array):
     *   IA_Engine::set('peertube_api', ['host' => '127.0.0.1', ...])
     *
     * Admin helper usage (single secret field):
     *   IA_Engine::set('peertube_api.token', '...', true)
     *   IA_Engine::set('peertube_api.admin_password', '__CLEAR__', true)
     */
    public static function set(string $service, $cfg, bool $is_secret_field = false): bool {
        if (!current_user_can('manage_options')) return false;

        // Support legacy admin calls that set one secret at a time:
        //   IA_Engine::set('peertube_api.token', '...', true)
        if ($is_secret_field || (is_string($cfg) && strpos($service, '.') !== false)) {
            $svc = strtolower(trim((string)$service));
            $parts = explode('.', $svc, 2);
            $svcName = $parts[0] ?? '';
            $field = $parts[1] ?? '';
            if ($svcName === '' || $field === '') return false;

            // Re-enter service-level setter with an array payload.
            return self::set($svcName, [ $field => (string)$cfg ], false);
        }

        if (!is_array($cfg)) return false;

        $service = strtolower(trim($service));
        $all = self::get_all();
        $prev = $all[$service] ?? [];
        if (!is_array($prev)) $prev = [];

        // IMPORTANT:
        // When admin UI saves a single secret field (via service.field), we must NOT
        // overwrite the whole service config with only that one field.
        // Merge incoming keys on top of existing config.
        $new = $prev;

        $secretFields = self::secret_fields($service);

        foreach ($cfg as $k => $v) {
            $k = (string)$k;
            if (in_array($k, $secretFields, true)) {
                $val = (string)$v;
                if ($val === '__CLEAR__') {
                    unset($new[$k]);
                    continue;
                }
                if ($val === '') {
                    // blank means keep existing
                    continue;
                }
                $new[$k] = IA_Engine_Crypto::encrypt($val);
            } else {
                // Non-secret: set as-is (empty string is a valid value)
                $new[$k] = $v;
            }
        }

        $all[$service] = $new;
        $ok = (bool) update_option(self::OPTION_KEY, $all, true);

        // Back-compat / integration shim: some plugins expect PeerTube API settings
        // in the legacy option key 'engine_peertube_api'. Keep it in sync.
        if ($ok && $service === 'peertube_api') {
            $pt = self::peertube_api();
            update_option('engine_peertube_api', [
                'internal_base_url' => $pt['internal_base_url'],
                'public_base_url'   => $pt['public_base_url'],
                'oauth_client_id'   => (string)($pt['oauth_client_id'] ?? ''),
                'oauth_client_secret' => (string)($pt['oauth_client_secret'] ?? ''),
                'admin_username'    => (string)($pt['admin_username'] ?? ''),
                'admin_password'    => (string)($pt['admin_password'] ?? ''),
                'admin_access_token' => (string)($pt['admin_access_token'] ?? ''),
                'admin_refresh_token' => (string)($pt['admin_refresh_token'] ?? ''),
            ], true);
        }

        return $ok;
    }

    private static function secret_fields(string $service): array {
        $service = strtolower(trim($service));
        if ($service === 'phpbb') return ['password'];
        if ($service === 'peertube') return ['password'];        // DB password
        if ($service === 'peertube_api') {
            return [
                'token',
                'oauth_client_secret',
                'admin_password',
                'admin_access_token',
                'admin_refresh_token'
            ];
        }
        return [];
    }

    // ---------- Convenience getters used by other plugins ----------

    public static function phpbb_db(): array {
        $c = self::get('phpbb');
        return [
            'host'   => (string)($c['host'] ?? 'localhost'),
            'port'   => (int)($c['port'] ?? 3306),
            'name'   => (string)($c['name'] ?? ''),
            'user'   => (string)($c['user'] ?? ''),
            'pass'   => (string)($c['password'] ?? ''),
            'prefix' => (string)($c['prefix'] ?? 'phpbb_'),
        ];
    }

    public static function peertube_db(): array {
        $c = self::get('peertube');
        return [
            'host' => (string)($c['host'] ?? '127.0.0.1'),
            'port' => (int)($c['port'] ?? 5432),
            'name' => (string)($c['name'] ?? ''),
            'user' => (string)($c['user'] ?? ''),
            'pass' => (string)($c['password'] ?? ''),
        ];
    }

    public static function peertube_api(): array {
        $c = self::get('peertube_api');
        return [
            'scheme'     => (string)($c['scheme'] ?? 'http'),
            'host'       => (string)($c['host'] ?? '127.0.0.1'),
            'port'       => (int)($c['port'] ?? 9000),
            'base_path'  => (string)($c['base_path'] ?? ''),
            'public_url' => (string)($c['public_url'] ?? 'https://stream.indieagora.com'),
            // Admin bearer token used for server-side API calls (eg. create users).
            // This is auto-maintained by IA Engine via a scheduled refresh.
            'token'      => (string)($c['token'] ?? ''),

            // OAuth client used to mint tokens (can be auto-detected from /api/v1/oauth-clients/local).
            'oauth_client_id'     => (string)($c['oauth_client_id'] ?? ''),
            'oauth_client_secret' => (string)($c['oauth_client_secret'] ?? ''),

            // Admin account credentials used ONLY to (re)mint a long-lived refresh token if needed.
            // Prefer providing a dedicated PeerTube admin/service account rather than your personal one.
            'admin_username' => (string)($c['admin_username'] ?? ''),
            'admin_password' => (string)($c['admin_password'] ?? ''),

            // Stored refresh/access tokens + expiries (unix timestamps).
            'admin_access_token'        => (string)($c['admin_access_token'] ?? ''),
            'admin_refresh_token'       => (string)($c['admin_refresh_token'] ?? ''),
            'admin_access_expires_at'   => (int)($c['admin_access_expires_at'] ?? 0),
            'admin_refresh_expires_at'  => (int)($c['admin_refresh_expires_at'] ?? 0),
        ];
    }

    public static function peertube_internal_base_url(): string {
        $c = self::peertube_api();
        $scheme = preg_match('~^https?$~', $c['scheme']) ? $c['scheme'] : 'http';
        $host = $c['host'] ?: '127.0.0.1';
        $port = (int)$c['port'];

        $basePath = trim((string)$c['base_path']);
        $basePath = $basePath !== '' ? '/' . ltrim($basePath, '/') : '';

        // Avoid :80/:443 noise
        $portPart = '';
        if (!(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) {
            $portPart = ':' . $port;
        }

        return $scheme . '://' . $host . $portPart . $basePath;
    }

    public static function peertube_public_base_url(): string {
        if (!class_exists('IA_Engine')) return '';
        $cfg = self::peertube_api();
        $u = trim((string)($cfg['public_url'] ?? ''));

        // If public URL is not set, fall back to internal base URL.
        // This keeps server-side provisioning working even when you only configure internal access.
        if ($u === '') {
            return self::peertube_internal_base_url();
        }

        return rtrim($u, '/');
    }

    public static function peertube_api_token(): string {
        // On-demand refresh (no manual ACP babysitting, and works even if WP-Cron is disabled).
        if (class_exists('IA_Engine_PeerTube_Token') && method_exists('IA_Engine_PeerTube_Token', 'refresh_if_needed')) {
            try {
                IA_Engine_PeerTube_Token::refresh_if_needed();
            } catch (Throwable $e) {
                // Never fatal: token refresh should not take the site down.
            }
        }

        // Re-read config after a possible refresh.
        $c = self::peertube_api();
        if (!empty($c['token'])) return (string)$c['token'];
        if (!empty($c['admin_access_token'])) return (string)$c['admin_access_token'];
        return '';
    }

    // ---------- AJAX test handlers ----------

    public static function ajax_test_phpbb(): void {
        self::ajax_guard();

        $cfg = self::phpbb_db();
        $ok = false;
        $msg = '';

        try {
            if (empty($cfg['name']) || empty($cfg['user'])) {
                throw new RuntimeException('Missing database name/user.');
            }

            $mysqli = @mysqli_connect($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name'], (int)$cfg['port']);
            if (!$mysqli) {
                throw new RuntimeException('MySQL connect failed: ' . mysqli_connect_error());
            }

            $res = $mysqli->query('SELECT 1');
            if (!$res) {
                throw new RuntimeException('MySQL test query failed.');
            }

            $ok = true;
            $msg = 'phpBB DB OK.';
            $mysqli->close();
        } catch (Throwable $e) {
            $msg = $e->getMessage();
        }

        wp_send_json([
            'ok' => $ok,
            'message' => $msg,
        ]);
    }

    public static function ajax_test_peertube_db(): void {
        self::ajax_guard();

        $cfg = self::peertube_db();
        $ok = false;
        $msg = '';

        try {
            if (!function_exists('pg_connect')) {
                throw new RuntimeException('pg_connect not available (install/enable PHP pgsql extension).');
            }
            if (empty($cfg['name']) || empty($cfg['user'])) {
                throw new RuntimeException('Missing database name/user.');
            }

            $connStr = sprintf(
                "host=%s port=%d dbname=%s user=%s password=%s connect_timeout=3",
                $cfg['host'],
                (int)$cfg['port'],
                $cfg['name'],
                $cfg['user'],
                $cfg['pass']
            );

            $conn = @pg_connect($connStr);
            if (!$conn) {
                throw new RuntimeException('Connection failed (pg_connect).');
            }

            $q = @pg_query($conn, 'SELECT 1;');
            if (!$q) {
                throw new RuntimeException('Postgres test query failed.');
            }

            $ok = true;
            $msg = 'PeerTube DB OK.';
            @pg_close($conn);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
        }

        wp_send_json([
            'ok' => $ok,
            'message' => $msg,
        ]);
    }

    public static function ajax_test_peertube_api(): void {
        self::ajax_guard();

        $ok = false;
        $msg = '';

        try {
            $base = rtrim(self::peertube_internal_base_url(), '/');
            $url = $base . '/api/v1/config';

            $headers = [
                'Accept' => 'application/json',
            ];

            $token = self::peertube_api_token();
            if ($token !== '') {
                // PeerTube supports Bearer auth for tokens in many setups
                $headers['Authorization'] = 'Bearer ' . $token;
            }

            $resp = wp_remote_get($url, [
                'timeout' => 6,
                'headers' => $headers,
            ]);

            if (is_wp_error($resp)) {
                throw new RuntimeException('HTTP error: ' . $resp->get_error_message());
            }

            $code = (int) wp_remote_retrieve_response_code($resp);
            if ($code < 200 || $code >= 300) {
                throw new RuntimeException('HTTP ' . $code . ' from ' . $url);
            }

            $ok = true;
            $msg = 'PeerTube API OK.';
        } catch (Throwable $e) {
            $msg = $e->getMessage();
        }

        wp_send_json([
            'ok' => $ok,
            'message' => $msg,
        ]);
    }

    private static function ajax_guard(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }
        check_ajax_referer('ia_engine_nonce', 'nonce');
    }
}

// Optional helper functions for other plugins
if (!function_exists('ia_engine_get')) {
    function ia_engine_get(string $service): array {
        return IA_Engine::get($service);
    }
}
if (!function_exists('ia_engine_get_safe')) {
    function ia_engine_get_safe(string $service): array {
        return IA_Engine::get_safe($service);
    }
}
