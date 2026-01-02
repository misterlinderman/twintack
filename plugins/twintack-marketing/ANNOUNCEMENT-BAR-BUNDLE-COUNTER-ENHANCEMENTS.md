# Announcement Bar & Bundle Counter Enhancements

## Version 1.1.0 - January 2, 2026

This update adds significant enhancements to the TwinTack Marketing plugin's announcement bar functionality and introduces a new bundle counter feature for tracking customer progress toward bundle discounts.

---

## 🎯 Feature 1: Page-Specific Announcement Bar Display

### What's New
The standard announcement bar can now be displayed on specific pages instead of requiring it to be shown on all pages or not at all.

### How to Configure

1. Navigate to **Marketing → Announcement Bar** in WordPress admin
2. Check "Enable announcement bar" to turn on the feature
3. Under "Display Options", choose:
   - **Show on all pages** - Traditional behavior (default)
   - **Show on specific pages only** - New option for targeted display
4. When "Show on specific pages only" is selected, a page selector appears showing all pages
5. Check the boxes for pages where you want the announcement bar to display
6. Save settings

### Use Cases
- Show promotional messages only on product pages
- Display special announcements on the homepage only
- Target specific landing pages with relevant messages
- Show different content to customers on checkout vs. shopping pages (requires multiple bars)

### Technical Details
- Uses WordPress `get_queried_object_id()` to detect current page
- Stores selected pages as array in `twintack_announcement_specific_pages` option
- JavaScript toggles page selector visibility based on radio button selection
- Backward compatible with existing announcement bar settings

---

## 🎁 Feature 2: Bundle Counter (Progress Tracker)

### What's New
A completely new feature that displays a real-time progress bar showing customers how close they are to qualifying for bundle discounts. This works **independently** from the standard announcement bar and can be active at the same time.

### How It Works
1. Monitors the customer's cart in real-time
2. Counts products from a specific category (e.g., "Bat Grips")
3. Shows visual progress toward the bundle size (e.g., 3 grips)
4. Updates automatically when items are added/removed from cart
5. Displays congratulations message when bundle is achieved

### How to Configure

