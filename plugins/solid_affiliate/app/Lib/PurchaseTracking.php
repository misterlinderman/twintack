<?php

namespace SolidAffiliate\Lib;

use Exception;
use Illuminate\Contracts\Validation\Validator;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Visit;

use SolidAffiliate\Lib\CommissionCalculator;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\Integrations\WooCommerceSubscriptionsIntegration;
use SolidAffiliate\Lib\VisitTracking;
use SolidAffiliate\Lib\VO\OrderDescription;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Lib\VO\OrderItemDescription;
use SolidAffiliate\Lib\VO\PurchaseTrackingEnv;
use SolidAffiliate\Models\AffiliateProductRate;

class PurchaseTracking
{
    const FAIL_MSG_NO_CHECKS_PASSED = "Referral was not created for this order because none of the referral checks passed.";

    /**
     * Given an OrderDescription + DB state/Request state, potentially creates and stores a Referral.
     * 
     * This is not a pure function. It will look at Cookies, the DB, actions/filters etc 
     * to determine Affiliate attribution.
     * 
     * DO NOT change the order of operations below, 
     * they're order dependent and rely on state + hooks that might get set.
     * 
     * TODO - This function should return a data structure explaining what exactly happened.
     *          If a renewal was rewarded...why?
     *          If a renewal was not rewarded...why?
     *        There are many reasons for a referral not being rewarded.
     *        The reasons more or less all go down in this function.
     *
     * @param OrderDescription $order_description
     * 
     * @return Either<int> The referral_id 
     */
    public static function potentially_reward_a_referral_for($order_description)
    {
        ///////////////////////////////////////////////////////////////////////////
        // Create the PurchaseTrackingEnv data structure, so we can pipe it through
        ///////////////////////////////////////////////////////////////////////////
        $setting_is_new_customer_commissions = (bool)Settings::get(Settings::KEY_IS_NEW_CUSTOMER_COMMISSIONS);
        $setting_is_prevent_self_referrals = (bool)Settings::get(Settings::KEY_IS_PREVENT_SELF_REFERRALS);
        $setting_is_enable_zero_value_referrals = (bool)Settings::get(Settings::KEY_IS_ENABLE_ZERO_VALUE_REFERRALS);
        $cookied_visit_id = VisitTracking::get_cookied_visit_id();

        $purchase_tracking_env = new PurchaseTrackingEnv([
            'setting_is_new_customer_commissions' => $setting_is_new_customer_commissions,
            'setting_is_prevent_self_referrals' => $setting_is_prevent_self_referrals,
            'setting_is_enable_zero_value_referrals' => $setting_is_enable_zero_value_referrals,
            'cookied_visit_id' => $cookied_visit_id,
        ]);

        $either_referral_id = self::potentially_reward_a_referral_for_order_and_env($order_description, $purchase_tracking_env);

        if ($either_referral_id->isRight) {
            /**
             * @param int $referral_id
             */
            do_action("solid_affiliate/purchase_tracking/referral_created", $either_referral_id->right);
        }

        return $either_referral_id;
    }

