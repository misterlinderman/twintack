<?php
/**
 * Header Blocks Class
 */
class TwinTack_Header_Blocks {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('acf/init', array($this, 'register_header_blocks'));
    }

    public function register_header_blocks() {
        if (function_exists('acf_register_block_type')) {
            acf_register_block_type(array(
                'name' => 'flexible-header',
                'title' => __('Flexible Header', 'twintack2025'),
                'description' => __('A customizable header block with multiple layouts.', 'twintack2025'),
                'render_template' => 'template-parts/blocks/header/flexible-header.php',
                'category' => 'layout',
                'icon' => 'align-wide',
                'keywords' => array('header', 'hero', 'banner'),
                'supports' => array(
                    'align' => array('wide', 'full'),
                    'mode' => true,
                    'jsx' => true
                ),
            ));
        }
    }
}

// Initialize the class
add_action('init', array('TwinTack_Header_Blocks', 'get_instance'));