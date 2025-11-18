<?php
/**
 * Admin Interface for TwinTack Security
 *
 * @package TwinTack_Security
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TwinTack Security Admin class
 */
class TwinTack_Security_Admin {

    /**
     * Single instance of the class
     *
     * @var TwinTack_Security_Admin
     */
    private static $_instance = null;

    /**
     * Main instance
     *
     * @return TwinTack_Security_Admin
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . TWINTACK_SECURITY_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('TwinTack Security', 'twintack-security'),
            __('TwinTack Security', 'twintack-security'),
            'manage_options',
            'twintack-security',
            array($this, 'display_dashboard_page'),
            'dashicons-shield-alt',
            30
        );

        add_submenu_page(
            'twintack-security',
            __('Dashboard', 'twintack-security'),
            __('Dashboard', 'twintack-security'),
            'manage_options',
            'twintack-security',
            array($this, 'display_dashboard_page')
        );

        add_submenu_page(
            'twintack-security',
            __('Spam Protection', 'twintack-security'),
            __('Spam Protection', 'twintack-security'),
            'manage_options',
            'twintack-security-spam',
            array($this, 'display_spam_page')
        );

        add_submenu_page(
            'twintack-security',
            __('Security Logs', 'twintack-security'),
            __('Security Logs', 'twintack-security'),
            'manage_options',
            'twintack-security-logs',
            array($this, 'display_logs_page')
        );

        add_submenu_page(
            'twintack-security',
            __('Account Cleanup', 'twintack-security'),
            __('Account Cleanup', 'twintack-security'),
            'manage_options',
            'twintack-security-cleanup',
            array($this, 'display_cleanup_page')
        );

        add_submenu_page(
            'twintack-security',
            __('Settings', 'twintack-security'),
            __('Settings', 'twintack-security'),
            'manage_options',
            'twintack-security-settings',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'twintack-security') === false) {
            return;
        }

        wp_enqueue_style(
            'twintack-security-admin',
            TWINTACK_SECURITY_PLUGIN_URL . 'admin/css/admin-styles.css',
            array(),
            TWINTACK_SECURITY_VERSION
        );

        wp_enqueue_script(
            'twintack-security-admin',
            TWINTACK_SECURITY_PLUGIN_URL . 'admin/js/admin-scripts.js',
            array('jquery'),
            TWINTACK_SECURITY_VERSION,
            true
        );

        wp_localize_script('twintack-security-admin', 'twintackSecurity', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('twintack_security_admin'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this?', 'twintack-security'),
                'confirmBulkDelete' => __('Are you sure you want to delete all selected spam accounts? This action cannot be undone.', 'twintack-security'),
                'loading' => __('Loading...', 'twintack-security'),
                'error' => __('An error occurred. Please try again.', 'twintack-security'),
                'success' => __('Operation completed successfully.', 'twintack-security')
            )
        ));
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('twintack_security_settings', 'twintack_security_enabled');
        register_setting('twintack_security_settings', 'twintack_security_sms_blocking_enabled');
        register_setting('twintack_security_settings', 'twintack_security_email_validation_enabled');
        register_setting('twintack_security_settings', 'twintack_security_rate_limiting_enabled');
        register_setting('twintack_security_settings', 'twintack_security_rate_limit_attempts');
        register_setting('twintack_security_settings', 'twintack_security_rate_limit_window');
        register_setting('twintack_security_settings', 'twintack_security_log_retention_days');
        register_setting('twintack_security_settings', 'twintack_security_admin_email_notifications');
    }

    /**
     * Add plugin action links
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=twintack-security-settings') . '">' . __('Settings', 'twintack-security') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Display dashboard page
     */
    public function display_dashboard_page() {
        $stats = TwinTack_Security_Logger::instance()->get_security_statistics();
        $spam_stats = TwinTack_Security_Spam_Protection::instance()->get_spam_statistics();
        
        include TWINTACK_SECURITY_ABSPATH . 'admin/views/dashboard.php';
    }

