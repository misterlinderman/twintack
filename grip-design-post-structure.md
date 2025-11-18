# Grip Design Post Structure - Updated Build

## Core Fields:
- **id** - WordPress Post ID
- **status** - publish (default for paid designs) / draft / pending / private
- **title** - Post title (format: "Custom Grip - [Team Name] [YYMMDD]")
- **content** - Design instructions / feedback from customer

## Custom Fields (Grip Metadata):

### Customer Information:
- **_grip_customer_name** - Full customer name
- **_grip_customer_email** - Customer email address
- **_grip_team_name** - Team/School name
- **_grip_quantity** - Number of grips ordered

### Design Specifications:
- **_grip_design_type** - Complete design description (e.g., "2-Color Fade (Grey + Violet)")
- **_grip_design_layout** - Base layout type (Solid Color, 2-Color Fade, 3-Color Fade, etc.) *[New Form Only]*
- **_grip_primary_color** - Primary/Product color *[New Form Only]*
- **_grip_secondary_color** - Second color (if applicable) *[New Form Only]*
- **_grip_tertiary_color** - Third color (if applicable) *[New Form Only]*

### Form & Order Tracking:
- **_grip_form_entry_id** - Gravity Forms entry ID
- **_grip_form_type** - 'original' or 'new' (identifies which form was used)
- **_grip_order_id** - WooCommerce order ID (links to purchase) **[NEW]**
- **_grip_order_item_id** - Specific order item ID **[NEW]**

### Artwork & Files:
- **_grip_artwork_url** - Full URL to uploaded artwork file **[ENHANCED]**
- **_grip_artwork_filename** - Original filename of artwork **[NEW]**
- **_grip_feedback** - Customer design instructions/feedback **[ENHANCED]**

### Workflow Status:
- **_grip_artwork_status** - Internal status ("artwork_pending", etc.) **[NEW]**
- **_grip_monday_feedback** - Monday.com updates/feedback
- **_grip_mockup_url** - URL to mockup files

## Computed Fields:
- **grip_status_label** - Human-readable status ("Artwork Pending", "In Production", etc.)
- **grip_status_code** - Raw status code for programmatic use

## Post Creation Workflow:

### Previous (Before Update):
```
Form Submission → Immediate Post Creation (draft) → Add to Cart → Payment → ???
```

### Current (After Update):
```
Form Submission → Store Data → Add to Cart → Payment Complete → Create Post (publish)
```

## Key Changes in New Build:

1. **Purchase-Based Creation**: Posts only created after successful payment of Custom Grip Design Deposit
2. **Published by Default**: New posts created with 'publish' status (customer has paid)
3. **Order Linking**: All posts linked to specific WooCommerce orders via `_grip_order_id`
4. **Enhanced Form Support**: Support for both original and new form types with color specifications
5. **Better File Tracking**: Separate fields for artwork URL and filename
6. **Audit Trail**: Complete tracking from form submission through order to post creation

## Status Flow:
1. **Form Submitted** → Data stored in cart
2. **Payment Completed** → Post created with status: `publish`
3. **Artwork Status** → `_grip_artwork_status`: "artwork_pending"
4. **Admin Processing** → Various custom statuses via workflow

## Admin Benefits:
- All grip design posts guaranteed to have associated paid orders
- Clear audit trail from order to design post
- No cleanup needed for unpaid/abandoned designs
- Order notes track post creation automatically 