<?php

namespace SolidAffiliate\Controllers;

use SolidAffiliate\Assets;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliatePortal;
use SolidAffiliate\Lib\AffiliatePortalFunctions;
use SolidAffiliate\Lib\AffiliateRegistrationForm\AffiliateRegistrationFormFunctions;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\CustomAffiliateSlugs\AffiliateCustomSlugBase;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\GlobalTypes;
use SolidAffiliate\Lib\Notices;
use SolidAffiliate\Lib\Recaptcha\RecaptchaClient;
use SolidAffiliate\Lib\Recaptcha\VerifyErrorHandling;
use SolidAffiliate\Lib\SchemaFunctions;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\AffiliatePortal\AffiliatePortalRegistrationViewInterface;
use SolidAffiliate\Lib\VO\AffiliatePortalViewInterface;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\Creative;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Visit;
use SolidAffiliate\Views\AffiliatePortal\DashboardView;
use SolidAffiliate\Views\AffiliatePortal\LoginView;
use SolidAffiliate\Views\AffiliatePortal\NoticesView;
use SolidAffiliate\Views\AffiliatePortal\RegistrationView;
use SolidAffiliate\Views\AffiliatePortal\AdminHelperView;

/**
 * AffiliatePortalController
 * @psalm-suppress PropertyNotSetInConstructor
 */
class AffiliatePortalController
{
    const POST_PARAM_SUBMIT_AFFILIATE_REGISTRATION = 'submit_affiliate_registration';
    const NONCE_SUBMIT_AFFILIATE_REGISTRATION = 'solid-affiliate-affiliate-registration';

    const POST_PARAM_SUBMIT_AFFILIATE_LOGIN = 'submit_solid_affiliate_login';
    const NONCE_SUBMIT_AFFILIATE_LOGIN = 'solid-affiliate-affiliate-login';

    const POST_PARAM_SUBMIT_UPDATE_SETTINGS = 'submit_affiliate_portal_update_settings';
    const NONCE_SUBMIT_UPDATE_SETTINGS = 'solid-affiliate-affiliate-update_settings';

    const SUCCESS_MSG_AFFILIATE_REGISTRATION = 'Successfully registered as an Affiliate!';
    const SUCCESS_MSG_AFFILIATE_LOGIN = 'Successfully logged in as an Affiliate';

    const SUCCESS_MSG_UPDATE_SETTINGS = "Successfully updated Affiliate settings.";
    const ERROR_MSG_UPDATE_SETTINGS = "There as an error updating the Affiliate settings.";

    const AFFILIATE_PORTAL_SHORTCODE = 'solid_affiliate_portal';
    const AFFILIATE_PORTAL_LOGIN_SHORTCODE = 'solid_affiliate_portal_login';
    const AFFILIATE_PORTAL_REGISTRATION_SHORTCODE = 'solid_affiliate_portal_registration';
    /**
     * @since 1.0.0
     *
     * @hook solid_affiliate_portal
     *
     * @return void
     */
    public static function register_shortcodes()
    {
        add_shortcode(self::AFFILIATE_PORTAL_SHORTCODE, [AffiliatePortalController::class, 'handle_affiliate_portal_shortcode']);
        add_shortcode(self::AFFILIATE_PORTAL_LOGIN_SHORTCODE, [AffiliatePortalController::class, 'handle_affiliate_portal_login_shortcode']);
        add_shortcode(self::AFFILIATE_PORTAL_REGISTRATION_SHORTCODE, [AffiliatePortalController::class, 'handle_affiliate_portal_registration_shortcode']);

        add_filter('display_post_states', [AffiliatePortalController::class, 'filter_display_post_states'], 10, 2);
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
        if (($post instanceof \WP_Post) && (has_shortcode($post->post_content, self::AFFILIATE_PORTAL_SHORTCODE))) {
            $post_states['solid_affiliate_portal'] = __('Affiliate Portal', 'solid-affiliate');
        }
        return $post_states;
    }

