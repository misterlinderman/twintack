<?php

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

function ced_amazon_time_elapsed_string( $datetime, $full = false ) {
	$now  = new DateTime();
	$ago  = new DateTime( $datetime );
	$diff = $now->diff( $ago );

	$diff->w  = floor( $diff->d / 7 );
	$diff->d -= $diff->w * 7;

	$string = array(
		'y' => 'year',
		'm' => 'month',
		'w' => 'week',
		'd' => 'day',
		'h' => 'hour',
		'i' => 'minute',
		's' => 'second',
	);
	foreach ( $string as $k => &$v ) {
		if ( $diff->$k ) {
			$v = $diff->$k . ' ' . $v . ( $diff->$k > 1 ? 's' : '' );
		} else {
			unset( $string[ $k ] );
		}
	}

	if ( ! $full ) {
		$string = array_slice( $string, 0, 1 );
	}
	return $string ? implode( ', ', $string ) . ' ago' : 'just now';
}


function ced_amazon_nestdiv( $woo_store_categories = array(), $current_amazon_profile = array(), $depth = 0, $amazon_wooCategories = array() ) {

	foreach ( $woo_store_categories as $key => $value ) {
		$selected = '';
		if ( ! empty( $current_amazon_profile ) ) {
			$woo_cat = json_decode( $current_amazon_profile['wocoommerce_category'], true );
			if ( isset( $woo_cat ) && in_array( $value->term_id, $woo_cat ) ) {

				$selected = 'selected';
			}
		}

		$cat_name = $value->name;
		$cat_name = ced_amazon_categories_tree( $value, $cat_name );

		if ( ! in_array( $value->term_id, $amazon_wooCategories ) ) {
			?>
			<option id="<?php echo esc_attr( $value->term_id ); ?>" value="<?php echo esc_attr( $value->term_id ); ?>" <?php echo esc_attr( $selected ); ?> ><?php echo esc_attr( $cat_name ); ?></option>
			<?php
		} else {
			if ( empty( $current_amazon_profile ) ) {
				$woo = array();
			} else {
				$woo = json_decode( $current_amazon_profile['wocoommerce_category'], true );
			}
			if ( isset( $woo ) && in_array( $value->term_id, $woo ) ) {
				?>
				<option id="<?php echo esc_attr( $value->term_id ); ?>" value="<?php echo esc_attr( $value->term_id ); ?>" <?php echo esc_attr( $selected ); ?> ><?php echo esc_attr( $cat_name ); ?></option>
				<?php
			}
		}

		if ( isset( $value->child_categories[0] ) ) {
			ced_amazon_nestdiv( $value->child_categories, $current_amazon_profile, ( $depth + 1 ), $amazon_wooCategories );
		}
	}
}

function ced_amazon_get_categories_hierarchical( $args = array() ) {

	if ( ! isset( $args['parent'] ) ) {
		$args['parent'] = 0;
	}

	$categories = get_categories( $args );
	foreach ( $categories as $key => $category ) :
		$args['parent']                       = $category->term_id;
		$categories[ $key ]->child_categories = ced_amazon_get_categories_hierarchical( $args );
	endforeach;

	return $categories;
}



function get_details() {

	$ced_unified_plan_details = get_transient( 'ced_unified_plan_details' );
	if ( $ced_unified_plan_details ) {
		return $ced_unified_plan_details; 
	}

	require_once CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-billing-apis.php';

	$ced_billing_instance  = new Billing_Apis();
	$current_plan_response = $ced_billing_instance->getAmazonPlanById( );

	if ( isset( $current_plan_response['status'] ) && $current_plan_response['status'] ) {

		$responseBody = isset( $current_plan_response['data'] ) && isset( $current_plan_response['data'][0] ) ? $current_plan_response['data'][0] : array() ;
		if ( !empty( $responseBody ) ) {
			$subscriptionStatus = isset( $responseBody['status'] ) ? $responseBody['status'] : '';
		} else {
			$subscriptionStatus = '';
		}

		$allowed_products = isset( $responseBody['allowed_products'] ) ? $responseBody['allowed_products'] : '';
		$allowed_orders   = isset( $responseBody['allowed_orders'] ) ? $responseBody['allowed_orders'] : '';
		$woo_response     = isset( $responseBody['data'] ) ? $responseBody['data'] : '{}';
		$woo_response     = json_decode( $woo_response, true );
		$end_date         = isset( $woo_response['end_date'] ) ? $woo_response['end_date'] : '' ;
			
		$data = array(
			'plan_status' => $subscriptionStatus,
			'end_date'    => $end_date,
			'allowed_products' => $allowed_products,
			'allowed_orders' => $allowed_orders
		);

		set_transient( 'ced_unified_plan_details', $data, 3600 );
		return $data;

	} else {
		return array(
			'plan_status' => '',
			'end_date'    => '',
		);
	}


}


