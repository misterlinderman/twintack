<?php
/**
 * Plugin Name: TwinTack Order Debugging Suite
 * Description: Comprehensive debugging and prevention tools for order duplication issues
 * Version: 1.0.0
 * Author: TwinTack
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure WordPress is loaded
if (!function_exists('add_action')) {
    return;
}

class TwinTack_Order_Debugging_Suite {
    
    private $debug_active = false;
    private $prevention_active = false;
    private $order_hooks_log = array();
    private $current_order_id = null;
    private $hook_call_count = 0;
    private $processed_orders = array();
    private $order_creation_lock = false;
    
    private $wholesale_plugins = array(
        'woocommerce-wholesale-lead-capture',
        'woocommerce-wholesale-order-form', 
        'woocommerce-wholesale-payments',
        'woocommerce-wholesale-prices',
        'woocommerce-wholesale-prices-premium'
    );
    
    private $order_hooks = array(
        'woocommerce_checkout_order_processed',
        'woocommerce_new_order',
        'woocommerce_payment_complete',
        'woocommerce_order_status_changed',
        'woocommerce_create_order',
        'woocommerce_checkout_create_order',
        'woocommerce_update_order',
        'woocommerce_new_order_item',
        'woocommerce_update_order_item',
        'woocommerce_rest_insert_shop_order_object'
    );
    
    public function __construct() {
        // Add admin interface
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add functionality
        add_action('admin_init', array($this, 'handle_admin_actions'));
        
        // Add admin notice
        add_action('admin_notices', array($this, 'show_admin_notices'));
        
        // Hook into order creation process
        $this->hook_into_order_creation();
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'Order Debugging Suite',
            'Order Debugging Suite',
            'manage_options',
            'twintack-order-debugging-suite',
            array($this, 'admin_page')
        );
    }
    
    public function handle_admin_actions() {
        // Handle debug activation
        if (isset($_GET['twintack_debug']) && $_GET['twintack_debug'] === '1') {
            $this->activate_debug();
        }
        
        if (isset($_GET['twintack_debug']) && $_GET['twintack_debug'] === '0') {
            $this->deactivate_debug();
        }
        
        // Handle prevention activation
        if (isset($_GET['twintack_prevent']) && $_GET['twintack_prevent'] === '1') {
            $this->activate_prevention();
        }
        
        if (isset($_GET['twintack_prevent']) && $_GET['twintack_prevent'] === '0') {
            $this->deactivate_prevention();
        }
        
        // Handle form submissions
        if (isset($_POST['activate_debug']) && wp_verify_nonce($_POST['_wpnonce'], 'activate_debug')) {
            $this->activate_debug();
        }
        
        if (isset($_POST['deactivate_debug']) && wp_verify_nonce($_POST['_wpnonce'], 'deactivate_debug')) {
            $this->deactivate_debug();
        }
        
        if (isset($_POST['activate_prevention']) && wp_verify_nonce($_POST['_wpnonce'], 'activate_prevention')) {
            $this->activate_prevention();
        }
        
        if (isset($_POST['deactivate_prevention']) && wp_verify_nonce($_POST['_wpnonce'], 'deactivate_prevention')) {
            $this->deactivate_prevention();
        }
        
        if (isset($_POST['clear_logs']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_logs')) {
            delete_transient('twintack_order_debug_log');
            delete_transient('twintack_processed_orders');
            $this->order_hooks_log = array();
            $this->processed_orders = array();
        }
    }
    
    private function activate_debug() {
        set_transient('twintack_order_debug_active', true, 1800); // 30 minutes
        $this->debug_active = true;
        
        // Clear previous logs
        delete_transient('twintack_order_debug_log');
        
        if (WP_DEBUG_LOG) {
            error_log('TWINTACK_ORDER_DEBUG: Order creation debugging activated for 30 minutes');
        }
    }
    
    private function deactivate_debug() {
        delete_transient('twintack_order_debug_active');
        $this->debug_active = false;
        
        if (WP_DEBUG_LOG) {
            error_log('TWINTACK_ORDER_DEBUG: Order creation debugging deactivated');
        }
    }
    
    private function activate_prevention() {
        set_transient('twintack_order_prevention_active', true, 3600); // 1 hour
        $this->prevention_active = true;
        
        if (WP_DEBUG_LOG) {
            error_log('TWINTACK_ORDER_PREVENTION: Order duplication prevention activated for 1 hour');
        }
    }
    
    private function deactivate_prevention() {
        delete_transient('twintack_order_prevention_active');
        $this->prevention_active = false;
        
        if (WP_DEBUG_LOG) {
            error_log('TWINTACK_ORDER_PREVENTION: Order duplication prevention deactivated');
        }
    }
    
    private function is_debug_active() {
        return get_transient('twintack_order_debug_active') || $this->debug_active;
    }
    
    private function is_prevention_active() {
        return get_transient('twintack_order_prevention_active') || $this->prevention_active;
    }
    
    private function hook_into_order_creation() {
        if (!$this->is_debug_active() && !$this->is_prevention_active()) {
            return;
        }
        
        // Debug hooks
        if ($this->is_debug_active()) {
            add_action('woocommerce_checkout_order_processed', array($this, 'log_order_processed'), 1, 1);
            add_action('woocommerce_new_order', array($this, 'log_new_order'), 1, 1);
            add_action('woocommerce_payment_complete', array($this, 'log_payment_complete'), 1, 1);
            add_action('woocommerce_order_status_changed', array($this, 'log_order_status_changed'), 1, 3);
            add_action('woocommerce_create_order', array($this, 'log_create_order'), 1, 1);
            add_action('woocommerce_checkout_create_order', array($this, 'log_checkout_create_order'), 1, 2);
            add_action('woocommerce_update_order', array($this, 'log_wholesale_update_order'), 1, 2);
            add_action('woocommerce_new_order_item', array($this, 'log_new_order_item'), 1, 3);
            add_action('woocommerce_update_order_item', array($this, 'log_update_order_item'), 1, 3);
            add_action('woocommerce_checkout_process', array($this, 'log_checkout_process'), 1);
            add_action('woocommerce_before_checkout_process', array($this, 'log_before_checkout'), 1);
            add_action('woocommerce_after_checkout_process', array($this, 'log_after_checkout'), 1);
            add_action('woocommerce_payment_complete_order_status', array($this, 'log_payment_status'), 1, 2);
            add_action('init', array($this, 'log_registered_hooks'), 999);
        }
        
        // Prevention hooks
        if ($this->is_prevention_active()) {
            add_action('woocommerce_checkout_order_processed', array($this, 'prevent_duplicate_order_processed'), 1, 1);
            add_action('woocommerce_new_order', array($this, 'prevent_duplicate_new_order'), 1, 1);
            add_action('woocommerce_payment_complete', array($this, 'prevent_duplicate_payment_complete'), 1, 1);
            add_action('woocommerce_update_order', array($this, 'prevent_duplicate_update_order'), 1, 2);
            add_action('woocommerce_new_order_item', array($this, 'prevent_duplicate_order_item'), 1, 3);
            add_action('woocommerce_before_checkout_process', array($this, 'set_order_creation_lock'), 1);
            add_action('woocommerce_after_checkout_process', array($this, 'clear_order_creation_lock'), 999);
        }
    }
    
    // Debug logging methods
    private function log_hook_call($hook_name, $data = null, $order_id = null) {
        if (!$this->is_debug_active()) {
            return;
        }
        
        $this->hook_call_count++;
        $timestamp = current_time('Y-m-d H:i:s');
        $backtrace = $this->get_simplified_backtrace();
        
        $log_entry = array(
            'timestamp' => $timestamp,
            'hook_name' => $hook_name,
            'order_id' => $order_id,
            'call_count' => $this->hook_call_count,
            'data' => $data,
            'backtrace' => $backtrace,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        );
        
        $this->order_hooks_log[] = $log_entry;
        
        // Store in transient for persistence
        set_transient('twintack_order_debug_log', $this->order_hooks_log, 3600);
        
        if (WP_DEBUG_LOG) {
            error_log("TWINTACK_ORDER_DEBUG: {$hook_name} - Order ID: {$order_id} - Call #{$this->hook_call_count}");
        }
    }
    
    private function get_simplified_backtrace() {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $simplified = array();
        
        foreach ($backtrace as $trace) {
            if (isset($trace['class']) && isset($trace['function'])) {
                $simplified[] = $trace['class'] . '::' . $trace['function'];
            } elseif (isset($trace['function'])) {
                $simplified[] = $trace['function'];
            }
        }
        
        return array_slice($simplified, 0, 5); // Limit to 5 levels
    }
    
    // Hook callback methods for debugging
    public function log_order_processed($order_id) {
        $this->log_hook_call('woocommerce_checkout_order_processed', null, $order_id);
        $this->current_order_id = $order_id;
    }
    
    public function log_new_order($order_id) {
        $this->log_hook_call('woocommerce_new_order', null, $order_id);
    }
    
    public function log_payment_complete($order_id) {
        $this->log_hook_call('woocommerce_payment_complete', null, $order_id);
    }
    
    public function log_order_status_changed($order_id, $old_status, $new_status) {
        $this->log_hook_call('woocommerce_order_status_changed', array(
            'old_status' => $old_status,
            'new_status' => $new_status
        ), $order_id);
    }
    
    public function log_create_order($order_data) {
        $this->log_hook_call('woocommerce_create_order', $order_data);
    }
    
    public function log_checkout_create_order($order, $data) {
        $this->log_hook_call('woocommerce_checkout_create_order', array(
            'order_id' => $order->get_id(),
            'data_keys' => array_keys($data)
        ), $order->get_id());
    }
    
    public function log_wholesale_update_order($order_id, $order) {
        $this->log_hook_call('woocommerce_update_order (wholesale)', array(
            'order_status' => $order->get_status()
        ), $order_id);
    }
    
    public function log_new_order_item($item_id, $item, $order_id) {
        $this->log_hook_call('woocommerce_new_order_item', array(
            'item_id' => $item_id,
            'product_id' => $item->get_product_id()
        ), $order_id);
    }
    
    public function log_update_order_item($item_id, $item, $order_id) {
        $this->log_hook_call('woocommerce_update_order_item', array(
            'item_id' => $item_id,
            'product_id' => $item->get_product_id()
        ), $order_id);
    }
    
    public function log_checkout_process() {
        $this->log_hook_call('woocommerce_checkout_process');
    }
    
    public function log_before_checkout() {
        $this->log_hook_call('woocommerce_before_checkout_process');
    }
    
    public function log_after_checkout() {
        $this->log_hook_call('woocommerce_after_checkout_process');
    }
    
    public function log_payment_status($status, $order_id) {
        $this->log_hook_call('woocommerce_payment_complete_order_status', array(
            'status' => $status
        ), $order_id);
    }
    
    public function log_registered_hooks() {
        global $wp_filter;
        
        $registered_hooks = array();
        foreach ($this->order_hooks as $hook_name) {
            if (isset($wp_filter[$hook_name])) {
                $callbacks = array();
                foreach ($wp_filter[$hook_name]->callbacks as $priority => $priority_callbacks) {
                    foreach ($priority_callbacks as $callback_id => $callback_data) {
                        $callback_info = $this->get_callback_info($callback_data['function']);
                        $callbacks[] = array(
                            'priority' => $priority,
                            'callback' => $callback_info
                        );
                    }
                }
                $registered_hooks[$hook_name] = $callbacks;
            }
        }
        
        $this->log_hook_call('registered_hooks_analysis', $registered_hooks);
    }
    
    // Prevention methods
    public function prevent_duplicate_order_processed($order_id) {
        if ($this->order_creation_lock || $this->is_order_already_processed($order_id)) {
            if (WP_DEBUG_LOG) {
                error_log("TWINTACK_ORDER_PREVENTION: Blocked duplicate order_processed for order {$order_id}");
            }
            return;
        }
        
        $this->mark_order_processed($order_id, 'order_processed');
        
        if (WP_DEBUG_LOG) {
            error_log("TWINTACK_ORDER_PREVENTION: Allowed order_processed for order {$order_id}");
        }
    }
    
    public function prevent_duplicate_new_order($order_id) {
        if ($this->order_creation_lock || $this->is_order_already_processed($order_id)) {
            if (WP_DEBUG_LOG) {
                error_log("TWINTACK_ORDER_PREVENTION: Blocked duplicate new_order for order {$order_id}");
            }
            return;
        }
        
        $this->mark_order_processed($order_id, 'new_order');
        
        if (WP_DEBUG_LOG) {
            error_log("TWINTACK_ORDER_PREVENTION: Allowed new_order for order {$order_id}");
        }
    }
    
    public function prevent_duplicate_payment_complete($order_id) {
        if ($this->order_creation_lock || $this->is_order_already_processed($order_id)) {
            if (WP_DEBUG_LOG) {
                error_log("TWINTACK_ORDER_PREVENTION: Blocked duplicate payment_complete for order {$order_id}");
            }
            return;
        }
        
        $this->mark_order_processed($order_id, 'payment_complete');
        
        if (WP_DEBUG_LOG) {
            error_log("TWINTACK_ORDER_PREVENTION: Allowed payment_complete for order {$order_id}");
        }
    }
    
    public function prevent_duplicate_update_order($order_id, $order) {
        if ($this->order_creation_lock || $this->is_order_already_processed($order_id)) {
            if (WP_DEBUG_LOG) {
                error_log("TWINTACK_ORDER_PREVENTION: Blocked duplicate update_order for order {$order_id}");
            }
            return;
        }
        
        $this->mark_order_processed($order_id, 'update_order');
        
        if (WP_DEBUG_LOG) {
            error_log("TWINTACK_ORDER_PREVENTION: Allowed update_order for order {$order_id}");
        }
    }
    
    public function prevent_duplicate_order_item($item_id, $item, $order_id) {
        if ($this->order_creation_lock || $this->is_order_already_processed($order_id)) {
            if (WP_DEBUG_LOG) {
                error_log("TWINTACK_ORDER_PREVENTION: Blocked duplicate order_item for order {$order_id}");
            }
            return;
        }
        
        $this->mark_order_processed($order_id, 'order_item');
        
        if (WP_DEBUG_LOG) {
            error_log("TWINTACK_ORDER_PREVENTION: Allowed order_item for order {$order_id}");
        }
    }
    
    public function set_order_creation_lock() {
        $this->order_creation_lock = true;
        
        if (WP_DEBUG_LOG) {
            error_log('TWINTACK_ORDER_PREVENTION: Order creation lock set');
        }
    }
    
    public function clear_order_creation_lock() {
        $this->order_creation_lock = false;
        
        if (WP_DEBUG_LOG) {
            error_log('TWINTACK_ORDER_PREVENTION: Order creation lock cleared');
        }
    }
    
    private function is_order_already_processed($order_id) {
        $processed_orders = get_transient('twintack_processed_orders');
        if (!$processed_orders) {
            $processed_orders = array();
        }
        
        return isset($processed_orders[$order_id]);
    }
    
    private function mark_order_processed($order_id, $hook_type) {
        $processed_orders = get_transient('twintack_processed_orders');
        if (!$processed_orders) {
            $processed_orders = array();
        }
        
        $processed_orders[$order_id] = array(
            'timestamp' => current_time('Y-m-d H:i:s'),
            'hook_type' => $hook_type,
            'user_id' => get_current_user_id()
        );
        
        // Store for 1 hour
        set_transient('twintack_processed_orders', $processed_orders, 3600);
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
    
    public function show_admin_notices() {
        if ($this->is_debug_active()) {
            $screen = get_current_screen();
            if ($screen && in_array($screen->id, ['shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders'])) {
                echo '<div class="notice notice-info">';
                echo '<p><strong>🔍 Order Creation Debugging Active</strong> - All order creation hooks are being logged. <a href="' . admin_url('tools.php?page=twintack-order-debugging-suite') . '">View Debug Log</a></p>';
                echo '</div>';
            }
        }
        
        if ($this->is_prevention_active()) {
            $screen = get_current_screen();
            if ($screen && in_array($screen->id, ['shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders'])) {
                echo '<div class="notice notice-warning">';
                echo '<p><strong>🛡️ Order Duplication Prevention Active</strong> - Duplicate order creation is being blocked. <a href="' . admin_url('tools.php?page=twintack-order-debugging-suite') . '">Manage Settings</a></p>';
                echo '</div>';
            }
        }
    }
    
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        echo '<div class="wrap">';
        echo '<h1>Order Debugging Suite</h1>';
        
        // Handle form submissions
        if (isset($_POST['activate_debug']) && wp_verify_nonce($_POST['_wpnonce'], 'activate_debug')) {
            $this->activate_debug();
            echo '<div class="notice notice-success"><p>Order creation debugging activated for 30 minutes!</p></div>';
        }
        
        if (isset($_POST['deactivate_debug']) && wp_verify_nonce($_POST['_wpnonce'], 'deactivate_debug')) {
            $this->deactivate_debug();
            echo '<div class="notice notice-success"><p>Order creation debugging deactivated!</p></div>';
        }
        
        if (isset($_POST['activate_prevention']) && wp_verify_nonce($_POST['_wpnonce'], 'activate_prevention')) {
            $this->activate_prevention();
            echo '<div class="notice notice-success"><p>Order duplication prevention activated for 1 hour!</p></div>';
        }
        
        if (isset($_POST['deactivate_prevention']) && wp_verify_nonce($_POST['_wpnonce'], 'deactivate_prevention')) {
            $this->deactivate_prevention();
            echo '<div class="notice notice-success"><p>Order duplication prevention deactivated!</p></div>';
        }
        
        if (isset($_POST['clear_logs']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_logs')) {
            delete_transient('twintack_order_debug_log');
            delete_transient('twintack_processed_orders');
            $this->order_hooks_log = array();
            $this->processed_orders = array();
            echo '<div class="notice notice-success"><p>Debug logs cleared!</p></div>';
        }
        
        // Display status
        $this->display_status();
        
        // Display wholesale plugin analysis
        $this->display_wholesale_analysis();
        
        // Display debug logs
        $this->display_debug_logs();
        
        // Display processed orders log
        $this->display_processed_orders();
        
        echo '</div>';
    }
    
    private function display_status() {
        echo '<div class="postbox">';
        echo '<div class="postbox-header"><h2 class="hndle">🔍 Order Debugging Suite Status</h2></div>';
        echo '<div class="inside">';
        
        if ($this->is_debug_active()) {
            echo '<div class="notice notice-success inline">';
            echo '<p><strong>✅ Order Creation Debugging is ACTIVE</strong></p>';
            echo '<p>All order creation hooks are being logged. Place a test order to see the debug information.</p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-info inline">';
            echo '<p><strong>ℹ️ Order Creation Debugging is INACTIVE</strong></p>';
            echo '<p>Activate debugging to monitor order creation hooks.</p>';
            echo '</div>';
        }
        
        if ($this->is_prevention_active()) {
            echo '<div class="notice notice-warning inline">';
            echo '<p><strong>🛡️ Order Duplication Prevention is ACTIVE</strong></p>';
            echo '<p>Duplicate order creation is being blocked. This will prevent the tripling issue.</p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-info inline">';
            echo '<p><strong>ℹ️ Order Duplication Prevention is INACTIVE</strong></p>';
            echo '<p>Activate prevention to block duplicate order creation.</p>';
            echo '</div>';
        }
        
        echo '<h3>Quick Actions:</h3>';
        echo '<form method="post" style="display: inline-block; margin-right: 10px;">';
        wp_nonce_field('activate_debug');
        echo '<button type="submit" name="activate_debug" class="button button-primary button-large">';
        echo '🔍 Activate Debugging (30 min)';
        echo '</button>';
        echo '</form>';
        
        if ($this->is_debug_active()) {
            echo '<form method="post" style="display: inline-block; margin-right: 10px;">';
            wp_nonce_field('deactivate_debug');
            echo '<button type="submit" name="deactivate_debug" class="button button-secondary">';
            echo '⏹️ Deactivate Debugging';
            echo '</button>';
            echo '</form>';
        }
        
        echo '<form method="post" style="display: inline-block; margin-right: 10px;">';
        wp_nonce_field('activate_prevention');
        echo '<button type="submit" name="activate_prevention" class="button button-primary button-large">';
        echo '🛡️ Activate Prevention (1 hour)';
        echo '</button>';
        echo '</form>';
        
        if ($this->is_prevention_active()) {
            echo '<form method="post" style="display: inline-block; margin-right: 10px;">';
            wp_nonce_field('deactivate_prevention');
            echo '<button type="submit" name="deactivate_prevention" class="button button-secondary">';
            echo '⏹️ Deactivate Prevention';
            echo '</button>';
            echo '</form>';
        }
        
        echo '<form method="post" style="display: inline-block;">';
        wp_nonce_field('clear_logs');
        echo '<button type="submit" name="clear_logs" class="button button-secondary">';
        echo '🗑️ Clear All Logs';
        echo '</button>';
        echo '</form>';
        
        echo '</div></div>';
    }
    
    private function display_wholesale_analysis() {
        echo '<div class="postbox">';
        echo '<div class="postbox-header"><h2 class="hndle">🔍 Wholesale Plugin Analysis</h2></div>';
        echo '<div class="inside">';
        
        echo '<h3>Plugin Status</h3>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Plugin</th><th>Status</th><th>Version</th><th>Active Hooks</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($this->wholesale_plugins as $plugin_slug) {
            $plugin_file = $plugin_slug . '/' . $plugin_slug . '.php';
            $is_active = is_plugin_active($plugin_file);
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($plugin_data['Name'] ?? $plugin_slug) . '</strong></td>';
            echo '<td>';
            if ($is_active) {
                echo '<span style="color: green;">✅ Active</span>';
            } else {
                echo '<span style="color: red;">❌ Inactive</span>';
            }
            echo '</td>';
            echo '<td>' . esc_html($plugin_data['Version'] ?? 'Unknown') . '</td>';
            echo '<td>' . $this->count_plugin_hooks($plugin_slug) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        echo '<h3>Hook Conflict Analysis</h3>';
        $this->analyze_hook_conflicts();
        
        echo '</div></div>';
    }
    
    private function count_plugin_hooks($plugin_slug) {
        global $wp_filter;
        $count = 0;
        
        foreach ($this->order_hooks as $hook_name) {
            if (isset($wp_filter[$hook_name])) {
                foreach ($wp_filter[$hook_name]->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback_id => $callback_data) {
                        $callback_info = $this->get_callback_info($callback_data['function']);
                        if (strpos($callback_info, $this->get_plugin_class_prefix($plugin_slug)) !== false) {
                            $count++;
                        }
                    }
                }
            }
        }
        
        return $count;
    }
    
    private function get_plugin_class_prefix($plugin_slug) {
        $prefixes = array(
            'woocommerce-wholesale-lead-capture' => 'WWLC',
            'woocommerce-wholesale-order-form' => 'WWOF',
            'woocommerce-wholesale-payments' => 'WWP',
            'woocommerce-wholesale-prices' => 'WWP',
            'woocommerce-wholesale-prices-premium' => 'WWPP'
        );
        
        return $prefixes[$plugin_slug] ?? '';
    }
    
    private function analyze_hook_conflicts() {
        global $wp_filter;
        
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Hook Name</th><th>Total Callbacks</th><th>Wholesale Callbacks</th><th>Risk Level</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($this->order_hooks as $hook_name) {
            $total_callbacks = 0;
            $wholesale_callbacks = 0;
            
            if (isset($wp_filter[$hook_name])) {
                foreach ($wp_filter[$hook_name]->callbacks as $priority => $callbacks) {
                    $total_callbacks += count($callbacks);
                    
                    foreach ($callbacks as $callback_id => $callback_data) {
                        $callback_info = $this->get_callback_info($callback_data['function']);
                        
                        foreach ($this->wholesale_plugins as $plugin_slug) {
                            $prefix = $this->get_plugin_class_prefix($plugin_slug);
                            if ($prefix && strpos($callback_info, $prefix) !== false) {
                                $wholesale_callbacks++;
                                break;
                            }
                        }
                    }
                }
            }
            
            $risk_level = $wholesale_callbacks > 1 ? 'HIGH' : ($wholesale_callbacks == 1 ? 'MEDIUM' : 'LOW');
            $risk_color = $wholesale_callbacks > 1 ? 'red' : ($wholesale_callbacks == 1 ? 'orange' : 'green');
            
            echo '<tr>';
            echo '<td><code>' . esc_html($hook_name) . '</code></td>';
            echo '<td>' . $total_callbacks . '</td>';
            echo '<td>' . $wholesale_callbacks . '</td>';
            echo '<td><span style="color: ' . $risk_color . '; font-weight: bold;">' . $risk_level . '</span></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    private function display_debug_logs() {
        $logs = get_transient('twintack_order_debug_log');
        
        echo '<div class="postbox">';
        echo '<div class="postbox-header"><h2 class="hndle">📋 Debug Logs</h2></div>';
        echo '<div class="inside">';
        
        if (empty($logs)) {
            echo '<p>No debug logs available. Activate debugging and place a test order to see hook execution details.</p>';
        } else {
            echo '<p><strong>' . count($logs) . ' debug entries</strong></p>';
            
            // Group logs by order ID
            $order_groups = array();
            foreach ($logs as $log) {
                $order_id = $log['order_id'] ?: 'no_order';
                $order_groups[$order_id][] = $log;
            }
            
            foreach ($order_groups as $order_id => $order_logs) {
                echo '<h3>Order ID: ' . esc_html($order_id) . ' (' . count($order_logs) . ' hooks)</h3>';
                echo '<table class="widefat fixed striped">';
                echo '<thead><tr>';
                echo '<th>Time</th><th>Hook</th><th>Call #</th><th>Data</th><th>Backtrace</th>';
                echo '</tr></thead><tbody>';
                
                foreach ($order_logs as $log) {
                    echo '<tr>';
                    echo '<td>' . esc_html($log['timestamp']) . '</td>';
                    echo '<td><code>' . esc_html($log['hook_name']) . '</code></td>';
                    echo '<td>' . esc_html($log['call_count']) . '</td>';
                    echo '<td>';
                    if ($log['data']) {
                        echo '<pre style="font-size: 11px; max-width: 200px; overflow: auto;">';
                        echo esc_html(print_r($log['data'], true));
                        echo '</pre>';
                    }
                    echo '</td>';
                    echo '<td>';
                    if ($log['backtrace']) {
                        echo '<pre style="font-size: 10px; max-width: 300px; overflow: auto;">';
                        echo esc_html(implode("\n", $log['backtrace']));
                        echo '</pre>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table><br>';
            }
        }
        
        echo '</div></div>';
    }
    
    private function display_processed_orders() {
        $processed_orders = get_transient('twintack_processed_orders');
        
        echo '<div class="postbox">';
        echo '<div class="postbox-header"><h2 class="hndle">📋 Processed Orders Log</h2></div>';
        echo '<div class="inside">';
        
        if (empty($processed_orders)) {
            echo '<p>No orders have been processed yet. Activate prevention and place a test order to see the log.</p>';
        } else {
            echo '<p><strong>' . count($processed_orders) . ' orders processed</strong> (log expires in 1 hour)</p>';
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th>Order ID</th><th>Hook Type</th><th>Timestamp</th><th>User ID</th><th>Actions</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($processed_orders as $order_id => $data) {
                echo '<tr>';
                echo '<td><a href="' . admin_url('post.php?post=' . $order_id . '&action=edit') . '">#' . $order_id . '</a></td>';
                echo '<td><code>' . esc_html($data['hook_type']) . '</code></td>';
                echo '<td>' . esc_html($data['timestamp']) . '</td>';
                echo '<td>' . esc_html($data['user_id']) . '</td>';
                echo '<td><a href="' . admin_url('post.php?post=' . $order_id . '&action=edit') . '" class="button button-small">View Order</a></td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div></div>';
    }
}

// Initialize the debugging suite
new TwinTack_Order_Debugging_Suite();

?>
