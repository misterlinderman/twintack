<?php

namespace SolidAffiliate\Lib\CustomAffiliateSlugs;

use SolidAffiliate\Lib\FF;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\Links;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateMeta;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Visit;
use SolidAffiliate\Views\Shared\SimpleTableView;
use SolidAffiliate\Views\Shared\SolidTooltipView;


class CustomSlugViewFunctions
{
    /**
     * HTML string for a tooltip explaining what happens if an Affiliate has more than the per affiliate custom slug limit.
     *
     * @return string
     */
    public static function slug_limit_rules_tooltip()
    {
        return SolidTooltipView::render_pretty(
            __('Limit Rules', 'solid-affiliate'),
            __('What happens when an Affiliate has more custom slugs than is allowed by the <strong>Per Affiliate Custom Slug Limit setting</strong>?', 'solid-affiliate'),
            __('A site admin can change the per-affiliate custom slug limit, which may result in a limit that is less than the number of custom slugs an affiliate has.', 'solid-affiliate') . ' ' .
                __('If this occurs, then <strong>Solid Affiliate will not automatically delete any custom slugs</strong> because this would break existing affiliate links without the affiliate knowing.', 'solid-affiliate') . ' ' .
                __('Instead, Solid Affiliate will not allow any new custom slugs to be created for the Affiliate.', 'solid-affiliate') . ' ' .
                __('If any new custom slugs are to be added, then either the site admin or the affiliate will need to delete custom slugs until the affiliate is under the limit.', 'solid-affiliate'),
            self::link_to_docs_note()
        );
    }

    /**
     * A link to the Affiliate Custom Slugs documentation.
     *
     * @return string
     */
    public static function link_to_docs_note()
    {
        return Links::render(AffiliateCustomSlugBase::DOCS_URL, __('Learn More', 'solid-affiliate'), '_blank');
    }

    /**
     * Return to the AFFILIATE_EDIT_AFTER_FORM_FILTER filter to be rendered on the Affiliate Edit page.
     * Displays the relevant forms, table data, and settings to the admin.
     *
     * @param array<string> $panels
     * @param Affiliate|null $affiliate
     *
     * @return array<string>
     */
    public static function render_custom_slugs_for_affiliate_edit($panels, $affiliate)
    {
        if (is_null($affiliate)) {
            return $panels;
        }

        // Prepare necessary data
        $ref_var = Validators::str(Settings::get(Settings::KEY_REFERRAL_VARIABLE));
        $example_query_str = URLs::url_referral_query_string($ref_var, 'slug');
        $slug_metas = CustomSlugDbFunctions::slug_metas_for_affiliate($affiliate->id);
        $acs_table_rows = self::_single_affiliate_custom_slugs_table_rows($affiliate, $slug_metas);
        $acs_table_headers = [
            __('Slug', 'solid-affiliate'),
            self::_example_url_table_header($ref_var, $example_query_str),
            self::_total_visit_count_header($ref_var, $example_query_str),
            self::_total_referral_count_header(),
            __('Actions', 'solid-affiliate')
        ];

        // Header Section with collapsible functionality
        $header = '<div class="sld_collapsible-section sld_custom-slugs-panel">
    <div class="sld_form-head has-icon sld_collapsible-header">
        <div class="icon-wrapper" style="display: flex; align-items: center;">
            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M22.667 29.3334V26.6667M12.0003 20.0001L20.0003 12.0001M14.667 8.00016L15.2843 7.2855C16.5347 6.03527 18.2306 5.33296 19.9988 5.33309C21.767 5.33321 23.4628 6.03576 24.713 7.28616C25.9632 8.53657 26.6655 10.2324 26.6654 12.0006C26.6653 13.7689 25.9627 15.4646 24.7123 16.7148L24.0003 17.3335M17.3338 24.0001L16.8045 24.7121C15.5395 25.963 13.8322 26.6646 12.0532 26.6646C10.2741 26.6646 8.56685 25.963 7.30185 24.7121C6.67832 24.0955 6.18331 23.3614 5.84546 22.5523C5.50762 21.7431 5.33366 20.8749 5.33366 19.9981C5.33366 19.1212 5.50762 18.2531 5.84546 17.4439C6.18331 16.6347 6.67832 15.9006 7.30185 15.2841L8.00051 14.6667M26.667 22.6667H29.3337M2.66699 9.33341H5.33366M9.33366 2.66675V5.33341" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <div class="text-wrapper" style="margin-left: 10px;">
            <h2>Custom URL Slugs ' . (FF::is_off('custom_affiliate_slugs') ? FF::pill() : '') . '</h2>
            <p>' . __('Manage custom URL slugs for this affiliate', 'solid-affiliate') . '</p>
        </div>
        <span class="toggle-icon">
            <svg class="plus-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 4V20M20 12H4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <svg class="minus-icon" style="display:none;" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 12H4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
    </div>

    <div class="sld_collapsible-content" style="display:none;">';

        // Prepare limit and usage information
        $limit = AffiliateCustomSlugBase::get_per_affiliate_slug_limit();
        $usage_limit = self::_per_affiliate_usage_limit_note(count($slug_metas), $limit);

        // Usage Limit Note
        $usage_note = '<div style="margin-bottom:20px">' .
            __('Limit: ' . $limit . ' custom slugs per affiliate ', 'solid-affiliate') .
            $usage_limit .
            '</div>';

        // Table Section
        $table_section = SimpleTableView::render(
            $acs_table_headers,
            $acs_table_rows,
            null,
            __('This Affiliate does not have any custom URL slugs. Use the form below to create a new custom slug.', 'solid-affiliate') . ' ' . self::link_to_docs_note(),
            "wp-list-table widefat fixed striped payouts sld-acs"
        );

        // New Slug Form
        $new_slug_form = self::_new_custom_slug_form($affiliate->id);

        // Combine all sections
        $acs_panel = $header .
            $usage_note .
            $table_section .
            $new_slug_form .
            '</div></div>';

        array_unshift($panels, $acs_panel);
        return $panels;
    }


