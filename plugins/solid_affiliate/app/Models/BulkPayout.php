<?php

namespace SolidAffiliate\Models;

use SolidAffiliate\Controllers\PayAffiliatesController;
use SolidAffiliate\Lib\GlobalTypes;
use SolidAffiliate\Lib\ListTables\BulkPayoutsListTable;
use SolidAffiliate\Lib\MikesDataModel;
use SolidAffiliate\Lib\MikesDataModelTrait;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\DatabaseTableOptions;

/**
 * @psalm-type BulkPayoutStatus = BulkPayout::FAIL_STATUS|BulkPayout::PROCESSING_STATUS|BulkPayout::SUCCESS_STATUS
 */

// TODO: use union types for all the enums - method, date_range, status, currency
/**
 * @property array $attributes
 * 
 * @property int $id
 * @property string $date_range_start
 * @property string $date_range_end
 * @property string $currency
 * @property PayAffiliatesController::BULK_PAYOUT_METHOD_* $method
 * @property string $date_range
 * @property float $total_amount
 * @property string $status
 * @property string $reference
 * @property string $created_at
 * @property string $updated_at
 * @property int $created_by_user_id
 * @property string $api_mode
 * @property string $serialized_logic_rules
 */
class BulkPayout extends MikesDataModel
{
    use MikesDataModelTrait;

    const API_MODE_LIVE = 'live';
    const API_MODE_SANDBOX = 'sandbox';

    /* Table name in database without prefix without the wp_ prefix */
    const TABLE = 'solid_affiliates_bulk_payouts';

    const PRIMARY_KEY = 'id';

    const MODEL_NAME = 'BulkPayout';

    /**
     * This lives on the pay affiliates page
     * TODO: Do we want to keep this constant here?
     */
    const ADMIN_PAGE_KEY = 'solid-affiliate-pay-affiliates';
    const ADMIN_PAGE_TAB = 'bulk_payouts'; // TODO use this constant in place of the string

    protected $table = self::TABLE;

    protected $primary_key = self::PRIMARY_KEY;

        /**
     * @var Schema<"created_at"|"created_by_user_id"|"currency"|"date_range"|"date_range_end"|"date_range_start"|"id"|"method"|"reference"|"status"|"total_amount"|"updated_at"|"api_mode"|"serialized_logic_rules">|null
     */
    private static $schema_cache = null;


    const FAIL_STATUS = 'fail';
    const PROCESSING_STATUS = 'processing';
    const SUCCESS_STATUS = 'success';

    // TOOD: maybe better names?
    // TODO: do we care about CANCELED?
    const STATUS_ENUM_OPTIONS = [
        [self::FAIL_STATUS, 'Fail'],
        [self::PROCESSING_STATUS, 'Processing'],
        [self::SUCCESS_STATUS, 'Success']
    ];

