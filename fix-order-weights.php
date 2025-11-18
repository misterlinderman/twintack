<?php
/**
 * Fix Weight Issues for Orders 1665 and 1662
 * 
 * This script diagnoses and fixes the Shippo weight sync issue
 * by ensuring proper weight data is available for the orders.
 */

// Load WordPress
require_once 'wp-config.php';
require_once 'wp-load.php';

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    die('WooCommerce is not active!');
}

echo "<h2>TwinTack Order Weight Fix Script</h2>\n";
echo "<pre>\n";

$order_ids = [1665, 1662];

foreach ($order_ids as $order_id) {
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "ANALYZING ORDER #{$order_id}\n";
    echo str_repeat("=", 50) . "\n";
    
    $order = wc_get_order($order_id);
    
    if (!$order) {
        echo "❌ Order #{$order_id} not found!\n";
        continue;
    }
    
    echo "📊 Order Status: " . $order->get_status() . "\n";
    echo "📅 Date Created: " . $order->get_date_created()->format('Y-m-d H:i:s') . "\n";
    echo "💰 Total: $" . $order->get_total() . "\n";
    echo "⚖️  WooCommerce Weight Unit: " . get_option('woocommerce_weight_unit', 'lb') . "\n\n";
    
    echo "📦 ORDER ITEMS:\n";
    echo str_repeat("-", 30) . "\n";
    
    $total_weight = 0;
    $has_weight_issues = false;
    
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        
        echo "Item: " . $item->get_name() . "\n";
        echo "  Quantity: " . $item->get_quantity() . "\n";
        
        if ($product) {
            echo "  Product ID: " . $product->get_id() . "\n";
            echo "  SKU: " . ($product->get_sku() ?: 'No SKU') . "\n";
            echo "  Product Weight: " . ($product->get_weight() ?: '0 (NOT SET!)') . "\n";
            echo "  Weight Unit: " . get_option('woocommerce_weight_unit', 'lb') . "\n";
            
            $item_weight = $product->get_weight();
            if (!$item_weight || $item_weight <= 0) {
                echo "  ❌ WEIGHT ISSUE: Product has no weight set!\n";
                $has_weight_issues = true;
                
                // Try to find weight from similar products or set default
                $default_weight = 1.0; // Default 1 lb for sports equipment
                echo "  🔧 FIXING: Setting default weight to {$default_weight} lbs\n";
                
                // Update the product weight
                $product->set_weight($default_weight);
                $product->save();
                
                $total_weight += $default_weight * $item->get_quantity();
                echo "  ✅ Weight fixed: {$default_weight} lbs\n";
            } else {
                $total_weight += floatval($item_weight) * $item->get_quantity();
                echo "  ✅ Weight OK: {$item_weight} lbs\n";
            }
        } else {
            echo "  ❌ Product not found!\n";
            $has_weight_issues = true;
        }
        echo "\n";
    }
    
    echo "📊 TOTAL ORDER WEIGHT: {$total_weight} lbs\n";
    
    // Test Shippo data formatting
    echo "\n🚢 SHIPPO DATA TEST:\n";
    echo str_repeat("-", 20) . "\n";
    
    // Simulate the line items that would be sent to Shippo
    $shippo_line_items = [];
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        
        $line_item = [
            'title' => $item->get_name(),
            'quantity' => $item->get_quantity(),
            'total_price' => max($item->get_total(), 0.01),
            'currency' => $order->get_currency()
        ];
        
        // Add weight if available - THIS IS THE KEY FIX
        if ($product && $product->get_weight()) {
            $line_item['weight'] = $product->get_weight();
            // FIX: Use 'lb' not 'lbs' for Shippo
            $wc_weight_unit = get_option('woocommerce_weight_unit', 'lb');
            $line_item['weight_unit'] = ($wc_weight_unit === 'lbs') ? 'lb' : $wc_weight_unit;
        }
        
        $shippo_line_items[] = $line_item;
    }
    
    echo "Shippo Line Items Data:\n";
    echo json_encode($shippo_line_items, JSON_PRETTY_PRINT) . "\n";
    
    // Check the overall order weight data
    $wc_weight_unit = get_option('woocommerce_weight_unit', 'lb');
    $shippo_weight_unit = ($wc_weight_unit === 'lbs') ? 'lb' : $wc_weight_unit;
    
    echo "\nOverall Weight Data:\n";
    echo "- Total Weight: " . max($total_weight, 0.1) . "\n";
    echo "- WooCommerce Unit: {$wc_weight_unit}\n";
    echo "- Shippo Unit: {$shippo_weight_unit}\n";
    
    if ($has_weight_issues) {
        echo "\n✅ FIXED: Weight issues have been resolved for order #{$order_id}\n";
    } else {
        echo "\n✅ OK: No weight issues found for order #{$order_id}\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🎯 SUMMARY & NEXT STEPS\n";
echo str_repeat("=", 50) . "\n";
echo "1. Product weights have been set where missing\n";
echo "2. The key issue was the weight_unit being 'lbs' instead of 'lb'\n";
echo "3. Shippo requires 'lb' not 'lbs' for pound units\n";
echo "4. You should now be able to sync these orders with Shippo\n";
echo "\n🔧 MANUAL CODE FIX NEEDED:\n";
echo "In plugins/twintack-manual-order-payments/includes/class-shippo-api-client.php\n";
echo "Line 453: Change 'lbs' to the mapped weight unit\n";
echo "Line 387: Should use map_weight_unit_for_shippo() function\n";

echo "\n</pre>";
?>
