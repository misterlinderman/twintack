<?php

namespace SolidAffiliate\Views\Admin\CommissionRates;

use SolidAffiliate\Addons\AffiliateLandingPages\Addon;
use SolidAffiliate\Addons\Core;
use SolidAffiliate\Controllers\AffiliatesController;
use SolidAffiliate\Lib\AffiliatePortalFunctions;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\Links;
use SolidAffiliate\Lib\ListTables\SharedListTableFunctions;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateGroup;
use SolidAffiliate\Models\AffiliateProductRate;
use SolidAffiliate\Views\Shared\SimpleTableView;
use SolidAffiliate\Views\Shared\SolidTooltipView;
use SolidAffiliate\Views\Shared\AdminHeader;


class RootView
{
    /**
     * @return string
     */
    public static function render()
    {
        ob_start();
?>

        <style>
            .commisiion-rates-render {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .commisiion-rates-render h3,
            .commisiion-rates-render p {
                margin: 3px;
                ;
            }

            .accordion {
                background-color: #fff;
                color: #444;
                height: 60px;
                cursor: pointer;
                padding: 18px;
                width: 100%;
                text-align: left;
                font-weight: 400;
                outline: 0;
                font-size: 13px;
                transition: .4s;
                justify-content: space-between;
                align-items: center;
                display: flex;
                border: 1px solid var(--sld-border);
            }

            .panel {
                border-color: var(--sld-border) !important;
            }

            .accordion.active {
                margin-bottom: 0;
                border-radius: 0;
            }

            .accordion span.setting-status {
                margin-left: auto;
                font-size: 12px;
                color: #4169e1
            }

            .accordion:hover,
            .active {
                background-color: rgba(255, 255, 255, .35)
            }

            .accordion:after {
                margin-left: 20px;
                background: #ededf0;
                border-radius: var(--sld-radius-sm);
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                content: url("data:image/svg+xml,%3Csvg width='21' height='21' viewBox='0 0 21 21' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M7.5 8.5L10.5 5.5L13.5 8.5' stroke='%23A7A7A7' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M7.5 13.5L10.5 16.5L13.5 13.5' stroke='%23A7A7A7' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E%0A");
            }

            .active:after {
                content: url("data:image/svg+xml,%3Csvg width='21' height='21' viewBox='0 0 21 21' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M7.5 15.5L10.5 12.5L13.5 15.5' stroke='%23A7A7A7' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M7.5 6.5L10.5 9.5L13.5 6.5' stroke='%23A7A7A7' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E%0A");
            }

            .panel {
                padding: 0 18px;
                background-color: #fff;
                max-height: 0;
                overflow: hidden;
                border-width: 0 1px;
                border-style: solid;
                border-color: #c3c4c7;
                transition: max-height .2s ease-out
            }

            .accordion.active+.panel {
                border-bottom-width: 1px;
            }

            .actions {
                margin-bottom: 20px
            }
        </style>
        <div class="wrap">
            <div class="sld-admin-container commisiion-rates-render">
                <div>
                    <h1><?php _e('Commission Rates', 'solid-affiliate') ?></h1>
                    <p><?php _e('Solid Affiliate allows you to customize your affiliate program to fit your needs. This page brings together all of the different commission rates that your program is currently using.', 'solid-affiliate') ?></p>
                </div>
                <div>
                    <h3><?php _e('Global Commission Rate Settings', 'solid-affiliate') ?></h3>
                    <button class="accordion">
                        <?php _e('Exclude Shipping', 'solid-affiliate') ?>
                        <span class="setting-status">
                            <?php echo ((bool)(Settings::get(Settings::KEY_IS_EXCLUDE_SHIPPING)) ? __('Enabled', 'solid-affiliate') : __('Disabled', 'solid-affiliate')) ?>
                        </span>
                    </button>
                    <div id="exclude-shipping" class="panel">
                        <p><?php _e('Depending on your business, you may be shipping physical products to customers and charging them a shipping fee, which is a hard/net cost. This setting allows you to exclude shipping costs from referral calculations, so the order total that Solid Affiliate calculates the referral amount from does not include the shipping cost that is charged to customers.', 'solid-affiliate') ?></p>
                        <p>
                            <?php _e(' Enable this setting to exclude shipping costs from referral calculations.', 'solid-affiliate') ?>
                        </p>
                        <div class="actions">
                            <p><a href="<?php echo (URLs::settings(Settings::TAB_GENERAL)) ?>#is_exclude_shipping" target="_blank" rel="noopener" class="sld-admin-card_button"><?php _e('Edit Setting', 'solid-affiliate') ?></a></p>
                        </div>
                    </div>
                    <button class="accordion">
                        <?php _e('Exclude Tax', 'solid-affiliate') ?>
                        <span class="setting-status">
                            <?php echo ((bool)(Settings::get(Settings::KEY_IS_EXCLUDE_TAX)) ? __('Enabled', 'solid-affiliate') : __('Disabled', 'solid-affiliate')) ?>
                        </span>
                    </button>
                    <div id="exclude-tax" class="panel">
                        <p><?php _e('Depending on your business, you may charge your customers tax, which is a hard/net cost. This setting allows you to exclude tax from referral calculations, so the order total that Solid Affiliate calculates the referral amount from does not include the tax that is charged to customers.', 'solid-affiliate') ?></p>
                        <p>
                            <?php _e('Enable this setting to exclude tax costs from referral calculations.', 'solid-affiliate') ?>
                        </p>
                        <div class="actions">
                            <p><a href="<?php echo (URLs::settings(Settings::TAB_GENERAL)) ?>#is_exclude_tax" target="_blank" rel="noopener" class="sld-admin-card_button"><?php _e('Edit Setting', 'solid-affiliate') ?></a></p>
                        </div>
                    </div>
                </div>
                <div>
                    <h3><?php _e('Default commissions rates', 'solid-affiliate') ?></h3>
                    <button class="accordion"><?php _e('Default Referral Rate', 'solid-affiliate') ?></button>
                    <div id="default-referral-rate" class="panel">
                        <p><?php _e('The referral rate determines how much the affiliate earns on each sale they generate for the store.', 'solid-affiliate') ?> <?php _e('The global referral rates <em>(which will apply to all affiliates)</em> are :', 'solid-affiliate') ?>
                        </p>
                        <?php echo (self::render_site_default_settings()) ?>
                        <div class="actions">
                            <p><a href="<?php echo (URLs::settings(Settings::TAB_GENERAL)) ?>#referral_rate" target="_blank" rel="noopener" class="sld-admin-card_button"><?php _e('Edit Setting', 'solid-affiliate') ?></a></p>
                        </div>
                    </div>
                </div>
                <div>
                    <h3>
                        <?php _e('Commission Rate Overrides', 'solid-affiliate') ?>
                    </h3>
                    <p>
                        <?php _e('Commissions Rates can take priority over others. Below is the order of which commission plan will be used.', 'solid-affiliate') ?>
                    </p>

                    <button class="accordion">
                        <?php _e('Affiliate-Product Rates', 'solid-affiliate') ?>
                        <span class="setting-status"><?php _e('Priority 1', 'solid-affiliate') ?></span>
                    </button>

                    <div id="per-affiliate" class="panel">
                        <p><?php _e('Set extra specific product commission rates on a per-affiliate level. These would override your site default rate and any other affiliate or product rates.', 'solid-affiliate') ?></p>
                        <h4><?php _e('An example configuration', 'solid-affiliate') ?></h4>
                        <?php _e('While your site default rate might be 20%% for all products, you need one special Affiliate (ID 22) to receive 10%% commission for WooCommerce product A, 80%% commission for product B, and a flat rate of $50 for product C.', 'solid-affiliate') ?>
                        <p>
                            <?php printf(__('You currently have <strong>%1$d</strong> Affiliate Product Rates configured.', 'solid-affiliate'), AffiliateProductRate::count()) ?>

                        </p>
                        <div class="actions">
                            <p>
                                <a href="<?php echo (URLs::admin_path(AffiliateProductRate::ADMIN_PAGE_KEY)) ?>" target="_blank" rel="noopener" class="sld-admin-card_button">
                                    <?php _e('Manage Affiliate Product Rates', 'solid-affiliate') ?>
                                </a>
                            </p>
                        </div>
                    </div>

                    <button class="accordion">
                        <?php _e('Affiliate Rates', 'solid-affiliate') ?>
                        <span class="setting-status"><?php _e('Priority 2', 'solid-affiliate') ?></span>
                    </button>
                    <div id="per-affiliate" class="panel">
                        <p><?php _e('Affiliate Specific rates are set on a per Affiliate basis. Use these to give specific Affiliates different rates than the site defaults.', 'solid-affiliate') ?>
                        </p>
                        <?php echo (self::render_affiliate_specific()) ?>
                        <div class="actions">
                            <p>
                                <a href="<?php echo (URLs::settings(Settings::TAB_GENERAL)) ?>" target="_blank" rel="noopener" class="sld-admin-card_button">
                                    <?php _e('Manage affiliates', 'solid-affiliate') ?>
                                </a>
                            </p>
                        </div>
                    </div>
                    <button class="accordion">
                        <?php _e('Affiliate Groups Rates', 'solid-affiliate') ?>
                        <span class="setting-status"><?php _e('Priority 3', 'solid-affiliate') ?></span>
                    </button>
                    <div id="per-affiliate-group" class="panel">
                        <p><?php _e('The Affiliate Group Rate applies if an Affiliate is in an <strong>Affiliate Group</strong>, and that group has commission rates configured to anything other than “site default”.', 'solid-affiliate') ?></p>
                        <?php echo (self::render_affiliate_groups()) ?>
                        <div class="actions">
                            <p>
                                <a href="<?php echo (URLs::admin_path(AffiliateGroup::ADMIN_PAGE_KEY)) ?>" target="_blank" rel="noopener" class="sld-admin-card_button">
                                    <?php _e('Manage Affiliate Groups', 'solid-affiliate') ?>
                                </a>
                            </p>
                        </div>
                    </div>

                    <button class="accordion">
                        <?php _e('Lifetime Commissions', 'solid-affiliate') ?>
                        <span class="setting-status"><?php _e('Priority 4', 'solid-affiliate') ?></span>
                    </button>
                    <div id="per-product" class="panel">
                        <?php $lifetime_commissions_url = URLs::settings(Settings::TAB_GENERAL, false, Settings::KEY_LIFETIME_COMMISSIONS_REFERRAL_RATE_TYPE); ?>
                        <p>Lifetime Commissions can be enabled to link your affiliates to any customers they refer. This way, they are guaranteed to get commissions for any future purchases by that customer. You can optionally set a specific commission rate for referrals generated by Lifetime Commissions.</p>
                        <?php echo (self::render_lifetime_commissions()) ?>
                        <div class="actions">
                            <?php $lifetime_commissions_settings_link = Links::render($lifetime_commissions_url, 'Lifetime Commissions'); ?>
                            <p> <?php echo ('Configure the Lifetime Commissions feature and commissions rate overide in Settings > General > ' . $lifetime_commissions_settings_link) ?></p>
                        </div>
                    </div>

                    <button class="accordion">
                        <?php _e('Product Variation Rates', 'solid-affiliate') ?>
                        <span class="setting-status"><?php _e('Priority 5', 'solid-affiliate') ?></span>
                    </button>
                    <div id="per-product" class="panel">
                        <p><?php _e('Product Variation Specific rates are set on a per product variation basis. Your affiliate will earn your specified affiliate commission per product variation in the order. You can set different affiliate commission rates for your affiliates. It can be a flat percentage or a flat rate commission.', 'solid-affiliate') ?></p>
                        <?php echo (self::render_product_variation_specific()) ?>
                        <div class="actions">
                            <p><?php _e('Edit any Product variation individually from the Edit Product page within <code>WooCommerce > Product > Variations > Solid Affiliate</code>.', 'solid-affiliate') ?></p>
                        </div>
                    </div>

                    <button class="accordion">
                        <?php _e('Product Rates', 'solid-affiliate') ?>
                        <span class="setting-status"><?php _e('Priority 6', 'solid-affiliate') ?></span>
                    </button>
                    <div id="per-product" class="panel">
                        <p><?php _e('Product Specific rates are set on a per product basis. Your affiliate will earn your specified affiliate commission per product in the order. You can set different affiliate commission rates for your affiliates. It can be a flat percentage or a flat rate commission.', 'solid-affiliate') ?></p>
                        <?php echo (self::render_product_specific()) ?>
                        <div class="actions">
                            <p><?php _e('Edit any Product individually from the Edit Product page within <code>WooCommerce > Product > Product Data > Solid Affiliate</code>.', 'solid-affiliate') ?></p>
                        </div>
                    </div>

                    <button class="accordion">
                        <?php _e('Product Category Rates', 'solid-affiliate') ?>
                        <span class="setting-status"><?php _e('Priority 7', 'solid-affiliate') ?></span>
                    </button>
                    <div id="per-product-category" class="panel">
                        <p><?php _e('Product Specific rates are set on a per product category basis. Use these to configure specific commission rates every product within a category. ', 'solid-affiliate') ?></p>
                        <?php echo (self::render_product_category_specific()) ?>
                        <div class="actions">
                            <p><?php _e('Create and edit commission plan for a product category within <code>Woocommerce > Products > Categories</code>.', 'solid-affiliate') ?></p>
                        </div>
                    </div>
                </div>
                <div>
                    <h3>
                        <?php _e('Affiliate Coupons', 'solid-affiliate') ?>
                    </h3>
                    <button class="accordion"><?php _e('Active Affiliate Coupons', 'solid-affiliate') ?></button>
                    <div id="active-coupons" class="panel">
                        <p><?php _e('You can assign WooCommerce Coupons to Affiliates. Whenever a coupon is redeemed, the associated Affiliate will be credited with the sale. Below the coupons that are currently assigned to Affiliates :', 'solid-affiliate') ?></p>
                        <?php echo (self::render_coupon_specific()) ?>
                        <div class="actions">
                            <p><?php _e('Enable affiliate coupon tracking with <b>WooCommerce > Marketing > Coupons</b> page and create a new coupon code.', 'solid-affiliate') ?></p>
                        </div>
                    </div>
                    <?php if (Core::is_addon_enabled(\SolidAffiliate\Addons\AffiliateLandingPages\Addon::ADDON_SLUG)) { ?>
                        <div class="spacer"></div>
                        <h2>
                            <?php _e('Affiliate Landing Pages', 'solid-affiliate') ?>
                        </h2>
                        <button class="accordion"><?php _e('Published Affiliate Landing Pages', 'solid-affiliate') ?></button>
                        <div id="active-landing-pages" class="panel">
                            <p><?php _e('You can assign any Page or Post to your Affiliates, and any traffic to that page will be attributed as a Visit for that Affiliate.', 'solid-affiliate') ?></p>
                            <?php echo (\SolidAffiliate\Addons\AffiliateLandingPages\Addon::table_for_commission_rates_page()) ?>
                            <div class="actions">
                                <p><?php _e('This is enabled by the <strong>Affiliate Landing Pages</strong> Addon.', 'solid-affiliate') ?> <?php echo (\SolidAffiliate\Addons\AffiliateLandingPages\Addon::render_documentation_link()) ?></p>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <script>
            var acc = document.getElementsByClassName("accordion");
            var i;

            for (i = 0; i < acc.length; i++) {
                acc[i].addEventListener("click", function() {
                    this.classList.toggle("active");
                    var panel = this.nextElementSibling;
                    if (panel.style.maxHeight) {
                        panel.style.maxHeight = null;
                    } else {
                        panel.style.maxHeight = panel.scrollHeight + "px";
                    }
                });
            }
        </script>

<?php
        return ob_get_clean();
    }

    /**
     * @return string
     */
    public static function render_site_default_settings()
    {
        $settings = Settings::get_many([
            Settings::KEY_REFERRAL_RATE,
            Settings::KEY_REFERRAL_RATE_TYPE,
            Settings::KEY_RECURRING_REFERRAL_RATE,
            Settings::KEY_RECURRING_REFERRAL_RATE_TYPE,
            Settings::KEY_IS_ENABLE_RECURRING_REFERRALS
        ]);
        $edit_link = sprintf('<a href="' . URLs::settings(Settings::TAB_GENERAL) . '">%1$s</a>', __('Edit', 'solid-affiliate'));
        $recurring_referrals_edit_link = sprintf('<a href="' . URLs::settings(Settings::TAB_RECURRING_REFERRALS) . '">%1$s</a>', __('Edit', 'solid-affiliate'));
        //////////////////////////////////////////////////
        // Standard Rate
        $referral_rate = (string)$settings[Settings::KEY_REFERRAL_RATE];
        /** @var 'flat'|'percentage' */
        $referral_rate_type = (string)$settings[Settings::KEY_REFERRAL_RATE_TYPE];
        $default_rate_formatted = Formatters::commission_rate($referral_rate, $referral_rate_type);
        if ($referral_rate_type === 'flat') {
            $default_rate_formatted = $default_rate_formatted . ' ' . __('flat', 'soild-affiliate');
        }
        //////////////////////////////////////////////////
        // Subscription Renewal Referrals Rate
        if ((bool)$settings[Settings::KEY_IS_ENABLE_RECURRING_REFERRALS]) {
            $referral_rate = (string)$settings[Settings::KEY_RECURRING_REFERRAL_RATE];
            /** @var 'flat'|'percentage' */
            $referral_rate_type = (string)$settings[Settings::KEY_RECURRING_REFERRAL_RATE_TYPE];
            $default_rate_formatted_recurring_referrals = Formatters::commission_rate($referral_rate, $referral_rate_type);
            if ($referral_rate_type === 'flat') {
                $default_rate_formatted_recurring_referrals = $default_rate_formatted_recurring_referrals . ' ' . __('flat', 'solid-affiliate');
            }
        } else {
            $default_rate_formatted_recurring_referrals = __("Disabled", 'solid-affiliate');
        }
        $table_body = [
            [__("Default Commission Rate", 'solid-affiliate'), $default_rate_formatted, $edit_link],
            [__("Subscription Renewal Referrals - Default Commission Rate", 'solid-affiliate'), $default_rate_formatted_recurring_referrals, $recurring_referrals_edit_link],
        ];
        return SimpleTableView::render([__('Setting', 'solid-affiliate'), __('Value', 'solid-affiliate'), __('Edit', 'solid-affiliate')], $table_body);
    }
    /**
     * @return string
     */
    public static function render_affiliate_specific()
    {
        // Get All Affiliates with Specific Rates.
        $affiliates_with_custom_rate = Affiliate::where([
            'commission_type' => [
                'operator' => '<>',
                'value' => 'site_default'
            ]
        ]);
        // Build a row for each
        $rows = array_map(function ($affiliate) {
            $affiliate_id = (int)$affiliate->id;
            $edit_link = sprintf('<a href="' . URLs::edit(Affiliate::class, $affiliate_id) . '">%1$s</a>', __('Edit', 'solid-affiliate'));
            /** @var 'flat'|'percentage' */
            $commission_type = (string)$affiliate->commission_type;
            $commission_rate = (string)$affiliate->commission_rate;
            $formatted_rate = Formatters::commission_rate($commission_rate, $commission_type);
            if ($commission_type === 'flat') {
                $formatted_rate = $formatted_rate . ' ' . __('flat', 'solid-affiliate');
            }
            $affiliate_column = SharedListTableFunctions::affiliate_column($affiliate_id, false);
            return [$affiliate_column, $formatted_rate, $edit_link];
        }, $affiliates_with_custom_rate);
        // Render table
        return SimpleTableView::render([__('Affiliate', 'solid-affiliate'), __('Per-Affiliate Commission Rate', 'solid-affiliate'), __('Action', 'solid-affiliate')], $rows);
    }

    /**
     * @return string
     */
    public static function render_affiliate_groups()
    {
        // Get All Affiliates with Specific Rates.
        $affiliate_groups = AffiliateGroup::all();

        // Build a row for each
        $rows = array_map(function ($affiliate_group) {
            $affiliate_group_id = (int)$affiliate_group->id;
            $group_name = $affiliate_group->name;
            $affiliates_in_group_count = Affiliate::count(['affiliate_group_id' => $affiliate_group_id]);

            $edit_link = sprintf('<a href="' . URLs::edit(AffiliateGroup::class, $affiliate_group_id) . '">%1$s</a>', __('Edit', 'solid-affiliate'));
            $commission_type = (string)$affiliate_group->commission_type;
            $commission_rate = (string)$affiliate_group->commission_rate;
            if ($commission_type === 'site_default') {
                $formatted_rate = __('Site default', 'solid-affiliate');
                return [$group_name, $affiliates_in_group_count, $formatted_rate, $edit_link];
            } else {
                $formatted_rate = Formatters::commission_rate($commission_rate, $commission_type);
                if ($commission_type === 'flat') {
                    $formatted_rate = $formatted_rate . ' ' . __('flat', 'solid-affiliate');
                }
                return [$group_name, $affiliates_in_group_count, $formatted_rate, $edit_link];
            }
        }, $affiliate_groups);
        // Render table
        return SimpleTableView::render([
            __('Affiliate Group', 'solid-affiliate'),
            __('# of Affiliates', 'solid-affiliate'),
            __('Group Commission Rate', 'solid-affiliate'),
            __('Action', 'solid-affiliate')
        ], $rows);
    }

    /**
     * TODO-HPOS
     * @return string
     */
    public static function render_product_variation_specific()
    {
        // Get All Products Variations with Specific Rates, or Referrals Disabled.
        $args = array(
            'post_type' => 'product_variation',
            'posts_per_page' => 100,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => WooCommerceIntegration::MISC['product_variation_referral_rate_type_key'],
                    'value' => ['flat', 'percentage'],
                    'compare' => 'IN'
                ],
                [
                    'key' => WooCommerceIntegration::MISC['is_product_variation_referral_disabled_key'],
                    'value' => 'yes'
                ]
            ]
        );
        $query = new \WP_Query($args);
        $posts = $query->get_posts();
        // Build a row for each
        $rows = array_map(
            /**
             * @param \WP_Post|int $product_post
             */
            function ($product_post) {
                if ($product_post instanceof \WP_Post) {
                    $product_id = $product_post->ID;
                    $product_link = WooCommerceIntegration::formatted_product_link($product_id);
                    $rate_type_key = WooCommerceIntegration::MISC['product_variation_referral_rate_type_key'];
                    /** @var 'percentage'|'flat' */
                    $referral_rate_type = (string)get_post_meta($product_id, $rate_type_key, true);
                    $rate_key = WooCommerceIntegration::MISC['product_variation_referral_rate_key'];
                    $referral_rate = (float)get_post_meta($product_id, $rate_key, true);
                    $is_disabled_key = WooCommerceIntegration::MISC['is_product_variation_referral_disabled_key'];
                    $is_disabled = (get_post_meta($product_id, $is_disabled_key, true) == 'yes');
                    if ($is_disabled) {
                        $formatted_rate = __('Disabled', 'solid-affiliate');
                    } else {
                        $formatted_rate = Formatters::commission_rate($referral_rate, $referral_rate_type);
                        if ($referral_rate_type === 'flat') {
                            $formatted_rate = $formatted_rate . ' ' . __('flat', 'solid-affiliate');
                        }
                    }
                    return [$product_link, $formatted_rate, ($is_disabled ? __('Disabled', 'solid-affiliate') : '-')];
                } else {
                    return ['-', '-', '-'];
                }
            },
            $posts
        );
        // Render table
        return SimpleTableView::render([__('Product Variation', 'solid-affiliate'), __('Per-Product Variation Commission Rate', 'solid-affiliate'), __('Referrals Disabled for this Product Variation', 'solid-affiliate')], $rows);
    }

