<?php

/**
 * Relevant WooCommerce actions: 
 * 
 * woocommerce_order_status_pending
 * woocommerce_order_status_failed
 * woocommerce_order_status_on-hold
 * woocommerce_order_status_processing
 * woocommerce_order_status_completed
 * woocommerce_order_status_refunded
 * woocommerce_order_status_cancelled
 */

namespace SolidAffiliate\Lib\Integrations;

////////////////////////////
// For HPOS compatibility. 
// See (https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book)
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
////////////////////////////


use SolidAffiliate\Addons\Core;
use SolidAffiliate\Addons\StoreCredit\Addon as StoreCreditAddon;
use SolidAffiliate\Controllers\AdminReportsController;
use SolidAffiliate\Lib\AdminReportsHelper;
use SolidAffiliate\Lib\CommissionCalculator;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\LifetimeCommissions;
use SolidAffiliate\Lib\Misc;
use SolidAffiliate\Lib\PurchaseTracking;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\SolidLogger;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VisitTracking;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Lib\VO\OrderDescription;
use SolidAffiliate\Lib\VO\OrderItemDescription;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Lib\VO\FormFieldArgs;
use SolidAffiliate\Lib\VO\ItemCommission;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateCustomerLink;
use SolidAffiliate\Views\Shared\SolidTooltipView;
use WC_Coupon;
use WC_Subscription;
use WC_Subscriptions;
use WC_Subscriptions_Order;
use WC_Subscriptions_Product;

class WooCommerceIntegration
{


    const SOURCE = 'woocommerce';

    /* Coupons */
    const COUPON_POST_TYPE = 'shop_coupon';
    const UNPERSISTED_COUPON_ID = 0;

    /** @var array<string, string> */
    const MISC = [
        'product_referral_rate_key' => '_solid_affiliate_woocommerce_product_referral_rate',
        'product_referral_rate_type_key' => '_solid_affiliate_woocommerce_product_referral_rate_type',
        'is_product_referral_disabled_key' => '_solid_affiliate_woocommerce_is_product_referrals_disabled',
        'product_category_referral_rate_key' => '_solid_affiliate_woocommerce_product_category_referral_rate',
        'product_category_referral_rate_type_key' => '_solid_affiliate_woocommerce_product_category_referral_rate_type',
        'is_product_category_referral_disabled_key' => '_solid_affiliate_woocommerce_is_product_category_referrals_disabled',
        'product_variation_referral_rate_key' => '_solid_affiliate_woocommerce_product_variation_referral_rate',
        'product_variation_referral_rate_type_key' => '_solid_affiliate_woocommerce_product_variation_referral_rate_type',
        'is_product_variation_referral_disabled_key' => '_solid_affiliate_woocommerce_is_product_variation_referrals_disabled',
        'coupon_affiliate_id_key' => '_solid_affiliate_woocommerce_coupon_affiliate_id',
        'is_coupon_referral_disabled_key' => '_solid_affiliate_woocommerce_is_coupon_referrals_disabled',
    ];

    const ORDER_STATUS_PROCESSING = 'processing';
    const ORDER_STATUS_COMPLETED = 'completed';
    const ORDER_STATUS_SHIPPED = 'shipped';

    const ORDER_STATUSES = [
        self::ORDER_STATUS_PROCESSING,
        self::ORDER_STATUS_COMPLETED,
        self::ORDER_STATUS_SHIPPED,
    ];

    const ORDER_STATUSES_ENUM_OPTIONS = [
        [self::ORDER_STATUS_PROCESSING, 'Processing'],
        [self::ORDER_STATUS_COMPLETED, 'Completed'],
        [self::ORDER_STATUS_SHIPPED, 'Shipped'],
    ];


    /**
     *
     * @return array{
     *   product_category: Schema<"_solid_affiliate_woocommerce_product_category_referral_rate"|"_solid_affiliate_woocommerce_product_category_referral_rate_type"|"_solid_affiliate_woocommerce_is_product_category_referrals_disabled">,
     *   product_variation: Schema<"_solid_affiliate_woocommerce_product_variation_referral_rate"|"_solid_affiliate_woocommerce_product_variation_referral_rate_type"|"_solid_affiliate_woocommerce_is_product_variation_referrals_disabled">,
     * }
     */
    public static function schemas()
    {
        return [
            'product_category' => new Schema(['entries' =>
            [
                self::MISC['product_category_referral_rate_type_key'] => new SchemaEntry(array(
                    'type' => 'varchar',
                    'length' => 255,
                    'required' => false,
                    'display_name' => __('Category Referral Rate Type', 'solid-affiliate'),
                    'is_enum' => true,
                    'enum_options' => [
                        ['site_default', __('Site Default', 'solid-affiliate')],
                        ['percentage', __('Percentage', 'solid-affiliate')],
                        ['flat', __('Flat', 'solid-affiliate')],
                    ],
                    'show_on_new_form' => true,
                    'show_on_edit_form' => true,
                    'show_list_table_column' => true,
                    'user_default' => 'site_default',
                    'form_input_description' => __('The Referral Rate Type for all products in this category. Set to Site Default to not have Product Category specific rates. Otherwise choose Flat or Percentage and then configure the corresponding value below. - Solid Affiliate', 'solid-affiliate')
                )),

                self::MISC['product_category_referral_rate_key'] => new SchemaEntry(array(
                    'type' => 'float',
                    'required' => false,
                    'display_name' => __('Category Referral Rate', 'solid-affiliate'),
                    'show_on_new_form' => true,
                    'show_on_edit_form' => true,
                    'show_list_table_column' => true,
                    'user_default' => null,
                    'form_input_description' => __('The Referral Rate for all products in this category. This will be ignored if Referral Rate Type is set to Site Default. It will be interpreted as % if Rate Type (above) is set to Percentage, and $ if Flat. - Solid Affiliate', 'solid-affiliate'),
                    'custom_form_input_attributes' => [
                        'min' => '0',
                        'step' => 'any'
                    ]
                )),
                self::MISC['is_product_category_referral_disabled_key'] => new SchemaEntry(array(
                    'type' => 'bool',
                    'required' => false,
                    'display_name' => __('Disable Referrals for Category', 'solid-affiliate'),
                    'show_on_new_form' => true,
                    'show_on_edit_form' => true,
                    'show_list_table_column' => true,
                    'user_default' => false,
                    'form_input_description' => __('This will disable Referrals from being generated for any products in this category. This option takes precedent over all the other Referral rate settings.', 'solid-affiliate'),
                )),
            ]]),

            'product_variation' => new Schema(['entries' =>
            [
                self::MISC['product_variation_referral_rate_type_key'] => new SchemaEntry(array(
                    'type' => 'varchar',
                    'length' => 255,
                    'required' => false,
                    'display_name' => __('Variation Referral Rate Type', 'solid-affiliate'),
                    'is_enum' => true,
                    'enum_options' => [
                        ['site_default', __('Site Default', 'solid-affiliate')],
                        ['percentage', __('Percentage', 'solid-affiliate')],
                        ['flat', __('Flat', 'solid-affiliate')],
                    ],
                    'show_on_new_form' => true,
                    'show_on_edit_form' => true,
                    'show_list_table_column' => true,
                    'user_default' => 'site_default',
                    'form_input_description' => __('The Referral Rate Type for all products in this variation. Set to Site Default to not have Product Variation specific rates. Otherwise choose Flat or Percentage and then configure the corresponding value below. - Solid Affiliate', 'solid-affiliate')
                )),

                self::MISC['product_variation_referral_rate_key'] => new SchemaEntry(array(
                    'type' => 'float',
                    'required' => false,
                    'display_name' => __('Variation Referral Rate', 'solid-affiliate'),
                    'show_on_new_form' => true,
                    'show_on_edit_form' => true,
                    'show_list_table_column' => true,
                    'user_default' => null,
                    'form_input_description' => __('The Referral Rate for all products in this variation. This will be ignored if Referral Rate Type is set to Site Default. It will be interpreted as % if Rate Type (above) is set to Percentage, and $ if Flat. - Solid Affiliate', 'solid-affiliate'),
                    'custom_form_input_attributes' => [
                        'min' => '0',
                        'step' => 'any'
                    ]
                )),

                self::MISC['is_product_variation_referral_disabled_key'] => new SchemaEntry(array(
                    'type' => 'bool',
                    'required' => false,
                    'display_name' => __('Disable Referrals for Variation', 'solid-affiliate'),
                    'show_on_new_form' => true,
                    'show_on_edit_form' => true,
                    'show_list_table_column' => true,
                    'user_default' => false,
                    'form_input_description' => __('This will disable Referrals from being generated for any products in this variation. This option takes precedent over all the other Referral rate settings.', 'solid-affiliate'),
                )),
            ]])

        ];
    }

