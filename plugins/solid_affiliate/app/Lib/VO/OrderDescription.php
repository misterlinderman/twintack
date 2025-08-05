<?php

namespace SolidAffiliate\Lib\VO;

use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\Integrations\WooCommerceSubscriptionsIntegration;

/** 
 * @psalm-type OrderDescriptionType = array{
 *  order_amount: float, 
 *  order_source: WooCommerceIntegration::SOURCE|WooCommerceSubscriptionsIntegration::SOURCE,
 *  order_id: int, 
 *  parent_order_id: int, 
 *  total_shipping: float,
 *  maybe_affiliate_id_from_coupon: int|null,
 *  maybe_affiliate_id_from_affiliate_customer_link: int|null,
 *  coupon_id: int,
 *  is_renewal_order: bool,
 *  is_switch_order: bool,
 *  customer_id: int,
 *  total_tax: float,
 *  currency: string
 * } 
 */
class OrderDescription
{
    /** @var OrderDescriptionType $data */
    public $data;

    /** @var float */
    public $order_amount;

    /** @var WooCommerceIntegration::SOURCE|WooCommerceSubscriptionsIntegration::SOURCE */
    public $order_source;

    /** @var int */
    public $order_id;

    /** @var int */
    public $parent_order_id;

    /** @var float */
    public $total_shipping;

    /** @var int|null */
    public $maybe_affiliate_id_from_coupon;

    /** @var int|null */
    public $maybe_affiliate_id_from_affiliate_customer_link;
 
    /** @var int */
    public $coupon_id;

    /** @var bool */
    public $is_renewal_order;

    /** @var bool */
    public $is_switch_order;

    /** @var int */
    public $customer_id;

    /** @var float */
    public $total_tax;

    /** @var string */
    public $currency;

    /** @param OrderDescriptionType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->order_amount = $data['order_amount'];
        $this->order_source = $data['order_source'];
        $this->order_id = $data['order_id'];
        $this->parent_order_id = $data['parent_order_id'];
        $this->total_shipping = $data['total_shipping'];
        $this->maybe_affiliate_id_from_coupon = $data['maybe_affiliate_id_from_coupon'];
        $this->maybe_affiliate_id_from_affiliate_customer_link = $data['maybe_affiliate_id_from_affiliate_customer_link'];
        $this->coupon_id = $data['coupon_id'];
        $this->is_renewal_order = $data['is_renewal_order'];
        $this->is_switch_order = $data['is_switch_order'];
        $this->customer_id = $data['customer_id'];
        $this->total_tax = $data['total_tax'];
        $this->currency = $data['currency'];
    }
}
