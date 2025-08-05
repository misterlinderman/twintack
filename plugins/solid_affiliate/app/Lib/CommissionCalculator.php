<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\Integrations\WooCommerceSubscriptionsIntegration;
use SolidAffiliate\Lib\VO\OrderDescription;
use SolidAffiliate\Lib\VO\OrderItemDescription;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Lib\VO\ItemCommission;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateGroup;
use SolidAffiliate\Models\AffiliateProductRate;
use SolidAffiliate\Models\Referral;

class CommissionCalculator
{
    const COMMISSION_STRATEGY_GLOBAL_RECURRING_RATE = 'global_recurring_rate';
    const COMMISSION_STRATEGY_AFFILIATE_PRODUCT_RATE = 'affiliate_product_rate';
    const COMMISSION_STRATEGY_AFFILIATE_SPECIFIC_RATE = 'affiliate_specific_rate';
    const COMMISSION_STRATEGY_AFFILIATE_GROUP_RATE = 'affiliate_group_rate';
    const COMMISSION_STRATEGY_LIFETIME_COMMISSIONS_RATE = 'lifetime_commissions';
    const COMMISSION_STRATEGY_PRODUCT_VARIATION_SPECIFIC_RATE = 'product_variation_specific_rate';
    const COMMISSION_STRATEGY_PRODUCT_SPECIFIC_RATE = 'product_specific_rate';
    const COMMISSION_STRATEGY_PRODUCT_CATEGORY_SPECIFIC_RATE = 'product_category_specific_rate';
    const COMMISSION_STRATEGY_GLOBAL_SITE_DEFAULT_RATE = 'global_site_default_rate';


    /**
     * Calculates an Affiliate's Commission for an Order.
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * @param OrderDescription $order_description
     * @param Referral::SOURCE_* $referral_source
     * 
     * @return array{0:float, 1: ItemCommission[]}
     */
    public static function calculate_commission_for_order_description($affiliate, $order_description, $referral_source)
    {
        //////////////////////////////////////////////////////
        // Do Some Checks
        if (!in_array($order_description->order_source, [WooCommerceIntegration::SOURCE, WooCommerceSubscriptionsIntegration::SOURCE])) {
            return [0.0, []];
        }

        $either_order_item_descriptions = self::get_order_item_descriptions_from_order_description($order_description);
        if ($either_order_item_descriptions->isLeft) {
            return [0.0, []];
        }


        //////////////////////////////////////////////////////
        // Sum totals for each Order Item
        $total = 0.0;
        $item_commissions = [];

        $order_item_descriptions = $either_order_item_descriptions->right;
        $order_item_descriptions = array_filter($order_item_descriptions, function ($order_item_description) use ($affiliate) {
            list($should_skip, $_fail_messages) = self::should_skip_referral_creation_for_order_item($order_item_description, $affiliate->id);
            return !$should_skip;
        });

        foreach ($order_item_descriptions as $order_item_description) {
            list($commission, $maybe_item_commission) = self::calculate_commission_for_order_item($affiliate, $order_item_description, $referral_source);
            $total += $commission;
            if ($maybe_item_commission instanceof ItemCommission) {
                $item_commissions[] = $maybe_item_commission;
            }
        }

        /**
         * Filters the total commission amount for an order before returning from the commission calculation.
         *
         * This allows for custom modifications to the commission amount by plugins or themes
         * before the final amount is set. The filter passes the calculated total commission,
         * the affiliate object, and the order description object to allow for detailed adjustments
         * based on the order or affiliate details.
         *
         * @param float $total The calculated total commission for the order.
         * @param \SolidAffiliate\Models\Affiliate $affiliate The affiliate object for whom the commission is calculated.
         * @param \SolidAffiliate\Lib\VO\OrderDescription $order_description The order description object containing details about the order.
         * @return float The modified total commission amount.
         */
        $total = (float)apply_filters("solid_affiliate/commission_calculator/commission_for_order", $total, $affiliate, $order_description);

        return [$total, $item_commissions];
    }

    /**
     *
     * @param OrderDescription $order_description
     * 
     * @return Either<OrderItemDescription[]>
     */
    public static function get_order_item_descriptions_from_order_description($order_description)
    {
        // TODO eventually could handle different order sources
        return WooCommerceIntegration::get_order_item_descriptions_from_order_description($order_description);
    }

