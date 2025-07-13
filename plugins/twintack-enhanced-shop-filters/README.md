# TwinTack Enhanced Shop Filters

A comprehensive WordPress plugin that extends WooCommerce shop filtering and sorting capabilities with admin-configurable options and multiple display modes.

## Features

### ✨ Advanced Filtering Options
- **Product Attributes**: Color, Pattern, Sport, Brand, and custom attributes
- **Price Range**: Min/max price filtering
- **Category Filtering**: Product category selection
- **AJAX Support**: Filter without page reloads
- **Multiple Display Modes**: Horizontal bar, sidebar, or modal

### 🔧 Admin Controls
- **Dashboard Integration**: Manage filters from WooCommerce admin
- **Custom Sorting Options**: Configure available sort options
- **Filter Style Selection**: Choose how filters are displayed
- **Enable/Disable Filters**: Control which filters appear
- **Default Sort Configuration**: Set the default sorting option

### 🎨 Three Display Modes

#### 1. **Horizontal Filter Bar**
- Displays filters in a horizontal layout above products
- Perfect for desktop layouts
- Auto-applies filters as users make selections

#### 2. **Sidebar Filters**
- Traditional sidebar filter layout
- Great for desktop sites with sidebar space
- Organized filter groups with clear sections

#### 3. **Modal Filters** (Default)
- Integrates with existing modal system
- Mobile-friendly approach
- Filters applied via "Apply Filters" button

### 🚀 Enhanced User Experience
- **Color Swatches**: Visual color selection
- **Loading States**: Visual feedback during filtering
- **URL Management**: Filter states reflected in URLs
- **Clear Filters**: Easy way to reset all filters
- **Responsive Design**: Works on all device sizes

## Installation

1. **Upload the plugin** to your `/wp-content/plugins/` directory
2. **Activate the plugin** through the WordPress admin
3. **Configure settings** at **WooCommerce > Shop Filters**

## Setup Requirements

### Product Attributes
Before using the filters, you need to set up product attributes:

1. Go to **WooCommerce > Products > Attributes**
2. Create attributes like:
   - `color` (for Color filtering)
   - `pattern` (for Pattern filtering)
   - `sport` (for Sport filtering)
   - `brand` (for Brand filtering)
3. Set **"Enable archives?"** to **Yes** for filterable attributes
4. Add terms to your attributes (e.g., Red, Blue, Green for Color)
5. Assign attributes to your products

### Current Integration
This plugin is designed to work with the existing TwinTack theme filtering system. It enhances the current implementation by:
- Adding admin controls for filter management
- Providing multiple display options
- Enhancing the user experience with AJAX
- Adding visual improvements like color swatches

## Configuration

### Admin Settings
Access the plugin settings at **WooCommerce > Shop Filters**:

#### Sorting Options
- **Custom Sort Options**: Choose which sorting options to display
- **Default Sorting**: Set the default sorting method
- Available options:
  - Default sorting (menu order)
  - Sort by popularity
  - Sort by average rating
  - Sort by latest
  - Sort by price: low to high
  - Sort by price: high to low
  - Sort by color
  - Sort by pattern

#### Filter Display
- **Display Style**: Choose between horizontal, sidebar, or modal
- **Enabled Filters**: Select which filters to show
- **AJAX Filtering**: Enable/disable AJAX functionality

#### Available Filters
- Filter by Color
- Filter by Pattern
- Filter by Price Range
- Filter by Category
- Filter by Sport
- Filter by Brand

## Usage

### For Administrators

1. **Configure Attributes**: Set up product attributes in WooCommerce
2. **Enable Filters**: Choose which filters to display in the admin
3. **Set Display Style**: Choose how filters should appear
4. **Configure Sorting**: Select available sorting options
5. **Test**: Visit the shop page to see the filters in action

### For Customers

#### Horizontal Filters
- Filters appear above the product grid
- Select options from dropdowns
- Filters apply automatically