    /**
     * Given an OrderDescription + DB state/Request state, potentially creates and stores a Referral.
     * 
     * This is not a pure function. It will look at Cookies, the DB, actions/filters etc 
     * to determine Affiliate attribution.
     * 
     * DO NOT change the order of operations below, 
     * they're order dependent and rely on state + hooks that might get set.
     * 
     * TODO - This function should return a data structure explaining what exactly happened.
     *          If a renewal was rewarded...why?
     *          If a renewal was not rewarded...why?
     *        There are many reasons for a referral not being rewarded.
     *        The reasons more or less all go down in this function.
     *
     * @param OrderDescription $order_description
     * @param PurchaseTrackingEnv $purchase_tracking_env
     * 
     * @return Either<int> The referral_id if right. If left, then the fail messages.
     */
    public static function potentially_reward_a_referral_for_order_and_env($order_description, $purchase_tracking_env)
    {
        $fail_messages = [];
        ///////////////////////////////////////////////////////////////////////////
        // Check if we should skip referral creation for this order
        ///////////////////////////////////////////////////////////////////////////
        list($should_skip, $should_skip_fail_messages) = self::_should_skip_referral_creation_for_order($order_description, $purchase_tracking_env);
        if ($should_skip) {
            $fail_messages = array_merge($fail_messages, $should_skip_fail_messages);
            $fail_messages = array_merge($fail_messages, [__('Referral creation was skipped for this order.', 'solid-affiliate')]);
            return new Either($fail_messages, 0, false);
        }


        ///////////////////////////////////////////////////////////////////////
        // Subscription Renewal Tracking -- Keep this at the top of this method
        ///////////////////////////////////////////////////////////////////////
        if ($order_description->is_renewal_order) {
            return self::_potentially_award_referral_for_renewal_order($order_description, $purchase_tracking_env);
        }

        ///////////////////////////////////////////////////////////////////////
        // Subscription Switch Tracking -- Keep this at the top of this method
        ///////////////////////////////////////////////////////////////////////
        if ($order_description->is_switch_order) {
            return self::_potentially_award_referral_for_switch_order($order_description, $purchase_tracking_env);
        }


        ///////////////////////////////////////////////////////////////////////
        // Auto Referral Tracking 
        // note: We don't want to do it for renewals. Whether or not a renewal
        // deserves a referral is a function of the parent order only.
        // TODO these aren't being returned from the function. They are being
        //  'silently' created.
        ///////////////////////////////////////////////////////////////////////
        $auto_referrals = self::_potentially_reward_auto_referrals($order_description);
        if (FF::is_off('revenue_sharing')) {
            $fail_messages = array_merge($fail_messages, [__('Auto Referrals is a PRO feature.', 'solid-affiliate')]);
        } elseif (empty($auto_referrals)) {
            $fail_messages = array_merge($fail_messages, [__('No auto referrals were created for this order.', 'solid-affiliate')]);
        } else {
            $count_of_auto_referrals = count($auto_referrals);
            $fail_messages = array_merge($fail_messages, [(string)$count_of_auto_referrals . ' ' . __('auto referral(s) were created for this order. All the notes here are regarding non auto referrals.', 'solid-affiliate')]);
            self::_prevent_double_counting_of_auto_referrals($auto_referrals);
        }


        ///////////////////////////////////////////////////////////////////////
        // Affiliate Customer Link tracking (Lifetime Commission)
        ///////////////////////////////////////////////////////////////////////
        if ($order_description->maybe_affiliate_id_from_affiliate_customer_link) {
            $referral_source = Referral::SOURCE_AFFILIATE_CUSTOMER_LINK; // Make this work with Referrals.
            $affiliate_id = $order_description->maybe_affiliate_id_from_affiliate_customer_link;
            $either_referral_id = self::_calculate_commission_and_create_referral_for_affiliate_if_allowed($order_description, $purchase_tracking_env, $affiliate_id, $referral_source, 0, 0);
            if ($either_referral_id->isLeft) {
                $fail_messages = array_merge($fail_messages, $either_referral_id->left);
            } else {
                return $either_referral_id;
            }
        } else {
            $fail_messages = array_merge($fail_messages, [__('No linked lifetimes commissions affiliate found for this customer.', 'solid-affiliate')]);
        }


        ///////////////////////////////////////////////////////////////////////
        // Coupon Tracking
        ///////////////////////////////////////////////////////////////////////
        if ($order_description->maybe_affiliate_id_from_coupon) {
            $either_referral_from_coupon_tracking = self::_coupon_tracking($order_description, $purchase_tracking_env);
            if ($either_referral_from_coupon_tracking->isRight) {
                return $either_referral_from_coupon_tracking;
            } else {
                $fail_messages = array_merge($fail_messages, $either_referral_from_coupon_tracking->left);
            }
        } else {
            $fail_messages = array_merge($fail_messages, [__('No coupon was associated with this order.', 'solid-affiliate')]);
        }

        ///////////////////////////////////////////////////////////////////////
        // Visit Tracking
        ///////////////////////////////////////////////////////////////////////
        $either_referral_from_visit_tracking = self::_visit_tracking($order_description, $purchase_tracking_env);
        if ($either_referral_from_visit_tracking->isRight) {
            return $either_referral_from_visit_tracking;
        } else {
            $fail_messages = array_merge($fail_messages, $either_referral_from_visit_tracking->left);
        }

        ///////////////////////////////////////////////////////////////////////
        // Fallback: no Referral created.
        ///////////////////////////////////////////////////////////////////////
        $fallback_fail_messages = [__(self::FAIL_MSG_NO_CHECKS_PASSED, 'solid-affiliate')];
        $fail_messages = array_merge($fail_messages, $fallback_fail_messages);
        return new Either($fail_messages, 0, false);
    }

