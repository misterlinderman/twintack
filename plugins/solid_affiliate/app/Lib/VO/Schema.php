<?php

namespace SolidAffiliate\Lib\VO;

use SolidAffiliate\Lib\VO\SchemaEntry;

/**
 * @template TKey of string
 */
class Schema
{
    /** @var array<TKey, SchemaEntry> $entries */
    public $entries;

    /** @param array{entries: array<TKey, SchemaEntry>} $data */
    public function __construct($data)
    {
        $this->entries = $data['entries'];
    }
}
