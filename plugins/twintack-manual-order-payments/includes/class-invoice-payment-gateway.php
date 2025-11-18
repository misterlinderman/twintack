<?php
/**
 * TwinTack Invoice Payment Gateway
 * 
 * Custom payment gateway for invoice/pay-later functionality in manual orders
 * 
 * @package TwinTack_Manual_Order_Payments
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Invoice_Payment_Gateway extends WC_Payment_Gateway {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'twintack_invoice';
        $this->icon               = '';
        $this->has_fields         = false;
        $this->method_title       = __('TwinTack Invoice Payment', 'twintack-manual-payments');
        $this->method_description = __('Allow customers to pay later via invoice. Admin can set orders to invoice status and send payment links to customers.', 'twintack-manual-payments');
        
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Get settings
        $this->title              = $this->get_option('title');
        $this->description        = $this->get_option('description');
        $this->instructions       = $this->get_option('instructions');
        $this->enabled            = $this->get_option('enabled');
        
        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        
        // Make available for admin manual orders
        add_filter('woocommerce_available_payment_gateways', array($this, 'add_to_admin_gateways'));
    }
    
    /**
     * Initialize gateway settings form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'twintack-manual-payments'),
                'type'    => 'checkbox',
                'label'   => __('Enable TwinTack Invoice Payment', 'twintack-manual-payments'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('Title', 'twintack-manual-payments'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'twintack-manual-payments'),
                'default'     => __('Invoice Payment (Pay Later)', 'twintack-manual-payments'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'twintack-manual-payments'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'twintack-manual-payments'),
                'default'     => __('You will receive an invoice and payment link via email. Payment can be completed at your convenience.', 'twintack-manual-payments'),
                'desc_tip'    => true,
            ),
            'instructions' => array(
                'title'       => __('Instructions', 'twintack-manual-payments'),
                'type'        => 'textarea',
                'description' => __('Instructions that will be added to the thank you page and emails.', 'twintack-manual-payments'),
                'default'     => __('You will receive an invoice and payment link via email shortly. Please complete payment when ready.', 'twintack-manual-payments'),
                'desc_tip'    => true,
            ),
        );
    }
    
    /**
     * Check if this gateway is available for use
     */
    public function is_available() {
        // Only available for admin manual orders or when specifically enabled
        if (is_admin() && current_user_can('edit_shop_orders')) {
            return true;
        }
        
        // Don't show to regular customers during checkout
        return false;
    }
    
    /**
     * Add to available gateways for admin use
     */
    public function add_to_admin_gateways($gateways) {
        if (is_admin() && current_user_can('edit_shop_orders')) {
            if (!isset($gateways[$this->id]) && $this->is_available()) {
                $gateways[$this->id] = $this;
            }
        }
        return $gateways;
    }
    
    /**
     * Process the payment and return the result
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return array(
                'result'   => 'failure',
                'messages' => 'Order not found'
            );
        }
        
        // Set order to "invoiced" status (we'll create this custom status)
        $order->update_status('wc-invoiced', __('Order set to invoice payment. Awaiting customer payment.', 'twintack-manual-payments'));
        
        // Add order note
        $order->add_order_note(__('Invoice payment method selected. Customer will receive payment link.', 'twintack-manual-payments'));
        
        // Mark as needing payment
        $order->update_meta_data('_twintack_needs_payment_link', 'yes');
        $order->save();
        
        if (function_exists('twintack_manual_payments_log')) {
            twintack_manual_payments_log("Order {$order_id} set to invoice payment method");
        }
        
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }
    
    /**
     * Output for the order received page
     */
    public function thankyou_page($order_id) {
        if ($this->instructions) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)));
        }
    }
    
    /**
     * Add content to the WC emails
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false) {
        if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
        }
    }
    
    /**
     * Check if order needs payment
     */
    public function needs_payment($order, $valid_statuses) {
        // Invoice orders need payment until they're completed
        if ($order->get_payment_method() === $this->id && $order->get_status() === 'invoiced') {
            return true;
        }
        return false;
    }
} 