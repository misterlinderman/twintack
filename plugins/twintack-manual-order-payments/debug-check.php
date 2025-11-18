<?php
/**
 * TwinTack Debug Check Script
 * 
 * Upload this to your WordPress root directory and access via:
 * yourdomain.com/debug-check.php
 * 
 * This will show you exactly what's working and what isn't.
 */

// Include WordPress
require_once('wp-config.php');
require_once('wp-load.php');

// Start output
?>
<!DOCTYPE html>
<html>
<head>
    <title>TwinTack Plugin Debug Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        pre { background: #f1f1f1; padding: 10px; border-radius: 3px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>TwinTack Manual Order Payments - Debug Check</h1>
    <p><em>Generated: <?php echo date('Y-m-d H:i:s'); ?></em></p>

    <div class="section">
        <h2>1. Plugin Status Check</h2>
        <?php
        // Check if plugin is active
        if (is_plugin_active('twintack-manual-order-payments/twintack-manual-order-payments.php')) {
            echo '<div class="success">✅ Plugin is ACTIVE</div>';
        } else {
            echo '<div class="error">❌ Plugin is NOT ACTIVE</div>';
        }

        // Check plugin version
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/twintack-manual-order-payments/twintack-manual-order-payments.php');
        echo '<div class="info">📊 Plugin Version: ' . $plugin_data['Version'] . '</div>';
        
        // Check if constants are defined
        if (defined('TWINTACK_MANUAL_PAYMENTS_VERSION')) {
            echo '<div class="success">✅ Plugin constants loaded: ' . TWINTACK_MANUAL_PAYMENTS_VERSION . '</div>';
        } else {
            echo '<div class="error">❌ Plugin constants NOT loaded</div>';
        }
        ?>
    </div>

    <div class="section">
        <h2>2. Class Loading Check</h2>
        <?php
        $classes_to_check = array(
            'TwinTack_Manual_Order_Payments' => 'Main plugin class',
            'TwinTack_Order_Status_Manager' => 'Order status manager (NEW)',
            'TwinTack_Shippo_Integration' => 'Shippo integration (NEW)',
            'TwinTack_Debug_Tools' => 'Debug tools (NEW)',
            'TwinTack_Admin_Order_Enhancements' => 'Admin enhancements',
            'TwinTack_Invoice_Payment_Gateway' => 'Invoice gateway (NEW)'
        );

        foreach ($classes_to_check as $class_name => $description) {
            if (class_exists($class_name)) {
                echo '<div class="success">✅ ' . $class_name . ' - ' . $description . '</div>';
            } else {
                echo '<div class="error">❌ ' . $class_name . ' - ' . $description . ' (NOT LOADED)</div>';
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>3. File Existence Check</h2>
        <?php
        $files_to_check = array(
            'includes/class-order-status-manager.php' => 'Order Status Manager',
            'includes/class-shippo-integration.php' => 'Shippo Integration', 
            'includes/class-debug-tools.php' => 'Debug Tools',
            'includes/class-invoice-payment-gateway.php' => 'Invoice Gateway',
            'includes/class-admin-order-enhancements.php' => 'Admin Enhancements'
        );

        $plugin_dir = WP_PLUGIN_DIR . '/twintack-manual-order-payments/';
        
        foreach ($files_to_check as $file_path => $description) {
            $full_path = $plugin_dir . $file_path;
            if (file_exists($full_path)) {
                $file_size = filesize($full_path);
                $file_date = date('Y-m-d H:i:s', filemtime($full_path));
                echo '<div class="success">✅ ' . $description . ' (' . round($file_size/1024, 1) . 'KB, ' . $file_date . ')</div>';
            } else {
                echo '<div class="error">❌ ' . $description . ' - FILE NOT FOUND: ' . $file_path . '</div>';
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>4. WooCommerce Order Status Check</h2>
        <?php
        $order_statuses = wc_get_order_statuses();
        echo '<div class="info">📋 Total Order Statuses: ' . count($order_statuses) . '</div>';
        
        if (isset($order_statuses['wc-invoiced'])) {
            echo '<div class="success">✅ "Invoiced" status is registered: ' . $order_statuses['wc-invoiced'] . '</div>';
        } else {
            echo '<div class="error">❌ "Invoiced" status is NOT registered</div>';
        }

        echo '<div class="info">📝 All registered statuses:</div>';
        echo '<pre>';
        foreach ($order_statuses as $key => $label) {
            echo $key . ' → ' . $label . "\n";
        }
        echo '</pre>';
        ?>
    </div>

    <div class="section">
        <h2>5. Database Order Check</h2>
        <?php
        global $wpdb;
        
        // Check for invoiced orders
        $invoiced_count = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_type = 'shop_order' 
            AND post_status = 'wc-invoiced'
        ");
        
        echo '<div class="info">📊 Orders with "wc-invoiced" status: ' . $invoiced_count . '</div>';
        
        // Check orders 1569 and 1582 specifically
        $specific_orders = $wpdb->get_results("
            SELECT ID, post_status, post_date 
            FROM {$wpdb->posts} 
            WHERE post_type = 'shop_order' 
            AND ID IN (1569, 1582)
        ");
        
        if ($specific_orders) {
            echo '<div class="info">🔍 Orders 1569 & 1582 status:</div>';
            foreach ($specific_orders as $order) {
                echo '<div class="info">Order #' . $order->ID . ': ' . $order->post_status . ' (' . $order->post_date . ')</div>';
            }
        } else {
            echo '<div class="warning">⚠️ Orders 1569 & 1582 not found in database</div>';
        }
        ?>
    </div>

    <div class="section">
        <h2>6. WordPress Hooks Check</h2>
        <?php
        // Check if admin menu hooks are registered
        global $wp_filter;
        
        $hooks_to_check = array(
            'admin_menu' => 'Admin menu registration',
            'init' => 'Plugin initialization',
            'plugins_loaded' => 'Plugin loading',
            'woocommerce_order_status_changed' => 'Order status changes'
        );

        foreach ($hooks_to_check as $hook => $description) {
            if (isset($wp_filter[$hook])) {
                $callbacks = count($wp_filter[$hook]->callbacks);
                echo '<div class="success">✅ ' . $description . ' hook has ' . $callbacks . ' callbacks</div>';
                
                // Check for TwinTack callbacks specifically
                $twintack_callbacks = 0;
                foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks_array) {
                    foreach ($callbacks_array as $callback) {
                        if (is_array($callback['function']) && is_object($callback['function'][0])) {
                            $class_name = get_class($callback['function'][0]);
                            if (strpos($class_name, 'TwinTack') !== false) {
                                $twintack_callbacks++;
                            }
                        }
                    }
                }
                
                if ($twintack_callbacks > 0) {
                    echo '<div class="info">   └── TwinTack callbacks: ' . $twintack_callbacks . '</div>';
                } else {
                    echo '<div class="warning">   └── No TwinTack callbacks found</div>';
                }
            } else {
                echo '<div class="error">❌ ' . $description . ' hook not found</div>';
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>7. PHP Error Check</h2>
        <?php
        // Try to instantiate classes manually to check for errors
        echo '<div class="info">🔧 Manual class instantiation test:</div>';
        
        if (class_exists('TwinTack_Order_Status_Manager')) {
            try {
                $status_manager = TwinTack_Order_Status_Manager::get_instance();
                echo '<div class="success">✅ Order Status Manager instantiated successfully</div>';
            } catch (Exception $e) {
                echo '<div class="error">❌ Order Status Manager error: ' . $e->getMessage() . '</div>';
            }
        }
        
        if (class_exists('TwinTack_Debug_Tools')) {
            try {
                $debug_tools = TwinTack_Debug_Tools::get_instance();
                echo '<div class="success">✅ Debug Tools instantiated successfully</div>';
            } catch (Exception $e) {
                echo '<div class="error">❌ Debug Tools error: ' . $e->getMessage() . '</div>';
            }
        }
        
        if (class_exists('TwinTack_Shippo_Integration')) {
            try {
                $shippo = TwinTack_Shippo_Integration::get_instance();
                echo '<div class="success">✅ Shippo Integration instantiated successfully</div>';
            } catch (Exception $e) {
                echo '<div class="error">❌ Shippo Integration error: ' . $e->getMessage() . '</div>';
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>8. Admin Menu Check</h2>
        <?php
        global $menu, $submenu;
        
        echo '<div class="info">🔍 Checking for TwinTack Debug menu...</div>';
        
        $found_debug_menu = false;
        if (isset($submenu['woocommerce'])) {
            foreach ($submenu['woocommerce'] as $item) {
                if (strpos($item[0], 'TwinTack Debug') !== false || $item[2] === 'twintack-debug') {
                    echo '<div class="success">✅ TwinTack Debug menu found: ' . $item[0] . '</div>';
                    $found_debug_menu = true;
                }
            }
        }
        
        if (!$found_debug_menu) {
            echo '<div class="error">❌ TwinTack Debug menu NOT found</div>';
            echo '<div class="info">Available WooCommerce submenus:</div>';
            if (isset($submenu['woocommerce'])) {
                echo '<pre>';
                foreach ($submenu['woocommerce'] as $item) {
                    echo $item[0] . ' (' . $item[2] . ')' . "\n";
                }
                echo '</pre>';
            } else {
                echo '<div class="warning">⚠️ No WooCommerce submenus found</div>';
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>9. Recommendations</h2>
        <?php
        echo '<div class="info">📋 Based on the above results:</div>';
        
        if (!class_exists('TwinTack_Debug_Tools')) {
            echo '<div class="error">🔧 CRITICAL: Debug Tools class not loading</div>';
            echo '<div class="info">   → Check if includes/class-debug-tools.php was uploaded correctly</div>';
            echo '<div class="info">   → Verify file permissions</div>';
        }
        
        if (!isset($order_statuses['wc-invoiced'])) {
            echo '<div class="error">🔧 CRITICAL: Invoiced status not registered</div>';
            echo '<div class="info">   → Order Status Manager may not be loading</div>';
            echo '<div class="info">   → Check includes/class-order-status-manager.php</div>';
        }
        
        if ($invoiced_count == 0) {
            echo '<div class="warning">⚠️ No invoiced orders found in database</div>';
            echo '<div class="info">   → Orders 1569 & 1582 may need to be re-processed</div>';
        }
        ?>
    </div>

    <div class="section">
        <h2>10. Next Steps</h2>
        <div class="info">
            <p><strong>If classes are not loading:</strong></p>
            <ol>
                <li>Verify all files were uploaded to the correct directory</li>
                <li>Check file permissions (should be 644 for files, 755 for directories)</li>
                <li>Clear any caching plugins</li>
                <li>Deactivate and reactivate the plugin</li>
            </ol>
            
            <p><strong>If order status is not registered:</strong></p>
            <ol>
                <li>Go to WordPress admin > Plugins</li>
                <li>Deactivate TwinTack Manual Order Payments</li>
                <li>Reactivate the plugin</li>
                <li>Visit any admin page to trigger hooks</li>
            </ol>
            
            <p><strong>Manual force registration (emergency):</strong></p>
            <p>Add this to your theme's functions.php temporarily:</p>
            <pre>add_action('init', function() {
    register_post_status('wc-invoiced', array(
        'label' => 'Invoiced',
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
    ));
});</pre>
        </div>
    </div>

</body>
</html>

<?php
// Clean up
// Remember to delete this file after debugging!
?> 