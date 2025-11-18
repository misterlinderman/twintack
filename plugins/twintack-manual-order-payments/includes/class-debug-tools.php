<?php
/**
 * TwinTack Debug Tools
 * 
 * Debugging utilities for troubleshooting invoice system issues
 * 
 * @package TwinTack_Manual_Order_Payments
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Debug_Tools {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add debug tools to admin menu
        add_action('admin_menu', array($this, 'add_debug_menu'));
        
        // AJAX handlers for debug functions
        add_action('wp_ajax_twintack_debug_email_test', array($this, 'test_email_functionality'));
        add_action('wp_ajax_twintack_debug_order_status', array($this, 'debug_order_status'));
        add_action('wp_ajax_twintack_debug_shippo_sync', array($this, 'debug_shippo_sync'));
        add_action('wp_ajax_twintack_force_shippo_sync', array($this, 'force_shippo_sync'));
    }
    
    /**
     * Add debug menu to admin
     */
    public function add_debug_menu() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        add_submenu_page(
            'woocommerce',
            'TwinTack Debug Tools',
            'TwinTack Debug',
            'manage_options',
            'twintack-debug',
            array($this, 'render_debug_page')
        );
    }
    
    /**
     * Render debug page
     */
    public function render_debug_page() {
        ?>
        <div class="wrap">
            <h1>TwinTack Manual Payments Debug Tools</h1>
            
            <div id="debug-results" style="margin: 20px 0;"></div>
            
            <div class="card" style="max-width: none;">
                <h2>Email Testing</h2>
                <p>Test email functionality to debug delivery issues:</p>
                <table class="form-table">
                    <tr>
                        <th><label for="test-email">Test Email Address:</label></th>
                        <td>
                            <input type="email" id="test-email" class="regular-text" placeholder="test@example.com" />
                            <button type="button" class="button button-primary" onclick="testEmailFunction()">Send Test Email</button>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="test-order-id">Order ID for Invoice Test:</label></th>
                        <td>
                            <input type="number" id="test-order-id" class="regular-text" placeholder="1569" />
                            <button type="button" class="button" onclick="testInvoiceEmail()">Send Test Invoice Email</button>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card" style="max-width: none;">
                <h2>Order Status Debugging</h2>
                <p>Debug order visibility and status issues:</p>
                <table class="form-table">
                    <tr>
                        <th>Invoiced Orders:</th>
                        <td>
                            <button type="button" class="button" onclick="debugOrderStatus()">Check Invoiced Orders Visibility</button>
                            <p class="description">Check if invoiced orders are properly registered and visible in admin lists.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Order Status List:</th>
                        <td>
                            <?php
                            $order_statuses = wc_get_order_statuses();
                            echo '<ul>';
                            foreach ($order_statuses as $status_key => $status_label) {
                                echo '<li><code>' . esc_html($status_key) . '</code> → ' . esc_html($status_label) . '</li>';
                            }
                            echo '</ul>';
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card" style="max-width: none;">
                <h2>Shippo Integration</h2>
                <p>Debug Shippo fulfillment status mapping:</p>
                <table class="form-table">
                    <tr>
                        <th><label for="shippo-order-id">Order ID for Shippo Sync:</label></th>
                        <td>
                            <input type="number" id="shippo-order-id" class="regular-text" placeholder="1569" />
                            <button type="button" class="button" onclick="debugShippoSync()">Debug Shippo Status</button>
                            <button type="button" class="button button-primary" onclick="forceShippoSync()">Force Shippo Sync</button>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card" style="max-width: none;">
                <h2>System Information</h2>
                <table class="form-table">
                    <tr>
                        <th>WordPress Mail Function:</th>
                        <td><?php echo function_exists('wp_mail') ? '✅ Available' : '❌ Not Available'; ?></td>
                    </tr>
                    <tr>
                        <th>WooCommerce Version:</th>
                        <td><?php echo defined('WC_VERSION') ? WC_VERSION : 'Not Available'; ?></td>
                    </tr>
                    <tr>
                        <th>TwinTack Plugin Version:</th>
                        <td><?php echo TWINTACK_MANUAL_PAYMENTS_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th>Debug Mode:</th>
                        <td><?php echo TwinTack_Manual_Order_Payments::get_option('debug_mode', 'no') === 'yes' ? '✅ Enabled' : '❌ Disabled'; ?></td>
                    </tr>
                    <tr>
                        <th>Admin Email:</th>
                        <td><?php echo get_option('admin_email'); ?></td>
                    </tr>
                    <tr>
                        <th>Shippo Integration:</th>
                        <td><?php echo class_exists('TwinTack_Shippo_Integration') ? '✅ Loaded' : '❌ Not Loaded'; ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="card" style="max-width: none;">
                <h2>Recent Debug Logs</h2>
                <div id="debug-logs">
                    <?php $this->display_recent_logs(); ?>
                </div>
                <button type="button" class="button" onclick="refreshLogs()">Refresh Logs</button>
            </div>
        </div>
        
        <script>
        function showDebugResult(message, type) {
            const resultDiv = document.getElementById('debug-results');
            const alertClass = type === 'error' ? 'notice-error' : 'notice-success';
            resultDiv.innerHTML = '<div class="notice ' + alertClass + ' is-dismissible"><p>' + message + '</p></div>';
        }
        
        function testEmailFunction() {
            const email = document.getElementById('test-email').value;
            if (!email) {
                alert('Please enter an email address');
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'twintack_debug_email_test',
                email: email,
                nonce: '<?php echo wp_create_nonce('twintack_debug'); ?>'
            }, function(response) {
                if (response.success) {
                    showDebugResult('✅ Test email sent successfully to ' + email, 'success');
                } else {
                    showDebugResult('❌ Email test failed: ' + response.data.message, 'error');
                }
            });
        }
        
        function testInvoiceEmail() {
            const orderId = document.getElementById('test-order-id').value;
            if (!orderId) {
                alert('Please enter an order ID');
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'twintack_debug_email_test',
                order_id: orderId,
                test_type: 'invoice',
                nonce: '<?php echo wp_create_nonce('twintack_debug'); ?>'
            }, function(response) {
                if (response.success) {
                    showDebugResult('✅ Invoice email test completed for order ' + orderId, 'success');
                } else {
                    showDebugResult('❌ Invoice email test failed: ' + response.data.message, 'error');
                }
            });
        }
        
        function debugOrderStatus() {
            jQuery.post(ajaxurl, {
                action: 'twintack_debug_order_status',
                nonce: '<?php echo wp_create_nonce('twintack_debug'); ?>'
            }, function(response) {
                if (response.success) {
                    showDebugResult(response.data.message, 'success');
                } else {
                    showDebugResult('❌ Order status debug failed: ' + response.data.message, 'error');
                }
            });
        }
        
        function debugShippoSync() {
            const orderId = document.getElementById('shippo-order-id').value;
            if (!orderId) {
                alert('Please enter an order ID');
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'twintack_debug_shippo_sync',
                order_id: orderId,
                nonce: '<?php echo wp_create_nonce('twintack_debug'); ?>'
            }, function(response) {
                if (response.success) {
                    showDebugResult(response.data.message, 'success');
                } else {
                    showDebugResult('❌ Shippo debug failed: ' + response.data.message, 'error');
                }
            });
        }
        
        function forceShippoSync() {
            const orderId = document.getElementById('shippo-order-id').value;
            if (!orderId) {
                alert('Please enter an order ID');
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'twintack_force_shippo_sync',
                order_id: orderId,
                nonce: '<?php echo wp_create_nonce('twintack_payment_processing'); ?>'
            }, function(response) {
                if (response.success) {
                    showDebugResult('✅ Shippo sync completed for order ' + orderId, 'success');
                } else {
                    showDebugResult('❌ Shippo sync failed: ' + response.data.message, 'error');
                }
            });
        }
        
        function refreshLogs() {
            location.reload();
        }
        </script>
        <?php
    }
    
    /**
     * Test email functionality
     */
    public function test_email_functionality() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'twintack_debug')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $test_type = isset($_POST['test_type']) ? sanitize_text_field($_POST['test_type']) : 'basic';
        
        if ($test_type === 'invoice') {
            $order_id = intval($_POST['order_id']);
            $order = wc_get_order($order_id);
            
            if (!$order) {
                wp_send_json_error(array('message' => 'Order not found'));
            }
            
            // Test invoice email
            $customer_email = $order->get_billing_email();
            $result = $this->send_test_invoice_email($order, $customer_email);
            
            if ($result) {
                wp_send_json_success(array('message' => "Invoice email test sent to {$customer_email}"));
            } else {
                wp_send_json_error(array('message' => 'Invoice email test failed'));
            }
        } else {
            // Basic email test
            $email = sanitize_email($_POST['email']);
            if (!is_email($email)) {
                wp_send_json_error(array('message' => 'Invalid email address'));
            }
            
            $subject = 'TwinTack Email Test - ' . date('Y-m-d H:i:s');
            $message = "This is a test email from TwinTack Manual Order Payments plugin.\n\nTime: " . date('Y-m-d H:i:s') . "\nSite: " . get_bloginfo('name');
            
            $result = wp_mail($email, $subject, $message);
            
            if ($result) {
                wp_send_json_success(array('message' => "Test email sent successfully to {$email}"));
            } else {
                global $phpmailer;
                $error = isset($phpmailer) ? $phpmailer->ErrorInfo : 'Unknown error';
                wp_send_json_error(array('message' => "Email test failed: {$error}"));
            }
        }
    }
    
    /**
     * Send test invoice email
     */
    private function send_test_invoice_email($order, $email) {
        $subject = 'TEST - Invoice for Order #' . $order->get_order_number();
        $message = "TEST EMAIL - This is a test of the invoice email system.\n\n";
        $message .= "Order: #" . $order->get_order_number() . "\n";
        $message .= "Total: " . wc_price($order->get_total()) . "\n";
        $message .= "Customer: " . $order->get_billing_first_name() . " " . $order->get_billing_last_name() . "\n";
        $message .= "Status: " . $order->get_status() . "\n";
        $message .= "\nThis is a test message. No payment is required.";
        
        return wp_mail($email, $subject, $message);
    }
    
    /**
     * Debug order status
     */
    public function debug_order_status() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'twintack_debug')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        global $wpdb;
        
        // Check invoiced orders
        $invoiced_count = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_type = 'shop_order' 
            AND post_status = 'wc-invoiced'
        ");
        
        // Get all order statuses
        $order_statuses = wc_get_order_statuses();
        $has_invoiced = isset($order_statuses['wc-invoiced']);
        
        // Check recent orders
        $recent_orders = $wpdb->get_results("
            SELECT ID, post_status, post_date 
            FROM {$wpdb->posts} 
            WHERE post_type = 'shop_order' 
            ORDER BY post_date DESC 
            LIMIT 10
        ");
        
        $message = "🔍 Order Status Debug Results:<br/><br/>";
        $message .= "📊 Invoiced Orders Count: {$invoiced_count}<br/>";
        $message .= "✅ Invoiced Status Registered: " . ($has_invoiced ? 'Yes' : 'No') . "<br/>";
        $message .= "📝 Total Order Statuses: " . count($order_statuses) . "<br/><br/>";
        
        $message .= "📋 Recent Orders:<br/>";
        foreach ($recent_orders as $order) {
            $message .= "Order #{$order->ID}: {$order->post_status} ({$order->post_date})<br/>";
        }
        
        wp_send_json_success(array('message' => $message));
    }
    
    /**
     * Debug Shippo sync
     */
    public function debug_shippo_sync() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'twintack_debug')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        $shippo_status = $order->get_meta('_shippo_fulfillment_status');
        $sync_timestamp = $order->get_meta('_shippo_sync_timestamp');
        $fulfillment_hold = $order->get_meta('_shippo_fulfillment_hold');
        
        $message = "🚢 Shippo Debug for Order #{$order_id}:<br/><br/>";
        $message .= "📦 WooCommerce Status: " . $order->get_status() . "<br/>";
        $message .= "🎯 Shippo Status: " . ($shippo_status ?: 'Not Set') . "<br/>";
        $message .= "⏰ Last Sync: " . ($sync_timestamp ? date('Y-m-d H:i:s', $sync_timestamp) : 'Never') . "<br/>";
        $message .= "🚫 Fulfillment Hold: " . ($fulfillment_hold === 'yes' ? 'Yes' : 'No') . "<br/>";
        
        if ($fulfillment_hold === 'yes') {
            $hold_reason = $order->get_meta('_shippo_hold_reason');
            $message .= "📝 Hold Reason: " . ($hold_reason ?: 'Not specified') . "<br/>";
        }
        
        wp_send_json_success(array('message' => $message));
    }
    
    /**
     * Force Shippo sync
     */
    public function force_shippo_sync() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'twintack_payment_processing')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        // Trigger manual sync
        if (class_exists('TwinTack_Shippo_Integration')) {
            $shippo_integration = TwinTack_Shippo_Integration::get_instance();
            $shippo_integration->sync_order_with_shippo($order_id, '', $order->get_status(), $order);
            
            wp_send_json_success(array('message' => 'Shippo sync completed successfully'));
        } else {
            wp_send_json_error(array('message' => 'Shippo integration not available'));
        }
    }
    
    /**
     * Display recent debug logs
     */
    private function display_recent_logs() {
        if (TwinTack_Manual_Order_Payments::get_option('debug_mode', 'no') !== 'yes') {
            echo '<p><em>Debug mode is disabled. Enable it in plugin settings to see logs.</em></p>';
            return;
        }
        
        // Try to read WooCommerce logs
        $log_files = glob(WC_LOG_DIR . 'twintack-manual-payments-*.log');
        
        if (empty($log_files)) {
            echo '<p><em>No log files found.</em></p>';
            return;
        }
        
        // Get the most recent log file
        $latest_log = array_slice($log_files, -1)[0];
        $log_content = file_get_contents($latest_log);
        
        if (empty($log_content)) {
            echo '<p><em>Log file is empty.</em></p>';
            return;
        }
        
        // Show last 20 lines
        $lines = explode("\n", $log_content);
        $recent_lines = array_slice($lines, -20);
        
        echo '<pre style="background: #f1f1f1; padding: 10px; max-height: 300px; overflow-y: auto;">';
        echo esc_html(implode("\n", $recent_lines));
        echo '</pre>';
    }
} 