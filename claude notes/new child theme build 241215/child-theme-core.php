// style.css
/*
Theme Name: Flatsome Child
Description: This is a child theme for Flatsome Theme
Author: Your Name
Template: flatsome
Version: 1.0
*/

// functions.php
<?php
// Enqueue parent and child theme styles
function flatsome_child_enqueue_styles() {
    // Parent theme CSS
    wp_enqueue_style('flatsome-main', get_template_directory_uri() . '/style.css');
    
    // Child theme CSS
    wp_enqueue_style('flatsome-child', 
        get_stylesheet_directory_uri() . '/style.css',
        array('flatsome-main'),
        wp_get_theme()->get('Version')
    );
    
    // Custom CSS
    wp_enqueue_style('custom-styles',
        get_stylesheet_directory_uri() . '/assets/css/custom.css',
        array('flatsome-child'),
        filemtime(get_stylesheet_directory() . '/assets/css/custom.css')
    );
    
    // Custom JS
    wp_enqueue_script('custom-scripts',
        get_stylesheet_directory_uri() . '/assets/js/custom.js',
        array('jquery'),
        filemtime(get_stylesheet_directory() . '/assets/js/custom.js'),
        true
    );
}
add_action('wp_enqueue_scripts', 'flatsome_child_enqueue_styles');

// Load additional PHP files
$includes = [
    '/inc/custom-post-types.php',
    '/inc/shortcodes.php',
    '/inc/woocommerce.php',
    '/inc/helpers.php'
];

foreach ($includes as $file) {
    if (file_exists(get_stylesheet_directory() . $file)) {
        require_once get_stylesheet_directory() . $file;
    }
}

// inc/woocommerce.php
<?php
// WooCommerce specific customizations
add_action('after_setup_theme', 'your_theme_woocommerce_setup');
function your_theme_woocommerce_setup() {
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
}

// inc/shortcodes.php
<?php
// Custom shortcodes
function custom_category_products($atts) {
    $atts = shortcode_atts(array(
        'category' => '',
        'limit' => '12',
        'columns' => '4'
    ), $atts);
    
    return do_shortcode('[products category="' . $atts['category'] . '" limit="' . $atts['limit'] . '" columns="' . $atts['columns'] . '"]');
}
add_shortcode('custom_category_products', 'custom_category_products');

// assets/css/custom.css
/* Custom styles */
.custom-category-header {
    padding: 40px 0;
    background: #f8f9fa;
}

.custom-product-grid {
    margin-top: 30px;
}

// assets/js/custom.js
jQuery(document).ready(function($) {
    // Custom JavaScript
});
