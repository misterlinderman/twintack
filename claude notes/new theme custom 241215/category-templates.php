<?php
// templates/category-baseball.php
get_header('baseball'); // Custom header for baseball
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

<div class="category-content">
    <div class="container">
        <div class="product-filters">
            <?php do_action('twintack_before_product_filters'); ?>
            <?php the_widget('WC_Widget_Product_Categories', array(
                'title' => 'Product Categories',
                'hierarchical' => true
            )); ?>
            <?php do_action('twintack_after_product_filters'); ?>
        </div>

        <div class="product-grid">
            <?php if (have_posts()) : ?>
                <div class="products-header">
                    <?php do_action('twintack_before_shop_loop'); ?>
                </div>

                <div class="products columns-4">
                    <?php while (have_posts()) : the_post(); ?>
                        <?php do_action('twintack_product_loop'); ?>
                    <?php endwhile; ?>
                </div>

                <?php do_action('twintack_after_shop_loop'); ?>
            <?php else : ?>
                <?php do_action('twintack_no_products_found'); ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php get_footer('baseball'); ?>
