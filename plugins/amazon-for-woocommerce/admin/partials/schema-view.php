<?php

$product_id = isset( $_GET['product_id'] ) ? sanitize_text_field( $_GET['product_id'] ) : '';
$seller_id  = isset( $_GET['seller_id'] ) ? sanitize_text_field( $_GET['seller_id'] ) : '';

$seller_id_array = explode( '|', $seller_id );
$mplocation      = isset( $seller_id_array[0] ) ? $seller_id_array[0] : '';
$mod_seller_id   = isset( $seller_id_array[1] ) ? $seller_id_array[1] : '';

if ( empty( $product_id ) || empty( $mplocation ) ) {
	echo 'Invalid seller ID';
	return;
}

$upload_dir = wp_upload_dir();
$folderPath = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) . 'ced-amazon/product_upload_schema/' . sanitize_file_name( $mplocation ) );

// Ensure the directory exists
if ( ! is_dir( $folderPath ) ) {
	wp_mkdir_p( $folderPath );
}

// Resolve real paths
$real_folder_path = realpath( $folderPath );
$upload_base_path = realpath( $upload_dir['basedir'] );

// Security check: ensure folder path is valid
if ( ! $real_folder_path || strpos( $real_folder_path, $upload_base_path ) !== 0 ) {
	echo 'Invalid directory path';
	return;
}

// Safely build and normalize file path
$filename       = sanitize_file_name( $product_id ) . '.json';
$sanitized_file = wp_normalize_path( trailingslashit( $real_folder_path ) . $filename );

// Security validations
if ( ! file_exists( $sanitized_file ) ) {
	echo 'File does not exist';
	return;
}

if ( pathinfo( $sanitized_file, PATHINFO_EXTENSION ) !== 'json' ) {
	echo 'Invalid file type';
	return;
}

// Final sanity check - prevent path traversal
if ( strpos( realpath( $sanitized_file ), $real_folder_path ) !== 0 ) {
	echo 'Unauthorized file access';
	return;
}

// Safe read operation
$product_data = file_get_contents( $sanitized_file );
if ( empty( $product_data ) ) {
	echo 'Empty product data';
	return;
}

$data = json_decode( $product_data, true );

$finalStructureData = $data['final_product_structure'] ?? array();
$finalStructureJson = wp_json_encode( $finalStructureData );

$final_product_details = $data['final_product_details'] ?? array();
$final_product_json    = wp_json_encode( $final_product_details );

?>

<body>

	<div class="ced_schema_container" >

	   <div class="ced_schm_left_container" >
			<div class="ced_heading bckg" >Product Final Details
				<span class="ced_final_pro_details" data-schm="<?php echo esc_attr( $final_product_json ); ?>" ></span>
			</div>
			<div class="ced_heading" >Product Final Structure
				<span class="ced_final_pro_schm" data-schm="<?php echo esc_attr( $finalStructureJson ); ?>" ></span>
			</div>
	   </div>
	  
	   <div class="ced_schm_right_container" >

			<h2>Formatted JSON View</h2>
			<!-- Container to hold the rendered JSON -->
			<pre id="json-display"></pre>

		</div>

	</div>
</body>


