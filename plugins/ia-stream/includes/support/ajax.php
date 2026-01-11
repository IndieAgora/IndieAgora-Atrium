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

  // Comment thread tree (replies)
  add_action('wp_ajax_ia_stream_comment_thread', 'ia_stream_ajax_comment_thread');
  add_action('wp_ajax_nopriv_ia_stream_comment_thread', 'ia_stream_ajax_comment_thread');

  // Write actions (logged-in only)
  add_action('wp_ajax_ia_stream_comment_create', 'ia_stream_ajax_comment_create');
  add_action('wp_ajax_ia_stream_comment_reply', 'ia_stream_ajax_comment_reply');

  // Video rating (logged-in only)
  add_action('wp_ajax_ia_stream_video_rate', 'ia_stream_ajax_video_rate');

  // Comment rating (local, logged-in only)
  add_action('wp_ajax_ia_stream_comment_rate', 'ia_stream_ajax_comment_rate');

  // Comment deletion (logged-in only)
  add_action('wp_ajax_ia_stream_comment_delete', 'ia_stream_ajax_comment_delete');

  // One-time PeerTube token mint helper (logged-in only).
  // Atrium auth is state-based (phpBB canonical), so Stream may need to prompt
  // for a password once to mint a per-user PeerTube token.
  add_action('wp_ajax_ia_stream_pt_mint_token', 'ia_stream_ajax_pt_mint_token');

  // Debug (logged-in only): returns current WP user + linked phpBB ID.
  add_action('wp_ajax_ia_stream_whoami', 'ia_stream_ajax_whoami');
}

/**
 * Rate a video (like/dislike) as the current Atrium user.
 *
 * Requires an OAuth user token. If the user doesn't have one yet, this
 * returns code=missing_user_token so the frontend can trigger the existing
 * Stream mint modal and retry.
 */
function ia_stream_ajax_video_rate(): void {
  if (function_exists('ia_stream_verify_nonce_or_die')) ia_stream_verify_nonce_or_die();
  if (!is_user_logged_in()) {
    wp_send_json(['ok' => false, 'error' => 'Login required']);
  }

  $video_id = ia_stream_post_str('id', '');
  $rating = ia_stream_post_str('rating', '');
  $video_id = trim((string) $video_id);
  $rating = trim((string) $rating);

  if ($video_id === '') wp_send_json(['ok' => false, 'error' => 'Missing video id']);
  if ($rating !== 'like' && $rating !== 'dislike') {
    wp_send_json(['ok' => false, 'error' => 'Invalid rating']);
  }

  if (!class_exists('IA_PeerTube_Token_Helper') || !method_exists('IA_PeerTube_Token_Helper', 'get_token_for_current_user')) {
    wp_send_json(['ok' => false, 'error' => 'Token helper missing']);
  }

  // We intentionally do not prompt here; the frontend handles the prompt.
  $token = '';
  try {
    $token = (string) IA_PeerTube_Token_Helper::get_token_for_current_user();
  } catch (Throwable $e) {
    // get_token_for_current_user records last_mint_error in the token table.
    $token = '';
  }

  if (trim($token) === '') {
    // Surface last mint error if possible (common case: missing_user_token)
    $detail = 'missing_user_token';
    global $wpdb;
    if ($wpdb instanceof wpdb) {
      $u = wp_get_current_user();
      $wp_user_id = (int) ($u && isset($u->ID) ? $u->ID : 0);
      if ($wp_user_id > 0) {
        $map = $wpdb->prefix . 'ia_identity_map';
        $row = $wpdb->get_row($wpdb->prepare("SELECT phpbb_user_id FROM {$map} WHERE wp_user_id=%d LIMIT 1", $wp_user_id), ARRAY_A);
        $phpbb_user_id = (is_array($row) && isset($row['phpbb_user_id'])) ? (int) $row['phpbb_user_id'] : 0;
        if ($phpbb_user_id > 0) {
          $tt = $wpdb->prefix . 'ia_peertube_user_tokens';
          $r2 = $wpdb->get_row($wpdb->prepare("SELECT last_mint_error FROM {$tt} WHERE phpbb_user_id=%d LIMIT 1", $phpbb_user_id), ARRAY_A);
          if (is_array($r2) && !empty($r2['last_mint_error'])) {
            $detail = (string) $r2['last_mint_error'];
          }
        }
      }
    }

    wp_send_json([
      'ok' => false,
      'code' => 'missing_user_token',
      'error' => $detail,
    ]);
  }

  if (!class_exists('IA_Stream_Service_PeerTube_API')) {
    wp_send_json(['ok' => false, 'error' => 'PeerTube API service missing']);
  }

  $api = new IA_Stream_Service_PeerTube_API();
  $api->set_token($token);

  $r = $api->rate_video($video_id, $rating);
  if (!is_array($r) || empty($r['ok'])) {
    $msg = is_array($r) && !empty($r['error']) ? (string) $r['error'] : 'Rate failed';
    wp_send_json(['ok' => false, 'error' => $msg, 'raw' => $r]);
  }

  // Return updated counts by refetching the video.
  $v = $api->get_video($video_id);
  if (is_array($v) && !empty($v['ok']) && isset($v['data']) && is_array($v['data'])) {
    $base = '';
    if (method_exists($api, 'public_base')) $base = (string) $api->public_base();
    $item = function_exists('ia_stream_norm_video') ? ia_stream_norm_video($v['data'], $base) : $v['data'];
    wp_send_json(['ok' => true, 'item' => $item]);
  }

  wp_send_json(['ok' => true]);
}

