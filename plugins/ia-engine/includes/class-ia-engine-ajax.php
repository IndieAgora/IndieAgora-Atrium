<?php
if (!defined('ABSPATH')) exit;

final class IA_Engine_Ajax {

    public static function init(): void {
        add_action('wp_ajax_ia_engine_test_phpbb', [__CLASS__, 'test_phpbb']);
        add_action('wp_ajax_ia_engine_test_peertube_db', [__CLASS__, 'test_peertube_db']);
        add_action('wp_ajax_ia_engine_test_peertube_api', [__CLASS__, 'test_peertube_api']);
    }

    private static function guard(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json(['ok' => false, 'message' => 'Forbidden.'], 403);
        }
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'ia_engine_nonce')) {
            wp_send_json(['ok' => false, 'message' => 'Bad nonce.'], 403);
        }
    }

    public static function test_phpbb(): void {
        self::guard();

        $cfg = IA_Engine::get_all()['phpbb'] ?? [];
        if (!is_array($cfg)) $cfg = [];

        $host   = (string)($cfg['host'] ?? 'localhost');
        $port   = (int)($cfg['port'] ?? 3306);
        $db     = (string)($cfg['name'] ?? '');
        $user   = (string)($cfg['user'] ?? '');
        // IMPORTANT: get decrypted secret, not the masked "safe" value
        $pass   = IA_Engine::get_secret('phpbb', 'password');

        if ($db === '' || $user === '') {
            wp_send_json(['ok' => false, 'message' => 'phpBB DB settings incomplete (db/user).']);
        }
        if ($pass === null || $pass === '') {
            wp_send_json(['ok' => false, 'message' => "phpBB password not set (engine has no stored secret)."]);
        }

        mysqli_report(MYSQLI_REPORT_OFF);
        $mysqli = @mysqli_init();
        if (!$mysqli) {
            wp_send_json(['ok' => false, 'message' => 'mysqli_init failed.']);
        }

        @mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 3);

        $ok = @mysqli_real_connect($mysqli, $host, $user, $pass, $db, $port);

        if (!$ok) {
            $err = mysqli_connect_error();
            @mysqli_close($mysqli);
            wp_send_json(['ok' => false, 'message' => 'PHPBB MySQL connect failed: ' . $err]);
        }

        @mysqli_close($mysqli);
        wp_send_json(['ok' => true, 'message' => 'phpBB DB OK.']);
    }

    public static function test_peertube_db(): void {
        self::guard();

        if (!function_exists('pg_connect')) {
            wp_send_json(['ok' => false, 'message' => 'pg_connect unavailable (php-pgsql extension missing).']);
        }

        $cfg = IA_Engine::get_all()['peertube'] ?? [];
        if (!is_array($cfg)) $cfg = [];

        $host = (string)($cfg['host'] ?? '127.0.0.1');
        $port = (int)($cfg['port'] ?? 5432);
        $db   = (string)($cfg['name'] ?? '');
        $user = (string)($cfg['user'] ?? '');
        $pass = IA_Engine::get_secret('peertube', 'password');

        if ($db === '' || $user === '') {
            wp_send_json(['ok' => false, 'message' => 'PeerTube DB settings incomplete (db/user).']);
        }
        if ($pass === null || $pass === '') {
            wp_send_json(['ok' => false, 'message' => "PeerTube DB password not set (engine has no stored secret)."]);
        }

        $connStr = sprintf(
            "host=%s port=%d dbname=%s user=%s password=%s connect_timeout=3",
            addcslashes($host, " \t\n\r\0\x0B"),
            $port,
            addcslashes($db, " \t\n\r\0\x0B"),
            addcslashes($user, " \t\n\r\0\x0B"),
            addcslashes($pass, " \t\n\r\0\x0B")
        );

        $conn = @pg_connect($connStr);
        if (!$conn) {
            wp_send_json(['ok' => false, 'message' => 'PeerTube PostGres Connection failed (pg_connect).']);
        }

        @pg_close($conn);
        wp_send_json(['ok' => true, 'message' => 'PeerTube DB OK.']);
    }

    public static function test_peertube_api(): void {
        self::guard();

        $base = IA_Engine::peertube_internal_base_url();
        if (!$base) {
            wp_send_json(['ok' => false, 'message' => 'PeerTube API base URL not configured.']);
        }

        $url = rtrim($base, '/') . '/api/v1/config';

        $resp = wp_remote_get($url, [
            'timeout' => 5,
            'headers' => [
                'User-Agent' => 'IA-Engine/0.2.0',
            ],
        ]);

        if (is_wp_error($resp)) {
            wp_send_json(['ok' => false, 'message' => 'PeerTube API request failed: ' . $resp->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($resp);
        if ($code >= 200 && $code < 300) {
            wp_send_json(['ok' => true, 'message' => 'PeerTube API OK.']);
        }

        wp_send_json(['ok' => false, 'message' => 'PeerTube API returned HTTP ' . $code]);
    }
}
