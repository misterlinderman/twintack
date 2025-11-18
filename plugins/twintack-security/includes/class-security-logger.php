<?php
/**
 * Security Logging functionality for TwinTack Security
 *
 * @package TwinTack_Security
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TwinTack Security Logger class
 */
class TwinTack_Security_Logger {

    /**
     * Single instance of the class
     *
     * @var TwinTack_Security_Logger
     */
    private static $_instance = null;

    /**
     * Database table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Main instance
     *
     * @return TwinTack_Security_Logger
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
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'twintack_security_log';
        
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Cleanup hook
        add_action('twintack_security_cleanup_logs', array($this, 'cleanup_old_logs'));
        
        // Admin hooks
        add_action('wp_ajax_twintack_security_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_twintack_security_export_logs', array($this, 'ajax_export_logs'));
        add_action('wp_ajax_twintack_security_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_twintack_security_get_log_stats', array($this, 'ajax_get_log_stats'));
    }

    /**
     * Log a security event
     *
     * @param string $event_type Type of event
     * @param array $details Event details
     * @param string $severity Severity level (low, medium, high, critical)
     * @param string $action_taken Action taken in response
     * @return bool Success
     */
    public function log_security_event($event_type, $details = array(), $severity = 'medium', $action_taken = '') {
        global $wpdb;

        // Sanitize inputs
        $event_type = sanitize_text_field($event_type);
        $severity = in_array($severity, array('low', 'medium', 'high', 'critical')) ? $severity : 'medium';
        $action_taken = sanitize_text_field($action_taken);

        // Get client information
        $ip_address = $this->get_client_ip();
        $user_agent = $this->get_user_agent();
        $user_email = isset($details['email']) ? sanitize_email($details['email']) : null;

        // Prepare details as JSON
        $details_json = wp_json_encode($details);

        // Insert log entry
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'timestamp' => current_time('mysql'),
                'event_type' => $event_type,
                'ip_address' => $ip_address,
                'user_email' => $user_email,
                'details' => $details_json,
                'severity' => $severity,
                'user_agent' => $user_agent,
                'action_taken' => $action_taken
            ),
            array(
                '%s', // timestamp
                '%s', // event_type
                '%s', // ip_address
                '%s', // user_email
                '%s', // details
                '%s', // severity
                '%s', // user_agent
                '%s'  // action_taken
            )
        );

        // Send admin notification for high-priority events
        if ($result && in_array($severity, array('high', 'critical'))) {
            $this->send_admin_notification($event_type, $details, $severity);
        }

        return $result !== false;
    }

    /**
     * Get security logs with filtering and pagination
     *
     * @param array $args Query arguments
     * @return array
     */
    public function get_security_logs($args = array()) {
        global $wpdb;

        // Default arguments
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'event_type' => '',
            'severity' => '',
            'ip_address' => '',
            'date_from' => '',
            'date_to' => '',
            'search' => '',
            'order_by' => 'timestamp',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause
        $where_conditions = array('1=1');
        $where_values = array();

        if (!empty($args['event_type'])) {
            $where_conditions[] = 'event_type = %s';
            $where_values[] = $args['event_type'];
        }

        if (!empty($args['severity'])) {
            $where_conditions[] = 'severity = %s';
            $where_values[] = $args['severity'];
        }

        if (!empty($args['ip_address'])) {
            $where_conditions[] = 'ip_address = %s';
            $where_values[] = $args['ip_address'];
        }

        if (!empty($args['date_from'])) {
            $where_conditions[] = 'timestamp >= %s';
            $where_values[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where_conditions[] = 'timestamp <= %s';
            $where_values[] = $args['date_to'] . ' 23:59:59';
        }

        if (!empty($args['search'])) {
            $where_conditions[] = '(user_email LIKE %s OR details LIKE %s OR ip_address LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // Build ORDER BY clause
        $allowed_order_by = array('timestamp', 'event_type', 'severity', 'ip_address');
        $order_by = in_array($args['order_by'], $allowed_order_by) ? $args['order_by'] : 'timestamp';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE $where_clause";
        if (!empty($where_values)) {
            $count_query = $wpdb->prepare($count_query, $where_values);
        }
        $total_count = $wpdb->get_var($count_query);

        // Get logs
        $query = "SELECT * FROM {$this->table_name} 
                  WHERE $where_clause 
                  ORDER BY $order_by $order 
                  LIMIT %d OFFSET %d";
        
        $query_values = array_merge($where_values, array((int) $args['limit'], (int) $args['offset']));
        $logs = $wpdb->get_results($wpdb->prepare($query, $query_values), ARRAY_A);

        // Process logs
        foreach ($logs as &$log) {
            $log['details'] = json_decode($log['details'], true);
            $log['formatted_timestamp'] = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['timestamp']));
        }

        return array(
            'logs' => $logs,
            'total_count' => (int) $total_count,
            'current_page' => floor($args['offset'] / $args['limit']) + 1,
            'total_pages' => ceil($total_count / $args['limit'])
        );
    }

    /**
     * Get security statistics
     *
     * @return array
     */
    public function get_security_statistics() {
        global $wpdb;

        $stats = array(
            'total_events' => 0,
            'events_today' => 0,
            'events_week' => 0,
            'events_month' => 0,
            'high_priority_events' => 0,
            'top_event_types' => array(),
            'top_blocked_ips' => array(),
            'severity_breakdown' => array()
        );

        // Get basic counts
        $periods = array(
            'total' => '',
            'today' => 'AND timestamp >= CURDATE()',
            'week' => 'AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
            'month' => 'AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );

        foreach ($periods as $period => $condition) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE 1=1 $condition");
            $stats['events_' . $period] = (int) $count;
        }

        // High priority events (last 30 days)
        $stats['high_priority_events'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE severity IN ('high', 'critical') 
             AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Top event types (last 30 days)
        $top_events = $wpdb->get_results(
            "SELECT event_type, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY event_type 
             ORDER BY count DESC 
             LIMIT 10",
            ARRAY_A
        );

        foreach ($top_events as $event) {
            $stats['top_event_types'][] = array(
                'type' => $event['event_type'],
                'count' => (int) $event['count']
            );
        }

        // Top blocked IPs (last 30 days)
        $top_ips = $wpdb->get_results(
            "SELECT ip_address, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE event_type LIKE '%blocked%'
             AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY ip_address 
             ORDER BY count DESC 
             LIMIT 10",
            ARRAY_A
        );

        foreach ($top_ips as $ip) {
            $stats['top_blocked_ips'][] = array(
                'ip' => $ip['ip_address'],
                'count' => (int) $ip['count']
            );
        }

        // Severity breakdown (last 30 days)
        $severity_counts = $wpdb->get_results(
            "SELECT severity, COUNT(*) as count 
             FROM {$this->table_name} 
             WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY severity",
            ARRAY_A
        );

        foreach ($severity_counts as $severity) {
            $stats['severity_breakdown'][$severity['severity']] = (int) $severity['count'];
        }

        return $stats;
    }

    /**
     * Clean up old log entries
     */
    public function cleanup_old_logs() {
        global $wpdb;

        $retention_days = (int) get_option('twintack_security_log_retention_days', 30);
        
        if ($retention_days > 0) {
            $deleted_count = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->table_name} 
                 WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_days
            ));

            if ($deleted_count > 0) {
                error_log("TwinTack Security: Cleaned up $deleted_count old log entries");
            }
        }
    }

    /**
     * Export security logs
     *
     * @param array $args Export arguments
     * @return string CSV content
     */
    public function export_logs($args = array()) {
        $logs_data = $this->get_security_logs(array_merge($args, array('limit' => 10000)));
        $logs = $logs_data['logs'];

        $csv_output = '';
        
        // CSV headers
        $headers = array(
            'Timestamp',
            'Event Type',
            'IP Address',
            'Email',
            'Severity',
            'Details',
            'Action Taken',
            'User Agent'
        );
        
        $csv_output .= implode(',', $headers) . "\n";

        // CSV data
        foreach ($logs as $log) {
            $row = array(
                $log['timestamp'],
                $log['event_type'],
                $log['ip_address'],
                $log['user_email'] ?: '',
                $log['severity'],
                str_replace(array("\n", "\r", ','), array(' ', ' ', ';'), wp_json_encode($log['details'])),
                $log['action_taken'] ?: '',
                str_replace(array("\n", "\r", ','), array(' ', ' ', ';'), substr($log['user_agent'], 0, 100))
            );
            
            $csv_output .= implode(',', array_map(array($this, 'csv_escape'), $row)) . "\n";
        }

        return $csv_output;
    }

    /**
     * Escape CSV field
     *
     * @param string $field Field value
     * @return string
     */
    private function csv_escape($field) {
        if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        return $field;
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
     * Get user agent
     *
     * @return string
     */
    private function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '';
    }

    /**
     * Send admin notification for high-priority events
     *
     * @param string $event_type Event type
     * @param array $details Event details
     * @param string $severity Severity level
     */
    private function send_admin_notification($event_type, $details, $severity) {
        if (get_option('twintack_security_admin_email_notifications') !== 'yes') {
            return;
        }

        $admin_email = get_option('admin_email');
        if (empty($admin_email)) {
            return;
        }

        $site_name = get_bloginfo('name');
        $site_url = get_site_url();

        $subject = sprintf(
            '[%s] Security Alert: %s',
            $site_name,
            ucfirst($severity) . ' priority event detected'
        );

        $message = sprintf(
            "A %s priority security event has been detected on your website.\n\n" .
            "Event Type: %s\n" .
            "Timestamp: %s\n" .
            "IP Address: %s\n" .
            "Details: %s\n\n" .
            "You can view more details in your security dashboard:\n%s\n\n" .
            "This is an automated message from TwinTack Security Suite.",
            strtoupper($severity),
            $event_type,
            current_time('Y-m-d H:i:s'),
            $this->get_client_ip(),
            wp_json_encode($details),
            admin_url('admin.php?page=twintack-security')
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * AJAX handler to get logs
     */
    public function ajax_get_logs() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_security_logs')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $args = array(
            'limit' => (int) ($_POST['limit'] ?? 50),
            'offset' => (int) ($_POST['offset'] ?? 0),
            'event_type' => sanitize_text_field($_POST['event_type'] ?? ''),
            'severity' => sanitize_text_field($_POST['severity'] ?? ''),
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? '')
        );

        $logs_data = $this->get_security_logs($args);
        wp_send_json_success($logs_data);
    }

    /**
     * AJAX handler to export logs
     */
    public function ajax_export_logs() {
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

        $args = array(
            'event_type' => sanitize_text_field($_POST['event_type'] ?? ''),
            'severity' => sanitize_text_field($_POST['severity'] ?? ''),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? '')
        );

        $csv_content = $this->export_logs($args);
        
        wp_send_json_success(array(
            'csv_content' => $csv_content,
            'filename' => 'twintack-security-logs-' . date('Y-m-d') . '.csv'
        ));
    }

    /**
     * AJAX handler to clear logs
     */
    public function ajax_clear_logs() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_security_clear_logs')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        global $wpdb;
        
        $older_than_days = (int) ($_POST['older_than_days'] ?? 0);
        
        if ($older_than_days > 0) {
            $deleted_count = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->table_name} 
                 WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $older_than_days
            ));
        } else {
            // Clear all logs
            $deleted_count = $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        }

        wp_send_json_success(array(
            'message' => sprintf(__('%d log entries deleted.', 'twintack-security'), $deleted_count),
            'deleted_count' => $deleted_count
        ));
    }

    /**
     * AJAX handler to get log statistics
     */
    public function ajax_get_log_stats() {
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

        $stats = $this->get_security_statistics();
        wp_send_json_success($stats);
    }
}
