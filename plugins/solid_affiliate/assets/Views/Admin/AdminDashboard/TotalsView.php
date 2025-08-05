<?php

namespace SolidAffiliate\Views\Admin\AdminDashboard;

use SolidAffiliate\Lib\AdminDashboardHelper;
use SolidAffiliate\Views\Shared\SimpleTableView;


class TotalsView
{

    /**
     * Undocumented function
     * 
     * @return string
     */
    public static function render()
    {
        // Attributable Revenue
        $headers = [__('Total Revenue from Affiliates', 'solid-affiliate'), __('Last 30 days', 'solid-affiliate'), __('Last 24 hours', 'solid-affiliate')];


        $rows = AdminDashboardHelper::total_attributable_revenue();
        $attributable_revenue_table = SimpleTableView::render($headers, $rows);

        // Affiliates
        $headers = [__('Total Affiliates', 'solid-affiliate'), __('Last 30 days', 'solid-affiliate'), __('Last 24 hours', 'solid-affiliate')];
        $rows = AdminDashboardHelper::total_affiliates();
        $affiliates_table = SimpleTableView::render($headers, $rows);

        // Referrals
        $headers = [__('Total Referrals', 'solid-affiliate'), __('Last 30 days', 'solid-affiliate'), __('Last 24 hours', 'solid-affiliate')];
        $rows = AdminDashboardHelper::total_referrals();
        $referrals_table = SimpleTableView::render($headers, $rows);

        // Visits
        $headers = [__('Total Visits', 'solid-affiliate'), __('Last 30 days', 'solid-affiliate'), __('Last 24 hours', 'solid-affiliate')];
        $rows = AdminDashboardHelper::total_visits();
        $visits_table = SimpleTableView::render($headers, $rows);

        $body = $attributable_revenue_table  . $affiliates_table . $referrals_table . $visits_table;
        ob_start();
?>

        <?php echo $body; ?>

    <?php
        return ob_get_clean();
    }
}
