<?php
/**
 * Product Page Color Scheme Toggle
 * Allows toggling product pages to an alternate color scheme
 */

if (!defined('ABSPATH')) exit;

class TwinTack_Marketing_Product_Colors {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add meta box to product edit screen
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box'), 10, 2);
        
        // Add body class for color scheme
        add_filter('body_class', array($this, 'add_color_scheme_class'));
    }
    
    public function add_meta_box() {
        add_meta_box(
            'twintack_product_color_scheme',
            __('Color Scheme', 'twintack-marketing'),
            array($this, 'render_meta_box'),
            'product',
            'side',
            'default'
        );
    }
    
    public function render_meta_box($post) {
        wp_nonce_field('twintack_product_color_scheme_nonce', 'twintack_product_color_scheme_nonce');
        
        $color_scheme = get_post_meta($post->ID, '_twintack_product_color_scheme', true);
        
        ?>
        <p>
            <label for="twintack_product_color_scheme"><?php _e('Product Page Color Scheme', 'twintack-marketing'); ?></label>
        </p>
        <p>
            <select name="twintack_product_color_scheme" id="twintack_product_color_scheme">
                <option value="default" <?php selected($color_scheme, 'default'); ?>><?php _e('Default', 'twintack-marketing'); ?></option>
                <option value="alternate" <?php selected($color_scheme, 'alternate'); ?>><?php _e('Alternate', 'twintack-marketing'); ?></option>
            </select>
        </p>
        <p class="description"><?php _e('Select an alternate color scheme for this product page.', 'twintack-marketing'); ?></p>
        <?php
    }
    
    public function save_meta_box($post_id, $post) {
        // Check nonce
        if (!isset($_POST['twintack_product_color_scheme_nonce']) || 
            !wp_verify_nonce($_POST['twintack_product_color_scheme_nonce'], 'twintack_product_color_scheme_nonce')) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Check if autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Save color scheme
        if (isset($_POST['twintack_product_color_scheme'])) {
            update_post_meta($post_id, '_twintack_product_color_scheme', sanitize_text_field($_POST['twintack_product_color_scheme']));
        } else {
            delete_post_meta($post_id, '_twintack_product_color_scheme');
        }
    }
    
    public function add_color_scheme_class($classes) {
        if (is_product()) {
            global $product;
            if ($product) {
                $color_scheme = get_post_meta($product->get_id(), '_twintack_product_color_scheme', true);
                if ($color_scheme === 'alternate') {
                    $classes[] = 'twintack-product-color-alternate';
                }
            }
        }
        
        return $classes;
    }
}

