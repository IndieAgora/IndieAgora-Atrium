<?php
/**
 * Plugin Name: IA PeerTube Login Sync
 * Description: Allows local PeerTube users to log in to Atrium without separate signup by auto-creating/linking phpBB canonical users + WP shadow users.
 * Version: 0.1.1
 * Author: IndieAgora
 * Text Domain: ia-peertube-login-sync
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


define('IA_PTLS_VERSION', '0.1.1');
define('IA_PTLS_PATH', plugin_dir_path(__FILE__));
define('IA_PTLS_URL', plugin_dir_url(__FILE__));

require_once IA_PTLS_PATH . 'includes/class-ia-ptls.php';
require_once IA_PTLS_PATH . 'includes/admin/class-ia-ptls-admin.php';

add_action('plugins_loaded', function () {
    if (!class_exists('IA_Engine')) {
        // IA Engine must be active for credentials.
        return;
    }
    IA_PTLS::instance();
    IA_PTLS_Admin::init();
});
