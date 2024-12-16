<?php
/**
 * Functions which enhance the theme by hooking into WordPress
 *
 * @package twintack2025
 */

/**
 * Adds custom classes to the array of body classes.
 *
 * @param array $classes Classes for the body element.
 * @return array
 */
function twintack2025_body_classes( $classes ) {
	// Adds a class of hfeed to non-singular pages.
	if ( ! is_singular() ) {
		$classes[] = 'hfeed';
	}

	// Adds a class of no-sidebar when there is no sidebar present.
	if ( ! is_active_sidebar( 'sidebar-1' ) ) {
		$classes[] = 'no-sidebar';
	}

	return $classes;
}
add_filter( 'body_class', 'twintack2025_body_classes' );

/**
 * Add a pingback url auto-discovery header for single posts, pages, or attachments.
 */
function twintack2025_pingback_header() {
	if ( is_singular() && pings_open() ) {
		printf( '<link rel="pingback" href="%s">', esc_url( get_bloginfo( 'pingback_url' ) ) );
	}
}
add_action( 'wp_head', 'twintack2025_pingback_header' );

function get_product_line_header() {
    $category = get_queried_object();
    
    // Check if we're on a product category page and have ACF fields
    if (is_product_category() && function_exists('get_field')) {
        // First check for category-specific header configuration
        if (have_rows('header_configuration', $category)) {
            get_template_part('template-parts/header/header', 'flexible');
            return;
        }
        
        // Fallback to default product line headers
        if (strpos(strtolower($category->name), 'baseball') !== false) {
            get_template_part('template-parts/header/header', 'baseball');
        } elseif (strpos(strtolower($category->name), 'fishing') !== false) {
            get_template_part('template-parts/header/header', 'fishing');
        } else {
            get_template_part('template-parts/header/header', 'default');
        }
    }
}
