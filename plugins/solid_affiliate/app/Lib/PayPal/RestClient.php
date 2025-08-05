<?php

namespace SolidAffiliate\Lib\PayPal;

// https://github.com/paypal/Payouts-PHP-SDK
use PaypalPayoutsSDK\Core\PayPalHttpClient;
use PaypalPayoutsSDK\Core\ProductionEnvironment;
use PaypalPayoutsSDK\Core\SandboxEnvironment;
use SolidAffiliate\Lib\Settings;

class RestClient
{
    /**
     * @return \PaypalPayoutsSDK\Core\PayPalHttpClient
     */
    public static function client()
    {
        return new PayPalHttpClient(self::environment());
    }

    /**
     * @return \PaypalPayoutsSDK\Core\ProductionEnvironment|\PaypalPayoutsSDK\Core\SandboxEnvironment
     */
    private static function environment()
    {


        // TODO get this from settings
        $use_live = boolval(Settings::get(Settings::KEY_INTEGRATIONS_PAYPAL_USE_LIVE));
        if ($use_live) {
            /** @var array<Settings::KEY_*, string>[] */
            $creds = Settings::get_many([Settings::KEY_INTEGRATIONS_PAYPAL_CLIENT_ID_LIVE, Settings::KEY_INTEGRATIONS_PAYPAL_SECRET_LIVE]);

            $client_id = $creds[Settings::KEY_INTEGRATIONS_PAYPAL_CLIENT_ID_LIVE];
            $secret = $creds[Settings::KEY_INTEGRATIONS_PAYPAL_SECRET_LIVE];

            return new ProductionEnvironment(
                $client_id,
                $secret
            );
        } else {
            /** @var array<Settings::KEY_*, string>[] */
            $creds = Settings::get_many([Settings::KEY_INTEGRATIONS_PAYPAL_CLIENT_ID_SANDBOX, Settings::KEY_INTEGRATIONS_PAYPAL_SECRET_SANDBOX]);

            $client_id = $creds[Settings::KEY_INTEGRATIONS_PAYPAL_CLIENT_ID_SANDBOX];
            $secret = $creds[Settings::KEY_INTEGRATIONS_PAYPAL_SECRET_SANDBOX];

            return new SandboxEnvironment(
                $client_id,
                $secret
            );
        }
    }
}
