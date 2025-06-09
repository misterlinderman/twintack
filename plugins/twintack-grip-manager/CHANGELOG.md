# TwinTack Grip Manager Changelog

## Version 1.5.0 - 2024-12-19

### 🎉 Major Features Added

#### Separated Post Status from Artwork Status
- **Post Status** now functions normally (published by default for paid orders)
- **Artwork Status** is a separate field visible to customers in dashboard
- New artwork statuses: `artwork_pending`, `pending_review`, `artwork_approved`, `internal_review`, `in_production`, `shipped`
- Admin interface clearly separates the two status types

#### Monday.com Integration Enhancement
- Added `_grip_mockup_asset_id` field for storing Monday.com asset IDs
- Added `_grip_mockup_asset_url` field for direct asset URLs
- Enhanced admin interface with dedicated Monday.com fields
- Renamed "Design Team Feedback" to "Design Team Message" for clarity

#### Customer Dashboard Improvements
- Mockups from Monday.com now take priority over original artwork in display
- Added "Message from Design Team" section to show Monday.com feedback
- Improved artwork display with primary/secondary layout
- Enhanced status display with new artwork status labels
- Better visual hierarchy between original artwork and design mockups

#### REST API for Make.com Integration
- New endpoint: `POST /wp-json/twintack/v1/grip-design/{id}/monday`
- Secure API key authentication
- Allows updating Monday.com fields and artwork status
- Complete documentation in `MONDAY-API.md`

### 🎨 UI/UX Improvements
- Enhanced CSS styling for status labels with color coding
- Improved admin meta box layout and organization
- Better visual distinction between different content types
- Added secondary button styling for original artwork links
- Improved responsive design for mobile devices

### 🔧 Technical Improvements
- Updated REST API meta field registration
- Enhanced error handling and logging
- Improved admin notices for status changes
- Better field validation and sanitization
- Backward compatibility maintained for existing data

### 📝 Documentation
- Added comprehensive API documentation (`MONDAY-API.md`)
- Updated plugin description
- Added inline code comments for better maintainability

### 🔄 Migration Notes
- Existing grip designs will automatically get `artwork_pending` status
- All existing functionality remains unchanged
- Post status continues to work as before
- No data loss or breaking changes

---

## Previous Versions

### Version 1.4.21
- Purchase-based grip design creation
- Enhanced form support for color specifications
- Order linking and audit trail improvements

### Version 1.3.x
- Initial grip design post type
- Gravity Forms integration
- WooCommerce order integration
- Basic customer dashboard 

## [1.5.19] - 2024-06-08
### Fixed
- **Customer Feedback REST API Exposure**: Added missing customer feedback fields to REST API registration
  - `_grip_customer_feedback` - Complete feedback history array
  - `_grip_latest_customer_feedback` - Most recent feedback text
  - `_grip_latest_customer_action` - Most recent action (approve/request_changes)
  - `_grip_final_order_id` - WooCommerce order ID after purchase
  - `_grip_production_started` - Production start timestamp

### Added
- **Enhanced REST API Fields for Make.com**: Added clean field names for easier automation access
  - `customer_feedback_history` - Clean access to feedback array
  - `latest_customer_feedback` - Most recent feedback without underscore prefix
  - `latest_customer_action` - Most recent action with validation
  - `final_order_id` - Order tracking field
  - `production_started` - Production timestamp field

### Enhanced
- **Make.com Integration**: All customer feedback data now available through WordPress REST API
- **API Completeness**: Full grip design lifecycle data accessible for external automation

## [1.5.18] - 2024-06-08
### Fixed
- **Monday.com Item ID REST API Exposure**: Added missing `_grip_monday_item_id` field to REST API registration
  - Field now properly appears in WordPress REST API responses
  - Make.com scenarios can now access Monday.com item ID for column updates
  - Added both underscore prefixed field (`_grip_monday_item_id`) and clean field (`monday_item_id`) for flexibility

### Enhanced
- **Make.com Integration**: Monday.com Item ID now available in REST API for Monday.com column value updates
- **API Accessibility**: Improved field accessibility for external automation tools