/**
 * Rate a comment (like/dislike/clear) using Stream-local storage.
 *
 * Request:
 * - comment_id
 * - rating: like|dislike|clear
 */
function ia_stream_ajax_comment_rate(): void {
  if (function_exists('ia_stream_verify_nonce_or_die')) ia_stream_verify_nonce_or_die();
  if (!is_user_logged_in()) {
    wp_send_json(['ok' => false, 'error' => 'Login required']);
  }

  if (!class_exists('IA_Stream_Service_Comment_Votes')) {
    wp_send_json(['ok' => false, 'error' => 'Comment vote service missing']);
  }

  $comment_id = trim((string) ia_stream_post_str('comment_id', ''));
  $rating = trim((string) ia_stream_post_str('rating', ''));
  if ($comment_id === '') wp_send_json(['ok' => false, 'error' => 'Missing comment_id']);

  $r = 0;
  if ($rating === 'like') $r = 1;
  elseif ($rating === 'dislike') $r = -1;
  elseif ($rating === 'clear') $r = 0;
  else wp_send_json(['ok' => false, 'error' => 'Invalid rating']);

  // Resolve current phpBB user id from wp_ia_identity_map.
  $wp_user_id = (int) get_current_user_id();
  $phpbb_user_id = 0;
  global $wpdb;
  if ($wpdb instanceof wpdb && $wp_user_id > 0) {
    $map = $wpdb->prefix . 'ia_identity_map';
    $row = $wpdb->get_row($wpdb->prepare("SELECT phpbb_user_id FROM {$map} WHERE wp_user_id=%d LIMIT 1", $wp_user_id), ARRAY_A);
    if (is_array($row) && isset($row['phpbb_user_id'])) $phpbb_user_id = (int)$row['phpbb_user_id'];
  }
  if ($phpbb_user_id <= 0) $phpbb_user_id = (int) get_user_meta($wp_user_id, 'ia_phpbb_user_id', true);
  if ($phpbb_user_id <= 0) {
    wp_send_json(['ok' => false, 'error' => 'Identity mapping missing (phpbb_user_id)']);
  }

  IA_Stream_Service_Comment_Votes::set_vote($phpbb_user_id, $comment_id, $r);
  $counts = IA_Stream_Service_Comment_Votes::counts([$comment_id]);
  $mine = IA_Stream_Service_Comment_Votes::user_votes($phpbb_user_id, [$comment_id]);

  wp_send_json([
    'ok' => true,
    'comment_id' => $comment_id,
    'votes' => [
      'up' => isset($counts[$comment_id]) ? (int)$counts[$comment_id]['up'] : 0,
      'down' => isset($counts[$comment_id]) ? (int)$counts[$comment_id]['down'] : 0,
      'my' => isset($mine[$comment_id]) ? (int)$mine[$comment_id] : 0,
    ],
  ]);
}

/**
 * Delete a PeerTube comment using the current Atrium user's OAuth token.
 *
 * Request:
 * - video_id
 * - comment_id
 */
