<?php
/**
 * Header Configuration Class
 */
class Header_Configuration {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_header_post_type'));
    }

    public function register_header_post_type() {
        $args = array(
            'public' => true,
            'label'  => 'Header Configurations',
            'supports' => array('title', 'custom-fields'),
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-admin-post',
            'rewrite' => array(
                'slug' => 'header-configuration',
                'with_front' => true
            )
        );
        register_post_type('header-configuration', $args);
    }

    public static function get_header_config($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        $header_config = get_field('select_header_configuration', $post_id);
        if (!$header_config) {
            return false;
        }

        return array(
            'background_image' => get_field('background_image', $header_config->ID),
            'embed_shortcode' => get_field('embed_responsively_shortcode', $header_config->ID),
            'callout_title' => get_field('callout_content_title', $header_config->ID),
            'callout_content' => get_field('callout_content', $header_config->ID),
            'callout_links' => get_field('callout_links', $header_config->ID)
        );
    }
}