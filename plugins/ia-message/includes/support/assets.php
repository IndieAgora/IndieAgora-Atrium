<?php
if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
  if (is_admin()) return;
  if (!ia_message_atrium_present()) return;

  wp_enqueue_style('ia-message-base', IA_MESSAGE_URL . 'assets/css/ia-message.base.css', [], IA_MESSAGE_VERSION);
  wp_enqueue_style('ia-message-layout', IA_MESSAGE_URL . 'assets/css/ia-message.layout.css', ['ia-message-base'], IA_MESSAGE_VERSION);

  // add these:
  wp_enqueue_style('ia-message-modal', IA_MESSAGE_URL . 'assets/css/ia-message.modal.css', ['ia-message-layout'], IA_MESSAGE_VERSION);
  wp_enqueue_style('ia-message-threads', IA_MESSAGE_URL . 'assets/css/ia-message.threads.css', ['ia-message-layout'], IA_MESSAGE_VERSION);
  wp_enqueue_style('ia-message-chat', IA_MESSAGE_URL . 'assets/css/ia-message.chat.css', ['ia-message-layout'], IA_MESSAGE_VERSION);

  wp_enqueue_script('ia-message-boot', IA_MESSAGE_URL . 'assets/js/ia-message.boot.js', [], IA_MESSAGE_VERSION, true);

  wp_localize_script('ia-message-boot', 'IA_MESSAGE', [
    'version'   => IA_MESSAGE_VERSION,
    'panelKey'  => IA_MESSAGE_PANEL_KEY,
    'ajaxUrl'   => admin_url('admin-ajax.php'),
    'nonceBoot' => ia_message_nonce_field('boot'),
  ]);
});
