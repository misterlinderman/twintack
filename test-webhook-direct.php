<?php
/**
 * Direct Webhook Test for Make.com Customer Feedback
 * 
 * This script tests the webhook URL directly to ensure Make.com can receive data
 */

// Test webhook URL
$webhook_url = 'https://hook.us2.make.com/gcyjyjgpodj6olnbs8oii3ofying0z6w';

// Test data matching the expected webhook payload
$test_data = array(
    'grip_design_id' => 1669,
    'grip_design_title' => 'Test Grip Design - Webhook Test',
    'customer_action' => 'request_changes',
    'customer_feedback' => 'This is a webhook test - please make the logo bigger',
    'latest_customer_feedback' => 'This is a webhook test - please make the logo bigger',
    'artwork_status' => 'customer_requested_changes',
    'customer_name' => 'Test Customer',
    'customer_email' => 'test@twintack.com',
    'team_name' => 'Test Team',
    'quantity' => 25,
    'monday_item_id' => '1234567890',
    'timestamp' => date('c'), // Current timestamp in ISO 8601 format
    'revision_count' => 1,
    'webhook_type' => 'customer_feedback_test'
);

echo "<h1>TwinTack Webhook Test</h1>";
echo "<h2>Testing Make.com Customer Feedback Webhook</h2>";

echo "<h3>Webhook URL:</h3>";
echo "<code>" . htmlspecialchars($webhook_url) . "</code>";

echo "<h3>Test Data Being Sent:</h3>";
echo "<pre>" . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>";

echo "<h3>Sending Webhook...</h3>";

// Send the webhook
$response = wp_remote_post($webhook_url, array(
    'headers' => array(
        'Content-Type' => 'application/json',
        'User-Agent' => 'TwinTack-Webhook-Test/1.0'
    ),
    'body' => json_encode($test_data),
    'timeout' => 15,
    'blocking' => true
));

// Check response
if (is_wp_error($response)) {
    echo "<div style='color: red;'>";
    echo "<h3>❌ Webhook Failed</h3>";
    echo "<p><strong>Error:</strong> " . $response->get_error_message() . "</p>";
    echo "</div>";
} else {
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    echo "<div style='color: " . ($response_code == 200 ? 'green' : 'orange') . ";'>";
    echo "<h3>📡 Webhook Response</h3>";
    echo "<p><strong>Response Code:</strong> " . $response_code . "</p>";
    echo "<p><strong>Response Body:</strong> " . htmlspecialchars($response_body) . "</p>";
    
    if ($response_code == 200) {
        echo "<p>✅ <strong>Webhook sent successfully!</strong> Check your Make.com scenario to see if this test webhook was received.</p>";
    } else {
        echo "<p>⚠️ <strong>Unexpected response code.</strong> The webhook may still have been received. Check Make.com.</p>";
    }
    echo "</div>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><strong>Check Make.com:</strong> Look for this test webhook in your scenario execution history</li>";
echo "<li><strong>Verify JSON settings:</strong> Ensure 'JSON pass-through' is set to 'Yes' in webhook settings</li>";
echo "<li><strong>Test customer feedback:</strong> If this test works, try submitting actual customer feedback</li>";
echo "<li><strong>Delete this file:</strong> Remove this test file after confirming webhook works</li>";
echo "</ol>";

echo "<p><em>Delete this file after testing.</em></p>";
?>
