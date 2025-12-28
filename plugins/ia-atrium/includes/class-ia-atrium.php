<?php
if (!defined('ABSPATH')) exit;

class IA_Atrium {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // IMPORTANT: your shortcode is [ia-atrium] (dash), keep it canonical.
        add_shortcode('ia-atrium', array($this, 'render_shortcode'));

        // Only enqueue assets on pages that actually contain [ia-atrium]
        add_action('wp_enqueue_scripts', function () {
            if (!is_singular()) return;
            global $post;
            if ($post && has_shortcode($post->post_content, 'ia-atrium')) {
                IA_Atrium_Assets::enqueue();
            }
        });

        /**
         * Core extension points for micro-plugins
         *
         * PHP actions:
         * - do_action('ia_atrium_shell_before');
         * - do_action('ia_atrium_panel_connect');
         * - do_action('ia_atrium_panel_discuss');
         * - do_action('ia_atrium_panel_stream');
         * - do_action('ia_atrium_composer_body');
         * - do_action('ia_atrium_composer_footer');
         * - do_action('ia_atrium_shell_after');
         *
         * PHP filters:
         * - apply_filters('ia_atrium_tabs', $tabs)
         * - apply_filters('ia_atrium_bottom_nav_items', $items)
         *
         * JS events dispatched by the shell:
         * - ia_atrium:tabChanged        detail: { tab }
         * - ia_atrium:openComposer      detail: { defaultDestination }
         * - ia_atrium:composerOpened
         * - ia_atrium:composerClosed
         * - ia_atrium:profile           detail: { userId }
         * - ia_atrium:chat
         * - ia_atrium:notifications
         *
         * JS event the shell listens for (micro-plugins can dispatch):
         * - ia_atrium:navigate          detail: { tab }
         * - ia_atrium:closeComposer
         */
    }

    public function render_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'default' => 'connect', // connect|discuss|stream
        ), $atts);

        $tabs = apply_filters('ia_atrium_tabs', array(
            'connect' => array('label' => 'Connect'),
            'discuss' => array('label' => 'Discuss'),
            'stream'  => array('label' => 'Stream'),
        ));

        $bottom_items = apply_filters('ia_atrium_bottom_nav_items', array(
            'profile' => array('label' => 'Profile', 'icon' => 'dashicons-admin-users'),
            'post'    => array('label' => 'Post', 'icon' => 'dashicons-edit'),
            'chat'    => array('label' => 'Chat', 'icon' => 'dashicons-format-chat'),
            'notify'  => array('label' => 'Notifications', 'icon' => 'dashicons-bell'),
        ));

        $data = array(
            'default_tab'  => sanitize_key($atts['default']),
            'tabs'         => $tabs,
            'bottom_items' => $bottom_items,
        );

        ob_start();
        $template = IA_ATRIUM_PATH . 'templates/atrium-shell.php';
        if (file_exists($template)) {
            include $template;
        } else {
            echo '<div class="ia-atrium-error">IA Atrium template missing.</div>';
        }
        return ob_get_clean();
    }
}
