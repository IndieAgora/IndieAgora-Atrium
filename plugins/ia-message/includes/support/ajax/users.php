<?php
if (!defined('ABSPATH')) exit;

function ia_message_ajax_user_search(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $q = isset($_POST['q']) ? trim((string) wp_unslash($_POST['q'])) : '';
  $limit  = isset($_POST['limit']) ? (int)$_POST['limit'] : 25;
  $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
  $limit  = min(50, max(1, $limit));
  $offset = max(0, $offset);

  $q_s = sanitize_text_field($q);

  // Prefer the same approach as Connect: search WP shadow users, then map to phpBB ids.
  $results = [];
  if (class_exists('WP_User_Query')) {
    $args = [
      'number' => $limit,
      'offset' => $offset,
      'fields' => ['ID', 'user_login', 'display_name'],
    ];
    if ($q_s !== '') {
      $args['search'] = '*' . $q_s . '*';
      $args['search_columns'] = ['user_login', 'user_nicename', 'display_name'];
    } else {
      // Browse mode: show newest/most recent shadow users.
      $args['orderby'] = 'ID';
      $args['order'] = 'DESC';
    }
    $query = new WP_User_Query($args);

    $meta_keys = [
      'ia_phpbb_user_id',
      'phpbb_user_id',
      'ia_phpbb_uid',
      'phpbb_uid',
      'ia_identity_phpbb',
    ];

    foreach ($query->get_results() as $u) {
      $uid = (int) $u->ID;
      if ($uid <= 0) continue;

      $phpbb = '';
      foreach ($meta_keys as $k) {
        $v = (string) get_user_meta($uid, $k, true);
        if ($v !== '') { $phpbb = $v; break; }
      }

      $phpbb_id = (int) ($phpbb ?: 0);
      if ($phpbb_id <= 0) continue;


      // Respect Connect privacy: allow_messages (admin bypass).
      if (!ia_message_recipient_allows_messages($phpbb_id, $me)) {
        continue;
      }

      $results[] = [
        'wp_user_id'    => $uid,
        'username'      => (string) $u->user_login,
        'display'       => (string) ($u->display_name ?: $u->user_login),
        'phpbb_user_id' => $phpbb_id,
        'avatarUrl'     => function_exists('ia_message_avatar_url_for_wp_user_id')
          ? ia_message_avatar_url_for_wp_user_id($uid, 64)
          : get_avatar_url($uid, ['size' => 64]),
      ];
    }
  }

  // Fallback: direct phpBB table search (less precise, but better than nothing).
  if (!$results && $q_s !== '') {
    $rows = ia_message_search_users_by_email($q_s, 10);
    foreach ($rows as $r) {
      $phpbb_id = isset($r['phpbb_user_id']) ? (int) $r['phpbb_user_id'] : 0;
      if ($phpbb_id <= 0) continue;

      if (!ia_message_recipient_allows_messages($phpbb_id, $me)) {
        continue;
      }
      $username = isset($r['username']) ? (string) $r['username'] : '';
      $display  = isset($r['label']) ? (string) $r['label'] : ($username !== '' ? $username : ('User #' . $phpbb_id));

      $results[] = [
        'username'      => $username,
        'display'       => $display,
        'phpbb_user_id' => $phpbb_id,
        'avatarUrl'     => '',
      ];
    }
  }

  ia_message_json_ok(['results' => $results]);
}

function ia_message_ajax_unread_count(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');

  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $count = function_exists('ia_message_total_unread') ? (int) ia_message_total_unread($me) : 0;
  ia_message_json_ok(['count' => $count]);
}

function ia_message_ajax_prefs_get(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');
  if (!is_user_logged_in()) ia_message_json_err('not_authenticated', 401);

  $uid = get_current_user_id();
  $prefs = get_user_meta($uid, 'ia_message_prefs', true);
  if (!is_array($prefs)) $prefs = [];

  $out = [
    'email' => array_key_exists('email', $prefs) ? (bool)$prefs['email'] : true,
    'popup' => array_key_exists('popup', $prefs) ? (bool)$prefs['popup'] : true,
  ];

  ia_message_json_ok($out);
}

function ia_message_ajax_prefs_set(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');
  if (!is_user_logged_in()) ia_message_json_err('not_authenticated', 401);

  $raw = isset($_POST['prefs']) ? (string)$_POST['prefs'] : '';
  $arr = json_decode($raw, true);
  if (!is_array($arr)) $arr = [];

  $prefs = [
    'email' => array_key_exists('email', $arr) ? (bool)$arr['email'] : true,
    'popup' => array_key_exists('popup', $arr) ? (bool)$arr['popup'] : true,
  ];

  update_user_meta(get_current_user_id(), 'ia_message_prefs', $prefs);
  ia_message_json_ok(['saved' => 1]);
}


/**
 * Upload a file for message composer.
 * Returns a public URL which the client embeds into message body.
 */

function ia_message_ajax_user_rel_status(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');
  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $target = isset($_POST['target_phpbb']) ? (int)$_POST['target_phpbb'] : 0;
  if ($target <= 0) ia_message_json_ok(['following'=>false,'blocked_any'=>false,'blocked_by_me'=>false]);

  ia_message_json_ok([
    'following' => ia_user_rel_is_following($me, $target),
    'blocked_any' => ia_user_rel_is_blocked_any($me, $target),
    'blocked_by_me' => ia_user_rel_is_blocked_by_me($me, $target),
  ]);
}

function ia_message_ajax_user_follow_toggle(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');
  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $target = isset($_POST['target_phpbb']) ? (int)$_POST['target_phpbb'] : 0;
  if ($target <= 0) ia_message_json_err('bad_user', 400);
  if (ia_user_rel_is_blocked_any($me, $target)) ia_message_json_err('blocked', 403);

  $following = ia_user_rel_toggle_follow($me, $target);
  ia_message_json_ok(['following' => $following]);
}

function ia_message_ajax_user_block_toggle(): void {
  ia_message_verify_nonce('boot', isset($_POST['nonce']) ? (string)$_POST['nonce'] : '');
  $me = ia_message_current_phpbb_user_id();
  if ($me <= 0) ia_message_json_err('not_authenticated', 401);

  $target = isset($_POST['target_phpbb']) ? (int)$_POST['target_phpbb'] : 0;
  if ($target <= 0) ia_message_json_err('bad_user', 400);

  $blocked_by_me = ia_user_rel_toggle_block($me, $target);
  ia_message_json_ok([
    'blocked_by_me' => $blocked_by_me,
    'blocked_any' => ia_user_rel_is_blocked_any($me, $target),
  ]);
}
