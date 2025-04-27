<?php
/**
 * twintack2025 functions and definitions - MINIMAL VERSION
 *
 * This is a stripped-down version of functions.php that removes all CSP implementations,
 * Stripe modifications, and checkout-related JavaScript to test checkout functionality.
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
require_once get_template_directory() . '/inc/class-category-customizer.php';
require_once get_template_directory() . '/inc/header/class-header-configuration.php';//remove once marquee is working
require_once get_template_directory() . '/inc/marquee/class-marquee-configuration.php';
require_once get_template_directory() . '/inc/team/class-team-member.php';

// Include the minimal version of the WooCommerce API fix
require_once get_template_directory() . '/inc/woocommerce-api-fix-minimal.php';

/**
 * Custom debug logging function
 */
function twintack_log($message) {
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

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
	
	// Update navigation menus
	register_nav_menus(array(
		'primary' => esc_html__('Main Menu', 'twintack2025'),
		'sport' => esc_html__('Sport Menu', 'twintack2025'),
		'product' => esc_html__('Product Menu', 'twintack2025')
	));

	// Remove old menu registrations
	remove_theme_support('baseball');
	remove_theme_support('fishing');

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
	// Add Archivo Google Font
	wp_enqueue_style(
		'archivo-font',
		'https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,100..900;1,100..900&display=swap',
		array(),
		null
	);

	// Enqueue company page styles if using the company page template
	if (is_page_template('page-company.php')) {
		wp_enqueue_style(
			'company-page-styles',
			get_template_directory_uri() . '/css/company-page.css',
			array(),
			_S_VERSION
		);
	}

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
	
	// WooCommerce custom styles
    if (class_exists('WooCommerce')) {
        wp_enqueue_style(
            'twintack2025-woocommerce-custom',
            get_template_directory_uri() . '/css/woocommerce-custom.css',
            array(),
            filemtime(get_template_directory() . '/css/woocommerce-custom.css')
        );
    }
	
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
	
	// Cart update script
	if (class_exists('WooCommerce')) {
		wp_enqueue_script(
			'twintack2025-cart-update',
			get_template_directory_uri() . '/js/cart-update.js',
			array('jquery'),
			filemtime(get_template_directory() . '/js/cart-update.js'),
			true
		);
        
        // Enqueue custom product tabs JS on product pages
        if (is_product()) {
            wp_enqueue_script(
                'twintack2025-product-tabs',
                get_template_directory_uri() . '/js/product-tabs.js',
                array('jquery'),
                filemtime(get_template_directory() . '/js/product-tabs.js'),
                true
            );
        }
	}

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'twintack2025_scripts' );

/**
 * Add a simple admin notice if Klaviyo list ID is not set
 */
function twintack_check_klaviyo_credentials() {
	// Only show to administrators
	if (!current_user_can('manage_options')) {
		return;
	}
	
	// Get the values
	$klaviyo_data = twintack_get_klaviyo_data();
	$list_id = $klaviyo_data['listId'];
	
	// Check if list ID is missing or empty
	if (empty($list_id)) {
		?>
		<div class="notice notice-warning is-dismissible">
			<p><strong>TwinTack Klaviyo Integration:</strong> Please set your Klaviyo List ID to enable newsletter signups. <a href="<?php echo admin_url('options-general.php?page=twintack-klaviyo-settings'); ?>">Configure settings</a></p>
		</div>
		<?php
	}
}
add_action('admin_notices', 'twintack_check_klaviyo_credentials');

/**
 * Update the Klaviyo enqueue function to use the constants
 */
function twintack_enqueue_klaviyo_script() {
	// Check if required files exist before trying to get filemtime
	$css_file = get_template_directory() . '/css/klaviyo-form.css';
	$js_file = get_template_directory() . '/js/klaviyo-newsletter.js';
	
	if (!file_exists($css_file)) {
		error_log('Klaviyo CSS file not found: ' . $css_file);
	}
	
	if (!file_exists($js_file)) {
		error_log('Klaviyo JS file not found: ' . $js_file);
	}
	
	// Enqueue the CSS file
	wp_enqueue_style(
		'twintack-klaviyo-styles',
		get_template_directory_uri() . '/css/klaviyo-form.css',
		array(),
		file_exists($css_file) ? filemtime($css_file) : _S_VERSION
	);
	
	// Enqueue the JS file with jQuery dependency
	wp_enqueue_script(
		'twintack-klaviyo-newsletter',
		get_template_directory_uri() . '/js/klaviyo-newsletter.js',
		array('jquery'),
		file_exists($js_file) ? filemtime($js_file) : _S_VERSION,
		true
	);
	
	// Get Klaviyo data from the function that checks for constants
	$klaviyo_data = twintack_get_klaviyo_data();
	
	// Add AJAX URL and nonce for security
	$klaviyo_data['ajaxUrl'] = admin_url('admin-ajax.php');
	$klaviyo_data['nonce'] = wp_create_nonce('klaviyo_subscribe_nonce');
	
	// Debug output
	if (WP_DEBUG) {
		error_log('Klaviyo data for JS: ' . print_r($klaviyo_data, true));
	}
	
	// Pass data to the script
	wp_localize_script('twintack-klaviyo-newsletter', 'klaviyoData', $klaviyo_data);
}
add_action('wp_enqueue_scripts', 'twintack_enqueue_klaviyo_script');

