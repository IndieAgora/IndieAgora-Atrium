<?php
/**
 * Plugin Name: IA Connect
 * Description: Atrium Connect surface (profile shell + viewer + bio + privacy toggles). Renders only inside Atrium Connect panel.
 * Version: 1.1.0
 * Author: IndieAgora
 * Text Domain: ia-connect
 */

if (!defined('ABSPATH')) exit;

define('IA_CONNECT_VERSION', '1.1.0');
define('IA_CONNECT_PATH', plugin_dir_path(__FILE__));
define('IA_CONNECT_URL', plugin_dir_url(__FILE__));

/**
 * Activation: create required tables.
 */
function ia_connect_activate(): void {
  global $wpdb;
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  $charset = $wpdb->get_charset_collate();
  $t = $wpdb->prefix . 'ia_connect_follow';

  $sql = "CREATE TABLE {$t} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    follower_id BIGINT UNSIGNED NOT NULL,
    followee_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY follower_followee (follower_id, followee_id),
    KEY followee_id (followee_id),
    KEY follower_id (follower_id)
  ) {$charset};";

  dbDelta($sql);
}

register_activation_hook(__FILE__, 'ia_connect_activate');


require_once IA_CONNECT_PATH . 'includes/ia-connect.php';

add_action('plugins_loaded', function () {
  ia_connect_boot();
}, 20);
