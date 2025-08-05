<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Addons\Core;
use SolidAffiliate\Controllers\AdminReportsController;
use SolidAffiliate\Controllers\CommissionRatesController;
use SolidAffiliate\Controllers\PayAffiliatesController;
use SolidAffiliate\Lib\License;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\AffiliateCustomerLink;
use SolidAffiliate\Models\AffiliateGroup;
use SolidAffiliate\Models\AffiliateProductRate;
use SolidAffiliate\Models\Creative;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Visit;
use SolidAffiliate\Views\Shared\AdminHeader;

/**
 * ===========================
 * ==Notes====================
 * ===========================
 * Example queries that should work:
 * - [ ] "Coupon"
 * - [ ] "Landing" / "landing pages"
 * - [ ] "creative"
 * - [ ] {name/email/id/username} of affiliate
 * - ???
 * 
 * 
 * 
 * Result Types
 * - Affiliate
 * - Setting
 * - Documentation (docs.solidaffiliate.com)
 * - In-app pages (e.g., "Affiliate", "creative", "pay affiliates", "addons" etc.)
 * - quick links
 * - ???
 * 
 * @ayman ideas
 * — [x] As you were talking about the features, I thought about search prefixes. For example, affiliate:Mike will only show affiliates; setting:Payout will show settings, etc.
 * - [ ] When using the "page:" search function, I believe it would be a great addition to include "page:page/tab," similar to how you implemented it for coupons. so for example, "page:settings/email" would take you to the email settings page.
 * — [ ] If an input of 3 words got a really high match strength (like the minimum payout example in your loom video), the other results should be disregarded.
 * - [ ] A "recent searches" functionality would be nice to have. 
 * - [ ] Theoretically, is it possible to build queries to filter links or perform specific searches for affiliates based on criteria such as status. For example, you could implement functionality where users can enter "> Find affiliates where status: pending" or "> Find unpaid referrals" to retrieve the desired results.
 * - [ ] I can see how useful it is to have a handy command palette. For example, entering commands like '> Add new affiliate' or '> referral_affiliate_#32' and having the relevant pages pop out is impressive.
 * - [ ] Another to consider are settings that aren't part of the "global" settings pages. Let's say I want to disable referrals if a coupon was used, or for a specific product etc..
 * - [ ] Affiliate info cards when "affiliate:" prefix is used

 * ===========================
 * ==End Notes================
 * ===========================
 * 
 * 
 * @psalm-type SearchResult = array{
 *  type: self::TYPE_*,
 *  url: string, 
 *  title: string,
 *  description: string,
 *  match_strength: float,
 *  result_index: int
 * }
 * 
 * @psalm-type GroupedSearchResults = array{
 *   'Setting': SearchResult[],
 *   'Affiliate': SearchResult[],
 *   'Documentation': SearchResult[],
 *   'Page': SearchResult[],
 *   'Quicklink': SearchResult[],
 * }
 * 
 */
class SolidSearch
{
    const ADMIN_PAGE_KEY = 'solid-affiliate-search';
    const DEFAULT_REQUIRED_CAPABILITY = 'read';
    const MENU_TITLE = 'Solid Search';
    const SEARCH_POST_PARAM_KEY = 'solid-affiliate-search-submit';
    const NONCE = 'solid-affiliate-search-nonce';

    const TYPE_AFFILIATE = 'Affiliate';
    const TYPE_SETTING = 'Setting';
    const TYPE_DOCUMENTATION = 'Documentation';
    const TYPE_PAGE = 'Page';
    const TYPE_QUICK_LINK = 'Quicklink';

    const MAX_RESULTS_PER_TYPE = 3;

    const QUICK_LINK_TRIGGER_QUERIES = ['affiliate', 'coupon', 'referral', 'creative', 'payout'];

    const OPTION_KEY_SEARCH_QUERY_COUNT = 'solid_affiliate_search_query_count';

    ///////////////////////////////////////////////////////////

    /**
     * @return GroupedSearchResults
     */
    public static function ff_upgrade_needed_response()
    {
        $upgrade_url = 'https://solidaffiliate.com/pricing/';

        /**
         * @var GroupedSearchResults
         */
        return [
            'Affiliate' => [
                [
                    'title' => '🔒 Unlock Affiliate Search',
                    'description' => 'Upgrade to Pro to search affiliates instantly',
                    'type' => 'Affiliate',
                    'url' => $upgrade_url,
                    'match_strength' => 1.0,
                    'result_index' => 0
                ]
            ],
            'Setting' => [
                [
                    'title' => '🔒 Quick Settings Access',
                    'description' => 'Search settings instantly with Pro',
                    'type' => 'Setting',
                    'url' => $upgrade_url,
                    'match_strength' => 1.0,
                    'result_index' => 1
                ]
            ],
            'Page' => [
                [
                    'title' => '🔒 Enhanced Page Search',
                    'description' => 'Navigate pages quickly with Pro search',
                    'type' => 'Page',
                    'url' => $upgrade_url,
                    'match_strength' => 1.0,
                    'result_index' => 2
                ]
            ],
            'Documentation' => [
                [
                    'title' => '🔒 Instant Documentation Access',
                    'description' => 'Search documentation instantly with Pro',
                    'type' => 'Documentation',
                    'url' => $upgrade_url,
                    'match_strength' => 1.0,
                    'result_index' => 3
                ]
            ]
        ];
    }


    /**
     * Handles the ajax search endpoing (AjaxHandler.php)
     *
     * @return never
     */
    public static function handle_search_ajax()
    {
        //////////////////////////////////
        // get query from POST
        //////////////////////////////////
        $query = isset($_POST['query']) ? (string)$_POST['query'] : '';
        // clean up the query
        // 1. Removes leading and trailing whitespace. 
        // 2. Replaces multiple spaces with a single space.
        $query = trim($query);
        $query = preg_replace('/\s+/', ' ', $query);

        //////////////////////////////////
        // do the actual searching
        //////////////////////////////////
        if (FF::is_off('global_search')) {
            $response = self::ff_upgrade_needed_response();
        } else {
            $response = self::search_for_query($query);
        }



        //////////////////////////////////
        // send response
        //////////////////////////////////
        wp_send_json_success([
            'syncedData' => [
                'response' => $response,
                'errors' => ['not implemented'],
                'parsedQuery' => $query,
            ]
        ]);
    }

    /**
     * @param string $query
     * 
     * @return GroupedSearchResults
     */
    public static function search_for_query($query)
    {
        $groupedSearchResults = self::_search_for_query($query);

        return self::updateResultIndices($groupedSearchResults);
    }

    /**
     * @param GroupedSearchResults $groupedSearchResults
     * @return GroupedSearchResults
     */
    public static function updateResultIndices($groupedSearchResults)
    {
        $counter = 0;

        /**
         * @psalm-suppress MixedAssignment
         */
        foreach ($groupedSearchResults as $_group => &$groupResults) {
            /**
             * @psalm-suppress MixedAssignment
             */
            foreach ($groupResults as $key => $_searchResult) {
                /**
                 * @psalm-suppress MixedArrayAccess
                 * @psalm-suppress MixedArrayOffset
                 * @psalm-suppress MixedArrayAssignment
                 */
                $groupResults[$key]['result_index'] = $counter++;
            }
        }
        unset($groupResults); // unset reference to avoid side effects

        return $groupedSearchResults;
    }


    /**
     * @param string $query
     * 
     * @return GroupedSearchResults
     */
    private static function _search_for_query($query)
    {
        self::increment_search_query_count();

        // Define the search modifiers and their respective search and sort methods
        $modifiers = [
            "affiliate:" => "search_for_affiliates",
            "setting:" => "search_for_settings",
            "page:" => "search_for_pages",
            "doc:" => "search_for_documentation"
        ];

        foreach ($modifiers as $modifier => $method) {
            if (strpos($query, $modifier) === 0) {
                // Remove modifier from query
                $stripped_query = trim(str_replace($modifier, '', $query));

                // Use variable function to call the right method
                /** @var SearchResult[] */
                $results = self::$method($stripped_query);
                $results = self::sort_and_slice_search_results($results);

                // Return only the required results, rest are empty
                return [
                    self::TYPE_QUICK_LINK => [],
                    self::TYPE_PAGE => $method === "search_for_pages" ? $results : [],
                    self::TYPE_SETTING => $method === "search_for_settings" ? $results : [],
                    self::TYPE_AFFILIATE => $method === "search_for_affiliates" ? $results : [],
                    self::TYPE_DOCUMENTATION => $method === "search_for_documentation" ? $results : [],
                ];
            }
        }

        ////////////////////////////////////////////////////////
        // If no modifier present, perform a search as usual
        ////////////////////////////////////////////////////////
        $settings_results = self::search_for_settings($query);
        $affiliate_results = self::search_for_affiliates($query);
        $documentation_results = self::search_for_documentation($query);
        $page_results = self::search_for_pages($query);
        $quick_link_results = self::search_for_quick_links($query);

        $settings_results = self::sort_and_slice_search_results($settings_results);
        $affiliate_results = self::sort_and_slice_search_results($affiliate_results);
        $documentation_results = self::sort_and_slice_search_results($documentation_results);
        $page_results = self::sort_and_slice_search_results($page_results);
        $quick_link_results = self::sort_and_slice_search_results($quick_link_results);

        return [
            self::TYPE_QUICK_LINK => $quick_link_results,
            self::TYPE_PAGE => $page_results,
            self::TYPE_SETTING => $settings_results,
            self::TYPE_AFFILIATE => $affiliate_results,
            self::TYPE_DOCUMENTATION => $documentation_results,
        ];
    }



    /**
     * @param string $query
     * 
     * @return SearchResult[]
     */
    public static function search_for_affiliates($query)
    {
        $affiliates = Affiliate::fuzzy_search($query);

        $query_words = self::split_query_into_words($query);

        $results = array_map(
            /** 
             * @param Affiliate $affiliate
             */
            function ($affiliate) use ($query_words) {
                $user = $affiliate->user();
                if (!$user) {
                    return null;
                } else {
                    $total_match_strength = 0;
                    $matched_words_count = 0;

                    // calculate match strength for each word in the query
                    foreach ($query_words as $word) {
                        $userLoginMatchStrength = substr_count(strtolower($user->user_login), $word);
                        $userEmailMatchStrength = substr_count(strtolower($user->user_email), $word);
                        $userNicenameMatchStrength = substr_count(strtolower($user->user_nicename), $word);
                        $userFirstnameMatchStrength = substr_count(strtolower($user->user_firstname), $word);
                        $userLastnameMatchStrength = substr_count(strtolower($user->user_lastname), $word);

                        $weights = [
                            'user_login' => 0.3,
                            'user_email' => 0.3,
                            'user_nicename' => 0.1,
                            'user_firstname' => 0.15,
                            'user_lastname' => 0.15
                        ];

                        $match_strength = ($weights['user_login'] * $userLoginMatchStrength + $weights['user_email'] * $userEmailMatchStrength + $weights['user_nicename'] * $userNicenameMatchStrength + $weights['user_firstname'] * $userFirstnameMatchStrength + $weights['user_lastname'] * $userLastnameMatchStrength) / ((strlen($user->user_login) * $weights['user_login'] + strlen($user->user_email) * $weights['user_email'] + strlen($user->user_nicename) * $weights['user_nicename'] + strlen($user->user_firstname) * $weights['user_firstname'] + strlen($user->user_lastname) * $weights['user_lastname']));

                        $total_match_strength += $match_strength;

                        if ($userLoginMatchStrength > 0 || $userEmailMatchStrength > 0 || $userNicenameMatchStrength > 0 || $userFirstnameMatchStrength > 0 || $userLastnameMatchStrength > 0) {
                            $matched_words_count++;
                        }
                    }

                    // Calculate weight based on the fraction of words from the query that were found
                    $query_words_present_weight = $matched_words_count / count($query_words);

                    // Multiply match strength with the weight
                    $final_match_strength = $total_match_strength * $query_words_present_weight;

                    return [
                        'type' => self::TYPE_AFFILIATE,
                        'title' => $user->user_nicename,
                        'description' => 'Affiliate #' . $affiliate->id . ' | ' . $user->user_email . ' | ' . $user->user_nicename . ' | First name ' . $user->user_firstname . ' | Last name ' . $user->user_lastname,
                        'url' => URLs::edit(Affiliate::class, $affiliate->id),
                        'match_strength' => $final_match_strength,
                        'result_index' => 0
                    ];
                }
            },
            $affiliates
        );

        // remove any nulls
        $results = array_filter($results);
        $results = self::_highlight_results($results, $query);

        return $results;
    }



    /**
     * @param string $query
     * 
     * @return SearchResult[]
     */
    public static function search_for_settings($query)
    {
        $settings_entries = Settings::schema()->entries;

        // Convert the query to an array of words
        $query_words = self::split_query_into_words($query);

        // Filter entries based on presence of any word from the query
        $matching_settings = array_filter($settings_entries, function ($entry) use ($query_words) {
            if ($entry->show_on_edit_form === false) {
                return false;
            }
            foreach ($query_words as $word) {
                if (stripos($entry->display_name, $word) !== false || stripos($entry->form_input_description, $word) !== false) {
                    return true;
                }
            }
            return false;
        });

        $results = array_map(
            /**
             * @psalm-suppress ArgumentTypeCoercion
             * 
             * @param string $setting_key
             * @param SchemaEntry $entry
             */
            function ($setting_key, $entry) use ($query_words) {
                $total_match_strength = 0;
                $matched_words_count = 0;

                // calculate match strength for each word in the query
                foreach ($query_words as $word) {
                    $displayNameMatchStrength = substr_count(strtolower($entry->display_name), $word);
                    $descriptionMatchStrength = substr_count(strtolower($entry->form_input_description), $word);

                    $weights = [
                        'display_name' => 0.8,
                        'description' => 0.2
                    ];

                    $match_strength = ($weights['display_name'] * $displayNameMatchStrength + $weights['description'] * $descriptionMatchStrength) / ((strlen($entry->display_name) * $weights['display_name'] + strlen($entry->form_input_description) * $weights['description']));

                    $total_match_strength += $match_strength;

                    // if word was found in either field, increment the matched words count
                    if ($displayNameMatchStrength > 0 || $descriptionMatchStrength > 0) {
                        $matched_words_count++;
                    }
                }

                // Calculate weight based on the fraction of words from the query that were found
                $query_words_present_weight = $matched_words_count / count($query_words);

                // Multiply match strength with the weight
                $final_match_strength = $total_match_strength * $query_words_present_weight;

                return [
                    'type' => 'Setting',
                    'url' => URLs::settings($entry->settings_tab, false, $setting_key),
                    'title' => $entry->display_name,
                    'description' => $entry->form_input_description,
                    'match_strength' => $final_match_strength,
                    'result_index' => 0
                ];
            },
            array_keys($matching_settings),
            $matching_settings
        );

        $results = self::_highlight_results($results, $query);

        return $results;
    }