    /**
     * @param array<string, string>|null $attributes
     * @param string|null $content
     * 
     * @return string
     */
    public static function handle_affiliate_portal_shortcode($attributes = null, $content = null)
    {
        Assets::enqueue_affiliate_portal_scripts();

        $maybe_affiliate_group_id = self::affiliate_group_id_from_attrs($attributes);

        if (!is_user_logged_in()) {
            $view_interface = self::_build_registration_interface_from_get_request($_GET, $maybe_affiliate_group_id);
            return self::affiliate_portal_logged_out($view_interface);
        } else {
            $current_user = wp_get_current_user();
            $maybe_affiliate = Affiliate::for_user_id($current_user->ID);
            $view_interface = self::_build_registration_interface_from_user($current_user, $maybe_affiliate_group_id, $_GET);

            return self::affiliate_portal_logged_in($maybe_affiliate, $view_interface);
        }
    }

    /**
     * @param array<string, string>|null $attributes
     * @param string|null $content
     * 
     * @return string
     */
    public static function handle_affiliate_portal_login_shortcode($attributes = null, $content = null)
    {
        if (!is_user_logged_in()) {
            return LoginView::render();
        } else {
            $current_user = wp_get_current_user();
            $maybe_affiliate = Affiliate::for_user_id($current_user->ID);

            if ($maybe_affiliate) {
                return self::_maybe_render_link_to_affiliate_portal_for_logged_in_affiliates();
            } else {
                $heading = __('You are already logged in to the WordPress Site. You can register to be an affiliate via the affiliate registration form provided by the site.', 'solid-affiliate');
                return $heading;
            }
        }
    }

    /**
     * @param array<string, string>|null $attributes
     * @param string|null $content
     * 
     * @return string
     */
    public static function handle_affiliate_portal_registration_shortcode($attributes = null, $content = null)
    {
        Assets::enqueue_recaptcha_js_if_enabled();
        $maybe_affiliate_group_id = self::affiliate_group_id_from_attrs($attributes);

        if (!is_user_logged_in()) {
            $view_interface = self::_build_registration_interface_from_get_request($_GET, $maybe_affiliate_group_id);
            return RegistrationView::render($view_interface);
        } else {
            $current_user = wp_get_current_user();
            $maybe_affiliate = Affiliate::for_user_id($current_user->ID);

            if ($maybe_affiliate) {
                return self::_maybe_render_link_to_affiliate_portal_for_logged_in_affiliates();
            } else {
                $view_interface = self::_build_registration_interface_from_user($current_user, $maybe_affiliate_group_id, $_GET);
                return RegistrationView::render($view_interface);
            }
        }
    }

    /**
     * If the affiliate portal page is setup, then it will render a message and link to affiliate portal. This is meant to point logged in affiliates to their portal when they visit a portal form page that is not the same as their affiliate portal page.
     *
     * @return string
     */
    private static function _maybe_render_link_to_affiliate_portal_for_logged_in_affiliates()
    # TODO: This function will hit the DB twice to render the portal link. Maybe we could change the maybe check to also return link?
    {
        $maybe_permalink = URLs::maybe_affiliate_portal_link();
        if ($maybe_permalink === false) {
            $heading = __('The site admin has not set up the Affiliate Portal. Until the Affiliate Portal has been set up, you will not be able to login.', 'solid-affiliate');
            return $heading;
        } else {
            $heading = __('You are already logged into the affiliate program. View you Affiliate Portal using the link below.', 'solid-affiliate');
            $link_text = __('View your Affiliate Portal', 'solid-affiliate');
            $path = URLs::site_portal_url();
            $link_to_portal = "<a href={$path}>{$link_text}</a>";
            return $heading . '<br /><br />' . $link_to_portal;
        }
    }

    /**
     * @param AffiliatePortalRegistrationViewInterface $view_interface
     * 
     * @return string
     */
    public static function affiliate_portal_logged_out($view_interface)
    {
        $o = "<div class='sld-ap-container'>";
        $o .= "<div class='sld-ap'>";
        $o .= self::maybe_render_affiliate_registration_form($view_interface);
        $o .= AffiliatePortalFunctions::should_render_affiliate_login_form() ? LoginView::render() : '';
        $o .= "</div>";
        $o .= "</div>";
        return $o;
    }

