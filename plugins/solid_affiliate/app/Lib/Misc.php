<?php

namespace SolidAffiliate\Lib;

use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use SolidAffiliate\Controllers\AdminMenuController;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Referral;

class Misc
{



    /**
     * This function will attempt to find the Affiliate who referred the current visitor, 
     * and then return the user id of that affiliate.
     * 
     * Example: 
     * $id = get_user_id_sld (); defaults user id to "1"
     * $id = get_user_id_sld ( 2 ); defaults user id to "2"
     * 
     * @template TBool of bool
     *
     * @param integer $user_id_to_return_if_no_affiliate_found
     * @param TBool $return_affiliate_if_found
     * @return integer|Affiliate
     * @psalm-return (TBool is true ? integer|Affiliate : integer)
     */
    public static function get_user_id_of_affiliate_who_referred_the_current_visitor($user_id_to_return_if_no_affiliate_found = 1, $return_affiliate_if_found = false)
    {
        // Test to see if SolidAffiliate is installed
        if (class_exists(\SolidAffiliate\Main::class)) {

            // Check to see if there is an Affiliate for the current page load.
            $affiliate = \SolidAffiliate\Lib\VisitTracking::_affiliate_from_request($_REQUEST);
            if (!is_null($affiliate)) {
                return $affiliate->user_id;
            }

            // If not, Check if cookie is already set.
            $visit_id_or_false = \SolidAffiliate\Lib\VisitTracking::get_cookied_visit_id();
            if ($visit_id_or_false) {
                $visit = \SolidAffiliate\Models\Visit::find($visit_id_or_false);
                if (!is_null($visit)) {
                    $affiliate = Affiliate::find($visit->affiliate_id);
                    if (!is_null($affiliate)) {
                        if ($return_affiliate_if_found) {
                            return $affiliate;
                        } else {
                            return $affiliate->user_id;
                        }
                    }
                }
            }
        }
        return $user_id_to_return_if_no_affiliate_found;
    }

    /**
     * @return Affiliate|null
     */
    public static function get_affiliate_who_referred_the_current_visitor()
    {
        $maybe_affiliate = self::get_user_id_of_affiliate_who_referred_the_current_visitor(0, true);
        if ($maybe_affiliate instanceof Affiliate) {
            return $maybe_affiliate;
        } else {
            return null;
        }
    }

    /**
     * @param integer $id
     * 
     * @return void
     */
    public static function handle_deleted_user(int $id)
    {
        $maybe_affiliate = Affiliate::find_where(['user_id' => $id]);
        if ($maybe_affiliate instanceof Affiliate) {
            Affiliate::delete($maybe_affiliate->id, true);
        }
    }

    /**
     * This function will find all duplicate (by order_id and order_source) Referrals and delete all but the first one.
     * It uses the $wpdb global to query the database.
     *
     * @return array<int> A list of all the deleted Referral IDs
     */
    public static function delete_duplicate_referrals()
    {
        global $wpdb;

        /** @var \wpdb $wpdb */
        $prefix = (string)$wpdb->prefix;

        /** @var array $response */
        $response = $wpdb->get_results(
            "SELECT id, order_id, order_source, COUNT(*) AS count
            FROM {$prefix}solid_affiliate_referrals
            WHERE referral_type = 'purchase'
            GROUP BY order_id, order_source
            HAVING count > 1",
            'ARRAY_A'
        );

        // if $response is an array, continue

        /** @psalm-suppress RedundantConditionGivenDocblockType */
        if (is_array($response)) {
            $deleted_referrals = [];
            /** @var array $row */
            foreach ($response as $row) {
                $referrals = Referral::where([
                    'order_id' => $row['order_id'],
                    'order_source' => $row['order_source'],
                ]);
                foreach ($referrals as $referral) {
                    if ($referral->id !== $row['id']) {
                        $deleted_referrals[] = $referral->id;
                        Referral::delete($referral->id, true);
                    }
                }
            }

            sort($deleted_referrals);
            return (array_reverse($deleted_referrals));
        } else {
            return [];
        }
    }

    /**
     * Add plugin action links.
     *
     * Add a link to the settings page on the plugins.php page.
     *
     * @since 1.0.0
     *
     * @param  array  $links List of existing plugin action links.
     * @return array         List of modified plugin action links.
     */
    public static function solid_affiliate_plugin_action_links($links)
    {

        $links = array_merge(array(
            '<a href="' . esc_url(URLs::settings()) . '">' . __('Settings', 'solid-affiliate') . '</a>'
        ), $links);

        if (!License::is_solid_affiliate_activated()) {
            $url = URLs::admin_path('solid-affiliate-license-key-options');
            $link_text = __('Enter a valid license key (updates disabled)', 'solid-affiliate');
            $link = sprintf('<a class="solid-affiliate_plugin-action-link_license-key" href="%1$s" target="_blank">%2$s</a>', esc_url($url), $link_text);
            $links = array_merge(array(
                $link
            ), $links);
        }

        if (SetupWizard::is_displayed()) {
            $setup_wizard_path = URLs::admin_path(AdminMenuController::ROOT_PAGE_KEY, false);

            $links = array_merge(array(
                '<a class="solid-affiliate_plugin-action-link_setup-wizard" href="' . esc_url($setup_wizard_path) . '">' . __('Setup Wizard for Solid Affiliate', 'solid-affiliate') . '</a>'
            ), $links);
        }


        return $links;
    }
}
