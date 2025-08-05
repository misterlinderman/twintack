<?php

namespace SolidAffiliate\Lib\ListTables;

use SolidAffiliate\Controllers\AdminReportsController;
use SolidAffiliate\Lib\AdminReportsHelper;
use SolidAffiliate\Lib\AffiliatePortalFunctions;
use SolidAffiliate\Lib\AffiliateRegistrationForm\AffiliateRegistrationFormFunctions;
use SolidAffiliate\Lib\FF;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Visit;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\ListTables\SharedListTableFunctions;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\Tags;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\ListTableConfigs;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Models\AffiliateGroup;
use SolidAffiliate\Views\Shared\SolidTooltipView;

class AffiliatesListTable extends SolidWPListTable
{
    /** @var ListTableConfigs */
    public $configs;

    /**
     * @return ListTableConfigs
     */
    public static function build_configs()
    {
        $configs = [
            'model_class' => Affiliate::class,
            'singular' => Affiliate::ct_table_options()->data['singular'],
            'plural' => Affiliate::ct_table_options()->data['plural'],
            'schema' => Affiliate::schema_with_custom_registration_data(),
            'page_key' => Affiliate::ADMIN_PAGE_KEY,
            'bulk_actions' => array(
                'Approve'  => __('Approve', 'solid-affiliate'),
                'Reject'  => __('Reject', 'solid-affiliate'),
                'Delete'  => __('Delete', 'solid-affiliate'),
            ),
            "search_by_key" => "payment_email",
            "search_button_text_override" => __('Search affiliates', 'solid-affiliate'),
            "search_function" =>
            /**
             * @param array $args
             * @param array<string> $search_args
             * @param array<array<string>> $filter_args
             * @param array<int> $where_ids
             * 
             * @return array<object>
             */
            function ($args, $search_args, $filter_args, $where_ids) {
                return Affiliate::search($args, $search_args, $filter_args, $where_ids);
            },
            "count_function" =>
            /** @return int */
            function () {
                return Affiliate::count();
            },
            'hidden_columns_by_default' => [
                'payment_email',
                'created_at',
                'first_name',
                'last_name',
                'mailchimp_user_id',
                'Visits',
                'paid_commission',
                'unpaid_commission',
                'affiliate_group_id'
            ],
            "computed_columns" => [
                [
                    'column_name' => __('Visits', 'solid-affiliate'),
                    'function' =>
                    /** 
                     * @param Affiliate $item 
                     * @return string
                     **/
                    function ($item) {
                        $count = Affiliate::total_visit_count($item->id);
                        if (empty($count)) {
                            return "0";
                        } else {
                            $path = URLs::index(Visit::class, true);
                            $path = add_query_arg(['affiliate_id' => $item->id], $path);
                            return "<a href='{$path}'>{$count}</a>";
                        }
                    }
                ],
                [
                    'column_name' => __('Referrals', 'solid-affiliate'),
                    'function' =>
                    /** 
                     * @param Affiliate $item 
                     * @return string
                     **/
                    function ($item) {
                        $count = Affiliate::total_referral_count($item->id);
                        if (empty($count)) {
                            return "0";
                        } else {
                            $path = URLs::index(Referral::class, true);
                            $path = add_query_arg(['affiliate_id' => $item->id], $path);
                            return "<a href='{$path}'>{$count}</a>";
                        }
                    }
                ],
                [
                    'column_name' => 'net_revenue',
                    'function' =>
                    /** 
                     * @param Affiliate $item 
                     * @return string
                     **/
                    function ($item) {
                        $dates = new PresetDateRangeParams(['preset_date_range' => 'all_time']);
                        $data = AdminReportsHelper::referrals_data($dates->computed_start_date(), $dates->computed_end_date(), $item->id, Referral::STATUSES_PAID_AND_UNPAID);

                        $total_referral_amount = $data['Referral Amount'];
                        $total_commission_amount = $data['Commission Amount'];
                        $total_profit_amount = $data['Net Revenue Amount'];

                        $out = "
                        <strong>" . __('Sales', 'solid-affiliate') . ":</strong>      <span class='sld-float-right'>{$total_referral_amount}</span>
                        <br>
                        <strong>" . __('Commission', 'solid-affiliate') . ": </strong><span class='sld-float-right'>({$total_commission_amount})</span>
                        <br>
                        <hr>
                        <strong>" . __('Net Revenue', 'solid-affiliate') . ": </strong><span class='sld-float-right'><strong>{$total_profit_amount}</strong></span>
                        ";

                        return $out;
                    }
                ],

                [
                    'column_name' => 'paid_commission',
                    'function' =>
                    /** 
                     * @param Affiliate $item 
                     * @return string
                     **/
                    function ($item) {
                        return Formatters::money(Affiliate::paid_earnings($item));
                    }
                ],
                [
                    'column_name' => 'unpaid_commission',
                    'function' =>
                    /** 
                     * @param Affiliate $item 
                     * @return string
                     **/
                    function ($item) {
                        return Formatters::money(Affiliate::unpaid_earnings($item));
                    }
                ],
                [
                    'column_name' => 'coupons',
                    'function' =>
                    /** 
                     * @param Affiliate $item 
                     * @return string
                     **/
                    function ($item) {
                        $coupon_data = AffiliatePortalFunctions::coupon_data_for_affiliate_id($item->id);
                        if (empty($coupon_data)) {
                            return 'No assigned coupons';
                        }

                        $portal_link =  URLs::admin_portal_preview_path($item->id); // Replace with actual link
                        $portal_link = add_query_arg(['tab' => 'coupons'], $portal_link);
                        $coupon_count = count($coupon_data);
                        $out = '';

                        $out .= "<div class='sld-coupon-pills'>";

                        foreach (array_slice($coupon_data, 0, 3) as $coupon_datum) {
                            $out .= "<div class='sld-coupon-pill'>";
                            $out .= "<div style='font-size:13px'>Code: <span style='float:right'>{$coupon_datum[0]}</span></div>";
                            $out .= "<div>Discount amount: <span style='float:right'>{$coupon_datum[1]}</span></div>";
                            $out .= "<div>Discount type: <span style='float:right'>{$coupon_datum[2]}</span></div>";
                            $out .= "<div>Referrals: <span style='float:right'>{$coupon_datum[3]}</span></div>";
                            $out .= "</div>";
                        }

                        if ($coupon_count > 3) {
                            $out .= "<a style='font-size:13px' href='{$portal_link}'>View all coupons →</a>";
                        }

                        $out .= "</div>";


                        return $out;
                    }
                ],
                [
                    'column_name' => 'tags',
                    'function' =>
                    /** 
                     * @param Affiliate $item 
                     * @return string
                     **/
                    function ($item) {
                        if (FF::is_off('affiliate_tags')) {
                            return FF::maybe_pill('affiliate_tags');
                        }

                        $tags = Tags::tags_for_affiliate_id($item->id);

                        if (empty($tags)) {
                            return 'No tags';
                        }

                        $out = '';
                        $link_to_manage_all_tags = URLs::admin_path(AffiliateGroup::ADMIN_PAGE_KEY, false, ['tab' => 'tags']);
                        ob_start();
                        foreach ($tags as $tag) {
?>
                <div class='affiliate-tag' style="display:flex; flex-direction:row; gap:5px; align-items:center; font-weight:400; font-size:12px;">
                    <div style='width:10px; height:10px; border-radius:10px; display:inline-block; border:1px solid var(--sld-border); background: <?= $tag['color'] ?>'
                        class="sld-tooltip" data-html="true"
                        data-sld-tooltip-content="
                                    <div style='max-width:200px; font-weight:400; padding:10px; display: flex; flex-direction: column;'>
                                        <p style=' font-size:12px; margin:0' ><strong style='font-weight:600'>Tag:</strong> <?= $tag['name'] ?></p>
                                        <p  style=' font-size:12px; margin:0' ><strong style='font-weight:600'>Description:</strong> <?= $tag['description'] ?><p>
                                        <div style='margin:0; font-size:10px; line-height:14px;'>Here you can manage this affiliate's tags. If you want to manage all tags, please go to the <a href='<?= $link_to_manage_all_tags ?>' target='_blank'>tag management tab</a>.</div>
                                    </div>
                                "
                        aria-expanded="false"></div>
                    </svg>
                    <?php echo $tag['name'] ?>
                    <div><?php echo $tag['description'] ?></div>
                </div>
<?php
                        }
                        $out = ob_get_clean();

                        return $out;
                    }
                ]
            ],
            'column_name_overrides' => [
                'id' => __('Affiliate', 'solid-affiliate'),
                'net_revenue' => __('Net Revenue', 'solid-affiliate'),
                'paid_commission' => __('Paid Commission', 'solid-affiliate'),
                'unpaid_commission' => __('Unpaid Commission', 'solid-affiliate'),
                'affiliate_group_id' => __('Affiliate Group', 'solid-affiliate'),
            ],
            "custom_css" => "
                .column-net_revenue { width: 200px; }
                .sld-float-right { float: right; padding-right: 20px; }
            ",
        ];

        $original_list_table_configs = new ListTableConfigs($configs);

        /**
         * @psalm-suppress MixedAssignment
         */
        $list_table_configs = apply_filters("solid_affiliate/admin_list_table_configs/Affiliate", $original_list_table_configs);

        if ($list_table_configs instanceof ListTableConfigs) {
            return $list_table_configs;
        } else {
            return $original_list_table_configs;
        }
    }


