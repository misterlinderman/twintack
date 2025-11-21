<?php

if ( ! defined( 'ABSPATH' ) ) {
	die;
}


if ( empty( get_option( 'ced_amazon_remote_shop_ids', array() ) ) ) {
	$connect_to_amazon['will_connect']  = 'block';
	$connect_to_amazon['did_connected'] = 'none';

} else {
	$connect_to_amazon['will_connect']  = 'none';
	$connect_to_amazon['did_connected'] = 'block';
}


$fileAccounts = CED_AMAZON_DIRPATH . 'admin/partials/amazonRegions.php';
if ( file_exists( $fileAccounts ) ) {
	require_once $fileAccounts;
}

$user_id       = isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '';
$seller_id     = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';
$connected     = isset( $_GET['connected'] ) ? sanitize_text_field( $_GET['connected'] ) : false;
$reAuthorising = isset( $_GET['reAuthorising'] ) ? sanitize_text_field( $_GET['reAuthorising'] ) : false;


$seller_id_array = explode( '|', $seller_id );
$mp_location     = isset( $seller_id_array[0] ) ? $seller_id_array[0] : '';
$merchant_id     = isset( $seller_id_array[1] ) ? $seller_id_array[1] : '';

$ced_amazon_sellernext_shop_ids    = get_option( 'ced_amazon_sellernext_shop_ids', array() );
$ced_amzon_configuration_validated = get_option( 'ced_amzon_configuration_validated', array() );


$ced_config_data_holder = array();

$marketplace_id = ced_get_marketplace_id_by_country( $mp_location );

if ( $reAuthorising ) {

	$req_user_id = '';
	foreach ( $ced_amazon_sellernext_shop_ids as $remote_shop_id => $sellernext_array ) {
		if ( $sellernext_array['marketplace_id'] == $marketplace_id ) {
			$req_user_id = $remote_shop_id;
			break;
		}
	}


	$ced_config_data_holder     = isset( $ced_amzon_configuration_validated[ $seller_id ] ) ? $ced_amzon_configuration_validated[ $seller_id ] : array();
	$ced_sellernext_data_holder = isset( $ced_amazon_sellernext_shop_ids[ $req_user_id ] ) ? $ced_amazon_sellernext_shop_ids[ $req_user_id ] : array();


	if ( isset( $ced_amazon_sellernext_shop_ids[ $req_user_id ] ) ) {
		unset( $ced_amazon_sellernext_shop_ids[ $req_user_id ] );
	}
	if ( isset( $ced_amzon_configuration_validated[ $seller_id ] ) ) {
		unset( $ced_amzon_configuration_validated[ $seller_id ] );
	}

} 


$ced_amazon_remote_shop_ids = get_option( 'ced_amazon_remote_shop_ids', array() );

if ( $connected ) {

	if ( ! empty( $user_id ) && ! empty( $seller_id ) ) {

		if ( $reAuthorising ) {
		   update_option( 'ced_amazon_sellernext_shop_ids', $ced_amazon_sellernext_shop_ids );
		}
		// $marketplace_id = !empty( $ced_config_data_holder['marketplace_id'] ) ? $ced_config_data_holder['marketplace_id'] : $ced_sellernext_data_holder['marketplace_id'] ;

		if ( isset( $ced_amazon_remote_shop_ids[ $user_id ] ) ) {
			unset( $ced_amazon_remote_shop_ids[ $user_id ] );
			update_option( 'ced_amazon_remote_shop_ids', $ced_amazon_remote_shop_ids );
		}

		if ( ! isset( $ced_amazon_remote_shop_ids[ $user_id ] ) ) {

			$ced_amazon_remote_shop_ids[ $user_id ] = array(
				'marketplace_id'    => $marketplace_id,
				'ced_mp_name'       => $ced_amazon_regions_info[ $marketplace_id ]['shop-name'],
				'ced_mp_seller_key' => $seller_id,
			);
			update_option( 'ced_amazon_remote_shop_ids', $ced_amazon_remote_shop_ids );


			$ced_amzon_configuration_validated[ $seller_id ] = array(
				'marketplace_id'      => $marketplace_id,
				'marketplace_region'  => $ced_amazon_regions_info[ $marketplace_id ]['region_value'],
				'country_name'        => $ced_amazon_regions_info[ $marketplace_id ]['country-name'],
				'country_value'       => $ced_amazon_regions_info[ $marketplace_id ]['value'],
				'seller_next_shop_id' => $user_id,
				'merchant_id'         => $merchant_id,
				'ced_mp_name'         => $ced_amazon_regions_info[ $marketplace_id ]['shop-name'],
				'ced_mp_seller_key'   => $seller_id,

			);
			update_option( 'ced_amzon_configuration_validated', $ced_amzon_configuration_validated );

			$params              = array();
			$params['user_id']   = $user_id;
			$params['seller_id'] = $seller_id;

			update_option( 'ced_amz_active_marketplace', $params );

		}

		$connect_to_amazon['will_connect']  = 'none';
		$connect_to_amazon['did_connected'] = 'block';

		$current_amaz_shop_id = $user_id;

	}
} else { 
	$current_amaz_shop_id = '';
}


$part = isset( $_GET['part'] ) ? sanitize_text_field( $_GET['part'] ) : false;
require_once CED_AMAZON_DIRPATH . 'admin/partials/ced-amazon-login.php';
