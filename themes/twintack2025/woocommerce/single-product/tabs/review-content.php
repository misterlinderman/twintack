<?php
/**
 * Display Review content without tabs
 *
 * This template handles reviews as standalone content
 *
 * @package TwinTack2025
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

if ( ! $product ) {
	return;
}

// Get only reviews tab (includes submission form when comments are open).
$product_tabs = apply_filters( 'woocommerce_product_tabs', array() );

if ( ! isset( $product_tabs['reviews'] ) ) {
	return;
}

$review_tab = $product_tabs['reviews'];
?>
    <div class="woocommerce-reviews-section">
        <h2><?php echo wp_kses_post( apply_filters( 'woocommerce_product_reviews_tab_title', $review_tab['title'] ) ); ?></h2>
        <div class="woocommerce-reviews-content">
            <?php
            if ( isset( $review_tab['callback'] ) ) {
                call_user_func( $review_tab['callback'], 'reviews', $review_tab );
            }
            ?>
        </div>
    </div> 