<?php


if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/** Import the header */
ced_import_header();

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

$ced_amz_all_queue_list = get_option( 'ced_amz_schedule_actions', array() );

class Ced_Amazon_List_Queue extends WP_List_Table {

	public $ced_amz_all_queue_list;
	public $seller_id;
	public $user_id;
	public $region;
	public $seller_id_array;
	
	/**
	 * Class constructor 
	 */
	public function __construct() {

		$this->user_id   = isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '';
		$this->seller_id = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';

		$mplocation_arr = explode( '|', $this->seller_id );
		$mplocation     = isset( $mplocation_arr[1] ) ? $mplocation_arr[0] : '';

		// current region
		$this->region = ced_amz_get_region_by_mp_location( $mplocation );

		$ced_amz_all_queues = get_option( 'ced_amz_schedule_actions', array() );

		$this->ced_amz_all_queue_list = isset( $ced_amz_all_queues[$this->seller_id] ) ? $ced_amz_all_queues[$this->seller_id] : array();
		
		$this->ced_amz_all_queue_list =  array_reverse( $this->ced_amz_all_queue_list, true );


		parent::__construct(
			array(
				'singular' => __( 'Amazon queue', 'amazon-for-woocommerce' ), // singular name of the listed records
				'plural'   => __( 'Amazon queues', 'amazon-for-woocommerce' ), // plural name of the listed records
				'ajax'     => true, // does this table support ajax?
			)
		);

	}

	
	/**
	 *
	 * Function for getting current status
	 */
	public function current_action() {

		$action = false;
		if ( isset( $_GET['panel'] ) ) {  
			$action = isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '';
		} elseif ( isset( $_POST['action'] ) ) { 
			if ( ! isset( $_POST['ced_amazon_queue_view_actions'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ced_amazon_queue_view_actions'] ) ), 'ced_amazon_queue_view' ) ) {
				return;
			}
			
			$action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';
		} 

		return $action;
	}

	/**
	 *
	 * Function for preparing data to be displayed
	 */
	public function prepare_items() {

		/**
		 *  Function to list queue based on per page
		 *
		 * @param 'function'
		 * @param  integer 'limit'
		 * @return 'count'
		 * @since  1.0.0
		 */

		$current_per_page = isset( $_GET['per_page'] ) ? sanitize_text_field( $_GET['per_page'] ) : 20;

		/**
		 * Filter to modify number of queues per page
		 *
		 * @since 1.1.3
		 */
		$per_page = apply_filters( 'ced_amazon_queues_list_per_page', $current_per_page );
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

		$this->items = self::ced_amazon_queues( $per_page, $current_page );

		$count = self::get_count();
		
		// Set the pagination
		$this->set_pagination_args(
			array(
				'total_items' => $count,
				'per_page'    => $per_page,
				'total_pages' => ceil( $count / $per_page ),
			)
		);

		// $this->renderHTML();
		if ( ! $this->current_action() ) {
			
			// $this->items = self::ced_amazon_get_profiles( $per_page, $current_page );
			$this->renderHTML();
		} else {
			$this->process_bulk_action();
		}
		
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

		wp_nonce_field( 'ced_amazon_queue_view_page_nonce', 'ced_amazon_queue_view_nonce' );

		$url = ced_get_navigation_url(
			'amazon',
			array(
				'section'   => 'queue-view',
				'user_id'   => $this->user_id,
				'seller_id' => $this->seller_id,
			)
		);

		if ( 'bulk-delete' === $this->current_action() || ( isset( $_GET['action'] ) && 'bulk-delete' === $_GET['action'] ) ) {

			if ( ! isset( $_POST['ced_amazon_queue_view_actions'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ced_amazon_queue_view_actions'] ) ), 'ced_amazon_queue_view' ) ) {
				return;
			}

			$queueIds = isset( $sanitized_array['ced_amazon_queue_ids'] ) ? $sanitized_array['ced_amazon_queue_ids'] : array();
			if ( is_array( $queueIds ) && ! empty( $queueIds ) ) {
				
				$seller_id_array = explode( '|', $this->seller_id );
				$country         = isset( $seller_id_array[0] ) ? $seller_id_array[0] : '';
				
				$ced_amz_all_queue_list = get_option( 'ced_amz_schedule_actions', array() );
				foreach ( $queueIds as $index => $qid ) {
					unset( $ced_amz_all_queue_list[$this->seller_id][ $qid ] );
				}
	
				update_option( 'ced_amz_schedule_actions', $ced_amz_all_queue_list );
				wp_safe_redirect( $url );
				exit();

			} else {
				wp_safe_redirect( $url );
				exit();

			}
		} elseif ( isset( $_GET['panel'] ) && 'edit' == $_GET['panel'] ) {

			$file = CED_AMAZON_DIRPATH . 'admin/partials/queue-view.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		} else {

			wp_safe_redirect( $url );
			exit();

		}
	}


	/**
	 *
	 * Function to count number of responses in result
	 */
	public function get_count() {
		return count( $this->ced_amz_all_queue_list );
	}

	/*
	 *
	 * Text displayed when no  data is available
	 *
	 */
	public function no_items() {
		esc_html_e( 'No queues To Display.', 'amazon-for-woocommerce' );
	}

	/**
	 *
	 * Function for id column
	 */
	public function column_id( $item ) {
		print_r($item['id']);
	}

	/**
	 *
	 * Function for created on column
	 */
	public function column_created_on( $item ) {
		if ( !empty( $item['id'] ) ) {
			$created_time = floatval( $item['id'] );
			$created_time = $created_time - 300;
			print_r( ced_woo_datetime_from_strtotime( $created_time, 'Y-m-d H:i:s' ) );
		} else {
			echo '-';
		}
		
	}


	/**
	 *
	 * Function for id column
	 */
	public function column_scheduled_on( $item ) {
		if ( !empty( $item['id'] ) ) {
			print_r( ced_woo_datetime_from_strtotime( $item['id'], 'Y-m-d H:i:s' ) );
		} else {
			echo '-';
		}
	}


	/**
	 *
	 * Function for action column
	 */
	public function column_action( $item ) {
		
		$html = '<b>' . ucfirst( $item['action'] ) . '</b>';
		print_r( $html );

	}

	/**
	 *
	 * Function for status column
	 */
	public function column_status( $item ) {
		
		$status_cls = $this->ced_amazon_return_amazon_status_classes( $item['args']['status'] );
		$html       = '<div class="ced-' . esc_attr( $status_cls ) . '-button-wrap"><a class="ced-' . 
		
		esc_attr( $status_cls ) . '-link"><span class="ced-circle" style=""></span> ' . 
		esc_attr( ucfirst( $item['args']['status'] ) ) . '</a> </div>';

		print_r( $html );


	}

	/**
	 *
	 * Function for response column
	 */
	public function column_response( $item ) {
		
		$feed_id = isset( $item['args']['feed-id'] ) ? $item['args']['feed-id'] : '';

		$class = '';

		if ( empty( $feed_id ) || 'pending' == $item['args']['status'] ) {
			$class = 'disabled-link';
		}

		$url = ced_get_navigation_url(
			'amazon',
			array(
				'section'     => 'feed-view',
				'feed-id'     => $feed_id,
				'user_id'     => $this->user_id,
				'seller_id'   => $this->seller_id,
			)
		);

		$html = '<a class="feed-view ' . $class . '" target="_blank"  href="' . esc_url( $url ) . '" > ' . esc_html__( 'Feed Response', 'amazon-for-woocommerce' ) . '</a>';
		print_r( $html );


	}


	public function ced_amazon_return_amazon_status_classes( $queueStatus = 'processing' ) {

		$status = 'processing';

		if ( 'created' == strtolower( $queueStatus ) || 'unshipped' == strtolower( $queueStatus ) ) {
			$status = 'processing';
		} elseif ( 'pending' == strtolower( $queueStatus ) || 'pendingavailability' == strtolower( $queueStatus ) ) {
			$status = 'pending';
		} elseif ( 'partiallyshipped' == strtolower( $queueStatus ) || 'shipped' == strtolower( $queueStatus ) ) {
			$status = 'completed';
		} elseif ( 'canceled' == strtolower( $queueStatus ) || 'unfulfillable' == strtolower( $queueStatus ) || 'failed' == strtolower( $queueStatus ) ) {
			$status = 'cancelled';
		}

		return $status;
	}



	public function column_woocommerce_status( $items ) {

		$status = '';
		$status = isset( $item['args']['status'] ) ? $item['args']['status'] : 'pending';
		$html   = '<div class="ced-' . esc_attr( $status ) . '-button-wrap"><a class="ced-' . esc_attr( $status ) . '-link"><span class="ced-circle" style=""></span> ' . esc_attr( ucfirst( $status ) ) . '</a> </div>';

		print_r( $html );

	}


	public function column_queueed_items( $item ) {

		$count = 0;
		$count = isset( $item['args']['product_ids'] ) ? count( $item['args']['product_ids'] ) : $item['args']['product_count'];
		
		if ( 1 < $count ) {
			$text = 'Items'; 
		} else {
			$text = 'Item';
		}
		echo '<p> <a herf="#">  ' . esc_html__( $count, 'amazon-for-woocommerce' ) . ' ' . esc_html( $text, 'amazon-for-woocommerce' ) . '</a></p>';

	}


	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	public function get_columns() {

		$columns = array(
			'cb'                     => '<input type="checkbox" />',
			'id'                     => __( 'Queue ID', 'amazon-for-woocommerce' ),
			'created_on'             => __( 'Created on', 'amazon-for-woocommerce' ),
			'action'                 => __( 'Action', 'amazon-for-woocommerce' ),
			'scheduled_on'           => __( 'Schudeled on', 'amazon-for-woocommerce' ),
			'queueed_items'          => __( 'Items', 'amazon-for-woocommerce' ),
			'status'                 => __( 'Status', 'amazon-for-woocommerce' ),
			'response'               => __( 'Response', 'amazon-for-woocommerce' ),
			
		);

		/**
		 *  Function to list queue based on per page
		 *
		 * @param 'function'
		 * @param  integer 'limit'
		 * @return 'count'
		 * @since  1.0.0
		 */
		$columns = apply_filters( 'ced_amazon_queues_columns', $columns );
		return $columns;
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
			'<input type="checkbox" name="ced_amazon_queue_ids[]" value="%s" class="ced_amazon_queue_ids"/>',
			$item['id']
		);
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
			echo '<select name="action' . esc_attr( $two ) . '" class="bulk-action-selector ">';
			echo '<option value="-1">' . esc_attr( 'Bulk actions' ) . "</option>\n";

			foreach ( $this->_actions as $name => $title ) {
				$class = 'edit' === $name ? ' class="hide-if-no-js"' : '';

				echo "\t" . '<option value="' . esc_attr( $name ) . '"' . esc_attr( $class ) . '>' . esc_attr( $title ) . "</option>\n";
			}

			echo "</select>\n";

			submit_button( __( 'Apply' ), 'action', '', false, array( 'id' => 'ced_amazon_queue_bulk_operation' ) );
			echo "\n";
		endif;
	}

	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = array(
			'bulk-delete' => __( 'Delete', 'amazon-for-woocommerce' ),
		);
		return $actions;
	}

	/**
	 *
	 * Function to renderHTML
	 */
	public function renderHTML() {
		
		$this->user_id   = isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '';
		$queue_error_log = get_option( 'ced_amazon_queue_fetch_log_' . $this->user_id );
		
		?>

		<!-- <div class="ced_amazon_fetch_queues_button_wrap" style="margin-top: 20px;" >
			<?php // ced_woo_timestamp(); ?>
		</div> -->
		
		<!-- Render amazon queue detail via ajax in modal -->
		
		<?php
		if ( ! empty( $queue_error_log ) ) {
			?>
			<section class="woocommerce-inbox-message plain">
				<div class="woocommerce-inbox-message__wrapper">
					<div class="woocommerce-inbox-message__content">
						<span class="woocommerce-inbox-message__date"><?php echo esc_html( ced_amazon_time_elapsed_string( $queue_error_log['timestamp'] ) ); ?></span>
						<h3 class="woocommerce-inbox-message__title"><?php echo esc_html__( 'Whoops! It looks like there were some errors in fetching your Amazon queues.', 'amazon-for-woocommerce' ); ?></h3>
						<div class="woocommerce-inbox-message__text">
							<?php
							foreach ( $queue_error_log as $key => $fetch_error ) {
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

		// $this->search_box( 'Search', 'search_id', 'search_queue' );

		wp_nonce_field( 'ced_amazon_queue_filter_page_nonce', 'ced_amazon_queue_filter_nonce' );

		echo '</div>';
		echo '</form>';
		echo '</div>';

		?>
		
		<div id="post-body" class="metabox-holder ced-marketplace-queue-wrapper columns-2">
			<div id="">
				<div class="meta-box-sortables ui-sortable">
					<form method="post">
						<?php
							wp_nonce_field( 'ced_amazon_queue_view', 'ced_amazon_queue_view_actions' );
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
	 *  Function to get all the queues
	 *
	 */
	public function ced_amazon_queues( $per_page = 10, $page_number = 1 ) {
		
		/** Add filter on queues on the basis of mp_location. */
		$ced_amz_queue_list = array();

		if ( !empty( $this->ced_amz_all_queue_list ) && is_array( $this->ced_amz_all_queue_list ) ) {
			foreach ( $this->ced_amz_all_queue_list as $queue_id => $queqe_data ) {
				if ( $this->seller_id == $queqe_data['args']['seller_id'] ) {
				  $ced_amz_queue_list[$queue_id] = $queqe_data;
				}
			}
		}

		// Apply pagination
		$offset             = ( $page_number - 1 ) * $per_page;
		$ced_amz_queue_list = array_slice( $ced_amz_queue_list, $offset, $per_page, true );
		
		return $ced_amz_queue_list;
	}


}


$ced_amazon_queues_obj = new Ced_Amazon_List_Queue();
$ced_amazon_queues_obj->prepare_items();


?>
