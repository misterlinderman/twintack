<?php
/**
 * Plugin Name: TwinTack Manual Order Payments
 * Plugin URI: https://twintack.com
 * Description: Enables Stripe and other payment gateways for manually created WooCommerce orders, with seamless integration with TwinTack Grip Manager. Now includes Stripe Checkout Sessions for customer self-service payments.
 * Version: 1.2.0
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
define('TWINTACK_MANUAL_PAYMENTS_VERSION', '1.2.0');
define('TWINTACK_MANUAL_PAYMENTS_PLUGIN_FILE', __FILE__);
define('TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TWINTACK_MANUAL_PAYMENTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TWINTACK_MANUAL_PAYMENTS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Helper functions - defined early to prevent undefined function errors
 */

/**
 * Check if manual payments are enabled
 */
function twintack_is_manual_payments_enabled() {
    if (!class_exists('TwinTack_Manual_Order_Payments')) {
        return false;
    }
    return TwinTack_Manual_Order_Payments::get_option('enable_manual_payment_processing', 'yes') === 'yes';
}

/**
 * Check if Stripe admin is enabled
 */
function twintack_is_stripe_admin_enabled() {
    if (!class_exists('TwinTack_Manual_Order_Payments')) {
        return false;
    }
    return TwinTack_Manual_Order_Payments::get_option('enable_stripe_admin', 'yes') === 'yes';
}

/**
 * Log debug message
 */
function twintack_manual_payments_log($message, $level = 'info') {
    if (!class_exists('TwinTack_Manual_Order_Payments')) {
        return;
    }
    
    if (TwinTack_Manual_Order_Payments::get_option('debug_mode', 'no') === 'yes') {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->log($level, $message, array('source' => 'twintack-manual-payments'));
        } else {
            error_log('TwinTack Manual Payments: ' . $message);
        }
    }
}

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
        add_action('plugins_loaded', array($this, 'init'), 20);
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WooCommerce is active first
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Create plugin options if they don't exist
        $this->create_plugin_options();
        
        // Load text domain for translations
        load_plugin_textdomain('twintack-manual-payments', false, dirname(TWINTACK_MANUAL_PAYMENTS_PLUGIN_BASENAME) . '/languages');
        
        // Initialize on admin_init for better timing
        add_action('admin_init', array($this, 'admin_init'));
        
        // Plugin loaded successfully
        do_action('twintack_manual_payments_loaded');
        
        twintack_manual_payments_log('Plugin initialized successfully');
    }
    
    /**
     * Initialize admin functionality
     */
    public function admin_init() {
        // Only in admin and if WooCommerce is available
        if (!is_admin() || !function_exists('WC')) {
            return;
        }
        
        // Include admin functionality with error handling
        $this->load_admin_functionality();
    }
    
    /**
     * Load admin functionality safely
     */
    private function load_admin_functionality() {
        try {
            $admin_file = TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-admin-order-enhancements.php';
            
            if (!file_exists($admin_file)) {
                twintack_manual_payments_log('Admin enhancements file not found: ' . $admin_file, 'error');
                return;
            }
            
            require_once $admin_file;
            
            if (!class_exists('TwinTack_Admin_Order_Enhancements')) {
                twintack_manual_payments_log('Admin enhancements class not found after include', 'error');
                return;
            }
            
            // Initialize immediately to ensure AJAX handlers are registered
            // AJAX requests don't go through current_screen hook
            TwinTack_Admin_Order_Enhancements::get_instance();
            
            twintack_manual_payments_log('Admin functionality loaded successfully');
            
        } catch (Exception $e) {
            twintack_manual_payments_log('Error loading admin functionality: ' . $e->getMessage(), 'error');
        }
    }
    

    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
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
            'debug_mode' => 'yes'
        );
        
        $existing_options = get_option('twintack_manual_payments_options', array());
        if (empty($existing_options)) {
            add_option('twintack_manual_payments_options', $default_options);
        }
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

// Declare WooCommerce HPOS compatibility (must be called before WooCommerce init)
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

/**
 * Initialize the plugin
 */
function twintack_manual_order_payments() {
    return TwinTack_Manual_Order_Payments::get_instance();
}

// Start the plugin
twintack_manual_order_payments(); 