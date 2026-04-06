<?php
if (!defined('ABSPATH')) exit;

function ia_notify_url_base(): string {
  // Atrium lives on the site front page; use home_url('/') for consistent routing.
  return home_url('/');
}

function ia_notify_url_connect_post(int $post_id, int $comment_id = 0): string {
  $args = ['tab' => 'connect', 'ia_post' => (int)$post_id];
  if ($comment_id > 0) $args['ia_comment'] = (int)$comment_id;
  return add_query_arg($args, ia_notify_url_base());
}

function ia_notify_url_connect_profile(string $username): string {
  $username = sanitize_text_field($username);
  if ($username === '') return add_query_arg(['tab' => 'connect'], ia_notify_url_base());
  return add_query_arg(['tab' => 'connect', 'ia_profile_name' => $username], ia_notify_url_base());
}

function ia_notify_url_messages_thread(int $thread_id, int $message_id = 0): string {
  $args = ['tab' => 'messages', 'ia_msg_thread' => (int)$thread_id];
  if ($message_id > 0) $args['ia_msg_mid'] = (int)$message_id;
  return add_query_arg($args, ia_notify_url_base());
}

function ia_notify_url_messages_invite(int $invite_id): string {
  return add_query_arg(['tab' => 'messages', 'ia_msg_invite' => (int)$invite_id], ia_notify_url_base());
}

function ia_notify_url_discuss_topic(int $topic_id, int $post_id = 0): string {
  $args = ['tab' => 'discuss', 'ia_tab' => 'discuss', 'iad_topic' => (int)$topic_id, 'iad_view' => 'topic'];
  if ($post_id > 0) $args['iad_post'] = (int)$post_id;
  $url = add_query_arg($args, ia_notify_url_base());
  // Allow Discuss/Atrium to override deep link format.
  return (string) apply_filters('ia_discuss_topic_url', $url, (int)$topic_id, (int)$post_id);
}

function ia_notify_url_discuss_agora(int $forum_id): string {
  $args = ['tab' => 'discuss', 'ia_tab' => 'discuss', 'iad_forum' => (int)$forum_id, 'iad_view' => 'agora'];
  $url = add_query_arg($args, ia_notify_url_base());
  return (string) apply_filters('ia_discuss_agora_url', $url, (int)$forum_id);
}

function ia_notify_rel_table(): string {
  // Shared relationship table (follow/block) is defined in ia-connect/ia-discuss under a function guard.
  if (function_exists('ia_user_rel_table')) return ia_user_rel_table();
  global $wpdb;
  return $wpdb->prefix . 'ia_user_relations';
}

function ia_notify_actor_followers(int $actor_phpbb_id): array {
  global $wpdb;
  $actor_phpbb_id = (int)$actor_phpbb_id;
  if ($actor_phpbb_id <= 0 || !$wpdb) return [];
  $t = ia_notify_rel_table();
  // Followers are src_phpbb_id rows where dst_phpbb_id = actor.
  $ids = (array) $wpdb->get_col($wpdb->prepare(
    "SELECT src_phpbb_id FROM {$t} WHERE rel_type='follow' AND dst_phpbb_id=%d",
    $actor_phpbb_id
  ));
  return array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
}

function ia_notify_is_blocked_any(int $a_phpbb, int $b_phpbb): bool {
  $a_phpbb = (int)$a_phpbb; $b_phpbb = (int)$b_phpbb;
  if ($a_phpbb <= 0 || $b_phpbb <= 0 || $a_phpbb === $b_phpbb) return false;
  if (function_exists('ia_user_rel_is_blocked_any')) {
    return (bool) ia_user_rel_is_blocked_any($a_phpbb, $b_phpbb);
  }
  return false;
}

function ia_notify_register_hooks(): void {
  add_filter('ia_mail_suite_allow_send', 'ia_notify_mail_suite_gate', 20, 4);
  add_action('ia_user_follow_created', 'ia_notify_on_follow_created', 20, 3);
  add_action('ia_connect_post_created', 'ia_notify_on_connect_post_created', 20, 3);
  add_action('ia_connect_comment_created', 'ia_notify_on_connect_comment_created', 20, 3);
  // Alias used in some Connect builds
  add_action('ia_connect_post_commented', 'ia_notify_on_connect_comment_created', 20, 3);
  add_action('ia_message_sent', 'ia_notify_on_message_sent', 20, 4);
  // IA Message groups
  add_action('ia_message_group_member_added', 'ia_notify_on_message_group_member_added', 20, 3);
  add_action('ia_message_group_member_kicked', 'ia_notify_on_message_group_member_kicked', 20, 3);
  add_action('ia_message_group_invited', 'ia_notify_on_message_group_invited', 20, 4);
  add_action('ia_message_group_invite_accepted', 'ia_notify_on_message_group_invite_accepted', 20, 4);
  // Discuss
  add_action('ia_discuss_topic_created', 'ia_notify_on_discuss_topic_created', 20, 4);
  add_action('ia_discuss_post_replied', 'ia_notify_on_discuss_post_replied', 20, 4);
  add_action('ia_discuss_user_mentioned', 'ia_notify_on_discuss_user_mentioned', 20, 4);
  add_action('ia_discuss_post_shared_to_connect', 'ia_notify_on_discuss_post_shared_to_connect', 20, 5);
  add_action('ia_discuss_agora_joined', 'ia_notify_on_discuss_agora_joined', 20, 2);
  add_action('ia_discuss_agora_kicked', 'ia_notify_on_discuss_agora_kicked', 20, 3);
  add_action('ia_discuss_agora_readded', 'ia_notify_on_discuss_agora_readded', 20, 3);
  // Canonical user registration hook from IA User (core auth). Falls back to WP's user_register.
  add_action('ia_user_after_register', 'ia_notify_on_user_register', 20, 2);
  add_action('user_register', 'ia_notify_on_user_register_wp_fallback', 20, 1);
}

