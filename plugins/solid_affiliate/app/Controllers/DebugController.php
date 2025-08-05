<?php

namespace SolidAffiliate\Controllers;

use Illuminate\Contracts\Validation\Validator;
use SolidAffiliate\Lib\Formatters;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\Integrations\WooCommerceSubscriptionsIntegration;
use SolidAffiliate\Lib\PayPal\PayoutsClient;
use SolidAffiliate\Lib\PayPal\Response;
use SolidAffiliate\Lib\PayPal\ResponseValidation;
use SolidAffiliate\Lib\SeedDatabase;
use SolidAffiliate\Lib\Settings;
use SolidAffiliate\Lib\SolidLogger;
use SolidAffiliate\Lib\URLs;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\PayPal\SuccessResponse;
use SolidAffiliate\Lib\WooSoftwareLicense\WOO_SLT_Licence;
use SolidAffiliate\Models\AffiliateGroup;
use SolidAffiliate\Models\BulkPayout;
use SolidAffiliate\Models\Payout;
use SolidAffiliate\Models\Referral;
use SolidAffiliate\Views\Shared\AdminTabsView;
use SolidAffiliate\Views\Shared\SimpleTableView;
use SolidAffiliate\Views\Shared\WPListTableView;


/**
 * DebugController
 * 
 * @psalm-suppress PropertyNotSetInConstructor
 */
class DebugController
{
    /**
     * @return void
     */
    public static function admin_root()
    {
        $tab = isset($_GET["tab"]) ? (string)$_GET["tab"] : "index";

        $o = '<div class="wrap">';
        $o .= AdminTabsView::render(
            'solid-affiliate-debug',
            [
                ['index', 'Debug'],
                ['fix_missing_recurring_referrals', '⚠️ Fix Missing Subscription Renewal Referrals ⚠️'],
                ['paypal_failed_payouts', 'PayPal Failed Payouts'],
                ['create_random_data', 'Create Random Data'],
                ['design_components', 'Design Components'],
                ['solid_logs', 'Solid Logs'],
                ['license', 'License'],
                ['misc', 'Misc']
            ],
            $tab,
            null
        );
        $o .= '</div>';

        echo $o;

        switch ($tab) {
            case "index":
                DebugController::GET_admin_index();
                break;
            case "fix_missing_recurring_referrals":
                DebugController::GET_fix_missing_recurring_referrals();
                break;
            case "design_components":
                DebugController::design_components();
                break;
            case "paypal_failed_payouts":
                DebugController::GET_paypal_failed_payouts();
                break;
            case "create_random_data":
                DebugController::GET_create_random_data();
                break;
            case "solid_logs":
                SolidLogger::display_admin_page();
                break;
            case "license":
                DebugController::GET_license();
                break;
            case "misc":
                DebugController::GET_misc();
                break;
            default:
                DebugController::GET_admin_index();
                break;
        }
    }

    /**
     * @return void
     */
    public static function GET_admin_index()
    {

        $o = '<div class="wrap">';
        $o .= '<h1>⚠️ Debug ⚠️</h1>';
        $o .= '<h2>The purpose of this part of the plugin is to facilitate Solid Affiliate team members in support, debugging, and maybe experimental features.</h2>';
        $o .= '<p>Everything here is dangerous to use, and might result in data corruption. If you are seeing this page and are not an employee of Solid Affiliate, please back away slowly and then contact our support team letting them know.</p>';
        $o .= '</div>';
        echo ($o);
    }

