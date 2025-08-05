<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Visit;
use SolidAffiliate\Addons\AffiliateLandingPages\Addon as AffiliateLandingPages;
use SolidAffiliate\Lib\CustomAffiliateSlugs\CustomSlugDbFunctions;

class VisitTracking
{
    const JS_VISIT_TRACKING_AFFILIATE_SLUG_FILTER = 'solid_affiliate/js_visit_tracking/affiliate_slug';

    /**
     * Gets the cookie key for the current blog.
     * This enables us to track visits across multiple blogs for multisite support.
     * 
     * @return string
     */
    public static function visit_cookie_key()
    {
        $blog_id = get_current_blog_id();
        if ($blog_id == 1) {
            return 'solid_visit_id';
        } else {
            return 'solid_visit_id-' . $blog_id;
        }
    }


    /**
     * parses $_server and $_request and maybe stores a visit in the db.
     *
     * @param array $server
     * @param array $request
     * @return void
     */
    public static function handle_visit_tracking($server, $request)
    {
        if ((bool)Settings::get(Settings::KEY_IS_SERVER_COOKIES_DISABLED)) {
            return;
        }

        $maybe_affiliate = self::_affiliate_from_request($request);

        if (!($maybe_affiliate instanceof Affiliate)) {
            return;
        }

        $either_visit = self::create_visit($server, $maybe_affiliate->id);

        if ($either_visit->isRight && $either_visit->right->id) {
            $id = $either_visit->right->id;
            self::set_visit_id_cookie($id);
        }
    }

    /**
     * Given the current $_REQUEST, return an Affiliate if their paramater or landing page is found.
     *
     * @param array $request
     * 
     * @return null|Affiliate
     */
    public static function _affiliate_from_request($request)
    {
        if (AffiliateLandingPages::should_track()) {
            $id = AffiliateLandingPages::get_affiliate_id_from_post_meta();
            $maybe_affiliate = Affiliate::find($id);
            if (!is_null($maybe_affiliate)) {
                return $maybe_affiliate;
            }
        }

        $visit_tracking_query_key = (string)Settings::get(Settings::KEY_REFERRAL_VARIABLE);
        if (isset($request[$visit_tracking_query_key])) {
            $param = Validators::str($request[$visit_tracking_query_key]);
            $maybe_id = self::_maybe_affiliate_id_from_query_param($param);
            return Affiliate::find($maybe_id);
        }

        return null;
    }

    /**
     * Returns the ID param if the param is numeric, otherwize tries to get the Affiliate ID from the custom slug, returning 0 if it cannot.
     *
     * @param string $param
     *
     * @return int
     */
    private static function _maybe_affiliate_id_from_query_param($param)
    {
        if (Utils::is_empty($param)) {
            return 0;
        }

        if (is_numeric($param)) {
            return (int)$param;
        }

        $maybe_id = CustomSlugDbFunctions::maybe_get_affiliate_id_from_slug($param);

        if (is_null($maybe_id)) {
            return 0;
        } else {
            return $maybe_id;
        }
    }

