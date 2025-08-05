<?php

namespace SolidAffiliate\Lib\VO;

// Notes
// Routes can be matched on the presence of and value of 
// a POST paramater.

/** 
 * @psalm-type RouteDescriptionType = array{
 *  post_param_key: string,
 *  post_param_val?: array<string>|string|null,
 *  post_param_key_b?: string|null,
 *  post_param_val_b?: array<string>|string|null,
 *  nonce: string,
 *  callback: callable
 * } 
 */
class RouteDescription
{

    /** @var RouteDescriptionType $data */
    public $data;

    /** @var string */
    public $post_param_key;

    /** @var array<string>|string|null */
    public $post_param_val;

    /** @var string|null */
    public $post_param_key_b;

    /** @var array<string>|string|null */
    public $post_param_val_b;

    /** @var string */
    public $nonce;

    /** @var callable */
    public $callback;

    /** @param RouteDescriptionType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->post_param_key = $data['post_param_key'];
        $this->post_param_val = isset($data['post_param_val']) ? $data['post_param_val'] : null;
        $this->post_param_key_b = isset($data['post_param_key_b']) ? $data['post_param_key_b'] : null;
        $this->post_param_val_b = isset($data['post_param_val_b']) ? $data['post_param_val_b'] : null;
        $this->nonce = $data['nonce'];
        $this->callback = $data['callback'];
    }
}
