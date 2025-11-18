<?php
/**
 * Shippo API Debug Tool
 * Upload this to your site and visit it directly to debug Shippo API calls
 */

// Basic WordPress bootstrap
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

// Get the API client
if (class_exists('TwinTack_Shippo_API_Client')) {
    $api_client = TwinTack_Shippo_API_Client::get_instance();
} else {
    wp_die('TwinTack Shippo API Client not found. Make sure the plugin is active.');
}

$debug_results = array();
$test_order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($test_order_id && isset($_GET['test_sync'])) {
    $order = wc_get_order($test_order_id);
    if ($order) {
        // Capture all output
        ob_start();
        
        // Test the sync process
        $debug_results['order_data'] = array(
            'id' => $order->get_id(),
            'number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'customer_email' => $order->get_billing_email(),
            'date_created' => $order->get_date_created()->format('c')
        );
        
        // Create shippo order data to test the API call
        $shippo_order_data = array(
            'order_id' => $order->get_id(),
            'status' => $order->get_status(),
            'fulfillment_status' => 'PAID'
        );
        
        // Clear the transient to force a sync for debugging
        delete_transient('twintack_shippo_sync_complete');
        twintack_manual_payments_log("DEBUG SCRIPT: Cleared Shippo sync transient for forced test.");
        
        twintack_manual_payments_log("DEBUG SCRIPT: Testing sync for order #{$order->get_id()} with data: " . json_encode($shippo_order_data));
        
        // Call the API method and capture any output
        $result = $api_client->send_order_to_shippo($shippo_order_data, $order);
        
        twintack_manual_payments_log("DEBUG SCRIPT: Test sync completed with result: " . ($result ? 'SUCCESS' : 'FAILED'));
        
        $debug_output = ob_get_clean();
        $debug_results['api_result'] = $result;
        $debug_results['debug_output'] = $debug_output;
    }
}

// Test basic API connectivity
$connectivity_test = array();
if (isset($_GET['test_connectivity'])) {
    try {
        // Use reflection to access the private make_api_request method for testing
        $reflection = new ReflectionClass($api_client);
        $method = $reflection->getMethod('make_api_request');
        $method->setAccessible(true);
        
        // Test 1: GET request to orders endpoint (main API we're using)
        $connectivity_test['orders_list'] = $method->invoke($api_client, 'GET', 'orders/?results=1');
        
        // Test 2: GET request to addresses endpoint
        try {
            $connectivity_test['addresses_list'] = $method->invoke($api_client, 'GET', 'addresses/?results=1');
        } catch (Exception $e) {
            $connectivity_test['addresses_error'] = $e->getMessage();
        }
        
        // Test 3: GET request to shipments endpoint (alternative API)
        try {
            $connectivity_test['shipments_list'] = $method->invoke($api_client, 'GET', 'shipments/?results=1');
        } catch (Exception $e) {
            $connectivity_test['shipments_error'] = $e->getMessage();
        }
        
    } catch (Exception $e) {
        $connectivity_test['error'] = $e->getMessage();
    }
}

// Get recent invoiced orders for testing
$recent_orders = wc_get_orders(array(
    'limit' => 10,
    'status' => array('invoiced', 'processing', 'completed'),
    'orderby' => 'date',
    'order' => 'DESC'
));

