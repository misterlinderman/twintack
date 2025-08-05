<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Addons\AutoCreateAffiliatesForNewUsers\Addon as AutoCreateAffiliatesForNewUsersAddon;
use SolidAffiliate\Addons\AutoCreateCoupons\Addon as AutoCreateCouponsAddon;
use SolidAffiliate\Addons\Core;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Visit;
use SolidAffiliate\Models\Payout;

/**
 * @psalm-import-type AdminDashboardV2Interface from \SolidAffiliate\Views\Admin\AdminDashboard\V2\RootView
 * @psalm-import-type AdminNotificationData from \SolidAffiliate\Views\Admin\AdminDashboard\V2\RootView
 * @psalm-import-type InsightsData from \SolidAffiliate\Views\Admin\AdminDashboard\V2\RootView
 * @psalm-import-type FeedData from \SolidAffiliate\Lib\AdminFeed
 * @psalm-import-type SmartTipData from \SolidAffiliate\Views\Admin\AdminDashboard\V2\RootView
 * 
 */
class AdminDashboardV2Helper
{
    const TRANSIENT_KEY_ADMIN_FEED = 'solid_affiliate_transient-admin_feed';
    const TRANSIENT_EXPIRATION_IN_SECONDS = 300; // 5 minutes
    ///////////////////////////////////////////////////////
    // Start - Admin Dashboard V2 
    ///////////////////////////////////////////////////////

    /**
     * @return AdminDashboardV2Interface
     */
    public static function construct_v2_interface()
    {
        self::construct_feed();
        $feed = Utils::solid_transient(
            [self::class, 'construct_feed'],
            self::TRANSIENT_KEY_ADMIN_FEED,
            self::TRANSIENT_EXPIRATION_IN_SECONDS
        );

        $interface_data = [
            'admin_notifications' => self::construct_admin_notifications(),
            'insights' => self::construct_insights(),
            'smart_tips' => self::construct_smart_tips(),
            'feed' => $feed,
        ];

        return $interface_data;
    }

    /**
     * @return AdminNotificationData[]
     */
    public static function construct_admin_notifications()
    {
        return AdminNotifications::get_admin_notifications_data();
    }

    /**
     * @return array{
     *   all_time: InsightsData,
     *   30_day: InsightsData,
     * }
     */
    public static function construct_insights()
    {
        return [
            'all_time' => self::get_all_time_insights(),
            '30_day' => self::get_30_day_insights(),
        ];
    }

    /**
     * @return FeedData
     */
    public static function construct_feed()
    {
        return AdminFeed::construct_feed();
    }


    /**
     * @return SmartTipData[]
     */
    public static function construct_smart_tips()
    {
        // TODO Build out a proper Smart Tips module. 
        // For now I'll hard code them here, it will be enough to build out the Dashboard V2.
        $link_to_affiliate_portal_header = __('Your Affiliate Registration Page', 'solid-affiliate');
        $raw_link_to_affiliate_portal = Links::render(URLs::site_portal_url(), URLs::site_portal_url());
        $affiliate_portal_component = "<div class='sld_dashboard-portal-link-container'>" . $link_to_affiliate_portal_header . '<br> <br>' . $raw_link_to_affiliate_portal . "</div>";
        $smart_tips = [
            [
                'slug' => 'smart-tip_acquiring-more-affiliates',
                'title' => __('Acquiring More Affiliates', 'solid-affiliate'),
                'html_message' => __('Get the ball rolling by finding quality affiliates. You can read some of our tips on <a href="https://solidaffiliate.com/acquiring-affiliates/" target="_blank">acquiring affiliates</a>.', 'solid-affiliate')
            ],
            [
                'slug' => 'smart-tip_customize-affiliate-registration-page',
                'title' => __('Share and Customize your Affiliate Registration page', 'solid-affiliate'),
                'html_message' =>  $affiliate_portal_component . __('Did you know that you can customize your affiliate registration page? Just like any other page on your WordPress site, you can add content and images to have a great design.', 'solid-affiliate')
            ],
            [
                'slug' => 'smart-tip_affiliate-portal-preview',
                'title' => __("Preview your Affiliates' Portals", 'solid-affiliate'),
                'html_message' => __('Did you know that you can preview your affiliates\' portals and see exactly what any of your affiliates would see when logged in? Read more about it <a href="https://docs.solidaffiliate.com/affiliate-portal/#portal-preview" target="_blank">here</a>.', 'solid-affiliate')

            ],
            // acquiring more affiliates. link out to https://solidaffiliate.com/acquiring-affiliates/ 
        ];

        if (!Core::is_addon_enabled(AutoCreateAffiliatesForNewUsersAddon::ADDON_SLUG)) {
            $smart_tips[] = [
                'slug' => 'smart-tip_enable-auto-register-customers-as-affiliates-addon',
                'title' => __('Auto Register Customers as Affiliates.', 'solid-affiliate'),
                'html_message' => __('Did you know that you can automatically register your customers as affiliates? This is a great way to get more affiliates on your site. You can enable this functionality in the <a href="#" target="_blank">addons menu</a>.', 'solid-affiliate')

            ];
        }

        if (!Core::is_addon_enabled(AutoCreateCouponsAddon::ADDON_SLUG)) {
            $smart_tips[] = [
                'slug' => 'smart-tip_enable-auto-create-coupon-for-affiliates-addon',
                'title' => __('Auto Create a Coupon for Affiliates', 'solid-affiliate'),
                'html_message' => __('Did you know that you can automatically create a coupon for your affiliates? This is a great way to empower your affiliates, and incentivize their audiences and friends to buy your products. You can enable this functionality in the <a href="#" target="_blank">Addons menu</a>.', 'solid-affiliate')
            ];
        }
        return $smart_tips;
    }



