<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Lib\VO\CsvExport;

/**
 * @psalm-import-type BulkPayoutCSVMapping from \SolidAffiliate\Lib\GlobalTypes
 */
class CSVExporter
{
    const DEFAULT_FILENAME = "php://output";
    const DEFAULT_MODE = "wb";
    const COMMAND_CHARS = ['=', '+', '-', '@'];

    /**
     * Sets HTTP Headers appropriate for CSV export.
     * 
     * @param string $filename
     *
     * @return void
     */
    public static function set_http_headers_for_csv_export($filename = "solid-affiliate.csv")
    {

        nocache_headers();
        header("Content-Transfer-Encoding: UTF-8");
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header("Expires: 0");
    }

    # NOTE: The rules for CSVs are important. To ensure good display, put doublequotes around your fields, and don't forget to replace double-quotes inside fields to double double-quotes:
    #   echo '"'.str_replace('"','""',$record1).'","'.str_replace....

    /**
     * Downloads a csv for the given resource.
     *
     * @param CsvExport $export
     *
     * @return void
     */
    public static function download_resource($export)
    {
        self::set_http_headers_for_csv_export($export->filename);

        $fp = fopen(self::DEFAULT_FILENAME, self::DEFAULT_MODE);

        $columns = $export->columns;

        $headers = array_map(function ($column) {
            return $column->name;
        }, $columns);

        fputcsv($fp, $headers);

        $records = call_user_func($export->record_query_callback);

        foreach ($records as $record) {
            $row = array_map(function ($column) use ($record) {
                return self::guard_against_command_injection((string)call_user_func($column->format_callback, $record));
            }, $columns);

            fputcsv($fp, $row);
        }

        fclose($fp);
    }

    /**
     * Taken from /woocommerce/includes/export/abstract-wc-csv-exporter.php `escape_data`
     *
     * Escape a string to be used in a CSV context
	 *
	 * Malicious input can inject formulas into CSV files, opening up the possibility
	 * for phishing attacks and disclosure of sensitive information.
	 *
	 * Additionally, Excel exposes the ability to launch arbitrary commands through
	 * the DDE protocol.
	 *
	 * @see http://www.contextis.com/resources/blog/comma-separated-vulnerabilities/
	 * @see https://hackerone.com/reports/72785
     *
     * @param string $cell
     *
     * @return string
     */
    private static function guard_against_command_injection($cell)
    {
		if ( in_array( mb_substr( $cell, 0, 1 ), self::COMMAND_CHARS, true ) ) {
			$cell = " " . $cell;
		}

		return $cell;
    }
}