    /**
     * @return void
     */
    public static function GET_fix_missing_recurring_referrals()
    {
        $o = '<div class="wrap">';
        $o .= "<h1>Fix Missing Subscription Renewal Referrals</h1>";
        $o .= "<p>This will attempt to fix any subscription renewal referrals that are missing from the database. This will run lots of n+1 queries, might break the site, and is limited to just 1000 Woo Orders so it might not actually work.</p>";


        if (isset($_GET['sudo'])) {
            $o .= "<p><strong>sudo query param detected in url. running function DebugController::incorrect_woo_subscription_renewals .</strong></p>";
            $o .= self::incorrect_woo_subscription_renewals();
        } else {
            $o .= "<p><strong>sudo query param not detected in url. aborting function DebugController::incorrect_woo_subscription_renewals.</strong></p>";
            $o .= "<p><strong>To run the function, add a 'sudo' query param to the current URL</strong></p>";
        };

        $o .= '</div>';

        echo ($o);
    }

    /**
     * @return void
     */
    public static function design_components()
    {
        ob_start();
?>

        <script type="text/javascript">
            document.querySelector('.copy-button').addEventListener('click', function() {
                // Get the code block
                var codeBlock = document.querySelector('#code-block');

                // Select the text
                var range = document.createRange();
                range.selectNode(codeBlock);
                window.getSelection().addRange(range);

                // Copy the text
                document.execCommand('copy');

                // Deselect the text
                window.getSelection().removeAllRanges();
            });
        </script>

        <style>
            code {
                font-family: 'Courier New', monospace;
                white-space: pre-wrap;
                color: #555;
            }

            /* Add syntax highlighting */
            .language-css {
                color: #d73a49;
            }
        </style>

        <pre>
  <code class="language-css" id="code-block">
    p {
      color: red;
    }
  </code>
  <button class="copy-button">Copy Code</button>
</pre>



    <?php
        $html = ob_get_clean();

        echo $html;
    }

    /**
     * @return void
     */
    public static function GET_paypal_failed_payouts()
    {
        $bulk_payout_count = BulkPayout::count(self::failed_paypal_payouts_bulk_payouts_where_query());
        // render array as string
        $query = self::failed_paypal_payouts_bulk_payouts_where_query();
        $query_string = json_encode($query);

        $is_live_mode = (bool)Settings::get(Settings::KEY_INTEGRATIONS_PAYPAL_USE_LIVE);
        $api_mode = $is_live_mode ? BulkPayout::API_MODE_LIVE : BulkPayout::API_MODE_SANDBOX;

        $o = '<div class="wrap">';
        $o .= "<h1>Find Failed Payouts via PayPal API</h1>";
        $o .= "<p>This hits your sandbox/live PayPal API using the credentials you have in settings. It will make a PayPal API request for every BulkPayout in the database.</p>";
        $o .= "<p>You are currently in API mode: <strong>" . $api_mode . "</strong></p>";
        $o .= "<p>You currently have <strong>$bulk_payout_count BulkPayouts in the database for this query</strong>: BulkPayout::where($query_string)</p>";
        $o .= "<p>Possible PayPal transaction statuses <a href='https://developer.paypal.com/docs/api/payments.payouts-batch/v1/#payouts-item-get-response'>PayPal Docs #Payouts Item</a> </p>";


        if (isset($_GET['sudo'])) {
            $o .= "<p><strong>sudo query param detected in url. running function DebugController::render_failed_paypal_payouts_view .</strong></p>";
            $o .= self::render_failed_paypal_payouts_view();
        } else {
            $o .= "<p><strong>sudo query param not detected in url. aborting function DebugController::render_failed_paypal_payouts_view.</strong></p>";
            $o .= "<p><strong>To run the function, add a 'sudo' query param to the current URL</strong></p>";
        };

        $o .= '</div>';

        echo ($o);
    }

