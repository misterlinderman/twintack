# TwinTack Homepage Blog Roll

## Overview

The TwinTack blog roll displays recent blog posts on the homepage automatically. It appears after the ACF flexible content sections but before the footer, showcasing the latest 5 blog posts with featured images, excerpts, and category information.

## Implementation

The blog roll is implemented as a **direct template feature** rather than a shortcode to avoid WordPress content filtering issues that were stripping inline styles.

### File Structure

- **Template Part**: `template-parts/content-blog-roll.php`
- **Homepage Template**: `front-page.php` (includes the blog roll template)
- **Styling**: `css/components/blog-roll.css` (imported in main.css)

### How It Works

1. **Homepage Only**: The blog roll only displays on the front page (`is_front_page()`)
2. **Automatic Display**: Loads after ACF flexible content, before footer
3. **Recent Posts**: Shows the 5 most recent published blog posts
4. **Featured Images**: Displays as background images with proper fallbacks
5. **Responsive Design**: Grid layout that adapts to mobile devices

## Features

- **Section Header**: "Latest From Our Blog" with subtitle
- **Grid Layout**: Responsive card-based design
- **Featured Images**: Background images with consistent sizing
- **Post Meta**: Date and category information
- **Excerpts**: Automatically trimmed to 20 words
- **Read More Links**: Direct links to full posts
- **View All Button**: Links to main blog page

## Styling

The blog roll uses the theme's design system:

- **Colors**: Theme CSS custom properties (`--color-primary`, `--light`, etc.)
- **Typography**: Archivo font family for consistency
- **Layout**: CSS Grid with responsive breakpoints
- **Interactions**: Hover effects and smooth transitions

### CSS Classes

- `.homepage-blog-roll` - Main section container
- `.blog-roll-header` - Title and subtitle area
- `.blog-roll-grid` - Grid container for posts
- `.blog-roll-item` - Individual post card
- `.blog-roll-image` - Featured image container (with inline background-image)
- `.blog-roll-content` - Text content area
- `.blog-roll-footer` - "View All Posts" button area

## Customization

### Changing Number of Posts

Edit `template-parts/content-blog-roll.php`, line 15:

```php
'numberposts' => 5, // Change to desired number
```

### Modifying Section Title

Edit `template-parts/content-blog-roll.php`, lines 31-32:

```php
<h2 class="section-title">Latest From Our Blog</h2>
<p class="section-subtitle">Stay updated with tips, techniques, and TwinTack news</p>
```

### Adjusting Excerpt Length

Edit `template-parts/content-blog-roll.php`, line 71:

```php
wp_trim_words($post->post_excerpt ?: $post->post_content, 20, '...')
```

### Styling Modifications

Edit `css/components/blog-roll.css` in the "Homepage Blog Roll Section" area.

## Responsive Behavior

- **Desktop**: Multi-column grid layout
- **Tablet**: Adjusted grid with smaller gaps
- **Mobile**: Single column layout with reduced padding

## Performance

- **Efficient Queries**: Uses `get_posts()` with specific limits
- **Optimized Images**: Uses 'large' image size for backgrounds
- **Conditional Loading**: Only processes on homepage
- **CSS Optimization**: Styles loaded via main.css import

## Previous Implementation

This replaces the previous shortcode-based system (`[twintack_blog_roll]`) which had WordPress content filtering issues. The shortcode approach was removed in favor of direct template implementation for better reliability and performance.

## Troubleshooting

### Blog Roll Not Appearing

1. Check if you're on the homepage (front page)
2. Verify recent blog posts exist and are published
3. Ensure CSS is loading (`css/components/blog-roll.css`)

### Featured Images Not Showing

1. Confirm posts have featured images set
2. Check image file accessibility
3. Verify inline styles aren't being stripped by other plugins

### Layout Issues

1. Check for CSS conflicts in browser dev tools
2. Verify container CSS is loading properly
3. Test responsive behavior at different screen sizes

## Technical Notes

- Uses WordPress template hierarchy (`front-page.php` priority)
- Leverages `get_posts()` for efficient querying
- Implements proper WordPress coding standards
- Follows theme's existing design patterns
- Avoids `wp_kses_post()` filtering issues through direct template output 