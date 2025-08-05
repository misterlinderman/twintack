<?php

namespace SolidAffiliate\Addons\AffiliateLandingPages;

use Illuminate\Contracts\Validation\Validator;
use SolidAffiliate\Addons\AddonInterface;
use SolidAffiliate\Addons\Core;
use SolidAffiliate\Lib\FF;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\ListTables\SharedListTableFunctions;
use SolidAffiliate\Lib\Utils;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VisitTracking;
use SolidAffiliate\Lib\VO\AddonDescription;
use SolidAffiliate\Lib\VO\AffiliatePortalViewInterface;
use SolidAffiliate\Lib\VO\FormFieldArgs;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Visit;
use SolidAffiliate\Views\Admin\Affiliates\EditView;
use SolidAffiliate\Views\AffiliatePortal\AffiliatePortalTabsView;
use SolidAffiliate\Views\AffiliatePortal\DashboardView;
use SolidAffiliate\Views\Shared\SimpleTableView;
use SolidAffiliate\Views\Shared\SolidModalView;

class Addon implements AddonInterface
{
    const ADDON_SLUG = 'affiliate-landing-pages';
    const DOCS_URL = 'https://docs.solidaffiliate.com/affiliate-landing-pages/';
    const ADMIN_PAGE_KEY = 'solid-affiliate-affiliate-landing-pages';

    const PAGE_POST_NONCE = 'solid_affiliate_post_assigned_to_affiliate_nonce';
    const PAGE_META_KEY = '_solid_affiliate_wp_post_affiliate_id';
    const AFFILIATE_ID_QUERY_KEY = 'sld_aff_id';

    const BEFORE_META_BOX_ACTION = 'solid_affiliate/affiliate_landing_pages/before_edit_page_meta_box';

    const ALLOWED_POST_TYPES = ['page', 'post'];

    const AFFILIATE_PORTAL_TAB_KEY = 'landing_pages';

    /**
     * This is the function which gets called when the Addon is loaded.
     * This is the entry point for the Addon.
     *
     * Register your Addon by using the "solid_affiliate/addons/addon_descriptions" filter.
     *
     * Then check if your Addon is enabled, and if so do your stuff.
     *
     * @return void
     */
    public static function register_hooks()
    {
        add_filter("solid_affiliate/addons/addon_descriptions", [self::class, 'register_addon_description']);
        add_action('solid_affiliate/addons/before_settings_form/' . self::ADDON_SLUG, [self::class, 'settings_message']);
    }


    /**
     * This is the function which includes a call to Core::is_addon_enabled() to check if the addon is enabled.
     * 
     * Do not put anything in the register_hooks above besides add_filter and add_action calls. 
     *
     * @return void
     */
    public static function register_if_enabled_hooks()
    {
        if (Core::is_addon_enabled(self::ADDON_SLUG)) {
            add_action('add_meta_boxes_page', [self::class, 'add_meta_box_assign_post_to_affiliate']);
            add_action('add_meta_boxes_post', [self::class, 'add_meta_box_assign_post_to_affiliate']);
            add_filter(VisitTracking::JS_VISIT_TRACKING_AFFILIATE_SLUG_FILTER, [self::class, 'maybe_attribute_to_affiliate'], 10, 2);
            add_action('save_post', [self::class, 'handle_save_post'], 10, 3);
            add_filter(AffiliatePortalTabsView::AFFILIATE_PORTAL_TABS_FILTER, [self::class, 'add_landing_pages_tab']);
            add_filter(AffiliatePortalTabsView::AFFILIATE_PORTAL_TAB_ICON_FILTER, [self::class, 'add_landing_pages_icon'], 10, 2);
            // add_filter(DashboardView::AFFILIATE_PORTAL_RENDER_TAB_FILTER, [self::class, 'maybe_render_landing_pages_affiliate_portal_tab'], 10, 3);
            add_action(DashboardView::AFFILIATE_PORTAL_RENDER_TAB_ACTION, [self::class, 'maybe_render_landing_pages_affiliate_portal_tab'], 10, 1);
            add_filter(EditView::AFFILIATE_EDIT_AFTER_FORM_FILTER, [self::class, 'render_landing_pages_on_affiliate_edit'], 10, 2);

            add_filter('display_post_states', [self::class, 'filter_display_post_states'], 10, 2);
        }
    }

