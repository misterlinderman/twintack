<?php
/**
 * TwinTack Plugin Activation Fixer
 * 
 * Upload this to your plugins/twintack-manual-order-payments/ directory
 * Access via: yourdomain.com/wp-content/plugins/twintack-manual-order-payments/fix-activation.php
 * 
 * This will manually force the activation of new features.
 */

// Security check
if (!defined('ABSPATH')) {
    // Include WordPress if not already loaded
    $wp_config_path = '';
    $dir = dirname(__FILE__);
    
    // Look for wp-config.php
    for ($i = 0; $i < 5; $i++) {
        if (file_exists($dir . '/wp-config.php')) {
            $wp_config_path = $dir . '/wp-config.php';
            break;
        }
        $dir = dirname($dir);
    }
    
    if ($wp_config_path) {
        require_once($wp_config_path);
    } else {
        die('WordPress not found. Please move this file to the correct location.');
    }
}

// Ensure we have WordPress loaded
if (!function_exists('add_action')) {
    die('WordPress functions not available.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>TwinTack Plugin Activation Fixer</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        .step { margin: 15px 0; padding: 10px; border-left: 3px solid #0073aa; background: #f9f9f9; }
        button { background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
        button:hover { background: #005a87; }
        pre { background: #f1f1f1; padding: 10px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 TwinTack Plugin Activation Fixer</h1>
        <p><em>Generated: <?php echo date('Y-m-d H:i:s'); ?></em></p>

        <?php
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        switch ($action) {
            case 'force_register_status':
                echo '<div class="step">';
                echo '<h3>🚀 Force Registering Order Status</h3>';
                
                // Manually register the order status
                register_post_status('wc-invoiced', array(
                    'label'                     => 'Invoiced',
                    'public'                    => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop('Invoiced <span class="count">(%s)</span>', 'Invoiced <span class="count">(%s)</span>')
                ));
                
                // Add to WooCommerce statuses
                add_filter('wc_order_statuses', function($statuses) {
                    $statuses['wc-invoiced'] = 'Invoiced';
                    return $statuses;
                });
                
                echo '<div class="success">✅ Order status registered manually</div>';
                echo '<div class="info">This is temporary. You still need to fix the main plugin loading.</div>';
                echo '</div>';
                break;
                
            case 'check_files':
                echo '<div class="step">';
                echo '<h3>📁 Checking File Uploads</h3>';
                
                $required_files = array(
                    'includes/class-order-status-manager.php',
                    'includes/class-debug-tools.php', 
                    'includes/class-shippo-integration.php',
                    'includes/class-invoice-payment-gateway.php'
                );
                
                $all_files_exist = true;
                foreach ($required_files as $file) {
                    if (file_exists($file)) {
                        $size = round(filesize($file) / 1024, 1);
                        echo '<div class="success">✅ ' . $file . ' (' . $size . 'KB)</div>';
                    } else {
                        echo '<div class="error">❌ MISSING: ' . $file . '</div>';
                        $all_files_exist = false;
                    }
                }
                
                if ($all_files_exist) {
                    echo '<div class="success">🎉 All required files are present!</div>';
                } else {
                    echo '<div class="error">⚠️ Some files are missing. Please re-upload them.</div>';
                }
                echo '</div>';
                break;
                
            case 'force_load_classes':
                echo '<div class="step">';
                echo '<h3>🔄 Force Loading Classes</h3>';
                
                $class_files = array(
                    'includes/class-order-status-manager.php' => 'TwinTack_Order_Status_Manager',
                    'includes/class-debug-tools.php' => 'TwinTack_Debug_Tools',
                    'includes/class-shippo-integration.php' => 'TwinTack_Shippo_Integration',
                    'includes/class-invoice-payment-gateway.php' => 'TwinTack_Invoice_Payment_Gateway'
                );
                
                foreach ($class_files as $file => $class_name) {
                    if (file_exists($file)) {
                        try {
                            require_once($file);
                            if (class_exists($class_name)) {
                                echo '<div class="success">✅ ' . $class_name . ' loaded successfully</div>';
                                
                                // Try to instantiate
                                if (method_exists($class_name, 'get_instance')) {
                                    $instance = $class_name::get_instance();
                                    echo '<div class="info">   └── Instance created</div>';
                                }
                            } else {
                                echo '<div class="error">❌ ' . $class_name . ' class not found after include</div>';
                            }
                        } catch (Exception $e) {
                            echo '<div class="error">❌ Error loading ' . $class_name . ': ' . $e->getMessage() . '</div>';
                        }
                    } else {
                        echo '<div class="error">❌ File not found: ' . $file . '</div>';
                    }
                }
                echo '</div>';
                break;
                
            case 'plugin_info':
                echo '<div class="step">';
                echo '<h3>ℹ️ Plugin Information</h3>';
                
                if (function_exists('get_plugin_data')) {
                    $plugin_file = 'twintack-manual-order-payments.php';
                    if (file_exists($plugin_file)) {
                        $plugin_data = get_plugin_data($plugin_file);
                        echo '<div class="info">📊 Plugin Version: ' . $plugin_data['Version'] . '</div>';
                        echo '<div class="info">📝 Plugin Name: ' . $plugin_data['Name'] . '</div>';
                        
                        if (is_plugin_active('twintack-manual-order-payments/twintack-manual-order-payments.php')) {
                            echo '<div class="success">✅ Plugin is active</div>';
                        } else {
                            echo '<div class="error">❌ Plugin is not active</div>';
                        }
                    } else {
                        echo '<div class="error">❌ Main plugin file not found</div>';
                    }
                } else {
                    echo '<div class="warning">⚠️ WordPress plugin functions not available</div>';
                }
                echo '</div>';
                break;
                
            default:
                // Default view - show options
                echo '<div class="step">';
                echo '<h3>🚀 Quick Fixes Available</h3>';
                echo '<p>Choose an action to troubleshoot your plugin issues:</p>';
                
                echo '<p><button onclick="location.href=\'?action=check_files\'">1. Check File Uploads</button></p>';
                echo '<p><button onclick="location.href=\'?action=plugin_info\'">2. Check Plugin Status</button></p>';
                echo '<p><button onclick="location.href=\'?action=force_load_classes\'">3. Force Load Classes</button></p>';
                echo '<p><button onclick="location.href=\'?action=force_register_status\'">4. Emergency: Register Order Status</button></p>';
                echo '</div>';
                
                echo '<div class="step">';
                echo '<h3>📋 Manual Steps to Try</h3>';
                echo '<ol>';
                echo '<li><strong>Deactivate and Reactivate Plugin:</strong> Go to WordPress Admin > Plugins, deactivate TwinTack Manual Order Payments, then reactivate it.</li>';
                echo '<li><strong>Clear Cache:</strong> If you use caching plugins, clear all caches.</li>';
                echo '<li><strong>Check File Permissions:</strong> Ensure files have 644 permissions and directories have 755.</li>';
                echo '<li><strong>WordPress Debug:</strong> Enable WordPress debug mode to see any PHP errors.</li>';
                echo '</ol>';
                echo '</div>';
                
                echo '<div class="step">';
                echo '<h3>🆘 Emergency Order Status Fix</h3>';
                echo '<p>If you need to quickly fix the "Invoiced" order status visibility, add this to your theme\'s functions.php:</p>';
                echo '<pre>// Temporary fix for TwinTack Invoiced status
add_action(\'init\', function() {
    register_post_status(\'wc-invoiced\', array(
        \'label\' => \'Invoiced\',
        \'public\' => true,
        \'exclude_from_search\' => false,
        \'show_in_admin_all_list\' => true,
        \'show_in_admin_status_list\' => true,
    ));
});

add_filter(\'wc_order_statuses\', function($statuses) {
    $statuses[\'wc-invoiced\'] = \'Invoiced\';
    return $statuses;
});</pre>';
                echo '<p><em>Remember to remove this after the plugin is working properly.</em></p>';
                echo '</div>';
                break;
        }
        ?>

        <div class="step">
            <h3>🔄 Back to Main Options</h3>
            <button onclick="location.href='?'">← Back to Main Menu</button>
            <button onclick="location.reload()">🔄 Refresh Page</button>
        </div>

        <div class="step">
            <h3>⚠️ Important Notes</h3>
            <ul>
                <li>Delete this file after troubleshooting for security</li>
                <li>These are temporary fixes - the main plugin still needs to work properly</li>
                <li>Always backup your site before making changes</li>
                <li>Contact support if issues persist</li>
            </ul>
        </div>

    </div>
</body>
</html> 