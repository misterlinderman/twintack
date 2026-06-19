<?php
/**
 * Marketing Admin Interface
 * Provides admin UI for managing marketing features
 */

if (!defined('ABSPATH')) exit;

class TwinTack_Marketing_Admin {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'), 10);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'handle_download_icons'));
    }
    
    public function add_menu_page() {
        add_menu_page(
            __('TwinTack Marketing', 'twintack-marketing'),
            __('Marketing', 'twintack-marketing'),
            'manage_options',
            'twintack-marketing',
            array($this, 'render_admin_page'),
            'dashicons-megaphone',
            30
        );
        
        add_submenu_page(
            'twintack-marketing',
            __('Featured Products', 'twintack-marketing'),
            __('Featured Products', 'twintack-marketing'),
            'manage_options',
            'twintack-marketing-featured',
            array($this, 'render_featured_products_page')
        );
        
        add_submenu_page(
            'twintack-marketing',
            __('Banner Blocks', 'twintack-marketing'),
            __('Banner Blocks', 'twintack-marketing'),
            'manage_options',
            'twintack-marketing-banners',
            array($this, 'render_banner_blocks_page')
        );
        
        add_submenu_page(
            'twintack-marketing',
            __('Hero Carousel', 'twintack-marketing'),
            __('Hero Carousel', 'twintack-marketing'),
            'manage_options',
            'twintack-marketing-hero',
            array($this, 'render_hero_carousel_page')
        );
        
        add_submenu_page(
            'twintack-marketing',
            __('Scrolling Marquee', 'twintack-marketing'),
            __('Scrolling Marquee', 'twintack-marketing'),
            'manage_options',
            'twintack-marketing-marquee',
            array($this, 'render_marquee_page')
        );

        add_submenu_page(
            'twintack-marketing',
            __('How TwinTack Works', 'twintack-marketing'),
            __('How TwinTack Works', 'twintack-marketing'),
            'manage_options',
            'twintack-marketing-video-tabs',
            array($this, 'render_video_tabs_page')
        );

        add_submenu_page(
            'twintack-marketing',
            __('Product Layout', 'twintack-marketing'),
            __('Product Layout', 'twintack-marketing'),
            'manage_options',
            'twintack-marketing-product-layout',
            array($this, 'render_product_layout_page')
        );

        // Add Announcement Bar submenu here to ensure parent exists
        add_submenu_page(
            'twintack-marketing',
            __('Announcement Bar', 'twintack-marketing'),
            __('Announcement Bar', 'twintack-marketing'),
            'manage_options',
            'twintack-announcement-bar',
            array($this, 'render_announcement_bar_page')
        );
        
        // Add Bundle Counter submenu
        add_submenu_page(
            'twintack-marketing',
            __('Bundle Counter', 'twintack-marketing'),
            __('Bundle Counter', 'twintack-marketing'),
            'manage_options',
            'twintack-bundle-counter',
            array($this, 'render_bundle_counter_page')
        );
    }
    
    public function render_marquee_page() {
        TwinTack_Marketing_Marquee::get_instance()->render_settings_page();
    }

    public function render_video_tabs_page() {
        TwinTack_Marketing_Video_Tabs::get_instance()->render_settings_page();
    }

    public function render_product_layout_page() {
        TwinTack_Marketing_Product_Layout::get_instance()->render_settings_page();
    }

    public function render_announcement_bar_page() {
        // Delegate to announcement bar class
        $announcement_bar = TwinTack_Marketing_Announcement_Bar::get_instance();
        $announcement_bar->render_settings_page();
    }
    
    public function render_bundle_counter_page() {
        // Delegate to bundle counter class
        $bundle_counter = TwinTack_Marketing_Bundle_Counter::get_instance();
        $bundle_counter->render_settings_page();
    }
    
    /**
     * Handle manual icon download request
     */
    public function handle_download_icons() {
        if (!isset($_GET['download_marketing_icons']) || !isset($_GET['_wpnonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'download_marketing_icons')) {
            wp_die('Invalid nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Icon mappings
        $icons = array(
            'carousel' => 8116739,
            'product' => 7727037,
            'content' => 7459085,
            'announcement' => 3408481,
            'landing-page' => 8142737,
            'input-product' => 4797115
        );
        
        $icons_dir = plugin_dir_path(__FILE__) . '../assets/images/icons/';
        if (!file_exists($icons_dir)) {
            wp_mkdir_p($icons_dir);
        }
        
        $results = array();
        
        if (class_exists('TwinTack_NounProject_API')) {
            $api = TwinTack_NounProject_API::get_instance();
            
            if ($api->is_configured()) {
                foreach ($icons as $name => $icon_id) {
                    $svg = $api->download_icon_svg($icon_id);
                    
                    if (!is_wp_error($svg)) {
                        $file_path = $icons_dir . $name . '.svg';
                        $saved = file_put_contents($file_path, $svg);
                        
                        if ($saved !== false) {
                            $results[] = "✓ Downloaded {$name}.svg";
                        } else {
                            $results[] = "✗ Failed to save {$name}.svg";
                        }
                    } else {
                        $results[] = "✗ Failed to download {$name}: " . $svg->get_error_message();
                    }
                }
            } else {
                $results[] = "✗ Noun Project API not configured";
            }
        } else {
            $results[] = "✗ Noun Project API class not found";
        }
        
        // Redirect back with results
        $message = urlencode(implode(' | ', $results));
        wp_redirect(admin_url('admin.php?page=twintack-marketing&icon_download_result=' . $message));
        exit;
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'twintack-marketing') === false && 
            strpos($hook, 'twintack-announcement-bar') === false && 
            strpos($hook, 'twintack-bundle-counter') === false &&
            strpos($hook, 'twintack-marketing-video-tabs') === false &&
            strpos($hook, 'twintack-marketing-product-layout') === false) {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
        
        wp_enqueue_style(
            'twintack-marketing-admin',
            plugin_dir_url(__FILE__) . '../assets/css/admin.css',
            array(),
            filemtime(plugin_dir_path(__FILE__) . '../assets/css/admin.css')
        );
        
        wp_enqueue_script(
            'twintack-marketing-admin',
            plugin_dir_url(__FILE__) . '../assets/js/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            filemtime(plugin_dir_path(__FILE__) . '../assets/js/admin.js'),
            true
        );
        
        wp_localize_script('twintack-marketing-admin', 'twintackMarketing', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('twintack_marketing_nonce')
        ));
    }
    
    /**
     * Get icon HTML for dashboard cards
     */
    private function get_card_icon($icon_id) {
        // Icon mapping
        $icons = array(
            'carousel' => 8116739,
            'product' => 7727037,
            'content' => 7459085,
            'announcement' => 3408481,
            'bundle-counter' => 3408481,
            'landing-page' => 8142737,
            'input-product' => 4797115
        );
        
        $icon_path = plugin_dir_path(__FILE__) . '../assets/images/icons/' . $icon_id . '.svg';
        $icon_url = plugin_dir_url(__FILE__) . '../assets/images/icons/' . $icon_id . '.svg';
        
        // If SVG exists locally, use it
        if (file_exists($icon_path)) {
            $svg = file_get_contents($icon_path);
            return '<span class="card-icon">' . $svg . '</span>';
        }
        
        // Otherwise, try to download from Noun Project API if available
        if (class_exists('TwinTack_NounProject_API') && isset($icons[$icon_id])) {
            $api = TwinTack_NounProject_API::get_instance();
            if ($api->is_configured()) {
                $svg = $api->download_icon_svg($icons[$icon_id]);
                if (!is_wp_error($svg)) {
                    // Ensure directory exists
                    $icons_dir = dirname($icon_path);
                    if (!file_exists($icons_dir)) {
                        wp_mkdir_p($icons_dir);
                    }
                    
                    // Save for future use
                    $saved = file_put_contents($icon_path, $svg);
                    if ($saved !== false) {
                        error_log("TwinTack Marketing: Successfully downloaded and saved icon '{$icon_id}' (ID: {$icons[$icon_id]})");
                        return '<span class="card-icon">' . $svg . '</span>';
                    } else {
                        error_log("TwinTack Marketing: Failed to save icon '{$icon_id}' to {$icon_path}");
                    }
                } else {
                    error_log("TwinTack Marketing: Failed to download icon '{$icon_id}' (ID: {$icons[$icon_id]}): " . $svg->get_error_message());
                }
            }
        }
        
        // Fallback to emoji if icon not available
        $emoji_fallbacks = array(
            'carousel' => '🎯',
            'product' => '⭐',
            'content' => '📢',
            'announcement' => '📣',
            'bundle-counter' => '🎁',
            'landing-page' => '📄',
            'input-product' => '🛍️'
        );
        
        return isset($emoji_fallbacks[$icon_id]) ? $emoji_fallbacks[$icon_id] . ' ' : '';
    }
    
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('TwinTack Marketing', 'twintack-marketing'); ?></h1>
            <p class="description"><?php _e('Manage marketing features for your TwinTack website.', 'twintack-marketing'); ?></p>
            
            <div class="twintack-marketing-dashboard">
                <!-- Hero Carousel Card -->
                <div class="twintack-marketing-card">
                    <h2><?php echo $this->get_card_icon('carousel'); ?><?php _e('Hero Carousel', 'twintack-marketing'); ?></h2>
                    <p><?php _e('Manage hero slides for the homepage marketing template. Upload desktop and mobile images with destination URLs.', 'twintack-marketing'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=twintack-marketing-hero'); ?>" class="button button-primary"><?php _e('Manage Hero Slides', 'twintack-marketing'); ?></a>
                </div>
                
                <!-- Featured Products Card -->
                <div class="twintack-marketing-card">
                    <h2><?php echo $this->get_card_icon('product'); ?><?php _e('Featured Products', 'twintack-marketing'); ?></h2>
                    <p><?php _e('Select and order products to feature on your marketing pages. Products will display in a responsive carousel.', 'twintack-marketing'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=twintack-marketing-featured'); ?>" class="button button-primary"><?php _e('Manage Featured Products', 'twintack-marketing'); ?></a>
                </div>
                
                <!-- How TwinTack Works Card -->
                <div class="twintack-marketing-card">
                    <h2><?php echo $this->get_card_icon('content'); ?><?php _e('How TwinTack Works', 'twintack-marketing'); ?></h2>
                    <p><?php _e('Manage the tabbed video section shown on Homepage V2 and Marketing V2 product pages. One set of videos and copy for all grip products.', 'twintack-marketing'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=twintack-marketing-video-tabs'); ?>" class="button button-primary"><?php _e('Manage Video Tabs', 'twintack-marketing'); ?></a>
                </div>

                <!-- Product Layout Card -->
                <div class="twintack-marketing-card">
                    <h2><?php echo $this->get_card_icon('input-product'); ?><?php _e('Product Layout', 'twintack-marketing'); ?></h2>
                    <p><?php _e('Enable the Marketing V2 product page site-wide, or manage layout per product. Includes a go-live checklist.', 'twintack-marketing'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=twintack-marketing-product-layout'); ?>" class="button button-primary"><?php _e('Configure Product Layout', 'twintack-marketing'); ?></a>
                </div>

                <!-- Banner Blocks Card -->
                <div class="twintack-marketing-card">
                    <h2><?php echo $this->get_card_icon('content'); ?><?php _e('Banner Blocks', 'twintack-marketing'); ?></h2>
                    <p><?php _e('Create promotional banner blocks with images and text. Choose between full-width image or 50/50 image and text layouts.', 'twintack-marketing'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=twintack-marketing-banners'); ?>" class="button button-primary"><?php _e('Manage Banner Blocks', 'twintack-marketing'); ?></a>
                </div>
                
                <!-- Announcement Bar Card -->
                <div class="twintack-marketing-card">
                    <h2><?php echo $this->get_card_icon('announcement'); ?><?php _e('Announcement Bar', 'twintack-marketing'); ?></h2>
                    <p><?php _e('Display a site-wide announcement bar with custom text, colors, and optional link. Perfect for promotions and alerts.', 'twintack-marketing'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=twintack-announcement-bar'); ?>" class="button button-primary"><?php _e('Configure Announcement Bar', 'twintack-marketing'); ?></a>
                </div>
                
                <!-- Bundle Counter Card -->
                <div class="twintack-marketing-card">
                    <h2><?php echo $this->get_card_icon('bundle-counter'); ?><?php _e('Bundle Counter', 'twintack-marketing'); ?></h2>
                    <p><?php _e('Show customers their progress toward bundle discounts. Real-time cart tracking with visual progress bar. Works independently from announcement bar.', 'twintack-marketing'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=twintack-bundle-counter'); ?>" class="button button-primary"><?php _e('Configure Bundle Counter', 'twintack-marketing'); ?></a>
                </div>
                
                <!-- Target Pages Card -->
                <div class="twintack-marketing-card">
                    <h2><?php echo $this->get_card_icon('landing-page'); ?><?php _e('Target Pages', 'twintack-marketing'); ?></h2>
                    <p><?php _e('Create campaign target pages with video hero, product grids, and video feature blocks. Use the "Marketing Target Page" template on any page.', 'twintack-marketing'); ?></p>
                    <a href="<?php echo admin_url('post-new.php?post_type=page'); ?>" class="button button-primary"><?php _e('Create Target Page', 'twintack-marketing'); ?></a>
                </div>
                
                <!-- Templates Card -->
                <div class="twintack-marketing-card">
                    <h2><?php echo $this->get_card_icon('landing-page'); ?><?php _e('Marketing Templates', 'twintack-marketing'); ?></h2>
                    <p><?php _e('Apply the Marketing Homepage template to any page to use the hero carousel, featured products, and banner blocks.', 'twintack-marketing'); ?></p>
                    <a href="<?php echo admin_url('edit.php?post_type=page'); ?>" class="button"><?php _e('Edit Pages', 'twintack-marketing'); ?></a>
                </div>
                
                <!-- Product Features Card -->
                <div class="twintack-marketing-card">
                    <h2><?php echo $this->get_card_icon('input-product'); ?><?php _e('Product Management', 'twintack-marketing'); ?></h2>
                    <p><?php _e('Manage your WooCommerce products, including pricing, inventory, and product details.', 'twintack-marketing'); ?></p>
                    <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button"><?php _e('Manage Products', 'twintack-marketing'); ?></a>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function render_featured_products_page() {
        $context = isset($_GET['context']) ? sanitize_text_field($_GET['context']) : 'homepage';
        $featured_products = TwinTack_Marketing_Featured_Products::get_instance()->get_featured_products($context);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Featured Products', 'twintack-marketing'); ?></h1>
            <p class="description"><?php _e('Select and arrange products to feature on your marketing pages. Products can be reordered by dragging.', 'twintack-marketing'); ?></p>
            
            <div class="twintack-marketing-admin">
                <div class="twintack-context-selector">
                    <label for="featured-context"><?php _e('Context:', 'twintack-marketing'); ?></label>
                    <select id="featured-context" name="context">
                        <option value="homepage" <?php selected($context, 'homepage'); ?>><?php _e('Homepage', 'twintack-marketing'); ?></option>
                        <option value="homepage-v2" <?php selected($context, 'homepage-v2'); ?>><?php _e('Homepage V2 (Preview)', 'twintack-marketing'); ?></option>
                        <option value="landing" <?php selected($context, 'landing'); ?>><?php _e('Landing Pages', 'twintack-marketing'); ?></option>
                    </select>
                </div>
                
                <div class="twintack-featured-products-manager">
                    <h2><?php _e('Selected Featured Products', 'twintack-marketing'); ?></h2>
                    <div id="featured-products-list" class="twintack-products-list sortable">
                        <?php foreach ($featured_products as $product) : ?>
                            <div class="twintack-product-item" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
                                <?php echo $product->get_image('thumbnail'); ?>
                                <div class="product-info">
                                    <strong><?php echo esc_html($product->get_name()); ?></strong>
                                    <span class="product-price"><?php echo $product->get_price_html(); ?></span>
                                </div>
                                <button class="remove-product" aria-label="<?php esc_attr_e('Remove', 'twintack-marketing'); ?>">×</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" class="button button-primary add-featured-product">
                        <?php _e('Add Product', 'twintack-marketing'); ?>
                    </button>
                    
                    <button type="button" class="button save-featured-products">
                        <?php _e('Save Featured Products', 'twintack-marketing'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function render_banner_blocks_page() {
        $context = isset($_GET['context']) ? sanitize_text_field($_GET['context']) : 'homepage';
        $banner_blocks = TwinTack_Marketing_Banner_Blocks::get_instance()->get_banner_blocks($context);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Banner Blocks', 'twintack-marketing'); ?></h1>
            <p class="description"><?php _e('Create promotional banner blocks with images and optional text. Choose between full-width or 50/50 layouts.', 'twintack-marketing'); ?></p>
            <?php if ('homepage-v2' === $context) : ?>
                <p class="description"><strong><?php esc_html_e('Homepage V2:', 'twintack-marketing'); ?></strong> <?php esc_html_e('Blocks render as image-background cards under “Built for Peak Performance.” Tag line + title + CTA show by default; hover description reveals on mouseover.', 'twintack-marketing'); ?></p>
            <?php endif; ?>
            
            <div class="twintack-marketing-admin">
                <div class="twintack-context-selector">
                    <label for="banner-context"><?php _e('Context:', 'twintack-marketing'); ?></label>
                    <select id="banner-context" name="context">
                        <option value="homepage" <?php selected($context, 'homepage'); ?>><?php _e('Homepage', 'twintack-marketing'); ?></option>
                        <option value="homepage-v2" <?php selected($context, 'homepage-v2'); ?>><?php _e('Homepage V2 (Preview)', 'twintack-marketing'); ?></option>
                        <option value="landing" <?php selected($context, 'landing'); ?>><?php _e('Landing Pages', 'twintack-marketing'); ?></option>
                    </select>
                </div>
                
                <div class="twintack-banner-blocks-manager">
                    <button type="button" class="button button-primary add-banner-block">
                        <?php _e('Add Banner Block', 'twintack-marketing'); ?>
                    </button>
                    
                    <div id="banner-blocks-list" class="twintack-banner-blocks-list sortable">
                        <?php foreach ($banner_blocks as $block) : ?>
                            <?php $this->render_banner_block_editor($block, $context); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Banner Block Template (hidden) -->
        <div id="banner-block-template" style="display: none;">
            <?php $this->render_banner_block_editor(array('id' => '', 'layout' => 'fullwidth'), $context, true); ?>
        </div>
        <?php
    }
    
    private function render_banner_block_editor($block, $context, $is_template = false) {
        $block_id = isset($block['id']) ? $block['id'] : uniqid('banner_');
        $layout = isset($block['layout']) ? $block['layout'] : 'fullwidth';
        $image_desktop = isset($block['image_desktop']) ? $block['image_desktop'] : '';
        $image_mobile = isset($block['image_mobile']) ? $block['image_mobile'] : '';
        $title = isset($block['title']) ? $block['title'] : '';
        $text = isset($block['text']) ? $block['text'] : '';
        $description = isset($block['description']) ? $block['description'] : '';
        $cta_text = isset($block['cta_text']) ? $block['cta_text'] : '';
        $cta_link = isset($block['cta_link']) ? $block['cta_link'] : '';
        $link = isset($block['link']) ? $block['link'] : '';
        $order = isset($block['order']) ? $block['order'] : 0;
        $is_v2_overlay = ('homepage-v2' === $context);
        
        ?>
        <div class="twintack-banner-block-editor" data-block-id="<?php echo esc_attr($block_id); ?>" data-context="<?php echo esc_attr($context); ?>">
            <div class="banner-block-header">
                <h3><?php _e('Banner Block', 'twintack-marketing'); ?> <span class="block-id"><?php echo esc_html($block_id); ?></span></h3>
                <button type="button" class="button remove-banner-block"><?php _e('Remove', 'twintack-marketing'); ?></button>
            </div>
            
            <div class="banner-block-content">
                <table class="form-table">
                    <tr>
                        <th><label><?php _e('Layout', 'twintack-marketing'); ?></label></th>
                        <td>
                            <select name="layout" class="banner-layout">
                                <option value="fullwidth" <?php selected($layout, 'fullwidth'); ?>><?php _e('Full Width Image', 'twintack-marketing'); ?></option>
                                <option value="50-50" <?php selected($layout, '50-50'); ?>><?php _e('50/50 Image & Text', 'twintack-marketing'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Desktop Image', 'twintack-marketing'); ?></label></th>
                        <td>
                            <div class="image-upload-wrapper">
                                <input type="hidden" name="image_desktop" class="image-url" value="<?php echo esc_attr($image_desktop); ?>" />
                                <div class="image-preview">
                                    <?php if ($image_desktop) : ?>
                                        <img src="<?php echo esc_url($image_desktop); ?>" alt="" />
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="button upload-image"><?php _e('Upload Image', 'twintack-marketing'); ?></button>
                                <button type="button" class="button remove-image" style="<?php echo $image_desktop ? '' : 'display:none;'; ?>"><?php _e('Remove', 'twintack-marketing'); ?></button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Mobile Image (Optional)', 'twintack-marketing'); ?></label></th>
                        <td>
                            <div class="image-upload-wrapper">
                                <input type="hidden" name="image_mobile" class="image-url" value="<?php echo esc_attr($image_mobile); ?>" />
                                <div class="image-preview">
                                    <?php if ($image_mobile) : ?>
                                        <img src="<?php echo esc_url($image_mobile); ?>" alt="" />
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="button upload-image"><?php _e('Upload Image', 'twintack-marketing'); ?></button>
                                <button type="button" class="button remove-image" style="<?php echo $image_mobile ? '' : 'display:none;'; ?>"><?php _e('Remove', 'twintack-marketing'); ?></button>
                            </div>
                        </td>
                    </tr>
                    <?php if ($is_v2_overlay) : ?>
                    <tr class="v2-overlay-fields">
                        <th><label><?php _e('Tag line', 'twintack-marketing'); ?></label></th>
                        <td>
                            <input type="text" name="text" class="regular-text" value="<?php echo esc_attr(wp_strip_all_tags($text)); ?>" placeholder="<?php esc_attr_e('Ready to Ship', 'twintack-marketing'); ?>">
                            <p class="description"><?php _e('Small label above the title (e.g. “New Drop”).', 'twintack-marketing'); ?></p>
                        </td>
                    </tr>
                    <tr class="v2-overlay-fields">
                        <th><label><?php _e('Title', 'twintack-marketing'); ?></label></th>
                        <td>
                            <input type="text" name="title" class="regular-text" value="<?php echo esc_attr($title); ?>" placeholder="<?php esc_attr_e('Shop Grips', 'twintack-marketing'); ?>">
                        </td>
                    </tr>
                    <tr class="v2-overlay-fields">
                        <th><label><?php _e('Hover description', 'twintack-marketing'); ?></label></th>
                        <td>
                            <textarea name="description" class="large-text" rows="3"><?php echo esc_textarea($description); ?></textarea>
                            <p class="description"><?php _e('Reveals on hover between the title and button.', 'twintack-marketing'); ?></p>
                        </td>
                    </tr>
                    <tr class="v2-overlay-fields">
                        <th><label><?php _e('CTA Text', 'twintack-marketing'); ?></label></th>
                        <td>
                            <input type="text" name="cta_text" class="regular-text" value="<?php echo esc_attr($cta_text); ?>" placeholder="<?php esc_attr_e('Shop Now', 'twintack-marketing'); ?>">
                        </td>
                    </tr>
                    <tr class="v2-overlay-fields">
                        <th><label><?php _e('CTA Link', 'twintack-marketing'); ?></label></th>
                        <td>
                            <input type="url" name="cta_link" class="regular-text" value="<?php echo esc_attr($cta_link); ?>">
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr class="text-fields" style="<?php echo ($is_v2_overlay || $layout !== '50-50') ? 'display:none;' : ''; ?>">
                        <th><label><?php _e('Title', 'twintack-marketing'); ?></label></th>
                        <td>
                            <input type="text" name="title" class="regular-text" value="<?php echo esc_attr($title); ?>" />
                        </td>
                    </tr>
                    <tr class="text-fields" style="<?php echo ($is_v2_overlay || $layout !== '50-50') ? 'display:none;' : ''; ?>">
                        <th><label><?php _e('Text', 'twintack-marketing'); ?></label></th>
                        <td>
                            <?php wp_editor($text, 'banner_text_' . $block_id, array(
                                'textarea_name' => 'text',
                                'textarea_rows' => 5,
                                'media_buttons' => false,
                                'teeny' => true
                            )); ?>
                        </td>
                    </tr>
                    <tr class="text-fields" style="<?php echo ($is_v2_overlay || $layout !== '50-50') ? 'display:none;' : ''; ?>">
                        <th><label><?php _e('CTA Text', 'twintack-marketing'); ?></label></th>
                        <td>
                            <input type="text" name="cta_text" class="regular-text" value="<?php echo esc_attr($cta_text); ?>" />
                        </td>
                    </tr>
                    <tr class="text-fields" style="<?php echo ($is_v2_overlay || $layout !== '50-50') ? 'display:none;' : ''; ?>">
                        <th><label><?php _e('CTA Link', 'twintack-marketing'); ?></label></th>
                        <td>
                            <input type="url" name="cta_link" class="regular-text" value="<?php echo esc_attr($cta_link); ?>" />
                        </td>
                    </tr>
                    <tr class="link-field" style="<?php echo ($is_v2_overlay || $layout !== 'fullwidth') ? 'display:none;' : ''; ?>">
                        <th><label><?php _e('Link URL (Optional)', 'twintack-marketing'); ?></label></th>
                        <td>
                            <input type="url" name="link" class="regular-text" value="<?php echo esc_attr($link); ?>" />
                            <p class="description"><?php _e('Make the entire image clickable', 'twintack-marketing'); ?></p>
                        </td>
                    </tr>
                    <input type="hidden" name="order" class="block-order" value="<?php echo esc_attr($order); ?>" />
                </table>
                
                <button type="button" class="button button-primary save-banner-block"><?php _e('Save Block', 'twintack-marketing'); ?></button>
            </div>
        </div>
        <?php
    }
    
    public function render_hero_carousel_page() {
        $hero_carousel = TwinTack_Marketing_Hero_Carousel::get_instance();
        $slides = $hero_carousel->get_all_hero_slides();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Hero Carousel', 'twintack-marketing'); ?></h1>
            <p class="description"><?php _e('Manage hero carousel slides for the homepage marketing template. Upload desktop and mobile images with destination URLs.', 'twintack-marketing'); ?></p>
            
            <div class="twintack-marketing-admin">
                <div class="twintack-hero-carousel-manager">
                    <button type="button" class="button button-primary add-hero-slide">
                        <?php _e('Add Hero Slide', 'twintack-marketing'); ?>
                    </button>
                    
                    <div id="hero-slides-list" class="twintack-hero-slides-list sortable">
                        <?php foreach ($slides as $slide) : ?>
                            <?php $this->render_hero_slide_editor($slide); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Hero Slide Template (hidden) -->
        <div id="hero-slide-template" style="display: none;">
            <?php $this->render_hero_slide_editor(array('id' => '', 'video_desktop' => '', 'image_desktop' => '', 'image_mobile' => '', 'destination_url' => '', 'alt_text' => '', 'active' => '1', 'order' => 0), true); ?>
        </div>
        <?php
    }
    
    private function render_hero_slide_editor($slide, $is_template = false) {
        $slide_id = isset($slide['id']) ? $slide['id'] : uniqid('hero_');
        $video_desktop = isset($slide['video_desktop']) ? $slide['video_desktop'] : '';
        $image_desktop = isset($slide['image_desktop']) ? $slide['image_desktop'] : '';
        $image_mobile = isset($slide['image_mobile']) ? $slide['image_mobile'] : '';
        $destination_url = isset($slide['destination_url']) ? $slide['destination_url'] : '';
        $alt_text = isset($slide['alt_text']) ? $slide['alt_text'] : '';
        $active = isset($slide['active']) ? $slide['active'] : '1';
        $order = isset($slide['order']) ? $slide['order'] : 0;
        
        ?>
        <div class="twintack-hero-slide-editor" data-slide-id="<?php echo esc_attr($slide_id); ?>">
            <div class="hero-slide-header">
                <h3><?php _e('Hero Slide', 'twintack-marketing'); ?> <span class="slide-id"><?php echo esc_html($slide_id); ?></span></h3>
                <div class="hero-slide-actions">
                    <label>
                        <input type="checkbox" name="active" class="slide-active" value="1" <?php checked($active, '1'); ?> />
                        <?php _e('Active', 'twintack-marketing'); ?>
                    </label>
                    <button type="button" class="button remove-hero-slide"><?php _e('Remove', 'twintack-marketing'); ?></button>
                </div>
            </div>
            
            <div class="hero-slide-content">
                <table class="form-table">
                    <tr>
                        <th><label><?php _e('Desktop Video (MP4)', 'twintack-marketing'); ?></label></th>
                        <td>
                            <div class="video-upload-wrapper">
                                <input type="hidden" name="video_desktop" class="video-url" value="<?php echo esc_attr($video_desktop); ?>" />
                                <div class="video-preview">
                                    <?php if ($video_desktop) : ?>
                                        <video src="<?php echo esc_url($video_desktop); ?>" style="max-width: 300px; height: auto;" muted></video>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="button upload-video"><?php _e('Upload Desktop Video', 'twintack-marketing'); ?></button>
                                <button type="button" class="button remove-video" style="<?php echo $video_desktop ? '' : 'display:none;'; ?>"><?php _e('Remove', 'twintack-marketing'); ?></button>
                                <p class="description"><?php _e('Optional: Upload an MP4 video for desktop. Will autoplay muted and loop.', 'twintack-marketing'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Desktop Image', 'twintack-marketing'); ?></label></th>
                        <td>
                            <div class="image-upload-wrapper">
                                <input type="hidden" name="image_desktop" class="image-url" value="<?php echo esc_attr($image_desktop); ?>" />
                                <div class="image-preview">
                                    <?php if ($image_desktop) : ?>
                                        <img src="<?php echo esc_url($image_desktop); ?>" alt="" style="max-width: 300px; height: auto;" />
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="button upload-image"><?php _e('Upload Desktop Image', 'twintack-marketing'); ?></button>
                                <button type="button" class="button remove-image" style="<?php echo $image_desktop ? '' : 'display:none;'; ?>"><?php _e('Remove', 'twintack-marketing'); ?></button>
                                <p class="description"><?php _e('If video is set, this image will be used as a fallback/poster. Otherwise used as the main desktop image.', 'twintack-marketing'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Mobile Image', 'twintack-marketing'); ?></label></th>
                        <td>
                            <div class="image-upload-wrapper">
                                <input type="hidden" name="image_mobile" class="image-url" value="<?php echo esc_attr($image_mobile); ?>" />
                                <div class="image-preview">
                                    <?php if ($image_mobile) : ?>
                                        <img src="<?php echo esc_url($image_mobile); ?>" alt="" style="max-width: 300px; height: auto;" />
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="button upload-image"><?php _e('Upload Mobile Image', 'twintack-marketing'); ?></button>
                                <button type="button" class="button remove-image" style="<?php echo $image_mobile ? '' : 'display:none;'; ?>"><?php _e('Remove', 'twintack-marketing'); ?></button>
                                <p class="description"><?php _e('Static image for mobile devices (video will not play on mobile).', 'twintack-marketing'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Destination URL', 'twintack-marketing'); ?></label></th>
                        <td>
                            <input type="url" name="destination_url" class="regular-text" value="<?php echo esc_attr($destination_url); ?>" placeholder="https://example.com" />
                            <p class="description"><?php _e('URL to link to when the slide is clicked (optional)', 'twintack-marketing'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php _e('Alt Text', 'twintack-marketing'); ?></label></th>
                        <td>
                            <input type="text" name="alt_text" class="regular-text" value="<?php echo esc_attr($alt_text); ?>" placeholder="Descriptive text for accessibility" />
                            <p class="description"><?php _e('Alt text for the image/video (recommended for accessibility)', 'twintack-marketing'); ?></p>
                        </td>
                    </tr>
                    <input type="hidden" name="order" class="slide-order" value="<?php echo esc_attr($order); ?>" />
                </table>
                
                <button type="button" class="button button-primary save-hero-slide"><?php _e('Save Slide', 'twintack-marketing'); ?></button>
            </div>
        </div>
        <?php
    }
}

