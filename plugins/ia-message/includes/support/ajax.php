<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX surface loader.
 *
 * Public action names remain unchanged. Callback implementations are split by
 * intent so upgrades can patch a narrower file without touching unrelated
 * endpoints.
 */
ia_message_require('includes/support/ajax/threads.php');
ia_message_require('includes/support/ajax/messages.php');
ia_message_require('includes/support/ajax/groups.php');
ia_message_require('includes/support/ajax/users.php');

add_action('wp_ajax_ia_message_threads', 'ia_message_ajax_threads');
add_action('wp_ajax_ia_message_thread', 'ia_message_ajax_thread');
add_action('wp_ajax_ia_message_send', 'ia_message_ajax_send');
add_action('wp_ajax_ia_message_new_dm', 'ia_message_ajax_new_dm');
add_action('wp_ajax_ia_message_new_group', 'ia_message_ajax_new_group');
add_action('wp_ajax_ia_message_user_search', 'ia_message_ajax_user_search');
add_action('wp_ajax_ia_message_group_invites', 'ia_message_ajax_group_invites');
add_action('wp_ajax_ia_message_group_invite_send', 'ia_message_ajax_group_invite_send');
add_action('wp_ajax_ia_message_group_invite_respond', 'ia_message_ajax_group_invite_respond');
add_action('wp_ajax_ia_message_group_members', 'ia_message_ajax_group_members');
add_action('wp_ajax_ia_message_group_kick', 'ia_message_ajax_group_kick');
add_action('wp_ajax_ia_message_unread_count', 'ia_message_ajax_unread_count');
add_action('wp_ajax_ia_message_prefs_get', 'ia_message_ajax_prefs_get');
add_action('wp_ajax_ia_message_prefs_set', 'ia_message_ajax_prefs_set');
add_action('wp_ajax_ia_message_upload', 'ia_message_ajax_upload');
add_action('wp_ajax_ia_message_forward', 'ia_message_ajax_forward');
add_action('wp_ajax_ia_message_user_rel_status', 'ia_message_ajax_user_rel_status');
add_action('wp_ajax_ia_message_user_follow_toggle', 'ia_message_ajax_user_follow_toggle');
add_action('wp_ajax_ia_message_user_block_toggle', 'ia_message_ajax_user_block_toggle');
