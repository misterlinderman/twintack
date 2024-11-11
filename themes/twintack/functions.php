<?php
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

// Proper way to enqueue parent theme styles and scripts
function flatsome_child_enqueue_styles() {
    // Get parent theme version for cache busting
    $parent_theme = wp_get_theme('flatsome');
    $theme_version = $parent_theme->get('Version');

    // Enqueue Flatsome styles and scripts
    wp_enqueue_style('flatsome-main', get_template_directory_uri() . '/style.css', array(), $theme_version);
    wp_enqueue_style('flatsome-child', get_stylesheet_directory_uri() . '/style.css', array('flatsome-main'));
    
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

    // Optional: Enqueue Flatsome scripts you want to modify
    wp_enqueue_script('flatsome-js', get_template_directory_uri() . '/assets/js/flatsome.js', array('jquery'), $theme_version);
}
add_action('wp_enqueue_scripts', 'flatsome_child_enqueue_styles');

// Category Page Configuration
add_filter('flatsome_custom_css', 'custom_category_css');
function custom_category_css($css) {
    $css .= '
        .category-landing-header { padding: 40px 0; }
        .category-landing-products { padding: 20px 0; }
    ';
    return $css;
}

// Add UX Builder elements for category pages
add_action('ux_builder_setup', 'add_custom_category_elements');
function add_custom_category_elements() {
    add_ux_builder_shortcode('category_products_grid', array(
        'name' => 'Category Products Grid',
        'category' => 'Shop',
        'options' => array(
            'category' => array(
                'type' => 'select',
                'heading' => 'Category',
                'default' => '',
                'options' => get_woocommerce_categories()
            ),
            'products' => array(
                'type' => 'slider',
                'heading' => 'Products',
                'default' => '8',
                'max' => '24',
                'min' => '4',
            ),
            'columns' => array(
                'type' => 'slider',
                'heading' => 'Columns',
                'default' => '4',
                'max' => '6',
                'min' => '1',
            ),
        )
    ));
}

// Helper function to get WooCommerce categories
function get_woocommerce_categories() {
    $categories = get_terms('product_cat', array('hide_empty' => false));
    $options = array();
    foreach ($categories as $cat) {
        $options[$cat->term_id] = $cat->name;
    }
    return $options;
}

// Custom shortcode for category products
add_shortcode('category_products_grid', 'render_category_products_grid');
function render_category_products_grid($atts) {
    $atts = shortcode_atts(array(
        'category' => '',
        'products' => '8',
        'columns' => '4'
    ), $atts);

    ob_start();
    echo do_shortcode('[products category="' . $atts['category'] . '" limit="' . $atts['products'] . '" columns="' . $atts['columns'] . '"]');
    return ob_get_clean();
}

// Add theme support for Flatsome features
add_action('after_setup_theme', 'custom_flatsome_options');
function custom_flatsome_options() {
    add_theme_support('flatsome-advanced');
    add_theme_support('flatsome-sticky-header');
    add_theme_support('wc-product-gallery-zoom');
}