    /**
     * Given an OrderDescription + PurchaseTrackingEnv, potentially creates and stores a Coupon tracking based Referral.
     * 
     * @param OrderDescription $order_description
     * @param PurchaseTrackingEnv $purchase_tracking_env
     * 
     * @return Either<int> The referral_id 
     */
    private static function _coupon_tracking($order_description, $purchase_tracking_env)
    {
        $referral_source = 'coupon';
        $affiliate_id = $order_description->maybe_affiliate_id_from_coupon;
        if ($affiliate_id) {
            $either_referral_id = self::_calculate_commission_and_create_referral_for_affiliate_if_allowed($order_description, $purchase_tracking_env, $affiliate_id, $referral_source, 0, $order_description->coupon_id);
            if ($either_referral_id->isLeft) {
                $coupon_id = $order_description->coupon_id;
                $error_messages = array_merge([__("Coupon #$coupon_id matched for affiliate #$affiliate_id but no referral was created during commission calculation.", 'solid-affiliate')], $either_referral_id->left);
                return new Either($error_messages, 0, false);
            } else {
                return $either_referral_id;
            }
        } else {
            $error_messages = [__("An invalid maybe_affiliate_id_from_coupon was passed to _coupon_tracking", 'solid-affiliate')];
            return new Either($error_messages, 0, false);
        }
    }

    /**
     * Given an OrderDescription + PurchaseTrackingEnv, potentially creates and stores a Visit tracking based Referral.
     * 
     * @param OrderDescription $order_description
     * @param PurchaseTrackingEnv $purchase_tracking_env
     * 
     * @return Either<int> The referral_id 
     */
    private static function _visit_tracking($order_description, $purchase_tracking_env)
    {
        $cookied_visit_id = $purchase_tracking_env->cookied_visit_id;
        if ($cookied_visit_id) {
            $maybe_visit = Visit::find($cookied_visit_id);
            if (is_null($maybe_visit)) {
                $error_messages = [sprintf(__('Visit cookie was found, but Visit with ID=%1$s does not exist.', 'solid-affiliate'), $cookied_visit_id)];
                return new Either($error_messages, 0, false);
            } else {
                $referral_source = 'visit';
                $affiliate_id = (int)$maybe_visit->affiliate_id;
                $either_referral_id = self::_calculate_commission_and_create_referral_for_affiliate_if_allowed($order_description, $purchase_tracking_env, $affiliate_id, $referral_source, $maybe_visit->id, 0);
                if ($either_referral_id->isLeft) {
                    $error_messages = array_merge([__("A visit matched for affiliate #$affiliate_id but no referral was created during commission calculation.", 'solid-affiliate')], $either_referral_id->left);
                    return new Either($error_messages, 0, false);
                } else {
                    return $either_referral_id;
                }
            }
        } else {
            $error_messages = [__("No Visit cookie was found.", 'solid-affiliate')];
            return new Either($error_messages, 0, false);
        }
    }