function ia_notify_send_template_to_wp(int $to_wp_id, string $key, array $ctx, string $fallback_subject, string $fallback_body): void {
  $to_wp_id = (int)$to_wp_id;
  if ($to_wp_id <= 0) return;
  if (!function_exists('ia_notify_emails_enabled_for_wp') || !ia_notify_emails_enabled_for_wp($to_wp_id)) return;

  $u = get_userdata($to_wp_id);
  $to = $u ? (string)$u->user_email : '';
  if ($to === '') return;

  if (function_exists('ia_mail_suite')) {
    try {
      $ok = ia_mail_suite()->send_template($key, $to, $ctx);
      if ($ok) return;
    } catch (Throwable $e) {}
  }

  @wp_mail($to, $fallback_subject, $fallback_body);
}

function ia_notify_resolve_username_to_phpbb_id(string $username): int {
  $username = trim((string)$username);
  if ($username === '') return 0;

  // Prefer IA Auth identity map.
  if (class_exists('IA_Auth') && method_exists('IA_Auth', 'instance')) {
    try {
      $ia = IA_Auth::instance();
      if (is_object($ia) && isset($ia->db) && is_object($ia->db) && method_exists($ia->db, 'find_phpbb_user_by_wp_user')) {
        $wp = get_user_by('login', $username);
        if (!$wp) $wp = get_user_by('slug', $username);
        if ($wp) {
          $phpbb = (int)$ia->db->find_phpbb_user_by_wp_user((int)$wp->ID);
          if ($phpbb > 0) return $phpbb;
        }
      }
    } catch (Throwable $e) {}
  }

  // Fallback: find WP user and read stored phpBB id meta.
  $wp = get_user_by('login', $username);
  if (!$wp) $wp = get_user_by('slug', $username);
  if ($wp) {
    $phpbb = (int) get_user_meta((int)$wp->ID, 'ia_phpbb_user_id', true);
    if ($phpbb <= 0) $phpbb = (int) get_user_meta((int)$wp->ID, 'phpbb_user_id', true);
    return $phpbb;
  }

  return 0;
}

function ia_notify_on_discuss_user_mentioned(string $mentioned_username, int $actor_phpbb, int $topic_id, int $post_id): void {
  $mentioned_username = sanitize_text_field($mentioned_username);
  $actor_phpbb = (int)$actor_phpbb;
  $topic_id = (int)$topic_id;
  $post_id = (int)$post_id;
  if ($mentioned_username === '' || $actor_phpbb <= 0 || $topic_id <= 0 || $post_id <= 0) return;

  $dst_phpbb = ia_notify_resolve_username_to_phpbb_id($mentioned_username);
  if ($dst_phpbb <= 0 || $dst_phpbb === $actor_phpbb) return;
  if (ia_notify_is_blocked_any($dst_phpbb, $actor_phpbb)) return;

  $dst_wp = ia_notify_wp_id_from_phpbb($dst_phpbb);
  if ($dst_wp <= 0) return;
  $prefs = ia_notify_get_prefs($dst_wp);
  if (!empty($prefs['mute_all'])) return;

  $actor = ia_notify_user_display_from_phpbb($actor_phpbb);
  $url = ia_notify_url_discuss_topic($topic_id, $post_id);

  ia_notify_insert([
    'recipient_phpbb_id' => $dst_phpbb,
    'actor_phpbb_id' => $actor_phpbb,
    'type' => 'discuss_mention',
    'object_type' => 'discuss_post',
    'object_id' => $post_id,
    'url' => $url,
    'text' => $actor['name'] . ' mentioned you in Discuss.',
    'meta' => [
      'actor_name' => $actor['name'],
      'actor_avatar' => $actor['avatar'],
      'actor_username' => (string)($actor['username'] ?? ''),
      'topic_id' => $topic_id,
      'post_id' => $post_id,
    ],
  ]);

  // Email (optional; uses IA Mail Suite when available).
  $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
  $subj = '[' . $site . '] You were mentioned by ' . $actor['name'];
  $body = "You were mentioned by {$actor['name']} in Discuss.\n\nOpen post: {$url}\n";
  ia_notify_send_template_to_wp($dst_wp, 'ia_discuss_mention', [
    'display_name' => (string)(get_userdata($dst_wp)->display_name ?? ''),
    'actor_name' => $actor['name'],
    'actor_username' => (string)($actor['username'] ?? ''),
    'post_url' => $url,
    'topic_id' => $topic_id,
    'post_id' => $post_id,
  ], $subj, $body);
}

function ia_notify_on_discuss_post_shared_to_connect(int $topic_id, int $post_id, int $connect_post_id, int $sharer_phpbb, int $orig_author_phpbb): void {
  $topic_id = (int)$topic_id;
  $post_id = (int)$post_id;
  $connect_post_id = (int)$connect_post_id;
  $sharer_phpbb = (int)$sharer_phpbb;
  $orig_author_phpbb = (int)$orig_author_phpbb;
  if ($topic_id <= 0 || $post_id <= 0 || $connect_post_id <= 0 || $sharer_phpbb <= 0 || $orig_author_phpbb <= 0) return;
  if ($orig_author_phpbb === $sharer_phpbb) return;
  if (ia_notify_is_blocked_any($orig_author_phpbb, $sharer_phpbb)) return;

  $dst_wp = ia_notify_wp_id_from_phpbb($orig_author_phpbb);
  if ($dst_wp <= 0) return;
  $prefs = ia_notify_get_prefs($dst_wp);
  if (!empty($prefs['mute_all'])) return;

  $actor = ia_notify_user_display_from_phpbb($sharer_phpbb);
  $url = ia_notify_url_connect_post($connect_post_id, 0);

  ia_notify_insert([
    'recipient_phpbb_id' => $orig_author_phpbb,
    'actor_phpbb_id' => $sharer_phpbb,
    'type' => 'discuss_shared_to_connect',
    'object_type' => 'connect_post',
    'object_id' => $connect_post_id,
    'url' => $url,
    'text' => $actor['name'] . ' shared your Discuss post to Connect.',
    'meta' => [
      'actor_name' => $actor['name'],
      'actor_avatar' => $actor['avatar'],
      'actor_username' => (string)($actor['username'] ?? ''),
      'topic_id' => $topic_id,
      'post_id' => $post_id,
      'connect_post_id' => $connect_post_id,
    ],
  ]);

  $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
  $subj = '[' . $site . '] Your Discuss post was shared to Connect';
  $body = $actor['name'] . " shared your Discuss post to Connect.\n\nOpen post: {$url}\n";
  ia_notify_send_template_to_wp($dst_wp, 'ia_discuss_share_to_connect', [
    'display_name' => (string)(get_userdata($dst_wp)->display_name ?? ''),
    'actor_name' => $actor['name'],
    'actor_username' => (string)($actor['username'] ?? ''),
    'post_url' => $url,
    'connect_post_id' => $connect_post_id,
    'topic_id' => $topic_id,
    'post_id' => $post_id,
  ], $subj, $body);
}

