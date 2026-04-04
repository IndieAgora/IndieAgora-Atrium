<?php
/**
 * Plugin Name: IA Cache Control
 * Description: Deterministic cache-busting + diagnostics for Atrium surface assets (admin-facing).
 * Version: 0.1.0
 * Author: IndieAgora
 */

if (!defined('ABSPATH')) exit;

define('IA_CACHE_CONTROL_VERSION', '0.1.0');

require_once __DIR__ . '/includes/class-ia-cache-control.php';
require_once __DIR__ . '/includes/admin/class-ia-cache-control-admin.php';

add_action('plugins_loaded', function() {
    IA_Cache_Control::boot();
});
