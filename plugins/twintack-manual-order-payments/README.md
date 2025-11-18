# TwinTack Manual Order Payments

A WordPress plugin that enables Stripe and other payment gateways for manually created WooCommerce orders, with seamless integration with the TwinTack Grip Manager system.

## Description

This plugin solves the common issue where WooCommerce payment gateways (especially Stripe) are not available when creating orders manually in the WordPress admin. It provides a clean, professional interface for processing payments on admin-created orders while maintaining full integration with existing TwinTack workflows.

## Features

- ✅ **Stripe Gateway Availability**: Forces Stripe to be available for admin-created orders
- ✅ **Universal Payment Processing**: Works with all customers (regular and wholesale)
- ✅ **Two Payment Options**:
  - **Mark as Paid**: For orders paid via phone, cash, or external means
  - **Process Payment via Gateway**: Set payment method and process through gateway
- ✅ **Grip Manager Integration**: Automatically triggers grip design creation when orders are completed
- ✅ **Clean Admin Interface**: Professional payment processing section in order edit pages
- ✅ **Comprehensive Logging**: Debug logging for troubleshooting
- ✅ **Safe & Secure**: Proper nonce verification and permission checks
- ✅ **HPOS Compatible**: Fully supports WooCommerce High-Performance Order Storage

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Stripe for WooCommerce plugin (for Stripe functionality)

## Installation

1. Upload the plugin files to `/wp-content/plugins/twintack-manual-order-payments/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. The plugin will automatically enable when you create or edit orders in WooCommerce admin

## Usage

### Creating Manual Orders with Payment

1. Go to **WooCommerce > Orders > Add Order**
2. Fill in customer information and add products
3. In the order edit screen, you'll see a **Payment Processing** section
4. Select a payment method from the dropdown (Stripe will be available)
5. Choose your action:
   - **Mark as Paid**: Use for phone orders or when payment was received externally
   - **Process Payment via Gateway**: Set payment method for gateway processing

### For Wholesale Customers

- Wholesale customers use the same payment process as regular customers
- Pricing differences are handled automatically by user roles
- No special payment terms - all payments are processed immediately

### Integration with Grip Manager

- When orders containing grip design products are marked as paid, grip design posts are automatically created
- All existing webhook integrations (Make.com, Monday.com) continue to work
- Purchase-based creation system is fully preserved

## Configuration

The plugin works out-of-the-box with sensible defaults. Advanced options are stored in the WordPress options table:

- `enable_stripe_admin`: Enable Stripe for admin orders (default: yes)
- `enable_manual_payment_processing`: Enable payment processing interface (default: yes)
- `auto_create_grip_posts`: Auto-create grip posts on payment completion (default: yes)
- `debug_mode`: Enable debug logging (default: no)

## Logging

When debug mode is enabled, the plugin logs to WooCommerce logs under the source `twintack-manual-payments`. You can view logs at **WooCommerce > Status > Logs**.

## Hooks and Filters

### Actions

- `twintack_manual_payments_loaded`: Fired when the plugin is fully loaded
- `twintack_manual_payments_order_marked_paid`: Fired when an order is manually marked as paid
- `twintack_manual_payments_payment_processed`: Fired when payment is processed via gateway

### Helper Functions

- `twintack_is_manual_payments_enabled()`: Check if manual payments are enabled
- `twintack_is_stripe_admin_enabled()`: Check if Stripe admin is enabled
- `twintack_manual_payments_log($message, $level)`: Log debug messages

## Troubleshooting

### Payment Methods Not Showing

1. Ensure WooCommerce is active and properly configured
2. Check that Stripe keys are properly set in WooCommerce settings
3. Verify user has `edit_shop_orders` capability

### WooCommerce HPOS Compatibility Warning

**Fixed in v1.0.2**: The plugin now properly declares HPOS compatibility and will not show compatibility warnings.

### AJAX Errors

1. Check browser console for JavaScript errors
2. Verify nonce is working (try refreshing the page)
3. Check PHP error logs for server-side issues

### Grip Posts Not Creating

1. Ensure TwinTack Grip Manager plugin is active
2. Verify order contains grip design products
3. Check that `auto_create_grip_posts` option is enabled

## Compatibility

- **Theme Independent**: Works with any WordPress theme
- **WooCommerce Versions**: Tested with WooCommerce 5.0 - 8.0
- **PHP Versions**: Compatible with PHP 7.4 - 8.2
- **WordPress Versions**: Compatible with WordPress 5.8+

## Security

- All AJAX requests use WordPress nonce verification
- Proper capability checks for order editing
- Input sanitization and output escaping
- No direct file access protection

## Development

### File Structure

```
plugins/twintack-manual-order-payments/
├── twintack-manual-order-payments.php    # Main plugin file
├── includes/
│   └── class-admin-order-enhancements.php # Core functionality
├── assets/
│   └── js/admin-order-payments.js        # JavaScript for admin interface
└── README.md                             # This file
```

### Coding Standards

- Follows WordPress Coding Standards
- PSR-4 autoloading compatible structure
- Comprehensive PHPDoc documentation
- Proper internationalization ready

## Changelog

### 1.0.2
- **Fixed**: HPOS compatibility warning in WordPress admin plugins page
- **Fixed**: Moved HPOS compatibility declaration to `before_woocommerce_init` hook (proper timing)
- **Improved**: Plugin now correctly recognized as HPOS compatible by WooCommerce
- **Updated**: Documentation reflects HPOS compatibility status

### 1.0.1
- Added HPOS (High-Performance Order Storage) compatibility
- Fixed WooCommerce feature compatibility warnings
- Enhanced support for both traditional and HPOS order storage

### 1.0.0
- Initial release
- Stripe gateway availability for admin orders
- Payment processing interface
- Grip Manager integration
- Debug logging system

## Support

For support, feature requests, or bug reports, please contact the TwinTack development team.

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html 