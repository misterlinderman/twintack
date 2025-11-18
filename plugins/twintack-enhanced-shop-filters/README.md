# TwinTack Enhanced Shop Filters

A comprehensive WordPress plugin that enhances WooCommerce shop filtering and sorting capabilities with admin-configurable options, variation swatch integration, and visual color filtering.

## ✅ Latest Updates (v1.0.1)

### 🔧 WooCommerce Compatibility Fixed
- **✅ Fixed**: WooCommerce HPOS (High-Performance Order Storage) compatibility
- **✅ Fixed**: Updated plugin headers to meet WooCommerce standards
- **✅ Fixed**: Proper dependency checks and error handling
- **✅ Fixed**: No more compatibility warnings in WooCommerce admin

### 🎨 Variation Swatch Integration
- **✅ New**: Automatic detection of "Variation Swatches for WooCommerce" plugin
- **✅ New**: Uses actual color swatches, dual-color gradients, and image swatches from your variation plugin
- **✅ New**: Seamless integration with your existing product swatch configurations
- **✅ New**: Falls back to built-in color mapping when swatch plugin is not active

### 🚀 Enhanced Features
- **✅ Improved**: Better admin interface with dependency status display
- **✅ Improved**: Enhanced accessibility with keyboard navigation and ARIA labels
- **✅ Improved**: Responsive design for mobile devices
- **✅ Improved**: Better error handling and user feedback

## Features

### 🎯 Advanced Filtering Options
- **Color Filter**: Visual color swatches with 40+ color mappings + variation swatch integration
- **Pattern Filter**: Filter by product patterns (stripes, dots, gradients, etc.)
- **Sport Filter**: Filter by sport categories (baseball, fishing, etc.)
- **Brand Filter**: Filter by product brands
- **Category Filter**: WooCommerce product category selection
- **Price Range Filter**: Min/max price filtering with number inputs
- **URL-based Filtering**: SEO-friendly filter URLs that work with existing theme

### 🎨 Visual Color Swatches
- **Automatic Integration**: Detects and uses "Variation Swatches for WooCommerce" plugin
- **Smart Color Mapping**: Handles color names like "Flamethrower", "Ocean", "Blue/Pink"
- **Dual Color Support**: Gradient swatches for multi-color products
- **Image Swatches**: Uses custom images when configured in variation plugin
- **Interactive Design**: Click to select, hover effects, active states
- **Accessibility**: Keyboard navigation and screen reader support

### 🔧 Admin Controls
- **WordPress Dashboard**: Full admin interface at **WooCommerce > Shop Filters**
- **Enable/Disable Filters**: Control which filters appear on shop pages
- **Custom Sorting Options**: Configure available sort options
- **Default Sort Selection**: Set the default product sorting
- **Display Style Options**: Modal (default), sidebar, or horizontal bar
- **AJAX Toggle**: Enable/disable AJAX filtering (currently form-based)

### 🛠️ Technical Features
- **Theme Integration**: Works with existing TwinTack theme filtering system
- **URL Parameter Support**: Uses `filter_pa_color`, `filter_pa_pattern`, etc.
- **WooCommerce Standards**: Follows WooCommerce coding standards
- **HPOS Compatible**: Works with High-Performance Order Storage
- **Responsive Design**: Mobile-first design with touch-friendly swatches
- **Performance Optimized**: Conditional loading and efficient queries

## Installation

1. **Upload Plugin**:
   - Upload the `twintack-enhanced-shop-filters` folder to `/wp-content/plugins/`
   - Or install via WordPress admin: Plugins > Add New > Upload Plugin

2. **Activate Plugin**:
   - Go to **Plugins** in WordPress admin
   - Find "TwinTack Enhanced Shop Filters" and click **Activate**

3. **Configure Settings**:
   - Go to **WooCommerce > Shop Filters** in WordPress admin
   - Enable desired filters and configure options

## Setup Instructions

### 1. Product Attributes Setup
1. Go to **WooCommerce > Products > Attributes**
2. Create attributes like "Color", "Pattern", "Sport", "Brand"
3. Set **"Enable archives?"** to **Yes** for filterable attributes
4. Add terms to your attributes (e.g., Red, Blue, Green for Color)
5. Assign attributes to your products

### 2. Variation Swatches Setup (Optional but Recommended)
1. Install **"Variation Swatches for WooCommerce"** plugin (free or pro)
2. Go to **Products > Attributes > Color** (or your color attribute)
3. Edit color terms to add:
   - **Color swatches**: Set hex color codes
   - **Dual colors**: Create gradient swatches
   - **Image swatches**: Upload custom color images
4. Save changes - the filter will automatically use these swatches

### 3. Plugin Configuration
1. Go to **WooCommerce > Shop Filters**
2. **Enable filters** you want to show (Color, Pattern, Sport, Brand, Category, Price)
3. **Choose display style**: Modal (default), Sidebar, or Horizontal Bar
4. **Configure sorting options**: Select available sort options and default
5. **Save changes**

## Usage

