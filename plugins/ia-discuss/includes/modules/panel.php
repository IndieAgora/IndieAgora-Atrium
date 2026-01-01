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
    // IMPORTANT: UI expects data-logged-in for showing certain actions (e.g. Create Agora).
    $logged_in = is_user_logged_in() ? '1' : '0';
    // Expose admin capability for optional UI gating (does NOT grant ACP access).
    $is_admin  = (is_user_logged_in() && current_user_can('manage_options')) ? '1' : '0';

    echo '<div class="ia-discuss-root" data-ia-discuss-root data-logged-in="' . esc_attr($logged_in) . '" data-is-admin="' . esc_attr($is_admin) . '">';
    echo '  <div class="iad-loading">Loading…</div>';
    echo '</div>';
  }

}