1. Navigate to **Marketing → Bundle Counter** in WordPress admin
2. Configure the following settings:

   **Enable Bundle Counter**
   - Check to activate the bundle counter site-wide
   - Works independently from the announcement bar

   **Product Category**
   - Select the WooCommerce product category to track
   - Example: "#24 Bat Grips" for the 3-grip bundle discount
   - Shows category ID for reference

   **Bundle Size**
   - Enter the number of items needed for the bundle
   - Default: 3 (for "Buy 3 Get 15% Off" promotions)

   **Discount Text**
   - Describe the discount customers will receive
   - Examples: "15% off", "$10 off", "Free Shipping"

   **Colors**
   - Background Color - Default: Dark gray (#1a1a1a)
   - Text Color - Default: White (#ffffff)
   - Progress Bar Color - Default: Lime green (#7ed321)

3. Save settings

### Display States

The bundle counter displays different content based on cart status:

**Empty Cart (0 items)**
```
Buy 3 grips and save 15% off!
```

**Partial Progress (1-2 items)**
```
Add 2 more grips to get 15% off!
[Progress bar: 33%] 1 / 3
```

**Bundle Achieved (3+ items)**
```
🎉 Congrats! You qualify for 15% off on your bundle!
```

### Visual Features
- Real-time progress bar that fills as items are added
- Numerical counter showing current / target items
- Pulsing glow animation when bundle is achieved
- Responsive design for mobile and desktop
- Smooth transitions when updating

### Real-Time Updates
The bundle counter automatically updates when:
- Products are added to cart
- Products are removed from cart
- Cart quantities are changed
- WooCommerce fragments refresh
- Page loads with existing cart

### Technical Implementation

**PHP Class**: `TwinTack_Marketing_Bundle_Counter`
- Location: `/includes/class-marketing-bundle-counter.php`
- Hooks into WooCommerce cart
- AJAX endpoint for real-time updates
- Checks product category membership

**JavaScript**
- Location: `/assets/js/marketing.js`
- Function: `initBundleCounter()` and `updateBundleCounter()`
- Listens for WooCommerce cart events
- Updates display without page refresh

**CSS**
- Location: `/assets/css/marketing.css`
- Responsive styles for mobile/desktop
- Progress bar animations
- Qualified state effects

**AJAX Endpoint**
- Action: `get_bundle_count`
- Returns: current count, bundle size, progress %, qualified status
- Works for logged-in and guest users

---

## 💻 Technical Architecture

### File Changes

**New Files:**
- `includes/class-marketing-bundle-counter.php` - Bundle counter class

**Modified Files:**
- `twintack-marketing.php` - Version bump to 1.1.0, initialize bundle counter
- `includes/class-marketing-announcement-bar.php` - Page-specific display logic
- `includes/class-marketing-admin.php` - Bundle counter admin menu and dashboard card
- `assets/css/marketing.css` - Bundle counter styles and responsive design
- `assets/js/marketing.js` - Bundle counter real-time update functionality

### WordPress Standards
All code follows:
- WordPress PHP Coding Standards
- Proper escaping and sanitization
- Security best practices
- Translation-ready strings
- Singleton pattern for classes

### Database Options

**Announcement Bar (New)**
- `twintack_announcement_show_all_pages` - Radio value: '1' or '0'
- `twintack_announcement_specific_pages` - Array of page IDs

**Bundle Counter (New)**
- `twintack_bundle_counter_enabled` - Boolean
- `twintack_bundle_counter_category` - WooCommerce category term ID
- `twintack_bundle_counter_bundle_size` - Integer (default: 3)
- `twintack_bundle_counter_discount_text` - String
- `twintack_bundle_counter_bg_color` - Hex color
- `twintack_bundle_counter_text_color` - Hex color
- `twintack_bundle_counter_progress_color` - Hex color

---

## 🚀 Usage Examples

### Example 1: Bat Grip Bundle Promotion

**Scenario**: You're running a promotion where customers get 15% off when they buy 3 bat grips.

**Configuration**:
1. Go to Marketing → Bundle Counter
2. Enable Bundle Counter: ✓
3. Product Category: "#24 Bat Grips"
4. Bundle Size: 3
5. Discount Text: "15% off"
6. Colors: Use defaults or match your brand
7. Save

**Result**: Customers see their progress as they add bat grips to their cart, encouraging them to add more to reach the discount threshold.

### Example 2: Homepage-Only Announcement

**Scenario**: You want to show a special promotion only on the homepage.

**Configuration**:
1. Go to Marketing → Announcement Bar
2. Enable announcement bar: ✓
3. Display Options: "Show on specific pages only"
4. Select Pages: Check "Home" only
5. Announcement Text: "New products just arrived! Free shipping on orders over $50"
6. Colors: Match your brand
7. Save

**Result**: The announcement bar appears only on the homepage, keeping other pages clean while highlighting the promotion where it matters most.

### Example 3: Both Features Active

**Scenario**: You want both a static announcement and a dynamic bundle counter.

**Configuration**:
1. Set up announcement bar (shows on all pages)
   - Text: "Holiday Sale - Up to 30% Off!"
   
2. Set up bundle counter (tracks bat grips)
   - Category: Bat Grips
   - Bundle Size: 3
   - Discount: "15% off"

**Result**: Customers see the general holiday sale message at the top, with the bundle counter appearing below it, creating a layered promotional strategy.

---

## 📱 Responsive Design

Both features are fully responsive:

**Desktop (> 768px)**
- Full-width display
- Larger text and progress bars
- Optimal spacing for readability

**Mobile (≤ 768px)**
- Compact display
- Smaller but readable text
- Touch-friendly buttons
- Efficient use of screen space

---

## 🔧 Troubleshooting

### Bundle Counter Not Updating
**Issue**: Counter doesn't update when adding items to cart

**Solutions**:
1. Check that WooCommerce is active
2. Verify the correct category is selected in settings
3. Ensure products are properly assigned to the category
4. Clear browser cache and test
5. Check browser console for JavaScript errors

### Announcement Bar Not Showing on Selected Pages
**Issue**: Bar doesn't appear on pages you selected

**Solutions**:
1. Verify "Enable announcement bar" is checked
2. Confirm "Show on specific pages only" is selected
3. Check that the page checkboxes are actually checked
4. Save settings again
5. Clear any page caching

### Bundle Counter Shows Wrong Count
**Issue**: Counter displays incorrect number of items

**Solutions**:
1. Verify the category ID is correct (check in WooCommerce → Products → Categories)
2. Ensure products are in the correct category
3. Check for cart caching issues
4. Test in an incognito/private browser window

---

## 🎨 Customization

### Styling the Bundle Counter

Add custom CSS in your theme or through Customizer:

```css
/* Change bundle counter height */
.twintack-bundle-counter {
    padding: 20px 30px;
}

/* Customize progress bar shape */
.twintack-bundle-counter .progress-track {
    height: 12px;
    border-radius: 6px;
}

/* Change qualified animation */
.twintack-bundle-counter.qualified {
    animation: custom-pulse 1s ease-in-out infinite;
}

@keyframes custom-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.02); }
}
```

### Custom Bundle Messages

To change the bundle counter messages, edit `/includes/class-marketing-bundle-counter.php`:

```php
// Line ~239 - Empty cart message
printf(__('Buy %d grips and save %s!', 'twintack-marketing'), $bundle_size, esc_html($discount_text));

// Line ~227 - Progress message
printf(_n('Add %d more grip to get %s!', 'Add %d more grips to get %s!', $remaining, 'twintack-marketing'), $remaining, esc_html($discount_text));

// Line ~221 - Qualified message
printf(__('Congrats! You qualify for %s on your bundle!', 'twintack-marketing'), esc_html($discount_text));
```

---

## 🔐 Security

Both features follow WordPress security best practices:

- All user inputs are sanitized using WordPress functions
- Output is properly escaped (`esc_html()`, `esc_attr()`, `esc_url()`)
- AJAX endpoints use proper WordPress AJAX handlers
- Nonce verification for sensitive operations
- Capability checks for admin functions (`manage_options`)
- No direct database queries (uses WordPress options API)

---

## 🌍 Translation Ready

All user-facing strings are wrapped in translation functions:

```php
__('Bundle Counter', 'twintack-marketing')
_e('Enable Bundle Counter', 'twintack-marketing')
_n('Add %d more grip', 'Add %d more grips', $count, 'twintack-marketing')
```

To translate, create a `.po` file for `twintack-marketing` text domain.

---

## 📊 Performance

Both features are optimized for performance:

- Bundle counter uses AJAX to avoid full page reloads
- CSS animations use GPU-accelerated properties
- JavaScript debouncing prevents excessive updates
- Minimal database queries
- Caching-friendly implementation

---

## 🔄 Version History

**Version 1.1.0** (January 2, 2026)
- Added page-specific display options for announcement bar
- Introduced new bundle counter feature
- Updated admin dashboard with bundle counter card
- Added real-time cart tracking
- Improved responsive design

**Version 1.0.1** (Previous)
- Basic announcement bar functionality
- Hero carousel
- Featured products
- Banner blocks

---

## 🆘 Support

For issues or questions:

1. Check this documentation first
2. Review the troubleshooting section
3. Check WordPress and WooCommerce versions are up to date
4. Test with other plugins disabled to rule out conflicts
5. Contact the TwinTack development team

---

## 📝 Notes for Developers

### Extending the Bundle Counter

The bundle counter can be extended to support:

- Multiple bundle tiers (buy 5 get 20% off, buy 10 get 30% off)
- Different categories with different bundle sizes
- Custom qualifying logic beyond simple category checks
- Integration with specific coupon codes
- Email notifications when bundle is achieved

### Filter Hooks (Potential Future Addition)

```php
// Allow customization of bundle count logic
apply_filters('twintack_bundle_count', $count, $category_id, $cart);

// Modify bundle counter display
apply_filters('twintack_bundle_counter_html', $html, $count, $bundle_size);

// Override qualifying products
apply_filters('twintack_bundle_qualifying_products', $product_ids, $category_id);
```

---

## ✅ Testing Checklist

Before deploying to production:

- [ ] Test announcement bar on all pages
- [ ] Test announcement bar on specific pages only
- [ ] Verify page selector shows/hides correctly
- [ ] Test bundle counter with 0 items in cart
- [ ] Test bundle counter with 1-2 items in cart
- [ ] Test bundle counter with 3+ items in cart
- [ ] Add item to cart and verify counter updates
- [ ] Remove item from cart and verify counter updates
- [ ] Test on mobile devices
- [ ] Test on tablet devices
- [ ] Test on desktop browsers (Chrome, Firefox, Safari, Edge)
- [ ] Verify colors match brand
- [ ] Check that both features can run simultaneously
- [ ] Test with WooCommerce cart caching enabled
- [ ] Verify performance with large product catalogs

---

## 🎉 Summary

These enhancements provide powerful marketing tools for the TwinTack website:

1. **Flexible Announcement Bar** - Target specific pages with promotional messages
2. **Dynamic Bundle Counter** - Encourage larger purchases with visual progress tracking
3. **Independent Operation** - Use one or both features as needed
4. **Real-Time Updates** - No page refresh required
5. **Mobile Optimized** - Perfect experience on all devices

Both features are production-ready, following WordPress and WooCommerce best practices, and fully compatible with the existing TwinTack Marketing plugin ecosystem.

