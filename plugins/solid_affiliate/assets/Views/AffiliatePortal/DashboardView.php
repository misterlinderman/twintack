<?php

namespace SolidAffiliate\Views\AffiliatePortal;

use SolidAffiliate\Controllers\AffiliatePortalController;
use SolidAffiliate\Lib\AffiliateRegistrationForm\AffiliateRegistrationFormFunctions;
use SolidAffiliate\Lib\ChartData;
use SolidAffiliate\Lib\CustomAffiliateSlugs\AffiliateCustomSlugBase;
use SolidAffiliate\Lib\CustomAffiliateSlugs\CustomSlugViewFunctions;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\FormBuilder\FormBuilder;
use SolidAffiliate\Lib\GlobalTypes;
use SolidAffiliate\Lib\ListTables\SharedListTableFunctions;
use SolidAffiliate\Lib\RandomData;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\Templates;
use SolidAffiliate\Lib\Translation;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\AffiliatePortalViewInterface;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliatePortal;
use SolidAffiliate\Models\Creative;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Views\AffiliatePortal\AffiliatePortalTabsView;
use SolidAffiliate\Views\Shared\SimpleTableView;
use SolidAffiliate\Lib\FF;


// wp_enquue_script
// -> wp_enqueue_style for frontend things (not admin)

// patterns woocommerce uses. 
//     - you use a shortcode
//     - you also define which page is your checkout page (in woocomerce admin settings)
// this way they can load special shit on their checkout pages like css and js

/**
 * @psalm-import-type PaginationArgs from \SolidAffiliate\Lib\GlobalTypes
 */
class DashboardView
{
    const AFFILIATE_PORTAL_RENDER_TAB_FILTER = 'solid_affiliate/affiliate_portal/render_tab';
    const AFFILIATE_PORTAL_RENDER_TAB_ACTION = 'solid_affiliate/affiliate_portal/render_tab_action';

