<?php

class Billing_Apis {


	/**
	 * Function to get Amaazon plan by ID
	 *
	 * @param            $id woocommerce contract ID
	 * @since            1.0.0
	 * @return           array
	 */
	public function getAmazonPlanById() {

		// Prepare query arguments
		$args = array(
			'action'  => 'subscription_exist',
			'channel' => 'amazon',
			'domain'  => site_url(),
		);
	
		// Prepare the full API URL
		$url = add_query_arg( $args, 'https://api.cedcommerce.com/woobilling/live/ced_api_request.php' );
	
		// Make the GET request using WordPress HTTP API
		$response = wp_remote_get( $url );
	
		// Check for errors
		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => false,
				'message' => 'Failed to fetch your current plans details. Please try again later or contact support.',
			);
		}
	
		// Decode the response body
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
	
		return $data;

	}
}
