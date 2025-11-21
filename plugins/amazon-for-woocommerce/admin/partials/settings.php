<?php

if ( ! defined( 'ABSPATH' ) ) {
	die;
}
$part              = isset( $_GET['part'] ) ? sanitize_text_field( $_GET['part'] ) : '';
$current_page      = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
$user_id           = isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '';
$seller_id         = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';
$sellernextShopIds = get_option( 'ced_amazon_remote_shop_ids', array() );
$amazon_accounts   = get_option( 'ced_amzon_configuration_validated', array() );


if ( empty( $seller_id ) ) {
	$seller_id = $sellernextShopIds[ $user_id ]['ced_mp_seller_key'];
}
if ( isset( $part ) && ! empty( $part ) ) {
	$sellernextShopIds[ $user_id ]['ced_amz_current_step'] = 2;
	update_option( 'ced_amazon_remote_shop_ids', $sellernextShopIds );
}


$seller_args = array( $seller_id );

$ced_amazon_general_options = get_option( 'ced_amazon_general_options', array() );

global $wpdb;
$results = $wpdb->get_results( "SELECT DISTINCT meta_key FROM {$wpdb->prefix}postmeta", 'ARRAY_A' );
$query   = $wpdb->get_results( $wpdb->prepare( "SELECT `meta_value` FROM  {$wpdb->prefix}postmeta WHERE `meta_key` LIKE %s", '_product_attributes' ), 'ARRAY_A' );


ced_import_header();

$file = CED_AMAZON_DIRPATH . 'admin/partials/ced_amazon_html_tags.php';
if ( file_exists( $file ) ) {
	require_once $file;
	$ced_html_tags = new Ced_Amazon_Html_Tags();
}

