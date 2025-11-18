<?php
/**
 * Fix HPOS Migration - TwinTack Manual Payments
 * 
 * This script investigates why orders are in wp_posts instead of wc_orders
 * and provides migration solutions.
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

echo "<h1>🔧 Fix HPOS Migration - TwinTack Manual Payments</h1>";
echo "<p><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</p>";

echo "<h2>1. Storage System Analysis</h2>";

// Check HPOS status
$hpos_enabled = class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && 
    method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled') &&
    Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

echo "<p><strong>HPOS Enabled:</strong> " . ($hpos_enabled ? '✅ Yes' : '❌ No') . "</p>";

global $wpdb;
$hpos_table = $wpdb->prefix . 'wc_orders';
$posts_table = $wpdb->prefix . 'posts';

// Check if HPOS table exists
$hpos_exists = $wpdb->get_var("SHOW TABLES LIKE '{$hpos_table}'") === $hpos_table;
echo "<p><strong>HPOS Table Exists:</strong> " . ($hpos_exists ? '✅ Yes' : '❌ No') . "</p>";

echo "<h2>2. Legacy wp_posts Investigation</h2>";

// Check wp_posts for our orders
$test_orders = array(1569, 1582, 1583, 1604);

foreach ($test_orders as $order_id) {
    echo "<h3>Order #{$order_id} in wp_posts</h3>";
    
    $post_data = $wpdb->get_row($wpdb->prepare("
        SELECT ID, post_type, post_status, post_date, post_title 
        FROM {$posts_table} 
        WHERE ID = %d
    ", $order_id));
    
    if ($post_data) {
        echo "<p>✅ <strong>Found in wp_posts:</strong></p>";
        echo "<ul>";
        echo "<li><strong>Post Type:</strong> {$post_data->post_type}</li>";
        echo "<li><strong>Post Status:</strong> {$post_data->post_status}</li>";
        echo "<li><strong>Date:</strong> {$post_data->post_date}</li>";
        echo "<li><strong>Title:</strong> {$post_data->post_title}</li>";
        echo "</ul>";
        
        // Check if it also exists in HPOS
        if ($hpos_exists) {
            $hpos_data = $wpdb->get_row($wpdb->prepare("
                SELECT id, status, type, date_created_gmt 
                FROM {$hpos_table} 
                WHERE id = %d
            ", $order_id));
            
            if ($hpos_data) {
                echo "<p>✅ <strong>Also in HPOS:</strong> Status = {$hpos_data->status}, Type = {$hpos_data->type}</p>";
            } else {
                echo "<p>❌ <strong>NOT in HPOS table</strong> - This order needs migration!</p>";
            }
        }
        
    } else {
        echo "<p>❌ Not found in wp_posts</p>";
    }
}

echo "<h2>3. Order Status Meta Check</h2>";

// Check order status meta in wp_postmeta
foreach ($test_orders as $order_id) {
    echo "<h3>Order #{$order_id} Meta</h3>";
    
    $status_meta = get_post_meta($order_id, '_order_status', true);
    echo "<p><strong>_order_status meta:</strong> " . ($status_meta ?: 'Not set') . "</p>";
    
    // Get all meta for this order
    $all_meta = get_post_meta($order_id);
    $relevant_meta = array();
    foreach ($all_meta as $key => $values) {
        if (strpos($key, '_order') !== false || strpos($key, 'status') !== false) {
            $relevant_meta[$key] = $values[0];
        }
    }
    
    if ($relevant_meta) {
        echo "<p><strong>Relevant Meta:</strong></p>";
        echo "<ul>";
        foreach ($relevant_meta as $key => $value) {
            echo "<li><strong>{$key}:</strong> {$value}</li>";
        }
        echo "</ul>";
    }
}

echo "<h2>4. WooCommerce Data Store Check</h2>";

foreach ($test_orders as $order_id) {
    $order = wc_get_order($order_id);
    if ($order) {
        echo "<h3>Order #{$order_id} Data Store Analysis</h3>";
        
        $data_store = $order->get_data_store();
        echo "<p><strong>Data Store Class:</strong> " . get_class($data_store) . "</p>";
        
        // Check what data store it should be using
        if ($hpos_enabled) {
            echo "<p><strong>Expected Data Store:</strong> Automattic\\WooCommerce\\Internal\\DataStores\\Orders\\OrdersTableDataStore</p>";
            echo "<p><strong>Issue:</strong> " . (get_class($data_store) !== 'Automattic\\WooCommerce\\Internal\\DataStores\\Orders\\OrdersTableDataStore' ? '❌ Using legacy data store!' : '✅ Using correct data store') . "</p>";
        }
        
        // Check order meta
        $order_meta = $order->get_meta_data();
        foreach ($order_meta as $meta) {
            $meta_data = $meta->get_data();
            if (strpos($meta_data['key'], 'status') !== false || strpos($meta_data['key'], '_order') !== false) {
                echo "<p><strong>Meta {$meta_data['key']}:</strong> {$meta_data['value']}</p>";
            }
        }
    }
}

echo "<h2>5. Migration Status Check</h2>";

if (class_exists('Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer')) {
    try {
        $synchronizer = wc_get_container()->get(Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class);
        
        $pending_count = $synchronizer->get_current_orders_pending_sync_count();
        echo "<p><strong>Orders Pending Sync:</strong> {$pending_count}</p>";
        
        if ($pending_count > 0) {
            echo "<div style='background: #ffe6cc; padding: 10px; border-left: 4px solid #ff9900; margin: 10px 0;'>";
            echo "<h4>⚠️ Migration Needed</h4>";
            echo "<p>There are {$pending_count} orders that need to be synced to HPOS.</p>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<p><strong>Synchronizer Error:</strong> " . $e->getMessage() . "</p>";
    }
}

echo "<h2>6. Manual Migration Test</h2>";

// Test if we can manually sync one order
$test_order_id = 1569;
$order = wc_get_order($test_order_id);

if ($order && $hpos_exists) {
    echo "<h3>Testing Manual Sync for Order #{$test_order_id}</h3>";
    
    // Check if order exists in HPOS before sync
    $before_sync = $wpdb->get_row($wpdb->prepare("
        SELECT id, status FROM {$hpos_table} WHERE id = %d
    ", $test_order_id));
    
    echo "<p><strong>Before Sync - HPOS Entry:</strong> " . ($before_sync ? "Exists (Status: {$before_sync->status})" : "Does not exist") . "</p>";
    
    try {
        // Force sync this order to HPOS
        if (class_exists('Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer')) {
            $synchronizer = wc_get_container()->get(Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class);
            
            // Try to sync this specific order
            if (method_exists($synchronizer, 'sync_order')) {
                $sync_result = $synchronizer->sync_order($test_order_id);
                echo "<p><strong>Sync Result:</strong> " . ($sync_result ? "✅ Success" : "❌ Failed") . "</p>";
            } else {
                echo "<p><strong>Manual Sync:</strong> sync_order method not available</p>";
            }
        }
        
        // Check if order exists in HPOS after sync attempt
        $after_sync = $wpdb->get_row($wpdb->prepare("
            SELECT id, status FROM {$hpos_table} WHERE id = %d
        ", $test_order_id));
        
        echo "<p><strong>After Sync - HPOS Entry:</strong> " . ($after_sync ? "✅ Exists (Status: {$after_sync->status})" : "❌ Still does not exist") . "</p>";
        
    } catch (Exception $e) {
        echo "<p><strong>Sync Error:</strong> " . $e->getMessage() . "</p>";
    }
}

echo "<h2>7. Query Hook Analysis</h2>";

echo "<p><strong>Current Issue:</strong> Our plugin hooks are targeting HPOS queries, but orders are in wp_posts!</p>";

// Check our plugin's hooks
echo "<p><strong>Our Plugin Hooks:</strong></p>";
echo "<ul>";
echo "<li>woocommerce_orders_table_query_clauses (HPOS) - ✅ Active</li>";
echo "<li>posts_clauses (Legacy) - ❌ Not implemented</li>";
echo "</ul>";

echo "<h2>8. Fix Recommendations</h2>";

echo "<div style='background: #e6f3ff; padding: 15px; border-left: 4px solid #0073aa; margin: 10px 0;'>";
echo "<h4>🔧 Required Fixes</h4>";
echo "<ol>";
echo "<li><strong>Immediate:</strong> Add legacy wp_posts query hooks to our plugin for backward compatibility</li>";
echo "<li><strong>Migration:</strong> Sync orders to HPOS table (can be done via WooCommerce admin)</li>";
echo "<li><strong>Future:</strong> Ensure new orders are created in HPOS</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #fff2cc; padding: 15px; border-left: 4px solid #d4a017; margin: 10px 0;'>";
echo "<h4>⚡ Quick Migration Options</h4>";
echo "<ol>";
echo "<li><strong>WooCommerce Admin:</strong> Go to WooCommerce > Settings > Advanced > Features > Enable HPOS and run migration</li>";
echo "<li><strong>CLI:</strong> wp wc hpos sync (if WP-CLI is available)</li>";
echo "<li><strong>Manual:</strong> Resave each order in admin to force HPOS sync</li>";
echo "</ol>";
echo "</div>";

// Check if we can provide a direct migration link
$admin_url = admin_url('admin.php?page=wc-settings&tab=advanced&section=features');
echo "<p><strong>🔗 Migration Link:</strong> <a href='{$admin_url}' target='_blank'>WooCommerce Features Settings</a></p>";

echo "<h2>9. Plugin Fix Required</h2>";

echo "<div style='background: #ffcccc; padding: 15px; border-left: 4px solid #dc3232; margin: 10px 0;'>";
echo "<h4>🚨 Plugin Code Fix Needed</h4>";
echo "<p>Our plugin only hooks into HPOS queries but these orders are in wp_posts. We need to add legacy support.</p>";
echo "<p><strong>File to update:</strong> <code>class-order-status-manager.php</code></p>";
echo "<p><strong>Required:</strong> Add hooks for legacy wp_posts queries in addition to HPOS</p>";
echo "</div>";

echo "<hr>";
echo "<p><strong>⚠️ Important:</strong> Delete this file after debugging for security reasons.</p>";
echo "<p><strong>Generated at:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>