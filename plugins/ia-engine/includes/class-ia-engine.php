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
     * Save a service config. Secrets are encrypted if provided.
     * If a secret field is missing from $cfg, it is preserved (not overwritten).
     */
    public static function set(string $service, array $cfg): bool {
        if (!current_user_can('manage_options')) return false;

        $service = strtolower(trim($service));
        $all = self::get_all();
        $prev = $all[$service] ?? [];
        if (!is_array($prev)) $prev = [];

        // Preserve existing secrets unless explicitly provided
        foreach (self::secret_fields($service) as $field) {
            if (array_key_exists($field, $cfg)) {
                $val = (string)$cfg[$field];
                if ($val === '') {
                    // blank means keep existing (admin UI uses "leave blank to keep existing")
                    if (isset($prev[$field])) {
                        $cfg[$field] = $prev[$field];
                    } else {
                        unset($cfg[$field]);
                    }
                } else {
                    $cfg[$field] = IA_Engine_Crypto::encrypt($val);
                }
            } else {
                // Not provided => preserve existing
                if (isset($prev[$field])) $cfg[$field] = $prev[$field];
            }
        }

        $all[$service] = $cfg;
        return (bool) update_option(self::OPTION_KEY, $all, true);
    }

    private static function secret_fields(string $service): array {
        $service = strtolower(trim($service));
        if ($service === 'phpbb') return ['password'];
        if ($service === 'peertube') return ['password'];        // DB password
        if ($service === 'peertube_api') return ['token'];       // API token
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
            'token'      => (string)($c['token'] ?? ''),
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
        $c = self::peertube_api();
        $u = trim((string)$c['public_url']);
        return rtrim($u, '/');
    }

    public static function peertube_api_token(): string {
        $c = self::peertube_api();
        return (string)$c['token'];
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
