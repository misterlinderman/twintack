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

// Add tabs in the correct order - Specs first, then Description
if (isset($product_tabs['additional_information'])) {
    $summary_tabs['additional_information'] = $product_tabs['additional_information'];
}

if (isset($product_tabs['description'])) {
    $summary_tabs['description'] = $product_tabs['description'];
}

if ( ! empty( $summary_tabs ) ) : ?>

	<div class="woocommerce-summary-tabs wc-tabs-wrapper">
		<ul class="tabs wc-tabs" role="tablist">
			<?php $first_tab = true; foreach ( $summary_tabs as $key => $product_tab ) : ?>
				<li class="<?php echo esc_attr( $key ); ?>_tab <?php echo $first_tab ? 'active' : ''; ?>" id="summary-tab-title-<?php echo esc_attr( $key ); ?>">
					<a href="#summary-tab-<?php echo esc_attr( $key ); ?>" role="tab" aria-controls="summary-tab-<?php echo esc_attr( $key ); ?>">
						<?php echo wp_kses_post( apply_filters( 'woocommerce_product_' . $key . '_tab_title', $product_tab['title'], $key ) ); ?>
					</a>
				</li>
			<?php $first_tab = false; endforeach; ?>
		</ul>
		<?php $first_panel = true; foreach ( $summary_tabs as $key => $product_tab ) : ?>
			<div class="woocommerce-Tabs-panel woocommerce-Tabs-panel--<?php echo esc_attr( $key ); ?> panel entry-content wc-tab<?php echo $first_panel ? ' active' : ''; ?>" id="summary-tab-<?php echo esc_attr( $key ); ?>" role="tabpanel" aria-labelledby="summary-tab-title-<?php echo esc_attr( $key ); ?>" <?php echo $first_panel ? '' : 'style="display: none;"'; ?>>
				<?php
				if ( isset( $product_tab['callback'] ) ) {
					call_user_func( $product_tab['callback'], $key, $product_tab );
				}
				?>
			</div>
		<?php $first_panel = false; endforeach; ?>
	</div>

<?php endif; ?> 