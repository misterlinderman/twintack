<?php
/**
 * Product Type Fix Class
 * 
 * This class handles the backend functionality for fixing
 * product type recognition issues.
 */

class TwinTack_Product_Type_Fix {

    /**
     * Initialize the fixes
     */
    public function __construct() {
        add_action('admin_init', array($this, 'init_fixes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_general_scripts'));
        add_action('wp_ajax_twintack_get_product_type', array($this, 'ajax_get_product_type'));
        add_action('wp_ajax_twintack_fix_product_type', array($this, 'ajax_fix_product_type'));
    }

    /**
     * Initialize various fixes
     */
    public function init_fixes() {
        // Fix product type detection issues - DISABLED (AJAX-only approach)
        // add_filter('woocommerce_product_type_query', array($this, 'fix_product_type_query_safe'), 10, 2);
        
        // Ensure proper product type handling
        add_action('save_post', array($this, 'ensure_product_type_consistency'), 10, 2);
        
        // Fix Klaviyo script loading order issues
        add_filter('script_loader_tag', array($this, 'fix_klaviyo_script_loading'), 10, 3);
    }

    /**
     * Enqueue necessary scripts
     */
    public function enqueue_scripts($hook) {
        // Only load on product edit pages
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }

        global $post;
        if (!$post || $post->post_type !== 'product') {
            return;
        }

        wp_enqueue_script(
            'twintack-product-type-fix',
            TWINTACK_CONSOLE_FIXES_PLUGIN_URL . 'assets/js/product-type-fix.js',
            array('jquery'),
            TWINTACK_CONSOLE_FIXES_VERSION,
            true
        );

