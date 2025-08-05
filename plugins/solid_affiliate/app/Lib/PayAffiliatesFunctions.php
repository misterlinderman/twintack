<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Addons\StoreCredit\Addon;
use SolidAffiliate\Controllers\PayAffiliatesController;
use SolidAffiliate\Lib\AffiliateRegistrationForm\AffiliateRegistrationFormFunctions;
use SolidAffiliate\Lib\CsvExport\AffiliateExport;
use SolidAffiliate\Lib\CsvExport\CsvExportFunctions;
use SolidAffiliate\Lib\CSVExporter;
use SolidAffiliate\Lib\PayPal;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\BulkPayout;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Lib\VO\AttemptedBulkPayoutOrchestrationResult;
use SolidAffiliate\Lib\VO\BulkPayoutOrchestrationParams;
use SolidAffiliate\Lib\VO\CsvColumn;
use SolidAffiliate\Lib\VO\CsvExport;
use SolidAffiliate\Lib\VO\PresetDateRangeParams;
use SolidAffiliate\Models\StoreCreditTransaction;

/**
 * @psalm-import-type BulkPayoutOrchestrationFiltersType from \SolidAffiliate\Lib\VO\BulkPayoutOrchestrationParams
 */
class PayAffiliatesFunctions
{
    /**
     * A filter that receives the default AffiliateExport CsvExport interface, and returns the modified interface that only includes the Affiliates with referrals scoped to the epxort, and merges in the extra Pay Affiliates columns.
     *
     * @param Referral[] $referrals
     * @param string $payout_currency
     * @param string $filename
     *
     * @return void
     */
    public static function add_pay_affiliates_csv_export_filter($referrals, $payout_currency, $filename)
    {
        add_filter(
            AffiliateExport::CSV_EXPORT_FILTER,
            /** @param CsvExport $export */
            function ($export) use ($referrals, $payout_currency, $filename) {
                return PayAffiliatesFunctions::paypal_payout_export_from_affiliate_export(
                    $export,
                    $referrals,
                    $payout_currency,
                    $filename
                );
            }
        );
    }

    /**
     * The CsvExport used when exporting Affiliates for the pay affiliates data.
     *
     * @param CsvExport $export
     * @param Referral[] $referrals
     * @param string $payout_currency
     * @param string $filename
     *
     * @return CsvExport
     */
    public static function paypal_payout_export_from_affiliate_export($export, $referrals, $payout_currency, $filename)
    # TODO: Add an "id" like property to the CsvColumn value object so that it can be indentified using something other than a translated name. Once this is done we can remove the functions that return the display names.
    {
        // Setup the payout specific columns that are already not on Affiliate that PayPal expects.
        $names = [
            __('Payment Email', 'solid-affiliate'),
            __('Amount', 'solid-affiliate'),
            __('Currency', 'solid-affiliate'),
            __('Affiliate ID', 'solid-affiliate'),
            __('Message', 'solid-affiliate'),
            __('Wallet', 'solid-affiliate'),
            __('Social Feed Privacy', 'solid-affiliate'),
            __('Holler URL', 'solid-affiliate'),
            __('Logo URL', 'solid-affiliate')
        ];
        [$email_name, $amount_name, $currency_name, $id_name, $message_name, $wallet_name, $social_feed_priv_name, $holler_name, $logo_name] = array_map(
            /** @param  string $name */
            function ($name) {
                return '[PayPal] ' . $name;
            },
            $names
        );

        $payout_columns = [
            new CsvColumn([
                'name' => $email_name,
                'format_callback' =>
                /** @param \SolidAffiliate\Lib\MikesDataModel $affiliate */
                static function ($affiliate) {
                    if ($affiliate instanceof Affiliate) {
                        return $affiliate->payment_email;
                    } else {
                        return AffiliateExport::DEFAULT_SKIPPED_VALUE;
                    }
                },
            ]),
            new CsvColumn([
                'name' => $amount_name,
                'format_callback' =>
                /** @param \SolidAffiliate\Lib\MikesDataModel $affiliate */
                static function ($affiliate) use ($referrals) {
                    if ($affiliate instanceof Affiliate) {
                        $id = $affiliate->id;
                        $mapping = self::create_affiliate_id_to_referrals_owed_mapping_from_referrals($referrals, $id);
                        $total_comissions = $mapping[$id]['commission_amount'];
                        return Validators::currency_amount_string($total_comissions);
                    } else {
                        return AffiliateExport::DEFAULT_SKIPPED_VALUE;
                    }
                },
            ]),
            new CsvColumn([
                'name' => $currency_name,
                'format_callback' =>
                /** @param \SolidAffiliate\Lib\MikesDataModel $_affiliate */
                static function ($_affiliate) use ($payout_currency) {
                    return $payout_currency;
                },
            ]),
            new CsvColumn([
                'name' => $id_name,
                'format_callback' =>
                /** @param \SolidAffiliate\Lib\MikesDataModel $affiliate */
                static function ($affiliate) {
                    return CsvExportFunctions::default_format_validation($affiliate->id);
                },
            ]),
            new CsvColumn([
                'name' => $message_name,
                'format_callback' =>
                /** @param \SolidAffiliate\Lib\MikesDataModel $_affiliate */
                static function ($_affiliate) {
                    return __('Solid Affiliate Payout', 'solid-affiliate');
                },
            ]),
            new CsvColumn([
                'name' => $wallet_name,
                'format_callback' =>
                /** @param \SolidAffiliate\Lib\MikesDataModel $_affiliate */
                static function ($_affiliate) {
                    return 'PayPal';
                },
            ]),
            new CsvColumn([
                'name' => $social_feed_priv_name,
                'format_callback' =>
                /** @param \SolidAffiliate\Lib\MikesDataModel $_affiliate */
                static function ($_affiliate) {
                    return '';
                },
            ]),
            new CsvColumn([
                'name' => $holler_name,
                'format_callback' =>
                /** @param \SolidAffiliate\Lib\MikesDataModel $_affiliate */
                static function ($_affiliate) {
                    return '';
                },
            ]),
            new CsvColumn([
                'name' => $logo_name,
                'format_callback' =>
                /** @param \SolidAffiliate\Lib\MikesDataModel $_affiliate */
                static function ($_affiliate) {
                    return '';
                },
            ]),
        ];

        // Filter out the computed columns and sort them in the order that PayPal expects.
        $all_columns = array_reduce(
            $export->columns,
            /**
             * @param CsvColumn[] $pc
             * @param CsvColumn $col
             */
            function ($pc, $col) {
                array_push($pc, $col);
                return $pc;
            },
            $payout_columns
        );
        $filtered_columns = array_filter($all_columns, function ($col) {
            return !in_array($col->name, [AffiliateExport::total_visits_name(), AffiliateExport::total_referrals_name()]);
        });

        // Return the modified CsvExport, with new and ordered column and only affiliates with unpaid referrals, interface the filter expects.
        $affiliate_ids = array_map(function ($ref) {
            return $ref->affiliate_id;
        }, $referrals);
        return new CsvExport([
            'resource_name' => '',
            'sub_heading' => '',
            'nonce_download' => '',
            'post_param' => '',
            'filename' => $filename,
            'record_count_callback' => static function () use ($affiliate_ids) {
                return count($affiliate_ids);
            },
            'record_query_callback' =>
            static function () use ($affiliate_ids) {
                $affs = Affiliate::find_many($affiliate_ids);
                return AffiliateRegistrationFormFunctions::flatten_custom_data_into_affiliates($affs);
            },
            'columns' => $filtered_columns
        ]);
    }

