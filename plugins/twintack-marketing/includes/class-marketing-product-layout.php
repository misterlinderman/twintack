<?php
/**
 * Product page Marketing V2 layout flag and meta.
 *
 * @package TwinTack_Marketing
 */

if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Marketing_Product_Layout {
    const META_LAYOUT = '_twintack_product_layout';
    const META_PREFIX = '_twintack_v2_product_';
    const OPTION_V2_GLOBAL = 'twintack_marketing_product_v2_global';

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

    const MIGRATION_FLAG = 'twintack_marketing_layout_inherit_migrated';
    const COMPARISON_CLEANUP_FLAG = 'twintack_marketing_comparison_default_cleared';
    const SHORT_PITCH_CLEANUP_FLAG = 'twintack_marketing_short_pitch_default_cleared';

    /**
     * Boilerplate short pitch an earlier build saved into product meta. When the
     * override still matches this exactly it should fall back to the WooCommerce
     * short description instead.
     */
    const LEGACY_DEFAULT_SHORT_PITCH = 'The Twin Tack Grip gives you a locked-in feel that holds up through every swing, in any condition.';

    private function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'maybe_migrate_layout_meta'));
        add_action('admin_init', array($this, 'maybe_clear_default_comparison_rows'));
        add_action('admin_init', array($this, 'maybe_clear_default_short_pitch'));
        add_filter('body_class', array($this, 'body_class'));
    }

    /**
     * One-time cleanup of the auto-saved boilerplate short pitch override so the
     * V2 hero reconnects to each product's WooCommerce short description.
     */
    public function maybe_clear_default_short_pitch() {
        if (get_option(self::SHORT_PITCH_CLEANUP_FLAG)) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $wpdb->delete(
            $wpdb->postmeta,
            array(
                'meta_key'   => self::META_PREFIX . 'short_pitch',
                'meta_value' => self::LEGACY_DEFAULT_SHORT_PITCH,
            )
        );

        update_option(self::SHORT_PITCH_CLEANUP_FLAG, '1');
    }

    /**
     * One-time cleanup: earlier builds had no "inherit" option, so the per-product
     * meta box silently wrote 'default' to every product that was saved, permanently
     * opting it out of the global Marketing V2 toggle. Clear those auto-written
     * values once so the global setting can apply. Products can still be explicitly
     * set to Default afterward.
     */
    public function maybe_migrate_layout_meta() {
        if (get_option(self::MIGRATION_FLAG)) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $wpdb->delete(
            $wpdb->postmeta,
            array(
                'meta_key'   => self::META_LAYOUT,
                'meta_value' => 'default',
            )
        );

        update_option(self::MIGRATION_FLAG, '1');
    }

    /**
     * One-time cleanup for the opt-in "Why TwinTack Wins" comparison chart.
     *
     * Earlier builds pre-filled the chart with sample rows; saving a product
     * persisted them, so the section kept rendering even though it is opt-in now.
     * Clear any untouched defaults while preserving genuinely customized charts.
     */
    public function maybe_clear_default_comparison_rows() {
        if (get_option(self::COMPARISON_CLEANUP_FLAG)) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $this->clear_default_comparison_rows();

        update_option(self::COMPARISON_CLEANUP_FLAG, '1');
    }

    /**
     * Remove comparison rows that exactly match the old boilerplate defaults.
     */
    private function clear_default_comparison_rows() {
        global $wpdb;

        $meta_key = self::META_PREFIX . 'comparison_rows';
        $rows     = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
                $meta_key
            )
        );

        if (empty($rows)) {
            return;
        }

        $default = self::default_comparison_rows();

        foreach ($rows as $row) {
            $value = maybe_unserialize($row->meta_value);

            if (self::comparison_rows_match_default($value, $default)) {
                delete_post_meta($row->post_id, $meta_key);
                delete_post_meta($row->post_id, self::META_PREFIX . 'comparison_title');
            }
        }
    }

    /**
     * Whether stored comparison rows are identical to the boilerplate defaults.
     *
     * @param mixed                          $value   Stored comparison rows.
     * @param array<int,array<string,string>> $default Default comparison rows.
     * @return bool
     */
    private static function comparison_rows_match_default($value, $default) {
        if (!is_array($value) || count($value) !== count($default)) {
            return false;
        }

        foreach ($default as $index => $default_row) {
            if (!isset($value[$index]) || !is_array($value[$index])) {
                return false;
            }

            foreach (array('feature', 'benefit', 'us', 'them') as $key) {
                $stored = isset($value[$index][$key]) ? (string) $value[$index][$key] : '';
                if ($stored !== (string) $default_row[$key]) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Register product layout settings.
     */
    public function register_settings() {
        register_setting(
            'twintack_product_layout',
            self::OPTION_V2_GLOBAL,
            array(
                'type'              => 'string',
                'sanitize_callback' => static function ($value) {
                    return '1' === $value ? '1' : '0';
                },
                'default'           => '0',
            )
        );
    }

    /**
     * Whether Marketing V2 is enabled site-wide (individual products may opt out).
     *
     * @return bool
     */
    public static function is_global_v2_enabled() {
        return '1' === get_option(self::OPTION_V2_GLOBAL, '0');
    }

    /**
     * @param int|null $product_id Product ID.
     * @return bool
     */
    public static function is_marketing_v2($product_id = null) {
        if (!is_product() && !$product_id) {
            return false;
        }

        if (!$product_id) {
            $product_id = get_the_ID();
        }

        if (!$product_id) {
            return false;
        }

        if (isset($_GET['preview_layout']) && 'marketing-v2' === sanitize_text_field(wp_unslash($_GET['preview_layout']))) {
            if (current_user_can('manage_woocommerce') || current_user_can('edit_product', $product_id)) {
                return true;
            }
        }

        $layout = get_post_meta($product_id, self::META_LAYOUT, true);

        if ('default' === $layout) {
            return false;
        }

        if ('marketing-v2' === $layout) {
            return true;
        }

        return self::is_global_v2_enabled();
    }

    /**
     * Whether a product has approved WooCommerce reviews to display.
     *
     * @param int|null $product_id Product ID.
     * @return bool
     */
    public static function product_has_active_reviews($product_id = null) {
        if (!$product_id) {
            $product_id = get_the_ID();
        }

        if (!$product_id || !function_exists('wc_get_product')) {
            return false;
        }

        $product = wc_get_product($product_id);

        return $product && $product->get_review_count() >= 1;
    }

    /**
     * @param string[] $classes Body classes.
     * @return string[]
     */
    public function body_class($classes) {
        if (self::is_marketing_v2()) {
            $classes[] = 'tt-v2-product';
        }
        return $classes;
    }

    /**
     * @param string $hook Admin hook.
     */
    public function enqueue_admin_assets($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'product') {
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
     * Rating line for V2 product summary — real reviews when available, placeholder otherwise.
     *
     * @param int $product_id Product ID.
     * @return string
     */
    public static function get_display_rating_line($product_id) {
        $display = self::get_summary_rating_display($product_id);

        return $display ? $display['line'] : '';
    }

    /**
     * Summary rating block for V2 hero — stars + text line.
     *
     * @param int $product_id Product ID.
     * @return array{rating:float,line:string,is_placeholder:bool}|null
     */
    public static function get_summary_rating_display($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return null;
        }

        $custom = get_post_meta($product_id, self::META_PREFIX . 'rating_line', true);
        if ($product->get_review_count() >= 1) {
            return array(
                'rating'         => (float) $product->get_average_rating(),
                'line'           => !empty($custom) ? $custom : sprintf(
                    /* translators: 1: average rating, 2: review count */
                    __('Rated %1$s / 5.0 • %2$s Reviews', 'twintack-marketing'),
                    number_format((float) $product->get_average_rating(), 1),
                    number_format_i18n($product->get_review_count())
                ),
                'is_placeholder' => false,
            );
        }

        return null;
    }

    /**
     * Register product meta boxes.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'twintack_product_layout',
            __('Marketing Layout', 'twintack-marketing'),
            array($this, 'render_layout_meta_box'),
            'product',
            'side',
            'default'
        );

        add_meta_box(
            'twintack_product_v2_content',
            __('Product V2 — Extra Content', 'twintack-marketing'),
            array($this, 'render_content_meta_box'),
            'product',
            'normal',
            'high'
        );
    }

    /**
     * @param WP_Post $post Product post.
     */
    public function render_layout_meta_box($post) {
        wp_nonce_field('twintack_product_layout_nonce', 'twintack_product_layout_nonce');
        $layout         = get_post_meta($post->ID, self::META_LAYOUT, true);
        $global_enabled = self::is_global_v2_enabled();
        $inherit_label  = $global_enabled
            ? __('Use global setting (currently: Marketing V2)', 'twintack-marketing')
            : __('Use global setting (currently: Default)', 'twintack-marketing');
        ?>
        <p>
            <label for="twintack_product_layout"><?php esc_html_e('Product template', 'twintack-marketing'); ?></label>
        </p>
        <select name="twintack_product_layout" id="twintack_product_layout" style="width:100%;">
            <option value="" <?php selected($layout, ''); ?>><?php echo esc_html($inherit_label); ?></option>
            <option value="default" <?php selected($layout, 'default'); ?>><?php esc_html_e('Force Default (legacy)', 'twintack-marketing'); ?></option>
            <option value="marketing-v2" <?php selected($layout, 'marketing-v2'); ?>><?php esc_html_e('Force Marketing V2', 'twintack-marketing'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('“Use global setting” follows Marketing → Product Layout. Choose Force Default to keep the legacy layout on this product even when V2 is enabled globally, or Force Marketing V2 to opt this product in regardless of the global setting.', 'twintack-marketing'); ?></p>
        <?php
    }

    /**
     * @param WP_Post $post Product post.
     */
    public function render_content_meta_box($post) {
        $meta         = self::get_product_v2_meta($post->ID);
        $review_count = 0;
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($post->ID);
            if ($product) {
                $review_count = (int) $product->get_review_count();
            }
        }
        ?>
        <div class="tt-v2-admin-wrap">
            <div class="tt-v2-admin-intro">
                <p><?php esc_html_e('Content blocks for the Marketing V2 product layout. Gallery and variations come from the standard WooCommerce product data.', 'twintack-marketing'); ?></p>
                <p>
                    <?php
                    printf(
                        wp_kses_post(
                            /* translators: %s: admin settings URL */
                            __('The bottom “How TwinTack Works” video tabs are managed globally under <a href="%s">Marketing → How TwinTack Works</a>.', 'twintack-marketing')
                        ),
                        esc_url(admin_url('admin.php?page=twintack-marketing-video-tabs'))
                    );
                    ?>
                </p>
                <p>
                    <?php esc_html_e('Reviews summary', 'twintack-marketing'); ?>:
                    <?php if ($review_count > 0) : ?>
                        <span class="tt-v2-admin-status tt-v2-admin-status--ok"><?php echo esc_html(sprintf(_n('%d review', '%d reviews', $review_count, 'twintack-marketing'), $review_count)); ?></span>
                    <?php else : ?>
                        <span class="tt-v2-admin-status tt-v2-admin-status--empty"><?php esc_html_e('Hidden until approved reviews exist', 'twintack-marketing'); ?></span>
                    <?php endif; ?>
                </p>
            </div>

            <details class="tt-v2-admin-panel" open>
                <summary><?php esc_html_e('Above the fold — Summary', 'twintack-marketing'); ?></summary>
                <div class="tt-v2-admin-panel__body">
                    <div class="tt-v2-admin-field">
                        <label for="twintack_v2_rating_line"><?php esc_html_e('Rating line override', 'twintack-marketing'); ?></label>
                        <input type="text" name="twintack_v2_rating_line" id="twintack_v2_rating_line" value="<?php echo esc_attr($meta['rating_line']); ?>" class="large-text" placeholder="<?php esc_attr_e('Rated 4.9 / 5.0 • 127 Reviews', 'twintack-marketing'); ?>">
                        <p class="description"><?php esc_html_e('Optional override when the product has approved reviews. Summary rating and the bottom reviews block stay hidden until then.', 'twintack-marketing'); ?></p>
                    </div>
                    <div class="tt-v2-admin-field">
                        <label for="twintack_v2_short_pitch"><?php esc_html_e('Short pitch override', 'twintack-marketing'); ?></label>
                        <textarea name="twintack_v2_short_pitch" id="twintack_v2_short_pitch" rows="2" class="large-text"><?php echo esc_textarea($meta['short_pitch']); ?></textarea>
                        <p class="description"><?php esc_html_e('Leave blank to use the WooCommerce product short description from the product editor.', 'twintack-marketing'); ?></p>
                    </div>
                    <div class="tt-v2-admin-field">
                        <label for="twintack_v2_summary_bullets"><?php esc_html_e('Summary bullets (one per line: title|description optional)', 'twintack-marketing'); ?></label>
                        <textarea name="twintack_v2_summary_bullets" id="twintack_v2_summary_bullets" rows="4" class="large-text"><?php
                            $lines = array();
                            foreach ((array) $meta['summary_bullets'] as $bullet) {
                                $line = $bullet['title'];
                                if (!empty($bullet['desc'])) {
                                    $line .= '|' . $bullet['desc'];
                                }
                                $lines[] = $line;
                            }
                            echo esc_textarea(implode("\n", $lines));
                        ?></textarea>
                    </div>
                    <div class="tt-v2-admin-field">
                        <label for="twintack_v2_trust_icons"><?php esc_html_e('Trust badges (one phrase per line, e.g. “Waterproof Technology”)', 'twintack-marketing'); ?></label>
                        <textarea name="twintack_v2_trust_icons" id="twintack_v2_trust_icons" rows="3" class="large-text"><?php
                            echo esc_textarea(implode("\n", (array) $meta['trust_icons']));
                        ?></textarea>
                        <p class="description"><?php esc_html_e('Icons appear below Add to Cart. Use a pipe for custom line breaks, e.g. Waterproof|Technology.', 'twintack-marketing'); ?></p>
                    </div>
                    <div class="tt-v2-admin-field">
                        <label for="twintack_v2_howto_video"><?php esc_html_e('How-to apply video URL', 'twintack-marketing'); ?></label>
                        <input type="url" name="twintack_v2_howto_video" id="twintack_v2_howto_video" value="<?php echo esc_url($meta['howto_video']); ?>" class="large-text">
                    </div>
                </div>
            </details>

            <details class="tt-v2-admin-panel">
                <summary><?php esc_html_e('Below the fold — Comparison chart', 'twintack-marketing'); ?></summary>
                <div class="tt-v2-admin-panel__body">
                    <div class="tt-v2-admin-field">
                        <label for="twintack_v2_comparison_title"><?php esc_html_e('Chart title', 'twintack-marketing'); ?></label>
                        <input type="text" name="twintack_v2_comparison_title" id="twintack_v2_comparison_title" value="<?php echo esc_attr($meta['comparison_title']); ?>" class="large-text">
                    </div>
                    <div class="tt-v2-admin-field">
                        <label for="twintack_v2_comparison_rows"><?php esc_html_e('Rows (feature|benefit|us|them — one per line)', 'twintack-marketing'); ?></label>
                        <textarea name="twintack_v2_comparison_rows" id="twintack_v2_comparison_rows" rows="6" class="large-text"><?php
                            $rows = array();
                            foreach ((array) $meta['comparison_rows'] as $row) {
                                $rows[] = implode('|', array(
                                    $row['feature'] ?? '',
                                    $row['benefit'] ?? '',
                                    $row['us'] ?? '',
                                    $row['them'] ?? '',
                                ));
                            }
                            echo esc_textarea(implode("\n", $rows));
                        ?></textarea>
                    </div>
                </div>
            </details>
        </div>
        <?php
    }

    /**
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public function save_meta_boxes($post_id, $post) {
        if (!isset($_POST['twintack_product_layout_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['twintack_product_layout_nonce'])), 'twintack_product_layout_nonce')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if ($post->post_type !== 'product') {
            return;
        }

        if (isset($_POST['twintack_product_layout'])) {
            $layout = sanitize_text_field(wp_unslash($_POST['twintack_product_layout']));
            if (!in_array($layout, array('', 'default', 'marketing-v2'), true)) {
                $layout = '';
            }
            if ('' === $layout) {
                // Inherit the global setting; no per-product override.
                delete_post_meta($post_id, self::META_LAYOUT);
            } else {
                update_post_meta($post_id, self::META_LAYOUT, $layout);
            }
        }

        $map = array(
            'rating_line'       => 'twintack_v2_rating_line',
            'short_pitch'       => 'twintack_v2_short_pitch',
            'comparison_title'  => 'twintack_v2_comparison_title',
        );
        foreach ($map as $meta_key => $post_key) {
            if (isset($_POST[ $post_key ])) {
                update_post_meta($post_id, self::META_PREFIX . $meta_key, sanitize_text_field(wp_unslash($_POST[ $post_key ])));
            }
        }

        if (isset($_POST['twintack_v2_howto_video'])) {
            update_post_meta($post_id, self::META_PREFIX . 'howto_video', esc_url_raw(wp_unslash($_POST['twintack_v2_howto_video'])));
        }

        if (isset($_POST['twintack_v2_summary_bullets'])) {
            $bullets = array();
            $lines   = array_filter(array_map('trim', explode("\n", wp_unslash($_POST['twintack_v2_summary_bullets']))));
            foreach ($lines as $line) {
                $parts = array_map('trim', explode('|', $line, 2));
                $bullets[] = array(
                    'title' => sanitize_text_field($parts[0]),
                    'desc'  => isset($parts[1]) ? sanitize_text_field($parts[1]) : '',
                );
            }
            update_post_meta($post_id, self::META_PREFIX . 'summary_bullets', $bullets);
        }

        if (isset($_POST['twintack_v2_trust_icons'])) {
            $icons = array_filter(array_map('trim', explode("\n", wp_unslash($_POST['twintack_v2_trust_icons']))));
            update_post_meta($post_id, self::META_PREFIX . 'trust_icons', array_map('sanitize_text_field', $icons));
        }

        if (isset($_POST['twintack_v2_comparison_rows'])) {
            $rows  = array();
            $lines = array_filter(array_map('trim', explode("\n", wp_unslash($_POST['twintack_v2_comparison_rows']))));
            foreach ($lines as $line) {
                $parts = array_map('trim', explode('|', $line));
                if (empty($parts[0])) {
                    continue;
                }
                $rows[] = array(
                    'feature' => sanitize_text_field($parts[0] ?? ''),
                    'benefit' => sanitize_text_field($parts[1] ?? ''),
                    'us'      => sanitize_text_field($parts[2] ?? ''),
                    'them'    => sanitize_text_field($parts[3] ?? ''),
                );
            }
            update_post_meta($post_id, self::META_PREFIX . 'comparison_rows', $rows);
        }
    }

    /**
     * @param int $product_id Product ID.
     * @return array<string,mixed>
     */
    public static function get_product_v2_meta($product_id) {
        $fields = array(
            'rating_line', 'short_pitch', 'summary_bullets', 'trust_icons',
            'howto_video', 'comparison_title', 'comparison_rows', 'ugc_videos',
        );

        $meta = array();
        foreach ($fields as $field) {
            $meta[ $field ] = get_post_meta($product_id, self::META_PREFIX . $field, true);
        }

        if (!is_string($meta['rating_line'])) {
            $meta['rating_line'] = '';
        }
        if (!is_string($meta['short_pitch'])) {
            $meta['short_pitch'] = '';
        }
        if (!is_array($meta['summary_bullets'])) {
            $meta['summary_bullets'] = array(
                array('title' => 'Provides Exceptional Grip', 'desc' => ''),
                array('title' => 'Reusable on Multiple Bats', 'desc' => ''),
                array('title' => 'Repels Water & Moisture', 'desc' => ''),
            );
        }
        if (!is_array($meta['trust_icons']) || empty($meta['trust_icons'])) {
            if (!metadata_exists('post', $product_id, self::META_PREFIX . 'trust_icons')) {
                $meta['trust_icons'] = self::default_trust_icons();
            } else {
                $meta['trust_icons'] = is_array($meta['trust_icons']) ? $meta['trust_icons'] : array();
            }
        }
        $meta['trust_icons'] = self::normalize_trust_icons($meta['trust_icons']);
        if (!is_array($meta['comparison_rows'])) {
            $meta['comparison_rows'] = array();
        }
        if (empty($meta['comparison_title'])) {
            $meta['comparison_title'] = 'Why TwinTack Wins';
        }

        if (empty($meta['howto_video'])) {
            $meta['howto_video'] = self::get_howto_video_url($product_id);
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

        return $meta;
    }

    /**
     * Short pitch for V2 hero — custom meta override, else WooCommerce short description.
     *
     * @param int|null $product_id Product ID.
     * @return string
     */
    public static function get_short_pitch_display($product_id = null) {
        if (!$product_id) {
            $product_id = get_the_ID();
        }

        if ($product_id) {
            $custom = get_post_meta($product_id, self::META_PREFIX . 'short_pitch', true);
            // Ignore the legacy auto-saved boilerplate so it falls back to product data.
            if (is_string($custom) && '' !== trim($custom) && trim($custom) !== self::LEGACY_DEFAULT_SHORT_PITCH) {
                return $custom;
            }
        }

        $product = $product_id ? wc_get_product($product_id) : null;
        if (!$product) {
            return '';
        }

        return apply_filters('woocommerce_short_description', $product->get_short_description());
    }

    /**
     * How-to apply video URL — per-product meta, then global "How to Setup" tab.
     *
     * @param int|null $product_id Product ID.
     * @return string Video URL or empty string.
     */
    public static function get_howto_video_url($product_id = null) {
        if (!$product_id) {
            $product_id = get_the_ID();
        }

        if ($product_id) {
            $custom = get_post_meta($product_id, self::META_PREFIX . 'howto_video', true);
            if (is_string($custom) && '' !== $custom) {
                return esc_url($custom);
            }
        }

        if (!class_exists('TwinTack_Marketing_Video_Tabs')) {
            return '';
        }

        $tabs_meta = TwinTack_Marketing_Video_Tabs::get_meta();
        if (empty($tabs_meta['video_tabs']) || !is_array($tabs_meta['video_tabs'])) {
            return '';
        }

        foreach ($tabs_meta['video_tabs'] as $tab) {
            if (!is_array($tab) || empty($tab['video'])) {
                continue;
            }

            $label = isset($tab['label']) ? strtolower($tab['label']) : '';
            if (
                false !== strpos($label, 'setup')
                || false !== strpos($label, 'apply')
                || false !== strpos($label, 'install')
            ) {
                return esc_url($tab['video']);
            }
        }

        if (isset($tabs_meta['video_tabs'][2]['video']) && !empty($tabs_meta['video_tabs'][2]['video'])) {
            return esc_url($tabs_meta['video_tabs'][2]['video']);
        }

        return '';
    }

    /**
     * Default trust badge labels below add-to-cart.
     *
     * @return array<int,string>
     */
    public static function default_trust_icons() {
        return array(
            'Waterproof Technology',
            'Buy 3 & Save 15%',
            'Fast Shipping',
        );
    }

    /**
     * Font Awesome icon + two-line label for a trust badge.
     *
     * @param string $label Trust badge label.
     * @param int    $index Zero-based position fallback.
     * @return array{icon:string,line1:string,line2:string}
     */
    public static function get_trust_icon_item($label, $index = 0) {
        $label = trim((string) $label);
        $key   = strtolower(preg_replace('/\s+/', ' ', $label));

        $known = array(
            'waterproof technology' => array(
                'icon'  => 'fa-droplet',
                'line1' => 'Waterproof',
                'line2' => 'Technology',
            ),
            'buy 3 & save 15%' => array(
                'icon'  => 'fa-cart-arrow-down',
                'line1' => 'Buy 3 &',
                'line2' => 'Save 15%',
            ),
            'fast shipping' => array(
                'icon'  => 'fa-box-open',
                'line1' => 'Fast',
                'line2' => 'Shipping',
            ),
        );

        if (isset($known[ $key ])) {
            return $known[ $key ];
        }

        foreach ($known as $needle => $item) {
            if (false !== strpos($key, $needle)) {
                return $item;
            }
        }

        $fallback_icons = array('fa-droplet', 'fa-cart-arrow-down', 'fa-box-open');
        $icon           = $fallback_icons[ $index % count($fallback_icons) ];
        $lines          = self::split_trust_label_lines($label);

        return array(
            'icon'  => $icon,
            'line1' => $lines['line1'],
            'line2' => $lines['line2'],
        );
    }

    /**
     * Split a trust badge label into two display lines.
     *
     * @param string $label Trust badge label.
     * @return array{line1:string,line2:string}
     */
    private static function split_trust_label_lines($label) {
        if (false !== strpos($label, '|')) {
            $parts = array_map('trim', explode('|', $label, 2));
            return array(
                'line1' => $parts[0],
                'line2' => isset($parts[1]) ? $parts[1] : '',
            );
        }

        if (preg_match('/^(.+?\s+\&)\s+(.+)$/i', $label, $matches)) {
            return array(
                'line1' => trim($matches[1]),
                'line2' => trim($matches[2]),
            );
        }

        $words = preg_split('/\s+/', $label);
        if (count($words) <= 1) {
            return array(
                'line1' => $label,
                'line2' => '',
            );
        }

        if (count($words) === 2) {
            return array(
                'line1' => $words[0],
                'line2' => $words[1],
            );
        }

        $split_at = (int) ceil(count($words) / 2);

        return array(
            'line1' => implode(' ', array_slice($words, 0, $split_at)),
            'line2' => implode(' ', array_slice($words, $split_at)),
        );
    }

    /**
     * Normalize trust badges saved with line breaks or split across rows.
     *
     * @param array<int,string> $icons Trust badge labels.
     * @return array<int,string>
     */
    public static function normalize_trust_icons($icons) {
        if (!is_array($icons) || empty($icons)) {
            return self::default_trust_icons();
        }

        $icons = array_values(array_filter(array_map(
            static function ($label) {
                return trim(str_replace(array("\r\n", "\r", "\n"), ' ', (string) $label));
            },
            $icons
        )));

        if (6 === count($icons)) {
            return array(
                trim($icons[0] . ' ' . $icons[1]),
                trim($icons[2] . ' ' . $icons[3]),
                trim($icons[4] . ' ' . $icons[5]),
            );
        }

        return $icons;
    }

    /**
     * Default comparison chart rows for first preview.
     *
     * @return array<int,array<string,string>>
     */
    public static function default_comparison_rows() {
        return array(
            array(
                'feature' => 'Water resistance',
                'benefit' => 'Maintains tack in wet conditions',
                'us'      => 'Yes',
                'them'    => 'No',
            ),
            array(
                'feature' => 'Reusable design',
                'benefit' => 'Reapply when you change bats or style',
                'us'      => 'Yes',
                'them'    => 'Limited',
            ),
            array(
                'feature' => 'Edge-to-edge graphics',
                'benefit' => 'Full-wrap clarity without seams',
                'us'      => 'Yes',
                'them'    => 'No',
            ),
        );
    }

    /**
     * Render global product layout settings page.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $global_enabled = self::is_global_v2_enabled();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Product Layout', 'twintack-marketing'); ?></h1>
            <p class="description"><?php esc_html_e('Control the Marketing V2 product template across your catalog.', 'twintack-marketing'); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields('twintack_product_layout'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Marketing V2 for all products', 'twintack-marketing'); ?></th>
                        <td>
                            <input type="hidden" name="<?php echo esc_attr(self::OPTION_V2_GLOBAL); ?>" value="0" />
                            <label for="<?php echo esc_attr(self::OPTION_V2_GLOBAL); ?>">
                                <input type="checkbox"
                                       name="<?php echo esc_attr(self::OPTION_V2_GLOBAL); ?>"
                                       id="<?php echo esc_attr(self::OPTION_V2_GLOBAL); ?>"
                                       value="1"
                                       <?php checked($global_enabled); ?> />
                                <?php esc_html_e('Use the Marketing V2 product page on every product', 'twintack-marketing'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('When enabled, all products use V2 unless a product is explicitly set to Default under Marketing Layout. Preview any product as an admin with ?preview_layout=marketing-v2 on the product URL.', 'twintack-marketing'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save Product Layout Settings', 'twintack-marketing')); ?>
            </form>

            <hr>

            <h2><?php esc_html_e('Go-live checklist', 'twintack-marketing'); ?></h2>
            <ol>
                <li><?php esc_html_e('Deploy the latest theme and TwinTack Marketing plugin files.', 'twintack-marketing'); ?></li>
                <li><?php esc_html_e('Configure Marketing → How TwinTack Works (video tabs shown on every V2 product page).', 'twintack-marketing'); ?></li>
                <li><?php esc_html_e('Spot-check one product with ?preview_layout=marketing-v2 before enabling globally.', 'twintack-marketing'); ?></li>
                <li><?php esc_html_e('Enable Marketing V2 for all products above, or set Marketing V2 per product in the product editor sidebar.', 'twintack-marketing'); ?></li>
                <li><?php esc_html_e('Optionally fill in Product V2 — Extra Content on key SKUs (pitch, bullets, comparison chart, how-to video).', 'twintack-marketing'); ?></li>
            </ol>
        </div>
        <?php
    }
}