#### Sidebar Filters
- Filters appear in a sidebar widget
- Select options from dropdowns
- Filters apply automatically

#### Modal Filters
- Click "Filter Products" button
- Select filter options in the modal
- Click "Apply Filters" to see results

### URL-Based Filtering
The plugin supports URL-based filtering for SEO and sharing:
- `?filter_pa_color=red` - Filter by red color
- `?filter_pa_pattern=gradient` - Filter by gradient pattern
- `?filter_pa_color=red&filter_pa_pattern=gradient` - Multiple filters
- `?min_price=10&max_price=50` - Price range filtering

## Technical Details

### Hooks and Filters
The plugin uses standard WordPress and WooCommerce hooks:
- `woocommerce_catalog_orderby_options` - Modify sorting options
- `woocommerce_default_catalog_orderby` - Set default sorting
- `woocommerce_before_shop_loop` - Display filters
- `pre_get_posts` - Handle filter queries

### AJAX Integration
- Uses WordPress AJAX system
- Secure with nonce verification
- Handles complex filter combinations
- Updates URL with history management

### Performance Considerations
- Caches filter options
- Optimized database queries
- Minimal JavaScript footprint
- CSS optimized for performance

## Compatibility

### WordPress/WooCommerce
- **WordPress**: 5.8+
- **WooCommerce**: 5.0+
- **PHP**: 7.4+

### Theme Integration
- Designed for TwinTack theme
- Compatible with most WooCommerce themes
- Respects existing styling
- Minimal theme conflicts

## Troubleshooting

### Common Issues

#### Filters Not Appearing
- Check that product attributes are set up correctly
- Ensure "Enable archives?" is set to "Yes" for attributes
- Verify filters are enabled in admin settings

#### AJAX Not Working
- Check browser console for JavaScript errors
- Verify AJAX is enabled in plugin settings
- Check for plugin conflicts

#### Styling Issues
- Clear any caching plugins
- Check for CSS conflicts
- Verify theme compatibility

#### No Products Found
- Ensure products have the required attributes
- Check that attribute terms match exactly
- Verify product visibility settings

### Debug Mode
Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Customization

### Custom CSS
Add custom styles to your theme's CSS:
```css
/* Customize filter appearance */
.twintack-enhanced-filters {
    /* Your custom styles */
}
```

### Custom JavaScript
Extend the functionality with custom JavaScript:
```javascript
// Extend the filtering behavior
jQuery(document).ready(function($) {
    // Your custom code
});
```

### Hook Usage
Use WordPress hooks to extend functionality:
```php
// Add custom filter processing
add_filter('twintack_filter_query_args', 'my_custom_filter_args');
function my_custom_filter_args($args) {
    // Modify the query arguments
    return $args;
}
```

## Developer Notes

### File Structure
```
twintack-enhanced-shop-filters/
├── twintack-enhanced-shop-filters.php (Main plugin file)
├── assets/
│   ├── js/
│   │   └── enhanced-filters.js
│   └── css/
│       └── enhanced-filters.css
├── includes/ (Future expansion)
└── README.md
```

### Key Functions
- `modify_sorting_options()` - Customizes WooCommerce sorting
- `display_enhanced_filters()` - Renders filter interface
- `ajax_filter_products()` - Handles AJAX filtering
- `render_filter_control()` - Renders individual filters

### Data Storage
- Plugin settings stored in WordPress options table
- Uses `get_option()` and `update_option()` for configuration
- Leverages WooCommerce's existing attribute system

## Support

### Documentation
- Complete inline code documentation
- WordPress coding standards compliance
- Extensive comments for customization

### Future Enhancements
- Additional filter types
- Advanced styling options
- Performance optimizations
- Multi-language support

## License

This plugin is developed specifically for TwinTack and follows WordPress coding standards and best practices.

## Changelog

### Version 1.0.0
- Initial release
- Basic filtering functionality
- Admin interface
- Three display modes
- AJAX support
- Color swatch support
- Responsive design 