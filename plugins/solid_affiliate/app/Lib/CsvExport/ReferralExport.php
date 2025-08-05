<?php

namespace SolidAffiliate\Lib\CsvExport;

use SolidAffiliate\Addons\DataExport\Addon;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\CsvColumn;
use SolidAffiliate\Lib\VO\CsvExport;
use SolidAffiliate\Models\Referral;

class ReferralExport implements CsvExportInterface
{
    const DEFAULT_FILENAME_PREFIX = 'solid-affiliate-referrals-export';
    const POST_PARAM = 'submit_download_referral_csv';
    const NONCE_DOWNLOAD = 'solid-affiliate-download-referral-csv';
    const MODEL_NAME = Referral::MODEL_NAME;

    /**
     * Add this export to the list of exports to be displayed by the Data Export Addon.
     *
     * @return void
     */
    public static function register_export()
    {
        add_filter(Addon::CSV_EXPORTS_FILTER_NAME, [self::class, "add_csv_export"]);
    }

    /**
     * Returns the CsvExport object used as the interface to export Referrals.
     *
     * @param CsvExport[] $exports
     *
     * @return CsvExport[]
     */
    public static function add_csv_export($exports)
    {
        $exports[] = self::csv_export();
        return $exports;
    }

    /**
     * Returns the CsvExport object used as the interface to export Referrals.
     *
     * @return CsvExport
     */
    public static function csv_export()
    {
        return new CsvExport([
            'resource_name' => self::MODEL_NAME,
            'sub_heading' => CsvExportFunctions::default_sub_heading(self::MODEL_NAME),
            'nonce_download' => self::NONCE_DOWNLOAD,
            'post_param' => self::POST_PARAM,
            'filename' => CsvExportFunctions::default_filename(self::DEFAULT_FILENAME_PREFIX),
            'record_query_callback' =>
            static function () {
                return self::default_record_query();
            },
            'record_count_callback' =>
            static function () {
                return self::default_count_query();
            },
            'columns' => self::column_list()
        ]);
    }

    /**
     * The array of Referrals to be downloaded.
     *
     * @return Referral[]
     */
    private static function default_record_query()
    {
        return Validators::arr_of_referral(Referral::all());
    }

    /**
     * The number of records to be downloaded.
     *
     * @return int
     */
    private static function default_count_query()
    {
        return Referral::count();
    }

    /**
     * Returns an array of column objects that define how to export themselves to a CSV.
     *
     * @return CsvColumn[]
     */
    private static function column_list()
    {
        return CsvExportFunctions::schema_columns(Referral::schema());
    }
}
