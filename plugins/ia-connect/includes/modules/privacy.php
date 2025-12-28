<?php
if (!defined('ABSPATH')) exit;

final class ia_connect_module_privacy implements ia_connect_module_interface {

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_connect_update_privacy' => ['method' => 'ajax_update_privacy', 'public' => false],
    ];
  }

  public function ajax_update_privacy(): void {
    ia_connect_support_security::require_login();
    ia_connect_support_security::check_nonce('nonce');

    $user_id = get_current_user_id();

    $privacy = [
      'profile_public' => !empty($_POST['profile_public']),
      'show_activity'  => !empty($_POST['show_activity']),
      'allow_mentions' => !empty($_POST['allow_mentions']),
    ];

    update_user_meta($user_id, 'ia_connect_privacy', $privacy);

    wp_send_json_success([
      'privacy' => $privacy,
      'message' => 'Privacy updated.',
    ]);
  }
}
