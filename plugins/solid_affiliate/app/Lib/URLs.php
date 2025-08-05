<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Controllers\AdminDashboardController;
use SolidAffiliate\Controllers\AdminMenuController;
use SolidAffiliate\Controllers\AdminReportsController;
use SolidAffiliate\Controllers\AffiliatesController;
use SolidAffiliate\Lib\CustomAffiliateSlugs\AffiliateCustomSlugBase;
use SolidAffiliate\Lib\MikesDataModel;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\Affiliate;

class URLs
{
    /**
     * Returns the WordPress core Permalink structure setting.
     * WordPress stores it as an empty string if the setting is set to "Plain" (ex: sitename.com/page_id=3). So return the string plain for that configuration.
     *
     * @return string
     */
    public static function get_permalink_format_setting()
    {
        $setting = Validators::str(get_option('permalink_structure'));

        if (Utils::is_empty($setting)) {
            return 'plain';
        } else {
            return $setting;
        }
    }

    /**
     * Builds URLs for any Admin Page thats just a function of a page_key.
     * 
     * @param string $page_key
     * @param bool $return_just_path If true, will just return the path. Otherwise the full URL.
     * @param array<string, mixed> $query_args
     * 
     * @return string
     */
    public static function admin_path($page_key, $return_just_path = false, $query_args = [])
    {
        $path = "admin.php?page={$page_key}";
        if (!empty($query_args)) {
            $path = add_query_arg($query_args, $path);
        }
        return $return_just_path ? $path : get_admin_url(null, $path);
    }

    /**
     * Delete URL generator.
     * 
     * PSALM will enfore that you give it a class string of a class that
     * inherits from DataModel. you have to append the ::class, see example.
     * 
     * Example:
     *      URLs::delete(Affiliate::class);
     *      => "localhost:3000/wp-admin/admin.php?page=solid-affiliate-affiliates&action=delete";
     * 
     *      URLs::delete(Affiliate::class, true);
     *      => "admin.php?page=solid-affiliate-affiliates&action=delete";
     *
     * @param class-string<MikesDataModel> $model_class
     * @param bool $return_just_path If true, will just return the path. Otherwise the full URL.
     * @param int|null $maybe_id 
     * 
     * @return string
     */
    public static function delete($model_class, $return_just_path = false, $maybe_id = null)
    {
        $page_key = (string)$model_class::ADMIN_PAGE_KEY;

        $args = [
            'action' => 'delete',
        ];

        if (is_int($maybe_id)) {
            $args['id'] = $maybe_id;
        };

        return self::admin_path($page_key, $return_just_path, $args);
    }

    /**
     * Create URL generator.
     * 
     * @param class-string<MikesDataModel> $model_class
     * @param bool $return_just_path If true, will just return the path. Otherwise the full URL.
     * 
     * @return string
     */
    public static function create($model_class, $return_just_path = false)
    {
        $page_key = (string)$model_class::ADMIN_PAGE_KEY;
        return self::admin_path($page_key, $return_just_path, [
            'action' => 'new'
        ]);
    }

    /**
     * Index URL generator.
     * 
     * @param class-string<MikesDataModel> $model_class
     * @param bool $return_just_path If true, will just return the path. Otherwise the full URL.
     * @param array<string, mixed> $query_args
     * 
     * @return string
     */
    public static function index($model_class, $return_just_path = false, $query_args = [])
    {
        $page_key = (string)$model_class::ADMIN_PAGE_KEY;

        $args = [];
        if (defined($model_class . '::ADMIN_PAGE_TAB')) {
            $args['tab'] = (string)$model_class::ADMIN_PAGE_TAB;
        }

        if (!empty($query_args)) {
            $args = array_merge($args, $query_args);
        }

        /**
         * @psalm-suppress MixedArgumentTypeCoercion
         */
        return self::admin_path($page_key, $return_just_path, $args);
    }

    /**
     * Edit URL generator.
     * 
     * @param class-string<MikesDataModel> $model_class
     * @param int $id
     * @param bool $return_just_path If true, will just return the path. Otherwise the full URL.
     * 
     * @return string
     */
    public static function edit($model_class, $id, $return_just_path = false)
    {
        $page_key = (string)$model_class::ADMIN_PAGE_KEY;

        return self::admin_path($page_key, $return_just_path, [
            'action' => 'edit',
            'id' => $id,
        ]);
    }