    /**
     * Checks if we should be skipping Referral creation for an order item.
     * For example, if a specific WooCommerce product has Referrals disabled.
     *
     * @param OrderItemDescription $order_item_description
     * @param int $affiliate_id_for_referral The Affiliate ID for the Referral. Use 0 if not applicable.
     * @return array{0: bool, 1: string[]}
     */
    public static function should_skip_referral_creation_for_order_item($order_item_description, $affiliate_id_for_referral)
    {
        $fail_messages = [];
        switch ($order_item_description->source) {
            case WooCommerceIntegration::SOURCE:
                // TODO move this logic to WooCommerceIntegration
                $product_id = $order_item_description->product_id;
                $product_name = "ID: $product_id";
                $maybe_wc_product = wc_get_product($product_id);
                if ($maybe_wc_product instanceof \WC_Product) {
                    $product_name .= ' (' . $maybe_wc_product->get_title() . ')';
                }

                // check if Product Variation has Referrals disabled
                $is_variation_referral_disabled_key = WooCommerceIntegration::MISC['is_product_variation_referral_disabled_key'];
                $should_skip = (bool)get_post_meta($order_item_description->variation_id, $is_variation_referral_disabled_key, true);
                if ($should_skip) {
                    $fail_messages = array_merge($fail_messages, ["Product $product_name > This product variation has Referrals disabled."]);
                }

                // check if Product has Referrals disabled
                $is_product_referral_disabled_key = WooCommerceIntegration::MISC['is_product_referral_disabled_key'];
                $should_skip = (bool)get_post_meta($product_id, $is_product_referral_disabled_key, true);
                if ($should_skip) {
                    $fail_messages = array_merge($fail_messages, ["Product $product_name > This product has Referrals disabled."]);
                }

                // Check if this Product's Category has Referrals disabled
                $category_ids = Validators::array_of_int(\wc_get_product_cat_ids($order_item_description->product_id));
                foreach ($category_ids as $category_id) {
                    $is_category_disabled_key = WooCommerceIntegration::MISC['is_product_category_referral_disabled_key'];
                    $is_category_disabled = (bool)get_term_meta($category_id, $is_category_disabled_key, false);
                    if ($is_category_disabled) {
                        $fail_messages = array_merge($fail_messages, ["Product $product_name > This product's Category ID: {$category_id} has Referrals disabled."]);
                        $should_skip = true;
                        break;
                    }
                }

                list($should_skip, $fail_messages) = Validators::arr(apply_filters("solid_affiliate/should_skip_referral_creation_for_order_item", [$should_skip, $fail_messages], $order_item_description, $affiliate_id_for_referral));
                return [(bool)$should_skip, Validators::array_of_string($fail_messages)];
            default:
                return [false, []];
        }
    }

