<?php
/**
 * Plugin Name: TwinTack Security Suite
 * Plugin URI: https://twintack.com
 * Description: Comprehensive security plugin to protect against SMS gateway spam and other threats. Specifically designed for TwinTack's WooCommerce needs.
 * Version: 1.0.0
 * Author: TwinTack Development Team
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: twintack-security
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 8.5
 * Requires Plugins: woocommerce
 * WC tested up to: 9.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TWINTACK_SECURITY_VERSION', '1.0.0');
define('TWINTACK_SECURITY_PLUGIN_FILE', __FILE__);
define('TWINTACK_SECURITY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TWINTACK_SECURITY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TWINTACK_SECURITY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main TwinTack Security class
 * 
 * @class TwinTack_Security
 * @version 1.0.0
 */
final class TwinTack_Security {

    /**
     * Single instance of the class
     *
     * @var TwinTack_Security
     */
    private static $_instance = null;

    /**
     * Main TwinTack Security instance
     * Ensures only one instance of TwinTack Security is loaded or can be loaded.
     *
     * @static
     * @return TwinTack_Security - Main instance
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
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
        $this->declare_hpos_compatibility();
    }

    /**
     * Define plugin constants
     */
    private function define_constants() {
        $this->define('TWINTACK_SECURITY_ABSPATH', dirname(TWINTACK_SECURITY_PLUGIN_FILE) . '/');
    }

