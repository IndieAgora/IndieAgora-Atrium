<?php
/**
 * Plugin Name: IA PeerTube Token Mint Users
 * Description: Mints, stores, refreshes, and reports per-user PeerTube OAuth tokens.
 * Version: 0.1.13
 * Author: IndieAgora
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('ia_pt_trace_log')) {
    function ia_pt_trace_log(string $channel, array $context = []): void {
        if (!(defined('WP_DEBUG_LOG') && WP_DEBUG_LOG)) return;
        static $reqid = null;
        if ($reqid === null) {
            $seed = (string)($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)) . '|' . (string)($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string)($_SERVER['REQUEST_URI'] ?? '');
            $reqid = substr(md5($seed), 0, 12);
        }
        $bits = ['req=' . $reqid, 'ch=' . $channel];
        foreach ($context as $k => $v) {
            if (is_bool($v)) $v = $v ? '1' : '0';
            elseif (is_array($v)) $v = wp_json_encode($v);
            elseif ($v === null) $v = 'null';
            $v = preg_replace('/\s+/', ' ', (string)$v);
            if (strlen($v) > 240) $v = substr($v, 0, 240) . '…';
            $bits[] = $k . '=' . $v;
        }
        error_log('[IA_PT_TOKEN_TRACE] ' . implode(' | ', $bits));
    }
}


define('IA_PT_TOKENS_VERSION', '0.1.13');
define('IA_PT_TOKENS_PATH', plugin_dir_path(__FILE__));
define('IA_PT_TOKENS_TABLE', $GLOBALS['wpdb']->prefix . 'ia_peertube_user_tokens');
define('IA_PT_IDENTITY_TABLE', $GLOBALS['wpdb']->prefix . 'ia_identity_map');

add_action('plugins_loaded', function () {
    if (!class_exists('IA_Engine')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>IA PeerTube Token Mint Users requires ia-engine to be active.</p></div>';
        });
        return;
    }

    require_once IA_PT_TOKENS_PATH . 'includes/class-schema.php';
    require_once IA_PT_TOKENS_PATH . 'includes/class-identity-resolver.php';
    require_once IA_PT_TOKENS_PATH . 'includes/class-password-capture.php';
    require_once IA_PT_TOKENS_PATH . 'includes/class-peertube-mint.php';
    require_once IA_PT_TOKENS_PATH . 'includes/class-token-store.php';
    require_once IA_PT_TOKENS_PATH . 'includes/class-token-mint.php';
    require_once IA_PT_TOKENS_PATH . 'includes/class-token-refresh.php';
    require_once IA_PT_TOKENS_PATH . 'includes/class-token-helper.php';
    require_once IA_PT_TOKENS_PATH . 'admin/class-admin-page.php';

    IA_PT_Tokens_Schema::ensure();

    IA_PeerTube_Token_Helper::boot();
});

register_activation_hook(__FILE__, function () {
    global $wpdb;
    $table = IA_PT_TOKENS_TABLE;
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
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
});
