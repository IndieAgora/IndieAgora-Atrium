<?php
/**
 * Plugin Name: IA Mail Suite
 * Description: Manage WordPress email sender, SMTP, templates, overrides, and one-off user emails (IndieAgora Atrium).
 * Version: 0.1.0
 * Author: IndieAgora
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/class-ia-mail-suite.php';

function ia_mail_suite(): IA_Mail_Suite {
  static $inst = null;
  if ($inst === null) $inst = new IA_Mail_Suite();
  return $inst;
}

add_action('plugins_loaded', function () {
  ia_mail_suite()->boot();
});