    /**
     * @param AffiliatePortalViewInterface $i
     * @param string $notices
     * @param bool $is_admin_preview
     * 
     * @return string
     */
    public static function render($i, $notices, $is_admin_preview = false)
    {
        $should_show_logout_link_on_affiliate_portal = (bool)Settings::get(Settings::KEY_IS_LOGOUT_LINK_ON_AFFILIATE_PORTAL);

        $affiliate_status = $i->affiliate->status;
        if ($affiliate_status != Affiliate::STATUS_APPROVED && (bool)Settings::get(Settings::KEY_IS_HIDE_AFFILIATE_PORTAL_FROM_UNAPPROVED_AFFILIATES)) {
            return (string)Settings::get(Settings::KEY_UNAPPROVED_AFFILIATES_CONTENT);
        }


        $maybe_perma_link = get_permalink();
        $logout_url = $maybe_perma_link ? wp_logout_url($maybe_perma_link) : '/logout';


        $affiliate_word = __(Validators::str(Settings::get(Settings::KEY_AFFILIATE_WORD_IN_PORTAL)), 'solid-affiliate');

        ob_start();
?>
        <?php echo (self::render_js($i)) ?>
        <?php echo (self::render_css()) ?>


        <div id='<?php echo (GlobalTypes::AFFILIATE_PORTAL_HTML_ID) ?>' x-data="{ current_tab: '<?php echo ($i->current_tab) ?>' }">


            <?php echo ($notices) ?>
            <div class="sld-ap-grid_container">
                <div class="sld-ap-header">
                    <div class="sld-ap-heading">
                        <h2><?php echo ($affiliate_word . ' ' . __('Portal', 'solid-affiliate')) ?></h2>
                    </div>
                    <div class="sld-ap-login">
                        <?php echo get_avatar(wp_get_current_user()->ID) ?>
                        <div class="sld-ap-login-user">
                            <span><?php _e('Logged in as', 'solid-affiliate') ?></span>
                            <div class="sld-ap-login-user_name">
                                <?php echo (wp_get_current_user()->user_nicename) ?>
                            </div>
                        </div>
                        <?php if ($should_show_logout_link_on_affiliate_portal) { ?>
                            <a href="<?php echo ($logout_url) ?>" class="sld-ap-login-user_logout">
                                <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1.75 2.875C1.75 2.25368 2.25366 1.75 2.875 1.75H12.875C13.4963 1.75 14 2.25368 14 2.875V3.875H15.75V2.875C15.75 1.28719 14.4628 0 12.875 0H2.875C1.28723 0 0 1.28719 0 2.875V13.875C0 15.4628 1.28723 16.75 2.875 16.75H12.875C14.4628 16.75 15.75 15.4628 15.75 13.875V11.875H14V13.875C14 14.4963 13.4963 15 12.875 15H2.875C2.25366 15 1.75 14.4963 1.75 13.875V2.875Z" />
                                    <path d="M1.75 2.875C1.75 2.25368 2.25366 1.75 2.875 1.75H12.875C13.4963 1.75 14 2.25368 14 2.875V3.875H15.75V2.875C15.75 1.28719 14.4628 0 12.875 0H2.875C1.28723 0 0 1.28719 0 2.875V13.875C0 15.4628 1.28723 16.75 2.875 16.75H12.875C14.4628 16.75 15.75 15.4628 15.75 13.875V11.875H14V13.875C14 14.4963 13.4963 15 12.875 15H2.875C2.25366 15 1.75 14.4963 1.75 13.875V2.875Z" />
                                    <path d="M10.4713 3.875L9.21301 5.16071L11.5135 7.51155H3.875V9.32982H11.4242L9.21313 11.5893L10.4713 12.875L14.875 8.375L10.4713 3.875Z" />
                                    <path d="M10.4713 3.875L9.21301 5.16071L11.5135 7.51155H3.875V9.32982H11.4242L9.21313 11.5893L10.4713 12.875L14.875 8.375L10.4713 3.875Z" />
                                </svg>
                            </a>
                        <?php }; ?>
                    </div>
                </div>
                <div class="sld-ap-info">
                    <div class="sld-ap-info_col">
                        <div class="sld-ap-info-box sld-ap-mr20">
                            <span class="sld-ap-info-box-top">
                                <?php echo ($affiliate_word . ' ' . __('Status', 'solid-affiliate')) ?>
                            </span>
                            <p class="sld-ap-info-box-bottom sld-ap-affiliate-status-<?php echo ($i->affiliate->status) ?>">
                                <?php _e(ucfirst((string)$i->affiliate->status), 'solid-affiliate') ?>
                            </p>
                        </div>
                        <div class="sld-ap-info-box sld-ap-mr20">
                            <span class="sld-ap-info-box-top">
                                <?php _e('Payment email', 'solid-affiliate') ?>
                            </span>
                            <p class="sld-ap-info-box-bottom">
                                <?php echo ($i->affiliate->payment_email) ?>
                                <!-- <a class="sld-ap-info-box_edit">
                                    <a href="edit-email.html" class="sld-ap-info-box_edit">
                                        <svg width="12" height="11" viewBox="0 0 12 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M0.666992 8.8125L7.12012 2.35938L9.30762 4.54688L2.85449 11H0.666992V8.8125ZM11.0029 2.85156L9.93652 3.91797L7.74902 1.73047L8.81543 0.664062C8.9248 0.554688 9.06152 0.5 9.22559 0.5C9.38965 0.5 9.52637 0.554688 9.63574 0.664062L11.0029 2.03125C11.1123 2.14062 11.167 2.27734 11.167 2.44141C11.167 2.60547 11.1123 2.74219 11.0029 2.85156Z" fill="#757575" />
                                        </svg>
                                    </a>
                                </a> -->
                            </p>
                        </div>
                        <div class="sld-ap-info-box">
                            <span class="sld-ap-info-box-top">
                                <?php _e('Account Email', 'solid-affiliate') ?>
                            </span>
                            <p class="sld-ap-info-box-bottom">
                                <?php echo ($i->account_email) ?>
                            </p>
                        </div>
                    </div>
                    <div class="sld-ap-info_col">
                        <div class="sld-ap-info-box sld-ap-mr20">
                            <span class="sld-ap-info-box-top">
                                <?php echo ($affiliate_word . ' ' . __('ID', 'solid-affiliate')) ?>
                            </span>
                            <p class="sld-ap-info-box-bottom">
                                <?php echo ($i->affiliate->id) ?>
                            </p>
                        </div>
                        <div class="sld-ap-info-box">
                            <span class="sld-ap-info-box-top">
                                <?php echo (__('Your ', 'solid-affiliate') . $affiliate_word . ' ' . __('Link', 'solid-affiliate')) ?>
                            </span>
                            <p class="sld-ap-info-box-bottom sld-ap-affiliate-link"><a href="<?php echo ($i->affiliate_link) ?>"><?php echo ($i->affiliate_link) ?></a></p>
                        </div>
                    </div>
                </div>
                <?php echo AffiliatePortalTabsView::render(
                    self::default_affiliate_tabs_enum($affiliate_word),
                    $i->current_tab,
                    GlobalTypes::AFFILIATE_PORTAL_HTML_ID,
                    [
                        'is_admin_preview' => $is_admin_preview,
                        'affiliate_id' => $i->affiliate->id,
                    ]
                ); ?>

                <!-- AJAX content -->
                <div class="sld-ap-main sld-ap-main_<?php echo ($i->current_tab) ?>">
                    <div x-show="current_tab === 'dashboard'">
                        <!-- 0.01 -->
                        <?php echo self::render_dashboard($i); ?>
                        <!-- 0.018 -->
                    </div>
                    <div x-show="current_tab === 'settings'">
                        <?php echo self::render_account_settings($i); ?>
                        <!-- 1.48 -->
                    </div>
                    <div x-show="current_tab === 'referrals'">
                        <?php echo self::render_referrals($i); ?>
                        <!-- 1.55 -->
                    </div>
                    <div x-show="current_tab === 'payouts'">
                        <?php echo self::render_payouts($i); ?>
                        <!-- 1.55 -->
                    </div>
                    <div x-show="current_tab === 'visits'">
                        <?php echo self::render_visits($i); ?>
                        <!-- 1.55 -->
                    </div>
                    <div x-show="current_tab === 'coupons'">
                        <?php echo self::render_coupons($i); ?>
                        <!-- 1.55 -->
                    </div>
                    <div x-show="current_tab === 'urls'">
                        <?php echo self::render_urls($i); ?>
                        <!-- 1.95 -->
                    </div>
                    <div x-show="current_tab === 'creatives'">
                        <?php echo self::render_creatives($i); ?>
                        <!-- 3.65 -->
                    </div>
                    <!-- TODO render all the addon tabs, that are currently working via filter -->

                    <?php do_action(self::AFFILIATE_PORTAL_RENDER_TAB_ACTION, $i); ?>
                    <!-- 3.66 -->

                </div>

            </div>
            <?php
            if (FF::is_off('white_labeled_affiliate_portal')) {
                echo '<div style="width:100%">
                        <a href="https://solidaffiliate.com" style="cursor:pointer; color:inherit; outline:0; justify-content:center; box-shadow: 0; text-decoration:none; opacity:.8; background:#fff; padding:10px; margin-top:10px; border-radius:10px; border:1px solid var(--sld-ap-border-color); font-size:13px; display:flex; flex-direction: row; align-items:center; gap:8px; 
                        @media (max-width: 768px) { display: none !important; }">
                            Affiliate program by <img height="16" src="https://solidaffiliate.com/brand/logo.svg" alt=""/>
                        </a>
                        </div>';
            } else {
                echo '';
            }
            ?>


        </div>


        <?php
        $res = ob_get_clean();
        if ($res) {
            return $res;
        } else {
            return __("Error rendering Affiliate Portal Dashboard.", 'solid-affiliate');
        }
    }

