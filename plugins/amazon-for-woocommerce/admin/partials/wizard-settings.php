<?php

if ( ! defined( 'ABSPATH' ) ) {
	die;
}
$part         = isset( $_GET['part'] ) ? sanitize_text_field( $_GET['part'] ) : '';
$current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
$user_id      = isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '';
$seller_id    = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';

$sellernextShopIds = get_option( 'ced_amazon_remote_shop_ids', array() );
$amazon_accounts   = get_option( 'ced_amzon_configuration_validated', array() );


if ( empty( $seller_id ) ) {
	$seller_id = $sellernextShopIds[ $user_id ]['ced_mp_seller_key'];
}
if ( isset( $part ) && ! empty( $part ) ) {
	$sellernextShopIds[ $user_id ]['ced_amz_current_step'] = 2;
	update_option( 'ced_amazon_remote_shop_ids', $sellernextShopIds );
}

$file = CED_AMAZON_DIRPATH . 'admin/partials/ced_amazon_html_tags.php';
if ( file_exists( $file ) ) {
	require_once $file;
	$ced_html_tags = new Ced_Amazon_Html_Tags();
}

// Set this argument to pass in CRON scheduler
$seller_args = array( $seller_id );

// Prepare dropdown for meta keys end
if ( '' !== $part ) {

	$connection_setup           = '';
	$integration_settings_setup = '';
	$amazon_options_setup       = '';
	$general_settings_setup     = '';
	if ( empty( $part ) || 'ced-amazon-login' == $part ) {
		$connection_setup = 'active';
	} elseif ( 'amazon-options' == $part ) {
		$amazon_options_setup = 'active';
	} elseif ( 'settings' == $part ) {
		$general_settings_setup = 'active';
	} elseif ( 'configuration' == $part ) {
		$integration_settings_setup = 'active';
	}
}


if ( isset( $_POST['ced_amazon_setting_nonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['ced_amazon_setting_nonce'] ), 'ced_amazon_setting_page_nonce' ) ) {
	if ( isset( $_POST['global_settings'] ) ) {

		$objDateTime         = new DateTime( 'NOW' );
		$timestamp           = $objDateTime->format( 'Y-m-d\TH:i:s\Z' );
		$global_setting_data = get_option( 'ced_amazon_global_settings', array() );
		$settings            = array();
		$sanitized_array     = filter_input_array( INPUT_POST, FILTER_UNSAFE_RAW );

		if ( isset( $part ) && ! empty( $part ) ) {
			$sellernextShopIds                                     = get_option( 'ced_amazon_remote_shop_ids', array() );
			$sellernextShopIds[ $user_id ]['ced_amz_current_step'] = 3;
			update_option( 'ced_amazon_remote_shop_ids', $sellernextShopIds );
		}

		$settings = get_option( 'ced_amazon_global_settings', array() );

		$sanitized_array['ced_amazon_global_settings']['ced_amazon_inventory_schedule_info'] = 'on';
		$sanitized_array['ced_amazon_global_settings']['ced_amazon_existing_products_sync']  = 'on';

		$old_settings = isset( $settings[ $seller_id ] ) ? $settings[ $seller_id ] : array();

		$new_settings                 = isset( $sanitized_array['ced_amazon_global_settings'] ) ? ( $sanitized_array['ced_amazon_global_settings'] ) : array();
		$new_settings['last_updated'] = $timestamp;
		$new_settings                 = array_merge( $old_settings, $new_settings );
		$settings[ $seller_id ]       = $new_settings;

		update_option( 'ced_amazon_global_settings', $settings );

		$seller_args = array( $seller_id );

		if ( function_exists( 'as_has_scheduled_action' ) ) {
			as_schedule_recurring_action( time(), 600, 'ced_amazon_existing_products_sync_job_' . $seller_id, $seller_args );
		} else {

			if ( as_has_scheduled_action( 'ced_amazon_existing_products_sync_job_' . $seller_id, $seller_args ) ) {
				as_unschedule_all_actions( 'ced_amazon_existing_products_sync_job_' . $seller_id, $seller_args );
			}
			as_schedule_recurring_action( time(), 600, 'ced_amazon_existing_products_sync_job_' . $seller_id, $seller_args );

		}


		$url = ced_get_navigation_url(
			'amazon',
			array(
				'section'   => 'setup-amazon',
				'part'      => 'configuration',
				'user_id'   => $user_id,
				'seller_id' => $seller_id,
			)
		);

		wp_safe_redirect( $url );


	} elseif ( isset( $_POST['reset_global_settings'] ) ) {

		$ced_amazon_global_settings = get_option( 'ced_amazon_global_settings', array() );
		unset( $ced_amazon_global_settings[ $seller_id ] );
		update_option( 'ced_amazon_global_settings', $ced_amazon_global_settings );


		if ( as_has_scheduled_action( 'ced_amazon_price_scheduler_job_' . $seller_id, $seller_args ) ) {
			as_unschedule_all_actions( 'ced_amazon_price_scheduler_job_' . $seller_id, $seller_args );
		}

		if ( as_has_scheduled_action( 'ced_amazon_inventory_scheduler_job_' . $seller_id, $seller_args ) ) {
			as_unschedule_all_actions( 'ced_amazon_inventory_scheduler_job_' . $seller_id, $seller_args );
		}

		if ( as_has_scheduled_action( 'ced_amazon_order_scheduler_job_' . $seller_id, $seller_args ) ) {
			as_unschedule_all_actions( 'ced_amazon_order_scheduler_job_' . $seller_id, $seller_args );
		}

		if ( as_has_scheduled_action( 'ced_amazon_existing_products_sync_job_' . $seller_id, $seller_args ) ) {
			as_unschedule_all_actions( 'ced_amazon_existing_products_sync_job_' . $seller_id, $seller_args );
		}

		if ( as_has_scheduled_action( 'ced_amazon_catalog_asin_sync_job_' . $seller_id, $seller_args ) ) {
			as_unschedule_all_actions( 'ced_amazon_catalog_asin_sync_job_' . $seller_id, $seller_args );
		}
	}
}

$renderDataOnGlobalSettings = get_option( 'ced_amazon_global_settings', false );

$settings_options = array(

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
							'placeholder' => 'Enter Value',
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
			),

		)

	),

);