    /**
     * Visit tracking from our JS calls.
     *
     * @param string $affiliate_slug
     * @param string $landing_url
     * @param string $http_referrer
     * @param string $visit_ip
     * @param int $previous_visit_id
     * 
     * @return false|int
     */
    public static function handle_visit_tracking_ajax($affiliate_slug, $landing_url, $http_referrer, $visit_ip, $previous_visit_id = 0)
    {
        // if cookie tracking is disabled, just bail out
        if (Settings::get(Settings::KEY_IS_COOKIES_DISABLED)) {
            return false;
        }

        $affiliate_slug = Validators::str(apply_filters(self::JS_VISIT_TRACKING_AFFILIATE_SLUG_FILTER, $affiliate_slug, $landing_url));

        if (is_numeric($affiliate_slug)) {
            if (!Affiliate::find((int)$affiliate_slug)) {
                return false;
            }
            $affiliate_id = (int)$affiliate_slug;
        } else {
            $maybe_affiliate_id = CustomSlugDbFunctions::maybe_get_affiliate_id_from_slug($affiliate_slug);

            if (is_null($maybe_affiliate_id)) {
                return false;
            } else {
                $affiliate_id = $maybe_affiliate_id;
            }
        }

        $visit_params = [
            'affiliate_id' => $affiliate_id,
            'referral_id' => 0,
            'landing_url' => $landing_url,
            'http_referrer' => $http_referrer,
            'http_ip' => $visit_ip,
            'previous_visit_id' => $previous_visit_id,
        ];

        $either_visit = Visit::insert($visit_params);

        if ($either_visit->isRight && $either_visit->right->id) {
            $id = $either_visit->right->id;
            if (self::set_visit_id_cookie($id)) {
                return $id;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Undocumented public static function
     *
     * @param int $visit_id
     * @return bool
     */
    public static function set_visit_id_cookie($visit_id)
    {
        if (Settings::get(Settings::KEY_IS_COOKIES_DISABLED)) {
            return false;
        }

        $cookie_lifetime_in_days = Validators::positive_int(Settings::get(Settings::KEY_COOKIE_EXPIRATION_DAYS));
        $cookie_lifetime_in_seconds = 60 * 60 * 24 * $cookie_lifetime_in_days;

        $expiration = time() + $cookie_lifetime_in_seconds;
        if (!defined('COOKIEPATH') || !defined('COOKIE_DOMAIN')) {
            return setcookie(self::visit_cookie_key(), (string)$visit_id, $expiration, '/', '/');
        } else {
            return setcookie(self::visit_cookie_key(), (string)$visit_id, $expiration, (string)COOKIEPATH, (string)COOKIE_DOMAIN);
        }
    }

    /**
     * parses $_server and $_request and maybe stores a visit in the db.
     *
     * @param array $server
     * @param int $affiliate_id
     * @return Either<Visit>
     */
    public static function create_visit($server, $affiliate_id)
    {
        // Louis: You never know what's going on with global variables, some hosting providers/server configs can mess with this.
        // Just always assume that the env your plugin runs on is defective.
        if (!isset($server['REQUEST_URI'])) {
            /** @var Either<Visit> */
            return new Either([__('REQUEST_URI not set', 'solid-affiliate')], 0, false);
        }

        $previous_visit_id = self::get_cookied_visit_id() ? self::get_cookied_visit_id() : 0;

        $current_url = home_url((string)$server['REQUEST_URI']);

        $http_referrer = wp_get_referer() ? wp_get_referer() : ''; // todo can make pure? side effect

        if (Settings::get(Settings::KEY_IS_DISABLE_CUSTOMER_IP_ADDRESS_LOGGING)) {
            $ip = '';
        } else {
            $ip = self::get_ip($server);
        }

        $visit_params = [
            'affiliate_id' => $affiliate_id,
            'referral_id' => 0,
            'landing_url' => $current_url,
            'http_referrer' => $http_referrer,
            'http_ip' => $ip,
            'previous_visit_id' => $previous_visit_id,
        ];

        return Visit::insert($visit_params);
    }


    /**
     * parses $_server and attempts to find the request IP.
     *
     * @param array $server
     * 
     * @return string
     */
    public static function get_ip($server)
    {
        $address = (string)$server['REMOTE_ADDR'];

        if (array_key_exists('HTTP_X_FORWARDED_FOR', $server) && !empty($server['HTTP_X_FORWARDED_FOR'])) {

            $address = (string)$server['HTTP_X_FORWARDED_FOR'];
        } elseif (array_key_exists('HTTP_CLIENT_IP', $server) && !empty($server['HTTP_CLIENT_IP'])) {

            $address = (string)$server['HTTP_CLIENT_IP'];
        }

        if (strpos($address, ",") > 0) {

            $ips = explode(",", $address);

            $address = trim($ips[0]);
        }

        return $address;
    }

    /**
     * If the visit_id cookie is set, it will return its parsed value. Otherwise returns false!
     *
     * @return false|integer
     */
    public static function get_cookied_visit_id()
    {
        if (isset($_COOKIE[self::visit_cookie_key()])) {
            /**
             * @psalm-suppress PossiblyInvalidArrayOffset
             */
            $val = (string)$_COOKIE[self::visit_cookie_key()];
            $val = intval($val);

            // add a filter to allow for custom return values 'solid_affiliate/visit_tracking/get_cookied_visit_id'
            /** @psalm-suppress MixedAssignment */
            $val = apply_filters("solid_affiliate/visit_tracking/get_cookied_visit_id", $val);

            // ensure $val is an integer or false
            if (!is_int($val)) {
                $val = false;
            }

            return $val;
        }

        return false;
    }
}
