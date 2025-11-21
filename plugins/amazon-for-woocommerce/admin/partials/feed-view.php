<?php


if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/** Import the header */
ced_import_header();

$feedId    = isset( $_GET['feed-id'] ) ? sanitize_text_field( $_GET['feed-id'] ) : '';
$user_id   = isset( $_GET['user_id'] ) ? sanitize_text_field( $_GET['user_id'] ) : '';
$seller_id = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';

$seller_id_array = explode( '|', $seller_id );
$country         = isset( $seller_id_array[0] ) ? $seller_id_array[0] : '';
$mod_seller_id   = isset( $seller_id_array[1] ) ? $seller_id_array[1] : '';

$marketplaceid             = ced_get_marketplace_id_by_country( $country );
$amz_currency_code_mapping = get_option( 'ced_amz_currency_code_mapping', array() );

require_once CED_AMAZON_DIRPATH . 'admin/amazon/lib/class-feed-manager.php';

if ( empty( $feedId ) ) {
	echo "<table border='3'><tbody>Feed id not found</tbody></table>";
	return;
}

global $wpdb;
$tableName        = $wpdb->prefix . 'ced_amazon_feeds';
$feed_request_ids = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ced_amazon_feeds WHERE `feed_id` = %d", $feedId ), 'ARRAY_A' );

if ( ! is_array( $feed_request_ids ) || ! is_array( $feed_request_ids[0] ) ) {
	echo "<table border='3'><tbody>Sorry details not found!!</tbody></table>";
	return;
}

$feed_request_id = $feed_request_ids[0];

$main_id     = $feed_request_id['id'];
$feed_action = $feed_request_id['feed_action'];
$location_id = $feed_request_id['feed_location'];

/** Opt type of feed. Manual or Automatic. */
$opt_type = isset( $feed_request_id['opt_type'] ) ? $feed_request_id['opt_type'] : '';

/** Data for eligible products. */
$product_data = isset( $feed_request_id['sku'] ) ? json_decode( $feed_request_id['sku'], true ) : array();

/** Data for ineligible products. */
$error_sku = isset( $feed_request_id['error_sku'] ) ? json_decode( $feed_request_id['error_sku'], true ) : array();

$skumapping = array();
if ( false !== strpos( $feed_action, 'JSON_LISTINGS_FEED_PRODUCT_UPLOAD' ) ) {
	foreach ( $product_data as $key => $product ) {
		$skumapping[ $product['messageId'] ] = $product['product_sku'];
	}
}



function prepare_ineligible_products( $error_sku ) {

	$ineligible_products = array();
	foreach ( $error_sku as $sku ) {
		$product               = wc_get_product( $sku['product_id'] );
		$type                  = $product->get_type();
		$ineligible_products[] = array(
			'product_name' => $product->get_name(),
			'product_sku'  => $sku['product_sku'],
			'product_id'   => $sku['product_id'],
			'type'         => ucfirst($type),

		);
		if ( isset( $sku['children_ids'] ) ) {
			foreach ( $sku['children_ids'] as $child_id ) {
				$child_product         = wc_get_product( $child_id );
				$child_type            = $child_product->get_type();
				$ineligible_products[] = array(
					'product_name' => $child_product->get_name(),
					'product_id'   => $child_id,
					'product_sku'  => $child_product->get_sku(),
					'parent_sku'   => $sku['product_sku'],
					'type'         => ucfirst($child_type),
				);
			}
		}
	}

	return $ineligible_products;
}

if ( 'JSON_LISTINGS_FEED_PRODUCT_UPLOAD' == $feed_action ) {
	
	// Add separator for eligible products
	array_unshift( $product_data, array( 'product_name' => 'Eligible Products') );
	
	// Add ineligible products if they exist
	if ( ! empty( $error_sku ) ) {
		// Add separator for ineligible products
		array_push( $product_data, array( 'product_name' => 'Ineligible Products') );
		
		// Prepare ineligible products data
		$ineligible_products = prepare_ineligible_products( $error_sku );
		
		// Merge ineligible products with main product data
		$product_data = array_merge( $product_data, $ineligible_products );
	}

}

$response = $feed_request_id['response'];
$response = json_decode( $response, true );

// return;

$response_format = false;

