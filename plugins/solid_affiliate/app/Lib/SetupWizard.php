<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateGroup;

/**
 * Class SetupWizard
 * 
 * @psalm-import-type SyncedData from \SolidAffiliate\Views\Admin\SetupWizard\V2View
 */
class SetupWizard
{
    const NOT_ACTIVE_VERSION = "Not Active";

    /**
     * This function is called when the plugin is activated.
     * It should redirect to our Setup Wizard, but only the first time the plugin is activated.
     *
     * @return void
     */
    public static function initial_redirect_to_setup_upon_activation()
    {
        if (defined('WP_CLI') && WP_CLI) {
            return;
        }
        if (get_option('solid_affiliate_do_activation_redirect', false)) {
            delete_option('solid_affiliate_do_activation_redirect');
            if (!isset($_GET['activate-multi'])) {
                // $is_setup_wizard_displayed = SetupWizard::is_displayed();
                $setup_wizard_path = URLs::admin_path('solid-affiliate-admin');
                wp_redirect($setup_wizard_path);
            }
        }
    }

    /**
     * Whether or not the setup wizard is currently displayed
     * 
     * @return bool
     */
    public static function is_displayed()
    {
        return (bool)Settings::get(Settings::KEY_IS_SETUP_WIZARD_DISPLAYED);
    }

    /**
     * @return string
     */
    public static function wc_sub_is_active_badge()
    {
        $is_active = self::wc_sub_exists();

        if ($is_active) {
            return sprintf("<span style='font-weight : 400; color: forestgreen;'>%s</span>", __('Installed & activated', 'solid-affiliate'));
        } else {
            return sprintf("<span style='font-weight : 400; color: red;'>%s</span>", __('Not installed', 'solid-affiliate'));
        }
    }


    /**
     * @param string $wc_version
     *
     * @return bool
     */
    private static function wc_active($wc_version)
    {
        return $wc_version != self::NOT_ACTIVE_VERSION;
    }

    /**
     * @return string
     */
    private static function check_for_wc_version()
    {
        if (defined('WC_VERSION')) {
            return WC_VERSION;
        } else {
            return self::NOT_ACTIVE_VERSION;
        }
    }

    /**
     * @return boolean
     */
    public static function is_woocommerce_active()
    {
        return self::wc_active(self::check_for_wc_version());
    }

    /**
     * @return boolean
     */
    public static function is_woocommerce_subscriptions_active()
    {
        return self::wc_sub_exists();
    }

    /**
     * @return bool
     */
    private static function wc_sub_exists()
    {
        return class_exists('WC_Subscriptions');
    }

    /**
     * @param string $title
     * @param string $slug
     * @return array{success: bool, error: string}
     */
    public static function create_affiliate_portal_page($title, $slug)
    {
        $page_name = $slug;
        $page_name = Validators::post_name($page_name);

        if (get_page_by_path($page_name)) {
            // convert this to using sprintf in the translation
            return ['success' => false, 'error' => sprintf(__('A page with the name %1$s already exists.', 'solid-affiliate'), $page_name)];
        } else {
            $post_args = array(
                'post_title' => $title,
                'post_name' => $page_name,
                'post_content' => '[solid_affiliate_portal]', // TODO create content
                'post_status' => 'publish',
                'post_author' => get_current_user_id(),
                'post_type' => 'page',
                'comment_status' => 'closed'
            );
            $either_int_or_wp_error = wp_insert_post($post_args);

            /**
             * @psalm-suppress DocblockTypeContradiction
             */
            if ($either_int_or_wp_error instanceof \WP_Error) {
                return ['success' => false, 'error' => $either_int_or_wp_error->get_error_message()];
            } else {
                $either_settings_updated = Settings::set_many([
                    Settings::KEY_AFFILIATE_PORTAL_PAGE => (int)$either_int_or_wp_error,
                    Settings::KEY_IS_SETUP_WIZARD_AFFILIATE_PORTAL_SETUP_COMPLETE => true
                ]);

                if ($either_settings_updated->isLeft) {
                    return ['success' => false, 'error' => $either_settings_updated->left[0]];
                } else {
                    return ['success' => true, 'error' => ''];
                }
            }
        }
    }

