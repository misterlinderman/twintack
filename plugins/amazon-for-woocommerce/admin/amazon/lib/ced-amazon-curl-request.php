<?php

class Ced_Amazon_Curl_Request {


	public function ced_amazon_get_category( $url, $user_id, $seller_id = '' ) {

		$topic = $url;
		$data  = array(
			'remote_shop_id' => $user_id,
		);

		$response = $this->ced_amazon_serverless_process( $topic, $data, 'GET' );

		if ( isset( $response['body'] ) ) {
			return json_decode( $response['body'], true );
		} else {
			return array(
				'success' => 'false',
				'message' => 'Unable to fetch template details',
			);
		}
	}

	public function fetchProductTemplate( $category_id, $userCountry, $seller_id = '', $marketplace_id = '', $user_id = '' ) {

		// Product flat file template structure json file
		$file_location = 'lib/' . $userCountry . '/' . $category_id . '/json/products_template_json_fields.json';

		$topic = 'get-template?location=' . $file_location;
		$data  = array(
			'remote_shop_id' => $user_id,
		);

		$data_response = $this->ced_amazon_serverless_process( $topic, $data, 'GET' );

		$json_template_data = ( $data_response['body'] );

		$upload_dir     = wp_upload_dir();
		$dirname        = $upload_dir['basedir'] . '/ced-amazon/templates/' . $userCountry . '/' . $category_id;
		$json_file_name = 'products_template_fields.json';

		if ( ! is_dir( $dirname ) ) {
			wp_mkdir_p( $dirname );
		}

		$templateFile = fopen( $dirname . '/' . $json_file_name, 'w' );
			fwrite( $templateFile, $json_template_data );

		fclose( $templateFile );
		chmod( $dirname . '/' . $json_file_name, 0777 );

	}

	public function getMarketplaceParticipations( $user_id ) {

		$topic = 'marketplace';
		$data  = array(
			'remote_shop_id' => $user_id,
		);

		$response = $this->ced_amazon_serverless_process( $topic, $data, 'GET' );
		return json_decode( $response['body'], true );
		
	}



	public function ced_amazon_serverless_process( $topic = '', $data = array(), $optType = 'GET' ) {

		$seller_id = isset( $data['seller_id'] ) ? $data['seller_id'] : '-';

		$url_components = parse_url( $topic );
		$mod_topic      = $url_components['path'];
		$query          = $url_components['query'] ?? '';
		parse_str( $query, $query_params ); 

		$endpoint = CED_LIVE_VALIDATOR . 'v1/remote';

		$site_url = site_url();
		
		if (strpos($endpoint, 'sandbox') == false) {
			// Force HTTPS if it's not already
			if (strpos($site_url, 'http://') === 0) {
				$site_url = 'https://' . substr($site_url, 7);
			}
		}

		$body = array_filter(
			array(
				'topic'       => $mod_topic,
				'body'        => $data,
				'marketplace' => 'amazon',
				'method'      => $optType,
				'domain'      => $site_url,
			
			)
		);
		
		$query_params['shop_id'] = $data['remote_shop_id'];
		
		// if( !empty( $query_params ) ){
			$body['query_params'] = $query_params;
		// }

		$body = wp_json_encode( $body );

		if ( isset( $data['marketplace_id'] ) && is_array( $data['marketplace_id'] ) ) {
			$marketplace_id = isset( $data['marketplace_id'][0] ) ? $data['marketplace_id'][0] : '';
		} elseif ( isset( $data['marketplace_id'] ) && is_string( $data['marketplace_id'] ) ) {
			$marketplace_id = $data['marketplace_id'];
		}

		$access_token = ced_amazon_get_auth_token();

		$options = array(   // $args
			'body'        => $body,
			'headers'     => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $access_token,
				'Product'       => 'amazon',
				'Product-type'  => 'native'
			),
			'timeout'     => 60,
			'sslverify'   => 0,
			'data_format' => 'body',
		);

		$response = wp_remote_post( $endpoint, $options );

		if ( is_array( $response ) && isset( $response['body'] ) ) {
			$body      = json_decode( $response['body'], true );
			$http_code = isset( $body['data'] ) && isset( $body['data']['http_code'] ) ? $body['data']['http_code'] : '200';

			if ( isset( $http_code ) && '200' != $http_code ) {
				if ( file_exists( CED_AMAZON_DIRPATH . 'admin/amazon/lib/class-ced-amazon-logger.php' ) ) {
					require_once CED_AMAZON_DIRPATH . 'admin/amazon/lib/class-ced-amazon-logger.php';

					$loggerInstance = new Class_Ced_Amazon_Logger();
					$loggerInstance->ced_add_log_response_serverless( $seller_id, $body, $topic, time(), $http_code );
				}
			}
		}

		return $response;
	}

	public function ced_search_amz_cat( $user_id, $seller_id, $cat_value ) {

		$category_query_params = array(
			'selected'    => $cat_value,
			'hasChildren' => false,
		);
		$category_data         = array(
			'remote_shop_id' => $user_id,
		);

		$catalog_topic = 'itemsbyean?' . http_build_query( $category_query_params );
		$response      = $this->ced_amazon_serverless_process( $catalog_topic, $category_data, 'GET' );

		if ( is_wp_error( $response ) ) {
			$errorResponse = json_decode( json_encode( $response ), true );
			wp_send_json_error( 'Unable to fetch data. Please try again.' );

		}
		$response_body = json_decode( $response['body'], true );
		$response_data = isset( $response_body['response'] ) ? $response_body['response'] : array();
		$response_data = json_decode( json_encode( $response_data ), true );

		return $response_data;
	}
}
