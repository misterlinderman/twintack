<?php
/**
 * Shippo Sync Admin Page
 * 
 * Provides an admin interface for syncing Shippo status to WooCommerce orders
 */

if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Shippo_Sync_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_twintack_simulate_shippo_webhooks', array($this, 'ajax_simulate_webhooks'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Shippo Sync',
            'Shippo Sync',
            'manage_options',
            'twintack-shippo-sync',
            array($this, 'admin_page')
        );
    }
    
    public function enqueue_scripts($hook) {
        if ('woocommerce_page_twintack-shippo-sync' !== $hook) {
            return;
        }
        
        wp_enqueue_script('jquery');
        
        // Inline script for AJAX
        $script = "
        jQuery(document).ready(function($) {
            $('#simulate-webhooks-btn').on('click', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var progressBar = $('#progress-bar');
                var progressFill = $('#progress-fill');
                var progressText = $('#progress-text');
                var resultsDiv = $('#sync-results');
                
                button.prop('disabled', true).text('Processing...');
                progressBar.show();
                resultsDiv.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'twintack_simulate_shippo_webhooks',
                        nonce: '" . wp_create_nonce('twintack_shippo_sync') . "'
                    },
                    success: function(response) {
                        if (response.success) {
                            progressFill.css('width', '100%');
                            progressText.text('Sync completed!');
                            resultsDiv.html(response.data.html);
                        } else {
                            resultsDiv.html('<div class=\"notice notice-error\"><p>Error: ' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        resultsDiv.html('<div class=\"notice notice-error\"><p>AJAX request failed</p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('🔄 Simulate Shippo Webhooks');
                        setTimeout(function() {
                            progressBar.hide();
                        }, 2000);
                    }
                });
            });
        });
        ";
        
        wp_add_inline_script('jquery', $script);
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>🔄 Shippo Sync Tool</h1>
            
            <div class="notice notice-info">
                <p><strong>Purpose:</strong> This tool syncs orders that have been shipped in Shippo but are still showing as "Invoiced" in WooCommerce.</p>
                <p>It will update them to "Shipped (Unpaid)" status and send tracking notifications to customers.</p>
            </div>
            
            <?php $this->show_overview(); ?>
            
            <div class="card">
                <h2>🚀 Run Sync</h2>
                <p>This will simulate Shippo webhooks for eligible orders to trigger the proper status updates.</p>
                
                <div id="progress-bar" style="display: none; background: #e0e0e0; border-radius: 3px; overflow: hidden; margin: 10px 0;">
                    <div id="progress-fill" style="background: #0073aa; height: 20px; width: 0%; transition: width 0.3s;"></div>
                </div>
                <div id="progress-text" style="margin: 10px 0;"></div>
                
                <button id="simulate-webhooks-btn" class="button button-primary button-large">
                    🔄 Simulate Shippo Webhooks
                </button>
                
                <div id="sync-results" style="margin-top: 20px;"></div>
            </div>
        </div>
        
        <style>
            .card { background: white; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin: 20px 0; }
            .order-item { background: #f9f9f9; padding: 10px; margin: 5px 0; border-left: 4px solid #0073aa; }
            .processed { background: #d4f6d4; border-left-color: #28a745; }
            .error-item { background: #f8d7da; border-left-color: #dc3545; }
        </style>
        <?php
    }
    
    private function show_overview() {
        // Get invoiced orders that can be synced
        $invoiced_orders = wc_get_orders(array(
            'status' => 'invoiced',
            'limit' => 50,
            'meta_query' => array(
                array(
                    'key' => '_shippo_order_id',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        $eligible_orders = array();
        $total_invoiced = count($invoiced_orders);
        
        foreach ($invoiced_orders as $order) {
            $days_old = round((time() - $order->get_date_created()->getTimestamp()) / DAY_IN_SECONDS, 1);
            if ($days_old >= 1) { // Orders 1 day or older
                $eligible_orders[] = $order;
            }
        }
        
        ?>
        <div class="card">
            <h2>📊 Current Status</h2>
            <table class="wp-list-table widefat fixed striped">
                <tbody>
                    <tr>
                        <td><strong>Total Invoiced Orders:</strong></td>
                        <td><?php echo $total_invoiced; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Eligible for Sync:</strong></td>
                        <td><span style="color: green; font-weight: bold;"><?php echo count($eligible_orders); ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Criteria:</strong></td>
                        <td>Invoiced status + Has Shippo Order ID + 1 day or older</td>
                    </tr>
                </tbody>
            </table>
            
            <?php if (!empty($eligible_orders)): ?>
            <h3>📋 Orders Ready for Sync:</h3>
            <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                <?php foreach (array_slice($eligible_orders, 0, 10) as $order): ?>
                <div class="order-item">
                    <strong>Order #<?php echo $order->get_id(); ?></strong> - 
                    <?php echo $order->get_billing_email(); ?><br>
                    <small>
                        Shippo ID: <?php echo $order->get_meta('_shippo_order_id'); ?> | 
                        Age: <?php echo round((time() - $order->get_date_created()->getTimestamp()) / DAY_IN_SECONDS, 1); ?> days
                    </small>
                </div>
                <?php endforeach; ?>
                
                <?php if (count($eligible_orders) > 10): ?>
                <p><em>... and <?php echo count($eligible_orders) - 10; ?> more orders</em></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function ajax_simulate_webhooks() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_shippo_sync')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        try {
            $result = $this->perform_webhook_simulation();
            wp_send_json_success(array('html' => $result));
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    private function perform_webhook_simulation() {
        // Check if webhook handler is available
        if (!class_exists('TwinTack_Shippo_Webhook_Handler')) {
            return '<div class="notice notice-error"><p>❌ Shippo Webhook Handler class not found.</p></div>';
        }
        
        // Get all invoiced orders with Shippo IDs first
        $all_invoiced_orders = wc_get_orders(array(
            'status' => 'invoiced',
            'limit' => 50,
            'meta_query' => array(
                array(
                    'key' => '_shippo_order_id',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        // Filter to eligible orders (older than 1 day)
        $target_orders = array();
        foreach ($all_invoiced_orders as $order) {
            $days_old = round((time() - $order->get_date_created()->getTimestamp()) / DAY_IN_SECONDS, 1);
            if ($days_old >= 1) {
                $target_orders[] = $order;
            }
        }
        
        if (empty($target_orders)) {
            $debug_info = '<div class="notice notice-warning">';
            $debug_info .= '<p>⚠️ No eligible orders found for webhook simulation.</p>';
            $debug_info .= '<p><strong>Debug Info:</strong></p>';
            $debug_info .= '<p>Total invoiced orders found: ' . count($all_invoiced_orders) . '</p>';
            
            if (!empty($all_invoiced_orders)) {
                $debug_info .= '<p>Order ages:</p><ul>';
                foreach ($all_invoiced_orders as $order) {
                    $days_old = round((time() - $order->get_date_created()->getTimestamp()) / DAY_IN_SECONDS, 1);
                    $debug_info .= '<li>Order #' . $order->get_id() . ': ' . $days_old . ' days old</li>';
                }
                $debug_info .= '</ul>';
                $debug_info .= '<p><em>Orders need to be 1 day or older to be eligible for sync.</em></p>';
            }
            
            $debug_info .= '</div>';
            return $debug_info;
        }
        
        $webhook_handler = TwinTack_Shippo_Webhook_Handler::get_instance();
        $success_count = 0;
        $error_count = 0;
        $output = '<h3>Processing ' . count($target_orders) . ' orders...</h3>';
        
        foreach ($target_orders as $order) {
            $order_id = $order->get_id();
            $shippo_order_id = $order->get_meta('_shippo_order_id');
            
            try {
                // Simulate a "shipment updated" webhook with SUCCESS status
                $simulated_webhook_data = array(
                    'data' => array(
                        'object' => array(
                            'tracking_number' => 'SYNC_' . strtoupper(substr(md5($order_id . time()), 0, 12)),
                            'status' => 'SUCCESS',
                            'carrier' => 'USPS',
                            'metadata' => array(
                                'wc_order_id' => $order_id
                            )
                        )
                    )
                );
                
                // Use reflection to call the private method safely
                $reflection = new ReflectionClass($webhook_handler);
                $method = $reflection->getMethod('handle_shipment_updated');
                $method->setAccessible(true);
                
                $result = $method->invoke($webhook_handler, $simulated_webhook_data);
                
                // Refresh order object to check new status
                $order = wc_get_order($order_id);
                $new_status = $order->get_status();
                
                if ($new_status === 'shipped-unpaid') {
                    $output .= '<div class="order-item processed">';
                    $output .= '<strong>✅ Order #' . $order_id . '</strong><br>';
                    $output .= 'Status: Invoiced → Shipped (Unpaid)<br>';
                    $output .= 'Customer: ' . $order->get_billing_email() . '<br>';
                    $output .= 'Tracking added & shipment email sent';
                    $output .= '</div>';
                    $success_count++;
                } else {
                    $output .= '<div class="order-item">';
                    $output .= '<strong>⚠️ Order #' . $order_id . '</strong><br>';
                    $output .= 'Webhook processed but status unchanged: ' . $new_status;
                    $output .= '</div>';
                }
                
            } catch (Exception $e) {
                $output .= '<div class="order-item error-item">';
                $output .= '<strong>❌ Order #' . $order_id . '</strong><br>';
                $output .= 'Error: ' . $e->getMessage();
                $output .= '</div>';
                $error_count++;
            }
        }
        
        $output .= '<div class="notice notice-success">';
        $output .= '<h3>📊 Simulation Complete!</h3>';
        $output .= '<p><strong>Successfully Updated:</strong> ' . $success_count . ' orders</p>';
        $output .= '<p><strong>Errors:</strong> ' . $error_count . ' orders</p>';
        
        if ($success_count > 0) {
            $output .= '<p><strong>Next steps:</strong></p>';
            $output .= '<ul>';
            $output .= '<li>Check WooCommerce → Orders to see updated "Shipped (Unpaid)" statuses</li>';
            $output .= '<li>Customers will receive shipment notification emails</li>';
            $output .= '<li>When customers pay, use bulk action "Mark Shipped Orders as Paid"</li>';
            $output .= '<li>Partners can now communicate shipment status to customers</li>';
            $output .= '</ul>';
        }
        $output .= '</div>';
        
        return $output;
    }
}

// Initialize the admin page
TwinTack_Shippo_Sync_Admin::get_instance();
