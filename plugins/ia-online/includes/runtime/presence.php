<?php
if (!defined('ABSPATH')) exit;

function ia_online_runtime_boot(): void {
    add_action('init', 'ia_online_ensure_guest_cookie', 1);
    add_action('init', 'ia_online_track_request', 40);
    add_action('wp_enqueue_scripts', 'ia_online_enqueue_frontend', 999);
    add_action('admin_enqueue_scripts', 'ia_online_enqueue_admin', 999);
}


function ia_online_ensure_guest_cookie(): void {
    if (is_user_logged_in()) {
        return;
    }
    $existing = isset($_COOKIE[IA_ONLINE_COOKIE]) ? sanitize_text_field((string) wp_unslash($_COOKIE[IA_ONLINE_COOKIE])) : '';
    if ($existing !== '') {
        return;
    }
    try {
        $token = wp_generate_uuid4();
    } catch (Throwable $e) {
        $token = md5(uniqid('ia_online_', true));
    }
    ia_online_set_guest_cookie($token);
}

function ia_online_should_track_request(): bool {
    $settings = ia_online_settings();
    if (is_admin() && empty($settings['track_admin']) && !wp_doing_ajax()) {
        return false;
    }
    if (wp_doing_cron()) return false;
    if (defined('REST_REQUEST') && REST_REQUEST) return false;
    return true;
}

function ia_online_track_request(): void {
    if (!ia_online_should_track_request()) return;

    $is_guest = !is_user_logged_in();
    $settings = ia_online_settings();
    if ($is_guest && empty($settings['track_guests'])) {
        return;
    }

    ia_online_upsert_presence([
        'current_route' => ia_online_detect_route(),
        'current_url' => ia_online_current_url(),
    ]);
    if (function_exists('ia_online_maybe_capture_history')) {
        ia_online_maybe_capture_history();
    }
}

function ia_online_enqueue_frontend(): void {
    if (is_admin()) return;
    wp_enqueue_script('ia-online-presence', IA_ONLINE_URL . 'assets/js/ia-online-presence.js', [], IA_ONLINE_VERSION, true);
    wp_localize_script('ia-online-presence', 'IA_ONLINE', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce(IA_ONLINE_NONCE_ACTION),
        'intervalMs' => 60000,
    ]);
}

function ia_online_enqueue_admin(): void {
    wp_enqueue_style('ia-online-admin', IA_ONLINE_URL . 'assets/css/ia-online-admin.css', [], IA_ONLINE_VERSION);
}

function ia_online_identity_for_wp_user(int $wp_user_id): array {
    global $wpdb;
    $phpbb_user_id = (int) get_user_meta($wp_user_id, 'ia_phpbb_user_id', true);
    if ($phpbb_user_id <= 0) {
        $phpbb_user_id = (int) get_user_meta($wp_user_id, 'phpbb_user_id', true);
    }

    $display_name = '';
    $login_name = '';
    $user = $wp_user_id > 0 ? get_userdata($wp_user_id) : null;
    if ($user) {
        $display_name = (string) ($user->display_name ?: $user->user_login);
        $login_name = (string) $user->user_login;
    }

    if ($wpdb && $phpbb_user_id <= 0) {
        $table = $wpdb->prefix . 'ia_identity_map';
        $found_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($found_table === $table) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT phpbb_user_id, phpbb_username_clean FROM {$table} WHERE wp_user_id = %d LIMIT 1",
                $wp_user_id
            ), ARRAY_A);
            if (is_array($row)) {
                $phpbb_user_id = (int) ($row['phpbb_user_id'] ?? 0);
                if ($login_name === '' && !empty($row['phpbb_username_clean'])) {
                    $login_name = (string) $row['phpbb_username_clean'];
                }
            }
        }
    }

    return [
        'wp_user_id' => $wp_user_id,
        'phpbb_user_id' => $phpbb_user_id,
        'display_name' => $display_name,
        'login_name' => $login_name,
    ];
}

