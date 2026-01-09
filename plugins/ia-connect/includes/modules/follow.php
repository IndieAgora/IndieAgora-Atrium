<?php
if (!defined('ABSPATH')) exit;

final class ia_connect_module_follow implements ia_connect_module_interface {

  public static function table_name(): string {
    global $wpdb;
    return $wpdb->prefix . 'ia_connect_follow';
  }

  public function boot(): void {}

  public function ajax_routes(): array {
    return [
      'ia_connect_follow_toggle' => ['method' => 'ajax_follow_toggle', 'public' => false],
    ];
  }

  public static function is_following(int $follower_id, int $followee_id): bool {
    if ($follower_id <= 0 || $followee_id <= 0) return false;
    if ($follower_id === $followee_id) return false;
    global $wpdb;
    $t = self::table_name();
    $sql = $wpdb->prepare("SELECT 1 FROM {$t} WHERE follower_id=%d AND followee_id=%d LIMIT 1", $follower_id, $followee_id);
    return (bool) $wpdb->get_var($sql);
  }

  /**
   * "Friend" = mutual follow (A follows B AND B follows A).
   * This avoids introducing a separate friend-request system while still
   * enabling a "friends only" privacy mode.
   */
  public static function is_friend(int $a, int $b): bool {
    $a = (int) $a; $b = (int) $b;
    if ($a <= 0 || $b <= 0) return false;
    if ($a === $b) return true;
    return self::is_following($a, $b) && self::is_following($b, $a);
  }

  public static function follower_count(int $followee_id): int {
    if ($followee_id <= 0) return 0;
    global $wpdb;
    $t = self::table_name();
    $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE followee_id=%d", $followee_id);
    return (int) $wpdb->get_var($sql);
  }

  public static function following_count(int $follower_id): int {
    if ($follower_id <= 0) return 0;
    global $wpdb;
    $t = self::table_name();
    $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE follower_id=%d", $follower_id);
    return (int) $wpdb->get_var($sql);
  }

  public function ajax_follow_toggle(): void {
    ia_connect_support_security::require_login();
    ia_connect_support_security::check_nonce('nonce');

    $me = (int) get_current_user_id();
    $target = isset($_POST['target_wp_user_id']) ? (int) $_POST['target_wp_user_id'] : 0;

    if ($target <= 0) {
      wp_send_json_error(['message' => 'Missing target.'], 400);
    }
    if ($me === $target) {
      wp_send_json_error(['message' => 'You cannot follow yourself.'], 400);
    }

    // Respect target privacy: if target is hidden, do not allow follows (unless admin).
    $privacy = get_user_meta($target, 'ia_connect_privacy', true);
    if (!is_array($privacy)) $privacy = [];
    $hidden = !empty($privacy['hide_profile']);

    if ($hidden && !current_user_can('manage_options')) {
      wp_send_json_error(['message' => 'User not available due to their privacy settings.'], 403);
    }

    global $wpdb;
    $t = self::table_name();

    $is_following = self::is_following($me, $target);

    if ($is_following) {
      $wpdb->delete($t, ['follower_id' => $me, 'followee_id' => $target], ['%d', '%d']);
      $is_following = false;
    } else {
      $wpdb->insert($t, [
        'follower_id' => $me,
        'followee_id' => $target,
        'created_at'  => current_time('mysql', true),
      ], ['%d','%d','%s']);
      $is_following = true;
    }

    wp_send_json_success([
      'isFollowing'    => $is_following,
      'followerCount'  => self::follower_count($target),
      'followingCount' => self::following_count($me),
      'message'        => $is_following ? 'Followed.' : 'Unfollowed.',
    ]);
  }
}
