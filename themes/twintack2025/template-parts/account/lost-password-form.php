<?php
/**
 * Template part for displaying the lost password form
 *
 * @package twintack2025
 */

// Don't allow direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check for email sent confirmation
$check_email = isset( $_GET['checkemail'] ) ? sanitize_text_field( $_GET['checkemail'] ) : '';
?>

<div class="twintack-lost-password-form">
    <?php wc_print_notices(); ?>
    
    <?php if ( $check_email === 'confirm' ) : ?>
        <p><?php echo esc_html__( 'A password reset email has been sent to the email address on file for your account, but may take several minutes to show up in your inbox. Please wait at least 10 minutes before attempting another reset.', 'woocommerce' ); ?></p>
        
        <p class="return-to-login">
            <a href="<?php echo esc_url( site_url( '/login/' ) ); ?>"><?php esc_html_e( 'Return to login', 'twintack2025' ); ?></a>
        </p>
    <?php else : ?>
        <form method="post" class="woocommerce-ResetPassword lost_reset_password">
            <p><?php echo apply_filters( 'woocommerce_lost_password_message', esc_html__( 'Lost your password? Please enter your username or email address. You will receive a link to create a new password via email.', 'woocommerce' ) ); ?></p>

            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="user_login"><?php esc_html_e( 'Username or email', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
                <input class="woocommerce-Input woocommerce-Input--text input-text" type="text" name="user_login" id="user_login" autocomplete="username" required aria-required="true" />
            </p>

            <div class="clear"></div>

            <?php do_action( 'woocommerce_lostpassword_form' ); ?>

            <p class="woocommerce-form-row form-row">
                <input type="hidden" name="wc_reset_password" value="true" />
                <button type="submit" class="woocommerce-Button button" value="<?php esc_attr_e( 'Reset password', 'woocommerce' ); ?>"><?php esc_html_e( 'Reset password', 'woocommerce' ); ?></button>
            </p>

            <?php wp_nonce_field( 'lost_password', 'woocommerce-lost-password-nonce' ); ?>
        </form>
    <?php endif; ?>
    
    <p class="return-to-login">
        <a href="<?php echo esc_url( site_url( '/login/' ) ); ?>"><?php esc_html_e( 'Return to login', 'twintack2025' ); ?></a>
    </p>
</div> 