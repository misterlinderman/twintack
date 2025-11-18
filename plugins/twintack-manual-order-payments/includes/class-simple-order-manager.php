<?php
/**
 * Simple Order Manager - Manual Admin Control
 * 
 * Gives admin full control over order status, Shippo sync, and payment methods
 * 
 * @package TwinTack_Manual_Order_Payments
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Simple_Order_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add simple admin controls to order edit page
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'add_simple_controls'));
        add_action('wp_ajax_twintack_simple_sync_order', array($this, 'handle_simple_sync'));
        add_action('wp_ajax_twintack_simple_set_status', array($this, 'handle_simple_status'));
        add_action('wp_ajax_twintack_send_customer_email', array($this, 'handle_send_email'));
        
        // Debug: Add a test AJAX handler
        add_action('wp_ajax_twintack_simple_test', array($this, 'handle_simple_test'));
        add_action('wp_ajax_twintack_debug_order', array($this, 'handle_debug_order'));
    }
    
    /**
     * Add simple manual controls to order edit page
     */
    public function add_simple_controls($order) {
        ?>
        <div class="twintack-simple-controls" style="margin-top: 20px; padding: 15px; border: 2px solid #0073aa; background: #f0f8ff; border-radius: 5px;">
            <h3 style="margin-top: 0; color: #0073aa;">🎯 TwinTack Order Control</h3>
            <p style="margin: 0 0 15px 0; font-style: italic; color: #666;">Complete manual control - click any button to take immediate action.</p>
            
            <div style="margin-bottom: 15px;">
                <label><strong>Current Status:</strong></label>
                <span style="padding: 4px 8px; background: #e1ecf4; border-radius: 3px;">
                    <?php echo esc_html(ucfirst($order->get_status())); ?>
                </span>
                
                <?php
                $shippo_status = $order->get_meta('_shippo_fulfillment_status');
                if ($shippo_status) {
                    echo ' | <strong>Shippo:</strong> <span style="padding: 4px 8px; background: #d4edda; border-radius: 3px;">' . esc_html($shippo_status) . '</span>';
                }
                ?>
            </div>
            
            <!-- Quick Status Actions -->
            <div style="margin-bottom: 15px;">
                <h5>Quick Status Actions:</h5>
                
                <button type="button" class="button" onclick="twintackSimpleSetStatus(<?php echo $order->get_id(); ?>, 'processing', 'Paid')" 
                        style="margin-right: 10px; background: #28a745; border-color: #28a745; color: white;">
                    ✅ Mark as Paid & Ready to Ship
                </button>
                
                <button type="button" class="button" onclick="twintackSimpleSetStatus(<?php echo $order->get_id(); ?>, 'invoiced', 'Payment Pending')" 
                        style="margin-right: 10px; background: #ffc107; border-color: #ffc107; color: black;">
                    💳 Set to Invoice (Send Payment Link)
                </button>
                
                <button type="button" class="button" onclick="twintackSimpleSetStatus(<?php echo $order->get_id(); ?>, 'completed', 'Shipped')" 
                        style="margin-right: 10px; background: #17a2b8; border-color: #17a2b8; color: white;">
                    📦 Mark as Shipped (Order Complete)
                </button>
            </div>
            
            <!-- Manual Shippo Sync -->
            <div style="margin-bottom: 15px;">
                <h5>Shippo Integration:</h5>
                
                <button type="button" class="button button-secondary" onclick="twintackSimpleSyncOrder(<?php echo $order->get_id(); ?>)" 
                        style="margin-right: 10px;">
                    🚢 Sync with Shippo (Create Shipping Label)
                </button>
                
                <label>
                    <input type="checkbox" id="shippo-ready-<?php echo $order->get_id(); ?>" 
                           <?php checked($order->get_meta('_shippo_ready_for_fulfillment'), 'yes'); ?>>
                    Ready for Shipment (override payment status)
                </label>
            </div>
            
            <!-- Manual Email Trigger -->
            <div>
                <h5>Customer Communication:</h5>
                
                <button type="button" class="button button-secondary" onclick="twintackSendCustomerEmail(<?php echo $order->get_id(); ?>, 'status_change')" 
                        style="margin-right: 10px;">
                    📧 Send Status Email
                </button>
                
                <button type="button" class="button button-secondary" onclick="twintackSendCustomerEmail(<?php echo $order->get_id(); ?>, 'invoice')" 
                        style="margin-right: 10px;">
                    💳 Send Payment Link
                </button>
            </div>
            
            <!-- Debug Test Button -->
            <div style="margin-top: 15px; padding: 10px; background: #f0f0f0; border: 1px dashed #ccc;">
                <h6 style="margin: 0 0 5px 0; color: #666;">Debug Test:</h6>
                <button type="button" class="button button-secondary" onclick="twintackSimpleTest()" style="background: #666; border-color: #666; color: white; margin-right: 5px;">
                    🔧 Test AJAX Connection
                </button>
                <button type="button" class="button button-secondary" onclick="twintackDebugOrder(<?php echo $order->get_id(); ?>)" style="background: #e74c3c; border-color: #e74c3c; color: white;">
                    🔍 Debug Order Data
                </button>
            </div>
            
            <!-- Loading Spinner -->
            <div id="twintack-simple-spinner" style="display: none; margin-top: 10px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 3px;">
                <span style="color: #856404;">⏳ Processing... Please wait</span>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Ensure we have ajaxurl
            if (typeof ajaxurl === 'undefined') {
                var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
            }
            
            // Debug info
            console.log('TwinTack Simple Manager loaded');
            console.log('ajaxurl:', ajaxurl);
        });
        
        function twintackSimpleSetStatus(orderId, wcStatus, shippoStatus) {
            if (typeof jQuery === 'undefined') {
                alert('❌ jQuery not loaded');
                return;
            }
            
            console.log('twintackSimpleSetStatus called', {orderId, wcStatus, shippoStatus});
            
            jQuery('#twintack-simple-spinner').show();
            
            var data = {
                action: 'twintack_simple_set_status',
                order_id: orderId,
                wc_status: wcStatus,
                shippo_status: shippoStatus,
                nonce: '<?php echo wp_create_nonce('twintack_simple_control'); ?>'
            };
            
            console.log('AJAX data:', data);
            console.log('ajaxurl:', ajaxurl);
            
            jQuery.post(ajaxurl, data, function(response) {
                console.log('AJAX response:', response);
                jQuery('#twintack-simple-spinner').hide();
                if (response.success) {
                    alert('✅ Status updated successfully!');
                    location.reload();
                } else {
                    var message = response.data && response.data.message ? response.data.message : 'Unknown error';
                    console.error('AJAX error response:', response);
                    alert('❌ Error: ' + message);
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX request failed:', {xhr, status, error});
                console.error('Response text:', xhr.responseText);
                jQuery('#twintack-simple-spinner').hide();
                alert('❌ AJAX Error: ' + error + ' (Check console for details)');
            });
        }
        
        function twintackSimpleSyncOrder(orderId) {
            if (typeof jQuery === 'undefined') {
                alert('❌ jQuery not loaded');
                return;
            }
            
            jQuery('#twintack-simple-spinner').show();
            
            var ready = jQuery('#shippo-ready-' + orderId).is(':checked');
            var data = {
                action: 'twintack_simple_sync_order',
                order_id: orderId,
                ready_for_fulfillment: ready ? 'yes' : 'no',
                nonce: '<?php echo wp_create_nonce('twintack_simple_control'); ?>'
            };
            
            jQuery.post(ajaxurl, data, function(response) {
                jQuery('#twintack-simple-spinner').hide();
                if (response.success) {
                    alert('🚢 Order synced to Shippo successfully!');
                    location.reload();
                } else {
                    var message = response.data && response.data.message ? response.data.message : 'Unknown error';
                    alert('❌ Sync failed: ' + message);
                }
            }).fail(function(xhr, status, error) {
                jQuery('#twintack-simple-spinner').hide();
                alert('❌ AJAX Error: ' + error);
            });
        }
        
        function twintackSendCustomerEmail(orderId, emailType) {
            if (typeof jQuery === 'undefined') {
                alert('❌ jQuery not loaded');
                return;
            }
            
            jQuery('#twintack-simple-spinner').show();
            
            var data = {
                action: 'twintack_send_customer_email',
                order_id: orderId,
                email_type: emailType,
                nonce: '<?php echo wp_create_nonce('twintack_simple_control'); ?>'
            };
            
            jQuery.post(ajaxurl, data, function(response) {
                jQuery('#twintack-simple-spinner').hide();
                if (response.success) {
                    alert('📧 Email sent successfully!');
                } else {
                    var message = response.data && response.data.message ? response.data.message : 'Unknown error';
                    alert('❌ Email failed: ' + message);
                }
            }).fail(function(xhr, status, error) {
                jQuery('#twintack-simple-spinner').hide();
                alert('❌ AJAX Error: ' + error);
            });
        }
        
        function twintackSimpleTest() {
            console.log('Testing AJAX connection...');
            
            var data = {
                action: 'twintack_simple_test',
                nonce: '<?php echo wp_create_nonce('twintack_simple_control'); ?>'
            };
            
            jQuery.post(ajaxurl, data, function(response) {
                console.log('Test response:', response);
                if (response.success) {
                    alert('✅ AJAX Test Success: ' + response.data.message);
                } else {
                    alert('❌ AJAX Test Failed: ' + (response.data ? response.data.message : 'Unknown error'));
                }
            }).fail(function(xhr, status, error) {
                console.error('Test AJAX failed:', {xhr, status, error});
                alert('❌ AJAX Test Connection Failed: ' + error);
            });
        }
        
        function twintackDebugOrder(orderId) {
            console.log('Debugging order data for order:', orderId);
            
            var data = {
                action: 'twintack_debug_order',
                order_id: orderId,
                nonce: '<?php echo wp_create_nonce('twintack_simple_control'); ?>'
            };
            
            jQuery.post(ajaxurl, data, function(response) {
                console.log('Debug response:', response);
                if (response.success) {
                    alert('🔍 Debug Data (check console):\n\n' + response.data.summary);
                    console.table(response.data.details);
                } else {
                    alert('❌ Debug Failed: ' + (response.data ? response.data.message : 'Unknown error'));
                }
            }).fail(function(xhr, status, error) {
                console.error('Debug AJAX failed:', {xhr, status, error});
                alert('❌ Debug Connection Failed: ' + error);
            });
        }
        </script>
        <?php
    }
    
    /**
     * Handle simple status change
     */
    public function handle_simple_status() {
        // Enhanced error checking and logging
        twintack_manual_payments_log("Simple Manager: handle_simple_status called");
        
        if (!isset($_POST['nonce'])) {
            twintack_manual_payments_log("Simple Manager: No nonce provided", 'error');
            wp_send_json_error(array('message' => 'No security token provided'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_simple_control')) {
            twintack_manual_payments_log("Simple Manager: Nonce verification failed", 'error');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $order_id = intval($_POST['order_id']);
        $wc_status = sanitize_text_field($_POST['wc_status']);
        $shippo_status = sanitize_text_field($_POST['shippo_status']);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        // Preserve wholesale pricing before status change
        $this->preserve_wholesale_pricing($order);
        
        // Update WooCommerce status
        $order->update_status($wc_status, "Status manually set via TwinTack Simple Manager: {$wc_status}");
        
        // Set Shippo meta
        $order->update_meta_data('_shippo_fulfillment_status', $shippo_status);
        $order->update_meta_data('_shippo_ready_for_fulfillment', 'yes');
        $order->update_meta_data('_twintack_manual_order', 'yes');
        $order->save();
        
        // Auto-send order confirmation email based on status change
        $this->auto_send_order_confirmation($order, $wc_status);
        
        // Restore wholesale pricing if it was disrupted by status change
        $this->restore_wholesale_pricing($order_id);
        
        // Force Shippo sync
        if (class_exists('TwinTack_Shippo_API_Client')) {
            $api_client = TwinTack_Shippo_API_Client::get_instance();
            
            // Create proper shippo order data structure
            $shippo_order_data = array(
                'order_id' => $order_id,
                'status' => $wc_status,
                'fulfillment_status' => $shippo_status
            );
            
            $api_client->send_order_to_shippo($shippo_order_data, $order);
        }
        
        twintack_manual_payments_log("Simple Manager: Order {$order_id} set to {$wc_status} with Shippo status {$shippo_status}");
        
        wp_send_json_success(array('message' => 'Status updated successfully'));
    }
    
    /**
     * Handle simple Shippo sync
     */
    public function handle_simple_sync() {
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_simple_control')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $order_id = intval($_POST['order_id']);
        $ready = sanitize_text_field($_POST['ready_for_fulfillment']);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        // Set fulfillment readiness
        $order->update_meta_data('_shippo_ready_for_fulfillment', $ready);
        if ($ready === 'yes') {
            $order->delete_meta_data('_shippo_fulfillment_hold');
        } else {
            $order->update_meta_data('_shippo_fulfillment_hold', 'yes');
        }
        $order->save();
        
        // Clear any previous error messages
        $order->delete_meta_data('_shippo_last_error');
        $order->save();
        
        // Force sync with Shippo
        if (class_exists('TwinTack_Shippo_API_Client')) {
            $api_client = TwinTack_Shippo_API_Client::get_instance();
            
            // Create proper shippo order data structure
            $shippo_order_data = array(
                'order_id' => $order_id,
                'status' => $order->get_status(),
                'fulfillment_status' => 'Payment Pending'  // Default for manual sync
            );
            
            $success = $api_client->send_order_to_shippo($shippo_order_data, $order);
            
            if ($success) {
                wp_send_json_success(array('message' => 'Order synced to Shippo successfully'));
            } else {
                // Get more specific error information
                $error_message = 'Failed to sync with Shippo API';
                
                // Check if there are any Shippo-specific error messages in the order meta
                $last_error = $order->get_meta('_shippo_last_error');
                if ($last_error) {
                    $error_message .= ': ' . $last_error;
                }
                
                twintack_manual_payments_log("Simple Manager: Shippo sync failed for order {$order_id}. Check debug logs for details.", 'error');
                wp_send_json_error(array('message' => $error_message));
            }
        } else {
            wp_send_json_error(array('message' => 'Shippo API client not available'));
        }
    }
    
    /**
     * Handle sending customer emails
     */
    public function handle_send_email() {
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_simple_control')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $order_id = intval($_POST['order_id']);
        $email_type = sanitize_text_field($_POST['email_type']);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        $customer_email = $order->get_billing_email();
        if (empty($customer_email)) {
            wp_send_json_error(array('message' => 'No customer email address'));
        }
        
        try {
            if ($email_type === 'status_change') {
                // Send order status change email
                $mailer = WC()->mailer();
                $emails = $mailer->get_emails();
                
                // Send the appropriate email based on current status
                $current_status = $order->get_status();
                if ($current_status === 'completed' && isset($emails['WC_Email_Customer_Completed_Order'])) {
                    $emails['WC_Email_Customer_Completed_Order']->trigger($order_id, $order);
                    $message = "Completed-order email sent to {$customer_email}";
                } elseif ($current_status === 'processing' && isset($emails['WC_Email_Customer_Processing_Order'])) {
                    $emails['WC_Email_Customer_Processing_Order']->trigger($order_id, $order);
                    $message = "Processing-order email sent to {$customer_email}";
                } else {
                    // Fallback to generic order details via wp_mail if no template matches
                    $subject = sprintf(__('Order #%s update', 'twintack-manual-payments'), $order->get_order_number());
                    $body    = sprintf(__('Your order status is now: %s', 'twintack-manual-payments'), wc_get_order_status_name($current_status));
                    wp_mail($customer_email, $subject, $body);
                    $message = "Status update email sent to {$customer_email}";
                }
                
            } elseif ($email_type === 'invoice') {
                // Send payment link email using existing admin enhancements
                if (class_exists('TwinTack_Admin_Order_Enhancements')) {
                    $admin_enhancements = TwinTack_Admin_Order_Enhancements::get_instance();
                    
                    // Create a simple payment link (you can enhance this)
                    $payment_url = $order->get_checkout_payment_url();
                    
                    // Simple email content
                    $subject = "Payment Required for Order #{$order->get_order_number()}";
                    $message_body = "Hello,\n\nPlease complete payment for your order:\n\nOrder: #{$order->get_order_number()}\nTotal: " . wc_price($order->get_total()) . "\n\nPayment Link: {$payment_url}\n\nThank you!";
                    
                    $headers = array('Content-Type: text/plain; charset=UTF-8');
                    $email_sent = wp_mail($customer_email, $subject, $message_body, $headers);
                    
                    if (!$email_sent) {
                        wp_send_json_error(array('message' => 'Failed to send payment email'));
                    }
                    
                    $message = "Payment link email sent to {$customer_email}";
                } else {
                    wp_send_json_error(array('message' => 'Admin enhancements not available'));
                }
                
            } else {
                wp_send_json_error(array('message' => 'Unknown email type'));
            }
            
            // Log the email send
            $order->add_order_note("Email sent via Simple Manager: {$email_type} to {$customer_email}");
            twintack_manual_payments_log("Simple Manager: {$email_type} email sent to {$customer_email} for order {$order_id}");
            
            wp_send_json_success(array('message' => $message));
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Simple Manager email error: " . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => 'Email sending failed: ' . $e->getMessage()));
        }
    }
    
    /**
     * Handle simple test AJAX - for debugging
     */
    public function handle_simple_test() {
        twintack_manual_payments_log("Simple Manager: handle_simple_test called");
        
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_simple_control')) {
            wp_send_json_error(array('message' => 'Nonce failed'));
        }
        
        wp_send_json_success(array('message' => 'AJAX is working! Simple Order Manager is connected.'));
    }
    
    /**
     * Handle debug order AJAX - for detailed order diagnostics
     */
    public function handle_debug_order() {
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_simple_control')) {
            wp_send_json_error(array('message' => 'Nonce failed'));
        }
        
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        // Collect comprehensive order data
        $debug_data = array(
            'order_id' => $order_id,
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'customer_email' => $order->get_billing_email(),
            'billing_address' => array(
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'address_1' => $order->get_billing_address_1(),
                'address_2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'postcode' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country(),
            ),
            'shipping_address' => array(
                'first_name' => $order->get_shipping_first_name(),
                'last_name' => $order->get_shipping_last_name(),
                'address_1' => $order->get_shipping_address_1(),
                'address_2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'postcode' => $order->get_shipping_postcode(),
                'country' => $order->get_shipping_country(),
            ),
            'item_count' => $order->get_item_count(),
            'items' => array(),
            'meta_data' => array(
                '_shippo_order_id' => $order->get_meta('_shippo_order_id'),
                '_shippo_fulfillment_status' => $order->get_meta('_shippo_fulfillment_status'),
                '_shippo_last_error' => $order->get_meta('_shippo_last_error'),
                '_twintack_manual_order' => $order->get_meta('_twintack_manual_order'),
                '_shippo_ready_for_fulfillment' => $order->get_meta('_shippo_ready_for_fulfillment'),
                '_shippo_payment_status' => $order->get_meta('_shippo_payment_status'),
            )
        );
        
        // Get order items
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $debug_data['items'][] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
                'sku' => $product ? $product->get_sku() : 'N/A',
                'weight' => $product ? $product->get_weight() : 'N/A',
            );
        }
        
        // Check for potential issues
        $issues = array();
        
        if (empty($order->get_billing_email())) {
            $issues[] = 'Missing customer email';
        }
        if (empty($order->get_shipping_address_1()) && empty($order->get_billing_address_1())) {
            $issues[] = 'Missing shipping/billing address';
        }
        if ($order->get_item_count() == 0) {
            $issues[] = 'No items in order';
        }
        if (empty($order->get_shipping_country()) && empty($order->get_billing_country())) {
            $issues[] = 'Missing country information';
        }
        
        $summary = "Order #{$order_id} Debug Summary:\n";
        $summary .= "Status: {$order->get_status()}\n";
        $summary .= "Items: {$order->get_item_count()}\n";
        $summary .= "Total: " . wc_price($order->get_total()) . "\n";
        $summary .= "Email: {$order->get_billing_email()}\n";
        
        if (!empty($issues)) {
            $summary .= "\n⚠️ Potential Issues:\n" . implode("\n", $issues);
        } else {
            $summary .= "\n✅ No obvious issues found";
        }
        
        twintack_manual_payments_log("Debug Order #{$order_id}: " . json_encode($debug_data, JSON_PRETTY_PRINT));
        
        wp_send_json_success(array(
            'message' => 'Debug data collected',
            'summary' => $summary,
            'details' => $debug_data,
            'issues' => $issues
        ));
    }
    
    /**
     * Auto-send order confirmation email based on status change
     */
    private function auto_send_order_confirmation($order, $new_status) {
        try {
            $customer_email = $order->get_billing_email();
            
            if (!is_email($customer_email)) {
                twintack_manual_payments_log("Simple Manager: Invalid email address for order {$order->get_id()}: {$customer_email}");
                return false;
            }
            
            // Get WooCommerce mailer
            $mailer = WC()->mailer();
            $emails = $mailer->get_emails();
            
            $email_sent = false;
            $email_type = '';
            
            // Send appropriate email based on status
            switch ($new_status) {
                case 'processing':
                    // Send processing order email
                    if (isset($emails['WC_Email_Customer_Processing_Order'])) {
                        $emails['WC_Email_Customer_Processing_Order']->trigger($order->get_id(), $order);
                        $email_type = 'processing order confirmation';
                        $email_sent = true;
                    }
                    break;
                    
                case 'completed':
                    // Send completed order email
                    if (isset($emails['WC_Email_Customer_Completed_Order'])) {
                        $emails['WC_Email_Customer_Completed_Order']->trigger($order->get_id(), $order);
                        $email_type = 'order completion notification';
                        $email_sent = true;
                    }
                    break;
                    
                case 'invoiced':
                    // Send custom invoice/payment request email
                    $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                    if (empty($customer_name)) {
                        $customer_name = 'Valued Customer';
                    }
                    
                    $payment_url = $order->get_checkout_payment_url();
                    $subject = sprintf('Payment Required for Order #%s - %s', $order->get_order_number(), get_bloginfo('name'));
                    
                    $message = sprintf("
Dear %s,

Thank you for your order! We have received your order and it's ready for payment.

Order Details:
- Order Number: #%s
- Order Date: %s
- Total Amount: %s

Please complete your payment using the secure link below:
%s

Once payment is received, we'll begin processing your order immediately.

If you have any questions, please don't hesitate to contact us.

Best regards,
%s Team

---
This is an automated message from your order management system.
                    ",
                        $customer_name,
                        $order->get_order_number(),
                        $order->get_date_created()->format('F j, Y'),
                        wc_price($order->get_total()),
                        $payment_url,
                        get_bloginfo('name')
                    );
                    
                    $headers = array(
                        'Content-Type: text/plain; charset=UTF-8',
                        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
                    );
                    
                    $email_sent = wp_mail($customer_email, $subject, $message, $headers);
                    $email_type = 'invoice/payment request';
                    break;
            }
            
            if ($email_sent) {
                $order->add_order_note("Auto-sent {$email_type} email to customer: {$customer_email}");
                twintack_manual_payments_log("Simple Manager: Auto-sent {$email_type} email to {$customer_email} for order {$order->get_id()}");
            } else {
                twintack_manual_payments_log("Simple Manager: Failed to auto-send email for order {$order->get_id()} status {$new_status}");
            }
            
            return $email_sent;
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Simple Manager: Error auto-sending email for order {$order->get_id()}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Preserve wholesale pricing data during status changes
     */
    private function preserve_wholesale_pricing($order) {
        try {
            // Check if this is a wholesale order
            $customer_id = $order->get_customer_id();
            $is_wholesale = false;
            
            if ($customer_id) {
                $user = get_user_by('id', $customer_id);
                if ($user) {
                    $user_roles = $user->roles;
                    foreach ($user_roles as $role) {
                        if (strpos($role, 'wholesale') !== false) {
                            $is_wholesale = true;
                            break;
                        }
                    }
                }
            }
            
            if (!$is_wholesale) {
                return; // Not a wholesale order, no action needed
            }
            
            twintack_manual_payments_log("Simple Manager: Preserving wholesale pricing for order {$order->get_id()}");
            
            // Store original pricing data
            $original_subtotal = $order->get_subtotal();
            $original_total = $order->get_total();
            $original_discount = $order->get_total_discount();
            
            // Preserve line item wholesale pricing
            foreach ($order->get_items() as $item_id => $item) {
                // Backup current wholesale pricing meta
                $wholesale_price = $item->get_meta('_wwp_wholesale_price');
                $wholesale_role = $item->get_meta('_wwp_wholesale_role');
                $line_subtotal = $item->get_subtotal();
                $line_total = $item->get_total();
                
                if ($wholesale_price) {
                    // Store backup of wholesale data
                    $item->update_meta_data('_twintack_backup_wwp_wholesale_price', $wholesale_price);
                    $item->update_meta_data('_twintack_backup_wwp_wholesale_role', $wholesale_role);
                    $item->update_meta_data('_twintack_backup_line_subtotal', $line_subtotal);
                    $item->update_meta_data('_twintack_backup_line_total', $line_total);
                    
                    twintack_manual_payments_log("Simple Manager: Backed up wholesale pricing for item {$item_id}: $" . $wholesale_price);
                }
            }
            
            // Store order-level backup data
            $order->update_meta_data('_twintack_backup_subtotal', $original_subtotal);
            $order->update_meta_data('_twintack_backup_total', $original_total);
            $order->update_meta_data('_twintack_backup_discount', $original_discount);
            $order->update_meta_data('_twintack_wholesale_preserved', 'yes');
            
            $order->save();
            
            twintack_manual_payments_log("Simple Manager: Wholesale pricing backup completed for order {$order->get_id()}");
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Simple Manager: Error preserving wholesale pricing for order {$order->get_id()}: " . $e->getMessage());
        }
    }
    
    /**
     * Restore wholesale pricing if it got disrupted
     */
    public function restore_wholesale_pricing($order_id) {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return false;
            }
            
            // Check if we have backup data
            $has_backup = $order->get_meta('_twintack_wholesale_preserved');
            if (!$has_backup) {
                return false;
            }
            
            twintack_manual_payments_log("Simple Manager: Restoring wholesale pricing for order {$order_id}");
            
            $pricing_changed = false;
            
            // Restore line item pricing
            foreach ($order->get_items() as $item_id => $item) {
                $backup_price = $item->get_meta('_twintack_backup_wwp_wholesale_price');
                $backup_role = $item->get_meta('_twintack_backup_wwp_wholesale_role');
                $backup_subtotal = $item->get_meta('_twintack_backup_line_subtotal');
                $backup_total = $item->get_meta('_twintack_backup_line_total');
                
                if ($backup_price) {
                    // Restore wholesale meta if missing
                    if (!$item->get_meta('_wwp_wholesale_price')) {
                        $item->update_meta_data('_wwp_wholesale_price', $backup_price);
                        $pricing_changed = true;
                    }
                    
                    if (!$item->get_meta('_wwp_wholesale_role')) {
                        $item->update_meta_data('_wwp_wholesale_role', $backup_role);
                    }
                    
                    // Check if line totals need restoration
                    if ($backup_subtotal && abs($item->get_subtotal() - $backup_subtotal) > 0.01) {
                        $item->set_subtotal($backup_subtotal);
                        $pricing_changed = true;
                    }
                    
                    if ($backup_total && abs($item->get_total() - $backup_total) > 0.01) {
                        $item->set_total($backup_total);
                        $pricing_changed = true;
                    }
                    
                    $item->save();
                    
                    twintack_manual_payments_log("Simple Manager: Restored wholesale pricing for item {$item_id}");
                }
            }
            
            // Restore order totals if needed
            $backup_subtotal = $order->get_meta('_twintack_backup_subtotal');
            $backup_total = $order->get_meta('_twintack_backup_total');
            
            if ($backup_subtotal && abs($order->get_subtotal() - $backup_subtotal) > 0.01) {
                $order->set_subtotal($backup_subtotal);
                $pricing_changed = true;
            }
            
            if ($backup_total && abs($order->get_total() - $backup_total) > 0.01) {
                $order->set_total($backup_total);
                $pricing_changed = true;
            }
            
            if ($pricing_changed) {
                $order->save();
                $order->add_order_note('TwinTack: Restored wholesale pricing after status change');
                twintack_manual_payments_log("Simple Manager: Successfully restored wholesale pricing for order {$order_id}");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Simple Manager: Error restoring wholesale pricing for order {$order_id}: " . $e->getMessage());
            return false;
        }
    }
}