if ( isset( $_POST['ced_amazon_setting_nonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['ced_amazon_setting_nonce'] ), 'ced_amazon_setting_page_nonce' ) ) {
	if ( isset( $_POST['global_settings'] ) ) {

		$seller_id = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';

		$objDateTime = new DateTime( 'NOW' );
		$timestamp   = $objDateTime->format( 'Y-m-d\TH:i:s\Z' );
		
		/** Sanitized array */
		$sanitized_array = filter_input_array( INPUT_POST, FILTER_UNSAFE_RAW );

		/** Current settings of all countries */
		$all_current_settings = get_option( 'ced_amazon_global_settings', array() );

		/** Current settings of a country */
		$current_settings = $all_current_settings[$seller_id] ??  array();

		/** New settings for a country */ 
		$new_settings = $sanitized_array['ced_amazon_global_settings']  ?? array();

		/** Updating settings for the current country */
		$all_current_settings[ $seller_id ]                 = $new_settings;
		$all_current_settings[ $seller_id ]['last_updated'] = $timestamp;

		/** Current options for countries */
		$global_options_data = get_option( 'ced_amazon_general_options', array() );
		$current_options     = $global_options_data['general_options'] ?? array();

		/** New options for countries */
		$new_options = $sanitized_array['ced_amazon_general_options'] ?? array();

		$global_options_data[ 'general_options' ]                 = $new_options;
		$global_options_data[ 'general_options' ]['last_updated'] = $timestamp;

		$fields_to_check = [ 'settings' => array( 'ced_amazon_product_markup', 'ced_amazon_product_markup_type', 'ced_amazon_product_rounding_off_type' ), 'options' => array( 'ced_amazon_rsrve_stck', 'ced_amazon_listing_stock' ) ];

		$ced_amz_all_prc_inv_sync = get_option( 'ced_amz_all_prc_inv_sync', array() );

		$inventory_flag = isset( $ced_amz_all_prc_inv_sync[$seller_id] ) && isset( $ced_amz_all_prc_inv_sync[$seller_id]['inventory'] ) ? $ced_amz_all_prc_inv_sync[$seller_id]['inventory'] : 0;
		$price_flag     = isset( $ced_amz_all_prc_inv_sync[$seller_id] ) && isset( $ced_amz_all_prc_inv_sync[$seller_id]['price'] ) ? $ced_amz_all_prc_inv_sync[$seller_id]['price'] : 0; 

		foreach ( $fields_to_check as $key => $fields ) {

			foreach ( $fields as $field ) {

				if ( 'options' == $key ) {

					$new_data = isset( $new_options[$field] ) ?  $new_options[$field]  : '';
					$old_data = isset( $current_options[$field] ) ?  $current_options[$field] : '';

				} else {
					$new_data = isset( $new_settings[$field] ) ?  $new_settings[$field]  : '';
					$old_data = isset( $current_settings[$field] ) ? $current_settings[$field]  : '';
				}
				
				if ( $new_data !== $old_data ) {
					
					if ( 'options' == $key ) {
						$inventory_flag = 0;
					} else {
						$price_flag = 0;
					}

				}

			}

		}

		update_option( 'ced_amazon_general_options', $global_options_data );
		update_option( 'ced_amazon_global_settings', $all_current_settings );

		if ( 0 == $inventory_flag || 0 == $price_flag ) {
			ced_amz_trigger_sync( $inventory_flag, $price_flag, $seller_id );
		}

		if ( isset( $part ) && ! empty( $part ) ) {
			$sellernextShopIds                                     = get_option( 'ced_amazon_remote_shop_ids', array() );
			$sellernextShopIds[ $user_id ]['ced_amz_current_step'] = 3;
			update_option( 'ced_amazon_remote_shop_ids', $sellernextShopIds );
		}

		$message = 'saved';

	} elseif ( isset( $_POST['reset_global_settings'] ) ) {

		$ced_amazon_global_settings = get_option( 'ced_amazon_global_settings', array() );
		unset( $ced_amazon_global_settings[ $seller_id ] );
		update_option( 'ced_amazon_global_settings', $ced_amazon_global_settings );

		$message = 'reset';
	}

	$admin_success_notice = '<div class="saved_container" ><p class="text-green-800"> Your configuration has been ' . esc_html__( $message ) . ' ! </p> </div>';
	print_r( $admin_success_notice );

}


$renderDataOnGlobalSettings = get_option( 'ced_amazon_global_settings', array() );
$renderDataOnGlobalSettings = isset( $renderDataOnGlobalSettings[$seller_id] ) ? $renderDataOnGlobalSettings[$seller_id] : array();

$ced_amazon_order_currency = isset( $renderDataOnGlobalSettings['ced_amazon_order_currency'] ) ? $renderDataOnGlobalSettings['ced_amazon_order_currency'] : '';

$ced_amazon_general_options = get_option( 'ced_amazon_general_options', array() );
$ced_amazon_general_options = isset( $ced_amazon_general_options[ 'general_options' ] ) ? $ced_amazon_general_options[ 'general_options' ] : array();

if ( isset( $renderDataOnGlobalSettings['ced_amz_conversion_type'] ) && 'manual' == $renderDataOnGlobalSettings['ced_amz_conversion_type']  ) {
	$curr_con_style1 = 'display:""';
} else {
	$curr_con_style1 = 'display:none';
}

if ( isset( $renderDataOnGlobalSettings['ced_amz_conversion_type'] ) && 'automatic' == $renderDataOnGlobalSettings['ced_amz_conversion_type'] ) {
	$curr_con_style2 = 'display:""';
} else {
	$curr_con_style2 = 'display:none';
}


$ced_amazon_order_schedule_info = isset( $renderDataOnGlobalSettings['ced_amazon_order_schedule_info'] ) ? $renderDataOnGlobalSettings['ced_amazon_order_schedule_info']  : '';
if ( ! empty( $ced_amazon_order_schedule_info ) ) {
	$ced_amazon_order_schedule_info = 'is-checked';
}

$ced_amazon_inventory_schedule_info = isset( $renderDataOnGlobalSettings['ced_amazon_inventory_schedule_info'] ) ? $renderDataOnGlobalSettings['ced_amazon_inventory_schedule_info']  : '';
if ( ! empty( $ced_amazon_inventory_schedule_info ) ) {
	$ced_amazon_inventory_schedule_info = 'is-checked';
}

$ced_amazon_price_schedule_info = isset( $renderDataOnGlobalSettings['ced_amazon_price_schedule_info'] ) ? $renderDataOnGlobalSettings['ced_amazon_price_schedule_info']  : '';
if ( ! empty( $ced_amazon_price_schedule_info ) ) {
	$ced_amazon_price_schedule_info = 'is-checked';
}

$ced_amazon_existing_products_sync = isset( $renderDataOnGlobalSettings['ced_amazon_existing_products_sync'] ) ? $renderDataOnGlobalSettings['ced_amazon_existing_products_sync']  : '';
if ( ! empty( $ced_amazon_existing_products_sync ) ) {
	$ced_amazon_existing_products_sync = 'is-checked';
}


$amazon_catalog_asin_sync = isset( $renderDataOnGlobalSettings['ced_amazon_catalog_asin_sync'] ) ? $renderDataOnGlobalSettings['ced_amazon_catalog_asin_sync'] : '';

$amazon_catalog_asin_sync_value = 	'';										
if ( ! empty( $amazon_catalog_asin_sync ) ) {
	$amazon_catalog_asin_sync_value = 'is-checked';
}

$ced_amazon_shipment_tracking_plugin = isset( $renderDataOnGlobalSettings['ced_amazon_shipment_tracking_plugin'] ) ? $renderDataOnGlobalSettings['ced_amazon_shipment_tracking_plugin'] : '';
$ced_amazon_shipment_tracking        = isset( $renderDataOnGlobalSettings['ced_amazon_shipment_tracking'] ) ? $renderDataOnGlobalSettings['ced_amazon_shipment_tracking'] : '';


$mod_ced_amazon_shipment_tracking = '';

if ( 'on' == $ced_amazon_shipment_tracking  ) {
	$mod_ced_amazon_shipment_tracking = 'is-checked';
}


$settings_options = array(

	'order_import' => array(

		'label' => 'Orders Import Settings',
		'id' => 'ced-faq-wrapper-one',
		'rows' => array(

		   'Use Amazon Order Number' => array(
				'fields' => array(
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'checkbox',
							'name' => 'ced_use_amz_order_no',
						),
					)
				),
				'description' => 'Check this option if you want to create Amazon orders on WooCommerce using Amazon order number.',
				'name' => 'Use Amazon Order Number'

			),

			'Email Notifications' => array(
				'fields' => array(
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'checkbox',
							'name' => 'ced_amz_email_nfc',
						
						),
					),
				),
				'description' => 'Check this option if you want to receive woocommerce email notifications for Amazon Orders',
				'name' => 'Email Notifications'
			),

			'Order Acknowledgement' => array(
				'fields' => array(
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'checkbox',
							'name' => 'ced_amz_order_acknow',
						
						),
					),
				),
				'description' => 'Check this option if you want to map WooCommerce order number with Amazon orders on Amazon.',
				'name' => 'Order Acknowledgement'
			),

			'Create order in WooCommerce store currency' => array(
				'fields' => array(
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'checkbox',
							'id' => 'ced_amazon_order_currency',
							'name' => 'ced_amazon_order_currency',
							
						
						),
					),
				),
				'description' => 'By default, we will be creating Amazon orders in Amazon store currency.',
				'name' => 'Create order in WooCommerce store currency'
			),


			'Manual Currency' => array(
				'fields' => array(
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'radio',
							'style' => 'display: inline-block;',
							'parent_tag_open_html' => '<p>',
							'parent_tag_closing_html' => '</p>',
							'id' => 'ced_amz_manual_curr_change',
							'name' => 'ced_amz_conversion_type',
							
						),
						'options' => array( 'manual' => 'Manual Currency Conversion' ),
					),
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'number',
							'style' => $curr_con_style1,
							'id' => 'ced_amazon_currency_convert_rate',
							'name' => 'ced_amazon_currency_convert_rate',
							'class' => 'ced_amazon_currency_convert_rate_container',
							'extraAttributes' => array(  'min' => 0, 'step' => 0.01  )
						
						),
						
					),
				),
				
				'description' => '',
				'name' => ''
			),

			'Automatic Currency' => array(
				'fields' => array(
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'radio',
							'parent_tag_open_html' => '<p>',
							'parent_tag_closing_html' => '</p>',
							'style' => 'display: inline-block;',
							'id' => 'ced_amz_auto_curr_change',
							'name' => 'ced_amz_conversion_type',
							'extraAttributes' => array()
						
						),
						'options' => array(  'automatic' => 'Automatic Currency Conversion' ),
					),
					array(
						'tag'  => 'select',
						'attributes' => array(
							'style' => 'width: 100%;' . $curr_con_style2,
							'id' => 'ced_amazon_currency_conversion_plugin',
							'name' => 'ced_amazon_currency_conversion_plugin',
							'class' => 'ced_amazon_currency_conversion_plugin',
							
						),
						'options' => array( '' => '-- select --' , 'curcy' => 'Curcy' )
						
					),
				),
				
				'description' => '',
				'name' => ''
			),

			'Fulfillment Channels' => array(
				'fields' => array(
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'radio',
							'parent_tag_open_html' => '<p>',
							'parent_tag_closing_html' => '</p>',
							'name' => 'fulfillment_channels',
							'class' => 'ced_amz_fulfill_chn',
							
						),
						'default_value' => 'both',
						'options' => array( 'MFN' => 'Only FBM Orders', 'AFN' => 'Only FBA Orders', 'both' => 'Both Orders'),
					),
				),
				'description' => 'Check the fulfillment channels for which you want to import orders. By default we import order for both FBA AND FBM.',
				'name' => 'Fulfillment Channels'
			),

			'Amazon orders time limit' => array(
			   'fields' => array(
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'number',							
							'id' => 'ced_amazon_order_sync_time_limit',
							'name' => 'ced_amazon_order_sync_time_limit',
							'class' => 'ced_amz_fulfill_chn',
							'extraAttributes' => array()
						),
					),
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'hidden',							
							
						),
					),
				),
				'description' => 'Time in hours of which you want to fetch Amazon orders. By default, we fetch orders of last 24 hours.',
				'name' => 'Amazon orders time limit'
			),

			'Order Taxation' => array(
				'fields' => array(
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'radio',
							'parent_tag_open_html' => '<p>',
							'parent_tag_closing_html' => '</p>',
							'name' => 'ced_amazon_order_taxation_rule',
							'class' => 'ced_amazon_order_taxation_rule',
							
						),
						'default_value' => 'amz_tax',
						'options' => array( 'woo_tax' => 'WooCommerce Taxation', 'amz_tax' => 'Amazon Taxation'),
					),
				),
				'description' => 'Select the taxation rule, which will be automatically used while creating Amazon order in WooCommerce. By default, we use Amazon taxation.',
				'name' => 'Order Taxation'

			),

		)

	),

	'general_settings' => array(

		'label' => 'General Settings',
		'id' => 'ced-faq-wrapper-three',
		'rows' => array(

			'Markup' => array(
				'fields' => array(
					array(
						'tag'  => 'select',
						'attributes' => array(
							'style' => 'width: 100%;',
							'id' => 'ced_amazon_product_markup_type',
							'name' => 'ced_amazon_product_markup_type',
							
						),
						'options' => array( '' => 'Select', 'Fixed_Increased' => 'Fixed Increment', 'Fixed_Decreased' => 'Fixed Decrement', 'Percentage_Increased' => 'Percentage Increment', 'Percentage_Decreased' => 'Percentage Decrement'  ), 
							
					),
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'number',
							'style' => 'width: 100%;min-width:50px;',
							'id' => 'ced_amazon_product_markup',
							'name' => 'ced_amazon_product_markup',
							'extraAttributes' =>  array( 'min' => 0 )
						),
					),
				),
				'description' => 'Markup is the amount you include in prices to earn profit while selling on Amazon.',
				'name' => 'Markup'
			),

			'Rounding Off' => array(
				'fields' => array(
					array(
						'tag'  => 'select',
						'attributes' => array(
							
							'style' => 'width: 100%;',
							'id' => 'ced_amazon_product_rounding_off_type',
							'name' => 'ced_amazon_product_rounding_off_type',
							
						),
						'options' => array(
							'Nearest Higher' => array(
								'higherWholeNumber' => 'Whole number',
								'higherEndWith9' => 'End with 9',
								'higherEndWith10' => 'End with 10',
								'higherEndWith0.49' => 'End with 0.49',
								'higherEndWith0.99' => 'End with 0.99',
							),
							'Nearest Lower' => array(
								'lowerWholeNumber' => 'Whole number',
								'lowerEndWith9' => 'End with 9',
								'lowerEndWith10' => 'End with 10',
								'lowerEndWith0.49' => 'End with 0.49',
								'lowerEndWith0.99' => 'End with 0.99',
							),
							'No Rounding Off' => array(
								'' => 'No round off',
							),
						), 
							
					),
				),
				'description' => 'Select the rounding method to format the final price before submitting it to Amazon',
				'name' => 'Rounding Off'
			),

			'Price Type' => array(
				'fields' => array(
					array(
						'tag'  => 'select',
						'attributes' => array(
							
							'style' => 'width: 100%;',
							'id' => 'ced_amazon_product_price_type',
							'name' => 'ced_amazon_product_price_type',
							
						),
						'options' => array( 'regular_price' => 'Regular Price', 'sale_price' => 'Sale Price'  ), 
							
					),
				),
				'description' => 'Select the price type, you want to upload use while price sync. By default, regular price is used while syncing.',
				'name' => 'Price Type'
			)

		)

	),

	'global_options' => array(

		'label' => 'Global Options',
		'id' => 'ced-faq-wrapper-four',
		'rows' => array(

			'Reserve Stock' => array(

				'fields' => array(
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'number',
							'style' => 'width: 100%;min-width:50px;',
							'placeholder' => 'Enter reserve stock',
							'id' => 'ced_amazon_rsrve_stck',
							'name' => 'ced_amazon_rsrve_stck',
							'extraAttributes' =>  array( 'min' => 1 )
						),
					),

					array(
						'tag'  => 'select',
						'attributes' => array(
							'style' => 'width: 100%;',
							'id' => 'ced_amazon_rsrve_stck',
							'name' => 'ced_amazon_rsrve_stck',
							 
						),
						'renderHTML' => 'ced_amz_product_global_and_custom_attributes',
						
					),
				),
				'description' => 'Add the product stock/invenotry that you want to reserve for WooCommerce store.',
				'name' => 'Reserve Stock'
			),

			'Maximum Stock' => array(

				'fields' => array(
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'number',
							'style' => 'width: 100%;min-width:50px;',
							'placeholder' => 'Enter maximum stock',
							'id' => 'ced_amazon_listing_stock',
							'name' => 'ced_amazon_listing_stock',
							'extraAttributes' =>  array( 'min' => 1 )
						),
					),

					array(
						'tag'  => 'select',
						'attributes' => array(
							'style' => 'width: 100%;',
							'id' => 'ced_amazon_listing_stock',
							'name' => 'ced_amazon_listing_stock',
							 
						),
						'renderHTML' => 'ced_amz_product_global_and_custom_attributes',
						
					),

				),
				'description' => 'Add the stock/invenotry thhreshold to limit them on Amazon.',
				'name' => 'Maximum Stock'
			)

		),

		'content' => 'The values set in the Global Options fields will be applied to all connected Amazon accounts.'

	),

	'advanced_settings' => array(

		'label' => 'Advanced Settings',
		'id' => 'ced-faq-wrapper-five',
		'rows' => array(

			'Fetch Amazon orders' => array(
				'fields' => array(
					
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'checkbox',
							'parent_tag_open_html' => '<div class="woocommerce-list__item-after"><label class="components-form-toggle ' . $ced_amazon_order_schedule_info . '">',
							'parent_tag_closing_html' => '<span class="components-form-toggle__track"></span>
															<span class="components-form-toggle__thumb"></span>
														</label>
													</div>',
							'style' => '',
							'id' => 'inspector-toggle-control-0',
							'name' => 'ced_amazon_order_schedule_info',
							'class' => 'components-form-toggle__input ced-settings-checkbox'
							
						),
					),
				),

				'description' => 'Enable the setting to fetch Amazon orders into WooCommerce automatically.',
				'name' => 'Fetch Amazon orders'
			),

			'Update inventory on Amazon' => array(
				'fields' => array(
					
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'checkbox',
							'parent_tag_open_html' => '<div class="woocommerce-list__item-after"><label class="components-form-toggle ' . $ced_amazon_inventory_schedule_info . '">',
							'parent_tag_closing_html' => '<span class="components-form-toggle__track"></span>
															<span class="components-form-toggle__thumb"></span>
														</label>
													</div>',
							'style' => '',
							'id' => 'inspector-toggle-control-0',
							'name' => 'ced_amazon_inventory_schedule_info',
							'class' => 'components-form-toggle__input ced-settings-checkbox'
							
						),
					),
				),

				'description' => 'Enable the setting to update inventory from WooCommerce to Amazon automatically.',
				'name' => 'Update inventory on Amazon'
			),
			
			'Update price on Amazon' => array(
				'fields' => array(
					
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'checkbox',
							'parent_tag_open_html' => '<div class="woocommerce-list__item-after"><label class="components-form-toggle ' . $ced_amazon_price_schedule_info . '">',
							'parent_tag_closing_html' => '<span class="components-form-toggle__track"></span>
															<span class="components-form-toggle__thumb"></span>
														</label>
													</div>',
							'style' => '',
							'id' => 'inspector-toggle-control-0',
							'name' => 'ced_amazon_price_schedule_info',
							'class' => 'components-form-toggle__input ced-settings-checkbox'
							
						),
					),
				),

				'description' => 'Enable the setting to update price from WooCommerce to Amazon automatically.',
				'name' => 'Update price on Amazon'
			),

			'Existing products sync' => array(
				'fields' => array(

					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'checkbox',
							'parent_tag_open_html' => '<div class="woocommerce-list__item-after"><label class="components-form-toggle ' . $ced_amazon_existing_products_sync . '">',
							'parent_tag_closing_html' => '<span class="components-form-toggle__track"></span>
															<span class="components-form-toggle__thumb"></span>
														</label>
													</div>',
							'style' => '',
							'id' => 'inspector-toggle-control-0',
							'name' => 'ced_amazon_existing_products_sync',
							'class' => 'components-form-toggle__input ced-settings-checkbox'
							
						),
					),
				),

				'description' => 'Enable the setting to automatically link WooCommerce products with Amazon on the basis of SKU.',
				'name' => 'Existing products sync'
			),


			'ASIN sync' => array(
				'fields' => array(
					array(
						'tag'  => 'input',
						'attributes' => array(
							'type' => 'checkbox',
							'parent_tag_open_html' => '<div class="woocommerce-list__item-after"><label class="components-form-toggle ' . $amazon_catalog_asin_sync_value . '">',
							'parent_tag_closing_html' => '<span class="components-form-toggle__track"></span>
															<span class="components-form-toggle__thumb"></span>
														</label>
													</div>',
							'style' => '',
							'id' => 'inspector-toggle-control-0',
							'name' => 'ced_amazon_catalog_asin_sync',
							'class' => 'components-form-toggle__input ced-settings-checkbox'
							
						),
					),
					array(
						'tag'  => 'select',
						'attributes' => array(
							'style' => 'width: 100%;',
							'id' => 'ced_amazon_catalog_asin_sync_meta',
							'name' => 'ced_amazon_catalog_asin_sync_meta',
							 
						),
						'renderHTML' => 'ced_amz_product_global_and_custom_attributes',
						
					),
				),

				'description' => 'Enable the scheduler to automatically fetch ASIN of WooCommerce products with the help of Barcodes. Fill the Barcode metakey in the selctbox. If no metakey is filled, SKU will be used as defauly key.',
				'name' => 'ASIN sync'
			),

			'Shipment Tracking' => array(
				'fields' => array(

					array(
						'tag'  => 'input',
						'attributes' => array(
							'type'   => 'checkbox',
							'parent_tag_open_html'    => '<div class="woocommerce-list__item-after"><label class="components-form-toggle ' . $mod_ced_amazon_shipment_tracking . '">',
							'parent_tag_closing_html' => '<span class="components-form-toggle__track"></span>
															<span class="components-form-toggle__thumb"></span>
														</label>
													</div>',
							'style' => '',
							'id' => 'inspector-toggle-control-0',
							'name' => 'ced_amazon_shipment_tracking',
							'class' => 'components-form-toggle__input ced-settings-checkbox'
							
						),
					),
					array(
						'tag'  => 'select',
						'attributes' => array(
							'style' => 'width: 100%;',
							'id' => 'ced_amazon_shipment_tracking_plugin',
							'name' => 'ced_amazon_shipment_tracking_plugin',
							 
						),
						'options' => array(
							'' => '-- select --',
							'Advanced Shipment Tracking for WooCommerce' => 'Advanced Shipment Tracking for WooCommerce',
							
						)
						// 'renderHTML' => 'ced_amz_product_global_and_custom_attributes'
					),
					
				),
				'description' => 'Enable the scheduler to automatically send tracking details to Amazon.',
				'name' => 'Shipment Tracking'
			),


		)

	)


);

