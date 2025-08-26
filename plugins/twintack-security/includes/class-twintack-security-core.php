<?php
/**
 * Core functionality for TwinTack Security
 *
 * @package TwinTack_Security
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TwinTack Security Core class
 */
class TwinTack_Security_Core {

    /**
     * Single instance of the class
     *
     * @var TwinTack_Security_Core
     */
    private static $_instance = null;

    /**
     * Main instance
     *
     * @return TwinTack_Security_Core
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // WordPress registration hooks
        add_filter('pre_user_login', array($this, 'validate_registration'), 10, 1);
        add_filter('registration_errors', array($this, 'validate_registration_errors'), 10, 3);
        
        // WooCommerce registration hooks
        add_action('woocommerce_register_post', array($this, 'validate_woo_registration'), 10, 3);
        add_filter('woocommerce_registration_errors', array($this, 'validate_woo_registration_errors'), 10, 4);
        
        // Comment hooks (optional protection)
        add_filter('pre_comment_approved', array($this, 'validate_comment'), 10, 2);
        
        // Rate limiting hooks
        add_action('wp_login_failed', array($this, 'handle_failed_login'));
        add_action('user_register', array($this, 'track_registration'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // AJAX hooks for admin interface
        add_action('wp_ajax_twintack_security_test_email', array($this, 'ajax_test_email'));
        add_action('wp_ajax_twintack_security_bulk_delete_spam', array($this, 'ajax_bulk_delete_spam'));
    }

    /**
     * Validate user registration (WordPress core)
     *
     * @param string $sanitized_user_login The username
     * @return string
     */
    public function validate_registration($sanitized_user_login) {
        if (!$this->is_security_enabled()) {
            return $sanitized_user_login;
        }

        // Get the email from registration form
        $user_email = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';
        
        // Validate the email and username
        $validation_result = $this->validate_user_data($sanitized_user_login, $user_email);
        
        if (is_wp_error($validation_result)) {
            // Log the blocked attempt
            TwinTack_Security_Logger::instance()->log_security_event(
                'registration_blocked',
                array(
                    'username' => $sanitized_user_login,
                    'email' => $user_email,
                    'reason' => $validation_result->get_error_message()
                ),
                'medium'
            );
            
            // Block the registration
            wp_die(
                $this->get_user_friendly_error_message($validation_result->get_error_code()),
                __('Registration Blocked', 'twintack-security'),
                array('response' => 403)
            );
        }

        return $sanitized_user_login;
    }

    /**
     * Validate registration errors (WordPress core)
     *
     * @param WP_Error $errors Registration errors
     * @param string $sanitized_user_login Username
     * @param string $user_email Email
     * @return WP_Error
     */
    public function validate_registration_errors($errors, $sanitized_user_login, $user_email) {
        if (!$this->is_security_enabled()) {
            return $errors;
        }

        $validation_result = $this->validate_user_data($sanitized_user_login, $user_email);
        
        if (is_wp_error($validation_result)) {
            $errors->add(
                $validation_result->get_error_code(),
                $this->get_user_friendly_error_message($validation_result->get_error_code())
            );
            
            // Log the blocked attempt
            TwinTack_Security_Logger::instance()->log_security_event(
                'registration_blocked',
                array(
                    'username' => $sanitized_user_login,
                    'email' => $user_email,
                    'reason' => $validation_result->get_error_message()
                ),
                'medium'
            );
        }

        return $errors;
    }

    /**
     * Validate WooCommerce registration
     *
     * @param string $username Username
     * @param string $email Email
     * @param WP_Error $errors Registration errors
     */
    public function validate_woo_registration($username, $email, $errors) {
        if (!$this->is_security_enabled()) {
            return;
        }

        $validation_result = $this->validate_user_data($username, $email);
        
        if (is_wp_error($validation_result)) {
            $errors->add(
                $validation_result->get_error_code(),
                $this->get_user_friendly_error_message($validation_result->get_error_code())
            );
            
            // Log the blocked attempt
            TwinTack_Security_Logger::instance()->log_security_event(
                'woo_registration_blocked',
                array(
                    'username' => $username,
                    'email' => $email,
                    'reason' => $validation_result->get_error_message()
                ),
                'medium'
            );
        }
    }