    /**
     * Given a representation of a renewal purchase, potentially creates and stores a Referral.
     * 
     * This is not a pure function. It will look at Cookies to determine Affiliate attribution.
     * 
     * @param OrderDescription $order_description
     * @param PurchaseTrackingEnv $purchase_tracking_env
     * 
     * @return Either<int> The referral_id 
     */
    private static function _potentially_award_referral_for_renewal_order($order_description, $purchase_tracking_env)
    {
        if ($order_description->is_renewal_order) {
            if ($order_description->parent_order_id != 0) {
                // Check if the parent order has a Referral. If so, we'll use that Referral to create a new Referral for the Renewel order.
                $original_subscription_referral = Referral::find_where([
                    'order_id' => $order_description->parent_order_id,
                    'status' => ['operator' => 'IN', 'value' => [Referral::STATUS_PAID, Referral::STATUS_UNPAID]]
                ]);

                if ($original_subscription_referral instanceof Referral) {
                    $affiliate_id = $original_subscription_referral->affiliate_id;

                    $is_within_recurring_referral_time_limit = WooCommerceSubscriptionsIntegration::is_renewal_within_recurring_referral_time_limit($original_subscription_referral, $order_description);

                    if (!$is_within_recurring_referral_time_limit) {
                        return new Either([__('Referral creation skipped for this order. The renewal was not within the recurring referral time limit.', 'solid-affiliate')], 0, false);
                    }

                    // TODO I need to handle the referral_source == 'auto_referral'.
                    $either_referral_id = self::_calculate_commission_and_create_referral_for_affiliate_if_allowed(
                        $order_description,
                        $purchase_tracking_env,
                        $affiliate_id,
                        Referral::SOURCE_SUBSCRIPTION_RENEWAL,
                        0,
                        0,
                        Referral::TYPE_SUBSCRIPTION_RENEWAL
                    );
                    if ($either_referral_id->isLeft) {
                        $error_messages = array_merge([__("Affiliate #$affiliate_id referred this renewal's parent order #$order_description->parent_order_id, but no referral was created during commission calculation.", 'solid-affiliate')], $either_referral_id->left);
                        return new Either($error_messages, 0, false);
                    } else {
                        return $either_referral_id;
                    }
                } else {
                    return new Either([__("It was a Renewal order with no affiliate associated with it's parent order.", 'solid-affiliate')], 0, false);
                }
            } else {
                return new Either([__('It was a Renewal order without a parent order ID.', 'solid-affiliate')], 0, false);
            }
        } else {
            return new Either([__('Referral creation skipped for this order. It was not a Renewal order, but for some reason was handled by PurchaseTracking::_potentially_award_referral_for_renewal_order.', 'solid-affiliate')], 0, false);
        }
    }


    /**
     * Given a representation of a subscription switch, potentially creates and stores a Referral.
     * 
     * This is not a pure function. It will look at Cookies to determine Affiliate attribution.
     * 
     * @param OrderDescription $order_description
     * @param PurchaseTrackingEnv $purchase_tracking_env
     * 
     * @return Either<int> The referral_id 
     */
    private static function _potentially_award_referral_for_switch_order($order_description, $purchase_tracking_env)
    {
        if ($order_description->is_switch_order) {
            if ($order_description->parent_order_id != 0) {
                // Check if the parent order has a Referral. If so, we'll use that Referral to create a new Referral for the Renewel order.
                $original_subscription_referral = Referral::find_where([
                    'order_id' => $order_description->parent_order_id,
                    'status' => ['operator' => 'IN', 'value' => [Referral::STATUS_PAID, Referral::STATUS_UNPAID]]
                ]);

                if ($original_subscription_referral instanceof Referral) {
                    $affiliate_id = $original_subscription_referral->affiliate_id;
                    // TODO I need to handle the referral_source == 'auto_referral'.
                    $either_referral_id = self::_calculate_commission_and_create_referral_for_affiliate_if_allowed(
                        $order_description,
                        $purchase_tracking_env,
                        $affiliate_id,
                        $original_subscription_referral->referral_source,
                        $original_subscription_referral->visit_id,
                        $original_subscription_referral->coupon_id,
                        Referral::TYPE_PURCHASE // TODO we might want something dedicated to Switch orders
                    );
                    if ($either_referral_id->isLeft) {
                        $error_messages = array_merge([__("Affiliate #$affiliate_id referred this subscription switch's parent order #$order_description->parent_order_id, but no referral was created during commission calculation.", 'solid-affiliate')], $either_referral_id->left);
                        return new Either($error_messages, 0, false);
                    } else {
                        return $either_referral_id;
                    }
                } else {
                    return new Either([__("It was a Switch order with no affiliate associated with it's parent order.", 'solid-affiliate')], 0, false);
                }
            } else {
                return new Either([__('It was a Switch order without a parent order ID.', 'solid-affiliate')], 0, false);
            }
        } else {
            return new Either([__('Referral creation skipped for this order. It was not a Switch order, but for some reason was handled by PurchaseTracking::_potentially_award_referral_for_switch_order.', 'solid-affiliate')], 0, false);
        }
    }



