<?php

namespace SolidAffiliate\Lib\VO\PayPal;

/**
 * @psalm-type SuccessResponseType = array{
 *   headers: array,
 *   body: SuccessResponseBody
 * }
 */

class SuccessResponse
{
    /** @var SuccessResponseType $data */
    public $data;

    /** @var array $headers */
    public $headers;

    /** @var SuccessResponseBody $body */
    public $body;

    /** @param SuccessResponseType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->headers = $data['headers'];
        $this->body = $data['body'];
    }
}