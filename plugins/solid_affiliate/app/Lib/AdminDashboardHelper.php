<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Lib\ListTables\SharedListTableFunctions;
use SolidAffiliate\Lib\Utils;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Visit;
use Action_Scheduler\WP_CLI\ProgressBar;
use SolidAffiliate\Models\Payout;

/**
 * @psalm-import-type AdminDashboardV2Interface from \SolidAffiliate\Views\Admin\AdminDashboard\V2\RootView
 * 
 * @psalm-import-type AdminNotificationData from \SolidAffiliate\Views\Admin\AdminDashboard\V2\RootView
 * @psalm-import-type InsightsData from \SolidAffiliate\Views\Admin\AdminDashboard\V2\RootView
 * @psalm-import-type FeedData from \SolidAffiliate\Lib\AdminFeed
 * @psalm-import-type SmartTipData from \SolidAffiliate\Views\Admin\AdminDashboard\V2\RootView
 * 
 */
class AdminDashboardHelper
{
    /**
     * @return array<array-key, array{0: string, 1: int, 2: string, 3: string}>
     */
    public static function recent_referrals()
    {
        /** 
         * @var array<object> 
         * @psalm-suppress all
         **/
        $r = Referral::builder()
            ->select('affiliate_id, commission_amount, description, created_at')
            ->order_by('created_at', 'DESC')
            ->limit(5)
            ->get();

        $r = array_map(function ($row) {

            /** @var array{0: string, 1: int, 2: string, 3: string} */
            $list = [$row->affiliate_id, Formatters::money($row->commission_amount), $row->description, $row->created_at];
            return $list;
        }, $r);

        return $r;
    }

    /**
     * TODO this function does not actually use the $maybe_preset_date_range_params
     * 
     * @psalm-suppress MixedMethodCall
     * 
     * @param PresetDateRangeParams|null $maybe_preset_date_range_params
     * 
     * @return array<array-key, array{string, string, string, string, int, int}>
     */
    public static function most_valuable_affiliates($maybe_preset_date_range_params = null)
    {
        // TODO We are still Psalm Ignoring the entire MikesDataModelTrait file in our psalm.xml which is why we are suppressing all here in this function
        $default_where = [
            'status' => [
                'operator' => 'IN',
                'value' => Referral::STATUSES_PAID_AND_UNPAID
            ]
        ];
        $where_clause = Utils::merge_maybe_preset_date_range_params_into_where_clause($default_where, $maybe_preset_date_range_params);
        /**
         * @var Referral[] $query_results
         * @psalm-suppress UndefinedDocblockClass
         */
        $query_results = Referral::builder()
            ->select('affiliate_id, status, SUM(commission_amount) as total_commission_amount, count(*) as total_referrals')
            ->where($where_clause)
            ->group_by('affiliate_id')
            ->order_by('total_commission_amount', 'DESC')
            ->limit(10)
            ->get();

        /**
         * @psalm-suppress UndefinedMagicPropertyFetch
         */
        $tuples = array_map(function ($row) use ($maybe_preset_date_range_params) {
            /** @var Affiliate */
            $affiliate = Affiliate::find($row->affiliate_id);

            return [
                SharedListTableFunctions::affiliate_column($row->affiliate_id, true),
                (float)$row->total_commission_amount,
                Affiliate::paid_earnings($affiliate, $maybe_preset_date_range_params),
                Affiliate::unpaid_earnings($affiliate, $maybe_preset_date_range_params),
                (int)$row->total_referrals,
                count($affiliate->visits($maybe_preset_date_range_params))
            ];
        }, $query_results);

        $formatted_tuples = array_map(
            /** @param array{string, float, float, float, int, int} $t */
            function ($t) {
                return [$t[0], Formatters::money($t[1]), Formatters::money($t[2]), Formatters::money($t[3]), $t[4], $t[5]];
            },
            $tuples
        );

        return $formatted_tuples;
    }


    /**
     * @return array<array{int, string, string, string}>
     */
    public static function new_affiliates()
    {
        /** 
         * @var array<object> 
         * @psalm-suppress all
         **/
        $r = Affiliate::builder()
            ->select('id, status, created_at')
            ->order_by('created_at', 'DESC')
            ->limit(5)
            ->get();

        $tuples = array_map(function ($row) {
            $edit_affiliate_url = URLs::edit(Affiliate::class, (int)$row->id);

            $view_link = sprintf('<a href="%s" data-id="%d" title="%s">%s</a>', $edit_affiliate_url, (int)$row->id, __('Edit this item', 'solid-affiliate'), __('Edit Affiliate', 'solid-affiliate'));
            $status_tooltip = Formatters::status_with_tooltip((string)$row->status, Affiliate::class, 'admin');
            /** @var array{int, string, string, string} */
            $list = [$row->id, $status_tooltip, $row->created_at, $view_link];
            return $list;
        }, $r);

        return $tuples;
    }

