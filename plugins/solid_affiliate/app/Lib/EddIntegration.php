<?php

namespace SolidAffiliate\Lib;

use Exception;

class EddIntegration
{
    const OUR_SITE = "https://solidaffiliate.com";

    const ACTION_CHECK_LICENSE = 'check_license';
    const ACTION_ACTIVATE_LICENSE = 'activate_license';

    const ITEM_ID = 758;

    const STATUS_INVALID = 'invalid';
    const STATUS_VALID = 'valid';

    /**
     * Undocumented function
     * 
     * @param string $license_key
     *
     * @return string
     */
    // public static function api_url($license_key)
    // {
    //     $base_url = self::OUR_SITE;
    //     $edd_action = self::ACTION_CHECK_LICENSE;

    //     $item_id = self::ITEM_ID;
    //     // $license = "alskdjf@3223dskjd"; // TODO get from settings
    //     $url_of_site_being_licensed = home_url();

    //     $url = "{$base_url}/?edd_action={$edd_action}&item_id={$item_id}&license={$license_key}&url={$url_of_site_being_licensed}";

    //     return $url;
    // }

    /**
     * Undocumented function
     *
     * @param string $license_key
     * 
     * @return array
     */
    public static function request_params($license_key)
    {
        return array(
            'edd_action' => self::ACTION_ACTIVATE_LICENSE,
            'item_id' => self::ITEM_ID,
            'license' => $license_key,
            'url'     => home_url()
        );
    }

    /**
     * Undocumented function
     *
     * @param string $license_key
     * 
     * @return array
     */
    public static function make_request($license_key)
    {
        // $request_url = self::api_url($license_key);
        $request_params = self::request_params($license_key);
        $response = wp_remote_post(self::OUR_SITE, array('timeout' => 15, 'sslverify' => false, 'body' => $request_params));
        $body = (array)json_decode(wp_remote_retrieve_body($response));

        return $body;
    }

    /**
     * Undocumented function
     *
     * @param string $license_key
     * 
     * @return self::STATUS_INVALID|self::STATUS_VALID
     */
    public static function activate_license_key($license_key)
    {
        try {
            $body = self::make_request($license_key);
        } catch (Exception $e) {
            return self::STATUS_INVALID;
        }

        if (isset($body['license'])) {
            if ($body['license'] == self::STATUS_VALID) {
                return self::STATUS_VALID;
            } else {
                return self::STATUS_INVALID;
            }
        } else {
            return self::STATUS_INVALID;
        }
    }
}
