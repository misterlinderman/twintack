<?php

namespace SolidAffiliate\Lib\Integrations;

use SolidAffiliate\Lib\Links;
use SolidAffiliate\Lib\PurchaseTracking;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\SolidLogger;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\VO\OrderDescription;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Referral;
use WC_Subscriptions_Order;

/**
 * Solid Affiliate <> WooCommerce Subscriptions Integration
 *
 * Helpful links:
 * https://woocommerce.com/document/subscriptions/develop/
 */
class WooCommerceSubscriptionsIntegration
{
    const SOURCE = 'woocommerce-subscriptions';
    const REFERRAL_TYPE = 'subscription_renewal';

    /**
     * Get's called on the on_init hook.
     *
     * @return void
     */
    public static function register_hooks()
    {
        $is_woocommerce_subscriptions_active = class_exists('WC_Subscriptions');
        if (!Settings::get(Settings::KEY_IS_ENABLE_RECURRING_REFERRALS) || !($is_woocommerce_subscriptions_active)) {
            return;
        }

        add_action('woocommerce_subscription_renewal_payment_complete', [self::class, 'handle_woocommerce_subscription_renewal_payment_complete'], 10, 2);
        add_action('woocommerce_subscriptions_switch_completed', [self::class, 'handle_woocommerce_subscriptions_switch_completed'], 10, 1);

        add_filter("solid_affiliate/woocommerce_integration/order_item/commissionable_amount", [self::class, 'filter_woocommerce_integration_order_item_commissionable_amount'], 10, 3);

        // These actions will trigger the updating of a Referral from status "draft" -> "unpaid".
        add_action('woocommerce_order_status_processing', [self::class, 'set_referrals_status_from_draft_or_rejected_to_unpaid'], 10);
        add_action('woocommerce_order_status_completed', [self::class, 'set_referrals_status_from_draft_or_rejected_to_unpaid'], 10);
        add_action('woocommerce_order_status_shipped', [self::class, 'set_referrals_status_from_draft_or_rejected_to_unpaid'], 10);

        // Refunds
        add_action('woocommerce_order_status_completed_to_refunded', [self::class, 'handle_woocommerce_order_refunded'], 10);
        add_action('woocommerce_order_status_on-hold_to_refunded', [self::class, 'handle_woocommerce_order_refunded'], 10);
        add_action('woocommerce_order_status_processing_to_refunded', [self::class, 'handle_woocommerce_order_refunded'], 10);

        // Cancellations
        add_action('woocommerce_order_status_completed_to_cancelled', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('woocommerce_order_status_on-hold_to_cancelled', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('woocommerce_order_status_processing_to_cancelled', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('woocommerce_order_status_pending_to_cancelled', [self::class, 'handle_woocommerce_order_cancelled'], 10);

        // Failures
        add_action('woocommerce_order_status_completed_to_failed', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('woocommerce_order_status_on-hold_to_failed', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('woocommerce_order_status_processing_to_failed', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('woocommerce_order_status_pending_to_failed', [self::class, 'handle_woocommerce_order_cancelled'], 10);

        // Trash (some people will do this instead of cancel)
        add_action('wc-completed_to_trash', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('wc-on-hold_to_trash', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('wc-processing_to_trash', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('wc-pending_to_trash', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('woocommerce_delete_order', [self::class, 'handle_woocommerce_order_cancelled'], 10);
        add_action('woocommerce_trash_order', [self::class, 'handle_woocommerce_order_cancelled'], 10);

        add_action('woocommerce_before_calculate_totals', [self::class, 'handle_before_calculate_cart_totals'], 10, 1);


        /**
         * Meta Box on Admin Order / Subscription pages
         */
        add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);
    }


    /**
     * Triggered when a renewel payment is made on an already active WooCommerce subscription.
     *
     * This will not be called during the initial purchase of a subscription, only on renewels.
     * The initial purchase of a subscription is handled by WooCommerceIntegration, just like any
     * regular order. We only handle the subsequent renewel orders here.
     *
     *
     * Notes:
     * WooCommerce Subscriptions makes 3 different order types to represent Subscriptions and their Renewels:
     *   - Parent order, which is linked to one:
     *     - Subscription order, which can be linked to many:
     *      - Renewel orders
     *
     * The Parent order represents the initial purchase of the Subscription. It's created by WooCommerce upon initial purchase.
     *   The regular WooCommerce integration handles creating the Referral for the Parent order.
     *
     * The Subscription order is created at the same time as the Parent order,
     *   and it represents the subscription itself (dates and intervals and such).
     *
     * The Renewel orders are created any time there's a renewel of an existing subscription.
     *   For example, a monthly subscription service would create a Renewel order once per month.
     *   It's these Renewel orders that we need to handle.
     *
     * @since 1.0.0
     *
     * @param \WC_Subscription $subscription This is the description of the parameter.
     * @param \WC_Order $renewal_order object representing the order created to record the renewal.
     *
     * @return boolean This is the description of the return.
     */
    public static function handle_woocommerce_subscription_renewal_payment_complete($subscription, $renewal_order)
    {
        SolidLogger::log("Handling WooCommerce order created event for renewal order #{$renewal_order->get_id()}");

        if (!Settings::get(Settings::KEY_IS_ENABLE_RECURRING_REFERRALS)) {
            SolidLogger::log("Exiting early because recurring referrals are disabled.");
            return false;
        }
        /** @psalm-suppress DocblockTypeContradiction */
        if (!$subscription instanceof \WC_Subscription) {
            SolidLogger::log("Exiting early because \$subscription is not an instance of \WC_Subscription");
            return false; // Defensively checking the type. Can't trust hooks and filters.
        }

        /** @psalm-suppress DocblockTypeContradiction */
        if (!$renewal_order instanceof \WC_Order) {
            SolidLogger::log("Exiting early because \$renewal_order is not an instance of \WC_Order");
            return false;
        }

        $order_description = self::order_description_for_renewal($renewal_order, $subscription);
        SolidLogger::log("Order description for renewal order #{$renewal_order->get_id()} is: " . print_r($order_description, true));

        if ($order_description->parent_order_id == 0) {
            SolidLogger::log("Exiting early because \$order_description->parent_order_id is 0");
            return false;
        }

        if (!$order_description->is_renewal_order) {
            SolidLogger::log("Exiting early because \$order_description->is_renewal_order is false");
            return false; // defensive bail, incase somehow it's not a renewal order.
        }

        // Hand off the OrderDescription to PurchaseTracking, which will determine if an Affiliate
        // earned a Referral for this Order, and if so it will calculate the amount and create a Referral
        // record in the Database, and return the Referral ID.
        $eitherReferralId = PurchaseTracking::potentially_reward_a_referral_for($order_description);

        if ($eitherReferralId->isRight) {
            $referral = Referral::find($eitherReferralId->right);
            if ($referral instanceof Referral) {
                SolidLogger::log("Referral #{$referral->id} was created for renewal order #{$renewal_order->get_id()}");
                Referral::updateInstance($referral, ['status' => Referral::STATUS_UNPAID]);
            }
            Referral::add_order_completed_note($eitherReferralId->right);
            return true;
        } else {
            WooCommerceIntegration::add_order_note_no_referral_created($order_description->order_id, $eitherReferralId->left);
        }

        return false;
    }

    /**
     * @param \WC_Order $switch_order
     * 
     * @return boolean
     */
    public static function handle_woocommerce_subscriptions_switch_completed($switch_order)
    {
        if (!Settings::get(Settings::KEY_IS_ENABLE_RECURRING_REFERRALS)) {
            return false;
        }

        /** @psalm-suppress DocblockTypeContradiction */
        if (!$switch_order instanceof \WC_Order) {
            return false;
        }

        // check some things
        $order_description = self::order_description_for_switch($switch_order);

        if ($order_description->parent_order_id == 0) {
            return false;
        }

        if (!$order_description->is_switch_order) {
            return false;
        }

        $eitherReferralId = PurchaseTracking::potentially_reward_a_referral_for($order_description);

        if ($eitherReferralId->isRight) {
            $referral = Referral::find($eitherReferralId->right);
            if ($referral instanceof Referral) {
                Referral::updateInstance($referral, ['status' => Referral::STATUS_UNPAID]);
            }
            Referral::add_order_completed_note($eitherReferralId->right);
            return true;
        } else {
            WooCommerceIntegration::add_order_note_no_referral_created($order_description->order_id, $eitherReferralId->left);
        }

        return false;
    }


    /**
     * @return void
     */
    public static function add_meta_boxes()
    {
        // Subscription Orders
        $icon = "<img class='solid-affiliate_meta-box_icon' src='https://solidaffiliate.com/brand/logo-icon.svg' alt=''>";
        $callback = [self::class, 'admin_shop_subscription_meta_box'];
        add_meta_box('solid-affiliate_meta-box_shop-subscription', "<span class='solid-affiliate_meta-box-title'>" . $icon . "Solid Affiliate</span>", $callback, 'shop_subscription', 'side', 'high');

        // Parents + Renewal Orders
        add_action("solid_affiliate/woocommerce/admin_shop_order_meta_box/after", [self::class, 'admin_shop_order_meta_box'], 10, 1);
    }

    /**
     * @param \WP_Post $post
     * @return void
     */
    public static function admin_shop_subscription_meta_box($post)
    {
        $subscription_id = $post->ID;
        if (!wcs_is_subscription($subscription_id)) {
            echo "This is not a Subscription";
            return;
        }

        /** @var \WC_Subscription|false $parent_order */
        $maybe_wc_subscription = wcs_get_subscription($subscription_id);
        if (!$maybe_wc_subscription) {
            echo "Error retrieving subscription";
            return;
        }
        $parent_order = $maybe_wc_subscription->get_parent();
        if (!$parent_order instanceof \WC_Order) {
            echo "Error retrieving parent order";
            return;
        }

        $parent_order_link = Links::render($parent_order, 'parent order');

        echo "<h4>Admin Helper - Subscription #{$subscription_id}</h4>";
        if (self::is_subscription_referred_by_affiliate($subscription_id)) {
            $affiliate = self::get_referring_affiliate_for_subscription_id($subscription_id);
            if ($affiliate) {
                $affiliate_link = Links::render($affiliate, 'affiliate');

                echo "<p>This subscription was referred by an affiliate. The {$parent_order_link} of this subscription contains an associated referral.</p>";
                echo "<p>The {$affiliate_link} will receive referrals for subsequent renewals of this subscription.</p>";
            }
        } else {
            echo "<p>This subscription was not referred by an affiliate. The {$parent_order_link} of this subscription does not contain an associated referral.</p>";
        }
    }

    /**
     * @param int $wc_order_id
     *
     * @return void
     */
    public static function admin_shop_order_meta_box($wc_order_id)
    {
        $maybe_referrals = Referral::where([
            'order_id' => $wc_order_id,
            'status' => ['operator' => 'IN', 'value' => [Referral::STATUS_PAID, Referral::STATUS_UNPAID]]
        ]);
        // Cases to cover:
        // 1. Order is a renewal order
        // 2. Order is a subscription order
        // 3. Order is a subscription parent order
        // 4. none
        if (wcs_order_contains_subscription($wc_order_id, 'parent')) {
            echo ("<p>WooCommerce Subscriptions Integration:</p>");
            if (empty($maybe_referrals)) {
                echo ('<p>This subscription parent order does not contain any referrals, therefor it will not generate subscription renewal referrals for any subsequent subscription renewals.</p>');
            } else {
                echo ('<p>This subscription parent order was referred by an affiliate, therefor it will generate subscription renewal referrals for subsequent renewals.</p>');
            }
        }

        if (self::get_is_renewal_order_for($wc_order_id)) {
            echo ("<p>WooCommerce Subscriptions Integration:</p>");

            /** @var \WC_Subscription[] $maybe_subscriptions */
            $maybe_subscriptions = wcs_get_subscriptions_for_renewal_order($wc_order_id);

            if (empty($maybe_subscriptions)) {
            } else {
                /**
                 * @psalm-suppress MixedArgument
                 * @psalm-suppress MixedAssignment
                 */
                $subscription = array_values($maybe_subscriptions)[0];
                /**
                 * @psalm-suppress RedundantConditionGivenDocblockType
                 */
                if ($subscription instanceof \WC_Subscription) {
                    $subscription_id = (int)$subscription->get_id();
                    $subscription_link = Links::render($subscription, 'subscription');

                    if (self::is_subscription_referred_by_affiliate($subscription_id)) {
                        $maybe_affiliate = self::get_referring_affiliate_for_subscription_id($subscription_id);

                        echo "<p>The {$subscription_link}'s parent order contains a referral.</p>";
                        if ($maybe_affiliate) {
                            $affiliate_link = Links::render($maybe_affiliate, 'affiliate');
                            echo "<p>The {$affiliate_link} will receive referrals for subsequent renewals of this subscription.</p>";
                        } else {
                            echo "<p>There was an error finding the associated affiliate.</p>";
                        }
                    } else {
                        echo "<p>This renewal order was not referred by an affiliate. The {$subscription_link} of this renewal order does not contain an associated referral.</p>";
                    }
                } else {
                    echo "<p>There was an error finding the associated subscription.</p>";
                }
            }
        }
    }

    /**
     * @param int $subscription_id
     * @return boolean
     */
    public static function is_subscription_referred_by_affiliate($subscription_id)
    {
        $maybe_wc_subscription = wcs_get_subscription($subscription_id);
        if ($maybe_wc_subscription instanceof \WC_Subscription) {
            $parent_order_id = $maybe_wc_subscription->get_parent_id();
            $original_subscription_referral = Referral::find_where([
                'order_id' => $parent_order_id,
                'status' => ['operator' => 'IN', 'value' => [Referral::STATUS_PAID, Referral::STATUS_UNPAID]]
            ]);

            return ($original_subscription_referral instanceof Referral);
        } else {
            return false;
        }
    }

    /**
     * @param int $subscription_id
     * @return Affiliate|null
     */
    public static function get_referring_affiliate_for_subscription_id($subscription_id)
    {
        $subscription = wcs_get_subscription($subscription_id);
        if ($subscription instanceof \WC_Subscription) {
            $parent_order_id = $subscription->get_parent_id();
            $maybe_original_subscription_referral = Referral::find_where([
                'order_id' => $parent_order_id,
                'status' => ['operator' => 'IN', 'value' => [Referral::STATUS_PAID, Referral::STATUS_UNPAID]]
            ]);
            if ($maybe_original_subscription_referral instanceof Referral) {
                return Affiliate::find($maybe_original_subscription_referral->affiliate_id);
            } else {
                return null;
            }
        } else {
            return null;
        }
    }



    /**
     * Undocumented function
     *
     * @param float $commissionable_amount
     * @param \WC_Order_Item_Product $wc_order_item
     * @param \WC_Order $wc_order
     *
     * @return float
     */
    public static function filter_woocommerce_integration_order_item_commissionable_amount($commissionable_amount, $wc_order_item, $wc_order)
    {
        // // Handles the case where the subscription has a signup fee. We need to make sure that the commissionable amount does not include the signup fee.
        // if (Settings::get(Settings::KEY_IS_ENABLE_RECURRING_REFERRALS) && Settings::get(Settings::KEY_IS_EXCLUDE_SIGNUP_FEE_RECURRING_REFERRALS)) {
        // }
        return $commissionable_amount;
    }

    /**
     * This function handles creating an OrderDescription for a renewal order and it's subscription.
     * Currently it relies on the WooCommerce Integration, but changes a couple values.
     *
     * @param \WC_Order $renewal_order
     * @param \WC_Subscription $subscription
     *
     * @return OrderDescription
     */
    public static function order_description_for_renewal($renewal_order, $subscription)
    {
        // Get the top-level parent Order of this subscription and renewel.
        // It represents the initial purchase of the subscription,
        // and we need it to determine if an Affiliate referred the initial subscription.
        $subscription_parent_order_id = (int)$subscription->get_parent_id();

        $renewal_order_id = $renewal_order->get_id();
        $order_description = WooCommerceIntegration::order_description_for($renewal_order_id);
        $order_description->parent_order_id = $subscription_parent_order_id;
        $order_description->order_source = self::SOURCE;
        return $order_description;
    }

    /**
     * This function handles creating an OrderDescription for a switch order and it's subscription.
     * Currently it relies on the WooCommerce Integration, but changes a couple values.
     *
     * @param \WC_Order $switch_order
     * @param \WC_Subscription $subscription
     *
     * @return OrderDescription
     */
    public static function order_description_for_switch($switch_order)
    {
        $switch_order_id = $switch_order->get_id();
        $order_description = WooCommerceIntegration::order_description_for($switch_order_id);

        $subscription_parent_order_id = 0;
        $maybe_subscriptions = wcs_get_subscriptions_for_switch_order($switch_order);
        if (empty($maybe_subscriptions)) {
            $subscription_parent_order_id = 0;
        } else {
            /**
             * This is currently assuming there is just one subscription per switch order.
             * This whole things is a bit of a hack, but it works for now. We need to handle more complex switch orders.
             *
             * @psalm-suppress MixedAssignment
             */
            foreach ($maybe_subscriptions as $subscription) {
                if ($subscription instanceof \WC_Subscription) {
                    $subscription_parent_order_id = (int)$subscription->get_parent_id();
                    break;
                }
            }
        }

        $order_description->parent_order_id = $subscription_parent_order_id;
        $order_description->order_source = self::SOURCE;
        return $order_description;
    }

    /**
     * Handles the processing status of a WooCommerce order.
     *
     * Long description of the thing.
     *
     * @since 1.0.0
     *
     * @param int $woocommerce_order_id This is the description of the parameter.
     *
     * @return boolean Whether or not we ended up creating a Referral for this order.
     */
    public static function set_referrals_status_from_draft_or_rejected_to_unpaid($woocommerce_order_id)
    {
        $maybe_referrals = Referral::where(['order_source' => self::SOURCE, 'order_id' => $woocommerce_order_id, 'status' => Referral::STATUS_DRAFT]);
        Referral::updateInstances($maybe_referrals, ['status' => Referral::STATUS_UNPAID]);

        $maybe_referrals = Referral::where(['order_source' => self::SOURCE, 'order_id' => $woocommerce_order_id, 'status' => Referral::STATUS_REJECTED]);
        Referral::updateInstances($maybe_referrals, ['status' => Referral::STATUS_UNPAID]);

        return true;
    }


    /**
     * @param int $woocommerce_order_id
     * @return bool
     */
    public static function get_is_renewal_order_for($woocommerce_order_id)
    {
        try {
            // This function would come from WooCommerce Subscriptions plugin
            if (function_exists('wcs_order_contains_renewal')) {
                /** @psalm-suppress MixedAssignment */
                $response = wcs_order_contains_renewal($woocommerce_order_id);
                if ($response === TRUE) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param int $woocommerce_order_id
     * @return bool
     */
    public static function get_is_switch_order_for($woocommerce_order_id)
    {
        try {
            if (function_exists('wcs_order_contains_switch')) {
                /** @psalm-suppress MixedAssignment */
                $response = wcs_order_contains_switch($woocommerce_order_id);
                if ($response === TRUE) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Undocumented public static function
     *
     * @param int $woocommerce_order_id
     * @return bool
     */
    public static function handle_woocommerce_order_refunded($woocommerce_order_id)
    {
        /**
         * Sometimes WooCommerce will pass a WP_Post object instead of an ID.
         *
         * @psalm-suppress DocblockTypeContradiction
         **/
        if ($woocommerce_order_id instanceof \WP_Post) {
            $woocommerce_order_id = $woocommerce_order_id->ID;
        }

        $maybe_referral = Referral::find_where(['order_source' => self::SOURCE, 'order_id' => $woocommerce_order_id]);

        if (is_null($maybe_referral)) {
            return false;
        } else {
            Referral::updateInstance(
                $maybe_referral,
                ['order_refunded_at' => (string)current_time('mysql', true)]
            );
            return Referral::reject_unless_already_paid($maybe_referral);
        }
    }

    /**
     * @param int $woocommerce_order_id
     * @return bool
     */
    public static function handle_woocommerce_order_cancelled($woocommerce_order_id)
    {
        /**
         * Sometimes WooCommerce will pass a WP_Post object instead of an ID.
         *
         * @psalm-suppress DocblockTypeContradiction
         **/
        if ($woocommerce_order_id instanceof \WP_Post) {
            $woocommerce_order_id = $woocommerce_order_id->ID;
        }

        $maybe_referral = Referral::find_where(['order_source' => self::SOURCE, 'order_id' => $woocommerce_order_id]);
        if (is_null($maybe_referral)) {
            return false;
        } else {
            return Referral::reject_unless_already_paid($maybe_referral);
        }
    }

    /**
     * Defensive logic to prevent some troublesome combinations of items in the same cart.
     * 
     * If there is a subscription renewal in the cart, there can be nothing else in the cart.
     * 
     * Also, if there is a subscription switch in the cart, there can be nothing else in the cart.
     * 
     * @param \WC_Cart $cart
     * 
     * @return void
     */
    public static function handle_before_calculate_cart_totals($cart)
    {
        $is_woocommerce_subscriptions_active = class_exists('WC_Subscriptions') && class_exists('WC_Subscriptions_Cart') && class_exists('WC_Subscriptions_Switcher');
        if (!Settings::get(Settings::KEY_IS_ENABLE_RECURRING_REFERRALS) || !($is_woocommerce_subscriptions_active)) {
            return;
        }

        $amount_of_items_in_cart = $cart->get_cart_contents_count();
        if ($amount_of_items_in_cart <= 1) {
            return; // early bail because we know there is only one item in the cart
        }

        $maybe_cart_item_renewal = wcs_cart_contains_early_renewal();
        if (is_array($maybe_cart_item_renewal) && !empty($maybe_cart_item_renewal)) {
            // remove just one item from the cart, the subscription renewal
            $key = isset($maybe_cart_item_renewal['key']) ? (string)$maybe_cart_item_renewal['key'] : null;
            if (!empty($key)) {
                $cart->remove_cart_item($key);
                wc_add_notice(__('Subscription upgrades cannot be added to this cart with other items. This is due to a compatibility issue with the Solid Affiliate plugin Recurring Referrals feature. To resolve this, please clear your cart and add only the subscription upgrade. Alternatively, you can disable the Solid Affiliate - Recurring Referrals setting or contact our support team at team@solidaffiliate.com for further assistance.', 'solid-affiliate'), 'error');
            }
        }

        $maybe_cart_item_switch = \WC_Subscriptions_Switcher::cart_contains_switches();
        if (is_array($maybe_cart_item_switch) && !empty($maybe_cart_item_switch)) {
            $item_key = (string)array_keys($maybe_cart_item_switch)[0];
            if (!empty($item_key)) {
                $cart->remove_cart_item($item_key);
                wc_add_notice(__('Subscription renewals cannot be added to this cart with other items. This is due to a compatibility issue with the Solid Affiliate plugin Recurring Referrals feature. To resolve this, please clear your cart and add only the subscription renewal. Alternatively, you can disable the Solid Affiliate - Recurring Referrals setting or contact our support team at team@solidaffiliate.com for further assistance.', 'solid-affiliate'), 'error');
            }
        }
    }

    /**
     * This function will determine if a subscription renewal is within the recurring referral limit window.
     * 
     * @param Referral $original_subscription_referral - The original referral that was created for the initial subscription purchase.
     * @param OrderDescription $renewal_order_description - The description of the renewal order.
     *
     * @return boolean
     */
    public static function is_renewal_within_recurring_referral_time_limit($original_subscription_referral, $renewal_order_description)
    {
        $limit_duration_in_seconds = (int)Settings::get(Settings::KEY_RECURRING_REFERRAL_LIMIT_DURATION_IN_SECONDS_STRING);
        // Add a buffer to the limit duration. This is to account for the fact that there 
        // are often delays in the processing of orders.
        // For example:
        // - the cron job that processes renewals might not run exactly on time.
        // - leap years
        // - variation in month length (i.e. January 31 days, February 28 days)
        // - payment failure retries
        // Since the minimum limit option is 1 week, we can safely add a buffer of 5 days.
        $buffer_in_seconds = 60 * 60 * 24 * 5;

        if ($limit_duration_in_seconds == 0) { // i.e. if there is no limit
            return true;
        }

        $original_order_created_at = WooCommerceIntegration::get_order_created_timestamp_from_order_id($original_subscription_referral->order_id);
        $renewal_order_created_at = WooCommerceIntegration::get_order_created_timestamp_from_order_id($renewal_order_description->order_id);

        // if either of these are false, we can't do the comparison. i.e. if one of the orders doesn't exist for some reason.
        if (!$original_order_created_at || !$renewal_order_created_at) {
            return false;
        }

        // compare the two timestamps
        $time_difference = ($renewal_order_created_at + $buffer_in_seconds) - $original_order_created_at;
        if ($time_difference < $limit_duration_in_seconds) {
            return true;
        } else {
            return false;
        }
    }
}
