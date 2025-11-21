<?php

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/** Import the header */
ced_import_header();

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}


class Ced_Amazon_Profile_Table extends WP_List_Table {

	public $current_amazon_profile;
	public $cloneTemplateIds = array();
	public $ced_html_tags;
	public $seller_id = '';
	public $user_id   = '';

	/** Class constructor */
	public function __construct() {

		$this->cloneTemplateIds = get_option( 'ced_amz_cloned_templates', array() );
		$this->seller_id        = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';
		$this->user_id          = isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '';

		parent::__construct(
			array(
				'singular' => __( 'Amazon Template', 'amazon-for-woocommerce' ),
				'plural'   => __( 'Amazon Templates', 'amazon-for-woocommerce' ),
				'ajax'     => false,
			)
		);

		$file = CED_AMAZON_DIRPATH . 'admin/partials/ced_amazon_html_tags.php';
		if ( file_exists( $file ) ) {
			require_once $file;
			$this->ced_html_tags = new Ced_Amazon_Html_Tags();
		}

	}

	/**
	 *
	 * Function for preparing profile data to be displayed column
	 */
	public function prepare_items() {

		/**
		 * Function to get listing per page
		 *
		 * @param 'function'
		 * @param  integer 'limit'
		 * @return 'count'
		 * @since  1.0.0
		 */
		$per_page = apply_filters( 'ced_amazon_profile_list_per_page', 10 );
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$current_page = $this->get_pagenum();
		if ( 1 < $current_page ) {
			$offset = $per_page * ( $current_page - 1 );
		} else {
			$offset = 0;
		}

		$this->items = self::ced_amazon_get_profiles( $per_page, $current_page );

		$count = self::get_count();

		$this->set_pagination_args(
			array(
				'total_items' => $count,
				'per_page'    => $per_page,
				'total_pages' => ceil( $count / $per_page ),
			)
		);

		if ( ! $this->current_action() ) {
			$this->items = self::ced_amazon_get_profiles( $per_page, $current_page );
			$this->renderHTML();
		} else {
			$this->process_bulk_action();
		}
	}

	/**
	 *
	 * Function for status column
	 */
	public function ced_amazon_get_profiles( $per_page = 1, $page_number = 1 ) {

		global $wpdb;
		$offset = ( $page_number - 1 ) * $per_page;
		
		$result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ced_amazon_profiles WHERE `seller_id` = %s ORDER BY `id` DESC LIMIT %d OFFSET %d", $this->seller_id, $per_page, $offset ), 'ARRAY_A' );
		return $result;
	}

	/*
	 *
	 * Function to count number of responses in result
	 *
	 */
	public function get_count() {

		global $wpdb;
		
		$amazon_profiles = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ced_amazon_profiles WHERE `seller_id` = %s", $this->seller_id ), 'ARRAY_A' );
		if ( ! empty( $amazon_profiles ) ) {
			return count( $amazon_profiles );
		} else {
			return 0;
		}
	}

