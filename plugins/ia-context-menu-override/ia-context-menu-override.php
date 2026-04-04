<?php
/**
 * Plugin Name: IndieAgora Context Menu Override
 * Description: Custom right-click menu across Atrium UI for opening destinations in new tabs/windows and copying links. Includes Quote Selection for Discuss.
 * Version: 0.2.3
 * Author: IndieAgora
 */

if (!defined('ABSPATH')) {
  exit;
}

final class IA_Context_Menu_Override {
  const VERSION = '0.2.3';

  public static function init() {
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 99);
  }

  public static function enqueue_assets() {
    // Front-end only.
    if (is_admin()) return;

    $base = plugin_dir_url(__FILE__);

    wp_register_style(
      'ia-context-menu-override',
      $base . 'assets/ia-context-menu-override.css',
      [],
      self::VERSION
    );

    wp_register_script(
      'ia-context-menu-override',
      $base . 'assets/ia-context-menu-override.js',
      [],
      self::VERSION,
      true
    );

    // Allow site owners to disable on specific pages if needed.
    $enabled = apply_filters('ia_context_menu_override_enabled', true);
    if (!$enabled) return;

    wp_enqueue_style('ia-context-menu-override');
    wp_enqueue_script('ia-context-menu-override');

    // Pass minimal config.
    $cfg = [
      'siteOrigin' => home_url('/'),
    ];
    wp_add_inline_script(
      'ia-context-menu-override',
      'window.IA_CONTEXT_MENU_CFG = ' . wp_json_encode($cfg) . ';',
      'before'
    );
  }
}

IA_Context_Menu_Override::init();
