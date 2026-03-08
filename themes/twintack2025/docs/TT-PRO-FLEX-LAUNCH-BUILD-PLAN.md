# TT Pro Flex Launch — Build Plan

**Created:** March 5, 2026  
**Purpose:** Two-stage implementation plan for TT Pro Flex product launch  
**Risk Strategy:** Stage 1 is additive-only (no existing files modified). Stage 2 modifies existing navigation with a feature flag for safe rollout.

---

## Pre-Work: WooCommerce Product Category Setup

Before either stage, the following WooCommerce categories should be created in the WordPress admin:

1. **TT Pro** — child category of `Bat Grips` (slug: `tt-pro`)
2. **TT Pro Flex** — child category of `Bat Grips` (slug: `tt-pro-flex`)

Existing products should be assigned to the `TT Pro` subcategory. New TT Pro Flex products get assigned to `TT Pro Flex`. This is a WordPress admin task — no code changes required.

**Category hierarchy:**
```
Bat Grips (existing, slug: baseball)
├── TT Pro (new subcategory)
└── TT Pro Flex (new subcategory)
Accessories (existing, slug: accessory)
```

---

## Stage 1: Marketing Target Page Template

**Risk Level:** None — all new files, no modifications to existing code  
**Deployment:** Upload new files via FTP → assign template to a page → test on draft/private page before publishing

### What Gets Built

A new page template called **"Marketing Target Page"** designed for digital marketing campaign landing pages. It extends the existing landing page pattern but adds:

- **Video-capable hero** (background video, full-width video, or static image)
- **Product grid content block** (displays products from a specific WooCommerce category)
- **Video feature blocks** in the content area (product demo videos with text)
- **Standard WordPress content area** (for any additional content)
- **Banner blocks and featured products** from the existing marketing plugin

### Files to Create

#### 1. Theme Template File

**Path:** `themes/twintack2025/templates/template-target-page.php`

**Template Name:** Marketing Target Page

**Structure:**
```
┌─────────────────────────────────────────┐
│  HERO SECTION                           │
│  (Video background / Full video / Image)│
│  Optional: Title + Subtitle + CTA       │
│  Responsive: Desktop video, Mobile img  │
└─────────────────────────────────────────┘
┌─────────────────────────────────────────┐
│  PAGE CONTENT (WordPress Editor)        │
│  Standard the_content() output          │
└─────────────────────────────────────────┘
┌─────────────────────────────────────────┐
│  PRODUCT GRID BLOCK                     │
│  Filterable by WooCommerce category     │
│  Optional section heading               │
│  Uses existing product card markup      │
└─────────────────────────────────────────┘
┌─────────────────────────────────────────┐
│  VIDEO FEATURE BLOCKS (repeatable)      │
│  Layout: video + text side-by-side      │
│  Alternating left/right alignment       │
│  Or full-width video                    │
└─────────────────────────────────────────┘
┌─────────────────────────────────────────┐
│  BANNER BLOCKS (existing shortcode)     │
└─────────────────────────────────────────┘
┌─────────────────────────────────────────┐
│  FEATURED PRODUCTS (existing shortcode) │
└─────────────────────────────────────────┘
```

#### 2. Plugin — Meta Box Class for Target Page Admin Fields

**Path:** `plugins/twintack-marketing/includes/class-marketing-target-page.php`

Follows the exact same singleton + meta box pattern as `class-marketing-landing-page.php`. Registers a meta box that appears on Pages in the WordPress editor.

**Meta fields:**

| Meta Key | Type | Purpose |
|----------|------|---------|
| `_twintack_target_hero_type` | select | Hero display mode: `video_background`, `video_full`, `image` |
| `_twintack_target_hero_video_desktop` | url | Desktop video file (MP4) |
| `_twintack_target_hero_video_mobile` | url | Mobile video file (MP4, optional) |
| `_twintack_target_hero_image_desktop` | url | Desktop image (or video poster/fallback) |
| `_twintack_target_hero_image_mobile` | url | Mobile image (fallback when no mobile video) |
| `_twintack_target_hero_title` | text | Hero overlay title |
| `_twintack_target_hero_subtitle` | text | Hero overlay subtitle |
| `_twintack_target_hero_cta_text` | text | CTA button text |
| `_twintack_target_hero_cta_url` | url | CTA button destination |
| `_twintack_target_product_category` | select | WooCommerce category for product grid |
| `_twintack_target_product_heading` | text | Product grid section heading |
| `_twintack_target_product_columns` | select | Grid columns (3 or 4) |
| `_twintack_target_product_limit` | number | Max products to display |
| `_twintack_target_video_blocks` | serialized array | Repeatable video feature blocks |

**Video block sub-fields (per block):**

