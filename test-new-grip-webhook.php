<?php
/**
 * Test script for new grip design webhook functionality
 * This simulates the webhook that should be triggered when a grip design is created from a purchase
 */

// Test webhook URL (your Make.com webhook)
$webhook_url = 'https://hook.us2.make.com/lmac9y2igw43gy8flqsois16lmy92o9p';

// Sample webhook data that would be sent when a new grip design is created
$webhook_data = array(
    'grip_design_id' => 1234,
    'grip_design_title' => 'Custom Grip - neon lakers 250109',
    'order_id' => 1222,
    'customer_name' => 'Matthew Linder',
    'customer_email' => 'itshouldbeashirt@gmail.com',
    'team_name' => 'neon lakers',
    'design_type' => '2-Color Fade (Yellow + Violet)',
    'quantity' => 125,
    'artwork_filename' => 'LosAngelesLakers-A23.webp',
    'design_instructions' => 'come on',
    'artwork_status' => 'artwork_pending',
    'timestamp' => date('c'), // ISO 8601 format
    'webhook_type' => 'new_grip_design',
    'site_url' => 'https://meo.efe.mybluehost.me'
);

echo "Testing New Grip Design Webhook\n";
echo "================================\n";
echo "Webhook URL: " . $webhook_url . "\n";
echo "Data to send:\n" . json_encode($webhook_data, JSON_PRETTY_PRINT) . "\n\n";

// Send the webhook
$response = wp_remote_post($webhook_url, array(
    'headers' => array(
        'Content-Type' => 'application/json',
        'User-Agent' => 'TwinTack-Grip-Manager/1.5.19-Test'
    ),
    'body' => json_encode($webhook_data),
    'timeout' => 15
));

// Check response
if (is_wp_error($response)) {
    echo "❌ Webhook failed: " . $response->get_error_message() . "\n";
} else {
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    echo "✅ Webhook sent successfully!\n";
    echo "Response Code: " . $response_code . "\n";
    echo "Response Body: " . $response_body . "\n";
    
    if ($response_code == 200) {
        echo "\n🎉 SUCCESS: Make.com should have received the webhook data!\n";
        echo "Check your Make.com scenario execution history to see the data.\n";
    } else {
        echo "\n⚠️  WARNING: Unexpected response code. Check Make.com scenario.\n";
    }
}

echo "\nThis webhook should be automatically triggered when:\n";
echo "- A customer completes a Custom Grip Design Deposit purchase\n";
echo "- The order status changes to 'processing' or 'completed'\n";
echo "- A grip design post is created from the order\n";
?> 