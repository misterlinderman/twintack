<?php

namespace SolidAffiliate\Lib\VO;

use SolidAffiliate\Lib\AdminDashboardHelper;
use SolidAffiliate\Lib\AdminReportsHelper;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\Integrations\WooCommerceSubscriptionsIntegration;
use SolidAffiliate\Lib\License;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\SolidSearch;
use SolidAffiliate\Lib\WooSoftwareLicense\WOO_SLT;
use SolidAffiliate\Lib\WooSoftwareLicense\WOO_SLT_Licence;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Creative;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\Referral;

/** 
 * UsageStatistics
 * A data structure representing a snapshot of usage statistics for an instance of Solid Affiliate.
 * The core components of this structure are:
 *   [] Identifier (who and when)
 *   [] Environment
 *   [] Solid Affiliate Settings 
 *   [] Usage Stats
 * 
 * @psalm-type UsageStatisticsType = array{
 *  domain: string,
 *  license_key: string,
 *  php_version: string,
 *  wp_version: string,
 *  woocommerce_version: string,
 *  solid_affiliate_version: string,
 *  is_paypal_integration_enabled: bool|string,
 *  count_paypal_payouts: int|string,
 *  count_total_affiliates: int|string,
 *  count_total_referrals: int|string,
 *  count_total_creatives: int|string,
 *  count_total_affiliate_revenue: string,
 *  count_total_affiliate_commission: string,
 *  currency_code: string,
 *  is_on_keyless_free_trial: string,
 *  keyless_free_trial_ends_at: string,
 *  keyless_id: string,
 *  search_query_count: int|string
 *  
 * } 
 */
class UsageStatistics
{
    /** @var UsageStatisticsType $data */
    public $data;

    /** @var string */
    public $domain;

    /** @var string */
    public $license_key;

    /** @var string */
    public $php_version;

    /** @var string */
    public $wp_version;

    /** @var string */
    public $woocommerce_version;

    /** @var string */
    public $solid_affiliate_version;

    /** @var bool|string */
    public $is_paypal_integration_enabled;

    /** @var int|string */
    public $count_paypal_payouts;

    /** @var int|string */
    public $count_total_affiliates;

    /** @var int|string */
    public $count_total_referrals;

    /** @var int|string */
    public $count_total_creatives;

    /** @var string */
    public $count_total_affiliate_revenue;

    /** @var string */
    public $count_total_affiliate_commission;

    /** @var string */
    public $currency_code;

    /** @var string */
    public $is_on_keyless_free_trial;

    /** @var string */
    public $keyless_free_trial_ends_at;

    /** @var string */
    public $keyless_id;

    /** @var int|string */
    public $search_query_count;

    /** @param UsageStatisticsType $data */
    public function __construct($data)
    {
        $this->data = $data;
        $this->domain = $data['domain'];
        $this->license_key = $data['license_key'];
        $this->php_version = $data['php_version'];
        $this->wp_version = $data['wp_version'];
        $this->woocommerce_version = $data['woocommerce_version'];
        // $this->woocommerce_subscriptions_version = $data['woocommerce_subscriptions_version'];
        $this->solid_affiliate_version = $data['solid_affiliate_version'];

        // Keyless
        $this->is_on_keyless_free_trial = $data['is_on_keyless_free_trial'];
        $this->keyless_free_trial_ends_at = $data['keyless_free_trial_ends_at'];
        $this->keyless_id = $data['keyless_id'];

        // Opt-In
        $this->is_paypal_integration_enabled = $data['is_paypal_integration_enabled'];
        $this->count_paypal_payouts = $data['count_paypal_payouts'];
        $this->count_total_affiliates = $data['count_total_affiliates'];
        $this->count_total_referrals = $data['count_total_referrals'];
        $this->count_total_creatives = $data['count_total_creatives'];
        $this->count_total_affiliate_revenue = $data['count_total_affiliate_revenue'];
        $this->count_total_affiliate_commission = $data['count_total_affiliate_commission'];
        $this->currency_code = $data['currency_code'];
        $this->search_query_count = $data['search_query_count'];
    }

    /**
     * Creates an instance of UsageStatistics from the current WordPress environment.
     *
     * @return UsageStatistics
     */
    public static function create_for_this_environment()
    {
        try {
            return self::_create_for_this_environment();
        } catch (\Error $e) {
            return self::empty_usage_statistics();
        } catch (\Exception $e) {
            return self::empty_usage_statistics();
        }
    }

