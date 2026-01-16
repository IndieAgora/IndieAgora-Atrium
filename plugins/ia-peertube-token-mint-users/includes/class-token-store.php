<?php
if (!defined('ABSPATH')) exit;

class IA_PeerTube_Token_Store {

    /**
     * Back-compat alias used by some builds.
     */
    public static function save(int $phpbb_user_id, ?int $peertube_user_id, string $access_enc, ?string $refresh_enc, ?string $expires_at): bool {
        global $wpdb;
        $now = current_time('mysql', 1);

        $ok = $wpdb->replace(IA_PT_TOKENS_TABLE, [
            'phpbb_user_id'      => $phpbb_user_id,
            'peertube_user_id'   => $peertube_user_id,
            'access_token_enc'   => $access_enc,
            'refresh_token_enc'  => $refresh_enc,
            'expires_at'         => $expires_at,
            'last_refresh_at'    => $now,
            'last_mint_at'       => $now,
            'last_mint_error'    => null,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        if ($ok === false && !empty($wpdb->last_error)) {
            self::touch_mint_error($phpbb_user_id, 'DB replace failed: ' . $wpdb->last_error);
            return false;
        }
        return true;
    }

    public static function get_all(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM " . IA_PT_TOKENS_TABLE, ARRAY_A) ?: [];
    }

    public static function get_all_indexed_by_phpbb(): array {
        $rows = self::get_all();
        $out = [];
        foreach ($rows as $r) $out[(int)$r['phpbb_user_id']] = $r;
        return $out;
    }

    public static function get(int $phpbb_user_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM " . IA_PT_TOKENS_TABLE . " WHERE phpbb_user_id = %d", $phpbb_user_id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function save_token_row(int $phpbb_user_id, string $access_enc, ?string $refresh_enc, ?string $expires_at): bool {
        global $wpdb;
        $now = current_time('mysql', 1);

        $ok = $wpdb->replace(IA_PT_TOKENS_TABLE, [
            'phpbb_user_id'      => $phpbb_user_id,
            'peertube_user_id'   => null,
            'access_token_enc'   => $access_enc,
            'refresh_token_enc'  => $refresh_enc,
            'expires_at'         => $expires_at,
            'last_refresh_at'    => $now,
            'last_mint_at'       => $now,
            'last_mint_error'    => null,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        if ($ok === false && !empty($wpdb->last_error)) {
            self::touch_mint_error($phpbb_user_id, 'DB replace failed: ' . $wpdb->last_error);
            return false;
        }
        return true;
    }

    public static function touch_mint_error(int $phpbb_user_id, string $err): void {
        global $wpdb;
        $now = current_time('mysql', 1);
        $err = substr((string)$err, 0, 2000);

        // Ensure schema exists before writing
        if (class_exists('IA_PT_Tokens_Schema')) IA_PT_Tokens_Schema::ensure();

        $q = $wpdb->prepare(
            "INSERT INTO " . IA_PT_TOKENS_TABLE . " (phpbb_user_id, last_mint_at, last_mint_error, created_at, updated_at)
             VALUES (%d, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE last_mint_at=VALUES(last_mint_at), last_mint_error=VALUES(last_mint_error), updated_at=VALUES(updated_at)",
            $phpbb_user_id, $now, $err, $now, $now
        );
        $wpdb->query($q);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[ia-peertube-token-mint-users] mint_error phpbb_id='.$phpbb_user_id.' '.$err);
            if (!empty($wpdb->last_error)) error_log('[ia-peertube-token-mint-users] DB error: '.$wpdb->last_error);
        }
    }

    /**
     * Persist a refreshed access token + expiry without touching refresh token or mint timestamps.
     *
     * Used by IA_PeerTube_Token_Refresh::maybe_refresh().
     */
    public static function update_access(int $phpbb_user_id, string $access_enc, ?string $expires_at): bool {
        global $wpdb;
        $now = current_time('mysql', 1);

        // Ensure schema exists before writing
        if (class_exists('IA_PT_Tokens_Schema')) IA_PT_Tokens_Schema::ensure();

        $data = [
            'access_token_enc' => $access_enc,
            'expires_at'       => $expires_at,
            'last_refresh_at'  => $now,
            'updated_at'       => $now,
            'last_mint_error'  => null,
        ];

        $ok = $wpdb->update(IA_PT_TOKENS_TABLE, $data, ['phpbb_user_id' => $phpbb_user_id]);
        if ($ok === false) {
            if (!empty($wpdb->last_error)) {
                self::touch_mint_error($phpbb_user_id, 'DB update_access failed: ' . $wpdb->last_error);
            }
            return false;
        }

        // If the row didn't exist yet, fall back to inserting a minimal row.
        if ($ok === 0) {
            $q = $wpdb->prepare(
                "INSERT INTO " . IA_PT_TOKENS_TABLE . " (phpbb_user_id, access_token_enc, expires_at, last_refresh_at, created_at, updated_at)
                 VALUES (%d, %s, %s, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE access_token_enc=VALUES(access_token_enc), expires_at=VALUES(expires_at), last_refresh_at=VALUES(last_refresh_at), updated_at=VALUES(updated_at)",
                $phpbb_user_id, $access_enc, $expires_at, $now, $now, $now
            );
            $wpdb->query($q);
            if (!empty($wpdb->last_error)) {
                self::touch_mint_error($phpbb_user_id, 'DB upsert update_access failed: ' . $wpdb->last_error);
                return false;
            }
        }

        return true;
    }
}