    /**
     * @param string[] $post_states
     * @param \WP_Post $post
     * 
     * @return string[]
     */
    public static function filter_display_post_states($post_states, $post)
    {
        /** @psalm-suppress RedundantConditionGivenDocblockType */
        if (($post instanceof \WP_Post) && (self::get_affiliate_id_from_post_meta($post->ID) != 0)) {
            $post_states['solid_affiliate_landing_page'] = __('Affiliate Landing Page', 'solid-affiliate');
        }
        return $post_states;
    }

    /**
     * The returned AddonDescription is used by \SolidAffiliate\Addons\Core
     * to display the addon in the admin panel, handle the settings, etc.
     *
     * @param AddonDescription[] $addon_descriptions
     * @return AddonDescription[]
     */
    public static function register_addon_description($addon_descriptions)
    {
        $settings_schema = new Schema(["entries" => []]);

        $description = new AddonDescription([
            'slug' => self::ADDON_SLUG,
            'name' => __('Affiliate Landing Pages', 'solid-affiliate'),
            'description' => __("Enable the Affiliate Landing Pages, which allows Pages and Posts to be assigned to an Affiliate.", 'solid-affiliate'),
            'author' => 'Solid Affiliate',
            'graphic_src' => '',
            'settings_schema' => $settings_schema,
            'documentation_url' => self::DOCS_URL,
            'enabled_by_default' => true,
            'ff_disabled' => FF::is_off('affiliate_landing_pages'),
        ]);

        $addon_descriptions[] = $description;
        return $addon_descriptions;
    }

    /**
     * The message to be displayed on the settings page above the settings form.
     *
     * @return void
     */
    public static function settings_message()
    {
        $msg = __('There are no settings for this Addon. Instead it allows you to assign Pages and Posts to an Affiliate. Any traffic to those pages will attribute the Visit to the assigned Affiliate.', 'solid-affiliate')
            . ' ' . __('<strong>Only published pages and posts will be considered active affiliate landing pages</strong>, ones with the status draft or trashed will not result in visits or referrals.', 'solid-affiliate');
        echo "<div style='padding:20px'>" . $msg . "</div>";
    }

    /**
     * Whether or the addon should be tracking visits based on if the addon is enabled and is it a single post of the allowed post types.
     *
     * @return boolean
     */
    public static function should_track()
    {
        return Core::is_addon_enabled(self::ADDON_SLUG) && is_singular(self::ALLOWED_POST_TYPES);
    }

