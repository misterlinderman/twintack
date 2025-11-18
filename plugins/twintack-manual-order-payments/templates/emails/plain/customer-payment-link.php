<?php
/**
 * Customer Payment Link Email (Plain Text)
 * 
 * Plain text email template for payment links
 * 
 * @package TwinTack_Manual_Order_Payments
 */

defined('ABSPATH') || exit;

echo "= " . wp_strip_all_tags($email_heading) . " =\n\n";

printf(
    esc_html__('Hi %s,', 'twintack-manual-payments') . "\n\n",
    esc_html($order->get_billing_first_name())
);

printf(
    esc_html__('Your order #%s has been created and is ready for payment. Please complete your payment using the secure payment link below:', 'twintack-manual-payments') . "\n\n",
    esc_html($order->get_order_number())
);

echo "COMPLETE PAYMENT NOW\n";
echo esc_url($payment_url) . "\n\n";

echo "ORDER SUMMARY\n";
echo "================\n\n";

printf(
    esc_html__('Order Number: #%s', 'twintack-manual-payments') . "\n",
    esc_html($order->get_order_number())
);

printf(
    esc_html__('Order Date: %s', 'twintack-manual-payments') . "\n",
    esc_html(wc_format_datetime($order->get_date_created()))
);

printf(
    esc_html__('Total Amount: %s', 'twintack-manual-payments') . "\n\n",
    wp_strip_all_tags($order->get_formatted_order_total())
);

// Order Items
if ($order->get_items()) {
    echo "ORDER ITEMS\n";
    echo "===========\n\n";
    
    foreach ($order->get_items() as $item_id => $item) {
        printf(
            "%s x %s - %s\n",
            esc_html($item->get_name()),
            esc_html($item->get_quantity()),
            wp_strip_all_tags($order->get_formatted_line_subtotal($item))
        );
    }
    echo "\n";
}

echo "🔒 SECURE PAYMENT\n";
echo "This payment link is secure and encrypted. Your payment information is processed safely through Stripe.\n\n";

echo "IMPORTANT: This payment link will expire in 24 hours for your security. If you have any questions, please contact us.\n\n";

echo apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')); 