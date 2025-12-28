<?php
/**
 * IA Discuss — AJAX Router/Dispatcher
 *
 * File: wp-content/plugins/ia-discuss/includes/support/class-ia-discuss-support-ajax.php
 *
 * Routes are declared by modules implementing IA_Discuss_Module_Interface::ajax_routes()
 * and dispatched through WP admin-ajax.php using wp_ajax_{action} / wp_ajax_nopriv_{action}.
 */
if (!defined('ABSPATH')) exit;

final class IA_Discuss_Support_Ajax {

  /** @var array<string, array{module:object, method:string, public:bool}> */
  private $routes = [];

  /**
   * Build the route map from modules and register WP AJAX hooks.
   *
   * @param array $modules
   * @param IA_Discuss_Support_Security $security
   */
  public function boot(array $modules, IA_Discuss_Support_Security $security): void {
    $this->routes = [];

    foreach ($modules as $m) {
      if (!($m instanceof IA_Discuss_Module_Interface)) continue;

      foreach ((array) $m->ajax_routes() as $action => $def) {
        $action = (string) $action;
        if ($action === '') continue;

        $method = isset($def['method']) ? (string) $def['method'] : '';
        if ($method === '' || !method_exists($m, $method)) continue;

        $is_public = isset($def['public']) ? (bool) $def['public'] : false;

        $this->routes[$action] = [
          'module' => $m,
          'method' => $method,
          'public' => $is_public,
        ];
      }
    }

    // Register WP AJAX hooks for every declared route.
    foreach ($this->routes as $action => $_def) {

      add_action('wp_ajax_' . $action, function () use ($action, $security) {
        $this->dispatch($action, $security, false);
      });

      add_action('wp_ajax_nopriv_' . $action, function () use ($action, $security) {
        $this->dispatch($action, $security, true);
      });
    }
  }

  /**
   * Dispatch an AJAX action to the owning module.
   *
   * @param string $action
   * @param IA_Discuss_Support_Security $security
   * @param bool $is_nopriv
   */
  private function dispatch(string $action, IA_Discuss_Support_Security $security, bool $is_nopriv): void {
    // Always enforce nonce for now.
   $security->verify_nonce_from_post('ia_discuss', 'nonce');

    if (!$security->can_run_ajax()) {
      ia_discuss_json_err('Not allowed', 403);
    }

    if (!isset($this->routes[$action])) {
      ia_discuss_json_err('Unknown route: ' . $action, 404);
    }

    $def = $this->routes[$action];

    // If route is not public, block nopriv calls.
    if ($is_nopriv && empty($def['public'])) {
      ia_discuss_json_err('Login required', 401);
    }

    try {
      $module = $def['module'];
      $method = $def['method'];
      $module->{$method}();
    } catch (Throwable $e) {
      ia_discuss_json_err('AJAX error: ' . $e->getMessage(), 500);
    }
  }
}

/**
 * Singleton accessor
 */
function ia_discuss_ajax(): IA_Discuss_Support_Ajax {
  static $i = null;
  if (!$i) $i = new IA_Discuss_Support_Ajax();
  return $i;
}
