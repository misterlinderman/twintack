<?php
/**
 * Force Load Classes Debug Script
 * 
 * Upload this to your plugins/twintack-manual-order-payments/ directory
 * Access via: yourdomain.com/wp-content/plugins/twintack-manual-order-payments/force-load-classes.php
 * 
 * This will force load the classes and show any PHP errors.
 */

// Include WordPress
$wp_config_found = false;
$dir = dirname(__FILE__);

// Look for wp-config.php going up directories
for ($i = 0; $i < 10; $i++) {
    if (file_exists($dir . '/wp-config.php')) {
        require_once($dir . '/wp-config.php');
        $wp_config_found = true;
        break;
    }
    $dir = dirname($dir);
}

if (!$wp_config_found) {
    // Try alternative paths
    $possible_paths = array(
        __DIR__ . '/../../../../wp-config.php',
        __DIR__ . '/../../../wp-config.php',
        __DIR__ . '/../../wp-config.php'
    );
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            $wp_config_found = true;
            break;
        }
    }
}

if (!$wp_config_found) {
    die('WordPress configuration not found. Please check file paths.');
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Force Load Classes - TwinTack Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        .step { margin: 15px 0; padding: 15px; border-left: 4px solid #0073aa; background: #f9f9f9; }
        pre { background: #f1f1f1; padding: 15px; border-radius: 3px; font-size: 12px; overflow-x: auto; }
        .php-error { background: #ffebee; border: 1px solid #f44336; padding: 10px; margin: 10px 0; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Force Load Classes - TwinTack Debug</h1>
        <p><em>Generated: <?php echo date('Y-m-d H:i:s'); ?></em></p>

        <div class="step">
            <h3>🔍 Environment Check</h3>
            <?php
            echo '<div class="info">📂 Current Directory: ' . __DIR__ . '</div>';
            echo '<div class="info">🐘 PHP Version: ' . phpversion() . '</div>';
            echo '<div class="info">📋 WordPress: ' . (defined('ABSPATH') ? 'Loaded ✅' : 'Not Loaded ❌') . '</div>';
            echo '<div class="info">🛒 WooCommerce: ' . (function_exists('WC') ? 'Available ✅' : 'Not Available ❌') . '</div>';
            ?>
        </div>

        <div class="step">
            <h3>🚀 Force Loading Classes with Full Error Reporting</h3>
            <?php
            
            // Capture any PHP errors
            $error_handler = function($errno, $errstr, $errfile, $errline) {
                echo '<div class="php-error">🚨 <strong>PHP Error:</strong> ' . $errstr . '<br>';
                echo '<strong>File:</strong> ' . $errfile . ' <strong>Line:</strong> ' . $errline . '</div>';
                return true; // Don't stop execution
            };
            
            set_error_handler($error_handler);
            
            $plugin_dir = __DIR__ . '/';
            
            $classes_to_load = array(
                'class-order-status-manager.php' => 'TwinTack_Order_Status_Manager',
                'class-shippo-integration.php' => 'TwinTack_Shippo_Integration', 
                'class-debug-tools.php' => 'TwinTack_Debug_Tools',
                'class-invoice-payment-gateway.php' => 'TwinTack_Invoice_Payment_Gateway',
                'class-admin-order-enhancements.php' => 'TwinTack_Admin_Order_Enhancements'
            );
            
            foreach ($classes_to_load as $filename => $classname) {
                echo '<h4>📄 Loading: ' . $filename . '</h4>';
                
                $file_path = $plugin_dir . 'includes/' . $filename;
                echo '<div class="info">File Path: ' . $file_path . '</div>';
                
                if (!file_exists($file_path)) {
                    echo '<div class="error">❌ File does not exist!</div>';
                    continue;
                }
                
                echo '<div class="success">✅ File exists (' . round(filesize($file_path)/1024, 1) . 'KB)</div>';
                
                // Check if already loaded
                if (class_exists($classname)) {
                    echo '<div class="warning">⚠️ Class already exists: ' . $classname . '</div>';
                    continue;
                }
                
                // Attempt to include
                echo '<div class="info">🔄 Including file...</div>';
                
                ob_start();
                $include_result = include_once($file_path);
                $output = ob_get_clean();
                
                if ($output) {
                    echo '<div class="warning">📝 Output during include:</div>';
                    echo '<pre>' . htmlspecialchars($output) . '</pre>';
                }
                
                if ($include_result === false) {
                    echo '<div class="error">❌ Include failed!</div>';
                    continue;
                }
                
                echo '<div class="success">✅ File included successfully</div>';
                
                // Check if class now exists
                if (class_exists($classname)) {
                    echo '<div class="success">🎉 Class loaded: ' . $classname . '</div>';
                    
                    // Try to instantiate if it has get_instance method
                    if (method_exists($classname, 'get_instance')) {
                        try {
                            echo '<div class="info">🔧 Attempting to instantiate...</div>';
                            $instance = $classname::get_instance();
                            echo '<div class="success">✅ Instance created successfully</div>';
                            
                            // For Order Status Manager, check if status gets registered
                            if ($classname === 'TwinTack_Order_Status_Manager') {
                                $statuses = wc_get_order_statuses();
                                if (isset($statuses['wc-invoiced'])) {
                                    echo '<div class="success">🎯 INVOICED STATUS REGISTERED!</div>';
                                } else {
                                    echo '<div class="warning">⚠️ Invoiced status not yet registered (may need init hook)</div>';
                                }
                            }
                            
                        } catch (Exception $e) {
                            echo '<div class="error">❌ Instantiation failed: ' . $e->getMessage() . '</div>';
                            echo '<div class="info">Stack trace:</div>';
                            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
                        }
                    } else {
                        echo '<div class="info">ℹ️ No get_instance method found</div>';
                    }
                    
                } else {
                    echo '<div class="error">❌ Class not found after include: ' . $classname . '</div>';
                    
                    // Show what classes are available
                    $declared_classes = get_declared_classes();
                    $twintack_classes = array_filter($declared_classes, function($class) {
                        return strpos($class, 'TwinTack') !== false;
                    });
                    
                    if (!empty($twintack_classes)) {
                        echo '<div class="info">🔍 Available TwinTack classes:</div>';
                        echo '<pre>' . implode("\n", $twintack_classes) . '</pre>';
                    }
                }
                
                echo '<hr style="margin: 20px 0;">';
            }
            
            // Restore error handler
            restore_error_handler();
            ?>
        </div>

        <div class="step">
            <h3>🔍 Final Status Check</h3>
            <?php
            echo '<h4>📋 Class Status Summary:</h4>';
            foreach ($classes_to_load as $filename => $classname) {
                if (class_exists($classname)) {
                    echo '<div class="success">✅ ' . $classname . '</div>';
                } else {
                    echo '<div class="error">❌ ' . $classname . '</div>';
                }
            }
            
            echo '<h4>📋 WooCommerce Order Statuses:</h4>';
            if (function_exists('wc_get_order_statuses')) {
                $statuses = wc_get_order_statuses();
                if (isset($statuses['wc-invoiced'])) {
                    echo '<div class="success">🎯 wc-invoiced: ' . $statuses['wc-invoiced'] . '</div>';
                } else {
                    echo '<div class="error">❌ wc-invoiced status not found</div>';
                }
                
                echo '<div class="info">All statuses (' . count($statuses) . '):</div>';
                echo '<pre>';
                foreach ($statuses as $key => $label) {
                    echo $key . ' → ' . $label . "\n";
                }
                echo '</pre>';
            } else {
                echo '<div class="error">❌ wc_get_order_statuses function not available</div>';
            }
            ?>
        </div>

        <div class="step">
            <h3>🛠️ Recommendations Based on Results</h3>
            <div class="info">
                <p><strong>If classes loaded successfully here but not in normal operation:</strong></p>
                <ul>
                    <li>The issue is with the plugin's loading sequence</li>
                    <li>Try deactivating and reactivating the plugin</li>
                    <li>Check if there are any caching plugins interfering</li>
                </ul>
                
                <p><strong>If you see PHP errors above:</strong></p>
                <ul>
                    <li>Fix the specific PHP errors shown</li>
                    <li>Check PHP version compatibility</li>
                    <li>Verify all files were uploaded correctly</li>
                </ul>
                
                <p><strong>If classes won't load at all:</strong></p>
                <ul>
                    <li>Check file permissions (should be 644)</li>
                    <li>Verify WordPress and WooCommerce are properly loaded</li>
                    <li>Check for conflicting plugins</li>
                </ul>
            </div>
        </div>

        <div class="step">
            <h3>⚠️ Important</h3>
            <p><strong>Delete this file after debugging for security reasons.</strong></p>
        </div>

    </div>
</body>
</html> 