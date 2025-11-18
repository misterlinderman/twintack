<?php
/**
 * Plugin Name: TwinTack Product Save Bypass
 * Description: Temporarily bypass wholesale plugin interference during product saves
 * Version: 1.0.1
 * Author: TwinTack
 * Requires at least: 5.8
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 * Requires Plugins: woocommerce
 * 
 * @package TwinTack_Product_Save_Bypass
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Declare WooCommerce HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

class TwinTack_Product_Save_Bypass {
    
    private $bypass_active = false;
    
    public function __construct() {
        // Delay initialization until plugins are loaded
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Check WooCommerce version compatibility
        if (version_compare(WC()->version, '6.0', '<')) {
            add_action('admin_notices', array($this, 'woocommerce_version_notice'));
            return;
        }
        
        // Add admin interface
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add bypass functionality
        add_action('admin_init', array($this, 'maybe_activate_bypass'));
        
        // Add admin notice
        add_action('admin_notices', array($this, 'show_bypass_notice'));
        
        // Add quick bypass button to product edit pages
        add_action('edit_form_after_title', array($this, 'add_bypass_button'));
    }
    
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><strong>TwinTack Product Save Bypass</strong> requires WooCommerce to be installed and activated.</p>
        </div>
        <?php
    }
    
    public function woocommerce_version_notice() {
        ?>
        <div class="error">
            <p><strong>TwinTack Product Save Bypass</strong> requires WooCommerce version 6.0 or higher. You are running version <?php echo WC()->version; ?>.</p>
        </div>
        <?php
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'Product Save Bypass',
            'Product Save Bypass',
            'manage_options',
            'twintack-bypass',
            array($this, 'admin_page')
        );
    }
    
    public function maybe_activate_bypass() {
        // Check if bypass is requested
        if (isset($_GET['twintack_bypass']) && $_GET['twintack_bypass'] === '1') {
            $this->activate_bypass();
        }
        
        // Check if we're saving a product with bypass active
        if (isset($_POST['post_type']) && $_POST['post_type'] === 'product' && $this->is_bypass_active()) {
            $this->apply_bypass();
        }
    }
    
    private function activate_bypass() {
        set_transient('twintack_bypass_active', true, 300); // 5 minutes
        $this->bypass_active = true;
        
        if (WP_DEBUG_LOG) {
            error_log('TWINTACK_BYPASS: Product save bypass activated for 5 minutes');
        }
    }
    
    private function is_bypass_active() {
        return get_transient('twintack_bypass_active') || $this->bypass_active;
    }
    
    private function apply_bypass() {
        // Remove wholesale plugin hooks that interfere with product saves
        $this->remove_wholesale_hooks();
        
        if (WP_DEBUG_LOG) {
            error_log('TWINTACK_BYPASS: Wholesale plugin hooks temporarily removed for product save');
        }
    }
    
    private function remove_wholesale_hooks() {
        global $wp_filter;
        
        // List of problematic wholesale plugin hooks
        $problematic_hooks = array(
            'woocommerce_process_product_meta' => array(
                'WWPP_Admin_Custom_Fields_Product',
                'WWP_Admin_Custom_Fields_Variable_Product',
                'WWPP_Product_Visibility'
            ),
            'save_post_product' => array(
                'WWPP_Admin_Custom_Fields_Product',
                'WWPP_Cache'
            ),
            'save_post' => array(
                'WWPP_Product_Visibility'
            )
        );
        
        foreach ($problematic_hooks as $hook_name => $class_prefixes) {
            if (isset($wp_filter[$hook_name])) {
                foreach ($wp_filter[$hook_name]->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback_id => $callback_data) {
                        $callback_info = $this->get_callback_info($callback_data['function']);
                        
                        // Check if this callback is from a wholesale plugin
                        foreach ($class_prefixes as $prefix) {
                            if (strpos($callback_info, $prefix) !== false) {
                                unset($wp_filter[$hook_name]->callbacks[$priority][$callback_id]);
                                if (WP_DEBUG_LOG) {
                                    error_log("TWINTACK_BYPASS: Removed hook {$hook_name} callback: {$callback_info}");
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
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
            return get_class($callback);
        }
        return 'Unknown callback';
    }
    
    public function show_bypass_notice() {
        if ($this->is_bypass_active()) {
            $screen = get_current_screen();
            if ($screen && in_array($screen->id, ['product', 'edit-product'])) {
                echo '<div class="notice notice-success">';
                echo '<p><strong>🛡️ Product Save Bypass Active</strong> - Wholesale plugin interference disabled for product saves.</p>';
                echo '</div>';
            }
        }
    }
    
    public function add_bypass_button() {
        global $post;
        
        if (!$post || $post->post_type !== 'product') {
            return;
        }
        
        $bypass_url = add_query_arg('twintack_bypass', '1', $_SERVER['REQUEST_URI']);
        
        echo '<div style="margin: 10px 0; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px;">';
        echo '<p><strong>Having trouble saving product types?</strong></p>';
        echo '<p>If the product type keeps reverting to "Simple Product", click this button before editing:</p>';
        echo '<a href="' . esc_url($bypass_url) . '" class="button button-secondary">';
        echo '🛡️ Activate Product Save Bypass (5 min)';
        echo '</a>';
        echo '</div>';
    }
    
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        echo '<div class="wrap">';
        echo '<h1>Product Save Bypass</h1>';
        
        if (isset($_POST['activate_bypass']) && wp_verify_nonce($_POST['_wpnonce'], 'activate_bypass')) {
            $this->activate_bypass();
            echo '<div class="notice notice-success"><p>Bypass activated for 5 minutes!</p></div>';
        }
        
        echo '<div class="postbox">';
        echo '<div class="postbox-header"><h2 class="hndle">🛡️ Wholesale Plugin Bypass</h2></div>';
        echo '<div class="inside">';
        
        if ($this->is_bypass_active()) {
            echo '<div class="notice notice-success inline">';
            echo '<p><strong>✅ Bypass is currently ACTIVE</strong></p>';
            echo '<p>Wholesale plugin interference is disabled for the next few minutes.</p>';
            echo '<p>You can now safely create/edit variable products.</p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-info inline">';
            echo '<p><strong>ℹ️ Bypass is currently INACTIVE</strong></p>';
            echo '<p>Wholesale plugins may interfere with product saves.</p>';
            echo '</div>';
        }
        
        echo '<h3>How to Use:</h3>';
        echo '<ol>';
        echo '<li><strong>Before creating/editing products:</strong> Activate the bypass</li>';
        echo '<li><strong>Create/edit your products:</strong> Set product types, add variations, save</li>';
        echo '<li><strong>Bypass auto-expires:</strong> After 5 minutes, normal operation resumes</li>';
        echo '</ol>';
        
        echo '<form method="post">';
        wp_nonce_field('activate_bypass');
        echo '<button type="submit" name="activate_bypass" class="button button-primary button-large">';
        echo '🛡️ Activate Bypass (5 minutes)';
        echo '</button>';
        echo '</form>';
        
        echo '</div></div>';
        
        echo '<div class="postbox">';
        echo '<div class="postbox-header"><h2 class="hndle">⚠️ What This Does</h2></div>';
        echo '<div class="inside">';
        echo '<p>This bypass temporarily removes the wholesale plugin hooks that interfere with product saves:</p>';
        echo '<ul>';
        echo '<li>WWPP_Admin_Custom_Fields_Product hooks</li>';
        echo '<li>WWP_Admin_Custom_Fields_Variable_Product hooks</li>';
        echo '<li>WWPP_Product_Visibility hooks</li>';
        echo '</ul>';
        echo '<p><strong>Note:</strong> Wholesale pricing features may not work correctly while bypass is active, but will resume normal operation when it expires.</p>';
        echo '</div></div>';
        
        echo '</div>';
    }
}

// Initialize the bypass
new TwinTack_Product_Save_Bypass();

?>
