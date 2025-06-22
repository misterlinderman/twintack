<?php
/**
 * Header Configuration Class
 * Handles the logic for header configurations
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
        add_action('acf/init', array($this, 'register_header_fields'));
    }

    public function register_header_post_type() {
        $args = array(
            'public' => true,
            'label'  => 'Header Configurations',
            'supports' => array('title', 'custom-fields'),
            'show_in_rest' => true,
        );
        register_post_type('header-configuration', $args);
    }

    public function get_header_config($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        $header_config = get_field('select_header_configuration', $post_id);
        if (!$header_config) {
            return false;
        }

        return array(
            'media_type' => get_field('image_or_video', $header_config->ID),
            'background_image' => get_field('background_image', $header_config->ID),
            'video_embed' => get_field('embed_responsively_shortcode', $header_config->ID),
            'callout_enabled' => get_field('header_callout_content', $header_config->ID) === 'on',
            'callout_title' => get_field('callout_content_title', $header_config->ID),
            'callout_content' => get_field('callout_content', $header_config->ID),
            'links_enabled' => get_field('header_callout_links', $header_config->ID) === 'on',
            'links' => get_field('callout_links', $header_config->ID)
        );
    }
}