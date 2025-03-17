<?php
/**
 * Plugin Name: TwinTack Grip Manager
 * Description: Manages custom grip orders with Gravity Forms and WooCommerce integration
 * Version: 1.0.0
 * Author: TwinTack Team
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

class TwinTack_Grip_Manager {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook into plugins_loaded to ensure WooCommerce is loaded first
        add_action('plugins_loaded', array($this, 'init'), 0);
        
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function init() {
        // Check WooCommerce dependency first
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Check Gravity Forms dependency
        if (!class_exists('GFAPI')) {
            add_action('admin_notices', array($this, 'gravityforms_missing_notice'));
            return;
        }
        
        // Load dependencies after confirming requirements are met
        require_once plugin_dir_path(__FILE__) . 'includes/class-grip-post-type.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-grip-form-handler.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-grip-admin.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-grip-account.php';
        
        // Initialize components
        TwinTack_Grip_Post_Type::get_instance();
        TwinTack_Grip_Form_Handler::get_instance();
        
        // Only load admin in admin area
        if (is_admin()) {
            TwinTack_Grip_Admin::get_instance();
        }

        // Initialize account features
        if (!is_admin()) {
            TwinTack_Grip_Account::get_instance();
        }

        // Add CSS for grip designs
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }
    
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('TwinTack Grip Manager requires WooCommerce to be installed and activated.', 'twintack-grip-manager'); ?></p>
        </div>
        <?php
    }
    
    public function gravityforms_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('TwinTack Grip Manager requires Gravity Forms to be installed and activated.', 'twintack-grip-manager'); ?></p>
        </div>
        <?php
    }
    
    public function activate() {
        // Load and initialize post type
        require_once plugin_dir_path(__FILE__) . 'includes/class-grip-post-type.php';
        $post_type = TwinTack_Grip_Post_Type::get_instance();
        $post_type->register_post_type();
        $post_type->register_statuses();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            'grip-designs',
            plugins_url('assets/css/grip-designs.css', dirname(__FILE__)),
            array(),
            '1.0.0'
        );
    }
}

// Initialize the plugin
add_action('plugins_loaded', array('TwinTack_Grip_Manager', 'get_instance'), -10);


