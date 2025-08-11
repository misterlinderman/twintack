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
require_once get_template_directory() . '/inc/class-category-customizer.php';
require_once get_template_directory() . '/inc/header/class-header-configuration.php';//remove once marquee is working
require_once get_template_directory() . '/inc/marquee/class-marquee-configuration.php';
require_once get_template_directory() . '/inc/team/class-team-member.php';

/**
 * Mail header enforcement for deliverability
 * - Force From to a site-owned address
 * - Set envelope sender (Return-Path) for SPF/DMARC alignment
 * - Ensure a sane Reply-To fallback
 */
function twintack_wp_mail_from($from) {
    $domain_from = 'support@twintack.com';
    return $domain_from;
}
add_filter('wp_mail_from', 'twintack_wp_mail_from');

function twintack_wp_mail_from_name($name) {
    return 'TwinTack Support';
}
add_filter('wp_mail_from_name', 'twintack_wp_mail_from_name');

function twintack_phpmailer_init($phpmailer) {
    // Set envelope sender (Return-Path). Use a real mailbox or alias on the domain
    if (empty($phpmailer->Sender)) {
        $phpmailer->Sender = 'support@twintack.com';
    }

    // Ensure there is at least one Reply-To. Do not override if already set.
    if (empty($phpmailer->getReplyToAddresses())) {
        try {
            $phpmailer->addReplyTo('support@twintack.com', 'TwinTack Support');
        } catch (Exception $e) {
            // Best-effort; avoid fatal on hosts without full PHPMailer
        }
    }

    // Optional SMTP override via constants (no plugin required)
    // Define these in wp-config.php to activate:
    // define('TWINTACK_SMTP_HOST', 'smtp.postmarkapp.com');
    // define('TWINTACK_SMTP_PORT', 587);
    // define('TWINTACK_SMTP_ENCRYPTION', 'tls'); // '', 'tls', or 'ssl'
    // define('TWINTACK_SMTP_USERNAME', 'YOUR_USERNAME');
    // define('TWINTACK_SMTP_PASSWORD', 'YOUR_PASSWORD');
    if (defined('TWINTACK_SMTP_HOST') && TWINTACK_SMTP_HOST) {
        $phpmailer->isSMTP();
        $phpmailer->Host       = TWINTACK_SMTP_HOST;
        $phpmailer->Port       = defined('TWINTACK_SMTP_PORT') ? (int) TWINTACK_SMTP_PORT : 587;
        $phpmailer->SMTPAuth   = defined('TWINTACK_SMTP_USERNAME') && TWINTACK_SMTP_USERNAME ? true : false;
        if (defined('TWINTACK_SMTP_ENCRYPTION') && TWINTACK_SMTP_ENCRYPTION) {
            $phpmailer->SMTPSecure = TWINTACK_SMTP_ENCRYPTION;
        }
        if (defined('TWINTACK_SMTP_USERNAME') && TWINTACK_SMTP_USERNAME) {
            $phpmailer->Username = TWINTACK_SMTP_USERNAME;
        }
        if (defined('TWINTACK_SMTP_PASSWORD') && TWINTACK_SMTP_PASSWORD) {
            $phpmailer->Password = TWINTACK_SMTP_PASSWORD;
        }
    }

    // Lightweight debug: log actual From/Sender/To at send time in debug env
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $toList    = method_exists($phpmailer, 'getToAddresses') ? $phpmailer->getToAddresses() : array();
        $replyList = method_exists($phpmailer, 'getReplyToAddresses') ? $phpmailer->getReplyToAddresses() : array();
        error_log('TwinTack Mail Debug → From=' . ($phpmailer->From ?? '') . ' | Sender=' . ($phpmailer->Sender ?? '') . ' | To=' . json_encode($toList) . ' | Reply-To=' . json_encode($replyList));
        try { $phpmailer->addCustomHeader('X-TT-Debug', '1'); } catch (Exception $e) {}
    }
}
add_action('phpmailer_init', 'twintack_phpmailer_init');

