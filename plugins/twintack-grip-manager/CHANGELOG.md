# TwinTack Grip Manager Changelog

## Version 1.7.01 - 2025-10-18

### 🧠 Intelligent Import - Smart Partial Data Handling

#### **Problem Identified**
User feedback: *"Obviously there is missing information, otherwise I wouldn't need to be using the tool to import the submission."*

The v1.7.00 importer was too strict - it would completely fail if ANY required field was missing, blocking the entire import. This defeated the purpose of having a manual creation tool.

#### **Solution Implemented**
Smart partial import that:
- ✅ **Always imports available data** - No more blocking errors
- ✅ **Identifies missing fields** - Lists exactly what needs manual entry
- ✅ **Visual highlighting** - Yellow background on fields needing attention
- ✅ **Helpful guidance** - Specific instructions for each missing field
- ✅ **Intelligent save behavior** - Only suggests auto-save when complete

#### **New Behavior**
```
Before: ❌ Error: Entry is missing team/school name [BLOCKED]

After:  ✓ Imported 8 fields from entry #849
        
        Missing 2 field(s):
        ⚠️ Team/School Name is REQUIRED - please add manually
        ⚠️ No artwork file uploaded in form - add via upload or URL
        
        → Please fill the highlighted fields above and save.
```

#### **Visual Improvements**
- **Warning Color**: Orange border/yellow background for partial imports
- **Success Color**: Green border/blue background for complete imports  
- **Field Highlighting**: Yellow background on empty required fields
- **Smart Messaging**: Shows count of imported vs missing fields

#### **User Benefits**
1. **Saves Time**: Import 11 fields, manually fill 1 (vs typing all 12)
2. **Reduces Errors**: Only manual fields can have typos
3. **Clear Guidance**: Specific list of what's missing
4. **Works Flexibly**: Handles any combination of present/missing data

#### **Technical Changes**
- Changed from hard validation failure to soft warning system
- Added `missing_fields` array tracking
- Enhanced response with `has_warnings` flag
- JavaScript now highlights empty fields and adjusts colors based on completeness
- Conditional save prompt based on data completeness

#### **Documentation**
- **New Guide**: `INTELLIGENT-IMPORT.md` - Complete explanation with examples

---

## Version 1.7.00 - 2025-10-18

### 🎨 Enhanced Manual Grip Design Creation

#### **New: Gravity Forms Entry Importer**
- **One-Click Import**: Import complete grip design data directly from Gravity Forms entries
- **Auto-Population**: Automatically fills all customer info, design specs, artwork, and color details
- **Smart Title Generation**: Creates proper grip design post title from team name
- **Form Type Detection**: Automatically identifies "new" vs "original" form submissions
- **Live Feedback**: Instant success/error messages with imported data preview
- **Entry Linking**: Maintains connection to original form submission

#### **New: Artwork Management**
- **Three Upload Methods**:
  1. Direct file upload via WordPress Media Library
  2. Paste artwork URL from Gravity Forms or Media Library
  3. Manual URL entry
- **Live Preview**: Artwork displays immediately after URL entry or upload
- **Auto-Filename Extract**: Automatically extracts filename from URL if not provided
- **Validation**: Ensures URLs are properly formatted and accessible
- **Media Library Integration**: Full WordPress media uploader support

#### **New: Complete Field Management**
- **All Meta Fields Available**: Every field that automatic creation uses
- **Order Linking**: Add Order ID and Order Item ID for tracking
- **Form Entry Reference**: Link to original Gravity Forms submission
- **Form Type Selector**: Choose between "new" form or "original" form
- **System Metadata**: View creation method, timestamps, and audit trail
- **Enhanced Field Organization**: Grouped by purpose (customer, design, system)

#### **New: System Information Sidebar**
- **Gravity Forms Integration**: Entry ID with direct link to form submission
- **WooCommerce Integration**: Order ID with direct link to order
- **Audit Trail**: Shows if created via fallback tool or manually
- **Timestamp Display**: Form submission date/time
- **Quick Links**: One-click access to related records

#### **Enhanced Admin Interface**
- **Better Meta Box Organization**: Logical grouping of related fields
- **Improved Save Handling**: Multiple nonce support, enhanced validation
- **Smart Sanitization**: Field-specific sanitization (email, URL, numbers, text)
- **Media Enqueuing**: WordPress media scripts loaded only when needed
- **AJAX Integration**: Real-time import without page refresh

