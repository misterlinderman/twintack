<?php
/**
 * Assets Management
 *
 * @package TwinTackHowToVideos
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TwinTack_HTV_Assets Class
 */
class TwinTack_HTV_Assets {
    
    /**
     * Instance of this class
     * @var TwinTack_HTV_Assets
     */
    private static $instance = null;
    
    /**
     * Get the single instance of this class
     * @return TwinTack_HTV_Assets
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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue on relevant pages
        if (!$this->should_enqueue_frontend_assets()) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'twintack-htv-frontend',
            TWINTACK_HTV_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            TWINTACK_HTV_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'twintack-htv-frontend',
            TWINTACK_HTV_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            TWINTACK_HTV_VERSION,
            true
        );
        
        // Localize script with data
        wp_localize_script('twintack-htv-frontend', 'twintackHTV', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('twintack_htv_nonce'),
            'strings' => array(
                'close' => __('Close', 'twintack-how-to-videos'),
                'loading' => __('Loading...', 'twintack-how-to-videos'),
                'error' => __('Error loading video', 'twintack-how-to-videos'),
            )
        ));
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only enqueue on relevant admin pages
        if (!$this->should_enqueue_admin_assets($hook)) {
            return;
        }
        
        wp_enqueue_style(
            'twintack-htv-admin',
            TWINTACK_HTV_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            TWINTACK_HTV_VERSION
        );
        
        wp_enqueue_script(
            'twintack-htv-admin',
            TWINTACK_HTV_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            TWINTACK_HTV_VERSION,
            true
        );
    }
    
    /**
     * Check if frontend assets should be enqueued
     */
    private function should_enqueue_frontend_assets() {
        // Check if this is a page that needs video functionality
        $should_enqueue = false;
        
        // Product pages
        if (is_product()) {
            $should_enqueue = true;
        }
        
        // Sport pages (using template)
        if (is_page() && get_page_template_slug() === 'templates/template-sport.php') {
            $should_enqueue = true;
        }
        
        // How-to pages
        if (is_page() && get_page_template_slug() === 'templates/template-how-to.php') {
            $should_enqueue = true;
        }
        
        // Single video pages
        if (is_singular('how_to_video')) {
            $should_enqueue = true;
        }
        
        // Check if page content contains shortcodes
        global $post;
        if (is_a($post, 'WP_Post')) {
            if (has_shortcode($post->post_content, 'twintack_how_to_videos') || 
                has_shortcode($post->post_content, 'twintack_video_carousel')) {
                $should_enqueue = true;
            }
        }
        
        // Check if any video display functions are being called
        if (did_action('twintack_htv_display_videos')) {
            $should_enqueue = true;
        }
        
        // If we detect video-related content in any flexible content or ACF fields
        if (function_exists('have_rows') && have_rows('content_configurations')) {
            $should_enqueue = true;
        }
        
        // Allow themes/plugins to override
        return apply_filters('twintack_htv_should_enqueue_frontend_assets', $should_enqueue);
    }
    
    /**
     * Check if admin assets should be enqueued
     */
    private function should_enqueue_admin_assets($hook) {
        $admin_pages = array(
            'how_to_video',
            'edit-how_to_video',
            'how_to_video_page_video-sport-assignment',
        );
        
        // Check if we're on a how-to video admin page
        return in_array($hook, $admin_pages) || 
               strpos($hook, 'how_to_video') !== false ||
               (isset($_GET['post_type']) && $_GET['post_type'] === 'how_to_video');
    }
    
    /**
     * Get inline CSS for dynamic styling
     * This allows the theme to customize video player appearance
     */
    public static function get_inline_css() {
        $css = '';
        
        // Allow themes to modify colors and styling
        $primary_color = apply_filters('twintack_htv_primary_color', '#000000');
        $accent_color = apply_filters('twintack_htv_accent_color', '#ffffff');
        $background_color = apply_filters('twintack_htv_background_color', '#f5f5f7');
        
        if ($primary_color !== '#000000' || $accent_color !== '#ffffff' || $background_color !== '#f5f5f7') {
            $css = "
                .twintack-video-carousel-section {
                    background-color: {$background_color};
                }
                .twintack-video-modal-content {
                    background-color: {$primary_color};
                }
                .twintack-video-modal-close {
                    color: {$accent_color};
                }
                .twintack-video-modal-title {
                    color: {$accent_color};
                }
            ";
        }
        
        return apply_filters('twintack_htv_inline_css', $css);
    }
    
    /**
     * Add inline CSS if needed
     */
    public function add_inline_css() {
        $inline_css = self::get_inline_css();
        
        if (!empty($inline_css)) {
            wp_add_inline_style('twintack-htv-frontend', $inline_css);
        }
    }
} 