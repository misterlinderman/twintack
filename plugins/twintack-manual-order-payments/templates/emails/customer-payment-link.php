<?php
/**
 * Customer Payment Link Email (HTML)
 * 
 * Professional email template for payment links using WooCommerce styling
 * 
 * @package TwinTack_Manual_Order_Payments
 */

defined('ABSPATH') || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action('woocommerce_email_header', $email_heading, $email); ?>

<div style="margin-bottom: 40px;">
    <h2 style="color: #557da1; display: block; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 18px; font-weight: bold; line-height: 130%; margin: 0 0 18px; text-align: left;">
        <?php esc_html_e('Payment Required', 'twintack-manual-payments'); ?>
    </h2>
    
    <p style="margin: 0 0 16px;">
        <?php printf(
            esc_html__('Hi %s,', 'twintack-manual-payments'),
            esc_html($order->get_billing_first_name())
        ); ?>
    </p>
    
    <p style="margin: 0 0 16px;">
        <?php printf(
            esc_html__('Your order #%s has been created and is ready for payment. Please complete your payment using the secure payment link below:', 'twintack-manual-payments'),
            esc_html($order->get_order_number())
        ); ?>
    </p>
    
    <!-- Payment Button -->
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 30px 0;">
        <tr>
            <td align="center">
                <table border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 15px 30px; background-color: #557da1; border-radius: 5px;">
                            <a href="<?php echo esc_url($payment_url); ?>" 
                               style="color: #ffffff; text-decoration: none; font-weight: bold; font-size: 16px; display: block;">
                                <?php esc_html_e('Complete Payment Now', 'twintack-manual-payments'); ?>
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    <p style="margin: 0 0 16px; font-size: 12px; color: #777;">
        <?php esc_html_e('Or copy and paste this link into your browser:', 'twintack-manual-payments'); ?><br>
        <a href="<?php echo esc_url($payment_url); ?>" style="color: #557da1; word-break: break-all;">
            <?php echo esc_url($payment_url); ?>
        </a>
    </p>
    
    <!-- Order Summary -->
    <h3 style="color: #557da1; display: block; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 16px; font-weight: bold; line-height: 130%; margin: 30px 0 18px; text-align: left;">
        <?php esc_html_e('Order Summary', 'twintack-manual-payments'); ?>
    </h3>
    
    <table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #eee; margin-bottom: 20px;" border="1" bordercolor="#eee">
        <tbody>
            <tr>
                <th scope="row" style="text-align: left; border: 1px solid #eee; padding: 12px; color: #557da1;">
                    <?php esc_html_e('Order Number:', 'twintack-manual-payments'); ?>
                </th>
                <td style="text-align: left; border: 1px solid #eee; padding: 12px;">
                    #<?php echo esc_html($order->get_order_number()); ?>
                </td>
            </tr>
            <tr>
                <th scope="row" style="text-align: left; border: 1px solid #eee; padding: 12px; color: #557da1;">
                    <?php esc_html_e('Order Date:', 'twintack-manual-payments'); ?>
                </th>
                <td style="text-align: left; border: 1px solid #eee; padding: 12px;">
                    <?php echo esc_html(wc_format_datetime($order->get_date_created())); ?>
                </td>
            </tr>
            <tr>
                <th scope="row" style="text-align: left; border: 1px solid #eee; padding: 12px; color: #557da1;">
                    <?php esc_html_e('Total Amount:', 'twintack-manual-payments'); ?>
                </th>
                <td style="text-align: left; border: 1px solid #eee; padding: 12px; font-weight: bold; font-size: 16px;">
                    <?php echo wp_kses_post($order->get_formatted_order_total()); ?>
                </td>
            </tr>
        </tbody>
    </table>
    
    <!-- Order Items -->
    <?php if ($order->get_items()) : ?>
        <h3 style="color: #557da1; display: block; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; font-size: 16px; font-weight: bold; line-height: 130%; margin: 30px 0 18px; text-align: left;">
            <?php esc_html_e('Order Items', 'twintack-manual-payments'); ?>
        </h3>
        
        <table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #eee; margin-bottom: 20px;" border="1" bordercolor="#eee">
            <thead>
                <tr>
                    <th scope="col" style="text-align: left; border: 1px solid #eee; padding: 12px; color: #557da1;">
                        <?php esc_html_e('Product', 'twintack-manual-payments'); ?>
                    </th>
                    <th scope="col" style="text-align: left; border: 1px solid #eee; padding: 12px; color: #557da1;">
                        <?php esc_html_e('Quantity', 'twintack-manual-payments'); ?>
                    </th>
                    <th scope="col" style="text-align: left; border: 1px solid #eee; padding: 12px; color: #557da1;">
                        <?php esc_html_e('Price', 'twintack-manual-payments'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order->get_items() as $item_id => $item) : ?>
                    <tr>
                        <td style="text-align: left; border: 1px solid #eee; padding: 12px;">
                            <?php echo esc_html($item->get_name()); ?>
                        </td>
                        <td style="text-align: left; border: 1px solid #eee; padding: 12px;">
                            <?php echo esc_html($item->get_quantity()); ?>
                        </td>
                        <td style="text-align: left; border: 1px solid #eee; padding: 12px;">
                            <?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <!-- Security Notice -->
    <div style="background-color: #f8f9fa; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0;">
        <p style="margin: 0; font-size: 14px;">
            <strong><?php esc_html_e('🔒 Secure Payment', 'twintack-manual-payments'); ?></strong><br>
            <?php esc_html_e('This payment link is secure and encrypted. Your payment information is processed safely through Stripe.', 'twintack-manual-payments'); ?>
        </p>
    </div>
    
    <!-- Expiration Notice -->
    <p style="margin: 20px 0 0; font-size: 12px; color: #999; font-style: italic;">
        <?php esc_html_e('This payment link will expire in 24 hours for your security. If you have any questions, please contact us.', 'twintack-manual-payments'); ?>
    </p>
</div>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action('woocommerce_email_footer', $email); 