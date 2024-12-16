// flatsome-child/style.css
/*
Theme Name: Flatsome Child
Description: This is a child theme for Flatsome Theme
Template: flatsome    /* This is the crucial line - it tells WordPress this is a child of Flatsome */
Author: Your Name
Version: 1.0
*/

// flatsome-child/functions.php
<?php
// Proper way to enqueue parent theme styles and scripts
function flatsome_child_enqueue_styles() {
    // Get parent theme version for cache busting
    $parent_theme = wp_get_theme('flatsome');
    $theme_version = $parent_theme->get('Version');

    // Enqueue Flatsome styles and scripts
    wp_enqueue_style('flatsome-main', get_template_directory_uri() . '/style.css', array(), $theme_version);
    wp_enqueue_style('flatsome-child', get_stylesheet_directory_uri() . '/style.css', array('flatsome-main'));

    // Optional: Enqueue Flatsome scripts you want to modify
    wp_enqueue_script('flatsome-js', get_template_directory_uri() . '/assets/js/flatsome.js', array('jquery'), $theme_version);
}
add_action('wp_enqueue_scripts', 'flatsome_child_enqueue_styles');

// Access Flatsome's UX Builder
function custom_ux_builder_elements() {
    // Make sure UX Builder is active
    if (function_exists('add_ux_builder_shortcode')) {
        // Add custom UX Builder element
        add_ux_builder_shortcode('custom_element', array(
            'name' => 'Custom Element',
            'category' => 'Content',
            'options' => array(
                'title' => array(
                    'type' => 'textfield',
                    'heading' => 'Title'
                )
            )
        ));
    }
}
add_action('ux_builder_setup', 'custom_ux_builder_elements');

// Access Flatsome's theme options
function custom_flatsome_options() {
    // Get Flatsome options
    $flatsome_options = get_theme_mod('header_bg');
    
    // Add your own theme options
    add_theme_support('flatsome-advanced');
    add_theme_support('flatsome-sticky-header');
    add_theme_support('wc-product-gallery-zoom');
}
add_action('after_setup_theme', 'custom_flatsome_options');

// Extend Flatsome's shortcodes
function custom_flatsome_shortcodes() {
    if (function_exists('add_ux_builder_shortcode')) {
        // Modify existing Flatsome shortcode
        add_filter('shortcode_atts_ux_banner', 'custom_banner_atts', 10, 3);
    }
}
add_action('init', 'custom_flatsome_shortcodes');

// Access Flatsome's helper functions
function custom_flatsome_helpers() {
    // Example: Use Flatsome's get_header_classes() function
    $header_classes = get_header_classes();
    
    // Example: Use Flatsome's flatsome_option() function
    $header_height = flatsome_option('header_height');
}

// Override Flatsome templates
function custom_flatsome_templates() {
    // Your template modifications here
    // Templates in child theme automatically override parent theme
}
