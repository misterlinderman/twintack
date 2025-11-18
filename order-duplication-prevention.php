<?php
/**
 * Plugin Name: TwinTack Order Duplication Prevention
 * Description: Prevents order duplication caused by wholesale plugin conflicts
 * Version: 1.0.0
 * Author: TwinTack
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Order_Duplication_Prevention {
    
    private $prevention_active = false;
    private $processed_orders = array();
    private $order_creation_lock = false;
    
    public function __construct() {
        // Add admin interface
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add prevention functionality
        add_action('admin_init', array($this, 'maybe_activate_prevention'));
        
        // Add admin notice
        add_action('admin_notices', array($this, 'show_prevention_notice'));
        
        // Hook into order creation process
        $this->hook_into_order_creation();
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'tools.php',
            'Order Duplication Prevention',
            'Order Duplication Prevention',
            'manage_options',
            'twintack-order-prevention',
            array($this, 'admin_page')
        );
    }
    
    public function maybe_activate_prevention() {
        // Check if prevention is requested
        if (isset($_GET['twintack_prevent']) && $_GET['twintack_prevent'] === '1') {
            $this->activate_prevention();
        }
        
        // Check if prevention should be deactivated
        if (isset($_GET['twintack_prevent']) && $_GET['twintack_prevent'] === '0') {
            $this->deactivate_prevention();
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
    
    private function is_prevention_active() {
        return get_transient('twintack_order_prevention_active') || $this->prevention_active;
    }
    
    private function hook_into_order_creation() {
        if (!$this->is_prevention_active()) {
            return;
        }
        
        // Hook into order creation with high priority to prevent duplicates
        add_action('woocommerce_checkout_order_processed', array($this, 'prevent_duplicate_order_processed'), 1, 1);
        add_action('woocommerce_new_order', array($this, 'prevent_duplicate_new_order'), 1, 1);
        add_action('woocommerce_payment_complete', array($this, 'prevent_duplicate_payment_complete'), 1, 1);
        
        // Hook into wholesale plugin order processing
        add_action('woocommerce_update_order', array($this, 'prevent_duplicate_update_order'), 1, 2);
        add_action('woocommerce_new_order_item', array($this, 'prevent_duplicate_order_item'), 1, 3);
        
        // Add order creation lock mechanism
        add_action('woocommerce_before_checkout_process', array($this, 'set_order_creation_lock'), 1);
        add_action('woocommerce_after_checkout_process', array($this, 'clear_order_creation_lock'), 999);
    }
    
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
    
    public function show_prevention_notice() {
        if ($this->is_prevention_active()) {
            $screen = get_current_screen();
            if ($screen && in_array($screen->id, ['shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders'])) {
                echo '<div class="notice notice-warning">';
                echo '<p><strong>🛡️ Order Duplication Prevention Active</strong> - Duplicate order creation is being blocked. <a href="' . admin_url('tools.php?page=twintack-order-prevention') . '">Manage Settings</a></p>';
                echo '</div>';
            }
        }
    }
    
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        echo '<div class="wrap">';
        echo '<h1>Order Duplication Prevention</h1>';
        
        if (isset($_POST['activate_prevention']) && wp_verify_nonce($_POST['_wpnonce'], 'activate_prevention')) {
            $this->activate_prevention();
            echo '<div class="notice notice-success"><p>Order duplication prevention activated for 1 hour!</p></div>';
        }
        
        if (isset($_POST['deactivate_prevention']) && wp_verify_nonce($_POST['_wpnonce'], 'deactivate_prevention')) {
            $this->deactivate_prevention();
            echo '<div class="notice notice-success"><p>Order duplication prevention deactivated!</p></div>';
        }
        
        if (isset($_POST['clear_processed_orders']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_processed_orders')) {
            delete_transient('twintack_processed_orders');
            echo '<div class="notice notice-success"><p>Processed orders log cleared!</p></div>';
        }
        
        echo '<div class="postbox">';
        echo '<div class="postbox-header"><h2 class="hndle">🛡️ Order Duplication Prevention</h2></div>';
        echo '<div class="inside">';
        
        if ($this->is_prevention_active()) {
            echo '<div class="notice notice-success inline">';
            echo '<p><strong>✅ Prevention is currently ACTIVE</strong></p>';
            echo '<p>Duplicate order creation is being blocked. This will prevent the tripling issue.</p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-info inline">';
            echo '<p><strong>ℹ️ Prevention is currently INACTIVE</strong></p>';
            echo '<p>Activate prevention to block duplicate order creation.</p>';
            echo '</div>';
        }
        
        echo '<h3>How It Works:</h3>';
        echo '<ol>';
        echo '<li><strong>Order Creation Lock:</strong> Prevents multiple order creation hooks from firing simultaneously</li>';
        echo '<li><strong>Duplicate Detection:</strong> Tracks processed orders to prevent duplicate processing</li>';
        echo '<li><strong>Hook Interception:</strong> Blocks wholesale plugin hooks that cause order duplication</li>';
        echo '<li><strong>Temporary Solution:</strong> Active for 1 hour to allow testing and troubleshooting</li>';
        echo '</ol>';
        
        echo '<h3>⚠️ Important Notes:</h3>';
        echo '<ul>';
        echo '<li><strong>This is a temporary fix</strong> - Use while troubleshooting the root cause</li>';
        echo '<li><strong>May affect wholesale functionality</strong> - Some wholesale features may not work correctly</li>';
        echo '<li><strong>Monitor closely</strong> - Watch for any issues with order processing</li>';
        echo '<li><strong>Use with debugging</strong> - Combine with Order Creation Debugger for analysis</li>';
        echo '</ul>';
        
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
        wp_nonce_field('clear_processed_orders');
        echo '<button type="submit" name="clear_processed_orders" class="button button-secondary">';
        echo '🗑️ Clear Processed Orders Log';
        echo '</button>';
        echo '</form>';
        
        echo '</div></div>';
        
        // Display processed orders log
        $this->display_processed_orders();
        
        echo '</div>';
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

// Initialize the prevention system
new TwinTack_Order_Duplication_Prevention();

?>
