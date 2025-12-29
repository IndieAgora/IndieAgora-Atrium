<?php
if (!defined('ABSPATH')) exit;

function ia_message_register_hooks(): void {
  register_activation_hook(IA_MESSAGE_PATH . 'ia-message.php', 'ia_message_install');
  add_action('admin_init', 'ia_message_maybe_upgrade');

  // ✅ Render as an Atrium panel surface (tab), not a modal.
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

  $messages = $wpdb->prefix . 'ia_message_messages';
  $threads  = $wpdb->prefix . 'ia_message_threads';
  $parts    = $wpdb->prefix . 'ia_message_participants';
  $map      = $wpdb->prefix . 'ia_message_id_map';

  $sql = [];

  $sql[] = "CREATE TABLE {$threads} (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_type VARCHAR(20) NOT NULL DEFAULT 'dm',
    title VARCHAR(190) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    last_message_id BIGINT(20) UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    KEY updated_at (updated_at),
    KEY last_message_id (last_message_id)
  ) {$charset};";

  $sql[] = "CREATE TABLE {$parts} (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    last_read_message_id BIGINT(20) UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY thread_user (thread_id, user_id),
    KEY thread_id (thread_id),
    KEY user_id (user_id)
  ) {$charset};";

  $sql[] = "CREATE TABLE {$messages} (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    thread_id BIGINT(20) UNSIGNED NOT NULL,
    sender_id BIGINT(20) UNSIGNED NOT NULL,
    body LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY thread_id (thread_id),
    KEY sender_id (sender_id),
    KEY created_at (created_at)
  ) {$charset};";

  $sql[] = "CREATE TABLE {$map} (
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
