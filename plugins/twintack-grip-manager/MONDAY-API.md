# Monday.com Integration API — DEPRECATED

> **This integration is no longer in use.** Production workflow moved to the native **TwinTack Custom Grips** experience in WordPress. The API endpoint and related code remain in Grip Manager for backward compatibility with legacy records and any dormant Make.com scenarios.
>
> **Current workflow documentation:** [`plugins/twintack-custom-grips/WORKFLOW.md`](../twintack-custom-grips/WORKFLOW.md)

---

## Status

| Item | State |
|------|-------|
| Monday.com board sync | **Retired** — not part of active workflow |
| Make.com scenarios | **Retired** — not required for production |
| REST endpoint | Still registered; do not use for new integrations |
| Legacy meta fields | `_grip_monday_item_id`, `_grip_monday_feedback` may exist on older posts |

---

## Legacy Endpoint (Reference Only)

```
POST /wp-json/twintack/v1/grip-design/{id}/monday
```

### Authentication

**Header (recommended):**
```
X-API-Key: your-secret-key
```

**Default API Key:** `twintack-monday-2024`  
**Custom API Key:** Define `TWINTACK_MONDAY_API_KEY` in wp-config.php

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Grip design post ID (URL path) |
| `monday_feedback` | string | Design team message |
| `mockup_asset_id` | string | Monday.com asset ID |
| `mockup_asset_url` | string | Direct URL to mockup asset |
| `artwork_status` | string | Status code (see below) |
| `monday_item_id` | string | Monday.com item ID |

### Legacy slug mapping

If this endpoint receives `approved_for_production`, it is stored as `in_production`.

---

## Current Artwork Status Reference

Use these slugs for any maintenance scripts or data review. See **WORKFLOW.md** for the active flow.

| Status Code | Label | Active in native workflow |
|-------------|-------|---------------------------|
| `artwork_pending` | Mockup Required | Yes |
| `pending_review` | Customer Review | Yes |
| `customer_requested_changes` | Customer Changes | Yes |
| `customer_approved` | Customer Approved | Yes |
| `in_production` | In Production | Yes — set automatically on WC Processing |
| `shipped` | Shipped | Yes — set automatically on WC Completed |
| `approved_for_production` | *(legacy)* | **No** — migrated to `in_production` |
| `artwork_approved` | *(legacy)* | Display only |
| `internal_review` | *(legacy)* | Display only |

### Current workflow (native)

1. `artwork_pending` → Initial state after deposit
2. `pending_review` → Team uploads mockup
3. `customer_requested_changes` ↔ `pending_review` → Customer feedback loop
4. `customer_approved` → Customer approves (stays until purchase)
5. `in_production` → WooCommerce order Processing
6. `shipped` → WooCommerce order Completed / shipped-unpaid

---

## Example Request (Legacy)

```bash
curl -X POST "https://yoursite.com/wp-json/twintack/v1/grip-design/123/monday" \
  -H "X-API-Key: twintack-monday-2024" \
  -H "Content-Type: application/json" \
  -d '{
    "monday_feedback": "Design looks great!",
    "mockup_asset_url": "https://example.com/mockup.png",
    "artwork_status": "pending_review"
  }'
```

---

## Migration Notes

- Mockup uploads now use the **Custom Grips team dashboard** (WordPress media), not Monday.com assets
- Customer approve/request-changes uses **My Custom Grips** (`TTCG_Ajax`), not Make.com webhooks
- Order status sync is handled natively via WooCommerce hooks → `TTCG_Status`

Do not build new features against this endpoint.
