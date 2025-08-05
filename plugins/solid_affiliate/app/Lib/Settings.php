<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Lib\AffiliateRegistrationForm\AffiliateRegistrationFormFunctions;
use SolidAffiliate\Lib\AffiliateRegistrationForm\SchemaSerializer;
use SolidAffiliate\Lib\CustomAffiliateSlugs\AffiliateCustomSlugBase;
use SolidAffiliate\Lib\CustomAffiliateSlugs\CustomSlugViewFunctions;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateGroup;
use SolidAffiliate\Models\AffiliatePortal;
use SolidAffiliate\Models\Template;
use SolidAffiliate\Views\Shared\AjaxButton;
use SolidAffiliate\Lib\Email_Notifications;
use SolidAffiliate\Models\Referral;

class Settings
{
    const OPTIONS_KEY = 'sld_affiliate_options_v1';
    const ADMIN_PAGE_KEY = 'solid-affiliate-settings';

    const CACHE_KEY_GET_ALL = 'sld_affiliate_settings_get_all';

    const TRANSIENT_EXPIRATION_IN_SECONDS = 100;

    /**
     * Gets a single setting value
     *
     * @param Settings::KEY_* $key
     *
     * @return mixed
     */
    public static function get($key)
    {
        $get_all = Utils::solid_transient(
            [self::class, 'get_all'],
            self::CACHE_KEY_GET_ALL,
            self::TRANSIENT_EXPIRATION_IN_SECONDS
        );
        /** @psalm-suppress MixedAssignment */
        $val = $get_all[$key] ?? null;

        /**
         * @psalm-suppress MixedAssignment
         * @param mixed $val 
         * @param string $key
         */
        $val = apply_filters("solid_affiliate/settings/get", $val, $key);
        return $val;
    }

    /**
     * Gets multiple setting values
     *
     * @param Settings::KEY_*[] $keys
     *
     * @return array<Settings::KEY_*, mixed>
     */
    public static function get_many($keys)
    {
        $get_all = Utils::solid_transient(
            [self::class, 'get_all'],
            self::CACHE_KEY_GET_ALL,
            self::TRANSIENT_EXPIRATION_IN_SECONDS
        );

        return array_intersect_key($get_all, array_flip($keys));
    }

    /**
     * Sets a single setting value
     *
     * @param array<Settings::KEY_*, mixed> $key_value
     *
     * @return Either<true>
     */
    public static function set($key_value)
    {
        return Settings::set_many($key_value);
    }

    /**
     * Sets multiple setting values
     *
     * @param array<Settings::KEY_*, mixed> $key_values
     *
     * @return Either<true>
     */
    public static function set_many($key_values)
    {

        delete_transient(self::CACHE_KEY_GET_ALL);
        // get the current settings from DB
        $current_settings = Settings::get_all();

        // Run all sanitize_callbacks functions
        $new_settings = Settings::_sanitize_settings($key_values);

        // merge new_settings
        $new_settings = array_merge($current_settings, $new_settings);

        // If new and current are the same, don't hit the database.
        // Also I do this because wordpress's update_option returns false
        // if there is no update to make.
        if ($current_settings == $new_settings) {
            return new Either([''], true, true);
        }

        $eitherValidSettings = Settings::_validate_settings($new_settings);


        if ($eitherValidSettings->isLeft) {
            return new Either($eitherValidSettings->left, true, false);
        } else {
            // update in DB
            $eitherSuccessfullyUpdated = update_option(self::OPTIONS_KEY, wp_unslash($eitherValidSettings->right));

            if ($eitherSuccessfullyUpdated) {
                delete_transient(self::CACHE_KEY_GET_ALL);
                return new Either([''], true, true);
            } else {
                return new Either([__('Error updating settings. Settings::set_many', 'solid-affiliate')], true, false);
            }
        }
    }

    /**
     * @return array<Settings::KEY_*, mixed>
     */
    public static function get_all()
    {
        if (get_option(self::OPTIONS_KEY, 'NOT_FOUND') === 'NOT_FOUND') {
            Settings::reset_defaults(); // TODO LUCA how to handle this in production. When we updated the keys and such. run this to restart the options during development
        }

        $defaults = SchemaFunctions::defaults_from_schema(Settings::schema());

        $validated_settings =
            Settings::validator_settings_array(
                Settings::validator_array_string_key(
                    apply_filters(
                        'solid_affiliate/settings/get_all',
                        get_option(self::OPTIONS_KEY, $defaults)
                    )
                ),
                true
            );

        return $validated_settings;
    }

    /**
     * Resets DB entry for options to defaults. Careful using this please.
     *
     * @return bool True if successful, False if not.
     */
    public static function reset_defaults()
    {
        $defaults = SchemaFunctions::defaults_from_schema(Settings::schema());

        // Get data about this site
        $site_name = (string)get_bloginfo('name');
        $site_default_admin_email = (string)get_bloginfo('admin_email');
        //

        $defaults = array_merge($defaults, [
            self::KEY_EMAIL_FROM_NAME => $site_name,
            self::KEY_FROM_EMAIL => $site_default_admin_email,
            self::KEY_AFFILIATE_MANAGER_EMAIL => $site_default_admin_email
        ]);

        return update_option(self::OPTIONS_KEY, $defaults);
    }

    /**
     * @param array<Settings::KEY_*, mixed> $key_values
     * 
     * @return array<Settings::KEY_*, mixed> 
     */
    private static function _sanitize_settings($key_values)
    {
        $schema = Settings::schema();

        /** @psalm-suppress MixedAssignment */
        foreach ($key_values as $key => $value) {
            $schema_entry = $schema->entries[$key];

            $key_values[$key] = SchemaEntry::sanitize($schema_entry, $value);
        }

        return $key_values;
    }

    /**
     * @param array<Settings::KEY_*, mixed> $key_values
     * 
     * @return Either<array<Settings::KEY_*, mixed>>
     */
    private static function _validate_settings($key_values)
    {
        $schema = Settings::schema();

        /** @psalm-suppress MixedAssignment */
        foreach ($key_values as $key => $value) {
            $schema_entry = $schema->entries[$key];
            $result = SchemaEntry::validate($schema_entry, $value);

            if (!$result[0]) {
                $error_msg = $result[1];
                if (Utils::is_empty($error_msg)) {
                    $error_msg = self::_error_msg_per_setting($key);
                }

                return new Either([$error_msg], $key_values, false);
            }
        }

        return new Either([''], $key_values, true);
    }

    /**
     * Until we refactor to allow the validate callback on a setting return error messages, this will return the key of the setting if we have a error message for it.
     * Or it will return the default message if not.
     *
     * @param Settings::KEY_*|AffiliateRegistrationFormFunctions::ERROR_KEY_* $key
     *
     * @return string
     */
    private static function _error_msg_per_setting($key)
    {
        switch ($key) {
            case AffiliateRegistrationFormFunctions::ERROR_KEY_UNIQUE_NAMES:
                return $key;
            case AffiliateRegistrationFormFunctions::ERROR_KEY_ENCODE_FAILURE:
                return $key;
            case AffiliateRegistrationFormFunctions::ERROR_KEY_DECODE_FAILURE:
                return $key;
            case AffiliateRegistrationFormFunctions::ERROR_KEY_PAST_TYPE_CONFLICT:
                return $key;
            case AffiliateRegistrationFormFunctions::ERROR_KEY_UNIQUE_LABELS:
                return $key;
            case AffiliateRegistrationFormFunctions::ERROR_KEY_EMPTY_NAME:
                return $key;
            case AffiliateRegistrationFormFunctions::ERROR_KEY_EMPTY_LABEL:
                return $key;
            case AffiliateRegistrationFormFunctions::ERROR_KEY_EMPTY_OR_DUP_ENUM_OPTION:
                return $key;
            case AffiliateRegistrationFormFunctions::ERROR_KEY_RESERVED_NAME_CONFLICT:
                return $key;
            default:
                return __("Invalid value for", 'solid-affiliate') . ": {$key}";
        }
    }

    /**
     * @param array<string, mixed> $array
     * @param bool $add_defaults
     * 
     * @return array<Settings::KEY_*, mixed> 
     */
    public static function validator_settings_array($array, $add_defaults = false)
    {
        $allowed  = Settings::SETTABLE_FIELD_KEYS;

        // Filter out any keys that are not in the SETTABLE_FILED_KEY constant
        $filtered = array_filter(
            $array,
            /** @param string $key */
            function ($key) use ($allowed) {
                return in_array($key, $allowed);
            },
            ARRAY_FILTER_USE_KEY
        );

        // Add any missing keys and their default values
        if ($add_defaults) {
            $defaults = SchemaFunctions::defaults_from_schema(Settings::schema());
            $filtered = array_merge($defaults, $filtered);
        }

        /** @var array<Settings::KEY_*, mixed> */
        return $filtered;
    }

    /**
     * @param mixed $mixed
     * 
     * @return array<string, mixed>
     */
    public static function validator_array_string_key($mixed)
    {
        if (!is_array($mixed)) {
            return [];
        } else {
            $filtered = array_filter(
                $mixed,
                /** @param mixed $key */
                function ($key) {
                    return is_string($key);
                },
                ARRAY_FILTER_USE_KEY
            );

            /** @var array<string, mixed> */
            return $filtered;
        }
    }

    const DEFAULT_EMAIL_FROM_NAME = "Default From Name";
    const DEFAULT_FROM_EMAIL = "example@email.com";
    const DEFAULT_AFFILIATE_MANAGER_EMAIL = "example@email.com";
    const DEFAULT_LICENSE_KEY = "";
    const DEFAULT_LICENSE_KEY_STATUS = "invalid";

    const KEY_FROM_EMAIL = "from_email";
    const KEY_LICENSE_KEY_ACTIVATION_EMAIL = "license_key_activation_email";
    const KEY_LICENSE_KEY = "license_key";
    const KEY_LICENSE_KEY_STATUS = "license_key_status";
    const KEY_EMAIL_FROM_NAME = "email_from_name";
    const KEY_AFFILIATE_PORTAL_PAGE = "affiliate_portal_page";
    const KEY_TERMS_OF_USE_PAGE = "terms_of_use_page";
    const KEY_TERMS_OF_USE_LABEL = "terms_of_use_label";
    const KEY_REFERRAL_RATE = "referral_rate";
    const KEY_REFERRAL_RATE_TYPE = "referral_rate_type";
    const KEY_REFERRAL_VARIABLE = "referral_variable";
    const KEY_CAN_AFFILIATES_CREATE_CUSTOM_SLUGS = "can_affiliates_create_custom_slugs";
    const KEY_PER_AFFILIATE_CUSTOM_SLUG_LIMIT = "per_affiliate_custom_slug_limit";
    const KEY_SHOULD_AUTO_CREATE_AFFILIATE_USERNAME_SLUG = "should_auto_create_affiliate_username_slug";
    const KEY_IS_AUTO_APPLY_AFFILIATE_COUPON_ENABLED = "is_auto_apply_affiliate_coupon_enabled";
    const KEY_DEFAULT_DISPLAY_AFFILIATE_SLUG_FORMAT = "default_display_affiliate_slug_format";
    // const KEY_IS_CREDIT_LAST_AFFILIATE = "is_credit_last_referrer";
    const KEY_IS_EXCLUDE_SHIPPING = "is_exclude_shipping";
    const KEY_IS_EXCLUDE_TAX = "is_exclude_tax";
    const KEY_IS_NEW_CUSTOMER_COMMISSIONS = "is_new_customer_commissions";
    const KEY_IS_ENABLE_ZERO_VALUE_REFERRALS = "is_enable_zero_value_referrals";
    const KEY_IS_PREVENT_SELF_REFERRALS = "is_prevent_affiliate_own_referrals";
    const KEY_IS_COOKIES_DISABLED = "is_cookies_disabled";
    const KEY_IS_SERVER_COOKIES_DISABLED = "is_server_cookies_disabled";
    const KEY_IS_AUTOMATIC_VISIT_TRACKING_DISABLED = "is_automatic_visit_tracking_disabled";
    const KEY_IS_LOGGING_DISABLED = "is_logging_disabled";
    const KEY_COOKIE_EXPIRATION_DAYS = "cookie_expiration_days";
    const KEY_REFERRAL_GRACE_PERIOD_NUMBER_OF_DAYS = "referral_grace_period_number_of_days";
    const KEY_BULK_PAYOUTS_MINIMUM_PAYOUT_AMOUNT = "bulk_payouts_minimum_payout_amount";
    const KEY_AFFILIATE_PORTAL_FORMS = "affiliate_portal_forms";
    const KEY_IS_HIDE_AFFILIATE_PORTAL_FROM_UNAPPROVED_AFFILIATES = "is_hide_affiliate_portal_from_unapproved_affiliates";
    const KEY_UNAPPROVED_AFFILIATES_CONTENT = "unapproved_affiliates_content";
    const KEY_AFFILIATE_PORTAL_IS_ANCHOR_TAG_ENABLED = "affiliate_portal_is_anchor_tag_enabled";
    const KEY_REFERRAL_STATUSES_TO_DISPLAY_TO_AFFILIATES = "referral_statuses_to_display_to_affiliates";
    const KEY_AFFILIATE_REGISTRATION_FIELDS_TO_DISPLAY = "affiliate_registration_fields_to_display";
    const KEY_REQUIRED_AFFILIATE_REGISTRATION_FIELDS = "required_affiliate_registration_fields";

