<?php
/**
 * Plugin Name: TwinTack Manual Payments Fix
 * Plugin URI: https://twintack.com
 * Description: Temporary fix to ensure TwinTack Manual Order Payments loads all new functionality correctly.
 * Version: 1.0.0
 * Author: TwinTack
 * License: GPL v2 or later
 * 
 * This is a temporary plugin to fix the loading issue. Delete this after the main plugin is fixed.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Force TwinTack classes to load
 */
add_action('plugins_loaded', function() {
    // Make sure this runs after the main plugin loads
    if (class_exists('TwinTack_Manual_Order_Payments')) {
        
        // Get the plugin instance
        $plugin = TwinTack_Manual_Order_Payments::get_instance();
        
        // Use reflection to access the private method
        try {
            $reflection = new ReflectionClass($plugin);
            
            if ($reflection->hasMethod('load_admin_functionality')) {
                $method = $reflection->getMethod('load_admin_functionality');
                $method->setAccessible(true);
                
                // Call the method to load all the new classes
                $method->invoke($plugin);
                
                // Log success if debug mode is on
                if (function_exists('twintack_manual_payments_log')) {
                    twintack_manual_payments_log('Fix Plugin: Successfully loaded admin functionality');
                }
            }
            
        } catch (Exception $e) {
            // Log error if debug mode is on
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log('Fix Plugin error: ' . $e->getMessage(), 'error');
            }
        }
    }
}, 25); // Priority 25 to run after the main plugin (priority 20)

/**
 * Backup: Ensure classes are loaded on admin_init as well
 */
add_action('admin_init', function() {
    // Only run in admin
    if (!is_admin()) {
        return;
    }
    
    // Check if our classes are loaded, if not, force load them
    $classes_to_check = array(
        'TwinTack_Order_Status_Manager',
        'TwinTack_Debug_Tools',
        'TwinTack_Shippo_Integration',
        'TwinTack_Admin_Order_Enhancements'
    );
    
    $missing_classes = array();
    foreach ($classes_to_check as $class) {
        if (!class_exists($class)) {
            $missing_classes[] = $class;
        }
    }
    
    // If any classes are missing, force load them
    if (!empty($missing_classes) && class_exists('TwinTack_Manual_Order_Payments')) {
        
        $plugin = TwinTack_Manual_Order_Payments::get_instance();
        
        try {
            $reflection = new ReflectionClass($plugin);
            
            if ($reflection->hasMethod('load_admin_functionality')) {
                $method = $reflection->getMethod('load_admin_functionality');
                $method->setAccessible(true);
                $method->invoke($plugin);
                
                if (function_exists('twintack_manual_payments_log')) {
                    twintack_manual_payments_log('Fix Plugin (admin_init): Loaded missing classes: ' . implode(', ', $missing_classes));
                }
            }
            
        } catch (Exception $e) {
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log('Fix Plugin (admin_init) error: ' . $e->getMessage(), 'error');
            }
        }
    }
}, 5); // Early priority

/**
 * Ensure the invoice payment gateway is registered
 */
add_filter('woocommerce_payment_gateways', function($gateways) {
    if (class_exists('TwinTack_Invoice_Payment_Gateway')) {
        if (!in_array('TwinTack_Invoice_Payment_Gateway', $gateways)) {
            $gateways[] = 'TwinTack_Invoice_Payment_Gateway';
        }
    }
    return $gateways;
}, 20);

/**
 * Add admin notice with success message and debug info
 */
add_action('admin_notices', function() {
    // Only show to admins
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check if our classes are now loaded
    $classes_loaded = class_exists('TwinTack_Debug_Tools') && class_exists('TwinTack_Order_Status_Manager');
    
    if ($classes_loaded) {
        // Don't show too frequently
        if (!get_transient('twintack_fix_success_shown')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>🎉 TwinTack Fix Plugin Active!</strong> ';
            echo 'New functionality is now loaded. Check <strong>WooCommerce → TwinTack Debug</strong> and look for "Invoiced" orders in your order list.</p>';
            echo '</div>';
            
            // Show this notice only once per hour
            set_transient('twintack_fix_success_shown', true, HOUR_IN_SECONDS);
        }
        
        // Show debug info if requested
        if (isset($_GET['twintack_debug']) && $_GET['twintack_debug'] === '1') {
            $classes = array(
                'TwinTack_Order_Status_Manager',
                'TwinTack_Debug_Tools', 
                'TwinTack_Shippo_Integration',
                'TwinTack_Invoice_Payment_Gateway',
                'TwinTack_Admin_Order_Enhancements'
            );
            
            echo '<div class="notice notice-info"><p><strong>TwinTack Class Status:</strong> ';
            foreach ($classes as $class) {
                $status = class_exists($class) ? '✅' : '❌';
                echo $status . ' ' . $class . ' | ';
            }
            echo '</p></div>';
            
            // Also show order statuses
            if (function_exists('wc_get_order_statuses')) {
                $statuses = wc_get_order_statuses();
                echo '<div class="notice notice-info"><p><strong>Order Statuses:</strong> ';
                if (isset($statuses['wc-invoiced'])) {
                    echo '✅ Invoiced status registered | ';
                } else {
                    echo '❌ Invoiced status missing | ';
                }
                echo 'Total: ' . count($statuses) . '</p></div>';
            }
        }
    } else {
        // Show error if classes still not loaded
        echo '<div class="notice notice-error">';
        echo '<p><strong>⚠️ TwinTack Fix Plugin:</strong> ';
        echo 'Classes still not loading. Main plugin may have issues. ';
        echo '<a href="' . admin_url('admin.php?page=plugins&twintack_debug=1') . '">Debug Info</a></p>';
        echo '</div>';
    }
});

/**
 * Add activation hook
 */
register_activation_hook(__FILE__, function() {
    // Clear any caches
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Set a flag that we've been activated
    set_transient('twintack_fix_plugin_activated', true, MINUTE_IN_SECONDS * 5);
});

/**
 * Show activation notice
 */
add_action('admin_notices', function() {
    if (get_transient('twintack_fix_plugin_activated') && current_user_can('manage_options')) {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>TwinTack Fix Plugin Activated!</strong> ';
        echo 'Checking TwinTack functionality... Refresh this page in a few seconds.</p>';
        echo '</div>';
        
        delete_transient('twintack_fix_plugin_activated');
    }
}); 