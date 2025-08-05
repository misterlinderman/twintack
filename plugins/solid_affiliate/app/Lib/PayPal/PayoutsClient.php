<?php

namespace SolidAffiliate\Lib\PayPal;

use PayPalHttp\HttpException;
use PayPalHttp\IOException;
use PaypalPayoutsSDK\Payouts\PayoutsGetRequest;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Lib\VO\PayPal\SuccessResponse;
use SolidAffiliate\Lib\VO\PayPal\FailResponse;
use PaypalPayoutsSDK\Payouts\PayoutsPostRequest;
use SolidAffiliate\Lib\GlobalTypes;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Models\Affiliate;

/**
 * PHP SDK
 * https://github.com/paypal/Payouts-PHP-SDK
 * Inherits from https://github.com/paypal/paypalhttp_php
 */
class PayoutsClient
{
    const BATCH_ID_PREFIX = "SOLID_AFFILIATE_Payout_";
    const RECIPIENT_IDENTIFIER_TYPE = "EMAIL";
    const SENDER_ID_PREFIX = "solid-affiliate-payout-";

    /**
     * POSTs a Batch Payout (15,000 item limit)
     * https://developer.paypal.com/docs/api/payments.payouts-batch/v1/#payouts_post
     * 
     * @param Payout[] $payouts
     * @param string $currency
     *
     * TODO: use a union type
     * @return FailResponse|SuccessResponse
     */
    public static function create($payouts, $currency)
    {
        $req = new PayoutsPostRequest();
        $req->body = self::build_payout_body($payouts, $currency);

        /* @var \PaypalPayoutsSDK\Core\PayPalHttpClient */
        $client = RestClient::client();

        try {
            /**
             * Reference for status lifecycle of a payout
             * https://developer.paypal.com/docs/api/payments.payouts-batch/v1#definition-payout_header
             */
            $resp = $client->execute($req);
            if (Response::is_success($resp)) {
                return Response::build_success($resp);
            } else {
                // Error Messages: https://developer.paypal.com/docs/api/payments.payouts-batch/v1/#errors
                // Error Response: https://developer.paypal.com/docs/api/reference/api-responses/#failed-requests
                return Response::build_fail($resp);
            }
        } catch (HttpException $e) {
            return Response::build_fail($e);
        } catch (IOException $e) {
            return Response::build_fail($e);
        }
    }

    /**
     * GETs a Batch Payout
     * https://developer.paypal.com/docs/api/payments.payouts-batch/v1/#payouts_get
     * 
     * @param string $payout_batch_id
     *
     * TODO: use a union type
     * @return FailResponse|SuccessResponse
     */
    public static function get($payout_batch_id)
    {
        $req = new PayoutsGetRequest($payout_batch_id);

        /* @var \PaypalPayoutsSDK\Core\PayPalHttpClient */
        $client = RestClient::client();

        try {
            /**
             * Reference for status lifecycle of a payout
             * https://developer.paypal.com/docs/api/payments.payouts-batch/v1#definition-payout_header
             */
            $resp = $client->execute($req);
            if (Response::is_success($resp, "get")) {
                return Response::build_success($resp);
            } else {
                // Error Messages: https://developer.paypal.com/docs/api/payments.payouts-batch/v1/#errors
                // Error Response: https://developer.paypal.com/docs/api/reference/api-responses/#failed-requests
                return Response::build_fail($resp);
            }
        } catch (HttpException $e) {
            return Response::build_fail($e);
        } catch (IOException $e) {
            return Response::build_fail($e);
        }
    }



    /**
     * @param Payout[] $payouts
     * @param string $currency
     * 
     * @return string
     */
    public static function build_payout_body($payouts, $currency)
    {
        $body = array(
            "sender_batch_header" => array(
                "email_subject" => "You have received a payout",
                "email_message" => "You have received a payout via the Solid Affiliate + PayPal integration.",
                "sender_batch_id" => self::generate_batch_id(),
                "recipient_type" => self::RECIPIENT_IDENTIFIER_TYPE
            ),
            "items" => self::build_items_array($payouts, $currency)
        );


        $maybe_json = json_encode($body);

        if ($maybe_json === false) {
            // TODO: How do we want to handle this condition if it is even possible?
            return '{}';
        } else {
            return $maybe_json;
        }
    }

    /**
     * @psalm-type PayPalItem = array{
     *   receiver: string|null,
     *   recipient_type: self::RECIPIENT_IDENTIFIER_TYPE,
     *   note: string,
     *   sender_item_id: string,
     *   amount: array{currency: string, value: string}
     * }
     * 
     * @param Payout[] $payouts
     * @param string $currency
     * 
     * @return array<PayPalItem>
     */
    private static function build_items_array($payouts, $currency)
    {
        $note = GlobalTypes::PAYPAL_DEFAULT_PAYOUT_NOTE;

        $payouts = array_values(array_filter($payouts, function ($payout_item) {
            return (float)$payout_item->amount > 0.0;
        }));

        $paypal_items_array = array_map(
            function ($po) use ($currency, $note) {
                $sender_item_id = self::sender_id_from_payout_id($po->id);
                $amount_string = self::format_currency_amount((float)$po->amount);

                return array(
                    "recipient_type" => self::RECIPIENT_IDENTIFIER_TYPE,
                    "receiver" => self::get_affiliate_email($po->affiliate_id),
                    "note" => $note,
                    "sender_item_id" => $sender_item_id,
                    "amount" =>
                    array(
                        "currency" => $currency,
                        "value" => "{$amount_string}"
                    )
                );
            },
            $payouts
        );

        return $paypal_items_array;
    }

    /**
     * Formats the currency amount to adhere to PayPal's API requirements
     * 
     * 
     * https://developer.paypal.com/docs/api/payments.payouts-batch/v1/#definition-currency
     *
     * @param float $amount
     * @return string
     */
    private static function format_currency_amount($amount)
    {
        $amount_string = Validators::currency_amount_string((float)$amount);
        // remove any commas and return
        return str_replace(',', '', $amount_string);
    }

    /**
     * @param int $affiliate_id
     * 
     * @return string|null
     */
    private static function get_affiliate_email($affiliate_id)
    {
        // TODO: this is getting n+1'd from above
        $maybe_affiliate = Affiliate::find($affiliate_id);

        if ($maybe_affiliate) {
            return $maybe_affiliate->payment_email;
        } else {
            // TODO
        }
    }

    /**
     * @return string
     */
    private static function generate_batch_id()
    {
        return self::BATCH_ID_PREFIX . date('y_d_m__h_i_s_a');
    }

    /**
     * @param int $payout_id
     * 
     * @return string
     */
    public static function sender_id_from_payout_id($payout_id)
    {
        return self::SENDER_ID_PREFIX . (string)$payout_id;
    }

    /**
     * @param string $sender_id
     * 
     * @return int
     */
    public static function payout_id_from_sender_id($sender_id)
    {
        // remove the prefix
        $payout_id = substr($sender_id, strlen(self::SENDER_ID_PREFIX));
        return (int)$payout_id;
    }
}