    /**
     * @return void
     */
    public static function register_hooks()
    {
        add_filter('solid_affiliate/settings/get_all', [self::class, 'filter_get_all_settings'], 10, 1);

        // This action will trigger the creation of a Referral with a status of "draft".
        add_action('woocommerce_checkout_update_order_meta', ['SolidAffiliate\Lib\Integrations\WooCommerceIntegration', 'handle_woocommerce_order_created'], 10);

        // For when the order is created via the admin panel, manually.
        add_action('woocommerce_process_shop_order_meta', ['SolidAffiliate\Lib\Integrations\WooCommerceIntegration', 'handle_woocommerce_process_shop_order_meta'], 10);

        // add action for when the order is made via api
        add_action('woocommerce_store_api_checkout_update_order_meta', ['SolidAffiliate\Lib\Integrations\WooCommerceIntegration', 'handle_woocommerce_order_created_via_api'], 10);

        // These actions will trigger the updating of a Referral from status "draft" -> "unpaid".
        add_action('woocommerce_order_status_processing', [self::class, 'handle_woocommerce_order_processing'], 10);
        add_action('woocommerce_order_status_completed', [self::class, 'handle_woocommerce_order_completed'], 10);
        add_action('woocommerce_order_status_shipped', [self::class, 'handle_woocommerce_order_shipped'], 10);

        // Refunds
        // TODO look into woocommerce_order_fully_refunded and woocommerce_order_partially_refunded. What happens 
        // to an underlying referral when the order is partially refunded?
        add_action('woocommerce_order_status_completed_to_refunded', [self::class, 'handle_woocommerce_order_refunded'], 10);
        add_action('woocommerce_order_status_on-hold_to_refunded', [self::class, 'handle_woocommerce_order_refunded'], 10);
        add_action('woocommerce_order_status_processing_to_refunded', [self::class, 'handle_woocommerce_order_refunded'], 10);

        // Cancellations
        add_action('woocommerce_order_status_completed_to_cancelled', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('woocommerce_order_status_on-hold_to_cancelled', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('woocommerce_order_status_processing_to_cancelled', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('woocommerce_order_status_pending_to_cancelled', [self::class, 'handle_woocommerce_order_cancelled'], 10);

        // Failures
        add_action('woocommerce_order_status_completed_to_failed', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('woocommerce_order_status_on-hold_to_failed', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('woocommerce_order_status_processing_to_failed', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('woocommerce_order_status_pending_to_failed', [self::class, 'handle_woocommerce_order_cancelled'], 10);

        // Trash (some people will do this instead of cancel)
        add_action('wc-completed_to_trash', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('wc-on-hold_to_trash', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('wc-processing_to_trash', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('wc-pending_to_trash', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('woocommerce_delete_order', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('woocommerce_trash_order', [self::class, 'handle_woocommerce_order_cancelled'], 10);

        add_action('before_delete_post', [self::class, 'handle_woocommerce_delete_checkout_draft_order'], 10, 1);

        /**
         * Add a custom product data tab
         */
        add_filter('woocommerce_product_data_tabs', [self::class, 'product_data_tabs']);
        add_action('woocommerce_product_data_panels', [self::class, 'product_data_panels']);
        add_action('save_post_product', [self::class, 'handle_save_post_product']);

        /**
         * Add custom product category fields
         */
        add_action('product_cat_add_form_fields', [self::class, 'add_product_category_rate'], 10, 2);
        add_action('product_cat_edit_form_fields', [self::class, 'edit_product_category_rate'], 10);
        add_action('edited_product_cat', [self::class, 'save_product_category_rate']);
        add_action('create_product_cat', [self::class, 'save_product_category_rate']);

        /**
         * Add custom product variation fields
         */
        add_action('woocommerce_product_after_variable_attributes', [self::class, 'add_product_variation_rate'], 10, 3);
        add_action('woocommerce_save_product_variation', [self::class, 'save_product_variation_rate'], 10, 2);


        /**
         * Add a custom Coupon data tab
         */
        add_filter('woocommerce_coupon_data_tabs', [self::class, 'coupon_data_tabs']);
        add_action('woocommerce_coupon_data_panels', [self::class, 'coupon_data_panels']);
        add_action('save_post_shop_coupon', [self::class, 'handle_save_post_shop_coupon']);
        add_action('manage_shop_coupon_posts_custom_column', [self::class, 'manage_shop_coupon_posts_custom_column'], 1000, 2);

        /**
         * Coupon filters for WooCommerce > Coupons table
         */
        add_filter('views_edit-shop_coupon', [self::class, 'add_affiliate_coupons_filter']);
        add_action('pre_get_posts', [self::class, 'filter_affiliate_coupons']);

        /** 
         * Adds thing to the WooCommerce Orders list table
         */
        add_action('manage_shop_order_posts_custom_column', [self::class, 'manage_shop_order_posts_custom_column'], 1000, 2);
        add_action('woocommerce_shop_order_list_table_custom_column', [self::class, 'manage_shop_order_posts_custom_column'], 1000, 2);

        /**
         * WooCommerce Dashboard meta box (on main WordPress dashboard)) 
         */
        add_action('woocommerce_after_dashboard_status_widget', [self::class, 'after_dashboard_status_widget'], 1);


        /**
         * Meta Box on Admin Order page
         */
        add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);

        /**
         * Auto-Apply Affiliate Coupons
         */
        add_action('woocommerce_add_to_cart', [self::class, 'auto_apply_affiliate_coupon_on_add_to_cart']);
        add_action('woocommerce_before_cart', [self::class, 'display_auto_applied_coupon_notice'], 5);
        add_action('woocommerce_before_checkout_form', [self::class, 'display_auto_applied_coupon_notice'], 5);
    }

    public static function auto_apply_affiliate_coupon_on_add_to_cart(): void
    {
        if (!Settings::get(Settings::KEY_IS_AUTO_APPLY_AFFILIATE_COUPON_ENABLED)) {
            return;
        }

        // The logic is very simple. Most of the code here is just for safety.
        // Get the affiliate for the current visitor, apply their coupon.
        $maybe_affiliate = Misc::get_affiliate_who_referred_the_current_visitor();
        if ($maybe_affiliate instanceof Affiliate) {
            $maybe_wc_coupon = Affiliate::maybe_default_coupon($maybe_affiliate);
            if ($maybe_wc_coupon instanceof WC_Coupon) {
                $coupon_code = $maybe_wc_coupon->get_code();
                if ($coupon_code) {
                    if (!WC()->cart->has_discount($coupon_code)) {
                        WC()->cart->apply_coupon($coupon_code);
                        WC()->session->set('solid_affiliate_auto_applied_coupon_notice', 'An affiliate coupon has been automatically applied to your cart!');
                    }
                }
            }
        }
    }

    public static function display_auto_applied_coupon_notice(): void
    {
        $notice = Validators::str(WC()->session->get('auto_applied_coupon_notice'));
        if (!empty($notice)) {
            wc_add_notice($notice, 'success');
            WC()->session->__unset('solid_affiliate_auto_applied_coupon_notice'); // Remove notice after displaying
        }
    }

    /**
     * @param mixed $_loop
     * @param array $_variation_data
     * @param \WP_Post $variation
     * @return void
     */
    public static function add_product_variation_rate($_loop, $_variation_data, $variation)
    {
        $variation_id = (int)$variation->ID;
        $product_id = $variation->post_parent;
        $product = wc_get_product($product_id);
        // ensure the product is a \WC_Product 
        if ($product instanceof \WC_Product_Variable) {
            $variation_rate_key = self::MISC['product_variation_referral_rate_key'];
            $variation_rate = (string)get_post_meta($variation_id, $variation_rate_key, true);

            $variation_rate_type_key = self::MISC['product_variation_referral_rate_type_key'];
            $variation_rate_type = (string)get_post_meta($variation_id, $variation_rate_type_key, true);

            $is_disabled_key = self::MISC['is_product_variation_referral_disabled_key'];
            $is_diabled = (bool)get_post_meta($variation_id, $is_disabled_key, true);

            $item = (object)[
                $variation_rate_key => $variation_rate,
                $variation_rate_type_key => $variation_rate_type,
                $is_disabled_key => $is_diabled
            ];


            $schema = self::schemas()['product_variation'];
            $form = FormBuilder::build_form($schema, 'edit', $item);
            echo ('<hr>');
            echo ('<strong> Solid Affiliate </strong>');
            echo ($form);
        }
    }

    /**
     * @param int $variation_id
     * @param int $i
     * 
     * @return void
     */
    public static function save_product_variation_rate($variation_id, $i)
    {
        // Ensure that $variation_id is a real variation
        if (get_post_type($variation_id) !== 'product_variation') {
            return;
        }

        $variation_rate_key = self::MISC['product_variation_referral_rate_key'];
        $variation_rate_type_key = self::MISC['product_variation_referral_rate_type_key'];
        $is_disabled_key = self::MISC['is_product_variation_referral_disabled_key'];

        $variation_rate = (string)($_POST[$variation_rate_key] ?? '');
        $variation_rate_type = (string)($_POST[$variation_rate_type_key] ?? '');
        $is_disabled = (bool)($_POST[$is_disabled_key] ?? false);

        update_post_meta($variation_id, $variation_rate_key, $variation_rate);
        update_post_meta($variation_id, $variation_rate_type_key, $variation_rate_type);
        update_post_meta($variation_id, $is_disabled_key, $is_disabled);
    }


    /**
     * Callback to add customization to the WooCommerce > Coupons table.
     * 
     * @param string $column
     * @param int $post_id
     * @return void
     */
    public static function manage_shop_coupon_posts_custom_column($column, $post_id)
    {
        if ($column === 'coupon_code') {
            // check if this coupon has an affiliate associated to it
            $affiliate_id = (int)get_post_meta((int)$post_id, self::MISC['coupon_affiliate_id_key'], true);
            if ($affiliate_id && Affiliate::find($affiliate_id)) {
                $svg = '<svg style="vertical-align: sub;" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M5.5 7.5C5.5 5.15279 7.40279 3.25 9.75 3.25C12.0972 3.25 14 5.15279 14 7.5C14 9.84721 12.0972 11.75 9.75 11.75C7.40279 11.75 5.5 9.84721 5.5 7.5ZM9.75 4.75C8.23122 4.75 7 5.98122 7 7.5C7 9.01878 8.23122 10.25 9.75 10.25C11.2688 10.25 12.5 9.01878 12.5 7.5C12.5 5.98122 11.2688 4.75 9.75 4.75Z" fill="#ACACAC"/>
                <path fill-rule="evenodd" clip-rule="evenodd" d="M2 17C2 14.9289 3.67893 13.25 5.75 13.25H6.09087C6.27536 13.25 6.45869 13.2792 6.63407 13.3364L7.49959 13.6191C8.96187 14.0965 10.5381 14.0965 12.0004 13.6191L12.8659 13.3364C13.0413 13.2792 13.2246 13.25 13.4091 13.25H13.75C15.8211 13.25 17.5 14.9289 17.5 17V18.1883C17.5 18.9415 16.9541 19.5837 16.2107 19.7051C11.9319 20.4037 7.5681 20.4037 3.28927 19.7051C2.54588 19.5837 2 18.9415 2 18.1883V17ZM5.75 14.75C4.50736 14.75 3.5 15.7574 3.5 17V18.1883C3.5 18.2064 3.51311 18.2218 3.53097 18.2247C7.64972 18.8972 11.8503 18.8972 15.969 18.2247C15.9869 18.2218 16 18.2064 16 18.1883V17C16 15.7574 14.9926 14.75 13.75 14.75H13.4091C13.3828 14.75 13.3566 14.7542 13.3315 14.7623L12.466 15.045C10.7012 15.6212 8.79881 15.6212 7.03398 15.045L6.16847 14.7623C6.14342 14.7542 6.11722 14.75 6.09087 14.75H5.75Z" fill="#ACACAC"/>
                <path fill-rule="evenodd" clip-rule="evenodd" d="M21.7241 5.11953C22.2104 5.38137 22.3923 5.98783 22.1305 6.4741L19.485 11.3872C18.7406 12.7696 16.7665 12.7945 15.9875 11.4313L14.8818 9.49614C14.6077 9.01662 14.7743 8.40577 15.2539 8.13176C15.7334 7.85775 16.3442 8.02434 16.6182 8.50386L17.724 10.439L20.3695 5.5259C20.6314 5.03963 21.2378 4.85769 21.7241 5.11953Z" fill="#ACACAC"/>
                </svg>';

                echo SolidTooltipView::render(__('This coupon is associated with an affiliate.', 'solid-affiliate'), $svg);
            }

            // check if the coupon has referrals disabled
            $is_disabled = (bool)get_post_meta((int)$post_id, self::MISC['is_coupon_referral_disabled_key'], true);
            if ($is_disabled) {
                $svg = '<svg style="vertical-align: sub;" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M10 3.25C7.65279 3.25 5.75 5.15279 5.75 7.5C5.75 9.84721 7.65279 11.75 10 11.75C12.3472 11.75 14.25 9.84721 14.25 7.5C14.25 5.15279 12.3472 3.25 10 3.25ZM7.25 7.5C7.25 5.98122 8.48122 4.75 10 4.75C11.5188 4.75 12.75 5.98122 12.75 7.5C12.75 9.01878 11.5188 10.25 10 10.25C8.48122 10.25 7.25 9.01878 7.25 7.5Z" fill="#ACACAC"/>
                <path d="M3.75 17C3.75 15.7574 4.75736 14.75 6 14.75H6.34087C6.36722 14.75 6.39342 14.7542 6.41847 14.7623L7.28398 15.045C8.28046 15.3703 9.32078 15.512 10.3554 15.4699C10.4934 15.4643 10.6063 15.3614 10.6354 15.2264C10.7089 14.8864 10.8113 14.5571 10.9397 14.2412C11.0003 14.0921 10.8773 13.9257 10.7171 13.9416C9.72187 14.0405 8.71109 13.933 7.74959 13.6191L6.88407 13.3364C6.70869 13.2792 6.52536 13.25 6.34087 13.25H6C3.92893 13.25 2.25 14.9289 2.25 17V18.1883C2.25 18.9415 2.79588 19.5837 3.53927 19.7051C6.1345 20.1288 8.76099 20.2955 11.3808 20.2053C11.5433 20.1997 11.6313 20.0111 11.5397 19.8768C11.3458 19.5925 11.1757 19.2906 11.0322 18.9741C10.9632 18.8217 10.8123 18.7209 10.645 18.7237C8.35042 18.762 6.05324 18.5957 3.78097 18.2247C3.76311 18.2218 3.75 18.2064 3.75 18.1883V17Z" fill="#ACACAC"/>
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 16.5C12 17.4719 12.3081 18.3718 12.8319 19.1074C13.1238 19.5173 13.4827 19.8762 13.8926 20.1681C14.6282 20.6919 15.5281 21 16.5 21C18.9853 21 21 18.9853 21 16.5C21 15.5281 20.6919 14.6282 20.1681 13.8926C19.8762 13.4827 19.5173 13.1238 19.1074 12.8319C18.3718 12.3081 17.4719 12 16.5 12C14.0147 12 12 14.0147 12 16.5ZM16.5 19.5C15.9436 19.5 15.4227 19.3486 14.976 19.0846L19.0846 14.976C19.3486 15.4227 19.5 15.9436 19.5 16.5C19.5 18.1569 18.1569 19.5 16.5 19.5ZM13.9154 18.024L18.024 13.9154C17.5773 13.6514 17.0564 13.5 16.5 13.5C14.8431 13.5 13.5 14.8431 13.5 16.5C13.5 17.0564 13.6514 17.5773 13.9154 18.024Z" fill="#ACACAC"/>
                </svg>';

                echo SolidTooltipView::render(__('This coupon has referrals entirely disabled.', 'solid-affiliate'), $svg);
            }

            if (Core::is_addon_enabled(StoreCreditAddon::ADDON_SLUG)) {
                $is_store_credit = StoreCreditAddon::is_coupon_store_credit(new WC_Coupon($post_id));
                if ($is_store_credit) {
                    $svg = '<svg style="vertical-align: sub;" width="15" height="18" viewBox="0 0 15 18" fill="#ccc" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12.6 3.6H10.8C10.8 1.611 9.189 0 7.2 0C5.211 0 3.6 1.611 3.6 3.6H1.8C0.81 3.6 0 4.41 0 5.4V16.2C0 17.19 0.81 18 1.8 18H12.6C13.59 18 14.4 17.19 14.4 16.2V5.4C14.4 4.41 13.59 3.6 12.6 3.6ZM5.4 7.2C5.4 7.695 4.995 8.1 4.5 8.1C4.005 8.1 3.6 7.695 3.6 7.2V5.4H5.4V7.2ZM7.2 1.8C8.19 1.8 9 2.61 9 3.6H5.4C5.4 2.61 6.21 1.8 7.2 1.8ZM10.8 7.2C10.8 7.695 10.395 8.1 9.9 8.1C9.405 8.1 9 7.695 9 7.2V5.4H10.8V7.2Z"/>
                    </svg>
                    ';

                    echo SolidTooltipView::render(__('This is a store credit coupon powered by Solid Affiliate. It was automatically created, we strongly recommend not altering or deleting this coupon.', 'solid-affiliate'), $svg);
                }
            }
        }
    }

    /**
     * Callback to add customization to the WooCommerce > Orders table.
     * 
     * @param string $column
     * @param int|\WC_Order $post_id_or_order_object
     * @return void
     */
    public static function manage_shop_order_posts_custom_column($column, $post_id_or_order_object)
    {
        $order_id = ($post_id_or_order_object instanceof \WC_Order) ? $post_id_or_order_object->get_id() : $post_id_or_order_object;

        if ($column === 'order_number') {
            // Check if a Referral exists for this Order
            $maybe_referral = Referral::find_where(['order_id' => $order_id, 'status' => ['operator' => 'IN', 'value' => Referral::STATUSES_PAID_AND_UNPAID]]);
            if ($maybe_referral) {
                $svg = '<svg style="vertical-align: sub;" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M5.5 7.5C5.5 5.15279 7.40279 3.25 9.75 3.25C12.0972 3.25 14 5.15279 14 7.5C14 9.84721 12.0972 11.75 9.75 11.75C7.40279 11.75 5.5 9.84721 5.5 7.5ZM9.75 4.75C8.23122 4.75 7 5.98122 7 7.5C7 9.01878 8.23122 10.25 9.75 10.25C11.2688 10.25 12.5 9.01878 12.5 7.5C12.5 5.98122 11.2688 4.75 9.75 4.75Z" fill="#ACACAC"/>
                <path fill-rule="evenodd" clip-rule="evenodd" d="M2 17C2 14.9289 3.67893 13.25 5.75 13.25H6.09087C6.27536 13.25 6.45869 13.2792 6.63407 13.3364L7.49959 13.6191C8.96187 14.0965 10.5381 14.0965 12.0004 13.6191L12.8659 13.3364C13.0413 13.2792 13.2246 13.25 13.4091 13.25H13.75C15.8211 13.25 17.5 14.9289 17.5 17V18.1883C17.5 18.9415 16.9541 19.5837 16.2107 19.7051C11.9319 20.4037 7.5681 20.4037 3.28927 19.7051C2.54588 19.5837 2 18.9415 2 18.1883V17ZM5.75 14.75C4.50736 14.75 3.5 15.7574 3.5 17V18.1883C3.5 18.2064 3.51311 18.2218 3.53097 18.2247C7.64972 18.8972 11.8503 18.8972 15.969 18.2247C15.9869 18.2218 16 18.2064 16 18.1883V17C16 15.7574 14.9926 14.75 13.75 14.75H13.4091C13.3828 14.75 13.3566 14.7542 13.3315 14.7623L12.466 15.045C10.7012 15.6212 8.79881 15.6212 7.03398 15.045L6.16847 14.7623C6.14342 14.7542 6.11722 14.75 6.09087 14.75H5.75Z" fill="#ACACAC"/>
                <path fill-rule="evenodd" clip-rule="evenodd" d="M21.7241 5.11953C22.2104 5.38137 22.3923 5.98783 22.1305 6.4741L19.485 11.3872C18.7406 12.7696 16.7665 12.7945 15.9875 11.4313L14.8818 9.49614C14.6077 9.01662 14.7743 8.40577 15.2539 8.13176C15.7334 7.85775 16.3442 8.02434 16.6182 8.50386L17.724 10.439L20.3695 5.5259C20.6314 5.03963 21.2378 4.85769 21.7241 5.11953Z" fill="#ACACAC"/>
                </svg>';

                echo '<span style="float: right;">' . SolidTooltipView::render(__('This order was referred by an affiliate.', 'solid-affiliate'), $svg) . '</span>';
            }
        }
    }

    /**
     * Determines if a WooCommerce order is a 'checkout-draft' order.
     * 
     * https://developer.woocommerce.com/2020/11/23/introducing-a-new-order-status-checkout-draft/
     *
     * @param int $order_id
     * @return boolean
     */
    public static function is_checkout_draft_order($order_id)
    {
        $order = wc_get_order($order_id);
        if ($order instanceof \WC_Order) {
            return $order->get_status() === 'checkout-draft';
        }
        return false;
    }

    /**
     * Handles a WooCommerce order creation event.
     * 
     * @since 1.0.0
     * 
     * @param int $woocommerce_order_id This is the description of the parameter.
     * 
     * @return false|int Whether or not we ended up creating a Referral for this order.
     */
    public static function handle_woocommerce_order_created($woocommerce_order_id)
    {
        SolidLogger::log("Handling WooCommerce order created event for order $woocommerce_order_id");

        $maybe_already_existing_referrals = Referral::where(['order_source' => self::SOURCE, 'order_id' => $woocommerce_order_id]);
        if (!empty($maybe_already_existing_referrals)) {
            SolidLogger::log("Exiting early because there are already existing referrals for order $woocommerce_order_id");
            return false;
        }

        // TODO refactor this + subscriptionIntegration to use a gateway instead of the co-routine pattern I have now.
        //   If you make that gateway filterable, you could even use a Scheme to determine if the state of the request applies. 
        $order_description = self::order_description_for($woocommerce_order_id);

        // log the order_description
        SolidLogger::log("Order description for order $woocommerce_order_id: " . json_encode($order_description));


        // Need to ignore Renewal type orders so they don't get double counted by
        // the WooCommerceSubscriptionsIntegration.
        if ($order_description->is_renewal_order || $order_description->is_switch_order) {
            SolidLogger::log("Exiting early because order $woocommerce_order_id is a renewal or switch order");
            return false; // Just bail.
        }

        // TODO should check for switch orders as well

        $eitherReferralId = PurchaseTracking::potentially_reward_a_referral_for($order_description);
        if ($eitherReferralId->isRight) {
            SolidLogger::log("Successfully created a referral for order $woocommerce_order_id - referral id: {$eitherReferralId->right}");
            Referral::add_order_completed_note($eitherReferralId->right);
        } else {
            $error_strings = implode(', ', $eitherReferralId->left);
            SolidLogger::log("Failed to create a referral for order $woocommerce_order_id - errors: {$error_strings}");
            self::add_order_note_no_referral_created($order_description->order_id, $eitherReferralId->left);
        }

        return $eitherReferralId->right;
    }

    /**
     * Handles a WooCommerce order creation event.
     * 
     * @since 1.0.0
     * 
     * @param int $woocommerce_order_id This is the description of the parameter.
     * 
     * @return false|int Whether or not we ended up creating a Referral for this order.
     */
    public static function handle_woocommerce_process_shop_order_meta($woocommerce_order_id)
    {
        // IF the current user is an admin, we don't want cookie tracking to work. It causes issues
        // with simply updating an order resulting in a referral being created because the admin
        // happens to have an affiliate cookie in their browser.
        if (current_user_can('edit_shop_orders')) {
            add_filter('solid_affiliate/visit_tracking/get_cookied_visit_id', function () {
                return false;
            });
        }

        // and then just continue as usual
        return self::handle_woocommerce_order_created($woocommerce_order_id);
    }

    /**
     * Undocumented function
     *
     * @param \WC_Order $woocommerce_order
     * 
     * @return false|int Whether or not we ended up creating a Referral for this order.
     */
    public static function handle_woocommerce_order_created_via_api($woocommerce_order)
    {
        // check if it is an instance of WC_Order
        if (!($woocommerce_order instanceof \WC_Order)) {
            return false;
        } else {
            return self::handle_woocommerce_order_created($woocommerce_order->get_id());
        }
    }


    /**
     * @psalm-suppress UndefinedClass
     * @psalm-suppress MixedMethodCall
     * @return void
     */
    public static function add_meta_boxes()
    {
        // WooCommerce Admin > Order > Edit Order


        $callback =
            /**
             * @param \WP_Post $post
             * @return void
             */
            function ($post) {
                // check if it is a WooCommerce order
                /** @psalm-suppress DocblockTypeContradiction */
                if ($post instanceof \WC_Order) {
                    $order_id = $post->get_id();
                } else {
                    $order_id = $post->ID;
                }

                $maybe_referrals = Referral::where([
                    'order_id' => $order_id,
                ]);

                echo "<h4>Admin Helper - Order #{$order_id}</h4>";

                if (empty($maybe_referrals)) {
                    echo "<p>No Referrals found for this order.</p>";
                } else {
                    echo "<p>Referral for this order:</p>";
                    foreach ($maybe_referrals as $referral) {
                        echo "<div class='solid-affiliate_meta-box_order-note'>";
                        echo Formatters::status_with_tooltip($referral->status, Referral::class, 'admin');
                        echo '<br>';
                        echo Referral::order_note_body($referral->id);
                        echo "</div>";
                    }
                }

                do_action("solid_affiliate/woocommerce/admin_shop_order_meta_box/after", $order_id);
            };


        //TODO fix psalm error (update WooCommerce stubs maybe ?)
        $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') && wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        $icon = "<img class='solid-affiliate_meta-box_icon' src='https://solidaffiliate.com/brand/logo-icon.svg' alt=''>";
        add_meta_box('solid-affiliate_meta-box_shop-order', "<span class='solid-affiliate_meta-box-title'>" . $icon . "Solid Affiliate</span>", $callback, $screen, 'side', 'high');
    }

    /**
     * Handles the action woocommerce_after_dashboard_status_widget 
     *
     * @param \WC_Admin_Report $reports
     * @return void
     * 
     * @psalm-suppress UnusedVariable
     */
    public static function after_dashboard_status_widget($reports)
    {
        $range = new PresetDateRangeParams(['preset_date_range' => 'this_month']);

        /** @psalm-suppress UnusedVariable */
        $referrals_data = AdminReportsHelper::referrals_data($range->computed_start_date(), $range->computed_end_date(), [0]);
        /** @psalm-suppress UnusedVariable */
        $total_revenue_from_affiliates = (string)$referrals_data['Referral Amount'];
        $total_commission = (string)$referrals_data['Commission Amount'];
        $total_net_revenue = (string)$referrals_data['Net Revenue Amount'];
        $on_click_url = URLs::admin_path(AdminReportsController::ADMIN_PAGE_KEY);

        ob_start();
?>
        <style>
            #woocommerce_dashboard_status .wc_status_list li.solid-affiliate_sales-this-month {
                width: 100%;
            }

            #woocommerce_dashboard_status .wc_status_list li.solid-affiliate_sales-this-month a::before {
                display: none;
            }

            .solid-affiliate-icon_sales-this-month {
                float: left;
                width: 30px;
                margin: 9px;
                margin-top: 14px;
            }

            .solid-affiliate_sales-this-month .solid-affiliate_commission {
                font-size: 14px;
                color: #ffba00;
            }

            .solid-affiliate_sales-this-month .solid-affiliate_net-revenue {
                font-size: 14px;
                color: #79d039;
            }

            .solid-affiliate_sales-this-month .solid-affiliate_math {
                font-size: 14px;
                color: #ddd;
            }
        </style>
        <li class="solid-affiliate_sales-this-month">
            <img class="solid-affiliate-icon_sales-this-month" src="https://solidaffiliate.com/brand/logo-icon.svg" alt="">
            <a href="<?php echo ($on_click_url) ?>">
                <span class="wc_sparkline lines tips" data-color="#777" data-tip="" data-barwidth="" data-sparkline="" style="padding: 0px;"><canvas class="flot-base" style="direction: ltr; position: absolute; left: 0px; top: 0px; width: 48px; height: 24px;" width="96" height="48"></canvas><canvas class="flot-overlay" style="direction: ltr; position: absolute; left: 0px; top: 0px; width: 48px; height: 24px;" width="96" height="48"></canvas></span>
                <strong>
                    <span class="woocommerce-Price-amount amount">
                        <bdi>
                            <?php echo ($total_revenue_from_affiliates) ?>
                            <span class="solid-affiliate_math">
                                -
                            </span>

                            <span class="solid-affiliate_commission">
                                <?php echo ($total_commission) ?> commission
                            </span>
                            <span class="solid-affiliate_math">
                                =
                            </span>

                            <span class="solid-affiliate_net-revenue">
                                <?php echo ($total_net_revenue) ?> net revenue
                            </span>
                        </bdi>
                    </span>
                </strong> <?php _e('total revenue from', 'solid-affiliate') ?> <span style="font-weight: bold">Solid Affiliate</span> <?php _e('this month', 'solid-affiliate') ?> </a>
        </li>

    <?php
        echo ob_get_clean();
    }

    /**
     * Handles the processing status of a WooCommerce order.
     * 
     * Long description of the thing.
     * 
     * @since 1.0.0
     * 
     * @param int $woocommerce_order_id This is the description of the parameter.
     * 
     * @return boolean Whether or not we ended up creating a Referral for this order.
     */
    public static function handle_woocommerce_order_processing($woocommerce_order_id)
    {
        // check if the 'processing' status is in the settings array
        $statuses_to_trigger = Validators::arr_of_woocommerce_order_status(Settings::get(Settings::KEY_WOOCOMMERCE_ORDER_STATUSES_WHICH_SHOULD_TRIGGER_A_REFERRAL));
        if (in_array(self::ORDER_STATUS_PROCESSING, $statuses_to_trigger)) {
            return self::set_referrals_status_from_draft_or_rejected_to_unpaid($woocommerce_order_id);
        } else {
            return false;
        }
    }

    /**
     * Handles the processing status of a WooCommerce order.
     * 
     * Long description of the thing.
     * 
     * @since 1.0.0
     * 
     * @param int $woocommerce_order_id This is the description of the parameter.
     * 
     * @return boolean Whether or not we ended up creating a Referral for this order.
     */
    public static function set_referrals_status_from_draft_or_rejected_to_unpaid($woocommerce_order_id)
    {
        $maybe_referrals = Referral::where(
            [
                'order_source' => self::SOURCE,
                'order_id' => $woocommerce_order_id,
                'status' => ['operator' => 'IN', 'value' => [Referral::STATUS_DRAFT, Referral::STATUS_REJECTED]]
            ]
        );
        Referral::updateInstances($maybe_referrals, ['status' => Referral::STATUS_UNPAID]);

        do_action("solid_affiliate/woocommerce/set_referrals_status_from_draft_or_rejected_to_unpaid", $maybe_referrals);

        return true;
    }

    /**
     * Handles the completed status of a WooCommerce order.
     * 
     * Long description of the thing.
     * 
     * @since 1.0.0
     * 
     * @param int $woocommerce_order_id This is the description of the parameter.
     * 
     * @return boolean Whether or not we ended up creating a Referral for this order.
     */
    public static function handle_woocommerce_order_completed($woocommerce_order_id)
    {
        // check if the 'completed' status is in the settings array
        $statuses_to_trigger = Validators::arr_of_woocommerce_order_status(Settings::get(Settings::KEY_WOOCOMMERCE_ORDER_STATUSES_WHICH_SHOULD_TRIGGER_A_REFERRAL));
        if (in_array(self::ORDER_STATUS_COMPLETED, $statuses_to_trigger)) {
            return self::set_referrals_status_from_draft_or_rejected_to_unpaid($woocommerce_order_id);
        } else {
            return false;
        }
    }

    /**
     * Handles the shipped status of a WooCommerce order.
     * 
     * Long description of the thing.
     * 
     * @since 1.0.0
     * 
     * @param int $woocommerce_order_id This is the description of the parameter.
     * 
     * @return boolean Whether or not we ended up creating a Referral for this order.
     */
    public static function handle_woocommerce_order_shipped($woocommerce_order_id)
    {
        // check if the 'shipped' status is in the settings array
        $statuses_to_trigger = Validators::arr_of_woocommerce_order_status(Settings::get(Settings::KEY_WOOCOMMERCE_ORDER_STATUSES_WHICH_SHOULD_TRIGGER_A_REFERRAL));
        if (in_array(self::ORDER_STATUS_SHIPPED, $statuses_to_trigger)) {
            return self::set_referrals_status_from_draft_or_rejected_to_unpaid($woocommerce_order_id);
        } else {
            return false;
        }
    }

    /**
     * Undocumented public static function
     *
     * @param int $woocommerce_order_id
     * @return void
     */
    public static function handle_woocommerce_order_on_hold($woocommerce_order_id)
    {
        if (self::is_payment_method_cod($woocommerce_order_id)) {
            self::handle_woocommerce_order_completed($woocommerce_order_id);
        }
    }

    /**
     * Undocumented public static function
     *
     * @param int $woocommerce_order_id
     * @return bool
     */
    public static function handle_woocommerce_order_refunded($woocommerce_order_id)
    {
        /**  
         * Sometimes WooCommerce will pass a WP_Post object instead of an ID.
         * 
         * @psalm-suppress DocblockTypeContradiction 
         **/
        if ($woocommerce_order_id instanceof \WP_Post) {
            $woocommerce_order_id = $woocommerce_order_id->ID;
        }

        // $maybe_referral = Referral::find_where(['order_source' => self::SOURCE, 'order_id' => $woocommerce_order_id]);
        $referrals = Referral::where(['order_source' => self::SOURCE, 'order_id' => $woocommerce_order_id]);

        if (empty($referrals)) {
            return false;
        }

        foreach ($referrals as $referral) {
            Referral::updateInstance(
                $referral,
                ['order_refunded_at' => (string)current_time('mysql', true)]
            );

            Referral::reject_unless_already_paid($referral);
        }
        return true;
    }

    /**
     * @param int $woocommerce_order_id
     * @return bool
     */
    public static function handle_woocommerce_order_cancelled($woocommerce_order_id)
    {
        /**  
         * Sometimes WooCommerce will pass a WP_Post object instead of an ID.
         * 
         * @psalm-suppress DocblockTypeContradiction 
         **/
        if ($woocommerce_order_id instanceof \WP_Post) {
            $woocommerce_order_id = $woocommerce_order_id->ID;
        }

        $maybe_referral = Referral::find_where(['order_source' => self::SOURCE, 'order_id' => $woocommerce_order_id]);
        if (is_null($maybe_referral)) {
            return false;
        } else {
            return Referral::reject_unless_already_paid($maybe_referral);
        }
    }

    /**
     * @param int $post_id
     * @return void
     */
    public static function handle_woocommerce_delete_checkout_draft_order($post_id)
    {
        if (self::is_checkout_draft_order($post_id)) {
            $maybe_referrals = Referral::where(['order_id' => $post_id]);
            foreach ($maybe_referrals as $referral) {
                $response = Referral::delete($referral->id);
            }
        }
    }


    /**
     * Given a WooCommerce order ID, returns the description of the order.
     * Includes all the necessary information for Solid Affiliate to work, and nothing more.
     * 
     * @param int $woocommerce_order_id
     * 
     * @return OrderDescription 
     */
    public static function order_description_for($woocommerce_order_id)
    {
        $order_amount = self::order_amount_for($woocommerce_order_id);
        $total_shipping_amount = self::total_shipping_amount_for($woocommerce_order_id);
        $total_tax_amount = self::total_tax_amount_for($woocommerce_order_id);
        list($coupon_id, $maybe_affiliate_id_from_coupon) = self::get_maybe_affiliate_id_from_coupon($woocommerce_order_id);
        $maybe_affiliate_id_from_affiliate_customer_link = self::get_maybe_affiliate_id_from_affiliate_customer_link($woocommerce_order_id);
        $parent_order_id = self::get_parent_order_id_for($woocommerce_order_id);
        $customer_id = self::get_user_id_for($woocommerce_order_id);
        $currency = self::get_currency_for($woocommerce_order_id);
        $is_renewal_order = WooCommerceSubscriptionsIntegration::get_is_renewal_order_for($woocommerce_order_id);
        $is_switch_order = WooCommerceSubscriptionsIntegration::get_is_switch_order_for($woocommerce_order_id);


        $order_description = new OrderDescription([
            'order_amount' => $order_amount,
            'order_source' => self::SOURCE,
            'order_id' => $woocommerce_order_id,
            'parent_order_id' => $parent_order_id,
            'total_shipping' => $total_shipping_amount,
            'maybe_affiliate_id_from_coupon' => $maybe_affiliate_id_from_coupon,
            'maybe_affiliate_id_from_affiliate_customer_link' => $maybe_affiliate_id_from_affiliate_customer_link,
            'coupon_id' => $coupon_id,
            'is_renewal_order' => $is_renewal_order,
            'is_switch_order' => $is_switch_order,
            'customer_id' => $customer_id,
            'total_tax' => $total_tax_amount,
            'currency' => $currency
        ]);

        return $order_description;
    }

    /**
     * Retrieve the affiliate ID + coupon used (if an affiliate associated coupon was used) for a particular WC Order.
     * It will loop through all the coupons and check if any of them are associated with an affiliate, returns first one found.
     * 
     * @param int $woocommerce_order_id
     *
     * @return array{0: int, 1: int|null}
     */
    public static function get_maybe_affiliate_id_from_coupon($woocommerce_order_id)
    {
        $maybe_wc_order = \wc_get_order($woocommerce_order_id);
        if ($maybe_wc_order instanceof \WC_Order) {
            $order = $maybe_wc_order;

            if (version_compare(WC()->version, '3.7.0', '>=')) {
                $coupons = $order->get_coupon_codes();
            } else {
                $coupons = $order->get_coupon_codes();
                // $coupons = $order->get_used_coupons(); this was the original one but it was throwing psalm errors
            }

            if (empty($coupons)) {
                return [0, null];
            }

            /** @psalm-suppress MixedAssignment */
            foreach ($coupons as $code) {
                $coupon = new \WC_Coupon($code);

                if (true === version_compare(WC()->version, '3.0.0', '>=')) {
                    $coupon_id = $coupon->get_id();
                } else {
                    $coupon_id = (int)$coupon->id;
                }

                $affiliate_id = (int)get_post_meta($coupon_id, self::MISC['coupon_affiliate_id_key'], true);

                if ($affiliate_id && Affiliate::find($affiliate_id)) {
                    return [$coupon_id, (int)$affiliate_id];
                }
            }
        } else {
            return [0, null];
        }

        return [0, null];
    }

    /**
     * @param int $woocommerce_order_id
     * @return int|null
     */
    public static function get_maybe_affiliate_id_from_affiliate_customer_link($woocommerce_order_id)
    {
        $customer_id = self::get_user_id_for($woocommerce_order_id);
        // if customer id is not empty - This is to fix a bug where the customer id is 0 and every guest purchase is linked to the same affiliate 
        if (!empty($customer_id)) {
            $maybe_link_by_customer_id = AffiliateCustomerLink::find_where(['customer_id' => $customer_id]);
            if ($maybe_link_by_customer_id) {
                // check for expiration
                if ($maybe_link_by_customer_id->expires_on_unix_seconds == 0) {
                    return $maybe_link_by_customer_id->affiliate_id;
                }
                if (time() > $maybe_link_by_customer_id->expires_on_unix_seconds) {
                    AffiliateCustomerLink::delete($maybe_link_by_customer_id->id);
                    return null;
                } else {
                    return $maybe_link_by_customer_id->affiliate_id;
                }
            }
        }

        $customer_email = self::get_email_for($woocommerce_order_id);
        // TODO need to check for blank email. for some reason guest email is not available in the order object at the time of purchase
        // Or, just rethink and make all this better because this is insane.
        $maybe_link_by_customer_email = AffiliateCustomerLink::find_where(['customer_email' => $customer_email]);
        if ($maybe_link_by_customer_email) {
            // check for expiration
            if ($maybe_link_by_customer_email->expires_on_unix_seconds == 0) {
                return $maybe_link_by_customer_email->affiliate_id;
            }
            if (time() > $maybe_link_by_customer_email->expires_on_unix_seconds) {
                AffiliateCustomerLink::delete($maybe_link_by_customer_email->id);
                return null;
            } else {
                return $maybe_link_by_customer_email->affiliate_id;
            }
        }

        return null;
    }

    /**
     * Retrieves all the coupons that were used for a particular WC Order.
     *
     * @param int $woocommerce_order_id
     * @return int[]
     */
    public static function get_all_coupon_ids_for_order_id($woocommerce_order_id)
    {
        $maybe_wc_order = \wc_get_order($woocommerce_order_id);
        if ($maybe_wc_order instanceof \WC_Order) {
            $order = $maybe_wc_order;

            if (version_compare(WC()->version, '3.7.0', '>=')) {
                $coupons = $order->get_coupon_codes();
            } else {
                $coupons = $order->get_coupon_codes();
                // $coupons = $order->get_used_coupons(); this was the original one but it was throwing psalm errors
            }

            if (empty($coupons)) {
                return [];
            }

            $coupon_ids = [];
            /** @psalm-suppress MixedAssignment */
            foreach ($coupons as $code) {
                $coupon = new \WC_Coupon($code);

                if (true === version_compare(WC()->version, '3.0.0', '>=')) {
                    $coupon_id = $coupon->get_id();
                } else {
                    $coupon_id = (int)$coupon->id;
                }

                $coupon_ids[] = $coupon_id;
            }

            return $coupon_ids;
        } else {
            return [];
        }
    }

    /**
     * @param int $woocommerce_order_id
     * @return boolean
     */
    public static function is_payment_method_cod($woocommerce_order_id)
    {
        return (self::payment_method_for($woocommerce_order_id) === 'cod');
    }

    /**
     * @param int $woocommerce_order_id
     * @return float
     */
    public static function order_amount_for($woocommerce_order_id)
    {
        $maybe_wc_order = \wc_get_order($woocommerce_order_id);
        if ($maybe_wc_order instanceof \WC_Order) {
            $order = $maybe_wc_order;

            $total = (float)$order->get_total();

            return $total;
        } else {
            return 0.0;
        }
    }

    /**
     * @param int $woocommerce_order_id
     * @return float
     */
    public static function total_shipping_amount_for($woocommerce_order_id)
    {
        $maybe_wc_order = \wc_get_order($woocommerce_order_id);
        if ($maybe_wc_order instanceof \WC_Order) {
            $order = $maybe_wc_order;

            $total = (float) $order->get_shipping_total();

            if (!Settings::get(Settings::KEY_IS_EXCLUDE_TAX)) {
                $total += (float)$order->get_shipping_tax();
            }

            return $total;
        } else {
            return 0.0;
        }
    }

    /**
     * @param int $woocommerce_order_id
     * @return float
     */
    public static function total_tax_amount_for($woocommerce_order_id)
    {
        $maybe_wc_order = \wc_get_order($woocommerce_order_id);
        if ($maybe_wc_order instanceof \WC_Order) {
            $order = $maybe_wc_order;

            $total = (float) $order->get_total_tax();

            return $total;
        } else {
            return 0.0;
        }
    }

    /**
     * @param int $woocommerce_order_id
     * @return string
     */
    public static function payment_method_for($woocommerce_order_id)
    {
        if (true === version_compare(WC()->version, '3.0.0', '>=')) {
            $maybe_wc_order = \wc_get_order($woocommerce_order_id);
            if ($maybe_wc_order instanceof \WC_Order) {
                $payment_method = $maybe_wc_order->get_payment_method();
            } else {
                return 'TODO';
            }
        } else {
            $payment_method = (string) get_post_meta($woocommerce_order_id, '_payment_method', true);
        }

        return $payment_method;
    }

    /**
     * @param int $woocommerce_order_id
     * @return int
     */
    public static function get_parent_order_id_for($woocommerce_order_id)
    {
        if (true === version_compare(WC()->version, '3.0.0', '>=')) {
            $maybe_wc_order = \wc_get_order($woocommerce_order_id);
            if ($maybe_wc_order instanceof \WC_Order) {
                return (int)$maybe_wc_order->get_parent_id();
            } else {
                return 0;
            }
        } else {
            /** TODO */
            return 0;
        }
    }

    /**
     * @param int $woocommerce_order_id
     * @return int
     */
    public static function get_user_id_for($woocommerce_order_id)
    {
        if (true === version_compare(WC()->version, '3.0.0', '>=')) {
            $maybe_wc_order = \wc_get_order($woocommerce_order_id);
            if ($maybe_wc_order instanceof \WC_Order) {
                return (int)$maybe_wc_order->get_user_id();
            } else {
                return 0;
            }
        } else {
            /** TODO */
            return 0;
        }
    }

    /**
     * @param int $woocommerce_order_id
     * @return string
     */
    public static function get_email_for($woocommerce_order_id)
    {
        if (true === version_compare(WC()->version, '3.0.0', '>=')) {
            $maybe_wc_order = \wc_get_order($woocommerce_order_id);
            if ($maybe_wc_order instanceof \WC_Order) {
                return (string)$maybe_wc_order->get_billing_email();
            } else {
                return '';
            }
        } else {
            /** TODO */
            return '';
        }
    }

    /**
     * @param int $woocommerce_order_id
     * @return string
     */
    public static function get_currency_for($woocommerce_order_id)
    {
        if (true === version_compare(WC()->version, '3.0.0', '>=')) {
            $maybe_wc_order = \wc_get_order($woocommerce_order_id);
            if ($maybe_wc_order instanceof \WC_Order) {
                return (string)$maybe_wc_order->get_currency();
            } else {
                return '';
            }
        } else {
            /** TODO */
            return '';
        }
    }

    /**
     * Gets WooCommerce Admin URL for an Order ID.
     *
     * @param int $order_id
     * @return string
     */
    public static function get_admin_order_url($order_id)
    {
        if (!function_exists('wc_get_order')) {
            $url = URLs::index(Referral::class) . '&error=' . urlencode(__('Order not found. It might have been deleted in the past.', 'solid-affiliate'));
            return $url;
        }

        $order = \wc_get_order($order_id);
        if ($order instanceof \WC_Order) {
            return $order->get_edit_order_url();
        } else {
            $url = URLs::index(Referral::class) . '&error=' . urlencode(__('Order not found. It might have been deleted in the past.', 'solid-affiliate'));
            return $url;
        }
    }

    /**
     * Adds an Order note.
     *
     * @param int $order_id
     * @param string $note
     * 
     * @return Either<int> Comment ID
     */
    public static function add_order_note($order_id, $note)
    {
        // LUCA ask how to best handle checking for this everywhere
        // is_active_plugin -> problem with this, it's kind of a hit or miss
        if (!function_exists('wc_get_order')) {
            return new Either([__("WooCommerce not found.", 'solid-affiliate')], 0, false);
        }

        $order = \wc_get_order($order_id);
        if ($order instanceof \WC_Order) {
            $comment_id = $order->add_order_note($note, 0, false);
            return new Either([''], $comment_id, true);
        } else {
            return new Either([sprintf(__('Failed to add order note to WooCommerce order %1$s', 'solid-affiliate'), $order_id)], 0, false);
        }
    }

    /**
     * Adds an Order note when a Referral is not created.
     *
     * @param int $order_id
     * @param string[] $fail_messages
     * 
     * @return Either<int> Comment ID
     */
    public static function add_order_note_no_referral_created($order_id, $fail_messages)
    {

        $fail_messages_li_elements = array_map(function ($message) {
            return '<li>' . $message . '</li>';
        }, $fail_messages);

        // the same as above but without the last element
        $fail_messages_li_elements_without_last = array_slice($fail_messages_li_elements, 0, -1);
        $fail_messages_html = implode('', $fail_messages_li_elements_without_last);

        $last_fail_message = end($fail_messages);
        $last_fail_message_element = '<div class="order_note-pill-1">
                <span>Conclusion</span>
                <p>' . $last_fail_message . '</p>
            </div>';

        $note = '
        <div class="solid-affiliate_woocommerce_order_note">
            <img src="https://solidaffiliate.com/brand/logo@2x.png" height="24" width="auto" alt="Solid Affiliate logo">
            <hr>
            <p style="margin-top:10px"><strong>No referral was created for this order.</strong> These notes were returned: </p>
            <ul>' .
            $fail_messages_html
            . '</ul>' .
            $last_fail_message_element .
            '</div>
    ';

        // $note = '<strong>Solid Affiliate</strong> </br> ';
        // $note .= __('Referral not created. These notes were returned:', 'solid-affiliate') . ' </br>';
        // $note .= implode('</br>', $fail_messages);


        return self::add_order_note($order_id, $note);
    }




    /**
     * @param array $tabs
     * @return array
     */
    public static function product_data_tabs($tabs)
    {

        $tabs['sld_affiliate'] = array(
            'label'     => 'Solid Affiliate',
            'target'     => 'sld_woocommerce_product_affiliate_settings_panel',
            'priority' => 100,
            'class'     => []
        );


        return $tabs;
    }

    /**
     * Echo's out WooCommerce Product Data Panel
     *
     * @return void
     */
    public static function product_data_panels()
    {
        ob_start();
    ?>
        <div id='sld_woocommerce_product_affiliate_settings_panel' class='panel woocommerce_options_panel'>
            <p><?php _e('Manage product specific Affiliate referral rates.', 'solid-affiliate') ?></p>
            <?php
            woocommerce_wp_select(array(
                'id'          => self::MISC['product_referral_rate_type_key'],
                'label'       => __('Referral Rate Type', 'solid-affiliate'),
                'options'     => [
                    'site_default' => __('Site Default (no product specific rate)', 'solid-affiliate'),
                    'percentage' => __('Percentage (%)', 'solid-affiliate'),
                    'flat' => __('Flat', 'solid-affiliate'),
                ],
                'desc_tip'    => true,
                'description' => __('Set to Site Default to not have product specific rates. Otherwise choose Flat or Percentage and then configure the corresponding value below.', 'solid-affiliate')
            ));
            woocommerce_wp_text_input(array(
                'id'          => self::MISC['product_referral_rate_key'],
                'label'       => __('Referral Rate', 'solid-affiliate'),
                'desc_tip'    => true,
                'description' => __('This will be ignored if Referral Rate Type is set to Site Default. It will be interpreted as % if Rate Type (above) is set to Percentage, and $ if Flat.', 'solid-affiliate'),
                'type' => 'number',
                'custom_attributes' => ['min' => 0, 'step' => 'any']
            ));

            woocommerce_wp_checkbox(array(
                'id'          => self::MISC['is_product_referral_disabled_key'],
                'label'       => __('Disable Referrals', 'solid-affiliate'),
                'desc_tip'    => false,
                'description' => __('<em>This will disable Referrals from being generated for this product. This option takes precedent over all the other Referral rate settings.</em>', 'solid-affiliate')
            ));

            wp_nonce_field('solid_affiliate_woocommerce_product_nonce', 'solid_affiliate_woocommerce_product_nonce');
            ?>
        </div>
    <?php
        echo ob_get_clean();
    }

    /**
     * Saves per-product referral rate settings input fields
     *
     * @param int $post_id
     * 
     * @return int
     */
    public static function handle_save_post_product($post_id = 0)
    {
        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        // Don't save revisions and autosaves
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return $post_id;
        }

        if (empty($_POST['solid_affiliate_woocommerce_product_nonce']) || !wp_verify_nonce((string)$_POST['solid_affiliate_woocommerce_product_nonce'], 'solid_affiliate_woocommerce_product_nonce')) {
            return $post_id;
        }

        $post = get_post($post_id);

        if (!($post instanceof \WP_Post)) {
            return $post_id;
        }

        // Check post type is product
        if ('product' != $post->post_type) {
            return $post_id;
        }

        // Check user permission
        if (!current_user_can('edit_post', $post_id)) {
            return $post_id;
        }

        $rate_key = self::MISC['product_referral_rate_key'];
        if (!empty($_POST[$rate_key])) {

            $rate = sanitize_text_field((string)$_POST[$rate_key]);
            update_post_meta($post_id, $rate_key, $rate);
        } else {

            delete_post_meta($post_id, $rate_key);
        }

        $rate_type_key = self::MISC['product_referral_rate_type_key'];
        if (!empty($_POST[$rate_type_key])) {

            $rate_type = sanitize_text_field((string)$_POST[$rate_type_key]);
            update_post_meta($post_id, $rate_type_key, $rate_type);
        } else {

            delete_post_meta($post_id, $rate_type_key);
        }

        $is_product_referral_disabled_key = self::MISC["is_product_referral_disabled_key"];
        if (!empty($_POST[$is_product_referral_disabled_key])) {

            $is_disabled = sanitize_text_field((string)$_POST[$is_product_referral_disabled_key]);
            update_post_meta($post_id, $is_product_referral_disabled_key, $is_disabled);
        } else {

            delete_post_meta($post_id, $is_product_referral_disabled_key);
        }


        return $post_id;
    }


    // COUPON support
    ////////////////////////////////////////////////////////////////////////////////
    /**
     * @param array $tabs
     * @return array
     */
    public static function coupon_data_tabs($tabs)
    {
        $tabs['sld_affiliate'] = array(
            'label'     => 'Solid Affiliate',
            'target'     => 'sld_woocommerce_coupon_affiliate_settings_panel',
            'priority' => 100,
            'class'     => []
        );

        return $tabs;
    }

    /**
     * Echo's out WooCommerce Product Data Panel
     *
     * @return void
     */
    public static function coupon_data_panels()
    {
        ob_start();
    ?>
        <style>
            #sld_woocommerce_coupon_affiliate_settings_panel label[for="affiliate-select"] {
                float: none;
                width: 150px;
                margin: 0px;
                display: block;
                padding: 5px 0px 0px 25px;
            }

            #sld_woocommerce_coupon_affiliate_settings_panel .sld_field-title {
                padding-left: 25px;
            }
        </style>
        <div id='sld_woocommerce_coupon_affiliate_settings_panel' class='panel woocommerce_options_panel'>
            <div class="options-group">

                <p><strong><?php _e('Link this Coupon to an Affiliate here.', 'solid-affiliate'); ?></strong> <?php _e('Any time this coupon is redeemed, followed by a successful purchase, the linked Affiliate will be awarded a Referral.', 'solid-affiliate') ?></p>
                <?php
                $coupon_id = (int)get_the_ID();
                $affiliate_id = (int)get_post_meta($coupon_id, self::MISC['coupon_affiliate_id_key'], true);

                ?>
                <div class='form-field'>
                    <?php

                    echo FormBuilder::build_affiliate_select_field(new FormFieldArgs([
                        'label_for_value' => 'affiliate-select',
                        'label' =>  __('Affiliate ID', 'solid-affiliate'),
                        'field_name' =>  self::MISC['coupon_affiliate_id_key'],
                        'field_id' =>  self::MISC['coupon_affiliate_id_key'],
                        'field_type' =>  'affiliate_select',
                        'value' => $affiliate_id,
                        'placeholder' =>  __('Select an Affiliate', 'solid-affiliate'),
                        'required' =>  false,
                        'description' =>  '',
                        'select_options' => [],
                        'custom_attributes' => [],
                        'wrapper_class' => '',
                        'label_class' => '',
                        'description_class' => '',
                        'hide_title' => false,
                        'hide_description' => false,
                        'shows_placeholder' => true,
                        'tooltip_content' => '',
                        'tooltip_class' => '',
                        'tooltip_body' => ''
                    ]));

                    echo ('</br>');
                    echo ('<hr>');
                    echo ('<p><strong>Disable Referrals entirely for this Coupon.</strong></p>');

                    woocommerce_wp_checkbox(array(
                        'id'          => self::MISC['is_coupon_referral_disabled_key'],
                        'label'       => __('Disable Referrals', 'solid-affiliate'),
                        'desc_tip'    => false,
                        'description' => __('<em>This will disable any and all Referrals from being generated when this coupon is used as part of an Order.</em>', 'solid-affiliate')
                    ));
                    ?>
                </div>
                <?php

                wp_nonce_field('solid_affiliate_woocommerce_coupon_nonce', 'solid_affiliate_woocommerce_coupon_nonce');
                ?>
            </div>
        </div>
    <?php
        echo ob_get_clean();
    }

    /**
     * Saves per-product referral rate settings input fields
     *
     * @param int $post_id
     * 
     * @return int
     */
    public static function handle_save_post_shop_coupon($post_id = 0)
    {

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        // Don't save revisions and autosaves
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return $post_id;
        }

        if (empty($_POST['solid_affiliate_woocommerce_coupon_nonce']) || !wp_verify_nonce((string)$_POST['solid_affiliate_woocommerce_coupon_nonce'], 'solid_affiliate_woocommerce_coupon_nonce')) {
            return $post_id;
        }

        $post = get_post($post_id);

        if (!($post instanceof \WP_Post)) {
            return $post_id;
        }

        // Check post type is product
        if (self::COUPON_POST_TYPE != $post->post_type) {
            return $post_id;
        }

        // Check user permission
        if (!current_user_can('edit_post', $post_id)) {
            return $post_id;
        }

        $key = self::MISC['coupon_affiliate_id_key'];
        if (!empty($_POST[$key])) {
            $affiliate_id = (int)sanitize_text_field((string)$_POST[$key]);
            if (!Affiliate::find($affiliate_id)) {
                // TODO report an error/notice to the user that this Affiliate doesn't exist.
                return $post_id;
            }
            update_post_meta($post_id, $key, $affiliate_id);
        } else {
            delete_post_meta($post_id, $key);
        }

        $is_coupon_referral_disabled_key = self::MISC["is_coupon_referral_disabled_key"];
        if (!empty($_POST[$is_coupon_referral_disabled_key])) {

            $is_disabled = sanitize_text_field((string)$_POST[$is_coupon_referral_disabled_key]);
            update_post_meta($post_id, $is_coupon_referral_disabled_key, $is_disabled);
        } else {

            delete_post_meta($post_id, $is_coupon_referral_disabled_key);
        }

        return $post_id;
    }

    /**
     * Retrieves all affiliate coupons from the WooCommerce store.
     *
     * This method queries the WooCommerce store for coupon posts, filtering by
     * the presence of a non-empty coupon affiliate ID. It returns an array
     * of coupon posts, each containing the associated metadata.
     * 
     * @param int $paged The page number to retrieve.
     * @param int $per_page The number of coupons to retrieve per page.
     *
     * @return \WP_Post[]|int[] An array of WP_Post objects representing affiliate coupons.
     */
    public static function get_all_affiliate_coupons($paged = 1, $per_page = 100)
    {
        $args = array(
            'post_type' => WooCommerceIntegration::COUPON_POST_TYPE,
            'posts_per_page' => 100,
            'paged' => $per_page,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => WooCommerceIntegration::MISC['coupon_affiliate_id_key'],
                    'value' => '',
                    'compare' => '!='
                ],
            ]
        );
        $query = new \WP_Query($args);
        $coupon_posts = $query->get_posts();

        return $coupon_posts;
    }

    /**
     * Undocumented function
     *
     * @return int
     */
    public static function count_affiliate_coupons()
    {
        $args = array(
            'post_type' => WooCommerceIntegration::COUPON_POST_TYPE,
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => WooCommerceIntegration::MISC['coupon_affiliate_id_key'],
                    'value' => '',
                    'compare' => '!='
                ],
            ],
            'fields' => 'ids',
        );
        $query = new \WP_Query($args);
        $count = $query->post_count;

        return $count;
    }

