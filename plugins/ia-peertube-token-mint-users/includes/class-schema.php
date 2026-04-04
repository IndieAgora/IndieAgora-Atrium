<?php
if (!defined('ABSPATH')) exit;

class IA_PT_Tokens_Schema {

    public static function ensure(): void {
        // Run dbDelta on every load (cheap) to ensure columns exist after upgrades.
        // This avoids 'silent no-op' inserts when new columns are introduced.
        self::create_or_update_tokens_table();
    }

    private static function create_or_update_tokens_table(): void {
        global $wpdb;
        $table = IA_PT_TOKENS_TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            phpbb_user_id BIGINT NOT NULL,
            peertube_user_id BIGINT NULL,
            access_token_enc LONGTEXT NULL,
            refresh_token_enc LONGTEXT NULL,
            expires_at DATETIME NULL,
            last_refresh_at DATETIME NULL,
            last_mint_at DATETIME NULL,
            last_mint_error LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (phpbb_user_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
