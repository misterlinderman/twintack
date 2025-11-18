<?php
/**
 * Carousel Integration for TwinTack
 * 
 * This file handles the integration of the carousel functionality
 * with the sport templates and WooCommerce.
 * 
 * @package TwinTack2025
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get carousel items by sport category
 */
function twintack_get_carousel_items_by_sport($sport) {
    // Sanitize the sport parameter
    $sport = sanitize_title($sport);
    
    // Get the sport term
    $term = get_term_by('slug', $sport, 'sport_category');
    if (!$term) {
        return array();
    }
    
    // Get carousel items for this sport
    $args = array(
        'post_type' => 'carousel_item',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'sport_category',
                'field' => 'term_id',
                'terms' => $term->term_id,
            ),
        ),
        'orderby' => 'menu_order',
        'order' => 'ASC',
    );
    
    $items = get_posts($args);
    $carousel_items = array();
    
    foreach ($items as $item) {
        // Get the linked product
        $product_id = get_post_meta($item->ID, '_linked_product_id', true);
        $product = wc_get_product($product_id);
        
        if (!$product) {
            continue;
        }
        
        // Get the selected variation if it exists
        $variation_id = get_post_meta($item->ID, '_linked_variation_id', true);
        $variation = $variation_id ? wc_get_product($variation_id) : null;
        
        // Get the lightbox image (which we'll use as main image too)
        $lightbox_image_id = get_post_meta($item->ID, '_lightbox_image_id', true);
        $main_image_url = $lightbox_image_id ? wp_get_attachment_image_url($lightbox_image_id, 'full') : '';
        
        // If no lightbox image is set, try to use the featured image
        if (!$main_image_url) {
            $image_id = get_post_thumbnail_id($item->ID);
            $main_image_url = wp_get_attachment_image_url($image_id, 'full');
        }
        
        // If still no image, use product image as fallback
        if (!$main_image_url) {
            $main_image_url = wp_get_attachment_image_url($product->get_image_id(), 'full');
        }
        
        // Build the carousel item data
        $carousel_items[] = array(
            'id' => $item->ID,
            'title' => $item->post_title,
            'image_url' => $main_image_url,
            'lightbox_image_url' => $main_image_url, // Same as main image
            'product_id' => $product_id,
            'variation_id' => $variation ? $variation->get_id() : null,
            'product_url' => $variation ? $variation->get_permalink() : $product->get_permalink(),
            'product_title' => $variation ? $variation->get_name() : $product->get_name(),
            'product_price' => $variation ? $variation->get_price_html() : $product->get_price_html(),
            'enable_lightbox' => get_post_meta($item->ID, '_enable_lightbox', true) === '1',
            'direct_add_to_cart' => get_post_meta($item->ID, '_direct_add_to_cart', true) === '1',
        );
    }
    
    return $carousel_items;
}

/**
 * Enqueue carousel scripts and styles
 */
function twintack_enqueue_carousel_assets() {
    // Only enqueue on pages that use the carousel
    if (!is_page_template('templates/template-sport.php')) {
        return;
    }
    
    // Enqueue Swiper CSS
    wp_enqueue_style(
        'swiper',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
        array(),
        '11.0.0'
    );
    
    // Enqueue Swiper JS
    wp_enqueue_script(
        'swiper',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
        array('jquery'),
        '11.0.0',
        true
    );
    
    // Enqueue custom carousel styles
    wp_enqueue_style(
        'twintack-carousel',
        get_template_directory_uri() . '/css/carousel.css',
        array('swiper'),
        filemtime(get_template_directory() . '/css/carousel.css')
    );
    
    // Enqueue video carousel styles
    wp_enqueue_style(
        'twintack-video-carousel',
        get_template_directory_uri() . '/css/video-carousel.css',
        array('swiper'),
        filemtime(get_template_directory() . '/css/video-carousel.css')
    );
    
    // Enqueue custom carousel script
    wp_enqueue_script(
        'twintack-carousel',
        get_template_directory_uri() . '/js/carousel.js',
        array('jquery', 'swiper'),
        filemtime(get_template_directory() . '/js/carousel.js'),
        true
    );

    // Localize script with AJAX data
    wp_localize_script('twintack-carousel', 'twintackCarousel', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('twintack_carousel_nonce'),
    ));
}
add_action('wp_enqueue_scripts', 'twintack_enqueue_carousel_assets', 20);