    /**
     * Undocumented function
     * 
     * @param AffiliatePortalViewInterface $i
     *
     * @return string
     */
    private static function render_account_settings($i)
    {
        $form_id = 'solid-affiliate-affiliate-portal_update_settings';
        $nonce = AffiliatePortalController::NONCE_SUBMIT_UPDATE_SETTINGS;
        $submit_action = AffiliatePortalController::POST_PARAM_SUBMIT_UPDATE_SETTINGS;
        $item = AffiliateRegistrationFormFunctions::prep_affiliate_object_form_form($i->affiliate);

        // check if all of the entries have show_on_non_admin_edit_form set to false
        $affiliate_registration_schema_for_portal = AffiliatePortal::get_affiliate_registration_schema_for_portal_forms();
        $entries = $affiliate_registration_schema_for_portal->entries;
        $entries_to_render = array_filter($entries, function ($entry) {
            return $entry->show_on_non_admin_edit_form === true;
        });

        if (empty($entries_to_render)) {
            ob_start();
        ?>
            <h2 class="sld-ap-title">
                <?php _e('Settings', 'solid-affiliate') ?>
            </h2>
            <p class="sld-ap-description">
                <?php _e('You have no permissions to edit any of the settings. All your account settings are managed by the program administrator.', 'solid-affiliate') ?>
            </p>
        <?php
            $res = ob_get_clean();
            return $res;
        }

        ob_start();
        ?>
        <?php echo (LoginView::render_css()) ?>
        <style>
            .sld-ap {
                display: block;
            }
        </style>
        <div class='solid-affiliate-affiliate-portal_account_settings'>
            <h2 class="sld-ap-title">
                <?php _e('Settings', 'solid-affiliate') ?>
            </h2>
            <div class="sld-ap-edit-email">
                <form action="" method="post" id="<?php echo $form_id ?>">
                    <?php echo (FormBuilder::build_form(
                        $affiliate_registration_schema_for_portal,
                        'non_admin_edit',
                        $item,
                        false,
                        false,
                        false,
                    )); ?>
                    <input type="hidden" name="field_id" value="<?php echo (string)$item->id; ?>">
                    <?php wp_nonce_field($nonce); ?>
                    <input type="submit" name="<?php echo ($submit_action) ?>" id="<?php echo ($submit_action) ?>" class="sld-ap-edit-email_button" value="<?php _e('Update Settings', 'solid-affiliate') ?>">
                </form>
            </div>
        </div>

    <?php
        $res = ob_get_clean();
        return $res;
    }

    /**
     * Undocumented function
     * 
     * @param AffiliatePortalViewInterface $i
     *
     * @return string
     */
    private static function render_dashboard($i)
    {
        $referrals_count = $i->referrals_count;
        $visits_count = $i->visits_count;
        $unpaid_earnings = Formatters::money($i->total_unpaid_earnings);
        $paid_earnings = Formatters::money($i->total_paid_earnings);

        if ($i->store_credit_data['is_enabled']) {
            $available_store_credit = Formatters::money($i->store_credit_data['outstanding_store_credit']);

            $store_credit_dashboard_component = '
         <div class="sld-ap-dashboard_item store_credit">
             <div class="sld-ap-dashboard_item-subtitle">' . __('Available Store Credit', 'solid-affiliate') . '</div>
             <div class="sld-ap-dashboard_item-value">' . $available_store_credit . '</div>
         </div>
            ';
        } else {
            $store_credit_dashboard_component = '';
        }


        return '
        <h2 class="sld-ap-title">
        ' . __('Dashboard', 'solid-affiliate') . '
     </h2>
     <p class="sld-ap-description">' . __('Purchases or conversions that have been attributed back to your link(s).', 'solid-affiliate') . '</p>
     <div class="sld-ap-dashboard_flex">
         <div class="sld-ap-dashboard_item referrals">
             <div class="sld-ap-dashboard_item-subtitle">' . __('Total Referrals', 'solid-affiliate') . '</div>
             <div class="sld-ap-dashboard_item-value">' . $referrals_count . '</div>
         </div>
         <div class="sld-ap-dashboard_item visits">
             <div class="sld-ap-dashboard_item-subtitle">' . __('Total Visits', 'solid-affiliate') . '</div>
             <div class="sld-ap-dashboard_item-value">' . $visits_count . '</div>
         </div>
         <div class="sld-ap-dashboard_item unpaid">
             <div class="sld-ap-dashboard_item-subtitle">' . __('Total Unpaid Earnings', 'solid-affiliate') . '</div>
             <div class="sld-ap-dashboard_item-value">' . $unpaid_earnings . '</div>
         </div>
         <div class="sld-ap-dashboard_item paid">
             <div class="sld-ap-dashboard_item-subtitle">' . __('Total Paid Earnings', 'solid-affiliate') . '</div>
             <div class="sld-ap-dashboard_item-value">' . $paid_earnings . '</div>
         </div>
         ' . $store_credit_dashboard_component . '
     </div>
     <div class="sld-ap-dashboard_flex">
         <div class="sld-ap-dashboard_item full">
             <div class="sld-ap-dashboard_item-subtitle">' . __('Visits — Daily last 30 days', 'solid-affiliate') . '</div>
             <div class="sld-ap-dashboard_item-chart">
                <div>' . self::render_dashboard_chart_one($i) . '</div>
             </div>
         </div>
         <div class="sld-ap-dashboard_item">
             <div class="sld-ap-dashboard_item-subtitle">' . __('Referrals — Daily last 30 days', 'solid-affiliate') . '</div>
             <div class="sld-ap-dashboard_item-chart">
                 <div>' . self::render_dashboard_chart_two($i) . '</div>
             </div>
         </div>
     </div>
        ';
    }

    /**
     * Undocumented function
     * 
     * @param AffiliatePortalViewInterface $i
     *
     * @return string
     */
    private static function render_referrals($i)
    {
        $should_display_customer_column = (bool)Settings::get(Settings::KEY_IS_DISPLAY_CUSTOMER_INFO_ON_AFFILIATE_PORTAL);
        // Referrals data
        if ($should_display_customer_column) {
            $referrals_table_headers = Translation::translate_array(['ID', 'Date', 'Source', 'Amount', 'Status', 'Customer']);
        } else {
            $referrals_table_headers = Translation::translate_array(['ID', 'Date', 'Source', 'Amount', 'Status']);
        }

        $referrals_table_rows = array_map(
            /** @param \SolidAffiliate\Models\Referral $referral */
            function ($referral) use ($should_display_customer_column) {
                $formatted_date = SharedListTableFunctions::created_at_column($referral);
                // $formatted_commission_amount = Formatters::money($referral->commission_amount);
                $formatted_status = Formatters::status_with_tooltip($referral->status, Referral::class, 'admin');
                $formatted_referral_source = self::format_referral_source($referral);
                // return [$referral->id, $formatted_date, $formatted_referral_source, $formatted_commission_amount, $referral->description, $formatted_status];
                $id_row = $referral->id;
                $formatted_commission_amount = Referral::render_commission_tooltip($referral);
                if ($should_display_customer_column) {
                    $customer_data = self::build_customer_info_for_referral($referral);
                    return [$id_row, $formatted_date, $formatted_referral_source, $formatted_commission_amount, $formatted_status, $customer_data];
                } else {
                    return [$id_row, $formatted_date, $formatted_referral_source, $formatted_commission_amount, $formatted_status];
                }
            },
            $i->referrals
        );


        $table_pagination_args = ['total_count' => $i->referrals_count, 'params_to_add_to_url' => ['tab' => 'referrals']];
        if ($i->current_tab !== 'referrals') {
            $table_pagination_args['current_page'] = 1;
        }

        $table = SimpleTableView::render(
            $referrals_table_headers,
            $referrals_table_rows,
            $table_pagination_args,
            __('No data to display.', 'solid-affiliate'),
            'sld-ap-table'
        );

        ob_start();
    ?>
        <h2 class="sld-ap-title">
            <?php _e('Referrals', 'solid-affiliate') ?>
        </h2>
        <p class="sld-ap-description"><?php _e('Purchases or conversions that have been attributed back to your links and coupons.', 'solid-affiliate') ?></p>
        <?php echo ($table) ?>



    <?php
        $res = ob_get_clean();
        return $res;
    }

