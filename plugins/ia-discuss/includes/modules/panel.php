<?php
if (!defined('ABSPATH')) exit;

/**
 * Discuss panel renderer.
 * MUST output the root mount element for JS: [data-ia-discuss-root]
 */

if (!function_exists('ia_discuss_module_panel_boot')) {

  function ia_discuss_module_panel_boot(): void {
    // Atrium render slot
    add_action('ia_atrium_panel_discuss', 'ia_discuss_render_panel', 10);
  }

  function ia_discuss_render_panel(): void {
    // Always render a mount point.
    echo '<div class="ia-discuss-root" data-ia-discuss-root>';
    echo '  <div class="iad-loading">Loading…</div>';
    echo '</div>';
  }

}
