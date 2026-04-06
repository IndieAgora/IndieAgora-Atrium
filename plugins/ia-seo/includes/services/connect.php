<?php
if (!defined('ABSPATH')) exit;

class IA_SEO_Connect_DB {
  public function get_posts(int $limit): array {
    if ($limit <= 0) return [];
    global $wpdb;
    $posts = $wpdb->prefix . 'ia_connect_posts';
    $comms = $wpdb->prefix . 'ia_connect_comments';

    $has_posts = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $posts));
    if (!$has_posts) return [];

    // Pull post lastmod as max(updated_at, latest comment updated_at).
    $sql = "
      SELECT p.id,
             p.wall_owner_wp_id,
             p.author_wp_id,
             p.updated_at,
             p.created_at,
             GREATEST(
               UNIX_TIMESTAMP(p.updated_at),
               UNIX_TIMESTAMP(p.created_at),
               IFNULL((SELECT UNIX_TIMESTAMP(MAX(c.updated_at)) FROM $comms c WHERE c.post_id = p.id AND c.is_deleted = 0), 0)
             ) AS lastmod_unix
      FROM $posts p
      WHERE p.status = 'publish'
      ORDER BY lastmod_unix DESC
      LIMIT %d";

    return (array)$wpdb->get_results($wpdb->prepare($sql, $limit), ARRAY_A);
  }

  public function get_public_profiles(int $limit): array {
    if ($limit <= 0) return [];
    global $wpdb;

    $users = $wpdb->users;
    $posts = $wpdb->prefix . 'ia_connect_posts';
    $has_posts = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $posts));
    $has_posts = is_string($has_posts) && $has_posts !== '';

    $sql = "SELECT ID, user_login, user_nicename, display_name, UNIX_TIMESTAMP(user_registered) AS registered_unix FROM {$users} ORDER BY ID DESC LIMIT %d";
    $rows = (array)$wpdb->get_results($wpdb->prepare($sql, $limit), ARRAY_A);
    if (!$rows) return [];

    $out = [];
    foreach ($rows as $row) {
      $wp_user_id = (int)($row['ID'] ?? 0);
      if ($wp_user_id <= 0) continue;
      if (!function_exists('ia_connect_user_seo_visible') || !function_exists('ia_connect_user_profile_searchable')) continue;
      if (!ia_connect_user_seo_visible($wp_user_id) || !ia_connect_user_profile_searchable($wp_user_id)) continue;

      $slug = sanitize_user((string)($row['user_nicename'] ?? ''), true);
      if ($slug === '') $slug = sanitize_user((string)($row['user_login'] ?? ''), true);
      if ($slug === '') continue;

      $lastmod = (int)($row['registered_unix'] ?? 0);
      if ($has_posts) {
        $post_lastmod = (int)$wpdb->get_var($wpdb->prepare(
          "SELECT GREATEST(IFNULL(UNIX_TIMESTAMP(MAX(updated_at)),0), IFNULL(UNIX_TIMESTAMP(MAX(created_at)),0)) FROM {$posts} WHERE status='publish' AND wall_owner_wp_id=%d",
          $wp_user_id
        ));
        if ($post_lastmod > $lastmod) $lastmod = $post_lastmod;
      }
      if ($lastmod <= 0) $lastmod = time();

      $out[] = [
        'wp_user_id' => $wp_user_id,
        'slug' => $slug,
        'display_name' => (string)($row['display_name'] ?? ''),
        'lastmod_unix' => $lastmod,
      ];
    }

    return $out;
  }
}