    /**
     * @param \SolidAffiliate\Models\Referral $referral
     * @return string
     */
    public static function format_referral_source($referral)
    {
        $source = $referral->referral_source;

        if ($source === Referral::SOURCE_COUPON) {
            $coupon = new \WC_Coupon($referral->coupon_id);
            $coupon_code = $coupon->get_code();
            if (empty($coupon_code)) {
                return __('Coupon', 'solid-affiliate') . ' #' . $referral->coupon_id;
            } else {
                return __('Coupon', 'solid-affiliate') . ' ' . $coupon_code;
            }
        } else {
            return ucfirst((string)$referral->referral_source);
        }
    }

    /**
     * @param Referral $referral
     * @return string
     */
    public static function build_customer_info_for_referral($referral)
    {
        $template_vars = Templates::email_template_vars_for_referral($referral);
        $woo_customer_full_name = $template_vars['woo_customer_full_name'];
        $woo_customer_email = $template_vars['woo_customer_email'];
        $woo_customer_shipping_address = $template_vars['woo_customer_shipping_address'];
        $woo_customer_billing_address = $template_vars['woo_customer_billing_address'];
        $woo_customer_phone = $template_vars['woo_customer_phone'];

        $s = "<ul>
            <li><strong>Full name</strong> - $woo_customer_full_name</li>
            <li><strong>Email</strong> - $woo_customer_email</li>
            <li><strong>Shipping Addr.</strong> - $woo_customer_shipping_address</li>
            <li><strong>Billing Addr.</strong> - $woo_customer_billing_address</li>
            <li><strong>Phone#</strong> - $woo_customer_phone</li>
        </ul>";

        return $s;
    }

    /**
     * Undocumented function
     * 
     * @param AffiliatePortalViewInterface $i
     *
     * @return string
     */
    private static function render_payouts($i)
    {
        // Payouts data
        $payouts_table_headers = Translation::translate_array(['ID', 'Date', 'Amount', 'Status']);
        $payouts_table_rows = array_map(
            function ($payout) {
                $formatted_date = date('F j, Y', strtotime($payout->created_at));
                $formatted_amount = Formatters::money($payout->amount);
                $formatted_status = Formatters::status_with_tooltip($payout->status, Payout::class, 'non_admin');
                return [$payout->id, $formatted_date, $formatted_amount, $formatted_status];
            },
            $i->payouts
        );

        $table_pagination_args = ['total_count' => $i->payouts_count, 'params_to_add_to_url' => ['tab' => 'payouts']];
        if ($i->current_tab !== 'payouts') {
            $table_pagination_args['current_page'] = 1;
        }

        $table = SimpleTableView::render(
            $payouts_table_headers,
            $payouts_table_rows,
            $table_pagination_args,
            __('No data to display.', 'solid-affiliate'),
            'sld-ap-table'
        );

        ob_start();
    ?>
        <h2 class="sld-ap-title">
            <?php _e('Payouts', 'solid-affiliate') ?>
        </h2>
        <p class="sld-ap-description"><?php _e('Payouts which have been paid to you.', 'solid-affiliate') ?></p>
        <?php echo ($table) ?>

    <?php
        $res = ob_get_clean();
        return $res;
    }

    /**
     * Undocumented function
     * 
     * @param AffiliatePortalViewInterface $i
     *
     * @return string
     */
    private static function render_visits($i)
    {
        // Visits data
        $visits_table_headers = Translation::translate_array(['ID', 'Date', 'Referral ID', 'Referring Page', 'Landing URL']);
        $visits_table_rows = array_map(
            function ($visit) {
                $formatted_date = date('F j, Y', strtotime($visit->created_at));
                return [$visit->id, $formatted_date, $visit->referral_id, $visit->http_referrer, $visit->landing_url];
            },
            $i->visits
        );

        $table_pagination_args = ['total_count' => $i->visits_count, 'params_to_add_to_url' => ['tab' => 'visits']];
        if ($i->current_tab !== 'visits') {
            $table_pagination_args['current_page'] = 1;
        }

        $table = SimpleTableView::render(
            $visits_table_headers,
            $visits_table_rows,
            $table_pagination_args,
            __('No data to display.', 'solid-affiliate'),
            'sld-ap-table'
        );

        ob_start();
    ?>

        <h2 class="sld-ap-title">
            <?php _e('Visits', 'solid-affiliate') ?>
        </h2>
        <p class="sld-ap-description"><?php _e('Visits which you have sent using your links.', 'solid-affiliate') ?></p>
        <?php echo ($table) ?>
    <?php
        $res = ob_get_clean();
        return $res;
    }

    /**
     * Undocumented function
     * 
     * @param AffiliatePortalViewInterface $i
     *
     * @return string
     */
    private static function render_coupons($i)
    {
        // Coupons data
        $coupons_table_headers = Translation::translate_array(['Code', 'Amount', 'Discount Type', 'Referrals']);
        $coupons_table_rows = $i->coupon_data;

        ob_start();
    ?>
        <h2 class="sld-ap-title">
            <?php _e('Coupons', 'solid-affiliate') ?>
        </h2>
        <p class="sld-ap-description"><?php _e('Coupons which have been linked to your account. You will receive a referral for any purchases that use these coupons.', 'solid-affiliate') ?></p>
        <?php echo (SimpleTableView::render($coupons_table_headers, $coupons_table_rows, null, 'No data to display.', 'sld-ap-table')) ?>

    <?php
        $res = ob_get_clean();
        return $res;
    }

    /**
     * Undocumented function
     * 
     * @param AffiliatePortalViewInterface $i
     *
     * @return string
     */
    private static function render_creatives($i)
    {
        ob_start();
    ?>
        <h2 class="sld-ap-title">
            <?php _e('Creatives', 'solid-affiliate') ?>
        </h2>
        <p class="sld-ap-description"><?php _e('Quickly embed our creatives on your personal site or blog. Download and share any creative on your social pages to promote your affiliate links.', 'solid-affiliate') ?> </p>
        <div class="sld-ap-creatives_wrapper">

            <?php
            foreach ($i->creatives as $creative) {
                echo Creative::render_affiliate_portal_creative($creative, $i->affiliate->id);
            }
            ?>
        </div>
    <?php
        $res = ob_get_clean();
        return $res;
    }

