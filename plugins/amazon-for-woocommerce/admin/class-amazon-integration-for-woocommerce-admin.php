<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       care@cedcommerce.com
 * @since      1.0.0
 *
 * @package    Amazon_Integration_For_Woocommerce
 * @subpackage Amazon_Integration_For_Woocommerce/admin
 */

use Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore;
use Automattic\WooCommerce\Utilities\OrderUtil as CedAmazonHOPS;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Amazon_Integration_For_Woocommerce
 * @subpackage Amazon_Integration_For_Woocommerce/admin
 */
class Amazon_Integration_For_Woocommerce_Admin {


	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0

	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;


	/**
	 * The HPOS instance.
	 *
	 * @since    1.0.0
	 * @var      string    $create_amz_order_hops    The HPOS instane.
	 */
	public $create_amz_order_hops = false;


	/**
	 * The current instance of excel sheet.
	 *
	 * @since    1.0.0
	 * @var      string    $reader    The current instance of excel sheet..
	 */
	public $reader;


	/**
	 * The instance of order manage file.
	 *
	 * @since    1.0.0

	 * @var      string    $order_manager    The instance of order manager file.
	 */
	public $order_manager;

	/**
	 * The instance of feed manager file.
	 *
	 * @since    1.0.0

	 * @var      string    $amz_com_opts_mng    The instance of common manager file.
	 */
	public $amz_com_opts_mng;

	/**
	 * The instance of feed manager file.
	 *
	 * @since    1.0.0

	 * @var      string    $amazon_feed_manager    The instance of feed manager file.
	 */
	public $amazon_feed_manager;


	/**
	 * The instance of price feed manager file.
	 *
	 * @since    1.0.0

	 * @var      string    $amazon_price_manager    The instance of price manager file.
	 */
	public $amazon_price_manager;


	/**
	 * The instance of inventory feed manager file.
	 *
	 * @since    1.0.0

	 * @var      string    $amazon_inventory_manager    The instance of inventory manager file.
	 */
	public $amazon_inventory_manager;


	/**
	 * The instance of curl request file.
	 *
	 * @since    1.0.0

	 * @var      string    $amzonCurlRequestInstance    The instance of curl request file.
	 */
	private $amzonCurlRequestInstance;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct() {

		ini_set( 'max_input_vars', 3000 );

		$this->plugin_name = 'amazon-for-woocommerce';
		$this->version     = '1.0.0';

		add_action( 'ced_show_connected_accounts', array( $this, 'ced_show_connected_accounts' ) );
		add_action( 'ced_show_connected_accounts_details', array( $this, 'ced_show_connected_accounts_details' ) );
		
		add_filter( 'views_edit-shop_order', array( $this, 'ced_amazon_add_woo_order_views' ) );
		add_filter( 'parse_query', array( $this, 'ced_amazon_woo_admin_order_filter_query' ) );
		add_filter( 'views_woocommerce_page_wc-orders', array( $this, 'ced_amazon_add_woo_order_views' ) );
		
		add_action( 'woocommerce_product_set_stock', array($this, 'ced_amz_product_stock_updated'), 10, 1 );

		$this->load_admin_classes();
		$this->instantiate_admin_classes();

		add_action( 'add_meta_boxes', array( $this->order_manager, 'add_meta_boxes' ), 30 );
		add_action( 'ced_sales_channel_include_template', array( $this, 'ced_amazon_accounts_page' ) );

		/** Custom column in order section */
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'custom_shop_order_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'custom_orders_list_column_content' ), 10, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'custom_shop_order_column' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'custom_orders_list_column_content' ), 20, 2 );

		add_filter( 'woocommerce_order_number', array( $this, 'ced_amz_modify_woo_order_number' ), 20, 2 );

		add_action( 'admin_init', array( $this, 'ced_mbc_handle_form_post' ) );

		if ( file_exists( CED_AMAZON_DIRPATH . 'admin/saas/class-ced-pricing.php' ) && ! in_array('multichannel-by-cedcommerce/multichannel-by-cedcommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			include_once CED_AMAZON_DIRPATH . 'admin/saas/class-ced-pricing.php';
			new Ced_Pricing();
		}

		add_action( 'admin_footer', array( $this, 'ced_amazon_include_chat_widget' ), 23 );
		add_action( 'woocommerce_new_product', array( $this, 'ced_update_on_product_change' ), 10, 2 );
		add_action( 'woocommerce_update_product', array( $this,  'ced_update_on_product_change' ), 10, 2 );
		 
		
		// CRON scheduler functions
		$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', array() );
		if ( is_array( $saved_amazon_details ) && ! empty( $saved_amazon_details ) ) {
			foreach ( $saved_amazon_details as $sellerDataKey => $sellerDataValue ) {

				/** Catalog asin sync */
				add_action( 'ced_amazon_catalog_asin_sync_job_' . $sellerDataKey, array( $this, 'ced_amazon_cron_catalog_asin_sync' ), 10, 1 );

				/** Shipment tracking sync sync */
				add_action( 'ced_amazon_shipment_tracking_job_' . $sellerDataKey, array( $this, 'ced_amazon_shipment_method' ), 10, 1 );

				/** Create report sync */
				add_action( 'ced_amazon_create_report_sync_job_' . $sellerDataKey, array( $this, 'ced_amazon_create_report_sync' ), 10, 1 );
				
				/** Get report sync */
				add_action( 'ced_amazon_get_report_sync_job_' . $sellerDataKey, array( $this, 'ced_amazon_get_report_sync' ), 10, 1 );


			}
		}

		$actions_array     = get_option( 'ced_amz_mod_actions_array', array() );
		$ced_merchant_list = array_keys( $actions_array );
		
		if ( is_array( $ced_merchant_list ) && ! empty( $ced_merchant_list ) ) {
			foreach ( $ced_merchant_list as $merchant_id ) {

				add_action( 'ced_amazon_inventory_scheduler_job_' . $merchant_id, array( $this, 'ced_amazon_cron_inventory_sync' ), 10, 1 );
				add_action( 'ced_amazon_price_scheduler_job_' . $merchant_id, array( $this, 'ced_amazon_cron_price_sync' ), 10, 1 );
				add_action( 'ced_amazon_common_prc_inv_scheduler_job_' . $merchant_id, array( $this, 'ced_amazon_common_prc_inv_method' ), 10, 1 );
				add_action( 'ced_amazon_order_scheduler_job_' . $merchant_id, array( $this, 'ced_amazon_cron_order_sync' ), 10, 1 );
				
			}
		}

		add_action( 'woocommerce_variation_set_stock', array( $this, 'ced_amazon_variations_stock_update' ) );
		add_action( 'pmxi_saved_post', array( $this, 'ced_update_products_via_wpimport' ), 10, 3 );
		add_action( 'woocommerce_product_import_inserted_product_object', array( $this, 'ced_update_products_via_wooimport' ), 10, 2);


	}


	public function ced_amazon_variations_stock_update( $variation ) {
	   
		$logger  = wc_get_logger();
		$context = array( 'source' => 'ced_variation_update' );
		
		// Check if variation is a valid product object
		if ( ! is_object( $variation ) || ! method_exists( $variation, 'get_parent_id' ) ) {
			$logger->error( 'Invalid variation object passed.', $context );
			return;
		}

		$parent_id = $variation->get_parent_id();
	   
		// Validate parent ID
		if ( ! $parent_id || $parent_id <= 0 ) {
			$logger->error( 'Variation has no valid parent ID.', $context );
			return;
		}
		$product = wc_get_product( $parent_id );
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			$logger->error( "Failed to load parent product with ID: {$parent_id}", $context );
			return;
		}
		
		$this->ced_amz_product_stock_updated( $product );

	}

	public function ced_update_products_via_wpimport( $post_id, $xml_node, $update) {

		if (get_post_type($post_id) === 'product') {
			if ($update) {
				$this->ced_amz_product_stock_updated( $xml_node );
				$this->ced_update_on_product_change( $post_id, $update);
				error_log("Product ID {$post_id} was UPDATED.");
			} else {
				error_log("Product ID {$post_id} was CREATED.");
			}
			
		}

	}

	public function ced_update_products_via_wooimport( $product, $data ) {

		$logger  = wc_get_logger();
		$context = array( 'source' => 'ced_update_products' );

		$product_id = $product->get_id();
		
		$logger->info( wc_print_r( 'current product id is: ' . $product_id , true ), $context );
		$this->ced_amz_product_stock_updated( $product );
		$this->ced_update_on_product_change( $product_id, 1 );
		error_log("Product ID {$product_id} was UPDATED.");
		
	
	}

	public function ced_update_on_product_change( $product_id, $new ) {

		$logger  = wc_get_logger();
		$context = array( 'source' => 'ced_update_products' );
				
		$logger->info( wc_print_r( 'current product id is: ' . $product_id , true ), $context );

		/** Get the product object */
		$product = wc_get_product($product_id);
		if ( !$product ) {
			return;
		}

		/** Check the product type */
		$product_type = $product->get_type();
		if ( !in_array( $product_type, ['simple', 'variable', 'variation'] ) ) {
			return;
		}

		$products_to_update   = get_option( 'ced_amz_price_updated_products', array() );
		$global_settings      = get_option( 'ced_amazon_global_settings' );
		$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', array() );

		if ( ! empty( $saved_amazon_details ) && is_array( $saved_amazon_details )  ) {

			foreach ( $saved_amazon_details as $seller_id => $seller_data ) {

				$products = isset( $products_to_update[ $seller_id ] ) ? $products_to_update[ $seller_id ] : array();

				$seller_global_settings = array();
				if ( isset( $global_settings[ $seller_id ] ) && ! empty( $global_settings[ $seller_id ] ) ) {
					$seller_global_settings = isset( $global_settings[ $seller_id ] ) ? $global_settings[ $seller_id ] : array();
				}

				/** By default we will send REGULAR price */
				$price_type = 'regular_price';
				if ( isset( $seller_global_settings['ced_amazon_product_price_type'] ) && 'sale_price' == $seller_global_settings['ced_amazon_product_price_type'] ) {
					$price_type = 'sale_price'; /** SALE price is send, only when the price type is sale price, in all other cases regular price is send */
				}

				if ( 'sale_price' == $price_type ) {
					/** Get SALE price of products */
					$current_price = $product->get_sale_price();
	
					/** Get REGULAR price of products, if SALE price is set on global level, but product doean't contains SALE price */
					if ( empty( $price_amz ) || '0' == $price_amz ) {
						$current_price = $product->get_regular_price();
					}
				} else { /** Get REGULAR price of products */
					$current_price = $product->get_regular_price();
				}

				$old_price = get_post_meta( $product_id, 'ced_amz_old_price', true );
				if ( $old_price !== $current_price ) {
					
					if ( !in_array( $product_id , $products ) ) {
						$products[] = $product_id;
					}
					
				}

				$products_to_update[ $seller_id ] = $products;

			}

			update_option( 'ced_amz_price_updated_products', $products_to_update );

		}



	}

	public function ced_amz_product_stock_updated( $product ) {

		$logger  = wc_get_logger();
		$context = array( 'source' => 'ced_update_products' );

		/** Get product details */
		$product_id = null;

		if ($product instanceof SimpleXMLElement) {
			$sku             = isset($product->sku) ? (string) $product->sku : '';
			$product_id_attr = isset($product->product_id) ? (int) $product->product_id : 0;

			$productDetails = wc_get_product( $product_id_attr );
			$productType    = $productDetails->type;
			
			if ( !in_array( $productType, array( 'simple', 'variable', 'variation' ) ) ) {
			   return;
			}

			// Prefer product_id if provided
			if ($product_id_attr > 0) {
				$product_id = $product_id_attr;
				$logger->info('Product ID from XML: ' . $product_id, $context);
			} elseif (!empty($sku)) {
				$product_id = wc_get_product_id_by_sku($sku);
				$logger->info('Product ID from SKU: ' . $product_id, $context);
			} else {
				$logger->warning('No product identifier (ID or SKU) found in XML node', $context);
			}
		} elseif (is_object($product) && method_exists($product, 'get_id')) {
			
			$product_id = $product->get_id();
			$logger->info('Product ID from object: ' . $product_id, $context);

			$productDetails = wc_get_product( $product_id );
			$productType    = $productDetails->get_type();
			
			if ( !in_array( $productType, array( 'simple', 'variable', 'variation' ) ) ) {
			   return;
			}

		} else {
			$logger->error('Invalid product format received', $context);
		}


		$products_to_update   = get_option( 'ced_amz_stock_updated_products', array() );
		$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', array() );
		$global_setting_data  = get_option( 'ced_amazon_global_settings', array() );

		$logger->info( wc_print_r( 'current product id is: ' . $product_id , true ), $context );

		if ( ! empty( $saved_amazon_details ) && is_array( $saved_amazon_details ) && function_exists( 'as_has_scheduled_action' ) ) {

			foreach ( $saved_amazon_details as $seller_id => $seller_data ) {

				$logger->info( wc_print_r( 'looping the products'  , true ), $context );

				if ( !isset( $products_to_update[$seller_id] ) ) {
					$products_to_update[$seller_id] = array();
				}

				$current_inventory_sync = isset( $global_setting_data[ $seller_id ] ) && isset( $global_setting_data[ $seller_id ]['ced_amazon_inventory_schedule_info'] ) ? $global_setting_data[ $seller_id ]['ced_amazon_inventory_schedule_info'] : '';
				if ( 'on' == $current_inventory_sync  ) {
					if ( !in_array( $product_id , $products_to_update[$seller_id] ) ) {
					   $products_to_update[$seller_id][] = $product_id;
					}
				}

			}

			$logger->info( wc_print_r( 'loop ended'  , true ), $context );
			update_option( 'ced_amz_stock_updated_products', $products_to_update );
			$logger->info( wc_print_r( 'option updated ' . $product_id , true ), $context );

		}

		return;

	}


	public function ced_amazon_include_chat_widget() {

		if ( isset( $_GET['page'] ) && 'sales_channel' == $_GET['page'] ) {

			// to check MBC email details
			$email_received = get_option( 'ced_mbc_email_received', '' );
			if ( 'yes' != $email_received ) {
				$this->load_email_pop_up();
			}

			?>
		   <script type="text/javascript" id="zsiqchat">var $zoho=$zoho || {};$zoho.salesiq = $zoho.salesiq || {widgetcode: "siqa8c5761519650fe047076203095c17a98d7328e7afd77313fcda6c78dfb88931", values:{},ready:function(){}};var d=document;s=d.createElement("script");s.type="text/javascript";s.id="zsiqscript";s.defer=true;s.src="https://salesiq.zohopublic.in/widget";t=d.getElementsByTagName("script")[0];t.parentNode.insertBefore(s,t);</script>
			<?php
		}
	}

	public function load_email_pop_up() {
		?>
	
		<form action="" method="post">
			<?php wp_nonce_field( 'user_register', 'user_register_submit' ); ?>
			<div class="ced-popup-form-wrapper" style="display:none;">
				<div class="ced-poup-form-content">
					<div id="ced-popup-form" class="ced-popup-overlay">
						<div class="ced-popup-wrap">
							<h2>Keep the Connection Strong</h2>
							<div class="ced-popup-content-wrapper">
								<p>Let's stay in touch so you can fully enjoy your <b>Multichannel for WooCommerce</b> plugin. Provide your email for expert assistance. We guarantee no marketing or spam—just genuine support for you!</p>
							</div>
							<div class="ced-popup-form-wrap">
								<div class="ced-form-wrapper-popup">
									<input type="email" id="email" name="mbc_email" placeholder="Email Address" required>
									<input type="submit" name="ced_mbc_submit_email" class="ced-popup-form-button" value="Submit">
								</div>
								<div class="ced-popup-fomr-steps">
									<span class="ced-popup-close">Remind me later</span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</form>
	
		<?php
	}
	
