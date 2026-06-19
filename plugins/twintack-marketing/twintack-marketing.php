<?php
/**
 * Plugin Name: TwinTack Marketing
 * Description: Marketing features for TwinTack homepage including hero carousel, featured products, banner blocks, announcement bar, bundle counter, and landing page templates.
 * Version: 1.2.0
 * Author: TwinTack Team
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Note: Product page features (videos, color schemes) are currently disabled
 */

if (!defined('ABSPATH')) exit;

class TwinTack_Marketing {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'), 10);
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function init() {
        // Check WooCommerce dependency
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load includes
        // DISABLED: Product page features (causing critical errors)
        // require_once plugin_dir_path(__FILE__) . 'includes/class-marketing-product-video.php';
        // require_once plugin_dir_path(__FILE__) . 'includes/class-marketing-product-colors.php';
        
        // ACTIVE: Homepage and site-wide features only
        require_once plugin_dir_path(__FILE__) . 'includes/class-marketing-announcement-bar.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-marketing-bundle-counter.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-marketing-featured-products.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-marketing-banner-blocks.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-marketing-landing-page.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-marketing-target-page.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-marketing-hero-carousel.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-marketing-homepage-v2.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-marketing-product-layout.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-marketing-video-tabs.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-marketing-marquee.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-marketing-vibe-pixel.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-marketing-admin.php';
        
        // Initialize components
        // DISABLED: Product page features (causing critical errors)
        // TwinTack_Marketing_Product_Video::get_instance();
        // TwinTack_Marketing_Product_Colors::get_instance();
        
        // ACTIVE: Homepage and site-wide features only
        TwinTack_Marketing_Announcement_Bar::get_instance();
        TwinTack_Marketing_Bundle_Counter::get_instance();
        TwinTack_Marketing_Featured_Products::get_instance();
        TwinTack_Marketing_Banner_Blocks::get_instance();
        TwinTack_Marketing_Landing_Page::get_instance();
        TwinTack_Marketing_Target_Page::get_instance();
        TwinTack_Marketing_Hero_Carousel::get_instance();
        TwinTack_Marketing_Homepage_V2::get_instance();
        TwinTack_Marketing_Product_Layout::get_instance();
        TwinTack_Marketing_Video_Tabs::get_instance();
        TwinTack_Marketing_Marquee::get_instance();
        TwinTack_Marketing_Vibe_Pixel::get_instance();
        
        // Admin interface
        if (is_admin()) {
            TwinTack_Marketing_Admin::get_instance();
        }
        
        // Enqueue styles and scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function enqueue_assets() {
        // Enqueue Slick Carousel for featured products
        wp_enqueue_style(
            'slick',
            'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css',
            array(),
            '1.8.1'
        );
        wp_enqueue_style(
            'slick-theme',
            'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css',
            array('slick'),
            '1.8.1'
        );
        wp_enqueue_script(
            'slick',
            'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js',
            array('jquery'),
            '1.8.1',
            true
        );
        
        wp_enqueue_style(
            'twintack-marketing',
            plugin_dir_url(__FILE__) . 'assets/css/marketing.css',
            array('slick', 'slick-theme'),
            filemtime(plugin_dir_path(__FILE__) . 'assets/css/marketing.css')
        );
        
        wp_enqueue_script(
            'twintack-marketing',
            plugin_dir_url(__FILE__) . 'assets/js/marketing.js',
            array('jquery', 'slick'),
            filemtime(plugin_dir_path(__FILE__) . 'assets/js/marketing.js'),
            true
        );
        
        // Target page assets — only when the template is active
        if (is_page_template('templates/template-target-page.php')) {
            wp_enqueue_style(
                'twintack-marketing-target-page',
                plugin_dir_url(__FILE__) . 'assets/css/marketing-target-page.css',
                array('twintack-marketing'),
                filemtime(plugin_dir_path(__FILE__) . 'assets/css/marketing-target-page.css')
            );

            wp_enqueue_script(
                'twintack-marketing-target-page',
                plugin_dir_url(__FILE__) . 'assets/js/marketing-target-page.js',
                array('jquery', 'slick'),
                filemtime(plugin_dir_path(__FILE__) . 'assets/js/marketing-target-page.js'),
                true
            );
        }

        $is_homepage_v2 = is_page_template('templates/template-homepage-v2.php');
        $is_product_v2  = class_exists('TwinTack_Marketing_Product_Layout')
            && TwinTack_Marketing_Product_Layout::is_marketing_v2();

        if ($is_homepage_v2 || $is_product_v2) {
            if ($is_product_v2) {
                wp_enqueue_style(
                    'font-awesome-6',
                    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
                    array(),
                    '6.5.0'
                );
            }

            wp_enqueue_style(
                'twintack-marketing-v2',
                plugin_dir_url(__FILE__) . 'assets/css/marketing-v2.css',
                array('twintack-marketing'),
                filemtime(plugin_dir_path(__FILE__) . 'assets/css/marketing-v2.css')
            );

            wp_enqueue_script(
                'twintack-marketing-v2',
                plugin_dir_url(__FILE__) . 'assets/js/marketing-v2.js',
                array('jquery'),
                filemtime(plugin_dir_path(__FILE__) . 'assets/js/marketing-v2.js'),
                true
            );
        }
    }

    public function activate() {
        flush_rewrite_rules();
    }

    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('TwinTack Marketing requires WooCommerce to be installed and active.', 'twintack-marketing'); ?></p>
        </div>
        <?php
    }
}

// Initialize the plugin
TwinTack_Marketing::get_instance();

