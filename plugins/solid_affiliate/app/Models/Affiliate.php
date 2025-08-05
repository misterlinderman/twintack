<?php

namespace SolidAffiliate\Models;

use Exception;
use SolidAffiliate\Lib\AffiliateRegistrationForm\AffiliateRegistrationFormFunctions;
use SolidAffiliate\Lib\GlobalTypes;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\ListTables\AffiliatesListTable;
use SolidAffiliate\Lib\MikesDataModel;
use SolidAffiliate\Lib\MikesDataModelTrait;
use SolidAffiliate\Lib\RandomData;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Utils;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\DatabaseTableOptions;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use WP_User_Query;

/**
 * @property array $attributes
 *
 * @property int $id
 * @property int $user_id
 * @property int $affiliate_group_id
 * @property float $commission_rate
 * @property 'site_default'|'percentage'|'flat' $commission_type
 * @property string $payment_email
 * @property string $mailchimp_user_id
 * @property string $first_name
 * @property string $last_name
 * @property self::STATUS_APPROVED|self::STATUS_PENDING|self::STATUS_REJECTED $status
 * @property string $custom_registration_data
 * @property string $created_at
 * @property string $updated_at
 */
class Affiliate extends MikesDataModel
{
    use MikesDataModelTrait;

    const STATUS_APPROVED = 'approved';
    const STATUS_PENDING = 'pending';
    const STATUS_REJECTED = 'rejected';


    /**
     * Data table name in database (without prefix).
     * @var string
     */
    const TABLE = "solid_affiliate_affiliates";

    const PRIMARY_KEY = 'id';

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
     * An affiliate approved status.
     * @var string
     */
    const APPROVED_STATUS = 'approved';

    /**
     * An affiliate default status.
     * @var string
     */
    const DEFAULT_STATUS = self::APPROVED_STATUS;

    /**
     * The Model name, used to identify it in Filters.
     *
     * @since TBD
     *
     * @var string
     */
    const MODEL_NAME = 'Affiliate';

    /**
     * Used to represent the URL key on WP Admin
     * 
     * @var string
     */
    const ADMIN_PAGE_KEY = 'solid-affiliate-affiliates';

    const KEY_SHARED_WITH_PORTAL_LAST_NAME = 'last_name';
    const KEY_SHARED_WITH_PORTAL_FIRST_NAME = 'first_name';
    const KEY_SHARED_WITH_PORTAL_REGISTRATION_NOTES = 'registration_notes';
    const KEY_SHARED_WITH_PORTAL_PAYMENT_EMAIL = 'payment_email';
    /** @var array<Affiliate::KEY_SHARED_WITH_PORTAL_*> */
    const SCHEMA_ENTRIES_THAT_CAN_ALSO_BE_ON_THE_REGISTRATION_FORM = [
        self::KEY_SHARED_WITH_PORTAL_FIRST_NAME,
        self::KEY_SHARED_WITH_PORTAL_LAST_NAME,
        self::KEY_SHARED_WITH_PORTAL_PAYMENT_EMAIL,
        self::KEY_SHARED_WITH_PORTAL_REGISTRATION_NOTES
    ];

