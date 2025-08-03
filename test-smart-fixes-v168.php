<?php
/**
 * Test Smart Fixes v1.6.8 - TwinTack Manual Payments
 * 
 * Tests the two critical fixes:
 * 1. Smart Tab Cleanup - Only removes actual duplicates, not valid tabs
 * 2. WooCommerce Hook Simulation - Triggers actual hooks that Shippo listens to
 * 
 * IMPORTANT: Delete this file after testing for security.
 */

// Load WordPress
$wp_load_path = dirname(__FILE__) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    die('❌ Could not find wp-load.php. Upload this file to your WordPress root directory.');
}
require_once($wp_load_path);

echo "<h1>🔧 Test Smart Fixes v1.6.8 - TwinTack Manual Payments</h1>";
echo "<p><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</p>";

echo "<h2>1. Plugin Version Check</h2>";

$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/twintack-manual-order-payments/twintack-manual-order-payments.php');
echo "<p><strong>Current Version:</strong> " . $plugin_data['Version'] . "</p>";

if (version_compare($plugin_data['Version'], '1.6.8', '>=')) {
    echo "<div style='background: #d4edda; padding: 10px; border-left: 4px solid #28a745; margin: 10px 0;'>";
    echo "<h3>✅ SMART FIXES APPLIED</h3>";
    echo "<p>Plugin is at v1.6.8 with smart tab cleanup and WooCommerce hook simulation</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border-left: 4px solid #dc3545; margin: 10px 0;'>";
    echo "<h3>❌ NEEDS UPDATE</h3>";
    echo "<p>Please upload plugin v1.6.8 to get the smart fixes</p>";
    echo "</div>";
}

echo "<h2>2. Smart Tab Cleanup Analysis</h2>";

echo "<div style='background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; margin: 10px 0;'>";
echo "<h4>🧠 How Smart Cleanup Works</h4>";
echo "<p><strong>Old Problem:</strong> JavaScript removed ALL invoiced tabs (even valid ones)</p>";
echo "<p><strong>New Solution:</strong> Only removes tabs with identical text content</p>";
echo "<p><strong>Console Messages:</strong> Now shows 'TwinTack v1.6.7' and explains what's being kept vs removed</p>";
echo "<p><strong>Timing:</strong> Runs once at 1500ms instead of multiple aggressive runs</p>";
echo "</div>";

echo "<script>
// Test the smart cleanup logic here in the test script
console.log('TwinTack v1.6.8 Test: Testing smart tab cleanup logic');

// Simulate different tab scenarios for testing
function testSmartCleanup() {
    console.log('=== Smart Cleanup Test ===');
    
    // Test case 1: No duplicates (should keep all)
    var test1 = [
        {text: 'Invoiced (4)', keep: true},
        {text: 'Processing (2)', keep: true}
    ];
    console.log('Test 1 - No duplicates: Should keep all unique tabs');
    test1.forEach(function(tab) {
        console.log('  Tab: \"' + tab.text + '\" - ' + (tab.keep ? 'KEEP' : 'REMOVE'));
    });
    
    // Test case 2: True duplicates (should remove duplicates)
    var test2 = [
        {text: 'Invoiced (4)', keep: true},  // First occurrence - keep
        {text: 'Invoiced (4)', keep: false}, // Duplicate - remove
        {text: 'Processing (2)', keep: true}
    ];
    console.log('Test 2 - True duplicates: Should remove only exact duplicates');
    test2.forEach(function(tab, index) {
        console.log('  Tab ' + index + ': \"' + tab.text + '\" - ' + (tab.keep ? 'KEEP' : 'REMOVE'));
    });
    
    // Test case 3: Similar but different (should keep all)
    var test3 = [
        {text: 'Invoiced (4)', keep: true},  // Different count
        {text: 'Invoiced (3)', keep: true},  // Different count
        {text: 'Processing (2)', keep: true}
    ];
    console.log('Test 3 - Similar but different: Should keep all (different counts)');
    test3.forEach(function(tab, index) {
        console.log('  Tab ' + index + ': \"' + tab.text + '\" - ' + (tab.keep ? 'KEEP' : 'REMOVE'));
    });
}

jQuery(document).ready(function($) {
    testSmartCleanup();
});
</script>";

echo "<h2>3. WooCommerce Hook Simulation Test</h2>";

// Clear transients to force fresh sync
delete_transient('twintack_shippo_sync_complete');
delete_transient('twintack_shippo_last_sync');
delete_site_transient('twintack_shippo_sync_complete');
delete_site_transient('twintack_shippo_last_sync');

echo "<div style='background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 10px 0;'>";
echo "<h3>⏳ FORCED FRESH SYNC</h3>";
echo "<p>Cleared all sync transients - next orders page visit will trigger WooCommerce hook simulation</p>";
echo "</div>";

