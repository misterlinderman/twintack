<?php

namespace SolidAffiliate\Lib\ListTables;

use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Lib\VO\ListTableConfigs;
use SolidAffiliate\Models\Referral;

/**
 * List table class
 */
class PayoutsListTable extends SolidWPListTable
{
    /**
     * @return ListTableConfigs
     */
    public static function configs()
    {
        $configs = [
            'model_class' => Payout::class,
            'singular' => Payout::ct_table_options()->data['singular'],
            'plural' => Payout::ct_table_options()->data['plural'],
            'schema' => Payout::schema(),
            'page_key' => Payout::ADMIN_PAGE_KEY,
            'bulk_actions' => array(
                'Delete'  => __('Delete', 'solid-affiliate'),
            ),
            "search_by_key" => "id",
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
                return Payout::search($args, $search_args, $filter_args, $where_ids);
            },
            "count_function" =>
            /** @return int */
            function () {
                return Payout::count();
            },
            'hidden_columns_by_default' => [
                'description', 'created_by_user_id'
            ],
            "computed_columns" => [
                [
                    'column_name' => __('Referrals', 'solid-affiliate'),
                    'function' =>
                    /** 
                     * @param Referral $item 
                     * @return string
                     **/
                    function ($item) {
                        $count = Referral::count(['payout_id' => $item->id]);
                        if (empty($count)) {
                            return "0";
                        } else {
                            // TODO once our tables are better and we can add filters easily,
                            // we should add the ability to click on the number of referrals and have them taken
                            // to 'Manage Referrals' with the payout_id filter filled out. Currently there is no payout_id filter.

                            // $path = URLs::index(Referral::class, true);
                            // $path = add_query_arg(['payout_id' => $item->id], $path);
                            // return "<a href='{$path}'>{$count}</a>";
                            return "{$count}";
                        }
                    }
                ],
            ],
            'column_name_overrides' => [
                'id' => __('Payout', 'solid-affiliate'),
                'affiliate_id' => __('Affiliate Paid', 'solid-affiliate'),
                'created_by_user_id' => __('Initiated by', 'solid-affiliate'),
                'amount' => __('Payout Amount', 'solid-affiliate')
            ],
            'default_sort' => [
                'orderby' => 'id',
                'order' => 'desc'
            ],
        ];

        return new ListTableConfigs($configs);
    }

    function __construct()
    {
        $this->items = [];
        $this->_args = [];
        /** @psalm-suppress PropertyTypeCoercion */
        $this->screen = (object)[];
        $this->_column_headers = [];

        parent::__construct(array(
            'singular' => $this->configs()->singular,
            'plural' => $this->configs()->plural,
            'ajax' => false
        ));
    }

    /**
     * @return void
     */
    public static function add_screen_options()
    {
        new PayoutsListTable;
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
        echo SharedListTableFunctions::render_affiliate_id_filter();
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
                /** @var Affiliate $item */
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
        $actions['delete'] = sprintf('<a href="%s" class="submitdelete" data-id="%d" title="%s">%s</a>', URLs::delete(Payout::class, false, $item_id), $item_id, __('Delete this item', 'solid-affiliate'), __('Delete', 'solid-affiliate'));

        return sprintf('<strong>%1$s</strong> %2$s', $item_id, $this->row_actions($actions));
    }

    /**
     * @param  object  $item
     *
     * @return string
     */
    function column_bulk_payout_id($item)
    {
        $bulk_payout_id = (int)$item->bulk_payout_id;

        if (empty($bulk_payout_id)) {
            return '-';
        } else {
            return (string)$bulk_payout_id;
        }
    }

    /**
     * @param  object  $item
     *
     * @return string
     */
    function column_affiliate_id($item)
    {
        $affiliate_id = (int)$item->affiliate_id;

        return SharedListTableFunctions::affiliate_column($affiliate_id, false);
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
     * @return string 
     */
    function column_amount($item)
    {
        return Formatters::money($item->amount);
    }

    /**
     * @param object $item
     * 
     * @return string
     */
    function column_status($item)
    {
        $status = (string)$item->status;
        return Formatters::status_with_tooltip($status, Payout::class, 'admin');
    }

    /**
     * @param object $item
     * 
     * @return string
     */
    function column_created_by_user_id($item)
    {
        $user_id = (int)$item->created_by_user_id;
        $user = get_userdata($user_id);
        $username = $user ? $user->user_nicename : 'User Not Found';

        $user_link = sprintf('<a href="%s" data-id="%d" title="%s">%s</a><br><small>%s</small>', get_edit_user_link($user_id), $user_id, $username, "{$username}", " User ID: {$user_id}");
        return $user_link;
    }

    /**
     * @param object $item 
     * @return string 
     */
    function column_payout_method($item)
    {
        return Formatters::payout_method((string)$item->payout_method);
    }

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

        $total_count    = '&nbsp;<span id="count-all" class="count">(' . Payout::count() . ')</span>';
        $paid_count = '&nbsp;<span id="count-paid" class="count">(' . Payout::count(['status' => Payout::STATUS_PAID]) . ')</span>';


        $views = array(
            'all'        => sprintf('<a id="filter-all" href="%s"%s>%s</a>', esc_url(remove_query_arg('status', $base_link)), $current === 'all' || $current == '' ? ' class="current"' : '', __('All', 'solid-affiliate') . $total_count),
            'paid'    => sprintf('<a id="filter-paid" href="%s"%s>%s</a>', esc_url(add_query_arg('status', 'paid', $base_link)), $current === 'paid' ? ' class="current"' : '', __('Paid', 'solid-affiliate') . $paid_count),
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

        $maybe_default_sort = $this->configs()->default_sort;
        if (!is_null($maybe_default_sort)) {
            $args['orderby'] = $maybe_default_sort['orderby'];
            $args['order']   = $maybe_default_sort['order'];
        }
        if (isset($_REQUEST['orderby']) && isset($_REQUEST['order'])) {
            $args['orderby'] = (string)$_REQUEST['orderby'];
            $args['order']   = (string)$_REQUEST['order'];
        }

        $search_function = $this->configs()->search_function;

        $filter_args = [];
        if (isset($_GET['status'])) {
            $filter_args[] = ['status', (string)$_GET['status']];
        }

        if (isset($_GET['affiliate_id']) && !empty($_GET['affiliate_id'])) {
            $filter_args[] = ['affiliate_id', (string)$_GET['affiliate_id']];
        }
        $this->items = $search_function($args, $search_args, $filter_args, []);

        $count_function = $this->configs()->count_function;
        $total_items = $count_function();

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ));
    }
}