function ced_handle_amz_success_resp( $message = array() ) {

	return array(
		'success' => true, 
		'response' => array(
			'messages' => $message 
		) 
	); 

}

function ced_handle_amz_failure_resp( $message = array() ) {

	return array(
		'success' => true, 
		'errors' => array(
			'messages' => $message  
		) 
	); 

}


function ced_woo_timestamp( $format = 'Y-m-d H:i:s' ) {

	$current_offset = get_option( 'gmt_offset' );
	$tzstring       = get_option( 'timezone_string' );

	$check_zone_info = true;

	// Remove old Etc mappings. Fallback to gmt_offset.
	if ( str_contains( $tzstring, 'Etc/GMT' ) ) {
		$tzstring = '';
	}

	if ( empty( $tzstring ) ) {

		$check_zone_info = false;
		if ( 0 == $current_offset ) {

			$tzstring                     = 'UTC';
			$target_timezone              = new DateTimeZone( $tzstring );
			$current_time_target_timezone = new DateTime( 'now', $target_timezone );

		} elseif ( $current_offset < 0 ) {

			$tzstring                       = 'UTC';
			$target_timezone_offset_seconds = $current_offset * 3600;

			$current_time_utc             = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
			$current_time_target_timezone = $current_time_utc->modify( "$target_timezone_offset_seconds seconds" );

		} else {
			$tzstring                       = 'UTC';
			$target_timezone_offset_seconds = $current_offset * 3600;

			$current_time_utc             = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
			$current_time_target_timezone = $current_time_utc->modify( "+$target_timezone_offset_seconds seconds" );

		}
	} else {

		$target_timezone              = new DateTimeZone( $tzstring );
		$current_time_target_timezone = new DateTime( 'now', $target_timezone );

	}

	$formatted_time = $current_time_target_timezone->format( $format );
	return ! empty( $formatted_time ) ? $formatted_time : gmdate( $format );
}


function ced_woo_datetime_from_strtotime( $strtotime, $format = 'Y-m-d H:i:s' ) {

	$current_offset = get_option( 'gmt_offset' );
	$tzstring       = get_option( 'timezone_string' );

	$check_zone_info = true;

	// Remove old Etc mappings. Fallback to gmt_offset.
	if ( str_contains( $tzstring, 'Etc/GMT' ) ) {
		$tzstring = '';
	}

	if ( empty( $tzstring ) ) {

		$check_zone_info = false;
		if ( 0 == $current_offset ) {

			$tzstring                     = 'UTC';
			$target_timezone              = new DateTimeZone( $tzstring );
			$current_time_target_timezone = new DateTime(  '@' . $strtotime, $target_timezone );

		} elseif ( $current_offset < 0 ) {

			$tzstring                       = 'UTC';
			$target_timezone_offset_seconds = $current_offset * 3600;

			$current_time_utc             = new DateTime(  '@' . $strtotime, new DateTimeZone( 'UTC' ) );
			$current_time_target_timezone = $current_time_utc->modify( "$target_timezone_offset_seconds seconds" );

		} else {
			$tzstring                       = 'UTC';
			$target_timezone_offset_seconds = $current_offset * 3600;

			$current_time_utc             = new DateTime(  '@' . $strtotime, new DateTimeZone( 'UTC' ) );
			$current_time_target_timezone = $current_time_utc->modify( "+$target_timezone_offset_seconds seconds" );

		}
	} else {

		$target_timezone              = new DateTimeZone( $tzstring );
		$current_time_target_timezone = new DateTime(  '@' . $strtotime, $target_timezone );

	}

	$formatted_time = $current_time_target_timezone->format( $format );
	return ! empty( $formatted_time ) ? $formatted_time : gmdate( $format );

}


/**
 * Function to add markup and rounding-off to given price
 * 
 */
