<?php
/**
 * TwinTack Product Save Debug Script
 * 
 * This script helps diagnose issues with WooCommerce product type saving
 * and variation data persistence problems.
 * 
 * Usage: Place in WordPress root and access via browser or WP-CLI
 * 
 * @package TwinTack
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Load WordPress if accessed directly
    require_once('wp-config.php');
}

class TwinTack_Product_Debug {
    
    private $debug_log = [];
    private $hooks_attached = false;
    
    public function __construct() {
        $this->log('=== TwinTack Product Debug Session Started ===');
        $this->log('Timestamp: ' . current_time('mysql'));
        $this->log('WooCommerce Version: ' . (defined('WC_VERSION') ? WC_VERSION : 'Not loaded'));
        $this->log('WordPress Version: ' . get_bloginfo('version'));
        
        // Attach debugging hooks
        $this->attach_debug_hooks();
        
        // Check current state
        $this->check_environment();
    }
    
    /**
     * Attach all debugging hooks for product save operations
     */
    private function attach_debug_hooks() {
        if ($this->hooks_attached) {
            return;
        }
        
        // Product save hooks - before and after
        add_action('woocommerce_process_product_meta', array($this, 'debug_before_product_save'), 5, 2);
        add_action('woocommerce_process_product_meta', array($this, 'debug_after_product_save'), 95, 2);
        
        // Product type specific hooks
        add_action('woocommerce_process_product_meta_simple', array($this, 'debug_simple_product_save'), 10, 1);
        add_action('woocommerce_process_product_meta_variable', array($this, 'debug_variable_product_save'), 10, 1);
        
        // Variation save hooks
        add_action('woocommerce_save_product_variation', array($this, 'debug_variation_save'), 10, 2);
        
        // Post save hooks
        add_action('save_post', array($this, 'debug_post_save'), 10, 3);
        add_action('wp_insert_post', array($this, 'debug_post_insert'), 10, 3);
        
        // Meta save hooks
        add_action('added_post_meta', array($this, 'debug_meta_added'), 10, 4);
        add_action('updated_post_meta', array($this, 'debug_meta_updated'), 10, 4);
        add_action('deleted_post_meta', array($this, 'debug_meta_deleted'), 10, 4);
        
        // AJAX hooks for admin product saves
        add_action('wp_ajax_woocommerce_save_variations', array($this, 'debug_ajax_save_variations'), 5);
        
        // Plugin conflict detection
        add_action('plugins_loaded', array($this, 'detect_plugin_conflicts'), 999);
        
        $this->hooks_attached = true;
        $this->log('Debug hooks attached successfully');
    }
    
    /**
     * Check environment and current state
     */
    private function check_environment() {
        $this->log('=== Environment Check ===');
        
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            $this->log('ERROR: WooCommerce is not active or loaded');
            return;
        }
        
        // Check active plugins that might interfere
        $active_plugins = get_option('active_plugins', array());
        $this->log('Active Plugins Count: ' . count($active_plugins));
        
        $wc_related_plugins = array();
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'woo') !== false || strpos($plugin, 'commerce') !== false) {
                $wc_related_plugins[] = $plugin;
            }
        }
        
        $this->log('WooCommerce-related plugins: ' . implode(', ', $wc_related_plugins));
        
        // Check for problematic plugins
        $problematic_plugins = array(
            'facebook-for-woocommerce',
            'amazon-for-woocommerce',
            'woo-variation-swatches',
            'product-import-export'
        );
        
        foreach ($problematic_plugins as $plugin_slug) {
            foreach ($active_plugins as $active_plugin) {
                if (strpos($active_plugin, $plugin_slug) !== false) {
                    $this->log('POTENTIAL CONFLICT: Found ' . $plugin_slug . ' - ' . $active_plugin);
                }
            }
        }
        
        // Check database tables
        global $wpdb;
        $tables_exist = array(
            'posts' => $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->posts}'"),
            'postmeta' => $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->postmeta}'"),
            'wc_products' => $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wc_products'"),
            'wc_product_meta_lookup' => $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wc_product_meta_lookup'")
        );
        
        foreach ($tables_exist as $table => $exists) {
            $this->log("Table {$table}: " . ($exists ? 'EXISTS' : 'MISSING'));
        }
        
        // Check HPOS status
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            $hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            $this->log('HPOS (High Performance Order Storage): ' . ($hpos_enabled ? 'ENABLED' : 'DISABLED'));
        }
    }
    
    /**
     * Debug before product save
     */
    public function debug_before_product_save($post_id, $post) {
        $this->log("=== BEFORE PRODUCT SAVE - Post ID: {$post_id} ===");
        
        // Get current product type from database
        $current_type = get_post_meta($post_id, '_product_type', true);
        $this->log("Current product type in DB: " . ($current_type ?: 'EMPTY'));
        
        // Get product type from POST data
        $posted_type = isset($_POST['product-type']) ? sanitize_text_field($_POST['product-type']) : 'NOT SET';
        $this->log("Posted product type: {$posted_type}");
        
        // Check for variations in POST data
        if (isset($_POST['variable_post_id']) && is_array($_POST['variable_post_id'])) {
            $variation_count = count($_POST['variable_post_id']);
            $this->log("Variations in POST data: {$variation_count}");
        }
        
        // Log all product-related POST data
        $product_post_keys = array();
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'product') !== false || strpos($key, 'variable') !== false || strpos($key, '_') === 0) {
                $product_post_keys[] = $key;
            }
        }
        $this->log("Product-related POST keys: " . implode(', ', $product_post_keys));
        
        // Check current variations
        if ($current_type === 'variable') {
            $variations = get_children(array(
                'post_parent' => $post_id,
                'post_type' => 'product_variation',
                'numberposts' => -1,
                'post_status' => 'any'
            ));
            $this->log("Current variations in DB: " . count($variations));
        }
    }
    
    /**
     * Debug after product save
     */
    public function debug_after_product_save($post_id, $post) {
        $this->log("=== AFTER PRODUCT SAVE - Post ID: {$post_id} ===");
        
        // Get final product type
        $final_type = get_post_meta($post_id, '_product_type', true);
        $this->log("Final product type in DB: " . ($final_type ?: 'EMPTY'));
        
        // Get WC Product object
        $product = wc_get_product($post_id);
        if ($product) {
            $this->log("WC Product Type: " . $product->get_type());
            $this->log("WC Product Status: " . $product->get_status());
            $this->log("WC Product Stock Status: " . $product->get_stock_status());
            
            if ($product->is_type('variable')) {
                $variations = $product->get_children();
                $this->log("WC Product Variations: " . count($variations));
                
                foreach ($variations as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation) {
                        $this->log("  Variation {$variation_id}: " . $variation->get_stock_status());
                    }
                }
            }
        } else {
            $this->log("ERROR: Could not load WC Product object");
        }
        
        // Check for any errors
        if (function_exists('wc_get_notices')) {
            $notices = wc_get_notices();
            if (!empty($notices['error'])) {
                $this->log("WC Errors: " . print_r($notices['error'], true));
            }
        }
    }
    
    /**
     * Debug simple product save
     */
    public function debug_simple_product_save($post_id) {
        $this->log("SIMPLE PRODUCT SAVE HOOK - Post ID: {$post_id}");
    }
    
    /**
     * Debug variable product save
     */
    public function debug_variable_product_save($post_id) {
        $this->log("VARIABLE PRODUCT SAVE HOOK - Post ID: {$post_id}");
    }
    
    /**
     * Debug variation save
     */
    public function debug_variation_save($variation_id, $i) {
        $this->log("VARIATION SAVE - Variation ID: {$variation_id}, Index: {$i}");
        
        $variation = wc_get_product($variation_id);
        if ($variation) {
            $this->log("  Variation Stock Status: " . $variation->get_stock_status());
            $this->log("  Variation Manage Stock: " . ($variation->get_manage_stock() ? 'YES' : 'NO'));
        }
    }
    
    /**
     * Debug post save
     */
    public function debug_post_save($post_id, $post, $update) {
        if ($post->post_type !== 'product') {
            return;
        }
        
        $this->log("POST SAVE HOOK - Post ID: {$post_id}, Update: " . ($update ? 'YES' : 'NO'));
    }
    
    /**
     * Debug post insert
     */
    public function debug_post_insert($post_id, $post, $update) {
        if ($post->post_type !== 'product') {
            return;
        }
        
        $this->log("POST INSERT HOOK - Post ID: {$post_id}, Update: " . ($update ? 'YES' : 'NO'));
    }
    
    /**
     * Debug meta operations
     */
    public function debug_meta_added($meta_id, $post_id, $meta_key, $meta_value) {
        if (strpos($meta_key, '_product') !== false || strpos($meta_key, '_stock') !== false) {
            $this->log("META ADDED - Post: {$post_id}, Key: {$meta_key}, Value: " . $this->format_meta_value($meta_value));
        }
    }
    
    public function debug_meta_updated($meta_id, $post_id, $meta_key, $meta_value) {
        if (strpos($meta_key, '_product') !== false || strpos($meta_key, '_stock') !== false) {
            $this->log("META UPDATED - Post: {$post_id}, Key: {$meta_key}, Value: " . $this->format_meta_value($meta_value));
        }
    }
    
    public function debug_meta_deleted($meta_ids, $post_id, $meta_key, $meta_value) {
        if (strpos($meta_key, '_product') !== false || strpos($meta_key, '_stock') !== false) {
            $this->log("META DELETED - Post: {$post_id}, Key: {$meta_key}");
        }
    }
    
    /**
     * Debug AJAX variation saves
     */
    public function debug_ajax_save_variations() {
        $this->log("AJAX SAVE VARIATIONS CALLED");
        
        if (isset($_POST['product_id'])) {
            $product_id = intval($_POST['product_id']);
            $this->log("AJAX Product ID: {$product_id}");
        }
    }
    
    /**
     * Detect plugin conflicts
     */
    public function detect_plugin_conflicts() {
        $this->log("=== Plugin Conflict Detection ===");
        
        // Check for hooks that might interfere with product saves
        global $wp_filter;
        
        $critical_hooks = array(
            'woocommerce_process_product_meta',
            'save_post',
            'wp_insert_post',
            'updated_post_meta'
        );
        
        foreach ($critical_hooks as $hook) {
            if (isset($wp_filter[$hook])) {
                $callbacks = $wp_filter[$hook]->callbacks;
                $this->log("Hook '{$hook}' has " . count($callbacks) . " priority levels");
                
                foreach ($callbacks as $priority => $functions) {
                    foreach ($functions as $function_data) {
                        $callback_info = $this->get_callback_info($function_data['function']);
                        $this->log("  Priority {$priority}: {$callback_info}");
                    }
                }
            }
        }
    }
    
    /**
     * Get callback information for debugging
     */
    private function get_callback_info($callback) {
        if (is_string($callback)) {
            return $callback;
        } elseif (is_array($callback)) {
            if (is_object($callback[0])) {
                return get_class($callback[0]) . '::' . $callback[1];
            } else {
                return $callback[0] . '::' . $callback[1];
            }
        } elseif (is_object($callback)) {
            if ($callback instanceof Closure) {
                return 'Closure';
            } else {
                return get_class($callback);
            }
        }
        return 'Unknown callback type';
    }
    
    /**
     * Format meta value for logging
     */
    private function format_meta_value($value) {
        if (is_array($value) || is_object($value)) {
            return print_r($value, true);
        }
        return (string) $value;
    }
    
    /**
     * Log debug message
     */
    private function log($message) {
        $timestamp = current_time('H:i:s');
        $formatted_message = "[{$timestamp}] {$message}";
        
        $this->debug_log[] = $formatted_message;
        
        // Write to WordPress debug log if enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('TWINTACK_PRODUCT_DEBUG: ' . $formatted_message);
        }
        
        // Also write to our custom log file
        $log_file = ABSPATH . 'twintack-product-debug.log';
        file_put_contents($log_file, $formatted_message . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get debug log
     */
    public function get_debug_log() {
        return $this->debug_log;
    }
    
    /**
     * Display debug information
     */
    public function display_debug_info() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        echo '<div style="font-family: monospace; background: #f0f0f0; padding: 20px; margin: 20px;">';
        echo '<h2>TwinTack Product Debug Information</h2>';
        
        foreach ($this->debug_log as $log_entry) {
            echo '<div style="margin: 5px 0; padding: 5px; background: white; border-left: 3px solid #0073aa;">';
            echo esc_html($log_entry);
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Test specific product
     */
    public function test_product($product_id) {
        $this->log("=== TESTING PRODUCT ID: {$product_id} ===");
        
        $product = wc_get_product($product_id);
        if (!$product) {
            $this->log("ERROR: Product not found");
            return;
        }
        
        $this->log("Product Title: " . $product->get_name());
        $this->log("Product Type: " . $product->get_type());
        $this->log("Product Status: " . $product->get_status());
        $this->log("Stock Status: " . $product->get_stock_status());
        $this->log("Manage Stock: " . ($product->get_manage_stock() ? 'YES' : 'NO'));
        
        // Check meta directly
        $meta_type = get_post_meta($product_id, '_product_type', true);
        $this->log("Meta _product_type: " . ($meta_type ?: 'EMPTY'));
        
        $meta_stock_status = get_post_meta($product_id, '_stock_status', true);
        $this->log("Meta _stock_status: " . ($meta_stock_status ?: 'EMPTY'));
        
        if ($product->is_type('variable')) {
            $variations = $product->get_children();
            $this->log("Variations Count: " . count($variations));
            
            foreach ($variations as $variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    $this->log("  Variation {$variation_id}: " . $variation->get_stock_status());
                }
            }
        }
        
        // Check for attributes
        $attributes = $product->get_attributes();
        $this->log("Attributes Count: " . count($attributes));
        
        foreach ($attributes as $attribute_name => $attribute) {
            if (is_object($attribute)) {
                $this->log("  Attribute: {$attribute_name}, Variation: " . ($attribute->get_variation() ? 'YES' : 'NO'));
            }
        }
    }
}

