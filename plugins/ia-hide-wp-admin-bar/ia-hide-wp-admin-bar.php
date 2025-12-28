<?php
/**
 * Plugin Name: IA Hide WP Admin Bar (Atrium Only)
 * Description: Hides the WordPress admin bar on pages that contain the [ia-atrium] shortcode (front-end only).
 * Version: 1.0.0
 * Author: IndieAgora
 */

if (!defined('ABSPATH')) exit;

final class IA_Hide_WP_Admin_Bar_Atrium {

    public static function boot(): void {
        // Disable admin bar output early (front-end only) when we're on an Atrium page.
        add_action('wp', [__CLASS__, 'maybe_disable_admin_bar'], 1);

        // Safety: hide any leftover bar + remove the theme "bump" CSS on Atrium pages.
        add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_hide_bar_css'], 999);
    }

    public static function maybe_disable_admin_bar(): void {
        if (is_admin()) return;
        if (!self::is_atrium_page()) return;

        // Primary method (stops WP from rendering it)
        add_filter('show_admin_bar', '__return_false', 100);
    }

    public static function maybe_hide_bar_css(): void {
        if (is_admin()) return;
        if (!self::is_atrium_page()) return;

        // Remove the front-end "bump" (margin-top) that WP injects when the bar is shown.
        remove_action('wp_head', '_admin_bar_bump_cb');

        // Extra safety: if anything still outputs, force-hide it visually.
        $css = '
            #wpadminbar { display:none !important; }
            html { margin-top: 0 !important; }
            body.admin-bar { margin-top: 0 !important; }
        ';
        wp_register_style('ia-hide-adminbar', false);
        wp_enqueue_style('ia-hide-adminbar');
        wp_add_inline_style('ia-hide-adminbar', $css);
    }

    private static function is_atrium_page(): bool {
        // Atrium pages are singular pages/posts where content includes [ia-atrium]
        if (!is_singular()) return false;

        global $post;
        if (!$post) return false;

        $content = $post->post_content ?? '';
        return is_string($content) && has_shortcode($content, 'ia-atrium');
    }
}

add_action('plugins_loaded', ['IA_Hide_WP_Admin_Bar_Atrium', 'boot']);
