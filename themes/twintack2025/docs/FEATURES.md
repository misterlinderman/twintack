# TwinTack Theme Features

This document catalogs the features of the TwinTack theme, serving as both documentation and a changelog to track feature additions and modifications.

## Core Features

### Unified Login System

**Description**: A centralized login experience for all user types (regular customers, wholesale buyers, and affiliate partners) with role-based redirects and user type selection.

**Implementation**:
- Custom login page template (`page-login.php`)
- Login page template (`templates/template-login.php`)
- Login form template part (`template-parts/account/login-form.php`)
- Registration form template part (`template-parts/account/register-form.php`)
- User type selector component (`template-parts/account/user-type-selector.php`)

**Status**: Implemented

**Usage**:
- The system automatically redirects all login attempts to the unified login page
- Users are redirected to appropriate dashboards based on their roles after login
- New users can select their account type during registration

---

### Grip Configurator Enhancement

**Description**: Adds user-friendly registration and login options to the custom grip configurator page for users who aren't logged in. Integrates seamlessly with the unified login system to convert visitors to registered users.

**Implementation**:
- Grip configurator enhancement class (`inc/class-twintack-grip-configurator.php`)
- Automatic page detection and enhancement
- Manual shortcode options for precise control
- Mobile-responsive design with modern styling

**Status**: Implemented

**Usage**:
- Automatically enhances grip configurator pages for non-logged-in users
- Provides two shortcodes: `[twintack_grip_login_prompt]` and `[twintack_account_required]`
- Shows clear benefits of account creation (design tracking, order history, etc.)
- Links directly to unified login/registration system

**Features**:
- Automatic detection of grip configurator pages
- Registration benefits explanation with compelling copy
- Mobile-responsive button layout
- Integration with existing unified login system
- Admin hints for shortcode usage

---

### Dual-Category Product Display

**Description**: Products are displayed with category-specific information for both baseball and fishing contexts.

**Implementation**:
- Category-specific templates (`templates/category-baseball.php`, `templates/category-fishing.php`)
- Custom variation display class (`inc/class-variation-display.php`)
- Category customizer for admin settings (`inc/class-category-customizer.php`)

**Status**: Implemented

---

### Custom Product Forms

**Description**: Specialized forms for product customization and configuration.

**Implementation**:
- Custom product forms class (`inc/class-twintack-product-forms.php`)
- Grip form template (`templates/template-gripform.php`)

**Status**: Implemented

---

### Role-Based Pricing

**Description**: Different pricing structures based on user roles.

**Implementation**:
- Role pricing class (`inc/class-twintack-role-pricing.php`)

**Status**: Implemented

---

### Team Member Showcase

**Description**: Display of team members with custom fields and formatting.

**Implementation**:
- Team member template (`template-team.php`)
- Team member content template (`content-team-member.php`)
- Team member class (`inc/team/class-team-member.php`)

**Status**: Implemented

---

### Marquee Component

**Description**: Scrolling marquee display for announcements or featured content.

**Implementation**:
- Marquee configuration class (`inc/marquee/class-marquee-configuration.php`)
- Marquee template parts (`template-parts/marquee/`)

**Status**: Implemented

---

### Custom Header Configurations

**Description**: Flexible header layouts and configurations.

**Implementation**:
- Header configuration class (`class-header-configuration.php`)
- Header template parts (`template-parts/header/`)

**Status**: Implemented

---

### Header logo and menu contrast detection

**Description**: Sampled the page color behind the header controls and set `data-contrast` on `.site-header` so the logo and hamburger icon switched between light and dark for readability. Built for a transparent or gradient masthead sitting over the marquee and page content.

**Status**: Removed 09/25/2026. The masthead (`header#masthead` in `css/components/_demo-style-adjustments.css`) is a solid black bar, so the logo and menu icon stay white. Marquee carousel-dot contrast in `js/header.js` is unchanged.

**Open tray (same date):** While the menu is open, the logo is hidden and the hamburger becomes the close X at the top of the tray. Tray content is inset `4.75rem` on the left so the Custom Grips button does not cover that X. Top padding stays `6rem` at every width. From 768px up the tray is 340px wide; from 481px to 767px it is 320px; at 480px and below it is full width.

**Deploy:** FTP `css/components/_header.css`, `css/components/_demo-style-adjustments.css`, `js/navigation.js`, and `functions.php`. `navigation.js` is versioned with `filemtime`. `_header.css` is loaded with `@import` from `main.css`, so browsers can keep a cached copy until a hard refresh.

**Restore if the header becomes transparent or gradient again:**