function ia_notify_on_follow_created(int $src_phpbb, int $dst_phpbb, array $meta = []): void {
  // dst gets notified: src followed you
  if ($src_phpbb <= 0 || $dst_phpbb <= 0 || $src_phpbb === $dst_phpbb) return;

  $dst_wp = ia_notify_wp_id_from_phpbb($dst_phpbb);
  if ($dst_wp <= 0) return;

  $prefs = ia_notify_get_prefs($dst_wp);
  if (!empty($prefs['mute_all'])) return;

  $srcd = ia_notify_user_display_from_phpbb($src_phpbb);

  ia_notify_insert([
    'recipient_phpbb_id' => $dst_phpbb,
    'actor_phpbb_id' => $src_phpbb,
    'type' => 'followed_you',
    'object_type' => 'user',
    'object_id' => $src_phpbb,
    'url' => ia_notify_url_connect_profile((string)($srcd['username'] ?? '')),
    'text' => $srcd['name'] . ' followed you.',
    'meta' => [
      'actor_name' => $srcd['name'],
      'actor_avatar' => $srcd['avatar'],
      'actor_username' => (string)($srcd['username'] ?? ''),
      'source' => $meta['source'] ?? '',
    ],
  ]);
}

function ia_notify_on_connect_post_created(int $post_id, int $actor_wp_id, int $wall_owner_phpbb_id): void {
  $post_id = (int)$post_id;
  $actor_wp_id = (int)$actor_wp_id;
  $wall_owner_phpbb_id = (int)$wall_owner_phpbb_id;
  if ($post_id <= 0 || $actor_wp_id <= 0 || $wall_owner_phpbb_id <= 0) return;

  $actor_phpbb = (int) get_user_meta($actor_wp_id, 'ia_phpbb_user_id', true);
  if ($actor_phpbb <= 0) return;

  // 1) Wall notification: only when the post lands on someone else's wall.
  $did_wall_notify = false;
  if ($actor_phpbb !== $wall_owner_phpbb_id) {
    $dst_wp = ia_notify_wp_id_from_phpbb($wall_owner_phpbb_id);
    if ($dst_wp > 0) {
      $prefs = ia_notify_get_prefs($dst_wp);
      if (empty($prefs['mute_all']) && !ia_notify_is_blocked_any($wall_owner_phpbb_id, $actor_phpbb)) {
        $actor = ia_notify_user_display_from_phpbb($actor_phpbb);
        ia_notify_insert([
          'recipient_phpbb_id' => $wall_owner_phpbb_id,
          'actor_phpbb_id' => $actor_phpbb,
          'type' => 'connect_wall_post',
          'object_type' => 'connect_post',
          'object_id' => $post_id,
          'url' => ia_notify_url_connect_post($post_id, 0),
          'text' => $actor['name'] . ' posted on your wall.',
          'meta' => [
            'actor_name' => $actor['name'],
            'actor_avatar' => $actor['avatar'],
            'actor_username' => (string)($actor['username'] ?? ''),
            'post_id' => $post_id,
            'wall_owner_phpbb_id' => $wall_owner_phpbb_id,
          ],
        ]);
        $did_wall_notify = true;
      }
    }
  }

  // 2) Follow notification: notify followers of the actor (Facebook-style),
  // but do not double-notify the wall owner if they already got the wall notification.
  $followers = ia_notify_actor_followers($actor_phpbb);
  if ($followers) {
    $actor = ia_notify_user_display_from_phpbb($actor_phpbb);
    foreach ($followers as $dst_phpbb) {
      $dst_phpbb = (int)$dst_phpbb;
      if ($dst_phpbb <= 0 || $dst_phpbb === $actor_phpbb) continue;
      if ($did_wall_notify && $dst_phpbb === $wall_owner_phpbb_id) continue;
      if (ia_notify_is_blocked_any($dst_phpbb, $actor_phpbb)) continue;

      $dst_wp = ia_notify_wp_id_from_phpbb($dst_phpbb);
      if ($dst_wp <= 0) continue;
      $prefs = ia_notify_get_prefs($dst_wp);
      if (!empty($prefs['mute_all'])) continue;

      ia_notify_insert([
        'recipient_phpbb_id' => $dst_phpbb,
        'actor_phpbb_id' => $actor_phpbb,
        'type' => 'connect_follow_post',
        'object_type' => 'connect_post',
        'object_id' => $post_id,
        'url' => ia_notify_url_connect_post($post_id, 0),
        'text' => $actor['name'] . ' posted in Connect.',
        'meta' => [
          'actor_name' => $actor['name'],
          'actor_avatar' => $actor['avatar'],
          'actor_username' => (string)($actor['username'] ?? ''),
          'post_id' => $post_id,
        ],
      ]);
    }
  }
}

