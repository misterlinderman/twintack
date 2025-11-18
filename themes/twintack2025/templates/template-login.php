<?php
/**
 * Template Name: Unified Login Page
 *
 * A custom template for the unified login experience.
 *
 * @package twintack2025
 */

get_header();

// Redirect logic has been moved to page-login.php
// This prevents duplicate redirects that could cause loops

// Check if we're handling a specific account action
$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';

// Special handling for user registration/password reset
$has_key_login = isset($_GET['key']) && isset($_GET['login']);
$is_password_setup = (
    // Check if this is a new account setup or password reset
    ($action === 'newaccount' || $action === 'resetpass' || $action === 'rp' || 
     $action === 'setup_password') && 
    $has_key_login
);

// Force the correct action for password setup
if ($is_password_setup) {
    $action = 'resetpass';
    if (WP_DEBUG === true) {
        error_log('Setting action to resetpass for password setup: ' . $action);
        error_log('Key: ' . $_GET['key'] . ' - Login: ' . $_GET['login']);
    }
}

// Fallback detection - if key and login are present, this must be a password reset
if ($has_key_login && !empty($_GET['key']) && !empty($_GET['login'])) {
    $action = 'resetpass';
    if (WP_DEBUG === true) {
        error_log('Setting action to resetpass based on key/login presence');
    }
}
?>

<div class="twintack-login-page">
    <div class="container">
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <div class="twintack-login-container">
                    <?php if ( $action === 'rp' || $action === 'resetpass' ) : ?>
                        <!-- Password Reset Form -->
                        <div class="twintack-reset-password-form">
                            <h2><?php esc_html_e( 'Reset Password', 'twintack2025' ); ?></h2>
                            <?php 
                            // Debug output for resetpass action
                            if (WP_DEBUG === true) {
                                error_log('Attempting to display reset password form with action: ' . $action);
                                error_log('Key and login present: ' . (isset($_GET['key']) && isset($_GET['login']) ? 'Yes' : 'No'));
                            }
                            
                            // Include the custom password reset form
                            get_template_part( 'template-parts/account/reset-password-form' ); 
                            ?>
                        </div>
                    <?php elseif ( $action === 'lostpassword' ) : ?>
                        <!-- Lost Password Form -->
                        <div class="twintack-lost-password-form">
                            <h2><?php esc_html_e( 'Lost Password', 'twintack2025' ); ?></h2>
                            <?php 
                            // Include the custom lost password form
                            get_template_part( 'template-parts/account/lost-password-form' ); 
                            ?>
                        </div>
                    <?php else : ?>
                        <!-- Regular Login Form -->
                        <?php get_template_part( 'template-parts/account/login-form' ); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
get_footer(); 