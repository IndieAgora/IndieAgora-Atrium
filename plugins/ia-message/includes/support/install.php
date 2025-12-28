<?php
if (!defined('ABSPATH')) exit;

function ia_message_register_hooks(): void {
  register_activation_hook(IA_MESSAGE_PATH . 'ia-message.php', 'ia_message_install');
  add_action('admin_init', 'ia_message_maybe_upgrade');

  // ✅ Mount hidden messages module inside the Atrium shell
  add_action('ia_atrium_shell_after', 'ia_message_render_shell_mount', 10, 0);

  // Keep this too (optional): if Atrium ever adds a real "messages" panel later, we’re ready.
  add_action('ia_atrium_panel_' . IA_MESSAGE_PANEL_KEY, 'ia_message_render_panel', 10, 0);
}

function ia_message_maybe_upgrade(): void {
  $have = get_option(IA_MESSAGE_DB_OPT, '0.0.0');
  if (version_compare($have, IA_MESSAGE_VERSION, '<')) {
    ia_message_install();
  }
}

function ia_message_install(): void {
  global $wpdb;
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  $charset = $wpdb->get_charset_collate();
  $p = $wpdb->prefix;

  $threads  = $p . 'ia_msg_threads';
  $members  = $p . 'ia_msg_thread_members';
  $messages = $p . 'ia_msg_messages';
  $imap     = $p . 'ia_msg_import_map';

  $sql = [];

  $sql[] = "CREATE TABLE {$threads} (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_key VARCHAR(190) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'dm',
    last_message_id BIGINT(20) UNSIGNED DEFAULT NULL,
    last_activity_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY thread_key (thread_key),
    KEY last_activity_at (last_activity_at)
  ) {$charset};";

  $sql[] = "CREATE TABLE {$members} (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id BIGINT(20) UNSIGNED NOT NULL,
    phpbb_user_id BIGINT(20) UNSIGNED NOT NULL,
    last_read_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
    last_read_message_id BIGINT(20) UNSIGNED DEFAULT NULL,
    is_muted TINYINT(1) NOT NULL DEFAULT 0,
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY thread_member (thread_id, phpbb_user_id),
    KEY phpbb_user_id (phpbb_user_id),
    KEY thread_id (thread_id)
  ) {$charset};";

  $sql[] = "CREATE TABLE {$messages} (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id BIGINT(20) UNSIGNED NOT NULL,
    author_phpbb_user_id BIGINT(20) UNSIGNED NOT NULL,
    body LONGTEXT NOT NULL,
    body_format VARCHAR(20) NOT NULL DEFAULT 'plain',
    created_at DATETIME NOT NULL,
    edited_at DATETIME DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY thread_created (thread_id, created_at),
    KEY author_created (author_phpbb_user_id, created_at)
  ) {$charset};";

  $sql[] = "CREATE TABLE {$imap} (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    source VARCHAR(50) NOT NULL,
    foreign_message_id VARCHAR(190) NOT NULL,
    message_id BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY source_foreign (source, foreign_message_id),
    KEY message_id (message_id)
  ) {$charset};";

  foreach ($sql as $q) dbDelta($q);

  update_option(IA_MESSAGE_DB_OPT, IA_MESSAGE_VERSION);
}
