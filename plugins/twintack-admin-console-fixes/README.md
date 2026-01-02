# TwinTack Admin Console Fixes Plugin

## Overview
This plugin fixes WordPress admin console errors and Variable Product type recognition issues. It addresses jQuery migration warnings, React component instability, useSelect hook warnings, and wholesale plugin conflicts.

## Features

### Console Error Fixes
- **jQuery Migration Warnings**: Suppresses deprecated jQuery method warnings
- **React Component Warnings**: Suppresses React defaultProps deprecation warnings
- **useSelect Hook Warnings**: Suppresses WordPress data store instability warnings
- **TinyMCE Errors**: Fixes admin-menu-editor plugin TinyMCE undefined errors
- **Duplicate DOM IDs**: Automatically makes duplicate element IDs unique

### Product Type Recognition Fix
- **Variable Product Detection**: Ensures Variable Products are properly recognized
- **Database Synchronization**: Keeps UI in sync with database values
- **Automatic Correction**: Detects and fixes product type mismatches
- **Real-time Updates**: Monitors and corrects product type changes

### Wholesale Plugin Compatibility
- **jQuery Delegate Patch**: Converts deprecated jQuery.delegate() to modern on() method
- **Event Handling**: Maintains wholesale plugin functionality
- **Product Type Consistency**: Ensures wholesale features work with correct product types

### Noun Project API Integration
- **Shared API Access**: Centralized Noun Project API integration for all TwinTack plugins
- **Icon Search**: Search millions of icons from The Noun Project
- **Icon Download**: Download icons in SVG format
- **Media Library Integration**: Save icons directly to WordPress media library
- **API Management**: Configure credentials and test connection from dedicated admin page
- **Developer Friendly**: Easy-to-use API methods available to all plugins

## Installation

1. Upload the `twintack-admin-console-fixes` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically start fixing console errors and product type issues

## Requirements

- WordPress 5.8 or higher
- WooCommerce 6.0 or higher
- PHP 7.4 or higher

## How It Works

### Console Error Suppression
The plugin intercepts console warnings and errors, filtering out known issues that don't affect functionality but create noise in the console.

### Product Type Recognition
1. Monitors product edit pages for type mismatches
2. Makes AJAX calls to verify database values
3. Automatically corrects UI when mismatches are detected
4. Maintains consistency during product saves

### Wholesale Plugin Patch
1. Patches jQuery delegate method before wholesale plugin loads
2. Converts deprecated methods to modern equivalents
3. Maintains all existing functionality
4. Prevents console warnings

## Files Structure

```
twintack-admin-console-fixes/
├── twintack-admin-console-fixes.php (Main plugin file)
├── includes/
│   ├── class-console-fixes.php
│   ├── class-product-type-fix.php
│   ├── class-wholesale-plugin-patch.php
│   └── class-nounproject-api.php
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       ├── product-type-fix.js
│       └── wholesale-plugin-patch.js
├── README.md
└── NOUNPROJECT-API.md
```

## AJAX Endpoints

- `twintack_get_product_type`: Retrieves actual product type from database
- `twintack_fix_product_type`: Updates product type if mismatch detected

## Hooks and Filters

### Actions
- `twintack-check-product-type`: Trigger product type consistency check
- `woocommerce-product-type-change`: Handle product type changes

### Filters
- `woocommerce_product_type_query`: Override product type queries when needed
- `script_loader_tag`: Fix script loading order issues

## Troubleshooting

### If console errors persist:
1. Clear browser cache
2. Check for plugin conflicts
3. Verify file permissions
4. Check WordPress debug log

### If product type still not recognized:
1. Ensure WooCommerce is active
2. Check for JavaScript errors
3. Verify AJAX endpoints are working
4. Test with default theme

## Performance Impact

- Minimal performance impact
- Scripts only load on product edit pages
- Efficient AJAX calls with proper caching
- No database queries on frontend

## Security

- All AJAX calls use proper nonce verification
- Input sanitization and validation
- No direct database access from JavaScript
- Proper capability checks

## Compatibility

### Tested With
- WordPress 5.8 - 6.8
- WooCommerce 6.0 - 9.0
- PHP 7.4 - 8.3

### Browser Support
- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+

## Noun Project API

This plugin includes centralized Noun Project API integration that can be used by all TwinTack plugins. 

### Setup
1. Navigate to **Noun Project** in WordPress admin
2. Enter your API Key and Secret
3. Click Test Connection to verify

### For Developers
See [NOUNPROJECT-API.md](NOUNPROJECT-API.md) for complete API documentation and usage examples.

```php
// Example usage in any TwinTack plugin
if (class_exists('TwinTack_NounProject_API')) {
    $api = TwinTack_NounProject_API::get_instance();
    $icons = $api->search_icons('baseball', 50, 1);
}
```

## Changelog

### Version 1.0.6
- Added Noun Project API integration
- Centralized icon search and download for all TwinTack plugins
- New standalone admin menu for API configuration
- Comprehensive API documentation

### Version 1.0.5
- Console error suppression
- Product type recognition fix
- Wholesale plugin compatibility
- jQuery delegate patch

## Support

For issues or questions:
1. Check this README first
2. Review browser console for errors
3. Check WordPress debug log
4. Contact development team

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html
