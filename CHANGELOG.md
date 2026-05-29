# TwinTack Grip Manager - Changelog

> **Current workflow:** See [`plugins/twintack-custom-grips/WORKFLOW.md`](plugins/twintack-custom-grips/WORKFLOW.md). Entries below are historical.

## Version 1.7.05 / Custom Grips 1.2.4 - 2026-05-28

### Native Workflow (Documentation Update)

- Production workflow documented as native WordPress experience via **twintack-custom-grips**
- Removed **Production Ready** (`approved_for_production`) status step; purchase → `in_production`
- Monday.com / Make.com marked retired for grip production workflow

---

## Version 1.5.1 - Customer Feedback Loop System (2024-01-15)

### 🎉 Major New Features

#### Customer Feedback System
- **Interactive Review Interface**: Customers can now review design mockups directly in their dashboard
- **Two-Action Feedback**: Simple "Approve Design" or "Request Changes" buttons with optional comments
- **Real-time AJAX Processing**: Smooth form submission without page reloads
- **Feedback History Tracking**: Complete audit trail of all customer interactions

#### Enhanced Status Management
- **New Customer-Specific Statuses**:
  - `customer_requested_changes` - Customer requested modifications
  - `customer_approved` - Customer approved the design  
  - `approved_for_production` - Final approval for production
- **Intelligent Status Flow**: Automatic progression through approval workflow
- **Visual Status Indicators**: Color-coded status labels with clear messaging

#### WooCommerce Final Purchase Integration
- **Seamless Purchase Flow**: Direct purchase of final product after approval
- **Automatic Cart Population**: Quantity and metadata automatically transferred
- **Purchase Validation**: Prevents purchase until design is customer-approved
- **Order Tracking**: Links final orders to original grip designs

#### Make.com Webhook Integration
- **Customer Feedback Webhooks**: Real-time notifications when customers provide feedback
- **Comprehensive Data Payload**: Includes all relevant design and customer information
- **Flexible Webhook Configuration**: Easy setup via WordPress filters
- **Production Approval Triggers**: Automatic notifications when orders are ready for production

### 🔧 Technical Improvements

#### API Enhancements
- **Customer Feedback Endpoint**: `POST /wp-json/twintack/v1/grip-design/{id}/customer-feedback`
- **Purchase Completion Webhook**: `POST /wp-json/twintack/v1/grip-design/{id}/purchase-complete`
- **Enhanced Security**: Customer ownership verification and permission checks
- **Comprehensive Error Handling**: Detailed error messages and validation

#### Database Schema Updates
- **New Meta Fields**:
  - `_grip_customer_feedback` - Complete feedback history
  - `_grip_latest_customer_feedback` - Most recent feedback
  - `_grip_latest_customer_action` - Last customer action
  - `_grip_final_order_id` - WooCommerce order ID for final purchase
  - `_grip_production_started` - Production approval timestamp

#### Frontend Enhancements
- **Responsive Design**: Mobile-optimized feedback forms and purchase interface
- **Enhanced CSS Styling**: Professional styling for all new components
- **Loading States**: Visual feedback during form submission
- **Success/Error Messaging**: Clear user feedback for all actions

### 🎨 UI/UX Improvements
- **Purchase Section**: Prominent, celebratory design approval interface
- **Feedback Form**: Clean, intuitive form design with clear call-to-action buttons
- **Status Progression**: Visual indicators showing customer where they are in the process
- **Responsive Layout**: Optimized for all device sizes

### 🔒 Security Enhancements
- **Customer Verification**: Email-based ownership verification
- **AJAX Security**: Nonce verification for all AJAX requests
- **Input Sanitization**: Comprehensive sanitization of all user inputs
- **Permission Validation**: Multi-layer permission checking

### 📚 Documentation
- **Customer Feedback System Guide**: Comprehensive documentation for new features
- **API Documentation**: Detailed endpoint specifications and examples
- **Make.com Integration Guide**: Step-by-step webhook setup instructions
- **Troubleshooting Guide**: Common issues and solutions

---

## Version 1.5.0 - Monday.com Integration & Status Separation (2024-01-08)

### 🎉 Major New Features