#### **Developer Features**
- **AJAX Handler**: `grip_import_gf_entry` endpoint for form data import
- **Security**: Nonce verification, capability checks, error handling
- **Error Messages**: Descriptive feedback for troubleshooting
- **Field Mapping**: Complete documentation of Gravity Forms field IDs
- **Extensible**: Filter and action hooks for customization

#### **Problem Solved**
- **Manual Creation Parity**: Manually created posts now have identical data to automatic posts
- **Missing Field Issue**: No more incomplete grip designs due to missing admin fields
- **Artwork Upload Gap**: Can now add artwork to manually created posts
- **Recovery Tool**: Easy way to create grip posts for failed automatic creation
- **Audit Trail**: Track which posts were created manually vs automatically

#### **Use Cases**
1. **Failed Automatic Creation**: Quickly recover by importing from form entry
2. **Missing Team Name**: Add required field and create grip post manually
3. **Order Status Issues**: Create grip post regardless of order status
4. **Bulk Processing**: Handle multiple failed orders systematically
5. **Custom Workflows**: Create grip posts outside normal purchase flow

#### **Documentation**
- **New Guide**: `MANUAL-GRIP-CREATION-GUIDE.md` - Complete manual creation documentation
- **Step-by-Step Workflows**: Four common scenarios with timing estimates
- **Troubleshooting**: Solutions for common errors and issues
- **Best Practices**: Tips for efficient manual creation

#### **Technical Details**
- **AJAX Endpoint**: Secure, permission-checked form import
- **Field Validation**: Ensures required fields (customer name, team name) present
- **Auto-Title Generation**: Same format as automatic creation (`Custom Grip - [Team] YYMMDD`)
- **Color Support**: Handles both simple and multi-color design types
- **WordPress Standards**: Uses core WordPress functions and security practices

#### **Breaking Changes**
- None - fully backward compatible

#### **Migration Notes**
- No migration needed
- Existing posts unchanged
- New features available immediately

---

## Version 1.6.06 - 2024-12-19

### 🔄 Make.com Revision Detection Fix

#### **Enhanced**
- **Revision Counter**: Added `_grip_feedback_revision_count` meta field to track each customer feedback submission
- **Force Post Modified**: Updates `post_modified` timestamp on every customer feedback to ensure Make.com detection
- **REST API Exposure**: Added `feedback_revision_count` field to REST API for Make.com filtering
- **Webhook Data**: Included `revision_count` in customer feedback webhook payload

#### **Problem Solved**
- **Make.com "Watch Posts" Issue**: Both "Watch Posts" and "Watch Posts Updated" were ignoring subsequent customer feedback on the same grip design
- **Revision Detection**: Make.com now sees each customer feedback as a unique update due to changing revision count and modified timestamp
- **Guaranteed Triggering**: Every customer feedback (approve/request changes) will now trigger Make.com scenarios

#### **Technical Details**
- **Automatic Increment**: Revision counter increments on each customer feedback submission
- **Forced WordPress Update**: Uses `wp_update_post()` to update modified timestamps
- **REST API Field**: `feedback_revision_count` available at `/wp-json/wp/v2/grip_design/{id}`
- **Debug Logging**: Enhanced logging for revision tracking and post updates

#### **Updated**
- **Email Templates**: Fixed logo URL to use correct TwinTack white logo SVG
- **Logo Path**: Updated to `https://twintack.com/wp-content/uploads/2024/11/twintacklogowhite2.svg`
- **Dark Theme Styling**: Maintained WooCommerce-matching dark email templates

---

## Version 1.6.04 - 2024-12-19

### 🏭 Production Status Email Trigger

#### **Enhanced**
- **Production Started Email**: Now triggers when Monday.com production team updates status to "production_started" or "in_production"
- **Flexible Order Handling**: Works with or without final order completion - adapts to your workflow
- **Monday.com Integration**: Production team can trigger customer emails directly from Monday.com status changes

#### **New Trigger Points**
- **Artwork Ready**: Monday.com updates to "pending_review" → Customer gets review email
- **Production Started**: Monday.com updates to "production_started" → Customer gets production email
- **Dual Workflow Support**: Handles both Monday.com status changes AND WooCommerce order completion