    /**
     * Finds Affiliates that are owed money for a given
     * date range of Referrals.
     * 
     * @param string $start
     * @param string $end
     *
     * @return \SolidAffiliate\Models\Affiliate[]
     */
    public static function find_affiliates_owed_for_date_range($start, $end)
    {
        $unpaid_referrals = Referral::find_unpaid_for_date_range($start, $end);
        $unpaid_affiliate_ids = array_map(
            /** 
             * @param \SolidAffiliate\Models\Referral $referral
             * 
             * @return int
             */
            function ($referral) {
                return (int) $referral->affiliate_id;
            },
            $unpaid_referrals
        );

        $affiliates = Affiliate::where(['id' => ['operator' => 'IN', 'value' => $unpaid_affiliate_ids]]);

        return $affiliates;
    }

    /**
     * Counts the number of Affiliates that are owed money for a given
     * date range of Referrals.
     * 
     * @param string $start
     * @param string $end
     *
     * @return int
     */
    public static function count_affiliates_owed_for_date_range($start, $end)
    {
        $affiliates = self::find_affiliates_owed_for_date_range($start, $end);

        return count($affiliates);
    }

    /**
     * Finds the total money owed to Affiliates for a given
     * date range of Referrals.
     * 
     * @param string $start
     * @param string $end
     *
     * @return float
     */
    public static function total_amount_owed_for_date_range($start, $end)
    {
        $total = Referral::total_owed_for_date_range($start, $end);

        return $total;
    }

    /**
     * Finds the number of Referrals await payment for a given date range
     * 
     * @param string $start
     * @param string $end
     *
     * @return \SolidAffiliate\Models\Referral[]
     */
    public static function find_referrals_owed_for_date_range($start, $end)
    {
        return Referral::find_unpaid_for_date_range($start, $end);
    }