    /**
     * @param array<string, string>|null $attributes
     * 
     * @return int|null 
     */
    public static function affiliate_group_id_from_attrs($attributes)
    {
        if (isset($attributes['affiliate_group_id'])) {
            return (int)$attributes['affiliate_group_id'];
        } else {
            return null;
        }
    }

    /**
     * @return string
     */
    public static function affiliate_portal_notices()
    {
        $o = '';
        if (isset($_GET[Notices::URL_PARAM_MESSAGE])) {
            $message = (string)$_GET[Notices::URL_PARAM_MESSAGE];
            $o .= NoticesView::render($message, 'success');
        }
        if (isset($_GET[Notices::URL_PARAM_ERROR])) {
            $message = urldecode((string)$_GET[Notices::URL_PARAM_ERROR]);
            $o .= NoticesView::render($message, 'error');
        }
        return $o;
    }

    /**
     * @param \SolidAffiliate\Models\Affiliate $affiliate
     * @param bool $is_admin_preview
     *
     * @return string
     */
    public static function render_affiliate_dashboard_with_data($affiliate, $is_admin_preview = false)
    {
        // TODO get counts from database directly
        // TODO support pagination. We don't need all the $visits, just the ones for the current page.
        //      with a maximum of GlobalTypes::AFFILIATE_PORTAL_PAGINATION_PER_PAGE

        // $referrals = $affiliate->referrals();
        // $referrals_count = count($referrals);
        // $payouts = $affiliate->payouts();
        // $payouts_count = count($payouts);
        $default_tab = 'dashboard';
        $current_tab = isset($_GET['tab']) ? (string)$_GET['tab'] : $default_tab;

        $page = isset($_GET[GlobalTypes::PAGINATION_PARAM]) ? (int)$_GET[GlobalTypes::PAGINATION_PARAM] : 1;
        $statuses_of_referrals_to_display = Validators::array_of_string(Settings::get(Settings::KEY_REFERRAL_STATUSES_TO_DISPLAY_TO_AFFILIATES));

        $affiliate_link = Affiliate::default_affiliate_link_for($affiliate);
        $account_email = Affiliate::account_email_for($affiliate);
        $coupon_data = AffiliatePortalFunctions::coupon_data_for_affiliate_id($affiliate->id);

        // Referarls
        // TODO Custom Work for Kim
        // use the new Settings 
        $where_referrals_query =
            [
                'affiliate_id' => (int)$affiliate->id,
                'status' => ['operator' => 'IN', 'value' => $statuses_of_referrals_to_display],
            ];

        $referrals = Referral::paginate(
            ['limit' => GlobalTypes::AFFILIATE_PORTAL_PAGINATION_PER_PAGE, 'page' => ($current_tab == 'referrals' ? $page : 1)],
            array_merge($where_referrals_query, ['order_by' => 'id', 'order' => 'DESC'])
        );

        $referrals_count = Referral::count($where_referrals_query);

        // Payouts
        $payout_args = [
            'affiliate_id' => (int)$affiliate->id,
            'status' => Payout::STATUS_PAID
        ];
        $payouts = Payout::paginate(
            ['limit' => GlobalTypes::AFFILIATE_PORTAL_PAGINATION_PER_PAGE, 'page' => ($current_tab == 'payouts' ? $page : 1)],
            $payout_args
        );
        $payouts_count = Payout::count($payout_args);

        // Visits
        $visits = Visit::paginate(
            ['limit' => GlobalTypes::AFFILIATE_PORTAL_PAGINATION_PER_PAGE, 'page' => ($current_tab == 'visits' ? $page : 1)],
            ['affiliate_id' => (int)$affiliate->id, 'order_by' => 'id', 'order' => 'DESC']
        );

        $visits_count = Visit::count(['affiliate_id' => $affiliate->id]);

        $creatives = Creative::where(['status' => 'active']);

        $total_unpaid_earnings = Affiliate::unpaid_earnings($affiliate);
        $total_paid_earnings = Affiliate::paid_earnings($affiliate);

        $slug_default_display_format = AffiliateCustomSlugBase::get_default_display_format();

        $store_credit_data = AffiliatePortalFunctions::store_credit_data_for_affiliate_id($affiliate->id, $page);

        $affiliate_portal_view_interface = new AffiliatePortalViewInterface(
            [
                'affiliate' => $affiliate,
                'account_email' => $account_email,
                'affiliate_link' => $affiliate_link,
                'referrals' => $referrals,
                'payouts' => $payouts,
                'visits' => $visits,
                'referrals_count' => $referrals_count,
                'payouts_count' => $payouts_count,
                'visits_count' => $visits_count,
                'coupon_data' => $coupon_data,
                'creatives' => $creatives,
                'total_unpaid_earnings' => $total_unpaid_earnings,
                'total_paid_earnings' => $total_paid_earnings,
                'custom_slug_default_display_format' => $slug_default_display_format,
                'store_credit_data' => $store_credit_data,
                'current_tab' => $current_tab,
            ]
        );

        return DashboardView::render($affiliate_portal_view_interface, self::affiliate_portal_notices(), $is_admin_preview);
    }

