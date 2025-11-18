<?php
/**
 * Manual database setup and test data creation for TwinTack Security
 * 
 * Run this file once to ensure the database is properly set up
 * Delete this file after use
 */

// Include WordPress
require_once('../../../wp-config.php');

if (!current_user_can('manage_options')) {
    die('Access denied');
}

global $wpdb;

$table_name = $wpdb->prefix . 'twintack_security_log';
$charset_collate = $wpdb->get_charset_collate();

// Create the table
$sql = "CREATE TABLE $table_name (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    timestamp datetime DEFAULT CURRENT_TIMESTAMP,
    event_type varchar(50) NOT NULL,
    ip_address varchar(45) NOT NULL,
    user_email varchar(254) DEFAULT NULL,
    details text DEFAULT NULL,
    severity enum('low','medium','high','critical') DEFAULT 'medium',
    user_agent text DEFAULT NULL,
    action_taken varchar(100) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_timestamp (timestamp),
    KEY idx_ip_address (ip_address),
    KEY idx_event_type (event_type)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
$result = dbDelta($sql);

echo "<h2>TwinTack Security Database Setup</h2>";
echo "<p>Database table creation result:</p>";
echo "<pre>" . print_r($result, true) . "</pre>";

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
echo "<p>Table exists: " . ($table_exists ? 'YES' : 'NO') . "</p>";

// Add some test log entries
if ($table_exists) {
    // Sample blocked registration attempts
    $test_logs = array(
        array(
            'event_type' => 'registration_blocked',
            'ip_address' => '192.168.1.100',
            'user_email' => '2064303465@vtext.com',
            'details' => json_encode(array(
                'username' => '2064303465',
                'email' => '2064303465@vtext.com',
                'reason' => 'SMS gateway domain blocked: vtext.com'
            )),
            'severity' => 'medium',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'action_taken' => 'Registration blocked'
        ),
        array(
            'event_type' => 'woo_registration_blocked',
            'ip_address' => '95.164.235.62',
            'user_email' => '5551234567@tmomail.net',
            'details' => json_encode(array(
                'username' => '5551234567',
                'email' => '5551234567@tmomail.net',
                'reason' => 'SMS gateway domain blocked: tmomail.net'
            )),
            'severity' => 'medium',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
            'action_taken' => 'WooCommerce registration blocked'
        ),
        array(
            'event_type' => 'registration_success',
            'ip_address' => '73.162.45.123',
            'user_email' => 'john.doe@gmail.com',
            'details' => json_encode(array(
                'user_id' => 999,
                'username' => 'johndoe',
                'email' => 'john.doe@gmail.com'
            )),
            'severity' => 'low',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            'action_taken' => 'Registration allowed'
        )
    );

    $inserted = 0;
    foreach ($test_logs as $log) {
        $result = $wpdb->insert($table_name, $log);
        if ($result) {
            $inserted++;
        }
    }
    
    echo "<p>Test log entries inserted: $inserted</p>";
}

// Update plugin options
update_option('twintack_security_db_version', '1.0.0');
update_option('twintack_security_enabled', 'yes');
update_option('twintack_security_sms_blocking_enabled', 'yes');
update_option('twintack_security_email_validation_enabled', 'yes');

echo "<p>Plugin options updated.</p>";
echo "<p><strong>Setup complete!</strong> You can now delete this file and refresh your admin page.</p>";
echo "<p><a href='../../../wp-admin/admin.php?page=twintack-security-logs'>View Security Logs</a></p>";
?>
