<?php
/**
 * TwinTack Product Type Fix Script
 * 
 * This script helps fix products that have been incorrectly saved as "Simple Product"
 * when they should be "Variable Product" based on their variations.
 * 
 * @package TwinTack
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    require_once('wp-config.php');
}

class TwinTack_Product_Type_Fixer {
    
    private $fixed_products = array();
    private $errors = array();
    
    public function run_fix() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        echo '<div style="font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px;">';
        echo '<h1>TwinTack Product Type Fix</h1>';
        
        if (isset($_POST['fix_products']) && wp_verify_nonce($_POST['_wpnonce'], 'fix_product_types')) {
            $this->fix_product_types();
        } else {
            $this->show_fix_interface();
        }
        
        echo '</div>';
    }
    
    private function show_fix_interface() {
        $problematic_products = $this->find_problematic_products();
        
        echo '<div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
        echo '<h2>🔍 Scanning for Product Type Issues</h2>';
        
        if (empty($problematic_products)) {
            echo '<p style="color: green;">✅ No product type issues found!</p>';
            echo '</div>';
            return;
        }
        
        echo '<p>Found ' . count($problematic_products) . ' products with potential issues:</p>';
        
        echo '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">';
        echo '<tr style="background: #ddd;">';
        echo '<th style="padding: 8px; border: 1px solid #ccc;">ID</th>';
        echo '<th style="padding: 8px; border: 1px solid #ccc;">Product Name</th>';
        echo '<th style="padding: 8px; border: 1px solid #ccc;">Current Type</th>';
        echo '<th style="padding: 8px; border: 1px solid #ccc;">Variations Found</th>';
        echo '<th style="padding: 8px; border: 1px solid #ccc;">Suggested Fix</th>';
        echo '<th style="padding: 8px; border: 1px solid #ccc;">Action</th>';
        echo '</tr>';
        
        foreach ($problematic_products as $product) {
            echo '<tr>';
            echo '<td style="padding: 8px; border: 1px solid #ccc;">' . $product['id'] . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ccc;">' . esc_html($product['name']) . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ccc;">' . $product['current_type'] . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ccc;">' . $product['variation_count'] . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ccc;">' . $product['suggested_fix'] . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #ccc;">';
            echo '<input type="checkbox" name="fix_product_ids[]" value="' . $product['id'] . '" checked> Fix';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</div>';
        
        echo '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #ffc107;">';
        echo '<h3>⚠️ Important Notes Before Fixing:</h3>';
        echo '<ul>';
        echo '<li><strong>Backup your database</strong> before running this fix</li>';
        echo '<li>This will change product types and may affect your store\'s behavior</li>';
        echo '<li>Test on a staging site first if possible</li>';
        echo '<li>The fix will also update stock status to "instock" for variable products</li>';
        echo '<li>Variation stock statuses will be preserved</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '<form method="post" style="background: #e8f5e8; padding: 20px; border-radius: 5px;">';
        wp_nonce_field('fix_product_types');
        echo '<h3>Fix Selected Products</h3>';
        echo '<p>Select the products you want to fix above, then click the button below:</p>';
        echo '<button type="submit" name="fix_products" style="background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">Fix Selected Products</button>';
        echo '</form>';
    }
    
    private function find_problematic_products() {
        global $wpdb;
        
        $products = $wpdb->get_results("
            SELECT p.ID, p.post_title,
                   pm.meta_value as product_type
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_product_type'
            WHERE p.post_type = 'product'
            AND p.post_status IN ('publish', 'draft', 'private')
            ORDER BY p.post_modified DESC
        ");
        
        $problematic = array();
        
        foreach ($products as $product_data) {
            $product_id = $product_data->ID;
            $current_type = $product_data->product_type ?: 'empty';
            
            // Count variations
            $variations = get_children(array(
                'post_parent' => $product_id,
                'post_type' => 'product_variation',
                'numberposts' => -1,
                'post_status' => 'any'
            ));
            
            $variation_count = count($variations);
            
            // Check for issues
            $has_issue = false;
            $suggested_fix = '';
            
            if ($current_type === 'simple' && $variation_count > 0) {
                $has_issue = true;
                $suggested_fix = 'Change to Variable Product';
            } elseif ($current_type === 'empty' && $variation_count > 0) {
                $has_issue = true;
                $suggested_fix = 'Set as Variable Product';
            } elseif ($current_type === 'variable' && $variation_count === 0) {
                $has_issue = true;
                $suggested_fix = 'Change to Simple Product';
            }
            
            if ($has_issue) {
                $problematic[] = array(
                    'id' => $product_id,
                    'name' => $product_data->post_title,
                    'current_type' => $current_type,
                    'variation_count' => $variation_count,
                    'suggested_fix' => $suggested_fix
                );
            }
        }
        
        return $problematic;
    }
    
    private function fix_product_types() {
        if (empty($_POST['fix_product_ids']) || !is_array($_POST['fix_product_ids'])) {
            echo '<p style="color: red;">No products selected for fixing.</p>';
            return;
        }
        
        echo '<div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
        echo '<h2>🔧 Fixing Product Types</h2>';
        
        foreach ($_POST['fix_product_ids'] as $product_id) {
            $product_id = intval($product_id);
            $this->fix_single_product($product_id);
        }
        
        echo '</div>';
        
        if (!empty($this->fixed_products)) {
            echo '<div style="background: #e8f5e8; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #4CAF50;">';
            echo '<h3>✅ Successfully Fixed Products:</h3>';
            foreach ($this->fixed_products as $fix) {
                echo '<p>• Product ID ' . $fix['id'] . ': ' . esc_html($fix['name']) . ' - ' . $fix['action'] . '</p>';
            }
            echo '</div>';
        }
        
        if (!empty($this->errors)) {
            echo '<div style="background: #ffeeee; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #ff0000;">';
            echo '<h3>❌ Errors:</h3>';
            foreach ($this->errors as $error) {
                echo '<p>• ' . $error . '</p>';
            }
            echo '</div>';
        }
        
        echo '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
        echo '<h3>📋 Next Steps:</h3>';
        echo '<ul>';
        echo '<li>Check the fixed products in WP Admin to ensure they display correctly</li>';
        echo '<li>Test adding products to cart to ensure variations work</li>';
        echo '<li>Clear any caching plugins or server-side caches</li>';
        echo '<li>Monitor the debug logs for any ongoing issues</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '<p><a href="' . $_SERVER['PHP_SELF'] . '">Run Another Scan</a></p>';
    }
    
    private function fix_single_product($product_id) {
        $product = get_post($product_id);
        if (!$product || $product->post_type !== 'product') {
            $this->errors[] = "Product ID {$product_id} not found or not a product";
            return;
        }
        
        echo '<p>Processing Product ID ' . $product_id . ': ' . esc_html($product->post_title) . '</p>';
        
        // Get current product type
        $current_type = get_post_meta($product_id, '_product_type', true);
        
        // Count variations
        $variations = get_children(array(
            'post_parent' => $product_id,
            'post_type' => 'product_variation',
            'numberposts' => -1,
            'post_status' => 'any'
        ));
        
        $variation_count = count($variations);
        
        echo '<p>  Current type: ' . ($current_type ?: 'EMPTY') . ', Variations: ' . $variation_count . '</p>';
        
        $action_taken = '';
        
        if ($variation_count > 0 && ($current_type !== 'variable')) {
            // Should be variable product
            update_post_meta($product_id, '_product_type', 'variable');
            
            // Set stock status to instock for variable products (they manage stock through variations)
            update_post_meta($product_id, '_stock_status', 'instock');
            update_post_meta($product_id, '_manage_stock', 'no');
            
            // Ensure variations are properly configured
            foreach ($variations as $variation) {
                $variation_product = wc_get_product($variation->ID);
                if ($variation_product) {
                    // If variation doesn't have stock status, set it to instock
                    if (!$variation_product->get_stock_status()) {
                        update_post_meta($variation->ID, '_stock_status', 'instock');
                    }
                }
            }
            
            $action_taken = 'Changed to Variable Product, set stock status to instock';
            
        } elseif ($variation_count === 0 && $current_type !== 'simple') {
            // Should be simple product
            update_post_meta($product_id, '_product_type', 'simple');
            
            // Set appropriate stock status for simple products
            $stock_status = get_post_meta($product_id, '_stock_status', true);
            if (!$stock_status) {
                update_post_meta($product_id, '_stock_status', 'instock');
            }
            
            $action_taken = 'Changed to Simple Product';
        } else {
            echo '<p>  No changes needed</p>';
            return;
        }
        
        // Clear product cache
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }
        
        // Clear WooCommerce cache
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($product_id, 'products');
        }
        
        $this->fixed_products[] = array(
            'id' => $product_id,
            'name' => $product->post_title,
            'action' => $action_taken
        );
        
        echo '<p style="color: green;">  ✅ ' . $action_taken . '</p>';
    }
}

// Run the fixer if accessed directly
if (!empty($_GET['run']) || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'fix-product-types.php') !== false)) {
    $fixer = new TwinTack_Product_Type_Fixer();
    $fixer->run_fix();
} else {
    echo '<h2>TwinTack Product Type Fixer</h2>';
    echo '<p><a href="?run=1">Click here to run the product type fixer</a></p>';
}

?>
