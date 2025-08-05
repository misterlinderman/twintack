<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\ListTables\SharedListTableFunctions;
use SolidAffiliate\Lib\Utils;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\BulkPayout;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\Visit;

/**
 * 
 * @psalm-type OverviewData = array{
 *   affiliate_registrations: string, 
 *   bulk_payouts: string,
 *   commission_amount: string,
 *   conversions_from_coupons: int,
 *   converted_visits: string,
 *   net_revenue: string,
 *   payout_amount: string,
 *   payouts: string,
 *   referral_amount: string,
 *   referrals: int,
 *   visits: string,
 *   visits_conversion_rate: string
 * }
 */
class AdminReportsHelper
{
    ///////////////////////////////////////////////////////
    // Overview
    /**
     * Undocumented function
     *
     * @param string $custom_start_date
     * @param string $custom_end_date
     * @param int $custom_affiliate_id
     * 
     * @return array<OverviewData>
     */
    public static function overview_data($custom_start_date, $custom_end_date, $custom_affiliate_id)
    {
        $preset_date_ranges = [
            new PresetDateRangeParams(['preset_date_range' => 'all_time']),
            new PresetDateRangeParams(['preset_date_range' => 'this_year']),
            new PresetDateRangeParams(['preset_date_range' => 'last_year']),
            new PresetDateRangeParams(['preset_date_range' => 'custom', 'start_date' => $custom_start_date, 'end_date' => $custom_end_date]),
        ];

        $data_array = array_map(function ($preset_date_range) {
            $start_date = $preset_date_range->computed_start_date();
            $end_date = $preset_date_range->computed_end_date();
            $affiliate_id = 0;

            $data = self::_overview_data($start_date, $end_date, $affiliate_id);
            return $data;
        }, $preset_date_ranges);

        return $data_array;
    }



    /**
     * Undocumented function
     * 
     *
     * @param string $start_date
     * @param string $end_date
     * @param int $affiliate_id
     * 
     * @return OverviewData
     */
    public static function _overview_data($start_date, $end_date, $affiliate_id)
    {
        // For now assume just one column "All Time" and all affiliates
        $referrals_data = self::referrals_data($start_date, $end_date, $affiliate_id);
        $affiliates_data = self::affiliates_data($start_date, $end_date);
        $payouts_data = self::payouts_data($start_date, $end_date, $affiliate_id);
        $visits_data = self::visits_data($start_date, $end_date, $affiliate_id);
        $coupons_data = self::conversions_from_coupons_data($start_date, $end_date, $affiliate_id);

        $data = [
            // Revenue grouping
            'referrals' => $referrals_data['Number of Referrals'],
            'referral_amount' => $referrals_data['Referral Amount'],
            'commission_amount' => $referrals_data['Commission Amount'],
            'net_revenue' => $referrals_data['Net Revenue Amount'],

            // Affiliates grouping
            'affiliate_registrations' => $affiliates_data['Total Affiliate Registrations'],

            // Payouts Grouping
            'bulk_payouts' => $payouts_data['Total Bulk Payouts Count'],
            'payouts' => $payouts_data['Total Payouts Count'],
            'payout_amount' => $payouts_data['Total Payout Amount'],

            // Visits/Traffic Grouping
            'visits' => $visits_data['Total Visits Count'],
            'converted_visits' => $visits_data['Total Visits Resulting in Referral'],
            'visits_conversion_rate' => $visits_data['Conversion Rate'],

            // Coupons Grouping
            'conversions_from_coupons' => $coupons_data['Conversions from Coupons']
        ];


        return $data;
    }

    /**
     * TODO this code is insane. There's a test though in AdminReportsCest.php
     * 
     * @param array<OverviewData> $data
     * 
     * @return array<array<mixed>>
     */
    public static function transform_overview_data_for_table($data)
    {

        // Transform array of associative arrays into array of tuples
        $data = array_map(function ($data) {
            return self::_transform_assoc_array_into_list_of_tuples($data);
        }, $data);

        // Reduce the multiple 'columns' into data the table expects
        // get the initial column.
        $initial = array_map(function ($tuple) {
            $row_label = Formatters::humanize((string)$tuple[0]);
            return [$row_label];
        }, $data[0]);

        $data = array_reduce(
            $data,
            /**
             * @param array<array<mixed>> $output
             * @param array $array_of_tuples
             */
            function ($output, $array_of_tuples) {
                $i = 0;
                foreach ($output as $_tuple) {
                    $tuple = Validators::arr($array_of_tuples[$i]);
                    /** @psalm-suppress MixedAssignment */
                    $output[$i][] = $tuple[1];
                    $i++;
                }

                return $output;
            },
            $initial
        );


        return $data;
    }


