<?php

namespace SolidAffiliate\Models;

use Exception;
use SolidAffiliate\Lib\MikesDataModelTrait;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\GlobalTypes;
use SolidAffiliate\Lib\ListTables\AffiliateGroupsListTable;
use SolidAffiliate\Lib\MikesDataModel;
use SolidAffiliate\Lib\RandomData;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Lib\VO\DatabaseTableOptions;
use SolidAffiliate\Lib\VO\Either;
use WP_Error;

/**
 * @psalm-import-type EnumOptionsReturnType from \SolidAffiliate\Lib\VO\SchemaEntry
 *
 * @property array $attributes
 *
 * @property int $id
 * @property string $name
 * @property float $commission_rate
 * @property 'site_default'|'percentage'|'flat' $commission_type
 * @property string $created_at
 * @property string $updated_at
 */
class AffiliateGroup extends MikesDataModel
{
    use MikesDataModelTrait;

    /**
     * Data table name in database (without prefix).
     * @var string
     */
    const TABLE = "solid_affiliate_" . "affiliate_groups";
    const PRIMARY_KEY = 'id';

    /**
     * The Model name, used to identify it in Filters.
     *
     * @since TBD
     *
     * @var string
     */
    const MODEL_NAME = 'AffiliateGroup';

    /**
     * Used to represent the URL key on WP Admin
     * 
     * @var string
     */
    const ADMIN_PAGE_KEY = 'solid-affiliate-affiliate-groups';

    /**
     * The dfault group name.
     * @var string
     */
    const DEFAULT_NAME = "Affiliates (default group)";

    const SETUP_WIZARD_GROUP_NAME = "Created during setup";

    /**
     * An affiliate default commission rate.
     * @var float
     */
    const DEFAULT_COMMISSION_RATE = 20.0;

    /**
     * An affiliate default commission type.
     * @var string
     */
    const DEFAULT_COMMISSION_TYPE = 'site_default';

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
     * @var Schema<"id"|"name"|"commission_rate"|"commission_type"|"created_at"|"updated_at">|null
     */
    private static $schema_cache = null;

