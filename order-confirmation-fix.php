<?php
/**
 * Order Confirmation Email Fix for Manual Orders
 * 
 * Upload this script to your WordPress root and run it to check
 * and fix order confirmation email issues for specific orders.
 */

// Load WordPress
require_once 'wp-config.php';
require_once 'wp-load.php';

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    die('WooCommerce is not active!');
}

echo "<h2>TwinTack Order Confirmation Email Diagnostic</h2>\n";
echo "<pre>\n";

// Function to send order confirmation email
function send_order_confirmation_email($order_id) {
    $order = wc_get_order($order_id);
    
    if (!$order) {
        echo "❌ Order #{$order_id} not found!\n";
        return false;
    }
    
    echo "📧 SENDING ORDER CONFIRMATION for Order #{$order_id}\n";
    echo str_repeat("-", 40) . "\n";
    
    $customer_email = $order->get_billing_email();
    echo "Customer Email: {$customer_email}\n";
    echo "Order Status: " . $order->get_status() . "\n";
    echo "Order Total: $" . $order->get_total() . "\n";
    
    // Check if email is valid
    if (!is_email($customer_email)) {
        echo "❌ Invalid email address: {$customer_email}\n";
        return false;
    }
    
    try {
        // Get WooCommerce mailer
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();
        
        // Send appropriate email based on order status
        $order_status = $order->get_status();
        $email_sent = false;
        
        if ($order_status === 'completed' && isset($emails['WC_Email_Customer_Completed_Order'])) {
            $emails['WC_Email_Customer_Completed_Order']->trigger($order_id, $order);
            echo "✅ Completed order email sent\n";
            $email_sent = true;
            
        } elseif ($order_status === 'processing' && isset($emails['WC_Email_Customer_Processing_Order'])) {
            $emails['WC_Email_Customer_Processing_Order']->trigger($order_id, $order);
            echo "✅ Processing order email sent\n";
            $email_sent = true;
            
        } elseif ($order_status === 'invoiced') {
            // For invoiced orders, send a custom payment request email
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
            
            if ($email_sent) {
                echo "✅ Invoice/payment request email sent\n";
                $order->add_order_note("Invoice email sent to customer: {$customer_email}");
            } else {
                echo "❌ Failed to send invoice email\n";
            }
            
        } else {
            // Send basic order confirmation
            if (isset($emails['WC_Email_New_Order'])) {
                // Send customer order confirmation
                if (isset($emails['WC_Email_Customer_New_Account'])) {
                    $emails['WC_Email_Customer_New_Account']->trigger($order->get_customer_id(), '', $order);
                }
                
                // Send processing email as fallback
                if (isset($emails['WC_Email_Customer_Processing_Order'])) {
                    $emails['WC_Email_Customer_Processing_Order']->trigger($order_id, $order);
                    echo "✅ Fallback processing email sent\n";
                    $email_sent = true;
                }
            }
        }
        
        if (!$email_sent) {
            echo "⚠️  No suitable email template found, sending basic confirmation...\n";
            
            // Send basic order confirmation
            $subject = sprintf('Order Confirmation #%s - %s', $order->get_order_number(), get_bloginfo('name'));
            $message = sprintf("
Thank you for your order!

Order Number: #%s
Order Date: %s
Order Total: %s
Order Status: %s

We'll send you updates as your order progresses.

Thank you for choosing %s!
            ",
                $order->get_order_number(),
                $order->get_date_created()->format('F j, Y'),
                wc_price($order->get_total()),
                wc_get_order_status_name($order->get_status()),
                get_bloginfo('name')
            );
            
            $headers = array(
                'Content-Type: text/plain; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
            );
            
            $email_sent = wp_mail($customer_email, $subject, $message, $headers);
            
            if ($email_sent) {
                echo "✅ Basic order confirmation sent\n";
            } else {
                echo "❌ Failed to send basic confirmation\n";
            }
        }
        
        return $email_sent;
        
    } catch (Exception $e) {
        echo "❌ Email error: " . $e->getMessage() . "\n";
        return false;
    }
}

// You can specify order IDs here or let the user input them
echo "MANUAL ORDER EMAIL CONFIRMATION SYSTEM\n";
echo str_repeat("=", 50) . "\n";

// Check recent manual orders that might need confirmation emails
$recent_orders = wc_get_orders(array(
    'limit' => 10,
    'status' => array('processing', 'invoiced', 'pending', 'on-hold'),
    'meta_key' => '_twintack_manual_order',
    'meta_value' => 'yes'
));

if (empty($recent_orders)) {
    echo "No recent manual orders found.\n";
    echo "You can manually send confirmation for any order by calling:\n";
    echo "send_order_confirmation_email(ORDER_ID);\n\n";
} else {
    echo "Recent manual orders found:\n";
    foreach ($recent_orders as $order) {
        echo "- Order #{$order->get_id()} ({$order->get_status()}) - {$order->get_billing_email()}\n";
    }
    echo "\n";
}

// Uncomment and modify these lines to send confirmation emails for specific orders:
// send_order_confirmation_email(1665);
// send_order_confirmation_email(1662);

echo "\n" . str_repeat("=", 50) . "\n";
echo "📧 EMAIL SYSTEM DIAGNOSTIC\n";
echo str_repeat("=", 50) . "\n";

// Test WordPress email functionality
echo "WordPress Email Settings:\n";
echo "- Admin Email: " . get_option('admin_email') . "\n";
echo "- Site Name: " . get_bloginfo('name') . "\n";
echo "- Site URL: " . get_site_url() . "\n";

// Check if wp_mail is working
echo "\nTesting wp_mail functionality...\n";
$test_email = get_option('admin_email');
$test_result = wp_mail($test_email, 'Test Email from TwinTack', 'This is a test email to verify wp_mail is working.');

if ($test_result) {
    echo "✅ wp_mail is working - test email sent to {$test_email}\n";
} else {
    echo "❌ wp_mail is not working - check your email configuration\n";
    
    // Get the last PHP error
    $last_error = error_get_last();
    if ($last_error && strpos($last_error['message'], 'mail') !== false) {
        echo "PHP Error: " . $last_error['message'] . "\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🚀 NEXT STEPS\n";
echo str_repeat("=", 50) . "\n";
echo "1. If wp_mail is working, uncomment the specific order email lines above\n";
echo "2. Refresh this page to send confirmation emails\n";
echo "3. Check with customer that they received the email\n";
echo "4. Update the manual order creation process to auto-send emails\n";

echo "\n</pre>";
?>
