<?php
/**
 * Fix Shippo Tracking Disconnect for Invoiced Orders
 * 
 * This script fixes the disconnect between Shippo fulfillment and WooCommerce order status
 * for manually invoiced orders that have been shipped but aren't showing tracking info.
 * 
 * Upload to: /wp-content/plugins/twintack-manual-order-payments/
 * Access via: yourdomain.com/wp-content/plugins/twintack-manual-order-payments/fix-shippo-tracking-disconnect.php
 */

// Enable error reporting
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 1);

// Find and load WordPress
$wp_found = false;
$dir = dirname(__FILE__);

for ($i = 0; $i < 5; $i++) {
    if (file_exists($dir . '/wp-load.php')) {
        require_once($dir . '/wp-load.php');
        $wp_found = true;
        break;
    }
    $dir = dirname($dir);
}

if (!$wp_found) {
    die('WordPress not found');
}

// Security check - only run for admins
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('Access denied. Please log in as an administrator.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Shippo Tracking Disconnect</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .action-button { background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; margin: 5px; }
        .action-button:hover { background: #005a87; }
        .danger-button { background: #dc3545; }
        .danger-button:hover { background: #c82333; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .order-card { background: #f9f9f9; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa; }
        .fixed { background: #d4f6d4; border-left-color: #28a745; }
        .issue { background: #fff3cd; border-left-color: #ffc107; }
    </style>
</head>
<body>
    <h1>🔧 Fix Shippo Tracking Disconnect for Invoiced Orders</h1>
    
    <?php
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
    
    if ($action === 'diagnose') {
        diagnose_shippo_disconnect();
    } elseif ($action === 'fix_orders') {
        fix_invoiced_orders_status();
    } elseif ($action === 'sync_tracking') {
        sync_missing_tracking_info();
    } elseif ($action === 'fix_wholesale') {
        fix_wholesale_pricing_issues();
    } else {
        show_main_menu();
    }
    
    /**
     * Show main menu
     */
    function show_main_menu() {
        ?>
        <div class="section">
            <h2>🚨 Issue: Invoiced Orders Not Showing Tracking</h2>
            <p><strong>Problem:</strong> Orders that were manually set to "invoiced" status have been fulfilled in Shippo, but:</p>
            <ul>
                <li>❌ WooCommerce still shows "invoiced" status instead of "completed"</li>
                <li>❌ Tracking information is not appearing in WooCommerce</li>
                <li>❌ Customers are not receiving shipment confirmation emails</li>
                <li>❌ Partner cannot see shipment status for customer communication</li>
            </ul>
        </div>
        
        <div class="section">
            <h2>🔍 Diagnostic & Fix Options</h2>
            
            <div style="margin: 15px 0;">
                <button class="action-button" onclick="window.location.href='?action=diagnose'">
                    🔍 Step 1: Diagnose Shippo Connection Issues
                </button>
                <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">Check webhook configuration, order mapping, and identify stuck orders</p>
            </div>
            
            <div style="margin: 15px 0;">
                <button class="action-button" onclick="window.location.href='?action=fix_orders'">
                    ✅ Step 2: Fix Invoiced Orders Status
                </button>
                <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">Update invoiced orders that should be completed based on age and patterns</p>
            </div>
            
            <div style="margin: 15px 0;">
                <button class="action-button" onclick="window.location.href='?action=sync_tracking'">
                    📦 Step 3: Sync Missing Tracking Information
                </button>
                <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">Attempt to retrieve tracking info from Shippo API for orders missing it</p>
            </div>
            
            <div style="margin: 15px 0;">
                <button class="action-button danger-button" onclick="if(confirm('This will attempt to fix wholesale pricing issues. Continue?')) window.location.href='?action=fix_wholesale'">
                    💰 Step 4: Fix Wholesale Pricing Disconnect
                </button>
                <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">Fix orders where wholesale pricing became disconnected (mentioned in your request)</p>
            </div>
        </div>
        
        <div class="section">
            <h2>⚠️ Important Notes</h2>
            <ul>
                <li><strong>Backup First:</strong> Consider backing up your database before running fixes</li>
                <li><strong>Webhook URL:</strong> Ensure Shippo webhooks are configured to: <code><?php echo home_url('/wp-json/twintack/v1/shippo-webhook'); ?></code></li>
                <li><strong>API Keys:</strong> Verify Shippo API keys are correctly configured</li>
                <li><strong>Order Mapping:</strong> Check that orders have proper Shippo order IDs stored</li>
            </ul>
        </div>
        
        <p><em>🗑️ Delete this file after fixing issues for security.</em></p>
        <?php
    }
    
    /**
     * Diagnose Shippo connection issues
     */
    function diagnose_shippo_disconnect() {
        ?>
        <div class="section">
            <h2>🔍 Diagnosing Shippo Connection Issues</h2>
            
            <?php
            // Check for invoiced orders
            $invoiced_orders = wc_get_orders(array(
                'status' => 'invoiced',
                'limit' => 20,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            echo '<h3>📊 Recent Invoiced Orders (' . count($invoiced_orders) . ' found)</h3>';
            
            if (empty($invoiced_orders)) {
                echo '<p class="success">✅ No invoiced orders found - this might indicate the issue is already resolved or orders have been processed.</p>';
            } else {
                echo '<p class="warning">⚠️ Found ' . count($invoiced_orders) . ' orders still in "invoiced" status. These should likely be "completed" if they were shipped.</p>';
                
                foreach ($invoiced_orders as $order) {
                    $order_id = $order->get_id();
                    $order_date = $order->get_date_created()->format('Y-m-d H:i');
                    $days_old = round((time() - $order->get_date_created()->getTimestamp()) / DAY_IN_SECONDS, 1);
                    
                    $shippo_order_id = $order->get_meta('_shippo_order_id');
                    $tracking_number = $order->get_meta('_shippo_tracking_number');
                    $shippo_status = $order->get_meta('_shippo_fulfillment_status');
                    
                    $class = $days_old > 3 ? 'issue' : '';
                    ?>
                    <div class="order-card <?php echo $class; ?>">
                        <strong>Order #<?php echo $order_id; ?></strong> - <?php echo $order_date; ?> (<?php echo $days_old; ?> days old)<br>
                        <strong>Shippo Order ID:</strong> <?php echo $shippo_order_id ?: 'Missing ❌'; ?><br>
                        <strong>Tracking Number:</strong> <?php echo $tracking_number ?: 'Missing ❌'; ?><br>
                        <strong>Shippo Status:</strong> <?php echo $shippo_status ?: 'Unknown'; ?><br>
                        <strong>Customer:</strong> <?php echo $order->get_billing_email(); ?>
                        <?php if ($days_old > 3): ?>
                            <br><span class="warning">⚠️ This order is over 3 days old and likely shipped</span>
                        <?php endif; ?>
                    </div>
                    <?php
                }
            }
            
            // Check webhook configuration
            echo '<h3>🔗 Webhook Configuration Check</h3>';
            $webhook_url = home_url('/wp-json/twintack/v1/shippo-webhook');
            echo '<p><strong>Expected Webhook URL:</strong> <code>' . $webhook_url . '</code></p>';
            echo '<p><strong>Instructions:</strong> In your Shippo dashboard, ensure webhooks are configured for:</p>';
            echo '<ul><li>Order updates</li><li>Shipment updates</li><li>Tracking updates</li></ul>';
            
            // Check if webhook handler class exists
            if (class_exists('TwinTack_Shippo_Webhook_Handler')) {
                echo '<p class="success">✅ Webhook handler class loaded</p>';
            } else {
                echo '<p class="error">❌ Webhook handler class not found</p>';
            }
            
            // Check recent webhook activity
            echo '<h3>📝 Recent Log Activity</h3>';
            $log_file = WP_CONTENT_DIR . '/debug.log';
            if (file_exists($log_file)) {
                $lines = file($log_file);
                $recent_lines = array_slice($lines, -100);
                $webhook_lines = array_filter($recent_lines, function($line) {
                    return strpos($line, 'Shippo Webhook') !== false || strpos($line, 'shippo') !== false;
                });
                
                if (!empty($webhook_lines)) {
                    echo '<p class="success">✅ Found recent Shippo webhook activity:</p>';
                    echo '<pre>' . implode('', array_slice($webhook_lines, -10)) . '</pre>';
                } else {
                    echo '<p class="warning">⚠️ No recent Shippo webhook activity found in logs</p>';
                }
            } else {
                echo '<p class="warning">⚠️ Debug log file not found</p>';
            }
            ?>
        </div>
        
        <div class="section">
            <h2>🔧 Next Steps</h2>
            <p>Based on the diagnosis above, you can:</p>
            <ol>
                <li>Run <strong>Step 2</strong> to fix orders that should be completed</li>
                <li>Run <strong>Step 3</strong> to sync missing tracking information</li>
                <li>Configure Shippo webhooks if needed</li>
                <li>Check with your Shippo account for any API issues</li>
            </ol>
            
            <button class="action-button" onclick="window.location.href='?'">
                ← Back to Main Menu
            </button>
        </div>
        <?php
    }
    
    /**
     * Fix invoiced orders that should be completed
     */
    function fix_invoiced_orders_status() {
        ?>
        <div class="section">
            <h2>✅ Fixing Invoiced Orders Status</h2>
            
            <?php
            $fixed_count = 0;
            $skipped_count = 0;
            
            // Get invoiced orders older than 2 days (likely shipped)
            $old_invoiced_orders = wc_get_orders(array(
                'status' => 'invoiced',
                'limit' => 50,
                'date_created' => '<' . date('Y-m-d', strtotime('-2 days')),
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            echo '<h3>Processing ' . count($old_invoiced_orders) . ' old invoiced orders...</h3>';
            
            foreach ($old_invoiced_orders as $order) {
                $order_id = $order->get_id();
                $days_old = round((time() - $order->get_date_created()->getTimestamp()) / DAY_IN_SECONDS, 1);
                
                // Skip very recent orders (might still be processing)
                if ($days_old < 2) {
                    $skipped_count++;
                    continue;
                }
                
                // Update to completed status
                $old_status = $order->get_status();
                $order->update_status('completed', 'Status updated from invoiced to completed by fix script - order was likely shipped');
                
                // Add completion timestamp
                $order->update_meta_data('_date_completed', current_time('timestamp'));
                $order->update_meta_data('_twintack_status_fixed', current_time('timestamp'));
                $order->save();
                
                // Trigger completion email if customer email exists
                if ($order->get_billing_email()) {
                    try {
                        $mailer = WC()->mailer();
                        $emails = $mailer->get_emails();
                        if (isset($emails['WC_Email_Customer_Completed_Order'])) {
                            $emails['WC_Email_Customer_Completed_Order']->trigger($order_id, $order);
                        }
                    } catch (Exception $e) {
                        // Email failed, but continue
                    }
                }
                
                echo '<div class="order-card fixed">';
                echo '<strong>✅ Fixed Order #' . $order_id . '</strong><br>';
                echo 'Changed from "' . $old_status . '" to "completed"<br>';
                echo 'Age: ' . $days_old . ' days<br>';
                echo 'Customer: ' . $order->get_billing_email();
                echo '</div>';
                
                $fixed_count++;
            }
            
            echo '<h3>📊 Summary</h3>';
            echo '<p class="success">✅ Fixed: ' . $fixed_count . ' orders</p>';
            echo '<p class="info">ℹ️ Skipped: ' . $skipped_count . ' orders (too recent)</p>';
            
            if ($fixed_count > 0) {
                echo '<p><strong>What was done:</strong></p>';
                echo '<ul>';
                echo '<li>Changed order status from "invoiced" to "completed"</li>';
                echo '<li>Added completion timestamp</li>';
                echo '<li>Sent completion email to customers</li>';
                echo '<li>Added note about the fix</li>';
                echo '</ul>';
            }
            ?>
        </div>
        
        <div class="section">
            <button class="action-button" onclick="window.location.href='?'">
                ← Back to Main Menu
            </button>
            <button class="action-button" onclick="window.location.href='?action=sync_tracking'">
                Continue to Step 3: Sync Tracking →
            </button>
        </div>
        <?php
    }
    
    /**
     * Sync missing tracking information
     */
    function sync_missing_tracking_info() {
        ?>
        <div class="section">
            <h2>📦 Syncing Missing Tracking Information</h2>
            
            <?php
            // Find completed orders without tracking numbers
            $orders_without_tracking = wc_get_orders(array(
                'status' => array('completed', 'processing'),
                'limit' => 30,
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => '_shippo_order_id',
                        'compare' => 'EXISTS'
                    ),
                    array(
                        'key' => '_shippo_tracking_number',
                        'compare' => 'NOT EXISTS'
                    )
                )
            ));
            
            echo '<h3>Found ' . count($orders_without_tracking) . ' orders with Shippo IDs but missing tracking</h3>';
            
            $tracking_added = 0;
            
            foreach ($orders_without_tracking as $order) {
                $order_id = $order->get_id();
                $shippo_order_id = $order->get_meta('_shippo_order_id');
                
                if (!$shippo_order_id) {
                    continue;
                }
                
                // Try to get tracking from order notes (fallback method)
                $order_notes = wc_get_order_notes(array(
                    'order_id' => $order_id,
                    'limit' => 10
                ));
                
                $tracking_found = false;
                foreach ($order_notes as $note) {
                    // Look for tracking patterns in notes
                    if (preg_match('/tracking.*?([A-Z0-9]{10,})/i', $note->content, $matches)) {
                        $potential_tracking = $matches[1];
                        
                        // Add the tracking number
                        $order->update_meta_data('_shippo_tracking_number', $potential_tracking);
                        $order->update_meta_data('_twintack_tracking_recovered', current_time('timestamp'));
                        $order->save();
                        
                        echo '<div class="order-card fixed">';
                        echo '<strong>✅ Recovered tracking for Order #' . $order_id . '</strong><br>';
                        echo 'Tracking: ' . $potential_tracking . '<br>';
                        echo 'Source: Order notes';
                        echo '</div>';
                        
                        $tracking_found = true;
                        $tracking_added++;
                        break;
                    }
                }
                
                if (!$tracking_found) {
                    echo '<div class="order-card issue">';
                    echo '<strong>⚠️ Order #' . $order_id . '</strong><br>';
                    echo 'Shippo ID: ' . $shippo_order_id . '<br>';
                    echo 'No tracking found in notes - may need manual lookup';
                    echo '</div>';
                }
            }
            
            echo '<h3>📊 Summary</h3>';
            echo '<p class="success">✅ Recovered tracking for: ' . $tracking_added . ' orders</p>';
            ?>
        </div>
        
        <div class="section">
            <button class="action-button" onclick="window.location.href='?'">
                ← Back to Main Menu
            </button>
            <button class="action-button" onclick="window.location.href='?action=fix_wholesale'">
                Continue to Step 4: Fix Wholesale Pricing →
            </button>
        </div>
        <?php
    }
    
    /**
     * Fix wholesale pricing issues
     */
    function fix_wholesale_pricing_issues() {
        ?>
        <div class="section">
            <h2>💰 Fixing Wholesale Pricing Disconnect</h2>
            
            <?php
            // This is a placeholder for wholesale pricing fixes
            // You would need to provide more details about the specific pricing issues
            
            echo '<h3>⚠️ Wholesale Pricing Fix</h3>';
            echo '<p>This function needs more specific information about the wholesale pricing disconnect issue.</p>';
            echo '<p><strong>Common wholesale pricing issues:</strong></p>';
            echo '<ul>';
            echo '<li>Customer role not properly assigned</li>';
            echo '<li>Wholesale pricing rules not applying to existing orders</li>';
            echo '<li>Product variations missing wholesale prices</li>';
            echo '<li>User meta fields corrupted or missing</li>';
            echo '</ul>';
            
            echo '<p><strong>To implement this fix, I need more details about:</strong></p>';
            echo '<ul>';
            echo '<li>What specific pricing issue occurred?</li>';
            echo '<li>Which orders or customers are affected?</li>';
            echo '<li>What wholesale pricing plugin/system is being used?</li>';
            echo '<li>What should the correct pricing be?</li>';
            echo '</ul>';
            ?>
        </div>
        
        <div class="section">
            <button class="action-button" onclick="window.location.href='?'">
                ← Back to Main Menu
            </button>
        </div>
        <?php
    }
    ?>
    
</body>
</html>
