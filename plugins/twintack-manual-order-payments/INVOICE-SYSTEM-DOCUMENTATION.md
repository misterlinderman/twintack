# TwinTack Manual Order Invoice System

## Overview

The TwinTack Manual Order Payments plugin has been enhanced with a comprehensive invoice system that allows admins to offer "Pay Now" or "Pay Later" options for manually created orders, with full Shippo fulfillment integration.

## Key Features

### 1. Pay Now vs Pay Later Options
- **Pay Now**: Immediately marks order as paid and sets to Processing status
- **Pay Later**: Creates an invoice and sends payment link to customer via email

### 2. Custom Order Status - "Invoiced"
- New order status specifically for Pay Later orders
- Automatically triggers proper Shippo status mapping
- Allows customers to complete payment when ready

### 3. Shippo Fulfillment Status Mapping
The system automatically maps WooCommerce order statuses to Shippo fulfillment statuses:

- **Processing** → **Paid** (ready for fulfillment)
- **Invoiced** → **Payment Pending** (awaiting customer payment)
- **Completed** → **Shipped** (order fulfilled and shipped)

### 4. Customer Payment Restrictions
- For invoiced orders, only Stripe payment is available to customers
- No checks or other payment methods for wholesale customers
- Secure credit card processing only

## Admin Interface

### Enhanced Order Edit Page
When editing a manual order, admins now see:

1. **Current Status Display**: Shows both WooCommerce and Shippo status
2. **Primary Actions**:
   - ✅ **Pay Now** (Mark as Paid) - Green button
   - 📄 **Pay Later** (Invoice) - Orange button
3. **Advanced Actions**:
   - Send Stripe Payment Link
   - Set Payment Method Only
4. **Status Explanations**: Clear mapping of statuses to Shippo fulfillment

### New AJAX Handlers
- `twintack_set_pay_later`: Handles Pay Later functionality
- Enhanced error handling and status reporting
- Real-time feedback to admin users

## Customer Experience

### Invoice Email
When "Pay Later" is selected, customers receive a professional email with:
- Order details and total amount
- Secure Stripe payment link
- Clear instructions for payment
- 24-hour payment link expiration

### Payment Process
1. Customer receives invoice email with payment link
2. Clicks link to access secure Stripe checkout
3. Only credit/debit card payment available (no other methods)
4. Order automatically updates to Processing upon payment completion
5. Fulfillment process begins automatically

## Technical Implementation

### New Classes Added

#### `TwinTack_Invoice_Payment_Gateway`
- Custom WooCommerce payment gateway for invoice functionality
- Admin-only visibility
- Integrates with order status system

#### `TwinTack_Order_Status_Manager`
- Manages custom order statuses
- Handles Shippo status mapping
- Provides workflow management functions

### Database Changes
- New order status: `wc-invoiced`
- Order meta fields:
  - `_twintack_needs_payment_link`: Flags orders needing payment links
  - `_shippo_fulfillment_status`: Stores current Shippo status
  - `_stripe_checkout_session_id`: Links orders to Stripe sessions

### File Structure
```
plugins/twintack-manual-order-payments/
├── includes/
│   ├── class-admin-order-enhancements.php (updated)
│   ├── class-invoice-payment-gateway.php (new)
│   ├── class-order-status-manager.php (new)
│   └── class-payment-link-email.php (existing)
├── assets/js/
│   └── admin-order-payments.js (updated)
└── twintack-manual-order-payments.php (updated)
```

## Workflow Examples

### Pay Now Workflow
1. Admin creates manual order
2. Clicks "Pay Now" button
3. Order status → Processing
4. Shippo status → Paid
5. Order ready for fulfillment
6. Grip creation triggered (if applicable)

### Pay Later Workflow
1. Admin creates manual order
2. Clicks "Pay Later" button
3. Order status → Invoiced
4. Shippo status → Payment Pending
5. Invoice email sent to customer
6. Customer pays via Stripe link
7. Order status → Processing
8. Shippo status → Paid
9. Fulfillment begins

### Shipping Completion
1. Order fulfilled and shipped
2. Admin marks as Complete
3. Shippo status → Shipped
4. Customer notified of shipment

## Security Features

- CSRF protection with WordPress nonces
- Permission checks for admin functions
- Secure Stripe payment processing
- Input sanitization and validation
- Error logging for debugging

## Configuration

### Required Settings
1. WooCommerce must be active
2. Stripe payment gateway must be configured
3. Customer email addresses required for invoicing
4. TwinTack Manual Order Payments plugin enabled

### Optional Integrations
- Shippo fulfillment system
- TwinTack Grip Manager (for grip creation)
- Make.com webhooks
- Monday.com project management

## Troubleshooting

### Common Issues
1. **No Stripe available**: Ensure Stripe gateway is properly configured
2. **Email not sending**: Check WordPress mail configuration
3. **Status not updating**: Verify order status manager is loaded
4. **Payment link expired**: Links expire after 24 hours - create new one

### Debug Logging
Enable debug mode in plugin settings to see detailed logs:
- Order status changes
- Payment link creation
- Shippo status updates
- Email sending attempts

## API Hooks

### Actions
- `twintack_shippo_status_updated`: Fired when Shippo status changes
- `twintack_manual_payments_loaded`: Plugin initialization complete

### Filters
- `woocommerce_available_payment_gateways`: Limited for invoiced orders
- `woocommerce_valid_order_statuses_for_payment`: Includes 'invoiced' status

## Future Enhancements

### Potential Additions
1. Bulk invoice processing
2. Custom invoice templates
3. Payment reminders
4. Multi-currency support
5. Partial payment options

### Integration Opportunities
1. QuickBooks invoice sync
2. Automated fulfillment triggers
3. Customer portal dashboard
4. Advanced reporting

## Version History

### v1.4.0 (Current)
- Added Pay Now/Pay Later functionality
- Implemented custom Invoiced status
- Added Shippo status mapping
- Enhanced admin interface
- Restricted customer payment options to Stripe only

### Previous Versions
- v1.3.1: Basic Stripe checkout sessions
- v1.2.0: Payment link email functionality
- v1.1.0: Manual order payment processing
- v1.0.0: Initial release

---

For technical support or feature requests, contact the TwinTack development team. 