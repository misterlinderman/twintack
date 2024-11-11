<?php
// functions.php
add_action('wp_enqueue_scripts', 'my_theme_enqueue_styles');
function my_theme_enqueue_styles() {
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('parent-style')
    );
}

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

// Other Stuff Suggested

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