## [1.5.17] - 2024-06-08
### Removed
- **Legacy Mockup Fields**: Removed obsolete "Legacy Mockup URL" and "Mockup Filename" fields from admin interface
  - These fields are no longer needed with the new Monday.com asset management system
  - Cleaner admin interface focused on current workflow
  - Simplified data management without backward compatibility burden

### Enhanced
- **Admin Interface**: Streamlined Monday.com Integration meta box with only active fields
- **Data Management**: Reduced unnecessary field storage and processing

## [1.5.16] - 2024-06-08
### Added
- **Customer Feedback Meta Box in WordPress Admin**
  - Visual display of latest customer action (Approved/Requested Changes) with color coding
  - Complete feedback history with chronological timeline
  - Scrollable container for extensive feedback histories
  - Customer information display with name and email
  - Timestamps for all feedback entries
  
- **Monday.com Item ID Field**
  - New field in Monday.com Integration meta box for storing Monday.com item reference
  - API endpoint support for updating Monday.com item ID via Make.com
  - Webhook integration includes Monday.com item ID for automated updates
  - Enables bidirectional synchronization between WordPress and Monday.com

### Enhanced
- **Structured Feedback Storage**: Customer feedback now stored as structured arrays instead of strings
- **API Webhook Data**: Monday.com item ID included in all customer feedback webhook payloads
- **Admin Interface**: Improved visual hierarchy and information display in meta boxes

### Technical
- Enhanced Monday.com API endpoint with `monday_item_id` parameter
- Improved feedback data structure for better admin display and API integration
- Updated webhook payload structure for enhanced Make.com automation

## [1.5.15] - 2024-06-08
### Fixed
- **Core AJAX Functionality Issue**: Fixed critical bug where customer feedback buttons weren't working
- **Class Instantiation**: Moved `TwinTack_Grip_Account::get_instance()` outside `!is_admin()` condition
- **WordPress AJAX Compatibility**: Resolved issue where AJAX handlers weren't registered for admin requests
- **Script Loading**: Refined JavaScript loading to prevent conflicts with other admin pages

### Technical Details
- WordPress AJAX calls run through `wp-admin/admin-ajax.php` which is considered an admin request
- The Account class was only being instantiated for non-admin requests, causing AJAX handlers to be missing
- This affected the customer feedback system's core functionality

## [1.5.14] - 2024-06-08
### Added
- **Customer Feedback Loop System**
  - Interactive customer interface for reviewing design mockups
  - Two-action system: Approve Design or Request Changes
  - Real-time AJAX processing with loading states
  - Feedback history tracking with timestamps
  - Status-based workflow management

- **Enhanced Status Management**
  - New customer-specific statuses: `customer_requested_changes`, `customer_approved`, `approved_for_production`
  - Separated customer and artist feedback workflows
  - Visual status indicators in admin interface

- **WooCommerce Purchase Integration**
  - Automatic final product purchase flow after customer approval
  - Custom Grip Product ID: 1196 integration
  - Purchase completion webhook for production approval
  - Order validation and metadata tracking

- **Make.com/Monday.com Webhook System**
  - Customer feedback webhooks with comprehensive data payload
  - Configurable webhook URLs via WordPress filters
  - Production approval triggers for Monday.com automation
  - Structured webhook data for advanced automation scenarios

- **API Endpoints**
  - `POST /wp-json/twintack/v1/grip-design/{id}/customer-feedback` - Customer feedback submission
  - `POST /wp-json/twintack/v1/grip-design/{id}/purchase-complete` - Purchase completion webhook
  - Enhanced security with customer ownership verification

### Enhanced
- **Customer Dashboard**: Added customer feedback interface to grip design display
- **Database Schema**: New meta fields for feedback tracking and order management
- **Admin Interface**: Enhanced status display with customer vs artist feedback separation

### Technical
- Enhanced AJAX handling for customer interactions
- Comprehensive error handling and validation
- Real-time status updates and webhook triggers
- Structured feedback data storage for scalability