    /**
     * Returns the the Affiliate Landing Pages panel HTML for the Affiliates Edit page along with the existing panels passed in by the filter.
     *
     * @param array<string> $panels
     * @param Affiliate|null $affiliate
     *
     * @return array<string>
     */
    public static function render_landing_pages_on_affiliate_edit($panels, $affiliate)
    {
        if (is_null($affiliate)) {
            return $panels;
        }

        // Header Section with collapsible functionality
        $header = '<div class="sld_collapsible-section">
        <div class="sld_form-head has-icon sld_collapsible-header">
            <div class="icon-wrapper" style="display: flex; align-items: center;">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16 10.6667V21.3334M10.6667 16H21.3334M26.6667 16C26.6667 22.4434 21.4434 27.6667 15 27.6667C8.55659 27.6667 3.33333 22.4434 3.33333 16C3.33333 9.55659 8.55659 4.33333 15 4.33333C21.4434 4.33333 26.6667 9.55659 26.6667 16Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="text-wrapper" style="margin-left: 10px;">
                <h2 id="edit-affiliate-landing_pages">' . __('Affiliate Landing Pages', 'solid-affiliate') . '</h2>  
                <p>' . __('Overview of landing pages assigned to this affiliate', 'solid-affiliate') . '</p>
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

        $affiliate_id = $affiliate->id;
        $landing_pages = self::get_all_pages_for_affiliate($affiliate->id);

        // Prepare table data
        $rows = array_map(function ($post) use ($affiliate_id) {
            $url = get_page_link($post);
            [$visit_count, $visit_ids] = self::_visit_count_for_page($affiliate_id, $post);
            $referral_count = self::_referral_count_for_page($affiliate_id, $visit_ids);
            $a = self::_action_links($post);
            return [$url, $visit_count, $referral_count, $a];
        }, $landing_pages);

        $headers = [
            self::_page_url_header(),
            self::_visit_count_header(),
            self::_referral_count_header(),
            self::_actions_header()
        ];

        // Create new page button
        $new_page_url = add_query_arg(self::AFFILIATE_ID_QUERY_KEY, $affiliate_id, get_admin_url(null, 'post-new.php?post_type=page'));
        $actions_section = '<div class="actions-section">
                                <div class="text-section">
                                    <div class="title">' . __('Manage Landing Pages', 'solid-affiliate') . '</div>
                                            <div class="lead">' . __('Create or manage landing pages for this affiliate.', 'solid-affiliate') . '</div>
                                        </div>
                                        <div class="button-section">' .
            SolidModalView::render(
                '<button class="button">' . __('Create new landing page', 'solid-affiliate') . '</button>',
                __('Create New Affiliate Landing Page', 'solid-affiliate'),
                '<form action="' . esc_url($new_page_url) . '" method="get" target="_blank">' .
                    '<input type="hidden" name="post_type" value="page">' .
                    '<input type="hidden" name="' . self::AFFILIATE_ID_QUERY_KEY . '" value="' . $affiliate_id . '">' .
                    '<p>' . __('You\'ll be redirected to create a new landing page for this affiliate. Look for the "Affiliate Landing Page" box when creating or editing WordPress pages.', 'solid-affiliate') . '</p>' .
                    '<input type="submit" class="button button-primary" value="' . __('Create new page', 'solid-affiliate') . '">' .
                    '</form>'
            ) .
            '</div>
                                    </div>';
        $table_section = SimpleTableView::render(
            $headers,
            $rows,
            null,
            __('No Published Affiliate Landing Pages are assigned to this Affiliate. You can assign any existing Page or Post to an affiliate, or create a new one. ' . self::render_documentation_link(), 'solid-affiliate')
        );

        // Footer section
        $footer_section = '<div class="footer-section" style="margin-top:10px; opacity:.75">' .
            __('This is enabled by the <strong>Affiliate Landing Pages</strong> Addon.', 'solid-affiliate') .
            ' ' . self::render_documentation_link() .
            '</div>';

        $landing_pages_panel = $header . $actions_section . '<div style="margin:20px">' . $table_section . $footer_section . '</div></div></div>';

        array_push($panels, $landing_pages_panel);
        return $panels;
    }

    /**
     * Returns a documentation link.
     *
     * @return string
     */
    public static function render_documentation_link()
    {
        $learn_more_link = '<a href="' . self::DOCS_URL . '" target="_blank">' . __('Learn More', 'solid-affiliate') . '</a>';
        return $learn_more_link;
    }

    /**
     * Returns the HTML for the Affiliate Landing Pages tab in the Affiliate Portal if the $current_tab passed in by the filter is the Affiliate Landing Pages tab.
     *
     * @param AffiliatePortalViewInterface $Itab
     *
     * @return void
     */
    public static function maybe_render_landing_pages_affiliate_portal_tab($Itab)
    {
?>
        <div x-show="current_tab === '<?php echo (self::AFFILIATE_PORTAL_TAB_KEY) ?>'">
            <?php echo  self::_render_affiliate_portal_tab($Itab); ?>
        </div>
    <?php
    }

    /**
     * Queries the posts table for all posts that have been assigned to the specified Affiliate.
     *
     * @param int $affiliate_id
     *
     * @return array<\WP_Post>
     */
    public static function get_all_pages_for_affiliate($affiliate_id)
    {
        $query_args = array(
            'post_type' => self::ALLOWED_POST_TYPES,
            'meta_query' => array(
                array(
                    'key' => self::PAGE_META_KEY,
                    'value' => $affiliate_id,
                )
            )
        );
        $query = new \WP_Query($query_args);
        return Validators::arr_of_wp_post($query->posts);
    }