function ced_calculate_markup_price( $markup_type = '', $base_price = 0, $markup_value = 0, $rounding_option = '' ) {
	
	if ( 'Fixed_Increased' == $markup_type ) {
		$markup_price = (float) $base_price + (float) $markup_value;
	} elseif ( 'Fixed_Decreased' == $markup_type ) {
		$markup_price = (float) $base_price - (float) $markup_value;
	} elseif ( 'Percentage_Increased' == $markup_type ) {
		$markup_price = ( ( ( (float) $base_price * (float) $markup_value ) / 100 ) + (float) $base_price );
	} elseif ( 'Percentage_Decreased' == $markup_type ) {
		$markup_price = ( (float) $base_price ) - ( ( (float) $base_price * (float) $markup_value ) / 100 );
	} else {
		$markup_price = (float) $base_price;
	}

	return ced_roundoff_numbers( $markup_price, $rounding_option );

}


function ced_roundoff_numbers( $markup_price, $rounding_option ) {
	switch ( $rounding_option ) {

		// Nearest Higher
		case 'higherWholeNumber':
			$markup_price = ceil( $markup_price );
			break;

		case 'higherEndWith9':
			$markup_price = ( $markup_price > floor($markup_price / 10) * 10 + 9 )
				? floor($markup_price / 10 + 1) * 10 + 9
				: floor($markup_price / 10) * 10 + 9;
			break;

		case 'higherEndWith10':
			$markup_price = ( $markup_price > floor($markup_price / 10) * 10 + 10 )
				? floor($markup_price / 10 + 1) * 10 + 10
				: floor($markup_price / 10) * 10 + 10;
			break;

		case 'higherEndWith0.49':
			$base         = floor($markup_price);
			$markup_price = ( $markup_price > $base + 0.49 )
				? $base + 1 + 0.49
				: $base + 0.49;
			break;

		case 'higherEndWith0.99':
			$base         = floor($markup_price);
			$markup_price = ( $markup_price > $base + 0.99 )
				? $base + 1 + 0.99
				: $base + 0.99;
			break;

		// Nearest Lower
		case 'lowerWholeNumber':
			$markup_price = floor( $markup_price );
			break;

		case 'lowerEndWith9':
			$block  = floor($markup_price / 10);
			$target = $block * 10 + 9;

			if ($markup_price < $target) {
				$target = ( $block - 1 ) * 10 + 9;
			}

			$markup_price = $target;
			break;

		case 'lowerEndWith10':
			$markup_price = ( $markup_price < floor($markup_price / 10) * 10 + 10 )
				? floor($markup_price / 10) * 10 + 10
				: floor($markup_price / 10 - 1) * 10 + 10;
			break;

		case 'lowerEndWith0.49':
			$base         = floor($markup_price);
			$markup_price = ( $markup_price < $base + 0.49 )
				? $base - 1 + 0.49
				: $base + 0.49;
			break;

		case 'lowerEndWith0.99':
			$base         = floor($markup_price);
			$markup_price = ( $markup_price < $base + 0.99 )
				? $base - 1 + 0.99
				: $base + 0.99;
			break;

		default:
			break;
	}

	return round($markup_price, 2);
}
 
if ( ! function_exists( 'ced_amazon_categories_tree' ) ) {
	function ced_amazon_categories_tree( $value, $cat_name ) {
		if ( 0 != $value->parent ) {
			$parent_id = $value->parent;
			$sbcatch2  = get_term( $parent_id );
			$cat_name  = $sbcatch2->name . ' --> ' . $cat_name;
			if ( 0 != $sbcatch2->parent ) {
				$cat_name = ced_amazon_categories_tree( $sbcatch2, $cat_name );
			}
		}
		return $cat_name;
	}
}


if ( ! function_exists( 'ced_get_navigation_url' ) ) {
	function ced_get_navigation_url( $channel = 'home', $query_args = array() ) {
		if ( ! empty( $query_args ) ) {
			return admin_url( 'admin.php?page=sales_channel&channel=' . $channel . '&' . http_build_query( $query_args ) );
		}
		return admin_url( 'admin.php?page=sales_channel&channel=' . $channel );
	}
}