$feed_name_array = array(
	
	'POST_ORDER_FULFILLMENT_DATA'      => 'Order Fulfillment',
	'POST_ORDER_ACKNOWLEDGEMENT_DATA'  => 'Order Acknowledgement',
	'JSON_LISTINGS_FEED'               => 'Delete Product',
	'JSON_LISTINGS_FEED_INVENTORY'     => 'Inventory Update',
	'JSON_LISTINGS_FEED_PRICE'         => 'Price Update',
	'JSON_LISTINGS_FEED_IMAGE'         => 'Image Update',
	'JSON_LISTINGS_FEED_RELIST'        => 'Relist Product',

	'JSON_LISTINGS_FEED_PRODUCT_UPLOAD' => 'Product Upload'

);

$feed_name = isset( $feed_name_array[ $feed_action ] ) ? $feed_name_array[ $feed_action ] : '-'; 
$curr_code = $amz_currency_code_mapping[$marketplaceid] ?? '';

if ( ! empty( $feedId ) ) {

	/** Checking whether we have the updated response for feedID or not. */
	if ( isset( $response['status'] ) && 'DONE' == $response['status'] ) {
		$response_format = true;

	} else {

		/** Using a sample log file to store logs */
		$logger  = wc_get_logger();
		$context = array( 'source' => 'ced_amazon_price_sync' );

		$logger->info( wc_print_r( 'feed id is: ' . $feedId, true ), $context );
		$logger->info( wc_print_r( 'feed_action is: ' . $feed_action, true ), $context );
		$logger->info( wc_print_r( 'location id is: ' . $location_id , true ), $context );
		$logger->info( wc_print_r( ' seller_id is: ' . $seller_id , true ), $context );

		if ( ! ( 'Manual' == $opt_type && ( 'Inventory Update' == $feed_name || 'Price Update' == $feed_name ) ) ) {

			/** Getting the updated response for the feedID. */
			$feed_manager = Ced_Umb_Amazon_Feed_Manager::get_instance(  );
			$response     = $feed_manager->getFeedItemsStatusSpApi( $feedId, $feed_action, $location_id, $seller_id );

			if ( isset( $response['status'] ) && 'DONE' == $response['status'] ) {
				$response_format = true;
			}

		}
		
	}


	if (  'Manual' == $opt_type && ( 'Inventory Update' == $feed_name || 'Price Update' == $feed_name ) ) {

		ced_prepare_feed_info_container( $feedId, $feed_name );

		ced_prepare_feed_product_table( $product_data, $curr_code, $feed_name );
		return;
	}
	
	if ( $response_format ) {			

		if ( isset( $response['feed_id'] ) && ! empty( $response['feed_id'] ) ) {
			ced_prepare_feed_info_container( $response['feed_id'], $feed_name );
		}

		
		if ( 'POST_FLAT_FILE_LISTINGS_DATA' == $feed_action ) {

			$tab_response_data = explode( "\n", $response['body'] );

			$first_row_data         = explode( "\t", $tab_response_data[0] );
			$second_row_data        = explode( "\t", $tab_response_data[1] );
			$third_row_data         = explode( "\t", $tab_response_data[2] );
			$response_heading       = isset( $first_row_data[0] ) ? $first_row_data[0] : '';
			$processed_record_lable = isset( $second_row_data[1] ) ? $second_row_data[1] : '';
			$processed_record_value = isset( $second_row_data[3] ) ? $second_row_data[3] : '';
			$success_record_lable   = isset( $third_row_data[1] ) ? $third_row_data[1] : '';
			$success_record_value   = isset( $third_row_data[3] ) ? $third_row_data[3] : '';

			$tab_response_html = '';
			foreach ( $tab_response_data as $tabKey => $tabValue ) {

				$line_data = explode( "\t", $tabValue );
				if ( 'Feed Processing Summary' == $line_data[0] || 'Feed Processing Summary:' == $line_data[0] ) {
					continue;
				} elseif ( empty( $line_data[0] ) || '' == $line_data[0] ) {
					continue;
				} elseif ( 'original-record-number' == $line_data[0] ) {
					continue;
				} else {
					$tab_response_html .= '<tr><td >' . esc_html__( $line_data[0], 'amazon-for-woocommerce' ) . '</td>';
					$tab_response_html .= '<td>' . esc_html__( $line_data[1], 'amazon-for-woocommerce' ) . '</td>';
					$tab_response_html .= '<td>' . esc_html__( $line_data[2], 'amazon-for-woocommerce' ) . '</td>';
					$tab_response_html .= '<td>' . esc_html__( $line_data[3], 'amazon-for-woocommerce' ) . '</td>';
					$tab_response_html .= '<td >' . esc_html__( $line_data[4], 'amazon-for-woocommerce' ) . '</td></tr>';
				}
			}

			$tableHtml = '<div class="ced-feed-summary-container" > <h3 class="ced_feed_product_table_heading" >Feed Summary Table: </h3> 
			<div class="ced-feed-summary" >
			<table class="wp-list-table widefat striped table-view-list posts ced_feed_processing_summary_table" >
				<thead class="table-dark">
					<tr>
						<th scope="col" colspan="5" style="text-align: center;" >' . esc_html__( $response_heading, 'amazon-for-woocommerce' ) . '</th>
					</tr>
					<tr>
						<th scope="col">' . esc_html__( $processed_record_lable, 'amazon-for-woocommerce' ) . '</th>
						<th scope="col" colspan="4">' . esc_html__( $processed_record_value, 'amazon-for-woocommerce' ) . '</th>
					</tr>
					<tr>
						<th scope="col">' . esc_html__( $success_record_lable, 'amazon-for-woocommerce' ) . '</th>
						<th scope="col" colspan="4">' . esc_html__( $success_record_value, 'amazon-for-woocommerce' ) . '</th>
					</tr>
					<tr>
						<th scope="col">' . esc_html__( 'Original record number', 'amazon-for-woocommerce' ) . '</th>
						<th scope="col">' . esc_html__( 'SKU', 'amazon-for-woocommerce' ) . '</th>
						<th scope="col">' . esc_html__( 'Error code', 'amazon-for-woocommerce' ) . '</th>
						<th scope="col">' . esc_html__( 'Error type', 'amazon-for-woocommerce' ) . '</th>
						<th scope="col">' . esc_html__( 'Error message', 'amazon-for-woocommerce' ) . '</th>
					</tr>
				</thead>
				<tbody>';

			$tableHtml .= $tab_response_html;
			$tableHtml .= '</tbody>
	        </table>
			</div>
			</div>
			</div>';

			print_r( $tableHtml );

		} elseif ( strpos( $feed_action, 'JSON_LISTINGS_FEED' ) === 0 ) {

			$feed_response = json_decode( $response['body'], true );
			
			ced_prepare_feed_product_table( $product_data, $curr_code, $feed_name, $opt_type );

			if ( isset( $feed_response ) && ! empty( $feed_response ) ) {

				$header_data      = isset( $feed_response['header'] ) ? $feed_response['header'] : array();
				$header_data_html = '';
				if ( ! empty( $header_data ) ) {
					foreach ( $header_data as $header_label => $header_fields ) {
						$header_data_html .= $header_label . ' : ' . $header_fields . '<br/>';
					}
				}

				$summary_data      = isset( $feed_response['summary'] ) ? $feed_response['summary'] : array();
				$summary_data_html = '';
				if ( ! empty( $summary_data ) ) {
					foreach ( $summary_data as $summary_label => $summary_fields ) {
						$summary_data_html .= $summary_label . ' : ' . $summary_fields . '<br/>';
					}
				} 

				$issues = isset( $feed_response['issues'] ) ? $feed_response['issues'] : array();
				
				$error_html = '';
				if ( ! empty( $issues ) ) {
					foreach ( $issues as $error_label => $error_fields ) {
						
						$message_id = isset( $error_fields['messageId'] ) ? $error_fields['messageId'] : '';
						
						$sku      = $skumapping[ $message_id ] ?? '';
						$code     = isset( $error_fields['code'] ) ? $error_fields['code'] : '';
						$severity = isset( $error_fields['severity'] ) ? $error_fields['severity'] : '';
						$message  = isset( $error_fields['message'] ) ? $error_fields['message'] : '';

						$error_html .= '<p><strong>' . esc_html__( 'Message Id: ', 'amazon-for-woocommerce' ) . '</strong>' . esc_html__( $message_id ) . '</p>';
						$error_html .= '<p><strong>' . esc_html__( 'SKU: ', 'amazon-for-woocommerce' ) . '</strong>' . esc_html__( $sku ) . '</p>';
						$error_html .= '<p><strong>' . esc_html__( 'Code: ', 'amazon-for-woocommerce' ) . '</strong>' . esc_html__( $code ) . '</p>';
						$error_html .= '<p><strong>' . esc_html__( 'Severity: ', 'amazon-for-woocommerce' ) . '</strong> ' . esc_html__( $severity ) . '</p>';
						$error_html .= '<p><strong>' . esc_html__( 'Message: ', 'amazon-for-woocommerce' ) . '</strong>' . esc_html__( $message ) . '<p/><hr/><br/>';
					}
				}

				$tableHtml  = '<div class="ced-feed-summary-container" > <h3 class="ced_feed_product_table_heading" >Feed Summary Table: </h3> 
				<div class="ced-feed-summary" >
				<table class="wp-list-table widefat striped table-view-list posts ced_feed_processing_summary_table" >
						<thead class="table-dark">
							<tr>
								<th scope="col">' . esc_html__( 'Header', 'amazon-for-woocommerce' ) . '</th>
								<th scope="col">' . esc_html__( 'Summary', 'amazon-for-woocommerce' ) . '</th>
								<th scope="col">' . esc_html__( 'Issues', 'amazon-for-woocommerce' ) . '</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>';
				$tableHtml .= $header_data_html;
				$tableHtml .= '</td>
							<td>';
				$tableHtml .= $summary_data_html;
				$tableHtml .= '</td>
							<td style="width: 30rem;">';
				$tableHtml .= $error_html;
				$tableHtml .= '</td>
						</tr>
						
					</tbody>
		        </table></div></div></div>';

				print_r( $tableHtml );


				if ( 'JSON_LISTINGS_FEED_PRODUCT_UPLOAD' == $feed_action ) {

					$skumapping = array();
					if ( !empty( $product_data ) ) {
						
						foreach ( $product_data as $key => $product ) {
							if ( isset($product['product_name']) && ( 'Eligible Products' == $product['product_name'] ||   'Ineligible Products' == $product['product_name'] )) {
								continue;
							}
							$skumapping[ $product['messageId'] ] = $product['product_sku'];
						}
					}

					/** Code to loop through the response and update the validation errors */ 
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

						if (!empty($validation_errors)) {
							foreach ($validation_errors as $message_id => $error_messages) {
								if (isset($skumapping[$message_id])) {
									$product_id = wc_get_product_id_by_sku( $skumapping[$message_id] );
									$arr        = array( 'amazon_validation' => $error_messages );
									update_post_meta( $product_id, 'ced_amz_json_validator_error_' . $seller_id , $arr );

								} else {
									$logger->info( wc_print_r( '------------------------- SKU NOT FOUND FOR MESSAGE ID ' . $message_id . ' ----------------------------', true ), $context );
								}
							}
						}

					}

				} 

			} else {
				echo 'Empty feed response';
			}

		} else {

			$sxml          = simplexml_load_string( $response['body'], 'SimpleXMLElement', LIBXML_NOCDATA );
			$arrayResponse = xml2array_convert( $sxml );

			ced_prepare_feed_product_table( $product_data, $curr_code, $feed_name, $opt_type );
			
			if ( isset( $arrayResponse['Message'] ) && ! empty( $arrayResponse['Message'] ) ) {

				$processingSummary     = isset( $arrayResponse['Message'] ) && isset( $arrayResponse['Message']['ProcessingReport'] ) && isset( $arrayResponse['Message']['ProcessingReport']['ProcessingSummary'] ) ? $arrayResponse['Message']['ProcessingReport']['ProcessingSummary'] : array();
				$processingSummaryHtml = '';

				$results     = isset( $arrayResponse['Message']['ProcessingReport']['Result'][0] ) ? $arrayResponse['Message']['ProcessingReport']['Result'] : $arrayResponse['Message']['ProcessingReport'];
				$resultsHtml = '';

				if ( ! empty( $processingSummary ) ) {
					foreach ( $processingSummary as $label => $fields ) {
						$processingSummaryHtml .= $label . ' : ' . $fields . '<br/>';
					}
				}

				if ( ! empty( $results ) ) {

					foreach ( $results as $label => $fields ) {

						if ( 'Result' == $label || is_numeric( $label ) ) {
							if ( is_object( $fields ) ) {
								$fields = xml2array_convert( $fields );
							}

							$resultCode        = isset( $fields['ResultCode'] ) ? $fields['ResultCode'] : '';
							$resultMessageCode = isset( $fields['ResultMessageCode'] ) ? $fields['ResultMessageCode'] : '';
							$resultDescription = isset( $fields['ResultDescription'] ) ? $fields['ResultDescription'] : '';
							$sku               = isset( $fields['AdditionalInfo'] ) && isset( $fields['AdditionalInfo']['SKU'] ) ? $fields['AdditionalInfo']['SKU'] : '';

							$resultsHtml .= '<p> <strong>Result code : </strong>' . esc_attr( $resultCode ) . '</p>';
							$resultsHtml .= '<p><strong> Result Message Code : </strong>' . esc_attr( $resultMessageCode ) . '</p>';
							$resultsHtml .= '<p> <strong>Result Description : </strong> ' . esc_attr( $resultDescription ) . '</p>';
							$resultsHtml .= '<p> <strong> Sku : </strong>' . esc_attr( $sku ) . '</p></hr><br/>';
						}
					}
				}

				$tableHtml = ' <div class="ced-feed-summary-container" > <h3 class="ced_feed_product_table_heading" >Feed Summary Table: </h3>
				<div class="ced-feed-summary" >
				<table class="wp-list-table widefat striped table-view-list posts ced_feed_processing_summary_table"  >
						<thead class="table-dark">
							<tr>
								<th scope="col">Merchant Identifier </th>
								<th scope="col">Message Type</th>
								<th scope="col">Status Code</th>
								<th scope="col">ProcessingSummary</th>
								<th scope="col">Results</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<th scope="row">' . esc_attr( $arrayResponse['Header']['MerchantIdentifier'] ) . '</th>
								<td>' . esc_attr( $arrayResponse['MessageType'] ) . '</td>
								<td>' . esc_attr( $arrayResponse['Message']['ProcessingReport']['StatusCode'] ) . '</td>
								<td>';

					$tableHtml .= $processingSummaryHtml;
					$tableHtml .= '</td>
								<td style="width: 30rem;">';
					$tableHtml .= $resultsHtml;
					$tableHtml .= '</td>
							</tr>
							
						</tbody>
			        </table></div></div></div>';

				print_r( $tableHtml );
			}
		}

	} elseif ( isset( $response['feed_id'] ) && ! empty( $response['feed_id'] ) ) {
		
		ced_prepare_feed_info_container( $response['feed_id'], $feed_name );
		ced_prepare_feed_product_table( $product_data, $curr_code, $feed_name, $opt_type );

		echo ' <div class="ced-feed-summary-container" > <h3 class="ced_feed_product_table_heading" >Feed Summary Table: </h3> 
		<div class="ced-feed-summary" >
		<table class="wp-list-table widefat striped table-view-list posts ced_feed_processing_summary_table" >
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
		</table></div> </div></div>';

	} else {
		$message = isset( $response['body'] ) ? $response['body'] : $response['message'];
		echo '<div class=""><p><strong>' . esc_attr( $message ) . '</strong></p></div>';
	}
}


