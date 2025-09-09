<?php
/**
 * Reset rate limiting for TwinTack Security plugin
 * This clears recent blocked attempts to allow testing
 */

// Include WordPress
require_once('wp-config.php');

echo "=== TwinTack Security Rate Limit Reset ===\n";

// Check if security plugin is active
if (!class_exists('TwinTack_Security_Core')) {
    echo "❌ TwinTack Security plugin not found or not active\n";
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'twintack_security_log';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
if (!$table_exists) {
    echo "⚠️  Security log table does not exist: $table_name\n";
    echo "Rate limiting data not found - may already be clear\n";
    exit;
}

// Get current IP
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
echo "Current IP: $ip_address\n";

// Count recent blocked attempts
$count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $table_name 
     WHERE ip_address = %s 
     AND event_type IN ('registration_blocked', 'woo_registration_blocked', 'login_failed')
     AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
    $ip_address
));

echo "Recent blocked attempts from your IP: $count\n";

if ($count > 0) {
    // Clear recent blocked attempts for this IP
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name 
         WHERE ip_address = %s 
         AND event_type IN ('registration_blocked', 'woo_registration_blocked', 'login_failed')
         AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        $ip_address
    ));
    
    echo "✅ Cleared $deleted recent blocked attempts for your IP\n";
    echo "You should now be able to attempt registration again\n";
} else {
    echo "✅ No recent blocked attempts found - rate limiting should not be blocking you\n";
}

// Also show rate limiting settings
echo "\n=== Current Rate Limiting Settings ===\n";
echo "Rate limiting enabled: " . get_option('twintack_security_rate_limiting_enabled', 'not set') . "\n";
echo "Max attempts: " . get_option('twintack_security_rate_limit_attempts', 'not set') . "\n";
echo "Time window: " . get_option('twintack_security_rate_limit_window', 'not set') . " seconds\n";

echo "\n=== Reset Complete ===\n";
