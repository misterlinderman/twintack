<?php

namespace SolidAffiliate;

use SolidAffiliate\Addons\AffiliateLandingPages\Addon;
use SolidAffiliate\Addons\Core;
use SolidAffiliate\Controllers\AdminReportsController;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\SetupWizard;
use SolidAffiliate\Lib\Utils;
use SolidAffiliate\Lib\VisitTracking;

/**
 * Enqueues assets. Scripts and styles. 
 * Deals with localization of scripts, i.e. passing variables
 * from PHP to JavaScript.
 * 
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Assets
{
    const ASSET_VERSION = '13.1.0';
    /**
     * Registers hooks with WordPress.
     *
     * @return void
     */
    public static function register_hooks()
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_frontend_scripts'], 10, 3);
        // add_action('wp_enqueue_scripts', [self::class, 'enqueue_affiliate_portal_scripts'], 10, 3);

        add_action('admin_enqueue_scripts', [self::class, 'enqueue_every_admin_page_scripts']);

        if (is_admin()) {
            add_action(\SolidAffiliate\Addons\AffiliateLandingPages\Addon::BEFORE_META_BOX_ACTION, [self::class, 'enqueue_needed_for_select2_dropdown']);
            add_filter('admin_body_class', [self::class, 'filter_admin_body_class']);
        }

        if (is_admin() && self::should_enqueue_admin_scripts()) {
            add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_scripts']);
            add_action('admin_footer', [self::class, 'alpine_js']);
        }
    }

    /**
     * @return void
     */
    public static function alpine_js()
    {
        echo '<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.12.3/dist/cdn.min.js"></script>';
    }

    /**
     * Determines if Solid Affiliate should enqueue scripts for the current request.
     *
     * @return bool
     */
    public static function should_enqueue_admin_scripts()
    {
        global $pagenow;
        // Check if current page is Admin > Dashboard. Don't include.
        if ((string)$pagenow === 'index.php') {
            return false;
        }

        // Check if current page is WooCommerce Admin wc-admin page NOTE this will not include WC products page or product category pages etc.
        if (isset($_GET['page']) && strpos((string)$_GET['page'], 'wc-admin') !== false) {
            return true;
        }
        if (isset($_GET['page']) && strpos((string)$_GET['page'], 'wc-orders') !== false) {
            return true;
        }

        // WooCommerce Coupons + Product page
        // check if current post is a wc_coupon
        if (isset($_GET['post_type']) && $_GET['post_type'] === 'shop_coupon') {
            return true;
        }
        if (isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order') {
            return true;
        }
        if (isset($_GET['post'])) {
            /** @psalm-suppress MixedAssignment */
            $current_post = get_post((int)$_GET['post']);
            $post_types_to_match = ['product', 'shop_coupon', 'shop_order'];
            if ($current_post instanceof \WP_Post && in_array($current_post->post_type, $post_types_to_match)) {
                return true;
            }
        }

        // WordPress User Edit Page
        if ((string)$pagenow === 'user-edit.php') {
            return true;
        }

        // Solid Affiliate pages
        if (isset($_GET['page']) && strpos((string)$_GET['page'], 'solid-affiliate') !== false) {
            return true;
        }

        return false;
    }


    /**
     * @since 1.0.0
     *
     * @hook wp_enqueue_scripts
     *
     * @return void
     */
    public static function enqueue_frontend_scripts()
    {
        // Visits JS
        wp_enqueue_script('solid-affiliate-visits-js', plugin_dir_url(__FILE__) . '../assets/js/visits.js', [], self::ASSET_VERSION, false);
        $variables = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'affiliate_param' => (string)Settings::get(Settings::KEY_REFERRAL_VARIABLE),
            'visit_cookie_key' => VisitTracking::visit_cookie_key(),
            'visit_cookie_expiration_in_days' => (int)Settings::get(Settings::KEY_COOKIE_EXPIRATION_DAYS),
            'is_cookies_disabled' => (bool)Settings::get(Settings::KEY_IS_COOKIES_DISABLED),
            'landing_pages' => [
                'is_landing_pages_enabled' => Core::is_addon_enabled(\SolidAffiliate\Addons\AffiliateLandingPages\Addon::ADDON_SLUG),
                'is_home_page_a_landing_page' => \SolidAffiliate\Addons\AffiliateLandingPages\Addon::is_home_page_an_affiliate_landing_page(),
            ],
            'is_automatic_visit_tracking_disabled' => (bool)Settings::get(Settings::KEY_IS_AUTOMATIC_VISIT_TRACKING_DISABLED),
        );
        wp_localize_script('solid-affiliate-visits-js', "sld_affiliate_js_variables", $variables);

        wp_enqueue_script('solid-affiliate-shared-js', plugin_dir_url(__FILE__) . '../assets/js/solid-shared.js', ['jquery'], self::ASSET_VERSION, false);
        wp_localize_script('solid-affiliate-shared-js', "sld_affiliate_js_variables", $variables);

        wp_enqueue_style('solid-affiliate-shared-css', plugin_dir_url(__FILE__) . '../assets/css/shared.css', [], self::ASSET_VERSION);
    }

    /**
     * @since 1.0.0
     *
     * @hook admin_enqueue_scripts
     *
     * @return void
     */
    public static function enqueue_admin_scripts()
    {
        if (Utils::current_page_contains(AdminReportsController::ADMIN_PAGE_KEY) || (Utils::is_current_page_admin_affiliate_portal_preview())) {
            wp_enqueue_script('solid-affiliate-chart-js', plugin_dir_url(__FILE__) . '../assets/js/chart.js', [], self::ASSET_VERSION, false);
        }

        wp_enqueue_script('solid-affiliate-modal-js', plugin_dir_url(__FILE__) . '../assets/js/modal.js', [], self::ASSET_VERSION, false);
        wp_enqueue_style('solid-affiliate-modal-css', plugin_dir_url(__FILE__) . '../assets/css/modal.css', [], self::ASSET_VERSION);

        wp_enqueue_script('solid-affiliate-tooltips-js', plugin_dir_url(__FILE__) . '../assets/js/tooltips.js', [], self::ASSET_VERSION, false);
        wp_enqueue_style('solid-affiliate-tooltips-css', plugin_dir_url(__FILE__) . '../assets/css/tippy-tooltips.css', [], self::ASSET_VERSION);

        wp_enqueue_script('solid-affiliate-admin-js', plugin_dir_url(__FILE__) . '../assets/js/admin.js', ['jquery', 'solid-affiliate-modal-js'], self::ASSET_VERSION, false);

        // Enqueue postbox for core meta boxes 
        // Pages : Admin dashboard
        if (Utils::current_page_contains('solid-affiliate-admin-dashboard') || Utils::current_page_contains('solid-affiliate')) {
            wp_enqueue_script('postbox');
            wp_enqueue_style('solid-affiliate-admin-css', plugin_dir_url(__FILE__) . '../assets/css/admin.css', [], self::ASSET_VERSION);
            wp_enqueue_style('solid-affiliate-tooltips-css', plugin_dir_url(__FILE__) . '../assets/css/tippy-tooltips.css', [], self::ASSET_VERSION);
        }

        // TODO only enqueue this for the page we need
        add_thickbox();

        // Add the Select2 CSS file
        wp_enqueue_style('select2-css', plugin_dir_url(__FILE__) . '../assets/css/select2.min.css', [], '4.1.0-rc.0');

        //Add the Select2 JavaScript file
        wp_enqueue_script('select2-js', plugin_dir_url(__FILE__) . '../assets/js/select2.min.js', ['jquery'], '4.1.0-rc.0');

        //Add a JavaScript file to initialize the Select2 elements
        wp_localize_script('solid-affiliate-admin-js', "sld_affiliate_admin_js_variables", self::_admin_js_variables());

        wp_enqueue_style('solid-affiliate-shared-css', plugin_dir_url(__FILE__) . '../assets/css/shared.css', [], self::ASSET_VERSION);

        // Add the jquery formBuilder js and config
        add_action(\SolidAffiliate\Views\Admin\Settings\RootView::BEFORE_CUSTOMIZE_AFFILIATE_REGISTRATION_FORM_ACTION, [self::class, 'enqueue_form_builder_assets']);
    }

    /**
     * Enqueues any scripts and styles needed for the affiliate portal.
     * Attempts to check if the current page is the affiliate portal and enqueues the scripts and styles.
     * 
     * @return void
     */
    public static function enqueue_affiliate_portal_scripts()
    {
        wp_enqueue_script('solid-affiliate-chart-js', plugin_dir_url(__FILE__) . '../assets/js/chart.js', [], self::ASSET_VERSION, false);
        wp_enqueue_script('solid-affiliate-modal-js', plugin_dir_url(__FILE__) . '../assets/js/modal.js', [], self::ASSET_VERSION, false);
        wp_enqueue_style('solid-affiliate-modal-css', plugin_dir_url(__FILE__) . '../assets/css/modal.css', [], self::ASSET_VERSION);
        wp_enqueue_script('solid-affiliate-tooltips-js', plugin_dir_url(__FILE__) . '../assets/js/tooltips.js', [], self::ASSET_VERSION, false);
        wp_enqueue_style('solid-affiliate-tooltips-css', plugin_dir_url(__FILE__) . '../assets/css/tippy-tooltips.css', [], self::ASSET_VERSION);
        wp_enqueue_script('solid-affiliate-affiliate-portal-js', plugin_dir_url(__FILE__) . '../assets/js/affiliate-portal.js', ['jquery', 'solid-affiliate-modal-js'], self::ASSET_VERSION, false);
        self::enqueue_recaptcha_js_if_enabled();
    }

    /**
     * Fires on every admin page load.
     *
     * @hook admin_enqueue_scripts
     * @return void
     */
    public static function enqueue_every_admin_page_scripts()
    {
        wp_enqueue_style('solid-affiliate-ever-admin-page-css', plugin_dir_url(__FILE__) . '../assets/css/every-admin-page.css', [], self::ASSET_VERSION);
        if (SetupWizard::is_displayed()) {
            $style = '#toplevel_page_solid-affiliate-admin ul.wp-submenu li:not(:nth-child(1)):not(:nth-child(2)) { display:none !important; }';
            // inline this style
            wp_add_inline_style('solid-affiliate-ever-admin-page-css', $style);
        };
    }



    /**
     * Action is fired to enque the recaptcha js when the solid_affiliate/affiliate_registration_form/before_recaptcha action is called.
     *
     * @return void
     */
    public static function enqueue_recaptcha_js_if_enabled()
    {
        if (Settings::get(Settings::KEY_IS_RECAPTCHA_ENABLED_FOR_AFFILIATE_REGISTRATION)) {
            wp_enqueue_script('solid-affiliate-recaptcha-js', plugin_dir_url(__FILE__) . '../assets/js/solid-affiliate-recaptcha.js', ['jquery'], self::ASSET_VERSION, false);
        }
    }

    /**
     * Action is fired to enque the form-builder and form-builder-configuration js when the solid_affiliate/settings/customize_affiliate_registration_form_tab action is called.
     *
     * @return void
     */
    public static function enqueue_form_builder_assets()
    {
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('form-builder-js', plugin_dir_url(__FILE__) . '../assets/js/form-builder-expanded.js', [], self::ASSET_VERSION, false);
        wp_enqueue_script('solid-affiliate-form-builder-configuration-js', plugin_dir_url(__FILE__) . '../assets/js/form-builder-configuration.js', [], self::ASSET_VERSION, false);
        wp_enqueue_style('solid-affiliate-form-builder-css', plugin_dir_url(__FILE__) . '../assets/css/form-builder.css', [], self::ASSET_VERSION);
        $variables = array('ajaxurl' => admin_url('admin-ajax.php'));
        wp_localize_script('solid-affiliate-form-builder-configuration-js', "sld_affiliate_js_variables", $variables);
    }

    /**
     * Action is fired to enque the Select2 js and css for the Affiliate Select dropdown when the solid_affiliate/affiliate_landing_pages/before_edit_page_meta_box action is called.
     *
     * @return void
     */
    public static function enqueue_needed_for_select2_dropdown()
    {
        wp_enqueue_script('solid-affiliate-modal-js', plugin_dir_url(__FILE__) . '../assets/js/modal.js', [], self::ASSET_VERSION, false);
        wp_enqueue_style('solid-affiliate-modal-css', plugin_dir_url(__FILE__) . '../assets/css/modal.css', [], self::ASSET_VERSION);
        wp_enqueue_script('solid-affiliate-admin-js', plugin_dir_url(__FILE__) . '../assets/js/admin.js', ['jquery', 'solid-affiliate-modal-js'], self::ASSET_VERSION, false);
        wp_localize_script('solid-affiliate-admin-js', "sld_affiliate_admin_js_variables", self::_admin_js_variables());
        wp_enqueue_script('solid-affiliate-tooltips-js', plugin_dir_url(__FILE__) . '../assets/js/tooltips.js', [], self::ASSET_VERSION, false);
        wp_enqueue_style('solid-affiliate-tooltips-css', plugin_dir_url(__FILE__) . '../assets/css/tippy-tooltips.css', [], self::ASSET_VERSION);
        wp_enqueue_style('select2-css', plugin_dir_url(__FILE__) . '../assets/css/select2.min.css', [], '4.1.0-rc.0');
        //Add the Select2 JavaScript file
        wp_enqueue_script('select2-js', plugin_dir_url(__FILE__) . '../assets/js/select2.min.js', ['jquery'], '4.1.0-rc.0');
    }

    /**
     * This function adds classes to the body tag on the admin pages.
     * 
     * It works by appending the classes to the existing string of classes.
     * The classes are all seperated by a space.
     * 
     * @param string $classes
     * @return string
     */
    public static function filter_admin_body_class($classes)
    {
        // if POST request, return the classes
        if (!isset($_SERVER['REQUEST_METHOD']) || ($_SERVER['REQUEST_METHOD'] !== 'GET')) {
            return $classes;
        }

        // get the page parameter from the url
        $page = isset($_GET['page']) ? (string)$_GET['page'] : '';

        // if the page parameter is empty, return the classes
        if (empty($page)) {
            return $classes;
        }

        // if the page paramater does not begin with solid-affiliate, return the classes
        if (strpos($page, 'solid-affiliate') !== 0) {
            return $classes;
        }

        $action = isset($_GET['action']) ? (string)$_GET['action'] : '';
        $tab = isset($_GET['tab']) ? (string)$_GET['tab'] : '';

        // if either action or tab are not empty, append them to the page parameter
        $class_to_add = $page;
        if (!empty($action)) {
            $class_to_add = $class_to_add . '-' . $action;
        }

        if (!empty($tab)) {
            $class_to_add = $class_to_add . '-' . $tab;
        }

        $classes .= ' ' . 'solid-affiliate-admin-ui' . ' ' . $class_to_add;

        return $classes;
    }

    /**
     * The js varibles for admin.js needed for ajax calls and currency formatting.
     *
     * @return array<string, string>
     */
    private static function _admin_js_variables()
    {
        return array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'current_currency_code' => WooCommerceIntegration::get_current_currency()
        );
    }
}