if (!class_exists('IA_SEO_Connect_Metadata_DB')) {
class IA_SEO_Connect_Metadata_DB extends IA_SEO_Connect_DB {
  public function get_profile_by_slug(string $slug): ?array {
    global $wpdb;
    $slug = sanitize_user($slug, true);
    if ($slug === '') return null;

    $user = $wpdb->get_row($wpdb->prepare(
      "SELECT ID, user_login, user_nicename, display_name, user_registered FROM {$wpdb->users} WHERE user_nicename=%s OR user_login=%s LIMIT 1",
      $slug,
      $slug
    ), ARRAY_A);
    if (!is_array($user)) return null;

    $wp_user_id = (int)($user['ID'] ?? 0);
    if ($wp_user_id <= 0) return null;
    if (function_exists('ia_connect_user_seo_visible') && !ia_connect_user_seo_visible($wp_user_id)) return null;
    if (function_exists('ia_connect_user_profile_searchable') && !ia_connect_user_profile_searchable($wp_user_id)) return null;

    $posts_tbl = $wpdb->prefix . 'ia_connect_posts';
    $has_posts = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $posts_tbl));
    $post_count = 0;
    $recent_titles = [];
    $recent_bodies = [];
    if (is_string($has_posts) && $has_posts !== '') {
      $post_count = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$posts_tbl} WHERE status='publish' AND wall_owner_wp_id=%d",
        $wp_user_id
      ));
      $rows = (array)$wpdb->get_results($wpdb->prepare(
        "SELECT title, body FROM {$posts_tbl} WHERE status='publish' AND wall_owner_wp_id=%d ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 3",
        $wp_user_id
      ), ARRAY_A);
      foreach ($rows as $row) {
        $title = trim((string)($row['title'] ?? ''));
        $body = trim((string)($row['body'] ?? ''));
        if ($title !== '') $recent_titles[] = $title;
        if ($body !== '') $recent_bodies[] = $body;
      }
    }

    return [
      'wp_user_id' => $wp_user_id,
      'slug' => $slug,
      'display_name' => (string)($user['display_name'] ?? ''),
      'registered' => (string)($user['user_registered'] ?? ''),
      'post_count' => $post_count,
      'recent_titles' => $recent_titles,
      'recent_bodies' => $recent_bodies,
    ];
  }

  public function get_post_by_id(int $post_id): ?array {
    global $wpdb;
    $post_id = (int)$post_id;
    if ($post_id <= 0) return null;
    $posts = $wpdb->prefix . 'ia_connect_posts';
    $comms = $wpdb->prefix . 'ia_connect_comments';
    $has_posts = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $posts));
    if (!is_string($has_posts) || $has_posts === '') return null;

    $row = $wpdb->get_row($wpdb->prepare(
      "SELECT p.id, p.wall_owner_wp_id, p.author_wp_id, p.title, p.body, p.created_at, p.updated_at, u.display_name AS author_name
       FROM {$posts} p
       LEFT JOIN {$wpdb->users} u ON u.ID = p.author_wp_id
       WHERE p.id=%d AND p.status='publish' LIMIT 1",
      $post_id
    ), ARRAY_A);
    if (!is_array($row)) return null;

    $wall_owner_wp_id = (int)($row['wall_owner_wp_id'] ?? 0);
    if ($wall_owner_wp_id > 0 && function_exists('ia_connect_user_seo_visible') && !ia_connect_user_seo_visible($wall_owner_wp_id)) return null;

    $comment_count = 0;
    $comments = [];
    $has_comms = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $comms));
    if (is_string($has_comms) && $has_comms !== '') {
      $comment_count = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$comms} WHERE post_id=%d AND is_deleted=0",
        $post_id
      ));
      $rows = (array)$wpdb->get_results($wpdb->prepare(
        "SELECT body FROM {$comms} WHERE post_id=%d AND is_deleted=0 ORDER BY COALESCE(updated_at, created_at) ASC LIMIT 5",
        $post_id
      ), ARRAY_A);
      foreach ($rows as $c) {
        $body = trim((string)($c['body'] ?? ''));
        if ($body !== '') $comments[] = $body;
      }
    }

    $row['comment_count'] = $comment_count;
    $row['comment_bodies'] = $comments;
    return $row;
  }
}
}
