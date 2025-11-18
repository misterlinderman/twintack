<?php
/**
 * Class Diagnostic Script
 * 
 * Upload this to your plugins/twintack-manual-order-payments/ directory
 * Access via: yourdomain.com/wp-content/plugins/twintack-manual-order-payments/class-diagnostic.php
 * 
 * This will check if there's a mismatch between what's on disk vs what's loaded in memory.
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
    <title>Class Diagnostic - TwinTack</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        .step { margin: 15px 0; padding: 15px; border-left: 4px solid #0073aa; background: #f9f9f9; }
        pre { background: #f1f1f1; padding: 15px; border-radius: 3px; font-size: 11px; overflow-x: auto; max-height: 300px; overflow-y: auto; }
        button { background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; margin: 5px; }
        .highlight { background: yellow; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Class Diagnostic - TwinTack</h1>
        <p><em>Generated: <?php echo date('Y-m-d H:i:s'); ?></em></p>

        <div class="step">
            <h3>📄 File Content Analysis</h3>
            <?php
            $main_file = __DIR__ . '/twintack-manual-order-payments.php';
            
            if (file_exists($main_file)) {
                $file_content = file_get_contents($main_file);
                $file_size = filesize($main_file);
                $file_date = date('Y-m-d H:i:s', filemtime($main_file));
                
                echo '<div class="success">✅ Main file exists: ' . round($file_size/1024, 1) . 'KB, modified: ' . $file_date . '</div>';
                
                // Check for specific method in file content
                if (strpos($file_content, 'load_admin_functionality') !== false) {
                    echo '<div class="success">✅ load_admin_functionality method found in file content</div>';
                    
                    // Count occurrences
                    $occurrences = substr_count($file_content, 'load_admin_functionality');
                    echo '<div class="info">📊 Found ' . $occurrences . ' occurrences of "load_admin_functionality"</div>';
                    
                    // Show the method definition
                    if (preg_match('/private function load_admin_functionality\(\).*?^\s*}/ms', $file_content, $matches)) {
                        echo '<div class="info">🔍 Method definition found:</div>';
                        echo '<pre>' . htmlspecialchars(substr($matches[0], 0, 500)) . '...</pre>';
                    }
                    
                } else {
                    echo '<div class="error">❌ load_admin_functionality method NOT found in file content</div>';
                }
                
                // Check for specific includes
                $expected_includes = array(
                    'class-order-status-manager.php',
                    'class-debug-tools.php',
                    'class-shippo-integration.php',
                    'class-invoice-payment-gateway.php'
                );
                
                echo '<div class="info">🔍 Checking for new class includes:</div>';
                foreach ($expected_includes as $include) {
                    if (strpos($file_content, $include) !== false) {
                        echo '<div class="success">✅ ' . $include . ' - found in file</div>';
                    } else {
                        echo '<div class="error">❌ ' . $include . ' - NOT found in file</div>';
                    }
                }
                
            } else {
                echo '<div class="error">❌ Main plugin file not found</div>';
            }
            ?>
        </div>

        <div class="step">
            <h3>🧠 Loaded Class Analysis</h3>
            <?php
            if (class_exists('TwinTack_Manual_Order_Payments')) {
                echo '<div class="success">✅ TwinTack_Manual_Order_Payments class is loaded</div>';
                
                // Get reflection of the class
                $reflection = new ReflectionClass('TwinTack_Manual_Order_Payments');
                
                echo '<div class="info">📄 Class file: ' . $reflection->getFileName() . '</div>';
                echo '<div class="info">📊 Total methods: ' . count($reflection->getMethods()) . '</div>';
                
                // List all methods
                echo '<div class="info">📋 All methods in loaded class:</div>';
                echo '<pre>';
                foreach ($reflection->getMethods() as $method) {
                    $visibility = '';
                    if ($method->isPrivate()) $visibility = 'private';
                    elseif ($method->isProtected()) $visibility = 'protected';  
                    else $visibility = 'public';
                    
                    echo $visibility . ' ' . $method->getName();
                    if ($method->getName() === 'load_admin_functionality') {
                        echo ' ← 🎯 THIS IS THE METHOD WE\'RE LOOKING FOR!';
                    }
                    echo "\n";
                }
                echo '</pre>';
                
                // Check specifically for load_admin_functionality
                if ($reflection->hasMethod('load_admin_functionality')) {
                    echo '<div class="success">🎉 load_admin_functionality method EXISTS in loaded class!</div>';
                    
                    $method = $reflection->getMethod('load_admin_functionality');
                    echo '<div class="info">👁️ Method visibility: ' . ($method->isPrivate() ? 'private' : ($method->isProtected() ? 'protected' : 'public')) . '</div>';
                    echo '<div class="info">📍 Method defined in: ' . $method->getFileName() . ' (line ' . $method->getStartLine() . ')</div>';
                    
                } else {
                    echo '<div class="error">❌ load_admin_functionality method NOT FOUND in loaded class</div>';
                }
                
            } else {
                echo '<div class="error">❌ TwinTack_Manual_Order_Payments class not loaded</div>';
            }
            ?>
        </div>

        <div class="step">
            <h3>🔄 Force Class Reload Test</h3>
            <?php if (isset($_GET['force_reload'])): ?>
                <div class="info">🚀 Attempting to force reload the main plugin class...</div>
                
                <?php
                // Clear any existing class definition (this won't work in PHP, but we can try)
                echo '<div class="warning">⚠️ Note: PHP doesn\'t allow redefining classes, but we can test fresh loading</div>';
                
                // Try to include the file again
                $main_file = __DIR__ . '/twintack-manual-order-payments.php';
                if (file_exists($main_file)) {
                    echo '<div class="info">🔄 Re-including main plugin file...</div>';
                    
                    // This won't redefine the class, but we can check what happens
                    include_once $main_file;
                    
                    // Check class again
                    if (class_exists('TwinTack_Manual_Order_Payments')) {
                        $reflection = new ReflectionClass('TwinTack_Manual_Order_Payments');
                        
                        if ($reflection->hasMethod('load_admin_functionality')) {
                            echo '<div class="success">✅ After re-include: load_admin_functionality method found</div>';
                        } else {
                            echo '<div class="error">❌ After re-include: load_admin_functionality method still missing</div>';
                        }
                    }
                }
                ?>
                
            <?php else: ?>
                <button onclick="location.href='?force_reload=1'">🔄 Force Reload Class</button>
            <?php endif; ?>
        </div>

        <div class="step">
            <h3>🎯 Manual Method Call Test</h3>
            <?php if (isset($_GET['test_method'])): ?>
                <div class="info">🧪 Testing manual method call...</div>
                
                <?php
                if (class_exists('TwinTack_Manual_Order_Payments')) {
                    try {
                        $instance = TwinTack_Manual_Order_Payments::get_instance();
                        
                        // Try to call the method using reflection (to access private method)
                        $reflection = new ReflectionClass($instance);
                        
                        if ($reflection->hasMethod('load_admin_functionality')) {
                            $method = $reflection->getMethod('load_admin_functionality');
                            $method->setAccessible(true); // Make private method accessible
                            
                            echo '<div class="info">🔧 Calling load_admin_functionality via reflection...</div>';
                            $method->invoke($instance);
                            echo '<div class="success">✅ Method called successfully!</div>';
                            
                            // Now check if classes are loaded
                            $classes_to_check = array(
                                'TwinTack_Order_Status_Manager',
                                'TwinTack_Debug_Tools',
                                'TwinTack_Shippo_Integration',
                                'TwinTack_Invoice_Payment_Gateway',
                                'TwinTack_Admin_Order_Enhancements'
                            );
                            
                            echo '<div class="info">📋 Checking if classes loaded after method call:</div>';
                            foreach ($classes_to_check as $class) {
                                if (class_exists($class)) {
                                    echo '<div class="success">✅ ' . $class . '</div>';
                                } else {
                                    echo '<div class="error">❌ ' . $class . '</div>';
                                }
                            }
                            
                        } else {
                            echo '<div class="error">❌ load_admin_functionality method not found for manual call</div>';
                        }
                        
                    } catch (Exception $e) {
                        echo '<div class="error">❌ Error during manual method call: ' . $e->getMessage() . '</div>';
                    }
                } else {
                    echo '<div class="error">❌ Main plugin class not available</div>';
                }
                ?>
                
            <?php else: ?>
                <button onclick="location.href='?test_method=1'">🧪 Test Manual Method Call</button>
            <?php endif; ?>
        </div>

        <div class="step">
            <h3>💡 Diagnosis Summary</h3>
            <div class="info">
                <p><strong>This diagnostic will tell us:</strong></p>
                <ul>
                    <li>If the file on disk has the correct content</li>
                    <li>If the loaded class matches what's on disk</li>
                    <li>If there's a caching or loading issue</li>
                    <li>If we can manually trigger the functionality</li>
                </ul>
                
                <p><strong>Possible outcomes:</strong></p>
                <ul>
                    <li><strong>File missing method:</strong> Need to re-upload correct file</li>
                    <li><strong>Class cached:</strong> Need to clear cache or restart</li>
                    <li><strong>Method exists but not working:</strong> Need to debug method execution</li>
                </ul>
            </div>
        </div>

    </div>
</body>
</html> 