    /**
     * Builds the interface value object for the registration view from a $_GET request.
     *
     * @param array $get
     * @param int|null $maybe_affiliate_group_id
     *
     * @return AffiliatePortalRegistrationViewInterface
     */
    private static function _build_registration_interface_from_get_request($get, $maybe_affiliate_group_id)
    {
        $data = array_merge(
            self::_registration_form_data($get),
            ['form_values' => self::_build_registration_form_values($get, $maybe_affiliate_group_id)]
        );
        return new AffiliatePortalRegistrationViewInterface($data);
    }

    /**
     * Builds the interface value object for the registration view from a WP_User.
     *
     * @param \WP_User $user
     * @param int|null $maybe_affiliate_group_id
     * @param array $get
     *
     * @return AffiliatePortalRegistrationViewInterface
     */
    private static function _build_registration_interface_from_user($user, $maybe_affiliate_group_id, $get)
    {
        $data = array_merge(
            self::_registration_form_data($get, ['for_logged_in_user' => true]),
            ['form_values' => self::_build_registration_form_values($get, $maybe_affiliate_group_id, $user)]
        );
        return new AffiliatePortalRegistrationViewInterface($data);
    }

    /**
     * An array of all the possible value for the registration form. AffiliateRegistrationFormFunctions::build_custom_data_from_form_submit filters out static schema fields,
     * so those have to be merged in 'manually'. They are ignored by the form if they are not on the current registration schema.
     *
     * @param array $get
     * @param int|null $maybe_affiliate_group_id
     * @param \WP_User $user
     *
     * @return array<string, mixed>
     */
    private static function _build_registration_form_values($get, $maybe_affiliate_group_id, $user = null)
    {
        $custom_form_values = AffiliateRegistrationFormFunctions::build_custom_data_from_form_submit(
            AffiliatePortal::get_affiliate_registration_schema(),
            AffiliatePortal::_legacy_affiliate_registration_schema(),
            $get
        );

        if (is_null($user)) {
            $static_form_values = [
                'user_login' => Validators::str_from_array($get, 'user_login'),
                'user_email' => Validators::str_from_array($get, 'user_email'),
                'payment_email' => Validators::str_from_array($get, 'payment_email'),
                'first_name' => Validators::str_from_array($get, 'first_name'),
                'last_name' => Validators::str_from_array($get, 'last_name'),
                'affiliate_group_id' => $maybe_affiliate_group_id
            ];
        } else {
            $static_form_values = [
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'payment_email' => Validators::str_from_array($get, 'payment_email'),
                'first_name' => Validators::str_from_array($get, 'first_name'),
                'last_name' => Validators::str_from_array($get, 'last_name'),
                'affiliate_group_id' => $maybe_affiliate_group_id
            ];
        }
        return array_merge($custom_form_values, $static_form_values);
    }

