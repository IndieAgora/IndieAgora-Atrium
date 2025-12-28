<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Stream Security
 *
 * Stream is a "read surface" by default:
 * - Logged-out users can view feed/channels/video/comments (read-only).
 * - Write actions (future) would be gated here.
 *
 * This file provides:
 * - Nonce creation/verification helpers
 * - Simple request sanitizers
 */

function ia_stream_security_boot(): void {
  // nothing else required at boot
}

/**
 * Nonce action name (stable)
 */
function ia_stream_nonce_action(): string {
  return 'ia_stream_nonce';
}

/**
 * Create nonce for front-end JS
 */
function ia_stream_create_nonce(): string {
  return wp_create_nonce(ia_stream_nonce_action());
}

/**
 * Verify nonce sent by JS (POST: nonce=...)
 */
function ia_stream_verify_nonce_or_die(): void {
  $nonce = isset($_POST['nonce']) ? (string)$_POST['nonce'] : '';
  if (!$nonce || !wp_verify_nonce($nonce, ia_stream_nonce_action())) {
    wp_send_json([
      'ok' => false,
      'error' => 'Bad nonce',
    ]);
  }
}

/**
 * Safe int getter from POST
 */
function ia_stream_post_int(string $key, int $default = 0): int {
  if (!isset($_POST[$key])) return $default;
  return (int)$_POST[$key];
}

/**
 * Safe string getter from POST
 */
function ia_stream_post_str(string $key, string $default = ''): string {
  if (!isset($_POST[$key])) return $default;
  $v = (string)$_POST[$key];
  $v = wp_unslash($v);
  $v = trim($v);
  return $v;
}