        // Localize script with necessary data
        wp_localize_script('twintack-product-type-fix', 'twintack_product_fix_params', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('twintack_product_fix_nonce'),
            'product_id' => $post->ID
        ));
    }
    
    /**
     * Enqueue general console fixes for all admin pages
     */
    public function enqueue_general_scripts($hook) {
        // Only enqueue if not already enqueued by the specific product script
        if (wp_script_is('twintack-product-type-fix', 'enqueued')) {
            return;
        }
        
        // Enqueue a minimal version for general console fixes
        wp_enqueue_script(
            'twintack-general-console-fixes',
            TWINTACK_CONSOLE_FIXES_PLUGIN_URL . 'assets/js/product-type-fix.js',
            array('jquery'),
            TWINTACK_CONSOLE_FIXES_VERSION,
            true
        );
        
        // Don't localize for general pages - let the JS handle missing params
    }

    /**
     * Fix Klaviyo script loading order issues
     */
    public function fix_klaviyo_script_loading($tag, $handle, $src) {
        if (strpos($handle, 'klaviyo') !== false || strpos($src, 'klaviyo') !== false) {
            // Force scripts to load in footer
            $tag = str_replace('<script ', '<script data-footer="true" ', $tag);
        }
        return $tag;
    }

    /**
     * AJAX handler to get product type from database
     */
    public function ajax_get_product_type() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_product_fix_nonce')) {
            wp_die('Security check failed');
        }

        $product_id = intval($_POST['product_id']);
        if (!$product_id) {
            wp_send_json_error('Invalid product ID');
        }

        // Get the product type from database
        $product_type = $this->get_product_type_from_database($product_id);
        
        if ($product_type) {
            wp_send_json_success(array(
                'product_type' => $product_type,
                'product_id' => $product_id
            ));
        } else {
            wp_send_json_error('Could not determine product type');
        }
    }

    /**
     * AJAX handler to fix product type
     */
    public function ajax_fix_product_type() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_product_fix_nonce')) {
            wp_die('Security check failed');
        }

        $product_id = intval($_POST['product_id']);
        $product_type = sanitize_text_field($_POST['product_type']);
        
        if (!$product_id || !$product_type) {
            wp_send_json_error('Invalid parameters');
        }

        // Update the product type
        $result = $this->update_product_type($product_id, $product_type);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Product type updated successfully',
                'product_type' => $product_type
            ));
        } else {
            wp_send_json_error('Failed to update product type');
        }
    }

    /**
     * Get product type from database
     */
    private function get_product_type_from_database($product_id) {
        // Safety check: ensure we have a valid product ID
        if (!$product_id || !is_numeric($product_id)) {
            return false;
        }

        // Safety check: ensure WooCommerce is loaded
        if (!function_exists('wc_get_product')) {
            return false;
        }

        try {
            // Use WooCommerce's proper method to get product type
            $product = wc_get_product($product_id);
            if ($product) {
                return $product->get_type();
            }
        } catch (Exception $e) {
            // If WooCommerce method fails, fall back to post meta
            error_log('TwinTack Product Fix: Error getting product type via WooCommerce: ' . $e->getMessage());
        }

        // Fallback: check post meta directly
        $product_type = get_post_meta($product_id, '_product_type', true);
        
        // If not found in post meta, try to determine from variations
        if (!$product_type) {
            try {
                $variations = get_posts(array(
                    'post_parent' => $product_id,
                    'post_type' => 'product_variation',
                    'numberposts' => 1,
                    'post_status' => 'any'
                ));

                if (!empty($variations)) {
                    $product_type = 'variable';
                } else {
                    $product_type = 'simple';
                }
            } catch (Exception $e) {
                error_log('TwinTack Product Fix: Error checking variations: ' . $e->getMessage());
                $product_type = 'simple'; // Default fallback
            }
        }

        return $product_type;
    }

    /**
     * Update product type in database
     */
    private function update_product_type($product_id, $product_type) {
        // Use WooCommerce's proper method to update product type
        $product = wc_get_product($product_id);
        if ($product) {
            $product->set_type($product_type);
            $result = $product->save();
            
            // Also update post meta for compatibility
            update_post_meta($product_id, '_product_type', $product_type);
            
            return $result;
        }

        // Fallback: update post meta directly
        return update_post_meta($product_id, '_product_type', $product_type);
    }

    /**
     * Safer version of product type query fix
     */
    public function fix_product_type_query_safe($override, $product_id) {
        // Only override if we're in admin and there might be an issue
        if (!is_admin() || $override !== false) {
            return $override;
        }

        // Safety check: ensure WooCommerce is loaded
        if (!function_exists('wc_get_product')) {
            return $override;
        }

        // Safety check: ensure we have a valid product ID
        if (!$product_id || !is_numeric($product_id)) {
            return $override;
        }

        // Additional safety: only run on product edit pages
        global $pagenow;
        if (!in_array($pagenow, array('post.php', 'post-new.php'))) {
            return $override;
        }

        // Additional safety: check if we're editing a product
        if (!isset($_GET['post']) || $_GET['post'] != $product_id) {
            return $override;
        }

        try {
            // Get the actual product type from database
            $actual_type = $this->get_product_type_from_database($product_id);
            
            if ($actual_type) {
                return $actual_type;
            }
        } catch (Exception $e) {
            // If there's any error, return the original override
            error_log('TwinTack Product Fix: Error in fix_product_type_query_safe: ' . $e->getMessage());
            return $override;
        }

        return $override;
    }

    /**
     * Fix product type query issues (original version - kept for reference)
     */
    public function fix_product_type_query($override, $product_id) {
        // Only override if we're in admin and there might be an issue
        if (!is_admin() || $override !== false) {
            return $override;
        }

        // Safety check: ensure WooCommerce is loaded
        if (!function_exists('wc_get_product')) {
            return $override;
        }

        // Safety check: ensure we have a valid product ID
        if (!$product_id || !is_numeric($product_id)) {
            return $override;
        }

        try {
            // Get the actual product type from database
            $actual_type = $this->get_product_type_from_database($product_id);
            
            if ($actual_type) {
                return $actual_type;
            }
        } catch (Exception $e) {
            // If there's any error, return the original override
            error_log('TwinTack Product Fix: Error in fix_product_type_query: ' . $e->getMessage());
            return $override;
        }

        return $override;
    }

    /**
     * Ensure product type consistency when saving
     */
    public function ensure_product_type_consistency($post_id, $post) {
        // Safety check: ensure we have valid parameters
        if (!$post_id || !$post || !is_object($post)) {
            return;
        }

        // Only for products
        if ($post->post_type !== 'product') {
            return;
        }

        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Safety check: ensure WooCommerce is loaded
        if (!function_exists('wc_get_product')) {
            return;
        }

        try {
            // Get the product type from the form
            if (isset($_POST['product-type'])) {
                $form_product_type = sanitize_text_field($_POST['product-type']);
                $db_product_type = $this->get_product_type_from_database($post_id);
                
                // If there's a mismatch, fix it
                if ($form_product_type && $form_product_type !== $db_product_type) {
                    $this->update_product_type($post_id, $form_product_type);
                }
            }
        } catch (Exception $e) {
            // If there's any error, log it but don't break the save process
            error_log('TwinTack Product Fix: Error in ensure_product_type_consistency: ' . $e->getMessage());
        }
    }
}