1. In `js/navigation.js`, inside the `DOMContentLoaded` handler that binds `.nav-toggle`, sample the background under `.header-controls` with `document.elementsFromPoint()`, skipping elements inside `.site-header`. Convert that color with YIQ (`((r * 299) + (g * 587) + (b * 114)) / 1000`; `>= 128` is `light`, otherwise `dark`) and set `header.dataset.contrast`. Run it on load. On open, force `data-contrast="dark"` (menu panel is black). On close, run the sample again inside `requestAnimationFrame`.
2. In `css/components/_header.css`, replace the fixed white logo fill and `.toggle-line` background with:
   - `.site-header[data-contrast="light"]` — black logo fill and black toggle lines
   - `.site-header[data-contrast="dark"]` — white logo fill and white toggle lines
   A later override in the same file had flipped those to white / light grey. Use the black-on-light, white-on-dark pair if restoring.

---

### Flexible Content System

**Description**: Modular content blocks that can be arranged in different layouts.

**Implementation**:
- Flexible content template (`template-flexible.php`)
- Flexible content template part (`content-flexible.php`)

**Status**: Implemented

---

### Enhanced Product Gallery

**Description**: Custom product gallery with advanced features.

**Implementation**:
- Product gallery scripts (`custom_product_gallery_scripts()` in functions.php)
- Product carousel scripts (`enqueue_product_carousel_scripts()` in functions.php)
- Lightbox integration (`twintack_enqueue_lightbox_scripts()` in functions.php)

**Status**: Implemented

---

### SVG Support

**Description**: Support for SVG graphics throughout the theme.

**Implementation**:
- SVG support functions (`svg-support.php`)
- Custom logo SVG function (`custom_logo_svg()` in functions.php)

**Status**: Implemented

---

## Plugin Features

These features are implemented as standalone plugins rather than within the theme. They are documented here for project-wide visibility.

### Amazon Tracking Bridge

**Description**: Bridges Shippo tracking data from WooCommerce order notes to WP-Lister Amazon fulfillment feeds. Solves a gap where Shippo writes tracking numbers only to order notes while WP-Lister reads only from order meta keys, causing Amazon orders to be flagged with "Invalid Tracking."

**Implementation**:
- Bridge plugin (`plugins/twintack-amazon-tracking-bridge/twintack-amazon-tracking-bridge.php`)
- Real-time order note interception via `woocommerce_order_note_added` hook
- Filter fallbacks via `wpla_custom_tracking_number` and related WP-Lister hooks

**Status**: Implemented (v1.1.0, deployed March 2026)

**Context**: 29 Amazon orders from Feb 10 – Mar 14, 2026 were affected before the fix was deployed. These could not be retroactively corrected as they had passed Amazon's delivery window. The bridge plugin prevents this issue for all future orders. Full details in `plugins/twintack-amazon-tracking-bridge/README.md`.

---

## Feature Changelog

### [Date: 09/25/2026] - Header logo and menu contrast detection removed

- Masthead background is solid black; the transparent gradient is no longer needed
- Removed background sampling that switched the header logo and hamburger between light and dark
- Logo and menu icon are fixed white on the solid black masthead
- Open tray hides the logo and uses the hamburger as the close X; tray content is inset so Custom Grips does not cover it
- Tray top padding stays 6rem below 768px; the tray is full width at 480px and below
- Restoration notes are under "Header logo and menu contrast detection" above
- Marquee carousel-dot contrast detection was left in place

### [Date: 03/18/2026] - Amazon Tracking Bridge Plugin Added

- Identified root cause of Amazon tracking sync failure: Shippo writes tracking to order notes only, not to meta keys that WP-Lister reads
- Created `twintack-amazon-tracking-bridge` plugin to intercept Shippo order notes in real-time and write tracking data to WP-Lister meta keys
- 29 historical orders (Feb 10 – Mar 14, 2026) could not be backfilled as they had passed Amazon's delivery window
- Plugin prevents all future orders from having this issue

### [Date: 03/01/2024] - Unified Login System Added

- Implemented unified login system for all user types
- Added user type selection for login and registration
- Implemented role-based redirects after login
- Added custom login page template and form components

### [Date: MM/DD/YYYY] - Initial Release

- Implemented dual-category product display
- Added custom product forms
- Implemented role-based pricing
- Added team member showcase
- Implemented marquee component
- Added custom header configurations
- Implemented flexible content system
- Added enhanced product gallery
- Added SVG support

### [Template for Future Updates]

**[Date: MM/DD/YYYY] - [Version X.X.X]**

- [Added/Modified/Removed] [Feature name]
- [Added/Modified/Removed] [Feature name]

## Planned Features

This section lists features that are planned for future implementation:

1. **[Feature Name]**
   - Description: [Brief description]
   - Priority: [High/Medium/Low]
   - Target implementation date: [MM/DD/YYYY]

2. **[Feature Name]**
   - Description: [Brief description]
   - Priority: [High/Medium/Low]
   - Target implementation date: [MM/DD/YYYY]

---

*This document serves as a living record of the TwinTack theme's features. When implementing new features or modifying existing ones, please update this document accordingly.* 