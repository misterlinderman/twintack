<?php

namespace SolidAffiliate\Lib\CsvExport;

use SolidAffiliate\Lib\MikesDataModel;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\CsvColumn;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;

class CsvExportFunctions
{
    /**
     * The name of the CSV to be downloaded.
     *
     * @param string $prefix
     *
     * @return string
     */
    public static function default_filename($prefix)
    {
        return sprintf('%1$s-%2$s.csv', __($prefix, 'solid-affiliate'), date('m/d/Y h:i:s a'));
    }

    /**
     * Returns the key for all exportable schema entries.
     *
     * @param Schema $schema
     *
     * @return string[]
     */
    public static function exportable_schema_entry_keys($schema)
    {

        return Validators::array_of_string(array_keys(SchemaFunctions::csv_exportable_entries($schema)));
    }

    /**
     * The default format validation function for a CSV cell.
     *
     * @param mixed $val
     *
     * @return string
     */
    public static function default_format_validation($val)
    {
        return Validators::str($val);
    }

    /**
     * The default sub heading for single CSV Export section.
     *
     * @param string $model_name
     *
     * @return string
     */
    public static function default_sub_heading($model_name)
    {
        return sprintf(__("Export all %s data to a CSV file.", 'solid-affiliate'), $model_name);
    }

    /**
     * Returns a list of CsvColumn's for each SchemaEntry that has been declared as CSV exportable.
     *
     * @param Schema<string> $schema
     *
     * @return CsvColumn[]
     */
    public static function schema_columns($schema)
    {
        return array_map(
            function ($key) use ($schema) {
                $entry = $schema->entries[$key];

                return new CsvColumn([
                    'name' => $entry->display_name,
                    'format_callback' =>
                    /** @param MikesDataModel $resource */
                    static function ($resource) use ($key, $entry) {
                        $attrs = Validators::arr($resource->attributes);
                        if (isset($attrs[$key])) {
                            return self::default_format_validation(SchemaEntry::csv_export($entry, $attrs[$key]));
                        } else {
                            return '';
                        }
                    }
                ]);
            },
            self::exportable_schema_entry_keys($schema)
        );
    }
}
