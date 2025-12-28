<?php
if (!defined('ABSPATH')) exit;

final class IA_PTLS_Admin {

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_post_ia_ptls_scan', [__CLASS__, 'handle_scan']);
        add_action('admin_post_ia_ptls_apply', [__CLASS__, 'handle_apply']);
        add_action('admin_post_ia_ptls_save_settings', [__CLASS__, 'handle_save_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function admin_menu(): void {
        add_menu_page(
            'IA PeerTube Login Sync',
            'IA PeerTube Sync',
            'manage_options',
            'ia-ptls',
            [__CLASS__, 'render_page'],
            'dashicons-video-alt3',
            81
        );
    }

    public static function enqueue_assets($hook): void {
        if ($hook !== 'toplevel_page_ia-ptls') return;
        wp_enqueue_style('ia-ptls-admin', IA_PTLS_URL . 'assets/css/admin.css', [], IA_PTLS_VERSION);
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) return;

        $last = get_option('ia_ptls_last_scan', []);
        $last_ts = !empty($last['ts']) ? (int)$last['ts'] : 0;
        $counts = is_array($last['counts'] ?? null) ? $last['counts'] : [];

        $cron_enabled = (get_option('ia_ptls_enable_cron', '0') === '1');
        $cron_minutes = (int)get_option('ia_ptls_cron_minutes', 60);
        if ($cron_minutes < 5) $cron_minutes = 5;
        if ($cron_minutes > 1440) $cron_minutes = 1440;

        $auto_apply = (get_option('ia_ptls_auto_apply', '0') === '1');

        $batch_size = (int)get_option('ia_ptls_batch_size', 50);
        if ($batch_size < 1) $batch_size = 1;
        if ($batch_size > 500) $batch_size = 500;

        $next_cron = wp_next_scheduled('ia_ptls_cron_scan');

        ?>
        <div class="wrap">
            <h1>IA PeerTube Login Sync</h1>

            <?php if (!class_exists('IA_Engine')): ?>
                <div class="notice notice-error"><p><strong>IA Engine is not active.</strong> This plugin requires IA Engine for credentials.</p></div>
            <?php endif; ?>

            <?php if (!class_exists('IA_Auth')): ?>
                <div class="notice notice-error"><p><strong>IA Auth is not active.</strong> This plugin depends on IA Auth’s identity map table.</p></div>
            <?php endif; ?>

            <div class="ia-ptls-grid">
                <div class="ia-ptls-card">
                    <h2>Status</h2>
                    <p><strong>Last run:</strong>
                        <?php echo $last_ts ? esc_html(gmdate('Y-m-d H:i:s', $last_ts) . ' UTC') : 'Never'; ?>
                    </p>
                    <ul>
                        <li><strong>Total PeerTube users:</strong> <?php echo esc_html((string)($counts['total'] ?? '—')); ?></li>
                        <li><strong>Mapped:</strong> <?php echo esc_html((string)($counts['mapped'] ?? '—')); ?></li>
                        <li><strong>Unmapped:</strong> <?php echo esc_html((string)($counts['unmapped'] ?? '—')); ?></li>
                    </ul>

                    <?php if (!empty($last['message'])): ?>
                        <p class="description"><?php echo esc_html((string)($last['message'])); ?></p>
                    <?php endif; ?>
                </div>

                <div class="ia-ptls-card">
                    <h2>Automation</h2>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('ia_ptls_settings_action', 'ia_ptls_nonce'); ?>
                        <input type="hidden" name="action" value="ia_ptls_save_settings">

                        <p>
                            <label>
                                <input type="checkbox" name="enable_cron" value="1" <?php checked($cron_enabled); ?>>
                                Enable automatic sync (WP-Cron)
                            </label>
                        </p>

                        <p>
                            <label>
                                Minutes between runs:
                                <input type="number" name="cron_minutes"
                                    value="<?php echo esc_attr((string)$cron_minutes); ?>"
                                    min="5" max="1440" style="width: 90px;">
                            </label>
                        </p>

                        <p>
                            <label>
                                <input type="checkbox" name="auto_apply" value="1" <?php checked($auto_apply); ?>>
                                Automatically map new PeerTube users (so they can log in)
                            </label>
                        </p>

                        <p>
                            <label>
                                Auto-apply batch size:
                                <input type="number" name="batch_size"
                                    value="<?php echo esc_attr((string)$batch_size); ?>"
                                    min="1" max="500" style="width: 90px;">
                            </label>
                        </p>

                        <p class="description">
                            WP-Cron runs on page traffic. When enabled, the job scans local PeerTube users and (if auto-apply is enabled)
                            creates/links phpBB + WP shadow users and writes the identity mapping.
                        </p>

                        <p><strong>Next scheduled run:</strong>
                            <?php echo $next_cron ? esc_html(gmdate('Y-m-d H:i:s', (int)$next_cron) . ' UTC') : 'Not scheduled'; ?>
                        </p>

                        <button class="button button-primary" type="submit">Save</button>
                    </form>
                </div>

                <div class="ia-ptls-card">
                    <h2>Scan</h2>
                    <p>Scan local PeerTube users and count mapped/unmapped users. This does not change anything.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('ia_ptls_scan_action', 'ia_ptls_nonce'); ?>
                        <input type="hidden" name="action" value="ia_ptls_scan">
                        <button class="button button-primary" type="submit">Run Scan</button>
                    </form>
                </div>

                <div class="ia-ptls-card">
                    <h2>Apply Sync</h2>
                    <p>Create/link canonical phpBB users and WP shadow users for any unmapped local PeerTube users.</p>
                    <p><strong>Note:</strong> This is safe to run multiple times. Existing mappings are reused.</p>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('ia_ptls_apply_action', 'ia_ptls_nonce'); ?>
                        <input type="hidden" name="action" value="ia_ptls_apply">
                        <label>
                            Batch size:
                            <input type="number" name="batch" value="<?php echo esc_attr((string)$batch_size); ?>" min="1" max="500" style="width: 90px;">
                        </label>
                        <button class="button button-secondary" type="submit">Apply Sync (Batch)</button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public static function handle_save_settings(): void {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ia_ptls_settings_action', 'ia_ptls_nonce');

        $enable = !empty($_POST['enable_cron']);
        $minutes = (int)($_POST['cron_minutes'] ?? 60);
        if ($minutes < 5) $minutes = 5;
        if ($minutes > 1440) $minutes = 1440;

        $auto_apply = !empty($_POST['auto_apply']);

        $batch = (int)($_POST['batch_size'] ?? 50);
        if ($batch < 1) $batch = 1;
        if ($batch > 500) $batch = 500;

        update_option('ia_ptls_enable_cron', $enable ? '1' : '0', false);
        update_option('ia_ptls_cron_minutes', (string)$minutes, false);
        update_option('ia_ptls_auto_apply', $auto_apply ? '1' : '0', false);
        update_option('ia_ptls_batch_size', (string)$batch, false);

        // Reschedule according to new settings
        IA_PTLS::instance()->reschedule_cron();

        wp_safe_redirect(admin_url('admin.php?page=ia-ptls'));
        exit;
    }

    public static function handle_scan(): void {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ia_ptls_scan_action', 'ia_ptls_nonce');

        $scan = IA_PTLS::instance()->scan_local_peertube_users();
        update_option('ia_ptls_last_scan', [
            'ts' => time(),
            'ok' => !empty($scan['ok']),
            'counts' => $scan['counts'] ?? [],
            'message' => $scan['message'] ?? '',
        ], false);

        wp_safe_redirect(admin_url('admin.php?page=ia-ptls'));
        exit;
    }

    public static function handle_apply(): void {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ia_ptls_apply_action', 'ia_ptls_nonce');

        $batch = max(1, min(500, (int)($_POST['batch'] ?? 50)));

        $scan = IA_PTLS::instance()->scan_local_peertube_users();
        if (empty($scan['ok'])) {
            update_option('ia_ptls_last_scan', [
                'ts' => time(),
                'ok' => false,
                'counts' => [],
                'message' => $scan['message'] ?? 'Scan failed.',
            ], false);
            wp_safe_redirect(admin_url('admin.php?page=ia-ptls'));
            exit;
        }

        $rows = $scan['rows'] ?? [];
        $processed = 0;

        foreach ($rows as $r) {
            if ($processed >= $batch) break;
            if (!empty($r['mapped_phpbb_user_id'])) continue;

            $pt_username = (string)($r['username'] ?? '');
            $pt_email = (string)($r['email'] ?? '');
            $pt_user_id = (int)($r['id'] ?? 0);

            if ($pt_email === '' || $pt_user_id <= 0) continue;

            // Optional safety: skip blocked
            if (!empty($r['blocked'])) continue;

            // Optional safety: require email verified
            if (isset($r['emailVerified']) && (int)$r['emailVerified'] !== 1) continue;

            $phpbb = IA_PTLS::instance()->phpbb_find_or_create_user($pt_username, $pt_email);
            if (!empty($phpbb['ok'])) {
                IA_PTLS::instance()->ensure_wp_shadow_user($phpbb['user'], $pt_user_id);
            }

            $processed++;
        }

        $scan2 = IA_PTLS::instance()->scan_local_peertube_users();
        update_option('ia_ptls_last_scan', [
            'ts' => time(),
            'ok' => !empty($scan2['ok']),
            'counts' => $scan2['counts'] ?? [],
            'message' => 'Applied sync batch of ' . $processed . '.',
        ], false);

        wp_safe_redirect(admin_url('admin.php?page=ia-ptls'));
        exit;
    }
}
