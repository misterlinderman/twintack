<?php
// Add to your child theme's functions.php

// Add variation image swapping
add_action('wp_footer', 'variation_image_script');
function variation_image_script() {
    if (!is_product()) return;
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('.variations_form').on('show_variation', function(event, variation) {
            if (variation && variation.image && variation.image.full_src) {
                // Update main image
                $('.woocommerce-product-gallery__image:first img').attr('src', variation.image.full_src);
                $('.woocommerce-product-gallery__image:first a').attr('href', variation.image.full_src);
                
                // Trigger Flatsome zoom/slider refresh if needed
                $(document).trigger('flatsome-product-gallery-refresh');
            }
        });
    });
    </script>
    <?php
}

// Add custom sorting for variations
add_filter('woocommerce_product_get_default_attributes', 'custom_default_attributes', 10, 2);
function custom_default_attributes($default_attributes, $product) {
    // If we have a preselected variant from the category page
    $preselected = get_query_var('preselected_variant');
    if ($preselected) {
        $variation = wc_get_product($preselected);
        if ($variation) {
            return $variation->get_attributes();
        }
    }
    return $default_attributes;
}
