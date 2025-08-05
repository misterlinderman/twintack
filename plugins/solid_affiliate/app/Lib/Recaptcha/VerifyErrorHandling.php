<?php

namespace SolidAffiliate\Lib\Recaptcha;

use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\RecaptchaResponse;

class VerifyErrorHandling
{
    /**
     * Returns the errors messages for RecaptchaReponse
     *
     * @param RecaptchaResponse $resp
     *
     * @return array<string>
     */
    public static function get_errors($resp)
    {
        if (empty($resp->error_codes)) {
            return [__('reCAPTCHA verify failed.', 'solid-affiliate')];
        } else {
            return self::_error_messages($resp);
        }
    }

    /**
     * Returns the error message for each error code.
     *
     * @param RecaptchaResponse $resp
     *
     * @return array<string>
     */
    private static function _error_messages($resp)
    {
        $error_map = array(
            'missing-input-secret' => __('reCAPTCHA Verify: The secret parameter is missing.', 'solid-affiliate'),
            'invalid-input-secret' => __('reCAPTCHA Verify: The secret parameter is invalid or malformed.', 'solid-affiliate'),
            'missing-input-response' => __('reCAPTCHA Verify: The response parameter is missing.', 'solid-affiliate'),
            'invalid-input-response' => __('reCAPTCHA Verify: The response parameter is invalid or malformed.', 'solid-affiliate'),
            'bad-request' => __('reCAPTCHA Verify: The request is invalid or malformed.', 'solid-affiliate'),
            'timeout-or-duplicate' => __('reCAPTCHA Verify: The response is no longer valid: either is too old or has been used previously.', 'solid-affiliate')
        );

       return array_map(
            /** @param string $code */
            function ($code) use ($error_map) {
                if (isset($error_map[$code])) {
                    return $error_map[$code];
                } else {
                    return __('reCAPTCHA verify failed.', 'solid-affiliate');
                }
            },
            $resp->error_codes
        );
    }
}