<?php
/**
 * Product Video Content
 * Adds video content to product pages below the initial product presentation
 */

if (!defined('ABSPATH')) exit;

class TwinTack_Marketing_Product_Video {
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
        
        // Display video on product page - after product summary
        // Only hook if WooCommerce is active
        if (class_exists('WooCommerce')) {
            // Use lower priority to ensure WooCommerce is fully loaded
            add_action('woocommerce_after_single_product_summary', array($this, 'display_product_video'), 15);
        }
    }
    
    public function add_meta_box() {
        add_meta_box(
            'twintack_product_video',
            __('Marketing Video', 'twintack-marketing'),
            array($this, 'render_meta_box'),
            'product',
            'normal',
            'high'
        );
    }
    
    public function render_meta_box($post) {
        wp_nonce_field('twintack_product_video_nonce', 'twintack_product_video_nonce');
        
        $video_url = get_post_meta($post->ID, '_twintack_product_video_url', true);
        $video_type = get_post_meta($post->ID, '_twintack_product_video_type', true);
        $video_embed_code = get_post_meta($post->ID, '_twintack_product_video_embed', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th>
                    <label for="twintack_product_video_type"><?php _e('Video Type', 'twintack-marketing'); ?></label>
                </th>
                <td>
                    <select name="twintack_product_video_type" id="twintack_product_video_type">
                        <option value=""><?php _e('None', 'twintack-marketing'); ?></option>
                        <option value="youtube" <?php selected($video_type, 'youtube'); ?>><?php _e('YouTube', 'twintack-marketing'); ?></option>
                        <option value="vimeo" <?php selected($video_type, 'vimeo'); ?>><?php _e('Vimeo', 'twintack-marketing'); ?></option>
                        <option value="embed" <?php selected($video_type, 'embed'); ?>><?php _e('Embed Code', 'twintack-marketing'); ?></option>
                    </select>
                    <p class="description"><?php _e('Select the type of video you want to display.', 'twintack-marketing'); ?></p>
                </td>
            </tr>
            <tr id="video-url-row" style="<?php echo ($video_type === 'youtube' || $video_type === 'vimeo') ? '' : 'display:none;'; ?>">
                <th>
                    <label for="twintack_product_video_url"><?php _e('Video URL', 'twintack-marketing'); ?></label>
                </th>
                <td>
                    <input type="url" name="twintack_product_video_url" id="twintack_product_video_url" 
                           value="<?php echo esc_attr($video_url); ?>" class="regular-text" />
                    <p class="description"><?php _e('Enter the full YouTube or Vimeo URL (e.g., https://www.youtube.com/watch?v=VIDEO_ID)', 'twintack-marketing'); ?></p>
                </td>
            </tr>
            <tr id="video-embed-row" style="<?php echo ($video_type === 'embed') ? '' : 'display:none;'; ?>">
                <th>
                    <label for="twintack_product_video_embed"><?php _e('Embed Code', 'twintack-marketing'); ?></label>
                </th>
                <td>
                    <textarea name="twintack_product_video_embed" id="twintack_product_video_embed" 
                              rows="5" class="large-text"><?php echo esc_textarea($video_embed_code); ?></textarea>
                    <p class="description"><?php _e('Paste the full iframe embed code here.', 'twintack-marketing'); ?></p>
                </td>
            </tr>
        </table>
        <script>
        jQuery(document).ready(function($) {
            $('#twintack_product_video_type').on('change', function() {
                var type = $(this).val();
                if (type === 'youtube' || type === 'vimeo') {
                    $('#video-url-row').show();
                    $('#video-embed-row').hide();
                } else if (type === 'embed') {
                    $('#video-url-row').hide();
                    $('#video-embed-row').show();
                } else {
                    $('#video-url-row').hide();
                    $('#video-embed-row').hide();
                }
            });
        });
        </script>
        <?php
    }
    
    public function save_meta_box($post_id, $post) {
        // Check nonce
        if (!isset($_POST['twintack_product_video_nonce']) || 
            !wp_verify_nonce($_POST['twintack_product_video_nonce'], 'twintack_product_video_nonce')) {
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
        
        // Save video data
        if (isset($_POST['twintack_product_video_type'])) {
            update_post_meta($post_id, '_twintack_product_video_type', sanitize_text_field($_POST['twintack_product_video_type']));
        }
        
        if (isset($_POST['twintack_product_video_url'])) {
            update_post_meta($post_id, '_twintack_product_video_url', esc_url_raw($_POST['twintack_product_video_url']));
        }
        
        if (isset($_POST['twintack_product_video_embed'])) {
            update_post_meta($post_id, '_twintack_product_video_embed', wp_kses_post($_POST['twintack_product_video_embed']));
        }
    }
    
    public function display_product_video() {
        // This hook only fires on single product pages, so we can skip is_product() check
        // Ensure WooCommerce functions exist
        if (!function_exists('wc_get_product') || !function_exists('get_the_ID')) {
            return;
        }
        
        try {
            global $product;
            
            // Get product ID
            $product_id = 0;
            if ($product && is_a($product, 'WC_Product')) {
                $product_id = $product->get_id();
            } else {
                $post_id = get_the_ID();
                if ($post_id) {
                    $product_id = $post_id;
                }
            }
            
            if (!$product_id) {
                return;
            }
            
            $video_type = get_post_meta($product_id, '_twintack_product_video_type', true);
            
            if (empty($video_type)) {
                return;
            }
            
            ?>
            <div class="twintack-product-video-wrapper">
                <?php
                if ($video_type === 'youtube' || $video_type === 'vimeo') {
                    $video_url = get_post_meta($product_id, '_twintack_product_video_url', true);
                    if (!empty($video_url)) {
                        echo $this->get_video_embed($video_url, $video_type);
                    }
                } elseif ($video_type === 'embed') {
                    $embed_code = get_post_meta($product_id, '_twintack_product_video_embed', true);
                    if (!empty($embed_code)) {
                        echo wp_kses_post($embed_code);
                    }
                }
                ?>
            </div>
            <?php
        } catch (Exception $e) {
            // Silently fail to prevent breaking product pages
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Marketing Video Error: ' . $e->getMessage());
            }
            return;
        } catch (Error $e) {
            // Catch fatal errors in PHP 7+
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TwinTack Marketing Video Fatal Error: ' . $e->getMessage());
            }
            return;
        }
    }
    
    private function get_video_embed($url, $type) {
        if ($type === 'youtube') {
            // Extract YouTube video ID
            preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
            if (!empty($matches[1])) {
                $video_id = $matches[1];
                return '<div class="twintack-video-container"><iframe src="https://www.youtube.com/embed/' . esc_attr($video_id) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
            }
        } elseif ($type === 'vimeo') {
            // Extract Vimeo video ID
            preg_match('/vimeo\.com\/(?:.*\/)?(\d+)/', $url, $matches);
            if (!empty($matches[1])) {
                $video_id = $matches[1];
                return '<div class="twintack-video-container"><iframe src="https://player.vimeo.com/video/' . esc_attr($video_id) . '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>';
            }
        }
        
        return '';
    }
}