    /**
     * Notes: To generate the large array of documentation, I used `python scrape-solid-docs.py` 
     * 
     * @param string $query
     * @return SearchResult[]
     */
    public static function search_for_documentation($query)
    {
        $documentation_db = [
            [
                'url' => 'https://docs.solidaffiliate.com/referrals/',
                'title' => 'Referrals',
                'description' => 'Create and manage referrals',
                'content' => 'Add a New ReferralFirst, go toSolid Affiliate > Referrals. To add a new Referral, click on the Add New button.In the Add New Referral page, fill in the following fields:Affiliate ID —Select the ID number of the Affiliate who earned the referral.Order Amount —Select the original Order Amount associated with the referral.Commission Amount —Select the commission amount earned by the Affiliate for this referral.Referral Source —Visit or Coupon. Select to determine where the referral originated.Visit ID —Enter the ID of the visit of the referral, if applicable.Coupon ID —Enter the ID of the coupon of the referral, if applicable.Customer ID —Enter the ID of the customer associated with the referral.Referral type —Purchase or Subscription renewal.Description —Purchase or Subscription renewal.Order source —Enter where the order for this referral originated. Currently WooCommerce is supported.Order ID —Enter the ID of the order associated with the referral. Currently only WooCommerce orders are supported.Created at —Select date of the referral.Payout ID —The ID of the Payout associated with this Referral, if one exists.Status  —Paid, Unpaid or Rejected. Select current status of the referral.Edit an Existing ReferralFirst, go toSolid Affiliate > Referrals. Select the referrals in the list, hover over the referral, and click on Edit.Manage referralsTo manage Referrals, go to Solid Affiliates > Referrals. Here, you can check the basic information on every referral and filter them using an Affiliate ID.Use theScreen optionsmenu located on the top right corner of your WordPress admin page to control which Referrals columns are displayed.Approve/Reject referralsAfter being recorded, the status of the referral order will be set as Pending. Merchant will then decide if the referral is valid or not. Here, there are two options for the merchant to choose from, based on the real situation of the order:Rejected —the commission will be rejected and not be sent.Approved —the commission for the order is validated for the affiliate, and ready to be sent.Commission Amount DetailsTo learn how the commission was calculated for a specific referral, click on icon next to the commission amount.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/settings/',
                'title' => 'Settings',
                'description' => 'Solid Affiliate\'s general settings',
                'content' => 'Affiliate Portal & RegistrationAffiliate RegistrationRequire Affiliate Registration ApprovalRequire approval of new Affiliate accounts before they can begin earning referrals. If turned off, Affiliates will be automatically set to Approved upon registration.Auto Register New UsersAffiliate PortalAffiliate Portal ShortcodeUse the shortcode[solid_affiliate_portal]to render the Affiliate Portal on a page of your choosing.Affiliate Portal PageSelect the page which contains the Affiliate Portal shortcode:[solid_affiliate_portal].Important note: Changing this setting will not add the shortcode to the page, you must do this yourself. This setting is simply so that Solid Affiliate can properly reference the page.Terms of Use PageSelect the page with contains your Affiliate Program Terms and Conditions. Solid Affiliate will link to this page on Affiliate Registration.Terms of Use LabelAffiliate Program Terms and Conditions labelAffiliate Portal Forms (Logged Out)Which forms should the Affiliate Portal display to logged out users.Required Affiliate Registration FieldsSelect the fields which need to be required on the Affiliate Registration Form.Note :Username, Email, and Password are always required fields.Logout LinkShow a logout link on the Affiliate Portal.IntegrationsWooCommerceIntegrations – WooCommerceAlways on. Enables the WooCommerce integration. You must have WooCommerce installed and activated.Integrations – Easy Digital DownloadsComing Soon. Enables the Easy Digital Downloads integration. You must have Easy Digital Downloads installed and activated.PayPalEnable PayPal IntegrationTurn on your PayPal Connection to easily pay your affiliates. You can find generate your API tokens in yourPayPal Developer Portal.PayPal Integration – Enable Live ModeUse the LIVE PayPal account and credentials. Otherwise, the SANDBOX credentials will be used.PayPal API Client ID – LiveSets your PayPal Client ID API Credential used to connect to your PayPal live account.PayPal API Secret – LiveSets your PayPal Secret API Credential used to connect to your PayPal live account.PayPal API Client ID – SandboxSets your PayPal Client ID API Credential used to connect to your PayPal sandbox account.PayPal API Secret – SandboxSets your PayPal Secret API Credential used to connect to your PayPal sandbox account.EmailsEmail GeneralEmail TemplateSelect an email template which all your outgoing emails will be processed through.From NameCustomize your email from name. The standard is to use your site name.From EmailSet the email address which emails will be sent from. This will set the “from” and “reply-to” address.Email NotificationsWhich events should send an automated email.Affiliate Manager EmailEnter one or more email addresses to receive Affiliate Manager notifications. Seperate multiple email addresses with a space in between.Affiliate Manager – Registration Notification EmailEmail SubjectEnter the subject line for this email.Email BodyEnter the email to send when a new affiliate registers. HTML is accepted. Available template tags:Affiliate Tags{affiliate_name}– The display name of the Affiliate, as set on the Affiliate’s user profile{affiliate_email}– The email of the Affiliate.{affiliate_payment_email}– The payment email of the Affiliate{affiliate_status}– The current status of the Affiliate{view_affiliate_url}– The URL to view and edit this affiliate within WordPress admin.Affiliate Manager – Referral Notification EmailEmail SubjectEnter the subject line for this email.Email BodyEnter the email to send when an Affiliate earns a Referral. HTML is accepted. Available template tags:Referral Tags{referral_order_amount}– The total $ amount of the referred order.{referral_commission_amount}– The amount of commission earned for this Referral.{referral_description}– Description of the referred order.{view_referral_url}– The URL to view and edit this Affiliate within WordPress admin.Affiliate Tags{affiliate_name}– The display name of the Affiliate, as set on the Affiliate’s user profile{affiliate_email}– The email of the Affiliate.{affiliate_payment_email}– The payment email of the Affiliate{affiliate_status}– The current status of the Affiliate{view_affiliate_url}– The URL to view and edit this Affiliate within WordPress admin.Affiliate – Application Accepted EmailEmail SubjectEnter the subject line for this email.Email BodyEnter the email to send to the Affiliate when their status gets updated to Approved. HTML is accepted. Available template tags:Affiliate Tags{affiliate_name}– The display name of the Affiliate, as set on the Affiliate’s user profile{affiliate_email}– The email of the Affiliate.{affiliate_payment_email}– The payment email of the Affiliate{affiliate_status}– The current status of the Affiliate{view_affiliate_url}– The URL to view and edit this Affiliate within WordPress admin.Affiliate – Referral Notification EmailEmail SubjectEnter the subject line for this email.Email BodyEnter the email to send when an Affiliate earns a Referral. HTML is accepted. Available template tags:Referral Tags{referral_order_amount}– The total $ amount of the referred order.{referral_commission_amount}– The amount of commission earned for this Referral.{referral_description}– Description of the referred order.{view_referral_url}– The URL to view and edit this Affiliate within WordPress admin.Affiliate Tags{affiliate_name}– The display name of the Affiliate, as set on the Affiliate’s user profile{affiliate_email}– The email of the Affiliate.{affiliate_payment_email}– The payment email of the Affiliate{affiliate_status}– The current status of the Affiliate{view_affiliate_url}– The URL to view and edit this Affiliate within WordPress admin.MiscMiscReject Unpaid Referrals on RefundAuto reject Unpaid Referrals when the original purchase is refunded or revoked.Disable IP Address LoggingDisable IP Address Logging of customers and visitorsRemove Data on UninstallComing Soon. Remove all saved data for Solid Affiliate when the plugin is deleted.reCAPTCHAEnable reCAPTCHAComing Soon. Add Google reCAPTCHA to all Affiliate Registration form submissions. This will help prevent bots.reCAPTCHA Site KeyComing Soon. Enter your reCAPTCHA site key.reCAPTCHA Secret KeyComing Soon. Enter your reCAPTCHA secret key.Setup WizardShow Setup WizardShow the setup wizard in the admin menu. This setting is automatically set to false once the initial setup is complete.Is Affiliate Portal Setup CompleteMark whether the Affiliate Portal Setup step is complete. This setting is automatically adjusted by the Setup Wizard, but can also be manually changed here.Recurring ReferralsRecurring ReferralsEnable Recurring RefferalsEnable Referral tracking for subscription renewal payments. The Affiliate who Referred the initial subscription payment will receive a Referral for every renewal of that subscription.Recurring Referral RateThis is the default recurring referral rate, used when calculating referral amounts for subscription purchases and renewals. When Recurring Referral Rate Type is set to ‘Percentage (%)’ this number is interpreted as a percentage. When Recurring Referral Rate Type is set to ‘Flat’ this number is interpreted as the fixed amount of whichever currency you are using. For examples, $10.00 flat.Recurring Referral Rate TypeUsed in conjunction with the Recurring Referral Rate to calculate the default referral amounts for subscription purchases and renewals.GeneralLicenseLicense KeyPlease enter and verify your active license key. This is needed for automatic updates and support.License Key StatusThe status of your license key. Can beinvalidorvalid.Referral RateReferral RateThis is the default referral rate, used when calculating referral amounts. When Referral Rate Type is set to ‘Percentage (%)’ this number is interpreted as a percentage. When Referral Rate Type is set to ‘Flat’ this number is interpreted as a float amount of whichever currency you are using.Referral Rate TypeUsed in conjunction with the Referral Rate to calculate the default referral amounts.Other ReferralReferral VariableThis is the URL parameter which will be used when generating links for Affiliates (example: www.solidwpaffiliate.com?sld=473).Note:changing this will break tracking on all previously created links.Credit Last AffiliateCurrently always enabled. Simply put, if multiple Affiliates send you the same person then the last Affiliate will receive credit for any purchases. Other attribution strategies are coming soon.Exclude ShippingWhen calculating referral amount, exclude shipping costs. (Will result in lower commission payments on items with shipping costs).Exclude TaxWhen calculating referral amount, exclude taxes. (Will result in lower commission payments on taxed items).New Customer CommissionsThis setting ensures that affiliates are only awarded commissions for referringnewcustomers (those making their first-ever purchase from your online store).Cookie Expiration DaysExpire the referral tracking cookie after this many days.Referral Grace Period DaysSet the Referrel grace period number of days. This is used by the Pay Affiliates feature to give the convenient option of paying all Referrals which are older than the grace period. Recommended: set this equal to your store refund policy, to minimize the chances of paying an Affiliate for a Referral while you are still liable to issue a refund for the underlying purchase.CurrencyCurrencyThe currency code to export for Referral payouts. The pay affiliates tool uses this, and the PayPal Payouts integration is supported. Support for additional currencies coming soon.Currency Symbol PositionSelect the currency symbol postion, before or after the amount.Currency Decimal SeperatorCustomize the symbol used to seperate thousands. It is commonly set to , or .Currency Decimal SeperatorCustomize the symbol used to seperate decimals. It is commonly set to , or .',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/affiliate-registration-form/',
                'title' => 'Affiliate Registration Form',
                'description' => 'Customize your Affiliate Registration Form',
                'content' => 'Customize your Affiliate Registration FormSolid Affiliate comes with a default affiliate registration form, but we know that our customers want to customize that form to fit their needs.Quick Guide – Video TutorialInSettings>CustomizeRegistrationFormyou will find a form builder that allows you to drag, drop, and configure fields to be included on your affiliate registration form.If you use caching on your site, you may have to clear the cache through your caching plugin or hosting provider before the changes to your affiliate registration form are reflected.How it WorksRequired FieldsThere arefour required fieldsthat Solid Affiliate always expects to exist on the affiliate registration form. These fields areUsername,Account Email,Password, andAccept Policy. Due to how Solid Affiliate uses native WordPress user management, these fields need to be on the form. These fields are marked by* Requiredand a 🔒. You will not be able to edit or remove these fields, but you can reorder them on the form.Pre-Built FieldsThere are also “pre-built” Solid Affiliate fields such asFirst Name,Last Name,Payment Email, andRegistration Notes. These fields exist natively on your Affiliates and are not “custom”, but you can configure them. The only properties you cannot change on them are their name and type. These fields are marked byand appear as field inputs in the form builder. You cannot duplicate these fields, but you can remove them.Text FieldsEach field type you can drag into your form is a specific form field type. However, Text Fields allow for subtypes. These subtypes are text, email, password, and URL, and can be configured by editing the field once it has been dropped into the form.Checkbox FieldsYou can create both single checkbox fields and multi-checkbox fields using the Checkbox Group field type.If you want the form field to be a single checkbox (like the required “Accept Policy” field), then you do not need to add a label or value to the checkbox option as it will be ignored. Single checkbox fields will only display the field level label.Multi-checkbox fields are Checkbox Group fields that have more than one option, and they will require a label and value for each option.Options for Select Drop-Downs, Checkboxes, and Radio ButtonsSelect, Multi-Checkbox, and Radio Group fields all require options.These options cannot be blank and cannot be duplicates of each other. The Options pair represents the label (on the left), and the value (on the right).Default FieldsIf you want to start over while you are building your form, you can press theReset form to defaultbutton and the form will revert to only the four required fields and the four “pre-built” fields.Affiliates can EditThe “Affiliates can Edit” configuration determines whether or not your affiliates can update the value of this field when they are logged into the affiliate portal.Validations and RulesWe want you to customize your affiliate registration form however you like, but there are some limitations (intentionally imposed to guard against bad data). When updating your custom form in the settings, you may see an error that starts with: “An invalid value for the custom registration form configuration was given…” There are various reasons why this could have happened.Names and Labels are Required– All fields must have a name and label, as this is how the plugin will keep track of the custom fields.Names and Labels are Unique– The value for a field’s name and label must be unique, otherwise the plugin will not know how to identify the field.Option Values and Labels– As mentioned above, all values and labels for the options of Select Drop-Down, Multi-Checkbox, or Radio Group fields cannot be empty, and must be unique.Reserved Names– Certain names are reserved by the Affiliate database table and cannot also be custom fields.Changing the Type of a Field– You cannot change the type of a form field. Let’s say you have a number field named “bank_account_number”, and then you want to change it to a text field. You cannot do so because data from the original custom field will be stored as numbers on your Affiliates. If you try to create a new field with the name “bank_account_number” as type text, it will not update your form. The plugin is built withstrict type safetyand to ensure bad data does not break your site, we do not allow changing the types of existing form fields.Malformed Data– It is very unlikely, but possible, that the data that represents your custom form cannot be read properly from your database. Solid Affiliate guards against this, but it is theoretically possible some combination of special characters (something like this:+`?:}}<“|>}?:{:=”\”‘\?{:?{:>{}{)&%’\’\/’\/*) as the value for your label, description, etc. could break your custom form.Accessing your Custom DataEvery custom field you add to the affiliate registration form will be included on the admin Affiliate new and edit pages, the Affiliate list table, the Affiliate data export to CSV, and the Pay Affiliates CSV. In essence, when you customize your affiliate registration form you are also customizing what data you can store and view on your Affiliates.Changing your Custom FieldsIf you change your custom affiliate registration form, your affiliates will retain their custom data from the old form, but only fields that are currently set in the custom form will be displayed and exported by the plugin. If you decide to add back a custom field that you removed earlier, and you give it the same name, then that field will again display and export data. This behavior is similar to the mature WordPress plugin Advanced Custom Fields.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/custom-affiliate-slugs/',
                'title' => 'Custom Affiliate Slugs',
                'description' => 'Personalized Custom Slugs for Affiliate Links',
                'content' => 'Solid Affiliate allows you to attribute Visits and Referrals to Affiliates in many different ways. Custom Affiliate Slugs is a core feature that enables you toassign several personalized URL slugs to an Affiliateto use in their Affiliate Links, orallow your Affiliates to create their own custom URL slugs. Custom slugs can be personal, specific to the affiliate’s business, specific to a marketing campaign or product, or something fun you or the Affiliate has thought of.OverviewBy default, a new Affiliate can use their Affiliate ID in an Affiliate Link:www.solidaffiliate.com/?sld=1. With a custom slug, that URL slug can be replaced with whatever text (with some constraints,see below) makes the most sense for sharing the link:www.solidaffiliate.com/?sld=verycoolcampaign. Using custom slugs will not remove the ability to use one’s Affiliate ID in an Affiliate Link. It is simply an additional way to send track traffic on your site.Creating and deleting custom slugs is easy and can be done on an Affiliate’s edit page. Within the “Active Custom Slugs” section, there is a “Add a new Custom Slug” form where you can create new custom slugs for the specific Affiliate. Once a slug is active, the plugin will display the number of Visits and Referrals that originated from traffic sent by links using that specific custom slug.If you want to delete a custom slug, simply click the Delete button in the Actions column of the custom slug table and confirm that you want to delete that custom slug.Deleting a custom slug will not delete any other related data—all Visits, Referrals, and Commissions will continue to exist in your database. If you want to add back a custom slug that used to be active, you can use the “Add a new Custom Slug” form, and the Visit and Referral data will be reported as if the custom slug was never deleted.Not only will the custom slug section be on each Affiliate edit page, it will also be in each Affiliate Portal for your Affiliates to track their success using custom slugs.In the Affiliate Links tab, your Affiliates will find their Default Affiliate Link provided by the site admin, a URL generator to create Affiliate Links anywhere on your site, and the Active Custom Slugs section. From here, Affiliates can view their custom slug metrics and create and delete custom slugs if the site admin has configured Solid Affiliate to grant permission to Affiliates to create and delete their custom slugs.SettingsSolid Affiliate allows you to configure how custom slugs are used and tracked, as well as how you display Affiliate Links to your Affiliates. In General Settings, there is a URL Tracking section where you can configure custom slug permissions, limits, and auto-creation.Allow Affiliates to Create and Delete SlugsThis global setting is turned on by default and allows all your Affiliates to create and delete custom slugs from their Affiliate Portal.If you want to only grant certain Affiliates permission to create and delete, then you can turn this global setting off and grant permission on a per-affiliate basis on the specific Affiliate’s edit page.If an Affiliate does not have permission to create and delete slugs, they will see a read-only version of their Active Custom Slugs section in the Affiliate Portal.Per Affiliate Custom Slug LimitDepending on your needs you may want to limit how many custom slugs a single Affiliate can own. This limit is only configurable via a global setting and defaults to 10. In each custom slugs section, there will be an indicator of how many custom slugs compared to the limit are being used by the Affiliate.We know that affiliate programs change, so if an Affiliate ever owns more than the per-affiliate slug limit, Solid Affiliate will not automatically delete any of your Affiliate’s custom slugs as this could break existing Affiliate Links. This may occur if a site admin changes the per-affiliate custom slug limit to a number that is less than an affiliate already has. In this situation, no new custom slugs can be created for the Affiliate. If any new custom slugs are to be added, then either the site admin or the affiliate will need to delete custom slugs until the affiliate is under the limit, or the per-affiliate limit will need to be increased.Auto Create Affiliate Username SlugBy default, this setting is turned on, and it will auto-create a default custom slug for a new and approved Affiliate based on their WordPress username.Once a new Affiliate is approved, the default custom slug will be created and displayed to the Affiliate in their portal for immediate use. For safe use in URLs, Solid Affiliate will only use English alphanumeric characters from the Affiliate’s username when creating the custom slug. If there is a conflict with another Affiliate’s custom slug, then one or more random numbers will be added to the username to create a unique custom slug.Custom Slug ConstraintsTo guarantee that custom slugs work in URLs and with Solid Affiliate visit tracking, there are certain constraints on what you can make a custom slug.A custom slug cannot be empty. There must be at least one character for Solid Affiliate to use when associating the slug to an Affiliate.A custom slug cannot be more than 40 characters. Slugs that are too long may affect browser URL limits.A custom slug cannot be only numbers. If a custom slug is only numbers then it would be treated as an Affiliate ID that may not be the correct Affiliate.A custom slug can only contain English alphanumeric characters limiting you to use digits 0-9 and the English alphabet. This is to ensure URLs do not break.Custom slugs must be unique across your affiliate program, otherwise Solid Affiliate would not know which Affiliate to attribute traffic to.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/multisite/',
                'title' => 'WordPress Multisite',
                'description' => 'Learn how does Solid Affiliate work with WordPress Multisite installation',
                'content' => 'WordPress Multisite is a feature that allows you to create a “network” of subsites within a single instance of WordPress. This network shares a file system, database, and are typically variations of the same domain. Simply put, WordPress Multisite Compatibility is a native WordPress feature that lets you manage multiple WordPress websites using a single WordPress installation.Does Solid Affiliate work on Multisite?Yes!Solid Affiliate will work on any WordPress Multisite installation.Every site will be running a completely separate instance of Solid Affiliate– the data from every site in your network is not shared with other sites. We ensure complete data isolation by creating dedicated database tables for each site in your network, following the WordPress convention of prefixing each database with the current site’s blog_id. Additionally, the cookies used for referral tracking are unique for each site in your network.Keep in mind:Think of every site as having a completely isolated Affiliate Program. All the Affiliates, Referrals, Visits, Cookies, etc are isolated from one another.You’ll need to activate the license key for each site separately.Example of Solid Affiliate pluginon a multisite environment.Automatic plugin updatesYou have to accept the automatic updates via the main site when you work with a multisite installation.Under My sites, go to your Network Admin.Go to Plugins, enable Solid Affiliate automatic updates.Verify if the plugin is active on network level.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/affiliate-landing-pages/',
                'title' => 'Affiliate Landing Pages',
                'description' => 'Assign Affiliates their own Landing Pages',
                'content' => 'The Affiliate Landing Pages addon allows you topublish personalized landing pagesfor your affiliates, which they canpromotewithout using an affiliate link.OverviewWith Affiliate Landing Pages, you can publish personalized landing pages for each affiliate. A landing page can either be a Page or Post, and all you have to do is assign an affiliate to the page using the Solid Affiliate affiliates select dropdown.This addon is enabled by default and can be activated and deactivated on the Addons page. There are no settings for this addon. Instead, the customization will be in how you personalize each Landing page to optimize the ability of your affiliates to promote your business.How it WorksOnce you have assigned an affiliate to a published (if a page is of status trashed or draft the addon will not track visits or referrals) landing, any traffic to that page will be attributed to that affiliate as Visits. And any order that results from those visits will be attributed to that affiliate as a Referral.Solid Affiliate will display relevant data about how your affiliate’s landing pages are doing through the plugin. This is useful when reviewing how effectively your affiliates and their landing pages increase traffic to your site and make your business money. You can view the landing pages you have assigned to an Affiliate when on that affiliates page, and you can view the landing pages for your entire affiliate program on the Commission Rates page.This allows you to see how your affiliate landing pages are doing at a glance, and quickly navigate to view or edit each page. Also, when you are viewing a single affiliate, you can start a new affiliate landing page by clicking the “Create New Affiliate Landing Page” button, which will open a new draft page with that affiliate preloaded as the assigned affiliate.Your affiliates will also be able to quickly see how their landing pages are doing when they are logged into their affiliate portal. The addon will add a new tab to the affiliate portal, to show affiliates what published pages they can promote and how many visits and referrals each page URL has.Visit TrackingThe addon will not affect how Solid Affiliate’s normal tracking works, and all existing functionality still works as expected with Affiliate Landing Pages enabled.Affiliate Landing Pages use the page URL to link visits to landing pages. Any traffic to your page’s permalink or page link will be attributed as a visit to that landing page. If you change the permalink or page link of a landing page, the addon will attribute those visits to the new page URL. If you remove a page, update it to trashed or draft, or assign a different affiilate to a landing page (thus removing the old affiliate from it), the addon will stop tracking and attributing tracking for that page to that affiliate. The visits and referrals attributed to an affiliate, through a landing page, will still be stored by Solid Affiliate, but they won’t be shown in the Affiliate Landing Pages widgets because they are no longer active/published for that affiliate.If you republish or assign an inactive page to its old affiliate, all of the tracking data for that affiliate and landing page will again be displayed throughout the plugin.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/pay-affiliates/',
                'title' => 'Pay Affiliates',
                'description' => 'Pay your affiliates their earnings',
                'content' => 'Solid Affiliate makes it easy to pay your Affiliates. You get to this tool by clickingSolid Affiliate -> Pay Affiliateswithin your WordPress admin.We recommend watching the following video for an overview of how the Pay Affiliates tool works.How to use the Pay Affiliate toolStep 1)You filter down which Referrals you’re going to pay commissions for. You have three options:Referrals that are older than your store’s refund policy. RecommendedAll Referrals.Custom date range. Including presets such asThis Quarter,This Month,This week, etc.Step 2)You can either select a Manual payout, which would export the data into a spreadsheet for you. Or you can use ourPayPal Bulk Payout integration.If you choose to do a Manual payout and download the CSV, all of the data on an Affiliate will be included in the export. This includes any custom fields from yourCustom Registration Form (e.g., bank account fields), plus a column representing the total amount to be paid to an Affiliate (“Amount”) and a column representing the currency to be paid out in (“Currency”).Step 3)Preview and confirm the payment.Seeing past bulk payoutsYou can see a log of all your bulk payouts by clickingSolid Affiliate -> Pay Affiliates -> Past Bulk Payouts tabwithin your WordPress admin.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/visits/',
                'title' => 'Visits',
                'description' => 'Track and manage your referrals visits',
                'content' => 'Manage visitsSolid Affiliate uses cookies to track affiliates and visits so referrals can be generated. To preview any visit entry, go toSolid Affiliates > Visits. Here, you can view detailed information about each visit.Use theScreen optionsmenu located on the top right corner of your WordPress admin page to control which Creative columns are displayed.How does Solid Affiliate track visits?Solid Affiliate tracks unique visits. When an affiliate’s link is first used to reach your website where Solid Affiliate is installed, a cookie will be created in the visitor’s browser. As long as the cookie remains in that browser and is active (that is based on your Cookie Expiration setting in SolidAffiliate > Settings > General), no additional visit entries will be created inSolid Affiliates > Visits.This doesn’t mean your affiliate will miss out on earning referrals, since any purchases or subscriptions made from that browser while the cookie is active will result in a referral created for the affiliate.One other setting that can affect when a visit is created is Credit Last Affiliate, also inSolid Affiliate > Settings > General. This will create visits for unique, distinct affiliate links. So if affiliate 1’s link is used, then the same visitor clicks on affiliate 2’s link, another visit will be created for affiliate 2. Basically the last affiliate link used wins.For configuration regarding cookie expiration days and credit last affiliate, please visit theGeneral settingsarticle.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/hooks-developers/',
                'title' => 'Hooks – Developers docs',
                'description' => 'Developer Hooks Reference',
                'content' => 'Note: These are work in progress docs. They are not available in the current version.Hooks ReferenceAll hooks are prefixed with"solid_affiliate/, for exampledo_action("solid_affiliate/Affiliate/new_registration/success", $affiliate_id, $user_id)',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/email-templates/',
                'title' => 'Email Templates',
                'description' => 'Manage email notifications',
                'content' => 'Solid Affiliate supports 4 email notifications :Affiliate registration notification.An email is sent to the affiliate manager when new Affiliate has registered.Referral creation notification.Affiliate Manager gets an email when a new Referral has been created.Earned referral notification.Affiliate gets an email when a new Referral has been earned by them.Approved affiliate application notification.We’ve built the email templates to support endless design opportunities. If you have intermediate HTML knowledge, you’ll be able to customize the templates using the given tags to match your linking. Below are a few variations of the default template that you can use.To use one of the templates, copy and paste the HTML box, and edit the appropriate field withinSolid Affiliate > Settings > Emails.Disable referral notification emails for a specific affiliateBy default, all affiliates should receive their referral notification emails. However, you have the option to disable referral notification emails for a specific affiliate from their “Edit affiliate” page under Misc. Settings.Quick Guide – Customize your email notificationsReady to use email templatesEmail Templates 1 – Default StyleEmail PreviewThe default template that ships with Solid Affiliate. All affiliate and referral tags are included in the email notifications.Affiliate Manager — Registration Notification Email SettingsNotification —New affiliate registration{affiliate_name} has signed up as an affiliate on your site.Full name{affiliate_name}Email{affiliate_email}Payment email{affiliate_payment_email}Status{affiliate_status}View affiliateAffiliate Manager — Referral Notification Email SettingsNotification —New ReferralAn Affiliate has just earned a new Referral.Referral amount{referral_order_amount}Commission amount{referral_commission_amount}Referral status{referral_status}Referral date{referral_date}Referral source{referral_source}Referral description{referral_description}Referral URL{view_referral_url}View affiliateAffiliate — Application Accepted Email SettingsApplication approved —Welcome to our affiliate programWelcome aboard!Your application to sign up as an Affiliate for our company has been accepted!Affiliate name{affiliate_name}Email{affiliate_email}Payment email{affiliate_payment_email}Affiliate status{affiliate_status}Affiliate — Referral Notification Email SettingsSolid Affiliate —New Referral EarnedYou\'ve earned a new referral!Referral amount{referral_order_amount}Commission amount{referral_commission_amount}Referral description{referral_description}Email Templates 2 – Serif StylePreviewAlternative to the default template with serif typography. All affiliate and referral tags are included in the email notifications.Affiliate Manager — Registration Notification Email SettingsNotification —New affiliate registration{affiliate_name} has signed up as an affiliate on your site.Full name{affiliate_name}Email{affiliate_email}Payment email{affiliate_payment_email}Status{affiliate_status}View affiliateAffiliate Manager — Referral Notification Email SettingsNotification —New ReferralAn Affiliate has just earned a new Referral.Referral amount{referral_order_amount}Commission amount{referral_commission_amount}Referral status{referral_status}Referral date{referral_date}Referral source{referral_source}Referral description{referral_description}Referral URL{view_referral_url}View affiliateAffiliate — Application Accepted Email SettingsApplication approved —Welcome to our affiliate programWelcome aboard!Your application to sign up as an Affiliate for our company has been accepted!Affiliate name{affiliate_name}Email{affiliate_email}Payment email{affiliate_payment_email}Affiliate status{affiliate_status}Affiliate — Referral Notification Email SettingsSolid Affiliate —New Referral EarnedYou\'ve earned a new referral!Referral amount{referral_order_amount}Commission amount{referral_commission_amount}Referral description{referral_description}Email Templates 3 – Dark StylePreviewA dark version of the default template. All affiliate and referral tags are included in the email notifications.Affiliate Manager — Registration Notification Email SettingsNotification —New affiliate registration{affiliate_name} has signed up as an affiliate on your site.Full name{affiliate_name}Email{affiliate_email}Payment email{affiliate_payment_email}Status{affiliate_status}View affiliateAffiliate Manager — Referral Notification Email SettingsNotification —New ReferralAn Affiliate has just earned a new Referral.Referral amount{referral_order_amount}Commission amount{referral_commission_amount}Referral status{referral_status}Referral date{referral_date}Referral source{referral_source}Referral description{referral_description}Referral URL{view_referral_url}View affiliateAffiliate — Application Accepted Email SettingsApplication approved —Welcome to our affiliate programWelcome aboard!Your application to sign up as an Affiliate for our company has been accepted!Affiliate name{affiliate_name}Email{affiliate_email}Payment email{affiliate_payment_email}Affiliate status{affiliate_status}Affiliate — Referral Notification Email SettingsSolid Affiliate —New Referral EarnedYou\'ve earned a new referral!Referral amount{referral_order_amount}Commission amount{referral_commission_amount}Referral description{referral_description}Email Templates 4 – Plain StylePreviewA plain-text template. Minimal design that matches the default HTML styling. All affiliate and referral tags are included in the email notifications.Affiliate Manager — Registration Notification Email SettingsYou have a new affiliate registration{affiliate_name} has signed up as an affiliate on your site.Full name : {affiliate_name}Email : {affiliate_email}Payment email : {affiliate_payment_email}Status : {affiliate_status}To view/edit this affiliate within WordPress admin.{view_affiliate_url}Affiliate Manager — Referral Notification Email SettingsNew Affiliate Referral!An Affiliate has just earned a new Referral.Referral amount : {referral_order_amount}Commission amount : {referral_commission_amount}Referral status : {referral_status}Referral date : {referral_date}Referral source : {referral_source}Referral description : {referral_description}Referral URL : {view_referral_url}To view/edit this affiliate within WordPress admin.{view_affiliate_url}Affiliate — Application Accepted Email SettingsWelcome aboard!Your application to sign up as an Affiliate for our company has been accepted!Affiliate name : {affiliate_name}Email : {affiliate_email}Payment email : {affiliate_payment_email}Affiliate status : {affiliate_status}Affiliate — Referral Notification Email SettingsNew Referral EarnedYou\'ve earned a new referralReferral amount : {referral_order_amount}Commission amount : {referral_commission_amount}Referral description : {referral_description}Build your own email templatesAffiliate tagsA collection of affiliate tags you can use to build or customize affiliate notification emails. The tags currently available are :TagDescription{affiliate_name}The display name of the affiliate, as set on the affiliate’s user profile.{affiliate_email}The email of the affiliate.{affiliate_payment_email}The payment email of the affiliate.{affiliate_status}The current status of the affiliate.{view_affiliate_url}The URL to view and edit this affiliate within WordPress admin.Referral tagsA collection of referral tags you can use to build or customize emails sent to your affiliates. The tags currently available are :TagDescription{referral_order_amount}The total amount of the referred order.{referral_commission_amount}The amount of commission earned for this Referral.{referral_description}Description of the referred order.{referral_status}Status of the referral record.{referral_date}Date of the referral record.{referral_source}Source of the referral record.{view_referral_url}The URL to view and edit this Affiliate within WordPress admin.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/exclude-payment-gateway-referrals/',
                'title' => 'Exclude payment gateway referrals',
                'description' => 'Exclude customer selected payment gateway from generating referrals.',
                'content' => 'The need to exclude payment gateways from generating referrals can depend on multiple factors : higher fees of certain gateways, processing time, etc.Using this addon, you can select WooCommerce Payment Gateways which Solid Affiliate should ignore when capturing referrals. Orders completed through these gateways will never result in a referral.Enable this addonAll Solid Affiliate addons can be enabled/disabled inSolid Affiliate > Addonspage.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/data-export/',
                'title' => 'Data Export',
                'description' => 'Export your Solid Affiliate Data',
                'content' => 'This addon adds a Data Export tool that allows you to export your Solid Affiliate data. All your core resources, including Affiliates, Referrals, Visits, Payouts, and Creatives will download to a CSV.OverviewThere are no settings for this addon and it is enabled by default. You can find the tool in the Solid Affiliate menu as “Data Export”. The tool will show you how many records of each resource and what columns will be downloaded.In the future, we expect that export functionality will expand to allow filtering, more computed columns, etc.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/affiliate-product-rates/',
                'title' => 'Affiliate Product Rates',
                'description' => 'Product-Affiliate Commissions and Auto-Referrals',
                'content' => 'OverviewAffiliate Product Rates allow you to set commission rates specific to an Affiliate-Product pairing. This commission rate will take priority over all commission rates except for global recurring rates if you are selling subscription products.Simply select a product and Affiliate to pair and add a commission rate and type to create a new Affiliate Product Rate that will lock in referrals for this product to that Affiliate at the commission rate, overriding the commission rates you may have set for this Affiliate. You can only create a single Affiliate Product Rate per Affiliate-Product pairing, but you can make as many as you like for a single product using different Affiliates. You can view a list of all of your rates on the list page.Auto ReferralsProducts Auto-Referrals allow you to link a Product and an Affiliate without requiring the use of Affiliate Links. This is useful when you want to reward an Affiliate for promoting and selling a specific product regardless of how the customers were referred to your site, such as a revenue split agreement. To create an auto-referral, check the Enable Auto Referral checkbox when creating a new Affiliate Product Rate.When viewing your rates on the list page, Auto Referral enabled rates will be marked the Enabled status. You can learn more about how this functionality works and how to test it by viewing ourTesting the Auto-Referral Featuredocumentation.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/emails-not-sending/',
                'title' => 'Emails not being sent',
                'description' => 'Learn how to troubleshoot notification emails within Solid Affiliate.',
                'content' => 'Note: If your Solid Affiliate email notifications are not being delivered it isalmost certainthat the problem is not with Solid Affiliate. Thousands of businesses use Solid Affiliate with no problem sending email notifications. Thequick fixis to install an SMTP plugin such ashttps://wordpress.org/plugins/wp-mail-smtp/Solid Affiliate sends email notifications to Affiliates and your Affiliate Managers whenever an important event occurs.If you’re experiencing issues with the emails not sending, do not be alarmed it’s most likely a quick fix.In this guide, we’ll show you how to set up and troubleshoot notification emails within Solid Affiliate.Step 1: Ensure WordPress is sending emails.First, ensure that WordPress is properly sending emails. The easiest way to quickly check ifanyemails are sent is to go through the “Lost your password?” flow from your site’s login screen.Going through this flow will trigger your WordPress site to send an email to you. If you find thatnoneof your emails are working, we’d recommend reaching out to your web host to find out why. If emails are being delivered by WordPress, please continue to the next step.Step 2: Check your spam folders.We recommend that you check your spam folder in case the emails have been sent there by mistake. If your emails are being sent to spam, we’d recommend running a freeemail deliverability test. If you can’t find the emails in the spam or junk folders, please continue to the next step.Step 3: Ensure Solid Affiliate email notifications are enabled.You can enable or disable specific email notifications within Solid Affiliate settings.Go toSolidAffiliate → Settings → Emails.Ensure that all the fields are filled out properly.Enable all or some of theEmail Notifications.Step 4: Check email logs.Use a free WordPress plugin to log outgoing emails:https://wordpress.org/plugins/check-email/. If the emails are showing up in the email logs but not reaching your inbox, you likely have a deliverability issue. We’d recommend a specific guide onWordPress not sending emails.If you don’t see Solid Affiliate attempting to enqueue an email in your logs, and you’re confident that everything else in your site is configured correctly – pleasecontact our support team.Step 5: Install an SMTP plugin.This will instantly fix your issue in almost every case. Try using any highly rated, free SMTP plugin from this list:https://wordpress.org/plugins/search/SMTP/If there is still an issue, kindly contact your hosting provider. Solid Affiliate simply uses the core WordPress mailing functionality (via wp_mail, the same function that nearly every popular plugin uses), so it’s possible that your hosting provider is blocking outgoing emails.If you want to test that everything else within Solid Affiliate is working, refer to this guide at any time:Solid Affiliate – How to test that everything is working properly.Additional ResourcesFree Check Email Pluginby WPChill.Email Deliverability Testby MailGenius.How to Fix the WordPress Not Sending Emailsby Kinsta.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/installation/',
                'title' => 'Installation',
                'description' => 'Learn how to install Solid Affiliate on your WordPress site.',
                'content' => 'If you are running a WordPress Multisite Network, please refer to ourWordPress multisite compatibility article.Upload and activate Solid AffiliateDownload the Solid Affiliate .zip fileDownload Solid Affiliate by using the download links you received upon purchase. If you don’t have it handy, check your email for a purchase confirmation receipt.Upload the pluginNavigate to yourWordPress Admin -> Plugins -> AddNew page.Clickupload pluginand thenchoose file.Select the recently downloaded Solid Affiliate .zip file.Once uploaded, clickinstall now.Activate the pluginOnce installation completes, clickactivate plugin.Complete the Setup WizardSolid Affiliate comes with a Setup Wizard to help with setup. Here’s a video that goes over the wizard, as well as some written notes below.Step 1:Install WooCommerce.Step 2:Install WooCommerce Subscriptions (optional).Step 3:Choose your outgoing email address and name.Step 4:Enter your license key.You can access your license key bylogging into our customer portal. It was also emailed to you upon purchase.Step 5:One-click Affiliate Portal setup.The one-click Affiliate Portal setup will create a new page on your site and automatically embed the Affiliate Portal into this new page. You can optionally skip this step and then manually add the Affiliate Portal to any page by using the[solid_affiliate_portal]shortcode.Next StepsCompleting the Setup Wizard – outlined above – gets your affiliate program up and running. At this point, there are no morerequiredsteps. However, we recommend configuring a few more things to make your affiliate program run the way you need it to. You can continue by heading over to theconfiguration documentation.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/does-solid-affiliate-work-with-caching/',
                'title' => 'Does Solid Affiliate work with caching?',
                'description' => 'Learn how to set up any WordPress caching plugin for Solid Affiliate.',
                'content' => 'Yes, Solid Affiliate works with caching right out of the box. It has been tested with majorWordPress caching pluginsand popular hosting providers with built-in caching.Solid Affiliate includes redundant referral tracking technologies to ensure an accurate and consistent visit, referral, and commission tracking system.As a WooCommerce store owner, you’re likely using a caching solution to keep your site running quickly. In most cases, Solid Affiliate will work with caching plugin with no specific configuration changes needed by you. However, If you’ve encountered issues with referral/visits tracking, or affiliates having trouble logging in, it may be necessary to make a few changes to your caching plugin settings. They’re quick and easy, just follow the simple steps below!In this guide, we’ll show you how to set up any WordPress caching plugin for Solid Affiliate, what to do if things go wrong, and how to test if affiliate tracking is working on your store.Have you run into any of these?Visits and referrals are not tracked.Referrals are being credited to the wrong affiliate.“Are you cheating?”, “Cheatin’ Uh?” errors.Affiliates having trouble signing in.These issues are most often due to caching interaction. You can quickly fix them by tweaking your caching plugins.Setting up Solid Affiliate cachingIf you’re using a caching plugin  you need to do the following:Step 1: Exclude Solid Affiliate Pages From Your Caching PluginExcluding pages is reasonably straightforward in most WordPress caching plugins, simply go to your caching plugin settings, look for the exclude option, and set the following pages to be excluded:Affiliate portal pageAffiliate registration page (if separate from the affiliate portal page)Affiliate login page (if separate from the Affiliate portal)Any pages usingSolid Affiliate shortcodesThe pages are the main dynamic pages offered by Solid Affiliate, where the content changes based on who is looking at the page.Step 2: Exclude Solid Affiliate Cookie From CachingSolid Affiliate plugin creates a single tracking cookie that should be excluded from caching :solid_visit_idNote for WordPress Multisite installations:Solid Affiliate creates separate cookies for every additional site in your network, to completely isolate the affiliate program data. The cookie will be in the formatsolid_visit_id-$blog_idfor examplesolid_visit_id-2. You can read more here:Solid Affiliate on WordPress Multisite.If the cookie is being cached, this could result in:Disabling visits and referrals tracking.Crediting the referral to the wrong affiliate.Affiliates unable to log in.We highly recommend excluding Solid Affiliate tracking cookies from caching.The process to exclude the above cookie and Solid Affiliate pages vary depending on which caching plugin you use.Please refer to the documentation for your specific caching plugin for instructions on excluding URLs and cookies –it’s a common process, and most plugins make it easy for you.Below, you’ll find guides to exclude Solid Affiliate cookies and URLs in popular WordPress caching plugins.Using Popular Caching plugins with Solid AffiliateWP RocketIt is very simple to exclude a page or pages from the cache.Go to WP RocketAdvanced Rulestab.Locate the boxNever cache (URLs).Enter the URLs of the Solid Affiliate pages. You can either enter the full URL e.g.http://www.example.com/affiliate-portal/or you can just enter the part after the domain name, e.g./affiliate-portal/. Either way will work.If you are using separate pages for registration and affiliate login, enter each one on its own line.Save settings.Note:Whether or not you include a trailing slash at the end of the URL depends on how your site is set up. You can go toSettings → Permalinksand look at the structure you are using. If it ends with a slash (/), such as/%postname%/(which is the most typical), you must include it when adding URLs to this box.To prevent cached pages from being served once the Solid Affiliate cookie is set in the browser. Follow these steps:Go to WP RocketAdvanced Rulestab.Locate the boxNever cache Cookies.Enter the following cookie idsolid_visit_id.Save settings.WP Fastest CacheTo exclude Solid Affiliate cookies and URLs in WP Fastest Cache:Go to WP Fastest CacheExcludetab.Add a new rule to exclude pages with the slugs of the Solid Affiliate pages.Add a new rule to exclude cookies, and entersolid_visit_id.Save settings.Purge all caches.W3 Total CacheTo exclude Solid Affiliate cookies and URLs in W3 Total Cache:Navigate toDashboard → Performance → Page Cache.In the “Never cache the following pages” field, add the slugs for Solid Affiliate pages.In the “Rejected cookies” field and addsolid_visit_id.Save settings.Purge all caches.LiteSpeed pageTo exclude Solid Affiliate cookies and URLs in LiteSpeed Cache:Navigate toDashboard → LiteSpeed Cache → Cache → Excludes tab.In the “Do Not Cache URIs” field, add the slugs for the Solid Affiliate pages.In the “Do Not Cache Cookies” field,  addsolid_visit_id.Save settings.Purge all caches.If you want to test that everything is working, refer to this guide at any time:Solid Affiliate – How to test that everything is working properly.If you’ve tried all of these things and you’re still having issues, pleasecontact our support team.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/woocommerce/',
                'title' => 'WooCommerce',
                'description' => 'Integrate with Solid Affiliate with WooCommerce',
                'content' => 'Installation and OverviewThe first thing to note is that the WooCommerce integration is built right into Solid Affiliate.You won’t need to purchase or install any additional add-ons to get it working. The plugin will detect WooCommerce and start working automatically, generating referrals for any purchases which were referred over via an affiliate link.Any referrals which came through WooCommerce will have a link to the corresponding order and aWooCommercelabel in the Order ID column. Solid Affiliate will also add order notes within the WooCommerce integration, keeping track of relevant events.ConfigurationYou can and should configure the WooCommerce Integration. All relevant settings can be found in the dedicated pageSolid Affiliate -> Commission Rates.Use the Commission Rates page to set exactly how commissions will be calculated for referred WooCommerce orders.Coupon TrackingSolid Affiliate comes with native support for WooCommerce coupons. You can link any coupon to an affiliate, granting that affiliate referrals and incentivizing them to share the coupon with as many potential customers as possible.To link a coupon to an affiliate, you simply go toWooCommerce -> Marketing -> Coupons, select the coupon you want to link and then find the Solid Affiliate tab under Coupon data. In this tab, you can select and Affiliate to link to the coupon.To see all your active affiliate coupons in one place, navigate toSolid Affiliate -> Commission Rates -> Affiliate Coupons.Admin Helper – WooCommerce OrdersSolid Affiliate adds helpful information throughout your admin pages wherever possible, including WooCommerce Orders pages.Based on customer feedback, the admin helper is meant to answer your most common questions about an order:Was this order referred by an affiliate?If yes, where did the referral come from? How much was it worth?If no, why not? Was it because no affiliate coupon was applied, or because this specific product category had referral disabled?etc.When you activate Solid Affiliate, you’ll see the WooCommerce Order admin helper on every order screen.Solid Affiliate – Admin helper on a WooCommerce OrderYou’ll also see order notes for both orders that resulted in a referral, and those that did not.The purchase and commission tracking within Solid Affiliate will keep track of exactly why a referral was, or was not, awarded to an affiliate.Admin Helper – WordPress DashboardSolid Affiliate adds helpful information and intelligent assistance throughout your admin pages wherever possible, including the WordPress main dashboard page.The WooCommerce widget within your WordPress dashboard is enhanced by Solid Affiliate. You can see your total and net revenue from Affiliates right from the dashboard. Clicking on the Solid Affiliate section will take you directly to a reporting tool, as you’d expect.Solid Affiliate augments the WooCommerce helper within your WordPress dashboard.This WooCommerce integration is built into the core of Solid Affiliate. There aremanymore helpful features sprinkled throughout the plugin, and it improves every day. If you have any questions or feature requests specific to the WooCommerce integration, pleasecontact our team.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/affiliate-portal/',
                'title' => 'Affiliate Portal',
                'description' => 'Overview of the Affiliate Portal',
                'content' => 'SetupSolid Affiliate comes with a Setup Wizard that will help you configure the necessary pieces to get your affiliate program running smoothly. Part of the Setup Wizard will automatically create an Affiliate Portal page for you, where your affiliates will register, log in, and view their personal portal. You can enter any page slug you like for the new Affiliate Portal page, click “Create Affiliate Portal Page,” and a new WordPress page with the Solid Affiliate shortcode[solid_affiliate_portal]will be generated.You can also configure an existing page to be the Affiliate portal, and which forms to show on the portal page inSettings>Affiliate Portal & Registration>Affiliate Portal Settings. To learn more about affiliate portal shortcodes, and how they affect which forms are displayed on your portal page, please visitSolid Affiliate Shortcodes.Logged in as an AffiliateEvery affiliate in your program will be able to log in and view their Affiliate Portal.This is where all the relevant data about how they are promoting your business, how much they are owed in commissions, what their affiliate links and creatives are, and any other feature that affects how an affiliate can promote.The dashboard allows your affiliates to know where they stand regarding their Referrals, Visits, and Earnings. Each tab will drill into each part of their interaction with your affiliate program,including what their Affiliate Links are. Solid Affiliate will track Visits to any URL on your site if it includes the referrals URL query param with a valid Affiliate ID. The Affiliates Links tab will generate the link for your Affiliates or whatever URL path they add to your site’s home page.Portal PreviewAs the site owner or manager of an affiliate program, you will often want to see what your affiliates see when they log into their Affiliate Portal. Solid Affiliate allows you to do this via the Preview Portal tool.Here you can view and interact with an affiliate’s portal how they would. This can be helpful when there is confusion about or a disagreement with an affiliate and their visits, links, referrals, payouts, etc. To view a specific affiliate’s portal, search for the affiliate, select it in the dropdown, and click “Change Affiliate.”When viewing your affiliates table, you can navigate to a specific portal preview by clicking theorange preview portal linkdisplayed in the Affiliate column.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/automatic-affiliate-coupons/',
                'title' => 'Automatic affiliate coupons',
                'description' => 'Auto-create coupons for your affiliates',
                'content' => 'Solid Affiliate comes withnative support for WooCommerce coupons. You can link any coupon to an affiliate, incentivizing them to share the coupon with as many potential customers as possible. This addon automatically creates a coupon in WooCommerce for an affiliate whenever a new affiliate is created and approved.OverviewThe settings for the addon require that a coupon template is selected from your list of WooCommerce coupons. Whenever an affiliate is approved, a coupon will be created and linked to the affiliate using the selected coupon template’s settings in WooCommerce. The only configuration that is not copied from the coupon template is the coupon code.If a new affiliate is created, but not approved, no coupon will be created until they are approved.Coupons that are in the trash cannot be selected as the coupon template. However, coupons that are of status “Draft” or “Pending Review” can be selected as the coupon template, but this will result in the auto-created coupons being of status “Draft” or “Pending Review.”If the selected coupon template is moved to the trash or deleted in WooCommerce, then the addon will remove that coupon as the template, and you will have to select a new coupon template from WooCommerce.Enable this addonAll Solid Affiliate addons can be enabled/disabled in Solid Affiliate’s Addons page. There are no additional plugins to install, everything is included.FAQHow is the coupon code determined?The coupon code is generated by Solid Affiliate automatically for each coupon created. The code is generated using the username of the WordPress useruser_nicenameand the “Coupon Amount” set on the coupon template.Is there a way for affiliates to generate their own coupons?Not at this time. If you really want this feature please contact us and let us know.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/overview/',
                'title' => 'Overview',
                'description' => 'An overview of Solid Affiliate.',
                'content' => 'What is Affiliate MarketingAffiliate Marketing describes revenue-sharing networks between businesses and affiliate marketers. The goal of affiliate marketing is to generate wealth by incentivizing referral channels.Online stores use plugins like Solid Affiliate to incentivize people (for example bloggers, YouTubers, influencers, businesses, existing customers) into sending them new customers.How do I use Solid AffiliateBasic usage of Solid Affiliate is straightforward. It’s designed to be ashands offas possible. You can get up and running in three steps:– Buy and install Solid Affiliate on your WordPress site, finish our setup wizard.– Sign up your first affiliates.– Pay affiliates their commissions.Solid Affiliate is designed to be easy to use. We recommend jumping in, getting started, and then using these docs as needed. Google has the best search engine and our docs are indexed, so we recommend running google searches with “Solid Affiliate”in them to search through these docs most efficiently. You’ll often find a helpful result, and we’re always adding more content.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/reports/',
                'title' => 'Reports',
                'description' => 'Track and monitor your affiliate program performance',
                'content' => 'Reporting within Solid AffiliateSolid Affiliate comes with reporting. You can use the reports tool to visualize the performance and trends of your affiliate program and see all the relevant data in one place.You get to the reporting tool by clickingSolid Affiliate -> Reportswithin WordPress admin.The Reports -> Overview tab is the most useful. All the data is on this one page, and you can filter by date range.The other tabs give you more detailed reporting on the data behind your Affiliates, Referrals, Payouts, and Visits.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/dashboard/',
                'title' => 'Dashboard',
                'description' => 'Monitor your affiliate program performance',
                'content' => 'Solid Affiliate gives you a dashboard into your Affiliate program. You get to this page by navigating toSolid Affiliate -> Dashboard.Admin NotificationsSolid Affiliate ships with a built-in notification system to manage affiliate approvals and pending payments. The system currently supports the following notifications:Setup wizard status —A reminder to finish all installation wizard steps when left uncompleted.Pending affiliate application(s) —Count of affiliate applications waiting for approval or denial.Pending affiliate payment(s) —Count of affiliates eligible for payouts.You can fold/unfold the notifications list by clicking on the notification box.TotalsThe Totals widget within the Solid Affiliate dashboard shows you quick stats for the following data. You can see more data about your affiliate program within theReports tool.Revenue from all AffiliatesAffiliate Signups.Referrals.Visits.New AffiliatesThe New Affiliates widget within the Solid Affiliate dashboard displays the most recent affiliate signups that your program has received. You can use this to quickly approve any pending affiliate registrations.Top AffiliatesThe Top Affiliates widget within the Solid Affiliate dashboard shows you quick stats for your highest earning affiliates. For each affiliate the widget shows:Paid CommissionsReferralsVisitsYou can see more data about your affiliate program within theReports tool.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/payouts/',
                'title' => 'Payouts',
                'description' => 'View and manage referral payouts',
                'content' => 'Manage payoutsTo preview any generated payout, go toSolid Affiliates > Payouts. Here, you can view detailed information about each payout. Payouts are generated when any of the following actions occur:Marking referrals as Paid with bulk actions (which will generate a payout for each affiliate individually).Marking a single referral as Paid using the Mark as Paid link in the Actions column on theSolid Affiliate > Referralsscreen.Generating a payout file.Generating a payout for a single affiliate.When a payout is created, it will show up on the Payouts screen:You can filter payouts by Affiliate ID to preview only those generated for that single affiliate.For configuration, help, and documentation regarding paying your affiliates, please visit the Paying your Affiliates article.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/store-credit/',
                'title' => 'Store Credit',
                'description' => 'Pay your affiliates in store credit.',
                'content' => 'This addon enables Store Credit functionality, allowing affiliates to be paid their commissions in store credit and then redeem their store credit during WooCommerce checkout. This adds arobust and professionalstore credit system to your store, including admin management, historical logs, email notifications, a store credit tab within each affiliate portal, and more.OverviewOnce the addon is enabled, you’ll store credit components in the following places:Solid Affiliate > Addons > Store Creditthis is the primary admin screen to manage your program’s store credit.Solid Affiliate > Affiliate > editwill now include a store credit section for adding and removing store credit manually from any individual affiliate.Affiliate Portalswill have a store credit tab, where the affiliates can see how much store credit they have in addition to historical logs for their store credit earnings and usage.Cart and Checkouton your store will display a component tologged-inaffiliates who have store credit. They’ll be able to apply and remove store credit from their cart with one click.Store Credit Settingsinclude relevant settings such as notification emails and disabling store credit.ScreenshotsBelow are screenshots of the store credit functionality within Solid Affiliate.Solid Affiliate > Addons > Store Credit this is the primary admin screen to manage your program’s store credit.Solid Affiliate > Affiliate > edit will now include a store credit section for adding and removing store credit manually from any individual affiliate.Affiliate Portals will have a store credit tab, where the affiliates can see how much store credit they have in addition to historical logs for their store credit earnings and usage.Cart and Checkout on your store will display a component to logged-in affiliates who have store credit. They’ll be able to apply and remove store credit from their cart with one click.All historical logs for Store Credit transactions are recorded and displayed to the admin. This makes for a robust and transparent store credit system.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/shortcodes/',
                'title' => 'Shortcodes',
                'description' => 'Solid Affiliate shortcodes',
                'content' => 'Affiliate portal shortcodeSolid Affiliate comes with a collection of shortcodes to give you control over the Affiliate Portal components.Solid Affiliate comes with an automatic way to create a page and embed the[solid_affiliate_portal]shortcode during the setup wizard. You can use these shortcodes on any page of your site, as many times as necessary to accomplish your goal.Overview VideoWe recommend watching this video recorded by our team which walks you through the shortcodes and shows you a live example use case. By the end of the video you’ll know everything you need to know.[solid_affiliate_portal]The[solid_affiliate_portal]shortcode renders the entire Affiliate Portal, including the registration form and affiliate login form unless you edit the Affiliate Portal settings. Youmustuse this shortcode somewhere to render the affiliate portal. You can disable the login and registration forms from rendering via this shortcode in Affiliate Portal settings, and then use the below shortcodes for fine-grained control.Note: This shortcode also takes anaffiliate_group_idparameter. Setting this will cause any affiliates who register through the form to be assigned to the respective affiliate group. You must ensure that the ID is correct and that the group exists. Example[solid_affiliate_portal affiliate_group_id="3"][solid_affiliate_portal_login]The[solid_affiliate_portal_login]shortcode renders just the Affiliate Portalloginform.[solid_affiliate_portal_registration]The[solid_affiliate_portal_registration]shortcode renders just the Affiliate Portalregistrationform.Note: This short-code also takes anaffiliate_group_idparameter. Setting this will cause any affiliates who register through the form to be assigned to the respective affiliate group. You must ensure that the ID is correct and that the group exists. Example[solid_affiliate_portal_registration affiliate_group_id="3"][solid_affiliate_if_referred_by_affiliate ]To conditionally display something to a visitor if they have been referred by an affiliate, you can use the[solid_affiliate_if_referred_by_affiliate]shortcode. This shortcode also takes anfieldparameter to conditionally show the current referring affiliate’s full name, or their username.[solid_affiliate_current_affiliate field="fullname"]displays display the affiliate’s fullname.[solid_affiliate_current_affiliate field="username"]displays the affiliate’s username.Example of conditional rendering[solid_affiliate_if_referred_by_affiliate ]You’re shopping with[solid_affiliate_current_affiliate field="fullname"]![/solid_affiliate_if_referred_by_affiliate][solid_affiliate_current_affiliate_link]Checks to see if the current user is an affiliate. If they are, it returns the default affiliate link for them. Otherwise, it returns an empty string.Use case: “I would like to insert this shortcode on my site, and a link like this should appear: https://mydomain.com?sld=affiliateid”',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/affiliates/',
                'title' => 'Affiliates',
                'description' => 'Create and manage affiliates',
                'content' => 'Enabling affiliate registrationSolid Affiliate ships with a useful affiliate registration form, so you can start recruiting affiliates as soon as you’re ready to launch. This registration form is not enabled by default, so you can plan and set up your affiliate program before you start allowing affiliate registrations.To learn more about affiliate registration, please visit theAffiliate portal configuration.Add a New AffiliateTo manually add a new affiliate from your WordPress admin, go toSolid Affiliate > Affiliates, and click on the Add New button.In the Add New Affiliate page, fill in the following fields:User ID —Select a WordPress user to link with this affiliate account.Commission Rate —The rate to use when calculating referral amounts. When Referral Rate Type is set to ‘Percentage (%)’ this number is interpreted as a percentage. When Referral Rate Type is set to ‘Flat’ this number is interpreted as a float amount of whichever currency you are using.Commission Type —Used in conjunction with the Referral Rate to calculate the default referral amounts. You can edit the site default in Settings > General.Payment Email —Enter the email that will be used for Affiliate’s payments.Registration Notes  —Affiliate submitted these notes upon registration.Status —Approved or Rejected. Select The status of the Affiliate’s account. Only Approved Affiliates can earn Referrals.Don’t forget to click the Add New Affiliate to apply changes.Register an existing WordPress User as an AffiliateAffiliate accounts can also be registered when manually editing WordPress user accounts on your site under Users. Search for the user, click Edit to see their profile, and then click “Register as an Affiliate” under the Solid Affiliate section.Edit an AffiliateFirst, go toSolid Affiliate > Affiliate. Select the affiliate in the list, hover over the affiliate name, and click on Edit Affiliate.Manage AffiliatesTo manage Affiliates, go toSolid Affiliates > Affiliates. Here, you can check the basic information on every link and filter them by status.Use theScreen optionsmenu located on the top right corner of your WordPress admin page to control which affiliate columns are displayed.Approve/Reject affiliatesAfter being registered, the status of the Affiliate will be set as Pending. Merchant will then decide to approve the Affiliate’s registration. Here, there are the 3 options for the merchant to choose from, based on the real situation of the affiliate registration:Approved —The affiliate’s registration is approved, and the affiliate will be granted access to the affiliate portal.Rejected —The affiliate registration is rejected. Merchant can change the status of the affiliate registration later.Delete —The affiliate registration will be deleted.Affiliate RolesAll WordPress users who register to be an Affiliate will be assigned anaffiliaterole. This is a standard practice that ensures compatibility and integration with WordPress and many popular plugins such as Elementor, User Role Editor, and Restrict Content Pro which all respect the WordPress User roles functionality.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/creatives/',
                'title' => 'Creatives',
                'description' => 'Affiliate creatives and banner images',
                'content' => 'Add a New CreativeFirst, go to Solid Affiliate > Creatives. To add a new Creative, click on the + Add New button.In the Add New Creative page, fill in the following fields:Status —Active or Inactive. Select the Active status to make the creative available to your affiliates immediately, and the Inactive status to disable the creative for now.Name —the name of the creative.Description — A short description of the creativeURL —the Landing Page tied to the creative. You can use a URL of your site or your product landing page. If you use multiple creatives, you can select different links (different landing pages) for each of them.Creative Text —The text to be used when generating the creative. If you want a text-only creative (a link to the URL above), do not upload a creative image below.Creative Image URL —The image to be used when generating this image banner creative.Don’t forget to click the Add New Creative to apply changes.Edit an Existing CreativeFirst, go toSolid Affiliate > Creatives. Select the creative in the list, hover over the creative, and click on Edit this item.Manage CreativesTo manage Creatives, go to Solid Affiliates > Creatives. Here, you can check the basic information on every link and filter them by status. You can also preview the Creative, tracking link, and the shortcode for every Creative.Use theScreen optionsmenu located on the top right corner of your WordPress admin page to control which Creative columns are displayed.Embedding Creatives (for your affiliates)Once an affiliate is logged into their portal, they can click on the Creatives tab to view the creatives (either image or text based) you have created for them.On the Creatives tab, they can click the “View Embed Code” button to see the HTML they can embed on whatever website or application they want.They can copy the HTML link provided in the modal, and paste that into wherever they display their HTML creatives. Depending on what your affiliates use to promote your site, the actual steps to embed the HTML may differ, but ultimately it is inserting HTML into an existing page. There will likely be documentation to help them insert the HTML they copied from their affiliate portal into where they edit the HTML of their website or application.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/translate-solid-affiliate/',
                'title' => 'Localization',
                'description' => 'Translate Solid Affiliate into your language',
                'content' => 'Solid Affiliate isfully translatable and supportsseveral languages out-of-the box. If your language is not included, don’t worry! The entire plugin can be easily translated using common tools such as Poedit or Loco Translate.Translating Solid Affiliate using Loco TranslateLoco Translateis a completely free WordPress plugin that provides in-browser editing of WordPress translation files and integration with automatic translation services. In order to install it, simply navigate to thePlugins → Add Newsection of your admin dashboard, search for Loco translate, install and activate it.Go toLoco Translate → Home.Under Plugins, click onSolid Affiliate.Choose a language by selecting it from the list of languages WordPress knows about. If your language is not in the list (or if you just prefer) you can enter the locale code in the text field instead.When choosing a location for your translation files, please avoid saving files in locations managed by WordPress updates. Your files may be overwritten or deleted.Edit translation files directly in WordPress admin and save them to the correct file formats for WordPress to use.Make sure your language has been selected in WordPress admin settings.You’re all set!For more details about Loco translate, please refer to theirLoco translateon adding some custom translations to a theme or a plugin.Translating Solid Affiliate using PoeditSolid Affiliate can be easily translated into your chosen language if a translation does not already exist within the plugin. Using Poedit will require installing a 3rd party software on your computer, and access to your WordPress installation files (Via FTP, or directly using your host file manager).Download and installPoedit.Open Solid Affiliate plugin zip file, and navigate to the languages folder (solid_affiliate/assets/lang) and open the solid-affiliate.pot file within Poedit.Go to File → Save As to save your translations in a .po file. Label the file with your country code included. For example, the Polish translation files are calledsolid-affiliate-pl_PL.moandsolid-affiliate-pl_PL.po.Start editing the translation to your language. When you are finished translating, go to File → Save As again to generate the .mo file.Use FTP or your hosting file manager to place these 2 files in your /wp-content/languages/plugins/solid-affiliate folder (create one if it’s not there).Make sure your language has been selected in WordPress. You’re all set!Contributing a translationOur team at Solid Affiliate work collaboratively on language packs using with customers and translators from all around the world. If you see an error or non-translated term in our plugin in your language, you can contribute a correction or translation via the by opening asupport ticketso we can include them in a future plugin update.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/faqs-misc/',
                'title' => 'FAQs / Misc.',
                'description' => 'Managing miscellaneous settings',
                'content' => 'What is Solid Affiliate?Solid Affiliate is a WordPress plugin that adds everything you need to build, manage, and grow your affiliate program from the comfort of your WordPress dashboard.What plugins does Solid Affiliate integrate with?Solid Affiliate integrates out of the box with WooCommerce and WooCommerce Subscriptions. We are working on more integrations with other popular WordPress plugins and SaaS platforms.What payment method do you accept?You can use PayPal or any major credit card to purchase the Solid Affiliate plugin.How do I request a refund?We offer a 30 day money-back guarantee. If you’re unhappy with Solid Affiliate for any reason, e-mailteam@solidaffiliate.comrequesting a refund and we’ll issue it for you.What license is Solid Affiliate released under?Solid Affiliate is 100% open source and licensed under the terms of the GPL v2. You can read ourlicense agreementat any time.Can I use Solid Affiliate on client websites?Yes. Your license is valid for unlimited sites that you work on, whether they’re for you or someone else. You can read ourlicense agreementat any time.Can I use Solid Affiliate on multiple websites?Yes! You can manage all your active websites in your account page under License page (View Websites > Manage Licenses).Can I use Solid Affiliate on client websites?Yes. Your license is valid for unlimited sites that you work on, whether they’re for you or someone else.How do I generate an affiliate link?When a visitor gets to your site via an affiliate link, Solid Affiliate will keep track of the fact that an affiliate sent you the visitor. If they make a purchase within thecookie expiration time limit, the affiliate will receive credit for the purchase in the form of areferralrecord. Affiliate links follow a simple format: any url on your website with a?sld=<affiliate_idquery parameter added to it.Here is an example :www.solidaffiliate.com/?sld=125. This link would associate a visit with Affiliate #125.The Affiliate’s link is displayed prominently on their Affiliate Portal.Every creative will include the link generated for the affiliate by the plugin automatically.You can generate links manually by adding that parameter to any page on your site.Note:In addition to these links, affiliates can receive a referral by using anyaffiliatecouponsthat you’ve linked to their account.Do you support Multi-level Marketing (MLM)?We don’t currently. If you want this feature, or you want tobuildthis feature as a partner, please reach out atteam@solidaffiliate.com.Can I set custom commission rates?Yes! It’s easy to change commission rates within Solid Affiliate. Read about theCommission Managementfeature.Does Solid Affiliate provide custom development?No. We solely focus on developing the core Solid Affiliate product.Which currency is supported for Affiliate payments?You can configure the payouts currency in your Solid Affiliate settings. The tool currently supports the major currency codes: USD, AUD, CAD, CZK, DKK, EUR, HKD, ILS, MXN, NZD, NOK, PHP, PLN, RUB, SGD, SEK, CHF, THB, GBP.How do I exclude certain products from my affiliate program?You can disable any product from generating Referrals by going to the product screen within WooCoomerce > Products > Edit Product and then you’ll see a Solid Affiliate tab. Simply check theDisable Referralssetting.What happens when I have a cookie from one affiliate and a coupon code from another on the same order? Which affiliate attribution strategy takes precedence? What is the order of affiliate attribution?​In Solid Affiliate, the Coupon takes precedence over the Cookie. To get a complete picture of affiliate attribution – i.e. which affiliate will get credit for an order – you can seeSolid Affiliate – Attribution documentation.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/paypal-payouts/',
                'title' => 'PayPal Payouts',
                'description' => 'Integrate Solid Affiliate with PayPal Payouts',
                'content' => 'Installation and OverviewThe PayPal Payouts integration is built into Solid Affiliate.You won’t need to purchase or install any additional add-ons to get it working. The integration works with thePay Affiliatesfeature by sending payments directly through your PayPal account, via API. To get things set up, navigate toSolid Affiliate -> Settings -> Integrations -> PayPal.You can also test things out by creating aSandboxaccount within PayPal and then using the sandbox credentials and sandbox mode within Solid Affiliate. Once things are set up, you’ll be able to access the PayPal Bulk Payout option withinSolid Affiliate -> Pay Affiliates.Video. How to connect Solid Affiliate and PayPal.Notes and TroubleshootingThe Solid Affiliate PayPal integration requires API access to your PayPal account. API access is available for all business accounts, you mustensure that Payouts are enabledwithin your PayPal account.Important noteIf you’re seeing an `AUTHORIZATION_ERROR` from PayPal, do not worry it’s an easy fix. This error most often simply means that your PayPal account needs to have the Payouts functionality enabled.Enable Live Payouts for your account within PayPal here https://developer.paypal.com/developer/accountStatusYou may need to submit a request and wait for PayPal to approve Payouts for your account. In our own experiences, it takes a few days. We’ve also found that the Payouts activation can be expedited by writing a professional support message to PayPal support, or by submitting a ticket toPayPal technical support.Youmusthave enough money in your PayPal account to cover the bulk payout, or the payout will fail.PayPal Fees:While Solid Affiliate does not charge any fees for using the PayPal integration, PayPal charges transfer fees in order to send payments.Learn more about PayPal mass payment fees.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/auto-register-new-users-as-affiliates/',
                'title' => 'Auto-register new users as Affiliates',
                'description' => 'Automatically create an affiliate account for all new users who register a user account.',
                'content' => 'Enable this addon if you would like all new users to become affiliates. New users might register through 3rd party integrations (WooCommerce customer profiles, registration forms, membership plugins.. etc). All new users who register a user account on your site will be automatically registered as affiliates. You can select any roles which should not result in an affiliate being created using the “User roles to ignore” setting.Enable this addonAll Solid Affiliate addons can be enabled/disabled inSolid Affiliate > Addonspage.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/mailchimp-integration/',
                'title' => 'Mailchimp integration',
                'description' => 'Integrate Solid Affiliate with Mailchimp',
                'content' => 'You can automatically signup your affiliates to a specific audience list in your Mailchimp account. Enabling integration will sync your affiliate to your Mailchimp list allowing you to communicate with them easily.Connect Solid Affiliate to MailchimpEnable Mailchimp integrationTo enable Mailchimp integration with Solid Affiliate from your WordPress admin, go to Solid Affiliate > Settings, and navigate to Integrations tab.Step 2 : Enter API Key and Audience IDTo connect your Mailchimp account with Solid Affiliate, you’ll need to generate an API key.Learn how to find or generate your API key on Mailchimp.Set a specific audience list within your Mailchimp account by entering the ID. Solid Affiliate will sync newly registered Affiliates to this list. Leave it blank to sync to your default list.Learn how to get your Mailchimp Audience ID.Don’t forget to click the Save settings to apply changes.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/lifetime-commissions/',
                'title' => 'Lifetime Commissions and Linked Customers',
                'description' => 'Solid Affiliate - Lifetime Comissions',
                'content' => 'Lifetime Commissions / Linked CustomersSolid Affiliate comes with the ability tolink a customer to an affiliateso that the affiliate receives commissions on all future purchases by the customer to who they originally referred. We call this featureLifetime Commissions.You can enable and configure this feature from Solid Affiliate > Settings.Overview VideoWe recommend watching this video recorded by our team, which walks you through the Lifetime Commissions feature and shows you a live example use case. By the end of the video, you’ll know everything you need to know.Explanation and benefitsEnabling lifetime commissions for your affiliates will increase the value of your affiliate program. Affiliates will be highly incentivized to refer you customers if they trust that they’ll receive ongoing commissions for the lifetime (or some other duration) of the customer on your store.You canguaranteeyour affiliates that theywillreceive a commission whenever a linked customer makes a purchase, even if the customer clears their cookies or uses an entirely different computer. The affiliate-customer relationship is stored directly in Solid Affiliate. Italso works for purchases as guests, using the customer’s email address to link back to the affiliate.As an admin, you can see which customers are linked to every affiliate in your program, and how many referrals have been generated by each affiliate-customer link.All these features work automatically when enabled.Settings and configurationEnable or disable lifetime commissions.This will enable the creation of linked customers automatically.Lifetime commissions referral rate.Either leave this tosite defaultto inherit your site referral rate settings, or set an override such as 10%. We recommendsite defaultto keep things simple.Duration.Set the duration of the link between the customer and the affiliate. This defaults toNo Limit.Only new customers.Use this setting if you want to preventexistingcustomers from being linked to affiliates. We recommend leaving this setting enabled.Show affiliates their customers.Use this setting if you want to add aLinked Customerstab to every affiliate’s portal. This will show them an anonymized list of link customers for whom they are actively receiving lifetime commissions. For each linked customer, they will see how many referrals they have made and when the link will expire (if there is an expiration set).FAQ – Frequently Asked Questions regarding lifetime commissions and linked customers.If you have a question about this feature please send it to us at team@solidaffiliate.com and we’ll answer it and add it to this list.Does Solid Affiliate support Lifetime Commissions?Yes! The feature is included, and you can activate it with one click. Absolutely no additional purchase, install, or configuration is necessary..Why should I enable Lifetime Commissions?It’s entirely up to you whether or not you enable this functionality. If enabled, lifetime commissions serve as a powerful incentive for your affiliates. They are guaranteed to receive commissions whenever their referred customers make additional purchases on your store.Will Lifetime Commissions work with guest checkouts?Yes! Solid Affiliate will automatically associate the email address used at checkout for any guest checkout.Can I set an expiration for lifetime commissions? For example, 6 months or 1 year?Yes! You can choose from many default duration limits. You can also delete or edit any linked customers at any time from your admin dashboard.Will affiliates see their lifetime customers within their affiliate portals?It’s up to you! There is a setting that either shows or hides an additionalLifetime Customerstab from your affiliates’ portals.How are Lifetime commissions different than simply setting the Solid Affiliate cookie lifetime to “unlimited” time?Lifetime Commissions is a significantly more robust attribution method than setting an unlimited cookie. Once a customer is linked to an affiliate, the attribution will work even if the customer orders from an entirely new computer or clears all their browsing cookies.If a customer that is linked to Affiliate #1, buys a product from a link of Affiliate #2, both affiliates will receive $? Like Affiliate #1 the lifetime commission and Affiliate #2 the product commission?Only the linked Affiliate #1 will receive the commission. The Lifetime Commissions is higher priority than an affiliate link or coupon or landing page…etc commission. We’ve added this as an FAQ herehttps://docs.solidaffiliate.com/lifetime-commissions/————There is only one way within Solid Affiliate for multiple affiliates to receive a commission on the same purchase: Auto Referrals. This is an awesome feature (that no other plugin we know of has) that allows you to model revenue-sharing / royalties within Solid Affiliate. You can configure a situation such as “Every time product X is purchased, no matter what, award a commission to Affiliate Y with a commission rate of Z.” This is super useful if you have a marketplace or a partner that deserves some % or flat $ for every purchase of a specific product(s).You can read a bit more about Auto Referrals here:https://docs.solidaffiliate.com/testing-solid-affiliate/#testing-the-auto-referral-feature',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/troubleshooting/',
                'title' => 'Troubleshooting',
                'description' => 'Learn how to troubleshoot Solid Affiliate plugin',
                'content' => 'Please emailteam@solidaffiliate.comor use thesupport portalfor any help with troubleshooting Solid Affiliate.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/statuses/',
                'title' => 'Statuses',
                'description' => 'Solid Affiliate Statuses',
                'content' => 'Affiliate StatusesEvery affiliate has a status that is visible both to the admins and the affiliates themselves through their portal.Approved– The affiliate has been approved and can actively earn referrals.Pending– The affiliate is awaiting approval. The affiliate can access their affiliate portal and use their links and creatives to generate visits, but they cannot generate referrals until approved.Rejected– The affiliate was rejected by an administrator. This affiliate cannot access their affiliate portal, and cannot generate referrals.These statuses are only changeable by administrators, with one exception: there is a setting within Solid Affiliate > Settings > Affiliate Portal & Registration >Require Affiliate Registration Approvalwhich when disabled will automatically set affiliate status toapprovedupon registration.Referral StatusesReferral statuses are perhaps the most important, as they directly affect owed commission amounts. Referral statuses areautomatically updatedby Solid Affiliate as events take place such as orders being refunded, canceled, completed, and/or when the affiliate is paid their commission through thebuilt-in pay affiliates tool.Unpaid– The referral has been approved and commission is owed to the referring affiliate. This is the “successful referral” status, indicating that the underlying order went through and the affiliate is owed a commission for this referral.Paid– The referral is approved and the commission has been paid to the associated affiliate. This status was marked as paid automatically by the Solid Affiliate > Pay Affiliates tool, or manually by an administrator.Rejected– The referral has been rejected either manually by an admin, or automatically by the Solid Affiliate > WooCommerce integration. Reasons for automatic rejection include: the underlying purchase failed, or was canceled or refundedbeforethe referral was paid. This referral willnotbe displayed within the affiliate portals, and willnotcount towards the referral reports.Draft– The referral is pending due to one of the following reasons: the underlying order status is stillpendingoron hold.The referral status will be updated automatically once the underlying order status is updated. This referral willnotbe displayed within the affiliate portals, and willnotcount towards the referral reports.We do not recommend manually changing referral statuses – trust in the Solid Affiliate plugin to do all this work for you – unless something happened outside of Solid Affiliate (for example, you paid a single affiliate by mailing them a check for some reason and you forgot to use the “manual payout” option within thebuilt-in pay affiliates tool) and want to force change a status. To learn more about WooCommerce order statuses, an integral part of the affiliate referral status flow, we’d recommendthis documentation page.Payout StatusesPaid– The payout has been marked as paid automatically by the Solid Affiliate > Pay Affiliates tool. This is the only valid status at this time.Bulk Payout StatusesProcessing– The bulk payout is still processing. In the case of a PayPal API payout, PayPal returned a processing status.Success– The bulk payout was successfully sent. In the case of a PayPal API payout, PayPal returned a success status.Fail– The bulk payout failed. In the case of a PayPal API payout, PayPal returned a fail status. If you see this status, please contact the Solid Affiliate support team.Bulk payouts are created via thebuilt-in pay affiliates tooland are assigned statuses automatically.Creative StatusesActive– The creative is active and will appear in the affiliate portals.Inactive– The creative is inactive and will not appear in the affiliate portals.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/configuration/',
                'title' => 'Configuration',
                'description' => 'Configure your own affiliate program',
                'content' => 'Configure Commission RatesSolid Affiliate makes it easy to customize the commission structure of your Affiliate Program.Here’s a complete list of the settings that can affect a commission rate for a given order:Exclude ShippingThis setting allows you to exclude shipping costs from referral calculations, so the order total that Solid Affiliate calculates the referral amount from does not include the shipping cost that is charged to customers.Exclude TaxThis setting allows you to exclude tax from referral calculations, so the order total that Solid Affiliate calculates the referral amount from does not include the tax that is charged to customers.Default Commission RateThe default commission rate applied to all orders. Can either be a flat amount or a percentage amount.Note:This does not apply to subscription renewals – see Default Commission Rate (Recurring Referrals).Default Commission Rate (Recurring Referrals)The default commission rate applied to all subscription renewals. Can either be a flat amount or a percentage amount.Affiliate Specific Commission RateA custom commission rate for a specific affiliate. This will override all other commission rates.Product Specific Commission RateA custom commission rate for a specific product. This will override the default commission rate and the product category commission rate, but not the affiliate specific commission rate.Product Category Commission RateA custom commission rate for a specific product category. This will override the default commission rate, but will be overridden by any of the other specific commission rate settings.Whether you want a dead-simple commission structure – for example 20% of any purchase – or something more complex where you offer a different commission for each of your products, or you need certain affiliates to have their own custom commission rate. Everything is contained on one page within the plugin.You can get to the Commission Rates page by clicking onSolid Affiliate -> Commission Rates. You can use the Commission Rates page to see and configure all your current commission rate settings.Configure your Affiliate PortalThe Affiliate Portal works right out of the box, but you should customize a few settings.Affiliate Portal settings can be found inSolid Affiliate -> Settings -> Affiliate Portal & Registration.Require Affiliate Registration ApprovalRequire approval of new Affiliate accounts before they can begin earning referrals. If turned off, Affiliates will be automatically set to Approved upon registration.Terms of Use PageSelect the page with contains your Affiliate Program Terms and Conditions. Solid Affiliate will link to this page on Affiliate Registration.Terms of Use labelEnter the text that should be displayed on the Affiliate Program Terms and Conditions label.reCAPTCHAYou have the option to enable a Google V2 Checkbox reCAPTCHA as part of your affiliate registration form. In order for it to work, you must (1) turn on the “Enable reCAPTCHA” setting, provide a valid site key and secret key, and configure your reCAPTCHA account to include your site’s domain under “Domains”. You will find your site and secret key, and the list of domains in your Google reCAPTCHA admin settings.Customize the page your Affiliate Portal is onThe[solid_affiliate_portal]shortcode will embed the Affiliate Portal into any page you choose on your site. When setting up the Portal via the Setup Wizard, the shortcode is added to the page of your choosing behind the scenes. We recommend editing this page using the WordPress editor or visual website builder of your choice in order to add pertinent information about your affiliate program.Customize the Registration FormIf you want to customize the registration form to fit your affiliate program needs, please refer to the documentation here:Customize the Affiliate Registration FormBrand your Affiliate PortalYou can change the word “Affiliate” on the Affiliate Portal Login and Registration forms, and within the Affiliate Portal your Affiliates see when they are logged in.You can use this to brand your Affiliate Portal how you like. For example: “Partner” or “Influencer”. Or you can configure it to display the name of your business to further brand your Affiliate Portal.To change the text on the portal forms the setting is underSettings>Affiliate Portal & Registration>Custom Registration Form Settings. To change the text in the Affiliate Portal (for logged in Affiliates) the setting is underSettings>Affiliate Portal & Registration>Affiliate Portal Settings.Display Default Affiliate LinksGiving your Affiliates a link to to start sharing immediately is important. To configure what this link looks like there are a couple of settings you can adjust. The first setting can be found atAffiliate Portal & Registration>Affiliate Portal Settings>Affiliate Slug Display Format, which sets the default URL slug format to eitherCustom Slugsor IDs. The second is atAffiliate Portal & Registration>Affiliate Portal Settings>Default Affiliate Link URL, which sets the default “base URL” to which the Affiliate tracking slug will be added to.Based on those two settings, the Affiliate Portal will display a default link, and when your Affiliate use the URL generator, those links will also be formatted using those settings.Add CreativesCreatives empower your affiliates to be more successful.Solid Affiliate makes creative management easy. You simply add creatives in your WordPress admin and they’ll be instantly available to all your affiliates within their affiliate portals. For a walkthrough on how to add creatives, please refer to theSolid Affiliate creatives documentation.Configure Email TemplatesSolid Affiliate email notifications work right out the box, but you should customize a few settings.  All email settings can be found inSolid Affiliate -> Settings -> Emails.Here’s a list of the settings we recommend you customize:Affiliate Manager EmailEnter one or more email addresses to receive Affiliate Manager notifications. Separate multiple email addresses with a space in between. These email addresses should be employees or team members of your company.The Four Email TemplatesYou can change the email templates to make the email notifications fit your needs. Every one of the email templates comes with a few tags which will be replaced with variable data when the email is sent by Solid Affiliate.Dates and TimezonesSolid Affiliate uses your WordPress date and timezone settings found in General Settings.These settings will affect how dates show up in Solid Affiliate. The three settings that Solid Affiliate uses are: Timezone, Date Format, and Time Format',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/woocommerce-subscriptions/',
                'title' => 'WooCommerce Subscriptions',
                'description' => 'Integrate with Solid Affiliate with WooCommerce Subscriptions',
                'content' => 'Installation and OverviewThe first thing to note is that the WooCommerce Subscriptions integration is built right into Solid Affiliate.You won’t need to purchase or install any additional add-ons to get it working. The plugin will detect WooCommerce Subscriptions and start working automatically, generating referrals for anyrenewals of subscriptionswhich were referred by an affiliate.Any referrals which came through WooCommerce Subscriptions will have a link to the corresponding order and aWooCommerceSubscriptions label in the Order ID column.ConfigurationThere is only one WooCommerce Subscriptions specific configuration, theRecurring Referrals – Default Commission Rate. You can configure this by going toSolid Affiliate -> Commission Rates -> Default Commission Rates.This will set the commission rate for therenewalsof any subscriptions.Important note on how subscription commission rates are calculated.The initial purchase of the subscription used the regular commission rate settings, just like any other product. Only therenewalsuse the Recurring Referrals rate. An example commission structure would be25% of the initial subscription payment, and then 10% of any renewals going forward.It would be trivial to set a commission structure such as that within Solid Affiliate.',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/commission-rates/',
                'title' => 'Commission Rates',
                'description' => 'Set up and track commissions rates',
                'content' => 'Commission Rates OverviewSolid Affiliate makes it easy to customize the commission structure of your Affiliate Program with a dedicated tool. You get to this tool by clickingSolid Affiliate -> Commission Rateswithin your WordPress admin.All the settings that could affect how a commission rate is calculated are aggregated on this one page. The page has explanations within it, so we’d recommend simply clicking around and reading.Commission Rate settings ExplainedGlobal commission rate settingsCredit Last AffiliateThe Credit Last Affiliate option allows you to credit the last affiliate who referred the customer. If multiple Affiliates send you the same person then the last Affiliate will receive credit for any purchases.Other attribution strategies are coming soon.Exclude ShippingDepending on your business, you may be shipping physical products to customers and charging them a shipping fee, which is a hard/net cost. This setting allows you to exclude shipping costs from referral calculations, so the order total that Solid Affiliate calculates the referral amount from does not include the shipping cost that is charged to customers.Enable this setting to exclude shipping costs from referral calculations.Exclude TaxDepending on your business, you may charge your customers tax, which is a hard/net cost. This setting allows you to exclude tax from referral calculations, so the order total that Solid Affiliate calculates the referral amount from does not include the tax that is charged to customers.Enable this setting to exclude tax costs from referral calculations.Default commissions ratesDefault Commission RateThis setting sets the default rate at which commissions are calculated. For example20%or$10.00 flatper purchase.Default Commission Rate (Recurring Referrals)This settings sets the default rate at which commissions for subscriptionrenewalsare calculated. Note: the initial purchase of a subscription uses thedefault commission ratesetting from above.Why have separate commission rates? It allows you to create common subscription commission strategies such as a larger % for the initial purchase, and then a smaller commission for every renewal period for as long as the customer stays subscribed.Commission rate overridesIf you need fine-grained control over your commission rates, you can create commission rate overrides. For example: a higher commission rate on one certain product.Commissions Rates can take priority over others.  Here’s the order in which commission rate settings will be applied:Priority 1:Recurring Referral RateThe Recurring Referral Rate only applies to users of the WooCommerce Subscriptions Integration.Priority 2:Affiliate Specific RateThe Affiliate Specific Rate applies a commission rate override on a per-affiliate basis. Use these to give specific Affiliates different commission rates than the site defaults.Priority 3:Affiliate Group RateThe Affiliate Group Rate applies if an Affiliate is in anaffiliate group, and that group has commission rates configured to anything other than “site default”.Priority 4:Product Specific RateThe Product Specific Rate applies a commission rate override on a per-product basis. Use these to give specific products different commission rates than the site default.Priority 5:Product Category Specific RateThe Product Category Specific Rate applies a commission rate override on a per-product basis. Use these to give every product in a specific category different commission rates than the site default.Priority 6:Default RateAffiliate couponsGet to this tool by navigating toSolid Affiliate -> Commission Rates -> Affiliate couponswithin your WordPress admin.You can assign WooCommerce Coupons to Affiliates. Whenever a coupon is redeemed, the associated Affiliate will be credited with the sale. This section within the plugin will aggregate all the active affiliate coupons.How do coupons affect commission rates?Commission rates will be calculated from thediscounted priceafter the coupon has been applied. For example, a $100 item with a 50% coupon discount will have acommissionable amountof $50. The affiliate will be awarded a commission on the $50. The final commission amount in this example will depend on your commission rate settings. If you had the default 20% commission rate, they would receive a $10 commission ($100 * 50% discount = $50. $50 * 20% commission rate = $10.)',
            ],
            [
                'url' => 'https://docs.solidaffiliate.com/testing-solid-affiliate/',
                'title' => 'Testing Solid Affiliate',
                'description' => 'How to make sure everything is working on your site.',
                'content' => 'Solid Affiliate works straight out of the box with no additional configuration, but it is still best to test the system for yourself to ensure everything is working as expected on your unique site environment. These simple steps will walk you through testing Solid Affiliate so that you can be confident everything is working.Testing affiliate sign upWe recommend creating at least one new test affiliate account for testing purposes, which will allow you to keep any Solid Affiliate testing separate from live user and affiliate accounts.Open up your WordPress admin in one browser (e.g. Chrome), and on a completely separate incognito browser (e.g. Safari) go to your affiliate registration page.Sign up as an affiliate on your own site using the included Affiliate Registration form.The new test affiliate account will now be visible in Solid Affiliate → Affiliates in your admin dashboard.Verify that you’ve received a new affiliate notification on the affiliate administrator email.The test affiliate will have aPendingstatus. Set the account toApproved.Verify that you’ve received the affiliate application approval on the email you used to sign up as an affiliate.Testing visitsOpen up your WordPress admin in one browser (e.g. Chrome), and on a completely separate incognito browser (e.g. Safari) append a referral variable to the end of your website address (e.g. https://mywebsite.com/?sld=1). Make sure that the ID matches an active affiliate’s ID. You can get the affiliate link for any affiliate from their portal.Back in your WordPress admin, in Solid Affiliate → Visits, refresh the visits page and you’ll see a visit recorded.Testing referralsComplete a purchase on the same incognito browser that generated the visit.Back in your WordPress admin, in Solid Affiliate → Referrals, you’ll see a new referral.We highly recommend going to the admin page of the WooCommerce Order, where you’ll see aWooCommerce Order admin helper.Verify if the referral is showing on the affiliate portal.Testing affiliate-tracked couponSolid Affiliate works natively with WooCommerce coupons. When using an affiliate-tracked coupon no referral link is needed in order to generate a referral for the affiliate, and/or you can offer a discount to your customers. To test coupon tracking, follow the steps below :Go to Marketing → Coupons to create a WooCommerce.Under Solid Affiliate tab, enter the ID of the Affiliate to be awarded a Referral.On a separate incognito browser (make sure the Solid Affiliate cookie isn’t set), complete a purchase.Verify that the referral was created properly in Solid Affiliate → Referrals.Go to the order page associated with the completed purchase; the referral associated to the coupon will appear in Order notes.Verify active affiliate coupons in Solid Affiliate → Commission Rates → Active Coupons.Testing the Auto-Referral featureThe auto-referral option (if enabled) will reward a referral anytime a specific product is purchased even if they did not refer the customer. This is useful for setting up a revenue-split situation for an individual affiliate. To test auto-referral, follow the steps below :Screenshot of Add new product rate page :Enabling auto-referral.Set a new product rate in Solid Affiliate → Affiliates → Product Rates. Enable auto-referral for this product.Complete a purchase with the selected product.Go to the order page associated of the completed purchase; the auto-referral will appear in Order notes.Verify active Auto-referrals in Solid Affiliate → Commission Rates → Commission Rate Overrides.Testing commission calculationEvery referral created within Solid Affiliate should be associated with the proper Affiliate, WooCommerce Order, and Visit or Coupon ID. After running a test purchase, you can check that the calculated commission is what you’d expect. Remember that you can configure your commission rates in Solid Affiliate → Commission Rates.Screenshot of a referral details:Use Screen options to configure the view of the referrals table.The commission insights show the proper commission rate and amount.Screenshot of commission insights:How was the commission calculated.An order note was added to the corresponding WooCommerce order.Screenshot of a WooCommerce order notes:Solid Affiliate will add helpful Order notes relevant to your affiliate program.',
            ],
        ];

        $extras = [
            [
                'url' => 'https://docs.solidaffiliate.com/woocommerce/#coupon-tracking',
                'title' => 'How does coupon tracking work?',
                'description' => 'How does coupon tracking work?',
                'content' => 'Solid Affiliate comes with native support for WooCommerce coupons. You can link any coupon to an affiliate, granting that affiliate referrals and incentivizing them to share the coupon with as many potential customers as possible.  To link a coupon to an affiliate, you simply go to WooCommerce -> Marketing -> Coupons, select the coupon you want to link and then find the Solid Affiliate tab under Coupon data. In this tab, you can select and Affiliate to link to the coupon.  To see all your active affiliate coupons in one place, navigate to Solid Affiliate -> Commission Rates -> Affiliate Coupons.'
            ]
        ];

        $documentation_db = array_merge($documentation_db, $extras);

        // Convert the query to an array of words
        $query_words = self::split_query_into_words($query);

        // Filter entries based on presence of any word from the query
        $matches = array_filter($documentation_db, function ($entry) use ($query_words) {
            foreach ($query_words as $word) {
                if (stripos($entry['title'], $word) !== false || stripos($entry['description'], $word) !== false || stripos($entry['content'], $word) !== false) {
                    return true;
                }
            }
            return false;
        });

        $results = array_map(
            /**
             * @param array $entry
             */
            function ($entry) use ($query_words) {
                $total_match_strength = 0;
                $matched_words_count = 0;

                // calculate match strength for each word in the query
                foreach ($query_words as $word) {
                    $titleMatchStrength = substr_count(strtolower((string)$entry['title']), $word);
                    $descriptionMatchStrength = substr_count(strtolower((string)$entry['description']), $word);
                    $contentMatchStrength = substr_count(strtolower((string)$entry['content']), $word);

                    $weights = [
                        'title' => 0.5,
                        'description' => 0.3,
                        'content' => 0.2,
                    ];

                    $match_strength = ($weights['title'] * $titleMatchStrength + $weights['description'] * $descriptionMatchStrength + $weights['content'] * $contentMatchStrength) / ((strlen((string)$entry['title']) * $weights['title'] + strlen((string)$entry['description']) * $weights['description'] + strlen((string)$entry['content']) * $weights['content']));

                    $total_match_strength += $match_strength;

                    // if word was found in either field, increment the matched words count
                    if ($titleMatchStrength > 0 || $descriptionMatchStrength > 0 || $contentMatchStrength > 0) {
                        $matched_words_count++;
                    }
                }

                // Calculate weight based on the fraction of words from the query that were found
                $query_words_present_weight = $matched_words_count / count($query_words);

                // Multiply match strength with the weight
                $final_match_strength = $total_match_strength * $query_words_present_weight;

                return [
                    'type' => self::TYPE_DOCUMENTATION,
                    'url' => (string)$entry['url'],
                    'title' => (string)$entry['title'],
                    'description' => (string)$entry['description'],
                    'match_strength' => $final_match_strength,
                    'result_index' => 0
                ];
            },
            $matches
        );

        $results = self::_highlight_results($results, $query);

        return $results;
    }



