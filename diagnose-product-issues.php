<?php
/**
 * TwinTack Product Issue Diagnostic Script
 * 
 * Quick diagnostic to identify patterns in product type issues
 * 
 * @package TwinTack
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('wp-config.php');
}

class TwinTack_Product_Diagnostic {
    
    public function run_diagnostics() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        echo '<div style="font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px;">';
        echo '<h1>TwinTack Product Issue Diagnostics</h1>';
        
        $this->check_woocommerce_status();
        $this->analyze_products();
        $this->check_plugin_conflicts();
        $this->check_database_integrity();
        $this->provide_recommendations();
        
        echo '</div>';
    }
    
    private function check_woocommerce_status() {
        echo '<h2>🔍 WooCommerce Status</h2>';
        echo '<div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
        
        if (!class_exists('WooCommerce')) {
            echo '<p style="color: red;">❌ WooCommerce is not active</p>';
            return;
        }
        
        echo '<p>✅ WooCommerce Version: ' . WC_VERSION . '</p>';
        echo '<p>✅ WordPress Version: ' . get_bloginfo('version') . '</p>';
        
        // Check HPOS status
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            $hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            echo '<p>' . ($hpos_enabled ? '✅' : '⚠️') . ' HPOS: ' . ($hpos_enabled ? 'Enabled' : 'Disabled') . '</p>';
        }
        
        // Check debug status
        echo '<p>' . (WP_DEBUG ? '✅' : '⚠️') . ' WP_DEBUG: ' . (WP_DEBUG ? 'Enabled' : 'Disabled') . '</p>';
        echo '<p>' . (WP_DEBUG_LOG ? '✅' : '⚠️') . ' WP_DEBUG_LOG: ' . (WP_DEBUG_LOG ? 'Enabled' : 'Disabled') . '</p>';
        
        echo '</div>';
    }
    
    private function analyze_products() {
        echo '<h2>📊 Product Analysis</h2>';
        
        global $wpdb;
        
        // Get all products
        $products = $wpdb->get_results("
            SELECT p.ID, p.post_title, p.post_status, p.post_modified,
                   pm1.meta_value as product_type,
                   pm2.meta_value as stock_status,
                   pm3.meta_value as manage_stock
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_product_type'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_stock_status'
            LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_manage_stock'
            WHERE p.post_type = 'product'
            AND p.post_status IN ('publish', 'draft', 'private')
            ORDER BY p.post_modified DESC
            LIMIT 50
        ");
        
        if (empty($products)) {
            echo '<p>No products found</p>';
            return;
        }
        
        echo '<div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
        echo '<h3>Recent Products (Last 50)</h3>';
        
        $simple_count = 0;
        $variable_count = 0;
        $empty_type_count = 0;
        $problematic_products = array();
        
        echo '<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
        echo '<tr style="background: #ddd;">';
        echo '<th style="padding: 8px; border: 1px solid #ccc;">ID</th>';
        echo '<th style="padding: 8px; border: 1px solid #ccc;">Title</th>';
        echo '<th style="padding: 8px; border: 1px solid #ccc;">Type (Meta)</th>';
        echo '<th style="padding: 8px; border: 1px solid #ccc;">Type (WC)</th>';
        echo '<th style="padding: 8px; border: 1px solid #ccc;">Stock Status</th>';
        echo '<th style="padding: 8px; border: 1px solid #ccc;">Variations</th>';
        echo '<th style="padding: 8px; border: 1px solid #ccc;">Last Modified</th>';
        echo '<th style="padding: 8px; border: 1px solid #ccc;">Status</th>';
        echo '</tr>';
        
        foreach ($products as $product_data) {
            $product = wc_get_product($product_data->ID);
            $wc_type = $product ? $product->get_type() : 'ERROR';
            
            // Count variations for variable products
            $variation_count = 0;
            if ($product && $product->is_type('variable')) {
                $variation_count = count($product->get_children());
            }
            
            // Detect issues
            $has_issue = false;
            $issue_type = '';
            
            if (empty($product_data->product_type)) {
                $empty_type_count++;
                $has_issue = true;
                $issue_type = 'Empty Type';
            } elseif ($product_data->product_type === 'simple' && $variation_count > 0) {
                $has_issue = true;
                $issue_type = 'Type Mismatch';
            } elseif ($product_data->product_type !== $wc_type) {
                $has_issue = true;
                $issue_type = 'WC Mismatch';
            }
            
            if ($has_issue) {
                $problematic_products[] = array(
                    'id' => $product_data->ID,
                    'title' => $product_data->post_title,
                    'issue' => $issue_type
                );
            }
            
            // Count types
            if ($product_data->product_type === 'simple') {
                $simple_count++;
            } elseif ($product_data->product_type === 'variable') {
                $variable_count++;
            }
            
            $row_style = $has_issue ? 'background: #ffeeee;' : '';
            echo '<tr style="' . $row_style . '">';
            echo '<td style="padding: 8px; border: 1px solid #ccc;">' . $product_data->ID . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ccc;">' . esc_html(substr($product_data->post_title, 0, 30)) . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ccc;">' . ($product_data->product_type ?: 'EMPTY') . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ccc;">' . $wc_type . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ccc;">' . ($product_data->stock_status ?: 'N/A') . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ccc;">' . $variation_count . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ccc;">' . date('Y-m-d H:i', strtotime($product_data->post_modified)) . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ccc;">' . ($has_issue ? '❌ ' . $issue_type : '✅ OK') . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        
        echo '<div style="margin-top: 15px;">';
        echo '<p><strong>Summary:</strong></p>';
        echo '<ul>';
        echo '<li>Simple Products: ' . $simple_count . '</li>';
        echo '<li>Variable Products: ' . $variable_count . '</li>';
        echo '<li>Empty Product Types: ' . $empty_type_count . '</li>';
        echo '<li>Problematic Products: ' . count($problematic_products) . '</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '</div>';
        
        if (!empty($problematic_products)) {
            echo '<div style="background: #ffeeee; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #ff0000;">';
            echo '<h3>🚨 Problematic Products Found</h3>';
            foreach ($problematic_products as $problem) {
                echo '<p>• Product ID ' . $problem['id'] . ': ' . esc_html($problem['title']) . ' - ' . $problem['issue'] . '</p>';
            }
            echo '</div>';
        }
    }
    
    private function check_plugin_conflicts() {
        echo '<h2>🔌 Plugin Conflict Analysis</h2>';
        echo '<div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
        
        $active_plugins = get_option('active_plugins', array());
        
        $potential_conflicts = array(
            'facebook-for-woocommerce' => 'Facebook for WooCommerce',
            'amazon-for-woocommerce' => 'Amazon for WooCommerce',
            'woo-variation-swatches' => 'Variation Swatches',
            'product-import-export' => 'Product Import/Export',
            'woocommerce-product-csv-import-suite' => 'Product CSV Import',
            'yith-woocommerce' => 'YITH WooCommerce plugins',
            'jetpack' => 'Jetpack (WooCommerce features)',
        );
        
        $found_conflicts = array();
        
        foreach ($active_plugins as $plugin) {
            foreach ($potential_conflicts as $conflict_slug => $conflict_name) {
                if (strpos($plugin, $conflict_slug) !== false) {
                    $found_conflicts[] = array(
                        'plugin' => $plugin,
                        'name' => $conflict_name,
                        'risk' => 'HIGH'
                    );
                }
            }
        }
        
        if (empty($found_conflicts)) {
            echo '<p>✅ No obvious plugin conflicts detected</p>';
        } else {
            echo '<h3>⚠️ Potential Plugin Conflicts:</h3>';
            foreach ($found_conflicts as $conflict) {
                echo '<p style="color: orange;">• ' . $conflict['name'] . ' (' . $conflict['plugin'] . ') - Risk: ' . $conflict['risk'] . '</p>';
            }
        }
        
        // Check for hooks that might interfere
        global $wp_filter;
        $critical_hooks = array(
            'woocommerce_process_product_meta',
            'save_post',
            'wp_insert_post'
        );
        
        echo '<h3>Hook Analysis:</h3>';
        foreach ($critical_hooks as $hook) {
            if (isset($wp_filter[$hook])) {
                $callback_count = 0;
                foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                    $callback_count += count($callbacks);
                }
                echo '<p>• ' . $hook . ': ' . $callback_count . ' callbacks</p>';
            }
        }
        
        echo '</div>';
    }
    
    private function check_database_integrity() {
        echo '<h2>🗄️ Database Integrity Check</h2>';
        echo '<div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
        
        global $wpdb;
        
        // Check for orphaned product meta
        $orphaned_meta = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.ID IS NULL
            AND pm.meta_key LIKE '_product%'
        ");
        
        echo '<p>Orphaned product meta entries: ' . $orphaned_meta . '</p>';
        
        // Check for products without product type
        $no_type = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_product_type'
            WHERE p.post_type = 'product'
            AND p.post_status IN ('publish', 'draft', 'private')
            AND pm.meta_value IS NULL
        ");
        
        echo '<p>Products without _product_type meta: ' . $no_type . '</p>';
        
        // Check for variable products without variations
        $variable_no_variations = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_product_type' AND pm.meta_value = 'variable'
            LEFT JOIN {$wpdb->posts} v ON p.ID = v.post_parent AND v.post_type = 'product_variation'
            WHERE p.post_type = 'product'
            AND p.post_status IN ('publish', 'draft', 'private')
            AND v.ID IS NULL
        ");
        
        echo '<p>Variable products without variations: ' . $variable_no_variations . '</p>';
        
        // Check table structure
        $tables_to_check = array(
            $wpdb->posts,
            $wpdb->postmeta,
            $wpdb->prefix . 'wc_products',
            $wpdb->prefix . 'wc_product_meta_lookup'
        );
        
        echo '<h3>Table Status:</h3>';
        foreach ($tables_to_check as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            echo '<p>• ' . $table . ': ' . ($exists ? '✅ EXISTS' : '❌ MISSING') . '</p>';
        }
        
        echo '</div>';
    }
    
    private function provide_recommendations() {
        echo '<h2>💡 Recommendations</h2>';
        echo '<div style="background: #e8f5e8; padding: 15px; border-radius: 5px; border-left: 4px solid #4CAF50;">';
        
        echo '<h3>Immediate Actions:</h3>';
        echo '<ol>';
        echo '<li><strong>Activate Debug Script:</strong> The debug script has been created. Access it at <code>' . home_url('/debug-product-save-issues.php') . '</code></li>';
        echo '<li><strong>Test Product Save:</strong> Edit a problematic product while the debug script is active to capture the issue</li>';
        echo '<li><strong>Check Plugin Conflicts:</strong> Temporarily deactivate recently added plugins (Facebook, Amazon) to test</li>';
        echo '<li><strong>Backup Database:</strong> Before making any fixes, backup your database</li>';
        echo '</ol>';
        
        echo '<h3>Debug Process:</h3>';
        echo '<ol>';
        echo '<li>Access the debug script: <a href="' . home_url('/debug-product-save-issues.php') . '" target="_blank">Open Debug Script</a></li>';
        echo '<li>Go to WP Admin → Products → Edit a problematic product</li>';
        echo '<li>Change product type to "Variable Product"</li>';
        echo '<li>Add/configure variations</li>';
        echo '<li>Save the product</li>';
        echo '<li>Check the debug output for what happened during save</li>';
        echo '</ol>';
        
        echo '<h3>Quick Fixes to Try:</h3>';
        echo '<ul>';
        echo '<li><strong>Plugin Deactivation Test:</strong> Deactivate Facebook and Amazon WooCommerce plugins temporarily</li>';
        echo '<li><strong>Theme Test:</strong> Switch to a default theme (Twenty Twenty-Four) temporarily</li>';
        echo '<li><strong>Clear Caches:</strong> Clear any caching plugins or server-side caches</li>';
        echo '<li><strong>Check File Permissions:</strong> Ensure wp-content and uploads directories are writable</li>';
        echo '</ul>';
        
        echo '<h3>Advanced Debugging:</h3>';
        echo '<ul>';
        echo '<li>Check <code>wp-content/debug.log</code> for PHP errors during product saves</li>';
        echo '<li>Monitor <code>twintack-product-debug.log</code> for detailed save process information</li>';
        echo '<li>Use browser developer tools to check for JavaScript errors during product saves</li>';
        echo '</ul>';
        
        echo '</div>';
    }
}

// Run diagnostics if accessed directly
if (!empty($_GET['run']) || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'diagnose-product-issues.php') !== false)) {
    $diagnostic = new TwinTack_Product_Diagnostic();
    $diagnostic->run_diagnostics();
} else {
    echo '<h2>TwinTack Product Issue Diagnostics</h2>';
    echo '<p><a href="?run=1">Click here to run diagnostics</a></p>';
}

?>