    function __construct()
    {
        // These are to stop PSALM from complaining about "property ... is not defined in constructor"
        $this->items = [];
        $this->_args = [];
        /** @psalm-suppress PropertyTypeCoercion */
        $this->screen = (object)[];
        $this->_column_headers = [];
        $this->configs = self::build_configs();



        parent::__construct(array(
            'singular' => $this->configs->singular,
            'plural' => $this->configs->plural,
            'ajax' => true,
        ));
    }

    /**
     * @return ListTableConfigs
     */
    public function configs()
    {
        return $this->configs;
    }

    /**
     * @return void
     */
    public static function add_screen_options()
    {
        new AffiliatesListTable;
    }

    function get_table_classes()
    {
        return array('widefat', 'fixed', 'striped', (string)$this->_args['plural']);
    }

    /**
     * Message to show if no designation found
     *
     * @return void
     */
    function no_items()
    {
        $plural = $this->configs->plural;

        echo sprintf(__('No %s found', 'solid-affiliate'), $plural);
    }

    /**
     * Default column values if no callback found
     *
     * @param  object  $item
     * @param  string  $column_name
     *
     * @return string
     */
    function column_default($item, $column_name)
    {
        // handle computed_columns
        $computed_column_structs = $this->configs->computed_columns;
        foreach ($computed_column_structs as $struct) {
            if ($struct['column_name'] != $column_name) {
                continue;
            } else {
                /** @var Affiliate $item */
                return (string)$struct['function']($item);
            }
        }

        if (isset($item->$column_name)) {
            return Validators::str($item->$column_name);
        } else {
            if (isset($item->custom_registration_data)) {
                $json_str = Validators::str($item->custom_registration_data);
                $data_arr = AffiliateRegistrationFormFunctions::decode_json_string($json_str);
                if (is_null($data_arr)) {
                    return '';
                } else {
                    return Formatters::custom_data_for_view($data_arr, $column_name);
                }
            } else {
                return '';
            }
        }
    }

