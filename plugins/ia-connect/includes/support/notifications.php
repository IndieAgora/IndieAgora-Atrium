<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Connect email notifications + @mentions.
 *
 * Uses wp_mail() so IA Mail Suite can handle sender/SMTP/templates.
 */

function ia_connect_notifications_boot(): void {
  // Post created (mentions).
  add_action('ia_connect_post_created', 'ia_connect_notify_on_post_created', 20, 3);
  // Comment created.
  add_action('ia_connect_comment_created', 'ia_connect_notify_on_comment_created', 20, 3);
  // Share created.
  add_action('ia_connect_share_created', 'ia_connect_notify_on_share_created', 20, 6);
}

add_action('plugins_loaded', 'ia_connect_notifications_boot', 50);

function ia_connect_build_post_url(int $post_id, int $comment_id = 0): string {
  // Always deep-link to the fullscreen modal.
  $base = home_url('/');
  $args = [
    'tab' => IA_CONNECT_PANEL_KEY,
    'ia_post' => $post_id,
  ];
  if ($comment_id > 0) $args['ia_comment'] = $comment_id;
  return add_query_arg($args, $base);
}

function ia_connect_wp_user_email(int $wp_user_id): string {
  $u = $wp_user_id > 0 ? get_userdata($wp_user_id) : null;
  return $u ? (string)$u->user_email : '';
}

/**
 * Send via IA Mail Suite templates when available; falls back to wp_mail().
 *
 * $key is a template key in IA Mail Suite (e.g. ia_connect_share_to_wall).
 */
function ia_connect_send_email(int $to_wp_user_id, string $key, array $ctx, string $fallback_subject, string $fallback_body): void {
  $to = ia_connect_wp_user_email($to_wp_user_id);
  if ($to === '') return;

  // Preferred: IA Mail Suite template override.
  if (function_exists('ia_mail_suite')) {
    try {
      $ok = ia_mail_suite()->send_template($key, $to, $ctx);
      if ($ok) return;
    } catch (Throwable $e) {
      // Fall through.
    }
  }

  // Fallback: send plain text.
  @wp_mail($to, $fallback_subject, $fallback_body);
}

function ia_connect_resolve_username_to_wp_id(string $username): int {
  $username = trim($username);
  if ($username === '') return 0;

  // Prefer phpBB canonical username lookup if available.
  $phpbb_db = ia_connect_phpbb_db();
  $phpbb_prefix = ia_connect_phpbb_prefix();
  if ($phpbb_db) {
    $users_tbl = $phpbb_prefix . 'users';
    // phpBB stores a normalized username_clean.
    $clean = strtolower($username);
    $phpbb_id = (int) $phpbb_db->get_var(
      $phpbb_db->prepare(
        "SELECT user_id FROM {$users_tbl} WHERE username_clean=%s OR username=%s LIMIT 1",
        $clean,
        $username
      )
    );
    if ($phpbb_id > 0) {
      $wp_id = ia_connect_map_phpbb_to_wp_id($phpbb_id);
      if ($wp_id > 0) return $wp_id;
    }
  }

  // Fallback to WP login.
  $u = get_user_by('login', $username);
  if ($u) return (int)$u->ID;
  $u = get_user_by('slug', $username);
  if ($u) return (int)$u->ID;
  return 0;
}

function ia_connect_extract_mentions(string $text): array {
  $text = (string)$text;
  if ($text === '') return [];
  if (!preg_match_all('/(^|[^a-zA-Z0-9_])@([a-zA-Z0-9_\-\.]{1,40})/u', $text, $m)) return [];
  $out = [];
  foreach ($m[2] as $u) {
    $u = (string)$u;
    if ($u === '') continue;
    $out[] = $u;
  }
  $out = array_values(array_unique($out));
  return $out;
}

