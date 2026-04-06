<?php
/**
 * Plugin Name: IA Connect
 * Description: Atrium Connect mini-platform: profile header + wall posts + settings.
 * Version: 0.5.42
 * Author: IndieAgora
 * Text Domain: ia-connect
 */

if (!defined('ABSPATH')) exit;

define('IA_CONNECT_VERSION', '0.5.42');
define('IA_CONNECT_PATH', plugin_dir_path(__FILE__));
define('IA_CONNECT_URL', plugin_dir_url(__FILE__));

define('IA_CONNECT_PANEL_KEY', 'connect');
define('IA_CONNECT_UPLOAD_SUBDIR', 'ia-connect');

define('IA_CONNECT_DB_VER', '2');

define('IA_CONNECT_META_PROFILE', 'ia_connect_profile_photo');
define('IA_CONNECT_META_COVER', 'ia_connect_cover_photo');

define('IA_CONNECT_META_PRIVACY', 'ia_connect_privacy');

// User signature (bio) + Discuss signature toggle
define('IA_CONNECT_META_SIGNATURE', 'ia_connect_signature');
define('IA_CONNECT_META_SIGNATURE_SHOW_DISCUSS', 'ia_connect_signature_show_discuss');

define('IA_CONNECT_META_HOME_TAB', 'ia_connect_home_tab');
define('IA_CONNECT_META_STYLE', 'ia_connect_style');


define('IA_CONNECT_OPT_SETTINGS', 'ia_connect_settings');

require_once IA_CONNECT_PATH . 'includes/functions.php';

if (function_exists('ia_connect_meta_boot')) {
  ia_connect_meta_boot();
}

register_activation_hook(__FILE__, function () {
  ia_connect_require('includes/db/install.php');
  if (function_exists('ia_connect_db_install')) {
    ia_connect_db_install();
  }
});

add_action('plugins_loaded', function () {
  // Ensure DB tables exist even if activation hook was skipped.
  ia_connect_require('includes/db/install.php');
  if (function_exists('ia_connect_db_maybe_install')) {
    ia_connect_db_maybe_install();
  }

  ia_connect_require('includes/support/assets.php');
  ia_connect_require('includes/support/ajax.php');
  ia_connect_require('includes/support/notifications.php');
  ia_connect_require('includes/modules/panel.php');

  if (function_exists('ia_connect_assets_boot')) ia_connect_assets_boot();
  if (function_exists('ia_connect_ajax_boot')) ia_connect_ajax_boot();

  add_filter('ia_atrium_default_tab', function ($default) {
    if (is_admin() || !is_user_logged_in()) return $default;
    if (!function_exists('ia_connect_get_user_home_tab')) return $default;
    if (isset($_GET['tab']) && $_GET['tab'] !== '') return $default;
    return ia_connect_get_user_home_tab((int) get_current_user_id());
  }, 20, 1);

  add_action('ia_atrium_panel_' . IA_CONNECT_PANEL_KEY, function () {
    if (class_exists('IA_Connect_Module_Panel')) {
      IA_Connect_Module_Panel::render();
    } else {
      echo '<div class="ia-connect-error">IA Connect panel module missing.</div>';
    }
  }, 10, 0);
}, 30);
