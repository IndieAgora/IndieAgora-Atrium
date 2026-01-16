<?php
if (!defined('ABSPATH')) exit;

class IA_PeerTube_Token_Helper {

    public static function boot() {
        IA_PT_Password_Capture::boot();
    }

    /**
     * Lazily mint or return a PeerTube user token for the current user.
     * This is the ONLY mint trigger in Atrium.
     */
    public static function get_token_for_current_user(): ?string {
        if (!is_user_logged_in()) return null;

        $wp_user_id = (int)get_current_user_id();
        $phpbb_user_id = IA_PT_Identity_Resolver::phpbb_id_from_wp($wp_user_id);
        if (!$phpbb_user_id) {
            self::set_identity_error($wp_user_id, 'No phpBB mapping found.');
            return null;
        }

        // Existing token?
        $row = IA_PeerTube_Token_Store::get($phpbb_user_id);
        if ($row && !empty($row['access_token_enc'])) {
            // Automatic refresh (just-in-time) to prevent expired-token 401s on write actions.
            if (class_exists('IA_PeerTube_Token_Refresh') && method_exists('IA_PeerTube_Token_Refresh', 'maybe_refresh')) {
                IA_PeerTube_Token_Refresh::maybe_refresh($phpbb_user_id, $row, 120);
                // Re-read in case the access token was updated.
                $row2 = IA_PeerTube_Token_Store::get($phpbb_user_id);
                if ($row2 && !empty($row2['access_token_enc'])) {
                    return self::decrypt($row2['access_token_enc']);
                }
            }
            return self::decrypt($row['access_token_enc']);
        }

        // Lazy mint
        $res = IA_PT_PeerTube_Mint::try_mint($wp_user_id, $phpbb_user_id);
        if (!$res['ok']) {
            self::set_identity_error($wp_user_id, $res['error']);
            IA_PeerTube_Token_Store::touch_mint_error($phpbb_user_id, $res['error']);
            return null;
        }

        IA_PeerTube_Token_Store::save_token_row(
            $phpbb_user_id,
            $res['access_enc'],
            $res['refresh_enc'] ?? null,
            $res['expires_at']
        );

        return self::decrypt($res['access_enc']);
    }

    private static function decrypt(string $enc): ?string {
        if (class_exists('IA_Engine_Crypto') && method_exists('IA_Engine_Crypto', 'decrypt')) {
            return IA_Engine_Crypto::decrypt($enc);
        }
        if (class_exists('IA_Engine') && method_exists('IA_Engine', 'decrypt')) {
            return IA_Engine::decrypt($enc);
        }
        return $enc;
    }

    private static function set_identity_error(int $wp_user_id, string $msg): void {
        global $wpdb;
        $t = IA_PT_IDENTITY_TABLE;
        $wpdb->update($t, [
            'last_error' => $msg ? substr($msg, 0, 1000) : null,
            'updated_at' => current_time('mysql', 1),
        ], ['wp_user_id' => $wp_user_id]);
        if (defined('WP_DEBUG') && WP_DEBUG && $msg) {
            error_log('[ia-peertube-token-mint-users] '.$msg);
        }
    }
}
