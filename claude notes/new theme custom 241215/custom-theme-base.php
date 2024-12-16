<?php
/*
Theme Name: TwinTack Custom Theme
Description: Custom WooCommerce theme for TwinTack
Author: Your Name
Version: 1.0
*/

// Theme Setup
function twintack_theme_setup() {
    add_theme_support('woocommerce');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ));
    
    // Register nav menus
    register_nav_menus(array(
        'primary' => 'Primary Menu',
        'baseball' => 'Baseball Menu',
        'fishing' => 'Fishing Menu'
    ));
}
add_action('after_setup_theme', 'twintack_theme_setup');

// Enqueue scripts and styles
function twintack_scripts() {
    wp_enqueue_style(
        'twintack-style',
        get_stylesheet_uri(),
        array(),
        filemtime(get_stylesheet_directory() . '/style.css')
    );
    
    wp_enqueue_script(
        'twintack-scripts',
        get_template_directory_uri() . '/assets/js/main.js',
        array('jquery'),
        filemtime(get_template_directory() . '/assets/js/main.js'),
        true
    );
}
add_action('wp_enqueue_scripts', 'twintack_scripts');

// Custom header function for product lines
function get_product_line_header() {
    $category = get_queried_object();
    if (is_product_category()) {
        if (strpos(strtolower($category->name), 'baseball') !== false) {
            get_template_part('template-parts/header/header', 'baseball');
        } elseif (strpos(strtolower($category->name), 'fishing') !== false) {
            get_template_part('template-parts/header/header', 'fishing');
        } else {
            get_template_part('template-parts/header/header', 'default');
        }
    }
}

// Template hierarchy modifications
function twintack_template_hierarchy($template) {
    if (is_product_category()) {
        $category = get_queried_object();
        if (strpos(strtolower($category->name), 'baseball') !== false) {
            return locate_template('templates/category-baseball.php');
        } elseif (strpos(strtolower($category->name), 'fishing') !== false) {
            return locate_template('templates/category-fishing.php');
        }
    }
    return $template;
}
add_filter('template_include', 'twintack_template_hierarchy');