### For Customers
- **Filter Button**: Click "Filter Products" on shop pages
- **Color Swatches**: Click colored circles to filter by color
- **Dropdown Filters**: Use dropdowns for pattern, sport, brand, category
- **Price Range**: Enter min/max prices
- **Clear Filters**: Reset all filters with one click

### For Administrators
- **Admin Interface**: Go to **WooCommerce > Shop Filters**
- **Enable/Disable**: Control which filters appear
- **Styling**: Choose how filters are displayed
- **Sorting**: Configure available sort options
- **Dependencies**: Check if variation swatches plugin is active

## Filter URLs

The plugin creates SEO-friendly filter URLs:
- `?filter_pa_color=red` - Filter by color
- `?filter_pa_pattern=gradient` - Filter by pattern
- `?filter_pa_sport=baseball` - Filter by sport
- `?filter_pa_brand=twintack` - Filter by brand
- `?product_cat=grips` - Filter by category
- `?min_price=10&max_price=50` - Filter by price range
- `?filter_pa_color=red&filter_pa_pattern=gradient` - Multiple filters

## Compatibility

### Required
- ✅ **WordPress**: 5.8+
- ✅ **WooCommerce**: 7.5+
- ✅ **PHP**: 7.4+

### Recommended
- ✅ **Variation Swatches for WooCommerce**: For enhanced color swatches
- ✅ **TwinTack Theme**: Optimized for TwinTack theme integration

### Tested With
- ✅ **WooCommerce HPOS**: High-Performance Order Storage
- ✅ **WordPress**: Up to 6.4
- ✅ **WooCommerce**: Up to 9.6
- ✅ **Mobile Devices**: Responsive design
- ✅ **Accessibility**: Screen readers and keyboard navigation

## Troubleshooting

### Common Issues

#### 1. WooCommerce Compatibility Warning
- **✅ Fixed in v1.0.1**: Plugin now fully compatible with WooCommerce HPOS
- **Solution**: Update to latest plugin version

#### 2. Filters Not Working
- **Check**: Go to **WooCommerce > Products > Attributes**
- **Verify**: Set "Enable archives?" to "Yes" for filterable attributes
- **Confirm**: Products have attributes assigned
- **Settings**: Enable filters in **WooCommerce > Shop Filters**

#### 3. Color Swatches Not Showing
- **Install**: "Variation Swatches for WooCommerce" plugin for enhanced swatches
- **Configure**: Set up color swatches in **Products > Attributes > Color**
- **Enable**: Color filter in plugin settings
- **Fallback**: Plugin includes built-in color mapping

#### 4. Admin Page Not Found
- **✅ Fixed**: Admin interface now at **WooCommerce > Shop Filters**
- **Required**: User must have `manage_woocommerce` capability
- **Check**: WooCommerce plugin is activated

#### 5. Swatch Colors Not Matching
- **✅ New**: Plugin now uses actual variation swatch data
- **Setup**: Configure colors in variation swatch plugin
- **Sync**: Colors automatically sync with filter swatches

## Advanced Customization

### Custom Color Mapping
Extend the color mapping with a filter:

```php
add_filter('twintack_color_mapping', function($color_map) {
    $color_map['custom_color'] = '#123456';
    $color_map['brand_blue'] = '#0066cc';
    return $color_map;
});
```

### Custom CSS
Override plugin styles in your theme:

```css
/* Custom swatch size */
.color-swatch {
    width: 50px;
    height: 50px;
}

/* Custom modal styling */
.filter-modal-content {
    border-radius: 12px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}
```

### Integration with Other Plugins
The plugin works with:
- **Variation Swatches for WooCommerce**: Automatic integration
- **WPML**: Multi-language support
- **Polylang**: Translation ready
- **Custom Themes**: Follows WordPress/WooCommerce standards

## Developer Notes

### Hooks and Filters
- `twintack_color_mapping` - Modify color name to hex mapping
- `woocommerce_catalog_orderby` - Modify sort options
- `woocommerce_default_catalog_orderby` - Change default sort

### File Structure
```
twintack-enhanced-shop-filters/
├── assets/
│   ├── css/enhanced-filters.css
│   └── js/enhanced-filters.js
├── README.md
└── twintack-enhanced-shop-filters.php
```

### Performance
- **Conditional Loading**: Assets only load on shop pages
- **Efficient Queries**: Optimized database queries
- **Caching**: Respects WordPress caching
- **Minification**: Ready for production use

## Support

For support with this plugin:
1. Check the troubleshooting section above
2. Verify your WooCommerce and WordPress versions
3. Test with default theme to isolate conflicts
4. Review browser console for JavaScript errors

## Changelog

### v1.0.1 (Latest)
- **Fixed**: WooCommerce HPOS compatibility
- **Fixed**: Plugin headers and requirements
- **Added**: Variation swatch integration
- **Added**: Enhanced admin interface
- **Improved**: Accessibility and mobile support
- **Improved**: Error handling and user feedback

### v1.0.0
- Initial release
- Basic filtering functionality
- Color swatch support
- Admin interface
- Theme integration

---

**Plugin Author**: TwinTack  
**Plugin Version**: 1.0.1  
**WordPress Compatibility**: 5.8+  
**WooCommerce Compatibility**: 7.5+  
**Last Updated**: 2024 