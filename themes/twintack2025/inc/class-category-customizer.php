<?php
class TwinTack_Category_Customizer {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('customize_register', array($this, 'register_category_options'));
        add_action('edit_term', array($this, 'save_category_meta'), 10, 3);
    }
    
    public function register_category_options($wp_customize) {
        // Add section for category customization
        $wp_customize->add_section('category_customization', array(
            'title' => __('Product Category Pages', 'twintack2025'),
            'priority' => 30,
        ));
        
        // Add settings for marquee slides
        $wp_customize->add_setting('category_marquee_slides', array(
            'default' => array(),
            'transport' => 'refresh',
            'sanitize_callback' => array($this, 'sanitize_marquee_slides')
        ));
        
        // Add control for marquee slides
        $wp_customize->add_control(new WP_Customize_Control(
            $wp_customize,
            'category_marquee_slides',
            array(
                'label' => __('Category Marquee Slides', 'twintack2025'),
                'section' => 'category_customization',
                'settings' => 'category_marquee_slides',
                'type' => 'repeater',
                'fields' => array(
                    'image' => array(
                        'type' => 'image',
                        'label' => __('Slide Image', 'twintack2025')
                    ),
                    'title' => array(
                        'type' => 'text',
                        'label' => __('Slide Title', 'twintack2025')
                    ),
                    'description' => array(
                        'type' => 'textarea',
                        'label' => __('Slide Description', 'twintack2025')
                    ),
                    'link' => array(
                        'type' => 'url',
                        'label' => __('Slide Link', 'twintack2025')
                    ),
                    'button_text' => array(
                        'type' => 'text',
                        'label' => __('Button Text', 'twintack2025')
                    )
                )
            )
        ));
    }
    
    public function sanitize_marquee_slides($input) {
        if (!is_array($input)) {
            return array();
        }
        
        $output = array();
        foreach ($input as $slide) {
            if (empty($slide)) {
                continue;
            }
            
            $output[] = array(
                'image' => esc_url_raw($slide['image']),
                'title' => sanitize_text_field($slide['title']),
                'description' => wp_kses_post($slide['description']),
                'link' => esc_url_raw($slide['link']),
                'button_text' => sanitize_text_field($slide['button_text'])
            );
        }
        
        return $output;
    }
    
    public function save_category_meta($term_id, $tt_id, $taxonomy) {
        if ($taxonomy !== 'product_cat') {
            return;
        }
        
        $slides = get_theme_mod('category_marquee_slides');
        if (!empty($slides)) {
            update_term_meta($term_id, 'category_marquee_slides', $slides);
        }
    }
}

// Initialize the class
add_action('init', array('TwinTack_Category_Customizer', 'get_instance')); 