    const KEY_INTEGRATIONS_WOOCOMMERCE = "integrations_woocommerce";
    const KEY_INTEGRATIONS_PAYPAL_ACTIVE = "integrations_paypal_active";
    const KEY_INTEGRATIONS_PAYPAL_USE_LIVE = "integrations_paypal_use_live";
    const KEY_INTEGRATIONS_PAYPAL_CLIENT_ID_LIVE = "integrations_paypal_client_id_live";
    const KEY_INTEGRATIONS_PAYPAL_SECRET_LIVE = "integrations_paypal_secret_live";
    const KEY_INTEGRATIONS_PAYPAL_CLIENT_ID_SANDBOX = "integrations_paypal_client_id_sandbox";
    const KEY_INTEGRATIONS_PAYPAL_SECRET_SANDBOX = "integrations_paypal_secret_sandbox";
    const KEY_INTEGRATIONS_MAILCHIMP_API_KEY_LIVE = "integrations_mailchimp_api_key_live";
    const KEY_INTEGRATIONS_MAILCHIMP_IS_ACTIVE = "integrations_mailchimp_is_active";
    const KEY_INTEGRATIONS_MAILCHIMP_AFFILIATE_SYNC_LIST_ID = "integrations_mailchimp_affiliate_sync_list_id";

    const KEY_EMAIL_TEMPLATE = "email_template";
    const KEY_EMAIL_NOTIFICATIONS = "email_notifications";
    const KEY_AFFILIATE_MANAGER_EMAIL = "affiliate_manager_email";
    const KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_MANAGER_NEW_AFFILIATE = "notification_email_subject_affiliate_manager_new_affiliate";
    const KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_MANAGER_NEW_AFFILIATE = "notification_email_body_affiliate_manager_new_affiliate";
    const KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_MANAGER_NEW_REFERRAL = "notification_email_subject_affiliate_manager_new_referral";
    const KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_MANAGER_NEW_REFERRAL = "notification_email_body_affiliate_manager_new_referral";
    const KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_APPLICATION_ACCEPTED = "notification_email_subject_affiliate_application_accepted";
    const KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_APPLICATION_ACCEPTED = "notification_email_body_affiliate_application_accepted";
    const KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_NEW_REFERRAL = "notification_email_subject_affiliate_new_referral";
    const KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_NEW_REFERRAL = "notification_email_body_affiliate_new_referral";
    const KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_SETUP_WIZARD_INVITE = "notification_email_subject_affiliate_setup_wizard_invite";
    const KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_SETUP_WIZARD_INVITE = "notification_email_body_affiliate_setup_wizard_invite";

    // const KEY_IS_ALLOW_AFFILIATE_REGISTRATION = "is_allow_affiliate_registration";
    const KEY_IS_REQUIRE_AFFILIATE_REGISTRATION_APPROVAL = "is_require_affiliate_registration_approval";
    const KEY_IS_LOGOUT_LINK_ON_AFFILIATE_PORTAL = "is_logout_link_on_affiliate_portal";
    const KEY_IS_DISPLAY_CUSTOMER_INFO_ON_AFFILIATE_PORTAL = "is_display_customer_info_on_affiliate_portal";
    const KEY_IS_RECAPTCHA_ENABLED_FOR_AFFILIATE_REGISTRATION = "is_recaptcha_enabled_for_affiliate_registration";
    const KEY_RECAPTCHA_SITE_KEY = "recaptcha_site_key";
    const KEY_RECAPTCHA_SECRET_KEY = "recaptcha_secret_key";
    const KEY_IS_REJECT_UNPAID_REFERRALS_ON_REFUND = "is_reject_unpaid_referrals_on_refund";
    const KEY_IS_DISABLE_CUSTOMER_IP_ADDRESS_LOGGING = "is_disable_customer_ip_address_logging";
    const KEY_IS_AFFILIATE_MANAGER_MONTHLY_REPORT_EMAIL_ENABLED = "is_affiliate_manager_monthly_report_email_enabled";
    const KEY_REFERRAL_URL_BLACKLIST = "referral_url_blacklist";
    const KEY_IS_REMOVE_DATA_ON_UNINSTALL = "is_remove_data_on_uninstall";
    const KEY_IS_ENABLE_RECURRING_REFERRALS = "is_enable_recurring_referrals";
    const KEY_RECURRING_REFERRAL_RATE = "recurring_referral_rate";
    const KEY_RECURRING_REFERRAL_RATE_TYPE = "recurring_referral_rate_type";
    const KEY_RECURRING_REFERRAL_LIMIT_DURATION_IN_SECONDS_STRING = "recurring_referral_limit_duration_in_seconds_string";
    const KEY_IS_EXCLUDE_SIGNUP_FEE_RECURRING_REFERRALS = "is_exclude_signup_fee_recurring_referrals";
    const KEY_AFFILIATE_PORTAL_SHORTCODE = "affiliate_portal_shortcode";
    const KEY_AFFILIATE_WORD_IN_PORTAL = "affiliate_word_in_affiliate_portal";
    const KEY_AFFILIATE_WORD_ON_PORTAL_FORMS = "affiliate_word_on_affiliate_portal_forms";

    const KEY_AFFILIATE_PORTAL_FORMS_THEME = "affiliate_portal_forms_theme";


    const KEY_IS_SETUP_WIZARD_DISPLAYED = "is_setup_wizard_displayed";
    const KEY_IS_SETUP_WIZARD_AFFILIATE_PORTAL_SETUP_COMPLETE = "is_setup_wizard_affiliate_portal_setup_complete";

    const KEY_AFFILIATE_GROUP_SHOULD_CREATE_DEFAULT = "affiliate_group_should_create_default";
    const KEY_AFFILIATE_GROUP_DEFAULT_GROUP_ID = "affiliate_group_default_group_id";
    const KEY_AFFILIATE_GROUP_SHOULD_ADD_AFFILIATES_TO_DEFAULT_GROUP = "affiliate_group_should_add_affiliates_to_default_group";

    const KEY_DEFAULT_AFFILIATE_LINK_URL = "default_affiliate_link_url";
    const KEY_AFFILIATE_PORTAL_TABS_TO_HIDE = "affiliate_portal_tabs_to_hide";

    const KEY_CUSTOM_REGISTRATION_FORM_SCHEMA = 'custom_registration_form_schema';
    const KEY_WOOCOMMERCE_ORDER_STATUSES_WHICH_SHOULD_TRIGGER_A_REFERRAL = 'woocommerce_order_statuses_which_should_trigger_a_referral';

    const KEY_IS_LIFETIME_COMMISSIONS_ENABLED = 'is_lifetime_commissions_enabled';
    const KEY_IS_LIFETIME_COMMISSIONS_AUTO_LINK_ENABLED = 'is_lifetime_commissions_auto_link_enabled';
    const KEY_LIFETIME_COMMISSIONS_REFERRAL_RATE_TYPE = 'lifetime_commissions_referral_rate_type';
    const KEY_LIFETIME_COMMISSIONS_REFERRAL_RATE = 'lifetime_commissions_referral_rate';
    const KEY_LIFETIME_COMMISSIONS_DURATION_IN_SECONDS_STRING = 'lifetime_commissions_duration_in_seconds_string';
    const KEY_IS_ONLY_NEW_CUSTOMER_LIFE_TIME_COMMISSIONS = 'is_only_new_customer_life_time_commissions';
    const KEY_IS_LIFETIME_COMMISSIONS_SHOW_AFFILIATES_THEIR_CUSTOMERS = 'is_lifetime_commissions_show_affiliates_their_customers';

    const KEY_IS_BASIC_USAGE_STATISTICS_ENABLED = 'is_basic_usage_statistics_enabled';

