<?php
/**
 * Plugin Name: TwinTack Order Creation Debugger
 * Description: Comprehensive debugging tool to identify order duplication issues
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

class TwinTack_Order_Creation_Debugger {
    
    private $debug_active = false;
    private $order_hooks_log = array();
    private $current_order_id = null;
    private $hook_call_count = 0;
    
    public function __construct() {
        // Add admin interface
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add debug functionality
        add_action('admin_init', array($this, 'maybe_activate_debug'));
        
        // Add admin notice
        add_action('admin_notices', array($this, 'show_debug_notice'));
        
        // Hook into order creation process
        $this->hook_into_order_creation();
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'Order Creation Debugger',
            'Order Creation Debugger',
            'manage_options',
            'twintack-order-debug',
            array($this, 'admin_page')
        );
    }
    
    public function maybe_activate_debug() {
        // Check if debug is requested
        if (isset($_GET['twintack_debug']) && $_GET['twintack_debug'] === '1') {
            $this->activate_debug();
        }
        
        // Check if debug should be deactivated
        if (isset($_GET['twintack_debug']) && $_GET['twintack_debug'] === '0') {
            $this->deactivate_debug();
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
    
    private function is_debug_active() {
        return get_transient('twintack_order_debug_active') || $this->debug_active;
    }
    
    private function hook_into_order_creation() {
        if (!$this->is_debug_active()) {
            return;
        }
        
        // Hook into all order creation events
        add_action('woocommerce_checkout_order_processed', array($this, 'log_order_processed'), 1, 1);
        add_action('woocommerce_new_order', array($this, 'log_new_order'), 1, 1);
        add_action('woocommerce_payment_complete', array($this, 'log_payment_complete'), 1, 1);
        add_action('woocommerce_order_status_changed', array($this, 'log_order_status_changed'), 1, 3);
        
        // Hook into order creation hooks
        add_action('woocommerce_create_order', array($this, 'log_create_order'), 1, 1);
        add_action('woocommerce_checkout_create_order', array($this, 'log_checkout_create_order'), 1, 2);
        
        // Hook into wholesale plugin order hooks
        add_action('woocommerce_update_order', array($this, 'log_wholesale_update_order'), 1, 2);
        add_action('woocommerce_new_order_item', array($this, 'log_new_order_item'), 1, 3);
        add_action('woocommerce_update_order_item', array($this, 'log_update_order_item'), 1, 3);
        
        // Hook into cart and checkout process
        add_action('woocommerce_checkout_process', array($this, 'log_checkout_process'), 1);
        add_action('woocommerce_before_checkout_process', array($this, 'log_before_checkout'), 1);
        add_action('woocommerce_after_checkout_process', array($this, 'log_after_checkout'), 1);
        
        // Hook into payment gateway
        add_action('woocommerce_payment_complete_order_status', array($this, 'log_payment_status'), 1, 2);
        
        // Log all hook registrations
        add_action('init', array($this, 'log_registered_hooks'), 999);
    }
    
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
    
    // Hook callback methods
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
        
        $order_hooks = array(
            'woocommerce_checkout_order_processed',
            'woocommerce_new_order',
            'woocommerce_payment_complete',
            'woocommerce_order_status_changed',
            'woocommerce_create_order',
            'woocommerce_checkout_create_order',
            'woocommerce_update_order',
            'woocommerce_new_order_item',
            'woocommerce_update_order_item'
        );
        
        $registered_hooks = array();
        foreach ($order_hooks as $hook_name) {
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
    
    public function show_debug_notice() {
        if ($this->is_debug_active()) {
            $screen = get_current_screen();
            if ($screen && in_array($screen->id, ['shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders'])) {
                echo '<div class="notice notice-info">';
                echo '<p><strong>🔍 Order Creation Debugging Active</strong> - All order creation hooks are being logged. <a href="' . admin_url('tools.php?page=twintack-order-debug') . '">View Debug Log</a></p>';
                echo '</div>';
            }
        }
    }
    
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        echo '<div class="wrap">';
        echo '<h1>Order Creation Debugger</h1>';
        
        if (isset($_POST['activate_debug']) && wp_verify_nonce($_POST['_wpnonce'], 'activate_debug')) {
            $this->activate_debug();
            echo '<div class="notice notice-success"><p>Order creation debugging activated for 30 minutes!</p></div>';
        }
        
        if (isset($_POST['deactivate_debug']) && wp_verify_nonce($_POST['_wpnonce'], 'deactivate_debug')) {
            $this->deactivate_debug();
            echo '<div class="notice notice-success"><p>Order creation debugging deactivated!</p></div>';
        }
        
        if (isset($_POST['clear_logs']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_logs')) {
            delete_transient('twintack_order_debug_log');
            $this->order_hooks_log = array();
            echo '<div class="notice notice-success"><p>Debug logs cleared!</p></div>';
        }
        
        echo '<div class="postbox">';
        echo '<div class="postbox-header"><h2 class="hndle">🔍 Order Creation Debugging</h2></div>';
        echo '<div class="inside">';
        
        if ($this->is_debug_active()) {
            echo '<div class="notice notice-success inline">';
            echo '<p><strong>✅ Debugging is currently ACTIVE</strong></p>';
            echo '<p>All order creation hooks are being logged. Place a test order to see the debug information.</p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-info inline">';
            echo '<p><strong>ℹ️ Debugging is currently INACTIVE</strong></p>';
            echo '<p>Activate debugging to monitor order creation hooks.</p>';
            echo '</div>';
        }
        
        echo '<h3>How to Use:</h3>';
        echo '<ol>';
        echo '<li><strong>Activate debugging:</strong> Click the activate button below</li>';
        echo '<li><strong>Place a test order:</strong> Go to your store and complete a test purchase</li>';
        echo '<li><strong>View results:</strong> Return here to see detailed hook execution logs</li>';
        echo '<li><strong>Analyze patterns:</strong> Look for duplicate hook calls or unexpected order creation</li>';
        echo '</ol>';
        
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
        
        echo '<form method="post" style="display: inline-block;">';
        wp_nonce_field('clear_logs');
        echo '<button type="submit" name="clear_logs" class="button button-secondary">';
        echo '🗑️ Clear Logs';
        echo '</button>';
        echo '</form>';
        
        echo '</div></div>';
        
        // Display debug logs
        $this->display_debug_logs();
        
        echo '</div>';
    }
    
    private function display_debug_logs() {
        $logs = get_transient('twintack_order_debug_log');
        if (empty($logs)) {
            echo '<div class="postbox">';
            echo '<div class="postbox-header"><h2 class="hndle">📋 Debug Logs</h2></div>';
            echo '<div class="inside">';
            echo '<p>No debug logs available. Activate debugging and place a test order to see hook execution details.</p>';
            echo '</div></div>';
            return;
        }
        
        echo '<div class="postbox">';
        echo '<div class="postbox-header"><h2 class="hndle">📋 Debug Logs (' . count($logs) . ' entries)</h2></div>';
        echo '<div class="inside">';
        
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
        
        echo '</div></div>';
    }
}

// Initialize the debugger
new TwinTack_Order_Creation_Debugger();

?>
