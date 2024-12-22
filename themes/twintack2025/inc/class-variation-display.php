<?php
class TwinTack_Variation_Display {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('woocommerce_product_query', array($this, 'modify_product_query'));
        add_action('woocommerce_product_query_posts', array($this, 'add_variations_to_loop'));
        add_filter('post_class', array($this, 'modify_variation_classes'), 10, 3);
        add_filter('query_vars', array($this, 'add_variation_query_var'));
    }

    public function modify_product_query($query) {
        if (!is_admin() && $query->is_main_query() && (is_product_category('baseball') || is_product_category('fishing'))) {
            $query->set('posts_per_page', -1);
        }
        return $query;
    }

    public function add_variations_to_loop($posts) {
        if (!is_product_category()) return $posts;

        $variations_posts = array();
        foreach ($posts as $post) {
            $product = wc_get_product($post->ID);
            if ($product && $product->is_type('variable')) {
                $variations = $product->get_available_variations();
                foreach ($variations as $variation) {
                    $variation_post = get_post($variation['variation_id']);
                    $variation_post->variation_data = $variation;
                    $variations_posts[] = $variation_post;
                }
            } else {
                $variations_posts[] = $post;
            }
        }
        return $variations_posts;
    }

    public function modify_variation_classes($classes, $class, $post_id) {
        if (get_post_type($post_id) === 'product_variation') {
            $classes[] = 'product-variation';
            $classes[] = 'product';
        }
        return $classes;
    }

    public function add_variation_query_var($vars) {
        $vars[] = 'preselected_variation';
        return $vars;
    }

    public function get_role_based_price($variation) {
        $user_role = wp_get_current_user()->roles[0] ?? 'customer';
        $price_key = '_' . $user_role . '_price';
        $role_price = get_post_meta($variation->get_id(), $price_key, true);
        
        return $role_price ? $role_price : $variation->get_price();
    }
} 