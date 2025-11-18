<?php
/**
 * Wholesale Pricing Diagnostic Script
 * 
 * Analyzes wholesale pricing data for orders to identify
 * discrepancies between subtotal and total after status changes
 */

// Load WordPress
require_once 'wp-config.php';
require_once 'wp-load.php';

// Check if required plugins are active
if (!class_exists('WooCommerce')) {
    die('WooCommerce is not active!');
}

echo "<h2>TwinTack Wholesale Pricing Diagnostic</h2>\n";
echo "<pre>\n";

// Function to analyze a specific order
function analyze_wholesale_order($order_id) {
    $order = wc_get_order($order_id);
    
    if (!$order) {
        echo "❌ Order #{$order_id} not found!\n";
        return false;
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "ANALYZING ORDER #{$order_id}\n";
    echo str_repeat("=", 60) . "\n";
    
    // Basic order info
    echo "📊 ORDER DETAILS:\n";
    echo "Status: " . $order->get_status() . "\n";
    echo "Customer: " . $order->get_billing_email() . "\n";
    echo "Date: " . $order->get_date_created()->format('Y-m-d H:i:s') . "\n";
    
    // Check if customer is wholesale
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
    
    echo "Customer ID: " . ($customer_id ?: 'Guest') . "\n";
    echo "Wholesale Customer: " . ($is_wholesale ? "✅ Yes ({$wholesale_role})" : "❌ No") . "\n";
    
    // Check wholesale pricing meta
    echo "\n💰 PRICING ANALYSIS:\n";
    echo str_repeat("-", 30) . "\n";
    
    $subtotal = $order->get_subtotal();
    $total = $order->get_total();
    $tax = $order->get_total_tax();
    $shipping = $order->get_shipping_total();
    $discounts = $order->get_total_discount();
    
    echo "Subtotal: $" . number_format($subtotal, 2) . "\n";
    echo "Tax: $" . number_format($tax, 2) . "\n";
    echo "Shipping: $" . number_format($shipping, 2) . "\n";
    echo "Discounts: $" . number_format($discounts, 2) . "\n";
    echo "Total: $" . number_format($total, 2) . "\n";
    
    // Calculate expected total
    $expected_total = $subtotal + $tax + $shipping - $discounts;
    $total_discrepancy = abs($total - $expected_total);
    
    if ($total_discrepancy > 0.01) {
        echo "⚠️  PRICING DISCREPANCY DETECTED!\n";
        echo "Expected Total: $" . number_format($expected_total, 2) . "\n";
        echo "Actual Total: $" . number_format($total, 2) . "\n";
        echo "Difference: $" . number_format($total_discrepancy, 2) . "\n";
    } else {
        echo "✅ Pricing calculations match\n";
    }
    
    // Analyze line items
    echo "\n📦 LINE ITEMS ANALYSIS:\n";
    echo str_repeat("-", 40) . "\n";
    
    $line_item_subtotal = 0;
    $wholesale_savings = 0;
    
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        $quantity = $item->get_quantity();
        $line_total = $item->get_total();
        $line_subtotal = $item->get_subtotal();
        
        echo "Product: " . $item->get_name() . "\n";
        echo "  Quantity: {$quantity}\n";
        echo "  Line Subtotal: $" . number_format($line_subtotal, 2) . "\n";
        echo "  Line Total: $" . number_format($line_total, 2) . "\n";
        
        // Check for wholesale pricing meta
        $wholesale_price = $item->get_meta('_wwp_wholesale_price');
        $wholesale_role_meta = $item->get_meta('_wwp_wholesale_role');
        $regular_price = $product ? $product->get_regular_price() : 0;
        
        if ($wholesale_price) {
            echo "  💳 Wholesale Price: $" . number_format($wholesale_price, 2) . " (Role: {$wholesale_role_meta})\n";
            if ($regular_price) {
                $savings_per_item = $regular_price - $wholesale_price;
                $total_savings = $savings_per_item * $quantity;
                $wholesale_savings += $total_savings;
                echo "  💰 Savings: $" . number_format($total_savings, 2) . " total\n";
            }
        } else {
            echo "  ⚠️  No wholesale pricing meta found\n";
            if ($regular_price) {
                echo "  Regular Price: $" . number_format($regular_price, 2) . "\n";
            }
        }
        
        // Check all item meta for wholesale-related data
        $item_meta = $item->get_meta_data();
        $wholesale_meta_found = false;
        foreach ($item_meta as $meta) {
            $key = $meta->get_data()['key'];
            if (strpos($key, 'wwp') !== false || strpos($key, 'wholesale') !== false) {
                if (!$wholesale_meta_found) {
                    echo "  📝 Wholesale Meta:\n";
                    $wholesale_meta_found = true;
                }
                echo "    {$key}: " . $meta->get_data()['value'] . "\n";
            }
        }
        
        $line_item_subtotal += $line_subtotal;
        echo "\n";
    }
    
    echo "📊 SUMMARY:\n";
    echo str_repeat("-", 20) . "\n";
    echo "Calculated Line Items Subtotal: $" . number_format($line_item_subtotal, 2) . "\n";
    echo "Order Subtotal: $" . number_format($subtotal, 2) . "\n";
    echo "Total Wholesale Savings: $" . number_format($wholesale_savings, 2) . "\n";
    
    if (abs($line_item_subtotal - $subtotal) > 0.01) {
        echo "⚠️  SUBTOTAL MISMATCH! Line items don't match order subtotal\n";
    }
    
    // Check order meta for wholesale data
    echo "\n📋 ORDER META ANALYSIS:\n";
    echo str_repeat("-", 25) . "\n";
    
    $order_meta = $order->get_meta_data();
    $wholesale_order_meta = false;
    
    foreach ($order_meta as $meta) {
        $key = $meta->get_data()['key'];
        if (strpos($key, 'wwp') !== false || strpos($key, 'wholesale') !== false || strpos($key, '_twintack') !== false) {
            if (!$wholesale_order_meta) {
                echo "Relevant Order Meta:\n";
                $wholesale_order_meta = true;
            }
            echo "  {$key}: " . $meta->get_data()['value'] . "\n";
        }
    }
    
    if (!$wholesale_order_meta) {
        echo "No relevant wholesale or TwinTack meta found\n";
    }
    
    return array(
        'order_id' => $order_id,
        'is_wholesale' => $is_wholesale,
        'wholesale_role' => $wholesale_role,
        'has_discrepancy' => $total_discrepancy > 0.01,
        'subtotal' => $subtotal,
        'total' => $total,
        'wholesale_savings' => $wholesale_savings
    );
}

