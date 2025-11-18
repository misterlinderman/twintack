# TwinTack How-To Videos Plugin

A comprehensive WordPress plugin for managing and displaying sport-specific how-to videos with carousel functionality and video modal integration.

## Features

- **Custom Post Type**: `how_to_video` for managing video content
- **Dual Taxonomy System**: Categorize videos by sport (`video_sport`) AND general categories (`video_category`)
- **Enhanced Admin Interface**: Separate assignment pages for sports and categories
- **Multiple Display Layouts**: Carousel, grid, list, and simple layouts
- **Flexible Frontend Display**: Responsive video displays with modal playback
- **Vimeo Integration**: Seamless Vimeo video embedding
- **Theme Integration**: Extensive hooks and filters for customization
- **Enhanced Shortcode Support**: Display videos with layout and category options
- **Dedicated Video Page**: Template for comprehensive video library pages
- **Responsive Design**: Mobile-friendly layouts across all display types
- **Accessibility**: Full keyboard navigation and ARIA labels

## Installation

1. Upload the plugin files to `/wp-content/plugins/twintack-how-to-videos/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure video sports via **How-To Videos > Video Sports**
4. Add videos via **How-To Videos > Add New**
5. Assign videos to sports via **How-To Videos > Assign to Sports**

## Migration from Theme

If you're migrating from the theme-based implementation:

### Automatic Data Migration

The plugin includes backward compatibility functions that maintain existing video data:

- Existing `how_to_video` posts will continue to work
- Video metadata (`_vimeo_url`, `_video_duration`) is preserved
- Sport assignments remain intact

### Theme Updates Required

Remove these functions from your theme's `functions.php`:

```php
// Remove these lines from functions.php
require get_template_directory() . '/inc/how-to-videos.php';
function twintack_enqueue_video_modal_styles() { ... }
```

Remove these files from your theme:
- `/inc/how-to-videos.php`
- `/template-parts/sport/how-to-videos.php`
- `/template-parts/product/how-to-videos.php`
- `/js/product-video-carousel.js`
- `/js/video-modal.js`
- `/css/components/_how-to-videos.css`

### Update Template Calls

Replace template calls in your theme:

**Old:**
```php
get_template_part('template-parts/sport/how-to-videos');
```

**New:**
```php
// Option 1: Use the plugin function
if (function_exists('twintack_htv_display_videos')) {
    twintack_htv_display_videos();
}

// Option 2: Use the action hook
do_action('twintack_htv_display_videos');

// Option 3: Use the shortcode
echo do_shortcode('[twintack_how_to_videos]');
```

## Usage

### Admin Interface

1. **Add Videos**: Go to **How-To Videos > Add New**
   - Enter video title and description
   - Upload a thumbnail image
   - Add Vimeo URL and duration in the Video Details meta box

2. **Manage Sports**: Go to **How-To Videos > Video Sports**
   - Create sport categories (e.g., "Baseball", "Fishing")
   - Organize videos by sport type

3. **Manage Categories**: Go to **How-To Videos > Video Categories**
   - Create general categories (e.g., "Blog", "Testimonial", "TwinTack", "Tutorial")
   - Organize videos by content type

4. **Assign to Sports**: Go to **How-To Videos > Assign to Sports**
   - Use the grid interface to assign videos to sports
   - Videos can belong to multiple sports

5. **Assign to Categories**: Go to **How-To Videos > Assign to Categories**
   - Use the grid interface to assign videos to categories
   - Videos can belong to multiple categories

### Frontend Display

#### Automatic Display

Videos automatically appear on:
- Product pages (based on product category)
- Sport pages using the sport template
- Pages with the how-to template

#### Manual Display

**Enhanced Shortcode:**
```php
// Basic usage (auto-detects context)
[twintack_how_to_videos]

// Display by sport
[twintack_how_to_videos sport="baseball" limit="3"]

// Display by category
[twintack_how_to_videos category="blog" limit="4"]

// Different layouts
[twintack_how_to_videos category="testimonial" layout="grid"]
[twintack_how_to_videos sport="fishing" layout="simple" limit="2"]
[twintack_how_to_videos category="tutorial" layout="list"]

// Custom title and styling
[twintack_how_to_videos category="twintack" title="Company Videos" show_title="true" class="my-videos"]

// Hide title
[twintack_how_to_videos sport="baseball" show_title="false"]
```

**Available Layouts:**
- `carousel` - Interactive carousel with navigation (default)
- `grid` - Responsive grid layout (best for 4+ videos)
- `simple` - Clean flex layout (best for 1-3 videos)
- `list` - Vertical list with thumbnails and content

**PHP Function:**
```php
// Legacy format (still supported)
twintack_htv_display_videos('baseball', 5);

// New enhanced format
twintack_htv_display_videos(array(
    'category' => 'blog',
    'limit' => 6,
    'layout' => 'grid',
    'title' => 'Blog Videos'
));

// Display by category with simple layout
twintack_htv_display_category_videos('testimonial', 3, 'simple');
```

**Action Hook:**
```php
// Legacy format
do_action('twintack_htv_display_videos', 'fishing', 3);

// Enhanced format
do_action('twintack_htv_display_videos', array(
    'category' => 'tutorial',
    'layout' => 'list',
    'limit' => 5
));
```

### Template Override

You can override any video template by creating files in your theme:

```
your-theme/
└── twintack-how-to-videos/
    ├── video-carousel.php
    ├── video-grid.php
    ├── video-list.php
    ├── video-simple.php
    └── page-videos.php