function ia_notify_on_connect_comment_created(int $comment_id, int $post_id, int $actor_wp_id): void {
  $comment_id = (int)$comment_id;
  $post_id = (int)$post_id;
  $actor_wp_id = (int)$actor_wp_id;
  if ($comment_id <= 0 || $post_id <= 0 || $actor_wp_id <= 0) return;

  $actor_phpbb = (int) get_user_meta($actor_wp_id, 'ia_phpbb_user_id', true);
  if ($actor_phpbb <= 0) return;

  // Notify followers of this post (not everyone who follows the actor).
  // NOTE: ia-connect follows table is (post_id, follower_wp_id, follower_phpbb_id) and has no is_following flag.
  global $wpdb;

  $followers_wp = [];
  if (function_exists('ia_connect_followers_wp_ids')) {
    $followers_wp = (array) ia_connect_followers_wp_ids($post_id);
  } else {
    $follows = $wpdb->prefix . 'ia_connect_follows';
    $followers_wp = (array) $wpdb->get_col($wpdb->prepare(
      "SELECT follower_wp_id FROM {$follows} WHERE post_id=%d",
      $post_id
    ));
  }

  // Always include the post author + wall owner, even if the follow row is missing.
  // NOTE: wall_owner_wp_id may be 0 in Atrium when the wall is anchored by phpBB id only.
  $posts = $wpdb->prefix . 'ia_connect_posts';
  $row = $wpdb->get_row($wpdb->prepare(
    "SELECT author_wp_id, wall_owner_wp_id, author_phpbb_id, wall_owner_phpbb_id FROM {$posts} WHERE id=%d LIMIT 1",
    $post_id
  ), ARRAY_A);
  $extra_phpbb = [];
  if (is_array($row)) {
    $followers_wp[] = (int)($row['author_wp_id'] ?? 0);
    $followers_wp[] = (int)($row['wall_owner_wp_id'] ?? 0);
    $extra_phpbb[] = (int)($row['author_phpbb_id'] ?? 0);
    $extra_phpbb[] = (int)($row['wall_owner_phpbb_id'] ?? 0);
  }

  foreach (array_values(array_unique(array_filter(array_map('intval', $extra_phpbb)))) as $phpbb_id) {
    if ($phpbb_id <= 0) continue;
    $wp_from_phpbb = ia_notify_wp_id_from_phpbb($phpbb_id);
    if ($wp_from_phpbb > 0) $followers_wp[] = (int)$wp_from_phpbb;
  }

  $followers_wp = array_values(array_unique(array_filter(array_map('intval', $followers_wp))));

  foreach ($followers_wp as $wp_id) {
    $wp_id = (int)$wp_id;
    if ($wp_id <= 0 || $wp_id === $actor_wp_id) continue;

    $dst_phpbb = (int) get_user_meta($wp_id, 'ia_phpbb_user_id', true);
    if ($dst_phpbb <= 0) continue;
    if (ia_notify_is_blocked_any($dst_phpbb, $actor_phpbb)) continue;

    $prefs = ia_notify_get_prefs($wp_id);
    if (!empty($prefs['mute_all'])) continue;

    $actor = ia_notify_user_display_from_phpbb($actor_phpbb);

    ia_notify_insert([
      'recipient_phpbb_id' => $dst_phpbb,
      'actor_phpbb_id' => $actor_phpbb,
      'type' => 'connect_post_reply',
      'object_type' => 'connect_post',
      'object_id' => $post_id,
      'url' => ia_notify_url_connect_post($post_id, $comment_id),
      'text' => $actor['name'] . ' replied to a post you follow.',
      'meta' => [
        'actor_name' => $actor['name'],
        'actor_avatar' => $actor['avatar'],
        'actor_username' => (string)($actor['username'] ?? ''),
        'post_id' => $post_id,
        'comment_id' => $comment_id,
      ],
    ]);
  }
}

function ia_notify_on_message_sent(int $message_id, int $thread_id, int $actor_phpbb_id, array $thread_member_phpbb_ids): void {
  $message_id = (int)$message_id;
  $thread_id = (int)$thread_id;
  $actor_phpbb_id = (int)$actor_phpbb_id;
  if ($message_id <= 0 || $thread_id <= 0 || $actor_phpbb_id <= 0) return;

  $actor = ia_notify_user_display_from_phpbb($actor_phpbb_id);

  foreach ($thread_member_phpbb_ids as $dst_phpbb) {
    $dst_phpbb = (int)$dst_phpbb;
    if ($dst_phpbb <= 0 || $dst_phpbb === $actor_phpbb_id) continue;
    if (ia_notify_is_blocked_any($dst_phpbb, $actor_phpbb_id)) continue;

    $dst_wp = ia_notify_wp_id_from_phpbb($dst_phpbb);
    if ($dst_wp <= 0) continue;

    $prefs = ia_notify_get_prefs($dst_wp);
    if (!empty($prefs['mute_all'])) continue;

    ia_notify_insert([
      'recipient_phpbb_id' => $dst_phpbb,
      'actor_phpbb_id' => $actor_phpbb_id,
      'type' => 'message_received',
      'object_type' => 'message_thread',
      'object_id' => $thread_id,
      'url' => ia_notify_url_messages_thread($thread_id, $message_id),
      'text' => $actor['name'] . ' sent you a message.',
      'meta' => [
        'actor_name' => $actor['name'],
        'actor_avatar' => $actor['avatar'],
        'actor_username' => (string)($actor['username'] ?? ''),
        'thread_id' => $thread_id,
        'message_id' => $message_id,
      ],
    ]);
  }
}

// -----------------------------
// IA Message group notifications
// -----------------------------

function ia_notify_message_group_title(int $thread_id): string {
  $thread_id = (int)$thread_id;
  if ($thread_id <= 0) return '';
  if (function_exists('ia_message_get_thread')) {
    try {
      $t = ia_message_get_thread($thread_id);
      $title = is_array($t) ? (string)($t['title'] ?? '') : '';
      return $title;
    } catch (Throwable $e) {}
  }
  return '';
}

function ia_notify_on_message_group_member_added(int $thread_id, int $actor_phpbb_id, int $added_phpbb_id): void {
  $thread_id = (int)$thread_id;
  $actor_phpbb_id = (int)$actor_phpbb_id;
  $added_phpbb_id = (int)$added_phpbb_id;
  if ($thread_id <= 0 || $actor_phpbb_id <= 0 || $added_phpbb_id <= 0) return;
  if ($added_phpbb_id === $actor_phpbb_id) return;
  if (ia_notify_is_blocked_any($added_phpbb_id, $actor_phpbb_id)) return;

  $dst_wp = ia_notify_wp_id_from_phpbb($added_phpbb_id);
  if ($dst_wp <= 0) return;
  $prefs = ia_notify_get_prefs($dst_wp);
  if (!empty($prefs['mute_all'])) return;

  $actor = ia_notify_user_display_from_phpbb($actor_phpbb_id);
  $title = ia_notify_message_group_title($thread_id);
  if ($title === '') $title = 'a group chat';

  ia_notify_insert([
    'recipient_phpbb_id' => $added_phpbb_id,
    'actor_phpbb_id' => $actor_phpbb_id,
    'type' => 'message_group_added',
    'object_type' => 'message_thread',
    'object_id' => $thread_id,
    'url' => ia_notify_url_messages_thread($thread_id),
    'text' => 'You were added to ' . $title . ' by ' . $actor['name'] . '.',
    'meta' => [
      'actor_name' => $actor['name'],
      'actor_avatar' => $actor['avatar'],
      'actor_username' => (string)($actor['username'] ?? ''),
      'thread_id' => $thread_id,
      'thread_title' => $title,
    ],
  ]);

  ia_notify_send_template_to_wp($dst_wp, 'ia_message_group_added', [
    'actor_name' => $actor['name'],
    'thread_title' => $title,
    'thread_url' => ia_notify_url_messages_thread($thread_id),
  ], 'Added to a group chat', 'You were added to a group chat.');
}

