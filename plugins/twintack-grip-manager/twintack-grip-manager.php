<?php
/**
 * Plugin Name: TwinTack Grip Manager
 * Description: Manages custom grip orders with Gravity Forms and WooCommerce integration. Features separate post/artwork status, Monday.com integration, customer dashboard display, and configurable volume pricing.
 * Version: 1.6.0
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
        add_action('plugins_loaded', array($this, 'init'), 10);
        
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Add deactivation hook to clean up
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Debug log to confirm plugin is loading
        if (WP_DEBUG) {
            error_log('TwinTack Grip Manager initializing...');
        }
        
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
        require_once plugin_dir_path(__FILE__) . 'includes/class-grip-volume-pricing.php';
        
        // Initialize components - ensure post type is registered first
        $post_type = TwinTack_Grip_Post_Type::get_instance();
        
        if (WP_DEBUG) {
            error_log('TwinTack Grip Manager: Post type instance created');
        }
        
        TwinTack_Grip_Form_Handler::get_instance();
        
        // Only load admin in admin area
        if (is_admin()) {
            TwinTack_Grip_Admin::get_instance();
        }

        // Initialize account features (always load for AJAX support)
        TwinTack_Grip_Account::get_instance();
        
        // Initialize volume pricing system
        TwinTack_Grip_Volume_Pricing::get_instance();
        
        // Only add template hijacking prevention for frontend
        if (!is_admin()) {
            // Prevent theme template from hijacking our endpoint
            add_action('template_redirect', array($this, 'prevent_theme_template_hijacking'));
        }

        // Add CSS for grip designs
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        
        // Force flush rewrite rules if needed (only once)
        $this->maybe_flush_rules();
    }
    
    private function maybe_flush_rules() {
        $version_option = 'twintack_grip_manager_version';
        $current_version = get_option($version_option);
        $plugin_version = '1.5.1';
        
        if ($current_version !== $plugin_version) {
            // Force flush rewrite rules
            flush_rewrite_rules();
            update_option($version_option, $plugin_version);
            
            if (WP_DEBUG) {
                error_log('TwinTack Grip Manager: Flushed rewrite rules for version ' . $plugin_version);
            }
        }
    }
    
    public function prevent_theme_template_hijacking() {
        global $wp_query;
        
        // Check if we're on the grip-designs endpoint
        if (isset($wp_query->query_vars['grip-designs'])) {
            // Remove any theme template hooks that might be interfering
            remove_all_filters('template_include');
            
            // Add our template include filter back with high priority
            add_filter('template_include', array($this, 'use_default_template'), 999);
        }
    }
    
    public function use_default_template($template) {
        // Use the default template for our endpoint
        return locate_template(array('page.php', 'single.php', 'index.php'));
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
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        if (WP_DEBUG) {
            error_log('TwinTack Grip Manager activated');
        }
    }
    
    public function deactivate() {
        // Flush rewrite rules on deactivation
        flush_rewrite_rules();
        
        if (WP_DEBUG) {
            error_log('TwinTack Grip Manager deactivated');
        }
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            'grip-designs',
            plugins_url('assets/css/grip-designs.css', __FILE__),
            array(),
            '1.5.1'
        );
    }
}

// Initialize the plugin immediately
TwinTack_Grip_Manager::get_instance();


