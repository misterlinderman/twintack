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

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function twintack2025_setup() {
	/*
		* Make theme available for translation.
		* Translations can be filed in the /languages/ directory.
		* If you're building a theme based on twintack2025, use a find and replace
		* to change 'twintack2025' to the name of your theme in all the template files.
		*/
	load_theme_textdomain( 'twintack2025', get_template_directory() . '/languages' );

	// Add default posts and comments RSS feed links to head.
	add_theme_support( 'automatic-feed-links' );

	/*
		* Let WordPress manage the document title.
		* By adding theme support, we declare that this theme does not use a
		* hard-coded <title> tag in the document head, and expect WordPress to
		* provide it for us.
		*/
	add_theme_support( 'title-tag' );

	/*
		* Enable support for Post Thumbnails on posts and pages.
		*
		* @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
		*/
	add_theme_support( 'post-thumbnails' );

	// This theme uses wp_nav_menu() in one location.
	register_nav_menus(
		array(
			'menu-1' => esc_html__( 'Primary', 'twintack2025' ),
		)
	);

	/*
		* Switch default core markup for search form, comment form, and comments
		* to output valid HTML5.
		*/
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

	// Set up the WordPress core custom background feature.
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

	// Add theme support for selective refresh for widgets.
	add_theme_support( 'customize-selective-refresh-widgets' );

	/**
	 * Add support for core custom logo.
	 *
	 * @link https://codex.wordpress.org/Theme_Logo
	 */
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 250,
			'width'       => 250,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);

	// Add WooCommerce support
	add_theme_support('woocommerce');
	add_theme_support('wc-product-gallery-zoom');
	add_theme_support('wc-product-gallery-lightbox');
	add_theme_support('wc-product-gallery-slider');
	
	// Register custom nav menus
	register_nav_menus(array(
		'primary' => 'Primary Menu',
		'baseball' => 'Baseball Menu',
		'fishing' => 'Fishing Menu'
	));

	// Add custom image sizes
	add_image_size('product-variant-thumb', 300, 300, true);
}
add_action( 'after_setup_theme', 'twintack2025_setup' );

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function twintack2025_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'twintack2025_content_width', 640 );
}
add_action( 'after_setup_theme', 'twintack2025_content_width', 0 );

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
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
 * Enqueue scripts and styles.
 */
function twintack2025_scripts() {
	// Enqueue the main CSS file
	wp_enqueue_style(
		'twintack2025-main',
		get_template_directory_uri() . '/css/main.css',
		array(),
		filemtime(get_template_directory() . '/css/main.css')
	);
	
	wp_enqueue_style( 'twintack2025-style', get_stylesheet_uri(), array(), _S_VERSION );
	wp_style_add_data( 'twintack2025-style', 'rtl', 'replace' );

	wp_enqueue_script( 'twintack2025-navigation', get_template_directory_uri() . '/js/navigation.js', array(), _S_VERSION, true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	wp_enqueue_script(
		'twintack2025-header',
		get_template_directory_uri() . '/js/header.js',
		array(),
		filemtime(get_template_directory() . '/js/header.js'),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'twintack2025_scripts' );

// Include necessary files after all functions are defined
require_once get_template_directory() . '/inc/custom-header.php';
require_once get_template_directory() . '/inc/template-tags.php';
require_once get_template_directory() . '/inc/template-functions.php';
require_once get_template_directory() . '/inc/customizer.php';
require_once get_template_directory() . '/inc/class-twintack-role-pricing.php';
require_once get_template_directory() . '/inc/class-twintack-custom-products.php';
require_once get_template_directory() . '/inc/class-twintack-product-forms.php';

/**
 * Load Jetpack compatibility file.
 */
if ( defined( 'JETPACK__VERSION' ) ) {
	require_once get_template_directory() . '/inc/jetpack.php';
}

// Add category template support
function twintack_category_template($template) {
    if (is_product_category()) {
        $category = get_queried_object();
        $template_name = '';
        
        if (strpos(strtolower($category->name), 'baseball') !== false) {
            $template_name = 'category-baseball.php';
        } elseif (strpos(strtolower($category->name), 'fishing') !== false) {
            $template_name = 'category-fishing.php';
        }
        
        if ($template_name) {
            $new_template = locate_template("templates/$template_name");
            if (!empty($new_template)) {
                return $new_template;
            }
        }
    }
    return $template;
}
add_filter('template_include', 'twintack_category_template');

// Add to your existing functions.php
require get_template_directory() . '/inc/class-category-customizer.php';
