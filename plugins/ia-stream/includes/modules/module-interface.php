<?php
if (!defined('ABSPATH')) exit;

/**
 * IA Stream Module Interface
 *
 * All Stream modules must implement this interface.
 * Keeps modules bounded and non-magical.
 */
interface IA_Stream_Module_Interface {
  public static function boot(): void;
}
