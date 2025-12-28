<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Stream AJAX
 *
 * Provides read endpoints for:
 * - feed
 * - channels
 * - video
 * - comments
 *
 * Uses nonce verification but remains read-only for logged-out users.
 */

function ia_stream_ajax_boot(): void {

  // Feed
  add_action('wp_ajax_ia_stream_feed', 'ia_stream_ajax_feed');
  add_action('wp_ajax_nopriv_ia_stream_feed', 'ia_stream_ajax_feed');

  // Channels
  add_action('wp_ajax_ia_stream_channels', 'ia_stream_ajax_channels');
  add_action('wp_ajax_nopriv_ia_stream_channels', 'ia_stream_ajax_channels');

  // Video
  add_action('wp_ajax_ia_stream_video', 'ia_stream_ajax_video');
  add_action('wp_ajax_nopriv_ia_stream_video', 'ia_stream_ajax_video');

  // Comments
  add_action('wp_ajax_ia_stream_comments', 'ia_stream_ajax_comments');
  add_action('wp_ajax_nopriv_ia_stream_comments', 'ia_stream_ajax_comments');
}

/* ------------------------
 * Handlers
 * ---------------------- */

function ia_stream_ajax_feed(): void {
  if (function_exists('ia_stream_verify_nonce_or_die')) ia_stream_verify_nonce_or_die();

  $q = [
    'page'     => ia_stream_post_int('page', 1),
    'per_page' => ia_stream_post_int('per_page', 10),
  ];

  if (class_exists('IA_Stream_Module_Feed')) {
    $out = IA_Stream_Module_Feed::get_feed($q);
    wp_send_json($out);
  }

  wp_send_json(['ok' => false, 'error' => 'Feed module missing']);
}

function ia_stream_ajax_channels(): void {
  if (function_exists('ia_stream_verify_nonce_or_die')) ia_stream_verify_nonce_or_die();

  $q = [
    'page'     => ia_stream_post_int('page', 1),
    'per_page' => ia_stream_post_int('per_page', 24),
    'search'   => ia_stream_post_str('search', ''),
  ];

  if (class_exists('IA_Stream_Module_Channels')) {
    $out = IA_Stream_Module_Channels::get_channels($q);
    wp_send_json($out);
  }

  wp_send_json(['ok' => false, 'error' => 'Channels module missing']);
}

function ia_stream_ajax_video(): void {
  if (function_exists('ia_stream_verify_nonce_or_die')) ia_stream_verify_nonce_or_die();

  $id = ia_stream_post_str('id', '');

  if (class_exists('IA_Stream_Module_Video')) {
    $out = IA_Stream_Module_Video::get_video($id);
    wp_send_json($out);
  }

  wp_send_json(['ok' => false, 'error' => 'Video module missing']);
}

function ia_stream_ajax_comments(): void {
  if (function_exists('ia_stream_verify_nonce_or_die')) ia_stream_verify_nonce_or_die();

  $q = [
    'video_id' => ia_stream_post_str('video_id', ''),
    'page'     => ia_stream_post_int('page', 1),
    'per_page' => ia_stream_post_int('per_page', 20),
  ];

  if (class_exists('IA_Stream_Module_Comments')) {
    $out = IA_Stream_Module_Comments::get_comments($q);
    wp_send_json($out);
  }

  wp_send_json(['ok' => false, 'error' => 'Comments module missing']);
}