/**
 * Load Jetpack compatibility file
 */
if ( defined( 'JETPACK__VERSION' ) ) {
	require_once get_template_directory() . '/inc/jetpack.php';
}

/**
 * Load Klaviyo integration settings
 */
require_once get_template_directory() . '/inc/klaviyo-settings.php';

/**
 * Load Klaviyo proxy for handling API requests
 */
require_once get_template_directory() . '/inc/klaviyo-proxy.php';

function twintack2025_enqueue_fonts() {
    wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Saira+Condensed:wght@100;200;300;400;500;600;700;800;900&display=swap', array(), null);
}
add_action('wp_enqueue_scripts', 'twintack2025_enqueue_fonts');

function twintack_locate_variation_template($template, $template_name, $template_path) {
    if ($template_name === 'content-product-variation.php') {
        $template = get_stylesheet_directory() . '/woocommerce/' . $template_name;
    }
    return $template;
}
add_filter('wc_get_template', 'twintack_locate_variation_template', 10, 3);

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
        echo wp_get_attachment_image($variation['image_id'], 'woocommerce_thumbnail', false, array('class' => 'attachment-woocommerce_thumbnail size-woocommerce_thumbnail'));
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

/**
 * TwinTack Product Display Scripts
 * Enqueues scripts and styles for the product display on shop and product category pages
 */
function twintack_product_display_scripts() {
    if (is_shop() || is_product_category()) {
        // Enqueue CSS
        wp_enqueue_style(
            'twintack-product-display',
            get_stylesheet_directory_uri() . '/css/components/woocommerce-product-display.css',
            array(),
            filemtime(get_stylesheet_directory() . '/css/components/woocommerce-product-display.css')
        );
        
        // Enqueue JS
        wp_enqueue_script(
            'twintack-product-display',
            get_stylesheet_directory_uri() . '/js/product-display.js',
            array('jquery'),
            filemtime(get_stylesheet_directory() . '/js/product-display.js'),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'twintack_product_display_scripts');

/**
 * Modify WooCommerce query to show all products without pagination
 */
function twintack_show_all_products($query) {
    if (!is_admin() && $query->is_main_query()) {
        if (is_shop() || is_product_category()) {
            // Set to show all products
            $query->set('posts_per_page', -1);
            // Set ordering to ensure all variations are displayed
            $query->set('orderby', 'menu_order title');
            $query->set('order', 'ASC');
        }
    }
    return $query;
}
add_filter('pre_get_posts', 'twintack_show_all_products');

/**
 * Remove pagination from WooCommerce shop and category pages
 */
function twintack_remove_pagination() {
    if (is_shop() || is_product_category()) {
        remove_action('woocommerce_after_shop_loop', 'woocommerce_pagination', 10);
    }
}
add_action('woocommerce_before_shop_loop', 'twintack_remove_pagination', 5);

// Include Carousel functionality files
require get_template_directory() . '/inc/carousel-post-type.php';
require get_template_directory() . '/inc/carousel-admin.php';
require get_template_directory() . '/inc/carousel-integration.php';

require get_template_directory() . '/inc/how-to-videos.php';

function twintack_enqueue_video_modal_styles() {
    if (is_product()) {
        wp_enqueue_style(
            'twintack-video-modal',
            get_template_directory_uri() . '/css/components/_how-to-videos.css',
            array(),
            '1.0.0'
        );
    }
}
add_action('wp_enqueue_scripts', 'twintack_enqueue_video_modal_styles');

/**
 * Register WooCommerce account endpoints
 */
function twintack_register_woocommerce_endpoints() {
    // Register standard WooCommerce endpoints
    add_rewrite_endpoint('orders', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('view-order', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('downloads', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('edit-account', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('edit-address', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('payment-methods', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('customer-logout', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('add-payment-method', EP_ROOT | EP_PAGES);
}
add_action('init', 'twintack_register_woocommerce_endpoints');

/**
 * Function to flush rewrite rules when needed
 * This should only be run once after changing endpoints
 */
function twintack_flush_rewrite_rules() {
    // Call the function that registers endpoints
    twintack_register_woocommerce_endpoints();
    
    // Flush the rules
    flush_rewrite_rules();
}
// Run this once, then comment it out again to avoid performance issues
// add_action('init', 'twintack_flush_rewrite_rules', 20); 

/**
 * Enqueue checkout compatibility script
 */
function twintack_enqueue_checkout_compat() {
    if (is_checkout()) {
        wp_enqueue_script(
            'twintack-checkout-compat',
            get_template_directory_uri() . '/js/checkout-compat.js',
            array('jquery', 'wp-util'),
            filemtime(get_template_directory() . '/js/checkout-compat.js'),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'twintack_enqueue_checkout_compat', 20); 