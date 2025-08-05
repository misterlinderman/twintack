<?php

namespace SolidAffiliate\Lib\PayPal;

use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\PayPal\SuccessResponse;
use SolidAffiliate\Lib\VO\PayPal\FailResponse;
use SolidAffiliate\Lib\VO\PayPal\SuccessResponseBody;

class Response
{
    const CREATE_SUCCESS_STATUS_CODE = 201;
    const GET_SUCCESS_STATUS_CODE = 200;

    /**
     * @param \PayPalHttp\HttpResponse $resp
     * @param string|null $action
     * 
     * @return boolean
     */
    public static function is_success($resp, $action = "create")
    {
        $success_code = $action == "create" ? self::CREATE_SUCCESS_STATUS_CODE : self::GET_SUCCESS_STATUS_CODE;
        return $resp->statusCode == $success_code;
    }

    /**
     * @param \PayPalHttp\HttpResponse $resp
     * 
     * @return SuccessResponse
     */
    public static function build_success($resp)
    {
        return new SuccessResponse([
            "headers" => $resp->headers,
            "body" => ResponseValidation::validate_success_body($resp->result)
        ]);
    }

    /**
     * @param \PayPalHttp\HttpResponse|\PayPalHttp\HttpException|\PayPalHttp\IOException $resp
     *
     * @return FailResponse
     */
    public static function build_fail($resp)
    {
        if (is_a($resp, \PayPalHttp\HttpResponse::class)) {
            return new FailResponse([
                "status_code" => $resp->statusCode,
                "error" => ResponseValidation::str_field($resp->result, ['error', 'name'], 'Unknown PayPal Error'),
                "message" => ResponseValidation::str_field($resp->result, ['error_description', 'message'], 'Unknown PayPal Error message'),
                "headers" => $resp->headers,
                "raw_body" => Validators::arr($resp->result),
                "debug_id" => ResponseValidation::str_field($resp->headers, ['Paypal-Debug-Id'], '-')
            ]);
        }

        if (is_a($resp, \PayPalHttp\HttpException::class)) {
            // We're expecting $parsed_exception to look like this:
            //   ["error" => "invalid_client", "error_description" => "Client Authentication failed"]
            // or ?
            //   ["name" => "INSUFFICIENT_FUNDS", "message" => "Sender does not have sufficient funds."]
            $parsed_exception = Validators::arr(json_decode($resp->getMessage()));

            $error = ResponseValidation::str_field($parsed_exception, ['error', 'name'], 'Unknown PayPal Error');
            $message = ResponseValidation::str_field($parsed_exception, ['error_description', 'message'], 'Unknown PayPal Error message');

            // Note:
            // Known $error values that I've seen:
            //   "invalid_client" - when the api keys are wrong.
            //   "INSUFFICIENT_FUNDS" - not enough money in the PayPal business account to cover the Payouts.
            return new FailResponse([
                "status_code" => (int)$resp->statusCode,
                "error" => $error,
                "message" => $message,
                "headers" => Validators::arr($resp->headers),
                "raw_body" => $parsed_exception,
                "debug_id" => ResponseValidation::str_field(Validators::arr($resp->headers), ['Paypal-Debug-Id'], '-')
            ]);
        }

        if (is_a($resp, \PayPalHttp\IOException::class)) {
            return new FailResponse([
                "status_code" => (int)$resp->getCode(),
                "error" => $resp->getMessage(),
                "message" => $resp->getMessage(),
                "headers" => [],
                "raw_body" => ['message' => $resp->getMessage()],
                "debug_id" => '-',
            ]);
        }

        // fallback
        return new FailResponse([
            "status_code" => 400,
            "error" => "Unknown PayPal Error",
            "message" => "Unknown PayPal Error",
            "headers" => [],
            "raw_body" => ['message' => "Unkown PayPal Error"],
            "debug_id" => '-',
        ]);
    }
}
