<?php
/**
 * Spam Protection functionality for TwinTack Security
 *
 * @package TwinTack_Security
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TwinTack Security Spam Protection class
 */
class TwinTack_Security_Spam_Protection {

    /**
     * Single instance of the class
     *
     * @var TwinTack_Security_Spam_Protection
     */
    private static $_instance = null;

    /**
     * Cached blocked domains
     *
     * @var array
     */
    private $blocked_domains = null;

    /**
     * Main instance
     *
     * @return TwinTack_Security_Spam_Protection
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
        // Admin hooks for domain management
        add_action('wp_ajax_twintack_security_add_domain', array($this, 'ajax_add_blocked_domain'));
        add_action('wp_ajax_twintack_security_remove_domain', array($this, 'ajax_remove_blocked_domain'));
        add_action('wp_ajax_twintack_security_get_spam_stats', array($this, 'ajax_get_spam_stats'));
        add_action('wp_ajax_twintack_security_export_spam_accounts', array($this, 'ajax_export_spam_accounts'));
    }

    /**
     * Check if an email is from a spam/SMS gateway
     *
     * @param string $email Email address to check
     * @return string|false Returns the blocked domain if spam, false if clean
     */
    public function is_spam_email($email) {
        if (empty($email) || !is_email($email)) {
            return false;
        }

        // Extract domain from email
        $email_parts = explode('@', strtolower($email));
        if (count($email_parts) !== 2) {
            return false;
        }
        
        $domain = $email_parts[1];
        $blocked_domains = $this->get_blocked_domains();

        // Check exact domain match
        if (in_array($domain, $blocked_domains, true)) {
            return $domain;
        }

        // Check for subdomain matches (e.g., text.domain.com matches domain.com)
        foreach ($blocked_domains as $blocked_domain) {
            if (strpos($domain, $blocked_domain) !== false) {
                return $blocked_domain;
            }
        }

        // Additional pattern checks for SMS gateway characteristics
        if ($this->has_sms_gateway_patterns($email, $domain)) {
            return 'sms_pattern_detected';
        }

        return false;
    }

