<?php
if (!defined('ABSPATH')) exit;

final class ia_connect_module_media implements ia_connect_module_interface {

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_connect_upload_avatar' => ['method' => 'ajax_upload_avatar', 'public' => false],
      'ia_connect_upload_cover'  => ['method' => 'ajax_upload_cover',  'public' => false],
    ];
  }

  public function ajax_upload_avatar(): void {
    $this->upload('avatar', 'ia_connect_avatar_id', 'thumbnail');
  }

  public function ajax_upload_cover(): void {
    $this->upload('cover', 'ia_connect_cover_id', 'large');
  }

  private function upload(string $field, string $meta_key, string $size): void {
    ia_connect_support_security::require_login();
    ia_connect_support_security::check_nonce('nonce');

    if (empty($_FILES[$field]['name'])) {
      wp_send_json_error(['message' => 'No file provided.'], 400);
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $user_id = get_current_user_id();
    $att_id = media_handle_upload($field, 0);

    if (is_wp_error($att_id)) {
      wp_send_json_error(['message' => $att_id->get_error_message()], 400);
    }

    update_user_meta($user_id, $meta_key, (int) $att_id);

    $url = wp_get_attachment_image_url($att_id, $size);
    wp_send_json_success([
      $field === 'avatar' ? 'avatarUrl' : 'coverUrl' => $url ?: '',
    ]);
  }
}
