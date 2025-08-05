<?php

namespace SolidAffiliate\Lib\VO;

use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\GlobalTypes;
use SolidAffiliate\Lib\Utils;

/** 
 * @psalm-type DateRangeOptions = value-of<self::DATE_RANGE_OPTIONS>
 * 
 * @psalm-type PresetDateRangeParamsType = array{
 *  preset_date_range: DateRangeOptions,
 *  start_date?: string, 
 *  end_date?: string
 * } 
 */
class PresetDateRangeParams
{
    const DATE_RANGE_OPTIONS = [
        'today',
        'yesterday',
        'this_week',
        'last_week',
        'this_month',
        'last_month',
        'this_quarter',
        'last_quarter',
        'this_year',
        'last_year',
        'all_time',
        'custom',
        'last_7_days',
        'last_30_days'
    ];

    /** @var PresetDateRangeParamsType $data */
    public $data;

    /** @var DateRangeOptions */
    public $preset_date_range;

    /** @var string */
    private $start_date;

    /** @var string */
    private $end_date;


    /** @param PresetDateRangeParamsType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->preset_date_range = $data['preset_date_range'];
        $this->start_date = isset($data['start_date']) ? $data['start_date'] : 'today';
        $this->end_date = isset($data['end_date']) ? $data['end_date'] : 'today';
    }

    /**
     * Undocumented function
     *
     * @param string $date_format
     * @param bool $offset_for_site_timezone
     *
     * @return string
     */
    public function computed_start_date($date_format = 'Y-m-d H:i:s', $offset_for_site_timezone = true)
    {
        switch ($this->preset_date_range) {
            case 'custom':
                $start_date = $this->start_date;
                $t = Utils::date_picker_time($start_date . ' midnight', 0, $date_format, $offset_for_site_timezone);
                return $t;
            case 'today':
                return Utils::date_picker_time('yesterday midnight', 0, $date_format, $offset_for_site_timezone);
            case 'yesterday':
                return Utils::date_picker_time('- 2 day midnight', 0, $date_format, $offset_for_site_timezone);
            case 'this_week':
                if (date('D', time()) === 'Sun') {
                    return Utils::date_picker_time('today midnight', 0, $date_format, $offset_for_site_timezone);
                } else {
                    return Utils::date_picker_time('last sunday midnight', 0, $date_format, $offset_for_site_timezone);
                }
            case 'last_week':
                if (date('D', time()) === 'Sun') {
                    return Utils::date_picker_time('last sunday midnight', 0, $date_format, $offset_for_site_timezone);
                } else {
                    return Utils::date_picker_time('last sunday -1 week midnight', 0, $date_format, $offset_for_site_timezone);
                }
            case 'this_month':
                return Utils::date_picker_time('first day of this month midnight', 0, $date_format, $offset_for_site_timezone);
            case 'last_month':
                return Utils::date_picker_time('first day of last month midnight', 0, $date_format, $offset_for_site_timezone);
            case 'this_quarter':
                $offset = (date('n') - 1) % 3;
                return Utils::date_picker_time("first day of -$offset month midnight", 0, $date_format, $offset_for_site_timezone);
            case 'last_quarter':
                $offset = (date('n') - 1) % 3;
                return Utils::date_picker_time("first day of -$offset month midnight", strtotime('-3 month'), $date_format, $offset_for_site_timezone);
            case 'this_year':
                return Utils::date_picker_time('first day of january this year midnight', 0, $date_format, $offset_for_site_timezone);
            case 'last_year':
                return Utils::date_picker_time('first day of january last year midnight', 0, $date_format, $offset_for_site_timezone);
            case 'last_7_days':
                return Utils::date_picker_time('-7 days midnight', 0, $date_format, $offset_for_site_timezone);
            case 'last_30_days':
                return Utils::date_picker_time('-30 days midnight', 0, $date_format, $offset_for_site_timezone);
            case 'all_time':
                return Utils::date_picker_time('- 50 year', 0, $date_format, $offset_for_site_timezone);
        }
    }