    /**
     * Display spam protection page
     */
    public function display_spam_page() {
        $blocked_domains = TwinTack_Security_Spam_Protection::instance()->get_blocked_domains();
        $spam_stats = TwinTack_Security_Spam_Protection::instance()->get_spam_statistics();
        
        include TWINTACK_SECURITY_ABSPATH . 'admin/views/spam-protection.php';
    }

    /**
     * Display security logs page
     */
    public function display_logs_page() {
        include TWINTACK_SECURITY_ABSPATH . 'admin/views/security-logs.php';
    }

    /**
     * Display account cleanup page
     */
    public function display_cleanup_page() {
        $spam_accounts = TwinTack_Security_Spam_Protection::instance()->detect_existing_spam_accounts();
        
        include TWINTACK_SECURITY_ABSPATH . 'admin/views/account-cleanup.php';
    }

    /**
     * Display settings page
     */
    public function display_settings_page() {
        if (isset($_POST['submit'])) {
            check_admin_referer('twintack_security_settings');
            
            $this->save_settings();
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully.', 'twintack-security') . '</p></div>';
        }
        
        include TWINTACK_SECURITY_ABSPATH . 'admin/views/settings.php';
    }

    /**
     * Save settings
     */
    private function save_settings() {
        $settings = array(
            'twintack_security_enabled' => isset($_POST['twintack_security_enabled']) ? 'yes' : 'no',
            'twintack_security_sms_blocking_enabled' => isset($_POST['twintack_security_sms_blocking_enabled']) ? 'yes' : 'no',
            'twintack_security_email_validation_enabled' => isset($_POST['twintack_security_email_validation_enabled']) ? 'yes' : 'no',
            'twintack_security_rate_limiting_enabled' => isset($_POST['twintack_security_rate_limiting_enabled']) ? 'yes' : 'no',
            'twintack_security_rate_limit_attempts' => (int) $_POST['twintack_security_rate_limit_attempts'],
            'twintack_security_rate_limit_window' => (int) $_POST['twintack_security_rate_limit_window'],
            'twintack_security_log_retention_days' => (int) $_POST['twintack_security_log_retention_days'],
            'twintack_security_admin_email_notifications' => isset($_POST['twintack_security_admin_email_notifications']) ? 'yes' : 'no'
        );

        foreach ($settings as $option_name => $value) {
            update_option($option_name, $value);
        }
    }

    /**
     * Get security status for dashboard
     */
    public function get_security_status() {
        $status = array(
            'overall' => 'good',
            'issues' => array(),
            'recommendations' => array()
        );

        // Check if main security is enabled
        if (get_option('twintack_security_enabled') !== 'yes') {
            $status['overall'] = 'critical';
            $status['issues'][] = __('TwinTack Security is disabled', 'twintack-security');
        }

        // Check for recent high-priority events
        global $wpdb;
        $table_name = $wpdb->prefix . 'twintack_security_log';
        $recent_threats = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name 
             WHERE severity IN ('high', 'critical') 
             AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        if ($recent_threats > 5) {
            $status['overall'] = 'warning';
            $status['issues'][] = sprintf(__('%d high-priority security events in the last 24 hours', 'twintack-security'), $recent_threats);
        }

        // Check blocked domains count
        $blocked_domains = TwinTack_Security_Spam_Protection::instance()->get_blocked_domains();
        if (count($blocked_domains) < 10) {
            $status['recommendations'][] = __('Consider adding more SMS gateway domains to the blocked list', 'twintack-security');
        }

        // Check for spam accounts
        $spam_accounts = TwinTack_Security_Spam_Protection::instance()->detect_existing_spam_accounts();
        if (count($spam_accounts) > 0) {
            $safe_to_delete = array_filter($spam_accounts, function($account) {
                return $account['is_safe_to_delete'];
            });
            
            if (count($safe_to_delete) > 0) {
                $status['recommendations'][] = sprintf(
                    __('%d spam accounts detected that can be safely removed', 'twintack-security'),
                    count($safe_to_delete)
                );
            }
        }

        return $status;
    }
}
