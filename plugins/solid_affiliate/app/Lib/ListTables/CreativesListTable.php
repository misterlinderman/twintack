<?php

namespace SolidAffiliate\Lib\ListTables;

use GuzzleHttp\Promise\Create;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Models\Creative;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\VO\ListTableConfigs;
use SolidAffiliate\Models\Affiliate;


class CreativesListTable extends SolidWPListTable
{

    /**
     * @return ListTableConfigs
     */
    public static function configs()
    {
        $configs = [
            'model_class' => Creative::class,
            'singular' => Creative::ct_table_options()->data['singular'],
            'plural' => Creative::ct_table_options()->data['plural'],
            'schema' => Creative::schema(),
            'page_key' => Creative::ADMIN_PAGE_KEY,
            'bulk_actions' => array(
                'Activate'  => __('Activate', 'solid-affiliate'),
                'Deactivate'  => __('Deactivate', 'solid-affiliate'),
                'Delete'  => __('Delete', 'solid-affiliate'),
            ),
            "search_by_key" => __("Name", 'solid-affiliate'),
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
                return Creative::search($args, $search_args, $filter_args, $where_ids);
            },
            "count_function" =>
            /** @return int */
            function () {
                return Creative::count();
            },
            "computed_columns" => [
                [
                    'column_name' => __('Shortcode', 'solid-affiliate'),
                    'function' =>
                    /** 
                     * @param Creative $item 
                     * @return string
                     **/
                    function ($item) {
                        return "[solid_affiliate_creative id='{$item->id}']";
                    }
                ],
                [
                    'column_name' => __('Preview', 'solid-affiliate'),
                    'function' =>
                    /** 
                     * @param Creative $item 
                     * @return string
                     **/
                    function ($item) {
                        return Creative::generate_html_for_creative($item, 0, '100%');
                    }
                ],
            ],
            'column_name_overrides' => [
                'id' => __('Creative ID', 'solid-affiliate'),
                'name' => __('Creative Name', 'solid-affiliate'),
                'url' => __('URL (when creative is clicked)', 'solid-affiliate')
            ]
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
        new CreativesListTable;
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
                /** @var Creative $item */
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
        $actions['edit']   = sprintf('<a href="%s" data-id="%d" title="%s">%s</a>', URLs::edit(Creative::class, $item_id), $item_id, __('Edit this item', 'solid-affiliate'), __('Edit this item', 'solid-affiliate'));
        $actions['delete'] = sprintf('<a href="%s" class="submitdelete" data-id="%d" title="%s">%s</a>', URLs::delete(Creative::class, false, $item_id), $item_id, __('Delete this item', 'solid-affiliate'), __('Delete Item', 'solid-affiliate'));

        return sprintf('<strong>%1$s</strong> %2$s', $item_id, $this->row_actions($actions));
    }

    /**
     * @param object $item
     * 
     * @return string
     */
    function column_status($item)
    {
        $status = (string)$item->status;
        return Formatters::status_with_tooltip($status, Creative::class, 'admin');
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
     * @param Creative $item
     * @return string
     */
    function column_creative_image_url($item)
    {
        if (empty($item->creative_image_url)) {
            return '';
        } else {
            return "<img src='{$item->creative_image_url}' width='100' height='100'></img>";
        }
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

        $total_count    = '&nbsp;<span id="count-all" class="count">(' . Creative::count() . ')</span>';
        $active_count   = '&nbsp;<span id="count-active" class="count">(' . Creative::count(['status' => 'active']) . ')</span>';
        $inactive_count  = '&nbsp;<span id="count-inactive" class="count">(' . Creative::count(['status' => 'inactive']) . ')</span>';


        $views = array(
            'all'        => sprintf('<a id="filter-all" href="%s"%s>%s</a>', esc_url(remove_query_arg('status', $base_link)), $current === 'all' || $current == '' ? ' class="current"' : '', __('All', 'solid-affiliate') . $total_count),
            'active'    => sprintf('<a id="filter-active" href="%s"%s>%s</a>', esc_url(add_query_arg('status', 'active', $base_link)), $current === 'active' ? ' class="current"' : '', __('Active', 'solid-affiliate') . $active_count),
            'inactive'    => sprintf('<a id="filter-inactive" href="%s"%s>%s</a>', esc_url(add_query_arg('status', 'inactive', $base_link)), $current === 'inactive' ? ' class="current"' : '', __('Inactive', 'solid-affiliate') . $inactive_count),
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
                $search_by_key, $user_search_key
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
        $this->items = $search_function($args, $search_args, $filter_args, []);


        $count_function = $this->configs()->count_function;
        $total_items = $count_function();

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ));
    }
}
