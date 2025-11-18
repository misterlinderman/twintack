<?php
/**
 * Add "Shipped (Unpaid)" Status for Better Invoice Workflow
 * 
 * This script adds a new order status to handle the workflow gap:
 * - Invoiced: Order created, invoice sent, awaiting payment
 * - Shipped (Unpaid): Order fulfilled and shipped, payment still pending
 * - Completed: Order shipped AND payment received
 * 
 * Access via: yourdomain.com/wp-content/plugins/twintack-manual-order-payments/add-shipped-unpaid-status.php
 */

// Load WordPress
require_once('../../../../wp-load.php');

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('Access denied');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Shipped (Unpaid) Status</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .button { background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .workflow-box { background: #f9f9f9; padding: 15px; margin: 10px 0; border-left: 4px solid #0073aa; }
        .status-demo { display: inline-block; padding: 5px 10px; margin: 2px; border-radius: 3px; color: white; font-weight: bold; }
        .status-invoiced { background: #ff9800; }
        .status-shipped-unpaid { background: #17a2b8; }
        .status-completed { background: #28a745; }
    </style>
</head>
<body>
    <h1>🚚 Add "Shipped (Unpaid)" Status</h1>
    
    <?php
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
    
    if ($action === 'add_status') {
        add_shipped_unpaid_status();
    } elseif ($action === 'update_orders') {
        update_existing_orders();
    } else {
        show_status_proposal();
    }
    
    function show_status_proposal() {
        ?>
        <div class="section">
            <h2>🎯 Perfect Workflow Solution</h2>
            <p><strong>Current Problem:</strong> Orders marked as "completed" when shipped lose payment tracking ability.</p>
            
            <div class="workflow-box">
                <h3>🚀 Proposed New Workflow:</h3>
                <ol>
                    <li>
                        <span class="status-demo status-invoiced">Invoiced</span>
                        <strong>Order created, invoice sent, awaiting payment</strong>
                        <ul>
                            <li>Customer receives invoice/payment link</li>
                            <li>Order ready for fulfillment in Shippo</li>
                            <li>Can be shipped immediately</li>
                        </ul>
                    </li>
                    
                    <li>
                        <span class="status-demo status-shipped-unpaid">Shipped (Unpaid)</span>
                        <strong>Order fulfilled and shipped, payment still pending</strong>
                        <ul>
                            <li>Shippo webhook updates status when shipped</li>
                            <li>Customer receives tracking information</li>
                            <li>Payment still pending - visible to admin</li>
                            <li>Partner can see shipment status</li>
                        </ul>
                    </li>
                    
                    <li>
                        <span class="status-demo status-completed">Completed</span>
                        <strong>Order shipped AND payment received</strong>
                        <ul>
                            <li>Admin marks as paid when customer pays</li>
                            <li>Order fully complete</li>
                            <li>Proper accounting and reporting</li>
                        </ul>
                    </li>
                </ol>
            </div>
        </div>
        
        <div class="section">
            <h2>✅ Benefits of This Approach</h2>
            <ul>
                <li>✅ <strong>Clear payment tracking:</strong> Always know what's paid vs unpaid</li>
                <li>✅ <strong>Proper customer communication:</strong> Customers get tracking info immediately</li>
                <li>✅ <strong>Partner visibility:</strong> Can see shipment status for customer communication</li>
                <li>✅ <strong>Workflow clarity:</strong> Each status has a clear meaning</li>
                <li>✅ <strong>Accounting accuracy:</strong> Shipped vs paid orders are distinct</li>
                <li>✅ <strong>Bulk operations still work:</strong> Can still bulk mark shipped orders as paid</li>
            </ul>
        </div>
        
        <div class="section">
            <h2>🛠️ Implementation Steps</h2>
            
            <div style="margin: 15px 0;">
                <a href="?action=add_status" class="button">
                    📝 Step 1: Add "Shipped (Unpaid)" Status
                </a>
                <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">Register the new status with WooCommerce</p>
            </div>
            
            <div style="margin: 15px 0;">
                <a href="?action=update_orders" class="button">
                    🔄 Step 2: Update Existing Orders
                </a>
                <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">Fix current "completed" orders that should be "shipped (unpaid)"</p>
            </div>
        </div>
        
        <div class="section">
            <h2>🔧 Technical Changes</h2>
            <p>This will modify:</p>
            <ul>
                <li><strong>Order Status Manager:</strong> Add new status registration</li>
                <li><strong>Shippo Webhook Handler:</strong> Map "SHIPPED" → "Shipped (Unpaid)" instead of "Completed"</li>
                <li><strong>Bulk Operations:</strong> Add action to mark "Shipped (Unpaid)" orders as "Completed"</li>
                <li><strong>Admin Interface:</strong> Update status options and colors</li>
            </ul>
        </div>
        <?php
    }
    
    function add_shipped_unpaid_status() {
        ?>
        <div class="section">
            <h2>📝 Adding "Shipped (Unpaid)" Status</h2>
            
            <?php
            // Add the new status to the order status manager
            $code_to_add = "
        register_post_status('wc-shipped-unpaid', array(
            'label'                     => _x('Shipped (Unpaid)', 'Order status', 'twintack-manual-payments'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Shipped (Unpaid) <span class=\"count\">(%s)</span>', 'Shipped (Unpaid) <span class=\"count\">(%s)</span>', 'twintack-manual-payments')
        ));";
            
            echo '<h3>✅ Status Registration Code Generated</h3>';
            echo '<p>Add this code to the <code>register_custom_order_statuses()</code> method in <code>class-order-status-manager.php</code>:</p>';
            echo '<pre style="background: #f5f5f5; padding: 15px; border-radius: 3px; overflow-x: auto;">' . htmlspecialchars($code_to_add) . '</pre>';
            
            $status_mapping_code = "
            // Add to add_custom_order_statuses() method:
            // Add shipped-unpaid status after processing
            if ('wc-processing' === \$key) {
                \$new_order_statuses['wc-shipped-unpaid'] = _x('Shipped (Unpaid)', 'Order status', 'twintack-manual-payments');
            }";
            
            echo '<h3>✅ Status Mapping Code</h3>';
            echo '<p>Add this to the <code>add_custom_order_statuses()</code> method:</p>';
            echo '<pre style="background: #f5f5f5; padding: 15px; border-radius: 3px; overflow-x: auto;">' . htmlspecialchars($status_mapping_code) . '</pre>';
            
            $webhook_mapping_code = "
        // In class-shippo-webhook-handler.php, map_shippo_status_to_wc() method:
        private function map_shippo_status_to_wc(\$shippo_status) {
            \$status_mapping = array(
                'PAID' => 'processing',
                'SHIPPED' => 'shipped-unpaid',  // Changed from 'completed'
                'DELIVERED' => 'shipped-unpaid', // Keep as shipped-unpaid until payment
                'CANCELLED' => 'cancelled',
                'REFUNDED' => 'refunded',
            );
            
            return isset(\$status_mapping[\$shippo_status]) ? \$status_mapping[\$shippo_status] : null;
        }";
            
            echo '<h3>✅ Webhook Mapping Update</h3>';
            echo '<p>Update the Shippo webhook mapping:</p>';
            echo '<pre style="background: #f5f5f5; padding: 15px; border-radius: 3px; overflow-x: auto;">' . htmlspecialchars($webhook_mapping_code) . '</pre>';
            
            echo '<h3>🎨 Admin Styling</h3>';
            echo '<p>Add CSS for the new status:</p>';
            echo '<pre style="background: #f5f5f5; padding: 15px; border-radius: 3px; overflow-x: auto;">';
            echo htmlspecialchars('.order-status.status-shipped-unpaid {
    background: #17a2b8;
    color: white;
    border-radius: 3px;
    padding: 3px 8px;
    font-weight: bold;
    font-size: 11px;
}
.widefat .column-order_status mark.shipped-unpaid {
    background: #17a2b8;
    color: white;
}');
            echo '</pre>';
            ?>
        </div>
        
        <div class="section">
            <h2>🔧 Manual Implementation Required</h2>
            <p class="warning">⚠️ The code above needs to be manually added to the plugin files. This ensures proper integration with the existing codebase.</p>
            
            <p><strong>Files to modify:</strong></p>
            <ol>
                <li><code>includes/class-order-status-manager.php</code> - Add status registration and mapping</li>
                <li><code>includes/class-shippo-webhook-handler.php</code> - Update status mapping</li>
                <li><code>includes/class-bulk-invoice-manager.php</code> - Add bulk action for shipped→completed</li>
            </ol>
            
            <a href="?" class="button">← Back to Overview</a>
            <a href="?action=update_orders" class="button">Continue to Step 2 →</a>
        </div>
        <?php
    }
    
    function update_existing_orders() {
        ?>
        <div class="section">
            <h2>🔄 Updating Existing Orders</h2>
            
            <?php
            // This would identify orders that should be "shipped (unpaid)" instead of "completed"
            echo '<h3>📊 Analysis of Current Orders</h3>';
            
            // Get recently completed orders that might actually be shipped but unpaid
            $recent_completed = wc_get_orders(array(
                'status' => 'completed',
                'limit' => 20,
                'date_created' => '>' . date('Y-m-d', strtotime('-30 days')),
                'meta_query' => array(
                    array(
                        'key' => '_twintack_manual_order',
                        'value' => 'yes',
                        'compare' => '='
                    )
                )
            ));
            
            echo '<p>Found <strong>' . count($recent_completed) . '</strong> recently completed manual orders that might need review.</p>';
            
            if (!empty($recent_completed)) {
                echo '<h4>🔍 Orders to Review:</h4>';
                foreach ($recent_completed as $order) {
                    $order_id = $order->get_id();
                    $tracking = $order->get_meta('_shippo_tracking_number');
                    $completion_date = $order->get_date_completed();
                    
                    echo '<div style="background: #fff3cd; padding: 10px; margin: 5px 0; border-left: 4px solid #ffc107;">';
                    echo '<strong>Order #' . $order_id . '</strong> - ' . $order->get_billing_email() . '<br>';
                    echo 'Completed: ' . ($completion_date ? $completion_date->format('Y-m-d H:i') : 'Unknown') . '<br>';
                    echo 'Tracking: ' . ($tracking ? $tracking : 'None') . '<br>';
                    echo '<strong>Action needed:</strong> Check if payment was actually received';
                    echo '</div>';
                }
                
                echo '<p class="info">💡 <strong>Recommendation:</strong> Manually review these orders to determine which should be changed to "Shipped (Unpaid)" status.</p>';
            }
            ?>
        </div>
        
        <div class="section">
            <h2>🎯 Next Steps</h2>
            <ol>
                <li><strong>Implement the status code</strong> from Step 1</li>
                <li><strong>Review the orders above</strong> and manually update statuses as needed</li>
                <li><strong>Test the new workflow</strong> with a new invoiced order</li>
                <li><strong>Update bulk operations</strong> to include "Mark Shipped Orders as Paid"</li>
            </ol>
            
            <a href="?" class="button">← Back to Overview</a>
        </div>
        <?php
    }
    ?>
    
    <p><em>🗑️ Delete this file after implementation for security.</em></p>
</body>
</html>