    /**
     *
     * The data comes in as a [['field' => 'val1'], ['field' => 'val2']]
     * and we want [['field', 'val1'], ['field' => 'val2']]
     * 
     * @param array $assoc_array
     * 
     * @return array<array{array-key, mixed}>
     */
    public static function _transform_assoc_array_into_list_of_tuples($assoc_array)
    {
        $assoc_array = array_map(function ($key) use ($assoc_array) {
            /** @psalm-suppress MixedAssignment */
            $val = $assoc_array[$key];
            return [$key, $val];
        }, array_keys($assoc_array));

        return $assoc_array;
    }

    // end - Overview
    ///////////////////////////////////////////////////////

    ///////////////////////////////////////////////////////
    // Referrals

    /**
     * Undocumented function
     *
     * @param string $start_date
     * @param string $end_date
     * @param int|array $affiliate_id
     * @param string[] $statuses
     * 
     * @return array{'Number of Referrals': int, 'Commission Amount': string, 'Referral Amount': string, 'Net Revenue Amount': string}
     */
    public static function referrals_data($start_date, $end_date, $affiliate_id, $statuses = [])
    {
        $where_clause = [
            'created_at' => [
                'operator' => 'BETWEEN',
                'min' => $start_date,
                'max' => $end_date
            ]
        ];

        $affiliate_id = (array)$affiliate_id;
        if (!empty($affiliate_id) && $affiliate_id != [0]) {
            $where_clause['affiliate_id'] = ['operator' => 'IN', 'value' => $affiliate_id];
        }

        if (empty($statuses)) {
            $status_query = [
                'status' => ['operator' => 'IN', 'value' => [Referral::STATUS_PAID, Referral::STATUS_UNPAID]]
            ];

            $where_clause = array_merge($where_clause, $status_query);
        }

        $total_referrals = Referral::builder()
            ->select('*')
            ->where($where_clause);

        $total_referrals_count = $total_referrals->count();

        $total_referral_amount = array_sum(array_map(function ($obj) {
            /** @var Referral $obj */
            return (float)$obj->order_amount;
        }, $total_referrals->get()));

        $total_commission_amount = array_sum(array_map(function ($obj) {
            /** @var Referral $obj */
            return (float)$obj->commission_amount;
        }, $total_referrals->get()));

        $total_profit_amount = $total_referral_amount - $total_commission_amount;

        return [
            'Referral Amount' => Formatters::money($total_referral_amount),
            'Number of Referrals' => $total_referrals_count,
            'Commission Amount' => Formatters::money($total_commission_amount),
            'Net Revenue Amount' => Formatters::money($total_profit_amount)
        ];
    }
    // end - Referrals
    ///////////////////////////////////////////////////////

    ///////////////////////////////////////////////////////
    // Affiliates

    /**
     * Undocumented function
     * 
     * @param string $start_date
     * @param string $end_date
     * 
     * @return array
     * @psalm-return array{'Total Affiliate Registrations': string}
     */
    public static function affiliates_data($start_date, $end_date)
    {
        $where_clause = [
            'created_at' => [
                'operator' => 'BETWEEN',
                'min' => $start_date,
                'max' => $end_date
            ]
        ];

        $total_affiliates = Affiliate::builder()
            ->select('*')
            ->where($where_clause);

        $total_affiliates_count = $total_affiliates->count();


        return [
            'Total Affiliate Registrations' => (string)$total_affiliates_count,
        ];
    }
    // end - Affiliates
    ///////////////////////////////////////////////////////


    ///////////////////////////////////////////////////////
    // Payouts