    /**
     * @param string $query
     * @return SearchResult[]
     */
    public static function search_for_pages($query)
    {
        $pages_db = [
            [
                'url' => URLs::admin_path('solid-affiliate-admin'),
                'title' => 'Dashboard',
                'description' => 'Solid Affiliate Dashboard',
                'content' => 'Dashboard overview of Solid Affiliate',
            ],
            [
                'url' => URLs::admin_path(Creative::ADMIN_PAGE_KEY),
                'title' => 'Creatives',
                'description' => 'Manage Creatives',
                'content' => 'Manage Creatives within the plugin',
            ],
            // Affiliates
            [
                'url' => URLs::index(Affiliate::class),
                'title' => 'Affiliates',
                'description' => 'Manage Affiliates',
                'content' => 'Manage Affiliates within the plugin',
            ],
            [
                'url' => URLs::create(Affiliate::class),
                'title' => 'Add New Affiliate',
                'description' => 'Add New Affiliate',
                'content' => 'Add New Affiliate within the plugin',
            ],
            [
                'url' => URLs::index(AffiliateGroup::class),
                'title' => 'Affiliate Groups',
                'description' => 'Manage Affiliate Groups',
                'content' => 'Manage Affiliate Groups within the plugin',
            ],
            [
                'url' => URLs::create(AffiliateGroup::class),
                'title' => 'Add New Affiliate Group',
                'description' => 'Add New Affiliate Group',
                'content' => 'Add New Affiliate Group within the plugin',
            ],
            [
                'url' => URLs::index(AffiliateCustomerLink::class),
                'title' => 'Affiliate Customer Links',
                'description' => 'Manage Affiliate Customer Links',
                'content' => 'Manage Affiliate Customer Links within the plugin',
            ],
            [
                'url' => URLs::index(AffiliateProductRate::class),
                'title' => 'Affiliate Product Rates',
                'description' => 'Manage Affiliate Product Rates',
                'content' => 'Manage Affiliate Product Rates within the plugin',
            ],
            [
                'url' => URLs::index(Visit::class),
                'title' => 'Visits',
                'description' => 'Manage Visits',
                'content' => 'Manage Visits within the plugin',
            ],
            [
                'url' => URLs::index(Referral::class),
                'title' => 'Referrals',
                'description' => 'Manage Referrals',
                'content' => 'Manage Referrals within the plugin',
            ],
            [
                'url' => URLs::create(Referral::class),
                'title' => 'Add New Referral',
                'description' => 'Add New Referral',
                'content' => 'Add New Referral within the plugin',
            ],
            [
                'url' => URLs::index(Payout::class),
                'title' => 'Payouts',
                'description' => 'Manage Payouts',
                'content' => 'Manage Payouts within the plugin',
            ],
            [
                'url' => URLs::admin_path(AdminReportsController::ADMIN_PAGE_KEY),
                'title' => 'Reports',
                'description' => 'Manage Reports',
                'content' => 'Manage Reports within the plugin',
            ],
            [
                'url' => URLs::admin_path(PayAffiliatesController::ADMIN_PAGE_KEY),
                'title' => 'Pay Affiliates',
                'description' => 'Manage Pay Affiliates',
                'content' => 'Manage Pay Affiliates within the plugin',
            ],
            [
                'url' => URLs::admin_path(CommissionRatesController::ADMIN_PAGE_KEY),
                'title' => 'Commission Rates',
                'description' => 'Manage Commission Rates',
                'content' => 'Manage Commission Rates within the plugin',
            ],
            [
                'url' => URLs::admin_path(Settings::ADMIN_PAGE_KEY),
                'title' => 'Settings',
                'description' => 'Manage Settings',
                'content' => 'Manage Settings within the plugin',
            ],
            [
                'url' => URLs::admin_path(License::ADMIN_PAGE_KEY),
                'title' => 'License',
                'description' => 'Manage License',
                'content' => 'Manage License within the plugin',
            ],
            [
                'url' => URLs::admin_path(Core::ADDONS_PAGE_SLUG),
                'title' => 'Addons',
                'description' => 'Manage Addons',
                'content' => 'Manage Addons within the plugin',
            ],
            [
                'url' => URLs::reports_coupons_path(),
                'title' => 'Reports / Coupons',
                'description' => 'View coupons report',
                'content' => 'View coupons report within Solid Affiliate reports',
            ]
        ];



        // Convert the query to an array of words
        $query_words = self::split_query_into_words($query);

        // Filter entries based on presence of any word from the query
        $matches = array_filter($pages_db, function ($entry) use ($query_words) {
            foreach ($query_words as $word) {
                if (stripos($entry['title'], $word) !== false || stripos($entry['description'], $word) !== false || stripos($entry['content'], $word) !== false) {
                    return true;
                }
            }
            return false;
        });

        $results = array_map(
            /**
             * @param array $entry
             */
            function ($entry) use ($query_words) {
                $total_match_strength = 0;
                $matched_words_count = 0;

                // calculate match strength for each word in the query
                foreach ($query_words as $word) {
                    $titleMatchStrength = substr_count(strtolower((string)$entry['title']), $word);
                    $descriptionMatchStrength = substr_count(strtolower((string)$entry['description']), $word);
                    $contentMatchStrength = substr_count(strtolower((string)$entry['content']), $word);

                    $weightForTitle = 0.6;
                    $weightForDescription = 0.25;
                    $weightForContent = 0.15;

                    $match_strength = ($weightForTitle * $titleMatchStrength + $weightForDescription * $descriptionMatchStrength + $weightForContent * $contentMatchStrength) / ((strlen((string)$entry['title']) * $weightForTitle + strlen((string)$entry['description']) * $weightForDescription + strlen((string)$entry['content']) * $weightForContent));

                    $total_match_strength += $match_strength;

                    // if word was found in either field, increment the matched words count
                    if ($titleMatchStrength > 0 || $descriptionMatchStrength > 0 || $contentMatchStrength > 0) {
                        $matched_words_count++;
                    }
                }

                // Calculate weight based on the fraction of words from the query that were found
                $query_words_present_weight = $matched_words_count / count($query_words);

                // Multiply match strength with the weight
                $final_match_strength = $total_match_strength * $query_words_present_weight;

                return [
                    'type' => self::TYPE_PAGE,
                    'url' => (string)$entry['url'],
                    'title' => (string)$entry['title'],
                    'description' => (string)$entry['description'],
                    'match_strength' => $final_match_strength,
                    'result_index' => 0
                ];
            },
            $matches
        );

        /**
         * @psalm-suppress ArgumentTypeCoercion
         */
        $results = self::_highlight_results($results, $query);

        return $results;
    }