    /**
     * Get the column names
     *
     * @return array<string, string>
     */
    function get_columns()
    {
        return SharedListTableFunctions::shared_get_columns($this->configs);
    }

    /**
     * Render the designation name column
     *
     * @param  object  $item
     *
     * @return string
     */
    function column_id($item)
    {
        $affiliate_id = (int)$item->id;

        return SharedListTableFunctions::affiliate_column($affiliate_id);
    }

    /**
     * Get sortable columns
     *
     * @return array
     */
    function get_sortable_columns()
    {
        $schema = $this->configs->schema;
        $columns = SchemaFunctions::sortable_columns_for_list_table_from_schema($schema);

        // NOTE builds in this format
        // $sortable_columns = array(
        //     'id'              => array('id', true),
        //     'user_id'         => array('user_id', true),
        // );
        $column_keys = array_keys($columns);

        /** @var array|null $sortable_columns */
        $sortable_columns = array_reduce(
            $column_keys,
            /**
             * @param array $result
             * @param array-key $item
             */
            function ($result, $item) {
                $result[$item] = array($item, true);
                return $result;
            },
            array()
        );

        if (is_null($sortable_columns)) {
            return [];
        } else {
            return $sortable_columns;
        }
    }


    /**
     * Set the bulk actions
     *
     * @return array
     */
    function get_bulk_actions()
    {
        $actions = $this->configs->bulk_actions;
        return $actions;
    }

