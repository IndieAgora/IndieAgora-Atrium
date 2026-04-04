<?php
if (!defined('ABSPATH')) exit;

function ia_online_require(string $relative): void {
    $path = IA_ONLINE_PATH . ltrim($relative, '/');
    if (file_exists($path)) {
        require_once $path;
    }
}

function ia_online_table(): string {
    global $wpdb;
    return $wpdb->prefix . IA_ONLINE_TABLE_SUFFIX;
}


function ia_online_history_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'ia_online_presence_history';
}

function ia_online_route_history_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'ia_online_presence_route_history';
}

function ia_online_settings(): array {
    $defaults = [
        'window_seconds' => 300,
        'cleanup_hours' => 48,
        'track_admin' => 1,
        'track_guests' => 1,
        'show_ip' => 1,
    ];
    $settings = get_option(IA_ONLINE_OPTION_SETTINGS, []);
    return wp_parse_args(is_array($settings) ? $settings : [], $defaults);
}

function ia_online_now_mysql(): string {
    return current_time('mysql', true);
}

function ia_online_client_ip(): string {
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        $raw = isset($_SERVER[$key]) ? (string) $_SERVER[$key] : '';
        if ($raw === '') continue;
        $parts = array_map('trim', explode(',', $raw));
        foreach ($parts as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
    }
    return '';
}

function ia_online_guest_fingerprint(): string {
    $ip = ia_online_client_ip();
    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT']) : '';
    $raw = strtolower(trim($ip . '|' . $agent));
    if ($raw === '|') {
        $raw = 'anon|' . (isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '');
    }
    return 'fp_' . md5($raw);
}

function ia_online_set_guest_cookie(string $token): bool {
    if (headers_sent()) {
        return false;
    }
    $path = COOKIEPATH ?: '/';
    $domain = COOKIE_DOMAIN ?: '';
    $expires = time() + YEAR_IN_SECONDS;
    $ok = false;
    if (PHP_VERSION_ID >= 70300) {
        $ok = setcookie(IA_ONLINE_COOKIE, $token, [
            'expires' => $expires,
            'path' => $path,
            'domain' => $domain,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        $ok = setcookie(IA_ONLINE_COOKIE, $token, $expires, $path . '; samesite=Lax', $domain, is_ssl(), true);
    }
    if ($ok) {
        $_COOKIE[IA_ONLINE_COOKIE] = $token;
    }
    return $ok;
}

function ia_online_guest_key(): string {
    $existing = isset($_COOKIE[IA_ONLINE_COOKIE]) ? sanitize_text_field(wp_unslash($_COOKIE[IA_ONLINE_COOKIE])) : '';
    if ($existing !== '') return $existing;
    try {
        $token = wp_generate_uuid4();
    } catch (Throwable $e) {
        $token = md5(uniqid('ia_online_', true));
    }
    if (ia_online_set_guest_cookie($token)) {
        return $token;
    }
    return ia_online_guest_fingerprint();
}
