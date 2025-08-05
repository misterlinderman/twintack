<?php

namespace SolidAffiliate\Lib;

use Exception;
use SolidAffiliate\Addons\Core;
use SolidAffiliate\Addons\StoreCredit\Addon;
use SolidAffiliate\Controllers\PayAffiliatesController;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Creative;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\StoreCreditTransaction;
use SolidAffiliate\Models\Visit;

class SeedDatabase
{
    /**
     * @return mixed
     */
    public static function run()
    {
        $user_password = 'password';
        $number_of_visits_referrals_and_payouts_per_affiliate = 20;

        # Admin User
        $admin_user_id_or_error = wp_create_user(RandomData::name('.'), $user_password, RandomData::email('wpuser.com'));
        if (!is_int($admin_user_id_or_error)) {
            echo ('Error with wp_create_user');
            echo ($admin_user_id_or_error->get_error_message());
            die();
        }


        # Affiliate Users
        $user_id_or_error = wp_create_user(RandomData::name('.'), $user_password, RandomData::email('wpuser.com'));
        if (!is_int($user_id_or_error)) {
            echo ('Error with wp_create_user');
            echo ($user_id_or_error->get_error_message());
            die();
        }

        # Affiliates 
        $affiliate = Affiliate::random(['user_id' => $user_id_or_error]);

        # Visits 
        for ($i = 0; $i < $number_of_visits_referrals_and_payouts_per_affiliate; $i++) {
            // code to repeat here
            $visit = Visit::random(['affiliate_id' => $affiliate->id]);

            # Referrals 
            $random_date = RandomData::sql_date();
            $referral = Referral::random(
                [
                    'affiliate_id' => $affiliate->id,
                    'visit_id' => $visit->id,
                    'coupon_id' => 0
                ],
                ['payout_id' => 0, 'created_at' => $random_date]
            );

            Visit::updateInstance($visit, ['referral_id' => $referral->id]);

            # Payouts 
            // $payout = Payout::random(
            //     [
            //         'affiliate_id' => $affiliate->id,
            //         'created_by_user_id' => $admin_user_id_or_error
            //     ]
            // );

            // Store Credit Transactions
            if (Core::is_addon_enabled(Addon::ADDON_SLUG)) {
                StoreCreditTransaction::random(
                    [
                        'affiliate_id' => $affiliate->id,
                        'created_by_user_id' => $admin_user_id_or_error
                    ]
                );
            }
        }


        # Creatives
        Creative::random();

        return true;
    }
}
