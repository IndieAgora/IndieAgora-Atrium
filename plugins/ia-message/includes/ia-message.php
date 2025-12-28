<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Message orchestrator (Safe Boot)
 */
function ia_message_boot(): void {
  try {
    // Support
    ia_message_require('includes/support/install.php');
    ia_message_require('includes/support/security.php');
    ia_message_require('includes/support/assets.php');
    ia_message_require('includes/support/ajax.php');

    // Services
    ia_message_require('includes/services/db.php');
    ia_message_require('includes/services/identity.php');
    ia_message_require('includes/services/threads.php');
    ia_message_require('includes/services/messages.php');
	ia_message_require('includes/services/users.php');


    // Render
    ia_message_require('includes/render/threads.php');
    ia_message_require('includes/render/messages.php');

    // Module render
    ia_message_require('includes/modules/panel.php');

    if (function_exists('ia_message_register_hooks')) {
      ia_message_register_hooks();
    }
  } catch (Throwable $e) {
    ia_message_admin_notice($e->getMessage());
  }
}
