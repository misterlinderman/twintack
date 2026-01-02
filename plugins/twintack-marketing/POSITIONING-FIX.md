# Bundle Counter & Announcement Bar - Positioning Fix

## Version 1.1.1 - January 2, 2026

This update fixes z-index and positioning issues where the bundle counter and announcement bar were conflicting with the site header, logo, and navigation.

---

## 🔧 What Was Fixed

### Problem
- Bundle counter and announcement bar were appearing **behind** the site logo and navigation
- Z-index conflicts causing overlap issues
- No proper positioning relative to WordPress admin bar
- When both bars were active, positioning was incorrect

### Solution
Both bars are now:
- **Fixed positioned** at the top of the viewport
- Set with **z-index: 10000+** (above site header's z-index: 1000)
- Automatically adjust for **WordPress admin bar** when logged in
- **Stack properly** when both are active
- **Push site header down** appropriately to prevent overlap

---

## 📐 Positioning Logic

### When Only Announcement Bar is Active
```
Top of viewport:
├─ Admin Bar (if logged in): 32px (desktop) / 46px (mobile)
├─ Announcement Bar: 48px height
└─ Site Header: pushed down accordingly
```

**Site Header Top Position:**
- No admin bar: `48px`
- With admin bar (desktop): `80px` (32 + 48)
- With admin bar (mobile): `94px` (46 + 48)

### When Only Bundle Counter is Active
```
Top of viewport:
├─ Admin Bar (if logged in): 32px (desktop) / 46px (mobile)
├─ Bundle Counter: 60px height
└─ Site Header: pushed down accordingly
```

**Site Header Top Position:**
- No admin bar: `60px`
- With admin bar (desktop): `92px` (32 + 60)
- With admin bar (mobile): `106px` (46 + 60)

### When Both Bars are Active
```
Top of viewport:
├─ Admin Bar (if logged in): 32px (desktop) / 46px (mobile)
├─ Announcement Bar: 48px height
├─ Bundle Counter: 60px height (positioned below announcement)
└─ Site Header: pushed down accordingly
```

**Bundle Counter Top Position:**
- No admin bar: `48px` (below announcement)
- With admin bar (desktop): `80px` (32 + 48)
- With admin bar (mobile): `94px` (46 + 48)

**Site Header Top Position:**
- No admin bar: `108px` (48 + 60)
- With admin bar (desktop): `140px` (32 + 48 + 60)
- With admin bar (mobile): `154px` (46 + 48 + 60)

---

## 🎨 Z-Index Hierarchy

```
Layer Stack (highest to lowest):
├─ 10001: Announcement Bar (topmost)
├─ 10000: Bundle Counter (just below announcement)
├─  1001: Site Header Controls (logo, cart, account icons)
├─  1000: Site Header
├─   999: Navigation Panels
└─     1: Page Content
```

---

## 🔄 Dynamic Adjustments

### When Announcement Bar is Dismissed

The JavaScript automatically:
1. Removes the announcement bar from DOM
2. Repositions bundle counter to top (accounting for admin bar)
3. Adjusts site header position to close the gap
4. Stores dismissal state in localStorage

**Example Flow:**
```javascript
// Before dismissal
Admin Bar: 32px
Announcement: 48px (top: 32px)
Bundle Counter: 60px (top: 80px)
Site Header: top: 140px

// After dismissal
Admin Bar: 32px
Bundle Counter: 60px (top: 32px) ← moves up
Site Header: top: 92px ← moves up
```

---

## 📱 Responsive Behavior

### Desktop (> 782px)
- Admin bar height: `32px`
- Full-width bars with optimal spacing
- Large, readable text

### Mobile (≤ 782px)
- Admin bar height: `46px`
- Compact padding for mobile viewport
- Smaller but readable text
- Progress bar scales to full width

---

## 💻 Technical Implementation

### CSS Changes

**Fixed Positioning:**
```css
.twintack-announcement-bar,
.twintack-bundle-counter {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 10001; /* announcement */
    z-index: 10000; /* bundle counter */
}
```

**Admin Bar Support:**
```css
.admin-bar .twintack-announcement-bar {
    top: 32px;
}

@media screen and (max-width: 782px) {
    .admin-bar .twintack-announcement-bar {
        top: 46px;
    }
}
```

### PHP Changes

**Dynamic Top Position Calculation:**
```php
// In bundle counter class
$announcement_active = get_option('twintack_announcement_enabled') && 
                       !empty(get_option('twintack_announcement_text'));

$top_offset = $announcement_active ? 48 : 0;
```

**Dynamic Site Header Push:**
```php
// Inline styles added to push site header
<style>
    body:not(.admin-bar) .site-header {
        top: <?php echo $header_offset; ?>px !important;
    }
</style>
```

### JavaScript Changes

**Dismissal Handler:**
```javascript
function adjustBarsPosition() {
    var $bundleCounter = $('#twintack-bundle-counter');
    var $siteHeader = $('.site-header');
    var isAdminBar = $('body').hasClass('admin-bar');
    
    // Calculate and apply new positions
    // ...
}
```

---

## ✅ Testing Checklist

When testing the positioning fix:

- [ ] **No Admin Bar, No Bars Active**
  - Site header at default position (top: 0)

- [ ] **No Admin Bar, Announcement Only**
  - Announcement at top: 0
  - Site header pushed down: 48px
  - No overlaps

- [ ] **No Admin Bar, Bundle Counter Only**
  - Bundle counter at top: 0
  - Site header pushed down: 60px
  - No overlaps

- [ ] **No Admin Bar, Both Bars Active**
  - Announcement at top: 0
  - Bundle counter at top: 48px
  - Site header pushed down: 108px
  - No overlaps

- [ ] **With Admin Bar (Desktop), Announcement Only**
  - Admin bar: 0-32px
  - Announcement: 32px
  - Site header: 80px

- [ ] **With Admin Bar (Desktop), Bundle Counter Only**
  - Admin bar: 0-32px
  - Bundle counter: 32px
  - Site header: 92px

- [ ] **With Admin Bar (Desktop), Both Bars**
  - Admin bar: 0-32px
  - Announcement: 32px
  - Bundle counter: 80px
  - Site header: 140px

- [ ] **With Admin Bar (Mobile), All Scenarios**
  - Test all above with 46px admin bar height

- [ ] **Dismissing Announcement Bar**
  - Bundle counter moves up smoothly
  - Site header adjusts position
  - No layout shift issues
  - Works with and without admin bar

- [ ] **Scrolling Behavior**
  - Bars remain fixed at top
  - Site header scrolls normally
  - No z-index issues during scroll

---

## 🐛 Known Issues / Limitations

### None Currently

The positioning system now handles:
- ✅ Single bar (announcement or bundle)
- ✅ Both bars together
- ✅ Admin bar on desktop and mobile
- ✅ Dynamic dismissal of announcement bar
- ✅ Z-index conflicts with site header
- ✅ Responsive design

---

## 🔮 Future Enhancements

Potential improvements:
- Add transition animations when bars are dismissed
- Option to make bundle counter collapsible
- Alternative layout: inline with header (original suggestion)
- Custom positioning per page/template

---

## 📝 Files Modified

**CSS:**
- `/assets/css/marketing.css`
  - Fixed positioning for both bars
  - Z-index hierarchy
  - Admin bar adjustments

**PHP:**
- `/includes/class-marketing-announcement-bar.php`
  - Added inline styles to push site header
  - Added data attributes for height tracking
  
- `/includes/class-marketing-bundle-counter.php`
  - Dynamic top position calculation
  - Detection of announcement bar state
  - Inline styles for header adjustment

**JavaScript:**
- `/assets/js/marketing.js`
  - `adjustBarsPosition()` function
  - Dismissal handler updates
  - Dynamic repositioning logic

---

## 📞 Support

If you encounter positioning issues:

1. **Check browser dev tools** - Inspect z-index and top values
2. **Clear cache** - Browser and WordPress caching
3. **Test logged out** - Admin bar can affect calculations
4. **Check theme conflicts** - Other themes may set header positioning
5. **Verify settings** - Ensure bars are enabled in admin

---

## 🎉 Summary

The bundle counter and announcement bar now work perfectly at the top of the site with proper z-index layering. They:
- Stay fixed at the top of the viewport
- Stack properly when both are active
- Account for WordPress admin bar automatically
- Push the site header down to prevent overlap
- Adjust smoothly when announcement bar is dismissed
- Work on all screen sizes

No more overlap or z-index issues!

