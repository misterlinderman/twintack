<?php
/**
 * Frontend Functionality
 *
 * @package TwinTackHowToVideos
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TwinTack_HTV_Frontend Class
 */
class TwinTack_HTV_Frontend {
    
    /**
     * Instance of this class
     * @var TwinTack_HTV_Frontend
     */
    private static $instance = null;
    
    /**
     * Get the single instance of this class
     * @return TwinTack_HTV_Frontend
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
        
        // Add hooks for theme integration
        add_action('twintack_htv_display_videos', array($this, 'display_videos_hook'), 10, 2);
    }
    
    /**
     * Initialize shortcodes
     */
    public function init_shortcodes() {
        add_shortcode('twintack_how_to_videos', array($this, 'videos_shortcode'));
        add_shortcode('twintack_video_carousel', array($this, 'videos_shortcode')); // Alias
    }
    
    /**
     * Videos shortcode callback
     * 
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function videos_shortcode($atts) {
        $atts = shortcode_atts(array(
            'sport' => '',
            'category' => '',
            'limit' => 5,
            'layout' => 'carousel',
            'title' => '',
            'show_title' => 'true',
            'class' => '',
        ), $atts, 'twintack_how_to_videos');
        
        // Sanitize attributes
        $sport = sanitize_text_field($atts['sport']);
        $category = sanitize_text_field($atts['category']);
        $limit = intval($atts['limit']);
        $layout = sanitize_text_field($atts['layout']);
        $title = sanitize_text_field($atts['title']);
        $show_title = $atts['show_title'] === 'true';
        $class = sanitize_html_class($atts['class']);
        
        // Validate layout
        $allowed_layouts = array('carousel', 'grid', 'list', 'simple');
        if (!in_array($layout, $allowed_layouts)) {
            $layout = 'carousel';
        }
        
        // Build display arguments
        $display_args = array(
            'sport' => $sport,
            'category' => $category,
            'limit' => $limit,
            'layout' => $layout,
            'title' => $title,
            'show_title' => $show_title,
            'class' => $class,
        );
        
        // If no sport or category specified, try to determine from context
        if (empty($sport) && empty($category)) {
            if (is_product()) {
                $display_args['sport'] = twintack_htv_get_product_sport(get_the_ID());
            } else {
                $page_title = strtolower(get_the_title());
                $display_args['sport'] = sanitize_title($page_title);
            }
        }
        
        // Get videos based on criteria
        if (!empty($category)) {
            $videos = twintack_htv_get_videos_by_category($category, $limit);
        } elseif (!empty($sport)) {
            $videos = twintack_htv_get_videos_by_sport($sport, $limit);
        } else {
            $videos = twintack_htv_get_videos($display_args);
        }
        
        if (empty($videos)) {
            return '';
        }
        
        // Start output buffering
        ob_start();
        
        // Add wrapper class to identify shortcode usage
        $wrapper_class = 'twintack-htv-shortcode twintack-htv-layout-' . $layout;
        if (!empty($class)) {
            $wrapper_class .= ' ' . $class;
        }
        
        // Add the shortcode class to the display args so templates can use it
        $display_args['shortcode_class'] = $wrapper_class;
        
        echo '<div class="' . esc_attr($wrapper_class) . '">';
        
        // Determine template based on layout
        $template_file = 'video-' . $layout . '.php';
        
        // Allow themes to override the template
        $theme_template = locate_template('twintack-how-to-videos/' . $template_file);
        
        if ($theme_template) {
            $args = $display_args; // Make args available to template
            include $theme_template;
        } elseif (file_exists(TWINTACK_HTV_PLUGIN_DIR . 'templates/' . $template_file)) {
            $args = $display_args; // Make args available to template
            include TWINTACK_HTV_PLUGIN_DIR . 'templates/' . $template_file;
        } else {
            // Fallback to carousel template
            $args = $display_args; // Make args available to template
            include TWINTACK_HTV_PLUGIN_DIR . 'templates/video-carousel.php';
        }
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Maybe enqueue assets based on content
     */
    public function maybe_enqueue_assets() {
        global $post;
        
        // Check if the current page contains our shortcode
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'twintack_how_to_videos')) {
            // Force asset enqueuing
            add_filter('twintack_htv_should_enqueue_frontend_assets', '__return_true');
        }
        
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'twintack_video_carousel')) {
            // Force asset enqueuing
            add_filter('twintack_htv_should_enqueue_frontend_assets', '__return_true');
        }
    }
    
    /**
     * Hook for displaying videos
     * Allows themes to call do_action('twintack_htv_display_videos', $sport, $limit)
     */
    public function display_videos_hook($sport = '', $limit = 5) {
        twintack_htv_display_videos($sport, $limit);
    }
    
    /**
     * Get video data for AJAX requests
     */
    public function ajax_get_video_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'twintack_htv_nonce')) {
            wp_die('Security check failed');
        }
        
        $video_id = intval($_POST['video_id']);
        
        if (!$video_id || get_post_type($video_id) !== 'how_to_video') {
            wp_send_json_error('Invalid video ID');
        }
        
        $video_data = array(
            'id' => $video_id,
            'title' => get_the_title($video_id),
            'vimeo_url' => get_post_meta($video_id, '_htv_vimeo_url', true),
            'duration' => get_post_meta($video_id, '_htv_video_duration', true),
            'excerpt' => get_the_excerpt($video_id),
        );
        
        wp_send_json_success($video_data);
    }
    
    /**
     * Add body classes when videos are displayed
     */
    public function add_body_classes($classes) {
        if (is_product() && twintack_htv_has_videos_for_sport(twintack_htv_get_product_sport(get_the_ID()))) {
            $classes[] = 'has-how-to-videos';
        }
        
        if (is_singular('how_to_video')) {
            $classes[] = 'single-how-to-video';
        }
        
        return $classes;
    }
} 