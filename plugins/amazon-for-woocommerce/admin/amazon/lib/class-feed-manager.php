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

if ( ! class_exists( 'Ced_Umb_Amazon_Feed_Manager' ) ) :

	/**
	 * Woo-marketplace feed submission functionality.
	 *
	 * Upload/update products, inventory, price, image, shipment from
	 * WooCommerce to Amazon.
	 *
	 * @since      1.0.0
	 * @package    Amazon_Integration_For_Woocommerce
	 * @subpackage Amazon_Integration_For_Woocommerce/admin/amazon/lib
	 */
	class Ced_Umb_Amazon_Feed_Manager {

		/**
		 * The Instace of Ced_Umb_Amazon_Feed_Manager.
		 *
		 * @since    1.0.0
		 * @var      $_instance   The Instance of Ced_Umb_Amazon_Feed_Manager class.
		 */
		private static $_instance;
		public $amazon_xml_lib;
		public $product_upload_notice;
		public $feed_xml_notice;
		public $amzonCurlRequestInstance;
		public $amzQueueManager;
		public $amzPriceManager;
		public $amzInventoryManager;
		public $amzImageManager;
		public $region;
		public $marketplace;
		public $seller_id;
		public $remote_shop_id;
		public $product_upload_errors;
		public $amz_com_opts_mng;
		
		
		/**
		 * Ced_Umb_Amazon_Feed_Manager Instance.
		 *
		 * Ensures only one instance of Ced_Umb_Amazon_Feed_Manager is loaded or can be loaded.
		 *
		 * @name get_instance()
		 * @since 1.0.0
		 * @static
		 * @return Ced_Umb_Amazon_Feed_Manager instance.
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

			require_once 'class-amazon-xml-lib.php';
			$this->amazon_xml_lib = new Ced_Amzon_XML_Lib();
			
			// Set default marketplace for Amazon integration
			$this->marketplace = 'amazon';

			$classes_to_load = array(
				'amzonCurlRequestInstance' => array(
					'path' => CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php',
					'class' => 'Ced_Amazon_Curl_Request',
				),
				'amzQueueManager' => array(
					'path' => CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-queue-manager.php',
					'class' => 'Ced_Amazon_Queue_Manager',
				),
				'amzPriceManager' => array(
					'path' => CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-price-manager.php',
					'class' => 'Ced_Amazon_Price_Feed_Manager',
				),
				'amzInventoryManager' => array(
					'path' => CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-inventory-manager.php',
					'class' => 'Ced_Amazon_Inventory_Feed_Manager',
				),
				'amzImageManager' => array(
					'path' => CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-image-manager.php',
					'class' => 'Ced_Amazon_Image_Feed_Manager',
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

			// Amazon order shipment ajax
			add_action( 'wp_ajax_umb_amazon_shipment_order', array( $this, 'umb_amazon_shipment_order' ) );
			add_action( 'wp_ajax_nopriv_umb_amazon_shipment_order', array( $this, 'umb_amazon_shipment_order' ) );
			
			add_action( 'wp_ajax_umb_amazon_check_feed_status', array( $this, 'umb_amazon_check_feed_status' ) );
			add_action( 'wp_ajax_nopriv_umb_amazon_check_feed_status', array( $this, 'umb_amazon_check_feed_status' ) );
			
		}

		/**
		 * Handle product management actions.
		 *
		 * Handling all product management actions i.e. upload products, update inventory, price and images.
		 *
		 * @name process_feed_request()
		 * @link  http://www.cedcommerce.com/
		 * @since 1.0.0
		 * @return bool true|false. 
		 */
		public function process_feed_request( $action = '', $marketplace = '', $proIds = array(), $mplocation = '', $seller_id = '', $remote_shop_id = '' ) {

			if ( empty( $action ) || empty( $marketplace ) || ! is_array( $proIds ) || empty( $mplocation ) || empty( $seller_id ) ) {
				$message = 'Either action, marketplace, products, mplocation, or seller_id is missing to perform the action. Please try again!';
				$classes = 'error is-dismissable';
				$error   = array(
					'message' => $message,
					'classes' => $classes,
				);
				return wp_json_encode( $error );
			} elseif ( ! empty( $action ) ) {

				$this->marketplace    = $marketplace;
				$this->seller_id      = $seller_id;
				$this->remote_shop_id = $remote_shop_id;

				return $this->upload_products_details( $proIds, $action, $mplocation, $seller_id );

			} else {
				return;
			}
		}

		/**
		 * Upload selected products on selected marketplace.
		 *
		 * @since 1.0.0
		 * @param string $marketplace
		 * @param array  $proIds
		 * @return json string
		 * @link  http://www.cedcommerce.com/
		 */
		public function upload_products_details( $proIds = '', $action = '', $mplocation = '', $seller_id = '' ) {

			// Check if the required parameters are set
			if ( ! is_array( $proIds ) || empty( $proIds ) || empty( $action ) || empty( $mplocation ) || empty( $seller_id ) ) {
				$message = 'Either action, marketplace, products, mplocation, or seller_id is missing to perform the action. Please try again!';
				$classes = 'error is-dismissable';
				$error   = array(
					'message' => $message,
					'classes' => $classes,
				);
				return wp_json_encode( $error );
			}

			if ( 'upload_product' == $action || 'update_product' == $action || 'delete_product' == $action || 'look_up' == $action || 'validate_product' == $action ) {
				
				if ( is_array( $proIds ) && ! empty( $proIds ) ) {
					$final_pro_ids  = array();
					$pro_ids_holder = $proIds;
					foreach ( $pro_ids_holder as $key => $pro_id ) {
						
						$product = wc_get_product( $pro_id );
						if ( ! is_object( $product ) ) {
							continue;
						}

						if ( '' == $product->get_sku() ) {
							$this->product_upload_errors[$pro_id]['errors'][] ='No SKU found.'; 
							$c = array_search( $pro_id, $proIds);
							unset( $proIds[$c] );
							continue;
						}

						$final_pro_ids[ $pro_id ] = $pro_id;
						$product_type             = $product->get_type();
						if ( 'variable' == $product_type ) {
							$children_ids = $product->get_children();
							foreach ( $children_ids as $key => $child_id ) {
								$final_pro_ids[ $child_id ] = $child_id;
							}
						}
					}

					if ( isset( $final_pro_ids ) && ! empty( $final_pro_ids ) && is_array( $final_pro_ids ) ) {
						$proIds = array_values( $final_pro_ids );
					}
				}
			}


			if ( is_array( $proIds ) && ! empty( $proIds ) ) {

				switch ( $action ) {
					case 'upload_product':
						return $this->uploadProductIds( $proIds, '', $mplocation, false );
					break;

					case 'update_product':
						return $this->uploadProductIds( $proIds, '', $mplocation, false );
					break;

					case 'relist_product':
						return $this->ced_amazon_relist_product( $proIds, $mplocation, $seller_id );
					break;

					case 'update_inventory':
						return $this->amzInventoryManager->ced_amazon_manual_inventory_update( $proIds, $mplocation, $seller_id );
					break;

					case 'update_price':
						return $this->amzPriceManager->ced_amazon_manual_price_update( $proIds, $mplocation, $seller_id, 'Manual', false, array() );
					break;

					case 'update_images':
						return $this->amzImageManager->ced_amazon_bulk_image_update( $proIds, $mplocation, $seller_id );
					break;

					case 'delete_product':
						return $this->ced_amazon_delete_product( $proIds, $mplocation, $seller_id );
					break;
					case 'look_up':
						return $this->ced_amazon_look_up( $proIds, $mplocation, $seller_id );
					break;
					case 'validate_product':
						return $this->uploadProductIds( $proIds, '', $mplocation, true );
					break;
					default:
						return;
					break;
				}
			}

			echo '222222222222222';
			$message = esc_attr_e( 'An unexpected error occurred. Please try again.', 'amazon-for-woocommerce' );
			$classes = 'notice notice-error is-dismissable';
			$error   = array(
				'message' => $message, 
				'classes' => $classes,
			);
			return wp_json_encode( $error );
		}


		public function uploadProductIds( $proIds = array(), $profileID = '', $mplocation = '', $is_validation = false, $template_name = '', $template_content = '', $product_ids_for_feed = array() ) {

			set_time_limit( 600 );
			wp_raise_memory_limit( -1 );
			ignore_user_abort( true );

			$seller_id = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';

			$logger  = wc_get_logger();
			$context = array( 'source' => 'ced_amazon_upload_products' );

			// throttle check
			$ced_amazon_create_feed_throttle = get_transient( 'ced_amazon_create_feed_throttle' );

			// if ( $ced_amazon_create_feed_throttle ) {
			// 	$notice['message'] = 'Create feed API call limit exceeded. Please try after 5 mins.';
			// 	$notice['classes'] = 'notice notice-error is-dismissable';
			// 	return wp_json_encode( $notice );
			// }

			if ( empty( $mplocation ) ) {
				$notice['message'] = 'Marketplace location is missing!';
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );
			}

			if ( empty( $seller_id ) ) {
				$notice['message'] = 'Seller ID is missing!';
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );
			}

			if ( isset( $proIds ) && ! empty( $proIds ) ) {

				try {

					// Early product eligibility check - before any data processing
					$logger->info( 'Starting early product eligibility check for ' . count($proIds) . ' products', $context );
					
					// Get amazon profiles list for eligibility check
					global $wpdb;
					$amazon_profiles      = $wpdb->get_results( $wpdb->prepare( "SELECT id, product_type FROM {$wpdb->prefix}ced_amazon_profiles WHERE `seller_id` = %s ", $seller_id ), 'ARRAY_A' );
					$amazon_profiles_list = array_combine(array_column($amazon_profiles, 'id'), array_column($amazon_profiles, 'product_type'));
					
					$finalAllProductIdsWithProfileId = array();
					foreach ( $proIds as $pro_key => $product_id ) {

						// new code start: get profile name based on product woo category
						$mod_product   = wc_get_product( $product_id );
						$mod_parent_id = $mod_product->get_parent_id();
	
						$product_types[ $product_id ] = $mod_product->get_type();
	
						if ( 0 == $mod_parent_id || '0' == $mod_parent_id ) {
							$terms = get_the_terms( $product_id, 'product_cat' );
						} else {
							$terms = get_the_terms( $mod_parent_id, 'product_cat' );
						}
	
						$term_array = array();
						if ( $terms && ! is_wp_error( $terms ) ) {
							foreach ( $terms as $term ) {
								$term_array[] = $term->term_id;
							}
						}
	
						$profileID              = '';
						$ced_woo_amazon_mapping = get_option( 'ced_woo_amazon_mapping', array() );
						$ced_woo_amazon_mapping = isset( $ced_woo_amazon_mapping[ $seller_id ] ) ? $ced_woo_amazon_mapping[ $seller_id ] : array();
	
						if ( ! empty( $ced_woo_amazon_mapping ) ) {
							foreach ( $ced_woo_amazon_mapping as $key => $woo_cat_array ) {
	
								$match_woo_cat = array_intersect( $woo_cat_array, $term_array );
								if ( is_array( $match_woo_cat ) && ! empty( $match_woo_cat ) ) {
	
									$profileID = $key;
									break;
	
								}
							}
						}
	
						// new code end.
	
						$profileIDPerProduct = '';
						if ( ! empty( $profileID ) ) {
							$profileIDPerProduct = $profileID;
						} else {
							$finalAllLoaderProductIds[ $product_id ] = $product_id;
							continue;
						}
	
						$finalAllProductIdsWithProfileId[ $product_id ] = $profileIDPerProduct;

						
					}

					// Perform basic eligibility check on product IDs only
					$earlyEligibilityResult = $this->perform_early_eligibility_check( $proIds, $finalAllProductIdsWithProfileId, $mplocation, $logger, $context );
					
					$errorArray             = $earlyEligibilityResult['errorArray']; // array of error products including variations
					$all_error_products_ids = $earlyEligibilityResult['all_error_products_ids']; // array of all error products ids including variations
					$healthyProducts        = $earlyEligibilityResult['eligibleProducts']; // array of healthy products including variations
					$skus_array             = $earlyEligibilityResult['skus_array']; // array of skus of all products including variations

					// If no eligible products, return error early
					if ( empty( $healthyProducts ) ) {
						$first_err_message = $errorArray[0]['reason'] ?? '';
						$logger->error( 'No eligible products found - all products have validation errors', $context );
						$notice['message'] = 'No eligible products found for upload. All products have validation errors. ' . $first_err_message;
						$notice['classes'] = 'notice notice-error is-dismissable';
						return wp_json_encode( $notice );
					}
					
					// Early product counting
					$logger->info( 'Starting early product counting', $context );
					$productCounts = $this->calculate_product_counts( $skus_array, $healthyProducts, $errorArray, $logger, $context );
					
					$healthyProductCount               = (int) $productCounts['healthyProductCount'];
					$healthyProductIds                 = $productCounts['healthyProductIds']; 
					$healthyProductCountWithVariations = (int) $productCounts['healthyProductCountWithVariations'] ;
					$healthyProductIdsWithVariations   = $productCounts['healthyProductIdsWithVariations']; 
					
					$logger->info( 'Early eligibility check results - Error count: ' . count($errorArray) . ', Eligible count: ' . count($healthyProductIds) . ', SKU count: ' . count($skus_array), $context );

					// add sku logic here

					$allDetails = $this->makeProductXMLFileToSendOnAmazon( $proIds, $profileID, $mplocation );
					$allDetails = json_decode( $allDetails, true );
					
					$logger->info( 'All details structure: ' . wc_print_r( array_keys( $allDetails ), true ), $context );
					if ( isset( $allDetails['profile_with_pro_ids'] ) ) {
						$logger->info( 'Profile with product IDs count: ' . count( $allDetails['profile_with_pro_ids'] ), $context );
					}

					if ( !isset($allDetails['profile_ids']) || empty($allDetails['profile_ids']) ) {
						$notice['message'] = 'The product has not been assigned to any template!';
						$notice['classes'] = 'notice notice-error is-dismissable';
						return wp_json_encode( $notice );
					}

					$validation_errors   = array();
					$total_product_count = 0;

					if ( !empty( $allDetails['profile_with_pro_ids'] ) ) {
						foreach ( $allDetails['profile_with_pro_ids'] as $pro_id => $product_array ) {
							$total_product_count += count( $product_array );
						}
					}

					global $wpdb;
					$amazon_profiles      = $wpdb->get_results( $wpdb->prepare( "SELECT id, product_type FROM {$wpdb->prefix}ced_amazon_profiles WHERE `seller_id` = %s ", $seller_id ), 'ARRAY_A' );
					$amazon_profiles_list = array_combine(array_column($amazon_profiles, 'id'), array_column($amazon_profiles, 'product_type'));
					
					$logger->info( 'Amazon profiles list: ' . wc_print_r( $amazon_profiles_list, true ), $context );
					
					$mod_all_details = array();
					$language_tag    = '';

					// Get remote shop ID from saved configuration
					$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );
					$this->remote_shop_id = '';
					$this->seller_id      = $seller_id;
					if ( isset( $saved_amazon_details[ $seller_id ] ) && ! empty( $saved_amazon_details[ $seller_id ] ) && is_array( $saved_amazon_details[ $seller_id ] ) ) {
						$shop_data            = $saved_amazon_details[ $seller_id ];
						$this->remote_shop_id = isset( $shop_data['seller_next_shop_id'] ) ? $shop_data['seller_next_shop_id'] : '';
					}
					
					$logger->info( 'Remote shop ID: ' . $this->remote_shop_id, $context );
					$logger->info( 'Seller ID: ' . $this->seller_id, $context );
					
					// CHECK IF THIS IS VALIDATION OR UPLOAD OPERATION
					if ( $is_validation ) {
						
						// VALIDATION OPERATION - Prepare data and call validation API directly (don't save to file)
						$logger->info( 'Starting product validation with direct API call for ' . count($proIds) . ' products', $context );
						
						// Use process_and_save_product_data function with save_files = false for validation
						$mod_all_details = $this->process_and_save_product_data( $allDetails, $amazon_profiles_list, $mplocation, $seller_id, $logger, $context, false );
						
						// Call validation API directly
						$amzonCurlRequest = CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php';

						if ( file_exists( $amzonCurlRequest ) ) {
							
							require_once $amzonCurlRequest;
							$this->amzonCurlRequestInstance = new Ced_Amazon_Curl_Request();

							// create feed for product validation
							$validation_topic = 'validate-schema';
							$validation_data  = array(
								'content'        => $mod_all_details,
								'remote_shop_id' => $this->remote_shop_id,
							);

							$reponse            = $this->amzonCurlRequestInstance->ced_amazon_serverless_process( $validation_topic, $validation_data, 'POST' );
							$validation_reponse = wp_remote_retrieve_body( $reponse );
							$validation_reponse = json_decode( $validation_reponse, true );

							if ( isset( $validation_reponse['results'] ) && is_array( $validation_reponse['results'] ) ) {
								foreach ( $validation_reponse['results'] as $pro_id => $errors ) {

									$arr = array( 'woocommerce_validation' => $errors );
									update_post_meta( $pro_id, 'ced_amz_json_validator_error_' . $seller_id , $arr );

									if ( 'Validation successful' == $errors ) {
										update_post_meta( $pro_id, 'ced_amazon_final_structure_data_' . $mplocation, $mod_all_details['product_data'][$pro_id] );
									}

								}
							}

							if ( isset( $validation_reponse['success'] ) && $validation_reponse['success'] ) {
								$notice['message'] = "Product validation is complete. To view specific errors for a product, please check the 'Amazon Validation Errors'. Page will refresh in next 5 seconds.";
								$notice['classes'] = 'notice notice-success is-dismissable';
								$notice['refresh'] =  true;
								return wp_json_encode( $notice );

							} else {
								$notice['message'] = isset( $validation_errors['message'] ) ? $validation_errors['message'] : 'Unable to validate products';
								$notice['classes'] = 'notice notice-error is-dismissable';
								return wp_json_encode( $notice );

							}
						

						} else {
							$notice['message'] = 'Unable to locate the cURL request file necessary to validate the products.';
							$notice['classes'] = 'notice notice-error';
							return wp_json_encode( $notice );
							
						}

					} else {
						
						// UPLOAD OPERATION - Only write product data to file and add to queue database
						$logger->info( 'Starting product upload preparation and queue setup for ' . count($proIds) . ' products', $context );
						
						// Optimized product data processing and file saving
						$mod_all_details = $this->process_and_save_product_data( $allDetails, $amazon_profiles_list, $mplocation, $seller_id, $logger, $context );
						
						$logger->info( 'Modified all details structure: ' . wc_print_r( array_keys( $mod_all_details ), true ), $context );
						if ( isset( $mod_all_details['product_data'] ) ) {
							$logger->info( 'Product data count: ' . count( $mod_all_details['product_data'] ), $context );
							$logger->info( 'Product data keys: ' . wc_print_r( array_keys( $mod_all_details['product_data'] ), true ), $context );
						}

						// Product eligibility and counting already done earlier - using results from early processing
						$logger->info( 'Using early processing results - Error count: ' . count($errorArray) . ', Eligible count: ' . count($healthyProductIds) . ', SKU count: ' . count($skus_array) . ', Actual product count: ' . $healthyProductCount, $context );
		
						// Schedule queue processing after 5 minutes using queue manager
						$event_time = time() + 300; // 5 minutes
						$hook_name  = 'ced_amazon_process_upload_queue';

						
						// use all_error_products_ids to remove the products ids from the $mod_all_details['profile_with_pro_ids']
						// note the $mod_all_details['profile_with_pro_ids'] has keys as profile ids and their values is an array of product ids
						// so we need to remove the product ids from the array of product ids for the profile id
						if ( !empty( $all_error_products_ids ) ) {
							foreach ( $all_error_products_ids as $product_id ) {
								foreach ( $mod_all_details['profile_with_pro_ids'] as $profile_id => $product_ids ) {
									// $mod_all_details['profile_with_pro_ids'][$profile_id] = array_diff( $product_ids, array( $product_id ) );
									if ( in_array( $product_id, $product_ids ) ) {
										unset( $mod_all_details['profile_with_pro_ids'][$profile_id][$product_id] );
									}
								}
							}
						}

						$totalProducts = (int) count($healthyProductIds) + (int) count($errorArray);

						// Prepare queue data for database
						$queue_args = array(
							'id' => $event_time, // Scheduled time as queue ID
							'region' => ced_amz_get_region_by_mp_location( $mplocation ),
							'mplocation' => $mplocation,
							'seller_id' => $seller_id,
							'action' => 'upload',
							'profile_with_pro_ids' => $mod_all_details['profile_with_pro_ids'],
							'product_count' => $totalProducts, // Count actual products, not variations
							'healthyProductIds' => $healthyProductIds, // this is the array of product ids that are eligible for upload
							'skus_array' => $skus_array,
							'error_count' => count($errorArray),
							'status' => 'processing',
							'context' => $context,
							'errorArray' => $errorArray 
						);
		
						// Use queue manager to schedule and store in database
						$queue_manager = Ced_Amazon_Queue_Manager::get_instance();
						$queue_manager->ced_amz_schedule_single_event( $hook_name, $queue_args );
		

						// Prepare success message
						$notice['message']  = 'Product data has been saved to file and upload queue has been set! ';
						$notice['message'] .= 'Processing will start in 5 minutes. ';
						$notice['message'] .= "Total products: {$totalProducts}, " . 'Ineligible products: ' . count($errorArray);
						
						if ( !empty( $healthyProducts ) ) {
							$notice['message'] .= ' Eligible products: ' . count( $healthyProductIds ) . '. ';
						}
						
						// if ( !empty( $errorArray ) ) {
						// 	$notice['message'] .= 'Ineligible products: ' . count( $errorArray ) . ' (check error logs for details).';
						// }
		
						$notice['classes'] = 'notice notice-success is-dismissable';
						return wp_json_encode( $notice );
		
					}
		
				} catch ( Exception $e ) {
					$logger->error( 'Exception in uploadProductIds: ' . $e->getMessage(), $context );
					$notice['message'] = 'Exception occurred: ' . $e->getMessage();
					$notice['classes'] = 'notice notice-error is-dismissable';
					return wp_json_encode( $notice );
				}

			} else {
				$notice['message'] = 'No product IDs provided!';
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );
			}
		
		}

		/**
		 * Optimized helper function to process and save product data
		 */
		private function process_and_save_product_data( $allDetails, $amazon_profiles_list, $mplocation, $seller_id, $logger, $context, $save_files = true ) {

			$mod_all_details = array();
			$upload_dir      = wp_upload_dir();
			$folderPath      = $upload_dir['basedir'] . '/ced-amazon/product_upload_schema/' . $mplocation;
			
			$logger->info( "Processing product data for folder: {$folderPath}", $context );
			
			if ( $save_files && !is_dir( $folderPath ) ) {
				mkdir( $folderPath, 0755, true );
			}
			
			// Cache schema files to avoid repeated file operations
			$schemaCache = array();
			
			if ( !empty( $allDetails['profile_with_pro_ids'] ) ) {
				$mod_all_details['profile_with_pro_ids'] = $allDetails['profile_with_pro_ids'];
				
				foreach ( $allDetails['profile_with_pro_ids'] as $profile_id => $products_list ) {
					if ( !empty( $products_list ) && !empty( $amazon_profiles_list[ $profile_id ] ) ) {
						
						// Cache schema for this profile
						if ( !isset( $schemaCache[$profile_id] ) ) {
							$dirname      = $upload_dir['baseurl'] . '/ced-amazon/templates/' . $mplocation . '/' . $amazon_profiles_list[ $profile_id ];
							$fileName     = $dirname . '/products_json_fields.json';
							$schema       = file_get_contents( $fileName );
							$mod_schema   = json_decode( $schema, true );
							$language_tag = $mod_schema['$defs']['language_tag']['default'];
							
							$schemaCache[$profile_id] = array(
								'schema' => $schema,
								'language_tag' => $language_tag,
								'fileName' => $fileName
							);
						}
						
						$schemaData = $schemaCache[$profile_id];
						
						foreach ( $products_list as $productId ) {
							$productData = get_post_meta( $productId, 'ced_amazon_final_pro_det_' . $mplocation, true );
							$type        = $allDetails['product_types'][$productId] ?? '';
							
							$structured_product_data = $this->ced_amz_prepareFinalStructure( 
								$productData, 
								$schemaData['schema'], 
								$this->marketplace, 
								$schemaData['language_tag'], 
								$type 
							);
							
							$mod_all_details['product_data'][$productId] = $structured_product_data;
							$mod_all_details['file_paths'][$profile_id]  = $schemaData['fileName'];
							
							// Only save files if $save_files is true
							if ( $save_files ) {
								$logger->info( 'Saving file for product ID: ' . $productId, $context );
								// Determine file path for saving
								$pro_type = $allDetails['product_types'][$productId];
								$id       = ( 'variation' == $pro_type ) ? wp_get_post_parent_id($productId) : $productId;
								$filePath = $folderPath . '/' . $id . '.json';
								
								// Read existing content or create new
								$jsonContent    = file_exists($filePath) ? file_get_contents($filePath) : '{}';
								$decodedContent = json_decode( $jsonContent, true ) ?? array();
								
								// Add product data
								$decodedContent['final_product_structure'][$productId] = $structured_product_data;
								
								// Save to file
								$a =  file_put_contents( $filePath, json_encode( $decodedContent, JSON_PRETTY_PRINT ) );
								$logger->info( 'File saved for product ID: ' . $a, $context );
							}
						}
					}
				}
			}
			
			return $mod_all_details;
		}


		/**
		 * Perform early eligibility check on product IDs only (before makeProductXMLFileToSendOnAmazon)
		 */
		private function perform_early_eligibility_check( $proIds, $amazon_profiles_list, $mplocation, $logger, $context ) {

			$errorArray             = array();
			$all_error_products_ids = array();

			$healthyProducts = array();
			$skus_array      = array();
			
			$logger->info( 'Starting early eligibility check for ' . count($proIds) . ' products', $context );
			
			foreach ( $proIds as $productId ) {
				
				$product   = wc_get_product( $productId );
				$productId = $product->get_id();

				if ( $product->get_type() === 'variation' ) {
					// Skip variations - they're handled with their parent variable product
					continue;
				} 

				if ( 'simple' == $product->get_type() ) {

					// Skip invalid products immediately
					if ( !$product ) {
						$errorArray[]             = array(
							'product_id' => $productId,
							'product_sku' => 'N/A',
							'reason' => 'Product not found'
						);
						$all_error_products_ids[] = $productId;
						continue;
					}
					
					// Check SKU requirement - skip products without SKUs
					if ( empty( $product->get_sku() ) ) {
						$errorArray[]             = array(
							'product_id' => $productId,
							'product_sku' => 'N/A',
							'reason' => 'Product has no SKU'
						);
						$all_error_products_ids[] = $productId;
						continue;
					}

					if ( empty( $amazon_profiles_list ) || !isset( $amazon_profiles_list[$productId] ) ) {
						$errorArray[]             = array(
							'product_id' => $productId,
							'product_sku' => $product->get_sku(),
							'reason' => 'No Template assigned.'
						);
						$all_error_products_ids[] = $productId;
						continue;
					}

					$healthyProducts[] = array(
						'product_sku' => $product->get_sku(),
						'type' => 'Simple',
						'product_id' => $product->get_id()
					);
					
					$skus_array[ $product->get_sku() ] = array(
						'type' => 'Simple',
						'product_sku' => $product->get_sku(),
						'product_id' => $product->get_id()
					);

				} elseif ( 'variable' == $product->get_type() ) {

					$children_ids       = $product->get_children();
					$valid_variations   = array();
					$invalid_variations = array();
					
					$reason    = '';
					$isInvalid = false;

					// Check all variations have SKUs and data
					foreach ( $children_ids as $child_id ) {

						$child_product = wc_get_product( $child_id );
						$variation_sku = get_post_meta( $child_id, '_sku', true );

						if ( empty( $variation_sku ) ) {
							$isInvalid                = true;
							$reason                   = 'No SKU found.';
							$all_error_products_ids[] = $child_id;
						} elseif ( empty( $amazon_profiles_list ) || !isset( $amazon_profiles_list[$productId] ) ) {
							$isInvalid                = true;
							$reason                   = 'No Template assigned.';
							$all_error_products_ids[] = $child_id;
						} else {
							$isInvalid                               = false;
							$skus_array[ $child_product->get_sku() ] = array(
								'type' => 'Variation',
								'product_sku' => $variation_sku,
								'product_id'  => $child_id
							);

						}

					}

					if ( $isInvalid ) {
						$errorArray[]             = array(
							'product_id' => $productId,
							'product_sku' => $product->get_sku(),
							'reason' => $reason,
							'children_ids' => $children_ids
						);
						$all_error_products_ids[] = $productId;

					} else {

						$valid_variations[] = $child_id;
						$healthyProducts[]  = array(
							'type' => 'Variable',
							'product_sku' => $product->get_sku(),
							'product_id' => $productId
						);
						
						// Add variable product to SKU array
						$skus_array[ $product->get_sku() ] = array(
							'type' => 'Variable',
							'product_sku' => $product->get_sku(),
							'product_id' => $productId
						);
					}
										
				}

			}
			
			$logger->info( 'Early eligibility check complete - Error count: ' . count($errorArray) . ', Eligible count: ' . count($healthyProducts) . ', SKU count: ' . count($skus_array), $context );
			
			return array(
				'errorArray'             => $errorArray,
				'all_error_products_ids' => $all_error_products_ids,
				'eligibleProducts'       => $healthyProducts,
				'skus_array'             => $skus_array
			);
		}


		/**
		 * Optimized helper function to calculate product counts
		 */
		private function calculate_product_counts( $skus_array, $healthyProducts, $errorArray, $logger, $context ) {

			$healthyProductCountWithVariations = 0; // total count of healthy products including variations without duplicates
			$healthyProductCount               = 0; // total count of healthy products without variations 

			$healthyProductIdsWithVariations = array(); // array of healthy product ids including variations without duplicates
			$healthyProductIds               = array(); // array of healthy product ids without variations

			
			foreach ( $skus_array as $sku_info ) {

				if ( 'Simple' === $sku_info['type'] ) {

					++$healthyProductCount;
					++$healthyProductCountWithVariations;
					$healthyProductIds[] = $sku_info['product_id'];

				} elseif ( 'Variable' === $sku_info['type'] ) {

					++$healthyProductCount;
					$healthyProductIds[]               = $sku_info['product_id'];
					$healthyProductIdsWithVariations[] = $sku_info['product_id'];
					++$healthyProductCountWithVariations;

				} elseif ( 'Variation' === $sku_info['type'] ) {

					$healthyProductIdsWithVariations[] = $sku_info['product_id'];
					++$healthyProductCountWithVariations;
				}
			}
		
			return array(
				'healthyProductCount' => $healthyProductCount,
				'healthyProductIds'   => $healthyProductIds,
				'healthyProductCountWithVariations' => $healthyProductCountWithVariations,
				'healthyProductIdsWithVariations' => $healthyProductIdsWithVariations,
			);
		}


		/**
		 * Relist product with exist Amazon catalog ASIN
		 *
		 * @name ced_amazon_relist_product
		 * @since 1.0.0
		 */
		public function ced_amazon_relist_product( $product_ids = array(), $mplocation = '', $seller_id = '' ) {

			// Log file name
			$logger  = wc_get_logger();
			$context = array( 'source' => 'ced_amazon_relist_products' );
			$logger->info( wc_print_r( ced_woo_timestamp(), true ), $context );

			// throttle check
			$ced_amazon_create_feed_throttle = get_transient( 'ced_amazon_create_feed_throttle' );
			if ( $ced_amazon_create_feed_throttle ) {

				$notice['message'] = 'Create feed API call limit exceeded. Please try after 5 mins.';
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );

			}

			if ( empty( $mplocation ) || empty( $seller_id ) ) {
				$notice['message'] = 'Marketplace location or seller ID is missing. Please check!';
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );
			}

			// current region
			$region = ced_amz_get_region_by_mp_location( $mplocation );

			$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );
			if ( isset( $saved_amazon_details[ $seller_id ] ) && ! empty( $saved_amazon_details[ $seller_id ] ) && is_array( $saved_amazon_details[ $seller_id ] ) ) {
				$shop_data = $saved_amazon_details[ $seller_id ];
			}

			$marketplace_id = isset( $shop_data['marketplace_id'] ) ? $shop_data['marketplace_id'] : '';
			$remote_shop_id = isset( $shop_data['seller_next_shop_id'] ) ? $shop_data['seller_next_shop_id'] : '';

			if ( empty( $marketplace_id ) || empty( $seller_id ) || empty( $mplocation ) ) {
				$notice['message'] = 'Seller Id and Marketplace Id are missing, please check!';
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );
			}

			if ( is_array( $product_ids ) && ! empty( $product_ids ) ) {
				$products = $product_ids;
			} else {
				$notice['message'] = 'No products were found to publish on Amazon!';
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );
			}

			$flag = 0;

			// chek the product asins
			foreach ( $products as $product ) {
				$asin = get_post_meta( $product, 'ced_amazon_catalog_asin_' . $mplocation, true );
				if ( !empty( $asin ) ) {
					$flag = 1;
				}

			}

			if ( !$flag ) {
				$notice['message'] = 'No valid products were found to publish on Amazon!';
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );
			}

			// current strtotime
			$current_timestamp = strtotime('now'); // current strtotime time;

			$ced_amz_lst_manaul_opt_time = get_option( 'ced_amz_lst_manaul_opt_time', array() );
			$next_manual_scheduled_time  = isset( $ced_amz_lst_manaul_opt_time['created-feed'] ) ? $ced_amz_lst_manaul_opt_time['created-feed'] : 0;

			// update products to db
			$ced_amazon_all_products_to_relist = get_option( 'ced_amazon_products_to_relist', array() );
			$ced_amazon_products_to_relist     = isset( $ced_amazon_all_products_to_relist[ $seller_id ] ) ? $ced_amazon_all_products_to_relist[ $seller_id ]  : array();
			
			$ced_amazon_all_products_to_relist[$seller_id] = array_unique( array_filter( array_merge( $ced_amazon_products_to_relist, $products ) ) ) ;
			update_option( 'ced_amazon_products_to_relist', $ced_amazon_all_products_to_relist );
			
			$logger->info( wc_print_r(  '------------------- products are added to the db------------------------------ ', true ), $context );
				
			if ( $next_manual_scheduled_time > $current_timestamp ) {

				$logger->info( wc_print_r(  '------------------------- RELIST ACTION HAS BEEN UPDATED -------------------------- ', true ), $context );
				
				$notice['message'] = 'A Relist action has been updated.';
				$notice['classes'] = 'notice notice-info is-dismissable';
				return wp_json_encode( $notice );

			} elseif ( $next_manual_scheduled_time < $current_timestamp  ) {
			   
				// Get the next time for which automatic scheduler will run for this region
				$next_amt_scheduler_time_for_rg = ced_amz_get_next_scheduled_time_for_rg( $region );
				 
				// Calculate the safe time window (5 minutes before and after automatic scheduler)
				$safe_window_start = (int) $next_amt_scheduler_time_for_rg - 300; // 5 minutes before
				$safe_window_end   = (int) $next_amt_scheduler_time_for_rg + 300;   // 5 minutes after
				
				// Start with current time + 5 minutes as desired time
				$desired_time = $current_timestamp + 300;
				
				// Check if desired time falls within the unsafe window
				if ( $desired_time >= $safe_window_start && $desired_time <= $safe_window_end ) {
					// If it falls within unsafe window, schedule it after the safe window ends
					$desired_time = $safe_window_end + 300; // Additional 5 minutes buffer
				}
				
				// Log the timing calculations for debugging
				$logger->info( wc_print_r( array(
					'current_timestamp' => $current_timestamp,
					'next_automatic_scheduler_time' => $next_amt_scheduler_time_for_rg,
					'safe_window_start' => $safe_window_start,
					'safe_window_end' => $safe_window_end,
					'final_desired_time' => $desired_time,
					'human_readable_time' => gmdate('Y-m-d H:i:s', $desired_time)
				), true ), $context );

				$this->amzQueueManager->ced_amz_schedule_single_event( 'ced_amazon_relist_products' , $args = array( 
					'seller_id'   => $seller_id, 
					'id'          => $desired_time, 
					'region'      => $region,
					'remote_shop_id'  => $remote_shop_id,
					'marketplace_ids' => array( $marketplace_id ),
					'mplocation'  => $mplocation, 
					'product_ids' => $product_ids, 
					'status'      => 'pending',
					'opt_type'    => 'Manual',
					'context'     => $context
					) 
				);

				$logger->info( wc_print_r(  '------------------- A NEW EVENT HAS BEEN CREATED ------------------------ ', true ), $context );
	
				$notice['message'] = 'Relist action has been added to queue.';
				$notice['classes'] = 'notice notice-info is-dismissable';
				return wp_json_encode( $notice );

			} else {
				$notice['message'] = 'Unexpected error occured';
				$notice['classes'] = 'notice notice-info is-dismissable';
				return wp_json_encode( $notice );
			}

		}

		public function ced_amazon_bulk_relist_products( $params ) {

			$logger  = wc_get_logger();
			$context = array( 'source' => 'ced_amazon_relist_products' );
			$logger->info( wc_print_r( '---------------------- INSIDE ' . __FUNCTION__ . ' ----------------- ', true ), $context );
			$logger->info( wc_print_r( $params, true ), $context );

			$seller_id       = isset( $params['seller_id'] ) ? $params['seller_id'] : '';
			$region          = isset( $params['region'] ) ? $params['region'] : '';
			$mplocation      = isset( $params['mplocation'] ) ? $params['mplocation'] : '';
			$remote_shop_id  = isset( $params['remote_shop_id'] ) ? $params['remote_shop_id'] : '';
			$marketplace_ids = isset( $params['marketplace_ids'] ) ? $params['marketplace_ids'] : array(); 
			$extraParams     = isset( $params['extraParams'] ) ? $params['extraParams'] : array();


			$ced_amazon_all_products_to_relist = get_option( 'ced_amazon_products_to_relist', array() );
			$ced_amazon_products_to_relist     = isset( $ced_amazon_all_products_to_relist[ $seller_id ] ) ? $ced_amazon_all_products_to_relist[ $seller_id ]  : array();
				
			$products = $ced_amazon_products_to_relist;
			$fileName = 'product-relist-' . $mplocation . '.json';
			
			$relist_content_array = $this->create_product_relist_data_json_file( $products, $mplocation, $fileName, $seller_id, $marketplace_ids ); // Create product data xml file
			$relist_content       = isset( $relist_content_array['content'] ) ? $relist_content_array['content'] : '';

			if ( isset( $relist_content_array['validity'] ) && !$relist_content_array['validity'] ) {
 
				$feed_xml_error = $this->feed_xml_notice;
				$logger->info( wc_print_r( 'Relist data validation failed', true ), $context );
				$logger->info( wc_print_r( $relist_content_array, true ), $context );
				return;

			} else {
				$logger->info( wc_print_r( 'Relist data validation passed', true ), $context );
			}

			$upload_dir = wp_upload_dir();
			$filePath   = $upload_dir['basedir'] . '/ced-amazon/' . $fileName;

			$feed_action = 'JSON_LISTINGS_FEED';

			try {

				// Product relist feed API call using SP-API endpoint
				$feed_topic = 'create-feed';
				$feed_data  = array(
					'feed_action'    => $feed_action,
					'feed_content'   => $relist_content,
					'remote_shop_id' => $remote_shop_id,
				);

				$feed_reponse  = $this->amzonCurlRequestInstance->ced_amazon_serverless_process( $feed_topic, $feed_data, 'POST' );
				$response_body = wp_remote_retrieve_body($feed_reponse);
				$response_data = json_decode($response_body, true);

				// check throttle if the feed type is JSON
				$throttleResponse        = $this->ced_amz_check_json_feed_throttle( $response_data, 'ced_amazon_create_feed_throttle');
				$decodedThrottleResponse = json_decode( $throttleResponse, true );
				if ( is_array( $decodedThrottleResponse ) && isset(  $decodedThrottleResponse['message'] ) && strpos( $decodedThrottleResponse['message'], 'Quoto Exceeded' ) === 0 ) {
					return $throttleResponse;
				}
				
				$product_relist_response = json_decode( $feed_reponse['body'], true );
				$logger->info( wc_print_r( '------------------------product_relist_response-------------------', true ), $context );
				$logger->info( wc_print_r( $product_relist_response, true ), $context );

				$product_relist_response = isset( $product_relist_response['data'] ) ? $product_relist_response['data'] : array();
				
				// modified feed_action
				if ( 'JSON_LISTINGS_FEED' == $feed_action ) {
					$feed_action = 'JSON_LISTINGS_FEED_RELIST';
				}

				if ( isset( $product_relist_response['feed_id'] ) && ! empty( $product_relist_response['feed_id'] ) ) {
					
					$feedId    = $product_relist_response['feed_id'];
					$feed_type = $feed_action;
					$this->amz_com_opts_mng->insertFeedInfoToDatabase( $feedId, $feed_type, $mplocation, $relist_content_array['SKUs'], 'Manual' );

					// update queued action status
					$params['status']  = 'completed';
					$params['feed-id'] = $feedId;

					// update action timings
					$ced_amz_action_timings                 = get_option( 'ced_amz_action_timings' , array() );
					$ced_amz_action_timings['created-feed'] = strtotime('now');
					update_option( 'ced_amz_action_timings', $ced_amz_action_timings );

					$logger->info( wc_print_r( '--------------------------------------- CREATING A SINGLE EVENT OF GET FEED FOR RELIST PRODUCTS ---------------------------------- ', true ), $context );
					
					/** To remove products ids from array in db */
					$event_time = time() + 120;
					$hook_name  = 'ced_amazon_get_feed_data';
					$hook_data  = array( array(
						'feed_id'   => $feedId, 
						'marketplace_ids' => $marketplace_ids,
						'region'     => $region,
						'user_id'    => $remote_shop_id,
						'action'     => 'relist',
						'mplocation' => $mplocation,
						'woo_relist_array' => $products,
						'first_sync' => false
						
					) );

					if ( function_exists( 'as_schedule_single_action' ) ) {
						as_schedule_single_action( $event_time, $hook_name, $hook_data );
					} else {
						wp_schedule_single_event( $event_time, $hook_name, $hook_data  );
					}

				} else {
					// update queued action status
					$params['status']  = 'failed';
					$params['feed-id'] = 0;

					$logger->info( wc_print_r( $product_relist_response, true ), $context );
					
				}

				$logger->info( wc_print_r( '---------------------- GOING TO CHECK RELIST QUEUED ACTION update condition ----------------- ', true ), $context );
				$logger->info( wc_print_r( $params, true ), $context );

				// update queued action status
				if ( isset( $params['status'] ) ) {
					
					$params['context'] = $context;
					$logger->info( wc_print_r( '---------------------- GOING TO UPDATE RELIST QUEUED ACTION #1----------------- ', true ), $context );
					$logger->info( wc_print_r( $params, true ), $context );

					$this->amzQueueManager->ced_amz_update_queue_action( 'ced_amazon_relist_scheduler_job_' , $params );
				}

				return;

			} catch ( Exception $e ) {

				$logger->info( wc_print_r( $e->getMessage(), true ), $context );
				return ;
			}

			$logger->info( wc_print_r( 'Unexpected error occured.', true ), $context );
			return;

		}

		/**
		 * Delete product from Amazon
		 *
		 * @name ced_amazon_delete_product
		 * @since 1.0.0
		 */
		public function ced_amazon_delete_product( $product_ids = array(), $mplocation = '', $seller_mp_key = '' ) {

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
				$notice['message'] = 'No products were found to update inventory on Amazon!';
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );
			}

			$isWriteJSON          = true;
			$jsonFileName         = 'product-delete-' . $mplocation . '.json';
			$delete_content_array = $this->create_product_delete_data_json_file( $products, $isWriteJSON, $mplocation, $jsonFileName, $seller_id ); // Product delete data json file
			$delete_content       = isset( $delete_content_array['json_data'] ) ? $delete_content_array['json_data'] : '';

			if ( false !== $delete_content ) {

				$upload_dir   = wp_upload_dir();
				$JSONfilePath = $upload_dir['basedir'] . '/ced-amazon/' . $jsonFileName;

				try {

					// Product delete feed API call using SP-API endpoint
					$feed_topic = 'create-feed';
					$feed_data  = array(
						'feed_action'    => 'JSON_LISTINGS_FEED',
						'feed_content'   => $delete_content,
						'remote_shop_id' => $remote_shop_id,
					);

					$feed_reponse = $this->amzonCurlRequestInstance->ced_amazon_serverless_process( $feed_topic, $feed_data, 'POST' );

					// check throttle if the feed type is JSON
					$throttleResponse = $this->ced_amz_check_json_feed_throttle( $response_data, 'ced_amazon_create_feed_throttle');

					$decodedThrottleResponse = json_decode( $throttleResponse, true );
					if ( is_array( $decodedThrottleResponse ) && isset(  $decodedThrottleResponse['message'] ) && strpos( $decodedThrottleResponse['message'], 'Quoto Exceeded' ) === 0 ) {
						return $throttleResponse;
					}

					$product_delete_response = json_decode( $feed_reponse['body'], true );
					$product_delete_response = isset( $product_delete_response['data'] ) ? $product_delete_response['data'] : array();

					if ( isset( $product_delete_response['success'] ) && 'false' == $product_delete_response['success'] ) {
						$notice['message'] = isset( $product_delete_response['body'] ) ? $product_delete_response['body'] : $product_delete_response['message'];
						$notice['classes'] = 'notice notice-error is-dismissable';
						return wp_json_encode( $notice );
					}

					if ( isset( $product_delete_response['feed_id'] ) && ! empty( $product_delete_response['feed_id'] ) ) {
						
						$feedId    = $product_delete_response['feed_id'];
						$feed_type = 'JSON_LISTINGS_FEED';
						$this->amz_com_opts_mng->insertFeedInfoToDatabase( $feedId, $feed_type, $mplocation, $delete_content_array['SKUs'], 'Manual' );

						// Save feed action with respect to each product
						// foreach ( $products as $pkey => $product_id ) {
						// 	$seller_id_val       = str_replace( '|', '_', $grtopt_data );
						// 	$product_feed_action = get_post_meta( $product_id, 'ced_amazon_feed_actions_' . $seller_id_val, true );
						// 	$current_feed_action = array( 'JSON_LISTINGS_FEED' => $feedId );
						// 	if ( is_array( $product_feed_action ) && ! empty( $product_feed_action ) ) {
						// 		$product_feed_action = array_replace( $product_feed_action, $current_feed_action );
						// 	} else {
						// 		$product_feed_action = $current_feed_action;
						// 	}
						// 	update_post_meta( $product_id, 'ced_amazon_feed_actions_' . $seller_id_val, $product_feed_action );
						// }

						// update action timings
						$ced_amz_action_timings                 = get_option( 'ced_amz_action_timings' , array() );
						$ced_amz_action_timings['created-feed'] = strtotime('now');
						update_option( 'ced_amz_action_timings', $ced_amz_action_timings );

						$notice['message'] = 'Product delete feed has been processed and submitted.';
						$notice['classes'] = 'notice notice-success is-dismissable';
						return wp_json_encode( $notice );

					} else {
						$notice['message'] = 'Something went wrong with the feed submission. Please check the product delete feed URL!';
						$notice['classes'] = 'notice notice-error is-dismissable';
						return wp_json_encode( $notice );
					}
				} catch ( Exception $e ) {
					echo 'An exception occurred when calling the product to delete the feed API endpoint: ', esc_attr( $e->getMessage() ), PHP_EOL;
					$notice['message'] = 'An exception occurred when calling the product to delete the feed API endpoint.';
					$notice['classes'] = 'notice notice-error is-dismissable';
					return wp_json_encode( $notice );
				}
			} else {
					$notice['message'] = 'Product delete data validation failed!';
					$notice['classes'] = 'notice notice-error is-dismissable';
					return wp_json_encode( $notice );
			}
			$notice['message'] = 'Something went wrong with the delete feed submission!';
			$notice['classes'] = 'notice notice-error is-dismissable';
			return wp_json_encode( $notice );
		}

		/**
		 * Make product xml file send on amaozn
		 *
		 * @param unknown $proIds
		 * @param string  $isWriteXML
		 * @param string  $xmlFileName
		 */
		public function makeProductXMLFileToSendOnAmazon( $proIds = array(), $profileID = '', $getopt_data = '' ) {

			if ( empty( $getopt_data ) ) {
				$notice['message']           = 'Marketplace location is missing. Please check!';
				$notice['classes']           = 'notice notice-error is-dismissable';
				$this->product_upload_notice = $notice;
				return;
			}

			$seller_id = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';

			$finalAllProfileType              = array();
			$finalAllLoaderProductIds         = array();
			$finalAllProfileTypeWithProductId = array();
			$product_types                    = array();

			if ( isset( $proIds['0'] ) ) {
				foreach ( $proIds as $pro_key => $product_id ) {

					$mod_product   = wc_get_product( $product_id );
					$mod_parent_id = $mod_product->get_parent_id();

					$product_types[ $product_id ] = $mod_product->get_type();

					if ( 0 == $mod_parent_id || '0' == $mod_parent_id ) {
						$terms = get_the_terms( $product_id, 'product_cat' );
					} else {
						$terms = get_the_terms( $mod_parent_id, 'product_cat' );
					}

					$term_array = array();
					if ( $terms && ! is_wp_error( $terms ) ) {
						foreach ( $terms as $term ) {
							$term_array[] = $term->term_id;
						}
					}

					$profileID              = '';
					$ced_woo_amazon_mapping = get_option( 'ced_woo_amazon_mapping', array() );
					$ced_woo_amazon_mapping = isset( $ced_woo_amazon_mapping[ $seller_id ] ) ? $ced_woo_amazon_mapping[ $seller_id ] : array();

					if ( ! empty( $ced_woo_amazon_mapping ) ) {
						foreach ( $ced_woo_amazon_mapping as $key => $woo_cat_array ) {

							$match_woo_cat = array_intersect( $woo_cat_array, $term_array );
							if ( is_array( $match_woo_cat ) && ! empty( $match_woo_cat ) ) {

								$profileID = $key;
								break;

							}
						}
					}

					$profileIDPerProduct = '';
					if ( ! empty( $profileID ) ) {
						$profileIDPerProduct = $profileID;
					} else {
						$finalAllLoaderProductIds[ $product_id ] = $product_id;
						continue;
					}

					$finalAllProfileType[ $profileIDPerProduct ]                             = $profileIDPerProduct;
					$finalAllProfileTypeWithProductId[ $profileIDPerProduct ][ $product_id ] = $product_id;

					if ( '' != $profileIDPerProduct && '0' != $profileIDPerProduct ) {
 
						$this->amazon_xml_lib->fetchAssignedProfileDataOfProduct( $product_id, $getopt_data, $profileIDPerProduct );
						$amazonxmlarrayresponse = $this->amazon_xml_lib->prepareAllProductTypeData( $product_id, $getopt_data, $profileIDPerProduct );

					} else {
						$finalAllLoaderProductIds[ $product_id ] = $product_id; 
					}
				}

			}

			return wp_json_encode(
				array(
					'invntory_loder_ids'   => array_unique( $finalAllLoaderProductIds ),
					'profile_ids'          => $finalAllProfileType,
					'profile_with_pro_ids' => $finalAllProfileTypeWithProductId,
					'product_types'        => $product_types  
				)
			);
		}

		/**
		 * Ship Amazon Order
		 *
		 * @name umb_amazon_shipment_order
		 * @link  http://www.cedcommerce.com/
		 */
		public function umb_amazon_shipment_order() {

			$check_ajax = check_ajax_referer( 'ced-amazon-order-shipment', 'ajax_nonce' );
			if ( ! $check_ajax ) {
				return;
			}

			$ced_amazon_create_feed_throttle = get_transient( 'ced_amazon_create_feed_throttle' );
			if ( $ced_amazon_create_feed_throttle ) {
				echo esc_attr_e( 'Create feed API call limit exceeded. Please try after 5 mins.', 'amazon-for-woocommerce' );
				die;
			}

			// Order shipment via SP-API
			$post_order = isset( $_POST['order'] ) ? sanitize_text_field( $_POST['order'] ) : '';
			$hpos       = isset( $_POST['hpos'] ) ? sanitize_text_field( $_POST['hpos'] ) : '';

			$order = '';

			$order      = wc_get_order( $post_order );
			$mplocation = $order->get_meta( 'ced_amazon_order_countory_code' );
			$seller_id  = $order->get_meta( 'ced_amazon_order_seller_id' );

			if ( empty( $mplocation ) || empty( $seller_id ) ) {
				echo esc_attr_e( 'Mp_location or Seller_id is missing for this order!', 'amazon-for-woocommerce' );
				die;
			}

			$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );

			if ( isset( $saved_amazon_details[ $seller_id ] ) && ! empty( $saved_amazon_details[ $seller_id ] ) && is_array( $saved_amazon_details[ $seller_id ] ) ) {
				$shop_data = $saved_amazon_details[ $seller_id ];
			}

			$region         = isset( $shop_data['marketplace_region'] ) ? $shop_data['marketplace_region'] : '';
			$marketplace_id = isset( $shop_data['marketplace_id'] ) ? $shop_data['marketplace_id'] : '';
			$seller_id      = isset( $shop_data['merchant_id'] ) ? $shop_data['merchant_id'] : '';
			$remote_shop_id = isset( $shop_data['seller_next_shop_id'] ) ? $shop_data['seller_next_shop_id'] : '';

			if ( empty( $seller_id ) || empty( $marketplace_id ) ) {
				echo esc_attr_e( 'Invalid or missing seller data', 'amazon-for-woocommerce' );
				die;
			}

			$order_id = isset( $_POST['order'] ) ? sanitize_text_field( $_POST['order'] ) : '';
			// $amazon_order_id    = get_post_meta( $order_id, 'amazon_order_id', true );

			$amazon_order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( $_POST['order_id'] ) : '';

			$amazon_carrier     = isset( $_POST['carrier'] ) ? sanitize_text_field( $_POST['carrier'] ) : '';
			$amazon_methodCode  = isset( $_POST['methodCode'] ) ? sanitize_text_field( $_POST['methodCode'] ) : '';
			$amazon_ship_todate = isset( $_POST['ship_todate'] ) ? sanitize_text_field( $_POST['ship_todate'] ) : '';
			$amazon_tracking    = isset( $_POST['tracking'] ) ? sanitize_text_field( $_POST['tracking'] ) : '';

			$order->update_meta_data( 'ced_amzon_shipped_data', $_POST );
			$order_items   = $order->get_meta( 'order_items' );
			$order_details = $order->get_meta( 'order_item_detail' );

			$order->save();

			$offset_end = $this->getStandardOffsetUTC(); // get offset
			if ( empty( $offset_end ) || '' == trim( $offset_end ) ) {
				$offset = '.0000000-00:00'; 
			} else {
				$offset = '.0000000' . trim( $offset_end );
			}

			$amazon_ship_todate = strtotime( $amazon_ship_todate );
			$Ship_todate        = gmdate( 'Y-m-d', $amazon_ship_todate ) . 'T' . gmdate( 'H:i:s', $amazon_ship_todate ) . $offset;

			$ordershipfulfildata['CarrierCode']           = $amazon_carrier;
			$ordershipfulfildata['ShippingMethod']        = $amazon_methodCode;
			$ordershipfulfildata['ShipperTrackingNumber'] = $amazon_tracking;
			$ordershiparray['AmazonOrderID']              = $amazon_order_id;
			$ordershiparray['FulfillmentDate']            = $Ship_todate;
			$ordershiparray['FulfillmentData']            = $ordershipfulfildata;

			$order     = new WC_Order( $order_id );
			$itemarray = array();
			foreach ( $order_details as $key => $order_item ) {
				$amznitem                        = array();
				$amznitem['AmazonOrderItemCode'] = $order_item['OrderItemId'];
				$amznitem['Quantity']            = $order_item['QuantityOrdered'];
				$itemarray[]                     = $amznitem;
				unset( $order_details[ $key ] );
			}

			$ordershiparray['Item'] = $itemarray;

			$ordershipmainarray = $this->ced_amz_prepare_feed_header( 'OrderFulfillment' );

			$ordershipmainarray['Message']['MessageID']        = 1;
			$ordershipmainarray['Message']['OperationType']    = 'Update';
			$ordershipmainarray['Message']['OrderFulfillment'] = $ordershiparray;

			$directorypath = plugin_dir_path( __FILE__ );
			if ( ! class_exists( 'Array2XML' ) ) {
				require_once CED_AMAZON_DIRPATH . 'marketplaces/amazon/lib/array2xml.php';
			}
			$xml       = Array2XML::createXML( 'AmazonEnvelope', $ordershipmainarray );
			$xmlString = $xml->saveXML();

			$xmlFileName = 'shipment-data-' . $mplocation . '.xml';
			$this->amz_com_opts_mng->writeStringToFile( $xmlString, $xmlFileName );

			$upload_dir = wp_upload_dir();
			$tmp_path   = $upload_dir['basedir'] . '/ced-amazon/' . $xmlFileName;

			if ( ! file_exists( $tmp_path ) ) {
				echo esc_attr_e( 'Shipment data file is not ready. Please check.', 'amazon-for-woocommerce' );
				die;
			}

			// Order shipment via SP-API
			$feed_topic = 'create-feed';
			$feed_data  = array(
				'feed_action'    => 'POST_ORDER_FULFILLMENT_DATA',
				'feed_content'   => $xmlString,
				'remote_shop_id' => $remote_shop_id,
			);

			$feed_reponse = $this->amzonCurlRequestInstance->ced_amazon_serverless_process( $feed_topic, $feed_data, 'POST' );

			// check throttle if the feed type is JSON
			$throttleResponse = $this->ced_amz_check_json_feed_throttle( $response_data, 'ced_amazon_create_feed_throttle');
			
			$decodedThrottleResponse = json_decode( $throttleResponse, true );
			if ( is_array( $decodedThrottleResponse ) && isset(  $decodedThrottleResponse['message'] ) && strpos( $decodedThrottleResponse['message'], 'Quoto Exceeded' ) === 0 ) {
				return $throttleResponse;
			}

			$ordershipmentreponse = json_decode( $feed_reponse['body'], true );
			$ordershipmentreponse = isset( $ordershipmentreponse['data'] ) ? $ordershipmentreponse['data'] : array();

			if ( isset( $ordershipmentreponse['success'] ) && 'false' == $ordershipmentreponse['success'] ) {
				$message = isset( $ordershipmentreponse['body'] ) ? $ordershipmentreponse['body'] : $ordershipmentreponse['message'];
				echo esc_attr( $message );
				die;
			}

			if ( isset( $ordershipmentreponse['feed_id'] ) && ! empty( $ordershipmentreponse['feed_id'] ) ) {
				$feedId = $ordershipmentreponse['feed_id'];

				$feedrequest['request']  = 'Shipped';
				$feedrequest['id']       = $feedId;
				$feedrequest['response'] = false;

				$order->update_meta_data( '_umb_order_feed_status', true );
				$order->update_meta_data( '_umb_order_feed_details', $feedrequest );

				$order->save();

				$this->amz_com_opts_mng->insertFeedInfoToDatabase( $feedId, 'POST_ORDER_FULFILLMENT_DATA', $mplocation, array(), 'Manual' );  // save product feed
				
				$order->update_meta_data( '_amazon_umb_order_status', 'Shipped' );
				$order->save();
				
				echo esc_attr_e( 'Shipment Request Submitted Successfully', 'amazon-for-woocommerce' );
				die;

			} else {
				echo esc_attr_e( 'Something went wrong with feed submission. Please check shipment feed URL!', 'amazon-for-woocommerce' );
				die;
			}
		}

		/**
		 * Create product relist data xml file
		 *
		 * @name create_product_relist_data_json_file
		 * @since 1.0.0
		 */
		public function create_product_relist_data_json_file( $proIds, $mplocation = '', $fileName = '', $seller_id = '', $marketplace_ids = array( ) ) {

			$logger  = wc_get_logger();
			$context = array( 'source' => 'ced_amazon_relist_products' );
			
			$errorsArray = array();
			if ( empty( $fileName ) ) {
				$errorsArray['File'] = 'file name is not availbale';
				return false;
			}

			$i          = 1;
			$SKUs_array = array();
			$messages   = array();

			$logger->info( wc_print_r( 'current marketplace ids are - ', true ), $context );
			$logger->info( wc_print_r( $marketplace_ids, true ), $context );

			// Get global settings data
			$seller_global_settings    = array();
			$global_settings           = get_option( 'ced_amazon_global_settings' );
			$amz_currency_code_mapping = get_option( 'ced_amz_currency_code_mapping', array() );

			$marketplace_id = $marketplace_ids[0];
			$user_id        = ced_amz_get_user_id_by_mrkp_id( $marketplace_id );

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

				foreach ( $proIds as $product_id ) {

					$product = wc_get_product( $product_id );
					if ( ! is_object( $product ) ) {
						continue;
					}
					$productType = $product->get_type();

					if ( 'simple' == $productType ) {

						$amazonxmlarray = array();
						$qty            = $product->get_stock_quantity();
						$sku            = $product->get_sku();
						$asin           = get_post_meta( $product_id, 'ced_amazon_catalog_asin_' . $mplocation, true );

						$price_array = $this->ced_amz_retrieve_final_price( $product, $price_type, $product_id , $seller_global_settings  );
						$price_amz   = isset( $price_array['amz_price'] ) ? $price_array['amz_price'] : 0;
						$woo_price   = isset( $price_array['woo_price'] ) ? $price_array['woo_price'] : 0;

						if ( isset( $sku ) && ! empty( $sku ) && ! empty( $asin ) ) {

							$SKUs_array[ $sku ] = array(
								'type'         => 'Simple',
								'value'        => array( 'asin' => $asin, 'price' => $price_amz, 'qty' => $qty ),
								'product_id'   => $product_id,
								'product_sku'  => $sku,
								'product_name' => $product->get_name(),
							);

							
							$message = $this->ced_amz_offerListing_json_array(  $i, $sku, $asin, $marketplace_id, $qty, $currency, $price_amz );
							if ( !empty( $message ) ) {
								++$i;
								$messages[] = $message;
							}

							
						}

					} elseif ( 'variable' == $productType ) {

						$amazonxmlarray = array();
						$qty            = $product->get_stock_quantity();
						$sku            = $product->get_sku();
						$asin           = get_post_meta( $product_id, 'ced_amazon_catalog_asin_' . $mplocation, true );

						if ( ! empty( $sku ) ) {
							$parent_sku = $sku;
						}
						if ( isset( $sku ) && ! empty( $sku ) && ! empty( $asin ) ) {
							$amazonxmlarray['MessageID']                             = $i;
							$amazonxmlarray['OperationType']                         = 'Update';
							$amazonxmlarray['Product']['SKU']                        = $sku;
							$amazonxmlarray['Product']['StandardProductID']['Type']  = 'ASIN';
							$amazonxmlarray['Product']['StandardProductID']['Value'] = $asin;
							$amazonxmlsubarray['Message'][]                          = $amazonxmlarray;
							++$i;
						}

						$all_available_var = $product->get_children();
						foreach ( $all_available_var as $var_key => $var_value ) {
							$product = wc_get_product( $var_value );

							$amazonxmlarray = array();
							
							$qty  = $product->get_stock_quantity();
							$sku  = $product->get_sku();
							$asin = get_post_meta( $product->get_id(), 'ced_amazon_catalog_asin_' . $mplocation, true );

							$price_array = $this->ced_amz_retrieve_final_price( $product, $price_type, $var_value , $seller_global_settings  );
							$price_amz   = isset( $price_array['amz_price'] ) ? $price_array['amz_price'] : 0;
							$woo_price   = isset( $price_array['woo_price'] ) ? $price_array['woo_price'] : 0;

							if ( isset( $parent_sku ) && $sku == $parent_sku ) {
								$sku = '';
							}

							if ( isset( $sku ) && ! empty( $sku ) && ! empty( $asin ) ) {
								$SKUs_array[ $sku ] = array(
									'type'         => 'Variation',
									'parent_sku'   => $parent_sku,
									'value'        => array( 'asin' => $asin, 'price' => $price_amz, 'qty' => $qty ),
									'product_id'   => $product->get_id(),
									'product_sku'  => $sku,
									'product_name' => $product->get_name(),
								);

								$message = $this->ced_amz_offerListing_json_array(  $i, $sku, $asin, $marketplace_id, $qty, $currency, $price_amz );
								if ( !empty( $message ) ) {
									++$i;
									$messages[] = $message;
								}
								
								
							}
						}
					}
				}

			}

			
			$seller_id_array = explode( '|', $seller_id );
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
				'validity'    => 1 < $i ? true : false,
				'content'     =>  $json_data,
				'SKUs'        => $SKUs_array,
				'errorsLists' => $errorsArray,
			);

		}

		/**
		 * Check ASIN of product from Amazon
		 *
		 * @name ced_amazon_look_up
		 * @since 1.0.0
		 */
		public function ced_amazon_look_up( $proIds = array(), $mplocation = '', $seller_mp_key = '' ) {

			$seller_id = $seller_mp_key;

			// Log file name
			$logger  = wc_get_logger();
			$context = array( 'source' => 'ced_amazon_look_up' );
			$logger->info( wc_print_r( ced_woo_timestamp(), true ), $context );

			$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );
			$location_for_seller  = $seller_id;
			if ( isset( $saved_amazon_details[ $location_for_seller ] ) && ! empty( $saved_amazon_details[ $location_for_seller ] ) && is_array( $saved_amazon_details[ $location_for_seller ] ) ) {
				$shop_data = $saved_amazon_details[ $location_for_seller ];
			}

			$marketplace_id = isset( $shop_data['marketplace_id'] ) ? $shop_data['marketplace_id'] : '';
			$remote_shop_id = isset( $shop_data['seller_next_shop_id'] ) ? $shop_data['seller_next_shop_id'] : '';

			if ( empty( $marketplace_id ) || empty( $mplocation ) || empty( $location_for_seller ) ) {
				$logger->info( "Refresh_token/marketplace_id/mplocation/seller_id are missing while ASIN sync! \n", $context );
				return;
			}

			// Get UPC/EAN mapping data from global settings
			$global_setting_data = get_option( 'ced_amazon_global_settings', false );
			$metakey             = isset( $global_setting_data[ $location_for_seller ]['ced_amazon_catalog_asin_sync_meta'] ) ? $global_setting_data[ $location_for_seller ]['ced_amazon_catalog_asin_sync_meta'] : '_sku';
			$meta_key_map_type   = isset( $global_setting_data[ $location_for_seller ]['ced_amazon_catalog_asin_sync_meta_type'] ) ? $global_setting_data[ $location_for_seller ]['ced_amazon_catalog_asin_sync_meta_type'] : '';

			if ( empty( $metakey ) ) {
				$metakey = '_sku';
			}

			$barcode_type = array(
				11 => 'UPC',
				12 => 'UPC',
				13 => 'EAN',
				14 => 'GTIN'
			);


			$products = $proIds;
			if ( isset( $products ) && ! empty( $products ) ) {

				$ean_array = array();
				foreach ( $products as $product_id ) {

					$product        = wc_get_product( $product_id );
					$parent_id      = 0;
					$product_parent = new stdClass();

					if ( !is_object( $product ) ) {
						$parent_id = $product->get_parent_id();
						if ( !empty($parent_id) ) {
							$product_parent = wc_get_product( $parent_id );
						}
						
					}

					$data       = array( 'product_id' => $product_id, 'product' => $product, 'parent_id' => $parent_id, 'product_parent' => $product_parent );
					$upc_number = $this->ced_amz_get_barcode( $metakey, $data );
					
					// Get UPC/EAN number from woo meta
					$upc_number_length = strlen( $upc_number );

					if ( ! empty( $upc_number ) && is_numeric( $upc_number ) && ( 11 == $upc_number_length || 12 == $upc_number_length || 13 == $upc_number_length || 14 == $upc_number_length ) ) {
						// Request to get product data using UPC/EAN
						$ean_array[ $product_id ] = $upc_number;
						if ( empty( $meta_key_map_type ) ) {
							$meta_key_map_type = $barcode_type[$upc_number_length];
						}
					}

				}

				if ( ! empty( $ean_array ) && is_array( $ean_array ) ) {

					$catalog_query_params = array(
						'identifiers'       => implode( ',', array_values( $ean_array ) ),
						'identifiers_types' => $meta_key_map_type,
						'included_data'     => implode( ',', array( 'summaries', 'identifiers', 'relationships' ) ),
					);

					$catalog_topic = 'items?' . http_build_query( $catalog_query_params );
					$catalog_data  = array(
						'remote_shop_id' => $remote_shop_id,
					);

					$catalog_response_main = $this->amzonCurlRequestInstance->ced_amazon_serverless_process( $catalog_topic, $catalog_data, 'GET' );
					$logger->info( wc_print_r( $catalog_response_main, true ), $context );

				} else {
					$catalog_response_main = array();
				}

				if ( is_wp_error( $catalog_response_main ) ) {

					$notice['message'] = 'Something went wrong with the Amazon lookup. Please try again later.';
					$notice['classes'] = 'notice notice-error is-dismissable';
					return wp_json_encode( $notice );

				}

				$catalog_response = isset( $catalog_response_main['body'] ) ? json_decode( $catalog_response_main['body'], true ) : array();
				$catalog_response = isset( $catalog_response['data'] ) ? $catalog_response['data'] : array();

				if ( isset( $catalog_response['success'] ) && 'false' == $catalog_response['success'] ) {

					$notice['message'] = 'Something went wrong with the Amazon lookup. Please try again later.';
					$notice['classes'] = 'notice notice-error is-dismissable';
					return wp_json_encode( $notice );
				}

				if ( isset( $catalog_response['items'][0] ) && is_array( $catalog_response['items'][0] ) && ! empty( $catalog_response['items'][0] ) ) {
					foreach ( $catalog_response['items'] as $key => $value ) {

						$child_asin   = $value['asin'];
						$identifiers  = $value['identifiers'][0]['identifiers'];
						$unique_id_no = '';

						foreach ( $identifiers as $k => $val ) {
							if ( $meta_key_map_type == $val['identifierType'] && in_array( $val['identifier'], $ean_array ) ) {
								$unique_id_no = $val['identifier'];
							}
						}

						if ( empty( $unique_id_no ) ) {
							continue;
						}

						$relationships = $value['relationships'][0]['relationships'];
						$parent_asin   = isset( $relationships[0]['parentAsins'][0] ) ? $relationships[0]['parentAsins'][0] : '';

						$product_id = array_search( $unique_id_no, $ean_array );
						$product    = wc_get_product( $product_id );

						$parent_id = 0;
						if ( is_object( $product ) ) {
							$parent_id = $product->get_parent_id();
						}
						
						if ( ! empty( $child_asin ) ) {
							update_post_meta( $product_id, 'ced_amazon_catalog_asin_' . $mplocation, $child_asin );
						}

						if ( 0 != $parent_id && ! empty( $parent_asin ) ) {
							update_post_meta( $parent_id, 'ced_amazon_catalog_asin_' . $mplocation, $parent_asin );
						}
					}

					$notice['message'] = 'Product lookup has been processed.';
					$notice['classes'] = 'notice notice-success is-dismissable';
					return wp_json_encode( $notice );

				} else {
					$notice['message'] = 'Product lookup not found on Amazon.';
					$notice['classes'] = 'notice notice-success is-dismissable';
					return wp_json_encode( $notice );
				}
			} else {
				$notice['message'] = 'Please select a product for the Amazon lookup.';
				$notice['classes'] = 'notice notice-error is-dismissable';
				return wp_json_encode( $notice );
			}

		}

		public function ced_amz_get_barcode( $metakey = '', $data = array()) {

			$value          = '';
			$product_id     = isset( $data['product_id'] ) ? $data['product_id'] : 0;
			$product        = isset( $data['product'] ) ? $data['product'] : new stdClass();
			$parent_id      = isset( $data['parent_id'] ) ? $data['parent_id'] : 0;
			$product_parent = isset( $data['product_parent'] ) ? $data['product_parent'] : new stdClass();

			// If woo attribute is selected
			if ( false !== strpos( $metakey, 'umb_pattr_' ) ) {

				$wooAttribute = explode( 'umb_pattr_', $metakey );
				$wooAttribute = end( $wooAttribute );

				if ( 'variation' == $product->get_type() ) {
					$attributes = $product->get_variation_attributes();
					if ( isset( $attributes[ 'attribute_pa_' . $wooAttribute ] ) && ! empty( $attributes[ 'attribute_pa_' . $wooAttribute ] ) ) {
						$wooAttributeValue = $attributes[ 'attribute_pa_' . $wooAttribute ];
						if ( '0' != $parent_id ) {
							$product_terms = get_the_terms( $parent_id, 'pa_' . $wooAttribute );
						} else {
							$product_terms = get_the_terms( $product_id, 'pa_' . $wooAttribute );
						}
					} else {

						$wooAttributeValue = $product_parent->get_attribute( 'pa_' . $wooAttribute );
						if ( '0' != $parent_id ) {
							$product_terms = get_the_terms( $parent_id, 'pa_' . $wooAttribute );
						} else {
							$product_terms = get_the_terms( $product_id, 'pa_' . $wooAttribute );
						}
					}

					if ( is_array( $product_terms ) && ! empty( $product_terms ) ) {
						foreach ( $product_terms as $tempkey => $tempvalue ) {
							if ( $tempvalue->slug == $wooAttributeValue ) {
								$wooAttributeValue = $tempvalue->name;
								break;
							}
						}
						if ( isset( $wooAttributeValue ) && ! empty( $wooAttributeValue ) ) {
							$value = $wooAttributeValue;
						} else {
							$value = get_post_meta( $product_id, $metaKey, true );
						}
					} else {
						$value = get_post_meta( $product_id, $metaKey, true );
					}

				} else {
					$wooAttributeValue = $product->get_attribute( 'pa_' . $wooAttribute );
					$product_terms     = get_the_terms( $product_id, 'pa_' . $wooAttribute );

					if ( is_array( $product_terms ) && ! empty( $product_terms ) ) {
						foreach ( $product_terms as $tempkey => $tempvalue ) {
							if ( $tempvalue->slug == $wooAttributeValue ) {
								$wooAttributeValue = $tempvalue->name;
								break;
							}
						}

						if ( isset( $wooAttributeValue ) && ! empty( $wooAttributeValue ) ) {
							$value = $wooAttributeValue;
						} else {
							$value = get_post_meta( $product_id, $metaKey, true );
						}
					} else {
						$value = get_post_meta( $product_id, $metaKey, true );
					}

				}

			} elseif ( false !== strpos( $metakey, 'ced_cstm_attrb_' ) ) {

				$custom_prd_attrb = explode( 'ced_cstm_attrb_', $metakey );
				$custom_prd_attrb = end( $custom_prd_attrb );
				$wooAttribute     = $custom_prd_attrb;
				if ( ! empty( $wooAttribute ) ) {
					if ( 'variation' == $product->get_type() ) {

						$attributes        = $product->get_variation_attributes();
						$wooAttributeLower = strtolower( $wooAttribute );
						if ( isset( $attributes[ 'attribute_' . $wooAttributeLower ] ) && ! empty( $attributes[ 'attribute_' . $wooAttributeLower ] ) ) {
							$wooAttributeValue = $attributes[ 'attribute_' . $wooAttributeLower ];
						} else {

							$wooAttributeValue = $product_parent->get_attribute( $wooAttribute );
							if ( ! empty( $wooAttributeValue ) ) {
								$wooAttributeValue = str_replace( '|', ',', $wooAttributeValue );
							}
						}

						if ( isset( $wooAttributeValue ) && ! empty( $wooAttributeValue ) ) {
							$value = $wooAttributeValue;
						} else {
							$value = get_post_meta( $product_id, $metaKey, true );
						}
					} else {
						$wooAttributeValue = $product->get_attribute( $wooAttribute );
						if ( ! empty( $wooAttributeValue ) ) {
							$wooAttributeValue = str_replace( '|', ',', $wooAttributeValue );
							$value             = $wooAttributeValue;
						}
					}
				}

			} else {
				$value = get_post_meta( $product_id, $metakey, true );
			}

			return $value;

		}

		/**
		 * Create json file for delete product
		 *
		 * @name create_product_delete_data_json_file
		 * @since 1.0.0
		 */
		public function create_product_delete_data_json_file( $proIds, $isWriteJSON = true, $mplocation = '', $jsonFileName = '', $seller_id = '' ) {

			// Log file name
			$logger  = wc_get_logger();
			$context = array( 'source' => 'ced_amazon_product_delete' );
			
			if ( empty( $jsonFileName ) || empty( $seller_id ) || empty( $mplocation ) ) {
				return false;
			}

			$json_format = false;
			$messages    = array();
			$counter     = 1;

			$SKUs_array = array();

			foreach ( $proIds as $product_id ) {

				$product = wc_get_product( $product_id );
				if ( ! is_object( $product ) ) {
					continue;
				}

				$sku  = $product->get_sku();
				$type = $product->get_type();

				if ( isset( $sku ) && ! empty( $sku ) ) {

					$SKUs_array[ $sku ] = array(
						'product_name' => $product->get_name(),
						'type'         => $type,
						'product_sku'  => $sku,
						'product_id'   => $product_id,
						'value'        => '',
					);

					update_post_meta( $product_id, 'ced_amazon_already_uploaded_' . $mplocation, 'no' );
					delete_post_meta( $product_id, 'ced_amazon_product_asin_' . $mplocation );

					$msg        = array(
						'messageId'     => $counter,
						'sku'           => $sku,
						'operationType' => 'DELETE',
					);
					$messages[] = $msg;
					++$counter;
					$json_format = true;
				}
			}

			$header = array(
				'sellerId'    => $seller_id,
				'version'     => '2.0',
				'issueLocale' => 'in',
			);

			$product_info = array(
				'header'   => $header,
				'messages' => $messages,
			);

			$json_data = wp_json_encode( $product_info );
			$this->amz_com_opts_mng->writeStringToFile( $json_data, $jsonFileName );

			if ( $json_format ) {
				return array(
					'json_data' => $json_data,
					'SKUs'      => $SKUs_array,
				);
			} else {
				return array(
					'json_data' => '',
					'SKUs'      => array(),
				);
			}
		}

		/**
		 * Check feed status of amazon order shipment.
		 *
		 * @name umb_amazon_check_feed_status
		 * @since 1.0.0
		 * @link  http://www.cedcommerce.com/
		 */
		public function umb_amazon_check_feed_status() {

			$check_ajax = check_ajax_referer( 'ced-amazon-order-shipment', 'ajax_nonce' );
			if ( ! $check_ajax ) {
				return;
			}

			$feedId   = isset( $_POST['feed_id'] ) ? sanitize_text_field( $_POST['feed_id'] ) : '';
			$order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( $_POST['order_id'] ) : '';
			$hpos     = isset( $_POST['hpos'] ) ? sanitize_text_field( $_POST['hpos'] ) : '';

			$order      = wc_get_order( $order_id );
			$mplocation = $order->get_meta( 'ced_amazon_order_countory_code' );
			$seller_id  = $order->get_meta( 'ced_amazon_order_seller_id' );

			if ( empty( $feedId ) || empty( $mplocation ) || empty( $seller_id ) ) {
				echo esc_attr_e( 'Feed_id/Mp_location/Seller_id is missing!', 'amazon-for-woocommerce' );
				die;
			}

			$feedresponse = $this->getFeedItemsStatusSpApi( $feedId, 'POST_ORDER_FULFILLMENT_DATA', $mplocation, $seller_id );

			if ( isset( $feedresponse['body'] ) ) {

				$finalxml    = simplexml_load_string( $feedresponse['body'], 'SimpleXMLElement', LIBXML_NOCDATA );
				$finalstring = wp_json_encode( $finalxml );
				$finalresult = json_decode( $finalstring, true );

			}

			if ( isset( $finalresult['Message']['ProcessingReport']['StatusCode'] ) ) {
				if ( 'Complete' == $finalresult['Message']['ProcessingReport']['StatusCode'] ) {
					if ( 0 == $finalresult['Message']['ProcessingReport']['ProcessingSummary']['MessagesWithError'] ) {
						$feed_req = isset( $_POST['feed_req'] ) ? sanitize_text_field( $_POST['feed_req'] ) : '';
						
						$order->update_meta_data( '_amazon_umb_order_status', $feed_req );
						$order->update_meta_data( '_umb_order_feed_status', false );

						$order->save();
						
						$feeddetails             = get_post_meta( $order_id, '_umb_order_feed_details', true );
						$feeddetails['response'] = true;
						
						$order->update_meta_data( '_umb_order_feed_details', $feeddetails );
						$order->save();
						
						echo esc_attr( $feed_req ) . ' Feed is process successfully';
						die;

					} else {
						if ( isset( $finalresult['Message']['ProcessingReport']['Result'] ) ) {
							$errormessages = isset( $finalresult['Message']['ProcessingReport']['Result'][0] ) ? $finalresult['Message']['ProcessingReport']['Result'] : $finalresult['Message']['ProcessingReport'];

							foreach ( $errormessages as $errormessage ) {
								if ( isset( $errormessage['ResultDescription'] ) ) {
									echo esc_attr( $errormessage['ResultDescription'] );

									$order->update_meta_data( '_umb_order_feed_status', false );
									$order->save();
									
								}
							}
						}
						die;
					}
				}
			}
			echo 'Request is under process.';
			die;
		}


		/**
		 * Get feed item status for SP-API
		 *
		 * @name getFeedItemsStatusSpApi
		 * @since 1.0.0 
		 * @link  http://www.cedcommerce.com/
		 */
		public function getFeedItemsStatusSpApi( $feedId = '', $feed_type = '', $location_id = '', $seller_id = '' ) {

			$ced_amazon_get_feed_throttle = get_transient( 'ced_amazon_get_feed_throttle' );
			if ( $ced_amazon_get_feed_throttle ) {
				$response['body'] = 'Get feed API call limit exceeded. Please try after 5 mins.';
				return $response;
			}

			$response = array();

			if ( empty( $seller_id ) ) {
				$response['body'] = 'Seller id is missing from URL!';
				return $response;
			}

			$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );
			if ( isset( $saved_amazon_details[ $seller_id ] ) && ! empty( $saved_amazon_details[ $seller_id ] ) && is_array( $saved_amazon_details[ $seller_id ] ) ) {
				$shop_data = $saved_amazon_details[ $seller_id ];
			}

			$marketplace_id = isset( $shop_data['marketplace_id'] ) ? $shop_data['marketplace_id'] : '';
			$remote_shop_id = isset( $shop_data['seller_next_shop_id'] ) ? $shop_data['seller_next_shop_id'] : '';

			if ( empty( $marketplace_id ) || empty( $feedId ) || empty( $feed_type ) ) {
				$response['body'] = 'Invalid seller info or feed type';
				return $response;
			}

			// Get feed response by feed id:
			$feed_data = array(
				'remote_shop_id' => $remote_shop_id,
			);

			$feed_topic         = 'feed-sync?feed_id=' . $feedId;
			$feed_response_data = $this->amzonCurlRequestInstance->ced_amazon_serverless_process( $feed_topic, $feed_data, 'GET' );


			$response_body = wp_remote_retrieve_body($feed_response_data);
			$response_data = json_decode( $response_body, true);

			// check throttle if the feed type is JSON
			$throttleResponse = $this->ced_amz_check_json_feed_throttle( $feed_response_data, 'ced_amazon_get_feed_throttle');

			$decodedThrottleResponse = json_decode( $throttleResponse, true );
			if ( is_array( $decodedThrottleResponse ) && isset(  $decodedThrottleResponse['message'] ) && strpos( $decodedThrottleResponse['message'], 'Quoto Exceeded' ) === 0 ) {
				return $throttleResponse;
			}

			$feed_response_body = json_decode( $feed_response_data['body'], true );
			if ( isset( $feed_response_body['feed_action'] ) && 'JSON_LISTINGS_FEED' == $feed_response_body['feed_action'] ) {
				$response      = $feed_response_body;
				$response_data =  wp_json_encode( $response) ;
			} else {
				$response_data = wp_json_encode( $feed_response_body );
			}

			global $wpdb;
			$tableName = $wpdb->prefix . 'ced_amazon_feeds';
			
			if ( isset( $feed_response_body['status'] ) && 'DONE' == $feed_response_body['status'] ) {
				$wpdb->update( $tableName, array( 'response' => $response_data ), array( 'feed_id' => $feedId ) );
			}

			return $feed_response_body;

		}

		/**
		 * Get Time Zone
		 *
		 * @name getStandardOffsetUTC
		 * @link  http://www.cedcommerce.com/
		 */
		public function getStandardOffsetUTC() {
			$timezone = date_default_timezone_get();

			if ( 'UTC' == $timezone ) {
				return '';
			} else {
				$timezone    = new DateTimeZone( $timezone );
				$transitions = array_slice( $timezone->getTransitions(), -3, null, true );

				foreach ( array_reverse( $transitions, true ) as $transition ) {
					if ( 1 == $transition['isdst'] ) {
						continue;
					}
					return sprintf( 'UTC %+03d:%02u', $transition['offset'] / 3600, abs( $transition['offset'] ) % 3600 / 60 );
				}

				return false;
			}
		}


		public function ced_amz_check_json_feed_throttle( $response_data, $transient_name = '' ) {

			if (  isset( $response_data['success'] ) && !$response_data['success'] && isset($response_data['error']) ) {
						
				$code = $response_data['error'];
				// Check for QuotaExceeded error
				if (strpos( $code, 'QuotaExceeded') !== false) {

					set_transient( $transient_name, 'on', 300 );
					$notice['message'] = 'Quoto Exceeded for feed submission. Please try again later!';
					$notice['classes'] = 'notice notice-error is-dismissable';
					return wp_json_encode( $notice );

				}
			}

			return '{}';

		}

		public function ced_amz_check_xml_feed_throttle( $response_data, $transient_name = '' ) {

			if (  isset( $response_data['data'] ) && isset($response_data['data']['body'] ) ) {
				$error_message = $response_data['data']['body'];
				// Check for QuotaExceeded error
				if (strpos($error_message, 'QuotaExceeded') !== false) {

					set_transient( 'ced_amazon_create_feed_throttle', 'on', 300 );
					$notice['message'] = 'Quoto Exceeded for feed submission. Please try again later!';
					$notice['classes'] = 'notice notice-error is-dismissable';
					return wp_json_encode( $notice );

				}
			}

			return '{}';

		}

		public function ced_amz_offerListing_json_array(  $i, $sku, $asin, $marketplace_id, $quantity, $currency, $price_amz ) {

			return array(
				
				'messageId' => $i,
				'sku' => $sku,
				'operationType' => 'UPDATE',
				'productType' => 'PRODUCT',
				'requirements' => 'LISTING_OFFER_ONLY',
				'attributes' => array(
					'condition_type' => array(  
									array(
										'value' => 'new_new',
										'marketplace_id' => $marketplace_id
									)
					),
					'merchant_suggested_asin' => array(
							array( 
								'value' => $asin,
								'marketplace_id' => $marketplace_id
							)
						),
					'fulfillment_availability' => array(
						array(
							'fulfillment_channel_code' => 'DEFAULT',
							'quantity' => $quantity
						)
						),
					'purchasable_offer' => array(
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
				
				)
				
			);
				
		}

		public function ced_amz_prepare_feed_header( $type ) {

			$amazonxmlsubarray                = array();
			$amazonxmlsubarray['@attributes'] = array(
				'xmlns:xsi'                     => 'http://www.w3.org/2001/XMLSchema-instance',
				'xsi:noNamespaceSchemaLocation' => 'amzn-envelope.xsd',
			);

			$amazonxmlsubarray['Header']['DocumentVersion']    = '1.01';
			$amazonxmlsubarray['Header']['MerchantIdentifier'] = 'M_SELLER_XXXXXX';
			$amazonxmlsubarray['MessageType']                  = $type;
			$amazonxmlsubarray['PurgeAndReplace']              = 'false';

			return $amazonxmlsubarray;
		}

		public function ced_amz_prepareFinalStructure( $inputData, $schema, $marketplaceId = '', $languageTag = 'en_US', $type = '') {
			
			if (empty($schema)) {
				return [];
			}
			
			$schema         = json_decode($schema, true);
			$finalStructure = [];
			
			if (!empty($schema['properties'])) {
				foreach ($schema['properties'] as $key => $property) {

					if ( 'purchasable_offer' === $key ) {
						$currency       = $inputData[$key . '.currency'] ?? 'USD';
						$price_amz      = (float) sprintf('%.2f', $inputData[$key . '.our_price'] ?? 0);
						$marketplace_id = $marketplaceId;
					
						$offer = array(
							'audience'       => 'ALL',
							'currency'       => $currency,
							'marketplace_id' => $marketplace_id,
						);
					
						// Required field
						$offer['our_price'] = array(
							array(
								'schedule' => array(
									array(
										'value_with_tax' => $price_amz,
									)
								)
							)
						);
					
						// Optional fields
						$optionalPrices = array(
							'maximum_retail_price',
							'map_price',
							'minimum_seller_allowed_price',
							'maximum_seller_allowed_price'
						);
					
						foreach ( $optionalPrices as $field ) {
							$value = $inputData["$key.$field"] ?? null;
							if ( null !== $value && '' !== $value ) {
								$offer[$field] = array(
									array(
										'schedule' => array(
											array(
												'value_with_tax' => (float) sprintf('%.2f', $value)
											)
										)
									)
								);
							}
						}
					
						// Discounted Price (includes date fields)
						if ( ! empty( $inputData["$key.discounted_price"] ) ) {
							$discountValue = (float) sprintf('%.2f', $inputData["$key.discounted_price"]);
							$startDate     = $inputData["$key.discounted_price_start_at"] ?? '';
							$endDate       = $inputData["$key.discounted_price_end_at"] ?? '';
							if ( $discountValue && $startDate && $endDate ) {
								$offer['discounted_price'] = array(
									array(
										'schedule' => array(
											array(
												'value_with_tax' => $discountValue,
												'start_at'       => $startDate,
												'end_at'         => $endDate,
											)
										)
									)
								);
							}
						}
					
						// Start/End At for overall offer
						if ( ! empty( $inputData["$key.start_at"] ) ) {
							$offer['start_at'] = array(
								'value' => $inputData["$key.start_at"]
							);
						}
						if ( ! empty( $inputData["$key.end_at"] ) ) {
							$offer['end_at'] = array(
								'value' => $inputData["$key.end_at"]
							);
						}
					
						// Automated Pricing Rule
						if ( ! empty( $inputData["$key.automated_pricing_merchandising_rule_plan"] ) ) {
							$offer['automated_pricing_merchandising_rule_plan'] = array(
								array(
									'merchandising_rule' => array(
										'rule_id' => $inputData["$key.automated_pricing_merchandising_rule_plan"]
									)
								)
							);
						}
					
						$finalStructure[$key] = array( $offer );
						continue;
					}

					if ('variable' == $type && 'list_price' == $key  ) {
						continue;
					}

					// Handling nested structures
					if ( 'item_depth_width_height' === $key && isset($inputData[$key . '.depth.value'])) {
						$finalStructure[$key] = [[
							'depth' => [
								'value' => (float) sprintf( '%.3f', $inputData[$key . '.depth.value'] ) ?? null,
								'unit' => $inputData[$key . '.length.unit'] ?? 'centimeters'
							],
							'width' => [
								'value' => (float) sprintf( '%.3f', $inputData[$key . '.width.value'] ) ?? null,
								'unit' => $inputData[$key . '.width.unit'] ?? 'centimeters'
							],
							'height' => [
								'value' => (float) sprintf( '%.3f', $inputData[$key . '.height.value'] ) ?? null,
								'unit' => $inputData[$key . '.height.unit'] ?? 'centimeters'
							],
							'marketplace_id' => $marketplaceId
						]];

						continue;
					}
					
					// Handling nested structures
					if ( 'item_length_width_height' === $key && isset($inputData[$key . '.length.value'])) {
						$finalStructure[$key] = [[
							'length' => [
								'value' => (float) sprintf( '%.3f', $inputData[$key . '.length.value'] ) ?? null,
								'unit' => $inputData[$key . '.length.unit'] ?? 'centimeters'
							],
							'width' => [
								'value' => (float) sprintf( '%.3f', $inputData[$key . '.width.value'] ) ?? null,
								'unit' => $inputData[$key . '.width.unit'] ?? 'centimeters'
							],
							'height' => [
								'value' => (float) sprintf( '%.3f', $inputData[$key . '.height.value'] ) ?? null,
								'unit' => $inputData[$key . '.height.unit'] ?? 'centimeters'
							],
							'marketplace_id' => $marketplaceId
						]];

						continue;
					}
					
					// // Handling nested structures
					if ( 'item_dimensions' === $key && isset($inputData[$key . '.length.value'])) {
						$finalStructure[$key] = [[
							'length' => [
								'value' => (float) sprintf( '%.3f', $inputData[$key . '.length.value'] ) ?? null,
								'unit' => $inputData[$key . '.length.unit'] ?? 'centimeters'
							],
							'width' => [
								'value' => (float) sprintf( '%.3f', $inputData[$key . '.width.value'] ) ?? null,
								'unit' => $inputData[$key . '.width.unit'] ?? 'centimeters'
							],
							'height' => [
								'value' => (float) sprintf( '%.3f', $inputData[$key . '.height.value'] ) ?? null,
								'unit' => $inputData[$key . '.height.unit'] ?? 'centimeters'
							],
							'marketplace_id' => $marketplaceId
						]];

						continue;
					}
					
					
					if ( 'item_package_dimensions' === $key && isset($inputData[$key . '.length.value'])  ) {
						$finalStructure[$key] = [[
							'length' => [
								'value' => (float) sprintf( '%.3f', $inputData[$key . '.length.value'] ) ?? null,
								'unit' => $inputData[$key . '.length.unit'] ?? 'centimeters'
							],
							'width' => [
								'value' => (float) sprintf( '%.3f', $inputData[$key . '.width.value'] ) ?? null,
								'unit' => $inputData[$key . '.width.unit'] ?? 'centimeters'
							],
							'height' => [
								'value' => (float) sprintf( '%.3f', $inputData[$key . '.height.value'] ) ?? null,
								'unit' => $inputData[$key . '.height.unit'] ?? 'centimeters'
							],
							'marketplace_id' => $marketplaceId
						]];
						continue;
					}
					

					if ( 'child_parent_sku_relationship' === $key && 'variation' == $type ) {
 
						$finalStructure[$key][0] = [
							'marketplace_id' => $marketplaceId
						];

						if ( !empty( $inputData[$key . '.child_relationship_type'] ) ) {
							$finalStructure[$key][0]['child_relationship_type'] = $inputData[$key . '.child_relationship_type'];
						}

						if ( !empty( $inputData[$key . '.parent_sku'] ) ) {
							$finalStructure[$key][0]['parent_sku'] = (string) $inputData[$key . '.parent_sku'];
						}
						
						continue;

					}


					if ( 'variation_theme' === $key && 'simple' == $type ) {
						continue;
					}


					if ( isset( $property['items']['properties'] ) ) {
						
						$selectors  = $property['selectors'] ?? array();
						$req_attrs  = $property['items']['required'] ?? array();
						$properties = $property['items']['properties'] ?? array();
						
						if ( !empty( $properties ) ) {

							foreach ( $properties as $property => $property_structure ) {

								// if( !in_array( $property, $req_attrs ) ){
								//     continue;
								// }

								if ( isset( $property_structure['type'] ) && 'integer' == $property_structure['type'] && isset( $inputData[$key . '.' . $property ] ) && !empty( $inputData[$key . '.' . $property ] ) ) {
									$finalStructure[$key][0][$property] =  (float) sprintf( '%.3f', $inputData[$key . '.' . $property]);

								} elseif ( isset( $property_structure['type'] ) && 'number' == $property_structure['type'] && isset( $inputData[$key . '.' . $property ] ) && !empty( $inputData[$key . '.' . $property ] ) ) {
									$finalStructure[$key][0][$property] =  (float) sprintf( '%.3f', $inputData[$key . '.' . $property]);

								} elseif ( isset( $property_structure['type'] ) && 'boolean' == $property_structure['type'] && isset( $inputData[$key . '.' . $property ] ) ) {
									$finalStructure[$key][0][$property] =  (bool) $inputData[$key . '.' . $property];

								} elseif ( isset( $property_structure['type'] ) && 'array' == $property_structure['type'] ) {

									$sub_req_attrs = $property_structure['required'] ?? array();
									$subc_attrs    = $property_structure['items']['properties'] ?? array();
									
									if ( !empty( $subc_attrs ) ) {
										// $finalStructure[$key][0][$property] =  array() ;
										foreach ( $subc_attrs as $sub_key => $sub_key_data ) {

											if ( 'language_tag' === $sub_key ) {
												$finalStructure[$key][0][$property][0][$sub_key] =  $languageTag;
											} elseif ( 'marketplace_id' === $sub_key ) {
												$finalStructure[$key][0][$property][0][$sub_key] =  $marketplaceId;
											} elseif ( isset( $inputData[$key . '.' . $property . '.' . $sub_key ] ) && !empty( $inputData[$key . '.' . $property . '.' . $sub_key ] ) ) {
												$finalStructure[$key][0][$property][0][$sub_key] =  $inputData[$key . '.' . $property . '.' . $sub_key ];
											} elseif ( isset( $inputData[$key . '.' . $property  ] ) && !empty( $inputData[$key . '.' . $property  ] ) ) {
												$finalStructure[$key][0][$property][0][$sub_key] =  $inputData[$key . '.' . $property  ];
											}
											
										}

										/** Unset keys that have only 1 subkey either language_tag or marketplace_id */
										if ( isset( $finalStructure[$key][0][$property][0] ) && is_array( $finalStructure[$key][0][$property][0] ) && 1 == count( $finalStructure[$key][0][$property][0] ) ) {
											$keys = array_keys( $finalStructure[$key][0][$property][0] );
											if ( 'marketplace_id' == $keys[0] || 'language_tag' == $keys[0] ) {
												unset( $finalStructure[$key] );
											}
										}

									}

								} elseif ( isset( $property_structure['type'] ) && 'object' == $property_structure['type'] ) {

									$sub_req_attrs = $property_structure['required'] ?? array();
									$subc_attrs    = $property_structure['properties'] ?? array();

									if ( !empty( $subc_attrs ) ) {
										// $finalStructure[$key][0][$property] =  array() ;
										foreach ( $subc_attrs as $sub_key => $sub_key_data ) {

											if ( 'language_tag' === $sub_key ) {
												$finalStructure[$key][0][$property][$sub_key] =  $languageTag;

											} elseif ( 'marketplace_id' === $sub_key ) {
												$finalStructure[$key][0][$property][$sub_key] =  $marketplaceId;

											} elseif ( isset( $inputData[$key . '.' . $property . '.' . $sub_key ] ) && !empty( $inputData[$key . '.' . $property . '.' . $sub_key ] ) ) {
											   $finalStructure[$key][0][$property][$sub_key] =  $inputData[$key . '.' . $property . '.' . $sub_key ];
											} elseif ( isset( $inputData[$key . '.' . $property  ] ) && !empty( $inputData[$key . '.' . $property  ] ) ) {
												$finalStructure[$key][0][$property][$sub_key] =  $inputData[$key . '.' . $property  ];
											}
										}
									}

								} elseif ( isset( $property_structure['type'] ) && 'string' == $property_structure['type'] && isset( $inputData[$key . '.' . $property ] ) ) {

									if ( 'string' == gettype( $inputData[$key . '.' . $property ] ) && !empty( $inputData[$key . '.' . $property ] ) ) {
										$finalStructure[$key][0][$property] =  $inputData[$key . '.' . $property ] ;

									} elseif ( !empty( $inputData[$key . '.' . $property] ) && is_array( $inputData[$key . '.' . $property ] ) ) {

										foreach ( $inputData[$key . '.' . $property ] as $k => $value ) {
											$finalStructure[$key][$k]['value']          = $value ;
											$finalStructure[$key][$k]['language_tag']   = $languageTag ;
											$finalStructure[$key][$k]['marketplace_id'] = $marketplaceId ;
										}

									}
									
								}


							}

							if ( isset( $finalStructure[$key] ) && is_array( $selectors ) ) {
								if ( in_array('marketplace_id', $selectors ) ) {
									$finalStructure[$key][0]['marketplace_id'] = $marketplaceId;
								}
								if (in_array('language_tag', $selectors ) ) {
									$finalStructure[$key][0]['language_tag'] = $languageTag;
								}
							}

						}

					}

					
				}
			}
			
			return $finalStructure;
		}


		public function ced_amazon_process_upload_queue( $queue_args ) {
			
			$logger  = wc_get_logger();
			$context = array( 'source' => 'ced_amazon_process_upload_queue' );
			
			$logger->info( 'Starting to process upload queue with args: ' . wc_print_r( $queue_args, true ), $context );
			
			try {
				
				// Extract queue information
				$scheduled_time       = $queue_args['id'] ?? ''; // ID is the scheduled time
				$region               = $queue_args['region'] ?? '';
				$mplocation           = $queue_args['mplocation'] ?? '';
				$seller_id            = $queue_args['seller_id'] ?? '';
				$product_count        = $queue_args['product_count'] ?? '';
				$profile_with_pro_ids = $queue_args['profile_with_pro_ids'] ?? '';

				$skus_array        = $queue_args['skus_array'];
				$error_count       = $queue_args['error_count'];
				$error_sku         = $queue_args['errorArray'];
				$healthyProductIds = $queue_args['healthyProductIds']; // this is the array of product ids that are eligible for upload.


				$logger->info( "Processing upload queue for marketplace: {$mplocation}, seller: {$seller_id}, products: {$product_count}", $context );
				
				// Throttle check
				$ced_amazon_create_feed_throttle = get_transient( 'ced_amazon_create_feed_throttle' );
				if ( $ced_amazon_create_feed_throttle ) {
					$logger->warning( 'Create feed API call limit exceeded. Queue processing delayed.', $context );
					
					// Reschedule for later
					$new_time         = time() + 300; // 5 minutes later
					$hook_name        = 'ced_amazon_process_upload_queue';
					$queue_args['id'] = $new_time; // Update ID to new scheduled time
					
					if ( function_exists( 'as_schedule_single_action' ) ) {
						as_schedule_single_action( $new_time, $hook_name, array( $queue_args ) );
					} else {
						wp_schedule_single_event( $new_time, $hook_name, array( $queue_args ) );
					}
					
					// Update queue status to rescheduled
					$queue_manager        = Ced_Amazon_Queue_Manager::get_instance();
					$queue_args['status'] = 'rescheduled';
					$queue_manager->ced_amz_update_queue_action( $hook_name, $queue_args );
					
					return;
				}
				
				// Set throttle
				set_transient( 'ced_amazon_create_feed_throttle', true, 300 );
				
				// Read product data from files
				$upload_dir = wp_upload_dir();
				$folderPath = $upload_dir['basedir'] . '/ced-amazon/product_upload_schema/' . $mplocation;
				
				if ( !is_dir( $folderPath ) ) {
					$logger->error( "Product upload schema folder not found: {$folderPath}", $context );
					$this->update_queue_status_to_completed( $queue_args, 'failed', 'Schema folder not found' );
					return;
				}
				
				$json_files = array();
				foreach ( $healthyProductIds as $product_id ) {
					$json_files[] = $folderPath . '/' . $product_id . '.json';
				}
				
				
				$logger->info( 'Found ' . count($json_files) . ' JSON files', $context );
				
				$all_products_data = array();
				foreach ( $json_files as $file ) {
					$logger->info( 'Processing file: ' . basename($file), $context );
					$content = file_get_contents( $file );
					$data    = json_decode( $content, true );
					
					if ( $data && isset( $data['final_product_structure'] ) ) {
						// Preserve keys by using array union operator instead of array_merge
						$all_products_data = $all_products_data + $data['final_product_structure'];
						$logger->info( 'Added ' . count($data['final_product_structure']) . ' products from ' . basename($file), $context );
					} else {
						$logger->error( 'Invalid data structure in file: ' . basename($file), $context );
					}
				}
				
				if ( empty( $all_products_data ) ) {
					$logger->error( 'No product data found in schema files', $context );
					$this->update_queue_status_to_completed( $queue_args, 'failed', 'No product data found' );
					return;
				}
				
				$logger->info( 'Total products to process: ' . count($all_products_data), $context );
				$logger->info( wc_print_r($all_products_data, true), $context );
				
				// Prepare JSON feed data using profile_with_pro_ids to get product types
				$feed_data  = array();
				$message_id = 1;
				
				foreach ( $all_products_data as $product_id => $product_data ) {
					$logger->info( "Processing product ID: {$product_id}", $context );
					
					$product = wc_get_product( $product_id );
					if ( $product && $product->get_sku() ) {
						
						// Find the profile ID for this product using profile_with_pro_ids
						$profile_id   = $this->find_profile_id_for_product( $product_id, $profile_with_pro_ids );
						$product_type = $this->get_product_type_from_profile( $profile_id, $seller_id );
						
						$logger->info( "Product {$product_id}: Profile ID = {$profile_id}, Type = {$product_type}", $context );
						
						$skus_array[ $product->get_sku() ]['messageId'] = $message_id;
						$feed_data[]                                    = array(
							'messageId'     => $message_id++,
							'sku'           => $product->get_sku(),
							'operationType' => 'UPDATE',
							'productType'   => $product_type,
							'attributes'    => $product_data
						);


					} else {
						$logger->error( "Product {$product_id}: Invalid product or missing SKU", $context );
					}
				}
				
				$logger->info( 'Prepared feed data: ' . wc_print_r( $feed_data, true ), $context );

				// Call Amazon API
				$amzonCurlRequest = CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php';
				
				if ( file_exists( $amzonCurlRequest ) ) {
					require_once $amzonCurlRequest;
					$this->amzonCurlRequestInstance = new Ced_Amazon_Curl_Request();
					
					// Get remote shop ID from saved configuration
					$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );
					$remote_shop_id       = '';
					if ( isset( $saved_amazon_details[ $seller_id ] ) && ! empty( $saved_amazon_details[ $seller_id ] ) && is_array( $saved_amazon_details[ $seller_id ] ) ) {
						$shop_data      = $saved_amazon_details[ $seller_id ];
						$remote_shop_id = isset( $shop_data['seller_next_shop_id'] ) ? $shop_data['seller_next_shop_id'] : '';
					}
					
					$logger->info( 'Remote shop ID: ' . $remote_shop_id, $context );

					$seller_id_array = explode( '|', $seller_id );
				  
					$header = array(
						'sellerId'    => $seller_id_array[1],
						'version'     => '2.0',
						'issueLocale' => 'in',
					);
					
					$product_info = array(
						'header'   => $header,
						'messages' => $feed_data,
					);
					
					$feed_topic = 'create-feed';
					$data       = array(
						'feed_action'    => 'JSON_LISTINGS_FEED',
						'feed_content'   => $product_info,
						'remote_shop_id' => $remote_shop_id,
					);
					
					$logger->info( 'API request data: ' . wc_print_r( json_encode( $product_info ), true ), $context );
					
					$feed_reponse = $this->amzonCurlRequestInstance->ced_amazon_serverless_process( $feed_topic, $data, 'POST' );

					// Check for WP error
					if ( is_wp_error( $feed_reponse ) ) {

						$logger->info( wc_print_r( $feed_reponse, true ), $context );
						$logger->info( wc_print_r( 'wp api call error', true ), $context );
						
						// Update queue status to failed
						$this->update_queue_status_to_completed( $queue_args, 'failed', 'WP API call error' );
						return;
					}

					// Modified feed_action
					$feed_action = 'JSON_LISTINGS_FEED_PRODUCT_UPLOAD';
					
					$response_body = wp_remote_retrieve_body($feed_reponse);
					$response_data = json_decode($response_body, true);

					$logger->info( wc_print_r( $response_data, true ), $context );
					
					// Check throttle if the feed type is JSON
					$throttleResponse = $this->ced_amz_check_json_feed_throttle( $response_data, 'ced_amazon_create_feed_throttle');
					
					$decodedThrottleResponse = json_decode( $throttleResponse, true );
					if ( is_array( $decodedThrottleResponse ) && isset( $decodedThrottleResponse['message'] ) && strpos( $decodedThrottleResponse['message'], 'Quoto Exceeded' ) === 0 ) {
						
						$logger->warning( 'Throttle limit reached, rescheduling queue processing', $context );
						
						// Reschedule for later
						$event_time = time() + 300; // 5 minutes
						$hook_name  = 'ced_amazon_process_upload_queue';
						$hook_data  = array( $queue_args );
						
						if ( function_exists( 'as_schedule_single_action' ) ) {
							as_schedule_single_action( $event_time, $hook_name, $hook_data );
						} else {
							wp_schedule_single_event( $event_time, $hook_name, $hook_data );
						}
						
						// Update queue status to rescheduled
						$this->update_queue_status_to_completed( $queue_args, 'rescheduled', 'Throttle limit reached' );
						return;
					}

					$productuploadreponse = isset( $response_data['data'] ) ? $response_data['data'] : array();
					if ( isset( $response_data['success'] ) && !$response_data['success'] ) {
						$logger->info( wc_print_r( 'API success false, returning', true ), $context );
						$logger->info( wc_print_r( $feed_reponse, true ), $context );
						
						// Update queue status to failed
						$this->update_queue_status_to_completed( $queue_args, 'failed', 'API success false' );
						return;
					}

					if ( isset( $productuploadreponse['feed_id'] ) && ! empty( $productuploadreponse['feed_id'] ) ) {
						$feedId = $productuploadreponse['feed_id'];
						
						// Add feed ID to queue args
						$queue_args['feed-id'] = $feedId;
						$logger->info( 'Added feed ID to queue args: ' . $feedId, $context );
						
						// Insert feed info to database
						$this->amz_com_opts_mng->insertFeedInfoToDatabase( $feedId, $feed_action, $mplocation, $skus_array, 'Manual', $error_sku );
						
						// Clean up schema files after successful upload
						$this->cleanup_schema_files( $folderPath );
						
						// Update queue status to completed with feed ID
						$this->update_queue_status_to_completed( $queue_args, 'completed', 'Feed created successfully with ID: ' . $feedId );
						
						$logger->info( 'Feed created successfully with ID: ' . $feedId, $context );

						// write a code to schedule a cron job to get feed-data, using a function we have already created
						$event_time = time() + 120;
						$hook_name  = 'ced_amazon_get_feed_data';
						$hook_data  = array( array(
							'feed_id'    => $feedId, 
							'seller_id'  => $seller_id,
							'marketplace_ids' => ced_get_marketplace_id_by_country( $mplocation ),
							'region'     => $region,
							'user_id'    => $remote_shop_id,
							'action'     => 'upload',
							'mplocation' => $mplocation,
							'skus_array' => $skus_array,
							
						) );

						if ( function_exists( 'as_schedule_single_action' ) ) {
							as_schedule_single_action( $event_time, $hook_name, $hook_data );
						} else {
							wp_schedule_single_event( $event_time, $hook_name, $hook_data  );
						}

						$logger->info( 'Scheduled cron job to get feed data', $context );
						
					} else {
						$logger->error( 'No feed ID received from Amazon', $context );
						
						// Update queue status to failed
						$this->update_queue_status_to_completed( $queue_args, 'failed', 'No feed ID received' );
					}
					
				} else {
					$logger->error( 'cURL request file not found', $context );
					$this->update_queue_status_to_completed( $queue_args, 'failed', 'cURL file not found' );
				}
				
			} catch ( Exception $e ) {
				$logger->error( 'Exception in ced_amazon_process_upload_queue: ' . $e->getMessage(), $context );
				$this->update_queue_status_to_completed( $queue_args, 'failed', 'Exception: ' . $e->getMessage() );
			}
		}

		/**
		 * Find the profile ID for a given product using profile_with_pro_ids
		 */
		private function find_profile_id_for_product( $product_id, $profile_with_pro_ids ) {
			
			foreach ( $profile_with_pro_ids as $profile_id => $products_list ) {
				if ( in_array( $product_id, $products_list ) ) {
					return $profile_id;
				}
			}
			
			return null;
		}

		/**
		 * Get product type from profile ID
		 */
		private function get_product_type_from_profile( $profile_id, $seller_id ) {
			
			if ( empty( $profile_id ) || empty( $seller_id ) ) {
				return '';
			}
			
			global $wpdb;
			$amazon_profiles = $wpdb->get_results( $wpdb->prepare( "SELECT product_type FROM {$wpdb->prefix}ced_amazon_profiles WHERE `id` = %d AND `seller_id` = %s", $profile_id, $seller_id ), 'ARRAY_A' );
			if ( !empty( $amazon_profiles ) ) {
				return $amazon_profiles[0]['product_type'];
			}
			
			return '';
		}
				

		/**
		 * Update queue status to completed
		 */
		private function update_queue_status_to_completed( $queue_args, $status, $message = '' ) {
			
			$logger  = wc_get_logger();
			$context = array( 'source' => 'ced_amazon_queue_status_update' );
			
			$logger->info( "Updating queue status to: {$status}. Message: {$message}", $context );
			
			// Update queue status in database
			$queue_manager                    = Ced_Amazon_Queue_Manager::get_instance();
			$queue_args['status']             = $status;
			$queue_args['completion_message'] = $message;
			$queue_args['completed_at']       = time();
			
			$queue_manager->ced_amz_update_queue_action( 'ced_amazon_process_upload_queue', $queue_args );
			
			$logger->info( "Queue status updated to: {$status}", $context );
		}	
		
		/**
		 * Clean up schema files after successful upload
		 */
		private function cleanup_schema_files( $folder_path ) {
			
			$logger  = wc_get_logger();
			$context = array( 'source' => 'ced_amazon_cleanup_schema' );
			
			try {
				$files = glob( $folder_path . '/*.json' );
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						unlink( $file );
					}
				}
				
				// Remove empty folder
				if ( is_dir( $folder_path ) && count( glob( $folder_path . '/*' ) ) === 0 ) {
					rmdir( $folder_path );
				}
				
				$logger->info( 'Schema files cleaned up successfully', $context );
				
			} catch ( Exception $e ) {
				$logger->error( 'Error cleaning up schema files: ' . $e->getMessage(), $context );
			}
		}
		

	}


endif;

