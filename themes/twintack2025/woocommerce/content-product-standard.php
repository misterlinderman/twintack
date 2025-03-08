<?php
/**
 * The template for displaying standard product content within loops
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-product-standard.php.
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
?>

<li <?php wc_product_class('', $product); ?>>
	<div class="product-image-container">
		<a href="<?php echo esc_url(get_permalink()); ?>" class="product-link">
			<?php 
			// Use the product-grid image size for standard products
			echo $product->get_image('product-grid', array('class' => 'product-image')); 
			?>
		</a>
	</div>
	<div class="product-info">
		<h2 class="woocommerce-loop-product__title"><?php echo esc_html($product_name); ?></h2>
		<div class="product-price"><?php echo $product->get_price_html(); ?></div>
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