function ia_notify_on_message_group_member_kicked(int $thread_id, int $actor_phpbb_id, int $kicked_phpbb_id): void {
  $thread_id = (int)$thread_id;
  $actor_phpbb_id = (int)$actor_phpbb_id;
  $kicked_phpbb_id = (int)$kicked_phpbb_id;
  if ($thread_id <= 0 || $actor_phpbb_id <= 0 || $kicked_phpbb_id <= 0) return;
  if ($kicked_phpbb_id === $actor_phpbb_id) return;
  if (ia_notify_is_blocked_any($kicked_phpbb_id, $actor_phpbb_id)) return;

  $dst_wp = ia_notify_wp_id_from_phpbb($kicked_phpbb_id);
  if ($dst_wp <= 0) return;
  $prefs = ia_notify_get_prefs($dst_wp);
  if (!empty($prefs['mute_all'])) return;

  $actor = ia_notify_user_display_from_phpbb($actor_phpbb_id);
  $title = ia_notify_message_group_title($thread_id);
  if ($title === '') $title = 'a group chat';

  ia_notify_insert([
    'recipient_phpbb_id' => $kicked_phpbb_id,
    'actor_phpbb_id' => $actor_phpbb_id,
    'type' => 'message_group_banned',
    'object_type' => 'message_thread',
    'object_id' => $thread_id,
    'url' => ia_notify_url_messages_thread($thread_id),
    'text' => 'You were removed from ' . $title . ' by ' . $actor['name'] . '.',
    'meta' => [
      'actor_name' => $actor['name'],
      'actor_avatar' => $actor['avatar'],
      'actor_username' => (string)($actor['username'] ?? ''),
      'thread_id' => $thread_id,
      'thread_title' => $title,
    ],
  ]);

  ia_notify_send_template_to_wp($dst_wp, 'ia_message_group_kicked', [
    'actor_name' => $actor['name'],
    'thread_title' => $title,
    'thread_url' => ia_notify_url_messages_thread($thread_id),
  ], 'Removed from a group chat', 'You were removed from a group chat.');
}

function ia_notify_on_message_group_invited(int $thread_id, int $actor_phpbb_id, int $invitee_phpbb_id, int $invite_id): void {
  $thread_id = (int)$thread_id;
  $actor_phpbb_id = (int)$actor_phpbb_id;
  $invitee_phpbb_id = (int)$invitee_phpbb_id;
  $invite_id = (int)$invite_id;
  if ($thread_id <= 0 || $actor_phpbb_id <= 0 || $invitee_phpbb_id <= 0 || $invite_id <= 0) return;
  if ($invitee_phpbb_id === $actor_phpbb_id) return;
  if (ia_notify_is_blocked_any($invitee_phpbb_id, $actor_phpbb_id)) return;

  $dst_wp = ia_notify_wp_id_from_phpbb($invitee_phpbb_id);
  if ($dst_wp <= 0) return;
  $prefs = ia_notify_get_prefs($dst_wp);
  if (!empty($prefs['mute_all'])) return;

  $actor = ia_notify_user_display_from_phpbb($actor_phpbb_id);
  $title = ia_notify_message_group_title($thread_id);
  if ($title === '') $title = 'a group chat';

  ia_notify_insert([
    'recipient_phpbb_id' => $invitee_phpbb_id,
    'actor_phpbb_id' => $actor_phpbb_id,
    'type' => 'message_group_invite',
    'object_type' => 'message_invite',
    'object_id' => $invite_id,
    'url' => ia_notify_url_messages_invite($invite_id),
    'text' => $actor['name'] . ' invited you to join ' . $title . '.',
    'meta' => [
      'actor_name' => $actor['name'],
      'actor_avatar' => $actor['avatar'],
      'actor_username' => (string)($actor['username'] ?? ''),
      'thread_id' => $thread_id,
      'thread_title' => $title,
      'invite_id' => $invite_id,
    ],
  ]);

  ia_notify_send_template_to_wp($dst_wp, 'ia_message_group_invite', [
    'actor_name' => $actor['name'],
    'thread_title' => $title,
    'invite_url' => ia_notify_url_messages_invite($invite_id),
  ], 'Group chat invite', 'You have been invited to a group chat.');
}

function ia_notify_on_message_group_invite_accepted(int $thread_id, int $invitee_phpbb_id, int $inviter_phpbb_id, int $invite_id): void {
  // Optional: let inviter know invite was accepted.
  $thread_id = (int)$thread_id;
  $invitee_phpbb_id = (int)$invitee_phpbb_id;
  $inviter_phpbb_id = (int)$inviter_phpbb_id;
  $invite_id = (int)$invite_id;
  if ($thread_id <= 0 || $invitee_phpbb_id <= 0 || $inviter_phpbb_id <= 0) return;
  if ($invitee_phpbb_id === $inviter_phpbb_id) return;
  if (ia_notify_is_blocked_any($inviter_phpbb_id, $invitee_phpbb_id)) return;

  $dst_wp = ia_notify_wp_id_from_phpbb($inviter_phpbb_id);
  if ($dst_wp <= 0) return;
  $prefs = ia_notify_get_prefs($dst_wp);
  if (!empty($prefs['mute_all'])) return;

  $actor = ia_notify_user_display_from_phpbb($invitee_phpbb_id);
  $title = ia_notify_message_group_title($thread_id);
  if ($title === '') $title = 'a group chat';

  ia_notify_insert([
    'recipient_phpbb_id' => $inviter_phpbb_id,
    'actor_phpbb_id' => $invitee_phpbb_id,
    'type' => 'message_group_invite_accepted',
    'object_type' => 'message_thread',
    'object_id' => $thread_id,
    'url' => ia_notify_url_messages_thread($thread_id),
    'text' => $actor['name'] . ' joined ' . $title . '.',
    'meta' => [
      'actor_name' => $actor['name'],
      'actor_avatar' => $actor['avatar'],
      'actor_username' => (string)($actor['username'] ?? ''),
      'thread_id' => $thread_id,
      'thread_title' => $title,
      'invite_id' => $invite_id,
    ],
  ]);
}

