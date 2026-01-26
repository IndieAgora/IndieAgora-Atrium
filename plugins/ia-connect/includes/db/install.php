<?php
if (!defined('ABSPATH')) exit;

function ia_connect_db_install(): void {
  global $wpdb;
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  $charset = $wpdb->get_charset_collate();
  $posts = $wpdb->prefix . 'ia_connect_posts';
  $atts  = $wpdb->prefix . 'ia_connect_attachments';
  $comms = $wpdb->prefix . 'ia_connect_comments';

  // Wall posts are anchored to a profile owner by phpBB user id (canonical in Atrium) when available.
  // For BuddyPress/WordPress-only contexts, we also store wall_owner_wp_id.

  $sql1 = "CREATE TABLE $posts (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    wall_owner_wp_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    wall_owner_phpbb_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    author_wp_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    author_phpbb_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    type VARCHAR(20) NOT NULL DEFAULT 'status',
    parent_post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    shared_tab VARCHAR(20) NOT NULL DEFAULT '',
    shared_ref VARCHAR(191) NOT NULL DEFAULT '',
    title VARCHAR(200) NOT NULL DEFAULT '',
    body LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'publish',
    PRIMARY KEY  (id),
    KEY wall_phpbb (wall_owner_phpbb_id, id),
    KEY wall_wp (wall_owner_wp_id, id),
    KEY author_wp (author_wp_id, id),
    KEY parent (parent_post_id)
  ) $charset;";

  $sql2 = "CREATE TABLE $atts (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT(20) UNSIGNED NOT NULL,
    sort_order INT(11) NOT NULL DEFAULT 0,
    url TEXT NOT NULL,
    mime VARCHAR(100) NOT NULL DEFAULT '',
    kind VARCHAR(20) NOT NULL DEFAULT 'file',
    file_name TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY post (post_id, sort_order)
  ) $charset;";

  $sql3 = "CREATE TABLE $comms (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id BIGINT(20) UNSIGNED NOT NULL,
    parent_comment_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    author_wp_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    author_phpbb_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    body LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY post (post_id, id),
    KEY parent (parent_comment_id)
  ) $charset;";

  dbDelta($sql1);
  dbDelta($sql2);
  dbDelta($sql3);

  update_option('ia_connect_db_ver', IA_CONNECT_DB_VER, false);
}

/**
 * Returns true when all IA Connect wall tables exist.
 *
 * We must always use WordPress' own $wpdb (never hardcode DB names, prefixes or credentials).
 */
function ia_connect_db_tables_exist(): bool {
  global $wpdb;
  $tables = [
    $wpdb->prefix . 'ia_connect_posts',
    $wpdb->prefix . 'ia_connect_attachments',
    $wpdb->prefix . 'ia_connect_comments',
  ];
  foreach ($tables as $t) {
    // SHOW TABLES LIKE requires a pattern; table names can include underscores.
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
    if (!$exists || $exists !== $t) return false;
  }
  return true;
}

/**
 * Safety net: if activation hook was skipped or failed, install tables on first use.
 * This avoids "table doesn't exist" errors in Ajax.
 */
function ia_connect_db_maybe_install(): void {
  // Avoid repeated work.
  static $ran = false;
  if ($ran) return;
  $ran = true;

  $ver = (string) get_option('ia_connect_db_ver', '');
  $needs = ($ver !== (string) IA_CONNECT_DB_VER) || !ia_connect_db_tables_exist();
  if (!$needs) return;

  // Cheap lock to avoid concurrent installs.
  if (get_transient('ia_connect_db_installing')) return;
  set_transient('ia_connect_db_installing', 1, 60);

  if (function_exists('ia_connect_db_install')) {
    ia_connect_db_install();
  }

  delete_transient('ia_connect_db_installing');
}