    /**
     * Builds URLs for the Admin Settings Page.
     * 
     * @param Settings::TAB_*|null $maybe_tab
     * @param bool $return_just_path If true, will just return the path. Otherwise the full URL.
     * @param Settings::KEY_*|null $maybe_key 
     * 
     * @return string
     */
    public static function settings($maybe_tab = null, $return_just_path = false, $maybe_key = null)
    {
        $tab = is_null($maybe_tab) ? Settings::TAB_GENERAL : $maybe_tab;
        $tab_key = Settings::TABS_TO_KEYS[$tab];
        $page_key = Settings::ADMIN_PAGE_KEY;

        $path = self::admin_path($page_key, $return_just_path, [
            'tab' => $tab_key,
        ]);

        if (!is_null($maybe_key)) {
            $path .= '#' . $maybe_key;
        }

        return $path;
    }


    /**
     * Checks if the affiliate portal page setting is set and returns a valid permalink if set.
     *
     * @return false|string
     */
    public static function maybe_affiliate_portal_link()
    {
        return get_permalink((int)Settings::get(Settings::KEY_AFFILIATE_PORTAL_PAGE));
    }

    /**
     * Will return 'REDIRECT_BACK' if no path is found.
     * @return string
     */
    public static function get_affiliate_portal_path()
    {
        $maybe_permalink = self::maybe_affiliate_portal_link();
        if ($maybe_permalink === false) {
            return 'REDIRECT_BACK';
        } else {
            return self::get_relative_permalink($maybe_permalink);
        }
    }

    /**
     * The URL to the site's portal, or if the portal page is not set, then to the sites home URL.
     * Used in emails to direct recipients to the portal.
     *
     * @return string
     */
    public static function site_portal_url()
    {
        $path = self::get_affiliate_portal_path();
        if ($path === 'REDIRECT_BACK') {
            return home_url();
        } else {
            return home_url($path);
        }
    }

    /**
     * @param int $affiliate_id
     * @param bool $return_just_path If true, will just return the path. Otherwise the full URL.
     * 
     * @return string
     */
    public static function admin_portal_preview_path($affiliate_id, $return_just_path = true)
    {
        return self::admin_path(Affiliate::ADMIN_PAGE_KEY, $return_just_path, [
            'action' => AffiliatesController::ADMIN_PREVIEW_AFFILIATE_PORTAL_ACTION,
            'id' => $affiliate_id,
        ]);
    }

    /**
     * @param string $url
     * @param string|null $home_url_override
     * 
     * @return string
     */
    public static function get_relative_permalink($url, $home_url_override = null)
    {
        $home_url = is_null($home_url_override) ? home_url() : $home_url_override;

        // remove everything after '?' and the '?'
        $home_url = preg_replace('/\?.*/', '', $home_url);
        // remove the trailing slash if there is one
        $home_url = rtrim($home_url, '/');

        return str_replace($home_url, "", $url);
    }

    /**
     * Returns the full URL for the default affiliate link using the Affiliate's ID.
     *
     * @param Affiliate $affiliate
     *
     * @return string
     */
    public static function default_id_format_affiliate_link($affiliate)
    {
        $referral_variable = (string)Settings::get(Settings::KEY_REFERRAL_VARIABLE);
        $base_url = self::default_affiliate_link_base_url();
        return add_query_arg([$referral_variable => (int)$affiliate->id], $base_url);
    }

    /**
     * Returns the full URL for the default affiliate link using the first custom slug if there is one. Otherwise return null.
     *
     * @param Affiliate $affiliate
     *
     * @return string|null
     */
    public static function maybe_default_slug_format_affiliate_link($affiliate)
    {
        $referral_variable = (string)Settings::get(Settings::KEY_REFERRAL_VARIABLE);
        $base_url = self::default_affiliate_link_base_url();
        $maybe_meta = AffiliateCustomSlugBase::maybe_default_slug_meta_for_affiliate($affiliate->id);

        if (is_null($maybe_meta)) {
            return null;
        } else {
            return add_query_arg([$referral_variable => $maybe_meta->meta_value], $base_url);
        }
    }

