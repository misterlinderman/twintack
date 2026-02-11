<?php
/**
 * Email template: Mockup ready for review.
 *
 * Available variables: $customer_name, $title, $status_label, $mockup_url,
 * $review_url, $site_name, $site_url, $current_year, $team_name, $quantity
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
                            <h2 style="margin: 0 0 8px; font-size: 18px; color: #1a1a2e;">Your Mockup Is Ready!</h2>
                            <p style="margin: 0 0 20px; font-size: 14px; color: #6b7280; line-height: 1.6;">
                                <?php
                                printf(
                                    /* translators: %s: customer name */
                                    esc_html__( 'Hey %s! We\'ve finished the mockup for your custom grip design. Take a look and let us know what you think!', 'twintack-custom-grips' ),
                                    esc_html( $customer_name )
                                );
                                ?>
                            </p>

                            <!-- Mockup Preview -->
                            <?php if ( ! empty( $mockup_url ) ) : ?>
                                <div style="text-align: center; margin-bottom: 24px;">
                                    <img src="<?php echo esc_url( $mockup_url ); ?>"
                                         alt="<?php echo esc_attr( $title ); ?> mockup"
                                         style="max-width: 100%; height: auto; border-radius: 8px; border: 1px solid #e2e4e9;">
                                </div>
                            <?php endif; ?>

                            <!-- Design Details -->
                            <div style="background-color: #f4f5f7; border-radius: 8px; padding: 16px 20px; margin-bottom: 24px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td style="padding: 6px 0; font-size: 13px; color: #6b7280; width: 120px;">Design:</td>
                                        <td style="padding: 6px 0; font-size: 14px; color: #1a1a2e; font-weight: 600;"><?php echo esc_html( $title ); ?></td>
                                    </tr>
                                    <?php if ( ! empty( $team_name ) ) : ?>
                                    <tr>
                                        <td style="padding: 6px 0; font-size: 13px; color: #6b7280;">Team:</td>
                                        <td style="padding: 6px 0; font-size: 13px; color: #1a1a2e;"><?php echo esc_html( $team_name ); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $quantity ) ) : ?>
                                    <tr>
                                        <td style="padding: 6px 0; font-size: 13px; color: #6b7280;">Quantity:</td>
                                        <td style="padding: 6px 0; font-size: 13px; color: #1a1a2e;"><?php echo esc_html( $quantity ); ?> grips</td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>

                            <p style="margin: 0 0 20px; font-size: 14px; color: #6b7280; line-height: 1.6;">
                                <?php esc_html_e( 'Please review the mockup and either approve it or let us know what changes you\'d like. You can respond directly through your account page.', 'twintack-custom-grips' ); ?>
                            </p>

                            <!-- CTA Button -->
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin: 0 auto;">
                                <tr>
                                    <td style="border-radius: 8px; background-color: #16a34a;">
                                        <a href="<?php echo esc_url( $review_url ); ?>"
                                           style="display: inline-block; padding: 14px 32px; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none;">
                                            Review Your Mockup &rarr;
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
