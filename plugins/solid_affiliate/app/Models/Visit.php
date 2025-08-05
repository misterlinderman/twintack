<?php

namespace SolidAffiliate\Models;

use Exception;
use LanguageServerProtocol\Range;
use SolidAffiliate\Lib\ListTables\VisitsListTable;
use SolidAffiliate\Lib\MikesDataModelTrait;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\MikesDataModel;
use SolidAffiliate\Lib\RandomData;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Lib\VO\DatabaseTableOptions;
use SolidAffiliate\Lib\VO\Either;
use WP_Error;

/**
 * @psalm-type VisitDbCols = "affiliate_id"|"created_at"|"http_ip"|"http_referrer"|"id"|"landing_url"|"previous_visit_id"|"referral_id"|"updated_at"
 * @property array $attributes
 *
 * @property int $id
 * @property int $previous_visit_id
 * @property int $affiliate_id
 * @property int $referral_id
 * @property string $landing_url
 * @property string $http_referrer
 * @property string $http_ip
 * @property string $date
 * @property string $created_at
 * @property string $updated_at
 */
class Visit extends MikesDataModel
{
    use MikesDataModelTrait;

    /**
     * Data table name in database (without prefix).
     * @var string
     */
    const TABLE = "solid_affiliate_" . "visits";
    const PRIMARY_KEY = 'id';

    /**
     * The Model name, used to identify it in Filters.
     *
     * @since TBD
     *
     * @var string
     */
    const MODEL_NAME = 'Visit';

    /**
     * Used to represent the URL key on WP Admin
     * 
     * @var string
     */
    const ADMIN_PAGE_KEY = 'solid-affiliate-visits';

    /**
     * Data table name in database (without prefix).
     * @var string
     * TODO the 'solid_affiliate_' prefix should be standardized somewhere
     */
    protected $table = self::TABLE;

    /**
     * Primary key column name. Default ID
     * @var string
     */
    protected $primary_key = self::PRIMARY_KEY;

    /**
     * @var Schema<VisitDbCols>|null
     */
    private static $schema_cache = null;


    /**
     * @return Schema<VisitDbCols>
     */
    public static function schema()
    {
        if (!is_null(self::$schema_cache)) {
            return self::$schema_cache;
        }

        $entries = array(
            'id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'auto_increment' => true,
                'primary_key' => true,
                'show_list_table_column' => true,
                'display_name' => __('ID', 'solid-affiliate'),
                'is_csv_exportable' => true
            ]),
            'previous_visit_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'show_list_table_column' => false,
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'display_name' => __('Previous Visit ID', 'solid-affiliate'),
                'form_input_description' => __("The ID of the tracked Visit previous to this Visit.", 'solid-affiliate'),
                'required'  => false,
                'user_default' => 0,
                'default' => 0,
                'is_csv_exportable' => true
            ]),
            'affiliate_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => true,
                'display_name' => __('Affiliate ID', 'solid-affiliate'),
                'form_input_description' => __("The ID of the Affiliate who sent over this Visit.", 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'key' => true,
                'is_csv_exportable' => true
            ]),
            'referral_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => false,
                'default' => 0,
                'user_default' => 0,
                'display_name' => __('Referral ID', 'solid-affiliate'),
                'form_input_description' => __("The ID of the Referral (if there is one) that this Visit resulted in.", 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'key' => true,
                'is_csv_exportable' => true
            ]),
            'landing_url' => new SchemaEntry([
                'type' => 'text',
                'display_name' => __('Landing URL', 'solid-affiliate'),
                'form_input_description' => __("The URL of the landing page that the visitor was sent to.", 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'required' => true,
                'is_csv_exportable' => true
            ]),
            'http_referrer' => new SchemaEntry([
                'type' => 'text',
                'display_name' => __('HTTP Referrer', 'solid-affiliate'),
                'form_input_description' => __("The referring domain and page.", 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'required' => false,
                'default' => '',
                'is_csv_exportable' => true
            ]),
            'http_ip' => new SchemaEntry([
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('IP', 'solid-affiliate'),
                'form_input_description' => __("The IP of the Visitor.", 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'required' => false,
                'default' => '',
                'is_csv_exportable' => true
            ]),
            'created_at' => new SchemaEntry([
                'type' => 'datetime',
                'display_name' => __('Created', 'solid-affiliate'),
                'show_list_table_column' => true,
                'key' => true,
                'is_csv_exportable' => true
            ]),
            'updated_at' => new SchemaEntry([
                'type' => 'datetime',
                'display_name' => __('Updated', 'solid-affiliate'),
                'is_csv_exportable' => true
            ]),
        );

        self::$schema_cache = new Schema(['entries' => $entries]);

        return self::$schema_cache;
    }

    /**
     * @return DatabaseTableOptions
     */
    public static function ct_table_options()
    {

        return new DatabaseTableOptions(array(
            'singular'      => 'Visit',
            'plural'        => 'Visits',
            'show_ui'       => false,        // Make custom table visible on admin area (check 'views' parameter)
            'show_in_rest'  => false,        // Make custom table visible on rest API
            'version'       => 14,           // Change the version on schema changes to run the schema auto-updater
            'primary_key' => self::PRIMARY_KEY,    // If not defined will be checked on the field that hsa primary_key as true on schema
            'schema'        => self::schema()
        ));
    }

    /**
     * @return string[]
     *
     * @psalm-return array<array-key, string>
     */
    public static function required_fields()
    {
        $schema = Visit::schema();
        $required_fields = SchemaFunctions::required_fields_from_schema($schema);

        return $required_fields;
    }
    /**
     * Model properties, data column list.
     * @var string[]
     */
    protected $attributes = [
        self::PRIMARY_KEY,
        'affiliate_id'
        // TODO
    ];

    /**
     * @return VisitsListTable
     */
    public static function admin_list_table()
    {
        return new VisitsListTable();
    }

    /**
     * Returns an array of IDs for the a search term and any list of columns on the DB table via LIKE fuzzy searching.
     *
	 * @global \wpdb $wpdb
     *
     * @param array<VisitDbCols> $cols
     * @param string $search_term
     *
     * @return array<int>
     */
    public static function get_ids_by_columns($cols, $search_term)
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $search_wildcard = '%' . $search_term . '%';

        $where_cols = array_map(
            /**
             * @param VisitDbCols $col
             * @return string
             */
            function ($col) {
                return "{$col} LIKE %s";
            },
            $cols
        );

        $where_clause = implode(' OR ', $where_cols);

        return Validators::array_of_int($wpdb->get_col(
            Validators::str($wpdb->prepare(
                "SELECT id FROM {$table} WHERE {$where_clause}",
                $search_wildcard,
                $search_wildcard
            ))
        ));
    }

    /**
     * Very WIP, used in a couple tests currently.
     * 
     * Goal: Creates and returns a new Affiliate with random data.
     * 
     * @param array{affiliate_id: int} $required_args
     *
     * @return self
     */
    public static function random($required_args)
    {
        $affiliate_id = $required_args['affiliate_id'];
        $random_args = [
            'landing_url' => RandomData::url() . "?sld={$affiliate_id}",
            'http_referrer' => RandomData::url(),
            'http_ip' => RandomData::ip()
        ];
        $final_args = array_merge($random_args, $required_args);

        $either_id = self::upsert($final_args, true);
        if ($either_id->isLeft) {
            throw new Exception(implode(" ", $either_id->left));
        }
        $id = $either_id->right;

        $self = self::find($id);

        /** @var self */
        return $self;
    }
}
