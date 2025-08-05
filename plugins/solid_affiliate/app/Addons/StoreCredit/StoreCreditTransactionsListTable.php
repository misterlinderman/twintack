<?php

namespace SolidAffiliate\Addons\StoreCredit;

use SolidAffiliate\Controllers\PayAffiliatesController;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\Links;
use SolidAffiliate\Lib\ListTables\SharedListTableFunctions;
use SolidAffiliate\Lib\ListTables\SolidWPListTable;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Lib\VO\ListTableConfigs;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\StoreCreditTransaction;

/**
 * List table class
 */
class StoreCreditTransactionsListTable extends SolidWPListTable
{
    /**
     * @return ListTableConfigs
     */
    public static function configs()
    {
        $configs = [
            'model_class' => StoreCreditTransaction::class,
            'singular' => StoreCreditTransaction::ct_table_options()->data['singular'],
            'plural' => StoreCreditTransaction::ct_table_options()->data['plural'],
            'schema' => StoreCreditTransaction::schema(),
            'page_key' => StoreCreditTransaction::ADMIN_PAGE_KEY,
            'bulk_actions' => array(),
            "search_by_key" => "affiliate_id",
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
                return StoreCreditTransaction::search($args, $search_args, $filter_args, $where_ids);
            },
            "count_function" =>
            /** @return int */
            function () {
                return StoreCreditTransaction::count();
            },
            'hidden_columns_by_default' => ['source_id', 'type', 'created_by_user_id', 'description'],
            "computed_columns" => [
                [
                    'column_name' => __('Affiliate current balance', 'solid-affiliate'),
                    'function' =>
                    /** 
                     * @param StoreCreditTransaction $item 
                     * @return string
                     **/
                    function ($item) {
                        $amount = Addon::outstanding_store_credit_for_affiliate($item->affiliate_id);
                        return Formatters::money($amount);
                    }
                ],
            ],
            "custom_css" => "
              .search-box { display: none; }
            ",
            "column_name_overrides" => [
                'id' => 'Transaction ID',
                'affiliate_id' => 'Affiliate',
            ],
            'default_sort' => [
                'orderby' => 'id',
                'order' => 'desc'
            ],
            'disable_checkbox_column' => true,
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
        new StoreCreditTransactionsListTable;
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

        return sprintf('<strong>%1$s</strong> %2$s', $item_id, $this->row_actions($actions));
    }

    /**
     * @param StoreCreditTransaction $item
     *
     * @return string
     */
    function column_created_at($item)
    {
        return Formatters::simple_date($item->created_at);
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
     * @param StoreCreditTransaction $item 
     * @return string 
     */
    function column_amount($item)
    {
        return Formatters::store_credit_amount($item->amount, $item->type);
    }

    /**
     * @param StoreCreditTransaction $item
     * 
     * @return string
     */
    function column_type($item)
    {
        $type = (string)$item->type;

        if ($type === StoreCreditTransaction::TYPE_DEBIT) {
            $formatted_type = '<span class="sld-store-credit-transaction-type sld-debit">' . Formatters::humanize($type) . '</span>';
        } else {
            $formatted_type = '<span class="sld-store-credit-transaction-type sld-credit">' . Formatters::humanize($type) . '</span>';
        }

        return $formatted_type;
    }

    /**
     * @param StoreCreditTransaction $item
     * 
     * @return string
     */
    function column_created_by_user_id($item)
    {
        if (StoreCreditTransaction::is_created_by_user_id_applicable($item)) {
            $user_id = (int)$item->created_by_user_id;
            $user = get_userdata($user_id);
            $username = $user ? $user->user_nicename : __('User Not Found', 'solid-affiliate');

            $user_link = sprintf('<a href="%s" data-id="%d" title="%s">%s</a><br><small>%s</small>', get_edit_user_link($user_id), $user_id, $username, "{$username}", " " . __('User ID', 'solid-affiliate') . ": {$user_id}");
            return $user_link;
        } else {
            return '<span class="sld-not-applicable" style="opacity: 0.5">' . __('not applicable', 'solid-affiliate') . '</span>';
        }
    }

    /**
     * @param StoreCreditTransaction $item
     * 
     * @return string
     */
    function column_source($item)
    {
        $source = $item->source;
        $source_id = $item->source_id;
        switch ($source) {
            case StoreCreditTransaction::SOURCE_MANUAL:
                $formatted_source = '<span class="sld-store-credit-transaction-source sld-manual">' . Formatters::humanize($source) . '</span>';
                break;
            case StoreCreditTransaction::SOURCE_PAYOUT:
                $url = URLs::edit(Payout::class, $source_id);
                $link = Links::render($url, 'Payout #' . $source_id);
                return $link;
            case StoreCreditTransaction::SOURCE_WOOCOMMERCE_PURCHASE:
                $url = WooCommerceIntegration::get_admin_order_url($source_id);
                $link = Links::render($url, 'Order #' . $source_id);
                return $link;
            case StoreCreditTransaction::SOURCE_WOOCOMMERCE_SUBSCRIPTION_RENEWAL:
                $url = WooCommerceIntegration::get_admin_order_url($source_id);
                $link = Links::render($url, 'Renewal #' . $source_id);
                return $link;
        }

        return $formatted_source;
    }

    /**
     * Set the views
     *
     * @return array
     */
    public function get_views()
    {
        $base_link = URLs::index(StoreCreditTransaction::class);

        $current        = isset($_GET['type']) ? (string)$_GET['type'] : '';
        $current_tab = isset($_GET['tab']) ? (string)$_GET['tab'] : '';
        $base_link = add_query_arg('tab', $current_tab, $base_link);

        $total_count    = '&nbsp;<span id="count-all" class="count">(' . StoreCreditTransaction::count() . ')</span>';
        $debit_count = '&nbsp;<span id="count-debit" class="count">(' . StoreCreditTransaction::count(['type' => StoreCreditTransaction::TYPE_DEBIT]) . ')</span>';
        $credit_count = '&nbsp;<span id="count-credit" class="count">(' . StoreCreditTransaction::count(['type' => StoreCreditTransaction::TYPE_CREDIT]) . ')</span>';


        $views = array(
            'all'        => sprintf('<a id="filter-all" href="%s"%s>%s</a>', esc_url(remove_query_arg('type', $base_link)), $current === 'all' || $current == '' ? ' class="current"' : '', __('All', 'solid-affiliate') . $total_count),
            StoreCreditTransaction::TYPE_DEBIT    => sprintf('<a id="filter-debit" href="%s"%s>%s</a>', esc_url(add_query_arg('type',  StoreCreditTransaction::TYPE_DEBIT, $base_link)), $current ===  StoreCreditTransaction::TYPE_DEBIT ? ' class="current"' : '', __('Earned', 'solid-affiliate') . $debit_count),
            StoreCreditTransaction::TYPE_CREDIT    => sprintf('<a id="filter-credit" href="%s"%s>%s</a>', esc_url(add_query_arg('type',  StoreCreditTransaction::TYPE_CREDIT, $base_link)), $current ===  StoreCreditTransaction::TYPE_CREDIT ? ' class="current"' : '', __('Spent', 'solid-affiliate') . $credit_count),
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
        $this->page_status     = isset($_GET['type']) ? sanitize_text_field((string)$_GET['type']) : '2';

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
        if (isset($_GET['type'])) {
            $filter_args[] = ['type', (string)$_GET['type']];
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
