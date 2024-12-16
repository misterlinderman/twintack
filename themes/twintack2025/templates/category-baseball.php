<?php
get_header('baseball');

// Get flexible header
get_product_line_header();
?>

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
    <?php do_action('twintack_before_category_content'); ?>
    
    <div class="container">
        <?php if (have_posts()) : ?>
            <div class="product-grid">
                <?php while (have_posts()) : the_post();
                    wc_get_template_part('content', 'product');
                endwhile; ?>
            </div>
            <?php do_action('twintack_after_shop_loop'); ?>
        <?php else :
            do_action('twintack_no_products_found');
        endif; ?>
    </div>
</div>

<?php get_footer('baseball'); ?> 