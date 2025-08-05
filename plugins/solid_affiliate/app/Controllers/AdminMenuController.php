<?php

namespace SolidAffiliate\Controllers;


use SolidAffiliate\Lib\Utils;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Visit;
use SolidAffiliate\Controllers\SetupWizardController;
use SolidAffiliate\Lib\AdminNotifications;
use SolidAffiliate\Lib\Capabilities;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\FF;
use SolidAffiliate\Lib\License;
use SolidAffiliate\Lib\ListTables\AffiliateGroupsListTable;
use SolidAffiliate\Models\BulkPayout;
use SolidAffiliate\Lib\ListTables\AffiliatesListTable;
use SolidAffiliate\Lib\ListTables\BulkPayoutsListTable;
use SolidAffiliate\Lib\ListTables\ReferralsListTable;
use SolidAffiliate\Lib\ListTables\PayoutsListTable;
use SolidAffiliate\Lib\ListTables\VisitsListTable;
use SolidAffiliate\Lib\ListTables\CreativesListTable;
use SolidAffiliate\Lib\NewNav;
use SolidAffiliate\Lib\SeedDatabase;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\SetupWizard;
use SolidAffiliate\Lib\Tags;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Lib\WooSoftwareLicense\WOO_SLT_Options_Interface;
use SolidAffiliate\Models\AffiliateGroup;
use SolidAffiliate\Models\AffiliateProductRate;
use SolidAffiliate\Models\Creative;
use SolidAffiliate\Views\Shared\AdminFooter;
use SolidAffiliate\Views\Shared\AdminHeader;

/**
 * AdminMenuController
 * 
 * @psalm-suppress PropertyNotSetInConstructor
 */