function ia_stream_ajax_comment_delete(): void {
  if (function_exists('ia_stream_verify_nonce_or_die')) ia_stream_verify_nonce_or_die();
  if (!is_user_logged_in()) {
    wp_send_json(['ok' => false, 'error' => 'Login required']);
  }

  $video_id = trim((string) ia_stream_post_str('video_id', ''));
  $comment_id = trim((string) ia_stream_post_str('comment_id', ''));
  if ($video_id === '') wp_send_json(['ok' => false, 'error' => 'Missing video_id']);
  if ($comment_id === '') wp_send_json(['ok' => false, 'error' => 'Missing comment_id']);

  if (!class_exists('IA_PeerTube_Token_Helper') || !method_exists('IA_PeerTube_Token_Helper', 'get_token_for_current_user')) {
    wp_send_json(['ok' => false, 'error' => 'Token helper missing']);
  }

  $tok = '';
  try {
    $tok = (string) IA_PeerTube_Token_Helper::get_token_for_current_user();
  } catch (Throwable $e) {
    $tok = '';
  }

  if (trim($tok) === '') {
    wp_send_json([
      'ok' => false,
      'code' => 'missing_user_token',
      'error' => 'missing_user_token',
    ]);
  }

  if (!class_exists('IA_Stream_Service_PeerTube_API')) {
    wp_send_json(['ok' => false, 'error' => 'PeerTube API service missing']);
  }

  $api = new IA_Stream_Service_PeerTube_API();
  $api->set_token($tok);
  $raw = $api->delete_comment($video_id, $comment_id);
  if (!is_array($raw) || empty($raw['ok'])) {
    $msg = is_array($raw) && !empty($raw['error']) ? (string)$raw['error'] : 'Delete failed';
    wp_send_json(['ok' => false, 'error' => $msg, 'raw' => $raw]);
  }

  // Best-effort: remove local votes tied to this comment.
  if (class_exists('IA_Stream_Service_Comment_Votes')) {
    global $wpdb;
    if ($wpdb instanceof wpdb) {
      $t = IA_Stream_Service_Comment_Votes::table();
      $wpdb->delete($t, ['comment_id' => $comment_id], ['%s']);
    }
  }

  wp_send_json(['ok' => true]);
}

/**
 * Debug helper: expose the effective WordPress user identity and the linked
 * phpBB user id (from wp_ia_identity_map if present).
 */
