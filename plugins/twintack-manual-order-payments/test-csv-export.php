<?php
/**
 * Test script to verify CSV export wholesale pricing
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Load WordPress
    require_once('../../../wp-load.php');
}

echo "<h2>CSV Export Wholesale Pricing Test</h2>";

// Test with the specific orders from the CSV
$order_ids = array(2180, 2181, 2182);

foreach ($order_ids as $order_id) {
    echo "<h3>Order {$order_id}</h3>";
    
    $order = wc_get_order($order_id);
    if (!$order) {
        echo "Order not found<br>";
        continue;
    }
    
    echo "Order total: $" . $order->get_total() . "<br>";
    echo "Order subtotal: $" . $order->get_subtotal() . "<br>";
    echo "Order tax: $" . $order->get_total_tax() . "<br>";
    echo "Shipping total: $" . $order->get_shipping_total() . "<br>";
    
    echo "<h4>Items breakdown:</h4>";
    foreach ($order->get_items() as $item) {
        $line_total = $item->get_total();
        $quantity = $item->get_quantity();
        $unit_price = $quantity > 0 ? ($line_total / $quantity) : 0;
        
        echo "Item: " . $item->get_name() . "<br>";
        echo "Quantity: {$quantity}<br>";
        echo "Line total: $" . $line_total . "<br>";
        echo "Unit price: $" . $unit_price . "<br>";
        echo "---<br>";
    }
    
    // Calculate expected wholesale unit price
    $order_total = $order->get_total();
    $shipping = $order->get_shipping_total();
    $tax = $order->get_total_tax();
    $subtotal_before_shipping_tax = $order_total - $shipping - $tax;
    
    echo "<h4>Calculation breakdown:</h4>";
    echo "Order total: $" . $order_total . "<br>";
    echo "Minus shipping: $" . $shipping . "<br>";
    echo "Minus tax: $" . $tax . "<br>";
    echo "Subtotal before shipping/tax: $" . $subtotal_before_shipping_tax . "<br>";
    
    foreach ($order->get_items() as $item) {
        $quantity = $item->get_quantity();
        $expected_unit_price = $quantity > 0 ? ($subtotal_before_shipping_tax / $quantity) : 0;
        echo "Expected unit price for " . $item->get_name() . ": $" . number_format($expected_unit_price, 2) . "<br>";
    }
    
    echo "<br><br>";
}

echo "<h3>Summary</h3>";
echo "Based on the order totals, the expected unit prices should be calculated as:<br>";
echo "(Order Total - Shipping - Tax) ÷ Quantity<br>";
echo "This should give us the actual wholesale unit price that was charged.<br>";
