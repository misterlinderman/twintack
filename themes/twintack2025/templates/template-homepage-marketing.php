<?php
/**
 * Template Name: Marketing Homepage
 * 
 * Alternate homepage template with featured products and banner blocks
 */

if (!defined('ABSPATH')) exit;

get_header();
?>

<main id="primary" class="site-main">
    <?php
    // Display banner blocks
    if (function_exists('TwinTack_Marketing_Banner_Blocks')) {
        $banner_blocks = TwinTack_Marketing_Banner_Blocks::get_instance();
        echo do_shortcode('[twintack_banner_block context="homepage"]');
    }
    
    // Display featured products
    if (function_exists('TwinTack_Marketing_Featured_Products')) {
        $featured_products = TwinTack_Marketing_Featured_Products::get_instance();
        echo do_shortcode('[twintack_featured_products context="homepage" columns="4" limit="8" title="Featured Products"]');
    }
    
    // Display page content if any
    while (have_posts()) :
        the_post();
        ?>
        <div class="entry-content">
            <?php the_content(); ?>
        </div>
        <?php
    endwhile;
    ?>
</main>

<?php
get_footer();

