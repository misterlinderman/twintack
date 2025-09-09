<?php
/**
 * Plugin Name: TwinTack Registration Debug
 * Description: Debug tool to identify what's blocking user registration
 * Version: 1.0.0
 * Author: TwinTack Development
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TwinTack Registration Debug Plugin
 */
class TwinTack_Registration_Debug {
    
    /**
     * Initialize the plugin
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_twintack_debug_clear_rate_limit', array($this, 'ajax_clear_rate_limit'));
        add_action('wp_ajax_twintack_debug_test_email', array($this, 'ajax_test_email'));
        add_action('wp_ajax_twintack_debug_test_redirect', array($this, 'ajax_test_redirect'));
        add_action('wp_ajax_twintack_debug_fix_redirect_function', array($this, 'ajax_fix_redirect_function'));
        
        // Auto-apply the redirect function fix when plugin loads
        add_action('init', array($this, 'auto_fix_redirect_function'), 1);
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            'Registration Debug',
            'Registration Debug',
            'manage_options',
            'twintack-registration-debug',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        
        ?>
        <div class="wrap">
            <h1>TwinTack Registration Debug</h1>
            
            <div class="notice notice-info">
                <p><strong>This tool helps diagnose why user registration is being blocked.</strong></p>
                <p>Test email: <code>elevatorsct@gmail.com</code></p>
            </div>
            
            <div class="card" style="margin: 20px 0;">
                <h2 class="title">Quick Actions</h2>
                <p>
                     <button type="button" class="button button-primary" onclick="testEmail()">Test Email Validation</button>
                     <button type="button" class="button button-secondary" onclick="clearRateLimit()">Clear Rate Limiting</button>
                     <button type="button" class="button button-secondary" onclick="testRedirect()">Test Redirect URL</button>
                     <button type="button" class="button button-primary" onclick="fixRedirectFunction()" style="background: #d63638; border-color: #d63638;">🔧 Fix Redirect Function</button>
                     <button type="button" class="button button-secondary" onclick="location.reload()">Refresh Results</button>
                </p>
            </div>
            
            <div class="card" style="margin: 20px 0;">
                <h2 class="title">Debug Results</h2>
                <div id="debug-results" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin: 10px 0;">
                    <pre style="font-family: monospace; font-size: 12px; line-height: 1.4; white-space: pre-wrap;"><?php echo esc_html($this->run_debug()); ?></pre>
                </div>
            </div>
        </div>
        
        <script>
        function testEmail() {
            if (confirm('Test email validation for elevatorsct@gmail.com?')) {
                jQuery.post(ajaxurl, {
                    action: 'twintack_debug_test_email',
                    email: 'elevatorsct@gmail.com',
                    username: 'elevators'
                }, function(response) {
                    alert('Result: ' + (response.success ? 'ALLOWED' : 'BLOCKED - ' + response.data));
                });
            }
        }
        
        function clearRateLimit() {
            if (confirm('Clear rate limiting for your IP address?')) {
                jQuery.post(ajaxurl, {
                    action: 'twintack_debug_clear_rate_limit'
                }, function(response) {
                    alert(response.data);
                    location.reload();
                });
            }
        }
        
         function testRedirect() {
             if (confirm('Test the registration redirect URL?')) {
                 jQuery.post(ajaxurl, {
                     action: 'twintack_debug_test_redirect'
                 }, function(response) {
                     if (response.success) {
                         alert('Redirect test results:\n' + response.data);
                     } else {
                         alert('Redirect test failed: ' + response.data);
                     }
                 });
             }
         }
         
         function fixRedirectFunction() {
             if (confirm('🔧 Fix the registration redirect function?\n\nThis will temporarily fix the "Too few arguments" error that is causing the white screen issue.')) {
                 jQuery.post(ajaxurl, {
                     action: 'twintack_debug_fix_redirect_function'
                 }, function(response) {
                     if (response.success) {
                         alert('✅ SUCCESS!\n\n' + response.data + '\n\n🎯 Try registration now - the white screen issue should be resolved!');
                         location.reload();
                     } else {
                         alert('❌ Fix failed: ' + response.data);
                     }
                 });
             }
         }
        </script>
        
        <style>
        .card { background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 20px; }
        .title { margin-top: 0; }
        </style>
        <?php
    }
    
    /**
     * Run the debug diagnostics
     */
    private function run_debug() {
        $output = '';
        
        // Test email
        $email = 'elevatorsct@gmail.com';
        $username = 'elevators';
        
        $output .= "=== TwinTack Security Registration Debug ===\n";
        $output .= "Testing email: $email\n";
        $output .= "Testing username: $username\n";
        $output .= "Current time: " . current_time('mysql') . "\n\n";
        
        // Check if security plugin is active
        if (!class_exists('TwinTack_Security_Core')) {
            $output .= "❌ TwinTack Security plugin not found or not active\n";
            return $output;
        }
        
        $output .= "✅ TwinTack Security plugin is active\n\n";
        
        // Check main security settings
        $output .= "=== Security Settings ===\n";
        $settings = [
            'twintack_security_enabled' => get_option('twintack_security_enabled', 'not set'),
            'twintack_security_sms_blocking_enabled' => get_option('twintack_security_sms_blocking_enabled', 'not set'),
            'twintack_security_email_validation_enabled' => get_option('twintack_security_email_validation_enabled', 'not set'),
            'twintack_security_rate_limiting_enabled' => get_option('twintack_security_rate_limiting_enabled', 'not set'),
            'twintack_security_rate_limit_attempts' => get_option('twintack_security_rate_limit_attempts', 'not set'),
            'twintack_security_rate_limit_window' => get_option('twintack_security_rate_limit_window', 'not set'),
        ];
        
        foreach ($settings as $setting => $value) {
            $output .= "$setting: $value\n";
        }
        $output .= "\n";
        
        // Test rate limiting
        $output .= "=== Rate Limiting Check ===\n";
        if (get_option('twintack_security_rate_limiting_enabled') !== 'yes') {
            $output .= "✅ Rate limiting is DISABLED\n";
        } else {
            $output .= "⚠️  Rate limiting is ENABLED\n";
            
            // Get current IP
            $ip_address = $this->get_client_ip();
            $attempts = (int) get_option('twintack_security_rate_limit_attempts', 5);
            $window = (int) get_option('twintack_security_rate_limit_window', 300);
            
            $output .= "IP Address: $ip_address\n";
            $output .= "Max attempts: $attempts\n";
            $output .= "Time window: $window seconds (" . ($window/60) . " minutes)\n";
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'twintack_security_log';
            
            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
            if (!$table_exists) {
                $output .= "⚠️  Security log table does not exist: $table_name\n";
            } else {
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name 
                     WHERE ip_address = %s 
                     AND event_type IN ('registration_blocked', 'woo_registration_blocked', 'login_failed')
                     AND timestamp > DATE_SUB(NOW(), INTERVAL %d SECOND)",
                    $ip_address,
                    $window
                ));
                
                $output .= "Recent blocked attempts from IP: $count\n";
                if ($count >= $attempts) {
                    $output .= "❌ IP IS RATE LIMITED! ($count >= $attempts)\n";
                    $output .= "   ➤ SOLUTION: Click 'Clear Rate Limiting' button above\n";
                } else {
                    $output .= "✅ IP is not rate limited ($count < $attempts)\n";
                }
            }
        }
        $output .= "\n";
        
        // Test SMS gateway blocking
        $output .= "=== SMS Gateway Blocking Check ===\n";
        if (get_option('twintack_security_sms_blocking_enabled') !== 'yes') {
            $output .= "✅ SMS gateway blocking is DISABLED\n";
        } else {
            $output .= "⚠️  SMS gateway blocking is ENABLED\n";
            
            if (class_exists('TwinTack_Security_Spam_Protection')) {
                $spam_check = TwinTack_Security_Spam_Protection::instance()->is_spam_email($email);
                if ($spam_check) {
                    $output .= "❌ EMAIL IS BLOCKED by SMS gateway protection: $spam_check\n";
                    $output .= "   ➤ ISSUE: gmail.com is being treated as SMS gateway\n";
                } else {
                    $output .= "✅ Email passes SMS gateway check\n";
                }
            } else {
                $output .= "⚠️  TwinTack_Security_Spam_Protection class not found\n";
            }
        }
        $output .= "\n";
        
        // Test email validation
        $output .= "=== Email Validation Check ===\n";
        if (get_option('twintack_security_email_validation_enabled') !== 'yes') {
            $output .= "✅ Email validation is DISABLED\n";
        } else {
            $output .= "⚠️  Email validation is ENABLED\n";
            
            if (class_exists('TwinTack_Security_Email_Validator')) {
                $email_validation = TwinTack_Security_Email_Validator::instance()->validate_email($email, $username);
                if (is_wp_error($email_validation)) {
                    $output .= "❌ EMAIL IS BLOCKED by email validator:\n";
                    $output .= "   Error Code: " . $email_validation->get_error_code() . "\n";
                    $output .= "   Error Message: " . $email_validation->get_error_message() . "\n";
                    $output .= "   ➤ ISSUE: Email validation rules are still too strict\n";
                } else {
                    $output .= "✅ Email passes validation check\n";
                }
            } else {
                $output .= "⚠️  TwinTack_Security_Email_Validator class not found\n";
            }
        }
        $output .= "\n";
        
        // Check blocked domains list
        $output .= "=== Blocked Domains Check ===\n";
        $blocked_domains = get_option('twintack_security_blocked_domains', []);
        if (empty($blocked_domains)) {
            $output .= "✅ No domains in blocked list\n";
        } else {
            $output .= "Blocked domains (" . count($blocked_domains) . "):\n";
            foreach ($blocked_domains as $domain) {
                $output .= "  - $domain\n";
                if ($domain === 'gmail.com') {
                    $output .= "    ❌ PROBLEM: gmail.com is blocked!\n";
                }
            }
        }
        $output .= "\n";
        
        // Check recent security log entries
        $output .= "=== Recent Security Log Entries (Last Hour) ===\n";
        global $wpdb;
        $table_name = $wpdb->prefix . 'twintack_security_log';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            $output .= "⚠️  Security log table does not exist\n";
        } else {
            $recent_entries = $wpdb->get_results(
                "SELECT timestamp, event_type, details, severity, ip_address
                 FROM $table_name 
                 WHERE timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                 ORDER BY timestamp DESC 
                 LIMIT 10",
                ARRAY_A
            );
            
            if (empty($recent_entries)) {
                $output .= "No recent security log entries found\n";
            } else {
                foreach ($recent_entries as $entry) {
                    $details = json_decode($entry['details'], true);
                    
                    // Highlight critical/high severity events
                    $prefix = '';
                    if ($entry['severity'] === 'critical') {
                        $prefix = '🚨 CRITICAL: ';
                    } elseif ($entry['severity'] === 'high') {
                        $prefix = '⚠️  HIGH: ';
                    } elseif (strpos($entry['event_type'], 'fatal') !== false) {
                        $prefix = '💥 FATAL: ';
                    } elseif (strpos($entry['event_type'], 'error') !== false) {
                        $prefix = '❌ ERROR: ';
                    }
                    
                    $output .= "[{$entry['timestamp']}] $prefix{$entry['event_type']} ({$entry['severity']}) - IP: {$entry['ip_address']}\n";
                    
                    if (isset($details['email']) && strpos($details['email'], 'elevatorsct') !== false) {
                        $output .= "  ⭐ Email: {$details['email']} (THIS IS OUR TEST EMAIL!)\n";
                    } elseif (isset($details['email'])) {
                        $output .= "  Email: {$details['email']}\n";
                    }
                    
                    if (isset($details['reason'])) {
                        $output .= "  Reason: {$details['reason']}\n";
                    }
                    
                    if (isset($details['message'])) {
                        $output .= "  Error: {$details['message']}\n";
                    }
                    
                    if (isset($details['file']) && isset($details['line'])) {
                        $output .= "  Location: {$details['file']}:{$details['line']}\n";
                    }
                    
                    if (isset($details['request_uri'])) {
                        $output .= "  URL: {$details['request_uri']}\n";
                    }
                    
                    $output .= "\n";
                }
            }
        }
        
         // Check if our auto-fix is active
         $output .= "=== Registration Function Fix Status ===\n";
         if (has_filter('woocommerce_registration_redirect', array($this, 'fixed_checkout_registration_redirect'))) {
             $output .= "✅ Auto-fix is ACTIVE - Registration redirect function has been patched\n";
             $output .= "   The problematic 'twintack_checkout_registration_redirect' function has been replaced\n";
             $output .= "   with a fixed version that accepts 1 parameter instead of 2.\n";
         } else {
             $output .= "⚠️  Auto-fix is NOT active - Original function may still cause issues\n";
             $output .= "   Try clicking the '🔧 Fix Redirect Function' button above.\n";
         }
         $output .= "\n";
         
         $output .= "=== Debug Complete ===\n";
         $output .= "If you see ❌ symbols above, those indicate the problems blocking registration.\n";
         
         return $output;
    }
    
    /**
     * AJAX handler to test email validation
     */
    public function ajax_test_email() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
            return;
        }
        
        $email = sanitize_email($_POST['email']);
        $username = sanitize_text_field($_POST['username']);
        
        if (!class_exists('TwinTack_Security_Core')) {
            wp_send_json_error('TwinTack Security plugin not active');
            return;
        }
        
        // Test the validation directly
        $core = TwinTack_Security_Core::instance();
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($core);
        $method = $reflection->getMethod('validate_user_data');
        $method->setAccessible(true);
        
        try {
            $result = $method->invoke($core, $username, $email);
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_code() . ': ' . $result->get_error_message());
            } else {
                wp_send_json_success('Email would be allowed');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error testing: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler to clear rate limiting
     */
    public function ajax_clear_rate_limit() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'twintack_security_log';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        if (!$table_exists) {
            wp_send_json_success('Security log table does not exist - rate limiting should not be active');
            return;
        }
        
        $ip_address = $this->get_client_ip();
        
        // Clear recent blocked attempts for this IP
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name 
             WHERE ip_address = %s 
             AND event_type IN ('registration_blocked', 'woo_registration_blocked', 'login_failed')
             AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            $ip_address
        ));
        
        wp_send_json_success("Cleared $deleted recent blocked attempts for IP: $ip_address");
    }
    
    /**
     * AJAX handler to test redirect URL
     */
    public function ajax_test_redirect() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
            return;
        }
        
        $redirect_url = 'https://twintack.com/twintack-custom-grips/grip-configurator/';
        $results = '';
        
        $results .= "Testing redirect URL: $redirect_url\n\n";
        
        // Test URL accessibility
        $results .= "=== URL Accessibility ===\n";
        $headers = @get_headers($redirect_url);
        if ($headers) {
            $results .= "✅ URL is accessible\n";
            $results .= "Response: " . $headers[0] . "\n";
        } else {
            $results .= "❌ URL is not accessible\n";
        }
        
        // Check if page exists in WordPress
        $results .= "\n=== WordPress Page Check ===\n";
        $page = get_page_by_path('twintack-custom-grips/grip-configurator');
        if ($page) {
            $results .= "✅ Page exists in WordPress\n";
            $results .= "Page ID: " . $page->ID . "\n";
            $results .= "Page Status: " . $page->post_status . "\n";
        } else {
            $results .= "❌ Page not found in WordPress\n";
            
            // Try alternative paths
            $alt_paths = ['grip-configurator', 'twintack-custom-grips', 'custom-grips/grip-configurator'];
            foreach ($alt_paths as $path) {
                $alt_page = get_page_by_path($path);
                if ($alt_page) {
                    $results .= "✅ Found alternative: $path (ID: {$alt_page->ID})\n";
                }
            }
        }
        
        // Check rewrite rules
        $results .= "\n=== Permalink Check ===\n";
        global $wp_rewrite;
        if ($wp_rewrite->using_permalinks()) {
            $results .= "✅ Permalinks enabled: " . get_option('permalink_structure') . "\n";
        } else {
            $results .= "❌ Permalinks not enabled\n";
        }
        
        wp_send_json_success($results);
    }
    
     /**
      * Auto-fix the redirect function when plugin loads
      */
     public function auto_fix_redirect_function() {
         // Only fix if the problematic function exists
         if (function_exists('twintack_checkout_registration_redirect')) {
             // Remove the problematic hook
             remove_filter('woocommerce_registration_redirect', 'twintack_checkout_registration_redirect', 20);
             
             // Add the fixed version that accepts 1 parameter
             add_filter('woocommerce_registration_redirect', array($this, 'fixed_checkout_registration_redirect'), 20, 1);
             
             // Log the fix for debugging
             if (defined('WP_DEBUG') && WP_DEBUG) {
                 error_log('TwinTack Debug Plugin: Auto-fixed registration redirect function parameter issue');
             }
         }
     }
     
     /**
      * AJAX handler to fix redirect function
      */
     public function ajax_fix_redirect_function() {
         if (!current_user_can('manage_options')) {
             wp_send_json_error('Access denied');
             return;
         }
         
         // Remove the problematic hook
         $removed = remove_filter('woocommerce_registration_redirect', 'twintack_checkout_registration_redirect', 20);
         
         if (!$removed) {
             wp_send_json_error('Could not remove the problematic hook. It may not be active or may have different parameters.');
             return;
         }
         
         // Add the fixed version that accepts 1 parameter
         add_filter('woocommerce_registration_redirect', array($this, 'fixed_checkout_registration_redirect'), 20, 1);
         
         $message = "🔧 Registration redirect function has been fixed!\n\n";
         $message .= "✅ Removed problematic hook: twintack_checkout_registration_redirect\n";
         $message .= "✅ Added fixed version that accepts 1 parameter\n";
         $message .= "✅ Registration should now work without the white screen\n\n";
         $message .= "⚠️  This is a temporary fix that lasts until the page reloads.\n";
         $message .= "   Update your functions.php file for a permanent solution.";
         
         wp_send_json_success($message);
     }
     
     /**
      * Fixed version of the checkout registration redirect function
      */
     public function fixed_checkout_registration_redirect($redirect_url) {
         // Check if this is a checkout registration
         if (isset($_POST['createaccount']) && $_POST['createaccount'] == '1' && !wp_doing_ajax()) {
             if (WP_DEBUG === true) {
                 error_log('TwinTack Debug: Account created during checkout, redirecting to My Account');
             }
             // Redirect to My Account page after checkout completion
             return wc_get_page_permalink('myaccount');
         }
         return $redirect_url;
     }
     
     /**
      * Get client IP address
      */
     private function get_client_ip() {
        $ip_fields = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_fields as $field) {
            if (!empty($_SERVER[$field])) {
                $ip = $_SERVER[$field];
                // Handle comma-separated IPs (load balancers, proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

// Initialize the plugin
new TwinTack_Registration_Debug();
