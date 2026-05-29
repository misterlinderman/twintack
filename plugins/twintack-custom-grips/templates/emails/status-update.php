<?php
/**
 * Email template: Status update notification.
 *
 * Available variables: $customer_name, $title, $old_status, $new_status,
 * $new_status_slug, $review_url, $site_name, $site_url, $current_year
 *
 * @package TwinTack_Custom_Grips
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Choose a contextual message based on the new status
$contextual_message = '';
switch ( $new_status_slug ?? '' ) {
    case 'pending_review':
        $contextual_message = __( 'Your grip mockup is ready for your review! Please take a look and let us know if it looks great or if you\'d like any changes.', 'twintack-custom-grips' );
        break;
    case 'customer_approved':
        $contextual_message = __( 'Thank you for approving your grip design! You can purchase your custom grips whenever you\'re ready.', 'twintack-custom-grips' );
        break;
    case 'in_production':
        $contextual_message = __( 'Your custom grips are now in production! We\'ll let you know once they ship.', 'twintack-custom-grips' );
        break;
    case 'approved_for_production':
        $contextual_message = __( 'Your custom grips are now in production! We\'ll let you know once they ship.', 'twintack-custom-grips' );
        break;
    case 'shipped':
        $contextual_message = __( 'Your custom grips have shipped! You should receive them soon.', 'twintack-custom-grips' );
        break;
    default:
        $contextual_message = __( 'The status of your grip design has been updated.', 'twintack-custom-grips' );
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
                            <h2 style="margin: 0 0 8px; font-size: 18px; color: #1a1a2e;">Status Update</h2>
                            <p style="margin: 0 0 20px; font-size: 14px; color: #6b7280; line-height: 1.6;">
                                <?php echo esc_html( $contextual_message ); ?>
                            </p>

                            <!-- Status Change -->
                            <div style="background-color: #f4f5f7; border-radius: 8px; padding: 16px 20px; margin-bottom: 20px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td style="padding: 6px 0; font-size: 13px; color: #6b7280; width: 120px;">Design:</td>
                                        <td style="padding: 6px 0; font-size: 14px; color: #1a1a2e; font-weight: 600;"><?php echo esc_html( $title ); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 6px 0; font-size: 13px; color: #6b7280;">Customer:</td>
                                        <td style="padding: 6px 0; font-size: 13px; color: #1a1a2e;"><?php echo esc_html( $customer_name ); ?></td>
                                    </tr>
                                    <?php if ( ! empty( $old_status ) ) : ?>
                                    <tr>
                                        <td style="padding: 6px 0; font-size: 13px; color: #6b7280;">Previous Status:</td>
                                        <td style="padding: 6px 0; font-size: 13px; color: #9ca3af;"><?php echo esc_html( $old_status ); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td style="padding: 6px 0; font-size: 13px; color: #6b7280;">New Status:</td>
                                        <td style="padding: 6px 0;">
                                            <span style="display: inline-block; padding: 3px 12px; border-radius: 100px; font-size: 12px; font-weight: 600; color: #ffffff; background-color: #2563eb;">
                                                <?php echo esc_html( $new_status ); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

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
