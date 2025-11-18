<?php
/**
 * Email Validation functionality for TwinTack Security
 *
 * @package TwinTack_Security
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TwinTack Security Email Validator class
 */
class TwinTack_Security_Email_Validator {

    /**
     * Single instance of the class
     *
     * @var TwinTack_Security_Email_Validator
     */
    private static $_instance = null;

    /**
     * Main instance
     *
     * @return TwinTack_Security_Email_Validator
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
        // Class initialization
    }

    /**
     * Comprehensive email validation (Updated to be less aggressive)
     * 
     * This validation is now focused primarily on blocking phone-number related
     * emails and obvious spam patterns, rather than being overly broad.
     *
     * @param string $email Email address to validate
     * @param string $username Username (optional)
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_email($email, $username = '') {
        if (empty($email)) {
            return new WP_Error('empty_email', 'Email address is required');
        }

        // Basic WordPress email validation
        if (!is_email($email)) {
            return new WP_Error('invalid_email_format', 'Invalid email format');
        }

        // Check for numeric-only email username (phone numbers)
        if ($this->is_numeric_email($email)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("TwinTack Security: Blocked numeric email pattern: $email");
            }
            return new WP_Error('numeric_email_blocked', 'Numeric email addresses are not allowed');
        }

        // Check for suspicious patterns
        if ($this->has_suspicious_patterns($email)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("TwinTack Security: Blocked suspicious email pattern: $email");
            }
            return new WP_Error('suspicious_pattern', 'Email pattern not allowed');
        }

        // Validate email domain
        $domain_validation = $this->validate_email_domain($email);
        if (is_wp_error($domain_validation)) {
            return $domain_validation;
        }

        // Additional username/email correlation checks
        if (!empty($username)) {
            $correlation_check = $this->validate_username_email_correlation($username, $email);
            if (is_wp_error($correlation_check)) {
                return $correlation_check;
            }
        }

        return true;
    }

    /**
     * Check if email has numeric-only local part (indicating phone number)
     *
     * @param string $email Email address
     * @return bool
     */
    private function is_numeric_email($email) {
        $email_parts = explode('@', $email);
        if (count($email_parts) !== 2) {
            return false;
        }

        $local_part = $email_parts[0];

        // Only block if it's clearly a phone number pattern
        // Be more specific to avoid blocking legitimate numeric usernames

        // Check for purely numeric local part that looks like a phone number (10-15 digits)
        if (preg_match('/^\d{10,15}$/', $local_part)) {
            return true;
        }

        // Check for obvious phone number patterns with separators
        $phone_patterns = array(
            '/^\d{3}[-.]?\d{3}[-.]?\d{4}$/',     // 123-456-7890, 123.456.7890, 1234567890 (US format)
            '/^\+1[-.]?\d{3}[-.]?\d{3}[-.]?\d{4}$/', // +1-123-456-7890 (US with country code)
            '/^\(\d{3}\)\s?\d{3}[-.]?\d{4}$/',  // (123) 456-7890 (US with parentheses)
        );

        foreach ($phone_patterns as $pattern) {
            if (preg_match($pattern, $local_part)) {
                return true;
            }
        }

        // Don't block shorter numeric strings or mixed alphanumeric
        // This allows legitimate usernames like "user123" or "john2024"
        return false;
    }

