<?php
/**
 * Plugin Name: IA Discuss
 * Description: Atrium Discuss panel (phpBB-backed) with mobile-first feed + agoras + modal topic view.
 * Version: 0.3.0
 * Author: IndieAgora
 * Text Domain: ia-discuss
 */

if (!defined('ABSPATH')) exit;

/**
 * Root constants MUST exist before any includes.
 * This file must live at: wp-content/plugins/ia-discuss/ia-discuss.php
 */
if (!defined('IA_DISCUSS_VERSION')) define('IA_DISCUSS_VERSION', '0.3.0');
if (!defined('IA_DISCUSS_PATH'))    define('IA_DISCUSS_PATH', plugin_dir_path(__FILE__));
if (!defined('IA_DISCUSS_URL'))     define('IA_DISCUSS_URL', plugin_dir_url(__FILE__));

/**
 * Safety: if the orchestrator file is missing, fail gracefully (no fatal).
 */
$boot = IA_DISCUSS_PATH . 'includes/ia-discuss.php';
if (!file_exists($boot)) {
  // Don’t fatal the entire site.
  add_action('admin_notices', function () use ($boot) {
    echo '<div class="notice notice-error"><p><strong>IA Discuss:</strong> missing boot file: ' . esc_html($boot) . '</p></div>';
  });
  return;
}

require_once $boot;

add_action('plugins_loaded', function () {
  if (function_exists('ia_discuss_boot')) ia_discuss_boot();
}, 20);