// Initialize debug class
$twintack_product_debug = new TwinTack_Product_Debug();

// Handle direct access for testing
if (!empty($_GET['action'])) {
    switch ($_GET['action']) {
        case 'display':
            $twintack_product_debug->display_debug_info();
            break;
            
        case 'test_product':
            if (!empty($_GET['product_id'])) {
                $product_id = intval($_GET['product_id']);
                $twintack_product_debug->test_product($product_id);
                $twintack_product_debug->display_debug_info();
            } else {
                echo '<p>Please provide product_id parameter</p>';
            }
            break;
            
        case 'clear_log':
            $log_file = ABSPATH . 'twintack-product-debug.log';
            if (file_exists($log_file)) {
                unlink($log_file);
                echo '<p>Debug log cleared</p>';
            }
            break;
            
        default:
            echo '<h2>TwinTack Product Debug Script</h2>';
            echo '<p>Available actions:</p>';
            echo '<ul>';
            echo '<li><a href="?action=display">Display current debug log</a></li>';
            echo '<li><a href="?action=test_product&product_id=123">Test specific product (replace 123 with product ID)</a></li>';
            echo '<li><a href="?action=clear_log">Clear debug log</a></li>';
            echo '</ul>';
            echo '<p>Debug hooks are now active. Edit/save products to see debug output.</p>';
    }
}

// Make debug instance globally available
$GLOBALS['twintack_product_debug'] = $twintack_product_debug;

?>
