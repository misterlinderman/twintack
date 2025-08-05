<?php

namespace SolidAffiliate\Controllers;

use SolidAffiliate\Addons\Core;
use SolidAffiliate\Addons\StoreCredit\Addon as StoreCreditAddon;
use SolidAffiliate\Lib\PayAffiliatesFunctions;
use SolidAffiliate\Lib\ControllerFunctions;
use SolidAffiliate\Lib\CsvExport\AffiliateExport;
use SolidAffiliate\Lib\CSVExporter;
use SolidAffiliate\Lib\Email_Notifications;
use SolidAffiliate\Lib\GlobalTypes;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Utils;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\AttemptedBulkPayoutOrchestrationResult;
use SolidAffiliate\Lib\VO\BulkPayoutOrchestrationParams;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\BulkPayout;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Views\Admin\PayAffiliates\RootView;
use SolidAffiliate\Views\Shared\AdminHeader;

/**
 * PayAffiliatesController
 *
 * @psalm-import-type EnumOptionsReturnType from \SolidAffiliate\Lib\VO\SchemaEntry
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class PayAffiliatesController
{
    const ADMIN_PAGE_KEY = 'solid-affiliate-pay-affiliates';

    const POST_PARAM_SUBMIT_BULK_PAYOUT_PREVIEW = 'submit_bulk_payout_preview';
    const NONCE_SUBMIT_BULK_PAYOUT_PREVIEW = 'solid-affiliate-bulk-payout-preview-new';

    const POST_PARAM_SUBMIT_BULK_PAYOUT = 'submit_bulk_payout';
    const NONCE_SUBMIT_BULK_PAYOUT = 'solid-affiliate-create-bulk-payout';
    const POST_PARAM_SUBMIT_BULK_PAYOUT_VALUE = "Mark these Referrals as Paid and Export CSV File";
    const POST_PARAM_SUBMIT_BULK_PAYOUT_VALUE_STORE_CREDIT = "Mark these Referrals as Paid and reward Store Credit";

    const POST_PARAM_DOWNLOAD_BULK_PAYOUT_CSV = 'submit_download_bulk_payout_csv';
    const NONCE_DOWNLOAD_BULK_PAYOUT_CSV = 'solid-affiliate-download-bulk-payout-csv';


    const BULK_PAYOUT_METHOD_PAYPAL = 'paypal';
    const BULK_PAYOUT_METHOD_CSV = 'manual';
    const BULK_PAYOUT_METHOD_STORE_CREDIT = 'store_credit';

    const ALL_BULK_PAYOUT_METHODS = [
        self::BULK_PAYOUT_METHOD_PAYPAL,
        self::BULK_PAYOUT_METHOD_CSV,
        self::BULK_PAYOUT_METHOD_STORE_CREDIT
    ];

    /**
     * @return Schema<"bulk_payout_method"|"date_range_preset"|"end_date"|"payout_currency"|"start_date"|"filter_choice">
     */
    public static function pay_affiliates_tool_schema()
    {
        $refund_grace_period_number_of_days = (int)Settings::get(Settings::KEY_REFERRAL_GRACE_PERIOD_NUMBER_OF_DAYS);
        $before_refund_grace_period_value = sprintf(__('Before the refund grace period %1$s days ago)', 'solid-affiliate'), "({$refund_grace_period_number_of_days})");
        $date_range_preset_options = array_merge(
            GlobalTypes::translated_DATE_RANGE_ENUM_OPTIONS(),
            [[GlobalTypes::BEFORE_REFUND_GRACE_PERIOD_DATE_RAGE_ENUM_OPTION_KEY, $before_refund_grace_period_value]]
        );

        $entries = [
            'filter_choice' => new SchemaEntry(array(
                'type' => 'varchar',
                'form_input_type_override' => 'radio_buttons',
                'length' => 255,
                'required' => true,
                'display_name' => __('Filter referrals you want to pay', 'solid-affiliate'),
                'is_enum' => true,
                'enum_options' => [
                    [GlobalTypes::BEFORE_REFUND_GRACE_PERIOD_DATE_RAGE_ENUM_OPTION_KEY, $before_refund_grace_period_value],
                    ['all_referrals', __('All Referrals', 'solid-affiliate')],
                    ['custom_range', __('Custom Range', 'solid-affiliate')]
                ],
                'user_default' => GlobalTypes::BEFORE_REFUND_GRACE_PERIOD_DATE_RAGE_ENUM_OPTION_KEY,
                'show_on_new_form' => true,
                'show_on_preview_form' => 'hidden',
                'form_input_description' => __('Date Range for <strong>Unpaid Referrals</strong> to include in this bulk Payout.', 'solid-affiliate')
            )),
            'date_range_preset' => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 255,
                'required' => true,
                'display_name' => __('Date Range for Payouts', 'solid-affiliate'),
                'is_enum' => true,
                'enum_options' => $date_range_preset_options,
                'user_default' => GlobalTypes::BEFORE_REFUND_GRACE_PERIOD_DATE_RAGE_ENUM_OPTION_KEY,
                'show_on_new_form' => true,
                'show_on_preview_form' => 'hidden',
                'form_input_description' => __('Date Range for <strong>Unpaid Referrals</strong> to include in this bulk Payout.', 'solid-affiliate')
            )),
            'start_date' => new SchemaEntry(array(
                'type' => 'datetime',
                'display_name' => __('Start Date', 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_preview_form' => 'hidden',
                'form_input_description' => __('Start date.', 'solid-affiliate')
            )),
            'end_date' => new SchemaEntry(array(
                'type' => 'datetime',
                'display_name' => __('End Date', 'solid-affiliate'),
                'show_on_new_form' => true,
                'show_on_preview_form' => 'hidden',
                'form_input_description' => __('End date.', 'solid-affiliate')
            )),
            'bulk_payout_method' => new SchemaEntry(array(
                'type' => 'varchar',
                'form_input_type_override' => 'radio_buttons',
                'length' => 255,
                'required' => true,
                'display_name' => __('Bulk Payout Method', 'solid-affiliate'),
                'is_enum' => true,
                'enum_options' => self::payout_method_enum_options(),
                'user_default' => null,
                'show_on_new_form' => true,
                'show_on_preview_form' => 'hidden',
                'form_input_description' => __('Payout method for bulk Payout. Manual Payout will export a CSV of each Affiliate who is owed a payment, and how much is owed. PayPal method will use the PayPal integration to make the payments for you.', 'solid-affiliate')
            )),
            'payout_currency' => new SchemaEntry(array(
                'type' => 'varchar',
                'length' => 50,
                'display_name' => __('Payout Currency', 'solid-affiliate'),
                'user_default' => WooCommerceIntegration::get_current_currency(),
                'show_on_new_form' => 'hidden_and_disabled',
                'show_on_preview_form' => 'hidden',
                'form_input_description' => __('You can change your Payout currency in Settings.', 'solid-affiliate'),
                'required' => false
            )),
        ];

        return new Schema(['entries' => $entries]);
    }

    /**
     * @since 1.0.0
     *
     *
     * @return void
     */
    public static function admin_root()
    {
        $action = isset($_GET["action"]) ? (string) $_GET["action"] : "index";

        switch ($action) {
            case "index":
                PayAffiliatesController::index();
                break;
            default:
                PayAffiliatesController::index();
                break;
        }
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public static function index()
    {
        echo RootView::render();
    }

    /**
     * This is the function which gets called once the initial page of the 
     * Pay Affiliates tool is submitted. It makes no database changes, it simply
     * takes the submitted form options and redirects to the "confirm bulk payout" URL 
     * with the proper URL parameters set.
     *
     * @return void
     */
    public static function POST_create_bulk_payout_preview()
    {
        ControllerFunctions::enforce_current_user_capabilities(['read']);

        $expected_form_inputs = ['start_date', 'end_date', 'bulk_payout_method', 'payout_currency', 'date_range_preset', 'filter_choice'];

        $eitherFields = ControllerFunctions::extract_and_validate_POST_params(
            $_POST,
            $expected_form_inputs,
            PayAffiliatesController::pay_affiliates_tool_schema()
        );

        if ($eitherFields->isLeft) {
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', $eitherFields->left, [], 'admin');
        }

        switch ($eitherFields->right['filter_choice']) {
            case GlobalTypes::BEFORE_REFUND_GRACE_PERIOD_DATE_RAGE_ENUM_OPTION_KEY:
                $refund_days = (int)Settings::get(Settings::KEY_REFERRAL_GRACE_PERIOD_NUMBER_OF_DAYS);
                $preset_date_range_params = new PresetDateRangeParams([
                    'preset_date_range' => 'custom',
                    'start_date' => Utils::date_picker_time('- 50 years'),
                    'end_date' => Utils::date_picker_time("- {$refund_days} days", 0, 'Y-m-d H:i:s', false),
                ]);
                break;
            case 'all_referrals':
                $preset_date_range_params = new PresetDateRangeParams([
                    'preset_date_range' => 'all_time',
                ]);
                break;
            case 'custom_range':
                $preset_date_range_params = new PresetDateRangeParams([
                    'preset_date_range' => Validators::one_of_date_range_options((string)$eitherFields->right['date_range_preset']),
                    'start_date' => (string)$eitherFields->right['start_date'],
                    'end_date' => (string)$eitherFields->right['end_date'],
                ]);
                break;
            default:
                $preset_date_range_params = new PresetDateRangeParams([
                    'preset_date_range' => 'all_time',
                ]);
                break;
        }



        $bulkPayoutOrchestrationParams = self::bulk_payout_orchestration_params_from_post_request($_POST);

        $args_to_add = [
            'action' => 'bulk_payout_preview',
            // TODO these are the arguments which describe a Bulk payout
            'date_range_preset' => $preset_date_range_params->preset_date_range,
            'start_date' => $preset_date_range_params->computed_start_date('Y-m-d H:i:s', false),
            'end_date' => $preset_date_range_params->computed_end_date('Y-m-d H:i:s', false),
            'bulk_payout_method' => (string)$eitherFields->right['bulk_payout_method'],
            'payout_currency' => (string)$eitherFields->right['payout_currency'],
            'filter_choice' => (string)$eitherFields->right['filter_choice'],
            'logic_rules' => $bulkPayoutOrchestrationParams->filters['logic_rules']
        ];


        $admin_bulk_payout_preview_path = 'REDIRECT_BACK'; // LUCA TODO paths? (show how rails handles it)
        ControllerFunctions::handle_redirecting_and_exit($admin_bulk_payout_preview_path, [], [], 'admin', $args_to_add);
    }

    /**
     * This is the function that gets called by the Pay Affiliates tool, after final confirmation.
     *
     * @return void
     */
    public static function POST_create_bulk_payout()
    {
        ControllerFunctions::enforce_current_user_capabilities(['read']);

        ///////////////////////////////////////////////////////////////////////////////////
        // STEP 1: Parse and prepare the user supplied paramaters for the Bulk Payout.
        //         For example: 
        //          - the start and end date of the date range.
        //          - the payout (CSV, PayPal, Store Credit) method, 
        //          - etc.
        ///////////////////////////////////////////////////////////////////////////////////
        $bulkPayoutOrchestrationParams = self::bulk_payout_orchestration_params_from_post_request($_POST);
        // echo('<pre>');
        // print_r($bulkPayoutOrchestrationParams);
        // echo('</pre>');
        // var_dump($bulkPayoutOrchestrationParams->filters['logic_rules']);
        // $referrals = PayAffiliatesFunctions::find_referrals_owed_for_bulk_payout_filters($bulkPayoutOrchestrationParams->filters);
        // var_dump($referrals);
        // die();

        ///////////////////////////////////////////////////////////////////////////////////
        // STEP 2: Orchestrate the bulk paying of affiliates. Do all the things necessary
        //         to complete the Bulk Payout - but don't create a BulkPayout record yet.
        ///////////////////////////////////////////////////////////////////////////////////
        $eitherAttemptedBulkPayoutOrchestrationResult = PayAffiliatesFunctions::attempt_to_orchestrate_a_bulk_payout(
            $bulkPayoutOrchestrationParams
        );

        ///////////////////////////////////////////////////////////////////////////////////
        // STEP 3: If everything worked, create a BulkPayout record in the DB + associate the Payouts with it;
        //         otherwise, rollback the database changes.
        //        
        //         Then, redirect with proper error or success messages.
        ///////////////////////////////////////////////////////////////////////////////////
        if ($eitherAttemptedBulkPayoutOrchestrationResult->isLeft) {
            self::rollback_bulk_payout_orchestration_and_redirect_back($eitherAttemptedBulkPayoutOrchestrationResult);
        } else {
            $either_bulk_payout = self::handle_inserting_a_bulk_payout_record_into_db(
                $bulkPayoutOrchestrationParams,
                $eitherAttemptedBulkPayoutOrchestrationResult->right
            );

            if ($either_bulk_payout->isLeft) {
                self::rollback_bulk_payout_orchestration_and_redirect_back($eitherAttemptedBulkPayoutOrchestrationResult);
            } else {
                self::associate_payouts_with_bulk_payout($either_bulk_payout->right, $eitherAttemptedBulkPayoutOrchestrationResult->right->payout_ids);
                self::redirect_for_succesful_bulk_payout($either_bulk_payout->right);
            }
        }
    }


    /**
     * This function handles downloading an existing Bulk Payout as a CSV file.
     * It is called by the Pay Affiliates > Past Bulk Payouts.
     * @return void
     */
    public static function POST_download_bulk_payout_csv()
    {
        ControllerFunctions::enforce_current_user_capabilities(['read']);

        $bulk_payout_id = isset($_POST['bulk_payout_id']) ? (int)$_POST['bulk_payout_id'] : 0;

        $maybe_bulk_payout = BulkPayout::find($bulk_payout_id);

        if (is_null($maybe_bulk_payout) || ($maybe_bulk_payout->method != self::BULK_PAYOUT_METHOD_CSV)) {
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', [__('Download error.', 'solid-affiliate')], [], 'admin');
        } else {
            // TODO get mapping from BulkPayout record/database/options? This current solution assumes Referrals didn't change in the database
            $payout_ids = Payout::select_ids(['bulk_payout_id' => $bulk_payout_id]);
            $referrals = Referral::where(['payout_id' => ['operator' => 'IN', 'value' => $payout_ids]]);
            PayAffiliatesFunctions::add_pay_affiliates_csv_export_filter($referrals, $maybe_bulk_payout->currency, $maybe_bulk_payout->reference);
            $export = AffiliateExport::csv_export();
            CSVExporter::download_resource($export);
            exit(); // LUCA csv export? Error handling?
        };
    }



    ///////////////////////////////////////////////////////////////////////////
    // Private Helper Functions
    ///////////////////////////////////////////////////////////////////////////

    /**
     * Rollback and redirect back with error messages.
     *
     * @param Either<AttemptedBulkPayoutOrchestrationResult> $eitherAttemptedBulkPayoutOrchestrationResult
     * @return void
     */
    private static function rollback_bulk_payout_orchestration_and_redirect_back($eitherAttemptedBulkPayoutOrchestrationResult)
    {
        PayAffiliatesFunctions::rollback_bulk_payout_orchestration(
            $eitherAttemptedBulkPayoutOrchestrationResult->right
        );
        ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', $eitherAttemptedBulkPayoutOrchestrationResult->left, [], 'admin');
    }

    /**
     * Associate the Payouts with the BulkPayout.
     *
     * @param BulkPayout $bulk_payout
     * @param int[] $payout_ids
     * 
     * @return void
     */
    private static function associate_payouts_with_bulk_payout($bulk_payout, $payout_ids)
    {
        Payout::update_all(
            ['bulk_payout_id' => $bulk_payout->id],
            ['id' => ['operator' => 'IN', 'value' => $payout_ids]]
        );
    }

    /**
     * This function will handle the HTTP redirect for a successful Bulk Payout.
     * Depending on whether it's a CSV or PayPal payout, it will redirect to the
     * appropriate page. It may also just exit() in the case of a CSV download.
     * 
     * @param BulkPayout $bulk_payout
     * @return never
     */
    private static function redirect_for_succesful_bulk_payout($bulk_payout)
    {
        $method = $bulk_payout->method;
        $bulk_payout_id = $bulk_payout->id;
        switch ($method) {
            case PayAffiliatesController::BULK_PAYOUT_METHOD_CSV:
                ///////////////////////////////////////////////////
                // exit here to stream the CSV, which was initiated 
                // from within handle_bulk_payout_submission above
                exit();
            case PayAffiliatesController::BULK_PAYOUT_METHOD_STORE_CREDIT:
                $success_msg = __("Successfully initiated a Store Credit Bulk Payout. See Bulk Payout ID: ", 'solid-affiliate') . "{$bulk_payout_id}.";
                ControllerFunctions::handle_redirecting_and_exit(
                    URLs::index(BulkPayout::class, true),
                    [],
                    [$success_msg],
                    'admin'
                );
                /** * @psalm-suppress RedundantConditionGivenDocblockType */
            case PayAffiliatesController::BULK_PAYOUT_METHOD_PAYPAL:
                $success_msg = __("Successfully initiated a PayPal Bulk Payout. See Bulk Payout ID: ", 'solid-affiliate') . "{$bulk_payout_id}.";
                ControllerFunctions::handle_redirecting_and_exit(
                    URLs::index(BulkPayout::class, true),
                    [],
                    [$success_msg],
                    'admin'
                );
            default:
                throw new \Exception("Invalid bulk payout method: {$method}");
        }
    }
    /**
     * @param BulkPayoutOrchestrationParams $bulkPayoutOrchestrationParams
     * @param AttemptedBulkPayoutOrchestrationResult $attemptedBulkPayoutOrchestrationResult
     * 
     * @return Either<BulkPayout>
     */
    private static function handle_inserting_a_bulk_payout_record_into_db($bulkPayoutOrchestrationParams, $attemptedBulkPayoutOrchestrationResult)
    {
        // TODO $api_mode should be passed back up from the actual orchestration, not checked here later.
        $is_live_mode = (bool)Settings::get(Settings::KEY_INTEGRATIONS_PAYPAL_USE_LIVE);
        $api_mode = $is_live_mode ? BulkPayout::API_MODE_LIVE : BulkPayout::API_MODE_SANDBOX;

        $args = [
            'date_range_start' => $bulkPayoutOrchestrationParams->filters['start_date'],
            'date_range_end' => $bulkPayoutOrchestrationParams->filters['end_date'],
            'currency' => $bulkPayoutOrchestrationParams->export_args['payout_currency'],
            'method' => $bulkPayoutOrchestrationParams->export_args['bulk_payout_method'],
            'date_range' => $bulkPayoutOrchestrationParams->filters['date_range_preset'],
            'created_by_user_id' => $bulkPayoutOrchestrationParams->export_args['created_by_user_id'],
            'total_amount' => $attemptedBulkPayoutOrchestrationResult->total_amount,
            'status' => $attemptedBulkPayoutOrchestrationResult->status,
            'reference' => $attemptedBulkPayoutOrchestrationResult->reference,
            'serialized_logic_rules' => serialize($bulkPayoutOrchestrationParams->filters['logic_rules']),
            'api_mode' => $api_mode
        ];

        $res = BulkPayout::insert($args);

        return $res;
    }

    /**
     * @param array $post
     * @return BulkPayoutOrchestrationParams
     */
    public static function bulk_payout_orchestration_params_from_post_request($post)
    {
        $expected_form_inputs = ['start_date', 'end_date', 'bulk_payout_method', 'payout_currency', 'date_range_preset'];

        $eitherFields = ControllerFunctions::extract_and_validate_POST_params(
            $post,
            $expected_form_inputs,
            PayAffiliatesController::pay_affiliates_tool_schema()
        );

        if ($eitherFields->isLeft) {
            ControllerFunctions::handle_redirecting_and_exit('REDIRECT_BACK', $eitherFields->left, [], 'admin');
        }

        ////////////////////////////////////////////////////////////////
        // Get the date range that the user wants to perform payouts for
        $maybe_start_date = (string)$eitherFields->right['start_date'];
        $maybe_end_date = (string)$eitherFields->right['end_date'];
        $date_range = (string)$eitherFields->right['date_range_preset'];

        $preset_date_range_params = new PresetDateRangeParams([
            'preset_date_range' => Validators::one_of_date_range_options($date_range),
            'start_date' => $maybe_start_date,
            'end_date' => $maybe_end_date
        ]);

        $start_date = $preset_date_range_params->computed_start_date('Y-m-d H:i:s', false);
        $end_date = $preset_date_range_params->computed_end_date('Y-m-d H:i:s', false);
        ///////////////////////////////////////////////////////////////

        $bulk_payout_method = Validators::one_of(self::ALL_BULK_PAYOUT_METHODS, self::ALL_BULK_PAYOUT_METHODS[0], (string)$eitherFields->right['bulk_payout_method']);
        $payout_currency = (string)$eitherFields->right['payout_currency'];
        $current_user_id = get_current_user_id();

        // Semi hacky but seems to work.
        $mark_referrals_as_paid = (
            ($bulk_payout_method == self::BULK_PAYOUT_METHOD_PAYPAL) ||
            ($bulk_payout_method == self::BULK_PAYOUT_METHOD_STORE_CREDIT) ||
            (isset($post[PayAffiliatesController::POST_PARAM_SUBMIT_BULK_PAYOUT]) && ($post[PayAffiliatesController::POST_PARAM_SUBMIT_BULK_PAYOUT] == self::POST_PARAM_SUBMIT_BULK_PAYOUT_VALUE)));

        $only_export_csv = !$mark_referrals_as_paid;

        // parse 'logic_rules' from the $post. It is a nested array of arrays.
        $logic_rules = isset($post['logic_rules']) ? Validators::arr_of_logic_rule($post['logic_rules']) : [];


        $bulkPayoutOrchestrationParams = new BulkPayoutOrchestrationParams([
            'filters' => [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'date_range_preset' => $date_range,
                'minimum_payout_amount' => (float)Settings::get(Settings::KEY_BULK_PAYOUTS_MINIMUM_PAYOUT_AMOUNT),
                'logic_rules' => $logic_rules
            ],
            'export_args' => [
                'bulk_payout_method' => $bulk_payout_method,
                'payout_currency' => $payout_currency,
                'created_by_user_id' => $current_user_id,
                'only_export_csv' => $only_export_csv,
                'mark_referrals_as_paid' => $mark_referrals_as_paid
            ]
        ]);

        return $bulkPayoutOrchestrationParams;
    }

    /**
     * @psalm-suppress InvalidReturnType
     * @return EnumOptionsReturnType
     */
    private static function payout_method_enum_options()
    {
        // PayPal
        $is_paypal_enabled = Settings::is_paypal_integration_configured_and_enabled();
        $paypal_disabled_option = $is_paypal_enabled ? '' : 'disabled';

        $paypal_integration_settings_link = URLs::settings(Settings::TAB_INTEGRATIONS);
        $paypal_label_enabled = '<strong>' . __('PayPal Bulk Payout', 'solid-affiliate') . '</strong> <em>' . __('Automatic PayPal Bulk Payout through the PayPal integration.', 'solid-affiliate') . '</em>';
        $paypal_label_disabled = "<strong>" . __('PayPal Bulk Payout', 'solid-affiliate') . "</strong>. <em>" . __('Currently Disabled. To use, please configure and ', 'solid-affiliate') . "<a href='{$paypal_integration_settings_link}'>" . __('Enable Solid Affiliate + PayPal Integration', 'solid-affiliate') . "</a>.</em>";
        $paypal_label = $is_paypal_enabled ? $paypal_label_enabled : $paypal_label_disabled;

        // Store Credit
        $is_store_credit_enabled = Core::is_addon_enabled(StoreCreditAddon::ADDON_SLUG);
        $store_credit_disabled_option = $is_store_credit_enabled ? '' : 'disabled';
        $addons_link = URLs::admin_path(Core::ADDONS_PAGE_SLUG);
        $store_credit_label_enabled = __('Store Credit through the integrated store credit addon.', 'solid-affiliate');
        $store_credit_label_disabled = __('Currently Disabled', 'solid-affiliate') . '</br>' . __('To use, please configure and ', 'solid-affiliate') . "<a href='{$addons_link}'>" . __('Enable Solid Affiliate Store Credit addon', 'solid-affiliate') . "</a>.";
        $store_credit_label = $is_store_credit_enabled ? $store_credit_label_enabled : $store_credit_label_disabled;

        $tuples = [
            [self::BULK_PAYOUT_METHOD_CSV, '<strong>' . __('Manual', 'solid-affiliate') . '</strong> <em>' . __('Export a CSV of the Payout data', 'solid-affiliate') . '</em>.'],
            [self::BULK_PAYOUT_METHOD_PAYPAL, $paypal_label, $paypal_disabled_option],
            [self::BULK_PAYOUT_METHOD_STORE_CREDIT, $store_credit_label, $store_credit_disabled_option],
        ];

        /**
         * @psalm-suppress InvalidReturnStatement
         */
        return $tuples;
    }
}
