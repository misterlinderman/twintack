<?php

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/** Import the header */
ced_import_header();

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

require_once CED_AMAZON_DIRPATH . 'admin/amazon/lib/class-feed-manager.php';

$notices = array();

if ( isset( $_POST['ced_amazon_product_bulk_action_nonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['ced_amazon_product_bulk_action_nonce'] ), 'ced_amazon_product_bulk_action_page_nonce' ) ) {

	if ( isset( $_POST['doaction'] ) ) {

		$seller_id = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';
		
		if ( ! empty( $seller_id ) ) {
			$mplocation_arr = explode( '|', $seller_id );
			$mplocation     = isset( $mplocation_arr[0] ) ? $mplocation_arr[0] : '';
		}
		$product_action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : -1;

		$sanitized_array = filter_input_array( INPUT_POST, FILTER_UNSAFE_RAW );
		$proIds          = isset( $sanitized_array['amazon_product_ids'] ) ? $sanitized_array['amazon_product_ids'] : array();

		$allset = true;

		$marketplace_array = ced_amz_marketplaceid_region_mapping( $mplocation );
		$marketplace       = $marketplace_array['marketplace_id'];

		$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );
		if ( isset( $saved_amazon_details[ $seller_id ] ) && ! empty( $saved_amazon_details[ $seller_id ] ) && is_array( $saved_amazon_details[ $seller_id ] ) ) {
			$shop_data = $saved_amazon_details[ $seller_id ];
		}

		$remote_shop_id = isset( $shop_data['seller_next_shop_id'] ) ? $shop_data['seller_next_shop_id'] : '';

		if ( empty( $product_action ) || -1 == $product_action ) {
			$allset    = false;
			$message   = __( 'Please select the bulk actions to perform an action!', 'amazon-for-woocommerce' );
			$classes   = 'error is-dismissable';
			$notices[] = array(
				'message' => $message,
				'classes' => $classes,
			);
		}

		if ( empty( $seller_id ) || '' == $seller_id ) {
			$allset    = false;
			$message   = __( 'Seller ID is missing to perform the action!', 'amazon-for-woocommerce' );
			$classes   = 'error is-dismissable';
			$notices[] = array(
				'message' => $message,
				'classes' => $classes,
			);
		}

		if ( empty( $mplocation ) || '' == $mplocation ) {
			$allset    = false;
			$message   = __( 'Seller location is missing to perform action!', 'amazon-for-woocommerce' );
			$classes   = 'error is-dismissable';
			$notices[] = array(
				'message' => $message,
				'classes' => $classes,
			);
		}

		if ( empty( $marketplace ) || -1 == $marketplace ) {
			$allset    = false;
			$message   = __( 'No marketplace is activated!', 'amazon-for-woocommerce' );
			$classes   = 'error is-dismissable';
			$notices[] = array(
				'message' => $message,
				'classes' => $classes,
			);
		}

		if ( ! is_array( $proIds ) ) {

			$allset    = false;
			$message   = __( 'Please select products to perform the bulk action!', 'amazon-for-woocommerce' );
			$classes   = 'error is-dismissable';
			$notices[] = array(
				'message' => $message,
				'classes' => $classes,
			);
		}

		if ( $allset ) {

			if ( class_exists( 'Ced_Umb_Amazon_Feed_Manager' ) ) {
				$feed_manager = Ced_Umb_Amazon_Feed_Manager::get_instance( );
				$notice       = $feed_manager->process_feed_request( $product_action, $marketplace, $proIds, $mplocation, $seller_id, $remote_shop_id );

				$notice_array = json_decode( $notice, true );

				if ( is_array( $notice_array ) ) {
					$message = isset( $notice_array['message'] ) ? $notice_array['message'] : '';
					$classes = isset( $notice_array['classes'] ) ? $notice_array['classes'] : 'error is-dismissable';
					$refresh = isset( $notice_array['refresh'] ) ? $notice_array['refresh'] : false;

					$notices[] = array(
						'message' => $message,
						'classes' => $classes,
						'refresh' => $refresh

					);
				} else {

					$message   = __( 'An unexpected error occurred. Please try again.', 'amazon-for-woocommerce' );
					$classes   = 'notice notice-error is-dismissable';
					$notices[] = array(
						'message' => $message,
						'classes' => $classes,
					);
				}
			}
		}
	}
}


if ( count( $notices ) ) {
	foreach ( $notices as $notice_array ) {
		$message = isset( $notice_array['message'] ) ? $notice_array['message'] : '';
		$classes = isset( $notice_array['classes'] ) ? esc_attr( $notice_array['classes'] ) : 'error is-dismissable';

		if ( strpos( $classes, 'error' ) !== false ) {
			$classes = 'ced-error';
		}
		if ( strpos( $classes, 'success' ) !== false ) {
			$classes = 'ced-success'; 
		}
		if ( ! empty( $message ) ) {
			?>
			<div class="<?php echo esc_attr( $classes ); ?>">
				<p><?php echo wp_kses_post($message); ?></p>
			</div>
			<?php
		}
	}

	if ( isset( $notice_array['refresh'] ) && $notice_array['refresh'] ) {
		header('Refresh:5;' );
	}
	unset( $notices );
	
	
}

$user_id                    = isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '';
$ced_amazon_remote_shop_ids =  get_option( 'ced_amazon_remote_shop_ids', array() );
$hasSuspendedListings       =  $ced_amazon_remote_shop_ids[ $user_id ]['hasSuspendedListings'] ?? false;
$ced_mp_name                =  $ced_amazon_remote_shop_ids[ $user_id ]['ced_mp_name'] ?? false;

$parts = explode('_', $ced_mp_name);
$mploc = isset($parts[1]) ? strtoupper($parts[1]) : strtoupper($parts[0]);

if ( $hasSuspendedListings ) {
	?>

<div class="notice notice-error is-dismissable ced_ntc_mgn">
	<p><?php echo esc_html('You cannot upload products to Amazon ' . strtoupper($mploc) . '. Your seller account currently has suspended listings.', 'amazon-for-woocommerce'); ?></p>
</div>

<?php
} 


class AmazonListProducts extends WP_List_Table {

	public $show_reset;
	public $user_id;
	public $seller_id;
	public $rounding_option               = '';
	public $mrkp_val                      = 0;
	public $mrkup_type                    = '';
	public $max_stock                     = 0;
	public $ced_amazon_rsrve_stck         = 0;
	public $ced_amazon_product_stock_type = '';
	public $amazon_regions                = array();
	public $seller_id_val; 
	public $ced_amazon_product_price_type = '';
	public $json_validator_errors         = array();
	public $items_ids                     = array();    
	
	/**
	 *
	 * Function to construct
	 */
	public function __construct() {

		parent::__construct(
			array(
				'singular' => __( 'ced-amazon-product', 'amazon-for-woocommerce' ),
				'plural'   => __( 'ced-amazon-products', 'amazon-for-woocommerce' ),
				'ajax'     => true,
			)

		);

		$this->seller_id = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';
		$this->user_id   = isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '';

		$this->seller_id_val = str_replace( '|', '_', $this->seller_id );

		$seller_global_settings = array();
		$global_settings        = get_option( 'ced_amazon_global_settings', array() );
		$general_options        = get_option( 'ced_amazon_general_options', array() );

		if ( isset( $global_settings[ $this->seller_id ] ) && ! empty( $global_settings[ $this->seller_id ] ) ) {
			$seller_global_settings = $global_settings[ $this->seller_id ];
		}

		if ( isset( $general_options[ 'general_options' ] ) && ! empty( $general_options[ 'general_options' ] ) ) {
			$seller_general_options = $general_options[ 'general_options' ];
		}

		// if ( isset( $seller_global_settings['ced_amazon_product_markup_type'] ) && ! empty( $seller_global_settings['ced_amazon_product_markup_type'] ) && isset( $seller_global_settings['ced_amazon_product_markup'] ) && ! empty( $seller_global_settings['ced_amazon_product_markup'] ) ) {

			$this->mrkup_type      = $seller_global_settings['ced_amazon_product_markup_type'] ?? '';
			$this->mrkp_val        = $seller_global_settings['ced_amazon_product_markup'] ?? '';
			$this->rounding_option = $seller_global_settings['ced_amazon_product_rounding_off_type'] ?? '';

		// }

		if ( isset( $seller_general_options['ced_amazon_listing_stock'] ) && ! empty( $seller_general_options['ced_amazon_listing_stock'] ) ) {
			$this->max_stock = $seller_general_options['ced_amazon_listing_stock']['default'];
		}

		if ( isset( $seller_general_options['ced_amazon_rsrve_stck'] ) && ! empty( $seller_general_options['ced_amazon_rsrve_stck'] ) ) {
			$this->ced_amazon_rsrve_stck = $seller_general_options['ced_amazon_rsrve_stck']['default'];
		}
		

		if ( isset( $seller_global_settings['ced_amazon_product_stock_type'] ) && ! empty( $seller_global_settings['ced_amazon_product_stock_type'] ) ) {
			$this->ced_amazon_product_stock_type = $seller_global_settings['ced_amazon_product_stock_type'];
		}

		if ( isset( $seller_global_settings['ced_amazon_product_price_type'] ) && ! empty( $seller_global_settings['ced_amazon_product_price_type'] ) ) {
			$this->ced_amazon_product_price_type = $seller_global_settings['ced_amazon_product_price_type'];
		}


		$ced_amazon_regions_info = array();
		$url_file                = CED_AMAZON_DIRPATH . 'admin/partials/amazonRegions.php';
		if ( file_exists( $url_file ) ) {
			require $url_file;
		}

		$this->amazon_regions = $ced_amazon_regions_info;

	}

	public function single_row($item) {
		$id = 'post-' . $item['id']; // Your custom class
		print_r ('<tr id=' . $id . ' >');
		$this->single_row_columns($item);
		echo '</tr>';
	}


	/**
	 *
	 * Function for preparing data to be displayed
	 */
	public function prepare_items() {

		global $wpdb;

		/**
		 * Function to list order based on per page
		 *
		 * @param 'function'
		 * @param  integer 'limit'
		 * @return 'count'
		 * @since  1.0.0
		 */

		$current_per_page = isset( $_GET['per_page'] ) ? sanitize_text_field( $_GET['per_page'] ) : 20;

		/**
		 * Filter to modify number of products per page
		 *
		 * @since 1.1.3
		 */
		$per_page  = apply_filters( 'ced_amazon_products_per_page', $current_per_page );
		$post_type = 'product';
		$columns   = $this->get_columns();
		$hidden    = array();
		$sortable  = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$current_page = $this->get_pagenum();
		if ( 1 < $current_page ) {
			$offset = $per_page * ( $current_page - 1 );
		} else {
			$offset = 0;
		}

		$this->items = self::ced_amazon_get_product_details( $per_page, $current_page, $post_type );
		$count       = self::get_count( $per_page, $current_page );
		$this->set_pagination_args(
			array(
				'total_items' => $count,
				'per_page'    => $per_page,
				'total_pages' => ceil( $count / $per_page ),
			)
		);

		if ( $this->current_action() ) {
			$this->process_bulk_action();
		}
		$this->renderHTML();

	}

	/**
	 *
	 * Function for get product data
	 */
	public function ced_amazon_get_product_details( $per_page = '', $page_number = '', $post_type = '' ) {

		$filterFile = CED_AMAZON_DIRPATH . 'admin/partials/products-filters.php';
		if ( file_exists( $filterFile ) ) {
			require_once $filterFile;
		}

		$instanceOf_FilterClass = new FilterClass();
		$args                   = $this->GetFilteredData( $per_page, $page_number );

		if ( ! empty( $args ) && ( isset( $args['tax_query'] ) || isset( $args['meta_query'] ) || isset( $args['s'] ) ) ) {
			$args = $args;

		} else {
			$args = array(
				'post_type'      => $post_type,
				'posts_per_page' => $per_page,
				'paged'          => $page_number,
			);
		}

		$args['product_type'] = array( 'simple', 'variable' );
		$args['post_status']  = 'publish';
		$args['order']        = 'DESC';
		$args['orderby']      = 'ID';

		$loop     = new WP_Query( $args );
		$products = $loop->posts;

		$search = isset($args['s']) ? $args['s'] : '';
		if (empty($products)) {
			$args['meta_query'] = array(
				array(
					'key'     => '_sku',
					'value'   => $search,
					'compare' => 'LIKE',
				),
			);
			$loop               = new WP_Query( $args );
			$products           = $loop->posts;
		   
		}

		$woo_categories = get_terms( 'product_cat' );
		$woo_products   = array();

		foreach ( $products as $key => $value ) {

			$product_data     = wc_get_product( $value->ID );
			$get_product_data = $product_data->get_data();
			if ( ! empty( $get_product_data['category_ids'] ) ) {
				rsort( $get_product_data['category_ids'] );
			}
			$woo_products[ $key ]['category_id']  = isset( $get_product_data['category_ids'] ) ? $get_product_data['category_ids'] : '';
			$woo_products[ $key ]['id']           = $value->ID;
			$woo_products[ $key ]['name']         = $get_product_data['name'];
			$woo_products[ $key ]['stock']        = $get_product_data['stock_quantity'];
			$woo_products[ $key ]['stock_status'] = $get_product_data['stock_status'];
			$woo_products[ $key ]['sku']          = $get_product_data['sku'];
			
			$type = $product_data->get_type();
			
			$woo_products[ $key ]['type'] = $type;
			if ( 'variable' == $type ) {

				// Regular price min and max
				$min_regular_price = $product_data->get_variation_regular_price( 'min' );
				$max_regular_price = $product_data->get_variation_regular_price( 'max' );

				// Sale price min and max
				$min_sale_price = $product_data->get_variation_sale_price( 'min' );
				$max_sale_price = $product_data->get_variation_sale_price( 'max' );

				$woo_products[ $key ]['min_regular_price'] = $min_regular_price;
				$woo_products[ $key ]['max_regular_price'] = $max_regular_price;

				$woo_products[ $key ]['min_sale_price'] = $min_sale_price;
				$woo_products[ $key ]['max_sale_price'] = $max_sale_price;

				$children_ids = $product_data->get_children();
				if ( !empty( $children_ids) ) {
					foreach ( $children_ids as $key2 => $child_id ) {
						$woo_products[ $key ]['child_ids'][]      = $child_id;
						$this->json_validator_errors[ $child_id ] = get_post_meta( $child_id, 'ced_amz_json_validator_error_' . $this->seller_id , true );
					}
				}
				
			} else {
				$woo_products[ $key ]['regular_price'] = $get_product_data['regular_price'];
				$woo_products[ $key ]['sale_price']    = $get_product_data['sale_price'];
			}

			$this->items_ids[$value->ID] = array( 'sku' => $get_product_data['sku'] );

			if ( !empty( $woo_products[ $key ]['child_ids'] ) ) {
				$this->items_ids[$value->ID]['child_ids'] =  $woo_products[ $key ]['child_ids'];
			}

			$Image_url_id                  = $get_product_data['image_id'];
			$woo_products[ $key ]['image'] = wp_get_attachment_url( $Image_url_id );
			foreach ( $woo_categories as $key1 => $value1 ) {
				if ( isset( $get_product_data['category_ids'] ) ) {
					foreach ( $get_product_data['category_ids'] as $key2 => $prodCat ) {
						if ( $value1->term_id == $prodCat ) {
							$woo_products[ $key ]['category'][] = $value1->name;
						}
					}
				}
			}


			$this->json_validator_errors[ $value->ID ] = get_post_meta( $value->ID, 'ced_amz_json_validator_error_' . $this->seller_id, true );

		}

		if ( isset( $_POST['filter_button'] ) ) {
			if ( isset( $_POST['ced_amazon_product_filter_nonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['ced_amazon_product_filter_nonce'] ), 'ced_amazon_product_filter_page_nonce' ) ) {
				$woo_products = $instanceOf_FilterClass->ced_amazon_filters_on_products();

			}
		} elseif ( isset( $_POST['s'] ) ) {
			if ( isset( $_POST['ced_amazon_product_filter_nonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['ced_amazon_product_filter_nonce'] ), 'ced_amazon_product_filter_page_nonce' ) ) {
				$s            = isset( $_POST['s'] ) ? sanitize_text_field( $_POST['s'] ) : '';
				$woo_products = $instanceOf_FilterClass->productSearch_box( $woo_products, $s );

			}
		}

		return $woo_products;
	}

	/**
	 *
	 * Text displayed when no data is available
	 */
	public function no_items() {
		esc_html_e( 'No Products To Show.', 'amazon-for-woocommerce' );
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

	/*
	 * Render the bulk edit checkbox
	 *
	 */
	public function column_cb( $item ) {
		echo '<div class="admin-custom-action-button-outer"><div class="admin-custom-action-show-button-outer">';
		return sprintf(
			'<input type="checkbox" name="amazon_product_ids[]" class="amazon_products_id" value="%s" /></div></div>',
			$item['id']
		);
	}

	/**
	 *
	 * Function for name column
	 */
	public function column_name( $item ) {

		$actions = array();

		$url           = get_edit_post_link( $item['id'], '' );
		$actions['id'] = 'ID:' . __( $item['id'] );

		$format = '<button type="button" data-comment-id="%d" data-post-id="%d" data-action="%s" class="%s button-link" aria-expanded="false" aria-label="%s">%s</button>';

		$actions['quickedit'] = sprintf(
			$format,
			$item['id'],
			$item['id'],
			'edit',
			'button-link editinline',
			esc_attr__( 'Quick edit this review inline', 'amazon-for-woocommerce' ),
			esc_html__( 'Amazon validation errors', 'amazon-for-woocommerce' )
		);

		$schm_url        = ced_get_navigation_url(
			'amazon',
			array(
				'section'     => 'schema-view',
				'user_id'     => $this->user_id,
				'seller_id'   => $this->seller_id,
				'product_id'  =>  $item['id']
			)
		);
		$actions['view'] = '<a class="product-schema" target="_blank" href="' . esc_url( $schm_url ) . '" > ' . esc_html__( 'Product schema view', 'amazon-for-woocommerce' ) . '</a>';

		echo '<b><a class="ced_amazon_prod_name" href="' . esc_url( $url ) . '" target="_blank">' . esc_html__( $item['name'], 'amazon-for-woocommerce' ) . '</a></b><br>';
		return $this->row_actions( $actions );

	}

	/**
	 *
	 * Function for profile column
	 */
	public function column_profile( $item ) {

		echo '<div class="admin-custom-action-button-outer"><div class="admin-custom-action-show-button-outer">';

		$terms = wp_get_post_terms(
			$item['id'],
			'product_cat',
			array(
				'order'   => '',
				'orderby' => '',
			)
		);

		$terms   = json_decode( wp_json_encode( $terms ), true );
		$cat_ids = array();
		if ( ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$cat_ids[] = $term['term_id'];
			}
		}

		$mapped                 = 0;
		$ced_woo_amazon_mapping = get_option( 'ced_woo_amazon_mapping', array() );
		$ced_woo_amazon_mapping = isset( $ced_woo_amazon_mapping[ $this->seller_id ] ) ? $ced_woo_amazon_mapping[ $this->seller_id ] : array();

		if ( ! empty( $ced_woo_amazon_mapping ) ) {
			foreach ( $ced_woo_amazon_mapping as $key => $woo_cat_array ) {

				$match_woo_cat = array_intersect( $woo_cat_array, $cat_ids );
				if ( is_array( $match_woo_cat ) && ! empty( $match_woo_cat ) ) {

					$mapped = $key;
					global $wpdb;
					$amazon_profiles = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ced_amazon_profiles WHERE `id` = %s", $key ), 'ARRAY_A' );
					$amazon_profiles = isset( $amazon_profiles[0] ) ? $amazon_profiles[0] : array();

					$product_type = isset( $amazon_profiles['product_type'] ) ? $amazon_profiles['product_type'] : '';
					if ( empty( $product_type ) ) {
						break;
					}

				}
			}
		}

		if ( $mapped ) {

			$url = ced_get_navigation_url(
				'amazon',
				array(
					'section'     => 'add-new-template',
					'template_id' => $mapped,
					'user_id'     => $this->user_id,
					'seller_id'   => $this->seller_id,
				)
			);
			echo '<a target="_blank" 
			href="' . esc_url( $url ) . '">'
				. esc_attr( $product_type ) . '</a>';

		} else {
			echo esc_attr( 'No template assigned' );
		}
	}



	/**
	 *
	 * Function for stock column
	 */
	public function column_stock( $item ) {

		if ( 'instock' == $item['stock_status'] ) {
			
			if ( '0' !== $this->max_stock && !empty($this->max_stock) && $item['stock'] > $this->max_stock ) {
				$qty = $this->max_stock;
			} else {
				$qty = $item['stock'];
			}
			
			$qty      = (int) $qty   - (int) $this->ced_amazon_rsrve_stck;
			$quantity = ( $qty >= 0 ) ? $qty : 0;

			if ( 0 == $quantity  || '0' == $quantity  ) {
				return '<div class="ced-connected-button-wrap"><a class="ced-connected-link"><b class="stock_alert_instock"><span class="ced-circle"></span>' . esc_attr( 'In stock', 'amazon-for-woocommerce' ) . '</b></a></div>';
			} else {
				return '<div class="ced-connected-button-wrap"><a class="ced-connected-link"><b class="stock_alert_instock"><span class="ced-circle"></span>In stock(' . $quantity . ')</b></a></div>';
			}
		} else {
			return '<div class="ced-connected-button-wrap"><a class="ced-connected-link"><b class="stock_alert_outofstock"><span class="ced-circle" style="background:#e2401c;"></span>' . esc_attr( 'Out of stock', 'amazon-for-woocommerce' ) . '</b></a></div>';
		}

	}
	/**
	 *
	 * Function for category column
	 */
	public function column_category( $item ) {
		echo '<div class="admin-custom-action-button-outer"><div class="admin-custom-action-show-button-outer">';

		if ( isset( $item['category'] ) ) {
			$allCategories = '';
			foreach ( $item['category'] as $key => $prodCat ) {
				$allCategories .= '<b>' . $prodCat . '</b><br>';
			}
			return $allCategories;
		}

		echo '</div></div>';
	}

	/**
	 *
	 * Function for price column
	 */
	public function column_price( $item ) {

		$currencySymbol = get_woocommerce_currency_symbol();
		$woo_price      = 0;
		$amz_price      = 0;

		if ( 'regular_price' == $this->ced_amazon_product_price_type ) { // handling REGULAR price case

			if ( 'variable' == $item['type'] ) { // to handle variable product case, when price type is REGULAR
				$woo_price_min = !empty( $item['min_regular_price'] ) ? $item['min_regular_price'] : 0;
				$woo_price_max = !empty( $item['max_regular_price'] ) ? $item['max_regular_price'] : 0;

				$amz_price1 = ced_calculate_markup_price( $this->mrkup_type, $woo_price_min, $this->mrkp_val, $this->rounding_option );
				$amz_price2 = ced_calculate_markup_price( $this->mrkup_type, $woo_price_max, $this->mrkp_val, $this->rounding_option );

				if ( 0 > $amz_price1 ) {
					$amz_price1 = 0;
				}

				if ( 0 > $amz_price2 ) {
					$amz_price2 = 0;
				}
				
				$woo_price = $currencySymbol . $woo_price_min . ' - ' . $currencySymbol . $woo_price_max;
				$amz_price = $currencySymbol . $amz_price1 . ' - ' . $currencySymbol . $amz_price2;

			} else { // to handle other product type case, when price type is REGULAR 
				$woo_price = !empty( $item['regular_price'] ) ? $item['regular_price'] : 0;
				$amz_price = ced_calculate_markup_price( $this->mrkup_type, $woo_price, $this->mrkp_val, $this->rounding_option );

				if ( 0 > $amz_price ) {
					$amz_price = 0;
				}

				$woo_price = $currencySymbol . $woo_price;
				$amz_price = $currencySymbol . $amz_price;
			}
			
		} else { // handling SALE price case

			if ( 'variable' == $item['type'] ) { // to handle variable product case, when price type is SALE
				$woo_price_min = !empty( $item['min_sale_price'] ) ? $item['min_sale_price'] : 0;
				$woo_price_max = !empty( $item['max_sale_price'] ) ? $item['max_sale_price'] : 0;

				if ( empty( $woo_price_min ) && empty( $woo_price_max ) ) {
					$woo_price_min = $item['min_regular_price'];
					$woo_price_max = $item['max_regular_price'];    
				}

				$amz_price1 = ced_calculate_markup_price( $this->mrkup_type, $woo_price_min, $this->mrkp_val, $this->rounding_option );
				$amz_price2 = ced_calculate_markup_price( $this->mrkup_type, $woo_price_max, $this->mrkp_val, $this->rounding_option );

				if ( 0 > $amz_price1 ) {
					$amz_price1 = 0;
				}

				if ( 0 > $amz_price2 ) {
					$amz_price2 = 0;
				}

				$woo_price = $currencySymbol . $woo_price_min . ' - ' . $currencySymbol . $woo_price_max;
				$amz_price = $currencySymbol . $amz_price1 . ' - ' . $currencySymbol . $amz_price2;

			} else { // to handle other product type case, when price type is SALE

				// get SALE price, if empty get REGULAR price
				$woo_price = !empty( $item['sale_price'] ) ? $item['sale_price'] : $item['regular_price'];
				$amz_price = ced_calculate_markup_price( $this->mrkup_type, $woo_price, $this->mrkp_val, $this->rounding_option );

				if ( 0 > $amz_price ) {
					$amz_price = 0;
				}

				$woo_price = $currencySymbol . $woo_price;
				$amz_price = $currencySymbol . $amz_price;
			}


		}

		echo '<div class="admin-custom-action-button-outer"><div class="admin-custom-action-show-button-outer">';
		echo '<p><b class="success_upload_on_amazon" >Woo price:</b> ' . esc_attr( $woo_price ) . '</p>';
		echo '<p><b class="success_upload_on_amazon" >Amazon price:</b> ' . esc_attr( $amz_price ) . '</p>';
		echo '</div></div>';

	}

	/**
	 *
	 * Function for product type column
	 */
	public function column_type( $item ) {
		echo '<div class="admin-custom-action-button-outer"><div class="admin-custom-action-show-button-outer">';
		echo '<b>' . esc_html__( $item['type'], 'amazon-for-woocommerce' ) . '</b></div></div>';
	}

	/**
	 *
	 * Function for sku column
	 */
	public function column_sku( $item ) {
		echo '<div class="admin-custom-action-button-outer"><div class="admin-custom-action-show-button-outer">';
		echo '<b>' . esc_html__( $item['sku'], 'amazon-for-woocommerce' ) . '</b>';
		echo '</div></div>';
	}

	/**
	 *
	 * Function for image column
	 */
	public function column_image( $item ) {
		$item_image = ( $item['image'] ) ? $item['image'] : wc_placeholder_img_src( 'woocommerce_thumbnail' );
		echo '<div class="admin-custom-action-button-outer"><div class="admin-custom-action-show-button-outer">';
		echo '<img height="50" width="50" src="' . esc_url( $item_image ) . '">';
		echo '</div></div>';
	}

	/**
	 *
	 * Function for status column
	 */
	public function column_status( $item ) {
		$actions = array();

		$seller_loc_arr      = explode( '|', $this->seller_id );
		$mp_location         = isset( $seller_loc_arr['1'] ) ? $seller_loc_arr['0'] : '';
		$listing_id          = get_post_meta( $item['id'], 'ced_amazon_product_asin_' . $mp_location, true );
		$amazon_catalog_asin = get_post_meta( $item['id'], 'ced_amazon_catalog_asin_' . $mp_location, true );

		if ( ! empty( get_post_meta( $item['id'], 'ced_amazon_alt_prod_description_' . $item['id'] . '_' . $this->user_id, true ) ) || ! empty( get_post_meta( $item['id'], 'ced_amazon_alt_prod_title_' . $item['id'] . '_' . $this->user_id, true ) ) ) {
			echo '<button class="px-3 py-1 mr-3 text-white font-semibold bg-blue-500 rounded">Modified</button><br>';

		}
		if ( ! empty( get_post_meta( $item['id'], '_ced_amazon_relist_item_id_' . $this->user_id, true ) ) ) {
			echo '<button class="px-3 py-1 mr-3 text-white font-semibold bg-blue-500 rounded">Re-Listed</button><br>';
		}

		$saved_amazon_details = get_option( 'ced_amzon_configuration_validated', false );
		$marketplace_id       = $saved_amazon_details[ $this->seller_id ]['marketplace_id'];

		if ( isset( $saved_amazon_details[ $this->seller_id ]['marketplace_id'] ) && ! empty( $saved_amazon_details[ $this->seller_id ]['marketplace_id'] ) ) {
			$view_url_production = $this->amazon_regions[ $marketplace_id ][ 'mp-url'] . 'dp/' . $listing_id;
			$catalog_asin_url    = $this->amazon_regions[ $marketplace_id ][ 'mp-url'] . 'dp/' . $amazon_catalog_asin;
		} else {
			$view_url_production = 'https://www.amazon.com/dp/' . $listing_id;
			$catalog_asin_url    = 'https://www.amazon.com/dp/' . $amazon_catalog_asin;
		}

		if ( isset( $listing_id ) && ! empty( $listing_id ) ) {
			
			$view_url_sandbox  = 'https://sandbox.amazon.com/itm/' . $listing_id;
			$mode_of_operation = get_option( 'ced_amazon_mode_of_operation', '' );
			if ( '_sandbox' == $mode_of_operation ) {

				echo '<div class="admin-custom-action-button-outer">';
				echo '<div class="admin-custom-action-show-button-outer">';

				echo '<div class="ced-connected-button-wrap"><a class="ced-connected-link" target="_blank" href="' . esc_attr( $view_url_sandbox ) . '" ><span class="ced-circle"></span>' . esc_html__( 'View on Amazon', 'amazon-for-woocommerce' ) . '</a> </div>';
				echo '</div></div>';

			} elseif ( 'production' == $mode_of_operation ) {

				echo '<div class="admin-custom-action-button-outer">';
				echo '<div class="admin-custom-action-show-button-outer">';

				echo '<div class="ced-connected-button-wrap"><a class="ced-connected-link" target="_blank" href="' . esc_attr( $view_url_production ) . '" ><span class="ced-circle"></span>' . esc_html__( 'View on Amazon', 'amazon-for-woocommerce' ) . '</a> </div>';
				echo '</div></div>';

			} else {

				echo '<div class="admin-custom-action-button-outer">';
				echo '<div class="admin-custom-action-show-button-outer">';

				echo '<div class="ced-connected-button-wrap"><a class="ced-connected-link" target="_blank" href="' . esc_attr( $view_url_production ) . '" ><span class="ced-circle"></span>' . esc_html__( 'View on Amazon', 'amazon-for-woocommerce' ) . '</a> </div>';

				if ( isset( $amazon_catalog_asin ) && ! empty( $amazon_catalog_asin ) ) {
					echo '<br><div class="ced-connected-button-wrap"><a class="ced-connected-link" target="_blank" href="' . esc_url( $catalog_asin_url ) . '" ><span class="ced-circle"></span>' . esc_html__( 'View ASIN', 'amazon-for-woocommerce' ) . '</a> </div>';
				}
				echo '</div></div>';
			}
			
		} elseif ( is_array( $this->json_validator_errors[ $item['id'] ] ) && empty( $this->json_validator_errors[ $item['id'] ] ) ) {
			echo '<div class="admin-custom-action-button-outer">';
			echo '<div class="admin-custom-action-show-button-outer">';

			echo '<div class="ced-processing-button-wrap"><a class="ced-connected-link"><span class="ced-circle" style="background:#72AEE6;"></span>' . esc_html__( 'Ready to upload', 'amazon-for-woocommerce' ) . '</a> </div>';
			echo '</div></div>';

		} else {
			echo '<div class="admin-custom-action-button-outer">';
			echo '<div class="admin-custom-action-show-button-outer">';

			echo '<div class="ced-disconnected-button-wrap"><a class="ced-connected-link"><span class="ced-circle" style="background:#000000;"></span>' . esc_html__( 'Not uploaded', 'amazon-for-woocommerce' ) . '</a> </div>';
			if ( isset( $amazon_catalog_asin ) && ! empty( $amazon_catalog_asin ) ) {

				echo '<br><div class="ced-connected-button-wrap"><a class="ced-connected-link" target="_blank" href="' . esc_url( $catalog_asin_url ) . '" ><span class="ced-circle"></span>' . esc_html__( 'View ASIN', 'amazon-for-woocommerce' ) . '</a> </div>';
			}
			echo '</div></div>';
		} 

		return $this->row_actions( $actions );
	}

	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'       => '<input type="checkbox" />',
			'image'    => __( 'Image', 'amazon-for-woocommerce' ),
			'name'     => __( 'Name', 'amazon-for-woocommerce' ),
			'type'     => __( 'Type', 'amazon-for-woocommerce' ),
			'price'    => __( 'Price', 'amazon-for-woocommerce' ),
			'profile'  => __( 'Template assigned', 'amazon-for-woocommerce' ),
			'sku'      => __( 'Sku', 'amazon-for-woocommerce' ),
			'stock'    => __( 'Stock', 'amazon-for-woocommerce' ),
			'category' => __( 'Woo category', 'amazon-for-woocommerce' ),
			'status'   => __( 'Status', 'amazon-for-woocommerce' ),

		);
		/**
		 * Function to list order based on per page
		 *
		 * @param 'function'
		 * @param  integer 'limit'
		 * @return 'count'
		 * @since 1.0.0
		 */
		$columns = apply_filters( 'ced_amazon_alter_product_table_columns', $columns );
		return $columns;
	}

	/**
	 *
	 * Function to count number of responses in result
	 */
	public function get_count( $per_page, $page_number ) {
		$args = $this->GetFilteredData( $per_page, $page_number );
		if ( ! empty( $args ) && ( isset( $args['tax_query'] ) || isset( $args['meta_query'] ) || isset( $args['s'] ) ) ) {
			$args = $args;
		} else {
			$args = array( 'post_type' => 'product', 'product_type' => array( 'simple', 'variable' ), 'post_status'  => 'publish' );
		}
		$loop         = new WP_Query( $args );
		$product_data = $loop->posts;
		$product_data = $loop->found_posts;

		return $product_data;
	}

	/**
	 *
	 * Function for GetFilteredData
	 */
	public function GetFilteredData( $per_page, $page_number ) {

		$this->show_reset = false;
		$args             = array();

		$seller_loc_arr = explode( '|', $this->seller_id );
		$mp_location    = isset( $seller_loc_arr['1'] ) ? $seller_loc_arr['0'] : '';

		if ( ( isset( $_GET['status_sorting'] ) || isset( $_GET['pro_cat_sorting'] ) || isset( $_GET['pro_type_sorting'] ) || isset( $_GET['pro_profile_sorting'] ) ) ) {
			$this->show_reset = true;

			if ( isset( $_REQUEST['pro_cat_sorting'] ) && ! empty( $_REQUEST['pro_cat_sorting'] ) ) {
				$pro_cat_sorting = isset( $_GET['pro_cat_sorting'] ) ? sanitize_text_field( $_GET['pro_cat_sorting'] ) : '';
				if ( '' != $pro_cat_sorting ) {
					$selected_cat          = array( $pro_cat_sorting );
					$tax_query             = array();
					$tax_queries           = array();
					$tax_query['taxonomy'] = 'product_cat';
					$tax_query['field']    = 'id';
					$tax_query['terms']    = $selected_cat;
					$args['tax_query'][]   = $tax_query;
				}
			}

			if ( isset( $_REQUEST['pro_type_sorting'] ) && ! empty( $_REQUEST['pro_type_sorting'] ) ) {
				$pro_type_sorting = isset( $_GET['pro_type_sorting'] ) ? sanitize_text_field( $_GET['pro_type_sorting'] ) : '';
				if ( '' != $pro_type_sorting ) {
					$selected_type         = array( $pro_type_sorting );
					$tax_query             = array();
					$tax_queries           = array();
					$tax_query['taxonomy'] = 'product_type';
					$tax_query['field']    = 'id';
					$tax_query['terms']    = $selected_type;
					$args['tax_query'][]   = $tax_query;
				}
			}

			if ( isset( $_REQUEST['status_sorting'] ) && ! empty( $_REQUEST['status_sorting'] ) ) {
				$status_sorting = isset( $_GET['status_sorting'] ) ? sanitize_text_field( $_GET['status_sorting'] ) : '';
				if ( '' != $status_sorting ) {
					$meta_query = array();
					if ( 'Uploaded' == $status_sorting ) {

						$meta_query[] = array(
							'key'     => 'ced_amazon_product_asin_' . $mp_location,
							'compare' => 'EXISTS',
						);
					} elseif ( 'NotUploaded' == $status_sorting ) {
						$meta_query[] = array(
							'key'     => 'ced_amazon_product_asin_' . $mp_location,
							'compare' => 'NOT EXISTS',
						);
					} elseif ( 'CatalogASIN' == $status_sorting ) {
						$meta_query[] = array(
							'key'     => 'ced_amazon_catalog_asin_' . $mp_location,
							'compare' => 'EXISTS',

						);
					}
					$args['meta_query'] = $meta_query;
				}
			}

			if ( isset( $_REQUEST['pro_stock_sorting'] ) && ! empty( $_REQUEST['pro_stock_sorting'] ) ) {
				$sort_by_stock = isset( $_GET['pro_stock_sorting'] ) ? sanitize_text_field( $_GET['pro_stock_sorting'] ) : '';
				if ( '' != $sort_by_stock ) {
					$meta_query = array();
					if ( 'instock' == $sort_by_stock ) {

						if ( 'Uploaded' == $_REQUEST['status_sorting'] ) {
							$args['meta_query'] = array(
								'relation' => 'AND',
								array(
									'key'     => 'ced_amazon_product_asin_' . $mp_location,
									'compare' => 'EXISTS',
								),
								array(
									'key'     => '_stock_status',
									'value'   => 'instock',
									'compare' => '=',
								),

							);

						} elseif ( 'NotUploaded' == $_REQUEST['status_sorting'] ) {
							$args['meta_query'] = array(
								'relation' => 'AND',
								array(
									'key'     => 'ced_amazon_product_asin_' . $mp_location,
									'compare' => 'NOT EXISTS',
								),
								array(
									'key'     => '_stock_status',
									'value'   => 'instock',
									'compare' => '=',
								),

							);

						} elseif ( 'CatalogASIN' == $_REQUEST['status_sorting'] ) {
							$args['meta_query'] = array(
								'relation' => 'AND',
								array(
									'key'     => 'ced_amazon_catalog_asin_' . $mp_location,
									'compare' => 'EXISTS',
								),
								array(
									'key'     => '_stock_status',
									'value'   => 'instock',
									'compare' => '=',
								),

							);

						} else {
							$args['meta_query'][] = array(
								'key'     => '_stock_status',
								'value'   => 'instock',
								'compare' => '=',
							);
						}
					} elseif ( 'outofstock' == $sort_by_stock ) {

						if ( 'Uploaded' == $_REQUEST['status_sorting'] ) {
							$args['meta_query'] = array(
								'relation' => 'AND',
								array(
									'key'     => 'ced_amazon_product_asin_' . $mp_location,
									'compare' => 'EXISTS',
								),
								array(
									'key'     => '_stock_status',
									'value'   => 'outofstock',
									'compare' => '=',
								),

							);

						} elseif ( 'NotUploaded' == $_REQUEST['status_sorting'] ) {
							$args['meta_query'] = array(
								'relation' => 'AND',
								array(
									'key'     => 'ced_amazon_product_asin_' . $mp_location,
									'compare' => 'NOT EXISTS',
								),

								array(
									'key'     => '_stock_status',
									'value'   => 'outofstock',
									'compare' => '=',
								),

							);

						} elseif ( 'CatalogASIN' == $_REQUEST['status_sorting'] ) {
							$args['meta_query'] = array(
								'relation' => 'AND',
								array(
									'key'     => 'ced_amazon_catalog_asin_' . $mp_location,
									'compare' => 'EXISTS',
								),

								array(
									'key'     => '_stock_status',
									'value'   => 'outofstock',
									'compare' => '=',
								),

							);

						} else {

							$args['meta_query'][] = array(
								'key'     => '_stock_status',
								'value'   => 'outofstock',
								'compare' => '=',
							);
						}
					}
				}
			}
		}

		if ( ! empty( $_REQUEST['s'] ) ) {
			$s = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
			if ( ! empty( $s ) ) {
				$args['s'] = $s;
			}
		}

		$args['post_type']      = 'product';
		$args['posts_per_page'] = $per_page;
		$args['paged']          = $page_number;

		return $args;
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
			echo '<select name="action' . esc_attr( $two ) . '" class="ced_amazon_select_amazon_product_action">';
			echo '<option value="-1">' . esc_attr( 'Bulk actions' ) . "</option>\n";

			foreach ( $this->_actions as $name => $title ) {
				$class = 'edit' === $name ? ' class="hide-if-no-js"' : '';

				echo "\t" . '<option value="' . esc_attr( $name ) . '"' . esc_attr( $class ) . '>' . esc_html( $title, 'amazon-for-woocommerce' ) . "</option>\n";
			}

			echo "</select>\n";

			wp_nonce_field( 'ced_amazon_product_bulk_action_page_nonce', 'ced_amazon_product_bulk_action_nonce' );
			submit_button( __( 'Apply' ), 'action', 'doaction', false, array( 'id' => 'ced_amazon_bulk_operation' ) );
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
			'upload_product'   => __( 'Upload', 'amazon-for-woocommerce' ),
			'validate_product' => __( 'Validate Product', 'amazon-for-woocommerce' ),
			'relist_product'   => __( 'Relist Product', 'amazon-for-woocommerce' ),
			'update_inventory' => __( 'Update Inventory', 'amazon-for-woocommerce' ),
			'update_price'     => __( 'Update Price', 'amazon-for-woocommerce' ),
			'update_images'    => __( 'Update Images', 'amazon-for-woocommerce' ),
			'delete_product'   => __( 'Delete Listing', 'amazon-for-woocommerce' ),
			'look_up'          => __( 'Look up on amazon', 'amazon-for-woocommerce' ),
		);
		return $actions;
	}

	/**
	 *
	 * Function for rendering html
	 */
	public function renderHTML() {
		
		?>
		<div id="post-body" class="metabox-holder columns-2">
			<div id="post-body-content">
				<div class="meta-box-sortables ui-sortable">
					<?php
						$status_actions = array(
							'Uploaded'    => __( 'Uploaded', 'amazon-for-woocommerce' ),
							'NotUploaded' => __( 'Not Uploaded', 'amazon-for-woocommerce' ),
							'CatalogASIN' => __( 'Amazon ASIN', 'amazon-for-woocommerce' ),
						);

						$product_types   = get_terms( 'product_type' );
						$temp_array      = array();
						$temp_array_type = array();
						
						foreach ( $product_types as $key => $value ) {
							if ( 'simple' == $value->name || 'variable' == $value->name ) {
								$temp_array_type[ $value->term_id ] = ucfirst( $value->name );
							}
						}
						$product_types      = $temp_array_type;
						$product_categories = $this->ced_amazon_get_taxonomy_hierarchy( 'product_cat', 0, 0 );
						$temp_array         = array();

						$profiles_array = array();

						$assigned_profiles              = $profiles_array;
						$previous_selected_status       = isset( $_GET['status_sorting'] ) ? sanitize_text_field( $_GET['status_sorting'] ) : '';
						$previous_selected_cat          = isset( $_GET['pro_cat_sorting'] ) ? sanitize_text_field( $_GET['pro_cat_sorting'] ) : '';
						$previous_selected_type         = isset( $_GET['pro_type_sorting'] ) ? sanitize_text_field( $_GET['pro_type_sorting'] ) : '';
						$previous_selected_stock_status = isset( $_GET['pro_stock_sorting'] ) ? sanitize_text_field( $_GET['pro_stock_sorting'] ) : '';
						echo '<div class="ced_amazon_wrap">';
						echo '<form method="post" action="">';
						echo '<div class="ced_amazon_top_wrapper">';

						echo '<select name="status_sorting" class="select_boxes_product_page">';
						echo '<option value="">' . esc_attr( 'Product status', 'amazon-for-woocommerce' ) . '</option>';
						foreach ( $status_actions as $name => $title ) {
							$selectedStatus = ( $previous_selected_status == $name ) ? 'selected="selected"' : '';
							$class          = 'edit' === $name ? ' class="hide-if-no-js"' : '';
							echo '<option ' . esc_attr( $selectedStatus ) . ' value="' . esc_attr( $name ) . '"' . esc_attr( $class ) . '>' . esc_attr( $title ) . '</option>';
						}

						echo '</select>';
						$previous_selected_cat = isset( $_GET['pro_cat_sorting'] ) ? sanitize_text_field( $_GET['pro_cat_sorting'] ) : '';

						$dropdown_cat_args = array(
							'name'            => 'pro_cat_sorting',
							'show_count'      => 1,
							'hierarchical'    => 1,
							'taxonomy'        => 'product_cat',
							'class'           => 'select_boxes_product_page',
							'selected'        => $previous_selected_cat,
							'show_option_all' => 'Product category',
							'hide_if_empty'   => true,

						);
						wp_dropdown_categories( $dropdown_cat_args );
						echo '<select name="pro_type_sorting" class="select_boxes_product_page">';
						echo '<option value="">' . esc_attr( 'Product type', 'amazon-for-woocommerce' ) . '</option>';
						foreach ( $product_types as $name => $title ) {
							$selectedType = ( $previous_selected_type == $name ) ? 'selected="selected"' : '';
							$class        = 'edit' === $name ? ' class="hide-if-no-js"' : '';
							echo '<option ' . esc_attr( $selectedType ) . ' value="' . esc_attr( $name ) . '"' . esc_attr( $class ) . '>' . esc_attr( $title ) . '</option>';
						}
						echo '</select>';

						echo '<select name="pro_stock_sorting" class="select_boxes_product_page">';
						echo '<option value="">' . esc_attr( 'Stock status', 'amazon-for-woocommerce' ) . '</option>';
						echo '<option ' . esc_attr( ( 'instock' == $previous_selected_stock_status ) ? 'selected="selected"' : '' ) . ' value="instock">In Stock</option>';
						echo '<option ' . esc_attr( ( 'outofstock' == $previous_selected_stock_status ) ? 'selected="selected"' : '' ) . ' value="outofstock">Out Of Stock</option>';
						echo '</select>';

						wp_nonce_field( 'ced_amazon_product_filter_page_nonce', 'ced_amazon_product_filter_nonce' );

						submit_button( __( 'Filter', 'amazon-for-woocommerce' ), 'action', 'filter_button', false, array() );

						$this->search_box( 'Search', 'search_id', 'search_product' );

						if ( $this->show_reset ) {

							$url = ced_get_navigation_url(
								'amazon',
								array(
									'section'   => 'products-view',
									'user_id'   => $this->user_id,
									'seller_id' => $this->seller_id,
								)
							);

							echo '<span class="ced_reset"><a href="' . esc_url( $url ) . '" class="button">X</a></span>';
						}
							echo '</div>';
							echo '</form>';
							echo '</div>';
						?>
					  

					<form method="post">
					</div>
					</div>
				   <?php $this->display(); ?>



				</form>
			</div>
		</div>

	
		<!-- inline edit code html -->

		<form method="get">
		<table style=""><tbody id="inlineedit">
 
			<tr id="inline-edit" class="inline-edit-row" style="">
			<td colspan="<?php echo esc_attr($this->get_column_count()) ; ?>" class="colspanchange">
			<div class="inline-edit-wrapper">

			<?php 

			if ( !empty( $this->json_validator_errors ) && is_array( $this->json_validator_errors ) ) {
			
				foreach ( $this->json_validator_errors as $product_id => $val_errors ) { 

					/** Cedcommerce Validator */
					$cedcommerce_val_errors = isset( $val_errors['woocommerce_validation'] ) ? $val_errors['woocommerce_validation'] : array();
					/** Amazon Validator */
					$amazon_val_erros = isset( $val_errors['amazon_validation'] ) ? $val_errors['amazon_validation'] : array();
					
					/** Final errors */
					$validation_errors = array();

					if ( is_string( $cedcommerce_val_errors) ) {

						if ( 'Validation Successful' == $cedcommerce_val_errors ) {
							/** If ced validation is successful, then we will only display the amazon validation errors.  */
							$validation_errors = $amazon_val_erros;
						} else {
							/** If ced validation is not successful, then we will merge the ced and amazon validation errors and display to user.  */
						   $validation_errors = array_merge( array($cedcommerce_val_errors), $amazon_val_erros );

						} 
						
					} elseif ( is_array( $cedcommerce_val_errors ) && is_array( $amazon_val_erros ) ) {
						$validation_errors = array_merge( $cedcommerce_val_errors, $amazon_val_erros );
					}

					$child_html = '';
					// parent products
					if ( isset( $this->items_ids[$product_id] ) && isset($this->items_ids[$product_id]['child_ids']) ) {
						
						foreach ( $this->items_ids[$product_id]['child_ids'] as $child_id ) {
							
							$child       = wc_get_product( $child_id );
							$child_sku   = $child->get_sku();
							$child_html .= '<p> Child SKU: ' . $child_sku . '</p>';

							if ( isset(  $this->json_validator_errors[ $child_id ] ) ) {

								$ced_val_errors = $this->json_validator_errors[ $child_id ]['woocommerce_validation'] ?? array();
								$amz_val_erros  = $this->json_validator_errors[ $child_id ]['amazon_validation'] ?? array();
								
								$val_errors = array();
								if ( is_string( $ced_val_errors) ) {

									if ( 'Validation Successful' == $ced_val_errors ) {
										/** If ced validation is successful, then we will only display the amazon validation errors.  */
										$val_errors = $amz_val_erros;
									} else {
										/** If ced validation is not successful, then we will merge the ced and amazon validation errors and display to user.  */
									   $val_errors = array_merge( array($ced_val_errors), $amz_val_erros );
			
									} 
									
								} elseif ( is_array( $ced_val_errors ) && is_array( $amz_val_erros ) ) {
									$val_errors = array_merge( $ced_val_errors, $amz_val_erros );
								}
								
								$child_html .= '<ol>';
								if ( !empty( $val_errors ) && is_array( $val_errors ) ) { 
									foreach ( $val_errors as $val_error ) { 
									
										if ( is_array( $val_error ) ) {

											$child_html .= '<li> <b>';
											$name        = $val_error['property'] ?? '';
											if (empty($name)) {
												$name = $val_error['attributeName'] ?? $val_error['attributeNames'][0];
											}
											$child_html .=  !empty($name) ?  $name : 'NA' ; 
											$child_html .= ': </b>' . $val_error['message'] . '</li>';

										} else {
											$child_html .= '<li> <b>NA: </b>' . $val_error . '</li>';
										}
								
									}

								} elseif ( is_string( $val_errors ) && 'The product has been successfully uploaded to Amazon.' == $validation_errors ) { 
									$child_html .= '<ol> <li> The product has been successfully uploaded to Amazon. </li> </ol>';
								} elseif ( empty( $val_errors ) && 'Validation successful' == $ced_val_errors ) { 
									$child_html .= '<ol> <li> Validation successful</li> </ol>';   
								} else { 
									$child_html .= '<ol> <li> No issues Found.</li> </ol>';   
								}
									
								$child_html .= '</ol>';

							}

						}

					}

					?>
			
					<div class="ced_amz_validation_issues ced_amz_validation_issues_<?php echo esc_attr($product_id); ?>" style="display: none" >

						<p> Product SKU: <?php echo esc_attr($this->items_ids[$product_id]['sku']) ; ?> </p>
						<ol>
							<?php
							if ( !empty( $validation_errors ) && is_array( $validation_errors ) ) { 
								foreach ( $validation_errors as $validation_error ) { 
										
									if ( is_array( $validation_error ) ) {
										?>

											<li> <b>
											<?php 
												$name = $validation_error['property'] ?? '';
											if (empty($name)) {
												$name = $validation_error['attributeName'] ?? $validation_error['attributeNames'][0];
											}
												print_r($name);
											?>
											:
											</b>  <?php echo esc_attr($validation_error['message']); ?> </li>
											<?php 

									} else {
										?>
											<li> <b> NA : </b>  <?php echo esc_attr($validation_error); ?> </li>
										<?php
									}
								}

							} elseif ( is_string( $validation_errors ) && 'The product has been successfully uploaded to Amazon.' == $validation_errors ) {
								?>
									<ol> <li> The product has been successfully uploaded to Amazon. </li> </ol> 
									<?php 
							} elseif ( empty( $validation_errors ) && 'Validation successful' == $cedcommerce_val_errors ) {
								?>
									<ol> <li> Validation successful</li> </ol> 
									<?php 
							} else {
								?>
									<ol> <li> No issues Found.</li> </ol>   
									<?php
							}
							?>
						</ol>

						<?php print_r($child_html); ?>
			
					</div>
					<?php 
				} 
			}
			
			?>
		

			<div class="inline-edit-save submit">
				<button type="button" class="cancel button"><?php esc_attr_e( 'Cancel' ); ?></button>
				<span class="spinner"></span>
				<?php 
					wp_nonce_field( 'inlineeditnonce', '_inline_edit', false ); 
					wp_admin_notice(
						'<p class="error"></p>',
						array(
							'type'               => 'error',
							'additional_classes' => array( 'notice-alt', 'inline', 'hidden' ),
							'paragraph_wrap'     => false,
						)
					);
				?>
			</div>
			</div>

			</td></tr>

		</tbody></table>
		</form>

		<!-- inline edit code html  -->
	
		<div class="clear"></div>
		</div>
		<?php
	}

	
}

$ced_amazon_products_obj = new AmazonListProducts();
$ced_amazon_products_obj->prepare_items();
?>
