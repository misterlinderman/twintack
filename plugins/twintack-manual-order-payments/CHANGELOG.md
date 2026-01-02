# TwinTack Manual Order Payments – Changelog

All notable changes to this plugin will be documented in this file.

## 4.6.1 – 2025-12-10
### Added
- **🔍 WP-CRON DIAGNOSTIC TOOL**: Comprehensive diagnostic tool for troubleshooting WP-Cron and Shippo sync issues
  - **Real-time Status Monitoring**: Shows WP-Cron configuration status and identifies problems
  - **Shippo Sync Dashboard**: Displays automation status, next scheduled run, and eligible orders
  - **Scheduled Events Viewer**: Lists all WordPress cron events with overdue detection
  - **One-Click Manual Sync**: Force immediate Shippo sync without waiting for cron
  - **WP-Cron Manual Trigger**: Ability to manually spawn WP-Cron to run overdue tasks
  - **Automation Toggle**: Enable/disable automated sync directly from diagnostic tool
  - **Access via**: `/wp-content/plugins/twintack-manual-order-payments/test-wp-cron-status.php`

- **📊 CRON HEALTH MONITOR**: Real-time monitoring of WP-Cron health directly in WordPress admin
  - **Dashboard Widget**: Displays cron health status on WordPress main dashboard
  - **Last Run Tracking**: Records and displays when WP-Cron last executed
  - **Overdue Event Detection**: Automatically detects and counts overdue scheduled tasks
  - **Real Cron Detection**: Identifies if site is using real server cron job or visitor-triggered WP-Cron
  - **Admin Notices**: Shows warnings when cron hasn't run in over 30 minutes
  - **Shippo Sync Integration**: Shows cron health status on Shippo Sync admin page
  - **Execution Counter**: Tracks total number of cron executions for monitoring trends

### Documentation
- **WP-CRON-SHIPPO-FIX-GUIDE.md**: Comprehensive troubleshooting guide for WP-Cron failures
  - Root cause analysis of WP-Cron failures
  - Step-by-step immediate fixes
  - Long-term solutions (real cron jobs, external services)
  - FTP-only environment considerations
  - Monitoring and prevention strategies
- **QUICK-FIX-SHIPPO-SYNC.md**: One-page quick reference for immediate fixes
- **CLIENT-COMMUNICATION-TEMPLATE.md**: Pre-written email templates for client communication
  - Initial problem notification
  - Follow-up messages
  - Alternative solutions for different scenarios

### Enhanced
- **Problem Detection**: Identifies when WP-Cron failures prevent Shippo sync from running
- **Visual Diagnostics**: Color-coded status indicators (green/yellow/red) for quick assessment
- **Order Backlog Detection**: Automatically identifies orders waiting to sync
- **Detailed Logging**: Enhanced logging for WP-Cron and automation events

### Technical
- **WP-Cron Analysis**: Checks for `DISABLE_WP_CRON` and `ALTERNATE_WP_CRON` constants
- **Scheduled Task Monitoring**: Detects overdue cron events with time calculations
- **Order Query Optimization**: Efficient queries for Processing and Invoiced orders
- **Shippo Integration Verification**: Validates Shippo plugin presence and configuration

### Fixed
- **WP-Cron Dependency**: Documented dependency on WP-Cron for automated Shippo sync
- **Sync Failure Detection**: Added tools to quickly identify why orders aren't syncing
- **Manual Sync Access**: Improved access to manual sync functionality for emergency use

## 4.6.0 – 2025-01-17
### Added
- **🎯 VIRTUAL PRODUCT SHIPPO FILTERING**: Intelligent filtering system prevents virtual products from syncing to Shippo
  - **Smart Detection**: Uses WooCommerce's native `needs_shipping()` method to identify virtual products
  - **Automatic Filtering**: Virtual-only orders are automatically skipped from Shippo sync
  - **Admin Visibility**: New "Shippo Skipped" admin page shows all skipped orders with reasons
  - **Comprehensive Logging**: All filtering decisions are logged with clear explanations
  - **Performance Optimization**: Reduces unnecessary API calls to Shippo for virtual products

### Enhanced
- **Shippo Integration**: Enhanced with intelligent product type detection before API sync
- **Admin Interface**: Added dedicated admin page at WooCommerce → Shippo Skipped for transparency
- **Order Tracking**: Skipped orders are marked in database with skip reasons for audit trail
- **Mixed Order Handling**: Orders with both virtual and physical products sync correctly (physical products trigger sync)

### Technical
- **Virtual Product Detection**: Added `order_needs_shipping()` method to check all order items
- **Skip Reason Tracking**: Orders marked with `_shippo_skipped_reason` meta for admin visibility
- **WooCommerce Native**: Uses built-in WooCommerce product type detection methods
- **Backward Compatible**: All existing functionality preserved, only adds filtering layer

### Fixed
- **Grip Deposit Orders**: Virtual grip deposit products no longer appear in Shippo dashboard
- **Clean Shippo Interface**: Only physical products requiring shipping appear in Shippo
- **Proper Product Classification**: Ensures virtual products are correctly identified and filtered

