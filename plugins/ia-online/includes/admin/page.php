<?php
if (!defined('ABSPATH')) exit;

function ia_online_admin_boot(): void {
    add_action('admin_menu', 'ia_online_admin_menu');
}

function ia_online_admin_menu(): void {
    $parent = 'tools.php';
    global $menu;
    if (is_array($menu)) {
        foreach ($menu as $item) {
            if (!empty($item[2]) && strpos((string) $item[2], 'ia-atrium') !== false) {
                $parent = (string) $item[2];
                break;
            }
        }
    }

    add_submenu_page(
        $parent,
        'IA Online',
        'IA Online',
        'manage_options',
        'ia-online',
        'ia_online_render_admin_page'
    );
}

function ia_online_render_admin_page(): void {
    if (!current_user_can('manage_options')) {
        wp_die('Access denied.');
    }

    $active_tab = isset($_GET['tabview']) ? sanitize_key((string) $_GET['tabview']) : 'overview';
    if (!in_array($active_tab, ['overview', 'analytics', 'live'], true)) {
        $active_tab = 'overview';
    }

    $rows = ia_online_get_rows(true);
    $users = array_values(array_filter($rows, fn($row) => empty($row['is_guest'])));
    $guests = array_values(array_filter($rows, fn($row) => !empty($row['is_guest'])));
    $settings = ia_online_settings();
    $analytics_range = ia_online_admin_analytics_range();
    $history_rows = function_exists('ia_online_history_rows_between') ? ia_online_history_rows_between($analytics_range['from_gmt'], $analytics_range['to_gmt']) : [];
    $history_stats = function_exists('ia_online_history_stats_from_rows') ? ia_online_history_stats_from_rows($history_rows) : [];
    $route_rows = function_exists('ia_online_route_stats_between') ? ia_online_route_stats_between($analytics_range['from_gmt'], $analytics_range['to_gmt'], 10) : [];
    $user_points = [];
    $guest_points = [];
    $total_points = [];
    foreach ($history_rows as $row) {
        $bucket_time = (string) ($row['bucket_time'] ?? '');
        $label = ia_online_admin_chart_label($bucket_time, $analytics_range['mode']);
        $full_label = get_date_from_gmt($bucket_time, 'Y-m-d H:i');
        $user_points[] = ['label' => $label, 'full_label' => $full_label, 'value' => (int) ($row['users_online'] ?? 0)];
        $guest_points[] = ['label' => $label, 'full_label' => $full_label, 'value' => (int) ($row['guests_online'] ?? 0)];
        $total_points[] = ['label' => $label, 'full_label' => $full_label, 'value' => (int) ($row['total_sessions'] ?? 0)];
    }
    $chart_options = ia_online_admin_chart_options($history_rows, $analytics_range);
    ?>
    <div class="wrap ia-online-admin">
        <h1>IA Online</h1>
        <p>Atrium-aware live presence for mapped users and guests. Online window: <?php echo esc_html((string) ((int) $settings['window_seconds'] / 60)); ?> minutes.</p>

        <?php $page_url = menu_page_url('ia-online', false);
        if (!is_string($page_url) || $page_url === '') {
            $page_url = admin_url('tools.php?page=ia-online');
        } ?>
        <nav class="ia-online-tabs" aria-label="IA Online sections">
            <a href="<?php echo esc_url(add_query_arg('tabview', 'overview', $page_url)); ?>" class="ia-online-tab <?php echo $active_tab === 'overview' ? 'is-active' : ''; ?>">Overview</a>
            <a href="<?php echo esc_url(add_query_arg('tabview', 'analytics', $page_url)); ?>" class="ia-online-tab <?php echo $active_tab === 'analytics' ? 'is-active' : ''; ?>">Analytics</a>
            <a href="<?php echo esc_url(add_query_arg('tabview', 'live', $page_url)); ?>" class="ia-online-tab <?php echo $active_tab === 'live' ? 'is-active' : ''; ?>">Live sessions</a>
        </nav>

        <?php if ($active_tab === 'overview') : ?>
            <div class="ia-online-summary">
                <div class="ia-online-card"><strong><?php echo esc_html((string) count($users)); ?></strong><span>Users online</span></div>
                <div class="ia-online-card"><strong><?php echo esc_html((string) count($guests)); ?></strong><span>Guests online</span></div>
                <div class="ia-online-card"><strong><?php echo esc_html((string) count($rows)); ?></strong><span>Total sessions</span></div>
            </div>

            <div class="ia-online-overview-actions">
                <a class="button button-primary" href="<?php echo esc_url(add_query_arg('tabview', 'analytics', $page_url)); ?>">Open analytics</a>
                <a class="button" href="<?php echo esc_url(add_query_arg('tabview', 'live', $page_url)); ?>">Open live sessions</a>
            </div>
        <?php endif; ?>

        <?php if ($active_tab === 'analytics') : ?>
            <h2>Analytics</h2>
            <?php ia_online_render_analytics_filters($page_url, $analytics_range); ?>
            <div class="ia-online-summary ia-online-summary-analytics">
                <div class="ia-online-card"><strong><?php echo esc_html((string) ($history_stats['peak_users'] ?? 0)); ?></strong><span>Peak users</span></div>
                <div class="ia-online-card"><strong><?php echo esc_html((string) ($history_stats['peak_guests'] ?? 0)); ?></strong><span>Peak guests</span></div>
                <div class="ia-online-card"><strong><?php echo esc_html((string) ($history_stats['peak_total'] ?? 0)); ?></strong><span>Peak sessions</span></div>
                <div class="ia-online-card"><strong><?php echo esc_html((string) ($history_stats['avg_users'] ?? 0)); ?></strong><span>Avg users</span></div>
                <div class="ia-online-card"><strong><?php echo esc_html((string) ($history_stats['avg_guests'] ?? 0)); ?></strong><span>Avg guests</span></div>
                <div class="ia-online-card"><strong><?php echo esc_html((string) ($history_stats['samples'] ?? 0)); ?></strong><span>Minute samples</span></div>
            </div>

            <div class="ia-online-chart-grid">
                <?php ia_online_render_svg_chart($user_points, 'Users online', $chart_options); ?>
                <?php ia_online_render_svg_chart($guest_points, 'Guests online', $chart_options); ?>
                <?php ia_online_render_svg_chart($total_points, 'Total sessions', $chart_options); ?>
            </div>

            <h2>Popular routes</h2>
            <?php ia_online_render_route_table($route_rows); ?>

            <h2>Captured samples</h2>
            <?php ia_online_render_sample_table($history_rows, 72); ?>
        <?php endif; ?>

        <?php if ($active_tab === 'live') : ?>
            <h2>Users online</h2>
            <?php ia_online_render_admin_table($users, $settings, 'No active user sessions found.'); ?>

            <h2>Guests online</h2>
            <?php ia_online_render_admin_table($guests, $settings, 'No active guest sessions found.'); ?>
        <?php endif; ?>
    </div>
    <?php
}


