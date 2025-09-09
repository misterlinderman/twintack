<?php
/**
 * Wholesale Hook Prevention
 * Prevents specific wholesale plugin hooks from causing order duplication
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Wholesale_Hook_Prevention {
    
    private $processed_orders = array();
    private $prevention_active = false;
    
    public function __construct() {
        // Only activate if prevention is enabled
        if (get_transient('twintack_order_prevention_active')) {
            $this->prevention_active = true;
            $this->hook_prevention();
        }
    }
    
    private function hook_prevention() {
        // Target specific wholesale plugin hooks that cause duplication
        add_action('woocommerce_new_order', array($this, 'prevent_wholesale_new_order_duplication'), 5, 1);
        add_action('woocommerce_update_order', array($this, 'prevent_wholesale_update_order_duplication'), 5, 2);
        
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('TWINTACK_PREVENTION: Wholesale hook prevention activated');
        }
    }
    
    public function prevent_wholesale_new_order_duplication($order_id) {
        // Check if this order has already been processed by wholesale plugins
        if ($this->has_wholesale_processing_started($order_id)) {
            // Remove the wholesale hook to prevent duplicate processing
            remove_action('woocommerce_new_order', array('WWPP_WC_Order', 'wholesale_woocommerce_update_order'), 11);
            
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log("TWINTACK_PREVENTION: Blocked duplicate wholesale processing for order {$order_id}");
            }
        } else {
            // Mark that wholesale processing has started for this order
            $this->mark_wholesale_processing_started($order_id);
            
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log("TWINTACK_PREVENTION: Allowed initial wholesale processing for order {$order_id}");
            }
        }
    }
    
    public function prevent_wholesale_update_order_duplication($order_id, $order) {
        // Check if this order has already been processed by wholesale plugins
        if ($this->has_wholesale_processing_started($order_id)) {
            // Remove the wholesale hook to prevent duplicate processing
            remove_action('woocommerce_update_order', array('WWPP_WC_Order', 'wholesale_woocommerce_update_order'), 11);
            
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log("TWINTACK_PREVENTION: Blocked duplicate wholesale update for order {$order_id}");
            }
        } else {
            // Mark that wholesale processing has started for this order
            $this->mark_wholesale_processing_started($order_id);
            
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log("TWINTACK_PREVENTION: Allowed initial wholesale update for order {$order_id}");
            }
        }
    }
    
    private function has_wholesale_processing_started($order_id) {
        $processed_orders = get_transient('twintack_wholesale_processed_orders');
        if (!$processed_orders) {
            $processed_orders = array();
        }
        
        return isset($processed_orders[$order_id]);
    }
    
    private function mark_wholesale_processing_started($order_id) {
        $processed_orders = get_transient('twintack_wholesale_processed_orders');
        if (!$processed_orders) {
            $processed_orders = array();
        }
        
        $processed_orders[$order_id] = array(
            'timestamp' => current_time('Y-m-d H:i:s'),
            'user_id' => get_current_user_id()
        );
        
        // Store for 1 hour
        set_transient('twintack_wholesale_processed_orders', $processed_orders, 3600);
    }
}

// Initialize wholesale hook prevention
new TwinTack_Wholesale_Hook_Prevention();

?>