class AdminMenuController
{
    const ROOT_PAGE_KEY = 'solid-affiliate-admin';
    /**
     *
     * @return void
     */
    public static function init()
    {
        $menu_slug = self::ROOT_PAGE_KEY;

        $admin_notifications_count = AdminNotifications::count();
        if ($admin_notifications_count > 0) {
            $admin_notifications_counter_span = "<span class='update-plugins count-1'><span class='plugin-count'>{$admin_notifications_count}</span></span>";
        } else {
            $admin_notifications_counter_span = "";
        }




        add_menu_page(
            'Solid Affiliate - Admin',
            "Solid Affiliate {$admin_notifications_counter_span}",
            Capabilities::MANAGE_AFFILIATE_PROGRAM, // This was 'manage_options before
            $menu_slug,
            function () {},
            'https://solidaffiliate.com/brand/favicon.svg'
        );

        // add_submenu_page(
        //     $menu_slug,
        //     'New Nav',
        //     __('New Nav', 'solid-affiliate'),
        //     Capabilities::MANAGE_AFFILIATE_PROGRAM,
        //     'solid-affiliate-admin-new-nav',
        //     function () {
        //         echo NewNav::render();
        //     }
        // );

        if (SetupWizard::is_displayed()) {
            add_submenu_page(
                $menu_slug,
                'Solid Affiliate - Setup Wizard',
                __('Setup Wizard', 'solid-affiliate'),
                Capabilities::MANAGE_AFFILIATE_PROGRAM,
                $menu_slug,
                function () {
                    SetupWizardController::admin_root();
                }
            );


            add_submenu_page(
                $menu_slug,
                'Solid Affiliate - Admin Dashboard',
                __('Dashboard', 'solid-affiliate') . ' ' . $admin_notifications_counter_span,
                Capabilities::MANAGE_AFFILIATE_PROGRAM,
                AdminDashboardController::PAGE_PARAM_V2,
                function () {
                    echo AdminHeader::render_from_get_request($_GET);
                    AdminDashboardController::v2();
                }
            );
        } else {
            add_submenu_page(
                $menu_slug,
                'Solid Affiliate - Admin Dashboard',
                __('Dashboard', 'solid-affiliate') . ' ' . $admin_notifications_counter_span,
                Capabilities::MANAGE_AFFILIATE_PROGRAM,
                $menu_slug,
                function () {
                    echo AdminHeader::render_from_get_request($_GET);
                    AdminDashboardController::v2();
                }
            );
        }

        $hook_suffix = add_submenu_page(
            $menu_slug,
            'Solid Affiliate - Manage affiliates',
            __('Affiliates', 'solid-affiliate'),
            Capabilities::MANAGE_AFFILIATE_PROGRAM,
            Affiliate::ADMIN_PAGE_KEY,
            function () {
                echo AdminHeader::render_from_get_request($_GET);
                AffiliatesController::admin_root();
            }
        );
        // Only add screen options to the index page.
        if (!isset($_GET['action'])) {
            add_action("load-$hook_suffix", [AffiliatesListTable::class, 'add_screen_options']);
        }

        $hook_suffix = add_submenu_page(
            $menu_slug,
            'Solid Affiliate - Manage Affiliate Groups',
            __('Groups', 'solid-affiliate'),
            Capabilities::MANAGE_AFFILIATE_PROGRAM,
            AffiliateGroup::ADMIN_PAGE_KEY,
            function () {
                echo AdminHeader::render_from_get_request($_GET);
                if (isset($_GET['tab']) && $_GET['tab'] == 'tags') {
                    Tags::admin_root();
                } else {
                    AffiliateGroupsController::admin_root();
                }
            }
        );
        // Only add screen options to the index page.
        if (!isset($_GET['action'])) {
            add_action("load-$hook_suffix", [AffiliateGroupsListTable::class, 'add_screen_options']);
        }

        $hook_suffix = add_submenu_page(
            $menu_slug,
            'Solid Affiliate - Manage Affiliate Product Rates',
            __('Product Rates', 'solid-affiliate'),
            Capabilities::MANAGE_AFFILIATE_PROGRAM,
            AffiliateProductRate::ADMIN_PAGE_KEY,
            function () {
                echo AdminHeader::render_from_get_request($_GET);
                AffiliateProductRatesController::admin_root();
            }
        );
        // Only add screen options to the index page.
        if (!isset($_GET['action'])) {
            add_action("load-$hook_suffix", [AffiliateGroupsListTable::class, 'add_screen_options']);
        }


        $hook_suffix = add_submenu_page(
            $menu_slug,
            'Solid Affiliate - Manage Referrals',
            __('Referrals', 'solid-affiliate'),
            Capabilities::MANAGE_AFFILIATE_PROGRAM,
            Referral::ADMIN_PAGE_KEY,
            function () {
                echo AdminHeader::render_from_get_request($_GET);
                ReferralsController::admin_root();
            }
        );
        // Only add screen options to the index page.
        if (!isset($_GET['action'])) {
            add_action("load-$hook_suffix", [ReferralsListTable::class, 'add_screen_options']);
        }

        $hook_suffix = add_submenu_page(
            $menu_slug,
            'Solid Affiliate - Manage Payouts',
            __('Payouts', 'solid-affiliate'),
            Capabilities::MANAGE_AFFILIATE_PROGRAM,
            Payout::ADMIN_PAGE_KEY,
            function () {
                echo AdminHeader::render_from_get_request($_GET);
                PayoutsController::admin_root();
            }
        );
        // Only add screen options to the index page.
        if (!isset($_GET['action'])) {
            add_action("load-$hook_suffix", [PayoutsListTable::class, 'add_screen_options']);
        }

        $hook_suffix = add_submenu_page(
            $menu_slug,
            'Solid Affiliate - Manage Visits',
            __('Visits', 'solid-affiliate'),
            Capabilities::MANAGE_AFFILIATE_PROGRAM,
            Visit::ADMIN_PAGE_KEY,
            function () {
                echo AdminHeader::render_from_get_request($_GET);
                VisitsController::admin_root();
            }
        );
        // Only add screen options to the index page.
        if (!isset($_GET['action'])) {
            add_action("load-$hook_suffix", [VisitsListTable::class, 'add_screen_options']);
        }

        $hook_suffix = add_submenu_page(
            $menu_slug,
            'Solid Affiliate - Manage Creatives',
            __('Creatives', 'solid-affiliate'),
            Capabilities::MANAGE_AFFILIATE_PROGRAM,
            Creative::ADMIN_PAGE_KEY,
            function () {
                echo AdminHeader::render_from_get_request($_GET);
                CreativesController::admin_root();
            }
        );
        // Only add screen options to the index page.
        if (!isset($_GET['action'])) {
            add_action("load-$hook_suffix", [CreativesListTable::class, 'add_screen_options']);
        }

        add_submenu_page(
            $menu_slug,
            'Solid Affiliate' . ' - ' . __('Reports', 'solid-affiliate'),
            __('Reports', 'solid-affiliate'),
            Capabilities::MANAGE_AFFILIATE_PROGRAM,
            AdminReportsController::ADMIN_PAGE_KEY,
            function () {
                echo AdminHeader::render_from_get_request($_GET);
                AdminReportsController::admin_root();
            }
        );
        $hook_suffix = add_submenu_page(
            $menu_slug,
            'Solid Affiliate - Pay Affiliates',
            __('Pay Affiliates', 'solid-affiliate'),
            Capabilities::MANAGE_AFFILIATE_PROGRAM,
            BulkPayout::ADMIN_PAGE_KEY,
            function () {
                echo AdminHeader::render_from_get_request($_GET);
                PayAffiliatesController::admin_root();
            }
        );
        // Only add screen options to the index page.
        if (!isset($_GET['action']) && isset($_GET['tab']) && $_GET['tab'] == 'bulk_payouts') {
            add_action("load-$hook_suffix", [BulkPayoutsListTable::class, 'add_screen_options']);
        }

        add_submenu_page(
            $menu_slug,
            'Solid Affiliate - Commission Rates',
            __('Commission Rates', 'solid-affiliate'),
            Capabilities::MANAGE_AFFILIATE_PROGRAM,
            CommissionRatesController::ADMIN_PAGE_KEY,
            function () {
                echo AdminHeader::render_from_get_request($_GET);

                // if (FF::is_on('commission_rates_overview')) {
                CommissionRatesController::admin_root();
                // } else {
                //     echo FF::maybe_overlay('commission_rates_overview');
                // }
            }
        );
        add_submenu_page(
            $menu_slug,
            'Solid Affiliate - Settings',
            __('Settings', 'solid-affiliate'),
            Capabilities::MANAGE_AFFILIATE_PROGRAM,
            'solid-affiliate-settings',
            function () {
                echo AdminHeader::render_from_get_request($_GET);
                SettingsController::admin_root();
            }
        );
        $hookID = add_submenu_page(
            $menu_slug,
            'Solid Affiliate - License',
            __('License', 'solid-affiliate'),
            Capabilities::MANAGE_AFFILIATE_PROGRAM,
            License::ADMIN_PAGE_KEY,
            function () {
                echo AdminHeader::render_from_get_request($_GET);
                $i = new WOO_SLT_Options_Interface;
                echo (FF::maybe_license_page_banner());
                if (License::is_solid_affiliate_activated()) {
                    $i->licence_deactivate_form();
                } else {
                    $i->licence_form();
                }
            }
        );

        if (is_string($hookID)) {
            /** 
             * @psalm-suppress UndefinedClass
             * @psalm-suppress InvalidArgument 
             */
            add_action('load-' . $hookID, [WOO_SLT_Options_Interface::class, 'admin_notices_static']);
        }

        /**
         * After admin submenu pages are added using add_submenu_page().
         *
         * @param string $menu_slug The slug of the parent menu. Use this to add submenu items.
         */
        do_action("solid_affiliate/admin/submenu_pages/after", $menu_slug);

        // add a hidden - Solid Affiliate Debug mode - page
        // this page should not show a link, but should be accessible via url
        // the link is hidden via CSS
        add_submenu_page(
            $menu_slug,
            'Solid Affiliate - Debug',
            __('Debug', 'solid-affiliate'),
            Capabilities::MANAGE_AFFILIATE_PROGRAM,
            'solid-affiliate-debug',
            function () {
                echo AdminHeader::render_from_get_request($_GET);
                DebugController::admin_root();
            }
        );
    }