    ///// Private Helpers for Admin Dashboard V2 /////

    /**
     * @return InsightsData
     */
    private static function get_all_time_insights()
    {
        $gross_affiliate_revenue = (float)Referral::builder()
            ->select('SUM(order_amount) AS gross_affiliate_revenue')
            ->where([
                'status' => [
                    'operator' => 'IN',
                    'value' => Referral::STATUSES_PAID_AND_UNPAID
                ]
            ])
            ->value();

        $net_affiliate_revenue = (float)Referral::builder()
            ->select('SUM(order_amount - commission_amount) AS net_affiliate_revenue')
            ->where([
                'status' => [
                    'operator' => 'IN',
                    'value' => Referral::STATUSES_PAID_AND_UNPAID
                ]
            ])
            ->value();

        $total_paid_earnings = (float)Payout::builder()
            ->select('SUM(amount) AS total_paid_earnings')
            ->where(['status' => Payout::STATUS_PAID])
            ->value();

        $total_unpaid_earnings = (float)Referral::builder()
            ->select('SUM(commission_amount) AS net_affiliate_revenue')
            ->where(['status' => Referral::STATUS_UNPAID])
            ->value();


        // Unformatted TODO do something with this. Extract this, it's useful data.

        // $all_time_insights = [
        //     'formatted_gross_affiliate_revenue' => $gross_affiliate_revenue,
        //     'formatted_net_affiliate_revenue' => $net_affiliate_revenue,
        //     'formatted_total_paid_earnings' => $total_paid_earnings,
        //     'formatted_total_unpaid_earnings' => $total_unpaid_earnings,
        //     'formatted_total_referrals' => Referral::count(['status' => ['operator' => 'IN', 'value' => Referral::STATUSES_PAID_AND_UNPAID]]),
        //     'formatted_total_visits' => Visit::count(),
        //     'formatted_total_approved_affiliates' => Affiliate::count(['status' => Affiliate::STATUS_APPROVED]),
        // ];

        $all_time_insights = [
            'formatted_gross_affiliate_revenue' => Formatters::money($gross_affiliate_revenue),
            'formatted_net_affiliate_revenue' => Formatters::money($net_affiliate_revenue),
            'formatted_total_paid_earnings' => Formatters::money($total_paid_earnings),
            'formatted_total_unpaid_earnings' => Formatters::money($total_unpaid_earnings),
            'formatted_total_referrals' => number_format(Referral::count(['status' => ['operator' => 'IN', 'value' => Referral::STATUSES_PAID_AND_UNPAID]])),
            'formatted_total_visits' => number_format(Visit::count()),
            'formatted_total_approved_affiliates' => number_format(Affiliate::count(['status' => Affiliate::STATUS_APPROVED])),
        ];

        return $all_time_insights;
    }

    /**
     * @return InsightsData
     */
    private static function get_30_day_insights()
    {
        $starting_date = date('Y-m-d', strtotime('-30 days'));

        $gross_affiliate_revenue = (float)Referral::builder()
            ->select('SUM(order_amount) AS gross_affiliate_revenue')
            ->where([
                'status' => [
                    'operator' => 'IN',
                    'value' => Referral::STATUSES_PAID_AND_UNPAID
                ],
                'created_at' => ['operator' => '>=', 'value' => $starting_date]
            ])
            ->value();

        $net_affiliate_revenue = (float)Referral::builder()
            ->select('SUM(order_amount - commission_amount) AS net_affiliate_revenue')
            ->where([
                'status' => [
                    'operator' => 'IN',
                    'value' => Referral::STATUSES_PAID_AND_UNPAID
                ],
                'created_at' => ['operator' => '>=', 'value' => $starting_date]
            ])
            ->value();

        $total_paid_earnings = (float)Payout::builder()
            ->select('SUM(amount) AS total_paid_earnings')
            ->where(['status' => Payout::STATUS_PAID, 'created_at' => ['operator' => '>=', 'value' => $starting_date]])
            ->value();

        $total_unpaid_earnings = (float)Referral::builder()
            ->select('SUM(commission_amount) AS net_affiliate_revenue')
            ->where(['status' => Referral::STATUS_UNPAID, 'created_at' => ['operator' => '>=', 'value' => $starting_date]])
            ->value();


        $all_time_insights = [
            'formatted_gross_affiliate_revenue' => Formatters::money($gross_affiliate_revenue),
            'formatted_net_affiliate_revenue' => Formatters::money($net_affiliate_revenue),
            'formatted_total_paid_earnings' => Formatters::money($total_paid_earnings),
            'formatted_total_unpaid_earnings' => Formatters::money($total_unpaid_earnings),
            'formatted_total_referrals' => number_format(Referral::count(['status' => ['operator' => 'IN', 'value' => Referral::STATUSES_PAID_AND_UNPAID], 'created_at' => ['operator' => '>=', 'value' => $starting_date]])),
            'formatted_total_visits' => number_format(Visit::count(['created_at' => ['operator' => '>=', 'value' => $starting_date]])),
            'formatted_total_approved_affiliates' => number_format(Affiliate::count(['status' => Affiliate::STATUS_APPROVED, 'created_at' => ['operator' => '>=', 'value' => $starting_date]])),
        ];

        return $all_time_insights;
    }

    ///////////////////////////////////////////////////////
    // END - Admin Dashboard V2
    ///////////////////////////////////////////////////////
}
