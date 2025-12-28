<?php
if (!defined('ABSPATH')) exit;

define('IA_MESSAGE_SLUG', 'ia-message');
define('IA_MESSAGE_DB_OPT', 'ia_message_db_version');

/**
 * Module key used by Atrium panel routing (keep stable).
 * If Atrium uses different panel keys, we can alias later.
 */
define('IA_MESSAGE_PANEL_KEY', 'messages');

/**
 * AJAX action namespace (keep stable).
 */
define('IA_MESSAGE_AJAX_NS', 'ia_message');

/**
 * Capability for admin import/tools pages (future).
 */
define('IA_MESSAGE_CAP_ADMIN', 'manage_options');