function xml2array_convert( $xmlObject, $out = array() ) {
	foreach ( (array) $xmlObject as $index => $node ) {
		$out[ $index ] = ( is_object( $node ) ) ? xml2array_convert( $node ) : $node;
	}

	return $out;
}



function ced_prepare_feed_info_container( $feedId, $feed_name ) {
	echo '<div class="ced_feed_info_container">';
	echo '<div><p><strong class="ced_frm_feed" >Feed Id</strong><span class="ced_feed_colon">:</span><span>' . esc_attr( $feedId ) . '</span></p>';
	echo '<p><strong class="ced_frm_feed" >Feed Action</strong><span class="ced_feed_colon">:</span><span>' . esc_attr(  $feed_name ) . '</span></p>';
	echo '<p><strong class="ced_frm_feed" >Feed Type</strong><span class="ced_feed_colon">:</span><span>JSON</span></p>';
	echo '</div>';
}

function ced_prepare_feed_product_table( $product_data, $curr_code, $feed_name, $opt_type = 'Manual' ) {

	if ( 'Order Fulfillment' !== $feed_name && 'Order Acknowledgement' !== $feed_name ) {

		// Heading for Products related Feeds
		$headers = array( 'Product Name', 'SKU', 'Type', 'Parent SKU','Data Transmitted' );

		if (  'Manual' == $opt_type && ( 'Inventory Update' == $feed_name || 'Price Update' == $feed_name ) ) {
			$headers[] = 'Status';
			$style     = '';
			$rotated   = 'rotated';
		} else {
			$style   = 'display: none;';
			$rotated = '';
		}

	} else {
		// Heading for Order related feeds
		$headers = array( 'Order No.', 'Items Count', 'Total Quantity', 'Current Status', 'Data Transmitted' );
	}

	?>
		<div class="ced-feed-accordian" >
			<h3 class="ced_feed_product_table_heading" style="cursor: pointer;" >
				<span class="arrow-icon <?php echo esc_attr($rotated); ?>" style="display: inline-block; transition: transform 0.3s;">&#9654;</span>
				Feed Product Table: </h3>

			<div class="accordion-content" style="<?php echo esc_attr($style); ?>" >
				<table class="wp-list-table widefat striped table-view-list posts ced_feed_product_table">
					<thead class="table-dark">
						<tr>
							<?php 
							foreach ( $headers as $headings ) {
								?>
								<th scope="col"><?php echo esc_attr( $headings ); ?></th>
								<?php 
							}  
							?>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $product_data as $key => $pro_details ) {

							
							if ( 'Order Fulfillment' !== $feed_name && 'Order Acknowledgement' !== $feed_name ) {

								if ( isset( $pro_details['product_name'] ) && 'Eligible Products' == $pro_details['product_name'] ) {
									?>
										<tr ><td colspan="5" ><strong style=" font-size: 16px; font-weight: bold; " >Eligible Products</strong></td></tr>
									<?php continue; } elseif ( isset( $pro_details['product_name'] ) &&  'Ineligible Products' == $pro_details['product_name'] ) { ?>
										<tr ><td colspan="5"><strong style=" font-size: 16px; font-weight: bold; " >Ineligible Products</strong></td></tr>
									<?php
									continue; }

									$name = '';
									if ( empty( $pro_details['product_name'] ) && !empty($key) ) {
										$pro = wc_get_product_id_by_sku( $key );
										if ( $pro ) {
											$name = get_the_title( $pro );
										}
									}
									$val1 = $pro_details['product_name'] ?? $name;
									$val2 = $pro_details['product_sku'] ?? $key;
									$val3 = $pro_details['type'] ?? '';
									$val4 = $pro_details['parent_sku'] ?? '';
									$val5 = $pro_details['value'] ?? array();
									$val6 = $pro_details['status'] ?? '';

							} else {
								$url  = $pro_details['url'] ?? ''; 
								$val1 = '<a href="' . $url . '" >' . $key . ' (' . $pro_details['woo_order_id'] . ') </a>';
								$val2 = $pro_details['items_count'] ?? '';
								$val3 = $pro_details['total_quantity'] ?? '';
								$val4 = ucfirst( $pro_details['current_status'] ) ?? '';
								$val5 = $pro_details['value'] ?? array();
								$val6 = $pro_details['status'] ?? '';
							}

							?>
									<tr>
									<?php
									for ( $i=1; $i <= count($headers); $i++ ) {
										if ( 1 == $i ) {
											?>
													<th scope="row"><?php esc_html_e( $val1 ); ?></th> 
												<?php
										} elseif ( 1 < $i && 5 !== $i ) { 
											$varName = 'val' . $i;
											?>
													<td scope="col"><?php esc_html_e($$varName); ?></td>
												<?php
										} elseif ( 5 == $i ) {
					
											$val               = '';
											$value_transmitted = $pro_details['value'] ?? '';

											if ( is_string( $value_transmitted ) ) {
												$val = $value_transmitted;
											} elseif ( is_numeric( $value_transmitted ) ) {
												$val = $value_transmitted;
											} elseif ( is_array( $value_transmitted ) ) {
												if ( !empty($value_transmitted) ) { 
													foreach ( $value_transmitted as $k => $v ) {
														if ( 'price' == $k ) {
															$v = $v . ' ' . $curr_code;
														}
														if ( 'array' == gettype( $v ) ) {
															continue;
														}
														$val .= '<p>' . ucfirst($k) . ': ' . $v . '</p>';
													}
												}
																		
											}
											?>
														<td scope="col"><?php echo wp_kses_post( $val ); ?></td>
												<?php

										}
													
									}
									?>
									</tr>	
								<?php

						}

						?>
							
						</tr>
					</tbody>
				</table>
			</div>
		</div>

	<?php
}

?>