// Optional: default to HTML for nicer templates (leave content to templates)
function twintack_wp_mail_content_type($content_type) {
    return 'text/html';
}
add_filter('wp_mail_content_type', 'twintack_wp_mail_content_type');

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

    // Reorder tabs - Specs first, then Description
    $tabs['additional_information']['priority'] = 5;
    $tabs['description']['priority'] = 10;
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
        error_log('POST data: ' . print_r($_POST, true));
    }
    
    // Check if this login is coming from Solid Affiliate plugin
    // If so, let Solid Affiliate handle its own redirect
    if ( isset( $_POST['submit_solid_affiliate_login'] ) || 
         ( isset( $_POST['user_email'] ) && isset( $_POST['user_pass'] ) && 
           ( strpos( $_SERVER['HTTP_REFERER'] ?? '', 'affiliates' ) !== false ||
             strpos( $_SERVER['REQUEST_URI'] ?? '', 'affiliates' ) !== false ) ) ) {
        if (WP_DEBUG === true) {
            error_log('Detected Solid Affiliate login - allowing plugin to handle redirect');
        }
        // Return the original redirect to let Solid Affiliate handle it
        return $redirect;
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
    // Load on shop pages, product category pages, single product pages (for related products), and sport template pages
    if (is_shop() || is_product_category() || is_product() || is_page_template('templates/template-sport.php')) {
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
 * Filter variations based on active filters
 * 
 * @param array $variations Array of product variations
 * @return array Filtered variations that match active filters
 */
function twintack_filter_variations_by_active_filters($variations) {
    // Get current active filters from URL
    $active_filters = array();
    
    // Get all attribute taxonomies
    $attributes = wc_get_attribute_taxonomies();
    
    foreach ($attributes as $attribute) {
        $filter_key = 'filter_pa_' . $attribute->attribute_name;
        if (isset($_GET[$filter_key]) && !empty($_GET[$filter_key])) {
            $active_filters['attribute_pa_' . $attribute->attribute_name] = $_GET[$filter_key];
        }
    }
    
    // If no attribute filters are active, return all variations
    if (empty($active_filters)) {
        return $variations;
    }
    
    // Debug logging for administrators
    if (WP_DEBUG && current_user_can('administrator')) {
        error_log('TwinTack Variation Filter - Active filters: ' . print_r($active_filters, true));
    }
    
    // Filter variations based on active filters
    $filtered_variations = array();
    
    foreach ($variations as $variation) {
        $should_include = true;
        
        // Check each active filter against this variation's attributes
        foreach ($active_filters as $attribute_name => $filter_value) {
            // Get the variation's attribute value
            $variation_attribute_value = '';
            if (isset($variation['attributes'][$attribute_name])) {
                $variation_attribute_value = $variation['attributes'][$attribute_name];
            }
            
            // Debug logging for administrators
            if (WP_DEBUG && current_user_can('administrator')) {
                error_log('TwinTack Variation Filter - Checking variation ' . $variation['variation_id'] . 
                         ' for attribute ' . $attribute_name . 
                         ': variation="' . $variation_attribute_value . 
                         '" filter="' . $filter_value . '"');
            }
            
            // If this variation doesn't match the filter, exclude it
            if ($variation_attribute_value !== $filter_value) {
                $should_include = false;
                break;
            }
        }
        
        // Only include variations that match all active filters
        if ($should_include) {
            $filtered_variations[] = $variation;
        }
    }
    
    // Debug logging for administrators
    if (WP_DEBUG && current_user_can('administrator')) {
        error_log('TwinTack Variation Filter - Filtered ' . count($filtered_variations) . ' variations out of ' . count($variations) . ' total');
    }
    
    return $filtered_variations;
}

/**
 * Get formatted attribute display name from slug
 * 
 * @param string $attribute_name The attribute taxonomy name
 * @param string $value The attribute value/slug
 * @return string The formatted display name
 */
function twintack_get_attribute_display_name($attribute_name, $value) {
    $taxonomy = str_replace('attribute_', '', $attribute_name);
    $term = get_term_by('slug', $value, $taxonomy);
    
    if ($term) {
        return $term->name;
    }
    
    // Fallback: convert slug to readable format
    return ucwords(str_replace('-', ' ', $value));
}

/**
 * Parse product name components for structured display
 * 
 * @param WC_Product $product The product object
 * @param array $variation_attributes The variation attributes array
 * @return array Array containing pattern, model, and color components
 */
function twintack_parse_product_name_components($product, $variation_attributes) {
    $model_name = $product->get_title(); // e.g., "TT Pro Bat Grip"
    $pattern_name = '';
    $color_name = '';
    
    // Extract pattern and color from variation attributes
    if (!empty($variation_attributes)) {
        $attribute_values = array_values($variation_attributes);
        $attribute_keys = array_keys($variation_attributes);
        
        // First attribute is typically the pattern/style
        if (count($attribute_values) > 0) {
            $pattern_name = twintack_get_attribute_display_name($attribute_keys[0], $attribute_values[0]);
        }
        
        // Second attribute is typically the color
        if (count($attribute_values) > 1) {
            $color_name = twintack_get_attribute_display_name($attribute_keys[1], $attribute_values[1]);
        }
    }
    
    return array(
        'pattern' => $pattern_name,
        'model' => $model_name,
        'color' => $color_name
    );
}

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
    
    // Display structured product title
    echo '<h2 class="woocommerce-loop-product__title">';
    
    if (!empty($variation['attributes'])) {
        // Parse the product name components
        $components = twintack_parse_product_name_components($product, $variation['attributes']);
        
        // Display in the requested order: Pattern > Model > Color
        echo '<div class="product-title-structured">';
        
        if (!empty($components['pattern'])) {
            echo '<span class="product-pattern">' . esc_html($components['pattern']) . '</span>';
        }
        
        if (!empty($components['model'])) {
            echo '<span class="product-model">' . esc_html($components['model']) . '</span>';
        }
        
        if (!empty($components['color'])) {
            echo '<span class="product-color">' . esc_html($components['color']) . '</span>';
        }
        
        echo '</div>';
    } else {
        // Fallback for products without variations
        echo '<span class="product-model">' . esc_html($product->get_title()) . '</span>';
    }
    
    echo '</h2>';
    
    // Display price
    echo '<span class="price">' . $variation_obj->get_price_html() . '</span>';
    
    echo '</a>';
    echo '</li>';
}

/**
 * Custom WooCommerce loop product title with structured format
 * Replaces the default woocommerce_template_loop_product_title function
 */
function twintack_custom_loop_product_title() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    echo '<h2 class="' . esc_attr( apply_filters( 'woocommerce_product_loop_title_classes', 'woocommerce-loop-product__title' ) ) . '">';
    
    // Check if this is a variation product (has variation attributes)
    if ($product->is_type('variation')) {
        // This is a variation product, parse its attributes
        $parent_product = wc_get_product($product->get_parent_id());
        $variation_attributes = $product->get_variation_attributes();
        
        if ($parent_product && !empty($variation_attributes)) {
            $components = twintack_parse_product_name_components($parent_product, $variation_attributes);
            
            echo '<div class="product-title-structured">';
            
            if (!empty($components['pattern'])) {
                echo '<span class="product-pattern">' . esc_html($components['pattern']) . '</span>';
            }
            
            if (!empty($components['model'])) {
                echo '<span class="product-model">' . esc_html($components['model']) . '</span>';
            }
            
            if (!empty($components['color'])) {
                echo '<span class="product-color">' . esc_html($components['color']) . '</span>';
            }
            
            echo '</div>';
        } else {
            // Fallback for variations without proper structure
            echo '<span class="product-model">' . esc_html($product->get_name()) . '</span>';
        }
    } else if ($product->is_type('variable')) {
        // This is a variable product parent, just show the base name
        echo '<span class="product-model">' . esc_html($product->get_name()) . '</span>';
    } else {
        // Regular product, just show the name
        echo '<span class="product-model">' . esc_html($product->get_name()) . '</span>';
    }
    
    echo '</h2>';
}

