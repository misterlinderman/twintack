<?php
class TwinTack_Product_Display {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('woocommerce_before_shop_loop', array($this, 'modify_category_display'), 10);
        add_filter('woocommerce_product_get_default_attributes', array($this, 'set_default_variant'), 10, 2);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }

    public function modify_category_display() {
        if (!is_product_category()) return;

        // Remove default WooCommerce loop
        remove_action('woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10);
        remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5);
        
        // Add our custom display
        add_action('woocommerce_before_shop_loop_item', array($this, 'variant_display_open'), 10);
        add_action('woocommerce_after_shop_loop_item', array($this, 'variant_display_close'), 5);
    }

    public function variant_display_open() {
        global $product;
        if ($product->is_type('variable')) {
            $this->display_variants($product);
        } else {
            $this->display_simple_product($product);
        }
    }

    private function display_variants($product) {
        $variations = $product->get_available_variations();
        foreach ($variations as $variation) {
            echo '<div class="product-variant">';
            echo '<a href="' . esc_url(add_query_arg('default_variant', $variation['variation_id'], $product->get_permalink())) . '">';
            echo wp_get_attachment_image($variation['image_id'], 'woocommerce_thumbnail');
            echo '<h3>' . $product->get_title() . ' - ' . implode(', ', $variation['attributes']) . '</h3>';
            echo '<span class="price">' . $variation['price_html'] . '</span>';
            echo '</a>';
            echo '</div>';
        }
    }

    private function display_simple_product($product) {
        // Standard product display
    }

    public function set_default_variant($default_attributes, $product) {
        $variant_id = get_query_var('default_variant');
        if ($variant_id) {
            $variation = wc_get_product($variant_id);
            if ($variation && $variation->get_type() === 'variation') {
                return $variation->get_attributes();
            }
        }
        return $default_attributes;
    }

    public function enqueue_styles() {
        wp_add_inline_style('twintack-style', '
            .product-variant {
                margin-bottom: 2em;
                transition: transform 0.3s ease;
            }
            .product-variant:hover {
                transform: translateY(-5px);
            }
        ');
    }
}

// Initialize the display system
add_action('init', array('TwinTack_Product_Display', 'get_instance'));
