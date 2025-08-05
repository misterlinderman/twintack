<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Visit;

use function PHPUnit\Framework\isNull;

class ChartData
{

    /**
     * Undocumented function
     * @param 'daily'|'monthly' $time_group
     * @param PresetDateRangeParams|null $date_range
     * @param int[] $affiliate_ids
     *
     * @return array
     */
    public static function referrals_data($time_group = 'daily', $date_range = null, $affiliate_ids = [])
    {
        $date_format = self::_sql_date_format($time_group);

        if (is_null($date_range)) {
            $date_range = new PresetDateRangeParams(['preset_date_range' => 'this_year']);
        }

        $where_clause = [
            'created_at' => [
                'operator' => 'BETWEEN',
                'min' => $date_range->computed_start_date(),
                'max' => $date_range->computed_end_date()
            ]
        ];

        if (!empty($affiliate_ids) && $affiliate_ids != [0]) {
            $where_clause['affiliate_id'] = ['operator' => 'IN', 'value' => $affiliate_ids];
        }

        /** 
         * @var array $data
         * @psalm-suppress UndefinedDocblockClass 
         **/
        $data = Referral::builder()
            ->select("DATE_FORMAT(created_at, {$date_format}) as date, count(ID) as count, SUM(order_amount) as total_order_amount, SUM(commission_amount) as total_commission_amount")
            ->where(['status' => ['value' => 'rejected', 'operator' => '<>']])
            ->where($where_clause)
            ->group_by('date')
            ->get('ARRAY_A');

        return $data;
    }

    /**
     * Undocumented function
     * @param 'daily'|'monthly' $time_group
     * @param PresetDateRangeParams|null $date_range
     *
     * @return array
     */
    public static function affiliates_data($time_group = 'daily', $date_range = null)
    {
        $date_format = self::_sql_date_format($time_group);

        if (is_null($date_range)) {
            $date_range = new PresetDateRangeParams(['preset_date_range' => 'this_year']);
        }

        $created_at_where_clause = [
            'created_at' => [
                'operator' => 'BETWEEN',
                'min' => $date_range->computed_start_date(),
                'max' => $date_range->computed_end_date()
            ]
        ];

        /** 
         * @var array $data
         * @psalm-suppress UndefinedDocblockClass 
         **/
        $data = Affiliate::builder()
            ->select("DATE_FORMAT(created_at, {$date_format}) as date, count(ID) as count")
            ->where(['status' => ['value' => 'rejected', 'operator' => '<>']])
            ->where($created_at_where_clause)
            ->group_by('date')
            ->get('ARRAY_A');

        return $data;
    }

    /**
     * Undocumented function
     * @param 'daily'|'monthly' $time_group
     * @param PresetDateRangeParams|null $date_range
     * @param int[] $affiliate_ids
     *
     * @return array
     */
    public static function visits_data($time_group = 'daily', $date_range = null, $affiliate_ids = [])
    {
        $date_format = self::_sql_date_format($time_group);

        if (is_null($date_range)) {
            $date_range = new PresetDateRangeParams(['preset_date_range' => 'this_year']);
        }

        $where_clause = [
            'created_at' => [
                'operator' => 'BETWEEN',
                'min' => $date_range->computed_start_date(),
                'max' => $date_range->computed_end_date()
            ]
        ];

        if (!empty($affiliate_ids) && $affiliate_ids != [0]) {
            $where_clause['affiliate_id'] = ['operator' => 'IN', 'value' => $affiliate_ids];
        }

        /** 
         * @var array $data
         * @psalm-suppress UndefinedDocblockClass 
         **/
        $data = Visit::builder()
            ->where($where_clause)
            ->select("DATE_FORMAT(created_at, {$date_format}) as date, count(ID) as count, sum(referral_id != 0) as converted_count")
            // ->where(['status' => ['value' => 'rejected', 'operator' => '<>']])
            ->group_by('date')
            ->get('ARRAY_A');

        return $data;
    }

    /**
     * Undocumented function
     *
     * @param 'daily'|'monthly' $time_group
     * 
     * @return string
     */
    public static function _sql_date_format($time_group = 'daily')
    {
        return [
            'daily' => "'%Y-%m-%d'",
            'monthly' => "'%Y-%m'"
        ][$time_group];
    }
}
