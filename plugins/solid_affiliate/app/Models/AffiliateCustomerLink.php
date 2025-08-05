<?php


namespace SolidAffiliate\Models;

use Exception;
use SolidAffiliate\Lib\FF;
use SolidAffiliate\Lib\LifetimeCommissions;
use SolidAffiliate\Lib\MikesDataModel;
use SolidAffiliate\Lib\MikesDataModelTrait;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\SolidAdminCRUD;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Lib\VO\DatabaseTableOptions;

/**
 * @property array $attributes
 * 
 * @property int  $id
 * @property int  $affiliate_id
 * @property int  $customer_id
 * @property string  $customer_email
 * @property int  $expires_on_unix_seconds
 * @property string  $created_at
 * @property string  $updated_at
 */
class AffiliateCustomerLink extends MikesDataModel
{
    use MikesDataModelTrait;

    const TABLE_SUFFIX = "affiliate_customer_links";
    /**
     * Data table name in database (without prefix).
     * @var string
     */
    const TABLE = "solid_affiliate_" . self::TABLE_SUFFIX;
    const PRIMARY_KEY = 'id';

    /**
     * The Model name, used to identify it in Filters.
     *
     * @since TBD
     *
     * @var string
     */
    const MODEL_NAME = 'AffiliateCustomerLink';

    /**
     * Data table name in database (without prefix).
     * @var string
     */
    protected $table = self::TABLE;

    /**
     * Primary key column name. Default ID
     * @var string
     */
    protected $primary_key = self::PRIMARY_KEY;

    /**
     * @var Schema<"affiliate_id"|"created_at"|"customer_id"|"expires_on_unix_seconds"|"id"|"updated_at"|"customer_email">|null
     */
    private static $schema_cache = null;

    /**
     * Used to represent the URL key on WP Admin
     * 
     * @var string
     */
    const ADMIN_PAGE_KEY = 'solid-affiliate-affiliate-customer-link';

    const POST_PARAM_CREATE_RESOURCE = self::ADMIN_PAGE_KEY . '_create';

    const POST_PARAM_DELETE_RESOURCE = self::ADMIN_PAGE_KEY . '_delete';

    /**
     * TODO make this return some nice data maybe?
     * 
     * @return void
     */
    public static function register_admin_crud_resource()
    {

        $crud_description = [
            'model' => self::class,
            'table_suffix' => self::TABLE_SUFFIX,
            'schema' => self::schema(),
            'page_key' => self::ADMIN_PAGE_KEY,
            'menu_title_override' => __('Lifetime Customers', 'solid-affiliate'),
            'model_singular' => 'Lifetime Customer',
            'model_plural' => 'Lifetime Customers',
            'post_param_create_resource' => self::POST_PARAM_CREATE_RESOURCE,
            'post_param_delete_resource' => self::POST_PARAM_DELETE_RESOURCE,
            'search_by_key' => 'customer_email',
            'column_name_overrides' => [
                'affiliate_id' => 'Affiliate',
                'customer_id' => 'Customer',
                'expires_on_unix_seconds' => 'Expires On',
            ],
            'submenu_position' => 3,
        ];

        if (FF::is_off('lifetime_commissions')) {
            // TODO-FF the PRO pill here
            $crud_description['menu_title_override'] = __('Lifetime Customers', 'solid-affiliate') . FF::pill();
            $crud_description['ff_disabled'] = true;
            SolidAdminCRUD::register($crud_description);
            return;
        }

        if (LifetimeCommissions::is_lifetime_commissions_enabled()) {
            SolidAdminCRUD::register($crud_description);
        }
    }


    /**
     * @return Schema<"affiliate_id"|"created_at"|"customer_id"|"expires_on_unix_seconds"|"id"|"updated_at"|"customer_email">
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
            ]),
            'affiliate_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => true,
                'display_name' => __('Affiliate ID', 'solid-affiliate'),
                'form_input_description' => __("The ID of the Affiliate who is linked with a customer.", 'solid-affiliate'),
                'form_input_type_override' => 'affiliate_select',
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'key' => true,
            ]),
            'customer_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => false,
                'display_name' => __('Customer ID', 'solid-affiliate'),
                'form_input_description' => __("The ID of the Customer associated with this Affiliate. You can enter 0 if there is no customer ID, i.e. it is a guest and you want to use customer email.", 'solid-affiliate'),
                'form_input_type_override' => 'user_select',
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'key' => false,
                'is_zero_value_allowed' => true
            ]),
            'customer_email' => new SchemaEntry([
                'type' => 'varchar',
                'length' => 255,
                'required' => false,
                'display_name' => __('Customer Email', 'solid-affiliate'),
                'form_input_description' => __("The email of the Customer associated with this Affiliate. You can enter a blank value if there is no customer email.", 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'key' => false,
            ]),
            'expires_on_unix_seconds' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => true,
                'display_name' => __('Expires On Unix Seconds', 'solid-affiliate'),
                'form_input_description' => __("When this Affiliate-Customer link expires. You can enter 0 to never expire. The value must be entered as a Unix Time Stamp - you can use a <a href='https://www.unixtimestamp.com/index.php'>Unix Timestamp Converter</a> to select a date such as one year from now.", 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'key' => false,
                'is_zero_value_allowed' => true
            ]),
            'created_at' => new SchemaEntry(array(
                'type' => 'datetime',
                'display_name' => __('Created', 'solid-affiliate'),
                'show_list_table_column' => true,
                'key' => true,
            )),
            'updated_at' => new SchemaEntry(array(
                'type' => 'datetime',
                'display_name' => __('DateTime', 'solid-affiliate'),
            ))
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
            'singular'      => 'Affiliate Customer Link',
            'plural'        => 'Affiliate Customer Links',
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
        $schema = self::schema();
        $required_fields = SchemaFunctions::required_fields_from_schema($schema);

        return $required_fields;
    }

    /**
     * Model properties, data column list.
     * @var string[]
     */
    protected $attributes = [
        self::PRIMARY_KEY,
    ];
}
