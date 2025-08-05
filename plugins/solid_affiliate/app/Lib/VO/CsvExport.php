<?php

namespace SolidAffiliate\Lib\VO;

use SolidAffiliate\Lib\MikesDataModel;

/**
 * @psalm-type CsvExportType = array{
 *   resource_name: string,
 *   sub_heading: string,
 *   nonce_download: string,
 *   post_param: string,
 *   filename: string,
 *   record_query_callback: callable():MikesDataModel[],
 *   record_count_callback: callable():int,
 *   columns: CsvColumn[]
 * }
 */

class CsvExport
{
    /** @var CsvExportType $data */
    public $data;

    /** @var string $resource_name */
    public $resource_name;

    /** @var string $sub_heading */
    public $sub_heading;

    /** @var string $nonce_download */
    public $nonce_download;

    /** @var string $post_param */
    public $post_param;

    /** @var string $filename */
    public $filename;

    /** @var callable():MikesDataModel[] $record_query_callback */
    public $record_query_callback;

    /** @var callable():int $record_query_callback */
    public $record_count_callback;

    /** @var CsvColumn[] $columns */
    public $columns;

    /** @param CsvExportType $data */
    public function __construct($data)
    {
        $this->data = $data;

        $this->resource_name = $data['resource_name'];
        $this->sub_heading = $data['sub_heading'];
        $this->nonce_download = $data['nonce_download'];
        $this->post_param = $data['post_param'];
        $this->filename = $data['filename'];
        $this->record_query_callback = $data['record_query_callback'];
        $this->record_count_callback = $data['record_count_callback'];
        $this->columns = $data['columns'];
    }
}
