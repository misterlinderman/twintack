<?php
/**
 * Test script to verify wholesale role detection
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Load WordPress
    require_once('../../../wp-load.php');
}

echo "<h2>Wholesale Role Detection Test</h2>";

// Test with the specific order from the image (Order #2182)
$order_id = 2182;

echo "<h3>Order {$order_id}</h3>";

$order = wc_get_order($order_id);
if (!$order) {
    echo "Order not found<br>";
    exit;
}

$customer_id = $order->get_customer_id();
echo "Customer ID: {$customer_id}<br>";

if ($customer_id) {
    $user = get_user_by('id', $customer_id);
    if ($user) {
        echo "Customer found: " . $user->display_name . "<br>";
        echo "User roles: " . implode(', ', $user->roles) . "<br>";
        
        // Test WooCommerce Wholesale Prices plugin role detection
        if (class_exists('WWP_Wholesale_Roles')) {
            echo "<h4>WooCommerce Wholesale Prices Plugin Detection:</h4>";
            $wholesale_roles = WWP_Wholesale_Roles::getInstance()->getUserWholesaleRole($user);
            echo "Detected wholesale roles: " . (empty($wholesale_roles) ? 'None' : implode(', ', $wholesale_roles)) . "<br>";
            
            if (!empty($wholesale_roles) && is_array($wholesale_roles)) {
                echo "✅ Wholesale customer detected!<br>";
                echo "Primary wholesale role: " . $wholesale_roles[0] . "<br>";
            } else {
                echo "❌ No wholesale roles detected<br>";
            }
        } else {
            echo "❌ WWP_Wholesale_Roles class not found<br>";
        }
        
        // Test manual role detection
        echo "<h4>Manual Role Detection:</h4>";
        $is_wholesale = false;
        foreach ($user->roles as $role) {
            if (strpos($role, 'wholesale') !== false || strpos($role, 'drop_ship') !== false) {
                $is_wholesale = true;
                echo "Found wholesale role: {$role}<br>";
                break;
            }
        }
        
        if (!$is_wholesale) {
            echo "❌ No wholesale roles found in manual detection<br>";
        }
        
        // Test all registered wholesale roles
        if (class_exists('WWP_Wholesale_Roles')) {
            echo "<h4>All Registered Wholesale Roles:</h4>";
            $all_roles = WWP_Wholesale_Roles::getInstance()->getAllRegisteredWholesaleRoles();
            if (!empty($all_roles)) {
                foreach ($all_roles as $role_key => $role_name) {
                    echo "- {$role_name} (Key: {$role_key})<br>";
                }
            } else {
                echo "No registered wholesale roles found<br>";
            }
        }
        
    } else {
        echo "❌ User not found<br>";
    }
} else {
    echo "❌ No customer ID found<br>";
}

echo "<h4>Order Analysis:</h4>";
echo "Order total: $" . $order->get_total() . "<br>";
echo "Order subtotal: $" . $order->get_subtotal() . "<br>";
echo "Shipping total: $" . $order->get_shipping_total() . "<br>";
echo "Tax total: $" . $order->get_total_tax() . "<br>";

$actual_subtotal = $order->get_total() - $order->get_shipping_total() - $order->get_total_tax();
echo "Calculated actual subtotal: $" . $actual_subtotal . "<br>";

if ($actual_subtotal < $order->get_subtotal()) {
    echo "✅ Wholesale pricing detected (actual subtotal < MSRP subtotal)<br>";
    echo "Expected unit price: $" . $actual_subtotal . "<br>";
} else {
    echo "❌ No wholesale pricing detected<br>";
}

echo "<h4>Summary:</h4>";
echo "The wholesale role detection should now work correctly for 'Drop Ship' customers.<br>";
echo "The PDF invoice should show the correct wholesale unit price instead of MSRP.<br>";
