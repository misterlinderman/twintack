<?php
/**
 * TwinTack Payment Link Email
 * 
 * Professional email template for sending Stripe payment links to customers
 * 
 * @package TwinTack_Manual_Order_Payments
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Link Email Class
 * 
 * Extends WooCommerce's email system to send professional payment link emails
 */
class TwinTack_Payment_Link_Email extends WC_Email {
    
    /**
     * Payment URL for the email
     */
    public $payment_url;
    
    /**
     * Constructor
     */
    public function __construct() {
        
        $this->id             = 'twintack_payment_link';
        $this->customer_email = true;
        $this->title          = 'Payment Link';
        $this->description    = 'Payment link emails are sent to customers when a payment link is generated for their order.';
        $this->template_html  = 'emails/customer-payment-link.php';
        $this->template_plain = 'emails/plain/customer-payment-link.php';
        $this->placeholders   = array(
            '{order_date}'   => '',
            '{order_number}' => '',
            '{site_title}'   => '',
        );
        
        // Call parent constructor
        parent::__construct();
        
        // Set email defaults
        $this->subject = 'Payment Required for Order #{order_number}';
        $this->heading = 'Complete Your Payment';
        
        // Template path should point to our plugin
        $this->template_base = TWINTACK_MANUAL_PAYMENTS_PLUGIN_DIR . 'templates/';
    }
    
    /**
     * Get email subject
     */
    public function get_default_subject() {
        return 'Payment Required for Order #{order_number} - {site_title}';
    }
    
    /**
     * Get email heading
     */
    public function get_default_heading() {
        return 'Complete Your Payment';
    }
    
    /**
     * Trigger the sending of this email
     */
    public function trigger($order_id, $payment_url = '', $order = false) {
        $this->setup_locale();
        
        if ($order_id && !is_a($order, 'WC_Order')) {
            $order = wc_get_order($order_id);
        }
        
        if (is_a($order, 'WC_Order')) {
            $this->object                         = $order;
            $this->recipient                      = $this->object->get_billing_email();
            $this->payment_url                    = $payment_url;
            $this->placeholders['{order_date}']   = wc_format_datetime($this->object->get_date_created());
            $this->placeholders['{order_number}'] = $this->object->get_order_number();
            $this->placeholders['{site_title}']   = $this->get_blogname();
        }
        
        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
        
        $this->restore_locale();
    }
    
    /**
     * Get content html
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            array(
                'order'              => $this->object,
                'email_heading'      => $this->get_heading(),
                'payment_url'        => $this->payment_url,
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => false,
                'email'              => $this,
            ),
            '',
            $this->template_base
        );
    }
    
    /**
     * Get content plain
     */
    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            array(
                'order'              => $this->object,
                'email_heading'      => $this->get_heading(),
                'payment_url'        => $this->payment_url,
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => true,
                'email'              => $this,
            ),
            '',
            $this->template_base
        );
    }
    
    /**
     * Return content from the additional_content field.
     * 
     * Displayed above the footer.
     */
    public function get_additional_content() {
        if (method_exists($this, 'get_option')) {
            return apply_filters('woocommerce_email_additional_content_' . $this->id, $this->get_option('additional_content'), $this->object, $this);
        }
        return '';
    }
} 