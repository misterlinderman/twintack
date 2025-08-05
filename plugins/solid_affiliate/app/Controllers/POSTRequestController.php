<?php

namespace SolidAffiliate\Controllers;

use SolidAffiliate\Lib\CustomAffiliateSlugs\AffiliateCustomSlugBase;
use SolidAffiliate\Lib\CustomAffiliateSlugs\CustomSlugControllerFunctions;
use SolidAffiliate\Lib\Tags;
use SolidAffiliate\Lib\VO\RouteDescription;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateGroup;
use SolidAffiliate\Models\AffiliateMeta;
use SolidAffiliate\Models\AffiliateProductRate;
use SolidAffiliate\Models\Creative;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\Referral;

/**
 * POSTRequestController
 *
 * @author Mike Holubowski <https://www.github.com/mholubowski>
 * @package solid-affiliate
 * @version 1.0.0
 * 
 * @psalm-suppress PropertyNotSetInConstructor
 */
class POSTRequestController
{
    /**
     * This function is responsible for routing all POST requests.
     * 
     * It does this by matching RouteDescriptions against the $_POST global array.
     * 
     * A real example:
     * 
     * We have a form for creating a new Referral in the admin dashboard.
     * The form submit button contains the route key example: <submit name="submit_referral">.
     * When the form is submitted, a POST request is sent and eventually this function is called.
     * The "submit_referrals" key is matched against the RouteDescriptions.
     * 
     * Route matching.
     * =================
     * The simplest RouteDescription matches if a key simply exists in the $_POST request:
     * 
     *   new RouteDescription([
     *       'post_param_key' => AffiliatePortalController::POST_PARAM_SUBMIT_AFFILIATE_REGISTRATION,
     *       'nonce' => AffiliatePortalController::NONCE_SUBMIT_AFFILIATE_REGISTRATION,
     *       'callback' => function () {
     *           AffiliatePortalController::POST_affiliate_registration_handler();
     *       }
     *   ]),
     * 
     * 
     * The RouteDescription can also be matched based on multiple key-value pairs in the $_POST request.
     * Take this as an example:
     * 
     *   new RouteDescription([
     *       'post_param_key' => 'page',
     *       'post_param_val' => Affiliate::ADMIN_PAGE_KEY,
     *       'post_param_key_b' => 'action',
     *       'post_param_val_b' => ['Approve', 'Reject', 'Delete'],
     *       'nonce' => 'bulk-affiliates', // nonce is "bulk-{plural_from_table_config}"
     *       'callback' => function () {
     *           AffiliatesController::POST_admin_table_bulk_actions_handler();
     *       }
     *   ]),
     * 
     * @return void
     */
    public static function route_post_request_to_callback_function()
    {
        if (!isset($_SERVER['REQUEST_METHOD'])) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $routes = [
            // AffiliatePortal
            new RouteDescription([
                'post_param_key' => AffiliatePortalController::POST_PARAM_SUBMIT_AFFILIATE_REGISTRATION,
                'nonce' => AffiliatePortalController::NONCE_SUBMIT_AFFILIATE_REGISTRATION,
                'callback' => function () {
                    AffiliatePortalController::POST_affiliate_registration_handler();
                }
            ]),
            new RouteDescription([
                'post_param_key' => AffiliatePortalController::POST_PARAM_SUBMIT_AFFILIATE_LOGIN,
                'nonce' => AffiliatePortalController::NONCE_SUBMIT_AFFILIATE_LOGIN,
                'callback' => function () {
                    AffiliatePortalController::POST_affiliate_login_handler();
                }
            ]),
            new RouteDescription([
                'post_param_key' => AffiliatePortalController::POST_PARAM_SUBMIT_UPDATE_SETTINGS,
                'nonce' => AffiliatePortalController::NONCE_SUBMIT_UPDATE_SETTINGS,
                'callback' => function () {
                    AffiliatePortalController::POST_affiliate_update_settings_handler();
                }
            ]),

            // Affiliates
            new RouteDescription([
                'post_param_key' => AffiliatesController::POST_PARAM_SUBMIT_AFFILIATE,
                'nonce' => AffiliatesController::NONCE_SUBMIT_AFFILIATE,
                'callback' => function () {
                    AffiliatesController::POST_admin_create_and_update_handler();
                }
            ]),
            new RouteDescription([
                'post_param_key' => AffiliatesController::POST_PARAM_DELETE_AFFILIATE,
                'nonce' => AffiliatesController::NONCE_DELETE_AFFILIATE,
                'callback' => function () {
                    AffiliatesController::POST_admin_delete_handler();
                }
            ]),

            // Affiliate Custom Slugs
            new RouteDescription([
                'post_param_key' => AffiliateCustomSlugBase::POST_PARAM_NEW_CUSTOM_SLUG_FOR_AFFILIATE,
                'nonce' => AffiliateCustomSlugBase::NONCE_NEW_CUSTOM_SLUG_FOR_AFFILIATE,
                'callback' => function () {
                    CustomSlugControllerFunctions::POST_create_custom_slug_for_affiliate();
                }
            ]),
            new RouteDescription([
                'post_param_key' => AffiliateCustomSlugBase::POST_PARAM_DELETE_AFFILIATE_CUSTOM_SLUG,
                'nonce' => AffiliateCustomSlugBase::NONCE_DELETE_AFFILIATE_CUSTOM_SLUG,
                'callback' => function () {
                    CustomSlugControllerFunctions::POST_delete_custom_slug_for_affiliate();
                }
            ]),
            new RouteDescription([
                'post_param_key' => AffiliateCustomSlugBase::POST_PARAM_AFFILIATE_CAN_EDIT_CUSTOM_SLUGS,
                'nonce' => AffiliateCustomSlugBase::NONCE_AFFILIATE_CAN_EDIT_SLUGS,
                'callback' => function () {
                    CustomSlugControllerFunctions::POST_edit_affiliate_can_edit_slugs();
                }
            ]),

            // Affiliate Groups
            new RouteDescription([
                'post_param_key' => AffiliateGroupsController::POST_PARAM_SUBMIT_AFFILIATE_GROUP,
                'nonce' => AffiliateGroupsController::NONCE_SUBMIT_AFFILIATE,
                'callback' => function () {
                    AffiliateGroupsController::POST_admin_create_and_update_handler();
                }
            ]),
            new RouteDescription([
                'post_param_key' => AffiliateGroupsController::POST_PARAM_DELETE,
                'nonce' => AffiliateGroupsController::NONCE_DELETE,
                'callback' => function () {
                    AffiliateGroupsController::POST_admin_delete_handler();
                }
            ]),

            // Referrals
            new RouteDescription([
                'post_param_key' => ReferralsController::POST_PARAM_SUBMIT_REFERRAL,
                'nonce' => ReferralsController::NONCE_SUBMIT_REFERRAL,
                'callback' => function () {
                    ReferralsController::POST_admin_create_and_update_handler();
                }
            ]),
            new RouteDescription([
                'post_param_key' => ReferralsController::POST_PARAM_DELETE_REFERRAL,
                'nonce' => ReferralsController::NONCE_DELETE_REFERRAL,
                'callback' => function () {
                    ReferralsController::POST_admin_delete_handler();
                }
            ]),


            // Payouts
            new RouteDescription([
                'post_param_key' => PayoutsController::POST_PARAM_SUBMIT_PAYOUT,
                'nonce' => PayoutsController::NONCE_SUBMIT_PAYOUT,
                'callback' => function () {
                    PayoutsController::POST_admin_create_and_update_handler();
                }
            ]),
            new RouteDescription([
                'post_param_key' => PayoutsController::POST_PARAM_DELETE_PAYOUT,
                'nonce' => PayoutsController::NONCE_DELETE_PAYOUT,
                'callback' => function () {
                    PayoutsController::POST_admin_delete_handler();
                }
            ]),


            // Visits
            new RouteDescription([
                'post_param_key' => VisitsController::POST_PARAM_SUBMIT_VISIT,
                'nonce' => VisitsController::NONCE_SUBMIT_VISIT,
                'callback' => function () {
                    VisitsController::POST_admin_create_and_update_handler();
                }
            ]),
            new RouteDescription([
                'post_param_key' => VisitsController::POST_PARAM_DELETE_VISIT,
                'nonce' => VisitsController::NONCE_DELETE_VISIT,
                'callback' => function () {
                    VisitsController::POST_admin_delete_handler();
                }
            ]),
            new RouteDescription([
                'post_param_key' => VisitsController::POST_PARAM_DELETE_UNCONVERTED_VISITS,
                'nonce' => VisitsController::NONCE_DELETE_UNCONVERTED_VISITS,
                'callback' => function () {
                    VisitsController::POST_admin_delete_unconverted_handler();
                }
            ]),


            // Creatives
            new RouteDescription([
                'post_param_key' => CreativesController::POST_PARAM_SUBMIT_CREATIVE,
                'nonce' => CreativesController::NONCE_SUBMIT_CREATIVE,
                'callback' => function () {
                    CreativesController::POST_admin_create_and_update_handler();
                }
            ]),
            new RouteDescription([
                'post_param_key' => CreativesController::POST_PARAM_DELETE_CREATIVE,
                'nonce' => CreativesController::NONCE_DELETE_CREATIVE,
                'callback' => function () {
                    CreativesController::POST_admin_delete_handler();
                }
            ]),

            // AffiliateProductRates
            new RouteDescription([
                'post_param_key' => AffiliateProductRatesController::POST_PARAM_SUBMIT,
                'nonce' => AffiliateProductRatesController::NONCE_SUBMIT,
                'callback' => function () {
                    AffiliateProductRatesController::POST_admin_create_and_update_handler();
                }
            ]),
            new RouteDescription([
                'post_param_key' => AffiliateProductRatesController::POST_PARAM_DELETE,
                'nonce' => AffiliateProductRatesController::NONCE_DELETE,
                'callback' => function () {
                    AffiliateProductRatesController::POST_admin_delete_handler();
                }
            ]),


            // Pay Affiliates
            new RouteDescription([
                'post_param_key' => PayAffiliatesController::POST_PARAM_SUBMIT_BULK_PAYOUT_PREVIEW,
                'nonce' => PayAffiliatesController::NONCE_SUBMIT_BULK_PAYOUT_PREVIEW,
                'callback' => function () {
                    PayAffiliatesController::POST_create_bulk_payout_preview();
                }
            ]),
            new RouteDescription([
                'post_param_key' => PayAffiliatesController::POST_PARAM_SUBMIT_BULK_PAYOUT,
                'nonce' => PayAffiliatesController::NONCE_SUBMIT_BULK_PAYOUT,
                'callback' => function () {
                    PayAffiliatesController::POST_create_bulk_payout();
                }
            ]),
            new RouteDescription([
                'post_param_key' => PayAffiliatesController::POST_PARAM_DOWNLOAD_BULK_PAYOUT_CSV,
                'nonce' => PayAffiliatesController::NONCE_DOWNLOAD_BULK_PAYOUT_CSV,
                'callback' => function () {
                    PayAffiliatesController::POST_download_bulk_payout_csv();
                }
            ]),

            // Admin Settings
            new RouteDescription([
                'post_param_key' => SettingsController::POST_PARAM_SUBMIT_SETTINGS,
                'nonce' => SettingsController::NONCE_SUBMIT_SETTINGS,
                'callback' => function () {
                    SettingsController::POST_update_admin_settings();
                }
            ]),

            // Admin Reports
            new RouteDescription([
                'post_param_key' => AdminReportsController::PARAM_KEY_SUBMIT_ADMIN_REPORTS_FILTERS,
                'nonce' => AdminReportsController::NONCE_SUBMIT_ADMIN_REPORTS_FILTERS,
                'callback' => function () {
                    AdminReportsController::POST_admin_reports_filters();
                }
            ]),

            // Admin List Tables
            new RouteDescription([
                'post_param_key' => 'page',
                'post_param_val' => Affiliate::ADMIN_PAGE_KEY,
                'post_param_key_b' => 'action',
                'post_param_val_b' => ['Approve', 'Reject', 'Delete'],
                'nonce' => 'bulk-affiliates', // nonce is "bulk-{plural_from_table_config}"
                'callback' => function () {
                    AffiliatesController::POST_admin_table_bulk_actions_handler();
                }
            ]),
            new RouteDescription([
                'post_param_key' => 'page',
                'post_param_val' => AffiliateGroup::ADMIN_PAGE_KEY,
                'post_param_key_b' => 'action',
                'post_param_val_b' => ['Delete'],
                'nonce' => 'bulk-affiliategroups', // nonce is "bulk-{plural_from_table_config}"
                'callback' => function () {
                    AffiliateGroupsController::POST_admin_table_bulk_actions_handler();
                }
            ]),
            new RouteDescription([
                'post_param_key' => 'page',
                'post_param_val' => Referral::ADMIN_PAGE_KEY,
                'post_param_key_b' => 'action',
                'post_param_val_b' => ['Approve', 'Reject', 'Mark as Paid', 'Mark as Unpaid', 'Delete'],
                'nonce' => 'bulk-referrals', // nonce is "bulk-{plural_from_table_config}"
                'callback' => function () {
                    ReferralsController::POST_admin_table_bulk_actions_handler();
                },
            ]),
            new RouteDescription([
                'post_param_key' => 'page',
                'post_param_val' => Payout::ADMIN_PAGE_KEY,
                'post_param_key_b' => 'action',
                'post_param_val_b' => ['Delete'],
                'nonce' => 'bulk-payouts', // nonce is "bulk-{plural_from_table_config}"
                'callback' => function () {
                    PayoutsController::POST_admin_table_bulk_actions_handler();
                },
            ]),
            new RouteDescription([
                'post_param_key' => 'page',
                'post_param_val' => Creative::ADMIN_PAGE_KEY,
                'post_param_key_b' => 'action',
                'post_param_val_b' => ['Activate', 'Deactivate', 'Delete'],
                'nonce' => 'bulk-creatives', // nonce is "bulk-{plural_from_table_config}"
                'callback' => function () {
                    CreativesController::POST_admin_table_bulk_actions_handler();
                }
            ]),
            new RouteDescription([
                'post_param_key' => 'page',
                'post_param_val' => AffiliateProductRate::ADMIN_PAGE_KEY,
                'post_param_key_b' => 'action',
                'post_param_val_b' => ['Delete'],
                'nonce' => 'bulk-affiliateproductrates', // nonce is "bulk-{plural_from_table_config}"
                'callback' => function () {
                    AffiliateProductRatesController::POST_admin_table_bulk_actions_handler();
                }
            ]),

            // AffiliateMeta
            new RouteDescription([
                'post_param_key' => AffiliateMeta::POST_PARAM_SUBMIT_MISC_META,
                'nonce' => AffiliateMeta::NONCE_SUBMIT_MISC_META,
                'callback' => function () {
                    AffiliateMeta::POST_update_misc_meta_handler();
                },
            ]),

            // Affiliate Tags
            new RouteDescription([
                'post_param_key' => Tags::POST_KEY_CREATE_TAG,
                'nonce' => Tags::POST_KEY_CREATE_TAG,
                'callback' => function () {
                    Tags::POST_create_tag();
                }
            ]),
            new RouteDescription([
                'post_param_key' => Tags::POST_KEY_DELETE_TAG,
                'nonce' => Tags::POST_KEY_DELETE_TAG,
                'callback' => function () {
                    Tags::POST_delete_tag();
                }
            ]),
            new RouteDescription([
                'post_param_key' => Tags::POST_KEY_UPDATE_SPECIFIC_AFFILIATE_TAGS,
                'nonce' => Tags::POST_KEY_UPDATE_SPECIFIC_AFFILIATE_TAGS,
                'callback' => function () {
                    Tags::POST_update_specific_affiliate_tags();
                }
            ]),

        ];

        /**
         * @var array<RouteDescription> $routes
         */
        $routes = apply_filters("solid_affiliate/PostRequestController/routes", $routes);

        ///////////////////////////////////////////////////////////////////
        // Check for Standard POST request
        foreach ($routes as $route_description) {
            if (self::check_if_route_matches($_POST, $route_description)) {
                self::verify_nonce_and_call_callback($route_description);
            }
        }
    }


