<?php
if (!defined('ABSPATH')) exit;

class IA_PeerTube_Token_Admin_Page {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_ia_pt_tokens_refresh_all', [$this, 'handle_refresh_all']);
        add_action('admin_post_ia_pt_tokens_validate_all', [$this, 'handle_validate_all']);
        add_action('admin_post_ia_pt_tokens_refresh_user', [$this, 'handle_refresh_user']);
        add_action('admin_post_ia_pt_tokens_validate_user', [$this, 'handle_validate_user']);
        add_action('admin_post_ia_pt_tokens_clear_user', [$this, 'handle_clear_user']);
    }

    public function register_menu() {
        add_menu_page(
            'PeerTube User Tokens',
            'PeerTube Tokens',
            'manage_options',
            'ia-peertube-user-tokens',
            [$this, 'render'],
            'dashicons-shield',
            81
        );
    }

    public function render() {
        if (isset($_GET['ia_pt_notice']) && is_string($_GET['ia_pt_notice'])) {
            $msg = sanitize_text_field(wp_unslash($_GET['ia_pt_notice']));
            if ($msg !== '') {
                echo '<div class="notice notice-info"><p>' . esc_html($msg) . '</p></div>';
            }
        }

        $users = get_users(['number' => 5000]);
        $token_rows = IA_PeerTube_Token_Store::get_all_indexed_by_phpbb();

        echo '<div class="wrap"><h1>PeerTube User Tokens</h1>';
        echo '<p>Token rows are keyed by phpBB user id (wp_ia_identity_map).</p>';

        echo '<div style="margin:12px 0; padding:12px; background:#fff; border:1px solid #ccd0d4; border-radius:6px;">';
        echo '<h2 style="margin-top:0;">Manual maintenance</h2>';
        echo '<p><strong>Refresh all</strong> will attempt an OAuth refresh_token grant for every row that has a refresh token stored.</p>';
        echo '<p><strong>Validate all</strong> will call <code>/api/v1/users/me</code> using the stored access token to detect invalid tokens.</p>';

        $refreshUrl = esc_url(admin_url('admin-post.php?action=ia_pt_tokens_refresh_all'));
        $validateUrl = esc_url(admin_url('admin-post.php?action=ia_pt_tokens_validate_all'));
        $nonce = wp_create_nonce('ia_pt_tokens_admin');

        echo '<form method="post" action="' . $refreshUrl . '" style="display:inline-block; margin-right:10px;">';
        echo '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">';
        echo '<button class="button button-primary" type="submit">Refresh tokens for all users</button>';
        echo '</form>';

        echo '<form method="post" action="' . $validateUrl . '" style="display:inline-block;">';
        echo '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">';
        echo '<button class="button" type="submit">Validate tokens for all users</button>';
        echo '</form>';
        echo '</div>';

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>User</th><th>WP ID</th><th>phpBB ID</th><th>Identity Status</th><th>Has Access</th><th>Has Refresh</th><th>Expires</th><th>Identity error</th><th>Token mint error</th><th>Last mint</th><th>Actions</th>';
        echo '</tr></thead><tbody>';

        foreach ($users as $u) {
            $wp_id = (int)$u->ID;
            $ident = IA_PT_Identity_Resolver::identity_from_wp($wp_id);
            $phpbb_id = $ident ? (int)$ident['phpbb_user_id'] : 0;
            $status = $ident ? $ident['status'] : '—';
            $ident_err = $ident ? ($ident['last_error'] ?? '') : '';

            $trow = ($phpbb_id && isset($token_rows[$phpbb_id])) ? $token_rows[$phpbb_id] : null;
            $has = ($trow && !empty($trow['access_token_enc'])) ? 'Yes' : 'No';
            $hasRefresh = ($trow && !empty($trow['refresh_token_enc'])) ? 'Yes' : 'No';
            $exp = ($trow && !empty($trow['expires_at'])) ? esc_html($trow['expires_at']) : '—';
            $mint_err = ($trow && !empty($trow['last_mint_error'])) ? $trow['last_mint_error'] : '';
            $mint_at = ($trow && !empty($trow['last_mint_at'])) ? esc_html($trow['last_mint_at']) : '—';

            $actionsHtml = '—';
            if ($phpbb_id) {
                $nonceRow = wp_create_nonce('ia_pt_tokens_admin');
                $basePost = admin_url('admin-post.php');
                $refreshU = esc_url($basePost . '?action=ia_pt_tokens_refresh_user&phpbb_user_id=' . (int)$phpbb_id);
                $validateU = esc_url($basePost . '?action=ia_pt_tokens_validate_user&phpbb_user_id=' . (int)$phpbb_id);
                $clearU = esc_url($basePost . '?action=ia_pt_tokens_clear_user&phpbb_user_id=' . (int)$phpbb_id);

                $actionsHtml = ''
                    . '<form method="post" action="' . $refreshU . '" style="display:inline-block; margin-right:6px;">'
                    . '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonceRow) . '">'
                    . '<button class="button button-small" type="submit">Refresh</button>'
                    . '</form>'
                    . '<form method="post" action="' . $validateU . '" style="display:inline-block; margin-right:6px;">'
                    . '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonceRow) . '">'
                    . '<button class="button button-small" type="submit">Validate</button>'
                    . '</form>'
                    . '<form method="post" action="' . $clearU . '" style="display:inline-block;">'
                    . '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonceRow) . '">'
                    . '<button class="button button-small" type="submit" onclick="return confirm(\'Clear stored tokens for this user?\')">Clear</button>'
                    . '</form>';
            }

            echo '<tr>';
            echo '<td>' . esc_html($u->user_login) . '</td>';
            echo '<td>' . esc_html($wp_id) . '</td>';
            echo '<td>' . esc_html($phpbb_id ?: '—') . '</td>';
            echo '<td>' . esc_html($status ?: '—') . '</td>';
            echo '<td>' . esc_html($has) . '</td>';
            echo '<td>' . esc_html($hasRefresh) . '</td>';
            echo '<td>' . $exp . '</td>';
            echo '<td>' . esc_html($ident_err ?: '—') . '</td>';
            echo '<td>' . esc_html($mint_err ?: '—') . '</td>';
            echo '<td>' . $mint_at . '</td>';
            echo '<td>' . $actionsHtml . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    private function admin_redirect_with_notice(string $msg): void {
        $url = admin_url('admin.php?page=ia-peertube-user-tokens&ia_pt_notice=' . rawurlencode($msg));
        wp_safe_redirect($url);
        exit;
    }

    public function handle_refresh_all(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer('ia_pt_tokens_admin');

        if (!class_exists('IA_PeerTube_Token_Store') || !class_exists('IA_PeerTube_Token_Refresh')) {
            $this->admin_redirect_with_notice('Token classes not available.');
        }

        $rows = IA_PeerTube_Token_Store::get_all();
        $total = count($rows);
        $refreshed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($rows as $r) {
            $phpbbId = (int)($r['phpbb_user_id'] ?? 0);
            if ($phpbbId <= 0) { $skipped++; continue; }
            if (empty($r['refresh_token_enc'])) { $skipped++; continue; }
            // Force refresh attempt by faking an expired timestamp.
            $rForce = $r;
            $rForce['expires_at'] = '1970-01-01 00:00:00';

            $res = IA_PeerTube_Token_Refresh::maybe_refresh($phpbbId, $rForce, 0);
            if (!empty($res['ok']) && !empty($res['did_refresh'])) {
                $refreshed++;
            } elseif (!empty($res['ok']) && empty($res['did_refresh'])) {
                $skipped++;
            } else {
                $failed++;
            }
        }

        $this->admin_redirect_with_notice("Refresh complete. rows={$total}, refreshed={$refreshed}, skipped={$skipped}, failed={$failed}.");
    }

    public function handle_validate_all(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden');
        }
        check_admin_referer('ia_pt_tokens_admin');

        if (!class_exists('IA_PeerTube_Token_Store') || !class_exists('IA_PeerTube_Token_Helper')) {
            $this->admin_redirect_with_notice('Token classes not available.');
        }

        $rows = IA_PeerTube_Token_Store::get_all();
        $total = count($rows);
        $ok = 0;
        $bad = 0;
        $skipped = 0;

        foreach ($rows as $r) {
            $phpbbId = (int)($r['phpbb_user_id'] ?? 0);
            if ($phpbbId <= 0) { $skipped++; continue; }
            if (empty($r['access_token_enc'])) { $skipped++; continue; }

            $v = IA_PeerTube_Token_Helper::validate_access_token_row($r);
            if (!empty($v['ok'])) {
                $ok++;
            } else {
                $bad++;
                if (!empty($v['message']) && is_string($v['message'])) {
                    IA_PeerTube_Token_Store::touch_mint_error($phpbbId, 'validate: ' . $v['message']);
                }
            }
        }

        $this->admin_redirect_with_notice("Validate complete. rows={$total}, ok={$ok}, bad={$bad}, skipped={$skipped}.");
    }

    public function handle_refresh_user(): void {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ia_pt_tokens_admin');
        $phpbbId = isset($_GET['phpbb_user_id']) ? (int)$_GET['phpbb_user_id'] : 0;
        if ($phpbbId <= 0) $this->admin_redirect_with_notice('Invalid phpBB user id.');

        $row = IA_PeerTube_Token_Store::get($phpbbId);
        if (!$row || empty($row['refresh_token_enc'])) {
            $this->admin_redirect_with_notice('No refresh token stored for phpBB id ' . $phpbbId . '.');
        }

        $row['expires_at'] = '1970-01-01 00:00:00';
        $res = IA_PeerTube_Token_Refresh::maybe_refresh($phpbbId, $row, 0);
        $msg = !empty($res['ok']) ? ('Refresh user ' . $phpbbId . ': OK. ' . ($res['message'] ?? '')) : ('Refresh user ' . $phpbbId . ': FAILED. ' . ($res['message'] ?? ''));
        $this->admin_redirect_with_notice($msg);
    }

    public function handle_validate_user(): void {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ia_pt_tokens_admin');
        $phpbbId = isset($_GET['phpbb_user_id']) ? (int)$_GET['phpbb_user_id'] : 0;
        if ($phpbbId <= 0) $this->admin_redirect_with_notice('Invalid phpBB user id.');
        $row = IA_PeerTube_Token_Store::get($phpbbId);
        if (!$row || empty($row['access_token_enc'])) {
            $this->admin_redirect_with_notice('No access token stored for phpBB id ' . $phpbbId . '.');
        }
        $v = IA_PeerTube_Token_Helper::validate_access_token_row($row);
        if (!empty($v['ok'])) {
            $this->admin_redirect_with_notice('Validate user ' . $phpbbId . ': OK.');
        }
        $msg = 'Validate user ' . $phpbbId . ': FAILED. ' . (string)($v['message'] ?? '');
        IA_PeerTube_Token_Store::touch_mint_error($phpbbId, 'validate: ' . $msg);
        $this->admin_redirect_with_notice($msg);
    }

    public function handle_clear_user(): void {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('ia_pt_tokens_admin');
        $phpbbId = isset($_GET['phpbb_user_id']) ? (int)$_GET['phpbb_user_id'] : 0;
        if ($phpbbId <= 0) $this->admin_redirect_with_notice('Invalid phpBB user id.');
        global $wpdb;
        $wpdb->update(IA_PT_TOKENS_TABLE, [
            'access_token_enc' => null,
            'refresh_token_enc' => null,
            'expires_at' => null,
            'last_refresh_at' => null,
            'last_mint_error' => 'cleared by admin',
            'updated_at' => current_time('mysql', 1),
        ], ['phpbb_user_id' => $phpbbId]);
        $this->admin_redirect_with_notice('Cleared stored tokens for phpBB id ' . $phpbbId . '.');
    }
}

new IA_PeerTube_Token_Admin_Page();
