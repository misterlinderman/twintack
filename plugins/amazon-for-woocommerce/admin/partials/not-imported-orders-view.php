<?php

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/** Import the header */
ced_import_header();

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}


use Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore;
use Automattic\WooCommerce\Utilities\OrderUtil as CedAmazonHOPS;

/* Not imported Orders */
class Ced_Amazon_List_Not_Imp_Orders extends WP_List_Table {


	public $create_amz_order_hops = false;
	public $item_data;
	public $user_id;
	public $seller_id;
	public $mplocation;
	public $ced_amz_all_not_imported_orders;
	public $ced_amz_not_imported_orders;

	/**
	* Class constructor
	*
	*/
	public function __construct() {

		if ( CedAmazonHOPS::custom_orders_table_usage_is_enabled() ) {
			$this->create_amz_order_hops = true;
		}

		parent::__construct(
			array(
				'singular' => __( 'Amazon order', 'amazon-for-woocommerce' ), // singular name of the listed records
				'plural'   => __( 'Amazon orders', 'amazon-for-woocommerce' ), // plural name of the listed records
				'ajax'     => true, // does this table support ajax?
			)
		);

		$this->seller_id = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';
		$this->user_id   = isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '';

		$seller_id_array  = explode( '|', $this->seller_id );
		$this->mplocation = $seller_id_array[0] ?? '';

		$this->ced_amz_all_not_imported_orders = get_option( 'ced_amz_all_not_imported_orders', array() );
		$this->ced_amz_not_imported_orders     = isset( $this->ced_amz_all_not_imported_orders[$this->seller_id] ) ?  $this->ced_amz_all_not_imported_orders[$this->seller_id] : array();
		

	}

	
	/**
	 *
	 * Function for preparing data to be displayed
	 */
	public function prepare_items() {

		/**
		 *  Function to list order based on per page
		 *
		 * @param 'function'
		 * @param  integer 'limit'
		 * @return 'count'
		 * @since  1.0.0
		 */

		$current_per_page = isset( $_GET['per_page'] ) ? sanitize_text_field( $_GET['per_page'] ) : 20;

		/**
		 * Filter to modify number of orders per page
		 *
		 * @since 1.1.3
		 */
		$per_page = apply_filters( 'ced_amazon_not_imported_orders_list_per_page', $current_per_page );
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		// Column headers
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$current_page = $this->get_pagenum();
		if ( 1 < $current_page ) {
			$offset = $per_page * ( $current_page - 1 );
		} else {
			$offset = 0;
		}

		$this->items = self::ced_amazon_orders( $per_page, $current_page );

		$count = self::get_count();
		
		// Set the pagination
		$this->set_pagination_args(
			array(
				'total_items' => $count,
				'per_page'    => $per_page,
				'total_pages' => ceil( $count / $per_page ),
			)
		);

		if ( ! $this->current_action() ) {

			$this->items = self::ced_amazon_orders( $per_page, $current_page );
			$this->renderHTML();
		} else {
			$this->process_bulk_action();
		}
	}


	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	public function column_cb( $item ) {
			
		return sprintf(
			'<input type="checkbox" name="amazon_order_ids[]" value="%s" class="amazon_order_ids"/>',
			$item['order_data']['AmazonOrderId']
		);
		

	}



	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = array(
			'bulk-import' => __( 'Import', 'amazon-for-woocommerce' ),
		);
		return $actions;
	}


	/**
	 *
	 * Function for processing bulk actions
	 */
	public function process_bulk_action() {

		$sanitized_array = filter_input_array( INPUT_POST, FILTER_UNSAFE_RAW );

		if ( ! session_id() ) {
			session_start();
		}

		wp_nonce_field( 'ced_amazon_order_bulk_action_page_nonce', 'ced_amazon_order_bulk_action_nonce' );

		$url = ced_get_navigation_url(
			'amazon',
			array(
				'section'   => 'not-imported-orders-view',
				'user_id'   => $this->user_id,
				'seller_id' => $this->seller_id,
			)
		);

		
		if ( 'bulk-import' === $this->current_action() || ( isset( $_GET['action'] ) && 'bulk-import' === $_GET['action'] ) ) {
			if ( ! isset( $_POST['ced_amazon_orders_view_actions'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ced_amazon_orders_view_actions'] ) ), 'ced_amazon_order_view' ) ) {
				return;
			}

			$amazon_order_ids = isset( $sanitized_array['amazon_order_ids'] ) ? $sanitized_array['amazon_order_ids'] : array();

			if ( is_array( $amazon_order_ids ) && ! empty( $amazon_order_ids ) ) {

				global $wpdb;
				$seller_id_array = explode( '|', $this->seller_id );
				$country         = isset( $seller_id_array[0] ) ? $seller_id_array[0] : '';

				if ( class_exists( 'Ced_Umb_Amazon_Order_Manager' ) ) {
					$order_manager = Ced_Umb_Amazon_Order_Manager::get_instance();
				}

				$shop_data = ced_amz_marketplaceid_region_mapping( $this->mplocation );
				$region    = $shop_data['region_value'] ?? '';
				$region    = strtolower($region);

				foreach ( $amazon_order_ids as $id ) {

					$order_id = $order_manager->is_umb_order_exists( $id );
					if ( $order_id ) {
						
						$ced_amz_all_not_imported_orders = get_option( 'ced_amz_all_not_imported_orders', array() );
						if ( array_key_exists( $id , $ced_amz_all_not_imported_orders[$this->seller_id] ) ) {
							unset( $ced_amz_all_not_imported_orders[$this->seller_id][ $id ] );
						} 
						update_option( 'ced_amz_all_not_imported_orders', $ced_amz_all_not_imported_orders);

						continue;

					}

					$params = array();
					
					$params['mplocation'] =  $this->mplocation;
					$params['cron']       =  false;
					$params['seller_id']  =  $this->seller_id;
					$params['region']     =  $region;
					
					$order_manager->fetchOrders( $params );
					
				}

				wp_safe_redirect( $url );
				exit();

			} else { 
				wp_safe_redirect( $url );
				exit();

			}
		} else { 
			wp_safe_redirect( $url );
			exit();

		}
	}


	/**
	 *
	 * Render bulk actions
	 */
	protected function bulk_actions( $which = '' ) {
		if ( 'top' == $which ) :
			if ( is_null( $this->_actions ) ) {
				$this->_actions = $this->get_bulk_actions();
				/**
				 * Filters the list table Bulk Actions drop-down.
				 *
				 * The dynamic portion of the hook name, `$this->screen->id`, refers
				 * to the ID of the current screen, usually a string.
				 *
				 * This filter can currently only be used to remove bulk actions.
				 *
				 * @since 3.5.0
				 *
				 * @param array $actions An array of the available bulk actions.
				 */
				$this->_actions = apply_filters( "bulk_actions-{$this->screen->id}", $this->_actions );
				$two            = '';
			} else {
				$two = '2';
			}

			if ( empty( $this->_actions ) ) {
				return;
			}

			echo '<label for="bulk-action-selector-' . esc_attr( $which ) . '" class="screen-reader-text">' . esc_attr( 'Select bulk action' ) . '</label>';
			echo '<select name="action' . esc_attr( $two ) . '" class="ced_amazon_select_amazon_order_action">';
			echo '<option value="-1">' . esc_attr( 'Bulk actions' ) . "</option>\n";

			foreach ( $this->_actions as $name => $title ) {
				$class = 'edit' === $name ? ' class="hide-if-no-js"' : '';

				echo "\t" . '<option value="' . esc_attr( $name ) . '"' . esc_attr( $class ) . '>' . esc_html( $title, 'amazon-for-woocommerce' ) . "</option>\n";
			}

			echo "</select>\n";

			wp_nonce_field( 'ced_amazon_order_bulk_action_page_nonce', 'ced_amazon_order_bulk_action_nonce' );
			submit_button( __( 'Apply' ), 'action', 'doaction', false, array( 'id' => 'ced_amazon_order_bulk_operation' ) );
			echo "\n";
		endif;
	}

	/**
	 *
	 * Function to count number of responses in result
	 */
	public function get_count() {
		global $wpdb;

		if ( ! isset( $_GET['s'] ) ) {
			return count($this->ced_amz_not_imported_orders);
		} elseif ( isset( $_GET['s'] ) ) {
			$amz_order_id = sanitize_text_field( $_GET['s'] );
			$id           = $this->ced_amz_not_imported_orders[ $amz_order_id ] ?? '';
			return $id ? count( array( $id ) ) : 0;
		}

		return count( $orders_post_id );
	}

	/*
	 *
	 * Text displayed when no  data is available
	 *
	 */
	public function no_items() {
		esc_html_e( 'No Orders To Display.', 'amazon-for-woocommerce' );
	}
	

	/**
	 *
	 * Function for order Id column
	 */
	public function column_amazon_order_id( $item ) {

		$actions = array();
		
		$format = '<button type="button" data-amz-order-id="%s" data-action="%s" class="button-link ced_amz_imported_orders"  aria-expanded="false" >%s</button>';

		$actions['mark_imported'] = sprintf(
			$format,
			$item['order_data']['AmazonOrderId'],
			'edit',
			esc_html__( 'Mark as imported', 'amazon-for-woocommerce' )
		);

		echo '<span>' . esc_html__( $item['order_data']['AmazonOrderId'], 'amazon-for-woocommerce' ) . '</span>';
		return $this->row_actions( $actions );

	}

	/**
	 *
	 * Function for order status column
	 */
	public function column_order_status( $item ) {
		
		$status = $this->ced_amazon_return_amazon_status_classes( $item['order_data']['OrderStatus'] );
		$html   = '<div class="ced-' . esc_attr( $status ) . '-button-wrap"><a class="ced-' . esc_attr( $status ) . '-link"><span class="ced-circle" style=""></span> ' . esc_attr( ucfirst( $item['order_data']['OrderStatus'] ) ) . '</a> </div>';
		print_r( $html );

	}


	public function column_ordered_items( $item ) {

		$count = $item['item_data'] ? count( $item['item_data'] ) : 0;

		if ( 1 < $count ) {
			$text = 'Items';
		} else {
			$text = 'Item';
		}
		echo '<p> <a herf="#">  ' . esc_html__( $count, 'amazon-for-woocommerce' ) . ' ' . esc_html( $text, 'amazon-for-woocommerce' ) . '</a></p>';
	}


	public function column_order_total( $item ) {

		$ccCode =  $item['order_data']['OrderTotal']['CurrencyCode'];
		$symbol = get_woocommerce_currency_symbol( $ccCode );
		echo '<p>' . esc_html__( $symbol ) . esc_html__($item['order_data']['OrderTotal']['Amount']) . '</p>';

	}


	/**
	 *
	 * Function display amazon fulfillment channel column
	 */
	public function column_fulfillment_channel( $item ) {
		
		echo '<b>' . esc_html__( $item['order_data']['FulfillmentChannel'] ) . '</b>';
		
	}


	public function column_purchased_on( $item ) {
		$cleanedDatetime = str_replace(['T', 'Z'], ' ', $item['order_data']['PurchaseDate'] );
		echo '<b>' . esc_html__( $cleanedDatetime ) . '</b>';
	}

	public function column_reason( $item ) {
		echo '<b>' . esc_html__( $item['message'] ) . '</b>';
	}


	public function ced_amazon_return_amazon_status_classes( $orderStatus = 'processing' ) {

		$status = 'processing';

		if ( 'created' == strtolower( $orderStatus ) || 'unshipped' == strtolower( $orderStatus ) ) {
			$status = 'processing';
		} elseif ( 'pending' == strtolower( $orderStatus ) || 'pendingavailability' == strtolower( $orderStatus ) ) {
			$status = 'pending';
		} elseif ( 'partiallyshipped' == strtolower( $orderStatus ) || 'shipped' == strtolower( $orderStatus ) ) {
			$status = 'completed';
		} elseif ( 'canceled' == strtolower( $orderStatus ) || 'unfulfillable' == strtolower( $orderStatus ) ) {
			$status = 'cancelled';
		}

		return $status;
	}

	
	public function column_import_btn( $item ) {

		echo '<div class="admin-custom-action-button-outer">';
		echo '<div class="admin-custom-action-show-button-outer">';
		echo '<button type="button" class="components-button is-primary ced_amz_import_order_manually" id="' . esc_attr($item['order_data']['AmazonOrderId']) . '" ><span>Import</span></button>';
		echo '</div></div>';

	}

	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	public function get_columns() {

		$columns = array(
			'cb'                  => '<input type="checkbox" />',
			'amazon_order_id'     => __( 'Amazon order ID', 'amazon-for-woocommerce' ),
			'order_status'        => __( 'Amazon status', 'amazon-for-woocommerce' ),
			'ordered_items'       => __( 'Ordered items', 'amazon-for-woocommerce' ),
			'order_total'         => __( 'Total', 'amazon-for-woocommerce' ),
			'fulfillment_channel' => __( 'Fulfillment channel', 'amazon-for-woocommerce' ),
			'purchased_on'        => __( 'Purchased on', 'amazon-for-woocommerce' ),
			'import_btn'          => __( 'Import', 'amazon-for-woocommerce' ),
			'reason'              => __( 'Reason', 'amazon-for-woocommerce' ),

		);

		/**
		 *  Function to list order based on per page
		 *
		 * @param 'function'
		 * @param  integer 'limit'
		 * @return 'count'
		 * @since  1.0.0
		 */
		$columns = apply_filters( 'ced_amazon_orders_columns', $columns );
		return $columns;
	}

	/**
	 * Columns to make sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array();
		return $sortable_columns;
	}

	/**
	 *
	 * Function to renderHTML
	 */
	public function renderHTML() {
		
		$order_error_log = get_option( 'ced_amazon_order_fetch_log_' . $this->user_id );
		?>
		
		<div class="ced_amazon_fetch_orders_button_wrap" style="margin-top: 20px;" >

			<button data-id="<?php echo esc_attr( isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '' ); ?>" style="margin-left:5px; margin-bottom: 15px;"  title="Import all orders" type="button" class="components-button is-primary ced_amazon_import_unimported_orders">
				<?php echo esc_html__( 'Import all orders', 'amazon-for-woocommerce' ); ?>
			</button>

		</div>

		<!-- Render amazon order detail via ajax in modal -->
		<?php
		if ( ! empty( $order_error_log ) ) {
			?>
			<section class="woocommerce-inbox-message plain">
				<div class="woocommerce-inbox-message__wrapper">
					<div class="woocommerce-inbox-message__content">
						<span class="woocommerce-inbox-message__date"><?php echo esc_html( ced_amazon_time_elapsed_string( $order_error_log['timestamp'] ) ); ?></span>
						<h3 class="woocommerce-inbox-message__title"><?php echo esc_html__( 'Whoops! It looks like there were some errors in fetching your Amazon Orders.', 'amazon-for-woocommerce' ); ?></h3>
						<div class="woocommerce-inbox-message__text">
							<?php
							foreach ( $order_error_log as $key => $fetch_error ) {
								if ( is_numeric( $key ) ) {
									?>
									<b><span><?php echo esc_html( $fetch_error ); ?></span></b><br>
									<?php
								}
							}
							?>
						</div>
					</div>
				</div>
			</section>
			<?php
		}

		echo '<div class="ced_amazon_wrap">';
		echo '<form method="post" action="">';
		echo '<div class="ced_amazon_top_wrapper">';

		$this->search_box( 'Search', 'search_id', 'search_order' );

		wp_nonce_field( 'ced_amazon_order_filter_page_nonce', 'ced_amazon_order_filter_nonce' );

		echo '</div>';
		echo '</form>';
		echo '</div>';

		?>
		
	<div id="post-body" class="metabox-holder ced-marketplace-order-wrapper columns-2">
		<div id="">
			<div class="meta-box-sortables ui-sortable">
				<form method="post">
					<?php
						wp_nonce_field( 'ced_amazon_order_view', 'ced_amazon_orders_view_actions' );
						$this->display();
					?>
				</form>	
 
			</div>
		</div>
		<div class="clear"></div>
	</div>

		<?php
	}
	/*
	 *
	 *  Function to get all the orders
	 *
	 */
	public function ced_amazon_orders( $per_page = 10, $page_number = 1 ) {

		$filterFile = CED_AMAZON_DIRPATH . 'admin/partials/order-filters.php';
		if ( file_exists( $filterFile ) ) {
			require_once $filterFile;
		}

		global $wpdb;
		$offset = ( $page_number - 1 ) * $per_page;

		$mplocation_arr = explode( '|', $this->seller_id );
		$mplocation     = isset( $mplocation_arr[1] ) ? $mplocation_arr[0] : '';

		$order_detail = array();

		if ( ! isset( $_GET['s'] ) ) {
			// no order search

			if ( 20 < count( $this->ced_amz_not_imported_orders ) ) {
				$chunks = array_chunk( $this->ced_amz_not_imported_orders, 20);
				if ( isset( $chunks[ $page_number - 1 ] ) ) {
					$order_detail = $chunks[ $page_number - 1 ];
				} 
			   
			} else {
				$order_detail = $this->ced_amz_not_imported_orders;
			}

		} elseif ( isset( $_GET['s'] ) ) {
			// order search condition
			$amz_order_id = sanitize_text_field( $_GET['s'] );
			$order_detail = $this->ced_amz_not_imported_orders[ $amz_order_id ] ? array( $this->ced_amz_not_imported_orders[ $amz_order_id ] ) : array();
		}

		
		$filterFile = CED_AMAZON_DIRPATH . 'admin/partials/order-filters.php';
		if ( file_exists( $filterFile ) ) {
			require_once $filterFile;
			$instanceOf_FilterClass = new FilterClass();
		} else {
			return;
		}

		if ( isset( $_POST['s'] ) ) {
			if ( isset( $_POST['ced_amazon_order_filter_nonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['ced_amazon_order_filter_nonce'] ), 'ced_amazon_order_filter_page_nonce' ) ) {
				$s          = isset( $_POST['s'] ) ? sanitize_text_field( $_POST['s'] ) : '';
				$woo_orders = $instanceOf_FilterClass->ced_amazon_order_search_box();

			}
		}

		return $order_detail;
	}
}


$ced_amazon_orders_obj = new Ced_Amazon_List_Not_Imp_Orders();
$ced_amazon_orders_obj->prepare_items();


?>