    /**
     * @param BulkPayoutOrchestrationFiltersType $filters
     * 
     * @return \SolidAffiliate\Models\Referral[]
     */
    public static function find_referrals_owed_for_bulk_payout_filters($filters)
    {
        // TODO these PresetDateRangeFilters are pretty annoying.
        $preset_date_range_params = new PresetDateRangeParams([
            'preset_date_range' => Validators::one_of_date_range_options($filters['date_range_preset']),
            'start_date' => $filters['start_date'],
            'end_date' => $filters['end_date'],
        ]);

        $start_date = $preset_date_range_params->computed_start_date();
        $end_date = $preset_date_range_params->computed_end_date();

        $where_clauses = [
            'status' => Referral::STATUS_UNPAID,
            'created_at' => [
                'operator' => 'BETWEEN',
                'min' => $start_date,
                'max' => $end_date,
            ],
        ];

        /////////////////////////////////////   
        // Map LogicRules to where clauses //
        /////////////////////////////////////
        $logic_rules = isset($filters['logic_rules']) ? $filters['logic_rules'] : [];

        $where_clauses_from_logic_rules = [];

        foreach ($logic_rules as $rule) {
            $operator = $rule['operator'] == 'include' ? 'IN' : 'NOT IN';

            /////////////////////////////
            // if field is affiliate_group_id then we need to join to the Affiliates table.
            if ($rule['field'] == 'affiliate_group_id') {
                $affiliate_ids = Affiliate::select_ids(
                    ['affiliate_group_id' => ['operator' => 'IN', 'value' => $rule['value']]]
                );

                $where_clauses_from_logic_rules['affiliate_id'] = [
                    'operator' => $operator,
                    'value' => $affiliate_ids,
                ];
            } elseif ($rule['field'] == 'tag') {
                $tag_names = $rule['value'];

                // map tag names to affiliate ids, and then flatten the array of affiliate ids

                $arrays_of_affiliate_ids_that_match_tags = array_map(function ($tag_name) {
                    $affiliates = Tags::affiliates_for_tag_name((string)$tag_name);
                    $affiliate_ids = array_map(function ($affiliate) {
                        return $affiliate->id;
                    }, $affiliates);

                    return $affiliate_ids;
                }, $tag_names);

                $flattened_affiliate_ids = array_reduce($arrays_of_affiliate_ids_that_match_tags, function ($carry, $item) {
                    /** @psalm-suppress MixedArgument */
                    return array_merge($carry, $item);
                }, []);


                $where_clauses_from_logic_rules['affiliate_id'] = [
                    'operator' => $operator,
                    'value' => $flattened_affiliate_ids,
                ];
            } else {
                // if value is 'referral_id', then we need to use just 'id'
                $field = $rule['field'] == 'referral_id' ? 'id' : $rule['field'];

                $value = $rule['value'];

                $where_clauses_from_logic_rules[$field] = [
                    'operator' => $operator,
                    'value' => $value
                ];
            }
        }
        // And then, merge in the logic rules
        $where_clauses = array_merge($where_clauses, $where_clauses_from_logic_rules);
        /////////////////////////////////////

        /////////////////////////////////////
        // Handle the minimum_payout_amount
        // We have to group the referrals by affiliate_id, and then sum the commission_amount
        // If the sum is less than the minimum_payout_amount, then we don't want to include any that affiliate's referrals.
        $minimum_payout_amount = isset($filters['minimum_payout_amount']) ? $filters['minimum_payout_amount'] : 0;

        if ($minimum_payout_amount > 0) {
            $referrals = Referral::where($where_clauses);
            $referrals_by_affiliate_id = [];
            foreach ($referrals as $referral) {
                if (!isset($referrals_by_affiliate_id[$referral->affiliate_id])) {
                    $referrals_by_affiliate_id[$referral->affiliate_id] = [];
                }
                $referrals_by_affiliate_id[$referral->affiliate_id][] = $referral;
            }

            $referrals_to_include = [];
            foreach ($referrals_by_affiliate_id as $_affiliate_id => $affiliate_referrals) {
                $total_commission_amount = array_reduce($affiliate_referrals, function ($carry, $referral) {
                    /** @var \SolidAffiliate\Models\Referral $referral */
                    return (float)$carry + (float)$referral->commission_amount;
                }, 0);

                // round the total_commission_amount to 2 decimal places to that it matches our UI
                $total_commission_amount = round($total_commission_amount, 2);

                if ($total_commission_amount >= $minimum_payout_amount) {
                    $referrals_to_include = array_merge($referrals_to_include, $affiliate_referrals);
                }
            }

            return $referrals_to_include;
        } else {
            return Referral::where($where_clauses);
        }
        // END - Handle the minimum_payout_amount
        //////////////////////////////////////////

    }

    /**
     * Finds any Referrals that are still owed payment and are older than the 
     * configured refund grace period.
     *
     * @return array{grace_period_days: int, amount_owed_formatted: string, affiliates_owed_count: int}
     */
    public static function data_for_grace_period_referrals()
    {
        $grace_period_days = (int)Settings::get(Settings::KEY_REFERRAL_GRACE_PERIOD_NUMBER_OF_DAYS);
        $start_date = Utils::date_picker_time('- 50 years');
        $end_date = Utils::date_picker_time("- {$grace_period_days} days");

        $amount_owed = PayAffiliatesFunctions::total_amount_owed_for_date_range($start_date, $end_date);
        $amount_owed_formatted = Formatters::money($amount_owed);
        if (empty($amount_owed)) {
            $affiliates_owed = [];
        } else {
            $affiliates_owed = PayAffiliatesFunctions::find_affiliates_owed_for_date_range($start_date, $end_date);
        }
        $affiliates_owed_count = count($affiliates_owed);

        return [
            'grace_period_days' => $grace_period_days,
            'amount_owed_formatted' => $amount_owed_formatted,
            'affiliates_owed_count' => $affiliates_owed_count
        ];
    }

