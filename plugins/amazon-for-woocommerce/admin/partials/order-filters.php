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

	/**
	 * Function- filter_by_category.
	 * Used to Apply Filter on Order Page
	 *
	 * @since 1.0.0
	 */
	public function ced_amazon_order_search_box() {

		$user_id   = isset( $_GET['user_id'] ) ? sanitize_text_field( wp_unslash( $_GET['user_id'] ) ) : '';
		$seller_id = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';
		$section   = isset( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : '';


		if ( isset( $_POST['s'] ) && ! empty( $_POST['s'] ) ) {
			if ( ! isset( $_POST['ced_amazon_order_filter_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['ced_amazon_order_filter_nonce'] ), 'ced_amazon_order_filter_page_nonce' ) ) {
				return;
			}

			$seller_id = str_replace( '|', '%7C', $seller_id );
			
			$current_url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$current_url = $current_url . '&seller_id=' . $seller_id;

			$searchdata = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
			$searchdata = str_replace( ' ', '+', urlencode( $searchdata ) );

			wp_safe_redirect( $current_url . '&s=' . $searchdata );

		} else {

			$url = ced_get_navigation_url(
				'amazon',
				array(
					'section'   => $section,
					'user_id'   => $user_id,
					'seller_id' => $seller_id,
				)
			);
			wp_safe_redirect( $url );
		}
	}
}
