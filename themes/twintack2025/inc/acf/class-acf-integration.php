<?php
/**
 * ACF Integration Class
 * Handles Advanced Custom Fields integration
 */
class ACF_Integration {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('acf/init', array($this, 'register_blocks'));
        add_filter('acf/settings/save_json', array($this, 'set_acf_json_save_path'));
        add_filter('acf/settings/load_json', array($this, 'add_acf_json_load_path'));
    }

    public function register_blocks() {
        if (function_exists('acf_register_block_type')) {
            acf_register_block_type(array(
                'name' => 'hero',
                'title' => __('Hero', 'twintack2025'),
                'description' => __('A custom hero block.', 'twintack2025'),
                'render_template' => 'template-parts/blocks/hero/hero.php',
                'category' => 'formatting',
                'icon' => 'admin-comments',
                'keywords' => array('hero', 'banner'),
            ));
        }
    }

    public function set_acf_json_save_path() {
        return get_stylesheet_directory() . '/inc/acf/json';
    }

    public function add_acf_json_load_path($paths) {
        $paths[] = get_stylesheet_directory() . '/inc/acf/json';
        return $paths;
    }
}