    /**
     * Calculates an Affiliate's Commission for a purchase.
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * @param OrderItemDescription $order_item_description
     * @param Referral::SOURCE_* $referral_source
     * 
     * @return array{0:float, 1: ItemCommission|null}
     */
    public static function calculate_commission_for_order_item($affiliate, $order_item_description, $referral_source)
    {
        // Global Recurring Rate (if enabled)
        /////////////////////////////////////////////////////////////
        $either = self::_global_recurring_rate($affiliate, $order_item_description);
        if ($either->isRight) {
            $item_commission = $either->right;
            return [$item_commission->commission_amount, $item_commission];
        };

        // Affiliate Specific Rate (if enabled)
        /////////////////////////////////////////////////////////////
        $either = self::_affiliate_product_rate($affiliate, $order_item_description);
        if ($either->isRight) {
            $item_commission = $either->right;
            return [$item_commission->commission_amount, $item_commission];
        };

        // Affiliate Specific Rate (if enabled)
        /////////////////////////////////////////////////////////////
        $either = self::_affiliate_specific_rate($affiliate, $order_item_description);
        if ($either->isRight) {
            $item_commission = $either->right;
            return [$item_commission->commission_amount, $item_commission];
        };

        // Affiliate Group Rate (if Affiliate is in a group)
        /////////////////////////////////////////////////////////////
        $either = self::_affiliate_group_rate($affiliate, $order_item_description);
        if ($either->isRight) {
            $item_commission = $either->right;
            return [$item_commission->commission_amount, $item_commission];
        };

        // Lifetime Commissions rate (if enabled)
        /////////////////////////////////////////////////////////////
        $either = self::_lifetime_commissions_rate($affiliate, $order_item_description, $referral_source);
        if ($either->isRight) {
            $item_commission = $either->right;
            return [$item_commission->commission_amount, $item_commission];
        };

        // Product Variation Specific Rate (if enabled)
        /////////////////////////////////////////////////////////////
        $either = self::_product_variation_specific_rate($affiliate, $order_item_description);
        if ($either->isRight) {
            $item_commission = $either->right;
            return [$item_commission->commission_amount, $item_commission];
        };

        // Product Specific Rate (if enabled)
        /////////////////////////////////////////////////////////////
        $either = self::_product_specific_rate($affiliate, $order_item_description);
        if ($either->isRight) {
            $item_commission = $either->right;
            return [$item_commission->commission_amount, $item_commission];
        };

        // Product Category Specific Rate (if enabled)
        /////////////////////////////////////////////////////////////
        $either = self::_product_category_specific_rate($affiliate, $order_item_description);
        if ($either->isRight) {
            $item_commission = $either->right;
            return [$item_commission->commission_amount, $item_commission];
        };

        // Global Lifetime Rate (if enabled)
        /////////////////////////////////////////////////////////////

        // Global Site Default Referral Rate (default)
        /////////////////////////////////////////////////////////////
        $either = self::_global_site_default_rate($affiliate, $order_item_description);
        if ($either->isRight) {
            $item_commission = $either->right;
            return [$item_commission->commission_amount, $item_commission];
        };

        return [0.0, null];
    }

    /**
     * Calculates Commission for ...
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * @param OrderItemDescription $order_item_description
     * 
     * @return Either<ItemCommission> The Commission
     */
    public static function _global_recurring_rate($affiliate, $order_item_description)
    {
        $settings = Settings::get_many([Settings::KEY_IS_ENABLE_RECURRING_REFERRALS, Settings::KEY_RECURRING_REFERRAL_RATE, Settings::KEY_RECURRING_REFERRAL_RATE_TYPE]);

        $is_enable_recurring_referrals = (bool)$settings[Settings::KEY_IS_ENABLE_RECURRING_REFERRALS];
        $recurring_referral_rate = (int)$settings['recurring_referral_rate'];
        /** @var 'percentage'|'flat' */
        $recurring_referral_rate_type = (string)$settings['recurring_referral_rate_type'];


        if (!$is_enable_recurring_referrals) {
            return self::not_applicable_either();
        }

        if (!$order_item_description->is_renewal_order_item) {
            return self::not_applicable_either();
        }

        switch ($recurring_referral_rate_type) {
            case 'percentage':
                $commission = ((float)$recurring_referral_rate / 100.0) * $order_item_description->commissionable_amount;
                $i = new ItemCommission(
                    [
                        'purchase_amount' => $order_item_description->amount,
                        'commissionable_amount' => $order_item_description->commissionable_amount,
                        'commission_amount' => $commission,
                        'commission_strategy' => self::COMMISSION_STRATEGY_GLOBAL_RECURRING_RATE,
                        'commission_strategy_rate_type' => 'percentage',
                        'commission_strategy_rate' => (float)$recurring_referral_rate,
                        'product_id' => $order_item_description->product_id,
                        'variation_id' => $order_item_description->variation_id,
                        'quantity' => $order_item_description->quantity
                    ]
                );
                return new Either([''], $i, true);
            case 'flat':
                $commission = (float)$recurring_referral_rate * $order_item_description->quantity;
                $i = new ItemCommission(
                    [
                        'purchase_amount' => $order_item_description->amount,
                        'commissionable_amount' => $order_item_description->commissionable_amount,
                        'commission_amount' => $commission,
                        'commission_strategy' => self::COMMISSION_STRATEGY_GLOBAL_RECURRING_RATE,
                        'commission_strategy_rate_type' => 'flat',
                        'commission_strategy_rate' => (float)$recurring_referral_rate,
                        'product_id' => $order_item_description->product_id,
                        'variation_id' => $order_item_description->variation_id,
                        'quantity' => $order_item_description->quantity
                    ]
                );
                // return new Either([''], $commission, true);
                return new Either([''], $i, true);
            default:
                return self::not_applicable_either();
        }
    }

