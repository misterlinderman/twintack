# TwinTack Grip Manager - Customer Feedback System

## Overview
The Customer Feedback Loop system allows customers to review design mockups and either approve them for purchase or request changes, with full integration to Make.com/Monday.com automation.

## System Components

### 1. Customer Interface
- **Review Form**: Interactive feedback form with textarea and two action buttons
- **Real-time Processing**: AJAX submission with loading states and instant feedback
- **Purchase Integration**: Direct "Buy Now" button appears after approval
- **Feedback History**: Displays previous feedback submissions with timestamps

### 2. Admin Interface (WordPress Backend)

#### Customer Feedback Meta Box
- **Latest Customer Action**: Displays most recent approval/change request with visual indicators
- **Complete Feedback History**: Chronological list of all customer interactions
  - Action type (Approved/Requested Changes) with color coding
  - Timestamp for each feedback entry
  - Full feedback text display
- **Customer Information**: Shows customer name and email for context
- **Scrollable History**: For designs with extensive feedback, scrollable container prevents UI overload

#### Monday.com Integration Fields
- **Monday.com Item ID**: New field to store Monday.com item reference
  - Used for updating Monday.com items with customer feedback
  - Enables bidirectional synchronization between WordPress and Monday.com
  - Accessible via API for Make.com scenarios
- **Design Team Message**: Messages from Monday.com via Make.com automation
- **Mockup Asset Management**: Direct links to Monday.com assets

### 3. API Endpoints

#### Customer Feedback API
```
POST /wp-json/twintack/v1/grip-design/{id}/customer-feedback
```

#### Monday.com Integration API (Enhanced)
```
POST /wp-json/twintack/v1/grip-design/{id}/monday
```
**New Parameters:**
- `monday_item_id`: Monday.com item ID for reference tracking

### 4. Status Management

#### Customer-Specific Statuses
- `pending_review` → Customer can review mockup
- `customer_approved` → Customer approved the design
- `customer_requested_changes` → Customer wants modifications
- `approved_for_production` → Final approval after purchase

### 5. Make.com/Monday.com Integration

#### Webhook Data (Enhanced)
```json
{
  "grip_design_id": 123,
  "grip_design_title": "Custom Grip - Team Name",
  "customer_action": "request_changes|approve",
  "customer_feedback": "Customer feedback text",
  "artwork_status": "customer_requested_changes|customer_approved",
  "customer_name": "John Doe",
  "customer_email": "john@example.com",
  "team_name": "Fighting Grimaces",
  "quantity": "200",
  "monday_item_id": "1234567890",
  "timestamp": "2024-06-08T14:30:00+00:00",
  "webhook_type": "customer_feedback"
}
```

**Key Enhancement**: `monday_item_id` field enables Make.com scenarios to:
- Update specific Monday.com items with customer feedback
- Create follow-up tasks for change requests
- Track approval status across both platforms
- Maintain data consistency between systems

### 6. Database Schema

#### Meta Fields
- `_grip_customer_feedback` - Array of feedback entries with structure:
  ```php
  array(
      array(
          'action' => 'approve|request_changes',
          'feedback' => 'Customer feedback text',
          'timestamp' => '2024-06-08 14:30:00'
      )
  )
  ```
- `_grip_latest_customer_feedback` - Most recent feedback text
- `_grip_latest_customer_action` - Most recent action (approve/request_changes)
- `_grip_monday_item_id` - Monday.com item ID for reference
- `_grip_final_order_id` - WooCommerce order ID after purchase
- `_grip_production_started` - Production approval timestamp

## Workflow Examples

### Approval Workflow
1. Design team uploads mockup → Status: `pending_review`
2. Customer clicks "Approve Design" → Status: `customer_approved`
3. Purchase button appears → Customer completes purchase
4. Purchase webhook triggered → Status: `approved_for_production`
5. Make.com receives webhook with `monday_item_id` → Updates Monday.com item

### Change Request Workflow
1. Design team uploads mockup → Status: `pending_review`
2. Customer clicks "Request Changes" with feedback → Status: `customer_requested_changes`
3. Make.com webhook includes `monday_item_id` and feedback
4. Monday.com item updated with customer feedback and new task created
5. Design team makes changes → Process repeats until approval

## Admin Benefits

### For Project Managers
- **Centralized Feedback View**: All customer interactions visible in WordPress admin
- **Status at a Glance**: Color-coded status indicators show current state
- **Customer Context**: Immediate access to customer details and history
- **Monday.com Sync**: Item ID tracking ensures data consistency

### For Design Team
- **Historical Context**: Complete feedback history for design decisions
- **Clear Instructions**: Customer feedback displayed with timestamps
- **Status Tracking**: Visual indicators of approval/change request states
- **Production Ready**: Clear transition from approval to production status

## Technical Implementation

The system uses structured data storage for scalability and maintains backward compatibility while providing enhanced admin interfaces. The Monday.com item ID integration enables sophisticated automation scenarios through Make.com while providing clear administrative oversight.

All customer actions trigger both local status updates and external webhook notifications, ensuring all systems stay synchronized throughout the feedback and approval process. 