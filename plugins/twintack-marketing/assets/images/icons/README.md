# Marketing Dashboard Icons

Icons sourced from The Noun Project API.

## Icon Details

1. **Announcement** (ID: 3408481)
   - File: `announcement.svg`
   - Artist: Alice Design
   - URL: https://thenounproject.com/icon/announcement-3408481/

2. **Product** (ID: 7727037)
   - File: `product.svg`
   - Artist: Yosua Bungaran
   - URL: https://thenounproject.com/icon/product-7727037/

3. **Carousel** (ID: 8116739)
   - File: `carousel.svg`
   - Artist: Side Project
   - URL: https://thenounproject.com/icon/carousel-8116739/

4. **Content** (ID: 7459085)
   - File: `content.svg`
   - Artist: Iqbal Taufiq Alim
   - URL: https://thenounproject.com/icon/content-7459085/

5. **Landing Page** (ID: 8142737)
   - File: `landing-page.svg`
   - Artist: Noval Aryadita
   - URL: https://thenounproject.com/icon/landing-page-8142737/

6. **Input Product** (ID: 4797115)
   - File: `input-product.svg`
   - URL: https://thenounproject.com/icon/input-product-4797115/

## Usage

To download these icons using the Noun Project API:

```php
$api = TwinTack_NounProject_API::get_instance();

// Download icons
$icons = [
    'announcement' => 3408481,
    'product' => 7727037,
    'carousel' => 8116739,
    'content' => 7459085,
    'landing-page' => 8142737,
    'input-product' => 4797115
];

foreach ($icons as $name => $icon_id) {
    $svg = $api->download_icon_svg($icon_id);
    if (!is_wp_error($svg)) {
        file_put_contents(__DIR__ . "/{$name}.svg", $svg);
    }
}
```

## License

Icons are used under The Noun Project license. Ensure proper attribution or subscription as required.