function ia_online_admin_analytics_range(): array {
    $mode = isset($_GET['range']) ? sanitize_key((string) $_GET['range']) : '24h';
    $allowed = ['24h', '7d', '30d', 'custom'];
    if (!in_array($mode, $allowed, true)) {
        $mode = '24h';
    }
    $now = current_time('timestamp', true);
    $from_ts = $now - DAY_IN_SECONDS;
    if ($mode === '7d') {
        $from_ts = $now - (7 * DAY_IN_SECONDS);
    } elseif ($mode === '30d') {
        $from_ts = $now - (30 * DAY_IN_SECONDS);
    }

    $from_raw = isset($_GET['from']) ? (string) wp_unslash($_GET['from']) : '';
    $to_raw = isset($_GET['to']) ? (string) wp_unslash($_GET['to']) : '';
    if ($mode === 'custom' && $from_raw !== '' && $to_raw !== '') {
        $from_candidate = strtotime($from_raw);
        $to_candidate = strtotime($to_raw);
        if ($from_candidate !== false && $to_candidate !== false && $from_candidate <= $to_candidate) {
            $from_ts = $from_candidate;
            $to_ts = $to_candidate;
        } else {
            $to_ts = $now;
        }
    } else {
        $to_ts = $now;
    }
    if (!isset($to_ts)) {
        $to_ts = $now;
    }
    if ($from_ts > $to_ts) {
        $from_ts = $to_ts - DAY_IN_SECONDS;
    }

    $from_gmt = gmdate('Y-m-d H:i:00', $from_ts);
    $to_gmt = gmdate('Y-m-d H:i:59', $to_ts);
    return [
        'mode' => $mode,
        'from_ts' => $from_ts,
        'to_ts' => $to_ts,
        'from_gmt' => $from_gmt,
        'to_gmt' => $to_gmt,
        'from_local' => get_date_from_gmt($from_gmt, 'Y-m-d\TH:i'),
        'to_local' => get_date_from_gmt(gmdate('Y-m-d H:i:00', $to_ts), 'Y-m-d\TH:i'),
        'label' => get_date_from_gmt($from_gmt, 'Y-m-d H:i') . ' to ' . get_date_from_gmt($to_gmt, 'Y-m-d H:i'),
    ];
}

function ia_online_admin_chart_label(string $bucket_time, string $mode): string {
    if ($bucket_time === '') {
        return '';
    }
    $format = in_array($mode, ['7d', '30d'], true) ? 'd M H:i' : 'H:i';
    return get_date_from_gmt($bucket_time, $format);
}

