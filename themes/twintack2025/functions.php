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
	
	// Cart update script
	if (class_exists('WooCommerce')) {
		wp_enqueue_script(
			'twintack2025-cart-update',
			get_template_directory_uri() . '/js/cart-update.js',
			array('jquery'),
			filemtime(get_template_directory() . '/js/cart-update.js'),
			true
		);
	}

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

function twintack2025_enqueue_fonts() {
    wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap', array(), null);
}
add_action('wp_enqueue_scripts', 'twintack2025_enqueue_fonts');

function twintack_load_variation_display() {
    require_once get_template_directory() . '/inc/class-variation-display.php';
    TwinTack_Variation_Display::get_instance();
}
add_action('after_setup_theme', 'twintack_load_variation_display');

// Add the image size to the existing function
add_action('after_setup_theme', function() {
    // Add variation image size
    add_image_size('variation-thumbnail', 300, 300, true);
});

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

/**
 * TwinTack Unified Login System
 * 
 * Handles the unified login experience for WooCommerce, Wholesale, and Affiliate users.
 */

// Redirect all login URLs to the unified login page
function twintack_login_url_filter( $login_url, $redirect = '' ) {
    // Don't modify the URL if we're already on the login page
    // This prevents redirect loops
    if ( is_page( 'login' ) ) {
        return $login_url;
    }
    
    // Add the redirect parameter if provided
    $url = site_url( '/login/' );
    if ( ! empty( $redirect ) ) {
        $url = add_query_arg( 'redirect_to', urlencode( $redirect ), $url );
    }
    return $url;
}

// Apply the filter to various login URL hooks
add_filter( 'woocommerce_get_myaccount_page_permalink', 'twintack_login_url_filter', 10, 1 );
add_filter( 'login_url', 'twintack_login_url_filter', 10, 2 );
add_filter( 'woocommerce_login_url', 'twintack_login_url_filter', 10, 2 );

// If you're using a wholesale plugin, add its filter (adjust as needed)
add_filter( 'wholesale_login_url', 'twintack_login_url_filter', 10, 2 );

// If you're using an affiliate plugin, add its filter (adjust as needed)
add_filter( 'affiliate_login_url', 'twintack_login_url_filter', 10, 2 );

// Customize the WordPress login page
function twintack_custom_login() {
    // Only redirect non-admin login attempts to our custom login page
    // Check if we're on the login page but not performing a specific action
    if ( ! isset( $_GET['action'] ) || 
         ( $_GET['action'] !== 'login' && 
           $_GET['action'] !== 'logout' && 
           $_GET['action'] !== 'lostpassword' && 
           $_GET['action'] !== 'rp' && 
           $_GET['action'] !== 'resetpass' ) 
    ) {
        // Make sure we're on the login page and not already on our custom login page
        if ( strpos( $_SERVER['REQUEST_URI'], '/wp-login.php' ) !== false && 
             ! isset( $_REQUEST['interim-login'] ) && 
             ! is_user_logged_in() && 
             ! strpos( $_SERVER['PHP_SELF'], 'wp-admin' ) &&
             ! isset( $_GET['redirect_to'] ) // Don't redirect if there's already a redirect parameter
        ) {
            wp_safe_redirect( site_url( '/login/' ) );
            exit;
        }
    }
    
    // Customize the login page appearance
    echo '<style type="text/css">
        body.login {
            background-color: #f1f1f1;
        }
        .login h1 a {
            background-image: url(' . get_stylesheet_directory_uri() . '/assets/images/logo.png);
            width: 320px;
            background-size: contain;
        }
    </style>';
}
add_action( 'login_enqueue_scripts', 'twintack_custom_login' );
add_action( 'login_head', 'twintack_custom_login' );

// Change login logo URL
function twintack_login_logo_url() {
    return home_url();
}
add_filter( 'login_headerurl', 'twintack_login_logo_url' );

