<?php
/**
 * Debug script to test wholesale pricing detection
 * Run this from WordPress admin or via WP-CLI
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Load WordPress
    require_once('../../../wp-load.php');
}

echo "<h2>Wholesale Pricing Debug</h2>";

// Test with the specific orders from the CSV
$order_ids = array(2180, 2181, 2182);

foreach ($order_ids as $order_id) {
    echo "<h3>Order {$order_id}</h3>";
    
    $order = wc_get_order($order_id);
    if (!$order) {
        echo "Order not found<br>";
        continue;
    }
    
    $customer_id = $order->get_customer_id();
    echo "Customer ID: {$customer_id}<br>";
    
    if ($customer_id) {
        $user = get_user_by('id', $customer_id);
        if ($user) {
            echo "Customer roles: " . implode(', ', $user->roles) . "<br>";
            
            $is_wholesale = false;
            $wholesale_role = null;
            foreach ($user->roles as $role) {
                if (strpos($role, 'wholesale') !== false) {
                    $is_wholesale = true;
                    $wholesale_role = $role;
                    break;
                }
            }
            
            echo "Is wholesale customer: " . ($is_wholesale ? 'Yes' : 'No') . "<br>";
            echo "Wholesale role: " . ($wholesale_role ?: 'None') . "<br>";
        }
    }
    
    echo "Order total: $" . $order->get_total() . "<br>";
    echo "Order subtotal: $" . $order->get_subtotal() . "<br>";
    
    echo "<h4>Items:</h4>";
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $effective_product_id = $variation_id ? $variation_id : $product_id;
        $quantity = $item->get_quantity();
        $line_total = $item->get_total();
        $unit_price = $quantity > 0 ? ($line_total / $quantity) : 0;
        
        echo "Product: " . $item->get_name() . "<br>";
        echo "Product ID: {$product_id}, Variation ID: {$variation_id}, Effective ID: {$effective_product_id}<br>";
        echo "Quantity: {$quantity}, Line Total: $" . $line_total . ", Unit Price: $" . $unit_price . "<br>";
        
        if ($is_wholesale && $wholesale_role) {
            // Test wholesale price lookup
            $product = wc_get_product($effective_product_id);
            if ($product) {
                $wholesale_price_meta = $product->get_meta($wholesale_role . '_wholesale_price', true);
                echo "Wholesale price meta ({$wholesale_role}_wholesale_price): " . ($wholesale_price_meta ?: 'Not set') . "<br>";
                
                // Try direct post meta
                $direct_meta = get_post_meta($effective_product_id, $wholesale_role . '_wholesale_price', true);
                echo "Direct post meta: " . ($direct_meta ?: 'Not set') . "<br>";
                
                // Try WooCommerce Wholesale Prices plugin
                if (class_exists('WWP_Wholesale_Prices')) {
                    $wwp_price = WWP_Wholesale_Prices::getProductWholesalePrice($effective_product_id, array($wholesale_role), $quantity);
                    echo "WWP wholesale price: " . ($wwp_price ?: 'Not found') . "<br>";
                }
                
                // List all meta for this product
                $all_meta = get_post_meta($effective_product_id);
                echo "All product meta keys containing 'wholesale':<br>";
                foreach ($all_meta as $key => $value) {
                    if (strpos($key, 'wholesale') !== false) {
                        echo "- {$key}: " . (is_array($value) ? implode(', ', $value) : $value) . "<br>";
                    }
                }
            }
        }
        
        echo "<hr>";
    }
    
    echo "<br><br>";
}

// Test specific product wholesale pricing
echo "<h3>Product Wholesale Pricing Test</h3>";

// Get a product from one of the orders
$order = wc_get_order(2180);
if ($order) {
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $effective_product_id = $variation_id ? $variation_id : $product_id;
        
        echo "<h4>Product ID: {$effective_product_id}</h4>";
        
        // List all wholesale-related meta
        $all_meta = get_post_meta($effective_product_id);
        echo "All meta for this product:<br>";
        foreach ($all_meta as $key => $value) {
            if (strpos($key, 'wholesale') !== false || strpos($key, 'price') !== false) {
                echo "- {$key}: " . (is_array($value) ? implode(', ', $value) : $value) . "<br>";
            }
        }
        
        break; // Just test the first product
    }
}

echo "<h3>Available Wholesale Roles</h3>";
$wholesale_roles = array();
$users = get_users(array('role' => 'wholesale_customer'));
foreach ($users as $user) {
    $wholesale_roles = array_merge($wholesale_roles, $user->roles);
}
$wholesale_roles = array_unique($wholesale_roles);
echo "Wholesale roles found: " . implode(', ', $wholesale_roles) . "<br>";

// Check if wholesale plugins are active
echo "<h3>Plugin Status</h3>";
echo "WWP_Wholesale_Prices class exists: " . (class_exists('WWP_Wholesale_Prices') ? 'Yes' : 'No') . "<br>";
echo "WWPP_Wholesale_Prices class exists: " . (class_exists('WWPP_Wholesale_Prices') ? 'Yes' : 'No') . "<br>";
echo "WooCommerce Wholesale Prices plugin active: " . (is_plugin_active('woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php') ? 'Yes' : 'No') . "<br>";
echo "WooCommerce Wholesale Prices Premium plugin active: " . (is_plugin_active('woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php') ? 'Yes' : 'No') . "<br>";