## 4.5.2 – 2025-01-14
### Fixed
- **🔧 PDF INVOICE WHOLESALE PRICING**: PDF invoices now correctly display wholesale pricing for Drop Ship customers
  - **Proper Role Detection**: Fixed wholesale role detection to work with WooCommerce Wholesale Prices plugin
  - **Accurate Unit Pricing**: Line items now show wholesale unit price ($14.49) instead of MSRP ($21.99)
  - **Consistent Subtotal**: Subtotal now matches line item pricing for wholesale customers
  - **Visual Indicators**: Added "WHOLESALE" badges to wholesale items in PDF invoices
  - **Enhanced Integration**: Properly integrates with WooCommerce Wholesale Prices plugin role detection

### Enhanced
- **Improved Wholesale Detection**: Uses WooCommerce Wholesale Prices plugin's built-in role detection methods
- **Comprehensive Fallback Logic**: Multiple fallback methods ensure wholesale pricing is applied correctly
- **Detailed Logging**: Added comprehensive logging for wholesale pricing detection and calculation
- **Consistent Pricing**: Both CSV exports and PDF invoices now use the same wholesale pricing logic

### Technical
- **Role Detection Fix**: Updated to use `WWP_Wholesale_Roles::getUserWholesaleRole()` for proper Drop Ship role detection
- **Subtotal Calculation**: Fixed subtotal calculation to use wholesale line totals instead of MSRP subtotal
- **Plugin Integration**: Enhanced integration with WooCommerce Wholesale Prices plugin (free and premium versions)

## 4.5.1 – 2025-01-08
### Fixed
- **💰 CSV EXPORT PRICING FIX**: CSV exports now show actual charged prices (wholesale/dropship rates) instead of MSRP
  - **Enhanced Item Details**: Each item now shows unit price and line total as actually charged to customer
  - **Separated Pricing Columns**: Added separate columns for Order Subtotal, Tax, and Total for better clarity
  - **Accurate Financial Reporting**: CSV exports now reflect true revenue and pricing for accounting purposes
  - **Product Variations**: Includes all product meta data (variations, custom options) in item descriptions

### Enhanced
- **Improved CSV Structure**: More detailed financial breakdown with subtotal, tax, and total columns
- **Better Item Formatting**: Items show quantity, unit price, line total, and any custom attributes
- **Financial Accuracy**: All pricing data reflects actual amounts charged, not catalog/MSRP prices

## 4.5.0 – 2025-01-08
### Added
- **📄 PDF INVOICE GENERATION**: Professional PDF invoices with complete order details and TwinTack branding
  - **Smart PDF Library Detection**: Automatically detects TCPDF availability with HTML fallback
  - **Professional Layout**: Comprehensive invoice format with company header, billing info, itemized list, and totals
  - **PO Number Integration**: Customer Purchase Order numbers prominently displayed on invoices
  - **Direct Download**: One-click PDF generation and download from order admin pages
- **🏢 TWINTACK ORDER CONTROL PANEL**: Dedicated section on order pages for manual order management
  - **PO Number Field**: Add and save customer Purchase Order numbers with real-time validation
  - **PDF Invoice Button**: Generate professional invoices with all order details and PO information
  - **Smart UI**: Visual indicators showing PO status and invoice readiness
  - **AJAX Integration**: Real-time saving and generation without page reloads

### Enhanced
- **Order Management Workflow**: Centralized control panel for all manual order processing tasks
- **Invoice Customization**: Branded invoices with company information and professional styling
- **Order Documentation**: Automatic logging of PDF generation and PO number updates in order notes
- **Fallback Support**: Works with or without TCPDF library - HTML invoices when PDF unavailable

### Technical
- New `TwinTack_PDF_Invoice_Generator` class with TCPDF integration
- Order meta field `_twintack_po_number` for storing customer PO numbers
- Enhanced admin interface with professional styling and user experience
- Comprehensive error handling and logging for PDF generation process

## 4.4.1 – 2025-01-08
### Added
- **⚙️ CONFIGURABLE SYNC AGE FILTER**: Choose when orders become eligible for sync
  - **Immediate (0 hours)**: Perfect for same-day shipping workflows - sync orders immediately
  - **2-12 hours**: Short delays for quality control before notifications
  - **24 hours (default)**: Conservative approach - ensures orders are actually shipped
  - **48+ hours**: Extra conservative for manual fulfillment workflows
- **Smart UI**: Admin interface shows current age setting and eligible order count
- **Unified Logic**: Both manual and automated sync use the same configurable age filter

### Enhanced
- **Real-time Feedback**: Age setting changes immediately affect both manual and automated sync
- **Dynamic Criteria Display**: Overview shows current age requirement (e.g., "Any age", "6 hours or older", "1 day or older")
- **Workflow Flexibility**: Supports everything from immediate same-day shipping to multi-day fulfillment processes

### Fixed
- **Same-day Shipping Support**: Can now sync orders shipped on the same day they were created
- **Age Filter Consistency**: Manual sync and automated sync use identical age criteria