    /** 
     * Outputs the reporting views.
     *
     * @access public
     * @since  1.0
     *
     * @param string $which Optional. Whether the bulk actions are being displayed at
     *                      the top or bottom of the list table. Accepts either 'top'
     *                      or bottom. Default empty.
     * 
     * @return void
     */
    public function bulk_actions($which = '')
    {
        parent::bulk_actions($which);
        // Add Filters
        if ($which === 'bottom') {
            return;
        }
        echo SharedListTableFunctions::render_affiliate_group_id_filter();
    }


    /**
     * Render the checkbox column
     *
     * @param  object  $item
     *
     * @return string
     */
    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="id[]" value="%d" />',
            (int)$item->id
        );
    }

    /**
     * Undocumented function
     *
     * @param object $item
     * 
     * @return string
     */
    function column_commission_rate($item)
    {
        $commission_rate = (string)$item->commission_rate;
        /** @var 'site_default'|'flat'|'percentage' $commission_type */
        $commission_type = (string)$item->commission_type;

        if ($commission_type === 'site_default') {
            $affiliate_group_id = (int)$item->affiliate_group_id;
            if (empty($affiliate_group_id)) {
            } else {
                $maybe_group = AffiliateGroup::find($affiliate_group_id);
                if ($maybe_group instanceof AffiliateGroup) {
                    if ($maybe_group->commission_type == 'site_default') {
                        // $commission_rate = 'site_default';
                    }
                    return SharedListTableFunctions::commission_rate_column($maybe_group->commission_rate, $maybe_group->commission_type, 'Affiliate Group');
                } else {
                }
            }
        }

        return SharedListTableFunctions::commission_rate_column($commission_rate, $commission_type);
    }

    /**
     * @param object $item
     * 
     * @return string
     */
    function column_created_at($item)
    {
        return SharedListTableFunctions::created_at_column($item);
    }

    /**
     * @param object $item
     * 
     * @return string
     */
    function column_status($item)
    {
        $status = (string)$item->status;
        return Formatters::status_with_tooltip($status, Affiliate::class, 'admin');
    }

    /**
     * @param object $item
     * 
     * @return string
     */
    function column_registration_notes($item)
    {
        $registration_notes = (string)$item->registration_notes;
        $registration_notes = nl2br(esc_html(stripslashes($registration_notes)));
        if (empty($registration_notes)) {
            return '-';
        } else {
            $registration_notes = '<div>' . '<h4>' . __('How will you promote us?', 'solid-affiliate') . '</h4>' . $registration_notes  . '</div>';
            return __('Hover to read', 'solid-affiliate') . ': ' . SolidTooltipView::render($registration_notes);
        }
    }

    /**
     * @param object $item
     * 
     * @return string
     */
    function column_affiliate_group_id($item)
    {
        $affiliate_group_id = (int)$item->affiliate_group_id;
        if (empty($affiliate_group_id)) {
            return '-';
        } else {
            $maybe_group = AffiliateGroup::find($affiliate_group_id);
            if ($maybe_group instanceof AffiliateGroup) {
                return $maybe_group->name;
            } else {
                return '-';
            }
        }
    }

    /**
     * Set the views
     *
     * @return array
     */
    public function get_views()
    {
        $page_key = $this->configs->page_key;
        $base_link      = URLs::admin_path($page_key);

        $current        = isset($_GET['status']) ? (string)$_GET['status'] : '';

        $total_count    = '&nbsp;<span id="count-all" class="count">(' . Affiliate::count() . ')</span>';
        $approved_count   = '&nbsp;<span id="count-approved" class="count">(' . Affiliate::count(['status' => 'approved']) . ')</span>';
        $pending_count  = '&nbsp;<span id="count-pending" class="count">(' . Affiliate::count(['status' => 'pending']) . ')</span>';
        $rejected_count = '&nbsp;<span id="count-rejected" class="count">(' . Affiliate::count(['status' => Referral::STATUS_REJECTED]) . ')</span>';


        $views = array(
            'all'        => sprintf('<a id="filter-all" href="%s"%s>%s</a>', esc_url(remove_query_arg('status', $base_link)), $current === 'all' || $current == '' ? ' class="current"' : '', __('All', 'solid-affiliate') . $total_count),
            'approved'    => sprintf('<a id="filter-approved" href="%s"%s>%s</a>', esc_url(add_query_arg('status', 'approved', $base_link)), $current === 'approved' ? ' class="current"' : '', __('Approved', 'solid-affiliate') . $approved_count),
            'pending'    => sprintf('<a id="filter-pending" href="%s"%s>%s</a>', esc_url(add_query_arg('status', 'pending', $base_link)), $current === 'pending' ? ' class="current"' : '', __('Pending', 'solid-affiliate') . $pending_count),
            'rejected'    => sprintf('<a id="filter-rejected" href="%s"%s>%s</a>', esc_url(add_query_arg('status', 'rejected', $base_link)), $current === 'rejected' ? ' class="current"' : '', __('Rejected', 'solid-affiliate') . $rejected_count),
        );

        return $views;
    }

    /**
     * Prepare the class items
     *
     * @return void
     */
    function prepare_items()
    {
        // Custom search things
        $user_search_key = isset($_GET['s']) ? wp_unslash(trim((string)$_GET['s'])) : '';
        /**
         * @psalm-suppress DocblockTypeContradiction
         */
        if (is_array($user_search_key)) {
            /**
             * @psalm-suppress MixedArrayAccess
             */
            $user_search_key = (string)$user_search_key[0];
        }


        if ($user_search_key) {
            $fuzzy_searched_ids = Affiliate::fuzzy_search($user_search_key, true);
            $search_args = array();
        } else {
            $fuzzy_searched_ids = [];
            $search_args = array();
        }


        $columns               = $this->get_columns();
        $hidden                = get_hidden_columns($this->screen);
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $per_page              = 10;
        $per_page = isset($_REQUEST['per_page']) ? (int)$_REQUEST['per_page'] : $per_page;
        // no more than 100
        $per_page = min(100, $per_page);

        $current_page          = $this->get_pagenum();
        $offset                = ($current_page - 1) * $per_page;
        $this->page_status     = isset($_GET['status']) ? sanitize_text_field((string)$_GET['status']) : '2';

        // only ncessary because we have sample data
        $args = array(
            'offset' => $offset,
            'number' => $per_page,
        );

        $maybe_default_sort = $this->configs->default_sort;
        if (!is_null($maybe_default_sort)) {
            $args['orderby'] = $maybe_default_sort['orderby'];
            $args['order']   = $maybe_default_sort['order'];
        }
        if (isset($_REQUEST['orderby']) && isset($_REQUEST['order'])) {
            $args['orderby'] = (string)$_REQUEST['orderby'];
            $args['order']   = (string)$_REQUEST['order'];
        }

        $search_function = $this->configs->search_function;

        $filter_args = [];
        if (isset($_GET['status'])) {
            $filter_args[] = ['status', (string)$_GET['status']];
        }
        if (isset($_GET['affiliate_group_id']) && !empty($_GET['affiliate_group_id'])) {
            $filter_args[] = ['affiliate_group_id', (string)$_GET['affiliate_group_id']];
        }

        // Handle tags filter
        if (isset($_GET['tag']) && !empty($_GET['tag'])) {
            $tag = (string)$_GET['tag'];
            $tagged_affiliate_ids = Tags::affiliate_ids_for_tag_name($tag);
            $fuzzy_searched_ids = array_merge($fuzzy_searched_ids, $tagged_affiliate_ids);
        }

        if ($user_search_key && empty($fuzzy_searched_ids)) {
            $this->items = [];
        } else {
            $this->items = $search_function($args, $search_args, $filter_args, $fuzzy_searched_ids);
        }


        $count_function = $this->configs->count_function;
        $total_items = $count_function();

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ));
    }
}
