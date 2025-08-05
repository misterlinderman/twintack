<?php

namespace SolidAffiliate\Lib\VO;

/**
 * @psalm-type RecaptchaResponseType = array{
 *   success: boolean,
 *   challenge_ts: string,
 *   hostname: string,
 *   error_codes: array<string>
 * }
 */

 class RecaptchaResponse
 {
    /** @var RecaptchaResponseType $data */
    public $data;

    /** @var boolean $success */
    public $success;

    /** @var string $challenge_ts */
    public $challenge_ts;

    /** @var string $hostname */
    public $hostname;

    /** @var array<string> $error_codes */
    public $error_codes;

    /** @param RecaptchaResponseType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->success = $data['success'];
        $this->challenge_ts = $data['challenge_ts'];
        $this->hostname = $data['hostname'];
        $this->error_codes = $data['error_codes'];
    }
 }