// Handle role-based login redirects
function twintack_login_redirect( $redirect, $user ) {
    // Debug information
    if (WP_DEBUG === true) {
        error_log('Login redirect triggered for user: ' . $user->user_login);
        error_log('Default redirect: ' . $redirect);
        error_log('REQUEST redirect_to: ' . (isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : 'Not set'));
    }
    
    // If there's a specific redirect_to parameter and it's a valid URL, use it
    if ( isset( $_REQUEST['redirect_to'] ) && ! empty( $_REQUEST['redirect_to'] ) ) {
        $redirect_to = $_REQUEST['redirect_to'];
        // Make sure it's a safe URL (on the same domain)
        if ( wp_validate_redirect( $redirect_to ) ) {
            if (WP_DEBUG === true) {
                error_log('Using redirect_to parameter: ' . $redirect_to);
            }
            return $redirect_to;
        }
    }
    
    // Get the user's role
    $user_roles = $user->roles;
    
    // Redirect based on user role
    if ( in_array( 'wholesale_customer', $user_roles ) ) {
        $wholesale_url = apply_filters( 'twintack_wholesale_dashboard_url', site_url( '/wholesale-dashboard/' ) );
        if (WP_DEBUG === true) {
            error_log('Redirecting wholesale user to: ' . $wholesale_url);
        }
        return $wholesale_url;
    } elseif ( in_array( 'affiliate', $user_roles ) ) {
        $affiliate_url = apply_filters( 'twintack_affiliate_dashboard_url', site_url( '/affiliate-dashboard/' ) );
        if (WP_DEBUG === true) {
            error_log('Redirecting affiliate user to: ' . $affiliate_url);
        }
        return $affiliate_url;
    } elseif ( in_array( 'administrator', $user_roles ) ) {
        $admin_url = admin_url();
        if (WP_DEBUG === true) {
            error_log('Redirecting admin user to: ' . $admin_url);
        }
        return $admin_url;
    } else {
        // Regular customers go to the WooCommerce my account page
        $account_url = wc_get_page_permalink( 'myaccount' );
        if (WP_DEBUG === true) {
            error_log('Redirecting customer user to: ' . $account_url);
        }
        return $account_url;
    }
}
add_filter( 'login_redirect', 'twintack_login_redirect', 10, 2 );
add_filter( 'woocommerce_login_redirect', 'twintack_login_redirect', 10, 2 );

// Process the user type selection during registration
function twintack_process_registration( $customer_id, $new_customer_data, $password_generated ) {
    // Check if account_type was submitted
    if ( isset( $_POST['account_type'] ) ) {
        $account_type = sanitize_text_field( $_POST['account_type'] );
        
        // Assign the appropriate role based on account type
        $user = new WP_User( $customer_id );
        
        switch ( $account_type ) {
            case 'wholesale':
                // Remove the default role
                $user->remove_role( 'customer' );
                // Add the wholesale role
                $user->add_role( 'wholesale_customer' );
                break;
                
            case 'affiliate':
                // Remove the default role
                $user->remove_role( 'customer' );
                // Add the affiliate role
                $user->add_role( 'affiliate' );
                break;
                
            default:
                // Keep the default customer role
                break;
        }
        
        // Force password generation if it wasn't generated
        if ( !$password_generated ) {
            // Generate a password
            $password = wp_generate_password();
            
            // Set the user's password
            wp_set_password( $password, $customer_id );
            
            // Trigger the new user notification manually
            wp_new_user_notification( $customer_id, null, 'user' );
        }
    }
}
add_action( 'woocommerce_created_customer', 'twintack_process_registration', 10, 3 );

// Update login styles to include the password reset forms
function twintack_login_styles() {
    if ( is_page( 'login' ) ) {
        ?>
        <style type="text/css">
            .twintack-login-page {
                padding: 40px 0;
            }
            .twintack-login-container {
                background: #fff;
                padding: 30px;
                border-radius: 5px;
                box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            }
            .twintack-user-type-selection {
                margin: 20px 0;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 4px;
            }
            .user-type-options {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
            }
            .user-type-options label {
                display: flex;
                align-items: center;
                cursor: pointer;
            }
            .user-type-options input {
                margin-right: 5px;
            }
            .nav-tabs {
                margin-bottom: 20px;
            }
            .woocommerce-message, 
            .woocommerce-error, 
            .woocommerce-info {
                padding: 1em 1.5em;
                margin: 0 0 2em;
                position: relative;
                background-color: #f7f6f7;
                color: #515151;
                border-top: 3px solid #a46497;
                list-style: none outside;
                width: auto;
                word-wrap: break-word;
                border-radius: 4px;
            }
            .woocommerce-message {
                border-top-color: #8fae1b;
            }
            .woocommerce-error {
                border-top-color: #b81c23;
            }
            .return-to-login {
                text-align: center;
                margin-top: 20px;
            }
            .password-info-message {
                background-color: #f8f8f8;
                padding: 10px 15px;
                border-left: 3px solid #2271b1;
                margin: 15px 0;
                border-radius: 3px;
            }
            /* Password reset form specific styles */
            .password-strength,
            .password-match {
                margin-top: 5px;
                font-size: 0.9em;
            }
            .twintack-reset-password-form input[type="password"] {
                border: 1px solid #ddd;
                padding: 10px;
                border-radius: 4px;
                width: 100%;
            }
            .twintack-reset-password-form .woocommerce-Button {
                margin-top: 15px;
            }
            /* Fix for console errors with missing resources */
            .woocommerce-error {
                list-style-type: none !important;
            }
        </style>
        <?php
    }
}
add_action( 'wp_head', 'twintack_login_styles' );

