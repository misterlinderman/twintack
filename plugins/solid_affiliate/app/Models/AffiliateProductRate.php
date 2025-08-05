<?php

namespace SolidAffiliate\Models;

use Exception;
use SolidAffiliate\Lib\FF;
use SolidAffiliate\Lib\GlobalTypes;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\ListTables\AffiliateProductRatesListTable;
use SolidAffiliate\Lib\ListTables\TemplatesListTable;
use SolidAffiliate\Lib\MikesDataModel;
use SolidAffiliate\Lib\MikesDataModelTrait;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\DatabaseTableOptions;
use SolidAffiliate\Lib\VO\OrderItemDescription;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;


/**
 * @property array $attributes
 *
 * @property int $id
 * @property int $woocommerce_product_id
 * @property int $affiliate_id
 * @property float $commission_rate
 * @property 'site_default'|'percentage'|'flat' $commission_type
 * @property string $created_at
 * @property string $updated_at
 * @property bool $is_auto_referral
 * @property bool $is_prevent_additional_referrals_when_auto_referral_is_triggered
 */
class AffiliateProductRate extends MikesDataModel
{
    use MikesDataModelTrait;

    /**
     * Data table name in database (without prefix).
     * @var string
     */
    const TABLE = "solid_affiliate_affiliate_product_rates";

    const PRIMARY_KEY = 'id';

    /**
     * An affiliate default status.
     * @var string
     */
    const DEFAULT_STATUS = 'active';

    /**
     * The Model name, used to identify it in Filters.
     *
     * @since TBD
     *
     * @var string
     */
    const MODEL_NAME = 'AffiliateProductRate';

    /**
     * Used to represent the URL key on WP Admin
     * 
     * @var string
     */
    const ADMIN_PAGE_KEY = 'solid-affiliate-affiliate-product-rates';

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
     * @var  Schema<string>|null
     */
    private static $schema_cache = null;

    /**
     * @return array{status: string}
     */
    public static function defaults()
    {
        return [
            'status' => self::DEFAULT_STATUS,
        ];
    }

