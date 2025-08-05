<?php

namespace SolidAffiliate\Lib\ListTables;

use SolidAffiliate\Lib\FF;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Models\AffiliateProductRate;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\VO\ListTableConfigs;


class AffiliateProductRatesListTable extends SolidWPListTable
{

    /**
     * @return ListTableConfigs
     */
    public static function configs()
    {
        $configs = [
            'model_class' => AffiliateProductRate::class,
            'singular' => AffiliateProductRate::ct_table_options()->data['singular'],
            'plural' => AffiliateProductRate::ct_table_options()->data['plural'],
            'schema' => AffiliateProductRate::schema(),
            'page_key' => AffiliateProductRate::ADMIN_PAGE_KEY,
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
                return AffiliateProductRate::search($args, $search_args, $filter_args, $where_ids);
            },
            "count_function" =>
            /** @return int */
            function () {
                return AffiliateProductRate::count();
            },
            "computed_columns" => [],
            'column_name_overrides' => [
                'woocommerce_product_id' => __('WooCommerce Product', 'solid-affiliate'),
                'affiliate_id' => __('Affiliate', 'solid-affiliate')
            ],
            'hidden_columns_by_default' => [
                'id',
                'created_at',
                'updated_at',
                'commission_type'
            ],
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
            'ajax' => false
        ));
    }

    /**
     * @return void
     */
    public static function add_screen_options()
    {
        new AffiliateProductRatesListTable;
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
                /** @var AffiliateProductRate $item */
                return (string)$struct['function']($item);
            }
        }

        switch ($column_name) {
            default:
                return isset($item->$column_name) ? (string)$item->$column_name : '';
        }
    }

    /**
     * @param AffiliateProductRate $item
     * @return string
     */
    function column_woocommerce_product_id($item)
    {
        return WooCommerceIntegration::formatted_product_link((int)$item->woocommerce_product_id);
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
        $actions['edit']   = sprintf('<a href="%s" data-id="%d" title="%s">%s</a>', URLs::edit(AffiliateProductRate::class, $item_id, false), $item_id, __('Edit this item', 'solid-affiliate'), __('Edit this item', 'solid-affiliate'));
        $actions['delete'] = sprintf('<a href="%s" class="submitdelete" data-id="%d" title="%s">%s</a>', URLs::delete(AffiliateProductRate::class, false, $item_id), $item_id, __('Delete this item', 'solid-affiliate'), __('Delete Item', 'solid-affiliate'));

        return sprintf('<strong>%1$s</strong> %2$s', $item_id, $this->row_actions($actions));
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

        return SharedListTableFunctions::commission_rate_column($commission_rate, $commission_type, 'item');
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
     * @param  object  $item
     *
     * @return string
     */
    function column_is_auto_referral($item)
    {
        $val = (bool)$item->is_auto_referral;

        $string =  $val ? 'enabled' : 'disabled';

        $tooltip_body =  "<p style='max-width: 300px; padding: 5px 10px;'><strong>" . __('Enable Auto Referral', 'solid-affiliate') . "</strong><br><br>" . __('If enabled, this affiliate will be rewarded a referral <strong>anytime this product is purchased</strong> even if they did not refer the customer. This is useful for setting up a revenue-split situation for an individual affiliate.', 'solid-affiliate') . "</p>";
        return Formatters::status($string, true, $tooltip_body);
    }

    /**
     * @param  object  $item
     *
     * @return string
     */
    function column_is_prevent_additional_referrals_when_auto_referral_is_triggered($item)
    {
        $val = (bool)$item->is_prevent_additional_referrals_when_auto_referral_is_triggered;

        $string =  $val ? 'enabled' : 'disabled';

        $tooltip_body = "<p style='max-width: 300px; padding: 5px 10px;'><strong>" . __('Prevent Additional Referrals When Auto Referral Is Triggered', 'solid-affiliate') . "</strong><br><br>" . __('If Auto Referral is enabled, this setting will prevent additional referrals from being created when the Auto Referral is triggered <strong>for the same referring affiliate</strong>. For example, if Affiliate #1 sends a customer to this product using their referral link or coupon code, they will only be rewarded the auto referral - not two seperate referrals.', 'solid-affiliate') . "</p>";
        return Formatters::status($string, true, $tooltip_body);
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
     * Set the views
     *
     * @return array
     */
    public function get_views()
    {
        $page_key = $this->configs()->page_key;
        $base_link      = URLs::admin_path($page_key);

        $current        = isset($_GET['status']) ? (string)$_GET['status'] : '';

        $total_count    = '&nbsp;<span id="count-all" class="count">(' . AffiliateProductRate::count() . ')</span>';

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

        if ($user_search_key) {
            $search_by_key = $this->configs()->search_by_key;
            $search_args = array(
                $search_by_key,
                $user_search_key
            );
        } else {
            $search_args = array();
        }


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
        if (isset($_GET['status'])) {
            $filter_args[] = ['status', (string)$_GET['status']];
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
