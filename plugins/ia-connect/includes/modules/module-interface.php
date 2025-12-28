<?php
if (!defined('ABSPATH')) exit;

interface ia_connect_module_interface {
  public function boot(): void;

  /**
   * [
   *   'ia_connect_action' => ['method' => 'handler_method', 'public' => true|false]
   * ]
   */
  public function ajax_routes(): array;
}
