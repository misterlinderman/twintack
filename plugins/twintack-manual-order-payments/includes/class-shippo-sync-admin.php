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
        add_action('wp_ajax_twintack_toggle_auto_sync', array($this, 'ajax_toggle_auto_sync'));
        add_action('wp_ajax_twintack_save_age_filter', array($this, 'ajax_save_age_filter'));
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
            
            <?php $this->show_cron_health_status(); ?>
            
            <div class="notice notice-info">
                <p><strong>Purpose:</strong> This tool syncs orders that have been shipped in Shippo but are still showing as "Invoiced" in WooCommerce.</p>
                <p>It will update them to "Shipped (Unpaid)" status and send tracking notifications to customers.</p>
            </div>
            
            <?php $this->show_automation_controls(); ?>
            
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
        
        $age_filter_hours = get_option('twintack_sync_age_filter', 24); // Default 24 hours
        $age_filter_seconds = $age_filter_hours * HOUR_IN_SECONDS;
        
        foreach ($invoiced_orders as $order) {
            $age_seconds = time() - $order->get_date_created()->getTimestamp();
            if ($age_seconds >= $age_filter_seconds) {
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
                        <td>Invoiced status + Has Shippo Order ID + <?php 
                            echo $age_filter_hours === 0 ? 'Any age (immediate sync)' : 
                                 ($age_filter_hours === 1 ? '1 hour or older' : 
                                  ($age_filter_hours < 24 ? "{$age_filter_hours} hours or older" : 
                                   ($age_filter_hours === 24 ? '1 day or older' : 
                                    round($age_filter_hours / 24, 1) . ' days or older')));
                        ?></td>
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
        
        // Filter to eligible orders using configurable age
        $age_filter_hours = get_option('twintack_sync_age_filter', 24); // Default 24 hours
        $age_filter_seconds = $age_filter_hours * HOUR_IN_SECONDS;
        
        $target_orders = array();
        foreach ($all_invoiced_orders as $order) {
            $age_seconds = time() - $order->get_date_created()->getTimestamp();
            if ($age_seconds >= $age_filter_seconds) {
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
    
    /**
     * Show WP-Cron health status
     */
    private function show_cron_health_status() {
        if (!class_exists('TwinTack_Cron_Health_Monitor')) {
            return;
        }
        
        $monitor = TwinTack_Cron_Health_Monitor::get_instance();
        $health = $monitor->get_cron_health();
        
        $status_class = $health['is_healthy'] ? 'notice-success' : 'notice-warning';
        $status_icon = $health['is_healthy'] ? '✅' : '⚠️';
        
        ?>
        <div class="notice <?php echo $status_class; ?>" style="padding: 15px; margin-bottom: 20px;">
            <h3 style="margin-top: 0;"><?php echo $status_icon; ?> WP-Cron Health Status</h3>
            <table style="width: 100%; max-width: 800px;">
                <tr>
                    <td style="width: 200px; font-weight: bold;">Overall Status:</td>
                    <td>
                        <strong style="color: <?php echo $health['is_healthy'] ? '#28a745' : '#dc3545'; ?>;">
                            <?php echo $health['is_healthy'] ? '✅ HEALTHY' : '❌ NEEDS ATTENTION'; ?>
                        </strong>
                    </td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Last Cron Run:</td>
                    <td>
                        <?php if ($health['last_run_timestamp']): ?>
                            <?php echo $health['last_run_human']; ?> (<?php echo $health['last_run_date']; ?>)
                        <?php else: ?>
                            <span style="color: #dc3545;">Never recorded</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Real Cron Job:</td>
                    <td>
                        <?php if ($health['has_real_cron']): ?>
                            <span style="color: #28a745;">✅ Active (recommended)</span>
                        <?php else: ?>
                            <span style="color: #ffc107;">⚠️ Using visitor-triggered WP-Cron</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="font-weight: bold;">Overdue Events:</td>
                    <td>
                        <?php if ($health['overdue_count'] > 0): ?>
                            <span style="color: #dc3545;">⚠️ <?php echo $health['overdue_count']; ?> event(s) overdue</span>
                        <?php else: ?>
                            <span style="color: #28a745;">None</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <?php if (!$health['is_healthy']): ?>
            <div style="background: #fff; padding: 10px; margin-top: 10px; border-left: 4px solid #ffc107;">
                <strong>⚠️ Action Required:</strong> <?php echo $health['warning_message']; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Show automation controls
     */
    private function show_automation_controls() {
        // Get webhook handler instance for automation status
        $webhook_handler = TwinTack_Shippo_Webhook_Handler::get_instance();
        $automation_status = $webhook_handler->get_automation_status();
        
        ?>
        <div class="card" style="margin-bottom: 20px;">
            <h2>🤖 Automated Sync</h2>
            
            <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 15px;">
                <div>
                    <strong>Status:</strong> 
                    <span style="color: <?php echo $automation_status['enabled'] ? 'green' : 'red'; ?>; font-weight: bold;">
                        <?php echo $automation_status['enabled'] ? '✅ ENABLED' : '❌ DISABLED'; ?>
                    </span>
                </div>
                
                <?php if ($automation_status['enabled']): ?>
                <div>
                    <strong>Next Run:</strong> <?php echo $automation_status['next_run']; ?>
                </div>
                <div>
                    <strong>Interval:</strong> Every <?php echo $automation_status['interval']; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                <h4>🚀 How Automation Works:</h4>
                <ul style="margin: 0;">
                    <li><strong>🕐 Scheduled Sync:</strong> Runs every 4 hours to check for orders that need syncing</li>
                    <li><strong>⚡ Real-time Triggers:</strong> When real Shippo webhooks arrive, checks for other orders that might need updating</li>
                    <li><strong>🎯 Smart Filtering:</strong> Configurable age filter (default: 1+ days old) and must have Shippo Order IDs</li>
                    <li><strong>🛡️ Safe Processing:</strong> Limits to 10 orders per run to prevent timeouts</li>
                    <li><strong>📧 Automatic Emails:</strong> Sends tracking notifications when orders are updated</li>
                </ul>
            </div>
            
            <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 15px; border-left: 4px solid #ffc107;">
                <h4>⚙️ Sync Settings:</h4>
                <label for="sync-age-filter" style="display: block; margin-bottom: 10px;">
                    <strong>Minimum Order Age for Sync:</strong>
                </label>
                <select id="sync-age-filter" style="margin-bottom: 10px;">
                    <?php $current_age = get_option('twintack_sync_age_filter', 24); ?>
                    <option value="0" <?php selected($current_age, 0); ?>>Immediate (0 hours) - Sync same-day orders</option>
                    <option value="2" <?php selected($current_age, 2); ?>>2 hours old</option>
                    <option value="6" <?php selected($current_age, 6); ?>>6 hours old</option>
                    <option value="12" <?php selected($current_age, 12); ?>>12 hours old</option>
                    <option value="24" <?php selected($current_age, 24); ?>>24 hours (1 day) old - DEFAULT</option>
                    <option value="48" <?php selected($current_age, 48); ?>>48 hours (2 days) old</option>
                </select>
                <button type="button" id="save-age-filter" class="button" style="margin-left: 10px;">Save Setting</button>
                <br>
                <small style="color: #666;">
                    <strong>Immediate:</strong> Good if orders are shipped same-day<br>
                    <strong>24 hours:</strong> Safer, ensures orders are actually shipped before notification
                </small>
            </div>
            
            <button type="button" 
                    id="toggle-automation-btn" 
                    class="button <?php echo $automation_status['enabled'] ? 'button-secondary' : 'button-primary'; ?>"
                    data-current-state="<?php echo $automation_status['enabled'] ? 'enabled' : 'disabled'; ?>">
                <?php echo $automation_status['enabled'] ? '🛑 Disable Automation' : '🚀 Enable Automation'; ?>
            </button>
            
            <div id="automation-result" style="margin-top: 10px;"></div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Handle age filter saving
            $('#save-age-filter').on('click', function() {
                var button = $(this);
                var ageValue = $('#sync-age-filter').val();
                
                button.prop('disabled', true).text('Saving...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'twintack_save_age_filter',
                        age_hours: ageValue,
                        nonce: '<?php echo wp_create_nonce('twintack_age_filter'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            button.text('✅ Saved!');
                            setTimeout(function() {
                                button.text('Save Setting');
                            }, 2000);
                        } else {
                            button.text('❌ Error');
                            setTimeout(function() {
                                button.text('Save Setting');
                            }, 2000);
                        }
                    },
                    complete: function() {
                        button.prop('disabled', false);
                    }
                });
            });
            
            $('#toggle-automation-btn').on('click', function() {
                var button = $(this);
                var currentState = button.data('current-state');
                var newState = currentState === 'enabled' ? 'disabled' : 'enabled';
                var resultDiv = $('#automation-result');
                
                button.prop('disabled', true).text('⏳ Updating...');
                resultDiv.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'twintack_toggle_auto_sync',
                        enable: newState === 'enabled' ? '1' : '0',
                        nonce: '<?php echo wp_create_nonce('twintack_auto_sync'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var message = newState === 'enabled' ? 
                                '✅ Automation enabled! Sync will run every 4 hours.' : 
                                '🛑 Automation disabled. Manual sync only.';
                                
                            resultDiv.html('<div style="color: green; padding: 8px; background: #d4f6d4; border-radius: 3px;">' + message + '</div>');
                            
                            // Update button state
                            button.data('current-state', newState);
                            if (newState === 'enabled') {
                                button.removeClass('button-primary').addClass('button-secondary').text('🛑 Disable Automation');
                            } else {
                                button.removeClass('button-secondary').addClass('button-primary').text('🚀 Enable Automation');
                            }
                            
                            // Reload page after 3 seconds to show updated status
                            setTimeout(function() {
                                location.reload();
                            }, 3000);
                        } else {
                            resultDiv.html('<div style="color: red; padding: 8px; background: #f8d7da; border-radius: 3px;">❌ ' + response.data + '</div>');
                        }
                    },
                    error: function() {
                        resultDiv.html('<div style="color: red; padding: 8px; background: #f8d7da; border-radius: 3px;">❌ Request failed</div>');
                    },
                    complete: function() {
                        button.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle AJAX request to toggle automation
     */
    public function ajax_toggle_auto_sync() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_auto_sync')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $enable = isset($_POST['enable']) && $_POST['enable'] === '1';
        
        try {
            $webhook_handler = TwinTack_Shippo_Webhook_Handler::get_instance();
            $result = $webhook_handler->set_automation_enabled($enable);
            
            $message = $enable ? 
                'Automated sync enabled! Will run every 4 hours.' : 
                'Automated sync disabled.';
                
            wp_send_json_success($message);
            
        } catch (Exception $e) {
            wp_send_json_error('Error updating automation: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle AJAX request to save age filter setting
     */
    public function ajax_save_age_filter() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_age_filter')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $age_hours = intval($_POST['age_hours']);
        
        // Validate age range
        $allowed_ages = array(0, 2, 6, 12, 24, 48);
        if (!in_array($age_hours, $allowed_ages)) {
            wp_send_json_error('Invalid age setting');
        }
        
        try {
            update_option('twintack_sync_age_filter', $age_hours);
            
            $message = $age_hours === 0 ? 
                'Sync age filter set to immediate (same-day orders will be synced)' : 
                "Sync age filter set to {$age_hours} hours";
                
            wp_send_json_success($message);
            
        } catch (Exception $e) {
            wp_send_json_error('Error saving setting: ' . $e->getMessage());
        }
    }
}

// Initialize the admin page
TwinTack_Shippo_Sync_Admin::get_instance();
