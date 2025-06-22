<?php
/**
 * Template part for displaying the product carousel on sport pages
 *
 * @package TwinTack2025
 */

// Get the current sport from the page title
$page_title = strtolower(get_the_title());
$sport = sanitize_title($page_title);

// Get carousel items for the current sport
$carousel_items = twintack_get_carousel_items_by_sport($sport);

// If no items, exit early
if (empty($carousel_items)) {
    return;
}
?>

<div class="sport-product-carousel">
    <div class="container">
        <h2 class="section-title">Featured Products</h2>
        
        <div class="twintack-carousel">
            <!-- Swiper container -->
            <div class="swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($carousel_items as $item) : ?>
                        <div class="swiper-slide">
                            <div class="product-card">
                                <div class="product-image">
                                    <?php if ($item['enable_lightbox']) : ?>
                                        <a href="<?php echo esc_url($item['lightbox_image_url']); ?>" class="product-lightbox">
                                            <img src="<?php echo esc_url($item['image_url']); ?>" alt="<?php echo esc_attr($item['title']); ?>" loading="lazy">
                                        </a>
                                    <?php else : ?>
                                        <img src="<?php echo esc_url($item['image_url']); ?>" alt="<?php echo esc_attr($item['title']); ?>" loading="lazy">
                                    <?php endif; ?>
                                </div>
                                <div class="product-details">
                                    <h3 class="product-title"><?php echo esc_html($item['product_title'] ?: $item['title']); ?></h3>
                                    <div class="product-price"><?php echo wp_kses_post($item['product_price']); ?></div>
                                    <div class="product-actions">
                                        <a href="<?php echo esc_url($item['product_url']); ?>" class="button view-details">View Details</a>
                                        <?php if ($item['direct_add_to_cart']) : ?>
                                            <button class="button add-to-cart" 
                                                    data-product-id="<?php echo esc_attr($item['product_id']); ?>"
                                                    <?php if ($item['variation_id']) : ?>
                                                        data-variation-id="<?php echo esc_attr($item['variation_id']); ?>"
                                                    <?php endif; ?>>
                                                Add to Cart
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Navigation buttons -->
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
            </div>
        </div>
    </div>
</div>

<?php
// Localize the carousel data for JavaScript
wp_localize_script('twintack-carousel', 'twintackCarousel', array(
    'items' => $carousel_items,
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('twintack_carousel_nonce'),
)); 