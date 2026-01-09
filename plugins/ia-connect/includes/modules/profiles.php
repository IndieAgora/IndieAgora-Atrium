<?php
if (!defined('ABSPATH')) exit;

final class ia_connect_module_profiles implements ia_connect_module_interface {

  public function boot(): void {
    // Render-time hooks.
    // - Add robots noindex when a profile sets "Discourage search engines".
    add_filter('wp_robots', [$this, 'filter_wp_robots'], 20, 1);
    // Fallback for older WP where wp_robots may not add a tag automatically.
    add_action('wp_head', [$this, 'maybe_print_robots_meta'], 1);
  }

  public function ajax_routes(): array {
    return [
      'ia_connect_get_profile'  => ['method' => 'ajax_get_profile', 'public' => false],
      'ia_connect_user_search'  => ['method' => 'ajax_user_search', 'public' => false],
    ];
  }

  /**
   * Resolve a WP user id from a phpBB user id or username.
   * This is intentionally defensive because identity mapping is handled by other Atrium plugins.
   */
  private function resolve_wp_user_id(int $phpbb_id, string $username): int {
    $phpbb_id = (int) $phpbb_id;
    $username = sanitize_user($username, true);

    // 1) Known meta keys used by shadow/identity-map plugins
    if ($phpbb_id > 0) {
      global $wpdb;
      $candidates = [
        'ia_phpbb_user_id',
        'phpbb_user_id',
        'ia_phpbb_uid',
        'phpbb_uid',
        'ia_identity_phpbb',
      ];
      foreach ($candidates as $k) {
        $uid = (int) $wpdb->get_var(
          $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            $k,
            (string) $phpbb_id
          )
        );
        if ($uid > 0) return $uid;
      }
    }

    // 2) Username match (login, nicename, slug)
    if ($username !== '') {
      $u = get_user_by('login', $username);
      if ($u && !empty($u->ID)) return (int) $u->ID;

      $u = get_user_by('slug', $username);
      if ($u && !empty($u->ID)) return (int) $u->ID;

      // last chance: search display_name
      $q = new WP_User_Query([
        'search'         => '*' . $username . '*',
        'search_columns' => ['user_login', 'user_nicename', 'display_name'],
        'number'         => 1,
        'fields'         => ['ID'],
      ]);
      $ids = $q->get_results();
      if (!empty($ids[0])) return (int) $ids[0]->ID;
    }

