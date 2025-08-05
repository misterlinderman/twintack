<?php

namespace SolidAffiliate\Lib\WooSoftwareLicense;

use SolidAffiliate\Lib\Validators;

/**
 * @psalm-type LicenseData = array{
 *  key: string, 
 *  last_check: int, 
 *  is_on_free_trial?: bool, 
 *  free_trial_end?: string|false,
 *  is_expired?: bool,
 *  feature_level?: int
 * }
 * 
 * NOTES for free trial flow
 * 
 * note the licence_expire key exists:
 * [{"status":"success","status_code":"s215","message":"Licence Key Is Active and Valid for Domain","licence_status":"active","licence_start":"2023-02-01","licence_expire":"2023-02-15"}]
 * 
 * When expired on a valid domain (that was already properly set up):
 * [{"status":"success","status_code":"s215","message":"Licence Key Is Active and Valid for Domain","licence_status":"expired","licence_start":"2023-02-01","licence_expire":"2023-02-01"}]
 */
class WOO_SLT_Licence
{
    const OPTION_KEY = 'solid_affiliate_slt_license';
    const MINIMUM_TIME_BETWEEN_API_CHECKS_IN_SECONDS = 36000; // 10 hour

    function __construct()
    {
        $this->licence_deactivation_check();
    }

    function __destruct() {}

    /**
     * NOTES: I do a lot of validation in here to get PSALM to pass. Leave it be.
     * 
     * @return LicenseData|false
     */
    public static function get_license_data()
    {
        $data = get_site_option(self::OPTION_KEY);

        if (!is_array($data)) {
            return false;
        }


        if (!isset($data['key']) || is_string($data['key']) === false) {
            return false;
        }

        if (!isset($data['last_check']) || is_int($data['last_check']) === false) {
            return false;
        }

        ////////////////////////////////////////////////////////////////////////////////////////////
        // add default values for is_on_free_trial and free_trial_end (both false is empty)
        if (!isset($data['is_on_free_trial']) || is_bool($data['is_on_free_trial']) === false) {
            $data['is_on_free_trial'] = false;
        }

        if (!isset($data['free_trial_end']) || empty($data['free_trial_end']) || is_string($data['free_trial_end']) === false) {
            $data['free_trial_end'] = false;
        }
        ////////////////////////////////////////////////////////////////////////////////////////////

        // feature_level is optional
        if (!isset($data['feature_level']) || is_int($data['feature_level']) === false) {
            $data['feature_level'] = 1;
        }

        $return_structure = [
            'key' => $data['key'],
            'last_check' => $data['last_check'],
            'is_on_free_trial' => $data['is_on_free_trial'],
            'free_trial_end' => $data['free_trial_end'],
            'feature_level' => $data['feature_level']
        ];

        if (isset($data['is_expired'])) {
            $return_structure['is_expired'] = (bool)$data['is_expired'];
        }

        return $return_structure;
    }

    /**
     * @param LicenseData $license_data
     * 
     * @return bool
     */
    public static function update_license_data($license_data)
    {
        return update_site_option(self::OPTION_KEY, $license_data);
    }

    /**
     * @return bool
     */
    public static function licence_key_verify()
    {
        $license_data = WOO_SLT_Licence::get_license_data();

        if (self::is_test_instance())
            return TRUE;

        if (!isset($license_data['key']) || $license_data['key'] == '')
            return FALSE;

        return TRUE;
    }

    /**
     * The point of this function is to determine if we're currently running tests.
     * 
     * We shouldn't be hitting our license API during tests, etc.
     * 
     * @return bool
     */
    public static function is_test_instance()
    {
        return (isset($_ENV['WORDPRESS_DB_NAME']) && ($_ENV['WORDPRESS_DB_NAME'] == 'tests')) || (get_site_url() == "http://wordpress.test");
    }


    /**
     * @return void
     */
    function licence_deactivation_check()
    {
        self::run_status_check();
    }


