<?php
if (!defined('ABSPATH')) exit;

final class ia_connect_support_security {

  public static function boot(): void {
    // reserved for later
  }

  public static function require_login(): void {
    if (!is_user_logged_in()) {
      wp_send_json_error(['message' => 'Not logged in.'], 401);
    }
  }

  public static function check_nonce(string $field = 'nonce'): void {
    check_ajax_referer('ia_connect_nonce', $field);
  }
}
