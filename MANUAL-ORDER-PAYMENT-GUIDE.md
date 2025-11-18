# Manual Order Payment System Guide

## Overview

The TwinTack manual order payment system has been converted to a standalone WordPress plugin that provides Stripe payment gateway support for admin-created orders, with proper integration with the grip manager system.

**Plugin Name**: TwinTack Manual Order Payments  
**Location**: `/plugins/twintack-manual-order-payments/`

## ✅ What This Fixes

- **Payment Gateway Availability**: Stripe payment gateway is now available for manual orders
- **Unified Payment System**: All customers (including wholesale) use Stripe for immediate payment
- **Manual Payment Processing**: Admin can mark orders as paid or process payments manually
- **Better Integration**: Works seamlessly with the existing grip manager purchase-based creation system

## 📦 Installation

1. The plugin is located at `/plugins/twintack-manual-order-payments/`
2. Go to **WordPress Admin > Plugins**
3. Find "TwinTack Manual Order Payments" and click **Activate**
4. The plugin will automatically work with your existing WooCommerce setup

**Note**: Version 1.0.1+ includes HPOS (High-Performance Order Storage) compatibility, resolving any WooCommerce feature incompatibility warnings.

## 🚀 How to Use

### Step 1: Create a Manual Order

1. Go to **WooCommerce > Orders > Add Order**
2. Add customer information and products as normal
3. **Important**: The system will now show Stripe and other available payment methods

### Step 2: Select Payment Method

In the order edit screen, you'll now see a **Payment Processing** section with:

- **Payment Method Dropdown**: Choose from available gateways (Stripe is the primary option)
- **Payment Action Buttons**: Two options for processing payment

### Step 3: Process Payment

Use the buttons in the Payment Processing section:

- **Mark as Paid**: Immediately marks order as paid and triggers grip creation workflows
- **Process Payment via Gateway**: Sets payment method and marks order as pending for manual gateway processing

## 💳 Payment Processing Details

### How It Works
The system provides two payment processing options:

1. **Mark as Paid**: For orders where payment has been received through external means (phone orders, etc.)
2. **Process Payment via Gateway**: For orders that need to be processed through Stripe or other gateways

### Wholesale Customers
- Wholesale customers use the same Stripe payment system as regular customers
- They receive different pricing automatically based on their user role
- No special payment terms - all payments are processed immediately
- **Net 60 Days**: Payment due within 60 days  
- **Cash on Delivery**: Payment upon delivery/pickup

## 🔧 Technical Details

### Payment Gateway Availability
The system automatically enables payment gateways for admin orders by:
- Overriding gateway availability restrictions
- Ensuring Stripe is accessible in admin context
- Adding manual payment options specifically for admin use

### Integration with Grip Manager
The enhanced system works with your existing grip manager plugin:
- **Purchase-based creation**: Grip design posts are still only created after payment completion
- **Order completion hooks**: All existing workflows (webhooks, status updates) continue to work
- **Audit trail**: Order notes track all payment processing actions

### Order Status Flow
```
Manual Order Created → Payment Method Selected → Payment Processed → Order Complete → Grip Post Created (if applicable)
```

## 🛠️ Configuration

### Enabling/Disabling Features
The system is automatically enabled when you create manual orders. No additional configuration needed.

### Stripe Configuration
Ensure your Stripe settings are properly configured in **WooCommerce > Settings > Payments > Stripe**.

### User Roles
The system works with all user types:
- **Regular customers**: Standard Stripe payment processing
- **Wholesale customers**: Same payment system with different pricing automatically applied

## 📋 Troubleshooting

### Payment Methods Not Showing
1. **Check Stripe Configuration**: Ensure Stripe keys are properly set
2. **Clear Cache**: Clear any caching plugins
3. **Check User Permissions**: Ensure you have `manage_woocommerce` capability

### Payment Processing Not Working
1. **AJAX Errors**: Check browser console for JavaScript errors
2. **Nonce Issues**: Try refreshing the page if nonce verification fails
3. **Server Errors**: Check PHP error logs for server-side issues

### Grip Posts Not Creating
1. **Payment Completion**: Ensure order status reaches 'processing' or 'completed'
2. **Product SKU**: Verify the order contains `grip-design-deposit` SKU
3. **Check Logs**: Look for TwinTack Grip Manager entries in error logs

## 🔍 Order Processing Workflow

### For Regular Orders
1. Create order in admin
2. Select **Stripe** or **Manual Payment**
3. Click **Process Payment** or **Mark as Paid**
4. Order moves to processing/completed status
5. Standard e-commerce fulfillment

### For Grip Design Orders
1. Create order with Custom Grip Design Deposit
2. Add grip design metadata (customer name, team, etc.)
3. Select payment method and process payment
4. System automatically creates grip design post
5. Webhook notifications sent to Make.com/Monday.com
6. Grip design workflow begins

### For Wholesale Orders
1. Create order for wholesale customer (pricing automatically adjusted)
2. Use same Stripe payment process as regular customers
3. Mark as paid or process payment via gateway
4. No special payment terms - immediate payment processing
5. Standard fulfillment process

## 📊 Benefits

### For Administrators
- **Streamlined Process**: All payment options in one place
- **Flexibility**: Multiple payment methods for different scenarios
- **Audit Trail**: Complete tracking of payment decisions
- **Integration**: Works with existing systems and workflows

### For Wholesale Management
- **Payment Terms**: Proper Net 30/60 handling
- **Customer Management**: Automatic detection of wholesale customers
- **Order Status**: Clear status indicators for payment terms

### For Customer Service
- **Quick Processing**: Faster manual order creation and payment
- **Error Reduction**: Guided payment method selection
- **Status Clarity**: Clear order status throughout process

## 🚦 Best Practices

### Order Creation
1. **Complete Customer Info**: Always fill out complete customer information
2. **Verify Products**: Ensure all products and quantities are correct
3. **Check Pricing**: Verify pricing is correct for customer type (retail/wholesale)

### Payment Processing
1. **Select Appropriate Method**: Choose payment method that matches customer's actual payment
2. **Add Order Notes**: Add notes explaining payment processing decisions
3. **Verify Completion**: Ensure order reaches proper status after payment

### Wholesale Orders
1. **Verify Customer Type**: Confirm customer has wholesale pricing privileges
2. **Document Terms**: Add order notes with specific payment terms
3. **Follow Up**: Set reminders for payment term deadlines

## 📞 Support

If you encounter issues with the manual order payment system:

1. **Check this guide** for common solutions
2. **Review order notes** for any error messages
3. **Check WordPress error logs** for detailed technical information
4. **Verify all prerequisites** (Stripe config, user roles, etc.)

The system is designed to be robust and handle edge cases, but proper configuration and usage following this guide will ensure smooth operation. 