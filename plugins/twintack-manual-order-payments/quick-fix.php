<?php
/**
 * TwinTack Quick Fix
 * 
 * Upload this to your plugins/twintack-manual-order-payments/ directory
 * 
 * This ensures the new classes load properly by forcing the load_admin_functionality method to execute.
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
                    twintack_manual_payments_log('Quick fix: Successfully loaded admin functionality');
                }
            }
            
        } catch (Exception $e) {
            // Log error if debug mode is on
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log('Quick fix error: ' . $e->getMessage(), 'error');
            }
        }
    }
}, 25); // Priority 25 to run after the main plugin (priority 20)

/**
 * Alternative: Ensure classes are loaded on admin_init as well
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
                    twintack_manual_payments_log('Quick fix (admin_init): Loaded missing classes: ' . implode(', ', $missing_classes));
                }
            }
            
        } catch (Exception $e) {
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log('Quick fix (admin_init) error: ' . $e->getMessage(), 'error');
            }
        }
    }
}, 5); // Early priority to ensure it runs before other admin functionality

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
});

/**
 * Debug helper: Show what classes are loaded
 */
add_action('admin_notices', function() {
    // Only show to admins and only if debug mode is on
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check if we should show debug info
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
    }
});

/**
 * Add admin notice with success message
 */
add_action('admin_notices', function() {
    // Only show once and only to admins
    if (!current_user_can('manage_options') || get_transient('twintack_quick_fix_notice_shown')) {
        return;
    }
    
    // Check if our classes are now loaded
    $classes_loaded = class_exists('TwinTack_Debug_Tools') && class_exists('TwinTack_Order_Status_Manager');
    
    if ($classes_loaded) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>TwinTack Quick Fix Applied!</strong> ';
        echo 'New functionality is now available. Check <strong>WooCommerce → TwinTack Debug</strong> and look for "Invoiced" orders in your order list.</p>';
        echo '</div>';
        
        // Don't show this notice again for 24 hours
        set_transient('twintack_quick_fix_notice_shown', true, DAY_IN_SECONDS);
    }
}); 