<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Module_Upload implements IA_Discuss_Module_Interface {

  private $upload;

  public function __construct(IA_Discuss_Service_Upload $upload) {
    $this->upload = $upload;
  }

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_discuss_upload' => ['method' => 'ajax_upload', 'public' => false],
    ];
  }

  public function ajax_upload(): void {
    if (!is_user_logged_in()) ia_discuss_json_err('Login required', 401);

    try {
      $out = $this->upload->handle('file');
      ia_discuss_json_ok($out);
    } catch (Throwable $e) {
      ia_discuss_json_err('Upload error: ' . $e->getMessage(), 500);
    }
  }
}