    /**
     * @param 'all-users'|'all-customers' $which_users_to_invite
     * @param string $welcome_email
     * 
     * @return int[] The async action IDs
     */
    public static function enqueue_handle_invite_existing_users($which_users_to_invite, $welcome_email)
    {
        // find all the users that are eligible for affiliate creation  
        $roles = $which_users_to_invite == 'all-customers' ? ['customer'] : [];
        $user_query = self::wp_user_query_for_affiliate_creation($roles);
        // for each of the users loop
        $user_ids = Validators::array_of_int($user_query->get_results());

        // save the welcome email to settings
        Settings::set_many([
            Settings::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_SETUP_WIZARD_INVITE => "You can now officially refer your friends!",
            Settings::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_SETUP_WIZARD_INVITE => $welcome_email
        ]);

        // enqueue the async action batched by 10
        $action_ids = [];
        do {
            $batch = array_splice($user_ids, 0, 10);

            $action_id = Action_Scheduler::enqueue_async_action(
                'solid_affiliate/create_affiliates_for_existing_users',
                ['user_ids' => $batch],
                'solid-affiliate'
            );
            $action_ids[] = $action_id;
        } while (count($user_ids) > 0);

        return $action_ids;
    }

    /**
     * [ ] Create a new affiliate for each user
     * [ ] Put them all in an affiliate group
     * [ ] Create the affiliate group if it doesn't exist
     * [ ] Set all their statuses to 'active' (?)
     * [ ] Schedule: Send an email to each user
     * 
     * Notes from ayman
     *  - Order of users to register should be ASC instead of the default DESC or alphabetical order.. I feel like..
     *
     * @param int[] $user_ids
     * 
     * @return array{success: bool, error: string}
     */
    public static function handle_invite_existing_users($user_ids)
    {
        $either_affiliate_group_id = AffiliateGroup::maybe_create_setup_wizard_affiliate_group();
        if ($either_affiliate_group_id->isLeft) {
            return ['success' => false, 'error' => $either_affiliate_group_id->left[0]];
        } else {
            //////////////////////////////////////////
            // Disable the default email notifications
            remove_action('solid_affiliate/Affiliate/new_registration/success', [\SolidAffiliate\Lib\Email_Notifications::class, 'on_new_affiliate_registration_success'], 10);
            remove_action(DevHelpers::AFFILIATE_APPROVED, [\SolidAffiliate\Lib\Email_Notifications::class, 'on_affiliate_approved'], 10);
            //////////////////////////////////////////

            $affiliate_group_id = (int)$either_affiliate_group_id->right;

            // get wp users by $user_ids
            $users = Validators::arr_of_wp_user(get_users(['include' => $user_ids]));

            foreach ($users as $user) {
                // create an affiliate for the user
                $affiliate_fields = [
                    'payment_email' => '',
                    'status' => 'approved',
                    'registration_notes' => 'This affiliate was created automatically during setup.',
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'affiliate_group_id' => $affiliate_group_id
                ];

                //////////////////////////////////////////
                // TODO PERFORMANCE takes approx 0.2 seconds to create an affiliate for an existing user
                $either_affiliate_id = \SolidAffiliate\Models\AffiliatePortal::create_affiliate_for_existing_user($user->ID, $affiliate_fields, true);
                //////////////////////////////////////////
                if ($either_affiliate_id->isLeft) {
                } else {
                    // send the welcome email
                    $affiliate_id = $either_affiliate_id->right;
                    Email_Notifications::enqueue_setup_wizard_affiliate_welcome_email($affiliate_id);
                }
            }

            return ['success' => true, 'error' => ''];
        }
    }

    /**
     * @param string[] $roles
     * 
     * @return \WP_User_Query
     */
    public static function wp_user_query_for_affiliate_creation($roles = [])
    {
        // All affiliate user_ids
        $affiliate_user_ids = array_map(function ($a) {
            return $a->user_id;
        }, Affiliate::all());

        // Find all users that are not already affiliates.

        $args = array(
            'fields' => 'ids',
            'count_total' => true,
            'no_found_rows' => true,
            'exclude' => $affiliate_user_ids,
            'role__in' => $roles,
            'role__not_in' => ['Administrator']
        );

        /** @psalm-suppress InvalidArgument */
        return new \WP_User_Query($args);
    }

