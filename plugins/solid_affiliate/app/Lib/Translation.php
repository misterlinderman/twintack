<?php

namespace SolidAffiliate\Lib;

/**
 * Responsibilities:
 * - Everything related to Translating the plugin.
 */
class Translation
{

    /**
     * Internationalization of Solid Affiliate.
     * 
     * @return void
     */
    public static function solid_affiliate_load_plugin_textdomain()
    {
        add_filter('plugin_locale', [Translation::class, 'solid_affiliate_set_locale'], 10, 2);
        load_plugin_textdomain('solid-affiliate', false, plugin_basename(dirname(SOLID_AFFILIATE_FILE)) . '/assets/lang');
    }

    /**
     * Override the locale for the plugin.
     *
     * @param string $locale
     * @param string $domain
     * @return string
     */
    public static function solid_affiliate_set_locale($locale, $domain)
    {
        // A Mapping of locales to the locale that should be used for the plugin.
        $locale_mapping = [
            // Spanish
            'es_MX' => 'es_ES',
            'es_EC' => 'es_ES',
            'es_CO' => 'es_ES',
            'es_AR' => 'es_ES',
            'es_PE' => 'es_ES',
            'es_DO' => 'es_ES',
            'es_CL' => 'es_ES',
            'es_UY' => 'es_ES',
            'es_PR' => 'es_ES',
            'es_VE' => 'es_ES',
            'es_CR' => 'es_ES',
            'es_GT' => 'es_ES',
            // Chinese
            'zh_CN' => 'zh_CN',
            'zh_HK' => 'zh_CN',
            // 'zh_TW' => 'zh_CN',
            // German
            'de_DE_formal' => 'de_DE',
            'de_DE_informal' => 'de_DE',
            'de_CH' => 'de_DE',
            'de_AT' => 'de_DE',
            // French
            'fr_FR' => 'fr_FR',
            'fr_BE' => 'fr_FR',
            // Dutch
            'nl_NL' => 'nl_NL',
            'nl_BE' => 'nl_NL',
        ];

        if ($domain === 'solid-affiliate') {
            // use the mapping above
            if (array_key_exists($locale, $locale_mapping)) {
                return $locale_mapping[$locale];
            }
        }

        return $locale;
    }

    /**
     * Casts all values in the array to a string and then translates them.
     *
     * @param array $array
     * @return array<string>
     */
    public static function translate_array($array)
    {
        return array_map(function ($item) {
            return __((string)$item, 'solid-affiliate');
        }, $array);
    }


    /**
     * This function is here so that POEDIT can find the strings.
     * 
     * @psalm-suppress UnevaluatedCode
     *
     * @return string
     */
    private static function _do_not_ever_use_this()
    {
        return "DO NOT USE";
        /** @psalm-suppress UnevaluatedCode */
        __('Other Referral', 'solid-affiliate');
        __('URL Tracking', 'solid-affiliate');
        __('Affiliate Registration', 'solid-affiliate');
        __('Other', 'solid-affiliate');
        __('Email General', 'solid-affiliate');
        __('Affiliate Manager - Registration Notification Email', 'solid-affiliate');
        __('Affiliate Manager - Referral Notification Email', 'solid-affiliate');
        __('Affiliate - Application Accepted Email', 'solid-affiliate');
        __('Affiliate - Referral Notification Email', 'solid-affiliate');
        __('approved', 'solid-affiliate');
        __('Referral Amount', 'solid-affiliate');
        __('Converted Visits', 'solid-affiliate');
        __('Visits Conversion Rate', 'solid-affiliate');
        __('Conversions From Coupons', 'solid-affiliate');
        __('Total Affiliate Registrations', 'solid-affiliate');
        __('Referral Amount', 'solid-affiliate');
        __('Number of Referrals', 'solid-affiliate');
        __('Commission Amount', 'solid-affiliate');
        __('Net Revenue Amount', 'solid-affiliate');
        __('Total Bulk Payouts Count', 'solid-affiliate');
        __('Total Payouts Count', 'solid-affiliate');
        __('Total Payout Amount', 'solid-affiliate');
        __('Average Payout Amount', 'solid-affiliate');
        __('Total Visits Count', 'solid-affiliate');
        __('Total Visits Resulting in Referral', 'solid-affiliate');
        __('Conversion Rate', 'solid-affiliate');
        __('Data Export', 'solid-affiliate');
        __('Documentation', 'solid-affiliate');
        __('Groups', 'solid-affiliate');
        __('License', 'solid-affiliate');
        __('Addons', 'solid-affiliate');
        __('Support', 'solid-affiliate');
        __('Affiliates (default group)', 'solid-affiliate');
        __('Date', 'solid-affiliate');  
        __('Source', 'solid-affiliate');
        __('Amount', 'solid-affiliate');
        __('Description', 'solid-affiliate');
        __('Status', 'solid-affiliate');
        __('No data to display.', 'solid-affiliate');
        __('Referring Page', 'solid-affiliate');
        __('Code', 'solid-affiliate');
        __('Type', 'solid-affiliate');
        __('Discount Type', 'solid-affiliate');
        __('No data to display.', 'solid-affiliate');
        __('Successfully registered as an Affiliate!', 'solid-affiliate');
        __('Successfully logged in as an Affiliate', 'solid-affiliate');
        __('Successfully updated Affiliate settings.', 'solid-affiliate');
        __('There as an error updating the Affiliate settings.', 'solid-affiliate');
        __('unpaid', 'solid-affiliate');
        __('paid', 'solid-affiliate');
        __('rejected', 'solid-affiliate');
        __('draft', 'solid-affiliate');
        __('visit', 'solid-affiliate');
        __('coupon', 'solid-affiliate');
        __('auto_referral', 'solid-affiliate');
        __('Influencer', 'solid-affiliate');
        __('Partner', 'solid-affiliate');
        __('Associate', 'solid-affiliate');
        __('Reseller', 'solid-affiliate');
        __('Marketer', 'solid-affiliate');
        __('Ambassador', 'solid-affiliate');
        __('Brand Ambassador', 'solid-affiliate');
        __('Item', 'solid-affiliate');
        __('Purchase Price', 'solid-affiliate');
        __('Commissionable Amount', 'solid-affiliate');
        __('Referral Rate Used', 'solid-affiliate');
        __('Commission', 'solid-affiliate');
        __('Customer', 'solid-affiliate');
        // Expires On
        __('Expires On', 'solid-affiliate');
        __('Percent', 'solid-affiliate');
        __('Fixed Cart', 'solid-affiliate');
    }
}
