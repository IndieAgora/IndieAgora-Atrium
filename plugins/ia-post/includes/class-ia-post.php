<?php
if (!defined('ABSPATH')) exit;

final class IA_Post {

  private static $instance = null;

  public static function instance(): self {
    if (self::$instance === null) self::$instance = new self();
    return self::$instance;
  }

  private function __construct() {
    add_action('wp_enqueue_scripts', [ 'IA_Post_Assets', 'enqueue' ], 1000);

    // Composer UI mounts inside Atrium composer modal.
    add_action('ia_atrium_composer_body', [ $this, 'render_composer_mount' ], 5);
  }

  public function render_composer_mount(): void {
    // Keep the shell placeholder, but our JS will hide it once mounted.
    $tpl = IA_POST_PATH . 'templates/composer-mount.php';
    if (file_exists($tpl)) include $tpl;
  }
}