    /**
     * @return array<array{int, int, int}>
     */
    public static function total_referrals()
    {
        $total = Referral::count();

        $last_30_days = Referral::count([
            'status' => [
                'operator' => 'IN',
                'value' => Referral::STATUSES_PAID_AND_UNPAID
            ],
            'created_at' => [
                'operator' => '>=',
                'value' => Utils::sql_time('30 days ago') //$myDate //strtotime('-1 day')
            ]
        ]);

        $last_24_hours = Referral::count([
            'status' => [
                'operator' => 'IN',
                'value' => Referral::STATUSES_PAID_AND_UNPAID
            ],
            'created_at' => [
                'operator' => '>=',
                'value' => Utils::sql_time('24 hours ago') //$myDate //strtotime('-1 day')
            ]
        ]);

        $tuples = [[$total, $last_30_days, $last_24_hours]];

        return $tuples;
    }

    /**
     * @return array<array{int, int, int}>
     */
    public static function total_affiliates()
    {
        $total = Affiliate::count();

        $last_30_days = Affiliate::count([
            'created_at' => [
                'operator' => '>=',
                'value' => Utils::sql_time('30 days ago') //$myDate //strtotime('-1 day')
            ]
        ]);

        $last_24_hours = Affiliate::count([
            'created_at' => [
                'operator' => '>=',
                'value' => Utils::sql_time('24 hours ago') //$myDate //strtotime('-1 day')
            ]
        ]);

        $tuples = [[$total, $last_30_days, $last_24_hours]];

        return $tuples;
    }

    /**
     * @psalm-suppress all
     * @param bool|null $formatted
     * @return array<array{float, float, float}>
     */
    public static function total_attributable_revenue($formatted = true)
    {
        $total = Referral::count();
        $r = Referral::builder()->select('order_amount')->where([
            'status' => [
                'operator' => 'IN',
                'value' => Referral::STATUSES_PAID_AND_UNPAID
            ]
        ])->get();
        $total = array_reduce($r, function ($sum, $current) {
            return $sum + $current->order_amount;
        }, 0.0);

        $r = Referral::builder()->select('order_amount')->where([
            'status' => [
                'operator' => 'IN',
                'value' => Referral::STATUSES_PAID_AND_UNPAID
            ],
            'created_at' => [
                'operator' => '>=',
                'value' => Utils::sql_time('30 days ago') //$myDate //strtotime('-1 day')
            ]
        ])->get();
        $last_30_days = array_reduce($r, function ($sum, $current) {
            return $sum + $current->order_amount;
        }, 0.0);

        $r = Referral::builder()->select('order_amount')->where([
            'status' => [
                'operator' => 'IN',
                'value' => Referral::STATUSES_PAID_AND_UNPAID
            ],
            'created_at' => [
                'operator' => '>=',
                'value' => Utils::sql_time('24 hours ago') //$myDate //strtotime('-1 day')
            ]
        ])->get();
        $last_24_hours = array_reduce($r, function ($sum, $current) {
            return $sum + $current->order_amount;
        }, 0.0);

        if ($formatted) {
            $tuples = [[Formatters::money($total), Formatters::money($last_30_days), Formatters::money($last_24_hours)]];
        } else {
            $tuples = [[(float)$total, (float)$last_30_days, (float)$last_24_hours]];
        }

        return $tuples;
    }

    /**
     * @return array<array{int, int, int}>
     */
    public static function total_visits()
    {
        $total = Visit::count();

        $last_30_days = Visit::count([
            'created_at' => [
                'operator' => '>=',
                'value' => Utils::sql_time('30 days ago') //$myDate //strtotime('-1 day')
            ]
        ]);

        $last_24_hours = Visit::count([
            'created_at' => [
                'operator' => '>=',
                'value' => Utils::sql_time('24 hours ago') //$myDate //strtotime('-1 day')
            ]
        ]);

        $tuples = [[$total, $last_30_days, $last_24_hours]];

        return $tuples;
    }
}