function ia_connect_create_mention_posts(int $original_post_id, int $actor_wp_id, array $mentioned_wp_ids): void {
  if ($original_post_id <= 0 || $actor_wp_id <= 0) return;
  $mentioned_wp_ids = array_values(array_unique(array_filter(array_map('intval', $mentioned_wp_ids))));
  if (empty($mentioned_wp_ids)) return;

  global $wpdb;
  $posts = $wpdb->prefix . 'ia_connect_posts';
  $now = current_time('mysql');
  $actor_phpbb = ia_connect_user_phpbb_id($actor_wp_id);

  foreach ($mentioned_wp_ids as $wp_id) {
    if ($wp_id <= 0 || $wp_id === $actor_wp_id) continue;
    $wall_phpbb = ia_connect_user_phpbb_id($wp_id);
    $wall_wp = $wp_id;

    // Avoid duplicates: only one mention per (wall, original).
    $exists = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT id FROM $posts WHERE type='mention' AND parent_post_id=%d AND wall_owner_wp_id=%d LIMIT 1",
      $original_post_id,
      $wall_wp
    ));
    if ($exists > 0) continue;

    $wpdb->insert($posts, [
      'wall_owner_wp_id' => $wall_wp,
      'wall_owner_phpbb_id' => $wall_phpbb,
      'author_wp_id' => $actor_wp_id,
      'author_phpbb_id' => $actor_phpbb,
      'type' => 'mention',
      'parent_post_id' => $original_post_id,
      'shared_tab' => '',
      'shared_ref' => 'mention',
      'title' => '',
      'body' => '',
      'created_at' => $now,
      'updated_at' => $now,
      'status' => 'publish',
    ], ['%d','%d','%d','%d','%s','%d','%s','%s','%s','%s','%s','%s','%s']);
  }
}

function ia_connect_notify_on_post_created(int $post_id, int $actor_wp_id, int $wall_owner_phpbb_id): void {
  // Mentions: email + ensure the post appears on mentioned users' walls.
  global $wpdb;
  $posts = $wpdb->prefix . 'ia_connect_posts';
  $p = $wpdb->get_row($wpdb->prepare("SELECT id, title, body FROM $posts WHERE id=%d", $post_id), ARRAY_A);
  if (!$p) return;

  $actor = get_userdata($actor_wp_id);
  $actor_name = $actor ? (string)($actor->display_name ?: $actor->user_login) : 'User';

  $mentions = array_merge(
    ia_connect_extract_mentions((string)($p['title'] ?? '')),
    ia_connect_extract_mentions((string)($p['body'] ?? ''))
  );
  $mentions = array_values(array_unique($mentions));
  if (empty($mentions)) return;

  $mentioned_wp_ids = [];
  foreach ($mentions as $uname) {
    $wp_id = ia_connect_resolve_username_to_wp_id($uname);
    if ($wp_id > 0) $mentioned_wp_ids[] = $wp_id;
  }
  $mentioned_wp_ids = array_values(array_unique($mentioned_wp_ids));
  if (empty($mentioned_wp_ids)) return;

  ia_connect_create_mention_posts($post_id, $actor_wp_id, $mentioned_wp_ids);

  $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
  $url = ia_connect_build_post_url($post_id);
  foreach ($mentioned_wp_ids as $wp_id) {
    if ($wp_id === $actor_wp_id) continue;
    $subj = '[' . $site . '] You were mentioned by ' . $actor_name;
    $body = "You were mentioned by {$actor_name} in a post.\n\nOpen post: {$url}\n";
    ia_connect_send_email($wp_id, 'ia_connect_mention_post', [
      'display_name' => (string)(get_userdata($wp_id)->display_name ?? ''),
      'actor_name' => $actor_name,
      'actor_username' => (string)($actor->user_login ?? ''),
      'post_url' => $url,
      'post_id' => $post_id,
    ], $subj, $body);
  }
}

function ia_connect_post_root_id(int $post_id): int {
  global $wpdb;
  $posts = $wpdb->prefix . 'ia_connect_posts';
  $cur = $post_id;
  $guard = 0;
  while ($cur > 0 && $guard < 12) {
    $row = $wpdb->get_row($wpdb->prepare("SELECT id, type, parent_post_id FROM $posts WHERE id=%d", $cur), ARRAY_A);
    if (!$row) break;
    $type = (string)($row['type'] ?? '');
    $parent = (int)($row['parent_post_id'] ?? 0);
    if (($type === 'repost' || $type === 'mention') && $parent > 0) {
      $cur = $parent;
      $guard++;
      continue;
    }
    return (int)($row['id'] ?? $post_id);
  }
  return $post_id;
}

