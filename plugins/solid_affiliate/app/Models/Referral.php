<?php

namespace SolidAffiliate\Models;

use Exception;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\Integrations\WooCommerceSubscriptionsIntegration;
use SolidAffiliate\Lib\ListTables\ReferralsListTable;
use SolidAffiliate\Lib\MikesDataModel;
use SolidAffiliate\Lib\MikesDataModelTrait;
use SolidAffiliate\Lib\RandomData;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Lib\VO\DatabaseTableOptions;
use SolidAffiliate\Lib\VO\ItemCommission;
use SolidAffiliate\Views\Admin\Referrals\ItemCommissionsModalAndIconView;

/**
 * @property array $attributes
 *
 * @property int $id
 * @property int $affiliate_id
 * @property int $visit_id
 * @property int $coupon_id
 * @property int $customer_id
 * @property int $payout_id
 * @property Referral::STATUS_* $status
 * @property float $commission_amount
 * @property string $description
 * @property float $order_amount
 * @property int $order_id
 * @property WooCommerceIntegration::SOURCE|WooCommerceSubscriptionsIntegration::SOURCE $order_source
 * @property self::SOURCE_* $referral_source
 * @property self::TYPE_* $referral_type
 * @property string $order_refunded_at
 * @property string $created_at
 * @property string $updated_at
 * @property string $serialized_item_commissions
 */
class Referral extends MikesDataModel
{
    use MikesDataModelTrait;

    /**
     * Data table name in database (without prefix).
     * @var string
     */
    const TABLE = "solid_affiliate_" . "referrals";
    const PRIMARY_KEY = 'id';

    const SOURCE_VISIT = 'visit';
    const SOURCE_COUPON = 'coupon';
    const SOURCE_AUTO_REFERRAL = 'auto_referral';
    const SOURCE_AFFILIATE_CUSTOMER_LINK = 'affiliate_customer_link';
    const SOURCE_SUBSCRIPTION_RENEWAL = 'subscription_renewal';
    const SOURCE_MANUAL = 'manual';

    ///////////////////////////////
    // TODO - these shouldn't exist
    const TYPE_PURCHASE = 'purchase';
    const TYPE_EVENT = 'event';
    const TYPE_SUBSCRIPTION_RENEWAL = 'subscription_renewal';
    const TYPE_AUTO_REFERRAL = 'auto_referral';
    // TODO - these shouldn't exist
    ///////////////////////////////

    const STATUS_PAID = 'paid';
    const STATUS_UNPAID = 'unpaid';
    const STATUS_REJECTED = 'rejected';
    const STATUS_DRAFT = 'draft';

    const STATUSES_PAID_AND_UNPAID = [self::STATUS_PAID, self::STATUS_UNPAID];
    const STATUSES_ALL_BESIDES_REJECTED = [self::STATUS_PAID, self::STATUS_UNPAID, self::STATUS_DRAFT];

    /**
     * The Model name, used to identify it in Filters.
     *
     * @since TBD
     *
     * @var string
     */
    const MODEL_NAME = 'Referral';

    /**
     * Used to represent the URL key on WP Admin
     * 
     * @var string
     */
    const ADMIN_PAGE_KEY = 'solid-affiliate-referrals';

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
     * @var Schema<"affiliate_id"|"commission_amount"|"coupon_id"|"order_refunded_at"|"created_at"|"customer_id"|"description"|"id"|"order_amount"|"order_id"|"order_source"|"payout_id"|"referral_source"|"referral_type"|"status"|"updated_at"|"visit_id"|"serialized_item_commissions"|"manually_created_by_admin_user_id"|"affiliate_customer_link_id">|null
     */
    private static $schema_cache = null;

