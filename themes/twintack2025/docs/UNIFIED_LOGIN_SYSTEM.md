# Unified Login System

## Overview

The TwinTack theme implements a unified login system that provides a centralized authentication experience for all user types: regular customers, wholesale buyers, and affiliate partners. This system replaces the default WordPress and WooCommerce login screens with a custom, branded login page that handles user type selection, role-based authentication, and appropriate redirects.

## Key Features

- **Centralized Login Page**: A single login page for all user types
- **User Type Selection**: Users can specify their account type during login and registration
- **Role-Based Authentication**: Validates that users have appropriate roles for their selected user type
- **Custom Redirects**: Redirects users to appropriate dashboards based on their roles
- **Seamless Integration**: Works with WordPress, WooCommerce, and third-party plugins

## Implementation Details

### File Structure

- **`page-login.php`**: Template for the login page URL (/login/)
- **`templates/template-login.php`**: Main login page template with tabbed interface
- **`template-parts/account/login-form.php`**: Login form component
- **`template-parts/account/register-form.php`**: Registration form component
- **`template-parts/account/user-type-selector.php`**: Reusable user type selector component
- **`woocommerce/myaccount/form-login.php`**: WooCommerce login form override

### Functions

The following functions in `functions.php` handle the unified login system:

- **`twintack_login_url_filter()`**: Redirects all login URLs to the unified login page
- **`twintack_custom_login()`**: Customizes the WordPress login page and handles redirects
- **`twintack_login_logo_url()`**: Changes the login logo URL
- **`twintack_login_redirect()`**: Handles role-based redirects after login
- **`twintack_process_registration()`**: Processes user type selection during registration
- **`twintack_login_styles()`**: Adds custom styles for the login page
- **`twintack_authenticate_user_type()`**: Validates user roles during login

## Usage Guide

### Setting Up the Login Page

1. Create a new WordPress page with the title "Login" and slug "login"
2. The page will automatically use the unified login template
3. No additional content is needed on the page

### Customizing Redirects

To customize where users are redirected after login, modify the `twintack_login_redirect()` function in `functions.php`:

```php
function twintack_login_redirect( $redirect, $user ) {
    // Get the user's role
    $user_roles = $user->roles;
    
    // Redirect based on user role
    if ( in_array( 'wholesale_customer', $user_roles ) ) {
        return apply_filters( 'twintack_wholesale_dashboard_url', site_url( '/wholesale-dashboard/' ) );
    } elseif ( in_array( 'affiliate', $user_roles ) ) {
        return apply_filters( 'twintack_affiliate_dashboard_url', site_url( '/affiliate-dashboard/' ) );
    } elseif ( in_array( 'administrator', $user_roles ) ) {
        return admin_url();
    } else {
        // Regular customers go to the WooCommerce my account page
        return wc_get_page_permalink( 'myaccount' );
    }
}
```

### Customizing User Types

To modify the available user types, edit the user type selector component in `template-parts/account/user-type-selector.php`.

### Styling the Login Page

Custom styles for the login page are defined in the `twintack_login_styles()` function in `functions.php`. Modify these styles to match your brand's design.

## Plugin Integration

### Wholesale Plugin Integration

The unified login system is designed to work with wholesale plugins. To integrate with a specific wholesale plugin:

1. Identify the login URL filter used by your wholesale plugin
2. Add the filter to `functions.php`:
   ```php
   add_filter( 'your_wholesale_plugin_login_url_filter', 'twintack_login_url_filter', 10, 2 );
   ```
3. Ensure the wholesale role name matches in the authentication and redirect functions

### Affiliate Plugin Integration

Similarly, for affiliate plugins:

1. Identify the login URL filter used by your affiliate plugin
2. Add the filter to `functions.php`:
   ```php
   add_filter( 'your_affiliate_plugin_login_url_filter', 'twintack_login_url_filter', 10, 2 );
   ```
3. Ensure the affiliate role name matches in the authentication and redirect functions

## Troubleshooting

### Redirect Loops

If you encounter redirect loops:

1. Check that the conditional checks in `twintack_custom_login()` are working correctly
2. Ensure that `is_page('login')` checks are present to prevent redirecting when already on the login page
3. Verify that form submission handling doesn't trigger additional redirects

### Authentication Issues

If users can't log in with their selected user type:

1. Check that the role names in `twintack_authenticate_user_type()` match your actual user roles
2. Verify that the user type radio buttons have the correct values
3. Check for any plugin conflicts that might be affecting authentication

## Security Considerations

The unified login system implements several security measures:

- Uses WordPress nonces for form submissions
- Sanitizes all user inputs
- Uses `wp_safe_redirect()` to prevent open redirect vulnerabilities
- Validates redirect URLs to ensure they're on the same domain

---

*This document provides detailed information about the unified login system implemented in the TwinTack theme. For general theme features, refer to the [Features](FEATURES.md) documentation.* 