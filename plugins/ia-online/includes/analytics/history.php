<?php
if (!defined('ABSPATH')) exit;

function ia_online_bucket_minute(?int $timestamp = null): string {
    $timestamp = $timestamp ?? time();
    return gmdate('Y-m-d H:i:00', $timestamp);
}

function ia_online_maybe_capture_history(): void {
    global $wpdb;
    $bucket = ia_online_bucket_minute();
    $history_table = ia_online_history_table();
    $route_table = ia_online_route_history_table();

    $exists = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$history_table} WHERE bucket_time = %s LIMIT 1",
        $bucket
    ));
    if ($exists > 0) {
        return;
    }

    $rows = ia_online_get_rows(true);
    $users = 0;
    $guests = 0;
    $routes = [];
    foreach ($rows as $row) {
        if (!empty($row['is_guest'])) {
            $guests++;
        } else {
            $users++;
        }
        $route = sanitize_key((string) ($row['current_route'] ?? ''));
        if ($route === '') {
            $route = 'unknown';
        }
        $routes[$route] = (int) ($routes[$route] ?? 0) + 1;
    }

    $wpdb->insert(
        $history_table,
        [
            'bucket_time' => $bucket,
            'users_online' => $users,
            'guests_online' => $guests,
            'total_sessions' => count($rows),
            'created_at' => ia_online_now_mysql(),
        ],
        ['%s', '%d', '%d', '%d', '%s']
    );

    foreach ($routes as $route => $count) {
        $wpdb->insert(
            $route_table,
            [
                'bucket_time' => $bucket,
                'route' => $route,
                'session_count' => $count,
                'created_at' => ia_online_now_mysql(),
            ],
            ['%s', '%s', '%d', '%s']
        );
    }
}

function ia_online_history_rows(int $hours = 24): array {
    global $wpdb;
    $hours = max(1, $hours);
    $cutoff = gmdate('Y-m-d H:i:00', time() - ($hours * HOUR_IN_SECONDS));
    $table = ia_online_history_table();
    $sql = $wpdb->prepare(
        "SELECT bucket_time, users_online, guests_online, total_sessions FROM {$table} WHERE bucket_time >= %s ORDER BY bucket_time ASC",
        $cutoff
    );
    return $wpdb->get_results($sql, ARRAY_A) ?: [];
}

function ia_online_route_stats(int $hours = 24, int $limit = 10): array {
    global $wpdb;
    $hours = max(1, $hours);
    $limit = max(1, $limit);
    $cutoff = gmdate('Y-m-d H:i:00', time() - ($hours * HOUR_IN_SECONDS));
    $table = ia_online_route_history_table();
    $sql = $wpdb->prepare(
        "SELECT route, SUM(session_count) AS total_samples, MAX(session_count) AS peak_sessions, COUNT(*) AS bucket_count
         FROM {$table}
         WHERE bucket_time >= %s
         GROUP BY route
         ORDER BY total_samples DESC, peak_sessions DESC, route ASC
         LIMIT %d",
        $cutoff,
        $limit
    );
    return $wpdb->get_results($sql, ARRAY_A) ?: [];
}

function ia_online_history_stats(int $hours = 24): array {
    return ia_online_history_stats_from_rows(ia_online_history_rows($hours));
}


function ia_online_history_rows_between(string $from_gmt, string $to_gmt): array {
    global $wpdb;
    $table = ia_online_history_table();
    $sql = $wpdb->prepare(
        "SELECT bucket_time, users_online, guests_online, total_sessions FROM {$table} WHERE bucket_time >= %s AND bucket_time <= %s ORDER BY bucket_time ASC",
        $from_gmt,
        $to_gmt
    );
    return $wpdb->get_results($sql, ARRAY_A) ?: [];
}

function ia_online_route_stats_between(string $from_gmt, string $to_gmt, int $limit = 10): array {
    global $wpdb;
    $table = ia_online_route_history_table();
    $limit = max(1, $limit);
    $sql = $wpdb->prepare(
        "SELECT route, SUM(session_count) AS total_samples, MAX(session_count) AS peak_sessions, COUNT(*) AS bucket_count
         FROM {$table}
         WHERE bucket_time >= %s AND bucket_time <= %s
         GROUP BY route
         ORDER BY total_samples DESC, peak_sessions DESC, route ASC
         LIMIT %d",
        $from_gmt,
        $to_gmt,
        $limit
    );
    return $wpdb->get_results($sql, ARRAY_A) ?: [];
}

function ia_online_history_stats_from_rows(array $rows): array {
    if (!$rows) {
        return [
            'peak_users' => 0,
            'peak_guests' => 0,
            'peak_total' => 0,
            'avg_users' => 0,
            'avg_guests' => 0,
            'avg_total' => 0,
            'samples' => 0,
        ];
    }

    $peak_users = 0;
    $peak_guests = 0;
    $peak_total = 0;
    $sum_users = 0;
    $sum_guests = 0;
    $sum_total = 0;
    foreach ($rows as $row) {
        $users = (int) ($row['users_online'] ?? 0);
        $guests = (int) ($row['guests_online'] ?? 0);
        $total = (int) ($row['total_sessions'] ?? 0);
        $peak_users = max($peak_users, $users);
        $peak_guests = max($peak_guests, $guests);
        $peak_total = max($peak_total, $total);
        $sum_users += $users;
        $sum_guests += $guests;
        $sum_total += $total;
    }
    $samples = count($rows);
    return [
        'peak_users' => $peak_users,
        'peak_guests' => $peak_guests,
        'peak_total' => $peak_total,
        'avg_users' => round($sum_users / $samples, 1),
        'avg_guests' => round($sum_guests / $samples, 1),
        'avg_total' => round($sum_total / $samples, 1),
        'samples' => $samples,
    ];
}
