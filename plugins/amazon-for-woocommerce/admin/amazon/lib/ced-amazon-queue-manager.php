<?php

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Amazon queue manager file.
 *
 * This file is used to perform all the relevant queue actions on Amazon.
 * Also used to get the relevant queue submission response.
 *
 * @since       1.0.0
 * @package     Amazon_Integration_For_Woocommerce
 * @subpackage  Amazon_Integration_For_Woocommerce/admin/amazon/lib  
 * @link        http://www.cedcommerce.com/
 */

if ( ! class_exists( 'Ced_Amazon_Queue_Manager' ) ) :
 
	/**
	 * Woo-marketplace queue submission functionality.
	 *
	 * Upload/update products, inventory, price, image, shipment from
	 * WooCommerce to Amazon.
	 *
	 * @since      1.0.0
	 * @package    Amazon_Integration_For_Woocommerce
	 * @subpackage Amazon_Integration_For_Woocommerce/admin/amazon/lib
	 */
	class Ced_Amazon_Queue_Manager {

		/**
		 * The Instace of Ced_Umb_Amazon_Queue_Manager.
		 *
		 * @since    1.0.0
		 * @var      $_instance   The Instance of Ced_Amazon_Queue_Manager class.
		 */
		private static $_instance;
		

		/**
		 * Ced_Amazon_Queue_Manager Instance.
		 *
		 * Ensures only one instance of Ced_Amazon_Queue_Manager is loaded or can be loaded.
		 *
		 * @name get_instance()
		 * @since 1.0.0
		 * @static
		 * @return Ced_Amazon_Queue_Manager instance.
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
		 * Schedule a single event and store its details.
		 *
		 * @param string $hook      The hook to be triggered.
		 * @param array  $args      Arguments to pass to the hook callback.
		 */
		public function ced_amz_schedule_single_event( $hook, $args = array( ) ) {

			$logger  = wc_get_logger();
			$context = $args['context'];

			$logger->info( wc_print_r( '--------------------- GOING TO CREATE SINGLE EVENT SCHEDULER-----------------------' , true ), $context );
			$logger->info( wc_print_r( $args['id'] , true ), $context );
			
			$all_regions_queued_actions = get_option( 'ced_amz_schedule_actions', array() );
			$regions_queued_actions     = isset( $all_regions_queued_actions[ $args['seller_id'] ] ) ? $all_regions_queued_actions[ $args['seller_id'] ] : array();

			$action_args = $args;
			
			unset( $action_args['context'] );
			
			$event_time = $args['id']; // ID is the scheduled time
			$hook_name  = $hook;
			$hook_data  = array( $action_args );

			/** Scheduled single event */
			if ( function_exists( 'as_schedule_single_action' ) ) {
				$resp = as_schedule_single_action( $event_time, $hook_name, $hook_data );
			} else {
				$resp = wp_schedule_single_event( $event_time, $hook_name, $hook_data  );
			}
			/** Scheduled single event */

			if ( $resp ) {
				// keeping track of time for which the last manual action is scheduled to run.
				$ced_amz_lst_manaul_opt_time = get_option( 'ced_amz_lst_manaul_opt_time', array() );
				if ( 'ced_amazon_manual_price_update_action' == $hook|| 'ced_amazon_manual_inventory_update_action' == $hook ) {
					$ced_amz_lst_manaul_opt_time['listings-feed'] = $args['id'];
				} elseif ( 'ced_amazon_relist_products' == $hook ) {
					$ced_amz_lst_manaul_opt_time['created-feed'] = $args['id'];
				}
				update_option( 'ced_amz_lst_manaul_opt_time', $ced_amz_lst_manaul_opt_time );
			}
			$str = $resp ? 'IS' : 'IS NOT'; 
			$logger->info( wc_print_r( '--------------------- SINGLE EVENT SCHEDULER ' . $str . ' CREATED -----------------------' , true ), $context );
			
			$logger->info( wc_print_r( '--------------------- Going to add action to the queue -----------------------' , true ), $context );
			// insert a new action to queue
			$this->ced_amz_insert_queue_action( $hook, $args );

			$logger->info( wc_print_r( '--------------------- Back from ced_amz_insert_queue_action function -----------------------' , true ), $context );
			return;

		}



		public function ced_amz_insert_queue_action( $hook, $args = array( ) ) {

			$logger  = wc_get_logger();
			$context = $args['context'];

			unset( $args['context']);
			
			$all_regions_queued_actions = get_option( 'ced_amz_schedule_actions', array() );
			$regions_queued_actions     = isset( $all_regions_queued_actions[ $args['seller_id'] ] ) ? $all_regions_queued_actions[ $args['seller_id'] ] : array();

			$action_names = array( 'price', 'inventory', 'relist', 'upload' );

			$current_action = '';
			foreach ( $action_names as $action_name ) {
				if ( strpos( $hook, $action_name ) > 0 ) {
					$current_action = $action_name;
				}
			}

			$queue_id = $args['id'];
			unset( $args['id'] );
			
			// Store the event details
			$regions_queued_actions[ $queue_id ] = array(
				'action'    => $current_action ,
				'id'        => $queue_id,
				'args'      => $args,
			);

			
			// /** Removes the first element if count exceeds by 500 */
			// if ( 500 > count( $regions_queued_actions) ) {
			// 	array_shift($regions_queued_actions); 
			// }

			$all_regions_queued_actions[ $args['seller_id'] ] = $regions_queued_actions;
			$resp = update_option( 'ced_amz_schedule_actions', $all_regions_queued_actions );

			$logger->info( wc_print_r( '--------------------- ADDED SINGLE EVENT ACTION TO DB -----------------------' , true ), $context );
			return;
			
		}

		public function ced_amz_update_queue_action( $hook, $args = array( ) ) {

			$logger  = wc_get_logger();
			$context = $args['context'];

			unset( $args['context']);

			$logger->info( wc_print_r( '--------------------- GOING TO UPDATE SINGLE EVENT TO DB #2 -----------------------' , true ), $context );

			$all_regions_queued_actions = get_option( 'ced_amz_schedule_actions', array() );
			$regions_queued_actions     = isset( $all_regions_queued_actions[ $args['seller_id'] ] ) ? $all_regions_queued_actions[ $args['seller_id'] ] : array();

			$action_names = array( 'price', 'inventory', 'relist', 'upload' );

			$current_action = '';
			foreach ( $action_names as $action_name ) {
				if ( strpos( $hook, $action_name ) > 0 ) {
					$current_action = $action_name;
				}
			}

			$queue_id = $args['id'];
			unset( $args['id'] );

			$updated_content = array(
				'action'    => $current_action ,
				'id'        => $queue_id,
				'args'      => $args,
			);

			$logger->info( wc_print_r( '-----------updated content fucntion-----------------', true ), $context );
			$logger->info( wc_print_r( $updated_content , true ), $context );

			// Store the event details
			$regions_queued_actions[ $queue_id ] = $updated_content;

			$logger->info( wc_print_r( $regions_queued_actions , true ), $context );
			$logger->info( wc_print_r( $regions_queued_actions , true ), $context );

			$all_regions_queued_actions[ $args['seller_id'] ] = $regions_queued_actions;
			update_option( 'ced_amz_schedule_actions', $all_regions_queued_actions );
			
			$logger->info( wc_print_r( '--------------------- UPDATED SINGLE EVENT ACTION TO DB  -----------------------' , true ), $context );
			return;


		}



	}

endif;
