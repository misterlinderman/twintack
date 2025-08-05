<?php


namespace SolidAffiliate\Models;

use Exception;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\ListTables\PayoutsListTable;
use SolidAffiliate\Lib\MikesDataModel;
use SolidAffiliate\Lib\MikesDataModelTrait;
use SolidAffiliate\Lib\RandomData;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Lib\VO\DatabaseTableOptions;

/**
 * @property array $attributes
 *
 * @property int  $id
 * @property int  $affiliate_id
 * @property int  $created_by_user_id
 * @property float  $amount
 * @property string  $description
 * @property self::PAYOUT_METHOD_* $payout_method
 * @property 'paid'|'failed'  $status
 * @property int  $bulk_payout_id
 * @property string  $created_at
 * @property string  $updated_at
 */
class Payout extends MikesDataModel
{
    use MikesDataModelTrait;

    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';

    const PAYOUT_METHOD_MANUAL = 'manual';
    const PAYOUT_METHOD_PAYPAL = 'paypal';
    const PAYOUT_METHOD_STORE_CREDIT = 'store_credit';


    /**
     * Data table name in database (without prefix).
     * @var string
     */
    const TABLE = "solid_affiliate_" . "payouts";
    const PRIMARY_KEY = 'id';

    /**
     * The Model name, used to identify it in Filters.
     *
     * @since TBD
     *
     * @var string
     */
    const MODEL_NAME = 'Payout';

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
     * Used to represent the URL key on WP Admin
     * 
     * @var string
     */
    const ADMIN_PAGE_KEY = 'solid-affiliate-payouts';

    /**
     * @var Schema<"affiliate_id"|"amount"|"created_at"|"created_by_user_id"|"description"|"id"|"payout_method"|"status"|"bulk_payout_id"|"updated_at">|null
     */
    private static $schema_cache = null;

    /**
     * @return Schema<"affiliate_id"|"amount"|"created_at"|"created_by_user_id"|"description"|"id"|"payout_method"|"status"|"bulk_payout_id"|"updated_at">
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
            'affiliate_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => true,
                'display_name' => __('Affiliate ID', 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'key' => true,
                'is_csv_exportable' => true
            ]),
            'amount' => new SchemaEntry(array(
                'type' => 'float',
                'required' => true,
                'display_name' => __('Amount', 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'is_csv_exportable' => true,
                'csv_export_callback' =>
                /** @param float $amount */
                static function ($amount) {
                    return Formatters::raw_money_str($amount);
                }
            )),
            'description' => new SchemaEntry(array(
                'type' => 'text',
                'display_name' => __('Description', 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'is_csv_exportable' => true
            )),
            'payout_method' => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'required' => true,
                'display_name' => __('Payout Method', 'solid-affiliate'),
                'is_enum' => true,
                'enum_options' => [
                    [self::PAYOUT_METHOD_MANUAL, __('Manual', 'solid-affiliate')],
                    [self::PAYOUT_METHOD_PAYPAL, 'PayPal'],
                    // TODO only activate this if the store credit addon is enabled
                    [self::PAYOUT_METHOD_STORE_CREDIT, 'Store Credit']
                ],
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'is_csv_exportable' => true
            )),
            'bulk_payout_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => false,
                'default' => 0,
                'display_name' => __('Bulk Payout ID', 'solid-affiliate'),
                'form_input_description' => __("The ID of the Bulk Payout associated with this Payout, if one exists.", 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'key' => true,
                'is_csv_exportable' => true
            ]),
            'created_by_user_id' => new SchemaEntry(array(
                'type' => 'bigint',
                'length' => 20,
                'required' => true,
                'display_name' => __('Created By User ID', 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'key' => true,
                'is_csv_exportable' => true
            )),
            'status' => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'required' => true,
                'is_enum' => true,
                'enum_options' => [
                    [self::STATUS_PAID, __('Paid', 'solid-affiliate')],
                    [self::STATUS_FAILED, __('Failed', 'solid-affiliate')],
                ],
                'display_name' => __('Status', 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'user_default' => 'pending',
                'key' => true,
                'is_csv_exportable' => true
            )),
            'created_at' => new SchemaEntry(array(
                'type' => 'datetime',
                'display_name' => __('Created', 'solid-affiliate'),
                'show_list_table_column' => true,
                'key' => true,
                'is_csv_exportable' => true
            )),
            'updated_at' => new SchemaEntry(array(
                'type' => 'datetime',
                'display_name' => __('DateTime', 'solid-affiliate'),
                'is_csv_exportable' => true
            )),
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
            'singular'      => 'Payout',
            'plural'        => 'Payouts',
            'show_ui'       => false,        // Make custom table visible on admin area (check 'views' parameter)
            'show_in_rest'  => false,        // Make custom table visible on rest API
            'version'       => 2,           // Change the version on schema changes to run the schema auto-updater
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
        $schema = Payout::schema();
        $required_fields = SchemaFunctions::required_fields_from_schema($schema);

        return $required_fields;
    }

    /**
     * Model properties, data column list.
     * @var string[]
     * TODO make this a function of the schema so we can DRY things up.
     */
    protected $attributes = [
        self::PRIMARY_KEY,
        'amount',
        'status',
        'created_at',
        'updated_at'
        // TODO
    ];

    // TODO figure out how to make this work so we don't dumplicate data.
    // currently i'm getting
    // Fatal error: Constant expression contains invalid operations
    // protected $attributes = array_keys(self::SCHEMA);

    /**
     * @return PayoutsListTable
     */
    public static function admin_list_table()
    {
        return new PayoutsListTable();
    }

    /**
     * Very WIP, used in a couple tests currently.
     * 
     * Goal: Creates and returns a new Record with random data.
     * 
     * @param array{affiliate_id: int, created_by_user_id: int} $required_args
     *
     * @return self
     */
    public static function random($required_args)
    {
        $random_args = [
            'amount' => RandomData::float(),
            'description' => 'Random description of Payout.',
            'payout_method' => 'manual', // todo add support for paypal
            'status' => Referral::STATUS_PAID,
            'created_at' => RandomData::date(),
            'bulk_payout_id' => 0
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

    /**
     * @param self[] $payouts
     * 
     * @return float
     */
    public static function sum_amount($payouts)
    {
        return array_sum(array_map(function ($p) {
            return $p->amount;
        }, $payouts));

    }
}
