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

if ( ! class_exists( 'Ced_Amazon_Common_Operations_Maanager' ) ) :

	/**
	 * Woo-marketplace feed submission functionality.
	 *
	 * @since      1.0.0
	 * @package    Amazon_Integration_For_Woocommerce
	 * @subpackage Amazon_Integration_For_Woocommerce/admin/amazon/lib
	 */
	class Ced_Amazon_Common_Operations_Maanager {

		/**
		 * The Instace of Ced_Amazon_Common_Operations_Maanager.
		 *
		 * @since    1.0.0
		 * @var      $_instance   The Instance of Ced_Amazon_Common_Operations_Maanager class.
		 */
		private static $_instance;
		public $amazon_xml_lib;
		public $product_upload_notice;
		public $feed_xml_notice;
		public $amzonCurlRequestInstance;
		public $amzQueueManager;
		public $amazon_feed_manager;
		public $region;
		public $marketplace;
		public $seller_id;
		public $remote_shop_id;
		public $product_upload_errors;


		/**
		 * Ced_Amazon_Common_Operations_Maanager Instance.
		 *
		 * Ensures only one instance of Ced_Amazon_Common_Operations_Maanager is loaded or can be loaded.
		 *
		 * @name get_instance()
		 * @since 1.0.0
		 * @static
		 * @return Ced_Amazon_Common_Operations_Maanager instance.
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

		
		}


		/**
		 * Function to get synthetic feeds from DB ( for manual inventory and price sync )
		 */
		public function get_amazon_synthetic_feed( $feed_id = null ) {
		
			if ( empty( $feed_id ) || is_null( $feed_id ) ) {
				return array();
			}

			global $wpdb;
		
			// Build table name safely from known prefix and suffix
			$table_name = $wpdb->prefix . 'ced_amazon_feeds';
		
			// Validate table name (precaution, though static in this case)
			if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
				return array();
			}
			 
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ced_amazon_feeds WHERE feed_id = %d", (int) $feed_id), ARRAY_A );
			return $results[0] ?? $results;

		}
		
		
		

		/**
		 * Function to update synthetic feeds into DB ( for manual inventory and price sync )
		 */
		public function update_amazon_synthetic_feed( $feed_id, $sku_data ) {
			global $wpdb;

			$table_name = $wpdb->prefix . 'ced_amazon_feeds';

			// Ensure $sku_data is in JSON format
			if ( is_array( $sku_data ) || is_object( $sku_data ) ) {
				$sku_json = wp_json_encode( $sku_data );
			} else {
				$sku_json = $sku_data;
			}

			// Prepare and run the update query
			$updated = $wpdb->update(
				$table_name,
				array( 'sku' => $sku_json ),
				array( 'feed_id' => $feed_id ),
				array( '%s' ), // format for sku column
				array( '%s' )  // format for feed_id
			);

			return false !== $updated;
		}


		/**
		 * SAVE FEEDID
		 *
		 * @name insertFeedInfoToDatabase
		 * @since 1.0.0
		 */
		public function insertFeedInfoToDatabase( $feedId = '', $feed_for = '', $mplocation = '', $SKUs = array(), $opt_type = '{}', $error_SKUs = array() ) {

			$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );
			$keys_array           = array();

			if ( !empty( $saved_amazon_details ) ) {
				foreach ( $saved_amazon_details as $key => $value ) {
					$keys_array[ $value['ced_mp_name'] ] = $key;
				}

			}

			global $wpdb;
			$prefix    = $wpdb->prefix;
			$tableName = $prefix . 'ced_amazon_feeds';

			$response['body'] = '';
			$response         = wp_json_encode( $response['body'] );
			$date_time        = ced_woo_timestamp();

			$wpdb->insert(
				$tableName,
				array(
					'feed_id'        => $feedId,
					'feed_action'    => $feed_for,
					'feed_location'  => $mplocation,
					'feed_date_time' => $date_time,
					'response'       => $response,
					'sku'            => json_encode( $SKUs ),
					'opt_type'       => $opt_type,
					'error_sku'      => json_encode( $error_SKUs )

				),
				array( '%s' )
			);

			// MAKING FEED API CALL TO GET DATA FOR SHIPPED ORDERS
			if ( 'POST_ORDER_FULFILLMENT_DATA' == $feed_for ) {

				$event_time = time() + 300;
				$hook_name  = 'ced_amazon_update_shipped_orders';
				$hook_data  = array( $feedId, $keys_array[ $mplocation ] );

				if ( function_exists( 'as_schedule_single_action' ) ) {
					$is_scheduled = as_schedule_single_action( $event_time, $hook_name, $hook_data );
				} else {
					$is_scheduled = wp_schedule_single_event( $event_time, $hook_name, $hook_data  );
				}

				$logger  = wc_get_logger();
				$context = array( 'source' => 'ced_amazon_update_shipped_orders' );
				if ( $is_scheduled ) {
					$logger->info( 'An action is created to update shipped orders of feedId: ' . $feedId , $context );
				} else {
					$logger->info( 'Unable to schudele an action to update shipped orders of feedId: ' . $feedId, $context );
				}

			}

			
		}


		/**
		 * This function writes xml string to destination file.
		 *
		 * @name writeStringToFile()
		 * @link  http://www.cedcommerce.com/
		 */
		public function writeStringToFile( $String, $fileName ) {
			
			$upload_dir = wp_upload_dir();
			$filePath   = $upload_dir['basedir'] . '/';

			if ( ! is_dir( $filePath ) ) {
				if ( ! mkdir( $filePath, 0755 ) ) {
					return false;
				}
			}

			$filePath = $filePath . 'ced-amazon/';
			if ( ! is_dir( $filePath ) ) {
				if ( ! mkdir( $filePath, 0755 ) ) {
					return false;
				}
			}

			if ( ! is_writable( $filePath ) ) {
				return false;
			}

			$filePath .= $fileName;
			$file      = fopen( $filePath, 'w' );
			fwrite( $file, $String );
			fclose( $file );

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

	


	}

endif;