## [1.5.13] - 2024-06-07
### Added
- Enhanced status management with customer feedback integration
- Improved Monday.com webhook data structure
- Customer feedback tracking and history

### Enhanced  
- Monday.com API integration with better error handling
- Status workflow improvements for customer feedback loop

## [1.5.12] - 2024-06-06
### Fixed
- Resolved WordPress login redirect issues
- Improved customer authentication flow
- Enhanced account dashboard functionality

### Enhanced
- Better error handling for customer access
- Improved user experience for grip design access

## [1.5.11] - 2024-06-05
### Added
- Customer account dashboard with grip design access
- Enhanced security for customer-specific content
- Improved grip design display templates

### Enhanced
- Customer authentication and authorization
- Account management functionality
- Better integration with WordPress user system

## [1.5.10] - 2024-06-04
### Added
- Customer account integration for personalized access
- Enhanced security for grip design viewing
- Improved customer experience with dedicated dashboard

### Enhanced
- User authentication workflow
- Customer data management
- Better integration with WooCommerce customer accounts

## [1.5.9] - 2024-06-03
### Added
- Enhanced Monday.com integration with asset management
- Improved mockup display functionality
- Better file handling for design assets

### Enhanced
- Asset URL management for mockups
- Integration with Monday.com file system
- Improved visual display of design mockups

## [1.5.8] - 2024-06-02
### Added
- Monday.com API integration for automated updates
- Enhanced mockup asset management
- Improved design team workflow

### Enhanced
- Automated status updates from Monday.com
- Better asset handling and display
- Streamlined design approval process

## [1.5.7] - 2024-06-01
### Added
- Enhanced customer dashboard with improved design display
- Better mockup presentation for customer review
- Improved customer experience interface

### Enhanced
- Visual design improvements for customer interface
- Better responsive design for mobile access
- Enhanced customer feedback collection

## [1.5.6] - 2024-05-31
### Added
- Customer dashboard functionality for grip design access
- Enhanced template system for design display
- Improved customer experience interface

### Enhanced
- Better design presentation for customers
- Enhanced navigation and user experience
- Improved responsive design for all devices

## [1.5.5] - 2024-05-30
### Fixed
- Resolved issues with grip design display templates
- Fixed customer access permissions
- Improved template loading mechanism

### Enhanced
- Better error handling for template issues
- Improved customer authentication flow
- Enhanced template system reliability

## [1.5.4] - 2024-05-29
### Added
- Enhanced grip design post type with custom templates
- Improved customer viewing experience
- Better integration with WordPress theming system

### Enhanced
- Custom post type template handling
- Better design display for customers
- Improved responsive design implementation

## [1.5.3] - 2024-05-28
### Added
- Enhanced meta box functionality for grip designs
- Improved admin interface for design management
- Better data organization and display

### Enhanced
- Admin user experience improvements
- Better data validation and sanitization
- Enhanced meta field management

## [1.5.2] - 2024-05-27
### Added
- Comprehensive meta box system for grip design management
- Enhanced admin interface with organized data display
- Improved design team workflow tools

### Enhanced
- Better organization of grip design data
- Enhanced admin user interface
- Improved data management capabilities

## [1.5.1] - 2024-05-26
### Added
- Enhanced artwork status management system
- Separate tracking for artwork vs post status
- Improved workflow for design team management

### Enhanced
- Better status workflow management
- Enhanced artwork tracking capabilities
- Improved team collaboration features

## [1.5.0] - 2024-05-25
### Added
- Core grip design management functionality
- Integration with Gravity Forms for order processing
- Basic WooCommerce integration
- Initial Monday.com webhook support

### Enhanced
- Complete redesign of grip management system
- Better integration with WordPress ecosystem
- Enhanced data management capabilities

---

**Legend:**
- 🎯 **Added**: New features and functionality
- ⚡ **Enhanced**: Improvements to existing features  
- 🔧 **Fixed**: Bug fixes and issue resolution
- 📋 **Technical**: Backend improvements and technical changes 