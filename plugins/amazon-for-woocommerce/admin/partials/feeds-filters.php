<?php

if ( ! defined( 'ABSPATH' ) ) {
	die;
}
/**
 * FilterClass.
 *
 * @since 1.0.0
 */
class FilterClass {

	public $seller_id = '';
	public $user_id   = '';

	/**
	 * Class constructor
	 */
	public function __construct() {

		$this->seller_id = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';
		$this->user_id   = isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '';
	}

	/**
	 * Function- filter_by_category.
	 * Used to Apply Filter on Feeds View Page
	 *
	 * @since 1.0.0
	 */
	public function ced_amazon_feed_search_box() {

		if ( isset( $_POST['s'] ) && ! empty( $_POST['s'] ) ) {
			if ( ! isset( $_POST['ced_amazon_feed_filter_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['ced_amazon_feed_filter_nonce'] ), 'ced_amazon_feed_filter_page_nonce' ) ) {
				return;
			}

			$seller_id = str_replace( '|', '%7C', $this->seller_id );

			$current_url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$current_url = $current_url . '&seller_id=' . $seller_id;

			$searchdata      = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
			$searchdata      = ucwords( $searchdata );
			$feed_name_array = array(
				
				'POST_ORDER_FULFILLMENT_DATA'      => 'Order Fulfillment',
				'POST_ORDER_ACKNOWLEDGEMENT_DATA'  => 'Order Acknowledgement',
				
				'JSON_LISTINGS_FEED'               => 'Delete Product',
				'JSON_LISTINGS_FEED_INVENTORY'     => 'Inventory Update',
				'JSON_LISTINGS_FEED_PRICE'         => 'Price Update',
				'JSON_LISTINGS_FEED_IMAGE'         => 'Image Update',
				'JSON_LISTINGS_FEED_RELIST'        => 'Relist Product',
				'JSON_LISTINGS_FEED_PRODUCT_UPLOAD' => 'Product Upload'

			);

			$modsearchdata = array_search( $searchdata, $feed_name_array );

			if ( ! $modsearchdata ) {
				$modsearchdata = $searchdata;
			}

			$modsearchdata = str_replace( ' ', '+', urlencode( $modsearchdata ) );
			wp_safe_redirect( $current_url . '&s=' . $modsearchdata );

		} else {

			$url = ced_get_navigation_url(
				'amazon',
				array(
					'section'   => 'feeds-view',
					'user_id'   => $this->user_id,
					'seller_id' => $this->seller_id,
				)
			);

			wp_safe_redirect( $url );

		}
	}
}
