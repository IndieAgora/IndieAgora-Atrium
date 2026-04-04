<?php
if (!defined('ABSPATH')) exit;

/**
 * User relationships AJAX for Discuss.
 * Nonce: 'ia_discuss' (matches Discuss router)
 */
add_action('wp_ajax_ia_user_rel_status', 'ia_discuss_ajax_user_rel_status');
add_action('wp_ajax_ia_user_follow_toggle', 'ia_discuss_ajax_user_follow_toggle');
add_action('wp_ajax_ia_user_block_toggle', 'ia_discuss_ajax_user_block_toggle');

function ia_discuss_ajax_user_rel_status(): void {
  $nonce = isset($_POST['nonce']) ? (string) wp_unslash($_POST['nonce']) : '';
  if (!wp_verify_nonce($nonce, 'ia_discuss')) wp_send_json_error(['message' => 'Bad nonce'], 403);

  $wp_uid = (int) get_current_user_id();
  $me_phpbb = (int) apply_filters('ia_current_phpbb_user_id', 0, $wp_uid);
  $target_phpbb = isset($_POST['target_phpbb']) ? (int) $_POST['target_phpbb'] : 0;
  if ($me_phpbb <= 0 || $target_phpbb <= 0) wp_send_json_success(['following'=>false,'blocked_any'=>false,'blocked_by_me'=>false]);

  wp_send_json_success([
    'following' => ia_user_rel_is_following($me_phpbb, $target_phpbb),
    'blocked_any' => ia_user_rel_is_blocked_any($me_phpbb, $target_phpbb),
    'blocked_by_me' => ia_user_rel_is_blocked_by_me($me_phpbb, $target_phpbb),
  ]);
}

function ia_discuss_ajax_user_follow_toggle(): void {
  $nonce = isset($_POST['nonce']) ? (string) wp_unslash($_POST['nonce']) : '';
  if (!wp_verify_nonce($nonce, 'ia_discuss')) wp_send_json_error(['message' => 'Bad nonce'], 403);

  $wp_uid = (int) get_current_user_id();
  $me_phpbb = (int) apply_filters('ia_current_phpbb_user_id', 0, $wp_uid);
  $target_phpbb = isset($_POST['target_phpbb']) ? (int) $_POST['target_phpbb'] : 0;
  if ($me_phpbb <= 0 || $target_phpbb <= 0) wp_send_json_error(['message'=>'Bad target'], 400);
  if (ia_user_rel_is_blocked_any($me_phpbb, $target_phpbb)) wp_send_json_error(['message'=>'Blocked'], 403);

  $following = ia_user_rel_toggle_follow($me_phpbb, $target_phpbb);
  wp_send_json_success(['following' => $following]);
}

function ia_discuss_ajax_user_block_toggle(): void {
  $nonce = isset($_POST['nonce']) ? (string) wp_unslash($_POST['nonce']) : '';
  if (!wp_verify_nonce($nonce, 'ia_discuss')) wp_send_json_error(['message' => 'Bad nonce'], 403);

  $wp_uid = (int) get_current_user_id();
  $me_phpbb = (int) apply_filters('ia_current_phpbb_user_id', 0, $wp_uid);
  $target_phpbb = isset($_POST['target_phpbb']) ? (int) $_POST['target_phpbb'] : 0;
  if ($me_phpbb <= 0 || $target_phpbb <= 0) wp_send_json_error(['message'=>'Bad target'], 400);

  $blocked_by_me = ia_user_rel_toggle_block($me_phpbb, $target_phpbb);
  wp_send_json_success([
    'blocked_by_me' => $blocked_by_me,
    'blocked_any' => ia_user_rel_is_blocked_any($me_phpbb, $target_phpbb),
  ]);
}
