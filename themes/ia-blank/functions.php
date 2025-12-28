<?php
/**
 * IA Blank theme setup
 */

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('editor-styles');
});

/**
 * Disable block theme layout constraints
 */
add_filter('should_load_separate_core_block_assets', '__return_true');
