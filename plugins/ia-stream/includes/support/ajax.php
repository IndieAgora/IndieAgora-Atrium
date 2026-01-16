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

  // My subscriptions (login required)
  add_action('wp_ajax_ia_stream_my_subs', 'ia_stream_ajax_my_subs');

  // Write actions (login required)
  add_action('wp_ajax_ia_stream_rate', 'ia_stream_ajax_rate');
  add_action('wp_ajax_ia_stream_subscribe', 'ia_stream_ajax_subscribe');
  add_action('wp_ajax_ia_stream_comment_create', 'ia_stream_ajax_comment_create');
  add_action('wp_ajax_ia_stream_upload', 'ia_stream_ajax_upload');
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


function ia_stream_ajax_my_subs(): void {
  if (function_exists('ia_stream_verify_nonce_or_die')) ia_stream_verify_nonce_or_die();

  if (!is_user_logged_in()) {
    wp_send_json(['ok' => false, 'error' => 'login_required', 'message' => 'Login required.']);
  }

  $q = [
    'page' => ia_stream_post_int('page', 1),
    'per_page' => ia_stream_post_int('per_page', 24),
    'sort' => ia_stream_post_str('sort', '-publishedAt'),
  ];

  if (!class_exists('IA_Stream_Service_Auth')) {
    wp_send_json(['ok' => false, 'error' => 'Auth service missing']);
  }

  $auth = new IA_Stream_Service_Auth();
  $tok = $auth->ensure_user_bearer();
  if (empty($tok['ok'])) {
    wp_send_json($tok);
  }

  if (!class_exists('IA_Stream_Module_Subscriptions')) {
    wp_send_json(['ok' => false, 'error' => 'Subscriptions module missing']);
  }

  $out = IA_Stream_Module_Subscriptions::get_my_subscriptions($q, (string)$tok['bearer']);
  wp_send_json($out);
}

/* ------------------------
 * Write handlers
 * ---------------------- */

function ia_stream_ajax_rate(): void {
  if (function_exists('ia_stream_verify_nonce_or_die')) ia_stream_verify_nonce_or_die();

  if (!is_user_logged_in()) {
    wp_send_json(['ok' => false, 'error' => 'login_required', 'message' => 'Login required.']);
  }

  $id = ia_stream_post_str('video_id', '');
  $rating = ia_stream_post_str('rating', 'like');

  if (!class_exists('IA_Stream_Service_Auth')) {
    wp_send_json(['ok' => false, 'error' => 'Auth service missing']);
  }

  $auth = new IA_Stream_Service_Auth();
  $tok = $auth->ensure_user_bearer();
  if (empty($tok['ok'])) {
    wp_send_json($tok);
  }

  if (!class_exists('IA_Stream_Service_PeerTube_API')) {
    wp_send_json(['ok' => false, 'error' => 'PeerTube API service missing']);
  }

  $api = new IA_Stream_Service_PeerTube_API();
  $res = $api->rate_video($id, $rating, (string)$tok['bearer']);

  wp_send_json($res);
}

function ia_stream_ajax_subscribe(): void {
  if (function_exists('ia_stream_verify_nonce_or_die')) ia_stream_verify_nonce_or_die();

  if (!is_user_logged_in()) {
    wp_send_json(['ok' => false, 'error' => 'login_required', 'message' => 'Login required.']);
  }

  $uri = ia_stream_post_str('uri', '');

  $auth = new IA_Stream_Service_Auth();
  $tok = $auth->ensure_user_bearer();
  if (empty($tok['ok'])) {
    wp_send_json($tok);
  }

  $api = new IA_Stream_Service_PeerTube_API();
  $res = $api->subscribe_channel($uri, (string)$tok['bearer']);

  wp_send_json($res);
}

function ia_stream_ajax_comment_create(): void {
  if (function_exists('ia_stream_verify_nonce_or_die')) ia_stream_verify_nonce_or_die();

  if (!is_user_logged_in()) {
    wp_send_json(['ok' => false, 'error' => 'login_required', 'message' => 'Login required.']);
  }

  $video_id = ia_stream_post_str('video_id', '');
  $text = ia_stream_post_str('text', '');

  $auth = new IA_Stream_Service_Auth();
  $tok = $auth->ensure_user_bearer();
  if (empty($tok['ok'])) {
    wp_send_json($tok);
  }

  $api = new IA_Stream_Service_PeerTube_API();
  $res = $api->create_comment_thread($video_id, $text, (string)$tok['bearer']);

  wp_send_json($res);
}

function ia_stream_ajax_upload(): void {
  if (function_exists('ia_stream_verify_nonce_or_die')) ia_stream_verify_nonce_or_die();

  if (!is_user_logged_in()) {
    wp_send_json(['ok' => false, 'error' => 'login_required', 'message' => 'Login required.']);
  }

  if (empty($_FILES['videofile']) || !is_array($_FILES['videofile'])) {
    wp_send_json(['ok' => false, 'error' => 'Missing videofile']);
  }

  $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
  $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';

  $file = $_FILES['videofile'];
  $tmp = isset($file['tmp_name']) ? (string)$file['tmp_name'] : '';
  if ($tmp === '' || !file_exists($tmp)) {
    wp_send_json(['ok' => false, 'error' => 'Upload temp file missing']);
  }

  $auth = new IA_Stream_Service_Auth();
  $tok = $auth->ensure_user_bearer();
  if (empty($tok['ok'])) {
    wp_send_json($tok);
  }

  $api = new IA_Stream_Service_PeerTube_API();

  // Determine a channelId (best effort)
  $channel_id = 0;
  $chan_res = $api->get_my_default_channel_id((string)$tok['bearer']);
  if (!empty($chan_res['ok'])) {
    $channel_id = (int)($chan_res['channel_id'] ?? 0);
  }
  if ($channel_id <= 0) {
    wp_send_json(['ok' => false, 'error' => 'No channel available for upload (could not determine channelId).']);
  }

  $res = $api->upload_legacy($tmp, $channel_id, $name !== '' ? $name : basename((string)($file['name'] ?? 'video')), (string)$tok['bearer'], $description);

  wp_send_json($res);
}

