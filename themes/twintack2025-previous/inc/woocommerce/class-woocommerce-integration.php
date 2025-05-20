<?php
/**
 * WooCommerce Integration Class
 * Handles WooCommerce specific functionality
 */
class WooCommerce_Integration {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('after_setup_theme', array($this, 'setup_woocommerce'));
        add_filter('woocommerce_enqueue_styles', array($this, 'dequeue_woocommerce_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_woocommerce_styles'));
    }

    public function setup_woocommerce() {
        add_theme_support('woocommerce');
        add_theme_support('wc-product-gallery-zoom');
        add_theme_support('wc-product-gallery-lightbox');
        add_theme_support('wc-product-gallery-slider');
    }

    public function dequeue_woocommerce_styles($enqueue_styles) {
        unset($enqueue_styles['woocommerce-general']);
        return $enqueue_styles;
    }

    public function enqueue_woocommerce_styles() {
        wp_enqueue_style(
            'twintack2025-woocommerce',
            get_template_directory_uri() . '/css/woocommerce.css',
            array(),
            wp_get_theme()->get('Version')
        );
    }
}