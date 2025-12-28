<?php
if (!defined('ABSPATH')) exit;

function ia_message_tbl(string $short): string {
  global $wpdb;
  return $wpdb->prefix . $short;
}

function ia_message_now_sql(): string {
  return gmdate('Y-m-d H:i:s');
}
