<?php

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

$file = CED_AMAZON_DIRPATH . 'admin/partials/amazonRegions.php';
if ( file_exists( $file ) ) {
	require_once $file;
}

$seller_id = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';
$user_id   = isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : false;


$sellernextShopIds = get_option( 'ced_amazon_remote_shop_ids', array() );

if ( empty( $seller_id ) ) {
	$seller_id = isset( $sellernextShopIds[ $user_id ] ) && isset( $sellernextShopIds[ $user_id ]['ced_mp_seller_key'] ) ? $sellernextShopIds[ $user_id ]['ced_mp_seller_key'] : '';
}

$seller_id = trim($seller_id);
$user_id   = trim($user_id);


// Check account participation
$seller_participation = false;
$participate_accounts = isset( $sellernextShopIds[ $user_id ] ) && isset( $sellernextShopIds[ $user_id ]['marketplaces_participation'] ) ? $sellernextShopIds[ $user_id ]['marketplaces_participation'] : '';


if ( is_array( $participate_accounts ) && $participate_accounts[ $seller_id ] ) {
	$seller_participation = true;
}


if ( isset( $_GET['section'] ) ) {
	$section = isset( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : '';
}


?>

<div class="ced-menu-container">
	<ul class="subsubsub">

			<?php
				$header_contents = array(
					'overview'       => array(
						'label'    => 'Overview',
						'selected' => array( 'overview' ),
						'count'    => 1,
					),
					'settings'       => array(
						'label'    => 'Settings',
						'selected' => array( 'settings' ),
						'count'    => 2,
					),
					'templates-view' => array(
						'label'    => 'Templates',
						'selected' => array( 'templates-view', 'add-new-template' ),
						'count'    => 3,
					),
					'products-view'  => array(
						'label'    => 'Products',
						'selected' => array( 'products-view' ),
						'count'    => 4,
					),
					'orders-view'    => array(
						'label'    => 'Orders',
						'selected' => array( 'orders-view' ),
						'count'    => 5,
					),
					'not-imported-orders-view'    => array(
						'label'    => 'Not-imported orders',
						'selected' => array( 'not-imported-orders-view' ),
						'count'    => 6,
					),
					'queue-view'     => array(
						'label'    => 'Queue',
						'selected' => array( 'queue-view' ),
						'count'    => 7,
					),
					'feeds-view'     => array(
						'label'    => 'Feeds',
						'selected' => array( 'feeds-view', 'feed-view' ),
						'count'    => 8,
					),
					
				);
				
				foreach ( $header_contents as $key => $value ) {

					$url = ced_get_navigation_url(
						'amazon',
						array(
							'section'   => $key,
							'user_id'   => $user_id,
							'seller_id' => $seller_id,
						)
					);

					if (  'not-imported-orders-view' == $key && 'orders-view' !== $section  && 'not-imported-orders-view' !== $section   ) {
						continue;
					}

					if (  'queue' == $key  ) {
						$ced_amz_pending_actions        = get_option( 'ced_amz_pending_actions', array() );
						$ced_amz_seller_pending_actions = isset( $ced_amz_pending_actions[$seller_id ] ) ? $ced_amz_pending_actions[$seller_id ] : array();
						
						
					}

					?>

					<li>
						<a href="<?php echo esc_attr( $url ); ?>" class="
											<?php
											if ( in_array( $section, $value['selected'] ) ) {
												echo 'current';
											}
											?>
							"><?php echo esc_html__( $value['label'], 'amazon-for-woocommerce' ); ?></a> 
						
						<?php
						if ( count( array_keys( $header_contents ) ) > $value['count'] ) {
							echo '|';
						}
						?>
					
					</li>
					<?php
				}

				?>
				

	</ul>

	<div class="ced-right">
		<?php

		$ced_amazon_remote_shop_ids       = get_option( 'ced_amazon_remote_shop_ids', array() );
		$ced_amazon_sellernext_shop_ids   = get_option( 'ced_amazon_sellernext_shop_ids', array() );
		$ced_amazon_accounts_merged_array = $ced_amazon_remote_shop_ids + $ced_amazon_sellernext_shop_ids;

		$current_active_section = isset( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : 'overview';

		if ( ! empty( $ced_amazon_accounts_merged_array ) ) {
			?>
			<select style="min-width: 160px;" id="media-attachment-filters" name="ced_amazon_change_acc" class="attachment-filters ced_amazon_change_acc">
				<?php

				foreach ( $ced_amazon_accounts_merged_array as $sellernextId => $sellernextData ) {

					$current_marketplace_id   = isset( $sellernextData['marketplace_id'] ) ? $sellernextData['marketplace_id'] : '';
					$current_marketplace_name = isset( $ced_amazon_regions_info[ $current_marketplace_id ] ) && isset( $ced_amazon_regions_info[ $current_marketplace_id ]['country-name'] ) ? $ced_amazon_regions_info[ $current_marketplace_id ]['country-name'] : '';

					if ( empty( $current_marketplace_id ) ) {
						continue;
					}

					$selected = '';
						
					if ( $user_id == $sellernextId && $seller_id == $sellernextData['ced_mp_seller_key'] ) {
						$selected = 'selected';
					}


					if ( isset( $sellernextData['ced_amz_current_step'] ) && 3 < $sellernextData['ced_amz_current_step'] ) {

						$url = ced_get_navigation_url(
							'amazon',
							array(
								'section'   => $current_active_section,
								'user_id'   => $sellernextId,
								'seller_id' => $sellernextData['ced_mp_seller_key'],
							)
						);

						?>
							<option value="all" <?php echo esc_attr( $selected ); ?> data-href="<?php echo esc_url( $url ); ?>"><?php echo esc_attr( $current_marketplace_name ); ?></option>
							<?php
					} else {

						$current_step = isset( $sellernextData['ced_amz_current_step'] ) ? $sellernextData['ced_amz_current_step'] : '';
						if ( empty( $current_step ) ) {
							$urlKey = array( 'section' => 'setup-amazon' );
						} elseif ( 1 == $current_step ) {
							$urlKey = array(
								'section' => 'setup-amazon',
								'part'    => 'wizard-options',
							);
						} elseif ( 2 == $current_step ) {
							$urlKey = array(
								'section' => 'setup-amazon',
								'part'    => 'wizard-settings',
							);
						} elseif ( 3 == $current_step ) {
							$urlKey = array(
								'section' => 'setup-amazon',
								'part'    => 'configuration',
							);
						} else {
							$urlKey = array( 'section' => 'overview' );
						}

						$seller_key = isset( $sellernextData['ced_mp_seller_key'] ) ? $sellernextData['ced_mp_seller_key'] : '';
							
						$merged_array = array(
							$urlKey,
							array(
								'user_id'   => $sellernextId,
								'seller_id' => $seller_key
							),
						);

						// $url = ced_get_navigation_url( 'amazon', $merged_array );
						$url = get_admin_url() . 'admin.php?page=sales_channel&channel=amazon&' . http_build_query( $urlKey ) . '&user_id=' . $sellernextId . '&seller_id=' . $seller_key;

						?>
							<option value="all" <?php echo esc_attr( $selected ); ?> data-href="<?php echo esc_url( $url ); ?>"><?php echo esc_attr( $current_marketplace_name ); ?></option>
							<?php
					}
				}

					$url = ced_get_navigation_url(
						'amazon',
						array(
							'section'         => 'setup-amazon',
							'add-new-account' => 'yes',
						)
					);

			?>
				<option value="image" data-href="<?php echo esc_url( $url ); ?>"> + Add New Account</option>
			</select>
			<?php

		}

		?>

	</div>
</div>


<?php

$new_account = isset( $_GET['add-new-account'] ) ? sanitize_text_field( $_GET['add-new-account'] ) : '';

if ( ! $seller_participation && empty( $new_account ) ) {
	?>
	<div class="notice notice-error is-dismissable">
		<p><?php echo esc_html__( "Something went wrong with seller's participation, please check your Amazon seller account!", 'amazon-for-woocommerce' ); ?></p>
	</div>
	<?php
}

?>
