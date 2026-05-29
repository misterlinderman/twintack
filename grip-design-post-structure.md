# Grip Design Post Structure

> **Workflow documentation:** [`plugins/twintack-custom-grips/WORKFLOW.md`](plugins/twintack-custom-grips/WORKFLOW.md)

## Core Fields

- **id** — WordPress Post ID
- **status** — `publish` (default for paid designs) / `draft` / `pending` / `private`
- **title** — Post title (format: "Custom Grip - [Team Name] [YYMMDD]")
- **content** — Design instructions / feedback from customer

## Custom Fields (Grip Metadata)

### Customer Information

- **_grip_customer_name** — Full customer name
- **_grip_customer_email** — Customer email address
- **_grip_team_name** — Team/School name
- **_grip_quantity** — Number of grips ordered

### Design Specifications

- **_grip_design_type** — Complete design description (e.g., "2-Color Fade (Grey + Violet)")
- **_grip_design_layout** — Base layout type (Solid Color, 2-Color Fade, 3-Color Fade, etc.) *[New Form Only]*
- **_grip_primary_color** — Primary/Product color *[New Form Only]*
- **_grip_secondary_color** — Second color (if applicable) *[New Form Only]*
- **_grip_tertiary_color** — Third color (if applicable) *[New Form Only]*

### Form & Order Tracking

- **_grip_form_entry_id** — Gravity Forms entry ID
- **_grip_form_type** — `original` or `new` (identifies which form was used)
- **_grip_order_id** — WooCommerce order ID (deposit order)
- **_grip_order_item_id** — Specific order item ID
- **_grip_final_order_id** — WooCommerce order ID for final grip purchase
- **_grip_production_started** — Timestamp when order entered production

### Artwork & Files

- **_grip_artwork_url** — Full URL to uploaded artwork file
- **_grip_artwork_filename** — Original filename of artwork
- **_grip_feedback** — Customer design instructions/feedback
- **_grip_mockup_url** — URL to mockup files (legacy; mockups now managed via Custom Grips dashboard)

### Workflow Status

- **_grip_artwork_status** — Artwork workflow status (see below)

### Legacy Fields (may exist on older records)

- **_grip_monday_feedback** — Monday.com team feedback *(retired integration)*
- **_grip_monday_item_id** — Monday.com item ID *(retired integration)*

## Computed / Display Fields

- **grip_status_label** — Human-readable status ("Mockup Required", "In Production", etc.)
- **grip_status_code** — Raw `_grip_artwork_status` slug

## Post Creation Workflow

### Current (Purchase-Based)

```
Form Submission → Store Data in Cart → Add to Cart → Payment Complete → Create Post (publish)
```

Implemented in **twintack-grip-manager**. Posts are only created after successful payment of the Custom Grip Design Deposit.

## Artwork Status Flow

Managed by **twintack-custom-grips** (`_grip_artwork_status`):

| Step | Status slug |
|------|-------------|
| Post created after deposit | `artwork_pending` |
| Team uploads mockup | `pending_review` |
| Customer requests changes | `customer_requested_changes` |
| Customer approves | `customer_approved` |
| Final order → WC Processing | `in_production` |
| Order fulfilled → WC Completed | `shipped` |

WordPress post status and WooCommerce order statuses are independent and unchanged by this workflow.

### Legacy slug

- `approved_for_production` — Removed as a team-facing step; migrated to `in_production` in Custom Grips v1.2.4

## Admin Benefits

- All grip design posts guaranteed to have associated paid deposit orders
- Clear audit trail from order to design post
- No cleanup needed for unpaid/abandoned designs
- Order notes track post creation automatically
- Native team dashboard provides full workflow without external tools

## Related Documentation

- [`plugins/twintack-custom-grips/WORKFLOW.md`](plugins/twintack-custom-grips/WORKFLOW.md) — Full native workflow
- [`plugins/twintack-grip-manager/PURCHASE-BASED-GRIP-CREATION.md`](plugins/twintack-grip-manager/PURCHASE-BASED-GRIP-CREATION.md) — Form → post creation details