```

Copy templates from `/plugins/twintack-how-to-videos/templates/` and customize as needed.

### Dedicated Video Page

Create a comprehensive video library page using the included template:

1. **Copy Template**: Copy `page-videos.php` to your theme directory
2. **Create Page**: Create a new WordPress page
3. **Assign Template**: Select "Videos Page" from the page template dropdown
4. **Configure Categories**: The page automatically displays all video categories

The video page template will:
- Display videos organized by categories
- Use grid layout for categories with many videos
- Use simple layout for categories with few videos
- Show "View All" links for categories with more than 6 videos
- Provide admin links for category management (for administrators)

## Customization

### Hooks and Filters

**Filters:**
```php
// Modify sport slugs
add_filter('twintack_htv_sport_slugs', function($slugs) {
    return array('baseball', 'fishing', 'custom-sport');
});

// Change default sport
add_filter('twintack_htv_default_sport', function($sport) {
    return 'fishing';
});

// Customize video query
add_filter('twintack_htv_query_args', function($args, $sport, $limit) {
    $args['meta_query'] = array(
        array(
            'key' => '_featured',
            'value' => 'yes'
        )
    );
    return $args;
}, 10, 3);

// Override template path
add_filter('twintack_htv_template_path', function($path) {
    return get_template_directory() . '/custom-video-template.php';
});

// Control asset loading
add_filter('twintack_htv_should_enqueue_frontend_assets', function($should_enqueue) {
    return is_page('videos') || $should_enqueue;
});
```

**Actions:**
```php
// Display videos using action
do_action('twintack_htv_display_videos', $sport, $limit);
```

### Styling

**Custom Colors:**
```php
add_filter('twintack_htv_primary_color', function() {
    return '#your-color';
});

add_filter('twintack_htv_accent_color', function() {
    return '#your-accent';
});

add_filter('twintack_htv_background_color', function() {
    return '#your-background';
});
```

**CSS Classes:**

The plugin uses prefixed CSS classes to avoid conflicts:
- `.twintack-video-carousel-section`
- `.twintack-video-slide`
- `.twintack-play-button`
- `.twintack-video-modal`

## API Reference

### Functions

#### `twintack_htv_get_videos_by_sport($sport_slug, $limit = 5)`
Retrieve videos for a specific sport.

**Parameters:**
- `$sport_slug` (string) - Sport taxonomy slug
- `$limit` (int) - Number of videos to retrieve

**Returns:** Array of video data

#### `twintack_htv_get_videos_by_category($category_slug, $limit = 5)`
Retrieve videos for a specific category.

**Parameters:**
- `$category_slug` (string) - Category taxonomy slug
- `$limit` (int) - Number of videos to retrieve

**Returns:** Array of video data

#### `twintack_htv_get_videos($args = array())`
Retrieve videos with flexible filtering options.

**Parameters:**
- `$args` (array) - Query arguments:
  - `sport` (string) - Sport slug
  - `category` (string) - Category slug
  - `limit` (int) - Number of videos
  - `orderby` (string) - Order by field
  - `order` (string) - Order direction

**Returns:** Array of video data

#### `twintack_htv_get_product_sport($product_id)`
Get the sport category for a WooCommerce product.

**Parameters:**
- `$product_id` (int) - Product ID

**Returns:** String sport slug

#### `twintack_htv_display_videos($args = array(), $limit = 5)`
Display videos with flexible layout options.

**Parameters:**
- `$args` (array|string) - Display arguments or sport slug for backward compatibility
  - `sport` (string) - Sport slug
  - `category` (string) - Category slug
  - `limit` (int) - Number of videos
  - `layout` (string) - Layout type (carousel, grid, list, simple)
  - `title` (string) - Section title
  - `show_title` (bool) - Whether to show title
  - `class` (string) - Custom CSS class
- `$limit` (int) - Number of videos (for backward compatibility)

#### `twintack_htv_display_category_videos($category_slug, $limit = 5, $layout = 'carousel')`
Convenience function for displaying videos by category.

**Parameters:**
- `$category_slug` (string) - Category slug
- `$limit` (int) - Number of videos
- `$layout` (string) - Layout type

#### `twintack_htv_has_videos_for_sport($sport_slug)`
Check if videos exist for a sport.

**Parameters:**
- `$sport_slug` (string) - Sport taxonomy slug

**Returns:** Boolean

### Backward Compatibility

The plugin provides backward compatibility functions:
- `twintack_get_product_sport()` → `twintack_htv_get_product_sport()`
- `twintack_get_how_to_videos_by_sport()` → `twintack_htv_get_videos_by_sport()`

## Requirements

- WordPress 5.8+
- PHP 7.4+
- WooCommerce (for product integration)

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Changelog

### 1.1.0
- **Major Enhancement**: Added video category taxonomy for broader content organization
- **New Layouts**: Added grid, list, and simple layout options beyond carousel
- **Enhanced Shortcodes**: Support for category filtering and layout selection
- **Admin Interface**: Separate assignment pages for sports and categories
- **Video Page Template**: Comprehensive template for video library pages
- **New Functions**: Added category-based functions and flexible display options
- **Backward Compatibility**: All existing functionality preserved

### 1.0.0
- Initial release
- Migrated from theme implementation
- Added shortcode support
- Improved admin interface
- Enhanced accessibility
- Responsive design improvements

## Support

For issues and feature requests, please contact the TwinTack development team.

## License

GPL v2 or later 