<?php
/**
 * WordPress Admin Debug Script
 * Add this to your theme's functions.php temporarily, or run as a plugin
 */

// Add admin menu item for debugging
add_action('admin_menu', 'twintack_debug_registration_menu');

function twintack_debug_registration_menu() {
    add_management_page(
        'Registration Debug',
        'Registration Debug',
        'manage_options',
        'twintack-registration-debug',
        'twintack_debug_registration_page'
    );
}

function twintack_debug_registration_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }
    
    echo '<div class="wrap">';
    echo '<h1>TwinTack Registration Debug</h1>';
    echo '<div style="background: #fff; padding: 20px; border: 1px solid #ddd; margin: 20px 0;">';
    echo '<pre style="font-family: monospace; font-size: 12px; line-height: 1.4; overflow-x: auto;">';
    
    // Start output buffering to capture the debug output
    ob_start();
    
    // Test email
    $email = 'elevatorsct@gmail.com';
    $username = 'elevators';
    
    echo "=== TwinTack Security Registration Debug ===\n";
    echo "Testing email: $email\n";
    echo "Testing username: $username\n\n";
    
    // Check if security plugin is active and enabled
    if (!class_exists('TwinTack_Security_Core')) {
        echo "❌ TwinTack Security plugin not found or not active\n";
        echo '</pre></div></div>';
        return;
    }
    
    echo "✅ TwinTack Security plugin is active\n\n";
    
    // Check main security settings
    echo "=== Security Settings ===\n";
    $settings = [
        'twintack_security_enabled' => get_option('twintack_security_enabled', 'not set'),
        'twintack_security_sms_blocking_enabled' => get_option('twintack_security_sms_blocking_enabled', 'not set'),
        'twintack_security_email_validation_enabled' => get_option('twintack_security_email_validation_enabled', 'not set'),
        'twintack_security_rate_limiting_enabled' => get_option('twintack_security_rate_limiting_enabled', 'not set'),
        'twintack_security_rate_limit_attempts' => get_option('twintack_security_rate_limit_attempts', 'not set'),
        'twintack_security_rate_limit_window' => get_option('twintack_security_rate_limit_window', 'not set'),
    ];
    
    foreach ($settings as $setting => $value) {
        echo "$setting: $value\n";
    }
    echo "\n";
    
    // Test rate limiting
    echo "=== Rate Limiting Check ===\n";
    if (get_option('twintack_security_rate_limiting_enabled') !== 'yes') {
        echo "✅ Rate limiting is DISABLED\n";
    } else {
        echo "⚠️  Rate limiting is ENABLED\n";
        
        // Get current IP
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $attempts = (int) get_option('twintack_security_rate_limit_attempts', 5);
        $window = (int) get_option('twintack_security_rate_limit_window', 300);
        
        echo "IP Address: $ip_address\n";
        echo "Max attempts: $attempts\n";
        echo "Time window: $window seconds\n";
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'twintack_security_log';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) {
            echo "⚠️  Security log table does not exist: $table_name\n";
        } else {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name 
                 WHERE ip_address = %s 
                 AND event_type IN ('registration_blocked', 'woo_registration_blocked', 'login_failed')
                 AND timestamp > DATE_SUB(NOW(), INTERVAL %d SECOND)",
                $ip_address,
                $window
            ));
            
            echo "Recent blocked attempts from IP: $count\n";
            if ($count >= $attempts) {
                echo "❌ IP IS RATE LIMITED! ($count >= $attempts)\n";
            } else {
                echo "✅ IP is not rate limited ($count < $attempts)\n";
            }
        }
    }
    echo "\n";
    
    // Test SMS gateway blocking
    echo "=== SMS Gateway Blocking Check ===\n";
    if (get_option('twintack_security_sms_blocking_enabled') !== 'yes') {
        echo "✅ SMS gateway blocking is DISABLED\n";
    } else {
        echo "⚠️  SMS gateway blocking is ENABLED\n";
        
        if (class_exists('TwinTack_Security_Spam_Protection')) {
            $spam_check = TwinTack_Security_Spam_Protection::instance()->is_spam_email($email);
            if ($spam_check) {
                echo "❌ EMAIL IS BLOCKED by SMS gateway protection: $spam_check\n";
            } else {
                echo "✅ Email passes SMS gateway check\n";
            }
        } else {
            echo "⚠️  TwinTack_Security_Spam_Protection class not found\n";
        }
    }
    echo "\n";
    
    // Test email validation
    echo "=== Email Validation Check ===\n";
    if (get_option('twintack_security_email_validation_enabled') !== 'yes') {
        echo "✅ Email validation is DISABLED\n";
    } else {
        echo "⚠️  Email validation is ENABLED\n";
        
        if (class_exists('TwinTack_Security_Email_Validator')) {
            $email_validation = TwinTack_Security_Email_Validator::instance()->validate_email($email, $username);
            if (is_wp_error($email_validation)) {
                echo "❌ EMAIL IS BLOCKED by email validator:\n";
                echo "   Error Code: " . $email_validation->get_error_code() . "\n";
                echo "   Error Message: " . $email_validation->get_error_message() . "\n";
            } else {
                echo "✅ Email passes validation check\n";
            }
        } else {
            echo "⚠️  TwinTack_Security_Email_Validator class not found\n";
        }
    }
    echo "\n";
    
    // Check recent security log entries
    echo "=== Recent Security Log Entries ===\n";
    global $wpdb;
    $table_name = $wpdb->prefix . 'twintack_security_log';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    
    if (!$table_exists) {
        echo "⚠️  Security log table does not exist\n";
    } else {
        $recent_entries = $wpdb->get_results(
            "SELECT timestamp, event_type, details, severity 
             FROM $table_name 
             WHERE timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             ORDER BY timestamp DESC 
             LIMIT 10",
            ARRAY_A
        );
        
        if (empty($recent_entries)) {
            echo "No recent security log entries found\n";
        } else {
            foreach ($recent_entries as $entry) {
                $details = json_decode($entry['details'], true);
                echo "[{$entry['timestamp']}] {$entry['event_type']} ({$entry['severity']})\n";
                if (isset($details['email'])) {
                    echo "  Email: {$details['email']}\n";
                }
                if (isset($details['reason'])) {
                    echo "  Reason: {$details['reason']}\n";
                }
                echo "\n";
            }
        }
    }
    
    echo "=== Debug Complete ===\n";
    
    echo '</pre>';
    echo '</div>';
    echo '</div>';
}

// Auto-remove this code after 24 hours for security
add_action('init', function() {
    if (get_option('twintack_debug_registration_added') && 
        time() - get_option('twintack_debug_registration_added') > 86400) {
        // Remove the admin menu after 24 hours
        remove_action('admin_menu', 'twintack_debug_registration_menu');
    } else if (!get_option('twintack_debug_registration_added')) {
        update_option('twintack_debug_registration_added', time());
    }
});