function ia_online_request_context(?string $url = null): string {
    $url = $url !== null ? $url : ia_online_current_url();
    $path = parse_url($url, PHP_URL_PATH);
    $path = is_string($path) ? (string) $path : '';
    if ($path !== '' && strpos($path, '/wp-admin/admin-ajax.php') !== false) {
        return 'ajax';
    }
    if ($path !== '' && strpos($path, '/wp-login.php') !== false) {
        return 'login';
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return 'rest';
    }
    if (is_admin()) {
        return 'admin';
    }
    return 'frontend';
}

function ia_online_describe_route_from_url(string $url, string $fallback = ''): string {
    $context = ia_online_request_context($url);
    $path = parse_url($url, PHP_URL_PATH);
    $path = is_string($path) ? trim($path, '/') : '';
    $query = parse_url($url, PHP_URL_QUERY);
    $params = [];
    if (is_string($query) && $query !== '') {
        parse_str($query, $params);
    }
    $tab = isset($params['tab']) ? sanitize_key((string) $params['tab']) : '';

    if ($context === 'ajax') {
        return 'ajax';
    }
    if ($context === 'login') {
        return 'login';
    }
    if ($context === 'admin') {
        if (isset($params['page']) && $params['page'] !== '') {
            return 'admin/' . sanitize_key((string) $params['page']);
        }
        return 'admin';
    }
    if ($tab !== '') {
        if ($tab === 'discuss') {
            if (!empty($params['iad_topic'])) {
                return 'discuss/topic-' . (int) $params['iad_topic'];
            }
            if (!empty($params['iad_forum'])) {
                return 'discuss/agora-' . (int) $params['iad_forum'];
            }
            if (!empty($params['sort'])) {
                return 'discuss/' . sanitize_key((string) $params['sort']);
            }
            return 'discuss';
        }
        if ($tab === 'connect') {
            if (!empty($params['ia_profile'])) {
                return 'connect/profile-' . (int) $params['ia_profile'];
            }
            if (!empty($params['ia_post'])) {
                return 'connect/post-' . (int) $params['ia_post'];
            }
            return 'connect';
        }
        if ($tab === 'stream') {
            return 'stream';
        }
        if ($tab === 'message' || $tab === 'messages') {
            return 'messages';
        }
        return $tab;
    }
    if ($path !== '') {
        return $path;
    }
    if ($fallback !== '') {
        return $fallback;
    }
    return 'home';
}

function ia_online_detect_route(): string {
    $tab = isset($_REQUEST['tab']) ? sanitize_key((string) wp_unslash($_REQUEST['tab'])) : '';
    if ($tab !== '') {
        return ia_online_describe_route_from_url(ia_online_current_url(), $tab);
    }
    return ia_online_describe_route_from_url(ia_online_current_url());
}

function ia_online_is_meaningful_live_route(string $route, string $url): bool {
    $context = ia_online_request_context($url);
    if (in_array($context, ['ajax', 'admin', 'login', 'rest'], true)) {
        return false;
    }
    return $route !== '' && $route !== 'home';
}

function ia_online_current_url(): string {
    $scheme = is_ssl() ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
    $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    $uri = is_string($uri) ? $uri : '';
    return $host !== '' ? $scheme . '://' . $host . $uri : $uri;
}

function ia_online_session_key(array $identity): string {
    if (!empty($identity['wp_user_id'])) {
        return 'u:' . (int) $identity['wp_user_id'];
    }
    return 'g:' . ia_online_guest_key();
}

