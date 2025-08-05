<?php

namespace SolidAffiliate\Lib\ListTables;

use SolidAffiliate\Addons\StoreCredit\StoreCreditTransactionsListTable;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\Tags;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\VO\ListTableConfigs;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateGroup;
use SolidAffiliate\Models\StoreCreditTransaction;

class SharedListTableFunctions
{
    /**
     * Undocumented function
     *
     * @return string
     */
    public static function render_affiliate_id_filter()
    {
        $entries = [
            'affiliate_id' => new SchemaEntry([
                'type' => 'bigint',
                'length' => 20,
                'required' => false,
                'display_name' => __('Affiliate ID', 'solid-affiliate'),
                'show_on_new_form' => true,
                'form_input_description' => __('Affiliate ID for filter', 'solid-affiliate'),
                'form_input_type_override' => 'affiliate_select',
            ])
        ];

        $schema = new Schema(['entries' => $entries]);

        $affiliate_id = isset($_GET['affiliate_id']) ? (int)$_GET['affiliate_id'] : null;
        $affiliate_id = empty($affiliate_id) ? null : $affiliate_id;

        $item = (object)[
            'affiliate_id' => $affiliate_id
        ];

        $form = FormBuilder::build_form($schema, 'new', $item, true, true, true);
        $filter_btn_text = __('Filter', 'solid-affiliate');
        $out = "
         <div style='display: inline-block;'>
         {$form}
         <div class='table-actions'>
         <input type='submit' class='button action filter-table-button' value='{$filter_btn_text}'>
         </div>
         </div>
        ";

        return $out;
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public static function render_affiliate_group_id_filter()
    {
        $entries = [
            'affiliate_group_id' => new SchemaEntry([
                'type' => 'bigint',
                'is_enum'                => true,
                'enum_options'           => [AffiliateGroup::class, 'affiliate_groups_list'],
                'length' => 20,
                'required' => false,
                'display_name' => __('Group ID', 'solid-affiliate'),
                'show_on_new_form' => true,
                'form_input_description' => __('Affiliate Group ID for filter', 'solid-affiliate')
            ]),
            'tag' => new SchemaEntry([
                'type' => 'varchar',
                'is_enum'                => true,
                'enum_options'           => [Tags::class, 'tags_enum_options'],
                'length' => 20,
                'required' => false,
                'display_name' => __('Tag', 'solid-affiliate'),
                'show_on_new_form' => true,
                'form_input_description' => __('Affiliate Tag for filter', 'solid-affiliate')
            ]),
        ];

        $schema = new Schema(['entries' => $entries]);

        $affiliate_group_id = isset($_GET['affiliate_group_id']) ? (int)$_GET['affiliate_group_id'] : null;
        $affiliate_group_id = empty($affiliate_group_id) ? null : $affiliate_group_id;
        $tag = isset($_GET['tag']) ? (string)$_GET['tag'] : null;

        $item = (object)[
            'affiliate_group_id' => $affiliate_group_id,
            'tag' => $tag
        ];

        $form = FormBuilder::build_form($schema, 'new', $item, true, true, true);
        $filter_btn_text = __('Filter', 'solid-affiliate');
        $out = "
        <div style='display:inline-block'>
            {$form}
            <div class='table-actions'>
            <input type='submit' class='button action filter-table-button' value='{$filter_btn_text}'>
            </div>
        </div>
        ";

        return $out;
    }

    /**
     * Renders the column content for an Affiliate column. For example, on the Referrals table we have a foreign_key of affiliate_id.
     *
     * @param int $affiliate_id
     * @param bool $render_actions
     * 
     * @return string
     */
    public static function affiliate_column($affiliate_id, $render_actions = true)
    {
        $affiliate = Affiliate::find($affiliate_id);
        $user_id = is_null($affiliate) ? 0 : (int)$affiliate->user_id;

        $actions           = array();
        $actions['edit']   = sprintf('<a href="%s" data-id="%d" title="%s">%s</a>', URLs::edit(Affiliate::class, $affiliate_id), $affiliate_id, __('Edit this item', 'solid-affiliate'), __('Edit', 'solid-affiliate'));
        $actions['admin_portal_preview'] = sprintf('<a href="%s" class="sld-preview-portal" data-id="%d" title="%s">%s</a>', URLs::admin_portal_preview_path($affiliate_id), $affiliate_id, __('Preview Portal', 'solid-affiliate'), __('Preview Portal', 'solid-affiliate'));
        $actions['edit_user']   = sprintf('<a href="%s" data-id="%d" title="%s">%s</a>', get_edit_user_link($user_id), $user_id, __('Edit User', 'solid-affiliate'), __('Edit User', 'solid-affiliate'));
        $actions['delete'] = sprintf('<a href="%s" class="submitdelete" data-id="%d" title="%s">%s</a>', URLs::delete(Affiliate::class, false, $affiliate_id), $affiliate_id, __('Delete this item', 'solid-affiliate'), __('Delete', 'solid-affiliate'));


        $user = get_userdata($user_id);
        $username = $user ? $user->user_nicename : __('User Not Found', 'solid-affiliate');
        $user_email = $user ? $user->user_email : __('User Not Found', 'solid-affiliate');

        $actions_section = $render_actions ? self::row_actions($actions) : '';

        return sprintf('<a href="%1$s"><strong>%3$s</strong></a> <span class="sld-affiliate-id">#%2$s</span> <br/> <small>%5$s</small> %4$s', URLs::edit(Affiliate::class, $affiliate_id, true), $affiliate_id, $username, $actions_section, $user_email);
    }

    /**
     * Returns a localized datetime string for the created_at timestamp.
     * If the localization fails, it defaults to a simple date.
     *
     * @param object $item
     *
     * @return string
     */
    public static function created_at_column($item)
    {
        $timestamp = (string)$item->created_at;

        if (empty($timestamp)) {
            return '-';
        } else {
            return Formatters::localized_datetime($timestamp);
        }
    }

    /**
     * Returns the string for a WooCommerce product column.
     *
     * @param int $woocommerce_product_id
     * @return string
     */
    public static function woocommerce_product_column($woocommerce_product_id)
    {
        return WooCommerceIntegration::formatted_product_link($woocommerce_product_id);
    }

    /**
     * Returns a formatted Commission Rate string, suitable for list table columns.
     *
     * @param string|float $commission_rate
     * @param 'site_default'|'flat'|'percentage' $commission_type
     * @param string $subtext
     * 
     * @return string
     */
    public static function commission_rate_column($commission_rate, $commission_type, $subtext = 'Affiliate')
    {
        $subtext = __($subtext, 'solid-affiliate');

        $is_from_site_default = $commission_type === 'site_default';

        if ($commission_type === 'site_default') {
            $site_default_settings = Settings::get_many([Settings::KEY_REFERRAL_RATE, Settings::KEY_REFERRAL_RATE_TYPE]);
            $commission_rate = (string)$site_default_settings[Settings::KEY_REFERRAL_RATE];
            /** @var 'flat'|'percentage' $commission_type */
            $commission_type = (string)$site_default_settings[Settings::KEY_REFERRAL_RATE_TYPE];
        }

        $formatted_rate = Formatters::commission_rate($commission_rate, $commission_type);

        if ($commission_type === 'flat') {
            return $formatted_rate . ' ' . __('flat', 'solid-affiliate') . "<br> <small>" . sprintf(__('from %1$s settings', 'solid-affiliate'), $subtext) . "</small>";
        }

        if ($is_from_site_default) {
            return $formatted_rate . "<br> <small>" . __('from site default', 'solid-affiliate') . "</small>";
        }

        return $formatted_rate . "<br> <small>" . sprintf(__('from %1$s settings', 'solid-affiliate'), $subtext) . "</small>";
    }


    /**
     * @param mixed $customer_id
     * 
     * @return string
     */
    public static function customer_id_column($customer_id)
    {
        $customer_id = (int)$customer_id;
        if (empty($customer_id)) {
            return __('guest', 'solid-affiliate');
        } else {
            return (string)$customer_id;
        }
    }

    /** 
     * Generates the required HTML for a list of row action links.
     * 
     * Copy Pasted from WP core WP_List_table
     * 
     * @since 3.1.0
     *
     * @param string[] $actions        An array of action links.
     * @param bool     $always_visible Whether the actions should be always visible.
     * @return string The HTML for the row actions.
     */
    private static function row_actions($actions, $always_visible = false)
    {
        $action_count = count($actions);

        if (!$action_count) {
            return '';
        }

        $mode = (string)get_user_setting('posts_list_mode', 'list');

        if ('excerpt' === $mode) {
            $always_visible = true;
        }

        $out = '<div class="' . ($always_visible ? 'row-actions visible' : 'row-actions') . '"><div>';

        foreach ($actions as $action => $link) {
            $out .= "<span class='$action'>$link</span>";
        }

        $out .= '</div></div>';

        $out .= '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __('Show more details', 'solid-affiliate') . '</span></button>';

        return $out;
    }

    /**
     * We hook into the default_hidden_columns filter with this handler.
     * 
     * The point is to make the 'hidden_columns_by_default' ListTableConfigs option within out List Tables work.
     *
     * @param array $hidden
     * @param \WP_Screen $screen
     * 
     * @return array
     */
    public static function handle_default_hidden_columns($hidden, $screen)
    {
        // This Prefix is derived from the Plugin Name docblock in plugin.php 
        // $PAGE_PREFIX = 'solid-affiliate_page_';

        // Bail early to save unnecessary computation.
        if (strpos($screen->id, 'solid-affiliate') === false) {
            return $hidden;
        }

        $table_configs = [
            AffiliatesListTable::build_configs(),
            AffiliateGroupsListTable::configs(),
            AffiliateProductRatesListTable::configs(),
            ReferralsListTable::configs(),
            PayoutsListTable::configs(),
            VisitsListTable::configs(),
            CreativesListTable::configs(),
            BulkPayoutsListTable::configs(),
            StoreCreditTransactionsListTable::configs(),
        ];

        foreach ($table_configs as $configs) {
            if (strpos($screen->id, $configs->page_key) !== false) {
                $hidden = array_merge($hidden, $configs->hidden_columns_by_default);
            } else {
                continue;
            }
        }

        return $hidden;
    }

    /**
     * Undocumented function
     *
     * @param array $computed_column_configs
     * @return array<string, string>
     */
    public static function create_computed_columns_column_names($computed_column_configs)
    {
        $computed_columns = array_reduce(
            $computed_column_configs,
            /**
             * @param array<string, string> $all
             * @param array{column_name: string, function: Closure(Affiliate):mixed} $computed_column_struct
             */
            function ($all, $computed_column_struct) {
                $name = $computed_column_struct['column_name'];
                $all[$name] = $name;
                return $all;
            },
            []
        );

        return $computed_columns;
    }

    /**
     * TODO description
     * 
     * @param ListTableConfigs $configs
     *
     * @return array<string, string>
     */
    public static function shared_get_columns($configs)
    {
        if ($configs->disable_checkbox_column) {
            $default = array();
        } else {
            $default = array(
                'cb'           => '<input type="checkbox" />',
            );
        }

        $schema = $configs->schema;

        $columns = SchemaFunctions::columns_for_list_table_from_schema($schema);
        $computed_columns = SharedListTableFunctions::create_computed_columns_column_names($configs->computed_columns);

        $columns = array_merge($default, $columns, $computed_columns);

        // This is to ensure we don't add any extra columns by providing column name
        // overrides for columns that don't actually exist.
        $column_name_overrides = array_intersect_key($configs->column_name_overrides, $columns);

        $columns = array_merge($columns, $column_name_overrides);

        return $columns;
    }
}
