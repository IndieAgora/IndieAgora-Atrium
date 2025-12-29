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
