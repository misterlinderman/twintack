<?php
/**
 * Template Name: Marketing Landing Page
 * 
 * Simple targeted marketing landing page template for niche campaigns
 * Includes: Hero image, Banner blocks, Featured products
 */

if (!defined('ABSPATH')) exit;

get_header();

// Get hero images
$hero_desktop = get_post_meta(get_the_ID(), '_twintack_landing_hero_desktop', true);
$hero_mobile = get_post_meta(get_the_ID(), '_twintack_landing_hero_mobile', true);
$hero_title = get_post_meta(get_the_ID(), '_twintack_landing_hero_title', true);
$hero_subtitle = get_post_meta(get_the_ID(), '_twintack_landing_hero_subtitle', true);
?>

<main id="primary" class="site-main twintack-landing-page">
    <?php
    // Hero Section
    if ($hero_desktop || $hero_mobile || $hero_title || $hero_subtitle) :
        ?>
        <section class="twintack-landing-hero">
            <?php if ($hero_desktop || $hero_mobile) : ?>
                <picture class="twintack-landing-hero-image">
                    <?php if ($hero_mobile) : ?>
                        <source media="(max-width: 768px)" srcset="<?php echo esc_url($hero_mobile); ?>">
                    <?php endif; ?>
                    <?php if ($hero_desktop) : ?>
                        <img src="<?php echo esc_url($hero_desktop); ?>" alt="<?php echo esc_attr($hero_title ?: get_the_title()); ?>" />
                    <?php endif; ?>
                </picture>
            <?php endif; ?>
            
            <?php if ($hero_title || $hero_subtitle) : ?>
                <div class="twintack-landing-hero-content">
                    <div class="container">
                        <?php if ($hero_title) : ?>
                            <h1 class="twintack-landing-hero-title"><?php echo esc_html($hero_title); ?></h1>
                        <?php endif; ?>
                        <?php if ($hero_subtitle) : ?>
                            <p class="twintack-landing-hero-subtitle"><?php echo esc_html($hero_subtitle); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
        <?php
    endif;
    
    // Page Content
    while (have_posts()) :
        the_post();
        ?>
        <section class="twintack-landing-content">
            <div class="container">
                <div class="entry-content">
                    <?php the_content(); ?>
                </div>
            </div>
        </section>
        <?php
    endwhile;
    
    // Banner Blocks
    if (function_exists('TwinTack_Marketing_Banner_Blocks')) {
        $banner_blocks = TwinTack_Marketing_Banner_Blocks::get_instance();
        echo do_shortcode('[twintack_banner_block context="landing"]');
    }
    
    // Featured Products
    if (function_exists('TwinTack_Marketing_Featured_Products')) {
        $featured_products = TwinTack_Marketing_Featured_Products::get_instance();
        echo do_shortcode('[twintack_featured_products context="landing" columns="4" limit="8" title="Featured Products"]');
    }
    ?>
</main>

<?php
get_footer();