if ( ! function_exists( 'ced_amazon_get_auth_token' ) ) {
	function ced_amazon_get_auth_token() {

		$subscription_info = get_option( 'ced_mcfw_subscription_details', array() );
		$access_token      = isset( $subscription_info['token'] ) ? $subscription_info['token'] : false;
		
		if ( empty( $access_token ) ) {
			$query_args['domain'] = site_url();
			$query_args['mode']   = get_option( 'set_checkout_mode', '' );
		   
			$url = 'https://api.cedcommerce.com/pricing/getaccesstoken?' . http_build_query( $query_args );

			// Make the remote GET request
			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 30,
				)
			);

			// Check for errors
			if ( is_wp_error( $response ) ) {
				return false;
			}

			// Get the body of the response
			$body     = wp_remote_retrieve_body( $response );
			$response = json_decode( $body, 1 );
			
			if ( isset( $response['access_token'] ) ) {
				$subscription_info['token'] = $response['access_token'];
				$access_token               = $subscription_info['token'];
				update_option( 'ced_mcfw_subscription_details', $subscription_info );
			}
		}

		return $access_token;
	}
}


if ( ! function_exists('ced_remote_validator_access_token') ) {
	function ced_remote_validator_access_token() {
		$url      = 'https://api.cedcommerce.com/pricing/getaccesstoken?domain=' . site_url() . '&marketplace=amazon';
		$args     = array(
			'method'  => 'GET',
			'headers' => array(
				'Product-type' => 'native'
			),
			'timeout' => 30,
		);
		$response = wp_remote_request($url, $args);
		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			update_option( 'ced_amazon_user_created_and_authorised', 'error' );
		} else {
			$response_body          = wp_remote_retrieve_body($response);
			$response               = ! empty( $response_body ) ? json_decode( $response_body, true ) : array();
			$access_token_validator = isset( $response['access_token'] ) ? $response['access_token'] : '';
			if ( ! empty( $access_token_validator ) ) {
				$subscription_info          = get_option( 'ced_mcfw_subscription_details', array() );
				$subscription_info['token'] = $access_token_validator;
				update_option( 'ced_mcfw_subscription_details', $subscription_info );
				update_option( 'ced_amazon_user_created_and_authorised', 'yes' );
			} else {		    	
				update_option( 'ced_amazon_user_created_and_authorised', 'body_error' );
			}
		}
	}
}



function is_valid_amazon_subscription( $details = array() ) {

	$planstatus = isset( $details['plan_status'] ) ? $details['plan_status'] : '';
	$end_date   = isset( $details['end_date'] ) ? $details['end_date'] : '';

	if ( 'active' == $planstatus ) {
	   return true;
	} else {
		return false;
	}
}

function is_multidimensional(array $array): bool {
	return count($array) !== count($array, COUNT_RECURSIVE);
}


if ( ! function_exists( 'ced_remote_request' ) ) {
	function ced_remote_request( $marketplace, $order_ids = array() ) {

		$access_token = ced_amazon_get_auth_token();
		$response     = wp_remote_request( 
			
			CED_LIVE_VALIDATOR . 'v1/orders_count',
			array(
				'method'    => 'PUT',
				'sslverify' => false,
				'body'      =>
				json_encode(
					array(
						'topic'       => 'orders_count',
						'order_ids'   => array_unique( $order_ids ),
						'domain'      => site_url(), 
						'marketplace' => $marketplace,
					)
				),
				'headers'   => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $access_token,
					'Product'       => 'amazon',
					'Product-type'  => 'native'
				),
				array(
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . ced_amazon_get_auth_token(),
					'Product'       => 'multichannel',
				),
			)
		);

		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_body ) {
			$logger  = wc_get_logger();
			$context = array( 'source' => 'mbc_order_count_update' );

			$logger->info( gmdate( 'y-m-d H:i:s' ) . "\n", $context );
			$logger->info( 'Response ' . $response_body . "\n", $context );
			$logger->info( "==================================================\n", $context );
		}

		return $response_body;

	}
}

function ced_import_header() {
	$file = CED_AMAZON_DIRPATH . 'admin/partials/header.php';
	if ( file_exists( $file ) ) {
		require_once $file;
	}
}


