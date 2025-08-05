<?php

namespace SolidAffiliate\Views\Admin\AdminDashboard;

use SolidAffiliate\Lib\AdminDashboardHelper;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Views\Shared\CardView;
use SolidAffiliate\Views\Shared\SimpleTableView;
use SolidAffiliate\Views\Shared\SolidTooltipView;

class MostValuableAffiliatesView
{

    /**
     * Undocumented function
     * 
     * @param PresetDateRangeParams|null $maybe_preset_date_range_params
     * 
     * @return string
     */
    public static function render($maybe_preset_date_range_params = null)
    {
        $referrals_tooltip = SolidTooltipView::render_pretty(__('Referrals', 'solid-affiliate'), __('The number of Referrals that have been earned by this affiliate.', 'solid-affiliate'), __('This will only count Referrals with a status of <code>Paid</code> or <code>Unpaid</code>.', 'solid-affiliate'), '');

        $sort_svg = '<svg style="vertical-align: middle" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M7.5 13.5H10.5V12H7.5V13.5ZM2.25 4.5V6H15.75V4.5H2.25ZM4.5 9.75H13.5V8.25H4.5V9.75Z" fill="#B5C3C7"/>
        </svg>';

        $headers = [
            __('Affiliate', 'solid-affiliate'),
            __('Total Earnings', 'solid-affiliate') . ' ' . $sort_svg,
            __('Paid Earnings', 'solid-affiliate'),
            __('Unpaid Earnings', 'solid-affiliate'),
            __('Referrals', 'solid-affiliate') . $referrals_tooltip,
            __('Visits', 'solid-affiliate'),
        ];

        $rows = AdminDashboardHelper::most_valuable_affiliates($maybe_preset_date_range_params);

        $body = SimpleTableView::render($headers, $rows, null, __('None of your affiliates earned a referral during this date range. Please update your filter.', 'solid-affiliate'));

        ob_start();
?>

    <?php echo $body; ?>

<?php
        return ob_get_clean();
    }
}
