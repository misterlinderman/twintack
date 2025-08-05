<?php

namespace SolidAffiliate\Lib\VO;

/** 
 * @psalm-import-type BulkPayoutStatus from \SolidAffiliate\Models\BulkPayout
 * 
 * @psalm-type AttemptedBulkPayoutOrchestrationResultType = array{
 *  message: string,
 *  status: BulkPayoutStatus,
 *  reference: string,
 *  payout_ids: int[],
 *  referral_ids: int[],
 *  total_amount: float
 * } 
 */
class AttemptedBulkPayoutOrchestrationResult
{
    /** @var AttemptedBulkPayoutOrchestrationResultType $data */
    public $data;

    /** @var string */
    public $message;

    /** @var string */
    public $status;

    /** @var string */
    public $reference;

    /** @var int[] */
    public $payout_ids;

    /** @var int[] */
    public $referral_ids;

    /** @var float */
    public $total_amount;

    /** @param AttemptedBulkPayoutOrchestrationResultType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->message = $data['message'];
        $this->status = $data['status'];
        $this->reference = $data['reference'];
        $this->payout_ids = $data['payout_ids'];
        $this->referral_ids = $data['referral_ids'];
        $this->total_amount = $data['total_amount'];
    }
}
