<?php

namespace SolidAffiliate\Lib\Recaptcha;

use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\RecaptchaResponse;

class RecaptchaClient
{
    /**
     * Makes a request to Google's reCAPTCHA verify service and returns the response.
     *
     * @param array $post $_POST
     * @param array $server $_SERVER
     *
     * @return RecaptchaResponse
     */
    public static function make_verify_request($post, $server)
    {
        $recaptcha = Validators::str_from_array($post, 'g-recaptcha-response');

        if (empty($recaptcha)) {
            return self::_build_error_response([__('reCAPTCHA not completed, please try again.', 'solid-affiliate')]);
        }

        $recaptcha_secret = Validators::str(Settings::get(Settings::KEY_RECAPTCHA_SECRET_KEY));

        if (empty($recaptcha_secret)) {
            return self::_build_error_response([__('The reCAPTCHA server secret is not set, please contact the site owner.', 'solid-affiliate')]);
        }

        $remote_ip = Validators::str_from_array($server, 'REMOTE_ADDR');
        $base_url = 'https://www.google.com/recaptcha/api/siteverify';
        $query_args = [
            'secret' => urlencode($recaptcha_secret),
            'response' => urlencode($recaptcha),
            'remoteip' => urlencode($remote_ip)
        ];
        $full_url = add_query_arg($query_args, $base_url);
        $post_resp = wp_remote_post($full_url);

        if ($post_resp instanceof \WP_Error) {
            return self::_build_error_response([__('There was an issue making a the request to the reCAPTCHA server.', 'solid-affiliate')]);
        } else {
            $body = Validators::arr(json_decode(wp_remote_retrieve_body($post_resp), true));

            if (empty($body)) {
                return self::_build_error_response([__('The reCAPTCHA verify response was malformed and is missing a response body.', 'solid-affiliate')]);
            }

            return new RecaptchaResponse([
                'success' => Validators::bool_from_array($body, 'success'),
                'challenge_ts' => Validators::str_from_array($body, 'challenge_ts'),
                'hostname' => Validators::str_from_array($body, 'hostname'),
                'error_codes' => self::_get_error_codes($body)
            ]);
        }
    }

    /**
     * Returns a RecaptchaResponse with the provided errors messages.
     *
     * @param array<string> $errors
     *
     * @return RecaptchaResponse
     */
    private static function _build_error_response($errors)
    {
        return new RecaptchaResponse([
            'success' => false,
            'challenge_ts' => '',
            'hostname' => '',
            'error_codes' => $errors
        ]);
    }

    /**
     * Gets the array of errors from the verify service if there is any.
     *
     * @param array $body
     *
     * @return array<string>
     */
    private static function _get_error_codes($body)
    {
        if (isset($body['error-codes'])) {
            return Validators::array_of_string($body['error-codes']);
        } else {
            return [];
        }
    }
}