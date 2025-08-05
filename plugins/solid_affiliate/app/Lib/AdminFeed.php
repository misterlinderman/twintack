<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\BulkPayout;
use SolidAffiliate\Models\Payout;



/**
 * @psalm-type FeedEventType = self::EVENT_TYPE_*
 * @psalm-type FeedEvent = array{
 *  type: FeedEventType,
 *  timestamp: string,
 *  html_message: string,
 *  action: array{label:string, url:string},
 * }
 * 
 * @psalm-type FeedData = array<string, FeedEvent[]>
 * 
 */
class AdminFeed
{
    const EVENT_TYPE_REFERRAL_CREATED = "referral_created";
    const EVENT_TYPE_REFERRAL_AUTO_REJECTED = "referral_auto_rejected";
    const EVENT_TYPE_AFFILIATE_CREATED = "affiliate_created";
    const EVENT_TYPE_BULK_PAYOUT_CREATED = "bulk_payout_created";


    /**
     * Do a bunch of Database queries to get the data for the feed
     * 
     * Types
     * [] "referral_created"
     * [] "referral_auto_rejected"
     * [] "affiliate_created"
     * [] "bulk_payout_created"
     * 
     * TODO
     * [] cache
     * [] check performance on large sites
     * [] infinite scroll (?)
     * 
     * @param int $limit - The ATTEMPTED MINIMUM number of events to return within FeedData. It may return up to 4x this number.
     * @return FeedData
     */
    public static function construct_feed($limit = 100)
    {
        /////////////////////////////////////////////
        // For each of the event types, I need to find the created_at timestamp of the last event within the limit (for example 10).
        // Then I need to find the earliest of those timestamps, to find the cutoff point for the query.
        // Then I need to add that cutoff point to the rest of the queiries below.
        
        $referrals_created = Referral::where([
            'limit' => $limit,
            'order_by' => 'created_at',
            'order' => 'DESC',
            'status' => [
                'operator' => 'IN',
                'value' => Referral::STATUSES_ALL_BESIDES_REJECTED
            ],
        ]);
        $referrals_created_at_cutoff = (count($referrals_created) == $limit) ? array_reverse($referrals_created)[0]->created_at : null;

        $referrals_auto_rejected = Referral::where([
            'limit' => $limit,
            'order_by' => 'created_at',
            'order' => 'DESC',
            'status' => Referral::STATUS_REJECTED,
            'raw' => 'order_refunded_at IS NOT NULL'
        ]);
        $referrals_auto_rejected_at_cutoff = (count($referrals_auto_rejected) == $limit) ? array_reverse($referrals_auto_rejected)[0]->created_at : null;
        // $referrals_auto_rejected_at_cutoff = array_reverse($referrals_auto_rejected)[0]->created_at;

        
        $affiliates_created = Affiliate::where([
            'limit' => $limit,
            'order_by' => 'created_at',
            'order' => 'DESC'
        ]);
        $affiliates_created_at_cutoff = (count($affiliates_created) == $limit) ? array_reverse($affiliates_created)[0]->created_at : null;
        // $affiliates_created_at_cutoff = array_reverse($affiliates_created)[0]->created_at;


        $bulk_payout_created = BulkPayout::where([
            'limit' => $limit,
            'order_by' => 'created_at',
            'order' => 'DESC'
        ]);
        $bulk_payout_created_at_cutoff = (count($bulk_payout_created) == $limit) ? array_reverse($bulk_payout_created)[0]->created_at : null;
        // $bulk_payout_created_at_cutoff = array_reverse($bulk_payout_created)[0]->created_at;

        $cutoff_created_at = max($referrals_created_at_cutoff, $referrals_auto_rejected_at_cutoff, $affiliates_created_at_cutoff, $bulk_payout_created_at_cutoff);
        $cutoff_created_at = empty($cutoff_created_at) ? '1970-01-01 00:00:00' : $cutoff_created_at;
        /////////////////////////////////////////////////////////////////////////////
        


        ///////////////////////////////////////////////////
        // handle referral_created
        $referrals_created = Referral::where([
            'limit' => $limit,
            'order_by' => 'created_at',
            'order' => 'DESC',
            'status' => [
                'operator' => 'IN',
                'value' => Referral::STATUSES_ALL_BESIDES_REJECTED
            ],
            'created_at' => ['operator' => '>=', 'value' => $cutoff_created_at]
        ]);

        $referral_created_events = array_map([self::class, 'referral_to_referral_created_feed_event'], $referrals_created);
        ///////////////////////////////////////////////////

        ///////////////////////////////////////////////////
        // handle referral_auto_rejected
        $referrals_auto_rejected = Referral::where([
            'limit' => $limit,
            'order_by' => 'created_at',
            'order' => 'DESC',
            'status' => Referral::STATUS_REJECTED,
            'order_refunded_at' => ['operator' => '>=', 'value' => $cutoff_created_at],
            'raw' => 'order_refunded_at IS NOT NULL'
        ]);

        $referrals_auto_rejected_events = array_map([self::class, 'referral_to_referral_auto_rejected_feed_event'], $referrals_auto_rejected);

        /////////////////////////////////////////////////
        // handle affiliate_created
        // TODO ask ayman about this query
        $affiliates_created = Affiliate::where([
            'limit' => $limit,
            'order_by' => 'created_at',
            'created_at' => ['operator' => '>=', 'value' => $cutoff_created_at],
            'order' => 'DESC'
        ]);

        $affiliates_created_events = array_map([self::class, 'affiliate_to_affiliate_created_feed_event'], $affiliates_created);

        /////////////////////////////////////////////////
        // handle payout_created

        $bulk_payout_created = BulkPayout::where([
            'limit' => $limit,
            'order_by' => 'created_at',
            'created_at' => ['operator' => '>=', 'value' => $cutoff_created_at],
            'order' => 'DESC'
        ]);

        $payouts_created_events = array_map([self::class, 'payout_to_payout_created_feed_event'], $bulk_payout_created);


        /////////////////////////////////////////////////
        // Merge all the events into one array, and then turn that into FeedData
        $all_events = array_merge(
            $referral_created_events,
            $referrals_auto_rejected_events,
            $affiliates_created_events,
            $payouts_created_events
        );


        $feed_data = self::feed_events_to_feed_data($all_events);
        return $feed_data;
    }

