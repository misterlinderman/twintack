<?php

namespace SolidAffiliate\Lib\ListTables;

use SolidAffiliate\Controllers\AdminReportsController;
use SolidAffiliate\Lib\AdminReportsHelper;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Visit;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\ListTables\SharedListTableFunctions;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\VO\ListTableConfigs;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Models\AffiliateGroup;
use SolidAffiliate\Views\Shared\SolidTooltipView;

class AffiliateGroupsListTable extends SolidWPListTable
{

    /**
     * @return ListTableConfigs
     */
    public static function configs()
    {
        $configs = [
            'model_class' => AffiliateGroup::class,
            'singular' => AffiliateGroup::ct_table_options()->data['singular'],
            'plural' => AffiliateGroup::ct_table_options()->data['plural'],
            'schema' => AffiliateGroup::schema(),
            'page_key' => AffiliateGroup::ADMIN_PAGE_KEY,
            'bulk_actions' => array(
                'Delete'  => __('Delete', 'solid-affiliate'),
            ),
            "search_by_key" => "name",
            "search_button_text_override" => __('Search by group name', 'solid-affiliate'),
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
                return AffiliateGroup::search($args, $search_args, $filter_args, $where_ids);
            },
            "count_function" =>
            /** @return int */
            function () {
                return AffiliateGroup::count();
            },
            'hidden_columns_by_default' => [
                'id', 'created_at', 'updated_at', 'commission_type'
            ],
            "computed_columns" => [
                [
                    'column_name' => __('Affiliates', 'solid-affiliate'),
                    'function' =>
                    /** 
                     * @param AffiliateGroup $item 
                     * @return string
                     **/
                    function ($item) {
                        $count = Affiliate::count(['affiliate_group_id' => $item->id]);
                        if (empty($count)) {
                            return "0";
                        } else {
                            $path = URLs::index(Affiliate::class, true);
                            $path = add_query_arg(['affiliate_group_id' => $item->id], $path);
                            return "<a href='{$path}'>{$count}</a>";
                        }
                    }
                ],
                // [
                //     'column_name' => 'Referrals',
                //     'function' =>
                //     /** 
                //      * @param Affiliate $item 
                //      * @return string
                //      **/
                //     function ($item) {
                //         $count = Referral::count(['affiliate_id' => $item->id]);
                //         if (empty($count)) {
                //             return "0";
                //         } else {
                //             $path = URLs::index(Referral::class, true);
                //             $path = add_query_arg(['affiliate_id' => $item->id], $path);
                //             return "<a href='{$path}'>{$count}</a>";
                //         }
                //     }
                // ],
                [
                    'column_name' => 'net_revenue',
                    'function' =>
                    /** 
                     * @param AffiliateGroup $item 
                     * @return string
                     **/
                    function ($item) {
                        $affiliate_ids = AffiliateGroup::affiliate_ids_for($item->id);
                        if (empty($affiliate_ids)) {
                            return __('Add affiliates to this group to see net revenue.', 'solid-affiliate');
                        }

                        $dates = new PresetDateRangeParams(['preset_date_range' => 'all_time']);
                        $data = AdminReportsHelper::referrals_data($dates->computed_start_date(), $dates->computed_end_date(), $affiliate_ids, Referral::STATUSES_PAID_AND_UNPAID);

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
            ],
            'column_name_overrides' => [
                // 'id' => 'Affiliate',
                'net_revenue' => __('Net Revenue', 'solid-affiliate'),
                // 'paid_commission' => 'Paid Commission',
                // 'unpaid_commission' => 'Unpaid Commission'
            ],
            "custom_css" => "",
        ];

        return new ListTableConfigs($configs);
    }


    function __construct()
    {
        // These are to stop PSALM from complaining about "property ... is not defined in constructor"
        $this->items = [];
        $this->_args = [];
        /** @psalm-suppress PropertyTypeCoercion */
        $this->screen = (object)[];
        $this->_column_headers = [];

        parent::__construct(array(
            'singular' => $this->configs()->singular,
            'plural' => $this->configs()->plural,
            'ajax' => true,
        ));
    }

    /**
     * @return void
     */
    public static function add_screen_options()
    {
        new AffiliateGroupsListTable;
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
        $plural = $this->configs()->plural;
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
        $computed_column_structs = $this->configs()->computed_columns;
        foreach ($computed_column_structs as $struct) {
            if ($struct['column_name'] != $column_name) {
                continue;
            } else {
                /** @var AffiliateGroup $item */
                return (string)$struct['function']($item);
            }
        }

        switch ($column_name) {
            default:
                return isset($item->$column_name) ? (string)$item->$column_name : '';
        }
    }

    /**
     * Get the column names
     *
     * @return array<string, string>
     */
    function get_columns()
    {
        return SharedListTableFunctions::shared_get_columns($this->configs());
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
        $item_id = (int)$item->id;

        $actions           = array();
        $actions['edit']   = sprintf('<a href="%s" data-id="%d" title="%s">%s</a>', URLs::edit(AffiliateGroup::class, $item_id), $item_id, __('Edit this item', 'solid-affiliate'), __('Edit', 'solid-affiliate'));
        $actions['delete'] = sprintf('<a href="%s" class="submitdelete" data-id="%d" title="%s">%s</a>', URLs::delete(AffiliateGroup::class, false, $item_id), $item_id, __('Delete this item', 'solid-affiliate'), __('Delete', 'solid-affiliate'));

        return sprintf('<a href="%1$s"><strong>%2$s</strong></a> %3$s', URLs::edit(AffiliateGroup::class, $item_id), $item_id, $this->row_actions($actions));
    }

    /**
     * Render the designation name column
     *
     * @param  object  $item
     *
     * @return string
     */
    function column_name($item)
    {
        $page_key = $this->configs()->page_key;
        $group_name = (string)$item->name;
        $item_id = (int)$item->id;

        $is_default_group = $item_id == AffiliateGroup::get_default_group_id();

        $actions           = array();
        $actions['edit']   = sprintf('<a href="%s" data-id="%d" title="%s">%s</a>', URLs::edit(AffiliateGroup::class, $item_id), $item_id, __('Edit this item', 'solid-affiliate'), __('Edit', 'solid-affiliate'));
        if (!$is_default_group) {
            $actions['set_default'] = sprintf('<a href="%s" class="submitset_default" data-id="%d" title="%s">%s</a>', URLs::admin_path($page_key, false, ['action' => 'set_default', 'id' => $item_id]), $item_id, __('Set as default', 'solid-affiliate'), __('Set as default', 'solid-affiliate'));
        }
        $actions['delete'] = sprintf('<a href="%s" class="submitdelete" data-id="%d" title="%s">%s</a>', URLs::delete(AffiliateGroup::class, false, $item_id), $item_id, __('Delete this item', 'solid-affiliate'), __('Delete', 'solid-affiliate'));

        $default_icon = $is_default_group ? '<span class="sld_badge">' . __('Default', 'solid-affiliate') . '</span>' : '';

        return sprintf('<strong>%2$s</strong> (#%4$s) %5$s %3$s', URLs::edit(AffiliateGroup::class, $item_id), $group_name, $this->row_actions($actions), $item_id, $default_icon);
    }

    /**
     * Get sortable columns
     *
     * @return array
     */
    function get_sortable_columns()
    {
        $schema = $this->configs()->schema;
        $columns = SchemaFunctions::columns_for_list_table_from_schema($schema);

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
        $actions = $this->configs()->bulk_actions;
        return $actions;
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
     * @param object $item
     * 
     * @return string
     */
    function column_commission_rate($item)
    {
        $commission_rate = (string)$item->commission_rate;
        /** @var 'site_default'|'flat'|'percentage' $commission_type */
        $commission_type = (string)$item->commission_type;

        return SharedListTableFunctions::commission_rate_column($commission_rate, $commission_type, 'Affiliate Group');
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

    // /**
    //  * @param object $item
    //  * 
    //  * @return string
    //  */
    // function column_status($item)
    // {
    //     $status = (string)$item->status;
    //     return Formatters::status($status);
    // }

    /**
     * Set the views
     *
     * @return array
     */
    public function get_views()
    {
        $page_key = $this->configs()->page_key;
        $base_link      = URLs::admin_path($page_key);

        $current        = isset($_GET['status']) ? (string)$_GET['status'] : '';

        $total_count    = '&nbsp;<span id="count-all" class="count">(' . AffiliateGroup::count() . ')</span>';

        $views = array(
            'all'        => sprintf('<a id="filter-all" href="%s"%s>%s</a>', esc_url(remove_query_arg('status', $base_link)), $current === 'all' || $current == '' ? ' class="current"' : '', __('All', 'solid-affiliate') . $total_count),
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
        $search_by_key = $this->configs()->search_by_key;
        $search_args = array(
            $search_by_key, $user_search_key
        );

        $columns               = $this->get_columns();
        $hidden                = get_hidden_columns($this->screen);
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $per_page              = 10;
        $current_page          = $this->get_pagenum();
        $offset                = ($current_page - 1) * $per_page;
        $this->page_status     = isset($_GET['status']) ? sanitize_text_field((string)$_GET['status']) : '2';

        // only ncessary because we have sample data
        $args = array(
            'offset' => $offset,
            'number' => $per_page,
        );

        if (isset($_REQUEST['orderby']) && isset($_REQUEST['order'])) {
            $args['orderby'] = (string)$_REQUEST['orderby'];
            $args['order']   = (string)$_REQUEST['order'];
        }

        $search_function = $this->configs()->search_function;
        $filter_args = [];

        $this->items = $search_function($args, $search_args, $filter_args, []);

        $count_function = $this->configs()->count_function;
        $total_items = $count_function();

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ));
    }
}
