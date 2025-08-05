<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Addons\AffiliateLandingPages\Addon as AffiliateLandingPagesAddon;
use SolidAffiliate\Addons\StoreCredit\Addon as StoreCreditAddon;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\AffiliateMeta;

/**
 * Feature Flags
 */
class FF
{
    /**
     * I'm keeping track of progress with check box system
     * 
     * [Bare basics locked down] [Fully styled and Ayman approved] [*FEAUTURE NEEDS TO BE BUILT*]
     * 
     * [ ] [ ] [ ]
     */
    const FEATURES = [
        2 => [
            'affiliate_tags', // [x] [ ]
            'global_search',  // [x] [ ]
            'email_reports',  // [x] [ ] [x] 
            'signup_bonus',   // [x] [ ] [x]
        ],
        3 => [
            'lifetime_commissions',                // [x] [ ] 
            'revenue_sharing',                     // [x] [ ]
            'advanced_payout_filters',             // [x] [ ]
            'fraud_prevention_suite',              // [x] [ ] [x]
            'affiliate_landing_pages',             // [x] [ ]
            'white_labeled_affiliate_portal',      // @ayman [ ] [ ] [ ]
            'customizable_email_notifications',    // @ayman [x] [x] [x]
            'customizable_affiliate_registration', // [x] [ ]
            'affiliate_portal_preview',            // [x] [ ]
            'affiliate_coupon_discount_links',     // [x] [ ]
            'commission_rates_overview',           // [ ] [ ] TODO-ff need to actually lock out the page.
            'custom_affiliate_slugs',              // [x] [ ]
            'recurring_referrals',                 // [x] [ ]
            'auto_create_affiliate_coupons'        // [ ] [ ] TODO-ff need to figure this out with the setup wizard
        ],
    ];

    /**
     * What this will actually do currently, is run each of the settings through
     * self::disable_a_schema_entry - this will disable the setting, only works for checkboxes atm.
     * 
     * @return array{lifetime_commissions: 'is_lifetime_commissions_enabled'[], affiliate_coupon_discount_links: 'is_auto_apply_affiliate_coupon_enabled'[], recurring_referrals: 'is_enable_recurring_referrals'[], custom_affiliate_slugs: ('can_affiliates_create_custom_slugs'|'should_auto_create_affiliate_username_slug')[], email_reports: 'is_affiliate_manager_monthly_report_email_enabled'[]}
     */
    public static function get_settings_mapping_to_disable()
    {
        return [
            'lifetime_commissions' => [
                Settings::KEY_IS_LIFETIME_COMMISSIONS_ENABLED
            ],
            'affiliate_coupon_discount_links' => [
                Settings::KEY_IS_AUTO_APPLY_AFFILIATE_COUPON_ENABLED
            ],
            'recurring_referrals' => [
                Settings::KEY_IS_ENABLE_RECURRING_REFERRALS
            ],
            'custom_affiliate_slugs' => [
                Settings::KEY_CAN_AFFILIATES_CREATE_CUSTOM_SLUGS,
                Settings::KEY_SHOULD_AUTO_CREATE_AFFILIATE_USERNAME_SLUG
            ],
            'email_reports' => [
                Settings::KEY_IS_AFFILIATE_MANAGER_MONTHLY_REPORT_EMAIL_ENABLED
            ]
        ];
    }

    /**
     * @return array{customizable_email_notifications: ('notification_email_subject_affiliate_manager_new_affiliate'|'notification_email_body_affiliate_manager_new_affiliate'|'notification_email_subject_affiliate_manager_new_referral'|'notification_email_body_affiliate_manager_new_referral'|'notification_email_subject_affiliate_application_accepted'|'notification_email_body_affiliate_application_accepted'|'notification_email_subject_affiliate_new_referral'|'notification_email_body_affiliate_new_referral')[]}
     */
    public static function get_settings_mapping_to_hide_group()
    {
        return [
            'customizable_email_notifications' => [
                Settings::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_MANAGER_NEW_AFFILIATE,
                Settings::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_MANAGER_NEW_AFFILIATE,

                Settings::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_MANAGER_NEW_REFERRAL,
                Settings::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_MANAGER_NEW_REFERRAL,

                Settings::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_APPLICATION_ACCEPTED,
                Settings::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_APPLICATION_ACCEPTED,

                Settings::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_NEW_REFERRAL,
                Settings::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_NEW_REFERRAL,
            ]
        ];
    }