// Remove the default loop title and add our custom one
remove_action('woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10);
add_action('woocommerce_shop_loop_item_title', 'twintack_custom_loop_product_title', 10);

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
    
    // This is important - you'll need to flush rewrite rules once
    // But don't do this on every page load as it's expensive
    // Uncomment this only when you're making changes to endpoints
    // flush_rewrite_rules();
}
add_action('init', 'twintack_register_woocommerce_endpoints');

/**
 * Fix WooCommerce endpoint URLs
 * This ensures account endpoint URLs are properly constructed
 */
function twintack_fix_account_endpoints($url, $endpoint, $value, $permalink) {
    // Check if the URL incorrectly contains /login/ for account endpoints
    if (strpos($url, '/login/') !== false && 
        in_array($endpoint, ['orders', 'view-order', 'downloads', 'edit-account', 'edit-address', 
                             'payment-methods', 'customer-logout', 'add-payment-method', 'grip-designs'])) {
        
        // Get the my account page URL
        $my_account_url = wc_get_page_permalink('myaccount');
        
        // Reconstruct the URL properly
        if ($value) {
            $url = trailingslashit($my_account_url) . trailingslashit($endpoint) . $value;
        } else {
            $url = trailingslashit($my_account_url) . $endpoint;
        }
    }
    
    return $url;
}
add_filter('woocommerce_get_endpoint_url', 'twintack_fix_account_endpoints', 20, 4);

