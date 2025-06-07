<?php
/**
 * The Template for displaying all single products
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product.php.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 1.6.4
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

get_header();

// Add this code to handle the variation selection
add_action('wp_footer', function() {
    if (isset($_GET['variation_id'])) {
        $variation_id = absint($_GET['variation_id']);
        $variation = new WC_Product_Variation($variation_id);
        if ($variation) {
            $attributes = $variation->get_variation_attributes();
            ?>
            <script>
            jQuery(document).ready(function($) {
                // Wait for variations to be initialized
                setTimeout(function() {
                    // Set each attribute in the form
                    <?php foreach ($attributes as $attribute_name => $attribute_value) : ?>
                    $('select[name="<?php echo esc_js($attribute_name); ?>"]').val('<?php echo esc_js($attribute_value); ?>').trigger('change');
                    <?php endforeach; ?>
                }, 100);
            });
            </script>
            <?php
        }
    }
});

// Check for ACF header first
if (have_rows('header_configuration')) {
    get_template_part('template-parts/header/header', 'flexible');
} else {
    // Fallback to default product header
    get_template_part('template-parts/header/header', 'product');
}

?>
<div class="page-wrapper">
    <main class="main">
        <div class="container">
            <?php
            /**
             * woocommerce_before_main_content hook.
             *
             * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
             * @hooked woocommerce_breadcrumb - 20
             */
            do_action('woocommerce_before_main_content');
            ?>

            <?php while (have_posts()) : ?>
                <?php 
                the_post();
                wc_get_template_part('content', 'single-product');
                ?>
            <?php endwhile; // end of the loop. ?>

            <?php
            /**
             * woocommerce_after_main_content hook.
             *
             * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
             */
            do_action('woocommerce_after_main_content');
            ?>
        </div>
    </main>
</div>

<?php
get_footer(); 