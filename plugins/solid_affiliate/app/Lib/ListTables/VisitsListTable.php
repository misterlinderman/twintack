<?php

namespace SolidAffiliate\Lib\ListTables;

use SolidAffiliate\Controllers\VisitsController;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\VO\ListTableConfigs;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Visit;


/**
 * Class VisitsListTable
 */
class VisitsListTable extends SolidWPListTable
{
    /**
     * @return ListTableConfigs
     */
    public static function configs()
    {
        $configs = [
            'model_class' => Visit::class,
            'singular' => Visit::ct_table_options()->data['singular'],
            'plural' => Visit::ct_table_options()->data['plural'],
            'schema' => Visit::schema(),
            'page_key' => Visit::ADMIN_PAGE_KEY,
            'bulk_actions' => array(
                // 'Delete'  => __('Delete', 'solid-affiliate'),
            ),
            "search_by_key" => "landing_url",
            "search_button_text_override" => __("Search by Landing or Referring URL", 'solid-affiliate'),
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
                return Visit::search($args, $search_args, $filter_args, $where_ids);
            },
            "count_function" =>
            /** @return int */
            function () {
                return Visit::count();
            },
            'column_name_overrides' => [
                'id' => __('Visit ID', 'solid-affiliate'),
                'affiliate_id' => __('Affiliate', 'solid-affiliate'),
                'referral_id' => __('Referral (if converted)', 'solid-affiliate'),
                'http_ip' => __("Visitor's IP", 'solid-affiliate'),
                'http_referrer' => __('Referring URL', 'solid-affiliate'),
                'created_at' => __('Time Stamp', 'solid-affiliate')
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
     * @return void
     */
    public static function add_screen_options()
    {
        new VisitsListTable;
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
        return (string)$item->id;
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
    function column_referral_id($item)
    {
        $referral_id = (int)$item->referral_id;
        if (empty($referral_id)) {
            return '-';
        } else {
            return sprintf('<a href="%1$s">Referral %2$s</a>', URLs::edit(Referral::class, $referral_id), $referral_id);
        }
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
     * Set the views
     *
     * @return array
     */
    public function get_views_()
    {
        return [];
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

        // If the user sends back an empty string, then ignore the search query
        if (empty($user_search_key)) {
            $where_ids = [];
        } else {
            $ids = Visit::get_ids_by_columns(['landing_url', 'http_referrer'], $user_search_key);

            // If the query returns no records, then set the $where_ids to [0], so MikesDataModelTrait::search does returns no records.
            // If an [] is passed instead, then it ignores the $where_ids.
            if (empty($ids)) {
                $where_ids = [0];
            } else {
                $where_ids = $ids;
            }
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
        if (isset($_GET['affiliate_id']) && !empty($_GET['affiliate_id'])) {
            $filter_args[] = ['affiliate_id', (string)$_GET['affiliate_id']];
        }

        $this->items = $search_function($args, [], $filter_args, $where_ids);

        $count_function = $this->configs()->count_function;
        $total_items = $count_function();

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ));
    }

    /**
     * Echos the HTML for the delete unconverted visits form for the visits list table.
     * This is a one off form, that will be injected into the list view WPListTableView::render if the $page_key is equal to Visit::ADMIN_PAGE_KEY
     * The form requires the user to confirm the submit via a js confirm box.
     *
     * @return string
     */
    public static function delete_unconverted_visits_form()
    {

        $days_field_id = VisitsController::DELETE_UNCONVERTED_VISITS_INPUT_ID;
        $modal_id = 'modal-unconverted-visits';
        ob_start();
?>
        <script>
            function validateDeleteVisits(days) {
                return confirm(jQuery('#delete-confirm-message').data('message').replace('DAYS_VAR', days));
            }
        </script>
        <div class="modal micromodal-slide visits-delete" id="<?php echo ($modal_id); ?>" aria-hidden="true">
            <div class="modal__overlay" tabindex="-1" data-micromodal-close>
                <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="<?php echo ($modal_id); ?>-title">
                    <header class="modal__header">
                        <h2 class="modal__title" id="<?php echo ($modal_id); ?>-title">
                            <?php _e('Delete Unconverted Visits', 'solid-affiliate') ?>
                        </h2>
                        <button class="modal__close" aria-label="Close modal" data-micromodal-close></button>
                    </header>
                    <main class="modal__content" id="<?php echo ($modal_id); ?>-content">
                        <p class="large"><?php _e('Only submit this form if you need to clear space in your database. Any Visit that is deleted can no longer be used for referral tracking, and will no longer be counted when generating reports, charts, etc. Visits which have already converted into a referral will not be affected.', 'solid-affiliate') ?>
                        </p>
                        <div class="visits-delete-unconverted">
                            <div id='delete-confirm-message' style="display: none;" data-message="<?php echo __("Are you sure you want to delete all unconverted Visits older than DAYS_VAR days?", 'solid-affiliate') ?>"></div>
                            <form action="" method="post" class="sld-form" id="visits-index-delete-unconverted">
                                <div class="sld-form_group">
                                    <label for="days_old"><?php _e('Delete all unconverted Visits that are older than "n" days:', 'solid-affiliate') ?><span class="required"> *</span></label>
                                    <input type="number" id="<?php echo $days_field_id ?>" name="<?php echo $days_field_id ?>" min="1" placeholder="30" required value="30">
                                </div>
                                <?php wp_nonce_field(VisitsController::NONCE_DELETE_UNCONVERTED_VISITS); ?>
                                <input style="vertical-align: baseline" onclick="return validateDeleteVisits(jQuery('input[name=\'visits-delete-unconverted-days\']').val())" type="submit" name="<?php echo VisitsController::POST_PARAM_DELETE_UNCONVERTED_VISITS ?>" id="<?php echo VisitsController::POST_PARAM_DELETE_UNCONVERTED_VISITS ?>" value="<?php _e('Delete Unconverted Visits', 'solid-affiliate') ?>" class="button button-primary">
                            </form>
                        </div>
                    </main>
                    <footer class="modal__footer">
                        <p><?php _e('We recommend backing up your database before deleting unconverted Visit records, as there is no way to restore them once deleted.', 'solid-affiliate')
                            ?>
                        </p>
                    </footer>
                </div>
            </div>
        </div>
<?php
        return ob_get_clean();
    }
}
