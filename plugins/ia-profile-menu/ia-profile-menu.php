<?php
/**
 * Plugin Name: IA Profile Menu
 * Description: Replaces Atrium's Profile dropdown items (zero-touch: no changes to ia-atrium).
 * Version: 0.1.1
 * Author: IndieAgora
 */

if (!defined('ABSPATH')) exit;

final class IA_Profile_Menu {
  private static $instance = null;

  public static function instance(): self {
    if (!self::$instance) self::$instance = new self();
    return self::$instance;
  }

  private function __construct() {
    add_action('wp_enqueue_scripts', [$this, 'enqueue']);
  }

  public function enqueue() {
    // Only load when Atrium is present (best-effort).
    if (!is_singular()) return;
    global $post;
    if (!$post || !has_shortcode($post->post_content ?? '', 'ia-atrium')) return;

    wp_enqueue_script(
      'ia-profile-menu',
      plugins_url('assets/js/ia-profile-menu.js', __FILE__),
      ['ia-atrium'],
      '0.1.1',
      true
    );

    wp_enqueue_style(
      'ia-profile-menu',
      plugins_url('assets/css/ia-profile-menu.css', __FILE__),
      [],
      '0.1.1'
    );

    // Pass admin capability + admin URL to JS (no ia-atrium changes).
    wp_add_inline_script('ia-profile-menu', 'window.IA_PROFILE_MENU = ' . wp_json_encode([
      'isAdmin'  => current_user_can('manage_options'),
      'adminUrl' => admin_url(),
    ]) . ';', 'before');
  }
}

add_action('plugins_loaded', ['IA_Profile_Menu', 'instance']);