function ced_amz_marketplaceid_region_mapping( $mplocation ) {
	
	$list = array(

		'us_ca' => array(
			'marketplace_id' => 'A2EUQ1WTGCTBG2',
			'region'         => 'North America',
			'region_value'   => 'NA',
		),
		'us_mx' => array(
			'marketplace_id' => 'A1AM78C64UM0Y8',
			'region'         => 'North America',
			'region_value'   => 'NA',
		),
		'us' => array(
			'marketplace_id' => 'ATVPDKIKX0DER',
			'region'         => 'North America',
			'region_value'   => 'NA',
		),
		'br' => array(
			'marketplace_id' => 'A2Q3Y263D00KWC',
			'region'         => 'North America',
			'region_value'   => 'NA',
		),

		'in' => array(
			'marketplace_id' => 'A21TJRUUN4KGV',
			'region'       => 'Europe',
			'region_value' => 'EU',
		),
		'uk_de' => array(
			'marketplace_id' => 'A1PA6795UKMFR9',
			'region'       => 'Europe',
			'region_value' => 'EU',
		),
		'uk_es' => array(
			'marketplace_id' => 'A1RKKUPIHCS9HS',
			'region'       => 'Europe',
			'region_value' => 'EU',
		),
		'uk_fr' => array(
			'marketplace_id' => 'A13V1IB3VIYZZH',
			'region'       => 'Europe',
			'region_value' => 'EU',
		),
		'uk' => array(
			'marketplace_id' => 'A1F83G8C2ARO7P',
			'region'       => 'Europe',
			'region_value' => 'EU',
		),
		'uk_it' => array(
			'marketplace_id' => 'APJ6JRA9NG5V4',
			'region'       => 'Europe',
			'region_value' => 'EU',
		),
		'nl' => array(
			'marketplace_id' => 'A1805IZSGTT6HS',
			'region'       => 'Europe',
			'region_value' => 'EU',
		),
		'be' => array(
			'marketplace_id' => 'AMEN7PMS3EDWL',
			'region'       => 'Europe',
			'region_value' => 'EU',
		),
		'se' => array(
			'marketplace_id' => 'A2NODRKZP88ZB9',
			'region'       => 'Europe',
			'region_value' => 'EU',
		),

		'za' => array(
			'marketplace_id' => 'AE08WJ6YKNBMC',
			'region'       => 'Europe',
			'region_value' => 'EU',
		),

		'ie' => array(
			'marketplace_id' => 'A28R8C7NBKEWEA',
			'region'       => 'Europe',
			'region_value' => 'EU',
		),

		'uk_pl' => array(
			'marketplace_id' => 'A1C3SOZRARQ6R3',
			'region'       => 'Europe',
			'region_value' => 'EU',
		),
		'eg' => array(
			'marketplace_id' => 'ARBP9OOSHTCHU',
			'region'       => 'Europe',
			'region_value' => 'EU',
		),
		'tr' => array(
			'marketplace_id' => 'A33AVAJ2PDY3EV',
			'region'       => 'Europe',
			'region_value' => 'EU',
		),
		'sa' => array(
			'marketplace_id' => 'A17E79C6D8DWNP',
			'region'       => 'Europe',
			'region_value' => 'EU',
		),
		'uae' => array(
			'marketplace_id' => 'A2VIGQ35RCS4UG',
			'region'       => 'Europe',
			'region_value' => 'EU',
		),

		'au' => array(
			'marketplace_id' => 'A39IBJ37TRP1C6',
			'region'       => 'Far East region',
			'region_value' => 'FE',
		),
		'sg' => array(
			'marketplace_id' => 'A19VAU5U5O7RUS',
			'region'       => 'Far East region',
			'region_value' => 'FE',
		),
		'jp' => array(
			'marketplace_id' => 'A1VC38T7YXB528',
			'region'       => 'Far East region',
			'region_value' => 'FE',
		)


	);

	return  isset( $list[ $mplocation ] ) ? $list[ $mplocation ] : array();

}

function ced_get_marketplace_id_by_country( $mplocation = '' ) {

	$mp_array = ced_amz_marketplaceid_region_mapping( $mplocation );
	return isset( $mp_array['marketplace_id'] ) ? $mp_array['marketplace_id'] : '';

}

function ced_amz_get_region_by_mp_location( $mplocation ) {

	$mp_array = ced_amz_marketplaceid_region_mapping( $mplocation );
	return strtolower( $mp_array['region_value'] );

}

function get_merchant_id_by_region( $marketplace_id ) {

	$merchant_id          = '';
	$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );
	if ( !empty( $saved_amazon_details ) ) {
		foreach ( $saved_amazon_details  as $seller_id => $seller_data ) {
			if ( $seller_data['marketplace_id'] == $marketplace_id  ) {
				$merchant_id = $seller_data['merchant_id'];
				break;
			}
		}
	}

	return $merchant_id;
}


