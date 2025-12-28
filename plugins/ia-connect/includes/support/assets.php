<?php
if (!defined('ABSPATH')) exit;

final class ia_connect_support_assets {

  public static function boot(): void {
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
  }

  private static function has_atrium_shortcode(): bool {
    if (!is_singular()) return false;
    global $post;
    if (!$post) return false;
    return has_shortcode($post->post_content ?? '', 'ia-atrium');
  }

  private static function media_id(int $user_id, string $meta_key): int {
    return (int) get_user_meta($user_id, $meta_key, true);
  }

  private static function media_url(int $user_id, string $meta_key, string $size = 'thumbnail'): string {
    $att_id = self::media_id($user_id, $meta_key);
    if ($att_id) {
      $url = wp_get_attachment_image_url($att_id, $size);
      if ($url) return $url;
    }
    return '';
  }

  private static function avatar_url(int $user_id): string {
    $custom = self::media_url($user_id, 'ia_connect_avatar_id', 'thumbnail');
    if ($custom) return $custom;
    $fallback = get_avatar_url($user_id, ['size' => 160]);
    return $fallback ?: '';
  }

  private static function cover_url(int $user_id): string {
    return self::media_url($user_id, 'ia_connect_cover_id', 'large') ?: '';
  }

  private static function privacy_defaults(): array {
    return [
      'profile_public' => true,
      'show_activity'  => true,
      'allow_mentions' => true,
    ];
  }

  private static function privacy_settings(int $user_id): array {
    $defaults = self::privacy_defaults();
    $raw = get_user_meta($user_id, 'ia_connect_privacy', true);
    if (!is_array($raw)) return $defaults;

    return array_merge($defaults, [
      'profile_public' => !empty($raw['profile_public']),
      'show_activity'  => !empty($raw['show_activity']),
      'allow_mentions' => !empty($raw['allow_mentions']),
    ]);
  }

  public static function enqueue_assets(): void {
    if (is_admin()) return;
    if (!self::has_atrium_shortcode()) return;

    $css_path = IA_CONNECT_PATH . 'assets/css/ia-connect.css';
    $js_path  = IA_CONNECT_PATH . 'assets/js/ia-connect.js';

    wp_enqueue_style(
      'ia-connect',
      IA_CONNECT_URL . 'assets/css/ia-connect.css',
      ['ia-atrium'],
      file_exists($css_path) ? filemtime($css_path) : IA_CONNECT_VERSION
    );

    wp_enqueue_script(
      'ia-connect',
      IA_CONNECT_URL . 'assets/js/ia-connect.js',
      ['ia-atrium'],
      file_exists($js_path) ? filemtime($js_path) : IA_CONNECT_VERSION,
      true
    );

    $is_logged_in = is_user_logged_in();
    $user_id = get_current_user_id();

    $handle = '';
    $display = '';
    $bio = '';
    $avatar_url = '';
    $cover_url = '';
    $privacy = self::privacy_defaults();

    if ($is_logged_in && $user_id) {
      $u = wp_get_current_user();
      $handle = 'agorian/' . ($u->user_login ?: '');
      $display = $u->display_name ?: $u->user_login;
      $bio = (string) get_user_meta($user_id, 'description', true);
      $avatar_url = self::avatar_url($user_id);
      $cover_url = self::cover_url($user_id);
      $privacy = self::privacy_settings($user_id);
    }

    wp_localize_script('ia-connect', 'IA_CONNECT', [
      'ajaxUrl'    => admin_url('admin-ajax.php'),
      'nonce'      => wp_create_nonce('ia_connect_nonce'),
      'isLoggedIn' => $is_logged_in,
      'userId'     => $user_id,
      'handle'     => $handle,
      'display'    => $display,
      'bio'        => $bio,
      'avatarUrl'  => $avatar_url,
      'coverUrl'   => $cover_url,
      'privacy'    => $privacy,
    ]);
  }
}
