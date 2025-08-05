<?php

namespace SolidAffiliate\Lib\Integrations;

use GuzzleHttp\Promise\Create;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Creative;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Visit;

class ZapierIntegration
{
    /**
     * @return void
     */
    public static function register_hooks()
    {
        // $models = ['Affiliate', 'Referral', 'Creative', 'Visit', 'Payout'];
        // $actions = ['insert', 'update', 'delete'];
        // add_action('solid_affiliate/Affiliate/insert', [self::class, 'handle_affiliate_insert']);
    }

    /**
     * @param int $affiliate_id
     *
     * @return void
     */
    public static function handle_affiliate_insert($affiliate_id)
    {
        if (Affiliate::find($affiliate_id)) {
            // Send the affiliate data to Zapier
            // $affiliate_id = Affiliate::find(($affiliate_id));
        } else {
            // log an error 
            // log('Affiliate not found');
        }
    }

    /**
     * Undocumented function
     *
     * @param 'insert'|'update'|'delete' $event_type
     * @param Affiliate|Referral|Creative|Visit|Payout $model_class
     * @param int $id
     * @return void
     */
    public static function handle_resource_event($event_type, $model_class, $id)
    {
        $maybe_instance = $model_class::find($id);

        if ($maybe_instance instanceof $model_class) {
            // handle event_type

        } else {
            // handle no instance found
        }
    }
}
