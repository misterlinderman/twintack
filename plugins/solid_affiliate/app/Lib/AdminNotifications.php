<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Controllers\AffiliatesController;
use SolidAffiliate\Controllers\PayAffiliatesController;
use SolidAffiliate\Controllers\SetupWizardController;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Models\Affiliate;

/**
 * 
 * @psalm-import-type AdminNotificationData from \SolidAffiliate\Views\Admin\AdminDashboard\V2\RootView
 */
class AdminNotifications
{
    /**
     * Undocumented function
     *
     * @return int
     */
    public static function count()
    {
        //////////////////////////////////////////////////////////////////////
        // Notification: Affiliates Need Approval
        $pending_affiliates_count = Affiliate::count(['status' => 'pending']);
        $affiliates_need_approval = $pending_affiliates_count > 0;
        //////////////////////////////////////////////////////////////////////

        //////////////////////////////////////////////////////////////////////
        // Notification: Affiliates Need Payment
        $r = PayAffiliatesFunctions::data_for_grace_period_referrals();
        $affiliates_owed_count = $r['affiliates_owed_count'];
        $affiliates_need_payment = !empty($affiliates_owed_count);

        //////////////////////////////////////////////////////////////////////

        //////////////////////////////////////////////////////////////////////
        // Notification: Complete the Setup Wizard
        $is_setup_wizard_displayed = SetupWizard::is_displayed();

        //////////////////////////////////////////////////////////////////////
        $array = [$affiliates_need_approval, $affiliates_need_payment, $is_setup_wizard_displayed];
        return count(array_filter($array));
    }

    /**
     * Undocumented function
     * 
     * @param bool $inline
     * 
     * @return string
     */
    public static function render_dashboard_notifications($inline = true)
    {
        $admin_notifications_count = AdminNotifications::count();
        ob_start();
?>
        <style>
            #sld-notification .postbox-header {
                height: 35px;
                padding: 0 15px;
            }

