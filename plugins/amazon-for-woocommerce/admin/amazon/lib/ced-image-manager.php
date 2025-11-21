<?php

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Amazon feed manager file.
 *
 * This file is used to perform all the relevant feed actions on Amazon.
 * Also used to get the relevant feed submission response.
 *
 * @since       1.0.0
 * @package     Amazon_Integration_For_Woocommerce
 * @subpackage  Amazon_Integration_For_Woocommerce/admin/amazon/lib 
 * @link        http://www.cedcommerce.com/
 */

if ( ! class_exists( 'Ced_Amazon_Image_Feed_Manager' ) ) :

	/**
	 * Woo-marketplace feed submission functionality.
	 *
	 * @since      1.0.0
	 * @package    Amazon_Integration_For_Woocommerce
	 * @subpackage Amazon_Integration_For_Woocommerce/admin/amazon/lib
	 */
	class Ced_Amazon_Image_Feed_Manager {

		/**
		 * The Instace of Ced_Amazon_Image_Feed_Manager.
		 *
		 * @since    1.0.0
		 * @var      $_instance   The Instance of Ced_Amazon_Image_Feed_Manager class.
		 */
		private static $_instance;
		public $amazon_xml_lib;
		public $product_upload_notice;
		public $feed_xml_notice;
		public $amzonCurlRequestInstance;
		public $amzQueueManager;
		public $amz_com_opts_mng;
		public $region;
		public $marketplace;
		public $seller_id;
		public $remote_shop_id;

		/**
		 * Ced_Amazon_Price_Feed_Manager Instance.
		 *
		 * Ensures only one instance of Ced_Amazon_Price_Feed_Manager is loaded or can be loaded.
		 *
		 * @name get_instance()
		 * @since 1.0.0
		 * @static
		 * @return Ced_Amazon_Price_Feed_Manager instance.
		 * @link  http://www.cedcommerce.com/
		 */
		public static function get_instance( ) {

			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			
			return self::$_instance;
		}

		/**
		 * Constructor.
		 *
		 * Registering actions and hooks for amazon.
		 *
		 * @link  http://www.cedcommerce.com/
		 * @since 1.0.0
		 */
		public function __construct() {

			$classes_to_load = array(
				'amazon_xml_lib' => array(
					'path' => CED_AMAZON_DIRPATH . 'admin/amazon/lib/class-amazon-xml-lib.php',
					'class' => 'Ced_Amzon_XML_Lib',
				),
				'amzonCurlRequestInstance' => array(
					'path' => CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php',
					'class' => 'Ced_Amazon_Curl_Request',
				),
				'amzQueueManager' => array(
					'path' => CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-queue-manager.php',
					'class' => 'Ced_Amazon_Queue_Manager',
				),
				'amz_com_opts_mng' => array(
					'path' => CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-common-manager.php',
					'class' => 'Ced_Amazon_Common_Operations_Maanager',
				),
			);

			foreach ( $classes_to_load as $property => $config ) {
				if ( file_exists( $config['path'] ) ) {
					require_once $config['path'];
					if ( class_exists( $config['class'] ) ) {
						$this->$property = new $config['class']();
					}
				}
			}

			
		}


		/**
		 * Update product image on Amazon
		 *
		 * @name ced_amazon_bulk_image_update
		 * @since 1.0.0
		 */
		public function ced_amazon_bulk_image_update( $product_ids = array(), $mplocation = '', $seller_mp_key = '' ) {

			set_time_limit( 600 );
			wp_raise_memory_limit( -1 );
			ignore_user_abort( true );

			$logger  = wc_get_logger();
			$context = array( 'source' => 'ced_amazon_image_update' );
			
			$ced_amazon_create_feed_throttle = get_transient( 'ced_amazon_create_feed_throttle' );
			if ( $ced_amazon_create_feed_throttle ) {

				$notice['message'] = 'Create feed API call limit exceeded. Please try after 5 mins.';
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );

			}

			if ( empty( $mplocation ) || empty( $seller_mp_key ) ) {
				$notice['message'] = 'Marketplace location or seller ID is missing. Please check!';
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );
			}

			$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );
			if ( isset( $saved_amazon_details[ $seller_mp_key ] ) && ! empty( $saved_amazon_details[ $seller_mp_key ] ) && is_array( $saved_amazon_details[ $seller_mp_key ] ) ) {
				$shop_data = $saved_amazon_details[ $seller_mp_key ];
			}

			if ( empty( $shop_data ) ) {
				$notice['message'] = 'Seller data is missing. Please check!';
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );
			}

			$region         = isset( $shop_data['marketplace_region'] ) ? $shop_data['marketplace_region'] : '';
			$marketplace_id = isset( $shop_data['marketplace_id'] ) ? $shop_data['marketplace_id'] : '';
			$seller_id      = isset( $shop_data['merchant_id'] ) ? $shop_data['merchant_id'] : '';
			$remote_shop_id = isset( $shop_data['seller_next_shop_id'] ) ? $shop_data['seller_next_shop_id'] : '';

			if ( empty( $marketplace_id ) || empty( $seller_id ) ) {
				$notice['message'] = 'Seller Id and Marketplace Id are missing, please check!';
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );
			}

			if ( is_array( $product_ids ) && ! empty( $product_ids ) ) {
				$products = $product_ids;
			} else {
				$notice['message'] = 'No products were found to update image on Amazon!';
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );
			}

			$fileName            = 'image-' . $mplocation . '.json';
			$image_content_array = $this->makeImageJsonFileToSendOnAmazon( $products, $mplocation, $fileName, $seller_mp_key );
			$image_content       = isset( $image_content_array['content'] ) ? $image_content_array['content'] : '';

			try {

				$feed_action = 'JSON_LISTINGS_FEED';

				$feed_topic = 'create-feed';
				$feed_data  = array(
					'feed_action'    => $feed_action,
					'feed_content'   => $image_content,
					'remote_shop_id' => $remote_shop_id,
				);

				$feed_reponse = $this->amzonCurlRequestInstance->ced_amazon_serverless_process( $feed_topic, $feed_data, 'POST' );
				
				// modified feed action
				if ( 'JSON_LISTINGS_FEED' == $feed_action ) {
					$feed_action = 'JSON_LISTINGS_FEED_IMAGE';
				}

				$response_body = wp_remote_retrieve_body($feed_reponse);
				$response_data = json_decode( $response_body, true);

				$logger->info( wc_print_r( $response_body, true ), $context );

				// check throttle if the feed type is JSON
				$throttleResponse = $this->amz_com_opts_mng->ced_amz_check_json_feed_throttle( $response_data, 'ced_amazon_create_feed_throttle');
				
				$decodedThrottleResponse = json_decode( $throttleResponse, true );
				if ( is_array( $decodedThrottleResponse ) && isset(  $decodedThrottleResponse['message'] ) && strpos( $decodedThrottleResponse['message'], 'Quoto Exceeded' ) === 0 ) {
					return $throttleResponse;
				}

				$imageuploadreponse = json_decode( $feed_reponse['body'], true );
				$imageuploadreponse = isset( $imageuploadreponse['data'] ) ? $imageuploadreponse['data'] : array();

				if ( isset( $imageuploadreponse['success'] ) && 'false' == $imageuploadreponse['success'] ) {
					$notice['message'] = isset( $imageuploadreponse['body'] ) ? $imageuploadreponse['body'] : $imageuploadreponse['message'];
					$notice['classes'] = 'notice notice-error is-dismissable';
					return wp_json_encode( $notice );
				}

				if ( isset( $imageuploadreponse['feed_id'] ) && ! empty( $imageuploadreponse['feed_id'] ) ) {
					
					$feedId    = $imageuploadreponse['feed_id'];
					$feed_type = $feed_action;
					$this->amz_com_opts_mng->insertFeedInfoToDatabase( $feedId, $feed_type, $mplocation, $image_content_array['SKUs'], 'Manual' );

					// update action timings
					$ced_amz_action_timings                 = get_option( 'ced_amz_action_timings' , array() );
					$ced_amz_action_timings['created-feed'] = strtotime('now');
					update_option( 'ced_amz_action_timings', $ced_amz_action_timings );

					$notice['message'] = 'Product image feed has processed and submitted.';
					$notice['classes'] = 'notice notice-success is-dismissable';
					return wp_json_encode( $notice );

				} else {
					$notice['message'] = 'Something went wrong with the feed submission. Please check the image feed URL!';
					$notice['classes'] = 'notice notice-error is-dismissable';
					return wp_json_encode( $notice );
				}

			} catch ( Exception $e ) {
				echo 'An exception occurred when calling the image update feed api endpint: ', esc_attr( $e->getMessage() ), PHP_EOL;
				$notice['message'] = 'An exception occurred when calling the image update feed api endpint!';
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );
			}

			
			$notice['message'] = 'Something went wrong with feed submission. Please check the image feed URL!';
			$notice['classes'] = 'notice notice-error is-dismissable';
			return wp_json_encode( $notice );
		}


		public function ced_amz_image_json_array(  $i, $sku, $image, $image_type = 'main', $j = 0, $marketplace_id = '' ) {

			if ( 'main' == $image_type ) {
				$path = '/attributes/main_product_image_locator';
			} else {
				$path = '/attributes/other_product_image_locator_' . $j;
			}

			return  !empty( $path ) ? array(
					'op' => 'replace',
					'path' => $path,
					'value' => array(
						array(
							'media_location' => $image,
							'marketplace_id' => $marketplace_id
						)
					)
			) : array();

						
				
		}


		/**
		 * Create Image File
		 *
		 * @name makeImageJsonFileToSendOnAmazon
		 * @since 1.0.0
		 */
		public function makeImageJsonFileToSendOnAmazon( $proIds, $mplocation = '', $fileName = '', $seller_mp_key = '' ) {

			$errorsArray = array();
			if ( empty( $fileName ) ) {
				$errorsArray['File'] = 'file name is not availbale';
				return false;
			}

			$i          = 1;
			$SKUs_array = array();

			/** To prepare the json feed content */ 
			$messages = array();

			$marketplace_id = ced_get_marketplace_id_by_country( $mplocation );
			
			foreach ( $proIds as $product_id ) {

				$patches = array();

				$product = wc_get_product( $product_id );
				if ( ! is_object( $product ) ) {
					continue;
				}
				$productType       = $product->get_type();
				$post_thumbnail_id = get_post_thumbnail_id( $product_id );
				$image             = wp_get_attachment_image_url( $post_thumbnail_id, 'full', false );
				
				$amazonxmlarray = array();
				$sku            = $product->get_sku();

				$productLevelAmazonSKU = get_post_meta( $product_id, 'item_sku', true );
				if ( isset( $productLevelAmazonSKU ) && ! empty( $productLevelAmazonSKU ) ) {
					$sku = $productLevelAmazonSKU;
				}

				if ( ! empty( $image ) ) {
					$attachment_url_modified = $this->amazon_xml_lib->modifyImageUrl( $image );
					$image                   = ! empty( $attachment_url_modified ) ? $attachment_url_modified : $image;
				}

				$product_images = array();

				if ( isset( $image ) && ! empty( $image ) && isset( $sku ) && ! empty( $sku ) ) {
					$product_images[] = $image;

					$patch     = $this->ced_amz_image_json_array( $i, $sku, $image, 'main', 0, $marketplace_id );
					$patches[] = $patch;
					
				}

				$attachment_ids = $product->get_gallery_image_ids();

				if ( isset( $attachment_ids ) && ! empty( $attachment_ids ) && isset( $sku ) && ! empty( $sku ) ) {
					$j = 1;
					foreach ( $attachment_ids as $attachment_id ) {
						if ( 8 > $j ) {
							$alternateimage = wp_get_attachment_url( $attachment_id );
							if ( ! empty( $alternateimage ) ) {
								$attachment_url_modified = $this->amazon_xml_lib->modifyImageUrl( $alternateimage );
								$alternateimage          = ! empty( $attachment_url_modified ) ? $attachment_url_modified : $alternateimage;
							}
							
							$product_images[] = $alternateimage;

							$patch     = $this->ced_amz_image_json_array( $i, $sku, $alternateimage, 'pt', $j, $marketplace_id );
							$patches[] = $patch;

							++$j;
						}
					}
				}

				$SKUs_array[ $sku ] = array(
					'product_name' => $product->get_name(),
					'type'         => $productType,
					'parent_sku'   => '',
					'product_id'   => $product_id,
					'product_sku'  => $sku,
					'value'        => $product_images,
				);

				if ( !empty( $patches ) ) {

					$messages[] = array( 
						'messageId' => $i,
						'sku' => $sku,
						'operationType' => 'PATCH',
						'productType' => 'PRODUCT',
						'patches' => $patches
					);

				}

				if ( 'variable' == $productType ) {

					$amazonxmlarray = array();
					$parent_product = $product;

					$attachment_ids = $parent_product->get_gallery_image_ids();

					if ( ! empty( $product->get_sku() ) ) {
						$parent_sku = $product->get_sku();
					}

					$productLevelAmazonPSKU = get_post_meta( $product_id, 'item_sku', true );
					if ( isset( $productLevelAmazonPSKU ) && ! empty( $productLevelAmazonPSKU ) ) {
						$parent_sku = $productLevelAmazonPSKU;
					}

					$all_available_var = $product->get_children();
					foreach ( $all_available_var as $var_key => $var_value ) {

						++$i;
						$patches = array();

						$product    = wc_get_product( $var_value );
						$product_id = $var_value;
						$sku        = $product->get_sku();

						$productLevelAmazonSKU = get_post_meta( $var_value, 'item_sku', true );
						if ( isset( $productLevelAmazonSKU ) && ! empty( $productLevelAmazonSKU ) ) {
							$sku = $productLevelAmazonSKU;
						}

						if ( isset( $parent_sku ) && $sku == $parent_sku ) {
							$sku = '';
						}

						$post_thumbnail_id = get_post_thumbnail_id( $var_value );
						$var_image         = '';
						$var_image         = wp_get_attachment_image_url( $post_thumbnail_id, 'full', false );
						if ( '' == $var_image ) {
							$var_image = $image;
						}

						if ( ! empty( $var_image ) ) {
							$attachment_url_modified = $this->amazon_xml_lib->modifyImageUrl( $var_image );
							$var_image               = ! empty( $attachment_url_modified ) ? $attachment_url_modified : $var_image;
						}

						$img_value = array();
						if ( isset( $var_image ) && ! empty( $var_image ) && isset( $sku ) && ! empty( $sku ) ) {

							$SKUs_array[ $sku ] = array(
								'product_name' => $product->get_name(),
								'type'         => 'Variation',
								'product_sku'  => $sku,
								'parent_sku'   => $parent_sku,
								'product_id'   => $var_value,
								'value'        => array( $var_image ),
							);

							$patch     = $this->ced_amz_image_json_array( $i, $sku, $var_image, 'main', 0, $marketplace_id );
							$patches[] = $patch;
							
							// ++$i;
						}

						if ( isset( $attachment_ids ) && ! empty( $attachment_ids ) && isset( $sku ) && ! empty( $sku ) ) {
							$j = 1;
							foreach ( $attachment_ids as $attachment_id ) {
								if ( 8 > $j ) {
									$alternateimage = wp_get_attachment_url( $attachment_id );
									if ( ! empty( $alternateimage ) ) {
										$attachment_url_modified = $this->amazon_xml_lib->modifyImageUrl( $alternateimage );
										$alternateimage          = ! empty( $attachment_url_modified ) ? $attachment_url_modified : $alternateimage;
									}

									if ( isset( $SKUs_array[ $sku ] ) && isset( $SKUs_array[ $sku ]['value'] ) ) {
										$SKUs_array[ $sku ]['value'][] = $alternateimage;
									} else {
										$SKUs_array[ $sku ]['value'] = array( $alternateimage );
									}

									$patch     = $this->ced_amz_image_json_array( $i, $sku, $alternateimage, 'pt', $j, $marketplace_id );
									$patches[] = $patch;

									// ++$i;
									++$j;
								}
							}
						}

						$messages[] = array( 
							'messageId' => $i,
							'sku'       => $sku,
							'operationType' => 'PATCH',
							'productType' => 'PRODUCT',
							'patches' => $patches
						);


					}

				}
				
				// if ( !empty( $patches ) ) {

				// 	$messages[] = array( 
				// 		'messageId' => $i,
				// 		'sku' => $sku,
				// 		'operationType' => 'PATCH',
				// 		'productType' => 'PRODUCT',
				// 		'patches' => $patches
				// 	);

				// }
				
				++$i;
								
			}
				
			$seller_id_array = explode( '|', $seller_mp_key );
			$header          = array(
				'sellerId'    => $seller_id_array[1],
				'version'     => '2.0',
				'issueLocale' => 'in',
			);

			$product_info = array(
				'header'   => $header,
				'messages' => $messages,
			);

			$json_data = wp_json_encode( $product_info );
			$this->amz_com_opts_mng->writeStringToFile( $json_data, $fileName );
		
			return array(
				'content'     =>   $json_data,
				'SKUs'        => $SKUs_array,
				'errorsLists' => $errorsArray,
			);


		}

	


	}

endif;