    /**
     * Undocumented function
     * 
     * @param string $start_date
     * @param string $end_date
     * @param int $affiliate_id
     * 
     * @return array
     * @psalm-return array{'Total Bulk Payouts Count': string, 'Average Payout Amount': string, 'Total Payout Amount': string, 'Total Payouts Count': string}
     * 
     */
    public static function payouts_data($start_date, $end_date, $affiliate_id)
    {
        $shared_where_clause = [
            'created_at' => [
                'operator' => 'BETWEEN',
                'min' => $start_date,
                'max' => $end_date
            ],
        ];

        if (!empty($affiliate_id)) {
            $shared_where_clause['affiliate_id'] = $affiliate_id;
        }

        $payouts_where_clause = array_merge($shared_where_clause, [
            'status' => Payout::STATUS_PAID
        ]);
        $total_payouts = Payout::builder()
            ->select('*')
            ->where($payouts_where_clause);

        $total_payouts_count = $total_payouts->count();

        $total_payouts_amount = array_sum(array_map(function ($obj) {
            /** @var Payout $obj */
            return (int)$obj->amount;
        }, $total_payouts->get()));

        ///////////////////////////////////////////////
        // BulkPayout
        $total_bulk_payouts_count = BulkPayout::count($shared_where_clause);
        ///////////////////////////////////////////////


        return [
            'Total Bulk Payouts Count' => (string)$total_bulk_payouts_count,
            'Total Payouts Count' => (string)$total_payouts_count,
            'Total Payout Amount' => Formatters::money($total_payouts_amount),
            'Average Payout Amount' => Formatters::money($total_payouts_amount / max($total_payouts_count, 1))
        ];
    }
    // end - Payouts
    ///////////////////////////////////////////////////////


    ////////////////////////////////////////////////////////
    // Visits

    /**
     * Undocumented function
     * 
     * @param string $start_date
     * @param string $end_date
     * @param int $affiliate_id
     * 
     * @return array{'Conversion Rate': string, 'Total Visits Count': string, 'Total Visits Resulting in Referral': string}
     */
    public static function visits_data($start_date, $end_date, $affiliate_id)
    {
        $where_clause = [
            'created_at' => [
                'operator' => 'BETWEEN',
                'min' => $start_date,
                'max' => $end_date
            ]
        ];

        if (!empty($affiliate_id)) {
            $where_clause['affiliate_id'] = $affiliate_id;
        }

        $total_visits = Visit::builder()
            ->select('*')
            ->where($where_clause);

        $total_visits_count = $total_visits->count();
        $total_converted_visits = $total_visits->where(['referral_id' => ['operator' => '>', 'value' => 0]])->count();

        $conversion_rate = round(($total_converted_visits / max($total_visits_count, 1) * 100.00), 2);

        return [
            'Total Visits Count' => (string)$total_visits_count,
            'Total Visits Resulting in Referral' => (string)$total_converted_visits,
            'Conversion Rate' => Formatters::percentage($conversion_rate),
        ];
    }
    // end - Visits
    ///////////////////////////////////////////////////////


    ////////////////////////////////////////////////////////
    // Coupons

    /**
     * Gets data for the "Coupons" section
     * 
     * @param string $start_date
     * @param string $end_date
     * @param int $affiliate_id
     * 
     * @return array<array{'Coupon': string, 'Affiliate': string, 'Referrals': int}>
     */
    public static function coupons_data($start_date, $end_date, $affiliate_id)
    {
        // Find all coupons that are associated with an Affiliate 
        $coupon_posts = WooCommerceIntegration::get_all_affiliate_coupons();
        // if affiliate_id is set to non zero, filter out coupons that are not associated with the affiliate

        if (!empty($affiliate_id)) {
            $coupon_posts = array_filter($coupon_posts, function ($obj) use ($affiliate_id) {
                /** @var \WP_Post $obj */
                $coupon_id = $obj->ID;
                // use meta key coupon_affiliate_id_key
                $coupon_affiliate_id = (int)get_post_meta($coupon_id, WooCommerceIntegration::MISC['coupon_affiliate_id_key'], true);
                return $coupon_affiliate_id === $affiliate_id;
            });
        }

        $rows = array_map(function ($obj) {
            /** @var \WP_Post $obj */
            $coupon_id = $obj->ID;
            // use meta key coupon_affiliate_id_key
            $affiliate_id = (int)get_post_meta($coupon_id, WooCommerceIntegration::MISC['coupon_affiliate_id_key'], true);

            $where_clause = [
                'coupon_id' => $coupon_id,
                'affiliate_id' => $affiliate_id,
                // 'created_at' => [
                //     'operator' => 'BETWEEN',
                // 'min' => $start_date,
                // 'max' => $end_date
                // ],
                'status' => ['operator' => 'IN', 'value' => Referral::STATUSES_PAID_AND_UNPAID],
            ];


            $referrals_count = Referral::count($where_clause);

            $coupon_column = Links::render($obj, $obj->post_title);
            $affiliate_column = SharedListTableFunctions::affiliate_column($affiliate_id, false);

            return [
                'Coupon' => $coupon_column,
                'Affiliate' => $affiliate_column,
                'Referrals' => $referrals_count
            ];
        }, $coupon_posts);

        return $rows;
    }