    /**
     * Creates an instance of UsageStatistics from the current WordPress environment.
     *
     * @return UsageStatistics
     */
    private static function _create_for_this_environment()
    {
        $license_data = WOO_SLT_Licence::get_license_data();
        if ($license_data === false) {
            $license_key = '';
        } else {
            $license_key = $license_data['key'];
        }

        $count_paypal_payouts = Payout::count([
            'payout_method' => Payout::PAYOUT_METHOD_PAYPAL
        ]);

        $r = Referral::builder()->select('commission_amount, order_amount')->where([
            'status' => [
                'operator' => 'IN',
                'value' => Referral::STATUSES_PAID_AND_UNPAID
            ]
        ])->get();

        $count_total_referrals = count($r);

        $count_total_affiliate_revenue = (string)array_reduce(
            $r,
            /** 
             * @param float $sum
             * @param object $current
             * 
             * @return float
             */
            function ($sum, $current) {
                return $sum + (float)$current->order_amount;
            },
            0.0
        );

        $count_total_affiliate_commission = (string)array_reduce(
            $r,
            /** 
             * @param float $sum
             * @param object $current
             * 
             * @return float
             */
            function ($sum, $current) {
                return $sum + (float)$current->commission_amount;
            },
            0.0
        );

        $currency_code = WooCommerceIntegration::get_current_currency();

        $is_paypal_integration_enabled = (bool)Settings::is_paypal_integration_configured_and_enabled();
        $count_total_affiliates = Affiliate::count();
        $count_total_creatives = Creative::count();

        $search_query_count = SolidSearch::get_search_query_count();

        if (!(bool)Settings::get(Settings::KEY_IS_BASIC_USAGE_STATISTICS_ENABLED)) {
            $is_paypal_integration_enabled = '-';
            $count_paypal_payouts = '-';
            $count_total_affiliates = '-';
            $count_total_referrals = '-';
            $count_total_creatives = '-';
            $count_total_affiliate_revenue = '-';
            $count_total_affiliate_commission = '-';
            $currency_code = '-';
            $search_query_count = '-';
        };

        return new UsageStatistics([
            'domain' => (string)WOO_SLT_INSTANCE,
            'license_key' => $license_key,
            'php_version' => (string)phpversion(),
            'wp_version' => (string)get_bloginfo('version'),
            'woocommerce_version' => (string)WC()->version,
            'solid_affiliate_version' => (string)WOO_SLT_VERSION,

            // Keyless
            'is_on_keyless_free_trial' => (string)License::is_on_keyless_free_trial(),
            'keyless_free_trial_ends_at' => (string)License::get_keyless_free_trial_end_timestamp(),
            'keyless_id' => (string)License::get_keyless_id(),

            // Additional Opt-In 
            'is_paypal_integration_enabled' => $is_paypal_integration_enabled,
            'count_paypal_payouts' => $count_paypal_payouts,
            'count_total_affiliates' => $count_total_affiliates,
            'count_total_referrals' => $count_total_referrals,
            'count_total_creatives' => $count_total_creatives,
            'count_total_affiliate_revenue' => $count_total_affiliate_revenue,
            'count_total_affiliate_commission' => $count_total_affiliate_commission,
            'currency_code' => $currency_code,
            'search_query_count' => $search_query_count
        ]);
    }

    /**
     * Creates an instance of UsageStatistics with all values empty.
     *
     * @return UsageStatistics
     */
    private static function empty_usage_statistics()
    {
        return new UsageStatistics([
            'domain' => '-',
            'license_key' => '-',
            'php_version' => '-',
            'wp_version' => '-',
            'woocommerce_version' => '-',
            'solid_affiliate_version' => '-',
            'is_paypal_integration_enabled' => '-',
            'is_on_keyless_free_trial' => '-',
            'keyless_free_trial_ends_at' => '-',
            'keyless_id' => '-',
            'count_paypal_payouts' => '-',
            'count_total_affiliates' => '-',
            'count_total_referrals' => '-',
            'count_total_creatives' => '-',
            'count_total_affiliate_revenue' => '-',
            'count_total_affiliate_commission' => '-',
            'currency_code' => '-',
            'search_query_count' => '-'
        ]);
    }

}
