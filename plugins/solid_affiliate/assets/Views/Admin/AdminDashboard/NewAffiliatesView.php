<?php

namespace SolidAffiliate\Views\Admin\AdminDashboard;

use SolidAffiliate\Lib\AdminDashboardHelper;
use SolidAffiliate\Views\Shared\CardView;
use SolidAffiliate\Views\Shared\SimpleTableView;


class NewAffiliatesView
{

    /**
     * Undocumented function
     * 
     * @return string
     */
    public static function render()
    {
        $headers = [
            __('Affiliate ID', 'solid-affiliate'),
            __('Status', 'solid-affiliate'),
            __('Created At', 'solid-affiliate'),
            __('Actions', 'solid-affiliate'),
        ];

        $rows = AdminDashboardHelper::new_affiliates();

        $body = SimpleTableView::render($headers, $rows);

        ob_start();
?>

    <?php echo $body; ?>

<?php
        return ob_get_clean();
    }
}