	public function ced_mbc_handle_form_post() {
	
		if ( isset( $_POST['ced_mbc_submit_email'] ) ) {
	
			if ( ! isset( $_POST['user_register_submit'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['user_register_submit'] ) ), 'user_register' ) ) {
				return;
			}
	
			$query_args['domain'] = site_url();
			$query_args['mode']   = get_option( 'set_checkout_mode', '' );
			$query_args['email']  = isset( $_POST['mbc_email'] ) ? sanitize_text_field( $_POST['mbc_email'] ) : '';
	
			$url = 'https://api.cedcommerce.com/pricing/getaccesstoken?' . http_build_query( $query_args ) ;
	
			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 30,
				)
			);
	
			// Check for errors
			if ( is_wp_error( $response ) ) {
				return false;
			}
	
				// Get the body of the response
			$body     = wp_remote_retrieve_body( $response );
			$response = json_decode( $body, 1 );
	
			if ( isset( $response['access_token'] ) ) {
				$subscription_info          = get_option( 'ced_mcfw_subscription_details', array() );
				$subscription_info['token'] = $response['access_token'];
				update_option( 'ced_mcfw_subscription_details', $subscription_info );
				update_option( 'ced_mbc_email_received', 'yes' );
			}
		}
	}


	public function ced_amz_check_schedules() {

		$is_authorised     = get_option('ced_amazon_user_created_and_authorised', '' );
		$subscription_info = get_option( 'ced_mcfw_subscription_details', array() );

		if ( 'yes' !== $is_authorised || !isset( $subscription_info['token'] ) ) {
			ced_remote_validator_access_token();
		}

		$merchant_ids         = array();
		$global_setting_data  = get_option( 'ced_amazon_global_settings', array() );
		$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', array() );
		
		if ( !empty( $saved_amazon_details ) ) {

			foreach ( $saved_amazon_details as $shop_data ) { 
				$mer_id = $shop_data['merchant_id'] ?? '';
				if ( !empty( $mer_id ) ) {
				   $merchant_ids[ $mer_id ] = $shop_data['marketplace_region'];
				} 
			}

		} 

		$merchant_ids = array_filter( array_unique( $merchant_ids ) );
		if ( empty( $merchant_ids ) ) {
			return;
		}
		
		// used to hold flags for initially price and inventory sync ( country wise ).
		$ced_amz_all_prc_inv_sync = get_option( 'ced_amz_all_prc_inv_sync', array() );

		if ( ! empty( $saved_amazon_details ) && is_array( $saved_amazon_details ) && function_exists( 'as_has_scheduled_action' ) ) {
			
			// used to store marketplaced ids, amazon region and scheduler type wise.
			$actions_array = get_option( 'ced_amz_mod_actions_array', array() );
			if ( ! empty( $merchant_ids ) ) {

				foreach ( $merchant_ids as $mer_id => $rg ) {
					$actions_array[ $mer_id ] = array( 

						'region'  => $rg,
						'common' => array( ),
						'price'  => array( ),
						'inventory' => array( ),
						'order'     => array( )
						
					);

				}
				
			}

			/**  Update the actions_array starts */
			foreach ( $saved_amazon_details as $seller_id => $seller_data ) {
				
				$mp_location_array = explode( '|', $seller_id );
				$mp_location       = $mp_location_array[0];
				$merchant_id       = $mp_location_array[1];

				if ( !isset( $ced_amz_all_prc_inv_sync[ $seller_id ] ) ) {
					$ced_amz_all_prc_inv_sync[ $seller_id ] = array();
				}

				/** To set flag for initially price sync  */
				if ( !isset( $ced_amz_all_prc_inv_sync[ $seller_id ]['price'] ) ) {
					$ced_amz_all_prc_inv_sync[ $seller_id ]['price'] = 0;
				}

				/** To set flag for initially price sync  */
				if ( !isset( $ced_amz_all_prc_inv_sync[ $seller_id ]['inventory'] ) ) {
					$ced_amz_all_prc_inv_sync[ $seller_id ]['inventory'] = 0;
				}

				$seller_args = array( array( 'seller_id' => $seller_id ) );

				/** To check and set the CATALOG ASIN SYNC SCHEDULER  */
				$current_asin_sync = isset( $global_setting_data[ $seller_id ] ) && isset( $global_setting_data[ $seller_id ]['ced_amazon_catalog_asin_sync'] ) ? $global_setting_data[ $seller_id ]['ced_amazon_catalog_asin_sync'] : '';
				if ( 'on' == $current_asin_sync && ! as_has_scheduled_action( 'ced_amazon_catalog_asin_sync_job_' . $seller_id ) ) {
					as_schedule_recurring_action( time(), 720, 'ced_amazon_catalog_asin_sync_job_' . $seller_id, $seller_args );
				} elseif ( 'on' !== $current_asin_sync && as_has_scheduled_action( 'ced_amazon_catalog_asin_sync_job_' . $seller_id ) ) {
					as_unschedule_all_actions( 'ced_amazon_catalog_asin_sync_job_' . $seller_id, $seller_args );
				}

				/** Create report action */
				$current_exist_product_sync = isset( $global_setting_data[ $seller_id ] ) && isset( $global_setting_data[ $seller_id ]['ced_amazon_existing_products_sync'] ) ? $global_setting_data[ $seller_id ]['ced_amazon_existing_products_sync'] : '';
				if ( 'on' == $current_exist_product_sync && ! as_has_scheduled_action( 'ced_amazon_create_report_sync_job_' . $seller_id ) ) {
					as_schedule_recurring_action( time(), 86400, 'ced_amazon_create_report_sync_job_' . $seller_id, $seller_args );
				} elseif ( 'on' !== $current_exist_product_sync && as_has_scheduled_action( 'ced_amazon_create_report_sync_job_' . $seller_id ) ) {
					as_unschedule_all_actions( 'ced_amazon_create_report_sync_job_' . $seller_id, $seller_args );
				}


				/** Get report action */
				$current_exist_product_sync = isset( $global_setting_data[ $seller_id ] ) && isset( $global_setting_data[ $seller_id ]['ced_amazon_existing_products_sync'] ) ? $global_setting_data[ $seller_id ]['ced_amazon_existing_products_sync'] : '';
				if ( 'on' == $current_exist_product_sync && ! as_has_scheduled_action( 'ced_amazon_get_report_sync_job_' . $seller_id ) ) {
					as_schedule_recurring_action( time(), 3600, 'ced_amazon_get_report_sync_job_' . $seller_id, $seller_args );
				} elseif ( 'on' !== $current_exist_product_sync && as_has_scheduled_action( 'ced_amazon_get_report_sync_job_' . $seller_id ) ) {
					as_unschedule_all_actions( 'ced_amazon_get_report_sync_job_' . $seller_id, $seller_args );
				}

				/** Shipment action */
				$current_shipment_sync = isset( $global_setting_data[ $seller_id ] ) && isset( $global_setting_data[ $seller_id ]['ced_amazon_shipment_tracking'] ) ? $global_setting_data[ $seller_id ]['ced_amazon_shipment_tracking'] : '';
				if ( 'on' == $current_shipment_sync && ! as_has_scheduled_action( 'ced_amazon_shipment_tracking_job_' . $seller_id ) ) {
					as_schedule_recurring_action( time(), 480, 'ced_amazon_shipment_tracking_job_' . $seller_id, $seller_args );
				} elseif ( 'on' !== $current_shipment_sync && as_has_scheduled_action( 'ced_amazon_shipment_tracking_job_' . $seller_id ) ) {
					as_unschedule_all_actions( 'ced_amazon_shipment_tracking_job_' . $seller_id, $seller_args );
				}

				/** Turn off the schedulers with old arguments */
				$old_seller_args = array( $seller_id );
				if ( as_has_scheduled_action( 'ced_amazon_order_scheduler_job_' . $seller_id ) ) {
					as_unschedule_all_actions( 'ced_amazon_order_scheduler_job_' . $seller_id, $old_seller_args );
				}
				if ( as_has_scheduled_action( 'ced_amazon_existing_products_sync_job_' . $seller_id ) ) {
					as_unschedule_all_actions( 'ced_amazon_existing_products_sync_job_' . $seller_id, $old_seller_args );
				}
				if ( as_has_scheduled_action( 'ced_amazon_catalog_asin_sync_job_' . $seller_id ) ) {
					as_unschedule_all_actions( 'ced_amazon_catalog_asin_sync_job_' . $seller_id, $old_seller_args );
				}

				if ( as_has_scheduled_action( 'ced_amazon_price_scheduler_job_' . $seller_id ) ) {
					as_unschedule_all_actions( 'ced_amazon_price_scheduler_job_' . $seller_id, $old_seller_args );
				}
				if ( as_has_scheduled_action( 'ced_amazon_inventory_scheduler_job_' . $seller_id ) ) {
					as_unschedule_all_actions( 'ced_amazon_inventory_scheduler_job_' . $seller_id, $old_seller_args );
				}
				if ( as_has_scheduled_action( 'ced_amazon_shipment_tracking_job_' . $seller_id ) ) {
					as_unschedule_all_actions( 'ced_amazon_shipment_tracking_job_' . $seller_id, $old_seller_args );
				}


				/** Turn off the seller-wise schedulers */
				$seller_args = array( array( 'seller_id' => $seller_id ) );
				if ( as_has_scheduled_action( 'ced_amazon_inventory_scheduler_job_' . $seller_id ) ) {
					as_unschedule_all_actions( 'ced_amazon_inventory_scheduler_job_' . $seller_id, $old_seller_args );
				}


				$current_mp_array = ced_amz_marketplaceid_region_mapping( $mp_location );
				$current_region   = strtolower( $current_mp_array['region_value'] );
		
				// if( !isset( $actions_array[ $current_region ] ) ){
				// 	$actions_array[ $current_region ][] = $current_mp_array['marketplace_id'];
				// }

				/** Code to ON/OFF region based schedulers */
				$current_price_sync     = isset( $global_setting_data[ $seller_id ] ) && isset( $global_setting_data[ $seller_id ]['ced_amazon_price_schedule_info'] ) ? $global_setting_data[ $seller_id ]['ced_amazon_price_schedule_info'] : '';
				$current_inventory_sync = isset( $global_setting_data[ $seller_id ] ) && isset( $global_setting_data[ $seller_id ]['ced_amazon_inventory_schedule_info'] ) ? $global_setting_data[ $seller_id ]['ced_amazon_inventory_schedule_info'] : '';
				$current_order_sync     = isset( $global_setting_data[ $seller_id ] ) && isset( $global_setting_data[ $seller_id ]['ced_amazon_order_schedule_info'] ) ? $global_setting_data[ $seller_id ]['ced_amazon_order_schedule_info'] : '';
				
				// To check and set the PRICE and INVENTORY COMMON SCHEDULER
				$regions_common    = $actions_array[ $merchant_id ]['common'] ?? array();
				$regions_inventory = $actions_array[ $merchant_id ]['inventory'] ?? array();
				$regions_price     = $actions_array[ $merchant_id ]['price'] ?? array();

				if ( 'on' == $current_price_sync && 'on' == $current_inventory_sync ) {
					
					$common_price_inc_scheduler = true;
					
					if ( !in_array( $current_mp_array['marketplace_id'], $regions_common ) ) {
						$actions_array[ $merchant_id ]['common'][] = $current_mp_array['marketplace_id'];

						// unset current marketplace id from price index if common scheduler is set.
						$key = array_search( $current_mp_array['marketplace_id'] , $regions_price );
						unset( $actions_array[ $merchant_id ]['price'][$key] );

						// unset current marketplace id from inventory index if common scheduler is set.
						$key = array_search( $current_mp_array['marketplace_id'] , $regions_inventory );
						unset( $actions_array[ $merchant_id ]['inventory'][$key] );

					}
					
				} elseif (  'on' !== $current_price_sync || 'on' !== $current_inventory_sync ) {

					// unset current marketplace id from common index if common scheduler is not set anymore.
					if ( in_array( $current_mp_array['marketplace_id'], $regions_common ) ) {
						$key = array_search( $current_mp_array['marketplace_id'] , $regions_common );
						unset( $actions_array[ $merchant_id ]['common'][$key] );
					}
				}
				
				// To check and set the INVENTORY SCHEDULER
				if ( 'on' !== $current_price_sync  && 'on' == $current_inventory_sync  ) {
					$common_price_inc_scheduler = false;

					if ( !in_array( $current_mp_array['marketplace_id'], $regions_inventory ) ) {
						$actions_array[ $merchant_id ]['inventory'][] = $current_mp_array['marketplace_id'];
					}
					
				} elseif ( 'on' !== $current_inventory_sync  ) {

					// unset current marketplace id from inventory index if inventory scheduler is not set anymore.
					if ( in_array( $current_mp_array['marketplace_id'], $regions_inventory ) ) {
						$key = array_search( $current_mp_array['marketplace_id'] , $regions_inventory );
						unset( $actions_array[ $merchant_id ]['inventory'][$key] );
					}
				}
				
				
				// To check and set the PRICE SCHEDULER
				if ( 'on' !== $current_inventory_sync && 'on' == $current_price_sync ) {
					$common_price_inc_scheduler = false;
					if ( !in_array( $current_mp_array['marketplace_id'], $regions_price ) ) {
						$actions_array[ $merchant_id ]['price'][] = $current_mp_array['marketplace_id'];
					}
					
				} elseif ( 'on' !== $current_price_sync  ) {

					// unset current marketplace id from price index if price scheduler is not set anymore.
					if ( in_array( $current_mp_array['marketplace_id'], $regions_price ) ) {
						$key = array_search( $current_mp_array['marketplace_id'] , $regions_price );
						unset( $actions_array[ $merchant_id ]['price'][$key] );
					}
				}

				// To check and set the ORDER SCHEDULER
				$regions_orders = $actions_array[ $merchant_id ]['order'] ?? array();
				if ( 'on' == $current_order_sync  ) {
					
					if ( !in_array( $current_mp_array['marketplace_id'], $regions_orders ) ) {
						$actions_array[ $merchant_id ]['order'][] = $current_mp_array['marketplace_id'];
					}
					
				} else {
					// unset current marketplace id from order index if order scheduler is not set anymore.
					if ( in_array( $current_mp_array['marketplace_id'], $regions_orders ) ) {
						$key = array_search( $current_mp_array['marketplace_id'] , $regions_orders );
						unset( $actions_array[ $merchant_id ]['order'][$key] );
					}
				}

				/** Code to ON/OFF region based schedulers */

			}
			/**  Update the actions_array ends */

			foreach ( $actions_array as $merchant_id => $schedulers_data ) {

				$region         = $schedulers_data['region'] ?? '';
				$scheduler_args = array( array( 
					'region' => $region,
					'merchant_id' => $merchant_id,
				) );

				foreach ( $schedulers_data as $action_type => $mp_ids ) {

					if ( 'region' == $action_type ) {
						continue;
					}

					$mp_ids = array_unique( array_filter(array_values( $mp_ids ) ) );
					if ( 'common' == $action_type ) {

						if ( empty( $mp_ids ) && as_has_scheduled_action( 'ced_amazon_common_prc_inv_scheduler_job_' . $merchant_id ) ) {
							// unschedule common scheduler
							as_unschedule_all_actions( 'ced_amazon_common_prc_inv_scheduler_job_' . $merchant_id, $scheduler_args );
						} elseif ( !empty( $mp_ids ) && !as_has_scheduled_action( 'ced_amazon_common_prc_inv_scheduler_job_' . $merchant_id ) ) {
							as_schedule_recurring_action( time(), 1200, 'ced_amazon_common_prc_inv_scheduler_job_' . $merchant_id, $scheduler_args  );
		
							// unschedule inventory scheduler
							as_unschedule_all_actions( 'ced_amazon_inventory_scheduler_job_' . $merchant_id, $scheduler_args );
		
							// unschedule price scheduler
							as_unschedule_all_actions( 'ced_amazon_price_scheduler_job_' . $merchant_id, $scheduler_args );
						}

					}

					if ( 'price' == $action_type ) {
						if ( empty( $mp_ids ) && as_has_scheduled_action( 'ced_amazon_price_scheduler_job_' . $merchant_id ) ) {
							// unschedule price scheduler
							as_unschedule_all_actions( 'ced_amazon_price_scheduler_job_' . $merchant_id, $scheduler_args );
						} elseif ( !empty( $mp_ids ) && !as_has_scheduled_action( 'ced_amazon_price_scheduler_job_' . $merchant_id ) && !as_has_scheduled_action( 'ced_amazon_common_prc_inv_scheduler_job_' . $merchant_id ) ) {
							
							/** We are ready to create price scheduler for new merchant ID */

							/** Check if inventory scheduler exists for same merchant ID, if yes, create common scheduler else create price scheduler */
							if ( as_has_scheduled_action( 'ced_amazon_inventory_scheduler_job_' . $merchant_id ) ) {
								as_schedule_recurring_action( time(), 900, 'ced_amazon_common_prc_inv_scheduler_job_' . $merchant_id, $scheduler_args );
								
								/** Unschedule inventory schudeler after setting common */
								as_unschedule_all_actions( 'ced_amazon_inventory_scheduler_job_' . $merchant_id, $scheduler_args );
		
							} else {
								as_schedule_recurring_action( time(), 900, 'ced_amazon_price_scheduler_job_' . $merchant_id, $scheduler_args );
							}
							
						}
					}

					if ( 'inventory' == $action_type ) {
						if ( empty( $mp_ids ) && as_has_scheduled_action( 'ced_amazon_inventory_scheduler_job_' . $merchant_id ) && !as_has_scheduled_action( 'ced_amazon_common_prc_inv_scheduler_job_' . $merchant_id ) ) {
							// unschedule inventory scheduler
							as_unschedule_all_actions( 'ced_amazon_inventory_scheduler_job_' . $merchant_id, $scheduler_args );
						} elseif ( !empty( $mp_ids ) && !as_has_scheduled_action( 'ced_amazon_inventory_scheduler_job_' . $merchant_id ) ) {
							
							/** Check if price scheduler exists for same merchant ID, if yes, create common scheduler else create inventory scheduler */
							if ( as_has_scheduled_action( 'ced_amazon_price_scheduler_job_' . $merchant_id ) ) {
								as_schedule_recurring_action( time(), 900, 'ced_amazon_common_prc_inv_scheduler_job_' . $merchant_id, $scheduler_args );
								
								/** Unschedule price schudeler after setting common */
								as_unschedule_all_actions( 'ced_amazon_price_scheduler_job_' . $merchant_id, $scheduler_args );
							} else {
								as_schedule_recurring_action( time(), 900, 'ced_amazon_inventory_scheduler_job_' . $merchant_id, $scheduler_args  );
							}
							
						}
					}

					if ( 'order' == $action_type ) {
						if ( empty( $mp_ids ) && as_has_scheduled_action( 'ced_amazon_order_scheduler_job_' . $merchant_id ) ) {
							// unschedule order scheduler
							as_unschedule_all_actions( 'ced_amazon_order_scheduler_job_' . $merchant_id, $scheduler_args );
						} elseif ( !empty( $mp_ids ) && !as_has_scheduled_action( 'ced_amazon_order_scheduler_job_' . $merchant_id ) ) {
							as_schedule_recurring_action( time(), 900, 'ced_amazon_order_scheduler_job_' . $merchant_id, $scheduler_args  );
						}
					}

				}

				# Code to delete region based schedulers
				$region_args = array( array( 
					'region' => strtolower( $region ),
				) );
				if ( as_has_scheduled_action( 'ced_amazon_price_scheduler_job_' . strtolower( $region ) ) ) {
					as_unschedule_all_actions( 'ced_amazon_price_scheduler_job_' . strtolower( $region ), $region_args );
				}
				if ( as_has_scheduled_action( 'ced_amazon_inventory_scheduler_job_' . strtolower( $region ) ) ) {
					as_unschedule_all_actions( 'ced_amazon_inventory_scheduler_job_' . strtolower( $region ), $region_args );
				}
				if ( as_has_scheduled_action( 'ced_amazon_common_prc_inv_scheduler_job_' . strtolower( $region ) ) ) {
					as_unschedule_all_actions( 'ced_amazon_common_prc_inv_scheduler_job_' . strtolower( $region ), $region_args );
				}

				/** Turn off order scheduler with sellerId arugument */
				if ( as_has_scheduled_action( 'ced_amazon_existing_products_sync_job_' . $seller_id ) ) {
					as_unschedule_all_actions( 'ced_amazon_existing_products_sync_job_' . $seller_id, $seller_args );
				}

			
			}

			// used to save flags for initially price and inventory sync ( country wise ).
			update_option( 'ced_amz_all_prc_inv_sync', $ced_amz_all_prc_inv_sync );

			// used to save scheduled actions_array.
			update_option( 'ced_amz_mod_actions_array', $actions_array );

			if ( ! as_has_scheduled_action( 'ced_amazon_delete_feed_cron_job'  ) ) {
				as_schedule_recurring_action( time(), 86400, 'ced_amazon_delete_feed_cron_job'  );
			} 

		} else {
			$this->ced_amz_check_schedules_cron($global_setting_data, $saved_amazon_details, $merchant_ids, $ced_amz_all_prc_inv_sync); // WP-Cron version
		}

	}


	public function ced_amz_check_schedules_cron($global_setting_data = array(), $saved_amazon_details = array(), $merchant_ids = array(), $ced_amz_all_prc_inv_sync = array()) {
		
		if ( !empty( $saved_amazon_details ) && is_array( $saved_amazon_details ) && function_exists( 'wp_next_scheduled' )) {

			// used to store marketplaced ids, amazon region and scheduler type wise.
			$actions_array = get_option( 'ced_amz_mod_actions_array', array() );
			if ( empty( $actions_array ) ) {

				foreach ( $merchant_ids as $mer_id => $rg ) {
					$actions_array[ $mer_id ] = array( 

						'region'  => $rg,
						'common' => array( ),
						'price'  => array( ),
						'inventory' => array( ),
						'order'     => array( )
						
					);

				}
				
			}

			foreach ( $saved_amazon_details as $seller_id => $seller_data ) {
				
				$mp_location_array = explode( '|', $seller_id );
				$mp_location       = $mp_location_array[0];
				$merchant_id       = $mp_location_array[1];

				if ( !isset( $ced_amz_all_prc_inv_sync[ $seller_id ] ) ) {
					$ced_amz_all_prc_inv_sync[ $seller_id ] = array();
				}

				/** To set flag for initially price sync  */
				if ( !isset( $ced_amz_all_prc_inv_sync[ $seller_id ]['price'] ) ) {
					$ced_amz_all_prc_inv_sync[ $seller_id ]['price'] = 0;
				}

				/** To set flag for initially price sync  */
				if ( !isset( $ced_amz_all_prc_inv_sync[ $seller_id ]['inventory'] ) ) {
					$ced_amz_all_prc_inv_sync[ $seller_id ]['inventory'] = 0;
				}

				$seller_args = array( array( 'seller_id' => $seller_id ) );

				/** To check and set the CATALOG ASIN SYNC SCHEDULER  */
				$current_asin_sync = isset( $global_setting_data[ $seller_id ] ) && isset( $global_setting_data[ $seller_id ]['ced_amazon_catalog_asin_sync'] ) ? $global_setting_data[ $seller_id ]['ced_amazon_catalog_asin_sync'] : '';
				if ( 'on' == $current_asin_sync && ! wp_next_scheduled( 'ced_amazon_catalog_asin_sync_job_' . $seller_id, $seller_args ) ) {
					wp_schedule_event( time(), 'ced_amazon_8min', 'ced_amazon_catalog_asin_sync_job_' . $seller_id, $seller_args );
				} elseif ( 'on' !== $current_asin_sync && wp_next_scheduled( 'ced_amazon_catalog_asin_sync_job_' . $seller_id, $seller_args ) ) {
					wp_clear_scheduled_hook( 'ced_amazon_catalog_asin_sync_job_' . $seller_id, $seller_args );
				}

				/** Create report action */
				$current_exist_product_sync = isset( $global_setting_data[ $seller_id ] ) && isset( $global_setting_data[ $seller_id ]['ced_amazon_existing_products_sync'] ) ? $global_setting_data[ $seller_id ]['ced_amazon_existing_products_sync'] : '';
				if ( 'on' == $current_exist_product_sync && ! wp_next_scheduled( 'ced_amazon_create_report_sync_job_' . $seller_id, $seller_args ) ) {
					wp_schedule_event( time(), 'ced_amazon_daily', 'ced_amazon_create_report_sync_job_' . $seller_id, $seller_args );
				} elseif ( 'on' !== $current_exist_product_sync && wp_next_scheduled( 'ced_amazon_create_report_sync_job_' . $seller_id, $seller_args ) ) {
					wp_clear_scheduled_hook( 'ced_amazon_create_report_sync_job_' . $seller_id, $seller_args );
				}


				/** Get report action */
				$current_exist_product_sync = isset( $global_setting_data[ $seller_id ] ) && isset( $global_setting_data[ $seller_id ]['ced_amazon_existing_products_sync'] ) ? $global_setting_data[ $seller_id ]['ced_amazon_existing_products_sync'] : '';
				if ( 'on' == $current_exist_product_sync && ! wp_next_scheduled( 'ced_amazon_get_report_sync_job_' . $seller_id, $seller_args ) ) {
					wp_schedule_event( time(), 'ced_amazon_hourly', 'ced_amazon_get_report_sync_job_' . $seller_id, $seller_args );
				} elseif ( 'on' !== $current_exist_product_sync && wp_next_scheduled( 'ced_amazon_get_report_sync_job_' . $seller_id, $seller_args ) ) {
					wp_clear_scheduled_hook( 'ced_amazon_get_report_sync_job_' . $seller_id, $seller_args );
				}

				/** Shipment action */
				$current_shipment_sync = isset( $global_setting_data[ $seller_id ] ) && isset( $global_setting_data[ $seller_id ]['ced_amazon_shipment_tracking'] ) ? $global_setting_data[ $seller_id ]['ced_amazon_shipment_tracking'] : '';
				if ( 'on' == $current_shipment_sync && ! wp_next_scheduled( 'ced_amazon_shipment_tracking_job_' . $seller_id, $seller_args ) ) {
					wp_schedule_event( time(), 'ced_amazon_8min', 'ced_amazon_shipment_tracking_job_' . $seller_id, $seller_args );
				} elseif ( 'on' !== $current_shipment_sync && wp_next_scheduled( 'ced_amazon_shipment_tracking_job_' . $seller_id, $seller_args ) ) {
					wp_clear_scheduled_hook( 'ced_amazon_shipment_tracking_job_' . $seller_id, $seller_args );
				}

				/** Turn off the schedulers with old arguments */
				$old_seller_args = array( $seller_id );
				if ( wp_next_scheduled( 'ced_amazon_order_scheduler_job_' . $seller_id, $old_seller_args ) ) {
					wp_clear_scheduled_hook( 'ced_amazon_order_scheduler_job_' . $seller_id, $old_seller_args );
				}
				if ( wp_next_scheduled( 'ced_amazon_existing_products_sync_job_' . $seller_id, $old_seller_args ) ) {
					wp_clear_scheduled_hook( 'ced_amazon_existing_products_sync_job_' . $seller_id, $old_seller_args );
				}
				if ( wp_next_scheduled( 'ced_amazon_catalog_asin_sync_job_' . $seller_id, $old_seller_args ) ) {
					wp_clear_scheduled_hook( 'ced_amazon_catalog_asin_sync_job_' . $seller_id, $old_seller_args );
				}

				if ( wp_next_scheduled( 'ced_amazon_price_scheduler_job_' . $seller_id, $old_seller_args ) ) {
					wp_clear_scheduled_hook( 'ced_amazon_price_scheduler_job_' . $seller_id, $old_seller_args );
				}
				if ( wp_next_scheduled( 'ced_amazon_inventory_scheduler_job_' . $seller_id, $old_seller_args ) ) {
					wp_clear_scheduled_hook( 'ced_amazon_inventory_scheduler_job_' . $seller_id, $old_seller_args );
				}
				if ( wp_next_scheduled( 'ced_amazon_shipment_tracking_job_' . $seller_id, $old_seller_args ) ) {
					wp_clear_scheduled_hook( 'ced_amazon_shipment_tracking_job_' . $seller_id, $old_seller_args );
				}

				/** Turn off the seller-wise schedulers */
				$seller_args = array( array( 'seller_id' => $seller_id ) );
				if ( wp_next_scheduled( 'ced_amazon_inventory_scheduler_job_' . $seller_id, $old_seller_args ) ) {
					wp_clear_scheduled_hook( 'ced_amazon_inventory_scheduler_job_' . $seller_id, $old_seller_args );
				}

				$current_mp_array = ced_amz_marketplaceid_region_mapping( $mp_location );
				$current_region   = strtolower( $current_mp_array['region_value'] );
		
				// if( !isset( $actions_array[ $current_region ] ) ){
				// 	$actions_array[ $current_region ][] = $current_mp_array['marketplace_id'];
				// }

				/** Code to ON/OFF region based schedulers */
				$current_price_sync     = isset( $global_setting_data[ $seller_id ] ) && isset( $global_setting_data[ $seller_id ]['ced_amazon_price_schedule_info'] ) ? $global_setting_data[ $seller_id ]['ced_amazon_price_schedule_info'] : '';
				$current_inventory_sync = isset( $global_setting_data[ $seller_id ] ) && isset( $global_setting_data[ $seller_id ]['ced_amazon_inventory_schedule_info'] ) ? $global_setting_data[ $seller_id ]['ced_amazon_inventory_schedule_info'] : '';
				$current_order_sync     = isset( $global_setting_data[ $seller_id ] ) && isset( $global_setting_data[ $seller_id ]['ced_amazon_order_schedule_info'] ) ? $global_setting_data[ $seller_id ]['ced_amazon_order_schedule_info'] : '';
				
				// To check and set the PRICE and INVENTORY COMMON SCHEDULER
				$regions_common    = $actions_array[ $merchant_id ]['common'] ?? array();
				$regions_inventory = $actions_array[ $merchant_id ]['inventory'] ?? array();
				$regions_price     = $actions_array[ $merchant_id ]['price'] ?? array();

				if ( 'on' == $current_price_sync && 'on' == $current_inventory_sync ) {
					
					$common_price_inc_scheduler = true;
					
					if ( !in_array( $current_mp_array['marketplace_id'], $regions_common ) ) {
						$actions_array[ $merchant_id ]['common'][] = $current_mp_array['marketplace_id'];

						// unset current marketplace id from price index if common scheduler is set.
						$key = array_search( $current_mp_array['marketplace_id'] , $regions_price );
						unset( $actions_array[ $merchant_id ]['price'][$key] );

						// unset current marketplace id from inventory index if common scheduler is set.
						$key = array_search( $current_mp_array['marketplace_id'] , $regions_inventory );
						unset( $actions_array[ $merchant_id ]['inventory'][$key] );

					}
					
				} elseif (  'on' !== $current_price_sync || 'on' !== $current_inventory_sync ) {

					// unset current marketplace id from common index if common scheduler is not set anymore.
					if ( in_array( $current_mp_array['marketplace_id'], $regions_common ) ) {
						$key = array_search( $current_mp_array['marketplace_id'] , $regions_common );
						unset( $actions_array[ $merchant_id ]['common'][$key] );
					}
				}
				
				// To check and set the INVENTORY SCHEDULER
				if ( 'on' !== $current_price_sync  && 'on' == $current_inventory_sync  ) {
					$common_price_inc_scheduler = false;

					if ( !in_array( $current_mp_array['marketplace_id'], $regions_inventory ) ) {
						$actions_array[ $merchant_id ]['inventory'][] = $current_mp_array['marketplace_id'];
					}
					
				} elseif ( 'on' !== $current_inventory_sync  ) {

					// unset current marketplace id from inventory index if inventory scheduler is not set anymore.
					if ( in_array( $current_mp_array['marketplace_id'], $regions_inventory ) ) {
						$key = array_search( $current_mp_array['marketplace_id'] , $regions_inventory );
						unset( $actions_array[ $merchant_id ]['inventory'][$key] );
					}
				}
				
				
				// To check and set the PRICE SCHEDULER
				if ( 'on' !== $current_inventory_sync && 'on' == $current_price_sync ) {
					$common_price_inc_scheduler = false;
					if ( !in_array( $current_mp_array['marketplace_id'], $regions_price ) ) {
						$actions_array[ $merchant_id ]['price'][] = $current_mp_array['marketplace_id'];
					}
					
				} elseif ( 'on' !== $current_price_sync  ) {

					// unset current marketplace id from price index if price scheduler is not set anymore.
					if ( in_array( $current_mp_array['marketplace_id'], $regions_price ) ) {
						$key = array_search( $current_mp_array['marketplace_id'] , $regions_price );
						unset( $actions_array[ $merchant_id ]['price'][$key] );
					}
				}

				// To check and set the ORDER SCHEDULER
				$regions_orders = $actions_array[ $merchant_id ]['order'] ?? array();
				if ( 'on' == $current_order_sync  ) {
					
					if ( !in_array( $current_mp_array['marketplace_id'], $regions_orders ) ) {
						$actions_array[ $merchant_id ]['order'][] = $current_mp_array['marketplace_id'];
					}
					
				} else {
					// unset current marketplace id from order index if order scheduler is not set anymore.
					if ( in_array( $current_mp_array['marketplace_id'], $regions_orders ) ) {
						$key = array_search( $current_mp_array['marketplace_id'] , $regions_orders );
						unset( $actions_array[ $merchant_id ]['order'][$key] );
					}
				}

				/** Code to ON/OFF region based schedulers */

			}

			foreach ( $actions_array as $merchant_id => $schedulers_data ) {

				$region         = $schedulers_data['region'] ?? '';
				$scheduler_args = array( array( 
					'region' => $region,
					'merchant_id' => $merchant_id,
				) );

				foreach ( $schedulers_data as $action_type => $mp_ids ) {

					if ( 'region' == $action_type ) {
						continue;
					}

					$mp_ids = array_unique( array_filter(array_values( $mp_ids ) ) );
					if ( 'common' == $action_type ) {

						if ( empty( $mp_ids ) && wp_next_scheduled( 'ced_amazon_common_prc_inv_scheduler_job_' . $merchant_id, $scheduler_args ) ) {
							// unschedule common scheduler
							wp_clear_scheduled_hook( 'ced_amazon_common_prc_inv_scheduler_job_' . $merchant_id, $scheduler_args );
						} elseif ( !empty( $mp_ids ) && !wp_next_scheduled( 'ced_amazon_common_prc_inv_scheduler_job_' . $merchant_id, $scheduler_args ) ) {
							wp_schedule_event( time(), 'ced_amazon_20min', 'ced_amazon_common_prc_inv_scheduler_job_' . $merchant_id, $scheduler_args  );
		
							// unschedule inventory scheduler
							wp_clear_scheduled_hook( 'ced_amazon_inventory_scheduler_job_' . $merchant_id, $scheduler_args );
		
							// unschedule price scheduler
							wp_clear_scheduled_hook( 'ced_amazon_price_scheduler_job_' . $merchant_id, $scheduler_args );
						}

					}

					if ( 'price' == $action_type ) {
						if ( empty( $mp_ids ) && wp_next_scheduled( 'ced_amazon_price_scheduler_job_' . $merchant_id, $scheduler_args ) ) {
							// unschedule price scheduler
							wp_clear_scheduled_hook( 'ced_amazon_price_scheduler_job_' . $merchant_id, $scheduler_args );
						} elseif ( !empty( $mp_ids ) && !wp_next_scheduled( 'ced_amazon_price_scheduler_job_' . $merchant_id, $scheduler_args ) && !wp_next_scheduled( 'ced_amazon_common_prc_inv_scheduler_job_' . $merchant_id, $scheduler_args ) ) {
							
							/** We are ready to create price scheduler for new merchant ID */

							/** Check if inventory scheduler exists for same merchant ID, if yes, create common scheduler else create price scheduler */
							if ( wp_next_scheduled( 'ced_amazon_inventory_scheduler_job_' . $merchant_id, $scheduler_args ) ) {
								wp_schedule_event( time(), 'ced_amazon_15min', 'ced_amazon_common_prc_inv_scheduler_job_' . $merchant_id, $scheduler_args );
								
								/** Unschedule inventory schudeler after setting common */
								wp_clear_scheduled_hook( 'ced_amazon_inventory_scheduler_job_' . $merchant_id, $scheduler_args );
		
							} else {
								wp_schedule_event( time(), 'ced_amazon_15min', 'ced_amazon_price_scheduler_job_' . $merchant_id, $scheduler_args );
							}
							
						}
					}

					if ( 'inventory' == $action_type ) {
						if ( empty( $mp_ids ) && wp_next_scheduled( 'ced_amazon_inventory_scheduler_job_' . $merchant_id, $scheduler_args ) && !wp_next_scheduled( 'ced_amazon_common_prc_inv_scheduler_job_' . $merchant_id, $scheduler_args ) ) {
							// unschedule inventory scheduler
							wp_clear_scheduled_hook( 'ced_amazon_inventory_scheduler_job_' . $merchant_id, $scheduler_args );
						} elseif ( !empty( $mp_ids ) && !wp_next_scheduled( 'ced_amazon_inventory_scheduler_job_' . $merchant_id ) ) {
							
							/** Check if price scheduler exists for same merchant ID, if yes, create common scheduler else create inventory scheduler */
							if ( wp_next_scheduled( 'ced_amazon_price_scheduler_job_' . $merchant_id, $scheduler_args ) ) {
								wp_schedule_event( time(), 'ced_amazon_15min', 'ced_amazon_common_prc_inv_scheduler_job_' . $merchant_id, $scheduler_args );
								
								/** Unschedule price schudeler after setting common */
								wp_clear_scheduled_hook( 'ced_amazon_price_scheduler_job_' . $merchant_id, $scheduler_args );
							} else {
								wp_schedule_event( time(), 'ced_amazon_15min', 'ced_amazon_inventory_scheduler_job_' . $merchant_id, $scheduler_args  );
							}
							
						}
					}

					if ( 'order' == $action_type ) {
						if ( empty( $mp_ids ) && wp_next_scheduled( 'ced_amazon_order_scheduler_job_' . $merchant_id, $scheduler_args ) ) {
							// unschedule order scheduler
							wp_clear_scheduled_hook( 'ced_amazon_order_scheduler_job_' . $merchant_id, $scheduler_args );
						} elseif ( !empty( $mp_ids ) && !wp_next_scheduled( 'ced_amazon_order_scheduler_job_' . $merchant_id, $scheduler_args ) ) {
							wp_schedule_event( time(), 'ced_amazon_15min', 'ced_amazon_order_scheduler_job_' . $merchant_id, $scheduler_args  );
						}
					}

				}

				# Code to delete region based schedulers
				$region_args = array( array( 
					'region' => strtolower( $region ),
				) );
				if ( wp_next_scheduled( 'ced_amazon_price_scheduler_job_' . strtolower( $region ), $region_args ) ) {
					wp_clear_scheduled_hook( 'ced_amazon_price_scheduler_job_' . strtolower( $region ), $region_args );
				}
				if ( wp_next_scheduled( 'ced_amazon_inventory_scheduler_job_' . strtolower( $region ), $region_args ) ) {
					wp_clear_scheduled_hook( 'ced_amazon_inventory_scheduler_job_' . strtolower( $region ), $region_args );
				}
				if ( wp_next_scheduled( 'ced_amazon_common_prc_inv_scheduler_job_' . strtolower( $region ), $region_args ) ) {
					wp_clear_scheduled_hook( 'ced_amazon_common_prc_inv_scheduler_job_' . strtolower( $region ), $region_args );
				}

				/** Turn off order scheduler with sellerId arugument */
				if ( wp_next_scheduled( 'ced_amazon_existing_products_sync_job_' . $seller_id, $seller_args ) ) {
					wp_clear_scheduled_hook( 'ced_amazon_existing_products_sync_job_' . $seller_id, $seller_args );
				}

			
			}

			// used to save flags for initially price and inventory sync ( country wise ).
			update_option( 'ced_amz_all_prc_inv_sync', $ced_amz_all_prc_inv_sync );

			// used to save scheduled actions_array.
			update_option( 'ced_amz_mod_actions_array', $actions_array );

			if ( ! wp_next_scheduled( 'ced_amazon_delete_feed_cron_job'  ) ) {
				wp_schedule_event( time(), 'ced_amazon_daily', 'ced_amazon_delete_feed_cron_job'  );
			} 

		}


	}

	/**
	 * Ced Amazon modifiy woocommerce order number.
	 *
	 * @param [int] $order_id
	 * @param [int] $order
	 * @return int
	 */
	public function ced_amz_modify_woo_order_number( $order_id, $order ) {

		if ( CedAmazonHOPS::custom_orders_table_usage_is_enabled() ) {
			$this->create_amz_order_hops = true;
		}


		if ( ! empty( $order_id ) ) {

			if ( $this->create_amz_order_hops ) {

				global $wpdb;

				$meta_key1 = 'amazon_order_id';
				$meta_key2 = 'ced_amazon_order_seller_id';

				$ced_amazon_order_id = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta WHERE order_id = %d AND meta_key = %s", $order_id, $meta_key1 ) );

				$ced_amazon_order_id = isset( $ced_amazon_order_id[0] ) ? json_decode( json_encode( $ced_amazon_order_id[0] ), true ) : array();
				$ced_amazon_order_id = isset( $ced_amazon_order_id['meta_value'] ) ? $ced_amazon_order_id['meta_value'] : '';

				$ced_amazon_order_seller_id = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta WHERE order_id = %d AND meta_key = %s", $order_id, $meta_key2 ) );

				$ced_amazon_order_seller_id = isset( $ced_amazon_order_seller_id[0] ) ? json_decode( json_encode( $ced_amazon_order_seller_id[0] ), true ) : array();
				$ced_amazon_order_seller_id = isset( $ced_amazon_order_seller_id['meta_value'] ) ? $ced_amazon_order_seller_id['meta_value'] : '';

			} else {
				
				$ced_amazon_order_id        = get_post_meta( $order->get_id(), 'amazon_order_id', true );
				$ced_amazon_order_seller_id = get_post_meta( $order->get_id(), 'ced_amazon_order_seller_id', true );
			}

			$ced_amazon_global_settings = get_option( 'ced_amazon_global_settings', array() );
			$ced_use_amz_order_no       = isset( $ced_amazon_global_settings[ $ced_amazon_order_seller_id ]['ced_use_amz_order_no'] ) ? $ced_amazon_global_settings[ $ced_amazon_order_seller_id ]['ced_use_amz_order_no'] : '';

			if ( ! empty( $ced_amazon_order_id ) && ( 'on' == $ced_use_amz_order_no || '1' == $ced_use_amz_order_no ) ) {
				return $ced_amazon_order_id;
			}
		}

		return $order_id;
	}


	public function ced_show_connected_accounts( $channel = 'amazon' ) {

		if ( 'amazon' == $channel ) {

			$ced_amazon_remote_shop_ids     = get_option( 'ced_amazon_remote_shop_ids', array() );
			$ced_amazon_sellernext_shop_ids = get_option( 'ced_amazon_sellernext_shop_ids', array() );

			$totalAccountsConnected = count( $ced_amazon_sellernext_shop_ids ) + count( $ced_amazon_remote_shop_ids );

			if ( ! empty( $ced_amazon_sellernext_shop_ids ) || ! empty( $ced_amazon_remote_shop_ids ) ) {

				?>
				<a class="woocommerce-importer-done-view-errors-amazon" href="javascript:void(0)" ><?php echo esc_attr( $totalAccountsConnected ); ?> account
					connected <span class="dashicons dashicons-arrow-down-alt2"></span></a>  
					<?php
			}
		}
	}


	public function ced_show_connected_accounts_details( $channel = 'amazon' ) {
 
		if ( 'amazon' == $channel ) {

			$ced_amazon_regions_info = array();
			$file                    = CED_AMAZON_DIRPATH . 'admin/partials/amazonRegions.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}

			$params = array();
			$href   = ced_get_navigation_url( 'amazon', $params );

			$ced_amazon_remote_shop_ids     = get_option( 'ced_amazon_remote_shop_ids', array() );
			$ced_amazon_sellernext_shop_ids = get_option( 'ced_amazon_sellernext_shop_ids', array() );

			$ced_amazon_accounts_merged_array    = $ced_amazon_remote_shop_ids + $ced_amazon_sellernext_shop_ids;
			$ced_amazon_remote_shop_ids_keys     = array_keys( $ced_amazon_remote_shop_ids );
			$ced_amazon_sellernext_shop_ids_keys = array_keys( $ced_amazon_sellernext_shop_ids );

			
			if ( ! empty( $ced_amazon_accounts_merged_array ) ) {
				?>
				<div class="ced_amazon_error"></div>

				<div id="ced-amazon-disconnect-account-modal" class="ced-modal">
					<div class="ced-modal-text-content-disconnect">
						<h4>Are you sure want to disconnect the account ?</h4>
						<div class="ced-button-wrap-popup">
							<span class="spinner"></span>
							<span id="ced_amazon_verf_disconnect_account_btn" data-shop-name="" class="button-primary">Confirm</span>
							<span class="ced-close-button">Cancel</span>
						</div>
					</div>
				</div>

				<tr class="wc-importer-error-log-amazon" style="display:none;">
					<td colspan="4">
						<div>
							<div class="ced-account-connected-form">

								<div class="ced-account-head">

									<div class="ced-account-label">
										<strong>Account Details</strong>
									</div>
									<div class="ced-account-label">
										<strong>Status</strong>
									</div> 
									<div class="ced-account-label">
									</div> 

								</div>

								<?php

								if ( is_array( $ced_amazon_accounts_merged_array ) ) {

									foreach ( $ced_amazon_accounts_merged_array as $sellernextId => $sellernextData ) {

										$current_marketplace_id   = isset( $sellernextData['marketplace_id'] ) ? $sellernextData['marketplace_id'] : '';
										$current_marketplace_name = isset( $ced_amazon_regions_info[ $current_marketplace_id ] ) && isset( $ced_amazon_regions_info[ $current_marketplace_id ]['country-name'] ) ? $ced_amazon_regions_info[ $current_marketplace_id ]['country-name'] : '';

										if ( isset( $sellernextData['ced_amz_current_step'] ) && 3 < $sellernextData['ced_amz_current_step'] ) {

											$mod_params = array_merge(
												array(
													'section'   => 'overview',
													'user_id'   => $sellernextId,
													'seller_id' => $sellernextData['ced_mp_seller_key'],

												),
												$params
											);

											$url      = ced_get_navigation_url( 'amazon', $mod_params );
											$sellerID = $sellernextData['ced_mp_seller_key'];
											?>

												<div class="ced-account-body"> 
													
													<div class="ced-acount-body-label">
														<strong><?php echo esc_attr( $current_marketplace_name ); ?></strong>
													</div>
													<div class="ced-connected-button-wrapper">
														<div class="ced-connected-link-account" href="javascript:void(0)"><span class="ced-circle"></span>Onboarding Completed</div>
													</div>

													<div class="ced-account-button">																											
														<button id="ced_amazon_disconnect_account_btn" type="button" class="components-button is-tertiary" sellernext-shop-id = "<?php echo esc_attr( $sellernextId ); ?>" seller-id = "<?php echo esc_attr( $sellerID ); ?>" > <?php echo esc_html__( 'Disconnect', 'amazon-for-woocommerce' ); ?></button>
															
													<?php if ( in_array( $sellernextId, $ced_amazon_remote_shop_ids_keys ) ) { ?> 
															<a type="button" class="components-button is-primary" href="<?php echo esc_url( $url ); ?>">Manage</a>
														<?php } elseif ( in_array( $sellernextId, $ced_amazon_sellernext_shop_ids_keys ) ) { ?>
															<a type="button" class="components-button is-primary ced_amazon_add_account_button" href="#" re-authorising = "true" mp-id="<?php echo esc_attr( $current_marketplace_id ); ?>" >
															Re-Authorise </a>
														<?php
														}
														?>
													</div>	

												</div> 
												<?php

										} else {
											$current_step = isset( $sellernextData['ced_amz_current_step'] ) ? $sellernextData['ced_amz_current_step'] : '';
											if ( empty( $current_step ) ) {
												$urlKey = array( 'section' => 'setup-amazon' );
											} elseif ( 1 == $current_step ) {
												$urlKey = array(
													'section' => 'setup-amazon',
													'part' => 'wizard-options',
												);
											} elseif ( 2 == $current_step ) {
												$urlKey = array(
													'section' => 'setup-amazon',
													'part' => 'wizard-settings',
												);
											} elseif ( 3 == $current_step ) {
												$urlKey = array(
													'section' => 'setup-amazon',
													'part' => 'configuration',
												);
											} else {
												// $part = 'section=overview';
												$urlKey = array( 'section' => 'overview' );
											}

											$sellerID  = isset( $sellernextData['ced_mp_seller_key'] ) ? $sellernextData['ced_mp_seller_key'] : '';
											$urlParams = array_merge(
												array(
													'user_id'   => $sellernextId,
													'seller_id' => $sellerID,
												),
												$params
											);
											$url       = ced_get_navigation_url( 'amazon', array_merge( $urlKey, $urlParams ) );

											?>

												<div class="ced-account-body">
													<div class="ced-acount-body-label">
														<strong><?php echo esc_attr( $current_marketplace_name ); ?></strong>
													</div>

													<div class="ced-pending-button-wrap">
														<a class="ced-pending-link" href="<?php echo esc_url( $url ); ?>"><span class="ced-circle"></span>Onboarding Pending</a>
													</div>

													<div class="ced-account-button">																											
														<button id="ced_amazon_disconnect_account_btn" type="button" class="components-button is-tertiary" sellernext-shop-id = "<?php echo esc_attr( $sellernextId ); ?>" seller-id = "<?php echo esc_attr( $sellerID ); ?>" >
														<?php echo esc_html__( 'Disconnect', 'amazon-for-woocommerce' ); ?>
														</button>

													<?php if ( in_array( $sellernextId, $ced_amazon_remote_shop_ids_keys ) ) { ?> 
															<a type="button" class="components-button is-primary" href="<?php echo esc_url( $url ); ?>">Manage</a>
														<?php
													} elseif ( in_array( $sellernextId, $ced_amazon_sellernext_shop_ids_keys ) ) {
														?>
															<a type="button" class="components-button is-primary ced_amazon_add_account_button" href="#" re-authorising = "true" mp-id="<?php echo esc_attr( $current_marketplace_id ); ?>" >
																Re-Authorise </a>
														<?php

													}
													?>
													</div>

												</div>

											<?php
										}
									}
								}

								?>

							</div>
						</div>


					</td>
				</tr>

				<?php
			}
		}
	}


	/**
	 * Including feed manager and order manager classes.
	 *
	 * @name load_admin_classes()
	 * @since 1.0.0
	 * @link  http://www.cedcommerce.com/
	 */
	private function load_admin_classes() {
		$classes_names = array(
			'admin/amazon/lib/class-order-manager.php',
			'admin/amazon/lib/class-feed-manager.php',
			'admin/amazon/lib/ced-price-manager.php',
			'admin/amazon/lib/ced-inventory-manager.php',
		);

		foreach ( $classes_names as $class_name ) {
			require_once CED_AMAZON_DIRPATH . $class_name;
		}
	}


	/**
	 * Storing instance of feed manager and order manager classes.
	 *
	 * @name instantiate_admin_classes()
	 * @since 1.0.0
	 * @link  http://www.cedcommerce.com/
	 */
	private function instantiate_admin_classes() {

		if ( class_exists( 'Ced_Umb_Amazon_Order_Manager' ) ) {
			$this->order_manager = Ced_Umb_Amazon_Order_Manager::get_instance();
		}

		if ( class_exists( 'Ced_Umb_Amazon_Feed_Manager' ) ) {
			$this->amazon_feed_manager = new Ced_Umb_Amazon_Feed_Manager();
		}

		if ( class_exists( 'Ced_Amazon_Price_Feed_Manager' ) ) {
			$this->amazon_price_manager = new Ced_Amazon_Price_Feed_Manager();
		}

		if ( class_exists( 'Ced_Amazon_Inventory_Feed_Manager' ) ) {
			$this->amazon_inventory_manager = new Ced_Amazon_Inventory_Feed_Manager();
		}
	}


	public function ced_amazon_relist_products( $params ) {
		
		$logger  = wc_get_logger();
		$context = isset( $params['context'] ) ? $params['context'] : array( 'source' => 'ced_amazon_relist_products' );

		$logger->info( wc_print_r(  '-------------------------------------- INSIDE ' . __FUNCTION__ . ' ----------------------------------- ', true ), $context );
		$logger->info( wc_print_r(  $params, true ), $context );
		
		$logger->info( wc_print_r(  '-------------------------------------- GOING to ced_amazon_bulk_relist_products FUNCTION ----------------------------------- ', true ), $context );
		$this->amazon_feed_manager->ced_amazon_bulk_relist_products( $params );
		$logger->info( wc_print_r(  '-------------------------------------- BACK FROM ced_amazon_bulk_relist_products FUNCTION ----------------------------------- ', true ), $context );
		
	}

	public function ced_amazon_common_prc_inv_method( $params ) {

		// Log file name
		$logger  = wc_get_logger();
		$context = array( 'source' => 'ced_amazon_common_prc_inv_sync' );
		$logger->info( wc_print_r( ced_woo_timestamp() . ' ' . __FUNCTION__, true ), $context );

		$region          = '';
		$marketplace_ids = array( );

		$region      = isset( $params['region'] ) ? $params['region'] : '';
		$merchant_id = isset( $params['merchant_id'] ) ? $params['merchant_id'] : '';
		$seller_id   = isset( $params['seller_id'] ) ? $params['seller_id'] : '';

		$req_params['context']        = $context;
		$req_params['scheduler_name'] = 'ced_amazon_common_prc_inv_sync_';
		$req_params['process_name']   = 'Common';
		$req_params['region']         = $region ;
		$req_params['merchant_id']    = $merchant_id ;
		$req_params['transient_name'] = 'ced_amazon_create_feed_throttle';
		
		$ced_req_fields = $this->ced_amazon_check_required_details_for_cron( $req_params );
		if ( !$ced_req_fields ) {
			$logger->info( wc_print_r( $ced_req_fields , true ), $context );
			return;
		}

		/** Any one remoteshopID (first one) for the current amazon region. */ 
		$remote_shop_id =  isset( $ced_req_fields['remote_shop_id'] ) ? $ced_req_fields['remote_shop_id'] : '';

		/** Any one mplocation (first one) for the current amazon region. */ 
		$mplocation =  isset( $ced_req_fields['mplocation'] ) ? $ced_req_fields['mplocation'] : '';
		
		/** List of all marketplaced_ids corresponding to the current amazon region. */ 
		$marketplace_ids =  isset( $ced_req_fields['marketplace_ids'] ) ? $ced_req_fields['marketplace_ids'] : '';

		$logger->info( wc_print_r( 'Region is ' . $region, true ), $context );

		$first_sync  = false;
		$bulk_params = array(
			'remote_shop_id'  => $remote_shop_id ,
			'mplocation'      => $mplocation,
			'marketplace_ids' =>  array_values( array_filter( array_unique( $marketplace_ids ))),
			'region'          => $region
		);

		$ced_amz_all_prc_inv_sync = get_option( 'ced_amz_all_prc_inv_sync', array() );
		$flag                     = 0;

		/** List of marketplace ids for which first_sync is not runned yet  */
		$yet_to_first_sync = array( );
		
		/** Code to check if flag value ends  */
		if ( !empty( $marketplace_ids ) ) {
			foreach ( $marketplace_ids as $mid ) {
				$seller_id = ced_amz_get_seller_id_by_mrkp_id( $mid  );

				$logger->info( wc_print_r( '------------------- CURRENT mp id IS ' . $mid . '--------------------- ', true ), $context );
				$logger->info( wc_print_r( '------------------- CURRENT seller_id IS ' . $seller_id . '--------------------- ', true ), $context );

				if ( empty( $seller_id ) ) {
					continue;
				}

				$prc_sync_flag = isset( $ced_amz_all_prc_inv_sync[$seller_id] ) && isset( $ced_amz_all_prc_inv_sync[$seller_id]['price'] ) ? $ced_amz_all_prc_inv_sync[$seller_id]['price'] : 0;
				$inv_sync_flag = isset( $ced_amz_all_prc_inv_sync[$seller_id] ) && isset( $ced_amz_all_prc_inv_sync[$seller_id]['inventory'] ) ? $ced_amz_all_prc_inv_sync[$seller_id]['inventory'] : 0;
				
				$logger->info( wc_print_r( '------------------- CURRENT prc_sync_flag IS ' . $prc_sync_flag . '--------------------- ', true ), $context );
				$logger->info( wc_print_r( '------------------- CURRENT inv_sync_flag IS ' . $inv_sync_flag . '--------------------- ', true ), $context );
				
				if ( !$prc_sync_flag || ! $inv_sync_flag ) {
					$yet_to_first_sync[ ] =  $mid;
				}		      
				
			}

			$yet_to_first_sync = array_filter( $yet_to_first_sync );
			$logger->info( wc_print_r( 'marketplace ids which are remaining for first_sync ', true ), $context );
			$logger->info( wc_print_r( $yet_to_first_sync , true ), $context );
				
			if ( !empty( $yet_to_first_sync ) && isset( $yet_to_first_sync[0] ) ) {
				$flag = 0;
			} else {
				$flag = 1;
			}

		}

		$logger->info( wc_print_r( '------------------- CURRENT FLAG IS ' . $flag . '--------------------- ', true ), $context );

		if ( !$flag ) {
			$logger->info( wc_print_r( '--------------------------------------- ITS A FIRST COMMON SYNC ---------------------------------- ', true ), $context );
			$first_sync = true;
			$args       = array(
				'post_type'      => array( 'product' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			);

			$product_ids = get_posts( $args );
			if ( count( $product_ids ) > 2000 ) {
				$product_ids = array_slice( $product_ids, 0, 2000 );
			}

			
			$bulk_params[ 'products' ]          = $product_ids;
			$bulk_params[ 'extraParams' ]       = array( 'first_sync' => $first_sync);
			$bulk_params[ 'opt_type' ]          = 'Automatic';
			$bulk_params[ 'is_common_prc_inv' ] = true;
			$bulk_params['marketplace_ids']     = array( $yet_to_first_sync[0] );
			
			$logger->info( wc_print_r( '--------------------------------------- FIRST COMMON SYNC bulk price params ---------------------------------- ', true ), $context );
			$logger->info( wc_print_r( $bulk_params, true ), $context );
			$logger->info( wc_print_r( '------------------GOING TO THE ced_amazon_bulk_price_update--------------------------- ', true ), $context );
			
			$this->amazon_price_manager->ced_amazon_bulk_price_update( $bulk_params );
			$logger->info( wc_print_r( '------------------BACK TO THE ced_amazon_bulk_price_update--------------------------- ', true ), $context );
		
		} else {

			// products whose price haxs to be updated
			$price_products_to_update = get_option( 'ced_amz_price_updated_products', array() );
			$price_product_ids        = isset( $price_products_to_update[$seller_id] ) ? $price_products_to_update[$seller_id] : array();

			// products whose stock haxs to be updated
			$inventory_products_to_update = get_option( 'ced_amz_stock_updated_products', array() );
			$inventory_product_ids        = isset( $inventory_products_to_update[$seller_id] ) ? $inventory_products_to_update[$seller_id] : array();

			// Get the union of the two arrays
			$product_ids = array_unique( array_merge( $price_product_ids, $inventory_product_ids ) );

			$logger->info( wc_print_r( '------------------- PRODUCTS TO UPDATE  --------------------- ', true ), $context );
			$logger->info( wc_print_r( $product_ids, true ), $context );

			$bulk_params[ 'products' ]          = $product_ids;
			$bulk_params[ 'extraParams' ]       = array( 'first_sync' => $first_sync);
			$bulk_params[ 'opt_type' ]          = 'Automatic';
			$bulk_params[ 'is_common_prc_inv' ] = true;
			
			if ( empty( $product_ids ) ) {
				$logger->info( wc_print_r( '------------------- NO PRODUCTS TO UPDATE PRICE and INVENTORY --------------------- ', true ), $context );
			} else {
				$logger->info( wc_print_r( '------------------- PRODUCTS FOUND TO UPDATE PRICE and INVENTORY --------------------- ', true ), $context );
				$logger->info( wc_print_r( '------------------------------------ GOING TO FEED MANAGER for BULK PRICE action ----------------------------------- ', true ), $context );
				
				$this->amazon_price_manager->ced_amazon_bulk_price_update( $bulk_params );
		
			}

		}


	}
	

	/**
	 * Price update via cron scheduler.
	 *
	 * @name ced_amazon_cron_price_sync()
	 * @since 1.0.0
	 * @link  http://www.cedcommerce.com/
	 */
	public function ced_amazon_cron_price_sync( $params ) {

		// Log file name
		$logger  = wc_get_logger();
		$context = array( 'source' => 'ced_amazon_price_sync' );
		$logger->info( wc_print_r( ced_woo_timestamp() . ' ' . __FUNCTION__, true ), $context );

		$region          = '';
		$marketplace_ids = array( );

		$region      = isset( $params['region'] ) ? $params['region'] : '';
		$merchant_id = isset( $params['merchant_id'] ) ? $params['merchant_id'] : '';
		$seller_id   = isset( $params['seller_id'] ) ? $params['seller_id'] : '';


		$req_params['context']        = $context;
		$req_params['scheduler_name'] = 'ced_amazon_price_schedule_info';
		$req_params['process_name']   = 'Price';
		$req_params['region']         = $region ;
		$req_params['merchant_id']    = $merchant_id ;
		$req_params['transient_name'] = 'ced_amazon_create_feed_throttle';
		
		$ced_req_fields = $this->ced_amazon_check_required_details_for_cron( $req_params );
		
		if ( !$ced_req_fields ) {
			$logger->info( wc_print_r( $ced_req_fields , true ), $context );
			return;
		}

		// any one remoteshopID (first one) for the current amazon region.
		$remote_shop_id =  isset( $ced_req_fields['remote_shop_id'] ) ? $ced_req_fields['remote_shop_id'] : '';

		// any one mplocation (first one) for the current amazon region.
		$mplocation =  isset( $ced_req_fields['mplocation'] ) ? $ced_req_fields['mplocation'] : '';
		
		// list of all marketplaced_ids corresponding to the current amazon region.
		$marketplace_ids =  isset( $ced_req_fields['marketplace_ids'] ) ? $ced_req_fields['marketplace_ids'] : '';

		$logger->info( wc_print_r( 'Region is ' . $region, true ), $context );

		$first_sync        = false;
		$bulk_price_params = array(
			'remote_shop_id'  => $remote_shop_id ,
			'mplocation'      => $mplocation,
			'marketplace_ids' =>  array_values( array_filter( array_unique( $marketplace_ids ))),
			'region'          => $region
		);

		$logger->info( wc_print_r( 'mplocation is ' . $mplocation, true ), $context );
		$first_sync = false;

		
		$ced_amz_all_prc_inv_sync = get_option( 'ced_amz_all_prc_inv_sync', array() );
		$flag                     = isset( $ced_amz_all_prc_inv_sync[$seller_id] ) && isset( $ced_amz_all_prc_inv_sync[$seller_id]['price'] ) ? $ced_amz_all_prc_inv_sync[$seller_id]['price'] : 0;
		
		/** List of marketplace ids for which first_sync is not runned yet  */
		$yet_to_first_sync = array( );
		
		// code to check if flag value ends
		if ( !empty( $marketplace_ids ) ) {
			foreach ( $marketplace_ids as $mid ) {
				$seller_id = ced_amz_get_seller_id_by_mrkp_id( $mid  );

				$logger->info( wc_print_r( '------------------- CURRENT mp id IS ' . $mid . '--------------------- ', true ), $context );
				$logger->info( wc_print_r( '------------------- CURRENT seller_id IS ' . $seller_id . '--------------------- ', true ), $context );

				if ( empty( $seller_id ) ) {
					continue;
				}
				$prc_sync_flag = isset( $ced_amz_all_prc_inv_sync[$seller_id] ) && isset( $ced_amz_all_prc_inv_sync[$seller_id]['price'] ) ? $ced_amz_all_prc_inv_sync[$seller_id]['price'] : 0;
				$logger->info( wc_print_r( '------------------- CURRENT prc_sync_flag IS ' . $prc_sync_flag . '--------------------- ', true ), $context );
				
				if ( !$prc_sync_flag ) {
					$yet_to_first_sync[ ] =  $mid;
				}		      
				
			}

			$yet_to_first_sync = array_filter( $yet_to_first_sync );
			$logger->info( wc_print_r( 'marketplace ids which are remaining for first_sync ', true ), $context );
			$logger->info( wc_print_r( $yet_to_first_sync , true ), $context );
				
			if ( !empty( $yet_to_first_sync ) && isset( $yet_to_first_sync[0] ) ) {
				$flag = 0;
			} else {
				$flag = 1;
			}

		}

		$logger->info( wc_print_r( '------------------- CURRENT FLAG IS ' . $flag . '--------------------- ', true ), $context );

		if ( !$flag ) {
			
			$logger->info( wc_print_r( '--------------------------------------- ITS A FIRST PRICE SYNC ---------------------------------- ', true ), $context );
			$first_sync = true;
			$args       = array(
				'post_type'      => array( 'product' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			);

			$product_ids = get_posts( $args );
			if ( count( $product_ids ) > 2000 ) {
				$product_ids = array_slice( $product_ids, 0, 2000 );
			}

			$bulk_price_params[ 'products' ]    = $product_ids;
			$bulk_price_params[ 'extraParams' ] = array( 'first_sync' => $first_sync);

			$bulk_price_params['marketplace_ids'] = array( $yet_to_first_sync[0] );
			
			$logger->info( wc_print_r( '--------------------------------------- FIRST PRICE SYNC bulk price params ---------------------------------- ', true ), $context );
			$logger->info( wc_print_r( $bulk_price_params, true ), $context );

			$logger->info( wc_print_r( '------------------GOING TO THE ced_amazon_bulk_price_update--------------------------- ', true ), $context );
			$this->amazon_price_manager->ced_amazon_bulk_price_update( $bulk_price_params );
			$logger->info( wc_print_r( '------------------BACK TO THE ced_amazon_bulk_price_update--------------------------- ', true ), $context );
			
		
		} else {

			$products_to_update = get_option( 'ced_amz_price_updated_products', array() );
			$seller_id          = ced_amz_get_seller_id_by_mrkp_id( $marketplace_ids[0] ); 
			$product_ids        = isset( $products_to_update[$seller_id] ) ? $products_to_update[$seller_id] : array();

			if ( empty( $product_ids ) ) {
				$logger->info( wc_print_r( '------------------- NO PRODUCTS TO UPDATE PRICE --------------------- ', true ), $context );
			} else {

				$logger->info( wc_print_r( '------------------- PRODUCTS FOUND TO UPDATE PRICE --------------------- ', true ), $context );
				$logger->info( wc_print_r( '------------------------------------ GOING TO FEED MANAGER for BULK PRICE ACTION ----------------------------------- ', true ), $context );
				
				$bulk_price_params[ 'products' ]    = $product_ids;
				$bulk_price_params[ 'extraParams' ] = array( 'first_sync' => $first_sync);

				$logger->info( wc_print_r( '------------------------------------ GOING TO FEED MANAGER for BULK PRICE action ----------------------------------- ', true ), $context );
				$this->amazon_price_manager->ced_amazon_bulk_price_update( $bulk_price_params );
				$logger->info( wc_print_r( '------------------BACK TO THE ced_amazon_bulk_price_update--------------------------- ', true ), $context );
		
			}

		}
				


	}


	/**
	 * Inventory update via cron scheduler.
	 *
	 * @name ced_amazon_cron_inventory_sync()
	 * @since 1.0.0
	 * @link  http://www.cedcommerce.com/
	 */
	public function ced_amazon_cron_inventory_sync( $params ) {

		// Log file name
		$logger  = wc_get_logger();
		$context = array( 'source' => 'ced_amazon_inventory_sync' );
		$logger->info( wc_print_r( '-------------------------- inside' . __FUNCTION__ . ' ------------------------', true ), $context );

		$region          = '';
		$marketplace_ids = array( );

		$region      = isset( $params['region'] ) ? $params['region'] : '';
		$merchant_id = isset( $params['merchant_id'] ) ? $params['merchant_id'] : '';
		$seller_id   = isset( $params['seller_id'] ) ? $params['seller_id'] : '';

		$req_params['context']        = $context;
		$req_params['scheduler_name'] = 'ced_amazon_inventory_schedule_info';
		$req_params['process_name']   = 'Inventory';
		$req_params['region']         = $region ;
		$req_params['merchant_id']    = $merchant_id ;
		$req_params['transient_name'] = 'ced_amazon_create_feed_throttle';

		// Below function will manage MARKETPLACE IDs  automatically for automatic and manual scheduler
		$logger->info( wc_print_r( 'going from ced_amazon_check_required_details_for_cron', true ), $context );
		$ced_req_fields = $this->ced_amazon_check_required_details_for_cron( $req_params );
		
		if ( !$ced_req_fields ) {
			return;
		}

		// any one remoteshopID (first one) for the current amazon region.
		$remote_shop_id =  isset( $ced_req_fields['remote_shop_id'] ) ? $ced_req_fields['remote_shop_id'] : '';

		// any one mplocation (first one) for the current amazon region.
		$mplocation =  isset( $ced_req_fields['mplocation'] ) ? $ced_req_fields['mplocation'] : '';
		
		// list of all marketplaced_ids corresponding to the current amazon region.
		$marketplace_ids =  isset( $ced_req_fields['marketplace_ids'] ) ? $ced_req_fields['marketplace_ids'] : '';

		$logger->info( wc_print_r( 'Region is ' . $region, true ), $context );

		$first_sync            = false;
		$bulk_inventory_params = array(
			'remote_shop_id'  => $remote_shop_id ,
			'mplocation'      => $mplocation,
			'marketplace_ids' => array_values( array_filter( array_unique( $marketplace_ids ))),
			'region'          => $region
		);
		$logger->info( wc_print_r( 'BULK INVENTORY PARAMS ' , true ), $context );
		$logger->info( wc_print_r( $bulk_inventory_params , true ), $context );

		$ced_amz_all_prc_inv_sync = get_option( 'ced_amz_all_prc_inv_sync', array() );
		$flag                     = 0;

		/** List of marketplace ids for which first_sync is not runned yet  */
		$yet_to_first_sync = array( );
		
		/**  Code to check if flag value ends */
		if ( !empty( $marketplace_ids ) ) {
			foreach ( $marketplace_ids as $mid ) {
				$seller_id = ced_amz_get_seller_id_by_mrkp_id( $mid  );

				$logger->info( wc_print_r( '------------------- CURRENT mp id IS ' . $mid . '--------------------- ', true ), $context );
				$logger->info( wc_print_r( '------------------- CURRENT seller_id IS ' . $seller_id . '--------------------- ', true ), $context );

				if ( empty( $seller_id ) ) {
					continue;
				}
				$inv_sync_flag = isset( $ced_amz_all_prc_inv_sync[$seller_id] ) && isset( $ced_amz_all_prc_inv_sync[$seller_id]['inventory'] ) ? $ced_amz_all_prc_inv_sync[$seller_id]['inventory'] : 0;
				$logger->info( wc_print_r( '------------------- CURRENT inv_sync_flag IS ' . $inv_sync_flag . '--------------------- ', true ), $context );
				
				if ( !$inv_sync_flag ) {
					$yet_to_first_sync[ ] =  $mid;
				}		      
				
			}

			$yet_to_first_sync = array_filter( $yet_to_first_sync );
			$logger->info( wc_print_r( 'marketplace ids which are remaining for first_sync ', true ), $context );
			$logger->info( wc_print_r( $yet_to_first_sync , true ), $context );
				
			if ( !empty( $yet_to_first_sync ) && isset( $yet_to_first_sync[0] ) ) {
				$flag = 0;
			} else {
				$flag = 1;
			}

		}

		// code to check if flag value ends
		$logger->info( wc_print_r( '------------------- CURRENT FLAG IS ' . $flag . '--------------------- ', true ), $context );
		
		if ( !$flag ) {
			
			$logger->info( wc_print_r( '--------------------------------------- ITS A FIRST INVENTORY SYNC FOR ' . $yet_to_first_sync[0] . '---------------------------------- ', true ), $context );
			$first_sync = true;
			
			$args = array(
				'post_type'      => array( 'product' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			);

			$product_ids = get_posts( $args );
			if ( count( $product_ids ) > 2000 ) {
				$product_ids = array_slice( $product_ids, 0, 2000 );
			}

			$bulk_inventory_params[ 'products' ]    = $product_ids;
			$bulk_inventory_params[ 'extraParams' ] = array( 'first_sync' => $first_sync);

			$bulk_inventory_params['marketplace_ids'] = array( $yet_to_first_sync[0] );
			
			$logger->info( wc_print_r( '--------------------------------------- FIRST INVENTORY SYNC bulk inventory params ---------------------------------- ', true ), $context );
			$logger->info( wc_print_r( $bulk_inventory_params, true ), $context );
			
			$logger->info( wc_print_r( '------------------GOING TO THE ced_amazon_bulk_inventory_update--------------------------- ', true ), $context ); 
			$this->amazon_inventory_manager->ced_amazon_bulk_inventory_update( $bulk_inventory_params );
			$logger->info( wc_print_r( '------------------BACK TO THE ced_amazon_bulk_inventory_update--------------------------- ', true ), $context );
			
		} else {

			$products_to_update = get_option( 'ced_amz_stock_updated_products', array() );
			$seller_id          = ced_amz_get_seller_id_by_mrkp_id( $marketplace_ids[0] ); 
			$product_ids        = isset( $products_to_update[$seller_id] ) ? $products_to_update[$seller_id] : array();

			if ( empty( $product_ids ) ) {
				$logger->info( wc_print_r( '------------------- NO PRODUCTS TO UPDATE STOCK --------------------- ', true ), $context );
			} else {

				$logger->info( wc_print_r( '------------------- PRODUCTS FOUND TO UPDATE STOCK --------------------- ', true ), $context );
				$logger->info( wc_print_r( '------------------------------------ GOING TO FEED MANAGER for BULK INVENTORY ACTION ----------------------------------- ', true ), $context );
				
				$bulk_inventory_params[ 'products' ]    = $product_ids;
				$bulk_inventory_params[ 'extraParams' ] = array( 'first_sync' => $first_sync);
				
				$logger->info( wc_print_r( '------------------GOING TO THE ced_amazon_bulk_inventory_update--------------------------- ', true ), $context );
				$this->amazon_inventory_manager->ced_amazon_bulk_inventory_update( $bulk_inventory_params );
				$logger->info( wc_print_r( '------------------BACK TO THE ced_amazon_bulk_inventory_update--------------------------- ', true ), $context );
			
			}

		}
			
		
	}


	/**
	 * Order sync via cron scheduler.
	 *
	 * @name ced_amazon_cron_order_sync()
	 * @since 1.0.0
	 * @link  http://www.cedcommerce.com/
	 */
	public function ced_amazon_cron_order_sync( $params  ) {

		$logger  = wc_get_logger();
		$context = array( 'source' => 'ced_amazon_order_fetch' );
		$logger->info( wc_print_r( ced_woo_timestamp() . ' ' . __FUNCTION__, true ), $context );

		$region      =  $params['region'] ?? '';
		$merchant_id =  $params['merchant_id']  ?? '';
		$seller_id   =  $params['seller_id']  ?? '';
		
		$logger->info( wc_print_r(  'merchant_id is: ' . $merchant_id . ' and region is: ' . $region , true ), $context );

		// $mplocation_arr = explode( '|', $seller_id );
		// $mplocation     = isset( $mplocation_arr[1] ) ? $mplocation_arr[0] : '';
		// $region         = ced_amz_get_region_by_mp_location( $mplocation );

		$req_params['context']        = $context;
		$req_params['scheduler_name'] = 'ced_amazon_order_schedule_info';
		$req_params['process_name']   = 'Order';
		$req_params['region']         = $region ;
		$req_params['merchant_id']    = $merchant_id ;
		$req_params['transient_name'] = 'ced_amazon_orders_throttle';

		$ced_req_fields = $this->ced_amazon_check_required_details_for_cron( $req_params );
		if ( !$ced_req_fields ) {
			return;
		}

		$remote_shop_id  =  isset( $ced_req_fields['remote_shop_id'] ) ? $ced_req_fields['remote_shop_id'] : '';
		$mplocation      =  isset( $ced_req_fields['mplocation'] ) ? $ced_req_fields['mplocation'] : '';   
		$marketplace_ids =  isset( $ced_req_fields['marketplace_ids'] ) ? $ced_req_fields['marketplace_ids'] : array();

		if ( empty( $marketplace_ids ) ) {
			$logger->info( wc_print_r( 'Order scheduler is not activated for any country.', true ), $context );
			return;
		}
		$logger->info( wc_print_r( 'mplocation is ' . $mplocation, true ), $context );

		$params['mplocation']      =  $mplocation;
		$params['cron']            =  true;
		$params['seller_id']       =  $seller_id;
		$params['region']          =  $region;
		$params['marketplace_ids'] =  $marketplace_ids;

		$this->order_manager->fetchOrders( $params );

	}


	public function ced_amazon_create_report_sync( $params = array() ) {

		$seller_id = isset( $params['seller_id'] ) ? $params['seller_id'] : '';

		// Initialize logger and context
		$logger  = wc_get_logger();
		$context = array('source' => 'ced_amazon_report_request_id');

		$logger->info(  wc_print_r(  ' --------------------------- Inside the ced_amazon_create_report_sync function --------------------------', true ), $context);
		$logger->info( wc_print_r(  '------------------- CURRENT SELLER ID IS: ' . $seller_id . ' ------------------------ ', true ), $context );
	
		// Fetch seller_id from GET request if not passed directly
		if ( empty( $seller_id ) ) {
			$seller_id = isset( $_GET['seller_id' ] ) ?  sanitize_text_field( $_GET['seller_id' ] ) : '';
		}
		
		$logger->info( wc_print_r(  '------------------- checking file existance ------------------------ ', true ), $context );
	
		// Include curl request file
		$amazonCurlRequestFile = CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php';
		$logger->info( wc_print_r(  $amazonCurlRequestFile , true ), $context );
	

		if (!file_exists($amazonCurlRequestFile)) {
			$logger->info( wc_print_r('Unable to find the required file.', true), $context);
			return;
		} else {
			$logger->info( wc_print_r('------------------------- file exists ------------------------', true), $context);
		}

		require_once $amazonCurlRequestFile;
		$this->amzonCurlRequestInstance = new Ced_Amazon_Curl_Request();
		
		$logger->info( wc_print_r( '------------------------- REQUIRED THE CURL FILE ------------------------', true ), $context);

		// Fetch saved Amazon details
		$saved_amazon_details = get_option('ced_amzon_configuration_validated', false);
		$shop_data            = $saved_amazon_details[$seller_id] ?? array();
		if (empty($shop_data) || !is_array($shop_data)) {
			$logger->info( wc_print_r("Invalid or missing shop data for seller_id: {$seller_id}", true ), $context);
			return;
		} else {
			$logger->info( wc_print_r( '------------------------- SHOP DATA ------------------------', true ), $context);
			$logger->info( wc_print_r( $shop_data, true ), $context );
	
		}
		
		$marketplace_id = $shop_data['marketplace_id'] ?? '';
		$merchant_id    = $shop_data['merchant_id'] ?? '';
		$remote_shop_id = $shop_data['seller_next_shop_id'] ?? '';
		
		// Check if report ID is already available
		$report_response_id = get_option('ced_amazon_request_report_id', array());
	   
		// Prepare and make the report request
		$report_query_params = array(
			'type' => 'GET_MERCHANT_LISTINGS_ALL_DATA',
			'marketplace_id' => $marketplace_id,
		);
		$report_topic        = 'report-request?' . http_build_query($report_query_params);
		$report_data         = array('remote_shop_id' => $remote_shop_id);
		
		$logger->info( wc_print_r(' ---------------------------  Going to make create report API call --------------------------', true), $context);

		$report_response = $this->amzonCurlRequestInstance->ced_amazon_serverless_process($report_topic, $report_data, 'GET');

		$logger->info( wc_print_r( ' --------------------------- Back after making API call --------------------------', true) , $context);
		$logger->info( wc_print_r( $report_response, true) , $context);

		$response_code = wp_remote_retrieve_response_code($report_response);
		
		// Handle rate limit
		if ( 429 == $response_code ) {
			set_transient('ced_amazon_report_request_sync_throttle', 'on', 300);
			$logger->info( wc_print_r( 'Rate limit hit. Sync throttled for 5 minutes.', true ), $context);
			return;
		}
		
		// Parse response
		$response_body       = json_decode($report_response['body'], true);
		$report_request_data = $response_body['response'][$marketplace_id] ?? array();
		
		if ( !empty( $report_request_data['ReportRequestId']  )) {
		   
			$request_response_id = $report_request_data['ReportRequestId'];
			// update_option( 'ced_amazon_request_report_id', $request_response_id);
			// return $request_response_id;

			$request_report_ids  = get_option( 'ced_amazon_request_report_id', true );
			$current_loc_request = array( $seller_id => $request_response_id );
			if ( is_array( $request_report_ids ) && ! empty( $request_report_ids ) ) {
				$request_report_ids = array_replace( $request_report_ids, $current_loc_request );
			} else {
				$request_report_ids = $current_loc_request;
			}

			update_option( 'ced_amazon_request_report_id', $request_report_ids );
			$logger->info( wc_print_r(' --------------------------- ced_amazon_request_report_id option has been updated --------------------------', true), $context);


		} else {
			$logger->info( 'Error fetching report request ID: ' . wc_print_r($report_response, true), $context);
			return;
		}
	}


	public function ced_amazon_get_report_sync( $params = array() ) {

		$seller_id = isset( $params['seller_id'] ) ? $params['seller_id'] : '';

		// Initialize logger and context
		$logger  = wc_get_logger();
		$context = array('source' => 'ced_amazon_get_report');

		$logger->info( wc_print_r( ' --------------------------- Inside the ced_amazon_get_report_sync function --------------------------', true ), $context);
		$logger->info( wc_print_r(  '------------------- CURRENT SELLER ID IS: ' . $seller_id . ' ------------------------ ', true ), $context );
	
		$request_ids = get_option( 'ced_amazon_request_report_id', array() );

		$logger->info( wc_print_r(  ' request_ids ', true ), $context );
		$logger->info( wc_print_r(  $request_ids , true ), $context );
	
		$keys = array_keys( $request_ids );

		if ( empty( $seller_id ) ) {
		   $seller_id = isset( $_GET['seller_id' ] ) ?  sanitize_text_field( $_GET['seller_id' ] ) : '';
		}

		$logger->info( wc_print_r( '--------------------------------------getting report data-----------------------------------', true ), $context );

		$mplocation = '';
		if ( empty( $seller_id ) ) {
			$logger->info( wc_print_r( 'Seller id argument is missing from report data CRON scheduler, please check!', true ), $context );
			return;
		} else {
			$mplocation_arr = explode('|', $seller_id);
			$mplocation     = isset($mplocation_arr[1]) ? $mplocation_arr[0] : '';
		}

		$location_for_seller = $seller_id;

		// check active marketplace or not
		$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );

		if ( isset( $saved_amazon_details[ $location_for_seller ] ) && ! empty( $saved_amazon_details[ $location_for_seller ] ) && is_array( $saved_amazon_details[ $location_for_seller ] ) ) {
			$shop_data = $saved_amazon_details[ $location_for_seller ];
		}

		$remote_shop_id = isset( $shop_data['seller_next_shop_id'] ) ? $shop_data['seller_next_shop_id'] : ''; 
		$marketplace_id = isset( $shop_data['marketplace_id'] ) ? $shop_data['marketplace_id'] : '';

		$upload_dir = wp_upload_dir();
		
		// Validate mplocation to prevent directory traversal attacks
		$safe_mplocation = preg_replace( '/[^a-zA-Z0-9_-]/', '', $mplocation );
		if ( empty( $safe_mplocation ) ) {
			$logger->error( 'Invalid marketplace location: ' . $mplocation, $context );
			return;
		}
		
		// Build sanitized file path using WordPress helper functions
		$upload_base_path   = realpath( $upload_dir['basedir'] );
		$amazonJsonFilePath = wp_normalize_path( trailingslashit( $upload_base_path ) . 'ced-amazon/amazon_products_report_details_' . $safe_mplocation . '.json' );
		
		$sanitized_file_path = sanitize_file_name($amazonJsonFilePath);

		if ( file_exists( $sanitized_file_path ) ) {
			
			$logger->info( wc_print_r( 'report file already exists for ' . $location_for_seller , true ), $context );
			ob_start();
			readfile( $sanitized_file_path );
			$json_data_def = ob_get_clean();
			if ( null != $json_data_def ) {  // base64_encode(gzcompress(serialize(($data)
				$data = $json_data_def ;
			}
			
		} else {
			$data = '{}';
		}

		$logger->info( wc_print_r( 'decoding data', true ), $context );	

		if ( null !== $data ) {
			$data = json_decode( $data, true );
		}

		$data = array_filter( $data );
		if ( empty($data) ) {
			$logger->info( wc_print_r( 'data is empty now', true ), $context );	
		}

		$logger->info( wc_print_r( 'above the try call', true ), $context );	
		$logger->info( wc_print_r( 'request_ids', true ), $context );	
		$logger->info( wc_print_r( $request_ids, true ), $context );	

		$amazonCurlRequestFile = CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php';
		require_once $amazonCurlRequestFile;
		$this->amzonCurlRequestInstance = new Ced_Amazon_Curl_Request();

		$logger->info( wc_print_r( 'mplocation is: ' . $mplocation, true ), $context );	

		if (  is_array( $request_ids ) && isset( $request_ids[ $location_for_seller ] ) && ! empty( $mplocation )  && empty($data) ) {
			try {

				$report_query_params = array( 'requests'   => $request_ids[ $location_for_seller ] );

				$report_topic = 'report-fetch?' . http_build_query( $report_query_params );
				$catalog_data = array( 'remote_shop_id' => $remote_shop_id );

				$logger->info( wc_print_r('get report sync job: ', true ), $context );
				$report_data_reponse = $this->amzonCurlRequestInstance->ced_amazon_serverless_process( $report_topic, $catalog_data, 'GET' );

				$logger->info( wc_print_r( $report_data_reponse, true ), $context );

				if ( is_wp_error( $report_data_reponse ) ) {
					// Save error in log
					$logger->info( wc_print_r( $report_data_reponse, true ), $context );
					return;
				}
				
				$report_data = json_decode( $report_data_reponse['body'], true );
				if ( isset($report_data['success']) && ! $report_data['success'] ) {
					// Save error in log
					$logger->info( wc_print_r( $report_data_reponse, true ), $context );
					return;
				}

				
				if ( isset( $report_data['success'] ) && $report_data['success'] && 'DONE' == $report_data['response'][0]['ReportProcessingStatus'] ) {

					$logger->info( wc_print_r( '-------------- if condition --------------', true ), $context );
					$data = $report_data['response'][0]['report_content'];
					$data = unserialize( gzuncompress( base64_decode( $data ) ) ) ;
					$data = explode( "\n", $data );

					if (isset($data[0]) && isset($data[1])) {
						unset($data['0']);
					}

					// $data = array_chunk($data,200);
					$data     = array_values($data);
					$mod_data = array();

					foreach ( $data as $string ) {
						$mod_data[] = preg_replace('/[^\x00-\x7F]/', '', $string);
					}
	
					$upload_dir = wp_upload_dir();
					$filePath   = $upload_dir['basedir'] . '/ced-amazon/';

					if (!is_dir($filePath)) {
						if (!mkdir($filePath, 0755)) {
							return;
						}
					}
					
					if (!is_writable($filePath)) {
						return;
					}
	
					$fp = fopen( $sanitized_file_path, 'w');
					chmod($sanitized_file_path, 0777);
					$d = json_encode($mod_data, JSON_PRETTY_PRINT );
					if ( file_put_contents($sanitized_file_path, $d ) ) {
						$logger->info( wc_print_r( "Data successfully written to $filePath", true ), $context );
					} else {
						$logger->info( wc_print_r( "Data not successfully written to $filePath", true ), $context );

					}
					//fwrite($fp, json_encode($data) );
					fclose($fp);
					
					unset( $request_ids[ $location_for_seller ] );
					update_option( 'ced_amazon_request_report_id' , $request_ids );
					return;

				} elseif (  isset( $report_data['status'] ) && 'IN_PROGRESS' == $report_data['response'][0]['ReportProcessingStatus'] ) {
					$logger->info( wc_print_r( '-------------- else if condition --------------', true ), $context );
					$logger->info( wc_print_r( '-------------------------------- report is in progress ---------------------- ', true ), $context );
					return;

				} else {
					$logger->info( wc_print_r( '-------------- else condition --------------', true ), $context );
					$report_data = json_decode( $report_data_reponse['body'], true );
					$logger->info( wc_print_r( $report_data_reponse, true ), $context );
					unset( $request_ids[ $location_for_seller ] );
					update_option( 'ced_amazon_request_report_id' , $request_ids );
					return;

				}

				unset( $request_ids[ $location_for_seller ] );
				update_option( 'ced_amazon_request_report_id', $request_ids );

			} catch ( Exception $e ) {

				// Save error in log
				$logger->info( wc_print_r( 'Exception when calling report data API end point', true ), $context );
				$logger->info( wc_print_r( $e->getMessage(), true ), $context );

			}
		} else {
			$logger->info( wc_print_r( 'Empty report id or mp_location or report data', true ), $context );
		}


		if ( isset($data[0]) && !empty($data[0])  ) {
			
			$logger->info( wc_print_r( 'Total products has length ' . count( $data )  , true ), $context );

			$chunked_data = array_chunk( $data, 1000 );

			$logger->info( wc_print_r( 'array to loop has length ' . count( $chunked_data )  , true ), $context );
			
			foreach ( $chunked_data[0] as $key => $value ) {
				
				$logger->info( wc_print_r( '-----------------------inside loop-----------------------', true ), $context );	
				
				$product_data_array = array();
				$line               = explode( "\t", $value );
				if ( 'item-name' == $line[0] ) {
					continue;
				}
				
				if ( null != $line && null != $line[0] ) {

					//Check if marketplace is France
					if ( 'uk_fr' == $mplocation ) {
						$product_data_array['sku'] = $line[2];
						if ( isset( $line[11] ) && ! empty( $line[11] ) && isset( $line[6] ) && 1 == $line[6] ) {
							$product_data_array['asin'] = $line[11];
						}
					} else {
						$product_data_array['sku']  = $line[3];
						$product_data_array['asin'] = $line[16];
					}
					
					$logger->info( wc_print_r( '---------------product_data_array-------------', true ), $context );	
					$logger->info( wc_print_r( $product_data_array, true ), $context );	
					$pro_id = wc_get_product_id_by_sku( $product_data_array['sku'] );
					
					if ( empty( $pro_id ) ) {
						$pro_id = $this->get_amazon_seller_sku( 'item_sku', $product_data_array['sku'] );
					}

					$logger->info( wc_print_r( '-------------pro_id-----------' . $pro_id, true ), $context );	

					$pro_id = (int) $pro_id;
					if ( '' != $pro_id && 0 != $pro_id && ! empty( $pro_id ) ) {
						update_post_meta( $pro_id, 'ced_amazon_already_uploaded_' . $mplocation, 'yes' );

						// Update meta asin for product
						if ( isset( $product_data_array['asin'] ) && ! empty( $product_data_array['asin'] ) ) {
							update_post_meta( $pro_id, 'ced_amazon_product_asin_' . $mplocation, $product_data_array['asin'] );
						}

						// Set asin to parent product
						$wooc_product = wc_get_product( $pro_id );
						$product_data = $wooc_product->get_data();
						if ( 0 != $product_data['parent_id'] ) {
							$parent_asin = get_post_meta( $product_data['parent_id'], 'ced_amazon_product_asin_' . $mplocation, true );
							if ( empty( $parent_asin ) ) {
								update_post_meta( $product_data['parent_id'], 'ced_amazon_already_uploaded_' . $mplocation, 'yes' );
								update_post_meta( $product_data['parent_id'], 'ced_amazon_product_asin_' . $mplocation, $line[16] );
							}
						}
					}
						
				}
			}

			$logger->info( wc_print_r( 'updating data in file', true ), $context );

			unset($chunked_data[0]);
			$data = array_values($chunked_data);
			
			$logger->info( wc_print_r( 'new array length is ' . count($data) , true ), $context );

			$upload_dir = wp_upload_dir();
			$filePath   =  $upload_dir['basedir'] . '/ced-amazon/';
			if (!is_dir($filePath)) {
				if (!mkdir($filePath, 0755)) {
					$logger->info( wc_print_r( 'returning #1' , true ), $context );
					return;
				}
			}
						
			if (!is_writable($filePath)) {
				$logger->info( wc_print_r( 'returning #2' , true ), $context );
				return;
			}
		
			$fp = fopen( $sanitized_file_path, 'w');
		
			fwrite( $fp, json_encode( array_merge(...$data) ) );
			fclose( $fp );

			$logger->info( wc_print_r( 'updated data in file', true ), $context );

		} else {
			$logger->info( wc_print_r( 'empty data', true ), $context );	
			$logger->info( wc_print_r( '--------------------making create report api call ----------------', true ), $context );	
			$this->ced_amazon_create_report_sync( array( 'seller_id' => $seller_id) );
			$logger->info( wc_print_r( '-------------------- create report api call done----------------', true ), $context );	
		}

		return;

	}


	/**
	 * Search and sync Amazon catalog ASIN in woo product using UPC/EAN.
	 *
	 * @name ced_amazon_cron_catalog_asin_sync()
	 * @since 1.0.0
	 */
	public function ced_amazon_cron_catalog_asin_sync( $params ) {

		// Log file name
		$logger  = wc_get_logger();
		$context = array( 'source' => 'ced_amazon_catalog_asin_sync' );
		$logger->info( wc_print_r( ced_woo_timestamp() . ' ' . __FUNCTION__, true ), $context );

		$seller_id = isset( $params['seller_id'] ) ? $params['seller_id'] : '';

		$mplocation_arr = explode( '|', $seller_id );
		$mplocation     = isset( $mplocation_arr[1] ) ? $mplocation_arr[0] : '';
		$region         = ced_amz_get_region_by_mp_location( $mplocation );

		$req_params['context']        = $context;
		$req_params['scheduler_name'] = 'ced_amazon_catalog_asin_sync';
		$req_params['process_name']   = 'Existing product sync';
		$req_params['region']         = $region ;
		$req_params['seller_id']      = $seller_id ;
		$req_params['transient_name'] = 'ced_amazon_catalog_asin_sync_throttle';

		$ced_req_fields = $this->ced_amazon_check_required_details_for_cron( $req_params );
		if ( !$ced_req_fields ) {
			return;
		}

		$logger->info( wc_print_r( 'returned from ced_amazon_check_required_details_for_cron', true ), $context );

		$remote_shop_id =  isset( $ced_req_fields['remote_shop_id'] ) ? $ced_req_fields['remote_shop_id'] : '';
		$mplocation     =  isset( $ced_req_fields['mplocation'] ) ? $ced_req_fields['mplocation'] : '';

		$location_for_seller = $seller_id;
		$page_number         = get_option( 'ced_amazon_catalog_asin_sync_page_number_' . $location_for_seller, '' );
		if ( empty( $page_number ) ) {
			$page_number = 1;
		}

		// Get UPC/EAN mapping data from global settings
		$global_setting_data = get_option( 'ced_amazon_global_settings', false );
		$meta_key_map        = ! empty( $global_setting_data[ $location_for_seller ]['ced_amazon_catalog_asin_sync_meta'] ) ? $global_setting_data[ $location_for_seller ]['ced_amazon_catalog_asin_sync_meta'] : '_sku';
		$meta_key_map_type   = isset( $global_setting_data[ $location_for_seller ]['ced_amazon_catalog_asin_sync_meta_type'] ) ? $global_setting_data[ $location_for_seller ]['ced_amazon_catalog_asin_sync_meta_type'] : 'EAN';

		$logger->info( wc_print_r( 'key is: ', true ), $context );
		$logger->info( wc_print_r( $meta_key_map, true ), $context );

		$args = array(
			'post_type'        => array( 'product', 'product_variation' ),
			'post_status'      => array( 'publish', 'draft' ),
			'posts_per_page'   => 20,
			'paged'            => $page_number,
			'suppress_filters' => false,
			'fields'           => 'ids',
		);

		$products = get_posts( $args );

		$logger->info( wc_print_r( 'products ID list: ', true ), $context );
		$logger->info( wc_print_r( json_encode($products), true ), $context );

		if ( isset( $products ) && ! empty( $products ) ) {

			$ean_array = array();
			foreach ( $products as $product_id ) {

				$product = wc_get_product( $product_id );
				if ( ! is_object( $product ) ) {
					continue;
				}
		
				// Get UPC/EAN number from woo meta
				$parent_id      = $product->get_parent_id();
				$product_parent = wc_get_product( $parent_id );

				$data       = array( 'product_id' => $product_id, 'product' => $product, 'parent_id' => $parent_id, 'product_parent' => $product_parent );
				$upc_number = $this->amazon_feed_manager->ced_amz_get_barcode( $meta_key_map, $data );

				$upc_number_length = strlen( $upc_number );

				if ( ! empty( $upc_number ) && is_numeric( $upc_number ) && ( 11 == $upc_number_length || 12 == $upc_number_length || 13 == $upc_number_length || 14 == $upc_number_length ) ) {
					// Request to get product data using UPC/EAN
					$ean_array[ $product_id ] = $upc_number;
				}

			}

			$logger->info( wc_print_r( 'ean array is: ', true ), $context );
			$logger->info( wc_print_r( json_encode( $ean_array ), true ), $context );

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

				$code = wp_remote_retrieve_response_code( $catalog_response_main );
				if ( 429 == $code ) {
					set_transient( 'ced_amazon_catalog_asin_sync_throttle', 'on', 300 );
				}

				if ( is_wp_error( $catalog_response_main ) ) {
					$logger->info( wc_print_r( $catalog_response_main, true ), $context );
				}

				$catalog_response = json_decode( $catalog_response_main['body'], true );
				$catalog_response = $catalog_response['data'];

				if ( isset( $catalog_response['success'] ) && 'false' == $catalog_response['success'] ) {
					$logger->info( wc_print_r( $catalog_response_main, true ), $context );
				}
			} else {
				$logger->info( wc_print_r( 'Empty EAN array for all products during asin sync', true ), $context );
			}

			if ( isset( $catalog_response['items'][0] ) && is_array( $catalog_response['items'][0] ) && ! empty( $catalog_response['items'][0] ) ) {
				foreach ( $catalog_response['items'] as $key => $value ) {

					$child_asin   = $value['asin'];
					$identifiers  = $value['identifiers'][0]['identifiers'];
					$unique_id_no = '';

					foreach ( $identifiers as $k => $val ) {
						if ( $meta_key_map_type == $val['identifierType'] ) {
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
			}
			++$page_number;
			update_option( 'ced_amazon_catalog_asin_sync_page_number_' . $location_for_seller, $page_number );

		} else {
			$logger->info( wc_print_r( 'No products found for asin sync', true ), $context );
			update_option( 'ced_amazon_catalog_asin_sync_page_number_' . $location_for_seller, '' );
		}
	}


	/**
	 * Add column in order table
	 *
	 * @since    1.0.0
	 */
	public function custom_shop_order_column( $columns ) {

		$modified_columns = array();

		foreach ( $columns as $key => $column ) {
			$modified_columns[ $key ] = $column;
			if ( 'order_number' == $key ) {
				$modified_columns['order_from'] = '<span title="Order source">Order source</span>';
			}
			if ( 'order_status' == $key ) {
				$modified_columns['sales_channel'] = __( 'Amazon sales channel', 'amazon-for-woocommerce' );
			}
		}
		return $modified_columns;
	}

	/**
	 * Show column data in order table
	 *
	 * @since    1.0.0
	 */
	public function custom_orders_list_column_content( $column, $post_id ) {


		switch ( $column ) {
			case 'order_from':
				$order           = wc_get_order( $post_id );
				$amazon_order_id = $order->get_meta( 'amazon_order_id' );

				if ( ! empty( $amazon_order_id ) ) {
					$amazon_icon = plugin_dir_url( __FILE__ ) . 'images/amazon-logo.png';
					echo '<p><img src="' . esc_url( $amazon_icon ) . '" height="35" width="60"></p>';
				}
				break;

			case 'sales_channel':
				$order               = wc_get_order( $post_id );
				$amazon_order_id     = $order->get_meta( 'amazon_order_id' );
				$order_sales_channel = $order->get_meta( 'ced_umb_order_sales_channel' );
				
				$order_sales_channel = ! empty( $order_sales_channel ) ? $order_sales_channel : '---';
				$amazon_order_id     = ! empty( $amazon_order_id ) ? $amazon_order_id : '---';

				echo '<p>Sales Channel: ' . esc_attr( $order_sales_channel ) . '</p>';
				echo '<p>Amazon Order Id: ' . esc_attr( $amazon_order_id ) . '</p>';

				break;

		}
	}




	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		/**
			* This function is provided for demonstration purposes only.
			*
			* An instance of this class should be passed to the run() function
			* defined in Amazon_Integration_For_Woocommerce_Loader as all of the hooks are defined
			* in that particular class.
			*
			* The Amazon_Integration_For_Woocommerce_Loader will then create the relationship
			* between the defined hooks and the functions defined in this
			* class.
			*/

			$section = ! empty( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : false;
			$channel = ! empty( $_GET['channel'] ) ? sanitize_text_field( $_GET['channel'] ) : false;

		if ( isset( $_GET['page'] ) && ( 'sales_channel' == $_GET['page'] ) ) {

			wp_enqueue_style( WC_ADMIN_APP );
			wp_enqueue_style( 'woocommerce_admin_styles' );
			wp_enqueue_style( 'ced-email-popup-css', plugin_dir_url( __FILE__ ) . 'css/email-popup.css', array(), time(), 'all' );

			wp_enqueue_style( 'marketplace-amazon-integration', plugin_dir_url( __FILE__ ) . 'css/marketplace_amazon_integration.css', array(), time(), 'all' );
			if ( 'amazon' == $channel ) {
				wp_enqueue_style( 'amazon', plugin_dir_url( __FILE__ ) . 'css/amazon.css', array(), time(), 'all' );
			}
		}
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Amazon_Integration_For_Woocommerce_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Amazon_Integration_For_Woocommerce_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		$channel = ! empty( $_GET['channel'] ) ? sanitize_text_field( $_GET['channel'] ) : false;
		$page    = ! empty( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : false;

		$seller_id = ! empty( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';
		$user_id   = ! empty( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '';

		$ced_amzon_configuration_validated = get_option( 'ced_amzon_configuration_validated', array() );
		$sellernextShopIds                 = get_option( 'ced_amazon_remote_shop_ids', array() );

		if ( empty( $seller_id ) && isset( $sellernextShopIds[ $user_id ]['ced_mp_seller_key'] ) ) {
			$seller_id = $sellernextShopIds[ $user_id ]['ced_mp_seller_key'];
		} else {
			$seller_id = urldecode( $seller_id );
		}

		// Ensure nonce is properly generated
		$ajax_nonce     = wp_create_nonce( 'ced-amazon-ajax-seurity-string' );
		$localize_array = array(
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'ajax_nonce' => $ajax_nonce,
			'site_url'   => get_option( 'siteurl' ),
			'user_id'    => $user_id,

		);

		wp_enqueue_media();

		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-ui-datepicker' );

		wp_enqueue_script( 'jquery-tiptip' );
		wp_enqueue_script( 'selectWoo' );

		wp_enqueue_script( 'jquery-ui-spinner' );
		wp_enqueue_script( 'jquery-blockui' );


		if ( 'sales_channel' == $page ) {

			wp_enqueue_script( 'ced-email-popup', plugin_dir_url( __FILE__ ) . 'js/email-popup.js', array(), time(), false );

			$suffix = '';
			wp_register_script( 'woocommerce_admin', WC()->plugin_url() . '/assets/js/admin/woocommerce_admin' . $suffix . '.js', array( 'jquery', 'jquery-blockui', 'jquery-ui-sortable', 'jquery-ui-widget', 'jquery-ui-core', 'jquery-tiptip', 'dompurify' ), WC_VERSION );
			wp_register_script( 'jquery-tiptip', WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip' . $suffix . '.js', array( 'jquery' ), WC_VERSION, true );

			wp_register_script( 'ced_inline_edit_post', admin_url( 'js/inline-edit-post.js' ) , array( 'jquery' ), WC_VERSION, true );

			$params = array();

			wp_localize_script( 'ced_inline_edit_post', 'ced_inline_edit_post', $params );
			wp_enqueue_script( 'ced_inline_edit_post' );

			wp_enqueue_script( 'dompurify', 'https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.1.7/purify.min.js', [], time(), true );

			$params = array(
				'strings' => array(
					'import_products' => __( 'Import', 'woocommerce' ),
					'export_products' => __( 'Export', 'woocommerce' ),
				),
				'urls'    => array(
					'import_products' => esc_url_raw( admin_url( 'edit.php?post_type=product&page=product_importer' ) ),
					'export_products' => esc_url_raw( admin_url( 'edit.php?post_type=product&page=product_exporter' ) ),
				),
			);

			wp_localize_script( 'woocommerce_admin', 'woocommerce_admin', $params );
			wp_enqueue_script( 'woocommerce_admin' );
			wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/amazon-integration-for-woocommerce-admin.js', array( 'jquery', 'jquery-tiptip', 'jquery-ui-spinner', 'jquery-blockui' ), time(), false );

		}

		wp_localize_script( $this->plugin_name, 'ced_amazon_admin_obj', $localize_array );

		// Load relevant js and css file for "Manage Amazon Order" section on order edit page
		global $post;
		$post_type = get_post_type( $post );
		$order_id  = isset( $post->ID ) ? intval( $post->ID ) : '';
		if ( empty( $order_id ) ) {
			$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
			if ( 'wc-orders' == $page ) {
				$order_id = isset( $_GET['id'] ) ? sanitize_text_field( $_GET['id'] ) : '';
			}
		}

		$screen      = get_current_screen();
		$screen_id   = $screen ? $screen->id : '';
		$order_types = wc_get_order_types();

		$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';

		if ( ( in_array( $post_type, $order_types ) && in_array( $screen_id, $order_types ) ) || 'wc-orders' == $page ) {

			$marketplace = $this->order_manager->get_marketplace_info( $order_id );
			if ( $marketplace && ! is_null( $marketplace ) ) {

				wp_enqueue_style( 'Ced_Umb_Amazon_Order_Manager', plugin_dir_url( __FILE__ ) . '/css/jquery-ui-timepicker-addon.css', array(), $this->version );

				$file_url = plugin_dir_url( __FILE__ ) . '/js/order_manager.js';
				wp_register_script( 'Ced_Umb_Amazon_Order_Manager', $file_url, array( 'jquery' ), $this->version );
				wp_localize_script(
					'Ced_Umb_Amazon_Order_Manager',
					'ced_order_localize',
					array(

						'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
						'ajax_nonce' => wp_create_nonce( 'ced-amazon-order-shipment' ),

					)
				);
				wp_enqueue_script( 'Ced_Umb_Amazon_Order_Manager' );
			}
		}


	}


	/*
	 *
	 * Function to create menu
	 */
	public function ced_amazon_add_menus() {
		global $submenu;

		$menu_slug = 'woocommerce';

		if ( ! empty( $submenu[ $menu_slug ] ) && ! in_array('multichannel-by-cedcommerce/multichannel-by-cedcommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			$sub_menus = array_column( $submenu[ $menu_slug ], 2 );
			if ( ! in_array( 'sales_channel', $sub_menus ) ) {
				add_submenu_page( 'woocommerce', 'CedCommerce', 'CedCommerce', 'manage_woocommerce', 'sales_channel', array( $this, 'ced_marketplace_home_page' ), 10, 1 );
			}
		}
	}

	/**
	 *
	 * Function to render home page
	 */
	public function ced_marketplace_home_page() {
		
		?>
		<div class='woocommerce'>
			<?php
			require CED_AMAZON_DIRPATH . 'admin/partials/home.php';
			if ( isset( $_GET['page'] ) && 'sales_channel' == $_GET['page'] && ! isset( $_GET['channel'] ) ) {
				require CED_AMAZON_DIRPATH . 'admin/partials/marketplaces.php';
			} else {

				$channel = ! empty( $_GET['channel'] ) ? sanitize_text_field( $_GET['channel'] ) : '';
				/**
				 *
				 * This action will be used in each plugin and basis of url segments to load the marketplace landing page.
				 *
				 * @since  1.0.0
				 */
				do_action( 'ced_sales_channel_include_template', $channel );
			}
			?>
	</div>
		<?php
	}

	/**
	 *
	 * Function to create submenus
	 */
	public function ced_amazon_add_marketplace_menus_to_array( $menus = array() ) {

		$subscription_details   = get_option( 'ced_mcfw_subscription_details' , array() );
		$subscibed_marketplaces = isset( $subscription_details['selected_marketplace'] ) ? explode( ',', base64_decode( $subscription_details['selected_marketplace'] ) ) : array();
	
		$installed_plugins = get_plugins();
		$menus             = array(
			'woocommerce-etsy-integration'        => array(
				'name'            => 'Etsy Integration',
				'tab'             => 'Etsy',
				'page_url'        => 'https://woocommerce.com/products/etsy-integration-for-woocommerce/',
				'doc_url'         => 'https://woocommerce.com/document/etsy-integration-for-woocommerce/',
				'slug'            => 'woocommerce-etsy-integration',
				'menu_link'       => 'etsy',
				'card_image_link' => CED_AMAZON_URL . 'admin/images/etsy-logo.png',
				'is_active'       => in_array( 'Etsy', $subscibed_marketplaces ) || in_array(
					'woocommerce-etsy-integration/woocommerce-etsy-integration.php',
					/**
										 * Function to get list of active plugins
										 *
										 * @param 'function'
										 * @return 'list'
										 * @since 1.0.0
										 */
					apply_filters( 'active_plugins', get_option( 'active_plugins' ) )
				),
				'is_installed'    => in_array( 'Etsy', $subscibed_marketplaces ) || isset( $installed_plugins['woocommerce-etsy-integration/woocommerce-etsy-integration.php'] ) ? true : false,
			),
			'walmart-integration-for-woocommerce' => array(
				'name'            => 'Walmart Integration',
				'tab'             => 'Walmart',
				'page_url'        => 'https://woocommerce.com/products/walmart-integration-for-woocommerce/',
				'doc_url'         => 'https://woocommerce.com/document/walmart-integration-for-woocommerce/',
				'slug'            => 'walmart-integration-for-woocommerce',
				'menu_link'       => 'walmart',
				'card_image_link' => CED_AMAZON_URL . 'admin/images/walmart-logo.png',
				'is_active'       => in_array( 'Walmart', $subscibed_marketplaces ) || in_array(
					'walmart-integration-for-woocommerce/walmart-woocommerce-integration.php',
					/**
										 * Function to get list of active plugins
										 *
										 * @param 'function'
										 * @return 'list'
										 * @since 1.0.0
										 */
					apply_filters( 'active_plugins', get_option( 'active_plugins' ) )
				),
				'is_installed'    => in_array( 'Walmart', $subscibed_marketplaces ) || isset( $installed_plugins['walmart-integration-for-woocommerce/walmart-woocommerce-integration.php'] ) ? true : false,
			),
			'ebay-integration-for-woocommerce'    => array(
				'name'            => 'eBay Integration',
				'tab'             => 'eBay',
				'page_url'        => 'https://woocommerce.com/products/ebay-integration-for-woocommerce/',
				'doc_url'         => 'https://woocommerce.com/document/ebay-integration-for-woocommerce/',
				'slug'            => 'ebay-integration-for-woocommerce',
				'menu_link'       => 'ebay',
				'card_image_link' => CED_AMAZON_URL . 'admin/images/ebay-logo.png',
				'is_active'       => in_array( 'Ebay', $subscibed_marketplaces ) || in_array(
					'ebay-integration-for-woocommerce/woocommerce-ebay-integration.php',
					/**
										 * Function to get list of active plugins
										 *
										 * @param 'function'
										 * @return 'list'
										 * @since 1.0.0
										 */
					apply_filters( 'active_plugins', get_option( 'active_plugins' ) )
				),
				'is_installed'    => in_array( 'Ebay', $subscibed_marketplaces ) || isset( $installed_plugins['ebay-integration-for-woocommerce/woocommerce-ebay-integration.php'] ) ? true : false,
			),
			'amazon-for-woocommerce'              => array(
				'name'            => 'Amazon Integration',
				'tab'             => 'Amazon',
				'page_url'        => 'https://woocommerce.com/products/amazon-for-woocommerce/',
				'doc_url'         => 'https://woocommerce.com/document/amazon-for-woocommerce/',
				'slug'            => 'amazon-for-woocommerce',
				'menu_link'       => 'amazon',
				'card_image_link' => CED_AMAZON_URL . 'admin/images/amazon-logo.png',
				'is_active'       => in_array( 'Amazon', $subscibed_marketplaces ) || in_array(
					'amazon-for-woocommerce/amazon-for-woocommerce.php',
					/**
										 * Function to get list of active plugins
										 *
										 * @param 'function'
										 * @return 'list'
										 * @since 1.0.0
										 */
					apply_filters( 'active_plugins', get_option( 'active_plugins' ) )
				),
				'is_installed'    => in_array( 'Amazon', $subscibed_marketplaces ) || isset( $installed_plugins['amazon-for-woocommerce/amazon-for-woocommerce.php'] ) ? true : false,
			),
		);
		return $menus;
	}


	/**
	 *
	 * Function for displaying default page
	 */
	public function ced_amazon_accounts_page( $channel = 'amazon' ) {

		if ( 'pricing' == $channel && ! in_array('multichannel-by-cedcommerce/multichannel-by-cedcommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

			require CED_AMAZON_DIRPATH . 'admin/saas/template/class-ced-common-pricing-plan.php';
			( new Ced_Pricing_Plans() )->ced_pricing_plan_display();

		} elseif ( 'amazon' == $channel ) {

			$fileAccounts = CED_AMAZON_DIRPATH . 'admin/partials/ced-amazon-accounts.php';
			if ( file_exists( $fileAccounts ) ) {
				require_once $fileAccounts;
			}
		}
	}

	/**
	 *
	 * Function to fetch next level Category
	 */
	public function ced_amazon_fetch_next_level_category() {

	
		$check_ajax = check_ajax_referer( 'ced-amazon-ajax-seurity-string', 'ajax_nonce' );
		if ( ! $check_ajax ) {
			return;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( WP_Filesystem() ) {
			global $wp_filesystem;
		}

		$template_id = isset( $_POST['template_id'] ) ? sanitize_text_field( $_POST['template_id'] ) : '';
		$user_id     = isset( $_POST['user_id'] ) ? sanitize_text_field( $_POST['user_id'] ) : '';
		$seller_id   = isset( $_POST['seller_id'] ) ? sanitize_text_field( $_POST['seller_id'] ) : '';

		$select_html = '';
		global $wpdb;

		$sanitized_array = filter_input_array( INPUT_POST, FILTER_UNSAFE_RAW );

		$amazon_category_data = isset( $sanitized_array['category_data'] ) ? ( $sanitized_array['category_data'] ) : array();
		$template_id          = isset( $sanitized_array['template_id'] ) ? ( $sanitized_array['template_id'] ) : '';

		$display_saved_values = isset( $sanitized_array['display_saved_values'] ) ? ( $sanitized_array['display_saved_values'] ) : '';

		$amzonCurlRequest = CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php';

		if ( file_exists( $amzonCurlRequest ) ) {
			require_once $amzonCurlRequest;
			$this->amzonCurlRequestInstance = new Ced_Amazon_Curl_Request();
		} else {
			return;
		}

		// $template_type = '';
		if ( ! empty( $template_id ) ) {

			global $wpdb;
			$result                 = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ced_amazon_profiles WHERE `id` = %s ", $template_id ), 'ARRAY_A' );
			$current_amazon_profile = isset( $result[0] ) ? $result[0] : array();

		}

		if ( 'no' == $display_saved_values ) {
			$current_amazon_profile = array();
		}

		if ( is_array( $amazon_category_data ) && ! empty( $amazon_category_data ) ) {

			$product_type = isset( $amazon_category_data['product_type'] ) ? $amazon_category_data['product_type'] : '';

		}
		
		$url_array = array(

			0 => array(
				'url' => 'get-product-type-definitions/?product_type=' . $product_type . '&productTypeVersion=LATEST&requirements=LISTING&requirementsEnforced=ENFORCED&locale=DEFAULT',
				'key' => 'category_attributes',
			),

		);

		$ced_amzon_configuration_validated = get_option( 'ced_amzon_configuration_validated', array() );

		if ( empty( $seller_id ) ) {
			$ced_amazon_remote_shop_ids = get_option( 'ced_amazon_remote_shop_ids', array() );
			$seller_id                  = isset( $ced_amazon_remote_shop_ids[ $user_id ] ) ? $ced_amazon_remote_shop_ids[ $user_id ]['ced_mp_seller_key'] : '';

		}

		$userData       = isset( $ced_amzon_configuration_validated[ $seller_id ] ) ? $ced_amzon_configuration_validated[ $seller_id ] : array() ;
		$userCountry    = isset( $userData['ced_mp_name'] ) ? $userData['ced_mp_name']  : '' ;
		$marketplace_id = isset( $userData['marketplace_id'] ) ? $userData['marketplace_id'] : '';

		$upload_dir = wp_upload_dir();

		// save profile
		$dirname        = $upload_dir['basedir'] . '/ced-amazon/templates/' . $userCountry . '/' . $product_type . '/';
		$fileName       = $dirname . '/products_json_fields.json';
		$schemafileName = $dirname . '/products_json_schema.json';
		
		$amazon_template_attributes_data = '{}';

		if ( ! file_exists( $fileName ) || ! file_exists( $schemafileName ) ) {

			if ( ! is_dir( $dirname ) ) {
				wp_mkdir_p( $dirname );
			}

			wp_mkdir_p( $dirname );

			$amazon_profile_data_response = $this->amzonCurlRequestInstance->ced_amazon_get_category( $url_array[ 0 ]['url'], $user_id, $seller_id );
			
			if ( $amazon_profile_data_response['success'] && null !== $amazon_profile_data_response['response'] ) {

				$metaschema_response = json_encode( $amazon_profile_data_response['response']['propertyGroups'] );

				$schema_url      = $amazon_profile_data_response['response']['schema']['link']['resource'];
				$schema_response = wp_remote_get( $schema_url, array(
					'timeout' => 30, // or more
				) );

				$amazon_template_schema          = $metaschema_response;
				$amazon_template_attributes_data = $schema_response['body'];

				if ( ! file_exists( $schemafileName ) && WP_Filesystem() ) {
					if ( $wp_filesystem ) {
						$wp_filesystem->put_contents( $schemafileName, $amazon_template_schema, FS_CHMOD_FILE );
					}
				}

				if ( ! file_exists( $fileName ) && WP_Filesystem() ) {
					if ( $wp_filesystem ) {
						$wp_filesystem->put_contents( $fileName, $amazon_template_attributes_data, FS_CHMOD_FILE );
					}
				}

			} else {
				echo esc_attr( json_encode( $select_html ) );
				wp_die();
			}

		} elseif ( WP_Filesystem() && $wp_filesystem ) {
			$amazon_template_attributes_data = $wp_filesystem->get_contents( $fileName );
			$amazon_template_schema          = $wp_filesystem->get_contents( $schemafileName );
		} 

		$amazonCategoryList   = json_decode( $amazon_template_attributes_data, true );
		$amazonCategorySchema = json_decode( $amazon_template_schema, true );

		$valid_values = array();
		
		
		if ( ! empty( $amazonCategoryList ) && ! empty( $amazonCategorySchema ) ) {

			global $wpdb;

			$results       = $wpdb->get_results( "SELECT DISTINCT meta_key FROM {$wpdb->prefix}postmeta", 'ARRAY_A' );
			$query         = $wpdb->get_results( $wpdb->prepare( "SELECT `meta_value` FROM  {$wpdb->prefix}postmeta WHERE `meta_key` LIKE %s", '_product_attributes' ), 'ARRAY_A' );
			$addedMetaKeys = get_option( 'CedUmbProfileSelectedMetaKeys', false );

			$optionalFields = array();
			$html           = '';

			$ced_amazon_general_options_arr = get_option( 'ced_amazon_general_options', array() );
			$ced_amazon_general_options     = $ced_amazon_general_options_arr[ 'general_options' ] ?? array();
			$count                          = 0;
			$amazonCategorySchema           = array_reverse( $amazonCategorySchema );
			foreach ( $amazonCategorySchema as $sectionKey => $sectionFields ) {

				
				++$count;
				$params = array(
					'results' => $results, 
					'query' => $query, 
					'addedMetaKeys' => $addedMetaKeys, 
					'sectionKey' => $sectionKey, 
					'sectionFields' => $sectionFields, 
					'amazonCategoryList' => $amazonCategoryList, 
					'current_amazon_profile' => $current_amazon_profile, 
					'display_saved_values' => $display_saved_values, 
					'seller_id' => $seller_id, 
					'template_id' => $template_id,
					'general_options' => $ced_amazon_general_options
				);

				$select_html2 = $this->prepareProfileFieldsSectionNew( $params );
				$select_html .= $select_html2['html'];
	
			}


		}

		echo esc_attr( wp_send_json_success( $select_html ) );
		wp_die();

		
	}

	public function ced_amz_check_attr_properties( $last_prt_key = '', $fieldkey = '', $properties = array(), $final_fields = array() ) {
		

		foreach ( $properties as $key => $arr ) {
			if ( isset( $arr['properties'] ) ) {
				
				if ( !empty( $fieldkey ) ) {
				   $final_fields[ $fieldkey ] = array();
				}
				$final_fields = $this->ced_amz_check_attr_properties( $fieldkey, $key, $arr['properties'], $final_fields );
			
			} elseif ( isset( $arr['items'] ) && isset( $arr['items']['properties'] ) ) {
				
				if ( !empty( $fieldkey ) ) {
				   $final_fields[ $fieldkey ] = array();
				}
				$final_fields = $this->ced_amz_check_attr_properties( $fieldkey, $key, $arr['items']['properties'], $final_fields );
			
			} else {
				
				if ( !empty( $fieldkey ) ) {
					$final_fields[ $fieldkey][ $key ] = $arr;
				}

				if ( !empty( $last_prt_key ) ) {
					$final_fields[ $last_prt_key][ $key ] = $arr;
				}
				
			}
		}

		return $final_fields;

	}


	public function prepareProfileFieldsSectionNew( $params ) {

		
		$sectionKey    = isset( $params['sectionKey'] ) ? $params['sectionKey'] : '';
		$sectionFields = isset( $params['sectionFields'] ) ? $params['sectionFields'] : array();
		
		$amazonCategoryList   = isset( $params['amazonCategoryList'] ) ? $params['amazonCategoryList'] : array();
		$display_saved_values = isset( $params['display_saved_values'] ) ? $params['display_saved_values'] : '';
		$general_options      = isset( $params['general_options'] ) ? $params['general_options'] : array();

		unset( $params['sectionKey'] );
		unset( $params['sectionFields'] );
		unset( $params['amazonCategoryList'] );
		
		$profileSectionHtml = '<div class="ced-faq-wrapper">

				<input class="ced-faq-trigger" id="' . $sectionKey . '" type="checkbox"  >
				<label class="ced-faq-title" for="' . $sectionKey . '"> ' . $sectionFields['title'] . '</label>
				<div class="ced-faq-content-wrap">
					<div class="ced-faq-content-holder">
						<div class="ced-form-accordian-wrap">
							<div class="wc-progress-form-content woocommerce-importer">
								<header>
									<table class="form-table ced_amz_attrs">
										<tbody> ';


										$required_fields = $amazonCategoryList['required'];
		foreach ( $sectionFields['propertyNames'] as $fieldsKey ) {


			$fieldsValue = $amazonCategoryList['properties'][$fieldsKey];
			$properties  = $fieldsValue['items']['properties'];
											
			$final_field_attributes = $this->ced_amz_check_attr_properties( $fieldsKey, '', $properties, array() );

			$sub_keys = array(
				'body_type', 'size_to', 'size', 'unit','type', 'media_location', 'entity', 'key', 'size_system', 'size_class', 'material', 'color',
				'cuff_style', 'our_price', 'your_price'
			);


			$parent_key = $fieldsKey;
			foreach ( $final_field_attributes as $attrKey1 => $attributeFields1 ) {
				foreach ( $attributeFields1 as $attrKey2 => $attributeFields2 ) {

					if ( 'marketplace_id' == $attrKey2 || 'language_tag' == $attrKey2 ) {
						continue;
					}

					/** To create unique ids/ slug for each attribute */ 
					if ( 'value' == $attrKey2 ) {
						/**  To handle attributes that doesn't have multiple attributes such as item_name */
						$attrKey = $attrKey1;
						if ( $parent_key !== $attrKey1 ) {
							$attrKey = $parent_key . '_' . $attrKey;
						}
					} else {
						/** To handle exceptional attributes */ 
						// $attrKey = $attrKey2;
						$attrKey = $attrKey1 . '_' . $attrKey2;
						if ( $parent_key !== $attrKey1 ) {
							$attrKey = $parent_key . '_' . $attrKey;
						}
					}

					// elseif ( in_array( $attrKey2, $sub_keys ) ) {
					// 	// to handle attributes ( such as item dimensions ) that has multiple sub-attributes inside them
					// 	$attrKey = $attrKey1 . '_' . $attrKey2;
					// 	if ( $parent_key !== $attrKey1 ) {
					// 		$attrKey = $parent_key . '_' . $attrKey;
					// 	}
					// } 

					$default_value  = isset( $general_options[ $attrKey ] ) && isset( $general_options[ $attrKey ]['default'] ) ? $general_options[ $attrKey ]['default'] : '';
					$meta_keyGlobal = isset( $general_options[ $attrKey ] ) && isset( $general_options[ $attrKey ]['metakey'] ) ? $general_options[ $attrKey]['metakey'] : '';

					$globalValue = 'no';
					if ( !empty( $default_value ) || !empty( $meta_keyGlobal ) ) {
						$globalValue = 'yes';
					}

					$rowParams = array_merge( $params, array( 
						'req' => '' , // $req,
						'required'        => '', // $required, 
						'attrKey' => $attrKey,
						'attributeFields' => $attributeFields2, 
						'globalValue'     => $globalValue, 
						'defaultGlobal'   => $default_value,
						'meta_keyGlobal'  => $meta_keyGlobal
														
					) );

					$prodileRowHTml      = $this->prepareProfileRowsNew( $rowParams  );
					$profileSectionHtml .= $prodileRowHTml;

				}

			}

		}
										
							$profileSectionHtml .= '</tbody>
								    </table>
							    </header>
						    </div>
					    </div>
				    </div>
			    </div>
			</div>';

			return array( 'html'  => $profileSectionHtml );


	}

	
	/*
	 *
	 * Function to prepare profile rows
	 */
	public function prepareProfileRowsNew( $rowParams ) {

		$results       = isset( $rowParams['results'] ) ? $rowParams['results'] : array();
		$query         = isset( $rowParams['query'] ) ? $rowParams['query'] : array();
		$addedMetaKeys = isset( $rowParams['addedMetaKeys'] ) ? $rowParams['addedMetaKeys'] : array();
		
		$current_amazon_profile = isset( $rowParams['current_amazon_profile'] ) ? $rowParams['current_amazon_profile'] : array();
		$seller_id              = isset( $rowParams['seller_id'] ) ? $rowParams['seller_id'] : '';
		$template_id            = isset( $rowParams['template_id'] ) ? $rowParams['template_id'] : '';
		$general_options        = isset( $rowParams['general_options'] ) ? $rowParams['general_options'] : array();
		
		$req       = isset( $rowParams['req'] ) ? $rowParams['req'] : '';
		$required  = isset( $rowParams['required'] ) ? $rowParams['required'] : '';
		$fieldsKey = isset( $rowParams['attrKey'] ) ? $rowParams['attrKey'] : '';

		$fieldsValue          = isset( $rowParams['attributeFields'] ) ? $rowParams['attributeFields'] : array();
		$display_saved_values = isset( $rowParams['display_saved_values'] ) ? $rowParams['display_saved_values'] : 'no';
		
		$globalValue        = isset( $rowParams['globalValue'] ) ? $rowParams['globalValue'] : 'no';
		$globalValueDefault = isset( $rowParams['defaultGlobal'] ) ? $rowParams['defaultGlobal'] : '';
		$globalValueMetakey = isset( $rowParams['meta_keyGlobal'] ) ? $rowParams['meta_keyGlobal'] : '';
		
		$rowHtml  = '';
		$rowHtml .= '<tr class="categoryAttributes" id="ced_amazon_categories" data-attr="' . $req . '">';

		if ( 'yes' == $display_saved_values ) {
			$req = '';
		}

		$fields_to_skip = [ 'battery_life_percentage' ];
		if (  in_array( $fieldsKey , $fields_to_skip ) ) {
			return;
		}

		$row_label = isset( $fieldsValue['title'] ) ?  $fieldsValue['title'] : '';

		$index = strpos( $fieldsKey, '_custom_field' );
		if ( $index > -1 ) {
			$slug = substr( $fieldsKey, 0, $index );
		} else {
			$slug = $fieldsKey;
		}

		$rowHtml .= '<td class="ced_template_labels" ><label for="" class="">' . $row_label;
		$desc     = isset( $fieldsValue['description'] ) ?  $fieldsValue['description'] : '';
		$rowHtml .= wc_help_tip( $desc, 'amazon-for-woocommerce' );
		$rowHtml .= '</label><p class="cat_attr_para"> (' . $slug . ') </p></td>';

		if ( ! empty( $current_amazon_profile ) ) {
			$saved_value = json_decode( $current_amazon_profile['category_attributes_data'], true );
			$saved_value = isset( $saved_value[ $fieldsKey ] ) ? $saved_value[ $fieldsKey ] : '';
		} else {
			$saved_value = array();
		}

		/** To use default values of the template */
		$default_value = isset( $saved_value['default'] ) ? $saved_value['default'] : '';
	   
		/** To use global values of the settings page */
		if ( empty( $default_value ) && 'yes' == $globalValue && empty( $template_id ) ) {
			$default_value = $globalValueDefault;
		}

		$rowHtml .= '<td>';
		
		if ( !isset($fieldsValue['enum' ]) && !isset($fieldsValue['enumNames']) && isset($fieldsValue[ 'anyOf' ]) && is_array($fieldsValue['anyOf']) ) {
			foreach ( $fieldsValue[ 'anyOf' ] as $key => $mod_value  ) {
				// $mod_value = json_decode( $value, true );
				if ( isset( $mod_value['enum'] ) && isset( $mod_value['enumNames'] ) ) {
					$fieldsValue[ 'enum' ]      = $mod_value['enum'];
					$fieldsValue[ 'enumNames' ] = $mod_value['enumNames'] ;
				}
			}
		}

		if ( isset( $fieldsValue[ 'enum' ] ) && isset( $fieldsValue[ 'enumNames' ] )  ) {

			$rowHtml .= '<select class="custom_category_attributes_select2" id="' . $fieldsKey . '"  name="ced_amazon_profile_data[' . $fieldsKey . '][default]"><option value="">--Select--</option>';

			$enum         =  $fieldsValue[ 'enum' ];
			$enumNames    =  $fieldsValue[ 'enumNames' ];
			$optionLabels = array_combine( $enum , $enumNames );
			
			foreach ( $optionLabels as $acpt_key => $acpt_value ) {

				$selected = '';
				if ( '' == $acpt_key ) {
					$acpt_key = '0';
				}
				if ( $acpt_key == $default_value ) {
					$selected = 'selected';
				} 
				// elseif ( $acpt_key == $sub_category_id && 'feed_product_type' == $fieldsKey ) {
				// 	$selected = 'selected';
				// }
				$rowHtml .= '<option value="' . $acpt_key . '"' . $selected . '>' . $acpt_value . '</option>';
			}

			$rowHtml .= '</select>';

		} else {
			$rowHtml .= '<input class="custom_category_attributes_input" value="' . esc_attr( $default_value ) . '" id="' . esc_attr( $fieldsKey ) . '" type="text" name="ced_amazon_profile_data[' . esc_attr( $fieldsKey ) . '][default]" />';
		}

		// elseif ( 'feed_product_type' == $fieldsKey && empty( $default_value ) ) {
		// 	$rowHtml .= '<input class="custom_category_attributes_input" value="' . esc_attr( $sub_category_id ) . '" id="' . esc_attr( $fieldsKey ) . '" type="text" name="ced_amazon_profile_data[' . esc_attr( $fieldsKey ) . '][default]" />';
		// } 
		
		$rowHtml .= '</td>';

		$rowHtml        .= '<td>';
		$selected_value2 = isset( $saved_value['metakey'] ) ? $saved_value['metakey'] : '';

		if ( empty( $selected_value2 ) && 'yes' == $globalValue && empty( $template_id ) ) {
			$selected_value2 = $globalValueMetakey;
		}

		$selectDropdownHTML = '<select class="select2 custom_category_attributes_select"  name="ced_amazon_profile_data[' . $fieldsKey . '][metakey]">';

		foreach ( $results as $key2 => $meta_key ) {
			$post_meta_keys[] = $meta_key['meta_key'];
		}

		$custom_prd_attrb = array();
		$attrOptions      = array();

		if ( ! empty( $query ) ) {
			foreach ( $query as $key3 => $db_attribute_pair ) {
				foreach ( maybe_unserialize( $db_attribute_pair['meta_value'] ) as $key4 => $attribute_pair ) {
					if ( 1 != $attribute_pair['is_taxonomy'] ) {
						$custom_prd_attrb[] = $attribute_pair['name'];
					}
				}
			}
		}

		if ( $addedMetaKeys && 0 < count( $addedMetaKeys ) ) {
			foreach ( $addedMetaKeys as $metaKey ) {
				$attrOptions[ $metaKey ] = $metaKey;
			}
		}

		$attributes = wc_get_attribute_taxonomies();

		if ( ! empty( $attributes ) ) {
			foreach ( $attributes as $attributesObject ) {
				$attrOptions[ 'umb_pattr_' . $attributesObject->attribute_name ] = $attributesObject->attribute_label;
			}
		}

		/* select dropdown setup */
		ob_start();
		$selectDropdownHTML .= '<option value=""> -- select -- </option>';

		if ( is_array( $attrOptions ) ) {
			$selectDropdownHTML .= '<optgroup label="Global Attributes">';
			foreach ( $attrOptions as $attrKey => $attrName ) {
				$selected = '';
				if ( $selected_value2 == $attrKey ) {
					$selected = 'selected';
				}
				$selectDropdownHTML .= '<option ' . $selected . ' value="' . $attrKey . '">' . $attrName . '</option>';
			}
		}

		if ( ! empty( $custom_prd_attrb ) ) {
			$custom_prd_attrb    = array_unique( $custom_prd_attrb );
			$selectDropdownHTML .= '<optgroup label="Custom Attributes">';

			foreach ( $custom_prd_attrb as $key5 => $custom_attrb ) {
				$selected = '';
				if ( 'ced_cstm_attrb_' . esc_attr( $custom_attrb ) == $selected_value2 ) {
					$selected = 'selected';
				}
				$selectDropdownHTML .= '<option ' . $selected . ' value="ced_cstm_attrb_' . esc_attr( $custom_attrb ) . '">' . esc_html( $custom_attrb ) . '</option>';

			}
		}

		if ( ! empty( $post_meta_keys ) ) {
			$post_meta_keys      = array_unique( $post_meta_keys );
			$selectDropdownHTML .= '<optgroup label="Custom Fields">';
			foreach ( $post_meta_keys as $key7 => $p_meta_key ) {

				/** To check valid keys */
				if (
					strpos($p_meta_key, '_post') === false &&
					strpos($p_meta_key, '_share') === false &&
					strpos($p_meta_key, 'oembed') === false &&
					strpos($p_meta_key, 'fb_') === false &&

					strpos($p_meta_key, '_rex_') === false &&
					strpos($p_meta_key, '_results_') === false &&
					strpos($p_meta_key, '_sticky_') === false &&
					strpos($p_meta_key, '_any_') === false &&
					strpos($p_meta_key, '_wpml_') === false &&
					strpos($p_meta_key, '_elementor_') === false &&
					strpos($p_meta_key, 'oembed') === false &&
					strpos($p_meta_key, 'oembed') === false 

				) {
					$selected            = ( $selected_value2 == $p_meta_key ) ? 'selected' : '';
					$selectDropdownHTML .= '<option ' . $selected . ' value="' . $p_meta_key . '">' . $p_meta_key . '</option>';
				}

				// $selected = '';
				// if ( $selected_value2 == $p_meta_key ) {
				// 	$selected = 'selected';
				// }
				// $selectDropdownHTML .= '<option ' . $selected . ' value="' . $p_meta_key . '">' . $p_meta_key . '</option>';
			}
		}

		$selectDropdownHTML .= '</select>';

		$rowHtml .= $selectDropdownHTML;
		$rowHtml .= '</td>';
		$rowHtml .= '</tr>';

		return $rowHtml;
	}


	/*
	 *
	 * Function to create sellernext user
	 */
	public function ced_amazon_create_sellernext_user() {

		$check_ajax = check_ajax_referer( 'ced-amazon-ajax-seurity-string', 'ajax_nonce' );
		if ( ! $check_ajax ) {
			return;
		}

		$marketplace_id = isset( $_POST['marketplace_id'] ) ? sanitize_text_field( $_POST['marketplace_id'] ) : false;
		$reAuthorising  = isset( $_POST['reAuthorising'] ) ? sanitize_text_field( $_POST['reAuthorising'] ) : false;
		
		update_option( 'ced_amazon_current_marketplace_id', $marketplace_id );

		$domain = ced_get_navigation_url(
			'amazon',
			array(
				'section' => 'setup-amazon',
			)
		);

		if ( ! empty( $marketplace_id ) ) {

			$amzonCurlRequest = CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php';
			if ( file_exists( $amzonCurlRequest ) ) {
				require_once $amzonCurlRequest;
				$this->amzonCurlRequestInstance = new Ced_Amazon_Curl_Request();
			}

			$ced_amazon_regions_info = array();

			$amzonRegions = CED_AMAZON_DIRPATH . 'admin/partials/amazonRegions.php';
			if ( file_exists( $amzonRegions ) ) {
				require_once $amzonRegions;
			}

			$home_redirect_url_params = array(
				'page'    => 'sales_channel',
				'channel' => 'amazon',
				'section' => 'setup-amazon',
			);

			$params = array(
				'section'   => 'setup-amazon',
				'connected' => 'true',
			);

			if ( 'true' == $reAuthorising || $reAuthorising ) {
				$params['reAuthorising'] = true;
			}

			$home_redirect_url = ced_get_navigation_url( 'amazon', $params );

			$login_query_params = array(
				'region'            => $ced_amazon_regions_info[ $marketplace_id ]['region_value'],
				'country'           => $ced_amazon_regions_info[ $marketplace_id ]['shop-name'],
				'state'             => 'test',
				'marketplace_id'    => $marketplace_id,
				'domain'            => site_url(),
				'marketplace'       => 'amazon',
				'home_redirect_url' => $home_redirect_url
				
			);

			$redirect_url = CED_LIVE_VALIDATOR . 'v1/auth?' . http_build_query( $login_query_params );
			wp_send_json_success( $redirect_url );

		}

		wp_die();
	}


	/*
	 *
	 *Function to fetch next level Category
	 */
	public function my_amazon_cron_schedules( $schedules ) {

		
		$schedules['ced_amazon_8min'] = array(
			'interval' => 8 * 60,
			'display'  => __( 'Once every 8 minutes' ),
		);

		$schedules['ced_amazon_10min'] = array(
			'interval' => 10 * 60,
			'display'  => __( 'Once every 10 minutes' ),
		);

		$schedules['ced_amazon_11min'] = array(
			'interval' => 11 * 60,
			'display'  => __( 'Once every 11 minutes' ),
		);

		$schedules['ced_amazon_12min'] = array(
			'interval' => 12 * 60,
			'display'  => __( 'Once every 12 minutes' ),
		);

		$schedules['ced_amazon_15min'] = array(
			'interval' => 15 * 60,
			'display'  => __( 'Once every 15 minutes' ),
		);

		$schedules['ced_amazon_20min'] = array(
			'interval' => 20 * 60,
			'display'  => __( 'Once every 20 minutes' ),
		);

		$schedules['ced_amazon_30min'] = array(
			'interval' => 30 * 60,
			'display'  => __( 'Once every 30 minutes' ),
		);

		$schedules['ced_amazon_hourly'] = array(
			'interval' => 60 * 60,
			'display'  => __( 'Once every 60 minutes' ),
		);

		$schedules['ced_amazon_daily'] = array(
			'interval' => 24 * 60 * 60,
			'display'  => __( 'Once every day' ),
		);

		$schedules['ced_amazon_twicedaily'] = array(
			'interval' => 12 * 60 * 60,
			'display'  => __( 'Twice daily' ),
		);

		return $schedules;
	}

	/*
	 *
	 * Function to update wizard step
	 */
	public function ced_amazon_update_current_step() {
		$check_ajax = check_ajax_referer( 'ced-amazon-ajax-seurity-string', 'ajax_nonce' );
		if ( ! $check_ajax ) {
			return;
		}

		$current_step = isset( $_POST['current_step'] ) ? sanitize_text_field( $_POST['current_step'] ) : false;
		$user_id      = isset( $_POST['user_id'] ) ? sanitize_text_field( $_POST['user_id'] ) : false;

		if ( ! empty( $current_step ) && ! empty( $user_id ) ) {

			$sellernextShopIds                                     = get_option( 'ced_amazon_remote_shop_ids', array() );
			$sellernextShopIds[ $user_id ]['ced_amz_current_step'] = $current_step;

			update_option( 'ced_amazon_remote_shop_ids', $sellernextShopIds );

			return wp_json_encode(
				array(
					'message' => 'updated',
					'status'  => '200',
				)
			);
		} else {
			return wp_json_encode(
				array(
					'message' => 'failed',
					'status'  => '400',
				)
			);
		}
	}

	/*
	 *
	 * Function to get orders.
	 */
	public function ced_amazon_get_orders() {

		$check_ajax = check_ajax_referer( 'ced-amazon-ajax-seurity-string', 'ajax_nonce' );
		if ( ! $check_ajax ) {
			return;
		}

		$seller_id      = isset( $_POST['seller_id'] ) ? sanitize_text_field( $_POST['seller_id'] ) : '';
		$amz_order_id   = isset( $_POST['amz_order_id'] ) ? sanitize_text_field( $_POST['amz_order_id'] ) : '';
		$created_after  = isset( $_POST['created_after'] ) ? sanitize_text_field( $_POST['created_after'] ) : '';
		$created_before = isset( $_POST['created_before'] ) ? sanitize_text_field( $_POST['created_before'] ) : '';
		
		$params = array(
			'amz_order_id'   => $amz_order_id,
			'created_after'  => $created_after,
			'created_before' => $created_before,
		);

		$mplocation = '';
		if ( ! empty( $seller_id ) ) {
			$mplocation_arr = explode( '|', $seller_id );
			$mplocation     = isset( $mplocation_arr[1] ) ? $mplocation_arr[0] : '';
		}

		$file_name = plugin_dir_path( __FILE__ ) . 'amazon/lib/class-order-manager.php';
		if ( file_exists( $file_name ) ) {

			require_once $file_name;
			$class_name = 'Ced_Umb_Amazon_Order_Manager';
			if ( class_exists( $class_name ) ) {

				$OrderInstance = Ced_Umb_Amazon_Order_Manager::get_instance();
				$order_id      = $OrderInstance->is_umb_order_exists( $amz_order_id );
				
				if ( ! is_wp_error( $OrderInstance ) ) {

					$params['mplocation'] =  $mplocation;
					$params['cron']       =  false;
					$params['seller_id']  =  $seller_id;

					$response = $OrderInstance->fetchOrders( $params );
					
					if ( 'completed' == $response['status'] && 0 < count( $response['orders_imported'] ) ) {
						$message = __( 'Orders have been fetched successfully. Please reload the page to view your new orders.', 'amazon-for-woocommerce' );
						$status  = 'success';
						$classes = 'success is-dismissable';
						
					} elseif ( 'completed' == $response['status'] && 0 == count( $response['orders_imported'] ) ) {
						$message = __( 'No orders found to import!', 'amazon-for-woocommerce' );
						$classes = 'error is-dismissable';
						$status  = 'success';
						
					} else {
						$message = __( 'Error while importing orders', 'amazon-for-woocommerce' );
						$classes = 'error is-dismissable';
						$status  = 'error';
					}

					wp_send_json_success(
						array(
							'message' => $message,
							'status'  => $status,
							'class'   => $classes
						)
					);

					wp_die();

				} else {
					$message = __( 'An unexpected error occurred. Please try again.', 'amazon-for-woocommerce' );
					$classes = 'notice notice-error is-dismissable';
					$status  = 'error';

				}

			} else {
				$message = __( 'Class missing to perform operation, please check if extension configured successfully!', 'amazon-for-woocommerce' );
				$classes = 'error is-dismissable';
				$status  = 'error';
				
			}
		} else {
			$message = __( 'Please check if selected marketplace is active!', 'amazon-for-woocommerce' );
			$classes = 'error is-dismissable';
			$status  = 'error';
			
		}	

		wp_send_json_success(
			array(
				'message' => $message,
				'status'  => $status,
				'class'   => $classes
			)
		);
		wp_die();
	}


	public function ced_amz_fetch_next_page_orders( $next_token, $mplocation, $seller_id ) {

		$logger  = wc_get_logger();
		$context = array( 'source' => 'ced_amazon_order_fetch' );
		$logger->info( 'ced amzon next page order function called', $context );

		$params['mplocation'] =  $mplocation;
		$params['cron']       =  false;
		$params['seller_id']  =  $seller_id;
		$params['next_token'] =  $next_token;

		$this->order_manager->fetchOrders( $params );

	}


	public function ced_amazon_remove_account_from_integration() {
		$check_ajax = check_ajax_referer( 'ced-amazon-ajax-seurity-string', 'ajax_nonce' );
		if ( $check_ajax ) {

			$seller_id        = isset( $_POST['seller_id'] ) ? sanitize_text_field( $_POST['seller_id'] ) : false;
			$sellernextShopId = isset( $_POST['sellernextShopId'] ) ? sanitize_text_field( $_POST['sellernextShopId'] ) : false;

			if ( ! empty( $seller_id ) && ! empty( $sellernextShopId ) ) {

				$ced_amazon_remote_shop_ids = get_option( 'ced_amazon_remote_shop_ids', array() );
				unset( $ced_amazon_remote_shop_ids[ $sellernextShopId ] );
				update_option( 'ced_amazon_remote_shop_ids', $ced_amazon_remote_shop_ids );

				$ced_amazon_sellernext_shop_ids = get_option( 'ced_amazon_sellernext_shop_ids', array() );
				unset( $ced_amazon_sellernext_shop_ids[ $sellernextShopId ] );
				update_option( 'ced_amazon_sellernext_shop_ids', $ced_amazon_sellernext_shop_ids );

				update_option( 'ced_amazon_mode_of_operation', '' );

				if ( function_exists( 'as_has_scheduled_action' ) ) {

					if ( as_has_scheduled_action( 'ced_amazon_price_scheduler_job_' . $seller_id ) ) {
						as_unschedule_all_actions( 'ced_amazon_price_scheduler_job_' . $seller_id );
					}

					if ( as_has_scheduled_action( 'ced_amazon_inventory_scheduler_job_' . $seller_id ) ) {
						as_unschedule_all_actions( 'ced_amazon_inventory_scheduler_job_' . $seller_id );
					}

					if ( as_has_scheduled_action( 'ced_amazon_order_scheduler_job_' . $seller_id ) ) {
						as_unschedule_all_actions( 'ced_amazon_order_scheduler_job_' . $seller_id );
					}

					if ( as_has_scheduled_action( 'ced_amazon_existing_products_sync_job_' . $seller_id ) ) {
						as_unschedule_all_actions( 'ced_amazon_existing_products_sync_job_' . $seller_id );
					}

					if ( as_has_scheduled_action( 'ced_amazon_catalog_asin_sync_job_' . $seller_id ) ) {
						as_unschedule_all_actions( 'ced_amazon_catalog_asin_sync_job_' . $seller_id );
					}
				}

				$amazon_accounts = get_option( 'ced_amzon_configuration_validated', array() );
				if ( is_array( $amazon_accounts ) && isset( $amazon_accounts[ $seller_id ] ) ) {

					unset( $amazon_accounts[ $seller_id ] );
					update_option( 'ced_amzon_configuration_validated', $amazon_accounts );
				}

				// Delete account participation option value
				wp_send_json(
					array(
						'status'  => 'success',
						'message' => 'Account Deleted Successfully',
						'title'   => 'Account Deleted',
					)
				);

			} elseif ( ! empty( $sellernextShopId ) ) {

				$ced_amazon_remote_shop_ids = get_option( 'ced_amazon_remote_shop_ids', array() );
				unset( $ced_amazon_remote_shop_ids[ $sellernextShopId ] );
				update_option( 'ced_amazon_remote_shop_ids', $ced_amazon_remote_shop_ids );

				$ced_amazon_sellernext_shop_ids = get_option( 'ced_amazon_sellernext_shop_ids', array() );
				unset( $ced_amazon_sellernext_shop_ids[ $sellernextShopId ] );
				update_option( 'ced_amazon_sellernext_shop_ids', $ced_amazon_sellernext_shop_ids );

				wp_send_json(
					array(
						'status'  => 'success',
						'message' => 'Account Deleted Successfully',
						'title'   => 'Account Deleted',
					)
				);

			} else {
				wp_send_json(
					array(
						'status'  => 'error',
						'message' => 'User ID not found',
						'title'   => 'Invalid User ID',
					)
				);
			}
		}
	}


	/**
	 * Function to verify seller
	 */
	public function ced_amazon_seller_verification() {
		$check_ajax = check_ajax_referer( 'ced-amazon-ajax-seurity-string', 'ajax_nonce' );
		if ( ! $check_ajax ) {
			return;
		}

		$user_id           = isset( $_POST['user_id'] ) ? sanitize_text_field( $_POST['user_id'] ) : '';
		$seller_id         = isset( $_POST['seller_id'] ) ? sanitize_text_field( $_POST['seller_id'] ) : '';
		$sellernextShopIds = get_option( 'ced_amazon_remote_shop_ids', array() );

		$config_array_key = $seller_id;


		if ( ! empty( $user_id ) && ! empty( $seller_id ) ) {

			$amzonCurlRequest = CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php';
			if ( file_exists( $amzonCurlRequest ) ) {
				require_once $amzonCurlRequest;
				$this->amzonCurlRequestInstance = new Ced_Amazon_Curl_Request();
			} else {

				wp_send_json(
					array(
						'success' => false,
						'message' => 'Curl Request file doesn\'t exits',
					)
				);
			}

			$payload         = array();
			$originalPayload = array();

			$originalPayload = $this->amzonCurlRequestInstance->getMarketplaceParticipations( $user_id );

			if ( $originalPayload['success'] ) {
				$payload = isset( $originalPayload['response'] ) && isset( $originalPayload['response']['payload'] ) ? $originalPayload['response']['payload'] : array();
			} else {
				wp_send_json( $originalPayload );
			}

			$seller_id_array = explode( '|', $seller_id );
			$mp_location     = isset( $seller_id_array[0] ) ? $seller_id_array[0] : '';
			$merchant_id     = isset( $seller_id_array[1] ) ? $seller_id_array[1] : '';

			$marketplace_id = ced_get_marketplace_id_by_country( $mp_location );

			$accountData          = array();
			$sellerParticipation  = false;
			$participate_accounts = array();

			if ( ! empty( $payload ) && is_array( $payload ) && isset( $originalPayload['success'] ) && $originalPayload['success'] ) {
				foreach ( $payload as $index => $accountsConnected ) {
					if ( $accountsConnected['marketplace']['id'] == $marketplace_id ) {
						$accountData              = $accountsConnected;
						$sellerParticipation      = isset( $accountsConnected['participation']['isParticipating'] ) ? $accountsConnected['participation']['isParticipating'] : false;
						$current_mp_participation = array( $config_array_key => $sellerParticipation );
						if ( is_array( $participate_accounts ) && ! empty( $participate_accounts ) ) {
							$participate_accounts = array_replace( $participate_accounts, $current_mp_participation );
						} else {
							$participate_accounts = $current_mp_participation;
						}
						$sellernextShopIds[ $user_id ]['marketplaces_participation'] = $participate_accounts;
						$sellernextShopIds[ $user_id ]['hasSuspendedListings']       = isset( $accountsConnected['participation']['hasSuspendedListings'] ) ? $accountsConnected['participation']['hasSuspendedListings'] : false;
						update_option( 'ced_amazon_remote_shop_ids', $sellernextShopIds );

						if ( $sellernextShopIds[ $user_id ]['hasSuspendedListings'] ) {
							set_transient( 'ced_amz_suspended_listings_' . $user_id, true, 30 );
						}
						wp_send_json(
							array(
								'success' => true,
								'data'    => array(
									'seller_id'      => $seller_id,
									'marketplace_id' => $marketplace_id,
									'user_id'        => $user_id,

								),
							)
						);
					}
				}
			} else {
				$ced_amzon_configuration_validated = get_option( 'ced_amzon_configuration_validated', array() );
				$sellernextShopIds                 = get_option( 'ced_amazon_remote_shop_ids', array() );
				$ced_mp_seller_key                 = '';

				if ( ! empty( $user_id ) && isset( $sellernextShopIds[ $user_id ] ) ) {
					$ced_mp_seller_key = isset( $sellernextShopIds[ $user_id ] ) && isset( $sellernextShopIds[ $user_id ]['ced_mp_seller_key'] ) ? $sellernextShopIds[ $user_id ]['ced_mp_seller_key'] : '';
					if ( isset( $ced_mp_seller_key ) && ! empty( $ced_mp_seller_key ) ) {
						unset( $ced_amzon_configuration_validated[ $ced_mp_seller_key ] );
						update_option( 'ced_amzon_configuration_validated', $ced_amzon_configuration_validated );
					}
				}

				unset( $sellernextShopIds[ $user_id ] );
				update_option( 'ced_amazon_remote_shop_ids', $sellernextShopIds );

				wp_send_json(
					array(
						'success' => false,
						'message' => 'unable to verify marketplace/seller id',
					)
				);
			}

			die;
		} else {
			wp_send_json(
				array(
					'success' => false,
					'message' => 'User Id or Seller Id is missing',
				)
			);
		}

	}



	public function ced_amazon_add_custom_profile_rows() {

		$check_ajax = check_ajax_referer( 'ced-amazon-ajax-seurity-string', 'ajax_nonce' );
		if ( ! $check_ajax ) {
			return;
		}

		$sanitized_array = filter_input_array( INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$custom_field    = isset( $sanitized_array['custom_field'] ) ? $sanitized_array['custom_field'] : array();
		$category_id     = isset( $_POST['primary_cat'] ) ? sanitize_text_field( $_POST['primary_cat'] ) : '';
		$sub_category_id = isset( $_POST['secondary_cat'] ) ? sanitize_text_field( $_POST['secondary_cat'] ) : '';

		$user_id   = isset( $_POST['userid'] ) ? sanitize_text_field( $_POST['userid'] ) : '';
		$seller_id = '';

		$file_url = isset( $_POST['fileUrl'] ) ? sanitize_text_field( $_POST['fileUrl'] ) : '';

		if ( empty( $seller_id ) ) {
			$ced_amazon_remote_shop_ids = get_option( 'ced_amazon_remote_shop_ids', array() );
			$seller_id                  = isset( $ced_amazon_remote_shop_ids[ $user_id ] ) ? $ced_amazon_remote_shop_ids[ $user_id ]['ced_mp_seller_key'] : '';

		}

		$this->ced_amazon_profile_dropdown( '', '', $sanitized_array, $custom_field, $category_id, $sub_category_id, 'yes', $user_id, $seller_id, $file_url );

		wp_die();
	}

	public function ced_amazon_profile_dropdown( $field_id = '', $required = '', $sanitized_array = array(), $custom_field = array(), $category_id = '', $sub_category_id = '', $display_hidden = 'no', $user_id = '', $seller_id = '', $file_url = '' ) {

		global $wpdb;
		$results = $wpdb->get_results( "SELECT DISTINCT meta_key FROM {$wpdb->prefix}postmeta", 'ARRAY_A' );
		$query   = $wpdb->get_results( $wpdb->prepare( "SELECT `meta_value` FROM  {$wpdb->prefix}postmeta WHERE `meta_key` LIKE %s", '_product_attributes' ), 'ARRAY_A' );

		$addedMetaKeys = get_option( 'CedUmbProfileSelectedMetaKeys', false );

		$row_html         = '';
		$mod_custom_field = array_values( $custom_field );

		foreach ( $mod_custom_field[0] as $custom_key => $custom_value ) {

			$index = strpos( $custom_key, '_custom_field' );
			if ( $index > -1 ) {
				$slug = substr( $custom_key, 0, $index );
			} else {
				$slug = $custom_key;
			}

			$optionLabel = $custom_value['label'];

			$row_html .= '<tr class="categoryAttributes" id="ced_amazon_categories" >
			<td class="ced_template_labels" >
			<label for="" class="">' . $optionLabel . ' (' . $slug . ') ';

			$row_html .= wc_help_tip( $custom_value['definition'], 'amazon-for-woocommerce' );
			$row_html .= '</label>';
			$row_html .= '</td>';

			$ced_amzon_configuration_validated = get_option( 'ced_amzon_configuration_validated', array() );

			if ( empty( $seller_id ) ) {
				$ced_amazon_remote_shop_ids = get_option( 'ced_amazon_remote_shop_ids', array() );
				$seller_id                  = isset( $ced_amazon_remote_shop_ids[ $user_id ] ) ? $ced_amazon_remote_shop_ids[ $user_id ]['ced_mp_seller_key'] : '';

			}

			$userData    = isset( $ced_amzon_configuration_validated[ $seller_id ] ) ? $ced_amzon_configuration_validated[ $seller_id ] : array();
			$userCountry = isset( $userData['ced_mp_name'] ) ? $userData['ced_mp_name'] : '';

			$upload_dir = wp_upload_dir();

			$valid_values2 = array();

			if ( empty( $file_url ) ) {

				$valid_values_file = $upload_dir['basedir'] . '/ced-amazon/templates/' . $userCountry . '/' . $category_id . '/valid_values.json';
				$valid_values      = file_get_contents( $valid_values_file );
				$valid_values2     = json_decode( $valid_values, true );

			}

			if ( ( isset( $valid_values2[ $custom_key ] ) && isset( $valid_values2[ $custom_key ][ $sub_category_id ] ) ) || ( isset( $valid_values2[ $optionLabel ] ) && isset( $valid_values2[ $optionLabel ][ $sub_category_id ] ) ) ) {

				$row_html    .= '<td><select class="custom_category_attributes_select2" id="' . $custom_key . '"  name="ced_amazon_profile_data[' . $custom_key . '][default]" ><option value="">--Select--</option>';
				$optionValues = ! empty( $valid_values2[ $custom_key ][ $sub_category_id ] ) ? $valid_values2[ $custom_key ][ $sub_category_id ] : $valid_values2[ $optionLabel ][ $sub_category_id ];

				foreach ( $optionValues as $acpt_key => $acpt_value ) {
					$selected = '';

					$row_html .= '<option value="' . $acpt_key . '">' . $acpt_value . '</option>';
				}

				$row_html .= '</select></td>';

			} elseif ( ( isset( $valid_values2[ $custom_key ] ) && isset( $valid_values2[ $custom_key ]['all_cat'] ) ) || ( isset( $valid_values2[ $optionLabel ] ) && isset( $valid_values2[ $optionLabel ]['all_cat'] ) ) ) {

				$row_html    .= '<td><select class="custom_category_attributes_select2" id="' . $custom_key . '"  name="ced_amazon_profile_data[' . $custom_key . '][default]" ><option value="">--Select--</option>';
				$optionValues = ! empty( $valid_values2[ $custom_key ]['all_cat'] ) ? $valid_values2[ $custom_key ]['all_cat'] : $valid_values2[ $optionLabel ]['all_cat'];

				foreach ( $optionValues as $acpt_key => $acpt_value ) {
					$selected = '';

					$row_html .= '<option value="' . $acpt_key . '">' . $acpt_value . '</option>';
				}

				$row_html .= '</select></td>';

			} else {

				$row_html .= '<td>';

				if ( 'yes' == $display_hidden ) {
					$row_html .= '<input type="hidden" name="ced_amazon_profile_data[ref_attribute_list][' . $custom_key . ']">';
				} else {
					$row_html .= '<input type="hidden" name="ced_amazon_profile_data[' . $custom_key . '][label]" value="' . $optionLabel . '" >';
				}

				$row_html .= '<input class="custom_category_attributes_input" value="" id="' . $custom_key . '" type="text" name="ced_amazon_profile_data[' . $custom_key . '][default]" >
				</td>';

			}

			$row_html .= '<td>';

			$selectDropdownHTML = '<select class="select2 custom_category_attributes_select"  name="ced_amazon_profile_data[' . esc_attr( $custom_key ) . '][metakey]" >';
			foreach ( $results as $key2 => $meta_key ) {
				$post_meta_keys[] = $meta_key['meta_key'];
			}

			$custom_prd_attrb = array();
			$attrOptions      = array();

			if ( ! empty( $query ) ) {
				foreach ( $query as $key3 => $db_attribute_pair ) {
					foreach ( maybe_unserialize( $db_attribute_pair['meta_value'] ) as $key4 => $attribute_pair ) {
						if ( 1 != $attribute_pair['is_taxonomy'] ) {
							$custom_prd_attrb[] = $attribute_pair['name'];
						}
					}
				}
			}

			if ( $addedMetaKeys && count( $addedMetaKeys ) > 0 ) {
				foreach ( $addedMetaKeys as $metaKey ) {
					$attrOptions[ $metaKey ] = $metaKey;
				}
			}

			$attributes = wc_get_attribute_taxonomies();
			if ( ! empty( $attributes ) ) {
				foreach ( $attributes as $attributesObject ) {
					$attrOptions[ 'umb_pattr_' . $attributesObject->attribute_name ] = $attributesObject->attribute_label;
				}
			}

			/* select dropdown setup */
			ob_start();
			$fieldID             = '{{*fieldID}}';
			$selectId            = $fieldID . '_attibuteMeta';
			$selectDropdownHTML .= '<option value=""> -- select -- </option>';
			if ( is_array( $attrOptions ) ) {
				$selectDropdownHTML .= '<optgroup label="Global Attributes">';
				foreach ( $attrOptions as $attrKey => $attrName ) {
					$selected            = '';
					$selectDropdownHTML .= '<option ' . $selected . ' value="' . $attrKey . '">' . $attrName . '</option>';
				}
			}

			if ( ! empty( $custom_prd_attrb ) ) {
				$custom_prd_attrb    = array_unique( $custom_prd_attrb );
				$selectDropdownHTML .= '<optgroup label="Custom Attributes">';

				foreach ( $custom_prd_attrb as $key5 => $custom_attrb ) {
					$selected            = '';
					$selectDropdownHTML .= '<option ' . $selected . ' value="ced_cstm_attrb_' . esc_attr( $custom_attrb ) . '">' . esc_html( $custom_attrb ) . '</option>';

				}
			}

			if ( ! empty( $post_meta_keys ) ) {
				$post_meta_keys      = array_unique( $post_meta_keys );
				$selectDropdownHTML .= '<optgroup label="Custom Fields">';
				foreach ( $post_meta_keys as $key7 => $p_meta_key ) {
					$selected            = '';
					$selectDropdownHTML .= '<option ' . $selected . ' value="' . $p_meta_key . '">' . $p_meta_key . '</option>';
				}
			}

			$selectDropdownHTML .= '</select>';
			$row_html           .= $selectDropdownHTML;

			$row_html .= '</td></tr>';

			echo wp_json_encode(
				array(
					'succes' => true,
					'data'   => $row_html,
				)
			);

			wp_die();

		}
	}


	public function ced_amazon_update_template() {
		$check_ajax = check_ajax_referer( 'ced-amazon-ajax-seurity-string', 'ajax_nonce' );
		if ( ! $check_ajax ) {
			return;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( WP_Filesystem() ) {
			global $wp_filesystem;
		}

		$next_level = 4;

		$category_id     = isset( $_POST['primary_cat'] ) ? sanitize_text_field( $_POST['primary_cat'] ) : '';
		$sub_category_id = isset( $_POST['secondary_cat'] ) ? sanitize_text_field( $_POST['secondary_cat'] ) : '';
		$browse_nodes    = isset( $_POST['browse_nodes'] ) ? sanitize_text_field( $_POST['browse_nodes'] ) : '';

		$seller_id = isset( $_POST['seller_id'] ) ? sanitize_text_field( $_POST['seller_id'] ) : '';
		$user_id   = isset( $_POST['user_id'] ) ? sanitize_text_field( $_POST['user_id'] ) : '';

		$ced_amzon_configuration_validated = get_option( 'ced_amzon_configuration_validated', array() );

		if ( empty( $seller_id ) ) {
			$ced_amazon_remote_shop_ids = get_option( 'ced_amazon_remote_shop_ids', array() );
			$seller_id                  = isset( $ced_amazon_remote_shop_ids[ $user_id ] ) ? $ced_amazon_remote_shop_ids[ $user_id ]['ced_mp_seller_key'] : '';

		}

		$userData       = $ced_amzon_configuration_validated[ $seller_id ];
		$userCountry    = $userData['ced_mp_name'];
		$marketplace_id = $userData['marketplace_id'];

		$amzonCurlRequest = CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php';

		if ( file_exists( $amzonCurlRequest ) ) {
			require_once $amzonCurlRequest;
			$this->amzonCurlRequestInstance = new Ced_Amazon_Curl_Request();
		} else {
			return;
		}

		if ( empty( $user_id ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => 'Invalid Shop Id',
					'status'  => 'error',
				)
			);
			die;
		}

		$url_array = array(
			4 => array(
				'url' => 'webapi/rest/v1/category-attribute/?category_id=' . $category_id . '&sub_category_id=' . $sub_category_id . '&browse_node_id=' . $browse_nodes . '&barcode_exemption=false',
				'key' => 'category_attributes',
			),
		);

		$upload_dir = wp_upload_dir();

		$dirname  = $upload_dir['basedir'] . '/ced-amazon/templates/' . $userCountry . '/' . $category_id . '/' . $sub_category_id;
		$fileName =  '/products.json';

		$filePath = $dirname . $fileName;
		$filePath = sanitize_file_name( $filePath );

		if ( ! is_dir( $dirname ) ) {
			wp_mkdir_p( $dirname );
		}

		$amazon_profile_data_response         = $this->amzonCurlRequestInstance->ced_amazon_get_category( $url_array[ $next_level ]['url'], $user_id, $seller_id );
		$decoded_amazon_profile_data_response = json_decode( $amazon_profile_data_response, true );

		if ( $decoded_amazon_profile_data_response['status'] ) {
			$amazon_profile_data = $decoded_amazon_profile_data_response['data'];
		} else {
			echo esc_attr( wp_send_json( $decoded_amazon_profile_data_response ) );
			die;
		}

		$amazon_profile_template = isset( $amazon_profile_data['response'] ) ? $amazon_profile_data['response'] : array();

		// Update product flat file template stricture json file
		$this->amzonCurlRequestInstance->fetchProductTemplate( $category_id, $userCountry, $seller_id, $marketplace_id, $user_id );

		if ( empty( $amazon_profile_template ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => 'Unable to fetch Data.',
					'status'  => 'error',
				)
			);
			die;
		}

		$amazon_profile_template_data = wp_json_encode( $amazon_profile_template );
		$amazon_profile_template_data = sanitize_text_field( $amazon_profile_template_data );

		if ( ! file_exists( $filePath ) && WP_Filesystem() ) {
			if ( $wp_filesystem ) {
				$wp_filesystem->put_contents( $filePath, $amazon_profile_template_data, FS_CHMOD_FILE );
			}
		}

		echo wp_json_encode(
			array(
				'success' => true,
				'message' => 'Product Template has been updated',
				'status'  => 'success',
			)
		);

		die;

	}



	/** Get product id based on Amazon Seller SKU field of product level (if product SKU not exist) **/
	public function get_amazon_seller_sku( $meta_key = '', $meta_value = '' ) {

		if ( empty( $meta_key ) || empty( $meta_value ) ) {
			return;
		}

		global $wpdb;
		$productId = $wpdb->get_var( $wpdb->prepare( " SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = %s AND meta_value = %s", $meta_key, $meta_value ) );

		return $productId;
	}


	/**
	 * Quick view feed response using modal in feeds table.
	 *
	 * @name ced_amazon_view_feed_response()
	 * @since 1.0.0
	 */
	public function ced_amazon_view_feed_response() {
		$check_ajax = check_ajax_referer( 'ced-amazon-ajax-seurity-string', 'ajax_nonce' );
		if ( ! $check_ajax ) {
			return;
		}

		require_once CED_AMAZON_DIRPATH . 'admin/amazon/lib/class-feed-manager.php';

		$feed_id   = isset( $_POST['feed_id'] ) ? sanitize_text_field( $_POST['feed_id'] ) : '';
		$seller_id = isset( $_POST['seller_id'] ) ? sanitize_text_field( $_POST['seller_id'] ) : '';
		
		if ( empty( $feed_id ) || empty( $seller_id ) ) {
			$html_response = '<h6>Error: Feed id or seller id missing!</h6>';
			wp_send_json_success( $html_response );
			wp_die();
		}

		global $wpdb;
		$tableName        = $wpdb->prefix . 'ced_amazon_feeds';
		$feed_request_ids = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ced_amazon_feeds WHERE `feed_id` = %d", $feed_id ), 'ARRAY_A' );

		if ( ! is_array( $feed_request_ids ) || ! is_array( $feed_request_ids[0] ) ) {
			$html_response = '<h6>Error: Feed details not found!</h6>';
			wp_send_json_success( $html_response );
			wp_die();
		}

		$feed_request_id = $feed_request_ids[0];
		$main_id         = $feed_request_id['id'];
		$feed_type       = $feed_request_id['feed_action'];
		$location_id     = $feed_request_id['feed_location'];
		$response        = $feed_request_id['response'];
		$response        = json_decode( $response, true );
		
		$response_format = false;
		if ( ! empty( $feed_id ) ) {

			if ( isset( $response['status'] ) && 'DONE' == $response['status'] ) {
				$response_format = true;
			} else {
				$feed_manager = Ced_Umb_Amazon_Feed_Manager::get_instance( );
				$response     = $feed_manager->getFeedItemsStatusSpApi( $feed_id, $feed_type, $location_id, $seller_id );

				if ( isset( $response['status'] ) && 'DONE' == $response['status'] ) {
					$response_format = true;
				}
				$response_data = wp_json_encode( $response );
				$wpdb->update( $tableName, array( 'response' => $response_data ), array( 'id' => $main_id ) );
			}

			if ( $response_format ) {

				if ( 'POST_FLAT_FILE_LISTINGS_DATA' == $feed_type ) {

					$tab_response_data = explode( "\n", $response['body'] );

					$first_row_data         = explode( "\t", $tab_response_data[0] );
					$second_row_data        = explode( "\t", $tab_response_data[1] );
					$third_row_data         = explode( "\t", $tab_response_data[2] );
					$response_heading       = isset( $first_row_data[0] ) ? $first_row_data[0] : '';
					$processed_record_lable = isset( $second_row_data[1] ) ? $second_row_data[1] : '';
					$processed_record_value = isset( $second_row_data[3] ) ? $second_row_data[3] : '';
					$success_record_lable   = isset( $third_row_data[1] ) ? $third_row_data[1] : '';
					$success_record_value   = isset( $third_row_data[3] ) ? $third_row_data[3] : '';

					$tab_response_html  = '';
					$tab_error_code_arr = array();
					foreach ( $tab_response_data as $tabKey => $tabValue ) {

						$line_data = explode( "\t", $tabValue );
						if ( 'Feed Processing Summary' == $line_data[0] || 'Feed Processing Summary:' == $line_data[0] ) {
							continue;
						} elseif ( empty( $line_data[0] ) || '' == $line_data[0] ) {
							continue;
						} elseif ( 'original-record-number' == $line_data[0] ) {
							continue;
						} elseif ( in_array( $line_data[2], $tab_error_code_arr ) ) {
							continue;
						} elseif ( ! empty( $line_data[2] ) ) {
							$tab_error_code_arr[] = $line_data[2];
							$tab_response_html   .= '<tr><td>' . esc_attr( $line_data[2] ) . '</td>';
							$tab_response_html   .= '<td >' . esc_attr( $line_data[4] ) . '</td></tr>';
						}
					}

					if ( isset( $tab_response_html ) && '' != $tab_response_html ) {
						$tableHtml = '<table class="wp-list-table widefat striped table-view-list posts" >
						<thead class="table-dark">
						<tr>
						<th scope="col">Error code</th>
						<th scope="col">Error message</th>
						</tr>
						</thead>
						<tbody>';

						$tableHtml .= $tab_response_html;
						$tableHtml .= '</tbody>
						</table>';
					} else {
						$tableHtml = '<p> Successful records: ' . $success_record_value . '</p>';
					}
				} elseif ( strpos( $feed_type, 'JSON_LISTINGS_FEED' ) === 0 ) {

					$feed_response = json_decode( $response['body'], true );

					if ( isset( $feed_response ) && ! empty( $feed_response ) ) {

						$summary_data   = isset( $feed_response['summary'] ) ? $feed_response['summary'] : array();
						$success_record = '';
						if ( ! empty( $summary_data ) ) {
							foreach ( $summary_data as $summary_label => $summary_fields ) {
								if ( 'messagesAccepted' == $summary_label ) {
									$success_record = $summary_fields;
								}
							}
						}

						$error_data          = isset( $feed_response['issues'] ) ? $feed_response['issues'] : array();
						$error_html          = '';
						$json_error_code_arr = array();
						if ( ! empty( $error_data ) ) {
							foreach ( $error_data as $error_label => $error_fields ) {
								$error_code = isset( $error_fields['code'] ) ? $error_fields['code'] : '';
								$message    = isset( $error_fields['message'] ) ? $error_fields['message'] : '';

								if ( in_array( $error_code, $json_error_code_arr ) ) {
									continue;
								}

								if ( isset( $error_code ) && ! empty( $error_code ) ) {
									$json_error_code_arr[] = $error_code;

									$error_html .= '<tr><td>' . esc_attr( $error_code ) . '</td>';
									$error_html .= '<td >' . esc_attr( $message ) . '</td></tr>';
								}
							}
						}

						if ( isset( $error_html ) && '' != $error_html ) {
							$tableHtml = '<table class="wp-list-table widefat striped table-view-list posts" >
							<thead class="table-dark">
							<tr>
							<th scope="col">Error code</th>
							<th scope="col">Error message</th>
							</tr>
							</thead>
							<tbody>';

							$tableHtml .= $error_html;
							$tableHtml .= '</tbody>
							</table>';
						} else {
							$tableHtml = '<h4>Successful records: ' . $success_record . '</h4>';
						}
					} else {
						$tableHtml = $feed_response;
					}
				} else {

					$sxml = simplexml_load_string( $response['body'], 'SimpleXMLElement', LIBXML_NOCDATA );

					$arrayResponse = wp_json_encode( $sxml );
					$arrayResponse = json_decode( $arrayResponse, true );

					if ( isset( $arrayResponse['Message'] ) && ! empty( $arrayResponse['Message'] ) ) {

						$processingSummary     = isset( $arrayResponse['Message'] ) && isset( $arrayResponse['Message']['ProcessingReport'] ) && isset( $arrayResponse['Message']['ProcessingReport']['ProcessingSummary'] ) ? $arrayResponse['Message']['ProcessingReport']['ProcessingSummary'] : array();
						$processingSummaryHtml = '';

						$results     = isset( $arrayResponse['Message']['ProcessingReport']['Result'][0] ) ? $arrayResponse['Message']['ProcessingReport']['Result'] : $arrayResponse['Message']['ProcessingReport'];
						$resultsHtml = '';

						$success_record = '';
						if ( ! empty( $processingSummary ) ) {
							foreach ( $processingSummary as $label => $fields ) {
								$processingSummaryHtml .= $label . ' : ' . $fields . '<br/>';
								if ( 'MessagesSuccessful' == $label ) {
									$success_record = $fields;
								}
							}
						}

						$xml_error_code_arr = array();
						if ( ! empty( $results ) ) {

							foreach ( $results as $label => $fields ) {

								if ( 'Result' == $label || is_numeric( $label ) ) {
									if ( is_object( $fields ) ) {
										$fields = $this->xml2array( $fields );
									}

									$resultMessageCode = isset( $fields['ResultMessageCode'] ) ? $fields['ResultMessageCode'] : '';
									$resultDescription = isset( $fields['ResultDescription'] ) ? $fields['ResultDescription'] : '';

									if ( in_array( $resultMessageCode, $xml_error_code_arr ) ) {
										continue;
									}

									if ( isset( $resultMessageCode ) && ! empty( $resultMessageCode ) ) {
										$xml_error_code_arr[] = $resultMessageCode;
										$resultsHtml         .= '<tr><td>' . esc_attr( $resultMessageCode ) . '</td>';
										$resultsHtml         .= '<td >' . esc_attr( $resultDescription ) . '</td></tr>';
									}
								}
							}
						}

						if ( isset( $resultsHtml ) && '' != $resultsHtml ) {
							$tableHtml = '<table class="wp-list-table widefat striped table-view-list posts" >
							<thead class="table-dark">
							<tr>
							<th scope="col">Error code</th>
							<th scope="col">Error message</th>
							</tr>
							</thead>
							<tbody>';

							$tableHtml .= $resultsHtml;
							$tableHtml .= '</tbody>
							</table>';
						} else {
							$tableHtml = '<h4>Successful records: ' . $success_record . '</h4>';
						}
					}
				}
			} elseif ( isset( $response['feed_id'] ) && ! empty( $response['feed_id'] ) ) {
				$tableHtml = '<table class="wp-list-table widefat striped table-view-list posts" >
				<thead class="table-dark">
				<tr>
				<th scope="col">Feed Id </th>
				<th scope="col">Feed Type</th>
				<th scope="col">Feed Status</th>
				</tr>
				</thead>
				<tbody>
				<tr>
				<td>' . esc_attr( $response['feed_id'] ) . '</td>
				<td>' . esc_attr( $response['feed_action'] ) . '</td>
				<td>' . esc_attr( $response['status'] ) . '</td>
				</tr>

				</tbody>
				</table>';

			} else {
				$message   = isset( $response['body'] ) ? $response['body'] : $response['message'];
				$tableHtml = '<p><b>' . esc_attr( $message ) . '</b></p>';
			}
		}

		// Final html response preparation
		$html_response = $tableHtml;
		wp_send_json_success( $html_response );
		wp_die();
	}

	// Function for XML feed response format
	public function xml2array( $xmlObject, $out = array() ) {
		foreach ( (array) $xmlObject as $index => $node ) {
			$out[ $index ] = ( is_object( $node ) ) ? $this->xml2array( $node ) : $node;
		}

		return $out;
	}


	/**
	 * Add filter in order
	 *
	 * @since    1.0.0
	 */
	public function ced_amazon_add_woo_order_views( $views ) {

		if ( ! current_user_can( 'edit_others_pages' ) ) {
			return $views;
		}

		$class        = ( isset( $_REQUEST['order_from_amazon'] ) && 'yes' == sanitize_text_field( $_REQUEST['order_from_amazon'] ) ) ? 'current' : '';
		$query_string = esc_url_raw( remove_query_arg( array( 'order_from_amazon' ) ) );

		$query_string = add_query_arg( 'order_from_amazon', urlencode( 'yes' ), $query_string );

		$views['amazon_order'] = '<a href="' . $query_string . '" class="' . $class . '">' . __( 'Amazon order', 'amazon-for-woocommerce' ) . '</a>';
		return $views;
	}

	/**
	 * Add filter in order
	 *
	 * @since    1.0.0
	 */
	public function ced_amazon_woo_admin_order_filter_query( $query ) {
		global $typenow, $wp_query, $wpdb;

		if ( 'shop_order' == $typenow ) {

			if ( ! empty( $_GET['order_from_amazon'] ) ) {

				if ( 'yes' == $_GET['order_from_amazon'] ) {

					$query->query_vars['meta_query'][] = array(
						'key'     => 'ced_umb_order_sales_channel',
						'compare' => 'EXISTS',
					);
				}
			}
		}
	}


	/**
	 * Change Amazon Region
	 *
	 * @since    1.0.0
	 */
	public function ced_amazon_change_region() {
		$check_ajax = check_ajax_referer( 'ced-amazon-ajax-seurity-string', 'ajax_nonce' );
		if ( ! $check_ajax ) {
			return;
		}

		$params              = array();
		$params['user_id']   = isset( $_POST['user_id'] ) ? sanitize_text_field( $_POST['user_id'] ) : '';
		$params['seller_id'] = isset( $_POST['seller_id'] ) ? sanitize_text_field( $_POST['seller_id'] ) : '';

		update_option( 'ced_amz_active_marketplace', $params );
		echo wp_json_encode( array( 'success' => true ) );
		die;
		
	}


	public function ced_amazon_clone_template_modal() {

		$check_ajax = check_ajax_referer( 'ced-amazon-ajax-seurity-string', 'ajax_nonce' );
		if ( ! $check_ajax ) {
			return;
		}

		$user_id   = isset( $_POST['user_id'] ) ? sanitize_text_field( $_POST['user_id'] ) : '';
		$seller_id = isset( $_POST['seller_id'] ) ? sanitize_text_field( $_POST['seller_id'] ) : '';

		$template_id = isset( $_POST['template_id'] ) ? trim( sanitize_text_field( $_POST['template_id'] ) ) : '';

		$sanitized_array = filter_input_array( INPUT_POST, FILTER_UNSAFE_RAW );
		$woo_cat         = isset( $sanitized_array['woo_cat'] ) ? ( $sanitized_array['woo_cat'] ) : array();
		$name            = isset( $sanitized_array['name'] ) ? ( $sanitized_array['name'] ) : '';

		global $wpdb;
		$tableName              = $wpdb->prefix . 'ced_amazon_profiles';
		$result                 = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ced_amazon_profiles WHERE `id` = %s ", $template_id ), 'ARRAY_A' );
		$current_amazon_profile = isset( $result[0] ) ? $result[0] : array();

		if ( empty( $current_amazon_profile ) ) {
			wp_send_json_error( 'Unable to fetch selected template . Please try again later.' );
			wp_die();
		}

		$wpdb->insert(
			$tableName,
			array(

				'primary_category'              => isset( $current_amazon_profile['primary_category'] ) ? $current_amazon_profile['primary_category'] : '',
				'secondary_category'            => isset( $current_amazon_profile['secondary_category'] ) ? $current_amazon_profile['secondary_category'] : '',
				'category_attributes_response'  => '',
				'wocoommerce_category'          => isset( $woo_cat ) ? wp_json_encode( $woo_cat ) : '[]',
				'category_attributes_structure' => isset( $current_amazon_profile['category_attributes_structure'] ) ? $current_amazon_profile['category_attributes_structure'] : '',
				'browse_nodes'                  => isset( $current_amazon_profile['browse_nodes'] ) ? $current_amazon_profile['browse_nodes'] : '',
				'browse_nodes_name'             => isset( $current_amazon_profile['browse_nodes_name'] ) ? $current_amazon_profile['browse_nodes_name'] : '',
				'amazon_categories_name'        => isset( $current_amazon_profile['amazon_categories_name'] ) ? $current_amazon_profile['amazon_categories_name'] : '',
				'category_attributes_data'      => isset( $current_amazon_profile['category_attributes_data'] ) ? $current_amazon_profile['category_attributes_data'] : '',
				'seller_id'                     => $seller_id,
				'profile_name'                  => $name,
				'product_type'                  => $current_amazon_profile['product_type'] ?? ''
			),
			array( '%s' )
		);

		$clone_template_id = $wpdb->insert_id;

		if ( $clone_template_id ) {

			$href = ced_get_navigation_url(
				'amazon',
				array(
					'section'     => 'add-new-template',
					'template_id' => $clone_template_id,
					'user_id'     => $user_id,
					'seller_id'   => $seller_id,
					
				)
			);

			$ced_woo_amazon_mapping                                     = get_option( 'ced_woo_amazon_mapping', array() );
			$ced_woo_amazon_mapping[ $seller_id ][ $clone_template_id ] = $woo_cat;
			update_option( 'ced_woo_amazon_mapping', $ced_woo_amazon_mapping );

			$ced_amz_cloned_templates                 = get_option( 'ced_amz_cloned_templates', array() );
			$ced_amz_cloned_templates[ $seller_id ][] = $clone_template_id;
			update_option( 'ced_amz_cloned_templates', $ced_amz_cloned_templates );

			wp_send_json_success( "Template cloned successfully <a href='" . $href . "' target='_blank' >View/Edit Template</a> ." );

		} else {
			wp_send_json_error( 'Unable to clone template . Please try again later.' );

		}

		wp_die();
	}


	public function ced_search_amz_categories() {

		$check_ajax = check_ajax_referer( 'ced-amazon-ajax-seurity-string', 'ajax_nonce' );
		if ( ! $check_ajax ) {
			return;
		}

		$user_id   = isset( $_POST['user_id'] ) ? sanitize_text_field( $_POST['user_id'] ) : '';
		$seller_id = isset( $_POST['seller_id'] ) ? sanitize_text_field( $_POST['seller_id'] ) : '';
		$cat_value = isset( $_POST['cat_value'] ) ? sanitize_text_field( $_POST['cat_value'] ) : '';

		require_once CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php';

		$this->amzonCurlRequestInstance = new Ced_Amazon_Curl_Request();
		$categories_array               = $this->amzonCurlRequestInstance->ced_search_amz_cat( $user_id, $seller_id, $cat_value );

		$html  = '';
		$html .= '<div class="ced-category-search-wrapper"></div><div class="ced-category-mapping">';

		$list             = '';
		$second_category  = '';
		$primary_category = '';

		if ( ! empty( $categories_array ) ) {

			foreach ( $categories_array as $category_array ) {
				$parent_ids = isset( $category_array['parent_id'] ) ? $category_array['parent_id'] : array();
				if ( isset( $parent_ids ) && is_array( $parent_ids ) ) {
					$parent_ids = implode( ',', $parent_ids );
				}

				$full_path        = isset( $category_array['full_path'] ) && is_array( $category_array['full_path'] ) ? implode( ' > ', $category_array['full_path'] ) : '';
				$second_category  = isset( $category_array['category'] ) && isset( $category_array['category']['sub-category'] ) ? $category_array['category']['sub-category'] : '';
				$primary_category = isset( $category_array['category'] ) && isset( $category_array['category']['primary-category'] ) ? $category_array['category']['primary-category'] : '';
				$hasChildren      = isset( $category_array['hasChildren'] ) ? $category_array['hasChildren'] : false;

				$list .= '<li data-category="' . esc_attr( json_encode( $category_array['category'] ) ) . '" class="ced_amazon_selected_srh_cat" data-name="' . esc_attr( $category_array['name'] ) . '" data-browsenodeID = "' . esc_attr( $category_array['browseNodeId'] ) . '" data-children="' . esc_attr( $hasChildren ) . '" data-id="' . esc_attr( $parent_ids ) . '" >' . esc_attr( $full_path ) . '</li>';

			}
		}

		$html .= '<input type="hidden" id="ced-category-header" value="Browse and Select a Category">';
		$html .= '<strong><span id="ced_amazon_cat_header" data-level="1">' . __( 'Browse and Select a Category', 'amazon-for-woocommerce' ) . '</span></strong>';
		$html .= '<ol id="ced_amz_categories_1" class="ced_amz_categories" data-level="1" data-node-value="Browse and Select a Category">';

		$html .= $list;

		$html .= '</ol>';
		$html .= '</div>';
		// $html .= '</div>';

		wp_send_json_success( $html );
	}


	public function ced_amz_email_restriction( $enable = '', $order = array() ) {
		if ( ! is_object( $order ) ) {
			return $enable;
		}

		$seller_id                  = $order->get_meta( 'ced_amazon_order_seller_id' );
		$renderDataOnGlobalSettings = get_option( 'ced_amazon_global_settings', array() );
		$ced_amz_email_nfc          = isset( $renderDataOnGlobalSettings[ $seller_id ] ) && isset( $renderDataOnGlobalSettings[ $seller_id ]['ced_amz_email_nfc'] ) ? $renderDataOnGlobalSettings[ $seller_id ]['ced_amz_email_nfc'] : '';
		$marketplace                = $order->get_meta( '_umb_marketplace' );

		if ( empty( $ced_amz_email_nfc ) && 'Amazon' == $marketplace ) {
			$enable = false;
		}
		return $enable;
	}


	public function ced_amazon_woocommerce_duplicate_product_exclude_meta_filter( $exclude_meta, $existing_meta_keys ) {

		$get_location  = get_option( 'ced_amzon_configuration_validated', array() );
		$location_keys = array_keys( $get_location );

		if ( is_array( $location_keys ) && ! empty( $location_keys ) ) {
			foreach ( $location_keys as $key => $value ) {
				$mplocation_arr = explode( '|', $value );
				$mplocation     = isset( $mplocation_arr[1] ) ? $mplocation_arr[0] : '';
				$exclude_meta[] = 'ced_amazon_already_uploaded_' . $mplocation;
				$exclude_meta[] = 'ced_amazon_product_asin_' . $mplocation;
				$exclude_meta[] = 'ced_amazon_catalog_asin_' . $mplocation;
			}
		}
		return $exclude_meta;
	}


	public function ced_amazon_get_selected_categories() {

		$check_ajax = check_ajax_referer( 'ced-amazon-ajax-seurity-string', 'ajax_nonce' );
		if ( ! $check_ajax ) {
			return;
		}

		$user_id   = isset( $_POST['user_id'] ) ? sanitize_text_field( $_POST['user_id'] ) : '';
		$seller_id = isset( $_POST['seller_id'] ) ? sanitize_text_field( $_POST['seller_id'] ) : '';
		$option    = isset( $_POST['option'] ) ? sanitize_text_field( $_POST['option'] ) : '';

		$amzonCurlRequest = CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php';
		if ( file_exists( $amzonCurlRequest ) ) {
			require_once $amzonCurlRequest;
			$this->amzonCurlRequestInstance = new Ced_Amazon_Curl_Request();

		}

		$cat_topic = 'category-all?&selected=' . $option;
		$cat_data  = array(
			'remote_shop_id' => $user_id,
		);

		$response = $this->amzonCurlRequestInstance->ced_amazon_serverless_process( $cat_topic, $cat_data, 'GET' );

		if ( isset( $response['body'] ) ) {
			$response = json_decode( $response['body'], true );
			$response = isset( $response['response'] ) ? $response['response'] : array();

			wp_send_json_success( $response );
		} else {
			// No response from the API
			wp_send_json_error( 'No response from the API' );
		}
	}


	public function ced_amazon_shipment_method( $params = array() ) {

		$logger  = wc_get_logger();
		$context = array( 'source' => 'ced_amazon_shipment_tracking' );
		
		$seller_id = $params['seller_id'] ?? '';

		if ( empty( $seller_id ) ) {
			$logger->info( wc_print_r( 'seller Id is empty', true ), $context );
			return;
		}

		if ( CedAmazonHOPS::custom_orders_table_usage_is_enabled() ) {
			$this->create_amz_order_hops = true;

			$after_date = gmdate('Y-m-d H:i:s', strtotime('-480 hours'));

			$args = array(
				'status'        => 'completed',
				'limit'         => -1,
				'return'        => 'ids',
				'meta_query'    => array(
					'relation' => 'AND',
					array(
						'key'       => '_amazon_umb_order_status',
						'value'     => 'Created',
						'compare'   => '=',
					),
					array(
						'key'       => 'ced_amazon_order_seller_id',
						'value'     => $seller_id,
						'compare'   => '=',
					),
				),
				'date_created' => '>=' . $after_date,
			);

			$orders = wc_get_orders($args);

		} else {

			$args = array(
				'post_type' 	=> 'shop_order',
				'fields' 		=> 'ids',
				'post_status' 	=>  array('completed'),
				'numberposts' 	=> -1,
				'meta_query' 	=> array(
					'relation' => 'AND',
						array(
							'key' 		=> '_amazon_umb_order_status',
							'value' 	=> 'Created',
							'compare'	=> '=',
							),
						array(
							'key'		=> 'ced_amazon_order_seller_id',
							'value'		=> $seller_id,
							'compare'	=> '='
						)
					),
				'date_query'	=>	array(
					array(
						'after' => '120 hours ago',
					),
				)
			);

			$orders = get_posts($args);
	
		}

		if ( empty( $orders ) ) {
			$logger->info( "Empty Completed Orders \n\n\n", $context );
			return;
		}

		$logger->info( 'Orders found for shipment: ', $context );
		$logger->info( wc_print_r( $orders, true ), $context );

		$settings         = get_option( 'ced_amazon_global_settings', array() );
		$settings         = $settings[$seller_id] ?? array();
		$orderacknowledge = $settings['ced_amz_order_acknow'] ;

		if ( isset( $orders ) && ! empty( $orders ) ) {

			/** Acknowledgement Orders starts */
			if ( !empty( $orderacknowledge ) && ( 'on' == $orderacknowledge || '1' == $orderacknowledge ) ) {
			   $this->ced_amazon_acknowledge_order( $orders, $seller_id );
			}
			/** Acknowledgement Orders ends  */

			/** Order Fulfillment starts */
				$this->ced_amazon_order_submit_tracking( $orders, $seller_id );
			/** Order Fulfillment ends */

		}

	}


	public function ced_amazon_order_submit_tracking(  $orders = array(), $seller_id = '' ) {

		$logger  = wc_get_logger();
		$context = array( 'source' => 'ced_amazon_shipment_tracking' );
		$logger->info( wc_print_r( ced_woo_timestamp() . ' ' . __FUNCTION__, true ), $context );

		$carriers = array();

		require CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-shipment-carrier-codes.php';
		require_once CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-shipment-manager.php';
		$shipment_instance = new Ced_Amazon_Shipment_Manager();

		$global_setting_data                 = get_option( 'ced_amazon_global_settings', array() );
		$seller_data                         = isset( $global_setting_data[$seller_id] ) ? $global_setting_data[$seller_id] : array();
		$ced_amazon_shipment_tracking_plugin = isset( $seller_data['ced_amazon_shipment_tracking_plugin'] ) ? $seller_data['ced_amazon_shipment_tracking_plugin'] : '';

		$remote_shop_id = '';

		$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );
		if ( isset( $saved_amazon_details[$seller_id] ) && !empty( $saved_amazon_details[ $seller_id ] ) && is_array( $saved_amazon_details[$seller_id] ) ) {
			$shop_data      = $saved_amazon_details[$seller_id];
			$remote_shop_id = isset( $shop_data['seller_next_shop_id'] ) ?  $shop_data['seller_next_shop_id'] : '';
		} else {
			$logger->info( 'Remote shop ID not found ', $context );
			return;
		}

		$count                             = 0;
		$ordershipmainarray                = array();
		$ordershipmainarray['@attributes'] = array(
			'xmlns:xsi'                     => 'http://www.w3.org/2001/XMLSchema-instance',
			'xsi:noNamespaceSchemaLocation' => 'amzn-envelope.xsd',
		);

		$ordershipmainarray['Header']['DocumentVersion']    = '1.01';
		$ordershipmainarray['Header']['MerchantIdentifier'] = 'M_SELLER_XXXXXX';
		$ordershipmainarray['MessageType']                  = 'OrderFulfillment';
		$ordershipmainarray['PurgeAndReplace']              = 'false';

		$counter        = 0;
		$shipped_orders = array();
		$logger->info( 'Going to loop orders.', $context );
		foreach ( $orders as $key => $orderID ) {

			if ( empty( $orderID ) ) {
				continue;
			}

			++$count;
			$order = wc_get_order( $orderID );

			// order_item_detail
			$marketplaceOrder = 'Amazon';
			$isUmbOrder       = $order->get_meta( 'amazon_order_id' );
			$order_detail     = $order->get_meta( 'order_detail' );
			$order_details    = $order->get_meta( 'order_item_detail' );

			if ( empty( $isUmbOrder ) || 'Amazon' !== $marketplaceOrder ) {
				continue;
			}

			$ced_tracking_details = array();
			$ordershipfulfildata  = array();

			/** Fetch tracking details */
			$logger->info( 'Going tracking details for order ' . $orderID , $context );
			$ced_tracking_details = $shipment_instance->getTrackingDetails( $ced_amazon_shipment_tracking_plugin, $orderID );
			
			if ( empty( $ced_tracking_details ) || ! isset( $ced_tracking_details['tracking_number'] ) || empty( $ced_tracking_details['tracking_number'] ) ) {
				$logger->info( 'Empty tracking details or tracking number found for WooCOmmerce order ID ' . $orderID, $context );
				continue;

			}

			$custom_plgn_carrier_code = isset( $ced_tracking_details['tracking_provider_code'] ) ? $ced_tracking_details['tracking_provider_code'] : '';
			$tracking_number          = isset( $ced_tracking_details['tracking_number'] ) ? $ced_tracking_details['tracking_number'] : '';
			$custom_plgn_carrier_name = isset( $ced_tracking_details['tracking_provider_name'] ) ? $ced_tracking_details['tracking_provider_name'] : '';

			if ( empty( $tracking_number ) ) {
				$logger->info( "Tracking no is empty \n", $context );
				continue;
			}

			if ( empty( $custom_plgn_carrier_name ) && !empty( $custom_plgn_carrier_code ) ) {
				$custom_plgn_carrier_name = $custom_plgn_carrier_code;
			}

			if ( empty( $custom_plgn_carrier_name ) ) {
				$logger->info( "custom_plgn_carrier_name no is empty \n", $context );
				continue;
			}

			$mod_custom_plgn_carrier_code = strtolower( $custom_plgn_carrier_name );
			
			/** Get amazon shipment carrier name w.r.t woocommerce shiipment carrier */
			$carrier_code = isset( $carriers[ $mod_custom_plgn_carrier_code ] ) ? $carriers[ $mod_custom_plgn_carrier_code ] : '';

			$logger->info( wc_print_r( 'carrier code' . $carrier_code , true ), $context );

			/** Prepare shipment data as per amazon */
			if ( empty( $carrier_code ) ) {
				/** Handling the condition when we dont have carrier name as per amazon */
				$ordershipfulfildata['CarrierCode']    = 'Other';   // CarrierName
				$ordershipfulfildata['CarrierName']    = ucfirst( $custom_plgn_carrier_name );
				$ordershipfulfildata['ShippingMethod'] = 'Standard';

				if ( !empty( $tracking_number ) ) {
					$ordershipfulfildata['ShipperTrackingNumber'] = trim($tracking_number);    
				}
				
			} else {
				$ordershipfulfildata['CarrierCode']           = trim($carrier_code); // Amazon carrier name
				$ordershipfulfildata['ShippingMethod']        = 'Standard';
				$ordershipfulfildata['ShipperTrackingNumber'] = trim($tracking_number);
			}
			
			$logger->info( wc_print_r( $ordershipfulfildata , true ), $context );

			$offset          = '.0000000-00:00';
			$FulfillmentDate = ced_woo_timestamp( 'Y-m-d\Th:i:s' ) . $offset;
	
			$ordershiparray['AmazonOrderID']   = $order_detail['AmazonOrderId'];
			$ordershiparray['FulfillmentDate'] = $FulfillmentDate;
			$ordershiparray['FulfillmentData'] = $ordershipfulfildata;

			$itemarray = array();	
			foreach ( $order_details as $key => $order_item ) {
	
				$amznitem['AmazonOrderItemCode'] = $order_item['OrderItemId'];
				// $amznitem['MerchantFulfillmentItemID'] = $order_item['id'];
				$amznitem['Quantity'] = $order_item['QuantityOrdered'];
				$itemarray[]          = $amznitem;
				unset( $order_details[ $key ] );
	
			}

			$total_quantity = 0; 
			foreach ( $order->get_items() as $item ) {
				$total_quantity += $item->get_quantity(); 
			}
	
			++$counter;
			$ordershiparray['Item']                                    = $itemarray;
			$ordershipmainarray['Message'][$count]['MessageID']        = $counter;
			$ordershipmainarray['Message'][$count]['OperationType']    = 'Update';
			$ordershipmainarray['Message'][$count]['OrderFulfillment'] = $ordershiparray;

			$shipped_orders[ $order_detail['AmazonOrderId'] ] = array(
				'items_count'    => count( $order->get_items() ),
				'total_quantity' => $total_quantity,
				'woo_order_id'   => $orderID,
				'current_status' => $order->get_status(),
				'url'            => $order->get_edit_order_url(), 
				'value'          => $ordershipfulfildata
			);

		}


		if ( isset( $ordershipmainarray['Message'] ) ) {

			require_once CED_AMAZON_DIRPATH . 'marketplaces/amazon/lib/array2xml.php';
			$xml       = Array2XML::createXML( 'AmazonEnvelope', $ordershipmainarray );
			$xmlString = $xml->saveXML();

			$xmlFileName = 'shipment_data' . $seller_id . '.xml';
			$this->amz_com_opts_mng->writeStringToFile( $xmlString, $xmlFileName );

			$logger->info( wc_print_r( $xmlString, true ), $context );

			$order_topic = 'create-feed';
			$order_keys  = array(
				'feed_action'    => 'POST_ORDER_FULFILLMENT_DATA',
				'feed_content'   => $xmlString,
				'remote_shop_id' => $remote_shop_id,
				
			);

			$file = CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php';

			if ( file_exists( $file ) ) {

				require_once $file;
				$this->amzonCurlRequestInstance = new Ced_Amazon_Curl_Request();
				$ordershipmentreponse           = $this->amzonCurlRequestInstance->ced_amazon_serverless_process( $order_topic, $order_keys, 'POST' );
				$ordershipmentreponse           = isset( $ordershipmentreponse['body'] ) ? json_decode( $ordershipmentreponse['body'], true ) : array();

				if ( $ordershipmentreponse['success'] && isset( $ordershipmentreponse['data'] ) && isset( $ordershipmentreponse['data']['feed_id'] ) ) {

					$feedId = $ordershipmentreponse['data']['feed_id'];

					require_once CED_AMAZON_DIRPATH . 'admin/amazon/lib/class-feed-manager.php';
					$feed_manager = Ced_Umb_Amazon_Feed_Manager::get_instance();

					$mplocation_arr = explode( '|', $seller_id );
					$mplocation     = isset( $mplocation_arr[0] ) ? $mplocation_arr[0] : '';

					$feed_manager->insertFeedInfoToDatabase( $feedId, 'POST_ORDER_FULFILLMENT_DATA', $mplocation, $shipped_orders, 'Automatic'  );
					
				} else {
					$logger->info( 'Unable to create feed for shipment tracking', $context );
				}


			}

		}


	}

	public function ced_amazon_acknowledge_order( $orders, $seller_id ) {

		$logger  = wc_get_logger();
		$context = array( 'source' => 'ced_amazon_order_acknowledgement' );
		
		$logger->info( wc_print_r( 'incoming orders..........', true), $context );
		$logger->info( wc_print_r( $orders, true), $context );
	
		$ced_orders = array();
	
		if ( !empty($orders) ) {
		
			global $wpdb;
			
			foreach ( $orders as $order_id ) {
				$order      = wc_get_order( $order_id );
				$seller_key = $order->get_meta('ced_amazon_order_seller_id');
	
				if ( array_key_exists( $seller_key, $ced_orders ) ) {
				  array_push( $ced_orders[$seller_key], $order_id );
				} else {
					$ced_orders[$seller_key] = [ $order_id ]; 
				}
				
			}
	
			$logger->info( wc_print_r( 'Amazon Orders to Acknowledge', true), $context );
			$logger->info( wc_print_r( $ced_orders, true), $context );
	
		} else {
			return;
		}
		
	
		if ( !empty( $ced_orders ) ) {
			foreach ( $ced_orders as $seller_id => $orders ) {
	
				$orderacknowledgearray                                 = array();
				$orderacknowledgearray                                 = array();
				$orderacknowledgearray['@attributes']                  = array(
						'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
						'xsi:noNamespaceSchemaLocation' => 'amzn-envelope.xsd'
				);
				$orderacknowledgearray['Header']['DocumentVersion']    = '2.1';
				$orderacknowledgearray['Header']['MerchantIdentifier'] = 'M_SELLER_XXXXXX';
				$orderacknowledgearray['MessageType']                  = 'OrderAcknowledgement';
	
				$mplocation = explode( '|', $seller_id );
				$mplocation = isset( $mplocation[0] ) ? $mplocation[0] : '';
	
				$logger->info( wc_print_r( 'current mp location is: ' . $mplocation, true), $context );
	
				$count         = 0;
				$acknow_orders = array();

				foreach ($orders as $key => $orderID ) {
					
					if (empty($orderID)) {
						continue;
					}
					
					++$count;
					$order = wc_get_order( $orderID );
					
					$amazon_order_id = $order->get_meta('amazon_order_id');
					$order_items     = $order->get_meta('order_items');
					$order_detail    = $order->get_meta( 'order_detail' );
					$order_details   = $order->get_meta('order_item_detail');
	
					$orderacknowledge['AmazonOrderID']   = $amazon_order_id;
					$orderacknowledge['MerchantOrderID'] = $orderID;
					$orderacknowledge['StatusCode']      = 'Success';
	
					$itemarray = array();
	
					include_once CED_AMAZON_DIRPATH . 'admin/amazon/lib/class-amazon-xml-lib.php';
					if ( !empty( $order->get_items() ) ) {
	
						foreach ( $order->get_items() as  $item_id => $item ) {
							$logger->info( wc_print_r( 'looping items', true), $context );
							$amznitem = array();
							$product  = apply_filters( 'woocommerce_order_item_product', $order->get_product_from_item( $item ), $item );
							
							if (class_exists('Ced_Amzon_XML_Lib')) {
								$logger->info( wc_print_r( 'Library loaded.', true), $context );
							} else {
								$logger->info( wc_print_r( 'Library NOT loaded.', true), $context );
							}
	
							$amazon_xml_lib = new Ced_Amzon_XML_Lib();
							
							$productaisn = $amazon_xml_lib->fetchMetaValueOfProduct( $product->id, 'ced_amazon_product_asin_' . $mplocation );
							foreach ( $order_details as $key => $order_item) {
	
								$logger->info( wc_print_r( 'inner loop', true), $context );
								if ($order_item['ASIN'] == $productaisn ) {
	
									$amznitem['AmazonOrderItemCode'] = $order_item['OrderItemId'];
									$amznitem['MerchantOrderItemID'] = $product->id;
									$itemarray[]                     = $amznitem;
									unset($order_details[$key]);
								}	
							}	
	
						}	
	
					} else {
						$logger->info( 'No order items exist for woocommerce order ID: ' . $order_id , $context);
					}
					
					$logger->info( wc_print_r( 'outside loop', true), $context );
	
					$total_quantity = 0; 
					foreach ( $order->get_items() as $item ) {
						$total_quantity += $item->get_quantity(); 
					}

					$orderacknowledge['Item']                                  = $itemarray;
					$orderacknowledgearray['Message'][$count]['MessageID']     = $orderID;
					$orderacknowledgearray['Message'][$count]['OperationType'] = 'Update';
					$orderacknowledgearray['Message'][$count]['OrderAcknowledgement'] = $orderacknowledge;

					$acknow_orders[ $order_detail['AmazonOrderId'] ] = array(
						'items_count'    => count( $order->get_items() ),
						'total_quantity' => $total_quantity,
						'woo_order_id'   => $orderID,
						'current_status' => $order->get_status(),
						'url'            => $order->get_edit_order_url(), 
						'value'          => $orderacknowledge
					);
	
				}
	
				$xmlFileName = 'order_acknowledgement_data' . $mplocation . '.xml';
	
				require_once CED_AMAZON_DIRPATH . 'marketplaces/amazon/lib/array2xml.php';
				$xml       = Array2XML::createXML('AmazonEnvelope', $orderacknowledgearray );
				$xmlString = $xml->saveXML();
	
				$this->writeXMLStringToFile( $xmlString, $xmlFileName );
				$logger->info( wc_print_r( $xmlString, true), $context );
				
				$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );
		
				if ( isset( $saved_amazon_details[$seller_id] ) && !empty( $saved_amazon_details[ $seller_id ] ) && is_array( $saved_amazon_details[$seller_id] ) ) {
					$shop_data = $saved_amazon_details[$seller_id];
				}
		
				if ( empty($shop_data) ) {
					$logger->info( wc_print_r( 'shop data is empty', true), $context );
					return;
				}
		
				$refresh_token  = isset($shop_data['amazon_refresh_token']) ? $shop_data['amazon_refresh_token']:'';
				$marketplace_id = isset($shop_data['marketplace_id']) ? $shop_data['marketplace_id']:'';
				
				$user_id = ced_amz_get_user_id_by_mrkp_id( $marketplace_id );
				$logger->info( wc_print_r( 'preparing data to make api call', true), $context );
				
				$order_topic = 'create-feed';
				$order_keys  = array(
					'feed_action'    => 'POST_ORDER_ACKNOWLEDGEMENT_DATA',
					'remote_shop_id' => $user_id,
					'feed_content'   => $xmlString
				);
	
				$logger->info( wc_print_r( 'preparing data for api call', true), $context );
	
				$file = CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php';
	
				if ( file_exists( $file) ) { 
	
					include_once $file;
					$amzonCurlRequestInstance = new Ced_Amazon_Curl_Request();
	
					$ordershipmentreponse = $amzonCurlRequestInstance->ced_amazon_serverless_process( $order_topic, $order_keys, 'POST');
					$ordershipmentreponse = isset( $ordershipmentreponse['body'] ) ? json_decode( $ordershipmentreponse['body'], true ) : array() ;
	
					$logger->info( wc_print_r( $ordershipmentreponse, true), $context );
	
					if ( $ordershipmentreponse['success'] && isset($ordershipmentreponse['data']) && isset($ordershipmentreponse['data']['feed_id'])) {
	
						$feedId = $ordershipmentreponse['data']['feed_id'];
	
						require_once CED_AMAZON_DIRPATH . 'admin/amazon/lib/class-feed-manager.php';
						$feed_manager = Ced_Umb_Amazon_Feed_Manager::get_instance();
	
						$feed_manager->insertFeedInfoToDatabase( $feedId, 'POST_ORDER_ACKNOWLEDGEMENT_DATA', $mplocation, $acknow_orders, 'Automatic' );
						
					} else {
						echo 'cannot creating feed';
					}
		
				} 
				
			}
			
		}
	
	}

	public function writeXMLStringToFile( $xmlString, $fileName ) {

		$XMLfilePath = ABSPATH . 'wp-content/uploads/';
		if ( ! is_dir( $XMLfilePath ) ) {
			if ( ! mkdir( $XMLfilePath, 0755 ) ) {
				return false;
			}
		}
		$XMLfilePath = $XMLfilePath . 'ced-amazon/';
		if ( ! is_dir( $XMLfilePath ) ) {
			if ( ! mkdir( $XMLfilePath, 0755 ) ) {
				return false;
			}
		}

		if ( ! is_writable( $XMLfilePath ) ) {
			return false;
		}
		$XMLfilePath .= $fileName;
		$XMLfile      = fopen( $XMLfilePath, 'w' );
		fwrite( $XMLfile, $xmlString );
		fclose( $XMLfile );

	}

	public function ced_amazon_check_required_details_for_cron(  $params ) {

		$logger = wc_get_logger();

		$context        = $params['context'] ?? array();
		$scheduler_name = $params['scheduler_name'] ?? '';
		$process_name   = $params['process_name'] ?? '';
		$region         = $params['region'] ?? '';
		$seller_id      = $params['seller_id'] ?? '';
		$merchant_id    = $params['merchant_id'] ?? '';
		$transient_name = $params['transient_name'] ?? '';

		$action = strtolower($process_name);

		$amazon_feed_manager = CED_AMAZON_DIRPATH . 'admin/amazon/lib/class-feed-manager.php';
		if ( file_exists( $amazon_feed_manager ) ) {
			require_once $amazon_feed_manager;
			$this->amazon_feed_manager = new Ced_Umb_Amazon_Feed_Manager();
		}
		
		if ( is_null( $this->amazon_feed_manager ) ) {
			$logger->info( "Unable to find feed manager file \n", $context );
			return false;
		}

		if ( empty( $region ) ) {
			$logger->info( 'Region is missing during ' . $process_name . " ! \n", $context );
			return false;
		}

		$amzonCurlRequest = CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php';
		if ( file_exists( $amzonCurlRequest ) ) {
			require_once $amzonCurlRequest;
			$this->amzonCurlRequestInstance = new Ced_Amazon_Curl_Request();
		} else {
			$log_message = "Amazon curl request file instance doesn't exist \n";
			$logger->info( wc_print_r( $log_message ), $context );
			return false;
		}

		// throttle check
		if ( ! empty( $transient_name ) ) {
			$ced_amazon_exist_product_sync_throttle = get_transient( $transient_name );
			if ( $ced_amazon_exist_product_sync_throttle ) {
				$log_message = "Quota exceeded. Please try after 5 mins.! \n";
				$logger->info( wc_print_r( $log_message, true ), $context );
				return false;
			}
		}

		$ced_amz_mod_actions_array = get_option( 'ced_amz_mod_actions_array', array() );
		$logger->info( wc_print_r( $ced_amz_mod_actions_array, true ), $context );

		$marketplace_ids = array();
		
		// code to get the seller id in case of manual operation
		$mplocation             = '';
		$current_marketplace_id = array();

		if ( ! empty( $seller_id ) ) {

			/** Code to handle manual operations
			 * Code to get marketplaceids using sellerID. 
			 */
			$mplocation_arr = explode( '|', $seller_id );
			$mplocation     = isset( $mplocation_arr[1] ) ? $mplocation_arr[0] : '';

			$current_mp_array       = ced_amz_marketplaceid_region_mapping( $mplocation );
			$current_marketplace_id = isset( $current_mp_array['marketplace_id'] ) ? array( $current_mp_array['marketplace_id'] ) : array();
			$logger->info( wc_print_r( '----------- CURRENT MARKETPLACE ID IS: '  , true ), $context );
			$logger->info( wc_print_r( $current_marketplace_id  , true ), $context );

		} else {
			/** Code to handle manual operations
			 * Code to get list of all marketplaced ids as per action type and merchantID. 
			 */
			if ( 'order' == $action || 'price' == $action || 'inventory' == $action ||  'common' == $action ) {
				$marketplace_ids = isset( $ced_amz_mod_actions_array[$merchant_id] ) && isset( $ced_amz_mod_actions_array[$merchant_id][$action] ) ? $ced_amz_mod_actions_array[$merchant_id][$action] : array();
			} 
		}

		/** Code to handle situation when automatic scheduler is off for a region and manual is executing. */
		$final_mrkt_ids = array_merge( $current_marketplace_id, $marketplace_ids );
		
		$remote_shop_id = '';
		$mplocation     = '';

		/** Get any one(first) remote shop id and mplocation corresponding to the current amzon region. */
		$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );
		if ( !empty( $saved_amazon_details ) ) {
			foreach ( $saved_amazon_details  as $seller_id => $seller_data ) {
				if ( in_array( $seller_data['marketplace_id'], $final_mrkt_ids ) ) {
					$remote_shop_id = $seller_data['seller_next_shop_id'];
					$mplocation     = $seller_data['ced_mp_name'];
					break;
				}
			}
		}

		$logger->info( wc_print_r( 'remote_shop_id', true ), $context  );
		$logger->info( wc_print_r( $remote_shop_id, true ), $context  );
		$logger->info( wc_print_r( 'mplocation', true ), $context  );
		$logger->info( wc_print_r( $mplocation, true ), $context  );
		$logger->info( wc_print_r( 'final marketplace_ids', true ), $context  );
		$logger->info( wc_print_r( $final_mrkt_ids, true ), $context  );
		
		if ( empty( $mplocation ) || empty( $remote_shop_id ) || empty( $final_mrkt_ids )  ) {
			$logger->info( '----------------------------------- -Mp Location or Remote shop id or marketplace_ids is missing --------------------------- ', $context );
			return false;
		}

		return array(
			'mplocation'      => $mplocation,
			'remote_shop_id'  => $remote_shop_id,
			'marketplace_ids' => array_unique( array_values( array_filter( $final_mrkt_ids ))),
			
		);

	}


	/**
	 * Modify table structure for feeds and profiles tables.
	 *
	 * @since 1.0.0
	 */
	public function ced_amazon_modify_feeds_table() {
		global $wpdb;

		// Add opt_type column to ced_amazon_feeds table.
		$column_name = 'opt_type';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is safe.
		$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$wpdb->prefix}ced_amazon_feeds LIKE %s", $column_name ) );
		if ( empty( $column_exists ) ) {
			$column_name   = sanitize_key( $column_name );
			$default_value = '';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is safe.
			$wpdb->query(
				$wpdb->prepare(
					"ALTER TABLE {$wpdb->prefix}ced_amazon_feeds ADD COLUMN opt_type TEXT NOT NULL DEFAULT %s",
					$default_value
				)
			);
		}

		// Add status column to ced_amazon_feeds table.
		$column_name = 'status';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is safe.
		$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$wpdb->prefix}ced_amazon_feeds LIKE %s", $column_name ) );
		if ( empty( $column_exists ) ) {
			$column_name   = sanitize_key( $column_name );
			$default_value = 'pending';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is safe.
			$wpdb->query(
				$wpdb->prepare(
					"ALTER TABLE {$wpdb->prefix}ced_amazon_feeds ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT %s",
					$default_value
				)
			);
		}

		// Add error_sku column to ced_amazon_feeds table.
		$column_name = 'error_sku';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is safe.
		$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$wpdb->prefix}ced_amazon_feeds LIKE %s", $column_name ) );
		if ( empty( $column_exists ) ) {
			$column_name = sanitize_key( $column_name );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table prefix is safe, no default value needed.
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}ced_amazon_feeds ADD COLUMN error_sku JSON DEFAULT NULL" );
		}

		// Add product_type column to ced_amazon_profiles table.
		$column_name = 'product_type';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is safe.
		$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$wpdb->prefix}ced_amazon_profiles LIKE %s", $column_name ) );
		if ( empty( $column_exists ) ) {
			$column_name   = sanitize_key( $column_name );
			$default_value = '';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is safe.
			$wpdb->query(
				$wpdb->prepare(
					"ALTER TABLE {$wpdb->prefix}ced_amazon_profiles ADD COLUMN product_type TEXT NOT NULL DEFAULT %s",
					$default_value
				)
			);
		}

		// Add category column to ced_amazon_profiles table.
		$column_name = 'category';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is safe.
		$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$wpdb->prefix}ced_amazon_profiles LIKE %s", $column_name ) );
		if ( empty( $column_exists ) ) {
			$column_name   = sanitize_key( $column_name );
			$default_value = '';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table prefix is safe.
			$wpdb->query(
				$wpdb->prepare(
					"ALTER TABLE {$wpdb->prefix}ced_amazon_profiles ADD COLUMN category VARCHAR(100) NOT NULL DEFAULT %s",
					$default_value
				)
			);
		}
	}


	
	public function ced_amazon_delete_feed_cron( ) {

		global $wpdb;
		$current_date     = current_time('mysql');
		$date_30_days_ago = gmdate('Y-m-d H:i:s', strtotime('-10 days', strtotime($current_date)));

		/** Execute the delete query */
		$result = $wpdb->query( 
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}ced_amazon_feeds WHERE feed_date_time <= %s",
				$date_30_days_ago
			)
		);

		
	}


	public function ced_mbc_add_pricing_tab( $navigation_tabs = array() ) {

		$navigation_tabs['pricing'] = array(
			'name'         => 'Pricing',
			'tab'          => 'Pricing',
			'menu_link'    => 'pricing',
			'is_active'    => 1,
			'is_installed' => 1,
		);

		return $navigation_tabs;

	}


	public function ced_amazon_update_shipped_orders( $feedId, $seller_id ) {

		$logger  = wc_get_logger();
		$context = array( 'source' => 'ced_amazon_update_shipped_orders' );

		global $wpdb;
		$tableName        = $wpdb->prefix . 'ced_amazon_feeds';
		$feed_request_ids = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ced_amazon_feeds WHERE `feed_id` = %d", $feedId ), 'ARRAY_A' );

		$feed_request_id = $feed_request_ids[0];
		
		$feed_type   = $feed_request_id['feed_action'];
		$location_id = $feed_request_id['feed_location'];

		$logger->info( wc_print_r( 'feed id is ' . $feedId , true ), $context );
		$logger->info( wc_print_r( 'seller id is: ' . $seller_id  , true ), $context );

		$feed_manager = Ced_Umb_Amazon_Feed_Manager::get_instance( );
		$response     = $feed_manager->getFeedItemsStatusSpApi( $feedId, $feed_type, $location_id, $seller_id );

		$logger->info( wc_print_r( ced_woo_timestamp() . ' ' . __FUNCTION__, true ), $context );
		
		$logger->info( wc_print_r( $response, true ), $context );
		$logger->info( wc_print_r( $response['status'], true ), $context );

		if ( isset( $response['status'] ) && 'DONE' != $response['status'] ) {
			$logger->info( wc_print_r( '-------------------- Feed status is: ' . $response['status'] . '-----------------------' , true ), $context );
			$event_time = time() + 300;
			$hook_name  = 'ced_amazon_update_shipped_orders';
			$hook_data  =  array( $feedId, $seller_id );

			if ( function_exists( 'as_schedule_single_action' ) ) {
				$is_scheduled = as_schedule_single_action( $event_time, $hook_name, $hook_data );
			} else {
				$is_scheduled = wp_schedule_single_event( $event_time, $hook_name, $hook_data  );
			}
			
			if ( $is_scheduled ) {
				$logger->info( 'An another action is created to update shipped orders of feed ID: ' . $feedId, $context );
			} else {
				$logger->info( 'Unable to schudele an action to update shipped orders of feed ID: ' . $feedId , $context );
			}

		} elseif ( isset( $response['status'] ) && 'DONE' == $response['status']  ) {

			$logger->info( wc_print_r( '-------------------- Feed status is: ' . $response['status'] . '-----------------------' , true ), $context );
			
			$order_ids = isset( $feed_request_id['sku'] ) ? json_decode( $feed_request_id['sku'], true ) : array();
			$xmlString = isset( $response['body'] ) ? $response['body'] : array();

			$logger->info( wc_print_r( ' Orders that can be updated'  , true ), $context );
			$logger->info( wc_print_r( wp_json_encode( $order_ids )  , true ), $context );
					
			$xml            = new SimpleXMLElement($xmlString);
			$orders_removed = array();

			if ( !empty( $xml ) ) {

				foreach ($xml->Message->ProcessingReport->Result as $result) {
					if ( 'Error' === (string) $result->ResultCode ) {
						$amazonOrderId = (string) $result->AdditionalInfo->AmazonOrderID;

						$logger->info( wc_print_r( '------------ error to ship amzon orderid : ' . $amazonOrderId . '------------------' , true ), $context );
						$logger->info( wc_print_r( '------------ Unable to ship WOO order ID : ' . $order_ids[ $amazonOrderId ] . '(' . $amazonOrderId . ')------------------' , true ), $context );
						$logger->info( wc_print_r( '-------------------- Removing WOO order ID : ' . $order_ids[ $amazonOrderId ] . ' from feedID ' . $feedId . '------------' , true ), $context );
				
						$orders_removed[] =  $order_ids[ $amazonOrderId ];
						unset( $order_ids[ $amazonOrderId ]  );
					}
				}

			}

			$logger->info( wc_print_r( '------------------------ ORDERS REMOVED  --------------------'  , true ), $context );
			$logger->info( wc_print_r( wp_json_encode( $orders_removed )  , true ), $context );

			$logger->info( wc_print_r( '------------------------ ORDERS to UPDATE  --------------------'  , true ), $context );
			$logger->info( wc_print_r( wp_json_encode( $order_ids )  , true ), $context );

			if ( !empty( $order_ids ) && is_array( $order_ids ) ) {
				foreach ( $order_ids as $order_id => $order_details) {

					$woo_order_id            = $order_details['woo_order_id'];
					$feedrequest['request']  = 'Shipped';
					$feedrequest['id']       = $feedId;
					$feedrequest['response'] = false;

					$order = wc_get_order( $woo_order_id );
					$order->update_meta_data( '_umb_order_feed_status', true );
					$order->update_meta_data( '_umb_order_feed_details', $feedrequest );
					$order->update_meta_data( '_amazon_umb_order_status', 'Shipped' );

					$order->save();
					$logger->info( wc_print_r( '------------ Updated woo order ID : ' . $woo_order_id . '(' . $order->get_meta( 'amazon_order_id' ) . ')------------------' , true ), $context );
					
				}
			}

		} else {
			$logger->info( 'Exception occured in get Order fulfillment API... ' . $feedId , $context );
		}


	}

	public function ced_amazon_get_feed_data( $data ) {

		$logger  = wc_get_logger();
		$actions = array();

		if ( 'price' == $data['action'] ) {
			$context = array( 'source' => 'ced_amazon_price_sync' );
			$actions = array( 'price' );

		} elseif ( 'inventory' == $data['action'] ) {
			$context = array( 'source' => 'ced_amazon_inventory_sync' );
			$actions = array( 'inventory' );

		} elseif ( 'common' == $data['action'] ) {
			$context               = array( 'source' => 'ced_amazon_common_prc_inv_sync' );
			$actions               = array( 'price', 'inventory' );
			$data['woo_inv_array'] = $data['woo_price_array'];

		} elseif ( 'relist' == $data['action'] ) {
			$context = array( 'source' => 'ced_amazon_relist_products' );
			$actions = array( 'relist' );
			
		} elseif ( 'upload' == $data['action'] ) {
			$context = array( 'source' => 'ced_amazon_process_upload_queue' );
			$actions = array( 'upload' );
			
		}
		
		$logger->info( wc_print_r( $data, true ), $context );

		$user_id         = isset( $data['user_id'] ) ? $data['user_id'] : '';
		$marketplace_ids = isset( $data['marketplace_ids'] ) ? $data['marketplace_ids'] : '';

		$ced_amazon_remote_shop_ids = get_option( 'ced_amazon_remote_shop_ids', array() );
		$seller_id                  = $ced_amazon_remote_shop_ids[$user_id]['ced_mp_seller_key'];
		$location_id                = $ced_amazon_remote_shop_ids[$user_id]['ced_mp_name'];
		
		$feed_manager = Ced_Umb_Amazon_Feed_Manager::get_instance( );

		$logger->info( wc_print_r( 'feed id is: ' . $data['feed_id'], true ), $context );
		$logger->info( wc_print_r( 'location id is: ' . $location_id , true ), $context );
		$logger->info( wc_print_r( ' seller_id is: ' . $seller_id , true ), $context );

		$response = $feed_manager->getFeedItemsStatusSpApi( $data['feed_id'], 'JSON_LISTINGS_FEED', $location_id, $seller_id );

		$logger->info( wc_print_r( '-------------------------feed response for single event starts----------------------------', true ), $context );
		$logger->info( wc_print_r( $response, true ), $context );
		$logger->info( wc_print_r( '-------------------------feed response for single event ends----------------------------', true ), $context );
	
		if ( isset( $response['status'] ) && 'DONE' == $response['status']) {

			foreach ( $actions as $action ) {
			
				if ( 'upload' == $data['action'] ) {
					$skus_array = isset( $data['skus_array'] ) ? $data['skus_array'] : array();

					$skumapping = array();
					if ( !empty( $skus_array ) ) {
						foreach ( $skus_array as $key => $product ) {
							$skumapping[ $product['messageId'] ] = $product['product_sku'];
						}
					}

					$logger->info( wc_print_r( $skumapping, true ), $context );
					
					/** Code to loop through the response and update the validation errors */ 
					$decoded_response = json_decode( $response['body'], true );
					$issues           = isset( $decoded_response['issues'] ) ? $decoded_response['issues'] : array();

					$logger->info( wc_print_r( 'issues', true ), $context );
					$logger->info( wc_print_r( $issues, true ), $context );
					
					if ( !empty( $issues ) ) {
						$validation_errors = array();
						foreach ( $issues as $issue ) {
							
							/** Grouping errors by messageId, using message id as key in validation_errors array and values as array
							* of error messages 
							*/
							if ( isset( $validation_errors[$issue['messageId']] ) ) {
								$validation_errors[$issue['messageId']] = array_merge( $validation_errors[$issue['messageId']], array($issue) );
							} else {
								$validation_errors[$issue['messageId']] = array( $issue );
							}
								
						}

						$logger->info( wc_print_r( 'validation_errors', true ), $context );
						$logger->info( wc_print_r( $validation_errors, true ), $context );

						if (!empty($validation_errors)) {
							foreach ($validation_errors as $message_id => $error_messages) {
								if (isset($skumapping[$message_id])) {
									$product_id = wc_get_product_id_by_sku( $skumapping[$message_id] );
									$arr        = array( 'amazon_validation' => $error_messages );
									update_post_meta( $product_id, 'ced_amz_json_validator_error_' . $seller_id , $arr );

									$logger->info( wc_print_r( '--------- Updated the Amazon Validation ERRORS -----------', true ), $context );
							

								} else {
									$logger->info( wc_print_r( '------------------------- SKU NOT FOUND FOR MESSAGE ID ' . $message_id . ' ----------------------------', true ), $context );
								}
							}
						}

					}

				} elseif ( isset( $data['first_sync'] ) && $data['first_sync'] ) {

					/** Code to update first sync offset for all marketplaces, the action name ( price, inventory, etc is set dynamically ) */ 
					$ced_amz_all_prc_inv_sync = get_option( 'ced_amz_all_prc_inv_sync', array() );
					foreach ( $marketplace_ids as $mid ) {
						$seller_id = ced_amz_get_seller_id_by_mrkp_id( $mid );
						$ced_amz_all_prc_inv_sync[ $seller_id ][ $action ] = 1;
						update_option( 'ced_amz_all_prc_inv_sync', $ced_amz_all_prc_inv_sync );
					}
					
					$logger->info( wc_print_r( $ced_amz_all_prc_inv_sync , true ), $context );
					$logger->info( wc_print_r( '------------------------- UPDATED THE FLAG FOR INITIAL ' . $action . ' SYNC ----------------------------', true ), $context );

					/** Code to update the current price of update products */
					if ( 'price' == $action ) {

						$logger->info( wc_print_r( '----------------- ITS A FIRST PRICE SYNC SO UPDATING OLD PRICE POST META ----------------', true ), $context );

						if ( !empty( $data['woo_price_array'] ) ) {
							
							foreach ( $data['woo_price_array'] as $product_id => $woo_price ) {
								update_post_meta( $product_id, 'ced_amz_old_price', $woo_price  );
							}

							$logger->info( wc_print_r( '------------------------- UPDATING THE OLD PRICE POST META  ----------------------------', true ), $context );
					
						} else {
							$logger->info( wc_print_r( '------------------------- WOO PRICE PRODCUST ARRAY IS EMPTY ----------------------------', true ), $context );
					
						} 

					} 

				} else {

					$logger->info( wc_print_r( '------------------------- first sync is OFF ----------------------------', true ), $context );
					/** Code to remove producta that has been updated from the list */

					if ( 'price' == $action ) {

						$products_to_update = get_option( 'ced_amz_price_updated_products', array() );

						foreach ( $marketplace_ids as $mid ) {
							$seller_id = ced_amz_get_seller_id_by_mrkp_id( $mid );
							$products  = isset( $products_to_update[ $seller_id ] ) ? $products_to_update[ $seller_id ] : array(); // all products which needs to be updated
		
							if ( !empty( $data['woo_price_array'] ) ) {
								
								foreach ( $data['woo_price_array'] as $product_id => $woo_price ) {
		
									$logger->info( wc_print_r( '------------------------- product id to remove is ' . $product_id . ' ----------------------------', true ), $context );
						
									$key = array_search( $product_id, $products );
									$logger->info( wc_print_r( '------------------------- key to remove is ' . $key . ' ----------------------------', true ), $context );
						
									if (  array_key_exists( $key, $products ) ) {
										$logger->info( wc_print_r( '------------------------- unsetting products id ' . $product_id . ' ----------------------------', true ), $context );
										unset( $products[$key] );
		
									}
									update_post_meta( $product_id, 'ced_amz_old_price', $woo_price  );
								}
		
								$products_to_update[ $seller_id ] = $products;
								
							} else {
								$logger->info( wc_print_r( '------------------------- WOO PRODCUST ARRAY IS EMPTY ----------------------------', true ), $context );
						
							}

							update_option( 'ced_amz_price_updated_products', $products_to_update );
							$logger->info( wc_print_r( $products_to_update, true ), $context );
							$logger->info( wc_print_r( '------------------------- UPDATED THE PRICE HOLDER ARRAY ----------------------------', true ), $context );

						}
						

					} elseif ( 'inventory' == $action ) {

						$products_to_update = get_option( 'ced_amz_stock_updated_products', array() );

						foreach ( $marketplace_ids as $mid ) {
							
							$seller_id = ced_amz_get_seller_id_by_mrkp_id( $mid );
							$products  = isset( $products_to_update[ $seller_id ] ) ? $products_to_update[ $seller_id ] : array();

							if ( !empty( $data['woo_inv_array'] ) ) {
								
								foreach ( $data['woo_inv_array'] as $product_id => $stock ) {
									$logger->info( wc_print_r( '------------------------- product id to remove is ' . $product_id . ' ----------------------------', true ), $context );
						
									$key = array_search( $product_id, $products );
									$logger->info( wc_print_r( '------------------------- key to remove is ' . $key . ' ----------------------------', true ), $context );
						
									if (  array_key_exists( $key, $products ) ) {
										$logger->info( wc_print_r( '------------------------- unsetting products id ' . $product_id . ' ----------------------------', true ), $context );
										unset( $products[$key] );

									}
								
								}

								$products_to_update[ $seller_id ] = $products;
								
							} else {
								$logger->info( wc_print_r( '------------------------- WOO PRODCUST ARRAY IS EMPTY ----------------------------', true ), $context );
						
							}

							update_option( 'ced_amz_stock_updated_products', $products_to_update );
							$logger->info( wc_print_r( $products_to_update, true ), $context );
							$logger->info( wc_print_r( '------------------------- UPDATED THE STOCK HOLDER ARRAY ----------------------------', true ), $context );
						

						}

					} elseif ( 'relist' == $action ) {

						$ced_amazon_all_products_to_relist = get_option( 'ced_amazon_products_to_relist', array() );
							
						foreach ( $marketplace_ids as $mid ) {
							
							$seller_id = ced_amz_get_seller_id_by_mrkp_id( $mid );
							$products  = isset( $ced_amazon_all_products_to_relist[ $seller_id ] ) ? $ced_amazon_all_products_to_relist[ $seller_id ]  : array();
						
							if ( !empty( $data['woo_relist_array'] ) ) {
								$logger->info( wc_print_r( '------------------------- LOOPING WOO PRODCUTS ARRAY ----------------------------', true ), $context );
						
								foreach ( $data['woo_relist_array'] as $key => $product_id ) {
									$logger->info( wc_print_r( '------------------------- product id to remove is ' . $product_id . ' ----------------------------', true ), $context );
						
									$key = array_search( $product_id, $products );
									$logger->info( wc_print_r( '------------------------- key to remove is ' . $key . ' ----------------------------', true ), $context );
						
									if (  array_key_exists( $key, $products ) ) {
										$logger->info( wc_print_r( '------------------------- unsetting products id ' . $product_id . ' ----------------------------', true ), $context );
										unset( $products[$key] );

									}
								
								}

								$ced_amazon_all_products_to_relist[ $seller_id ] = $products;
								
							} else {
								$logger->info( wc_print_r( '------------------------- WOO PRODCUTS ARRAY IS EMPTY ----------------------------', true ), $context );
						
							}

							update_option( 'ced_amazon_products_to_relist', $ced_amazon_all_products_to_relist );
							$logger->info( wc_print_r( $ced_amazon_all_products_to_relist, true ), $context );
							$logger->info( wc_print_r( '------------------------- UPDATED THE RELIST PRODUCT HOLDER ARRAY ----------------------------', true ), $context );
						

						}
					}

				}

			}

		} elseif ( isset( $response['status'] ) && 'PROGRESS' == $response['status']) {

			wp_schedule_single_event( time() + 120, 'ced_amazon_get_feed_data', array( $data ) );
			$logger->info( wc_print_r( '-------------------------CREATED NEXT SCHEDULED TO GET INITIAL SYNC FEED DATA ----------------------------', true ), $context );

		} 


	}


	public function ced_amz_update_product_types() {

		$check_ajax = check_ajax_referer( 'ced-amazon-ajax-seurity-string', 'ajax_nonce' );
		if ( ! $check_ajax ) {
			return;
		}

		$user_id   = isset( $_POST['user_id'] ) ? sanitize_text_field( $_POST['user_id'] ) : '';
		$seller_id = isset( $_POST['seller_id'] ) ? sanitize_text_field( $_POST['seller_id'] ) : '';
		
		if ( empty( $seller_id ) ) {
			echo json_encode( array(
				'status' => false,
				'message' => 'Seller Id not found'
			) ); 

			wp_die();
		}

		$mp_array    = explode( '|', $seller_id );
		$mp_location = isset( $mp_array[0] ) ? $mp_array[0] : '';

		if ( empty( $mp_location ) ) {
			echo json_encode( array(
				'status' => false,
				'message' => 'Marketplace Location not found'
			) ); 

			wp_die();
		}

		$marketplace_info =  ced_amz_marketplaceid_region_mapping( $mp_location ); 
		$marketplace_id   = isset( $marketplace_info['marketplace_id'] ) ? $marketplace_info['marketplace_id'] : '';

		if ( empty( $marketplace_id ) ) {
			echo  json_encode( array(
				'status' => false,
				'message' => 'Marketplace Id not found'
			) ); 

			wp_die();
		}

		$fld      = CED_AMAZON_DIRPATH . 'admin/amazon/productTypes/';
		$fileName = 'productTypes_' . $mp_location . '.json';
		$fileName = sanitize_file_name($fileName);

		if ( !is_dir( $fld ) ) {
			wp_mkdir_p( $fld );
		}

		$amzonProductTypes = $fld . $fileName;
		
		$amzonCurlRequest = CED_AMAZON_DIRPATH . 'admin/amazon/lib/ced-amazon-curl-request.php';
		if ( file_exists( $amzonCurlRequest ) ) {
			require_once $amzonCurlRequest;
			$amzonCurlRequestInstance = new Ced_Amazon_Curl_Request();

		}

		$cat_topic = 'product-types';
		$cat_data  = array(
			'remote_shop_id' => $user_id,
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

				echo json_encode( array(
					'status'  => true,
					'message' => 'The product type has been successfully updated. All changes have been saved.'
				) ); 

			} else {
				echo json_encode( array(
					'status' => false,
					'message' => 'File System not found, unable to update product types'
				) ); 
			}

		} else {
			echo json_encode( array(
				'status' => false,
				'message' => isset( $response['message'] ) ? $response['message'] : 'Error in API response.'
			) ); 
		}

		wp_die();

	}
	

	public function ced_amz_del_tem( ) {

		$check_ajax = check_ajax_referer( 'ced-amazon-ajax-seurity-string', 'ajax_nonce' );
		if ( ! $check_ajax ) {
			return;
		}

		$template_id = isset( $_POST['template_id'] ) ? sanitize_text_field( $_POST['template_id'] ) : '';
		$seller_id   = isset( $_POST['seller_id'] ) ? sanitize_text_field( $_POST['seller_id'] ) : '';

		global $wpdb;

		$template_id = (int) $template_id;
		
		/** Prepare the SQL query */
		$table_name = $wpdb->prefix . 'ced_amazon_profiles';

		$ced_woo_amazon_mapping   = get_option( 'ced_woo_amazon_mapping', array() );
		$ced_woo_amazon_cat_array = isset( $ced_woo_amazon_mapping[ $seller_id ] ) ? $ced_woo_amazon_mapping[ $seller_id ] : array();

		if ( ! empty( $ced_woo_amazon_cat_array ) && is_array( $ced_woo_amazon_cat_array ) ) {

			unset( $ced_woo_amazon_mapping[ $seller_id ][ $template_id ] );
			update_option( 'ced_woo_amazon_mapping', $ced_woo_amazon_mapping );

		}
		
		/**  Execute the query */
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}ced_amazon_profiles WHERE id = %s", $template_id) );

		
		/** Check the result */
		if ( false !== $result ) {
			echo  json_encode( array( 'status' => true, 'message' => 'Template with ID ' . $template_id . 'deleted successfully.') );
		} else {
			echo  json_encode( array( 'status' => false, 'message' => 'Failed to delete template with ID ' . $template_id ) );
		}

		wp_die();
		
	}


	public function ced_amz_update_imported_orders( ) {

		$check_ajax = check_ajax_referer( 'ced-amazon-ajax-seurity-string', 'ajax_nonce' );
		if ( ! $check_ajax ) {
			return;
		}

		$id        = isset( $_POST['id'] ) ? sanitize_text_field( $_POST['id'] ) : '';
		$seller_id = isset( $_POST['seller_id'] ) ? sanitize_text_field( $_POST['seller_id'] ) : '';
		
		if ( empty( $seller_id ) ) {
			echo json_encode( array(
				'status' => false,
				'message' => 'Seller Id not found'
			) ); 

			wp_die();
		}

		$ced_amz_all_not_imported_orders = get_option( 'ced_amz_all_not_imported_orders', array() );
		$ced_amz_not_imported_orders     = isset( $ced_amz_all_not_imported_orders[$seller_id] ) ?  $ced_amz_all_not_imported_orders[$seller_id] : array();
		
		if ( isset( $ced_amz_not_imported_orders[$id] ) ) {
			unset( $ced_amz_not_imported_orders[$id] );
			$ced_amz_all_not_imported_orders[$seller_id] = $ced_amz_not_imported_orders;
			update_option( 'ced_amz_all_not_imported_orders', $ced_amz_all_not_imported_orders );

			echo json_encode( array(
				'status' => true,
				'message' => 'Order is marked as imported.'
			) ); 

		} else {
			echo json_encode( array(
				'status' => false,
				'message' => 'Unable to update order id.'
			) ); 

		}

		wp_die();


	}


	public function append_json_to_file($data, $filename = 'custom-data.json') {

		// Get the path to the WordPress uploads directory
		$upload_dir = wp_upload_dir();
		$file_path  = trailingslashit($upload_dir['basedir']) . $filename;

		// Create file if it doesn't exist
		if (!file_exists($file_path)) {
			file_put_contents($file_path, json_encode([]));
		}

		// Read current data
		$json_data = json_decode(file_get_contents($file_path), true);

		if (!is_array($json_data)) {
			$json_data = [];
		}

		// Append new data
		$json_data[] = $data;

		// Save back to the file
		file_put_contents($file_path, json_encode($json_data, JSON_PRETTY_PRINT));

		return true;

	}

	public function ced_amazon_manual_inventory_update_action( $params ) {

		$logger  = wc_get_logger();
		$context = array('source' => 'ced_amazon_manual_scheduler');
		$this->amazon_inventory_manager->ced_amazon_manual_inventory_update_listing($params);
	}

	public function ced_amazon_manual_price_update_action( $params ) {

		$logger  = wc_get_logger();
		$context = array('source' => 'ced_amazon_manual_price_sync');
		$this->amazon_price_manager->ced_amazon_manual_price_update_listing($params);
	}

	/**
	 * Process upload queue and send data to Amazon
	 */
	public function ced_amazon_process_upload_queue( $queue_args ) {
		// Call the feed manager function to process the queue
		$this->amazon_feed_manager->ced_amazon_process_upload_queue( $queue_args );
	}




}



?>