#### **Make.com Scenario Setup**
- **Watch Monday.com**: Monitor status changes in Custom Grip Orders board  
- **Trigger Condition**: When item moves to "Production Ready" or "In Production" group
- **API Call**: `POST /wp-json/twintack/v1/grip-design/{id}/monday`
- **Payload**: `{"artwork_status": "production_started"}`

#### **Testing**
- **Test Script**: `test-production-trigger.php` for simulating Monday.com production triggers
- **Debug Logging**: Enhanced logging for production status email triggers
- **Flexible Testing**: Works with real grip designs or test data

---

## Version 1.6.03 - 2024-12-19

### 📧 Email Notifications System

#### **Added**
- **Comprehensive Email Notification System**: Automated customer emails for key workflow events
- **Artwork Ready for Review Email**: Sent when Monday.com updates artwork status to "pending_review"
- **Production Started Email**: Sent when customer approves design and production begins
- **Email Template System**: Professional HTML email templates with dynamic content
- **Email Logging**: Complete tracking of email attempts and delivery status
- **Admin Email Testing**: Test email functionality with real or mock data

#### **Email Templates**
- **Artwork Ready**: Professional template with review links and mockup display
- **Production Started**: Confirmation email with order details and timeline
- **Responsive Design**: Mobile-friendly HTML emails with TwinTack branding
- **Dynamic Content**: Customer names, team names, grip details, and action links

#### **Integration Points**
- **Monday.com API**: Triggers email when artwork_status changes to "pending_review"
- **WooCommerce Orders**: Triggers production email when final grip order is completed
- **Customer Dashboard**: Email links direct customers to their grip design review page
- **WordPress Actions**: Extensible hook system for custom email triggers

#### **Testing & Management**
- **Email Test Page**: `/test-email-notifications.php` for comprehensive testing
- **AJAX Testing**: Admin interface for sending test emails with real or mock data
- **Debug Logging**: Detailed email send tracking with WP_DEBUG integration
- **Template Customization**: Filterable email templates for custom branding

#### **Technical Details**
- **Hook System**: `grip_design_artwork_status_changed` and `grip_production_approval` actions
- **Email Logging**: Complete audit trail stored in grip design meta fields
- **Proper Headers**: Professional from addresses and HTML content type
- **Error Handling**: Graceful failure handling with detailed logging

### 🔧 Email Workflow Integration
- **Step 6**: Artwork ready email automatically sent when Monday.com updates design status
- **Step 12**: Production started email sent when customer approves and order processes
- **Customer Experience**: Clear communication throughout the entire grip design process
- **Admin Oversight**: Complete email delivery tracking and testing capabilities

---

## Version 1.6.02 - 2024-12-19

### 🔧 Critical Fixes for Make.com Integration

#### **Fixed**
- **displayError toString Issues**: Resolved critical Make.com parsing errors by converting complex array responses to JSON strings
- **REST API Response Structure**: Simplified API response objects to prevent undefined property access errors
- **Complex Data Type Handling**: Ensured all REST field callbacks return parseable string values instead of raw arrays
- **Error Response Format**: Flattened error response structure to eliminate nested objects that caused parsing issues

#### **Added**
- **API Test Endpoint**: New `/wp-json/twintack/v1/test` endpoint for Make.com connectivity testing
- **Enhanced API Logging**: Comprehensive debugging logs for API request tracking and troubleshooting
- **Permission Check Debugging**: Detailed logging for API key validation and access control
- **Diagnostic Script**: Created `api-test.php` for comprehensive API testing and validation

#### **Changed**
- **Customer Feedback History Field**: Now returns JSON-encoded string instead of raw array
- **Monday Feedback History**: Complex arrays properly serialized as JSON strings
- **API Response Payload**: Flattened structure with simple data types for Make.com compatibility
- **User-Agent Headers**: Updated to version 1.6.02 for better request tracking

#### **Technical Details**
- All `get_post_meta()` array returns now use `json_encode()` for Make.com compatibility
- Removed nested `debug_info` objects from API responses
- Enhanced error handling with proper HTTP status codes
- Improved webhook data serialization for external integrations

### 🚀 Make.com Integration Testing
- Use the new test endpoint: `/wp-json/twintack/v1/test`
- Upload and run `api-test.php` for comprehensive diagnostics
- All API responses now return simple, parseable data types
- Enhanced logging for better troubleshooting

---

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