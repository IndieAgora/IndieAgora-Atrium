<?php
if (!defined('ABSPATH')) exit;

class IA_Atrium_Assets {

    public static function enqueue() {
        $ver = defined('IA_ATRIUM_VERSION') ? IA_ATRIUM_VERSION : time();

        wp_enqueue_style(
            'ia-atrium',
            IA_ATRIUM_URL . 'assets/css/atrium.css',
            array(),
            $ver
        );

        wp_enqueue_script(
            'ia-atrium',
            IA_ATRIUM_URL . 'assets/js/atrium.js',
            array(),
            $ver,
            true
        );

        // Dashicons provide the navbar icons.
        wp_enqueue_style('dashicons');

        $current_url = (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');

        wp_localize_script('ia-atrium', 'IA_ATRIUM', array(
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'isLoggedIn'   => is_user_logged_in(),
            'userId'       => get_current_user_id(),
            'loginPostUrl' => site_url('wp-login.php', 'login_post'),
            'registerUrl'  => wp_registration_url(),
            'lostPassUrl'  => wp_lostpassword_url(),
            'logoutUrl'    => wp_logout_url($current_url),
            'currentUrl'   => $current_url,
        ));
    }
}
