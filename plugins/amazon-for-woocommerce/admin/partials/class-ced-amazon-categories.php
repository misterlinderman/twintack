<?php


class Ced_Amazon_Get_Categoires {

	public function __construct( $shop_id, $seller_id ) {
		$categories   = $this->ced_amazon_get_categories( $shop_id );
		$productTypes = $this->ced_amazon_get_product_types( $shop_id, $seller_id );

		$this->ced_amazon_render_select( $categories, $productTypes );
	}


	public function ced_amazon_get_categories( $shop_id ) {

		$amzonCurlRequest = CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php';
		if ( file_exists( $amzonCurlRequest ) ) {
			require_once $amzonCurlRequest;
			$amzonCurlRequestInstance = new Ced_Amazon_Curl_Request();

		}

		$contract_data = get_option( 'ced_unified_contract_details', array() );
		$contract_id   = isset( $contract_data['amazon'] ) && isset( $contract_data['amazon']['contract_id'] ) ? $contract_data['amazon']['contract_id'] : '';

		$cat_topic = 'category-all';
		$cat_data  = array(
			'contract_id'    => $contract_id,
			'remote_shop_id' => $shop_id,
		);

		$response = $amzonCurlRequestInstance->ced_amazon_serverless_process( $cat_topic, $cat_data, 'GET' );

		if ( isset( $response['body'] ) ) {
			$response = json_decode( $response['body'], true );
			$response = isset( $response['response'] ) ? $response['response'] : array();

			return $response;
			
		} else {
			// No response from the API
			echo 'No response from the API';
		}
	}

	public function ced_amazon_get_product_types( $shop_id, $seller_id ) {

		$productTypeFlag = 0;
		$mp_array        = explode( '|', $seller_id );
		$mp_location     = isset( $mp_array[0] ) ? $mp_array[0] : '';

		if ( empty( $mp_location ) ) {
			echo json_encode( array(
				'status' => false,
				'message' => 'Marketplace Location not found'
			) ); 
		}
		
		$fld      = CED_AMAZON_DIRPATH . 'admin/amazon/productTypes/';
		$fileName = 'productTypes_' . $mp_location . '.json';

		if ( !is_dir( $fld ) ) {
			wp_mkdir_p( $fld );
		}

		$amzonProductTypes = $fld . $fileName;
		
		if ( file_exists( $amzonProductTypes ) ) {
			$jsonContent = file_get_contents( $amzonProductTypes );
			$response    = !empty($jsonContent) ? json_decode( $jsonContent, true ) : array();
			$response    = array_filter( $response );
			if ( !empty( $jsonContent ) ) {
				$productTypeFlag = 1;
			}
			
		} 

		if ( !$productTypeFlag ) {

			$amzonCurlRequest = CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php';
			if ( file_exists( $amzonCurlRequest ) ) {
				require_once $amzonCurlRequest;
				$amzonCurlRequestInstance = new Ced_Amazon_Curl_Request();

			}

			$cat_topic = 'product-types';
			$cat_data  = array(
				'remote_shop_id' => $shop_id,
			);

			$response = $amzonCurlRequestInstance->ced_amazon_serverless_process( $cat_topic, $cat_data, 'GET' );

			if ( isset( $response['body'] ) ) {
				
				$response = json_decode( $response['body'], true );
				$response = isset( $response['response'] ) ? $response['response'] : array();

				if ( WP_Filesystem() ) {
					global $wp_filesystem;
				}

				if ( $wp_filesystem ) {
					$wp_filesystem->put_contents( $amzonProductTypes, json_encode($response), FS_CHMOD_FILE );
				}
				
				
			} else {
				// No response from the API
				echo 'No response from the API';
				return;
			}

		}

		return $response;


	}


