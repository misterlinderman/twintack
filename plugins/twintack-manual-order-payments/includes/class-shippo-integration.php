<?php
/**
 * TwinTack Shippo Integration
 * 
 * Ensures proper integration between TwinTack invoice system and Shippo fulfillment
 * 
 * @package TwinTack_Manual_Order_Payments
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Shippo_Integration {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // DISABLED: Automatic hooks to prevent conflicts with Simple Order Manager
        // Manual control is now handled via Simple Order Manager
        // add_action('woocommerce_order_status_changed', array($this, 'sync_order_with_shippo'), 20, 4);
        // add_filter('shippo_order_statuses', array($this, 'add_invoiced_status_to_shippo'), 10, 1);
        // add_action('twintack_shippo_status_updated', array($this, 'handle_shippo_status_update'), 10, 3);
        
        // Ensure invoiced orders are recognized by shipping integrations
        add_filter('woocommerce_shipping_packages', array($this, 'include_invoiced_orders_in_shipping'));
        add_action('woocommerce_checkout_order_processed', array($this, 'process_order_for_shippo'), 10, 1);
        
        // Add admin menu for skipped orders
        add_action('admin_menu', array($this, 'add_skipped_orders_menu'));
        
        // DISABLED: Meta box conflicts with TwinTack Order Control - use Simple Order Manager instead
        // add_action('add_meta_boxes', array($this, 'add_shippo_meta_box'));
        // add_action('save_post', array($this, 'save_shippo_meta_fields'));
    }
    
    /**
     * Add admin menu for skipped orders
     */
    public function add_skipped_orders_menu() {
        add_submenu_page(
            'woocommerce',
            'Shippo Skipped Orders',
            'Shippo Skipped',
            'manage_woocommerce',
            'shippo-skipped-orders',
            array($this, 'skipped_orders_page')
        );
    }

    /**
     * Display skipped orders page
     */
    public function skipped_orders_page() {
        $skipped_orders = $this->get_skipped_orders();
        ?>
        <div class="wrap">
            <h1>Orders Skipped from Shippo Sync</h1>
            <p>These orders contain only virtual/downloadable products and were automatically skipped from Shippo synchronization.</p>
            
            <?php if (empty($skipped_orders)): ?>
                <div class="notice notice-success">
                    <p>No orders have been skipped from Shippo sync.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Skip Reason</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($skipped_orders as $order): ?>
                            <tr>
                                <td><a href="<?php echo admin_url('post.php?post=' . $order->get_id() . '&action=edit'); ?>">#<?php echo $order->get_order_number(); ?></a></td>
                                <td><?php echo $order->get_date_created()->format('Y-m-d H:i:s'); ?></td>
                                <td><?php echo $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); ?></td>
                                <td>
                                    <?php 
                                    $items = array();
                                    foreach ($order->get_items() as $item) {
                                        $product = $item->get_product();
                                        $items[] = $item->get_name() . ' (' . ($product && $product->is_virtual() ? 'Virtual' : 'Physical') . ')';
                                    }
                                    echo implode(', ', $items);
                                    ?>
                                </td>
                                <td><?php echo $order->get_meta('_shippo_skipped_reason'); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('post.php?post=' . $order->get_id() . '&action=edit'); ?>" class="button button-small">View Order</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get orders that were skipped from Shippo sync
     */
    public function get_skipped_orders() {
        $skipped_orders = wc_get_orders(array(
            'meta_query' => array(
                array(
                    'key' => '_shippo_skipped_reason',
                    'compare' => 'EXISTS'
                )
            ),
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        return $skipped_orders;
    }

    /**
     * Sync order status changes with Shippo
     */
    public function sync_order_with_shippo($order_id, $old_status, $new_status, $order) {
        if (!$order) {
            return;
        }
        
        // Get the corresponding Shippo status
        $shippo_status = $this->get_shippo_status_from_wc_status($new_status, $order);
        
        if (!$shippo_status) {
            return;
        }
        
        // Update order meta with Shippo status
        $order->update_meta_data('_shippo_fulfillment_status', $shippo_status);
        $order->update_meta_data('_shippo_sync_timestamp', current_time('timestamp'));
        $order->save();
        
        // Log the sync
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log("Shippo sync: Order {$order_id} status {$new_status} → Shippo status {$shippo_status}");
        }
        
        // Trigger any Shippo API calls if integration exists
        $this->notify_shippo_api($order, $shippo_status, $new_status);
    }
    
    /**
     * Get Shippo fulfillment status from WooCommerce status
     */
    private function get_shippo_status_from_wc_status($wc_status, $order = null) {
        $status_mapping = array(
            'pending'    => 'Payment Pending',
            'on-hold'    => 'Payment Pending', 
            'invoiced'   => 'Payment Pending', // Key mapping for invoiced orders
            'shipped'    => 'Shipped',
            'completed'  => 'Shipped',
            'cancelled'  => 'Cancelled',
            'refunded'   => 'Refunded',
            'failed'     => 'Failed'
        );
        
        // Special handling for processing status - depends on order type
        if ($wc_status === 'processing') {
            if ($order && $this->is_manual_order($order)) {
                // Manual orders: Processing = Payment Pending (ready for fulfillment)
                return 'Payment Pending';
            } else {
                // Regular orders: Processing = Paid (customer already paid)
                return 'Paid';
            }
        }
        
        return isset($status_mapping[$wc_status]) ? $status_mapping[$wc_status] : null;
    }
    
    /**
     * Add invoiced status to Shippo's recognized order statuses
     */
    public function add_invoiced_status_to_shippo($statuses) {
        if (!in_array('invoiced', $statuses)) {
            $statuses[] = 'invoiced';
        }
        return $statuses;
    }
    
    /**
     * Handle Shippo status updates
     */
    public function handle_shippo_status_update($order_id, $shippo_status, $wc_status) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Add detailed order note
        $order->add_order_note(sprintf(
            'Shippo Integration: Order status "%s" mapped to Shippo fulfillment status "%s"',
            ucfirst($wc_status),
            $shippo_status
        ));
        
        // Handle specific status transitions
        switch ($shippo_status) {
            case 'Payment Pending':
                $this->handle_payment_pending_status($order);
                break;
            case 'Paid':
                $this->handle_paid_status($order);
                break;
            case 'Shipped':
                $this->handle_shipped_status($order);
                break;
        }
    }
    
    /**
     * Handle payment pending status
     */
    private function handle_payment_pending_status($order) {
        // For invoiced orders AND manual processing orders, do NOT put on fulfillment hold
        // Customer requirement: "Payment Pending" orders should still ship immediately
        if ($order->get_status() === 'invoiced' || ($order->get_status() === 'processing' && $this->is_manual_order($order))) {
            $order->update_meta_data('_shippo_ready_for_fulfillment', 'yes');
            $order->update_meta_data('_shippo_payment_status', 'Payment Pending');
            
            if ($order->get_status() === 'invoiced') {
                $order->update_meta_data('_shippo_fulfillment_note', 'Invoice sent - ship immediately despite pending payment');
            } elseif ($order->get_status() === 'processing' && $this->is_manual_order($order)) {
                $order->update_meta_data('_shippo_fulfillment_note', 'Manual order - ship immediately');
            }
            
            // Remove any existing hold flags
            $order->delete_meta_data('_shippo_fulfillment_hold');
            $order->delete_meta_data('_shippo_hold_reason');
            
            if (function_exists('twintack_manual_payments_log')) {
                if ($order->get_status() === 'invoiced') {
                    $order_type = 'invoiced';
                } elseif ($this->is_manual_order($order)) {
                    $order_type = 'manual processing';
                } else {
                    $order_type = 'processing (unknown type)';
                }
                twintack_manual_payments_log("Shippo: Order {$order->get_id()} set for immediate fulfillment - {$order_type} order ships despite pending payment");
            }
        } else {
            // For other payment pending orders (like on-hold), use standard hold logic
            $order->update_meta_data('_shippo_fulfillment_hold', 'yes');
            $order->update_meta_data('_shippo_hold_reason', 'Awaiting customer payment');
            
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Shippo: Order {$order->get_id()} set to fulfillment hold - awaiting payment");
            }
        }
    }
    
    /**
     * Handle paid status
     */
    private function handle_paid_status($order) {
        // Release fulfillment hold
        $order->delete_meta_data('_shippo_fulfillment_hold');
        $order->delete_meta_data('_shippo_hold_reason');
        $order->update_meta_data('_shippo_ready_for_fulfillment', 'yes');
        
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log("Shippo: Order {$order->get_id()} released for fulfillment - payment received");
        }
    }
    
    /**
     * Handle shipped status
     */
    private function handle_shipped_status($order) {
        // Mark as fulfilled
        $order->update_meta_data('_shippo_fulfillment_complete', 'yes');
        $order->update_meta_data('_shippo_fulfilled_timestamp', current_time('timestamp'));
        
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log("Shippo: Order {$order->get_id()} marked as fulfilled and shipped");
        }
    }
    
    /**
     * Include invoiced orders in shipping calculations
     */
    public function include_invoiced_orders_in_shipping($packages) {
        // This ensures invoiced orders are still processed for shipping calculations
        // even though payment is pending
        return $packages;
    }
    
    /**
     * Process order for Shippo after checkout
     */
    public function process_order_for_shippo($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // If this is an invoiced order, set appropriate Shippo metadata
        if ($order->get_status() === 'invoiced') {
            $this->sync_order_with_shippo($order_id, '', 'invoiced', $order);
        }
    }
    
    /**
     * Notify Shippo API of status changes
     */
    private function notify_shippo_api($order, $shippo_status, $wc_status) {
        // Check if Shippo integration is active
        if (!class_exists('Shippo') && !function_exists('shippo_create_order')) {
            return;
        }
        
        try {
            // Get physical items that need shipping
            $physical_items = $this->get_order_items_for_shippo($order);
            
            // Skip orders with no physical items
            if (empty($physical_items)) {
                if (function_exists('twintack_manual_payments_log')) {
                    twintack_manual_payments_log("Shippo: Order {$order->get_id()} contains only digital products - skipping Shippo notification");
                }
                return;
            }
            
            // Prepare order data for Shippo
            $shippo_order_data = array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'status' => $wc_status,
                'fulfillment_status' => $shippo_status,
                'total' => $order->get_total(),
                'currency' => $order->get_currency(),
                'customer_email' => $order->get_billing_email(),
                'shipping_address' => array(
                    'name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
                    'street1' => $order->get_shipping_address_1(),
                    'street2' => $order->get_shipping_address_2(),
                    'city' => $order->get_shipping_city(),
                    'state' => $order->get_shipping_state(),
                    'zip' => $order->get_shipping_postcode(),
                    'country' => $order->get_shipping_country(),
                ),
                'items' => $physical_items
            );
            
            // Apply filters to allow other plugins to modify the data
            $shippo_order_data = apply_filters('twintack_shippo_order_data', $shippo_order_data, $order);
            
            // Trigger action for Shippo API integration
            do_action('twintack_notify_shippo_api', $shippo_order_data, $order);
            
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Shippo API notification sent for order {$order->get_id()} with " . count($physical_items) . " physical item(s)");
            }
            
        } catch (Exception $e) {
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Error notifying Shippo API for order {$order->get_id()}: " . $e->getMessage(), 'error');
            }
        }
    }
    
    /**
     * Get order items formatted for Shippo
     * Only includes physical products that need shipping
     */
    private function get_order_items_for_shippo($order) {
        $items = array();
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            // Skip virtual/digital products - they don't need shipping
            if (!$product || $product->is_virtual() || $product->is_downloadable()) {
                if (function_exists('twintack_manual_payments_log')) {
                    $product_name = $product ? $product->get_name() : $item->get_name();
                    $product_type = $product ? ($product->is_virtual() ? 'virtual' : 'downloadable') : 'missing product';
                    twintack_manual_payments_log("Shippo: Skipping {$product_type} product '{$product_name}' - no shipping required");
                }
                continue;
            }
            
            $items[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $item->get_total(),
                'sku' => $product->get_sku() ?: '',
                'weight' => $product->get_weight() ?: '',
                'dimensions' => array(
                    'length' => $product->get_length() ?: '',
                    'width' => $product->get_width() ?: '',
                    'height' => $product->get_height() ?: ''
                )
            );
            
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log("Shippo: Including physical product '{$item->get_name()}' (SKU: {$product->get_sku()})");
            }
        }
        
        return $items;
    }
    
    /**
     * Add Shippo meta box to order edit page
     */
    public function add_shippo_meta_box() {
        add_meta_box(
            'twintack-shippo-status',
            'Shippo Fulfillment Status',
            array($this, 'render_shippo_meta_box'),
            'shop_order',
            'side',
            'high'
        );
        
        // Also add for new WooCommerce HPOS orders
        add_meta_box(
            'twintack-shippo-status',
            'Shippo Fulfillment Status',
            array($this, 'render_shippo_meta_box'),
            'woocommerce_page_wc-orders',
            'side',
            'high'
        );
    }
    
    /**
     * Render Shippo meta box content
     */
    public function render_shippo_meta_box($post_or_order) {
        $order = is_object($post_or_order) ? $post_or_order : wc_get_order($post_or_order->ID);
        
        if (!$order) {
            echo '<p>No order data available.</p>';
            return;
        }
        
        $shippo_status = $order->get_meta('_shippo_fulfillment_status');
        $sync_timestamp = $order->get_meta('_shippo_sync_timestamp');
        $fulfillment_hold = $order->get_meta('_shippo_fulfillment_hold');
        
        echo '<div style="padding: 10px;">';
        echo '<p><strong>Current Shippo Status:</strong> ';
        if ($shippo_status) {
            echo '<span style="background: #0073aa; color: white; padding: 2px 8px; border-radius: 3px;">' . esc_html($shippo_status) . '</span>';
        } else {
            echo '<em>Not set</em>';
        }
        echo '</p>';
        
        if ($sync_timestamp) {
            echo '<p><strong>Last Sync:</strong> ' . date('Y-m-d H:i:s', $sync_timestamp) . '</p>';
        }
        
        if ($fulfillment_hold === 'yes') {
            $hold_reason = $order->get_meta('_shippo_hold_reason');
            echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 8px; border-radius: 3px; margin: 10px 0;">';
            echo '<strong>⚠️ Fulfillment Hold:</strong> ' . esc_html($hold_reason ?: 'Hold active');
            echo '</div>';
        }
        
        // Manual sync button
        echo '<p>';
        echo '<button type="button" class="button" onclick="twintackSyncShippo(' . $order->get_id() . ')">Force Sync with Shippo</button>';
        echo '</p>';
        
        echo '</div>';
        
        // Add JavaScript for manual sync
        ?>
        <script>
        function twintackSyncShippo(orderId) {
            if (confirm('Force sync this order with Shippo?')) {
                // Trigger manual sync via AJAX
                jQuery.post(ajaxurl, {
                    action: 'twintack_force_shippo_sync',
                    order_id: orderId,
                    nonce: '<?php echo wp_create_nonce('twintack_payment_processing'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Shippo sync completed successfully!');
                        location.reload();
                    } else {
                        alert('Sync failed: ' + response.data.message);
                    }
                });
            }
        }
        </script>
        <?php
    }
    
    /**
     * Save Shippo meta fields
     */
    public function save_shippo_meta_fields($post_id) {
        // This will be called by WooCommerce's save process
        // Additional meta field saving logic can be added here if needed
    }
    
    /**
     * Determine if an order is a manual order (created by admin) vs regular order (customer-placed)
     */
    private function is_manual_order($order) {
        // SIMPLIFIED: For now, treat ALL processing orders as manual orders needing immediate fulfillment
        // This ensures backwards compatibility while we debug the detection logic
        
        // Basic check - if it's marked as manual by our plugin, it's definitely manual
        if ($order->get_meta('_twintack_manual_order') === 'yes') {
            return true;
        }
        
        // For processing orders, be permissive and assume manual for now
        // Regular customer orders typically go: pending → processing → completed
        // Manual orders often start at processing
        $payment_method = $order->get_payment_method();
        $is_likely_manual = in_array($payment_method, array(
            'twintack_manual_payment',
            'twintack_invoice', 
            'igfw_invoice_gateway',
            'bacs',
            'cheque', 
            'cod',
            ''  // Empty payment method often indicates manual order
        ));
        
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log(
                "Order {$order->get_id()} manual detection: " . 
                ($is_likely_manual ? 'MANUAL' : 'REGULAR') . 
                " (Payment Method: '{$payment_method}')"
            );
        }
        
        return $is_likely_manual;
    }
} 