            #sld-notification .hndle {
                height: 35px;
                cursor: pointer;
            }

            #sld-notification .inside {
                padding: 0 !important;
                margin: 0 !important
            }


            #sld-notification .sld-notice {
                border: 0;
                margin: 0 !important;
                padding: 2px 15px !important;
                border-bottom: 1px solid #e1e1e1;
            }

            #sld-notification .sld-notice:last-child {
                border: 0;
            }

            #sld-notification h4 {
                font-size: 1rem;
                font-weight : 400;
            }

            .solid-well {
                padding: 20px;
                margin: 20px 0;
                background-color: #f7f2f2;
                border: 1px solid #dcdcdc;
                border-radius: var(--sld-radius-sm);
                -webkit-box-shadow: 0px 3px 5px 0px rgb(0 0 0 / 5%);
                box-shadow: 0px 3px 5px 0px rgb(0 0 0 / 5%);
            }

            .sld-notice p {
                padding: 0 !important;
                margin: 0.5em 0;
            }

            .solid-well h2 {
                margin-top: 0;
            }

            .solid-well p {
                margin: 5px 0 !important;
            }

            .solid-well .sld-notice {
                margin: 5px 0;
            }

            .update-plugins {
                display: inline-block;
                vertical-align: top;
                box-sizing: border-box;
                margin: 1px 0 -1px 2px;
                padding: 0 5px;
                min-width: 18px;
                height: 18px;
                border-radius: 9px;
                background-color: #ca4a1f;
                color: #fff;
                font-size: 11px;
                line-height: 1.6;
                text-align: center;
                z-index: 26;
            }

            .update-plugins.disabled {
                background-color: #a0a0a0;
            }
        </style>

        <div id="sld-notification" class="postbox">
            <div class="postbox-header">
                <div class="hndle">
                    <?php if ($admin_notifications_count > 0) { ?>
                        <h4><?php _e('Notifications', 'solid-affiliate') ?> <span class="update-plugins count-1"><span class="plugin-count"><?php echo ($admin_notifications_count) ?></span></span></h4>
                    <?php } else { ?>
                        <h4><?php _e('Notifications', 'solid-affiliate') ?> <span class="update-plugins count-1 disabled"><span class="plugin-count"><?php echo ($admin_notifications_count) ?></span></span></h4>
                    <?php } ?>
                </div>
                <div class="handle-actions hide-if-no-js">

                </div>
            </div>
            <div class="inside">
                <div class=''>
                    <?php if ($admin_notifications_count > 0) { ?>
                        <?php echo self::render_complete_setup_wizard_nag(true); ?>
                        <?php echo self::render_affiliates_pending_approval_nag(true); ?>
                        <?php echo self::render_affiliates_need_payment_nag(true); ?>
                    <?php } else { ?>
                        <div class="sld-notice">
                            <h4><?php _e('You currently have no admin notifications. Have a great day!', 'solid-affiliate') ?></h4>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

    <?php
        return ob_get_clean();
    }

    /**
     * Undocumented function
     * 
     * @param bool $inline
     *
     * @return string
     */
    public static function render_affiliates_pending_approval_nag($inline = true)
    {
        $pending_affiliates_count = Affiliate::count(['status' => 'pending']);
        $classes = 'sld-notice';
        if ($inline) {
            $classes = $classes . ' inline';
        }

        $pending_affiliates_path = add_query_arg(['status' => 'pending'], URLs::admin_path(Affiliate::ADMIN_PAGE_KEY));
        if ($pending_affiliates_count == 1) {
            $message = sprintf(__('You have %1$d Affiliate pending approval', 'solid-affiliate'), $pending_affiliates_count);
        } else {
            $message = sprintf(__('You have %1$d Affiliates pending approval', 'solid-affiliate'), $pending_affiliates_count);
        }
        ob_start();
    ?>
        <?php if ($pending_affiliates_count > 0) { ?>
            <div class='<?php echo $classes ?>'>
                <p><strong><?php echo $message ?></strong><br />
                    <?php echo sprintf(__('Approve or reject them in <a href="%1$s">Manage Pending Affiliates</a>.', 'solid-affiliate'), $pending_affiliates_path) ?>
            </div>
        <?php } ?>
    <?php
        return ob_get_clean();
    }

    /**
     * Undocumented function
     * 
     * @param bool $inline
     * 
     * @return string
     */
    public static function render_affiliates_need_payment_nag($inline = true)
    {
        $r = PayAffiliatesFunctions::data_for_grace_period_referrals();
        $grace_period_days = $r['grace_period_days'];
        $total_payout_formatted = $r['amount_owed_formatted'];
        $affiliates_owed_count = $r['affiliates_owed_count'];

        $pay_affiliates_path = URLs::admin_path(PayAffiliatesController::ADMIN_PAGE_KEY);

        $classes = 'sld-notice';
        if ($inline) {
            $classes = $classes . ' inline';
        }
        ob_start();
    ?>
        <?php if ($affiliates_owed_count > 0) { ?>
            <div class='<?php echo $classes ?>'>
                <p><strong><?php echo sprintf(__('A total of %1$s is due to %2$d Affiliate(s) for unpaid Referrals older than %3$d days.', 'solid-affiliate'), $total_payout_formatted, $affiliates_owed_count, $grace_period_days) ?></strong></p>
                <p><?php echo sprintf(__('You can issue Payouts using the <a href="%1$s">Pay Affiliates Tool</a>.', 'solid-affiliate'), $pay_affiliates_path) ?></p>
            </div>
        <?php } ?>
    <?php
        return ob_get_clean();
    }

    /**
     * Undocumented function
     * 
     * @param bool $inline
     * 
     * @return string
     */
    public static function render_complete_setup_wizard_nag($inline = true)
    {
        $is_setup_wizard_displayed = SetupWizard::is_displayed();

        $setup_wizard_path = URLs::admin_path('solid-affiliate-admin');

        $classes = 'sld-notice';
        if ($inline) {
            $classes = $classes . ' inline';
        }
        ob_start();
    ?>
        <?php if ($is_setup_wizard_displayed) { ?>
            <div class='<?php echo $classes ?>'>
                <p><?php echo sprintf(__('Please complete the <a href="%1$s">Setup Wizard</a>.', 'solid-affiliate'), $setup_wizard_path) ?></p>
            </div>
        <?php } ?>
<?php
        return ob_get_clean();
    }

    ////////////////////////////////////////////////
    // V2 - Data Driven
    ////////////////////////////////////////////////

    /**
     * @return AdminNotificationData[]
     */
    public static function get_admin_notifications_data()
    {
        $data = [];

        // Is there currently a background job for creating affiliates via the setup wizard?
        if (SetupWizardController::is_there_a_background_job_creating_affiliates()) {
            $d = [
                'type' => 'background_job_creating_affiliates',
                'title' => "<div class='sld-spinner white'></div>" .__('Currently creating affiliates', 'solid-affiliate'),
                'html_message' =>  __("Affiliate accounts are currently being created in the background. This may take a few minutes.", 'solid-affiliate'),
                // add an svg spinner to the message
                'action_1' => [
                    'label' => 'View Your Affiliates',
                    'url' => URLs::index(Affiliate::class),
                ],
            ];

            $data[] = $d;
        }

        // Complete Setup Wizard
        $is_setup_wizard_displayed = SetupWizard::is_displayed();
        if ($is_setup_wizard_displayed) {
            $d = [
                'type' => 'setup_wizard',
                'title' => __('Complete the setup wizard', 'solid-affiliate'),
                'html_message' => __('Finish your Solid Affiliate configuration.', 'solid-affiliate'),
                'action_1' => [
                    'label' => 'Open Setup Wizard',
                    'url' => URLs::admin_path('solid-affiliate-admin'),
                ],
            ];

            $data[] = $d;
        }

        // Affiliates Pending Approval 
        $pending_affiliates_count = Affiliate::count(['status' => 'pending']);
        if ($pending_affiliates_count > 0) {

            if ($pending_affiliates_count == 1) {
                $message = sprintf(__('You have %1$d Affiliate pending approval.', 'solid-affiliate'), $pending_affiliates_count);
            } else {
                $message = sprintf(__('You have %1$d Affiliates pending approval.', 'solid-affiliate'), $pending_affiliates_count);
            }

            $d = [
                'type' => 'affiliates_pending_approval',
                'title' => __('New affiliate applications', 'solid-affiliate'),
                'html_message' => $message,
                'action_1' => [
                    'label' => __('Review Pending Affiliates', 'solid-affiliate'),
                    'url' => add_query_arg(['status' => 'pending'], URLs::admin_path(Affiliate::ADMIN_PAGE_KEY)),
                ],
                // 'action_2' => [
                //     'label' => 'Approve all',
                //     'url' => 'TODO',
                // ],
            ];

            $data[] = $d;
        }

        // Affiliates Need Payment
        $r = PayAffiliatesFunctions::data_for_grace_period_referrals();
        $grace_period_days = $r['grace_period_days'];
        $total_payout_formatted = $r['amount_owed_formatted'];
        $affiliates_owed_count = $r['affiliates_owed_count'];
        if ($affiliates_owed_count > 0) {
            if ($affiliates_owed_count == 1) {
                $message = sprintf(__('A total of %1$s is due to %2$d Affiliate for unpaid Referrals older than %3$d days.', 'solid-affiliate'), $total_payout_formatted, $affiliates_owed_count, $grace_period_days);
            } else {
                $message = sprintf(__('A total of %1$s is due to %2$d Affiliates for unpaid Referrals older than %3$d days.', 'solid-affiliate'), $total_payout_formatted, $affiliates_owed_count, $grace_period_days);
            }
            $d = [
                'type' => 'affiliates_need_payment',
                'title' => __('New payout is ready', 'solid-affiliate'),
                'html_message' => $message,
                'action_1' => [
                    'label' => __('Open Pay Affiliates Tool', 'solid-affiliate'),
                    'url' => URLs::admin_path(PayAffiliatesController::ADMIN_PAGE_KEY),
                ],
            ];

            $data[] = $d;
        }

        return $data;
    }
}