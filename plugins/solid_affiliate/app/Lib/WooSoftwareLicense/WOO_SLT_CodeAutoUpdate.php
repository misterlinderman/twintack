<?php

namespace SolidAffiliate\Lib\WooSoftwareLicense;

use SolidAffiliate\Lib\SolidAdminCRUD;
use SolidAffiliate\Lib\SolidLogger;
use SolidAffiliate\Lib\VO\UsageStatistics;

class WOO_SLT_CodeAutoUpdate
{
    const SECONDS_TO_WAIT_BEFORE_CHECKING_FOR_UPDATES = 1;

    # URL to check for updates, this is where the index.php script goes
    /** @var string */
    public $api_url;

    /** @var string */
    private $slug;

    /** @var string */
    public $plugin;


    /**
     * @param string $api_url
     * @param string $slug
     * @param string $plugin
     */
    public function __construct($api_url, $slug, $plugin)
    {
        $this->api_url = $api_url;

        $this->slug    = $slug;
        $this->plugin  = $plugin;
    }


    /**
     * @param mixed $checked_data
     * @return mixed
     */
    public function check_for_plugin_update_old($checked_data)
    {
        // SolidLogger::log('WOO_SLT_CodeAutoUpdate.php check_for_plugin_update() called');
        if (!is_object($checked_data) ||  !isset($checked_data->response) || WOO_SLT_Licence::is_test_instance()) {
            // SolidLogger::log('WOO_SLT_CodeAutoUpdate.php check_for_plugin_update() returning because is_object($checked_data) is false or WOO_SLT_Licence::is_test_instance() is true');
            return $checked_data;
        } else {
            $request_string = $this->prepare_request('plugin_update');
            /** @psalm-suppress DocblockTypeContradiction */
            if ($request_string === FALSE) {
                // SolidLogger::log('WOO_SLT_CodeAutoUpdate.php check_for_plugin_update() returning because $request_string is false');
                return $checked_data;
            }

            /////////////////////////////////////////////////////////////////////////////////
            // Add a check to see if we already made a request anytime in the last 10 minutes
            // if we did, don't make another request and return $checked_data
            $last_check = (int)get_option('solid_affiliate_slt_plugin-update_last_check', 0);
            if (time() < $last_check + self::SECONDS_TO_WAIT_BEFORE_CHECKING_FOR_UPDATES) {
                // SolidLogger::log('WOO_SLT_CodeAutoUpdate.php check_for_plugin_update() returning because we already checked in the last ' . self::SECONDS_TO_WAIT_BEFORE_CHECKING_FOR_UPDATES . ' seconds');
                /**
                 * @psalm-suppress MixedAssignment
                 */
                $cached_response = $this->get_cached_api_response();
                if ($cached_response) {
                    /**
                     * @psalm-suppress MixedPropertyFetch
                     */
                    if ($cached_response->new_version != WOO_SLT_VERSION) {
                        // SolidLogger::log('WOO_SLT_CodeAutoUpdate.php check_for_plugin_update() returning cached_response because new_version != WOO_SLT_VERSION');
                        /**
                         * @psalm-suppress MixedArrayAssignment
                         */
                        $checked_data->response[$this->plugin] = $cached_response;
                    } else {
                        return $checked_data;
                    }
                }
                return $checked_data;
            } else {
                // SolidLogger::log('WOO_SLT_CodeAutoUpdate.php check_for_plugin_update() updating last check time to now');
                update_option('solid_affiliate_slt_plugin-update_last_check', time());
            }
            /////////////////////////////////////////////////////////////////////////////////


            // Start checking for an update
            $request_uri = $this->api_url . '?' . http_build_query($request_string, '', '&');
            // SolidLogger::log('WOO_SLT_CodeAutoUpdate.php check_for_plugin_update() request_uri: ' . $request_uri);

            $data = wp_remote_get($request_uri);

            if ($data instanceof \WP_Error) {
                // SolidLogger::log('WOO_SLT_CodeAutoUpdate.php check_for_plugin_update() wp_remote_get returned WP_Error');
                return $checked_data;
            } else {
                if (!isset($data['response']) || !isset($data['response']['code']) || $data['response']['code'] != 200) {
                    // SolidLogger::log('WOO_SLT_CodeAutoUpdate.php check_for_plugin_update() wp_remote_get returned non-200 response code');
                    return $checked_data;
                }
            }

            $response_block = json_decode($data['body']);


            if (!is_array($response_block) || count($response_block) < 1) {
                SolidLogger::log('WOO_SLT_CodeAutoUpdate.php check_for_plugin_update() response_block is not an array or is empty');
                return $checked_data;
            }

            // If a newer version is available, the happy path response looks like this:
            // "[{"status":"success","status_code":"s401","message":{"new_version":"1.0.29","date":"2022-07-28","package":"https:\/\/dl.dropbox.com\/s\/cax4n6kbwviclse\/solid_affiliate-v1-0-29.zip","upgrade_notice":"Update available","author":"Solid Plugins","tested":"5.8.1","homepage":"https:\/\/solidaffiliate.com\/","icons":{"svg":"https:\/\/solidaffiliate.com\/wp-content\/uploads\/2021\/12\/icon-svg.svg","2x":"https:\/\/solidaffiliate.com\/wp-content\/uploads\/2021\/12\/Icon-2x.png","1x":"https:\/\/solidaffiliate.com\/wp-content\/uploads\/2021\/12\/Icon-1x.png"}}}]"
            // retrieve the last message within the $response_block


            /** * @psalm-suppress MixedAssignment */
            $response_block = $response_block[count($response_block) - 1];

            if (!is_object($response_block) || !isset($response_block->message)) {
                // SolidLogger::log('WOO_SLT_CodeAutoUpdate.php check_for_plugin_update() response_block is not an object or does not have a message property');
                return $checked_data;
            }

            /** * @psalm-suppress MixedAssignment */
            $response = $response_block->message;

            /** * @psalm-suppress RedundantCondition */
            if (is_object($response) && !empty($response)) // Feed the update data into WP updater
            {
                $response  =   $this->postprocess_response($response);
                // SolidLogger::log('WOO_SLT_CodeAutoUpdate.php check_for_plugin_update() response: ' . print_r($response, true));

                $this->set_cached_api_response($response);
                /**
                 * @psalm-suppress MixedArrayAssignment
                 */
                $checked_data->response[$this->plugin] = $response;
            } else {
                // SolidLogger::log('WOO_SLT_CodeAutoUpdate.php check_for_plugin_update() response is not an object or is empty');
            }

            // SolidLogger::log('WOO_SLT_CodeAutoUpdate.php check_for_plugin_update() returning checked_data: ' . print_r($checked_data, true));

            return $checked_data;
        }
    }

