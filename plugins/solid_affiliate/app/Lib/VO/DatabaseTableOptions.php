<?php

namespace SolidAffiliate\Lib\VO;

use SolidAffiliate\Lib\VO\Schema;

/**
 * @psalm-type DatabaseTableOptionsType = array{
 *      singular: string, 
 *      plural: string, 
 *      show_ui: boolean, 
 *      show_in_rest: boolean, 
 *      version: int, 
 *      primary_key: string, 
 *      schema: Schema
 * }
 */
class DatabaseTableOptions
{
    /** @var DatabaseTableOptionsType $data */
    public $data;

    /** @param DatabaseTableOptionsType $data */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Data for custom tables creation.
     *
     * @return array
     */
    public function data_for_ct()
    {
        $data = $this->data;

        $data['schema'] = $data['schema']->entries;

        /** @var array */
        $array_data = json_decode(json_encode($data), true);

        return $array_data;
    }
}
