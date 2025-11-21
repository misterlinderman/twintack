<?php

if ( ! defined( 'ABSPATH' ) ) {
	die;
}


/**
 * Amazon order manager file.
 *
 * @since      1.0.0
 *
 * @package    Amazon_Integration_For_Woocommerce
 * @subpackage Amazon_Integration_For_Woocommerce/admin/amazon/lib
 */

if ( ! class_exists( 'Ced_Umb_Amazon_Order_Manager' ) ) :

	/**
	 * Order related functionalities.
	 *
	 * @since      1.0.0
	 * @package    Amazon_Integration_For_Woocommerce
	 * @subpackage Amazon_Integration_For_Woocommerce/admin/amazon/lib
	 * @link       http://www.cedcommerce.com/
	 */
	class Ced_Umb_Amazon_Order_Manager { 

		/**
		 * The Instace of Ced_Umb_Amazon_Feed_Manager.
		 *
		 * @since    1.0.0
		 * @var      $_instance   The Instance of Ced_Umb_Amazon_Order_Manager class.
		 */
		private static $_instance;

		public $amzonCurlRequestInstance;
		public $marketplace_order_ids = array();

		public $tax_included_regions = array( 'Amazon.de', 'Amazon.es', 'Amazon.fr',  'Amazon.co.uk',  'Amazon.it',  'Amazon.nl',  'Amazon.com.be',  'Amazon.pl',  'Amazon.jp', 'Amazon.se' );
		public $tax_excluded_regions = array( 'Amazon.ca', 'Amazon.com', 'Amazon.in', 'Amazon.ae', 'Amazon.com.tr', 'Amazon.br', 'Amazon.com.mx', 'Amazon.com.au', 'Amazon.com.sg', 'Amazon.eg', 'Amazon.sa');

		public $woo_tax = false;
		public $global_setting_data;

		public $logger;
		public $tax_rates                       = array();
		public $context                         = array( 'source' => 'ced_amazon_order_fetch' );
		public $ced_amz_not_imported_orders     = array();
		public $ced_amz_all_not_imported_orders = array();


		/**
		 * Ced_Umb_Amazon_Feed_Manager Instance.
		 *
		 * Ensures only one instance of Ced_Umb_Amazon_Order_Manager is loaded or can be loaded.
		 *
		 * @since 1.0.0
		 * @static
		 * @return Ced_Umb_Amazon_Order_Manager instance.
		 * @link  http://www.cedcommerce.com/
		 */
		public static function get_instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}


		public function __construct() {

			$amzonCurlRequest = CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php';
			if ( file_exists( $amzonCurlRequest ) ) {
				require_once $amzonCurlRequest;
				$this->amzonCurlRequestInstance = new Ced_Amazon_Curl_Request();
			}

		}


		/**
		 * Get order info.
		 *
		 * @name get_marketplace_info()
		 * @since 1.0.0
		 * @link  http://www.cedcommerce.com/
		 */
		public function get_marketplace_info( $order_id = '' ) {


			if ( ! is_null( $order_id ) ) {
				$order = wc_get_order( $order_id );
				if ( is_wp_error( $order ) ) {
					return false;
				} elseif ( '' == $order ) {
					return false;
				} else {
					$order_from  = $order->get_meta( '_umb_marketplace' );
					$marketplace = strtolower( $order_from );
					return $marketplace;
					
				}
			}
		}

		/**
		 * Meta boxes for managing the orders at woo order page.
		 *
		 * @name add_meta_boxes()
		 * @since 1.0.
		 * @link  http://www.cedcommerce.com/
		 */
		public function add_meta_boxes() {
			global $post;

			$post_type   = get_post_type( $post );
			$order_types = wc_get_order_types();

			$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';

			if ( in_array( $post_type, $order_types ) || 'wc-orders' == $page ) {

				$order_id = isset( $post->ID ) ? intval( $post->ID ) : ''; 
				if ( empty( $order_id ) ) {
					$order_id = isset( $_GET['id'] ) ? sanitize_text_field( $_GET['id'] ) : '';
				}

				if ( ! is_null( $order_id ) ) {
					$order = wc_get_order( $order_id );
					if ( ! is_wp_error( $order ) && is_object( $order ) ) {
						if ( 'Amazon' == $order->get_meta( '_umb_marketplace' ) ) {
						   add_meta_box( 'ced-amazon-order-manager', __( 'Manage Amazon orders', 'amazon-for-woocommerce' ) . wc_help_tip( __( 'Please send shipping confirmation or order cancellation request.', 'amazon-for-woocommerce' ) ), array( $this, 'ced_amazon_order_manager_box' ) );
					
						}
					}
				}
			}
		}

		/**
		 * Order meta box at woo order page.
		 *
		 * @name order_manager_box()
		 * @since 1.0.0
		 * @link  http://www.cedcommerce.com/
		 */
		public function ced_amazon_order_manager_box() {

			$template_path = CED_AMAZON_DIRPATH . 'admin/helper/order_template.php';
			if ( file_exists( $template_path ) ) {
				require_once $template_path;
			}

		}

		/**
		 * This function to fetch order from amazon seller panel
		 *
		 * @name fetchOrders
		 * @since 1.0.0
		 */
		public function fetchOrders( $params = array()  ) {

			$mplocation      = $params['mplocation'] ?? '';
			$cron            = $params['cron'] ?? false ;
			$seller_id       = $params['seller_id'] ?? '' ;
			$next_token      = $params['next_token'] ?? '';
			$region          = $params['region'] ?? '';
			$marketplace_ids = $params['marketplace_ids'] ?? '';

			$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', array() );
			$global_setting_data  = get_option( 'ced_amazon_global_settings', array() );

			if ( empty( $seller_id ) ) {
				/** 
				 * Seller id will be empty in case of automatic order fetch
				 * Whereas in case of manual orders fetch we have seller id.
				 * 
				 * Marketplace ids will be empty in case of manual order fetch
				 * Whereas in case of automatic orders fetch we have array of marketplace ids.
				 * 
				 * Below sellerId is used to get general order settings, to fetch new orders(for automatic fetch).
				 */
				$seller_id = ced_amz_get_seller_id_by_mrkp_id( $marketplace_ids[0] );
			} 
			
			$this->logger = wc_get_logger();
			$this->logger->info( wc_print_r( 'mplocation is: ' . $mplocation . ' seller_mp_key is: ' . $seller_id , true ), $this->context );
			$this->logger->info( wc_print_r( $params, true ), $this->context );
			
			$this->ced_amz_all_not_imported_orders = get_option( 'ced_amz_all_not_imported_orders', array() );
			
			$cronVal = '';
			if ( $cron ) {
				$cronVal = 'Cron is enabled';
			} else {
				$cronVal = 'Cron is not enabled';
			}

			// throttle check
			$ced_amazon_orders_throttle = get_transient( 'ced_amazon_orders_throttle' );
			if ( $ced_amazon_orders_throttle ) {
				$this->logger->info( "API call limit exceeded. Please try after 5 mins.! \n\n\n", $this->context );
				return;
			}

			$this->global_setting_data = isset( $global_setting_data[ $seller_id ] ) ? $global_setting_data[ $seller_id ] : array();
			
			if ( $cron) {
				/** In case of automatic order fetch we will fetch orders of flat 48 hours only. */
				$time_limit = '-48 hours';
			} else {
				/** In case of manual order duration will depend seller location settings. */
				$time_limit = ! empty( $this->global_setting_data['ced_amazon_order_sync_time_limit'] ) ? $this->global_setting_data['ced_amazon_order_sync_time_limit'] : 24;
				$time_limit = "-$time_limit hours";
			}
			
			/**
			 * Remote_shop_id ( sample ) in case of automatic order fetch.
			 */
			if ( isset( $saved_amazon_details[ $seller_id ] ) && ! empty( $saved_amazon_details[ $seller_id ] ) && is_array( $saved_amazon_details[ $seller_id ] ) ) {
				$shop_data = $saved_amazon_details[ $seller_id ];
			}
			$remote_shop_id = isset( $shop_data['seller_next_shop_id'] ) ? $shop_data['seller_next_shop_id'] : '';

			/** Get marketplace ids in case of manual order fetch ( cron = false ) */
			if ( !$cron ) {
				$marketplace_ids = isset( $shop_data['marketplace_id'] ) ? $shop_data['marketplace_id'] : '';
			}
			
			try {

				set_time_limit( 600 );
				wp_raise_memory_limit( -1 );

				$time_limit = gmdate( 'Y-m-d\Th:i:s\Z', strtotime( $time_limit ) );

				// Order list
				if ( ! empty( $params['amz_order_id'] ) ) {
					$order_topic = 'order?amazon_order_id=' . $params['amz_order_id'];
					$order_data  = array( 'remote_shop_id' => $remote_shop_id );
				} else {

					$orders_query_params = array(
						'updated_after'  => $time_limit,
						'order_statuses' => 'Unshipped,PartiallyShipped,Shipped',
						'next_token'     => $next_token,
						'marketplace_ids'     => is_array( $marketplace_ids ) ? implode( ',', $marketplace_ids ) : $marketplace_ids // 'A2EUQ1WTGCTBG2,ATVPDKIKX0DER' // $marketplace_ids
					);

					$ced_amz_fulfill_chn = ! empty( $this->global_setting_data['fulfillment_channels'] ) ? $this->global_setting_data['fulfillment_channels'] : '';
					if ( 'MFN' == $ced_amz_fulfill_chn || 'AFN' == $ced_amz_fulfill_chn ) {
						$orders_query_params['fulfillment_channels'] = $ced_amz_fulfill_chn;
					}

					$order_topic = 'order?' . http_build_query( $orders_query_params );
					$order_data  = array( 'remote_shop_id' => $remote_shop_id );

					
				}

				$this->logger->info( wc_print_r( '------------------------ GET ORDERS API RESPONSE STARTS ---------------------------', true ), $this->context );
				$order_reponse_main = $this->amzonCurlRequestInstance->ced_amazon_serverless_process( $order_topic, $order_data, 'GET' );
				// $this->logger->info( wc_print_r( $order_reponse_main, true ), $this->context );
				$this->logger->info( wc_print_r( '------------------------ GET ORDERS API RESPONSE ENDS ----------------------------', true ), $this->context );

				$code = wp_remote_retrieve_response_code( $order_reponse_main );
				if ( 429 == $code ) {
					set_transient( 'ced_amazon_orders_throttle', 'on', 300 );
				}

				if ( is_wp_error( $order_reponse_main ) ) {
					$this->logger->info( wc_print_r( 'error while making api call. Returning back1.', true ), $this->context );
					return;
				}

				$response = json_decode( $order_reponse_main['body'], true );
				
				if ( isset( $response['success'] ) && false === (bool) $response['success'] ) {
					$this->logger->info( wc_print_r( '---------------------------- ERROR WHILE FETCHING ORDERS -------------------------------', true ), $this->context );
					return;
				}

				if ( ! empty( $params['amz_order_id'] ) ) {
					$order_reponse[0] = isset( $response['data'] ) && isset( $response['data']['payload'] ) ? $response['data']['payload'] : array();
				} else {
					$order_reponse = isset( $response['orders'] ) ? $response['orders'] : array();
				}

				$orderlists = $order_reponse;

				// Save next token for order fetch (when order response are more than 100)
				if ( isset( $response['NextToken'] ) ) {

					$event_time =  time();
					$hook_name  = 'ced_amz_fetch_next_page_orders';
					$hook_data  =  array( $response['NextToken'], $mplocation, $seller_id );

					if ( function_exists( 'as_schedule_single_action' ) ) {
						$is_scheduled2 = as_schedule_single_action( $event_time, $hook_name, $hook_data );
					} else {
						$is_scheduled2 = wp_schedule_single_event( $event_time, $hook_name, $hook_data  );
					}

					$logger2  = wc_get_logger();
					$context2 = array( 'source' => 'ced_amazon_order_fetch' );
					if ( $is_scheduled2 ) {
						$logger2->info( 'ced_amz_fetch_next_page_orders is scheduled for next time', $context2 );
					} else {
						$logger2->info( 'unable to schuled ced_amz_fetch_next_page_orders is scheduled for next time', $context2 );
					}
				}


				$ced_amazon_regions_info = array();
				$amzonRegions            = CED_AMAZON_DIRPATH . 'admin/partials/amazonRegions.php';
				if ( file_exists( $amzonRegions ) ) {
					require_once $amzonRegions;
				}

				$counter = 1;

				if ( isset( $orderlists ) && ! empty( $orderlists ) ) {

					foreach ( $orderlists as $orderlist ) {

						$amazon_order_detail = $orderlist;
						
						/** SellerId depends upon marketplace id in case of automatic order fetch. */
						$order_marketplace                 = $amazon_order_detail['MarketplaceId'];
						$mplocation                        = $ced_amazon_regions_info[$order_marketplace]['shop-name'];
						$seller_id                         = ced_amz_get_seller_id_by_mrkp_id( $order_marketplace );
						$this->ced_amz_not_imported_orders = isset( $this->ced_amz_all_not_imported_orders[$seller_id] ) ?  $this->ced_amz_all_not_imported_orders[$seller_id] : array();
			
						$this->global_setting_data = isset( $global_setting_data[ $seller_id ] ) ? $global_setting_data[ $seller_id ] : array();
						if ( isset( $this->global_setting_data['ced_amazon_order_taxation_rule'] )  ) {
							if ( 'woo_tax' == $this->global_setting_data['ced_amazon_order_taxation_rule'] ) {
								$this->woo_tax = true;
							} else {
								$this->woo_tax = false;
							}
						} else {
							$this->woo_tax = false;
						}
					
						$this->logger->info( wc_print_r( '---------------- STARTED WORKING ON ORDER ' . $orderlist['AmazonOrderId'] . '-------------------', true ), $this->context );

						$amazonOrderActualData = array();
						// Fetch only 5 orders via manually fetch request
						if ( ! $cron ) {
							if ( 5 < $counter ) {
								return;
							}
						}

						$exist_order_id = $this->is_umb_order_exists( $amazon_order_detail['AmazonOrderId'] );
						if ( $exist_order_id ) {
							$handled = apply_filters( 'ced_amazon_existing_order_handling', false, $exist_order_id, $amazon_order_detail, $this );
							if ( $handled ) {
								$this->logger->info( wc_print_r( '----------------' . $orderlist['AmazonOrderId'] . ' ALREADY EXISTS ON WOOCOMMERCE-------------------', true ), $this->context );
								continue;
							}
						}

						$amazonOrderActualData['order_total'] = $orderlist['OrderTotal']['CurrencyCode'] . ' ' . $orderlist['OrderTotal']['Amount']; 

						$site_url = str_replace( array( 'http://', 'https://' ), array( '', '' ), get_site_url() );
						if ( ! empty( $site_url ) && ! empty( $amazon_order_detail['AmazonOrderId'] ) ) {
							$amazon_order_detail['BuyerEmail'] = $amazon_order_detail['AmazonOrderId'] . '@' . $site_url;
						}

						$amzCurrencyCode                  = isset( $amazon_order_detail['OrderTotal'] ) && isset( $amazon_order_detail['OrderTotal']['CurrencyCode'] ) ? $amazon_order_detail['OrderTotal']['CurrencyCode'] : '';
						$amazon_order_detail['BuyerName'] = isset( $amazon_order_detail['BuyerInfo']['BuyerName'] ) ? $amazon_order_detail['BuyerInfo']['BuyerName'] : '';

						$amazonorderid      = isset( $amazon_order_detail['AmazonOrderId'] ) ? $amazon_order_detail['AmazonOrderId'] : '';
						$fulfillmentChannel = isset( $amazon_order_detail['FulfillmentChannel'] ) ? $amazon_order_detail['FulfillmentChannel'] : '';
						$salesChannel       = isset( $amazon_order_detail['SalesChannel'] ) ? $amazon_order_detail['SalesChannel'] : '';

						$address                          = $this->ced_amazon_shipping_and_billing_address( $amazon_order_detail['ShippingAddress'], $amazonorderid, $params, $remote_shop_id, $amazon_order_detail );
						$ced_amazon_currency_convert_rate = $this->ced_amazon_get_currency_convert_rate( $amzCurrencyCode );

						// Get Order items
						$order_topic = 'order-items?amazon_order_id=' . $amazonorderid;
						$order_data  = array(
							'order_id'       => $amazonorderid,
							'remote_shop_id' => $remote_shop_id,
							
						);

						$order_item_reponse = $this->amzonCurlRequestInstance->ced_amazon_serverless_process( $order_topic, $order_data, 'GET' );
						$this->logger->info( wc_print_r( '------------------------------- FETCHED ORDER API CALL RESPONSE STARTS --------------------------------', true ), $this->context );
						
						$code = wp_remote_retrieve_response_code( $order_item_reponse );
						if ( 429 == $code ) {
							set_transient( 'ced_amazon_order_item_throttle', 'on', 300 );
						}

						if ( is_wp_error( $order_item_reponse ) ) {
							return;
						}

						$order_item_reponse = json_decode( $order_item_reponse['body'], true );
						$amzitemlistitems   = $order_item_reponse['payload']['OrderItems'];
						$amzitemlistitems   = apply_filters( 'ced_amazon_order_lineitem_modifications', $amzitemlistitems, $salesChannel );

						$orderlineitems = array();

						if ( isset( $amzitemlistitems ) && ! empty( $amzitemlistitems ) ) {

							$shipping_price     = 0;
							$shipping_tax       = 0;
							$promotion_discount = 0;
							$shipping_discount  = 0;

							foreach ( $amzitemlistitems as $linenu => $amzitemlistitem ) {

								$product_price = 0;
								$product_tax   = 0;

								$amazonOrderActualData['items'][] = array(
									'product_price' => isset( $amzitemlistitem['ItemPrice'] ) ? $amzitemlistitem['ItemPrice'] : 0,
									'product_tax'   => isset( $amzitemlistitem['ItemTax'] ) ? $amzitemlistitem['ItemTax'] : 0,
									'shipping' => isset( $amzitemlistitem['ShippingPrice'] ) ? $amzitemlistitem['ShippingPrice'] : 0,
									'shipping_discount' => isset( $amzitemlistitem['ShippingDiscount'] ) ? $amzitemlistitem['ShippingDiscount'] : 0,
									'shipping_tax' => isset( $amzitemlistitem['ShippingTax'] ) ? $amzitemlistitem['ShippingTax'] : 0,
									'promotion_discount' => isset( $amzitemlistitem['PromotionDiscount'] ) ? $amzitemlistitem['PromotionDiscount'] : 0,
									'promotion_tax' => isset( $amzitemlistitem['PromotionDiscountTax'] ) ? $amzitemlistitem['PromotionDiscountTax'] : 0,
									
								); 

								$sku  = $amzitemlistitem['SellerSKU'];
								$asin = $amzitemlistitem['ASIN'];

								if ( $sku ) {
									$ID = $this->ced_amz_get_id_by_sku( $sku );
								}

								$product_id = isset( $ID[0] ) ? $ID[0] : '';
								$product    = wc_get_product( $product_id );
								if ( ! is_object( $product ) ) {

									// to store list of not imported orders
									if ( !isset( $this->ced_amz_all_not_imported_orders[$seller_id][ $orderlist['AmazonOrderId'] ] ) ) {

										$this->ced_amz_all_not_imported_orders[$seller_id][  $orderlist['AmazonOrderId'] ] = array(
											'message' => ' Product does not exist with SKU ' . $sku,
											'order_data' => $orderlist,
											'item_data'  => $amzitemlistitems
										);

									}
									
									$this->logger->info( wc_print_r( 'product does not exists with SKU: ' . $sku , true ), $this->context );
									continue;
								} else {
									$this->logger->info( wc_print_r( 'product exists with SKU: ' . $sku, true ), $this->context );
								}

								$product_qty     = $amzitemlistitem['QuantityOrdered'];
								$ced_price_array = $this->ced_amazon_item_price_hanlder( $amzitemlistitem, $salesChannel );

								$product_price = $ced_price_array['product_price'];
								$product_tax   = $ced_price_array['product_tax'];

								$shipping_price     += $ced_price_array['shipping_price'];
								$shipping_tax       += $ced_price_array['shipping_tax'];
								$promotion_discount += $ced_price_array['promotion_discount'];
								$shipping_discount  += $ced_price_array['shipping_discount'];
								
								$item = array(
									'OrderedQty' => $product_qty,
									'CancelQty'  => '',
									'UnitPrice'  => $product_price * $ced_amazon_currency_convert_rate,
									'UnitTax'    => $product_tax   * $ced_amazon_currency_convert_rate,
									'ID'         => $product_id,
									'Sku'        => $sku,
								);

								$orderlineitems[] = $item;

							}
						}

						if ( empty( $orderlineitems ) ) {
							$this->logger->info( wc_print_r( $cronVal . " Amazon Order $amazonorderid SKU does not exist in woo.", true), $this->context );
							continue;
						}

						$shippingservice = $amazon_order_detail['ShipmentServiceLevelCategory'];

						$OrderNumber    = isset( $amazon_order_detail['AmazonOrderId'] ) ? $amazon_order_detail['AmazonOrderId'] : '';
						$OrderItemsInfo = array(
							'OrderNumber'    => $OrderNumber,
							'ItemsArray'     => $orderlineitems,
							'tax'            => 0,
							'ShippingAmount' => $shipping_price * $ced_amazon_currency_convert_rate,
							'ShippingTax'    => $shipping_tax * $ced_amazon_currency_convert_rate,
							'ShipService'    => $shippingservice,
							'DiscountAmount' => $promotion_discount * $ced_amazon_currency_convert_rate,
							'ShippingDiscount' => $shipping_discount * $ced_amazon_currency_convert_rate

						);

						$merchantOrderId = $OrderNumber;
						$amazonOrderMeta = array(
							'amazon_order_id'   => $merchantOrderId,
							'order_detail'      => $amazon_order_detail,
							'order_item_detail' => $amzitemlistitems,
							'order_items'       => $orderlineitems,
							'OrderItemsInfo'    => $OrderItemsInfo,
							'amzCurrencyCode'   => $amzCurrencyCode,
						);

						$buyeremail = isset( $amazon_order_detail['BuyerInfo'] ) && isset( $amazon_order_detail['BuyerInfo']['BuyerEmail'] ) ? $amazon_order_detail['BuyerInfo']['BuyerEmail'] : '';
						$buyername  = isset( $amazon_order_detail['BuyerInfo'] ) && isset( $amazon_order_detail['BuyerInfo']['BuyerName'] ) ? $amazon_order_detail['BuyerInfo']['BuyerName'] : '';

						$amazonorderid      = isset( $amazon_order_detail['AmazonOrderId'] ) ? $amazon_order_detail['AmazonOrderId'] : '';
						$fulfillmentChannel = isset( $amazon_order_detail['FulfillmentChannel'] ) ? $amazon_order_detail['FulfillmentChannel'] : '';
						$salesChannel       = isset( $amazon_order_detail['SalesChannel'] ) ? $amazon_order_detail['SalesChannel'] : '';

						$OrderNumber = isset( $amazon_order_detail['AmazonOrderId'] ) ? $amazon_order_detail['AmazonOrderId'] : '';
						$order_id    = $this->create_order( $address, $OrderItemsInfo, 'Amazon', $amazonOrderMeta, $mplocation, $seller_id, $cronVal );

						if ( isset( $this->ced_amz_all_not_imported_orders[$seller_id] ) && is_array( $this->ced_amz_all_not_imported_orders[$seller_id] ) && array_key_exists( $OrderNumber, $this->ced_amz_all_not_imported_orders[$seller_id] ) ) {
							$this->logger->info( $OrderNumber . ' order exists in not imported order list, removing the order.', $this->context );
							unset( $this->ced_amz_all_not_imported_orders[$seller_id][ $OrderNumber ] );
						} else {
							$this->logger->info( $OrderNumber . ' order does not exist in not imported order list.', $this->context );
						}

					$order = wc_get_order( $order_id );
					// DISABLED: JSON order data logging - creates excessive order notes
					// $order->add_order_note( json_encode($amazonOrderActualData) );

						$order->update_meta_data( 'ced_amazon_order_countory_code', $mplocation );
						$order->update_meta_data( 'ced_umb_order_sales_channel', $salesChannel );
						$order->update_meta_data( 'ced_umb_amazon_fulfillment_channel', $fulfillmentChannel );

						$order->save();

						$this->ced_amazon_manage_order_status( $order, $counter, $amazon_order_detail['OrderStatus']  );
						$order->update_meta_data( 'umb_amazon_shippied_data', $amazonOrderMeta );
						$order->save();

						++$counter;

						update_option( 'ced_amz_all_not_imported_orders', $this->ced_amz_all_not_imported_orders );

					}

					$resp = ced_remote_request( 'amazon', $this->marketplace_order_ids );
					$this->logger->info(  wc_print_r( $resp , true) , $this->context );

					$mod_resp = json_decode( $resp, true );

					$allowedOrders    = $mod_resp['allowed_orders'] ?? 0;
					$used_orders_data = $mod_resp['used_orders_data'] ?? array();
					$startDates       = array();
					$count            = 0;

					if ( !empty($used_orders_data) ) {
						foreach ( $used_orders_data as $key => $data ) {
							if ( 'total' == $key ) {
							   continue;
							}
							$startDates[] = $data['start_date'];
						}

						$maxDate = max($startDates);
						
						foreach ( $used_orders_data as $key2 => $data ) {
							if ( 'total' == $key ) {
							  continue;
							}
							if ( $data['start_date'] == $maxDate ) {
								$count = $data['count'];
							}
							
						}
					}

					if ( 0 == $allowedOrders ) {
						require_once CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-billing-apis.php';

						$ced_billing_instance  = new Billing_Apis();
						$current_plan_response = $ced_billing_instance->getAmazonPlanById( );
		
						if ( isset( $current_plan_response['status'] ) && $current_plan_response['status'] ) {
							$responseBody  = isset( $current_plan_response['data'] ) && isset( $current_plan_response['data'][0] ) ? $current_plan_response['data'][0] : array() ;
							$allowedOrders = $responseBody['allowed_orders'] ?? 0;
						}
					}

					$order_count_array = array(
						'allowedOrders' => $allowedOrders, 'count' => $count
					);
					
					update_option( 'ced_amz_order_imported_count', $order_count_array );
					$this->logger->info(  wc_print_r( $resp , true) , $this->context );
				
					$this->logger->info( '------------------------------------- ends mplocation is: ' . $mplocation . ' ------------------------------- ' , $this->context );

					// $this->ced_amz_all_not_imported_orders[$seller_id] = $this->ced_amz_not_imported_orders;
					update_option( 'ced_amz_all_not_imported_orders', $this->ced_amz_all_not_imported_orders );

					return array( 'status' => 'completed', 'orders_imported' => $this->marketplace_order_ids ) ;

				} else {

					$this->logger->info( wc_print_r( $cronVal . " No orders found. \n\n\n", true), $this->context );
					return array( 'status' => 'completed', 'orders_imported' => array() ) ;

				}
			} catch ( Exception $e ) {

				$this->logger->info( wc_print_r( 'An error occured', true ), $this->context );
				$this->logger->info( wc_print_r( $e->getMessage(), true ), $this->context );

				return array( 'status' => 'failed', 'orders_imported' => array() ) ;
			}

		}



		public function ced_amazon_shipping_and_billing_address( $shippingAddress, $amazonorderid, $params, $remote_shop_id, $amazon_order_detail = array() ) {

			$ShipToFirstName = isset( $shippingAddress['Name'] ) ? $shippingAddress['Name'] : '';
			/*explode first name and last name*/

			if ( empty( $ShipToFirstName ) ) {

				$order_topic = 'order?amazon_order_id=' . $amazonorderid;
				$order_data  = array(
					'remote_shop_id' => $remote_shop_id,
				);

				$single_order = $this->amzonCurlRequestInstance->ced_amazon_serverless_process( $order_topic, $order_data, 'GET' );
				$single_order = json_decode( $single_order['body'], true );

				if ( $single_order['success'] && isset( $single_order['data'] ) && isset( $single_order['data']['payload'] ) ) {

					$shippingAddress = $single_order['data']['payload']['ShippingAddress'];
					$ShipToFirstName = isset( $shippingAddress['Name'] ) ? $shippingAddress['Name'] : '';

				}
			}

			// list( $shipping_firstname, $shipping_lastname ) = explode( ' ', $ShipToFirstName, 2 );
			if ( ! empty( $ShipToFirstName ) ) {
				$shippingNameArray  = explode( ' ', $ShipToFirstName, 2 );
				$shipping_firstname = isset( $shippingNameArray[0] ) ? $shippingNameArray[0] : '';
				$shipping_lastname  = isset( $shippingNameArray[1] ) ? $shippingNameArray[1] : '';
			}

			$ShipToFirstName = ! empty( $shipping_firstname ) ? sanitize_user( $shipping_firstname, true ) : '';
			$ShipToLastName  = ! empty( $shipping_lastname ) ? sanitize_user( $shipping_lastname, true ) : '';
			$first_buyername = ! empty( $shipping_firstname ) ? sanitize_user( $shipping_firstname, true ) : '';
			$last_buyername  = ! empty( $shipping_lastname ) ? sanitize_user( $shipping_lastname, true ) : '';

			$ShipToAddress1 = isset( $shippingAddress['AddressLine1'] ) ? $shippingAddress['AddressLine1'] : '';
			$ShipToAddress2 = isset( $shippingAddress['AddressLine2'] ) ? $shippingAddress['AddressLine2'] : '';

			if ( ! empty( $ShipToAddress2 ) && empty( $ShipToAddress1 ) ) {
				$ShipToAddress1 = $ShipToAddress2;
				$ShipToAddress2 = '';
			}

			$ShipToCityName          = isset( $shippingAddress['City'] ) ? $shippingAddress['City'] : '';
			$ShipToCountyName        = isset( $shippingAddress['County'] ) ? $shippingAddress['County'] : '';
			$ShipToDistrictName      = isset( $shippingAddress['District'] ) ? $shippingAddress['District'] : '';
			$ShipToStateOrRegionName = isset( $shippingAddress['StateOrRegion'] ) ? $shippingAddress['StateOrRegion'] : '';
			$ShipToZipCode           = isset( $shippingAddress['PostalCode'] ) ? $shippingAddress['PostalCode'] : '';
			$ShipToCountry           = isset( $shippingAddress['CountryCode'] ) ? $shippingAddress['CountryCode'] : '';
			$ShipToPhone             = isset( $shippingAddress['Phone'] ) ? $shippingAddress['Phone'] : '';

			$ShippingAddress = array(
				'first_name' => $ShipToFirstName,
				'last_name'  => $ShipToLastName,
				'address_1'  => $ShipToAddress1,
				'address_2'  => $ShipToAddress2,
				'city'       => $ShipToCityName,
				'county'     => $ShipToCountyName,
				'district'   => $ShipToDistrictName,
				'state'      => $ShipToStateOrRegionName,
				'postcode'   => $ShipToZipCode,
				'country'    => $ShipToCountry,
				'phone'      => $ShipToPhone,
			);

			$buyeremail = isset( $amazon_order_detail['BuyerInfo'] ) && isset( $amazon_order_detail['BuyerInfo']['BuyerEmail'] ) ? $amazon_order_detail['BuyerInfo']['BuyerEmail'] : '';
			// $buyername  = isset( $amazon_order_detail['BuyerInfo'] ) && isset( $amazon_order_detail['BuyerInfo']['BuyerName'] ) ? $amazon_order_detail['BuyerInfo']['BuyerName'] : '';

			$BillingAddress = array(
				'first_name' => $first_buyername,
				'last_name'  => $last_buyername,
				'email'      => $buyeremail,
				'address_1'  => $ShipToAddress1,
				'address_2'  => $ShipToAddress2,
				'city'       => $ShipToCityName,
				'county'     => $ShipToCountyName,
				'district'   => $ShipToDistrictName,
				'state'      => $ShipToStateOrRegionName,
				'postcode'   => $ShipToZipCode,
				'country'    => $ShipToCountry,
				'phone'      => $ShipToPhone,
			);


			return array(
				'shipping' => $ShippingAddress,
				'billing'  => $BillingAddress,
			);

		}


		public function ced_amazon_item_price_hanlder( $amzitemlistitem, $salesChannel ) {

			$product_price      = 0;
			$product_tax        = 0;
			$shipping_price     = 0;
			$shipping_tax       = 0;
			$promotion_discount = 0;
			$shipping_discount  = 0;

			$product_qty = isset( $amzitemlistitem['QuantityOrdered'] ) ? $amzitemlistitem['QuantityOrdered'] : 1;
			if ( $product_qty > 1 ) {
				$product_price = $amzitemlistitem['ItemPrice']['Amount'] / $product_qty;
			} else {
				$product_price = $amzitemlistitem['ItemPrice']['Amount'];
			}

			if ( isset( $amzitemlistitem['ShippingPrice'] ) && ! empty( $amzitemlistitem['ShippingPrice']['Amount'] ) ) {
				$shipping_price = $amzitemlistitem['ShippingPrice']['Amount'];
			}

			if ( isset( $amzitemlistitem['ShippingTax'] ) && ! empty( $amzitemlistitem['ShippingTax']['Amount'] ) ) {
				$shipping_tax = $amzitemlistitem['ShippingTax']['Amount'];
			}

			if ( isset( $amzitemlistitem['ItemTax'] ) && $amzitemlistitem['ItemTax']['Amount'] > 0 ) {
				$product_tax = $amzitemlistitem['ItemTax']['Amount'];
			} else {
				$product_tax = 0;
			}

			if ( isset( $amzitemlistitem['PromotionDiscount'] ) && $amzitemlistitem['PromotionDiscount']['Amount'] > 0 ) {
				$promotion_discount = $amzitemlistitem['PromotionDiscount']['Amount'];
			}

			if ( isset( $amzitemlistitem['ShippingDiscount'] ) && $amzitemlistitem['ShippingDiscount']['Amount'] > 0 ) {
				$shipping_discount = $amzitemlistitem['ShippingDiscount']['Amount'];
			}

			if ( in_array( $salesChannel, $this->tax_included_regions ) ) {
				$product_price  = $product_price - ( $product_tax / $product_qty ); 
				$shipping_price = $shipping_price - $shipping_tax; 
			} 

			return array(
				'product_price'  => $product_price,
				'shipping_price' => $shipping_price,
				'product_tax'    => $product_tax,
				'shipping_tax'   => $shipping_tax,
				'promotion_discount' => $promotion_discount,
				'shipping_discount' => $shipping_discount
			);


		}


		public function ced_amazon_get_currency_convert_rate( $amzCurrencyCode = '') {
			
			$ced_woo_store_currency           = ! empty( $this->global_setting_data['ced_amazon_order_currency'] ) ? $this->global_setting_data['ced_amazon_order_currency'] : '';
			$ced_amazon_currency_convert_rate = 1;

			if ( ! empty( $ced_woo_store_currency ) && ( '1' == $ced_woo_store_currency || 'on' == $ced_woo_store_currency ) ) {

				$ced_amz_conversion_type = ! empty( $this->global_setting_data['ced_amz_conversion_type'] ) ? $this->global_setting_data['ced_amz_conversion_type'] : '';

				if ( 'automatic' == $ced_amz_conversion_type ) { // automatic

					$woo_multi_currency_params             = get_option( 'woo_multi_currency_params', array() );
					$ced_amazon_currency_conversion_plugin = ! empty( $this->global_setting_data['ced_amazon_currency_conversion_plugin'] ) ? $this->global_setting_data['ced_amazon_currency_conversion_plugin'] : '';

					if ( 'Curcy' == $ced_amazon_currency_conversion_plugin && ! empty( $woo_multi_currency_params ) ) {

						$ced_woo_store_currency = isset( $woo_multi_currency_params['currency_default'] ) ? $woo_multi_currency_params['currency_default'] : $ced_woo_store_currency;
						$index_to_used          = '';

						if ( $amzCurrencyCode == $ced_woo_store_currency ) {
							$ced_amazon_currency_convert_rate = 1;
						} else {
							$index_to_used                    = array_search( $amzCurrencyCode, $woo_multi_currency_params['currency'] );
							$ced_amazon_currency_convert_rate = 1 / ( $woo_multi_currency_params['currency_rate'][ $index_to_used ] );
						}
					}
				}

				if ( 'manual' == $ced_amz_conversion_type ) { // manual
					$ced_amazon_currency_convert_rate = ! empty( $this->global_setting_data['ced_amazon_currency_convert_rate'] ) ? $this->global_setting_data['ced_amazon_currency_convert_rate'] : 1;

				}

			}

			$this->logger->info( wc_print_r( 'currency_convert_rate is: ' . $ced_amazon_currency_convert_rate , true ), $this->context );
			return $ced_amazon_currency_convert_rate;

		}


		public function ced_amz_get_id_by_sku( $sku ) {

			$pro_ids = apply_filters( 'ced_amz_custom_get_ids_by_sku', null, $sku );
			if ( ! empty( $pro_ids ) ) {
				return $pro_ids;
			}

			$metaKey  = '_sku';
			$metaKey2 = 'item_sku';
			$args     = array(
				'post_type'      => array( 'product', 'product_variation' ),
				'posts_per_page' => -1,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => $metaKey,
						'value'   => trim( $sku ),
						'compare' => '=',
					),
					array(
						'key'     => $metaKey2,
						'value'   => trim( $sku ),
						'compare' => '=',
					),
				),
				'fields'         => 'ids',
			);
			$ID       = get_posts( $args );

			return $ID;

		}



		public function ced_amazon_manage_order_status( $order, $counter = 0, $amazon_order_status = '' ) {

			if ( !empty($order) ) {

				// Order status
				if ( 'Unshipped' == $amazon_order_status ) {
					$current_order_status = 'processing';
					$amazon_order_status  = 'Created';
				} elseif ( 'Shipped' == $amazon_order_status ) {
					$current_order_status = 'completed';
					$amazon_order_status  = 'Shipped';
				} else {
					$current_order_status = 'processing';
					$amazon_order_status  = 'Created';
				}

				if ( $order->get_status() != $current_order_status ) {
					$order->update_status( $current_order_status );
				}

				
				$order->update_meta_data( '_amazon_umb_order_status', $amazon_order_status );
				$order->save();

			}


		}



		/**
		 * Create order into woo.
		 *
		 * @name create_order()
		 * @since 1.0.0
		 * @param array() $address
		 * @param array() $OrderItemsInfo
		 * @since string $frameworkName
		 * @param array() $orderMeta
		 * @link  http://www.cedcommerce.com/
		 */
		public function create_order( $address = array(), $OrderItemsInfo = array(), $frameworkName = 'UMB', $orderMeta = array(), $mplocation = '', $seller_id = '', $cronVal = 'Cron is empty' ) {

			set_time_limit( 600 );
			wp_raise_memory_limit( -1 );

			global $ced_umb_helper_amaz;

			$order_id           = '';
			$order_created      = false;
			$tax_amount         = 0;
			$total_items_amount = 0;

			if ( ! class_exists( 'WC_Tax' ) ) {
				include_once WC_ABSPATH . 'includes/class-wc-tax.php';
			}

			if ( count( $OrderItemsInfo ) ) {

				$OrderNumber = isset( $OrderItemsInfo['OrderNumber'] ) ? $OrderItemsInfo['OrderNumber'] : 0;
				$order_id    = $this->is_umb_order_exists( $OrderNumber );
				
				if ( $order_id ) {
					$order = wc_get_order( $order_id );
					
					$order->update_meta_data( 'ced_amazon_order_countory_code', $mplocation );
					$order->update_meta_data( 'ced_amazon_order_seller_id', $seller_id );

					$order->save();
					return $order_id;

				}

				if ( count( $OrderItemsInfo ) ) {
					$ItemsArray = isset( $OrderItemsInfo['ItemsArray'] ) ? $OrderItemsInfo['ItemsArray'] : array();

					if ( is_array( $ItemsArray ) ) {
						$productIdsToUpdate = array();

						foreach ( $ItemsArray as $ItemInfo ) {
							$ProID = isset( $ItemInfo['ID'] ) ? intval( $ItemInfo['ID'] ) : 0;
							$Sku   = isset( $ItemInfo['Sku'] ) ? $ItemInfo['Sku'] : '';

							$params = array( '_sku' => $Sku );
							if ( ! $ProID ) {
								$ProID = $ced_umb_helper_amaz->umb_get_product_by( $params );
							}
							if ( ! $ProID ) {
								$ProID = $Sku;
							}

							$Qty       = isset( $ItemInfo['OrderedQty'] ) ? intval( $ItemInfo['OrderedQty'] ) : 0;
							$UnitPrice = isset( $ItemInfo['UnitPrice'] ) ? floatval( $ItemInfo['UnitPrice'] ) : 0;
							$UnitTax   = isset( $ItemInfo['UnitTax'] ) ? floatval( $ItemInfo['UnitTax'] ) : 0;

							$_product = wc_get_product( $ProID );

							$productIdsToUpdate[] = $ProID;
							if ( is_wp_error( $_product ) ) {
								continue;
							} elseif ( is_null( $_product ) ) {
								continue;
							} elseif ( ! $_product ) {
								continue;
							} else {
								if ( ! $order_created ) {
									$order_data = array(
										/**
										 * Function to get woocommerce order by status
										 *
										 * @param 'function'
										 * @param  integer 'limit'
										 * @return 'count'
										 * @since  1.0.0
										 */
										'status'        => apply_filters( 'woocommerce_default_order_status', 'pending' ),
										'customer_note' => __( 'Order from ', 'amazon-for-woocommerce' ) . $frameworkName,
										'created_via'   => $frameworkName,
									);

									/* ORDER CREATED IN WOOCOMMERCE */
									$order = wc_create_order( $order_data );

									if ( is_plugin_active( 'woocommerce-sequential-order-numbers-pro/woocommerce-sequential-order-numbers-pro.php' ) ) {
										if ( function_exists( 'wc_seq_order_number_pro' ) && method_exists( 'wc_seq_order_number_pro', 'set_sequential_order_number' ) ) {
											wc_seq_order_number_pro()->set_sequential_order_number( $order->get_id(), get_post( $order->get_id() ) );
										}
									}

									/* ORDER CREATED IN WOOCOMMERCE */
									if ( is_wp_error( $order ) ) {
										$this->logger->info( wc_print_r( $cronVal . " There is an error while create order. \n\n\n", true), $this->context );
										continue;
									} elseif ( false === $order ) {
										continue;
									} else {
										$order_id                      = $order->get_id();
										$order_created                 = true;
										$this->marketplace_order_ids[] = $OrderNumber;
									}

								}

								if ( $UnitTax > 0 ) {
									$tax_amount         += $UnitTax;
									$total_items_amount += ( $UnitPrice * $Qty );
								}

								
								if ( $this->woo_tax ) {

									$product_id         = $_product->get_id();
									$quantity           = $Qty;
									$prices_include_tax = get_option( 'woocommerce_prices_include_tax' );

									$this->logger->info( wc_print_r( $prices_include_tax, true ), $this->context );

									$tax_class = $_product->get_tax_class();
									if (empty($tax_class)) {
										$tax_class = 'standard'; // Default to 'standard' tax class if none is set
									}

									$standard_tax_rates = \WC_Tax::get_rates_for_tax_class( $tax_class );
									$standard_tax_rates = json_decode( json_encode( $standard_tax_rates ), true );

									$this->logger->info( wc_print_r( $standard_tax_rates, true ), $this->context );

									$tax_rates = array();
									$tax_flag  = false;
									foreach ( $standard_tax_rates as $key => $std_tax_rate ) {
										if (  $std_tax_rate['tax_rate_country'] == $address['billing']['country']  || $std_tax_rate['tax_rate_country'] == $address['shipping']['country'] ) {
											$tax_rates       = $std_tax_rate;
											$this->tax_rates = $tax_rates;
											$tax_flag        = true;
										}
										
									}

									if ( ! $tax_flag ) {
										foreach ( $standard_tax_rates as $key => $std_tax_rate ) {
											if (  '' == $std_tax_rate['tax_rate_country'] ) {
												$tax_rates       = $std_tax_rate;
												$this->tax_rates = $tax_rates;
												$tax_flag        = true;
												break;
											}
											
										}
									}

									$this->logger->info( wc_print_r( $tax_rates, true ), $this->context );

									// Calculate the subtotal and total based on tax settings
									if ( 'yes' == $prices_include_tax ) {
										
										// Prices include tax
										$UnitPrice = $UnitPrice / ( $tax_rates['tax_rate'] / 100 + 1 );
										// $subtotal  = $UnitPrice *  ( 1 - ($tax_rates['tax_rate'] / 100 ) ) * $quantity;
										$subtotal = $UnitPrice * $quantity;
										$total    = $UnitPrice * $quantity;

									} else {

										// Prices exclude tax
										$UnitPrice = $UnitPrice / ( 1 + ( $tax_rates['tax_rate'] / 100 ) ) ;
										$subtotal  = $UnitPrice * ( 1 + ( $tax_rates['tax_rate'] / 100 ) ) * $quantity;
										$total     = $UnitPrice * ( 1 + ( $tax_rates['tax_rate'] / 100 ) ) * $quantity;

									}

									// Add the product to the order
									$item_id = $order->add_product(
										$_product,
										$quantity,
										array(
											'subtotal' => $subtotal,
											'total'    => $total,
										)
									);
									$order->save();
									
								} else {

									$item_id = $order->add_product(
										$_product,
										$Qty,
										array(
											'subtotal' => $Qty * $UnitPrice,
											'total'    => $Qty * $UnitPrice,
										)
									);
									$order->save();

								}

								// Add line item tax if woo tax is false
								if ( ! empty( $item_id ) && 0 < $UnitTax && !$this->woo_tax ) {

									$tax_arr_data             = array();
									$tax_arr_data['total']    = array( '1' => $UnitTax );
									$tax_arr_data['subtotal'] = array( '1' => $UnitTax );
									wc_update_order_item_meta( $item_id, '_line_subtotal_tax', $UnitTax );
									wc_update_order_item_meta( $item_id, '_line_tax', $UnitTax );
									wc_update_order_item_meta( $item_id, '_line_tax_data', $tax_arr_data );

								}

								$BillingAddress = isset( $address['billing'] ) ? $address['billing'] : '';
								if ( is_array( $BillingAddress ) ) {
									$order->set_address( $BillingAddress, 'billing' );
								}

								$ShippingAddress = isset( $address['shipping'] ) ? $address['shipping'] : '';
								if ( is_array( $ShippingAddress ) ) {
									$order->set_address( $ShippingAddress, 'shipping' );
								}

								$order->calculate_totals( $this->woo_tax );

								// Reduce quantity from product level amazon field
								$stock_quantity = get_post_meta( $ProID, 'quantity', true );
								if ( isset( $stock_quantity ) && is_numeric( $stock_quantity ) ) {
									if ( $stock_quantity > 0 ) {
										$update_stock_quantity = $stock_quantity - $Qty;

										$order->update_meta_data( 'quantity', $update_stock_quantity );
										$order->save();
										
									}
								}
							}
						}

					}

					if ( ! $order_created ) {
						$this->logger->info( $cronVal . " Order not created, please check!! \n\n\n", $this->context );
						return false;
					}

					if ( isset( $order ) && ! empty( $order ) ) {
						$order->save();
						wc_reduce_stock_levels( $order_id );
					}

					$order->update_meta_data( '_umb_order_id', $OrderNumber );
					$order->save();

					$inventory_sync_frequency = get_option( 'ced_amazon_inventory_scheduler_job_' . $seller_id );

					$ShippingAmount          = isset( $OrderItemsInfo['ShippingAmount'] ) ? $OrderItemsInfo['ShippingAmount'] : 0;
					$ShippingTax             = isset( $OrderItemsInfo['ShippingTax'] ) ? $OrderItemsInfo['ShippingTax'] : 0;
					$PromotionDiscountAmount = isset( $OrderItemsInfo['DiscountAmount'] ) ? $OrderItemsInfo['DiscountAmount'] : 0;
					$ShippingDiscountAmount  = isset( $OrderItemsInfo['ShippingDiscount'] ) ? $OrderItemsInfo['ShippingDiscount'] : 0;
					$ShipService             = isset( $OrderItemsInfo['ShipService'] ) ? $OrderItemsInfo['ShipService'] : '';

					// Add amazon tax if woo tax is false
					if ( $tax_amount > 0 && ! $this->woo_tax ) {

						$tax_percent = ( $tax_amount * 100 ) / $total_items_amount;
						$tax_percent = round( $tax_percent, 2 );

						$tax_item = new WC_Order_Item_Tax();
						$tax_item->set_label( 'Tax' );
						$tax_item->set_name( 'AMAZON-TAX' );
						$tax_item->set_rate_id( 1 );
						$tax_item->set_rate_percent( $tax_percent );
						$tax_item->set_shipping_tax_total( $ShippingTax );
						$tax_item->set_tax_total( $tax_amount );
						$order->add_item( $tax_item );

						$total_tax_amount = $tax_amount + $ShippingTax;
						$order->set_cart_tax( $total_tax_amount );

					} else {

						// $prices_include_tax = get_option( 'woocommerce_prices_include_tax' );
						// if ( 'yes' == $prices_include_tax ) {
						// 	$ShippingAmount = $ShippingAmount / ( 1 + ( $this->tax_rates['tax_rate'] / 100 ) );
						// } else {
							$ShippingAmount = $ShippingAmount;
						// }

					}

					// Add shipping
					$shipping_item = new WC_Order_Item_Shipping();
					$shipping_item->set_method_title( $ShipService );
					$shipping_item->set_method_id( 'amazon-shipping' );
					$shipping_item->set_total( $ShippingAmount );

					// Add Amazon shipping tax if woo tax is false
					if ( ! $this->woo_tax ) {
						$ship_tax_arr_data          = array();
						$ship_tax_arr_data['total'] = array( '1' => $ShippingTax );
						$shipping_item->set_taxes( $ship_tax_arr_data );
					}

					$order->add_item( $shipping_item );

					if ( 0 < $ShippingDiscountAmount ) {

						$shipping_discount_item = new WC_Order_Item_Fee();
						$shipping_discount_item->set_name( 'Shipping Discount' );
						$shipping_discount_fee = 0 - $ShippingDiscountAmount;
						$shipping_discount_item->set_total( $shipping_discount_fee );
						$order->add_item( $shipping_discount_item );

					}


					if ( 0 < $PromotionDiscountAmount ) {

						$discount_item = new WC_Order_Item_Fee();
						$discount_item->set_name( 'Promotion Discount' );
						$fee_amount = 0 - $PromotionDiscountAmount;
						$discount_item->set_total( $fee_amount );
						$order->add_item( $discount_item );

					}

					$overall_discount = $ShippingDiscountAmount + $PromotionDiscountAmount;
					if ( 0 < $overall_discount ) {
						update_post_meta( $order_id, '_cart_discount', $overall_discount );
					}

					$order->calculate_totals( $this->woo_tax );
					// $order->add_order_note( 'A discount of 5 was applied to the order.' );
					$order->save();

					$this->ced_amazon_manage_order_currency( $orderMeta, $order );
					
					$order->update_meta_data( '_umb_order_id', $OrderNumber );
					$order->update_meta_data( '_is_amazon_order', 1 );
					// $order->update_meta_data(  '_amazon_umb_order_status', 1 );
					$order->update_meta_data( '_umb_marketplace', $frameworkName );
					$order->update_meta_data( 'ced_amazon_order_countory_code', $mplocation );
					$order->update_meta_data( 'ced_amazon_order_seller_id', $seller_id );

					$order->save();

					if ( count( $orderMeta ) ) {
						foreach ( $orderMeta as $oKey => $oValue ) {
							$order->update_meta_data( $oKey, $oValue );
							$order->save();
						}
					}

					$order->save();
					$log_message = $cronVal . " Amazon order $OrderNumber has been created with woo order id $order_id. \n\n\n";
					$this->logger->info( wc_print_r( $log_message, true ), $this->context );

				}

				return $order_id;
			}
			return false;
		}

		/**
		 * Check if order already imported or not.
		 *
		 * @name is_umb_order_exists()
		 * @since 1.0.0
		 * @link  http://www.cedcommerce.com/
		 * @return integer
		 */
		public function is_umb_order_exists( $order_number = 0 ) {
			global $wpdb;
			if ( $order_number ) {
				
				$args = array(
					'return'       => 'ids',
					'meta_key'     => '_umb_order_id',
					'meta_value'   => $order_number,
					'meta_compare' => '=',
				);

				$order_id = wc_get_orders( $args );
				if ( isset( $order_id[0] ) ) {
					return $order_id[0];
				}
				
			}

			return false;
		}


		public function ced_amazon_manage_order_currency( $orderMeta, $order ) {

			$amzCurrencyCode          = isset( $orderMeta['amzCurrencyCode'] ) ? $orderMeta['amzCurrencyCode'] : ''; // euro
			$ced_amazon_currency_code = get_option( 'ced_amazon_currency_code', '' ); // USD

			if ( ! empty( $amzCurrencyCode ) && empty( $ced_amazon_currency_code ) ) {
				update_option( 'ced_amazon_currency_code', $amzCurrencyCode );
			}

			$ced_woo_store_currency = ! empty( $this->global_setting_data['ced_amazon_order_currency'] ) ? $this->global_setting_data['ced_amazon_order_currency'] : '';

			if ( ! empty( $ced_woo_store_currency ) && ( '1' == $ced_woo_store_currency || 'on' == $ced_woo_store_currency ) ) {

				$ced_amz_conversion_type = ! empty( $this->global_setting_data['ced_amz_conversion_type'] ) ? $this->global_setting_data['ced_amz_conversion_type'] : '';

				if ( 'automatic' == $ced_amz_conversion_type ) { // automatic

					$woo_multi_currency_params             = get_option( 'woo_multi_currency_params', array() );
					$ced_amazon_currency_conversion_plugin = ! empty( $this->global_setting_data['ced_amazon_currency_conversion_plugin'] ) ? $this->global_setting_data['ced_amazon_currency_conversion_plugin'] : '';

					if ( 'Curcy' == $ced_amazon_currency_conversion_plugin && ! empty( $woo_multi_currency_params ) ) {
						$ced_woo_store_currency = isset( $woo_multi_currency_params['currency_default'] ) ? $woo_multi_currency_params['currency_default'] : $ced_woo_store_currency;
						$order->set_currency( $ced_woo_store_currency );
					}
				}

				if ( 'manual' == $ced_amz_conversion_type ) { // manual
					$order->set_currency( get_option( 'woocommerce_currency' ) );
				}
			} else {
				// amazon currency
				$order->set_currency( $amzCurrencyCode );
			}

		}
	}

endif;
