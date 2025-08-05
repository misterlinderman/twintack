<?php

namespace SolidAffiliate\Controllers;

use SolidAffiliate\Lib\VisitTracking;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Addons\AffiliateLandingPages\Addon as AffiliateLandingPages;

/**
 * SolidController
 * 
 * @psalm-suppress PropertyNotSetInConstructor
 */
class SolidController
{
    /**
     * @hook wp
     * @param \WP $_wp
     *
     * @return void
     */
    public static function visit_tracking($_wp)
    {
        $visit_tracking_query_key = (string)Settings::get(Settings::KEY_REFERRAL_VARIABLE);

        if (self::_should_track_visits($_REQUEST, $visit_tracking_query_key)) {
            add_filter('allowed_redirect_hosts', [self::class, "allow_external_host"], 10, 2);
            VisitTracking::handle_visit_tracking($_SERVER, $_REQUEST);
        }
    }

    /**
     * Whether or not visit tracking should be on, based on if the tracking param key is present or if the Affiliate Landing Pages addon should be tracking visits.
     *
     * @param array $request $_REQUEST
     * @param string $tracking_key
     *
     * @return boolean
     */
    private static function _should_track_visits($request, $tracking_key)
    {
        return !(Settings::get(Settings::KEY_IS_COOKIES_DISABLED)) && (isset($request[$tracking_key]) || AffiliateLandingPages::should_track());
    }

    /**
     * Merge the host that is passed to `wp_validate_redirect` from `wp_get_referer` and `wp_get_raw_referer`
     * into the list of allowed hosts taken from `home_url`.
     *
     * @see https://developer.wordpress.org/reference/functions/wp_get_referer/
     * @see https://developer.wordpress.org/reference/functions/wp_validate_redirect/
     *
     * @param string[] $hosts
     * @param string $host
     *
     * @return string[]
     */
    public static function allow_external_host($hosts, $host)
    {
        if (!empty($host)) {
            return array_merge($hosts, [$host]);
        } else {
            return $hosts;
        }
    }
}
