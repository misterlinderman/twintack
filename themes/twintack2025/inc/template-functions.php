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

/**
 * Custom header function for product lines
 */
function get_product_line_header() {
    if (is_product_category()) {
        // Use the base header template with category-specific configurations
        get_template_part('template-parts/header/header', 'base');
    }
}

// Function to check if we're using the sport template
function is_sport_template() {
    $template_slug = get_page_template_slug();
    return strpos($template_slug, 'template-sport.php') !== false;
}

/**
 * Enqueue sport page styles
 */
function twintack_enqueue_sport_styles() {
    // Only load on sport template
    if (is_sport_template()) {
        wp_enqueue_style(
            'twintack-sport-style',
            get_template_directory_uri() . '/css/sport-page.css',
            array(),
            '1.0.0'
        );
    }
}
add_action('wp_enqueue_scripts', 'twintack_enqueue_sport_styles');

/**
 * Enqueue additional scripts needed for sport pages
 */
function twintack_enqueue_sport_scripts() {
    // Only load on sport template
    if (is_sport_template()) {
        // Video modal functionality is handled by the TwinTack How-to Videos plugin
    }
}
add_action('wp_enqueue_scripts', 'twintack_enqueue_sport_scripts');

/**
 * Add additional Vimeo embed support in footer
 */
function twintack_vimeo_support() {
    if (is_sport_template()) {
        ?>
        <script>
        // Ensure proper Vimeo embedding for IE/Edge
        document.addEventListener('DOMContentLoaded', function() {
            // Fix for data attributes on play buttons
            document.querySelectorAll('.play-button').forEach(function(button) {
                // Ensure data attributes are accessible
                if (button.hasAttribute('data-video-url')) {
                    const videoUrl = button.getAttribute('data-video-url');
                    const videoTitle = button.getAttribute('data-video-title');
                    
                    // Store as properties as well (for older browsers)
                    button.videoUrl = videoUrl;
                    button.videoTitle = videoTitle;
                    
                    console.log('Play button loaded with URL:', videoUrl);
                }
            });
        });
        </script>
        <?php
    }
}
add_action('wp_footer', 'twintack_vimeo_support', 100);