function ced_amz_get_user_id_by_mrkp_id( $marketplace_id ) {

	$seller_next_shop_id  = '';
	$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );
	if ( !empty( $saved_amazon_details ) ) {
		foreach ( $saved_amazon_details  as $seller_id => $seller_data ) {
			if ( $seller_data['marketplace_id'] == $marketplace_id  ) {
				$seller_next_shop_id = $seller_data['seller_next_shop_id'];
				break;
			}
		}
	}

	return $seller_next_shop_id;
}


function ced_amz_get_seller_id_by_mrkp_id( $mid ) {

	$seller_id            = '';
	$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );

	if ( !empty( $saved_amazon_details ) ) {
		foreach ( $saved_amazon_details as $seller_id => $seller_data_array ) {
			if ( $mid == $seller_data_array['marketplace_id'] ) {
				return $seller_id;
			}
		}
	}
	
	return $seller_id;

}

function ced_get_seller_id_by_merchant( $merchant_id = '' ) {

	$seller_id            = '';
	$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );
	if ( !empty( $saved_amazon_details ) ) {
		foreach ( $saved_amazon_details  as $seller_id => $seller_data ) {
			if ( $seller_data['merchant_id'] == $merchant_id  ) {
				$seller_id = $seller_data['ced_mp_seller_key'];
				break;
			}
		}
	}

	return $seller_next_shop_id;

}

// Function to get the next scheduled time for a specific hook.
function get_next_scheduled_time( $hook, $args = [] ) {
	// Use the Action Scheduler's store to find scheduled actions.
	$store = \ActionScheduler::store();

	// Query for the next instance of the action.
	$next_instance = $store->find_action(
		$hook,
		$args,
		['status' => 'pending', 'orderby' => 'scheduled_date', 'order' => 'ASC']
	);

	if ($next_instance) {
		// Get the scheduled date for the next instance.
		$scheduled_date = $store->get_date($next_instance);
		// return strtotime($scheduled_date->format('Y-m-d H:i:s'));
		return $scheduled_date->format('U'); // Return as a Unix timestamp.
	}

	return null; // Return null if no scheduled action found.
}

// Function to get next time at which schdeuler will run for a region
function ced_amz_get_next_scheduled_time_for_rg( $rg ) {
	// only one scheduler will be active ata a time for a region either inventory, price or common\

	$rgs        = array( 'na', 'eu', 'fe');
	$schudelers = array( 'ced_amazon_inventory_scheduler_job_', 'ced_amazon_price_scheduler_job_', 'ced_amazon_common_prc_inv_scheduler_job_' );

	$schudeled_time = '';
	foreach ( $rgs as $rg ) {
		foreach ( $schudelers as $schudeler ) {
			$time = get_next_scheduled_time( $schudeler . $rg );
			if ( !empty( $time ) ) {
				$schudeled_time = $time;
			}
		}
	}

	return $schudeled_time;

}



function prepare_skus_array( $product_ids = array() ) {

	$SKUs_array = array();

	foreach ( $product_ids as $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			continue; // skip if invalid product
		}

		if ( $product->is_type( 'simple' ) ) {
			$sku = $product->get_sku();
		   
			$SKUs_array[ $sku ] = array(
				'type'         => 'Simple',
				'value'        => '',
				'product_id'   => $product_id,
				'product_sku'  => $sku,
				'product_name' => $product->get_name(),
			);

		} elseif ( $product->is_type( 'variable' ) ) {
			$parent_sku = $product->get_sku();
			$children   = $product->get_children(); // variation IDs

			foreach ( $children as $variation_id ) {
				$variation     = wc_get_product( $variation_id );
				$variation_sku = $variation->get_sku();

				if ( ! $variation_sku ) {
					continue;
				}

				$price_amz = isset( $price_map[ $variation_sku ] ) ? $price_map[ $variation_sku ] : $variation->get_price();

				$SKUs_array[ $variation_sku ] = array(
					'type'         => 'Variation',
					'product_sku'  => $variation_sku,
					'parent_sku'   => $parent_sku,
					'value'        => '',
					'product_id'   => $variation_id,
					'product_name' => $variation->get_name(),
				);
			}
		}
	}

	return $SKUs_array;
}
