<?php
/**
 * Header SVG Manager Class
 */
class TwinTack_Header_SVG_Manager {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('twintack_before_page_content', array($this, 'render_svg_header'));
    }

    public function enqueue_assets() {
        if ($this->should_load_svg_header()) {
            wp_enqueue_style(
                'twintack-svg-header',
                get_template_directory_uri() . '/css/components/_svg-header.css',
                array(),
                wp_get_theme()->get('Version')
            );

            wp_enqueue_script(
                'twintack-svg-header',
                get_template_directory_uri() . '/js/svg-header.js',
                array('jquery'),
                wp_get_theme()->get('Version'),
                true
            );
        }
    }

    private function should_load_svg_header() {
        return is_page() && get_field('enable_custom_header');
    }

    public function render_svg_header() {
        if (!$this->should_load_svg_header()) {
            return;
        }

        get_template_part('template-parts/header/svg-layers');
    }
} 