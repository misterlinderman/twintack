<?php
/**
 * Order Status Diagnostic & Fix Tool
 * Upload to WordPress root and visit: https://yoursite.com/check-orders-status.php
 */

require_once(__DIR__ . '/wp-config.php');
require_once(__DIR__ . '/wp-load.php');

?>
<!DOCTYPE html>
<html><head><title>TwinTack Order Status Check</title>
<style>body{font-family:Arial,sans-serif;margin:20px;}.success{color:green;font-weight:bold;}.error{color:red;font-weight:bold;}.info{color:blue;}.section{border:1px solid #ccc;margin:10px 0;padding:15px;}table{border-collapse:collapse;width:100%;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}</style>
</head><body>

<h1>🔧 TwinTack Order Status Diagnostic</h1>
<p><strong>Generated:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

<div class="section">
<h2>1. Environment Check</h2>
<?php
echo "<p><strong>WordPress:</strong> " . get_bloginfo('version') . "</p>";
echo "<p><strong>WooCommerce:</strong> " . (function_exists('WC') ? '✅ v' . WC()->version : '❌ Not found') . "</p>";
echo "<p><strong>TwinTack Plugin:</strong> " . (defined('TWINTACK_MANUAL_PAYMENTS_VERSION') ? '✅ v' . TWINTACK_MANUAL_PAYMENTS_VERSION : '❌ Not loaded') . "</p>";
?>
</div>

<div class="section">
<h2>2. Comprehensive Order Analysis - Orders 1569 & 1582</h2>
<?php
global $wpdb;
$orders_to_check = [1569, 1582];

foreach ($orders_to_check as $order_id) {
    echo "<h3>🔍 Deep Analysis: Order #{$order_id}</h3>";
    
    // 1. Check wp_posts table with any post_type
    $post_data = $wpdb->get_row($wpdb->prepare("
        SELECT ID, post_type, post_status, post_date 
        FROM {$wpdb->posts} 
        WHERE ID = %d
    ", $order_id));
    
    echo "<p><strong>wp_posts table:</strong> ";
    if ($post_data) {
        echo "✅ Found - Type: <code>{$post_data->post_type}</code>, Status: <code>{$post_data->post_status}</code>, Date: {$post_data->post_date}";
    } else {
        echo "❌ Not found in wp_posts table";
    }
    echo "</p>";
    
    // 2. Check WooCommerce HPOS tables (if they exist)
    $hpos_tables = [
        'wc_orders' => 'id',
        'wp_wc_orders' => 'id', 
        'woocommerce_orders' => 'id'
    ];
    
    foreach ($hpos_tables as $table => $id_col) {
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$table}'");
        if ($table_exists) {
            $hpos_data = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}{$table} 
                WHERE {$id_col} = %d
            ", $order_id));
            
            echo "<p><strong>{$table} table:</strong> ";
            if ($hpos_data) {
                echo "✅ Found - Status: <code>" . (isset($hpos_data->status) ? $hpos_data->status : 'Unknown') . "</code>";
            } else {
                echo "❌ Not found";
            }
            echo "</p>";
        }
    }
    
    // 3. WooCommerce object check
    $order = wc_get_order($order_id);
    echo "<p><strong>WooCommerce object:</strong> ";
    if ($order) {
        echo "✅ Found - Status: <code>" . $order->get_status() . "</code>, Type: <code>" . get_class($order) . "</code>";
        echo "<br>• Data store: <code>" . $order->get_data_store()->get_current_class_name() . "</code>";
        echo "<br>• Total: $" . $order->get_total();
        echo "<br>• Date: " . $order->get_date_created()->date('Y-m-d H:i:s');
    } else {
        echo "❌ WooCommerce can't find this order";
    }
    echo "</p>";
    
    // 4. Search for order in any table containing the ID
    echo "<p><strong>Database search:</strong> ";
    $search_results = $wpdb->get_results($wpdb->prepare("
        SELECT 'wp_posts' as table_name, ID, post_type, post_status as status FROM {$wpdb->posts} WHERE ID = %d
        UNION ALL
        SELECT 'wp_postmeta' as table_name, post_id as ID, meta_key as post_type, meta_value as status FROM {$wpdb->postmeta} WHERE post_id = %d LIMIT 5
    ", $order_id, $order_id));
    
    if (!empty($search_results)) {
        echo "<br>";
        foreach ($search_results as $result) {
            echo "• {$result->table_name}: ID {$result->ID}, {$result->post_type}, {$result->status}<br>";
        }
    } else {
        echo "No references found in standard tables";
    }
    echo "</p>";
    
    echo "<hr>";
}
?>

<h3>📊 Quick Summary Table</h3>
<table><tr><th>Order</th><th>wp_posts</th><th>WC Object</th><th>WC Status</th><th>Action</th></tr>
<?php
foreach ($orders_to_check as $order_id) {
    echo "<tr>";
    echo "<td>#{$order_id}</td>";
    
    // wp_posts check
    $post_exists = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $order_id));
    echo "<td>" . ($post_exists ? '✅ Exists' : '❌ Missing') . "</td>";
    
    // WC object check
    $order = wc_get_order($order_id);
    echo "<td>" . ($order ? '✅ Found' : '❌ Missing') . "</td>";
    echo "<td>" . ($order ? $order->get_status() : 'N/A') . "</td>";
    
    // Action
    if ($order && !$post_exists) {
        echo "<td class='error'>🔧 HPOS/Custom storage</td>";
    } elseif (!$order) {
        echo "<td class='error'>❌ Order missing</td>";
    } else {
        echo "<td class='success'>✅ Normal storage</td>";
    }
    
    echo "</tr>";
}
?>
</table>
</div>

<div class="section">
<h2>3. All Invoiced Orders</h2>
<?php
// Check if HPOS is enabled
$is_hpos = false;
if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
    $is_hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}