// -----------------------------
// Discuss notifications
// -----------------------------

function ia_notify_discuss_members_with_agora_notify(int $forum_id): array {
  global $wpdb;
  $forum_id = (int)$forum_id;
  if ($forum_id <= 0 || !$wpdb) return [];
  $t = $wpdb->prefix . 'ia_discuss_agora_members';
  // notify_agora=1 means bell-on for this Agora.
  $ids = (array) $wpdb->get_col($wpdb->prepare(
    "SELECT phpbb_user_id FROM {$t} WHERE forum_id=%d AND notify_agora=1",
    $forum_id
  ));
  return array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
}

function ia_notify_discuss_topic_notify_users(int $topic_id): array {
  global $wpdb;
  $topic_id = (int)$topic_id;
  if ($topic_id <= 0 || !$wpdb) return [];
  $t = $wpdb->prefix . 'ia_discuss_topic_notify';
  // Table may not exist in very early installs.
  $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
  if ((string)$exists !== (string)$t) return [];

  $ids = (array) $wpdb->get_col($wpdb->prepare(
    "SELECT phpbb_user_id FROM {$t} WHERE topic_id=%d AND enabled=1",
    $topic_id
  ));
  return array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
}

function ia_notify_discuss_topic_optout_users(int $topic_id): array {
  global $wpdb;
  $topic_id = (int)$topic_id;
  if ($topic_id <= 0 || !$wpdb) return [];
  $t = $wpdb->prefix . 'ia_discuss_topic_notify';
  $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
  if ((string)$exists !== (string)$t) return [];

  $ids = (array) $wpdb->get_col($wpdb->prepare(
    "SELECT phpbb_user_id FROM {$t} WHERE topic_id=%d AND enabled=0",
    $topic_id
  ));
  return array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
}

function ia_notify_on_discuss_topic_created(int $topic_id, int $forum_id, int $actor_phpbb_id, int $post_id): void {
  $topic_id = (int)$topic_id;
  $forum_id = (int)$forum_id;
  $actor_phpbb_id = (int)$actor_phpbb_id;
  $post_id = (int)$post_id;
  if ($topic_id <= 0 || $forum_id <= 0 || $actor_phpbb_id <= 0) return;

  $actor = ia_notify_user_display_from_phpbb($actor_phpbb_id);

  // Recipients:
  // - Users who enabled Agora notifications (bell)
  // - Users following the actor
  // - Users who explicitly enabled topic notifications
  $recipients = array_values(array_unique(array_merge(
    ia_notify_discuss_members_with_agora_notify($forum_id),
    ia_notify_actor_followers($actor_phpbb_id),
    ia_notify_discuss_topic_notify_users($topic_id)
  )));

  foreach ($recipients as $dst_phpbb) {
    if ($dst_phpbb <= 0 || $dst_phpbb === $actor_phpbb_id) continue;
    if (ia_notify_is_blocked_any($dst_phpbb, $actor_phpbb_id)) continue;

    $dst_wp = ia_notify_wp_id_from_phpbb($dst_phpbb);
    if ($dst_wp <= 0) continue;
    $prefs = ia_notify_get_prefs($dst_wp);
    if (!empty($prefs['mute_all'])) continue;

    ia_notify_insert([
      'recipient_phpbb_id' => $dst_phpbb,
      'actor_phpbb_id' => $actor_phpbb_id,
      'type' => 'discuss_new_topic',
      'object_type' => 'discuss_topic',
      'object_id' => $topic_id,
      'url' => ia_notify_url_discuss_topic($topic_id, $post_id),
      'text' => $actor['name'] . ' posted a new topic.',
      'meta' => [
        'actor_name' => $actor['name'],
        'actor_avatar' => $actor['avatar'],
        'actor_username' => (string)($actor['username'] ?? ''),
        'forum_id' => $forum_id,
        'topic_id' => $topic_id,
        'post_id' => $post_id,
      ],
    ]);
  }
}