| Field | Type | Purpose |
|-------|------|---------|
| `video_url` | url | MP4 or YouTube/Vimeo embed URL |
| `video_type` | select | `mp4`, `youtube`, `vimeo` |
| `heading` | text | Feature heading |
| `description` | textarea | Feature description text |
| `layout` | select | `video_left`, `video_right`, `full_width` |

#### 3. CSS for Target Page

**Path:** `plugins/twintack-marketing/assets/css/marketing-target-page.css`

Separate stylesheet loaded only when the target page template is active. Covers:

- `.twintack-target-hero` — Hero section with video/image variants
- `.twintack-target-hero-video` — Video container with proper aspect ratios
- `.twintack-target-hero-overlay` — Text overlay positioning on background video
- `.twintack-target-product-grid` — Product grid wrapper with heading
- `.twintack-target-video-block` — Side-by-side video + text feature blocks
- Responsive breakpoints matching existing brand patterns (768px mobile, 1024px tablet)

#### 4. JS for Target Page Admin

**Path:** `plugins/twintack-marketing/assets/js/marketing-target-page-admin.js`

Admin JavaScript for:
- Media library upload buttons (images and video)
- Hero type selector toggle (show/hide relevant fields)
- Video block repeater (add/remove/reorder blocks)
- Preview thumbnails for uploaded media

#### 5. JS for Target Page Frontend (if needed)

**Path:** `plugins/twintack-marketing/assets/js/marketing-target-page.js`

Frontend JavaScript for:
- Video lazy loading / intersection observer (play when in viewport)
- YouTube/Vimeo embed initialization
- Mobile video handling (pause background video on low bandwidth)

### Files to Modify (Plugin Only — Not Theme)

#### 1. Main Plugin File — Include New Class

**File:** `plugins/twintack-marketing/twintack-marketing.php` (or wherever classes are loaded)

**Change:** Add `require_once` for `class-marketing-target-page.php` and instantiate it.

#### 2. Marketing Admin — Add Dashboard Card (Optional)

**File:** `plugins/twintack-marketing/includes/class-marketing-admin.php`

**Change:** Add a "Marketing Target Pages" card to the admin dashboard page, linking to the Pages list filtered by template. This is cosmetic — the template works without it.

### How the Hero Video Works

Three modes, selectable per page:

**1. Video Background (`video_background`)**
```html
<section class="twintack-target-hero hero-video-background">
    <div class="hero-video-wrapper desktop-only">
        <video autoplay muted loop playsinline poster="[desktop_image]">
            <source src="[desktop_video]" type="video/mp4">
        </video>
    </div>
    <picture class="hero-image-wrapper mobile-only">
        <img src="[mobile_image]" alt="[title]" />
    </picture>
    <div class="twintack-target-hero-overlay">
        <div class="container">
            <h1>[title]</h1>
            <p>[subtitle]</p>
            <a href="[cta_url]" class="btn btn-primary">[cta_text]</a>
        </div>
    </div>
</section>
```

**2. Full Video (`video_full`)**
```html
<section class="twintack-target-hero hero-video-full">
    <div class="hero-video-wrapper">
        <video autoplay muted loop playsinline controls poster="[desktop_image]">
            <source src="[desktop_video]" type="video/mp4">
        </video>
    </div>
</section>
```

**3. Static Image (`image`)** — Same as existing landing page hero pattern.

### How the Product Grid Works

Uses WooCommerce's built-in query and the theme's existing product card markup:

```php
$args = array(
    'post_type'      => 'product',
    'posts_per_page' => $limit,
    'tax_query'      => array(
        array(
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $category_slug,
        ),
    ),
);
```

Renders using `wc_get_template_part('content', 'product')` inside a `.products-grid` container, reusing the existing `_product-grid.css` styles.

### How Video Feature Blocks Work

Repeatable content blocks, each containing a video and text. Admin UI allows adding multiple blocks with drag-to-reorder. Each block stores:
- Video source (MP4 upload or embed URL)
- Heading and description
- Layout preference (alternates automatically if set to "auto")

Rendered as sections in the content area between the product grid and banner blocks.

### Deployment Steps (Stage 1)

1. Build all files locally in Cursor
2. Upload via FTP:
   - `themes/twintack2025/templates/template-target-page.php`
   - `plugins/twintack-marketing/includes/class-marketing-target-page.php`
   - `plugins/twintack-marketing/assets/css/marketing-target-page.css`
   - `plugins/twintack-marketing/assets/js/marketing-target-page-admin.js`
   - `plugins/twintack-marketing/assets/js/marketing-target-page.js`
   - Updated `plugins/twintack-marketing/twintack-marketing.php` (add require_once)
   - Optionally updated `plugins/twintack-marketing/includes/class-marketing-admin.php`