    const SETTABLE_FIELD_KEYS = [
        self::KEY_AFFILIATE_MANAGER_EMAIL,
        self::KEY_AFFILIATE_PORTAL_FORMS,
        self::KEY_AFFILIATE_PORTAL_FORMS_THEME,
        self::KEY_IS_HIDE_AFFILIATE_PORTAL_FROM_UNAPPROVED_AFFILIATES,
        self::KEY_UNAPPROVED_AFFILIATES_CONTENT,
        self::KEY_AFFILIATE_PORTAL_IS_ANCHOR_TAG_ENABLED,
        self::KEY_AFFILIATE_PORTAL_PAGE,
        self::KEY_AFFILIATE_PORTAL_SHORTCODE,
        self::KEY_IS_COOKIES_DISABLED,
        self::KEY_IS_SERVER_COOKIES_DISABLED,
        self::KEY_IS_AUTOMATIC_VISIT_TRACKING_DISABLED,
        self::KEY_IS_LOGGING_DISABLED,
        self::KEY_COOKIE_EXPIRATION_DAYS,
        self::KEY_REFERRAL_GRACE_PERIOD_NUMBER_OF_DAYS,
        self::KEY_BULK_PAYOUTS_MINIMUM_PAYOUT_AMOUNT,
        self::KEY_EMAIL_FROM_NAME,
        self::KEY_EMAIL_NOTIFICATIONS,
        self::KEY_EMAIL_TEMPLATE,
        self::KEY_FROM_EMAIL,
        self::KEY_INTEGRATIONS_PAYPAL_ACTIVE,
        self::KEY_INTEGRATIONS_PAYPAL_USE_LIVE,
        self::KEY_INTEGRATIONS_PAYPAL_CLIENT_ID_LIVE,
        self::KEY_INTEGRATIONS_PAYPAL_SECRET_LIVE,
        self::KEY_INTEGRATIONS_PAYPAL_CLIENT_ID_SANDBOX,
        self::KEY_INTEGRATIONS_PAYPAL_SECRET_SANDBOX,
        self::KEY_INTEGRATIONS_WOOCOMMERCE,
        self::KEY_INTEGRATIONS_MAILCHIMP_API_KEY_LIVE,
        self::KEY_INTEGRATIONS_MAILCHIMP_IS_ACTIVE,
        self::KEY_INTEGRATIONS_MAILCHIMP_AFFILIATE_SYNC_LIST_ID,
        // self::KEY_IS_ALLOW_AFFILIATE_REGISTRATION,
        // self::KEY_IS_CREDIT_LAST_AFFILIATE,
        self::KEY_IS_DISABLE_CUSTOMER_IP_ADDRESS_LOGGING,
        self::KEY_IS_ENABLE_RECURRING_REFERRALS,
        self::KEY_IS_EXCLUDE_SHIPPING,
        self::KEY_IS_EXCLUDE_TAX,
        self::KEY_IS_NEW_CUSTOMER_COMMISSIONS,
        self::KEY_IS_ENABLE_ZERO_VALUE_REFERRALS,
        self::KEY_IS_PREVENT_SELF_REFERRALS,
        self::KEY_IS_LOGOUT_LINK_ON_AFFILIATE_PORTAL,
        self::KEY_IS_DISPLAY_CUSTOMER_INFO_ON_AFFILIATE_PORTAL,
        self::KEY_REFERRAL_STATUSES_TO_DISPLAY_TO_AFFILIATES,
        self::KEY_IS_RECAPTCHA_ENABLED_FOR_AFFILIATE_REGISTRATION,
        self::KEY_IS_REJECT_UNPAID_REFERRALS_ON_REFUND,
        self::KEY_IS_REMOVE_DATA_ON_UNINSTALL,
        self::KEY_IS_REQUIRE_AFFILIATE_REGISTRATION_APPROVAL,
        self::KEY_LICENSE_KEY_ACTIVATION_EMAIL,
        self::KEY_LICENSE_KEY,
        self::KEY_LICENSE_KEY_STATUS,
        self::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_APPLICATION_ACCEPTED,
        self::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_MANAGER_NEW_AFFILIATE,
        self::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_MANAGER_NEW_REFERRAL,
        self::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_NEW_REFERRAL,
        self::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_APPLICATION_ACCEPTED,
        self::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_MANAGER_NEW_AFFILIATE,
        self::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_MANAGER_NEW_REFERRAL,
        self::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_NEW_REFERRAL,
        self::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_SETUP_WIZARD_INVITE,
        self::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_SETUP_WIZARD_INVITE,
        self::KEY_RECAPTCHA_SECRET_KEY,
        self::KEY_RECAPTCHA_SITE_KEY,
        self::KEY_RECURRING_REFERRAL_RATE,
        self::KEY_RECURRING_REFERRAL_RATE_TYPE,
        self::KEY_RECURRING_REFERRAL_LIMIT_DURATION_IN_SECONDS_STRING,
        self::KEY_IS_EXCLUDE_SIGNUP_FEE_RECURRING_REFERRALS,
        self::KEY_REFERRAL_RATE,
        self::KEY_REFERRAL_RATE_TYPE,
        self::KEY_IS_AFFILIATE_MANAGER_MONTHLY_REPORT_EMAIL_ENABLED,
        self::KEY_REFERRAL_URL_BLACKLIST,
        self::KEY_REFERRAL_VARIABLE,
        self::KEY_CAN_AFFILIATES_CREATE_CUSTOM_SLUGS,
        self::KEY_PER_AFFILIATE_CUSTOM_SLUG_LIMIT,
        self::KEY_SHOULD_AUTO_CREATE_AFFILIATE_USERNAME_SLUG,
        self::KEY_IS_AUTO_APPLY_AFFILIATE_COUPON_ENABLED,
        self::KEY_DEFAULT_DISPLAY_AFFILIATE_SLUG_FORMAT,
        self::KEY_AFFILIATE_REGISTRATION_FIELDS_TO_DISPLAY,
        self::KEY_REQUIRED_AFFILIATE_REGISTRATION_FIELDS,
        self::KEY_TERMS_OF_USE_LABEL,
        self::KEY_TERMS_OF_USE_PAGE,
        self::KEY_IS_SETUP_WIZARD_DISPLAYED,
        self::KEY_IS_SETUP_WIZARD_AFFILIATE_PORTAL_SETUP_COMPLETE,
        self::KEY_AFFILIATE_GROUP_SHOULD_CREATE_DEFAULT,
        self::KEY_AFFILIATE_GROUP_DEFAULT_GROUP_ID,
        self::KEY_AFFILIATE_GROUP_SHOULD_ADD_AFFILIATES_TO_DEFAULT_GROUP,
        self::KEY_DEFAULT_AFFILIATE_LINK_URL,
        self::KEY_AFFILIATE_PORTAL_TABS_TO_HIDE,
        self::KEY_CUSTOM_REGISTRATION_FORM_SCHEMA,
        self::KEY_AFFILIATE_WORD_IN_PORTAL,
        self::KEY_AFFILIATE_WORD_ON_PORTAL_FORMS,
        self::KEY_WOOCOMMERCE_ORDER_STATUSES_WHICH_SHOULD_TRIGGER_A_REFERRAL,
        // Lifetime Commissions
        self::KEY_IS_LIFETIME_COMMISSIONS_ENABLED,
        self::KEY_IS_LIFETIME_COMMISSIONS_AUTO_LINK_ENABLED,
        self::KEY_LIFETIME_COMMISSIONS_REFERRAL_RATE_TYPE,
        self::KEY_LIFETIME_COMMISSIONS_REFERRAL_RATE,
        self::KEY_LIFETIME_COMMISSIONS_DURATION_IN_SECONDS_STRING,
        self::KEY_IS_ONLY_NEW_CUSTOMER_LIFE_TIME_COMMISSIONS,
        self::KEY_IS_LIFETIME_COMMISSIONS_SHOW_AFFILIATES_THEIR_CUSTOMERS,
        // Other
        self::KEY_IS_BASIC_USAGE_STATISTICS_ENABLED
    ];

    # Supported Payout Currencies:
    # Note: we're only supporting the Codes with no 1,2,3 footnotes/exceptions.
    # https://developer.paypal.com/docs/api/reference/currency-codes/
    const SUPPORTED_CURRENCIES = [
        ['USD', 'United States Dollar'],
        ['AUD', 'Australia Dollar'],
        ['CAD', 'Canada Dollar'],
        ['CZK', 'Czech Republic Koruna'],
        ['DKK', 'Denmark Krone'],
        ['EUR', 'Euro'],
        ['HKD', 'Hong Kong Dollar'],
        ['ILS', 'Israel Shekel'],
        ['MXN', 'Mexico Peso'],
        ['NZD', 'New Zealand Dollar'],
        ['NOK', 'Norway Krone'],
        ['PHP', 'Philippines Peso'],
        ['PLN', 'Poland Zloty'],
        ['RUB', 'Russia Ruble'],
        ['SGD', 'Singapore Dollar'],
        ['SEK', 'Sweden Krona'],
        ['CHF', 'Switzerland Franc'],
        ['THB', 'Thailand Baht'],
        ['GBP', 'United Kingdom Pound'],
    ];

    const TAB_GENERAL = 'General';
    const TAB_AFFILIATE_PORTAL_AND_REGISTRATION = 'Affiliate Portal & Registration';
    const TAB_INTEGRATIONS = 'Integrations';
    const TAB_EMAILS = 'Emails';
    const TAB_MISC = 'Misc';
    const TAB_RECURRING_REFERRALS = 'Subscription Renewal Referrals';
    const TAB_CUSTOMIZE_REGISTRATION_FORM = 'Customize Registration Form';
    const TAB_FRAUD_PREVENTION = 'Fraud Prevention';

    const TABS_TO_KEYS = [
        self::TAB_GENERAL => 'general',
        self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION => 'affiliate_portal',
        self::TAB_INTEGRATIONS => 'integrations',
        self::TAB_EMAILS => 'emails',
        self::TAB_MISC => 'misc',
        self::TAB_RECURRING_REFERRALS => 'recurring_referrals',
        self::TAB_CUSTOMIZE_REGISTRATION_FORM => 'customize_registration_form',
        self::TAB_FRAUD_PREVENTION => 'fraud_prevention'
    ];

    const GROUP_EMAIL_GENERAL = 'Email notifications';
    const GROUP_EMAIL_AFFILIATE_MANAGER_NEW_AFFILIATE = 'New affiliate registration';
    const GROUP_EMAIL_AFFILIATE_MANAGER_NEW_REFERRAL = 'New unpaid referral';
    const GROUP_EMAIL_AFFILIATE_NEW_REFERRAL = 'New referral earned';
    const GROUP_EMAIL_AFFILIATE_APPLICATION_ACCEPTED = 'Application approval';
    const GROUP_LIFETIME_COMMISSIONS = 'Lifetime commissions';


    /** 
     * @var Schema<Settings::KEY_*>|null
     */
    private static $schema_cache = null;