    /**
     * Finds the number of Referrals await payment for a given date range
     * 
     * @param string $start
     * @param string $end
     *
     * @return int
     */
    public static function count_referrals_owed_for_date_range($start, $end)
    {
        $referrals = self::find_referrals_owed_for_date_range($start, $end);

        return count($referrals);
    }


    /**
     * Given the input paramaters, do the following:
     *   [just export the payout CSV]
     *   or 
     *   [create Payout records and mark the associated Referrals as paid]
     *    and 
     *   [conditionally handle PayPal (hit API) or Store Credit (issue credit) or CSV method (export CSV)] 
     *   then
     *   return AttemptedBulkPayoutOrchestrationResult to explain what happened, and which Referrals + Payouts were affected.
     * 
     * @param BulkPayoutOrchestrationParams $params
     *
     * @return Either<AttemptedBulkPayoutOrchestrationResult>
     */
    public static function attempt_to_orchestrate_a_bulk_payout($params)
    {
        ///////////////////////////////////////////////////////////////////////////////
        // Here we do extra checks for PayPal payouts because we want to be super solid
        if ($params->export_args['bulk_payout_method'] == PayAffiliatesController::BULK_PAYOUT_METHOD_PAYPAL) {
            ///////////////////////////////////////////////////////////////////////////////
            // Here we check if the user is attempting to make a PayPal Bulk Payout while 
            // the PayPal integration is disable. 
            if (!Settings::is_paypal_integration_configured_and_enabled()) {
                return new Either(
                    [__('PayPal Integration needs to be configured and enabled.', 'solid-affiliate')],
                    new AttemptedBulkPayoutOrchestrationResult(
                        [
                            "message" => '',
                            "status" => BulkPayout::FAIL_STATUS,
                            "reference" => '',
                            'payout_ids' => [],
                            'referral_ids' => [],
                            'total_amount' => 0.0
                        ]
                    ),
                    false
                );
            }
            ///////////////////////////////////////////////////////////////////////////////
            // check to see if there are any missing or invalid email addresses
            // for each affiliate that is being paid. (affiliate->payment_email)
            $maybe_error = self::verify_affiliates_have_valid_payment_emails($params);
            if (!is_null($maybe_error)) {
                return $maybe_error;
            }
        }


        ///////////////////////////////////////////////////////////////////////////////
        // Here we check if the user is attempting to make only CSV export Bulk Payout
        // and if so, we just export the CSV exit.
        if ($params->export_args['only_export_csv']) {
            $referrals = self::find_referrals_owed_for_bulk_payout_filters($params->filters);
            $date = date('m-d-Y');
            $filename = "solid-affiliate-bulk-payout-{$date}.csv";
            self::add_pay_affiliates_csv_export_filter($referrals, $params->export_args['payout_currency'], $filename);
            $export = AffiliateExport::csv_export();
            CSVExporter::download_resource($export);
            exit();
        }

        ///////////////////////////////////////////////////////////////////////////////
        // For the given filters/params, find all the Referrals that are still owed payment.
        // Then, create a Payout record for each Affiliate, and then associate the Referrals to the Payouts.
        list($maybe_payouts, $created_payout_ids, $updated_referral_ids) = self::create_payout_records_and_associate_referrals_to_them_for_bulk_payout_orchestration_params(
            $params
        );

        if ($maybe_payouts->isLeft) {
            return new Either(
                [__('There was an issues creating the payout records.', 'solid-affiliate')],
                new AttemptedBulkPayoutOrchestrationResult(
                    [
                        "message" => '',
                        "status" => BulkPayout::FAIL_STATUS,
                        "reference" => '',
                        'payout_ids' => $created_payout_ids,
                        'referral_ids' => $updated_referral_ids,
                        'total_amount' => 0.0
                    ]
                ),
                false
            );
        } else {
            $payouts = $maybe_payouts->right;
            // TODO this function handle_side_effects.... does not properly return referral and payout ids in the response object
            $either_bulk_payout_result = self::handle_side_effects_of_bulk_payout_orchestration_such_as_paypal_or_store_credit_or_csv(
                $params,
                $payouts
            );

            if ($either_bulk_payout_result->isRight && $params->export_args['mark_referrals_as_paid']) {
                // $referral_ids_to_mark_as_paid = $either_bulk_payout_result->right->referral_ids;
                // $either_updated_referral_ids = self::mark_referral_ids_as_paid($referral_ids_to_mark_as_paid);
                $either_updated_referral_ids = self::mark_referral_ids_as_paid($updated_referral_ids);
                $updated_referral_ids = $either_updated_referral_ids->right;
            }

            ///////////////////////////////////////////////////////////////////////////////
            // Return the results of the successful Bulk Payout Orchestration.
            $either_bulk_payout_result->right->referral_ids = $updated_referral_ids;
            $either_bulk_payout_result->right->payout_ids = $created_payout_ids;

            return $either_bulk_payout_result;
        }
    }

