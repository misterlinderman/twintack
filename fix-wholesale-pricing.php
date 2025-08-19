<?php
/**
 * Fix Wholesale Pricing Discrepancies
 * 
 * This script fixes orders where wholesale pricing calculations
 * got disrupted, specifically where the subtotal doesn't match
 * the total after accounting for discounts.
 */

// Load WordPress
require_once 'wp-config.php';
require_once 'wp-load.php';

// Check if required plugins are active
if (!class_exists('WooCommerce')) {
    die('WooCommerce is not active!');
}

echo "<h2>TwinTack Wholesale Pricing Fix</h2>\n";
echo "<pre>\n";

function fix_wholesale_order_pricing($order_id) {
    $order = wc_get_order($order_id);
    
    if (!$order) {
        echo "❌ Order #{$order_id} not found!\n";
        return false;
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "FIXING ORDER #{$order_id}\n";
    echo str_repeat("=", 50) . "\n";
    
    // Check if this is a wholesale customer
    $customer_id = $order->get_customer_id();
    $is_wholesale = false;
    $wholesale_role = '';
    
    if ($customer_id) {
        $user = get_user_by('id', $customer_id);
        if ($user) {
            $user_roles = $user->roles;
            foreach ($user_roles as $role) {
                if (strpos($role, 'wholesale') !== false) {
                    $is_wholesale = true;
                    $wholesale_role = $role;
                    break;
                }
            }
        }
    }
    
    if (!$is_wholesale) {
        echo "ℹ️  Order #{$order_id} is not a wholesale order, skipping\n";
        return false;
    }
    
    echo "🏪 Wholesale Customer: {$wholesale_role}\n";
    
    // Get current totals
    $current_subtotal = $order->get_subtotal();
    $current_total = $order->get_total();
    $tax = $order->get_total_tax();
    $shipping = $order->get_shipping_total();
    $discounts = $order->get_total_discount();
    
    echo "Current Subtotal: $" . number_format($current_subtotal, 2) . "\n";
    echo "Current Total: $" . number_format($current_total, 2) . "\n";
    echo "Tax: $" . number_format($tax, 2) . "\n";
    echo "Shipping: $" . number_format($shipping, 2) . "\n";
    echo "Discounts: $" . number_format($discounts, 2) . "\n";
    
    // Calculate what the subtotal should be based on line items
    $calculated_subtotal = 0;
    $has_wholesale_pricing = false;
    
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        $quantity = $item->get_quantity();
        $line_subtotal = $item->get_subtotal();
        $line_total = $item->get_total();
        
        // Check for wholesale pricing
        $wholesale_price = $item->get_meta('_wwp_wholesale_price');
        $regular_price = $product ? $product->get_regular_price() : 0;
        
        echo "\nItem: " . $item->get_name() . "\n";
        echo "  Quantity: {$quantity}\n";
        echo "  Line Subtotal: $" . number_format($line_subtotal, 2) . "\n";
        echo "  Line Total: $" . number_format($line_total, 2) . "\n";
        
        if ($wholesale_price) {
            $has_wholesale_pricing = true;
            echo "  💳 Wholesale Price: $" . number_format($wholesale_price, 2) . "\n";
            
            // Calculate correct line subtotal based on wholesale price
            $correct_line_subtotal = $wholesale_price * $quantity;
            
            if (abs($line_subtotal - $correct_line_subtotal) > 0.01) {
                echo "  🔧 FIXING: Line subtotal should be $" . number_format($correct_line_subtotal, 2) . "\n";
                $item->set_subtotal($correct_line_subtotal);
                $item->set_total($correct_line_subtotal); // Assuming no line-level discounts
                $item->save();
                $line_subtotal = $correct_line_subtotal;
            } else {
                echo "  ✅ Line pricing is correct\n";
            }
        } else if ($regular_price) {
            echo "  ⚠️  No wholesale pricing found, using regular price: $" . number_format($regular_price, 2) . "\n";
            // For wholesale customers without wholesale pricing set, we might need to apply it
            $calculated_line_subtotal = $regular_price * $quantity;
            
            if (abs($line_subtotal - $calculated_line_subtotal) > 0.01) {
                echo "  🔧 FIXING: Setting line subtotal to regular price\n";
                $item->set_subtotal($calculated_line_subtotal);
                $item->set_total($calculated_line_subtotal);
                $item->save();
                $line_subtotal = $calculated_line_subtotal;
            }
        }
        
        $calculated_subtotal += $line_subtotal;
    }
    
    echo "\n📊 TOTALS ANALYSIS:\n";
    echo "Calculated Subtotal from Line Items: $" . number_format($calculated_subtotal, 2) . "\n";
    echo "Current Order Subtotal: $" . number_format($current_subtotal, 2) . "\n";
    
    // Fix order subtotal if needed
    if (abs($current_subtotal - $calculated_subtotal) > 0.01) {
        echo "🔧 FIXING: Order subtotal mismatch detected\n";
        $order->set_subtotal($calculated_subtotal);
        
        // Recalculate total
        $new_total = $calculated_subtotal + $tax + $shipping - $discounts;
        $order->set_total($new_total);
        
        $order->save();
        
        echo "✅ FIXED: New subtotal: $" . number_format($calculated_subtotal, 2) . "\n";
        echo "✅ FIXED: New total: $" . number_format($new_total, 2) . "\n";
        
        $order->add_order_note('TwinTack: Fixed wholesale pricing discrepancy - subtotal corrected from $' . number_format($current_subtotal, 2) . ' to $' . number_format($calculated_subtotal, 2));
        
        return true;
    } else {
        echo "✅ Order pricing is already correct\n";
        return false;
    }
}

