<?php
/**
 * Plugin Name: IA User
 * Description: Atrium login/register UI that uses phpBB (phpbb_users) as the user authority. On success, a WP shadow user is created and logged in for session/UI purposes.
 * Version: 0.1.6
 * Author: IndieAgora
 * Text Domain: ia-user
 */

if (!defined('ABSPATH')) exit;

define('IA_USER_VERSION', '0.1.6');
define('IA_USER_PATH', plugin_dir_path(__FILE__));
define('IA_USER_URL', plugin_dir_url(__FILE__));

require_once IA_USER_PATH . 'includes/class-ia-user-phpbb.php';
require_once IA_USER_PATH . 'includes/class-ia-user.php';
require_once IA_USER_PATH . 'includes/ia-message-bridge.php';


add_action('plugins_loaded', function () {
    IA_User::instance();
});