    /**
     * @return Schema<Settings::KEY_*>
     */
    public static function schema()
    {
        if (!is_null(self::$schema_cache)) {
            return self::$schema_cache;
        }

        $entries =  array(
            ///////////////////////////////////////////////////////
            // General Tab
            ///////////////////////////////////////////////////////
            // License Group
            self::KEY_LICENSE_KEY_ACTIVATION_EMAIL => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('License Activation Email', 'solid-affiliate'),
                'user_default' => '',
                // 'settings_group' => 'License',
                'settings_group' => 'HIDDEN',
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => false,
                'form_input_description' => __('Enter your account email address used when purchasing Solid Affiliate.', 'solid-affiliate'),
            )),
            self::KEY_LICENSE_KEY => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('License Key', 'solid-affiliate'),
                'user_default' => self::DEFAULT_LICENSE_KEY,
                'settings_group' => 'HIDDEN',
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => false,
                'form_input_description' => __('Please enter and verify your active license key. This is needed for automatic updates and support.', 'solid-affiliate'),
                'is_password' => true
            )),
            self::KEY_LICENSE_KEY_STATUS => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('License Key Status', 'solid-affiliate'),
                'user_default' => self::DEFAULT_LICENSE_KEY_STATUS,
                'settings_group' => 'HIDDEN',
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => false,
                'form_input_description' => __('The status of your license key. Can be <em>invalid</em> or <em>valid</em>.', 'solid-affiliate')
            )),

            // Referral Group
            self::KEY_REFERRAL_RATE_TYPE => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'is_enum' => true,
                'enum_options' => [
                    ['percentage', __('Percentage (%)', 'solid-affiliate')],
                    ['flat', __('Flat', 'solid-affiliate')],
                ],
                'display_name' => __('Referral Rate Type', 'solid-affiliate'),
                'user_default' => 'percentage',
                'settings_group' => 'Commissions calculations',
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => true,
                'form_input_description' => __('Used in conjunction with the referral rate to calculate the default referral amounts.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_REFERRAL_RATE => new SchemaEntry(array(
                'type' => 'float',
                'required' => true,
                'display_name' => __('Referral Rate', 'solid-affiliate'),
                'user_default' => 20,
                'settings_group' => 'Commissions calculations',
                'settings_tab' => self::TAB_GENERAL,
                'form_input_description' => GlobalTypes::REFERRAL_RATE_DESCRIPTION(),
                'show_on_edit_form' => true,
                'is_zero_value_allowed' => true,
                'validate_callback' => function (float $val) {
                    /**
                     * @psalm-suppress RedundantCondition
                     */
                    return is_numeric($val) && (float)$val >= 0;
                }
            )),
            self::KEY_REFERRAL_VARIABLE => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('Tracking URL parameter', 'solid-affiliate'),
                'user_default' => 'sld',
                'settings_group' => 'Affiliate links',
                'settings_tab' => self::TAB_GENERAL,
                'form_input_description' => __('Custom tracking parameter in affiliate links. Changing breaks existing link tracking.', 'solid-affiliate'),
                'show_on_edit_form' => true,
                'sanitize_callback' =>
                /** @param mixed $val 
                 * @return mixed */
                function ($val) {
                    return trim((string)$val);
                },
                'validate_callback' =>
                /** @param mixed $val */
                function ($val) {
                    return (is_string($val) && !empty($val) && filter_var(
                        $val,
                        FILTER_VALIDATE_REGEXP,
                        array(
                            "options" => array("regexp" => "/^\S*$/")
                        )
                    ));
                }
            )),
            self::KEY_COOKIE_EXPIRATION_DAYS => new SchemaEntry(array(
                'type' => 'bigint',
                'display_name' => __('Cookie duration', 'solid-affiliate'),
                'required' => true,
                'user_default' => 30,
                'settings_group' => 'Affiliate links',
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => true,
                'form_input_description' => __('Set tracking cookie lifespan.', 'solid-affiliate'),
                'custom_form_input_attributes' => ['min' => '1', 'max' => '1000']
            )),
            self::KEY_CAN_AFFILIATES_CREATE_CUSTOM_SLUGS => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Custom slugs management', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => 'Affiliate links',
                'settings_tab' => self::TAB_GENERAL,
                'form_input_description' => __('Allow all affiliates to create or delete custom link slug. Can be restricted per-affiliate if needed.', 'solid-affiliate'),
                'show_on_edit_form' => true
            )),
            self::KEY_PER_AFFILIATE_CUSTOM_SLUG_LIMIT => new SchemaEntry(array(
                'type' => 'bigint',
                'display_name' => __('Slug limit', 'solid-affiliate') . CustomSlugViewFunctions::slug_limit_rules_tooltip(),
                'user_default' => 10,
                'settings_group' => 'Affiliate links',
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => true,
                'form_input_description' => __(" Cap number of custom slugs per affiliate.", 'solid-affiliate'),
                'custom_form_input_attributes' => ['min' => '1']
            )),
            self::KEY_SHOULD_AUTO_CREATE_AFFILIATE_USERNAME_SLUG => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Auto create affiliate slug', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => 'Affiliate links',
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => true,
                'form_input_description' => __("Generate affiliate slug from WordPress username automatically.", 'solid-affiliate')
            )),
            self::KEY_IS_AUTO_APPLY_AFFILIATE_COUPON_ENABLED => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Auto apply affiliate coupon', 'solid-affiliate'),
                'user_default' => false,
                'settings_group' => 'Commissions calculations',
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => true,
                'form_input_description' => __("Automatically apply affiliate's coupon when visitors arrive via affiliate link.", 'solid-affiliate')
            )),
            // self::KEY_IS_CREDIT_LAST_AFFILIATE => new SchemaEntry(array(
            //     'type' => 'bool',
            //     'display_name' => __('Credit Last Affiliate', 'solid-affiliate'),
            //     'user_default' => true,
            //     'settings_group' => 'Commissions calculations',
            //     'settings_tab' => self::TAB_GENERAL,
            //     'show_on_edit_form' => 'hidden_and_disabled',
            //     'form_input_description' => __('Last referring affiliate gets commission. Always on.', 'solid-affiliate'),
            //     'required' => false
            // )),
            self::KEY_IS_EXCLUDE_SHIPPING => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Exclude shipping', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => 'Commissions calculations',
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => true,
                'required' => false,
                'form_input_description' => __('Option to exclude shipping costs from commission calculations', 'solid-affiliate')
            )),
            self::KEY_IS_EXCLUDE_TAX => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Exclude tax', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => 'Commissions calculations',
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => true,
                'required' => false,
                'form_input_description' => __('Option to exclude taxes from commission calculations', 'solid-affiliate')
            )),
            self::KEY_IS_NEW_CUSTOMER_COMMISSIONS => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('New customer commissions', 'solid-affiliate'),
                'user_default' => false,
                'settings_group' => 'Commissions calculations',
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => true,
                'required' => false,
                'form_input_description' => __('Only award commissions for first-time customer purchases. Subscription renewals are excluded.', 'solid-affiliate')
            )),
            self::KEY_IS_ENABLE_ZERO_VALUE_REFERRALS => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Zero value referrals', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => 'Commissions calculations',
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => true,
                'required' => false,
                'form_input_description' => __(' Credit $0 commission referrals.', 'solid-affiliate')
            )),
            self::KEY_IS_PREVENT_SELF_REFERRALS => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Prevent self referrals', 'solid-affiliate'),
                'user_default' => false,
                'settings_group' => 'Fraud Prevention',
                'settings_tab' => self::TAB_FRAUD_PREVENTION,
                'show_on_edit_form' => true,
                'required' => false,
                'form_input_description' => __('Block affiliates from creating referrals on their own purchases. Default is on.', 'solid-affiliate')
            )),
            self::KEY_REFERRAL_GRACE_PERIOD_NUMBER_OF_DAYS => new SchemaEntry(array(
                'type' => 'bigint',
                'display_name' => __('Referral grace deriod days', 'solid-affiliate'),
                'required' => true,
                'user_default' => 30,
                'settings_group' => 'Fraud Prevention',
                'settings_tab' => self::TAB_FRAUD_PREVENTION,
                'show_on_edit_form' => true,
                'form_input_description' => __('Define days before paying old referrals. Recommended: Match your refund policy period.', 'solid-affiliate'),
                'custom_form_input_attributes' => ['min' => '1', 'max' => '1000']
            )),
            self::KEY_BULK_PAYOUTS_MINIMUM_PAYOUT_AMOUNT => new SchemaEntry(array(
                'type' => 'float',
                'required' => true,
                'display_name' => __('Minimum payout amount', 'solid-affiliate'),
                'user_default' => 0.0,
                'settings_group' => 'Commissions calculations',
                'settings_tab' => self::TAB_GENERAL,
                'form_input_description' => __('Set lowest commission for payment.', 'solid-affiliate'),
                'show_on_edit_form' => true,
                'is_zero_value_allowed' => true
            )),
            self::KEY_IS_LIFETIME_COMMISSIONS_ENABLED => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Lifetime commissions', 'solid-affiliate'),
                'user_default' => false,
                'settings_group' => self::GROUP_LIFETIME_COMMISSIONS,
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => true,
                'form_input_description' => __('Enable earning commission on all future purchases by referred customers.', 'solid-affiliate'),
            )),

            self::KEY_IS_LIFETIME_COMMISSIONS_AUTO_LINK_ENABLED => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Enable Link on Purchase', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => self::GROUP_LIFETIME_COMMISSIONS,
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => true,
                'form_input_description' => __('Automatically link a customer to the referring affiliate anytime a referral is earned.', 'solid-affiliate'),
            )),
            self::KEY_LIFETIME_COMMISSIONS_REFERRAL_RATE_TYPE => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'is_enum' => true,
                'enum_options' => [
                    ['percentage', __('Percentage (%)', 'solid-affiliate')],
                    ['flat', __('Flat', 'solid-affiliate')],
                    ['site_default', __('Site Default', 'solid-affiliate')],
                ],
                'display_name' => __('Lifetime Commission Rate Type', 'solid-affiliate'),
                'user_default' => 'site_default',
                'settings_group' => self::GROUP_LIFETIME_COMMISSIONS,
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => true,
                'form_input_description' => __('Used in conjunction with the referral rate to calculate the default referral amounts.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_LIFETIME_COMMISSIONS_REFERRAL_RATE => new SchemaEntry(array(
                'type' => 'float',
                'required' => true,
                'display_name' => __('Lifetime Commission Rate', 'solid-affiliate'),
                'user_default' => 20,
                'settings_group' => self::GROUP_LIFETIME_COMMISSIONS,
                'settings_tab' => self::TAB_GENERAL,
                'form_input_description' => GlobalTypes::REFERRAL_RATE_DESCRIPTION(),
                'show_on_edit_form' => true,
                'is_zero_value_allowed' => true,
                'validate_callback' => function (float $val) {
                    /**
                     * @psalm-suppress RedundantCondition
                     */
                    return is_numeric($val) && (float)$val >= 0;
                }
            )),
            self::KEY_LIFETIME_COMMISSIONS_DURATION_IN_SECONDS_STRING => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'is_enum' => true,
                'enum_options' => LifetimeCommissions::enum_options_lifetime_commissions_duration_in_seconds(),
                'display_name' => __('Lifetime commission duration', 'solid-affiliate'),
                'user_default' => LifetimeCommissions::enum_options_lifetime_commissions_duration_in_seconds()[0][0],
                'settings_group' => self::GROUP_LIFETIME_COMMISSIONS,
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => true,
                'form_input_description' => __('Set the lifetime customer relationship duration.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_IS_ONLY_NEW_CUSTOMER_LIFE_TIME_COMMISSIONS => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Exclude existing customers', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => self::GROUP_LIFETIME_COMMISSIONS,
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => true,
                'form_input_description' => __('Prevent lifetime commissions from being earned for existing customers', 'solid-affiliate'),
            )),
            self::KEY_IS_LIFETIME_COMMISSIONS_SHOW_AFFILIATES_THEIR_CUSTOMERS => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Show lifetime customers', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => self::GROUP_LIFETIME_COMMISSIONS,
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => true,
                'form_input_description' => __('Enable a tab in the affiliate portal to show lifetime customers. Data is anonymized.', 'solid-affiliate'),
            )),
            ///////////////////////////////////////////////////////
            // Integrations Tab
            ///////////////////////////////////////////////////////
            self::KEY_INTEGRATIONS_WOOCOMMERCE => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Integrations - WooCommerce', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => 'HIDDEN',
                'settings_tab' => self::TAB_INTEGRATIONS,
                'show_on_edit_form' => 'hidden',
                'form_input_description' => __('Always on. Enables the WooCommerce integration. You must have WooCommerce installed and activated.', 'solid-affiliate'),
                'required' => false
            )),
            ///////////////////////////////////////////////////////
            // PayPal Group
            self::KEY_INTEGRATIONS_PAYPAL_ACTIVE => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Enable PayPal Integration', 'solid-affiliate'),
                'user_default' => false,
                'settings_group' => 'PayPal',
                'settings_tab' => self::TAB_INTEGRATIONS,
                'show_on_edit_form' => true,
                'form_input_description' => sprintf(__("Turn on your PayPal Connection to easily pay your affiliates.<br /> You can find generate your API tokens in your", 'solid-affiliate') . " <a href='%s'>PayPal Developer Portal</a>.", GlobalTypes::PAYPAL_DEVELOPER_PORTAL_URL),
                'required' => false,
                'sanitize_callback' =>
                /** @param bool $value */
                function ($value) {
                    if (!WooCommerceIntegration::is_current_currency_valid_for_paypal_integration()) {
                        return false;
                    } else {
                        return $value;
                    }
                    // return $value === true || $value === false;
                }
            )),
            self::KEY_INTEGRATIONS_PAYPAL_USE_LIVE => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('PayPal Integration - Enable Live Mode', 'solid-affiliate'),
                'user_default' => false,
                'settings_group' => 'PayPal',
                'settings_tab' => self::TAB_INTEGRATIONS,
                'show_on_edit_form' => true,
                'form_input_description' => __("Use the LIVE PayPal account and credentials. Otherwise, the SANDBOX credentials will be used.", 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_INTEGRATIONS_PAYPAL_CLIENT_ID_LIVE => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('PayPal API Client ID - Live', 'solid-affiliate'),
                'user_default' => '',
                'settings_group' => 'PayPal',
                'settings_tab' => self::TAB_INTEGRATIONS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Sets your PayPal Client ID API Credential used to connect to your PayPal <strong>live</strong> account.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_INTEGRATIONS_PAYPAL_SECRET_LIVE => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('PayPal API Secret - Live', 'solid-affiliate'),
                'user_default' => '',
                'settings_group' => 'PayPal',
                'settings_tab' => self::TAB_INTEGRATIONS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Sets your PayPal Secret API Credential used to connect to your PayPal <strong>live</strong> account.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_INTEGRATIONS_PAYPAL_CLIENT_ID_SANDBOX => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('PayPal API Client ID - Sandbox', 'solid-affiliate'),
                'user_default' => '',
                'settings_group' => 'PayPal',
                'settings_tab' => self::TAB_INTEGRATIONS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Sets your PayPal Client ID API Credential used to connect to your PayPal <strong>sandbox</strong> account.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_INTEGRATIONS_PAYPAL_SECRET_SANDBOX => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('PayPal API Secret - Sandbox', 'solid-affiliate'),
                'user_default' => '',
                'settings_group' => 'PayPal',
                'settings_tab' => self::TAB_INTEGRATIONS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Sets your PayPal Secret API Credential used to connect to your PayPal <strong>sandbox</strong> account.', 'solid-affiliate'),
                'required' => false
            )),
            ///////////////////////////////////////////////////////
            // MailChimp Integration
            self::KEY_INTEGRATIONS_MAILCHIMP_API_KEY_LIVE => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('MailChimp API Key', 'solid-affiliate'),
                'user_default' => '',
                'settings_group' => 'MailChimp',
                'settings_tab' => self::TAB_INTEGRATIONS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Sets your MailChimp API key used to connect to your MailChimp account.', 'solid-affiliate') . __('The MailChimp API key is usually at least 30 charachters long. Please make sure you are using the correct API key before saving.') . ' <a href="https://docs.solidaffiliate.com/mailchimp-integration/" target="_blank">Mailchimp + Solid Affiliate</a>',
                'required' => false,
                'validate_callback' =>
                /** @param mixed $val */
                function ($val) {
                    // check that the string is either empty or at least 20 characters long
                    return empty($val) || strlen((string)$val) >= 20;
                },
            )),

            self::KEY_INTEGRATIONS_MAILCHIMP_IS_ACTIVE => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('MailChimp Integration - Enable Affiliate Sync', 'solid-affiliate'),
                'user_default' => false,
                'settings_group' => 'MailChimp',
                'settings_tab' => self::TAB_INTEGRATIONS,
                'show_on_edit_form' => true,
                'form_input_description' => __("When enabled, Solid Affiliate will sync new affiliate registrations as contacts in your MailChimp account. All contacts will be given an <code>affiliate</code> tag within MailChimp for easy segmentation.", 'solid-affiliate'),
                'required' => false
            )),

            self::KEY_INTEGRATIONS_MAILCHIMP_AFFILIATE_SYNC_LIST_ID => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('Affiliate Sync - List ID', 'solid-affiliate'),
                'user_default' => '',
                'settings_group' => 'MailChimp',
                'settings_tab' => self::TAB_INTEGRATIONS,
                'show_on_edit_form' => true,
                'form_input_description' => __('You can set a specific audience list within your MailChimp account by entering the ID. Solid Affiliate will sync newly registered Affiliates to this list. Leave it blank to sync to your default list. See <a href="https://mailchimp.com/help/find-audience-id/">finding your MailChimp audience ID.</a>', 'solid-affiliate'),
                'required' => false
            )),
            ///////////////////////////////////////////////////////
            // Emails Tab
            ///////////////////////////////////////////////////////
            // Email General Group
            // TODO validation and sanitization here.
            self::KEY_EMAIL_TEMPLATE => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'is_enum' => true,
                'enum_options' => [
                    ['default_template', __('Default email template', 'solid-affiliate')],
                    ['plaintext', __('Plaintext Only', 'solid-affiliate')],
                ],
                'display_name' => __('Email template', 'solid-affiliate'),
                'user_default' => 'default_template',
                'settings_group' => self::GROUP_EMAIL_GENERAL,
                'settings_tab' => self::TAB_EMAILS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Select an email template which all your outgoing emails will be processed through.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_EMAIL_FROM_NAME => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('From Name', 'solid-affiliate'),
                'user_default' => self::DEFAULT_EMAIL_FROM_NAME,
                'settings_group' => self::GROUP_EMAIL_GENERAL,
                'settings_tab' => self::TAB_EMAILS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Customize your email from name. The standard is to use your site name.', 'solid-affiliate'),
                'required' => true
            )),
            self::KEY_FROM_EMAIL => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('From Email', 'solid-affiliate'),
                'user_default' => self::DEFAULT_FROM_EMAIL,
                'settings_group' => self::GROUP_EMAIL_GENERAL,
                'settings_tab' => self::TAB_EMAILS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Set the email address which emails will be sent from. This will set the "from" and "reply-to" address.', 'solid-affiliate'),
                'required' => true,
                'validate_callback' =>
                /** @param mixed $val */
                function ($val) {
                    return !!filter_var($val, FILTER_VALIDATE_EMAIL);
                }
            )),
            self::KEY_EMAIL_NOTIFICATIONS => new SchemaEntry(array(
                'type' => 'multi_checkbox',
                'is_enum' => true,
                'enum_options' => [
                    [Email_Notifications::EMAIL_AFFILIATE_MANAGER_NEW_AFFILIATE, __('Affiliate Manager gets an email when: New Affiliate has registered.', 'solid-affiliate')],
                    [Email_Notifications::EMAIL_AFFILIATE_MANAGER_NEW_REFERRAL, __('Affiliate Manager gets an email when: New Referral has been created.', 'solid-affiliate')],
                    [Email_Notifications::EMAIL_AFFILIATE_NEW_REFERRAL, __('Affiliate gets an email when: New Referral has been earned by them.', 'solid-affiliate')],
                    [Email_Notifications::EMAIL_AFFILIATE_APPLICATION_ACCEPTED, __('Affiliate gets an email when: Their Affiliate Application has been accepted.', 'solid-affiliate')],
                ],
                'display_name' => __('Email notifications', 'solid-affiliate'),
                'user_default' => Email_Notifications::ALL_EMAILS,
                'settings_group' => self::GROUP_EMAIL_GENERAL,
                'settings_tab' => self::TAB_EMAILS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Which events should send an automated email. <strong>Note</strong>: You also have the option to disable referral email notifications for specific affiliates. <a href="https://docs.solidaffiliate.com/email-templates/#disable-referral-notification-emails-for-a-specific-affiliate">Learn More</a>', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_AFFILIATE_MANAGER_EMAIL => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('Affiliate Manager Email', 'solid-affiliate'),
                'user_default' => self::DEFAULT_AFFILIATE_MANAGER_EMAIL,
                'settings_group' => self::GROUP_EMAIL_GENERAL,
                'settings_tab' => self::TAB_EMAILS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Enter one or more email addresses to receive Affiliate Manager notifications. Separate multiple email addresses with a space in between.', 'solid-affiliate'),
                'required' => true,
                'validate_callback' =>
                /** @param mixed $val */
                function ($val) {
                    // we consider the case that there are multiple emails with a space in between
                    $all_emails = explode(' ', (string)$val);
                    $is_valid_array = array_map(function ($email) {
                        return !!filter_var($email, FILTER_VALIDATE_EMAIL);
                    }, $all_emails);
                    return !in_array(false, $is_valid_array, true);
                }
            )),

            // Affiliate Manager - Registration Notification Email
            self::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_MANAGER_NEW_AFFILIATE => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('Email subject', 'solid-affiliate'),
                'user_default' => 'New Affiliate Registration',
                'settings_group' => self::GROUP_EMAIL_AFFILIATE_MANAGER_NEW_AFFILIATE,
                'settings_tab' => self::TAB_EMAILS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Enter the subject line for this email.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_MANAGER_NEW_AFFILIATE => new SchemaEntry(array(
                'type' => 'wp_editor',
                'display_name' => __('Email body', 'solid-affiliate'),
                'user_default' => Email_Notifications::get_email_template_affiliate_manager_new_affiliate(),
                'settings_group' => self::GROUP_EMAIL_AFFILIATE_MANAGER_NEW_AFFILIATE,
                'settings_tab' => self::TAB_EMAILS,
                'show_on_edit_form' => true,
                'form_input_description' => __("Enter the email to send when a new affiliate registers. HTML is accepted. Available template tags:", 'solid-affiliate') . Templates::tags_to_documentation_html(['Affiliate']),
                'required' => false
            )),

            // Affiliate Manager - New Referral Notification Email
            self::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_MANAGER_NEW_REFERRAL => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('Email subject', 'solid-affiliate'),
                'user_default' => 'New Affiliate Referral',
                'settings_group' => self::GROUP_EMAIL_AFFILIATE_MANAGER_NEW_REFERRAL,
                'settings_tab' => self::TAB_EMAILS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Enter the subject line for this email.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_MANAGER_NEW_REFERRAL => new SchemaEntry(array(
                'type' => 'wp_editor',
                'display_name' => __('Email body', 'solid-affiliate'),
                'user_default' => Email_Notifications::get_email_template_affiliate_manager_new_referral(),
                'settings_group' => self::GROUP_EMAIL_AFFILIATE_MANAGER_NEW_REFERRAL,
                'settings_tab' => self::TAB_EMAILS,
                'show_on_edit_form' => true,
                'form_input_description' => __("Enter the email to send when an Affiliate earns a Referral. HTML is accepted. Available template tags:", 'solid-affiliate') . Templates::tags_to_documentation_html(),
                'required' => false
            )),

            // Affiliate - Application Accepted Notification Email
            self::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_APPLICATION_ACCEPTED => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('Email subject', 'solid-affiliate'),
                'user_default' => 'Application Accepted',
                'settings_group' => self::GROUP_EMAIL_AFFILIATE_APPLICATION_ACCEPTED,
                'settings_tab' => self::TAB_EMAILS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Enter the subject line for this email.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_APPLICATION_ACCEPTED => new SchemaEntry(array(
                'type' => 'wp_editor',
                'display_name' => __('Email body', 'solid-affiliate'),
                'user_default' => Email_Notifications::get_email_template_affiliate_application_accepted(),
                'settings_group' => self::GROUP_EMAIL_AFFILIATE_APPLICATION_ACCEPTED,
                'settings_tab' => self::TAB_EMAILS,
                'show_on_edit_form' => true,
                'form_input_description' => __("Enter the email to send to the Affiliate when their status gets updated to Approved. HTML is accepted. Available template tags:", 'solid-affiliate') . Templates::tags_to_documentation_html(['Affiliate']),
                'required' => false
            )),

            // Affiliate - Referral Earned Notification Email
            self::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_NEW_REFERRAL => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('Email subject', 'solid-affiliate'),
                'user_default' => 'New Referral Earned',
                'settings_group' => self::GROUP_EMAIL_AFFILIATE_NEW_REFERRAL,
                'settings_tab' => self::TAB_EMAILS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Enter the subject line for this email.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_NEW_REFERRAL => new SchemaEntry(array(
                'type' => 'wp_editor',
                'display_name' => __('Email body', 'solid-affiliate'),
                'user_default' => Email_Notifications::get_email_template_affiliate_new_referral(),
                'settings_group' => self::GROUP_EMAIL_AFFILIATE_NEW_REFERRAL,
                'settings_tab' => self::TAB_EMAILS,
                'show_on_edit_form' => true,
                'form_input_description' => __("Enter the email to send when an Affiliate earns a Referral. HTML is accepted. Available template tags:", 'solid-affiiate') . Templates::tags_to_documentation_html(),
                'required' => false
            )),

            // Affiliate - Referral Earned Notification Email
            self::KEY_NOTIFICATION_EMAIL_SUBJECT_AFFILIATE_SETUP_WIZARD_INVITE => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('Email subject', 'solid-affiliate'),
                'user_default' => 'Referral Program Notice',
                'settings_group' => 'Welcome email',
                'settings_tab' => self::TAB_EMAILS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Enter the subject line for this email.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_NOTIFICATION_EMAIL_BODY_AFFILIATE_SETUP_WIZARD_INVITE => new SchemaEntry(array(
                'type' => 'wp_editor',
                'display_name' => __('Email body', 'solid-affiliate'),
                'user_default' => 'You have been invited to join our referral program. Share this link to earn cash or store credit: {default_affiliate_link}',
                'settings_group' => 'Welcome email',
                'settings_tab' => self::TAB_EMAILS,
                'show_on_edit_form' => true,
                'form_input_description' => __("Enter the email to send users that you setup as affiliates from within the setup wizard. HTML is accepted. Available template tags:", 'solid-affiiate') . Templates::tags_to_documentation_html(['Affiliate']),
                'required' => false
            )),

            ///////////////////////////////////////////////////////
            // Misc Tab
            ///////////////////////////////////////////////////////
            // Misc General Group
            self::KEY_IS_REJECT_UNPAID_REFERRALS_ON_REFUND => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Reject unpaid referrals on refund', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => 'Fraud Prevention',
                'settings_tab' => self::TAB_FRAUD_PREVENTION,
                'show_on_edit_form' => true,
                'form_input_description' => __(' Automatically cancel unpaid referrals if the original purchase is refunded.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_IS_DISABLE_CUSTOMER_IP_ADDRESS_LOGGING => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Disable IP address logging', 'solid-affiliate'),
                'user_default' => false,
                'settings_group' => 'Fraud Prevention',
                'settings_tab' => self::TAB_FRAUD_PREVENTION,
                'show_on_edit_form' => true,
                'form_input_description' => __('Option to stop recording IP addresses. IP logging can help detect potential fraud.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_IS_AFFILIATE_MANAGER_MONTHLY_REPORT_EMAIL_ENABLED => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Enable Monthly Report Email', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => 'Email notifications',
                'settings_tab' => self::TAB_EMAILS,
                'show_on_edit_form' => true,
                'form_input_description' => __('When enabled, a monthly report email will be sent to your affiliate managers.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_REFERRAL_URL_BLACKLIST => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('Referral URL Blacklist TODO Make text area, change description.', 'solid-affiliate'),
                'user_default' => '',
                'settings_group' => 'Misc',
                'settings_tab' => self::TAB_MISC,
                'show_on_edit_form' => false, // TODO V2 removed this because we don't currently use
                'form_input_description' => __('URLs placed here will be blocked from generating referrals. Separated URLs with a space. NOTE: This will only apply to new visits after the URL has been saved.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_IS_BASIC_USAGE_STATISTICS_ENABLED => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Share basic usage stats', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => 'Misc',
                'settings_tab' => self::TAB_MISC,
                'show_on_edit_form' => true,
                'form_input_description' => __('Help Solid Affiliate make a great product by sending basic usage statistics. This also helps us provide you better support.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_IS_REMOVE_DATA_ON_UNINSTALL => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Remove data on uninstall', 'solid-affiliate'),
                'user_default' => false,
                'settings_group' => 'Misc',
                'settings_tab' => self::TAB_MISC,
                'show_on_edit_form' => true,
                'form_input_description' => __('Enable to remove all saved data for Solid Affiliate when the plugin is deleted. This will drop all database tables, you will not be able to recover the data through Solid Affiliate.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_IS_SETUP_WIZARD_DISPLAYED => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Show setup wizard', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => 'Setup Wizard',
                'settings_tab' => self::TAB_MISC,
                'show_on_edit_form' => false,
                'form_input_description' => __('Show the setup wizard in the admin menu. This setting is automatically set to false once the initial setup is complete.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_IS_SETUP_WIZARD_AFFILIATE_PORTAL_SETUP_COMPLETE => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Is Affiliate Portal Setup Complete', 'solid-affiliate'),
                'user_default' => false,
                'settings_group' => 'Setup Wizard',
                'settings_tab' => self::TAB_MISC,
                'show_on_edit_form' => false,
                'form_input_description' => __('Mark whether the Affiliate Portal Setup step is complete. This setting is automatically adjusted by the Setup Wizard, but can also be manually changed here.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_WOOCOMMERCE_ORDER_STATUSES_WHICH_SHOULD_TRIGGER_A_REFERRAL => new SchemaEntry(array(
                'type' => 'multi_checkbox',
                'length' => 50,
                'is_enum' => true,
                'enum_options' => WooCommerceIntegration::ORDER_STATUSES_ENUM_OPTIONS,
                'display_name' => __('WooCommerce order statuses to trigger a referral', 'solid-affiliate'),
                'user_default' => WooCommerceIntegration::ORDER_STATUSES,
                'settings_group' => 'Advanced',
                'settings_tab' => self::TAB_MISC,
                'show_on_edit_form' => true,
                'form_input_description' => __('Select WooCommerce order statuses that should trigger a referral and mark it as unpaid. By default, referrals are marked unpaid when the order status changes to Processing, Completed, or Shipped. Adjust this based on your needs.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_IS_COOKIES_DISABLED => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Disable all cookie tracking', 'solid-affiliate'),
                'user_default' => false,
                'settings_group' => 'Advanced',
                'settings_tab' => self::TAB_MISC,
                'show_on_edit_form' => true,
                'required' => false,
                'form_input_description' => __('If you turn this setting on, all cookie tracking will be disabled. Solid Affiliate will not set any cookies. Visit tracking will not work without cookies.', 'solid-affiliate')
            )),
            self::KEY_IS_SERVER_COOKIES_DISABLED => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Disable server-side cookie tracking', 'solid-affiliate'),
                'user_default' => false,
                'settings_group' => 'Advanced',
                'settings_tab' => self::TAB_MISC,
                'show_on_edit_form' => true,
                'required' => false,
                'form_input_description' => __('Enabling this will disable server-side cookies. Cookies will only be set via JavaScript. This is useful if you need to conditionally enable tracking, such as with a GDPR consent solution. Solid Affiliate will still work perfectly with this setting enabled.', 'solid-affiliate')
            )),
            self::KEY_IS_AUTOMATIC_VISIT_TRACKING_DISABLED => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Disable automatic client side tracking', 'solid-affiliate'),
                'user_default' => false,
                'settings_group' => 'Advanced',
                'settings_tab' => self::TAB_MISC,
                'show_on_edit_form' => true,
                'required' => false,
                'form_input_description' => __('If you turn this setting on, you will need to manually call SolidAffiliate.maybe_track_visit() in Javascript. This might be useful if you need to conditionally enable tracking via a GDPR consent solution, for example.', 'solid-affiliate')
            )),

            self::KEY_IS_LOGGING_DISABLED => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Disable logging (used by Solid Affiliate support team)', 'solid-affiliate'),
                'user_default' => false,
                'settings_group' => 'Advanced',
                'settings_tab' => self::TAB_MISC,
                'show_on_edit_form' => true,
                'required' => false,
                'form_input_description' => __('If you turn this setting on, troubleshooting logging will be disabled. Disabling logging may increase performance.', 'solid-affiliate')
            )),
            ///////////////////////////////////////////////////////
            // Subscription Renewal Referrals Tab
            ///////////////////////////////////////////////////////
            // Subscription Renewal Referrals General Group
            self::KEY_IS_ENABLE_RECURRING_REFERRALS => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Enable renewal referrals', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => 'Subscription Renewal Referrals',
                'settings_tab' => self::TAB_RECURRING_REFERRALS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Enable this to track referrals for subscription renewals. The affiliate who referred the initial subscription will receive a referral for each renewal payment.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_RECURRING_REFERRAL_RATE => new SchemaEntry(array(
                'type' => 'bigint',
                'display_name' => __('Subscription Renewal Referral Rate', 'solid-affiliate'),
                'user_default' => 20,
                'settings_group' => 'Subscription Renewal Referrals',
                'settings_tab' => self::TAB_RECURRING_REFERRALS,
                'show_on_edit_form' => true,
                'form_input_description' => __("This is the default rate for subscription renewals. When the rate type is 'Percentage (%)', this is a percentage. When set to 'Flat', it is a fixed amount in your currency (e.g., $10.00).", 'solid-affiliate'),
                'required' => true,
                'is_zero_value_allowed' => true,
                'validate_callback' => function (float $val) {
                    /**
                     * @psalm-suppress RedundantCondition
                     */
                    return is_numeric($val) && (float)$val >= 0;
                }
            )),
            self::KEY_RECURRING_REFERRAL_RATE_TYPE => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'is_enum' => true,
                'enum_options' => [
                    ['percentage', __('Percentage (%)', 'solid-affiliate')],
                    ['flat', __('Flat', 'solid-affiliate')],
                ],
                'display_name' => __('Subscription Renewal Referral Rate Type', 'solid-affiliate'),
                'user_default' => 'percentage',
                'settings_group' => 'Subscription Renewal Referrals',
                'settings_tab' => self::TAB_RECURRING_REFERRALS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Used in conjunction with the Subscription Renewal Referral Rate to calculate the default referral amounts for subscription purchases and renewals.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_RECURRING_REFERRAL_LIMIT_DURATION_IN_SECONDS_STRING => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'is_enum' => true,
                'enum_options' => LifetimeCommissions::enum_options_lifetime_commissions_duration_in_seconds(),
                'display_name' => __('Recurring Referral Time Limit', 'solid-affiliate'),
                'user_default' => LifetimeCommissions::enum_options_lifetime_commissions_duration_in_seconds()[0][0],
                'settings_group' => 'Subscription Renewal Referrals',
                'settings_tab' => self::TAB_RECURRING_REFERRALS,
                'show_on_edit_form' => true,
                'form_input_description' => __('The duration for which an affiliate will receive renewal referrals for any given subscription. The default is no limit.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_IS_EXCLUDE_SIGNUP_FEE_RECURRING_REFERRALS => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Exclude Signup Fees', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => 'Subscription Renewal Referrals',
                'settings_tab' => self::TAB_RECURRING_REFERRALS,
                'show_on_edit_form' => false,
                'form_input_description' => __('Exclude Signup Fees when calculating commissions for subscription renewal referrals.', 'solid-affiliate'),
                'required' => false
            )),
            ///////////////////////////////////////////////////////
            // Customize Registration Form Tab
            ///////////////////////////////////////////////////////
            self::KEY_CUSTOM_REGISTRATION_FORM_SCHEMA => new SchemaEntry(array(
                'type' => 'text',
                'display_name' => 'Customize the Affiliate Registration Form',
                'user_default' => '',
                'settings_group' => 'HIDDEN',
                'settings_tab' => self::TAB_CUSTOMIZE_REGISTRATION_FORM,
                'show_on_edit_form' => 'hidden',
                'form_input_description' => __('Customize your Affiliate Registration', 'solid-affiliate'),
                'required' => false,
                'validate_callback' =>
                /** @param mixed $entries */
                function ($entries) {
                    # NOTE: It should never be empty UNLESS the setting has not been created yet.
                    #       Settings::get_all() will return it as an empty string, as that is the default for text fields.
                    #       So, do not validate if the setting has not be set yet.
                    if (empty($entries)) {
                        return [true, ''];
                    }

                    if (in_array($entries, AffiliateRegistrationFormFunctions::ERROR_KEYS)) {
                        return [false, ErrorMessages::get_message(Validators::str($entries))];
                    } else {
                        if (AffiliateRegistrationFormFunctions::can_json_str_be_saved_read_and_decoded($entries)) {
                            return [true, ''];
                        } else {
                            return [false, AffiliateRegistrationFormFunctions::ERROR_KEY_DECODE_FAILURE];
                        }
                    }
                },
                'sanitize_callback' =>
                /** @param mixed $controls */
                function ($controls) {
                    # NOTE: The unique and required validations are in the sanitize_callback instead of the validate_callback,
                    #       because the sanitize_callback converts the SchemaEntry's to a json encoded string, which is harder
                    #       to iterate over and check for invalid values. As an array<string, SchemaEntry>, it is much easier to do so.
                    $controls_str = Validators::str($controls);
                    $globally_required_entries = AffiliatePortal::required_affiliate_registration_schema_entries();
                    if (Utils::is_empty($controls_str)) {
                        $maybe_str = AffiliateRegistrationFormFunctions::encode_to_json_string($globally_required_entries);
                        if (is_null($maybe_str)) {
                            return AffiliateRegistrationFormFunctions::ERROR_KEY_ENCODE_FAILURE;
                        } else {
                            return sanitize_text_field($maybe_str);
                        }
                    } else {
                        $maybe_arr = AffiliateRegistrationFormFunctions::decode_json_string(Validators::str(wp_unslash($controls_str)));
                        if (is_null($maybe_arr)) {
                            return AffiliateRegistrationFormFunctions::ERROR_KEY_DECODE_FAILURE;
                        }

                        # NOTE: The has_unique_names validation is separate from the has_all_required_and_unique_data,
                        #       because once the JSON payload is converted to an array<string, SchemaEntry> the duplicate names
                        #       will silently only take the second entry with the dup name, and the validate_callback will not
                        #       know that the JSON payload had duplicate names. @see https://www.php.net/manual/en/language.types.array.php
                        #       -> If multiple elements in the array declaration use the same key, only the last one will be used as all others are overwritten.
                        if (!AffiliateRegistrationFormFunctions::has_unique_names($maybe_arr)) {
                            return AffiliateRegistrationFormFunctions::ERROR_KEY_UNIQUE_NAMES;
                        }

                        $schema = SchemaSerializer::custom_formbuilder_post_to_schema($maybe_arr);
                        if (AffiliateRegistrationFormFunctions::has_past_name_and_type_conflict($schema)) {
                            return AffiliateRegistrationFormFunctions::ERROR_KEY_PAST_TYPE_CONFLICT;
                        }

                        $all_entries = AffiliateRegistrationFormFunctions::merge_required_and_shared_schema_entries(
                            $schema->entries,
                            AffiliatePortal::registration_schema_entries_that_are_also_on_affiliate(),
                            $globally_required_entries
                        );
                        $validate_result = AffiliateRegistrationFormFunctions::has_all_required_and_unique_data($all_entries);
                        if (!$validate_result[0]) {
                            return $validate_result[1];
                        }

                        $maybe_str = AffiliateRegistrationFormFunctions::encode_to_json_string($all_entries);
                        if (is_null($maybe_str)) {
                            return AffiliateRegistrationFormFunctions::ERROR_KEY_ENCODE_FAILURE;
                        }

                        return sanitize_text_field($maybe_str);
                    }
                }
            )),
            ///////////////////////////////////////////////////////
            // Affiliate Portal & Registration Tab
            ///////////////////////////////////////////////////////
            // Affiliate Registration Group
            self::KEY_IS_REQUIRE_AFFILIATE_REGISTRATION_APPROVAL => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Require application approval', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => 'Affiliate management',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'show_on_edit_form' => true,
                'form_input_description' => __('Manually approve new affiliate applications before they can earn referrals.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_AFFILIATE_PORTAL_SHORTCODE => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('Affiliate Portal Shortcode', 'solid-affiliate'),
                'user_default' => 'solid_affiliate_portal',
                'settings_group' => 'Affiliate Portal',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'show_on_edit_form' => 'hidden_and_disabled',
                'form_input_description' => __('Use the shortcode <code>[solid_affiliate_portal]</code> to render the Affiliate Portal on a page of your choosing.', 'solid-affiliate'),
                'required' => true
            )),
            self::KEY_AFFILIATE_PORTAL_PAGE => new SchemaEntry(array(
                'type' => 'wp_page',
                'display_name' => __('Affiliate portal page', 'solid-affiliate'),
                'user_default' => '',
                'settings_group' => 'Affiliate Portal',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'show_on_edit_form' => true, // TODO what to do with this setting?
                'form_input_description' => __('Select the page with the [solid_affiliate_portal] shortcode. You must manually add the shortcode. This setting ensures proper redirection for affiliate login and registration.', 'solid-affiliate'),
            )),
            self::KEY_TERMS_OF_USE_PAGE => new SchemaEntry(array(
                'type' => 'wp_page',
                'display_name' => __('Terms of use page', 'solid-affiliate'),
                'user_default' => '',
                'settings_group' => 'Affiliate Portal',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'form_input_description' => __('Choose the page with your affiliate terms. It will be linked during registration.', 'solid-affiliate'),
                'show_on_edit_form' => true // TODO V2 set this to true and make this setting work
            )),
            self::KEY_TERMS_OF_USE_LABEL => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('Terms of use label', 'solid-affiliate'),
                'user_default' => __('Agree to our Terms of Service and Privacy Policy', 'solid-affiliate'),
                'settings_group' => 'Affiliate Portal',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'form_input_description' => __('Customize the text shown on the label during registration.', 'solid-affiliate'),
                'show_on_edit_form' => true // TODO V2 set this to true and make this setting work
            )),
            self::KEY_AFFILIATE_WORD_ON_PORTAL_FORMS => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('Affilate display name on forms', 'solid-affiliate'),
                'user_default' => __('Affiliate', 'solid-affiliate'),
                'settings_group' => 'Affiliate Portal',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'form_input_description' => __('Customize "Affiliate" label on the affiliate registration and login forms.', 'solid-affiliate'),
                'show_on_edit_form' => true // TODO V2 set this to true and make this setting work
            )),

            self::KEY_AFFILIATE_PORTAL_FORMS_THEME => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 50,
                'is_enum' => true,
                'enum_options' => [
                    ['light', __('Light mode', 'solid-affiliate')],
                    ['dark', __('Dark mode', 'solid-affiliate')],
                ],
                'display_name' => __('Affiliate portal theme', 'solid-affiliate'),
                'user_default' => 'light',
                'settings_group' => 'Affiliate Portal',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'show_on_edit_form' => true,
                'form_input_description' => __('Select the theme for your affiliate portal.', 'solid-affiliate'),
                'required' => false
            )),

            self::KEY_AFFILIATE_WORD_IN_PORTAL => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('Affilate display name in the portal', 'solid-affiliate'),
                'user_default' => __('Affiliate', 'solid-affiliate'),
                'settings_group' => 'Affiliate Portal',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'show_on_edit_form' => true,
                'form_input_description' => __('Customize "Affiliate" label across portal (e.g., "Partner", "Influencer", "Ambassador")', 'solid-affiliate'),
                'required' => false
            )),
            // Affiliate Portal Form
            self::KEY_AFFILIATE_PORTAL_FORMS => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 50,
                'is_enum' => true,
                'enum_options' => [
                    ['registration_and_login', __('Both forms', 'solid-affiliate')],
                    ['registration', __('Registration form only', 'solid-affiliate')],
                    ['login', __('Login form only', 'solid-affiliate')],
                    ['none', __('None', 'solid-affiliate')],
                ],
                'display_name' => __('Logged-out forms', 'solid-affiliate'),
                'user_default' => 'registration_and_login',
                'settings_group' => 'Affiliate Portal',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'show_on_edit_form' => true,
                'form_input_description' => __('Choose visible forms for non-logged users.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_AFFILIATE_REGISTRATION_FIELDS_TO_DISPLAY => new SchemaEntry(array(
                'type' => 'multi_checkbox',
                'is_enum' => true,
                'enum_options' => [
                    ['payment_email', __('Payment Email (The Email used for receiving referral payouts)', 'solid-affiliate')],
                    ['first_name', __('First Name', 'solid-affiliate')],
                    ['last_name', __('Last Name', 'solid-affiliate')],
                    ['registration_notes', __('Registration Notes', 'solid-affiliate')],
                ],
                'display_name' => __('Affiliate Registration Fields', 'solid-affiliate'),
                'user_default' => ['payment_email', 'registration_notes'],
                'settings_group' => 'Affiliate Portal',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'show_on_edit_form' => false,
                'form_input_description' => __('Select the fields to include on the Affiliate Registration Form. NOTE: Username, Email, and Password are always included.', 'solid-affiliate'),
                'required' => false,
                'sanitize_callback' =>
                /** @param mixed $enum_options
                 * @return array<string>
                 */
                function ($enum_options) {
                    $enum_options = Validators::array_of_string($enum_options);
                    return $enum_options;
                }
            )),

            self::KEY_REQUIRED_AFFILIATE_REGISTRATION_FIELDS => new SchemaEntry(array(
                'type' => 'multi_checkbox',
                'is_enum' => true,
                'enum_options' => [
                    // ['username', __('Username', 'solid-affiliate')],
                    // ['account_email', __('Account Email', 'solid-affiliate')],
                    // ['password', __('Password', 'solid-affiliate')],
                    ['payment_email', __('Payment Email (The Email used for receiving referral payouts)', 'solid-affiliate')],
                    ['first_name', __('First Name', 'solid-affiliate')],
                    ['last_name', __('Last Name', 'solid-affiliate')],
                    ['registration_notes', __('Registration Notes', 'solid-affiliate')],
                ],
                'display_name' => __('Required Affiliate Registration Fields', 'solid-affiliate'),
                // 'user_default' => ['username', 'account_email', 'payment_email', 'registration_notes'],
                'user_default' => ['payment_email', 'registration_notes'],
                // 'user_default' => 'Username',
                'settings_group' => 'Affiliate Portal',
                // 'settings_group' => 'HIDDEN',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                // 'settings_tab' => self::TAB_CUSTOMIZE_REGISTRATION_FORM,
                'show_on_edit_form' => false,
                'form_input_description' => __('Select the fields which need to be required on the Affiliate Registration Form. NOTE: Username, Email, and Password are always required fields.', 'solid-affiliate'),
                'required' => false,
                'sanitize_callback' =>
                /** @param mixed $enum_options
                 * @return array<string>
                 */
                function ($enum_options) {
                    # TODO:3: Do we want validations?
                    $enum_options = Validators::array_of_string($enum_options);
                    // if (!in_array('username', $enum_options)) {
                    //     array_push($enum_options, 'username');
                    // }
                    // if (!in_array('account_email', $enum_options)) {
                    //     array_push($enum_options, 'account_email');
                    // }
                    // if (!in_array('password', $enum_options)) {
                    //     array_push($enum_options, 'password');
                    // }
                    return $enum_options;
                }
            )),
            self::KEY_IS_RECAPTCHA_ENABLED_FOR_AFFILIATE_REGISTRATION => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Enable reCAPTCHA', 'solid-affiliate'),
                'user_default' => false,
                'settings_group' => 'Google reCAPTCHA',
                'settings_tab' => self::TAB_INTEGRATIONS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Add a Google reCAPTCHA v2 checkbox to the Affiliate Registration form. This will help prevent bots.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_RECAPTCHA_SITE_KEY => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('reCAPTCHA Site Key', 'solid-affiliate'),
                'user_default' => '',
                'settings_group' => 'Google reCAPTCHA',
                'settings_tab' => self::TAB_INTEGRATIONS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Enter your reCAPTCHA site key.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_RECAPTCHA_SECRET_KEY => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('reCAPTCHA Secret Key', 'solid-affiliate'),
                'user_default' => '',
                'settings_group' => 'Google reCAPTCHA',
                'settings_tab' => self::TAB_INTEGRATIONS,
                'show_on_edit_form' => true,
                'form_input_description' => __('Enter your reCAPTCHA secret key.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_IS_LOGOUT_LINK_ON_AFFILIATE_PORTAL => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Logout link', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => 'Affiliate Portal',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'show_on_edit_form' => true,
                'form_input_description' => __('Show a logout link on the affiliate portal.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_IS_DISPLAY_CUSTOMER_INFO_ON_AFFILIATE_PORTAL => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Customer billing details', 'solid-affiliate'),
                'user_default' => false,
                'settings_group' => 'Affiliate Portal',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'show_on_edit_form' => true,
                'form_input_description' => __('Show billing details of the customers which generated referrals on the affiliate portal.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_REFERRAL_STATUSES_TO_DISPLAY_TO_AFFILIATES => new SchemaEntry(array(
                'type' => 'multi_checkbox',
                'length' => 50,
                'is_enum' => true,
                'enum_options' => Referral::status_enum_options(true),
                'display_name' => __('Referral statuses filter', 'solid-affiliate'),
                'user_default' => Referral::STATUSES_PAID_AND_UNPAID,
                'settings_group' => 'Affiliate Portal',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'show_on_edit_form' => true,
                'form_input_description' => __('Select which referral statuses are visible to affiliates in their portal.', 'solid-affiliate'),
                'required' => false
            )),
            self::KEY_DEFAULT_DISPLAY_AFFILIATE_SLUG_FORMAT => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'is_enum' => true,
                'enum_options' => AffiliateCustomSlugBase::default_display_formats_enum_options(),
                'display_name' => __('Affiliate link format', 'solid-affiliate'),
                'user_default' => AffiliateCustomSlugBase::DEFAULT_DISPLAY_FORMAT_VALUE,
                'settings_group' => 'Affiliate management',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'show_on_edit_form' => true,
                'form_input_description' => __("Choose how affiliate links are displayed: ID or custom slug.", 'solid-affiliate'),
                'sanitize_callback' =>
                /** @param mixed $val */
                static function ($val) {
                    return strtolower(trim(Validators::str($val)));
                },
                'validate_callback' =>
                /** @param mixed $val */
                static function ($val) {
                    return in_array(Validators::str($val), AffiliateCustomSlugBase::ALL_DISPLAY_FORMAT_VALUES, true);
                }
            )),
            self::KEY_DEFAULT_AFFILIATE_LINK_URL => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'display_name' => __('Default affiliate URL', 'solid-affiliate'),
                'user_default' => home_url('/'),
                'settings_group' => 'Affiliate management',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'form_input_description' => __('Override homepage URL with a custom URL. Use dynamic variables:<code>{{affiliate.slug}}</code> for the custom slug, and <code>{{affiliate.id}}</code> for the affiliate ID.', 'solid-affiliate'),
                'show_on_edit_form' => true,
                'validate_callback' =>
                /** @param mixed $val */
                function ($val) {
                    if (empty($val)) {
                        return true;
                    }

                    if (filter_var((string)$val, FILTER_VALIDATE_URL) === false) {
                        return false;
                    } else {
                        return true;
                    }
                },
                'form_input_placeholder' => 'https://yoursite.com/landing-page/'
            )),
            self::KEY_AFFILIATE_PORTAL_TABS_TO_HIDE => new SchemaEntry(array(
                'type' => 'multi_checkbox',
                'is_enum' => true,
                'enum_options' => \SolidAffiliate\Views\AffiliatePortal\AffiliatePortalTabsView::get_filtered_tab_tuples(\SolidAffiliate\Views\AffiliatePortal\DashboardView::default_affiliate_tabs_enum()),
                'display_name' => __('Hide tabs from portal', 'solid-affiliate'),
                'user_default' => [],
                'settings_group' => 'Affiliate Portal',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'show_on_edit_form' => true,
                'form_input_description' => __('Select tabs to hide from the affiliate portal.', 'solid-affiliate'),
                'required' => false,
                'sanitize_callback' =>
                /** @param mixed $enum_options
                 * @return array<string>
                 */
                function ($enum_options) {
                    $enum_options = Validators::array_of_string($enum_options);
                    return $enum_options;
                }
            )),
            // Other group
            self::KEY_IS_HIDE_AFFILIATE_PORTAL_FROM_UNAPPROVED_AFFILIATES => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Affiliate portal visilibity', 'solid-affiliate'),
                'user_default' => false,
                'settings_group' => 'Affiliate management',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'show_on_edit_form' => true,
                'form_input_description' => __('Hides the Affiliate Portal dashboard for unapproved affiliates.', 'solid-affiliate'),
                'required' => false,
            )),

            self::KEY_UNAPPROVED_AFFILIATES_CONTENT => new SchemaEntry(array(
                'type' => 'wp_editor',
                'display_name' => __('Unapproved Affiliate content', 'solid-affiliate'),
                'user_default' => "<h2>Your affiliate registration is pending approval. You'll receive an email once it's approved.</h2>",
                'settings_group' => 'Affiliate management',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'show_on_edit_form' => true,
                'form_input_description' => __('Content shown to unapproved affiliates when the "Hide Affiliate Portal" setting is enabled.', 'solid-affiliate'),
                'required' => false,
            )),

            self::KEY_AFFILIATE_PORTAL_IS_ANCHOR_TAG_ENABLED => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Anchor tagging tabs', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => 'Affiliate Portal',
                'settings_tab' => self::TAB_AFFILIATE_PORTAL_AND_REGISTRATION,
                'show_on_edit_form' => true,
                'form_input_description' => __('Enable this to add anchor tags to the Affiliate Portal tabs, ensuring the browser scrolls to the portal on refresh.', 'solid-affiliate'),
                'required' => false,
            )),

            ////////////////////////////////////
            // Affiliate groups
            self::KEY_AFFILIATE_GROUP_SHOULD_CREATE_DEFAULT => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Should create default affiliate group', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => 'Affiliate groups',
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => 'hidden',
                'form_input_description' => __('INTERNAL. Whether or not a default group should be created on admin page load.', 'solid-affiliate'),
                'required' => false,
            )),
            self::KEY_AFFILIATE_GROUP_SHOULD_ADD_AFFILIATES_TO_DEFAULT_GROUP => new SchemaEntry(array(
                'type' => 'bool',
                'display_name' => __('Auto-group assignment', 'solid-affiliate'),
                'user_default' => true,
                'settings_group' => 'Affiliate groups',
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => true,
                'form_input_description' => __('Automatically add new affiliates to default group.', 'solid-affiliate'),
                'required' => false,
            )),
            self::KEY_AFFILIATE_GROUP_DEFAULT_GROUP_ID => new SchemaEntry(array(
                'type' => 'bigint',
                'required' => true,
                'display_name' => __('Default affiliate group', 'solid-affiliate'),
                'user_default' => 0,
                'is_enum'                => true,
                'enum_options'           => [AffiliateGroup::class, 'affiliate_groups_list'],
                'settings_group' => 'Affiliate groups',
                'settings_tab' => self::TAB_GENERAL,
                'show_on_edit_form' => true,
                'form_input_description' => __('Select the default affiliate group. Applies when auto-group assignment is enabled.', 'solid-affiliate'),
            ))
        );

        $entries = FF::filter_settings_schema_entries($entries);

        self::$schema_cache = new Schema(['entries' => $entries]);

        return self::$schema_cache;
    }

    /**
     * @param Settings::TAB_* $tab
     *
     * @return Schema<string>
     */
    public static function schema_for_tab($tab)
    {
        $full_schema = Settings::schema();

        $keys_of_matching_entries = SchemaFunctions::_keys_that_have_prop_some_value_from_schema($full_schema, 'settings_tab', $tab);

        $schema_for_tab = SchemaFunctions::_filter_schema_entries_by_keys($full_schema, $keys_of_matching_entries);

        return $schema_for_tab;
    }

    /**
     * @param Schema $schema
     * @param string $settings_group
     *
     * @return Schema<string>
     */
    public static function schema_for_settings_group($schema, $settings_group)
    {
        $full_schema = $schema; //Settings::schema();

        $keys_of_matching_entries = SchemaFunctions::_keys_that_have_prop_some_value_from_schema($full_schema, 'settings_group', $settings_group);

        $schema_for_tab = SchemaFunctions::_filter_schema_entries_by_keys($full_schema, $keys_of_matching_entries);

        return $schema_for_tab;
    }

    /**
     * Undocumented function
     *
     * Example:
     *  $setting_groups = [
     *    'Referral' => Settings::schema_for_settings_group('Referral'),
     *    'Integrations' => Settings::schema_for_settings_group('Integrations')
     *  ];
     *
     * @param Schema $schema
     * @return array<string, Schema<string>>
     */
    public static function schema_grouped_by_settings_groups($schema)
    {
        /** @var array<string> */
        $unique_setting_groups = SchemaFunctions::unique_values_from_schema_by_key($schema, 'settings_group');

        $mapping = array();
        foreach ($unique_setting_groups as $setting_group_title) {
            $mapping[$setting_group_title] = Settings::schema_for_settings_group($schema, $setting_group_title);
        }

        return $mapping;
    }

    /**
     * Checks the settings to determine if the PayPal integration is ready to be used.
     * It does not check if the API credential are valid, simply whether they exists 
     * and the Integration is enabled.
     * 
     * TODO If live mode is enabled it will check if the live credentials are set.
     * Otherwise it will check if sandbox credentials are set.
     *
     * @psalm-suppress PossiblyUndefinedArrayOffset
     * @psalm-suppress MixedAssignment 
     * 
     * @return boolean
     */
    public static function is_paypal_integration_configured_and_enabled()
    {
        $use_live = boolval(Settings::get(Settings::KEY_INTEGRATIONS_PAYPAL_USE_LIVE));
        if ($use_live) {
            list($is_active, $client_id, $secret) = array_values(self::get_many([
                Settings::KEY_INTEGRATIONS_PAYPAL_ACTIVE,
                Settings::KEY_INTEGRATIONS_PAYPAL_CLIENT_ID_LIVE,
                Settings::KEY_INTEGRATIONS_PAYPAL_SECRET_LIVE
            ]));

            return (($is_active === true) && (!empty($client_id)) && (!empty($secret)));
        } else {
            list($is_active, $client_id, $secret) = array_values(self::get_many([
                Settings::KEY_INTEGRATIONS_PAYPAL_ACTIVE,
                Settings::KEY_INTEGRATIONS_PAYPAL_CLIENT_ID_SANDBOX,
                Settings::KEY_INTEGRATIONS_PAYPAL_SECRET_SANDBOX
            ]));

            return (($is_active === true) && (!empty($client_id)) && (!empty($secret)));
        }
    }


    /**
     * @return void
     */
    public static function register_hooks()
    {
        add_action('solid_affiliate/settings/group_heading/after', [Settings::class, 'after_group_heading'], 10, 1);
    }


    /**
     * @param string $settings_group_name
     * @return void
     */
    public static function after_group_heading($settings_group_name)
    {
        if ($settings_group_name === 'PayPal') {
            echo '
                       <a href="https://docs.solidaffiliate.com/paypal-payouts/" class="sld-admin-card_button" target="_blank">' . __('Documentation', 'solid-affiliate') . '</a>
                       ';

            if (!WooCommerceIntegration::is_current_currency_valid_for_paypal_integration()) {
                ob_start();
?>
                <?php if (!WooCommerceIntegration::is_current_currency_valid_for_paypal_integration()) { ?>
                    <?php $current_currency = WooCommerceIntegration::get_current_currency(); ?>
                    <br>
                    <div class='notice notice-error inline'>
                        <p><strong><?php _e('IMPORTANT', 'solid-affiliate') ?> - <?php _e('You have an incompatible currency configured within WooCommerce.', 'solid-affiliate') ?> </strong></p>
                        <p><?php echo sprintf(__('Your current currency <code>%1$s</code> is incompatible with the <code>Pay Affiliates tool</code> PayPal Integration.', 'solid-affiliate'), $current_currency) ?></p>
                        <p><?php _e('Please go to', 'solid-affiliate') ?> <strong>WooCommerce > <?php _e('Settings', 'solid-affiliate') ?> > <?php _e('Currency', 'solid-affiliate') ?></strong> <?php _e('and use a', 'solid-affiliate') ?> <a href="https://docs.solidaffiliate.com/faqs-misc/#faq-currency">Solid Affiliate <?php _e('compatible currency', 'solid-affiliate') ?></a>, <?php _e('otherwise some features such as the Pay Affiliate tool will not work properly.', 'solid-affiliate') ?></p>
                    </div>
                <?php } ?>
<?php
                echo ob_get_clean();
            }
        }

        if ($settings_group_name === 'MailChimp') {
            echo '<a class="sld-admin-card_button" href="https://docs.solidaffiliate.com/mailchimp-integration/" target="_blank">' . __('Documentation', 'solid-affiliate') . '</a>';
        }

        ////////////////////////////////////////////////////////////////////////////
        // Emails
        $send_test_email_btn_text = __('Send test email to affiliate manager(s)', 'solid-affiliate');
        if ($settings_group_name === self::GROUP_EMAIL_AFFILIATE_MANAGER_NEW_AFFILIATE) {
            echo AjaxButton::render(
                AjaxHandler::AJAX_SEND_TEST_EMAIL,
                $send_test_email_btn_text,
                ['email_key' => Email_Notifications::EMAIL_AFFILIATE_MANAGER_NEW_AFFILIATE]
            );
        }

        if ($settings_group_name === self::GROUP_EMAIL_AFFILIATE_MANAGER_NEW_REFERRAL) {
            echo AjaxButton::render(
                AjaxHandler::AJAX_SEND_TEST_EMAIL,
                $send_test_email_btn_text,
                ['email_key' => Email_Notifications::EMAIL_AFFILIATE_MANAGER_NEW_REFERRAL]
            );
        }

        if ($settings_group_name === self::GROUP_EMAIL_AFFILIATE_APPLICATION_ACCEPTED) {
            echo AjaxButton::render(
                AjaxHandler::AJAX_SEND_TEST_EMAIL,
                $send_test_email_btn_text,
                ['email_key' => Email_Notifications::EMAIL_AFFILIATE_APPLICATION_ACCEPTED]
            );
        }

        if ($settings_group_name === self::GROUP_EMAIL_AFFILIATE_NEW_REFERRAL) {
            echo AjaxButton::render(
                AjaxHandler::AJAX_SEND_TEST_EMAIL,
                $send_test_email_btn_text,
                ['email_key' => Email_Notifications::EMAIL_AFFILIATE_NEW_REFERRAL]
            );
        }
        ////////////////////////////////////////////////////////////////////////////

        if ($settings_group_name == 'Advanced') {
            _e('These are advanced settings that may have adverse effects if you are not completely familiar with the plugin.', 'solid-affiliate');
        }

        if ($settings_group_name === 'Lifetime commissions') {
            echo '<a class="setting-docs-link" href="https://docs.solidaffiliate.com/lifetime-commissions/" target="_blank">Documentation</a>';
        }
    }
}
