<?php

namespace SolidAffiliate\Lib\VO;

use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;

/** 
 * @psalm-type OrderItemDescriptionType = array{
 *  amount: float, 
 *  commissionable_amount: float, 
 *  source: WooCommerceIntegration::SOURCE,
 *  product_id: int, 
 *  variation_id: int, 
 *  order_id: int,
 *  type: string,
 *  quantity: int,
 *  is_renewal_order_item: bool,
 *  item_tax: float,
 *  item_shipping: float
 * } 
 */
class OrderItemDescription
{

    /** @var OrderItemDescriptionType $data */
    public $data;

    /** @var float */
    public $amount;

    /** @var float */
    public $commissionable_amount;

    /** @var WooCommerceIntegration::SOURCE */
    public $source;

    /** @var int */
    public $product_id;

    /** @var int */
    public $variation_id;

    /** @var int */
    public $order_id;

    /** @var string */
    public $type;

    /** @var int */
    public $quantity;

    /** @var bool */
    public $is_renewal_order_item;

    /** @var float */
    public $item_shipping;

    /** @var float */
    public $item_tax;

    /** @param OrderItemDescriptionType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->amount = $data['amount'];
        $this->commissionable_amount = $data['commissionable_amount'];
        $this->source = $data['source'];
        $this->product_id = $data['product_id'];
        $this->variation_id = $data['variation_id'];
        $this->order_id = $data['order_id'];
        $this->type = $data['type'];
        $this->quantity = $data['quantity'];
        $this->is_renewal_order_item = $data['is_renewal_order_item'];
        $this->item_shipping = $data['item_shipping'];
        $this->item_tax = $data['item_tax'];
    }
}
