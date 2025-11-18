<?php
/**
 * The template for displaying the login page.
 *
 * This is used when a page with the slug "login" is created.
 *
 * @package twintack2025
 */

// Get the action from the URL
$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';

// Get all parameters to check for multiple actions
$all_params = $_GET;

// Check for setup_password action which is used in new account emails
if (isset($all_params['action']) && ($all_params['action'] === 'setup_password' || 
    (is_array($all_params['action']) && in_array('setup_password', $all_params['action'])))) {
    // This is explicitly a password setup request
    $action = 'resetpass';
}

// Check for newaccount action which is used in registration emails
if (isset($all_params['action']) && ($all_params['action'] === 'newaccount' || 
    (is_array($all_params['action']) && in_array('newaccount', $all_params['action'])))) {
    // If we have a key and login parameter, this is a password setup/reset request
    if (isset($all_params['key']) && isset($all_params['login'])) {
        $action = 'resetpass';
    }
}

// The wp_parse_args function may convert multiple action parameters into an array
if (is_array($all_params) && isset($all_params['action']) && is_array($all_params['action'])) {
    if (in_array('resetpass', $all_params['action']) || in_array('rp', $all_params['action']) || 
        in_array('setup_password', $all_params['action'])) {
        $action = 'resetpass';
    } elseif (in_array('newaccount', $all_params['action'])) {
        // Check if we have key and login - it means this is a password setup for new account
        if (isset($all_params['key']) && isset($all_params['login'])) {
            $action = 'resetpass';
        }
    }
}

// Always check for key and login as a fallback - if they exist, it's a password reset form
if (isset($all_params['key']) && isset($all_params['login']) && !empty($all_params['key']) && !empty($all_params['login'])) {
    $action = 'resetpass';
}

// Debug logging for URL parameters
if (WP_DEBUG === true) {
    error_log('Login page accessed with action: ' . $action);
    error_log('URL parameters: ' . print_r($_GET, true));
}

// Check if we're processing a login, registration, or password reset form
$is_processing_form = isset( $_POST['login'] ) || isset( $_POST['register'] ) || 
                     isset( $_POST['wc_reset_password'] ) || $action === 'rp' || 
                     $action === 'resetpass' || $action === 'lostpassword';

// If user is logged in and not processing a form, redirect to appropriate dashboard
if ( is_user_logged_in() && ! $is_processing_form ) {
    $user = wp_get_current_user();
    $redirect_url = '';
    
    // Redirect based on user role
    if ( in_array( 'wholesale_customer', (array) $user->roles ) ) {
        $redirect_url = apply_filters( 'twintack_wholesale_dashboard_url', site_url( '/wholesale-dashboard/' ) );
    } elseif ( in_array( 'affiliate', (array) $user->roles ) ) {
        $redirect_url = apply_filters( 'twintack_affiliate_dashboard_url', site_url( '/affiliate-dashboard/' ) );
    } else {
        $redirect_url = wc_get_page_permalink( 'myaccount' );
    }
    
    wp_safe_redirect( $redirect_url );
    exit;
}

