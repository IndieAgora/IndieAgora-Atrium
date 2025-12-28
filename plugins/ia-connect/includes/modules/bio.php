<?php
if (!defined('ABSPATH')) exit;

final class ia_connect_module_bio implements ia_connect_module_interface {

  public function boot(): void {
    // nothing else needed yet
  }

  public function ajax_routes(): array {
    return [
      'ia_connect_update_bio' => ['method' => 'ajax_update_bio', 'public' => false],
    ];
  }

  public function ajax_update_bio(): void {
    ia_connect_support_security::require_login();
    ia_connect_support_security::check_nonce('nonce');

    $user_id = get_current_user_id();
    $bio = isset($_POST['bio']) ? wp_unslash($_POST['bio']) : '';
    $bio = sanitize_textarea_field($bio);

    $res = wp_update_user([
      'ID'          => $user_id,
      'description' => $bio,
    ]);

    if (is_wp_error($res)) {
      wp_send_json_error(['message' => $res->get_error_message()], 400);
    }

    wp_send_json_success(['bio' => $bio, 'message' => 'Bio updated.']);
  }
}
