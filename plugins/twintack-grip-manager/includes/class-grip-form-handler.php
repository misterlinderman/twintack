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
            
            // Trigger webhook for Make.com integration
            $this->trigger_new_grip_design_webhook($grip_id, $order_id, array(
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'team_name' => $team_name,
                'design_type' => $design_type,
                'quantity' => $quantity,
                'artwork_filename' => $artwork_filename,
                'feedback' => $feedback
            ));
            
            error_log('TwinTack Grip Manager: Successfully created grip design post ID ' . $grip_id . ' for order ' . $order_id);
        }
    }
    
    /**
     * Trigger webhook for Make.com when a new grip design is created from purchase
     */
    private function trigger_new_grip_design_webhook($grip_id, $order_id, $grip_data) {
        // Get the webhook URL from the theme configuration
        $webhook_url = apply_filters('grip_customer_feedback_webhook_url', '');
        
        if (empty($webhook_url)) {
            error_log('TwinTack Grip Manager: No webhook URL configured for new grip design notifications');
            return;
        }
        
        // Get additional order data
        $order = wc_get_order($order_id);
        $order_item = null;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->get_sku() === 'grip-design-deposit') {
                $order_item = $item;
                break;
            }
        }
        
        // Prepare comprehensive webhook data with all form fields
        $webhook_data = array(
            // Core grip design info
            'grip_design_id' => $grip_id,
            'grip_design_title' => get_the_title($grip_id),
            'order_id' => $order_id,
            'entry_id' => $grip_data['form_entry_id'] ?? '',
            'entry_date' => current_time('c'),
            'date_updated' => current_time('c'),
            
            // Design specifications
            'design_layout' => $grip_data['design_layout'] ?? '',
            'product_color' => $grip_data['primary_color'] ?? '',
            'second_color' => $grip_data['secondary_color'] ?? '',
            'third_color' => $grip_data['tertiary_color'] ?? '',
            'design_type_full' => $grip_data['design_type'] ?? '',
            
            // Team/Logo information
            'team_name' => $grip_data['team_name'] ?? '',
            'logo_upload' => $grip_data['artwork_url'] ?? '',
            'logo_filename' => $grip_data['artwork_filename'] ?? '',
            'design_instructions' => $grip_data['feedback'] ?? '',
            
            // Quantity and pricing
            'quantity' => intval($grip_data['quantity'] ?? 0),
            'custom_grips_name' => $order_item ? $order_item->get_name() : 'Custom Grip Design Deposit',
            'custom_grips_price' => $order_item ? $order_item->get_total() : 50.00,
            'custom_grips_quantity' => intval($grip_data['quantity'] ?? 0),
            'custom_grips_75_plus_name' => intval($grip_data['quantity'] ?? 0) >= 75 ? 'Custom Grips (75+)' : '',
            'custom_grips_75_plus_price' => intval($grip_data['quantity'] ?? 0) >= 75 ? ($order_item ? $order_item->get_total() : 0) : '',
            'custom_grips_75_plus_quantity' => intval($grip_data['quantity'] ?? 0) >= 75 ? intval($grip_data['quantity'] ?? 0) : '',
            
            // Customer information
            'name_first' => $order ? $order->get_billing_first_name() : '',
            'name_last' => $order ? $order->get_billing_last_name() : '',
            'customer_name' => $grip_data['customer_name'] ?? '',
            'email' => $grip_data['customer_email'] ?? '',
            'phone' => $order ? $order->get_billing_phone() : '',
            
            // Billing address
            'billing_address_street' => $order ? $order->get_billing_address_1() : '',
            'billing_address_line_2' => $order ? $order->get_billing_address_2() : '',
            'billing_address_city' => $order ? $order->get_billing_city() : '',
            'billing_address_state' => $order ? $order->get_billing_state() : '',
            'billing_address_zip' => $order ? $order->get_billing_postcode() : '',
            'billing_address_country' => $order ? $order->get_billing_country() : '',
            
            // Shipping address
            'shipping_address_street' => $order ? $order->get_shipping_address_1() : '',
            'shipping_address_line_2' => $order ? $order->get_shipping_address_2() : '',
            'shipping_address_city' => $order ? $order->get_shipping_city() : '',
            'shipping_address_state' => $order ? $order->get_shipping_state() : '',
            'shipping_address_zip' => $order ? $order->get_shipping_postcode() : '',
            'shipping_address_country' => $order ? $order->get_shipping_country() : '',
            
            // Order details
            'created_by_user_id' => $order ? $order->get_user_id() : 0,
            'source_url' => get_site_url(),
            'transaction_id' => $order ? $order->get_transaction_id() : '',
            'payment_amount' => $order ? $order->get_total() : 0,
            'payment_date' => $order ? $order->get_date_created()->format('c') : current_time('c'),
            'payment_status' => $order ? $order->get_status() : 'processing',
            'post_id' => $grip_id,
            
            // System fields
            'artwork_status' => 'artwork_pending',
            'timestamp' => current_time('c'),
            'webhook_type' => 'new_grip_design',
            'site_url' => get_site_url()
        );
        
        // Allow filtering of webhook data
        $webhook_data = apply_filters('grip_new_design_webhook_data', $webhook_data, $grip_id, $order_id);
        
        // Send webhook
        $response = wp_remote_post($webhook_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'TwinTack-Grip-Manager/1.6.03'
            ),
            'body' => wp_json_encode($webhook_data),
            'timeout' => 15,
            'blocking' => false // Don't wait for response
        ));
        
        // Log the webhook attempt
        if (WP_DEBUG) {
            error_log('TwinTack Grip Manager: Sent new grip design webhook for ID ' . $grip_id . ' to ' . $webhook_url);
            error_log('Webhook data: ' . wp_json_encode($webhook_data));
            
            if (is_wp_error($response)) {
                error_log('Webhook error: ' . $response->get_error_message());
            }
        }
        
        // WordPress action for custom integrations
        do_action('grip_new_design_created', $grip_id, $order_id, $webhook_data);
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
