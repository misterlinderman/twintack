# Customer Feedback System

> **Primary documentation:** [`plugins/twintack-custom-grips/WORKFLOW.md`](../twintack-custom-grips/WORKFLOW.md)
>
> This document describes the customer feedback feature and clarifies which plugin owns what. The native experience lives in **TwinTack Custom Grips**. **TwinTack Grip Manager** provides the underlying post type, form intake, and WooCommerce bridge.

---

## Overview

Customers review design mockups and either approve them for purchase or request changes. The feedback loop is managed natively in WordPress — no Monday.com or Make.com involvement in the active workflow.

---

## Active System (TwinTack Custom Grips)

### Customer interface

- **Location:** WooCommerce My Account → **My Custom Grips** (`/my-account/my-custom-grips/`)
- **Actions:** Approve design, request changes (with optional feedback)
- **Handler:** `TTCG_Ajax::handle_customer_review_design()`
- **Messaging:** Feedback appears in the design message thread

### Team interface

- **Location:** Team Dashboard (`/team-dashboard/`)
- **Mockup upload:** Sets status to `pending_review` and sends customer email
- **Status management:** Role-based permissions via `TTCG_Status`

### Status flow

| Step | Status |
|------|--------|
| Design submitted (deposit paid) | `artwork_pending` |
| Team uploads mockup | `pending_review` |
| Customer requests changes | `customer_requested_changes` |
| Team re-uploads mockup | `pending_review` |
| Customer approves | `customer_approved` |
| Customer purchases final grips (WC Processing) | `in_production` |
| Order shipped (WC Completed) | `shipped` |

There is no **Production Ready** (`approved_for_production`) step. Customer approval persists until purchase.

---

## Grip Manager Role (Infrastructure)

Grip Manager still provides:

- `grip_design` custom post type registration
- Gravity Forms → cart → purchase-based post creation
- WooCommerce order status sync (delegates to `TTCG_Status` when Custom Grips is active)
- Cart thumbnail and reorder helpers
- WordPress admin meta boxes for data inspection

### Legacy customer feedback path

The original implementation in `TwinTack_Grip_Account` (`wp_ajax_grip_customer_feedback`) and the REST endpoint `POST /wp-json/twintack/v1/grip-design/{id}/customer-feedback` remain for backward compatibility with the old **My Grip Designs** page. New development should use the Custom Grips endpoints and UI only.

### Legacy Make.com webhooks

Grip Manager can still fire Make.com webhooks from the **legacy** feedback handler if a webhook URL is configured via the `grip_customer_feedback_webhook_url` filter. The **native** Custom Grips customer review path does **not** use these webhooks.

---

## Database Meta Fields

| Field | Description |
|-------|-------------|
| `_grip_customer_feedback` | Feedback history (string or array depending on entry path) |
| `_grip_latest_customer_feedback` | Most recent feedback text |
| `_grip_latest_customer_action` | Last action (`approve` / `request_changes`) |
| `_grip_final_order_id` | WooCommerce order ID for final grip purchase |
| `_grip_production_started` | Timestamp when order entered production |
| `_grip_monday_item_id` | **Legacy** — Monday.com item reference |
| `_grip_monday_feedback` | **Legacy** — Monday.com team messages |

---

## API Endpoints

### Active (native)

Customer review is handled via WordPress AJAX:

```
wp_ajax_ttcg_customer_review_design
```

Requires logged-in customer who owns the design; design must be in `pending_review`.

### Legacy (Grip Manager REST)

```
POST /wp-json/twintack/v1/grip-design/{id}/customer-feedback
POST /wp-json/twintack/v1/grip-design/{id}/purchase-complete
POST /wp-json/twintack/v1/grip-design/{id}/monday  (deprecated — see MONDAY-API.md)
```

Do not use these for new integrations.

---

## WooCommerce Integration

- Final grip purchase adds the custom grip product to cart with design metadata
- Purchase allowed when artwork status is `customer_approved` or `shipped` (reorder)
- Order **Processing** → artwork status `in_production`
- Order **Completed** / **shipped-unpaid** → artwork status `shipped`

---

## Security

- Customer must be logged in and own the design (email match)
- Feedback only accepted when status is `pending_review`
- Nonce verification on all AJAX requests
- Staff status changes validated against role permission matrix

---

## Troubleshooting

| Issue | Check |
|-------|-------|
| Feedback form not showing | Status must be `pending_review`; customer on **My Custom Grips** page |
| Purchase button missing | Status must be `customer_approved` |
| Status stuck after order | Verify WooCommerce order reached Processing/Completed; check `TTCG_Status` sync methods |
| Legacy "Production Ready" showing | Deploy Custom Grips v1.2.4+ to run migration, or manually update meta to `in_production` |

---

## Related Documentation

- [`WORKFLOW.md`](../twintack-custom-grips/WORKFLOW.md) — Canonical native workflow
- [`MONDAY-API.md`](MONDAY-API.md) — Deprecated Monday.com API
- [`PURCHASE-BASED-GRIP-CREATION.md`](PURCHASE-BASED-GRIP-CREATION.md) — Form → post creation
