<?php
/**
 * Custom Summary Product tabs
 *
 * This template handles Description and Specs tabs in the product summary
 *
 * @package TwinTack2025
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get only description and additional_information tabs
$product_tabs = apply_filters( 'woocommerce_product_tabs', array() );
$summary_tabs = array();

if (isset($product_tabs['description'])) {
    $summary_tabs['description'] = $product_tabs['description'];
}

if (isset($product_tabs['additional_information'])) {
    $summary_tabs['additional_information'] = $product_tabs['additional_information'];
}

if ( ! empty( $summary_tabs ) ) : ?>

	<div class="woocommerce-summary-tabs wc-tabs-wrapper">
		<ul class="tabs wc-tabs" role="tablist">
			<?php foreach ( $summary_tabs as $key => $product_tab ) : ?>
				<li class="<?php echo esc_attr( $key ); ?>_tab" id="summary-tab-title-<?php echo esc_attr( $key ); ?>">
					<a href="#summary-tab-<?php echo esc_attr( $key ); ?>" role="tab" aria-controls="summary-tab-<?php echo esc_attr( $key ); ?>">
						<?php echo wp_kses_post( apply_filters( 'woocommerce_product_' . $key . '_tab_title', $product_tab['title'], $key ) ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php foreach ( $summary_tabs as $key => $product_tab ) : ?>
			<div class="woocommerce-Tabs-panel woocommerce-Tabs-panel--<?php echo esc_attr( $key ); ?> panel entry-content wc-tab" id="summary-tab-<?php echo esc_attr( $key ); ?>" role="tabpanel" aria-labelledby="summary-tab-title-<?php echo esc_attr( $key ); ?>">
				<?php
				if ( isset( $product_tab['callback'] ) ) {
					call_user_func( $product_tab['callback'], $key, $product_tab );
				}
				?>
			</div>
		<?php endforeach; ?>
	</div>

<?php endif; ?> 