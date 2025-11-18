<?php
/**
 * Template part for displaying category products grid
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<main id="primary" class="site-main">
    <div class="container">
        <?php if (have_posts()) : ?>
            <header class="page-header">
                <?php
                the_archive_title('<h1 class="page-title">', '</h1>');
                the_archive_description('<div class="archive-description">', '</div>');
                ?>
            </header>

            <div class="product-filters">
                <?php
                // Add WooCommerce sorting and filtering
                do_action('woocommerce_before_shop_loop');
                ?>
            </div>

            <div class="product-grid">
                <?php
                while (have_posts()) :
                    the_post();
                    wc_get_template_part('content', 'product');
                endwhile;
                ?>
            </div>

            <?php
            do_action('woocommerce_after_shop_loop');
            ?>
        <?php else : ?>
            <?php do_action('woocommerce_no_products_found'); ?>
        <?php endif; ?>
    </div>
</main> 