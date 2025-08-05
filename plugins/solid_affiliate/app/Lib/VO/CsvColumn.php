<?php

namespace SolidAffiliate\Lib\VO;

use SolidAffiliate\Lib\MikesDataModel;

/**
 * @psalm-type CsvColumnType = array{
 *   name: string,
 *   format_callback: callable(MikesDataModel):string,
 * }
 */

 class CsvColumn
 {
    /** @var CsvColumnType $data */
    public $data;

    /** @var string $name */
    public $name;

    /** @var callable(MikesDataModel):string $format_callback */
    public $format_callback;

    /** @param CsvColumnType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->name = $data['name'];
        $this->format_callback = $data['format_callback'];
    }
 }