    /**
     * @return void
     */
    public static function GET_create_random_data()
    {
        $o = '<div class="wrap">';
        $o .= "<div>
                    <h2>Every time you refresh this page with SUDO, <strong>SeedDatabase::run()</strong> is called.</h2>
                    <p>It adds:</p>
                    <ul>
                      <li>&bull; <strong>1 WP user</strong> with random email and password = 'password'</li>
                      <li>&bull; <strong>1 Affiliate</strong> with user_id = the WP user that was just made</li>
                      <li>&bull; <strong>20 Visits</strong> for the Affiliate</li>
                      <li>&bull; <strong>20 Referrals</strong> for the Affiliate</li>
                      <li>&bull; <strong>20 Payouts</strong> for the Affiliate</li>
                      <li>&bull; <strong>1 Creative</strong> with a random image, url, etc</li>
                      <li>&bull; <strong>20 Store Credit Transactions</strong> if the Addon is enabled</li>
                    </ul>

                    <p>
                        You can see the data in two places: 1) Our wp-admin pages 2) The Affiliate's Affiliate Portal. To see a portal, log in as any of the created Affiliates' users. Password = 'password' and you can find their email in Manage affiliates.
                    </p>
                    <p>
                    <strong>Note:</strong> To render the affiliate portal use the <code>[solid_affiliate_portal]</code> shortcode on any page.
                    </p>
                </div>";


        if (isset($_GET['sudo'])) {
            $o .= "<p><strong>sudo query param detected in url. running function SeedDatabase::run() .</strong></p>";
            $o .= "<h1 style='background: yellow;'>Random SolidAffiliate Data was just added to the database</h1>";
            SeedDatabase::run();
        } else {
            $o .= "<p><strong>sudo query param not detected in url. aborting function SeedDatabase::run().</strong></p>";
            $o .= "<p><strong>To run the function, add a 'sudo' query param to the current URL</strong></p>";
        };

        $o .= '</div>';

        echo ($o);
    }

    /**
     * @return void
     */
    public static function GET_license()
    {
        $license_data = WOO_SLT_Licence::get_license_data();

        $o = '<div class="wrap">';
        $o .= "<div>
                    <h2>License Data Debugging</h2>
                    <pre> " . print_r($license_data, true) . "</pre>
                </div>";


        if (isset($_GET['sudo'])) {
            $o .= "<p><strong>sudo query param detected in url. Going to force remove the license key from this site. NOTE this does not deactivate anything on our server.</strong></p>";
            $license_data = WOO_SLT_Licence::get_license_data();
            if ($license_data == false) {
                $license_data = [];
            }

            //save the license
            $license_data['key']          = '';
            $license_data['last_check']   = time();

            WOO_SLT_Licence::update_license_data($license_data);
        } else {
            $o .= "<p><strong>sudo query param not detected in url.</strong></p>";
            $o .= "<p><strong>To run the function, add a 'sudo' query param to the current URL</strong></p>";
            $o .= "<p><strong>Running this will FORCE REMOVE the license key from this site. It will set it to an empty string. It will not ping our server or deactivate anything on our server.</strong></p>";
        };

        $o .= '</div>';

        echo ($o);
    }


    /**
     * @param int|null $limit
     * @return string
     */
    public static function incorrect_woo_subscription_renewals($limit = 1000)
    {
        // get all WooCommerce Subscription renewal orders
        $all_orders = Validators::arr_of_woocommerce_order(wc_get_orders([
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ]));

        /** @psalm-suppress MixedArgumentTypeCoercion */
        $renewal_orders = array_filter($all_orders, 'wcs_order_contains_renewal');


        $is_renewal_missing_referral =
            /**
             * @param \WC_Order $order
             * @return bool
             */
            function ($order) {
                // return false if order status is not completed
                if ($order->get_status() !== 'completed') {
                    return false;
                }
                /** @var \WC_Subscription[] $maybe_subscriptions */
                $maybe_subscriptions = wcs_get_subscriptions_for_renewal_order($order);
                if (empty($maybe_subscriptions)) {
                    return false;
                } else {
                    $subscription = array_shift($maybe_subscriptions);
                    $subscription_id = $subscription->get_id();
                    $should_have_referral = WooCommerceSubscriptionsIntegration::is_subscription_referred_by_affiliate($subscription_id);
                    $maybe_existing_referral = Referral::where([
                        'order_id' => $order->get_id(),
                    ]);

                    return $should_have_referral && empty($maybe_existing_referral);
                }
            };

        $renewal_orders_with_referred_subscriptions_and_no_referrals = array_filter($renewal_orders, $is_renewal_missing_referral);

        $create_missing_referrals_tuple = array_map(function ($order) {
            $order_id = $order->get_id();
            // get woo order admin url
            $order_admin_url = admin_url("post.php?post=$order_id&action=edit");

            /** @var \WC_Subscription[] $subscriptions */
            $subscriptions = wcs_get_subscriptions_for_renewal_order($order);
            $subscription = array_values($subscriptions)[0];

            $did_create_referral = WooCommerceSubscriptionsIntegration::handle_woocommerce_subscription_renewal_payment_complete($subscription, $order);
            return [
                'order url' => $order_admin_url,
                'order_id' => $order_id,
                'did_create_referral' => $did_create_referral
            ];
        }, $renewal_orders_with_referred_subscriptions_and_no_referrals);

        ob_start();
        /** @psalm-suppress ForbiddenCode */
        '<pre>' . var_dump($create_missing_referrals_tuple) . '</pre>';
        $value = ob_get_clean();
        return $value;
    }

    /**
     * The query for which BulkPayouts we will be checking against the PayPal API.
     *
     * @return array{api_mode: BulkPayout::API_MODE_LIVE|BulkPayout::API_MODE_SANDBOX, method: PayAffiliatesController::BULK_PAYOUT_METHOD_PAYPAL}
     */
    public static function failed_paypal_payouts_bulk_payouts_where_query()
    {
        $is_live_mode = (bool)Settings::get(Settings::KEY_INTEGRATIONS_PAYPAL_USE_LIVE);
        $api_mode = $is_live_mode ? BulkPayout::API_MODE_LIVE : BulkPayout::API_MODE_SANDBOX;

        return [
            'method' => PayAffiliatesController::BULK_PAYOUT_METHOD_PAYPAL,
            'api_mode' => $api_mode
        ];
    }

    /**
     * Warning: This function makes live API calls to PayPal.
     * 
     * Impure function which hits the PayPal API to find failed payouts.
     *
     * @return string
     */
    public static function render_failed_paypal_payouts_view()
    {
        $bulk_payouts = BulkPayout::where(self::failed_paypal_payouts_bulk_payouts_where_query());

        // Notes:
        // References look like these:
        // $references = ['CH2W78754QLYN', 'LU6H9796FPEZS', 'RSGKUNNNFM2J6'];
        $references = array_map(
            function ($bulk_payout) {
                return $bulk_payout->reference;
            },
            $bulk_payouts
        );


        $reference_returned_payout_ids_tuple = array_map(
            function ($ref) {
                $live_api_response = PayoutsClient::get($ref);
                if ($live_api_response instanceof SuccessResponse) {
                    $results = self::validate_get_response($live_api_response);
                    if (isset($results['returned_payout_ids']) && isset($results['payout_ids_to_transaction_status_tuples'])) {
                        $returned_payout_ids = Validators::array_of_int($results['returned_payout_ids']);
                        $payout_id_to_transaction_status_tuple = $results['payout_ids_to_transaction_status_tuples'];
                        return [$ref, $returned_payout_ids, $payout_id_to_transaction_status_tuple];
                    } else {
                        return [$ref, [], []];
                    }
                } else {
                    // TODO handle API error
                    return [$ref, [], []];
                }
            },
            $references
        );

        $all_non_success_payout_id_to_status_tuples = array_reduce(
            $reference_returned_payout_ids_tuple,
            /**
             * @param array $carry
             * @param array<array>|array $item
             * 
             * @return array<array{int, string}>
             */
            function ($carry, $item) {
                /** @var array{int, string} */
                $payout_id_to_transaction_status_tuple = $item[2];
                /** @var array<array{int, string}> */
                $carry = array_merge($carry, $payout_id_to_transaction_status_tuple);
                return $carry;
            },
            []
        );

        $payout_tuples = array_map(function ($payout_id_to_status_tuple) {
            $payout_id = (int)$payout_id_to_status_tuple[0];
            $payout = Payout::find($payout_id);
            if ($payout) {
                $paypal_transaction_status = (string)$payout_id_to_status_tuple[1];
                $recommended_action = "<ul style='list-style: auto;'><li>Double check whether the data is correct in real PayPal.</li> <li>Mark this Payout as <strong>failed</strong></li> <li>Mark all its associated Referrals as <strong>unpaid</strong>.</li> <li>Fix any issues with the affiliate's account (their payment email, etc.) and then use the Pay Affiliates tool to send the payout again.</li></ul>";
                $last_updated_at = "<small>Last updated at: " . Formatters::localized_datetime('now') . "</small>";
                $associated_referrals = Referral::where([
                    'payout_id' => $payout_id
                ]);
                if (empty($associated_referrals)) {
                    $associated_referrals_output = "No referrals associated with this payout. If the payout has a status of <strong>failed</strong>, then it is a good thing that there are no referrals because you likely either deleted those referrals or paid for them again and now they're associated with a difference payout record..";
                } else {
                    $associated_referrals_output = join('</br> ', array_map(function ($referral) {
                        return Formatters::status_with_tooltip($referral->status, Referral::class, 'admin') . " referral #" . $referral->id;
                    }, $associated_referrals));
                }
                return [
                    $payout->id,
                    URLs::edit(Payout::class, $payout->id),
                    Formatters::status_with_tooltip($payout->status, Payout::class, 'admin'),
                    $associated_referrals_output,
                    // TODO the returned status might not be returned. It might be anything other than Success.
                    // https://developer.paypal.com/docs/api/payments.payouts-batch/v1/#definition-payout_batch_items
                    Formatters::status($paypal_transaction_status) . "<br> " . $last_updated_at,
                    $recommended_action
                ];
            } else {
                return ['-', '-', '-', '-', '-', '-'];
            }
        }, $all_non_success_payout_id_to_status_tuples);

        ob_start();
    ?>
        <div class="wrap">
            <h1><?php _e('Failed Payouts', 'solid-affiliate') ?></h1>
            <?php
            $o = "";
            $o .= '<p>This hits your live API. Only for internal use.</p>';
            $o .= "<h2>Admin Helper</h2>";
            $o .= "<div>";
            $o .= SimpleTableView::render(
                [
                    'Payout ID',
                    'Payout URL',
                    'Status in Solid Affiliate',
                    'Associated Referrals',
                    'Status in PayPal',
                    'Recommended Action'
                ],
                $payout_tuples
            );
            $o .= "</div>";
            $o .= "<h2>API Response data</h2>";
            $o .= "<pre>" . print_r($reference_returned_payout_ids_tuple, true) . "</pre>";
            echo ($o);
            ?>
        </div>

<?php
        $res = ob_get_clean();
        if ($res) {
            return $res;
        } else {
            return __("Error rendering Failed Payouts screen.", 'solid-affiliate');
        }
    }


    /**
     * Transforms a successful GET Payout response into our data object.
     *
     * @param SuccessResponse $response
     * 
     * @return array{'error validating GET Payout response': string}|array{'returned_payout_ids': int[], 'payout_ids_to_transaction_status_tuples': array{int, string}[], 'raw': array}
     */
    public static function validate_get_response($response)
    {
        $raw_response = $response->body->raw;
        // These should be an array of PayPal Batch Items objects https://developer.paypal.com/docs/api/payments.payouts-batch/v1/#definition-payout_batch_items.

        try {
            $paypal_payout_items = Validators::arr($raw_response['items']);
            $validated_payout_items = array_filter(array_map([Validators::class, 'paypal_payout_item'], $paypal_payout_items));

            $raw_response = array_filter($validated_payout_items, function ($item) {
                $item_transaction_status = $item['transaction_status'];
                return $item_transaction_status != ResponseValidation::PAY_PAL_SUCCESS;
            });
            return [
                'raw' => Validators::arr($raw_response),
                'returned_payout_ids' => array_map(function ($item) {
                    $sender_item_id = $item['payout_item']['sender_item_id'];
                    return PayoutsClient::payout_id_from_sender_id($sender_item_id);
                }, $raw_response),
                'payout_ids_to_transaction_status_tuples' => array_map(function ($item) {
                    $sender_item_id = $item['payout_item']['sender_item_id'];
                    $item_transaction_status = (string)$item['transaction_status'];
                    $payout_id = PayoutsClient::payout_id_from_sender_id($sender_item_id);
                    return [$payout_id, $item_transaction_status];
                }, $raw_response)
            ];
        } catch (\Exception $e) {
            $raw_response = ['error validating GET Payout response' => $e->getMessage()];
            return $raw_response;
        }
    }


    ///////////////////

    /**
     * @return void
     */
    public static function GET_misc()
    {
        $order_id = $_GET['order_id'] ?? null;

        if (!$order_id) {
            $data =  "No order_id provided";
        } else {
            $data = WooCommerceIntegration::order_description_for((int)$order_id); //self::misc_data();
        }


        $o = '<div class="wrap">';
        $o .= "<h1> Misc. debug things</h1>";

        // render the data
        $o .= '<div>';
        $o .= '<h2>Raw data</h2>';
        $o .= '<pre>' . print_r($data, true) . '</pre>';
        $o .= '</div>';

        $o .= '</div>';

        echo ($o);
    }

    // public static function misc_data()
    // {
    //     // $solid_affiliate_hooks_data = self::get_attached_solid_affiliate_hooks();
    //     $woocommerce_hooks_data = self::get_attached_woocommerce_hooks();

    //     return [
    //         // 'solid_affiliate_hooks_data' => $hooks_data,
    //         'woocommerce_hooks_data' => $woocommerce_hooks_data,
    //     ];
    // }

    // public static function get_attached_solid_affiliate_hooks()
    // {
    //     global $wp_filter;
    //     $prefix = 'SolidAffiliate';
    //     $attached_functions = [];

    //     foreach ($wp_filter as $hook_name => $hook_data) {
    //         foreach ($hook_data->callbacks as $priority => $callbacks) {
    //             foreach ($callbacks as $function_key => $callback) {
    //                 $function_name = self::extract_function_name($callback['function']);

    //                 if (strpos($function_name, $prefix) === 0) {
    //                     $attached_functions[] = [
    //                         'hook_name' => $hook_name,
    //                         'function_name' => $function_name,
    //                         'priority' => $priority,
    //                     ];
    //                 }
    //             }
    //         }
    //     }

    //     return $attached_functions;
    // }

    // /**
    //  *
    //  * @return list<array{function_name: mixed, hook_name: "woocommerce_checkout_update_order_meta", priority: mixed}>
    //  */
    // public static function get_attached_woocommerce_hooks()
    // {
    //     global $wp_filter;
    //     $attached_functions = [];

    //     // find anything attached to the woocommerce_checkout_update_order_meta hook
    //     $hook_name = 'woocommerce_checkout_update_order_meta';
    //     $hook_data = $wp_filter[$hook_name];
    //     foreach ($hook_data->callbacks as $priority => $callbacks) {
    //         foreach ($callbacks as $function_key => $callback) {
    //             $function_name = self::extract_function_name($callback['function']);

    //             $attached_functions[] = [
    //                 'hook_name' => $hook_name,
    //                 'function_name' => $function_name,
    //                 'priority' => $priority,
    //             ];
    //         }
    //     }

    //     return $attached_functions;
    // }

    // public static function extract_function_name($function)
    // {
    //     if (is_array($function)) {
    //         if (is_object($function[0])) {
    //             return get_class($function[0]) . '->' . $function[1];
    //         } else {
    //             return $function[0] . '::' . $function[1];
    //         }
    //     } else {
    //         return $function;
    //     }
    // }
}
