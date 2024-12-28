<?php
/**
 * Marquee Configuration Class
 *
 * @package twintack2025
 */

class TwinTack_Marquee_Configuration {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_marquee_post_type'));
        add_action('acf/init', array($this, 'register_marquee_fields'));
    }

    public function register_marquee_post_type() {
        $args = array(
            'public' => true,
            'label'  => 'Marquee Configurations',
            'supports' => array('title', 'custom-fields'),
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-admin-post',
        );
        register_post_type('marquee-config', $args);
    }

    public function register_marquee_fields() {
        if (function_exists('acf_add_local_field_group')) {
            // ACF fields will be registered automatically from JSON
        }
    }
} 