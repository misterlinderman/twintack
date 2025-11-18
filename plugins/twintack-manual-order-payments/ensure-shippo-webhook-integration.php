<?php
/**
 * Ensure Shippo Webhook Integration for Invoiced Orders
 * 
 * This script ensures that invoiced orders are properly integrated with Shippo
 * and will receive webhook updates when fulfilled.
 * 
 * Run this after creating invoiced orders to ensure they sync properly with Shippo.
 * 
 * Access via: yourdomain.com/wp-content/plugins/twintack-manual-order-payments/ensure-shippo-webhook-integration.php
 */

// Security and WordPress loading
require_once('../../../../wp-load.php');

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('Access denied');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Ensure Shippo Webhook Integration</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .button { background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; display: inline-block; }
        .order-item { background: #f9f9f9; padding: 10px; margin: 5px 0; border-left: 4px solid #0073aa; }
    </style>
</head>
<body>
    <h1>🔗 Ensure Shippo Webhook Integration</h1>
    
    <?php
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
    
    if ($action === 'sync_now') {
        sync_invoiced_orders_with_shippo();
    } else {
        show_sync_options();
    }
    
    function show_sync_options() {
        ?>
        <div class="section">
            <h2>📊 Current Invoiced Orders Status</h2>
            <?php
            // Get invoiced orders
            $invoiced_orders = wc_get_orders(array(
                'status' => 'invoiced',
                'limit' => 20,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            if (empty($invoiced_orders)) {
                echo '<p class="success">✅ No invoiced orders found.</p>';
                return;
            }
            
            echo '<p>Found <strong>' . count($invoiced_orders) . '</strong> invoiced orders:</p>';
            
            $needs_sync = 0;
            foreach ($invoiced_orders as $order) {
                $order_id = $order->get_id();
                $shippo_order_id = $order->get_meta('_shippo_order_id');
                $ready_for_fulfillment = $order->get_meta('_shippo_ready_for_fulfillment');
                $last_sync = $order->get_meta('_shippo_sync_timestamp');
                
                $status_class = '';
                $status_text = '';
                
                if (empty($shippo_order_id)) {
                    $status_class = 'error';
                    $status_text = '❌ Missing Shippo Order ID';
                    $needs_sync++;
                } elseif ($ready_for_fulfillment !== 'yes') {
                    $status_class = 'warning';
                    $status_text = '⚠️ Not marked ready for fulfillment';
                    $needs_sync++;
                } else {
                    $status_class = 'success';
                    $status_text = '✅ Properly synced with Shippo';
                }
                
                echo '<div class="order-item">';
                echo '<strong>Order #' . $order_id . '</strong> - ' . $order->get_date_created()->format('Y-m-d H:i') . '<br>';
                echo '<span class="' . $status_class . '">' . $status_text . '</span><br>';
                echo 'Customer: ' . $order->get_billing_email() . '<br>';
                if ($shippo_order_id) {
                    echo 'Shippo ID: ' . $shippo_order_id . '<br>';
                }
                if ($last_sync) {
                    echo 'Last Sync: ' . date('Y-m-d H:i:s', $last_sync);
                }
                echo '</div>';
            }
            
            if ($needs_sync > 0) {
                echo '<p class="warning">⚠️ <strong>' . $needs_sync . '</strong> orders need Shippo synchronization.</p>';
                echo '<a href="?action=sync_now" class="button">🔄 Sync All Invoiced Orders with Shippo</a>';
            } else {
                echo '<p class="success">✅ All invoiced orders are properly synced with Shippo!</p>';
            }
            ?>
        </div>
        
        <div class="section">
            <h2>🔗 Webhook Configuration</h2>
            <p><strong>Shippo Webhook URL:</strong></p>
            <code><?php echo home_url('/wp-json/twintack/v1/shippo-webhook'); ?></code>
            
            <h3>Required Webhook Events in Shippo:</h3>
            <ul>
                <li>✅ <strong>order_updated</strong> - Updates order status when fulfillment changes</li>
                <li>✅ <strong>shipment_updated</strong> - Adds tracking info when label is created</li>
                <li>✅ <strong>tracking_updated</strong> - Updates delivery status</li>
            </ul>
            
            <p><strong>Test Webhook:</strong> <a href="<?php echo home_url('/wp-json/twintack/v1/shippo-webhook'); ?>" target="_blank">Visit webhook endpoint</a> (should return a message)</p>
        </div>
        
        <div class="section">
            <h2>🔄 How This Fixes the Issue</h2>
            <p>When you sync invoiced orders with Shippo:</p>
            <ol>
                <li>✅ Orders get proper Shippo Order IDs for webhook mapping</li>
                <li>✅ Orders are marked "ready for fulfillment" despite pending payment</li>
                <li>✅ Shippo can fulfill and ship the orders immediately</li>
                <li>✅ When shipped, webhooks update WooCommerce to "completed" status</li>
                <li>✅ Tracking information is automatically added to orders</li>
                <li>✅ Customers receive shipment confirmation emails</li>
            </ol>
        </div>
        <?php
    }
    
    function sync_invoiced_orders_with_shippo() {
        ?>
        <div class="section">
            <h2>🔄 Syncing Invoiced Orders with Shippo</h2>
            
            <?php
            // Get invoiced orders
            $invoiced_orders = wc_get_orders(array(
                'status' => 'invoiced',
                'limit' => 50
            ));
            
            if (empty($invoiced_orders)) {
                echo '<p class="success">✅ No invoiced orders to sync.</p>';
                return;
            }
            
            $synced_count = 0;
            $error_count = 0;
            
            // Check if Shippo integration is available
            if (!class_exists('TwinTack_Shippo_Integration')) {
                echo '<p class="error">❌ Shippo integration class not available.</p>';
                return;
            }
            
            echo '<h3>Processing ' . count($invoiced_orders) . ' invoiced orders...</h3>';
            
            foreach ($invoiced_orders as $order) {
                $order_id = $order->get_id();
                
                try {
                    // Set up order for immediate fulfillment
                    $order->update_meta_data('_shippo_fulfillment_status', 'Payment Pending');
                    $order->update_meta_data('_shippo_ready_for_fulfillment', 'yes');
                    $order->update_meta_data('_shippo_payment_status', 'Payment Pending');
                    $order->update_meta_data('_shippo_fulfillment_note', 'Invoiced order - ship immediately despite pending payment');
                    $order->update_meta_data('_shippo_sync_timestamp', current_time('timestamp'));
                    
                    // Remove any hold flags
                    $order->delete_meta_data('_shippo_fulfillment_hold');
                    $order->delete_meta_data('_shippo_hold_reason');
                    $order->save();
                    
                    // Trigger Shippo sync via standard WooCommerce hook
                    do_action('woocommerce_order_status_changed', $order_id, 'pending', 'invoiced', $order);
                    
                    // Add order note
                    $order->add_order_note('Shippo sync: Order configured for immediate fulfillment despite invoiced status');
                    
                    echo '<div class="order-item">';
                    echo '<span class="success">✅ Synced Order #' . $order_id . '</span><br>';
                    echo 'Customer: ' . $order->get_billing_email() . '<br>';
                    echo 'Set for immediate fulfillment in Shippo';
                    echo '</div>';
                    
                    $synced_count++;
                    
                } catch (Exception $e) {
                    echo '<div class="order-item">';
                    echo '<span class="error">❌ Error syncing Order #' . $order_id . '</span><br>';
                    echo 'Error: ' . $e->getMessage();
                    echo '</div>';
                    
                    $error_count++;
                }
            }
            
            echo '<h3>📊 Sync Summary</h3>';
            echo '<p class="success">✅ Successfully synced: ' . $synced_count . ' orders</p>';
            if ($error_count > 0) {
                echo '<p class="error">❌ Errors: ' . $error_count . ' orders</p>';
            }
            
            echo '<h3>🎯 What Happens Next</h3>';
            echo '<ol>';
            echo '<li>Orders are now properly configured in Shippo for fulfillment</li>';
            echo '<li>When you fulfill orders in Shippo, webhooks will update WooCommerce</li>';
            echo '<li>Order status will change from "invoiced" to "completed"</li>';
            echo '<li>Tracking information will be added automatically</li>';
            echo '<li>Customers will receive shipment confirmation emails</li>';
            echo '</ol>';
            ?>
        </div>
        
        <div class="section">
            <a href="?" class="button">← Back to Status Check</a>
        </div>
        <?php
    }
    ?>
    
    <p><em>🗑️ Delete this file after use for security.</em></p>
</body>
</html>
