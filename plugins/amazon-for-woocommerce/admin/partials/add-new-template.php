<?php



if ( ! defined( 'ABSPATH' ) ) {
	die;
}


$user_id   = isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '';
$seller_id = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';
// $template_type = isset( $_GET['template_type'] ) ? sanitize_text_field( $_GET['template_type'] ) : '';
$template_id = isset( $_GET['template_id'] ) ? sanitize_text_field( $_GET['template_id'] ) : '';

global $wpdb;
$tableName       = $wpdb->prefix . 'ced_amazon_profiles';
$amazon_profiles = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ced_amazon_profiles WHERE `seller_id` = %s ", $seller_id ), 'ARRAY_A' );

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

/** Import the header */
ced_import_header();

$file = CED_AMAZON_DIRPATH . 'admin/partials/ced_amazon_html_tags.php';
if ( file_exists( $file ) ) {
	require_once $file;
	$ced_html_tags = new Ced_Amazon_Html_Tags();
}

if ( empty( $seller_id ) ) {
	echo '<div class="notice notice-error is-dismissable">
	 	<p>Seller id is missing, please check your amazon account connected properlly!</p>
	</div>';
	return;
}

$profile_data         = false;
$woo_store_categories = ced_amazon_get_categories_hierarchical(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	)
);


$tableName              = $wpdb->prefix . 'ced_amazon_profiles';
$result                 = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ced_amazon_profiles WHERE `id` = %s ", $template_id ), 'ARRAY_A' );
$current_amazon_profile = isset( $result[0] ) ? $result[0] : array();

if ( isset( $_POST['ced_amazon_profile_edit'] ) && wp_verify_nonce( sanitize_text_field( $_POST['ced_amazon_profile_edit'] ), 'ced_amazon_profile_edit_page_nonce' ) ) {
	if ( isset( $_POST['add_meta_keys'] ) || isset( $_POST['ced_amazon_profile_save_button'] ) ) {

		$sanitized_array     = filter_input_array( INPUT_POST, FILTER_UNSAFE_RAW );
		$amazon_profile_data = isset( $sanitized_array['ced_amazon_profile_data'] ) ? ( $sanitized_array['ced_amazon_profile_data'] ) : array();

		$seller_id = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';
		if ( empty( $seller_id ) ) {
			echo '<div class="notice notice-error is-dismissable">
			 	<p>Seller id is missing, please check your amazon account connected properlly!</p>
			</div>';
			return;
		}

		$profileDetails = array(
			
			'wocoommerce_category'   => $amazon_profile_data['wocoommerce_category'], 
			'product_type'           => $amazon_profile_data['product_type'],
			'profile_name'           => $amazon_profile_data['profile_name'],

			'primary_category'       => $amazon_profile_data['primary_category'],
			'secondary_category'     => $amazon_profile_data['secondary_category'],
			'browse_nodes'           => $amazon_profile_data['browse_nodes'],
			
			'browse_nodes_name'      => $amazon_profile_data['browse_nodes_name'],
			'amazon_categories_name' => $amazon_profile_data['amazon_categories_name'],

		);
		
		unset( $amazon_profile_data['primary_category'] );
		unset( $amazon_profile_data['secondary_category'] );
		unset( $amazon_profile_data['browse_nodes'] );
		unset( $amazon_profile_data['browse_nodes_name'] );
		unset( $amazon_profile_data['amazon_categories_name'] );

		unset( $amazon_profile_data['wocoommerce_category'] );
		unset( $amazon_profile_data['product_type_name'] );
		unset( $amazon_profile_data['product_type'] );
		unset( $amazon_profile_data['profile_name'] );

		$mod_amazon_profile_data = array();

		foreach ( $amazon_profile_data as $key => $value ) {
			$mod_amazon_profile_data[$key] = '';
		}

		$category_attributes_structure              = wp_json_encode( $mod_amazon_profile_data );
		$profileDetails['category_attributes_data'] = wp_json_encode( $amazon_profile_data );

		
		global $wpdb;
		$tableName = $wpdb->prefix . 'ced_amazon_profiles';

		$template_id = isset( $_GET['template_id'] ) ? sanitize_text_field( $_GET['template_id'] ) : '';
		if ( empty( $template_id ) ) {


			$wpdb->insert(
				$tableName,
				array(

					'primary_category'              => $profileDetails['primary_category'],
					'secondary_category'            => $profileDetails['secondary_category'],
					
					'category_attributes_response'  => '',
					'profile_name'                  => $profileDetails['profile_name'],
					'wocoommerce_category'          => wp_json_encode( $profileDetails['wocoommerce_category'] ),
					'product_type'                  => $profileDetails['product_type'],
					'category_attributes_structure' => $category_attributes_structure,
					
					'browse_nodes'                  => $profileDetails['browse_nodes'],
					'browse_nodes_name'             => $profileDetails['browse_nodes_name'],
					'amazon_categories_name'        => $profileDetails['amazon_categories_name'],

					'category_attributes_data'      => $profileDetails['category_attributes_data'],
					'seller_id'                     => $seller_id,
			
				),
				array( '%s' )
			);


			if ( isset( $profileDetails['wocoommerce_category'] ) && ! empty( $profileDetails['wocoommerce_category'] ) ) {
				$temp_id = $wpdb->insert_id;

				$ced_woo_amazon_mapping                           = get_option( 'ced_woo_amazon_mapping', array() );
				$ced_woo_amazon_mapping[ $seller_id ][ $temp_id ] = $profileDetails['wocoommerce_category'];
				update_option( 'ced_woo_amazon_mapping', $ced_woo_amazon_mapping );
			}
		} else {

			$wpdb->update(
				$tableName,
				array(

					'primary_category'              => $profileDetails['primary_category'],
					'secondary_category'            => $profileDetails['secondary_category'],
					'category_attributes_response'  => '',
					
					'profile_name'                  => $profileDetails['profile_name'],
					'wocoommerce_category'          => wp_json_encode( $profileDetails['wocoommerce_category'] ),
					'product_type'                  => $profileDetails['product_type'],
					'category_attributes_structure' => $category_attributes_structure,
					'category_attributes_data'      => $profileDetails['category_attributes_data'],
					
					'browse_nodes'                  => (int) $profileDetails['browse_nodes'],
					'browse_nodes_name'             => $profileDetails['browse_nodes_name'],
					'seller_id'                     => $seller_id,

				),
				array( 'id' => $template_id ),
				array( '%s' )
			);

			if ( isset( $profileDetails['wocoommerce_category'] ) && ! empty( $profileDetails['wocoommerce_category'] ) ) {
				$ced_woo_amazon_mapping                               = get_option( 'ced_woo_amazon_mapping', array() );
				$ced_woo_amazon_mapping[ $seller_id ][ $template_id ] = $profileDetails['wocoommerce_category'];
				update_option( 'ced_woo_amazon_mapping', $ced_woo_amazon_mapping );
			}
		}



		$user_id   = isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '';
		$seller_id = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';

		$url = ced_get_navigation_url(
			'amazon',
			array(
				'section'   => 'templates-view',
				'user_id'   => $user_id,
				'seller_id' => $seller_id,
			)
		);

		wp_safe_redirect( $url );

	}
}