    /**
     * Handles creating a bulk payout via the store_credit method, coordinating everything that needs to happen.
     *
     * @param Payout[] $payouts
     *
     * @return Either<AttemptedBulkPayoutOrchestrationResult>
     */
    private static function handle_store_credit_bulk_payout($payouts)
    {
        $payout_ids = array_map(function ($p) {
            return $p->id;
        }, $payouts);

        $either_store_credit_transactions = array_map(
            /** @param Payout $p */
            function ($p) {
                $affiliate_id = $p->affiliate_id;
                $amount = $p->amount;
                $description = "Store credit earned through payout #{$p->id}";
                return Addon::add_store_credit_to_affiliate($affiliate_id, $amount, $p, $description);
            },
            $payouts
        );

        $created_transaction_ids = array_map(
            /** @param Either<StoreCreditTransaction> $either_transaction */
            function ($either_transaction) {
                return $either_transaction->right->id;;
            },
            array_filter(
                $either_store_credit_transactions,
                /** 
                 * @param Either<StoreCreditTransaction> $e
                 * @return bool
                 */
                function ($e) {
                    return $e->isRight;
                }
            )
        );


        // check if any of the store credit transactions failed
        $store_credit_transactions_failed = array_filter(
            $either_store_credit_transactions,
            function ($either) {
                return $either->isLeft;
            }
        );

        $some_store_credit_transactions_failed = count($store_credit_transactions_failed) > 0;

        if ($some_store_credit_transactions_failed) {
            ////////////////////////////////////////////////////////
            // ROLLBACK
            foreach ($created_transaction_ids as $transaction_id) {
                StoreCreditTransaction::delete($transaction_id);
            }
            ////////////////////////////////////////////////////////

            return new Either(
                [__('There was an issue processing your Store Credit bulk payout.', 'solid-affiliate')],
                new AttemptedBulkPayoutOrchestrationResult(
                    ["message" => '', "status" => BulkPayout::FAIL_STATUS, "reference" => '', 'payout_ids' => [], 'referral_ids' => [], 'total_amount' => 0.0]
                ),
                false
            );
        } else {
            /////////////////////////////////////////////////
            // Send store credit emails if enabled
            Email_Notifications::async_email_store_credit_transaction_notification($created_transaction_ids);
            /////////////////////////////////////////////////
            return new Either(
                [''],
                new AttemptedBulkPayoutOrchestrationResult([
                    "message" => __("Your bulk payout was processed and store credit was distributed to the appropriate affiliates", 'solid-affiliate'),
                    "status" => BulkPayout::SUCCESS_STATUS,
                    "reference" => 'not-applicable',
                    "payout_ids" => $payout_ids,
                    'total_amount' => Payout::sum_amount($payouts),
                    "referral_ids" => []
                ]),
                true
            );
        }
    }


    /** 
     * @param BulkPayoutOrchestrationFiltersType $bulk_payout_filters
     * @param string $payout_currency
     * @param string $filename
     * 
     * @return null
     */
    public static function generate_bulk_payout_csv_for_bulk_payout_filters($bulk_payout_filters, $payout_currency, $filename)
    {
        $referrals = self::find_referrals_owed_for_bulk_payout_filters($bulk_payout_filters);
        self::add_pay_affiliates_csv_export_filter($referrals, $payout_currency, $filename);
        $export = AffiliateExport::csv_export();
        CSVExporter::download_resource($export);
    }

    /**
     * Finds all referrals that are owed for a given date range.
     * Handles creating the Payout records, one for each affiliate.
     * Then, associates the Payout records with the Referral records.
     * 
     * @param BulkPayoutOrchestrationParams $params
     *
     * @return array{0: Either<Payout[]>, 1: int[], 2: int[]} - [Either<payouts>, $created_payout_ids, $updated_referral_ids]
     */
    public static function create_payout_records_and_associate_referrals_to_them_for_bulk_payout_orchestration_params($params)
    {
        $mapping = self::create_affiliate_id_to_referrals_owed_mapping_from_bulk_payout_filters($params->filters);
        $bulk_payout_method = $params->export_args['bulk_payout_method'];
        $created_by_user_id = $params->export_args['created_by_user_id'];

        // In this shape:
        // [
        //  [Either<Payout>, [1,2,3]]
        // ]
        $array_of_tuples_either_payout_and_updated_referral_ids = array_map(
            function ($affiliate_id) use ($mapping, $bulk_payout_method, $created_by_user_id) {
                $payout_description = $mapping[$affiliate_id];

                $either_payout = Payout::insert([
                    'affiliate_id' => $affiliate_id,
                    'created_by_user_id' => $created_by_user_id,
                    'payout_method' => $bulk_payout_method,
                    'amount' => $payout_description['commission_amount'],
                    'status' => Referral::STATUS_PAID,
                ]);

                $updated_referral_ids = [];
                if ($either_payout->isRight) {
                    $payout = $either_payout->right;
                    $payout_id = $payout->id;
                    $referral_ids = $payout_description['referral_ids'];

                    Referral::update_all(
                        ['payout_id' => $payout_id],
                        ['id' => ['operator' => 'IN', 'value' => $referral_ids]]
                    );

                    $updated_referral_ids = array_merge($updated_referral_ids, $referral_ids);
                }

                return [$either_payout, $updated_referral_ids];
            },
            array_keys($mapping)
        );

        //////////////////////////////////////////////////
        // Get the updated Referral IDS
        //////////////////////////////////////////////////
        $array_of_id_arrays = array_map(function ($tuple) {
            return $tuple[1];
        }, $array_of_tuples_either_payout_and_updated_referral_ids);

        $updated_referral_ids = array_reduce(
            $array_of_id_arrays,
            /**
             * @param array $all_ids
             * @param array<int> $new_ids
             */
            function ($all_ids, $new_ids) {
                return array_merge($all_ids, $new_ids);
            },
            []
        );

        $updated_referral_ids = Validators::array_of_int($updated_referral_ids);
        //////////////////////////////////////////////////

        $maybe_payouts = array_map(function ($tuple) {
            return $tuple[0];
        }, $array_of_tuples_either_payout_and_updated_referral_ids);

        $lefts = array_filter($maybe_payouts, function ($either_payout) {
            return $either_payout->isLeft;
        });

        $rights = array_filter($maybe_payouts, function ($either_payout) {
            return $either_payout->isRight;
        });

        $created_payout_ids = array_map(function ($right_of_payout) {
            return $right_of_payout->right->id;
        }, $rights);

        if (!empty($lefts)) {
            $either = new Either([__('At least one Payout creation failed.', 'solid-affiliate')], [], false);
            return [$either, $created_payout_ids, $updated_referral_ids];
        } else {
            /** @var array<Payout> $payouts */
            $payouts = array_map(function ($payout) {
                return $payout->right;
            }, $maybe_payouts);

            $either = new Either([''], $payouts, true);

            return [$either, $created_payout_ids, $updated_referral_ids];
        }
    }


