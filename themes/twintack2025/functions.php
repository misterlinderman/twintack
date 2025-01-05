<?php
/**
 * twintack2025 functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package twintack2025
 */

if ( ! defined( '_S_VERSION' ) ) {
	// Replace the version number of the theme on each release.
	define( '_S_VERSION', '1.0.0' );
}

// Include class loader first
require_once get_template_directory() . '/inc/class-loader.php';

// Include necessary files early
require_once get_template_directory() . '/inc/custom-header.php';
require_once get_template_directory() . '/inc/template-tags.php';
require_once get_template_directory() . '/inc/template-functions.php';
require_once get_template_directory() . '/inc/customizer.php';
require_once get_template_directory() . '/inc/class-twintack-role-pricing.php';
require_once get_template_directory() . '/inc/class-twintack-custom-products.php';
require_once get_template_directory() . '/inc/class-twintack-product-forms.php';
require_once get_template_directory() . '/inc/class-category-customizer.php';
require_once get_template_directory() . '/inc/header/class-header-configuration.php';//remove once marquee is working
require_once get_template_directory() . '/inc/marquee/class-marquee-configuration.php';
require_once get_template_directory() . '/inc/team/class-team-member.php';

/**
 * Sets up theme defaults and registers support for various WordPress features.
 */
function twintack2025_setup() {
	load_theme_textdomain( 'twintack2025', get_template_directory() . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	
	// HTML5 support
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form', 
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	// Custom background
	add_theme_support(
		'custom-background',
		apply_filters(
			'twintack2025_custom_background_args',
			array(
				'default-color' => 'ffffff',
				'default-image' => '',
			)
		)
	);

	// Custom logo
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 250,
			'width'       => 250,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);

	// WooCommerce support
	add_theme_support('woocommerce');
	add_theme_support('wc-product-gallery-zoom');
	add_theme_support('wc-product-gallery-lightbox');
	add_theme_support('wc-product-gallery-slider');
	
	// Register nav menus
	register_nav_menus(array(
		'primary' => esc_html__('Primary Menu', 'twintack2025'),
		'baseball' => esc_html__('Baseball Menu', 'twintack2025'),
		'fishing' => esc_html__('Fishing Menu', 'twintack2025')
	));

	// Custom image sizes
	add_image_size('product-variant-thumb', 300, 300, true);
}
add_action( 'after_setup_theme', 'twintack2025_setup' );

/**
 * Set the content width
 */
function twintack2025_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'twintack2025_content_width', 640 );
}
add_action( 'after_setup_theme', 'twintack2025_content_width', 0 );

/**
 * Register widget area
 */
