<?php
if (!defined('ABSPATH')) exit;

function ia_online_install_schema(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = ia_online_table();
    $history_table = ia_online_history_table();
    $route_history_table = ia_online_route_history_table();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        session_key varchar(191) NOT NULL,
        wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        phpbb_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        display_name varchar(191) NOT NULL DEFAULT '',
        login_name varchar(191) NOT NULL DEFAULT '',
        ip_address varchar(100) NOT NULL DEFAULT '',
        user_agent text NULL,
        current_route varchar(191) NOT NULL DEFAULT '',
        current_url text NULL,
        is_guest tinyint(1) unsigned NOT NULL DEFAULT 1,
        last_seen datetime NOT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY session_key (session_key),
        KEY wp_user_id (wp_user_id),
        KEY phpbb_user_id (phpbb_user_id),
        KEY is_guest (is_guest),
        KEY last_seen (last_seen)
    ) {$charset};";

    $history_sql = "CREATE TABLE {$history_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        bucket_time datetime NOT NULL,
        users_online int unsigned NOT NULL DEFAULT 0,
        guests_online int unsigned NOT NULL DEFAULT 0,
        total_sessions int unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY bucket_time (bucket_time)
    ) {$charset};";

    $route_history_sql = "CREATE TABLE {$route_history_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        bucket_time datetime NOT NULL,
        route varchar(191) NOT NULL DEFAULT '',
        session_count int unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY bucket_route (bucket_time, route),
        KEY route (route)
    ) {$charset};";

    dbDelta($sql);
    dbDelta($history_sql);
    dbDelta($route_history_sql);
    update_option(IA_ONLINE_OPTION_DB_VERSION, IA_ONLINE_DB_VERSION);
}

add_action('ia_online_cleanup_event', function (): void {
    global $wpdb;
    $settings = ia_online_settings();
    $hours = max(1, (int) $settings['cleanup_hours']);
    $cutoff = gmdate('Y-m-d H:i:s', time() - ($hours * HOUR_IN_SECONDS));
    $wpdb->query($wpdb->prepare("DELETE FROM " . ia_online_table() . " WHERE last_seen < %s", $cutoff));
    $wpdb->query($wpdb->prepare("DELETE FROM " . ia_online_history_table() . " WHERE bucket_time < %s", $cutoff));
    $wpdb->query($wpdb->prepare("DELETE FROM " . ia_online_route_history_table() . " WHERE bucket_time < %s", $cutoff));
});
