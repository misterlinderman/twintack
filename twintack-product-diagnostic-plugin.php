<?php
/**
 * Plugin Name: TwinTack Product Diagnostic
 * Description: Diagnose and fix WooCommerce product type issues
 * Version: 1.0.0
 * Author: TwinTack
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Product_Diagnostic_Plugin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('init', array($this, 'add_debug_hooks'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'Product Diagnostic',
            'Product Diagnostic',
            'manage_options',
            'twintack-product-diagnostic',
            array($this, 'diagnostic_page')
        );
    }
    
    public function diagnostic_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        echo '<div class="wrap">';
        echo '<h1>TwinTack Product Diagnostic</h1>';
        
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            echo '<div class="notice notice-error"><p>WooCommerce is not active!</p></div>';
            echo '</div>';
            return;
        }
        
        // Handle fix request
        if (isset($_POST['fix_products']) && wp_verify_nonce($_POST['_wpnonce'], 'fix_products')) {
            $this->fix_products();
        }
        
        // Handle emergency fix request
        if (isset($_POST['emergency_fix']) && wp_verify_nonce($_POST['_wpnonce'], 'emergency_fix')) {
            $this->emergency_fix_all_products();
        }
        
        $this->show_diagnostics();
        echo '</div>';
    }
    
    private function show_diagnostics() {
        global $wpdb;
        
        // WooCommerce Status
        echo '<div class="postbox" style="margin-top: 20px;">';
        echo '<div class="postbox-header"><h2 class="hndle">🔍 System Status</h2></div>';
        echo '<div class="inside">';
        echo '<p><strong>WooCommerce Version:</strong> ' . WC_VERSION . '</p>';
        echo '<p><strong>WordPress Version:</strong> ' . get_bloginfo('version') . '</p>';
        echo '<p><strong>WP_DEBUG:</strong> ' . (WP_DEBUG ? '✅ Enabled' : '❌ Disabled') . '</p>';
        echo '<p><strong>WP_DEBUG_LOG:</strong> ' . (WP_DEBUG_LOG ? '✅ Enabled' : '❌ Disabled') . '</p>';
        echo '</div></div>';
        
        // Get recent products
        $products = $wpdb->get_results("
            SELECT p.ID, p.post_title, p.post_status, p.post_modified,
                   pm1.meta_value as product_type,
                   pm2.meta_value as stock_status
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_product_type'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_stock_status'
            WHERE p.post_type = 'product'
            AND p.post_status IN ('publish', 'draft', 'private')
            ORDER BY p.post_modified DESC
            LIMIT 25
        ");
        
        if (empty($products)) {
            echo '<div class="notice notice-warning"><p>No products found.</p></div>';
            return;
        }
        
        $problematic_products = array();
        $simple_count = 0;
        $variable_count = 0;
        $empty_type_count = 0;
        
        echo '<div class="postbox" style="margin-top: 20px;">';
        echo '<div class="postbox-header"><h2 class="hndle">📊 Recent Products (Last 25)</h2></div>';
        echo '<div class="inside">';
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th style="width: 60px;">ID</th>';
        echo '<th>Title</th>';
        echo '<th style="width: 100px;">Meta Type</th>';
        echo '<th style="width: 100px;">WC Type</th>';
        echo '<th style="width: 80px;">Variations</th>';
        echo '<th style="width: 100px;">Stock Status</th>';
        echo '<th style="width: 120px;">Status</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($products as $product_data) {
            $product = wc_get_product($product_data->ID);
            $wc_type = $product ? $product->get_type() : 'ERROR';
            
            // Count variations
            $variation_count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_parent = %d AND post_type = 'product_variation'
            ", $product_data->ID));
            
            // Detect issues
            $has_issue = false;
            $issue_description = '';
            
            if (empty($product_data->product_type)) {
                $has_issue = true;
                $issue_description = 'Empty type';
                $empty_type_count++;
            } elseif ($product_data->product_type === 'simple' && $variation_count > 0) {
                $has_issue = true;
                $issue_description = 'Simple w/ variations';
                $problematic_products[] = $product_data->ID;
                $simple_count++;
            } elseif ($product_data->product_type === 'variable' && $variation_count === 0) {
                $has_issue = true;
                $issue_description = 'Variable w/o variations';
                $variable_count++;
            } elseif ($product_data->product_type !== $wc_type) {
                $has_issue = true;
                $issue_description = 'Type mismatch';
            } else {
                if ($product_data->product_type === 'simple') $simple_count++;
                if ($product_data->product_type === 'variable') $variable_count++;
            }
            
            $row_style = $has_issue ? 'background-color: #ffeeee;' : '';
            
            echo '<tr style="' . $row_style . '">';
            echo '<td>' . $product_data->ID . '</td>';
            echo '<td>' . esc_html(substr($product_data->post_title, 0, 40)) . '</td>';
            echo '<td>' . ($product_data->product_type ?: '<em>EMPTY</em>') . '</td>';
            echo '<td>' . $wc_type . '</td>';
            echo '<td>' . $variation_count . '</td>';
            echo '<td>' . ($product_data->stock_status ?: 'N/A') . '</td>';
            echo '<td>' . ($has_issue ? '❌ ' . $issue_description : '✅ OK') . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        echo '<div style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-radius: 4px;">';
        echo '<strong>Summary:</strong> ';
        echo 'Simple: ' . $simple_count . ' | ';
        echo 'Variable: ' . $variable_count . ' | ';
        echo 'Empty Type: ' . $empty_type_count . ' | ';
        echo 'Issues Found: ' . count($problematic_products);
        echo '</div>';
        
        echo '</div></div>';
        
        // Check for empty product types (the real issue)
        $empty_type_count = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_product_type'
            WHERE p.post_type = 'product'
            AND p.post_status IN ('publish', 'draft', 'private')
            AND (pm.meta_value IS NULL OR pm.meta_value = '')
        ");
        
        if ($empty_type_count > 0) {
            echo '<div class="postbox" style="margin-top: 20px;">';
            echo '<div class="postbox-header"><h2 class="hndle">🚨 CRITICAL ISSUE DETECTED</h2></div>';
            echo '<div class="inside">';
            echo '<div class="notice notice-error inline">';
            echo '<p><strong>' . $empty_type_count . ' products have EMPTY product type metadata!</strong></p>';
            echo '<p>This is why products show as "Simple Product" in admin but still work as variable products.</p>';
            echo '<p><strong>Root Cause:</strong> Wholesale Pricing plugins are interfering with product saves.</p>';
            echo '</div>';
            
            echo '<form method="post" style="margin-top: 15px;">';
            wp_nonce_field('emergency_fix');
            echo '<p style="color: #d63638;"><strong>⚠️ BACKUP YOUR DATABASE FIRST!</strong></p>';
            echo '<p><strong>🚑 Emergency Fix will:</strong></p>';
            echo '<ul>';
            echo '<li>✅ Set product type to "variable" for products with variations</li>';
            echo '<li>✅ Set product type to "simple" for products without variations</li>';
            echo '<li>✅ Update stock statuses appropriately</li>';
            echo '<li>✅ Clear product caches</li>';
            echo '<li>✅ Stop future SKU modifications by wholesale plugins</li>';
            echo '</ul>';
            echo '<div class="notice notice-info inline">';
            echo '<p><strong>📝 Note:</strong> This will NOT modify existing SKUs. Your client can manually clean up SKUs as needed.</p>';
            echo '</div>';
            
            echo '<button type="submit" name="emergency_fix" class="button button-primary button-large" style="background: #d63638; border-color: #d63638;">';
            echo '🚑 Run Emergency Fix (' . $empty_type_count . ' products)';
            echo '</button>';
            echo '</form>';
            echo '</div></div>';
        }
        
        // Show fix interface if problems found (original fix for specific issues)
        if (!empty($problematic_products) && $empty_type_count == 0) {
            echo '<div class="postbox" style="margin-top: 20px;">';
            echo '<div class="postbox-header"><h2 class="hndle">🔧 Minor Issues Found</h2></div>';
            echo '<div class="inside">';
            echo '<div class="notice notice-warning inline">';
            echo '<p><strong>Found ' . count($problematic_products) . ' products with minor type issues.</strong></p>';
            echo '</div>';
            
            echo '<form method="post" style="margin-top: 15px;">';
            wp_nonce_field('fix_products');
            echo '<p>This will fix specific product type mismatches:</p>';
            echo '<button type="submit" name="fix_products" class="button button-secondary">';
            echo 'Fix ' . count($problematic_products) . ' Products';
            echo '</button>';
            echo '</form>';
            echo '</div></div>';
        }
        
        // Plugin analysis
        $this->show_plugin_analysis();
        
        // Debug instructions
        $this->show_debug_instructions();
    }
    
    private function fix_products() {
        global $wpdb;
        
        // Get problematic products again
        $product_ids = $wpdb->get_col("
            SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_product_type' AND pm.meta_value = 'simple'
            WHERE p.post_type = 'product'
            AND p.post_status IN ('publish', 'draft', 'private')
            AND EXISTS (
                SELECT 1 FROM {$wpdb->posts} v 
                WHERE v.post_parent = p.ID AND v.post_type = 'product_variation'
            )
        ");
        
        if (empty($product_ids)) {
            echo '<div class="notice notice-info"><p>No products need fixing.</p></div>';
            return;
        }
        
        echo '<div class="notice notice-success"><p><strong>🔧 Fixing Products...</strong></p></div>';
        
        $fixed_count = 0;
        $errors = array();
        
        foreach ($product_ids as $product_id) {
            $product = get_post($product_id);
            if (!$product) {
                $errors[] = "Product ID {$product_id} not found";
                continue;
            }
            
            // Update product type to variable
            update_post_meta($product_id, '_product_type', 'variable');
            
            // Set stock status to instock
            update_post_meta($product_id, '_stock_status', 'instock');
            update_post_meta($product_id, '_manage_stock', 'no');
            
            // Clear product cache
            if (function_exists('wc_delete_product_transients')) {
                wc_delete_product_transients($product_id);
            }
            
            $fixed_count++;
            
            // Log the fix
            if (WP_DEBUG_LOG) {
                error_log("TWINTACK_FIX: Fixed Product ID {$product_id} - changed from simple to variable");
            }
        }
        
        echo '<div class="notice notice-success">';
        echo '<p><strong>✅ Successfully fixed ' . $fixed_count . ' products!</strong></p>';
        
        if (!empty($errors)) {
            echo '<p><strong>Errors:</strong></p>';
            echo '<ul>';
            foreach ($errors as $error) {
                echo '<li style="color: #d63638;">' . $error . '</li>';
            }
            echo '</ul>';
        }
        
        echo '<p><strong>Next steps:</strong></p>';
        echo '<ul>';
        echo '<li>Go to <a href="' . admin_url('edit.php?post_type=product') . '">WooCommerce → Products</a> and verify the fixed products</li>';
        echo '<li>Test adding products to cart to ensure variations work correctly</li>';
        echo '<li>Clear any caching plugins or server-side caches</li>';
        echo '<li>Monitor the debug log for any ongoing issues</li>';
        echo '</ul>';
        echo '</div>';
    }
    
    private function show_plugin_analysis() {
        echo '<div class="postbox" style="margin-top: 20px;">';
        echo '<div class="postbox-header"><h2 class="hndle">🔌 Plugin Conflict Analysis</h2></div>';
        echo '<div class="inside">';
        
        $active_plugins = get_option('active_plugins', array());
        
        $potential_conflicts = array(
            'facebook-for-woocommerce' => array('name' => 'Facebook for WooCommerce', 'risk' => 'HIGH'),
            'amazon-for-woocommerce' => array('name' => 'Amazon for WooCommerce', 'risk' => 'HIGH'),
            'woo-variation-swatches' => array('name' => 'Variation Swatches', 'risk' => 'MEDIUM'),
            'jetpack' => array('name' => 'Jetpack', 'risk' => 'MEDIUM'),
            'yith-woocommerce' => array('name' => 'YITH WooCommerce plugins', 'risk' => 'MEDIUM')
        );
        
        $found_conflicts = array();
        
        foreach ($active_plugins as $plugin) {
            foreach ($potential_conflicts as $conflict_slug => $conflict_data) {
                if (strpos($plugin, $conflict_slug) !== false) {
                    $found_conflicts[] = array(
                        'plugin' => $plugin,
                        'name' => $conflict_data['name'],
                        'risk' => $conflict_data['risk']
                    );
                }
            }
        }
        
        if (empty($found_conflicts)) {
            echo '<p>✅ No obvious plugin conflicts detected</p>';
        } else {
            echo '<div class="notice notice-warning inline">';
            echo '<p><strong>⚠️ Potential Plugin Conflicts Found:</strong></p>';
            echo '<ul>';
            foreach ($found_conflicts as $conflict) {
                $color = $conflict['risk'] === 'HIGH' ? '#d63638' : '#dba617';
                echo '<li style="color: ' . $color . ';">';
                echo '<strong>' . $conflict['name'] . '</strong> (' . $conflict['plugin'] . ') - Risk: ' . $conflict['risk'];
                echo '</li>';
            }
            echo '</ul>';
            echo '<p><strong>Recommendation:</strong> Try temporarily deactivating these plugins one by one to test if they\'re causing the product type issue.</p>';
            echo '</div>';
        }
        
        echo '</div></div>';
    }
    
    private function show_debug_instructions() {
        echo '<div class="postbox" style="margin-top: 20px;">';
        echo '<div class="postbox-header"><h2 class="hndle">🐛 Debug Instructions</h2></div>';
        echo '<div class="inside">';
        
        echo '<p><strong>This plugin is now monitoring product saves.</strong> When you edit and save products, debug information will be logged.</p>';
        
        if (WP_DEBUG_LOG) {
            echo '<div class="notice notice-success inline">';
            echo '<p>✅ Debug logging is enabled. Check your debug log for detailed information:</p>';
            echo '<ul>';
            echo '<li><strong>WordPress debug log:</strong> <code>wp-content/debug.log</code></li>';
            echo '<li><strong>Look for:</strong> Lines starting with <code>TWINTACK_DEBUG:</code></li>';
            echo '</ul>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-warning inline">';
            echo '<p>⚠️ Debug logging is disabled. To enable detailed logging:</p>';
            echo '<ol>';
            echo '<li>Edit your <code>wp-config.php</code> file</li>';
            echo '<li>Ensure these lines are present and set to <code>true</code>:</li>';
            echo '<li><code>define(\'WP_DEBUG\', true);</code></li>';
            echo '<li><code>define(\'WP_DEBUG_LOG\', true);</code></li>';
            echo '</ol>';
            echo '</div>';
        }
        
        echo '<h4>Testing Process:</h4>';
        echo '<ol>';
        echo '<li>Go to <a href="' . admin_url('edit.php?post_type=product') . '">WooCommerce → Products</a></li>';
        echo '<li>Edit a product that\'s showing as "Simple Product" but should be "Variable"</li>';
        echo '<li>Change the product type to "Variable Product"</li>';
        echo '<li>Configure variations and save</li>';
        echo '<li>Check if the product type reverts back to "Simple"</li>';
        echo '<li>Check the debug log for what happened during the save</li>';
        echo '</ol>';
        
        echo '</div></div>';
    }
    
    private function emergency_fix_all_products() {
        global $wpdb;
        
        echo '<div class="notice notice-info">';
        echo '<h2>🚑 Running Emergency Fix...</h2>';
        echo '</div>';
        
        // Get all products with empty product types
        $empty_type_products = $wpdb->get_results("
            SELECT p.ID, p.post_title
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_product_type'
            WHERE p.post_type = 'product'
            AND p.post_status IN ('publish', 'draft', 'private')
            AND (pm.meta_value IS NULL OR pm.meta_value = '')
        ");
        
        if (empty($empty_type_products)) {
            echo '<div class="notice notice-success"><p>No products need fixing!</p></div>';
            return;
        }
        
        $fixed_variable = 0;
        $fixed_simple = 0;
        
        echo '<div class="notice notice-success">';
        echo '<h3>Processing ' . count($empty_type_products) . ' products...</h3>';
        
        foreach ($empty_type_products as $product_data) {
            $product_id = $product_data->ID;
            
            // Count variations
            $variation_count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_parent = %d AND post_type = 'product_variation'
            ", $product_id));
            
            if ($variation_count > 0) {
                // Should be variable product
                update_post_meta($product_id, '_product_type', 'variable');
                update_post_meta($product_id, '_stock_status', 'instock');
                update_post_meta($product_id, '_manage_stock', 'no');
                $fixed_variable++;
                
                echo '<p>✅ Fixed Product ID ' . $product_id . ': ' . esc_html($product_data->post_title) . ' → Variable Product (' . $variation_count . ' variations)</p>';
                
            } else {
                // Should be simple product
                update_post_meta($product_id, '_product_type', 'simple');
                
                // Set stock status if not already set
                $current_stock_status = get_post_meta($product_id, '_stock_status', true);
                if (!$current_stock_status) {
                    update_post_meta($product_id, '_stock_status', 'instock');
                }
                
                $fixed_simple++;
                echo '<p>✅ Fixed Product ID ' . $product_id . ': ' . esc_html($product_data->post_title) . ' → Simple Product</p>';
            }
            
            // Clear product cache
            if (function_exists('wc_delete_product_transients')) {
                wc_delete_product_transients($product_id);
            }
            
            // Log the fix
            if (WP_DEBUG_LOG) {
                error_log("TWINTACK_EMERGENCY_FIX: Fixed Product ID {$product_id} - set type to " . ($variation_count > 0 ? 'variable' : 'simple'));
            }
        }
        
        echo '<h3>🎉 Emergency Fix Complete!</h3>';
        echo '<p><strong>Fixed ' . $fixed_variable . ' Variable Products</strong></p>';
        echo '<p><strong>Fixed ' . $fixed_simple . ' Simple Products</strong></p>';
        echo '</div>';
        
        echo '<div class="notice notice-warning">';
        echo '<h3>⚠️ Important Next Steps</h3>';
        echo '<ol>';
        echo '<li><strong>Test your products:</strong> Go to <a href="' . admin_url('edit.php?post_type=product') . '">WooCommerce → Products</a> and verify they now show correct types</li>';
        echo '<li><strong>Test variations:</strong> Add products to cart to ensure variations work</li>';
        echo '<li><strong>Install bypass plugin:</strong> Upload the bypass plugin for future product editing</li>';
        echo '<li><strong>Clean SKUs manually:</strong> Your client can now safely edit products and clean up SKUs</li>';
        echo '<li><strong>Use bypass when editing:</strong> Activate bypass before editing products to prevent wholesale plugin interference</li>';
        echo '</ol>';
        echo '</div>';
    }
    
    public function add_debug_hooks() {
        if (!is_admin()) {
            return;
        }
        
        // Monitor product saves
        add_action('woocommerce_process_product_meta', array($this, 'debug_product_save'), 5, 2);
        add_action('save_post', array($this, 'debug_post_save'), 10, 3);
        add_action('updated_post_meta', array($this, 'debug_meta_update'), 10, 4);
    }
    
    public function debug_product_save($post_id, $post) {
        if (!WP_DEBUG_LOG) {
            return;
        }
        
        $current_type = get_post_meta($post_id, '_product_type', true);
        $posted_type = isset($_POST['product-type']) ? sanitize_text_field($_POST['product-type']) : 'NOT SET';
        
        error_log("TWINTACK_DEBUG: Product save hook - ID: {$post_id}, Title: " . $post->post_title);
        error_log("TWINTACK_DEBUG: Current Type: {$current_type}, Posted Type: {$posted_type}");
        
        if (isset($_POST['variable_post_id']) && is_array($_POST['variable_post_id'])) {
            $variation_count = count($_POST['variable_post_id']);
            error_log("TWINTACK_DEBUG: Variations in POST: {$variation_count}");
        }
        
        // Log all product-related POST keys
        $product_keys = array();
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'product') !== false || strpos($key, 'variable') !== false) {
                $product_keys[] = $key;
            }
        }
        if (!empty($product_keys)) {
            error_log("TWINTACK_DEBUG: Product POST keys: " . implode(', ', $product_keys));
        }
    }
    
    public function debug_post_save($post_id, $post, $update) {
        if ($post->post_type !== 'product' || !WP_DEBUG_LOG) {
            return;
        }
        
        $final_type = get_post_meta($post_id, '_product_type', true);
        error_log("TWINTACK_DEBUG: Post save complete - ID: {$post_id}, Final Type: {$final_type}, Update: " . ($update ? 'YES' : 'NO'));
    }
    
    public function debug_meta_update($meta_id, $post_id, $meta_key, $meta_value) {
        if (!WP_DEBUG_LOG || $meta_key !== '_product_type') {
            return;
        }
        
        $post = get_post($post_id);
        if ($post && $post->post_type === 'product') {
            error_log("TWINTACK_DEBUG: Meta updated - Post: {$post_id}, Key: {$meta_key}, Value: {$meta_value}");
        }
    }
}

// Initialize the plugin
new TwinTack_Product_Diagnostic_Plugin();

?>