    /**
     * TODO-HPOS
     * @return string
     */
    public static function render_product_specific()
    {
        // Get All Products with Specific Rates, or Referrals Disabled.
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 100,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => WooCommerceIntegration::MISC['product_referral_rate_type_key'],
                    'value' => ['flat', 'percentage'],
                    'compare' => 'IN'
                ],
                [
                    'key' => WooCommerceIntegration::MISC['is_product_referral_disabled_key'],
                    'value' => 'yes'
                ]
            ]
        );
        $query = new \WP_Query($args);
        $posts = $query->get_posts();
        // Build a row for each
        $rows = array_map(
            /**
             * @param \WP_Post|int $product_post
             */
            function ($product_post) {
                if ($product_post instanceof \WP_Post) {
                    $product_id = $product_post->ID;
                    $product_link = WooCommerceIntegration::formatted_product_link($product_id);
                    $rate_type_key = WooCommerceIntegration::MISC['product_referral_rate_type_key'];
                    /** @var 'percentage'|'flat' */
                    $referral_rate_type = (string)get_post_meta($product_id, $rate_type_key, true);
                    $rate_key = WooCommerceIntegration::MISC['product_referral_rate_key'];
                    $referral_rate = (float)get_post_meta($product_id, $rate_key, true);
                    $is_disabled_key = WooCommerceIntegration::MISC['is_product_referral_disabled_key'];
                    $is_disabled = (get_post_meta($product_id, $is_disabled_key, true) == 'yes');
                    if ($is_disabled) {
                        $formatted_rate = __('Disabled', 'solid-affiliate');
                    } else {
                        $formatted_rate = Formatters::commission_rate($referral_rate, $referral_rate_type);
                        if ($referral_rate_type === 'flat') {
                            $formatted_rate = $formatted_rate . ' ' . __('flat', 'solid-affiliate');
                        }
                    }
                    return [$product_link, $formatted_rate, ($is_disabled ? __('Disabled', 'solid-affiliate') : '-')];
                } else {
                    return ['-', '-', '-'];
                }
            },
            $posts
        );
        // Render table
        return SimpleTableView::render([__('Product', 'solid-affiliate'), __('Per-Product Commission Rate', 'solid-affiliate'), __('Referrals Disabled for this Product', 'solid-affiliate')], $rows);
    }
    /**
     * @return string
     */
    public static function render_product_category_specific()
    {
        // Get All Products with Specific Rates, or Referrals Disabled.
        $args = array(
            'taxonomy' => 'product_cat',
            'number' => 0,
            'hide_empty' => false,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => WooCommerceIntegration::MISC['product_category_referral_rate_type_key'],
                    'value' => ['flat', 'percentage'],
                    'compare' => 'IN'
                ],
                [
                    'key' => WooCommerceIntegration::MISC['is_product_category_referral_disabled_key'],
                    'value' => true
                ]
            ]
        );
        $product_categories = get_terms($args);
        /** @psalm-suppress DocblockTypeContradiction */
        if (($product_categories instanceof \WP_Error) || (is_int($product_categories))) {
            return SimpleTableView::render([__('Product Category', 'solid-affiliate'), __('Per-Product Category rate', 'solid-affiliate'), __('Referrals Disabled for this Category', 'solid-affiliate')], [[]]);
        } else {
            $product_categories = Validators::arr_of_wp_term($product_categories);
            // Build a row for each
            $rows = array_map(function ($product_category) {
                $product_category_id = $product_category->term_id;
                $product_category_name = $product_category->name;
                $category_rate_key = WooCommerceIntegration::MISC['product_category_referral_rate_key'];
                $category_rate = (string)get_term_meta($product_category_id, $category_rate_key, true);
                $category_rate_type_key = WooCommerceIntegration::MISC['product_category_referral_rate_type_key'];
                /** @var 'flat'|'percentage' */
                $category_rate_type = (string)get_term_meta($product_category_id, $category_rate_type_key, true);
                $product_url = get_edit_term_link($product_category_id);
                $product_category_link = "<a href='{$product_url}'>{$product_category_name}</a>";
                $formatted_rate = Formatters::commission_rate($category_rate, $category_rate_type);
                // TODO add this 'flat' logic to Formatters::commission_rate
                if ($category_rate_type === 'flat') {
                    $formatted_rate = $formatted_rate . ' ' . __('flat', 'solid-affiliate');
                }
                if ($category_rate_type === 'site_default') {
                    $formatted_rate = __('Site Default', 'solid-affiliate');
                }
                $is_disabled_key = WooCommerceIntegration::MISC['is_product_category_referral_disabled_key'];
                $is_disabled = (bool)get_term_meta($product_category_id, $is_disabled_key, false);
                return [$product_category_link, $formatted_rate, ($is_disabled ? __('Disabled', 'solid-affiliate') : '-')];
            }, $product_categories);
            // Render table
            return SimpleTableView::render([__('Product Category', 'solid-affiliate'), __('Per-Product Category rate', 'solid-affiliate'), __('Referrals Disabled for this Category', 'solid-affiliate')], $rows);
        }
    }

    /**
     * TODO-HPOS
     * @return string
     */
    public static function render_coupon_specific()
    {
        $coupon_posts = WooCommerceIntegration::get_all_affiliate_coupons();
        // Build a row for each
        $rows = array_map(function ($coupon_post) {
            if ($coupon_post instanceof \WP_Post) {
                $coupon_id = $coupon_post->ID;
                $coupon_column = Links::render($coupon_post, $coupon_post->post_title);
                $linked_affiliate_id = (int)get_post_meta($coupon_id, WooCommerceIntegration::MISC['coupon_affiliate_id_key'], true);
                $affiliate_column = SharedListTableFunctions::affiliate_column($linked_affiliate_id, false);
                $edit_column = Links::render($coupon_post, 'Edit');
                $referrals_count_column = AffiliatePortalFunctions::coupon_data_for_affiliate_id($linked_affiliate_id)[0][3];
                return [$coupon_column, $affiliate_column, $referrals_count_column, $edit_column];
            } else {
                return ['-', '-', '-', '-'];
            }
        }, $coupon_posts);
        // Render table
        return SimpleTableView::render([__('Coupon', 'solid-affiliate'), __('Linked Affiliate', 'solid-affiliate'), __('Referrals via coupon'), __('Edit', 'solid-affiliate')], $rows);
    }

    /**
     * @return string
     */
    public static function render_lifetime_commissions()
    {
        /** @var 'site_default'|'percentage'|'flat' $rate_type*/
        $rate_type = Settings::get(Settings::KEY_LIFETIME_COMMISSIONS_REFERRAL_RATE_TYPE);
        $rate = (float)Settings::get(Settings::KEY_LIFETIME_COMMISSIONS_REFERRAL_RATE);

        if ($rate_type == 'site_default') {
            $formatted_rate = __('Site Default. There is no override.', 'solid-affiliate');
        } else {
            $formatted_rate = Formatters::commission_rate($rate, $rate_type);
        }
        return "Your current Lifetime Commission rate settings: " . '<strong>' . $formatted_rate . '</strong>';
    }
}