    /**
     * @param Referral $referral
     * @return FeedEvent
     */
    public static function referral_to_referral_created_feed_event($referral)
    {
        $commissions_amount_formatted = Formatters::money($referral->commission_amount);

        $maybe_affiliate = Affiliate::find($referral->affiliate_id);
        if ($maybe_affiliate) {
            $user = get_userdata($maybe_affiliate->user_id);
            $username = $user ? $user->user_nicename : __('User Not Found', 'solid-affiliate');
            $affiliate_link = Links::render($maybe_affiliate, $username);
        } else {
            $affiliate_link = 'Affiliate Not Found';
        }

        $action_url = URLs::edit(Referral::class, $referral->id);

        try {
            $order = new \WC_Order($referral->order_id);
            $order_link = Links::render($order, "Order (#{$referral->order_id})");
        } catch (\Exception $e) {
            $order_link = "Order (#{$referral->order_id})";
        }

        $actual = [
            'type' => 'referral_created',
            'timestamp' => $referral->created_at,
            'html_message' => sprintf(__('<strong>%1$s</strong> was referred by <strong>%2$s</strong> earning a commissions of %3$s.', 'solid-affiliate'), $order_link, $affiliate_link, $commissions_amount_formatted),
            'action' => [
                'label' => __('View Referral', 'solid-affiliate'),
                'url' => $action_url,
            ],
        ];

        return $actual;
    }

    /**
     * @param Referral $referral
     * @return FeedEvent
     */
    public static function referral_to_referral_auto_rejected_feed_event($referral)
    {
        $action_url = URLs::edit(Referral::class, $referral->id);
        $referral_link = Links::render($referral, "Referral (#{$referral->id})");

        try {
            $order = new \WC_Order($referral->order_id);
            $order_link = Links::render($order, "associated order (#{$referral->order_id})");
        } catch (\Exception $e) {
            $order_link = "associated order (#{$referral->order_id})";
        }

        $actual = [
            'type' => 'referral_auto_rejected',
            'timestamp' => $referral->order_refunded_at,
            'html_message' => sprintf(__('<strong>%1$s</strong> was rejected. The <strong>%2$s</strong> was refunded.', 'solid-affiliate'), $referral_link, $order_link),
            'action' => [
                'label' => __('View Referral', 'solid-affiliate'),
                'url' => $action_url,
            ],
        ];

        return $actual;
    }

    /**
     * @param Affiliate $affiliate
     * @return FeedEvent
     */
    public static function affiliate_to_affiliate_created_feed_event($affiliate)
    {
        $user = get_userdata($affiliate->user_id);
        $username = $user ? $user->user_nicename : __('User Not Found', 'solid-affiliate');
        $affiliate_link = Links::render($affiliate, $username);

        $action_url = URLs::edit(Affiliate::class, $affiliate->id);

        $actual = [
            'type' => 'affiliate_created',
            'timestamp' => $affiliate->created_at,
            'html_message' => sprintf(__('<strong>%1$s</strong> is now an affiliate on your site.', 'solid-affiliate'), $affiliate_link),
            'action' => [
                'label' => __('View Affiliate', 'solid-affiliate'),
                'url' => $action_url,
            ],
        ];

        return $actual;
    }