    /**
     * Marks referrals as paid in the DB.
     * 
     * @param int[] $referral_ids
     *
     * 
     * @return Either<int[]> - The IDs of the Referrals marked as paid.
     */
    public static function mark_referral_ids_as_paid($referral_ids)
    {
        $referrals = Referral::where(['id' => ['operator' => 'IN', 'value' => $referral_ids]]);
        Referral::updateInstances($referrals, ['status' => Referral::STATUS_PAID]);

        $referral_ids = array_map(function ($referral) {
            return (int)$referral->id;
        }, $referrals);

        return new Either([''], $referral_ids, true);
    }


    /**
     * Creates a mapping of affiliate_id => referral_ids and commission_amount for a given date range.
     * 
     * Example:
     *   [
     *       1 => [
     *           'referral_ids' => [1,2,3],
     *           'commission_amount' => 234.32
     *       ]
     *   ]
     * @param BulkPayoutOrchestrationFiltersType $bulk_payout_filters
     *
     * @return array<int, array{referral_ids: array<int>, commission_amount: float, order_amount: float, payment_email: string}>
     */
    public static function create_affiliate_id_to_referrals_owed_mapping_from_bulk_payout_filters($bulk_payout_filters)
    {
        $referrals = self::find_referrals_owed_for_bulk_payout_filters($bulk_payout_filters);

        $res = self::create_affiliate_id_to_referrals_owed_mapping_from_referrals($referrals);

        return $res;
    }

    /**
     * Creates a mapping of affiliate_id => referral_ids and commission_amount for a given date range.
     * 
     * @param Referral[] $referrals
     * @param int|null $scoped_to_affiliate_id
     *
     * @return array<int, array{referral_ids: array<int>, commission_amount: float, order_amount: float, payment_email: string}>
     */
    public static function create_affiliate_id_to_referrals_owed_mapping_from_referrals($referrals, $scoped_to_affiliate_id = null)
    {
        $res = array_reduce(
            $referrals,
            /**
             * @param array<int, array{referral_ids: array<int>, commission_amount: float, order_amount: float, payment_email: string}> $output
             * @param \SolidAffiliate\Models\Referral $referral
             */
            function ($output, $referral) use ($scoped_to_affiliate_id) {
                $affiliate_id = (int) $referral->affiliate_id;

                if (!is_null($scoped_to_affiliate_id) && $affiliate_id !== (int)$scoped_to_affiliate_id) {
                    return $output;
                }

                if (isset($output[$affiliate_id])) {
                    $output[$affiliate_id]['referral_ids'][] = (int) $referral->id;
                    $output[$affiliate_id]['commission_amount'] += (float) $referral->commission_amount;
                    $output[$affiliate_id]['commission_amount'] = Validators::currency_amount_float($output[$affiliate_id]['commission_amount']);
                    $output[$affiliate_id]['order_amount'] += (float) $referral->order_amount;
                } else {
                    $maybe_affiliate = Affiliate::find($affiliate_id);
                    if (is_null($maybe_affiliate)) {
                    } else {
                        $payment_email = $maybe_affiliate->payment_email;

                        $output[$affiliate_id] = [
                            'referral_ids' => [(int) $referral->id],
                            'commission_amount' => Validators::currency_amount_float((float) $referral->commission_amount),
                            'order_amount' => (float) $referral->order_amount,
                            'payment_email' => $payment_email
                        ];
                    }
                }

                return $output;
            },
            array()
        );

        return $res;
    }

