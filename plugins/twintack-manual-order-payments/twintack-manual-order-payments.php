<?php
/**
 * Plugin Name: TwinTack Manual Order Payments
 * Plugin URI: https://twintack.com
 * Description: Enables Stripe and other payment gateways for manually created WooCommerce orders, with seamless integration with TwinTack Grip Manager. Now includes Stripe Checkout Sessions for customer self-service payments.
 * Version: 4.6.1
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
define('TWINTACK_MANUAL_PAYMENTS_VERSION', '4.6.1');
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
 * Log debug message - FIXED to work during early loading
 */
function twintack_manual_payments_log($message, $level = 'info') {
    // Always log during debugging - don't depend on plugin options during early loading
    $debug_enabled = true; // Set to false to disable debugging
    
    if (!$debug_enabled) {
        return;
    }
    
    // Add timestamp and prefix for easier tracking
    $timestamp = date('Y-m-d H:i:s');
    $prefixed_message = "[$timestamp] $message";
    
    // Force logging to work even if WP_DEBUG is disabled
    $log_file = WP_CONTENT_DIR . '/debug.log';
    $log_entry = date('c') . " TwinTack Manual Payments: $prefixed_message" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Also try WooCommerce logger if available
    if (function_exists('wc_get_logger')) {
        try {
            $logger = wc_get_logger();
            $logger->log($level, $prefixed_message, array('source' => 'twintack-manual-payments'));
        } catch (Exception $e) {
            // Ignore WC logger errors
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
        add_action('plugins_loaded', array($this, 'init'), 5);
        
        // Extra early hook to ensure we load before admin functionality
        add_action('init', array($this, 'ensure_early_loading'), 1);
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Extra early loading to ensure classes are available before admin hooks
     */
    public function ensure_early_loading() {
        // Only run once
        static $early_loaded = false;
        if ($early_loaded) {
            return;
        }
        $early_loaded = true;
        
        twintack_manual_payments_log('EARLY LOADING: Triggered on init hook priority 1');
        
        // Check if WooCommerce is available yet
        if (!function_exists('WC')) {
            twintack_manual_payments_log('EARLY LOADING: WooCommerce not yet available, will load later');
            return;
        }
        
        twintack_manual_payments_log('EARLY LOADING: WooCommerce available, loading classes early');
        $this->load_admin_functionality();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        twintack_manual_payments_log('PLUGIN INIT: Starting plugin initialization');
        
        // Check if WooCommerce is active first
        if (!$this->is_woocommerce_active()) {
            twintack_manual_payments_log('PLUGIN INIT: WooCommerce not active, showing notice');
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        twintack_manual_payments_log('PLUGIN INIT: WooCommerce is active, setting up hooks');
        
        // Create plugin options if they don't exist
        $this->create_plugin_options();
        
        // Load text domain for translations
        load_plugin_textdomain('twintack-manual-payments', false, dirname(TWINTACK_MANUAL_PAYMENTS_PLUGIN_BASENAME) . '/languages');
        
        // Check if classes were already loaded in early loading phase
        if (class_exists('TwinTack_Debug_Tools')) {
            twintack_manual_payments_log('PLUGIN INIT: Classes already loaded in early phase, skipping reload');
        } else {
            twintack_manual_payments_log('PLUGIN INIT: Loading admin functionality now');
            $this->load_admin_functionality();
        }
        
        // Keep admin_init for any additional setup
        add_action('admin_init', array($this, 'admin_init'));
        twintack_manual_payments_log('PLUGIN INIT: Registered admin_init hook');
        
        // Remove the fallback since we're loading immediately
        // add_action('init', array($this, 'ensure_classes_loaded'), 15);
        twintack_manual_payments_log('PLUGIN INIT: Classes loaded immediately, no fallback needed');
        
        // Register custom email class
        add_filter('woocommerce_email_classes', array($this, 'register_email_classes'));
        
        // Register custom payment gateways
        add_filter('woocommerce_payment_gateways', array($this, 'register_payment_gateways'));
        
        twintack_manual_payments_log('PLUGIN INIT: Initialization complete, hooks registered');
        
        // Plugin loaded successfully
        do_action('twintack_manual_payments_loaded');
        
        twintack_manual_payments_log('Plugin initialized successfully');
    }
    
    /**
     * Admin initialization - classes already loaded during init()
     */
    public function admin_init() {
        // Debug: Log admin_init call
        twintack_manual_payments_log('admin_init called - is_admin: ' . (is_admin() ? 'true' : 'false') . ', WC exists: ' . (function_exists('WC') ? 'true' : 'false'));
        
        // Only in admin and if WooCommerce is available
        if (!is_admin() || !function_exists('WC')) {
            twintack_manual_payments_log('admin_init: Exiting early - conditions not met');
            return;
        }
        
        // Classes already loaded during init() - just verify they're available
        $key_classes = array('TwinTack_Debug_Tools', 'TwinTack_Order_Status_Manager');
        $missing = array();
        foreach ($key_classes as $class) {
            if (!class_exists($class)) {
                $missing[] = $class;
            }
        }
        
        if (!empty($missing)) {
            twintack_manual_payments_log('admin_init: WARNING - Some classes missing: ' . implode(', ', $missing), 'warning');
        } else {
            twintack_manual_payments_log('admin_init: All key classes are available');
        }
    }
    
    /**
     * Removed: ensure_classes_loaded - no longer needed since classes load immediately
     * This method is kept as a stub in case other code references it
     */
    public function ensure_classes_loaded() {
        twintack_manual_payments_log('ensure_classes_loaded: Called but no longer needed - classes load immediately during init()');
        // No-op - classes are loaded immediately during init()
    }
    
    /**
     * Get debug logs for troubleshooting
     * Check WooCommerce → Status → Logs → twintack-manual-payments
     */
    public function get_debug_info() {
        $info = array(
            'plugin_version' => TWINTACK_MANUAL_PAYMENTS_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'woocommerce_version' => function_exists('WC') ? WC()->version : 'Not available',
            'is_admin' => is_admin(),
            'loaded_classes' => array()
        );
        
        $classes_to_check = array(
            'TwinTack_Order_Status_Manager',
            'TwinTack_Debug_Tools',
            'TwinTack_Shippo_Integration',
            'TwinTack_Invoice_Payment_Gateway',
            'TwinTack_Admin_Order_Enhancements'
        );
        
        foreach ($classes_to_check as $class) {
            $info['loaded_classes'][$class] = class_exists($class);
        }
        
        return $info;
    }
    
    /**
     * Load admin functionality safely with detailed error handling
     */
    private function load_admin_functionality() {
        twintack_manual_payments_log('=== STARTING ADMIN FUNCTIONALITY LOADING ===');
        
        $classes_to_load = array(
            'invoice_gateway' => array(
                'file' => TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-invoice-payment-gateway.php',
                'class' => 'TwinTack_Invoice_Payment_Gateway',
                'instantiate' => false
            ),
            'order_status_manager' => array(
                'file' => TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-order-status-manager.php',
                'class' => 'TwinTack_Order_Status_Manager',
                'instantiate' => true
            ),
            'shippo_integration' => array(
                'file' => TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-shippo-integration.php',
                'class' => 'TwinTack_Shippo_Integration',
                'instantiate' => true
            ),
            'shippo_api_client' => array(
                'file' => TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-shippo-api-client.php',
                'class' => 'TwinTack_Shippo_API_Client',
                'instantiate' => true
            ),
            'shippo_webhook_handler' => array(
                'file' => TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-shippo-webhook-handler.php',
                'class' => 'TwinTack_Shippo_Webhook_Handler',
                'instantiate' => true
            ),
            'simple_order_manager' => array(
                'file' => TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-simple-order-manager.php',
                'class' => 'TwinTack_Simple_Order_Manager',
                'instantiate' => true
            ),
            'debug_tools' => array(
                'file' => TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-debug-tools.php',
                'class' => 'TwinTack_Debug_Tools',
                'instantiate' => true
            ),
            'admin_enhancements' => array(
                'file' => TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-admin-order-enhancements.php',
                'class' => 'TwinTack_Admin_Order_Enhancements',
                'instantiate' => true
            ),
            'shippo_tracking_display' => array(
                'file' => TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-shippo-tracking-display.php',
                'class' => 'TwinTack_Shippo_Tracking_Display',
                'instantiate' => true
            ),
            'bulk_invoice_manager' => array(
                'file' => TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-bulk-invoice-manager.php',
                'class' => 'TwinTack_Bulk_Invoice_Manager',
                'instantiate' => true
            ),
            'shippo_sync_admin' => array(
                'file' => TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-shippo-sync-admin.php',
                'class' => 'TwinTack_Shippo_Sync_Admin',
                'instantiate' => true
            ),
            'pdf_invoice_generator' => array(
                'file' => TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-pdf-invoice-generator.php',
                'class' => 'TwinTack_PDF_Invoice_Generator',
                'instantiate' => false  // Only instantiate when needed
            ),
            'cron_health_monitor' => array(
                'file' => TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-cron-health-monitor.php',
                'class' => 'TwinTack_Cron_Health_Monitor',
                'instantiate' => true
            )
        );
        
        $loaded_count = 0;
        $failed_count = 0;
        
        foreach ($classes_to_load as $name => $config) {
            try {
                twintack_manual_payments_log("Loading {$name}...");
                
                // Check if file exists
                if (!file_exists($config['file'])) {
                    twintack_manual_payments_log("FAILED: File not found - {$config['file']}", 'error');
                    $failed_count++;
                    continue;
                }
                
                // Include the file
                ob_start(); // Capture any output/errors
                $include_result = require_once $config['file'];
                $output = ob_get_clean();
                
                if ($output) {
                    twintack_manual_payments_log("Include output for {$name}: " . $output);
                }
                
                // Check if class exists after include
                if (!class_exists($config['class'])) {
                    twintack_manual_payments_log("FAILED: Class {$config['class']} not found after including {$config['file']}", 'error');
                    $failed_count++;
                    continue;
                }
                
                // Instantiate if needed
                if ($config['instantiate']) {
                    $instance = call_user_func(array($config['class'], 'get_instance'));
                    if (!$instance) {
                        twintack_manual_payments_log("FAILED: Could not instantiate {$config['class']}", 'error');
                        $failed_count++;
                        continue;
                    }
                }
                
                twintack_manual_payments_log("SUCCESS: {$name} loaded and " . ($config['instantiate'] ? 'instantiated' : 'ready'));
                $loaded_count++;
                
            } catch (Throwable $e) {
                twintack_manual_payments_log("CRITICAL ERROR loading {$name}: " . $e->getMessage(), 'error');
                twintack_manual_payments_log("Error file: " . $e->getFile() . " line " . $e->getLine(), 'error');
                twintack_manual_payments_log("Stack trace: " . $e->getTraceAsString(), 'error');
                $failed_count++;
            }
        }
        
        twintack_manual_payments_log("=== LOADING COMPLETE: {$loaded_count} successful, {$failed_count} failed ===");
        
        // Final verification
        $final_status = array();
        foreach ($classes_to_load as $name => $config) {
            $exists = class_exists($config['class']);
            $final_status[] = "{$name}: " . ($exists ? 'OK' : 'MISSING');
        }
        
        twintack_manual_payments_log("Final class status: " . implode(', ', $final_status));
    }
    

    
    /**
     * Register custom email classes with WooCommerce
     */
    public function register_email_classes($email_classes) {
        // Include the email class file
        require_once TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-payment-link-email.php';
        
        // Add our custom email to WooCommerce emails
        $email_classes['TwinTack_Payment_Link_Email'] = new TwinTack_Payment_Link_Email();
        
        return $email_classes;
    }
    
    /**
     * Register custom payment gateways with WooCommerce
     */
    public function register_payment_gateways($gateways) {
        if (class_exists('TwinTack_Invoice_Payment_Gateway')) {
            $gateways[] = 'TwinTack_Invoice_Payment_Gateway';
        }
        return $gateways;
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