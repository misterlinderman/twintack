# TwinTack Manual Order Payments – Changelog

All notable changes to this plugin will be documented in this file.

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
