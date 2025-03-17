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
        
        // Add to cart handling
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_grip_data_to_cart'), 10, 3);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_custom_data'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_grip_data_to_order'), 10, 4);
    }
    
    public function process_grip_form($entry, $form) {
        try {
            // Create grip design post
            $grip_id = wp_insert_post(array(
                'post_type' => 'grip_design',
                'post_title' => sprintf('Custom Grip - %s %s', $entry['1.3'], $entry['1.6']),
                'post_status' => 'artwork_pending',
                'meta_input' => array(
                    '_grip_customer_name' => $entry['1.3'] . ' ' . $entry['1.6'],
                    '_grip_customer_email' => $entry['3'],
                    '_grip_team_name' => $entry['8'],
                    '_grip_design_type' => $entry['12'],
                    '_grip_notes' => $entry['14'],
                    '_grip_quantity' => $entry['21'],
                    '_grip_form_entry_id' => $entry['id']
                )
            ));

            if (is_wp_error($grip_id)) {
                throw new Exception($grip_id->get_error_message());
            }

            // Get or create deposit product
            $deposit_product_id = $this->get_deposit_product_id();
            if (!$deposit_product_id) {
                throw new Exception('Failed to get or create deposit product');
            }

            // Add to cart with metadata
            $cart_item_data = array(
                'grip_design_data' => array(
                    'grip_id' => $grip_id,
                    'customer_name' => $entry['1.3'] . ' ' . $entry['1.6'],
                    'customer_email' => $entry['3'],
                    'team_name' => $entry['8'],
                    'design_type' => $entry['12'],
                    'notes' => $entry['14'],
                    'quantity' => $entry['21']
                )
            );

            // Force unique cart item
            $cart_item_data['unique_key'] = md5(microtime() . rand());

            // Add to cart
            WC()->cart->add_to_cart($deposit_product_id, 1, 0, array(), $cart_item_data);

            // Redirect to cart
            wp_redirect(wc_get_cart_url());
            exit;

        } catch (Exception $e) {
            error_log('Grip Form Error: ' . $e->getMessage());
            // Add error message to form
            GFAPI::add_note($entry['id'], 0, 'error', 'Error processing grip design: ' . $e->getMessage());
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
            $item_data[] = array(
                'key' => 'Customer',
                'value' => $cart_item['grip_design_data']['customer_name']
            );
            $item_data[] = array(
                'key' => 'Team/School',
                'value' => $cart_item['grip_design_data']['team_name']
            );
            $item_data[] = array(
                'key' => 'Design Type',
                'value' => $cart_item['grip_design_data']['design_type']
            );
            $item_data[] = array(
                'key' => 'Quantity',
                'value' => $cart_item['grip_design_data']['quantity']
            );
        }
        return $item_data;
    }

    public function save_grip_data_to_order($item, $cart_item_key, $values, $order) {
        if (isset($values['grip_design_data'])) {
            // Save all grip design data as hidden meta
            foreach ($values['grip_design_data'] as $key => $value) {
                $item->add_meta_data("_grip_{$key}", $value, true);
            }
            
            // Add visible meta data
            $item->add_meta_data('Customer', $values['grip_design_data']['customer_name'], true);
            $item->add_meta_data('Team/School', $values['grip_design_data']['team_name'], true);
            $item->add_meta_data('Design Type', $values['grip_design_data']['design_type'], true);
            $item->add_meta_data('Quantity', $values['grip_design_data']['quantity'], true);
            
            // Link to grip design post
            $item->add_meta_data('_grip_design_id', $values['grip_design_data']['grip_id'], true);
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