    /**
     * Define constant if not already set
     *
     * @param string $name  Constant name
     * @param string $value Constant value
     */
    private function define($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    /**
     * Include required core files
     */
    public function includes() {
        include_once TWINTACK_SECURITY_ABSPATH . 'includes/class-twintack-security-core.php';
        include_once TWINTACK_SECURITY_ABSPATH . 'includes/class-spam-protection.php';
        include_once TWINTACK_SECURITY_ABSPATH . 'includes/class-email-validator.php';
        include_once TWINTACK_SECURITY_ABSPATH . 'includes/class-security-logger.php';
        
        if (is_admin()) {
            include_once TWINTACK_SECURITY_ABSPATH . 'includes/class-admin-interface.php';
        }
    }

    /**
     * Hook into actions and filters
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'install'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('TwinTack_Security', 'uninstall'));

        add_action('init', array($this, 'init'), 0);
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        do_action('twintack_security_before_init');

        // Check if database tables need to be created/updated
        $this->maybe_create_tables();

        // Initialize core functionality
        TwinTack_Security_Core::instance();
        TwinTack_Security_Spam_Protection::instance();
        TwinTack_Security_Email_Validator::instance();
        TwinTack_Security_Logger::instance();

        if (is_admin()) {
            TwinTack_Security_Admin::instance();
        }

        do_action('twintack_security_init');
    }

    /**
     * Load plugin textdomain
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain('twintack-security', false, dirname(TWINTACK_SECURITY_PLUGIN_BASENAME) . '/languages/');
    }

    /**
     * Install the plugin
     */
    public function install() {
        // Create database tables
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule any cron events
        $this->schedule_events();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log installation
        error_log('TwinTack Security Suite: Plugin installed successfully');
        
        // Add version to track updates
        update_option('twintack_security_db_version', '1.0.0');
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'twintack_security_log';

        $charset_collate = $wpdb->get_charset_collate();

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
        dbDelta($sql);
        
        // Log table creation
        error_log('TwinTack Security: Database table created/updated - ' . $table_name);
    }

    /**
     * Check if tables need to be created
     */
    private function maybe_create_tables() {
        $current_version = get_option('twintack_security_db_version', '0');
        
        if (version_compare($current_version, TWINTACK_SECURITY_VERSION, '<')) {
            $this->create_tables();
            update_option('twintack_security_db_version', TWINTACK_SECURITY_VERSION);
        }
    }

    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $default_options = array(
            'twintack_security_enabled' => 'yes',
            'twintack_security_sms_blocking_enabled' => 'yes',
            'twintack_security_email_validation_enabled' => 'yes',
            'twintack_security_rate_limiting_enabled' => 'yes',
            'twintack_security_blocked_domains' => $this->get_default_blocked_domains(),
            'twintack_security_rate_limit_attempts' => 5,
            'twintack_security_rate_limit_window' => 300, // 5 minutes
            'twintack_security_log_retention_days' => 30,
            'twintack_security_admin_email_notifications' => 'yes',
            'twintack_security_version' => TWINTACK_SECURITY_VERSION
        );

        foreach ($default_options as $option_name => $default_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $default_value);
            }
        }
    }

    /**
     * Get default blocked SMS gateway domains
     */
    private function get_default_blocked_domains() {
        return array(
            // US Carriers
            'vtext.com',           // Verizon
            'text.vzw.com',        // Verizon alternate
            'vzwpix.com',          // Verizon MMS
            'tmomail.net',         // T-Mobile
            'mymetropcs.com',      // Metro PCS
            'txt.att.net',         // AT&T
            'mms.att.net',         // AT&T MMS
            'messaging.sprintpcs.com', // Sprint
            'pm.sprint.com',       // Sprint alternate
            'sms.uscc.net',        // US Cellular
            'mms.uscc.net',        // US Cellular MMS
            'txt.cr8.net',         // Cricket
            'mypixmessages.com',   // Cricket MMS
            'mmst5.tracfone.com',  // TracFone
            'message.alltel.com',  // Alltel
            'txt.bell.ca',         // Bell Canada
            'msg.telus.com',       // Telus
            'pcs.rogers.com',      // Rogers
            'fido.ca',             // Fido
            'txt.freedommobile.ca', // Freedom Mobile
            
            // International carriers (common ones)
            'sms.three.co.uk',     // Three UK
            'txtmail.co.uk',       // UK generic
            'sms.orange.co.uk',    // Orange UK
            'sms.vodafone.de',     // Vodafone Germany
            'sms.o2.co.uk',        // O2 UK
            
            // Additional suspicious patterns
            'text.com',
            'sms.com',
            'txt.com',
            'msg.com',
            'email2sms.com',
            'sms2email.com'
        );
    }

    /**
     * Schedule cron events
     */
    private function schedule_events() {
        if (!wp_next_scheduled('twintack_security_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'twintack_security_cleanup_logs');
        }
    }

    /**
     * Deactivate the plugin
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('twintack_security_cleanup_logs');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        error_log('TwinTack Security Suite: Plugin deactivated');
    }

    /**
     * Uninstall the plugin
     */
    public static function uninstall() {
        global $wpdb;

        // Remove database tables
        $table_name = $wpdb->prefix . 'twintack_security_log';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");

        // Remove all plugin options
        $options = array(
            'twintack_security_enabled',
            'twintack_security_sms_blocking_enabled',
            'twintack_security_email_validation_enabled',
            'twintack_security_rate_limiting_enabled',
            'twintack_security_blocked_domains',
            'twintack_security_rate_limit_attempts',
            'twintack_security_rate_limit_window',
            'twintack_security_log_retention_days',
            'twintack_security_admin_email_notifications',
            'twintack_security_version'
        );

        foreach ($options as $option) {
            delete_option($option);
        }

        // Clear scheduled events
        wp_clear_scheduled_hook('twintack_security_cleanup_logs');

        error_log('TwinTack Security Suite: Plugin uninstalled and data cleaned up');
    }

    /**
     * Check if WooCommerce is active
     */
    public function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Get the plugin version
     */
    public function get_version() {
        return TWINTACK_SECURITY_VERSION;
    }

    /**
     * Get the plugin path
     */
    public function plugin_path() {
        return untrailingslashit(plugin_dir_path(__FILE__));
    }

    /**
     * Get the plugin URL
     */
    public function plugin_url() {
        return untrailingslashit(plugins_url('/', __FILE__));
    }

    /**
     * Declare HPOS compatibility
     */
    private function declare_hpos_compatibility() {
        add_action('before_woocommerce_init', function() {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });
    }
}

/**
 * Returns the main instance of TwinTack Security
 *
 * @return TwinTack_Security
 */
function TwinTack_Security() {
    return TwinTack_Security::instance();
}

// Initialize the plugin
TwinTack_Security();
