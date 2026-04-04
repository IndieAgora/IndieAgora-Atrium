<?php
if (!defined('ABSPATH')) exit;

final class IA_Post_Assets {

  public static function enqueue(): void {
    if (is_admin()) return;
    if (!self::atrium_present()) return;

    wp_enqueue_style('ia-post', IA_POST_URL . 'assets/css/ia-post.css', [], IA_POST_VERSION);
    wp_enqueue_script('ia-post', IA_POST_URL . 'assets/js/ia-post.js', [], IA_POST_VERSION, true);

    wp_localize_script('ia-post', 'IA_POST', [
      'version' => IA_POST_VERSION,
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'loggedIn' => is_user_logged_in() ? '1' : '0',
      'me' => [
        'wp' => (int) get_current_user_id(),
        'login' => (string) (is_user_logged_in() ? wp_get_current_user()->user_login : ''),
      ],
    ]);
  }

  private static function atrium_present(): bool {
    if (!is_singular()) return false;
    global $post;
    if (!$post) return false;
    return has_shortcode($post->post_content, 'ia-atrium');
  }
}