    /**
     * Undocumented function
     *
     * @param string $date_format
     * @param bool $offset_for_site_timezone
     *
     * @return string
     */
    public function computed_end_date($date_format = 'Y-m-d H:i:s', $offset_for_site_timezone = true)
    {
        switch ($this->preset_date_range) {
            case 'custom':
                $end_date = $this->end_date;
                // $end_date example: "2023-08-01 06:59:59"
                // if $end_date is "2023-08-01" then we add "23:59:59" to it
                // but if $end_date is "2023-08-01 06:59:59" then we don't add "23:59:59" to it
                if (strlen($end_date) === 10) {
                    $end_date .= ' 23:59:59';
                }
                $t = Utils::date_picker_time($end_date, 0, $date_format, $offset_for_site_timezone);
                return $t;
            case 'today':
                return Utils::date_picker_time('today 23:59:59', 0, $date_format, $offset_for_site_timezone);
            case 'yesterday':
                return Utils::date_picker_time('yesterday 23:59:59', 0, $date_format, $offset_for_site_timezone);
            case 'this_week':
                if (date('D', time()) === 'Sat') {
                    return Utils::date_picker_time('today 23:59:59', 0, $date_format, $offset_for_site_timezone);
                } else {
                    return Utils::date_picker_time('next saturday 23:59:59', 0, $date_format, $offset_for_site_timezone);
                }
            case 'last_week':
                return Utils::date_picker_time('last saturday 23:59:59', 0, $date_format, $offset_for_site_timezone);
            case 'this_month':
                return Utils::date_picker_time('last day of this month 23:59:59', 0, $date_format, $offset_for_site_timezone);
            case 'last_month':
                return Utils::date_picker_time('last day of last month 23:59:59', 0, $date_format, $offset_for_site_timezone);
            case 'this_quarter':
                $offset = (3 - (date('n')) % 3) % 3;
                return Utils::date_picker_time("last day of +$offset month 23:59:59", 0, $date_format, $offset_for_site_timezone);
            case 'last_quarter':
                $offset = (3 - (date('n')) % 3) % 3;
                return Utils::date_picker_time("last day of +$offset month 23:59:59", strtotime('-3 month'), $date_format, $offset_for_site_timezone);
            case 'this_year':
                return Utils::date_picker_time('last day of december this year 23:59:59', 0, $date_format, $offset_for_site_timezone);
            case 'last_year':
                return Utils::date_picker_time('last day of december last year 23:59:59', 0, $date_format, $offset_for_site_timezone);
            case 'last_7_days':
                return Utils::date_picker_time('today 23:59:59', 0, $date_format, $offset_for_site_timezone);
            case 'last_30_days':
                return Utils::date_picker_time('today 23:59:59', 0, $date_format, $offset_for_site_timezone);
            case 'all_time':
                return Utils::date_picker_time('+ 50 year', 0, $date_format, $offset_for_site_timezone);
        }
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function formatted_preset_date_range()
    {
        $tuples = GlobalTypes::translated_DATE_RANGE_ENUM_OPTIONS();
        $preset = $this->preset_date_range;

        foreach ($tuples as $tuple) {
            # code...
            if ($preset == $tuple[0]) {
                return $tuple[1];
            }
        }

        return $this->preset_date_range;
    }

    /**
     * Returns a human readable string describing the date range.
     * Some examples:
     * - "Today"
     * - "Yesterday"
     * - "Last 7 days"
     * - "Before 2021-01-01"
     * - "2021-03-24 - 2021-04-28"
     *
     * @param bool $offset_for_site_timezone
     * 
     * @return string
     */
    public function human_readable_range($offset_for_site_timezone = false)
    {
        $date_format = Formatters::site_date_format();
        return $this->formatted_preset_date_range() . ' (' . $this->computed_start_date($date_format, $offset_for_site_timezone) . ' - ' . $this->computed_end_date($date_format, $offset_for_site_timezone) . ')';
    }
}
