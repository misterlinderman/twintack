<?php
/**
 * Admin Order Enhancements
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
        // Enable payment gateways for admin orders
        add_filter('woocommerce_available_payment_gateways', array($this, 'enable_admin_payment_gateways'), 10, 1);
        
        // Add payment processing section to admin order edit page
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'add_payment_processing_section'), 10, 1);
        
        // Handle manual payment processing
        add_action('wp_ajax_process_manual_payment', array($this, 'handle_manual_payment_processing'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Initialize the class
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Hook into WooCommerce
        if (class_exists('WooCommerce')) {
            // Log that the enhancement is loaded
            twintack_manual_payments_log('Admin Order Enhancements initialized');
        }
    }
    
    /**
     * Enable payment gateways for admin-created orders
     */
    public function enable_admin_payment_gateways($gateways) {
        if (is_admin() && function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && in_array($screen->id, array('shop_order', 'woocommerce_page_wc-orders'))) {
                // Force enable Stripe for admin orders if enabled
                if (twintack_is_stripe_admin_enabled() && class_exists('WC_Gateway_Stripe')) {
                    $stripe_gateway = new WC_Gateway_Stripe();
                    $stripe_gateway->enabled = 'yes';
                    $gateways['stripe'] = $stripe_gateway;
                    
                    twintack_manual_payments_log('Stripe gateway enabled for admin orders');
                }
                
                // Enable other available gateways
                $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
                foreach ($available_gateways as $gateway_id => $gateway) {
                    if ($gateway->enabled === 'yes') {
                        $gateways[$gateway_id] = $gateway;
                    }
                }
            }
        }
        
        return $gateways;
    }
    
    /**
     * Add payment processing section to admin order edit page
     */
    public function add_payment_processing_section($order) {
        if (!$order || $order->get_status() === 'completed') {
            return;
        }
        
        // Only show if manual payments are enabled
        if (!twintack_is_manual_payments_enabled()) {
            return;
        }
        
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        
        echo '<div class="twintack-payment-processing" style="margin-top: 20px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">';
        echo '<h4>' . __('Payment Processing', 'twintack-manual-payments') . '</h4>';
        
        // Payment method selector
        echo '<p><label for="payment_method_select">' . __('Payment Method:', 'twintack-manual-payments') . '</label><br>';
        echo '<select id="payment_method_select" name="payment_method" style="width: 100%; max-width: 300px;">';
        echo '<option value="">' . __('Select Payment Method', 'twintack-manual-payments') . '</option>';
        
        foreach ($available_gateways as $gateway_id => $gateway) {
            $selected = ($order->get_payment_method() === $gateway_id) ? 'selected' : '';
            echo '<option value="' . esc_attr($gateway_id) . '" ' . $selected . '>' . esc_html($gateway->get_title()) . '</option>';
        }
        
        echo '</select></p>';
        
        // Payment action buttons
        if ($order->get_status() !== 'processing' && $order->get_status() !== 'completed') {
            echo '<p>';
            echo '<button type="button" class="button button-primary" id="mark-order-paid" data-order-id="' . $order->get_id() . '">' . __('Mark as Paid', 'twintack-manual-payments') . '</button> ';
            echo '<button type="button" class="button" id="process-payment" data-order-id="' . $order->get_id() . '">' . __('Process Payment via Gateway', 'twintack-manual-payments') . '</button>';
            echo '</p>';
        }
        
        // Payment status display
        $payment_method = $order->get_payment_method();
        $payment_method_title = $order->get_payment_method_title();
        
        if ($payment_method) {
            echo '<p><strong>' . __('Current Payment Method:', 'twintack-manual-payments') . '</strong> ' . esc_html($payment_method_title) . ' (' . esc_html($payment_method) . ')</p>';
        }
        
        echo '<div id="payment-processing-messages"></div>';
        echo '</div>';
    }
    
    /**
     * Handle manual payment processing via AJAX
     */
    public function handle_manual_payment_processing() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_payment_processing')) {
            twintack_manual_payments_log('Nonce verification failed', 'error');
            wp_die(__('Security check failed', 'twintack-manual-payments'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            twintack_manual_payments_log('Insufficient permissions for user: ' . get_current_user_id(), 'error');
            wp_die(__('Insufficient permissions', 'twintack-manual-payments'));
        }
        
        $order_id = intval($_POST['order_id']);
        $action_type = sanitize_text_field($_POST['action_type']);
        $payment_method = sanitize_text_field($_POST['payment_method']);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            twintack_manual_payments_log('Order not found: ' . $order_id, 'error');
            wp_die(__('Order not found', 'twintack-manual-payments'));
        }
        
        twintack_manual_payments_log("Processing payment for order {$order_id}, action: {$action_type}, method: {$payment_method}");
        
        switch ($action_type) {
            case 'mark_paid':
                $this->mark_order_as_paid($order, $payment_method);
                break;
                
            case 'process_payment':
                $this->process_payment_via_gateway($order, $payment_method);
                break;
        }
        
        wp_die();
    }
    
    /**
     * Mark order as paid manually
     */
    private function mark_order_as_paid($order, $payment_method = '') {
        try {
            // Set payment method if provided
            if (!empty($payment_method)) {
                $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
                if (isset($available_gateways[$payment_method])) {
                    $order->set_payment_method($payment_method);
                    $order->set_payment_method_title($available_gateways[$payment_method]->get_title());
                }
            }
            
            // Mark as paid
            $order->payment_complete();
            $order->add_order_note(__('Payment marked as received manually by admin.', 'twintack-manual-payments'));
            
            // Trigger grip creation if applicable and auto-creation is enabled
            if (TwinTack_Manual_Order_Payments::get_option('auto_create_grip_posts', 'yes') === 'yes') {
                if (function_exists('twintack_trigger_grip_creation_from_order')) {
                    twintack_trigger_grip_creation_from_order($order->get_id());
                    twintack_manual_payments_log("Triggered grip creation for order {$order->get_id()}");
                }
            }
            
            twintack_manual_payments_log("Order {$order->get_id()} marked as paid successfully");
            
            wp_send_json_success(array(
                'message' => __('Order marked as paid successfully.', 'twintack-manual-payments'),
                'order_status' => $order->get_status()
            ));
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Error marking order {$order->get_id()} as paid: " . $e->getMessage(), 'error');
            wp_send_json_error(array(
                'message' => __('Error marking order as paid: ', 'twintack-manual-payments') . $e->getMessage()
            ));
        }
    }
    
    /**
     * Process payment via selected gateway
     */
    private function process_payment_via_gateway($order, $payment_method) {
        try {
            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            
            if (!isset($available_gateways[$payment_method])) {
                throw new Exception(__('Selected payment method is not available.', 'twintack-manual-payments'));
            }
            
            $gateway = $available_gateways[$payment_method];
            
            // Set payment method
            $order->set_payment_method($payment_method);
            $order->set_payment_method_title($gateway->get_title());
            
            // For Stripe and similar gateways, we'll mark as pending payment
            // and provide instructions for manual processing
            $order->update_status('pending', __('Payment gateway set for manual processing.', 'twintack-manual-payments'));
            
            twintack_manual_payments_log("Payment gateway set for order {$order->get_id()}: {$payment_method}");
            
            wp_send_json_success(array(
                'message' => sprintf(__('Payment method set to %s. Process payment manually through the gateway.', 'twintack-manual-payments'), $gateway->get_title()),
                'order_status' => $order->get_status()
            ));
            
        } catch (Exception $e) {
            twintack_manual_payments_log("Error processing payment for order {$order->get_id()}: " . $e->getMessage(), 'error');
            wp_send_json_error(array(
                'message' => __('Error processing payment: ', 'twintack-manual-payments') . $e->getMessage()
            ));
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (in_array($hook, array('post.php', 'post-new.php', 'woocommerce_page_wc-orders'))) {
            global $post_type;
            if ($post_type === 'shop_order' || strpos($hook, 'wc-orders') !== false) {
                wp_enqueue_script(
                    'twintack-admin-order-payments',
                    TWINTACK_MANUAL_PAYMENTS_PLUGIN_URL . 'assets/js/admin-order-payments.js',
                    array('jquery'),
                    TWINTACK_MANUAL_PAYMENTS_VERSION,
                    true
                );
                
                wp_localize_script('twintack-admin-order-payments', 'twintackAdminPayments', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('twintack_payment_processing'),
                    'messages' => array(
                        'processing' => __('Processing...', 'twintack-manual-payments'),
                        'success' => __('Success!', 'twintack-manual-payments'),
                        'error' => __('Error:', 'twintack-manual-payments'),
                        'confirm_mark_paid' => __('Are you sure you want to mark this order as paid?', 'twintack-manual-payments'),
                        'select_payment_method' => __('Please select a payment method first.', 'twintack-manual-payments')
                    )
                ));
            }
        }
    }
}

// Initialize the class - this will be called by the main plugin file 