    /**
     * Validate WooCommerce registration errors
     *
     * @param WP_Error $errors Registration errors
     * @param string $username Username
     * @param string $password Password
     * @param string $email Email
     * @return WP_Error
     */
    public function validate_woo_registration_errors($errors, $username, $password, $email) {
        if (!$this->is_security_enabled()) {
            return $errors;
        }

        $validation_result = $this->validate_user_data($username, $email);
        
        if (is_wp_error($validation_result)) {
            $errors->add(
                $validation_result->get_error_code(),
                $this->get_user_friendly_error_message($validation_result->get_error_code())
            );
        }

        return $errors;
    }

    /**
     * Core validation logic for user data
     *
     * @param string $username Username
     * @param string $email Email
     * @return bool|WP_Error True if valid, WP_Error if blocked
     */
    private function validate_user_data($username, $email) {
        // Rate limiting check
        if ($this->is_rate_limited()) {
            return new WP_Error(
                'rate_limited',
                'Too many registration attempts. Please try again later.'
            );
        }

        // SMS gateway email blocking
        if (get_option('twintack_security_sms_blocking_enabled') === 'yes') {
            $spam_check = TwinTack_Security_Spam_Protection::instance()->is_spam_email($email);
            if ($spam_check) {
                return new WP_Error(
                    'sms_gateway_blocked',
                    'SMS gateway email blocked: ' . $spam_check
                );
            }
        }

        // Enhanced email validation
        if (get_option('twintack_security_email_validation_enabled') === 'yes') {
            $email_validation = TwinTack_Security_Email_Validator::instance()->validate_email($email, $username);
            if (is_wp_error($email_validation)) {
                return $email_validation;
            }
        }

        return true;
    }

    /**
     * Check if security is enabled
     *
     * @return bool
     */
    private function is_security_enabled() {
        return get_option('twintack_security_enabled', 'yes') === 'yes';
    }

