<?php
/**
 * Test Order Status Fix - TwinTack Manual Payments
 * 
 * This script checks and fixes order statuses in the database to ensure
 * they're properly set for display in admin lists.
 * 
 * IMPORTANT: Delete this file after debugging for security.
 */

// Load WordPress
$wp_load_path = dirname(__FILE__) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    die('❌ Could not find wp-load.php. Upload this file to your WordPress root directory.');
}
require_once($wp_load_path);

// Ensure we're in admin context
if (!function_exists('WC') || !is_admin()) {
    define('WP_ADMIN', true);
}

echo "<h1>🔧 Test Order Status Fix - TwinTack Manual Payments</h1>";
echo "<p><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</p>";

echo "<h2>1. Current Order Status Analysis</h2>";

$test_orders = array(1569, 1582, 1583, 1604);
global $wpdb;

foreach ($test_orders as $order_id) {
    echo "<h3>Order #{$order_id}</h3>";
    
    // Get WooCommerce order object
    $order = wc_get_order($order_id);
    if (!$order) {
        echo "<p>❌ Order not found via wc_get_order()</p>";
        continue;
    }
    
    echo "<p><strong>WC Status:</strong> " . $order->get_status() . "</p>";
    
    // Check wp_posts status
    $post_data = $wpdb->get_row($wpdb->prepare("
        SELECT post_status, post_type FROM {$wpdb->posts} WHERE ID = %d
    ", $order_id));
    
    if ($post_data) {
        echo "<p><strong>wp_posts status:</strong> {$post_data->post_status}</p>";
        echo "<p><strong>wp_posts type:</strong> {$post_data->post_type}</p>";
        
        // Check if status needs fixing
        $wc_status = $order->get_status();
        $expected_post_status = 'wc-' . $wc_status;
        
        if ($post_data->post_status !== $expected_post_status) {
            echo "<div style='background: #ffeecc; padding: 10px; border-left: 4px solid #ff9900; margin: 10px 0;'>";
            echo "<p><strong>⚠️ Status Mismatch:</strong></p>";
            echo "<p>WooCommerce says: <code>{$wc_status}</code></p>";
            echo "<p>Database has: <code>{$post_data->post_status}</code></p>";
            echo "<p>Should be: <code>{$expected_post_status}</code></p>";
            
            // Offer to fix
            if (isset($_GET['fix']) && $_GET['fix'] === 'true') {
                $result = $wpdb->update(
                    $wpdb->posts,
                    array('post_status' => $expected_post_status),
                    array('ID' => $order_id),
                    array('%s'),
                    array('%d')
                );
                
                if ($result !== false) {
                    echo "<p>✅ <strong>FIXED:</strong> Updated post_status to <code>{$expected_post_status}</code></p>";
                } else {
                    echo "<p>❌ <strong>FAILED:</strong> Could not update post_status</p>";
                }
            } else {
                echo "<p><a href='?fix=true' style='background: #0073aa; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>Fix This Order</a></p>";
            }
            echo "</div>";
        } else {
            echo "<p>✅ <strong>Status OK:</strong> Database matches WooCommerce</p>";
        }
    } else {
        echo "<p>❌ Not found in wp_posts table</p>";
    }
    
    // Check order meta
    $status_meta = get_post_meta($order_id, '_order_status', true);
    echo "<p><strong>_order_status meta:</strong> " . ($status_meta ?: 'Not set') . "</p>";
    
    echo "<hr>";
}

echo "<h2>2. Query Test After Fixes</h2>";

if (isset($_GET['fix']) && $_GET['fix'] === 'true') {
    echo "<p>Testing queries after status fixes...</p>";
    
    // Test legacy query
    $legacy_query = $wpdb->get_results("
        SELECT ID, post_status, post_type 
        FROM {$wpdb->posts} 
        WHERE post_type = 'shop_order' 
        AND (post_status = 'wc-invoiced' OR post_status = 'invoiced')
        ORDER BY ID
    ");
    
    echo "<p><strong>Legacy Query Results:</strong></p>";
    if ($legacy_query) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Order ID</th><th>Post Status</th><th>Post Type</th></tr>";
        foreach ($legacy_query as $row) {
            echo "<tr><td>#{$row->ID}</td><td>{$row->post_status}</td><td>{$row->post_type}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p>❌ No orders found in legacy query</p>";
    }
    
    // Test WooCommerce query
    $wc_orders = wc_get_orders(array(
        'status' => 'invoiced',
        'limit' => -1,
        'return' => 'ids'
    ));
    
    echo "<p><strong>WooCommerce Query Results:</strong> ";
    if ($wc_orders) {
        echo "✅ Found " . count($wc_orders) . " orders: " . implode(', ', array_map(function($id) { return "#$id"; }, $wc_orders));
    } else {
        echo "❌ No orders found";
    }
    echo "</p>";
}

echo "<h2>3. Manual Query Verification</h2>";

// Test all possible status combinations
$status_queries = array(
    'wc-invoiced' => "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status = 'wc-invoiced'",
    'invoiced' => "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status = 'invoiced'",
    'both' => "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND (post_status = 'wc-invoiced' OR post_status = 'invoiced')"
);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Status Query</th><th>Count</th></tr>";
foreach ($status_queries as $label => $query) {
    $count = $wpdb->get_var($query);
    echo "<tr><td>{$label}</td><td>{$count}</td></tr>";
}
echo "</table>";

echo "<h2>4. Recommendations</h2>";

if (!isset($_GET['fix'])) {
    echo "<div style='background: #e6f3ff; padding: 15px; border-left: 4px solid #0073aa; margin: 10px 0;'>";
    echo "<h4>🔧 Fix Status Mismatches</h4>";
    echo "<p>If you see status mismatches above, click the fix buttons or use the link below to fix all at once:</p>";
    echo "<p><a href='?fix=true' style='background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>🔧 Fix All Status Issues</a></p>";
    echo "</div>";
}

echo "<div style='background: #fff2cc; padding: 15px; border-left: 4px solid #d4a017; margin: 10px 0;'>";
echo "<h4>⚡ Next Steps</h4>";
echo "<ol>";
echo "<li>Fix any status mismatches above</li>";
echo "<li>Upload the updated plugin (v1.6.0)</li>";
echo "<li>Test the admin order list and 'Invoiced' filter</li>";
echo "<li>Consider migrating to HPOS for better performance</li>";
echo "</ol>";
echo "</div>";

echo "<hr>";
echo "<p><strong>⚠️ Important:</strong> Delete this file after debugging for security reasons.</p>";
echo "<p><strong>Generated at:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>