?>
<!DOCTYPE html>
<html>
<head>
    <title>Shippo API Debug Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .debug-section { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007cba; }
        .error { border-left-color: #d63638; background: #fcf2f2; }
        .success { border-left-color: #00a32a; background: #f0f8f0; }
        pre { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        .order-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin: 20px 0; }
        .order-card { background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
        .btn { background: #007cba; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; display: inline-block; margin: 5px; }
        .btn:hover { background: #005a87; color: white; }
        .api-info { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚢 Shippo API Debug Tool</h1>
        <p>This tool helps debug Shippo API integration issues by showing exactly what data is being sent.</p>
        
        <?php
        // Show current API configuration
        $debug_info = $api_client->get_debug_info();
        ?>
        
        <div class="debug-section <?php echo $debug_info['api_token_configured'] ? 'success' : 'error'; ?>">
            <h3>🔑 API Configuration</h3>
            <p><strong>API Token:</strong> <?php echo $debug_info['api_token_configured'] ? '✅ Configured' : '❌ Not configured'; ?></p>
            <p><strong>Token Preview:</strong> <?php echo esc_html($debug_info['api_token_preview']); ?></p>
            <p><strong>Token Type:</strong> <?php echo esc_html($debug_info['token_type']); ?></p>
            <p><strong>API Base URL:</strong> <?php echo esc_html($debug_info['api_base_url']); ?></p>
            <p><strong>WordPress Remote Requests:</strong> <?php echo $debug_info['wp_remote_available'] ? '✅ Available' : '❌ Not available'; ?></p>
            <p><strong>JSON Functions:</strong> <?php echo $debug_info['json_functions_available'] ? '✅ Available' : '❌ Not available'; ?></p>
            <p><strong>Current Time:</strong> <?php echo esc_html($debug_info['current_time']); ?></p>
            <p><strong>PHP Version:</strong> <?php echo esc_html($debug_info['php_version']); ?></p>
            <p><strong>WordPress Version:</strong> <?php echo esc_html($debug_info['wordpress_version']); ?></p>
        </div>
        
        <div class="api-info">
            <h3>📋 API Documentation Needed</h3>
            <p>To better debug this issue, please share the following Shippo API documentation:</p>
            <ul>
                <li><strong>Orders API Endpoint:</strong> What's the correct endpoint for creating/updating orders?</li>
                <li><strong>Required Fields:</strong> What fields are required for order creation?</li>
                <li><strong>Order Status Values:</strong> What are the valid order status values?</li>
                <li><strong>Response Format:</strong> What does a successful response look like?</li>
                <li><strong>Error Format:</strong> How are errors returned?</li>
            </ul>
        </div>
        
        <h2>🧪 Test Orders</h2>
        <div class="order-list">
            <?php foreach ($recent_orders as $order): ?>
                <div class="order-card">
                    <h4>Order #<?php echo $order->get_order_number(); ?></h4>
                    <p><strong>Status:</strong> <?php echo ucfirst($order->get_status()); ?></p>
                    <p><strong>Total:</strong> <?php echo $order->get_formatted_order_total(); ?></p>
                    <p><strong>Customer:</strong> <?php echo $order->get_billing_email(); ?></p>
                    <p><strong>Date:</strong> <?php echo $order->get_date_created()->format('Y-m-d H:i:s'); ?></p>
                    <a href="?order_id=<?php echo $order->get_id(); ?>&test_sync=1" class="btn">🚢 Test Sync</a>
                    <a href="<?php echo admin_url('post.php?post=' . $order->get_id() . '&action=edit'); ?>" class="btn">✏️ Edit Order</a>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (!empty($debug_results)): ?>
            <h2>🔍 Debug Results</h2>
            
            <div class="debug-section">
                <h3>📦 Order Data Being Processed</h3>
                <pre><?php echo json_encode($debug_results['order_data'], JSON_PRETTY_PRINT); ?></pre>
            </div>
            
            <div class="debug-section">
                <h3>🔄 API Call Result</h3>
                <pre><?php echo json_encode($debug_results['api_result'], JSON_PRETTY_PRINT); ?></pre>
            </div>
            
            <?php if (!empty($debug_results['debug_output'])): ?>
                <div class="debug-section">
                    <h3>📝 Debug Output</h3>
                    <pre><?php echo esc_html($debug_results['debug_output']); ?></pre>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <h2>📊 Recent Plugin Logs</h2>
        <div class="debug-section">
            <?php
            // Try to read the debug log if it exists
            $log_file = WP_CONTENT_DIR . '/debug.log';
            if (file_exists($log_file)) {
                $log_content = file_get_contents($log_file);
                $log_lines = explode("\n", $log_content);
                $shippo_logs = array_filter($log_lines, function($line) {
                    return strpos($line, 'Shippo') !== false || strpos($line, 'TwinTack') !== false;
                });
                $recent_logs = array_slice(array_reverse($shippo_logs), 0, 20);
                
                if (!empty($recent_logs)) {
                    echo '<h4>Recent Shippo-related logs:</h4>';
                    echo '<pre>' . esc_html(implode("\n", $recent_logs)) . '</pre>';
                } else {
                    echo '<p>No Shippo-related logs found in debug.log</p>';
                }
            } else {
                echo '<p>WordPress debug.log not found. Enable WP_DEBUG_LOG in wp-config.php to see detailed logs.</p>';
            }
            ?>
        </div>
        
        <?php if (!empty($connectivity_test)): ?>
            <h2>🌐 API Connectivity Test Results</h2>
            
            <div class="debug-section">
                <h3>📡 Raw API Test Results</h3>
                <pre><?php echo json_encode($connectivity_test, JSON_PRETTY_PRINT); ?></pre>
            </div>
        <?php endif; ?>
        
        <h2>🧪 Manual API Tests</h2>
        <div class="debug-section">
            <a href="?test_connectivity=1" class="btn">🌐 Test API Connectivity</a>
            <p><em>This will test basic API endpoints to verify connectivity and authentication.</em></p>
        </div>
        
        <div class="debug-section">
            <h3>🔗 Useful Links</h3>
            <a href="<?php echo admin_url('admin.php?page=twintack-shippo-api'); ?>" class="btn">⚙️ Shippo Settings</a>
            <a href="<?php echo admin_url('admin.php?page=wc-orders'); ?>" class="btn">📋 Orders List</a>
            <a href="<?php echo admin_url('tools.php?page=twintack_debug'); ?>" class="btn">🔧 TwinTack Debug</a>
        </div>
    </div>
</body>
</html>