    /**
     * @param mixed $checked_data
     * @return mixed
     */
    public function check_for_plugin_update($checked_data)
    {
        if (!is_object($checked_data) ||  !isset($checked_data->response) || WOO_SLT_Licence::is_test_instance()) {
            return $checked_data;
        } else {
            $request_string = $this->prepare_request('plugin_update');

            /////////////////////////////////////////////////////////////////////////////////
            // Add a check to see if we already made a request anytime in the last 10 minutes
            // if we did, don't make another request and return $checked_data
            $last_check = (int)get_option('solid_affiliate_slt_plugin-update_last_check', 0);
            // TODO canceling this code to see if the caching is the issue
            // if (time() < $last_check + self::SECONDS_TO_WAIT_BEFORE_CHECKING_FOR_UPDATES) {
            //     /**
            //      * @psalm-suppress MixedAssignment
            //      */
            //     $cached_response = $this->get_cached_api_response();
            //     if ($cached_response) {
            //         /**
            //          * @psalm-suppress MixedPropertyFetch
            //          */
            //         if ($cached_response->new_version != WOO_SLT_VERSION) {
            //             /**
            //              * @psalm-suppress MixedArrayAssignment
            //              */
            //             $checked_data->response[$this->plugin] = $cached_response;
            //         } else {
            //             return $checked_data;
            //         }
            //     }
            //     return $checked_data;
            // } else {
            //     update_option('solid_affiliate_slt_plugin-update_last_check', time());
            // }
            /////////////////////////////////////////////////////////////////////////////////


            // Start checking for an update
            $request_uri = $this->api_url . '?' . http_build_query($request_string, '', '&');

            $data = wp_remote_get($request_uri);

            if ($data instanceof \WP_Error) {
                return $checked_data;
            } else {
                if (!isset($data['response']) || !isset($data['response']['code']) || $data['response']['code'] != 200) {
                    return $checked_data;
                }
            }

            $response_block = json_decode($data['body']);


            if (!is_array($response_block) || count($response_block) < 1) {
                return $checked_data;
            }

            // If a newer version is available, the happy path response looks like this:
            // "[{"status":"success","status_code":"s401","message":{"new_version":"1.0.29","date":"2022-07-28","package":"https:\/\/dl.dropbox.com\/s\/cax4n6kbwviclse\/analyticswp-v1-0-29.zip","upgrade_notice":"Update available","author":"Solid Plugins","tested":"5.8.1","homepage":"https:\/\/solidaffiliate.com\/","icons":{"svg":"https:\/\/solidaffiliate.com\/wp-content\/uploads\/2021\/12\/icon-svg.svg","2x":"https:\/\/solidaffiliate.com\/wp-content\/uploads\/2021\/12\/Icon-2x.png","1x":"https:\/\/solidaffiliate.com\/wp-content\/uploads\/2021\/12\/Icon-1x.png"}}}]"
            // retrieve the last message within the $response_block


            /** * @psalm-suppress MixedAssignment */
            $response_block = $response_block[count($response_block) - 1];

            if (!is_object($response_block) || !isset($response_block->message)) {
                return $checked_data;
            }

            /** * @psalm-suppress MixedAssignment */
            $response = $response_block->message;

            /** * @psalm-suppress RedundantCondition */
            if (is_object($response) && !empty($response) && isset($response->new_version) && !empty($response->new_version)) // Feed the update data into WP updater
            {
                $response  =   $this->postprocess_response($response);

                $this->set_cached_api_response($response);

                // check if $response has required fields
                // - new_version
                if (!isset($response->new_version)) {
                    $response->new_version = '';
                }

                /**
                 * @psalm-suppress MixedArrayAssignment
                 */
                $checked_data->response[$this->plugin] = $response;
            } else {
                // No update is available.
                $item = (object) array(
                    'id'            => 'solid_affiliate/plugin.php',
                    'slug'          => 'solid_affiliate',
                    'plugin'        => 'solid_affiliate/plugin.php',
                    'new_version'   => WOO_SLT_VERSION,
                    'url'           => '',
                    'package'       => '',
                    'icons'         => array(),
                    'banners'       => array(),
                    'banners_rtl'   => array(),
                    'tested'        => '',
                    'requires_php'  => '',
                    'compatibility' => new \stdClass(),
                );
                // Adding the "mock" item to the `no_update` property is required
                // for the enable/disable auto-updates links to correctly appear in UI.
                $checked_data->no_update['solid_affiliate/plugin.php'] = $item;

                // remove the plugin from the response array
                unset($checked_data->response[$this->plugin]);
            }

            return $checked_data;
        }
    }

