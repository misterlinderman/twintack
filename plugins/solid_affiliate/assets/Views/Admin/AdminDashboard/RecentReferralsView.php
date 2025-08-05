<?php

namespace SolidAffiliate\Views\Admin\AdminDashboard;

use SolidAffiliate\Lib\AdminDashboardHelper;
use SolidAffiliate\Views\Shared\CardView;
use SolidAffiliate\Views\Shared\SimpleTableView;


class RecentReferralsView
{

    /**
     * Undocumented function
     * 
     * @return string
     */
    public static function render()
    {
        // convert the strings above for translation domain = solid-affiliate
        $headers = [
            __('Affiliate ID', 'solid-affiliate'),
            __('Amount', 'solid-affiliate'),
            __('Description', 'solid-affiliate'),
            __('Created At', 'solid-affiliate'),
        ];

        $rows = AdminDashboardHelper::recent_referrals();

        $body = SimpleTableView::render($headers, $rows);

        ob_start();
?>

    <?php echo $body; ?>

<?php
        return ob_get_clean();
    }
}