/**
 * Advanced override of WooCommerce endpoint URLs
 * Use this if the regular approach doesn't work
 */
function twintack_force_correct_account_urls() {
    // Only run this on front-end requests
    if (is_admin()) {
        return;
    }
    
    // Get the Account page ID and URL
    $account_page_id = wc_get_page_id('myaccount');
    if ($account_page_id <= 0) {
        return;
    }
    
    // Get the account page URL
    $account_page_url = get_permalink($account_page_id);
    if (!$account_page_url) {
        return;
    }
    
    // Make sure it's using the correct URL structure
    global $woocommerce;
    
    // Force the account page URL to be the correct one
    add_filter('woocommerce_get_myaccount_page_permalink', function() use ($account_page_url) {
        return $account_page_url;
    }, 999);
    
    // Override all endpoint URLs with high priority
    add_filter('woocommerce_get_endpoint_url', function($url, $endpoint, $value, $permalink) use ($account_page_url) {
        // Don't modify lost-password endpoint as that's handled by custom login
        if ($endpoint === 'lost-password') {
            return $url;
        }
        
        // Don't modify WooCommerce authentication endpoints (for API integrations like Klaviyo)
        if (strpos($url, '/wc-auth/') !== false) {
            return $url;
        }
        
        // Don't modify if this is already an authentication-related URL
        if (strpos($url, 'access_granted') !== false || 
            strpos($url, 'wc_auth_nonce') !== false ||
            strpos($url, 'callback_url') !== false) {
            return $url;
        }
        
        // Rebuild the endpoint URL using the correct account page
        if ($value) {
            return trailingslashit($account_page_url) . trailingslashit($endpoint) . $value;
        } else {
            return trailingslashit($account_page_url) . $endpoint;
        }
    }, 999, 4);
}
add_action('init', 'twintack_force_correct_account_urls', 5);

/**
 * Debug WooCommerce account URLs
 * Add ?debug_account=1 to any page to see the current endpoints and URLs
 */
function twintack_debug_account_urls() {
    if (!isset($_GET['debug_account']) || $_GET['debug_account'] != 1) {
        return;
    }
    
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Get account page info
    $account_page_id = wc_get_page_id('myaccount');
    $account_page_url = get_permalink($account_page_id);
    
    echo '<div style="background:#fff; padding:20px; margin:20px; border:1px solid #ccc;">';
    echo '<h2>WooCommerce Account Debug</h2>';
    echo '<p>Account Page ID: ' . $account_page_id . '</p>';
    echo '<p>Account Page URL: ' . $account_page_url . '</p>';
    
    // Check if this page exists
    $account_page = get_post($account_page_id);
    echo '<p>Account Page Status: ' . ($account_page ? $account_page->post_status : 'Not found') . '</p>';
    
    // Get all endpoints
    $endpoints = array(
        'orders',
        'view-order',
        'downloads',
        'edit-account',
        'edit-address',
        'payment-methods',
        'customer-logout',
        'add-payment-method',
        'grip-designs'
    );
    
    echo '<h3>Endpoint URLs:</h3>';
    echo '<ul>';
    foreach ($endpoints as $endpoint) {
        $url = wc_get_account_endpoint_url($endpoint);
        echo '<li><strong>' . $endpoint . ':</strong> ' . $url . '</li>';
    }
    echo '</ul>';
    
    echo '<h3>Is WC Endpoint:</h3>';
    foreach ($endpoints as $endpoint) {
        echo '<li><strong>' . $endpoint . ':</strong> ' . (WC()->query->get_current_endpoint() === $endpoint ? 'Yes' : 'No') . '</li>';
    }
    
    echo '<h3>Permalink Structure:</h3>';
    echo '<p>' . get_option('permalink_structure') . '</p>';
    
    echo '</div>';
    exit;
}
add_action('wp_loaded', 'twintack_debug_account_urls', 999);

/**
 * Check if WooCommerce pages exist and create them if not
 */
