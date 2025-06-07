<?php
/**
 * The template for displaying the login page.
 *
 * This is used when a page with the slug "login" is created.
 *
 * @package twintack2025
 */

// Check if we're processing a login or registration form
$is_processing_form = isset( $_POST['login'] ) || isset( $_POST['register'] );

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

// Include the unified login template
include( get_template_directory() . '/templates/template-login.php' ); 