#### Separated Status Systems
- **Independent Post Status**: WordPress post status now functions normally (published by default for paid orders)
- **Dedicated Artwork Status**: New customer-visible artwork status field with specific workflow states
- **Enhanced Admin Interface**: Clearly separated status sections in admin with detailed explanations

#### Monday.com Integration
- **Asset ID Storage**: Store Monday.com image mockups using asset IDs from Make.com scenarios
- **Direct Asset URLs**: Support for direct Monday.com asset URLs for customer display
- **Design Team Messages**: Display artist messages from Monday.com in customer dashboard
- **Automatic Updates**: Seamless integration with Make.com automation scenarios

#### Customer Dashboard Improvements
- **Priority Mockup Display**: Monday.com mockups display with priority over original artwork
- **Message from Design Team**: Dedicated section for artist communication
- **Enhanced Visual Hierarchy**: Clear primary/secondary artwork display structure
- **Improved Status Communication**: Customer-friendly status labels and descriptions

### 🔧 Technical Improvements

#### REST API for Make.com
- **New Endpoint**: `POST /wp-json/twintack/v1/grip-design/{id}/monday`
- **API Key Authentication**: Secure authentication with configurable API key (default: twintack-monday-2024)
- **Comprehensive Parameters**:
  - `mockup_asset_id` - Monday.com asset ID
  - `mockup_asset_url` - Direct asset URL
  - `wordpress_media_id` - WordPress media ID for featured image
  - `monday_feedback` - Design team message
  - `artwork_status` - Updated artwork status

#### Enhanced Meta Fields
- **New Monday.com Fields**:
  - `_grip_mockup_asset_id` - Monday.com asset ID
  - `_grip_mockup_asset_url` - Direct asset URL for display
  - `_grip_monday_feedback` - Design team messages
- **Backward Compatibility**: Maintains support for existing legacy fields

#### Admin Interface Enhancements
- **Dedicated Monday.com Meta Box**: Organized interface for Monday.com integration fields
- **Status Guide**: Helpful explanations for each artwork status
- **Visual Separation**: Clear distinction between post status and artwork status
- **Integration Notes**: Helpful information about Make.com automation

### 🎨 CSS & Styling Improvements
- **Status Label Styling**: Color-coded status labels for all artwork statuses
- **Monday.com Feedback Section**: Dedicated styling for design team messages
- **Secondary Button Styling**: Subtle styling for original artwork links
- **Responsive Design**: Improved mobile experience for customer dashboard

### 🔒 Security & Validation
- **API Key Authentication**: Secure endpoint access for Make.com integration
- **Input Validation**: Comprehensive validation for all API parameters
- **Sanitization**: Proper sanitization of all user inputs and API data
- **Permission Checks**: Appropriate permission validation for admin functions

### 📚 Documentation & Support
- **API Documentation**: Complete API reference with examples (MONDAY-API.md)
- **Integration Guide**: Step-by-step Make.com setup instructions
- **Troubleshooting**: Common issues and solutions
- **Changelog**: Detailed feature documentation

---

## Version 1.4.21 - Foundation Release

### Initial Features
- Custom post type for grip designs
- Gravity Forms integration
- Basic customer dashboard
- WooCommerce integration
- File upload handling
- Admin management interface

---

## Upgrade Notes

### From 1.5.0 to 1.5.1
- **New Database Fields**: Automatic migration of customer feedback fields
- **Enhanced Statuses**: New customer-specific statuses added to existing workflow
- **WooCommerce Integration**: Requires Custom Grip Product (ID: 1196) to be configured
- **Webhook Configuration**: Optional webhook URL setup for Make.com integration

### From 1.4.21 to 1.5.0
- **Status Migration**: Existing grip designs automatically receive artwork status field
- **Backward Compatibility**: All existing functionality preserved
- **New API Endpoints**: Monday.com integration endpoints available immediately
- **Enhanced Display**: Customer dashboard automatically shows Monday.com mockups when available

---

## Support & Development

For technical support, feature requests, or custom development needs, contact the TwinTack development team.

**Plugin Version**: 1.5.1  
**WordPress Compatibility**: 5.8+  
**PHP Compatibility**: 7.4+  
**Dependencies**: WooCommerce, Gravity Forms 