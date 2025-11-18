<?php
/**
 * Password Reset Helper Functions
 * 
 * Helps ensure the password reset functionality works correctly
 * by providing helper functions and hooks.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handle custom password reset parameters in URLs
 */
function twintack_handle_password_reset_parameters() {
    global $wp;
    
    // Check if we're on the login page and have key/login parameters
    if (is_page('login') && isset($_GET['key']) && isset($_GET['login'])) {
        // Force the action to be 'resetpass' if not already set
        if (!isset($_GET['action']) || 
            (isset($_GET['action']) && $_GET['action'] !== 'resetpass' && 
             $_GET['action'] !== 'rp' && $_GET['action'] !== 'setup_password')) {
            
            $_GET['action'] = 'resetpass';
            $_REQUEST['action'] = 'resetpass';
            $wp->query_vars['action'] = 'resetpass';
            
            if (WP_DEBUG) {
                error_log('Password reset parameters detected - forcing action=resetpass');
            }
        }
    }
}
add_action('template_redirect', 'twintack_handle_password_reset_parameters', 5);

/**
 * Debug the email content when sending password reset emails
 */
function twintack_debug_email_content($wp_mail_content) {
    if (WP_DEBUG) {
        if (strpos($wp_mail_content['subject'], 'Password Reset') !== false || 
            strpos($wp_mail_content['subject'], 'Your new account') !== false) {
            error_log('Password Reset Email Subject: ' . $wp_mail_content['subject']);
            error_log('Password Reset Email Body: ' . substr($wp_mail_content['message'], 0, 500) . '...');
        }
    }
    return $wp_mail_content;
}
add_filter('wp_mail', 'twintack_debug_email_content');

/**
 * Redirect non-admin users to our custom login page with appropriate parameters
 */
function twintack_redirect_to_custom_password_reset($redirect_to, $requested_redirect_to, $user) {
    // If this is a password reset, redirect to our custom page
    if (strpos($redirect_to, 'wp-login.php?action=rp') !== false || 
        strpos($redirect_to, 'action=resetpass') !== false ||
        strpos($redirect_to, 'action=newaccount') !== false) {
        
        // Extract key and login
        $parts = parse_url($redirect_to);
        parse_str($parts['query'], $query);
        
        if (isset($query['key']) && isset($query['login'])) {
            $custom_redirect = add_query_arg(
                array(
                    'action' => 'resetpass',
                    'key'    => $query['key'],
                    'login'  => $query['login'],
                ),
                site_url('/login/')
            );
            
            if (WP_DEBUG) {
                error_log('Redirecting standard password reset to custom page: ' . $custom_redirect);
            }
            
            return $custom_redirect;
        }
    }
    
    return $redirect_to;
}
add_filter('login_redirect', 'twintack_redirect_to_custom_password_reset', 10, 3);

// Include this file in the theme
require_once get_stylesheet_directory() . '/inc/password-reset-helper.php'; 