// Test with Order #1741 if it exists, otherwise find recent wholesale orders
$test_order_id = 1741;

echo "🔍 WHOLESALE PRICING DIAGNOSTIC SYSTEM\n";
echo str_repeat("=", 50) . "\n";

// Check if WWP plugin is active
if (class_exists('WooCommerce_Wholesale_Prices')) {
    echo "✅ WooCommerce Wholesale Prices plugin is active\n";
} else {
    echo "❌ WooCommerce Wholesale Prices plugin not found\n";
}

if (class_exists('TwinTack_Manual_Order_Payments')) {
    echo "✅ TwinTack Manual Order Payments plugin is active\n";
} else {
    echo "❌ TwinTack Manual Order Payments plugin not found\n";
}

// Analyze the specific order
if (wc_get_order($test_order_id)) {
    $result = analyze_wholesale_order($test_order_id);
} else {
    echo "\n⚠️  Order #{$test_order_id} not found, analyzing recent orders...\n";
    
    // Find recent wholesale orders
    $recent_orders = wc_get_orders(array(
        'limit' => 10,
        'status' => array('pending', 'processing', 'invoiced', 'on-hold', 'completed'),
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    echo "Found " . count($recent_orders) . " recent orders\n";
    
    foreach ($recent_orders as $order) {
        $customer_id = $order->get_customer_id();
        if ($customer_id) {
            $user = get_user_by('id', $customer_id);
            if ($user) {
                $user_roles = $user->roles;
                foreach ($user_roles as $role) {
                    if (strpos($role, 'wholesale') !== false) {
                        echo "\nFound wholesale order: #{$order->get_id()}\n";
                        analyze_wholesale_order($order->get_id());
                        break 2; // Exit both loops after finding first wholesale order
                    }
                }
            }
        }
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🔧 POTENTIAL FIXES FOR WHOLESALE PRICING ISSUES\n";
echo str_repeat("=", 50) . "\n";
echo "1. **Recalculate Order Totals**: Use WooCommerce's built-in recalculation\n";
echo "2. **Preserve Wholesale Meta**: Ensure wholesale pricing meta is maintained\n";
echo "3. **Hook into Status Changes**: Prevent wholesale calculations from being reset\n";
echo "4. **Manual Recalculation**: Force recalculation with wholesale pricing intact\n";

echo "\n📝 RECOMMENDATIONS:\n";
echo "- If discrepancies are found, the order may need totals recalculated\n";
echo "- Wholesale meta data should be preserved during status changes\n";
echo "- Consider adding hooks to maintain wholesale pricing integrity\n";

echo "\n</pre>";
?>