function ia_notify_on_discuss_post_replied(int $topic_id, int $post_id, int $forum_id, int $actor_phpbb_id): void {
  $topic_id = (int)$topic_id;
  $post_id = (int)$post_id;
  $forum_id = (int)$forum_id;
  $actor_phpbb_id = (int)$actor_phpbb_id;
  if ($topic_id <= 0 || $post_id <= 0 || $forum_id <= 0 || $actor_phpbb_id <= 0) return;

  $actor = ia_notify_user_display_from_phpbb($actor_phpbb_id);

  // Notification rules for replies:
  // 1) If user is subscribed to the topic, they get a notification regardless of whether they follow the actor.
  // 2) If user is NOT subscribed to the topic, but they DO follow the actor, they get a notification.
  // 3) If neither applies, no notification.
  //
  // "Subscribed" here means: topic participants (distinct posters) plus explicit topic opt-ins,
  // minus explicit per-topic opt-outs.

  $participants = (array) apply_filters('ia_discuss_topic_participants', [], $topic_id);
  $participants = array_values(array_unique(array_filter(array_map('intval', $participants), fn($v) => $v > 0)));

  $optins  = ia_notify_discuss_topic_notify_users($topic_id);
  $optouts = ia_notify_discuss_topic_optout_users($topic_id);
  $subscribed = array_values(array_unique(array_merge($participants, $optins)));
  if ($optouts) {
    $optout_map = array_fill_keys($optouts, 1);
    $subscribed = array_values(array_filter($subscribed, fn($v) => empty($optout_map[(int)$v])));
  }

  $followers = ia_notify_actor_followers($actor_phpbb_id);
  $recipients = array_values(array_unique(array_merge($subscribed, $followers)));

  foreach ($recipients as $dst_phpbb) {
    if ($dst_phpbb <= 0 || $dst_phpbb === $actor_phpbb_id) continue;
    if (ia_notify_is_blocked_any($dst_phpbb, $actor_phpbb_id)) continue;
    $dst_wp = ia_notify_wp_id_from_phpbb($dst_phpbb);
    if ($dst_wp <= 0) continue;
    $prefs = ia_notify_get_prefs($dst_wp);
    if (!empty($prefs['mute_all'])) continue;

    ia_notify_insert([
      'recipient_phpbb_id' => $dst_phpbb,
      'actor_phpbb_id' => $actor_phpbb_id,
      'type' => 'discuss_new_reply',
      'object_type' => 'discuss_topic',
      'object_id' => $topic_id,
      'url' => ia_notify_url_discuss_topic($topic_id, $post_id),
      'text' => $actor['name'] . ' replied to a topic.',
      'meta' => [
        'actor_name' => $actor['name'],
        'actor_avatar' => $actor['avatar'],
        'actor_username' => (string)($actor['username'] ?? ''),
        'forum_id' => $forum_id,
        'topic_id' => $topic_id,
        'post_id' => $post_id,
      ],
    ]);
  }
}

function ia_notify_on_discuss_agora_joined(int $actor_phpbb_id, int $forum_id): void {
  $actor_phpbb_id = (int)$actor_phpbb_id;
  $forum_id = (int)$forum_id;
  if ($actor_phpbb_id <= 0 || $forum_id <= 0) return;

  $followers = ia_notify_actor_followers($actor_phpbb_id);
  if (!$followers) return;
  $actor = ia_notify_user_display_from_phpbb($actor_phpbb_id);

  foreach ($followers as $dst_phpbb) {
    $dst_phpbb = (int)$dst_phpbb;
    if ($dst_phpbb <= 0 || $dst_phpbb === $actor_phpbb_id) continue;
    if (ia_notify_is_blocked_any($dst_phpbb, $actor_phpbb_id)) continue;

    $dst_wp = ia_notify_wp_id_from_phpbb($dst_phpbb);
    if ($dst_wp <= 0) continue;
    $prefs = ia_notify_get_prefs($dst_wp);
    if (!empty($prefs['mute_all'])) continue;

    ia_notify_insert([
      'recipient_phpbb_id' => $dst_phpbb,
      'actor_phpbb_id' => $actor_phpbb_id,
      'type' => 'discuss_agora_joined',
      'object_type' => 'discuss_agora',
      'object_id' => $forum_id,
      'url' => ia_notify_url_discuss_agora($forum_id),
      'text' => $actor['name'] . ' joined an Agora.',
      'meta' => [
        'actor_name' => $actor['name'],
        'actor_avatar' => $actor['avatar'],
        'actor_username' => (string)($actor['username'] ?? ''),
        'forum_id' => $forum_id,
      ],
    ]);
  }
}

if (!function_exists('ia_notify_on_discuss_agora_kicked')) {
function ia_notify_on_discuss_agora_kicked(int $user_phpbb_id, int $forum_id, int $actor_phpbb_id): void {
  $user_phpbb_id = (int)$user_phpbb_id;
  $forum_id = (int)$forum_id;
  $actor_phpbb_id = (int)$actor_phpbb_id;
  if ($user_phpbb_id <= 0 || $forum_id <= 0 || $actor_phpbb_id <= 0) return;
  if ($user_phpbb_id === $actor_phpbb_id) return;

  $dst_wp = ia_notify_wp_id_from_phpbb($user_phpbb_id);
  if ($dst_wp <= 0) return;
  $prefs = ia_notify_get_prefs($dst_wp);
  if (!empty($prefs['mute_all'])) return;

  $actor = ia_notify_user_display_from_phpbb($actor_phpbb_id);
  ia_notify_insert([
    'recipient_phpbb_id' => $user_phpbb_id,
    'actor_phpbb_id' => $actor_phpbb_id,
    'type' => 'discuss_kicked',
    'object_type' => 'discuss_agora',
    'object_id' => $forum_id,
    'url' => ia_notify_url_discuss_agora($forum_id),
    'text' => 'You were kicked from an Agora by ' . $actor['name'] . '.',
    'meta' => [
      'actor_name' => $actor['name'],
      'actor_avatar' => $actor['avatar'],
      'actor_username' => (string)($actor['username'] ?? ''),
      'forum_id' => $forum_id,
    ],
  ]);
}
}


if (!function_exists('ia_notify_on_discuss_agora_readded')) {
function ia_notify_on_discuss_agora_readded(int $user_phpbb_id, int $forum_id, int $actor_phpbb_id): void {
  $user_phpbb_id = (int)$user_phpbb_id;
  $forum_id = (int)$forum_id;
  $actor_phpbb_id = (int)$actor_phpbb_id;
  if ($user_phpbb_id <= 0 || $forum_id <= 0 || $actor_phpbb_id <= 0) return;
  if ($user_phpbb_id === $actor_phpbb_id) return;

  $dst_wp = ia_notify_wp_id_from_phpbb($user_phpbb_id);
  if ($dst_wp <= 0) return;
  $prefs = ia_notify_get_prefs($dst_wp);
  if (!empty($prefs['mute_all'])) return;

  $actor = ia_notify_user_display_from_phpbb($actor_phpbb_id);
  ia_notify_insert([
    'recipient_phpbb_id' => $user_phpbb_id,
    'actor_phpbb_id' => $actor_phpbb_id,
    'type' => 'discuss_unbanned',
    'object_type' => 'discuss_agora',
    'object_id' => $forum_id,
    'url' => ia_notify_url_discuss_agora($forum_id),
    'text' => 'You were unbanned/re-added to an Agora by ' . $actor['name'] . '.',
    'meta' => [
      'actor_name' => $actor['name'],
      'actor_avatar' => $actor['avatar'],
      'actor_username' => (string)($actor['username'] ?? ''),
      'forum_id' => $forum_id,
    ],
  ]);
}
}


