<?php
/**
 * Template part for displaying the registration form
 *
 * @package twintack2025
 */

// Don't allow direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add success message logic at the top
$registration_complete = isset( $_GET['registered'] ) && $_GET['registered'] === 'success';
?>

<?php if ( $registration_complete ) : ?>
    <div class="woocommerce-message" role="alert">
        <p><?php esc_html_e( 'Registration complete! Please check your email for login instructions.', 'twintack2025' ); ?></p>
        <p><?php esc_html_e( 'You\'ll receive an email with a link to set your password. If you don\'t see it within a few minutes, please check your spam folder.', 'twintack2025' ); ?></p>
    </div>
<?php else : ?>
    <div class="twintack-register-form">
        <?php wc_print_notices(); ?>
        
        <form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?>>
            <?php do_action( 'woocommerce_register_form_start' ); ?>

            <?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="reg_username"><?php esc_html_e( 'Username', 'woocommerce' ); ?> <span class="required">*</span></label>
                    <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" />
                </p>
            <?php endif; ?>

            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="reg_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?> <span class="required">*</span></label>
                <input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" />
            </p>

            <?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                    <label for="reg_password"><?php esc_html_e( 'Password', 'woocommerce' ); ?> <span class="required">*</span></label>
                    <input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" />
                </p>
            <?php else : ?>
                <div class="password-info-message">
                    <p><?php esc_html_e( 'After registration, you\'ll receive an email with instructions to set your password.', 'woocommerce' ); ?></p>
                </div>
            <?php endif; ?>

            <!-- User Type Selection -->
            <div class="twintack-user-type-selection">
                <p><?php esc_html_e( 'Account type:', 'twintack2025' ); ?></p>
                <div class="user-type-options">
                    <label>
                        <input type="radio" name="account_type" value="customer" checked />
                        <span><?php esc_html_e( 'Customer', 'twintack2025' ); ?></span>
                    </label>
                    <label>
                        <input type="radio" name="account_type" value="wholesale" />
                        <span><?php esc_html_e( 'Wholesale Buyer', 'twintack2025' ); ?></span>
                    </label>
                    <label>
                        <input type="radio" name="account_type" value="affiliate" />
                        <span><?php esc_html_e( 'Affiliate Partner', 'twintack2025' ); ?></span>
                    </label>
                </div>
            </div>

            <?php do_action( 'woocommerce_register_form' ); ?>

            <p class="woocommerce-form-row form-row">
                <?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
                <button type="submit" class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>"><?php esc_html_e( 'Register', 'woocommerce' ); ?></button>
            </p>

            <?php do_action( 'woocommerce_register_form_end' ); ?>
        </form>
    </div>
<?php endif; ?> 