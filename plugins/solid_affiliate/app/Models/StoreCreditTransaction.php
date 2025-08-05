<?php


namespace SolidAffiliate\Models;

use Exception;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\ListTables\PayoutsListTable;
use SolidAffiliate\Lib\MikesDataModel;
use SolidAffiliate\Lib\MikesDataModelTrait;
use SolidAffiliate\Lib\RandomData;
use SolidAffiliate\Lib\SchemaFunctions;
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
 * @property 'manual'|'payout'|'woocommerce_purchase'|'woocommerce_subscription_renewal'  $source
 * @property int  $source_id
 * @property 'credit'|'debit'  $type
 * @property string  $created_at
 * @property string  $updated_at
 */
class StoreCreditTransaction extends MikesDataModel
{
    use MikesDataModelTrait;

    const TYPE_CREDIT = 'credit';
    const TYPE_DEBIT = 'debit';

    const SOURCE_MANUAL = 'manual';
    const SOURCE_PAYOUT = 'payout';
    const SOURCE_WOOCOMMERCE_PURCHASE = 'woocommerce_purchase';
    const SOURCE_WOOCOMMERCE_SUBSCRIPTION_RENEWAL = 'woocommerce_subscription_renewal';

    /**
     * Data table name in database (without prefix).
     * @var string
     */
    const TABLE = "solid_affiliate_" . "store_credit_transactions";
    const PRIMARY_KEY = 'id';

    /**
     * The Model name, used to identify it in Filters.
     *
     * @since TBD
     *
     * @var string
     */
    const MODEL_NAME = 'StoreCreditTransaction';

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
    const ADMIN_PAGE_KEY = 'solid-affiliate-store-credit';

        /**
     * @var Schema<"affiliate_id"|"amount"|"created_at"|"created_by_user_id"|"description"|"id"|"source"|"source_id"|"type"|"updated_at">|null
     */
    private static $schema_cache = null;

    /**
     * @return Schema<"affiliate_id"|"amount"|"created_at"|"created_by_user_id"|"description"|"id"|"source"|"source_id"|"type"|"updated_at">
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
            'source' => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'required' => true,
                'display_name' => __('Source', 'solid-affiliate'),
                'is_enum' => true,
                'enum_options' => [
                    ['manual', __('Manual', 'solid-affiliate')],
                    ['payout', 'Payout'],
                    ['woocommerce_purchase', 'WooCommerce Purchase'],
                    ['woocommerce_subscription_renewal', 'WooCommerce Subscription Renewal']
                ],
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'is_csv_exportable' => true
            )),
            'source_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => false,
                'display_name' => __('Source ID', 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'key' => true,
                'is_csv_exportable' => true
            ]),
            'type' => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'required' => true,
                'display_name' => __('Type', 'solid-affiliate'),
                'is_enum' => true,
                'enum_options' => [
                    [self::TYPE_CREDIT, 'Credit'],
                    [self::TYPE_DEBIT, 'Debit']
                ],
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'is_csv_exportable' => true
            )),
            'created_by_user_id' => new SchemaEntry(array(
                'type' => 'bigint',
                'default' => null,
                'user_default' => null,
                'nullable' => true,
                'length' => 20,
                'required' => false,
                'display_name' => __('Created By User ID', 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
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
            'singular'      => 'Store Credit Transaction',
            'plural'        => 'Store Credit Transactions',
            'show_ui'       => false,        // Make custom table visible on admin area (check 'views' parameter)
            'show_in_rest'  => false,        // Make custom table visible on rest API
            'version'       => 1,           // Change the version on schema changes to run the schema auto-updater
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
        $schema = self::schema();
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
    // public static function admin_list_table()
    // {
    //     return new PayoutsListTable();
    // }

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
        $random_source = RandomData::from_array([self::SOURCE_MANUAL, self::SOURCE_PAYOUT, self::SOURCE_WOOCOMMERCE_PURCHASE, self::SOURCE_WOOCOMMERCE_SUBSCRIPTION_RENEWAL]);
        // TODO we need data integrity in the model. This should be caught.
        if (in_array($random_source, [self::SOURCE_WOOCOMMERCE_PURCHASE, self::SOURCE_WOOCOMMERCE_SUBSCRIPTION_RENEWAL])) {
            unset($required_args['created_by_user_id']);
        }

        // Weight debits 2/3 of the time, so the total credit doesn't end up negative. Wouldn't make sense
        $random_type = RandomData::from_array([self::TYPE_CREDIT, self::TYPE_DEBIT, self::TYPE_DEBIT]);
        $random_amount = RandomData::float();
        $formatted_amount = Formatters::money($random_amount);

        switch ($random_source) {
            case self::SOURCE_MANUAL:
                $sign = $random_type === self::TYPE_DEBIT ? '+' : '-';
                $random_source_id = 0;
                $description = "Manual adjustment of {$sign}{$formatted_amount}. Reason given: none."; // TODO Reason given
                break;
            case self::SOURCE_PAYOUT:
                $random_type = self::TYPE_DEBIT;
                $random_source_id = RandomData::from_array(array_map(function ($p) {
                    return $p->id;
                }, Payout::all()));
                $description = "Credit earned through Payout #{$random_source_id}.";
                break;
            case self::SOURCE_WOOCOMMERCE_PURCHASE:
                $random_type = self::TYPE_CREDIT;
                // get the last woocommerce order id
                $wc_order_posts = get_posts(array(
                    'numberposts' => 10,
                    'post_type'   => 'shop_order',
                    'post_status'    => 'any'
                ));
                $random_source_id = RandomData::from_array(array_map(
                    function ($p) {
                        /** @var \WP_Post $p */
                        return $p->ID;
                    },
                    $wc_order_posts
                ));
                // $random_source_id = $wc_order_posts[0]->ID;

                $description = "Credit used for order #{$random_source_id}";
                break;
            case self::SOURCE_WOOCOMMERCE_SUBSCRIPTION_RENEWAL:
                $random_type = self::TYPE_CREDIT;
                // get the last woocommerce order id
                $wc_order_posts = get_posts(array(
                    'numberposts' => 10,
                    'post_type'   => 'shop_order',
                    'post_status'    => 'any'
                ));
                $random_source_id = RandomData::from_array(array_map(function ($p) {
                    /** @var \WP_Post $p */
                    return $p->ID;
                }, $wc_order_posts));
                // $random_source_id = $wc_order_posts[0]->ID;
                $description = "Credit used for subscription renewal #{$random_source_id}";
                break;
        }

        $random_args = [
            'amount' => $random_amount,
            'type' => $random_type,
            'source' => $random_source,
            'source_id' => $random_source_id, // TODO this is more complicated. it depends on the source.
            'description' => $description,
            'created_at' => RandomData::date(),
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
     * @param self $self
     * @return boolean
     */
    public static function is_created_by_user_id_applicable($self)
    {
        return (in_array($self->source, [self::SOURCE_PAYOUT, self::SOURCE_MANUAL]));
    }
}