    /**
     * Attempts to Rollback any DB changes from a Pay Affiliates flow. For example, if a PayPal
     * API request fails, we need to rollback any half-way done changes to the DB (such as marking Referrals as 'paid').
     * 
     * DATABASE CHANGES
     * - Payout Records get created
     * - Referrals payout_id set
     * - Referrals status set
     * - BulkPayout created
     * - Payouts bulk_payout_id set
     * 
     * To ROLLBACK we need $payout_ids, $referral_ids, $bulk_payout_id
     * - Delete the appropriate Payout records (payout_ids from the AttemptedBulkPayoutOrchestrationResult)
     * - unset the appropriate Referral->payout_id
     * - unset the appropriate Referral->payout_id
     * - Delete the BulkPayout
     *
     * @param AttemptedBulkPayoutOrchestrationResult $attempted_bulk_payout_orchestration_result
     * @param int $bulk_payout_id
     * @return void
     */
    public static function rollback_bulk_payout_orchestration($attempted_bulk_payout_orchestration_result, $bulk_payout_id = 0)
    {
        $referral_ids = $attempted_bulk_payout_orchestration_result->referral_ids;
        $payout_ids = $attempted_bulk_payout_orchestration_result->payout_ids;

        // set Referrals status to 'unpaid' and payout_id to 0 
        Referral::update_all(
            ['status' => Referral::STATUS_UNPAID, 'payout_id' => 0],
            ['id' => ['operator' => 'IN', 'value' => $referral_ids]]
        );

        // delete the Payouts n+1
        foreach ($payout_ids as $payout_id) {
            Payout::delete($payout_id);
        }

        // delete the BulkPayout
        BulkPayout::delete($bulk_payout_id);
    }

    /**
     * TODO this function does not properly return ->referral_ids nor ->payout_ids in the return value
     * 
     * @param BulkPayoutOrchestrationParams $params
     * @param array<Payout> $payouts
     * 
     * @return Either<AttemptedBulkPayoutOrchestrationResult>
     */
    private static function handle_side_effects_of_bulk_payout_orchestration_such_as_paypal_or_store_credit_or_csv($params, $payouts)
    {
        $method = $params->export_args['bulk_payout_method'];
        $currency = $params->export_args['payout_currency'];

        switch ($method) {
            case PayAffiliatesController::BULK_PAYOUT_METHOD_PAYPAL:
                return self::handle_paypal_bulk_payout($payouts, $currency);
            case PayAffiliatesController::BULK_PAYOUT_METHOD_CSV:
                return self::handle_csv_bulk_payout($payouts, $params->filters, $currency);
            case PayAffiliatesController::BULK_PAYOUT_METHOD_STORE_CREDIT:
                return self::handle_store_credit_bulk_payout($payouts);
            default:
                return self::handle_csv_bulk_payout($payouts, $params->filters, $currency);
        }
    }

    /**
     * Handles creating a bulk payout via the PayPal method, coordinating everything that needs to happen.
     *
     * @param Payout[] $payouts
     * @param string $currency
     *
     * @return Either<AttemptedBulkPayoutOrchestrationResult>
     */
    private static function handle_paypal_bulk_payout($payouts, $currency)
    {
        $response = PayPal\PayoutsClient::create($payouts, $currency);

        if (is_a($response, \SolidAffiliate\Lib\VO\PayPal\SuccessResponse::class)) {
            $payout_ids = array_map(function ($p) {
                return $p->id;
            }, $payouts);



            /** @psalm-suppress ArgumentTypeCoercion */
            return new Either(
                [''],
                new AttemptedBulkPayoutOrchestrationResult([
                    "message" => __('PayPal has received, and will soon process your bulk payout.', 'solid-affiliate'),
                    "status" => $response->body->batch_status,
                    "reference" => $response->body->payout_batch_id,
                    'payout_ids' => $payout_ids,
                    'total_amount' => Payout::sum_amount($payouts),
                    'referral_ids' => []
                ]),
                true
            );
        } else {
            if (is_a($response, \SolidAffiliate\Lib\VO\PayPal\FailResponse::class)) {
                $long_paypal_error_message = __("There was an issue processing your PayPal bulk payout.", 'solid-affiliate') . "
                        <br>
                        <br>
                        <pre>" .
                    __("Message from PayPal", 'solid-affiliate') . ": {$response->message}<br>" .
                    __("Error from PayPal", 'solid-affiliate') . ": {$response->error}<br>" .
                    __("Status Code", 'solid-affiliate') . ": {$response->status_code}<br>" .
                    __("Information Link 1", 'solid-affiliate') . ": https://developer.paypal.com/docs/api/payments.payouts-batch/v1/#errors <br>" .
                    __("Information Link 2", 'solid-affiliate') . ": https://developer.paypal.com/docs/api/reference/api-responses/#failed-requests <br>" .
                    __("Debug ID", 'solid-affiliate') . ": {$response->debug_id} https://developer.paypal.com/developer/dashboard/live/ <br>" .
                    "<br>" .
                    "Solid Affiliate" . __("Documentation", 'solid-affiliate') . ": https://docs.solidaffiliate.com/paypal-payouts/" .
                    "</pre>";

                if ($response->error === "AUTHORIZATION_ERROR") {
                    $authorization_error_helpful_message = "
                        <br>" .
                        __("If the error is AUTHORIZATION_ERROR, please read the documentation above and ensure the following:", 'solid-affiliate') .
                        "<br>" .
                        "1) " . __("You have enabled the PayPal API in your PayPal account settings.", 'solid-affiliate') .
                        "<br>" .
                        "2) " . __("Your Business PayPal account has Payouts enabled. You may need to contact PayPal support to have Payouts activated.", 'solid-affiliate') .
                        "<br>" .
                        "3) " . __("Your API credentials are correct.", 'solid-affiliate') .
                        "<br>" .
                        "4) " . __("You have enough funds in your PayPal account to pay out the total commission amount.", 'solid-affiliate');

                    $long_paypal_error_message = $long_paypal_error_message . $authorization_error_helpful_message;
                }

                return new Either(
                    [
                        $long_paypal_error_message
                    ],
                    new AttemptedBulkPayoutOrchestrationResult(
                        ["message" => $response->message, "status" => BulkPayout::FAIL_STATUS, "reference" => '', 'payout_ids' => [], 'referral_ids' => [], 'total_amount' => 0.0]
                    ),
                    false
                );
            } else {
                return new Either(
                    [__('There was an issue processing your PayPal bulk payout.', 'solid-affiliate')],
                    new AttemptedBulkPayoutOrchestrationResult(
                        ["message" => '', "status" => BulkPayout::FAIL_STATUS, "reference" => '', 'payout_ids' => [], 'referral_ids' => [], 'total_amount' => 0.0]
                    ),
                    false
                );
            }
        }
    }

