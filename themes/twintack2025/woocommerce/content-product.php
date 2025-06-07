<?php
/**
 * The template for displaying product content within loops
 *
 * This template is a custom implementation for TwinTack with vertical product orientation
 * on desktop and horizontal on mobile.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.4.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

// Check if the product is a valid WooCommerce product and ensure its visibility before proceeding.
if ( ! is_a( $product, WC_Product::class ) || ! $product->is_visible() ) {
	return;
}

// Get product attributes
$product_name = $product->get_name();
$product_pattern = '';

// Get pattern attribute if it exists
$pattern_attribute = $product->get_attribute('pa_pattern');
if (!empty($pattern_attribute)) {
    $product_pattern = $pattern_attribute;
}

// If no pattern attribute, try to extract pattern from name
if (empty($product_pattern)) {
    // Assuming product name might be in format "NAME PATTERN"
    $name_parts = explode(' ', $product_name);
    if (count($name_parts) > 1) {
        $product_name = $name_parts[0];
        $product_pattern = end($name_parts);
    }
}

// Get product price
$product_price = $product->get_price_html();

// Get product image
$product_image = wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()), 'full');
$image_url = !empty($product_image) ? $product_image[0] : wc_placeholder_img_src();
?>

<li <?php wc_product_class('twintack-product-card', $product); ?>>
    <div class="twintack-product-image-container">
        <div class="twintack-product-rotated">
            <!-- Product Label -->
            <div class="twintack-product-label">
                <div class="twintack-product-name"><?php echo esc_html($product_name); ?></div>
                <?php if (!empty($product_pattern)) : ?>
                <div class="twintack-product-pattern"><?php echo esc_html($product_pattern); ?></div>
                <?php endif; ?>
            </div>
            
            <!-- Product Image -->
            <a href="<?php echo esc_url($product->get_permalink()); ?>">
                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($product->get_name()); ?>" class="twintack-product-image">
            </a>
        </div>
    </div>
    
    <!-- Product Info -->
    <div class="twintack-product-info">
        <div class="twintack-product-title"><?php echo esc_html($product_name); ?></div>
        <?php if (!empty($product_pattern)) : ?>
        <div class="twintack-product-pattern-text">(<?php echo esc_html($product_pattern); ?>)</div>
        <?php endif; ?>
        <div class="twintack-product-price"><?php echo $product_price; ?></div>
        
        <?php
        // Add to cart button
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
            $product,
            $args = array()
        );
        ?>
    </div>
</li> 