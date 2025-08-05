<?php

namespace SolidAffiliate\Lib\VO;

use SolidAffiliate\Lib\CommissionCalculator;

/** 
 * 
 * @psalm-type ItemCommissionType = array{
 *  purchase_amount: float, 
 *  commissionable_amount: float, 
 *  commission_amount: float, 
 *  commission_strategy: CommissionCalculator::COMMISSION_STRATEGY_*,
 *  commission_strategy_rate_type: 'flat'|'percentage', 
 *  commission_strategy_rate: float,
 *  product_id: int,
 *  variation_id: int,
 *  quantity: int
 * } 
 */
class ItemCommission
{

    /** @var ItemCommissionType $data */
    public $data;

    /** @var float */
    public $purchase_amount;

    /** @var float */
    public $commissionable_amount;

    /** @var float */
    public $commission_amount;

    /** @var CommissionCalculator::COMMISSION_STRATEGY_* */
    public $commission_strategy;

    /** @var 'flat'|'percentage' */
    public $commission_strategy_rate_type;

    /** @var float */
    public $commission_strategy_rate;

    /** @var int */
    public $product_id;

    /** @var int */
    public $variation_id;

    /** @var int */
    public $quantity;


    /** @param ItemCommissionType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->purchase_amount = $data['purchase_amount'];
        $this->commissionable_amount = $data['commissionable_amount'];
        $this->commission_amount = $data['commission_amount'];
        $this->commission_strategy = $data['commission_strategy'];
        $this->commission_strategy_rate_type = $data['commission_strategy_rate_type'];
        $this->commission_strategy_rate = $data['commission_strategy_rate'];
        $this->product_id = $data['product_id'];
        $this->variation_id = $data['variation_id'];
        $this->quantity = $data['quantity'];
    }
}
