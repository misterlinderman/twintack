<?php
/**
 * Template part for displaying the login form
 *
 * @package twintack2025
 */

// Don't allow direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check if we have a password reset success message
$password_reset = isset( $_GET['password-reset'] ) && $_GET['password-reset'] === 'true';
?>

<div class="twintack-login-form">
    <?php if ( $password_reset ) : ?>
        <div class="woocommerce-message" role="alert">
            <?php esc_html_e( 'Your password has been reset successfully. You can now log in with your new password.', 'twintack2025' ); ?>
        </div>
    <?php endif; ?>
    
    <?php wc_print_notices(); ?>

    <h2><?php esc_html_e( 'Login', 'twintack2025' ); ?></h2>
    
    <form class="woocommerce-form woocommerce-form-login login" method="post">
        <?php do_action( 'woocommerce_login_form_start' ); ?>

        <?php
        // Add redirect_to field if it exists in the URL
        if ( isset( $_GET['redirect_to'] ) ) {
            echo '<input type="hidden" name="redirect_to" value="' . esc_attr( $_GET['redirect_to'] ) . '">';
        } else {
            // Add default redirect to my account page if no redirect is specified
            echo '<input type="hidden" name="redirect_to" value="' . esc_attr( wc_get_page_permalink( 'myaccount' ) ) . '">';
        }
        ?>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="username"><?php esc_html_e( 'Username or email address', 'woocommerce' ); ?> <span class="required">*</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" />
        </p>
        
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="password"><?php esc_html_e( 'Password', 'woocommerce' ); ?> <span class="required">*</span></label>
            <input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" />
        </p>

        <!-- User Type Selection -->
        <div class="twintack-user-type-selection">
            <p><?php esc_html_e( 'I am a:', 'twintack2025' ); ?></p>
            <div class="user-type-options">
                <label>
                    <input type="radio" name="user_type" value="customer" checked />
                    <span><?php esc_html_e( 'Customer', 'twintack2025' ); ?></span>
                </label>
                <label>
                    <input type="radio" name="user_type" value="wholesale" />
                    <span><?php esc_html_e( 'Wholesale Buyer', 'twintack2025' ); ?></span>
                </label>
                <label>
                    <input type="radio" name="user_type" value="affiliate" />
                    <span><?php esc_html_e( 'Affiliate Partner', 'twintack2025' ); ?></span>
                </label>
            </div>
        </div>

        <?php do_action( 'woocommerce_login_form' ); ?>

        <p class="form-row">
            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
                <input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> 
                <span><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
            </label>
            <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
            <button type="submit" class="woocommerce-button button woocommerce-form-login__submit" name="login" value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>"><?php esc_html_e( 'Log in', 'woocommerce' ); ?></button>
        </p>
        
        <p class="woocommerce-LostPassword lost_password">
            <a href="<?php echo esc_url( add_query_arg( 'action', 'lostpassword', site_url( '/login/' ) ) ); ?>"><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></a>
        </p>

        <?php do_action( 'woocommerce_login_form_end' ); ?>
    </form>
</div> 