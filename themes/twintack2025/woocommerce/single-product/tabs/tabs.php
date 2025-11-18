<?php
/**
 * Single Product tabs
 *
 * This template has been modified to not display the standard tabs
 * since they are now handled separately:
 * - Description and Specs are displayed in the summary
 * - Reviews is displayed as standalone content
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// We're not displaying tabs here anymore
// The action hook is kept for compatibility
do_action( 'woocommerce_product_after_tabs' );