3. In WordPress admin:
   - Create a new Page
   - Select "Marketing Target Page" template
   - Configure hero, product grid, and video blocks via the meta box
   - Save as **Draft** or **Private** for testing
   - Preview the page — verify hero, product grid, video blocks render correctly
   - When ready, **Publish** and use the URL in marketing campaigns

### Verification Checklist (Stage 1)

- [ ] Template appears in page template dropdown
- [ ] Meta box appears when template is selected
- [ ] Media uploads work for images and video
- [ ] Video block repeater adds/removes/reorders correctly
- [ ] Hero renders in all three modes (video bg, video full, image)
- [ ] Background video autoplays muted on desktop
- [ ] Mobile falls back to static image
- [ ] Product grid displays correct products from selected category
- [ ] Product grid uses existing card styles (consistent with shop)
- [ ] Video feature blocks render with correct layouts
- [ ] YouTube/Vimeo embeds work (if using external video)
- [ ] Page is responsive (mobile, tablet, desktop)
- [ ] No console errors
- [ ] Existing landing pages and homepage are unaffected

---

## Stage 2: Navigation Tray Update + Feature Flag

**Risk Level:** Medium — modifies `header-base.php` (used on every page)  
**Safety Mechanism:** Feature flag constant controls which navigation renders  
**Deployment:** Upload modified files → verify with flag OFF → flip flag ON when ready

### What Gets Built

The slide-out navigation tray is updated from the current structure:

```
CUSTOM GRIPS
SPORT
  [Baseball icon] BAT GRIPS    [Fishing icon] FISHING
PRODUCT
  Shop All | Bat Grips | Fishing Grips | Accessories
TWINTACK
  Our Story | Technology | Contact | Login/Register
```

To the new structure:

```
CUSTOM GRIPS
MODELS
  [TT Pro icon] TT PRO    [TT Pro Flex icon] TT PRO FLEX
CATALOG
  Bat Grips
    └ TT Pro
    └ TT Pro Flex
  Accessories
TWINTACK
  Our Story | Technology | Contact | Login/Register
```

### Feature Flag Implementation

**Constant defined in `wp-config.php`:**

```php
define('TWINTACK_NEW_NAV', false);
```

The header template checks this constant and renders the appropriate navigation version. When `false`, the current navigation displays exactly as it does today. When `true`, the new Models/Catalog navigation appears.

**Why `wp-config.php`?** It's the simplest toggle — no database options to manage, no admin UI needed. Change one line and the site switches. It can also be changed via FTP without logging into WordPress admin, which is useful in an emergency rollback scenario.

### Files to Modify

#### 1. Header Base Template

**File:** `themes/twintack2025/template-parts/header/header-base.php`

**Change:** Wrap the Sport and Product nav sections in a conditional:

```php
<?php if (defined('TWINTACK_NEW_NAV') && TWINTACK_NEW_NAV) : ?>
    <!-- Models Navigation (New) -->
    <div class="nav-section">
        <h2>MODELS</h2>
        <div class="model-icons">
            <a href="/product-category/baseball/tt-pro/" class="model-icon tt-pro">
                <!-- TT Pro SVG icon (provided by client) -->
                <svg>...</svg>
                <span>TT PRO</span>
            </a>
            <a href="/product-category/baseball/tt-pro-flex/" class="model-icon tt-pro-flex">
                <!-- TT Pro Flex SVG icon (provided by client) -->
                <svg>...</svg>
                <span>TT PRO FLEX</span>
            </a>
        </div>
    </div>

    <!-- Catalog Navigation (New) -->
    <div class="nav-section">
        <h2>CATALOG</h2>
        <nav class="catalog-nav">
            <div class="catalog-group">
                <span class="catalog-label">BAT GRIPS</span>
                <div class="catalog-subnav">
                    <a href="/product-category/baseball/tt-pro/">TT PRO</a>
                    <a href="/product-category/baseball/tt-pro-flex/">TT PRO FLEX</a>
                </div>
            </div>
            <a href="/shop/?product_cat=accessory">ACCESSORIES</a>
        </nav>
    </div>
<?php else : ?>
    <!-- Sport Navigation (Current) -->
    <div class="nav-section">
        <h2>SPORT</h2>
        <!-- ... existing sport icons markup unchanged ... -->
    </div>

    <!-- Product Navigation (Current) -->
    <div class="nav-section">
        <h2>PRODUCT</h2>
        <!-- ... existing product nav markup unchanged ... -->
    </div>
<?php endif; ?>
```

The TwinTack section and Custom Grips link remain untouched.

#### 2. Header CSS

**File:** `themes/twintack2025/css/components/_header.css`

**New styles added (do not remove existing):**