    /**
     * TODO this method needs to be much more robust.
     *
     * @param OrderDescription $order_description
     * @param PurchaseTrackingEnv $purchase_tracking_env
     * @param int $affiliate_id
     * @param Referral::SOURCE_* $referral_source
     * @param int $visit_id
     * @param int $coupon_id
     * @param Referral::TYPE_* $referral_type
     * 
     * 
     * @return Either<int> The referral_id 
     */
    private static function _calculate_commission_and_create_referral_for_affiliate_if_allowed($order_description, $purchase_tracking_env, $affiliate_id, $referral_source, $visit_id, $coupon_id, $referral_type = Referral::TYPE_PURCHASE)
    {
        //////////////////////////////////////////////////////////////////////////////////////
        // Check for Prevent Self Referral - TODO this should be in the should_skip_referral_creation_for_order method
        //                                     however we do not know the affiliate_id until now.
        //////////////////////////////////////////////////////////////////////////////////////
        if (self::_should_prevent_self_referral($order_description, $purchase_tracking_env, $affiliate_id)) {
            $error_messages = [__('Self Referral is not allowed.', 'solid-affiliate')];
            return new Either($error_messages, 0, false);
        }
        //////////////////////////////////////////////////////////////////////////////////////
        if ($referral_source === Referral::SOURCE_AUTO_REFERRAL && !$order_description->is_renewal_order) {
            $error_messages = [__('referral_source = auto_referral is not handled by _calculate_commission_and_create_referral_for_affiliate_if_allowed if order_description->is_renewal_order = false.', 'solid-affiliate')];
            return new Either($error_messages, 0, false);
        }

        $maybe_affiliate = Affiliate::find($affiliate_id);
        if (is_null($maybe_affiliate)) {
            $error_messages = [sprintf(__('Affiliate with ID=%1$s does not exist.', 'solid-affiliate'), $affiliate_id)];
            return new Either($error_messages, 0, false);
        }

        if (!Affiliate::can_earn_referral($maybe_affiliate)) {
            $error_messages = [sprintf(__('Affiliate with ID=%1$s is not approved.', 'solid-affiliate'), $affiliate_id)];
            return new Either($error_messages, 0, false);
        }

        list($commission_amount, $item_commissions) = CommissionCalculator::calculate_commission_for_order_description($maybe_affiliate, $order_description, $referral_source);

        // Handle the case of the commission = $0.00 and there are no item commissions
        if (!($commission_amount > 0.0) && empty($item_commissions)) {
            $error_messages = [__('Commission amount is zero and no Item Commissions were calculated.', 'solid-affiliate')];
            return new Either($error_messages, 0, false);
        }

        // Handle the case of the commission = $0.00 an there are some item commissions
        if (!($commission_amount > 0.0)) {
            if (!$purchase_tracking_env->setting_is_enable_zero_value_referrals) {
                return new Either([__('Commission amount is zero, and zero value referrals are disabled.', 'solid-affiliate')], 0, false);
            }
        }

        // log the order description

        /** @psalm-suppress MixedAssignment */
        $serialized_item_commissions = wp_slash((string)maybe_serialize($item_commissions));

        $order_amount = $order_description->order_amount;
        $order_amount_formatted = Formatters::money($order_amount);

        $site_url = str_replace(array("https://", "http://"), "", network_site_url());

        $maybe_affiliate_customer_link_id = ($referral_source == Referral::SOURCE_AFFILIATE_CUSTOMER_LINK) ? $order_description->maybe_affiliate_id_from_affiliate_customer_link : 0;

        $referral_args = [
            'affiliate_id' => $affiliate_id,
            'referral_source' => $referral_source,
            'visit_id' => $visit_id,
            'coupon_id' => $coupon_id,
            'customer_id' => $order_description->customer_id,
            'payout_id' => 0,
            'commission_amount' => $commission_amount,
            'referral_type' => $referral_type,
            'description' => sprintf(__('Purchase of %1$s on %2$s', 'solid-affiliate'), $order_amount_formatted, $site_url),
            'status' => Referral::STATUS_DRAFT,
            'order_source' => $order_description->order_source,
            'order_amount' => $order_amount,
            'order_id' => $order_description->order_id,
            'serialized_item_commissions' => $serialized_item_commissions,
            'affiliate_customer_link_id' => $maybe_affiliate_customer_link_id,
            'manually_created_by_admin_user_id' => 0,
        ];

        // Here we can add a final filter to prevent the referral creation
        // We can use the filter to change anything about the Referral before it is created.
        // We can also return False and an error message to prevent the Referral from being created.

        /** @psalm-suppress MixedAssignment */
        $referral_args_or_error_message = apply_filters("solid_affiliate/before_referral_creation", $referral_args, $order_description, $purchase_tracking_env);

        if (is_string($referral_args_or_error_message)) {
            return new Either([$referral_args_or_error_message], 0, false);
        } else {
            $referral_args = Validators::arr($referral_args_or_error_message);
        }

        $either_referral = Referral::insert($referral_args);

        if ($either_referral->isLeft) {
            return new Either($either_referral->left, 0, false);
        } else {
            $referral_id = $either_referral->right->id;
            // Update Visit here if it's a visit referral.
            if (!empty($visit_id)) {
                Visit::update_all(['referral_id' => $referral_id], ['id' => $visit_id]);
            }
            //////////////////////////////////////////////
            return new Either([''], $referral_id, true);
        }
    }




