# TwinTack Bulk Invoice Manager - New Features Guide

## Overview

Version 4.2.0 introduces comprehensive bulk invoice management capabilities to streamline order processing and reporting. This guide covers the three main new features you requested.

## 🔄 1. Bulk Edit - Mark Multiple Orders as Paid

### From Orders List Page
1. **Navigate to**: WooCommerce → Orders
2. **Select Orders**: Check the boxes for multiple orders you want to mark as paid
3. **Bulk Action**: From the "Bulk actions" dropdown, select "Mark as Paid (TwinTack)"
4. **Apply**: Click "Apply" to process all selected orders

### From Dedicated Admin Page
1. **Navigate to**: WooCommerce → Bulk Invoice Manager
2. **View Statistics**: See counts of invoiced, processing, and completed orders
3. **Bulk Process**: Click "Mark All Invoiced Orders as Paid" to process all invoiced orders at once

### What Happens When You Bulk Mark as Paid:
- ✅ Order status changes to "Processing"
- ✅ Payment marked as complete
- ✅ Shippo status updated to "Paid" and ready for fulfillment
- ✅ Automatic grip creation triggered (if enabled)
- ✅ Order notes added for audit trail
- ✅ Shippo sync automatically triggered

## 📊 2. CSV Export for Reporting

### Export Options Available:

#### A. Export Invoiced Orders Only
- **From Orders Page**: Click "Export Invoiced Orders (CSV)" button at top
- **From Bulk Manager**: Click "Export Invoiced Orders" button
- **Contains**: All orders with "invoiced" status

#### B. Export All Orders
- **From Orders Page**: Click "Export All Orders (CSV)" button at top  
- **From Bulk Manager**: Click "Export All Orders" button
- **Contains**: All orders regardless of status

#### C. Export Selected Orders
- **From Orders Page**: Select specific orders → Bulk Actions → "Export Selected (CSV)"
- **Contains**: Only the orders you selected

### CSV File Contains:
- Order ID and Order Number
- Date Created and Current Status
- Customer Name and Email
- Billing and Shipping Addresses
- Payment Method and Order Total
- Order Items (with quantities)
- Shippo Status and Tracking Numbers
- Order Notes

### File Naming:
- Files are automatically named with timestamps: `invoiced_orders_2025-01-08_14-30-25.csv`
- UTF-8 encoded for proper Excel compatibility

## 💳 3. Individual Order "Mark as Paid" Button

### Prominent Payment Processing Section
When editing any individual order, you'll now see a prominent **"Quick Payment Processing"** section with:

#### Primary Action Button:
- **"✅ Mark as Paid & Ready to Ship"** (green button)
  - Instantly marks order as paid
  - Sets status to "Processing" 
  - Triggers Shippo sync for fulfillment
  - Perfect for after completing manual payment processing

#### Secondary Action Button:
- **"📄 Set to Invoice (Send Payment Link)"** (orange button)
  - Sets order to "Invoiced" status
  - Creates Stripe payment link
  - Emails payment link to customer
  - Only shows if customer has email address

#### Visual Status Indicators:
- Shows current order status and total
- Dynamic status messages ("Ready for Payment Processing", "Awaiting Customer Payment", etc.)
- Customer email display for reference

### Location:
- Appears prominently at the top of individual order edit pages
- Only shows for orders that need payment processing (not already completed)
- Bright yellow background for high visibility

## 🔧 Technical Integration

### Shippo Integration
All new payment processing features automatically:
- Update Shippo fulfillment status
- Set orders as ready for fulfillment
- Trigger Shippo API sync
- Clear any payment holds

### Security
- All AJAX requests use WordPress nonces for security
- Proper capability checks (requires `edit_shop_orders` permission)
- Input sanitization and validation

### Error Handling
- Clear error messages for users
- Detailed logging for troubleshooting
- Graceful fallbacks if services are unavailable

## 📍 Where to Find Everything

### Bulk Operations:
1. **WooCommerce → Orders** (bulk actions dropdown + export buttons)
2. **WooCommerce → Bulk Invoice Manager** (dedicated admin page)

### Individual Order Processing:
- **Edit any order** → Look for yellow "Quick Payment Processing" section at top

### Reporting:
- **CSV exports** available from orders page or bulk manager
- **Order statistics** on the Bulk Invoice Manager page

## 💡 Tips for Efficient Use

1. **Daily Workflow**: Use the Bulk Invoice Manager page to see order counts and process invoiced orders in batches
2. **Reporting**: Export orders regularly for accounting and fulfillment tracking
3. **Individual Processing**: Use the prominent "Mark as Paid" buttons for quick order processing
4. **Customer Service**: The export includes customer emails and addresses for easy reference

## 🔍 Troubleshooting

### If Features Don't Appear:
- Ensure you have `edit_shop_orders` permission
- Check that the plugin is activated and updated to version 4.2.0
- Clear any caching (page/object cache)

### For Technical Issues:
- Check WooCommerce → Status → Logs for "twintack-manual-payments" entries
- Enable WordPress debug mode for detailed error information

---

**Version**: 4.2.0  
**Last Updated**: January 8, 2025