- `.model-icons` — Layout container for model icon links (same grid as `.sport-icons`)
- `.model-icon` — Individual model icon styling (mirrors `.sport-icon`)
- `.model-icon svg` — SVG fill and hover states
- `.catalog-nav` — Catalog section layout
- `.catalog-group` — Parent category group container
- `.catalog-label` — Non-link parent label (e.g., "BAT GRIPS")
- `.catalog-subnav` — Indented child links
- `.catalog-subnav a` — Child link styling (slightly smaller, indented)

Since we're only adding new CSS classes, existing styles remain intact. The old `.sport-icons` and `.product-nav` styles stay in the file — they're simply not rendered when the flag is active.

### Files to Create

#### 1. SVG Icon Files (provided by client)

**Path:** `themes/twintack2025/assets/svg/` (or inline in template)

- `tt-pro-icon.svg`
- `tt-pro-flex-icon.svg`

These will be inlined in the template the same way the baseball and fishing icons are today — inline `<svg>` elements with custom paths.

### Deployment Steps (Stage 2)

1. Build all changes locally in Cursor
2. Upload via FTP:
   - Modified `themes/twintack2025/template-parts/header/header-base.php`
   - Modified `themes/twintack2025/css/components/_header.css`
3. Verify site loads correctly with flag **OFF** (or not defined — defaults to current nav)
4. Verify no visual changes to the navigation (current nav still renders)
5. Add `define('TWINTACK_NEW_NAV', true);` to `wp-config.php` via FTP
6. Refresh the site — new navigation should appear
7. Test all nav links work correctly
8. If anything is wrong, change `true` back to `false` in `wp-config.php` → instant rollback

### Verification Checklist (Stage 2)

**Before flipping the flag:**
- [ ] Site loads normally after file upload
- [ ] Current navigation renders identically to before
- [ ] No PHP errors in debug log
- [ ] All existing nav links still work

**After flipping the flag:**
- [ ] "MODELS" section appears with correct heading
- [ ] Model icons display correctly (SVG rendering, hover states)
- [ ] Model icon links go to correct category pages
- [ ] "CATALOG" section appears with correct heading
- [ ] "BAT GRIPS" label appears as a non-link parent
- [ ] TT Pro and TT Pro Flex sub-links are indented correctly
- [ ] Sub-links go to correct category archive pages
- [ ] "ACCESSORIES" link works
- [ ] "TWINTACK" section is unchanged
- [ ] "CUSTOM GRIPS" link is unchanged
- [ ] Navigation works on mobile (slide-out tray)
- [ ] Navigation works on desktop
- [ ] Close/open toggle still functions
- [ ] No layout shifts or spacing issues

**Rollback verification:**
- [ ] Changing flag back to `false` restores original navigation immediately

---

## URL Structure Reference

These are the expected URLs once WooCommerce categories are set up:

| Navigation Item | URL | Notes |
|----------------|-----|-------|
| TT Pro (Models icon) | `/product-category/baseball/tt-pro/` | Child of bat-grips/baseball |
| TT Pro Flex (Models icon) | `/product-category/baseball/tt-pro-flex/` | Child of bat-grips/baseball |
| Bat Grips (Catalog label) | Non-link label | Or optionally `/product-category/baseball/` |
| TT Pro (Catalog sub-link) | `/product-category/baseball/tt-pro/` | Same as model icon |
| TT Pro Flex (Catalog sub-link) | `/product-category/baseball/tt-pro-flex/` | Same as model icon |
| Accessories | `/shop/?product_cat=accessory` | Existing URL |

**Note:** Actual URLs depend on WooCommerce permalink settings and the parent category slug. Verify after creating categories.

---

## Dependencies Between Stages

- **Stage 1 has no dependency on Stage 2.** The target page template works independently.
- **Stage 2 has no dependency on Stage 1.** Navigation changes are independent of the template.
- **Both depend on Pre-Work** (WooCommerce category setup), but only Stage 2 navigation links require the categories to exist. Stage 1's product grid can use any existing category during testing.

The stages can be built and deployed in any order, though the recommended sequence is Stage 1 first (zero risk, immediate marketing value) followed by Stage 2 (coordinated with the product launch date).

---

## Post-Launch Cleanup

After the TT Pro Flex launch is confirmed stable:

1. **Remove the feature flag conditional** from `header-base.php` — keep only the new navigation markup
2. **Remove `TWINTACK_NEW_NAV` constant** from `wp-config.php`
3. **Remove old `.sport-icons` and `.product-nav` CSS** if no longer needed anywhere
4. **Update any other templates** that reference the old sport-based navigation (e.g., `navigation-category.php`, category templates)
5. **Update documentation** in `themes/twintack2025/docs/`

---

*End of Build Plan*
