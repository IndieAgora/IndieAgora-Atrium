<?php
if (!defined('ABSPATH')) exit;

function ia_discuss_is_atrium_page(): bool {
  // Mirrors the “detect [ia-atrium]” pattern used elsewhere.
  global $post;
  if ($post && isset($post->post_content) && has_shortcode($post->post_content, 'ia-atrium')) return true;
  return (bool) apply_filters('ia_discuss_should_enqueue', false);
}

function ia_discuss_clean_out_buffer(): void {
  if (ob_get_length()) { @ob_clean(); }
}

function ia_discuss_json_ok($data = []): void {
  ia_discuss_clean_out_buffer();
  nocache_headers();
  wp_send_json_success($data);
  wp_die();
}

function ia_discuss_json_err(string $message, int $status = 400, array $extra = []): void {
  ia_discuss_clean_out_buffer();
  nocache_headers();
  wp_send_json_error(['message' => $message] + $extra, $status);
  wp_die();
}
