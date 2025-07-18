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
        
        // Log AJAX registration
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log('Admin Order Enhancements: AJAX handlers registered');
        }
    }
    
    /**
     * Initialize admin-only hooks
     */
    private function init_admin_hooks() {
        // Add payment processing section to order edit pages
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'add_payment_section'), 10, 1);
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Log hook registration
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log('Admin Order Enhancements: Admin hooks registered');
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
        echo '<h5 style="margin: 0 0 10px 0;">' . esc_html__('Payment Actions', 'twintack-manual-payments') . '</h5>';
        
        // Payment buttons row
        echo '<div style="margin-bottom: 15px;">';
        
        // Mark as Paid button
        echo '<button type="button" class="button button-primary" id="twintack-mark-paid" data-order-id="' . esc_attr($order->get_id()) . '" style="margin-right: 8px; margin-bottom: 5px;">';
        echo '✓ ' . esc_html__('Mark as Paid', 'twintack-manual-payments');
        echo '</button>';
        
        // Process Payment button
        echo '<button type="button" class="button button-secondary" id="twintack-process-payment" data-order-id="' . esc_attr($order->get_id()) . '" style="margin-right: 8px; margin-bottom: 5px;">';
        echo '💳 ' . esc_html__('Process Payment via Gateway', 'twintack-manual-payments');
        echo '</button>';
        
        // Send Payment Link button
        echo '<button type="button" class="button button-secondary" id="twintack-send-payment-link" data-order-id="' . esc_attr($order->get_id()) . '" style="margin-bottom: 5px; background: #6c5ce7; border-color: #6c5ce7; color: white;">';
        echo '📧 ' . esc_html__('Send Payment Link to Customer', 'twintack-manual-payments');
        echo '</button>';
        
        echo '</div>';
        
        // Detailed instructions
        echo '<div style="font-size: 12px; color: #666; line-height: 1.4; background: #f8f9fa; padding: 10px; border-radius: 3px;">';
        echo '<div style="margin-bottom: 8px;"><strong>✓ Mark as Paid:</strong> Use for phone orders, cash payments, or when payment was received externally.</div>';
        echo '<div style="margin-bottom: 8px;"><strong>💳 Process Payment:</strong> Set payment method and update order status for manual gateway processing.</div>';
        echo '<div><strong>📧 Send Payment Link:</strong> Generate secure Stripe payment link and email it to customer for self-service payment.</div>';
        echo '</div>';
        
        // Customer information for payment link
        $customer_email = $order->get_billing_email();
        if ($customer_email) {
            echo '<div style="margin-top: 10px; padding: 8px; background: #e3f2fd; border-left: 3px solid #2196f3; font-size: 12px;">';
            echo '<strong>📧 Payment Link will be sent to:</strong> ' . esc_html($customer_email);
            echo '</div>';
        } else {
            echo '<div style="margin-top: 10px; padding: 8px; background: #fff3e0; border-left: 3px solid #ff9800; font-size: 12px;">';
            echo '<strong>⚠️ Warning:</strong> No customer email address found. Please add billing email before sending payment link.';
            echo '</div>';
        }
        
        // Messages area
        echo '<div id="twintack-payment-messages" style="margin-top: 15px;"></div>';
        echo '</div>';
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
            
            // Update order status
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
            
            // Send email to customer
            $email_sent = $this->send_payment_link_email($order, $checkout_session->url);
            
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
        
        // Create success and cancel URLs
        $return_url = add_query_arg(array(
            'twintack_payment' => 'return',
            'order_id' => $order->get_id(),
            'session_id' => '{CHECKOUT_SESSION_ID}',
        ), admin_url('post.php?post=' . $order->get_id() . '&action=edit'));
        
        $cancel_url = add_query_arg(array(
            'twintack_payment' => 'cancelled',
            'order_id' => $order->get_id(),
        ), admin_url('post.php?post=' . $order->get_id() . '&action=edit'));
        
        // Prepare checkout session parameters
        $session_params = array(
            'mode' => 'payment',
            'line_items' => $line_items,
            'customer_email' => $order->get_billing_email(),
            'success_url' => $return_url,
            'cancel_url' => $cancel_url,
            'client_reference_id' => $order->get_id(),
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
        return WC_Stripe_API::request($session_params, 'checkout/sessions');
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
     * Send payment link email to customer
     */
    private function send_payment_link_email($order, $payment_url) {
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
} 