    /**
     * @return array{revenue_sharing: string[], custom_affiliate_slugs: 'meta_value'[], signup_bonus: 'setting_key_is_store_credit_signup_bonus_enabled'[]}
     */
    public static function get_schema_entries_mapping()
    {
        return [
            'revenue_sharing' => [
                'is_auto_referral',
                'is_prevent_additional_referrals_when_auto_referral_is_triggered'
            ],
            'custom_affiliate_slugs' => [
                AffiliateMeta::SCHEMA_KEY_META_VALUE
            ],
            'signup_bonus' => [
                StoreCreditAddon::SETTING_KEY_IS_STORE_CREDIT_SIGNUP_BONUS_ENABLED
            ]
        ];
    }

    /**
     * @return array{affiliate_landing_pages: 'affiliate-landing-pages'[]}
     */
    public static function get_addons_mapping()
    {
        return [
            'affiliate_landing_pages' => [
                AffiliateLandingPagesAddon::ADDON_SLUG
            ]
        ];
    }

    /**
     * TODO-ff IMPLEMENT
     * TODO-ff cache/memoize per request
     * 
     * @return 1|2|3
     */
    public static function get_plan_level()
    {
        $license_data = License::get_license_data();
        $feature_level = $license_data['feature_level'] ?? 1;

        // ensure it is 1 or 2 or 3
        if (!in_array($feature_level, [1, 2, 3])) {
            return 1;
        } else {
            /** @var 1|2|3 */
            return $feature_level;
        }

        return $feature_level;
    }


    /**
     * @return string
     */
    public static function get_plan_level_written()
    {
        $planLevel = self::get_plan_level();

        if ($planLevel === 1) {
            return 'Personal';
        } else if ($planLevel === 2) {
            return 'Expert';
        } else if ($planLevel === 3) {
            return 'Pro';
        }

        return 'ERROR - please contact support';
    }

    /**
     * @return void
     */
    public static function register_hooks()
    {
        add_filter('solid_affiliate/settings/get', [self::class, 'filter_get_setting'], 10, 2);

        add_filter('solid_affiliate/settings/get_all', [self::class, 'filter_get_all_settings'], 10, 1);
    }

    /**
     * @param string $feature
     * @return bool
     */
    public static function is_on($feature)
    {
        $planLevel = self::get_plan_level();

        // Collect all available features from current and lower plan levels
        $availableFeatures = [];
        foreach (self::FEATURES as $level => $features) {
            // Only include features from levels less than or equal to current plan
            if ($level <= $planLevel) {
                $availableFeatures = array_merge($availableFeatures, $features);
            }
        }

        // Verify the feature exists in our feature set
        $allFeatures = array_merge(...array_values(self::FEATURES));
        if (!in_array($feature, $allFeatures)) {
            throw new \Exception("Feature '$feature' not found in any plan level.");
        }

        // Check if the feature is available in current plan or lower plans
        return in_array($feature, $availableFeatures);
    }

    /**
     * @param string $feature
     * @return bool
     */
    public static function is_off($feature)
    {
        return !self::is_on($feature);
    }

    /**
     * @param string $feature
     * @return string
     */
    public static function maybe_pill($feature)
    {
        if (self::is_off($feature)) {
            return self::pill();
        } else {
            return "";
        }
    }

    /**
     * @return string
     */
    public static function pill()
    {
        // Define styles for the pill and tooltip
        $pillStyle = '
            background:rgb(255 210 113);
            font-size: 12px;
            color:#754114;
            margin-left:3px;
            padding: 3px 5px;
            border-radius: 10px;
            font-weight: 600;
            line-height: 1;
            display: inline-block;
            align-self: center;
        ';

        $tooltipContent = '
            <div class="sld-tooltip-box" style="max-width: 200px;">
                <p class="sld-tooltip_heading">Pro feature</p>
                <p class="sld-tooltip_body">
                    This feature is exclusively available in the Solid Affiliate Pro Plan. To unlock and use this functionality, please upgrade your current plan.
                </p>
                <p class="sld-tooltip_hint">
                    <a href="https://solidaffiliate.com/pricing" target="_blank">Upgrade to the pro plan</a>
                </p>
            </div>
        ';

        // Return the final HTML
        return sprintf(
            '<div style="%s" class="sld-tooltip" data-html="true" data-sld-tooltip-content="%s">Pro</div>',
            $pillStyle,
            htmlspecialchars($tooltipContent) // Escape the tooltip content for security
        );
    }


