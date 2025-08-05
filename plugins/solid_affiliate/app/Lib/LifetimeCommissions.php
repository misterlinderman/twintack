<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\VO\AffiliatePortalViewInterface;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateCustomerLink;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Views\Admin\Affiliates\EditView;
use SolidAffiliate\Views\AffiliatePortal\AffiliatePortalTabsView;
use SolidAffiliate\Views\AffiliatePortal\DashboardView;
use SolidAffiliate\Views\Shared\SimpleTableView;

/**
 * This class deals with the Lifetime Commissions features of Solid Affiliate
 */
class LifetimeCommissions
{
    const AFFILIATE_PORTAL_TAB_KEY = 'lifetime-commissions';
    /**
     * @return void
     */
    public static function register_hooks()
    {
        if (!self::is_lifetime_commissions_enabled()) {
            return;
        }

        add_filter(EditView::AFFILIATE_EDIT_AFTER_FORM_FILTER, [self::class, 'render_affiliate_edit_section'], 10, 2);

        // Affiliate Portal
        if ((bool)Settings::get(Settings::KEY_IS_LIFETIME_COMMISSIONS_SHOW_AFFILIATES_THEIR_CUSTOMERS)) {
            add_filter(AffiliatePortalTabsView::AFFILIATE_PORTAL_TABS_FILTER, [self::class, 'add_tab_to_affiliate_portal']);
            add_filter(AffiliatePortalTabsView::AFFILIATE_PORTAL_TAB_ICON_FILTER, [self::class, 'add_icon_to_affiliate_portal_tab'], 10, 2);
            add_filter(DashboardView::AFFILIATE_PORTAL_RENDER_TAB_ACTION, [self::class, 'maybe_render_store_credit_tab_on_affiliate_portal'], 10, 1);
        }

        if ((bool)Settings::get(Settings::KEY_IS_LIFETIME_COMMISSIONS_AUTO_LINK_ENABLED)) {
            add_action("solid_affiliate/purchase_tracking/referral_created", [self::class, 'on_referral_created'], 10, 1);
        }
    }

    /**
     * @return boolean
     */
    public static function is_lifetime_commissions_enabled()
    {
        return (bool)Settings::get(Settings::KEY_IS_LIFETIME_COMMISSIONS_ENABLED);
    }

    /**
     * @param int $referral_id
     * @return void
     */
    public static function on_referral_created($referral_id)
    {
        $referral = Referral::find($referral_id);

        if (!$referral) {
            return;
        }

        if (!self::is_lifetime_commissions_enabled()) {
            return;
        }

        // check if referral source is visit or coupon
        $referral_source = $referral->referral_source;
        if ($referral_source != Referral::SOURCE_VISIT && $referral_source != Referral::SOURCE_COUPON) {
            return;
        }

        if (Settings::get(Settings::KEY_IS_ONLY_NEW_CUSTOMER_LIFE_TIME_COMMISSIONS)) {
            $customer = new \WC_Customer($referral->customer_id);
            /** @psalm-suppress RedundantCondition */
            if ($customer instanceof \WC_Customer) {
                $order_count = $customer->get_order_count();
                if ($order_count > 1) {
                    return;
                }
            }
        }

        // Check if there is already a link for this referral
        $maybe_link = self::get_link_for_referral($referral);
        // If there isn't, create one
        if (!$maybe_link) {
            self::create_link_for_referral($referral);
        }
    }

    /**
     * @param Referral $referral
     * @return null|AffiliateCustomerLink
     */
    public static function get_link_for_referral($referral)
    {
        $maybe_customer = get_user_by('ID', $referral->customer_id);
        $email = WooCommerceIntegration::get_email_for($referral->order_id);

        if ($maybe_customer && !empty($maybe_customer->ID)) {
            $maybe_link_by_customer_id = AffiliateCustomerLink::find_where([
                'affiliate_id' => $referral->affiliate_id,
                'customer_id' => $maybe_customer->ID,
            ]);

            if ($maybe_link_by_customer_id) {
                return $maybe_link_by_customer_id;
            }
        }

        if (!empty($email)) {
            $maybe_link_by_customer_email = AffiliateCustomerLink::find_where([
                'affiliate_id' => $referral->affiliate_id,
                'customer_email' => $email,
            ]);

            if ($maybe_link_by_customer_email) {
                return $maybe_link_by_customer_email;
            }
        }

        return null;
    }

