<?php
if (!defined('ABSPATH')) exit;

class IA_PT_Password_Capture {
    private static array $buf = [];

    public static function boot(): void {
        // Fires for core WP auth flows (wp_signon/wp_authenticate)
        add_action('wp_authenticate', [__CLASS__, 'capture'], 5, 2);
        // Allow custom login handlers to provide password explicitly:
        // do_action('ia_pt_user_password', $wp_user_id, $password, $identifier);
        add_action('ia_pt_user_password', [__CLASS__, 'capture_for_user'], 10, 3);
    }

    public static function capture($username, $password): void {
        if (!is_string($username) || $username === '') return;
        if (!is_string($password) || $password === '') return;
        // store in memory only for this request
        self::$buf['username'] = $username;
        self::$buf['password'] = $password;
        self::$buf['ts'] = time();
    }

    public static function capture_for_user($wp_user_id, $password, $identifier=''): void {
        if (!is_numeric($wp_user_id)) return;
        if (!is_string($password) || $password === '') return;
        self::$buf['wp_user_id'] = (int)$wp_user_id;
        self::$buf['password'] = $password;
        self::$buf['identifier'] = is_string($identifier) ? $identifier : '';
        self::$buf['ts'] = time();
    }

    public static function get_for_wp_user(int $wp_user_id): ?string {
        // Prefer explicit user capture
        if (!empty(self::$buf['wp_user_id']) && (int)self::$buf['wp_user_id'] === $wp_user_id && !empty(self::$buf['password'])) {
            return self::$buf['password'];
        }
        // Fallback: if we only captured username, ensure it matches current user login
        $u = get_user_by('id', $wp_user_id);
        if ($u && !empty(self::$buf['username']) && $u->user_login === self::$buf['username'] && !empty(self::$buf['password'])) {
            return self::$buf['password'];
        }
        return null;
    }

    public static function get_identifier_for_wp_user(int $wp_user_id): string {
        if (!empty(self::$buf['wp_user_id']) && (int)self::$buf['wp_user_id'] === $wp_user_id && isset(self::$buf['identifier'])) {
            return (string)self::$buf['identifier'];
        }
        return '';
    }
}
