<?php
/**
 * Simple Webhook Test - Just receives data, no Monday.com updates
 */

$webhook_url = 'https://hook.us2.make.com/gcyjyjgpodj6olnbs8oii3ofying0z6w';

// Minimal test data
$test_data = array(
    'test' => true,
    'message' => 'Simple webhook test',
    'timestamp' => date('c')
);

echo "<h1>Simple Webhook Test</h1>";
echo "<p>Sending minimal data to webhook...</p>";
echo "<pre>" . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>";

// Send with shorter timeout
$response = wp_remote_post($webhook_url, array(
    'headers' => array(
        'Content-Type' => 'application/json'
    ),
    'body' => json_encode($test_data),
    'timeout' => 5, // Short timeout
    'blocking' => true
));

if (is_wp_error($response)) {
    echo "<p style='color: red;'>Error: " . $response->get_error_message() . "</p>";
} else {
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    echo "<p style='color: green;'>Response Code: " . $code . "</p>";
    echo "<p>Response: " . htmlspecialchars($body) . "</p>";
}

echo "<p><em>Check Make.com scenario execution history now.</em></p>";
?>
