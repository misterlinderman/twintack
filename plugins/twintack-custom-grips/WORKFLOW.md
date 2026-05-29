# TwinTack Custom Grips — Native Workflow

**Canonical reference** for the custom grip design workflow as of plugin v1.2.4.

Production is managed entirely in WordPress via the **TwinTack Custom Grips** plugin. Monday.com and Make.com are **no longer used** for this workflow. Legacy integration code remains in **TwinTack Grip Manager** for backward compatibility only.

---

## Plugin Responsibilities

| Plugin | Role |
|--------|------|
| **twintack-custom-grips** | Native workflow: team dashboard, customer **My Custom Grips**, artwork statuses, mockup uploads, messaging, email notifications |
| **twintack-grip-manager** | Infrastructure: `grip_design` post type, Gravity Forms intake, purchase-based post creation, WooCommerce order bridge, cart/reorder helpers |

Grip Manager does **not** drive the production UI. It creates and links grip design posts to orders; Custom Grips handles everything the art and production teams see and do day to day.

---

## Artwork Status Flow

All artwork progress is stored in `_grip_artwork_status` on the `grip_design` post.

| Status slug | Label | Set by | Description |
|-------------|-------|--------|-------------|
| `artwork_pending` | Mockup Required | System / team | Initial state after deposit paid; waiting for design team mockup |
| `pending_review` | Customer Review | Art team (mockup upload) | Mockup ready; customer can approve or request changes |
| `customer_requested_changes` | Customer Changes | Customer | Customer submitted revision feedback |
| `customer_approved` | Customer Approved | Customer | Customer approved; design stays here until final purchase |
| `in_production` | In Production | **Automatic** (WooCommerce) | Set when the final grip order moves to **Processing** |
| `shipped` | Shipped | **Automatic** (WooCommerce) | Set when the order is **Completed** or **shipped-unpaid** |

### End-to-end flow

```
Deposit paid → grip_design post created (artwork_pending)
     ↓
Team uploads mockup → pending_review
     ↓
Customer approves → customer_approved  ← stays here until purchase
     ↓
Customer purchases final grips → WooCommerce Processing → in_production
     ↓
Order fulfilled / shipped → WooCommerce Completed → shipped
```

There is **no** internal "Production Ready" step. After customer approval, the design remains **Customer Approved** until the customer completes their final purchase.

### Legacy status slugs

These may still exist on older records and are mapped for display only:

| Legacy slug | Maps to | Notes |
|-------------|---------|-------|
| `approved_for_production` | `in_production` | Removed as a team-facing step (v1.2.4); migrated automatically on plugin update |
| `artwork_approved` | Customer Approved step in progress UI | Pre–customer-feedback era |
| `internal_review` | Customer Review step in progress UI | Internal-only legacy status |

---

## WordPress & WooCommerce Statuses (Unchanged)

These systems are **independent** from artwork status:

- **WordPress post status** (`publish`, `draft`, etc.) — controls post visibility; grip designs are `publish` after deposit payment
- **WooCommerce order status** (`processing`, `completed`, `shipped-unpaid`, etc.) — triggers artwork status sync only; order statuses themselves are not modified by this workflow

### WooCommerce → artwork sync

Handled by `TwinTack_Grip_Account` in Grip Manager, which delegates to Custom Grips when active:

| WooCommerce order status | Artwork status set |
|--------------------------|-------------------|
| `processing` | `in_production` via `TTCG_Status::sync_in_production_from_wc_order()` |
| `completed`, `shipped-unpaid` | `shipped` via `TTCG_Status::sync_shipped_from_wc_order()` |

Filter: `twintack_grip_order_statuses_meaning_shipped` — defaults to `completed` and `shipped-unpaid`.

---

## User Interfaces

### Team dashboard (Custom Grips)

- **URL**: `/team-dashboard/` (requires Art Team or Production Team role)
- Status filters, mockup upload, messaging, manual status changes (where permitted)

### Customer dashboard (Custom Grips)

- **URL**: `/my-account/my-custom-grips/` (canonical endpoint)
- Legacy `/my-account/grip-designs/` redirects here
- Approve / request changes when status is `pending_review`
- Purchase and reorder when status is `customer_approved` or `shipped`

### WordPress admin (Grip Manager)

- Grip design post edit screen with meta boxes
- Used for data inspection and manual corrections; not the primary team workflow

---

## Role Permissions

Defined in `TTCG_Status`:

| Role | Can set statuses |
|------|------------------|
| **Art Team** | `pending_review` (via mockup upload) |
| **Production Team** / **Administrator** | All active statuses: `artwork_pending`, `pending_review`, `customer_requested_changes`, `customer_approved`, `in_production`, `shipped` |
| **Customer** | `customer_approved`, `customer_requested_changes` (self-service, own designs only, when `pending_review`) |

---

## Notifications

All active notifications are managed by **twintack-custom-grips**:

| Event | Recipient |
|-------|-----------|
| Customer sends message | Art + Production teams |
| Team sends message | Customer |
| Status changed | Customer (contextual email) |
| Mockup uploaded | Customer ("mockup ready" email) |
| Customer approves | Team + Customer |

View the full list in **WP Admin → Custom Grips → Notification Manager**.

---

## Purchase-Based Post Creation

Grip design posts are **not** created at form submission. Flow:

```
Gravity Form → cart metadata → deposit payment → grip_design post (publish)
```

Implemented in **twintack-grip-manager**. See `plugins/twintack-grip-manager/PURCHASE-BASED-GRIP-CREATION.md`.

Initial artwork status after creation: `artwork_pending`.

---

## Related Documentation

| Document | Purpose |
|----------|---------|
| `plugins/twintack-grip-manager/PURCHASE-BASED-GRIP-CREATION.md` | Form → cart → post creation |
| `plugins/twintack-grip-manager/MONDAY-API.md` | **Deprecated** — legacy Monday.com API reference |
| `grip-design-post-structure.md` | Post meta field reference |
| `.cursor/rules/data-workflow.cursor-rules` | Developer rules for data structures |

---

## Version History

| Version | Change |
|---------|--------|
| 1.2.4 | Removed `approved_for_production` (Production Ready) as team-facing step; purchase → `in_production` directly; DB migration for legacy records |
| 1.2.x | Native team dashboard, My Custom Grips, messaging, mockup uploads replace Monday.com workflow |