function ia_online_upsert_presence(array $overrides = []): void {
    global $wpdb;
    $identity = is_user_logged_in() ? ia_online_identity_for_wp_user(get_current_user_id()) : [
        'wp_user_id' => 0,
        'phpbb_user_id' => 0,
        'display_name' => 'Guest',
        'login_name' => 'guest',
    ];

    $session_key = ia_online_session_key($identity);
    $now = ia_online_now_mysql();
    $route = isset($overrides['current_route']) ? sanitize_text_field((string) $overrides['current_route']) : ia_online_detect_route();
    $url = isset($overrides['current_url']) ? esc_url_raw((string) $overrides['current_url']) : ia_online_current_url();
    $ip = ia_online_client_ip();
    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 500) : '';

    $data = [
        'session_key' => $session_key,
        'wp_user_id' => (int) ($identity['wp_user_id'] ?? 0),
        'phpbb_user_id' => (int) ($identity['phpbb_user_id'] ?? 0),
        'display_name' => (string) ($identity['display_name'] ?? ''),
        'login_name' => (string) ($identity['login_name'] ?? ''),
        'ip_address' => $ip,
        'user_agent' => $agent,
        'current_route' => $route,
        'current_url' => $url,
        'is_guest' => empty($identity['wp_user_id']) ? 1 : 0,
        'last_seen' => $now,
        'updated_at' => $now,
    ];

    $existing_row = $wpdb->get_row($wpdb->prepare(
        'SELECT id, current_route, current_url FROM ' . ia_online_table() . ' WHERE session_key = %s LIMIT 1',
        $session_key
    ), ARRAY_A);
    $existing_id = is_array($existing_row) ? (int) ($existing_row['id'] ?? 0) : 0;

    if ($existing_id > 0 && !ia_online_is_meaningful_live_route($route, $url)) {
        $existing_route = is_array($existing_row) ? (string) ($existing_row['current_route'] ?? '') : '';
        $existing_url = is_array($existing_row) ? (string) ($existing_row['current_url'] ?? '') : '';
        if (ia_online_is_meaningful_live_route($existing_route, $existing_url)) {
            $data['current_route'] = $existing_route;
            $data['current_url'] = $existing_url;
        }
    }

    if ($existing_id <= 0 && !empty($data['is_guest'])) {
        $window = max(60, (int) ia_online_settings()['window_seconds']);
        $recent_cutoff = gmdate('Y-m-d H:i:s', time() - $window);
        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . ia_online_table() . ' WHERE is_guest = 1 AND ip_address = %s AND user_agent = %s AND last_seen >= %s ORDER BY last_seen DESC, id DESC LIMIT 1',
            $ip,
            $agent,
            $recent_cutoff
        ));
    }

    if ($existing_id > 0) {
        $wpdb->update(
            ia_online_table(),
            $data,
            ['id' => $existing_id],
            ['%s','%d','%d','%s','%s','%s','%s','%s','%s','%d','%s','%s'],
            ['%d']
        );
        if (!empty($data['is_guest'])) {
            ia_online_dedupe_guest_rows($session_key, $ip, $agent);
        }
        return;
    }

    $data['created_at'] = $now;
    $wpdb->insert(
        ia_online_table(),
        $data,
        ['%s','%d','%d','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s']
    );
    if (!empty($data['is_guest'])) {
        ia_online_dedupe_guest_rows($session_key, $ip, $agent);
    }
}


function ia_online_dedupe_guest_rows(string $session_key, string $ip, string $agent): void {
    global $wpdb;
    if ($session_key === '' || strpos($session_key, 'g:') !== 0) {
        return;
    }
    $table = ia_online_table();
    $window = max(60, (int) ia_online_settings()['window_seconds']);
    $recent_cutoff = gmdate('Y-m-d H:i:s', time() - $window);
    $keeper_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE session_key = %s ORDER BY last_seen DESC, id DESC LIMIT 1",
        $session_key
    ));
    if ($keeper_id <= 0) {
        return;
    }
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table} WHERE is_guest = 1 AND id <> %d AND last_seen >= %s AND ((session_key = %s) OR (ip_address = %s AND user_agent = %s))",
        $keeper_id,
        $recent_cutoff,
        $session_key,
        $ip,
        $agent
    ));
}

function ia_online_get_rows(bool $online_only = true): array {
    global $wpdb;
    $settings = ia_online_settings();
    $window = max(60, (int) $settings['window_seconds']);
    $table = ia_online_table();
    if ($online_only) {
        $cutoff = gmdate('Y-m-d H:i:s', time() - $window);
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE last_seen >= %s ORDER BY is_guest ASC, last_seen DESC, id DESC", $cutoff);
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }
    return $wpdb->get_results("SELECT * FROM {$table} ORDER BY last_seen DESC, id DESC LIMIT 250", ARRAY_A) ?: [];
}
