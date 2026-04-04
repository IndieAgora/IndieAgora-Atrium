<?php
if (!defined('ABSPATH')) exit;

function ia_online_ajax_boot(): void {
    add_action('wp_ajax_ia_online_ping', 'ia_online_ajax_ping');
    add_action('wp_ajax_nopriv_ia_online_ping', 'ia_online_ajax_ping');
}

function ia_online_ajax_ping(): void {
    $nonce = isset($_POST['nonce']) ? (string) wp_unslash($_POST['nonce']) : '';
    if ($nonce !== '' && !wp_verify_nonce($nonce, IA_ONLINE_NONCE_ACTION)) {
        wp_send_json_error(['message' => 'Invalid nonce.'], 403);
    }

    $route = isset($_POST['route']) ? sanitize_text_field((string) wp_unslash($_POST['route'])) : ia_online_detect_route();
    $url = isset($_POST['url']) ? esc_url_raw((string) wp_unslash($_POST['url'])) : ia_online_current_url();
    ia_online_upsert_presence([
        'current_route' => $route,
        'current_url' => $url,
    ]);

    wp_send_json_success(['ok' => true]);
}