    const RESERVED_SCHEMA_ENTRY_KEY_COMISSION_RATE = 'commission_rate';
    const RESERVED_SCHEMA_ENTRY_KEY_COMISSION_TYPE = 'commission_type';
    const RESERVED_SCHEMA_ENTRY_KEY_CREATED_AT = 'created_at';
    const RESERVED_SCHEMA_ENTRY_KEY_ID = 'id';
    const RESERVED_SCHEMA_ENTRY_KEY_STATUS = 'status';
    const RESERVED_SCHEMA_ENTRY_KEY_UPDATED_AT = 'updated_at';
    const RESERVED_SCHEMA_ENTRY_KEY_USER_ID = 'user_id';
    const RESERVED_SCHEMA_ENTRY_KEY_MAILCHIMP_USER_ID = 'mailchimp_user_id';
    const RESERVED_SCHEMA_ENTRY_KEY_AFFILIATE_GROUP_ID = 'affiliate_group_id';
    const RESERVED_SCHEMA_ENTRY_KEY_CUSTOM_REGISTRATION_DATA = 'custom_registration_data';
    /** @var array<Affiliate::RESERVED_SCHEMA_ENTRY_KEY_*> */
    const RESERVED_AFFILIATE_COLUMNS = [
        self::RESERVED_SCHEMA_ENTRY_KEY_COMISSION_RATE,
        self::RESERVED_SCHEMA_ENTRY_KEY_COMISSION_TYPE,
        self::RESERVED_SCHEMA_ENTRY_KEY_CREATED_AT,
        self::RESERVED_SCHEMA_ENTRY_KEY_ID,
        self::RESERVED_SCHEMA_ENTRY_KEY_STATUS,
        self::RESERVED_SCHEMA_ENTRY_KEY_UPDATED_AT,
        self::RESERVED_SCHEMA_ENTRY_KEY_USER_ID,
        self::RESERVED_SCHEMA_ENTRY_KEY_MAILCHIMP_USER_ID,
        self::RESERVED_SCHEMA_ENTRY_KEY_AFFILIATE_GROUP_ID,
        self::RESERVED_SCHEMA_ENTRY_KEY_CUSTOM_REGISTRATION_DATA
    ];

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
     * @return array{commission_rate: float, commission_type: string, status: string}
     */
    public static function defaults()
    {
        return [
            'commission_rate' => self::DEFAULT_COMMISSION_RATE,
            'commission_type' => self::DEFAULT_COMMISSION_TYPE,
            'status' => self::DEFAULT_STATUS,
        ];
    }

    /**
     * Merges the static Schema into the custom user defined Schema, with the static Schema overriding the user defined one.
     *
     * @return Schema<string>
     */
    public static function schema_with_custom_registration_data()
    {
        $static_entries = self::schema()->entries;
        $registration_entries = AffiliatePortal::get_affiliate_registration_schema()->entries;
        # TODO:3: A 2 extra loop hack so that fields shpw up in the 'correct' order on the new form, list table, and edit form
        return new Schema(['entries' => array_reverse(array_merge($registration_entries, array_reverse($static_entries)))]);
    }

    /**
     * @var Schema<Affiliate::RESERVED_SCHEMA_ENTRY_KEY_*|Affiliate::KEY_SHARED_WITH_PORTAL_*|"affiliate_group_id">|null
     */
    private static $schema_cache = null;

