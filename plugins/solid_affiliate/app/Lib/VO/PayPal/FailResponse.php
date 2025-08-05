<?php

namespace SolidAffiliate\Lib\VO\PayPal;

// TODO: could use a union string for error
// TODO: could use union int for status_code
/**
 * @psalm-type FailResponseType = array{
 *   status_code: int,
 *   error: string,
 *   message: string,
 *   headers: array,
 *   raw_body: array,
 *   debug_id: string,
 * }
 */

class FailResponse
{
    /** @var FailResponseType $data */
    public $data;

    /** @var int $status_code */
    public $status_code;

    /** @var string $error */
    public $error;

    /** @var string $message */
    public $message;

    /** @var array $headers */
    public $headers;

    /** @var array $raw_body */
    public $raw_body;

    /** @var string $debug_id */
    public $debug_id;

    /** @param FailResponseType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->status_code = $data['status_code'];
        $this->error = $data['error'];
        $this->message = $data['message'];
        $this->headers = $data['headers'];
        $this->raw_body = $data['raw_body'];
        $this->debug_id = $data['debug_id'];
    }
}
