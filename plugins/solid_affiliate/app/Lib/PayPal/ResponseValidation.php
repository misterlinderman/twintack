<?php

namespace SolidAffiliate\Lib\PayPal;

// TODO why is this not auto loading???
require_once __DIR__ . '/../VO/PayPal/SuccessResonseBody.php';

use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\PayPal\SuccessResponseBody;
use SolidAffiliate\Models\BulkPayout;

/**
 * @psalm-import-type BulkPayoutStatus from \SolidAffiliate\Models\BulkPayout
 */
class ResponseValidation
{
    const PAY_PAL_PENDING = 'PENDING';
    const PAY_PAL_PROCESSING = 'PROCESSING';
    const PAY_PAL_SUCCESS = 'SUCCESS';
    const SUCCESSFUL_STATUSES = [self::PAY_PAL_PENDING, self::PAY_PAL_PROCESSING, self::PAY_PAL_SUCCESS];

    /**
     * @param array|string|object $result
     * 
     * @return SuccessResponseBody
     */
    public static function validate_success_body($result)
    {
        //problem is that result is coming in as an object, and the code expects an array.
        if (is_object($result)) {
            /** @psalm-suppress MixedAssignment */
            $result = json_decode(json_encode($result), true);
        }

        $batch_header = self::arr($result, 'batch_header', ['Missing top level response body key - check raw']);
        $sender_batch_header = self::arr($batch_header, 'sender_batch_header', ['Missing sender_batch_header key in response body - check raw']);

        $body = new SuccessResponseBody([
            'sender_batch_id' => self::str_field($sender_batch_header, ['sender_batch_id'], 'Missing sender_batch_id key in response body - check raw'),
            'payout_batch_id' => self::str_field($batch_header, ['payout_batch_id'], 'Missing payout_batch_id key in response body - check raw'),
            'batch_status' => self::status(self::str_field($batch_header, ['batch_status'], 'Missing batch_status key in response body - check raw')),
            'raw' => Validators::arr($result)
        ]);

        return $body;
    }

    /**
     * Coerces the status returned by PayPal into the statuses we work with in our system
     * https://developer.paypal.com/docs/api/payments.payouts-batch/v1/#definition-payout_header
     *
     * @param string $str
     * 
     * @return BulkPayoutStatus
     */
    private static function status($str)
    {
        if ($str == in_array($str, self::SUCCESSFUL_STATUSES)) {
            return BulkPayout::SUCCESS_STATUS;
        } else {
            return BulkPayout::FAIL_STATUS;
        }
    }

    /**
     * @param array|string|object $result
     * @param string[] $keys up to two keys to check. The primary and a backup.
     * @param string $default
     * 
     * @return string
     */
    public static function str_field($result, $keys, $default)
    {
        if (is_string($result)) {
            return $default;
        } else if (is_array($result)) {
            if (array_key_exists($keys[0], $result)) {
                return Validators::str($result[$keys[0]], $default);
            } elseif (isset($keys[1]) && array_key_exists($keys[1], $result)) {
                return Validators::str($result[$keys[1]], $default);
            } else {
                return $default;
            }
        } else {
            return $default;
        }
    }

    /**
     * @param mixed $result
     * @param string $key
     * @param array $default
     * 
     * @return array
     */
    private static function arr($result, $key, $default)
    {
        if (is_array($result) && array_key_exists($key, $result)) {
            return Validators::arr($result[$key]);
        } else {
            return $default;
        }
    }
}