    /**
     * @param array $views
     * 
     * @return array
     */
    public static function add_affiliate_coupons_filter($views)
    {
        $affiliate_coupons_count = self::count_affiliate_coupons();
        $class = (isset($_GET['affiliate_coupons']) && $_GET['affiliate_coupons'] == '1') ? 'current' : '';
        $views['affiliate_coupons'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
            add_query_arg(array('affiliate_coupons' => '1'), admin_url('edit.php?post_type=shop_coupon')),
            $class,
            __('Affiliate Coupons', 'solid-affiliate'),
            $affiliate_coupons_count
        );

        return $views;
    }

    /**
     * Filters the WooCommerce Coupons list to display only affiliate coupons when the "Affiliate Coupons" filter is selected.
     *
     * @param \WP_Query $query The WordPress Query object.
     *
     * @return void
     */
    public static function filter_affiliate_coupons($query)
    {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') != 'shop_coupon') {
            return;
        }

        if (isset($_GET['affiliate_coupons']) && $_GET['affiliate_coupons'] == '1') {
            $query->set('meta_query', [
                'relation' => 'OR',
                [
                    'key' => WooCommerceIntegration::MISC['coupon_affiliate_id_key'],
                    'value' => '',
                    'compare' => '!='
                ],
            ]);
        }
    }


    ////////////////////////////////////////////////////////////////////////////////

    /**
     * Calculates Commission for ...
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * @param OrderItemDescription $order_item_description
     * 
     * @return Either<ItemCommission> The Commission
     */
    public static function calculate_product_variation_specific_commission($affiliate, $order_item_description)
    {
        // TODO
        $variation_id = $order_item_description->variation_id;

        if (empty($variation_id)) {
            return CommissionCalculator::not_applicable_either(__('Variation ID not found.', 'solid-affiliate'));
        }

        // ensure this variation exists and is a valid \WC_Product_Variation
        $product_variation = \wc_get_product($variation_id);
        if (!$product_variation instanceof \WC_Product_Variation) {
            return CommissionCalculator::not_applicable_either(__('Product Variation not found.', 'solid-affiliate'));
        } else {
            $rate_type_key = self::MISC['product_variation_referral_rate_type_key'];
            /** @var 'site_default'|'percentage'|'flat'|'' */
            $referral_rate_type = (string)get_post_meta($variation_id, $rate_type_key, true);

            $rate_key = self::MISC['product_variation_referral_rate_key'];
            $referral_rate = (float)get_post_meta($variation_id, $rate_key, true);

            switch ($referral_rate_type) {
                case 'site_default':
                    return CommissionCalculator::not_applicable_either();
                case 'percentage':
                    $commission = ($referral_rate / 100.0) * $order_item_description->amount;
                    $i = new ItemCommission(
                        [
                            'purchase_amount' => $order_item_description->amount,
                            'commissionable_amount' => $order_item_description->commissionable_amount,
                            'commission_amount' => $commission,
                            'commission_strategy' => CommissionCalculator::COMMISSION_STRATEGY_PRODUCT_VARIATION_SPECIFIC_RATE,
                            'commission_strategy_rate_type' => 'percentage',
                            'commission_strategy_rate' => $referral_rate,
                            'product_id' => $order_item_description->product_id,
                            'variation_id' => $order_item_description->variation_id,
                            'quantity' => $order_item_description->quantity
                        ]
                    );
                    return new Either([''], $i, true);
                case 'flat':
                    $commission = $referral_rate * $order_item_description->quantity;
                    $i = new ItemCommission(
                        [
                            'purchase_amount' => $order_item_description->amount,
                            'commissionable_amount' => $order_item_description->commissionable_amount,
                            'commission_amount' => $commission,
                            'commission_strategy' => CommissionCalculator::COMMISSION_STRATEGY_PRODUCT_VARIATION_SPECIFIC_RATE,
                            'commission_strategy_rate_type' => 'flat',
                            'commission_strategy_rate' => $referral_rate,
                            'product_id' => $order_item_description->product_id,
                            'variation_id' => $order_item_description->variation_id,
                            'quantity' => $order_item_description->quantity
                        ]
                    );
                    return new Either([''], $i, true);
                case '':
                    return CommissionCalculator::not_applicable_either();
            }
        }
    }

    /**
     * Calculates Commission for ...
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * @param OrderItemDescription $order_item_description
     * 
     * @return Either<ItemCommission> The Commission
     */
    public static function calculate_product_specific_commission($affiliate, $order_item_description)
    {
        $product_id = $order_item_description->product_id;

        $rate_type_key = self::MISC['product_referral_rate_type_key'];
        /** @var 'site_default'|'percentage'|'flat'|'' */
        $referral_rate_type = (string)get_post_meta($product_id, $rate_type_key, true);

        $rate_key = self::MISC['product_referral_rate_key'];
        $referral_rate = (float)get_post_meta($product_id, $rate_key, true);

        switch ($referral_rate_type) {
            case 'site_default':
                return CommissionCalculator::not_applicable_either();
            case 'percentage':
                $commission = ($referral_rate / 100.0) * $order_item_description->amount;
                $i = new ItemCommission(
                    [
                        'purchase_amount' => $order_item_description->amount,
                        'commissionable_amount' => $order_item_description->commissionable_amount,
                        'commission_amount' => $commission,
                        'commission_strategy' => CommissionCalculator::COMMISSION_STRATEGY_PRODUCT_SPECIFIC_RATE,
                        'commission_strategy_rate_type' => 'percentage',
                        'commission_strategy_rate' => $referral_rate,
                        'product_id' => $order_item_description->product_id,
                        'variation_id' => $order_item_description->variation_id,
                        'quantity' => $order_item_description->quantity
                    ]
                );
                return new Either([''], $i, true);
            case 'flat':
                $commission = $referral_rate * $order_item_description->quantity;
                $i = new ItemCommission(
                    [
                        'purchase_amount' => $order_item_description->amount,
                        'commissionable_amount' => $order_item_description->commissionable_amount,
                        'commission_amount' => $commission,
                        'commission_strategy' => CommissionCalculator::COMMISSION_STRATEGY_PRODUCT_SPECIFIC_RATE,
                        'commission_strategy_rate_type' => 'flat',
                        'commission_strategy_rate' => $referral_rate,
                        'product_id' => $order_item_description->product_id,
                        'variation_id' => $order_item_description->variation_id,
                        'quantity' => $order_item_description->quantity
                    ]
                );
                return new Either([''], $i, true);
            case '':
                return CommissionCalculator::not_applicable_either();
        }
    }

    /**
     * Calculates Commission for ...
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * @param OrderItemDescription $order_item_description
     * 
     * @return Either<ItemCommission> The Commission
     */
    public static function calculate_product_category_specific_commission($affiliate, $order_item_description)
    {
        $category_ids = \wc_get_product_cat_ids($order_item_description->product_id);

        if (empty($category_ids)) {
            return CommissionCalculator::not_applicable_either(__('Not applicable, Product does not belong to any Categories.', 'solid-affiliate'));
        }


        /** @var int $category_id */
        foreach ($category_ids as $category_id) {
            $category_id = (int)$category_id;

            $category_rate_key = self::MISC['product_category_referral_rate_key'];
            $referral_rate = (float)get_term_meta($category_id, $category_rate_key, true);

            $category_rate_type_key = self::MISC['product_category_referral_rate_type_key'];
            $referral_rate_type = (string)get_term_meta($category_id, $category_rate_type_key, true);

            $is_category_disabled_key = self::MISC['is_product_category_referral_disabled_key'];
            $is_category_disabled = (bool)get_term_meta($category_id, $is_category_disabled_key, false);

            if ($is_category_disabled) {
                // TODO should we return something else when a category is disabled?
                return CommissionCalculator::not_applicable_either(__('This category is disabled.', 'solid-affiliate'));
            }


            switch ($referral_rate_type) {
                case 'site_default':
                    break;
                case 'percentage':
                    $commission = ($referral_rate / 100.0) * $order_item_description->amount;
                    $i = new ItemCommission(
                        [
                            'purchase_amount' => $order_item_description->amount,
                            'commissionable_amount' => $order_item_description->commissionable_amount,
                            'commission_amount' => $commission,
                            'commission_strategy' => CommissionCalculator::COMMISSION_STRATEGY_PRODUCT_CATEGORY_SPECIFIC_RATE,
                            'commission_strategy_rate_type' => 'percentage',
                            'commission_strategy_rate' => $referral_rate,
                            'product_id' => $order_item_description->product_id,
                            'variation_id' => $order_item_description->variation_id,
                            'quantity' => $order_item_description->quantity
                        ]
                    );
                    return new Either([''], $i, true);
                case 'flat':
                    $commission = $referral_rate * $order_item_description->quantity;
                    $i = new ItemCommission(
                        [
                            'purchase_amount' => $order_item_description->amount,
                            'commissionable_amount' => $order_item_description->commissionable_amount,
                            'commission_amount' => $commission,
                            'commission_strategy' => CommissionCalculator::COMMISSION_STRATEGY_PRODUCT_CATEGORY_SPECIFIC_RATE,
                            'commission_strategy_rate_type' => 'flat',
                            'commission_strategy_rate' => $referral_rate,
                            'product_id' => $order_item_description->product_id,
                            'variation_id' => $order_item_description->variation_id,
                            'quantity' => $order_item_description->quantity
                        ]
                    );
                    return new Either([''], $i, true);
                case '':
                    break;
            }
        }

        return CommissionCalculator::not_applicable_either();
    }

    /**
     * @param OrderDescription $order_description
     * 
     * @return Either<OrderItemDescription[]>
     */
    public static function get_order_item_descriptions_from_order_description($order_description)
    {
        if (!in_array($order_description->order_source, [WooCommerceIntegration::SOURCE, WooCommerceSubscriptionsIntegration::SOURCE])) {
            return new Either([sprintf(__('Could not find WC Order - improper order_source %1$s', 'solid-affiliate'), $order_description->order_source)], [], false);
        }

        $order = \wc_get_order($order_description->order_id);

        if ($order instanceof \WC_Order) {
            $wc_order_items = $order->get_items();

            // TODO support for non Product Order Item Types?
            $wc_order_items = array_filter($wc_order_items, function ($i) {
                return ($i instanceof \WC_Order_Item_Product);
            });


            $per_item_shipping = ($order_description->total_shipping / max(count($wc_order_items), 1));

            $order_item_descriptions = array_map(
                function ($wc_order_item) use ($per_item_shipping, $order_description, $order) {
                    $total = (float)$wc_order_item->get_total();
                    $item_tax = (float)$wc_order_item->get_total_tax();

                    $commissionable_amount = $total;

                    if (!Settings::get(Settings::KEY_IS_EXCLUDE_TAX)) {
                        $commissionable_amount = $commissionable_amount + $item_tax;
                    }
                    if (!Settings::get(Settings::KEY_IS_EXCLUDE_SHIPPING)) {
                        $commissionable_amount = $commissionable_amount + $per_item_shipping;
                    }

                    $commissionable_amount = (float)apply_filters("solid_affiliate/woocommerce_integration/order_item/commissionable_amount", $commissionable_amount, $wc_order_item, $order);


                    $d = new OrderItemDescription([
                        'amount' => $total,
                        'commissionable_amount' => $commissionable_amount,
                        'item_tax' => $item_tax,
                        'item_shipping' => $per_item_shipping,
                        'source' => self::SOURCE,
                        'product_id' => $wc_order_item->get_product_id(),
                        'variation_id' => $wc_order_item->get_variation_id(),
                        'order_id' => $wc_order_item->get_order_id(),
                        'type' => $wc_order_item->get_type(),
                        'quantity' => $wc_order_item->get_quantity(),
                        'is_renewal_order_item' => $order_description->is_renewal_order,
                    ]);

                    return $d;
                },
                $wc_order_items
            );

            return new Either([''], $order_item_descriptions, true);
        } else {
            return new Either([__('Could not find WooCommerce Order', 'solid-affiliate')], [], false);
        }
    }


    /**
     * Add product category referral rate field.
     * 
     * @param mixed $category
     * 
     * @return void
     */
    public static function add_product_category_rate($category)
    {
        $schema = self::schemas()['product_category'];
        $form = FormBuilder::build_form($schema, 'new');
        echo ($form);
    ?>

<?php
    }

    /**
     * @param \WP_Term $category
     * 
     * @return void
     */
    public static function edit_product_category_rate($category)
    {
        $category_id   = $category->term_id;

        $category_rate_key = self::MISC['product_category_referral_rate_key'];
        $category_rate = (string)get_term_meta($category_id, $category_rate_key, true);

        $category_rate_type_key = self::MISC['product_category_referral_rate_type_key'];
        $category_rate_type = (string)get_term_meta($category_id, $category_rate_type_key, true);

        $is_disabled_key = self::MISC['is_product_category_referral_disabled_key'];
        $is_diabled = (bool)get_term_meta($category_id, $is_disabled_key, true);

        $item = (object)[
            $category_rate_key => $category_rate,
            $category_rate_type_key => $category_rate_type,
            $is_disabled_key => $is_diabled
        ];

        $schema = self::schemas()['product_category'];
        $form = FormBuilder::build_form($schema, 'edit', $item);
        echo ($form);
    }

    /**
     * @param int $category_id
     * 
     * @return void
     */
    public static function save_product_category_rate($category_id)
    {
        $category_rate_key = self::MISC['product_category_referral_rate_key'];

        if (isset($_POST[$category_rate_key])) {
            $rate     = (float)$_POST[$category_rate_key];

            if ($rate) {
                update_term_meta($category_id, $category_rate_key, $rate);
            } else {
                delete_term_meta($category_id, $category_rate_key);
            }
        }

        $category_rate_type_key = self::MISC['product_category_referral_rate_type_key'];

        if (isset($_POST[$category_rate_type_key])) {
            $rate_type     = (string)$_POST[$category_rate_type_key];

            if ($rate_type) {
                update_term_meta($category_id, $category_rate_type_key, $rate_type);
            } else {
                delete_term_meta($category_id, $category_rate_type_key);
            }
        }

        $is_disabled_key = self::MISC['is_product_category_referral_disabled_key'];
        if (isset($_POST[$is_disabled_key])) {
            $is_disabled     = (bool)$_POST[$is_disabled_key];

            if ($is_disabled) {
                update_term_meta($category_id, $is_disabled_key, $is_disabled);
            } else {
                delete_term_meta($category_id, $is_disabled_key);
            }
        } else {
            delete_term_meta($category_id, $is_disabled_key);
        }
    }

    /**
     * Will get all WooCommerce Products and return tuples
     * in the format:
     * [
     *   [113, 'Virtual Subscription (#113)],
     *   [71, 'Logo Collection (logo-collection)]
     * ]
     * 
     * @param int $limit
     * 
     * @return array<array{int, string}>
     */
    public static function woocommerce_product_select_tuples($limit = 300)
    {
        if (!function_exists('wc_get_products')) {
            return [];
        }
        $all_product_data = Validators::arr_of_woocommerce_product(
            wc_get_products(['limit' => $limit])
        );

        $data = array_map(function ($r) {
            return [(int)$r->id, $r->get_formatted_name()];
        }, $all_product_data);

        return $data;
    }

    /**
     * @param int $product_id
     * @return bool
     */
    public static function does_product_exist($product_id)
    {
        if (!function_exists('wc_get_product')) {
            return false;
        }
        return (wc_get_product($product_id) instanceof \WC_Product);
    }

    /**
     * @param int $product_id
     * @return string
     */
    public static function formatted_product_link($product_id)
    {
        if (!function_exists('wc_get_product')) {
            return '-';
        }

        $maybe_wc_product = wc_get_product($product_id);
        if ($maybe_wc_product instanceof \WC_Product) {
            $product_url = get_edit_post_link($product_id);
            $product_title = $maybe_wc_product->get_title();

            return "<a href='{$product_url}'>{$product_title}</a>";
        } else {
            return '-';
        }
    }

    /**
     * @param int $product_id
     * @param int $variation_id
     * @return string
     */
    public static function product_name_by_id($product_id, $variation_id = 0)
    {
        if (!function_exists('wc_get_product')) {
            return '-';
        }

        if ($variation_id) {
            $maybe_wc_product = wc_get_product($variation_id);
        } else {
            $maybe_wc_product = wc_get_product($product_id);
        }

        if ($maybe_wc_product instanceof \WC_Product) {
            $product_title = $maybe_wc_product->get_formatted_name();

            return $product_title;
        } else {
            return '-';
        }
    }

    /**
     * @return string
     */
    public static function get_current_currency()
    {
        if (function_exists('get_woocommerce_currency')) {
            $currency_code = (string)get_woocommerce_currency();
            return $currency_code;
        } else {
            return "USD";
        }
    }

    /**
     * Rturns the currency symbol for the current currency.
     * Examples:
     *  - $
     *  - €
     *  
     * @return string
     */
    public static function get_current_currency_symbol()
    {
        if (function_exists('get_woocommerce_currency_symbol')) {
            $sumbol = (string)get_woocommerce_currency_symbol();
            return $sumbol;
        } else {
            return "$";
        }
    }

    /**
     * Given a coupon id and new coupon code, this function will check if the WC_Coupon exists
     * and if so, will update the code using set_code().
     * 
     * Maybe we'll use this to build a feature allowing Affiliates to update their own coupon codes *shrug*.
     * 
     * @param int $coupon_id
     * @param string $new_code
     * @return bool whether or not the coupon was updated.
     */
    public static function change_coupon_code($coupon_id, $new_code)
    {
        return false;
        // Verify that the $new_code is a proper coupon code.
        // if (!self::is_valid_coupon_code($new_code)) {
        //     return false;
        // }

        // $coupon = new \WC_Coupon($coupon_id);
        // if ($coupon instanceof \WC_Coupon) {
        //     $coupon->set_code($new_code);
        //     $coupon->save();
        //     return true;
        // } else {
        //     return false;
        // }
    }

    /**
     * @param string $coupon_code
     * 
     * @return boolean
     */
    public static function is_valid_coupon_code($coupon_code)
    {
        // TODO implement this.
        return true;
    }

    /**
     * Whether or not there is a valid coupon in the database.
     *
     * @param int $coupon_id
     *
     * @return boolean
     */
    public static function is_a_valid_coupon_id($coupon_id)
    {
        if (class_exists('WC_Coupon')) {
            $maybe_coupon = new \WC_Coupon($coupon_id);

            if ($maybe_coupon->get_id() != self::UNPERSISTED_COUPON_ID) {
                # TODO: Have a const/func for WP Post magic string of 'trash'?
                if (get_post_status($maybe_coupon->get_id()) === "trash") {
                    return false;
                } else {
                    return true;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Builds a new WC_Coupon from an existing WC_Coupon.
     *
     * @param \WC_Coupon $template_coupon
     * @param string $code
     *
     * @return \WC_Coupon
     */
    # TODO: Do we want a default here? How flexbile do we want this to be?
    public static function build_coupon_from_coupon($template_coupon, $code)
    {
        $new_coupon = new \WC_Coupon($template_coupon);
        // Set ID to 0, so the new recorded is created, not updated.
        $new_coupon->set_id(0);
        $new_coupon->set_code($code);
        // Don't use the template coupon's created and updated dates becuase they will be inaccurate.
        $new_coupon->set_date_created(null);
        $new_coupon->set_date_modified(null);
        $new_coupon->set_description('This coupon was generated by Solid Affiliate.');
        return $new_coupon;
    }

    /**
     * Associates an Affiliate with a coupon via the wp_postmeta join table.
     *
     * @param int $coupon_id
     * @param int $affiliate_id
     *
     * @return int|bool
     */
    public static function associate_affiliate_with_coupon($coupon_id, $affiliate_id)
    {
        # TODO: Use constant for MISC Keys?
        return update_post_meta($coupon_id, self::MISC['coupon_affiliate_id_key'], $affiliate_id);
    }

    /**
     * Returns the specifc coupon if the coupon is associated to the specific Affiliate.
     *
     * @param int $coupon_id
     * @param int $affiliate_id
     *
     * @return \WC_Coupon|null
     */
    public static function find_coupon_for_affiliate($coupon_id, $affiliate_id)
    {
        $query_args = array(
            'post_type' => self::COUPON_POST_TYPE,
            'p' => $coupon_id,
            'meta_query' => array(
                array(
                    'key' => WooCommerceIntegration::MISC['coupon_affiliate_id_key'],
                    'value' => $affiliate_id
                )
            )
        );
        $query = new \WP_Query($query_args);
        $post = $query->post;

        if (is_null($post)) {
            return null;
        } else {
            return new \WC_Coupon($post->ID);
        }
    }

    /**
     * Returns all affiliate coupons from the WooCommerce store.
     *
     *
     * @return \WC_Coupon[]
     */
    public static function find_all_affiliate_coupons()
    {
        $args = array(
            'post_type' => \SolidAffiliate\Lib\Integrations\WooCommerceIntegration::COUPON_POST_TYPE,
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => \SolidAffiliate\Lib\Integrations\WooCommerceIntegration::MISC['coupon_affiliate_id_key'],
                    'value' => '',
                    'compare' => '!='
                ],
            ]
        );
        $query = new \WP_Query($args);
        $coupon_posts = $query->get_posts();

        $coupons = array_map(function ($post) {
            $post_id = is_int($post) ? $post : $post->ID;
            return new \WC_Coupon($post_id);
        }, $coupon_posts);

        return $coupons;
    }

    /**
     * Undocumented function
     *
     * @param mixed $settings
     * @return array
     */
    public static function filter_get_all_settings($settings)
    {
        $settings = array_merge((array)$settings, [
            Settings::KEY_INTEGRATIONS_WOOCOMMERCE => true
        ]);

        return $settings;
    }

    /**
     * Check if the currency configured in WooCommerce is compatible with Solid Affiliate
     *
     * @return boolean
     */
    public static function is_current_currency_valid_for_paypal_integration()
    {
        $acceptable_codes = array_map(function ($a) {
            return $a[0];
        }, Settings::SUPPORTED_CURRENCIES);

        $current_currency = WooCommerceIntegration::get_current_currency();
        $is_valid_currency = in_array($current_currency, $acceptable_codes);
        return $is_valid_currency;
    }

    /**
     * Given an email address, this function will return the WooCommerce Order IDs
     * that are associated with that email address.
     *
     * @param string $email
     * @return array<int>
     */
    public static function get_order_ids_for_email($email)
    {
        // Check that the email is valid and not empty string. Return an empty array if not.
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($email)) {
            return [];
        }

        $query_args = array(
            'post_type' => 'shop_order',
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => '_billing_email',
                    'value' => $email
                )
            )
        );
        $query = new \WP_Query($query_args);
        $posts = $query->posts;
        $order_ids = array_map(function ($post) {
            if ($post instanceof \WP_Post) {
                return $post->ID;
            }
            /**
             * @psalm-suppress RedundantConditionGivenDocblockType
             */
            if (is_int($post)) {
                return $post;
            }
        }, $posts);

        return $order_ids;
    }

    /**
     * Get the Unix timestamp (in seconds) of when a WooCommerce order was created.
     *
     * @param int $order_id The ID of the WooCommerce order.
     * @return int|false The Unix timestamp (in seconds) of when the order was created, or false on failure.
     */
    public static function get_order_created_timestamp_from_order_id(int $order_id)
    {
        // Ensure that WooCommerce is loaded
        if (!function_exists('wc_get_order')) {
            return false;
        }

        // Get the WooCommerce order object
        $order = wc_get_order($order_id);

        // Ensure that the order object is valid
        if (!$order || !($order instanceof \WC_Order)) {
            return false;
        }

        // Get the order creation date
        $created_date = $order->get_date_created();

        // Ensure that the creation date is valid
        if (!$created_date || !($created_date instanceof \WC_DateTime)) {
            return false;
        }

        // Return the Unix timestamp (in seconds) of the order creation date
        return $created_date->getTimestamp();
    }
}
