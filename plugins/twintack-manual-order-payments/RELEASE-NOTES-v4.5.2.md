# TwinTack Manual Order Payments v4.5.2 - Wholesale Pricing Fix

**Release Date**: January 14, 2025  
**Version**: 4.5.2  
**Type**: Bug Fix Release

## 🎯 Overview

This release fixes critical wholesale pricing issues in PDF invoice generation, ensuring that Drop Ship customers see the correct wholesale pricing ($14.49) instead of MSRP ($21.99) in their invoices.

## 🔧 Key Fixes

### PDF Invoice Wholesale Pricing
- **Fixed Unit Pricing**: Line items now show wholesale unit price ($14.49) instead of MSRP ($21.99)
- **Fixed Subtotal**: Subtotal now matches line item pricing for wholesale customers
- **Added Visual Indicators**: "WHOLESALE" badges appear on wholesale items
- **Proper Role Detection**: Fixed wholesale role detection for "Drop Ship" customers

### Enhanced Integration
- **WooCommerce Wholesale Prices Plugin**: Properly integrates with the plugin's built-in role detection
- **Comprehensive Fallback Logic**: Multiple fallback methods ensure wholesale pricing is applied correctly
- **Consistent Pricing**: Both CSV exports and PDF invoices now use the same wholesale pricing logic

## 🐛 Issues Resolved

### Before (v4.5.1)
```
PDF Invoice Display:
- Line Item: $21.99 (MSRP - incorrect)
- Subtotal: $21.99 (MSRP - incorrect)  
- Shipping: $5.00
- Total: $19.49 (correct)
```

### After (v4.5.2)
```
PDF Invoice Display:
- Line Item: $14.49 (wholesale - correct) ✅
- Subtotal: $14.49 (wholesale - correct) ✅
- Shipping: $5.00
- Total: $19.49 (correct) ✅
```

## 🔍 Technical Details

### Role Detection Fix
- **Previous**: Manual string matching for roles containing "wholesale"
- **Current**: Uses `WWP_Wholesale_Roles::getUserWholesaleRole()` for proper detection
- **Result**: Correctly detects "Drop Ship" as a wholesale role

### Subtotal Calculation Fix
- **Previous**: Used `$order->get_subtotal()` (MSRP subtotal)
- **Current**: Calculates wholesale subtotal from actual line items
- **Result**: Subtotal matches line item pricing

### Enhanced Logging
- Added comprehensive logging for wholesale pricing detection
- Detailed logs show role detection and pricing calculation process
- Easier troubleshooting for wholesale pricing issues

## 📋 Files Modified

- `plugins/twintack-manual-order-payments/includes/class-bulk-invoice-manager.php`
- `plugins/twintack-manual-order-payments/includes/class-pdf-invoice-generator.php`
- `plugins/twintack-manual-order-payments/twintack-manual-order-payments.php`
- `plugins/twintack-manual-order-payments/CHANGELOG.md`

## 🧪 Testing

### Test Scenarios
1. **Drop Ship Customer**: Generate PDF invoice for Drop Ship customer
2. **Regular Customer**: Ensure regular customers still see MSRP pricing
3. **Multiple Items**: Test with orders containing multiple wholesale items
4. **Role Detection**: Verify proper detection of all wholesale roles

### Expected Results
- Drop Ship customers see wholesale pricing ($14.49) in line items and subtotal
- Regular customers see MSRP pricing ($21.99) as before
- "WHOLESALE" badges appear on wholesale items
- Subtotal matches line item pricing for all customer types

## 🔄 Compatibility

- **WordPress**: 5.8+
- **WooCommerce**: 5.0+
- **WooCommerce Wholesale Prices**: Free and Premium versions
- **PHP**: 7.4+

## 📚 Documentation

- **CSV-WHOLESALE-PRICING-FIX.md**: Documents CSV export wholesale pricing fixes
- **PDF-WHOLESALE-PRICING-FIX-FINAL.md**: Documents PDF invoice wholesale pricing fixes
- **CHANGELOG.md**: Complete changelog with all version history

## 🚀 Installation

1. **Backup**: Always backup your site before updating
2. **Update**: Replace plugin files with v4.5.2
3. **Test**: Generate PDF invoices for wholesale customers
4. **Verify**: Check that wholesale pricing is displayed correctly

## 🎉 Benefits

- **Customer Clarity**: Wholesale customers see accurate pricing in invoices
- **Consistent Experience**: PDF invoices match the actual charged amounts
- **Professional Appearance**: Clean, accurate invoices with proper wholesale indicators
- **Reduced Confusion**: Eliminates pricing discrepancies between line items and totals

## 🔮 Future Enhancements

- Enhanced wholesale role management
- Additional wholesale pricing display options
- Improved wholesale customer experience
- Advanced wholesale reporting features

---

**For support or questions about this release, contact the TwinTack development team.**