    /**
     * @return mixed
     */
    public function get_cached_api_response()
    {
        /**
         * @psalm-suppress MixedAssignment
         */
        $cached_response = get_option('solid_affiliate_slt_plugin-update_response', false);
        if ($cached_response) {
            return $cached_response;
        } else {
            return false;
        }
    }

    /**
     * @param mixed $response
     * @return bool
     */
    public function set_cached_api_response($response)
    {
        return update_option('solid_affiliate_slt_plugin-update_response', $response);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function force_ping_solid_server()
    {
        $request_string = $this->prepare_request('plugin_update');
        /** @psalm-suppress DocblockTypeContradiction */
        if ($request_string === FALSE || WOO_SLT_Licence::is_test_instance()) {
            return;
        }

        /////////////////////////////////////////////////////////////////////////////////
        // Add a check to see if we already made a request anytime in the last 10 minutes
        // if we did, don't make another request and return $checked_data
        $last_check = (int)get_option('solid_affiliate_slt_plugin-update_last_check', 0);
        if (time() < $last_check + self::SECONDS_TO_WAIT_BEFORE_CHECKING_FOR_UPDATES) {
            return;
        } else {
            update_option('solid_affiliate_slt_plugin-update_last_check', time());
        }
        /////////////////////////////////////////////////////////////////////////////////
        $request_uri = $this->api_url . '?' . http_build_query($request_string, '', '&');
        wp_remote_get($request_uri);
        return;
    }



    /**
     * @param mixed $def
     * @param mixed $action
     * @param mixed $args
     * 
     * @return mixed
     */
    public function plugins_api_call($def, $action, $args)
    {
        if (!is_object($args) || !isset($args->slug) || $args->slug != $this->slug)
            return $def;


        //$args->package_type = $this->package_type;

        /**
         * @psalm-suppress MixedArgument
         * @psalm-suppress InvalidArgument
         */
        $request_string = $this->prepare_request($action, $args);
        /**
         * @psalm-suppress DocblockTypeContradiction
         */
        if ($request_string === FALSE)
            return new \WP_Error('plugins_api_failed', __('An error occour when try to identify the pluguin.', 'solid-affiliate') . '&lt;/p> &lt;p>&lt;a href=&quot;?&quot; onclick=&quot;document.location.reload(); return false;&quot;>' . __('Try again', 'solid-affiliate') . '&lt;/a>');;

        $request_uri = $this->api_url . '?' . http_build_query($request_string, '', '&');
        $data = wp_remote_get($request_uri);

        /**
         * @psalm-suppress MixedArrayAccess
         * @psalm-suppress UndefinedMethod
         */
        if (is_wp_error($data) || $data['response']['code'] != 200)
            /**
             * @psalm-suppress PossiblyInvalidMethodCall
             */
            return new \WP_Error('plugins_api_failed', __('An Unexpected HTTP Error occurred during the API request.', 'solid-affiliate') . '&lt;/p> &lt;p>&lt;a href=&quot;?&quot; onclick=&quot;document.location.reload(); return false;&quot;>' . __('Try again', 'solid-affiliate') . '&lt;/a>', $data->get_error_message());

        /**
         * @psalm-suppress MixedArgument
         * @psalm-suppress UndefinedMethod
         * @psalm-suppress MixedAssignment
         */
        $response_block = json_decode($data['body']);

        //retrieve the last message within the $response_block
        /**
         * @psalm-suppress MixedArrayAccess
         * @psalm-suppress MixedArgument
         * @psalm-suppress MixedAssignment
         */
        $response_block = $response_block[count($response_block) - 1];
        /**
         * @psalm-suppress MixedPropertyFetch
         * @psalm-suppress MixedAssignment
         */
        $response = $response_block->message;

        /**
         * @psalm-suppress RedundantCondition
         */
        if (is_object($response) && !empty($response)) // Feed the update data into WP updater
        {
            //include slug and plugin data
            $response  =   $this->postprocess_response($response);

            return $response;
        }
    }

    /**
     * @param string $action
     * @param array|null $args
     * 
     * @return array
     */
    public function prepare_request($action, $args = array())
    {
        global $wp_version;

        $license_data = WOO_SLT_Licence::get_license_data();
        $key = isset($license_data['key']) ? $license_data['key'] : '';

        $usage_statistics = UsageStatistics::create_for_this_environment();

        return array(
            'woo_sl_action'        => $action,
            'version'              => WOO_SLT_VERSION,
            'product_unique_id'    => WOO_SLT_PRODUCT_ID,
            'licence_key'          => $key,
            'domain'               => WOO_SLT_INSTANCE,

            'wp-version'           => $wp_version,
            'api_version'          => '1.1',

            // always send
            'php_version'          => $usage_statistics->php_version,
            'wp_version'           => $usage_statistics->wp_version,
            'woocommerce_version'  => $usage_statistics->woocommerce_version,
            // opt-in
            'is_paypal_integration_enabled' => $usage_statistics->is_paypal_integration_enabled,
            'count_paypal_payouts' => $usage_statistics->count_paypal_payouts,
            'count_total_affiliates' => $usage_statistics->count_total_affiliates,
            'count_total_referrals' => $usage_statistics->count_total_referrals,
            'count_total_creatives' => $usage_statistics->count_total_creatives,
            'count_total_affiliate_revenue' => $usage_statistics->count_total_affiliate_revenue,
            'count_total_affiliate_commission' => $usage_statistics->count_total_affiliate_commission,
            'currency_code' => $usage_statistics->currency_code,
            'search_query_count' => $usage_statistics->search_query_count,

            // keyless
            'is_on_keyless_free_trial' => $usage_statistics->is_on_keyless_free_trial,
            'keyless_free_trial_ends_at' => $usage_statistics->keyless_free_trial_ends_at,
            'keyless_id' => $usage_statistics->keyless_id,
        );
    }


    /**
     * @param object $response
     * @return object
     */
    private function postprocess_response($response)
    {
        //include slug and plugin data
        $response->slug    =   $this->slug;
        $response->plugin  =   $this->plugin;

        //if sections are being set
        if (isset($response->sections))
            $response->sections = (array)$response->sections;

        //if banners are being set
        if (isset($response->banners))
            $response->banners = (array)$response->banners;

        //if icons being set, convert to array
        if (isset($response->icons))
            $response->icons    =   (array)$response->icons;

        return $response;
    }
}
