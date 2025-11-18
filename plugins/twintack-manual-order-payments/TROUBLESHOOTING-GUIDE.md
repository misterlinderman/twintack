# TwinTack Invoice System - Troubleshooting Guide

## Issues Fixed in Version 1.4.0

This guide addresses the specific issues you encountered with orders #1569 and #1582:

1. ✅ **Email delivery problems** 
2. ✅ **Invoiced orders not appearing in admin lists**
3. ✅ **Shippo not recognizing invoiced orders**

---

## Quick Fixes Applied

### 📧 **Email Delivery Issues**

**Problem**: Customers not receiving invoice emails despite success messages.

**Solutions Implemented**:
- Enhanced email debugging with detailed error logging
- Added fallback to WooCommerce mailer when `wp_mail` fails  
- Added email validation and detailed error reporting
- Order notes now show specific email delivery status

**To Test**:
1. Go to **WooCommerce > TwinTack Debug** in your admin
2. Use the "Email Testing" section to test delivery
3. Check order notes for detailed email status

### 📋 **Orders Missing from Admin Lists**

**Problem**: Orders with "Invoiced" status disappear from WooCommerce order lists.

**Solutions Implemented**:
- Added proper WooCommerce order status registration
- Fixed admin query modifications to include invoiced orders
- Added "Invoiced" filter to order list views
- Enhanced order visibility with proper post status handling

**To Verify**:
- Invoiced orders should now appear in main order list
- New "Invoiced" filter tab should be visible
- Order count should include invoiced orders

### 🚢 **Shippo Integration Issues**

**Problem**: Shippo not recognizing invoiced orders for fulfillment.

**Solutions Implemented**:
- Created dedicated Shippo integration class
- Added proper status mapping: Invoiced = "Payment Pending"
- Enhanced order metadata for Shippo tracking
- Added fulfillment hold mechanism for payment pending orders

**Status Mapping**:
- **Invoiced** → **Payment Pending** (awaiting customer payment)
- **Processing** → **Paid** (ready for fulfillment)
- **Completed** → **Shipped** (order fulfilled)

---

## Debug Tools Available

### Access Debug Tools
Go to **WooCommerce > TwinTack Debug** in your WordPress admin.

### Email Testing
- **Basic Email Test**: Tests WordPress mail functionality
- **Invoice Email Test**: Tests invoice-specific email delivery
- Real-time error reporting and logging

### Order Status Debugging  
- Check if invoiced orders are properly registered
- Verify order visibility in admin queries
- Count and list all invoiced orders

### Shippo Integration Testing
- Debug order status mapping for Shippo
- Force manual sync with Shippo
- View fulfillment hold status

---

## Manual Fixes for Existing Orders

### For Orders #1569 and #1582

1. **Re-send Invoice Emails**:
   ```
   - Edit the order in WooCommerce admin
   - Look for "TwinTack Payment Processing" section
   - Click "Pay Later (Invoice)" again
   - Check order notes for email delivery status
   ```

2. **Force Shippo Sync**:
   ```
   - Go to WooCommerce > TwinTack Debug
   - Use "Shippo Integration" section
   - Enter order ID (1569 or 1582)
   - Click "Force Shippo Sync"
   ```

3. **Verify Order Visibility**:
   ```
   - Go to WooCommerce > Orders
   - Look for "Invoiced" tab/filter
   - Orders should now be visible
   ```

---

## Email Configuration Checklist

If emails still aren't sending:

### WordPress Mail Configuration
1. **Test Basic WordPress Email**:
   - Go to TwinTack Debug > Email Testing
   - Send test email to yourself
   - Check if it arrives

2. **SMTP Plugin** (if needed):
   - Install WP Mail SMTP or similar plugin
   - Configure with your hosting provider's SMTP settings
   - Test email delivery

3. **Server Mail Settings**:
   - Contact your hosting provider
   - Verify PHP `mail()` function is enabled
   - Check server mail logs

### WooCommerce Email Settings
1. **Admin Email Address**:
   - Go to Settings > General
   - Verify admin email is correct
   - This is used as "From" address

2. **WooCommerce Email Settings**:
   - Go to WooCommerce > Settings > Emails
   - Verify email settings are configured
   - Test email functionality

---

## Shippo Configuration

### Verify Integration
1. **Check Shippo Plugin**:
   - Ensure Shippo plugin is active and configured
   - Verify API keys are set correctly

2. **Order Metadata**:
   - Edit an invoiced order
   - Look for "Shippo Fulfillment Status" meta box
   - Should show "Payment Pending" status

3. **Manual Sync**:
   - Use debug tools to force sync
   - Check order notes for sync status

---

## Common Issues & Solutions

### Issue: "Orders Still Not Visible"
**Solution**: Clear any caching plugins and refresh admin page.

### Issue: "Emails Still Not Sending"  
**Solution**: 
1. Use debug tools to test email functionality
2. Check WordPress mail configuration
3. Consider SMTP plugin for reliable delivery

### Issue: "Shippo Still Not Recognizing Orders"
**Solution**:
1. Force manual sync via debug tools
2. Check Shippo plugin configuration
3. Verify order has proper fulfillment metadata

---

## Debug Log Locations

### Plugin Debug Logs
- **Location**: `/wp-content/uploads/wc-logs/`
- **Files**: `twintack-manual-payments-*.log`
- **Enable**: Set debug mode to "Yes" in plugin settings

### WordPress Debug Logs  
- **Location**: `/wp-content/debug.log`
- **Enable**: Add to `wp-config.php`:
  ```php
  define('WP_DEBUG', true);
  define('WP_DEBUG_LOG', true);
  ```

---

## Support Information

### When Contacting Support
Include this information:
1. **Order IDs** experiencing issues
2. **Debug tool results** from TwinTack Debug page
3. **Error messages** from order notes or logs
4. **Email delivery test results**
5. **Shippo sync status** for affected orders

### Plugin Version
Current version: **1.4.0** (includes all fixes)

---

## Next Steps

1. **Update Plugin**: Ensure you're running version 1.4.0
2. **Test Debug Tools**: Use the new debug interface 
3. **Re-process Problem Orders**: Use "Pay Later" button again for orders #1569 and #1582
4. **Verify Email Delivery**: Check if customers receive invoices
5. **Confirm Shippo Integration**: Verify orders appear in Shippo with correct status

The enhanced system should now properly handle email delivery, order visibility, and Shippo integration for your invoice workflow. 