    /**
     * @param BulkPayout $bulkpayout
     * @return FeedEvent
     */
    public static function payout_to_payout_created_feed_event($bulkpayout)
    {
        $amount_formatted = Formatters::money($bulkpayout->total_amount);
        $action_url = URLs::index(BulkPayout::class);


        $payout_link = Links::render($bulkpayout, "Payout (#{$bulkpayout->id})");



        $actual = [
            'type' => 'bulk_payout_created',
            'timestamp' => $bulkpayout->created_at,
            'html_message' => sprintf(__('<strong>%1$s</strong> was processed with total amount of %2$s.', 'solid-affiliate'), $payout_link, $amount_formatted),
            'action' => [
                'label' => __('View Payout', 'solid-affiliate'),
                'url' => $action_url,
            ],
        ];

        return $actual;
    }


    /**
     * Given an arbitrary amount of FeedEvents, this function will sort and group them by date (an individual day).
     * 
     * Example return value:
     *  [
     *    '2019-01-02' => [...FeedEvents..],
     *    '2019-01-01' => [...FeedEvents..],
     *  ]
     * 
     * @param FeedEvent[] $feed_events
     * @return FeedData
     */
    public static function feed_events_to_feed_data($feed_events)
    {
        // sort the events by timestamp in descending order
        usort(
            $feed_events,
            /**
             * @param FeedEvent $a
             * @param FeedEvent $b
             */
            function ($a, $b) {
                return $b['timestamp'] <=> $a['timestamp'];
            }
        );

        $feed_data = [];

        foreach ($feed_events as $feed_event) {
            // parse timestamp to unix timestamp
            $unix = strtotime($feed_event['timestamp']);
            $date = human_time_diff($unix, (int)current_time('U', true));

            if (!isset($feed_data[$date])) {
                $feed_data[$date] = [];
            }

            $feed_data[$date][] = $feed_event;
        }

        return $feed_data;
    }

    /**
     * @return FeedData
     */
    public static function construct_feed_stub()
    {
        return [
            'September 7, 2022' => [
                [
                    'type' => 'referral_created',
                    'timestamp' => '2022-09-07 12:00:00',
                    'html_message' => '<strong>Order (#26289)</strong> was referred by <strong>darrel03</strong> earning a commissions of $56.82.',
                    'action' => [
                        'label' => 'View Referral',
                        'url' => '#',
                    ],
                ],
                [
                    'type' => 'referral_auto_rejected',
                    'timestamp' => '2022-09-07 12:00:00',
                    'html_message' => '<strong>Referral (#232)</strong> was rejected. The <strong>associated order</strong> was refunded.',
                    'action' => [
                        'label' => 'View Referral',
                        'url' => '#',
                    ],
                ],
                [
                    'type' => 'affiliate_created',
                    'timestamp' => '2022-09-07 12:00:00',
                    'html_message' => '<strong>Jason Bézout</strong> has signed up as an affiliate on your site.',
                    'action' => [
                        'label' => 'View Affiliate',
                        'url' => '#',
                    ],
                ]
            ],
            'September 4, 2022' => [
                [
                    'type' => 'referral_created',
                    'timestamp' => '2022-09-04 12:00:00',
                    'html_message' => '<strong>Order (#26278)</strong> was referred by <strong>Jman</strong> earning a commissions of $96.12.',
                    'action' => [
                        'label' => 'View Referral',
                        'url' => '#',
                    ],
                ],
                [
                    'type' => 'referral_created',
                    'timestamp' => '2022-09-04 12:00:00',
                    'html_message' => '<strong>Order (#26276)</strong> was referred by <strong>WokeWP</strong> earning a commissions of $10.00.',
                    'action' => [
                        'label' => 'View Referral',
                        'url' => '#',
                    ],
                ],
            ],
            'September 1, 2022' => [
                [
                    'type' => 'bulk_payout_created',
                    'timestamp' => '2022-09-01 12:00:00',
                    'html_message' => '<strong>Payout (#23)</strong> was processed with total amount of $2,304.05.',
                    'action' => [
                        'label' => 'View Payout',
                        'url' => '#',
                    ],
                ],
            ],
            'August 24, 2022' => [
                [
                    'type' => 'referral_created',
                    'timestamp' => '2022-08-24 12:00:00',
                    'html_message' => '<strong>Order (#26218)</strong> was referred by <strong>Mr. Meow Meow</strong> earning a commissions of $420.69.',
                    'action' => [
                        'label' => 'View Referral',
                        'url' => '#',
                    ],
                ],
                [
                    'type' => 'bulk_payout_created',
                    'timestamp' => '2022-08-24 12:00:00',
                    'html_message' => '<strong>Payout (#22)</strong> was processed with total amount of $552.28.',
                    'action' => [
                        'label' => 'View Payout',
                        'url' => '#',
                    ],
                ],
            ],

        ];
    }
}