// Function to recalculate order totals the WooCommerce way
function recalculate_order_totals($order_id) {
    $order = wc_get_order($order_id);
    
    if (!$order) {
        return false;
    }
    
    echo "🔄 Recalculating order totals for #{$order_id}...\n";
    
    // Save current wholesale pricing before recalculation
    $wholesale_backup = array();
    foreach ($order->get_items() as $item_id => $item) {
        $wholesale_price = $item->get_meta('_wwp_wholesale_price');
        $wholesale_role = $item->get_meta('_wwp_wholesale_role');
        if ($wholesale_price) {
            $wholesale_backup[$item_id] = array(
                'price' => $wholesale_price,
                'role' => $wholesale_role
            );
        }
    }
    
    // Recalculate
    $order->calculate_totals();
    
    // Restore wholesale pricing if it was lost
    foreach ($wholesale_backup as $item_id => $backup_data) {
        $item = $order->get_item($item_id);
        if ($item && !$item->get_meta('_wwp_wholesale_price')) {
            $item->update_meta_data('_wwp_wholesale_price', $backup_data['price']);
            $item->update_meta_data('_wwp_wholesale_role', $backup_data['role']);
            $item->save();
            echo "  ↻ Restored wholesale pricing for item {$item_id}\n";
        }
    }
    
    echo "✅ Recalculation complete\n";
    return true;
}

echo "🔧 WHOLESALE PRICING FIX UTILITY\n";
echo str_repeat("=", 40) . "\n";

// Check plugin status
if (class_exists('WooCommerce_Wholesale_Prices')) {
    echo "✅ WooCommerce Wholesale Prices plugin detected\n";
} else {
    echo "❌ WooCommerce Wholesale Prices plugin not found\n";
}

// Fix specific order (Order #1741 from the screenshot)
$order_to_fix = 1741;

if (isset($_GET['order_id'])) {
    $order_to_fix = intval($_GET['order_id']);
}

echo "\n🎯 Target Order: #{$order_to_fix}\n";

if (wc_get_order($order_to_fix)) {
    $was_fixed = fix_wholesale_order_pricing($order_to_fix);
    
    if ($was_fixed) {
        echo "\n✅ WHOLESALE PRICING FIX COMPLETED!\n";
    } else {
        echo "\nℹ️  No fixes were needed for this order\n";
    }
} else {
    echo "❌ Order #{$order_to_fix} not found\n";
    
    // Find recent wholesale orders to fix
    echo "\n🔍 Searching for recent wholesale orders...\n";
    
    $recent_orders = wc_get_orders(array(
        'limit' => 20,
        'status' => array('pending', 'processing', 'invoiced', 'on-hold'),
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    $wholesale_orders_found = 0;
    
    foreach ($recent_orders as $order) {
        $customer_id = $order->get_customer_id();
        if ($customer_id) {
            $user = get_user_by('id', $customer_id);
            if ($user) {
                $user_roles = $user->roles;
                foreach ($user_roles as $role) {
                    if (strpos($role, 'wholesale') !== false) {
                        echo "Found wholesale order: #{$order->get_id()}\n";
                        fix_wholesale_order_pricing($order->get_id());
                        $wholesale_orders_found++;
                        break;
                    }
                }
            }
        }
        
        if ($wholesale_orders_found >= 5) {
            break; // Limit to 5 orders per run
        }
    }
    
    if ($wholesale_orders_found === 0) {
        echo "No recent wholesale orders found\n";
    }
}

echo "\n" . str_repeat("=", 40) . "\n";
echo "🎯 USAGE:\n";
echo "- To fix a specific order: add ?order_id=XXXX to the URL\n";
echo "- Without parameters: fixes recent wholesale orders\n";
echo "- Upload the updated Simple Order Manager to prevent future issues\n";

echo "\n📝 PREVENTION:\n";
echo "- The updated TwinTack Simple Order Manager now preserves wholesale pricing\n";
echo "- Future status changes will maintain pricing integrity\n";
echo "- Backup data is stored for recovery if needed\n";

echo "\n</pre>";
?>
