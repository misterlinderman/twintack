<?php
/**
 * Test script to verify PDF wholesale pricing
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Load WordPress
    require_once('../../../wp-load.php');
}

echo "<h2>PDF Wholesale Pricing Test</h2>";

// Test with the specific order from the image (Order #2182)
$order_id = 2182;

echo "<h3>Order {$order_id}</h3>";

$order = wc_get_order($order_id);
if (!$order) {
    echo "Order not found<br>";
    exit;
}

echo "Order total: $" . $order->get_total() . "<br>";
echo "Order subtotal: $" . $order->get_subtotal() . "<br>";
echo "Order tax: $" . $order->get_total_tax() . "<br>";
echo "Shipping total: $" . $order->get_shipping_total() . "<br>";

// Check customer
$customer_id = $order->get_customer_id();
echo "Customer ID: {$customer_id}<br>";

if ($customer_id) {
    $user = get_user_by('id', $customer_id);
    if ($user) {
        echo "Customer roles: " . implode(', ', $user->roles) . "<br>";
        
        $is_wholesale = false;
        foreach ($user->roles as $role) {
            if (strpos($role, 'wholesale') !== false) {
                $is_wholesale = true;
                break;
            }
        }
        echo "Is wholesale customer: " . ($is_wholesale ? 'Yes' : 'No') . "<br>";
    }
}

echo "<h4>Items breakdown:</h4>";
foreach ($order->get_items() as $item) {
    $line_total = $item->get_total();
    $quantity = $item->get_quantity();
    $unit_price = $quantity > 0 ? ($line_total / $quantity) : 0;
    
    echo "Item: " . $item->get_name() . "<br>";
    echo "Quantity: {$quantity}<br>";
    echo "Line total: $" . $line_total . "<br>";
    echo "Unit price: $" . $unit_price . "<br>";
    
    // Calculate expected wholesale unit price
    $order_total = $order->get_total();
    $shipping = $order->get_shipping_total();
    $tax = $order->get_total_tax();
    $subtotal_before_shipping_tax = $order_total - $shipping - $tax;
    $expected_unit_price = $quantity > 0 ? ($subtotal_before_shipping_tax / $quantity) : 0;
    
    echo "Expected wholesale unit price: $" . number_format($expected_unit_price, 2) . "<br>";
    echo "---<br>";
}

// Test the bulk manager
echo "<h4>Bulk Manager Test:</h4>";
if (class_exists('TwinTack_Bulk_Invoice_Manager')) {
    echo "TwinTack_Bulk_Invoice_Manager class exists<br>";
    $bulk_manager = TwinTack_Bulk_Invoice_Manager::get_instance();
    if ($bulk_manager) {
        echo "Bulk manager instance created successfully<br>";
        
        // Test wholesale pricing for first item
        foreach ($order->get_items() as $item) {
            $pricing = $bulk_manager->get_wholesale_item_pricing($order, $item);
            echo "Wholesale pricing result:<br>";
            echo "- Unit price: $" . $pricing['unit_price'] . "<br>";
            echo "- Line total: $" . $pricing['line_total'] . "<br>";
            echo "- Is wholesale: " . ($pricing['is_wholesale'] ? 'Yes' : 'No') . "<br>";
            break; // Just test first item
        }
    } else {
        echo "Failed to create bulk manager instance<br>";
    }
} else {
    echo "TwinTack_Bulk_Invoice_Manager class not found<br>";
}

// Test PDF generator
echo "<h4>PDF Generator Test:</h4>";
if (class_exists('TwinTack_PDF_Invoice_Generator')) {
    echo "TwinTack_PDF_Invoice_Generator class exists<br>";
    $pdf_generator = TwinTack_PDF_Invoice_Generator::get_instance();
    if ($pdf_generator) {
        echo "PDF generator instance created successfully<br>";
        echo "PDF available: " . ($pdf_generator->is_pdf_available() ? 'Yes' : 'No') . "<br>";
    } else {
        echo "Failed to create PDF generator instance<br>";
    }
} else {
    echo "TwinTack_PDF_Invoice_Generator class not found<br>";
}

echo "<h4>Summary</h4>";
echo "Based on the order data:<br>";
echo "- Order total: $" . $order->get_total() . "<br>";
echo "- Shipping: $" . $order->get_shipping_total() . "<br>";
echo "- Tax: $" . $order->get_total_tax() . "<br>";
echo "- Expected item subtotal: $" . ($order->get_total() - $order->get_shipping_total() - $order->get_total_tax()) . "<br>";
echo "- Expected unit price: $" . number_format(($order->get_total() - $order->get_shipping_total() - $order->get_total_tax()), 2) . "<br>";
echo "<br>This should be the wholesale unit price shown in the PDF invoice.<br>";