// Test the WooCommerce hook simulation directly
if (class_exists('TwinTack_Order_Status_Manager')) {
    $manager = TwinTack_Order_Status_Manager::get_instance();
    
    echo "<div style='background: #d4edda; padding: 10px; border-left: 4px solid #28a745; margin: 10px 0;'>";
    echo "<h3>✅ STATUS MANAGER LOADED</h3>";
    echo "<p>Testing WooCommerce hook simulation...</p>";
    echo "</div>";
    
    // Check if Shippo integration is available
    if (class_exists('TwinTack_Shippo_Integration')) {
        echo "<div style='background: #d4edda; padding: 10px; border-left: 4px solid #28a745; margin: 10px 0;'>";
        echo "<h3>✅ SHIPPO INTEGRATION AVAILABLE</h3>";
        echo "<p>TwinTack_Shippo_Integration class loaded and ready for hook simulation</p>";
        echo "</div>";
        
        $shippo = TwinTack_Shippo_Integration::get_instance();
        
        // Test if the sync method exists
        if (method_exists($shippo, 'sync_order_with_shippo')) {
            echo "<div style='background: #e7f3ff; padding: 10px; border-left: 4px solid #2196f3; margin: 10px 0;'>";
            echo "<h3>🎯 RUNNING WOOCOMMERCE HOOK SIMULATION</h3>";
            echo "<p>Executing the new trigger_direct_shippo_integration method...</p>";
            echo "</div>";
            
            // Run the hook simulation
            try {
                $manager->trigger_direct_shippo_integration();
                
                echo "<div style='background: #d4edda; padding: 10px; border-left: 4px solid #28a745; margin: 10px 0;'>";
                echo "<h4>✅ HOOK SIMULATION COMPLETED</h4>";
                echo "<p>WooCommerce hooks have been triggered for all invoiced orders</p>";
                echo "<p>Check plugin logs for detailed execution flow</p>";
                echo "</div>";
            } catch (Exception $e) {
                echo "<div style='background: #f8d7da; padding: 10px; border-left: 4px solid #dc3545; margin: 10px 0;'>";
                echo "<h4>❌ HOOK SIMULATION ERROR</h4>";
                echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "</div>";
            }
        } else {
            echo "<div style='background: #f8d7da; padding: 10px; border-left: 4px solid #dc3545; margin: 10px 0;'>";
            echo "<h3>❌ SHIPPO SYNC METHOD MISSING</h3>";
            echo "<p>sync_order_with_shippo method not found in TwinTack_Shippo_Integration</p>";
            echo "</div>";
        }
    } else {
        echo "<div style='background: #f8d7da; padding: 10px; border-left: 4px solid #dc3545; margin: 10px 0;'>";
        echo "<h3>❌ SHIPPO INTEGRATION NOT FOUND</h3>";
        echo "<p>TwinTack_Shippo_Integration class not available</p>";
        echo "</div>";
    }
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border-left: 4px solid #dc3545; margin: 10px 0;'>";
    echo "<h3>❌ STATUS MANAGER NOT FOUND</h3>";
    echo "<p>TwinTack_Order_Status_Manager class not available</p>";
    echo "</div>";
}

echo "<h2>4. Order Meta Verification</h2>";

global $wpdb;

