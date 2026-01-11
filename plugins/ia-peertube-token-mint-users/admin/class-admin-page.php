<?php
if (!defined('ABSPATH')) exit;

class IA_PeerTube_Token_Admin_Page {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
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
        $users = get_users(['number' => 5000]);
        $token_rows = IA_PeerTube_Token_Store::get_all_indexed_by_phpbb();

        echo '<div class="wrap"><h1>PeerTube User Tokens</h1>';
        echo '<p>Read-only. Token rows are keyed by phpBB user id (wp_ia_identity_map).</p>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>User</th><th>WP ID</th><th>phpBB ID</th><th>Identity Status</th><th>Has Token</th><th>Expires</th><th>Identity error</th><th>Token mint error</th><th>Last mint</th>';
        echo '</tr></thead><tbody>';

        foreach ($users as $u) {
            $wp_id = (int)$u->ID;
            $ident = IA_PT_Identity_Resolver::identity_from_wp($wp_id);
            $phpbb_id = $ident ? (int)$ident['phpbb_user_id'] : 0;
            $status = $ident ? $ident['status'] : '—';
            $ident_err = $ident ? ($ident['last_error'] ?? '') : '';

            $trow = ($phpbb_id && isset($token_rows[$phpbb_id])) ? $token_rows[$phpbb_id] : null;
            $has = ($trow && !empty($trow['access_token_enc'])) ? 'Yes' : 'No';
            $exp = ($trow && !empty($trow['expires_at'])) ? esc_html($trow['expires_at']) : '—';
            $mint_err = ($trow && !empty($trow['last_mint_error'])) ? $trow['last_mint_error'] : '';
            $mint_at = ($trow && !empty($trow['last_mint_at'])) ? esc_html($trow['last_mint_at']) : '—';

            echo '<tr>';
            echo '<td>' . esc_html($u->user_login) . '</td>';
            echo '<td>' . esc_html($wp_id) . '</td>';
            echo '<td>' . esc_html($phpbb_id ?: '—') . '</td>';
            echo '<td>' . esc_html($status ?: '—') . '</td>';
            echo '<td>' . esc_html($has) . '</td>';
            echo '<td>' . $exp . '</td>';
            echo '<td>' . esc_html($ident_err ?: '—') . '</td>';
            echo '<td>' . esc_html($mint_err ?: '—') . '</td>';
            echo '<td>' . $mint_at . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }
}

new IA_PeerTube_Token_Admin_Page();