    /**
     * Builds an array of data needed to render the registration form.
     *
     * @param array $get
     * @param array{for_logged_in_user?: bool} $args 
     *
     * TODO: Make this a shared type
     * @return array{schema: Schema<string>, form_nonce: string, submit_action: string, affiliate_approval_required: boolean, notices: string, just_submitted: boolean}
     */
    private static function _registration_form_data($get, $args = [])
    {
        if (isset($get['submit_action'])) {
            $just_submitted = $get['submit_action'] === self::POST_PARAM_SUBMIT_AFFILIATE_REGISTRATION;
        } else {
            $just_submitted = false;
        }

        return [
            'affiliate_approval_required' => (bool)Settings::get(Settings::KEY_IS_REQUIRE_AFFILIATE_REGISTRATION_APPROVAL),
            'form_nonce' => self::NONCE_SUBMIT_AFFILIATE_REGISTRATION,
            'notices' => self::affiliate_portal_notices(),
            'schema' => AffiliatePortal::get_affiliate_registration_schema_for_portal_forms($args),
            'submit_action' => self::POST_PARAM_SUBMIT_AFFILIATE_REGISTRATION,
            'just_submitted' => $just_submitted
        ];
    }

    /**
     * @param false|\SolidAffiliate\Models\Affiliate $maybe_affiliate
     * @param AffiliatePortalRegistrationViewInterface $view_interface
     *
     * @return string
     */
    public static function affiliate_portal_logged_in($maybe_affiliate, $view_interface)
    {
        $is_dark_mode = AffiliatePortalFunctions::is_affiliate_portal_dark_mode();

        // Corrected: Only assign the div once, including dark-mode if applicable
        $o = "<div class='sld-ap-container" . ($is_dark_mode ? ' dark-mode' : '') . "'>";
        $o .= self::maybe_render_admin_helper($view_interface);

        // Now, $o should contain the correct structure with dark-mode if necessary
        $o .= "<div class='sld-ap'>";  // No overwriting here

        if ($maybe_affiliate) {
            $o .= self::render_affiliate_dashboard_with_data($maybe_affiliate);
        } else {
            $o .= self::maybe_render_affiliate_registration_form($view_interface);
        }

        $o .= "</div>";  // Close sld-ap
        $o .= "</div>";  // Close sld-ap-container

        return $o;
    }





    /**
     * Renders the Affiliate Portal Registration Form if configured to be shown on the Affiliate Portal.
     *
     * @param AffiliatePortalRegistrationViewInterface $view_interface
     *
     * @return string
     */
    private static function maybe_render_affiliate_registration_form($view_interface)
    {
        if (AffiliatePortalFunctions::should_render_affiliate_registration_form()) {
            return RegistrationView::render($view_interface);
        } else {
            return '';
        }
    }

    /**
     * Renders the Affiliate Portal Admin Helper if configured to be shown on the Affiliate Portal.
     *
     * @param AffiliatePortalRegistrationViewInterface $view_interface
     *
     * @return string
     */
    private static function maybe_render_admin_helper($view_interface)
    {
        $is_current_user_an_admin = (is_user_logged_in() &&
            current_user_can('administrator')
        );

        $should_show_admin_helper = $is_current_user_an_admin;
        if ($should_show_admin_helper) {
            return AdminHelperView::render($view_interface);
        }

        return '';
    }

    ///////////////////////////////////////////////////////////////////////////
    // POST handlers
    ///////////////////////////////////////////////////////////////////////////

    // Portal form logic
    // Affiliate Login form
    // - on success -> [x]redirect to affiliate_portal page
    // - on failure -> [x]redirect back
    // Affiliate Registration form
    // - on success -> [ ]redirect to affiliate_portal page
    // - on failure -> [x]redirect back

    // test accounts on production
    // - (non-affiliate) mike.holubowski+nonaffiliate@gmail.com password
    // - (affiliate) mike.holubowski+test@gmail.com [my password]

    // https://solidaffiliate.com/affiliates/ login modal
    // [x] login modal success
    // [bug] login modal failure
    // https://solidaffiliate.com/affiliates/
    // [x] logged out
    // [x] logged in as affiliate
    // [bug] logged in as non-affiliate
    //    - i think we want to redirect them to affiliate/join ?
    // https://solidaffiliate.com/affiliates/portal
    // [x] logged out
    //   [x] login form success
    //   [x] login form failure
    // [x] logged in as affiliate
    // [x] logged in as non-affiliate
    // https://solidaffiliate.com/affiliates/join/
    // [x] logout works