    /**
     * Returns the HTML string with the relevant forms, table data, and settings to the Affiliate in the Affiliate Links portal tab based on their permissions.
     *
     * @param Affiliate $affiliate
     *
     * @return string
     */
    public static function custom_slugs_section_for_affiliate_portal($affiliate)
    {
        $ref_var = Validators::str(Settings::get(Settings::KEY_REFERRAL_VARIABLE));
        $example_query_str = URLs::url_referral_query_string($ref_var, 'slug');
        $auto_create = AffiliateCustomSlugBase::get_should_auto_create_default_username_custom_slug();
        $can_edit = AffiliateCustomSlugBase::can_edit_custom_slugs($affiliate);
        $slug_metas = CustomSlugDbFunctions::slug_metas_for_affiliate($affiliate->id);
        $acs_table_rows = self::_single_affiliate_custom_slugs_table_rows($affiliate, $slug_metas);
        $acs_table_headers = [__('Slug', 'solid-affiliate'), self::_example_url_table_header($ref_var, $example_query_str), self::_total_visit_count_header($ref_var, $example_query_str), self::_total_referral_count_header(), __('Actions', 'solid-affiliate')];

        if ($can_edit) {
            $empty_state_cta = __('Use the form below to create a new custom slug.', 'solid-affiliate');
        } else {
            $empty_state_cta = __('Ask the site admin to create a custom slug for you. Or ask to granted permission to create your own from this tab in the Affiliate Portal.', 'solid-affiliate');
        }
        $acs_header = '<h2>' . __('Custom slugs', 'solid-affiliate') . '</h2>';
        $acs_table = SimpleTableView::render($acs_table_headers, $acs_table_rows, null, __('You do not have any custom slugs to use in your Affiliate Links.', 'solid-affiliate') . ' ' . $empty_state_cta . ' ' . self::link_to_docs_note(), 'sld-ap-table');
        $acs_sub_header = '<p class="sld-ap-description">' . __('If the site admin has auto create custom slugs on, then upon being approved you will be given a custom slug based on your username.', 'solid-affiliate') . ' ' . __('Currently this setting is turned', 'solid-affiliate') . ': ' . ($auto_create ? __('ON', 'solid-affiliate') : __('OFF', 'solid-affiliate')) . '</p>';
        $limit = AffiliateCustomSlugBase::get_per_affiliate_slug_limit();
        $usage_limit = self::_per_affiliate_usage_limit_note(count($slug_metas), $limit);
        $section = self::_js_confirm_script() . $acs_header . $acs_sub_header . $usage_limit . $acs_table;

        if ($can_edit) {
            $acs_new_form = self::_new_custom_slug_form($affiliate->id, 'sld-ap-title');
            $section = $section . '<br />' . $acs_new_form;
        }

        return Validators::str(apply_filters("solid_affiliate/affiliate_portal/custom_slugs_section", $section));
    }