?>

<div class="woocommerce-progress-form-wrapper">
	<h2 style="text-align: left;"> <?php echo esc_html__( 'Amazon for WooCommerce: Onboarding', 'amazon-for-woocommerce' ); ?></h2>
	<ol class="wc-progress-steps ced-progress">
		<li class="done"> <?php echo esc_html__( 'Global Options', 'amazon-for-woocommerce' ); ?></li>
		<li class="active"> <?php echo esc_html__( 'General Settings', 'amazon-for-woocommerce' ); ?></li>
		<li class=""><?php echo esc_html__( 'Done!', 'amazon-for-woocommerce' ); ?></li>
	</ol>
	<div class="wc-progress-form-content woocommerce-importer">
		<header>
			<h2><?php echo esc_html__( 'General Settings', 'amazon-for-woocommerce' ); ?></h2>
		</header>

		<header>
			<form  method="post" >
				<h3><?php echo esc_html__( 'Listings Configuration', 'amazon-for-woocommerce' ); ?></h3>
				<p><?php echo esc_html__( 'Effortlessly adjust Amazon listing prices and WooCommerce stock levels: Increase or decrease prices and efficiently manage inventory.', 'amazon-for-woocommerce' ); ?></p>
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<?php
								$ced_html_tags->print_table_label( 'Column name', '', false );
								$ced_html_tags->print_table_label( 'Map to Options', '', false );
								$ced_html_tags->print_table_label( 'Custom Value', '', false );
							?>
						</tr>

						<?php
												
						foreach ( $settings_options as $key => $settings_container ) { 					
							foreach ( $settings_container['rows'] as $row_name => $row_data ) { 

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

															
												if ( isset( $row_columns['options'] ) && 'input' == $row_columns['tag'] ) { 

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
																				$_id, $saved_value, $style, $row_columns['options'],
																				$extraAttributes ); 

														}
																	
																
													}

												} else { 

													if ( 'input' == $row_columns['tag'] && 'global_options' == $key ) {
														$name        = 'ced_amazon_general_options[' . $name . '][default]';
														$saved_value = isset( $saved_value['default'] ) ? $saved_value['default'] : '';
													} else {
														$name        = 'ced_amazon_global_settings[' . $name . ']';
														$saved_value = $saved_value;
													}
																
													$checkbox_checked = '';
																
													if ( 'input' == $row_columns['tag'] ) {
														$ced_html_tags->input_tag( $row_columns['attributes']['type'], $name, $class , $_id, $saved_value, $style, 
														$placeholder, $extraAttributes, array(), $checkbox_checked, $parent_tag_open_html, $parent_tag_closing_html
														); 

													} elseif ( 'select' == $row_columns['tag'] ) {

														$ced_html_tags->select_tag(  $name , $class, $_id, $saved_value, $style, $row_columns['options'],
																				$extraAttributes ); 

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
						}
						?>

					</tbody>
				</table>

							
				<div class="wc-actions">
					<?php wp_nonce_field( 'ced_amazon_setting_page_nonce', 'ced_amazon_setting_nonce' ); ?>
					<button type="submit" class="components-button is-secondary general_settings_reset_button" id="rest_global_settings" name="reset_global_settings" ><?php echo esc_html__( 'Reset all values', 'amazon-for-woocommerce' ); ?></button>
					<button style="float: right;" type="submit" name="global_settings" class="components-button is-primary button-next"><?php echo esc_html__( 'Save and continue', 'amazon-for-woocommerce' ); ?></button>
					<?php
						$url = ced_get_navigation_url(
							'amazon',
							array(
								'section'   => 'setup-amazon',
								'part'      => 'configuration',
								'user_id'   => $user_id,
								'seller_id' => $seller_id,
							)
						);

						?>
					<a style="float: right;" data-attr='3' id="ced_amazon_continue_wizard_button" href="<?php echo esc_url( $url ); ?>" class="components-button woocommerce-admin-dismiss-notification"><?php echo esc_html__( 'Skip', 'amazon-for-woocommerce' ); ?></a>
				</div>

			</form>
		</header>

	</div>
</div>
