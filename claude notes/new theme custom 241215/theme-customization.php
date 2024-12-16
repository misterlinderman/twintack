<?php
class TwinTack_Theme_Customizer {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('customize_register', array($this, 'register_customizer_options'));
        add_action('wp_head', array($this, 'output_custom_styles'));
    }

    public function register_customizer_options($wp_customize) {
        // Baseball Category Settings
        $wp_customize->add_section('baseball_settings', array(
            'title' => 'Baseball Category Settings',
            'priority' => 30
        ));

        $wp_customize->add_setting('baseball_primary_color', array(
            'default' => '#002D72',
            'sanitize_callback' => 'sanitize_hex_color'
        ));

        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'baseball_primary_color', array(
            'label' => 'Primary Color',
            'section' => 'baseball_settings'
        )));

        // Fishing Category Settings
        $wp_customize->add_section('fishing_settings', array(
            'title' => 'Fishing Category Settings',
            'priority' => 31
        ));

        $wp_customize->add_setting('fishing_primary_color', array(
            'default' => '#00529B',
            'sanitize_callback' => 'sanitize_hex_color'
        ));

        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'fishing_primary_color', array(
            'label' => 'Primary Color',
            'section' => 'fishing_settings'
        )));

        // Product Display Settings
        $wp_customize->add_section('product_display', array(
            'title' => 'Product Display Settings',
            'priority' => 32
        ));

        $wp_customize->add_setting('products_per_row', array(
            'default' => '4',
            'sanitize_callback' => 'absint'
        ));

        $wp_customize->add_control('products_per_row', array(
            'label' => 'Products per row',
            'section' => 'product_display',
            'type' => 'select',
            'choices' => array(
                '2' => '2',
                '3' => '3',
                '4' => '4',
                '5' => '5'
            )
        ));
    }

    public function output_custom_styles() {
        $baseball_color = get_theme_mod('baseball_primary_color', '#002D72');
        $fishing_color = get_theme_mod('fishing_primary_color', '#00529B');
        $products_per_row = get_theme_mod('products_per_row', '4');
        ?>
        <style>
            .category-hero.baseball {
                background-color: <?php echo esc_attr($baseball_color); ?>;
            }
            
            .category-hero.fishing {
                background-color: <?php echo esc_attr($fishing_color); ?>;
            }
            
            @media (min-width: 768px) {
                .product-grid {
                    grid-template-columns: repeat(<?php echo esc_attr($products_per_row); ?>, 1fr);
                }
            }
        </style>
        <?php
    }
}

// Initialize the customizer
add_action('init', array('TwinTack_Theme_Customizer', 'get_instance'));