function ia_connect_notify_on_comment_created(int $comment_id, int $post_id, int $actor_wp_id): void {
  global $wpdb;
  $posts = $wpdb->prefix . 'ia_connect_posts';
  $comms = $wpdb->prefix . 'ia_connect_comments';

  $post = $wpdb->get_row($wpdb->prepare("SELECT id, type, wall_owner_wp_id, author_wp_id FROM $posts WHERE id=%d", $post_id), ARRAY_A);
  if (!$post) return;

  // No emails for comments on shared/nested repost posts.
  $ptype = (string)($post['type'] ?? '');
  if ($ptype === 'repost' || $ptype === 'mention') {
    // Mentions inside comments still count.
  }

  $comment = $wpdb->get_row($wpdb->prepare("SELECT id, body FROM $comms WHERE id=%d", $comment_id), ARRAY_A);
  if (!$comment) return;

  $actor = get_userdata($actor_wp_id);
  $actor_name = $actor ? (string)($actor->display_name ?: $actor->user_login) : 'User';
  $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
  $url = ia_connect_build_post_url($post_id, $comment_id);

  // Mentions in comments: email + post appears on their wall.
  $mentions = ia_connect_extract_mentions((string)($comment['body'] ?? ''));
  $mentioned_wp_ids = [];
  foreach ($mentions as $uname) {
    $wp_id = ia_connect_resolve_username_to_wp_id($uname);
    if ($wp_id > 0) $mentioned_wp_ids[] = $wp_id;
  }
  $mentioned_wp_ids = array_values(array_unique($mentioned_wp_ids));
  if (!empty($mentioned_wp_ids)) {
    // Ensure the post appears on mentioned users' walls.
    ia_connect_create_mention_posts($post_id, $actor_wp_id, $mentioned_wp_ids);
    foreach ($mentioned_wp_ids as $wp_id) {
      if ($wp_id === $actor_wp_id) continue;
      $subj = '[' . $site . '] You were mentioned by ' . $actor_name;
      $body = "You were mentioned by {$actor_name} in a comment.\n\nOpen comment: {$url}\n";
      ia_connect_send_email($wp_id, 'ia_connect_mention_comment', [
        'display_name' => (string)(get_userdata($wp_id)->display_name ?? ''),
        'actor_name' => $actor_name,
        'actor_username' => (string)($actor->user_login ?? ''),
        'comment_url' => $url,
        'post_url' => ia_connect_build_post_url($post_id),
        'post_id' => $post_id,
        'comment_id' => $comment_id,
      ], $subj, $body);
    }
  }

  // Notification rules:
  // - Comment on a status on someone's wall => email wall owner.
  // - Also email anyone who has already interacted (commented) on that status.
  // - No email when commenting on a shared/nested repost post.
  if ($ptype === 'repost' || $ptype === 'mention') {
    return;
  }

  $watchers = [];
  $wall_owner_wp = (int)($post['wall_owner_wp_id'] ?? 0);
  $post_author_wp = (int)($post['author_wp_id'] ?? 0);
  if ($wall_owner_wp > 0) $watchers[] = $wall_owner_wp;
  if ($post_author_wp > 0) $watchers[] = $post_author_wp;

  // Prior commenters.
  $prior = $wpdb->get_col($wpdb->prepare(
    "SELECT DISTINCT author_wp_id FROM $comms WHERE post_id=%d AND is_deleted=0",
    $post_id
  ));
  foreach (($prior ?: []) as $uid) {
    $uid = (int)$uid;
    if ($uid > 0) $watchers[] = $uid;
  }

  $watchers = array_values(array_unique(array_filter(array_map('intval', $watchers))));
  $watchers = array_values(array_diff($watchers, [$actor_wp_id]));
  if (empty($watchers)) return;

  $subj = '[' . $site . '] New comment from ' . $actor_name;
  $body = "{$actor_name} commented on a post you follow.\n\nOpen comment: {$url}\n";
  foreach ($watchers as $uid) {
    ia_connect_send_email($uid, 'ia_connect_comment_new', [
      'display_name' => (string)(get_userdata($uid)->display_name ?? ''),
      'actor_name' => $actor_name,
      'actor_username' => (string)($actor->user_login ?? ''),
      'comment_url' => $url,
      'post_url' => ia_connect_build_post_url($post_id),
      'post_id' => $post_id,
      'comment_id' => $comment_id,
    ], $subj, $body);
  }
}

