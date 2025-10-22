<?php
/**
 * TwinTack Shippo API Client
 * 
 * Direct API integration with Shippo for order synchronization
 * 
 * @package TwinTack_Manual_Order_Payments
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Shippo_API_Client {
    
    private static $instance = null;
    private $api_token;
    private $api_base_url = 'https://api.goshippo.com/';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Get API token from WordPress options or environment
        $this->api_token = $this->get_api_token();
        
        // Hook into Shippo sync actions
        add_action('twintack_notify_shippo_api', array($this, 'handle_shippo_notification'), 10, 2);
        add_action('twintack_shippo_status_updated', array($this, 'handle_shippo_status_update'), 10, 3);
        
        // Add admin settings for API token
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add AJAX handler for connection test
        add_action('wp_ajax_test_shippo_connection', array($this, 'test_connection'));
    }
    
    /**
     * Get Shippo API token from settings
     */
    private function get_api_token() {
        // Try to get from WordPress options first
        $token = get_option('twintack_shippo_api_token');
        
        // Fallback to environment variable or constant
        if (empty($token)) {
            $token = defined('TWINTACK_SHIPPO_API_TOKEN') ? TWINTACK_SHIPPO_API_TOKEN : '';
        }
        
        return $token;
    }
    
    /**
     * Handle Shippo notification from twintack_notify_shippo_api action
     */
    public function handle_shippo_notification($shippo_order_data, $order) {
        if (!$order instanceof WC_Order) {
            twintack_manual_payments_log("Shippo API: Invalid order object received in notification", 'error');
            return;
        }
        
        $order_id = $order->get_id();
        $wc_status = $order->get_status();
        
        twintack_manual_payments_log("Shippo API: Received notification for order #{$order_id} with status '{$wc_status}'");
        
        // Delegate to main sync method
        $this->send_order_to_shippo($shippo_order_data, $order);
    }
    
    /**
     * Handle Shippo status update from twintack_shippo_status_updated action
     */
    public function handle_shippo_status_update($order_id, $shippo_status, $wc_status) {
        $order = wc_get_order($order_id);
        if (!$order) {
            twintack_manual_payments_log("Shippo API: Order #{$order_id} not found for status update", 'error');
            return;
        }
        
        twintack_manual_payments_log("Shippo API: Received status update for order #{$order_id}: '{$wc_status}' -> Shippo '{$shippo_status}'");
        
        // Create shippo order data structure for the existing method
        $shippo_order_data = array(
            'order_id' => $order_id,
            'status' => $wc_status,
            'fulfillment_status' => $shippo_status
        );
        
        // Delegate to main sync method
        $this->send_order_to_shippo($shippo_order_data, $order);
    }
    
    /**
     * Check if order contains any items that need shipping
     */
    private function order_needs_shipping($order) {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->needs_shipping()) {
                return true; // At least one item needs shipping
            }
        }
        return false; // All items are virtual/downloadable
    }

    /**
     * Send order to Shippo API using Orders API (CORRECTED with proper required fields)
     */
    public function send_order_to_shippo($shippo_order_data, $order) {
        if (empty($this->api_token)) {
            twintack_manual_payments_log("Shippo API: No API token configured", 'error');
            return false;
        }
        
        // Check if order needs shipping before syncing to Shippo
        if (!$this->order_needs_shipping($order)) {
            $order_id = $order->get_id();
            twintack_manual_payments_log("Shippo API: ⏭️ Skipping order #{$order_id} - contains only virtual/downloadable products");
            
            // Mark as skipped in order meta for tracking
            $order->update_meta_data('_shippo_skipped_reason', 'Virtual products only');
            $order->update_meta_data('_shippo_api_sync_timestamp', current_time('timestamp'));
            $order->save();
            
            return true; // Return true since this is expected behavior, not an error
        }
        
        try {
            $order_id = $order->get_id();
            twintack_manual_payments_log("Shippo API: ========== STARTING ORDER SYNC FOR ORDER #{$order_id} ==========");
            twintack_manual_payments_log("Shippo API: Input shippo_order_data: " . json_encode($shippo_order_data));
             
             // Format order data according to Shippo Orders API specification
             $api_data = $this->format_order_for_shippo_orders_api($shippo_order_data, $order);
             twintack_manual_payments_log("Shippo API: Formatted order data: " . json_encode($api_data, JSON_PRETTY_PRINT));
             
             // Create order in Shippo using correct Orders API
             twintack_manual_payments_log("Shippo API: Creating order in Shippo Orders API");
             $response = $this->create_shippo_order($api_data);
             twintack_manual_payments_log("Shippo API: Order creation response: " . json_encode($response, JSON_PRETTY_PRINT));
             
             if ($response && isset($response['object_id'])) {
                 // Store Shippo object ID in order meta
                 $order->update_meta_data('_shippo_order_id', $response['object_id']);
                 $order->update_meta_data('_shippo_api_sync_timestamp', current_time('timestamp'));
                 $order->update_meta_data('_shippo_last_sync_data', json_encode($api_data));
                 $order->save();
                 
                 twintack_manual_payments_log("Shippo API: ✅ Successfully created order #{$order_id}, Shippo Order ID: {$response['object_id']}");
                 return true;
             } else {
                 $error_message = "Invalid response format: " . json_encode($response);
                 twintack_manual_payments_log("Shippo API: ❌ {$error_message}", 'error');
                 
                 // Store error in order meta for Simple Manager to access
                 $order->update_meta_data('_shippo_last_error', $error_message);
                 $order->save();
                 
                 return false;
             }
             
         } catch (Exception $e) {
             $error_message = "Exception: " . $e->getMessage();
             twintack_manual_payments_log("Shippo API: ❌ Exception for order #{$order_id}: " . $e->getMessage(), 'error');
             twintack_manual_payments_log("Shippo API: Exception trace: " . $e->getTraceAsString(), 'error');
             
             // Store error in order meta for Simple Manager to access
             $order->update_meta_data('_shippo_last_error', $error_message);
             $order->save();
             
             return false;
         }
     }
    
         /**
      * Format WooCommerce order data for Shippo Shipments API (CORRECT)
      */
     private function format_order_for_shippo_shipments_api($shippo_order_data, $order) {
         // Get "from" address (shop address)
         $address_from = $this->create_shippo_address($this->get_shop_address());
         
         // Get "to" address (customer shipping address)  
         $address_to = $this->create_shippo_address($this->get_customer_address($order));
         
         // Create parcel for the shipment
         $parcels = array($this->create_shippo_parcel($order));
         
         // Build metadata for tracking
         $metadata = array(
             'wc_order_id' => (string) $order->get_id(),
             'wc_order_number' => $order->get_order_number(),
             'wc_status' => $order->get_status(),
             'fulfillment_note' => $this->get_order_notes($order)
         );
         
         // Shippo Shipments API format
         $shipment_data = array(
             'address_from' => $address_from,
             'address_to' => $address_to,
             'parcels' => $parcels,
             'async' => false, // Get rates immediately
             'metadata' => json_encode($metadata)
         );
         
         return $shipment_data;
     }
     
     /**
      * Create Shippo address object
      */
     private function create_shippo_address($address_data) {
         // Clean up empty fields and ensure required fields
         $address = array_filter($address_data, function($value) {
             return !empty($value);
         });
         
         // Ensure required fields are present
         if (empty($address['name'])) {
             $address['name'] = 'Customer';
         }
         if (empty($address['country'])) {
             $address['country'] = 'US'; // Default to US if not set
         }
         
         return $address;
     }
     
     /**
      * Create Shippo parcel object
      */
     private function create_shippo_parcel($order) {
         $total_weight = $this->calculate_total_weight($order);
         if ($total_weight <= 0) {
             $total_weight = 1; // Default to 1 lb if no weight specified
         }
         
         return array(
             'length' => '10', // Default package dimensions
             'width' => '8',
             'height' => '4',
             'distance_unit' => 'in',
             'weight' => (string) $total_weight,
             'mass_unit' => get_option('woocommerce_weight_unit', 'lb'),
             'metadata' => json_encode(array(
                 'order_value' => $order->get_total(),
                 'currency' => $order->get_currency(),
                 'item_count' => count($order->get_items())
             ))
         );
     }
     
     /**
      * Get customer shipping address
      */
     private function get_customer_address($order) {
         return array(
             'name' => trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()) ?: $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
             'company' => $order->get_shipping_company(),
             'street1' => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
             'street2' => $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
             'city' => $order->get_shipping_city() ?: $order->get_billing_city(),
             'state' => $order->get_shipping_state() ?: $order->get_billing_state(),
             'zip' => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
             'country' => $order->get_shipping_country() ?: $order->get_billing_country(),
             'phone' => $order->get_billing_phone(),
             'email' => $order->get_billing_email()
         );
     }
     
     /**
      * Format WooCommerce order data for Shippo Orders API (CORRECTED per documentation)
      */
     private function format_order_for_shippo_orders_api($shippo_order_data, $order) {
         // Build the mandatory to_address structure per Shippo specification
         $to_address = array(
             'name' => trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()) ?: 'Customer',
             'street1' => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
             'city' => $order->get_shipping_city() ?: $order->get_billing_city(),
             'state' => $order->get_shipping_state() ?: $order->get_billing_state(),
             'zip' => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
             'country' => $order->get_shipping_country() ?: $order->get_billing_country() ?: 'US',
             'email' => $order->get_billing_email(),
             'phone' => $order->get_billing_phone()
         );
         
         // Add company if exists
         if ($order->get_shipping_company()) {
             $to_address['company'] = $order->get_shipping_company();
         }
         
         // Add street2 if exists
         if ($order->get_shipping_address_2()) {
             $to_address['street2'] = $order->get_shipping_address_2();
         }
         
         // Clean up empty fields (but keep required ones)
         $to_address = array_filter($to_address, function($value, $key) {
             // Always keep required fields even if empty
             $required_fields = ['name', 'street1', 'city', 'state', 'zip', 'country'];
             return !empty($value) || in_array($key, $required_fields);
         }, ARRAY_FILTER_USE_BOTH);
         
         // Build the order data according to Shippo specification
         $order_data = array(
             // MANDATORY FIELDS per documentation
             'to_address' => $to_address,
             'placed_at' => $order->get_date_created()->format('c'), // ISO 8601 format
             
             // RECOMMENDED FIELDS
             'order_number' => $order->get_order_number(),
             'order_status' => $this->map_wc_status_to_shippo_orders($order->get_status(), $order),
             
                         // OPTIONAL FIELDS - handle $0 orders specially
            'total_price' => number_format(max($order->get_total(), 0.01), 2, '.', ''), // Minimum $0.01, max 2 decimals
            'currency' => $order->get_currency(),
            'subtotal_price' => number_format(max(($order->get_total() - $order->get_total_tax() - $order->get_shipping_total()), 0.01), 2, '.', ''), // Format to 2 decimals
            'total_tax' => number_format($order->get_total_tax(), 2, '.', ''),
            'shipping_cost' => number_format($order->get_shipping_total(), 2, '.', ''),
             'shipping_cost_currency' => $order->get_currency(),
             'shop_app' => 'WooCommerce',
             'line_items' => $this->format_line_items_for_orders_api($order)
         );
         
         // Add weight (REQUIRED by Shippo Orders API)
         $total_weight = $this->calculate_total_weight($order);
         // Always include weight fields as they are required by Shippo
         $order_data['weight'] = (string) max($total_weight, 0.1); // Minimum 0.1 for zero-weight items
         $order_data['weight_unit'] = $this->map_weight_unit_for_shippo(get_option('woocommerce_weight_unit', 'lb'));
         
         // Add notes if available
         $notes = $this->get_order_notes($order);
         if (!empty($notes)) {
             $order_data['notes'] = $notes;
         }
         
         return $order_data;
     }
     
     /**
      * Map WooCommerce weight units to Shippo-accepted weight units
      */
     private function map_weight_unit_for_shippo($wc_weight_unit) {
         $weight_unit_map = array(
             'lbs' => 'lb',    // WooCommerce 'lbs' -> Shippo 'lb'
             'lb'  => 'lb',    // Already correct
             'kg'  => 'kg',    // Kilograms work in both
             'g'   => 'g',     // Grams work in both
             'oz'  => 'oz',    // Ounces work in both
             ''    => 'lb'     // Default to lb if empty
         );
         
         return isset($weight_unit_map[$wc_weight_unit]) ? $weight_unit_map[$wc_weight_unit] : 'lb';
     }
     
     /**
      * Map WooCommerce status to Shippo Orders API status
      */
     private function map_wc_status_to_shippo_orders($wc_status, $order = null) {
         // Per Shippo documentation: UNKNOWN, AWAITPAY, PAID, REFUNDED, CANCELLED, PARTIALLY_FULFILLED, SHIPPED
         $status_map = array(
             'pending' => 'AWAITPAY',
             'on-hold' => 'AWAITPAY',
             'invoiced' => 'PAID', // Treat invoiced as paid for fulfillment
             'processing' => 'PAID',
             'completed' => 'SHIPPED',
             'cancelled' => 'CANCELLED',
             'refunded' => 'REFUNDED',
             'failed' => 'CANCELLED'
         );
         
         // Special handling for $0 orders - they should be treated as PAID regardless of status
         // since no payment is actually required
         if ($order && floatval($order->get_total()) == 0.00) {
             if (in_array($wc_status, array('processing', 'invoiced', 'completed'))) {
                 return 'PAID'; // $0 orders are effectively "paid"
             }
         }
         
         return isset($status_map[$wc_status]) ? $status_map[$wc_status] : 'UNKNOWN';
     }
     
         /**
     * Format line items for Shippo Orders API
     * Only includes physical products that need shipping
     */
    private function format_line_items_for_orders_api($order) {
        $line_items = array();
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            // Skip virtual/digital products - they don't need shipping
            if (!$product || $product->is_virtual() || $product->is_downloadable()) {
                twintack_manual_payments_log("Shippo API: Skipping virtual/digital product: " . $item->get_name());
                continue;
            }
            
            $line_item = array(
                'title' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total_price' => max($item->get_total(), 0.01), // Minimum $0.01 per item for Shippo
                'currency' => $order->get_currency()
            );
            
            // Add SKU if available
            if ($product->get_sku()) {
                $line_item['sku'] = $product->get_sku();
            }
            
            // Add weight if available
            if ($product->get_weight()) {
                $line_item['weight'] = $product->get_weight();
                $line_item['weight_unit'] = $this->map_weight_unit_for_shippo(get_option('woocommerce_weight_unit', 'lb'));
            }
            
            $line_items[] = $line_item;
            twintack_manual_payments_log("Shippo API: Including physical product: " . $item->get_name() . " (SKU: " . $product->get_sku() . ")");
        }
        
        return $line_items;
    }
    
    /**
     * Map WooCommerce status to Shippo status
     */
    private function map_wc_status_to_shippo($wc_status) {
        $status_map = array(
            'pending' => 'PAID',
            'processing' => 'PAID',
            'invoiced' => 'PAID', // Treat invoiced as paid for fulfillment purposes
            'on-hold' => 'PAID',
            'completed' => 'SHIPPED',
            'cancelled' => 'CANCELLED',
            'refunded' => 'CANCELLED',
            'failed' => 'CANCELLED'
        );
        
        return isset($status_map[$wc_status]) ? $status_map[$wc_status] : 'PAID';
    }
    
         /**
      * Get shop address for "from" address
      */
     private function get_shop_address() {
         // Parse country/state from default country setting (e.g., "US:CA" -> country="US", state="CA")
         $default_country = get_option('woocommerce_default_country', 'US');
         $country_parts = explode(':', $default_country);
         $country = $country_parts[0];
         $state = isset($country_parts[1]) ? $country_parts[1] : '';
         
         return array(
             'name' => get_bloginfo('name') ?: 'Store',
             'street1' => get_option('woocommerce_store_address') ?: '123 Main St',
             'street2' => get_option('woocommerce_store_address_2', ''),
             'city' => get_option('woocommerce_store_city') ?: 'City',
             'state' => $state ?: get_option('woocommerce_store_state', 'CA'),
             'zip' => get_option('woocommerce_store_postcode') ?: '90210',
             'country' => $country,
             'phone' => get_option('woocommerce_store_phone', ''),
             'email' => get_option('admin_email') ?: 'admin@example.com'
         );
     }
    
    /**
     * Format order line items for Shippo
     */
    private function format_line_items($order) {
        $line_items = array();
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            $line_items[] = array(
                'title' => $item->get_name(),
                'sku' => $product ? $product->get_sku() : '',
                'quantity' => $item->get_quantity(),
                'total_price' => $item->get_total(),
                'currency' => $order->get_currency(),
                'weight' => $product ? $product->get_weight() : 0,
                'weight_unit' => $this->map_weight_unit_for_shippo(get_option('woocommerce_weight_unit', 'lb'))
            );
        }
        
        return $line_items;
    }
    
    /**
     * Calculate total order weight
     */
    private function calculate_total_weight($order) {
        $total_weight = 0;
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->get_weight()) {
                $total_weight += floatval($product->get_weight()) * $item->get_quantity();
            }
        }
        
        return $total_weight;
    }
    
    /**
     * Get order notes for Shippo
     */
    private function get_order_notes($order) {
        $notes = array();
        
        // Add fulfillment note for invoiced orders
        if ($order->get_status() === 'invoiced') {
            $notes[] = 'INVOICE ORDER - Ship immediately despite Payment Pending status';
        }
        
        // Add customer note if exists
        if ($order->get_customer_note()) {
            $notes[] = 'Customer Note: ' . $order->get_customer_note();
        }
        
        return implode(' | ', $notes);
    }
    
         /**
      * Create new shipment in Shippo (CORRECT API)
      */
     private function create_shippo_shipment($shipment_data) {
         twintack_manual_payments_log("Shippo API: Creating shipment with data: " . json_encode($shipment_data, JSON_PRETTY_PRINT));
         return $this->make_api_request('POST', 'shipments/', $shipment_data);
     }
     
     /**
      * Create new order in Shippo Orders API
      */
     private function create_shippo_order($order_data) {
         twintack_manual_payments_log("Shippo API: Creating order using Orders API");
         return $this->make_api_request('POST', 'orders/', $order_data);
     }
     
     /**
      * Update existing order in Shippo (DEPRECATED - Orders API is beta)
      */
     private function update_shippo_order($shippo_object_id, $order_data) {
         twintack_manual_payments_log("Shippo API: WARNING - Using deprecated Orders API (beta)");
         return $this->make_api_request('PUT', "orders/{$shippo_object_id}/", $order_data);
     }
     
     /**
      * Get existing order from Shippo by order number (DEPRECATED - Orders API is beta)
      */
     private function get_shippo_order($order_number) {
         twintack_manual_payments_log("Shippo API: WARNING - Using deprecated Orders API (beta)");
         $response = $this->make_api_request('GET', "orders/?order_number={$order_number}");
         
         if ($response && isset($response['results']) && !empty($response['results'])) {
             return $response['results'][0];
         }
         
         return null;
     }
    
    /**
     * Make API request to Shippo
     */
    private function make_api_request($method, $endpoint, $data = null) {
        $url = $this->api_base_url . $endpoint;
        
        // Log the full request details
        twintack_manual_payments_log("Shippo API: 🌐 Making {$method} request to: {$url}");
        twintack_manual_payments_log("Shippo API: 🔑 Using token: " . substr($this->api_token, 0, 10) . "..." . substr($this->api_token, -4));
        
        $headers = array(
            'Authorization' => 'ShippoToken ' . $this->api_token,
            'Content-Type' => 'application/json'
        );
        
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30
        );
        
        if ($data && in_array($method, array('POST', 'PUT'))) {
            $args['body'] = json_encode($data);
            twintack_manual_payments_log("Shippo API: 📤 Request body: " . json_encode($data, JSON_PRETTY_PRINT));
        }
        
        twintack_manual_payments_log("Shippo API: 📋 Full request args: " . json_encode(array(
            'method' => $args['method'],
            'headers' => array(
                'Authorization' => 'ShippoToken ' . substr($this->api_token, 0, 10) . '...',
                'Content-Type' => $args['headers']['Content-Type']
            ),
            'timeout' => $args['timeout'],
            'body_length' => isset($args['body']) ? strlen($args['body']) : 0
        )));
        
        $start_time = microtime(true);
        $response = wp_remote_request($url, $args);
        $end_time = microtime(true);
        $duration = round(($end_time - $start_time) * 1000, 2);
        
        twintack_manual_payments_log("Shippo API: ⏱️ Request completed in {$duration}ms");
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            twintack_manual_payments_log("Shippo API: ❌ WP Error: {$error_message}", 'error');
            throw new Exception('API request failed: ' . $error_message);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);
        
        twintack_manual_payments_log("Shippo API: 📥 Response code: {$response_code}");
        twintack_manual_payments_log("Shippo API: 📥 Response headers: " . json_encode($response_headers->getAll()));
        twintack_manual_payments_log("Shippo API: 📥 Response body: " . $response_body);
        
        if ($response_code >= 400) {
            twintack_manual_payments_log("Shippo API: ❌ Error response {$response_code}: {$response_body}", 'error');
            throw new Exception("API error {$response_code}: {$response_body}");
        }
        
        $parsed_response = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            twintack_manual_payments_log("Shippo API: ❌ JSON decode error: " . json_last_error_msg(), 'error');
            throw new Exception('Invalid JSON response from Shippo API');
        }
        
        twintack_manual_payments_log("Shippo API: ✅ Parsed response: " . json_encode($parsed_response, JSON_PRETTY_PRINT));
        
        return $parsed_response;
    }
    
    /**
     * Register admin settings (simplified)
     */
    public function register_settings() {
        // Simplified - we'll handle the form manually
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'TwinTack Shippo API',
            'Shippo API',
            'manage_woocommerce',
            'twintack-shippo-api',
            array($this, 'admin_page')
        );
    }
    

    
    /**
     * Admin page
     */
    public function admin_page() {
        $message = '';
        
        // Handle form submission
        if (isset($_POST['save_token']) && wp_verify_nonce($_POST['_wpnonce'], 'twintack_shippo_save_token')) {
            $new_token = sanitize_text_field($_POST['shippo_api_token']);
            update_option('twintack_shippo_api_token', $new_token);
            $this->api_token = $new_token; // Update instance variable
            $message = '<div class="notice notice-success"><p>✅ Shippo API token saved successfully!</p></div>';
        }
        
        $token = get_option('twintack_shippo_api_token');
        $has_token = !empty($token);
        
        ?>
        <div class="wrap">
            <h1>TwinTack Shippo API Configuration</h1>
            
            <?php echo $message; ?>
            
            <div class="card">
                <h2>Current Status</h2>
                <p><strong>API Token:</strong> <?php echo $has_token ? '✅ Configured' : '❌ Not configured'; ?></p>
                <?php if ($has_token): ?>
                    <p><strong>Token (masked):</strong> <?php echo str_repeat('*', max(0, strlen($token) - 8)) . substr($token, -8); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2>Configure API Token</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('twintack_shippo_save_token'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="shippo_api_token">Shippo API Token</label>
                            </th>
                            <td>
                                <input type="password" 
                                       name="shippo_api_token" 
                                       id="shippo_api_token" 
                                       value="<?php echo esc_attr($token); ?>" 
                                       class="regular-text" />
                                <p class="description">
                                    Enter your Shippo Live or Test API token. 
                                    Find this in your <a href="https://apps.goshippo.com/settings/api" target="_blank">Shippo dashboard under Settings → API</a>.
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Token', 'primary', 'save_token'); ?>
                </form>
            </div>
            
            <?php if ($has_token): ?>
                <div class="card">
                    <h2>Test API Connection</h2>
                    <p>
                        <button type="button" class="button" onclick="testShippoConnection()">Test Connection</button>
                        <span id="test-result"></span>
                    </p>
                </div>
                
                <script>
                function testShippoConnection() {
                    var button = document.querySelector('button');
                    var result = document.getElementById('test-result');
                    
                    button.disabled = true;
                    button.textContent = 'Testing...';
                    result.innerHTML = '';
                    
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=test_shippo_connection&nonce=<?php echo wp_create_nonce('test_shippo_connection'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        button.disabled = false;
                        button.textContent = 'Test Connection';
                        
                        if (data.success) {
                            result.innerHTML = '<span style="color: green;">✅ Connection successful!</span>';
                        } else {
                            result.innerHTML = '<span style="color: red;">❌ Connection failed: ' + data.data.message + '</span>';
                        }
                    })
                    .catch(error => {
                        button.disabled = false;
                        button.textContent = 'Test Connection';
                        result.innerHTML = '<span style="color: red;">❌ Test failed: ' + error.message + '</span>';
                    });
                }
                </script>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Test API connection via AJAX
     */
    public function test_connection() {
        check_ajax_referer('test_shippo_connection', 'nonce');
        
        if (empty($this->api_token)) {
            wp_send_json_error(array('message' => 'No API token configured'));
        }
        
        try {
            // Test API connection by fetching orders
            $response = $this->make_api_request('GET', 'orders/?results=1');
            wp_send_json_success(array('message' => 'API connection successful - Orders API working'));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Get debug information about the API client configuration
     */
    public function get_debug_info() {
        return array(
            'api_token_configured' => !empty($this->api_token),
            'api_token_preview' => !empty($this->api_token) ? substr($this->api_token, 0, 10) . '...' . substr($this->api_token, -4) : 'Not set',
            'api_base_url' => $this->api_base_url,
            'token_type' => !empty($this->api_token) ? (strpos($this->api_token, 'live') !== false ? 'Live' : 'Test') : 'Unknown',
            'wp_remote_available' => function_exists('wp_remote_request'),
            'json_functions_available' => function_exists('json_encode') && function_exists('json_decode'),
            'current_time' => current_time('c'),
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version')
        );
    }
}

// Initialize the API client
add_action('init', function() {
    TwinTack_Shippo_API_Client::get_instance();
});