<?php

namespace SolidAffiliate\Lib\VO;

use SolidAffiliate\Controllers\PayAffiliatesController;

/** 
 * 
 * TODO fields to add to BulkPayoutLogicRuleType later: 'referral_order_status' | 'custom_affiliate_field_xyz'
 * 
 * @psalm-type BulkPayoutLogicRuleType = array{
 *      operator: 'include' | 'exclude',
 *      field: 'affiliate_id' | 'affiliate_group_id' | 'referral_id'| 'tag',
 *      value: int[]|string[],
 * }
 * 
 * @psalm-type BulkPayoutMethodType = PayAffiliatesController::BULK_PAYOUT_METHOD_*
 * 
 * @psalm-type BulkPayoutOrchestrationFiltersType = array{
 *   start_date: string,
 *   end_date: string,
 *   date_range_preset: string,
 *   minimum_payout_amount: float,
 *   logic_rules: BulkPayoutLogicRuleType[],
 * }
 * 
 * @psalm-type BulkPayoutOrchestrationExportArgsType = array{
 *   bulk_payout_method: BulkPayoutMethodType,
 *   payout_currency: string,
 *   created_by_user_id: int,
 *   mark_referrals_as_paid: bool,
 *   only_export_csv: bool,
 * }
 * 
 * @psalm-type BulkPayoutOrchestrationParamsType = array{
 *  filters: BulkPayoutOrchestrationFiltersType,
 *  export_args: BulkPayoutOrchestrationExportArgsType,
 * }
 */
class BulkPayoutOrchestrationParams
{
    /** @var BulkPayoutOrchestrationParamsType $data */
    public $data;

    /** @var BulkPayoutOrchestrationFiltersType */
    public $filters;

    /** @var BulkPayoutOrchestrationExportArgsType */
    public $export_args;


    /** @param BulkPayoutOrchestrationParamsType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->filters = $data['filters'];
        $this->export_args = $data['export_args'];
    }
}
