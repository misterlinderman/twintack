# TwinTack Marketing Plugin

A comprehensive marketing plugin for TwinTack WordPress/WooCommerce site that provides content marketing features including product videos, announcement bars, color schemes, featured products, and landing page templates.

## Features

### 1. Product Page Video Content
- Add marketing videos to product pages below the initial product presentation
- Supports YouTube, Vimeo, or custom embed code
- Configured via product edit screen meta box

### 2. Website-wide Announcement Bar
- Optional announcement bar displayed at the top of all pages
- Customizable text, colors, and link
- Dismissible option for users
- Configured via Settings > Announcement Bar

### 3. Product Page Color Scheme Toggle
- Toggle individual product pages to an alternate color scheme
- Set via product edit screen meta box
- Adds `twintack-product-color-alternate` class to body for custom styling

### 4. Featured Products Management
- Curate featured products for homepage and landing pages
- Drag-and-drop reordering
- Managed via Marketing > Featured Products

### 5. Banner Blocks
- Create banner blocks with two layouts:
  - **Full Width**: Desktop and mobile images with optional clickthrough link
  - **50/50**: Image on one side, text and CTA on the other
- Managed via Marketing > Banner Blocks

### 6. Alternate Homepage Template
- **Template Name**: Marketing Homepage
- Includes banner blocks and featured products
- Assign to any page via Page Attributes > Template

### 7. Landing Page Template
- **Template Name**: Marketing Landing Page
- Includes:
  - Hero section with desktop/mobile images
  - Banner blocks
  - Featured products
  - Page content
- Perfect for targeted marketing campaigns

## Installation

1. Upload the `twintack-marketing` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure WooCommerce is installed and active

## Usage

### Product Videos

1. Edit a product in WooCommerce
2. Find the "Marketing Video" meta box
3. Select video type (YouTube, Vimeo, or Embed)
4. Enter video URL or embed code
5. Save the product

The video will appear below the product summary on the product page.

### Announcement Bar

1. Go to Settings > Announcement Bar
2. Enable the announcement bar
3. Enter announcement text
4. Optionally add a link and link text
5. Customize colors
6. Enable dismissible option if desired
7. Save changes

### Product Color Scheme

1. Edit a product in WooCommerce
2. Find the "Color Scheme" meta box in the sidebar
3. Select "Alternate" from the dropdown
4. Save the product

Add custom CSS for `.twintack-product-color-alternate` in your theme to style alternate color scheme pages.

### Featured Products

1. Go to Marketing > Featured Products
2. Select context (Homepage or Landing Pages)
3. Click "Add Product" to select products
4. Drag to reorder
5. Click "Save Featured Products"

Display featured products using the shortcode:
```
[twintack_featured_products context="homepage" columns="4" limit="8" title="Featured Products"]
```

### Banner Blocks

1. Go to Marketing > Banner Blocks
2. Select context (Homepage or Landing Pages)
3. Click "Add Banner Block"
4. Choose layout (Full Width or 50/50)
5. Upload images (desktop and optional mobile)
6. For 50/50 layout: Add title, text, and CTA
7. For Full Width: Optionally add clickthrough link
8. Click "Save Block"

Display banner blocks using the shortcode:
```
[twintack_banner_block context="homepage"]
```

### Using Templates

#### Marketing Homepage Template

1. Create or edit a page
2. In Page Attributes, select "Marketing Homepage" template
3. The page will automatically display banner blocks and featured products configured for "homepage" context

#### Marketing Landing Page Template

1. Create or edit a page
2. In Page Attributes, select "Marketing Landing Page" template
3. Edit the page to configure hero section:
   - Hero Title (optional override)
   - Hero Subtitle
   - Desktop Hero Image
   - Mobile Hero Image (optional)
4. Add page content
5. Banner blocks and featured products configured for "landing" context will display automatically

## Shortcodes

### Featured Products
```
[twintack_featured_products context="homepage" columns="4" limit="8" title="Featured Products"]
```

**Parameters:**
- `context`: Context identifier (default: "homepage")
- `columns`: Number of columns (default: "4")
- `limit`: Maximum products to display (default: "8")
- `title`: Section title (default: "Featured Products")

### Banner Blocks
```
[twintack_banner_block context="homepage" id=""]
```

**Parameters:**
- `context`: Context identifier (default: "homepage")
- `id`: Specific banner block ID (optional, displays all if omitted)

## Customization

### Alternate Color Scheme Styling

Add CSS to your theme for the alternate color scheme:

```css
body.twintack-product-color-alternate .woocommerce .product {
    /* Your alternate styles here */
}
```

### Banner Block Styling

Banner blocks use the following CSS classes:
- `.twintack-banner-block` - Main wrapper
- `.twintack-banner-fullwidth` - Full width layout
- `.twintack-banner-50-50` - 50/50 layout
- `.twintack-banner-image-side` - Image container (50/50)
- `.twintack-banner-text-side` - Text container (50/50)

Customize these in your theme's CSS or via the plugin's CSS file.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- WooCommerce (active)

## Support

For issues or feature requests, contact the TwinTack development team.

## Version

1.0.0

