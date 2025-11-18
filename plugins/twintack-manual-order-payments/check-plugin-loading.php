<?php
/**
 * Plugin Loading Debug Script
 * 
 * Upload this to your plugins/twintack-manual-order-payments/ directory
 * Access via: yourdomain.com/wp-content/plugins/twintack-manual-order-payments/check-plugin-loading.php
 * 
 * This will debug the actual plugin loading process.
 */

// Include WordPress
$wp_config_found = false;
$dir = dirname(__FILE__);

for ($i = 0; $i < 10; $i++) {
    if (file_exists($dir . '/wp-config.php')) {
        require_once($dir . '/wp-config.php');
        $wp_config_found = true;
        break;
    }
    $dir = dirname($dir);
}

if (!$wp_config_found) {
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
    die('WordPress configuration not found.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Plugin Loading Debug - TwinTack</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        .step { margin: 15px 0; padding: 15px; border-left: 4px solid #0073aa; background: #f9f9f9; }
        pre { background: #f1f1f1; padding: 15px; border-radius: 3px; font-size: 12px; overflow-x: auto; }
        button { background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; margin: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Plugin Loading Debug - TwinTack</h1>
        <p><em>Generated: <?php echo date('Y-m-d H:i:s'); ?></em></p>

        <div class="step">
            <h3>🔍 Current WordPress Context</h3>
            <?php
            echo '<div class="info">📄 is_admin(): ' . (is_admin() ? 'true ✅' : 'false ❌') . '</div>';
            echo '<div class="info">🔗 Current Screen: ' . (function_exists('get_current_screen') && get_current_screen() ? get_current_screen()->id : 'Not available') . '</div>';
            echo '<div class="info">🪝 Current Hook: ' . current_action() . '</div>';
            echo '<div class="info">🔄 admin_init fired: ' . (did_action('admin_init') ? 'Yes ✅' : 'No ❌') . '</div>';
            echo '<div class="info">🔄 plugins_loaded fired: ' . (did_action('plugins_loaded') ? 'Yes ✅' : 'No ❌') . '</div>';
            echo '<div class="info">🔄 init fired: ' . (did_action('init') ? 'Yes ✅' : 'No ❌') . '</div>';
            ?>
        </div>

        <div class="step">
            <h3>🚀 Plugin Instance Check</h3>
            <?php
            // Check if main plugin class exists and is instantiated
            if (class_exists('TwinTack_Manual_Order_Payments')) {
                echo '<div class="success">✅ Main plugin class exists</div>';
                
                try {
                    $plugin_instance = TwinTack_Manual_Order_Payments::get_instance();
                    echo '<div class="success">✅ Plugin instance retrieved</div>';
                    
                    // Check if the instance has the methods we expect
                    $methods = get_class_methods($plugin_instance);
                    $expected_methods = array('init', 'admin_init', 'load_admin_functionality');
                    
                    foreach ($expected_methods as $method) {
                        if (in_array($method, $methods)) {
                            echo '<div class="success">✅ Method exists: ' . $method . '</div>';
                        } else {
                            echo '<div class="error">❌ Method missing: ' . $method . '</div>';
                        }
                    }
                    
                } catch (Exception $e) {
                    echo '<div class="error">❌ Error getting plugin instance: ' . $e->getMessage() . '</div>';
                }
            } else {
                echo '<div class="error">❌ Main plugin class not found</div>';
            }
            ?>
        </div>

        <div class="step">
            <h3>🪝 Hook Analysis</h3>
            <?php
            global $wp_filter;
            
            $hooks_to_check = array(
                'plugins_loaded' => 'TwinTack_Manual_Order_Payments->init',
                'admin_init' => 'TwinTack_Manual_Order_Payments->admin_init',
                'init' => 'Order status registration',
                'admin_menu' => 'TwinTack_Debug_Tools->add_debug_menu'
            );
            
            foreach ($hooks_to_check as $hook => $expected) {
                echo '<h4>🔍 Hook: ' . $hook . '</h4>';
                
                if (isset($wp_filter[$hook])) {
                    $priority_count = count($wp_filter[$hook]->callbacks);
                    echo '<div class="info">📊 Total callbacks: ' . $priority_count . '</div>';
                    
                    $twintack_found = false;
                    foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                        foreach ($callbacks as $callback_id => $callback_data) {
                            if (is_array($callback_data['function'])) {
                                if (is_object($callback_data['function'][0])) {
                                    $class_name = get_class($callback_data['function'][0]);
                                    if (strpos($class_name, 'TwinTack') !== false) {
                                        echo '<div class="success">✅ TwinTack callback found: ' . $class_name . '->' . $callback_data['function'][1] . ' (priority: ' . $priority . ')</div>';
                                        $twintack_found = true;
                                    }
                                } elseif (is_string($callback_data['function'][0]) && strpos($callback_data['function'][0], 'TwinTack') !== false) {
                                    echo '<div class="success">✅ TwinTack static callback: ' . $callback_data['function'][0] . '::' . $callback_data['function'][1] . '</div>';
                                    $twintack_found = true;
                                }
                            } elseif (is_string($callback_data['function']) && strpos($callback_data['function'], 'twintack') !== false) {
                                echo '<div class="success">✅ TwinTack function: ' . $callback_data['function'] . '</div>';
                                $twintack_found = true;
                            }
                        }
                    }
                    
                    if (!$twintack_found) {
                        echo '<div class="warning">⚠️ No TwinTack callbacks found for ' . $hook . '</div>';
                    }
                } else {
                    echo '<div class="error">❌ Hook not registered: ' . $hook . '</div>';
                }
                echo '<hr>';
            }
            ?>
        </div>

        <div class="step">
            <h3>💾 WordPress Options Check</h3>
            <?php
            // Check if plugin options exist
            $option_name = 'twintack_manual_payments_settings';
            $options = get_option($option_name);
            
            if ($options) {
                echo '<div class="success">✅ Plugin options exist</div>';
                echo '<div class="info">📄 Settings found: ' . count($options) . ' entries</div>';
            } else {
                echo '<div class="warning">⚠️ Plugin options not found - this might be the first run</div>';
            }
            
            // Check active plugins
            $active_plugins = get_option('active_plugins');
            $plugin_found = false;
            foreach ($active_plugins as $plugin) {
                if (strpos($plugin, 'twintack-manual-order-payments') !== false) {
                    echo '<div class="success">✅ Plugin found in active plugins list: ' . $plugin . '</div>';
                    $plugin_found = true;
                    break;
                }
            }
            
            if (!$plugin_found) {
                echo '<div class="error">❌ Plugin not found in active plugins list</div>';
            }
            ?>
        </div>

        <div class="step">
            <h3>🎯 Force Trigger Plugin Loading</h3>
            <p>Click the button below to manually trigger the plugin's admin_init process:</p>
            
            <?php if (isset($_GET['force_trigger'])): ?>
                <div class="info">🚀 Attempting to force trigger plugin loading...</div>
                <?php
                if (class_exists('TwinTack_Manual_Order_Payments')) {
                    try {
                        $plugin_instance = TwinTack_Manual_Order_Payments::get_instance();
                        
                        echo '<div class="info">🔄 Calling admin_init manually...</div>';
                        $plugin_instance->admin_init();
                        
                        echo '<div class="success">✅ admin_init called successfully</div>';
                        
                        // Check if classes are now loaded
                        $classes = array(
                            'TwinTack_Order_Status_Manager',
                            'TwinTack_Debug_Tools',
                            'TwinTack_Shippo_Integration',
                            'TwinTack_Invoice_Payment_Gateway',
                            'TwinTack_Admin_Order_Enhancements'
                        );
                        
                        echo '<div class="info">📋 Checking class loading results:</div>';
                        foreach ($classes as $class) {
                            if (class_exists($class)) {
                                echo '<div class="success">✅ ' . $class . '</div>';
                            } else {
                                echo '<div class="error">❌ ' . $class . '</div>';
                            }
                        }
                        
                    } catch (Exception $e) {
                        echo '<div class="error">❌ Error during force trigger: ' . $e->getMessage() . '</div>';
                    }
                } else {
                    echo '<div class="error">❌ Main plugin class not available for force trigger</div>';
                }
            else:
            ?>
                <button onclick="location.href='?force_trigger=1'">🚀 Force Trigger Plugin Loading</button>
            <?php endif; ?>
        </div>

        <div class="step">
            <h3>🛠️ Diagnosis & Recommendations</h3>
            <div class="info">
                <p><strong>Based on the above analysis:</strong></p>
                
                <p><strong>If admin_init hasn't fired:</strong> This script is running before WordPress admin is fully loaded. This is normal for direct script access.</p>
                
                <p><strong>If no TwinTack callbacks are found:</strong> The plugin isn't registering its hooks properly during initialization.</p>
                
                <p><strong>Most likely solutions:</strong></p>
                <ol>
                    <li><strong>Deactivate & Reactivate:</strong> This will retrigger the plugin activation hooks</li>
                    <li><strong>Clear all caches:</strong> Including object cache, page cache, etc.</li>
                    <li><strong>Check for plugin conflicts:</strong> Temporarily deactivate other plugins</li>
                    <li><strong>WordPress admin access:</strong> The classes might only load in proper admin context</li>
                </ol>
            </div>
        </div>

        <div class="step">
            <h3>⚡ Quick Fix Options</h3>
            <p><strong>Option 1:</strong> Deactivate and reactivate the plugin</p>
            <p><strong>Option 2:</strong> Add this to your theme's functions.php temporarily to force loading:</p>
            <pre>add_action('init', function() {
    if (is_admin() && class_exists('TwinTack_Manual_Order_Payments')) {
        $plugin = TwinTack_Manual_Order_Payments::get_instance();
        if (method_exists($plugin, 'admin_init')) {
            $plugin->admin_init();
        }
    }
});</pre>
            <p><em>Remove this after the plugin works normally.</em></p>
        </div>

    </div>
</body>
</html> 