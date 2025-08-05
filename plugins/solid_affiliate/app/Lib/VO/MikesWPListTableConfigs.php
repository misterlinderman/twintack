<?php

namespace SolidAffiliate\Lib\VO;

use SolidAffiliate\Lib\VO\Schema;

/**
 * @psalm-type MType = array{
 *  singular: string, 
 *  plural: string, 
 *  schema: Schema,
 *  page_key: string
 * }
 */
class M
{
    /** @var MType $data */
    public $data;

    /** @param MType $data */
    public function __construct($data)
    {
        $this->data = $data;
    }
}
