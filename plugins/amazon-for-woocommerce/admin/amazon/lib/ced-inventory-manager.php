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

if ( ! class_exists( 'Ced_Amazon_Inventory_Feed_Manager' ) ) :

	/**
	 * Woo-marketplace feed submission functionality.
	 *
	 * @since      1.0.0
	 * @package    Amazon_Integration_For_Woocommerce
	 * @subpackage Amazon_Integration_For_Woocommerce/admin/amazon/lib
	 */
	class Ced_Amazon_Inventory_Feed_Manager {

		/**
		 * The Instace of Ced_Amazon_Inventory_Feed_Manager.
		 *
		 * @since    1.0.0
		 * @var      $_instance   The Instance of Ced_Amazon_Inventory_Feed_Manager class.
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
		 * Ced_Amazon_Inventory_Feed_Manager Instance.
		 *
		 * Ensures only one instance of Ced_Amazon_Inventory_Feed_Manager is loaded or can be loaded.
		 *
		 * @name get_instance()
		 * @since 1.0.0
		 * @static
		 * @return Ced_Amazon_Inventory_Feed_Manager instance.
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
		

		public function ced_amz_inventory_json_array( $i, $sku, $quantity ) {

			$patches = array();
			if ( 0 <= $quantity ) {
			   $patches['fulfillment_availability'] = $this->ced_amz_inventory_json_patch( $quantity );
			}
 
			return  !empty( $patches ) ? array(
				'messageId' =>  $i,
				'sku' => $sku,
				'operationType' => 'PARTIAL_UPDATE',
				'productType' => 'PRODUCT',
				'attributes' => $patches

			) : array() ;
		}

		public function ced_amz_inventory_json_patch( $quantity ) {

			return  array(
						array(
							'fulfillment_channel_code' => 'DEFAULT',
							'quantity' => $quantity,
							
						)
					);
			
		}

		public function ced_amz_listing_inventory_json_patch( $quantity, $marketplace_id ) {
			if ( empty( $quantity ) || empty( $marketplace_id ) ) {
				return array();
			}

			return array(
				array(
					'op'   => 'replace',
					'path' => '/attributes/fulfillment_availability',
					'value' => array(
						array(
							'fulfillment_channel_code' => 'DEFAULT',
							'quantity' => (int) $quantity
						),
					),
				),
			);
		}


		public function ced_amz_update_inventory($product_id, $product, $sku, $marketplace_id, $remote_shop_id) {

			$logger                = wc_get_logger();
			$context               = array( 'source' => 'ced_amazon_manual_scheduler' );
			$qty                   = $product->get_stock_quantity();
			$qty                   = $this->ced_amz_retrieve_final_qty( $product_id, $qty );
			$productLevelAmazonSKU = get_post_meta( $product_id, 'item_sku', true );
			$sku                   = ! empty( $productLevelAmazonSKU ) ? $productLevelAmazonSKU : $product->get_sku();
			
			if ( isset( $qty ) && isset( $sku ) && ! empty( $sku ) ) {
				
				// Prepare JSON Patch for inventory
				$patches = $this->ced_amz_listing_inventory_json_patch( $qty, $marketplace_id );
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
					$logger->info('Product Inventory is updated successfully', $context);
				} elseif (isset($response_data['status']) && 'INVALID' == $response_data['status'] && empty($response_data['issues'])) {
					$logger->info(wc_print_r($response['issues'], true), $context);
				} else {
					$logger->info('Product Data is empty', $context);
				}

				return array(
					'status' => $response_data['status'],
					'value'  => $qty
				);
				
			}
		}

		public function ced_amazon_manual_inventory_update_listing( $params = array() ) {

			$logger           = wc_get_logger();
			$context          = array( 'source' => 'ced_amazon_manual_inventory_sync' );
			$seller_id        = $params['seller_id'] ?? '';
			$marketplace_ids  = $params['marketplace_ids'] ?? '';
			$remote_shop_id   = $params['remote_shop_id'] ?? '';
			$products_to_sync = $params['product_ids'] ?? array();
			$feed_id          = $params['feed-id'] ?? '';

			$logger->info( wc_print_r( 'params starts', true) , $context );
			$logger->info( wc_print_r( $params, true) , $context );

			$logger->info( 'Working on the function', $context );

			$marketplace_id = $marketplace_ids[0] ?? '';

			if ( empty( $seller_id ) || empty( $marketplace_id ) || empty( $remote_shop_id ) ) {
				$logger->info( 'Missing seller, marketplace, or remote shop ID.', $context );
				return;
			}

			$chunk_size      = 5;
			$total_processed = 0;

			$current_feed = $this->amz_com_opts_mng->get_amazon_synthetic_feed( $feed_id );
			$logger->info( wc_print_r( $current_feed, true) , $context );
		   
			$SKUs_array =  $current_feed['sku'] ?? array();

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
						$response                     = $this->ced_amz_update_inventory( $product_id, $product, $sku, $marketplace_id, $remote_shop_id );
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
								$response                               = $this->ced_amz_update_inventory( $variation_id, $variation_product, $variation_sku, $marketplace_id, $remote_shop_id );
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
			$this->amzQueueManager->ced_amz_update_queue_action( 'ced_amazon_manual_inventory_update_action', $params );
			$logger->info( "Processed $total_processed products successfully.", $context );

			$this->amz_com_opts_mng->update_amazon_synthetic_feed( $feed_id, $SKUs_array );

		}


		/**
		 * Create Inventory File
		 *
		 * @name create_inventory_data_file
		 * @since 1.0.0
		 */
		public function create_inventory_data_file( $proIds, $shop_location = '', $filename = '', $region = '', $marketplace_ids = array() ) {

			$logger  = wc_get_logger();
			$context = array( 'source' => 'ced_amazon_inventory_sync' );
			$logger->info( wc_print_r( ced_woo_timestamp(), true ), $context );

			$errorsArray = array();

			if ( empty( $filename )  ) {
				$errorsArray['File'] = 'file name is not availbale';
				return false;
			}


			/** Get global settings data */
			$seller_global_settings = array();
			$global_settings        = get_option( 'ced_amazon_global_settings' );
			
			$i = 1;
			/** To hold skus and amazon stock that has send on the feed to update */ 
			$SKUs_array = array();

			/** To prepare the json feed content */ 
			$messages = array();

			/** To hold product_id and woo stock that has been send on the feed to update */ 
			$woo_inv_array = array();

			/** To hold products that could not be updated */ 
			$errorsArray = array();

			foreach ( $proIds as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( ! is_object( $product ) ) {
					continue;
				}
				$productType = $product->get_type();

				if ( 'simple' == $productType ) {

					$amazonxmlarray = array();
					$sku            = $product->get_sku();

					/** Stock quantity thershold */
					$qty                   = $product->get_stock_quantity();
					$qty                   = $this->ced_amz_retrieve_final_qty( $product_id, $qty );
					$productLevelAmazonSKU = get_post_meta( $product_id, 'item_sku', true );
					if ( isset( $productLevelAmazonSKU ) && ! empty( $productLevelAmazonSKU ) ) {
						$sku = $productLevelAmazonSKU;
						
					}
					
					if ( isset( $qty ) && isset( $sku ) && ! empty( $sku ) ) {
						
						$quantity                   = ( $qty >= 0 ) ? $qty : 0;
						$woo_inv_array[$product_id] = $quantity;
						
						$SKUs_array[ $sku ] = array(
							'type'         => 'Simple',
							'value'        => $quantity,
							'product_id'   => $product_id,
							'product_sku'  => $sku,
							'product_name' => $product->get_name(),
						);
						
						$message = $this->ced_amz_inventory_json_array( $i, $sku, $quantity );
						if ( !empty( $message ) ) {
							++$i;
							$messages[] = $message;
						}
						
						
					} else {
						$errorsArray[] = $product_id; 
					}
				} elseif ( 'variable' == $productType ) {

					$amazonxmlarray = array();

					$sku = $product->get_sku();
					if ( ! empty( $sku ) ) {
						$parent_sku = $product->get_sku();
					}

					/** Stock quantity thershold */
					$productLevelAmazonSKU = get_post_meta( $product_id, 'item_sku', true );
					if ( isset( $productLevelAmazonSKU ) && ! empty( $productLevelAmazonSKU ) ) {
						$sku        = $productLevelAmazonSKU;
						$parent_sku = $productLevelAmazonSKU;
					}

					$all_available_var = $product->get_children();

					foreach ( $all_available_var as $var_key => $var_value ) {

						$product = wc_get_product( $var_value );
						$sku     = $product->get_sku();

						/** Stock quantity thershold */
						$qty = $product->get_stock_quantity();
						$qty = $this->ced_amz_retrieve_final_qty( $product_id, $qty );

						$productLevelAmazonSKU = get_post_meta( $var_value, 'item_sku', true );
						if ( isset( $productLevelAmazonSKU ) && ! empty( $productLevelAmazonSKU ) ) {
							$sku = $productLevelAmazonSKU;
						}

						if ( isset( $parent_sku ) && $sku == $parent_sku ) {
							$sku = '';
						}

						if ( isset( $qty ) && isset( $sku ) && ! empty( $sku ) ) {

							$quantity                   = ( $qty >= 0 ) ? $qty : 0;
							$woo_inv_array[$product_id] = $quantity;
							$SKUs_array[ $sku ]         = array(
								'type'         => 'Variation',
								'parent_sku'   => $parent_sku,
								'value'        => $quantity,
								'product_id'   => $var_value,
								'product_sku'  => $sku,
								'product_name' => $product->get_name(),
							);

							$message = $this->ced_amz_inventory_json_array( $i, $sku, $quantity );
							if ( !empty( $message ) ) {
								$messages[] = $message;
								++$i;
							}
							
						} else {
							$errorsArray[] = $var_value; 
						}
					}
				}
			}

			
			$merchant_id = get_merchant_id_by_region( $marketplace_ids[0] );
			$header      = array(
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
				'validity'    => 1 < $i ? true: false,
				'content'     => $json_data,
				'SKUs'        => $SKUs_array,
				'errorsLists' => $errorsArray,
				'woo_inv_array' => $woo_inv_array
			);

		}



		/**
		 * Update bulk products inventory on Amazon
		 *
		 * @name ced_amazon_bulk_inventory_update
		 * @since 1.0.0
		 */
		public function ced_amazon_bulk_inventory_update( $bulk_inventory_params  ) {
		   
			$product_ids     = isset( $bulk_inventory_params['products'] ) ? $bulk_inventory_params['products'] : array(); 
			$mplocation      = isset( $bulk_inventory_params['mplocation'] ) ? $bulk_inventory_params['mplocation'] : '';
			$remote_shop_id  = isset( $bulk_inventory_params['remote_shop_id'] ) ? $bulk_inventory_params['remote_shop_id'] : '';
			$marketplace_ids = isset( $bulk_inventory_params['marketplace_ids'] ) ? $bulk_inventory_params['marketplace_ids'] : array(); 
			$region          = isset( $bulk_inventory_params['region'] ) ? $bulk_inventory_params['region'] : '';
			$extraParams     = isset( $bulk_inventory_params['extraParams'] ) ? $bulk_inventory_params['extraParams'] : array();

			set_time_limit( 600 );
			wp_raise_memory_limit( -1 );
			ignore_user_abort( true );

			// Log file name
			$logger  = wc_get_logger();
			$context = array( 'source' => 'ced_amazon_inventory_sync' );
			
			if ( empty( $mplocation ) ) {
				$logger->info( "Marketplace location is missing during bulk inventory update. Please check! \n", $context );
				return;
			}

			if ( is_array( $product_ids ) && ! empty( $product_ids ) ) {
				$products = $product_ids;
			} else {
				$logger->info( "No products were found to update inventory on Amazon! \n", $context );
				return;
			}

			$opt_type =  isset( $extraParams['status'] ) && 'pending' ==  $extraParams['status']  ? 'Manual' : 'Automatic';

			$fileName                = 'inventory-' . $mplocation . '.json';
			$inventory_content_array = $this->create_inventory_data_file( $products, $mplocation, $fileName, $region, $marketplace_ids ); // create inventory xml file
			$inventory_content       = isset( $inventory_content_array['content'] ) ? $inventory_content_array['content'] : '';
			$woo_inv_array           = isset( $inventory_content_array['woo_inv_array'] ) ? $inventory_content_array['woo_inv_array'] : '';

			$decoded_inventory_content = json_decode( $inventory_content, true );
			if ( isset( $decoded_inventory_content['messages'] ) && empty( $decoded_inventory_content['messages'] ) ) {
				$logger->info( "No messages were found to update inventory on Amazon! \n", $context );
				return;
			}

			try {

				$logger->info( wc_print_r( '------------------------------------ GOINT TO CALL AMAZON JSON API ----------------------------------', true ), $context );
			   
				$feed_action =  'JSON_LISTINGS_FEED';

				// Inventory update feed API call using SP-API endpoint
				$feed_topic = 'create-feed';
				$feed_data  = array(
					'feed_action'    => $feed_action ,
					'feed_content'   => $inventory_content,
					'remote_shop_id' => $remote_shop_id,
					'marketplace_id' => $marketplace_ids
				);

				$logger->info( wc_print_r( 'feed params before making api call', true ), $context );
				$logger->info( wc_print_r( $feed_data, true ), $context );

				$feed_reponse = $this->amzonCurlRequestInstance->ced_amazon_serverless_process( $feed_topic, $feed_data, 'POST' );
				
				// modified feed_action
				if ( 'JSON_LISTINGS_FEED' == $feed_action ) {
					$feed_action = 'JSON_LISTINGS_FEED_INVENTORY';
				}

				$response_body = wp_remote_retrieve_body($feed_reponse);
				$response_data = json_decode($response_body, true);

				$logger->info( wc_print_r( $response_body, true ), $context );

				// check throttle if the feed type is JSON
				$throttleResponse        = $this->amz_com_opts_mng->ced_amz_check_json_feed_throttle( $response_data, 'ced_amazon_create_feed_throttle');
				$decodedThrottleResponse = json_decode( $throttleResponse, true );
				if ( is_array( $decodedThrottleResponse ) && isset(  $decodedThrottleResponse['message'] ) && strpos( $decodedThrottleResponse['message'], 'Quoto Exceeded' ) === 0 ) {
					return $throttleResponse;
				}

				if ( is_wp_error( $feed_reponse ) ) {
					$logger->info( wc_print_r( $feed_reponse, true ), $context );
				}

				$inventoryuploadreponse = json_decode( $feed_reponse['body'], true );
				$inventoryuploadreponse = isset( $inventoryuploadreponse['data'] ) ? $inventoryuploadreponse['data'] : array();

				if ( isset( $inventoryuploadreponse['success'] ) && 'false' == $inventoryuploadreponse['success'] ) {
					$logger->info( wc_print_r( $feed_reponse, true ), $context );
					return;
				}

				if ( isset( $inventoryuploadreponse['feed_id'] ) && ! empty( $inventoryuploadreponse['feed_id'] ) ) {
					$feedId = $inventoryuploadreponse['feed_id'];
					$this->amz_com_opts_mng->insertFeedInfoToDatabase( $feedId, $feed_action, $mplocation, $inventory_content_array['SKUs'], $opt_type );

					// update queued action status
					if ( isset( $extraParams['status'] ) && 'pending' ==  $extraParams['status']  ) {
					
						$extraParams['status']  = 'completed';
						$extraParams['feed-id'] = $feedId;
						$extraParams['context'] = $context;

						$this->amzQueueManager->ced_amz_update_queue_action( 'ced_amazon_inventory_scheduler_job_' . $region , $extraParams );
					}

					// update action timings
					$ced_amz_action_timings                 = get_option( 'ced_amz_action_timings' , array() );
					$ced_amz_action_timings['created-feed'] = strtotime('now');
					update_option( 'ced_amz_action_timings', $ced_amz_action_timings );

					$logger->info( "Bulk inventory feed has processed and submitted. \n", $context );

					// schedule a single event to verify the updation of inventory sync
					if ( $extraParams['first_sync'] ) {
						$logger->info( wc_print_r( '--------------------------------------- CREATING A SINGLE EVENT OF GET FEED FOR FIRST INVENTORY SYNC ---------------------------------- ', true ), $context );
						$first_sync = true;
					} else {
						$logger->info( wc_print_r( '--------------------------------------- CREATING A SINGLE EVENT OF GET FEED FOR REGULAR INVENTORY SYNC ---------------------------------- ', true ), $context );
						$first_sync = false;
					}
						
					$event_time	= time() + 120;
					$hook_name  = 'ced_amazon_get_feed_data';
					$hook_data  = array( array(
						'feed_id'   => $feedId, 
						'marketplace_ids' => $marketplace_ids,
						'region'     => $region,
						'user_id'    => $remote_shop_id,
						'action'     => 'inventory',
						'mplocation' => $mplocation,
						'first_sync' => $first_sync,
						'woo_inv_array' => $woo_inv_array
						
					) ) ;

					if ( function_exists( 'as_schedule_single_action' ) ) {
						as_schedule_single_action( $event_time, $hook_name, $hook_data );
					} else {
						wp_schedule_single_event( $event_time, $hook_name, $hook_data  );
					}

					return;

				} else {
					$logger->info( "Something went wrong with the inventory feed submission. Please check the inventory feed URL! \n", $context );
					return;
				}
			} catch ( Exception $e ) {
				$logger->info( wc_print_r( 'An error occured: ', true ), $context );
				$logger->info( wc_print_r( $e->getMessage(), true ), $context );
				return;
			}

			
		}


		/**
		 * Update products inventory on Amazon manually
		 *
		 * @name ced_amazon_manual_inventory_update
		 * @since 1.0.0
		 */
		public function ced_amazon_manual_inventory_update( $product_ids = array(), $mplocation = '', $seller_id = '' ) {
			
			$logger  = wc_get_logger();
			$context = array( 'source' => 'ced_amazon_manual_inventory_sync' );
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

			if ( ! is_array( $product_ids ) || empty( $product_ids ) ) {
				return wp_json_encode( array(
					'message' => 'No products were found to publish on Amazon!',
					'classes' => 'notice notice-error is-dismissable'
				));
			}

			$SKUs_array        = prepare_skus_array( $product_ids );
			$current_timestamp = time();
			
			$this->amz_com_opts_mng->insertFeedInfoToDatabase( $current_timestamp, 'JSON_LISTINGS_FEED_INVENTORY', $mplocation, $SKUs_array, 'Manual' );
			$logger->info( wc_print_r( 'Stored product IDs for seller: ' . $seller_id, true ), $context );
			
			$this->amzQueueManager->ced_amz_schedule_single_event( 'ced_amazon_manual_inventory_update_action', $args = array(
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

			$logger->info( wc_print_r( 'Inventory queue action scheduled.', true ), $context );
			return wp_json_encode( array(
				'message' => 'Inventory action has been added to the queue.',
				'classes' => 'notice notice-success is-dismissable'
			));

		}


		public function ced_amz_retrieve_final_qty( $product_id, $qty ) {
			$logger  = wc_get_logger();
			$context = [ 'source' => 'ced_amazon_inventory_sync' ];
			$logger->info( 'Initial qty: ' . print_r( $qty, true ), $context );
			
			$product_level_qty = get_post_meta( $product_id, 'quantity', true );
			if ( isset( $product_level_qty ) && is_numeric( $product_level_qty ) ) {
				$qty = floatval( $product_level_qty );
			} else {
				$qty = floatval( $qty );
			}
			
			$general_settings = get_option( 'ced_amazon_general_options', [] );
			$general_options  = $general_settings['general_options'] ?? [];
			
			$reserve_stock = isset( $general_options['ced_amazon_rsrve_stck']['default'] )
			? floatval( $general_options['ced_amazon_rsrve_stck']['default'] )
			: 0;
			
			$qty -= $reserve_stock;
			if ( $qty < 0 ) {
				$qty = 0;
			}
			
			$max_stock_raw = $general_options['ced_amazon_listing_stock']['default'] ?? '';

			if ( '' !== $max_stock_raw && null !== $max_stock_raw ) {
				$max_stock = floatval( $max_stock_raw );
				if ( $max_stock >= 0 ) {
					$qty = min( $qty, $max_stock );
				}
			}

			$logger->info( 'Final qty after applying reserve & max limit: ' . $qty, $context );

			return $qty;
		}



	}

endif;