    /**
     * @return Schema<"id"|"name"|"commission_rate"|"commission_type"|"created_at"|"updated_at">
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
                'display_name' => __('ID', 'solid-affiliate')
            ]),
            'name' => new SchemaEntry([
                'type' => 'varchar',
                'default' => '',
                'user_default' => '',
                'form_input_description' => __('The group name.', 'solid-affiliate'),
                'length' => 255,
                'display_name' => __('Group Name', 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'sanitize_callback' =>
                /** @param string $val */
                static function ($val) {
                    // @todo here default to the user email if empty?
                    return trim($val);
                },
            ]),
            'commission_type' => new SchemaEntry([
                'type' => 'varchar',
                'length' => 255,
                'required' => true,
                'display_name' => __('Commission Type', 'solid-affiliate'),
                'form_input_description' => __("Used in conjunction with the Referral Rate to calculate the referral amounts for this affiliate group. You can edit the site default in Settings > General.", 'solid-affiliate'),
                'is_enum' => true,
                'enum_options' => [
                    ['site_default', __('Site Default', 'solid-affiliate')],
                    ['percentage', __('Percentage', 'solid-affiliate')],
                    ['flat', __('Flat', 'solid-affiliate')],
                ],
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => false,
                'user_default' => static::defaults()['commission_type'],
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
                'user_default' => static::defaults()['commission_rate'],
                'custom_form_input_attributes' => [
                    'min' => '0',
                    'step' => 'any'
                ],
                'is_zero_value_allowed' => true,
                'validate_callback' =>
                /** @param mixed $commission_rate */
                static function ($commission_rate) {
                    // @todo should this be validated against the commission type too? <= 100 if %.
                    return (is_int($commission_rate) || is_float($commission_rate)) && $commission_rate >= 0;
                },
                'sanitize_callback' =>
                /** @param mixed $commission_rate */
                static function ($commission_rate) {
                    return (float) $commission_rate;
                },
            ]),
            'created_at' => new SchemaEntry([
                'type' => 'datetime',
                'display_name' => __('Created', 'solid-affiliate'),
                'show_list_table_column' => true,
                'key' => true
            ]),
            'updated_at' => new SchemaEntry([
                'type' => 'datetime',
                'display_name' => __('Updated', 'solid-affiliate')
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
            'singular'      => 'Affiliate Group',
            'plural'        => 'Affiliate Groups',
            'show_ui'       => false,        // Make custom table visible on admin area (check 'views' parameter)
            'show_in_rest'  => false,        // Make custom table visible on rest API
            'version'       => 2,           // Change the version on schema changes to run the schema auto-updater
            'primary_key' => self::PRIMARY_KEY,    // If not defined will be checked on the field that hsa primary_key as true on schema
            'schema'        => self::schema()
        ));
    }

    /**
     * @return array{name:string, commission_rate: float, commission_type: string}
     */
    public static function defaults()
    {
        return [
            'name' => __(self::DEFAULT_NAME, 'solid-affiliate'),
            'commission_rate' => self::DEFAULT_COMMISSION_RATE,
            'commission_type' => self::DEFAULT_COMMISSION_TYPE,
        ];
    }

    /**
     * @return string[]
     *
     * @psalm-return array<array-key, string>
     */
    public static function required_fields()
    {
        $schema = AffiliateGroup::schema();
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

    /**
     * @return AffiliateGroupsListTable
     */
    public static function admin_list_table()
    {
        return new AffiliateGroupsListTable();
    }


    /**
     * Will create a default affiliate group, if applicable.
     *
     * @return Either<int> If created, the Affiliate Group ID
     */
    public static function maybe_create_default_affiliate_group()
    {
        $should_create = (AffiliateGroup::count() == 0) &&
            (bool)Settings::get(Settings::KEY_AFFILIATE_GROUP_SHOULD_CREATE_DEFAULT);

        if ($should_create) {
            $either_group_id = AffiliateGroup::upsert(array_merge(self::defaults()));
            if ($either_group_id->isLeft) {
                return new Either([__('Did not create the default Affiliate Group.', 'solid-affiliate')], 0, false);
            } else {
                Settings::set_many(
                    [
                        Settings::KEY_AFFILIATE_GROUP_DEFAULT_GROUP_ID => $either_group_id->right,
                        Settings::KEY_AFFILIATE_GROUP_SHOULD_CREATE_DEFAULT => false,
                    ]
                );
                return new Either([''], $either_group_id->right, true);
            }
        } else {
            return new Either([__('Did not create the default Affiliate Group.', 'solid-affiliate')], 0, false);
        }
    }


    /**
     * Will create an affiliate group for the setup wizard, if applicable.
     *
     * @return Either<int> If created, the Affiliate Group ID
     */
    public static function maybe_create_setup_wizard_affiliate_group()
    {
        // if group iwht name already exists, return that.
        $maybe_existing_group = AffiliateGroup::find_where(['name' => self::SETUP_WIZARD_GROUP_NAME]);
        if ($maybe_existing_group instanceof AffiliateGroup) {
            return new Either([''], (int)$maybe_existing_group->id, true);
        }

        $either_group_id = AffiliateGroup::upsert(array_merge(self::defaults(), ['name' => self::SETUP_WIZARD_GROUP_NAME]));
        if ($either_group_id->isLeft) {
            return new Either([__('Did not create the default Affiliate Group.', 'solid-affiliate')], 0, false);
        } else {
            return new Either([''], (int)$either_group_id->right, true);
        }
    }

    /**
     * Will add an affiliate to the default group if the setting is enabled, and the group exists etc.
     *
     * @param int $affiliate_id
     * 
     * @return Either<int> AffiliateGroup id
     */
    public static function maybe_add_affiliate_to_default_group($affiliate_id)
    {
        $settings = Settings::get_many(
            [
                Settings::KEY_AFFILIATE_GROUP_SHOULD_ADD_AFFILIATES_TO_DEFAULT_GROUP,
                Settings::KEY_AFFILIATE_GROUP_DEFAULT_GROUP_ID
            ]
        );

        $should_add = (bool)$settings[Settings::KEY_AFFILIATE_GROUP_SHOULD_ADD_AFFILIATES_TO_DEFAULT_GROUP];
        $default_id = (int)$settings[Settings::KEY_AFFILIATE_GROUP_DEFAULT_GROUP_ID];

        if ($should_add && AffiliateGroup::find($default_id)) {
            $either_group_id = self::add_affiliate_to_group($affiliate_id, $default_id);
            if ($either_group_id->isLeft) {
                return new Either([__('Did not add affiliate to default group.', 'solid-affiliate')], 0, false);
            } else {
                return new Either([''], $either_group_id->right, true);
            }
        } else {
            return new Either([__('Did not add affiliate to default group.', 'solid-affiliate')], 0, false);
        }
    }

    /**
     * @param int $affiliate_id
     * @param int $group_id
     * 
     * @return Either<int> AffiliateGroup id
     */
    public static function add_affiliate_to_group($affiliate_id, $group_id)
    {
        $maybe_affiliate = Affiliate::find($affiliate_id);
        if ($maybe_affiliate instanceof Affiliate) {
            $either = Affiliate::updateInstance($maybe_affiliate, ['affiliate_group_id' => $group_id]);
            if ($either->isLeft) {
                return new Either($either->left, 0, false);
            } else {
                return new Either([''], $group_id, true);
            }
        } {
            return new Either([__('Error adding affiliate to group.', 'solid-affiliate')], 0, false);
        }
    }



    /**
     * Get's a tuple of available Affiliate Groups, for use in enum_options / select / dropdown.
     * 
     * @return EnumOptionsReturnType A tuple that will define each new user ID and label.
     */
    public static function affiliate_groups_list()
    {
        $group_tuples = array_map(static function (AffiliateGroup $affiliate_group) {
            return [
                Validators::str($affiliate_group->id),
                $affiliate_group->name
            ];
        }, AffiliateGroup::all());

        // PHP is insane, how tf do i do this properly.
        $group_tuples[] = ['', __('Select an affiliate group', 'solid-affiliate')];
        return array_reverse($group_tuples);
    }

    /**
     * @param int $affiliate_group_id
     * @return array<int>
     */
    public static function affiliate_ids_for($affiliate_group_id)
    {
        return Affiliate::select_ids(
            ['affiliate_group_id' => $affiliate_group_id]
        );
    }

    /**
     * @return int
     */
    public static function get_default_group_id()
    {
        return (int)Settings::get(Settings::KEY_AFFILIATE_GROUP_DEFAULT_GROUP_ID);
    }

    /**
     * @param int $id
     * @return int
     */
    public static function set_default_group_id($id)
    {
        Settings::set([Settings::KEY_AFFILIATE_GROUP_DEFAULT_GROUP_ID => $id]);
        return $id;
    }

    /**
     * @param Affiliate $affiliate
     * 
     * @return null|AffiliateGroup
     */
    public static function for_affiliate($affiliate)
    {
        return AffiliateGroup::find((int)$affiliate->affiliate_group_id);
    }
}
