<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Service_Upload {

  public function handle(string $field = 'file'): array {
    if (!is_user_logged_in()) throw new Exception('Login required');

    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
      throw new Exception('Missing file');
    }

    $file = $_FILES[$field];

    if (!function_exists('wp_handle_upload')) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $overrides = [
      'test_form' => false,
      'mimes' => null, // allow WP defaults
    ];

    $res = wp_handle_upload($file, $overrides);

    if (!is_array($res) || empty($res['url'])) {
      $msg = is_array($res) && !empty($res['error']) ? $res['error'] : 'Upload failed';
      throw new Exception($msg);
    }

    return [
      'url' => (string)$res['url'],
      'type' => (string)($res['type'] ?? ''),
      'file' => (string)($res['file'] ?? ''),
      'name' => (string)($file['name'] ?? ''),
      'size' => (int)($file['size'] ?? 0),
    ];
  }
}
