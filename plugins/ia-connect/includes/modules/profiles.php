<?php
if (!defined('ABSPATH')) exit;

final class ia_connect_module_profiles implements ia_connect_module_interface {

  public function boot(): void {
    // no render hooks; purely AJAX + helpers
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

    $avatar_url = (string) get_user_meta($wp_user_id, 'ia_connect_avatar_url', true);
    if ($avatar_url === '') {
      // fallback to WP avatar
      $avatar_url = get_avatar_url($wp_user_id, ['size' => 256]);
    }

    $cover_url = (string) get_user_meta($wp_user_id, 'ia_connect_cover_url', true);

    $privacy = get_user_meta($wp_user_id, 'ia_connect_privacy', true);
    if (!is_array($privacy)) $privacy = [];

    return [
      'wp_user_id' => (int) $wp_user_id,
      'username'   => (string) $u->user_login,
      'display'    => (string) $display,
      'handle'     => (string) $handle,
      'bio'        => (string) $bio,
      'avatarUrl'  => (string) $avatar_url,
      'coverUrl'   => (string) $cover_url,
      'privacy'    => $privacy,
    ];
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
}