    /**
     * Check for SMS gateway patterns
     *
     * @param string $email Full email address
     * @param string $domain Email domain
     * @return bool
     */
    private function has_sms_gateway_patterns($email, $domain) {
        $email_parts = explode('@', $email);
        $local_part = $email_parts[0];

        // Pattern 1: Numeric-only local part (phone numbers)
        if (preg_match('/^\d{10,15}$/', $local_part)) {
            return true;
        }

        // Pattern 2: Common SMS gateway domain patterns
        $sms_patterns = array(
            '/\.sms\./',
            '/\.txt\./',
            '/\.text\./',
            '/\.msg\./',
            '/mms\./',
            '/messaging\./',
            '/mobile\./',
            '/wireless\./',
            '/cellular\./',
            '/pcs\./',
            '/email2sms/',
            '/sms2email/',
            '/textmsg/',
            '/txtmsg/'
        );

        foreach ($sms_patterns as $pattern) {
            if (preg_match($pattern, $domain)) {
                return true;
            }
        }

        // Pattern 3: Specific carrier identifiers
        $carrier_indicators = array(
            'vtext', 'vzw', 'verizon',
            'tmo', 'tmobile', 't-mobile',
            'att', 'attwireless',
            'sprint', 'sprintpcs',
            'uscc', 'uscellular',
            'cricket', 'cr8',
            'tracfone', 'tfn',
            'alltel',
            'bell', 'telus', 'rogers', 'fido',
            'metropcs', 'metro'
        );

        foreach ($carrier_indicators as $indicator) {
            if (strpos($domain, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get blocked domains list
     *
     * @return array
     */
    public function get_blocked_domains() {
        if ($this->blocked_domains === null) {
            $this->blocked_domains = get_option('twintack_security_blocked_domains', array());
            
            // Ensure it's an array
            if (!is_array($this->blocked_domains)) {
                $this->blocked_domains = array();
            }
        }
        
        return $this->blocked_domains;
    }

    /**
     * Add a domain to the blocked list
     *
     * @param string $domain Domain to block
     * @return bool Success
     */
    public function add_blocked_domain($domain) {
        $domain = strtolower(trim($domain));
        
        // Validate domain format
        if (!$this->is_valid_domain($domain)) {
            return false;
        }

        $blocked_domains = $this->get_blocked_domains();
        
        if (!in_array($domain, $blocked_domains, true)) {
            $blocked_domains[] = $domain;
            $this->blocked_domains = $blocked_domains;
            
            $result = update_option('twintack_security_blocked_domains', $blocked_domains);
            
            if ($result) {
                // Log the addition
                TwinTack_Security_Logger::instance()->log_security_event(
                    'domain_blocked',
                    array(
                        'domain' => $domain,
                        'admin_user' => get_current_user_id()
                    ),
                    'medium'
                );
            }
            
            return $result;
        }
        
        return true; // Already exists
    }

    /**
     * Remove a domain from the blocked list
     *
     * @param string $domain Domain to unblock
     * @return bool Success
     */
    public function remove_blocked_domain($domain) {
        $domain = strtolower(trim($domain));
        $blocked_domains = $this->get_blocked_domains();
        
        $key = array_search($domain, $blocked_domains, true);
        if ($key !== false) {
            unset($blocked_domains[$key]);
            $blocked_domains = array_values($blocked_domains); // Re-index array
            $this->blocked_domains = $blocked_domains;
            
            $result = update_option('twintack_security_blocked_domains', $blocked_domains);
            
            if ($result) {
                // Log the removal
                TwinTack_Security_Logger::instance()->log_security_event(
                    'domain_unblocked',
                    array(
                        'domain' => $domain,
                        'admin_user' => get_current_user_id()
                    ),
                    'medium'
                );
            }
            
            return $result;
        }
        
        return false;
    }

    /**
     * Validate domain format
     *
     * @param string $domain Domain to validate
     * @return bool
     */
    private function is_valid_domain($domain) {
        // Basic domain validation
        if (empty($domain) || strlen($domain) > 253) {
            return false;
        }

        // Check for valid domain characters
        if (!preg_match('/^[a-z0-9.-]+$/', $domain)) {
            return false;
        }

        // Check for valid domain structure
        if (strpos($domain, '..') !== false || 
            substr($domain, 0, 1) === '.' || 
            substr($domain, -1) === '.') {
            return false;
        }

        return true;
    }

    /**
     * Get spam statistics
     *
     * @return array
     */
    public function get_spam_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'twintack_security_log';
        $blocked_domains = $this->get_blocked_domains();
        
        // Get blocked attempts by time period
        $stats = array(
            'total_domains' => count($blocked_domains),
            'blocked_today' => 0,
            'blocked_week' => 0,
            'blocked_month' => 0,
            'top_blocked_domains' => array(),
            'recent_blocks' => array()
        );

        // Get blocked attempts counts
        $periods = array(
            'today' => 'INTERVAL 1 DAY',
            'week' => 'INTERVAL 7 DAY',
            'month' => 'INTERVAL 30 DAY'
        );

        foreach ($periods as $period => $interval) {
            $count = $wpdb->get_var(
                "SELECT COUNT(*) FROM $table_name 
                 WHERE event_type IN ('registration_blocked', 'woo_registration_blocked') 
                 AND details LIKE '%sms_gateway_blocked%'
                 AND timestamp > DATE_SUB(NOW(), $interval)"
            );
            $stats['blocked_' . $period] = (int) $count;
        }

        // Get top blocked domains (from log details)
        $top_domains = $wpdb->get_results(
            "SELECT details, COUNT(*) as count 
             FROM $table_name 
             WHERE event_type IN ('registration_blocked', 'woo_registration_blocked') 
             AND details LIKE '%sms_gateway_blocked%'
             AND timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY details 
             ORDER BY count DESC 
             LIMIT 10",
            ARRAY_A
        );

        foreach ($top_domains as $domain_data) {
            // Extract domain from details JSON
            $details = json_decode($domain_data['details'], true);
            if (isset($details['email'])) {
                $email_parts = explode('@', $details['email']);
                if (count($email_parts) === 2) {
                    $domain = $email_parts[1];
                    $stats['top_blocked_domains'][] = array(
                        'domain' => $domain,
                        'count' => (int) $domain_data['count']
                    );
                }
            }
        }

        // Get recent blocked attempts
        $recent_blocks = $wpdb->get_results(
            "SELECT timestamp, details 
             FROM $table_name 
             WHERE event_type IN ('registration_blocked', 'woo_registration_blocked') 
             AND details LIKE '%sms_gateway_blocked%'
             ORDER BY timestamp DESC 
             LIMIT 20",
            ARRAY_A
        );

        foreach ($recent_blocks as $block) {
            $details = json_decode($block['details'], true);
            $stats['recent_blocks'][] = array(
                'timestamp' => $block['timestamp'],
                'email' => $details['email'] ?? 'Unknown',
                'username' => $details['username'] ?? 'Unknown'
            );
        }

        return $stats;
    }

    /**
     * Detect potential spam accounts in existing users
     *
     * @return array
     */
    public function detect_existing_spam_accounts() {
        global $wpdb;
        
        $blocked_domains = $this->get_blocked_domains();
        if (empty($blocked_domains)) {
            return array();
        }

        $domain_conditions = array();
        foreach ($blocked_domains as $domain) {
            $domain_conditions[] = $wpdb->prepare("user_email LIKE %s", '%@' . $domain);
        }
        
        $domain_sql = implode(' OR ', $domain_conditions);
        
        // Get users with SMS gateway emails
        $spam_candidates = $wpdb->get_results(
            "SELECT u.ID, u.user_login, u.user_email, u.user_registered,
                    (SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
                     WHERE pm.meta_key = '_customer_user' AND pm.meta_value = u.ID) as order_count
             FROM {$wpdb->users} u
             WHERE ($domain_sql)
             ORDER BY u.user_registered DESC",
            ARRAY_A
        );

        $spam_accounts = array();
        foreach ($spam_candidates as $user) {
            $spam_accounts[] = array(
                'id' => $user['ID'],
                'username' => $user['user_login'],
                'email' => $user['user_email'],
                'registered' => $user['user_registered'],
                'order_count' => (int) $user['order_count'],
                'is_safe_to_delete' => ((int) $user['order_count'] === 0)
            );
        }

        return $spam_accounts;
    }

    /**
     * AJAX handler to add blocked domain
     */
    public function ajax_add_blocked_domain() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_security_domain_management')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $domain = sanitize_text_field($_POST['domain']);
        
        if ($this->add_blocked_domain($domain)) {
            wp_send_json_success(array(
                'message' => sprintf(__('Domain "%s" added to blocked list.', 'twintack-security'), $domain),
                'domain' => $domain
            ));
        } else {
            wp_send_json_error('Failed to add domain. Please check the domain format.');
        }
    }

    /**
     * AJAX handler to remove blocked domain
     */
    public function ajax_remove_blocked_domain() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_security_domain_management')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $domain = sanitize_text_field($_POST['domain']);
        
        if ($this->remove_blocked_domain($domain)) {
            wp_send_json_success(array(
                'message' => sprintf(__('Domain "%s" removed from blocked list.', 'twintack-security'), $domain)
            ));
        } else {
            wp_send_json_error('Failed to remove domain.');
        }
    }

    /**
     * AJAX handler to get spam statistics
     */
    public function ajax_get_spam_stats() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_security_stats')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $stats = $this->get_spam_statistics();
        $existing_spam = $this->detect_existing_spam_accounts();
        
        wp_send_json_success(array(
            'stats' => $stats,
            'existing_spam' => $existing_spam
        ));
    }

