<?php

namespace SolidAffiliate\Views\Admin\AdminDashboard\V2;

use SolidAffiliate\Lib\AdminFeed;

/**
 * @psalm-type AdminNotificationData = array{
 *   type: string,
 *   title: string,
 *   html_message: string,
 *   action_1: array{label:string, url:string},
 *   action_2?: array{label:string, url:string}
 * }
 * 
 * @psalm-type InsightsData = array{
 *   formatted_gross_affiliate_revenue: string, 
 *   formatted_net_affiliate_revenue: string, 
 *   formatted_total_paid_earnings: string,
 *   formatted_total_unpaid_earnings: string,
 *   formatted_total_referrals: string, 
 *   formatted_total_visits: string, 
 *   formatted_total_approved_affiliates: string
 * }
 * 
 * @psalm-import-type FeedEventType from \SolidAffiliate\Lib\AdminFeed
 * @psalm-import-type FeedEvent from \SolidAffiliate\Lib\AdminFeed
 * @psalm-import-type FeedData from \SolidAffiliate\Lib\AdminFeed
 * 
 * 
 * @psalm-type SmartTipData = array{
 *   slug: string,
 *   title: string,
 *   html_message: string
 * }
 * 
 * @psalm-type AdminDashboardV2Interface = array{
 *   admin_notifications: AdminNotificationData[],
 *   insights: array{
 *     all_time: InsightsData,
 *     30_day: InsightsData,
 *   },
 *   feed: FeedData,
 *   smart_tips: SmartTipData[],
 * }
 * 
 */
class RootView
{

