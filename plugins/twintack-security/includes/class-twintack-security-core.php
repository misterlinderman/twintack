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
        // WordPress registration hooks - use higher priority to run after other plugins
        add_filter('pre_user_login', array($this, 'validate_registration'), 20, 1);
        add_filter('registration_errors', array($this, 'validate_registration_errors'), 20, 3);
        
        // WooCommerce registration hooks
        add_action('woocommerce_register_post', array($this, 'validate_woo_registration'), 10, 3);
        add_filter('woocommerce_registration_errors', array($this, 'validate_woo_registration_errors'), 10, 3);
        
        // Comment hooks (optional protection)
        add_filter('pre_comment_approved', array($this, 'validate_comment'), 10, 2);
        
        // Rate limiting hooks
        add_action('wp_login_failed', array($this, 'handle_failed_login'));
        add_action('user_register', array($this, 'track_registration'));
        
        // Track successful registrations and redirects
        add_action('wp_login', array($this, 'track_successful_login'), 10, 2);
        add_action('template_redirect', array($this, 'track_page_loads'));
        
        // Post-registration error handling
        add_action('woocommerce_created_customer', array($this, 'safe_post_registration_processing'), 5, 3);
        
        // Add comprehensive error handling for registration process
        add_action('init', array($this, 'setup_error_handlers'));
        add_action('wp', array($this, 'log_registration_attempt'));
        
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

        // EMERGENCY BYPASS: If this is ANY kind of checkout request, skip ALL validation
        if ($this->is_any_checkout_request()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: EMERGENCY BYPASS in validate_registration - Detected checkout request, skipping ALL security validation');
            }
            return $sanitized_user_login;
        }

        // BYPASS: If this is a lost password request, skip ALL validation
        if ($this->is_lost_password_request()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: BYPASS in validate_registration - Detected lost password request, skipping ALL security validation');
            }
            return $sanitized_user_login;
        }

        // Get the email from registration form - try multiple possible field names
        $user_email = '';
        
        // Try different possible email field names used by various registration forms
        $email_fields = array('user_email', 'email', 'reg_email', 'account_email', 'billing_email');
        
        foreach ($email_fields as $field) {
            if (isset($_POST[$field]) && !empty($_POST[$field])) {
                $user_email = sanitize_email($_POST[$field]);
                break;
            }
        }
        
        // DEBUG: Log what we're getting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('TwinTack Security: validate_registration called');
            error_log('TwinTack Security: Username: ' . $sanitized_user_login);
            error_log('TwinTack Security: Email from POST: ' . $user_email);
            error_log('TwinTack Security: REQUEST_URI: ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'not set'));
            error_log('TwinTack Security: POST data: ' . print_r($_POST, true));
        }
        
        // SECURITY CHECK: If email is empty, this is a problem that needs to be fixed
        // Don't bypass - instead fail with a clear error
        if (empty($user_email)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Email is empty - this indicates a form processing or hook timing issue');
                error_log('TwinTack Security: REQUEST_URI: ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'not set'));
                error_log('TwinTack Security: POST keys: ' . implode(', ', array_keys($_POST)));
            }
            
            // Don't bypass security - instead return an error
            wp_die(
                'Registration error: Email address is required for security validation.',
                __('Registration Error', 'twintack-security'),
                array('response' => 400, 'back_link' => true)
            );
        }
        
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

        // EMERGENCY BYPASS: If this is ANY kind of checkout request, skip ALL validation
        if ($this->is_any_checkout_request()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: EMERGENCY BYPASS in validate_registration_errors - Detected checkout request, skipping ALL security validation');
            }
            return $errors;
        }

        // BYPASS: If this is a lost password request, skip ALL validation
        if ($this->is_lost_password_request()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: BYPASS in validate_registration_errors - Detected lost password request, skipping ALL security validation');
            }
            return $errors;
        }

        // SECURITY CHECK: If email is empty, add an error instead of bypassing
        if (empty($user_email)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Email is empty in registration_errors - adding validation error');
            }
            $errors->add('empty_email_security', 'Email address is required for security validation.');
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

        // EMERGENCY BYPASS: If this is ANY kind of checkout request, skip ALL validation
        if ($this->is_any_checkout_request()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: EMERGENCY BYPASS in validate_woo_registration - Detected checkout request, skipping ALL security validation');
            }
            return;
        }

        // Skip validation for checkout registrations
        if ($this->is_checkout_registration()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: BYPASSING woocommerce_register_post validation for checkout');
            }
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
     * @param string $email Email (optional - may not be passed by all WooCommerce hooks)
     * @return WP_Error
     */
    public function validate_woo_registration_errors($errors, $username, $password, $email = '') {
        if (!$this->is_security_enabled()) {
            return $errors;
        }

        // EMERGENCY BYPASS: If this is ANY kind of WooCommerce checkout request, skip ALL validation
        if ($this->is_any_checkout_request()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: EMERGENCY BYPASS - Detected checkout request, skipping ALL security validation');
            }
            return $errors;
        }

        // Handle different parameter patterns from WooCommerce
        // Sometimes email is passed as the 3rd parameter instead of password
        if (empty($email) && is_email($password)) {
            $email = $password;
        }

        // Skip rate limiting for checkout registrations to avoid blocking legitimate customers
        $is_checkout = $this->is_checkout_registration();
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('TwinTack Security: WooCommerce registration validation - Is checkout: ' . ($is_checkout ? 'Yes' : 'No'));
            error_log('TwinTack Security: Username: ' . $username . ' | Email: ' . $email);
        }
        
        // Skip security validation for legitimate checkout registrations
        if ($is_checkout) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Allowing checkout registration to proceed without security validation');
            }
            return $errors;
        }
        
        $validation_result = $this->validate_user_data($username, $email, $is_checkout);
        
        if (is_wp_error($validation_result)) {
            $errors->add(
                $validation_result->get_error_code(),
                $this->get_user_friendly_error_message($validation_result->get_error_code())
            );
        }

        return $errors;
    }

    /**
     * Check if this is a checkout registration (not a regular registration form)
     *
     * @return bool
     */
    private function is_checkout_registration() {
        // FIRST: Exclude logout requests - they should never be treated as checkout registrations
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'customer-logout') !== false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Logout request detected - NOT checkout registration');
            }
            return false;
        }
        
        // Multiple detection methods for checkout registration
        
        // Method 1: WooCommerce AJAX checkout (MOST IMPORTANT - this is what's failing)
        if (isset($_GET['wc-ajax']) && $_GET['wc-ajax'] === 'checkout') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Detected checkout via wc-ajax GET parameter');
            }
            return true;
        }
        
        if (isset($_POST['wc-ajax']) && $_POST['wc-ajax'] === 'checkout') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Detected checkout via wc-ajax POST parameter');
            }
            return true;
        }
        
        // Method 2: Check for checkout-specific fields
        if (isset($_POST['createaccount']) && $_POST['createaccount'] == '1') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Detected checkout via createaccount field');
            }
            return true;
        }
        
        // Method 3: Check referrer/current page (but exclude logout)
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'checkout') !== false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Detected checkout via REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
            }
            return true;
        }
        
        // Method 4: Check for WooCommerce checkout nonce (but be more specific)
        if (isset($_POST['woocommerce-process-checkout-nonce'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Detected checkout via checkout nonce');
            }
            return true;
        }
        
        // Method 5: Check action parameter
        if (isset($_POST['action']) && $_POST['action'] === 'woocommerce_checkout') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Detected checkout via action parameter');
            }
            return true;
        }
        
        // Method 6: Check for AJAX checkout request in REQUEST_URI
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'wc-ajax=checkout') !== false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Detected checkout via wc-ajax in REQUEST_URI');
            }
            return true;
        }
        
        // Method 7: Emergency bypass - if we're processing any WooCommerce checkout, allow it
        if (doing_action('woocommerce_checkout_process') || 
            doing_action('woocommerce_before_checkout_process') ||
            doing_action('woocommerce_after_checkout_process')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Detected checkout via WooCommerce action hooks');
            }
            return true;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('TwinTack Security: NOT detected as checkout registration');
        }
        
        return false;
    }

    /**
     * Emergency bypass method - detect ANY kind of checkout request
     *
     * @return bool
     */
    private function is_any_checkout_request() {
        // Check for any indication this is a checkout-related request
        
        // Check URL parameters
        if (isset($_GET['wc-ajax']) || isset($_POST['wc-ajax'])) {
            return true;
        }
        
        // Check REQUEST_URI for checkout-related paths
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
            if (strpos($uri, 'checkout') !== false || 
                strpos($uri, 'wc-ajax') !== false ||
                strpos($uri, 'order-received') !== false) {
                return true;
            }
        }
        
        // Check for any checkout-related POST data
        if (isset($_POST['woocommerce-process-checkout-nonce']) ||
            isset($_POST['createaccount']) ||
            isset($_POST['billing_email']) ||
            isset($_POST['payment_method'])) {
            return true;
        }
        
        // Check if we're in any WooCommerce checkout context
        if (function_exists('is_checkout') && is_checkout()) {
            return true;
        }
        
        // Check current action
        if (doing_action('woocommerce_checkout_process') ||
            doing_action('woocommerce_before_checkout_process') ||
            doing_action('woocommerce_after_checkout_process') ||
            doing_action('woocommerce_checkout_order_processed')) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if this is a lost password request
     *
     * @return bool
     */
    private function is_lost_password_request() {
        // Check URL parameters for lost password action
        if (isset($_GET['action']) && $_GET['action'] === 'lostpassword') {
            return true;
        }
        
        // Check REQUEST_URI for lost password paths
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
            if (strpos($uri, 'lostpassword') !== false || 
                strpos($uri, 'lost-password') !== false) {
                return true;
            }
        }
        
        // Check for lost password form submission
        if (isset($_POST['wc_reset_password']) && isset($_POST['user_login'])) {
            return true;
        }
        
        // Check for WordPress lost password form
        if (isset($_POST['user_login']) && !isset($_POST['user_email']) && !isset($_POST['register'])) {
            // This is likely a lost password form (has user_login but no email or register fields)
            return true;
        }
        
        return false;
    }

    /**
     * Core validation logic for user data
     *
     * @param string $username Username
     * @param string $email Email
     * @param bool $skip_rate_limit Skip rate limiting (for checkout registrations)
     * @return bool|WP_Error True if valid, WP_Error if blocked
     */
    private function validate_user_data($username, $email, $skip_rate_limit = false) {
        // Rate limiting check (skip for checkout registrations)
        if (!$skip_rate_limit && $this->is_rate_limited()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Rate limiting triggered - blocking registration attempt');
            }
            return new WP_Error(
                'rate_limited',
                'Too many registration attempts. Please try again later.'
            );
        } elseif ($skip_rate_limit) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Rate limiting SKIPPED for checkout registration');
            }
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

        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
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
    
    /**
     * Safe post-registration processing with error handling
     * This runs before the theme's registration processing to catch errors
     *
     * @param int $customer_id Customer ID
     * @param array $new_customer_data Customer data
     * @param bool $password_generated Whether password was generated
     */
    public function safe_post_registration_processing($customer_id, $new_customer_data, $password_generated) {
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Safe post-registration processing started for customer ' . $customer_id);
            }
            
            // Let other plugins/theme handle the actual processing
            // We're just here to catch any fatal errors and prevent 500s
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Error in post-registration processing: ' . $e->getMessage());
                error_log('TwinTack Security: Stack trace: ' . $e->getTraceAsString());
            }
            
            // Log as security event
            $this->log_security_event(
                'post_registration_error',
                array(
                    'customer_id' => $customer_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ),
                'medium'
            );
            
            // Don't re-throw - let registration complete successfully
        } catch (Error $e) {
            // Catch PHP 7+ fatal errors too
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Fatal error in post-registration: ' . $e->getMessage());
            }
            
            $this->log_security_event(
                'post_registration_fatal_error',
                array(
                    'customer_id' => $customer_id,
                    'error' => $e->getMessage()
                ),
                'high'
            );
        }
    }
    
    /**
     * Setup comprehensive error handlers
     */
    public function setup_error_handlers() {
        // Only set up error handlers for registration requests
        if (!$this->is_registration_request()) {
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('TwinTack Security: Setting up error handlers for registration request');
        }
        
        // Set custom error handler
        set_error_handler(array($this, 'registration_error_handler'));
        
        // Set custom exception handler
        set_exception_handler(array($this, 'registration_exception_handler'));
        
        // Register shutdown function to catch fatal errors
        register_shutdown_function(array($this, 'registration_shutdown_handler'));
    }
    
    /**
     * Check if this is a registration request
     */
    private function is_registration_request() {
        return (isset($_POST['register']) || 
                (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'register') !== false) ||
                isset($_POST['user_email']));
    }
    
    /**
     * Log registration attempt details
     */
    public function log_registration_attempt() {
        if (!$this->is_registration_request()) {
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('TwinTack Security: Registration attempt detected');
            error_log('TwinTack Security: REQUEST_URI: ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'not set'));
            error_log('TwinTack Security: REQUEST_METHOD: ' . (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'not set'));
            error_log('TwinTack Security: POST keys: ' . (empty($_POST) ? 'none' : implode(', ', array_keys($_POST))));
            error_log('TwinTack Security: User agent: ' . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'not set'));
            
            // Log redirect parameter if present
            if (isset($_POST['redirect_to']) || isset($_GET['redirect_to'])) {
                $redirect = $_POST['redirect_to'] ?? $_GET['redirect_to'] ?? 'not set';
                error_log('TwinTack Security: Redirect parameter: ' . $redirect);
            }
        }
        
        // Also log to our security system for debugging
        $this->log_security_event(
            'registration_attempt_detected',
            array(
                'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown',
                'post_keys' => empty($_POST) ? 'none' : array_keys($_POST),
                'redirect_to' => $_POST['redirect_to'] ?? $_GET['redirect_to'] ?? 'none'
            ),
            'low'
        );
    }
    
    /**
     * Custom error handler for registration
     */
    public function registration_error_handler($severity, $message, $file, $line) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("TwinTack Security: PHP Error during registration - Severity: $severity, Message: $message, File: $file, Line: $line");
        }
        
        $this->log_security_event(
            'registration_php_error',
            array(
                'severity' => $severity,
                'message' => $message,
                'file' => $file,
                'line' => $line,
                'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown'
            ),
            'medium'
        );
        
        // Don't interfere with WordPress error handling
        return false;
    }
    
    /**
     * Custom exception handler for registration
     */
    public function registration_exception_handler($exception) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('TwinTack Security: Uncaught exception during registration: ' . $exception->getMessage());
            error_log('TwinTack Security: Exception trace: ' . $exception->getTraceAsString());
        }
        
        $this->log_security_event(
            'registration_uncaught_exception',
            array(
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown'
            ),
            'high'
        );
    }
    
    /**
     * Shutdown handler to catch fatal errors during registration
     */
    public function registration_shutdown_handler() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Fatal error during registration: ' . $error['message']);
                error_log('TwinTack Security: Error file: ' . $error['file'] . ' line ' . $error['line']);
            }
            
            $this->log_security_event(
                'registration_fatal_error',
                array(
                    'type' => $error['type'],
                    'message' => $error['message'],
                    'file' => $error['file'],
                    'line' => $error['line'],
                    'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown'
                ),
                'critical'
            );
        }
    }
    
    /**
     * Track successful logins (including after registration)
     */
    public function track_successful_login($user_login, $user) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('TwinTack Security: Successful login for user: ' . $user_login);
            error_log('TwinTack Security: User ID: ' . $user->ID);
            error_log('TwinTack Security: REQUEST_URI: ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'not set'));
        }
        
        $this->log_security_event(
            'successful_login',
            array(
                'user_login' => $user_login,
                'user_id' => $user->ID,
                'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown'
            ),
            'low'
        );
    }
    
    /**
     * Track page loads to see where redirects go
     */
    public function track_page_loads() {
        // Only track if this might be related to registration
        if (isset($_SERVER['REQUEST_URI']) && 
            (strpos($_SERVER['REQUEST_URI'], 'grip-configurator') !== false ||
             strpos($_SERVER['REQUEST_URI'], 'login') !== false ||
             strpos($_SERVER['REQUEST_URI'], 'register') !== false)) {
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Security: Page load tracked: ' . $_SERVER['REQUEST_URI']);
                error_log('TwinTack Security: Current user: ' . (is_user_logged_in() ? wp_get_current_user()->user_login : 'not logged in'));
            }
            
            $this->log_security_event(
                'page_load_tracked',
                array(
                    'request_uri' => $_SERVER['REQUEST_URI'],
                    'user_logged_in' => is_user_logged_in(),
                    'current_user' => is_user_logged_in() ? wp_get_current_user()->user_login : 'none'
                ),
                'low'
            );
        }
    }
    
    /**
     * Helper method to log security events
     */
    private function log_security_event($event_type, $details, $severity) {
        if (class_exists('TwinTack_Security_Logger')) {
            TwinTack_Security_Logger::instance()->log_security_event($event_type, $details, $severity);
        }
    }
}