function twintack_check_woocommerce_pages() {
    // Add ?create_wc_pages=1 to any admin URL to force checking and creating pages
    if (is_admin() && isset($_GET['create_wc_pages']) && $_GET['create_wc_pages'] == 1 && current_user_can('manage_options')) {
        // This will install all WooCommerce pages, including My Account
        WC_Install::create_pages();
        
        // Redirect to admin with success message
        wp_redirect(admin_url('admin.php?page=wc-settings&tab=advanced&section=page_setup&wc_pages_created=1'));
        exit;
    }
    
    // Add admin notice if My Account page doesn't exist or is in trash
    if (is_admin() && current_user_can('manage_options')) {
        $account_page_id = wc_get_page_id('myaccount');
        $account_page = get_post($account_page_id);
        
        if (!$account_page || $account_page->post_status !== 'publish') {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p>The WooCommerce My Account page doesn't exist or is not published. <a href="<?php echo admin_url('?create_wc_pages=1'); ?>">Click here to create WooCommerce pages</a></p>
                </div>
                <?php
            });
        }
    }
}
add_action('init', 'twintack_check_woocommerce_pages');

/**
 * Add JavaScript to fix account links on the frontend
 * This is a client-side solution that will correct all links regardless of how they're generated
 */
function twintack_fix_account_links_js() {
    // Only add on the frontend
    if (is_admin()) {
        return;
    }
    
    // Only add on account pages or pages that might contain account links
    if (!is_account_page() && !is_front_page() && !is_page()) {
        return;
    }
    
    // Get the correct account page URL
    $account_url = wc_get_page_permalink('myaccount');
    if (!$account_url) {
        return;
    }
    
    // Make sure it ends with a slash
    $account_url = trailingslashit($account_url);
    
    // Add JavaScript to fix all account links
    ?>
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        // Get all links in the navigation
        var accountLinks = document.querySelectorAll('.woocommerce-MyAccount-navigation a');
        var correctAccountBaseUrl = '<?php echo esc_js($account_url); ?>';
        
        // Process each link
        accountLinks.forEach(function(link) {
            var href = link.getAttribute('href');
            
            // If the link contains /login/ and is an account endpoint, fix it
            if (href && href.indexOf('/login/') !== -1) {
                // Extract the endpoint from the URL
                var urlParts = href.split('/');
                var endpoint = '';
                
                // Find the endpoint part (usually after "login")
                for (var i = 0; i < urlParts.length; i++) {
                    if (urlParts[i] === 'login' && i + 1 < urlParts.length) {
                        endpoint = urlParts[i + 1];
                        break;
                    }
                }
                
                // If we found an endpoint, rebuild the URL
                if (endpoint) {
                    if (endpoint === 'customer-logout') {
                        // Special case for logout - keep WC nonce
                        var logoutUrl = href;
                        if (logoutUrl.indexOf('?') !== -1) {
                            // Keep the query string (contains the nonce)
                            var queryString = logoutUrl.split('?')[1];
                            link.setAttribute('href', correctAccountBaseUrl + 'customer-logout/?' + queryString);
                        } else {
                            link.setAttribute('href', correctAccountBaseUrl + 'customer-logout/');
                        }
                    } else {
                        // Regular endpoints
                        link.setAttribute('href', correctAccountBaseUrl + endpoint + '/');
                    }
                } else if (href.indexOf('login/?action=lostpassword') !== -1) {
                    // Lost password link - keep it as is
                } else {
                    // Fix dashboard link
                    link.setAttribute('href', correctAccountBaseUrl);
                }
            }
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'twintack_fix_account_links_js');

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
add_action('init', 'twintack_flush_rewrite_rules', 20);

/**
 * Include Carousel functionality files
 */
require get_template_directory() . '/inc/carousel-post-type.php';
require get_template_directory() . '/inc/carousel-admin.php';
require get_template_directory() . '/inc/carousel-integration.php';

/**
 * Customize My Account menu items
 */
function twintack_customize_account_menu_items($items) {
    $new_items = array();
    
    // Copy existing items, but skip downloads
    foreach ($items as $key => $value) {
        if ($key !== 'downloads') {
            $new_items[$key] = $value;
        }
    }
    
    // Add wholesale-specific menu items for wholesale users
    if (current_user_can('wholesale_customer')) {
        $new_items['wholesale-orderforms'] = __('Order Forms', 'twintack2025');
    }
    
    return $new_items;
}
add_filter('woocommerce_account_menu_items', 'twintack_customize_account_menu_items');

/**
 * Add custom endpoint for wholesale order forms
 */
function twintack_add_wholesale_endpoint() {
    add_rewrite_endpoint('wholesale-orderforms', EP_ROOT | EP_PAGES);
}
add_action('init', 'twintack_add_wholesale_endpoint');

/**
 * Add wholesale orderforms content
 */
function twintack_wholesale_orderforms_content() {
    ?>
    <div class="wholesale-orderforms-wrapper">
        <h2><?php _e('Order Forms', 'twintack2025'); ?></h2>
        <div class="orderforms-grid">
            <?php
            // Get your order form links/content here
            $orderforms = array(
                array(
                    'title' => 'Baseball Order Form',
                    'description' => 'Order baseball grips and accessories',
                    'link' => home_url('/wholesale-ordering/'),
                ),
                array(
                    'title' => 'Fishing Order Form',
                    'description' => 'Order fishing grips and accessories',
                    'link' => home_url('/wholesale-fishing-ordering/'),
                ),
                // Add more order forms as needed
            );

            foreach ($orderforms as $form) : ?>
                <div class="orderform-card">
                    <h3><?php echo esc_html($form['title']); ?></h3>
                    <p><?php echo esc_html($form['description']); ?></p>
                    <a href="<?php echo esc_url($form['link']); ?>" class="button"><?php _e('View Form', 'twintack2025'); ?></a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
add_action('woocommerce_account_wholesale-orderforms_endpoint', 'twintack_wholesale_orderforms_content');

/**
 * Add grip designs endpoint
 * NOTE: Re-enabled for endpoint registration, but content is handled by plugin
 */
function twintack_add_grip_designs_endpoint() {
    add_rewrite_endpoint('grip-designs', EP_ROOT | EP_PAGES);
}
add_action('init', 'twintack_add_grip_designs_endpoint');

/**
 * TwinTack 404 Intelligent Redirect System
 * 
 * Analyzes the requested URL and suggests relevant pages based on common patterns
 */
function twintack_get_404_suggestions($current_url, $url_parts) {
    $suggestions = array();
    
    // Clean URL for analysis (remove query parameters)
    $clean_url = strtok($current_url, '?');
    $clean_url = strtolower($clean_url);
    
    // Define URL patterns and their suggestions
    $patterns = array(
        // Sport-related patterns
        array(
            'patterns' => array('baseball', 'bat', 'batting'),
            'suggestion' => array(
                'url' => '/baseball/',
                'title' => 'Baseball Section',
                'description' => 'Browse our baseball grips and accessories',
                'icon' => file_get_contents(get_template_directory() . '/baseball-icon-3.svg')
            )
        ),
        array(
            'patterns' => array('fishing', 'fish', 'rod', 'reel'),
            'suggestion' => array(
                'url' => '/fishing/',
                'title' => 'Fishing Section',
                'description' => 'Browse our fishing grips and accessories',
                'icon' => file_get_contents(get_template_directory() . '/fishing-icon-3.svg')
            )
        ),
        
        // Product-related patterns
        array(
            'patterns' => array('shop', 'store', 'product', 'buy', 'purchase'),
            'suggestion' => array(
                'url' => '/shop/',
                'title' => 'Shop All Products',
                'description' => 'Browse our complete product catalog',
                'icon' => '🛒'
            )
        ),
        array(
            'patterns' => array('custom', 'design', 'personalize', 'configurator'),
            'suggestion' => array(
                'url' => '/twintack-custom-grips/',
                'title' => 'Custom Grips',
                'description' => 'Design your own custom grip',
                'icon' => '🎨'
            )
        ),
        
        // Account-related patterns
        array(
            'patterns' => array('account', 'profile', 'dashboard', 'orders'),
            'suggestion' => array(
                'url' => '/my-account/',
                'title' => 'My Account',
                'description' => 'Access your account dashboard',
                'icon' => '👤'
            )
        ),
        array(
            'patterns' => array('login', 'signin', 'register', 'signup'),
            'suggestion' => array(
                'url' => '/login/',
                'title' => 'Login/Register',
                'description' => 'Sign in to your account or create a new one',
                'icon' => '🔐'
            )
        ),
        array(
            'patterns' => array('cart', 'checkout'),
            'suggestion' => array(
                'url' => '/cart/',
                'title' => 'Shopping Cart',
                'description' => 'View your cart and checkout',
                'icon' => '🛒'
            )
        ),
        
        // Company-related patterns
        array(
            'patterns' => array('about', 'company', 'story', 'twintack'),
            'suggestion' => array(
                'url' => '/twintack/',
                'title' => 'About TwinTack',
                'description' => 'Learn about our company and story',
                'icon' => 'ℹ️'
            )
        ),
        array(
            'patterns' => array('contact', 'support', 'help'),
            'suggestion' => array(
                'url' => '/contact/',
                'title' => 'Contact Us',
                'description' => 'Get in touch with our team',
                'icon' => '📞'
            )
        ),
        
        // Wholesale patterns
        array(
            'patterns' => array('wholesale', 'bulk', 'dealer'),
            'suggestion' => array(
                'url' => '/login/',
                'title' => 'Wholesale Login',
                'description' => 'Access wholesale pricing and order forms',
                'icon' => '💼'
            )
        )
    );
    
    // Check for pattern matches
    foreach ($patterns as $pattern_group) {
        foreach ($pattern_group['patterns'] as $pattern) {
            if (strpos($clean_url, $pattern) !== false) {
                $suggestions[] = $pattern_group['suggestion'];
                break; // Avoid duplicate suggestions from same group
            }
        }
    }
    
    // Check for common old WordPress patterns
    if (strpos($clean_url, 'wp-') !== false) {
        $suggestions[] = array(
            'url' => '/shop/',
            'title' => 'Shop Products',
            'description' => 'Browse our product catalog',
            'icon' => '🛒'
        );
        $suggestions[] = array(
            'url' => '/login/',
            'title' => 'Admin/Login',
            'description' => 'Access your account',
            'icon' => '🔐'
        );
    }
    
    // Check for category patterns
    if (strpos($clean_url, 'category') !== false || strpos($clean_url, 'cat') !== false) {
        $suggestions[] = array(
            'url' => '/shop/',
            'title' => 'Shop by Category',
            'description' => 'Browse products by category',
            'icon' => '📂'
        );
    }
    
    // Check for blog patterns
    if (strpos($clean_url, 'blog') !== false || strpos($clean_url, 'news') !== false || strpos($clean_url, 'post') !== false) {
        $suggestions[] = array(
            'url' => '/twintack/',
            'title' => 'Company Information',
            'description' => 'Learn more about TwinTack',
            'icon' => 'ℹ️'
        );
    }
    
    // Remove duplicates based on URL
    $unique_suggestions = array();
    $seen_urls = array();
    foreach ($suggestions as $suggestion) {
        if (!in_array($suggestion['url'], $seen_urls)) {
            $unique_suggestions[] = $suggestion;
            $seen_urls[] = $suggestion['url'];
        }
    }
    
    // Limit to maximum 4 suggestions for good UX
    return array_slice($unique_suggestions, 0, 4);
}

/**
 * Log 404 errors for analysis (optional - can help identify common missing pages)
 */
function twintack_log_404_errors() {
    if (is_404() && WP_DEBUG_LOG) {
        $current_url = $_SERVER['REQUEST_URI'];
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Direct access';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
        
        error_log("TwinTack 404 Error - URL: {$current_url} | Referer: {$referer} | User Agent: " . substr($user_agent, 0, 100));
    }
}
add_action('wp', 'twintack_log_404_errors');

/**
 * Add grip designs to account menu items
 * NOTE: Re-enabled for menu item, but content is handled by plugin
 */
function twintack_add_grip_designs_menu_item($items) {
    $new_items = array();
    
    foreach ($items as $key => $value) {
        $new_items[$key] = $value;
        if ($key === 'dashboard') {
            $new_items['grip-designs'] = __('My Grip Designs', 'twintack2025');
        }
    }
    
    return $new_items;
}
add_filter('woocommerce_account_menu_items', 'twintack_add_grip_designs_menu_item', 20);

/**
 * Get grip design status label based on WordPress status
 * This function is used by the My Account template
 */
function twintack_get_grip_status_label($status) {
    $status_map = array(
        'draft'     => 'Artwork Pending',
        'pending'   => 'Pending Review', 
        'publish'   => 'Artwork Approved',
        'private'   => 'Internal Review',
        'future'    => 'Scheduled'
    );
    
    return isset($status_map[$status]) ? $status_map[$status] : ucfirst($status);
}

/**
 * Register grip designs endpoint content
 * NOTE: Only runs if plugin hasn't already loaded content
 */
function twintack_grip_designs_endpoint_content() {
    // Only load if plugin hasn't already provided content
    if (!defined('TWINTACK_GRIP_CONTENT_LOADED')) {
        wc_get_template('myaccount/grip-designs.php');
    }
}
add_action('woocommerce_account_grip-designs_endpoint', 'twintack_grip_designs_endpoint_content', 10);

// Note: Grip design post type is registered by the TwinTack Grip Manager plugin

/**
 * Legacy Custom Grip Product Functionality Moved
 * 
 * The volume pricing and quantity restriction functionality has been moved
 * to the TwinTack Grip Manager plugin for better configurability.
 * 
 * To configure volume pricing:
 * 1. Go to WordPress Admin > Grip Designs > Volume Pricing
 * 2. Select your custom grip product
 * 3. Configure quantity restrictions and pricing tiers
 * 
 * The new system supports:
 * - Configurable product selection (not hardcoded to ID 1196)
 * - Flexible quantity minimums and steps
 * - Multiple pricing tiers
 * - Fixed dollar discounts or percentage discounts
 * - Individual product settings or global settings
 */

/**
 * TwinTack Make.com Integration Configuration
 * 
 * These hooks configure the webhook URLs for Make.com integration.
 * The TwinTack Grip Manager plugin will use these URLs to send data to Make.com scenarios.
 */

// Configure webhook URL for customer feedback events and production approval
add_filter('grip_customer_feedback_webhook_url', function() {
    return 'https://hook.us2.make.com/lmac9y2igw43gy8flqsois16lmy92o9p';
});

// Configure webhook URL for production approval events (when grip moves to "ready for production")
add_filter('grip_production_approval_webhook_url', function() {
    return 'https://hook.us2.make.com/lmac9y2igw43gy8flqsois16lmy92o9p';
});

// Optional: Add webhook for grip design updates (when admin updates via Monday.com API)
add_action('grip_design_updated', function($grip_id, $updated_fields) {
    $webhook_url = 'https://hook.us2.make.com/lmac9y2igw43gy8flqsois16lmy92o9p';
    
    if (!empty($webhook_url)) {
        $webhook_data = array(
            'grip_id' => $grip_id,
            'updated_fields' => $updated_fields,
            'site_url' => get_site_url(),
            'timestamp' => current_time('c'),
            'event_type' => 'grip_design_updated'
        );
        
        // Send webhook
        wp_remote_post($webhook_url, array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($webhook_data),
            'timeout' => 15,
        ));
        
        // Debug logging
        if (WP_DEBUG) {
            error_log('Sent grip design update webhook: ' . json_encode($webhook_data));
        }
    }
}, 10, 2);

// Admin notice to remind about webhook configuration
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $customer_webhook_url = apply_filters('grip_customer_feedback_webhook_url', '');
    $production_webhook_url = apply_filters('grip_production_approval_webhook_url', '');
    
    if (empty($customer_webhook_url) || empty($production_webhook_url)) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong>TwinTack Make.com Integration:</strong> Please configure your Make.com webhook URLs in the theme's functions.php file to enable grip design notifications.</p>
        </div>
        <?php
    }
});