    /**
     * Calculates Commission for ...
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * @param OrderItemDescription $order_item_description
     * 
     * @return Either<ItemCommission> The Commission
     */
    public static function _affiliate_product_rate($affiliate, $order_item_description)
    {
        $maybe_affiliate_product_rate = AffiliateProductRate::for_affiliate_and_order_item_description($affiliate, $order_item_description);

        if (is_null($maybe_affiliate_product_rate)) {
            return self::not_applicable_either();
        } else {
            $commission_rate = (float)$maybe_affiliate_product_rate->commission_rate;

            switch ($maybe_affiliate_product_rate->commission_type) {
                case 'percentage':
                    $commission = ($commission_rate / 100.0) * $order_item_description->commissionable_amount;
                    $i = new ItemCommission(
                        [
                            'purchase_amount' => $order_item_description->amount,
                            'commissionable_amount' => $order_item_description->commissionable_amount,
                            'commission_amount' => $commission,
                            'commission_strategy' => self::COMMISSION_STRATEGY_AFFILIATE_PRODUCT_RATE,
                            'commission_strategy_rate_type' => 'percentage',
                            'commission_strategy_rate' => $commission_rate,
                            'product_id' => $order_item_description->product_id,
                            'variation_id' => $order_item_description->variation_id,
                            'quantity' => $order_item_description->quantity
                        ]
                    );
                    return new Either([''], $i, true);
                case 'flat':
                    $commission = $commission_rate * $order_item_description->quantity;
                    $i = new ItemCommission(
                        [
                            'purchase_amount' => $order_item_description->amount,
                            'commissionable_amount' => $order_item_description->commissionable_amount,
                            'commission_amount' => $commission,
                            'commission_strategy' => self::COMMISSION_STRATEGY_AFFILIATE_PRODUCT_RATE,
                            'commission_strategy_rate_type' => 'flat',
                            'commission_strategy_rate' => $commission_rate,
                            'product_id' => $order_item_description->product_id,
                            'variation_id' => $order_item_description->variation_id,
                            'quantity' => $order_item_description->quantity
                        ]
                    );
                    return new Either([''], $i, true);
                default:
                    return self::not_applicable_either();
            }
        }
    }

    /**
     * Calculates Commission for ...
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * @param OrderItemDescription $order_item_description
     * 
     * @return Either<ItemCommission> The Commission
     */
    public static function _affiliate_specific_rate($affiliate, $order_item_description)
    {
        $commission_rate = (float)$affiliate->commission_rate;

        switch ($affiliate->commission_type) {
            case 'percentage':
                $commission = ($commission_rate / 100.0) * $order_item_description->commissionable_amount;
                $i = new ItemCommission(
                    [
                        'purchase_amount' => $order_item_description->amount,
                        'commissionable_amount' => $order_item_description->commissionable_amount,
                        'commission_amount' => $commission,
                        'commission_strategy' => self::COMMISSION_STRATEGY_AFFILIATE_SPECIFIC_RATE,
                        'commission_strategy_rate_type' => 'percentage',
                        'commission_strategy_rate' => $commission_rate,
                        'product_id' => $order_item_description->product_id,
                        'variation_id' => $order_item_description->variation_id,
                        'quantity' => $order_item_description->quantity
                    ]
                );
                return new Either([''], $i, true);
            case 'flat':
                $commission = $commission_rate * $order_item_description->quantity;
                $i = new ItemCommission(
                    [
                        'purchase_amount' => $order_item_description->amount,
                        'commissionable_amount' => $order_item_description->commissionable_amount,
                        'commission_amount' => $commission,
                        'commission_strategy' => self::COMMISSION_STRATEGY_AFFILIATE_SPECIFIC_RATE,
                        'commission_strategy_rate_type' => 'flat',
                        'commission_strategy_rate' => $commission_rate,
                        'product_id' => $order_item_description->product_id,
                        'variation_id' => $order_item_description->variation_id,
                        'quantity' => $order_item_description->quantity
                    ]
                );
                return new Either([''], $i, true);
            default:
                return self::not_applicable_either();
        }
    }