    /**
     * Handles creating a bulk payout via the CSV method, coordinating everything that needs to happen.
     *
     * @param Payout[] $payouts
     * @param BulkPayoutOrchestrationFiltersType $bulk_payout_filters
     * @param string $payout_currency
     *
     * @return Either<AttemptedBulkPayoutOrchestrationResult> Returns name of CSV file on success
     */
    private static function handle_csv_bulk_payout($payouts, $bulk_payout_filters, $payout_currency)
    {
        // (?) Generate CSV // TODO
        // Louis
        // I would generate the CSV file with a loop. You need to make a request with no other things happening.

        // TODO: do we want this to always be isRight?
        $date = date('m-d-Y');
        $filename = "solid-affiliate-bulk-payout-{$date}.csv";
        self::generate_bulk_payout_csv_for_bulk_payout_filters($bulk_payout_filters, $payout_currency, $filename);

        $payout_ids = array_map(function ($p) {
            return $p->id;
        }, $payouts);

        return new Either(
            [''],
            new AttemptedBulkPayoutOrchestrationResult([
                "message" => __("Your bulk payout was processed and downloaded into a CSV file", 'solid-affiliate') . ": {$filename}",
                "status" => BulkPayout::SUCCESS_STATUS,
                "reference" => $filename,
                "payout_ids" => $payout_ids,
                'total_amount' => Payout::sum_amount($payouts),
                "referral_ids" => [] // TODO actually find and return the referral ids that are involved
            ]),
            true
        );
    }

    /**
     * 
     * @param BulkPayoutOrchestrationParams $params
     *
     * @return Either<AttemptedBulkPayoutOrchestrationResult>|null
     */
    private static function verify_affiliates_have_valid_payment_emails($params)
    {
        $referrals_owed = self::find_referrals_owed_for_bulk_payout_filters($params->filters);
        $affiliates_owed_ids = array_map(function ($referral) {
            return $referral->affiliate_id;
        }, $referrals_owed);
        $affiliates_owed = Affiliate::where(['id' => ['operator' => 'IN', 'value' => $affiliates_owed_ids]]);

        $affiliates_with_missing_or_invalid_payment_emails = array_filter($affiliates_owed, function ($affiliate) {
            $payment_email = $affiliate->payment_email;

            return empty($payment_email) || (filter_var($payment_email, FILTER_VALIDATE_EMAIL) === false);
        });


        if (!empty($affiliates_with_missing_or_invalid_payment_emails)) {
            $affiliates_with_missing_or_invalid_payment_emails = array_map(function ($affiliate) {
                $payment_email = $affiliate->payment_email;
                return "Affiliate #" . $affiliate->id . " - email is " . (empty($payment_email) ? 'missing' : 'invalid');
            }, $affiliates_with_missing_or_invalid_payment_emails);

            $affiliates_with_missing_or_invalid_payment_emails = implode(', ', $affiliates_with_missing_or_invalid_payment_emails);

            return new Either(
                [__('One or more affiliates in this payouts have a missing or invalid payment email: ', 'solid-affiliate') . $affiliates_with_missing_or_invalid_payment_emails],
                new AttemptedBulkPayoutOrchestrationResult(
                    [
                        "message" => '',
                        "status" => BulkPayout::FAIL_STATUS,
                        "reference" => '',
                        'payout_ids' => [],
                        'referral_ids' => [],
                        'total_amount' => 0.0
                    ]
                ),
                false
            );
        } else {
            return null;
        }
    }
}
