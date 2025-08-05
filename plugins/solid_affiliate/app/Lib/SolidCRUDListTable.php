<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Controllers\AdminReportsController;
use SolidAffiliate\Lib\AdminReportsHelper;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Visit;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\ListTables\SharedListTableFunctions;
use SolidAffiliate\Lib\ListTables\SolidWPListTable;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\VO\ListTableConfigs;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Models\AffiliateCustomerLink;
use SolidAffiliate\Views\Shared\SolidTooltipView;

/**
 * @psalm-import-type ResourceDescription from \SolidAffiliate\Lib\SolidAdminCRUD
 */
class SolidCRUDListTable extends SolidWPListTable
{
    /** @var ResourceDescription */
    public $description;

    /** @var ListTableConfigs */
    public $configs;

    /**
     * @param ResourceDescription $description
     */
    function __construct($description)
    {
        $this->description = $description;

        // These are to stop PSALM from complaining about "property ... is not defined in constructor"
        $this->items = [];
        $this->_args = [];
        /** @psalm-suppress PropertyTypeCoercion */
        $this->screen = (object)[];
        $this->_column_headers = [];

        $this->configs = self::configs_from_description($description);


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
     * @param ResourceDescription $description
     * @return ListTableConfigs
     */
    public static function configs_from_description($description)
    {
        $model = $description['model'];

        $configs = [
            'model_class' => $model,
            'singular' => $description['model_singular'],
            'plural' => $description['model_plural'],
            'schema' => $description['schema'],
            'page_key' => $description['page_key'],
            'bulk_actions' => array( // TODO
                'Delete'  => __('Delete', 'solid-affiliate'),
            ),
            "search_by_key" => $description['search_by_key'],
            "search_button_text_override" => __(Formatters::humanize($description['search_by_key']), 'solid-affiliate'), 
            "search_function" =>
            /**
             * @param array $args
             * @param array<string> $search_args
             * @param array<array<string>> $filter_args
             * @param array<int> $where_ids
             * 
             * @return array<object>
             */
            function ($args, $search_args, $filter_args, $where_ids) use ($model) {
                return $model::search($args, $search_args, $filter_args, $where_ids);
            },
            "count_function" =>
            /** @return int */
            function () use ($model) {
                return $model::count(); // TODO psalm
            },
            'hidden_columns_by_default' => [],
            "computed_columns" => [],
            'column_name_overrides' => isset($description['column_name_overrides']) ? $description['column_name_overrides'] : [],
            "custom_css" => "
            ",
        ];

        return new ListTableConfigs($configs);
    }


    // /**
    //  * TODO figure this shit out. 
    //  * 
    //  * @return void
    //  */
    // function add_screen_options()
    // {
    //     $description = $this->$description;

    //     new self($description);
    // }

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
                return (string)$struct['function']($item);
            }
        }

        switch ($column_name) {
            case 'affiliate_id':
                $affiliate_id = (int)$item->affiliate_id;
                return SharedListTableFunctions::affiliate_column($affiliate_id, true);
            case 'created_at':
                return SharedListTableFunctions::created_at_column($item);
            case 'expires_on_unix_seconds':
                // unix timstamp to date
                $unix_seconds = (int)$item->expires_on_unix_seconds;
                if ($unix_seconds == 0) {
                    return 'Never';
                } elseif ($unix_seconds < time()) {
                    return 'Expired';
                }
                else {
                    return date('Y-m-d', $unix_seconds);
                }
            default:
                if (isset($item->$column_name)) {
                    $val = Validators::str($item->$column_name);
                    return empty($val) ? '-' : $val;
                } else {
                    return '-';
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
        $item_id = (int)$item->id;

        $actions           = array();
        $actions['edit']   = sprintf('<a href="%s" data-id="%d" title="%s">%s</a>', URLs::edit($this->configs()->model_class, $item_id), $item_id, __('Edit this item', 'solid-affiliate'), __('Edit', 'solid-affiliate'));
        $actions['delete'] = sprintf('<a href="%s" class="submitdelete" data-id="%d" title="%s">%s</a>', URLs::delete($this->configs()->model_class, false, $item_id), $item_id, __('Delete this item', 'solid-affiliate'), __('Delete', 'solid-affiliate'));

        return sprintf('<a href="%1$s"><strong>%2$s</strong></a> %3$s', URLs::edit($this->configs()->model_class, $item_id), $item_id, $this->row_actions($actions));
    }

    /**
     * Get sortable columns
     *
     * @return array
     */
    function get_sortable_columns()
    {
        $schema = $this->configs->schema;
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
        $actions = $this->configs->bulk_actions;
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
        $page_key = $this->configs->page_key;
        $base_link      = URLs::admin_path($page_key);

        $current        = isset($_GET['status']) ? (string)$_GET['status'] : '';

        $total_count    = '&nbsp;<span id="count-all" class="count">(' . $this->configs()->model_class::count() . ')</span>';

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
        $search_by_key = $this->configs->search_by_key;
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

        $search_function = $this->configs->search_function;
        $filter_args = [];

        $this->items = $search_function($args, $search_args, $filter_args, []);

        $count_function = $this->configs->count_function;
        $total_items = $count_function();

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ));
    }
}