    /**
     * @param AdminDashboardV2Interface $interface
     *
     * @return string
     */
    public static function render($interface)
    {
        ob_start();
?>
        <script type="module">
            import {
                Gradient
            } from "<?php echo (plugin_dir_url(SOLID_AFFILIATE_FILE) . 'assets/js/gradient.js') ?>";
            const gradient = new Gradient();
            gradient.initGradient("#sld_gradient");
        </script>
        <style>
            @import url(<?php echo (plugin_dir_url(SOLID_AFFILIATE_FILE) . 'assets/css/dashboard.css') ?>);
        </style>
        <canvas id="sld_gradient"></canvas>
        <div class="sld_dashboard">
            <div class="sld_dashboard_hero has-notifications">
                <div class="sld_dashboard_hero_canvas">
                    <div class="sld_dashboard_welcome">
                        <div class="sld_global_container">
                        <h2><?php _e('Welcome back', 'solid-affiliate') ?>, <?php echo (ucwords(wp_get_current_user()->display_name)); ?>!</h2>
                        <p class="sld_dashboard_inspirational-mike">"<?php echo self::welcome_message(); ?>"</p>
                        </div>
                        <div class="sld_dashboard_notifications_box" x-cloak x-data="{ ShowNotifications: false }" :class="!ShowNotifications ? '' : 'show-background'" x-init="setTimeout(() => ShowNotifications = true, 800)" transition.duration.60ms>
                            <div class="sld_global_container">
                                <div class="sld_dashboard-notifications_agent" @click="ShowNotifications = !ShowNotifications">
                                    <div class="notifications-title">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#fff" class="title-icon">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0M3.124 7.5A8.969 8.969 0 015.292 3m13.416 0a8.969 8.969 0 012.168 4.5" />
                                        </svg>
                                        <span> <?php _e('Notifications', 'solid-affiliate') ?> </span>
                                    </div>
                                    <div class="collapse-icon">
                                        <a class="expand-collapse-icon" :class="!ShowNotifications ? '' : 'collapsed'"> </a>
                                    </div>
                                </div>

                                <div x-show="ShowNotifications" x-collaps x-collapse.duration.500ms>
                                    <?php echo (self::render_admin_notifications($interface['admin_notifications'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sld_dashboard_container sld_global_container">
                <div class="sld_dashboard-column insights">
                    <div class="sld_dashboard-column_title">
                        <div class="sld_dashboard-column_title-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#fff">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5m.75-9l3-3 2.148 2.148A12.061 12.061 0 0116.5 7.605" />
                            </svg>

                        </div>
                        <span><?php _e('Insights', 'solid-affiliate') ?></span>
                    </div>

                    <div x-data="{loadingdots: false}">
                        <?php echo (self::render_insights($interface['insights'])); ?>
                        <?php $more_insights_text = __('More insights', 'solid-affiliate'); ?>
                        <a href="<?php echo (admin_url('admin.php?page=solid-affiliate-reports')); ?>" class="sld_dashboard_btn-more" :class="{'loadingdots': loadingdots===true}" @click="loadingdots=!loadingdots" x-html="loadingdots===true?'<span>&bull;</span><span>&bull;</span><span>&bull;</span>': '<?php echo($more_insights_text) ?>'">
                        </a>
                    </div>
                </div>

                <!-- How Boss renders his views -->
                <?php echo (self::render_feed($interface['feed'])); ?>

                <!-- Add collapses with .min modifiers -->
                <div class="sld_dashboard-column smart-tips">
                    <div class="sld_dashboard-column_title">
                        <div class="sld_dashboard-column_title-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#fff">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                            </svg>
                        </div>

                        <span><?php _e('Smart tips', 'solid-affiliate') ?></span>
                    </div>
                    <?php echo (self::render_smart_tips($interface['smart_tips'])); ?>
                </div>

            </div>
        </div>


    <?php
        return ob_get_clean();
    }

    /**
     * Renders the Feed.
     *
     * @param FeedData $feed_data
     * @return string
     */
    private static function render_feed($feed_data)
    {
        $feed_groups = array_map(function ($date) use ($feed_data) {
            $feed_events_for_date = $feed_data[$date];
            return self::render_feed_events_group($date, $feed_events_for_date);
        }, array_keys($feed_data));

        $feed_groups_combined = implode('', $feed_groups);

        if (empty($feed_groups_combined)) {
            $feed_groups_combined = self::render_empty_feed();
        }

        ob_start();
    ?>
        <div class="sld_dashboard-column feed">

            <div class="sld_dashboard-column_title">
                <div class="sld_dashboard-column_title-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#fff">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                </div>
                <span><?php _e('Feed', 'solid-affiliate') ?></span>
            </div>
            <div class="sld_dashboard-column_item">
                <?php echo ($feed_groups_combined); ?>
            </div>
        </div>

    <?php
        return ob_get_clean();
    }

    /**
     * Renders a group of FeedEvents.
     * 
     * @param string $date_of_group
     * @param FeedEvent[] $feed_events_for_date
     * @return string
     */
    private static function render_feed_events_group($date_of_group, $feed_events_for_date)
    {
        $rendered_feed_events = array_map([self::class, 'render_feed_event'], $feed_events_for_date);
        $rendered_feed_events_combined = implode('', $rendered_feed_events);
        $count_rendered_feed_events = count($rendered_feed_events);
        ob_start();
    ?>

        <div class="feed-item_box" x-cloak x-data="{ expanded: false }">
            <div class="feed-item_agent" x-bind:class="{'feed-item_max-height': !expanded}">
                <?php if ($count_rendered_feed_events > 6) : ?>
                    <div class="feed-item_show-more" @click="expanded = !expanded">
                        <span x-text="expanded == true ? 'Show less' : 'Show more'"></span>
                    </div>
                <?php endif; ?>
                <div class="sld_dashboard-column_item">
                    <div class="sld_dashboard_slug"><?php echo ($date_of_group) ?> <?php _e('ago', 'solid-affiliate') ?></div>
                    <div class="sld_dashboard_box"><?php echo ($rendered_feed_events_combined) ?></div>
                </div>
            </div>
        </div>

    <?php
        return ob_get_clean();
    }


    /**
     * Renders an individual FeedEvent.
     * 
     * @param FeedEvent $feed_event
     * @return string
     */
    private static function render_feed_event($feed_event)
    {
        switch ($feed_event['type']) {
            case AdminFeed::EVENT_TYPE_REFERRAL_CREATED:
                $icon = '
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#707477">
                 <path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672L13.684 16.6m0 0l-2.51 2.225.569-9.47 5.227 7.917-3.286-.672zM12 2.25V4.5m5.834.166l-1.591 1.591M20.25 10.5H18M7.757 14.743l-1.59 1.59M6 10.5H3.75m4.007-4.243l-1.59-1.59" />
                </svg>
                ';
                break;
            case AdminFeed::EVENT_TYPE_REFERRAL_AUTO_REJECTED:
                $icon = '
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#707477">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9.75h4.875a2.625 2.625 0 010 5.25H12M8.25 9.75L10.5 7.5M8.25 9.75L10.5 12m9-7.243V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185z" />
                  </svg>
                
                ';
                break;
            case AdminFeed::EVENT_TYPE_AFFILIATE_CREATED:
                $icon = '
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#707477">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                </svg>
                ';
                break;
            case AdminFeed::EVENT_TYPE_BULK_PAYOUT_CREATED:
                $icon = '
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#707477">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                /svg>
                ';
                break;
        }

        ob_start();
    ?>

        <div class="feed-item">
            <div class="feed-item_step">
                <svg width="6" height="6" viewBox="0 0 6 6" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="3" cy="3" r="3" fill="#B7BBBE" />
                </svg>
            </div>
            <div class="feed-item_container">
                <div class="feed-item_icon">
                    <?php echo ($icon); ?>
                </div>
                <div class="feed-item_body">
                    <span><?php echo ($feed_event['html_message']) ?></span>
                    <a href="<?php echo ($feed_event['action']['url']) ?>"><?php echo ($feed_event['action']['label']) ?></a>
                </div>
            </div>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Returns the body of the feed for an empty/brand new feed.
     * 
     * @return string
     */
    private static function render_empty_feed()
    {
        ob_start();
    ?>
        <div class="feed-empty">
        <div class="feed-item">
            <div class="feed-item_step">
            </div>
            <div class="feed-item_container">
                <div class="feed-item_body">
                    <p>
                        Your feed is empty!
                    </p>
                    <p class="empty-feed-middle">
                        This is where you'll see all of your recent activity such as affiliate signups, referrals, and more.
                    </p>

                    <p>
                        We'd recommend getting your program started by <a href="https://solidaffiliate.com/acquiring-affiliates/" target="_blank">acquiring affiliates</a>.
                    </p>
                </div>
            </div>
        </div>
        </div>
    <?php
        return ob_get_clean();
    }


    /**
     * TODO we'll need to handle the case where there are NO admin notification. 
     * Probably need to pull more out of the above ^^^ html into this function.
     * 
     * @param AdminNotificationData[] $admin_notifications
     * @return string
     */
    private static function render_admin_notifications($admin_notifications)
    {
        $admin_notifications_html_joined = implode('', array_map([self::class, 'render_admin_notification'], $admin_notifications));

        ob_start();
    ?>
        <div class="sld_dashboard_notifications">
            <?php if (!empty($admin_notifications_html_joined)) : ?>
                <?php echo ($admin_notifications_html_joined); ?>
            <?php else : ?>
                <?php _e('No notifications! Pat yourself on the back for having a squeaky clean dashboard.', 'solid-affiliate') ?>

            <?php endif; ?>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * @param AdminNotificationData $admin_notification
     * @return string
     */
    private static function render_admin_notification($admin_notification)
    {
        ob_start();
    ?>
        <div class="notification-item">
            <div class="notification-item_title"><?php echo ($admin_notification['title']) ?></div>
            <div class="notification-item_body"><?php echo ($admin_notification['html_message']) ?></div>
            <div class="notification-item_actions">
                <a href="<?php echo ($admin_notification['action_1']['url']) ?>"><?php echo ($admin_notification['action_1']['label']) ?></a>
                <?php if (!empty($admin_notification['action_2'])) : ?>
                    <a href="<?php echo ($admin_notification['action_2']['url']) ?>"><?php echo ($admin_notification['action_2']['label']) ?></a>
                <?php endif; ?>
            </div>
        </div>
    <?php
        return ob_get_clean();
    }


    /**
     * 
     * @param SmartTipData[] $smart_tips
     * @return string
     */
    private static function render_smart_tips($smart_tips)
    {
        $smart_tips_html_joined = implode('', array_map([self::class, 'render_smart_tip'], $smart_tips));

        ob_start();
    ?>
        <?php echo ($smart_tips_html_joined); ?>
    <?php
        return ob_get_clean();
    }

    /**
     * @param SmartTipData $smart_tip
     * @return string
     */
    private static function render_smart_tip($smart_tip)
    {
        ob_start();
    ?>
        <div class="sld_dashboard-column_item">
            <div class="sld_dashboard_slug"><?php echo ($smart_tip['title']) ?></div>
            <div class="sld_dashboard_box"><?php echo ($smart_tip['html_message']) ?></div>
        </div>
    <?php
        return ob_get_clean();
    }
    /**
     * @param InsightsData[] $insight
     * @return string
     */
    private static function render_insights($insight)
    {
        ob_start();
    ?>
        <div class="sld_dashboard-column_item" x-data="{ AllTimeInsights: true, MonthlyInsights: false }">
            <div class="sld_dashboard_slug">
                <a class="insights_date-range" x-cloak @click="MonthlyInsights = !MonthlyInsights" x-text="MonthlyInsights ? 'Last 30 days' : 'All time'"></a>
            </div>
            <div class="sld_dashboard_box" x-cloak>
                <div class="insights-item">
                    <span x-show="!MonthlyInsights"><mark><?php echo ($insight['all_time']['formatted_gross_affiliate_revenue']) ?></mark></span>
                    <span x-show="MonthlyInsights"><mark><?php echo ($insight['30_day']['formatted_gross_affiliate_revenue']) ?></mark></span>
                    <span class="insights-item_title"><?php _e('Gross affiliate-generated revenue', 'solid-affiliate') ?></span>
                </div>
                <div class="insights-item">
                    <span x-show="MonthlyInsights"><?php echo ($insight['30_day']['formatted_net_affiliate_revenue']) ?></span>
                    <span x-show="!MonthlyInsights"><?php echo ($insight['all_time']['formatted_net_affiliate_revenue']) ?></span>
                    <span class="insights-item_title"><?php _e('Net affiliate-generated revenue', 'solid-affiliate') ?></span>
                </div>
                <div class="insights-item">
                    <span x-show="MonthlyInsights"><?php echo ($insight['30_day']['formatted_total_paid_earnings']) ?></span>
                    <span x-show="!MonthlyInsights"><?php echo ($insight['all_time']['formatted_total_paid_earnings']) ?></span>
                    <span class="insights-item_title"><?php _e('Paid affiliate earnings', 'solid-affiliate') ?></span>
                </div>
                <div class="insights-item">
                    <span x-show="MonthlyInsights"><?php echo ($insight['30_day']['formatted_total_visits']) ?></span>
                    <span x-show="!MonthlyInsights"><?php echo ($insight['all_time']['formatted_total_visits']) ?></span>
                    <span class="insights-item_title"><?php _e('Visits', 'solid-affiliate') ?></span>
                </div>
                <div class="insights-item">
                    <span x-show="MonthlyInsights"><?php echo ($insight['30_day']['formatted_total_referrals']) ?></span>
                    <span x-show="!MonthlyInsights"><?php echo ($insight['all_time']['formatted_total_referrals']) ?></span>
                    <span class="insights-item_title"><?php _e('Referrals', 'solid-affiliate') ?></span>
                </div>
                <div class="insights-item">
                    <span x-show="MonthlyInsights"><?php echo ($insight['30_day']['formatted_total_approved_affiliates']) ?></span>
                    <span x-show="!MonthlyInsights"><?php echo ($insight['all_time']['formatted_total_approved_affiliates']) ?></span>
                    <span class="insights-item_title"><?php _e('Approved affiliates', 'solid-affiliate') ?></span>
                </div>
            </div>
        </div>
<?php
        return ob_get_clean();
    }

    /**
     * @return string
     */
    public static function welcome_message()
    {
        $messages = [
            'Make that money, honey!',
            'You are the captain now!',
            'Practice the supreme art of affiliate marketing.',
            'Affiliate opportunities are like buses, there is always another one coming.',
            'Why did the chicken cross the road? To join your affiliate program.',
            'You are the best affiliate manager ever!',
            "Stop! It's affiliate marketing time!",
            "99 problems but an affiliate link ain't one.",
            "For every action, there is an equal and opposite affiliate commission.",
            "Let's get down to affiliate business.",
            "Pardon me, do you have a moment to talk about our affiliate program?",
            "Affiliate marketing is the best marketing.",
            "Can I interest you in an affiliate link?",
            "Feel the power of affiliate marketing!",
            "Pssst... I have a secret for you. You're an affiliate marketing genius!",
            "Look at you, you're an affiliate marketing rockstar!",
            "23andMe: You are 99.9% affiliate marketer.",
            "You're a natural at affiliate marketing.",
            "#1 Affiliate Marketer in the World",
            "Live, laugh, love, and affiliate.",
            "Aaah, affiliate marketing.",
            "Achieve your affiliate marketing dream.",
            "Admit it, you're an affiliate marketing genius.",
            "Ay caramba! Affiliate marketing!",
            "All your affiliate marketing dreams will come true.",
            "An affiliate a day keeps the doctor away.",
            "Affiliate marketing, the best thing since sliced bread.",
            "Be the affiliate marketer you want to see in the world.",
            "Bffl: Best friends for life. Amfl: Affiliate Marketer for life.",
            "Back to the affiliate grind.",
            "Can't stop, won't stop affiliate marketing.",
            "Daydreaming about affiliate marketing.",
            "One day, you'll be an affiliate marketing legend.",
            "Today is a good day to be an affiliate marketer.",
            "Affiliates love you, and your amazing products.",
        ];

        // pick a random message
        $message = $messages[rand(0, count($messages) - 1)];
        return $message;
    }
}
