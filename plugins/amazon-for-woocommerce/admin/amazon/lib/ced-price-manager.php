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

if ( ! class_exists( 'Ced_Amazon_Price_Feed_Manager' ) ) :

	/**
	 * Woo-marketplace feed submission functionality.
	 *
	 * @since      1.0.0
	 * @package    Amazon_Integration_For_Woocommerce
	 * @subpackage Amazon_Integration_For_Woocommerce/admin/amazon/lib
	 */
	class Ced_Amazon_Price_Feed_Manager {

		/**
		 * The Instace of Ced_Amazon_Price_Feed_Manager.
		 *
		 * @since    1.0.0
		 * @var      $_instance   The Instance of Ced_Amazon_Price_Feed_Manager class.
		 */
		private static $_instance;
		public $amazon_xml_lib;
		public $product_upload_notice;
		public $feed_xml_notice;
		public $amzonCurlRequestInstance;
		public $amzQueueManager;
		public $amz_com_opts_mng;
		public $amz_inv_manager;
		public $region;
		public $marketplace;
		public $seller_id;
		public $remote_shop_id;
		public $product_upload_errors;


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
				'amz_inv_manager' => array(
					'path' => CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-inventory-manager.php',
					'class' => 'Ced_Amazon_Inventory_Feed_Manager',
				)
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


		public function ced_amz_price_json_array( $i, $sku, $currency, $price_amz, $marketplace_id ) {

			$patches = array();
			if ( 0 < $price_amz ) {
			   $patches['purchasable_offer'] = $this->ced_amz_price_json_patch( $price_amz, $currency, $marketplace_id  );
			}

			return  !empty( $patches ) ? array(
				'messageId' => $i,
				'sku'=> $sku,
				'operationType' => 'PARTIAL_UPDATE',
				'productType' => 'PRODUCT',
				'attributes' => $patches

			) : array();
		}

		public function ced_amz_price_json_patch( $price_amz, $currency = '', $marketplace_id = '' ) {

			$logger  = wc_get_logger();
			$context = array( 'source' => 'ced_amazon_price_sync' );
		
			return   array(
						array(
							'audience' => 'ALL',
							'currency' => $currency,
							'marketplace_id' => $marketplace_id,
							'our_price' => array(
								array(
									'schedule' => array(
										array(
											'value_with_tax' => $price_amz
										)
									)
								)
							)
						)
					);
				
			
		}


		public function ced_amz_listing_price_json_patch( $price_amz, $currency ) {
			if ( empty( $price_amz ) || empty( $currency ) ) {
				return array();
			}

			return array(
				array(
					'op'   => 'replace',
					'path' => '/attributes/purchasable_offer',
					'value' => array(
						array(
							'currency' => $currency,
							'our_price' => array(
								array(
									'schedule' => array(
										array(
											'value_with_tax' => $price_amz
										)
									)
								)
							)
						)
					)
				),
			);
		}


		public function ced_amazon_manual_price_update_listing( $params = array() ) {

			$logger           = wc_get_logger();
			$context          = array( 'source' => 'ced_amazon_manual_price_sync' );
			$seller_id        = $params['seller_id'] ?? '';
			$marketplace_ids  = $params['marketplace_ids'] ?? array();
			$remote_shop_id   = $params['remote_shop_id'] ?? '';
			$products_to_sync = $params['product_ids'] ?? array();
			$marketplace_id   = $marketplace_ids[0] ?? '';
			$feed_id          = $params['feed-id'] ?? '';
			$logger->info( 'Manual price update function', $context );

			// Get global settings data
			$seller_global_settings     = array();
			$seller_global_settings_all = get_option( 'ced_amazon_global_settings', array() );
			$seller_global_settings     = $seller_global_settings_all[ $seller_id ] ?? array();

			$logger->info(wc_print_r($seller_global_settings, true), $context);
			if ( empty( $seller_id ) || empty( $marketplace_id ) || empty( $remote_shop_id ) ) {
				$logger->info( 'Missing seller, marketplace, or remote shop ID.', $context );
				return;
			}
			
			$chunk_size      = 5;
			$total_processed = 0;
			
			$current_feed = $this->amz_com_opts_mng->get_amazon_synthetic_feed( $feed_id );
			$SKUs_array   =  $current_feed['sku'] ?? array();

			if ( !empty( $SKUs_array ) ) {
				$SKUs_array = json_decode( $SKUs_array, true );
			}

			$logger->info( wc_print_r( '--------current feed starts----------', true) , $context );
			$logger->info( wc_print_r( $current_feed, true) , $context );
			$logger->info( wc_print_r( '--------current feed ends----------', true) , $context );
			$logger->info( wc_print_r( '--------skus array starts----------', true) , $context );
			$logger->info( wc_print_r( $SKUs_array , true) , $context );
			$logger->info( wc_print_r( '--------skus array ends----------', true) , $context );

			foreach (array_chunk( $products_to_sync, $chunk_size ) as $product_chunk ) {
				foreach ( $product_chunk as $product_id ) {
					$product = wc_get_product( $product_id );
					if ( ! $product ) {
						continue;
					}

					$sku = $product->get_sku();
					if ( empty( $sku ) ) {
						$logger->info( "SKU is missing for product ID $product_id", $context );
						continue;
					}
					
					$product_type = $product->get_type();
					if ( 'simple' === $product_type ) {
						$response                     = $this->ced_amz_update_price( $product_id, $product, $sku, $product_type, $marketplace_id, $remote_shop_id, $seller_global_settings );
						$SKUs_array[ $sku ]['status'] = $response['status'] ?? '';
						$SKUs_array[ $sku ]['value']  = $response['value'] ?? '';
					}

					if ( 'variable' === $product_type ) {
						$variation_ids = $product->get_children();
						foreach ( $variation_ids as $variation_id ) {
							$variation_product = wc_get_product( $variation_id );
							if ( ! $variation_product ) {
								continue;
							}
							$variation_sku = $variation_product->get_sku();
							if ( ! empty( $variation_sku ) ) {
								$response                               = $this->ced_amz_update_price( $variation_id, $variation_product, $variation_sku, $product_type, $marketplace_id, $remote_shop_id, $seller_global_settings );
								$SKUs_array[ $variation_sku ]['status'] = $response['status'] ?? '';
								$SKUs_array[ $variation_sku ]['value']  = $response['value'] ?? '';
							}

							sleep(1);
						}
					}

					sleep(1);
					++$total_processed;
				}
			}

			$params['status'] = 'completed';
			$this->amzQueueManager->ced_amz_update_queue_action( 'ced_amazon_manual_price_update_action', $params );
			$logger->info( "Processed $total_processed products successfully.", $context );

			$logger->info( wc_print_r( $SKUs_array , true) , $context );

			$this->amz_com_opts_mng->update_amazon_synthetic_feed( $feed_id, $SKUs_array );
			

		}

		public function ced_amz_update_price($product_id, $product, $sku, $product_type, $marketplace_id, $remote_shop_id, $seller_global_settings = array()) {

			$logger                    = wc_get_logger();
			$context                   = array( 'source' => 'ced_amazon_manual_price_sync' );
			$amz_currency_code_mapping = get_option( 'ced_amz_currency_code_mapping', array() );
			$user_id                   = ced_amz_get_user_id_by_mrkp_id( $marketplace_id );
			$price_type                = 'regular_price';
			if ( isset( $seller_global_settings['ced_amazon_product_price_type'] ) && 'sale_price' == $seller_global_settings['ced_amazon_product_price_type'] ) {
				$price_type = 'sale_price'; // SALE price is send, only when the price type is sale price, in all other cases regular price is send
			}
			/** Code to get amazon currency code starts */
			
			if ( isset( $amz_currency_code_mapping[$marketplace_id] ) && !empty( $amz_currency_code_mapping[$marketplace_id] ) ) {
				$currency = $amz_currency_code_mapping[$marketplace_id];
			} else {
				
				$url                          = 'get-product-type-definitions/?product_type=3D_PRINTED_PRODUCT&productTypeVersion=LATEST&requirements=LISTING&requirementsEnforced=ENFORCED&locale=DEFAULT';
				$amazon_profile_data_response = $this->amzonCurlRequestInstance->ced_amazon_get_category( $url, $user_id, $seller_id );
				
				$currency = '';
				
				if ( $amazon_profile_data_response['success'] && null !== $amazon_profile_data_response['response'] ) {
					
					$schema_url      = $amazon_profile_data_response['response']['schema']['link']['resource'];
					$schema_response = wp_remote_get( $schema_url );
					
					$amazon_template_attributes_data = json_decode( $schema_response['body'], true );
					$purchasable_offer               = isset( $amazon_template_attributes_data['properties']['purchasable_offer'] ) ? $amazon_template_attributes_data['properties']['purchasable_offer'] : array();
					$currency                        = $purchasable_offer['items']['properties']['currency']['anyOf'][1]['enum'][0];
					
					$amz_currency_code_mapping[$marketplace_id] = $currency;
					update_option( 'ced_amz_currency_code_mapping', $amz_currency_code_mapping );
					
					
				} else {
					$logger->info( wc_print_r( 'Unable to get product type data from API' , true ), $context );
				}
				
			}
			
			$price_array           = $this->ced_amz_retrieve_final_price( $product, $price_type, $product_id , $seller_global_settings );
			$price_amz             = isset( $price_array['amz_price'] ) ? $price_array['amz_price'] : 0;
			$woo_price             = isset( $price_array['woo_price'] ) ? $price_array['woo_price'] : 0;
			$productLevelAmazonSKU = get_post_meta( $product_id, 'item_sku', true );
			$sku                   = ! empty( $productLevelAmazonSKU ) ? $productLevelAmazonSKU : $sku;
			
			if ( isset( $price_amz ) && isset( $sku ) && ! empty( $price_amz ) ) {
				
				// Prepare JSON Patch for inventory
				$patches = $this->ced_amz_listing_price_json_patch( $price_amz, $currency );
				$logger->info(wc_print_r($patches, true), $context);
				
				$update_topic = 'listing-update';
				
				$update_data = array(
					'product_type'   => 'PRODUCT',
					'sku'            => $sku,
					'patches'        => $patches,
					'remote_shop_id' => $remote_shop_id,
				);

				// Send PATCH request to Amazon Listing endpoint
				$update_response = $this->amzonCurlRequestInstance->ced_amazon_serverless_process( $update_topic, $update_data, 'POST' );
				$response_body   = wp_remote_retrieve_body( $update_response );
				$response_data   = json_decode( $response_body, true );
				$response_data   = isset( $response_data['response'] ) ? $response_data['response'] : array();

				if (isset($response_data['status']) && 'ACCEPTED' == $response_data['status'] && empty($response_data['issues'])) {
					$logger->info('Product Price is updated successfully', $context);
				} elseif (isset($response_data['status']) && 'INVALID' == $response_data['status'] && empty($response_data['issues'])) {
					$logger->info(wc_print_r($response['issues'], true), $context);
				} else {
					$logger->info('Product Data is empty', $context);
				}

				return array(
					'status' => $response_data['status'],
					'value'  => $price_amz
				);
				
			}
		}


		/**
		 * Create Price File
		 *
		 * @name makePriceFileToSendOnAmazon
		 * @since 1.0.0
		 */
		public function makePriceFileToSendOnAmazon( $proIds, $mplocation = '', $filename = '', $region = '', $marketplace_ids = array() ) {

			$logger  = wc_get_logger();
			$context = array( 'source' => 'ced_amazon_price_sync' );
		
			// Get global settings data
			$seller_global_settings = array();
			$global_settings        = get_option( 'ced_amazon_global_settings' );

			$i = 1;
			// To hold skus and amazon price that has send on the feed to update
			$SKUs_array = array();

			// To prepare the json feed content
			$messages = array();

			// To hold product_id and woo price that has been send on the feed to update
			$woo_price_array = array();

			// To hold products that could not be updated
			$errorsArray = array();

			$amz_currency_code_mapping = get_option( 'ced_amz_currency_code_mapping', array() );

			$marketplace_ids = array_filter( $marketplace_ids );
			if ( !empty( $marketplace_ids ) ) {
				foreach ( $marketplace_ids as $marketplace_id ) {

					$seller_id = ced_amz_get_seller_id_by_mrkp_id( $marketplace_id );
					$user_id   = ced_amz_get_user_id_by_mrkp_id( $marketplace_id );
					
					if ( isset( $global_settings[ $seller_id ] ) && ! empty( $global_settings[ $seller_id ] ) ) {
						$seller_global_settings = $global_settings[ $seller_id ];
					}

					$logger->info( wc_print_r( $seller_id, true ), $context );
		
					// by default we will send REGULAR price
					$price_type = 'regular_price';
					if ( isset( $seller_global_settings['ced_amazon_product_price_type'] ) && 'sale_price' == $seller_global_settings['ced_amazon_product_price_type'] ) {
						$price_type = 'sale_price'; // SALE price is send, only when the price type is sale price, in all other cases regular price is send
					}

					/** Code to get amazon currency code starts */

					if ( isset( $amz_currency_code_mapping[$marketplace_id] ) && !empty( $amz_currency_code_mapping[$marketplace_id] ) ) {
						$currency = $amz_currency_code_mapping[$marketplace_id];
					} else {
						
						$url                          = 'get-product-type-definitions/?product_type=3D_PRINTED_PRODUCT&productTypeVersion=LATEST&requirements=LISTING&requirementsEnforced=ENFORCED&locale=DEFAULT';
						$amazon_profile_data_response = $this->amzonCurlRequestInstance->ced_amazon_get_category( $url, $user_id, $seller_id );

						$currency = '';

						if ( $amazon_profile_data_response['success'] && null !== $amazon_profile_data_response['response'] ) {

							$schema_url      = $amazon_profile_data_response['response']['schema']['link']['resource'];
							$schema_response = wp_remote_get( $schema_url );
			
							$amazon_template_attributes_data = json_decode( $schema_response['body'], true );
							$purchasable_offer               = isset( $amazon_template_attributes_data['properties']['purchasable_offer'] ) ? $amazon_template_attributes_data['properties']['purchasable_offer'] : array();
							$currency                        = $purchasable_offer['items']['properties']['currency']['anyOf'][1]['enum'][0];

							$amz_currency_code_mapping[$marketplace_id] = $currency;
							update_option( 'ced_amz_currency_code_mapping', $amz_currency_code_mapping );
							   
			
						} else {
							$logger->info( wc_print_r( 'Unable to get product type data from API' , true ), $context );
						}

					}
		
				   /** Code to get amazon currency code ends */

					foreach ( $proIds as $product_id ) {
						
						$product = wc_get_product( $product_id );
						if ( ! is_object( $product ) ) {
							continue;
						}
						$productType = $product->get_type();
						if ( 'simple' == $productType ) {
							$amazonxmlarray = array();
							$sku            = $product->get_sku();

							$productLevelAmazonSKU = get_post_meta( $product_id, 'item_sku', true );
							if ( isset( $productLevelAmazonSKU ) && ! empty( $productLevelAmazonSKU ) ) {
								$sku = $productLevelAmazonSKU;
							}

							$price_array = $this->ced_amz_retrieve_final_price( $product, $price_type, $product_id , $seller_global_settings  );
							$price_amz   = isset( $price_array['amz_price'] ) ? $price_array['amz_price'] : 0;
							$woo_price   = isset( $price_array['woo_price'] ) ? $price_array['woo_price'] : 0;
							
							if ( isset( $price_amz ) && ! empty( $price_amz ) && isset( $sku ) && ! empty( $sku ) ) {

								$woo_price_array[$product_id] = $woo_price;
								$SKUs_array[ $sku ]           = array(
									'type'         => 'Simple',
									'value'        => $price_amz,
									'product_id'   => $product_id,
									'product_sku'  => $sku,
									'product_name' => $product->get_name(),
								);

								$message = $this->ced_amz_price_json_array( $i, $sku, $currency, $price_amz , $marketplace_id );

								if ( !empty( $message ) ) {
									$messages[] = $message;
									++$i;
								} else {
									$errorsArray[] = $product_id; 
								}
								
							} else {
								$errorsArray[] = $product_id; 
							}

						} elseif ( 'variable' == $productType ) {

							$amazonxmlarray = array();
							$sku            = $product->get_sku();

							if ( ! empty( $sku ) ) {
								$parent_sku = $product->get_sku();
							}

							$productLevelAmazonSKU = get_post_meta( $product_id, 'item_sku', true );
							if ( isset( $productLevelAmazonSKU ) && ! empty( $productLevelAmazonSKU ) ) {
								$sku        = $productLevelAmazonSKU;
								$parent_sku = $productLevelAmazonSKU;
							}

							$all_available_var = $product->get_children();
							foreach ( $all_available_var as $var_key => $var_value ) {
								
								$product = wc_get_product( $var_value );
								$qty     = $product->get_stock_quantity();
								$sku     = $product->get_sku();

								$price_array = $this->ced_amz_retrieve_final_price( $product, $price_type, $product->get_id() , $seller_global_settings  );
								$price_amz   = isset( $price_array['amz_price'] ) ? $price_array['amz_price'] : 0;
								$woo_price   = isset( $price_array['woo_price'] ) ? $price_array['woo_price'] : 0;
								
								$productLevelAmazonSKU = get_post_meta( $product->get_id(), 'item_sku', true );
								if ( isset( $productLevelAmazonSKU ) && ! empty( $productLevelAmazonSKU ) ) {
									$sku = $productLevelAmazonSKU;
								}

								if ( isset( $parent_sku ) && $sku == $parent_sku ) {
									$sku = '';
								}

								if ( isset( $price_amz ) && ! empty( $price_amz ) && isset( $sku ) && ! empty( $sku ) ) {
									
									$woo_price_array[$product_id] = $woo_price;
									$SKUs_array[ $sku ]           = array(
										'type'         => 'Variation',
										'product_sku'  => $sku,
										'parent_sku'   => $parent_sku,
										'value'        => $price_amz,
										'product_id'   => $product->get_id(),
										'product_name' => $product->get_name(),
									);
									
									$message = $this->ced_amz_price_json_array( $i, $sku, $currency, $price_amz, $marketplace_id );
									if ( !empty( $message ) ) {
										++$i;
										$messages[] = $message;
									} else {
										$errorsArray[] = $var_value; 
									}
									

								} else {
									$errorsArray[] = $var_value; 
								}
							}
						}
						
					}

				}
			}
		
			$merchant_id = get_merchant_id_by_region( $marketplace_ids[0] );
			
			$header = array(
				'sellerId'    => $merchant_id,
				'version'     => '2.0',
				'issueLocale' => 'in',
			);

			$product_info = array(
				'header'   => $header,
				'messages' => $messages,
			);

			$json_data = wp_json_encode( $product_info );
			$this->amz_com_opts_mng->writeStringToFile( $json_data, $filename );

			return array(
				'validity'    => 1 < $i ?  true : false,
				'content'     => $json_data,
				'SKUs'        => $SKUs_array,
				'errorsLists' => $errorsArray,
				'woo_price_array' => $woo_price_array
			);


		}


		/**
		 * Update product price on Amazon
		 *
		 * @name ced_amazon_bulk_price_update
		 * @since 1.0.0
		 */
		public function ced_amazon_bulk_price_update( $bulk_price_params = array() ) {

			$product_ids       = isset( $bulk_price_params['products'] ) ? $bulk_price_params['products'] : array(); 
			$mplocation        = isset( $bulk_price_params['mplocation'] ) ? $bulk_price_params['mplocation'] : '';
			$opt_type          = isset( $bulk_price_params['opt_type'] ) ? $bulk_price_params['opt_type'] : '';
			$is_common_prc_inv = isset( $bulk_price_params['is_common_prc_inv'] ) ? $bulk_price_params['is_common_prc_inv'] : false;
			$remote_shop_id    = isset( $bulk_price_params['remote_shop_id'] ) ? $bulk_price_params['remote_shop_id'] : '';
			$marketplace_ids   = isset( $bulk_price_params['marketplace_ids'] ) ? $bulk_price_params['marketplace_ids'] : array(); 
			$region            = isset( $bulk_price_params['region'] ) ? $bulk_price_params['region'] : '';
			$extraParams       = isset( $bulk_price_params['extraParams'] ) ? $bulk_price_params['extraParams'] : array();

			// Log file name
			$logger = wc_get_logger();
			
			if ( $is_common_prc_inv ) {
				$context = array( 'source' => 'ced_amazon_common_prc_inv_sync' );
			} else {
				$context = array( 'source' => 'ced_amazon_price_sync' );
			}
			
			set_time_limit( 600 );
			wp_raise_memory_limit( -1 );
			ignore_user_abort( true );

			$logger->info( wc_print_r( 'current region is: ' . $region, true ), $context );
			
			if ( empty( $mplocation ) ) {
				$logger->info( "Marketplace location or seller ID is missing during bulk inventory update. Please check! \n", $context );
				return;
			}

			if ( is_array( $product_ids ) && ! empty( $product_ids ) ) {
				$products = $product_ids;
			} else {
				$logger->info( "No products were found to update price on Amazon! \n", $context );
				return;
			}

			$opt_type =  isset( $extraParams['status'] ) && 'pending' ==  $extraParams['status']  ? 'Manual' : 'Automatic';
			$fileName = 'price-' . $mplocation . '.json';

			if ( $is_common_prc_inv ) {
				
				$logger->info( wc_print_r( '------------------------------- CALLING makeCommonPrcInvFileToSendOnAmazon ACTION -------------------- ', true ), $context );

				$price_content_array = $this->makeCommonPrcInvFileToSendOnAmazon( $products, $mplocation, $fileName, $region, $marketplace_ids ); // create COMMON JSON file
				$logger->info( wc_print_r( $price_content_array, true ), $context );
				$logger->info( wc_print_r( '------------------------------- BACK FROM makeCommonPrcInvFileToSendOnAmazon ACTION -------------------- ', true ), $context );

				$logger->info( wc_print_r( $price_content_array, true ), $context );
				
				$price_content   = isset( $price_content_array['content'] ) ? $price_content_array['content'] : '';
				$woo_price_array = isset( $price_content_array['woo_price_array'] ) ? $price_content_array['woo_price_array'] : array();
			
			} else {

				$logger->info( wc_print_r( '------------------------------- CALLING makePriceFileToSendOnAmazon ACTION -------------------- ', true ), $context );
				$logger->info( wc_print_r( $marketplace_ids, true ), $context );
				$price_content_array = $this->makePriceFileToSendOnAmazon( $products, $mplocation, $fileName, $region, $marketplace_ids ); // create Price JSON file
				$logger->info( wc_print_r( '------------------------------- BACK FROM makePriceFileToSendOnAmazon ACTION -------------------- ', true ), $context );

				$logger->info( wc_print_r( json_encode($price_content_array), true ), $context );
				
				$price_content   = isset( $price_content_array['content'] ) ? $price_content_array['content'] : ''; 
				$woo_price_array = isset( $price_content_array['woo_price_array'] ) ? $price_content_array['woo_price_array'] : array();

			}

			$decoded_price_content = json_decode( $price_content, true );
			if ( isset( $decoded_price_content['messages'] ) && empty( $decoded_price_content['messages'] ) ) {
				$logger->info( "No messages were found to update data on Amazon! \n", $context );
				return;
			}

			if ( isset( $price_content_array['validity'] ) && !$price_content_array['validity'] ) {
 
				$logger->info( wc_print_r( '------------------------------- DATA VALIDATION FAILED -------------------- ', true ), $context );
				$feed_xml_error    = $this->feed_xml_notice;
				$notice['message'] = 'Price data validation failed: ' . $feed_xml_error;
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );

			}

			try {

				$logger->info( wc_print_r( '------------------------------------ GOING TO CALL AMAZON JSON API ----------------------------------', true ), $context );
				$feed_action = 'JSON_LISTINGS_FEED';

				// Price update feed API call using SP-API endpoint
				$feed_topic = 'create-feed';
				$feed_data  = array(
					'feed_action'    => $feed_action,
					'feed_content'   => $price_content,
					'remote_shop_id' => $remote_shop_id,
					
				);
				$feed_reponse = $this->amzonCurlRequestInstance->ced_amazon_serverless_process( $feed_topic, $feed_data, 'POST' );
				
				// modified feed action
				if ( 'JSON_LISTINGS_FEED' == $feed_action ) {
					$feed_action = 'JSON_LISTINGS_FEED_PRICE';
				}

				$response_body = wp_remote_retrieve_body($feed_reponse);
				$response_data = json_decode( $response_body, true);

				$logger->info( wc_print_r( json_encode($response_body), true ), $context );

				// check throttle if the feed type is JSON
				$throttleResponse = $this->amz_com_opts_mng->ced_amz_check_json_feed_throttle( $response_data, 'ced_amazon_create_feed_throttle');
				
				$decodedThrottleResponse = json_decode( $throttleResponse, true );
				if ( is_array( $decodedThrottleResponse ) && isset(  $decodedThrottleResponse['message'] ) && strpos( $decodedThrottleResponse['message'], 'Quoto Exceeded' ) === 0 ) {
					return $throttleResponse;
				}
				
				$priceuploadreponse = json_decode( $feed_reponse['body'], true );
				$priceuploadreponse = isset( $priceuploadreponse['data'] ) ? $priceuploadreponse['data'] : array();

				if ( isset( $priceuploadreponse['success'] ) && 'false' == $priceuploadreponse['success'] ) {
					$notice['message'] = isset( $priceuploadreponse['body'] ) ? $priceuploadreponse['body'] : $priceuploadreponse['message'];
					$notice['classes'] = 'notice notice-error is-dismissable';
					return wp_json_encode( $notice );
				}

				if ( isset( $priceuploadreponse['feed_id'] ) && ! empty( $priceuploadreponse['feed_id'] ) ) {
					
					$feedId = $priceuploadreponse['feed_id'];
					$this->amz_com_opts_mng->insertFeedInfoToDatabase( $feedId, $feed_action, $mplocation, $price_content_array['SKUs'], $opt_type );
					
					// update queued action status
					if ( isset( $extraParams['status'] ) && 'pending' ==  $extraParams['status']  ) {
					
						$extraParams['status']  = 'completed';
						$extraParams['feed-id'] = $feedId;
						$extraParams['context'] = $context;
						
						// function to updtae contents in the que action
						$this->amzQueueManager->ced_amz_update_queue_action( 'ced_amazon_price_scheduler_job_', $extraParams );
					}

					// update action timings
					$ced_amz_action_timings                 = get_option( 'ced_amz_action_timings' , array() );
					$ced_amz_action_timings['created-feed'] = strtotime('now');
					update_option( 'ced_amz_action_timings', $ced_amz_action_timings );

					// schedule a single event to verify the updation of price sync
					$str = $is_common_prc_inv ? 'COMMON' : 'PRICE';
					if ( $extraParams['first_sync'] ) {
						$logger->info( wc_print_r( '--------------------------------------- CREATING A SINGLE EVENT OF GET FEED FOR FIRST ' . $str . ' SYNC ---------------------------------- ', true ), $context );
						$first_sync = true; 
					} else {
						$logger->info( wc_print_r( '--------------------------------------- CREATING A SINGLE EVENT OF GET FEED FOR REGULAR ' . $str . ' SYNC ---------------------------------- ', true ), $context );
						$first_sync = false;
					}
						
					$event_time = time() + 120;
					$hook_name  = 'ced_amazon_get_feed_data';
					$hook_data  = array( array(
						'feed_id'   => $feedId, 
						'marketplace_ids' => $marketplace_ids,
						'region'     => $region,
						'user_id'    => $remote_shop_id,
						'action'     => $is_common_prc_inv ? 'common' : 'price', 
						'mplocation' => $mplocation,
						'first_sync' => $first_sync,
						'woo_price_array' => $woo_price_array
					) ) ;

					if ( function_exists( 'as_schedule_single_action' ) ) {
						as_schedule_single_action( $event_time, $hook_name, $hook_data );
					} else {
						wp_schedule_single_event( $event_time, $hook_name, $hook_data  );
					}

					return;

				} else {
					$notice['message'] = 'Something went wrong with the feed submission. Please check the price feed URL!';
					$notice['classes'] = 'notice notice-error is-dismissable';
					return wp_json_encode( $notice );
				}
			} catch ( Exception $e ) {
				echo 'Exception when calling price update feed api endpint: ', esc_attr( $e->getMessage() ), PHP_EOL;
				$notice['message'] = 'An exception occurred when calling the price update feed API endpoint.';
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );
			}

			$notice['message'] = 'Something went wrong with feed submission. Please check the price feed URL!';
			$notice['classes'] = 'notice notice-error is-dismissable';
			return wp_json_encode( $notice );
		}



		/**
		 * Update product price on Amazon manually
		 *
		 * @name ced_amazon_manual_price_update
		 * @since 1.0.0
		 */
		public function ced_amazon_manual_price_update( $product_ids = array(), $mplocation = '', $seller_id = '', $opt_type = '', $is_common_prc_inv = false, $extraParams = array() ) {

			$logger  = wc_get_logger();
			$context = array( 'source' => 'ced_amazon_manual_price_sync' );
			$logger->info( wc_print_r( ced_woo_timestamp(), true ), $context );

			if ( empty( $mplocation ) || empty( $seller_id ) ) {
				return wp_json_encode( array(
					'message' => 'Marketplace location or seller ID is missing. Please check!',
					'classes' => 'notice notice-error is-dismissable'
				));
			}

			if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
				$logger->warning( 'No valid product IDs provided for manual inventory sync.', $context );
				return;
			}

			$region               = ced_amz_get_region_by_mp_location( $mplocation );
			$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );
			$shop_data            = isset( $saved_amazon_details[ $seller_id ] ) && is_array( $saved_amazon_details[ $seller_id ] )
				? $saved_amazon_details[ $seller_id ]
				: array();

			$marketplace_id = $shop_data['marketplace_id'] ?? '';
			$remote_shop_id = $shop_data['seller_next_shop_id'] ?? '';
			if ( empty( $marketplace_id ) || empty( $remote_shop_id ) || empty( $region ) ) {
				return wp_json_encode( array(
					'message' => 'Seller Id and Marketplace Id are missing, please check!',
					'classes' => 'notice notice-error is-dismissable'
				));
			}

			$SKUs_array        = prepare_skus_array( $product_ids );
			$current_timestamp = time();
			
			$this->amz_com_opts_mng->insertFeedInfoToDatabase( $current_timestamp, 'JSON_LISTINGS_FEED_PRICE', $mplocation, $SKUs_array, 'Manual' );
			$logger->info( wc_print_r( 'Stored product IDs for seller: ' . $seller_id, true ), $context );

			$this->amzQueueManager->ced_amz_schedule_single_event( 'ced_amazon_manual_price_update_action', $args = array(
				'seller_id'       => $seller_id,
				'id'              => $current_timestamp,
				'feed-id'         => $current_timestamp,
				'region'          => $region,
				'remote_shop_id'  => $remote_shop_id,
				'marketplace_ids' => array($marketplace_id),
				'mplocation'      => $mplocation,
				'product_ids'     => $product_ids,
				'status'          => 'processing',
				'opt_type'        => 'Manual',
				'context'         => $context
				)
			);

			$logger->info( wc_print_r( 'Price queue action scheduled.', true ), $context );
			return wp_json_encode( array(
				'message' => 'Price action has been added to the queue.',
				'classes' => 'notice notice-success is-dismissable'
			));

		}


		public function ced_amz_retrieve_final_price( $product, $price_type = 'regular_price', $product_id = 0, $seller_global_settings = array() ) {
			$woo_price = 0;
			if ( 'sale_price' == $price_type ) {
				// get SALE price of products
				$price_amz = get_post_meta( $product_id, '_sale_price', true );
				$woo_price = get_post_meta( $product_id, '_sale_price', true );
				
				// get REGULAR price of products, if SALE price is set on global level, but product doean't contains SALE price
				if ( empty( $price_amz ) || '0' == $price_amz ) {
					$price_amz =  get_post_meta( $product_id, '_regular_price', true );
					$woo_price =  get_post_meta( $product_id, '_regular_price', true );
				}
			} else { // get REGULAR price of products
				$price_amz =  get_post_meta( $product_id, '_regular_price', true );
				$woo_price =  get_post_meta( $product_id, '_regular_price', true );
			}
			
			$markup_type     = $seller_global_settings['ced_amazon_product_markup_type'] ?? '';
			$markup_val      = $seller_global_settings['ced_amazon_product_markup'] ?? '';
			$roundOff_option = $seller_global_settings['ced_amazon_product_rounding_off_type'] ?? '';   
			
			// if ( isset( $seller_global_settings['ced_amazon_product_markup_type'] ) && ! empty( $seller_global_settings['ced_amazon_product_markup_type'] ) && isset( $seller_global_settings['ced_amazon_product_markup'] ) && ! empty( $seller_global_settings['ced_amazon_product_markup'] ) ) {
				$price_amz = ced_calculate_markup_price( $markup_type, $price_amz, $markup_val, $roundOff_option );
				// }
				
				// $roundOff_option = $seller_global_settings['ced_amazon_product_rounding_off_type'] ?? '';
				// $price_amz = ced_add_roundOff_to_price( $price_amz, $roundOff_option );
				
				// check for price PRODUCT LEVEL Markup
				$productLevelAmazonPrice = get_post_meta( $product_id, 'standard_price', true );
			if ( isset( $productLevelAmazonPrice ) && ! empty( $productLevelAmazonPrice ) ) {
				$price_amz = $productLevelAmazonPrice;
			}
				
				// set price to zero, if price is negative
			if ( 0 > $price_amz ) {
				$price_amz = 0;
			}
				
				$value = str_replace(',', '', $price_amz );
				return array( 'amz_price' => number_format((float) $value, 2, '.', '') , 'woo_price' => $woo_price );
				
		}


		public function ced_amz_common_prc_inv_json_array( $i, $sku, $quantity, $currency, $price_amz, $marketplace_id ) {

			$patches                             = array();
			$patches['fulfillment_availability'] = $this->ced_amz_inventory_json_patch( $quantity );
			
			if ( 0 < $price_amz ) {
			   $patches['purchasable_offer'] = $this->ced_amz_price_json_patch( $price_amz, $currency, $marketplace_id  );
			}
			
			return   !empty( $patches ) ? array(
				'messageId' => $i,
				'sku' => $sku,
				'operationType' => 'PARTIAL_UPDATE',
				'productType' => 'PRODUCT',
				'attributes' => $patches

			) : array();

		}

		/**
		 * Create Price File
		 *
		 * @name makeCommonPrcInvFileToSendOnAmazon
		 * @since 1.0.0
		 */
		public function makeCommonPrcInvFileToSendOnAmazon( $proIds, $mplocation = '', $filename = '',  $region = '', $marketplace_ids = array() ) {

			$logger  = wc_get_logger();
			$context = array( 'source' => 'ced_amazon_common_prc_inv_sync' );

			// Get global settings data
			$seller_global_settings = array();
			$global_settings        = get_option( 'ced_amazon_global_settings' );
			
			$ced_amazon_rsrve_stck = 0;
			
			$i = 1;
			
			// to hold skus and amazon price that has send on the feed to update
			$SKUs_array = array();

			// to prepare the json feed content
			$messages = array();

			// to hold product_id and woo price that has been send on the feed to update
			$woo_price_array = array();

			// to hold products that could not be updated
			$errorsArray = array();

			$amz_currency_code_mapping = get_option( 'ced_amz_currency_code_mapping', array() );

			$marketplace_ids = array_filter( $marketplace_ids );
			if ( !empty( $marketplace_ids ) ) {
				foreach ( $marketplace_ids as $marketplace_id ) {

					$seller_id = ced_amz_get_seller_id_by_mrkp_id( $marketplace_id );
					$user_id   = ced_amz_get_user_id_by_mrkp_id( $marketplace_id );
			
					//  to fetch the reserve stock
					if ( isset( $global_settings[ $seller_id ] ) && ! empty( $global_settings[ $seller_id ] ) ) {
						$seller_global_settings = $global_settings[ $seller_id ];
						$ced_amazon_rsrve_stck  = isset( $seller_global_settings['ced_amazon_rsrve_stck'] ) ? $seller_global_settings['ced_amazon_rsrve_stck'] : '';
					}

					// by default we will send REGULAR price
					$price_type = 'regular_price';
					if ( isset( $seller_global_settings['ced_amazon_product_price_type'] ) && 'sale_price' == $seller_global_settings['ced_amazon_product_price_type'] ) {
						$price_type = 'sale_price'; // SALE price is send, only when the price type is sale price, in all other cases regular price is send
					}

					// code to get amazon currency code starts
					if ( isset( $amz_currency_code_mapping[$marketplace_id] ) && !empty( $amz_currency_code_mapping[$marketplace_id] ) ) {
						$currency = $amz_currency_code_mapping[$marketplace_id];
					} else {
						
						$url                          = 'get-product-type-definitions/?product_type=3D_PRINTED_PRODUCT&productTypeVersion=LATEST&requirements=LISTING&requirementsEnforced=ENFORCED&locale=DEFAULT';
						$amazon_profile_data_response = $this->amzonCurlRequestInstance->ced_amazon_get_category( $url, $user_id, $seller_id );

						$currency = '';
						if ( $amazon_profile_data_response['success'] && null !== $amazon_profile_data_response['response'] ) {

							$schema_url      = $amazon_profile_data_response['response']['schema']['link']['resource'];
							$schema_response = wp_remote_get( $schema_url );
			
							$amazon_template_attributes_data = json_decode( $schema_response['body'], true );
							$purchasable_offer               = isset( $amazon_template_attributes_data['properties']['purchasable_offer'] ) ? $amazon_template_attributes_data['properties']['purchasable_offer'] : array();
							$currency                        = $purchasable_offer['items']['properties']['currency']['anyOf'][1]['enum'][0];

							$amz_currency_code_mapping[$marketplace_id] = $currency;
							update_option( 'ced_amz_currency_code_mapping', $amz_currency_code_mapping );
							   
						} else {
							$logger->info( wc_print_r( 'Unable to get product type data from API' , true ), $context );
						}

					}
		
				   // code to get amazon currency code ends

					foreach ( $proIds as $product_id ) {
						
						$product = wc_get_product( $product_id );
						if ( ! is_object( $product ) ) {
							continue;
						}
						$productType = $product->get_type();

						if ( 'simple' == $productType ) {

							$amazonxmlarray = array();
							$sku            = $product->get_sku();

							$productLevelAmazonSKU = get_post_meta( $product_id, 'item_sku', true );
							if ( isset( $productLevelAmazonSKU ) && ! empty( $productLevelAmazonSKU ) ) {
								$sku = $productLevelAmazonSKU;
							}

							$quantity = $product->get_stock_quantity();
							$quantity = $this->amz_inv_manager->ced_amz_retrieve_final_qty( $product_id, $quantity );
							
							$price_array = $this->ced_amz_retrieve_final_price( $product, $price_type, $product_id , $seller_global_settings  );
							$price_amz   = isset( $price_array['amz_price'] ) ? $price_array['amz_price'] : 0;
							$woo_price   = isset( $price_array['woo_price'] ) ? $price_array['woo_price'] : 0;

							$logger->info( wc_print_r( 'current price amz is: ' . $price_amz  , true ), $context );
							
							if ( isset( $sku ) && ! empty( $sku ) ) {

								$woo_price_array[$product_id] = array( 'price' => $price_amz, 'inventory' => $quantity );
								$SKUs_array[ $sku ]           = array(
									'type'         => 'Simple',
									'value'        => array( 'price' => $price_amz, 'inventory' => $quantity ),
									'product_id'   => $product_id,
									'product_sku'  => $sku,
									'product_name' => $product->get_name(),
								);

								$logger->info( wc_print_r( 'current price amz is: ' . $price_amz , true ), $context );

								// $messages[] = $this->ced_amz_price_json_array( $i, $sku, $currency = '', $price_amz );
								$message = $this->ced_amz_common_prc_inv_json_array( $i, $sku, $quantity, $currency, $price_amz, $marketplace_id );
								if ( !empty( $message ) ) {
									++$i;
									$messages[] = $message;
								} else {
									$errorsArray[] = $product_id; 
								}
								
							} else {
								$errorsArray[] = $product_id; 
							}

						} elseif ( 'variable' == $productType ) {

							$amazonxmlarray = array();
							$sku            = $product->get_sku();

							if ( ! empty( $sku ) ) {
								$parent_sku = $product->get_sku();
							}

							$productLevelAmazonSKU = get_post_meta( $product_id, 'item_sku', true );
							if ( isset( $productLevelAmazonSKU ) && ! empty( $productLevelAmazonSKU ) ) {
								$sku        = $productLevelAmazonSKU;
								$parent_sku = $productLevelAmazonSKU;
							}

							$all_available_var = $product->get_children();
							foreach ( $all_available_var as $var_key => $var_value ) {
								
								$product               = wc_get_product( $var_value );
								$sku                   = $product->get_sku();
								$productLevelAmazonSKU = get_post_meta( $product->get_id(), 'item_sku', true );
								if ( isset( $productLevelAmazonSKU ) && ! empty( $productLevelAmazonSKU ) ) {
									$sku = $productLevelAmazonSKU;
								}

								if ( isset( $parent_sku ) && $sku == $parent_sku ) {
									$sku = '';
								}

								$quantity    = $product->get_stock_quantity();
								$quantity    = $this->amz_inv_manager->ced_amz_retrieve_final_qty( $product->get_id(), $quantity );
								$price_array = $this->ced_amz_retrieve_final_price( $product, $price_type, $product->get_id() , $seller_global_settings  );
								
								$price_amz = isset( $price_array['amz_price'] ) ? $price_array['amz_price'] : 0;
								$woo_price = isset( $price_array['woo_price'] ) ? $price_array['woo_price'] : 0;

								$logger->info( wc_print_r( 'current price amz is: ' . $price_amz , true ), $context );
								
								if ( isset( $sku ) && ! empty( $sku ) ) {
									$woo_price_array[$product_id] = array( 'price' => $price_amz, 'inventory' => $quantity );
									$SKUs_array[ $sku ]           = array(
										'type'         => 'Variation',
										'product_sku'  => $sku,
										'parent_sku'   => $parent_sku,
										'value'        => array( 'price' => $price_amz, 'inventory' => $quantity ),
										'product_id'   => $product->get_id(),
										'product_name' => $product->get_name(),
									);

									$logger->info( wc_print_r( 'current price amz is: ' . $price_amz, true ), $context );
									
									$message = $this->ced_amz_common_prc_inv_json_array( $i, $sku, $quantity, $currency, $price_amz, $marketplace_id );
									if ( !empty( $message ) ) {
										++$i;
										$messages[] = $message;
									} else {
										$errorsArray[] = $var_value; 
									}
									
								} else {
									$errorsArray[] = $var_value; 
								}
							}					
						}
						
					}

				}
			}

			$merchant_id = get_merchant_id_by_region( $marketplace_ids[0] );

			$header = array(
				'sellerId'    => $merchant_id,
				'version'     => '2.0',
				'issueLocale' => 'in',
			);

			$product_info = array(
				'header'   => $header,
				'messages' => $messages,
			);

			$json_data = wp_json_encode( $product_info );
			$this->amz_com_opts_mng->writeStringToFile( $json_data, $filename );
			
			return array(
				'validity'    => 1 < $i ?  true : false,
				'content'     => $json_data,
				'SKUs'        => $SKUs_array,
				'errorsLists' => $errorsArray,
				'woo_price_array' => $woo_price_array
			);


		}

		/**
		 * Generate inventory JSON patch for Amazon API
		 *
		 * @param int $quantity The quantity to set
		 * @return array JSON patch array for inventory update
		 */
		public function ced_amz_inventory_json_patch( $quantity ) {

			return array(
				array(
					'fulfillment_channel_code' => 'DEFAULT',
					'quantity' => $quantity,
				)
			);
		}

	}

endif;