// Handle user type selection during login
function twintack_authenticate_user_type( $user, $username, $password ) {
    // If authentication has already failed, don't do anything
    if ( is_wp_error( $user ) ) {
        return $user;
    }
    
    // Check if user_type was submitted
    if ( isset( $_POST['user_type'] ) ) {
        $user_type = sanitize_text_field( $_POST['user_type'] );
        $user_roles = $user->roles;
        
        // Check if the user has the appropriate role for the selected user type
        switch ( $user_type ) {
            case 'wholesale':
                if ( ! in_array( 'wholesale_customer', $user_roles ) && ! in_array( 'administrator', $user_roles ) ) {
                    return new WP_Error( 'invalid_user_type', __( 'You do not have access to the wholesale area.', 'twintack2025' ) );
                }
                break;
                
            case 'affiliate':
                if ( ! in_array( 'affiliate', $user_roles ) && ! in_array( 'administrator', $user_roles ) ) {
                    return new WP_Error( 'invalid_user_type', __( 'You do not have access to the affiliate area.', 'twintack2025' ) );
                }
                break;
                
            default:
                // For regular customers, no additional checks needed
                break;
        }
    }
    
    return $user;
}
add_filter( 'authenticate', 'twintack_authenticate_user_type', 30, 3 );

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
 * Add Display Type custom field to WooCommerce products
 */
function twintack_add_display_type_field() {
    woocommerce_wp_select(
        array(
            'id'          => 'display_type',
            'label'       => __('Display Type', 'twintack2025'),
            'description' => __('Choose how this product should be displayed in the shop', 'twintack2025'),
            'desc_tip'    => true,
            'options'     => array(
                ''              => __('Standard Grid (Default)', 'twintack2025'),
                'grip_carousel' => __('Grip Carousel', 'twintack2025')
            )
        )
    );
}
add_action('woocommerce_product_options_general_product_data', 'twintack_add_display_type_field');

/**
 * Save Display Type custom field
 */
function twintack_save_display_type_field($post_id) {
    $display_type = isset($_POST['display_type']) ? $_POST['display_type'] : '';
    update_post_meta($post_id, 'display_type', $display_type);
}
add_action('woocommerce_process_product_meta', 'twintack_save_display_type_field');

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

/**
 * Add custom image sizes for different product displays
 */
function twintack_add_custom_image_sizes() {
    // Add image size specifically for grip products in the carousel
    // Taller, narrower image optimized for the vertical bat-rack style display
    add_image_size('grip-carousel', 200, 600, false); // Width: 200px, Height: 600px, Soft crop
    
    // Standard product grid image (square format)
    add_image_size('product-grid', 400, 400, true); // Width: 400px, Height: 400px, Hard crop
}
add_action('after_setup_theme', 'twintack_add_custom_image_sizes');

/**
 * Add the custom image sizes to the media library dropdown
 */
function twintack_custom_image_sizes_names($sizes) {
    return array_merge($sizes, array(
        'grip-carousel' => __('Grip Carousel Image', 'twintack2025'),
        'product-grid' => __('Product Grid Image', 'twintack2025')
    ));
}
add_filter('image_size_names_choose', 'twintack_custom_image_sizes_names');

/**
 * Add admin notice to regenerate thumbnails after adding new image sizes
 */
function twintack_thumbnail_notice() {
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check if we've already dismissed this notice
    if (get_option('twintack_thumbnail_notice_dismissed')) {
        return;
    }
    
    ?>
    <div class="notice notice-warning is-dismissible" id="twintack-thumbnail-notice">
        <p>
            <strong>TwinTack:</strong> New product image sizes have been added. 
            <a href="<?php echo esc_url(admin_url('admin.php?page=wc-status&tab=tools')); ?>">
                Please regenerate your thumbnails
            </a> 
            to ensure all products display correctly.
        </p>
    </div>
    <script>
        jQuery(document).ready(function($) {
            $(document).on('click', '#twintack-thumbnail-notice .notice-dismiss', function() {
                $.ajax({
                    url: ajaxurl,
                    data: {
                        action: 'dismiss_thumbnail_notice'
                    }
                });
            });
        });
    </script>
    <?php
}
add_action('admin_notices', 'twintack_thumbnail_notice');

