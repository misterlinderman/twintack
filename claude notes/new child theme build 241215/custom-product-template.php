<?php
// Create this file as: flatsome-child/woocommerce/single-product/custom-product.php

/**
 * Custom Product Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add this to functions.php to register the template
function add_custom_product_template($templates) {
    $templates['custom-product'] = 'Custom Product Template';
    return $templates;
}
add_filter('woocommerce_product_custom_templates', 'add_custom_product_template');

// Template Content
global $product;

// Get the associated form ID
$form_id = get_post_meta($product->get_id(), '_gravity_form_id', true);

?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class('custom-product-template', $product); ?>>
    <div class="custom-product-container">
        <div class="product-gallery-col">
            <?php
            /**
             * Hook: woocommerce_before_single_product_summary
             * @hooked woocommerce_show_product_images - 20
             */
            do_action('woocommerce_before_single_product_summary');
            ?>
        </div>

        <div class="product-info-col">
            <?php
            /**
             * Hook: woocommerce_single_product_summary
             * @hooked woocommerce_template_single_title - 5
             * @hooked woocommerce_template_single_price - 10
             * @hooked woocommerce_template_single_excerpt - 20
             */
            do_action('woocommerce_single_product_summary');
            ?>

            <?php if ($form_id && class_exists('GFAPI')) : ?>
                <div class="custom-product-form">
                    <?php 
                    gravity_form($form_id, false, false, false, null, true, 1); 
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php do_action('woocommerce_after_single_product'); ?>
