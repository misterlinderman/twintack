<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate;
use SolidAffiliate\Lib\WooSoftwareLicense\WOO_SLT;
use SolidAffiliate\Lib\WooSoftwareLicense\WOO_SLT_CodeAutoUpdate;
use SolidAffiliate\Lib\WooSoftwareLicense\WOO_SLT_Licence;

/**
 * For anyone who want to steal our code / null this class, you should reconsider it.
 * 
 * We take care of our community and we offer special discounts to any customers who can't justify the full price.
 * 
 * You should reach out to our team and we can talk about ways you can make much more money by becoming an affiliate, without nulling our code.
 * 
 * With love, from the Solid Affiliate team.
 * 
 * @psalm-import-type LicenseData from \SolidAffiliate\Lib\WooSoftwareLicense\WOO_SLT_Licence
 */
class License
{
    const ADMIN_PAGE_KEY = "solid-affiliate-license-key-options";
    const NUMBER_OF_KEYLESS_FREE_TRIAL_DAYS = 7;
    const OPTION_KEY_KEYLESS_FREE_TRIAL_BEGIN = 'solid-affiliate_free_trial_begin';
    const OPTIONE_KEY_KEYLESS_FREE_TRIAL_UNIQUE_KEY = 'solid-affiliate_free_trial_unique_key';

    /**
     * @param string $plugin_file
     * 
     * @return void
     */
    public static function init($plugin_file)
    {
        ////////////////////////////////////////////////////
        // WooCommerce Software License - heavily modified
        // https://wpsoftwarelicense.com/
        define('WOO_SLT_PATH',   plugin_dir_path($plugin_file));
        define('WOO_SLT_URL',    plugins_url('', $plugin_file));
        define('WOO_SLT_APP_API_URL',      'https://solidaffiliate.com/index.php');
        define('WOO_SLT_VERSION', '3.1.0');
        define('WOO_SLT_PRODUCT_ID',           'solid-affiliate');
        define('WOO_SLT_INSTANCE',             str_replace(array("https://", "http://"), "", network_site_url()));

        add_action('after_setup_theme', [self::class, 'run_updater']);

        new WOO_SLT; // This runs the deactivation check on __construct
    }

    /**
     * Undocumented function
     *
     * @return LicenseData|false
     */
    public static function get_license_data()
    {
        return WOO_SLT_Licence::get_license_data();
    }


    /**
     * Starts the free trial.
     *
     * @return int - unix timestamp
     */
    public static function begin_free_trial()
    {
        // timestamp
        $free_trial_begins_at = time();
        // if this site is already activated (an existing customer from before this funcitonlaity), then set the timestamp to a date in 1969
        if (self::is_solid_affiliate_activated()) {
            $free_trial_begins_at = 0;
        }

        //check if it already exists
        if (!get_site_option(self::OPTION_KEY_KEYLESS_FREE_TRIAL_BEGIN, false)) {
            add_site_option(self::OPTION_KEY_KEYLESS_FREE_TRIAL_BEGIN, $free_trial_begins_at);
        }

        // also, make a unique indentifier key followig this format: "sldf-cb95dace-75b5d5a7-2dab9f49"
        if (!get_site_option(self::OPTIONE_KEY_KEYLESS_FREE_TRIAL_UNIQUE_KEY, false)) {
            if (self::is_solid_affiliate_activated()) {
                $unique_key = 'sld-already-active-' . md5($free_trial_begins_at . uniqid());
            } else {
                $unique_key = 'sld-trial-' . md5($free_trial_begins_at . uniqid());
            }
            add_site_option(self::OPTIONE_KEY_KEYLESS_FREE_TRIAL_UNIQUE_KEY, $unique_key);
            License::force_ping_solid_server();
        }

        return (int)get_site_option(self::OPTION_KEY_KEYLESS_FREE_TRIAL_BEGIN);
    }

    /**
     * @return bool
     */
    public static function is_on_keyless_free_trial()
    {
        $end = self::get_keyless_free_trial_end_timestamp();
        $now = time();
        return $now <= $end;
    }

    /**
     * Undocumented function
     *
     * @return int - unix timestamp
     */
    public static function get_keyless_free_trial_end_timestamp()
    {
        $begin = self::begin_free_trial();

        $end = $begin + (self::NUMBER_OF_KEYLESS_FREE_TRIAL_DAYS * 24 * 60 * 60);
        return $end;
    }


    /**
     * @return string
     */
    public static function get_keyless_id()
    {
        self::begin_free_trial();
        return (string)get_site_option('solid-affiliate_free_trial_unique_key');
    }


    /**
     * @return bool
     */
    public static function is_solid_affiliate_activated()
    {
        return WOO_SLT_Licence::licence_key_verify();
    }


    /**
     * @return bool
     */
    public static function is_solid_affiliate_activated_but_expired()
    {
        $is_active = WOO_SLT_Licence::licence_key_verify();
        if (!$is_active) {
            return false;
        } else {
            $maybe_license_data = self::get_license_data();
            $maybe_is_expired = $maybe_license_data['is_expired'] ?? false;
            return $maybe_is_expired;
        }
    }

    /**
     * @return bool
     */
    public static function is_solid_affiliate_activated_and_not_expired()
    {
        return WOO_SLT_Licence::licence_key_verify() && !self::is_solid_affiliate_activated_but_expired();
    }

    /**
     * @return bool
     */
    public static function is_solid_affiliate_activated_and_not_expired_or_on_keyless_free_trial()
    {
        return self::is_on_keyless_free_trial() || (WOO_SLT_Licence::licence_key_verify() && !self::is_solid_affiliate_activated_but_expired());
    }

    /**
     * @return bool
     */
    public static function is_solid_affiliate_on_free_trial()
    {
        $on_free_trial = self::get_license_data()['is_on_free_trial'] ?? false;
        $free_trial_end = self::get_license_data()['free_trial_end'] ?? '0';

        return $on_free_trial && strtotime((string)$free_trial_end) > time();
    }

    /**
     * Returns a timestamp of when the free trial expires.
     * If the plugin is not currently on a free trial, it will return false.
     *
     * @return string|false
     */
    public static function free_trial_expires_at()
    {
        return self::get_license_data()['free_trial_end'] ?? false;
    }



    /**
     * @return string
     */
    public static function get_license_key()
    {
        if (!self::is_solid_affiliate_activated()) {
            return "";
        } else {
            $maybe_license_data = self::get_license_data();
            $maybe_license_key = $maybe_license_data['key'] ?? '';
            return $maybe_license_key;
        }
    }

    /**
     * This function will force the plugin to ping the Solid server to update Solid Licenses data.
     *
     * @return void
     */
    public static function force_ping_solid_server()
    {
        if (WOO_SLT_Licence::is_test_instance()) {
            return;
        }

        $wp_plugin_auto_update = new WOO_SLT_CodeAutoUpdate(WOO_SLT_APP_API_URL, 'solid_affiliate', 'solid_affiliate/plugin.php');
        $wp_plugin_auto_update->force_ping_solid_server();
    }

    /**
     * @return void
     */
    public static function run_updater()
    {
        $wp_plugin_auto_update = new WOO_SLT_CodeAutoUpdate(WOO_SLT_APP_API_URL, 'solid_affiliate', 'solid_affiliate/plugin.php');

        // Take over the update check
        add_filter('pre_set_site_transient_update_plugins', array($wp_plugin_auto_update, 'check_for_plugin_update'));

        // Take over the Plugin info screen
        add_filter('plugins_api', array($wp_plugin_auto_update, 'plugins_api_call'), 10, 3);
    }
}
