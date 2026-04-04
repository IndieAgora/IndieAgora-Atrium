<?php
if (!defined('ABSPATH')) exit;

function ia_online_render_svg_chart(array $points, string $label, array $options = []): void {
    $width = 720;
    $height = 180;
    $pad = 20;
    $count = count($points);
    $max = 0;
    foreach ($points as $point) {
        $max = max($max, (int) ($point['value'] ?? 0));
    }
    $max = max(1, $max);
    $range_label = isset($options['range_label']) ? (string) $options['range_label'] : '';
    $start_label = isset($options['start_label']) ? (string) $options['start_label'] : '';
    $mid_label = isset($options['mid_label']) ? (string) $options['mid_label'] : '';
    $end_label = isset($options['end_label']) ? (string) $options['end_label'] : '';
    $peak_label = isset($options['peak_label']) ? (string) $options['peak_label'] : '';
    $coords = [];
    if ($count === 1) {
        $value = (int) ($points[0]['value'] ?? 0);
        $x = (int) ($width / 2);
        $y = (int) round($height - $pad - (($value / $max) * ($height - ($pad * 2))));
        $coords[] = $x . ',' . $y;
    } else {
        foreach ($points as $index => $point) {
            $x = (int) round($pad + ($index * (($width - ($pad * 2)) / max(1, $count - 1))));
            $value = (int) ($point['value'] ?? 0);
            $y = (int) round($height - $pad - (($value / $max) * ($height - ($pad * 2))));
            $coords[] = $x . ',' . $y;
        }
    }
    ?>
    <div class="ia-online-chart-card">
        <h3><?php echo esc_html($label); ?></h3>
        <?php if ($range_label !== '') : ?><p class="ia-online-chart-range"><?php echo esc_html($range_label); ?></p><?php endif; ?>
        <svg class="ia-online-chart" viewBox="0 0 <?php echo esc_attr((string) $width); ?> <?php echo esc_attr((string) $height); ?>" preserveAspectRatio="none" aria-hidden="true">
            <line x1="20" y1="160" x2="700" y2="160" class="ia-online-chart-axis" />
            <line x1="20" y1="20" x2="20" y2="160" class="ia-online-chart-axis" />
            <polyline points="<?php echo esc_attr(implode(' ', $coords)); ?>" class="ia-online-chart-line" />
            <?php foreach ($points as $index => $point) :
                $point_x = $count === 1 ? (int) ($width / 2) : (int) round($pad + ($index * (($width - ($pad * 2)) / max(1, $count - 1))));
                $point_value = (int) ($point['value'] ?? 0);
                $point_y = (int) round($height - $pad - (($point_value / $max) * ($height - ($pad * 2))));
                $point_title = (string) (($point['full_label'] ?? ($point['label'] ?? '')) . ' — ' . $point_value);
            ?>
                <circle cx="<?php echo esc_attr((string) $point_x); ?>" cy="<?php echo esc_attr((string) $point_y); ?>" r="3" class="ia-online-chart-point">
                    <title><?php echo esc_html($point_title); ?></title>
                </circle>
            <?php endforeach; ?>
        </svg>
        <div class="ia-online-chart-axis-labels">
            <span><?php echo esc_html($start_label); ?></span>
            <span><?php echo esc_html($mid_label); ?></span>
            <span><?php echo esc_html($end_label); ?></span>
        </div>
        <div class="ia-online-chart-meta">
            <span>0</span>
            <span>Peak <?php echo esc_html((string) $max); ?><?php echo $peak_label !== '' ? ' at ' . esc_html($peak_label) : ''; ?></span>
        </div>
    </div>
    <?php
}

function ia_online_render_route_table(array $rows): void {
    ?>
    <div class="ia-online-table-wrap">
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Route</th>
                    <th>Samples</th>
                    <th>Peak sessions</th>
                    <th>Minutes seen</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="4">No route history captured yet.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo esc_html((string) ($row['route'] ?: 'unknown')); ?></td>
                        <td><?php echo esc_html((string) (int) ($row['total_samples'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) (int) ($row['peak_sessions'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) (int) ($row['bucket_count'] ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}


function ia_online_render_sample_table(array $rows, int $limit = 60): void {
    $rows = array_slice(array_reverse($rows), 0, max(1, $limit));
    ?>
    <div class="ia-online-table-wrap">
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Bucket time</th>
                    <th>Users</th>
                    <th>Guests</th>
                    <th>Total sessions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="4">No history captured for this range yet.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo esc_html(get_date_from_gmt((string) ($row['bucket_time'] ?? ''), 'Y-m-d H:i')); ?></td>
                        <td><?php echo esc_html((string) (int) ($row['users_online'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) (int) ($row['guests_online'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) (int) ($row['total_sessions'] ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
