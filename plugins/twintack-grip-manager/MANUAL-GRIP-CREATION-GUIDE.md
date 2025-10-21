# Manual Grip Design Creation Guide

## Version 1.7.00 - Enhanced Admin Interface

This guide explains the new features for manually creating and managing grip design posts in the WordPress admin.

## Overview

The admin interface has been significantly enhanced to provide parity between automatically-created and manually-created grip design posts. You now have three powerful tools:

1. **Gravity Forms Importer** - Import data directly from form submissions
2. **Artwork Upload/URL** - Add artwork via upload or direct URL paste
3. **Complete Field Set** - All metadata fields available for manual entry

## New Features

### 1. Gravity Forms Entry Importer

**Location:** Sidebar meta box "Import from Gravity Forms"

**How to Use:**
1. Enter the Gravity Forms entry ID (e.g., 849)
2. Click "📥 Import Data from Entry"
3. All fields are automatically populated:
   - Customer name and email
   - Team/School name
   - Design type and specifications
   - Quantity
   - Artwork URL and filename
   - Design instructions
   - Color information (for new form)
   - Form entry ID and type
4. Confirm to save

**Benefits:**
- No manual data entry needed
- Ensures data accuracy
- Links grip post to original form submission
- Automatically sets correct form type
- Generates proper post title

**Perfect For:**
- Creating grip posts from orders that failed automatic creation
- Recovering from missing team name errors
- Manual order processing

### 2. Artwork Upload & Management

**Location:** "Artwork Versions" meta box

**Three Ways to Add Artwork:**

#### Option A: Paste URL
1. Get the artwork URL from:
   - Gravity Forms entry (copy file URL)
   - WordPress Media Library
   - WooCommerce order item meta
2. Paste into "Artwork URL" field
3. Enter filename (or it will auto-extract from URL)
4. Save

#### Option B: Upload File
1. Click "📁 Upload Artwork File" button
2. Select file from your computer or Media Library
3. File URL and name are automatically populated
4. Preview updates immediately
5. Save

#### Option C: Manual Entry
1. Enter URL in "Artwork URL" field
2. Enter filename in "Filename" field
3. Save

**Features:**
- Live preview of artwork
- Automatic filename extraction from URL
- WordPress Media Library integration
- Support for Gravity Forms upload URLs
- Validates URL format

### 3. Complete Field Management

#### Grip Design Details Meta Box

**Customer Information:**
- Customer Name (required)
- Customer Email
- Team/School Name (required)

**Design Specifications:**
- Design Type
- Quantity

**Pattern & Color Details** (for new form submissions):
- Pattern (e.g., "Solid Color", "2-Color Fade", "3-Color Fade")
- Primary Color
- Secondary Color (if applicable)
- Tertiary Color (if applicable)

**Design Instructions:**
- Multi-line text area for customer feedback/instructions

#### Order & System Information Meta Box

**Location:** Sidebar

**Gravity Forms Integration:**
- Form Entry ID (with link to view entry)
- Form Type selector (New Form or Original Form)

**WooCommerce Integration:**
- Order ID (with link to view order)
- Order Item ID

**System Metadata:**
- Form submission timestamp
- Manual creation indicator (if applicable)

## Step-by-Step: Manual Creation Workflows

### Workflow 1: Import from Gravity Forms Entry

**Scenario:** Order failed to create grip post automatically

1. Go to **Grip Designs → Add New**
2. In sidebar, find "Import from Gravity Forms"
3. Enter the entry ID (get from WooCommerce order or Gravity Forms)
4. Click "📥 Import Data from Entry"
5. Review imported data
6. Confirm save when prompted
7. Done! ✅

**Time:** ~30 seconds

### Workflow 2: Create from Scratch

**Scenario:** Creating a grip post without an existing form entry

1. Go to **Grip Designs → Add New**
2. Enter **Customer Name** (required)
3. Enter **Team/School Name** (required) - used for post title
4. Fill in **Design Type** and **Quantity**
5. Add **Artwork**:
   - Option A: Click "📁 Upload Artwork File"
   - Option B: Paste artwork URL
6. Add **Design Instructions** if any
7. Click "Publish"

**Time:** ~2-3 minutes

### Workflow 3: Recover from Failed Order

**Scenario:** Order exists with all meta data but no grip post created

1. Go to **WooCommerce → Orders**
2. Find the order
3. In order items, locate Custom Grip Design Deposit
4. Click "View meta data" to see hidden fields
5. Note the `_grip_form_entry_id` value
6. Go to **Grip Designs → Add New**
7. Use Gravity Forms Importer with that entry ID
8. In sidebar, manually enter **Order ID** and **Order Item ID**
9. Save

**Time:** ~1-2 minutes

### Workflow 4: Edit Existing Grip Design

**Scenario:** Need to update artwork or information

1. Go to **Grip Designs → All Grip Designs**
2. Click on the grip design to edit
3. Update any fields as needed
4. For artwork:
   - Replace URL, or
   - Upload new file
5. Click "Update"

**Time:** ~1 minute

## Field Requirements

### Absolutely Required
- **Customer Name** - For identification
- **Team/School Name** - For post title and production

