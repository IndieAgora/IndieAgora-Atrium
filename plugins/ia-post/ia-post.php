<?php
/**
 * Plugin Name: IA Post
 * Description: Global Atrium post composer (Connect + Discuss). Hooks into the Atrium bottom nav "Post" button.
 * Version: 0.1.7
 * Author: IndieAgora
 * Text Domain: ia-post
 */

if (!defined('ABSPATH')) exit;

define('IA_POST_VERSION','0.1.7');
define('IA_POST_PATH', plugin_dir_path(__FILE__));
define('IA_POST_URL', plugin_dir_url(__FILE__));

require_once IA_POST_PATH . 'includes/class-ia-post-assets.php';
require_once IA_POST_PATH . 'includes/class-ia-post.php';

add_action('plugins_loaded', function () {
  IA_Post::instance();
});
