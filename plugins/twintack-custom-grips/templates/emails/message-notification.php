<?php
/**
 * Email template: New message notification.
 *
 * Available variables: $customer_name, $title, $status_label,
 * $message_content, $review_url, $site_name, $site_url, $current_year
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f4f5f7; font-family: 'Archivo', Arial, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f5f7; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #1a1a2e; padding: 24px 32px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 700;"><?php echo esc_html( $site_name ); ?></h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 32px;">
                            <h2 style="margin: 0 0 8px; font-size: 18px; color: #1a1a2e;">New Message</h2>
                            <p style="margin: 0 0 20px; font-size: 14px; color: #6b7280;">
                                <?php
                                printf(
                                    /* translators: %s: grip design title */
                                    esc_html__( 'A new message has been posted on grip design: %s', 'twintack-custom-grips' ),
                                    '<strong>' . esc_html( $title ) . '</strong>'
                                );
                                ?>
                            </p>

                            <!-- Message Content -->
                            <?php if ( ! empty( $message_content ) ) : ?>
                                <div style="background-color: #f4f5f7; border-left: 4px solid #2563eb; padding: 16px 20px; border-radius: 0 8px 8px 0; margin-bottom: 20px;">
                                    <p style="margin: 0; font-size: 14px; color: #1a1a2e; line-height: 1.6;">
                                        <?php echo wp_kses_post( $message_content ); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <!-- Details -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 6px 0; font-size: 13px; color: #6b7280; width: 120px;">Customer:</td>
                                    <td style="padding: 6px 0; font-size: 13px; color: #1a1a2e; font-weight: 500;"><?php echo esc_html( $customer_name ); ?></td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; font-size: 13px; color: #6b7280;">Status:</td>
                                    <td style="padding: 6px 0; font-size: 13px; color: #1a1a2e; font-weight: 500;"><?php echo esc_html( $status_label ); ?></td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <table role="presentation" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="border-radius: 8px; background-color: #2563eb;">
                                        <a href="<?php echo esc_url( $review_url ); ?>"
                                           style="display: inline-block; padding: 12px 28px; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none;">
                                            View Design &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 32px; background-color: #f9fafb; border-top: 1px solid #e2e4e9; text-align: center;">
                            <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                                &copy; <?php echo esc_html( $current_year ); ?> <?php echo esc_html( $site_name ); ?>. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
