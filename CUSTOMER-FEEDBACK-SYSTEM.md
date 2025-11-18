# TwinTack Grip Manager - Customer Feedback System

## Overview

Version 1.5.1 introduces a comprehensive customer feedback loop system that allows customers to review design mockups and provide approval or change requests directly through their account dashboard.

## Features

### 1. Customer Review Interface
- **Feedback Form**: Text area for customer comments
- **Two Action Buttons**: 
  - ✓ **Approve Design**: Approves the mockup for production
  - ↻ **Request Changes**: Requests modifications with feedback

### 2. Status Management
The system uses enhanced artwork statuses to track the feedback loop:

- `artwork_pending` - Initial status when design is submitted
- `pending_review` - Design mockup ready for customer review
- `customer_requested_changes` - Customer requested modifications
- `customer_approved` - Customer approved the design
- `approved_for_production` - Final approval for production
- `in_production` - Currently being manufactured
- `shipped` - Order completed and shipped

### 3. Automated Workflow

#### Customer Approval Flow:
1. Design team uploads mockup → Status: `pending_review`
2. Customer sees feedback form in dashboard
3. Customer clicks "Approve" → Status: `customer_approved`
4. Purchase option appears for final product
5. Customer completes purchase → Status: `approved_for_production`

#### Customer Change Request Flow:
1. Design team uploads mockup → Status: `pending_review`
2. Customer provides feedback and clicks "Request Changes" → Status: `customer_requested_changes`
3. Make.com webhook triggered with customer feedback
4. Design team makes changes and uploads new mockup → Status: `pending_review`
5. Process repeats until customer approves

## API Endpoints

### Customer Feedback API
**Endpoint**: `POST /wp-json/twintack/v1/grip-design/{id}/customer-feedback`

**Authentication**: Customer must be logged in and own the design

**Parameters**:
- `action` (required): `approve` or `request_changes`
- `feedback` (optional): Customer feedback text

**Response**:
```json
{
  "success": true,
  "action": "approve",
  "new_status": "customer_approved",
  "message": "Design approved! You can now purchase your custom grips.",
  "redirect_url": "https://yoursite.com/grip-designs/1234"
}
```

### Purchase Completion Webhook
**Endpoint**: `POST /wp-json/twintack/v1/grip-design/{id}/purchase-complete`

**Authentication**: API key required (same as Monday.com API)

**Parameters**:
- `order_id` (required): WooCommerce order ID
- `payment_status` (required): `completed`

## Make.com Integration

### Customer Feedback Webhook
When customers provide feedback, a webhook is triggered with the following data:

```json
{
  "grip_design_id": 1234,
  "grip_design_title": "Custom Grip - Team Name",
  "customer_action": "request_changes",
  "customer_feedback": "Please make the text larger",
  "artwork_status": "customer_requested_changes",
  "customer_name": "John Doe",
  "customer_email": "john@example.com",
  "team_name": "Eagles Basketball",
  "quantity": 25,
  "timestamp": "2024-01-15T10:30:00Z",
  "webhook_type": "customer_feedback"
}
```

### Setting Up Webhook URL
Add this to your theme's `functions.php` or a custom plugin:

```php
add_filter('grip_customer_feedback_webhook_url', function() {
    return 'https://hook.integromat.com/your-webhook-url';
});
```

### Make.com Scenario Structure

1. **Webhook Module**: Receives customer feedback
2. **Router**: 
   - Path A: `customer_action = "approve"` → Update Monday.com status to approved
   - Path B: `customer_action = "request_changes"` → Update Monday.com with feedback and change status
3. **Monday.com Update**: Update item status and add customer feedback to notes

## WooCommerce Integration

### Final Product Purchase
- Product ID: 1196 (Custom Grip Product)
- Automatic quantity matching from grip design
- Cart meta data includes grip design details
- Order completion triggers production approval

### Cart Integration
When customer clicks "Purchase Custom Grips":
- Adds product to cart with grip design metadata
- Quantity automatically set from original order
- Price: $19.99 per grip
- Prevents purchase if design not approved

## Database Schema

### New Meta Fields
- `_grip_customer_feedback` - Complete feedback history
- `_grip_latest_customer_feedback` - Most recent feedback
- `_grip_latest_customer_action` - Last action (approve/request_changes)
- `_grip_final_order_id` - WooCommerce order ID for final purchase
- `_grip_production_started` - Timestamp when production approved

## Frontend Implementation

### Customer Dashboard Display
The feedback form only appears when status is `pending_review`:

```php
<?php if ($artwork_status === 'pending_review'): ?>
    <div class="customer-feedback-section">
        <h3>Review Your Design</h3>
        <form id="customer-feedback-form">
            <textarea id="customer-feedback" placeholder="Comments..."></textarea>
            <div class="feedback-buttons">
                <button id="approve-design">✓ Approve Design</button>
                <button id="request-changes">↻ Request Changes</button>
            </div>
        </form>
    </div>
<?php endif; ?>
```

### AJAX Implementation
- Real-time form submission without page reload
- Loading states on buttons
- Success/error message display
- Automatic page refresh after successful submission

## CSS Styling

### Key Classes
- `.customer-feedback-section` - Main feedback container
- `.feedback-btn` - Button styling
- `.approve-btn` - Green approval button
- `.changes-btn` - Yellow changes button
- `.purchase-section` - Final purchase area
- `.status-customer_approved` - Approved status styling

## Security Features

### Permission Checks
- Customer must be logged in
- Email verification against grip design owner
- Status validation (only `pending_review` allows feedback)
- Nonce verification for AJAX requests

### Data Sanitization
- All feedback text sanitized with `sanitize_textarea_field()`
- Action parameters validated against allowed values
- SQL injection protection through WordPress meta functions

## Troubleshooting

### Common Issues

1. **Feedback form not appearing**
   - Check artwork status is `pending_review`
   - Verify customer is logged in with correct email

2. **JavaScript errors**
   - Ensure jQuery is loaded
   - Check grip ID is properly set in data attribute

3. **Webhook not triggering**
   - Verify webhook URL is set via filter
   - Check Make.com webhook URL is accessible

4. **Purchase button not working**
   - Confirm status is `customer_approved`
   - Verify Custom Grip Product (ID: 1196) exists

### Debug Mode
Enable WordPress debug logging to see webhook triggers:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Version History

### 1.5.1 - Customer Feedback System
- Added customer feedback form and AJAX handling
- Implemented approval/change request workflow
- Enhanced status management with customer-specific statuses
- Added WooCommerce integration for final purchase
- Created webhook system for Make.com integration
- Added comprehensive CSS styling and responsive design

## Support

For technical support or customization requests, contact the TwinTack development team. 