function ia_stream_ajax_whoami(): void {
  // Intentionally no nonce check: this is a diagnostic endpoint that returns only
  // the currently effective WP user and linked phpBB ID. It is logged-in only.
  if (!is_user_logged_in()) {
    wp_send_json(['ok' => false, 'error' => 'Login required']);
  }

  $u = wp_get_current_user();
  $wp_user_id = (int)($u && isset($u->ID) ? $u->ID : 0);
  $login = ($u && isset($u->user_login)) ? (string)$u->user_login : '';
  $email = ($u && isset($u->user_email)) ? (string)$u->user_email : '';

  $phpbb_user_id = 0;
  global $wpdb;
  if ($wp_user_id > 0 && $wpdb instanceof wpdb) {
    $t = $wpdb->prefix . 'ia_identity_map';
    $row = $wpdb->get_row(
      $wpdb->prepare("SELECT phpbb_user_id FROM {$t} WHERE wp_user_id=%d LIMIT 1", $wp_user_id),
      ARRAY_A
    );
    if (is_array($row) && isset($row['phpbb_user_id'])) {
      $phpbb_user_id = (int)$row['phpbb_user_id'];
    }
  }

  wp_send_json([
    'ok' => true,
    'wp_user_id' => $wp_user_id,
    'wp_user_login' => $login,
    'wp_user_email' => $email,
    'phpbb_user_id' => $phpbb_user_id,
  ]);
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

function ia_stream_ajax_comment_thread(): void {
  if (function_exists('ia_stream_verify_nonce_or_die')) ia_stream_verify_nonce_or_die();

  $q = [
    'video_id' => ia_stream_post_str('video_id', ''),
    'thread_id' => ia_stream_post_str('thread_id', ''),
  ];

  if (class_exists('IA_Stream_Module_Comments')) {
    $out = IA_Stream_Module_Comments::get_thread($q);
    wp_send_json($out);
  }

  wp_send_json(['ok' => false, 'error' => 'Comments module missing']);
}

function ia_stream_ajax_comment_create(): void {
  if (function_exists('ia_stream_verify_nonce_or_die')) ia_stream_verify_nonce_or_die();
  if (!is_user_logged_in()) {
    wp_send_json(['ok' => false, 'error' => 'Login required']);
  }

  $q = [
    'video_id' => ia_stream_post_str('video_id', ''),
    'text' => ia_stream_post_str('text', ''),
  ];

  if (class_exists('IA_Stream_Module_Comments')) {
    $out = IA_Stream_Module_Comments::create_thread($q);
    wp_send_json($out);
  }

  wp_send_json(['ok' => false, 'error' => 'Comments module missing']);
}

function ia_stream_ajax_comment_reply(): void {
  if (function_exists('ia_stream_verify_nonce_or_die')) ia_stream_verify_nonce_or_die();
  if (!is_user_logged_in()) {
    wp_send_json(['ok' => false, 'error' => 'Login required']);
  }

  $q = [
    'video_id' => ia_stream_post_str('video_id', ''),
    'comment_id' => ia_stream_post_str('comment_id', ''),
    'text' => ia_stream_post_str('text', ''),
  ];

  if (class_exists('IA_Stream_Module_Comments')) {
    $out = IA_Stream_Module_Comments::reply($q);
    wp_send_json($out);
  }

  wp_send_json(['ok' => false, 'error' => 'Comments module missing']);
}

/**
 * Prompted mint: accepts a plaintext password once, captures it for the current
 * WP user, and mints/stores the per-user PeerTube token via the token helper.
 *
 * This avoids relying on wp_login/wp_authenticate in Atrium's state-based auth.
 */
function ia_stream_ajax_pt_mint_token(): void {
  if (function_exists('ia_stream_verify_nonce_or_die')) ia_stream_verify_nonce_or_die();
  if (!is_user_logged_in()) {
    wp_send_json(['ok' => false, 'error' => 'Login required']);
  }

  $password = ia_stream_post_str('password', '');
  $password = trim((string)$password);
  if ($password === '') {
    wp_send_json(['ok' => false, 'error' => 'Missing password']);
  }

  $u = wp_get_current_user();
  $wp_user_id = (int)($u && isset($u->ID) ? $u->ID : 0);
  if ($wp_user_id <= 0) {
    wp_send_json(['ok' => false, 'error' => 'User not found']);
  }

  // Identifier is used by the token mint plugin for the PeerTube password grant.
  // Prefer user_login; fall back to email.
  $identifier = '';
  if ($u && !empty($u->user_login)) $identifier = (string)$u->user_login;
  if ($identifier === '' && $u && !empty($u->user_email)) $identifier = (string)$u->user_email;

  // Capture password for this request (token plugin listens to this action).
  // Also call capture helper directly if present (covers non-hooked variants).
  try {
    do_action('ia_pt_user_password', $wp_user_id, $password, $identifier);
  } catch (Throwable $e) {
    // ignore
  }

  if (class_exists('IA_PT_Password_Capture') && method_exists('IA_PT_Password_Capture', 'capture_for_user')) {
    try {
      IA_PT_Password_Capture::capture_for_user($wp_user_id, $password, $identifier);
    } catch (Throwable $e) {
      // ignore
    }
  }

  if (!class_exists('IA_PeerTube_Token_Helper') || !method_exists('IA_PeerTube_Token_Helper', 'get_token_for_current_user')) {
    wp_send_json(['ok' => false, 'error' => 'Token helper missing']);
  }

  try {
    $tok = (string) IA_PeerTube_Token_Helper::get_token_for_current_user();
  } catch (Throwable $e) {
    wp_send_json([
      'ok' => false,
      'error' => $e->getMessage() ?: 'Mint failed',
    ]);
  }

  if (trim($tok) === '') {
    // Try to surface the underlying mint failure recorded by the token plugin.
    $detail = '';
    global $wpdb;
    if ($wpdb instanceof wpdb) {
      $t = $wpdb->prefix . 'ia_identity_map';
      $row = $wpdb->get_row($wpdb->prepare("SELECT phpbb_user_id FROM {$t} WHERE wp_user_id=%d LIMIT 1", $wp_user_id), ARRAY_A);
      $phpbb_user_id = (is_array($row) && isset($row['phpbb_user_id'])) ? (int)$row['phpbb_user_id'] : 0;
      if ($phpbb_user_id > 0) {
        $tt = $wpdb->prefix . 'ia_peertube_user_tokens';
        $r2 = $wpdb->get_row($wpdb->prepare("SELECT last_mint_error FROM {$tt} WHERE phpbb_user_id=%d LIMIT 1", $phpbb_user_id), ARRAY_A);
        if (is_array($r2) && !empty($r2['last_mint_error'])) {
          $detail = (string)$r2['last_mint_error'];
        }
      }
    }
    wp_send_json(['ok' => false, 'error' => $detail !== '' ? $detail : 'Mint failed']);
  }

  wp_send_json(['ok' => true]);
}
