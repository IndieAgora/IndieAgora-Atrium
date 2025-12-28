<?php
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Support_Security {

  public function verify_nonce_from_post(string $action, string $field = 'nonce'): void {
    $nonce = isset($_POST[$field]) ? (string) $_POST[$field] : '';
    if (!$nonce || !wp_verify_nonce($nonce, $action)) {
      ia_discuss_json_err('Bad nonce', 403);
    }
  }

  public function can_run_ajax(): bool {
    // Discuss is readable while logged out, so we do NOT require login here.
    // (Write endpoints later can require login here.)
    return true;
  }
}

function ia_discuss_security(): IA_Discuss_Support_Security {
  static $i = null;
  if (!$i) $i = new IA_Discuss_Support_Security();
  return $i;
}
