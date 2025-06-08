<?php
/**
 * Theme Bootstrap Class
 * Handles core theme initialization
 */
class Theme_Bootstrap {
    private static $instance = null;
    private $loaded_modules = [];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_core_modules();
        add_action('after_setup_theme', array($this, 'init_theme'));
    }

    private function load_core_modules() {
        $core_modules = [
            'Theme_Setup',
            'Theme_Customizer',
            'WooCommerce_Integration',
            'ACF_Integration',
            'Theme_Hooks',
            'Header_Configuration',
            'Header_Blocks',
            'Header_Render',
            'TwinTack_Header_SVG_Manager',
            'TwinTack_SVG_Support'
        ];

        foreach ($core_modules as $module) {
            if (class_exists($module)) {
                $this->loaded_modules[$module] = $module::get_instance();
            }
        }
    }

    public function init_theme() {
        // Theme setup
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('html5', [
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'style',
            'script'
        ]);
        
        // Register nav menus
        register_nav_menus([
            'primary' => __('Primary Menu', 'twintack2025'),
            'footer' => __('Footer Menu', 'twintack2025')
        ]);

        // Set content width
        if (!isset($content_width)) {
            $content_width = 1200;
        }
    }
}