    /**
     * Check if current IP is rate limited
     *
     * @return bool
     */
    private function is_rate_limited() {
        if (get_option('twintack_security_rate_limiting_enabled') !== 'yes') {
            return false;
        }

        $ip_address = $this->get_client_ip();
        $attempts = (int) get_option('twintack_security_rate_limit_attempts', 5);
        $window = (int) get_option('twintack_security_rate_limit_window', 300); // 5 minutes

        global $wpdb;
        $table_name = $wpdb->prefix . 'twintack_security_log';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE ip_address = %s 
             AND event_type IN ('registration_blocked', 'woo_registration_blocked', 'login_failed')
             AND timestamp > DATE_SUB(NOW(), INTERVAL %d SECOND)",
            $ip_address,
            $window
        ));

        return $count >= $attempts;
    }

    /**
     * Get client IP address
     *
     * @return string
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

    /**
     * Get user-friendly error message
     *
     * @param string $error_code Error code
     * @return string
     */
    private function get_user_friendly_error_message($error_code) {
        $messages = array(
            'sms_gateway_blocked' => __('Please use a standard email address for registration. SMS/text message addresses are not supported.', 'twintack-security'),
            'numeric_email_blocked' => __('Please use a valid email address format for registration.', 'twintack-security'),
            'suspicious_pattern' => __('Please use a standard email address for registration.', 'twintack-security'),
            'rate_limited' => __('Too many registration attempts. Please wait a few minutes and try again.', 'twintack-security'),
            'invalid_email_format' => __('Please enter a valid email address.', 'twintack-security')
        );

        return isset($messages[$error_code]) ? $messages[$error_code] : __('Registration could not be completed. Please try again with a different email address.', 'twintack-security');
    }

    /**
     * Handle failed login attempts
     *
     * @param string $username Username
     */
    public function handle_failed_login($username) {
        TwinTack_Security_Logger::instance()->log_security_event(
            'login_failed',
            array(
                'username' => $username
            ),
            'low'
        );
    }

    /**
     * Track successful registrations
     *
     * @param int $user_id User ID
     */
    public function track_registration($user_id) {
        $user = get_user_by('id', $user_id);
        if ($user) {
            TwinTack_Security_Logger::instance()->log_security_event(
                'registration_success',
                array(
                    'user_id' => $user_id,
                    'username' => $user->user_login,
                    'email' => $user->user_email
                ),
                'low'
            );
        }
    }

    /**
     * Validate comments (optional protection)
     *
     * @param int|string|WP_Error $approved Comment approval status
     * @param array $commentdata Comment data
     * @return int|string|WP_Error
     */
    public function validate_comment($approved, $commentdata) {
        if (!$this->is_security_enabled()) {
            return $approved;
        }

        $email = $commentdata['comment_author_email'] ?? '';
        
        if (!empty($email)) {
            $spam_check = TwinTack_Security_Spam_Protection::instance()->is_spam_email($email);
            if ($spam_check) {
                TwinTack_Security_Logger::instance()->log_security_event(
                    'comment_blocked',
                    array(
                        'email' => $email,
                        'reason' => $spam_check
                    ),
                    'low'
                );
                return 'spam';
            }
        }

        return $approved;
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Check if WooCommerce is active
        if (!TwinTack_Security()->is_woocommerce_active()) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>' . __('TwinTack Security Suite:', 'twintack-security') . '</strong> ';
            echo __('WooCommerce is not active. Some security features may not work properly.', 'twintack-security');
            echo '</p></div>';
        }

        // Check for high security events in the last 24 hours
        global $wpdb;
        $table_name = $wpdb->prefix . 'twintack_security_log';
        
        $recent_threats = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name 
             WHERE severity IN ('high', 'critical') 
             AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        if ($recent_threats > 0) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . __('TwinTack Security Alert:', 'twintack-security') . '</strong> ';
            echo sprintf(
                _n(
                    '%d high-priority security event detected in the last 24 hours.',
                    '%d high-priority security events detected in the last 24 hours.',
                    $recent_threats,
                    'twintack-security'
                ),
                $recent_threats
            );
            echo ' <a href="' . admin_url('admin.php?page=twintack-security') . '">' . __('View Details', 'twintack-security') . '</a>';
            echo '</p></div>';
        }
    }

    /**
     * AJAX handler to test email validation
     */
    public function ajax_test_email() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_security_test_email')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $email = sanitize_email($_POST['email']);
        $username = sanitize_text_field($_POST['username']);

        $validation_result = $this->validate_user_data($username, $email);
        
        if (is_wp_error($validation_result)) {
            wp_send_json_error(array(
                'message' => $validation_result->get_error_message(),
                'code' => $validation_result->get_error_code(),
                'user_message' => $this->get_user_friendly_error_message($validation_result->get_error_code())
            ));
        } else {
            wp_send_json_success(array(
                'message' => 'Email would be allowed'
            ));
        }
    }

    /**
     * AJAX handler to bulk delete spam accounts
     */
    public function ajax_bulk_delete_spam() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_security_bulk_delete')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check permissions
        if (!current_user_can('delete_users')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $deleted_count = $this->bulk_delete_spam_accounts();
        
        wp_send_json_success(array(
            'message' => sprintf(__('%d spam accounts deleted successfully.', 'twintack-security'), $deleted_count),
            'deleted_count' => $deleted_count
        ));
    }

    /**
     * Bulk delete spam accounts
     *
     * @return int Number of accounts deleted
     */
    private function bulk_delete_spam_accounts() {
        global $wpdb;

        // Get spam accounts (SMS gateway emails with no orders)
        $blocked_domains = get_option('twintack_security_blocked_domains', array());
        if (empty($blocked_domains)) {
            return 0;
        }

        $domain_conditions = array();
        foreach ($blocked_domains as $domain) {
            $domain_conditions[] = $wpdb->prepare("user_email LIKE %s", '%@' . $domain);
        }
        
        $domain_sql = implode(' OR ', $domain_conditions);
        
        // Get users with SMS gateway emails
        $spam_users = $wpdb->get_results(
            "SELECT ID, user_email FROM {$wpdb->users} 
             WHERE ($domain_sql) 
             AND user_registered > DATE_SUB(NOW(), INTERVAL 90 DAY)"
        );

        $deleted_count = 0;
        
        foreach ($spam_users as $user) {
            // Check if user has any orders (WooCommerce)
            if (TwinTack_Security()->is_woocommerce_active()) {
                $order_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                     WHERE meta_key = '_customer_user' 
                     AND meta_value = %d",
                    $user->ID
                ));
                
                if ($order_count > 0) {
                    continue; // Skip users with orders
                }
            }

            // Check for any user activity (posts, comments, etc.)
            $activity_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d",
                $user->ID
            ));
            
            $comment_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d",
                $user->ID
            ));

            if ($activity_count > 0 || $comment_count > 0) {
                continue; // Skip users with activity
            }

            // Safe to delete - log it first
            TwinTack_Security_Logger::instance()->log_security_event(
                'spam_account_deleted',
                array(
                    'user_id' => $user->ID,
                    'email' => $user->user_email
                ),
                'medium'
            );

            // Delete the user
            wp_delete_user($user->ID);
            $deleted_count++;
        }

        return $deleted_count;
    }
}
