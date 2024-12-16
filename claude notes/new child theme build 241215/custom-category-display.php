<?php
// Add to your child theme's functions.php

class Product_Variant_Display {
    public function __construct() {
        // Remove default category display
        remove_action('woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10);
        
        // Add our custom display
        add_action('woocommerce_archive_description', array($this, 'display_variant_products'), 20);
        
        // Add query var for preselected variant
        add_filter('query_vars', array($this, 'add_variant_query_var'));
        
        // Handle variant selection on product page
        add_filter('woocommerce_product_get_default_attributes', array($this, 'set_default_variant'), 10, 2);
    }

    public function display_variant_products() {
        if (!is_product_category()) return;

        $category = get_queried_object();
        $products = $this->get_category_products($category->term_id);
        
        echo '<div class="variant-products-grid">';
        foreach ($products as $product) {
            if ($product->is_type('variable')) {
                $this->display_product_variants($product);
            } else {
                $this->display_single_product($product);
            }
        }
        echo '</div>';
    }

    private function get_category_products($category_id) {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category_id
                )
            )
        );
        
        return wc_get_products($args);
    }

    private function display_product_variants($product) {
        $variations = $product->get_available_variations();
        $attributes = $product->get_variation_attributes();
        
        foreach ($variations as $variation) {
            $variant_atts = $variation['attributes'];
            $color = reset($variant_atts); // Assuming color is the first attribute
            
            echo '<div class="variant-product-card">';
            echo '<a href="' . esc_url(add_query_arg('preselected_variant', $variation['variation_id'], $product->get_permalink())) . '">';
            echo wp_get_attachment_image($variation['image_id'], 'woocommerce_thumbnail');
            echo '<h3>' . $product->get_title() . ' - ' . $color . '</h3>';
            echo '<span class="price">' . $variation['price_html'] . '</span>';
            echo '</a>';
            echo '</div>';
        }
    }

    private function display_single_product($product) {
        echo '<div class="variant-product-card">';
        echo '<a href="' . $product->get_permalink() . '">';
        echo $product->get_image('woocommerce_thumbnail');
        echo '<h3>' . $product->get_title() . '</h3>';
        echo '<span class="price">' . $product->get_price_html() . '</span>';
        echo '</a>';
        echo '</div>';
    }

    public function add_variant_query_var($vars) {
        $vars[] = 'preselected_variant';
        return $vars;
    }

    public function set_default_variant($default_attributes, $product) {
        $preselected = get_query_var('preselected_variant');
        if ($preselected) {
            $variation = wc_get_product($preselected);
            if ($variation && $variation->get_type() === 'variation') {
                return $variation->get_attributes();
            }
        }
        return $default_attributes;
    }
}

// Initialize the class
add_action('init', function() {
    new Product_Variant_Display();
});
