<?php
/**
 * Featured products grid for Homepage V2.
 *
 * @package twintack2025
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="tt-v2-featured-wrap">
    <?php
    if (shortcode_exists('twintack_featured_products')) {
        echo do_shortcode('[twintack_featured_products context="homepage-v2" columns="4" limit="4" title=""]');
    } elseif (function_exists('wc_get_products')) {
        $products = wc_get_products(
            array(
                'limit'   => 4,
                'status'  => 'publish',
                'orderby' => 'popularity',
            )
        );

        if (!empty($products)) {
            echo '<div class="container"><ul class="products tt-v2-product-grid columns-4">';
            foreach ($products as $product) {
                $post_object = get_post($product->get_id());
                setup_postdata($GLOBALS['post'] = $post_object);
                wc_get_template_part('content', 'product');
            }
            echo '</ul></div>';
            wp_reset_postdata();
        }
    }
    ?>
</section>
