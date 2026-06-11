<?php
/**
 * Template Name: Marketing Homepage V2
 *
 * Conversion-focused homepage layout (Shopify mockup). Does not embed a product PDP block.
 *
 * @package twintack2025
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$page_id = get_the_ID();
$meta    = class_exists('TwinTack_Marketing_Homepage_V2')
    ? TwinTack_Marketing_Homepage_V2::get_homepage_v2_meta($page_id)
    : array();
?>

<main id="primary" class="site-main tt-v2-page">

    <?php get_template_part('template-parts/marketing/v2/hero-carousel'); ?>

    <?php get_template_part('template-parts/marketing/v2/scrolling-marquee', null, array('meta' => $meta)); ?>

    <?php get_template_part('template-parts/marketing/v2/image-collage', null, array('meta' => $meta)); ?>

    <?php
    get_template_part(
        'template-parts/marketing/v2/section-heading',
        null,
        array(
            'heading'   => $meta['best_sellers_heading'] ?? '',
            'variant'   => 'light',
            'align'     => 'center',
        )
    );
    ?>

    <?php get_template_part('template-parts/marketing/v2/featured-products'); ?>

    <?php get_template_part('template-parts/marketing/v2/two-column-cards', null, array('meta' => $meta)); ?>

    <?php get_template_part('template-parts/marketing/v2/ugc-carousel', null, array('meta' => $meta)); ?>

    <?php get_template_part('template-parts/marketing/v2/video-tabs', null, array('meta' => $meta)); ?>

    <?php get_template_part('template-parts/marketing/v2/video-modal'); ?>

    <?php
    while (have_posts()) :
        the_post();
        if (get_the_content()) :
            ?>
            <section class="tt-v2-page-content">
                <div class="container">
                    <div class="entry-content">
                        <?php the_content(); ?>
                    </div>
                </div>
            </section>
            <?php
        endif;
    endwhile;
    ?>

</main>

<?php
get_footer();