    /**
     * Calculates Commission for ...
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * @param OrderItemDescription $order_item_description
     * 
     * @return Either<ItemCommission> The Commission
     */
    public static function _affiliate_group_rate($affiliate, $order_item_description)
    {
        $maybe_affiliate_group = AffiliateGroup::for_affiliate($affiliate);

        if (is_null($maybe_affiliate_group)) {
            return self::not_applicable_either();
        } else {
            $commission_rate = (float)$maybe_affiliate_group->commission_rate;

            switch ($maybe_affiliate_group->commission_type) {
                case 'percentage':
                    $commission = ($commission_rate / 100.0) * $order_item_description->commissionable_amount;
                    $i = new ItemCommission(
                        [
                            'purchase_amount' => $order_item_description->amount,
                            'commissionable_amount' => $order_item_description->commissionable_amount,
                            'commission_amount' => $commission,
                            'commission_strategy' => self::COMMISSION_STRATEGY_AFFILIATE_GROUP_RATE,
                            'commission_strategy_rate_type' => 'percentage',
                            'commission_strategy_rate' => $commission_rate,
                            'product_id' => $order_item_description->product_id,
                            'variation_id' => $order_item_description->variation_id,
                            'quantity' => $order_item_description->quantity
                        ]
                    );
                    return new Either([''], $i, true);
                case 'flat':
                    $commission = $commission_rate * $order_item_description->quantity;
                    $i = new ItemCommission(
                        [
                            'purchase_amount' => $order_item_description->amount,
                            'commissionable_amount' => $order_item_description->commissionable_amount,
                            'commission_amount' => $commission,
                            'commission_strategy' => self::COMMISSION_STRATEGY_AFFILIATE_GROUP_RATE,
                            'commission_strategy_rate_type' => 'flat',
                            'commission_strategy_rate' => $commission_rate,
                            'product_id' => $order_item_description->product_id,
                            'variation_id' => $order_item_description->variation_id,
                            'quantity' => $order_item_description->quantity
                        ]
                    );
                    return new Either([''], $i, true);
                default:
                    return self::not_applicable_either();
            }
        }
    }

    /**
     * Calculates Commission for ...
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * @param OrderItemDescription $order_item_description
     * @param Referral::SOURCE_* $referral_source
     * 
     * @return Either<ItemCommission> The Commission
     */
    public static function _lifetime_commissions_rate($affiliate, $order_item_description, $referral_source)
    {
        if ($referral_source != Referral::SOURCE_AFFILIATE_CUSTOMER_LINK) {
            return self::not_applicable_either();
        }

        $commission_rate = (float)Settings::get(Settings::KEY_LIFETIME_COMMISSIONS_REFERRAL_RATE);

        switch (Settings::get(Settings::KEY_LIFETIME_COMMISSIONS_REFERRAL_RATE_TYPE)) {
            case 'percentage':
                $commission = ($commission_rate / 100.0) * $order_item_description->commissionable_amount;
                $i = new ItemCommission(
                    [
                        'purchase_amount' => $order_item_description->amount,
                        'commissionable_amount' => $order_item_description->commissionable_amount,
                        'commission_amount' => $commission,
                        'commission_strategy' => self::COMMISSION_STRATEGY_LIFETIME_COMMISSIONS_RATE,
                        'commission_strategy_rate_type' => 'percentage',
                        'commission_strategy_rate' => $commission_rate,
                        'product_id' => $order_item_description->product_id,
                        'variation_id' => $order_item_description->variation_id,
                        'quantity' => $order_item_description->quantity
                    ]
                );
                return new Either([''], $i, true);
            case 'flat':
                $commission = $commission_rate * $order_item_description->quantity;
                $i = new ItemCommission(
                    [
                        'purchase_amount' => $order_item_description->amount,
                        'commissionable_amount' => $order_item_description->commissionable_amount,
                        'commission_amount' => $commission,
                        'commission_strategy' => self::COMMISSION_STRATEGY_LIFETIME_COMMISSIONS_RATE,
                        'commission_strategy_rate_type' => 'flat',
                        'commission_strategy_rate' => $commission_rate,
                        'product_id' => $order_item_description->product_id,
                        'variation_id' => $order_item_description->variation_id,
                        'quantity' => $order_item_description->quantity
                    ]
                );
                return new Either([''], $i, true);
            default:
                return self::not_applicable_either();
        }
    }