    /**
     * Check for suspicious email patterns (focused on phone-related patterns only)
     *
     * @param string $email Email address
     * @return bool
     */
    private function has_suspicious_patterns($email) {
        $email_parts = explode('@', strtolower($email));
        if (count($email_parts) !== 2) {
            return false;
        }

        $local_part = $email_parts[0];
        $domain = $email_parts[1];

        // ONLY block patterns that are clearly phone-number related or SMS gateways
        // This is much more targeted than the previous version

        // Pattern 1: Very short local parts that are purely numeric (likely phone fragments)
        if (strlen($local_part) <= 4 && preg_match('/^\d+$/', $local_part)) {
            return true;
        }

        // Pattern 2: Extremely long random alphanumeric strings (30+ chars, likely auto-generated)
        if (preg_match('/^[a-z0-9]{30,}$/', $local_part)) {
            return true;
        }

        // Pattern 3: Obvious phone number patterns in local part
        $phone_patterns = array(
            '/^\d{10}$/',           // Exactly 10 digits (US phone number)
            '/^\d{11}$/',           // Exactly 11 digits (US phone with country code)
            '/^\+1\d{10}$/',        // +1 followed by 10 digits
            '/^\d{3}-?\d{3}-?\d{4}$/', // Standard US phone format
        );

        foreach ($phone_patterns as $pattern) {
            if (preg_match($pattern, $local_part)) {
                return true;
            }
        }

        // Pattern 4: Only block extremely obvious spam/test patterns
        $spam_patterns = array(
            '/^(test|temp|fake|spam|dummy)\d*$/',  // Removed 'example' as it's too broad
            '/^no[-_]?reply$/',
            '/^donotreply$/',
        );

        foreach ($spam_patterns as $pattern) {
            if (preg_match($pattern, $local_part)) {
                return true;
            }
        }

        // Pattern 5: Only block domains that are clearly temporary/disposable
        // Removed most patterns to be less aggressive - these will be handled by disposable email check
        $suspicious_domain_patterns = array(
            '/^temp\./',            // temp.domain.com
            '/^fake\./',            // fake.domain.com  
            '/\.tmp$/',             // domain.tmp
            '/\.temp$/',            // domain.temp
            '/example\.com$/',      // Keep example.com as it's a reserved domain
        );

        foreach ($suspicious_domain_patterns as $pattern) {
            if (preg_match($pattern, $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate email domain
     *
     * @param string $email Email address
     * @return bool|WP_Error
     */
    private function validate_email_domain($email) {
        $email_parts = explode('@', strtolower($email));
        if (count($email_parts) !== 2) {
            return new WP_Error('invalid_email_format', 'Invalid email format');
        }

        $domain = $email_parts[1];

        // Check if domain exists (basic DNS check)
        if (!$this->domain_exists($domain)) {
            return new WP_Error('invalid_domain', 'Email domain does not exist');
        }

        // Check against known disposable email providers
        if ($this->is_disposable_email_domain($domain)) {
            return new WP_Error('disposable_email_blocked', 'Disposable email addresses are not allowed');
        }

        return true;
    }

    /**
     * Check if domain exists (DNS lookup)
     *
     * @param string $domain Domain to check
     * @return bool
     */
    private function domain_exists($domain) {
        // Use transient caching for DNS lookups to improve performance
        $cache_key = 'twintack_security_domain_' . md5($domain);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result === 'exists';
        }

        // Perform DNS lookup
        $exists = false;
        
        // Check for MX record first (preferred for email)
        if (function_exists('checkdnsrr')) {
            $exists = checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
        } else {
            // Fallback for systems without checkdnsrr
            $mx_records = array();
            if (function_exists('getmxrr')) {
                $exists = getmxrr($domain, $mx_records);
            }
            
            // If no MX records, check A record
            if (!$exists) {
                $exists = (gethostbyname($domain) !== $domain);
            }
        }

        // Cache result for 1 hour
        set_transient($cache_key, $exists ? 'exists' : 'not_exists', HOUR_IN_SECONDS);

        return $exists;
    }

    /**
     * Check if domain is a known disposable email provider
     *
     * @param string $domain Domain to check
     * @return bool
     */
    private function is_disposable_email_domain($domain) {
        $disposable_domains = $this->get_disposable_email_domains();
        
        // Check exact match only - be more conservative
        if (in_array($domain, $disposable_domains, true)) {
            return true;
        }

        // Only check for very specific subdomain patterns to avoid false positives
        // Be much more careful with substring matches
        foreach ($disposable_domains as $disposable_domain) {
            // Only match if it ends with the disposable domain (proper subdomain)
            if ($domain !== $disposable_domain && 
                (strpos($domain, '.' . $disposable_domain) !== false && 
                 substr($domain, -(strlen($disposable_domain) + 1)) === '.' . $disposable_domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get list of known disposable email domains
     *
     * @return array
     */
    private function get_disposable_email_domains() {
        // Use transient caching
        $cache_key = 'twintack_security_disposable_domains';
        $cached_domains = get_transient($cache_key);
        
        if ($cached_domains !== false) {
            return $cached_domains;
        }

        $disposable_domains = array(
            // Major disposable email providers
            'mailinator.com', 'guerrillamail.info', 'guerrillamail.biz',
            'guerrillamail.com', 'guerrillamail.de', 'guerrillamail.net',
            'guerrillamail.org', '10minutemail.com', '10minutemail.net',
            'tempmail.org', 'temp-mail.org', 'throwaway.email',
            'getnada.com', 'yopmail.com', 'maildrop.cc',
            'trashmail.com', 'dispostable.com', 'spamgourmet.com',
            'e4ward.com', 'mytrashmail.com', 'mailnesia.com',
            'sharklasers.com', 'grr.la', 'deadaddress.com',
            'fake-mail.ml', 'tempail.com', 'tempmailo.com',
            
            // Additional patterns
            'temp-mail.de', 'temp-mail.io', 'temp-mail.ru',
            'tempinbox.com', 'tempymail.com', 'getairmail.com',
            'emailfake.com', 'mohmal.com', 'anonymouse.org',
            'fakeinbox.com', 'temporaryemail.net'
        );

        // Cache for 24 hours
        set_transient($cache_key, $disposable_domains, DAY_IN_SECONDS);

        return $disposable_domains;
    }

    /**
     * Validate username and email correlation
     *
     * @param string $username Username
     * @param string $email Email address
     * @return bool|WP_Error
     */
    private function validate_username_email_correlation($username, $email) {
        $email_parts = explode('@', $email);
        $local_part = $email_parts[0];

        // Check if username is identical to email local part and both are numeric
        if ($username === $local_part && preg_match('/^\d+$/', $username)) {
            return new WP_Error('suspicious_correlation', 'Username and email correlation suggests automated registration');
        }

        // Check for other suspicious correlations
        if (strlen($username) >= 10 && preg_match('/^\d+$/', $username) && 
            strlen($local_part) >= 10 && preg_match('/^\d+$/', $local_part)) {
            return new WP_Error('phone_number_pattern', 'Phone number patterns detected in username and email');
        }

        return true;
    }

    /**
     * Validate email in real-time (AJAX endpoint)
     *
     * @param string $email Email to validate
     * @param string $username Username (optional)
     * @return array Validation result
     */
    public function validate_email_ajax($email, $username = '') {
        $result = $this->validate_email($email, $username);
        
        if (is_wp_error($result)) {
            return array(
                'valid' => false,
                'error_code' => $result->get_error_code(),
                'error_message' => $result->get_error_message(),
                'user_message' => $this->get_user_friendly_message($result->get_error_code())
            );
        }

        return array(
            'valid' => true,
            'message' => 'Email address is valid'
        );
    }

    /**
     * Get user-friendly error messages
     *
     * @param string $error_code Error code
     * @return string
     */
    private function get_user_friendly_message($error_code) {
        $messages = array(
            'empty_email' => __('Email address is required.', 'twintack-security'),
            'invalid_email_format' => __('Please enter a valid email address.', 'twintack-security'),
            'numeric_email_blocked' => __('Please use a standard email address format.', 'twintack-security'),
            'suspicious_pattern' => __('Please use a standard email address.', 'twintack-security'),
            'invalid_domain' => __('The email domain appears to be invalid.', 'twintack-security'),
            'disposable_email_blocked' => __('Temporary or disposable email addresses are not allowed.', 'twintack-security'),
            'suspicious_correlation' => __('Please use a standard email address format.', 'twintack-security'),
            'phone_number_pattern' => __('Please use a standard email address format.', 'twintack-security')
        );

        return isset($messages[$error_code]) ? $messages[$error_code] : __('Please use a valid email address.', 'twintack-security');
    }

    /**
     * Get email validation statistics
     *
     * @return array
     */
    public function get_validation_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'twintack_security_log';
        
        $stats = array(
            'total_validations' => 0,
            'blocked_numeric' => 0,
            'blocked_suspicious' => 0,
            'blocked_disposable' => 0,
            'blocked_invalid_domain' => 0
        );

        // Get validation statistics from security log
        $validation_events = $wpdb->get_results(
            "SELECT details, COUNT(*) as count 
             FROM $table_name 
             WHERE event_type IN ('registration_blocked', 'woo_registration_blocked') 
             AND timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY details",
            ARRAY_A
        );

        foreach ($validation_events as $event) {
            $details = json_decode($event['details'], true);
            $reason = $details['reason'] ?? '';
            
            $stats['total_validations'] += (int) $event['count'];
            
            if (strpos($reason, 'numeric') !== false) {
                $stats['blocked_numeric'] += (int) $event['count'];
            } elseif (strpos($reason, 'suspicious') !== false) {
                $stats['blocked_suspicious'] += (int) $event['count'];
            } elseif (strpos($reason, 'disposable') !== false) {
                $stats['blocked_disposable'] += (int) $event['count'];
            } elseif (strpos($reason, 'domain') !== false) {
                $stats['blocked_invalid_domain'] += (int) $event['count'];
            }
        }

        return $stats;
    }

    /**
     * Clear domain existence cache
     */
    public function clear_domain_cache() {
        global $wpdb;
        
        // Delete all domain cache transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_twintack_security_domain_%'"
        );
        
        // Also clear timeout transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_twintack_security_domain_%'"
        );
    }

    /**
     * Update disposable email domains list (can be run periodically)
     *
     * @return int Number of domains updated
     */
    public function update_disposable_domains_list() {
        // This could fetch an updated list from an external API
        // For now, we'll just refresh the cache
        delete_transient('twintack_security_disposable_domains');
        
        $domains = $this->get_disposable_email_domains();
        return count($domains);
    }
}
