<?php
/**
 * Template Name: Marketing Homepage
 * 
 * Alternate homepage template with featured products and banner blocks
 */

if (!defined('ABSPATH')) exit;

get_header();
?>

    <?php
    // Display hero carousel BEFORE main content
    if (class_exists('TwinTack_Marketing_Hero_Carousel')) {
        $hero_carousel = TwinTack_Marketing_Hero_Carousel::get_instance();
        $hero_output = do_shortcode('[twintack_hero_carousel context="homepage"]');
        if (!empty($hero_output)) {
            echo $hero_output;
        }
    }
    ?>

<main id="primary" class="site-main">
    <?php
    // 1. Display featured products from Marketing Plugin
    if (class_exists('TwinTack_Marketing_Featured_Products')) {
        $featured_products = TwinTack_Marketing_Featured_Products::get_instance();
        echo do_shortcode('[twintack_featured_products context="homepage" columns="4" limit="8" title="Featured Products"]');
    }
    
    // 2. Display banner blocks from Marketing Plugin
    if (class_exists('TwinTack_Marketing_Banner_Blocks')) {
        $banner_blocks = TwinTack_Marketing_Banner_Blocks::get_instance();
        echo do_shortcode('[twintack_banner_block context="homepage"]');
    }
    
    // 3. Display ACF content and WordPress editor content
    while (have_posts()) :
        the_post();
        
        // Display WordPress editor content if any
        if (get_the_content()) : ?>
            <section class="page-content-section">
                <div class="container">
                    <div class="entry-content">
                        <?php the_content(); ?>
                    </div>
                </div>
            </section>
        <?php endif;
        
        // Display flexible content layouts if ACF is active
        if (function_exists('have_rows') && have_rows('content_configurations')) :
            get_template_part('template-parts/content-flexible');
        endif;
        
    endwhile;
    ?>
</main>

<?php
get_footer();

