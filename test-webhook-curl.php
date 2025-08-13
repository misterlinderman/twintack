<?php
/**
 * Webhook Test using cURL (doesn't require WordPress functions)
 */

$webhook_url = 'https://hook.us2.make.com/gcyjyjgpodj6olnbs8oii3ofying0z6w';

// Test data
$test_data = array(
    'test' => true,
    'message' => 'cURL webhook test',
    'timestamp' => date('c')
);

echo "<h1>cURL Webhook Test</h1>";
echo "<p><strong>Webhook URL:</strong> " . htmlspecialchars($webhook_url) . "</p>";
echo "<p><strong>Test Data:</strong></p>";
echo "<pre>" . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>";

echo "<h3>Sending webhook...</h3>";

// Use cURL instead of wp_remote_post
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhook_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($test_data))
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<div style='color: red;'>";
    echo "<h3>❌ cURL Error</h3>";
    echo "<p>" . htmlspecialchars($error) . "</p>";
    echo "</div>";
} else {
    echo "<div style='color: " . ($http_code == 200 ? 'green' : 'orange') . ";'>";
    echo "<h3>📡 Webhook Response</h3>";
    echo "<p><strong>HTTP Code:</strong> " . $http_code . "</p>";
    echo "<p><strong>Response:</strong> " . htmlspecialchars($response) . "</p>";
    
    if ($http_code == 200) {
        echo "<p>✅ <strong>Webhook sent successfully!</strong></p>";
    }
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>Next:</strong> Check your Make.com scenario execution history for this test.</p>";
echo "<p><em>Delete this file after testing.</em></p>";
?>