    /**
     * @param string $feature
     * @return string
     */
    public static function maybe_banner($feature)
    {
        if (self::is_off($feature)) {
            return "<div style='background-color: white; color: #333; padding: 15px; border: 2px solid orange; text-align: center; font-size: 18px;'>
            🔒 This is a Pro feature. <a href='https://solidaffiliate.com/pricing' style='color: #4CAF50;'>Click here to upgrade to the Pro Plan.</a>
        </div>";
        } else {
            return "";
        }
    }

    /**
     * @param string $feature
     * @return string
     */
    public static function maybe_overlay($feature)
    {
        if (!self::is_off($feature)) {
            return '';
        }

        $translations = [
            'logo_alt' => esc_attr__('Solid Affiliate logo', 'solid-affiliate'),
            'pro_label' => 'Pro',
            'feature_title' => __('This is a %s feature', 'solid-affiliate'),
            'upgrade_description' => __('Access to this feature is restricted exclusively to users with a "Pro Plan" subscription. Please consider upgrading your license to use this feature.', 'solid-affiliate'),
            'upgrade_button' => __('Upgrade now', 'solid-affiliate'),
        ];

        $urls = [
            'logo' => 'https://solidaffiliate.com/brand/logo.svg',
            'pricing' => 'https://solidaffiliate.com/pricing',
        ];

        // Build the title with the Pro pill link
        $title_with_link = sprintf(
            $translations['feature_title'],
            sprintf(
                '<a href="%s" class="pill-within-overlay">%s</a>',
                esc_url($urls['pricing']),
                $translations['pro_label']
            )
        );

        // Build HTML using heredoc syntax for better readability
        $overlay_content = <<<HTML
    <div class="sld-feature-overlay">
        <div class="sld-feature-overlay-content">
            <img src="{$urls['logo']}" 
                 height="20" 
                 width="auto" 
                 alt="{$translations['logo_alt']}">
            <div>
                <h2>{$title_with_link}</h2>
                <p>{$translations['upgrade_description']}</p>
            </div>
            <a href="{$urls['pricing']}" 
               class="sld-button primary large" 
               target="_blank">{$translations['upgrade_button']}</a>
        </div>
    </div>
HTML;

        return $overlay_content;
    }


    /**
     * @return string
     */
    public static function maybe_license_page_banner()
    {
        return '<p>Your plan level is: <strong>' . FF::get_plan_level_written() . '</strong></p> <p>Upgrade it if you need to, and click Refresh to see the changes.</p>';
    }

    ////////////////////////////////////////////////////////////    
    // SETTINGS - start
    ////////////////////////////////////////////////////////////    

    /**
     * @param SchemaEntry $entry
     * 
     * @return SchemaEntry
     */
    public static function disable_a_schema_entry($entry)
    {
        $entry->show_on_edit_form = 'disabled';
        $entry->show_on_new_form = 'hidden_and_disabled';
        $entry->display_name = $entry->display_name . FF::pill();
        $entry->form_input_description = $entry->form_input_description;
        $entry->default = false;
        $entry->user_default = false;

        return $entry;
    }

    /**
     * @param SchemaEntry $entry
     * 
     * @return SchemaEntry
     */
    public static function hide_group_of_schema_entry($entry)
    {
        $entry->settings_group = 'HIDDEN';

        return $entry;
    }


    /**
     * This is the function that runs from within Settings::schema()
     * 
     * @param array<Settings::KEY_*, SchemaEntry> $entries
     * 
     * @return array<Settings::KEY_*, SchemaEntry>
     */
    public static function filter_settings_schema_entries($entries)
    {
        // For every matching setting in settings overrides:
        // - Disable the setting [x] (should it be 'disabled_and_hidden' ?)
        // - Force it's value [ ]
        // - Add styling/copy to the settings page [ ]
        // - Maybe make the toggles red or something [ ]
        $settings_overrides = self::settings_overrides();
        $entries = self::filter_schema_entries($settings_overrides, $entries);

        // Hide the groups
        $groups_to_hide = self::get_settings_mapping_to_hide_group();
        $overrides = self::get_overrides($groups_to_hide);
        $entries = self::filter_schema_entries($overrides, $entries, 'hide_group');


        /**
         * @var array<Settings::KEY_*, SchemaEntry> 
         */
        return $entries;
    }

