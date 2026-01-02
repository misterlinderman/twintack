<?php
/**
 * Plugin Name: TwinTack Admin Console Fixes
 * Plugin URI: https://twintack.com
 * Description: Fixes WordPress admin console errors and Variable Product type recognition issues. Addresses jQuery migration warnings, React component instability, useSelect hook warnings, and wholesale plugin conflicts.
 * Version: 1.0.9
 * Author: TwinTack Development Team
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: twintack-admin-console-fixes
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 * Requires Plugins: woocommerce
 * 
 * @package TwinTack_Admin_Console_Fixes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Declare WooCommerce HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Define plugin constants
define('TWINTACK_CONSOLE_FIXES_VERSION', '1.0.6');
define('TWINTACK_CONSOLE_FIXES_PLUGIN_FILE', __FILE__);
define('TWINTACK_CONSOLE_FIXES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TWINTACK_CONSOLE_FIXES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TWINTACK_CONSOLE_FIXES_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main TwinTack Admin Console Fixes Plugin Class
 */
class TwinTack_Admin_Console_Fixes {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get single instance of the class
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
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Check WooCommerce version compatibility
        if (version_compare(WC()->version, '6.0', '<')) {
            add_action('admin_notices', array($this, 'woocommerce_version_notice'));
            return;
        }

        // Load plugin classes
        $this->load_classes();
        
        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Load plugin classes
     */
    private function load_classes() {
        require_once TWINTACK_CONSOLE_FIXES_PLUGIN_DIR . 'includes/class-console-fixes.php';
        require_once TWINTACK_CONSOLE_FIXES_PLUGIN_DIR . 'includes/class-product-type-fix.php';
        require_once TWINTACK_CONSOLE_FIXES_PLUGIN_DIR . 'includes/class-wholesale-plugin-patch.php';
        require_once TWINTACK_CONSOLE_FIXES_PLUGIN_DIR . 'includes/class-nounproject-api.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize console fixes
        new TwinTack_Console_Fixes();
        
        // Initialize product type fixes
        new TwinTack_Product_Type_Fix();
        
        // Initialize wholesale plugin patch
        new TwinTack_Wholesale_Plugin_Patch();
        
        // Initialize Noun Project API (shared across all TwinTack plugins)
        TwinTack_NounProject_API::get_instance();
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on Noun Project settings page
        if ($hook !== 'toplevel_page_twintack-nounproject') {
            return;
        }
        
        wp_enqueue_style(
            'twintack-console-fixes-admin',
            TWINTACK_CONSOLE_FIXES_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            TWINTACK_CONSOLE_FIXES_VERSION
        );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log activation
        error_log('TwinTack Admin Console Fixes plugin activated');
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        error_log('TwinTack Admin Console Fixes plugin deactivated');
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong>TwinTack Admin Console Fixes</strong> requires WooCommerce to be installed and active.
                Please install and activate WooCommerce to use this plugin.
            </p>
        </div>
        <?php
    }

    /**
     * WooCommerce version notice
     */
    public function woocommerce_version_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong>TwinTack Admin Console Fixes</strong> requires WooCommerce version 6.0 or higher.
                You are currently running WooCommerce version <?php echo WC()->version; ?>.
                Please update WooCommerce to use this plugin.
            </p>
        </div>
        <?php
    }
}

// Initialize the plugin
TwinTack_Admin_Console_Fixes::get_instance();
