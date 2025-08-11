<?php
/**
 * TwinTack Grip Email Notifications
 * 
 * Handles email notifications for the grip design workflow
 */

class TwinTack_Grip_Email_Notifications {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook into grip design status changes
        add_action('grip_design_artwork_status_changed', array($this, 'handle_artwork_status_change'), 10, 3);
        add_action('grip_production_approval', array($this, 'handle_production_approval'), 10, 3);
        
        // Add email template filters
        add_filter('twintack_grip_email_template', array($this, 'get_email_template'), 10, 1);
    }
    
    /**
     * Handle artwork status changes and send appropriate emails
     */
    public function handle_artwork_status_change($grip_id, $old_status, $new_status) {
        if (WP_DEBUG) {
            error_log("TwinTack Email: Artwork status changed for grip {$grip_id}: {$old_status} -> {$new_status}");
        }
        
        // Send email when artwork is ready for customer review
        if ($new_status === 'pending_review') {
            $this->send_artwork_ready_email($grip_id);
        }
        
        // Send email when production starts (Monday.com moves to production status)
        if ($new_status === 'production_started' || $new_status === 'in_production') {
            $this->send_production_started_email($grip_id);
        }
    }
    
    /**
     * Handle production approval and send notification
     */
    public function handle_production_approval($grip_id, $order_id, $webhook_data) {
        if (WP_DEBUG) {
            error_log("TwinTack Email: Production approved for grip {$grip_id}, order {$order_id}");
        }
        
        $this->send_production_started_email($grip_id, $order_id);
    }
    
    /**
     * Send email when artwork is ready for customer review
     */
    public function send_artwork_ready_email($grip_id) {
        $post = get_post($grip_id);
        if (!$post) return false;
        
        // Get customer details
        $customer_email = get_post_meta($grip_id, '_grip_customer_email', true);
        $customer_name = get_post_meta($grip_id, '_grip_customer_name', true);
        $team_name = get_post_meta($grip_id, '_grip_team_name', true);
        
        if (empty($customer_email)) {
            error_log("TwinTack Email: No customer email found for grip {$grip_id}");
            return false;
        }
        
        // Get mockup image URL if available
        $mockup_url = get_post_meta($grip_id, '_grip_mockup_asset_url', true);
        $review_url = $this->get_grip_review_url($grip_id);
        
        // Email subject
        $subject = sprintf('Your %s Grip Design is Ready for Review!', $team_name ?: 'Custom');
        
        // Email content
        $message = $this->get_email_template('artwork_ready');
        $message = str_replace('[CUSTOMER_NAME]', $customer_name, $message);
        $message = str_replace('[TEAM_NAME]', $team_name ?: 'your team', $message);
        $message = str_replace('[GRIP_TITLE]', $post->post_title, $message);
        $message = str_replace('[REVIEW_URL]', $review_url, $message);
        $message = str_replace('[MOCKUP_URL]', $mockup_url, $message);
        $message = str_replace('[SITE_URL]', get_site_url(), $message);
        $message = str_replace('[SITE_NAME]', get_bloginfo('name'), $message);
        
        // Send email
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <noreply@' . $this->get_domain() . '>'
        );
        
        $sent = wp_mail($customer_email, $subject, $message, $headers);
        
        if (WP_DEBUG) {
            error_log("TwinTack Email: Artwork ready email " . ($sent ? 'sent' : 'failed') . " to {$customer_email}");
        }
        
        // Log the email attempt
        $this->log_email_attempt($grip_id, 'artwork_ready', $customer_email, $sent);
        
        return $sent;
    }
    
    /**
     * Send email when production starts
     */
    public function send_production_started_email($grip_id, $order_id = null) {
        $post = get_post($grip_id);
        if (!$post) return false;
        
        // Get customer details
        $customer_email = get_post_meta($grip_id, '_grip_customer_email', true);
        $customer_name = get_post_meta($grip_id, '_grip_customer_name', true);
        $team_name = get_post_meta($grip_id, '_grip_team_name', true);
        $quantity = get_post_meta($grip_id, '_grip_quantity', true);
        
        if (empty($customer_email)) {
            error_log("TwinTack Email: No customer email found for grip {$grip_id}");
            return false;
        }
        
        // Get order details if available (for final purchase scenario)
        $order = null;
        $final_order_id = get_post_meta($grip_id, '_grip_final_order_id', true);
        if ($order_id) {
            $order = wc_get_order($order_id);
        } elseif ($final_order_id) {
            $order = wc_get_order($final_order_id);
        }
        
        // Email subject
        $subject = sprintf('Your %s Grips are Now in Production!', $team_name ?: 'Custom');
        
        // Email content
        $message = $this->get_email_template('production_started');
        $message = str_replace('[CUSTOMER_NAME]', $customer_name, $message);
        $message = str_replace('[TEAM_NAME]', $team_name ?: 'your team', $message);
        $message = str_replace('[GRIP_TITLE]', $post->post_title, $message);
        $message = str_replace('[QUANTITY]', $quantity ?: 'your', $message);
        $message = str_replace('[ORDER_NUMBER]', $order ? $order->get_order_number() : ($final_order_id ?: $grip_id), $message);
        $message = str_replace('[SITE_URL]', get_site_url(), $message);
        $message = str_replace('[SITE_NAME]', get_bloginfo('name'), $message);
        
        // Send email
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <noreply@' . $this->get_domain() . '>'
        );
        
        $sent = wp_mail($customer_email, $subject, $message, $headers);
        
        if (WP_DEBUG) {
            error_log("TwinTack Email: Production started email " . ($sent ? 'sent' : 'failed') . " to {$customer_email}");
        }
        
        // Log the email attempt
        $this->log_email_attempt($grip_id, 'production_started', $customer_email, $sent);
        
        return $sent;
    }
    
    /**
     * Get email template content
     */
    public function get_email_template($template_type) {
        $templates = array(
            'artwork_ready' => '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div style="background: #0073aa; color: white; padding: 20px; text-align: center;">
                        <h1 style="margin: 0;">[SITE_NAME]</h1>
                        <p style="margin: 10px 0 0 0;">Your Custom Grip Design is Ready!</p>
                    </div>
                    
                    <div style="background: #f9f9f9; padding: 30px; border: 1px solid #ddd;">
                        <h2 style="color: #333; margin-top: 0;">Hi [CUSTOMER_NAME],</h2>
                        
                        <p>Great news! Your custom grip design for <strong>[TEAM_NAME]</strong> is ready for your review.</p>
                        
                        <p><strong>Design:</strong> [GRIP_TITLE]</p>
                        
                        <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 5px; text-align: center;">
                            <h3 style="margin-top: 0;">Review Your Design</h3>
                            <p>Click the button below to view your design and provide feedback:</p>
                            <a href="[REVIEW_URL]" style="display: inline-block; background: #0073aa; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">Review Design</a>
                        </div>
                        
                        <p>You can:</p>
                        <ul>
                            <li><strong>Approve</strong> the design to move forward with production</li>
                            <li><strong>Request changes</strong> with specific feedback for our design team</li>
                        </ul>
                        
                        <p>If you have any questions, please don\'t hesitate to contact us.</p>
                        
                        <p>Best regards,<br>The TwinTack Team</p>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; color: #666; font-size: 12px;">
                        <p>© [SITE_NAME] | <a href="[SITE_URL]">Visit Our Website</a></p>
                    </div>
                </div>
            ',
            
            'production_started' => '
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div style="background: #28a745; color: white; padding: 20px; text-align: center;">
                        <h1 style="margin: 0;">[SITE_NAME]</h1>
                        <p style="margin: 10px 0 0 0;">Your Grips are in Production!</p>
                    </div>
                    
                    <div style="background: #f9f9f9; padding: 30px; border: 1px solid #ddd;">
                        <h2 style="color: #333; margin-top: 0;">Hi [CUSTOMER_NAME],</h2>
                        
                        <p>Excellent! Your custom grips for <strong>[TEAM_NAME]</strong> have been approved and are now in production.</p>
                        
                        <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 5px;">
                            <h3 style="margin-top: 0; color: #28a745;">Production Details</h3>
                            <p><strong>Design:</strong> [GRIP_TITLE]</p>
                            <p><strong>Quantity:</strong> [QUANTITY] grips</p>
                            <p><strong>Order Number:</strong> #[ORDER_NUMBER]</p>
                        </div>
                        
                        <p>Our production team is now creating your custom grips. You\'ll receive a shipping notification with tracking information once your order ships.</p>
                        
                        <p><strong>What happens next?</strong></p>
                        <ul>
                            <li>Your grips will be carefully crafted by our production team</li>
                            <li>Quality control inspection to ensure perfect results</li>
                            <li>Secure packaging and shipping</li>
                            <li>Tracking information sent to your email</li>
                        </ul>
                        
                        <p>Thank you for choosing TwinTack for your custom grip needs!</p>
                        
                        <p>Best regards,<br>The TwinTack Team</p>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; color: #666; font-size: 12px;">
                        <p>© [SITE_NAME] | <a href="[SITE_URL]">Visit Our Website</a></p>
                    </div>
                </div>
            '
        );
        
        return apply_filters('twintack_grip_email_template_' . $template_type, $templates[$template_type] ?? '');
    }
    
    /**
     * Get the URL for reviewing a grip design
     */
    private function get_grip_review_url($grip_id) {
        $my_account_url = wc_get_page_permalink('myaccount');
        return add_query_arg(array(
            'grip-designs' => '',
            'grip_id' => $grip_id
        ), $my_account_url);
    }
    
    /**
     * Get domain name for email from address
     */
    private function get_domain() {
        $url = get_site_url();
        $parsed = parse_url($url);
        return $parsed['host'] ?? 'twintack.com';
    }
    
    /**
     * Log email attempts for tracking
     */
    private function log_email_attempt($grip_id, $email_type, $recipient, $success) {
        $log_entry = array(
            'timestamp' => current_time('c'),
            'email_type' => $email_type,
            'recipient' => $recipient,
            'success' => $success
        );
        
        // Get existing email log
        $email_log = get_post_meta($grip_id, '_grip_email_log', true);
        if (!is_array($email_log)) {
            $email_log = array();
        }
        
        $email_log[] = $log_entry;
        update_post_meta($grip_id, '_grip_email_log', $email_log);
        
        if (WP_DEBUG) {
            error_log("TwinTack Email: Logged {$email_type} email attempt for grip {$grip_id}");
        }
    }
    
    /**
     * Send test email (for admin testing)
     */
    public function send_test_email($email_type, $test_email, $grip_id = null) {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        // Use test data if no grip ID provided
        if (!$grip_id) {
            $grip_id = 'TEST';
            $customer_name = 'Test Customer';
            $team_name = 'Test Team';
            $grip_title = 'Test Grip Design';
        } else {
            $post = get_post($grip_id);
            $customer_name = get_post_meta($grip_id, '_grip_customer_name', true);
            $team_name = get_post_meta($grip_id, '_grip_team_name', true);
            $grip_title = $post ? $post->post_title : 'Test Grip Design';
        }
        
        $subject = "[TEST] TwinTack Email Notification";
        $message = $this->get_email_template($email_type);
        
        // Replace placeholders with test data
        $message = str_replace('[CUSTOMER_NAME]', $customer_name, $message);
        $message = str_replace('[TEAM_NAME]', $team_name, $message);
        $message = str_replace('[GRIP_TITLE]', $grip_title, $message);
        $message = str_replace('[REVIEW_URL]', '#test-review-url', $message);
        $message = str_replace('[MOCKUP_URL]', '#test-mockup-url', $message);
        $message = str_replace('[QUANTITY]', '25', $message);
        $message = str_replace('[ORDER_NUMBER]', 'TEST-123', $message);
        $message = str_replace('[SITE_URL]', get_site_url(), $message);
        $message = str_replace('[SITE_NAME]', get_bloginfo('name'), $message);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <noreply@' . $this->get_domain() . '>'
        );
        
        return wp_mail($test_email, $subject, $message, $headers);
    }
}