    /**
     * Checks if we should skip Referral creation for order.
     *
     * @param OrderDescription $order_description
     * @param PurchaseTrackingEnv $purchase_tracking_env
     * @return array{0: bool, 1: string[]}
     */
    private static function _should_skip_referral_creation_for_order($order_description, $purchase_tracking_env)
    {

        //////////////////////////////////////////////////////////////////////////////////////
        // Check if New Customer Commissions setting is in effect
        //////////////////////////////////////////////////////////////////////////////////////
        if ($purchase_tracking_env->setting_is_new_customer_commissions && !$order_description->is_renewal_order) {

            // $order->get_billing_email();

            // return true if the customer making this purchase has made a purchase in the past
            $customer = new \WC_Customer($order_description->customer_id);

            // TODO handle GUEST checkout
            if ($customer->get_id() === 0) {
                $guest_customer_email = WooCommerceIntegration::get_email_for($order_description->order_id);
                // check if the email is a valid email
                if ($guest_customer_email && is_email($guest_customer_email)) {
                    // $customer = new \WC_Customer($guest_customer_email);
                    $order_by_billing_email_ids = wc_get_orders(array(
                        'billing_email' => $guest_customer_email,
                        'status'        => array('pending', 'processing', 'on-hold', 'completed', 'refunded', 'cancelled'),
                        // excluding 'failed' but including other common statuses, adjust as needed
                        'return'        => 'ids',
                        'limit'         => -1,
                    ));

                    if (count(Validators::arr($order_by_billing_email_ids)) > 1) {
                        $should_skip = true;
                        $fail_messages = ['Your Solid Affiliate settings allow for only new customers to generate referrals, and this customer was not new. Since this was a guest checkout, we used the billing email address to match against previous orders: ' . $guest_customer_email];
                        /** @psalm-suppress MixedAssignment */
                        list($should_skip, $fail_messages) = Validators::arr(apply_filters("solid_affiliate/should_skip_referral_creation_for_order", [$should_skip, $fail_messages], $order_description));
                        return [(bool)$should_skip, Validators::array_of_string($fail_messages)];
                    }
                }
            }

            /** @psalm-suppress RedundantCondition */
            if ($customer instanceof \WC_Customer) {
                $order_count = $customer->get_order_count();
                // remove any 'failed' orders from the count.
                // find all orders for this customer with a status of 'failed'
                $failed_orders = wc_get_orders(array(
                    'customer' => $order_description->customer_id,
                    'status' => ['failed']
                ));
                $failed_orders_count = count(Validators::arr_of_woocommerce_order($failed_orders));

                $non_failed_orders_count = $order_count - $failed_orders_count;

                if ($non_failed_orders_count > 1) {
                    $should_skip = true;
                    $fail_messages = ['Your Solid Affiliate settings allow for only new customers to generate referrals, and this customer was not new.'];
                    /** @psalm-suppress MixedAssignment */
                    list($should_skip, $fail_messages) = Validators::arr(apply_filters("solid_affiliate/should_skip_referral_creation_for_order", [$should_skip, $fail_messages], $order_description));
                    return [(bool)$should_skip, Validators::array_of_string($fail_messages)];
                }
            }
        };

        //////////////////////////////////////////////////////////////////////////////////////
        // Check if this order contained a coupon which has referrals disabled
        //////////////////////////////////////////////////////////////////////////////////////
        $coupon_ids = WooCommerceIntegration::get_all_coupon_ids_for_order_id($order_description->order_id);

        foreach ($coupon_ids as $coupon_id) {
            $coupon = new \WC_Coupon($coupon_id);
            /** @psalm-suppress RedundantCondition */
            if ($coupon instanceof \WC_Coupon) {
                $coupon_referrals_disabled = (bool)$coupon->get_meta(WooCommerceIntegration::MISC['is_coupon_referral_disabled_key'], true);
                if ($coupon_referrals_disabled) {
                    $should_skip = true;
                    $coupon_code = (string)$coupon->get_code();
                    $fail_messages = ["This order contained the coupon $coupon_code and this coupon had referrals entirely disabled."];
                    /** @psalm-suppress MixedAssignment */
                    list($should_skip, $fail_messages) = Validators::arr(apply_filters("solid_affiliate/should_skip_referral_creation_for_order", [$should_skip, $fail_messages], $order_description));
                    return [(bool)$should_skip, Validators::array_of_string($fail_messages)];
                }
            }
        }


        //////////////////////////////////////////////////////////////////////////////////////
        // Check if every order_item should be skipped
        //////////////////////////////////////////////////////////////////////////////////////
        $either_order_item_descriptions = CommissionCalculator::get_order_item_descriptions_from_order_description($order_description);

        if ($either_order_item_descriptions->isLeft) {
            $should_skip = false;
            return [$should_skip, ['']];
        } else {
            $should_skip_map = array_map(
                function ($order_item_description) {
                    return CommissionCalculator::should_skip_referral_creation_for_order_item($order_item_description, 0);
                },
                $either_order_item_descriptions->right
            );

            $should_skip_map = array_filter($should_skip_map, function ($should_skip_tuple) {
                return $should_skip_tuple[0];
            });
            $should_skip_fail_messages = array_map(
                function ($should_skip_tuple) {
                    return $should_skip_tuple[1];
                },
                $should_skip_map
            );

            // If every order_item should be skipped, skip the whole order
            $should_skip = count($should_skip_map) === count($either_order_item_descriptions->right);
            $fail_messages = [__('Every order item was skipped for referral consideration.', 'solid-affiliate')];
            // flatten the array of arrays $should_skip_fail_messages
            $should_skip_fail_messages = array_reduce($should_skip_fail_messages, 'array_merge', []);


            $fail_messages = array_merge($fail_messages, $should_skip_fail_messages);

            list($should_skip, $fail_messages) = Validators::arr(apply_filters("solid_affiliate/should_skip_referral_creation_for_order", [$should_skip, $fail_messages], $order_description));
            return [(bool)$should_skip, Validators::array_of_string($fail_messages)];
        }
        //////////////////////////////////////////////////////////////////////////////////////
    }


