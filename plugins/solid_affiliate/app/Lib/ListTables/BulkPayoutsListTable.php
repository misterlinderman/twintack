<?php

namespace SolidAffiliate\Lib\ListTables;

use SolidAffiliate\Controllers\PayAffiliatesController;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Lib\VO\ListTableConfigs;
use SolidAffiliate\Models\BulkPayout;
use SolidAffiliate\Models\Referral;

/**
 * List table class
 */
class BulkPayoutsListTable extends SolidWPListTable
{
    /**
     * @return ListTableConfigs
     */
    public static function configs()
    {
        $configs = [
            'model_class' => BulkPayout::class,
            'singular' => BulkPayout::ct_table_options()->data['singular'],
            'plural' => BulkPayout::ct_table_options()->data['plural'],
            'schema' => BulkPayout::schema(),
            'page_key' => BulkPayout::ADMIN_PAGE_KEY,
            'bulk_actions' => array(),
            "search_by_key" => "reference",
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
                return BulkPayout::search($args, $search_args, $filter_args, $where_ids);
            },
            "count_function" =>
            /** @return int */
            function () {
                return BulkPayout::count();
            },
            'hidden_columns_by_default' => [
                'currency', 'date_range_end', 'created_by_user_id', 'date_range', 'Totals', 'total_amount'
            ],
            "computed_columns" => [
                [
                    'column_name' => __('Totals', 'solid-affiliate'),
                    'function' =>
                    /** 
                     * @param BulkPayout $item 
                     * @return string
                     **/
                    function ($item) {
                        $payout_ids = Payout::select_ids(['bulk_payout_id' => $item->id]);
                        $total_payouts = count($payout_ids);

                        $total_affiliates = $total_payouts; // It's one payout per Affiliate
                        $total_referrals = Referral::count(['payout_id' => ['operator' => 'IN', 'value' => $payout_ids]]);

                        return "<small>" . __('Payouts', 'solid-affiliate') . ": {$total_payouts}</small>" . "<br>" . "<small>" . __('Affiliates', 'solid-affiliate') . ": {$total_affiliates}</small>" . "<br>" . "<small>" . __('Referrals', 'solid-affiliate') . ": {$total_referrals}</small>";
                    }
                ],
            ],
            "custom_css" => "
                .column-date_range_start { width: 20%; }
                .column-Totals { width: 18%; }
            ",
            "column_name_overrides" => [
                'date_range_start' => __('Date Range', 'solid-affiliate'),
            ],
            'default_sort' => [
                'orderby' => 'id',
                'order' => 'desc'
            ]
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
        new BulkPayoutsListTable;
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
        // if ($which === 'bottom') {
        //     return;
        // }
        // echo SharedListTableFunctions::render_affiliate_id_filter();
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
     * @param BulkPayout $item 
     * @return string 
     */
    function column_total_amount($item)
    {
        return Formatters::money($item->total_amount);
    }

    /**
     * @param object $item
     * 
     * @return string
     */
    function column_status($item)
    {
        $status = (string)$item->status;
        return Formatters::status_with_tooltip($status, BulkPayout::class, 'admin');
    }

    /**
     * @param BulkPayout $item
     * 
     * @return string
     */
    function column_date_range_start($item)
    {
        $start_date = (string)$item->date_range_start;
        $formatted_start_date = Formatters::simple_date($start_date);

        $end_date = (string)$item->date_range_end;
        $formatted_end_date = Formatters::simple_date($end_date);

        $date_range_selector = $item->date_range;

        return "{$formatted_start_date} <small>-</small> {$formatted_end_date}" . "<br>" . "<small>" . __('range', 'solid-affiliate') . ": <em>{$date_range_selector}</em></small>";
    }

    /**
     * @param BulkPayout $item
     * 
     * @return string
     */
    function column_date_range_end($item)
    {
        $val = (string)$item->date_range_end;
        return Formatters::simple_date($val);
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
        $username = $user ? $user->user_nicename : __('User Not Found', 'solid-affiliate');

        $user_link = sprintf('<a href="%s" data-id="%d" title="%s">%s</a><br><small>%s</small>', get_edit_user_link($user_id), $user_id, $username, "{$username}", " " . __('User ID', 'solid-affiliate') . ": {$user_id}");
        return $user_link;
    }

    /**
     * @param BulkPayout $item
     * 
     * @return string
     */
    function column_reference($item)
    {
        $reference = $item->reference;

        if ($item->method == PayAffiliatesController::BULK_PAYOUT_METHOD_CSV) {

            ob_start();
?>
            <form action="" method="post" id="download-csv">
                <?php wp_nonce_field(PayAffiliatesController::NONCE_DOWNLOAD_BULK_PAYOUT_CSV); ?>
                <?php echo FormBuilder::build_hidden_field('bulk_payout_id', $item->id); ?>
                <?php submit_button(__("Download CSV", 'solid-affiliate'), 'small', PayAffiliatesController::POST_PARAM_DOWNLOAD_BULK_PAYOUT_CSV); ?>
            </form>
<?php
            $res = ob_get_clean();
            return $reference . '<br>' . $res;
        }

        /** @psalm-suppress RedundantConditionGivenDocblockType */
        if ($item->method == PayAffiliatesController::BULK_PAYOUT_METHOD_PAYPAL) {
            $is_paypal_sandbox = ($item->api_mode == 'sandbox');
            $link = BulkPayout::paypal_reference_url($reference, $is_paypal_sandbox);

            $paypal_reference_html = "<small>" . __('PayPal Batch Payout ID', 'solid-affiliate') . "</small>" . "<br>" . $reference . "<br>" . "<a href='{$link}'>" . __('View in PayPal', 'solid-affiliate') . "</a>";
            if ($is_paypal_sandbox) {
                $paypal_reference_html .= "<br><small style='color: orange;'>" . __('PayPal Sandbox Mode', 'solid-affiliate') . "</small>";
            }
            return $paypal_reference_html;
        }

        return $reference;
    }

    /**
     * @param BulkPayout $item
     * 
     * @return string
     */
    function column_method($item)
    {
        return Formatters::payout_method($item->method);
    }

    /**
     * @param BulkPayout $item
     * 
     * @return string
     */
    function column_serialized_logic_rules($item)
    {
        $logic_rules = Validators::arr_of_logic_rule(
            unserialize($item->serialized_logic_rules)
        );

////////////////////////////////
         // if there are no logic rules, return a helpful message
         if (empty($logic_rules)) {
            return '<p>' . __('No additional filters were applied to this bulk payout', 'solid-affiliate') . '</p>';
         }
         ////////////////////////////////

         // Start building the HTML output
         $html = '';

         // loop through the logic rules
         foreach ($logic_rules as $data) {
            $html .= "<div class='logic-rule'>";

            // Add the operator to the output
            $html .= "<div class='operator'><span>Function</span>" . $data['operator'] . "</div>";

            // Add the field to the output
            $html .= "<div class='field'><span>Filter by</span>" . $data['field'] . "</div>";

            // Add the values to the output
            $html .= "<div class='values'><span>Selected values</span><ul>";
            foreach ($data['value'] as $value) {
               $html .= "<li>" . $value . "</li>";
            }
            $html .= "</ul>";
            $html .= "</div>";
            // Finish the HTML output
            $html .= "</div>";
         }

         // Return the HTML
         return $html;
    }

    /**
     * Set the views
     *
     * @return array
     */
    public function get_views()
    {
        $base_link = URLs::index(BulkPayout::class);

        $current        = isset($_GET['status']) ? (string)$_GET['status'] : '';

        $total_count    = '&nbsp;<span id="count-all" class="count">(' . BulkPayout::count() . ')</span>';
        $success_count = '&nbsp;<span id="count-sucess" class="count">(' . BulkPayout::count(['status' => BulkPayout::SUCCESS_STATUS]) . ')</span>';
        $processing_count = '&nbsp;<span id="count-processing" class="count">(' . BulkPayout::count(['status' => BulkPayout::PROCESSING_STATUS]) . ')</span>';
        $fail_count = '&nbsp;<span id="count-fail" class="count">(' . BulkPayout::count(['status' => BulkPayout::FAIL_STATUS]) . ')</span>';


        $views = array(
            'all'        => sprintf('<a id="filter-all" href="%s"%s>%s</a>', esc_url(remove_query_arg('status', $base_link)), $current === 'all' || $current == '' ? ' class="current"' : '', __('All', 'solid-affiliate') . $total_count),
            BulkPayout::SUCCESS_STATUS    => sprintf('<a id="filter-paid" href="%s"%s>%s</a>', esc_url(add_query_arg('status', BulkPayout::SUCCESS_STATUS, $base_link)), $current === BulkPayout::SUCCESS_STATUS ? ' class="current"' : '', __(BulkPayout::SUCCESS_STATUS, 'solid-affiliate') . $success_count),
            BulkPayout::PROCESSING_STATUS    => sprintf('<a id="filter-paid" href="%s"%s>%s</a>', esc_url(add_query_arg('status', BulkPayout::PROCESSING_STATUS, $base_link)), $current === BulkPayout::PROCESSING_STATUS ? ' class="current"' : '', __(BulkPayout::PROCESSING_STATUS, 'solid-affiliate') . $processing_count),
            BulkPayout::FAIL_STATUS    => sprintf('<a id="filter-paid" href="%s"%s>%s</a>', esc_url(add_query_arg('status', BulkPayout::FAIL_STATUS, $base_link)), $current === BulkPayout::FAIL_STATUS ? ' class="current"' : '', __(BulkPayout::FAIL_STATUS, 'solid-affiliate') . $fail_count),
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
