<?php

namespace SolidAffiliate\Lib\CsvExport;

interface CsvExportInterface
{
    /**
     * @return void
     */
    public static function register_export();

    /**
     * @param \SolidAffiliate\Lib\VO\CsvExport[] $exports
     *
     * @return \SolidAffiliate\Lib\VO\CsvExport[]
     */
    public static function add_csv_export($exports);

    /**
     * @return \SolidAffiliate\Lib\VO\CsvExport
     */
    public static function csv_export();
}

