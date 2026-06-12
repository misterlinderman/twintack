<?php
/**
 * Marketing Target Page Management
 * Adds hero video, product grid, and video feature block fields to target page template
 *
 * @package TwinTack_Marketing
 */

if (!defined('ABSPATH')) exit;

class TwinTack_Marketing_Target_Page {
    private static $instance = null;

    /**
     * Meta key prefix for all target page fields
     */
    const META_PREFIX = '_twintack_target_';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_head', array($this, 'output_page_overrides'));
    }

    /**
     * Register meta boxes for pages using the Marketing Target Page template.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'twintack_target_hero',
            __('Target Page — Hero Section', 'twintack-marketing'),
            array($this, 'render_hero_meta_box'),
            'page',
            'normal',
            'high'
        );

        add_meta_box(
            'twintack_target_product_grid',
            __('Target Page — Product Grid', 'twintack-marketing'),
            array($this, 'render_product_grid_meta_box'),
            'page',
            'normal',
            'high'
        );

        add_meta_box(
            'twintack_target_image_row',
            __('Target Page — Image Row / Carousel', 'twintack-marketing'),
            array($this, 'render_image_row_meta_box'),
            'page',
            'normal',
            'high'
        );

        add_meta_box(
            'twintack_target_video_blocks',
            __('Target Page — Video & Image Feature Blocks', 'twintack-marketing'),
            array($this, 'render_video_blocks_meta_box'),
            'page',
            'normal',
            'high'
        );
    }

    /**
     * Enqueue admin assets only on page edit screens.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'page') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');

        wp_enqueue_script(
            'twintack-target-page-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/marketing-target-page-admin.js',
            array('jquery', 'jquery-ui-sortable', 'wp-color-picker'),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/marketing-target-page-admin.js'),
            true
        );

        wp_localize_script('twintack-target-page-admin', 'twintackTargetPage', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('twintack_target_page_nonce'),
        ));
    }

    /* -------------------------------------------------------------------------
     * Hero Meta Box
     * ---------------------------------------------------------------------- */

    /**
     * Render the hero section meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_hero_meta_box($post) {
        wp_nonce_field('twintack_target_page_nonce', 'twintack_target_page_nonce');

        $hero_type          = get_post_meta($post->ID, self::META_PREFIX . 'hero_type', true) ?: 'image';
        $video_desktop      = get_post_meta($post->ID, self::META_PREFIX . 'hero_video_desktop', true);
        $video_mobile       = get_post_meta($post->ID, self::META_PREFIX . 'hero_video_mobile', true);
        $image_desktop      = get_post_meta($post->ID, self::META_PREFIX . 'hero_image_desktop', true);
        $image_mobile       = get_post_meta($post->ID, self::META_PREFIX . 'hero_image_mobile', true);
        $hero_title         = get_post_meta($post->ID, self::META_PREFIX . 'hero_title', true);
        $hero_subtitle      = get_post_meta($post->ID, self::META_PREFIX . 'hero_subtitle', true);
        $hero_cta_text      = get_post_meta($post->ID, self::META_PREFIX . 'hero_cta_text', true);
        $hero_cta_url       = get_post_meta($post->ID, self::META_PREFIX . 'hero_cta_url', true);
        $accent_color          = get_post_meta($post->ID, self::META_PREFIX . 'accent_color', true);
        $hide_bundle_counter   = get_post_meta($post->ID, self::META_PREFIX . 'hide_bundle_counter', true);
        ?>
        <p class="description">
            <?php _e('These fields are used when the "Marketing Target Page" template is selected.', 'twintack-marketing'); ?>
        </p>

        <table class="form-table twintack-target-hero-fields">
            <tr>
                <th><?php _e('Page Overrides', 'twintack-marketing'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="twintack_target_hide_bundle_counter" value="1"
                               <?php checked($hide_bundle_counter, '1'); ?> />
                        <?php _e('Hide bundle counter bar on this page', 'twintack-marketing'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="twintack_target_accent_color"><?php _e('Accent Color Override', 'twintack-marketing'); ?></label></th>
                <td>
                    <input type="text" name="twintack_target_accent_color" id="twintack_target_accent_color"
                           value="<?php echo esc_attr($accent_color); ?>" class="twintack-color-picker"
                           placeholder="#ff0b00" />
                    <p class="description"><?php _e('Leave empty to use the default site accent color. Set a hex value to override --primary, --primary-dark, and --color-highlight for this page.', 'twintack-marketing'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="twintack_target_hero_type"><?php _e('Hero Type', 'twintack-marketing'); ?></label></th>
                <td>
                    <select name="twintack_target_hero_type" id="twintack_target_hero_type" class="target-hero-type-select">
                        <option value="image" <?php selected($hero_type, 'image'); ?>><?php _e('Static Image', 'twintack-marketing'); ?></option>
                        <option value="video_background" <?php selected($hero_type, 'video_background'); ?>><?php _e('Video Background (with text overlay)', 'twintack-marketing'); ?></option>
                        <option value="video_full" <?php selected($hero_type, 'video_full'); ?>><?php _e('Full Video (no overlay)', 'twintack-marketing'); ?></option>
                    </select>
                </td>
            </tr>

            <!-- Video fields (shown for video_background and video_full) -->
            <tr class="target-hero-video-field">
                <th><label><?php _e('Desktop Video (MP4)', 'twintack-marketing'); ?></label></th>
                <td>
                    <div class="twintack-media-upload" data-type="video">
                        <input type="hidden" name="twintack_target_hero_video_desktop" class="media-url" value="<?php echo esc_attr($video_desktop); ?>" />
                        <div class="media-preview" style="<?php echo $video_desktop ? '' : 'display:none;'; ?>">
                            <?php if ($video_desktop) : ?>
                                <video src="<?php echo esc_url($video_desktop); ?>" style="max-width:300px;height:auto;" muted></video>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button upload-media-btn"><?php _e('Upload Video', 'twintack-marketing'); ?></button>
                        <button type="button" class="button remove-media-btn" style="<?php echo $video_desktop ? '' : 'display:none;'; ?>"><?php _e('Remove', 'twintack-marketing'); ?></button>
                    </div>
                </td>
            </tr>
            <tr class="target-hero-video-field">
                <th><label><?php _e('Mobile Video (MP4, Optional)', 'twintack-marketing'); ?></label></th>
                <td>
                    <div class="twintack-media-upload" data-type="video">
                        <input type="hidden" name="twintack_target_hero_video_mobile" class="media-url" value="<?php echo esc_attr($video_mobile); ?>" />
                        <div class="media-preview" style="<?php echo $video_mobile ? '' : 'display:none;'; ?>">
                            <?php if ($video_mobile) : ?>
                                <video src="<?php echo esc_url($video_mobile); ?>" style="max-width:300px;height:auto;" muted></video>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button upload-media-btn"><?php _e('Upload Video', 'twintack-marketing'); ?></button>
                        <button type="button" class="button remove-media-btn" style="<?php echo $video_mobile ? '' : 'display:none;'; ?>"><?php _e('Remove', 'twintack-marketing'); ?></button>
                        <p class="description"><?php _e('If empty, mobile will fall back to the mobile image below.', 'twintack-marketing'); ?></p>
                    </div>
                </td>
            </tr>

            <!-- Image fields (always shown) -->
            <tr>
                <th><label><?php _e('Desktop Image', 'twintack-marketing'); ?></label></th>
                <td>
                    <div class="twintack-media-upload" data-type="image">
                        <input type="hidden" name="twintack_target_hero_image_desktop" class="media-url" value="<?php echo esc_attr($image_desktop); ?>" />
                        <div class="media-preview" style="<?php echo $image_desktop ? '' : 'display:none;'; ?>">
                            <?php if ($image_desktop) : ?>
                                <img src="<?php echo esc_url($image_desktop); ?>" alt="" style="max-width:300px;height:auto;" />
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button upload-media-btn"><?php _e('Upload Image', 'twintack-marketing'); ?></button>
                        <button type="button" class="button remove-media-btn" style="<?php echo $image_desktop ? '' : 'display:none;'; ?>"><?php _e('Remove', 'twintack-marketing'); ?></button>
                        <p class="description target-hero-video-field"><?php _e('Used as video poster / fallback when a video is set.', 'twintack-marketing'); ?></p>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Mobile Image (Optional)', 'twintack-marketing'); ?></label></th>
                <td>
                    <div class="twintack-media-upload" data-type="image">
                        <input type="hidden" name="twintack_target_hero_image_mobile" class="media-url" value="<?php echo esc_attr($image_mobile); ?>" />
                        <div class="media-preview" style="<?php echo $image_mobile ? '' : 'display:none;'; ?>">
                            <?php if ($image_mobile) : ?>
                                <img src="<?php echo esc_url($image_mobile); ?>" alt="" style="max-width:300px;height:auto;" />
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button upload-media-btn"><?php _e('Upload Image', 'twintack-marketing'); ?></button>
                        <button type="button" class="button remove-media-btn" style="<?php echo $image_mobile ? '' : 'display:none;'; ?>"><?php _e('Remove', 'twintack-marketing'); ?></button>
                    </div>
                </td>
            </tr>

            <!-- Overlay text fields (shown for video_background and image) -->
            <tr class="target-hero-overlay-field">
                <th><label for="twintack_target_hero_title"><?php _e('Hero Title', 'twintack-marketing'); ?></label></th>
                <td>
                    <input type="text" name="twintack_target_hero_title" id="twintack_target_hero_title"
                           value="<?php echo esc_attr($hero_title); ?>" class="large-text" />
                </td>
            </tr>
            <tr class="target-hero-overlay-field">
                <th><label for="twintack_target_hero_subtitle"><?php _e('Hero Subtitle', 'twintack-marketing'); ?></label></th>
                <td>
                    <input type="text" name="twintack_target_hero_subtitle" id="twintack_target_hero_subtitle"
                           value="<?php echo esc_attr($hero_subtitle); ?>" class="large-text" />
                </td>
            </tr>
            <tr class="target-hero-overlay-field">
                <th><label for="twintack_target_hero_cta_text"><?php _e('CTA Button Text', 'twintack-marketing'); ?></label></th>
                <td>
                    <input type="text" name="twintack_target_hero_cta_text" id="twintack_target_hero_cta_text"
                           value="<?php echo esc_attr($hero_cta_text); ?>" class="regular-text" placeholder="<?php esc_attr_e('e.g. Shop Now', 'twintack-marketing'); ?>" />
                </td>
            </tr>
            <tr class="target-hero-overlay-field">
                <th><label for="twintack_target_hero_cta_url"><?php _e('CTA Button URL', 'twintack-marketing'); ?></label></th>
                <td>
                    <input type="url" name="twintack_target_hero_cta_url" id="twintack_target_hero_cta_url"
                           value="<?php echo esc_attr($hero_cta_url); ?>" class="regular-text" placeholder="https://" />
                </td>
            </tr>
        </table>
        <?php
    }

    /* -------------------------------------------------------------------------
     * Product Grid Meta Box
     * ---------------------------------------------------------------------- */

    /**
     * Render the product grid configuration meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_product_grid_meta_box($post) {
        $category  = get_post_meta($post->ID, self::META_PREFIX . 'product_category', true);
        $heading   = get_post_meta($post->ID, self::META_PREFIX . 'product_heading', true);
        $columns   = get_post_meta($post->ID, self::META_PREFIX . 'product_columns', true) ?: '4';
        $limit     = get_post_meta($post->ID, self::META_PREFIX . 'product_limit', true) ?: '8';

        $product_cats = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'name',
        ));
        ?>
        <p class="description">
            <?php _e('Display a WooCommerce product grid filtered by category. Leave category empty to skip the product grid.', 'twintack-marketing'); ?>
        </p>

        <table class="form-table">
            <tr>
                <th><label for="twintack_target_product_category"><?php _e('Product Category', 'twintack-marketing'); ?></label></th>
                <td>
                    <select name="twintack_target_product_category" id="twintack_target_product_category">
                        <option value=""><?php _e('— None (hide product grid) —', 'twintack-marketing'); ?></option>
                        <?php if (!is_wp_error($product_cats)) : ?>
                            <?php foreach ($product_cats as $cat) : ?>
                                <option value="<?php echo esc_attr($cat->slug); ?>" <?php selected($category, $cat->slug); ?>>
                                    <?php echo esc_html($cat->name); ?> (<?php echo esc_html($cat->count); ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="twintack_target_product_heading"><?php _e('Section Heading', 'twintack-marketing'); ?></label></th>
                <td>
                    <input type="text" name="twintack_target_product_heading" id="twintack_target_product_heading"
                           value="<?php echo esc_attr($heading); ?>" class="regular-text"
                           placeholder="<?php esc_attr_e('e.g. TT Pro Flex Collection', 'twintack-marketing'); ?>" />
                </td>
            </tr>
            <tr>
                <th><label for="twintack_target_product_columns"><?php _e('Columns', 'twintack-marketing'); ?></label></th>
                <td>
                    <select name="twintack_target_product_columns" id="twintack_target_product_columns">
                        <option value="3" <?php selected($columns, '3'); ?>>3</option>
                        <option value="4" <?php selected($columns, '4'); ?>>4</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="twintack_target_product_limit"><?php _e('Max Products', 'twintack-marketing'); ?></label></th>
                <td>
                    <input type="number" name="twintack_target_product_limit" id="twintack_target_product_limit"
                           value="<?php echo esc_attr($limit); ?>" min="1" max="24" step="1" class="small-text" />
                </td>
            </tr>
        </table>
        <?php
    }

    /* -------------------------------------------------------------------------
     * Image Row Meta Box
     * ---------------------------------------------------------------------- */

    /**
     * Render the image row meta box (row of 3 on desktop, carousel on mobile).
     *
     * @param WP_Post $post Current post object.
     */
    public function render_image_row_meta_box($post) {
        $images = get_post_meta($post->ID, self::META_PREFIX . 'image_row', true);
        if (!is_array($images)) {
            $images = array();
        }
        $heading = get_post_meta($post->ID, self::META_PREFIX . 'image_row_heading', true);
        ?>
        <p class="description">
            <?php _e('Add marketing images to display in a row of 3 on desktop and a swipeable carousel on mobile. Displays between the hero and product grid.', 'twintack-marketing'); ?>
        </p>

        <table class="form-table">
            <tr>
                <th><label for="twintack_target_image_row_heading"><?php _e('Section Heading (Optional)', 'twintack-marketing'); ?></label></th>
                <td>
                    <input type="text" name="twintack_target_image_row_heading" id="twintack_target_image_row_heading"
                           value="<?php echo esc_attr($heading); ?>" class="regular-text"
                           placeholder="<?php esc_attr_e('e.g. Why TT Pro Flex?', 'twintack-marketing'); ?>" />
                </td>
            </tr>
        </table>

        <div id="twintack-image-row-container" style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
            <?php foreach ($images as $index => $img_url) : ?>
                <div class="twintack-image-row-item" style="position:relative;width:200px;">
                    <img src="<?php echo esc_url($img_url); ?>" alt="" style="width:100%;height:auto;border:1px solid #ccc;border-radius:4px;" />
                    <input type="hidden" name="twintack_target_image_row[]" value="<?php echo esc_attr($img_url); ?>" />
                    <button type="button" class="button twintack-remove-image-row-item" style="position:absolute;top:4px;right:4px;padding:0 6px;min-height:24px;line-height:22px;">&times;</button>
                </div>
            <?php endforeach; ?>
        </div>

        <p>
            <button type="button" class="button button-primary" id="twintack-add-image-row">
                <?php _e('+ Add Images', 'twintack-marketing'); ?>
            </button>
        </p>
        <?php
    }

    /* -------------------------------------------------------------------------
     * Video Feature Blocks Meta Box
     * ---------------------------------------------------------------------- */

    /**
     * Render the video feature blocks repeater meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_video_blocks_meta_box($post) {
        $blocks = get_post_meta($post->ID, self::META_PREFIX . 'video_blocks', true);
        if (!is_array($blocks)) {
            $blocks = array();
        }
        ?>
        <p class="description">
            <?php _e('Add video or image feature sections to showcase product details. Blocks display in the order shown below.', 'twintack-marketing'); ?>
        </p>

        <div id="twintack-video-blocks-container">
            <?php foreach ($blocks as $index => $block) : ?>
                <?php $this->render_video_block_row($index, $block); ?>
            <?php endforeach; ?>
        </div>

        <p>
            <button type="button" class="button button-primary" id="twintack-add-video-block">
                <?php _e('+ Add Feature Block', 'twintack-marketing'); ?>
            </button>
        </p>

        <!-- Hidden template for new blocks (JS clones this) -->
        <script type="text/html" id="tmpl-twintack-video-block">
            <?php $this->render_video_block_row('__INDEX__', array()); ?>
        </script>
        <?php
    }

    /**
     * Render a single video block row for the repeater.
     *
     * @param int|string $index Block index (or placeholder string for JS template).
     * @param array      $block Saved block data.
     */
    private function render_video_block_row($index, $block) {
        $video_url   = isset($block['video_url'])   ? $block['video_url']   : '';
        $video_type  = isset($block['video_type'])  ? $block['video_type']  : 'mp4';
        $heading     = isset($block['heading'])     ? $block['heading']     : '';
        $description = isset($block['description']) ? $block['description'] : '';
        $cta_text    = isset($block['cta_text'])    ? $block['cta_text']    : '';
        $cta_url     = isset($block['cta_url'])     ? $block['cta_url']     : '';
        $layout      = isset($block['layout'])      ? $block['layout']      : 'video_left';
        $prefix      = 'twintack_target_video_blocks[' . $index . ']';
        ?>
        <div class="twintack-video-block-row" data-index="<?php echo esc_attr($index); ?>">
            <div class="video-block-header">
                <strong><?php _e('Feature Block', 'twintack-marketing'); ?> <span class="block-number">#<?php echo is_numeric($index) ? intval($index) + 1 : ''; ?></span></strong>
                <button type="button" class="button twintack-remove-video-block"><?php _e('Remove', 'twintack-marketing'); ?></button>
            </div>
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Media Type', 'twintack-marketing'); ?></label></th>
                    <td>
                        <select name="<?php echo esc_attr($prefix); ?>[video_type]" class="video-type-select">
                            <option value="mp4" <?php selected($video_type, 'mp4'); ?>><?php _e('MP4 Upload', 'twintack-marketing'); ?></option>
                            <option value="image" <?php selected($video_type, 'image'); ?>><?php _e('Image', 'twintack-marketing'); ?></option>
                            <option value="youtube" <?php selected($video_type, 'youtube'); ?>><?php _e('YouTube URL', 'twintack-marketing'); ?></option>
                            <option value="vimeo" <?php selected($video_type, 'vimeo'); ?>><?php _e('Vimeo URL', 'twintack-marketing'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr class="video-block-mp4-field">
                    <th><label><?php _e('Video File', 'twintack-marketing'); ?></label></th>
                    <td>
                        <div class="twintack-media-upload" data-type="video">
                            <input type="hidden" name="<?php echo esc_attr($prefix); ?>[video_url]" class="media-url" value="<?php echo esc_attr($video_type === 'mp4' ? $video_url : ''); ?>" />
                            <div class="media-preview" style="<?php echo $video_url && $video_type === 'mp4' ? '' : 'display:none;'; ?>">
                                <?php if ($video_url && $video_type === 'mp4') : ?>
                                    <video src="<?php echo esc_url($video_url); ?>" style="max-width:250px;height:auto;" muted></video>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button upload-media-btn"><?php _e('Upload Video', 'twintack-marketing'); ?></button>
                            <button type="button" class="button remove-media-btn" style="<?php echo ($video_url && $video_type === 'mp4') ? '' : 'display:none;'; ?>"><?php _e('Remove', 'twintack-marketing'); ?></button>
                        </div>
                    </td>
                </tr>
                <tr class="video-block-image-field">
                    <th><label><?php _e('Image File', 'twintack-marketing'); ?></label></th>
                    <td>
                        <?php
                        $image_url_val = ($video_type === 'image') ? $video_url : '';
                        ?>
                        <div class="twintack-media-upload" data-type="image">
                            <input type="hidden" name="<?php echo esc_attr($prefix); ?>[image_url]" class="media-url" value="<?php echo esc_attr($image_url_val); ?>" />
                            <div class="media-preview" style="<?php echo $image_url_val ? '' : 'display:none;'; ?>">
                                <?php if ($image_url_val) : ?>
                                    <img src="<?php echo esc_url($image_url_val); ?>" alt="" style="max-width:250px;height:auto;" />
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button upload-media-btn"><?php _e('Upload Image', 'twintack-marketing'); ?></button>
                            <button type="button" class="button remove-media-btn" style="<?php echo $image_url_val ? '' : 'display:none;'; ?>"><?php _e('Remove', 'twintack-marketing'); ?></button>
                        </div>
                    </td>
                </tr>
                <tr class="video-block-embed-field" style="<?php echo ($video_type !== 'mp4' && $video_type !== 'image') ? '' : 'display:none;'; ?>">
                    <th><label><?php _e('Video URL', 'twintack-marketing'); ?></label></th>
                    <td>
                        <input type="url" name="<?php echo esc_attr($prefix); ?>[video_embed_url]" class="regular-text embed-url-input"
                               value="<?php echo esc_attr(in_array($video_type, array('youtube', 'vimeo'), true) ? $video_url : ''); ?>"
                               placeholder="https://www.youtube.com/watch?v=..." />
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Heading', 'twintack-marketing'); ?></label></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr($prefix); ?>[heading]" class="large-text"
                               value="<?php echo esc_attr($heading); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Description', 'twintack-marketing'); ?></label></th>
                    <td>
                        <textarea name="<?php echo esc_attr($prefix); ?>[description]" rows="3" class="large-text"><?php echo esc_textarea($description); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('CTA Button Text', 'twintack-marketing'); ?></label></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr($prefix); ?>[cta_text]" class="regular-text"
                               value="<?php echo esc_attr($cta_text); ?>" placeholder="<?php esc_attr_e('e.g. Shop Now', 'twintack-marketing'); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('CTA Button URL', 'twintack-marketing'); ?></label></th>
                    <td>
                        <input type="url" name="<?php echo esc_attr($prefix); ?>[cta_url]" class="regular-text"
                               value="<?php echo esc_attr($cta_url); ?>" placeholder="https://" />
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Layout', 'twintack-marketing'); ?></label></th>
                    <td>
                        <select name="<?php echo esc_attr($prefix); ?>[layout]">
                            <option value="video_left" <?php selected($layout, 'video_left'); ?>><?php _e('Video Left / Text Right', 'twintack-marketing'); ?></option>
                            <option value="video_right" <?php selected($layout, 'video_right'); ?>><?php _e('Video Right / Text Left', 'twintack-marketing'); ?></option>
                            <option value="full_width" <?php selected($layout, 'full_width'); ?>><?php _e('Full Width Video', 'twintack-marketing'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /* -------------------------------------------------------------------------
     * Save
     * ---------------------------------------------------------------------- */

    /**
     * Save all target page meta box data.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public function save_meta_boxes($post_id, $post) {
        if (!isset($_POST['twintack_target_page_nonce']) ||
            !wp_verify_nonce($_POST['twintack_target_page_nonce'], 'twintack_target_page_nonce')) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Hero fields
        $text_fields = array(
            'hero_type', 'hero_title', 'hero_subtitle', 'hero_cta_text',
        );
        foreach ($text_fields as $field) {
            $key = 'twintack_target_' . $field;
            if (isset($_POST[$key])) {
                update_post_meta($post_id, self::META_PREFIX . $field, sanitize_text_field($_POST[$key]));
            }
        }

        $url_fields = array(
            'hero_video_desktop', 'hero_video_mobile',
            'hero_image_desktop', 'hero_image_mobile',
            'hero_cta_url',
        );
        foreach ($url_fields as $field) {
            $key = 'twintack_target_' . $field;
            if (isset($_POST[$key])) {
                update_post_meta($post_id, self::META_PREFIX . $field, esc_url_raw($_POST[$key]));
            }
        }

        // Page override fields
        $hide_bundle = isset($_POST['twintack_target_hide_bundle_counter']) ? '1' : '0';
        update_post_meta($post_id, self::META_PREFIX . 'hide_bundle_counter', $hide_bundle);

        // Accent color override
        if (isset($_POST['twintack_target_accent_color'])) {
            $color = sanitize_hex_color($_POST['twintack_target_accent_color']);
            update_post_meta($post_id, self::META_PREFIX . 'accent_color', $color ?: '');
        }

        // Product grid fields
        if (isset($_POST['twintack_target_product_category'])) {
            update_post_meta($post_id, self::META_PREFIX . 'product_category', sanitize_text_field($_POST['twintack_target_product_category']));
        }
        if (isset($_POST['twintack_target_product_heading'])) {
            update_post_meta($post_id, self::META_PREFIX . 'product_heading', sanitize_text_field($_POST['twintack_target_product_heading']));
        }
        if (isset($_POST['twintack_target_product_columns'])) {
            update_post_meta($post_id, self::META_PREFIX . 'product_columns', sanitize_text_field($_POST['twintack_target_product_columns']));
        }
        if (isset($_POST['twintack_target_product_limit'])) {
            update_post_meta($post_id, self::META_PREFIX . 'product_limit', absint($_POST['twintack_target_product_limit']));
        }

        // Image row
        if (isset($_POST['twintack_target_image_row']) && is_array($_POST['twintack_target_image_row'])) {
            $sanitized_images = array();
            foreach ($_POST['twintack_target_image_row'] as $img_url) {
                $clean = esc_url_raw($img_url);
                if (!empty($clean)) {
                    $sanitized_images[] = $clean;
                }
            }
            update_post_meta($post_id, self::META_PREFIX . 'image_row', $sanitized_images);
        } else {
            update_post_meta($post_id, self::META_PREFIX . 'image_row', array());
        }
        if (isset($_POST['twintack_target_image_row_heading'])) {
            update_post_meta($post_id, self::META_PREFIX . 'image_row_heading', sanitize_text_field($_POST['twintack_target_image_row_heading']));
        }

        // Video feature blocks (repeater)
        if (isset($_POST['twintack_target_video_blocks']) && is_array($_POST['twintack_target_video_blocks'])) {
            $sanitized_blocks = array();
            foreach ($_POST['twintack_target_video_blocks'] as $block) {
                if (!is_array($block)) {
                    continue;
                }
                $vtype = isset($block['video_type']) ? sanitize_text_field($block['video_type']) : 'mp4';
                if (!in_array($vtype, array('mp4', 'image', 'youtube', 'vimeo'), true)) {
                    $vtype = 'mp4';
                }
                // MP4 and image uploads use video_url / image_url; embeds use video_embed_url
                $raw_url = '';
                if ($vtype === 'mp4') {
                    $raw_url = isset($block['video_url']) ? $block['video_url'] : '';
                } elseif ($vtype === 'image') {
                    $raw_url = isset($block['image_url']) ? $block['image_url'] : '';
                } else {
                    $raw_url = isset($block['video_embed_url']) ? $block['video_embed_url'] : '';
                }

                $sanitized_blocks[] = array(
                    'video_url'   => esc_url_raw($raw_url),
                    'video_type'  => $vtype,
                    'heading'     => isset($block['heading'])     ? sanitize_text_field($block['heading'])     : '',
                    'description' => isset($block['description']) ? sanitize_textarea_field($block['description']) : '',
                    'cta_text'    => isset($block['cta_text'])    ? sanitize_text_field($block['cta_text'])    : '',
                    'cta_url'     => isset($block['cta_url'])     ? esc_url_raw($block['cta_url'])             : '',
                    'layout'      => isset($block['layout'])      ? sanitize_text_field($block['layout'])      : 'video_left',
                );
            }
            update_post_meta($post_id, self::META_PREFIX . 'video_blocks', $sanitized_blocks);
        } else {
            update_post_meta($post_id, self::META_PREFIX . 'video_blocks', array());
        }
    }

    /* -------------------------------------------------------------------------
     * Frontend Helpers
     * ---------------------------------------------------------------------- */

    /**
     * Get all target page meta for a given post.
     *
     * @param int $post_id Post ID.
     * @return array Associative array of all target page meta values.
     */
    public static function get_target_page_meta($post_id) {
        $fields = array(
            'hero_type', 'hero_video_desktop', 'hero_video_mobile',
            'hero_image_desktop', 'hero_image_mobile',
            'hero_title', 'hero_subtitle', 'hero_cta_text', 'hero_cta_url',
            'accent_color', 'hide_bundle_counter',
            'image_row', 'image_row_heading',
            'product_category', 'product_heading', 'product_columns', 'product_limit',
            'video_blocks',
        );

        $meta = array();
        foreach ($fields as $field) {
            $meta[$field] = get_post_meta($post_id, self::META_PREFIX . $field, true);
        }

        // Defaults
        if (empty($meta['hero_type'])) {
            $meta['hero_type'] = 'image';
        }
        if (empty($meta['product_columns'])) {
            $meta['product_columns'] = '4';
        }
        if (empty($meta['product_limit'])) {
            $meta['product_limit'] = 8;
        }
        if (!is_array($meta['image_row'])) {
            $meta['image_row'] = array();
        }
        if (!is_array($meta['video_blocks'])) {
            $meta['video_blocks'] = array();
        }

        return $meta;
    }

    /* -------------------------------------------------------------------------
     * Frontend Page Overrides
     * ---------------------------------------------------------------------- */

    /**
     * Output inline CSS for accent color override on target pages.
     * Hooked to wp_head.
     */
    public function output_page_overrides() {
        if (!is_page_template('templates/template-target-page.php')) {
            return;
        }

        $page_id = get_queried_object_id();
        if (!$page_id) {
            return;
        }

        // Accent color override
        $accent = get_post_meta($page_id, self::META_PREFIX . 'accent_color', true);
        if (!empty($accent) && preg_match('/^#[a-fA-F0-9]{3,8}$/', $accent)) {
            $dark = self::darken_hex($accent, 15);
            printf(
                '<style>:root,.twintack-target-page{--primary:%1$s;--primary-dark:%2$s;--color-highlight:%1$s}</style>' . "\n",
                esc_attr($accent),
                esc_attr($dark)
            );
        }
    }

    /**
     * Darken a hex color by a percentage.
     *
     * @param string $hex     Hex color (e.g. #32c5f4).
     * @param int    $percent Percentage to darken (0-100).
     * @return string Darkened hex color.
     */
    private static function darken_hex($hex, $percent) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $factor = 1 - ($percent / 100);
        $r = max(0, min(255, intval($r * $factor)));
        $g = max(0, min(255, intval($g * $factor)));
        $b = max(0, min(255, intval($b * $factor)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