    /**
     * Undocumented function
     * 
     * @param OrderDescription $order_description
     *
     * @return Referral[]
     */
    private static function _potentially_reward_auto_referrals($order_description)
    {
        // This has to handle on the Order Item level. For example, one product in
        // an order of multiple products might have an auto-referral while others don't.
        // check each product in the order
        //   if auto_renewal=true, reward a Referral to the affiliate
        $order_item_descriptions = CommissionCalculator::get_order_item_descriptions_from_order_description($order_description);
        if ($order_item_descriptions->isLeft) {
            return [];
        } else {
            $referrals = array_map(
                /**
                 * @param OrderItemDescription $item
                 */
                function ($item) use ($order_description) {
                    // check if the product has referrals disabled
                    if (CommissionCalculator::should_skip_referral_creation_for_order_item($item, 0)[0]) {
                        return [];
                    }

                    // check if product needs an auto-referral
                    $maybe_product_rate = AffiliateProductRate::find_where(['woocommerce_product_id' => $item->product_id, 'is_auto_referral' => true]);
                    if (is_null($maybe_product_rate)) {
                        return null;
                    } else {
                        // check if the affiliate exists
                        $maybe_affiliate = Affiliate::find($maybe_product_rate->affiliate_id);
                        if (is_null($maybe_affiliate) || !Affiliate::can_earn_referral($maybe_affiliate)) {
                            return null;
                        } else {
                            // reward the affiliate a referral
                            $order_amount = $item->commissionable_amount;
                            list($commission, $maybe_item_commission) = CommissionCalculator::calculate_commission_for_order_item($maybe_affiliate, $item, Referral::SOURCE_AUTO_REFERRAL);
                            /** @psalm-suppress MixedAssignment */
                            $serialized_item_commissions = wp_slash((string)maybe_serialize([$maybe_item_commission]));

                            $site_url = str_replace(array("https://", "http://"), "", network_site_url());
                            $referral_args = [
                                'affiliate_id' => $maybe_affiliate->id,
                                'referral_source' => Referral::SOURCE_AUTO_REFERRAL,
                                'visit_id' => 0,
                                'coupon_id' => 0,
                                'customer_id' => $order_description->customer_id,
                                'payout_id' => 0,
                                'commission_amount' => $commission,
                                'referral_type' => Referral::SOURCE_AUTO_REFERRAL,
                                'description' => sprintf(__('Purchase of %1$s on %2$s', 'solid-affiliate'), $order_amount, $site_url),
                                'status' => Referral::STATUS_DRAFT,
                                'order_source' => $order_description->order_source,
                                'order_amount' => $order_amount,
                                'order_id' => $order_description->order_id,
                                'serialized_item_commissions' => $serialized_item_commissions,
                                'manually_created_by_admin_user_id' => 0,
                            ];

                            $either_referral = Referral::insert($referral_args);

                            if ($either_referral->isLeft) {
                                return null;
                            } else {
                                return $either_referral->right;
                            }
                        }
                    }
                },
                $order_item_descriptions->right
            );

            return array_filter($referrals);
        }
    }

