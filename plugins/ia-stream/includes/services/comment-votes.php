<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Stream Comment Votes (local)
 *
 * PeerTube OpenAPI (as provided in this project) does not expose a public
 * like/dislike endpoint for video comments. Atrium Stream therefore stores
 * comment votes locally.
 */
final class IA_Stream_Service_Comment_Votes {

  public static function table(): string {
    global $wpdb;
    return ($wpdb instanceof wpdb) ? ($wpdb->prefix . 'ia_stream_comment_votes') : 'wp_ia_stream_comment_votes';
  }

  /**
   * Get vote counts for a list of comment IDs.
   * Returns: [comment_id => ['up'=>int,'down'=>int]]
   */
  public static function counts(array $comment_ids): array {
    global $wpdb;
    if (!($wpdb instanceof wpdb)) return [];

    $ids = array_values(array_filter(array_map('strval', $comment_ids), function ($v) {
      return trim($v) !== '';
    }));
    $ids = array_slice(array_unique($ids), 0, 500);
    if (!$ids) return [];

    $table = self::table();
    $place = implode(',', array_fill(0, count($ids), '%s'));
    $sql = "SELECT comment_id,
                   SUM(CASE WHEN rating=1 THEN 1 ELSE 0 END) AS up,
                   SUM(CASE WHEN rating=-1 THEN 1 ELSE 0 END) AS down
            FROM {$table}
            WHERE comment_id IN ({$place})
            GROUP BY comment_id";

    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$ids), ARRAY_A);
    $out = [];
    if (is_array($rows)) {
      foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $cid = (string)($r['comment_id'] ?? '');
        if ($cid === '') continue;
        $out[$cid] = [
          'up' => (int)($r['up'] ?? 0),
          'down' => (int)($r['down'] ?? 0),
        ];
      }
    }

    // Ensure empty ids are still present with zeros (optional)
    foreach ($ids as $cid) {
      if (!isset($out[$cid])) $out[$cid] = ['up' => 0, 'down' => 0];
    }

    return $out;
  }

  /**
   * Get the current user's vote for each comment.
   * Returns: [comment_id => -1|0|1]
   */
  public static function user_votes(int $phpbb_user_id, array $comment_ids): array {
    global $wpdb;
    if (!($wpdb instanceof wpdb)) return [];
    if ($phpbb_user_id <= 0) return [];

    $ids = array_values(array_filter(array_map('strval', $comment_ids), function ($v) {
      return trim($v) !== '';
    }));
    $ids = array_slice(array_unique($ids), 0, 500);
    if (!$ids) return [];

    $table = self::table();
    $place = implode(',', array_fill(0, count($ids), '%s'));
    $sql = "SELECT comment_id, rating
            FROM {$table}
            WHERE phpbb_user_id=%d AND comment_id IN ({$place})";
    $args = array_merge([$phpbb_user_id], $ids);
    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);

    $out = [];
    if (is_array($rows)) {
      foreach ($rows as $r) {
        $cid = (string)($r['comment_id'] ?? '');
        if ($cid === '') continue;
        $out[$cid] = (int)($r['rating'] ?? 0);
      }
    }

    foreach ($ids as $cid) {
      if (!isset($out[$cid])) $out[$cid] = 0;
    }
    return $out;
  }

  /**
   * Upsert vote.
   * rating must be -1, 0, or 1. rating=0 removes vote.
   */
  public static function set_vote(int $phpbb_user_id, string $comment_id, int $rating): bool {
    global $wpdb;
    if (!($wpdb instanceof wpdb)) return false;
    if ($phpbb_user_id <= 0) return false;
    $comment_id = trim((string)$comment_id);
    if ($comment_id === '') return false;
    if ($rating !== -1 && $rating !== 0 && $rating !== 1) return false;

    $table = self::table();
    $now = current_time('mysql');

    if ($rating === 0) {
      $wpdb->delete($table, ['phpbb_user_id' => $phpbb_user_id, 'comment_id' => $comment_id], ['%d','%s']);
      return true;
    }

    // Update if exists, else insert.
    $exists = (int)$wpdb->get_var($wpdb->prepare(
      "SELECT id FROM {$table} WHERE phpbb_user_id=%d AND comment_id=%s LIMIT 1",
      $phpbb_user_id,
      $comment_id
    ));

    if ($exists > 0) {
      $wpdb->update(
        $table,
        ['rating' => $rating, 'updated_at' => $now],
        ['id' => $exists],
        ['%d','%s'],
        ['%d']
      );
      return true;
    }

    $wpdb->insert(
      $table,
      [
        'phpbb_user_id' => $phpbb_user_id,
        'comment_id' => $comment_id,
        'rating' => $rating,
        'created_at' => $now,
        'updated_at' => $now,
      ],
      ['%d','%s','%d','%s','%s']
    );

    return true;
  }
}
