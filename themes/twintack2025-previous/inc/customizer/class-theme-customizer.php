<?php
/**
 * Theme Customizer Class
 * Handles theme customization options
 */
class Theme_Customizer {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('customize_register', array($this, 'register_customizer_settings'));
    }

    public function register_customizer_settings($wp_customize) {
        // Site Identity Section
        $wp_customize->add_setting('site_logo_light', array(
            'default' => '',
            'sanitize_callback' => 'esc_url_raw',
        ));

        $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'site_logo_light', array(
            'label' => __('Light Logo', 'twintack2025'),
            'section' => 'title_tagline',
            'settings' => 'site_logo_light',
        )));

        // Colors Section
        $wp_customize->add_setting('primary_color', array(
            'default' => '#007bff',
            'sanitize_callback' => 'sanitize_hex_color',
        ));

        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'primary_color', array(
            'label' => __('Primary Color', 'twintack2025'),
            'section' => 'colors',
            'settings' => 'primary_color',
        )));
    }
}