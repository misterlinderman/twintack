<?php

namespace SolidAffiliate\Controllers;

use SolidAffiliate\Lib\AdminDashboardHelper;
use SolidAffiliate\Lib\AdminDashboardV2Helper;
use SolidAffiliate\Lib\AdminNotifications;
use SolidAffiliate\Lib\Links;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Views\Admin\AdminDashboard\MostValuableAffiliatesView;
use SolidAffiliate\Views\Admin\AdminDashboard\NewAffiliatesView;
use SolidAffiliate\Views\Admin\AdminDashboard\RecentReferralsView;
use SolidAffiliate\Views\Admin\AdminDashboard\RootView;
use SolidAffiliate\Views\Admin\AdminDashboard\V2\RootView as V2RootView;
use SolidAffiliate\Views\Admin\AdminDashboard\TotalsView;

/**
 * AdminDashboardController
 * 
 * @psalm-suppress PropertyNotSetInConstructor
 */
class AdminDashboardController
{
    const PAGE_PARAM_INDEX = 'solid-affiliate-admin-dashboard';
    const PAGE_PARAM_V2 = 'solid-affiliate-admin-dashboard-v2';
    /**
     * @since 1.0.0
     *
     *
     * @return void
     */
    public static function admin_root()
    {
        AdminDashboardController::index();
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public static function index()
    {
        $o = RootView::render();
        echo $o;
    }

    /**
     * @return void
     */
    public static function v2()
    {
        $interface = AdminDashboardV2Helper::construct_v2_interface();
        $o = V2RootView::render($interface);
        echo $o;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public static function solid_affiliate_add_dashboard_widgets()
    {
        wp_add_dashboard_widget(
            'solid_affiliate_dashboard_widget',
            ' - ' . __('Overview', 'solid-affiliate'),
            /** @param mixed $_args */
            function ($_args) {
                echo '<style> 
                  #solid_affiliate_dashboard_widget h2.hndle {
                    justify-content: normal;
                  }

                  #solid_affiliate_dashboard_widget h2.hndle::before {
                    content: "";
                    padding-right: 2px;
                    background-size: 130px 41px;
                    display: inline-block;
                    width: 130px;
                    height: 40px;
                    background-repeat: no-repeat;
                    margin-top: 0px;
                    background-image: url(https://solidaffiliate.com/brand/logo.svg);
                    margin-top: -8px;
                    margin-bottom: -5px;
                    padding-right: 6px;
                  }
                </style>';
                echo AdminNotifications::render_dashboard_notifications();
                echo '<style>.postbox table.widefat { margin: 20px 0; }</style> ' . TotalsView::render();
                echo Links::render(URLs::dashboard_path(), __('View Solid Affiliate Dashboard', 'solid-affiliate'));
            }, // render callable
            null,
            null,
            'normal'
        );
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public static function solid_affiliate_add_admin_dashboard_meta_boxes()
    {
        // .1 seconds
        add_meta_box(
            'solid-affiliate_meta-box_totals',
            __('Totals', 'solid-affiliate'),
            /** @param mixed $_args */
            function ($_args) {
                echo TotalsView::render();
            }, 
            AdminDashboardController::PAGE_PARAM_INDEX,
            'advanced',
            'default',
            []
        );

        // .1 seconds
        add_meta_box(
            'solid-affiliate_meta-box_recent-referrals-v2',
            __('Recent Referrals', 'solid-affiliate'),
            /** @param mixed $_args */
            function ($_args) {
                echo RecentReferralsView::render();
            }, 
            AdminDashboardController::PAGE_PARAM_INDEX,
            'normal',
            'default',
            []
        );

        // .1 seconds
        add_meta_box(
            'solid-affiliate_meta-box_new-affiliates-v2',
            __('New Affiliates', 'solid-affiliate'),
            /** @param mixed $_args */
            function ($_args) {
                echo NewAffiliatesView::render();
            },
            AdminDashboardController::PAGE_PARAM_INDEX,
            'advanced',
            'default',
            []
        );

        // .9 seconds
        add_meta_box(
            'solid-affiliate_meta-box_most-valuable-affiliates-v2',
            __('Top Affiliates (All Time)', 'solid-affiliate'),
            /** @param mixed $_args */
            function ($_args) {
                echo MostValuableAffiliatesView::render();
            },
            AdminDashboardController::PAGE_PARAM_INDEX,
            'side',
            'default',
            []
        );
    }
}