function twintack2025_widgets_init() {
	register_sidebar(
		array(
			'name'          => esc_html__( 'Sidebar', 'twintack2025' ),
			'id'            => 'sidebar-1',
			'description'   => esc_html__( 'Add widgets here.', 'twintack2025' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'twintack2025_widgets_init' );

/**
 * Enqueue scripts and styles
 */
function twintack2025_scripts() {
	// Bootstrap
	wp_enqueue_style(
		'bootstrap',
		'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
		array(),
		'5.3.2'
	);

	wp_enqueue_script(
		'bootstrap-bundle',
		'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js',
		array('jquery'),
		'5.3.2',
		true
	);

	// Theme styles
	wp_enqueue_style(
		'twintack2025-main',
		get_template_directory_uri() . '/css/main.css',
		array(),
		filemtime(get_template_directory() . '/css/main.css')
	);
	
	wp_enqueue_style( 'twintack2025-style', get_stylesheet_uri(), array('bootstrap'), _S_VERSION );
	wp_style_add_data( 'twintack2025-style', 'rtl', 'replace' );

	// Theme scripts
	wp_enqueue_script( 'twintack2025-navigation', get_template_directory_uri() . '/js/navigation.js', array(), _S_VERSION, true );
	wp_enqueue_script(
		'twintack2025-header',
		get_template_directory_uri() . '/js/header.js',
		array(),
		filemtime(get_template_directory() . '/js/header.js'),
		true
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'twintack2025_scripts' );

/**
 * Load Jetpack compatibility file
 */
if ( defined( 'JETPACK__VERSION' ) ) {
	require_once get_template_directory() . '/inc/jetpack.php';
}

/**
 * Add category template support
 */
function twintack_category_template_loader($template) {
    if (is_product_category()) {
        $category = get_queried_object();
        $template_name = '';
        
        // Check category and all parent categories for baseball/fishing
        $term_list = get_term_parents_list($category->term_id, 'product_cat', array('format' => 'slug'));
        
        if (strpos($term_list, 'baseball') !== false) {
            $template_name = 'category-baseball.php';
        } elseif (strpos($term_list, 'fishing') !== false) {
            $template_name = 'category-fishing.php';
        }
        
        if ($template_name) {
            $new_template = locate_template(array(
                'templates/' . $template_name,
                $template_name
            ));
            
            if (!empty($new_template)) {
                return $new_template;
            }
        }
    }
    return $template;
}

// Remove any existing template filters
remove_filter('template_include', 'twintack_template_hierarchy');
remove_filter('template_include', 'twintack_category_template');

// Add our new template loader
add_filter('template_include', 'twintack_category_template_loader', 99);

/**
 * Load header classes
 */
function twintack_load_header_classes() {
    require_once get_template_directory() . '/inc/header/class-header-configuration.php';
    require_once get_template_directory() . '/inc/header/class-header-render.php';
    
    // Initialize header configuration
    Header_Configuration::get_instance();
}
add_action('after_setup_theme', 'twintack_load_header_classes');

function twintack_category_body_class($classes) {
    if (is_product_category() || is_product()) {
        $term = null;
        
        if (is_product_category()) {
            $term = get_queried_object();
        } elseif (is_product()) {
            $terms = get_the_terms(get_the_ID(), 'product_cat');
            if ($terms) {
                $term = reset($terms); // Get first category
            }
        }
        
        if ($term) {
            if (strpos(strtolower($term->name), 'baseball') !== false) {
                $classes[] = 'baseball';
            } elseif (strpos(strtolower($term->name), 'fishing') !== false) {
                $classes[] = 'fishing';
            }
        }
    }
    return $classes;
}
add_filter('body_class', 'twintack_category_body_class');

/**
 * Load menu configuration
 */
function twintack_load_menu_classes() {
    require_once get_template_directory() . '/inc/class-menu-configuration.php';
    Menu_Configuration::get_instance();
}
add_action('after_setup_theme', 'twintack_load_menu_classes');

function twintack_get_category_menu($category_type) {
    if (is_product_category() || is_shop()) {
        $current_term = get_queried_object();
        $category_base = '';
        
        // Check if current category or its ancestors are baseball/fishing
        if ($current_term && isset($current_term->term_id)) {
            $ancestors = get_ancestors($current_term->term_id, 'product_cat');
            $all_terms = array_merge([$current_term->term_id], $ancestors);
            
            foreach ($all_terms as $term_id) {
                $term = get_term($term_id, 'product_cat');
                if (strpos(strtolower($term->name), $category_type) !== false) {
                    $category_base = $category_type;
                    break;
                }
            }
        }
        
        if ($category_base) {
            get_template_part('template-parts/navigation/category', $category_base);
        }
    }
}

function twintack_body_classes($classes) {
    // Add admin-bar class if admin bar is showing
    if (is_admin_bar_showing()) {
        $classes[] = 'has-admin-bar';
    }
    
    return $classes;
}
add_filter('body_class', 'twintack_body_classes');

function twintack2025_enqueue_fonts() {
    wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap', array(), null);
}
add_action('wp_enqueue_scripts', 'twintack2025_enqueue_fonts');

function twintack_load_variation_display() {
    require_once get_template_directory() . '/inc/class-variation-display.php';
    TwinTack_Variation_Display::get_instance();
}
add_action('after_setup_theme', 'twintack_load_variation_display');

// Add after your existing twintack2025_setup function
function twintack_variation_setup() {
    // Load variation display class
    require_once get_template_directory() . '/inc/class-variation-display.php';
    TwinTack_Variation_Display::get_instance();
    
    // Add variation image size
    add_image_size('variation-thumbnail', 300, 300, true);
}
add_action('after_setup_theme', 'twintack_variation_setup');

function twintack_locate_variation_template($template, $template_name, $template_path) {
    if ($template_name === 'content-product-variation.php') {
        $template = get_stylesheet_directory() . '/woocommerce/' . $template_name;
    }
    return $template;
}
add_filter('wc_get_template', 'twintack_locate_variation_template', 10, 3);

function twintack_setup_variation_display() {
    if (class_exists('TwinTack_Variation_Display')) {
        TwinTack_Variation_Display::get_instance();
    }
}
add_action('init', 'twintack_setup_variation_display');

class TwinTack_Category_Display {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('twintack_product_loop', array($this, 'display_product'));
    }

    public function display_product() {
        $product = wc_get_product(get_the_ID());
        
        if ($product && $product->is_type('variable')) {
            $variations = $product->get_available_variations();
            foreach ($variations as $variation) {
                $this->display_single_variation($variation, $product);
            }
        } else {
            wc_get_template_part('content', 'product');
        }
    }

    private function display_single_variation($variation, $product) {
        $variation_obj = wc_get_product($variation['variation_id']);
        if (!$variation_obj) return;
        
        echo '<div class="product-variation">';
        echo '<a class="product-variation-link" href="' . esc_url(add_query_arg('variation_id', $variation['variation_id'], get_permalink($product->get_id()))) . '">';
        echo wp_get_attachment_image($variation['image_id'], 'woocommerce_thumbnail');
        echo '<h2 class="woocommerce-loop-product__title">';
        echo esc_html($product->get_title());
        if (!empty($variation['attributes'])) {
            echo ' - ' . implode(', ', $variation['attributes']);
        }
        echo '</h2>';
        echo $variation_obj->get_price_html();
        echo '</a>';
        echo '</div>';
    }
}

// Initialize the category display
add_action('after_setup_theme', function() {
    TwinTack_Category_Display::get_instance();
});

function twintack_init_custom_types() {
    TwinTack_Marquee_Configuration::get_instance();
    TwinTack_Team_Member::get_instance();
}
add_action('after_setup_theme', 'twintack_init_custom_types');


// Functionality for Product Tabs
add_filter('woocommerce_product_tabs', 'custom_product_tabs', 98);
function custom_product_tabs($tabs) {
    // Rename Additional Information tab
    if (isset($tabs['additional_information'])) {
        $tabs['additional_information']['title'] = 'Specs';
    }

    // Optionally reorder tabs
    $tabs['description']['priority'] = 10;
    $tabs['additional_information']['priority'] = 20;
    $tabs['reviews']['priority'] = 30;

    return $tabs;
}

// Customize the Specs tab content
add_filter('woocommerce_product_additional_information_heading', 'custom_specs_heading');
function custom_specs_heading() {
    return 'Product Specifications'; // Change or remove the heading
}

// Customize how attributes are displayed
add_action('woocommerce_product_additional_information', 'custom_specs_content', 5);
function custom_specs_content() {
    global $product;
    
    // Get product attributes
    $attributes = $product->get_attributes();
    
    // Define attributes to exclude
    $excluded_attributes = array('weight', 'dimensions', 'pa_weight', 'pa_dimensions');
    
    
}

// Add to functions.php
add_action('after_setup_theme', 'custom_theme_setup');
function custom_theme_setup() {
    // Add WooCommerce support
    add_theme_support('woocommerce');
    
    // Add Product Gallery support
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
}

add_action('wp_enqueue_scripts', 'custom_product_gallery_scripts');
function custom_product_gallery_scripts() {
    if (is_product()) {
        wp_enqueue_script('flexslider');
        wp_enqueue_script('zoom');
        wp_enqueue_script('photoswipe');
        wp_enqueue_script('photoswipe-ui-default');
        
        // Add your custom gallery script if needed
        wp_enqueue_script('custom-product-gallery', get_template_directory_uri() . '/assets/js/product-gallery.js', array('jquery'), '1.0.0', true);
    }
}

function enqueue_product_carousel_scripts() {
    if (is_product()) {
        // Enqueue Slick Slider CSS
        wp_enqueue_style('slick', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css');
        wp_enqueue_style('slick-theme', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css');
        
        // Enqueue Slick Slider JS
        wp_enqueue_script('slick', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', array('jquery'), null, true);
        
        // Enqueue custom carousel script
        wp_enqueue_script('product-carousel', get_stylesheet_directory_uri() . '/js/product-carousel.js', array('jquery', 'slick'), null, true);
    }
}
add_action('wp_enqueue_scripts', 'enqueue_product_carousel_scripts');

function enqueue_product_gallery_scripts() {
    if (is_product()) {
        wp_enqueue_style('slick', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css');
        wp_enqueue_style('slick-theme', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css');
        wp_enqueue_script('slick', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', array('jquery'), null, true);
        wp_enqueue_script('product-carousel', get_stylesheet_directory_uri() . '/js/product-carousel.js', array('jquery', 'slick'), null, true);
    }
}
add_action('wp_enqueue_scripts', 'enqueue_product_gallery_scripts');

function twintack_enqueue_lightbox_scripts() {
    if (is_product()) {
        wp_enqueue_script('twintack-product-lightbox', get_stylesheet_directory_uri() . '/js/product-lightbox.js', array('jquery', 'photoswipe-ui-default'), null, true);
    }
}
add_action('wp_enqueue_scripts', 'twintack_enqueue_lightbox_scripts');

function custom_logo_svg() {
    $custom_logo_id = get_theme_mod('custom_logo');
    if ($custom_logo_id) {
        $logo_url = wp_get_attachment_url($custom_logo_id);
        
        // Path to your SVG file in the theme directory
        $svg_path = get_template_directory() . '/twintack-logo-1.svg';
        
        if (file_exists($svg_path)) {
            $svg_content = file_get_contents($svg_path);
            
            return sprintf(
                '<a href="%1$s" class="custom-logo-link" rel="home">%2$s</a>',
                esc_url(home_url('/')),
                $svg_content
            );
        }
    }
    return '';
}