    /**
     * @param Referral $referral
     * @return Either<AffiliateCustomerLink>
     */
    public static function create_link_for_referral($referral)
    {
        $expiration_on_unix_seconds = self::get_expiration_on_unix_seconds_from_settings();

        return AffiliateCustomerLink::insert([
            'affiliate_id' => $referral->affiliate_id,
            'customer_id' => $referral->customer_id,
            'customer_email' => WooCommerceIntegration::get_email_for($referral->order_id),
            'expires_on_unix_seconds' => $expiration_on_unix_seconds
        ]);
    }

    /**
     * @return int
     */
    public static function get_expiration_on_unix_seconds_from_settings()
    {
        $duration = (int)Settings::get(Settings::KEY_LIFETIME_COMMISSIONS_DURATION_IN_SECONDS_STRING);
        if ($duration == 0) {
            return 0;
        } else {
            return time() + $duration;
        }
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
    public static function render_affiliate_edit_section($panels, $affiliate)
    {
        if (is_null($affiliate)) {
            return $panels;
        }

        $header = '<h2 id="edit-affiliate-lifetime_commissions">' . __('Lifetime Commissions', 'solid-affiliate') . '</h2>';

        $table = self::render_affiliate_customer_links_table($affiliate->id);

        $section = $header . $table;

        array_unshift($panels, $section);
        return $panels;
    }

    /**
     * @param int $affiliate_id
     * @param bool $for_affiliate_portal
     * 
     * @return string
     */
    public static function render_affiliate_customer_links_table($affiliate_id, $for_affiliate_portal = false)
    {

        $affiliate_customer_links = AffiliateCustomerLink::where([
            'affiliate_id' => $affiliate_id,
        ]);

        $body_rows = array_map(function ($affiliate_customer_link) use ($for_affiliate_portal) {
            // time from unix timestamp to human readable
            $unix_seconds = $affiliate_customer_link->expires_on_unix_seconds;

            if ($unix_seconds == 0) {
                $expires_on = __('Never', 'solid-affiliate');
            } else {
                $expires_on = date('Y-m-d H:i:s', $unix_seconds);
            }

            $delete_link = Links::render(URLs::delete(AffiliateCustomerLink::class, false, (int)$affiliate_customer_link->id), __('Delete', 'solid-affiliate'));
            // if it's for the affiliate portal, we don't want the delete link
            if ($for_affiliate_portal) {
                $delete_link = '-';
            }

            $customer_id = empty((int)$affiliate_customer_link->customer_id) ? 'guest' : $affiliate_customer_link->customer_id;

            return [
                $customer_id,
                $affiliate_customer_link->customer_email,
                $expires_on,
                self::count_referrals_for_affiliate_customer_link($affiliate_customer_link->id),
                $delete_link,
            ];
        }, $affiliate_customer_links);

        $table = SimpleTableView::render(
            Translation::translate_array(['Customer', 'Customer Email', 'Expires On', 'Referrals',  'Actions']),
            $body_rows
        );

        $maybe_ff_overlay = FF::maybe_overlay('lifetime_commissions');

        return $maybe_ff_overlay . ' ' . $table;
    }

    /**
     * @param int $link_id
     * @return int
     */
    public static function count_referrals_for_affiliate_customer_link($link_id)
    {
        $affiliate_customer_link = AffiliateCustomerLink::find($link_id);

        if (is_null($affiliate_customer_link)) {
            return 0;
        }

        // TODO need to account for guest customers. If there is a customer email
        // then find all the WooCommerce Order IDs under that email
        $maybe_customer_email = $affiliate_customer_link->customer_email;
        if (!empty($maybe_customer_email)) {
            $order_ids = WooCommerceIntegration::get_order_ids_for_email($maybe_customer_email);
        } else {
            $order_ids = [];
        }

        if (empty($order_ids)) {
            $referrals_by_order_id = [];
        } else {
            $referrals_by_order_id = Referral::where([
                'affiliate_id' => $affiliate_customer_link->affiliate_id,
                'order_id' => ['operator' => 'IN', 'value' => $order_ids],
                'status' => ['operator' => 'IN', 'value' => Referral::STATUSES_PAID_AND_UNPAID]
            ]);
        }

        // Count the referrals where customer_id is the same as the affiliate_customer_link->customer_id
        // OR the order_id is in the order_ids array

        $referrals_by_customer_id = [];
        if (!empty($affiliate_customer_link->customer_id)) {
            $referrals_by_customer_id = Referral::where([
                'affiliate_id' => $affiliate_customer_link->affiliate_id,
                'customer_id' => $affiliate_customer_link->customer_id,
                'status' => ['operator' => 'IN', 'value' => Referral::STATUSES_PAID_AND_UNPAID]
            ]);
        }

        // deduplicate the referrals by their ID and count them
        $all_referrals = array_merge($referrals_by_customer_id, $referrals_by_order_id);
        // map them by ID
        $all_referrals_by_id = array_map(function ($referral) {
            return $referral->id;
        }, $all_referrals);
        // deduplicate the IDs
        $all_referrals_by_id = array_unique($all_referrals_by_id);

        return count($all_referrals_by_id);
    }

    //////////////////////////////////////////////
    // AFFILIATE PORTAL INTEGRATION
    //////////////////////////////////////////////

    /**
     * Returns the array of tuples representing all the tabs in the Affiliate Portal with the Affiliate Landing Pages tab at the end of the array.
     *
     * @param array<array{0: string, 1: string}> $tab_tuples
     *
     * @return array<array{0: string, 1: string}>
     */
    public static function add_tab_to_affiliate_portal($tab_tuples)
    {
        array_push($tab_tuples, [self::AFFILIATE_PORTAL_TAB_KEY, __('Lifetime Customers', 'solid-affiliate')]);
        return $tab_tuples;
    }


    /**
     * Returns the HTML representing the Affiliate Landing Pages icon shown on the Affiliate Portal if $tab_key passed in by the filter is the Affiliate Landing Pages tab.
     *
     * @param string $default_icon
     * @param string $tab_key
     *
     * @return string
     */
    public static function add_icon_to_affiliate_portal_tab($default_icon, $tab_key)
    {
        if ($tab_key === self::AFFILIATE_PORTAL_TAB_KEY) {
            return '
                    <svg class="sld-ap-nav_menu-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M3.45457 5.07433C4.03326 4.83468 4.67002 4.77203 5.28432 4.89429C5.89452 5.01574 6.45533 5.31423 6.89681 5.75236C7.31395 6.13193 7.68408 6.55873 8.00008 7.02348C8.31608 6.55872 8.68622 6.13192 9.10336 5.75235C9.54484 5.31422 10.1057 5.01574 10.7158 4.89429C11.3301 4.77203 11.9669 4.83468 12.5456 5.07433C13.1243 5.31398 13.6189 5.71986 13.9669 6.24064C14.3149 6.76141 14.5007 7.37369 14.5007 8.00004C14.5007 8.62639 14.3149 9.23867 13.9669 9.75945C13.6189 10.2802 13.1243 10.6861 12.5456 10.9258C11.9669 11.1654 11.3301 11.2281 10.7158 11.1058C10.1057 10.9843 9.54484 10.6859 9.10337 10.2477C8.68622 9.86816 8.31608 9.44136 8.00008 8.97661C7.68408 9.44136 7.31395 9.86816 6.8968 10.2477C6.45533 10.6859 5.89451 10.9843 5.28432 11.1058C4.67002 11.2281 4.03326 11.1654 3.45457 10.9258C2.87588 10.6861 2.38126 10.2802 2.03326 9.75945C1.68526 9.23867 1.49951 8.62639 1.49951 8.00004C1.49951 7.37369 1.68526 6.76141 2.03326 6.24064C2.38126 5.71986 2.87588 5.31398 3.45457 5.07433ZM7.43145 8.00004C7.1092 7.43451 6.69916 6.92308 6.21619 6.48509C6.21015 6.47961 6.20424 6.47398 6.19847 6.46821C5.89549 6.16513 5.50942 5.95871 5.08911 5.87506C4.6688 5.7914 4.23313 5.83427 3.83718 5.99824C3.44124 6.16221 3.10281 6.43992 2.86471 6.79624C2.6266 7.15256 2.49951 7.57149 2.49951 8.00004C2.49951 8.4286 2.6266 8.84752 2.86471 9.20385C3.10281 9.56017 3.44124 9.83787 3.83718 10.0018C4.23313 10.1658 4.6688 10.2087 5.08911 10.125C5.50942 10.0414 5.89549 9.83495 6.19847 9.53188C6.20424 9.52611 6.21015 9.52048 6.21619 9.515C6.69916 9.07701 7.1092 8.56557 7.43145 8.00004ZM8.56871 8.00004C8.89096 8.56557 9.301 9.07701 9.78397 9.515C9.79001 9.52048 9.79592 9.52611 9.80169 9.53188C10.1047 9.83495 10.4907 10.0414 10.911 10.125C11.3314 10.2087 11.767 10.1658 12.163 10.0018C12.5589 9.83787 12.8974 9.56017 13.1355 9.20385C13.3736 8.84752 13.5007 8.4286 13.5007 8.00004C13.5007 7.57149 13.3736 7.15256 13.1355 6.79624C12.8974 6.43992 12.5589 6.16221 12.163 5.99824C11.767 5.83427 11.3314 5.7914 10.911 5.87506C10.4907 5.95871 10.1047 6.16513 9.80169 6.46821C9.79592 6.47398 9.79001 6.47961 9.78397 6.48509C9.301 6.92308 8.89096 7.43451 8.56871 8.00004Z" fill="black"/>
                    </svg>
                    ';
        } else {
            return $default_icon;
        }
    }

    /**
     * Returns the HTML for the Affiliate Landing Pages tab in the Affiliate Portal if the $current_tab passed in by the filter is the Affiliate Landing Pages tab.
     *
     * @param AffiliatePortalViewInterface $Itab
     *
     * @return void
     */
    public static function maybe_render_store_credit_tab_on_affiliate_portal($Itab)
    {
?>
        <div x-show="current_tab === '<?php echo (self::AFFILIATE_PORTAL_TAB_KEY) ?>'">
            <?php echo  self::_render_affiliate_portal_tab($Itab); ?>
        </div>
    <?php
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
        $table = self::render_affiliate_customer_links_table($affiliate_id, true);

        /////////////////////////////////////////////
        ob_start();
    ?>
        <div class='solid-affiliate-affiliate-portal_lifetime-commissions'>
            <h2 class="sld-ap-title"><?php _e('Lifetime Customers', 'solid-affiliate') ?></h2>
            <p class="sld-ap-description"><?php _e('Here is where you can see all your lifetime customers. These customers will generate referrals for you when they make purchases.', 'solid-affiliate') ?></p>
            <?php echo ($table) ?>
        </div>
<?php
        return ob_get_clean();
    }

    /**
     *
     * @return array<array{0:string, 1:string}>
     */
    public static function enum_options_lifetime_commissions_duration_in_seconds()
    {
        return [
            ['0', __('No Limit', 'solid-affiliate')],
            ['604800', __('1 Week', 'solid-affiliate')],
            ['2592000', __('1 Month', 'solid-affiliate')],
            ['7776000', __('3 Months', 'solid-affiliate')],
            ['15552000', __('6 Months', 'solid-affiliate')],
            ['31536000', __('1 Year', 'solid-affiliate')],
            ['63072000', __('2 Years', 'solid-affiliate')],
            ['94608000', __('3 Years', 'solid-affiliate')],
            ['157680000', __('5 Years', 'solid-affiliate')],
            ['315360000', __('10 Years', 'solid-affiliate')],
        ];
    }

    /**
     * Undocumented function
     *
     * @param int $customer_id
     * @param int $affiliate_id
     * @param int $expiration_in_unix_seconds
     * @return void
     */
    public static function assign_customer_id_to_affiliate_id_with_expiration($customer_id, $affiliate_id, $expiration_in_unix_seconds) {}

    // public static function get_lifetime_customer_ids_from_affiliate_id($affiliate_id)
    // {
    //     return get_user_meta($affiliate_id, self::KEY_CUSTOMER_ID, true);
    // }

    // public static function get_lifetime_customer_affiliate_from_customer_id($customer_id)
    // {
    //     return get_user_meta($customer_id, self::KEY_AFFILIATE_ID, true);
    // }
}
