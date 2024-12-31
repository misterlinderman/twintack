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
            'rewrite' => array(
                'slug' => 'marquee-config',
                'with_front' => true
            )
        );
        register_post_type('marquee-config', $args);
    }

    public function register_marquee_fields() {
        if (function_exists('acf_add_local_field_group')) {
            // ACF fields will be registered automatically from JSON
        }
    }

    public static function get_marquee_config($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        $marquee_config = get_field('select_marquee_configuration', $post_id);
        if (!$marquee_config) {
            return false;
        }

        return array(
            'background_image' => get_field('background_image', $marquee_config->ID),
            'embed_shortcode' => get_field('embed_responsively_shortcode', $marquee_config->ID),
            'callout_title' => get_field('callout_content_title', $marquee_config->ID),
            'callout_content' => get_field('callout_content', $marquee_config->ID),
            'callout_links' => get_field('callout_links', $marquee_config->ID)
        );
    }
} 