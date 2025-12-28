<?php
if (!defined('ABSPATH')) exit;

final class ia_connect_support_ajax {

  /** @var array<string, array{callable: callable, public: bool}> */
  private static array $routes = [];

  /**
   * @param array<int, ia_connect_module_interface> $modules
   */
  public static function boot(array $modules): void {
    self::$routes = [];

    foreach ($modules as $m) {
      $defs = $m->ajax_routes();
      if (!is_array($defs)) continue;

      foreach ($defs as $action => $def) {
        $action = is_string($action) ? sanitize_key($action) : '';
        if (!$action || !is_array($def)) continue;

        $method = isset($def['method']) ? (string) $def['method'] : '';
        $public = !empty($def['public']);
        if (!$method || !method_exists($m, $method)) continue;

        self::$routes[$action] = [
          'callable' => [$m, $method],
          'public'   => $public,
        ];
      }
    }

    foreach (self::$routes as $action => $route) {
      add_action("wp_ajax_{$action}", [__CLASS__, 'dispatch']);
      if (!empty($route['public'])) {
        add_action("wp_ajax_nopriv_{$action}", [__CLASS__, 'dispatch']);
      }
    }
  }

  public static function dispatch(): void {
    $action = isset($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';
    if (!$action || empty(self::$routes[$action])) {
      wp_send_json_error(['message' => 'Unknown action.'], 400);
    }
    call_user_func(self::$routes[$action]['callable']);
  }
}