    /**
     * Undocumented function
     * 
     * @param string $text
     *
     * @return string
     */
    public static function admin_footer_hook_callback($text)
    {
        $prefix = 'solid-affiliate';
        if (isset($_REQUEST['page']) && (substr((string)$_REQUEST['page'], 0, strlen($prefix)) == $prefix)) {
            echo AdminFooter::render();
            return $text;
        } else {
            return $text;
        }
    }

    /**
     * Admin List Tables Search and Filtering.
     * 
     * When the user clicks the "Search" button, this function redirects them to a URL with the 's'
     * query parameter set to what they were searching. Also handles filters such as admin_id.
     *
     * @since 1.0.0
     *
     * @hook admin_init
     *
     * @return void
     */
    public static function admin_wp_list_table_search_and_filter_handler()
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || ($_SERVER['REQUEST_METHOD'] !== 'POST')) {
            return;
        }
        // TODO this function needs to be refactored. It's not clear what is going on. 

        // Determine if they just clicked the search button on the list table page
        ///////////////////////////////////////////////////////////////////////////
        if (!(isset($_POST['s']) || isset($_POST['affiliate_id']) || isset($_POST['affiliate_group_id'])) || !isset($_REQUEST['page'])) {
            return;
        }
        $prefix = 'solid-affiliate';
        if (substr((string)$_REQUEST['page'], 0, strlen($prefix)) !== $prefix) {
            return;
        }

        if (
            (isset($_GET['s']) && ($_POST['s'] === $_GET['s'])) &&
            (isset($_GET['affiliate_id']) && ($_POST['affiliate_id'] === $_GET['affiliate_id'])) &&
            (isset($_GET['affiliate_group_id']) && ($_POST['affiliate_group_id'] === $_GET['affiliate_group_id']))
        ) {
            return;
        }

        $params_to_add = [
            's' => $_POST['s'],
            'affiliate_id' => $_POST['affiliate_id'],
            'affiliate_group_id' => $_POST['affiliate_group_id'],
            'tag' => isset($_POST['tag']) ? $_POST['tag'] : '',
            'paged' => '1' // Need to reset the pagination when first clicking search, otherwise the results will be bugged. Trust.
        ];

        // Redirect them back to the same page, but with the query param in the url from the search/filter fields
        ///////////////////////////////////////////////////////////////////////////
        ControllerFunctions::handle_redirecting_and_exit(
            'REDIRECT_BACK',
            [],
            [],
            'admin',
            $params_to_add
        );
    }
}