    /**
     * Undocumented function
     * 
     * @param string $start_date
     * @param string $end_date
     * @param int $affiliate_id
     * 
     * @return array{'Conversions from Coupons': int}
     */
    public static function conversions_from_coupons_data($start_date, $end_date, $affiliate_id)
    {
        $where_clause = [
            'created_at' => [
                'operator' => 'BETWEEN',
                'min' => $start_date,
                'max' => $end_date
            ],
            'status' => ['operator' => 'IN', 'value' => Referral::STATUSES_PAID_AND_UNPAID],
        ];

        if (!empty($affiliate_id)) {
            $where_clause['affiliate_id'] = $affiliate_id;
        }

        $total_referrals = Referral::builder()
            ->select('*')
            ->where($where_clause);

        $total_converted_coupons = $total_referrals->where(['coupon_id' => ['operator' => '>', 'value' => 0]])->count();

        return [
            'Conversions from Coupons' => $total_converted_coupons,
        ];
    }
    // end - Coupons
    ///////////////////////////////////////////////////////




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
            $list = [$row->affiliate_id, $row->commission_amount, $row->description, $row->created_at];
            return $list;
        }, $r);

        return $r;
    }

    /**
     * @return list<array{int, float, int, int}>
     */
    public static function most_valuable_affiliates()
    {
        $affiliates = Affiliate::all();
        $tuples = array_map(function ($affiliate) {
            $id = $affiliate->id;
            return [$id, Affiliate::paid_earnings($affiliate), count($affiliate->referrals()), count($affiliate->visits())];
        }, $affiliates);

        $compare_function =
            /** 
             * @param array $a
             * @param array $b
             * @return int
             */
            function ($a, $b) {
                if ($a[1] == $b[1]) {
                    return 0;
                }
                return ($a[1] > $b[1]) ? -1 : 1;
            };

        usort($tuples, $compare_function);

        return array_slice($tuples, 0, 10);
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
            /** @var array{int, string, string, string} */
            $list = [$row->id, $row->status, $row->created_at, $view_link];
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
            'created_at' => [
                'operator' => '>=',
                'value' => Utils::sql_time('30 days ago') //$myDate //strtotime('-1 day')
            ]
        ]);

        $last_24_hours = Referral::count([
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
     * @return array<array{float, float, float}>
     */
    public static function total_attributable_revenue()
    {
        $total = Referral::count();
        $r = Referral::builder()->select('order_amount')->get();
        $total = array_reduce($r, function ($sum, $current) {
            return $sum + $current->order_amount;
        }, 0.0);

        $r = Referral::builder()->select('order_amount')->where([
            'created_at' => [
                'operator' => '>=',
                'value' => Utils::sql_time('30 days ago') //$myDate //strtotime('-1 day')
            ]
        ])->get();
        $last_30_days = array_reduce($r, function ($sum, $current) {
            return $sum + $current->order_amount;
        }, 0.0);

        $r = Referral::builder()->select('order_amount')->where([
            'created_at' => [
                'operator' => '>=',
                'value' => Utils::sql_time('24 hours ago') //$myDate //strtotime('-1 day')
            ]
        ])->get();
        $last_24_hours = array_reduce($r, function ($sum, $current) {
            return $sum + $current->order_amount;
        }, 0.0);

        $tuples = [[$total, $last_30_days, $last_24_hours]];

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
