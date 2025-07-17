<?php
/**
 * Plugin Name: TwinTack Manual Order Payments
 * Plugin URI: https://twintack.com
 * Description: Enables Stripe and other payment gateways for manually created WooCommerce orders, with seamless integration with TwinTack Grip Manager.
 * Version: 1.0.1
 * Author: TwinTack
 * Author URI: https://twintack.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: twintack-manual-payments
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * 
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * 
 * @package TwinTack_Manual_Order_Payments
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TWINTACK_MANUAL_PAYMENTS_VERSION', '1.0.1');
define('TWINTACK_MANUAL_PAYMENTS_PLUGIN_FILE', __FILE__);
define('TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TWINTACK_MANUAL_PAYMENTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TWINTACK_MANUAL_PAYMENTS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Plugin Class
 */
class TwinTack_Manual_Order_Payments {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'plugins_loaded'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('twintack-manual-payments', false, dirname(TWINTACK_MANUAL_PAYMENTS_PLUGIN_BASENAME) . '/languages');
        
        // Initialize only if WooCommerce is active
        if ($this->is_woocommerce_active()) {
            $this->includes();
            $this->init_hooks();
        } else {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
        }
    }
    
    /**
     * Load plugin after all plugins are loaded
     */
    public function plugins_loaded() {
        // Check for required plugins
        if (!$this->is_woocommerce_active()) {
            return;
        }
        
        // Declare HPOS compatibility
        $this->declare_hpos_compatibility();
        
        // Plugin loaded successfully
        do_action('twintack_manual_payments_loaded');
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Load admin functionality only in admin
        if (is_admin()) {
            require_once TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-admin-order-enhancements.php';
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize admin enhancements
        if (is_admin()) {
            TwinTack_Admin_Order_Enhancements::get_instance();
        }
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * Declare HPOS (High-Performance Order Storage) compatibility
     */
    private function declare_hpos_compatibility() {
        if (class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                TWINTACK_MANUAL_PAYMENTS_PLUGIN_FILE,
                true
            );
        }
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('TwinTack Manual Order Payments', 'twintack-manual-payments'); ?></strong>
                <?php esc_html_e('requires WooCommerce to be installed and active.', 'twintack-manual-payments'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check for WooCommerce
        if (!$this->is_woocommerce_active()) {
            deactivate_plugins(TWINTACK_MANUAL_PAYMENTS_PLUGIN_BASENAME);
            wp_die(
                esc_html__('TwinTack Manual Order Payments requires WooCommerce to be installed and active.', 'twintack-manual-payments'),
                esc_html__('Plugin Activation Error', 'twintack-manual-payments'),
                array('back_link' => true)
            );
        }
        
        // Set plugin version
        update_option('twintack_manual_payments_version', TWINTACK_MANUAL_PAYMENTS_VERSION);
        
        // Create any necessary database tables or options
        $this->create_plugin_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up any scheduled events
        wp_clear_scheduled_hook('twintack_manual_payments_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create plugin options
     */
    private function create_plugin_options() {
        $default_options = array(
            'enable_stripe_admin' => 'yes',
            'enable_manual_payment_processing' => 'yes',
            'auto_create_grip_posts' => 'yes',
            'debug_mode' => 'no'
        );
        
        add_option('twintack_manual_payments_options', $default_options);
    }
    
    /**
     * Get plugin option
     */
    public static function get_option($key, $default = '') {
        $options = get_option('twintack_manual_payments_options', array());
        return isset($options[$key]) ? $options[$key] : $default;
    }
    
    /**
     * Update plugin option
     */
    public static function update_option($key, $value) {
        $options = get_option('twintack_manual_payments_options', array());
        $options[$key] = $value;
        update_option('twintack_manual_payments_options', $options);
    }
    
    /**
     * Get plugin URL
     */
    public static function get_plugin_url() {
        return TWINTACK_MANUAL_PAYMENTS_PLUGIN_URL;
    }
    
    /**
     * Get plugin path
     */
    public static function get_plugin_path() {
        return TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR;
    }
}

/**
 * Initialize the plugin
 */
function twintack_manual_order_payments() {
    return TwinTack_Manual_Order_Payments::get_instance();
}

// Start the plugin
twintack_manual_order_payments();

/**
 * Helper functions
 */

/**
 * Check if manual payments are enabled
 */
function twintack_is_manual_payments_enabled() {
    return TwinTack_Manual_Order_Payments::get_option('enable_manual_payment_processing', 'yes') === 'yes';
}

/**
 * Check if Stripe admin is enabled
 */
function twintack_is_stripe_admin_enabled() {
    return TwinTack_Manual_Order_Payments::get_option('enable_stripe_admin', 'yes') === 'yes';
}

/**
 * Log debug message
 */
function twintack_manual_payments_log($message, $level = 'info') {
    if (TwinTack_Manual_Order_Payments::get_option('debug_mode', 'no') === 'yes') {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->log($level, $message, array('source' => 'twintack-manual-payments'));
        } else {
            error_log('TwinTack Manual Payments: ' . $message);
        }
    }
} 