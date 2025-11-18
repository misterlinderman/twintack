<?php
/**
 * Template Name: Sport
 *
 * Sport page template with product carousel, flexible content, and how-to videos.
 *
 * @package TwinTack2025
 */

get_header();

// Get the current page title to determine the sport
$page_title = strtolower(get_the_title());
$sport = sanitize_title($page_title); // Default to page title as sport slug
?>

<div class="sport-page-content">
    <?php
    // Product Carousel Section
    get_template_part('template-parts/sport/product-carousel');
    
    // Flexible Content Section
    if (have_rows('content_configurations')) {
        get_template_part('template-parts/content', 'flexible');
    }
    
    // How-To Videos Section (grid layout)
    if (function_exists('twintack_htv_display_videos')) {
        twintack_htv_display_videos(array(
            'sport' => $sport,
            'layout' => 'grid',
            'limit' => 6,
            'show_title' => true,
            'title' => 'Latest Videos'
        ));
    }
    ?>
</div>

<?php get_footer(); ?> 