    /**
     * Import common SMS gateway domains
     *
     * @return int Number of domains imported
     */
    public function import_common_sms_domains() {
        $common_domains = array(
            // Major US carriers
            'vtext.com', 'text.vzw.com', 'vzwpix.com',
            'tmomail.net', 'mymetropcs.com',
            'txt.att.net', 'mms.att.net', 'cingularme.com',
            'messaging.sprintpcs.com', 'pm.sprint.com',
            'sms.uscc.net', 'mms.uscc.net',
            'txt.cr8.net', 'mypixmessages.com',
            'mmst5.tracfone.com', 'message.alltel.com',
            
            // Canadian carriers
            'txt.bell.ca', 'msg.telus.com', 'pcs.rogers.com',
            'fido.ca', 'txt.freedommobile.ca',
            
            // International
            'sms.three.co.uk', 'txtmail.co.uk', 'sms.orange.co.uk',
            'sms.vodafone.de', 'sms.o2.co.uk',
            
            // Generic patterns
            'text.com', 'sms.com', 'txt.com', 'msg.com',
            'email2sms.com', 'sms2email.com', 'textmsg.com'
        );

        $imported = 0;
        foreach ($common_domains as $domain) {
            if ($this->add_blocked_domain($domain)) {
                $imported++;
            }
        }

        return $imported;
    }

    /**
     * AJAX handler to export spam accounts
     */
    public function ajax_export_spam_accounts() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_security_export')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $spam_accounts = $this->detect_existing_spam_accounts();
        
        // Generate CSV content
        $csv_content = "ID,Username,Email,Registered,Order Count,Safe to Delete\n";
        
        foreach ($spam_accounts as $account) {
            $csv_content .= sprintf(
                "%d,%s,%s,%s,%d,%s\n",
                $account['id'],
                $this->csv_escape($account['username']),
                $this->csv_escape($account['email']),
                $account['registered'],
                $account['order_count'],
                $account['is_safe_to_delete'] ? 'Yes' : 'No'
            );
        }

        wp_send_json_success(array(
            'csv_content' => $csv_content,
            'filename' => 'twintack-spam-accounts-' . date('Y-m-d') . '.csv'
        ));
    }

    /**
     * Escape CSV field
     */
    private function csv_escape($field) {
        if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        return $field;
    }
}
