# TwinTack Grip Manager - Volume Pricing Setup Guide

## Overview

The TwinTack Grip Manager now includes a flexible volume pricing system that allows you to configure quantity restrictions and volume discounts for any product in your catalog.

## Key Features

✅ **Configurable Product Selection** - Choose any product to be your "custom grip" product  
✅ **Flexible Quantity Rules** - Set custom minimum quantities and step increments  
✅ **Volume Pricing Tiers** - Configure multiple pricing levels with fixed dollar or percentage discounts  
✅ **Admin Interface** - Easy-to-use admin dashboard for configuration  
✅ **Wholesale Integration** - Works alongside your existing wholesale pricing plugin  
✅ **Real-time Validation** - Frontend quantity validation with helpful error messages  
✅ **Price Preview** - Dynamic price calculations shown to customers  

## Setup Instructions

### Step 1: Enable Volume Pricing

1. Go to **WordPress Admin > Grip Designs > Volume Pricing**
2. Check **"Enable volume pricing system"**
3. Click **"Save Changes"**

### Step 2: Select Your Custom Grip Product

1. From the **"Custom Grip Product"** dropdown, select your product (currently ID 1196)
2. Click **"Save Changes"**

### Step 3: Configure Global Settings

Set your default pricing rules:

- **Minimum Quantity**: `25` (customers must order at least 25 units)
- **Quantity Step**: `25` (orders must be in multiples of 25)
- **Volume Break Quantity**: `75` (volume pricing kicks in at 75+ units)
- **Volume Discount Type**: `Fixed Amount ($)`
- **Volume Discount Amount**: `2.00` (customers save $2.00 per unit at 75+)

### Step 4: Configure Product-Specific Settings (Optional)

1. Go to **Products > Edit** your custom grip product
2. Scroll down to the **"Custom Grip Volume Pricing"** meta box
3. Choose between:
   - **Use global pricing settings** (uses settings from Step 3)
   - **Custom settings** (override global settings for this product only)

## Example Configuration

Based on your requirements (original price $19.99, volume price $17.99):

- **Regular Price**: $19.99 (set in normal WooCommerce product settings)
- **Volume Discount Type**: Fixed Amount ($)
- **Volume Discount Amount**: 2.00
- **Volume Break Quantity**: 75

This will result in:
- **25-74 units**: $19.99 each
- **75+ units**: $17.99 each (save $2.00 per unit)

## Admin Features

### Volume Pricing Dashboard
- **Location**: Grip Designs > Volume Pricing
- **Features**: Product selection, global settings, configuration summary

### Product Meta Box
- **Location**: Product edit page (for selected volume pricing product)
- **Features**: Product-specific overrides, global setting toggle

### Configuration Summary
The admin dashboard shows a real-time summary of your current pricing:
- Selected product name and ID
- Current pricing tiers
- Minimum order requirements
- Order increment rules

## Frontend Experience

### Product Page
- **Volume pricing tiers** displayed clearly
- **Quantity validation** with real-time error messages
- **Price preview** that updates as quantity changes
- **Professional styling** that matches your theme

### Cart & Checkout
- **Automatic price calculation** based on quantity
- **Strike-through pricing** showing savings for volume orders
- **Validation** prevents invalid quantity changes

## Wholesale Integration

The volume pricing system is designed to work alongside your existing wholesale management plugin. The pricing calculations are applied through WooCommerce's standard pricing hooks, so wholesale pricing should take precedence when applicable.

## Technical Notes

### Database Storage
- Global settings: WordPress options table
- Product settings: WooCommerce product meta

### Performance
- Minimal performance impact
- Only activates when volume pricing is enabled
- Uses efficient WooCommerce hooks

### Compatibility
- Compatible with WooCommerce 5.0+
- Works with most themes and plugins
- Tested with wholesale pricing plugins

## Troubleshooting

### Volume Pricing Not Working
1. Ensure volume pricing is **enabled** in the settings
2. Verify a **product is selected** as the volume pricing product
3. Check that the **plugin is active**

### Quantity Validation Issues
1. Clear any browser cache
2. Ensure JavaScript is enabled
3. Check for theme conflicts in developer tools

### Price Not Updating
1. Clear WooCommerce cart
2. Verify product regular price is set correctly
3. Check discount amount configuration

## Migration from Theme Functions

If you were previously using the hardcoded functions in your theme:

1. ✅ **Old code removed** - Legacy functions have been cleaned up
2. ✅ **Settings preserved** - Default settings match your previous configuration
3. ✅ **Enhanced flexibility** - Now configurable through admin interface

## Support

For issues or questions:
1. Check this documentation first
2. Review WordPress admin notices for any configuration warnings
3. Test with a simple product configuration
4. Contact your developer for custom modifications

---

**Version**: 1.6.0  
**Last Updated**: Current Version  
**Compatibility**: WooCommerce 5.0+, WordPress 5.8+ 