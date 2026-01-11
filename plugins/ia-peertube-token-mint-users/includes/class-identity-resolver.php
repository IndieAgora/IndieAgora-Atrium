<?php
if (!defined('ABSPATH')) exit;

class IA_PT_Identity_Resolver {

    public static function phpbb_id_from_wp(int $wp_user_id): ?int {
        global $wpdb;
        $t = IA_PT_IDENTITY_TABLE;
        $val = $wpdb->get_var($wpdb->prepare("SELECT phpbb_user_id FROM {$t} WHERE wp_user_id = %d LIMIT 1", $wp_user_id));
        if ($val === null) return null;
        $id = (int)$val;
        return $id > 0 ? $id : null;
    }

    public static function identity_from_wp(int $wp_user_id): ?array {
        global $wpdb;
        $t = IA_PT_IDENTITY_TABLE;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE wp_user_id = %d LIMIT 1", $wp_user_id), ARRAY_A);
        return $row ?: null;
    }
}