    /**
     * @param string[] $overrides
     * @param array<string, SchemaEntry> $entries
     * @param 'disable'|'hide_group' $type
     * 
     * @return array<string, SchemaEntry>
     */
    public static function filter_schema_entries($overrides, $entries, $type = 'disable')
    {

        foreach ($overrides as $key) {
            $entry = $entries[$key];

            if ($type === 'disable') {
                $entry = self::disable_a_schema_entry($entry);
            } else if ($type === 'hide_group') {
                $entry = self::hide_group_of_schema_entry($entry);
            }

            $entries[$key] = $entry;
        }

        return $entries;
    }

    /**
     * @return string[]
     * 
     * get an array of all the settings that are overridden,
     * which would be any on a higher plan level
     * for example: if the plan level is 2, then return
     * all the settings that match a feature flag from level 3
     * i.e. [Settings::KEY_IS_LIFETIME_COMMISSIONS_ENABLED]
     */
    public static function settings_overrides()
    {
        $mapping = self::get_settings_mapping_to_disable();
        return self::get_overrides($mapping);
    }

    /**
     * @param array<string, string[]> $mapping
     * @param string|null $feature_flag
     * 
     * @return string[]
     */
    public static function get_overrides($mapping, $feature_flag = null)
    {
        $planLevel = self::get_plan_level();
        $keys_to_override = [];

        // If feature_flag is provided, filter the mapping to only include that key
        if ($feature_flag !== null) {
            $mapping = array_intersect_key($mapping, [$feature_flag => []]);
        }

        // Iterate through each plan level in FEATURES
        foreach (self::FEATURES as $level => $features) {
            // Only look at features from higher plan levels
            if ($level > $planLevel) {
                // For each feature in this level
                foreach ($features as $feature) {
                    // If this feature has mapped settings
                    if (isset($mapping[$feature])) {
                        // Add all mapped settings for this feature to our result
                        $keys_to_override = array_merge(
                            $keys_to_override,
                            $mapping[$feature]
                        );
                    }
                }
            }
        }

        return array_unique($keys_to_override);
    }

    /**
     * Undocumented function
     *
     * @param mixed $settings
     * 
     * @return array
     */
    public static function filter_get_all_settings($settings)
    {
        // for every setting in settings overrides:
        // - set the value to false [x]

        // - maybe handle setting to anything [ ]

        $settings_overrides = self::settings_overrides();

        foreach ($settings_overrides as $setting_key) {
            $settings[$setting_key] = false;
        }

        return $settings;
    }

    public static function filter_get_setting($val, $key)
    {
        $settings_overrides = self::settings_overrides();

        if (in_array($key, $settings_overrides)) {
            return false;
        }

        return $val;
    }
    ////////////////////////////////////////////////////////////    
    // SETTINGS - end
    ////////////////////////////////////////////////////////////    

    /**
     * @template T
     * @param array<string, SchemaEntry> $entries
     * @param string $feature_flag
     * @return array<string, SchemaEntry> - todo it returns the exact same type as the input
     */
    public static function filter_schema_entries_for_feature_flag($entries, $feature_flag)
    {
        // will need to make a mapping for feature_flag -> entries
        // and run all that match through self::disable_a_schema_entry
        $schema_entries_mapping = self::get_schema_entries_mapping();

        $overrides = self::get_overrides($schema_entries_mapping, $feature_flag);


        return self::filter_schema_entries($overrides, $entries);
    }

    ////////////////
    // ADDONS
    ////////////////
    /**
     * @param string[] $enabled_addons
     * @return string[]
     */
    public static function filter_enabled_addons($enabled_addons)
    {
        $planLevel = self::get_plan_level();

        $overrides = self::get_overrides(self::get_addons_mapping());

        foreach ($overrides as $addon) {
            $enabled_addons = array_diff($enabled_addons, [$addon]);
        }

        return $enabled_addons;
    }
}
