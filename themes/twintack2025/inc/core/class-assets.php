<?php
/**
 * Theme Assets Class
 * Handles all asset loading
 */
class Theme_Assets {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    public function enqueue_frontend_assets() {
        // Main styles
        wp_enqueue_style(
            'twintack2025-style',
            get_stylesheet_uri(),
            array(),
            TWINTACK_VERSION
        );

        // Component styles
        wp_enqueue_style(
            'twintack2025-components',
            get_template_directory_uri() . '/css/components.css',
            array(),
            TWINTACK_VERSION
        );

        // Scripts
        wp_enqueue_script(
            'twintack2025-navigation',
            get_template_directory_uri() . '/js/navigation.js',
            array(),
            TWINTACK_VERSION,
            true
        );

        if (is_singular() && comments_open() && get_option('thread_comments')) {
            wp_enqueue_script('comment-reply');
        }
    }

    public function enqueue_admin_assets() {
        wp_enqueue_style(
            'twintack2025-admin',
            get_template_directory_uri() . '/css/admin.css',
            array(),
            TWINTACK_VERSION
        );
    }
}