    /**
     * Builds the table rows for a single Affiliate's custom slugs table to be rendered in the views.
     *
     * @param Affiliate $affiliate
     * @param array<AffiliateMeta> $slug_metas
     *
     * @return array<array{0: string, 1: string, 2: int, 3: int, 4: string}>
     */
    private static function _single_affiliate_custom_slugs_table_rows($affiliate, $slug_metas = [])
    {
        if (empty($slug_metas)) {
            $slug_metas = CustomSlugDbFunctions::slug_metas_for_affiliate($affiliate->id);
        }

        $can_edit = AffiliateCustomSlugBase::can_edit_custom_slugs($affiliate) || is_admin();
        $base_url = URLs::default_affiliate_link_base_url();

        return array_map(function ($meta) use ($affiliate, $can_edit, $base_url) {
            $slug = $meta->meta_value;
            $example_url = URLs::default_affiliate_link($affiliate, $slug, $base_url);
            $visit_count_tuple = self::_visit_count_for_slug($affiliate->id, $slug);
            $referral_count = self::_referral_count_for_custom_slug($affiliate->id, $visit_count_tuple[1]);

            if ($can_edit) {
                return [$slug, $example_url, $visit_count_tuple[0], $referral_count, self::_delete_action_html($meta)];
            } else {
                return [$slug, $example_url, $visit_count_tuple[0], $referral_count, self::_visit_action_html($example_url)];
            }
        }, $slug_metas);
    }

