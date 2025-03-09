<?php
/**
 * The template for displaying grip product content within loops
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-product-grip.php.
 *
 * @package TwinTack2025
 */

defined('ABSPATH') || exit;

global $product;

// Ensure visibility
if (empty($product) || !$product->is_visible()) {
    return;
}

// Get product data
$product_name = $product->get_name();
$product_pattern = ''; // You can set this from a custom field

// Try to extract pattern from product name (if applicable)
$name_parts = explode(' - ', $product_name);
if (count($name_parts) > 1) {
    $product_name = $name_parts[0];
    $product_pattern = $name_parts[1];
}

// You can also get pattern from a custom field if you prefer
// $product_pattern = get_post_meta($product->get_id(), 'pattern', true);
?>

<li class="twintack-product-card">
    <div class="twintack-product-image-container">
        <a href="<?php echo esc_url(get_permalink()); ?>" class="twintack-product-rotated">
            <div class="twintack-product-label">
                <div class="twintack-product-name"><?php echo esc_html($product_name); ?></div>
                <?php if (!empty($product_pattern)) : ?>
                    <div class="twintack-product-pattern"><?php echo esc_html($product_pattern); ?></div>
                <?php endif; ?>
            </div>
            <?php 
            // Use the grip-carousel image size specifically for grip products
            echo $product->get_image('grip-carousel', array('class' => 'twintack-product-image')); 
            ?>
        </a>
    </div>
    <div class="twintack-product-info">
        <h2 class="twintack-product-title"><?php echo esc_html($product_name); ?></h2>
        <?php if (!empty($product_pattern)) : ?>
            <div class="twintack-product-pattern-text"><?php echo esc_html($product_pattern); ?></div>
        <?php endif; ?>
        <div class="twintack-product-price"><?php echo $product->get_price_html(); ?></div>
        <?php
        echo apply_filters(
            'woocommerce_loop_add_to_cart_link',
            sprintf(
                '<a href="%s" data-quantity="%s" class="%s" %s>%s</a>',
                esc_url($product->add_to_cart_url()),
                esc_attr(isset($args['quantity']) ? $args['quantity'] : 1),
                esc_attr(isset($args['class']) ? $args['class'] : 'button'),
                isset($args['attributes']) ? wc_implode_html_attributes($args['attributes']) : '',
                esc_html($product->add_to_cart_text())
            ),
            $product
        );
        ?>
    </div>
</li> 