function ia_online_admin_chart_options(array $history_rows, array $analytics_range): array {
    $count = count($history_rows);
    $mid_index = $count > 0 ? (int) floor(($count - 1) / 2) : 0;
    $peak_label = '';
    $peak_value = -1;
    foreach ($history_rows as $row) {
        $value = (int) ($row['total_sessions'] ?? 0);
        if ($value > $peak_value) {
            $peak_value = $value;
            $peak_label = get_date_from_gmt((string) ($row['bucket_time'] ?? ''), 'Y-m-d H:i');
        }
    }
    return [
        'range_label' => $analytics_range['label'],
        'start_label' => $count > 0 ? get_date_from_gmt((string) $history_rows[0]['bucket_time'], in_array($analytics_range['mode'], ['7d', '30d'], true) ? 'd M H:i' : 'H:i') : '',
        'mid_label' => $count > 0 ? get_date_from_gmt((string) $history_rows[$mid_index]['bucket_time'], in_array($analytics_range['mode'], ['7d', '30d'], true) ? 'd M H:i' : 'H:i') : '',
        'end_label' => $count > 0 ? get_date_from_gmt((string) $history_rows[$count - 1]['bucket_time'], in_array($analytics_range['mode'], ['7d', '30d'], true) ? 'd M H:i' : 'H:i') : '',
        'peak_label' => $peak_label,
    ];
}

function ia_online_render_analytics_filters(string $page_url, array $analytics_range): void {
    ?>
    <form method="get" class="ia-online-filters">
        <input type="hidden" name="page" value="ia-online" />
        <input type="hidden" name="tabview" value="analytics" />
        <div class="ia-online-filter-row">
            <label><span>Range</span>
                <select name="range">
                    <option value="24h" <?php selected($analytics_range['mode'], '24h'); ?>>Last 24 hours</option>
                    <option value="7d" <?php selected($analytics_range['mode'], '7d'); ?>>Last 7 days</option>
                    <option value="30d" <?php selected($analytics_range['mode'], '30d'); ?>>Last 30 days</option>
                    <option value="custom" <?php selected($analytics_range['mode'], 'custom'); ?>>Custom</option>
                </select>
            </label>
            <label><span>From</span>
                <input type="datetime-local" name="from" value="<?php echo esc_attr($analytics_range['from_local']); ?>" />
            </label>
            <label><span>To</span>
                <input type="datetime-local" name="to" value="<?php echo esc_attr($analytics_range['to_local']); ?>" />
            </label>
            <div class="ia-online-filter-actions">
                <button type="submit" class="button button-primary">Apply</button>
                <a class="button" href="<?php echo esc_url(add_query_arg(['tabview' => 'analytics', 'range' => '24h'], $page_url)); ?>">Reset</a>
            </div>
        </div>
        <p class="description">Showing <?php echo esc_html($analytics_range['label']); ?></p>
    </form>
    <?php
}


function ia_online_admin_row_context(array $row): string {
    $url = (string) ($row['current_url'] ?? '');
    return ia_online_request_context($url);
}

function ia_online_admin_display_url(string $url): string {
    if ($url === '') {
        return '';
    }
    $parts = wp_parse_url($url);
    if (!is_array($parts)) {
        return $url;
    }
    $path = isset($parts['path']) ? (string) $parts['path'] : '';
    $query = [];
    if (!empty($parts['query'])) {
        parse_str((string) $parts['query'], $query);
    }
    if ($path === '/wp-admin/admin-ajax.php') {
        return 'admin-ajax';
    }
    if ($path === '/wp-login.php') {
        return 'wp-login';
    }
    if ($path !== '' && strpos($path, '/wp-admin') === 0) {
        if (!empty($query['page'])) {
            return 'wp-admin/' . sanitize_key((string) $query['page']);
        }
        return 'wp-admin';
    }
    if ($path === '/' || $path === '') {
        if (!empty($query['tab'])) {
            return '/?tab=' . sanitize_key((string) $query['tab']);
        }
        return '/';
    }
    if (!empty($query['tab'])) {
        return $path . '?tab=' . sanitize_key((string) $query['tab']);
    }
    return $path;
}

function ia_online_render_admin_table(array $rows, array $settings, string $empty_message): void {
    ?>
    <div class="ia-online-table-wrap">
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Display</th>
                    <th>WP ID</th>
                    <th>phpBB ID</th>
                    <th>IP</th>
                    <th>Context</th>
                    <th>Route</th>
                    <th>URL</th>
                    <th>Last seen</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="9"><?php echo esc_html($empty_message); ?></td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo !empty($row['is_guest']) ? 'Guest' : 'User'; ?></td>
                        <td><?php echo esc_html((string) ($row['display_name'] ?: $row['login_name'] ?: 'Guest')); ?></td>
                        <td><?php echo esc_html((string) (int) $row['wp_user_id']); ?></td>
                        <td><?php echo esc_html((string) (int) $row['phpbb_user_id']); ?></td>
                        <td><?php echo !empty($settings['show_ip']) ? esc_html((string) $row['ip_address']) : 'Hidden'; ?></td>
                        <td><?php echo esc_html(ia_online_admin_row_context($row)); ?></td>
                        <td><?php echo esc_html((string) $row['current_route']); ?></td>
                        <td><code><?php echo esc_html(ia_online_admin_display_url((string) $row['current_url'])); ?></code></td>
                        <td><?php echo esc_html(get_date_from_gmt((string) $row['last_seen'], 'Y-m-d H:i:s')); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
