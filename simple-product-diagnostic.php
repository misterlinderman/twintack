<?php
/**
 * Simple Product Diagnostic - WordPress Plugin Format
 * 
 * This creates a simple admin page to diagnose product issues
 * 
 * @package TwinTack
 * @version 1.0.0
 */

// Only run if WordPress is loaded
if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

// Add admin menu
add_action('admin_menu', 'twintack_add_diagnostic_menu');

function twintack_add_diagnostic_menu() {
    add_submenu_page(
        'tools.php',
        'Product Diagnostic',
        'Product Diagnostic',
        'manage_options',
        'twintack-product-diagnostic',
        'twintack_product_diagnostic_page'
    );
}

function twintack_product_diagnostic_page() {
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
    
    // Run diagnostics
    twintack_run_product_diagnostics();
    
    echo '</div>';
}

function twintack_run_product_diagnostics() {
    global $wpdb;
    
    echo '<div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4;">';
    echo '<h2>🔍 WooCommerce Status</h2>';
    echo '<p><strong>WooCommerce Version:</strong> ' . WC_VERSION . '</p>';
    echo '<p><strong>WordPress Version:</strong> ' . get_bloginfo('version') . '</p>';
    echo '<p><strong>WP_DEBUG:</strong> ' . (WP_DEBUG ? 'Enabled' : 'Disabled') . '</p>';
    echo '</div>';
    
    // Get problematic products
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
        LIMIT 20
    ");
    
    echo '<div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4;">';
    echo '<h2>📊 Recent Products Analysis</h2>';
    
    if (empty($products)) {
        echo '<p>No products found.</p>';
        echo '</div>';
        return;
    }
    
    $problematic_products = array();
    
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>ID</th><th>Title</th><th>Meta Type</th><th>WC Type</th><th>Variations</th><th>Stock Status</th><th>Status</th>';
    echo '</tr></thead><tbody>';
    
    foreach ($products as $product_data) {
        $product = wc_get_product($product_data->ID);
        $wc_type = $product ? $product->get_type() : 'ERROR';
        
        // Count variations
        $variation_count = 0;
        if ($product && $product->is_type('variable')) {
            $variation_count = count($product->get_children());
        } else {
            // Check database directly for variations
            $variation_count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_parent = %d AND post_type = 'product_variation'
            ", $product_data->ID));
        }
        
        // Detect issues
        $has_issue = false;
        $issue_description = '';
        
        if (empty($product_data->product_type)) {
            $has_issue = true;
            $issue_description = 'Empty product type';
        } elseif ($product_data->product_type === 'simple' && $variation_count > 0) {
            $has_issue = true;
            $issue_description = 'Simple product with variations';
            $problematic_products[] = $product_data->ID;
        } elseif ($product_data->product_type === 'variable' && $variation_count === 0) {
            $has_issue = true;
            $issue_description = 'Variable product without variations';
        } elseif ($product_data->product_type !== $wc_type) {
            $has_issue = true;
            $issue_description = 'Meta/WC type mismatch';
        }
        
        $row_class = $has_issue ? 'style="background-color: #ffeeee;"' : '';
        
        echo '<tr ' . $row_class . '>';
        echo '<td>' . $product_data->ID . '</td>';
        echo '<td>' . esc_html(substr($product_data->post_title, 0, 30)) . '</td>';
        echo '<td>' . ($product_data->product_type ?: 'EMPTY') . '</td>';
        echo '<td>' . $wc_type . '</td>';
        echo '<td>' . $variation_count . '</td>';
        echo '<td>' . ($product_data->stock_status ?: 'N/A') . '</td>';
        echo '<td>' . ($has_issue ? '❌ ' . $issue_description : '✅ OK') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '</div>';
    
    // Show problematic products summary
    if (!empty($problematic_products)) {
        echo '<div style="background: #fff2cc; padding: 20px; margin: 20px 0; border: 1px solid #d1b000;">';
        echo '<h2>🚨 Problematic Products Found</h2>';
        echo '<p>Found ' . count($problematic_products) . ' products that appear to be "Simple" but have variations.</p>';
        
        if (isset($_POST['fix_products']) && wp_verify_nonce($_POST['_wpnonce'], 'fix_products')) {
            twintack_fix_problematic_products($problematic_products);
        } else {
            echo '<form method="post">';
            wp_nonce_field('fix_products');
            echo '<p><strong>⚠️ BACKUP YOUR DATABASE FIRST!</strong></p>';
            echo '<p>This will change the product type to "variable" for products that have variations but are marked as "simple".</p>';
            echo '<button type="submit" name="fix_products" class="button button-primary">Fix These Products</button>';
            echo '</form>';
        }
        echo '</div>';
    }
    
    // Plugin analysis
    twintack_analyze_plugins();
}

function twintack_fix_problematic_products($product_ids) {
    echo '<div style="background: #e8f5e8; padding: 20px; margin: 20px 0; border: 1px solid #46b450;">';
    echo '<h3>🔧 Fixing Products...</h3>';
    
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
        
        // Set stock status to instock (variable products manage stock through variations)
        update_post_meta($product_id, '_stock_status', 'instock');
        update_post_meta($product_id, '_manage_stock', 'no');
        
        // Clear product cache
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }
        
        $fixed_count++;
        echo '<p>✅ Fixed Product ID ' . $product_id . ': ' . esc_html($product->post_title) . '</p>';
    }
    
    echo '<p><strong>Fixed ' . $fixed_count . ' products successfully!</strong></p>';
    
    if (!empty($errors)) {
        echo '<h4>Errors:</h4>';
        foreach ($errors as $error) {
            echo '<p style="color: red;">❌ ' . $error . '</p>';
        }
    }
    
    echo '<p><strong>Next steps:</strong></p>';
    echo '<ul>';
    echo '<li>Go to WooCommerce → Products and check the fixed products</li>';
    echo '<li>Test adding products to cart to ensure variations work</li>';
    echo '<li>Clear any caching plugins</li>';
    echo '</ul>';
    
    echo '</div>';
}