    /**
     * Counts the total amount of users that are eligible for affiliate creation.
     * 
     * @param string[] $roles
     *
     * @return int
     */
    public static function total_eligible_users_for_affiliate_creation($roles = [])
    {
        $user_query = self::wp_user_query_for_affiliate_creation($roles);

        $total_users = $user_query->get_total();

        return $total_users;
    }

    /**
     * @return SyncedData|false
     */
    public static function _parse_synced_data_from_post()
    {
        $raw = filter_input(INPUT_POST, 'syncedData', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

        if (!is_array($raw)) {
            return false;
        }

        $isWooCommerceActive = filter_var($raw['isWooCommerceActive'], FILTER_VALIDATE_BOOLEAN);
        $isSolidAffiliateActive = filter_var($raw['isSolidAffiliateActive'], FILTER_VALIDATE_BOOLEAN);
        $isDefaultReferralRateConfigured = filter_var($raw['isDefaultReferralRateConfigured'], FILTER_VALIDATE_BOOLEAN);
        $isDefaultRecurringReferralRateConfigured = filter_var($raw['isDefaultRecurringReferralRateConfigured'], FILTER_VALIDATE_BOOLEAN);
        $isPortalConfigured = filter_var($raw['isPortalConfigured'], FILTER_VALIDATE_BOOLEAN);
        $inputIsCouponIndividualUse = filter_var($raw['inputIsCouponIndividualUse'], FILTER_VALIDATE_BOOLEAN);
        $inputIsCouponExcludeSaleItems = filter_var($raw['inputIsCouponExcludeSaleItems'], FILTER_VALIDATE_BOOLEAN);

        return [
            'currentPage' => (int)($raw['currentPage'] ?? 1),
            'isWooCommerceActive' => $isWooCommerceActive,
            'inputLicenseKey' => (string)($raw['inputLicenseKey'] ?? ''),
            'isOnKeylessFreeTrial' => License::is_on_keyless_free_trial(),
            'keylessFreeTrialEndsAt' => License::get_keyless_free_trial_end_timestamp(),
            'isSolidAffiliateActive' => $isSolidAffiliateActive,
            'inputDefaultReferralRate' => (float)($raw['inputDefaultReferralRate'] ?? 0),
            'inputDefaultReferralRateType' => isset($raw['inputDefaultReferralRateType']) ? Validators::rate_type($raw['inputDefaultReferralRateType']) : 'percentage',
            'isDefaultReferralRateConfigured' => $isDefaultReferralRateConfigured,
            'inputDefaultRecurringReferralRate' => (float)($raw['inputDefaultRecurringReferralRate'] ?? 0),
            'inputDefaultRecurringReferralRateType' => isset($raw['inputDefaultRecurringReferralRateType']) ? Validators::rate_type($raw['inputDefaultRecurringReferralRateType']) : 'percentage',
            'isDefaultRecurringReferralRateConfigured' => $isDefaultRecurringReferralRateConfigured,
            'inputPortalPageTitle' => (string)($raw['inputPortalPageTitle'] ?? ''),
            'inputPortalSlug' => (string)($raw['inputPortalSlug'] ?? ''),
            'isPortalConfigured' => $isPortalConfigured,
            'inputDefaultEmailFromName' => (string)($raw['inputDefaultEmailFromName'] ?? ''),
            'inputDefaultFromEmail' => (string)($raw['inputDefaultFromEmail'] ?? ''),
            'inputCouponRate' => (float)($raw['inputCouponRate'] ?? 0),
            'inputCouponRateType' => isset($raw['inputCouponRateType']) ? Validators::rate_type($raw['inputCouponRateType']) : 'percentage',
            'inputIsCouponIndividualUse' => $inputIsCouponIndividualUse,
            'inputIsCouponExcludeSaleItems' => $inputIsCouponExcludeSaleItems,
            'inputWhichUsersToInvite' => isset($raw['inputWhichUsersToInvite']) ? Validators::one_of(['all-users', 'all-customers'], 'all-users', $raw['inputWhichUsersToInvite']) : 'all-users',
            'portalPageURL' => (string)($raw['portalPageURL'] ?? ''),
            'totalEligibleUsersForAffiliateCreation' => (int)($raw['totalEligibleUsersForAffiliateCreation'] ?? 0),
            'inputWelcomeEmail' => (string)($raw['inputWelcomeEmail'] ?? ''),
            'errors' => isset($raw['errors']) ? Validators::array_of_string($raw['errors']) : [],
        ];
    }

    /**
     * @param string[] $errors
     * @param int $currentPage
     * @return never
     */
    public static function _handle_setup_step_errors($errors, $currentPage)
    {
        wp_send_json_success([
            'syncedData' => [
                'currentPage' => $currentPage,
                'errors' => $errors
            ]
        ]);
    }

    /**
     * @return never
     */
    public static function handle_setup_wizard_POST()
    {
        $syncedData = self::_parse_synced_data_from_post();
        if (!$syncedData) {
            self::_handle_setup_step_errors(['Invalid data received from the setup wizard. Please contact team@solidaffiliate.com'], 1);
            return;
        }

        // parse the post data and get the 'wizardAction' value
        $wizardAction = (string)filter_input(INPUT_POST, 'wizardAction', FILTER_SANITIZE_STRING);
        $currentPage = $syncedData['currentPage'];

        /////////////////////////////////////////
        // Handle the Default Commision Rate Step
        if ($wizardAction === 'commission-rate') {
            $eitherSettings = Settings::set_many([
                Settings::KEY_REFERRAL_RATE_TYPE => $syncedData['inputDefaultReferralRateType'],
                Settings::KEY_REFERRAL_RATE => $syncedData['inputDefaultReferralRate'],
            ]);
            if ($eitherSettings->isLeft) {
                self::_handle_setup_step_errors($eitherSettings->left, $currentPage);
                return;
            }
        }
        /////////////////////////////////////////

        /////////////////////////////////////////
        // Handle the Default Recurring Commision Rate Step
        if ($wizardAction === 'recurring-commission-rate') {
            $eitherSettings = Settings::set_many([
                Settings::KEY_RECURRING_REFERRAL_RATE_TYPE => $syncedData['inputDefaultRecurringReferralRateType'],
                Settings::KEY_RECURRING_REFERRAL_RATE => $syncedData['inputDefaultRecurringReferralRate'],
            ]);
            if ($eitherSettings->isLeft) {
                self::_handle_setup_step_errors($eitherSettings->left, $currentPage);
                return;
            }
        }

        /////////////////////////////////////////
        // Handle creating affiliate portal page
        if ($wizardAction === 'create-portal-page') {
            $portal_page_title = $syncedData['inputPortalPageTitle'];
            $portal_page_slug = $syncedData['inputPortalSlug'];
            $res = SetupWizard::create_affiliate_portal_page($portal_page_title, $portal_page_slug);
            if (!$res['success']) {
                self::_handle_setup_step_errors((array)$res['error'], $currentPage);
                return;
            }
        }

        /////////////////////////////////////////
        // Handle email settings
        if ($wizardAction === 'email-settings') {
            $eitherSettings = Settings::set_many([
                Settings::KEY_EMAIL_FROM_NAME => $syncedData['inputDefaultEmailFromName'],
                Settings::KEY_FROM_EMAIL => $syncedData['inputDefaultFromEmail'],
            ]);

            if ($eitherSettings->isLeft) {
                self::_handle_setup_step_errors($eitherSettings->left, $currentPage);
            }
        }

        /////////////////////////////////////////
        // Handle coupon settings
        if ($wizardAction === 'coupon-settings') {
            // Step 1) Extract params from the synced data
            $inputCouponRate = $syncedData['inputCouponRate'];
            $inputCouponRateType = $syncedData['inputCouponRateType'];
            $inputIsCouponIndividualUse = $syncedData['inputIsCouponExcludeSaleItems'];
            $inputIsCouponExcludeSaleItems = $syncedData['inputIsCouponIndividualUse'];

            // Step 2) Create the coupon
            $coupon_id = \SolidAffiliate\Addons\AutoCreateCoupons\Addon::create_new_woocommerce_coupon($inputCouponRate, $inputCouponRateType, $inputIsCouponIndividualUse, $inputIsCouponExcludeSaleItems);
            if ($coupon_id === false) {
                self::_handle_setup_step_errors(['There was an error creating the coupon.'], $currentPage);
                return;
            }

            // Step 3) Enable the auto coupons addon
            if (!\SolidAffiliate\Addons\Core::is_addon_enabled(\SolidAffiliate\Addons\AutoCreateCoupons\Addon::ADDON_SLUG)) {
                \SolidAffiliate\Addons\Core::toggle_addon(\SolidAffiliate\Addons\AutoCreateCoupons\Addon::ADDON_SLUG);
            }

            // Step 4) Set the coupon template id
            \SolidAffiliate\Addons\AutoCreateCoupons\Addon::set_coupon_template_id($coupon_id);
        }

        /////////////////////////////////////////
        // Handle Inviting existing users
        if ($wizardAction === 'invite-users') {
            $which_users_to_invite = $syncedData['inputWhichUsersToInvite'];
            $welcome_email = $syncedData['inputWelcomeEmail'] ? (string)$syncedData['inputWelcomeEmail'] : '';
            SetupWizard::enqueue_handle_invite_existing_users($which_users_to_invite, $welcome_email);
        }

        /////////////////////////////////////////
        // Handle License Key Activation
        if ($wizardAction === 'license-key') {
            // get the params out of the synced data
            $license_key = $syncedData['inputLicenseKey'] ? (string)$syncedData['inputLicenseKey'] : '';

            $true_or_error_messages = \SolidAffiliate\Lib\WooSoftwareLicense\WOO_SLT_Options_Interface::handle_activating_license($license_key);

            if ($true_or_error_messages !== true) {
                self::_handle_setup_step_errors($true_or_error_messages, $currentPage);
            }
        }

        $maybe_license_data = \SolidAffiliate\Lib\WooSoftwareLicense\WOO_SLT_Licence::get_license_data();
        $maybe_license_key = $maybe_license_data['key'] ?? '';

        /////////////////////////////////////////
        // Handle completing the setup wizard
        if ($wizardAction === 'complete') {
            // Step 1) Set the setup wizard as completed
            $eitherSettings = Settings::set([Settings::KEY_IS_SETUP_WIZARD_DISPLAYED => false]);
            if ($eitherSettings->isLeft) {
                self::_handle_setup_step_errors($eitherSettings->left, $currentPage);
            } else {
                // Step 2) Redirect to the dashboard page
                $redirect_path = URLs::dashboard_path();
                wp_send_json_success([
                    'redirectUrl' => $redirect_path,
                ]);
                return;
            }
        }
        /////////////////////////////////////////


        // TODO figure this out, make sure it's all synced up properly with the front end
        //////////////////////////////////////////////////////////
        $isWooCommerceSubscriptionsActive = SetupWizard::is_woocommerce_subscriptions_active();
        $totalEligibleUsersForAffiliateCreation = SetupWizard::total_eligible_users_for_affiliate_creation();
        //////////////////////////////////////////////////////////

        wp_send_json_success([
            'wizardAction' => $wizardAction,
            'syncedData' => [
                'errors' => [],
                'currentPage' => $currentPage + 1,
                'isWooCommerceActive' => SetupWizard::is_woocommerce_active(),
                'isWooCommerceSubscriptionsActive' => $isWooCommerceSubscriptionsActive,
                'totalEligibleUsersForAffiliateCreation' => $totalEligibleUsersForAffiliateCreation,
                /////////////////////////////// 
                // inputs
                'inputLicenseKey' => $maybe_license_key,
                'isSolidAffiliateActive' => License::is_solid_affiliate_activated_and_not_expired(),
                'isPortalConfigured' => !empty(Settings::get(Settings::KEY_AFFILIATE_PORTAL_PAGE)),
                'portalPageURL' => get_permalink((int)Settings::get(Settings::KEY_AFFILIATE_PORTAL_PAGE)),
            ]
        ]);
    }
}