echo "<p><strong>Storage System:</strong> " . ($is_hpos ? 'HPOS (High Performance)' : 'Legacy Posts') . "</p>";

if ($is_hpos) {
    // Check HPOS table
    $invoiced_orders = $wpdb->get_results("
        SELECT id as ID, status, date_created_gmt as post_date 
        FROM {$wpdb->prefix}wc_orders 
        WHERE status = 'invoiced'
        ORDER BY date_created_gmt DESC
    ");
} else {
    // Check legacy posts table
    $invoiced_orders = $wpdb->get_results("
        SELECT ID, post_status as status, post_date 
        FROM {$wpdb->posts} 
        WHERE post_type = 'shop_order' AND post_status = 'wc-invoiced'
        ORDER BY post_date DESC
    ");
}

if (!empty($invoiced_orders)) {
    echo "<p class='success'>✅ Found " . count($invoiced_orders) . " invoiced orders:</p>";
    echo "<table><tr><th>Order ID</th><th>Status</th><th>Date</th></tr>";
    foreach ($invoiced_orders as $order) {
        echo "<tr><td>#{$order->ID}</td><td>{$order->status}</td><td>{$order->post_date}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>❌ No orders found with 'invoiced' status</p>";
}
?>
</div>

<div class="section">
<h2>4. Registered Order Statuses</h2>
<?php
if (function_exists('wc_get_order_statuses')) {
    $statuses = wc_get_order_statuses();
    echo "<p><strong>Total:</strong> " . count($statuses) . " statuses</p>";
    $has_invoiced = isset($statuses['wc-invoiced']);
    echo "<p><strong>Invoiced Status:</strong> " . ($has_invoiced ? '✅ Registered' : '❌ Missing') . "</p>";
    
    if ($has_invoiced) {
        echo "<p><strong>Label:</strong> " . $statuses['wc-invoiced'] . "</p>";
    }
}
?>
</div>

<?php if (isset($_GET['fix']) && $_GET['fix'] === '1'): ?>
<div class="section">
<h2>🔧 Fixing Order Statuses (Enhanced)</h2>
<?php
foreach ($orders_to_check as $order_id) {
    echo "<h3>Order #{$order_id}</h3>";
    
    $order = wc_get_order($order_id);
    if (!$order) {
        echo "<p class='error'>❌ Order not found</p>";
        continue;
    }
    
    // Check actual database status first
    $actual_db_status = $wpdb->get_var($wpdb->prepare("
        SELECT post_status FROM {$wpdb->posts} 
        WHERE ID = %d AND post_type = 'shop_order'
    ", $order_id));
    
    echo "<p><strong>Before Fix:</strong></p>";
    echo "<p>• Database post_status: <code>" . ($actual_db_status ?: 'Not found') . "</code></p>";
    echo "<p>• WC get_status(): <code>" . $order->get_status() . "</code></p>";
    
    // Check if this is HPOS or legacy storage
    $is_hpos = false;
    if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
        $is_hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }
    
    echo "<p><strong>Storage Type:</strong> " . ($is_hpos ? 'HPOS (High Performance)' : 'Legacy Posts') . "</p>";
    
    if ($is_hpos) {
        // HPOS: Check and update wc_orders table
        $hpos_status = $wpdb->get_var($wpdb->prepare("
            SELECT status FROM {$wpdb->prefix}wc_orders WHERE id = %d
        ", $order_id));
        
        echo "<p><strong>HPOS Status:</strong> <code>" . ($hpos_status ?: 'Not found') . "</code></p>";
        
        if ($hpos_status !== 'invoiced') {
            echo "<p class='info'>🔧 Updating HPOS table to invoiced status...</p>";
            
            $updated = $wpdb->update(
                $wpdb->prefix . 'wc_orders',
                ['status' => 'invoiced'],
                ['id' => $order_id],
                ['%s'],
                ['%d']
            );
            
            if ($updated) {
                echo "<p class='success'>✅ HPOS status updated to invoiced</p>";
                
                // Also update via WooCommerce to trigger hooks
                $order->set_status('invoiced', 'Status set to invoiced via HPOS-compatible diagnostic tool');
                $order->save();
                echo "<p class='success'>✅ WooCommerce hooks triggered</p>";
            } else {
                echo "<p class='error'>❌ Failed to update HPOS table</p>";
            }
        } else {
            echo "<p class='success'>✅ HPOS already has invoiced status</p>";
        }
        
    } else {
        // Legacy: Update wp_posts table
        if ($actual_db_status !== 'wc-invoiced') {
            echo "<p class='info'>🔧 Forcing database update to wc-invoiced...</p>";
            
            $updated = $wpdb->update(
                $wpdb->posts,
                ['post_status' => 'wc-invoiced'],
                ['ID' => $order_id, 'post_type' => 'shop_order'],
                ['%s'],
                ['%d', '%s']
            );
            
            if ($updated) {
                echo "<p class='success'>✅ Database post_status updated to wc-invoiced</p>";
            } else {
                echo "<p class='error'>❌ Failed to update database</p>";
            }
            
            // Now update via WooCommerce
            $order->set_status('invoiced', 'Status forcefully set to invoiced via enhanced diagnostic tool');
            $order->save();
            echo "<p class='success'>✅ WooCommerce status updated</p>";
            
        } else {
            echo "<p class='success'>✅ Database already has wc-invoiced status</p>";
        }
    }
    
    // Ensure Shippo meta is set
    $order->update_meta_data('_shippo_fulfillment_status', 'Payment Pending');
    $order->update_meta_data('_shippo_sync_timestamp', current_time('timestamp'));
    $order->save();
    echo "<p class='info'>📋 Shippo metadata updated</p>";
    
    // Verify final status based on storage type
    echo "<p><strong>After Fix:</strong></p>";
    
    if ($is_hpos) {
        $final_hpos_status = $wpdb->get_var($wpdb->prepare("
            SELECT status FROM {$wpdb->prefix}wc_orders WHERE id = %d
        ", $order_id));
        
        echo "<p>• HPOS status: <code>" . ($final_hpos_status ?: 'Still not found') . "</code></p>";
        echo "<p>• WC get_status(): <code>" . $order->get_status() . "</code></p>";
        
        if ($final_hpos_status === 'invoiced') {
            echo "<p class='success'>🎉 Order #{$order_id} successfully fixed in HPOS!</p>";
        } else {
            echo "<p class='error'>⚠️ Order #{$order_id} may still have HPOS issues</p>";
        }
    } else {
        $final_db_status = $wpdb->get_var($wpdb->prepare("
            SELECT post_status FROM {$wpdb->posts} 
            WHERE ID = %d AND post_type = 'shop_order'
        ", $order_id));
        
        echo "<p>• Database post_status: <code>" . ($final_db_status ?: 'Still not found') . "</code></p>";
        echo "<p>• WC get_status(): <code>" . $order->get_status() . "</code></p>";
        
        if ($final_db_status === 'wc-invoiced') {
            echo "<p class='success'>🎉 Order #{$order_id} successfully fixed!</p>";
        } else {
            echo "<p class='error'>⚠️ Order #{$order_id} may still have issues</p>";
        }
    }
    
    echo "<hr>";
}
?>
</div>
<?php endif; ?>

<div class="section">
<h2>5. Actions</h2>
<?php if (!isset($_GET['fix'])): ?>
<p><a href="?fix=1" style="background:#0073aa;color:white;padding:10px 20px;text-decoration:none;border-radius:3px;">🔧 Fix Orders 1569 & 1582</a></p>
<?php else: ?>
<p class='success'>✅ Fix applied! <a href="<?php echo admin_url('admin.php?page=wc-orders'); ?>">View Orders List</a></p>
<?php endif; ?>
<p><small><strong>⚠️ Delete this file after use!</strong></small></p>
</div>

</body></html>