function twintack_analyze_plugins() {
    echo '<div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4;">';
    echo '<h2>🔌 Plugin Analysis</h2>';
    
    $active_plugins = get_option('active_plugins', array());
    
    $potential_conflicts = array(
        'facebook-for-woocommerce' => 'Facebook for WooCommerce',
        'amazon-for-woocommerce' => 'Amazon for WooCommerce',
        'woo-variation-swatches' => 'Variation Swatches',
        'jetpack' => 'Jetpack',
        'yith-woocommerce' => 'YITH WooCommerce plugins'
    );
    
    $found_conflicts = array();
    
    foreach ($active_plugins as $plugin) {
        foreach ($potential_conflicts as $conflict_slug => $conflict_name) {
            if (strpos($plugin, $conflict_slug) !== false) {
                $found_conflicts[] = $conflict_name . ' (' . $plugin . ')';
            }
        }
    }
    
    if (empty($found_conflicts)) {
        echo '<p>✅ No obvious plugin conflicts detected</p>';
    } else {
        echo '<h3>⚠️ Potential Plugin Conflicts:</h3>';
        echo '<ul>';
        foreach ($found_conflicts as $conflict) {
            echo '<li style="color: orange;">' . $conflict . '</li>';
        }
        echo '</ul>';
        echo '<p><strong>Recommendation:</strong> Try temporarily deactivating these plugins to test if they\'re causing the issue.</p>';
    }
    
    echo '</div>';
}

// Add debugging hooks when this file is loaded
add_action('init', 'twintack_add_product_debug_hooks');

function twintack_add_product_debug_hooks() {
    if (!is_admin()) {
        return;
    }
    
    // Add hooks to monitor product saves
    add_action('woocommerce_process_product_meta', 'twintack_debug_product_save', 5, 2);
    add_action('save_post', 'twintack_debug_post_save', 10, 3);
}

function twintack_debug_product_save($post_id, $post) {
    if (!WP_DEBUG_LOG) {
        return;
    }
    
    $current_type = get_post_meta($post_id, '_product_type', true);
    $posted_type = isset($_POST['product-type']) ? sanitize_text_field($_POST['product-type']) : 'NOT SET';
    
    error_log("TWINTACK_DEBUG: Product save - ID: {$post_id}, Current Type: {$current_type}, Posted Type: {$posted_type}");
    
    if (isset($_POST['variable_post_id']) && is_array($_POST['variable_post_id'])) {
        $variation_count = count($_POST['variable_post_id']);
        error_log("TWINTACK_DEBUG: Variations in POST: {$variation_count}");
    }
}

function twintack_debug_post_save($post_id, $post, $update) {
    if ($post->post_type !== 'product' || !WP_DEBUG_LOG) {
        return;
    }
    
    $final_type = get_post_meta($post_id, '_product_type', true);
    error_log("TWINTACK_DEBUG: Post save complete - ID: {$post_id}, Final Type: {$final_type}");
}

?>
