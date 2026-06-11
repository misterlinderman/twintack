<?php
/**
 * Homepage V2 scrolling marquee — global settings.
 *
 * @package TwinTack_Marketing
 */

if (!defined('ABSPATH')) {
    exit;
}

class TwinTack_Marketing_Marquee {
    const OPTION_KEY = 'twintack_marketing_v2_marquee_items';

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
     * @param string $hook Admin screen hook.
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'twintack-marketing-marquee') === false) {
            return;
        }

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
     * Register marquee option.
     */
    public function register_settings() {
        register_setting(
            'twintack_marketing_marquee',
            self::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array($this, 'sanitize_items'),
                'default'           => self::default_items(),
            )
        );
    }

    /**
     * @param mixed $input Raw option value.
     * @return array<int,string>
     */
    public function sanitize_items($input) {
        if (!is_array($input)) {
            return self::default_items();
        }

        $items = array();
        foreach ($input as $line) {
            $line = sanitize_text_field(wp_unslash((string) $line));
            if ('' !== $line) {
                $items[] = $line;
            }
        }

        return !empty($items) ? $items : self::default_items();
    }

    /**
     * @return array<int,string>
     */
    public static function default_items() {
        if (class_exists('TwinTack_Marketing_Homepage_V2')) {
            $defaults = TwinTack_Marketing_Homepage_V2::default_meta();
            if (!empty($defaults['marquee_items']) && is_array($defaults['marquee_items'])) {
                return array_values($defaults['marquee_items']);
            }
        }

        return array(
            'Free Shipping on All Orders',
            '4.9/5 Stars — 500+ Reviews',
            '1 Year Warranty on Every Order',
            'Custom Grips Available',
        );
    }

    /**
     * Items used on the homepage marquee.
     *
     * @return array<int,string>
     */
    public static function get_items() {
        $items = get_option(self::OPTION_KEY, null);

        if (!is_array($items) || empty($items)) {
            $items = self::default_items();
        }

        $items = array_values(array_filter(array_map('trim', $items)));

        return !empty($items) ? $items : self::default_items();
    }

    /**
     * Marketing admin settings page.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $items = self::get_items();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Scrolling Marquee', 'twintack-marketing'); ?></h1>
            <p class="description">
                <?php esc_html_e('Trust ticker below the hero on Marketing Homepage V2. Add, remove, or edit messages — they loop continuously on the frontend.', 'twintack-marketing'); ?>
            </p>

            <form method="post" action="options.php" class="tt-v2-admin-wrap">
                <?php settings_fields('twintack_marketing_marquee'); ?>

                <div class="tt-v2-admin-panel__body" style="max-width:720px;">
                    <div id="tt-v2-marquee-items" class="tt-v2-marquee-items" data-marquee-list>
                        <?php foreach ($items as $item) : ?>
                            <div class="tt-v2-marquee-item tt-v2-admin-card" data-marquee-item>
                                <input
                                    type="text"
                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[]"
                                    value="<?php echo esc_attr($item); ?>"
                                    class="large-text"
                                    placeholder="<?php esc_attr_e('Marquee message', 'twintack-marketing'); ?>"
                                >
                                <button type="button" class="button-link-delete tt-v2-marquee-remove" data-marquee-remove>
                                    <?php esc_html_e('Remove', 'twintack-marketing'); ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <p>
                        <button type="button" class="button" id="tt-v2-marquee-add">
                            <?php esc_html_e('Add message', 'twintack-marketing'); ?>
                        </button>
                    </p>

                    <?php submit_button(__('Save marquee', 'twintack-marketing')); ?>
                </div>
            </form>

            <template id="tt-v2-marquee-item-template">
                <div class="tt-v2-marquee-item tt-v2-admin-card" data-marquee-item>
                    <input
                        type="text"
                        name="<?php echo esc_attr(self::OPTION_KEY); ?>[]"
                        value=""
                        class="large-text"
                        placeholder="<?php esc_attr_e('Marquee message', 'twintack-marketing'); ?>"
                    >
                    <button type="button" class="button-link-delete tt-v2-marquee-remove" data-marquee-remove>
                        <?php esc_html_e('Remove', 'twintack-marketing'); ?>
                    </button>
                </div>
            </template>
        </div>
        <?php
    }
}