// Handle password reset processing
if ( isset( $_POST['wc_reset_password'] ) && isset( $_POST['reset_key'] ) && isset( $_POST['reset_login'] ) ) {
    // Process reset password request
    $reset_key = sanitize_text_field( wp_unslash( $_POST['reset_key'] ) );
    $reset_login = sanitize_text_field( wp_unslash( $_POST['reset_login'] ) );
    $password_1 = isset( $_POST['password_1'] ) ? $_POST['password_1'] : '';
    $password_2 = isset( $_POST['password_2'] ) ? $_POST['password_2'] : '';
    
    $user = check_password_reset_key( $reset_key, $reset_login );
    
    if ( ! is_wp_error( $user ) ) {
        if ( empty( $password_1 ) ) {
            wc_add_notice( __( 'Please enter your password.', 'woocommerce' ), 'error' );
        }
        
        if ( $password_1 !== $password_2 ) {
            wc_add_notice( __( 'Passwords do not match.', 'woocommerce' ), 'error' );
        }
        
        if ( 0 === wc_notice_count( 'error' ) ) {
            // Reset the password
            reset_password( $user, $password_1 );
            
            // Log success for debugging
            error_log('Password reset successful for user: ' . $reset_login);
            
            // Let's automatically log the user in after password reset
            $creds = array(
                'user_login'    => $reset_login,
                'user_password' => $password_1,
                'remember'      => false
            );
            
            // Try to login with the credentials
            $user = wp_signon( $creds, is_ssl() );
            
            if ( ! is_wp_error( $user ) ) {
                // Set auth cookie
                wp_set_auth_cookie( $user->ID, false );
                
                // Get the appropriate redirect URL based on user role
                $redirect_url = apply_filters('woocommerce_login_redirect', wc_get_page_permalink('myaccount'), $user);
                
                if (WP_DEBUG === true) {
                    error_log('Auto login after password reset. Redirecting to: ' . $redirect_url);
                }
                
                // Add success message and redirect
                wc_add_notice( __( 'Your password has been reset successfully and you have been logged in.', 'woocommerce' ), 'success' );
                wp_safe_redirect( $redirect_url );
                exit;
            } else {
                // If automatic login fails, just show the success message and let them login manually
                wc_add_notice( __( 'Your password has been reset successfully. Please log in with your new password.', 'woocommerce' ), 'success' );
                wp_safe_redirect( add_query_arg( 'password-reset', 'true', site_url( '/login/' ) ) );
                exit;
            }
        }
    } else {
        wc_add_notice( __( 'This password reset key is invalid or has already been used. Please request a new link.', 'woocommerce' ), 'error' );
    }
}

// Handle lost password request
if ( isset( $_POST['wc_reset_password'] ) && isset( $_POST['user_login'] ) && !isset( $_POST['reset_key'] ) ) {
    // Verify nonce for security
    $nonce_value = wc_get_var( $_REQUEST['woocommerce-lost-password-nonce'], wc_get_var( $_REQUEST['_wpnonce'], '' ) );
    
    if ( ! wp_verify_nonce( $nonce_value, 'lost_password' ) ) {
        wc_add_notice( __( 'Security verification failed. Please try again.', 'woocommerce' ), 'error' );
    } else {
        // Process lost password (reCAPTCHA disabled for lost password forms)
        $user_login = sanitize_text_field( wp_unslash( $_POST['user_login'] ) );
        
        if ( empty( $user_login ) ) {
            wc_add_notice( __( 'Enter a username or email address.', 'woocommerce' ), 'error' );
        } else {
            // Check if it's a username
            $user_data = get_user_by( 'login', $user_login );
            
            // If not a username, try email
            if ( ! $user_data && is_email( $user_login ) ) {
                $user_data = get_user_by( 'email', $user_login );
            }
            
            if ( $user_data ) {
                $user_login = $user_data->user_login;
                // Get new password reset key (a temp key to allow password reset)
                $key = get_password_reset_key( $user_data );
                
                if ( ! is_wp_error( $key ) ) {
                    // Send reset email
                    $reset_url = add_query_arg(
                        array(
                            'action' => 'rp',
                            'key'    => $key,
                            'login'  => rawurlencode( $user_login ),
                        ),
                        site_url( '/login/' )
                    );
                    
                    // Log for debugging
                    error_log('Sending password reset for user: ' . $user_login . ' with key: ' . $key);
                    error_log('Reset URL: ' . $reset_url);
                    
                    // Hooks into the password reset request to send email
                    do_action( 'retrieve_password', $user_login );
                    
                    // Show message
                    wc_add_notice( __( 'Password reset instructions have been sent to your email address.', 'woocommerce' ), 'success' );
                    wp_safe_redirect( add_query_arg( 'checkemail', 'confirm', site_url( '/login/?action=lostpassword' ) ) );
                    exit;
                }
            } else {
                wc_add_notice( __( 'Invalid username or email.', 'woocommerce' ), 'error' );
            }
        }
    }
}

// Handle the standard WordPress login
if ( isset( $_POST['login'] ) && isset( $_POST['username'] ) && isset( $_POST['password'] ) ) {
    // This login will be processed by WordPress's standard login handlers
    // Our redirect filters will handle where to send the user
    if (WP_DEBUG === true) {
        error_log('Login submission detected on login page');
        error_log('Redirect to: ' . (isset($_POST['redirect_to']) ? $_POST['redirect_to'] : 'Not set'));
    }
}

// Include the unified login template
include( get_template_directory() . '/templates/template-login.php' ); 