	public function ced_amazon_render_select( $categories, $productTypes ) {

		$html = '';

		/** Search wrapper starts */
		$html .= '<div class="ced-category-search-wrapper">';
		$html .= '</div>';
		/** Search wrapper ends */


		/** Amazon categoty mapping wrapper starts */
		$html .= '<div class="ced-category-mapping-wrapper">';
		$html .= '<div class="ced-category-mapping">';

		$html .= '<input type="hidden" class="ced_amz_cat_name_arr" name="ced_amazon_profile_data[amazon_categories_name]" value="" />';
		$html .= '<input type="hidden" class="ced_primary_category" name="ced_amazon_profile_data[primary_category]" />';
		$html .= '<input type="hidden" class="ced_secondary_category" name="ced_amazon_profile_data[secondary_category]" />';
		$html .= '<input type="hidden" class="ced_browse_category" name="ced_amazon_profile_data[browse_nodes]" />';
		$html .= '<input type="hidden" class="ced_browse_node_name" name="ced_amazon_profile_data[browse_nodes_name]" />';

		$html .= '<input type="hidden" id="ced-category-header" value="Browse and Select a Category">';
		$html .= '<strong><span id="ced_amazon_cat_header" data-level="1">' . __( 'Browse and Select a Category', 'amazon-for-woocommerce' ) . '</span></strong>';
		$html .= '<ol id="ced_amz_categories_1" class="ced_amz_categories" data-level="1" data-node-value="Browse and Select a Category">';

		foreach ( $categories as $key => $value ) {

			$parent_ids = isset( $value['parent_id'] ) ? $value['parent_id'] : array();
			if ( isset( $parent_ids ) && is_array( $parent_ids ) ) {
				$parent_ids = implode( ',', $parent_ids ); 
			}
			$hasChildren = isset( $value['hasChildren'] ) ? $value['hasChildren'] : false;

			if ( $hasChildren ) {
				$class = 'ced_amazon_category_arrow';
			} else {
				$class = 'ced_amz_child_category';
			}

			$html .= '<li id="' . esc_attr( $parent_ids ) . '" data-level="1" class="' . $class . '" data-name="' . esc_attr( $value['name'] ) . '" data-children="' . esc_attr( $hasChildren ) . '" data-id="' . esc_attr( $parent_ids ) . '" >' . esc_attr( $value['name'] );
			
			if ( $hasChildren ) {
				$html .= '<span  class="dashicons dashicons-arrow-right-alt2"></span>';
			} else {
				$html .= '<input type="radio" name="ced_amazon_last_level_cat" id="ced_amazon_last_level_cat" value="fitness_stepper">';
			}
			
			$html .= '</li>';
		
		}

		$html .= '</ol>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '<div class="ced-category-mapping-wrapper-breadcrumb" >';
		$html .= '<p  id="ced_amazon_breadcrumb" > </p>';
		$html .= '</div>';

		/** Amazon categoty mapping wrapper ends */

		/** Amazon product type mapping starts */

		$html .= '<div class="ced-product-type-mapping-wrapper">';
		$html .= '<div class="ced-product-type-mapping">';
		
		$html .= '<input type="hidden" class="ced_product_type" name="ced_amazon_profile_data[product_type]" value = "" />';
		$html .= '<input type="hidden" class="ced_product_type_name" name="ced_amazon_profile_data[product_type_name]" value = "" />';

		$html .= '<input type="hidden" id="ced-product-type-header" value="Browse and Select a Product Type">';
		$html .= '<strong><span id="ced_amazon_cat_header" data-level="1">' . __( 'Browse and Select a Product Type', 'amazon-for-woocommerce' ) . '</span></strong>';
		$html .= '<ol id="ced_amz_product_types_1" class="ced_amz_product_types" data-level="1" data-node-value="Browse and Select a Product Type">';

		foreach ( $productTypes as $key => $value ) {

			$class = 'ced_amz_product_type';
			$html .= '<li data-level="1" class="' . $class . '" data-name="' . esc_attr( $value['name'] ) . '" >' . esc_attr( str_replace( '_', ' ', $value['name'] ) );
			$html .= '<input type="radio" name="ced_amz_tmp_radio_btn" id="ced_amz_tmp_radio_btn" value="' . esc_attr( $value['name'] ) . '">';
			$html .= '</li>';
		
		}

		$html .= '</ol>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '<div class="ced-product-type-mapping-wrapper-breadcrumb" >';
		$html .= '<p  id="ced_amazon_breadcrumb" > </p>';
		$html .= '</div>';

		/** Amazon product type mapping ends */

		print_r($html);
	}
}