    /**
     * @param string $query
     * @return SearchResult[]
     */
    public static function search_for_quick_links($query)
    {
        if (!in_array(strtolower(trim($query)), self::QUICK_LINK_TRIGGER_QUERIES)) {
            return [];
        }

        $quick_links_db = [
            'coupon' => [
                [
                    'url' => URLs::add_new_coupon_path(),
                    'title' => 'Add new coupon',
                    'description' => 'Add new coupon in WooCommerce',
                    'content' => 'Add new coupon in WooCommerce which can be assigned as an affiliate coupon',
                ],
                [
                    'url' => URLs::all_affiliate_coupons_path(),
                    'title' => 'All affiliate coupons',
                    'description' => 'View all affiliate coupons',
                    'content' => 'View all affiliate coupons',
                ],
            ],
            'affiliate' => [
                [
                    'url' => URLs::create(Affiliate::class),
                    'title' => 'Add new affiliate',
                    'description' => 'Add new affiliate',
                    'content' => 'Add new affiliate',
                ],
                [
                    'url' => URLs::index(Affiliate::class, false, ['status' => Affiliate::STATUS_PENDING]),
                    'title' => 'Pending affiliate applications',
                    'description' => 'View pending affiliate applications',
                    'content' => 'View pending affiliate applications',
                ],
                [
                    'url' => URLs::index(Affiliate::class),
                    'title' => 'All affiliates',
                    'description' => 'View all affiliates',
                    'content' => 'View all affiliates',
                ],
                [
                    'url' => URLs::reports_affiliates_path(),
                    'title' => 'Top affiliates',
                    'description' => 'View top affiliates',
                    'content' => 'View top affiliates',
                ],
            ]
        ];

        // get the quick links for the query. If there is no quick links for the query, return an empty array
        $results = $quick_links_db[$query] ?? [];

        return array_map(
            function ($entry) {
                return [
                    'type' => self::TYPE_QUICK_LINK,
                    'url' => (string)$entry['url'],
                    'title' => (string)$entry['title'],
                    'description' => (string)$entry['description'],
                    'match_strength' => 1.0,
                    'result_index' => 0
                ];
            },
            $results
        );
    }


