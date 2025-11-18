<?php
/**
 * Theme Setup Class
 * Handles core theme setup functionality
 */
class Theme_Setup {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('after_setup_theme', array($this, 'setup_theme'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function setup_theme() {
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('html5', array(
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
        ));
        add_theme_support('customize-selective-refresh-widgets');
        add_theme_support('woocommerce');
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'twintack2025-style',
            get_stylesheet_uri(),
            array(),
            wp_get_theme()->get('Version')
        );

        wp_enqueue_script(
            'twintack2025-navigation',
            get_template_directory_uri() . '/js/navigation.js',
            array(),
            wp_get_theme()->get('Version'),
            true
        );

        // Bootstrap
        wp_enqueue_style('bootstrap', get_template_directory_uri() . '/node_modules/bootstrap/dist/css/bootstrap.min.css');
        wp_enqueue_script('bootstrap', get_template_directory_uri() . '/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js', array('jquery'));
        
        // Animation libraries
        wp_enqueue_style('animate-css', get_template_directory_uri() . '/node_modules/animate.css/animate.min.css');
        wp_enqueue_style('aos', get_template_directory_uri() . '/node_modules/aos/dist/aos.css');
        wp_enqueue_script('aos', get_template_directory_uri() . '/node_modules/aos/dist/aos.js');
    }
}