## 4.4.0 – 2025-01-08
### Added
- **🤖 AUTOMATED SHIPPO SYNC**: Full automation system for Shippo order synchronization
  - **Scheduled Sync**: WordPress cron job runs every 4 hours to automatically sync eligible orders
  - **Real-time Triggers**: When real Shippo webhooks arrive, automatically checks for other orders needing sync
  - **Smart Filtering**: Only processes orders 1+ days old with Shippo Order IDs (prevents premature syncing)
  - **Safe Processing**: Limits to 10 orders per run to prevent server timeouts
  - **Enhanced Admin Interface**: Toggle automation on/off from WooCommerce → Shippo Sync
- **Automation Controls**: Enable/disable automated sync with one-click toggle in admin
- **Webhook Enhancement**: Existing Shippo webhooks now trigger additional sync checks for related orders
- **Comprehensive Logging**: All automated sync activities logged for debugging and monitoring

### Enhanced
- **Shippo Sync Admin Page**: Now includes automation status, next run time, and toggle controls
- **Webhook Handler**: Enhanced with automation triggers and cron job scheduling
- **Manual Sync Integration**: Manual sync and automated sync use the same reliable logic
- **Error Handling**: Robust error handling for automated processes with detailed logging

### Technical
- Custom WordPress cron intervals (4-hour and 1-hour options)
- Automated webhook simulation for seamless order status updates
- Integration with existing webhook infrastructure
- Backward compatible with all existing manual sync functionality

## 4.3.0 – 2025-01-08
### Added
- **New Custom Order Status**: "Shipped (Unpaid)" for orders that have been shipped but payment is still pending
- **Enhanced Shippo Integration**: Webhook handler now properly maps shipped orders to "Shipped (Unpaid)" status
- **Shippo Sync Admin Page**: WooCommerce → Shippo Sync for syncing existing shipped orders from Shippo
- **Manual Tracking Interface**: Dedicated tracking information section on order admin pages
  - Add/edit tracking numbers and carriers manually
  - Send tracking notification emails to customers
  - Real-time AJAX updates with visual feedback
- **New Bulk Action**: "Mark Shipped Orders as Paid (TwinTack)" for completing the payment workflow
- **Automatic Email Notifications**: Shipment emails sent when orders transition to "Shipped (Unpaid)"

### Enhanced
- Webhook simulation system for syncing orders that were shipped in Shippo but not reflected in WooCommerce
- Age filtering criteria for sync operations (now includes orders ≥1 day old)
- Improved status mapping between Shippo and WooCommerce for better workflow tracking
- Email notification system that works with existing "Send Status Email" buttons

### Fixed
- Payment workflow disconnect where shipped orders weren't properly reflected in WooCommerce
- Tracking information display for manually updated orders
- Enhanced webhook handler to properly process shipment status updates

## 4.2.0 – 2025-01-08
### Added
- **Bulk Invoice Manager**: Complete bulk operations system for managing invoiced orders
  - Bulk "Mark as Paid" action for multiple orders from the orders list
  - Bulk "Set to Invoiced" action for quick invoice processing
  - CSV export functionality for invoiced orders and all orders
  - Dedicated admin page: WooCommerce → Bulk Invoice Manager
  - Enhanced bulk actions dropdown with TwinTack-specific options
- **Individual Order Enhancements**: Prominent "Mark as Paid" section on individual order admin screens
  - Quick "Mark as Paid & Ready to Ship" button for instant payment processing
  - "Set to Invoice" button for sending payment links to customers
  - Visual status indicators and helpful guidance text
  - Automatic Shippo sync integration for all payment processing
- **CSV Export Features**: Comprehensive order data export with customer info, items, shipping details, and Shippo tracking
- **Enhanced UI/UX**: Improved visual design with prominent payment processing sections and clear action buttons

### Enhanced
- Admin order enhancements now work alongside existing Simple Order Manager
- Better integration with Shippo fulfillment workflow
- Improved AJAX error handling and user feedback
- Enhanced security with proper nonce verification for all new features

## 4.1.6 – 2025-08-11
- Tracking links now resolve to carrier-specific pages when carrier info is available (USPS/UPS/FedEx/DHL). Falls back to Shippo tracker otherwise.
- Webhook now stores `_shippo_tracking_carrier` so the front-end can build the correct link.

## 4.1.5 – 2025-08-10
- Added tracking display to customer My Account order view and to WooCommerce emails via `TwinTack_Shippo_Tracking_Display`.
- Completed-order email now triggers when Shippo marks an order completed or when a tracking number is added to an already-completed order.
- “Send Status Email” admin action now sends the correct template based on current order status (Processing/Completed), with a safe fallback.
- Tracking display supports multiple sources: `_shippo_tracking_number`, `_wc_shipment_tracking_items`, and fallback parsing of order notes.
- Internal logging improvements around webhook handling.

## 4.1.4 – 2025-07-xx
- Internal improvements to admin UI and Shippo API client.

---

Note: Version numbers mirror the plugin header `Version:` and the constant `TWINTACK_MANUAL_PAYMENTS_VERSION` in `twintack-manual-order-payments.php`.