/**
 * AJAX handler to dismiss the thumbnail notice
 */
function twintack_dismiss_thumbnail_notice() {
    update_option('twintack_thumbnail_notice_dismissed', true);
    wp_die();
}
add_action('wp_ajax_dismiss_thumbnail_notice', 'twintack_dismiss_thumbnail_notice');

/**
 * Handle product filtering based on attributes
 */
function twintack_product_filter($query) {
    if (!is_admin() && $query->is_main_query() && (is_shop() || is_product_category())) {
        try {
            $tax_query = array();
            
            // Get all attribute taxonomies
            $attributes = wc_get_attribute_taxonomies();
            
            foreach ($attributes as $attribute) {
                $taxonomy = 'pa_' . $attribute->attribute_name;
                $filter_key = 'filter_' . $taxonomy;
                
                // Check if filter exists in GET parameters
                if (isset($_GET[$filter_key])) {
                    // Handle both array and string values
                    $filter_values = is_array($_GET[$filter_key]) ? $_GET[$filter_key] : array($_GET[$filter_key]);
                    
                    // Clean and validate the filter values
                    $valid_terms = array();
                    foreach ($filter_values as $value) {
                        $clean_value = sanitize_text_field($value);
                        // Verify the term exists
                        if (term_exists($clean_value, $taxonomy)) {
                            $valid_terms[] = $clean_value;
                        }
                    }
                    
                    // Only add to tax query if we have valid terms
                    if (!empty($valid_terms)) {
                        $tax_query[] = array(
                            'taxonomy' => $taxonomy,
                            'field'    => 'slug',
                            'terms'    => $valid_terms,
                            'operator' => 'IN',
                        );
                    }
                }
            }
            
            if (!empty($tax_query)) {
                // Get existing tax query
                $existing_tax_query = $query->get('tax_query');
                
                // Merge with existing tax query if it exists
                if (!empty($existing_tax_query)) {
                    $tax_query = array_merge(
                        array('relation' => 'AND'),
                        $existing_tax_query,
                        $tax_query
                    );
                } else {
                    $tax_query = array_merge(
                        array('relation' => 'AND'),
                        $tax_query
                    );
                }
                
                $query->set('tax_query', $tax_query);
            }
            
        } catch (Exception $e) {
            error_log('TwinTack Product Filter Error: ' . $e->getMessage());
            return $query;
        }
    }
    return $query;
}
add_filter('pre_get_posts', 'twintack_product_filter', 99);

// Add support for array parameters in URLs
function twintack_query_vars($vars) {
    // Get all attribute taxonomies
    $attributes = wc_get_attribute_taxonomies();
    
    foreach ($attributes as $attribute) {
        $filter_key = 'filter_pa_' . $attribute->attribute_name;
        $vars[] = $filter_key;
        
        // Also register array version of the parameter
        $vars[] = $filter_key . '[]';
    }
    
    return $vars;
}
add_filter('query_vars', 'twintack_query_vars');

// Register custom rewrite rules for filter parameters
function twintack_add_rewrite_rules() {
    global $wp_rewrite;
    
    // Get all attribute taxonomies
    $attributes = wc_get_attribute_taxonomies();
    
    foreach ($attributes as $attribute) {
        $filter_key = 'filter_pa_' . $attribute->attribute_name;
        add_rewrite_tag("%{$filter_key}%", '([^&]+)');
        add_rewrite_tag("%{$filter_key}[]%", '([^&]+)');
    }
}
add_action('init', 'twintack_add_rewrite_rules', 10, 0);

// Flush rewrite rules when needed
function twintack_flush_rules() {
    $version = '1.0.1'; // Increment this when making changes to rewrite rules
    $current_version = get_option('twintack_rewrite_version');
    
    if ($current_version !== $version) {
        flush_rewrite_rules(false);
        update_option('twintack_rewrite_version', $version);
    }
}
add_action('init', 'twintack_flush_rules', 20);

/**
 * Display a single product variation in the product loop
 * 
 * @param array $variation The variation data
 * @param WC_Product $product The parent product
 */
