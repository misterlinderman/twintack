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
        
        // Handle order completion - create grip design post only when purchase is completed
        add_action('woocommerce_order_status_completed', array($this, 'process_completed_order'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this, 'process_completed_order'), 10, 1);
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
            
            // Get current user email (this ensures the grip design is associated with the logged-in user)
            $current_user = wp_get_current_user();
            $customer_email = $current_user->user_email;
            
            // Store grip design data temporarily - DO NOT create post yet
            $grip_data = array(
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'team_name' => $team_name,
                'design_type' => rgar($entry, '12'),
                'quantity' => rgar($entry, '21'),
                'artwork_url' => $file_upload,
                'artwork_filename' => $file_name,
                'feedback' => $feedback,
                'form_entry_id' => $entry['id'],
                'form_type' => 'original',
                'timestamp' => current_time('timestamp')
            );

            // Add to cart with grip design data
            $product_id = $this->get_deposit_product_id();
            WC()->cart->add_to_cart($product_id, 1, 0, array(), array(
                'grip_design_data' => $grip_data
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
            
            // Get current user email (this ensures the grip design is associated with the logged-in user)
            $current_user = wp_get_current_user();
            $customer_email = $current_user->user_email;
            
            // Store grip design data temporarily - DO NOT create post yet
            $grip_data = array(
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
                'feedback' => $feedback,
                'form_entry_id' => $entry['id'],
                'form_type' => 'new',
                'timestamp' => current_time('timestamp')
            );

            // Add to cart with grip design data
            $product_id = $this->get_deposit_product_id();
            WC()->cart->add_to_cart($product_id, 1, 0, array(), array(
                'grip_design_data' => $grip_data
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
            
            // Save all grip design data as hidden meta (with proper prefixes)
            foreach ($data as $key => $value) {
                $item->add_meta_data("_grip_{$key}", $value, true);
            }
            
            // Add visible meta data for customer/admin view
            $item->add_meta_data('Customer', $data['customer_name'], true);
            $item->add_meta_data('Team/School', $data['team_name'], true);
            $item->add_meta_data('Design Type', $data['design_type'], true);
            $item->add_meta_data('Quantity', $data['quantity'], true);
            if (!empty($data['artwork_filename'])) {
                $item->add_meta_data('Artwork File', $data['artwork_filename'], true);
            }
            if (!empty($data['feedback'])) {
                $item->add_meta_data('Design Instructions', $data['feedback'], true);
            }
        }
    }
    
    /**
     * Process completed order and create grip design posts only for Custom Grip Design Deposit purchases
     */
    public function process_completed_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('TwinTack Grip Manager: Invalid order ID ' . $order_id);
            return;
        }
        
        error_log('TwinTack Grip Manager: Processing completed order ' . $order_id);
        
        foreach ($order->get_items() as $item_id => $item) {
            // Check if this is a Custom Grip Design Deposit purchase
            $product = $item->get_product();
            if (!$product || $product->get_sku() !== 'grip-design-deposit') {
                continue;
            }
            
            error_log('TwinTack Grip Manager: Found Custom Grip Design Deposit in order ' . $order_id . ', item ' . $item_id);
            
            // Check if we already created a grip design for this order item
            $existing_grip_id = $item->get_meta('_grip_design_id');
            if ($existing_grip_id) {
                error_log('TwinTack Grip Manager: Grip design already exists for item ' . $item_id . ' (ID: ' . $existing_grip_id . ')');
                continue; // Already processed
            }
            
            // Get the grip design data from the order item
            $customer_name = $item->get_meta('_grip_customer_name');
            $customer_email = $item->get_meta('_grip_customer_email');
            $team_name = $item->get_meta('_grip_team_name');
            $design_type = $item->get_meta('_grip_design_type');
            $quantity = $item->get_meta('_grip_quantity');
            $artwork_url = $item->get_meta('_grip_artwork_url');
            $artwork_filename = $item->get_meta('_grip_artwork_filename');
            $feedback = $item->get_meta('_grip_feedback');
            $form_entry_id = $item->get_meta('_grip_form_entry_id');
            $form_type = $item->get_meta('_grip_form_type');
            
            // Validate required data
            if (empty($customer_name) || empty($team_name)) {
                error_log('TwinTack Grip Manager: Missing required grip data for order ' . $order_id . ', item ' . $item_id);
                $order->add_order_note('ERROR: Missing grip design data. Could not create grip design post.');
                continue;
            }
            
            // Generate date suffix (YYMMDD)
            $date_suffix = current_time('ymd');
            
            // Create post title
            $post_title = sprintf('Custom Grip - %s %s', $team_name, $date_suffix);
            
            // Prepare meta data based on form type
            $meta_input = array(
                '_grip_customer_name' => $customer_name,
                '_grip_customer_email' => $customer_email,
                '_grip_team_name' => $team_name,
                '_grip_design_type' => $design_type,
                '_grip_quantity' => $quantity,
                '_grip_form_entry_id' => $form_entry_id,
                '_grip_artwork_url' => $artwork_url,
                '_grip_artwork_filename' => $artwork_filename,
                '_grip_feedback' => $feedback,
                '_grip_artwork_status' => 'artwork_pending',
                '_grip_order_id' => $order_id,
                '_grip_order_item_id' => $item_id
            );
            
            // Add additional meta for new form type
            if ($form_type === 'new') {
                $meta_input['_grip_design_layout'] = $item->get_meta('_grip_design_layout');
                $meta_input['_grip_primary_color'] = $item->get_meta('_grip_primary_color');
                $meta_input['_grip_secondary_color'] = $item->get_meta('_grip_secondary_color');
                $meta_input['_grip_tertiary_color'] = $item->get_meta('_grip_tertiary_color');
            }
            
            // Create grip design post NOW that payment is completed
            $grip_id = wp_insert_post(array(
                'post_type' => 'grip_design',
                'post_title' => $post_title,
                'post_content' => $feedback,
                'post_status' => 'publish',
                'post_author' => $order->get_user_id() ?: 1, // Use order user or admin
                'meta_input' => $meta_input
            ));

            if (is_wp_error($grip_id)) {
                error_log('TwinTack Grip Manager: Error creating grip design post for order ' . $order_id . ': ' . $grip_id->get_error_message());
                $order->add_order_note('ERROR: Failed to create grip design post - ' . $grip_id->get_error_message());
                continue;
            }
            
            // Link the grip design post to the order item
            $item->add_meta_data('_grip_design_id', $grip_id, true);
            $item->save_meta_data();
            
            // Add order note
            $order->add_order_note(
                sprintf('Grip design post created: %s (ID: %d)', $post_title, $grip_id)
            );
            
            error_log('TwinTack Grip Manager: Successfully created grip design post ID ' . $grip_id . ' for order ' . $order_id);
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
    
    /**
     * Admin utility: Clean up grip design posts that were created without a completed purchase
     * This can be useful for posts created before implementing the purchase-based creation
     */
    public function cleanup_orphaned_grip_designs() {
        if (!current_user_can('administrator')) {
            return false;
        }
        
        $grip_posts = get_posts(array(
            'post_type' => 'grip_design',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_grip_order_id',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
        
        $cleaned = 0;
        foreach ($grip_posts as $post) {
            // Check if there's a completed order with this grip data
            $customer_email = get_post_meta($post->ID, '_grip_customer_email', true);
            $team_name = get_post_meta($post->ID, '_grip_team_name', true);
            
            // Look for orders containing grip deposits for this customer/team
            $orders = wc_get_orders(array(
                'billing_email' => $customer_email,
                'status' => array('completed', 'processing'),
                'limit' => -1
            ));
            
            $found_matching_order = false;
            foreach ($orders as $order) {
                foreach ($order->get_items() as $item) {
                    $product = $item->get_product();
                    if ($product && $product->get_sku() === 'grip-design-deposit') {
                        $order_team = $item->get_meta('_grip_team_name');
                        if ($order_team === $team_name) {
                            $found_matching_order = true;
                            // Link this grip design to the order
                            update_post_meta($post->ID, '_grip_order_id', $order->get_id());
                            $item->add_meta_data('_grip_design_id', $post->ID, true);
                            $item->save_meta_data();
                            break 2;
                        }
                    }
                }
            }
            
            if (!$found_matching_order) {
                // No matching completed order found, consider this orphaned
                wp_delete_post($post->ID, true);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
}
