<?php
/**
 * Login Form
 *
 * This template overrides /woocommerce/templates/myaccount/form-login.php
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Only redirect if we're not already on the login page and not processing a form submission
if ( ! is_page( 'login' ) && ! isset( $_POST['login'] ) && ! isset( $_POST['register'] ) ) {
    wp_safe_redirect( site_url( '/login/' ) );
    exit;
}

// Otherwise, include our custom login template
include( get_template_directory() . '/templates/template-login.php' ); 