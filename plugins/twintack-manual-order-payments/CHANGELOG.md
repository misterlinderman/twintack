# TwinTack Manual Order Payments – Changelog

All notable changes to this plugin will be documented in this file.

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