    /**
     * @return void
     */
    public static function POST_affiliate_login_handler()
    {
        # TODO: If a site renders both forms on the Affiliate Portal, and a user fails a submit on a form, and
        #       after the redirect-to-back decides to fill out the other form, and fails that submit,
        #       then the Affiliate Portal persists the params from the original redirect-to-back URL.
        #       This results in the first form auto-filling its values even though the other form was the one just submitted.

        $login_schema = AffiliatePortal::login_schema();
        $eitherFields = ControllerFunctions::extract_and_validate_POST_params(
            $_POST,
            ['user_email', 'user_pass'],
            $login_schema
        );

        if ($eitherFields->isLeft) {
            // TODO need to hack this here so that multiple messages+errors don't persist between requests. Must be a better way.
            ControllerFunctions::handle_redirecting_and_exit(
                'REDIRECT_BACK',
                $eitherFields->left,
                [],
                'home',
                ControllerFunctions::params_to_persist_on_form_submit($eitherFields->right, $login_schema, self::POST_PARAM_SUBMIT_AFFILIATE_LOGIN),
                AffiliatePortalFunctions::should_replace_params_on_form_submit()
            );
        } else {
            $login_credentials = Validators::LoginCredentials($eitherFields->right);
            $eitherUserID = AffiliatePortalFunctions::attempt_affiliate_login($login_credentials);

            if ($eitherUserID->isLeft) {
                ControllerFunctions::handle_redirecting_and_exit(
                    'REDIRECT_BACK',
                    $eitherUserID->left,
                    [],
                    'home',
                    ControllerFunctions::params_to_persist_on_form_submit($login_credentials, $login_schema, self::POST_PARAM_SUBMIT_AFFILIATE_LOGIN),
                    AffiliatePortalFunctions::should_replace_params_on_form_submit()
                );
            } else {
                ControllerFunctions::handle_redirecting_and_exit(URLs::get_affiliate_portal_path(), [], [__(AffiliatePortalController::SUCCESS_MSG_AFFILIATE_LOGIN, 'solid-affiliate')], 'home');
            }
        }
    }

