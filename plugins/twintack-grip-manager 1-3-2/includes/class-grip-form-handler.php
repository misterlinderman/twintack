<?php
class TwinTack_Grip_Form_Handler {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Handle Gravity Form submission
        add_action('gform_after_submission_8', array($this, 'process_grip_form'), 10, 2);
        add_action('gform_after_submission_9', array($this, 'process_new_grip_form'), 10, 2);
        
        // Add to cart handling
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_grip_data_to_cart'), 10, 3);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_custom_data'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_grip_data_to_order'), 10, 4);
    }
    
    public function process_grip_form($entry, $form) {
        try {
            // Get the file upload field (field ID 9)
            $file_upload = rgar($entry, '9');
            $file_name = !empty($file_upload) ? basename($file_upload) : '';
            
            // Get the feedback field (field ID 14)
            $feedback = rgar($entry, '14');
            
            // Get customer name from name field parts (1.3 for first name, 1.6 for last name)
            $first_name = rgar($entry, '1.3');
            $last_name = rgar($entry, '1.6');
            $customer_name = trim($first_name . ' ' . $last_name);
            
            // Get team name (field ID 8)
            $team_name = rgar($entry, '8');
            
            // Generate date suffix (YYMMDD)
            $date_suffix = current_time('ymd');
            
            // Create post title
            $post_title = sprintf('Custom Grip - %s %s', $team_name, $date_suffix);
            
            // Get current user email (this ensures the grip design is associated with the logged-in user)
            $current_user = wp_get_current_user();
            $customer_email = $current_user->user_email;
            
            // Create grip design post
            $grip_id = wp_insert_post(array(
                'post_type' => 'grip_design',
                'post_title' => $post_title,
                'post_content' => $feedback,
                'post_status' => 'artwork_pending',
                'meta_input' => array(
                    '_grip_customer_name' => $customer_name,
                    '_grip_customer_email' => $customer_email,
                    '_grip_team_name' => $team_name,
                    '_grip_design_type' => rgar($entry, '12'),
                    '_grip_quantity' => rgar($entry, '21'),
                    '_grip_form_entry_id' => $entry['id'],
                    '_grip_artwork_url' => $file_upload,
                    '_grip_artwork_filename' => $file_name,
                    '_grip_feedback' => $feedback
                )
            ));

            if (is_wp_error($grip_id)) {
                throw new Exception($grip_id->get_error_message());
            }

            // Add to cart with enhanced metadata
            $product_id = $this->get_deposit_product_id();
            WC()->cart->add_to_cart($product_id, 1, 0, array(), array(
                'grip_design_data' => array(
                    'grip_id' => $grip_id,
                    'customer_name' => $customer_name,
                    'customer_email' => $customer_email,
                    'team_name' => $team_name,
                    'design_type' => rgar($entry, '12'),
                    'quantity' => rgar($entry, '21'),
                    'artwork_url' => $file_upload,
                    'artwork_filename' => $file_name,
                    'feedback' => $feedback
                )
            ));

            wp_redirect(wc_get_cart_url());
            exit;
        } catch (Exception $e) {
            error_log('Grip Form Processing Error: ' . $e->getMessage());
        }
    }
    
    public function process_new_grip_form($entry, $form) {
        try {
            // Get the file upload field (field ID 9)
            $file_upload = rgar($entry, '9');
            $file_name = !empty($file_upload) ? basename($file_upload) : '';
            
            // Get the feedback/design instructions field (field ID 14)
            $feedback = rgar($entry, '14');
            
            // Get customer name from name field parts (1.3 for first name, 1.6 for last name)
            $first_name = rgar($entry, '1.3');
            $last_name = rgar($entry, '1.6');
            $customer_name = trim($first_name . ' ' . $last_name);
            
            // Get team name (field ID 8)
            $team_name = rgar($entry, '8');
            
            // Generate date suffix (YYMMDD)
            $date_suffix = current_time('ymd');
            
            // Get new form specific color/pattern data
            $design_layout = rgar($entry, '12'); // Which design layout (Solid Color, 2-Color Fade, 3-Color Fade, etc)
            $primary_color = rgar($entry, '41'); // Primary/Product Color
            $secondary_color = rgar($entry, '42'); // Second Color (conditional)
            $tertiary_color = rgar($entry, '43'); // Third Color (conditional)
            
            // Create complete design type string
            $design_type = $design_layout;
            if ($design_layout != 'Solid Color') {
                $colors = $primary_color;
                if (!empty($secondary_color)) {
                    $colors .= " + " . $secondary_color;
                }
                if (!empty($tertiary_color)) {
                    $colors .= " + " . $tertiary_color;
                }
                $design_type .= " (" . $colors . ")";
            } else {
                $design_type .= " (" . $primary_color . ")";
            }
            
            // Create post title
            $post_title = sprintf('Custom Grip - %s %s', $team_name, $date_suffix);
            
            // Get current user email (this ensures the grip design is associated with the logged-in user)
            $current_user = wp_get_current_user();
            $customer_email = $current_user->user_email;
            
            // Create grip design post
            $grip_id = wp_insert_post(array(
                'post_type' => 'grip_design',
                'post_title' => $post_title,
                'post_content' => $feedback,
                'post_status' => 'artwork_pending',
                'meta_input' => array(
                    '_grip_customer_name' => $customer_name,
                    '_grip_customer_email' => $customer_email,
                    '_grip_team_name' => $team_name,
                    '_grip_design_type' => $design_type,
                    '_grip_design_layout' => $design_layout,
                    '_grip_primary_color' => $primary_color,
                    '_grip_secondary_color' => $secondary_color,
                    '_grip_tertiary_color' => $tertiary_color,
                    '_grip_quantity' => rgar($entry, '21'),
                    '_grip_form_entry_id' => $entry['id'],
                    '_grip_artwork_url' => $file_upload,
                    '_grip_artwork_filename' => $file_name,
                    '_grip_feedback' => $feedback
                )
            ));

            if (is_wp_error($grip_id)) {
                throw new Exception($grip_id->get_error_message());
            }

            // Add to cart with enhanced metadata
            $product_id = $this->get_deposit_product_id();
            WC()->cart->add_to_cart($product_id, 1, 0, array(), array(
                'grip_design_data' => array(
                    'grip_id' => $grip_id,
                    'customer_name' => $customer_name,
                    'customer_email' => $customer_email,
                    'team_name' => $team_name,
                    'design_type' => $design_type,
                    'design_layout' => $design_layout,
                    'primary_color' => $primary_color,
                    'secondary_color' => $secondary_color,
                    'tertiary_color' => $tertiary_color,
                    'quantity' => rgar($entry, '21'),
                    'artwork_url' => $file_upload,
                    'artwork_filename' => $file_name,
                    'feedback' => $feedback
                )
            ));

            wp_redirect(wc_get_cart_url());
            exit;
        } catch (Exception $e) {
            error_log('New Grip Form Processing Error: ' . $e->getMessage());
        }
    }

    public function add_grip_data_to_cart($cart_item_data, $product_id, $variation_id) {
        if (isset($cart_item_data['grip_design_data'])) {
            // Ensure each cart item is unique
            $cart_item_data['unique_key'] = md5(microtime() . rand());
        }
        return $cart_item_data;
    }

    public function display_cart_item_custom_data($item_data, $cart_item) {
        if (isset($cart_item['grip_design_data'])) {
            $data = $cart_item['grip_design_data'];
            
            $item_data[] = array(
                'key' => 'Customer',
                'value' => $data['customer_name']
            );
            
            $item_data[] = array(
                'key' => 'Team/School',
                'value' => $data['team_name']
            );
            
            $item_data[] = array(
                'key' => 'Design Type',
                'value' => $data['design_type']
            );
            
            $item_data[] = array(
                'key' => 'Quantity',
                'value' => $data['quantity']
            );

            if (!empty($data['artwork_filename'])) {
                $item_data[] = array(
                    'key' => 'Artwork File',
                    'value' => $data['artwork_filename']
                );
            }

            if (!empty($data['feedback'])) {
                $item_data[] = array(
                    'key' => 'Design Instructions',
                    'value' => $data['feedback']
                );
            }
        }
        return $item_data;
    }

    public function save_grip_data_to_order($item, $cart_item_key, $values, $order) {
        if (isset($values['grip_design_data'])) {
            $data = $values['grip_design_data'];
            
            // Save all grip design data as hidden meta
            foreach ($data as $key => $value) {
                $item->add_meta_data("_grip_{$key}", $value, true);
            }
            
            // Add visible meta data
            $item->add_meta_data('Customer', $data['customer_name'], true);
            $item->add_meta_data('Team/School', $data['team_name'], true);
            $item->add_meta_data('Design Type', $data['design_type'], true);
            $item->add_meta_data('Quantity', $data['quantity'], true);
            $item->add_meta_data('Artwork File', $data['artwork_filename'], true);
            $item->add_meta_data('Design Instructions', $data['feedback'], true);
            
            // Link to grip design post
            $item->add_meta_data('_grip_design_id', $data['grip_id'], true);
        }
    }
    
    private function get_deposit_product_id() {
        // Try to get existing product by SKU
        $product_id = wc_get_product_id_by_sku('grip-design-deposit');
        
        if (!$product_id) {
            // Create new product
            $product = new WC_Product_Simple();
            $product->set_name('Custom Grip Design Deposit');
            $product->set_regular_price('50.00');
            $product->set_sku('grip-design-deposit');
            $product->set_virtual(true);
            $product->set_sold_individually(true);
            $product->set_status('publish');
            $product_id = $product->save();
        }
        
        return $product_id;
    }
}
