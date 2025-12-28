<?php
if (!defined('ABSPATH')) exit;

interface IA_Discuss_Module_Interface {
  /** Register hooks/actions. */
  public function boot(): void;

  /**
   * Return AJAX routes as:
   *  action => ['method' => 'handlerMethod', 'public' => true|false]
   */
  public function ajax_routes(): array;
}