    /**
     * @return void
     */
    public static function POST_affiliate_registration_handler()
    {
        if ((bool)Settings::get(Settings::KEY_IS_RECAPTCHA_ENABLED_FOR_AFFILIATE_REGISTRATION)) {
            $resp = RecaptchaClient::make_verify_request($_POST, $_SERVER);

            if (!$resp->success) {
                $errors = VerifyErrorHandling::get_errors($resp);
                ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', $errors, [], 'home');
            }
        }

        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            self::handle_logged_in_user_registering($_POST, $current_user);
        } else {
            self::handle_logged_out_user_registering($_POST);
        }
    }

    /**
     * TODO REFACTOR CONTROLLERS
     *   What this controller function is responsible for:
     *   -  Updates an Affiliate
     * 
     *   Everything else is supportive
     *   - Gets the user input (POST params) out of the $_POST super global
     *   - Redirects back with error or success messages 
     *
     * @return void
     */
    public static function POST_affiliate_update_settings_handler()
    {
        // $registration_schema = AffiliatePortal::get_affiliate_registration_schema();
        $registration_schema = AffiliatePortal::get_affiliate_registration_schema_for_portal_forms();
        $eitherFields = ControllerFunctions::extract_and_validate_POST_params(
            $_POST,
            SchemaFunctions::keys_on_non_admin_edit_form_from_schema($registration_schema, [true, 'hidden_and_disabled', 'hidden']),
            $registration_schema
        );

        // TODO why is it field_id and not id? Why don't we use the extract_and_validate here....?
        $field_id = isset($_POST['field_id']) ? intval($_POST['field_id']) : 0;
        $affiliate = Affiliate::find($field_id);

        // TODO need to hack this here to that multiple messages+errors don't persist between requests. Must be a better way.
        if (is_null($affiliate)) {
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', [__(AffiliatePortalController::ERROR_MSG_UPDATE_SETTINGS, 'solid-affiliate')], [], 'home');
            return;
        }

        $aff_schema_entries = Affiliate::schema()->entries;
        $aff_attrs = $affiliate->attributes;

        /** @var array<string, mixed> $sanitized_attrs */
        $sanitized_attrs = array_reduce(
            Validators::array_of_string(array_keys($aff_attrs)),
            /**
             * @param array<mixed>|array<string, mixed> $arr
             * @param string $key
             */
            function ($arr, $key) use ($aff_schema_entries) {
                if (isset($aff_schema_entries[$key])) {
                    $entry = $aff_schema_entries[$key];
                    /** @var mixed */
                    $arr[$key] = SchemaEntry::sanitize($entry, $arr[$key]);
                    return $arr;
                }
            },
            $aff_attrs
        );

        $fields_to_upsert = array_merge($sanitized_attrs, $eitherFields->right);
        $eitherAffiliateID = Affiliate::upsert($fields_to_upsert);

        if ($eitherAffiliateID->isLeft) {
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', $eitherAffiliateID->left, [], 'home');
        } else {
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', [], [__(AffiliatePortalController::SUCCESS_MSG_UPDATE_SETTINGS, 'solid-affiliate')], 'home');
        }
    }
    ///////////////////////////////////////////////////
    // Helpers

    /**
     * Attempts to Register a User as an Affiliate, and handles redirecting with error/success msg.
     * 
     * @param array $post The $_POST
     * @param \WP_User $current_user
     * @return void
     */
    public static function handle_logged_in_user_registering($post, $current_user)
    {
        $registration_schema = AffiliatePortal::get_affiliate_registration_schema_for_portal_forms(['for_logged_in_user' => true]);

        $eitherFields = ControllerFunctions::extract_and_validate_POST_params(
            $post,
            SchemaFunctions::keys_on_non_admin_new_form_from_schema($registration_schema, [true, 'hidden_and_disabled', 'hidden']),
            $registration_schema
        );

        if ($eitherFields->isLeft) {
            ControllerFunctions::handle_redirecting_and_exit(
                'REDIRECT_BACK',
                $eitherFields->left,
                [],
                'home',
                ControllerFunctions::params_to_persist_on_form_submit($eitherFields->right, $registration_schema, self::POST_PARAM_SUBMIT_AFFILIATE_REGISTRATION)
            );
        } else {
            $user_login = $current_user->user_login;
            $user_email = $current_user->user_email;
            $rightFields = $eitherFields->right;

            $eitherAffiliateID = AffiliatePortal::handle_affiliate_registration_submission(
                $user_login,
                $user_email,
                $rightFields
            );

            if ($eitherAffiliateID->isLeft) {
                ControllerFunctions::handle_redirecting_and_exit(
                    'REDIRECT_BACK',
                    $eitherAffiliateID->left,
                    [],
                    'home',
                    ControllerFunctions::params_to_persist_on_form_submit($rightFields, $registration_schema, self::POST_PARAM_SUBMIT_AFFILIATE_REGISTRATION)
                );
            } else {
                ControllerFunctions::handle_redirecting_and_exit(URLs::get_affiliate_portal_path(), [], [__(AffiliatePortalController::SUCCESS_MSG_AFFILIATE_REGISTRATION, 'solid-affiliate')], 'home');
            }
        }
    }

    /**
     * 
     * @param array $post The $_POST
     * 
     * @return void
     */
    public static function handle_logged_out_user_registering($post)
    {
        $registration_schema = AffiliatePortal::get_affiliate_registration_schema_for_portal_forms();
        $eitherFields = ControllerFunctions::extract_and_validate_POST_params(
            $post,
            SchemaFunctions::keys_on_non_admin_new_form_from_schema($registration_schema, [true, 'hidden_and_disabled', 'hidden']),
            $registration_schema
        );

        if ($eitherFields->isLeft) {
            ControllerFunctions::handle_redirecting_and_exit(
                'REDIRECT_BACK',
                $eitherFields->left,
                [],
                'home',
                ControllerFunctions::params_to_persist_on_form_submit($eitherFields->right, $registration_schema, self::POST_PARAM_SUBMIT_AFFILIATE_REGISTRATION)
            );
        } else {
            $rightFields = $eitherFields->right;
            $user_login = Validators::str_from_array($rightFields, 'user_login');
            $user_email = Validators::str_from_array($rightFields, 'user_email');
            $maybe_user = AffiliatePortalFunctions::maybe_get_wp_user_from_logged_out_registration($user_login, $user_email);
            $user_password = Validators::str_from_array($rightFields, 'user_pass');

            if ($maybe_user instanceof \WP_User) {
                $did_login = AffiliatePortalFunctions::maybe_login_existing_wp_user_with_registration_creds($maybe_user, $user_password, $user_login);

                if ($did_login instanceof \WP_User) {
                    $maybe_existing_affiliate = Affiliate::for_user_id($did_login->ID);

                    if ($maybe_existing_affiliate instanceof Affiliate) {
                        ControllerFunctions::handle_redirecting_and_exit(URLs::get_affiliate_portal_path(), [], [__('You already have an Affiliate account associated to this user information, so we have signed you into your affiliate portal. Next time use the Affiliate login form to sign in.', 'solid-affiliate')], 'home');
                    }

                    self::handle_logged_in_user_registering($post, wp_get_current_user());
                } else {
                    ControllerFunctions::handle_redirecting_and_exit(
                        'REDIRECT_BACK',
                        [__('The wrong password, username, or account email was provided for the existing user account. You can try logging in first.', 'solid-affiliate')],
                        [],
                        'home',
                        ControllerFunctions::params_to_persist_on_form_submit($rightFields, $registration_schema, self::POST_PARAM_SUBMIT_AFFILIATE_REGISTRATION)
                    );
                }
            }

            if (get_user_by('email', $user_email)) {
                ControllerFunctions::handle_redirecting_and_exit(
                    'REDIRECT_BACK',
                    [__('Email already taken. If this is your account, please login first.', 'solid-affiliate')],
                    [],
                    'home',
                    ControllerFunctions::params_to_persist_on_form_submit($rightFields, $registration_schema, self::POST_PARAM_SUBMIT_AFFILIATE_REGISTRATION)
                );
            }
            if (get_user_by('login', $user_login)) {
                ControllerFunctions::handle_redirecting_and_exit(
                    'REDIRECT_BACK',
                    [__('Username already taken. If this is your account, please login first.', 'solid-affiliate')],
                    [],
                    'home',
                    ControllerFunctions::params_to_persist_on_form_submit($rightFields, $registration_schema, self::POST_PARAM_SUBMIT_AFFILIATE_REGISTRATION)
                );
            }

            $eitherAffiliateId = AffiliatePortal::handle_affiliate_registration_submission(
                $user_login,
                $user_email,
                $rightFields
            );

            if ($eitherAffiliateId->isLeft) {
                ControllerFunctions::handle_redirecting_and_exit(
                    'REDIRECT_BACK',
                    $eitherAffiliateId->left,
                    [],
                    'home',
                    ControllerFunctions::params_to_persist_on_form_submit($rightFields, $registration_schema, self::POST_PARAM_SUBMIT_AFFILIATE_REGISTRATION)
                );
            } else {
                $login_credentials = [
                    'user_email' => $user_email,
                    'user_pass' => $user_password
                ];
                $login_attempt = AffiliatePortalFunctions::attempt_affiliate_login($login_credentials);

                if ($login_attempt->isLeft) {
                    ControllerFunctions::handle_redirecting_and_exit(
                        'REDIRECT_BACK',
                        $login_attempt->left,
                        [],
                        'home',
                        ControllerFunctions::params_to_persist_on_form_submit($rightFields, $registration_schema, self::POST_PARAM_SUBMIT_AFFILIATE_REGISTRATION)
                    );
                } else {
                    ControllerFunctions::handle_redirecting_and_exit(URLs::get_affiliate_portal_path(), [], [__(AffiliatePortalController::SUCCESS_MSG_AFFILIATE_REGISTRATION, 'solid-affiliate')], 'home');
                }
            }
        }
    }
}
