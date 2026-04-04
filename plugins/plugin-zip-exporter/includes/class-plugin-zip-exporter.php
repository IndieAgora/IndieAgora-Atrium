<?php

if (!defined('ABSPATH')) {
    exit;
}

class Plugin_Zip_Exporter {
    const PAGE_SLUG = 'plugin-zip-exporter';
    const ACTION_SINGLE = 'pze_download_single_plugin';
    const ACTION_ALL = 'pze_download_all_plugins';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_admin_page'));
        add_action('admin_post_' . self::ACTION_SINGLE, array(__CLASS__, 'handle_single_download'));
        add_action('admin_post_' . self::ACTION_ALL, array(__CLASS__, 'handle_all_download'));
    }

    public static function register_admin_page() {
        add_management_page(
            __('Plugin Zip Exporter', 'plugin-zip-exporter'),
            __('Plugin Zip Exporter', 'plugin-zip-exporter'),
            'manage_options',
            self::PAGE_SLUG,
            array(__CLASS__, 'render_admin_page')
        );
    }

    public static function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'plugin-zip-exporter'));
        }

        if (!class_exists('ZipArchive')) {
            echo '<div class="notice notice-error"><p>' .
                esc_html__('ZipArchive is not available on this server. This plugin requires the PHP Zip extension.', 'plugin-zip-exporter') .
                '</p></div>';
        }

        $plugins = self::get_plugin_directories();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Plugin Zip Exporter', 'plugin-zip-exporter'); ?></h1>
            <p><?php echo esc_html__('Download any installed plugin folder as a zip file, or export the full plugins directory.', 'plugin-zip-exporter'); ?></p>

            <p>
                <a class="button button-primary" href="<?php echo esc_url(self::get_all_plugins_download_url()); ?>">
                    <?php echo esc_html__('Download Entire Plugins Folder', 'plugin-zip-exporter'); ?>
                </a>
            </p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Plugin Folder', 'plugin-zip-exporter'); ?></th>
                        <th><?php echo esc_html__('Action', 'plugin-zip-exporter'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($plugins)) : ?>
                    <tr>
                        <td colspan="2"><?php echo esc_html__('No plugin folders were found.', 'plugin-zip-exporter'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($plugins as $plugin_slug => $label) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($label); ?></strong><br>
                                <code><?php echo esc_html($plugin_slug); ?></code>
                            </td>
                            <td>
                                <a class="button" href="<?php echo esc_url(self::get_single_plugin_download_url($plugin_slug)); ?>">
                                    <?php echo esc_html__('Download Zip', 'plugin-zip-exporter'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    protected static function get_plugin_directories() {
        $plugins_dir = WP_PLUGIN_DIR;
        $entries = @scandir($plugins_dir);

        if (!is_array($entries)) {
            return array();
        }

        $plugins = array();

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $plugins_dir . DIRECTORY_SEPARATOR . $entry;

            if (!is_dir($path)) {
                continue;
            }

            $plugins[$entry] = $entry;
        }

        natcasesort($plugins);

        return $plugins;
    }

    protected static function get_single_plugin_download_url($plugin_slug) {
        return wp_nonce_url(
            admin_url('admin-post.php?action=' . self::ACTION_SINGLE . '&plugin=' . rawurlencode($plugin_slug)),
            self::ACTION_SINGLE . ':' . $plugin_slug
        );
    }

    protected static function get_all_plugins_download_url() {
        return wp_nonce_url(
            admin_url('admin-post.php?action=' . self::ACTION_ALL),
            self::ACTION_ALL
        );
    }

    public static function handle_single_download() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'plugin-zip-exporter'));
        }

        $plugin_slug = isset($_GET['plugin']) ? sanitize_text_field(wp_unslash($_GET['plugin'])) : '';
        check_admin_referer(self::ACTION_SINGLE . ':' . $plugin_slug);

        if ($plugin_slug === '') {
            wp_die(esc_html__('No plugin folder was specified.', 'plugin-zip-exporter'));
        }

        $source = self::normalize_and_validate_plugin_path($plugin_slug);

        if (!$source || !is_dir($source)) {
            wp_die(esc_html__('The requested plugin folder could not be found.', 'plugin-zip-exporter'));
        }

        self::stream_zip_download($source, $plugin_slug . '.zip', basename($source));
    }

    public static function handle_all_download() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'plugin-zip-exporter'));
        }

        check_admin_referer(self::ACTION_ALL);

        $plugins_dir = WP_PLUGIN_DIR;

        if (!is_dir($plugins_dir)) {
            wp_die(esc_html__('The plugins directory could not be found.', 'plugin-zip-exporter'));
        }

        $filename = 'plugins-' . gmdate('Y-m-d-H-i-s') . '.zip';
        self::stream_zip_download($plugins_dir, $filename, basename($plugins_dir));
    }

    protected static function normalize_and_validate_plugin_path($plugin_slug) {
        $plugin_slug = trim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $plugin_slug), DIRECTORY_SEPARATOR);

        if ($plugin_slug === '' || strpos($plugin_slug, '..') !== false) {
            return false;
        }

        $base = wp_normalize_path(WP_PLUGIN_DIR);
        $path = wp_normalize_path(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin_slug);

        if (strpos($path, $base) !== 0) {
            return false;
        }

        return $path;
    }

    protected static function stream_zip_download($source_dir, $download_name, $root_name_in_zip) {
        if (!class_exists('ZipArchive')) {
            wp_die(esc_html__('ZipArchive is not available on this server.', 'plugin-zip-exporter'));
        }

        if (!is_readable($source_dir)) {
            wp_die(esc_html__('The source directory is not readable.', 'plugin-zip-exporter'));
        }

        @set_time_limit(0);

        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        $temp = wp_tempnam($download_name);

        if (!$temp) {
            wp_die(esc_html__('Could not create a temporary file for the archive.', 'plugin-zip-exporter'));
        }

        $zip = new ZipArchive();
        $opened = $zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            @unlink($temp);
            wp_die(esc_html__('Could not create the zip archive.', 'plugin-zip-exporter'));
        }

        $source_dir = wp_normalize_path($source_dir);
        $root_name_in_zip = trim(str_replace('..', '', $root_name_in_zip), '/\\');

        if ($root_name_in_zip === '') {
            $root_name_in_zip = 'archive';
        }

        $zip->addEmptyDir($root_name_in_zip);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source_dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $item_path = wp_normalize_path($item->getPathname());
            $relative = ltrim(substr($item_path, strlen($source_dir)), '/');
            $zip_path = $root_name_in_zip . ($relative !== '' ? '/' . $relative : '');

            if ($item->isDir()) {
                $zip->addEmptyDir($zip_path);
            } elseif ($item->isFile() && $item->isReadable()) {
                $zip->addFile($item_path, $zip_path);
            }
        }

        $zip->close();

        if (!file_exists($temp) || !is_readable($temp)) {
            @unlink($temp);
            wp_die(esc_html__('The zip archive could not be prepared for download.', 'plugin-zip-exporter'));
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        @ini_set('zlib.output_compression', 'Off');

        nocache_headers();
        status_header(200);
        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($download_name) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . (string) filesize($temp));
        header('X-Content-Type-Options: nosniff');

        $result = readfile($temp);
        @unlink($temp);

        if ($result === false) {
            wp_die(esc_html__('The zip archive could not be streamed to the browser.', 'plugin-zip-exporter'));
        }

        exit;
    }
}