    /**
     * @return Schema<string>
     */
    public static function schema()
    {
        if (!is_null(self::$schema_cache)) {
            return self::$schema_cache;
        }

        $entries =  [
            'id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'auto_increment' => true,
                'primary_key' => true,
                'show_list_table_column' => true,
                'display_name' => __('ID', 'solid-affiliate'),
                'show_on_edit_form' => 'disabled',
                'user_default' => null,
                'form_input_description' => __("The ID of the Affiliate Product Rate record.", 'solid-affiliate'),
                'validate_callback' =>
                /** @param mixed $id */
                static function ($id) {
                    return is_int($id);
                },
                'sanitize_callback' =>
                /** @param mixed $id */
                static function ($id) {
                    return (int) $id;
                },
            ]),
            'woocommerce_product_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'show_list_table_column' => true,
                'display_name' => __('WooCommerce Product ID', 'solid-affiliate'),
                'form_input_description' => __("The WooCommerce Product.", 'solid-affiliate'),
                'form_input_type_override' => 'woocommerce_product_select',
                'required' => true,
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'user_default' => null,
                'validate_callback' =>
                /** @param mixed $id */
                static function ($id) {
                    return is_int($id) && WooCommerceIntegration::does_product_exist($id);
                },
                'sanitize_callback' =>
                /** @param mixed $id */
                static function ($id) {
                    return (int) $id;
                },
            ]),
            'affiliate_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'show_list_table_column' => true,
                'required' => true,
                'display_name' => __('Affiliate ID', 'solid-affiliate'),
                'form_input_description' => __("The Associated Affiliate.", 'solid-affiliate'),
                'form_input_type_override' => 'affiliate_select',
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'user_default' => null,
                'validate_callback' =>
                /** @param mixed $id */
                static function ($id) {
                    return is_int($id) && (Affiliate::find($id));
                },
                'sanitize_callback' =>
                /** @param mixed $id */
                static function ($id) {
                    return (int) $id;
                },
            ]),
            'commission_type' => new SchemaEntry([
                'type' => 'varchar',
                'length' => 255,
                'required' => true,
                'display_name' => __('Commission Type', 'solid-affiliate'),
                'form_input_description' => __("Defines how commission amounts are calculated. Adjust the default in the plugin settings.", 'solid-affiliate'),
                'is_enum' => true,
                'enum_options' => [
                    ['site_default', __('Site Default', 'solid-affiliate')],
                    ['percentage', __('Percentage', 'solid-affiliate')],
                    ['flat', __('Flat', 'solid-affiliate')],
                ],
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'user_default' => 'site_default',
                'validate_callback' =>
                /** @param string $commission_type */
                static function ($commission_type) {
                    return in_array(trim($commission_type), ['site_default', 'percentage', 'flat'], true);
                },
                'sanitize_callback' =>
                /** @param string $commission_type */
                static function ($commission_type) {
                    return trim($commission_type);
                },
            ]),
            'commission_rate' => new SchemaEntry([
                'type' => 'float',
                'required' => true,
                'display_name' => __('Commission Rate', 'solid-affiliate'),
                'form_input_description' => GlobalTypes::REFERRAL_RATE_DESCRIPTION(),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'user_default' => 20,
                'custom_form_input_attributes' => [
                    'min' => '0',
                    'step' => 'any'
                ],
                'is_zero_value_allowed' => true
            ]),
            'is_auto_referral' => new SchemaEntry([
                'type' => 'bool',
                'required' => false,
                'display_name' => __('Revenue sharing', 'solid-affiliate'),
                'show_list_table_column' => true,
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'form_input_description' => __('Automatically create a referral for this affiliate whenever the linked product is purchased, even if they didn’t refer the customer. Ideal for revenue-sharing setups.', 'solid-affiliate')
            ]),
            'is_prevent_additional_referrals_when_auto_referral_is_triggered' => new SchemaEntry([
                'type' => 'bool',
                'required' => false,
                'default' => true,
                'user_default' => true,
                'display_name' => __('Prevent additional commissions', 'solid-affiliate'),
                'show_list_table_column' => true,
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'form_input_description' => __('If "Revenue sharing" is enabled, this prevents duplicate commissions for the same affiliate. For example, Affiliate #1 will earn only the shared revenue, not two separate referrals, when their link or coupon is used.', 'solid-affiliate')
            ]),
            'created_at' => new SchemaEntry([
                'type' => 'datetime',
                'display_name' => __('Created At', 'solid-affiliate'),
                'show_list_table_column' => false,
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
                'key' => true
            ]),
            'updated_at' => new SchemaEntry([
                'type' => 'datetime',
                'display_name' => __('Updated At', 'solid-affiliate'),
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
        ];

        $entries = FF::filter_schema_entries_for_feature_flag($entries, 'revenue_sharing');

        self::$schema_cache = new Schema(['entries' => $entries]);

        return self::$schema_cache;
    }

    /**
     * @return DatabaseTableOptions
     */
    public static function ct_table_options()
    {
        return new DatabaseTableOptions(array(
            'singular'      => 'Affiliate Product Rate',
            'plural'        => 'Affiliate Product Rates',
            'show_ui'       => false,        // Make custom table visible on admin area (check 'views' parameter)
            'show_in_rest'  => false,        // Make custom table visible on rest API
            'version'       => 4,           // Change the version on schema changes to run the schema auto-updater
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
        'created_at',
        'updated_at'
    ];

    /**
     * @return AffiliateProductRatesListTable
     */
    public static function admin_list_table()
    {
        return new AffiliateProductRatesListTable();
    }

    /**
     * @param Affiliate $affiliate
     * @param OrderItemDescription $order_item_description
     * 
     * @return AffiliateProductRate|null
     */
    public static function for_affiliate_and_order_item_description($affiliate, $order_item_description)
    {
        return AffiliateProductRate::find_where([
            'affiliate_id' => $affiliate->id,
            'woocommerce_product_id' => $order_item_description->product_id
        ]);
    }
}