    /**
     * Checks to see if the homepage of the current site is a landing page.
     *
     * @return boolean
     */
    public static function is_home_page_an_affiliate_landing_page()
    {

        $home_page_id = (int)get_option('page_on_front');
        if ($home_page_id == 0) {
            return false;
        }

        $maybe_affiliate_id = self::get_affiliate_id_from_post_meta($home_page_id);

        if (Affiliate::find($maybe_affiliate_id)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns the HTML that displays all Affiliate Landing Pages in a table for the Comission Rates page.
     *
     * @global \wpdb $wpdb
     *
     * @return string
     */
    public static function table_for_commission_rates_page()
    {
        global $wpdb;
        $table = $wpdb->prefix . Affiliate::TABLE;
        $affiliate_ids = Validators::array_of_int($wpdb->get_col(
            Validators::str($wpdb->prepare(
                "SELECT id FROM {$table} WHERE status = %s",
                Affiliate::APPROVED_STATUS
            ))
        ));

        $query_args = array(
            'post_type' => self::ALLOWED_POST_TYPES,
            'meta_query' => array(
                array(
                    'key' => self::PAGE_META_KEY,
                    'value' => $affiliate_ids,
                )
            )
        );
        $query = new \WP_Query($query_args);
        $posts = Validators::arr_of_wp_post($query->posts);

        $rows = array_map(
            function ($post) {
                $affiliate_id = (int)get_post_meta($post->ID, self::PAGE_META_KEY, true);
                $affiliate_column = SharedListTableFunctions::affiliate_column($affiliate_id, false);
                [$visit_count, $visit_ids] = self::_visit_count_for_page($affiliate_id, $post);
                $referral_count = self::_referral_count_for_page($affiliate_id, $visit_ids);
                $url = get_page_link($post);
                $a = self::_action_links($post);
                return [$url, $affiliate_column, $visit_count, $referral_count, $a];
            },
            $posts
        );
        $headers = [self::_page_url_header(), __('Linked Affiliate', 'solid-affiliate'), self::_visit_count_header(), self::_referral_count_header(), self::_actions_header()];
        return SimpleTableView::render($headers, $rows, null, __('There are no Published Affiliate Landing pages assigned to any Affiliate.', 'solid-affiliate'));
    }

    /**
     * Queries the visits table and returns the total Visit count and list of visit IDs for a specified Affiliate and landing_url.
     *
     * @global \wpdb $wpdb
     *
     * @param int $affiliate_id
     * @param \WP_Post $post
     *
     * @return array{0: int, 1: array<int>}
     */
    private static function _visit_count_for_page($affiliate_id, $post)
    # NOTE: Does not account for landing URLs that have extra query params added to the end of the URL.
    # NOTE: Counts IDs in memory insteaf of doing a single DB call with 2 columns COUNT(*), id
    {
        global $wpdb;
        $table = $wpdb->prefix . Visit::TABLE;
        $page = get_page_link($post);
        $perm = get_permalink($post);
        $ids = $wpdb->get_col(
            Validators::str($wpdb->prepare(
                "SELECT id FROM {$table} WHERE affiliate_id = {$affiliate_id} AND landing_url = %s OR landing_url = %s",
                $page,
                $perm
            ))
        );
        return [count($ids), Validators::array_of_int($ids)];
    }

    /**
     * Queries the referrals table for the count of referrals that are associated to the given $visit_ids.
     *
     * @global \wpdb $wpdb
     *
     * @param int $affiliate_id
     * @param array<int> $visit_ids IDs of the visits attribued to a specific affiliate landing page.
     *
     * @return int
     */
    private static function _referral_count_for_page($affiliate_id, $visit_ids = [])
    {
        return Referral::referral_count_for_affiliate_visits($affiliate_id, $visit_ids);
    }

    /**
     * Returns a row for the Affiliate Landing Pages table in the Affiliate Portal.
     *
     * @param \WP_Post $post
     * @param int $affiliate_id
     *
     * @return array<string|int>
     */
    private static function _landing_page_table_data_for_portal($post, $affiliate_id)
    {
        $link = get_page_link($post);
        $a = "<a href='{$link}' target='_blank'>{$link}</a>";
        [$visit_count, $visit_ids] = self::_visit_count_for_page($affiliate_id, $post);
        $referral_count = self::_referral_count_for_page($affiliate_id, $visit_ids);
        return [$a, $visit_count, $referral_count];
    }

    /**
     * Returns the HTML representing the links to edit and visit a page, for the Actions table column.
     *
     * @param \WP_Post $post
     *
     * @return string
     */
    private static function _action_links($post)
    {
        $view_url = get_page_link($post);
        $edit_url = get_edit_post_link($post);
        $a_view = "<a class='sld-visit-page' href='{$view_url}' target='_blank'>" . __('View', 'solid-affiliate') . "</a>";
        $a_edit = "<a class='sld-edit-page' href='{$edit_url}' target='_blank'>" . __('Edit', 'solid-affiliate') . "</a>";
        return $a_edit . '<br />' . $a_view;
    }

    /**
     * Returns the HTML to be displayed on the Affiliate Landing Pages tab in the Affiliate Portal.
     *
     * @param AffiliatePortalViewInterface $Itab
     *
     * @return string
     */
    private static function _render_affiliate_portal_tab($Itab)
    {
        $affiliate_id = $Itab->affiliate->id;
        $landing_pages = self::get_all_pages_for_affiliate($affiliate_id);
        $rows = array_map(function ($post) use ($affiliate_id) {
            return self::_landing_page_table_data_for_portal($post, $affiliate_id);
        }, $landing_pages);
        $headers = [self::_page_url_header(), self::_visit_count_header(), self::_referral_count_header()];
        ob_start();
    ?>
        <div class='solid-affiliate-affiliate-portal_landing_pages'>
            <h2 class="sld-ap-title"><?php _e('Published Affiliate Landing Pages', 'solid-affiliate') ?></h2>
            <p class="sld-ap-description"><?php _e('All the pages listed below are your personal landing pages. Any traffic to these pages, and orders from that traffic will be attributed to you.', 'solid-affiliate') ?></p>
            <?php echo (SimpleTableView::render($headers, $rows, null, __('There are no landing pages provided for you. If you would like one, work with the site admin to add one for you.', 'solid-affiliate'), 'sld-ap-table')) ?>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Returns the HTML representing the Affiliate Landing Pages icon shown on the Affiliate Portal if $tab_key passed in by the filter is the Affiliate Landing Pages tab.
     *
     * @param string $default_icon
     * @param string $tab_key
     *
     * @return string
     */
    public static function add_landing_pages_icon($default_icon, $tab_key)
    {
        if ($tab_key === self::AFFILIATE_PORTAL_TAB_KEY) {
            return '
               <svg class="sld-ap-nav_menu-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                   <path fill-rule="evenodd" clip-rule="evenodd" d="M3.33333 3.83337C3.11232 3.83337 2.90036 3.92117 2.74408 4.07745C2.5878 4.23373 2.5 4.44569 2.5 4.66671V11.3334C2.5 11.5544 2.5878 11.7663 2.74408 11.9226C2.90036 12.0789 3.11232 12.1667 3.33333 12.1667H12.6667C12.8877 12.1667 13.0996 12.0789 13.2559 11.9226C13.4122 11.7663 13.5 11.5544 13.5 11.3334V4.66671C13.5 4.44569 13.4122 4.23373 13.2559 4.07745C13.0996 3.92117 12.8877 3.83337 12.6667 3.83337H3.33333ZM2.03697 3.37034C2.38079 3.02653 2.8471 2.83337 3.33333 2.83337H12.6667C13.1529 2.83337 13.6192 3.02653 13.963 3.37034C14.3068 3.71416 14.5 4.18048 14.5 4.66671V11.3334C14.5 11.8196 14.3068 12.2859 13.963 12.6297C13.6192 12.9736 13.1529 13.1667 12.6667 13.1667H3.33333C2.8471 13.1667 2.38079 12.9736 2.03697 12.6297C1.69315 12.2859 1.5 11.8196 1.5 11.3334V4.66671C1.5 4.18048 1.69315 3.71416 2.03697 3.37034ZM3.5 5.33337C3.5 5.05723 3.72386 4.83337 4 4.83337H4.00667C4.28281 4.83337 4.50667 5.05723 4.50667 5.33337C4.50667 5.60952 4.28281 5.83337 4.00667 5.83337H4C3.72386 5.83337 3.5 5.60952 3.5 5.33337ZM5.5 5.33337C5.5 5.05723 5.72386 4.83337 6 4.83337H6.00667C6.28281 4.83337 6.50667 5.05723 6.50667 5.33337C6.50667 5.60952 6.28281 5.83337 6.00667 5.83337H6C5.72386 5.83337 5.5 5.60952 5.5 5.33337Z" fill="black"/>
                </svg>
            ';
        } else {
            return $default_icon;
        }
    }

    /**
     * Returns the array of tuples representing all the tabs in the Affiliate Portal with the Affiliate Landing Pages tab at the end of the array.
     *
     * @param array<array{0: string, 1: string}> $tab_tuples
     *
     * @return array<array{0: string, 1: string}>
     */
    public static function add_landing_pages_tab($tab_tuples)
    {
        array_push($tab_tuples, [self::AFFILIATE_PORTAL_TAB_KEY, __('Landing Pages', 'solid-affiliate')]);
        return $tab_tuples;
    }

    /**
     * Callback for the filter returning the Affiliate Slug or Affiliate ID to be attributed a visit as part of the javascript visit tracking.
     * If the addon is not tracking, then it returns the passed in $affiliate_slug, otherwise it checks to see if the passed in $landing_url is assigned to an Affiliate.
     *
     * @param string $affiliate_slug
     * @param string $landing_url
     *
     * @return int|string
     */
    public static function maybe_attribute_to_affiliate($affiliate_slug, $landing_url)
    {
        if (!self::should_track()) {
            return $affiliate_slug;
        }

        $post_id = url_to_postid($landing_url);
        $alp_affiliate_id = self::get_affiliate_id_from_post_meta($post_id);

        if (!Utils::is_number_zero($alp_affiliate_id)) {
            return $alp_affiliate_id;
        }

        return $affiliate_slug;
    }

    /**
     * Add a WordPress meta box to the post editor that allows selecting an Affiliate.
     *
     * @param \WP_Post $_post
     *
     * @return void
     */
    public static function add_meta_box_assign_post_to_affiliate($_post)
    {
        do_action(self::BEFORE_META_BOX_ACTION);
        add_meta_box(
            'solid-affiliate_assign_post_to_affiliate',
            __('Affiliate Landing Page', 'solid-affiliate'),
            [self::class, 'render_post_meta_box'],
            self::ALLOWED_POST_TYPES,
            'side',
            'default'
        );
    }

    /**
     * Queries the postmeta table to return the Affiliate ID if it is associated to the current or passed in post.
     *
     * @param int $post_id
     *
     * @return int - The Affiliate ID or 0 if not found.
     */
    public static function get_affiliate_id_from_post_meta($post_id = 0)
    {
        if ($post_id === 0) {
            $post_id = (int)get_the_ID();
        }
        return (int)get_post_meta($post_id, self::PAGE_META_KEY, true);
    }

    /**
     * Returns the HTML representing the Affiliate select dropdown to be rendered in the post meta box.
     *
     * @return void
     */
    public static function render_post_meta_box()
    {
        ob_start();
    ?>
        <style>
            label[for=affiliate-select] .select2-container {
                width: 240px !important;
            }

            #solid-affiliate_assign_post_to_affiliate .postbox-header h2::before,
            #solid-affiliate_assign_post_to_affiliate h2.hndle.ui-sortable-handle::before {
                content: "" !important;
                background-image: url(https://solidaffiliate.com/wp-content/uploads/2022/12/favicon.png);
                background-size: 16px 16px;
                display: inline-block;
                width: 16px;
                height: 16px;
                background-repeat: no-repeat;
                position: absolute;
                left: -8px;
            }

            #solid-affiliate_assign_post_to_affiliate h2.hndle.ui-sortable-handle {
                position: relative;
                left: 20px;
                border: none;
            }
        </style>
        <script>
            jQuery('document').ready(function() {
                jQuery('#solid-affiliate_assign_post_to_affiliate.postbox').removeClass('closed');
            })
        </script>
<?php
        echo FormBuilder::build_affiliate_select_field(new FormFieldArgs([
            'label_for_value' => 'affiliate-select',
            'label' =>  __('Assign an Affiliate', 'solid-affiliate'),
            'field_name' =>  self::PAGE_META_KEY,
            'field_id' =>  self::PAGE_META_KEY,
            'field_type' =>  'affiliate_select',
            'value' => self::_get_affiliate_id_for_metabox_affiliate_select($_REQUEST),
            'required' =>  false,
            'description' =>  '',
            'select_options' => [],
            'custom_attributes' => [],
            'wrapper_class' => '',
            'label_class' => '',
            'description_class' => '',
            'hide_title' => false,
            'hide_description' => true,
            'shows_placeholder' => false,
            'placeholder' => '',
            'tooltip_content' => '',
            'tooltip_class' => '',
            'tooltip_body' => ''
        ]));
        wp_nonce_field(self::PAGE_POST_NONCE, self::PAGE_POST_NONCE);
        echo '<hr />' . __('Any traffic to this page, and order from that traffic, will be attributed this Affiliate as Visits and Referrals.', 'solid-affiliate');
        echo ' ' . self::render_documentation_link() . __(' about the Affiliate Landing Pages addon.', 'solid-affiliate');
        echo (ob_get_clean());
    }


    /**
     * Returns the affiliate ID associated to the landing page if present, if not, checks to see if the $request includes an affiliate ID to preload for the affiliate select dropdown.
     *
     * @param array $request $_REQUEST
     *
     * @return int
     */
    private static function _get_affiliate_id_for_metabox_affiliate_select($request)
    {
        $maybe_affiliate_id_from_meta = self::get_affiliate_id_from_post_meta();
        if (!Utils::is_empty($maybe_affiliate_id_from_meta)) {
            return $maybe_affiliate_id_from_meta;
        }

        $maybe_affiliate_id_from_request = Validators::str_from_array($request, self::AFFILIATE_ID_QUERY_KEY);
        if (!Utils::is_empty($maybe_affiliate_id_from_request)) {
            return (int)$maybe_affiliate_id_from_request;
        }

        return $maybe_affiliate_id_from_meta;
    }

    /**
     * Callback fired when a post is saved or updated.
     * If the save passes a series of checks (autosave/revision, allowed post types, nonce verification, permissions, meta key param), then it will update the postmeta to associated the assigned Affiliate.
     *
     * @param integer $post_id
     * @param \WP_Post $post
     * @param boolean $_update
     *
     * @return int The ID of the WP_Post.
     */
    public static function handle_save_post($post_id, $post, $_update)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $post_id;
        }

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return $post_id;
        }