function twintack_display_single_variation($variation, $product) {
    $variation_obj = wc_get_product($variation['variation_id']);
    if (!$variation_obj) return;
    
    echo '<li class="product product-variation type-product">';
    echo '<a class="product-variation-link woocommerce-LoopProduct-link" href="' . esc_url(add_query_arg('variation_id', $variation['variation_id'], get_permalink($product->get_id()))) . '">';
    
    // Display sale flash if on sale
    if ($variation_obj->is_on_sale()) {
        echo '<span class="onsale">' . esc_html__('Sale!', 'woocommerce') . '</span>';
    }
    
    // Display variation image
    echo wp_get_attachment_image($variation['image_id'], 'woocommerce_thumbnail', false, array('class' => 'attachment-woocommerce_thumbnail size-woocommerce_thumbnail'));
    
    // Display product title with variation attributes
    echo '<h2 class="woocommerce-loop-product__title">';
    echo esc_html($product->get_title());
    if (!empty($variation['attributes'])) {
        echo ' - ' . implode(', ', array_values($variation['attributes']));
    }
    echo '</h2>';
    
    // Display price
    echo '<span class="price">' . $variation_obj->get_price_html() . '</span>';
    
    echo '</a>';
    echo '</li>';
}

/**
 * Redirect product category pages to shop page with category filter
 * This ensures category pages use the same layout as the shop page
 */
function twintack_redirect_product_categories_to_shop() {
    // Only run on product category pages and not on the shop page
    if (is_product_category() && !is_shop()) {
        // Check if we're already on a redirected URL to prevent loops
        if (isset($_GET['redirected_from_category'])) {
            return;
        }
        
        // Get current category
        $category = get_queried_object();
        
        // Build the redirect URL
        $redirect_url = add_query_arg(
            array(
                'product_cat' => $category->slug,
                'redirected_from_category' => '1' // Add a flag to prevent redirect loops
            ),
            get_permalink(wc_get_page_id('shop'))
        );
        
        // Preserve any existing query parameters
        foreach ($_GET as $key => $value) {
            if ($key !== 'product_cat' && $key !== 'redirected_from_category') {
                $redirect_url = add_query_arg($key, $value, $redirect_url);
            }
        }
        
        // Redirect
        wp_safe_redirect($redirect_url);
        exit;
    }
}
add_action('template_redirect', 'twintack_redirect_product_categories_to_shop', 5); // Lower priority to run early

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

// Add this to your theme's functions.php or a debugging plugin
add_action('gform_after_submission', function($entry, $form) {
    error_log('Form submitted: ' . print_r($entry, true));
}, 10, 2);

// Fix password reset URL to use our custom login page
function twintack_custom_reset_password_url( $default_url, $user_id = null ) {
    // Get our custom login page URL
    $login_url = site_url( '/login/' );
    
    // Ensure we're only modifying the reset password URL
    if ( strpos( $default_url, 'action=rp' ) !== false ) {
        // Extract the key and login from the default URL
        $parts = parse_url( $default_url );
        parse_str( $parts['query'], $query );
        
        if ( isset( $query['key'] ) && isset( $query['login'] ) ) {
            // Reconstruct the URL with our login page
            $login_url = add_query_arg( array(
                'action' => 'rp',
                'key'    => $query['key'],
                'login'  => $query['login'],
            ), $login_url );
            
            return $login_url;
        }
    }
    
    return $default_url;
}
add_filter( 'lostpassword_url', 'twintack_custom_reset_password_url', 20, 1 );
add_filter( 'woocommerce_get_endpoint_url', 'twintack_fix_password_reset_endpoint', 10, 4 );

function twintack_fix_password_reset_endpoint( $url, $endpoint, $value, $permalink ) {
    if ( $endpoint === 'lost-password' ) {
        return site_url( '/login/?action=lostpassword' );
    }
    
    return $url;
}

// Redirect WooCommerce account page to custom login for non-logged in users
function twintack_redirect_account_page() {
    // Only apply on the my-account page
    if ( ! is_user_logged_in() && is_account_page() && ! is_wc_endpoint_url() ) {
        // Redirect to our custom login page
        wp_redirect( site_url( '/login/' ) );
        exit;
    }
}
add_action( 'template_redirect', 'twintack_redirect_account_page' );

// Add registration success redirection
function twintack_registration_redirect( $redirect_to ) {
    // Modify only if this is a registration
    if ( isset( $_POST['register'] ) ) {
        return add_query_arg( 'registered', 'success', site_url( '/login/' ) );
    }
    
    return $redirect_to;
}
add_filter( 'woocommerce_registration_redirect', 'twintack_registration_redirect', 10, 1 );