/**
 * Move WooCommerce breadcrumb to product summary
 * Places breadcrumb inside the product summary div above the product title
 */
function twintack_move_product_breadcrumb() {
    // Only modify on single product pages
    if (is_product()) {
        // Remove breadcrumb from its default location
        remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);
        
        // Add breadcrumb to product summary with priority 1 (before product title which has priority 5)
        add_action('woocommerce_single_product_summary', 'woocommerce_breadcrumb', 1);
    }
}
add_action('template_redirect', 'twintack_move_product_breadcrumb');

/**
 * BLOG ROLL FUNCTIONALITY MOVED TO TEMPLATE
 * The blog roll is now implemented as a direct template part in:
 * - template-parts/content-blog-roll.php (template)
 * - front-page.php (includes the template)
 * - css/components/blog-roll.css (styling)
 * 
 * This approach avoids WordPress content filtering issues that
 * were stripping inline styles from shortcode output.
 */

/**
 * CSS for blog roll is loaded via main.css import:
 * @import 'components/blog-roll.css';
 * 
 * No additional enqueuing needed since it's part of template output.
 */

/**
 * Include Grip Configurator Enhancements
 * Adds registration options for non-logged-in users on the grip configurator page
 */
require get_template_directory() . '/inc/class-twintack-grip-configurator.php';