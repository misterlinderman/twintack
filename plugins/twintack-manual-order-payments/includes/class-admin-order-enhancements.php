<?php
/**
 * Admin Order Enhancements - Simplified Version
 * 
 * Handles payment gateway availability for manual order creation
 * 
 * @package TwinTack_Manual_Order_Payments
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Admin_Order_Enhancements {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Log initialization
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log('Admin Order Enhancements: Starting initialization');
        }
        
        // Only proceed if WooCommerce exists
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Register AJAX handlers immediately (they need to be available for AJAX requests)
        $this->init_ajax_handlers();
        
        // Register admin-only hooks if in admin
        if (is_admin()) {
            $this->init_admin_hooks();
        }
        
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log('Admin Order Enhancements: Initialization complete');
        }
    }
    
    /**
     * Initialize AJAX handlers (must be available for AJAX requests)
     */
    private function init_ajax_handlers() {
        // Add AJAX handlers for payment processing
        add_action('wp_ajax_twintack_mark_paid', array($this, 'handle_mark_paid'));
        add_action('wp_ajax_twintack_process_payment', array($this, 'handle_process_payment'));
        add_action('wp_ajax_twintack_send_payment_link', array($this, 'handle_send_payment_link'));
        add_action('wp_ajax_twintack_set_pay_later', array($this, 'handle_set_pay_later'));
        add_action('wp_ajax_twintack_force_shippo_sync', array($this, 'handle_force_shippo_sync'));
        add_action('wp_ajax_twintack_update_tracking', array($this, 'handle_update_tracking'));
        add_action('wp_ajax_twintack_send_tracking_email', array($this, 'handle_send_tracking_email'));
        add_action('wp_ajax_twintack_save_po_number', array($this, 'handle_save_po_number'));
        add_action('wp_ajax_twintack_generate_pdf_invoice', array($this, 'handle_generate_pdf_invoice'));
        
        // Log AJAX registration
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log('Admin Order Enhancements: AJAX handlers registered');
        }
    }
    
    /**
     * Initialize admin-only hooks
     */
    private function init_admin_hooks() {
        // TEMPORARILY DISABLE the new mark as paid section to fix broken order pages
        // TODO: Re-enable after fixing the redundant navigation issue
        
        // Add payment processing section to order edit pages (keeping this for now)
        // add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'add_payment_section'), 10, 1);
        
        // DISABLED: Add prominent "Mark as Paid" section - causing redundant navigation
        // add_action('woocommerce_admin_order_data_after_order_details', array($this, 'add_mark_as_paid_section_safe'), 5, 1);
        
        // Add tracking information section
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'add_tracking_section'), 10, 1);
        
        // Add TwinTack Order Control section with PO Number and PDF Invoice
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'add_twintack_order_control_section'), 15, 1);
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Handle customer payment pages
        add_action('init', array($this, 'init_customer_payment_handling'));
        
        // Handle payment return from Stripe
        add_action('woocommerce_thankyou', array($this, 'handle_stripe_payment_return'), 10, 1);
        
        // Ensure only Stripe for invoiced orders
        add_filter('woocommerce_available_payment_gateways', array($this, 'limit_payment_gateways_for_invoiced_orders'), 10, 1);
        
        // Log hook registration
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log('Admin Order Enhancements: Admin hooks registered (UI sections temporarily disabled)');
        }
    }
    
    /**
     * Add payment processing section to admin order edit page
     */
    public function add_payment_section($order) {
        // Safety checks
        if (!$order) {
            return;
        }
        
        // Check if manual payments are enabled
        if (!function_exists('twintack_is_manual_payments_enabled') || !twintack_is_manual_payments_enabled()) {
            return;
        }
        
        // Don't show for completed orders
        if ($order->get_status() === 'completed') {
            return;
        }
        
        // Log section display
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log('Admin Order Enhancements: Displaying payment section for order ' . $order->get_id());
        }
        
        echo '<div class="twintack-payment-processing" style="margin-top: 20px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">';
        echo '<h4>' . esc_html__('TwinTack Payment Processing', 'twintack-manual-payments') . '</h4>';
        
        // Current order information
        echo '<div style="margin-bottom: 15px;">';
        echo '<p><strong>Order ID:</strong> ' . esc_html($order->get_id()) . '</p>';
        echo '<p><strong>Order Status:</strong> ' . esc_html($order->get_status()) . '</p>';
        echo '<p><strong>Order Total:</strong> ' . wc_price($order->get_total()) . '</p>';
        echo '<p><strong>Current Payment Method:</strong> ' . esc_html($order->get_payment_method_title() ?: 'None') . '</p>';
        echo '</div>';
        
        // Payment method selector
        $this->render_payment_method_selector($order);
        
        echo '</div>';
    }
    
    /**
     * Render payment method selector
     */
    private function render_payment_method_selector($order) {
        // Get available payment gateways
        $available_gateways = array();
        
        if (function_exists('WC') && WC()->payment_gateways) {
            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            
            // Force enable Stripe for admin if setting is enabled
            if (function_exists('twintack_is_stripe_admin_enabled') && twintack_is_stripe_admin_enabled() && class_exists('WC_Gateway_Stripe')) {
                if (!isset($available_gateways['stripe'])) {
                    $stripe_gateway = new WC_Gateway_Stripe();
                    $stripe_gateway->enabled = 'yes';
                    $available_gateways['stripe'] = $stripe_gateway;
                    
                    if (function_exists('twintack_manual_payments_log')) {
                        twintack_manual_payments_log('Stripe gateway manually added for admin order processing');
                    }
                }
            }
        }
        
        if (empty($available_gateways)) {
            echo '<p style="color: #d63638;"><strong>⚠️ No payment gateways available.</strong> Please configure payment methods in WooCommerce settings.</p>';
            return;
        }
        
        echo '<div style="margin-bottom: 15px;">';
        echo '<label for="twintack_payment_method_select"><strong>' . esc_html__('Select Payment Method:', 'twintack-manual-payments') . '</strong></label><br>';
        echo '<select id="twintack_payment_method_select" name="payment_method" style="width: 100%; max-width: 300px; margin-top: 5px;">';
        echo '<option value="">' . esc_html__('-- Select Payment Method --', 'twintack-manual-payments') . '</option>';
        
        foreach ($available_gateways as $gateway_id => $gateway) {
            $selected = ($order->get_payment_method() === $gateway_id) ? 'selected' : '';
            $gateway_title = $gateway->get_title();
            
            // Add helpful context for admin users
            if ($gateway_id === 'stripe' || strpos(strtolower($gateway_title), 'stripe') !== false) {
                $gateway_title .= ' [STRIPE]';
            } elseif (strpos(strtolower($gateway_title), 'credit') !== false || strpos(strtolower($gateway_title), 'card') !== false) {
                $gateway_title .= ' [CARD]';
            }
            
            echo '<option value="' . esc_attr($gateway_id) . '" ' . $selected . '>';
            echo esc_html($gateway_title);
            echo '</option>';
        }
        
        echo '</select>';
        echo '</div>';
        
        // Payment action buttons
        $this->render_payment_buttons($order);
        
        // Log available gateways
        if (function_exists('twintack_manual_payments_log')) {
            $gateway_details = array();
            foreach ($available_gateways as $gateway_id => $gateway) {
                $gateway_details[] = $gateway_id . ' (' . $gateway->get_title() . ', ' . get_class($gateway) . ')';
            }
            twintack_manual_payments_log('Available payment gateways for order ' . $order->get_id() . ': ' . implode(', ', $gateway_details));
        }
    }
    
    /**
     * Render payment action buttons
     */
    private function render_payment_buttons($order) {
        // Don't show buttons for completed orders
        if ($order->get_status() === 'completed') {
            echo '<div style="margin-top: 15px; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460;">';
            echo '<strong>ℹ️ Order Completed:</strong> This order is already completed. No payment actions available.';
            echo '</div>';
            return;
        }
        
        echo '<div style="margin-top: 15px; border-top: 1px solid #ddd; padding-top: 15px;">';
        
        // Show current status information
        $current_status = $order->get_status();
        $shippo_status = '';
        if (class_exists('TwinTack_Order_Status_Manager')) {
            $status_manager = TwinTack_Order_Status_Manager::get_instance();
            $shippo_status = $status_manager->get_order_shippo_status($order->get_id());
        }
        
        echo '<div style="margin-bottom: 15px; padding: 10px; background: #f0f8ff; border: 1px solid #0073aa; border-radius: 3px;">';
        echo '<strong>Current Status:</strong> ' . esc_html(ucfirst($current_status));
        if ($shippo_status) {
            echo ' | <strong>Shippo Status:</strong> ' . esc_html($shippo_status);
        }
        echo '</div>';
        
        echo '<h5 style="margin: 0 0 15px 0;">' . esc_html__('Payment Actions for Manual Orders', 'twintack-manual-payments') . '</h5>';
        
        // Primary Action Buttons - Pay Now vs Pay Later
        echo '<div style="margin-bottom: 20px; padding: 15px; background: #fff; border: 2px solid #e5e5e5; border-radius: 5px;">';
        echo '<h6 style="margin: 0 0 10px 0; color: #333;">Choose Payment Method for This Order:</h6>';
        
        echo '<div style="margin-bottom: 15px;">';
        
        // Pay Now button (Mark as Paid)
        echo '<button type="button" class="button button-primary" id="twintack-pay-now" data-order-id="' . esc_attr($order->get_id()) . '" style="margin-right: 15px; margin-bottom: 8px; padding: 8px 20px; font-weight: bold;">';
        echo '✅ Pay Now (Mark as Paid)';
        echo '</button>';
        
        // Pay Later button (Invoice)
        echo '<button type="button" class="button" id="twintack-pay-later" data-order-id="' . esc_attr($order->get_id()) . '" style="margin-right: 15px; margin-bottom: 8px; padding: 8px 20px; background: #ff9800; border-color: #ff9800; color: white; font-weight: bold;">';
        echo '📄 Pay Later (Invoice)';
        echo '</button>';
        
        echo '</div>';
        
        echo '<div style="font-size: 11px; color: #666; line-height: 1.3;">';
        echo '<div style="margin-bottom: 5px;"><strong>Pay Now:</strong> Sets order to Processing status (Shippo: Payment Pending) - ready for immediate fulfillment</div>';
        echo '<div><strong>Pay Later:</strong> Sets order to Invoiced status (Shippo: Payment Pending) - customer will receive payment link, ready for immediate fulfillment</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Advanced Actions
        echo '<div style="margin-bottom: 15px;">';
        echo '<h6 style="margin: 0 0 10px 0; color: #666;">Advanced Payment Actions:</h6>';
        
        // Send Payment Link button
        echo '<button type="button" class="button button-secondary" id="twintack-send-payment-link" data-order-id="' . esc_attr($order->get_id()) . '" style="margin-right: 8px; margin-bottom: 5px; background: #6c5ce7; border-color: #6c5ce7; color: white;">';
        echo '📧 Send Stripe Payment Link';
        echo '</button>';
        
        // Process Payment button (legacy)
        echo '<button type="button" class="button button-secondary" id="twintack-process-payment" data-order-id="' . esc_attr($order->get_id()) . '" style="margin-right: 8px; margin-bottom: 5px;">';
        echo '💳 Set Payment Method Only';
        echo '</button>';
        
        // Force Shippo Sync button (for testing)
        echo '<button type="button" class="button button-secondary" id="twintack-force-shippo-sync" data-order-id="' . esc_attr($order->get_id()) . '" style="margin-right: 8px; margin-bottom: 5px; background: #17a2b8; border-color: #17a2b8; color: white;">';
        echo '🚢 Force Shippo Sync';
        echo '</button>';
        
        echo '</div>';
        
        // Status explanations
        echo '<div style="font-size: 11px; color: #666; line-height: 1.4; background: #f8f9fa; padding: 10px; border-radius: 3px; margin-bottom: 15px;">';
        echo '<strong>Status Mapping for Shippo Fulfillment:</strong><br>';
        echo '• <strong>Processing = Payment Pending</strong> (manual order - ready for immediate fulfillment)<br>';
        echo '• <strong>Invoiced = Payment Pending</strong> (invoice sent - ready for immediate fulfillment)<br>';
        echo '• <strong>Completed = Shipped</strong> (order fulfilled and shipped)';
        echo '</div>';
        
        // Customer information for payment link
        $customer_email = $order->get_billing_email();
        if ($customer_email) {
            echo '<div style="margin-top: 10px; padding: 8px; background: #e3f2fd; border-left: 3px solid #2196f3; font-size: 12px;">';
            echo '<strong>📧 Customer Email:</strong> ' . esc_html($customer_email);
            echo '</div>';
        } else {
            echo '<div style="margin-top: 10px; padding: 8px; background: #fff3e0; border-left: 3px solid #ff9800; font-size: 12px;">';
            echo '<strong>⚠️ Warning:</strong> No customer email address found. Please add billing email before using Pay Later option.';
            echo '</div>';
        }
        
        // Messages area
        echo '<div id="twintack-payment-messages" style="margin-top: 15px;"></div>';
        echo '</div>';
        
        // Add JavaScript for all button handlers
        $this->add_payment_buttons_javascript();
    }
    
    /**
     * Add prominent "Mark as Paid" section to order actions area
     */
    public function add_mark_as_paid_section($order) {
        // Enhanced safety checks
        if (!$order || !is_object($order)) {
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log('Admin Enhancement: add_mark_as_paid_section called with invalid order object', 'error');
            }
            return;
        }
        
        // Check if this is actually a WooCommerce order
        if (!method_exists($order, 'get_id') || !method_exists($order, 'get_status')) {
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log('Admin Enhancement: Object is not a valid WooCommerce order', 'error');
            }
            return;
        }
        
        // Check if manual payments are enabled
        if (!function_exists('twintack_is_manual_payments_enabled') || !twintack_is_manual_payments_enabled()) {
            return;
        }
        
        // Don't show for completed orders or orders that are already processing/completed
        try {
            $status = $order->get_status();
            if (in_array($status, array('completed', 'processing', 'shipped'))) {
                return;
            }
        } catch (Exception $e) {
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log('Admin Enhancement: Error getting order status: ' . $e->getMessage(), 'error');
            }
            return;
        }
        
        try {
            $order_id = $order->get_id();
            $current_status = $order->get_status();
            $order_total = $order->get_total();
        } catch (Exception $e) {
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log('Admin Enhancement: Error getting order data: ' . $e->getMessage(), 'error');
            }
            return;
        }
        
        ?>
        <div class="twintack-mark-paid-section" style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                <span style="font-size: 24px; margin-right: 10px;">💳</span>
                <h3 style="margin: 0; color: #856404; font-size: 18px;">Quick Payment Processing</h3>
            </div>
            
            <div style="margin-bottom: 15px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 5px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <strong>Order #<?php echo esc_html($order->get_order_number()); ?></strong><br>
                        <span style="color: #666;">Status: <?php echo esc_html(ucfirst($current_status)); ?></span><br>
                        <span style="color: #666;">Total: <?php echo wp_kses_post(wc_price($order_total)); ?></span>
                    </div>
                    <div style="text-align: right;">
                        <?php if ($order->get_billing_email()): ?>
                            <small style="color: #666;">Customer: <?php echo esc_html($order->get_billing_email()); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                <!-- Primary Action: Mark as Paid -->
                <button type="button" 
                        id="twintack-quick-mark-paid" 
                        data-order-id="<?php echo esc_attr($order_id); ?>" 
                        class="button button-primary button-large"
                        style="background: #28a745; border-color: #28a745; color: white; font-weight: bold; padding: 10px 25px; font-size: 14px;">
                    ✅ Mark as Paid & Ready to Ship
                </button>
                
                <!-- Secondary Action: Set to Invoice -->
                <?php if ($current_status !== 'invoiced' && $order->get_billing_email()): ?>
                <button type="button" 
                        id="twintack-quick-set-invoice" 
                        data-order-id="<?php echo esc_attr($order_id); ?>" 
                        class="button button-secondary"
                        style="background: #ff9800; border-color: #ff9800; color: white; font-weight: bold; padding: 10px 20px;">
                    📄 Set to Invoice (Send Payment Link)
                </button>
                <?php endif; ?>
                
                <!-- Status indicator -->
                <div style="margin-left: auto; padding: 8px 15px; background: #e1ecf4; border-radius: 20px; font-size: 13px; font-weight: bold; color: #0073aa;">
                    <?php 
                    $status_text = 'Ready for Payment Processing';
                    $status_color = '#0073aa';
                    
                    if ($current_status === 'invoiced') {
                        $status_text = 'Awaiting Customer Payment';
                        $status_color = '#ff9800';
                    } elseif (in_array($current_status, array('on-hold', 'pending'))) {
                        $status_text = 'Payment Required';
                        $status_color = '#dc3545';
                    }
                    ?>
                    <span style="color: <?php echo esc_attr($status_color); ?>;">⚡ <?php echo esc_html($status_text); ?></span>
                </div>
            </div>
            
            <!-- Help text -->
            <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 3px; font-size: 12px; color: #666;">
                <strong>💡 Quick Actions:</strong>
                <ul style="margin: 5px 0 0 20px; line-height: 1.4;">
                    <li><strong>Mark as Paid:</strong> Sets order to "Processing" status - ready for immediate fulfillment and Shippo sync</li>
                    <?php if ($order->get_billing_email()): ?>
                    <li><strong>Set to Invoice:</strong> Sets order to "Invoiced" status and sends payment link to customer</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Messages area -->
            <div id="twintack-quick-messages" style="margin-top: 15px;"></div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce = '<?php echo wp_create_nonce('twintack_payment_processing'); ?>';
            
            // Quick Mark as Paid
            $('#twintack-quick-mark-paid').on('click', function() {
                var button = $(this);
                var orderId = button.data('order-id');
                var originalText = button.text();
                
                if (!confirm('Are you sure you want to mark this order as paid? This will set the order to Processing status and trigger fulfillment.')) {
                    return;
                }
                
                button.prop('disabled', true).text('⏳ Processing...');
                
                $.post(ajaxurl, {
                    action: 'twintack_mark_paid',
                    order_id: orderId,
                    payment_method: 'twintack_manual',
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        showQuickMessage('✅ Order marked as paid successfully! Page will reload...', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        showQuickMessage('❌ Error: ' + (response.data.message || 'Unknown error'), 'error');
                        button.prop('disabled', false).text(originalText);
                    }
                }).fail(function() {
                    showQuickMessage('❌ Connection error occurred', 'error');
                    button.prop('disabled', false).text(originalText);
                });
            });
            
            // Quick Set to Invoice
            $('#twintack-quick-set-invoice').on('click', function() {
                var button = $(this);
                var orderId = button.data('order-id');
                var originalText = button.text();
                
                if (!confirm('Set this order to Invoice status? A payment link will be sent to the customer.')) {
                    return;
                }
                
                button.prop('disabled', true).text('⏳ Creating Invoice...');
                
                $.post(ajaxurl, {
                    action: 'twintack_set_pay_later',
                    order_id: orderId,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        showQuickMessage('✅ Order set to Invoice status! Page will reload...', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        showQuickMessage('❌ Error: ' + (response.data.message || 'Unknown error'), 'error');
                        button.prop('disabled', false).text(originalText);
                    }
                }).fail(function() {
                    showQuickMessage('❌ Connection error occurred', 'error');
                    button.prop('disabled', false).text(originalText);
                });
            });
            
            function showQuickMessage(message, type) {
                var messageClass = 'notice-info';
                var bgColor = '#e3f2fd';
                var borderColor = '#2196f3';
                
                if (type === 'success') {
                    messageClass = 'notice-success';
                    bgColor = '#d4edda';
                    borderColor = '#28a745';
                } else if (type === 'error') {
                    messageClass = 'notice-error';
                    bgColor = '#f8d7da';
                    borderColor = '#dc3545';
                }
                
                var messageHtml = '<div style="background: ' + bgColor + '; border: 2px solid ' + borderColor + '; color: #333; padding: 12px; border-radius: 5px; font-weight: bold;">' + message + '</div>';
                $('#twintack-quick-messages').html(messageHtml);
                
                if (type === 'success') {
                    setTimeout(function() {
                        $('#twintack-quick-messages').fadeOut();
                    }, 5000);
                }
            }
        });
        </script>
        <?php
    }
    
    /**
     * Safer wrapper for add_mark_as_paid_section
     */
    public function add_mark_as_paid_section_safe($order) {
        try {
            // Only proceed if we're in admin and on the right page
            if (!is_admin() || !function_exists('get_current_screen')) {
                return;
            }
            
            $screen = get_current_screen();
            if (!$screen || (strpos($screen->id, 'shop_order') === false && strpos($screen->id, 'wc-orders') === false)) {
                return;
            }
            
            // Call the main function
            $this->add_mark_as_paid_section($order);
            
        } catch (Exception $e) {
            if (function_exists('twintack_manual_payments_log')) {
                twintack_manual_payments_log('Admin Enhancement: Critical error in add_mark_as_paid_section_safe: ' . $e->getMessage(), 'error');
                twintack_manual_payments_log('Stack trace: ' . $e->getTraceAsString(), 'error');
            }
            
            // Display a safe fallback message instead of breaking the page
            echo '<div class="notice notice-error" style="margin: 20px 0; padding: 15px;"><p><strong>TwinTack Manual Payments:</strong> Payment processing section temporarily unavailable. Please check error logs.</p></div>';
        }
    }
    
    /**
     * Handle Mark as Paid AJAX request
     */
    public function handle_mark_paid() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'twintack_payment_processing')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        try {
            // Set payment method if provided
            if (!empty($payment_method) && function_exists('WC') && WC()->payment_gateways) {
                $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
                if (isset($available_gateways[$payment_method])) {
                    $order->set_payment_method($payment_method);
                    $order->set_payment_method_title($available_gateways[$payment_method]->get_title());
                }
            }
            
            // Mark as paid
            $order->payment_complete();
            $order->add_order_note('Payment marked as received manually by admin via TwinTack Manual Order Payments plugin.');
            
            // Trigger grip creation if applicable
            if (class_exists('TwinTack_Manual_Order_Payments') && 
                TwinTack_Manual_Order_Payments::get_option('auto_create_grip_posts', 'yes') === 'yes') {
                
                if (function_exists('twintack_trigger_grip_creation_from_order')) {
                    twintack_trigger_grip_creation_from_order($order->get_id());
                    twintack_manual_payments_log("Triggered grip creation for order {$order->get_id()}");
                }
            }
            
            twintack_manual_payments_log("Order {$order->get_id()} marked as paid successfully by user " . get_current_user_id());
            
            wp_send_json_success(array(
                'message' => 'Order marked as paid successfully!',
                'order_status' => $order->get_status(),
                'payment_method' => $order->get_payment_method_title()
            ));
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Error marking order {$order->get_id()} as paid: " . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
    }
    
    /**
     * Handle Process Payment AJAX request
     */
    public function handle_process_payment() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'twintack_payment_processing')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';
        
        if (empty($payment_method)) {
            wp_send_json_error(array('message' => 'Please select a payment method first'));
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        try {
            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            
            if (!isset($available_gateways[$payment_method])) {
                wp_send_json_error(array('message' => 'Selected payment method is not available'));
            }
            
            $gateway = $available_gateways[$payment_method];
            
            // Set payment method
            $order->set_payment_method($payment_method);
            $order->set_payment_method_title($gateway->get_title());
            
            // Mark as manual order and update status
            $order->update_meta_data('_twintack_manual_order', 'yes');
            $order->update_meta_data('_twintack_payment_processed', current_time('timestamp'));
            $order->update_status('pending', 'Payment method set for manual processing via TwinTack plugin.');
            
            twintack_manual_payments_log("Payment method set for order {$order->get_id()}: {$payment_method}");
            
            wp_send_json_success(array(
                'message' => sprintf('Payment method set to %s. Process payment manually through the gateway.', $gateway->get_title()),
                'order_status' => $order->get_status(),
                'payment_method' => $gateway->get_title()
            ));
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Error processing payment for order {$order->get_id()}: " . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
    }
    
    /**
     * Handle Send Payment Link AJAX request
     */
    public function handle_send_payment_link() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'twintack_payment_processing')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        // Check for customer email
        $customer_email = $order->get_billing_email();
        if (empty($customer_email)) {
            wp_send_json_error(array('message' => 'Customer email address is required to send payment link'));
        }
        
        try {
            // Get Stripe gateway settings to ensure we have API access
            $stripe_gateway = $this->get_stripe_gateway();
            if (!$stripe_gateway || !$stripe_gateway->secret_key) {
                wp_send_json_error(array('message' => 'Stripe is not properly configured'));
            }
            
            // Set Stripe API key for this request
            if (class_exists('WC_Stripe_API')) {
                WC_Stripe_API::set_secret_key($stripe_gateway->secret_key);
            } else {
                wp_send_json_error(array('message' => 'Stripe API not available'));
            }
            
            // Create Stripe Checkout Session
            $checkout_session = $this->create_stripe_checkout_session($order);
            
            if (empty($checkout_session->url)) {
                wp_send_json_error(array('message' => 'Failed to create payment link'));
            }
            
            // Set order payment method to Stripe
            $order->set_payment_method('stripe');
            $order->set_payment_method_title('Credit / Debit Card [STRIPE]');
            
            // Store checkout session ID in order meta
            $order->update_meta_data('_stripe_checkout_session_id', $checkout_session->id);
            
            // Add order note
            $order->add_order_note(sprintf(
                'Stripe Checkout Session created. Payment link sent to %s. Session ID: %s',
                $customer_email,
                $checkout_session->id
            ));
            
            $order->save();
            
            // Send professional email to customer
            $email_sent = $this->send_professional_payment_link_email($order, $checkout_session->url);
            
            twintack_manual_payments_log("Stripe Checkout Session created for order {$order->get_id()}: {$checkout_session->id}");
            
            wp_send_json_success(array(
                'message' => sprintf(
                    'Payment link created and %s to %s. Amount: %s',
                    $email_sent ? 'emailed' : 'ready to send',
                    $customer_email,
                    wc_price($order->get_total())
                ),
                'payment_url' => $checkout_session->url,
                'session_id' => $checkout_session->id,
                'customer_email' => $customer_email,
                'email_sent' => $email_sent
            ));
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Error creating Stripe Checkout Session for order {$order->get_id()}: " . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
    }
    
    /**
     * Handle Set Pay Later (Invoice) AJAX request
     */
    public function handle_set_pay_later() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'twintack_payment_processing')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        // Check for customer email
        $customer_email = $order->get_billing_email();
        if (empty($customer_email)) {
            wp_send_json_error(array('message' => 'Customer email address is required for Pay Later option'));
        }
        
        try {
            // Set payment method to TwinTack Invoice
            $order->set_payment_method('twintack_invoice');
            $order->set_payment_method_title('Invoice Payment (Pay Later)');
            
            // Mark as manual order
            $order->update_meta_data('_twintack_manual_order', 'yes');
            $order->update_meta_data('_twintack_payment_processed', current_time('timestamp'));
            
            // Set order to invoiced status - this will trigger Shippo status mapping
            $order->update_status('invoiced', 'Order set to Pay Later (Invoice). Customer will receive payment link.');
            
            // Mark as needing payment link
            $order->update_meta_data('_twintack_needs_payment_link', 'yes');
            $order->save();
            
            // Create Stripe checkout session for when customer is ready to pay
            $stripe_gateway = $this->get_stripe_gateway();
            if ($stripe_gateway && $stripe_gateway->secret_key) {
                if (class_exists('WC_Stripe_API')) {
                    WC_Stripe_API::set_secret_key($stripe_gateway->secret_key);
                    
                    // Create checkout session
                    $checkout_session = $this->create_stripe_checkout_session($order);
                    
                    if (!empty($checkout_session->url)) {
                        // Store checkout session ID
                        $order->update_meta_data('_stripe_checkout_session_id', $checkout_session->id);
                        $order->save();
                        
                        // Send invoice email with payment link
                        $email_sent = $this->send_invoice_email($order, $checkout_session->url);
                        
                        twintack_manual_payments_log("Invoice created for order {$order_id} with Stripe checkout session: {$checkout_session->id}");
                        
                        wp_send_json_success(array(
                            'message' => sprintf(
                                'Order set to Pay Later (Invoice). %s to %s. Customer can pay via Stripe when ready.',
                                $email_sent ? 'Invoice email sent' : 'Invoice ready',
                                $customer_email
                            ),
                            'order_status' => 'invoiced',
                            'shippo_status' => 'Payment Pending',
                            'payment_url' => $checkout_session->url,
                            'email_sent' => $email_sent
                        ));
                    }
                }
            }
            
            // Fallback: Order set to invoice but no Stripe session
            wp_send_json_success(array(
                'message' => sprintf(
                    'Order set to Pay Later (Invoice). Customer: %s. Payment link can be sent separately.',
                    $customer_email
                ),
                'order_status' => 'invoiced',
                'shippo_status' => 'Payment Pending'
            ));
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Error setting order {$order_id} to Pay Later: " . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
    }
    
    /**
     * Send invoice email to customer
     */
    private function send_invoice_email($order, $payment_url) {
        $customer_email = $order->get_billing_email();
        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        
        if (empty($customer_name)) {
            $customer_name = 'Valued Customer';
        }
        
        $subject = sprintf('Invoice for Order #%s - %s', $order->get_order_number(), get_bloginfo('name'));
        
        $message = sprintf("
Dear %s,

Thank you for your order! We've created an invoice for your recent purchase.

ORDER DETAILS:
- Order Number: #%s
- Total Amount: %s
- Order Date: %s

PAYMENT OPTIONS:
You can pay this invoice at your convenience using the secure payment link below:

%s

This payment link accepts all major credit cards and is fully secure. The link will remain active for 24 hours.

If you have any questions about your order or need assistance, please don't hesitate to contact us.

Thank you for choosing %s!

Best regards,
%s Team

---
This invoice was generated automatically. For support, please contact us directly.
        ",
            $customer_name,
            $order->get_order_number(),
            wc_price($order->get_total()),
            $order->get_date_created()->format('F j, Y'),
            $payment_url,
            get_bloginfo('name'),
            get_bloginfo('name')
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        // Add debugging information before sending
        twintack_manual_payments_log("Attempting to send invoice email to: {$customer_email} for order {$order->get_id()}");
        twintack_manual_payments_log("Email subject: {$subject}");
        twintack_manual_payments_log("From address: " . get_option('admin_email'));
        
        // Check if WordPress can send emails
        if (!function_exists('wp_mail')) {
            twintack_manual_payments_log("wp_mail function not available", 'error');
            return false;
        }
        
        // Validate email address
        if (!is_email($customer_email)) {
            twintack_manual_payments_log("Invalid customer email address: {$customer_email}", 'error');
            return false;
        }
        
        $email_sent = wp_mail($customer_email, $subject, $message, $headers);
        
        if ($email_sent) {
            $order->add_order_note("Invoice email successfully sent to customer: {$customer_email}");
            twintack_manual_payments_log("✅ Invoice email sent successfully to {$customer_email} for order {$order->get_id()}");
        } else {
            // Get the last error from WordPress mail system
            global $phpmailer;
            $mail_error = '';
            if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                $mail_error = $phpmailer->ErrorInfo;
            }
            
            $order->add_order_note("❌ Failed to send invoice email to customer: {$customer_email}. Error: {$mail_error}");
            twintack_manual_payments_log("❌ Failed to send invoice email to {$customer_email} for order {$order->get_id()}. Error: {$mail_error}", 'error');
            
            // Try alternative email method using WooCommerce mailer
            return $this->send_invoice_email_via_wc_mailer($order, $payment_url);
        }
        
        return $email_sent;
    }
    
    /**
     * Send invoice email using WooCommerce mailer (fallback method)
     */
    private function send_invoice_email_via_wc_mailer($order, $payment_url) {
        if (!class_exists('WC_Emails')) {
            return false;
        }
        
        $customer_email = $order->get_billing_email();
        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        
        if (empty($customer_name)) {
            $customer_name = 'Valued Customer';
        }
        
        try {
            $mailer = WC()->mailer();
            $subject = sprintf('Invoice for Order #%s - %s', $order->get_order_number(), get_bloginfo('name'));
            
            // Create HTML message
            $message = sprintf("
                <h2>Invoice for Order #%s</h2>
                <p>Dear %s,</p>
                <p>Thank you for your order! We've created an invoice for your recent purchase.</p>
                
                <h3>Order Details:</h3>
                <ul>
                    <li><strong>Order Number:</strong> #%s</li>
                    <li><strong>Total Amount:</strong> %s</li>
                    <li><strong>Order Date:</strong> %s</li>
                </ul>
                
                <h3>Payment Options:</h3>
                <p>You can pay this invoice at your convenience using the secure payment link below:</p>
                <p><a href='%s' style='background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;'>Pay Invoice Now</a></p>
                
                <p><em>This payment link accepts all major credit cards and is fully secure. The link will remain active for 24 hours.</em></p>
                
                <p>If you have any questions about your order or need assistance, please don't hesitate to contact us.</p>
                
                <p>Thank you for choosing %s!</p>
                
                <p>Best regards,<br>%s Team</p>
                
                <hr>
                <p><small>This invoice was generated automatically. For support, please contact us directly.</small></p>
            ",
                $order->get_order_number(),
                $customer_name,
                $order->get_order_number(),
                wc_price($order->get_total()),
                $order->get_date_created()->format('F j, Y'),
                $payment_url,
                get_bloginfo('name'),
                get_bloginfo('name')
            );
            
            // Wrap in WooCommerce email template
            $message = $mailer->wrap_message($subject, $message);
            
            // Send using WooCommerce mailer
            $sent = $mailer->send($customer_email, $subject, $message);
            
            if ($sent) {
                $order->add_order_note("✅ Invoice email sent via WooCommerce mailer to: {$customer_email}");
                twintack_manual_payments_log("✅ Invoice email sent via WC mailer to {$customer_email} for order {$order->get_id()}");
                return true;
            } else {
                $order->add_order_note("❌ Failed to send invoice email via WooCommerce mailer to: {$customer_email}");
                twintack_manual_payments_log("❌ Failed to send invoice email via WC mailer to {$customer_email} for order {$order->get_id()}", 'error');
                return false;
            }
            
        } catch (Exception $e) {
            $order->add_order_note("❌ Exception sending invoice email: " . $e->getMessage());
            twintack_manual_payments_log("❌ Exception sending invoice email for order {$order->get_id()}: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Limit payment gateways for invoiced orders to Stripe only
     */
    public function limit_payment_gateways_for_invoiced_orders($gateways) {
        // Only apply this on frontend for customers
        if (is_admin() || !is_wc_endpoint_url('order-pay')) {
            return $gateways;
        }
        
        global $wp;
        if (!isset($wp->query_vars['order-pay'])) {
            return $gateways;
        }
        
        $order_id = absint($wp->query_vars['order-pay']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return $gateways;
        }
        
        // If this is an invoiced order, only show Stripe
        if ($order->get_status() === 'invoiced' || $order->get_payment_method() === 'twintack_invoice') {
            $stripe_only = array();
            
            // Keep only Stripe gateway
            if (isset($gateways['stripe'])) {
                $stripe_only['stripe'] = $gateways['stripe'];
                
                // Update the title to be more descriptive
                $stripe_only['stripe']->title = 'Credit / Debit Card (Stripe)';
                $stripe_only['stripe']->description = 'Pay securely with your credit or debit card. This is the only payment method available for invoice orders.';
                
                twintack_manual_payments_log("Limited payment options to Stripe only for invoiced order {$order_id}");
                
                return $stripe_only;
            } else {
                // No Stripe available - this shouldn't happen but log it
                twintack_manual_payments_log("WARNING: Stripe not available for invoiced order {$order_id}", 'error');
                return array();
            }
        }
        
        return $gateways;
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on order edit pages
        if (!in_array($hook, array('post.php', 'post-new.php', 'woocommerce_page_wc-orders'))) {
            return;
        }
        
        global $post_type;
        $screen = get_current_screen();
        
        // Check if we're on an order page
        if (($post_type !== 'shop_order') && 
            (!$screen || strpos($screen->id, 'wc-orders') === false)) {
            return;
        }
        
        $script_path = TWINTACK_MANUAL_PAYMENTS_PLUGIN_URL . 'assets/js/admin-order-payments.js';
        
        wp_enqueue_script(
            'twintack-admin-order-payments',
            $script_path,
            array('jquery'),
            TWINTACK_MANUAL_PAYMENTS_VERSION,
            true
        );
        
        wp_localize_script('twintack-admin-order-payments', 'twintackAdminPayments', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('twintack_payment_processing'),
            'messages' => array(
                'processing' => 'Processing...',
                'success' => 'Success!',
                'error' => 'Error:',
                'confirm_mark_paid' => 'Are you sure you want to mark this order as paid?',
                'confirm_send_link' => 'Send payment link to customer?',
                'select_payment_method' => 'Please select a payment method first.'
            )
        ));
        
        twintack_manual_payments_log("Admin scripts enqueued for hook: {$hook}");
    }
    
    /**
     * Initialize customer payment handling
     */
    public function init_customer_payment_handling() {
        // Allow manual orders to be paid by customers
        add_filter('woocommerce_order_needs_payment', array($this, 'allow_manual_order_payment'), 10, 3);
        
        // Redirect to Stripe checkout if session exists
        add_action('woocommerce_pay_order_before_payment', array($this, 'redirect_to_stripe_checkout'));
    }
    
    /**
     * Allow manual orders to be paid by customers
     */
    public function allow_manual_order_payment($needs_payment, $order, $valid_statuses) {
        // Debug logging
        twintack_manual_payments_log("Checking if order {$order->get_id()} needs payment. Current status: {$order->get_status()}, Has session: " . ($order->get_meta('_stripe_checkout_session_id') ? 'yes' : 'no'));
        
        // If this is a TwinTack manual payment order and it's invoiced, allow payment
        if ($order->get_meta('_stripe_checkout_session_id') && 
            in_array($order->get_status(), array('pending', 'on-hold', 'invoiced'))) {
            twintack_manual_payments_log("Order {$order->get_id()} allowed for payment (has session and valid status)");
            return true;
        }
        
        // Allow invoiced orders to be paid
        if ($order->get_status() === 'invoiced' && $order->get_total() > 0) {
            twintack_manual_payments_log("Order {$order->get_id()} allowed for payment (invoiced status)");
            return true;
        }
        
        // Also allow if the order total is greater than 0 and status is pending
        if ($order->get_total() > 0 && $order->get_status() === 'pending') {
            twintack_manual_payments_log("Order {$order->get_id()} allowed for payment (pending status with total > 0)");
            return true;
        }
        
        return $needs_payment;
    }
    
    /**
     * Redirect to Stripe checkout if session exists
     */
    public function redirect_to_stripe_checkout() {
        global $wp;
        $order_id = absint($wp->query_vars['order-pay']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        $session_id = $order->get_meta('_stripe_checkout_session_id');
        if (!$session_id) {
            return;
        }
        
        // Get the Stripe checkout URL
        $stripe_gateway = $this->get_stripe_gateway();
        if (!$stripe_gateway || !$stripe_gateway->secret_key) {
            return;
        }
        
        try {
            WC_Stripe_API::set_secret_key($stripe_gateway->secret_key);
            $session = WC_Stripe_API::request(array(), "checkout/sessions/{$session_id}", 'GET');
            
            if (isset($session->url) && !empty($session->url)) {
                twintack_manual_payments_log("Redirecting order {$order_id} to Stripe checkout: {$session->url}");
                wp_redirect($session->url);
                exit;
            }
        } catch (Exception $e) {
            twintack_manual_payments_log("Error retrieving checkout session for redirect: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Handle payment return from Stripe
     */
    public function handle_stripe_payment_return($order_id) {
        if (!isset($_GET['twintack_payment']) || $_GET['twintack_payment'] !== 'success') {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $session_id = $order->get_meta('_stripe_checkout_session_id');
        if (!$session_id) {
            return;
        }
        
        // Check if payment was successful
        $stripe_gateway = $this->get_stripe_gateway();
        if (!$stripe_gateway || !$stripe_gateway->secret_key) {
            return;
        }
        
        try {
            WC_Stripe_API::set_secret_key($stripe_gateway->secret_key);
            $session = WC_Stripe_API::request(array(), "checkout/sessions/{$session_id}", 'GET');
            
            if (isset($session->payment_status) && $session->payment_status === 'paid') {
                if ($order->get_status() !== 'completed' && $order->get_status() !== 'processing') {
                    $order->payment_complete($session->payment_intent);
                    $order->add_order_note('Payment completed via Stripe Checkout Session: ' . $session_id);
                    
                    // Trigger grip creation if applicable
                    if (function_exists('twintack_trigger_grip_creation_from_order')) {
                        twintack_trigger_grip_creation_from_order($order->get_id());
                    }
                    
                    twintack_manual_payments_log("Payment completed for order {$order_id} via Stripe session {$session_id}");
                }
            }
        } catch (Exception $e) {
            twintack_manual_payments_log("Error checking payment status: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Get Stripe gateway instance
     */
    private function get_stripe_gateway() {
        if (!function_exists('WC')) {
            return false;
        }
        
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();
        return isset($gateways['stripe']) ? $gateways['stripe'] : false;
    }
    
    /**
     * Create Stripe Checkout Session for an order
     */
    private function create_stripe_checkout_session($order) {
        $order_total = $order->get_total();
        $currency = strtolower($order->get_currency());
        
        // Convert amount to Stripe format (cents for USD, smallest currency unit)
        $stripe_amount = round($order_total * 100);
        
        // Prepare line items for the checkout session
        $line_items = array(
            array(
                'price_data' => array(
                    'currency' => $currency,
                    'product_data' => array(
                        'name' => sprintf('Order #%s - %s', $order->get_order_number(), get_bloginfo('name')),
                        'description' => $this->get_order_description($order),
                    ),
                    'unit_amount' => $stripe_amount,
                ),
                'quantity' => 1,
            )
        );
        
        // Create success and cancel URLs pointing to customer website
        $return_url = add_query_arg(array(
            'order-received' => $order->get_id(),
            'key' => $order->get_order_key(),
            'twintack_payment' => 'success',
        ), wc_get_checkout_url());
        
        $cancel_url = add_query_arg(array(
            'twintack_payment' => 'cancelled',
            'order_id' => $order->get_id(),
            'key' => $order->get_order_key(),
        ), $order->get_checkout_payment_url());
        
        // Prepare checkout session parameters (standard hosted mode)
        $session_params = array(
            'mode' => 'payment',
            'line_items' => $line_items,
            'customer_email' => $order->get_billing_email(),
            'success_url' => $return_url,
            'cancel_url' => $cancel_url,
            'client_reference_id' => $order->get_id(),
            'expires_at' => time() + (24 * 60 * 60), // 24 hours
            'metadata' => array(
                'order_id' => $order->get_id(),
                'woocommerce_order' => 'true',
                'twintack_manual_payment' => 'true',
            ),
            'payment_intent_data' => array(
                'metadata' => array(
                    'order_id' => $order->get_id(),
                    'order_number' => $order->get_order_number(),
                ),
            ),
        );
        
        // Create the checkout session via Stripe API
        try {
            twintack_manual_payments_log("Creating Stripe checkout session with params: " . print_r($session_params, true));
            
            $response = WC_Stripe_API::request($session_params, 'checkout/sessions');
            
            if (!$response) {
                twintack_manual_payments_log("Stripe API returned empty response", 'error');
                throw new Exception('Empty response from Stripe API');
            }
            
            if (isset($response->error)) {
                twintack_manual_payments_log("Stripe API error: " . print_r($response->error, true), 'error');
                throw new Exception('Stripe API error: ' . $response->error->message);
            }
            
            twintack_manual_payments_log("Stripe checkout session created successfully: " . $response->id);
            return $response;
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Exception in checkout session creation: " . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Get order description for checkout session
     */
    private function get_order_description($order) {
        $items = array();
        foreach ($order->get_items() as $item) {
            $product_name = $item->get_name();
            $quantity = $item->get_quantity();
            $items[] = $quantity > 1 ? "{$product_name} (×{$quantity})" : $product_name;
        }
        
        if (empty($items)) {
            return sprintf('Order #%s', $order->get_order_number());
        }
        
        return implode(', ', array_slice($items, 0, 3)) . (count($items) > 3 ? ' and more...' : '');
    }
    
    /**
     * Send professional payment link email using WooCommerce email system
     */
    private function send_professional_payment_link_email($order, $payment_url) {
        // Use WooCommerce's email system for professional formatting
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();
        
        if (isset($emails['TwinTack_Payment_Link_Email'])) {
            $email = $emails['TwinTack_Payment_Link_Email'];
            $email->trigger($order->get_id(), $payment_url, $order);
            
            twintack_manual_payments_log("Professional payment link email sent to {$order->get_billing_email()} for order {$order->get_id()}");
            return true;
        } else {
            // Fallback to basic email if WooCommerce email not available
            return $this->send_basic_payment_link_email($order, $payment_url);
        }
    }
    
    /**
     * Send basic payment link email (fallback method)
     */
    private function send_basic_payment_link_email($order, $payment_url) {
        $customer_email = $order->get_billing_email();
        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        
        if (empty($customer_name)) {
            $customer_name = 'Valued Customer';
        }
        
        $subject = sprintf('Payment Link for Order #%s - %s', $order->get_order_number(), get_bloginfo('name'));
        
        $message = sprintf("
Dear %s,

Please complete your payment for Order #%s using the secure payment link below:

%s

Order Details:
- Order Number: #%s
- Amount: %s
- Order Date: %s

This payment link is secure and will expire in 24 hours. If you have any questions, please contact us.

Thank you,
%s Team

---
This is an automated message. Please do not reply to this email.
        ",
            $customer_name,
            $order->get_order_number(),
            $payment_url,
            $order->get_order_number(),
            wc_price($order->get_total()),
            $order->get_date_created()->format('F j, Y'),
            get_bloginfo('name')
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        $email_sent = wp_mail($customer_email, $subject, $message, $headers);
        
        if ($email_sent) {
            twintack_manual_payments_log("Payment link email sent to {$customer_email} for order {$order->get_id()}");
        } else {
            twintack_manual_payments_log("Failed to send payment link email to {$customer_email} for order {$order->get_id()}", 'error');
        }
        
        return $email_sent;
    }
    
    /**
     * Handle force Shippo sync AJAX request
     */
    public function handle_force_shippo_sync() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'twintack_payment_processing')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        try {
            // Force trigger Shippo sync for this order
            $wc_status = $order->get_status();
            
            twintack_manual_payments_log("Manual Shippo sync requested for order #{$order_id} with status '{$wc_status}'");
            
            // Trigger both possible Shippo integration paths
            
            // 1. Trigger our custom action
            do_action('twintack_shippo_status_updated', $order_id, 'Manual Sync', $wc_status);
            
            // 2. If the main Shippo integration class exists, call it directly
            if (class_exists('TwinTack_Shippo_Integration')) {
                $shippo_integration = TwinTack_Shippo_Integration::get_instance();
                if (method_exists($shippo_integration, 'sync_order_with_shippo')) {
                    $shippo_integration->sync_order_with_shippo($order_id, '', $wc_status, $order);
                }
            }
            
            // 3. Also trigger the standard WooCommerce hook in case other Shippo plugins are listening
            do_action('woocommerce_order_status_changed', $order_id, 'pending', $wc_status, $order);
            
            $order->add_order_note('Manual Shippo sync triggered by admin via TwinTack plugin.');
            
            wp_send_json_success(array(
                'message' => 'Shippo sync triggered successfully',
                'order_id' => $order_id,
                'status' => $wc_status
            ));
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Error in manual Shippo sync: " . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => 'Sync failed: ' . $e->getMessage()));
        }
    }
    
    /**
     * Add JavaScript for payment button handlers
     */
    private function add_payment_buttons_javascript() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce = '<?php echo wp_create_nonce('twintack_payment_processing'); ?>';
            
            // Force Shippo Sync button handler
            $('#twintack-force-shippo-sync').on('click', function() {
                var button = $(this);
                var orderId = button.data('order-id');
                var originalText = button.text();
                
                button.prop('disabled', true).text('🚢 Syncing...');
                
                $.post(ajaxurl, {
                    action: 'twintack_force_shippo_sync',
                    order_id: orderId,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        $('#twintack-payment-messages').html('<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; border-radius: 3px; margin-top: 10px;">✅ ' + response.data.message + '</div>');
                        // Optionally reload the page to show updated order notes
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $('#twintack-payment-messages').html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 3px; margin-top: 10px;">❌ ' + response.data.message + '</div>');
                    }
                }).fail(function() {
                    $('#twintack-payment-messages').html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 3px; margin-top: 10px;">❌ Connection error occurred</div>');
                }).always(function() {
                    button.prop('disabled', false).text(originalText);
                });
            });
            
            // Note: Other button handlers (Pay Now, Pay Later, etc.) would be added here
            // but they may already exist elsewhere in the codebase
        });
        </script>
        <?php
    }
    
    /**
     * Add tracking information section to order admin page
     */
    public function add_tracking_section($order) {
        if (!$order instanceof WC_Order) {
            return;
        }
        
        $order_id = $order->get_id();
        $current_tracking = $order->get_meta('_shippo_tracking_number');
        $current_carrier = $order->get_meta('_shippo_tracking_carrier');
        $current_status = $order->get_meta('_shippo_tracking_status');
        
        // Only show for shipped orders or orders that need tracking
        $order_status = $order->get_status();
        if (!in_array($order_status, array('shipped-unpaid', 'processing', 'completed'))) {
            return;
        }
        
        ?>
        <div class="twintack-tracking-section" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px;">
            <h3 style="margin-top: 0;">📦 Shipment Tracking Information</h3>
            
            <table class="form-table" style="margin: 0;">
                <tr>
                    <th style="width: 150px;">
                        <label for="twintack_tracking_number">Tracking Number:</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="twintack_tracking_number" 
                               name="twintack_tracking_number" 
                               value="<?php echo esc_attr($current_tracking); ?>" 
                               style="width: 300px;" 
                               placeholder="Enter tracking number">
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="twintack_tracking_carrier">Carrier:</label>
                    </th>
                    <td>
                        <select id="twintack_tracking_carrier" name="twintack_tracking_carrier" style="width: 300px;">
                            <option value="">Select Carrier</option>
                            <option value="USPS" <?php selected($current_carrier, 'USPS'); ?>>USPS</option>
                            <option value="UPS" <?php selected($current_carrier, 'UPS'); ?>>UPS</option>
                            <option value="FedEx" <?php selected($current_carrier, 'FedEx'); ?>>FedEx</option>
                            <option value="DHL" <?php selected($current_carrier, 'DHL'); ?>>DHL</option>
                            <option value="Other" <?php selected($current_carrier, 'Other'); ?>>Other</option>
                        </select>
                    </td>
                </tr>
                <?php if ($current_status): ?>
                <tr>
                    <th>Current Status:</th>
                    <td><strong><?php echo esc_html($current_status); ?></strong></td>
                </tr>
                <?php endif; ?>
            </table>
            
            <div style="margin-top: 15px;">
                <button type="button" 
                        id="update-tracking-btn" 
                        class="button button-primary"
                        data-order-id="<?php echo $order_id; ?>">
                    Update Tracking Info
                </button>
                
                <?php if ($current_tracking): ?>
                <button type="button" 
                        id="send-tracking-email-btn" 
                        class="button"
                        data-order-id="<?php echo $order_id; ?>"
                        style="margin-left: 10px;">
                    📧 Send Tracking Email
                </button>
                <?php endif; ?>
            </div>
            
            <div id="tracking-result" style="margin-top: 10px;"></div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#update-tracking-btn').on('click', function() {
                var button = $(this);
                var orderId = button.data('order-id');
                var trackingNumber = $('#twintack_tracking_number').val();
                var carrier = $('#twintack_tracking_carrier').val();
                var resultDiv = $('#tracking-result');
                
                if (!trackingNumber.trim()) {
                    alert('Please enter a tracking number');
                    return;
                }
                
                button.prop('disabled', true).text('Updating...');
                resultDiv.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'twintack_update_tracking',
                        order_id: orderId,
                        tracking_number: trackingNumber,
                        carrier: carrier,
                        nonce: '<?php echo wp_create_nonce('twintack_tracking_' . $order_id); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            resultDiv.html('<div style="color: green; padding: 8px; background: #d4f6d4; border-radius: 3px;">✅ ' + response.data.message + '</div>');
                            
                            // Show send email button if tracking was added
                            if (trackingNumber && !$('#send-tracking-email-btn').length) {
                                button.after('<button type="button" id="send-tracking-email-btn" class="button" data-order-id="' + orderId + '" style="margin-left: 10px;">📧 Send Tracking Email</button>');
                            }
                            
                            // Reload page after 2 seconds to show updated info
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            resultDiv.html('<div style="color: red; padding: 8px; background: #f8d7da; border-radius: 3px;">❌ ' + response.data + '</div>');
                        }
                    },
                    error: function() {
                        resultDiv.html('<div style="color: red; padding: 8px; background: #f8d7da; border-radius: 3px;">❌ Request failed</div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Update Tracking Info');
                    }
                });
            });
            
            // Handle send tracking email button
            $(document).on('click', '#send-tracking-email-btn', function() {
                var button = $(this);
                var orderId = button.data('order-id');
                var resultDiv = $('#tracking-result');
                
                button.prop('disabled', true).text('Sending...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'twintack_send_tracking_email',
                        order_id: orderId,
                        nonce: '<?php echo wp_create_nonce('twintack_tracking_email_' . $order_id); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            resultDiv.html('<div style="color: green; padding: 8px; background: #d4f6d4; border-radius: 3px;">✅ Tracking email sent!</div>');
                        } else {
                            resultDiv.html('<div style="color: red; padding: 8px; background: #f8d7da; border-radius: 3px;">❌ ' + response.data + '</div>');
                        }
                    },
                    complete: function() {
                        button.prop('disabled', false).text('📧 Send Tracking Email');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle AJAX request to update tracking information
     */
    public function handle_update_tracking() {
        // Check nonce
        $order_id = intval($_POST['order_id']);
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_tracking_' . $order_id)) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $tracking_number = sanitize_text_field($_POST['tracking_number']);
        $carrier = sanitize_text_field($_POST['carrier']);
        
        if (empty($tracking_number)) {
            wp_send_json_error('Tracking number is required');
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order not found');
        }
        
        try {
            // Update tracking meta fields
            $order->update_meta_data('_shippo_tracking_number', $tracking_number);
            if ($carrier) {
                $order->update_meta_data('_shippo_tracking_carrier', $carrier);
            }
            $order->update_meta_data('_shippo_tracking_status', 'SHIPPED');
            $order->update_meta_data('_tracking_updated_manually', current_time('timestamp'));
            
            $order->save();
            
            // Add order note
            $note = 'Tracking information updated manually:' . "\n";
            $note .= 'Tracking Number: ' . $tracking_number;
            if ($carrier) {
                $note .= "\n" . 'Carrier: ' . $carrier;
            }
            $order->add_order_note($note);
            
            twintack_manual_payments_log("Tracking updated for order {$order_id}: {$tracking_number} ({$carrier})");
            
            wp_send_json_success(array(
                'message' => 'Tracking information updated successfully!',
                'tracking_number' => $tracking_number,
                'carrier' => $carrier
            ));
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Error updating tracking for order {$order_id}: " . $e->getMessage(), 'error');
            wp_send_json_error('Error updating tracking: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle AJAX request to send tracking email
     */
    public function handle_send_tracking_email() {
        // Check nonce
        $order_id = intval($_POST['order_id']);
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_tracking_email_' . $order_id)) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order not found');
        }
        
        $tracking_number = $order->get_meta('_shippo_tracking_number');
        if (!$tracking_number) {
            wp_send_json_error('No tracking number available');
        }
        
        try {
            // Send a processing order email (which includes tracking info)
            $mailer = WC()->mailer();
            $emails = $mailer->get_emails();
            
            if (isset($emails['WC_Email_Customer_Processing_Order'])) {
                $emails['WC_Email_Customer_Processing_Order']->trigger($order_id, $order);
                
                // Add order note
                $order->add_order_note('Tracking notification email sent to customer: ' . $order->get_billing_email() . ' (Tracking: ' . $tracking_number . ')');
                
                twintack_manual_payments_log("Tracking email sent for order {$order_id} to " . $order->get_billing_email());
                
                wp_send_json_success('Tracking email sent successfully!');
            } else {
                wp_send_json_error('Email system not available');
            }
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Error sending tracking email for order {$order_id}: " . $e->getMessage(), 'error');
            wp_send_json_error('Error sending email: ' . $e->getMessage());
        }
    }
    
    /**
     * Add TwinTack Order Control section with PO Number and PDF Invoice
     */
    public function add_twintack_order_control_section($order) {
        if (!$order instanceof WC_Order) {
            return;
        }
        
        $order_id = $order->get_id();
        $current_po_number = $order->get_meta('_twintack_po_number');
        
        ?>
        <div class="twintack-order-control-section" style="margin: 20px 0; padding: 20px; background: #fff; border: 2px solid #0073aa; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; margin-bottom: 20px;">
                <span style="font-size: 24px; margin-right: 12px;">🏢</span>
                <h3 style="margin: 0; color: #0073aa; font-size: 20px;">TwinTack Order Control</h3>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start;">
                <!-- PO Number Section -->
                <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; border: 1px solid #ddd;">
                    <h4 style="margin: 0 0 15px 0; color: #333; font-size: 16px;">📋 Purchase Order Information</h4>
                    
                    <div style="margin-bottom: 15px;">
                        <label for="twintack_po_number" style="display: block; margin-bottom: 8px; font-weight: bold; color: #555;">
                            Original PO Number:
                        </label>
                        <input type="text" 
                               id="twintack_po_number" 
                               name="twintack_po_number" 
                               value="<?php echo esc_attr($current_po_number); ?>" 
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;" 
                               placeholder="Enter customer PO number">
                        <small style="color: #666; font-style: italic;">This PO number will appear on the PDF invoice</small>
                    </div>
                    
                    <button type="button" 
                            id="save-po-number-btn" 
                            class="button button-primary"
                            data-order-id="<?php echo $order_id; ?>"
                            style="background: #28a745; border-color: #28a745;">
                        💾 Save PO Number
                    </button>
                </div>
                
                <!-- PDF Invoice Section -->
                <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; border: 1px solid #ddd;">
                    <h4 style="margin: 0 0 15px 0; color: #333; font-size: 16px;">📄 Invoice Generation</h4>
                    
                    <div style="margin-bottom: 15px;">
                        <p style="margin: 0 0 10px 0; color: #666; line-height: 1.5;">
                            Generate a professional invoice for this order including all order details, customer information, and PO number.
                        </p>
                        
                        <?php
                        // Check PDF availability
                        if (!class_exists('TwinTack_PDF_Invoice_Generator')) {
                            require_once TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-pdf-invoice-generator.php';
                        }
                        $pdf_generator = TwinTack_PDF_Invoice_Generator::get_instance();
                        $pdf_available = $pdf_generator->is_pdf_available();
                        ?>
                        
                        <div style="background: <?php echo $pdf_available ? '#e8f5e8' : '#fff8e1'; ?>; padding: 8px; border-radius: 3px; border: 1px solid <?php echo $pdf_available ? '#4caf50' : '#ff9800'; ?>; margin-bottom: 10px;">
                            <small style="color: <?php echo $pdf_available ? '#2e7d32' : '#e65100'; ?>;">
                                <strong><?php echo $pdf_available ? '📄 PDF' : '📋 HTML'; ?> Format:</strong> 
                                <?php echo $pdf_available ? 'Professional PDF will be generated' : 'Printable HTML will be generated (TCPDF not available)'; ?>
                            </small>
                        </div>
                        
                        <?php if ($current_po_number): ?>
                        <div style="background: #d4edda; padding: 10px; border-radius: 3px; border: 1px solid #c3e6cb; margin-bottom: 10px;">
                            <small style="color: #155724;">
                                <strong>✅ PO Number:</strong> <?php echo esc_html($current_po_number); ?> will be included in the invoice
                            </small>
                        </div>
                        <?php else: ?>
                        <div style="background: #fff3cd; padding: 10px; border-radius: 3px; border: 1px solid #ffeaa7; margin-bottom: 10px;">
                            <small style="color: #856404;">
                                <strong>⚠️ Note:</strong> No PO number set. Add one above for complete invoice.
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="button" 
                            id="generate-pdf-invoice-btn" 
                            class="button button-secondary"
                            data-order-id="<?php echo $order_id; ?>"
                            style="background: #dc3545; border-color: #dc3545; color: white; font-weight: bold;">
                        <?php echo $pdf_available ? '📄 Generate PDF Invoice' : '📋 Generate HTML Invoice'; ?>
                    </button>
                </div>
            </div>
            
            <div id="twintack-control-result" style="margin-top: 20px;"></div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Save PO Number
            $('#save-po-number-btn').on('click', function() {
                var button = $(this);
                var orderId = button.data('order-id');
                var poNumber = $('#twintack_po_number').val().trim();
                var resultDiv = $('#twintack-control-result');
                
                button.prop('disabled', true).text('💾 Saving...');
                resultDiv.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'twintack_save_po_number',
                        order_id: orderId,
                        po_number: poNumber,
                        nonce: '<?php echo wp_create_nonce('twintack_po_number_' . $order_id); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            resultDiv.html('<div style="color: green; padding: 12px; background: #d4f6d4; border-radius: 5px; border: 1px solid #28a745;">✅ ' + response.data.message + '</div>');
                            
                            // Update the PO display in PDF section
                            if (poNumber) {
                                $('.pdf-po-status').html('<div style="background: #d4edda; padding: 10px; border-radius: 3px; border: 1px solid #c3e6cb; margin-bottom: 10px;"><small style="color: #155724;"><strong>✅ PO Number:</strong> ' + poNumber + ' will be included in the invoice</small></div>');
                            }
                            
                            // Reload page after 2 seconds to show updated info
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            resultDiv.html('<div style="color: red; padding: 12px; background: #f8d7da; border-radius: 5px; border: 1px solid #dc3545;">❌ ' + response.data + '</div>');
                        }
                    },
                    error: function() {
                        resultDiv.html('<div style="color: red; padding: 12px; background: #f8d7da; border-radius: 5px; border: 1px solid #dc3545;">❌ Request failed</div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('💾 Save PO Number');
                    }
                });
            });
            
            // Generate PDF Invoice
            $('#generate-pdf-invoice-btn').on('click', function() {
                var button = $(this);
                var orderId = button.data('order-id');
                var resultDiv = $('#twintack-control-result');
                
                if (!confirm('Generate PDF invoice for Order #' + orderId + '?')) {
                    return;
                }
                
                button.prop('disabled', true).text('📄 Generating...');
                resultDiv.html('<div style="color: #0073aa; padding: 12px; background: #e3f2fd; border-radius: 5px; border: 1px solid #2196f3;">⏳ Generating invoice... This may take a moment.</div>');
                
                // Create a form to submit for PDF download
                var form = $('<form>', {
                    'method': 'POST',
                    'action': ajaxurl,
                    'target': '_blank'
                }).append(
                    $('<input>', {'type': 'hidden', 'name': 'action', 'value': 'twintack_generate_pdf_invoice'}),
                    $('<input>', {'type': 'hidden', 'name': 'order_id', 'value': orderId}),
                    $('<input>', {'type': 'hidden', 'name': 'nonce', 'value': '<?php echo wp_create_nonce('twintack_pdf_invoice_' . $order_id); ?>'})
                );
                
                $('body').append(form);
                form.submit();
                form.remove();
                
                // Reset button and show success message
                setTimeout(function() {
                    button.prop('disabled', false).text(button.text().replace('Generating...', button.text().includes('PDF') ? 'Generate PDF Invoice' : 'Generate HTML Invoice'));
                    resultDiv.html('<div style="color: green; padding: 12px; background: #d4f6d4; border-radius: 5px; border: 1px solid #28a745;">✅ Invoice generated! Check the new tab that opened.</div>');
                    
                    setTimeout(function() {
                        resultDiv.fadeOut();
                    }, 5000);
                }, 2000);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle AJAX request to save PO number
     */
    public function handle_save_po_number() {
        // Check nonce
        $order_id = intval($_POST['order_id']);
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_po_number_' . $order_id)) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $po_number = sanitize_text_field($_POST['po_number']);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order not found');
        }
        
        try {
            // Update PO number meta field
            $order->update_meta_data('_twintack_po_number', $po_number);
            $order->save();
            
            // Add order note
            if (!empty($po_number)) {
                $note = 'PO Number updated: ' . $po_number;
            } else {
                $note = 'PO Number cleared';
            }
            $order->add_order_note($note);
            
            twintack_manual_payments_log("PO number updated for order {$order_id}: '{$po_number}'");
            
            wp_send_json_success(array(
                'message' => !empty($po_number) ? 'PO Number saved successfully!' : 'PO Number cleared successfully!',
                'po_number' => $po_number
            ));
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Error updating PO number for order {$order_id}: " . $e->getMessage(), 'error');
            wp_send_json_error('Error saving PO number: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle AJAX request to generate PDF invoice
     */
    public function handle_generate_pdf_invoice() {
        // Check nonce
        $order_id = intval($_POST['order_id']);
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_pdf_invoice_' . $order_id)) {
            wp_die('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            wp_die('Insufficient permissions');
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die('Order not found');
        }
        
        try {
            // Load PDF generator
            if (!class_exists('TwinTack_PDF_Invoice_Generator')) {
                require_once TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'includes/class-pdf-invoice-generator.php';
            }
            
            $pdf_generator = TwinTack_PDF_Invoice_Generator::get_instance();
            
            // Add order note
            $current_user = wp_get_current_user();
            $user_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;
            $order->add_order_note('PDF invoice generated by admin user: ' . $user_name);
            
            twintack_manual_payments_log("PDF invoice generated for order {$order_id} by user " . get_current_user_id());
            
            // Generate and download PDF
            $result = $pdf_generator->generate_invoice($order_id, true);
            
            if (is_wp_error($result)) {
                wp_die('Error generating PDF: ' . $result->get_error_message());
            }
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Error generating PDF invoice for order {$order_id}: " . $e->getMessage(), 'error');
            wp_die('Error generating PDF invoice: ' . $e->getMessage());
        }
    }
} 