    return 0;
  }

  private function build_profile(int $wp_user_id): array {
    $u = get_userdata($wp_user_id);
    if (!$u) return [];

    $handle = 'agorian/' . ($u->user_login ?: '');
    $display = $u->display_name ?: $u->user_login;

    $bio = (string) get_user_meta($wp_user_id, 'ia_connect_bio', true);

    // Avatar
    $avatar_url = '';
    $avatar_id = (int) get_user_meta($wp_user_id, 'ia_connect_avatar_id', true);
    if ($avatar_id > 0) {
      $avatar_url = (string) wp_get_attachment_image_url($avatar_id, 'thumbnail');
      if ($avatar_url === '') {
        // Some setups do not generate intermediate sizes; fall back.
        $avatar_url = (string) wp_get_attachment_image_url($avatar_id, 'full');
      }
    }
    if ($avatar_url === '') {
      // legacy/meta fallback
      $avatar_url = (string) get_user_meta($wp_user_id, 'ia_connect_avatar_url', true);
    }
    if ($avatar_url === '') {
      $avatar_url = get_avatar_url($wp_user_id, ['size' => 256]);
    }

    // Cover
    $cover_url = '';
    $cover_id = (int) get_user_meta($wp_user_id, 'ia_connect_cover_id', true);
    if ($cover_id > 0) {
      $cover_url = (string) wp_get_attachment_image_url($cover_id, 'large');
      if ($cover_url === '') {
        $cover_url = (string) wp_get_attachment_image_url($cover_id, 'full');
      }
    }
    if ($cover_url === '') {
      $cover_url = (string) get_user_meta($wp_user_id, 'ia_connect_cover_url', true);
    }

    $privacy = get_user_meta($wp_user_id, 'ia_connect_privacy', true);
    if (!is_array($privacy)) $privacy = [];

    // Back-compat: old boolean hide_profile becomes visibility=hidden.
    if (!empty($privacy['hide_profile']) && empty($privacy['profile_visibility'])) {
      $privacy['profile_visibility'] = 'hidden';
    }

    if (empty($privacy['profile_visibility'])) {
      $privacy['profile_visibility'] = 'public';
    }

    return [
      'wp_user_id' => (int) $wp_user_id,
      'username'   => (string) $u->user_login,
      'display'    => (string) $display,
      'handle'     => (string) $handle,
      'bio'        => (string) $bio,
      'avatarUrl'  => (string) $avatar_url,
      'coverUrl'   => (string) $cover_url,
      'privacy'    => $privacy,
      'isSelf'     => ((int) get_current_user_id() === (int) $wp_user_id),
      'isFollowing'=> ia_connect_module_follow::is_following((int) get_current_user_id(), (int) $wp_user_id),
      'followers'  => ia_connect_module_follow::follower_count((int) $wp_user_id),
      'following'  => ia_connect_module_follow::following_count((int) $wp_user_id),
    ];
  }

  /**
   * Determine if the current viewer is allowed to access a profile.
   * Visibility options:
   *  - public: everyone logged-in
   *  - friends: only mutual followers
   *  - hidden: no one (except self/admin)
   */
  private function can_view_profile(int $viewer_id, int $target_id, array $privacy): bool {
    if ($viewer_id === $target_id) return true;
    if (current_user_can('manage_options')) return true;

    $vis = isset($privacy['profile_visibility']) ? (string) $privacy['profile_visibility'] : '';
    if ($vis === '') {
      $vis = !empty($privacy['hide_profile']) ? 'hidden' : 'public';
    }

    if ($vis === 'hidden') return false;
    if ($vis === 'friends') {
      return ia_connect_module_follow::is_friend($viewer_id, $target_id);
    }

    // public
    return true;
  }

  public function ajax_get_profile(): void {
    ia_connect_support_security::require_login();
    ia_connect_support_security::check_nonce('nonce');

    $phpbb_id = isset($_POST['phpbb_user_id']) ? (int) $_POST['phpbb_user_id'] : 0;
    $username = isset($_POST['username']) ? (string) wp_unslash($_POST['username']) : '';

    $wp_user_id = $this->resolve_wp_user_id($phpbb_id, $username);
    if ($wp_user_id <= 0) {
      wp_send_json_error(['message' => 'Profile not found.'], 404);
    }

    // Privacy enforcement.
    $me = (int) get_current_user_id();
    $privacy = get_user_meta($wp_user_id, 'ia_connect_privacy', true);
    if (!is_array($privacy)) $privacy = [];
    if (!$this->can_view_profile($me, (int) $wp_user_id, $privacy)) {
      wp_send_json_error(['message' => 'User not available due to their privacy settings.'], 403);
    }

    $profile = $this->build_profile($wp_user_id);
    if (!$profile) {
      wp_send_json_error(['message' => 'Profile not found.'], 404);
    }

    wp_send_json_success([
      'profile' => $profile,
    ]);
  }

  public function ajax_user_search(): void {
    ia_connect_support_security::require_login();
    ia_connect_support_security::check_nonce('nonce');

    $q = isset($_POST['q']) ? trim((string) wp_unslash($_POST['q'])) : '';
    if ($q === '') {
      wp_send_json_success(['results' => []]);
    }

    $q_s = sanitize_text_field($q);

    $query = new WP_User_Query([
      'search'         => '*' . $q_s . '*',
      'search_columns' => ['user_login', 'user_nicename', 'display_name'],
      'number'         => 10,
      'fields'         => ['ID', 'user_login', 'display_name'],
    ]);

    $out = [];
    foreach ($query->get_results() as $u) {
      $uid = (int) $u->ID;

      $privacy = get_user_meta($uid, 'ia_connect_privacy', true);
      if (!is_array($privacy)) $privacy = [];

      // Back-compat: hide_profile => hidden.
      if (!empty($privacy['hide_profile']) && empty($privacy['profile_visibility'])) {
        $privacy['profile_visibility'] = 'hidden';
      }
      $vis = isset($privacy['profile_visibility']) ? (string) $privacy['profile_visibility'] : 'public';
      if ($vis === '') $vis = 'public';

      // Hide from search unless viewer is allowed to view.
      $me = (int) get_current_user_id();
      if (!$this->can_view_profile($me, $uid, $privacy)) continue;

      
      $phpbb = (string) get_user_meta($uid, 'ia_phpbb_user_id', true);
      if ($phpbb === '') $phpbb = (string) get_user_meta($uid, 'phpbb_user_id', true);

      $out[] = [
        'wp_user_id'    => $uid,
        'username'      => (string) $u->user_login,
        'display'       => (string) ($u->display_name ?: $u->user_login),
        'phpbb_user_id' => (int) ($phpbb ?: 0),
        'avatarUrl'     => get_avatar_url($uid, ['size' => 64]),
      ];
    }

    wp_send_json_success([
      'results' => $out,
    ]);
  }

  /**
   * Resolve a target wp_user_id for the current request (for robots meta).
   * Uses the same query vars the Connect shell uses:
   *  - ia_profile (phpBB id)
   *  - ia_profile_name (username)
   */
  private function resolve_target_for_request(): int {
    $phpbb_id = isset($_GET['ia_profile']) ? (int) $_GET['ia_profile'] : 0;
    $username = isset($_GET['ia_profile_name']) ? sanitize_user((string) wp_unslash($_GET['ia_profile_name']), true) : '';

    // If neither is present, no profile context.
    if ($phpbb_id <= 0 && $username === '') return 0;

    // Map phpBB id via usermeta.
    if ($phpbb_id > 0) {
      global $wpdb;
      $candidates = ['ia_phpbb_user_id','phpbb_user_id','ia_phpbb_uid','phpbb_uid','ia_identity_phpbb'];
      foreach ($candidates as $k) {
        $uid = (int) $wpdb->get_var(
          $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            $k,
            (string) $phpbb_id
          )
        );
        if ($uid > 0) return $uid;
      }
    }

    // Username -> WP user.
    if ($username !== '') {
      $u = get_user_by('login', $username);
      if ($u && !empty($u->ID)) return (int) $u->ID;
      $u = get_user_by('slug', $username);
      if ($u && !empty($u->ID)) return (int) $u->ID;
    }

    return 0;
  }

  /**
   * Add robots rules for discouraged profiles.
   */
  public function filter_wp_robots(array $robots): array {
    // Only act on Connect profile URLs.
    $is_connect = (!empty($_GET['tab']) && (string) $_GET['tab'] === 'connect')
      || isset($_GET['ia_profile'])
      || isset($_GET['ia_profile_name']);
    if (!$is_connect) return $robots;

    $uid = $this->resolve_target_for_request();
    if ($uid <= 0) return $robots;

    $privacy = get_user_meta($uid, 'ia_connect_privacy', true);
    if (!is_array($privacy)) $privacy = [];
    $discourage = !empty($privacy['discourage_search']);
    if ($discourage) {
      $robots['noindex'] = true;
      $robots['nofollow'] = true;
    }
    return $robots;
  }

  /**
   * Fallback output for older WP installs.
   */
  public function maybe_print_robots_meta(): void {
    $is_connect = (!empty($_GET['tab']) && (string) $_GET['tab'] === 'connect')
      || isset($_GET['ia_profile'])
      || isset($_GET['ia_profile_name']);
    if (!$is_connect) return;

    $uid = $this->resolve_target_for_request();
    if ($uid <= 0) return;

    $privacy = get_user_meta($uid, 'ia_connect_privacy', true);
    if (!is_array($privacy)) $privacy = [];
    if (!empty($privacy['discourage_search'])) {
      echo "\n<meta name=\"robots\" content=\"noindex,nofollow\">\n";
    }
  }
}
