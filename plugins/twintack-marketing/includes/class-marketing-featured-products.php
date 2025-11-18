<?php
/**
 * Featured Products Management
 * Handles featured product selection and display
 */

if (!defined('ABSPATH')) exit;

class TwinTack_Marketing_Featured_Products {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Shortcode for displaying featured products
        add_shortcode('twintack_featured_products', array($this, 'render_featured_products'));
        
        // AJAX handlers for admin
        add_action('wp_ajax_twintack_get_featured_products', array($this, 'ajax_get_featured_products'));
        add_action('wp_ajax_twintack_save_featured_products', array($this, 'ajax_save_featured_products'));
        add_action('wp_ajax_twintack_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_twintack_get_product_details', array($this, 'ajax_get_product_details'));
    }
    
    /**
     * Get featured products for a specific context (homepage, landing page, etc.)
     */
    public function get_featured_products($context = 'homepage') {
        $featured = get_option('twintack_featured_products_' . $context, array());
        
        if (empty($featured) || !is_array($featured)) {
            return array();
        }
        
        $products = array();
        foreach ($featured as $product_id) {
            $product = wc_get_product($product_id);
            if ($product && $product->is_visible()) {
                $products[] = $product;
            }
        }
        
        return $products;
    }
    
    /**
     * Save featured products for a specific context
     */
    public function save_featured_products($context, $product_ids) {
        $product_ids = array_map('intval', $product_ids);
        $product_ids = array_filter($product_ids);
        
        update_option('twintack_featured_products_' . $context, $product_ids);
        
        return true;
    }
    
    /**
     * Render featured products shortcode
     */
    public function render_featured_products($atts) {
        $atts = shortcode_atts(array(
            'context' => 'homepage',
            'columns' => '4',
            'limit' => '8',
            'title' => 'Featured Products'
        ), $atts, 'twintack_featured_products');
        
        $products = $this->get_featured_products($atts['context']);
        
        if (empty($products)) {
            return '';
        }
        
        // Limit products
        if (!empty($atts['limit'])) {
            $products = array_slice($products, 0, intval($atts['limit']));
        }
        
        ob_start();
        ?>
        <div class="twintack-featured-products" data-columns="<?php echo esc_attr($atts['columns']); ?>">
            <?php if (!empty($atts['title'])) : ?>
                <h2 class="twintack-featured-products-title"><?php echo esc_html($atts['title']); ?></h2>
            <?php endif; ?>
            
            <ul class="products columns-<?php echo esc_attr($atts['columns']); ?>">
                <?php
                foreach ($products as $product) {
                    $post_object = get_post($product->get_id());
                    setup_postdata($GLOBALS['post'] = $post_object);
                    wc_get_template_part('content', 'product');
                }
                wp_reset_postdata();
                ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Get featured products
     */
    public function ajax_get_featured_products() {
        check_ajax_referer('twintack_marketing_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'homepage';
        $product_ids = get_option('twintack_featured_products_' . $context, array());
        
        $products = array();
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $products[] = array(
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                    'price' => $product->get_price_html()
                );
            }
        }
        
        wp_send_json_success(array('products' => $products));
    }
    
    /**
     * AJAX: Save featured products
     */
    public function ajax_save_featured_products() {
        check_ajax_referer('twintack_marketing_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'homepage';
        $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : array();
        
        $this->save_featured_products($context, $product_ids);
        
        wp_send_json_success(array('message' => 'Featured products saved'));
    }
    
    /**
     * AJAX: Search products
     */
    public function ajax_search_products() {
        check_ajax_referer('twintack_marketing_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        
        if (empty($query) || strlen($query) < 2) {
            wp_send_json_success(array('products' => array()));
        }
        
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            's' => $query,
            'orderby' => 'relevance',
            'order' => 'DESC'
        );
        
        $products_query = new WP_Query($args);
        $products = array();
        
        if ($products_query->have_posts()) {
            while ($products_query->have_posts()) {
                $products_query->the_post();
                $product = wc_get_product(get_the_ID());
                if ($product && $product->is_visible()) {
                    $products[] = array(
                        'id' => $product->get_id(),
                        'name' => $product->get_name(),
                        'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src('thumbnail'),
                        'price' => $product->get_price_html()
                    );
                }
            }
            wp_reset_postdata();
        }
        
        wp_send_json_success(array('products' => $products));
    }
    
    /**
     * AJAX: Get product details
     */
    public function ajax_get_product_details() {
        check_ajax_referer('twintack_marketing_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error(array('message' => 'Invalid product ID'));
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product) {
            wp_send_json_error(array('message' => 'Product not found'));
        }
        
        wp_send_json_success(array(
            'product' => array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src('thumbnail'),
                'price' => $product->get_price_html()
            )
        ));
    }
}

