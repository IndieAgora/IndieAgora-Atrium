<?php
if (!defined('ABSPATH')) exit;

function ia_message_render_panel(): void {
  $tpl = IA_MESSAGE_PATH . 'includes/templates/panel.php';
  if (!file_exists($tpl)) {
    echo '<div class="ia-msg-shell"><p>IA Message: missing template.</p></div>';
    return;
  }
  include $tpl;
}

/**
 * Mounted inside Atrium shell as hidden overlay,
 * opened via the "ia_atrium:chat" intent event.
 */
function ia_message_render_shell_mount(): void {
  $tpl = IA_MESSAGE_PATH . 'includes/templates/modal.php';
  if (!file_exists($tpl)) return;
  include $tpl;
}