    /**
     * @return Schema<"created_at"|"created_by_user_id"|"currency"|"date_range"|"date_range_end"|"date_range_start"|"id"|"method"|"reference"|"status"|"total_amount"|"updated_at"|"api_mode"|"serialized_logic_rules">
     */
    public static function schema()
    {
        if (!is_null(self::$schema_cache)) {
            return self::$schema_cache;
        }

        $entries = [
            'id' => new SchemaEntry([
                'display_name' => __('ID', 'solid-affiliate'),
                'type' => 'bigint',
                'primary_key' => true,
                'auto_increment' => true,
                'length' => 20,
                'required' => true,
                'show_list_table_column' => true,
                'validate_callback' =>
                /** @param mixed $id */
                static function ($id) {
                    return is_int($id);
                },
                'sanitize_callback' =>
                /** @param mixed $id */
                static function ($id) {
                    return (int) $id;
                }
            ]),
            'date_range_start' => new SchemaEntry([
                'display_name' => __('Date range start', 'solid-affiliate'),
                'type' => 'datetime',
                'show_list_table_column' => true
            ]),
            'date_range_end' => new SchemaEntry([
                'display_name' => __('Date range end', 'solid-affiliate'),
                'type' => 'datetime',
                'show_list_table_column' => true
            ]),
            'currency' => new SchemaEntry([
                'display_name' => __('Currency', 'solid-affiliate'),
                'type' => 'varchar',
                'length' => 255,
                'show_list_table_column' => true,
                'required' => true,
                'is_enum' => true,
                'enum_options' => Settings::SUPPORTED_CURRENCIES
            ]),
            'method' => new SchemaEntry([
                'display_name' => __('Payment method', 'solid-affiliate'),
                'type' => 'varchar',
                'length' => 255,
                'show_list_table_column' => true,
                'required' => true,
                'is_enum' => true,
                'enum_options' => [
                    [PayAffiliatesController::BULK_PAYOUT_METHOD_CSV, PayAffiliatesController::BULK_PAYOUT_METHOD_CSV],
                    [PayAffiliatesController::BULK_PAYOUT_METHOD_PAYPAL, PayAffiliatesController::BULK_PAYOUT_METHOD_PAYPAL],
                    [PayAffiliatesController::BULK_PAYOUT_METHOD_STORE_CREDIT, PayAffiliatesController::BULK_PAYOUT_METHOD_STORE_CREDIT],
                ] //using PayAffiliatesController::payout_method_enum_options() does now work because it checks the options DB, which is not setup during model creation.
            ]),
            'date_range' => new SchemaEntry([
                'display_name' => __('Date range', 'solid-affiliate'),
                'type' => 'varchar',
                'length' => 255,
                'show_list_table_column' => true,
                'required' => true,
                'is_enum' => true,
                'enum_options' => GlobalTypes::translated_DATE_RANGE_ENUM_OPTIONS()
            ]),
            'total_amount' => new SchemaEntry([
                'display_name' => __('Total payout amount', 'solid-affiliate'),
                'type' => 'float',
                'show_list_table_column' => true,
                'required' => true
            ]),
            'status' => new SchemaEntry([
                'display_name' => __('Status', 'solid-affiliate'),
                'type' => 'varchar',
                'length' => 255,
                'show_list_table_column' => true,
                'required' => true,
                'is_enum' => true,
                'enum_options' => self::STATUS_ENUM_OPTIONS
            ]),
            'reference' => new SchemaEntry([
                'display_name' => __('Reference', 'solid-affiliate'),
                'type' => 'text',
                'show_list_table_column' => true,
                'required' => true
            ]),
            'api_mode' => new SchemaEntry([
                'display_name' => __('API Mode', 'solid-affiliate'),
                'type' => 'varchar',
                'length' => 255,
                'show_list_table_column' => false,
                'required' => true,
                'is_enum' => true,
                'enum_options' => [
                    [self::API_MODE_LIVE, 'Live'],
                    [self::API_MODE_SANDBOX, 'Sandbox']
                ]
            ]),
            'created_by_user_id' => new SchemaEntry([
                'display_name' => __('Created by user ID', 'solid-affiliate'),
                'length' => 20,
                'type' => 'bigint',
                'show_list_table_column' => true,
                'required' => true
            ]),
            'created_at' => new SchemaEntry([
                'type' => 'datetime',
                'display_name' => __('Created at', 'solid-affiliate'),
                'show_list_table_column' => true,
                'validate_callback' =>
                /** @param string $created_at */
                static function ($created_at) {
                    return is_int(strtotime($created_at));
                },
                'sanitize_callback' =>
                /** @param mixed $created_at */
                static function ($created_at) {
                    return Validators::str($created_at);
                },
            ]),
            'updated_at' => new SchemaEntry([
                'type' => 'datetime',
                'display_name' => __('Updated at', 'solid-affiliate'),
                'validate_callback' =>
                /** @param string $updated_at */
                static function ($updated_at) {
                    return is_int(strtotime($updated_at));
                },
                'sanitize_callback' =>
                /** @param mixed $updated_at */
                static function ($updated_at) {
                    return Validators::str($updated_at);
                },
            ]),
            'serialized_logic_rules' => new SchemaEntry([
                'type' => 'text',
                'display_name' => __('Filters', 'solid-affiliate'),
                'form_input_description' => __("Filters", 'solid-affiliate'),
                'show_on_new_form' => false,
                'show_on_edit_form' => false,
                'show_list_table_column' => true,
                'user_default' => serialize(array())
            ])
        ];

        self::$schema_cache = new Schema(['entries' => $entries]);

        return self::$schema_cache;
    }

    /**
     * @return DatabaseTableOptions
     */
    public static function ct_table_options()
    {
        return new DatabaseTableOptions([
            'singular'      => 'Bulk Payout',
            'plural'        => 'Bulk Payouts',
            'show_ui'       => false, // Make custom table visible on admin area (check 'views' parameter)
            'show_in_rest'  => false, // Make custom table visible on rest API
            'version'       => 125, // Change the version on schema changes to run the schema auto-updater
            'primary_key'   => self::PRIMARY_KEY,
            'schema'        => self::schema()
        ]);
    }

    /**
     * @return string[]
     *
     * @psalm-return array<array-key, string>
     */
    public static function required_fields()
    {
        return SchemaFunctions::required_fields_from_schema(self::schema());
    }

    /**
     * Model properties, data column list.
     * 
     * @var string[]
     */
    // TODO: make this a function of the schema so we can DRY things up.
    protected $attributes = [
        self::PRIMARY_KEY,
        'date_range_start',
        'date_range_end',
        'currency',
        'method',
        'date_range',
        'total_amount',
        'status',
        'reference',
        'created_by_user_id',
        'created_at',
        'updated_at',
        'api_mode'
    ];

    /**
     * @return BulkPayoutsListTable
     */
    public static function admin_list_table()
    {
        return new BulkPayoutsListTable();
    }

    /**
     * Undocumented function
     * 
     * TODO Sandbox vs real
     *
     * @param string $reference
     * @param bool $sandbox
     * @return string
     */
    public static function paypal_reference_url($reference, $sandbox = true)
    {
        if ($sandbox) {
            return "https://www.sandbox.paypal.com/activity/masspay/MPA-{$reference}";
        } else {
            # TODO verify that this URL is correct
            return "https://www.paypal.com/activity/masspay/MPA-{$reference}";
        }
    }
}