    /**
     * Given a $_POST request and a route description, this function will
     * decide if there's a match.
     * 
     * What this function does is actually pretty simple.
     * 
     * It checks if post_param_a and _b both match. The values can be null|string|array<string>. 
     *   If null, anything matches.
     *   If string, need exact match.
     *   if array<string>, one needs to exact match.
     * 
     *   Also post_param_b is optional. It additionally constrains the match.
     *
     * @param array<mixed> $post_request the $_POST global
     * @param RouteDescription $route_description
     * 
     * @return bool
     */
    public static function check_if_route_matches($post_request, $route_description)
    {
        ///////////////
        // post_param_a
        ///////////////////////////////////////////////////
        $does_param_a_value_match = false;

        $does_param_a_exist_in_post_request = isset($post_request[$route_description->post_param_key]);

        if (!$does_param_a_exist_in_post_request) {
            return false;
        }

        if (is_null($route_description->post_param_val)) {
            $does_param_a_value_match = true;
        }

        if (is_string($route_description->post_param_val)) {
            $does_param_a_value_match = ($post_request[$route_description->post_param_key] == $route_description->post_param_val);
        }

        if (is_array($route_description->post_param_val)) {
            $values = $route_description->post_param_val;
            $does_param_a_value_match = in_array($post_request[$route_description->post_param_key], $values);
        }

        $does_param_a_match = $does_param_a_value_match;
        ///////////////////////////////////////////////////

        ///////////////
        // post_param_b
        ///////////////////////////////////////////////////
        $does_param_b_value_match = false;

        if (is_null($route_description->post_param_key_b)) {
            $does_param_b_match = true;
        } else {
            $does_param_b_exist_in_post_request = isset($post_request[$route_description->post_param_key_b]);
            if (is_null($route_description->post_param_val_b)) {
                $does_param_b_value_match = true;
            }

            if (is_string($route_description->post_param_val_b) && isset($post_request[$route_description->post_param_key_b])) {
                $does_param_b_value_match = ($post_request[$route_description->post_param_key_b] == $route_description->post_param_val_b);
            }

            if (is_array($route_description->post_param_val_b) && isset($post_request[$route_description->post_param_key_b])) {
                $values = $route_description->post_param_val_b;
                $does_param_b_value_match = in_array($post_request[$route_description->post_param_key_b], $values);
            }

            $does_param_b_match = $does_param_b_exist_in_post_request && $does_param_b_value_match;
        }

        return $does_param_a_match && $does_param_b_match;
    }

    /** * Verify's nonce and then just calls the proper Controller::Action function for the given RouteDescription.
     *
     * @param RouteDescription $route_description
     * @return void
     */
    public static function verify_nonce_and_call_callback($route_description)
    {
        if (!wp_verify_nonce((string)$_POST['_wpnonce'], $route_description->nonce)) {
            die(__('Solid Affiliate detected an invalid request. This may be due to caching, please refer to this page: https://docs.solidaffiliate.com/does-solid-affiliate-work-with-caching/', 'solid-affiliate'));
        }

        call_user_func($route_description->callback);
    }
}
