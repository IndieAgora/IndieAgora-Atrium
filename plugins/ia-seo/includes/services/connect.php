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
}