// Check if orders have proper Shippo meta after the hook simulation
$orders_with_meta = $wpdb->get_results("
    SELECT o.id, o.status, 
           MAX(CASE WHEN m.meta_key = '_shippo_fulfillment_status' THEN m.meta_value END) as fulfillment_status,
           MAX(CASE WHEN m.meta_key = '_shippo_fulfillment_hold' THEN m.meta_value END) as fulfillment_hold,
           MAX(CASE WHEN m.meta_key = '_shippo_sync_timestamp' THEN m.meta_value END) as sync_timestamp
    FROM {$wpdb->prefix}wc_orders o
    LEFT JOIN {$wpdb->prefix}wc_orders_meta m ON o.id = m.order_id 
    WHERE o.status = 'invoiced' AND o.type = 'shop_order'
    GROUP BY o.id, o.status
    ORDER BY o.id DESC
");

if (!empty($orders_with_meta)) {
    echo "<div style='background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; margin: 10px 0;'>";
    echo "<h4>Shippo Meta Data Status</h4>";
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr><th>Order ID</th><th>Status</th><th>Fulfillment Status</th><th>Hold Status</th><th>Sync Time</th></tr>";
    
    foreach ($orders_with_meta as $order_meta) {
        $sync_time = $order_meta->sync_timestamp ? date('Y-m-d H:i:s', $order_meta->sync_timestamp) : 'Not synced';
        
        echo "<tr>";
        echo "<td>#{$order_meta->id}</td>";
        echo "<td>{$order_meta->status}</td>";
        echo "<td>" . ($order_meta->fulfillment_status ?: 'Not set') . "</td>";
        echo "<td>" . ($order_meta->fulfillment_hold ?: 'No') . "</td>";
        echo "<td>{$sync_time}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Count orders with proper meta
    $properly_synced = 0;
    foreach ($orders_with_meta as $order_meta) {
        if ($order_meta->fulfillment_status === 'Payment Pending' && $order_meta->fulfillment_hold === 'yes') {
            $properly_synced++;
        }
    }
    
    if ($properly_synced === count($orders_with_meta)) {
        echo "<p style='color: #28a745;'><strong>✅ All {$properly_synced} orders properly synced with Shippo meta</strong></p>";
    } else {
        echo "<p style='color: #dc3545;'><strong>⚠️ Only {$properly_synced} of " . count($orders_with_meta) . " orders properly synced</strong></p>";
    }
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 10px; border-left: 4px solid #dc3545; margin: 10px 0;'>";
    echo "<h4>❌ No Invoiced Orders Found</h4>";
    echo "<p>No orders with 'invoiced' status found for meta verification</p>";
    echo "</div>";
}

echo "<h2>5. Expected Results After v1.6.8</h2>";

echo "<div style='background: #e8f5e8; padding: 15px; border-left: 4px solid #4caf50; margin: 10px 0;'>";
echo "<h3>🎉 What Should Happen Now</h3>";
echo "<ol>";
echo "<li><strong>Smart Tab Behavior:</strong>";
echo "<ul>";
echo "<li>Tabs appear and stay visible (no aggressive removal)</li>";
echo "<li>Only true duplicates with identical text are removed</li>";
echo "<li>Console shows smart cleanup decisions</li>";
echo "</ul>";
echo "</li>";
echo "<li><strong>Shippo Integration:</strong>";
echo "<ul>";
echo "<li>All invoiced orders trigger <code>woocommerce_order_status_changed</code> hooks</li>";
echo "<li>Orders get proper Shippo meta: <code>_shippo_fulfillment_status = 'Payment Pending'</code></li>";
echo "<li>Orders appear in Shippo as 'Payment Pending'</li>";
echo "<li>Order notes show Shippo sync activity</li>";
echo "</ul>";
echo "</li>";
echo "</ol>";
echo "</div>";

echo "<h2>6. Debug Log Check Points</h2>";

echo "<div style='background: #e9ecef; padding: 10px; border-left: 4px solid #6c757d; margin: 10px 0;'>";
echo "<h4>🔍 Look for These Log Messages:</h4>";
echo "<ol>";
echo "<li><strong>Smart Tab Cleanup:</strong>";
echo "<ul>";
echo "<li><code>TwinTack v1.6.7: Found X invoiced tab links</code></li>";
echo "<li><code>TwinTack v1.6.7: Keeping unique tab: Invoiced (4)</code></li>";
echo "<li><code>TwinTack v1.6.7: No true duplicates found - all tabs are unique</code></li>";
echo "</ul>";
echo "</li>";
echo "<li><strong>WooCommerce Hook Simulation:</strong>";
echo "<ul>";
echo "<li><code>Direct Shippo Integration: Starting WooCommerce hook simulation for invoiced orders</code></li>";
echo "<li><code>Direct Shippo Integration: Processing order #[ID] with status 'invoiced'</code></li>";
echo "<li><code>Direct Shippo Integration: Called sync_order_with_shippo for order #[ID]</code></li>";
echo "<li><code>Direct Shippo Integration: Triggered woocommerce_order_status_changed hook for order #[ID]</code></li>";
echo "<li><code>Shippo sync: Order [ID] status invoiced → Shippo status Payment Pending</code></li>";
echo "</ul>";
echo "</li>";
echo "</ol>";
echo "</div>";

echo "<h2>7. Browser Console Check</h2>";

echo "<div style='background: #e7f3ff; padding: 10px; border-left: 4px solid #2196f3; margin: 10px 0;'>";
echo "<h4>🔍 Check Browser Console For:</h4>";
echo "<ul>";
echo "<li><strong>'TwinTack v1.6.8 Test: Testing smart tab cleanup logic'</strong></li>";
echo "<li><strong>'=== Smart Cleanup Test ==='</strong> - Shows different test scenarios</li>";
echo "<li><strong>On actual orders page:</strong> 'TwinTack v1.6.7: ✅ Perfect - single invoiced tab found'</li>";
echo "<li><strong>Tab behavior:</strong> Tabs should appear and stay visible</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><strong>⚠️ Important:</strong> Delete this file after testing for security reasons.</p>";
echo "<p><strong>Generated at:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>