
<?php

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

$ced_amazon_remote_shop_ids = get_option( 'ced_amazon_remote_shop_ids', array() );

$part    = isset( $_GET['part'] ) ? sanitize_text_field( $_GET['part'] ) : '';
$user_id = isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '';
$section = isset( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : '';

$details                      = get_details(); 
$is_valid_amazon_subscription = is_valid_amazon_subscription( $details );


if ( isset( $_GET['part'] ) ) {

	$allowed_parts = array( 'wizard-options', 'configuration', 'wizard-settings' );

	/** To handle reauthorisation via change account toggle
	* responsible for case where onboarding is not completed( verify and continue step is completed ) and we are going for reauthorize */ 
	if ( !isset( $ced_amazon_remote_shop_ids[$user_id] ) ) {
		if ( file_exists( CED_AMAZON_DIRPATH . 'admin/partials/setup-amazon.php' ) ) {
			require_once CED_AMAZON_DIRPATH . 'admin/partials/setup-amazon.php';
			wp_die();
		}
	}

	if ( in_array( $part, $allowed_parts ) ) {

		switch ( $part ) {
			case 'wizard-options':
				if ( file_exists( CED_AMAZON_DIRPATH . 'admin/partials/wizard-options.php' ) ) {
					require_once CED_AMAZON_DIRPATH . 'admin/partials/wizard-options.php';
				}
				break;
			case 'configuration':
				if ( file_exists( CED_AMAZON_DIRPATH . 'admin/partials/configuration.php' ) ) {
					require_once CED_AMAZON_DIRPATH . 'admin/partials/configuration.php';
				}
				break;
			case 'wizard-settings':
				if ( file_exists( CED_AMAZON_DIRPATH . 'admin/partials/wizard-settings.php' ) ) {
					require_once CED_AMAZON_DIRPATH . 'admin/partials/wizard-settings.php';
				}
				break;
			default:
				echo '';
		}

		die;
	}

}


$subscriptionVerified = 0;

if ( $is_valid_amazon_subscription ) {
	$subscriptionVerified = 1;
} else {
	$pricing_url = ced_get_navigation_url( 'pricing' );
	wp_safe_redirect( $pricing_url );
}

if ( ! $subscriptionVerified ) {
	$pricing_url = ced_get_navigation_url( 'pricing' );
	wp_safe_redirect( $pricing_url );

} elseif ( isset( $_GET['section'] ) ) {

	$allowed_sections = array( 
		'overview', 'orders-view', 'templates-view', 'products-view','feeds-view', 'feed-view', 'settings', 'amazon-options',
		'plans-view', 'add-new-template', 'setup-amazon', 'not-imported-orders-view', 'queue-view', 'schema-view'
	);

	$section = isset( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : '';

	/*
	* To handle reauthorisation via change account toggle
	* responsible for case where onboarding is completed and we are going for reauthorize 
	*/ 
	if ( 'setup-amazon' !== $section && !isset( $ced_amazon_remote_shop_ids[$user_id] ) ) {
	   $section = 'setup-amazon';
	}


	/** Code will automatically handle where onboarding is not completed ( verify and continue step is yet to complete ) and we are going for reauthorize */

	if ( in_array( $section, $allowed_sections ) ) {
		require_once CED_AMAZON_DIRPATH . 'admin/partials/' . $section . '.php';
	} else {
		require_once CED_AMAZON_DIRPATH . 'admin/partials/overview.php';
	}

} else {

	if ( ! session_id() ) {
		session_start();
	}

	?>

	<div class="ced-amazon-login-dashboard-wrapper">
		<div class="ced-amazon-login-dashboard">
			<div class="ced-amazon-wrap-login">
				<div class="ced-amazon-common-wrap-head">
					<h1><?php echo esc_html__( 'Amazon for WooCommerce', 'amazon-for-woocommerce' ); ?></h1>
				</div>
				<div class="ced-amazon-user-container">
					<div class="ced-amazon-user-holder">

						<?php

						if ( ! empty( $ced_amazon_remote_shop_ids ) && is_array( $ced_amazon_remote_shop_ids ) ) {

							$ced_amz_active_marketplace = get_option( 'ced_amz_active_marketplace' );
							$current_shop               = array();

							if ( ! empty( $ced_amz_active_marketplace ) ) {

								$keys   = array_keys( $ced_amazon_remote_shop_ids );
								$values = array_values( $ced_amazon_remote_shop_ids );

								$user_id  = isset( $ced_amz_active_marketplace['user_id'] ) ? $ced_amz_active_marketplace['user_id'] : '';
								$sellerID = isset( $ced_amazon_remote_shop_ids[ $user_id ] ) && isset( $ced_amazon_remote_shop_ids[ $user_id ]['ced_mp_seller_key'] ) ? $ced_amazon_remote_shop_ids[ $user_id ]['ced_mp_seller_key'] : '';


								$current_shop = array(
									'user_id'   => $user_id,
									'seller_id' => $sellerID,
								);

							} else {

								$lastIndex = count( $ced_amazon_remote_shop_ids ) - 1;

								$keys     = array_keys( $ced_amazon_remote_shop_ids );
								$values   = array_values( $ced_amazon_remote_shop_ids );
								$sellerID = $values[ $lastIndex ]['ced_mp_seller_key'];

								$current_shop = array(
									'user_id'   => $keys[ $lastIndex ],
									'seller_id' => $sellerID,
								);

							}

							if ( 3 < $ced_amazon_remote_shop_ids[ $current_shop['user_id'] ]['ced_amz_current_step'] ) {

								$url = ced_get_navigation_url(
									'amazon',
									array(
										'section'   => 'overview',
										'user_id'   => $current_shop['user_id'],
										'seller_id' => $current_shop['seller_id'],
									)
								);

								wp_safe_redirect( $url );

							} else {

								$current_step = $ced_amazon_remote_shop_ids[ $current_shop['user_id'] ]['ced_amz_current_step'];
								if ( empty( $current_step ) ) {
									$part = '';
								} elseif ( 1 == $current_step ) {
									$part = 'wizard-options';
								} elseif ( 2 == $current_step ) {
									$part = 'wizard-settings';
								} elseif ( 3 == $current_step ) {
									$part = 'configuration';
								}

								$url = ced_get_navigation_url(
									'amazon',
									array(
										'section'   => 'setup-amazon',
										'part'      => $part,
										'user_id'   => $current_shop['user_id'],
										'seller_id' => $current_shop['seller_id'],
									)
								);

								wp_safe_redirect( $url );

							}
						} else {

							$url = ced_get_navigation_url(
								'amazon',
								array(
									'section' => 'setup-amazon',
								)
							);
							wp_safe_redirect( $url );

						}
						?>

					</div>
				</div>
			</div>
		</div>
	</div>

	<?php

}
?>