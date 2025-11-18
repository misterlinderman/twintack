<?php
/**
 * Template part for displaying the reset password form
 *
 * @package twintack2025
 */

// Don't allow direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Debug the URL parameters
if (WP_DEBUG === true) {
    error_log('Reset password form loaded');
    error_log('GET parameters: ' . print_r($_GET, true));
}

// Get key and login from the URL
$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
$login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';

// Debug the key and login
if (WP_DEBUG === true) {
    error_log('Reset key: ' . $key);
    error_log('Login: ' . $login);
    
    // Also dump all GET parameters to help debug
    error_log('GET parameters: ' . json_encode($_GET));
}

// Check for special setup_password action to display appropriate message
$is_new_account = (isset($_GET['action']) && $_GET['action'] === 'setup_password');
$info_message = $is_new_account ? 
    __('Welcome! Please set a password for your new account below.', 'twintack2025') : 
    __('Enter a new password for your account below.', 'twintack2025');

// Check if key and login are provided
if ( empty( $key ) || empty( $login ) ) {
    if (WP_DEBUG === true) {
        error_log('Missing key or login parameters for password reset');
    }
    wc_add_notice( __( 'Invalid password reset link. Please request a new link.', 'woocommerce' ), 'error' );
    ?>
    <p class="return-to-login">
        <a href="<?php echo esc_url( add_query_arg( 'action', 'lostpassword', site_url( '/login/' ) ) ); ?>"><?php esc_html_e( 'Request a new password reset link', 'twintack2025' ); ?></a>
    </p>
    <?php
    return;
}

// Verify key
$user = check_password_reset_key( $key, $login );

if ( is_wp_error( $user ) ) {
    if (WP_DEBUG === true) {
        error_log('Invalid reset key: ' . $user->get_error_message());
    }
    wc_add_notice( __( 'This password reset link has expired or is invalid. Please request a new link.', 'woocommerce' ), 'error' );
    ?>
    <p class="return-to-login">
        <a href="<?php echo esc_url( add_query_arg( 'action', 'lostpassword', site_url( '/login/' ) ) ); ?>"><?php esc_html_e( 'Request a new password reset link', 'twintack2025' ); ?></a>
    </p>
    <?php
    return;
}

// Valid key and user found
if (WP_DEBUG === true) {
    error_log('Valid reset key for user: ' . $user->user_login);
}

// Check for success message
$password_reset = isset( $_GET['password-reset'] ) && $_GET['password-reset'] === 'true';

if ( $password_reset ) {
    wc_add_notice( __( 'Your password has been reset successfully. You can now log in with your new password.', 'woocommerce' ), 'success' );
    ?>
    <p class="return-to-login">
        <a href="<?php echo esc_url( site_url( '/login/' ) ); ?>"><?php esc_html_e( 'Return to login', 'twintack2025' ); ?></a>
    </p>
    <?php
    return;
}
?>

<div class="twintack-reset-password-form">
    <?php wc_print_notices(); ?>
    
    <form method="post" class="woocommerce-ResetPassword reset-password">
        <p><?php echo $info_message; ?></p>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="password_1"><?php esc_html_e( 'New password', 'woocommerce' ); ?> <span class="required">*</span></label>
            <input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password_1" id="password_1" autocomplete="new-password" />
        </p>
        
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="password_2"><?php esc_html_e( 'Re-enter new password', 'woocommerce' ); ?> <span class="required">*</span></label>
            <input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password_2" id="password_2" autocomplete="new-password" />
        </p>

        <input type="hidden" name="reset_key" value="<?php echo esc_attr( $key ); ?>" />
        <input type="hidden" name="reset_login" value="<?php echo esc_attr( $login ); ?>" />

        <div class="clear"></div>

        <?php do_action( 'woocommerce_resetpassword_form' ); ?>

        <p class="woocommerce-form-row form-row">
            <input type="hidden" name="wc_reset_password" value="true" />
            <button type="submit" class="woocommerce-Button button" value="<?php esc_attr_e( 'Save', 'woocommerce' ); ?>"><?php esc_html_e( 'Save', 'woocommerce' ); ?></button>
        </p>

        <?php wp_nonce_field( 'reset_password', 'woocommerce-reset-password-nonce' ); ?>
    </form>
</div> 