$settings_options = apply_filters('ced_amazon_settings_options', $settings_options);


function ced_amz_trigger_sync( $inventory_flag, $price_flag, $seller_id ) {

	$ced_amz_all_prc_inv_sync = get_option( 'ced_amz_all_prc_inv_sync', array() );

	$ced_amz_all_prc_inv_sync[$seller_id]['price']     = $price_flag;
	$ced_amz_all_prc_inv_sync[$seller_id]['inventory'] = $inventory_flag;

	update_option( 'ced_amz_all_prc_inv_sync', $ced_amz_all_prc_inv_sync );

}

?>

<form action="" method="post">
	<div
		class="components-card is-size-medium woocommerce-table pinterest-for-woocommerce-landing-page__faq-section css-1xs3c37-CardUI e1q7k77g0">
		<div class="components-panel ced_amazon_settings_new">
			<div class="wc-progress-form-content woocommerce-importer ced-padding">

				<?php


				foreach ( $settings_options as $key => $settings_container ) {
					?>

					<div class="ced-faq-wrapper">

						<input class="ced-faq-trigger" id="<?php echo esc_attr( $settings_container['id']); ?>" type="checkbox" <?php echo 'order_import' == $key ? 'checked' : ''; ?> ><label class="ced-faq-title" for="<?php echo esc_attr( $settings_container['id']); ?>"><?php echo esc_attr( $settings_container['label']); ?></label>
						<div class="ced-faq-content-wrap">
							<div class="ced-faq-content-holder">
								<div class="ced-form-accordian-wrap">
									<div class="wc-progress-form-content woocommerce-importer">
										<header>
											<table class="form-table">
												<tbody> 
													
													<?php
														$style = ''; 
													if ( 'general_settings' == $key ) {
														?>
															<tr valign="top" >
															<?php
																$ced_html_tags->print_table_label( 'Column name', '', false, true );
																$ced_html_tags->print_table_label( 'Map to Options', '', false, true );
																$ced_html_tags->print_table_label( 'Custom Value', '', false, true  );
															?>
															</tr>
															<?php
													} else {
														?>
															<tr><th></th><th></th><th></th></tr>
														<?php
													}
														 
													?>

												<!-- </tr> -->

												<?php
												
													$length = 0;
												foreach ( $settings_container['rows'] as $row_name => $row_data ) { 
														
													++$length;
													if ( 'Automatic Currency' == $row_name || 'Manual Currency' == $row_name ) {
														$className = 'ced_amz_currency_convert_row';

														if ( '1' == $ced_amazon_order_currency || 'on' == $ced_amazon_order_currency ) {
															$style = "display:''";
														} else {
															$style = 'display:none';  
														}
															
													} else {
														$className = '';
														$style     = '';
													}


													if ( 'JSON Listings' == $row_name  ) {
														$style = 'display:none';  
													} 
														
													?>
														
														<tr class="<?php echo esc_attr($className); ?>" style="<?php echo esc_attr($style); ?>" >
														
														<?php
														
														$ced_html_tags->print_table_label( $row_data['name'], $row_data['description'], true, true  ); 
															
														//  to map a single row fields
														foreach ( $row_data['fields'] as $row_columns ) { 
															
															if ( is_array( $row_columns ) ) {


																$_id                     = isset( $row_columns['attributes']['id'] ) ? $row_columns['attributes']['id'] : '';
																$class                   = isset( $row_columns['attributes']['class'] ) ? $row_columns['attributes']['class'] : '';
																$placeholder             = isset( $row_columns['attributes']['placeholder'] ) ? $row_columns['attributes']['placeholder'] : '';
																$style                   = isset( $row_columns['attributes']['style'] ) ? $row_columns['attributes']['style'] : '';
																$extraAttributes         = isset( $row_columns['attributes']['extraAttributes'] ) ? $row_columns['attributes']['extraAttributes'] : array();
																$parent_tag_open_html    = isset( $row_columns['attributes']['parent_tag_open_html'] ) ? $row_columns['attributes']['parent_tag_open_html'] : '';
																$parent_tag_closing_html = isset( $row_columns['attributes']['parent_tag_closing_html'] ) ? $row_columns['attributes']['parent_tag_closing_html'] : '';

																?>

																	<td class="forminp forminp-select" style="width: 200px;" >
																	<?php

																		$name = isset( $row_columns['attributes']['name'] ) ? $row_columns['attributes']['name'] : '';
																	if ( 'global_options' == $key ) {
																		$saved_value = isset( $ced_amazon_general_options[$name] ) ? $ced_amazon_general_options[$name] : '';
																	} else {
																		$saved_value = isset( $renderDataOnGlobalSettings[$name] ) ? $renderDataOnGlobalSettings[$name] : '';
																	}

																	if ( isset( $row_columns['attributes']['type'] ) && 'checkbox' == $row_columns['attributes']['type'] ) {
																		$checkbox_checked = ''; 
																		if ( ! empty( $saved_value ) && ( '1' == $saved_value ||  'on' == $saved_value ) ) {
																			$checkbox_checked = 'checked';
																		}
																		$saved_value = $checkbox_checked;
																	} 


																	if ( isset( $row_columns['renderHTML'] ) ) {
																		/** To redner fields having attributes names */
																		if ( 'global_options' == $key  ) {
																			$renderHtmlName = 'ced_amazon_general_options';
																		} else {
																			$renderHtmlName = 'ced_amazon_global_settings';
																		}
																		$func = $row_columns['renderHTML'];
																		$ced_html_tags->$func( $ced_amazon_general_options, $renderDataOnGlobalSettings , $results, $query, $row_columns, $row_data['description'], $renderHtmlName );

																	} elseif (  'p' == $row_columns['tag'] ) { 
																				
																		$ced_html_tags->content_tags( 'p', true, $row_columns['content'], $class );
																				
																	} elseif ( isset( $row_columns['options'] ) && 'input' == $row_columns['tag'] ) { 

																		foreach ( $row_columns['options'] as $field_value => $field_label ) { 

																			if ( 'input' == $row_columns['tag'] ) {

																				$checked = '';

																				if ( 'Fulfillment Channels' == $row_name || 'Order Taxation' == $row_name || 'Manual Currency' == $row_name || 'Automatic Currency' == $row_name ) { 
																					if ( $saved_value == $field_value  ) {
																						$checked = 'checked';
																					} elseif ( '' == $saved_value && isset( $row_columns['default_value'] ) && $field_value == $row_columns['default_value'] ) {
																						$checked = 'checked';
																					}
																									
																				} 

																				$ced_html_tags->input_tag( $row_columns['attributes']['type'], 'ced_amazon_global_settings[' . $name . ']', $class, 
																							$_id, $saved_value, $style, $placeholder,
																							$extraAttributes, array(  $field_value => $field_label ), $checked, $parent_tag_open_html, $parent_tag_closing_html ); 

																			} elseif ( 'select' == $row_columns['tag'] ) {

																				$ced_html_tags->select_tag( 'ced_amazon_global_settings[' . $name . ']', $class, 
																						$_id, $saved_value, $style, $row_columns['options'], $extraAttributes ); 

																			}
																						
																		}

																	} else { 
																					
																		if ( ( 'input' == $row_columns['tag'] || 'select' == $row_columns['tag'] ) && 'global_options' == $key ) {
																			$name        = 'ced_amazon_general_options[' . $name . '][default]';
																			$saved_value = isset( $saved_value['default'] ) ? $saved_value['default'] : '';
																		} else {
																			$name        = 'ced_amazon_global_settings[' . $name . ']';
																			$saved_value = $saved_value;
																		}
																						
																		if ( 'input' == $row_columns['tag'] ) {
																			$ced_html_tags->input_tag( $row_columns['attributes']['type'], $name, $class , $_id, $saved_value, $style, 
																			$placeholder, $extraAttributes, array(), $checkbox_checked, $parent_tag_open_html, $parent_tag_closing_html
																			); 

																		} elseif ( 'select' == $row_columns['tag'] ) {

																			$mod_options = apply_filters( 'ced_amz_add_custom_shipping_carriers', $row_columns['options'], $name );
																			$ced_html_tags->select_tag(  $name , $class, $_id, $saved_value, $style, $mod_options, $extraAttributes ); 

																		}	

																	}

																	?>
																		
																	</td>

																	<?php
															}

														}
															
														?>
														
														</tr>
													
													 <?php
												}

												?>
												</tbody>
										</table>
									</header>
								</div>
							</div>
						</div>
					</div>

				</div>

				<?php
				}

				?>

				<div class="ced-margin-top">
				   <?php wp_nonce_field( 'ced_amazon_setting_page_nonce', 'ced_amazon_setting_nonce' ); ?>
					<button id="save_global_settings" class="config_button components-button is-primary" style="float: right;"
						name="global_settings">
						<?php echo esc_html__( 'Save', 'amazon-for-woocommerce' ); ?>
					</button>
				</div>

			</div>
		</div>
	</div>

	
</form>

