<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Discuss — Asset registration + enqueue
 *
 * This file is booted by ia_discuss_boot() via:
 *   ia_discuss_support_assets_boot()
 *
 * If this function does not exist or does not hook wp_enqueue_scripts,
 * Discuss JS dependencies will never load and boot.js will timeout.
 */

/**
 * Boot hook called by orchestrator.
 * MUST attach wp_enqueue_scripts.
 */
function ia_discuss_support_assets_boot(): void {
  add_action('wp_enqueue_scripts', 'ia_discuss_enqueue_assets', 20);
}

/**
 * Register all Discuss assets (CSS + JS).
 * Uses dependency-safe registration.
 */
function ia_discuss_register_assets(): void {
  $ver = defined('IA_DISCUSS_VERSION') ? IA_DISCUSS_VERSION : '0.0.0';

  // ---------------------------
  // CSS (split files)
  // ---------------------------
  wp_register_style(
    'ia-discuss-base',
    IA_DISCUSS_URL . 'assets/css/ia-discuss.base.css',
    [],
    $ver
  );

  wp_register_style(
    'ia-discuss-layout',
    IA_DISCUSS_URL . 'assets/css/ia-discuss.layout.css',
    ['ia-discuss-base'],
    $ver
  );

  wp_register_style(
    'ia-discuss-cards',
    IA_DISCUSS_URL . 'assets/css/ia-discuss.cards.css',
    ['ia-discuss-layout'],
    $ver
  );

  wp_register_style(
    'ia-discuss-modal',
    IA_DISCUSS_URL . 'assets/css/ia-discuss.modal.css',
    ['ia-discuss-layout'],
    $ver
  );

  wp_register_style(
    'ia-discuss-agora',
    IA_DISCUSS_URL . 'assets/css/ia-discuss.agora.css',
    ['ia-discuss-layout'],
    $ver
  );

  wp_register_style(
    'ia-discuss-composer',
    IA_DISCUSS_URL . 'assets/css/ia-discuss.composer.css',
    ['ia-discuss-layout'],
    $ver
  );

  // ---------------------------
  // JS (dependency-safe)
  // ---------------------------
  $in_footer = true;

  // Core (no deps)
  wp_register_script(
    'ia-discuss-core',
    IA_DISCUSS_URL . 'assets/js/ia-discuss.core.js',
    [],
    $ver,
    $in_footer
  );

  // Services
  wp_register_script(
    'ia-discuss-api',
    IA_DISCUSS_URL . 'assets/js/ia-discuss.api.js',
    ['ia-discuss-core'],
    $ver,
    $in_footer
  );

  wp_register_script(
    'ia-discuss-state',
    IA_DISCUSS_URL . 'assets/js/ia-discuss.state.js',
    ['ia-discuss-core'],
    $ver,
    $in_footer
  );

  // UI modules
  wp_register_script(
    'ia-discuss-ui-shell',
    IA_DISCUSS_URL . 'assets/js/ia-discuss.ui.shell.js',
    ['ia-discuss-core'],
    $ver,
    $in_footer
  );

  wp_register_script(
    'ia-discuss-ui-feed',
    IA_DISCUSS_URL . 'assets/js/ia-discuss.ui.feed.js',
    ['ia-discuss-core', 'ia-discuss-api', 'ia-discuss-state'],
    $ver,
    $in_footer
  );

  wp_register_script(
    'ia-discuss-ui-agora',
    IA_DISCUSS_URL . 'assets/js/ia-discuss.ui.agora.js',
    ['ia-discuss-core', 'ia-discuss-api'],
    $ver,
    $in_footer
  );

  wp_register_script(
    'ia-discuss-ui-topic',
    IA_DISCUSS_URL . 'assets/js/ia-discuss.ui.topic.js',
    ['ia-discuss-core', 'ia-discuss-api', 'ia-discuss-state'],
    $ver,
    $in_footer
  );

  wp_register_script(
    'ia-discuss-ui-composer',
    IA_DISCUSS_URL . 'assets/js/ia-discuss.ui.composer.js',
    ['ia-discuss-core', 'ia-discuss-api'],
    $ver,
    $in_footer
  );

  // Boot LAST — depends on everything
  wp_register_script(
    'ia-discuss-boot',
    IA_DISCUSS_URL . 'assets/js/ia-discuss.boot.js',
    [
      'ia-discuss-core',
      'ia-discuss-api',
      'ia-discuss-state',
      'ia-discuss-ui-shell',
      'ia-discuss-ui-feed',
      'ia-discuss-ui-agora',
      'ia-discuss-ui-topic',
      'ia-discuss-ui-composer',
    ],
    $ver,
    $in_footer
  );

  // Localize EARLY onto core (boot + api rely on it)
  wp_localize_script('ia-discuss-core', 'IA_DISCUSS', [
    'ajaxUrl'  => admin_url('admin-ajax.php'),
    'nonce'    => wp_create_nonce('ia_discuss'),
    'loggedIn' => is_user_logged_in() ? '1' : '0',
    'userId'   => get_current_user_id(),
    'version'  => $ver,
  ]);
}

/**
 * Enqueue assets.
 * Only enqueue boot — dependencies auto-load.
 */
function ia_discuss_enqueue_assets(): void {
  if (is_admin()) return;

  ia_discuss_register_assets();

  // CSS
  wp_enqueue_style('ia-discuss-base');
  wp_enqueue_style('ia-discuss-layout');
  wp_enqueue_style('ia-discuss-cards');
  wp_enqueue_style('ia-discuss-modal');
  wp_enqueue_style('ia-discuss-agora');
  wp_enqueue_style('ia-discuss-composer');

  // JS
  wp_enqueue_script('ia-discuss-boot');
}