    /**
     * Highlights the matching part of the string in a 'mark' tag so it can be highlighted in the UI.
     *
     * @param SearchResult[] $results
     * @param string $query
     * @return SearchResult[]
     */
    public static function _highlight_results($results, $query)
    {
        // Convert the query to an array of words
        $query_words = self::split_query_into_words($query);

        // map the response to the format we want to return. We need to wrap the matching part of the string in a 'mark' tag so it can be highlighted in the UI.
        $results = array_map(function ($entry) use ($query_words) {
            foreach ($query_words as $word) {
                $entry['title'] = preg_replace('/(' . preg_quote($word, '/') . ')/i', '<mark>$1</mark>', $entry['title']);
                $entry['description'] = preg_replace('/(' . preg_quote($word, '/') . ')/i', '<mark>$1</mark>', $entry['description']);
            }
            return $entry;
        }, $results);

        return $results;
    }






    /**
     * Sorts and slices the search results.
     *
     * @param SearchResult[] $results
     * @return SearchResult[]
     */
    private static function sort_and_slice_search_results($results)
    {
        // Sort by match_strength
        usort(
            $results,
            /**
             * @param SearchResult $a
             * @param SearchResult $b
             */
            function ($a, $b) {
                return $b['match_strength'] <=> $a['match_strength'];
            }
        );

        // Limit each result type to 3 max.
        $results = array_slice($results, 0, self::MAX_RESULTS_PER_TYPE);

        return $results;
    }