    /**
     * Returns the HTML string for the create a new custom slug form.
     *
     * @param int $affiliate_id
     * @param string $title_css_class
     *
     * @return string
     */
    private static function _new_custom_slug_form($affiliate_id, $title_css_class = '')
    {
        $form_values = [
            AffiliateMeta::SCHEMA_KEY_AFFILIATE_ID => $affiliate_id,
            AffiliateMeta::SCHEMA_KEY_META_KEY => AffiliateMeta::META_KEY_CUSTOM_AFFILIATE_SLUG
        ];
        $form_id = 'sld_affiliate_edit_custom_slug_new';
        $btn_txt = __('Create', 'solid-affiliate');
        $btn_disabled = FF::is_off('custom_affiliate_slugs');
        ob_start();
?>
        <div class="add-new-slug">
            <h3 class="<?php echo $title_css_class ?>"><?php echo __('Add a new custom slug', 'solid-affiliate') ?></h3>
            <form action="" method="post" class="sld-ap-form" id="<?php echo $form_id ?>">
                <?php echo (FormBuilder::build_form(
                    AffiliateCustomSlugBase::slug_schema(),
                    'new',
                    (object)$form_values,
                    false,
                    false,
                    false,
                )); ?>
                <?php wp_nonce_field(AffiliateCustomSlugBase::NONCE_NEW_CUSTOM_SLUG_FOR_AFFILIATE) ?>
                <input <?php echo $btn_disabled ? 'disabled' : ''; ?> type="submit" name="<?php echo (AffiliateCustomSlugBase::POST_PARAM_NEW_CUSTOM_SLUG_FOR_AFFILIATE) ?>" id="<?php echo (AffiliateCustomSlugBase::POST_PARAM_NEW_CUSTOM_SLUG_FOR_AFFILIATE) ?>" class="button button-primary" value="<?php echo ($btn_txt) ?>">
            </form>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Returns the HTMl string for the checkbox form toggling if a single affiliate can create and delete custom slugs.
     *
     * @param AffiliateMeta $can_edit_meta
     *
     * @return string
     */
    private static function _affiliate_can_edit_slugs_form($can_edit_meta)
    {
        $form_values = [
            AffiliateMeta::SCHEMA_KEY_AFFILIATE_ID => $can_edit_meta->affiliate_id,
            AffiliateMeta::SCHEMA_KEY_META_KEY => AffiliateMeta::META_KEY_CUSTOM_AFFILIATE_SLUG_IS_EDITABLE_BY_AFFILIATE,
            AffiliateMeta::SCHEMA_KEY_META_VALUE => $can_edit_meta->meta_value,
            AffiliateMeta::PRIMARY_KEY => $can_edit_meta->id
        ];
        $btn_txt = __('Submit', 'solid-affiliate');
        ob_start();
    ?>
        <form action="" method="post" id="<?php echo AffiliateCustomSlugBase::CAN_EDIT_CUSTOM_SLUGS_FORM_ID ?>">
            <?php echo (FormBuilder::build_form(
                AffiliateCustomSlugBase::affiliate_can_edit_schema(),
                'edit',
                (object)$form_values,
                false,
                false,
                false,
            )); ?>
            <?php wp_nonce_field(AffiliateCustomSlugBase::NONCE_AFFILIATE_CAN_EDIT_SLUGS) ?>
            <input type="submit" style="display: none;" name="<?php echo (AffiliateCustomSlugBase::POST_PARAM_AFFILIATE_CAN_EDIT_CUSTOM_SLUGS) ?>" id="<?php echo (AffiliateCustomSlugBase::POST_PARAM_AFFILIATE_CAN_EDIT_CUSTOM_SLUGS) ?>" value="<?php echo ($btn_txt) ?>">
        </form>
    <?php
        return ob_get_clean();
    }

    /**
     * The total number of visits for a give custom slug. Note the limitation comments below.
     *
     * @global \wpdb $wpdb
     *
     * @param int $affiliate_id
     * @param string $slug
     *
     * @return array{0: int, 1: array<int>}
     */
    private static function _visit_count_for_slug($affiliate_id, $slug)
    # TODO: Do we want to scope the query to the current referral var? If not then:
    # -> Visits will be counted if the affiliate is given a visit via their ID but the custom slug is also included in a different query param, e.g. http://latestdev.local/?action=slugme&sld=58
    # -> Visits will be given if they go to a page that has a path of =<customslug> in it and the visit is counted via their ID, e.g. http://latestdev.local/=slugme/?sld=58
    {
        global $wpdb;
        $table = $wpdb->prefix . Visit::TABLE;
        $slug_matcher_end_of_url = '%' . $wpdb->esc_like('=' . $slug);
        $slug_matcher_before_another_query_param = '%' . $wpdb->esc_like('=' . $slug . '&') . '%';
        $ids = $wpdb->get_col(
            Validators::str($wpdb->prepare(
                "SELECT id FROM {$table} WHERE affiliate_id = {$affiliate_id} AND (landing_url LIKE %s OR landing_url LIKE %s)",
                $slug_matcher_end_of_url,
                $slug_matcher_before_another_query_param
            ))
        );
        return [count($ids), Validators::array_of_int($ids)];
    }

    /**
     * The HTML string for the Actions column if the user does not have permissions to delete. Takes the user to the default affiliate link URL.
     *
     * @param string $url
     *
     * @return string
     */
    private static function _visit_action_html($url)
    {
        return "<a class='sld-visit-page' href='{$url}' target='_blank'>" . __('Visit Example URL', 'solid-affiliate') . "</a>";
    }

    /**
     * The HTML string for the delete a custom slug form button.
     *
     * @param AffiliateMeta $meta
     *
     * @return string
     */
    private static function _delete_action_html($meta)
    {
        $form_id = 'sld_affiliate_custom_slug_delete_' . $meta->meta_value . '_form';
        $tt = SolidTooltipView::render_pretty(
            __('What does it mean to Delete a Custom Slug?', 'solid-affiliate'),
            __('When a custom slug is deleted it can no longer be used to track Visits and Referrals, but it will not remove any other data.', 'solid-affiliate'),
            __('Solid Affiliate prioritizes data integrity.', 'solid-affiliate') . ' <strong>' . __('If you delete a custom slug, none of your previous Visits or Referrals will be deleted, so all tracking and commission data will exist as it did before deleting the custom slug.', 'solid-affiliate') . '</strong>' . ' ' .
                __('This allows Affiliates and admins to freely delete and create custom slugs for different campaigns, products, etc. without worrying about their affiliate program data being lost.', 'solid-affiliate') . ' ' .
                __('However, deleting a custom slug will break existing Affiliate Links using the custom slug.', 'solid-affiliate'),
            self::link_to_docs_note()
        );
        ob_start();
    ?>
        <form action="" method="post" id="<?php echo $form_id ?>">
            <?php echo FormBuilder::build_hidden_field(AffiliateCustomSlugBase::DELETE_CUSTOM_SLUG_FIELD_PARAM_KEY, $meta->id) ?>
            <?php wp_nonce_field(AffiliateCustomSlugBase::NONCE_DELETE_AFFILIATE_CUSTOM_SLUG) ?>
            <?php submit_button(__('Delete', 'solid-affiliate'), 'small', AffiliateCustomSlugBase::POST_PARAM_DELETE_AFFILIATE_CUSTOM_SLUG, false, ['onclick' => 'return deleteConfirmCustomSlug(event);']); ?>
            <?php echo $tt ?>
        </form>
<?php
        return ob_get_clean();
    }

    /**
     * The table header for the Total Referral Count column.
     *
     * @return string
     */
    private static function _total_referral_count_header()
    {
        return __('Total Referral Count', 'solid-affiliate') . SolidTooltipView::render_pretty(
            __('Total Referral Count using this Custom Slug', 'solid-affiliate'),
            '',
            __('This only counts Referrals for this affiliate that were tracked because a Visit using this custom slug was attributed to the Affiliate.', 'solid-affiliate'),
            self::link_to_docs_note()
        );
    }

    /**
     * The table header for the Total Visit Count column.
     *
     * @param string $param_key
     * @param string $query_str
     *
     * @return string
     */
    private static function _total_visit_count_header($param_key, $query_str)
    {
        return __('Total Visit Count', 'solid-affiliate') . SolidTooltipView::render_pretty(
            __('Total Visit Count using this Custom Slug', 'solid-affiliate'),
            '',
            __("This only counts Visits that were tracked because this custom slug was included as a query param using the referral variable ({$param_key}) configured in General Settings. Example: {$query_str}", 'solid-affiliate')
                . ' ' . __('This is helpful when determining whether a custom slug URL is providing more traffic than other custom slugs, ID based slugs, Affiliate Landing Pages, etc.', 'solid-affiliate'),
            self::link_to_docs_note()
        );
    }

    /**
     * The table header for the Example URL column.
     *
     * @param string $param_key
     * @param string $query_str
     *
     * @return string
     */
    private static function _example_url_table_header($param_key, $query_str)
    {
        return __('Example URL', 'solid-affiliate') . SolidTooltipView::render_pretty(
            __('Custom Slug Usage', 'solid-affiliate'),
            __('A custom slug can be added as a query param to any URL on your site.', 'solid-affiliate'),
            __("Affiliates can generate a link to anywhere on the site in the Affiliate Portal using this custom slug and the referral variable ({$param_key}) configured in General Settings. Example: {$query_str}", 'solid-affiliate'),
            self::link_to_docs_note()
        );
    }

    /**
     * Queries the referrals table for the count of referrals that are associated to the given $visit_ids.
     *
     * @global \wpdb $wpdb
     *
     * @param int $affiliate_id
     * @param array<int> $visit_ids IDs of the visits attribued to a specific custom slug.
     *
     * @return int
     */
    private static function _referral_count_for_custom_slug($affiliate_id, $visit_ids = [])
    {
        return Referral::referral_count_for_affiliate_visits($affiliate_id, $visit_ids);
    }

    /**
     * The JS string for the confirm function when a user wants to delete a custom slug.
     *
     * @return string
     */
    private static function _js_confirm_script()
    {
        $msg = __('Are you sure you want to delete this custom slug? Deleting it will break existing Affiliate Links using the slug in the URL.', 'solid-affiliate');
        return '
        <script>
            function deleteConfirmCustomSlug(e) {
                return confirm("' . $msg . '");
            }
        </script>';
    }

    /**
     * The subheader telling the user how many custom slugs are in use compared to the per affiliate limit.
     *
     * @param int $slug_count
     * @param int $limit
     *
     * @return string
     */
    private static function _per_affiliate_usage_limit_note($slug_count, $limit)
    {
        if ($slug_count > $limit) {
            $used_css_color = '#e30000';
        } elseif ($slug_count === $limit) {
            $used_css_color = '#b35500';
        } else {
            $used_css_color = '#3c7d3c';
        }

        $tt = self::slug_limit_rules_tooltip();
        $note = '<div class="slugs-counter">' .
            '<span style="color: ' . $used_css_color . '; font-weight : 400;">' . $slug_count . '</span>' . ' ' .
            '<strong>' . __('out of', 'solid-affiliate') . '</strong>' . ' ' .
            '<span style="color: #c30101; font-weight : 400;">' . $limit . '</span>' . ' ' .
            '<strong>' . __('total used', 'solid-affiliate') . '</strong>' . $tt .
            '</div>';
        return $note;
    }
}
