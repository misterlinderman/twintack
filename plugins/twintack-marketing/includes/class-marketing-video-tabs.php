<?php
/**
 * Global "How TwinTack Works" video tabs for Marketing V2 homepage and products.
 *
 * @package TwinTack_Marketing
 */

if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Marketing_Video_Tabs {
    const OPTION_KEY = 'twintack_marketing_video_tabs';

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
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Register option and sanitization.
     */
    public function register_settings() {
        register_setting(
            'twintack_marketing_video_tabs',
            self::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default'           => self::default_meta(),
            )
        );
    }

    /**
     * @param string $hook Admin hook suffix.
     */
    public function enqueue_admin_assets($hook) {
        if ('marketing_page_twintack-marketing-video-tabs' !== $hook) {
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
        wp_enqueue_script(
            'twintack-marketing-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js',
            array('jquery'),
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/admin.js'),
            true
        );
    }

    /**
     * @param array<string,mixed>|mixed $input Raw settings.
     * @return array<string,mixed>
     */
    public function sanitize_settings($input) {
        if (!is_array($input)) {
            return self::default_meta();
        }

        $tabs = array();
        if (isset($input['tabs']) && is_array($input['tabs'])) {
            foreach ($input['tabs'] as $tab) {
                if (!is_array($tab)) {
                    continue;
                }
                $label = isset($tab['label']) ? trim(sanitize_text_field($tab['label'])) : '';
                if ('' === $label) {
                    continue;
                }
                $tabs[] = array(
                    'label'  => $label,
                    'tag'    => isset($tab['tag']) ? sanitize_text_field($tab['tag']) : '',
                    'title'  => isset($tab['title']) ? sanitize_text_field($tab['title']) : '',
                    'body'   => isset($tab['body']) ? sanitize_textarea_field($tab['body']) : '',
                    'video'  => isset($tab['video']) ? esc_url_raw($tab['video']) : '',
                    'poster' => isset($tab['poster']) ? esc_url_raw($tab['poster']) : '',
                );
            }
        }

        return array(
            'video_tabs_title' => isset($input['video_tabs_title']) ? sanitize_text_field($input['video_tabs_title']) : '',
            'video_tabs'       => $tabs,
        );
    }

    /**
     * Video tab meta for frontend templates.
     *
     * @return array{video_tabs_title:string,video_tabs:array<int,array<string,string>>}
     */
    public static function get_meta() {
        $settings = get_option(self::OPTION_KEY);

        if (!is_array($settings) || empty($settings['video_tabs'])) {
            $settings = self::maybe_migrate_legacy_settings();
        }

        $defaults = self::default_meta();

        return array(
            'video_tabs_title' => !empty($settings['video_tabs_title']) ? $settings['video_tabs_title'] : $defaults['video_tabs_title'],
            'video_tabs'       => self::normalize_tabs(
                !empty($settings['video_tabs']) && is_array($settings['video_tabs']) ? $settings['video_tabs'] : $defaults['video_tabs']
            ),
        );
    }

    /**
     * Pull saved tabs from the Homepage V2 page when global settings are empty.
     *
     * @return array<string,mixed>
     */
    private static function maybe_migrate_legacy_settings() {
        $legacy = self::get_legacy_homepage_video_tabs();
        if (!empty($legacy['video_tabs'])) {
            update_option(self::OPTION_KEY, $legacy, false);
            return $legacy;
        }

        return self::default_meta();
    }

    /**
     * @return array{video_tabs_title:string,video_tabs:array<int,array<string,string>>}
     */
    private static function get_legacy_homepage_video_tabs() {
        if (!class_exists('TwinTack_Marketing_Homepage_V2')) {
            return array('video_tabs_title' => '', 'video_tabs' => array());
        }

        $pages = get_posts(
            array(
                'post_type'      => 'page',
                'posts_per_page' => 1,
                'post_status'    => array('publish', 'draft', 'private'),
                'meta_key'       => '_wp_page_template',
                'meta_value'     => TwinTack_Marketing_Homepage_V2::TEMPLATE_SLUG,
                'fields'         => 'ids',
            )
        );

        if (empty($pages[0])) {
            return array('video_tabs_title' => '', 'video_tabs' => array());
        }

        $post_id = (int) $pages[0];
        $prefix  = TwinTack_Marketing_Homepage_V2::META_PREFIX;

        return array(
            'video_tabs_title' => (string) get_post_meta($post_id, $prefix . 'video_tabs_title', true),
            'video_tabs'       => (array) get_post_meta($post_id, $prefix . 'video_tabs', true),
        );
    }

    /**
     * @param array<int,array<string,string>> $tabs Tab rows.
     * @return array<int,array<string,string>>
     */
    private static function normalize_tabs($tabs) {
        $normalized = array();

        foreach ((array) $tabs as $tab) {
            if (!is_array($tab)) {
                continue;
            }
            $label = isset($tab['label']) ? trim((string) $tab['label']) : '';
            if ('' === $label) {
                continue;
            }
            $normalized[] = array(
                'label'  => $label,
                'tag'    => isset($tab['tag']) ? (string) $tab['tag'] : '',
                'title'  => isset($tab['title']) ? (string) $tab['title'] : '',
                'body'   => isset($tab['body']) ? (string) $tab['body'] : (isset($tab['caption']) ? (string) $tab['caption'] : ''),
                'video'  => isset($tab['video']) ? (string) $tab['video'] : '',
                'poster' => isset($tab['poster']) ? (string) $tab['poster'] : '',
            );
        }

        return self::apply_preferred_tab_order($normalized);
    }

    /**
     * Ensure Reusability precedes Water Resistance for legacy saved tab order.
     *
     * @param array<int,array<string,string>> $tabs Tab rows.
     * @return array<int,array<string,string>>
     */
    private static function apply_preferred_tab_order($tabs) {
        if (count($tabs) < 2) {
            return $tabs;
        }

        $first  = strtolower(trim($tabs[0]['label']));
        $second = strtolower(trim($tabs[1]['label']));

        if (false !== strpos($first, 'water') && false !== strpos($second, 'reus')) {
            $swap       = $tabs[0];
            $tabs[0]    = $tabs[1];
            $tabs[1]    = $swap;
        }

        return $tabs;
    }

    /**
     * Inline SVG icon for a video tab button (matches Shopify Galleria tabs).
     *
     * @param int    $index Zero-based tab index.
     * @param string $label Tab label for icon matching.
     * @return string
     */
    public static function get_tab_icon_svg($index, $label = '') {
        $icons = array(
            'water' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2C6 8 4 12 4 15a8 8 0 0016 0c0-3-2-7-8-13z"></path></svg>',
            'reuse' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"></path></svg>',
            'setup' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M19.07 19.07l-1.41-1.41M4.93 19.07l1.41-1.41M12 2v2M12 20v2M2 12h2M20 12h2"></path></svg>',
        );

        $slug = strtolower(trim($label));
        if (false !== strpos($slug, 'reus')) {
            return $icons['reuse'];
        }
        if (false !== strpos($slug, 'water')) {
            return $icons['water'];
        }
        if (false !== strpos($slug, 'setup') || false !== strpos($slug, 'install') || false !== strpos($slug, 'how')) {
            return $icons['setup'];
        }

        $fallback = array_values($icons);
        return isset($fallback[ $index ]) ? $fallback[ $index ] : $icons['water'];
    }

    /**
     * Allowed SVG markup for tab icons.
     *
     * @return array<string,array<string,bool>>
     */
    public static function get_tab_icon_allowed_html() {
        return array(
            'svg'      => array(
                'viewBox'           => true,
                'fill'              => true,
                'stroke'            => true,
                'stroke-width'      => true,
                'stroke-linecap'    => true,
                'stroke-linejoin'   => true,
                'aria-hidden'       => true,
                'focusable'         => true,
            ),
            'path'     => array('d' => true),
            'polyline' => array('points' => true),
            'circle'   => array('cx' => true, 'cy' => true, 'r' => true),
        );
    }

    /**
     * Default tab content.
     *
     * @return array{video_tabs_title:string,video_tabs:array<int,array<string,string>>}
     */
    public static function default_meta() {
        if (class_exists('TwinTack_Marketing_Homepage_V2')) {
            $defaults = TwinTack_Marketing_Homepage_V2::default_meta();
            return array(
                'video_tabs_title' => $defaults['video_tabs_title'],
                'video_tabs'       => $defaults['video_tabs'],
            );
        }

        return array(
            'video_tabs_title' => 'HOW TWINTACK WORKS',
            'video_tabs'       => array(
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

    /**
     * Render Marketing admin settings page.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $meta = self::get_meta();
        $tabs = (array) $meta['video_tabs'];

        while (count($tabs) < 3) {
            $tabs[] = array(
                'label' => '', 'tag' => '', 'title' => '', 'body' => '', 'video' => '', 'poster' => '',
            );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('How TwinTack Works — Video Tabs', 'twintack-marketing'); ?></h1>
            <p class="description">
                <?php esc_html_e('Global tabbed video section used on the Homepage V2 preview and all Marketing V2 product pages. Update once — it applies everywhere.', 'twintack-marketing'); ?>
            </p>

            <form method="post" action="options.php" class="tt-v2-admin-wrap">
                <?php settings_fields('twintack_marketing_video_tabs'); ?>

                <div class="tt-v2-admin-panel__body" style="max-width:960px;">
                    <div class="tt-v2-admin-field">
                        <label for="twintack_video_tabs_title"><?php esc_html_e('Section title', 'twintack-marketing'); ?></label>
                        <input
                            type="text"
                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[video_tabs_title]"
                            id="twintack_video_tabs_title"
                            value="<?php echo esc_attr($meta['video_tabs_title']); ?>"
                            class="large-text"
                        >
                    </div>

                    <?php for ($t = 0; $t < 3; $t++) :
                        $tab = isset($tabs[ $t ]) ? $tabs[ $t ] : array(
                            'label' => '', 'tag' => '', 'title' => '', 'body' => '', 'video' => '', 'poster' => '',
                        );
                        ?>
                        <div class="tt-v2-admin-card twintack-video-tab-editor">
                            <p class="tt-v2-admin-card__title"><?php printf(esc_html__('Tab %d', 'twintack-marketing'), $t + 1); ?></p>
                            <div class="tt-v2-admin-grid-2">
                                <input
                                    type="text"
                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[tabs][<?php echo (int) $t; ?>][label]"
                                    value="<?php echo esc_attr($tab['label']); ?>"
                                    placeholder="<?php esc_attr_e('Tab label', 'twintack-marketing'); ?>"
                                    class="large-text"
                                >
                                <input
                                    type="text"
                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[tabs][<?php echo (int) $t; ?>][tag]"
                                    value="<?php echo esc_attr($tab['tag']); ?>"
                                    placeholder="<?php esc_attr_e('Eyebrow tag', 'twintack-marketing'); ?>"
                                    class="large-text"
                                >
                            </div>
                            <div class="tt-v2-admin-field">
                                <input
                                    type="text"
                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[tabs][<?php echo (int) $t; ?>][title]"
                                    value="<?php echo esc_attr($tab['title']); ?>"
                                    placeholder="<?php esc_attr_e('Panel title', 'twintack-marketing'); ?>"
                                    class="large-text"
                                >
                            </div>
                            <div class="tt-v2-admin-field">
                                <textarea
                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[tabs][<?php echo (int) $t; ?>][body]"
                                    rows="2"
                                    class="large-text"
                                    placeholder="<?php esc_attr_e('Panel body', 'twintack-marketing'); ?>"
                                ><?php echo esc_textarea($tab['body']); ?></textarea>
                            </div>
                            <div class="tt-v2-admin-field">
                                <label><?php esc_html_e('MP4 URL', 'twintack-marketing'); ?></label>
                                <div class="video-upload-wrapper">
                                    <input
                                        type="url"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[tabs][<?php echo (int) $t; ?>][video]"
                                        class="large-text video-url"
                                        value="<?php echo esc_url($tab['video']); ?>"
                                    >
                                    <button type="button" class="button upload-video"><?php esc_html_e('Upload Video', 'twintack-marketing'); ?></button>
                                </div>
                            </div>
                            <div class="tt-v2-admin-field">
                                <label><?php esc_html_e('Poster image', 'twintack-marketing'); ?></label>
                                <div class="image-upload-wrapper">
                                    <input
                                        type="url"
                                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[tabs][<?php echo (int) $t; ?>][poster]"
                                        class="large-text image-url"
                                        value="<?php echo esc_url($tab['poster']); ?>"
                                    >
                                    <div class="image-preview">
                                        <?php if (!empty($tab['poster'])) : ?>
                                            <img src="<?php echo esc_url($tab['poster']); ?>" alt="" style="max-width:200px;height:auto;">
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button upload-image"><?php esc_html_e('Upload Image', 'twintack-marketing'); ?></button>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <?php submit_button(__('Save Video Tabs', 'twintack-marketing')); ?>
            </form>
        </div>
        <?php
    }
}