function ia_connect_notify_on_share_created(int $original_post_id, int $actor_wp_id, int $actor_phpbb_id, array $targets, array $created_ids, $created_map = []): void {
  global $wpdb;
  $posts = $wpdb->prefix . 'ia_connect_posts';

  $actor = get_userdata($actor_wp_id);
  $actor_name = $actor ? (string)($actor->display_name ?: $actor->user_login) : 'User';
  $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

  // 1) Share to another user's wall => email the wall owner (not the sharer).
  // Use the repost id for that target when available.
  $map = is_array($created_map) ? $created_map : [];
  foreach (($targets ?: []) as $phpbb_wall_id) {
    $phpbb_wall_id = (int)$phpbb_wall_id;
    if ($phpbb_wall_id <= 0) continue;
    if ($phpbb_wall_id === (int)$actor_phpbb_id) continue;
    $wp_target = ia_connect_map_phpbb_to_wp_id($phpbb_wall_id);
    if ($wp_target <= 0) continue;

    $repost_id = (int)($map[$phpbb_wall_id] ?? 0);
    if ($repost_id <= 0) {
      // Best-effort lookup.
      $repost_id = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $posts WHERE type='repost' AND parent_post_id=%d AND wall_owner_phpbb_id=%d AND author_wp_id=%d ORDER BY id DESC LIMIT 1",
        $original_post_id,
        $phpbb_wall_id,
        $actor_wp_id
      ));
    }
    $url = ia_connect_build_post_url($repost_id > 0 ? $repost_id : $original_post_id);
    $subj = '[' . $site . '] ' . $actor_name . ' shared a post with you';
    $body = "{$actor_name} shared a post with you.\n\nOpen post: {$url}\n";
    ia_connect_send_email($wp_target, 'ia_connect_share_to_wall', [
      'display_name' => (string)(get_userdata($wp_target)->display_name ?? ''),
      'actor_name' => $actor_name,
      'actor_username' => (string)($actor->user_login ?? ''),
      'post_url' => $url,
      'post_id' => ($repost_id > 0 ? $repost_id : $original_post_id),
    ], $subj, $body);
  }

  // 2) When someone shares a status the user shared => email prior sharers.
  // We treat the root/original post as the shared status.
  $root_id = ia_connect_post_root_id($original_post_id);
  // Include direct reposts of the root, plus reposts of those reposts (common chain).
  $prior_sharers = $wpdb->get_col($wpdb->prepare(
    "SELECT DISTINCT author_wp_id FROM $posts\n".
    " WHERE type='repost' AND (parent_post_id=%d OR parent_post_id IN (SELECT id FROM $posts WHERE type='repost' AND parent_post_id=%d))",
    $root_id,
    $root_id
  ));
  $notify = [];
  foreach (($prior_sharers ?: []) as $uid) {
    $uid = (int)$uid;
    if ($uid <= 0 || $uid === $actor_wp_id) continue;
    $notify[] = $uid;
  }
  $notify = array_values(array_unique($notify));
  if (!empty($notify)) {
    $url = ia_connect_build_post_url($root_id);
    $subj = '[' . $site . '] ' . $actor_name . ' reshared a post you shared';
    $body = "{$actor_name} shared a post that you previously shared.\n\nOpen post: {$url}\n";
    foreach ($notify as $uid) {
      ia_connect_send_email($uid, 'ia_connect_reshare_shared_post', [
        'display_name' => (string)(get_userdata($uid)->display_name ?? ''),
        'actor_name' => $actor_name,
        'actor_username' => (string)($actor->user_login ?? ''),
        'post_url' => $url,
        'post_id' => $root_id,
      ], $subj, $body);
    }
  }
}
