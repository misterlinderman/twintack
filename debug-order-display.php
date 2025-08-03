<?php
/**
 * Debug Order Display - TwinTack Manual Payments
 * 
 * This script investigates why orders are not displaying in the admin list
 * even when the query is correctly formed.
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

echo "<h1>🔍 Debug Order Display - TwinTack Manual Payments</h1>";
echo "<p><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</p>";

echo "<h2>1. Environment Check</h2>";
echo "<p><strong>WordPress:</strong> " . get_bloginfo('version') . "</p>";
echo "<p><strong>WooCommerce:</strong> " . (function_exists('WC') ? '✅ v' . WC()->version : '❌ Not available') . "</p>";
echo "<p><strong>HPOS Enabled:</strong> " . (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && 
    method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled') &&
    Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? '✅ Yes' : '❌ No') . "</p>";

echo "<h2>2. Direct Database Query</h2>";
global $wpdb;

// Get all invoiced orders directly from database
$hpos_table = $wpdb->prefix . 'wc_orders';
$invoiced_orders = $wpdb->get_results($wpdb->prepare("
    SELECT id, status, type, total, date_created_gmt 
    FROM {$hpos_table} 
    WHERE status = %s 
    AND type = %s
    ORDER BY date_created_gmt DESC
", 'wc-invoiced', 'shop_order'));

echo "<p><strong>Direct HPOS Query Results:</strong></p>";
if ($invoiced_orders) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Order ID</th><th>Status</th><th>Type</th><th>Total</th><th>Date Created</th></tr>";
    foreach ($invoiced_orders as $order) {
        echo "<tr>";
        echo "<td>#{$order->id}</td>";
        echo "<td>{$order->status}</td>";
        echo "<td>{$order->type}</td>";
        echo "<td>{$order->total}</td>";
        echo "<td>{$order->date_created_gmt}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ No invoiced orders found in direct database query</p>";
}

echo "<h2>3. WooCommerce Query Test</h2>";

// Test WooCommerce query methods
if (function_exists('wc_get_orders')) {
    $wc_orders = wc_get_orders(array(
        'status' => 'invoiced',
        'limit' => -1,
        'return' => 'ids'
    ));
    
    echo "<p><strong>wc_get_orders() with status 'invoiced':</strong> ";
    if ($wc_orders) {
        echo "✅ Found " . count($wc_orders) . " orders: " . implode(', ', array_map(function($id) { return "#$id"; }, $wc_orders));
    } else {
        echo "❌ No orders found";
    }
    echo "</p>";
    
    // Try with wc- prefix
    $wc_orders_prefixed = wc_get_orders(array(
        'status' => 'wc-invoiced',
        'limit' => -1,
        'return' => 'ids'
    ));
    
    echo "<p><strong>wc_get_orders() with status 'wc-invoiced':</strong> ";
    if ($wc_orders_prefixed) {
        echo "✅ Found " . count($wc_orders_prefixed) . " orders: " . implode(', ', array_map(function($id) { return "#$id"; }, $wc_orders_prefixed));
    } else {
        echo "❌ No orders found";
    }
    echo "</p>";
}

echo "<h2>4. Admin Query Simulation</h2>";

// Simulate the admin query that WooCommerce uses
if (class_exists('Automattic\WooCommerce\Admin\Overrides\OrdersTableQuery')) {
    try {
        // Create a query similar to what WooCommerce admin uses
        $query_args = array(
            'type' => 'shop_order',
            'status' => array('wc-invoiced'),
            'limit' => -1,
            'return' => 'ids'
        );
        
        $admin_query_orders = wc_get_orders($query_args);
        
        echo "<p><strong>Admin-style query simulation:</strong> ";
        if ($admin_query_orders) {
            echo "✅ Found " . count($admin_query_orders) . " orders: " . implode(', ', array_map(function($id) { return "#$id"; }, $admin_query_orders));
        } else {
            echo "❌ No orders found in admin simulation";
        }
        echo "</p>";
        
    } catch (Exception $e) {
        echo "<p>❌ Admin query simulation failed: " . $e->getMessage() . "</p>";
    }
}

echo "<h2>5. Order Status Investigation</h2>";

// Check specific orders mentioned in conversation
$test_orders = array(1569, 1582, 1583, 1604);
foreach ($test_orders as $order_id) {
    echo "<h3>Order #{$order_id}</h3>";
    
    $order = wc_get_order($order_id);
    if ($order) {
        echo "<p><strong>WooCommerce Object:</strong> ✅ Found</p>";
        echo "<p><strong>Status (get_status()):</strong> " . $order->get_status() . "</p>";
        echo "<p><strong>Status (with prefix):</strong> wc-" . $order->get_status() . "</p>";
        echo "<p><strong>Type:</strong> " . $order->get_type() . "</p>";
        echo "<p><strong>Date Created:</strong> " . $order->get_date_created()->format('Y-m-d H:i:s') . "</p>";
        echo "<p><strong>Total:</strong> $" . $order->get_total() . "</p>";
        
        // Check if order would be included in admin list
        $meta_screen_filter = $order->get_meta('_edit_lock', true);
        echo "<p><strong>Edit Lock Meta:</strong> " . ($meta_screen_filter ? $meta_screen_filter : 'None') . "</p>";
        
        // Check data store
        $data_store = $order->get_data_store();
        echo "<p><strong>Data Store:</strong> " . get_class($data_store) . "</p>";
        
    } else {
        echo "<p>❌ Order not found via wc_get_order()</p>";
        
        // Check if it exists in database
        $db_order = $wpdb->get_row($wpdb->prepare("
            SELECT id, status, type FROM {$hpos_table} WHERE id = %d
        ", $order_id));
        
        if ($db_order) {
            echo "<p>✅ Found in database: Status = {$db_order->status}, Type = {$db_order->type}</p>";
        } else {
            echo "<p>❌ Not found in database either</p>";
        }
    }
}

echo "<h2>6. Admin List Filters Check</h2>";

// Check what filters might be affecting the admin list
$current_screen_filters = array();
if (function_exists('current_screen')) {
    $screen = current_screen();
    if ($screen) {
        echo "<p><strong>Current Screen:</strong> " . $screen->id . "</p>";
    }
}

// Check for any filters that might be hiding orders
echo "<p><strong>Active Filters on woocommerce_orders_table_query_clauses:</strong></p>";
$query_filters = $GLOBALS['wp_filter']['woocommerce_orders_table_query_clauses'] ?? null;
if ($query_filters) {
    foreach ($query_filters->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            if (is_array($callback['function'])) {
                $function_name = is_object($callback['function'][0]) ? get_class($callback['function'][0]) . '::' . $callback['function'][1] : implode('::', $callback['function']);
            } else {
                $function_name = $callback['function'];
            }
            echo "<p>Priority {$priority}: {$function_name}</p>";
        }
    }
} else {
    echo "<p>No filters found</p>";
}

echo "<h2>7. Manual Query Test with All Conditions</h2>";

// Test the exact query that should be running
$exact_query = "
SELECT sCO_wc_orders.id 
FROM {$hpos_table} sCO_wc_orders 
WHERE 1=1 
AND (sCO_wc_orders.status = 'wc-invoiced') 
AND (sCO_wc_orders.type = 'shop_order')
ORDER BY sCO_wc_orders.date_created_gmt DESC
";

$manual_results = $wpdb->get_col($exact_query);
echo "<p><strong>Manual Exact Query Results:</strong> ";
if ($manual_results) {
    echo "✅ Found " . count($manual_results) . " orders: " . implode(', ', array_map(function($id) { return "#$id"; }, $manual_results));
} else {
    echo "❌ No orders found";
}
echo "</p>";

echo "<h2>8. Cache Check</h2>";

// Check for any caching that might interfere
if (function_exists('wp_cache_flush')) {
    echo "<p><strong>Object Cache:</strong> ";
    try {
        wp_cache_flush();
        echo "✅ Flushed";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage();
    }
    echo "</p>";
}

// Check for transients
$transient_keys = array(
    'woocommerce_orders_count',
    'wc_admin_orders_count',
    'woocommerce_order_statuses'
);

foreach ($transient_keys as $key) {
    $transient = get_transient($key);
    if ($transient !== false) {
        echo "<p><strong>Transient '{$key}':</strong> Found (deleting...)</p>";
        delete_transient($key);
    }
}

echo "<h2>9. Recommendations</h2>";

if ($invoiced_orders && !empty($manual_results)) {
    echo "<div style='background: #ffe6e6; padding: 10px; margin: 10px 0; border-left: 4px solid #ff0000;'>";
    echo "<h4>🚨 Orders Exist But Not Displaying</h4>";
    echo "<p>The orders exist in the database and can be queried, but they're not showing in the admin list. This suggests:</p>";
    echo "<ul>";
    echo "<li><strong>Rendering Issue:</strong> WooCommerce might be filtering the results after the query</li>";
    echo "<li><strong>JavaScript/CSS Hiding:</strong> Orders might be loaded but hidden by frontend code</li>";
    echo "<li><strong>Permissions:</strong> Current user might not have permission to see these orders</li>";
    echo "<li><strong>Screen Options:</strong> Admin screen options might be filtering them out</li>";
    echo "</ul>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Check the browser developer tools Network tab when clicking 'Invoiced' to see what AJAX requests are made</li>";
    echo "<li>Inspect the HTML source to see if orders are present but hidden</li>";
    echo "<li>Check WooCommerce > Settings > Advanced > Features to ensure HPOS is properly configured</li>";
    echo "<li>Try with a different admin user account</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #ffeecc; padding: 10px; margin: 10px 0; border-left: 4px solid #ff9900;'>";
    echo "<h4>⚠️ No Orders Found</h4>";
    echo "<p>No invoiced orders were found in the database. This suggests:</p>";
    echo "<ul>";
    echo "<li>Orders might have a different status than expected</li>";
    echo "<li>Orders might be in wp_posts instead of wc_orders table</li>";
    echo "<li>The status registration might not be working correctly</li>";
    echo "</ul>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>⚠️ Important:</strong> Delete this file after debugging for security reasons.</p>";
echo "<p><strong>Generated at:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>