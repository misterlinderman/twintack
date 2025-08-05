<?php

namespace SolidAffiliate\Lib\CsvExport;

use SolidAffiliate\Addons\DataExport\Addon;
use SolidAffiliate\Lib\AffiliateRegistrationForm\AffiliateRegistrationFormFunctions;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\CsvColumn;
use SolidAffiliate\Lib\VO\CsvExport;
use SolidAffiliate\Models\Affiliate;

class AffiliateExport implements CsvExportInterface
{
    const DEFAULT_FILENAME_PREFIX = 'solid-affiliate-affiliates-export';
    const POST_PARAM = 'submit_download_affiliate_csv';
    const NONCE_DOWNLOAD = 'solid-affiliate-download-affiliate-csv';
    const MODEL_NAME = Affiliate::MODEL_NAME;
    const DEFAULT_SKIPPED_VALUE = "";
    const CSV_EXPORT_FILTER = 'solid_affiliate/csv_exports/affiliates';

    /**
     * Add this export to the list of export to be displayed by the Data Export Addon.
     *
     * @return void
     */
    public static function register_export()
    {
        add_filter(Addon::CSV_EXPORTS_FILTER_NAME, [self::class, "add_csv_export"]);
    }

    /**
     * The returned CsvExport is used by the Data Export Addon to display download panel for this resource.
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
     * Returns the CsvExport object used as the interface to export Affiliates.
     *
     * @return CsvExport
     */
    public static function csv_export()
    {
        $default_export = new CsvExport([
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

        /** @var mixed $export */
        $export = apply_filters(self::CSV_EXPORT_FILTER, $default_export);

        if ($export instanceof CsvExport) {
            return $export;
        } else {
            return $default_export;
        }
    }

    /**
     * The array of Affiliates to be downloaded.
     *
     * @return array<Affiliate>
     */
    private static function default_record_query()
    {
        # TODO: For all the Export classes maybe we should validate the query array later, so this query can be added
        #       to before being executed. The Data Export page renders the count of each record query which would be
        #       done via a DB count query instead of an array count.
        $all = Validators::arr_of_affiliate(Affiliate::all());
        return AffiliateRegistrationFormFunctions::flatten_custom_data_into_affiliates($all);
    }

    /**
     * The number of records to be downloaded.
     *
     * @return int
     */
    private static function default_count_query()
    {
        return Affiliate::count();
    }

    /**
     * Returns an array of column objects that define how to export themselves to a CSV.
     *
     * @return CsvColumn[]
     */
    public static function column_list()
    {
        $columns = [
            new CsvColumn([
                'name' => self::total_visits_name(),
                'format_callback' =>
                /** @param \SolidAffiliate\Lib\MikesDataModel $affiliate */
                static function ($affiliate) {
                    if ($affiliate instanceof Affiliate) {
                        return CsvExportFunctions::default_format_validation(
                            Affiliate::total_visit_count($affiliate->id)
                        );
                    } else {
                        return self::DEFAULT_SKIPPED_VALUE;
                    }
                },
            ]),
            new CsvColumn([
                'name' => self::total_referrals_name(),
                'format_callback' =>
                /** @param \SolidAffiliate\Lib\MikesDataModel $affiliate */
                static function ($affiliate) {
                    if ($affiliate instanceof Affiliate) {
                        return CsvExportFunctions::default_format_validation(
                            Affiliate::total_referral_count($affiliate->id)
                        );
                    } else {
                        return self::DEFAULT_SKIPPED_VALUE;
                    }
                },
            ]),
            new CsvColumn([
                'name' => 'WordPress User Email',
                'format_callback' =>
                /** @param \SolidAffiliate\Lib\MikesDataModel $affiliate */
                static function ($affiliate) {
                    if ($affiliate instanceof Affiliate) {
                        return CsvExportFunctions::default_format_validation(
                            Affiliate::account_email_for($affiliate)
                        );
                    } else {
                        return self::DEFAULT_SKIPPED_VALUE;
                    }
                },
            ]),
        ];

        return array_merge(CsvExportFunctions::schema_columns(Affiliate::schema_with_custom_registration_data()), $columns);
    }

    /**
     * The translated string representing the total visit count for an affiliate csv column.
     *
     * @return string
     */
    public static function total_visits_name()
    {
        return __('Total Visits', 'solid-affiliate');
    }

    /**
     * The translated string representing the total referral count for an affiliate csv column.
     *
     * @return string
     */
    public static function total_referrals_name()
    {
        return __('Total Referrals', 'solid-affiliate');
    }
}
