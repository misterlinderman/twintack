<?php
/**
 * Hero Carousel Management
 * Handles hero carousel slides for homepage marketing template
 */

if (!defined('ABSPATH')) exit;

class TwinTack_Marketing_Hero_Carousel {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Shortcode for displaying hero carousel
        add_shortcode('twintack_hero_carousel', array($this, 'render_hero_carousel'));
        
        // AJAX handlers for admin
        add_action('wp_ajax_twintack_get_hero_slides', array($this, 'ajax_get_hero_slides'));
        add_action('wp_ajax_twintack_save_hero_slide', array($this, 'ajax_save_hero_slide'));
        add_action('wp_ajax_twintack_delete_hero_slide', array($this, 'ajax_delete_hero_slide'));
    }
    
    /**
     * Get hero slides
     */
    public function get_hero_slides() {
        $slides = get_option('twintack_hero_carousel_slides', array());
        
        if (empty($slides) || !is_array($slides)) {
            return array();
        }
        
        // Filter out inactive slides
        $active_slides = array_filter($slides, function($slide) {
            return isset($slide['active']) && $slide['active'] === '1';
        });
        
        // Sort by order
        usort($active_slides, function($a, $b) {
            $order_a = isset($a['order']) ? intval($a['order']) : 0;
            $order_b = isset($b['order']) ? intval($b['order']) : 0;
            return $order_a - $order_b;
        });
        
        return array_values($active_slides);
    }
    
    /**
     * Get all hero slides (including inactive)
     */
    public function get_all_hero_slides() {
        $slides = get_option('twintack_hero_carousel_slides', array());
        
        if (empty($slides) || !is_array($slides)) {
            return array();
        }
        
        // Sort by order
        usort($slides, function($a, $b) {
            return ($a['order'] ?? 0) - ($b['order'] ?? 0);
        });
        
        return $slides;
    }
    
    /**
     * Save hero slides
     */
    public function save_hero_slides($slides) {
        update_option('twintack_hero_carousel_slides', $slides);
        return true;
    }
    
    /**
     * Render hero carousel shortcode
     */
    public function render_hero_carousel($atts) {
        $atts = shortcode_atts(array(
            'context' => 'homepage'
        ), $atts, 'twintack_hero_carousel');
        
        $slides = $this->get_hero_slides();
        
        if (empty($slides)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="site-marquee twintack-marketing-hero">
            <div class="marquee-slides">
                <?php foreach ($slides as $index => $slide) : 
                    $desktop_video = isset($slide['video_desktop']) ? $slide['video_desktop'] : '';
                    $desktop_image = isset($slide['image_desktop']) ? $slide['image_desktop'] : '';
                    $mobile_image = isset($slide['image_mobile']) ? $slide['image_mobile'] : '';
                    $destination_url = isset($slide['destination_url']) ? $slide['destination_url'] : '';
                    $alt_text = isset($slide['alt_text']) ? $slide['alt_text'] : '';
                    $has_video = !empty($desktop_video);
                ?>
                    <div class="marquee-slide <?php echo ($index === 0) ? 'active' : ''; ?>" <?php echo $has_video ? 'data-has-video="true"' : ''; ?>>
                        <?php if ($destination_url) : ?>
                            <a href="<?php echo esc_url($destination_url); ?>" class="marquee-slide-link" aria-label="<?php echo esc_attr($alt_text ?: 'Hero slide ' . ($index + 1)); ?>">
                        <?php endif; ?>
                        
                        <?php if ($has_video) : ?>
                            <!-- Desktop: Video with image fallback/poster -->
                            <div class="marquee-slide-video desktop-only">
                                <video 
                                    class="marquee-video" 
                                    autoplay 
                                    muted 
                                    loop 
                                    playsinline
                                    <?php if ($desktop_image) : ?>poster="<?php echo esc_url($desktop_image); ?>"<?php endif; ?>
                                    aria-label="<?php echo esc_attr($alt_text ?: 'Hero video ' . ($index + 1)); ?>"
                                >
                                    <source src="<?php echo esc_url($desktop_video); ?>" type="video/mp4">
                                    <?php if ($desktop_image) : ?>
                                        <img src="<?php echo esc_url($desktop_image); ?>" 
                                             alt="<?php echo esc_attr($alt_text ?: 'Hero slide ' . ($index + 1)); ?>" 
                                             class="marquee-background-image video-fallback" />
                                    <?php endif; ?>
                                </video>
                            </div>
                            <!-- Mobile: Static image -->
                            <?php if ($mobile_image) : ?>
                                <picture class="marquee-slide-image mobile-only">
                                    <img src="<?php echo esc_url($mobile_image); ?>" 
                                         alt="<?php echo esc_attr($alt_text ?: 'Hero slide ' . ($index + 1)); ?>" 
                                         class="marquee-background-image" />
                                </picture>
                            <?php endif; ?>
                        <?php elseif ($desktop_image || $mobile_image) : ?>
                            <picture class="marquee-slide-image">
                                <?php if ($mobile_image) : ?>
                                    <source media="(max-width: 768px)" srcset="<?php echo esc_url($mobile_image); ?>">
                                <?php endif; ?>
                                <?php if ($desktop_image) : ?>
                                    <img src="<?php echo esc_url($desktop_image); ?>" 
                                         alt="<?php echo esc_attr($alt_text ?: 'Hero slide ' . ($index + 1)); ?>" 
                                         class="marquee-background-image" />
                                <?php elseif ($mobile_image) : ?>
                                    <img src="<?php echo esc_url($mobile_image); ?>" 
                                         alt="<?php echo esc_attr($alt_text ?: 'Hero slide ' . ($index + 1)); ?>" 
                                         class="marquee-background-image" />
                                <?php endif; ?>
                            </picture>
                        <?php endif; ?>
                        
                        <?php if ($destination_url) : ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($slides) > 1) : ?>
                <div class="marquee-navigation">
                    <?php foreach ($slides as $index => $slide) : ?>
                        <button class="marquee-nav-dot <?php echo ($index === 0) ? 'active' : ''; ?>" 
                                data-slide="<?php echo esc_attr($index); ?>"
                                aria-label="Go to slide <?php echo esc_attr($index + 1); ?>">
                            <span class="screen-reader-text">Slide <?php echo esc_html($index + 1); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Get hero slides
     */
    public function ajax_get_hero_slides() {
        check_ajax_referer('twintack_marketing_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $slides = $this->get_all_hero_slides();
        
        wp_send_json_success(array('slides' => $slides));
    }
    
    /**
     * AJAX: Save hero slide
     */
    public function ajax_save_hero_slide() {
        check_ajax_referer('twintack_marketing_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $slide = isset($_POST['slide']) ? $_POST['slide'] : array();
        
        // Sanitize slide data
        $sanitized_slide = array(
            'id' => isset($slide['id']) ? sanitize_text_field($slide['id']) : uniqid('hero_'),
            'video_desktop' => isset($slide['video_desktop']) ? esc_url_raw($slide['video_desktop']) : '',
            'image_desktop' => isset($slide['image_desktop']) ? esc_url_raw($slide['image_desktop']) : '',
            'image_mobile' => isset($slide['image_mobile']) ? esc_url_raw($slide['image_mobile']) : '',
            'destination_url' => isset($slide['destination_url']) ? esc_url_raw($slide['destination_url']) : '',
            'alt_text' => isset($slide['alt_text']) ? sanitize_text_field($slide['alt_text']) : '',
            'active' => isset($slide['active']) ? sanitize_text_field($slide['active']) : '1',
            'order' => isset($slide['order']) ? intval($slide['order']) : 0
        );
        
        $slides = $this->get_all_hero_slides();
        
        // Update or add slide
        $found = false;
        foreach ($slides as $key => $existing_slide) {
            if ($existing_slide['id'] === $sanitized_slide['id']) {
                $slides[$key] = $sanitized_slide;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $slides[] = $sanitized_slide;
        }
        
        $this->save_hero_slides($slides);
        
        wp_send_json_success(array('slide' => $sanitized_slide, 'message' => 'Hero slide saved'));
    }
    
    /**
     * AJAX: Delete hero slide
     */
    public function ajax_delete_hero_slide() {
        check_ajax_referer('twintack_marketing_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $slide_id = isset($_POST['slide_id']) ? sanitize_text_field($_POST['slide_id']) : '';
        
        $slides = $this->get_all_hero_slides();
        $slides = array_filter($slides, function($slide) use ($slide_id) {
            return $slide['id'] !== $slide_id;
        });
        
        $this->save_hero_slides(array_values($slides));
        
        wp_send_json_success(array('message' => 'Hero slide deleted'));
    }
}