/**
 * Fix the user notification email to use our custom password reset URL format
 */
function twintack_custom_password_reset_email( $message, $key, $user_login, $user_data ) {
    // Build the reset URL to point to our custom login page
    $reset_url = add_query_arg(
        array(
            'action' => 'resetpass',
            'key'    => $key,
            'login'  => rawurlencode( $user_login ),
        ),
        site_url( '/login/' )
    );
    
    // Replace the default URL in the email with our custom URL
    $message = str_replace(
        network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' ),
        esc_url_raw( $reset_url ),
        $message
    );
    
    // Additional replacement to catch other URL formats
    $message = str_replace(
        site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ) ),
        esc_url_raw( $reset_url ),
        $message
    );
    
    // Log for debugging
    if (WP_DEBUG === true) {
        error_log('Password reset email URL: ' . $reset_url);
    }
    
    return $message;
}
add_filter( 'retrieve_password_message', 'twintack_custom_password_reset_email', 10, 4 );

// Also fix the wp_new_user_notification_email filter
function twintack_custom_new_user_notification_email( $wp_new_user_notification_email, $user, $blogname ) {
    // Get the key
    $key = get_password_reset_key( $user );
    
    if ( is_wp_error( $key ) ) {
        return $wp_new_user_notification_email;
    }
    
    // Build the reset URL to point to our custom login page with a clearer action
    $reset_url = add_query_arg(
        array(
            'action' => 'setup_password', // Using a more distinctive action name
            'key'    => $key,
            'login'  => rawurlencode( $user->user_login ),
        ),
        site_url( '/login/' )
    );
    
    // Replace the default URL in the email with our custom URL
    $wp_new_user_notification_email['message'] = str_replace(
        network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ), 'login' ), 
        esc_url_raw( $reset_url ),
        $wp_new_user_notification_email['message']
    );
    
    // Additional replacement to catch other URL formats
    $wp_new_user_notification_email['message'] = str_replace(
        site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ) ),
        esc_url_raw( $reset_url ),
        $wp_new_user_notification_email['message']
    );
    
    // Log the email message for debugging
    if (WP_DEBUG === true) {
        error_log('New user reset URL in email: ' . $reset_url);
        error_log('Email message contains reset URL: ' . (strpos($wp_new_user_notification_email['message'], $reset_url) !== false ? 'Yes' : 'No'));
    }
    
    return $wp_new_user_notification_email;
}
add_filter( 'wp_new_user_notification_email', 'twintack_custom_new_user_notification_email', 10, 3 );

// Hook into password reset to debug the process
function twintack_debug_password_reset($user_login) {
    twintack_log('Password reset requested for: ' . $user_login);
    
    $user = get_user_by('login', $user_login);
    if ($user) {
        // Get the key that was generated
        $key = get_transient('retrieve_key_for_' . $user->ID);
        if ($key) {
            twintack_log('Generated reset key: ' . $key);
        }
    }
}
add_action('retrieve_password', 'twintack_debug_password_reset');

// Include the password reset helper functions
require_once get_template_directory() . '/inc/password-reset-helper.php';

/**
 * Enqueue password reset script
 */
function twintack_enqueue_password_reset_script() {
    if (is_page('login') && isset($_GET['action']) && ($_GET['action'] === 'rp' || $_GET['action'] === 'resetpass')) {
        wp_enqueue_script(
            'twintack-password-reset',
            get_template_directory_uri() . '/js/password-reset.js',
            array('jquery'),
            filemtime(get_template_directory() . '/js/password-reset.js'),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'twintack_enqueue_password_reset_script');

/**
 * Force redirect after WooCommerce login
 * This ensures our custom login form always redirects correctly
 */
function twintack_force_login_redirect($user_login, $user) {
    // Don't redirect during AJAX requests
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    
    if (WP_DEBUG === true) {
        error_log('User logged in: ' . $user_login);
    }
    
    // Get the appropriate redirect URL based on user role
    $redirect_url = twintack_login_redirect('', $user);
    
    // Only redirect if we have a valid URL
    if (!empty($redirect_url)) {
        if (WP_DEBUG === true) {
            error_log('Forcing redirect to: ' . $redirect_url);
        }
        wp_safe_redirect($redirect_url);
        exit;
    }
}
add_action('wp_login', 'twintack_force_login_redirect', 10, 2);