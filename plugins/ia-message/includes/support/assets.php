<?php
if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
  if (is_admin()) return;
  if (!ia_message_atrium_present()) return;

  // Styles
  wp_enqueue_style('ia-message-base', IA_MESSAGE_URL . 'assets/css/ia-message.base.css', [], IA_MESSAGE_VERSION);
  wp_enqueue_style('ia-message-layout', IA_MESSAGE_URL . 'assets/css/ia-message.layout.css', ['ia-message-base'], IA_MESSAGE_VERSION);
  wp_enqueue_style('ia-message-threads', IA_MESSAGE_URL . 'assets/css/ia-message.threads.css', ['ia-message-layout'], IA_MESSAGE_VERSION);
  wp_enqueue_style('ia-message-chat', IA_MESSAGE_URL . 'assets/css/ia-message.chat.css', ['ia-message-layout'], IA_MESSAGE_VERSION);
  wp_enqueue_style('ia-message-composer', IA_MESSAGE_URL . 'assets/css/ia-message.composer.css', ['ia-message-layout'], IA_MESSAGE_VERSION);

  // Needed for New Chat sheet overlay + suggestions UI
  wp_enqueue_style('ia-message-modal', IA_MESSAGE_URL . 'assets/css/ia-message.modal.css', ['ia-message-base'], IA_MESSAGE_VERSION);

  // Scripts
  wp_enqueue_script('ia-message-boot', IA_MESSAGE_URL . 'assets/js/ia-message.boot.js', [], IA_MESSAGE_VERSION, true);

  wp_localize_script('ia-message-boot', 'IA_MESSAGE', [
    'version'   => IA_MESSAGE_VERSION,
    'panelKey'  => IA_MESSAGE_PANEL_KEY,
    'ajaxUrl'   => admin_url('admin-ajax.php'),
    'nonceBoot' => ia_message_nonce_field('boot'),
  ]);
}, 999);


 /**
  * Inject the panel template so Atrium can mount it at runtime.
  */
add_action('wp_footer', function () {
  if (is_admin()) return;
  if (!ia_message_atrium_present()) return;

  $panel_tpl = IA_MESSAGE_PATH . 'includes/templates/panel.php';
  if (!file_exists($panel_tpl)) return;
?>
  <template id="ia-message-atrium-panel-template">
    <div class="ia-panel" data-panel="<?php echo esc_attr(IA_MESSAGE_PANEL_KEY); ?>" style="display:none;">
      <?php include $panel_tpl; ?>
    </div>
  </template>
<?php
}, 50);