function ia_notify_on_user_register($phpbb_user_row, int $wp_user_id): void {
  $wp_user_id = (int)$wp_user_id;
  if ($wp_user_id <= 0) return;

  $u = get_userdata($wp_user_id);
  if (!$u) return;

  $new_phpbb_id = (int) get_user_meta($wp_user_id, 'ia_phpbb_user_id', true);
  if ($new_phpbb_id <= 0) return;

  $new_name = (string)($u->display_name ?? $u->user_login ?? '');
  if ($new_name === '') $new_name = 'new user';

  // Notify all users (except the new user) who did not mute notifications.
  // This enables the "say hello" flow without relying on admin-only broadcasts.
  $users = get_users(['fields' => ['ID']]);
  foreach ($users as $row) {
    $dst_wp = (int)($row->ID ?? 0);
    if ($dst_wp <= 0 || $dst_wp === $wp_user_id) continue;

    $dst_phpbb = (int) get_user_meta($dst_wp, 'ia_phpbb_user_id', true);
    if ($dst_phpbb <= 0) continue;

    $prefs = ia_notify_get_prefs($dst_wp);
    if (!empty($prefs['mute_all'])) continue;

    ia_notify_insert([
      'recipient_phpbb_id' => $dst_phpbb,
      'actor_phpbb_id' => 0,
      'type' => 'new_user',
      'object_type' => 'user',
      'object_id' => $new_phpbb_id,
      'url' => ia_notify_url_connect_profile((string)($u->user_login ?? '')),
      'text' => $new_name . ' joined Atrium.',
      'meta' => [
        'new_user_wp_id' => $wp_user_id,
        'new_user_phpbb_id' => $new_phpbb_id,
        'new_user_name' => $new_name,
        'new_user_username' => (string)($u->user_login ?? ''),
      ],
    ]);
  }
}

function ia_notify_on_user_register_wp_fallback(int $wp_user_id): void {
  // If IA User (core auth) isn't installed/active, fall back to WP registration.
  ia_notify_on_user_register(null, (int)$wp_user_id);
}

if (!function_exists('ia_notify_on_discuss_agora_kicked')) {
function ia_notify_on_discuss_agora_kicked(int $user_phpbb_id, int $forum_id, int $actor_phpbb_id): void {
  $user_phpbb_id = (int)$user_phpbb_id;
  $forum_id = (int)$forum_id;
  $actor_phpbb_id = (int)$actor_phpbb_id;
  if ($user_phpbb_id <= 0 || $forum_id <= 0 || $actor_phpbb_id <= 0) return;

  $dst_wp = ia_notify_wp_id_from_phpbb($user_phpbb_id);
  if ($dst_wp <= 0) return;
  $prefs = ia_notify_get_prefs($dst_wp);
  if (!empty($prefs['mute_all'])) return;

  $actor = ia_notify_user_display_from_phpbb($actor_phpbb_id);

  ia_notify_insert([
    'recipient_phpbb_id' => $user_phpbb_id,
    'actor_phpbb_id' => $actor_phpbb_id,
    'type' => 'agora_kicked',
    'object_type' => 'discuss_agora',
    'object_id' => $forum_id,
    'url' => ia_notify_url_connect_profile((string)($actor['username'] ?? '')),
    'text' => 'You were kicked from an Agora by ' . $actor['name'] . '.',
    'meta' => [
      'actor_name' => $actor['name'],
      'actor_avatar' => $actor['avatar'],
      'actor_username' => (string)($actor['username'] ?? ''),
      'forum_id' => $forum_id,
    ],
  ]);
}
}


if (!function_exists('ia_notify_on_discuss_agora_readded')) {
function ia_notify_on_discuss_agora_readded(int $user_phpbb_id, int $forum_id, int $actor_phpbb_id): void {
  $user_phpbb_id = (int)$user_phpbb_id;
  $forum_id = (int)$forum_id;
  $actor_phpbb_id = (int)$actor_phpbb_id;
  if ($user_phpbb_id <= 0 || $forum_id <= 0 || $actor_phpbb_id <= 0) return;

  $dst_wp = ia_notify_wp_id_from_phpbb($user_phpbb_id);
  if ($dst_wp <= 0) return;
  $prefs = ia_notify_get_prefs($dst_wp);
  if (!empty($prefs['mute_all'])) return;

  $actor = ia_notify_user_display_from_phpbb($actor_phpbb_id);

  ia_notify_insert([
    'recipient_phpbb_id' => $user_phpbb_id,
    'actor_phpbb_id' => $actor_phpbb_id,
    'type' => 'agora_unbanned',
    'object_type' => 'discuss_agora',
    'object_id' => $forum_id,
    'url' => ia_notify_url_discuss_topic(0, 0),
    'text' => 'You were unbanned/re-added to an Agora by ' . $actor['name'] . '.',
    'meta' => [
      'actor_name' => $actor['name'],
      'actor_avatar' => $actor['avatar'],
      'actor_username' => (string)($actor['username'] ?? ''),
      'forum_id' => $forum_id,
    ],
  ]);
}
}



/**
 * Veto IA Mail Suite sends when IA Notify email preferences are disabled.
 * This is a safety net for any module that sends templates without consulting ia_notify_emails_enabled_*().
 */
function ia_notify_mail_suite_gate($allow, string $key, string $to_email, array $ctx) {
  if (!$allow) return false;

  // Only gate Atrium notification templates.
  $k = (string)$key;
  if (!(str_starts_with($k, 'ia_connect_') || str_starts_with($k, 'ia_discuss_') || str_starts_with($k, 'ia_message_'))) {
    return $allow;
  }

  $to_email = sanitize_email($to_email);
  if ($to_email === '' || !is_email($to_email)) return $allow;

  $u = get_user_by('email', $to_email);
  if (!$u || empty($u->ID)) return $allow;

  if (function_exists('ia_notify_emails_enabled_for_wp') && !ia_notify_emails_enabled_for_wp((int)$u->ID)) {
    return false;
  }

  return $allow;
}
