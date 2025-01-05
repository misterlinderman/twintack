<?php
if (!defined('ABSPATH')) {
    exit;
}

global $product;

$columns = apply_filters('woocommerce_product_thumbnails_columns', 4);
$post_thumbnail_id = $product->get_image_id();
$gallery_images = $product->get_gallery_image_ids();

// Get variations and their images
$variations = $product->is_type('variable') ? $product->get_available_variations() : [];
$variation_images = [];
foreach ($variations as $variation) {
    $variation_images[] = $variation['image_id'];
}

// Combine all image IDs in the order we want them
$all_images = array_unique(array_merge($variation_images, [$post_thumbnail_id], $gallery_images));

$wrapper_classes = apply_filters(
    'woocommerce_single_product_image_gallery_classes',
    array(
        'woocommerce-product-gallery',
        'woocommerce-product-gallery--' . ($post_thumbnail_id ? 'with-images' : 'without-images'),
        'images',
    )
);
?>
<div class="<?php echo esc_attr(implode(' ', array_map('sanitize_html_class', $wrapper_classes))); ?>">
    <div class="main-carousel">
        <?php
        foreach ($all_images as $image_id) {
            $full_image = wp_get_attachment_image_src($image_id, 'full');
            echo '<div class="carousel-slide">';
            echo '<a href="' . esc_url($full_image[0]) . '" class="woocommerce-product-gallery__image" data-pswp-width="' . esc_attr($full_image[1]) . '" data-pswp-height="' . esc_attr($full_image[2]) . '">';
            echo wp_get_attachment_image($image_id, 'full', false, array(
                'class' => 'wp-post-image',
                'data-image-id' => $image_id
            ));
            echo '</a>';
            echo '</div>';
        }
        ?>
    </div>

    <div class="variation-thumbnails">
        <?php
        if ($variations) {
            foreach ($variations as $variation) {
                $variation_image_id = $variation['image_id'];
                $color = '';
                $color_slug = '';
                foreach ($variation['attributes'] as $attribute => $value) {
                    if (strpos(strtolower($attribute), 'color') !== false) {
                        // Get the term object to access both name and slug
                        $term = get_term_by('slug', $value, str_replace('attribute_', '', $attribute));
                        $color = $term ? $term->name : $value;
                        $color_slug = $value; // This is the slug
                        break;
                    }
                }
                echo '<div class="variation-thumb" data-image-id="' . esc_attr($variation_image_id) . '" data-color-slug="' . esc_attr($color_slug) . '">';
                echo wp_get_attachment_image($variation_image_id, 'thumbnail');
                if ($color) {
                    echo '<span class="variation-color">' . esc_html($color) . '</span>';
                }
                echo '</div>';
            }
        }
        ?>
    </div>
</div>