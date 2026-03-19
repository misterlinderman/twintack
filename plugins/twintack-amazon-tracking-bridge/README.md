# TwinTack Amazon Tracking Bridge

A WordPress plugin that bridges Shippo tracking data from WooCommerce order notes to WP-Lister Amazon fulfillment feeds, ensuring tracking numbers are synced to Amazon.

## Background

In March 2026, a tracking sync issue was identified where Amazon orders were being marked as shipped but without tracking numbers, causing Amazon to flag them with "Invalid Tracking" (VTR criteria 2: "Was a tracking ID provided?" = No).

### Root Cause

The Shippo/WooCommerce Shipping integration creates shipping labels and writes tracking numbers **only to WooCommerce order notes** — not to any order meta key. WP-Lister Amazon reads tracking data exclusively from meta keys (specifically `_wpla_tracking_number`). This disconnect meant every order shipped via Shippo was submitted to Amazon without a tracking number.

Working orders from before Feb 10, 2026 had tracking numbers entered manually through WP-Lister's UI. After that date, manual entry stopped and the gap became visible.

### How It Works

```
Without Bridge:
  Shippo → Order Note (tracking #) → WP-Lister reads meta → Empty → Amazon feed WITHOUT tracking

With Bridge:
  Shippo → Order Note (tracking #) → Bridge intercepts note → Writes to _wpla_tracking_number
  → WP-Lister reads meta → Found → Amazon feed WITH tracking
```

## Features

- **Real-time note interception**: Hooks into `woocommerce_order_note_added` to parse Shippo label creation notes and immediately write tracking data to WP-Lister meta keys
- **Filter fallbacks**: Safety net via `wpla_custom_tracking_number`, `wpla_custom_tracking_provider`, `wpla_set_tracking_number_for_order`, and `wpla_set_tracking_service_for_order` filter hooks
- **Late-arrival re-trigger**: If an order was already submitted to Amazon without tracking, and tracking arrives later, the plugin re-submits the fulfillment feed
- **Multi-carrier support**: Maps carrier names (USPS, UPS, FedEx, DHL) to WP-Lister carrier codes

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- WP-Lister for Amazon (must be active)
- PHP 7.4 or higher

## Installation

1. Upload the plugin directory to `/wp-content/plugins/twintack-amazon-tracking-bridge/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. No configuration needed — the plugin works automatically once activated

## Technical Details

### Note Parsing Pattern

The plugin matches Shippo label creation notes using this regex:

```
/(\w+)\s+.*?label\s+with\s+tracking\s+number\s+([A-Za-z0-9]{10,30})\s+has\s+been\s+created\s+on\s+Shippo/i
```

Example matched note:
> usps Ground Advantage label with tracking number 9200190396055707139969 has been created on Shippo

### Meta Keys Written

| Meta Key | Value | Description |
|---|---|---|
| `_wpla_tracking_number` | Tracking number from Shippo note | Read by WP-Lister for fulfillment feed |
| `_wpla_tracking_provider` | Carrier code (e.g., "USPS") | Read by WP-Lister for carrier field |

### Hooks Used

| Hook | Type | Purpose |
|---|---|---|
| `woocommerce_order_note_added` | Action | Primary: intercept Shippo notes in real-time |
| `wpla_custom_tracking_number` | Filter | Fallback: supply tracking at feed-build time |
| `wpla_custom_tracking_provider` | Filter | Fallback: supply carrier at feed-build time |
| `wpla_set_tracking_number_for_order` | Filter | Fallback: final tracking resolution |
| `wpla_set_tracking_service_for_order` | Filter | Fallback: service name for "Other" carriers |

### Carrier Mapping

| Shippo Value | WP-Lister Code |
|---|---|
| usps | USPS |
| ups | UPS |
| fedex | FedEx |
| dhl | DHL |
| (other) | Other |

## Logging

When `WP_DEBUG` is enabled, the plugin logs to the PHP error log with the prefix `TwinTack Amazon Bridge:`. Log messages include:

- Note interception events
- Meta key writes
- Fulfillment feed re-submissions
- Filter fallback activations

## Affected Orders (Historical Context)

29 Amazon orders from Feb 10 – Mar 14, 2026 were affected by this issue. These orders had already passed their Amazon delivery windows when the fix was deployed, so retroactive backfill was not performed — Amazon does not accept tracking updates for orders past their expected delivery date.

The bridge plugin prevents this issue for all future orders.

## File Structure

```
plugins/twintack-amazon-tracking-bridge/
├── twintack-amazon-tracking-bridge.php    # Main plugin file (singleton class)
└── README.md                              # This file
```

## Changelog

### 1.1.0 (2026-03-18)
- Rewrote plugin based on debug findings confirming order notes as sole tracking source
- Added `woocommerce_order_note_added` hook as primary real-time interception mechanism
- Removed dead-code meta sources (`_shippo_tracking_number`, `_wc_connect_labels`, WC Fulfillments)
- Removed `updated_post_meta`/`added_post_meta` hooks (Shippo never writes meta)
- Simplified filter fallbacks to order-note-only parsing
- Added re-entrant protection for bridge's own order notes

### 1.0.0 (2026-03-18)
- Initial draft with multi-source tracking resolution
- Superseded by v1.1.0 after debug confirmed only order notes contain tracking data

## Related Files

- **Debug script** (`debug-amazon-tracking-sync.php`): Used to diagnose the root cause. Should be deleted from server after debugging.
- **Backfill script** (`fix-amazon-tracking-backfill.php`): Designed to retroactively fix affected orders. Not deployed (orders past delivery window).
- **Shippo tracking display** (`plugins/twintack-manual-order-payments/includes/class-shippo-tracking-display.php`): Displays Shippo tracking to customers in emails and account pages.

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html
