<?php

namespace SolidAffiliate\Lib\ListTables;

use MaxMind\Db\Reader;
use SolidAffiliate\Lib\AjaxHandler;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\Integrations\WooCommerceSubscriptionsIntegration;
use SolidAffiliate\Lib\VO\ListTableConfigs;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Lib\ListTables\SharedListTableFunctions;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Views\Admin\Referrals\ItemCommissionsModalAndIconView;
use SolidAffiliate\Views\Admin\Referrals\ItemCommissionsView;
use SolidAffiliate\Views\Shared\AjaxButton;

/**
 * List table class
 */
class ReferralsListTable extends SolidWPListTable
{
    /**
     * @return ListTableConfigs
     */
    public static function configs()
    {
        $configs = [
            'model_class' => Referral::class,
            'singular' => Referral::ct_table_options()->data['singular'],
            'plural' => Referral::ct_table_options()->data['plural'],
            'schema' => Referral::schema(),
            'page_key' => Referral::ADMIN_PAGE_KEY,
            'bulk_actions' => array(
                'Reject'  => __('Reject', 'solid-affiliate'),
                'Mark as Paid'  => __('Mark as Paid', 'solid-affiliate'),
                'Mark as Unpaid'  => __('Mark as Unpaid', 'solid-affiliate'),
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
                return Referral::search($args, $search_args, $filter_args, $where_ids);
            },
            "count_function" =>
            /** @return int */
            function () {
                return Referral::count();
            },
            'hidden_columns_by_default' => [
                'customer_id', 'payout_id', 'description', 'referral_type', 'order_source', 'visit_id', 'coupon_id'
            ],
            'column_name_overrides' => [
                'id' => __('Referral', 'solid-affiliate'),
                'affiliate_id' => __('Affiliate', 'solid-affiliate')
            ],
            'default_sort' => [
                'orderby' => 'id',
                'order' => 'desc'
            ],
            "computed_columns" => [
                [
                    'column_name' => __('Actions', 'solid-affiliate'),
                    'function' =>
                    /** 
                     * @param Referral $item 
                     * @return string
                     **/
                    function ($item) {
                        $referral_id = (int)$item->id;
                        $tooltip_text = "Resend the referral notification email to the affiliate.";
                        $btn = AjaxButton::render(AjaxHandler::AJAX_RESEND_REFERRAL_EMAIL_TO_AFFILIATE, '', ['referral_id' => $referral_id], $tooltip_text);
                        return $btn;
                    }
                ],
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
        new ReferralsListTable;
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
                /** @var Referral $item */
                return (string)$struct['function']($item);
            }
        }

        switch ($column_name) {
            case 'order_id':
                $referral_id = (int)$item->id;
                $order_id = (int)$item->$column_name;

                $either_admin_order_url = Referral::get_admin_order_url($referral_id);
                if ($either_admin_order_url->isRight) {
                    $link = "<a href='{$either_admin_order_url->right}'>{$order_id} - " . __('view order', 'solid-affiliate') . "</a>";
                    switch ((string)$item->order_source) {
                        case WooCommerceIntegration::SOURCE:
                            return $link . "<br>" . "<small>WooCommerce</small>";
                        case WooCommerceSubscriptionsIntegration::SOURCE:
                            return $link . "<br>" . "<small>WooCommerce Subscriptions</small>";
                        default:
                            return $link . "<br>" . "<small>{$item->order_source}</small>";
                    }
                } else {
                    return (string)$order_id;
                }
            default:
                return isset($item->$column_name) ? (string)$item->$column_name : '-';
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
        $actions['edit']   = sprintf('<a href="%s" data-id="%d" title="%s">%s</a>', URLs::edit(Referral::class, $item_id), $item_id, __('Edit this item', 'solid-affiliate'), __('Edit', 'solid-affiliate'));
        $actions['delete'] = sprintf('<a href="%s" class="submitdelete" data-id="%d" title="%s">%s</a>', URLs::delete(Referral::class, false, $item_id), $item_id, __('Delete this item', 'solid-affiliate'), __('Delete', 'solid-affiliate'));

        return sprintf('<a href="%1$s"><strong>%2$s</strong></a> %3$s', URLs::edit(Referral::class, $item_id), $item_id, $this->row_actions($actions));
    }

    /**
     * Render the designation name column
     *
     * @param  object  $item
     *
     * @return string
     */
    function column_customer_id($item)
    {
        return SharedListTableFunctions::customer_id_column($item->customer_id);
    }

    /**
     * @param  object  $item
     *
     * @return string
     */
    function column_payout_id($item)
    {
        $payout_id = (int)$item->payout_id;

        if (empty($payout_id)) {
            return '-';
        } else {
            return (string)$payout_id;
        }
    }

    /**
     * @param  object  $item
     *
     * @return string
     */
    function column_referral_source($item)
    {
        $referral_source = (string)$item->referral_source;

        switch ($referral_source) {
            case Referral::SOURCE_VISIT:
                $visit_id = (int)$item->visit_id;
                return "Visit #{$visit_id}";
            case Referral::SOURCE_COUPON:
                $coupon_id = (int)$item->coupon_id;
                return self::_format_coupon_source($coupon_id);
            case Referral::SOURCE_AUTO_REFERRAL:
                return "Auto Referral";
            case Referral::SOURCE_MANUAL:
                return "Manually created by admin ID #{$item->manually_created_by_admin_user_id}";
            case Referral::SOURCE_AFFILIATE_CUSTOMER_LINK:
                return "Affiliate Customer Link #{$item->affiliate_customer_link_id}";
            case Referral::SOURCE_SUBSCRIPTION_RENEWAL:
                return "Subscription Renewal";
            default:
                return $referral_source;
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

        return SharedListTableFunctions::affiliate_column($affiliate_id, true);
    }

    /**
     * @param  object  $item
     *
     * @return string
     */
    function column_coupon_id($item)
    {
        $coupon_id = (int)$item->coupon_id;

        if (empty($coupon_id)) {
            return '-';
        } else {
            return self::_format_coupon_source($coupon_id);
        }
    }

    /**
     * @param object $item 
     * @return string 
     */
    function column_commission_amount($item)
    {
        $maybe_referral = new Referral((array)$item);
        /** @psalm-suppress RedundantCondition */
        if ($maybe_referral instanceof Referral) {
            return Referral::render_commission_tooltip($maybe_referral);
        } else {
            return 'Referral not found';
        }
    }

    /**
     * @param object $item 
     * @return string 
     */
    function column_order_amount($item)
    {
        return Formatters::money($item->order_amount);
    }

    /**
     * @param object $item 
     * @return string 
     */
    function column_referral_type($item)
    {
        return Formatters::humanize(((string)$item->referral_type));
    }

    /**
     * @param object $item 
     * @return string 
     */
    function column_order_source($item)
    {
        return Formatters::humanize(((string)$item->order_source), true, '-');
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
     * Undocumented function
     *
     * @param object $item
     * 
     * @return string
     */
    function column_created_at($item)
    {
        return SharedListTableFunctions::created_at_column($item);
    }

    /**
     * @param Referral $item
     * 
     * @return string
     */
    function column_status($item)
    {
        $status = (string)$item->status;

        if (empty($item->order_refunded_at)) {
            $formatted_status = Formatters::status_with_tooltip($status, Referral::class, 'admin');
        } else {
            $order_refunded_at = (string)$item->order_refunded_at;
            $formatted_order_refunded_at = Formatters::localized_datetime($order_refunded_at);
            $icon = '
                <svg style="vertical-align: text-bottom;" width="15" height="13" viewBox="0 0 15 13" fill="#656565" xmlns="http://www.w3.org/2000/svg">
                    <path d="M0 13L7.34375 0.34375L14.6562 13H0ZM8 11V9.65625H6.65625V11H8ZM8 8.34375V5.65625H6.65625V8.34375H8Z"></path>
                </svg>
            ';
            $formatted_status = Formatters::status_with_tooltip($status, Referral::class, 'admin') . '</br>' . "<small style='color: #656565;'> {$icon}" . __('Order refunded', 'solid-affiliate') . " {$formatted_order_refunded_at}</strong>";
        }

        /////////////////////////////////////////////////
        // Payout method (manual / paypal / store credit)
        $payout = Payout::find($item->payout_id);
        if (is_null($payout)) {
            $payout_render = '';
        } else {
            $payout_method = Formatters::humanize($payout->payout_method);
            $payout_date = Formatters::simple_date($payout->created_at);
            $payout_render = "<br> <small>Payout method:  $payout_method on $payout_date</small>";
        }

        return $formatted_status . $payout_render;
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

        $total_count    = '&nbsp;<span id="count-all" class="count">(' . Referral::count() . ')</span>';
        $rejected_count = '&nbsp;<span id="count-rejected" class="count">(' . Referral::count(['status' => Referral::STATUS_REJECTED]) . ')</span>';
        $paid_count = '&nbsp;<span id="count-paid" class="count">(' . Referral::count(['status' => Referral::STATUS_PAID]) . ')</span>';
        $unpaid_count = '&nbsp;<span id="count-unpaid" class="count">(' . Referral::count(['status' => Referral::STATUS_UNPAID]) . ')</span>';
        $draft_count = '&nbsp;<span id="count-draft" class="count">(' . Referral::count(['status' => Referral::STATUS_DRAFT]) . ')</span>';


        $views = array(
            'all'        => sprintf('<a id="filter-all" href="%s"%s>%s</a>', esc_url(remove_query_arg('status', $base_link)), $current === 'all' || $current == '' ? ' class="current"' : '', __('All', 'solid-affiliate') . $total_count),
            Referral::STATUS_REJECTED => sprintf('<a id="filter-rejected" href="%s"%s>%s</a>', esc_url(add_query_arg('status', Referral::STATUS_REJECTED, $base_link)), $current === Referral::STATUS_REJECTED ? ' class="current"' : '', __('Rejected', 'solid-affiliate') . $rejected_count),
            Referral::STATUS_PAID    => sprintf('<a id="filter-paid" href="%s"%s>%s</a>', esc_url(add_query_arg('status', Referral::STATUS_PAID, $base_link)), $current === Referral::STATUS_PAID ? ' class="current"' : '', __('Paid', 'solid-affiliate') . $paid_count),
            Referral::STATUS_UNPAID    => sprintf('<a id="filter-unpaid" href="%s"%s>%s</a>', esc_url(add_query_arg('status', Referral::STATUS_UNPAID, $base_link)), $current === Referral::STATUS_UNPAID ? ' class="current"' : '', __('Unpaid', 'solid-affiliate') . $unpaid_count),
            Referral::STATUS_DRAFT    => sprintf('<a id="filter-draft" href="%s"%s>%s</a>', esc_url(add_query_arg('status', Referral::STATUS_DRAFT, $base_link)), $current === Referral::STATUS_DRAFT ? ' class="current"' : '', __('Draft', 'solid-affiliate') . $draft_count),
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

        //////////////////////////////////////////////////////////////
        // This is some weird hack we need to do to get the search to work
        // when there are ids in the url. For some reason, if 
        // where_ids is set, then search_args need to be an empty array.
        $maybe_ids = ControllerFunctions::extract_ids_from_get($_GET);
        if ($maybe_ids == [0]) {
            $maybe_ids = [];
        } else {
            $search_args = [];
        }

        $this->items = $search_function($args, $search_args, $filter_args, $maybe_ids);

        $count_function = $this->configs()->count_function;
        $total_items = $count_function();

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ));
    }

    /**
     * Returns a HTML link for a coupon, or a a plain text coupon reference if `eget_edit_post_link` fails.
     *
     * @param int $coupon_id
     *
     * @return string
     */
    private static function _format_coupon_source($coupon_id)
    {
        $coupon = new \WC_Coupon($coupon_id);
        $coupon_code = $coupon->get_code();

        if (empty($coupon_code)) {
            $text = __('Coupon', 'solid-affiliate') . ' #' . $coupon_id;
        } else {
            $text = __('Coupon', 'solid-affiliate') . ' ' . $coupon_code;
        }

        $maybe_url = get_edit_post_link($coupon_id);

        if (is_null($maybe_url)) {
            return $text;
        } else {
            return "<a href={$maybe_url}>{$text}</a>";
        }
    }
}
