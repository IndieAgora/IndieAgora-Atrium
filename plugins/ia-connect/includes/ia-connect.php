<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Connect boot entry.
 * All paths + filenames are lowercase for Linux safety.
 */
function ia_connect_boot(): void {

  // Support services (centralized)
  require_once IA_CONNECT_PATH . 'includes/support/security.php';
  require_once IA_CONNECT_PATH . 'includes/support/assets.php';
  require_once IA_CONNECT_PATH . 'includes/support/ajax.php';

  // Modules
  require_once IA_CONNECT_PATH . 'includes/modules/module-interface.php';
  require_once IA_CONNECT_PATH . 'includes/modules/profiles.php';
  require_once IA_CONNECT_PATH . 'includes/modules/profile-shell.php';
  require_once IA_CONNECT_PATH . 'includes/modules/bio.php';
  require_once IA_CONNECT_PATH . 'includes/modules/media.php';
  require_once IA_CONNECT_PATH . 'includes/modules/privacy.php';

  // Build module list
  $modules = [
    new ia_connect_module_profile_shell(),
    new ia_connect_module_profiles(),
    new ia_connect_module_bio(),
    new ia_connect_module_media(),
    new ia_connect_module_privacy(),
  ];

  // Boot modules
  foreach ($modules as $m) {
    if (method_exists($m, 'boot')) $m->boot();
  }

  // Boot services
  ia_connect_support_security::boot();
  ia_connect_support_assets::boot();
  ia_connect_support_ajax::boot($modules);
}