$template_id = isset( $_GET['template_id'] ) ? sanitize_text_field( $_GET['template_id'] ) : '';

if ( ! empty( $template_id ) ) {
	global $wpdb;
	$tableName = $wpdb->prefix . 'ced_amazon_profiles';
	$result    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ced_amazon_profiles WHERE `id` = %s ", $template_id ), 'ARRAY_A' );

	$current_amazon_profile = isset( $result[0] ) ? $result[0] : array();

}


?>

<div class="" style="padding: 20px;" >

	<div class="">
		<div class="ced-button-wrapper-top"> 
			<?php
			if ( ! empty( $template_id ) ) {

				?>
					<div style="" >
					<?php echo wc_help_tip( 'Clicking on the Refresh Template button will refresh the existing template and sync in new template as per the latest standards of Amazon.', 'amazon-for-woocommerce' ); ?>
						<button class="components-button is-primary" id="update_template" > <?php esc_attr_e( 'Refresh template', 'amazon-for-woocommerce' ); ?> </button> 
					</div>
					<?php
			}

			?>

		</div>
	</div>
</div>

<form action="" method="post">
	
		<div class="components-card is-size-medium woocommerce-table pinterest-for-woocommerce-landing-page__faq-section css-1xs3c37-CardUI e1q7k77g0 ced_profile_table">

		<div class="components-panel ced-padding profile_name">
				
				<div class="profile_name_sidebar" >
					<h2> <?php esc_attr_e( 'Template Name', 'amazon-for-woocommerce' ); ?> </h2>
					<!-- <span> <?php //esc_attr_e( '', 'amazon-for-woocommerce' ); ?> </span> -->
				</div>

				<table class="form-table css-off1bd profile_name_section">
					<tbody>
						<tr>
							<td class="forminp forminp-input">
								<?php 
									$profile_name = '';
								if ( '' != $template_id  ) {
									$profile_name = $current_amazon_profile['profile_name']; 
								}
								?>
								<input class="profile_name" name="ced_amazon_profile_data[profile_name]"  placeholder="Enter Template Name" value="<?php echo esc_attr($profile_name); ?>" />
							</td> 
						</tr>

					</tbody>
				</table>

			</div>

			<hr/>

			
			<div class="components-panel ced-padding category_mapping">
				
				<div class="category_mapping_sidebar" >
					<h2> <?php esc_attr_e( 'Category Mapping', 'amazon-for-woocommerce' ); ?> </h2>
					<span> <?php esc_attr_e( "Allocate an Amazon category to the template and then link the designated Amazon category to the WooCommerce category that you've already set in advance.", 'amazon-for-woocommerce' ); ?> </span>
				</div>

				<table class="form-table css-off1bd category_mapping_section">
					<tbody>

						<tr>
							<?php $ced_html_tags->print_table_label( 'WooCommerce Category', true ); ?>
						</tr>
						<tr>
							<td class="forminp forminp-select">
								<select  class="select2 wooCategories" name="ced_amazon_profile_data[wocoommerce_category][]"  multiple="multiple" >
									<?php ced_amazon_nestdiv( $woo_store_categories, $current_amazon_profile, 0, $amazon_wooCategories ); ?>
								</select>
   
							</td> 
						</tr>

					<?php

					if (  '' == $template_id ) {
						?>

							<tr>
								<?php $ced_html_tags->print_table_label( 'Amazon Category', true ); ?> 
							</tr>
							<tr>
								<td class="forminp forminp-select">
									<?php
									$file = CED_AMAZON_DIRPATH . 'admin/partials/class-ced-amazon-categories.php';
									if ( file_exists( $file ) ) {
										require_once $file;
										$obj = new Ced_Amazon_Get_Categoires( $user_id, $seller_id );
									}
									?>
								</td>
							</tr>
							<?php 
					} elseif ( '' !== $template_id ) {
						?>

								<tr>  <?php $ced_html_tags->print_table_label( 'Amazon Category', true ); ?> </tr>
								<tr>
									<td>
										<input type="hidden" class="ced_primary_category" name="ced_amazon_profile_data[primary_category]" value="<?php echo esc_attr( $current_amazon_profile['primary_category'] ); ?>" />
										<input type="hidden" class="ced_secondary_category" name="ced_amazon_profile_data[secondary_category]" value="<?php echo esc_attr( $current_amazon_profile['secondary_category'] ); ?>" />
										<input type="hidden" class="ced_browse_category" name="ced_amazon_profile_data[browse_nodes]" value="<?php echo esc_attr( $current_amazon_profile['browse_nodes'] ); ?>" />
										<input type="hidden" class="ced_browse_node_name" name="ced_amazon_profile_data[browse_nodes_name]" value="<?php echo esc_attr( $current_amazon_profile['browse_nodes_name'] ); ?>" />
											
										<input type="hidden" class="ced_product_type" name="ced_amazon_profile_data[product_type]" value = "<?php echo esc_attr( $current_amazon_profile['product_type'] ); ?>" />
										<?php
										$categoryArray = array(
											'primary_category' => isset( $current_amazon_profile['primary_category'] ) ? $current_amazon_profile['primary_category'] : '',
											'secondary_category' => isset( $current_amazon_profile['secondary_category'] ) ? $current_amazon_profile['secondary_category'] : '',
											'browse_nodes' => isset( $current_amazon_profile['browse_nodes'] ) ? $current_amazon_profile['browse_nodes'] : '',
											'browse_nodes_name' => isset( $current_amazon_profile['browse_nodes_name'] ) ? $current_amazon_profile['browse_nodes_name'] : '',
										);

										$amazonCategories = '';
										if ( ! empty( $current_amazon_profile['amazon_categories_name'] ) ) {
											$amazonCategories = $current_amazon_profile['amazon_categories_name'];
										}

										?>
										<p class="ced_amz_productType_label" ><?php echo esc_attr_e( str_replace( '_', ' ', $amazonCategories ), 'amazon-for-woocommerce' ); ?></p>
									</td>
								</tr>

								<tr>  <?php $ced_html_tags->print_table_label( 'Amazon Product Type', true ); ?>  </tr>
								<tr>
									<td>
										<input type="hidden" class="ced_product_type" name="ced_amazon_profile_data[product_type]" value = "<?php echo esc_attr( $current_amazon_profile['product_type'] ); ?>" />
										<p class="ced_amz_productType_label" ><?php echo esc_attr_e( str_replace( '_', ' ', $current_amazon_profile['product_type'] ), 'amazon-for-woocommerce' ); ?></p>
									</td>
								</tr>

							<?php 
					} 
					?>
						
					</tbody>
				</table>

			</div>

			<hr/>

			<div class="components-panel ced-padding category_attributes">
				
				<div class="attributes_sidebar" >
					<h2> <?php esc_attr_e( 'Attributes', 'amazon-for-woocommerce' ); ?> </h2>
					<span> 
					<?php
					esc_attr_e( 'Attributes are organized by category. It is essential to complete the necessary attributes. You have the option to complete the optional or recommended attributes according to your preference.
					Providing attribute information can enhance your Amazon listings.', 'amazon-for-woocommerce' );
					?>
					</span>
				</div>

				<div class="ced_amz_attributes_sections ced_template_required_attributes"></div>

			</div>


		</div>
		
		<?php wp_nonce_field( 'ced_amazon_profile_edit_page_nonce', 'ced_amazon_profile_edit' ); ?>
		<div class="wc-actions">
			<button type="submit" class="components-button is-primary save_profile_button button-next" name="ced_amazon_profile_save_button" >Save template</button>
		</div>

</form>

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
