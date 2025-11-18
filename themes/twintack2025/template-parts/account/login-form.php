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

<div class="tt-login">
    <div class="tt-login-container">
        <?php if ( $password_reset ) : ?>
            <div class="woocommerce-message" role="alert">
                <?php esc_html_e( 'Your password has been reset successfully. You can now log in with your new password.', 'twintack2025' ); ?>
            </div>
        <?php endif; ?>
        
        <?php wc_print_notices(); ?>

        <h2><?php esc_html_e( 'Sign In', 'twintack2025' ); ?></h2>
        
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

            <div class="form-row">
                <label for="username">
                    <span class="screen-reader-text"><?php esc_html_e( 'Username or email address', 'woocommerce' ); ?></span>
                </label>
                <input type="text" class="woocommerce-Input input-text" name="username" id="username" 
                    autocomplete="username" placeholder="<?php esc_attr_e( 'Username or email', 'woocommerce' ); ?>"
                    value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" />
            </div>
            
            <div class="form-row">
                <label for="password">
                    <span class="screen-reader-text"><?php esc_html_e( 'Password', 'woocommerce' ); ?></span>
                </label>
                <input class="woocommerce-Input input-text" type="password" name="password" id="password" 
                    autocomplete="current-password" placeholder="<?php esc_attr_e( 'Password', 'woocommerce' ); ?>" />
            </div>

            <?php do_action( 'woocommerce_login_form' ); ?>

            <div class="form-row">
                <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
                <button type="submit" class="woocommerce-button button woocommerce-form-login__submit" name="login" 
                    value="<?php esc_attr_e( 'Sign In', 'woocommerce' ); ?>">
                    <?php esc_html_e( 'Sign In', 'woocommerce' ); ?>
                </button>
            </div>
            
            <div class="form-footer">
                <label class="woocommerce-form__label-for-checkbox">
                    <input class="woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" />
                    <span><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
                </label>
                <a href="<?php echo esc_url( add_query_arg( 'action', 'lostpassword', site_url( '/login/' ) ) ); ?>" class="lost-password-link">
                    <?php esc_html_e( 'Forgot Password?', 'woocommerce' ); ?>
                </a>
            </div>

            <?php do_action( 'woocommerce_login_form_end' ); ?>
        </form>

        <?php if ( get_option( 'woocommerce_enable_myaccount_registration' ) === 'yes' ) : ?>
            <div class="create-account-link">
                <p>
                    <?php esc_html_e( "Don't have an account?", 'woocommerce' ); ?> 
                    <button type="button" class="toggle-register-form">
                        <?php esc_html_e( 'Create one', 'woocommerce' ); ?>
                    </button>
                </p>
            </div>

            <form method="post" class="woocommerce-form woocommerce-form-register register hidden" <?php do_action( 'woocommerce_register_form_tag' ); ?>>
                <h3><?php esc_html_e( 'Create Account', 'woocommerce' ); ?></h3>

                <?php do_action( 'woocommerce_register_form_start' ); ?>

                <?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
                    <div class="form-row">
                        <label for="reg_username">
                            <span class="screen-reader-text"><?php esc_html_e( 'Username', 'woocommerce' ); ?></span>
                        </label>
                        <input type="text" class="woocommerce-Input input-text" name="username" id="reg_username" 
                            autocomplete="username" placeholder="<?php esc_attr_e( 'Username', 'woocommerce' ); ?>"
                            value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" />
                    </div>
                <?php endif; ?>

                <div class="form-row">
                    <label for="reg_email">
                        <span class="screen-reader-text"><?php esc_html_e( 'Email address', 'woocommerce' ); ?></span>
                    </label>
                    <input type="email" class="woocommerce-Input input-text" name="email" id="reg_email" 
                        autocomplete="email" placeholder="<?php esc_attr_e( 'Email address', 'woocommerce' ); ?>"
                        value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" />
                </div>

                <?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
                    <div class="form-row">
                        <label for="reg_password">
                            <span class="screen-reader-text"><?php esc_html_e( 'Password', 'woocommerce' ); ?></span>
                        </label>
                        <input type="password" class="woocommerce-Input input-text" name="password" id="reg_password" 
                            autocomplete="new-password" placeholder="<?php esc_attr_e( 'Password', 'woocommerce' ); ?>" />
                    </div>
                <?php else : ?>
                    <p class="registration-note"><?php esc_html_e( 'A link to set your password will be sent to your email address.', 'woocommerce' ); ?></p>
                <?php endif; ?>

                <?php do_action( 'woocommerce_register_form' ); ?>

                <div class="form-row">
                    <?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
                    <button type="submit" class="woocommerce-Button button" name="register" 
                        value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>">
                        <?php esc_html_e( 'Register', 'woocommerce' ); ?>
                    </button>
                </div>

                <?php do_action( 'woocommerce_register_form_end' ); ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Initially hide the form
    $('.woocommerce-form-register').hide();
    
    $('.toggle-register-form').on('click', function(e) {
        e.preventDefault();
        var $form = $('.woocommerce-form-register');
        $form.slideToggle(300, function() {
            $form.toggleClass('show');
        });
        $(this).toggleClass('active');
    });
    
    // Ensure reCAPTCHA is properly initialized
    // Wait for reCAPTCHA API to be available
    function initializeRecaptcha() {
        if (typeof grecaptcha !== 'undefined' && typeof wpcaptcha_captcha === 'function') {
            // For reCAPTCHA v3, ensure the token is generated before form submission
            $('form.woocommerce-form-login').on('submit', function(e) {
                var $form = $(this);
                var $response = $form.find('input[name="g-recaptcha-response"]');
                
                // If reCAPTCHA v3 response field exists but is empty, generate token first
                if ($response.length && !$response.val()) {
                    e.preventDefault();
                    
                    // Call the reCAPTCHA function and then submit
                    wpcaptcha_captcha();
                    
                    // Wait a moment for token to be set, then submit
                    setTimeout(function() {
                        if ($response.val()) {
                            $form.off('submit').submit();
                        }
                    }, 500);
                }
            });
        } else {
            // Retry initialization after a short delay
            setTimeout(initializeRecaptcha, 500);
        }
    }
    
    // Start initialization
    initializeRecaptcha();
});
</script> 