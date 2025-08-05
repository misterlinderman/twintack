<?php

namespace SolidAffiliate\Lib\VO\PayPal;

/**
 * @psalm-type SuccessResponseBodyType = array{
 *   sender_batch_id: string,
 *   payout_batch_id: string,
 *   batch_status: string,
 *   raw: array
 * }
 */

class SuccessResponseBody
{
    /** @var SuccessResponseBodyType $data */
    public $data;

    /** @var string $sender_batch_id */
    public $sender_batch_id;

    /** @var string $payout_batch_id */
    public $payout_batch_id;

    /** @var string $batch_status */
    public $batch_status;

    /** @var array $raw */
    public $raw;

    /** @param SuccessResponseBodyType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->sender_batch_id = $data['sender_batch_id'];
        $this->payout_batch_id = $data['payout_batch_id'];
        $this->batch_status = $data['batch_status'];
        $this->raw = $data['raw'];
    }
}