    /**
     * Calculates Commission for ...
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * @param OrderItemDescription $order_item_description
     * 
     * @return Either<ItemCommission> The Commission
     */
    public static function _product_variation_specific_rate($affiliate, $order_item_description)
    {
        switch ($order_item_description->source) {
            case WooCommerceIntegration::SOURCE:
                return WooCommerceIntegration::calculate_product_variation_specific_commission($affiliate, $order_item_description);
            default:
                return self::not_applicable_either();
        }
    }

    /**
     * Calculates Commission for ...
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * @param OrderItemDescription $order_item_description
     * 
     * @return Either<ItemCommission> The Commission
     */
    public static function _product_specific_rate($affiliate, $order_item_description)
    {
        switch ($order_item_description->source) {
            case WooCommerceIntegration::SOURCE:
                return WooCommerceIntegration::calculate_product_specific_commission($affiliate, $order_item_description);
            default:
                return self::not_applicable_either();
        }
    }

    /**
     * Calculates Commission for ...
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * @param OrderItemDescription $order_item_description
     * 
     * @return Either<ItemCommission> The Commission
     */
    public static function _product_category_specific_rate($affiliate, $order_item_description)
    {
        switch ($order_item_description->source) {
            case WooCommerceIntegration::SOURCE:
                return WooCommerceIntegration::calculate_product_category_specific_commission($affiliate, $order_item_description);
            default:
                return self::not_applicable_either();
        }
    }

    /**
     * Calculates Commission for ...
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * @param OrderItemDescription $order_item_description
     * 
     * @return Either<ItemCommission> The Commission
     */
    public static function _global_site_default_rate($affiliate, $order_item_description)
    {
        /** @var 'percentage'|'flat' TODO figure this out these types should come in */
        $commission_type = Settings::get(Settings::KEY_REFERRAL_RATE_TYPE);
        $commission_rate = (float)Settings::get(Settings::KEY_REFERRAL_RATE);

        switch ($commission_type) {
            case 'percentage':
                $commission = ((float)$commission_rate / 100.0) * $order_item_description->commissionable_amount;
                $i = new ItemCommission(
                    [
                        'purchase_amount' => $order_item_description->amount,
                        'commissionable_amount' => $order_item_description->commissionable_amount,
                        'commission_amount' => $commission,
                        'commission_strategy' => self::COMMISSION_STRATEGY_GLOBAL_SITE_DEFAULT_RATE,
                        'commission_strategy_rate_type' => 'percentage',
                        'commission_strategy_rate' => (float)$commission_rate,
                        'product_id' => $order_item_description->product_id,
                        'variation_id' => $order_item_description->variation_id,
                        'quantity' => $order_item_description->quantity
                    ]
                );
                return new Either([''], $i, true);
            case 'flat':
                $commission = (float)$commission_rate * $order_item_description->quantity;
                $i = new ItemCommission(
                    [
                        'purchase_amount' => $order_item_description->amount,
                        'commissionable_amount' => $order_item_description->commissionable_amount,
                        'commission_amount' => $commission,
                        'commission_strategy' => self::COMMISSION_STRATEGY_GLOBAL_SITE_DEFAULT_RATE,
                        'commission_strategy_rate_type' => 'flat',
                        'commission_strategy_rate' => (float)$commission_rate,
                        'product_id' => $order_item_description->product_id,
                        'variation_id' => $order_item_description->variation_id,
                        'quantity' => $order_item_description->quantity
                    ]
                );
                return new Either([''], $i, true);
            default:
                return self::not_applicable_either();
        }
    }

    /**
     * Helper function.
     *
     * @param string $left_message
     * @return Either<ItemCommission> The Commission
     */
    public static function not_applicable_either($left_message = 'Not applicable.')
    {
        // TODO this is a total hack because of the way the Either is implemented.
        /** @var ItemCommission */
        $not_applicable_item_commission = null;
        return new Either([__($left_message, 'solid-affiliate')], $not_applicable_item_commission, false);
    }
}