    /**
     * @return Schema<Affiliate::RESERVED_SCHEMA_ENTRY_KEY_*|Affiliate::KEY_SHARED_WITH_PORTAL_*|"affiliate_group_id">
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
                'display_name' => __('Affiliate ID', 'solid-affiliate'),
                'form_input_description' => __('The ID of the Affiliate. Cannot be changed.', 'solid-affiliate'),
                'show_on_edit_form' => 'disabled',
                'user_default' => null,
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
                'is_csv_exportable' => true,
            ]),
            'user_id' => new SchemaEntry([
                'type'                   => 'bigint',
                'form_input_description' => __('Select user to link with this affiliate account.', 'solid-affiliate'),
                'form_input_type_override' => 'user_select',
                'length'                 => 20,
                'required'               => true,
                'display_name'           => __('WordPress user', 'solid-affiliate'),
                'custom_form_input_attributes' => ['min' => '1'],
                'show_on_new_form'       => true,
                'show_on_edit_form' => 'hidden_and_disabled',
                'show_list_table_column' => false,
                'unique'                 => true,
                'validate_callback' =>
                /** @param mixed $user_id */
                static function ($user_id) {
                    return is_int($user_id) && get_user_by('ID', $user_id) instanceof \WP_User;
                },
                'sanitize_callback' =>
                /** @param mixed $user_id */
                static function ($user_id) {
                    return (int) $user_id;
                },
                'is_csv_exportable' => true,
            ]),
            'affiliate_group_id' => new SchemaEntry([
                'type'                   => 'bigint',
                'default' => 0,
                'user_default' => 0,
                'is_enum'                => true,
                'enum_options'           => [AffiliateGroup::class, 'affiliate_groups_list'],
                'form_input_description' => __('Assign to a group or leave unselected to use the default group.', 'solid-affiliate'),
                'length'                 => 20,
                'required'               => false,
                'display_name'           => __('Affiliate Group ID', 'solid-affiliate'),
                'custom_form_input_attributes' => ['min' => '0'],
                'show_on_new_form'       => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'unique'                 => false,
                'validate_callback' =>
                /** @param mixed $affiliate_group_id */
                static function ($affiliate_group_id) {
                    return is_int($affiliate_group_id) && ($affiliate_group_id == 0 || AffiliateGroup::find($affiliate_group_id));
                },
                'sanitize_callback' =>
                /** @param mixed $affiliate_group_id */
                static function ($affiliate_group_id) {
                    return (int) $affiliate_group_id;
                },
                'is_csv_exportable' => true,
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
                'is_csv_exportable' => true,
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
                'is_csv_exportable' => true,
            ]),
            self::KEY_SHARED_WITH_PORTAL_PAYMENT_EMAIL => new SchemaEntry([
                'type' => 'varchar',
                'default' => '',
                'user_default' => '',
                'form_input_description' => __('Email used for payout payments.', 'solid-affiliate'),
                'length' => 255,
                'display_name' => __('Payment Email', 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'form_input_type_override' => 'email',
                'validate_callback' =>
                /** @param string $payment_email */
                static function ($payment_email) {
                    return '' === $payment_email || filter_var(trim($payment_email), FILTER_VALIDATE_EMAIL);
                },
                'sanitize_callback' =>
                /** @param string $payment_email */
                static function ($payment_email) {
                    // @todo here default to the user email if empty?
                    return trim($payment_email);
                },
                'is_csv_exportable' => true,
            ]),
            'mailchimp_user_id' => new SchemaEntry([
                'type' => 'varchar',
                'default' => '',
                'user_default' => '',
                'form_input_description' => __('Assign a unique ID for MailChimp integration.', 'solid-affiliate'),
                'length' => 255,
                'display_name' => __('MailChimp ID', 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'sanitize_callback' =>
                /** @param string $val */
                static function ($val) {
                    // @todo here default to the user email if empty?
                    return trim($val);
                },
                'is_csv_exportable' => true,
            ]),
            self::KEY_SHARED_WITH_PORTAL_FIRST_NAME => new SchemaEntry([
                'type' => 'varchar',
                'default' => '',
                'user_default' => '',
                'form_input_description' => __('First name of the affiliate.', 'solid-affiliate'),
                'length' => 255,
                'display_name' => __('First name', 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'sanitize_callback' =>
                /** @param string $val */
                static function ($val) {
                    return trim($val);
                },
                'is_csv_exportable' => true,
            ]),
            self::KEY_SHARED_WITH_PORTAL_LAST_NAME => new SchemaEntry([
                'type' => 'varchar',
                'default' => '',
                'user_default' => '',
                'form_input_description' => __('Last name of the affiliate.', 'solid-affiliate'),
                'length' => 255,
                'display_name' => __('Last name', 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'sanitize_callback' =>
                /** @param string $val */
                static function ($val) {
                    return trim($val);
                },
                'is_csv_exportable' => true,
            ]),
            self::KEY_SHARED_WITH_PORTAL_REGISTRATION_NOTES => new SchemaEntry([
                'type' => 'text',
                'default' => '',
                'user_default' => '',
                'form_input_type_override' => 'textarea',
                'custom_form_input_attributes' => [
                    'rows' => '8',
                ],
                'required' => false,
                'display_name' => __('Registration Notes', 'solid-affiliate'),
                'show_on_new_form' => true,
                // 'show_on_edit_form' => 'hidden_and_disabled',
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'form_input_description' => __('Notes provided by the affiliate during registration.', 'solid-affiliate'),
                'sanitize_callback' =>
                /** @param string $val */
                static function ($val) {
                    return wp_kses_post($val);
                },
                'is_csv_exportable' => true,
            ]),
            'status' => new SchemaEntry([
                'type' => 'varchar',
                'length' => 255,
                'required' => true,
                'is_enum' => true,
                'enum_options' => [
                    // @todo Either Accepted or Approved, pick one.
                    [self::APPROVED_STATUS, __('Approved', 'solid-affiliate')],
                    ['pending', __('Pending', 'solid-affiliate')],
                    ['rejected', __('Rejected', 'solid-affiliate')],
                ],
                'display_name' => __('Status', 'solid-affiliate'),
                'form_input_description' => __("Only approved affiliates can earn referrals.", 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'user_default' => static::defaults()['status'],
                'validate_callback' =>
                /** @param string $status */
                static function ($status) {
                    return in_array(trim($status), [self::APPROVED_STATUS, 'pending', 'rejected'], true);
                },
                'sanitize_callback' =>
                /** @param string $status */
                static function ($status) {
                    return trim($status);
                },
                'key' => true,
                'is_csv_exportable' => true,
            ]),
            self::RESERVED_SCHEMA_ENTRY_KEY_CUSTOM_REGISTRATION_DATA => new SchemaEntry([
                'type' => 'text',
                'default' => '',
                'user_default' => '',
                'show_on_edit_form' => 'hidden',
                'show_on_new_form' => 'hidden',
                'show_on_non_admin_edit_form' => 'hidden',
                'display_name' => __('Registration Info', 'solid-affiliate'),
                'validate_callback' =>
                /** @param mixed $val */
                static function ($val) {
                    if (empty($val)) {
                        return true;
                    } else {
                        return AffiliateRegistrationFormFunctions::can_json_str_be_saved_read_and_decoded($val);
                    }
                }
            ]),
            'created_at' => new SchemaEntry([
                'type' => 'datetime',
                'display_name' => __('Created At', 'solid-affiliate'),
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
                'key' => true,
                'is_csv_exportable' => true,
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
                'is_csv_exportable' => true,
            ]),
        ];

        self::$schema_cache = new Schema(['entries' => $entries]);

        return self::$schema_cache;
    }

    /**
     * @return DatabaseTableOptions
     */
    public static function ct_table_options()
    {
        return new DatabaseTableOptions(array(
            'singular'      => 'Affiliate',
            'plural'        => 'Affiliates',
            'show_ui'       => false,        // Make custom table visible on admin area (check 'views' parameter)
            'show_in_rest'  => false,        // Make custom table visible on rest API
            'version'       => 16,           // Change the version on schema changes to run the schema auto-updater
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
        $schema = Affiliate::schema();
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
        'user_id',
        'commission_rate',
        'commission_type',
        'payment_email',
        'status',
        'created_at',
        'updated_at'
    ];

    // TODO figure out how to make this work so we don't dumplicate data.
    // currently i'm getting
    // Fatal error: Constant expression contains invalid operations
    // protected $attributes = array_keys(self::SCHEMA);

    /**
     * @return AffiliatesListTable
     */
    public static function admin_list_table()
    {
        return new AffiliatesListTable();
    }


    /**
     * Checks the DB for an Affiliate.
     *
     * @param int $user_id
     * @return false|self
     */
    public static function for_user_id($user_id)
    {
        if ($user_id === 0) {
            return false;
        }

        // @todo check the user actually exists here?

        $maybe_affiliate =  Affiliate::find_where(['user_id' => $user_id]);
        if (is_null($maybe_affiliate)) {
            return false;
        } else {
            return $maybe_affiliate;
        }
    }

    /**
     * Returns the URL of the default affiliate link. The URL is based on the Affiliate Slug Display Format and Default Affiliate Link URL settings.
     * If the Affiliate Slug Display Format is set to Custom Slugs but there are none for the Affiliate, then it will default to the ID format.
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     *
     * @return string
     */
    public static function default_affiliate_link_for($affiliate)
    {
        return URLs::default_affiliate_link($affiliate);
    }

    /**
     * Checks if the Affiliate is approved.
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * @return boolean
     */
    public static function is_approved($affiliate)
    {
        if ($affiliate->status === self::APPROVED_STATUS) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Undocumented function
     *
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * @return string
     */
    public static function account_email_for($affiliate)
    {
        $maybe_user = $affiliate->user();
        if ($maybe_user) {
            return $maybe_user->user_email;
        } else {
            return "";
        }
    }

    /////// Has_many methods - TODO figure out how to do this better

    /**
     * @return \WP_User|false
     */
    public function user()
    {
        $user_id = (int)$this->user_id;

        return get_userdata($user_id);
    }

    /**
     * @param PresetDateRangeParams|null $maybe_preset_date_range_params
     * @param array<string> $referral_statuses
     * @return \SolidAffiliate\Models\Referral[]
     */
    public function referrals($maybe_preset_date_range_params = null, $referral_statuses = [])
    {
        $id = (int)$this->id;
        $where_clause = ['affiliate_id' => $id];

        if (!empty($referral_statuses)) {
            $where_clause['status'] = [
                'operator' => 'IN',
                'value' => $referral_statuses
            ];
        }
        $where_clause = Utils::merge_maybe_preset_date_range_params_into_where_clause($where_clause, $maybe_preset_date_range_params);
        return Referral::where($where_clause);
    }

    /**
     * @param PresetDateRangeParams|null $maybe_preset_date_range_params
     * @return \SolidAffiliate\Models\Payout[]
     */
    public function payouts($maybe_preset_date_range_params = null)
    {
        $id = (int)$this->id;
        $where_clause = ['affiliate_id' => $id];
        $where_clause = Utils::merge_maybe_preset_date_range_params_into_where_clause($where_clause, $maybe_preset_date_range_params);
        return Payout::where($where_clause);
    }

    /**
     * @param PresetDateRangeParams|null $maybe_preset_date_range_params
     * @return \SolidAffiliate\Models\Visit[]
     */
    public function visits($maybe_preset_date_range_params = null)
    {
        $id = (int)$this->id;
        $where_clause = ['affiliate_id' => $id];
        $where_clause = Utils::merge_maybe_preset_date_range_params_into_where_clause($where_clause, $maybe_preset_date_range_params);
        return Visit::where($where_clause);
    }

    /**
     * Calculates how much total paid out to an Affiliate.
     *
     * @param self $affiliate
     * @param PresetDateRangeParams|null $maybe_preset_date_range_params
     * 
     * @return float
     */
    public static function total_revenue($affiliate, $maybe_preset_date_range_params = null)
    {
        $where_clause = [
            'affiliate_id' => $affiliate->id,
            'status' => ['operator' => 'IN', 'value' => [Referral::STATUS_PAID, Referral::STATUS_UNPAID]]
        ];
        $where_clause = Utils::merge_maybe_preset_date_range_params_into_where_clause($where_clause, $maybe_preset_date_range_params);

        $referrals = Referral::where($where_clause);

        $amounts = array_map(
            /**
             * @param Referral $r
             * @return float
             **/
            function ($r) {
                /** @var float */
                return $r->order_amount;
            },
            $referrals
        );

        return array_sum($amounts);
    }


    /**
     * Calculates how much total paid out to an Affiliate.
     *
     * @param self $affiliate
     * @param PresetDateRangeParams|null $maybe_preset_date_range_params
     * 
     * @return float
     */
    public static function paid_earnings($affiliate, $maybe_preset_date_range_params = null)
    {
        $where_clause = ['affiliate_id' => $affiliate->id, 'status' => Referral::STATUS_PAID];
        $where_clause = Utils::merge_maybe_preset_date_range_params_into_where_clause($where_clause, $maybe_preset_date_range_params);

        $referrals = Referral::where($where_clause);

        $amounts = array_map(
            /**
             * @param Referral $r
             * @return float
             **/
            function ($r) {
                /** @var float */
                return $r->commission_amount;
            },
            $referrals
        );

        return array_sum($amounts);
    }

    /**
     * Calculates how much total paid out to an Affiliate.
     *
     * @param self $affiliate
     * @param PresetDateRangeParams|null $maybe_preset_date_range_params
     * 
     * @return float
     */
    public static function unpaid_earnings($affiliate, $maybe_preset_date_range_params = null)
    {
        $where_clause = ['affiliate_id' => $affiliate->id, 'status' => Referral::STATUS_UNPAID];
        $where_clause = Utils::merge_maybe_preset_date_range_params_into_where_clause($where_clause, $maybe_preset_date_range_params);

        $unpaid_referrals = Referral::where($where_clause);

        $amounts = array_map(
            /**
             * @param Referral $r
             * @return float
             * */
            function ($r) {
                /** @var float */
                return $r->commission_amount;
            },
            $unpaid_referrals
        );

        return array_sum($amounts);
    }

    /**
     * Very WIP, used in a couple tests currently.
     * 
     * Goal: Creates and returns a new Affiliate with random data.
     * 
     * @param array{user_id: int} $required_args
     *
     * @return self
     */
    public static function random($required_args)
    {
        $random_args = [
            'payment_email' => RandomData::email('paypal.com')
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
     * Searches for Affiliate(s)
     * 
     * By Affiliate->id
     * By Affiliate->wp_user->user_email
     * By Affiliate->wp_user->user_login
     * 
     * @template T as bool
     * 
     * @param string $search
     * @param T $return_ids
     *
     * @return int[]|self[]
     * @psalm-return (T is true ? int[] : self[])
     */
    public static function fuzzy_search($search, $return_ids = false)
    {
        $list = [];

        // Search by Affiiate->id
        if (is_numeric($search)) {
            $results = Affiliate::where(['id' => intval($search)]);
            $list = array_merge($list, $results);
        }

        // Search by Affiliate->user->user_email and Affiliate->user->user_login
        $args = array(
            'search'         => "*$search*",
            'search_columns' => array('user_login', 'user_email')
        );
        $user_query = new WP_User_Query($args);
        /** @var \WP_User[] */
        $user_results = $user_query->get_results();
        $user_ids = array_map(function ($wp_user) {
            return $wp_user->ID;
        }, $user_results);

        if (!empty($user_ids)) {
            $results = Affiliate::where(['user_id' => ['operator' => 'IN', 'value' => $user_ids]]);
            $list = array_merge($list, $results);
        }

        // TODO Ensure no duplicates in results (search matches the id and user_email/user_login for the same affiliate)

        if ($return_ids) {
            $ids = array_map(
                function ($affiliate) {
                    return $affiliate->id;
                },
                $list
            );

            return $ids;
        } else {
            return $list;
        }
    }

    /**
     * Returns the number of total Visits for an Affiliate.
     *
     * @param int $affiliate_id
     *
     * @return int
     */
    public static function total_visit_count($affiliate_id)
    {

        return Visit::count(['affiliate_id' => $affiliate_id]);
    }

    /**
     * Returns the number of total Referrals for an Affiliate.
     *
     * @param int $affiliate_id
     *
     * @return int
     */
    public static function total_referral_count($affiliate_id)
    {
        return Referral::count(['affiliate_id' => $affiliate_id]);
    }

    /**
     * Returns the total commission amount. Does not include Rejected or Draft referrals.
     *
     * @param int $affiliate_id
     *
     * @return float
     */
    public static function total_commission_amount($affiliate_id)
    {
        /**
         * @var Referral[] $query_results
         * @psalm-suppress UndefinedDocblockClass
         */
        $query_results = Referral::builder()
            ->select('SUM(commission_amount) as total_commission_amount')
            // having status paid or unpaid
            ->where([
                'affiliate_id' => $affiliate_id,
                'status' => ['operator' => 'IN', 'value' => [Referral::STATUS_PAID, Referral::STATUS_UNPAID]]
            ])
            ->order_by('total_commission_amount', 'DESC')
            ->get();


        /**
         * @psalm-suppress UndefinedMagicPropertyFetch
         */
        return (float)$query_results[0]->total_commission_amount;
    }

    /**
     * Determines whether or not an affiliate can earn a referral.
     *
     * @param self $affiliate
     * @return boolean
     */
    public static function can_earn_referral($affiliate)
    {
        return $affiliate->status === self::APPROVED_STATUS;
    }

    /**
     * Returns the first WC coupon associated to the Affiliate if there is one.
     *
     * @param Affiliate $affiliate
     *
     * @return \WC_Coupon|null
     */
    public static function maybe_default_coupon($affiliate)
    {
        $query_args = array(
            'post_type' => WooCommerceIntegration::COUPON_POST_TYPE,
            'meta_query' => array(
                array(
                    'key' => WooCommerceIntegration::MISC['coupon_affiliate_id_key'],
                    'value' => $affiliate->id
                )
            )
        );
        $query = new \WP_Query($query_args);
        $posts = $query->posts;

        if (empty($posts)) {
            return null;
        } else {
            $post = $posts[0];

            if ($post instanceof \WP_Post) {
                return new \WC_Coupon($post->ID);
            } else {
                return new \WC_Coupon($post);
            }
        }
    }

    /**
     * @return self|null
     */
    public static function current()
    {
        return self::find_where(['user_id' => get_current_user_id()]);
    }
}
