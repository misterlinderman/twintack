<?php
/**
 * Marketing Homepage V2 — page meta and helpers.
 *
 * @package TwinTack_Marketing
 */

if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Marketing_Homepage_V2 {
    const META_PREFIX   = '_twintack_v2_';
    const TEMPLATE_SLUG = 'templates/template-homepage-v2.php';

    /** @var self|null */
    private static $instance = null;

    /**
     * @return self
     */
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
        add_filter('body_class', array($this, 'body_class'));
    }

    /**
     * Whether the given page uses the V2 homepage template.
     *
     * @param int $post_id Post ID.
     * @return bool
     */
    public static function is_v2_page($post_id = 0) {
        if (!$post_id) {
            $post_id = get_queried_object_id();
        }
        if (!$post_id) {
            return false;
        }
        return get_page_template_slug($post_id) === self::TEMPLATE_SLUG;
    }

    /**
     * @param string[] $classes Body classes.
     * @return string[]
     */
    public function body_class($classes) {
        if (is_page() && self::is_v2_page()) {
            $classes[] = 'tt-v2-homepage';
        }
        return $classes;
    }

    /**
     * Register meta boxes (shown on all pages; saved only for V2 template).
     */
    public function add_meta_boxes() {
        add_meta_box(
            'twintack_v2_content',
            __('Homepage V2 — Content Blocks', 'twintack-marketing'),
            array($this, 'render_content_meta_box'),
            'page',
            'normal',
            'high'
        );
    }

    /**
     * @param string $hook Admin hook.
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
        wp_enqueue_style(
            'twintack-v2-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/marketing-v2-admin.css',
            array(),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/marketing-v2-admin.css')
        );
        wp_enqueue_script(
            'twintack-v2-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/marketing-v2-admin.js',
            array('jquery'),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/marketing-v2-admin.js'),
            true
        );
    }

    /**
     * Render a collapsible admin panel.
     *
     * @param string   $title   Panel title.
     * @param callable $content Render callback.
     * @param bool     $open    Open by default.
     */
    private function render_admin_panel($title, $content, $open = false) {
        printf(
            '<details class="tt-v2-admin-panel"%s><summary>%s</summary><div class="tt-v2-admin-panel__body">',
            $open ? ' open' : '',
            esc_html($title)
        );
        call_user_func($content);
        echo '</div></details>';
    }

    /**
     * Render image URL field with preview.
     *
     * @param string $id    Input ID.
     * @param string $label Field label.
     * @param string $url   Current URL.
     * @param string $name  Optional input name (defaults to ID).
     */
    private function render_media_field($id, $label, $url, $name = '') {
        if ('' === $name) {
            $name = $id;
        }
        ?>
        <div class="tt-v2-admin-field">
            <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
            <div class="tt-v2-media-row">
                <input type="url" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>" value="<?php echo esc_url($url); ?>" class="large-text tt-v2-media-url">
                <button type="button" class="button tt-v2-media-upload" data-target="<?php echo esc_attr($id); ?>"><?php esc_html_e('Select', 'twintack-marketing'); ?></button>
                <div class="tt-v2-media-preview" aria-hidden="true"></div>
            </div>
        </div>
        <?php
    }

    /**
     * @param WP_Post $post Post object.
     */
    public function render_content_meta_box($post) {
        wp_nonce_field('twintack_v2_nonce', 'twintack_v2_nonce');

        $meta = self::get_homepage_v2_meta($post->ID);
        $self = $this;
        ?>
        <div class="tt-v2-admin-wrap">
            <div class="tt-v2-admin-intro">
                <p><strong><?php esc_html_e('Homepage V2 template fields', 'twintack-marketing'); ?></strong> — <?php esc_html_e('These sections map to the page from top to bottom. Hero slides, featured products, and category cards are managed separately.', 'twintack-marketing'); ?></p>
                <div class="tt-v2-admin-links">
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=twintack-marketing-hero')); ?>"><?php esc_html_e('Hero Carousel', 'twintack-marketing'); ?></a>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=twintack-marketing-marquee')); ?>"><?php esc_html_e('Scrolling Marquee', 'twintack-marketing'); ?></a>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=twintack-marketing-featured&context=homepage-v2')); ?>"><?php esc_html_e('Featured Products (V2)', 'twintack-marketing'); ?></a>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=twintack-marketing-banners&context=homepage-v2')); ?>"><?php esc_html_e('Banner Blocks (V2)', 'twintack-marketing'); ?></a>
                </div>
            </div>

            <?php
            $this->render_admin_panel(__('1. Scrolling Marquee', 'twintack-marketing'), function () {
                ?>
                <p class="tt-v2-admin-panel__hint">
                    <?php esc_html_e('Trust ticker below the hero. Managed globally — add, remove, or edit messages in Marketing.', 'twintack-marketing'); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=twintack-marketing-marquee')); ?>" class="button button-secondary">
                        <?php esc_html_e('Edit scrolling marquee', 'twintack-marketing'); ?>
                    </a>
                </p>
                <?php
            }, true);

            $this->render_admin_panel(__('2. Image Collage — “Built for grip”', 'twintack-marketing'), function () use ($meta, $self) {
                ?>
                <div class="tt-v2-admin-grid-2">
                    <div class="tt-v2-admin-field">
                        <label for="twintack_v2_collage_heading_1"><?php esc_html_e('Heading line 1', 'twintack-marketing'); ?></label>
                        <input type="text" name="twintack_v2_collage_heading_1" id="twintack_v2_collage_heading_1" value="<?php echo esc_attr($meta['collage_heading_1']); ?>" class="large-text">
                    </div>
                    <div class="tt-v2-admin-field">
                        <label for="twintack_v2_collage_heading_2"><?php esc_html_e('Heading line 2 (accent)', 'twintack-marketing'); ?></label>
                        <input type="text" name="twintack_v2_collage_heading_2" id="twintack_v2_collage_heading_2" value="<?php echo esc_attr($meta['collage_heading_2']); ?>" class="large-text">
                    </div>
                </div>
                <div class="tt-v2-admin-field">
                    <label for="twintack_v2_collage_paragraph"><?php esc_html_e('Paragraph', 'twintack-marketing'); ?></label>
                    <textarea name="twintack_v2_collage_paragraph" id="twintack_v2_collage_paragraph" rows="3" class="large-text"><?php echo esc_textarea($meta['collage_paragraph']); ?></textarea>
                </div>
                <div class="tt-v2-admin-grid-2">
                    <div class="tt-v2-admin-field">
                        <label for="twintack_v2_collage_cta_text"><?php esc_html_e('CTA text', 'twintack-marketing'); ?></label>
                        <input type="text" name="twintack_v2_collage_cta_text" id="twintack_v2_collage_cta_text" value="<?php echo esc_attr($meta['collage_cta_text']); ?>" class="large-text">
                    </div>
                    <div class="tt-v2-admin-field">
                        <label for="twintack_v2_collage_cta_url"><?php esc_html_e('CTA URL', 'twintack-marketing'); ?></label>
                        <input type="url" name="twintack_v2_collage_cta_url" id="twintack_v2_collage_cta_url" value="<?php echo esc_url($meta['collage_cta_url']); ?>" class="large-text">
                    </div>
                </div>
                <div class="tt-v2-admin-field">
                    <label for="twintack_v2_collage_subtext"><?php esc_html_e('Subtext under CTA', 'twintack-marketing'); ?></label>
                    <input type="text" name="twintack_v2_collage_subtext" id="twintack_v2_collage_subtext" value="<?php echo esc_attr($meta['collage_subtext']); ?>" class="large-text">
                </div>
                <?php
                for ($i = 1; $i <= 3; $i++) {
                    $self->render_media_field(
                        'twintack_v2_collage_image_' . $i,
                        sprintf(__('Collage image %d', 'twintack-marketing'), $i),
                        $meta['collage_images'][ $i - 1 ] ?? ''
                    );
                }
                $bullets = (array) $meta['collage_bullets'];
                for ($b = 0; $b < 3; $b++) {
                    $bullet = isset($bullets[ $b ]) ? $bullets[ $b ] : array('title' => '', 'desc' => '', 'icon' => '');
                    ?>
                    <div class="tt-v2-admin-card">
                        <p class="tt-v2-admin-card__title"><?php printf(esc_html__('Bullet %d', 'twintack-marketing'), $b + 1); ?></p>
                        <div class="tt-v2-admin-grid-2">
                            <input type="text" name="twintack_v2_bullet_title[]" value="<?php echo esc_attr($bullet['title']); ?>" placeholder="<?php esc_attr_e('Title', 'twintack-marketing'); ?>" class="large-text">
                            <input type="text" name="twintack_v2_bullet_desc[]" value="<?php echo esc_attr($bullet['desc']); ?>" placeholder="<?php esc_attr_e('Description', 'twintack-marketing'); ?>" class="large-text">
                        </div>
                        <?php $self->render_media_field('twintack_v2_bullet_icon_' . $b, __('Icon', 'twintack-marketing'), $bullet['icon'], 'twintack_v2_bullet_icon[]'); ?>
                    </div>
                    <?php
                }
            });

            $this->render_admin_panel(__('3. Section Headings', 'twintack-marketing'), function () use ($meta) {
                ?>
                <p class="tt-v2-admin-panel__hint"><?php esc_html_e('Best sellers product grid is configured under Featured Products (V2). HTML allowed in headings.', 'twintack-marketing'); ?></p>
                <div class="tt-v2-admin-field">
                    <label for="twintack_v2_best_sellers_heading"><?php esc_html_e('Best sellers heading', 'twintack-marketing'); ?></label>
                    <input type="text" name="twintack_v2_best_sellers_heading" id="twintack_v2_best_sellers_heading" value="<?php echo esc_attr($meta['best_sellers_heading']); ?>" class="large-text">
                </div>
                <div class="tt-v2-admin-field">
                    <label for="twintack_v2_peak_heading"><?php esc_html_e('Peak performance heading', 'twintack-marketing'); ?></label>
                    <input type="text" name="twintack_v2_peak_heading" id="twintack_v2_peak_heading" value="<?php echo esc_attr($meta['peak_heading']); ?>" class="large-text">
                </div>
                <div class="tt-v2-admin-field">
                    <label for="twintack_v2_peak_subheading"><?php esc_html_e('Peak performance subheading', 'twintack-marketing'); ?></label>
                    <textarea name="twintack_v2_peak_subheading" id="twintack_v2_peak_subheading" rows="2" class="large-text"><?php echo esc_textarea($meta['peak_subheading']); ?></textarea>
                </div>
                <?php
            });

            $this->render_admin_panel(__('4. UGC Video Carousel', 'twintack-marketing'), function () use ($meta, $self) {
                ?>
                <div class="tt-v2-admin-field">
                    <label for="twintack_v2_ugc_headline"><?php esc_html_e('Headline (HTML allowed)', 'twintack-marketing'); ?></label>
                    <input type="text" name="twintack_v2_ugc_headline" id="twintack_v2_ugc_headline" value="<?php echo esc_attr($meta['ugc_headline']); ?>" class="large-text">
                </div>
                <div class="tt-v2-admin-field">
                    <label for="twintack_v2_ugc_subheadline"><?php esc_html_e('Subheadline', 'twintack-marketing'); ?></label>
                    <input type="text" name="twintack_v2_ugc_subheadline" id="twintack_v2_ugc_subheadline" value="<?php echo esc_attr($meta['ugc_subheadline']); ?>" class="large-text">
                </div>
                <?php
                $ugc = (array) $meta['ugc_videos'];
                for ($u = 0; $u < 4; $u++) {
                    $item = isset($ugc[ $u ]) ? $ugc[ $u ] : array('video' => '', 'poster' => '');
                    ?>
                    <div class="tt-v2-admin-card">
                        <p class="tt-v2-admin-card__title"><?php printf(esc_html__('Video %d', 'twintack-marketing'), $u + 1); ?></p>
                        <div class="tt-v2-admin-field">
                            <label><?php esc_html_e('MP4 URL', 'twintack-marketing'); ?></label>
                            <input type="url" name="twintack_v2_ugc_video[]" value="<?php echo esc_url($item['video']); ?>" class="large-text">
                        </div>
                        <?php
                        $self->render_media_field(
                            'twintack_v2_ugc_poster_' . $u,
                            __('Poster / thumbnail', 'twintack-marketing'),
                            $item['poster'],
                            'twintack_v2_ugc_poster[]'
                        );
                        ?>
                    </div>
                    <?php
                }
            });

            $this->render_admin_panel(__('5. How TwinTack Works — Video Tabs', 'twintack-marketing'), function () {
                ?>
                <p class="tt-v2-admin-panel__hint">
                    <?php esc_html_e('This section is managed globally for all Marketing V2 pages and grip products.', 'twintack-marketing'); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=twintack-marketing-video-tabs')); ?>" class="button button-secondary">
                        <?php esc_html_e('Edit video tabs in Marketing', 'twintack-marketing'); ?>
                    </a>
                </p>
                <?php
            });
            ?>
        </div>
        <?php
    }

    /**
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public function save_meta_boxes($post_id, $post) {
        if (!isset($_POST['twintack_v2_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['twintack_v2_nonce'])), 'twintack_v2_nonce')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if ($post->post_type !== 'page') {
            return;
        }
        if (!$this->is_saving_v2_template($post_id)) {
            return;
        }

        $heading_allowed = array(
            'em'     => array(),
            'strong' => array(),
            'span'   => array('class' => array()),
            'br'     => array(),
        );

        $text_fields = array(
            'collage_heading_1'    => 'twintack_v2_collage_heading_1',
            'collage_heading_2'    => 'twintack_v2_collage_heading_2',
            'collage_paragraph'    => 'twintack_v2_collage_paragraph',
            'collage_cta_text'     => 'twintack_v2_collage_cta_text',
            'collage_subtext'      => 'twintack_v2_collage_subtext',
            'peak_subheading'      => 'twintack_v2_peak_subheading',
            'ugc_subheadline'      => 'twintack_v2_ugc_subheadline',
        );

        foreach ($text_fields as $meta_key => $post_key) {
            if (isset($_POST[ $post_key ])) {
                update_post_meta($post_id, self::META_PREFIX . $meta_key, sanitize_text_field(wp_unslash($_POST[ $post_key ])));
            }
        }

        $html_fields = array(
            'best_sellers_heading' => 'twintack_v2_best_sellers_heading',
            'peak_heading'         => 'twintack_v2_peak_heading',
            'ugc_headline'         => 'twintack_v2_ugc_headline',
        );

        foreach ($html_fields as $meta_key => $post_key) {
            if (isset($_POST[ $post_key ])) {
                update_post_meta(
                    $post_id,
                    self::META_PREFIX . $meta_key,
                    wp_kses(wp_unslash($_POST[ $post_key ]), $heading_allowed)
                );
            }
        }

        if (isset($_POST['twintack_v2_collage_cta_url'])) {
            update_post_meta($post_id, self::META_PREFIX . 'collage_cta_url', esc_url_raw(wp_unslash($_POST['twintack_v2_collage_cta_url'])));
        }

        $images = array();
        for ($i = 1; $i <= 3; $i++) {
            $key = 'twintack_v2_collage_image_' . $i;
            if (isset($_POST[ $key ])) {
                $images[] = esc_url_raw(wp_unslash($_POST[ $key ]));
            }
        }
        update_post_meta($post_id, self::META_PREFIX . 'collage_images', $images);

        $bullets = array();
        if (isset($_POST['twintack_v2_bullet_title']) && is_array($_POST['twintack_v2_bullet_title'])) {
            $titles = wp_unslash($_POST['twintack_v2_bullet_title']);
            $descs  = isset($_POST['twintack_v2_bullet_desc']) ? wp_unslash($_POST['twintack_v2_bullet_desc']) : array();
            $icons  = isset($_POST['twintack_v2_bullet_icon']) ? wp_unslash($_POST['twintack_v2_bullet_icon']) : array();
            foreach ($titles as $idx => $title) {
                if ('' === trim($title)) {
                    continue;
                }
                $bullets[] = array(
                    'title' => sanitize_text_field($title),
                    'desc'  => isset($descs[ $idx ]) ? sanitize_text_field($descs[ $idx ]) : '',
                    'icon'  => isset($icons[ $idx ]) ? esc_url_raw($icons[ $idx ]) : '',
                );
            }
        }
        update_post_meta($post_id, self::META_PREFIX . 'collage_bullets', $bullets);

        $ugc = array();
        if (isset($_POST['twintack_v2_ugc_video']) && is_array($_POST['twintack_v2_ugc_video'])) {
            $videos  = wp_unslash($_POST['twintack_v2_ugc_video']);
            $posters = isset($_POST['twintack_v2_ugc_poster']) ? wp_unslash($_POST['twintack_v2_ugc_poster']) : array();
            foreach ($videos as $idx => $video) {
                if ('' === trim($video)) {
                    continue;
                }
                $ugc[] = array(
                    'video'  => esc_url_raw($video),
                    'poster' => isset($posters[ $idx ]) ? esc_url_raw($posters[ $idx ]) : '',
                );
            }
        }
        update_post_meta($post_id, self::META_PREFIX . 'ugc_videos', $ugc);
    }

    /**
     * Whether the current save uses the V2 homepage template.
     *
     * @param int $post_id Post ID.
     * @return bool
     */
    private function is_saving_v2_template($post_id) {
        if (isset($_POST['page_template'])) {
            return sanitize_text_field(wp_unslash($_POST['page_template'])) === self::TEMPLATE_SLUG;
        }
        return get_page_template_slug($post_id) === self::TEMPLATE_SLUG;
    }

    /**
     * @param int $post_id Post ID.
     * @return array<string,mixed>
     */
    public static function get_homepage_v2_meta($post_id) {
        $fields = array(
            'marquee_items', 'collage_heading_1', 'collage_heading_2', 'collage_paragraph',
            'collage_cta_text', 'collage_cta_url', 'collage_subtext', 'collage_images', 'collage_bullets',
            'best_sellers_heading', 'peak_heading', 'peak_subheading',
            'ugc_headline', 'ugc_subheadline', 'ugc_videos',
        );

        $meta = array();
        foreach ($fields as $field) {
            $meta[ $field ] = get_post_meta($post_id, self::META_PREFIX . $field, true);
        }

        $defaults = self::default_meta();
        foreach ($defaults as $key => $value) {
            if (is_array($value)) {
                if (!metadata_exists('post', $post_id, self::META_PREFIX . $key) || !is_array($meta[ $key ])) {
                    $meta[ $key ] = $value;
                }
                continue;
            }
            if (empty($meta[ $key ]) && $meta[ $key ] !== '0') {
                $meta[ $key ] = $value;
            }
        }

        if (!is_array($meta['collage_images'])) {
            $meta['collage_images'] = array();
        }

        if (is_array($meta['ugc_videos'])) {
            $normalized = array();
            foreach ($meta['ugc_videos'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $video = isset($item['video']) ? $item['video'] : (isset($item['url']) ? $item['url'] : '');
                if ('' === $video) {
                    continue;
                }
                $normalized[] = array(
                    'video'  => $video,
                    'poster' => isset($item['poster']) ? $item['poster'] : (isset($item['thumb']) ? $item['thumb'] : ''),
                );
            }
            $meta['ugc_videos'] = $normalized;
        } else {
            $meta['ugc_videos'] = array();
        }

        if (class_exists('TwinTack_Marketing_Marquee')) {
            $meta['marquee_items'] = TwinTack_Marketing_Marquee::get_items();
        }

        if (class_exists('TwinTack_Marketing_Video_Tabs')) {
            $global_video = TwinTack_Marketing_Video_Tabs::get_meta();
            $meta['video_tabs_title'] = $global_video['video_tabs_title'];
            $meta['video_tabs']       = $global_video['video_tabs'];
        }

        if (empty($meta['collage_cta_url'])) {
            $meta['collage_cta_url'] = self::get_default_shop_url();
        }

        return $meta;
    }

    /**
     * Default WooCommerce shop URL for collage CTA.
     *
     * @return string
     */
    public static function get_default_shop_url() {
        if (function_exists('wc_get_page_permalink')) {
            $shop_url = wc_get_page_permalink('shop');
            if ($shop_url) {
                return $shop_url;
            }
        }

        return home_url('/shop/');
    }

    /**
     * Default inline SVG icon for collage feature bullets.
     *
     * @param int $index Zero-based bullet index.
     * @return string
     */
    public static function get_collage_bullet_icon_svg($index) {
        $icons = array(
            0 => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2C6 8 4 12 4 15a8 8 0 0016 0c0-3-2-7-8-13z"></path></svg>',
            1 => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>',
            2 => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="1.5" fill="currentColor" stroke="none"></circle><circle cx="16" cy="8" r="1.5" fill="currentColor" stroke="none"></circle><circle cx="8" cy="16" r="1.5" fill="currentColor" stroke="none"></circle><circle cx="16" cy="16" r="1.5" fill="currentColor" stroke="none"></circle><circle cx="8" cy="12" r="1.5" fill="currentColor" stroke="none"></circle><circle cx="16" cy="12" r="1.5" fill="currentColor" stroke="none"></circle></svg>',
        );

        return isset($icons[ $index ]) ? $icons[ $index ] : $icons[0];
    }

    /**
     * Allowed SVG markup for collage bullet icons.
     *
     * @return array<string,array<string,bool>>
     */
    public static function get_collage_bullet_icon_allowed_html() {
        return array(
            'svg'    => array(
                'viewBox'         => true,
                'fill'            => true,
                'stroke'          => true,
                'stroke-width'    => true,
                'stroke-linecap'  => true,
                'stroke-linejoin' => true,
                'aria-hidden'     => true,
            ),
            'path'   => array('d' => true),
            'line'   => array('x1' => true, 'y1' => true, 'x2' => true, 'y2' => true),
            'circle' => array('cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true),
        );
    }

    /**
     * Shopify-aligned defaults for first preview.
     *
     * @return array<string,mixed>
     */
    public static function default_meta() {
        return array(
            'marquee_items'        => array(
                'Free Shipping on All Orders',
                '4.9/5 Stars — 500+ Reviews',
                '1 Year Warranty on Every Order',
                'Custom Grips Available',
            ),
            'collage_heading_1'    => 'Built for grip.',
            'collage_heading_2'    => 'Made to last.',
            'collage_paragraph'    => "TwinTack's unique formula gives superior grip, durability, water resistance, and UV protection for top performance you can't find anywhere else.",
            'collage_cta_text'     => 'Shop Now',
            'collage_cta_url'      => self::get_default_shop_url(),
            'collage_subtext'      => 'Buy 3 Grips & Save 15%',
            'collage_bullets'      => array(
                array('title' => 'Superior Grip Technology', 'desc' => 'Maintains grip in wet conditions', 'icon' => ''),
                array('title' => 'Built to Perform', 'desc' => 'Unlimited graphic possibilities with edge-to-edge clarity', 'icon' => ''),
                array('title' => 'Trusted by Athletes', 'desc' => 'The perfect level of tackiness for control without stickiness', 'icon' => ''),
            ),
            'best_sellers_heading' => 'SHOP OUR <em>BEST</em> <em>SELLERS:</em>',
            'peak_heading'         => 'Built For <em>Peak Performance</em>',
            'peak_subheading'      => 'H.T.P. (High Traction Polymer) technology for superior tackiness and unmatched performance in any condition.',
            'ugc_headline'         => 'See it <span class="tt-v2-accent">in action</span>',
            'ugc_subheadline'      => 'Real customers. Real results.',
            'video_tabs_title'     => 'HOW TWINTACK WORKS',
            'video_tabs'           => array(
                array(
                    'label'  => 'Reusability',
                    'tag'    => 'Built to Last',
                    'title'  => 'Designed to Be Reusable',
                    'body'   => 'Whether you have experienced a broken bat or simply want to change your style, re-application is easy.',
                    'video'  => '',
                    'poster' => '',
                ),
                array(
                    'label'  => 'Water Resistance',
                    'tag'    => 'Water Resistant',
                    'title'  => 'Crafted for Water Resistance',
                    'body'   => "Twin Tack's proprietary compound ensures that your grip remains effective even in the most challenging weather conditions.",
                    'video'  => '',
                    'poster' => '',
                ),
                array(
                    'label'  => 'How to Setup',
                    'tag'    => 'Easy Install',
                    'title'  => 'Simple Application',
                    'body'   => 'Watch the video to find out how to apply your new TwinTack bat grip.',
                    'video'  => '',
                    'poster' => '',
                ),
            ),
        );
    }
}