### Highly Recommended
- **Design Type** - Production specifications
- **Quantity** - Order fulfillment
- **Artwork URL** - Visual reference
- **Form Entry ID** - Links to original submission

### Optional but Helpful
- Customer Email - Communication
- Design Instructions - Special requests
- Order ID - Links to purchase
- Color details - For new form types

## Tips & Best Practices

### For Manual Creation

1. **Always use Gravity Forms Importer when possible**
   - Most accurate
   - Fastest method
   - Complete data set

2. **Team Name is Critical**
   - Used in post title
   - Required for Monday.com
   - Cannot be empty

3. **Artwork URL Format**
   - Must be complete URL
   - Should start with `https://`
   - Gravity Forms format: `https://twintack.com/wp-content/uploads/gravity_forms/...`
   - Media Library format: `https://twintack.com/wp-content/uploads/YYYY/MM/filename.jpg`

4. **Linking to Orders**
   - Add Order ID for tracking
   - Helps with customer service
   - Maintains audit trail

### For Troubleshooting Failed Orders

1. **Check Order Status First**
   - Must be "Processing" or "Completed"
   - Check order notes for status history

2. **Verify Form Entry Exists**
   - Go to Gravity Forms → Entries
   - Search by customer email or date
   - Confirm entry has all data

3. **Missing Team Name**
   - Most common failure cause
   - Check form entry first
   - If missing, need to get from customer
   - Now required in form to prevent this

4. **Use Importer for Consistency**
   - Ensures proper field mapping
   - Matches automatic creation exactly
   - Reduces human error

## Troubleshooting

### "Entry not found" Error

**Cause:** Invalid entry ID or entry was deleted

**Solution:**
- Double-check entry ID
- Go to Gravity Forms → Entries to verify
- Try searching for entry by customer email

### "Entry is missing team/school name" Error

**Cause:** Form entry doesn't have team name field filled

**Solution:**
- Contact customer for team name
- Manually enter in "Team/School Name" field
- Don't use importer; fill fields manually instead

### Artwork Not Showing

**Cause:** Invalid URL or file doesn't exist

**Solution:**
- Check if URL is accessible in browser
- Try re-uploading file
- For Gravity Forms uploads, check if form entry still exists
- URL must be complete (include https://)

### Import Button Does Nothing

**Cause:** Gravity Forms not active or JavaScript error

**Solution:**
- Verify Gravity Forms is active
- Check browser console for errors
- Try refreshing the page
- Clear browser cache

## Comparison: Automatic vs. Manual

| Feature | Automatic Creation | Manual Creation w/ Importer | Manual Creation from Scratch |
|---------|-------------------|----------------------------|------------------------------|
| **Speed** | Instant | ~30 seconds | ~2-3 minutes |
| **Data Accuracy** | 100% | 100% | Depends on user |
| **Artwork** | Automatic | Automatic | Manual upload/paste |
| **Order Linking** | Automatic | Manual entry | Manual entry |
| **Post Title** | Auto-generated | Auto-generated | Manual or auto |
| **Webhook Trigger** | Yes | No (requires separate trigger) | No (requires separate trigger) |

## Advanced Features

### Editing Automatically-Created Posts

You can edit any field in automatically-created grip posts:
- Update customer information
- Change artwork
- Modify quantities
- Add additional notes

All your changes are preserved and won't be overwritten.

### Bulk Operations

For multiple failed orders:
1. Create a list of entry IDs
2. For each entry:
   - Create new grip design
   - Use importer
   - Note order ID in system meta box
3. Save each post

### Integration with Existing Workflow

Manual posts work identically to automatic ones:
- Same artwork status workflow
- Same customer feedback system
- Same Monday.com integration (if manually triggered)
- Same display in customer dashboard

## Security & Permissions

- Only users with `edit_posts` capability can create grip designs
- Only administrators can import from Gravity Forms
- AJAX requests are nonce-protected
- File uploads go through WordPress media security

## Common Questions

**Q: Will manually created posts appear in customer dashboards?**
A: Yes, as long as the customer email matches their account.

**Q: Can I trigger Make.com webhook after manual creation?**
A: Not automatically. You'll need to trigger it separately through your Make.com scenario or Monday.com integration.

**Q: What if I enter the wrong entry ID?**
A: The importer validates the entry exists. If wrong entry is imported, simply import the correct one - it will overwrite the data.

**Q: Can I create grip posts without any form submission?**
A: Yes, manually enter all required fields. Leave form entry ID blank.

**Q: Do I need to match the order ID?**
A: For tracking purposes, yes. But it's not required for the grip post to function.

## Version History

### Version 1.7.00
- Added Gravity Forms Entry Importer
- Added artwork upload capability
- Added artwork URL paste field
- Added order linking fields
- Added form type selector
- Enhanced field organization
- Improved save handling
- Added media library integration

### Previous Versions
- Basic field management only
- No artwork upload capability
- No form integration
- Limited metadata fields

## Support

For issues with manual grip creation:
1. Check this guide first
2. Verify Gravity Forms and WooCommerce are active
3. Check browser console for JavaScript errors
4. Review WordPress debug log
5. Contact development team with specific error messages

---

**Remember:** Making "Team/School Name" required in your Gravity Form (which you've now done) will prevent most issues that required manual creation in the first place!