/**
 * AJAX handler for adding items to cart
 */
function twintack_ajax_add_to_cart() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'twintack_carousel_nonce')) {
        wp_send_json_error(array('message' => 'Invalid security token.'));
    }
    
    // Get product ID and variation ID
    if (!isset($_POST['product_id'])) {
        wp_send_json_error(array('message' => 'No product ID provided.'));
    }
    
    $product_id = intval($_POST['product_id']);
    $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    // Add to cart
    $cart_item_key = false;
    
    if ($variation_id) {
        // Get the variation's attributes
        $variation = wc_get_product($variation_id);
        if ($variation) {
            $variation_data = wc_get_product_variation_attributes($variation_id);
            $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation_data);
        }
    } else {
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity);
    }
    
    if ($cart_item_key) {
        wp_send_json_success(array(
            'message' => __('Product added to cart successfully!', 'twintack'),
            'cart_url' => wc_get_cart_url(),
            'cart_count' => WC()->cart->get_cart_contents_count(),
        ));
    } else {
        wp_send_json_error(array('message' => __('Failed to add product to cart.', 'twintack')));
    }
}
add_action('wp_ajax_twintack_add_to_cart', 'twintack_ajax_add_to_cart');
add_action('wp_ajax_nopriv_twintack_add_to_cart', 'twintack_ajax_add_to_cart');

/**
 * Add carousel-specific body classes
 */
function twintack_carousel_body_classes($classes) {
    if (is_page_template('templates/template-sport.php')) {
        $classes[] = 'has-carousel';
    }
    return $classes;
}
add_filter('body_class', 'twintack_carousel_body_classes');

/**
 * Add carousel-specific meta tags
 */
function twintack_carousel_meta_tags() {
    if (!is_page_template('templates/template-sport.php')) {
        return;
    }
    
    $current_sport = sanitize_title(get_the_title());
    $carousel_items = twintack_get_carousel_items_by_sport($current_sport);
    
    if (!empty($carousel_items)) {
        // Add Open Graph meta tags for the first carousel item
        $first_item = $carousel_items[0];
        echo '<meta property="og:image" content="' . esc_url($first_item['image_url']) . '" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($first_item['product_title']) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($first_item['product_title'] . ' - ' . get_bloginfo('name')) . '" />' . "\n";
    }
}
add_action('wp_head', 'twintack_carousel_meta_tags');

/**
 * Add carousel-specific schema markup
 */
function twintack_carousel_schema_markup() {
    if (!is_page_template('templates/template-sport.php')) {
        return;
    }
    
    $current_sport = sanitize_title(get_the_title());
    $carousel_items = twintack_get_carousel_items_by_sport($current_sport);
    
    if (!empty($carousel_items)) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => array(),
        );
        
        foreach ($carousel_items as $index => $item) {
            $schema['itemListElement'][] = array(
                '@type' => 'ListItem',
                'position' => $index + 1,
                'item' => array(
                    '@type' => 'Product',
                    'name' => $item['product_title'],
                    'image' => $item['image_url'],
                    'url' => $item['product_url'],
                    'offers' => array(
                        '@type' => 'Offer',
                        'price' => strip_tags($item['product_price']),
                        'priceCurrency' => get_woocommerce_currency(),
                        'availability' => 'https://schema.org/InStock',
                        'url' => $item['product_url'],
                    ),
                ),
            );
        }
        
        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
    }
}
add_action('wp_footer', 'twintack_carousel_schema_markup'); 