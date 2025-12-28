<?php
if (!defined('ABSPATH')) exit;

function ia_message_nonce_action(string $suffix): string {
  return IA_MESSAGE_AJAX_NS . '_' . $suffix;
}

function ia_message_nonce_field(string $suffix): string {
  return wp_create_nonce(ia_message_nonce_action($suffix));
}

function ia_message_verify_nonce(string $suffix, string $provided): void {
  if (!wp_verify_nonce($provided, ia_message_nonce_action($suffix))) {
    wp_send_json_error(['error' => 'bad_nonce'], 403);
  }
}

function ia_message_json_ok(array $data = []): void {
  wp_send_json_success($data, 200);
}

function ia_message_json_err(string $code, int $status = 400, array $extra = []): void {
  wp_send_json_error(array_merge(['error' => $code], $extra), $status);
}
