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
        
        // Add Announcement Bar submenu here to ensure parent exists
        add_submenu_page(
            'twintack-marketing',
            __('Announcement Bar', 'twintack-marketing'),
            __('Announcement Bar', 'twintack-marketing'),
            'manage_options',
            'twintack-announcement-bar',
            array($this, 'render_announcement_bar_page')
        );
    }
    
    public function render_announcement_bar_page() {
        // Delegate to announcement bar class
        $announcement_bar = TwinTack_Marketing_Announcement_Bar::get_instance();
        $announcement_bar->render_settings_page();
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'twintack-marketing') === false && strpos($hook, 'twintack-announcement-bar') === false) {
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
    
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('TwinTack Marketing', 'twintack-marketing'); ?></h1>
            <p><?php _e('Manage marketing features for your TwinTack website.', 'twintack-marketing'); ?></p>
            
            <div class="twintack-marketing-dashboard">
                <div class="twintack-marketing-card">
                    <h2><?php _e('Quick Links', 'twintack-marketing'); ?></h2>
                    <ul>
                        <li><a href="<?php echo admin_url('admin.php?page=twintack-marketing-hero'); ?>"><?php _e('Manage Hero Carousel', 'twintack-marketing'); ?></a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=twintack-marketing-featured'); ?>"><?php _e('Manage Featured Products', 'twintack-marketing'); ?></a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=twintack-marketing-banners'); ?>"><?php _e('Manage Banner Blocks', 'twintack-marketing'); ?></a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=twintack-announcement-bar'); ?>"><?php _e('Announcement Bar Settings', 'twintack-marketing'); ?></a></li>
                    </ul>
                </div>
                
                <div class="twintack-marketing-card">
                    <h2><?php _e('Product Features', 'twintack-marketing'); ?></h2>
                    <p><?php _e('To add marketing videos or change color schemes for products, edit individual products in WooCommerce.', 'twintack-marketing'); ?></p>
                    <p><a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button"><?php _e('Manage Products', 'twintack-marketing'); ?></a></p>
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
            
            <div class="twintack-marketing-admin">
                <div class="twintack-context-selector">
                    <label for="featured-context"><?php _e('Context:', 'twintack-marketing'); ?></label>
                    <select id="featured-context" name="context">
                        <option value="homepage" <?php selected($context, 'homepage'); ?>><?php _e('Homepage', 'twintack-marketing'); ?></option>
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
            
            <div class="twintack-marketing-admin">
                <div class="twintack-context-selector">
                    <label for="banner-context"><?php _e('Context:', 'twintack-marketing'); ?></label>
                    <select id="banner-context" name="context">
                        <option value="homepage" <?php selected($context, 'homepage'); ?>><?php _e('Homepage', 'twintack-marketing'); ?></option>
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
        $cta_text = isset($block['cta_text']) ? $block['cta_text'] : '';
        $cta_link = isset($block['cta_link']) ? $block['cta_link'] : '';
        $link = isset($block['link']) ? $block['link'] : '';
        $order = isset($block['order']) ? $block['order'] : 0;
        
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
                    <tr class="text-fields" style="<?php echo ($layout === '50-50') ? '' : 'display:none;'; ?>">
                        <th><label><?php _e('Title', 'twintack-marketing'); ?></label></th>
                        <td>
                            <input type="text" name="title" class="regular-text" value="<?php echo esc_attr($title); ?>" />
                        </td>
                    </tr>
                    <tr class="text-fields" style="<?php echo ($layout === '50-50') ? '' : 'display:none;'; ?>">
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
                    <tr class="text-fields" style="<?php echo ($layout === '50-50') ? '' : 'display:none;'; ?>">
                        <th><label><?php _e('CTA Text', 'twintack-marketing'); ?></label></th>
                        <td>
                            <input type="text" name="cta_text" class="regular-text" value="<?php echo esc_attr($cta_text); ?>" />
                        </td>
                    </tr>
                    <tr class="text-fields" style="<?php echo ($layout === '50-50') ? '' : 'display:none;'; ?>">
                        <th><label><?php _e('CTA Link', 'twintack-marketing'); ?></label></th>
                        <td>
                            <input type="url" name="cta_link" class="regular-text" value="<?php echo esc_attr($cta_link); ?>" />
                        </td>
                    </tr>
                    <tr class="link-field" style="<?php echo ($layout === 'fullwidth') ? '' : 'display:none;'; ?>">
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
            <?php $this->render_hero_slide_editor(array('id' => '', 'image_desktop' => '', 'image_mobile' => '', 'destination_url' => '', 'alt_text' => '', 'active' => '1', 'order' => 0), true); ?>
        </div>
        <?php
    }
    
    private function render_hero_slide_editor($slide, $is_template = false) {
        $slide_id = isset($slide['id']) ? $slide['id'] : uniqid('hero_');
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
                                <p class="description"><?php _e('Recommended size: Industry standard desktop banner dimensions', 'twintack-marketing'); ?></p>
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
                                <p class="description"><?php _e('Recommended size: Industry standard mobile banner dimensions', 'twintack-marketing'); ?></p>
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
                            <p class="description"><?php _e('Alt text for the image (recommended for accessibility)', 'twintack-marketing'); ?></p>
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