        if (!in_array($post->post_type, self::ALLOWED_POST_TYPES)) {
            return $post_id;
        }

        if (empty($_POST[self::PAGE_POST_NONCE]) || !wp_verify_nonce((string)$_POST[self::PAGE_POST_NONCE], self::PAGE_POST_NONCE)) {
            return $post_id;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return $post_id;
        }

        if (!empty($_POST[self::PAGE_META_KEY])) {
            $affiliate_id = (int)sanitize_text_field((string)$_POST[self::PAGE_META_KEY]);
            if (!Affiliate::find($affiliate_id)) {
                return $post_id;
            }
            update_post_meta($post_id, self::PAGE_META_KEY, $affiliate_id);
        } else {
            delete_post_meta($post_id, self::PAGE_META_KEY);
        }

        return $post_id;
    }

    /**
     * The translated string representing the page url header.
     *
     * @return string
     */
    private static function _page_url_header()
    {
        return __('Page URL', 'solid-affiliate');
    }

    /**
     * The translated string representing the visit count header.
     *
     * @return string
     */
    private static function _visit_count_header()
    {
        return __('Total Visit Count', 'solid-affiliate');
    }

    /**
     * The translated string representing the referral count header.
     *
     * @return string
     */
    private static function _referral_count_header()
    {
        return __('Total Referral Count', 'solid-affiliate');
    }

    /**
     * The translated string representing the view page header.
     *
     * @return string
     */
    private static function _actions_header()
    {
        return __('Actions', 'solid-affiliate');
    }
}