    /**
     * @return Schema<"affiliate_id"|"commission_amount"|"coupon_id"|"order_refunded_at"|"created_at"|"customer_id"|"description"|"id"|"order_amount"|"order_id"|"order_source"|"payout_id"|"referral_source"|"referral_type"|"status"|"updated_at"|"visit_id"|"serialized_item_commissions"|"manually_created_by_admin_user_id"|"affiliate_customer_link_id">
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
                'form_input_description' => __("The ID of the Affiliate who earned this Referral.", 'solid-affiliate'),
                'form_input_type_override' => 'affiliate_select',
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'key' => true,
                'is_csv_exportable' => true
            ]),
            'order_amount' => new SchemaEntry([
                'type' => 'float',
                'display_name' => __('Order Amount', 'solid-affiliate'),
                'form_input_description' => __("The original Order Amount associated with this Referral, at the time of Referral creation.", 'solid-affiliate'),
                'required' => true,
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'is_zero_value_allowed' => true,
                'is_csv_exportable' => true,
                'csv_export_callback' =>
                /** @param float $amount */
                static function ($amount) {
                    return Formatters::raw_money_str($amount);
                }
            ]),
            'commission_amount' => new SchemaEntry([
                'type' => 'float',
                'required' => true,
                'display_name' => __('Commission Amount', 'solid-affiliate'),
                'form_input_description' => __("The Commission Amount earned by the Affiliate for this Referral.", 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'is_zero_value_allowed' => true,
                'is_csv_exportable' => true,
                'csv_export_callback' =>
                /** @param float $amount */
                static function ($amount) {
                    return Formatters::raw_money_str($amount);
                }
            ]),
            'referral_source' => new SchemaEntry([
                'type' => 'varchar',
                'length' => 255,
                'required' => true,
                'is_enum' => true,
                'user_default' => self::SOURCE_MANUAL,
                'enum_options' => [
                    [self::SOURCE_MANUAL, __('Manual', 'solid-affiliate')],
                    [self::SOURCE_VISIT, __('Visit', 'solid-affiliate')],
                    [self::SOURCE_COUPON, __('Coupon', 'solid-affiliate')],
                    [self::SOURCE_AUTO_REFERRAL, __('Auto Referral', 'solid-affiliate')],
                    [self::SOURCE_AFFILIATE_CUSTOMER_LINK, __('Affiliate-Customer Link', 'solid-affiliate')],
                    [self::SOURCE_SUBSCRIPTION_RENEWAL, __('Subscription Renewal', 'solid-affiliate')],
                ],
                'display_name' => __('Referral Source', 'solid-affiliate'),
                'form_input_description' => __("Where this Referral originated.", 'solid-affiliate'),
                'show_on_new_form' => 'hidden_and_disabled',
                'show_on_edit_form' => 'hidden_and_disabled',
                'show_list_table_column' => true,
                'is_csv_exportable' => true
            ]),
            'visit_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => false,
                'default' => 0,
                'display_name' => __('Visit ID', 'solid-affiliate'),
                'form_input_description' => __("The ID of the Visit which resulted to this Referral, if applicable.", 'solid-affiliate'),
                'show_on_new_form' => false,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'is_csv_exportable' => true
            ]),
            'coupon_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => false,
                'default' => 0,
                'display_name' => __('Coupon ID', 'solid-affiliate'),
                'form_input_type_override' => 'woocommerce_coupon_select',
                'form_input_description' => __("The ID of the Coupon which resulted to this Referral, if applicable.", 'solid-affiliate'),
                'show_on_new_form' => false,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'is_csv_exportable' => true
            ]),
            'customer_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => false,
                'default' => 0,
                'display_name' => __('Customer ID', 'solid-affiliate'),
                'form_input_description' => __("The ID of the Customer associated with this Referral.", 'solid-affiliate'),
                'show_on_new_form' => false,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'key' => true,
                'is_csv_exportable' => true
            ]),
            'referral_type' => new SchemaEntry([
                'type' => 'varchar',
                'length' => 255,
                'required' => true,
                'display_name' => __('Referral Type', 'solid-affiliate'),
                'form_input_description' => __("The type of Referral, either a Purchase or Subscription Renewal. (Coming soon: Custom Referral Events).", 'solid-affiliate'),
                'is_enum' => true,
                'user_default' => self::TYPE_PURCHASE,
                'enum_options' => [
                    [self::TYPE_PURCHASE, __('Purchase', 'solid-affiliate')],
                    [self::TYPE_SUBSCRIPTION_RENEWAL, __('Subscription Renewal', 'solid-affiliate')],
                    [self::TYPE_AUTO_REFERRAL, __('Auto Referral', 'solid-affiliate')]
                    // ['event', 'Event']
                ],
                'show_on_new_form' => 'hidden',
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'is_csv_exportable' => true
            ]),
            'description' => new SchemaEntry([
                'type' => 'text',
                'display_name' => __('Description', 'solid-affiliate'),
                'form_input_description' => __("A description of the Referral.", 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'is_csv_exportable' => true
            ]),
            'order_source' => new SchemaEntry([
                'type' => 'varchar',
                'length' => 255,
                'is_enum' => true,
                'enum_options' => [
                    [WooCommerceIntegration::SOURCE, 'WooCommerce'],
                    [WooCommerceSubscriptionsIntegration::SOURCE, 'WooCommerce Subscriptions']
                ],
                'display_name' => __('Order Source', 'solid-affiliate'),
                'form_input_description' => __("Where the Order for this Referral originated.", 'solid-affiliate'),
                'required' => true,
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'is_csv_exportable' => true
            ]),
            'order_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => true,
                'display_name' => __('Order ID', 'solid-affiliate'),
                'form_input_description' => __("The ID of the Order associated with this Referral. Currently WooCommerce Orders are supported.", 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'key' => true,
                'is_csv_exportable' => true
            ]),
            'order_refunded_at' => new SchemaEntry([
                'type' => 'datetime',
                'default' => null,
                'user_default' => null,
                'display_name' => __('Order Refunded On', 'solid-affiliate'),
                'show_list_table_column' => false,
                'show_on_new_form' => false,
                'show_on_edit_form' => 'hidden',
                'form_input_description' => __("The date of the refund of the associated Order, if applicable.", 'solid-affiliate'),
                'key' => true,
                'nullable' => true,
                'is_csv_exportable' => true
            ]),
            'created_at' => new SchemaEntry([
                'type' => 'datetime',
                'display_name' => __('Created', 'solid-affiliate'),
                'show_list_table_column' => true,
                'show_on_new_form' => false,
                'show_on_edit_form' => 'hidden_and_disabled',
                'form_input_description' => __("The date of the Referral.", 'solid-affiliate'),
                'key' => true,
                'is_csv_exportable' => true
            ]),
            'updated_at' => new SchemaEntry([
                'type' => 'datetime',
                'display_name' => __('Updated', 'solid-affiliate'),
                'is_csv_exportable' => true
            ]),
            'payout_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => false,
                'default' => 0,
                'display_name' => __('Payout ID', 'solid-affiliate'),
                'form_input_description' => __("The ID of the Payout associated with this Referral, if one exists.", 'solid-affiliate'),
                'show_on_new_form' => false,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'key' => true,
                'is_csv_exportable' => true
            ]),
            'status' => new SchemaEntry([
                'type' => 'varchar',
                'length' => 255,
                'required' => true,
                'default' => self::STATUS_UNPAID,
                'is_enum' => true,
                'enum_options' => self::status_enum_options(),
                'display_name' => __('Status', 'solid-affiliate'),
                'form_input_description' => __("The current Status of this Referral.", 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_edit_form' => true,
                'show_list_table_column' => true,
                'key' => true,
                'is_csv_exportable' => true
            ]),
            'serialized_item_commissions' => new SchemaEntry([
                'type' => 'text',
                'display_name' => __('Serialized Item Commissions', 'solid-affiliate'),
                'form_input_description' => __("Serialized Item Commissions", 'solid-affiliate'),
                'show_on_new_form' => false,
                'show_on_edit_form' => false,
                'show_list_table_column' => false,
                'user_default' => serialize(array())
            ]),
            'manually_created_by_admin_user_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => false,
                'default' => 0,
                'display_name' => __('Manually created by User ID', 'solid-affiliate'),
                'form_input_description' => __("The ID of the user whom manually created this referral, if applicable.", 'solid-affiliate'),
                'show_on_new_form' => false,
                'show_on_edit_form' => 'hidden_and_disabled',
                'show_list_table_column' => false,
                'key' => true,
                'is_csv_exportable' => true
            ]),
            'affiliate_customer_link_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => false,
                'default' => 0,
                'display_name' => __('Affiliate-Customer Link ID', 'solid-affiliate'),
                'form_input_description' => __("The ID of the affiliate_customer_link record, if applicable. For Lifetime Commissions.", 'solid-affiliate'),
                'show_on_new_form' => false,
                'show_on_edit_form' => false,
                'show_list_table_column' => false,
                'key' => true,
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
            'singular'      => 'Referral',
            'plural'        => 'Referrals',
            'show_ui'       => false,        // Make custom table visible on admin area (check 'views' parameter)
            'show_in_rest'  => false,        // Make custom table visible on rest API
            'version'       => 19,           // Change the version on schema changes to run the schema auto-updater
            'primary_key' => self::PRIMARY_KEY,    // If not defined will be checked on the field that hsa primary_key as true on schema
            'schema'        => self::schema()
        ));
    }

    /**
     * @param bool $formatted
     * @return array{array{"unpaid", string}, array{"paid", string}, array{"rejected", string}, array{"draft", string}}
     */
    public static function status_enum_options($formatted = false)
    {
        $options = [
            [self::STATUS_UNPAID, __('Unpaid', 'solid-affiliate')],
            [self::STATUS_PAID, __('Paid', 'solid-affiliate')],
            [self::STATUS_REJECTED, __('Rejected', 'solid-affiliate')],
            [self::STATUS_DRAFT, __('Draft', 'solid-affiliate')],
        ];

        if ($formatted) {
            $formatted_options = array_map(function ($option) {
                return [
                    $option[0],
                    Formatters::status_with_tooltip($option[0], Referral::class, 'admin')
                ];
            }, $options);

            /** @var array{array{"unpaid", string}, array{"paid", string}, array{"rejected", string}, array{"draft", string}} */
            return $formatted_options;
        } else {
            return $options;
        }
    }

    /**
     * @return string[]
     *
     * @psalm-return array<array-key, string>
     */
    public static function required_fields()
    {
        $schema = Referral::schema();
        $required_fields = SchemaFunctions::required_fields_from_schema($schema);

        return $required_fields;
    }
    /**
     * Model properties, data column list.
     * @var string[]
     */
    protected $attributes = [
        self::PRIMARY_KEY,
        'affiliate_id',
        'visit_id',
        'customer_id',
        'payout_id',
        'amount',
        'referral_type',
        'description',
        'status',
        'order_amount',
        'order_source',
        'order_refunded_at',
        'created_at',
        'updated_at'
    ];

    /**
     * @return ReferralsListTable
     */
    public static function admin_list_table()
    {
        return new ReferralsListTable();
    }


    /**
     * Update status to rejected status unless it's in a state where that doesn't make sense.
     *
     * @param self $referral
     * @return bool
     */
    public static function reject_unless_already_paid($referral)
    {
        if (!Settings::get(Settings::KEY_IS_REJECT_UNPAID_REFERRALS_ON_REFUND)) {
            return false;
        }

        if ($referral->status != Referral::STATUS_PAID) {
            $either_referral =  Referral::updateInstance(
                $referral,
                ['status' => Referral::STATUS_REJECTED]
            );

            if ($either_referral->isLeft) {
                return false;
            } else {
                return true;
            }
        }
        return false;
    }


    /**
     * Undocumented function
     *
     * @param string $start
     * @param string $end
     *
     * @return float amount unpaid
     */
    public static function total_owed_for_date_range($start, $end)
    {

        $unpaid_referrals = Referral::find_unpaid_for_date_range($start, $end);

        return self::sum_commission_amount($unpaid_referrals);
    }

    /**
     * Sums the commissions for an array of Referrals
     *
     * @param Referral[] $referrals
     * @return float
     */
    public static function sum_commission_amount($referrals)
    {
        return array_reduce(
            $referrals,
            /**
             * @param float $total
             * @param \SolidAffiliate\Models\Referral $referral
             *
             * @return float
             **/
            function ($total, $referral) {
                return $total + (float)$referral->commission_amount;
            },
            0.0
        );
    }

    /**
     * Sums the commissions for an array of Referrals
     *
     * @param Referral[] $referrals
     * @return float
     */
    public static function sum_order_amount($referrals)
    {
        return array_reduce(
            $referrals,
            /**
             * @param float $total
             * @param \SolidAffiliate\Models\Referral $referral
             *
             * @return float
             **/
            function ($total, $referral) {
                return $total + (float)$referral->order_amount;
            },
            0.0
        );
    }

    /**
     * Undocumented function
     *
     * @param string $start
     * @param string $end
     *
     * @return self[]
     */
    public static function find_unpaid_for_date_range($start, $end)
    {
        if (empty($start)) {
            $start = '1000 years ago';
        }

        if (empty($end)) {
            $end = 'now';
        }

        $start_date = new \DateTime($start);
        $end_date = new \DateTime($end);

        $unpaid_referrals = Referral::where([
            'created_at' => [
                'operator' => 'BETWEEN',
                'min' => $start_date->format('Y-m-d'),
                'max' => $end_date->format('Y-m-d'),
            ],
            'status' => self::STATUS_UNPAID
        ]);


        return $unpaid_referrals;
    }


    /**
     * Gets the Admin Order URL for a Referral. Will check order_source (WooCommerce/EDD)
     * and attempt to fail gracefully.
     *
     * @param int $referral_id
     * @return Either<string> The full URL.
     */
    public static function get_admin_order_url($referral_id)
    {
        $referral_or_null = self::find($referral_id);

        if (is_null($referral_or_null)) {
            return new Either([__("No Referral found for ID {$referral_id}", 'solid-affiliate')], '', false);
        } else {
            $order_id = (int)$referral_or_null->order_id;
            $order_source = (string)$referral_or_null->order_source;
            switch ($order_source) {
                case WooCommerceIntegration::SOURCE:
                    $url = WooCommerceIntegration::get_admin_order_url($order_id);
                    return new Either([], $url, true);
                case WooCommerceSubscriptionsIntegration::SOURCE:
                    $url = WooCommerceIntegration::get_admin_order_url($order_id);
                    return new Either([], $url, true);
                default:
                    /**
                     * @psalm-suppress InvalidCast
                     */
                    return new Either([__("Invalid Order Source: {$order_source}", 'solid-affiliate')], '', false);
            }
        }
    }

    /**
     * Gets the Admin Order URL for a Referral. Will check order_source (WooCommerce/EDD)
     * and attempt to fail gracefully.
     *
     * @param int $referral_id
     * @return Either<string> The full note.
     */
    public static function add_order_completed_note($referral_id)
    {
        $referral_or_null = self::find($referral_id);

        if (is_null($referral_or_null)) {
            return new Either([__("No Referral found for ID {$referral_id}"), 'solid-affiliate'], '-', false);
        } else {
            $order_id = (int)$referral_or_null->order_id;
            $order_source = (string)$referral_or_null->order_source;
            switch ($order_source) {
                case WooCommerceIntegration::SOURCE:
                case WooCommerceSubscriptionsIntegration::SOURCE:
                    $note = '<strong>Solid Affiliate</strong> </br> ' . self::order_note_body($referral_id);
                    WooCommerceIntegration::add_order_note($order_id, $note);
                    /** @var Either<string> */
                    return new Either([], $note, true);
                default:
                    /**
                     * @psalm-suppress InvalidCast
                     */
                    return new Either([__("Invalid Order Source: {$order_source}", 'solid-affiliate')], '-', false);
            }
        }
    }

    /**
     * Returns an order note body.
     *
     * @param int $referral_id
     * @return string
     */
    public static function order_note_body($referral_id)
    {
        $referral_or_null = self::find($referral_id);

        if (is_null($referral_or_null)) {
            return "No referral found.";
        } else {
            $referral_url = URLs::edit(Referral::class, $referral_id);
            $referral_link = "<a href='{$referral_url}'>" . __('View Referral')  . " #{$referral_id}</a>";

            $affiliate = Affiliate::find($referral_or_null->affiliate_id);
            if (is_null($affiliate)) {
                $affiliate_link = '-';
            } else {
                $user = get_userdata($affiliate->user_id);
                $username = $user ? $user->user_nicename : __('User Not Found', 'solid-affiliate');

                $affiliate_url = URLs::edit(Affiliate::class, $affiliate->id);
                $affiliate_link = "<a href='{$affiliate_url}'>" . " {$username}</a>";
            }

            $commission_amount = $referral_or_null->commission_amount;

            $note = __('This order was referred by', 'solid-affiliate') . ' ' . $affiliate_link . ' ' . __('earning a commission of', 'solid-affiliate') . ' ' . Formatters::money($commission_amount) . ' </br> ' . $referral_link;
            return $note;
        }
    }

    /**
     * Very WIP, used in a couple tests currently.
     * 
     * Goal: Creates and returns a new Record with random data.
     * 
     * @param array{affiliate_id: int, visit_id: int, coupon_id: int} $required_args
     * @param array{payout_id: int, created_at?: string}|null $optional_args
     *
     * @return self
     */
    public static function random($required_args, $optional_args = null)
    {
        $random_commission_amount = RandomData::float();
        $status = (isset($optional_args['payout_id']) && ($optional_args['payout_id'] > 0)) ? 'paid' : 'unpaid';
        $random_args = [
            'referral_source' => self::SOURCE_VISIT,
            'customer_id' => 0,
            'commission_amount' => $random_commission_amount,
            'referral_type' => self::TYPE_PURCHASE,
            'description' => 'Random description of product.',
            'status' => $status,
            'order_amount' => $random_commission_amount * 5.0,
            'order_source' => 'RandomData.php',
            'order_id' => 1,
            'created_at' => RandomData::date(),
            'serialized_item_commissions' => serialize([]),
            'manually_created_by_admin_user_id' => 0,
            'affiliate_customer_link_id' => 0,
        ];
        if (is_null($optional_args)) {
            $final_args = array_merge($random_args, $required_args);
        } else {
            $final_args = array_merge($random_args, $required_args, $optional_args);
        }

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
     * Undocumented function
     *
     * @param Referral $referral
     * 
     * @return string
     */
    public static function render_commission_tooltip($referral)
    {
        $formatted_commission = Formatters::money($referral->commission_amount);
        $item_commissions = self::get_item_commissions($referral);

        if (empty($item_commissions)) {
            return $formatted_commission;
        } else {
            return $formatted_commission . ItemCommissionsModalAndIconView::render($item_commissions, (int)$referral->id, (float)$referral->commission_amount);
        }
    }


    /**
     * Gets the Product IDs associated with a Referral.
     *
     * @param Referral $referral
     * 
     * @return int[]
     */
    public static function get_product_ids($referral)
    {
        $item_commissions = self::get_item_commissions($referral);

        return array_map(function ($item_commission) {
            return $item_commission->product_id;
        }, $item_commissions);
    }

    /**
     * Gets the Item Commissions associated with a Referral.
     *
     * @param Referral $referral
     * 
     * @return ItemCommission[]
     */
    public static function get_item_commissions($referral)
    {
        $maybe_serialized_item_commissions = (string)$referral->serialized_item_commissions;
        if (!empty($maybe_serialized_item_commissions)) {
            try {
                $item_commissions = Validators::arr_of_item_commission(
                    unserialize($maybe_serialized_item_commissions)
                );

                return $item_commissions;
            } catch (\Exception $e) {
                return [];
            }
        } else {
            return [];
        }
    }

    /**
     * Queries the referrals table for the count of referrals that are associated to the given $visit_ids for an Affiliate.
     *
     * @global \wpdb $wpdb
     *
     * @param int $affiliate_id
     * @param array<int> $visit_ids
     *
     * @return int
     */
    public static function referral_count_for_affiliate_visits($affiliate_id, $visit_ids = [])
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        if (empty($visit_ids)) {
            # NOTE: Having a query that looks for IDs IN (), when the IN is empty, throws a MySQL syntax error according to the Query Monitor plugin
            $in_ids_string = '(0)';
        } else {
            $in_ids_string = '(' . implode(',', $visit_ids) . ')';
        }
        $count = $wpdb->get_var(
            Validators::str($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE affiliate_id = {$affiliate_id} AND referral_source = %s AND visit_id IN " . $in_ids_string,
                self::SOURCE_VISIT
            ))
        );

        return (int)$count;
    }
}