    /**
     * @return int
     */
    public static function get_search_query_count()
    {
        $query_count = (int)get_option(self::OPTION_KEY_SEARCH_QUERY_COUNT, 0);
        return $query_count;
    }


    /**
     * @return bool
     */
    public static function increment_search_query_count()
    {
        $query_count = self::get_search_query_count();
        $query_count++;

        return update_option(self::OPTION_KEY_SEARCH_QUERY_COUNT, $query_count);
    }


    /**
     * Splits the query into words.
     * 
     * Example:
     *  split_query_into_words('hello World  ') => ['hello', 'world']
     *
     * @param string $query
     * @return string[]
     */
    public static function split_query_into_words($query)
    {
        return preg_split('/\s+/', strtolower(trim($query)));
    }

    ///////////////////////////////////////////////////////////////////

    /**
     * @return string
     */
    public static function admin_root()
    {
        return self::page_heading() . self::render_solid_search_component();
    }


    /**
     * The list page for the Data Export UI.
     *
     * @return string
     */
    public static function render_solid_search_component()
    {
        ob_start();
?>

        <script>
            document.addEventListener('alpine:init', () => {
                // if solidSearch is already defined, don't do anything
                console.log('alpine:init from Solid Search')
                if (typeof Alpine.store('solidSearch') !== 'undefined') {
                    console.log('solidSearch already defined')
                    return;
                }
                // Set up Alpine.store to handle the state of our setup/onboarding wizard
                Alpine.store('solidSearch', {
                    requestCounter: 0, // This solves for race conditions when making multiple requests
                    pendingAjax: false,
                    inputQuery: '',
                    highlightIndex: 0,
                    syncedData: {
                        errors: [],
                        response: {},
                    },
                    total_results: 0,
                    postData() {
                        query = this.inputQuery;
                        // if the query is empty, reset the syncedData
                        if (query === '') {
                            this.syncedData = {
                                errors: [],
                                response: {},
                            };
                            this.total_results = 0;
                            return;
                        }
                        // if query (stripped) is just a valid modifier, don't do anything
                        modifiers = ['affiliate:', 'setting:', 'page:', 'doc:'];
                        if (modifiers.includes(query.trim())) {
                            return;
                        }

                        this.pendingAjax = true;
                        this.requestCounter += 1; // Increment the counter each time a request is made
                        const currentRequestCounter = this.requestCounter; // Save the current counter value
                        jQuery.post(ajaxurl, {
                                action: 'sld_affiliate_search',
                                query: query,
                                syncedData: this.syncedData,
                            }, (response) => {
                                // Only update the state if the response corresponds to the most recent request
                                if (currentRequestCounter === this.requestCounter) {
                                    this.syncedData = Object.assign(this.syncedData, response.data.syncedData);
                                    this.total_results = (Object.values(response.data.syncedData.response)).reduce((sum, currentArray) => sum + currentArray.length, 0);
                                    // if the response is not empty, show the results
                                    // debugger;
                                    // if (Object.keys(response.data.syncedData.response).length > 0) {
                                    //     jQuery('.sld-search-results').show();
                                    //     this.showResults = true;
                                    // }
                                }
                            })
                            .fail((error) => {
                                console.log(error);
                            })
                            .always(() => {
                                this.highlightIndex = 0;
                                this.pendingAjax = false;
                            });
                    },

                    handleSelectResultByIndex(highlightIndex) {
                        // find the result by result_index
                        let result = Object.values(this.syncedData.response).flat().find(result => result.result_index === highlightIndex);
                        if (result) {
                            window.location.href = result.url;
                        }
                    },
                });
            });

            jQuery(document).ready(function() {
                document.addEventListener('keydown', function(e) {
                    if ((e.keyCode == 75) && (e.metaKey || e.ctrlKey)) {
                        e.preventDefault();
                        let searchInput = document.querySelector('.sld-search-input');
                        if (searchInput) {
                            searchInput.focus();
                            searchInput.scrollIntoView({
                                behavior: 'smooth'
                            });
                        }
                    }
                }, false);
            });
        </script>

        <style>
            .sld-header_solid-search-container {
                margin-left: 20px;
            }

            .sld-search-field {
                display: flex;
                flex-direction: row;
                align-items: center;
                gap: 10px;
            }

            .sld-search-field p {
                line-height: 16px;
                font-size: 12px;
                font-weight: 400;
                margin-bottom: 2px;
                margin-top: 0
            }

            .sld-search-field input {
                padding-left: 36px;
                box-shadow: var(--sld-shadow-sm);
                background: url("data:image/svg+xml,%3Csvg width='18' height='18' viewBox='0 0 18 18' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M15.75 15.75L11.25 11.25M2.25 7.5C2.25 8.18944 2.3858 8.87213 2.64963 9.50909C2.91347 10.146 3.30018 10.7248 3.78769 11.2123C4.2752 11.6998 4.85395 12.0865 5.49091 12.3504C6.12787 12.6142 6.81056 12.75 7.5 12.75C8.18944 12.75 8.87213 12.6142 9.50909 12.3504C10.146 12.0865 10.7248 11.6998 11.2123 11.2123C11.6998 10.7248 12.0865 10.146 12.3504 9.50909C12.6142 8.87213 12.75 8.18944 12.75 7.5C12.75 6.81056 12.6142 6.12787 12.3504 5.49091C12.0865 4.85395 11.6998 4.2752 11.2123 3.78769C10.7248 3.30018 10.146 2.91347 9.50909 2.64963C8.87213 2.3858 8.18944 2.25 7.5 2.25C6.81056 2.25 6.12787 2.3858 5.49091 2.64963C4.85395 2.91347 4.2752 3.30018 3.78769 3.78769C3.30018 4.2752 2.91347 4.85395 2.64963 5.49091C2.3858 6.12787 2.25 6.81056 2.25 7.5Z' stroke='%239B9B9B' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E%0A") no-repeat 10px center;
            }

            .sld-search-field-hint {
                opacity: .5;
                margin-top: 8px;
            }

            sld-search-field-hint:hover {
                opacity: 1;
            }

            .sld-search-field p strong {
                font-weight: 600;
            }

            .sld-search-input {
                padding: 12px 8px;
                font-size: 13px;
                width: 400px;
                border-radius: 5px;
                border: 1px solid #ccc;
            }

            input.sld-search-input:focus-visible {
                background-color: rgba(255, 255, 255, .8);
            }

            .sld-search-modifiers {
                font-size: 11px;
                opacity: .8;
                width: 100%;
                text-align: right;
            }

            .sld-search-result {
                display: flex;
                flex-direction: row;
                gap: 4px;
                padding: 4px;
                border-radius: 8px;
                cursor: pointer;
                height: 40px;
                position: relative;
            }

            .sld-search-quick-link.highlighted {
                border: 2px solid #FFEBE2;
            }

            .sld-search-result.highlighted {
                background: #FFEBE2;
            }

            .sld-search-result::after {
                content: url("data:image/svg+xml,%3Csvg width='20' height='20' viewBox='0 0 20 20' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M4.16669 9.99999H15.8334M15.8334 9.99999L12.5 13.3333M15.8334 9.99999L12.5 6.66666' stroke='%23505062' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E%0A");
                position: absolute;
                background: rgba(255, 255, 255, .4);
                height: 20px;
                width: 20px;
                border-radius: 20px;
                padding: 2px;
                right: 10px;
                top: 15px;
                opacity: 0;
            }

            .sld-search-result:hover {
                background-color: #f0f0f0;
            }

            .sld-search-result:hover::after {
                opacity: 1;
            }


            .sld-search-info {
                padding: 20px;
                border: 1px solid #ccc;
                border-radius: 5px;
                margin-bottom: 1rem;
            }

            .sld-search-info pre {
                display: inline-block;
            }

            .sld-search-info li {
                margin: 0;
                padding: 0;
                height: 20px;
            }

            .sld-search-quick-link {
                font-size: 12px;
                padding: 4px 6px;
                background: #CFDDFF;
                display: inline-block;
                margin: 5px;
                font-weight: 400;
                cursor: pointer;
                border-radius: 4px;
            }

            .sld-search-quick-link:hover {
                background: #B8C9FF;
            }

            .sld-search-box {
                width: 400px;
                position: relative;
            }

            .sld-search-input {
                width: 100%;
            }

            .sld-search-results-no-results {
                padding: 40px;
                text-align: center;
                font-size: 16px;
                color: #8586ad;
            }

            .sld-search-results {
                width: calc(100% - 33px);
                display: block;
                position: absolute;
                z-index: 99999;
                background: #ffff;
                max-height: 500px;
                border: 1px solid var(--sld-border);
                border-radius: 4px;
                overflow-y: scroll;
                margin-top: 4px;
                box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;
            }

            .sld-search-result-type-title {
                padding: 10px;
                line-height: 13px;
                font-weight: 600;
                font-size: 12px;
                color: #8586ad;
            }

            .sld-search-result-type {
                padding: 4px;
            }

            .sld-search-result-text {
                display: flex;
                flex-direction: column;
                justify-content: center;
                width: calc(100% - 50px);
            }

            .sld-search-result-icon {
                width: 40px;
                border-radius: 4px;
                display: flex;
                justify-content: center;
                align-items: center;
            }

            .sld-search-result-icon-background {
                width: 30px;
                display: flex;
                height: 30px;
                align-items: center;
                justify-content: center;
                border-radius: 4px;
            }

            .sld-search-result-icon-background:after {
                line-height: 1;
            }

            .sld-search-result-icon-background.Page {
                background: #e79c84;
            }

            .sld-search-result-icon-background.Setting {
                background: #3fabc2;
            }

            .sld-search-result-icon-background.Affiliate {
                background: #436dff;
            }

            .sld-search-result-icon-background.Documentation {
                background: #be2a8c;
            }

            .sld-search-result-icon-background.Quicklink {
                background: #ff0707;
            }

            .sld-search-result-icon .Page:after {
                content: url("data:image/svg+xml,%3Csvg width='20' height='20' viewBox='0 0 20 20' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M5 12.5V11.6667C5 11.2246 5.17559 10.8007 5.48816 10.4882C5.80072 10.1756 6.22464 10 6.66667 10H13.3333C13.7754 10 14.1993 10.1756 14.5118 10.4882C14.8244 10.8007 15 11.2246 15 11.6667V12.5M10 7.5V10M2.5 14.1667C2.5 13.7246 2.67559 13.3007 2.98816 12.9882C3.30072 12.6756 3.72464 12.5 4.16667 12.5H5.83333C6.27536 12.5 6.69928 12.6756 7.01184 12.9882C7.3244 13.3007 7.5 13.7246 7.5 14.1667V15.8333C7.5 16.2754 7.3244 16.6993 7.01184 17.0118C6.69928 17.3244 6.27536 17.5 5.83333 17.5H4.16667C3.72464 17.5 3.30072 17.3244 2.98816 17.0118C2.67559 16.6993 2.5 16.2754 2.5 15.8333V14.1667ZM12.5 14.1667C12.5 13.7246 12.6756 13.3007 12.9882 12.9882C13.3007 12.6756 13.7246 12.5 14.1667 12.5H15.8333C16.2754 12.5 16.6993 12.6756 17.0118 12.9882C17.3244 13.3007 17.5 13.7246 17.5 14.1667V15.8333C17.5 16.2754 17.3244 16.6993 17.0118 17.0118C16.6993 17.3244 16.2754 17.5 15.8333 17.5H14.1667C13.7246 17.5 13.3007 17.3244 12.9882 17.0118C12.6756 16.6993 12.5 16.2754 12.5 15.8333V14.1667ZM7.5 4.16667C7.5 3.72464 7.67559 3.30072 7.98816 2.98816C8.30072 2.67559 8.72464 2.5 9.16667 2.5H10.8333C11.2754 2.5 11.6993 2.67559 12.0118 2.98816C12.3244 3.30072 12.5 3.72464 12.5 4.16667V5.83333C12.5 6.27536 12.3244 6.69928 12.0118 7.01184C11.6993 7.3244 11.2754 7.5 10.8333 7.5H9.16667C8.72464 7.5 8.30072 7.3244 7.98816 7.01184C7.67559 6.69928 7.5 6.27536 7.5 5.83333V4.16667Z' stroke='white' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E%0A");
            }


            .sld-search-result-icon .Setting:after {
                content: url("data:image/svg+xml,%3Csvg width='20' height='20' viewBox='0 0 20 20' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M4.99998 10C4.55795 10 4.13403 9.82441 3.82147 9.51185C3.50891 9.19929 3.33331 8.77537 3.33331 8.33334C3.33331 7.89132 3.50891 7.46739 3.82147 7.15483C4.13403 6.84227 4.55795 6.66668 4.99998 6.66668M4.99998 10C5.44201 10 5.86593 9.82441 6.17849 9.51185C6.49105 9.19929 6.66665 8.77537 6.66665 8.33334C6.66665 7.89132 6.49105 7.46739 6.17849 7.15483C5.86593 6.84227 5.44201 6.66668 4.99998 6.66668M4.99998 10V16.6667M4.99998 6.66668V3.33334M9.99998 15C9.55795 15 9.13403 14.8244 8.82147 14.5119C8.50891 14.1993 8.33331 13.7754 8.33331 13.3333C8.33331 12.8913 8.50891 12.4674 8.82147 12.1548C9.13403 11.8423 9.55795 11.6667 9.99998 11.6667M9.99998 15C10.442 15 10.8659 14.8244 11.1785 14.5119C11.4911 14.1993 11.6666 13.7754 11.6666 13.3333C11.6666 12.8913 11.4911 12.4674 11.1785 12.1548C10.8659 11.8423 10.442 11.6667 9.99998 11.6667M9.99998 15V16.6667M9.99998 11.6667V3.33334M15 7.50001C14.558 7.50001 14.134 7.32442 13.8215 7.01185C13.5089 6.69929 13.3333 6.27537 13.3333 5.83334C13.3333 5.39132 13.5089 4.96739 13.8215 4.65483C14.134 4.34227 14.558 4.16668 15 4.16668M15 7.50001C15.442 7.50001 15.8659 7.32442 16.1785 7.01185C16.4911 6.69929 16.6666 6.27537 16.6666 5.83334C16.6666 5.39132 16.4911 4.96739 16.1785 4.65483C15.8659 4.34227 15.442 4.16668 15 4.16668M15 7.50001V16.6667M15 4.16668V3.33334' stroke='white' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E%0A");
            }


            .sld-search-result-icon .Affiliate:after {
                content: url("data:image/svg+xml,%3Csvg width='20' height='20' viewBox='0 0 20 20' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M5 17.5V15.8333C5 14.9493 5.35119 14.1014 5.97631 13.4763C6.60143 12.8512 7.44928 12.5 8.33333 12.5H11.6667C12.5507 12.5 13.3986 12.8512 14.0237 13.4763C14.6488 14.1014 15 14.9493 15 15.8333V17.5M6.66667 5.83333C6.66667 6.71739 7.01786 7.56523 7.64298 8.19036C8.2681 8.81548 9.11594 9.16667 10 9.16667C10.8841 9.16667 11.7319 8.81548 12.357 8.19036C12.9821 7.56523 13.3333 6.71739 13.3333 5.83333C13.3333 4.94928 12.9821 4.10143 12.357 3.47631C11.7319 2.85119 10.8841 2.5 10 2.5C9.11594 2.5 8.2681 2.85119 7.64298 3.47631C7.01786 4.10143 6.66667 4.94928 6.66667 5.83333Z' stroke='white' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E%0A");
            }


            .sld-search-result-icon .Documentation:after {
                content: url("data:image/svg+xml,%3Csvg width='20' height='20' viewBox='0 0 20 20' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M10 15.8333C8.85986 15.1751 7.56652 14.8285 6.25 14.8285C4.93347 14.8285 3.64014 15.1751 2.5 15.8333V4.99999C3.64014 4.34173 4.93347 3.99518 6.25 3.99518C7.56652 3.99518 8.85986 4.34173 10 4.99999M10 15.8333C11.1401 15.1751 12.4335 14.8285 13.75 14.8285C15.0665 14.8285 16.3599 15.1751 17.5 15.8333V4.99999C16.3599 4.34173 15.0665 3.99518 13.75 3.99518C12.4335 3.99518 11.1401 4.34173 10 4.99999M10 15.8333V4.99999' stroke='white' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E%0A");
            }


            .sld-search-result-icon .Quicklink:after {
                content: url("data:image/svg+xml,%3Csvg width='20' height='20' viewBox='0 0 20 20' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M10.8334 2.5V8.33333H15.8334L9.16669 17.5V11.6667H4.16669L10.8334 2.5Z' stroke='white' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E%0A");
            }


            .sld-search-result-type:not(:last-child) {
                border-bottom: 1px solid var(--sld-border);
            }

            .sld-search-result-heading {
                font-size: 12px;
                font-weight: 500;
                line-height: 16px;
                white-space: nowrap;
                overflow: hidden;
                width: 100%;
                text-overflow: ellipsis;
            }

            .sld-search-result-desc {
                font-size: 11px;
                font-weight: 400;
                white-space: nowrap;
                overflow: hidden;
                width: 100%;
                text-overflow: ellipsis;
                opacity: .8;
            }

            .sld-search-result:hover .sld-search-result-desc,
            .sld-search-result-heading {
                width: calc(100% - 40px);
            }

            .quick-links {
                display: flex;
                flex-direction: row;
            }

            .sld-search-result-desc br {
                display: none;
            }

            .sld-search-input-shortcut {
                position: relative;
            }

            .sld-search-input-shortcut::after {
                content: '⌘K';
                position: absolute;
                right: 40px;
                top: 50%;
                transform: translateY(-50%);
                color: #888;
                pointer-events: none;
                font-size: 11px;
            }
        </style>

        <div x-data class='sld-search-wrapper'>
            <div class="sld-search-box" x-data="{ showResults: false}" @keydown.escape="showResults = false" @keydown.arrow-up="$store.solidSearch.highlightIndex = Math.max($store.solidSearch.highlightIndex - 1, -1)" @keydown.arrow-down="$store.solidSearch.highlightIndex = Math.min($store.solidSearch.highlightIndex + 1, $store.solidSearch.total_results - 1)" @keydown.enter="if ($store.solidSearch.highlightIndex >= 0) { $store.solidSearch.handleSelectResultByIndex($store.solidSearch.highlightIndex) };">
                <!-- Search Input -->
                <div class="sld-search-field sld-search-input-shortcut">
                    <input class="sld-search-input" placeholder="<?php _e('Search anything', 'solid-affiliate') ?>..." x-model="$store.solidSearch.inputQuery" x-ref="inputQuery" @input.debounce="$store.solidSearch.postData()" @focus="showResults = true" @blur="showResults = false" @click="showResults = true">
                    <div data-html="true" data-sld-tooltip-content="
            <div>
                <p><strong>Search Modifiers:</strong> Use these filters to narrow down your search results.</p>
                <p><code>Affiliate:</code> Search for affiliates only.</p>
                <p><code>Setting:</code> Search for settings only.</p>
                <p><code>Page:</code> Search for pages only.</p>
                <p><code>Documentation:</code> Search for documentation only.</p>
            </div>" class="sld-tooltip sld-search-field-hint" aria-expanded="false">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10.8334 2.5V8.33333H15.8334L9.16669 17.5V11.6667H4.16669L10.8334 2.5Z" stroke="#47597C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                </div>
                <!-- end - Search Input -->

                <!-- RESULTS -->
                <div class="sld-search-results" x-cloak x-show="$store.solidSearch.syncedData.response && Object.keys($store.solidSearch.syncedData.response).length > 0 && showResults">
                    <div class="sld-search-results-no-results" x-show="showResults && Object.values($store.solidSearch.syncedData.response).every(arr => arr.length === 0)">No Results</div>
                    <template x-for="(results, type) in $store.solidSearch.syncedData.response">
                        <div class="sld-search-result-type" x-show="results.length > 0">
                            <div class="sld-search-result-type-title" x-text="type + ((results.length > 1 && type !== '<?php echo self::TYPE_DOCUMENTATION; ?>') ? 's' : '')"></div>
                            <div>
                                <template x-for="result in results">
                                    <div>
                                        <!-- Quick Link Type -->
                                        <template class="quick-links" x-if="result.type === '<?php echo self::TYPE_QUICK_LINK; ?>'">
                                            <div class="sld-search-quick-link" :class="{ 'highlighted': $store.solidSearch.highlightIndex === result.result_index }" @mousedown="window.open(result.url, '_blank')">
                                                <!-- Add your icon HTML here -->
                                                <i class="sld-search-result-icon" :class="type"></i>
                                                <!-- Use x-text to display result.title -->
                                                <span x-text="result.title"></span>
                                            </div>
                                        </template>

                                        <!-- All other types -->
                                        <template x-if="result.type !== '<?php echo self::TYPE_QUICK_LINK; ?>'">
                                            <div class="sld-search-result" :class="{ 'highlighted': $store.solidSearch.highlightIndex === result.result_index }" @mousedown="window.open(result.url, '_blank')">
                                                <div class="sld-search-result-icon">
                                                    <div class="sld-search-result-icon-background" :class="type"></div>
                                                </div>
                                                <div class="sld-search-result-text">
                                                    <div class="sld-search-result-heading" x-html="result.title"></div>
                                                    <div class="sld-search-result-desc" x-html="result.description"></div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>


    <?php
        return ob_get_clean();
    }

    /**
     * Returns the HTML for the Addon page heading.
     *
     * @return string
     */
    private static function page_heading()
    {
        ob_start();
    ?>
        <?php echo AdminHeader::render(self::MENU_TITLE) ?>
        <div class="wrap">
            <h1></h1>
            <div class="addons-note">
                <?php _e("Use this tool to search for anything within Solid Affiliate.", 'solid-affiliate') ?>
            </div>
    <?php
        return ob_get_clean();
    }


    /**
     * Returns the HTML that links to the Data Export page.
     *
     * @return string
     */
    private static function link_to_admin_page()
    {
        return sprintf('<a href="%1$s">%2$s</a>', admin_url('admin.php?page=' . self::ADMIN_PAGE_KEY), __(self::MENU_TITLE, 'solid-affiliate'));
    }
}