    /**
     * If an auto_referral was created, we perhaps should not double-create a referral
     * for the same order, if the affiliate who's auto-referral was created is also
     * the affiliate who is the referrer for the order (via coupon or link).
     * 
     * @param Referral[] $auto_referrals
     * @return void
     */
    private static function _prevent_double_counting_of_auto_referrals($auto_referrals)
    {
        foreach ($auto_referrals as $auto_referral) {
            $auto_referral_order_id = $auto_referral->order_id;
            $auto_referral_product_id = (int)Referral::get_product_ids($auto_referral)[0];
            $auto_referral_affiliate_id = $auto_referral->affiliate_id;

            $affiliate_product_rate = AffiliateProductRate::find_where(['affiliate_id' => $auto_referral_affiliate_id, 'woocommerce_product_id' => $auto_referral_product_id]);
            if ($affiliate_product_rate instanceof AffiliateProductRate) {
                if ($affiliate_product_rate->is_prevent_additional_referrals_when_auto_referral_is_triggered) {
                    add_filter(
                        'solid_affiliate/should_skip_referral_creation_for_order_item',
                        /**
                         * @param array{0: bool, 1: string[]} $should_skip_tuple
                         * @param OrderItemDescription $order_item_description
                         * @param int $affiliate_id_for_referral
                         * 
                         * @return array{0: bool, 1: string[]}
                         */
                        function ($should_skip_tuple, $order_item_description, $affiliate_id_for_referral) use ($auto_referral_order_id, $auto_referral_product_id, $auto_referral_affiliate_id) {
                            if (
                                $order_item_description->order_id === $auto_referral_order_id &&
                                $order_item_description->product_id === $auto_referral_product_id &&
                                $affiliate_id_for_referral === $auto_referral_affiliate_id
                            ) {
                                $fail_messages = array_merge($should_skip_tuple[1], [__('Order item skipped to prevent double counting. This product has auto-referrals > prevent additional referrals enabled..', 'solid-affiliate')]);
                                return [true, $fail_messages];
                            }
                            return $should_skip_tuple;
                        },
                        10,
                        3
                    );
                }
            }
        }
    }

    /**
     * Checks if the Referral should be skipped for this order due to being a self referral.
     * 
     * TODO the code is insane, refactor. It works though and it's robust and type safe.
     *
     * @param OrderDescription $order_description
     * @param PurchaseTrackingEnv $purchase_tracking_env
     * @param int $affiliate_id
     * 
     * @return boolean
     */
    private static function _should_prevent_self_referral($order_description, $purchase_tracking_env, $affiliate_id)
    {
        if ($purchase_tracking_env->setting_is_prevent_self_referrals) {
            // Use the Affiliate Email and compare it to the customer email (?). Are there better ways?
            $affiliate = Affiliate::find($affiliate_id);
            if ($affiliate instanceof Affiliate) {
                $affiliate_user = get_user_by('id', $affiliate->user_id);
                if ($affiliate_user instanceof \WP_User) {
                    $wc_order = wc_get_order($order_description->order_id); // TODO this assumes WooCommerce
                    if ($wc_order instanceof \WC_Order) {
                        $customer_email = $wc_order->get_billing_email();
                        if ($affiliate_user->user_email == $customer_email || $affiliate_user->ID == $wc_order->get_customer_id()) {
                            return true;
                        }
                    }
                }
            }
        }



        return false;
    }
}
