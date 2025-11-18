<?php
/**
 * Template for Baseball category
 */

get_header();
?>

<div class="category-hero baseball">
    <div class="container">
        <h1><?php woocommerce_page_title(); ?></h1>
        <?php do_action('twintack_category_hero'); ?>
    </div>
</div>

<div class="category-navigation">
    <div class="container">
        <?php
        wp_nav_menu(array(
            'theme_location' => 'baseball',
            'container_class' => 'category-menu',
            'menu_class' => 'category-menu-items'
        ));
        ?>
    </div>
</div>

<main id="primary" class="site-main baseball-category">
    <div class="container">
        <div class="product-filters">
            <?php do_action('twintack_before_product_filters'); ?>
            <?php the_widget('WC_Widget_Product_Categories', array(
                'title' => 'Product Categories',
                'hierarchical' => true
            )); ?>
            <?php do_action('twintack_after_product_filters'); ?>
        </div>

        <?php if (have_posts()) : ?>
            <div class="products-grid">
                <?php
                while (have_posts()) :
                    the_post();
                    do_action('twintack_product_loop');
                endwhile;
                ?>
            </div>

            <?php the_posts_navigation(); ?>
        <?php else :
            get_template_part('template-parts/content', 'none');
        endif; ?>
    </div>
</main>

<?php get_footer(); ?> 