    /**
     * Undocumented function
     * 
     * @param AffiliatePortalViewInterface $i
     *
     * @return string
     */
    private static function render_urls($i)
    {
        $referral_string = AffiliateCustomSlugBase::default_referral_string($i->affiliate);

        if ($i->custom_slug_default_display_format === AffiliateCustomSlugBase::DISPLAY_FORMAT_VALUE_SLUG) {
            $alt_format_note = __('You can also use your Affiliate ID in URLs. For example', 'solid-affiliate') . ': ' . URLs::default_id_format_affiliate_link($i->affiliate);
        } else {
            $alt_format_note = __('You can also use any Custom Slug assigned to you. View them below in the custom slug section.', 'solid-affiliate');
        }

        ob_start();
    ?>
        <?php echo (LoginView::render_css()) ?>
        <style>
            .sld-ap {
                display: block;
            }
        </style>
        <div class='solid-affiliate-affiliate-portal_urls'>
            <h2 class="sld-ap-title"><?php _e('Affiliate Links', 'solid-affiliate') ?></h2>
            <p class="sld-ap-description"><?php echo '<strong>';
                                            _e('Your default Affiliate URL provided to you by the site admin', 'solid-affiliate');
                                            echo ': ';
                                            echo $i->affiliate_link;
                                            echo '</strong>' ?></p>
            <p class="sld-ap-description"><?php echo sprintf(__('To create an affiliate link to any page on this site, simply add <mark><strong>%1$s</strong></mark> to the url. You can also use the URL generator below.', 'solid-affiliate'), $referral_string) ?></p>
            <p class="sld-ap-description"><?php echo $alt_format_note ?></p>
            <br />
            <hr />
            <br />
            <h2 class="sld-ap-title"><?php _e('URL Generator', 'solid-affiliate') ?></h2>
            <p class="sld-ap-description">1) <?php _e('Enter any page URL from our site', 'solid-affiliate') ?></p>
            <input style="width: 100%" id="sld-ap-page_url" name="page_url" type="text" value="<?php echo URLs::default_affiliate_link_base_url() ?>">
            <br />
            <br />
            <p class="sld-ap-description">2) <?php _e('Use your generated affiliate link', 'solid-affiliate') ?></p>
            <mark>
                <span id="sld-ap-generated_affiliate_url"><?php echo ($i->affiliate_link) ?></span>
            </mark>
            <br />
            <br />
            <hr />
            <br />
            <?php echo CustomSlugViewFunctions::custom_slugs_section_for_affiliate_portal($i->affiliate) ?>
        </div>
    <?php
        $res = ob_get_clean();
        return $res;
    }

    /**
     * Undocumented function
     * 
     * @param AffiliatePortalViewInterface $i
     * @return string
     */
    private static function render_js($i)
    {
        $default_slug = AffiliateCustomSlugBase::default_affiliate_slug($i->affiliate);

        ob_start();
    ?>
        <!-- Alpine Plugins -->
        <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>

        <!-- Alpine Core -->
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
        <script>
            window.sldShowCreativeModal = function(creative_id) {
                var modal = document.getElementById("sld-ap-modal-" + creative_id);
                modal.style.display = "block";
            }

            window.sldHideCreativeModal = function(creative_id) {
                var modal = document.getElementById("sld-ap-modal-" + creative_id);
                modal.style.display = "none";
            }

            window.updateTabAndPageInURL = function(tab) {
                var url = new URL(window.location.href);
                url.searchParams.set("tab", tab);
                url.searchParams.set("<?php echo GlobalTypes::PAGINATION_PARAM ?>", 1);
                window.history.replaceState({}, "", url);
            }

            /**
             * Add a URL parameter (or changing it if it already exists)
             * @param {search} string  this is typically document.location.search
             * @param {key}    string  the key to set
             * @param {val}    string  value 
             */
            window.sldAddUrlParam = function(search, key, val) {
                try {
                    var url = new URL(search);

                    url.searchParams.append(key, val);
                    return url;

                } catch (e) {
                    return '<small>Invalid URL. Be sure to include the <code>https://</code> prefix.</small>';
                }
            };

            window.sldGenerateURLForCurrentAffiliate = function(search) {
                return window.sldAddUrlParam(search, <?php echo json_encode((string)Settings::get(Settings::KEY_REFERRAL_VARIABLE)) ?>, <?php echo json_encode($default_slug) ?>);
            };

            window.sldMountURLGeneratorEvents = function() {
                const source = document.getElementById('sld-ap-page_url');
                const target = document.getElementById('sld-ap-generated_affiliate_url');
                const inputHandler = function(e) {
                    const val = e.target.value;
                    const generatedAffiliateURL = window.sldGenerateURLForCurrentAffiliate(val);
                    target.innerHTML = generatedAffiliateURL;
                }

                // if source exists, add event listener
                if (source) {
                    source.addEventListener('input', inputHandler);
                }
            };

            window.addEventListener('load',
                function() {
                    window.sldMountURLGeneratorEvents();
                }, false);
        </script>

    <?php
        $res = ob_get_clean();
        return $res;
    }

    /**
     * Undocumented function
     * 
     * @return string
     */
    private static function render_css()
    {
        ob_start();
    ?>

        <style>
            :root {
                /* Global container color */
                --sld-ap-background: #fff;
                /* Shading background : Used for hovers and tables */
                --sld-ap-shading: #FAFCFD;

                /* Color scheme */
                --sld-ap-primary-color: #212044;
                --sld-ap-secondary-color: #4B587C;
                --sld-ap-accent-color: #FF4B0D;
                --sld-ap-border-color: #D9E1EC;
                --sld-ap-border-radius: 10px;
                --sld-ap-font-size: 14px;
                --sld-ap-font-family: inherit;

                /* Font sizes */
                --sld-ap-font-size-xs: 11px;
                --sld-ap-font-size-m: 13px;
                --sld-ap-font-size-l: 20px;
                --sld-ap-font-size-xl: 22px;
            }

            /* Global layout/grid  */
            .sld-ap {
                width: 100%;
                margin-left: auto;
                margin-right: auto;
                font-size: 1rem;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
                margin-bottom: 20px;
            }

            .sld-ap-info {
                background: var(--sld-ap-shading);
            }

            .sld-ap-nav {
                width: fit-content;
                border-right: 1px solid var(--sld-ap-border-color);
                padding: 20px;
                box-sizing: border-box;

            }



            .sld-ap-grid_container {
                display: grid;
                overflow: auto;
                grid-gap: 0;
                background: var(--sld-ap-background);
                border: 1px solid var(--sld-ap-border-color);
                border-radius: var(--sld-ap-border-radius);
                grid-template-columns: minmax(150px, auto) repeat(6, 1fr);
            }

            .sld-ap-header,
            .sld-ap-info,
            .sld-ap-nav,
            .sld-ap-main {
                padding: 10px;
            }

            .sld-ap-header {
                grid-column: 1 / -1;
                grid-row: 1;
                /* Explicitly place in the first row */

            }

            .sld-ap-info {
                grid-column: 1 / -1;
                grid-row: 2;

            }

            .sld-ap-nav {
                grid-column: 1 / 2;
                grid-row: 3 / 4;
            }

            .sld-ap-main {
                grid-column: 2 / -1;
                grid-row: 3 / 4;
            }

            /* Menu Styling */

            .sld-ap-nav ul {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            ul.sld-ap-nav_menu li {
                margin-left: 0;
            }

            ul.sld-ap-nav_menu>li a {
                display: block;
                font-size: var(--sld-ap-font-size-m);
                color: var(--sld-ap-primary-color);
                padding: 12px;
                text-decoration: none !important;
                background: transparent;
                border-radius: 4px;
                border: 1px solid transparent;
                outline: 0;
            }

            ul.sld-ap-nav_menu>li a:hover {
                background: var(--sld-ap-shading);
            }

            ul.sld-ap-nav_menu>li a:focus {
                border: 1px solid transparent;
            }

            ul.sld-ap-nav_menu>li a.active {
                color: var(--sld-ap-accent-color);
                background: var(--sld-ap-shading);
                border: 1px solid var(--sld-ap-border-color);
            }

            svg.sld-ap-nav_menu-icon {
                display: inline-block;
                width: 24px;
                height: 24px;
                vertical-align: middle;
                margin-right: 10px;
                color: var(--sld-ap-secondary-color);
            }

            ul.sld-ap-nav_menu>li a.active svg.sld-ap-nav_menu-icon {
                opacity: 1;
            }

            ul.sld-ap-nav_menu>li a svg.sld-ap-nav_menu-icon {
                opacity: .4;
            }

            @media screen and (max-width: 760px) {
                ul.sld-ap-nav_menu>li span.sld-ap-nav_menu-title {
                    display: none
                }

                svg.sld-ap-nav_menu-icon {
                    margin-right: 0;
                }
            }


            /* Main Area styling */

            .sld-ap-main {
                padding: 20px;
            }

            h3.sld-ap-title {
                font-size: var(--sld-ap-font-size-l);
                font-weight: 400;
                margin-bottom: 10px;
            }

            p.sld-ap-description {
                color: var(--sld-ap-secondary-color);
                font-size: var(--sld-ap-font-size-m);
                margin: 0;
            }

            table.sld-ap-table {
                width: 100%;
                margin-top: 20px;
                font-size: var(--sld-ap-font-size-m);
                border-collapse: collapse;
            }

            table.sld-ap-table th {
                color: var(--sld-ap-secondary-color);
                text-transform: capitalize;
            }

            table.sld-ap-table tbody {
                border-bottom: 1px solid var(--sld-ap-border-color);
            }

            table.sld-ap-table th .tippy-content {
                text-transform: none;
            }

            table.sld-ap-table td {
                color: var(--sld-ap-primary-color);
                max-width: 150px;
                min-width: 50px;
                word-wrap: break-word;
            }

            table.sld-ap-table td,
            table.sld-ap-table th {
                border: 1px solid var(--sld-ap-border-color);
                text-align: left;
                padding: 10px;
            }

            table.sld-ap-table tr:nth-child(even) {
                background: var(--sld-ap-shading);
            }

            .sld-ap-creatives_wrapper {
                margin-top: 20px;
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
            }

            .sld-ap-creative_wrapper-card {
                flex-grow: 1;
                justify-content: space-between;
            }

            @media screen and (max-width: 760px) {
                .sld-ap-creative_wrapper-card {
                    width: 100%
                }
            }

            .sld-ap-creative_wrapper-card_box {
                border: 1px solid var(--sld-ap-border-color);
            }

            .sld-ap-creative_wrapper-card_box img {
                height: 160px;
                width: 100%;
                margin: 0;
                object-fit: contain;
            }

            .sld-ap-creative_wrapper-card_box-text {
                padding: 20px;
                word-wrap: break-word;
            }

            .sld-ap-creative_wrapper-card_box-text span {
                font-size: var(--sld-ap-font-size-xs);
                color: var(--sld-ap-secondary-color);
            }

            .sld-ap-creative_wrapper-card_box-text p {
                font-size: var(--sld-ap-font-size-m);
                color: var(--sld-ap-primary-color);
                margin-top: 4px;
                font-weight: 400
            }

            .sld-ap-creative_wrapper-card_box-copy {
                display: flex;
                justify-content: space-around;
                width: 100%;
                gap: 20px;
            }

            .sld-ap-creative_wrapper-card_box-copy a {
                background: var(--sld-ap-accent-color);
                text-decoration: none;
                color: #fff;
                width: auto;
                text-align: center;
                padding: 15px 20px;
                margin-top: 20px;
                width: 100%;
                font-size: var(--sld-ap-font-size-m);
            }

            .sld-ap-creative_wrapper-card_box-copy a[download] {
                background: transparent;
                color: var(--sld-ap-accent-color);
                border: 1px solid var(--sld-ap-accent-color);
            }



            .sld-ap-edit-email {
                margin-top: 20px;
            }

            .sld-ap-edit-email input {
                padding: 10px;
                border: 1px solid var(--sld-ap-border-color);
            }

            .sld-ap-edit-email input[type=text] {
                width: 50%;
                display: inline-block;
            }

            .sld-ap-edit-email .sld-ap-edit-email_button {
                background: var(--sld-ap-accent-color);
                border: transparent;
                color: #fff;
            }

            /* Subheader Styling */

            .sld-ap-info {
                border-color: var(--sld-ap-border-color);
                border-width: 1px 0 1px 0;
                border-style: solid;
                padding: 15px 20px;
                display: flex;
                flex-flow: row wrap;
                justify-content: space-between;
            }

            .sld-ap-info_col {
                display: flex;
                flex-flow: row wrap;
            }

            .sld-ap-mr20 {
                margin-right: 20px;
            }

            @media screen and (max-width: 800px) {
                .sld-ap-info {
                    justify-content: start
                }

                .sld-ap-info_col {
                    flex-direction: column;
                }

                .sld-ap-mr20 {
                    margin-right: 30px;
                    margin-bottom: 10px;
                }
            }


            span.sld-ap-info-box-top {
                font-size: var(--sld-ap-font-size-xs);
                display: block;
                margin-bottom: 5px;
                color: var(--sld-ap-secondary-color);
            }

            p.sld-ap-info-box-bottom,
            .sld-ap-info-box-bottom a {
                font-size: var(--sld-ap-font-size-m);
                color: var(--sld-ap-primary-color);
                font-weight: 400;
                text-decoration: none;
                margin: 5px 0;
                line-height: 14px;
            }


            /* Header styling */

            .sld-ap-header {
                padding: 15px 20px;
                display: flex;
                flex-flow: row wrap;
                justify-content: space-between;
                align-items: center;
            }

            .sld-ap-header h2 {
                font-size: var(--sld-ap-font-size-xl);
                font-weight: 400;
            }

            .sld-ap-header .sld-ap-login {
                display: flex;
                align-items: center;
            }

            .sld-ap-header .sld-ap-login img {
                width: 35px;
                height: 35px;
                display: inline-block;
                vertical-align: top;
                border-radius: 50%;
            }

            .sld-ap-header .sld-ap-login-user {
                display: inline-block;
                vertical-align: middle;
                margin-left: 10px;
            }

            .sld-ap-header .sld-ap-login-user span {
                font-size: var(--sld-ap-font-size-xs);
                color: var(--sld-ap-secondary-color);
            }

            .sld-ap-header .sld-ap-login-user .sld-ap-login-user_name {
                margin-top: 3px;
                font-size: var(--sld-ap-font-size-m);
                color: var(--sld-ap-primary-color);
                font-weight: 400;
            }

            a.sld-ap-login-user_logout {
                display: inline-block;
                margin-top: 5px;
                color: var(--sld-ap-accent-color);
            }

            .sld-ap-login-user_logout svg {
                fill: var(--sld-ap-primary-color);
                margin-left: 10px;
            }


            /* Footer styling */

            .sld-ap-footer {
                padding: 12px 20px;
                border: 1px solid var(--sld-ap-border-color);
                margin: 0 20px 20px;
                height: 15px;
            }

            .sld-ap-footer_notice {
                font-size: var(--sld-ap-font-size-xs);
            }

            .sld-ap-affiliate-status-approved {
                color: #0F9D58 !important;
            }

            .sld-ap .sld_pagination {
                margin-top: 20px;
                font-size: var(--sld-ap-font-size-xs);
            }

            /* Modal styling */
            /* The Modal (background) */
            .sld-ap-modal {
                display: none;
                /* Hidden by default */
                position: fixed;
                /* Stay in place */
                z-index: 1;
                /* Sit on top */
                padding-top: 100px;
                /* Location of the box */
                left: 0;
                top: 0;
                width: 100%;
                /* Full width */
                height: 100%;
                /* Full height */
                overflow: auto;
                /* Enable scroll if needed */
                background-color: rgb(0, 0, 0);
                /* Fallback color */
                background-color: rgba(0, 0, 0, 0.4);
                /* Black w/ opacity */
                z-index: 9999;
            }

            .sld-ap-modal pre {
                max-width: 100% !important;
                white-space: initial;
            }

            /* Modal Content */
            .sld-ap-modal-content {
                background-color: #fefefe;
                margin: auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                max-width: 800px;
            }

            /* flat blue button */
            .sld-ap-modal-copy-content {
                display: inline-block;
                background-color: #4285F4;
                padding: 10px;
                color: white;
                cursor: pointer;
            }

            /* The Close Button */
            .sld-ap-close-modal {
                color: #aaaaaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
            }

            .sld-ap-close-modal:hover,
            .sld-ap-close-modal:focus {
                color: #000;
                text-decoration: none;
                cursor: pointer;
            }

            /* Dashboard */

            .sld-ap-dashboard_flex {
                border-color: var(--sld-ap-border-color);
                padding: 20px 0 0;
                display: flex;
                flex-flow: row wrap;
                justify-content: space-between;
                column-gap: 20px;

            }

            .sld-ap-dashboard_item {
                flex: 1 1 0px;
                border: 1px solid var(--sld-ap-border-color);
                border-radius: var(--sld-ap-border-radius);
                padding: 20px;
                min-width: 100px;
                margin-bottom: 20px;
            }

            @media only screen and (max-width: 800px) {
                .sld-ap-dashboard_item.full {
                    flex: 100%
                }
            }

            .sld-ap-dashboard_item-subtitle {
                font-size: var(--sld-ap-font-size-xs);
                color: var(--sld-ap-secondary-color);
                margin-bottom: 3px;
            }

            .sld-ap-dashboard_item-value {
                font-size: var(--sld-ap-font-size-xl);
                color: var(--sld-ap-secondary-primary);
            }

            .sld-ap-dashboard_item-chart {
                margin: 20px 0;
            }

            /* Solid Tooltips */
            .sld-tooltip {
                display: inline-block;
                vertical-align: sub;
                width: 15px;
            }

            .sld-tooltip-box {
                padding: 5px 9px;
            }

            .sld-tooltip_heading {
                margin-bottom: 3px;
                font-size: 14px;
                color: #000000 !important;
            }

            .sld-tooltip_sub-heading {
                font-size: 13px;
                color: #7d7d7d !important;
            }

            .sld-tooltip_body {
                margin: 5px 0;
                font-size: 13px;
                color: #606060 !important;
            }

            .sld-tooltip_hint {
                border-top: 1px solid #dedede;
                padding-top: 9px;
                font-size: 12px;
                color: #7c7c7c !important;
            }

            .sld-tooltip_hint a {
                color: #2271b1 !important;
                text-decoration: underline;
            }

            .card .sld-store-credit-transaction-type.sld-debit {
                color: white;
                font-size: 28px;
            }

            /* CSS To support Settings::AFFILIATE_PORTAL_TABS_TO_HIDE */
            li.sld-hide-tab {
                display: none !important;
            }

            /* Admin Preview */
            .solid-affiliate_admin-affiliate-portal-preview li {
                display: list-item !important;
                position: relative;
            }

            .solid-affiliate_admin-affiliate-portal-preview li.sld-hide-tab a {
                background: #ebebeb;
                transition: all 200ms ease-in-out;
            }

            .solid-affiliate_admin-affiliate-portal-preview li.sld-hide-tab a:hover {
                background: #E9E9E9;
            }

            .solid-affiliate_admin-affiliate-portal-preview li.sld-hide-tab:after {
                content: url("data:image/svg+xml,%3Csvg width='18' height='18' viewBox='0 0 18 18' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M5.32426 5.36502C4.05205 6.14928 2.84892 7.36092 1.71484 8.99993C3.88946 12.1428 6.31803 13.7142 9.00056 13.7142C10.2203 13.7142 11.3876 13.3893 12.5024 12.7395M13.8635 11.7622C14.7034 11.0412 15.511 10.1204 16.2863 8.99993C14.1117 5.85707 11.6831 4.28564 9.00056 4.28564C8.28871 4.28564 7.59474 4.3963 6.91865 4.61762' stroke='%23A7A7A7' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M3.42969 3.42847L14.5725 14.6322' stroke='%23A7A7A7' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E%0A");
                width: 18px;
                height: 18px;
                background: #fff;
                border-radius: 20px;
                padding: 4.5px;
                position: absolute;
                right: 18px;
                top: calc(50% - 14px);
            }

            .solid-affiliate_admin-affiliate-portal-preview li.sld-hide-tab:hover:before {
                visibility: visible;
            }

            .solid-affiliate_admin-affiliate-portal-preview li.sld-hide-tab:before {
                content: 'Tab is hidden from the affiliate portal.';
                position: absolute;
                background: #fff;
                width: 120px;
                top: -30px;
                border: 1px solid #dedede;
                border-radius: var(--sld-radius-sm);
                visibility: hidden;
                font-size: 11px;
                line-height: 14px;
                font-weight: 400;
                right: -20px;
                z-index: 999;
                padding: 6px;
                box-shadow: 1px 10px 17px -8px rgba(0, 0, 0, 0.13);
                -webkit-box-shadow: 1px 10px 17px -8px rgba(0, 0, 0, 0.13);
                -moz-box-shadow: 1px 10px 17px -8px rgba(0, 0, 0, 0.13);
            }

            .slugs-counter {
                background: var(--sld-ap-shading);
                border: 1px solid var(--sld-ap-border-color);
                font-size: 14px;
                width: fit-content;
                padding: 5px;
                border-radius: 4px;
                margin: 10px 0;
            }

            .slugs-counter strong {
                font-weight: 400 !important;
            }

            .sld_store-credit-card {
                padding: 10px;
                margin: 20px 0;
                border: 1px solid var(--sld-ap-border-color);
                width: fit-content;
            }
        </style>

    <?php
        $res = ob_get_clean();
        return $res;
    }

    /**
     * Renders a chart.
     *
     * @param AffiliatePortalViewInterface $i
     * @return string
     */
    public static function render_dashboard_chart_one($i)
    {
        $random_id = 'solid-affiliate-chart-' . RandomData::string();
        $date_range = new PresetDateRangeParams(['preset_date_range' => 'last_30_days']);

        ob_start();
    ?>
        <div class='sld_chart-container' style="padding: 0px">
            <canvas id="<?php echo ($random_id) ?>"></canvas>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {

                var ctx = document.getElementById('<?php echo ($random_id) ?>');
                var myChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        datasets: [{
                            label: "<?php _e('Visits', 'solid-affiliate') ?>",
                            data: <?php echo (json_encode(ChartData::visits_data('daily', $date_range, [$i->affiliate->id]))) ?>,
                            parsing: {
                                yAxisKey: 'count'
                            },
                            backgroundColor: 'rgba(153, 102, 255, 0.3)',
                            borderColor: 'rgb(153, 102, 255)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        parsing: {
                            xAxisKey: 'date',
                        },
                        scales: {
                            x: {
                                type: 'time',
                                title: 'Date',
                                stacked: true,
                                time: {
                                    unit: 'day',
                                    tooltipFormat: 'do MMM Y'
                                }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    // forces step size to be 50 units
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            });
        </script>
    <?php
        return ob_get_clean();
    }

    /**
     * Renders a chart.
     *
     * @param AffiliatePortalViewInterface $i
     * @return string
     */
    public static function render_dashboard_chart_two($i)
    {
        $random_id = 'solid-affiliate-chart-' . RandomData::string();
        $date_range = new PresetDateRangeParams(['preset_date_range' => 'last_30_days']);

        ob_start();
    ?>
        <div class='sld_chart-container' style="padding: 0px">
            <canvas id="<?php echo ($random_id) ?>"></canvas>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var ctx = document.getElementById('<?php echo ($random_id) ?>');
                var myChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        datasets: [{
                            label: "<?php _e('Referrals', 'solid-affiliate') ?>",
                            data: <?php echo (json_encode(ChartData::referrals_data('daily', $date_range, [$i->affiliate->id]))) ?>,
                            parsing: {
                                yAxisKey: 'count'
                            },
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        spanGaps: true,
                        parsing: {
                            xAxisKey: 'date',
                        },
                        scales: {
                            x: {
                                type: 'time',
                                title: 'Date',
                                stacked: true,
                                time: {
                                    unit: 'day',
                                    tooltipFormat: 'do MMM Y'
                                }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    // forces step size to be 50 units
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            });
        </script>
<?php
        return ob_get_clean();
    }

    /**
     * Returns the default affiliate tabs.
     *
     * @param string $affiliate_word
     * @return array<array-key, array{0: string, 1: string}>
     */
    public static function default_affiliate_tabs_enum($affiliate_word = 'Affiliate')
    {
        $default_tabs = [
            ['dashboard', __('Dashboard', 'solid-affiliate')],
            ['referrals', __('Referrals', 'solid-affiliate')],
            ['visits', __('Visits', 'solid-affiliate')],
            ['payouts', __('Payouts', 'solid-affiliate')],
            ['coupons', __('Coupons', 'solid-affiliate')],
            ['creatives', __('Creatives', 'solid-affiliate')],
            ['urls', $affiliate_word . ' ' . __('Links', 'solid-affiliate')],
            ['settings', __('Settings', 'solid-affiliate')],
        ];

        /** @var array<array-key, array{0: string, 1: string}> $default_tabs */
        $default_tabs = apply_filters("solid_affiliate/default_affiliate_tabs", $default_tabs);

        return $default_tabs;
    }
}
