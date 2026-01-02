# Marketing Dashboard Icons

## Overview
The TwinTack Marketing dashboard uses custom icons from The Noun Project to provide a professional, cohesive visual experience.

## Icon Integration

### How It Works

The dashboard automatically downloads and caches icons from The Noun Project API:

1. **First Load**: If icons don't exist locally, the system attempts to download them via the Noun Project API
2. **Caching**: Downloaded icons are saved to `assets/images/icons/` for future use
3. **Fallback**: If API is not configured or download fails, emoji fallbacks are used
4. **Performance**: Icons are served as SVG files for crisp display at any size

### Icons Used

| Feature | Icon | Noun Project ID | Artist | URL |
|---------|------|----------------|--------|-----|
| Hero Carousel | carousel.svg | 8116739 | Side Project | [View Icon](https://thenounproject.com/icon/carousel-8116739/) |
| Featured Products | product.svg | 7727037 | Yosua Bungaran | [View Icon](https://thenounproject.com/icon/product-7727037/) |
| Banner Blocks | content.svg | 7459085 | Iqbal Taufiq Alim | [View Icon](https://thenounproject.com/icon/content-7459085/) |
| Announcement Bar | announcement.svg | 3408481 | Alice Design | [View Icon](https://thenounproject.com/icon/announcement-3408481/) |
| Marketing Templates | landing-page.svg | 8142737 | Noval Aryadita | [View Icon](https://thenounproject.com/icon/landing-page-8142737/) |
| Product Management | input-product.svg | 4797115 | (Various) | [View Icon](https://thenounproject.com/icon/input-product-4797115/) |

## Prerequisites

For automatic icon downloading to work:

1. **Noun Project API**: Must be configured in the Admin Console Fixes plugin
   - Navigate to **Noun Project** in WordPress admin
   - Enter API Key and Secret
   - Test connection

2. **File Permissions**: The `plugins/twintack-marketing/assets/images/icons/` directory must be writable

## Manual Icon Download

If you prefer to download icons manually:

```php
// In WordPress admin or via WP-CLI
$api = TwinTack_NounProject_API::get_instance();

$icons = array(
    'carousel' => 8116739,
    'product' => 7727037,
    'content' => 7459085,
    'announcement' => 3408481,
    'landing-page' => 8142737,
    'input-product' => 4797115
);

foreach ($icons as $name => $icon_id) {
    $svg = $api->download_icon_svg($icon_id);
    if (!is_wp_error($svg)) {
        $path = plugin_dir_path(__FILE__) . 'assets/images/icons/' . $name . '.svg';
        file_put_contents($path, $svg);
        echo "Downloaded: {$name}.svg\n";
    }
}
```

## Styling

Icons are styled via CSS in `assets/css/admin.css`:

```css
.twintack-marketing-card h2 .card-icon svg {
    width: 28px;
    height: 28px;
    fill: #2271b1;  /* WordPress blue */
    transition: fill 0.2s ease;
}

.twintack-marketing-card:hover h2 .card-icon svg {
    fill: #135e96;  /* Darker blue on hover */
}
```

### Customization

To change icon size:
```css
.twintack-marketing-card h2 .card-icon svg {
    width: 32px;  /* Increase size */
    height: 32px;
}
```

To change icon color:
```css
.twintack-marketing-card h2 .card-icon svg {
    fill: #00a32a;  /* Green */
}
```

## Icon Display Logic

The `get_card_icon()` method in `class-marketing-admin.php` follows this priority:

1. **Check for local SVG file** - Fastest, no API call needed
2. **Download from API** - If file doesn't exist and API is configured
3. **Save downloaded icon** - Cache for future use
4. **Fallback to emoji** - If all else fails

```php
private function get_card_icon($icon_id) {
    // 1. Try local file
    if (file_exists($icon_path)) {
        return SVG;
    }
    
    // 2. Try Noun Project API
    if (API available) {
        $svg = download_icon();
        save_locally();
        return SVG;
    }
    
    // 3. Fallback to emoji
    return emoji;
}
```

## Troubleshooting

### Icons not appearing?

1. **Check API Configuration**
   ```
   WordPress Admin → Noun Project → Test Connection
   ```

2. **Check File Permissions**
   ```bash
   chmod 755 plugins/twintack-marketing/assets/images/icons/
   ```

3. **Check PHP Error Log**
   ```php
   // Look for file write errors
   error_log('Cannot write to icons directory');
   ```

4. **Manual Download**
   - Download icons manually from Noun Project
   - Save to `assets/images/icons/` directory
   - Name files according to the mapping

### Icons are emoji instead of SVG?

This means icons haven't been downloaded yet. Solutions:

1. Configure Noun Project API
2. Visit the dashboard page (triggers download)
3. Or download manually (see above)

## Licensing

Icons from The Noun Project require either:
- **Attribution** - If using free version
- **Subscription** - No attribution required

Ensure you comply with [The Noun Project's terms of service](https://thenounproject.com/legal/#icons).

## Future Enhancements

Potential improvements:

1. **Bulk Download Script**
   - Download all icons at once
   - Run on plugin activation

2. **Icon Color Customization**
   - Admin setting to choose icon color
   - Match brand colors

3. **Alternative Icons**
   - Allow users to upload custom icons
   - Choose from icon library

4. **Icon Cache Management**
   - Clear cached icons
   - Re-download updated versions
   - View icon attribution

## Resources

- [The Noun Project](https://thenounproject.com/)
- [Noun Project API Docs](https://thenounproject.com/developers/api-docs)
- [TwinTack Noun Project Integration](../twintack-admin-console-fixes/NOUNPROJECT-API.md)