    /**
     * Runs the status-check on the license API.
     * 
     * @param bool|null $force_check
     *
     * @return void
     */
    public static function run_status_check($force_check = false)
    {
        /**
         * @psalm-suppress DocblockTypeContradiction
         */
        if (!self::licence_key_verify() ||  self::is_test_instance()  === TRUE)
            return;

        if (strpos(WOO_SLT_INSTANCE, 'solidaffiliate') !== false) {
            return;
        };

        $license_data = WOO_SLT_Licence::get_license_data();
        if ($license_data === false) {
            $license_data = [
                'key' => '',
                'last_check' => time()
            ];
            WOO_SLT_Licence::update_license_data($license_data);
            return;
        }

        if (!$force_check && (time() < ((int)$license_data['last_check'] + self::MINIMUM_TIME_BETWEEN_API_CHECKS_IN_SECONDS))) {
            return;
        } else {
            $license_data['last_check'] = time();
            WOO_SLT_Licence::update_license_data($license_data);
        }

        $license_key = $license_data['key'];
        $args = array(
            'woo_sl_action'         => 'status-check',
            'licence_key'           => $license_key,
            'product_unique_id'     => WOO_SLT_PRODUCT_ID,
            'domain'                => WOO_SLT_INSTANCE
        );

        if ($force_check) {
            // add random arg based on current timestampe
            $args['force_check'] = time();
        }

        $request_uri    = WOO_SLT_APP_API_URL . '?' . http_build_query($args, '', '&');
        $data           = wp_remote_get($request_uri);

        if ($data instanceof \WP_Error) {
            $license_data['last_check']   = time();
            WOO_SLT_Licence::update_license_data($license_data);
            return;
        } else {
            if (!isset($data['response']) || !isset($data['response']['code']) || $data['response']['code'] != 200) {
                $license_data['last_check']   = time();
                WOO_SLT_Licence::update_license_data($license_data);
                return;
            }

            if (!isset($data['body'])) {
                $license_data['last_check']   = time();
                WOO_SLT_Licence::update_license_data($license_data);
                return;
            }

            $response_block = Validators::arr(json_decode($data['body']));


            /**
             * This is some weird hack by the developer because the response comes in as an array with an object in it, like so:
             * "[{"status":"success","status_code":"s215","message":"Licence Key Is Active and Valid for Domain","licence_status":"active"}]"
             * 
             * Happy case this $response_block is a stdClass object
             *   status -> 'success'
             *   message -> 'Licence Key Is Active and Valid for Domain'
             *   status_cuse -> 's215'
             *   licence_status -> 'active'
             * 
             * @psalm-suppress MixedAssignment
             */
            $response_block = $response_block[count($response_block) - 1];

            if (!($response_block instanceof \stdClass)) {
                $license_data['last_check']   = time();
                WOO_SLT_Licence::update_license_data($license_data);
                return;
                // TODO handle
            } else {
                if (isset($response_block->status)) {
                    if ($response_block->status == 'success') {
                        // handle "is_on_free_trial":true,"free_trial_end":"2023-02-19 19:37:45"} 
                        // which might might not be in the response_block
                        if (isset($response_block->is_on_free_trial) && $response_block->is_on_free_trial == true) {
                            $license_data['is_on_free_trial'] = true;
                            $license_data['free_trial_end'] = (string)$response_block->free_trial_end;
                        } else {
                            $license_data['is_on_free_trial'] = false;
                            $license_data['free_trial_end'] = '';
                        }

                        if (isset($response_block->feature_level)) {
                            $license_data['feature_level'] = (int)$response_block->feature_level;
                        }


                        if ($response_block->licence_status == 'expired') {
                            // TODO Handle Expired
                            $license_data['last_check']   = time();
                            $license_data['is_expired']   = true;
                            WOO_SLT_Licence::update_license_data($license_data);
                            return;
                        }

                        if ($response_block->status_code == 's203' || $response_block->status_code == 's204') {
                            $license_data['key']          = '';
                        }
                    }

                    if ($response_block->status == 'error') {
                        $license_data['key']          = '';
                    }
                }

                $license_data['last_check']   = time();
                $license_data['is_expired']   = false;
                WOO_SLT_Licence::update_license_data($license_data);
            }
        }
    }
}