    /**
     * The full URL for the default affiliate link to be shown to Affiliates.
     *
     * @param Affiliate $affiliate
     * @param string|null $slug
     * @param string|null $base_url
     *
     * @return string
     */
    public static function default_affiliate_link($affiliate, $slug = null, $base_url = null)
    {
        $referral_variable = (string)Settings::get(Settings::KEY_REFERRAL_VARIABLE);

        if (is_null($base_url)) {
            $base_url = self::default_affiliate_link_base_url();
        }

        if (is_null($slug)) {
            $slug = AffiliateCustomSlugBase::default_affiliate_slug($affiliate);
        }

        ////////////////////////////////////////////////////////////
        // Handle parsing and adding dynamic params to the base_url.
        // Supported dynamic params:
        //
        // {{affiliate.slug}}
        // {{affiliate.id}}
        //
        // Example
        // https://example.com/{{affiliate.slug}}/some-page => https://example.com/jackson2/some-page
        $base_url = str_replace('{{affiliate.slug}}', (string)$slug, $base_url);
        $base_url = str_replace('{{affiliate.id}}', (string)$affiliate->id, $base_url);
        ////////////////////////////////////////////////////////////


        return add_query_arg([$referral_variable => $slug], $base_url);
    }

    /**
     * The base URL for the default affiliate link to be shown to Affiliates. It is based on the KEY_DEFAULT_AFFILIATE_LINK_URL setting, and defaults to the root URL.
     *
     * @return string
     */
    public static function default_affiliate_link_base_url()
    {
        $default_affiliate_link_url = (string)Settings::get(Settings::KEY_DEFAULT_AFFILIATE_LINK_URL);

        if (empty($default_affiliate_link_url)) {
            return home_url('/');
        } else {
            return $default_affiliate_link_url;
        }
    }

    /**
     * Returns the query param string (key and val) for referral tracking. For display in views.
     *
     * @param string $key
     * @param string|int $val
     *
     * @return string
     */
    public static function url_referral_query_string($key, $val)
    {
        return '?' . $key . '=' . $val;
    }


    /**
     * @param bool $return_just_path If true, will just return the path. Otherwise the full URL.
     * 
     * @return string
     */
    public static function dashboard_path($return_just_path = true)
    {
        if (SetupWizard::is_displayed()) {
            return self::admin_path(AdminDashboardController::PAGE_PARAM_V2, $return_just_path);
        } else {
            return self::admin_path(AdminMenuController::ROOT_PAGE_KEY, $return_just_path);
        }
    }

    /**
     * Returns path for adding a new coupon.
     * i.e. /wp-admin/post-new.php?post_type=shop_coupon
     *
     * @param boolean $return_just_path
     * 
     * @return string
     */
    public static function add_new_coupon_path($return_just_path = true)
    {
        $path = 'post-new.php?post_type=shop_coupon';
        return $return_just_path ? $path : get_admin_url(null, $path);
    }

    /**
     * Returns path for all affiliate coupons
     * i.e. 'wp-admin/edit.php?post_type=shop_coupon&affiliate_coupons=1
     *
     * @param boolean $return_just_path
     * 
     * @return string
     */
    public static function all_affiliate_coupons_path($return_just_path = true)
    {
        $path = 'edit.php?post_type=shop_coupon&affiliate_coupons=1';
        return $return_just_path ? $path : get_admin_url(null, $path);
    }

    /** 
     * Returns the path for Reports / Coupons
     * 
     * @param boolean $return_just_path
     * 
     * @return string
     */
    public static function reports_coupons_path($return_just_path = true)
    {
        return self::admin_path(AdminReportsController::ADMIN_PAGE_KEY, $return_just_path, [
            'tab' => 'coupons',
        ]);
    }

    /** 
     * Returns the path for Reports / Affiliates
     * 
     * @param boolean $return_just_path
     * 
     * @return string
     */
    public static function reports_affiliates_path($return_just_path = true)
    {
        return self::admin_path(AdminReportsController::ADMIN_PAGE_KEY, $return_just_path, [
            'tab' => 'affiliates',
        ]);
    }
}