	/*
	*
	* Text displayed when no customer data is available
	*
	*/
	public function no_items() {
		echo esc_html__( 'No Templates Created.', 'amazon-for-woocommerce' );
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
			'<input type="checkbox" name="amazon_profile_ids[]" value="%s" class="amazon_profile_ids"/>',
			$item['id']
		);
	}


	/**
	 * Function for product_type column
	 *
	 * @param array $item an array of DB data
	 *
	 * @return string
	 */
	public function column_template_product_type( $item ) {

		echo '<p>' . esc_html__( str_replace( '_', ' ', $item['product_type'] ), 'amazon-for-woocommerce' ) . '</p>';

	}

	/**
	 * Function for name column
	 *
	 * @param array $item an array of DB data
	 *
	 * @return string
	 */
	public function column_profile_name( $item ) {

		$template_id = $item['id'];
		$cloned      = false;

		if ( isset( $this->cloneTemplateIds[ $this->seller_id ] ) && in_array( $template_id, $this->cloneTemplateIds[ $this->seller_id ] ) ) {
			$cloned = true;
		}

		echo '<p>' . esc_html__( $item['profile_name'], 'amazon-for-woocommerce' );
		if ( $cloned ) { 
			?>  <span class="ced-clone-lable-wrapper"> Clone </span> 
			<?php
		}

		echo '</p>';

	}

	/**
	 *
	 * Function for profile status column
	 */
	public function column_profile_status( $item ) {

		echo '<div class="ced_amz_temp_status" > ';
		if ( isset( $item['profile_status'] ) && ! empty( $item['profile_status'] ) ) {
			if ( 'inactive' == $item['profile_status'] ) {
				return '<span>InActive</span>';
			} else {
				echo '<span>Active</span>';
			}
		} else {
			echo '<span>Active</span>';
		}
		echo '</div>';

	}

	/**
	 *
	 * Function for category column
	 */
	public function column_woo_categories( $item ) {

		$woo_categories = json_decode( $item['wocoommerce_category'], true );

		if ( ! empty( $woo_categories ) ) {
			foreach ( $woo_categories as $key => $value ) {
				$term = get_term_by( 'id', $value, 'product_cat' );

				$cat_name = $term->name;
				$cat_name = ced_amazon_categories_tree( $term, $cat_name );

				if ( isset( $term ) && ! empty( $term ) ) {
					echo '<span class="' . esc_attr( $item['id'] ) . '" id="' . esc_attr( $term->term_id ) . '">' . esc_attr( $cat_name ) . ' </span>';
					if ( $key + 1 < count( $woo_categories ) ) {
						echo '<br>';
					}
				}
			}
		} else {
			echo esc_html__( 'No category mapped', 'amazon-for-woocommerce' );
		}
	}


	public function column_action( $item ) {

		$ced_amaz_cat_val_array = $this->ced_amz_get_woo_categories();

		$wooUsedCategoriesArray = isset( $ced_amaz_cat_val_array['wooUsedCategoriesArray'] ) ? $ced_amaz_cat_val_array['wooUsedCategoriesArray'] : array();
		$allWooCategories       = isset( $ced_amaz_cat_val_array['allWooCategories'] ) ? $ced_amaz_cat_val_array['allWooCategories'] : array();

		$url = ced_get_navigation_url(
			'amazon',
			array(
				'section'       => 'add-new-template',
				'template_id'   => $item['id'],
				'user_id'       => $this->user_id,
				'seller_id'     => $this->seller_id,
			)
		);

		echo '<p class="ced_amz_template_actions" > 
		     <a class="profile-edit" target="_blank" href="' . esc_url($url) . '">Edit</a> | 
		     <a class="ced-amz-profile-clone"  href="#" data-clone_tmp_id="' . esc_attr( $item['id'] ) . '"
				data-woo-used-cat="' . esc_attr( htmlspecialchars( json_encode( $wooUsedCategoriesArray ), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 ) ) . '" 
				data-woo-all-cat = "' . esc_attr( htmlspecialchars( json_encode( $allWooCategories ), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 ) ) . '"
				>Clone</a> | 

		    <span class="ced_amz_del_tem" data-id="' . esc_attr( $item['id'] ) . '" > Delete </span> </p>';
	}

	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'             => '<input type="checkbox" />',
			'profile_name'   => __( 'Template Name', 'amazon-for-woocommerce' ),
			'template_product_type'   => __( 'Amazon Product Type', 'amazon-for-woocommerce' ),
			'woo_categories' => __( 'Mapped WooCommerce categories', 'amazon-for-woocommerce' ),
			'profile_status' => __( 'Status', 'amazon-for-woocommerce' ),
			'action'         => __( 'Action', 'amazon-for-woocommerce' ),

		);

		/**
		 * Function to alter profile table columns
		 *
		 * @param 'function'
		 * @param  integer 'limit'
		 * @return 'count'
		 * @since 1.0.0
		 */
		$columns = apply_filters( 'ced_amazon_alter_profiles_table_columns', $columns );
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

			submit_button( __( 'Apply' ), 'action', '', false, array( 'id' => 'ced_amazon_profile_bulk_operation' ) );
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


	public function ced_amz_get_woo_categories() {

		global $wpdb;
		
		$wooUsedCategoriesArray = array();
		$wooUsedCategories      = $wpdb->get_results( $wpdb->prepare( "SELECT `wocoommerce_category` FROM {$wpdb->prefix}ced_amazon_profiles WHERE `seller_id` = %s", $this->seller_id ), 'ARRAY_A' );

		if ( ! empty( $wooUsedCategories ) ) {
			foreach ( $wooUsedCategories as $wooUsedCategory ) {
				$decoded_woo_categories = json_decode( $wooUsedCategory['wocoommerce_category'], true );
				if ( ! empty( $decoded_woo_categories ) ) {
					foreach ( $decoded_woo_categories as $decoded_woo_category ) {

						settype( $decoded_woo_category, 'integer' );
						$wooUsedCategoriesArray[] = $decoded_woo_category;
					}
				}
			}
		}

		$wooUsedCategoriesArray = array_values( array_unique( $wooUsedCategoriesArray ) );

		$allWooCategories = array();
		$categories       = get_terms( 'product_cat' );

		if ( ! empty( $categories ) ) {
			foreach ( $categories as $category ) {
				$cat                = json_decode( wp_json_encode( $category ), true );
				$allWooCategories[] = $cat['term_id'];
			}
		}

		return array(
			'allWooCategories'       => $allWooCategories,
			'wooUsedCategoriesArray' => $wooUsedCategoriesArray,
		);
	}

	/**
	 * Function to get changes in html
	 */
	public function renderHTML() {

		if ( isset( $_POST['ced_amazon_profile_edit'] ) && wp_verify_nonce( sanitize_text_field( $_POST['ced_amazon_profile_edit'] ), 'ced_amazon_profile_edit_page_nonce' ) ) {

			if ( isset( $_POST['add_meta_keys'] ) || isset( $_POST['ced_amazon_profile_save_button'] ) ) {

				$sanitized_array     = filter_input_array( INPUT_POST, FILTER_UNSAFE_RAW );
				$amazon_profile_data = isset( $sanitized_array['ced_amazon_profile_data'] ) ? ( $sanitized_array['ced_amazon_profile_data'] ) : array();

				$profileDetails = array(
					'primary_category'       => isset( $amazon_profile_data['primary_category'] ) ? $amazon_profile_data['primary_category'] : '',
					'secondary_category'     => isset( $amazon_profile_data['secondary_category'] ) ? $amazon_profile_data['secondary_category'] : '',
					'browse_nodes'           => isset( $amazon_profile_data['browse_nodes'] ) ? $amazon_profile_data['browse_nodes'] : '',
					'wocoommerce_category'   => isset( $amazon_profile_data['wocoommerce_category'] ) ? $amazon_profile_data['wocoommerce_category'] : '',
					
					'browse_nodes_name'      => isset( $amazon_profile_data['browse_nodes_name'] ) ? $amazon_profile_data['browse_nodes_name'] : '',
					'amazon_categories_name' => isset( $amazon_profile_data['amazon_categories_name'] ) ? $amazon_profile_data['amazon_categories_name'] : '',
				
				);

				$profileDetails['category_attributes_structure'] = wp_json_encode( $amazon_profile_data['ref_attribute_list'] );

				unset( $amazon_profile_data['primary_category'] );
				unset( $amazon_profile_data['secondary_category'] );
				unset( $amazon_profile_data['browse_nodes'] );

				unset( $amazon_profile_data['browse_nodes_name'] );
				unset( $amazon_profile_data['amazon_categories_name'] );

				unset( $amazon_profile_data['ref_attribute_list'] );
				unset( $amazon_profile_data['wocoommerce_category'] );

				$profileDetails['category_attributes_data'] = wp_json_encode( $amazon_profile_data );

				global $wpdb;
				$tableName = $wpdb->prefix . 'ced_amazon_profiles';

				$wpdb->insert(
					$tableName,
					array(
						'primary_category'              => $profileDetails['primary_category'],
						'secondary_category'            => $profileDetails['secondary_category'],
						'category_attributes_response'  => '',
						'wocoommerce_category'          => wp_json_encode( $profileDetails['wocoommerce_category'] ),
						'category_attributes_structure' => $profileDetails['category_attributes_structure'],
						'browse_nodes'                  => $profileDetails['browse_nodes'],

						'browse_nodes_name'             => $profileDetails['browse_nodes_name'],
						'amazon_categories_name'        => $profileDetails['amazon_categories_name'],

						'category_attributes_data'      => $profileDetails['category_attributes_data'],
						'seller_id'                     => $this->seller_id
						
				
					),
					array( '%s' )
				);

				$seller_id = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';
				$user_id   = isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '';

				$seller_id = str_replace( '|', '%7C', $seller_id );

				$url = ced_get_navigation_url(
					'amazon',
					array(
						'section'   => 'templates-view',
						'user_id'   => $user_id,
						'seller_id' => $seller_id,
					)
				);

				wp_safe_redirect( $url );
				exit();

			}
		}

	
		global $wpdb;
		$tableName            = $wpdb->prefix . 'ced_amazon_profiles';
		$amazon_profiles      = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ced_amazon_profiles WHERE `seller_id` = %s ", $this->seller_id ), 'ARRAY_A' );
		$amazon_wooCategories = array();

		if ( ! empty( $amazon_profiles ) ) {
			foreach ( $amazon_profiles as $amazon_profile ) {

				$wooCatIds = json_decode( $amazon_profile['wocoommerce_category'], true );
				if ( ! empty( $wooCatIds ) ) {
					foreach ( $wooCatIds as $wooCatId ) {
						$amazon_wooCategories[] = $wooCatId;
					}
				}
			}
		}

		$ced_amaz_cat_val_array = $this->ced_amz_get_woo_categories();

		$wooUsedCategoriesArray = isset( $ced_amaz_cat_val_array['wooUsedCategoriesArray'] ) ? $ced_amaz_cat_val_array['wooUsedCategoriesArray'] : array();
		$allWooCategories       = isset( $ced_amaz_cat_val_array['allWooCategories'] ) ? $ced_amaz_cat_val_array['allWooCategories'] : array();

		if ( ! empty( $this->seller_id ) ) {

			?>

				<div class="ced-button-wrapper-top">

					<button type="button" class="components-button is-primary update-productType-btn" data-woo-used-cat="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $wooUsedCategoriesArray ), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 ) ); ?>" data-woo-all-cat = "<?php print_r( htmlspecialchars( wp_json_encode( $allWooCategories ), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 ) ); ?>" >
						<?php echo esc_html__( 'Update Product Types', 'amazon-for-woocommerce' ); ?>
					</button>

					<button type="button" class="components-button is-primary add-new-template-btn" data-woo-used-cat="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $wooUsedCategoriesArray ), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 ) ); ?>" data-woo-all-cat = "<?php print_r( htmlspecialchars( wp_json_encode( $allWooCategories ), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 ) ); ?>" >
						<?php echo esc_html__( 'Create new template', 'amazon-for-woocommerce' ); ?>
					</button>
				</div>
				
				<?php

		}

		if ( ! session_id() ) {
			session_start();
		}

		?>


		<!-- Clone modal code starts --> 
		<div id="cloneTemplateModal" class="ced-modal">
			<div class="ced-modal-text-content modal-body ced-amz-clone-tmp">

				<div class="ced-amaz-clone-response-modal">
					<div class="modal-body">
						<h2>Clone Template </h2>
						<form action="" method="post">

							<div class="components-card is-size-medium woocommerce-table pinterest-for-woocommerce-landing-page__faq-section css-1xs3c37-CardUI e1q7k77g0">
								<div class="components-panel ced-padding">	
									
									<table class="form-table">
										<?php
											$woo_store_categories = ced_amazon_get_categories_hierarchical(
												array(
													'taxonomy'   => 'product_cat',
													'hide_empty' => false,
												)
											);

										?>
										
										<tbody>
											<tr>
												<?php $this->ced_html_tags->print_table_label( 'Template Name', 'Enter the name for the new template.', true ); ?>
												<td class="forminp forminp-select">
													<input  class="clone_template_name" name="ced_amazon_profile_data[template_name]"  type="text" />
												</td> 
											</tr>
											<tr>
												<?php $this->ced_html_tags->print_table_label( 'WooCommerce Category', 'Select a WooCommerce category to map with the new template.', true ); ?>
												<td class="forminp forminp-select">
													<select  class="select2 wooCategories" name="ced_amazon_profile_data[wocoommerce_category][]"  multiple="multiple" >
														<?php ced_amazon_nestdiv( $woo_store_categories, $this->current_amazon_profile, 0, $amazon_wooCategories ); ?>
													</select>
					
												</td> 
											</tr>
											<tr>
												<td colspan="2">
												<p><i>During the template cloning process, please note that Amazon product type and template details will be automatically copied from the selected template.</i></p>
												</td>

											</tr>

										</tbody>
									</table>
									
								</div>
							</div>
							

							<div class="modal-footer" style="float: right; padding: 0px; margin-right: 10px; margin-bottom: 7px;" >
								<button type="button" class="components-button is-secondary ced-close-button ced_clone_modal_cancel button-primary woocommerce-save-button ced-cancel" refresh="false" >Close</button>
								<button class="components-button is-primary ced_amazon_clone_template_button"  ><?php esc_attr_e( 'Clone template', 'amazon-integration-for-woocommerce' ); ?></button>
							</div>

						</form>		

					</div>

				</div>
				
			</div>
		</div>


		<!-- Clone modal code ends -->

		<div id="post-body" class="metabox-holder columns-2">
			<div id="">
				<div class="meta-box-sortables ui-sortable">
					<form method="post">
						<?php
						wp_nonce_field( 'amazon_profile_view', 'amazon_profile_view_actions' );
						$this->display();
						?>
					</form>
				</div>
			</div>


			<div class="clear"></div>
		</div>


		<?php
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
			if ( ! isset( $_POST['amazon_profile_view_actions'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['amazon_profile_view_actions'] ) ), 'amazon_profile_view' ) ) {
				return;
			}
			$action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';
			
		}

		return $action;
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

		wp_nonce_field( 'ced_amazon_profiles_view_page_nonce', 'ced_amazon_profiles_view_nonce' );


		$url = ced_get_navigation_url(
			'amazon',
			array(
				'section'   => 'templates-view',
				'user_id'   => $this->user_id,
				'seller_id' => $this->seller_id,
			)
		);

		if ( 'bulk-delete' === $this->current_action() || ( isset( $_GET['action'] ) && 'bulk-delete' === $_GET['action'] ) ) {
			if ( ! isset( $_POST['amazon_profile_view_actions'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['amazon_profile_view_actions'] ) ), 'amazon_profile_view' ) ) {
				return;
			}
			$profileIds = isset( $sanitized_array['amazon_profile_ids'] ) ? $sanitized_array['amazon_profile_ids'] : array();

			if ( is_array( $profileIds ) && ! empty( $profileIds ) ) {

				global $wpdb;

				$seller_id_array = explode( '|', $this->seller_id );
				$country         = isset( $seller_id_array[0] ) ? $seller_id_array[0] : '';

				foreach ( $profileIds as $index => $pid ) {

					$product_ids_assigned = get_option( 'ced_amazon_product_ids_in_profile_' . $pid, array() );
					foreach ( $product_ids_assigned as $index => $ppid ) {
						delete_post_meta( $ppid, 'ced_amazon_profile_assigned' . $user_id );
					}

					$term_id = $wpdb->get_results( $wpdb->prepare( "SELECT `wocoommerce_category` FROM {$wpdb->prefix}ced_amazon_profiles WHERE `id` = %s ", $pid ), 'ARRAY_A' );
					$term_id = json_decode( $term_id[0]['wocoommerce_category'], true );
					foreach ( $term_id as $key => $value ) {
						delete_term_meta( $value, 'ced_amazon_profile_created_' . $user_id );
						delete_term_meta( $value, 'ced_amazon_profile_id_' . $user_id );
						delete_term_meta( $value, 'ced_amazon_mapped_category_' . $user_id );
					}
				}

				foreach ( $profileIds as $id ) {
					$ced_woo_amazon_mapping   = get_option( 'ced_woo_amazon_mapping', array() );
					$ced_woo_amazon_cat_array = isset( $ced_woo_amazon_mapping[ $this->seller_id ] ) ? $ced_woo_amazon_mapping[ $this->seller_id ] : array();

					if ( ! empty( $ced_woo_amazon_cat_array ) && is_array( $ced_woo_amazon_cat_array ) ) {

						unset( $ced_woo_amazon_mapping[ $this->seller_id ][ $id ] );
						update_option( 'ced_woo_amazon_mapping', $ced_woo_amazon_mapping );

					}

					$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}ced_amazon_profiles WHERE `id` IN (%s)", $id ) );

				}

				wp_safe_redirect( $url );
				exit();
			} else {

				wp_safe_redirect( $url );
				exit();

			}
		} elseif ( isset( $_GET['panel'] ) && 'edit' == $_GET['panel'] ) {

			$file = CED_AMAZON_DIRPATH . 'admin/partials/profile-edit-view.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		} else {

			wp_safe_redirect( $url );
			exit();

		}
	}
}

	$ced_amazon_profile_obj = new Ced_Amazon_Profile_Table();
	$ced_amazon_profile_obj->prepare_items();


?>


<script>

	jQuery(document).ready(function() {
		jQuery('.ced_amazon_select_category').selectWoo();
		jQuery(".wooCategories").selectWoo({
			dropdownPosition: 'below',
			dropdownAutoWidth : true,
			allowClear: true,
			placeholder: 'Select Category',
			width: '100%'
		});
	});

</script>
