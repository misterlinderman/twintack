<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Addons\StoreCredit\Addon as StoreCreditAddon;
use SolidAffiliate\Controllers\AffiliatePortalController;
use SolidAffiliate\Controllers\SetupWizardController;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Lib\VO\SchemaEntry;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Models\Referral;

/**
 * TODO security reference: https://github.com/soflyy/unicorn/blob/master/plugin/ajax/api.php
 * 
 * @psalm-import-type SyncedData from \SolidAffiliate\Views\Admin\SetupWizard\V2View
 */
class AjaxHandler
{
    const AJAX_SEND_TEST_EMAIL = 'sld_affiliate_send_test_email';
    const AJAX_RESEND_REFERRAL_EMAIL_TO_AFFILIATE = 'sld_affiliate_resend_referral_email_to_affiliate';
    const AJAX_APPLY_STORE_CREDIT_TO_CART = 'sld_affiliate_apply_store_credit_to_cart';

    /**
     * @return void
     */
    public static function register_all_ajax_nopriv_hooks()
    {
        add_action('wp_ajax_nopriv_sld_affiliate_track_visit', [self::class, 'track_visit']);
        add_action('wp_ajax_nopriv_sld_affiliate_affiliate_search', [self::class, 'affiliate_search']);
        add_action('wp_ajax_nopriv_sld_affiliate_user_search', [self::class, 'user_search']);
        add_action('wp_ajax_nopriv_sld_affiliate_woocommerce_product_search', [self::class, 'woocommerce_product_search']);
        add_action('wp_ajax_nopriv_sld_affiliate_woocommerce_coupon_search', [self::class, 'woocommerce_coupon_search']);
        add_action('wp_ajax_nopriv_sld_affiliate_validate_setting', [self::class, 'validate_setting']);
        add_action('wp_ajax_nopriv_sld_affiliate_generate_registration_nonce', [self::class, 'generate_registration_nonce']);
        add_action('wp_ajax_nopriv_sld_affiliate_generate_login_nonce', [self::class, 'generate_login_nonce']);
        add_action('wp_ajax_nopriv_sld_affiliate_check_if_field_is_already_in_use', [self::class, 'check_if_field_is_already_in_use']);

        add_action('wp_ajax_nopriv_' . self::AJAX_APPLY_STORE_CREDIT_TO_CART, [self::class, self::AJAX_APPLY_STORE_CREDIT_TO_CART]);
    }

    /**
     * @return void
     */
    public static function register_all_ajax_hooks()
    {
        add_action('wp_ajax_sld_affiliate_track_visit', [self::class, 'track_visit']);
        add_action('wp_ajax_sld_affiliate_affiliate_search', [self::class, 'affiliate_search']);
        add_action('wp_ajax_sld_affiliate_user_search', [self::class, 'user_search']);
        add_action('wp_ajax_sld_affiliate_woocommerce_product_search', [self::class, 'woocommerce_product_search']);
        add_action('wp_ajax_sld_affiliate_woocommerce_coupon_search', [self::class, 'woocommerce_coupon_search']);
        add_action('wp_ajax_sld_affiliate_validate_setting', [self::class, 'validate_setting']);
        add_action('wp_ajax_sld_affiliate_generate_registration_nonce', [self::class, 'generate_registration_nonce']);
        add_action('wp_ajax_sld_affiliate_generate_login_nonce', [self::class, 'generate_login_nonce']);
        add_action('wp_ajax_sld_affiliate_check_if_field_is_already_in_use', [self::class, 'check_if_field_is_already_in_use']);

        add_action('wp_ajax_sld_affiliate_setup_wizard_v2_post', [self::class, 'setup_wizard_v2_post']);

        add_action('wp_ajax_sld_affiliate_search', [SolidSearch::class, 'handle_search_ajax']);

        add_action('wp_ajax_' . self::AJAX_SEND_TEST_EMAIL, [self::class, self::AJAX_SEND_TEST_EMAIL]);
        add_action('wp_ajax_' . self::AJAX_RESEND_REFERRAL_EMAIL_TO_AFFILIATE, [self::class, self::AJAX_RESEND_REFERRAL_EMAIL_TO_AFFILIATE]);
        add_action('wp_ajax_' . self::AJAX_APPLY_STORE_CREDIT_TO_CART, [self::class, self::AJAX_APPLY_STORE_CREDIT_TO_CART]);
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////////
    // Functions and Ajax handlers
    ///////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * TODO translations
     * Note that we're not currently doing anything with the strings returned here by success or error. 
     * The error message is hard coded in the front end in RegistrationView.php
     *
     * @return never
     */
    public static function check_if_field_is_already_in_use()
    {
        $field = Validators::one_of(['email', 'login'], 'email', ($_POST['field'] ?? ''));

        $value = $_POST['value'] ?? '';
        if ($field === 'email') {
            $value = sanitize_email((string)$value);
            if (empty($value)) {
                // this is kinda weird. i just want to not return an error if the email is empty and stop execution.
                wp_send_json_success();
            }

            $user = get_user_by('email', $value);

            if ($user instanceof \WP_User) {
                wp_send_json_error();
            }
        } else {
            $value = sanitize_user((string)$value);
            if (empty($value)) {
                wp_send_json_success();
            }

            $user = get_user_by('login', $value);

            if ($user instanceof \WP_User) {
                wp_send_json_error();
            }
        }


        wp_send_json_success();
    }

    /**
     * TODO-setup move this all out of here, into Setup Wizard class
     * @return never
     */
    public static function setup_wizard_v2_post()
    {
        SetupWizard::handle_setup_wizard_POST();
    }


    /**
     * @return never
     */
    public static function generate_registration_nonce()
    {
        $nonce = wp_create_nonce(AffiliatePortalController::NONCE_SUBMIT_AFFILIATE_REGISTRATION);
        wp_send_json_success($nonce);
    }

    /**
     * @return never
     */
    public static function generate_login_nonce()
    {
        $nonce = wp_create_nonce(AffiliatePortalController::NONCE_SUBMIT_AFFILIATE_LOGIN);
        wp_send_json_success($nonce);
    }

    /**
     * TODO Move all of this out of here into the Addon
     *
     * @return never
     */
    public static function sld_affiliate_apply_store_credit_to_cart()
    {
        $either_applied = StoreCreditAddon::apply_store_credit_to_current_woocommerce_cart();

        if ($either_applied->isLeft) {
            $error_msg = $either_applied->left[0];
            wp_send_json_error(['error' => $error_msg, 'valid' => false]);
        } else {
            wp_send_json_success(['error' => '', 'valid' => true, 'recipients' => ['tester']]);
        }
    }

    /**
     * Undocumented function
     *
     * @return never
     */
    public static function sld_affiliate_send_test_email()
    {
        $email_key = isset($_POST['email_key']) ? (string)$_POST['email_key'] : '';
        if (in_array($email_key, Email_Notifications::ALL_EMAILS)) {
            // TODO this send_test_email method should be more robust, handle error cases.
            /** @psalm-suppress ArgumentTypeCoercion */
            $recipients = Email_Notifications::send_test_email($email_key);
            wp_send_json_success(['error' => '', 'valid' => true, 'recipients' => $recipients]);
        } else {
            wp_send_json_error(['error' => 'Invalid email key sent with request.', 'valid' => false], 400);
        }
    }

    /**
     * @return string[]|null
     */
    public static function sld_affiliate_resend_referral_email_to_affiliate()
    {
        $referral_id = isset($_POST['referral_id']) ? (int)$_POST['referral_id'] : 0;
        $maybe_referral = Referral::find($referral_id);
        if ($maybe_referral instanceof Referral) {

            $check = Email_Notifications::check_referral_id($referral_id);
            if ($check->isLeft) {
                return [];
            } else {
                list($_referral, $_affiliate, $user) = $check->right;
                $recipients = Email_Notifications::dispatch_referral_insertion_notification($user->user_email, $referral_id);
                wp_send_json_success(['error' => '', 'valid' => true, 'recipients' => $recipients]);
            }
        } else {
            return [];
        }
    }

    /**
     * Undocumented function
     *
     * @return never
     */
    public static function validate_setting()
    {
        $post = $_POST;
        if (!isset($post['setting_key'])) {
            wp_send_json_error(['error' => __('Missing Setting Key', 'solid-affiliate'), 'valid' => false], 400);
        }
        if (!array_key_exists('setting_value', $post)) {
            wp_send_json_error(['error' => __('Missing Setting Value', 'solid-affiliate'), 'valid' => false], 400);
        }

        $key = Validators::str_from_array($post, 'setting_key');
        $value = Validators::str($post['setting_value']);
        $validated_setting = Settings::validator_settings_array([$key => $value]);
        if (empty($validated_setting)) {
            wp_send_json_error(['error' => __('Invalid Setting Lookup', 'solid-affiliate'), 'valid' => false], 400);
        }

        $schema = Settings::schema();
        $entry = $schema->entries[$key];
        /** @var mixed $sanitized_value */
        $sanitized_value = SchemaEntry::sanitize($entry, $value);
        $result = SchemaEntry::validate($entry, $sanitized_value);
        if ($result[0]) {
            wp_send_json_success(['error' => '', 'valid' => true]);
        } else {
            wp_send_json_success(['error' => $result[1], 'valid' => false]);
        }
    }

    /**
     * @return never
     */
    public static function track_visit()
    {
        $affiliate_slug = isset($_POST['affiliate_slug']) ? Validators::str($_POST['affiliate_slug']) : '0';
        $landing_url = isset($_POST['landing_url']) ? (string)$_POST['landing_url'] : '';
        $http_referrer = isset($_POST['http_referer']) ? (string)$_POST['http_referer'] : '';
        $visit_ip = isset($_POST['visit_ip']) ? (string)$_POST['visit_ip'] : '';
        $previous_visit_id = 0;


        $false_or_visit_id = VisitTracking::handle_visit_tracking_ajax(
            $affiliate_slug,
            $landing_url,
            $http_referrer,
            $visit_ip,
            $previous_visit_id
        );

        if ($false_or_visit_id === false) {
            wp_send_json_success([
                'created_visit_id' => false,
                'visit_id' => 0
            ]);
        } else {
            wp_send_json_success([
                'created_visit_id' => true,
                'visit_id' => $false_or_visit_id
            ]);
        }
    }

    /**
     * The AJAX endpoint to search for Affiliates in our database. Returns a JSON array of Affiliate objects.
     *
     * @return void
     */
    public static function affiliate_search()
    {
        $q = isset($_REQUEST['q']) ? (string)$_REQUEST['q'] : '';
        $affiliate_results = Affiliate::fuzzy_search($q);
        $affiliate_results = Validators::arr_of_affiliate($affiliate_results);

        $affiliate_results = array_map(function ($affiliate) {
            $user = get_userdata($affiliate->user_id);
            $user_email = $user ? $user->user_email : __('User Email Not Found', 'solid-affiliate');
            $avatar = get_avatar($affiliate->user_id, 32);

            $results_html = "
                <div class='sld-affiliate-select-result'>
                    <div class='sld-affiliate-avatar'>
                    {$avatar}
                    </div>
                   <div>
                    <div class='sld-affiliate-info'>{$affiliate->first_name} {$affiliate->last_name} ({$affiliate->id})</div>
                    <div class='sld-affiliate-email'>{$user_email}</div>
                    </div>
                </div>
            ";

            return [
                'id' => $affiliate->id,
                'text' => $user_email,
                'result_html' => $results_html
            ];
        }, $affiliate_results);

        $response = [
            'results' => $affiliate_results,
            'pagination' => [
                'more' => false,
            ],
        ];

        wp_die(json_encode($response));
    }

    /**
     * The AJAX endpoint to search for WordPress Users. Returns a JSON array of User objects.
     *
     * @return void
     */
    public static function user_search()
    {
        $q = isset($_REQUEST['q']) ? (string)$_REQUEST['q'] : '';
        $user_query = new \WP_User_Query([
            'search' => '*' . esc_attr($q) . '*',
            'number' => 10
        ]);
        $user_results = $user_query->get_results();

        $user_results = Validators::arr_of_wp_user($user_results);

        $user_results = array_map(function ($user_data) {
            $data = $user_data->data;
            $id = (int)$data->ID;
            $user_nicename = (string)$data->user_nicename;
            $user_email = (string)$data->user_email;
            $avatar = get_avatar($id, 32);

            $results_html = "
                <div class='sld-affiliate-select-result'>
                    <div class='sld-affiliate-avatar'>
                    {$avatar}
                    </div>
                   <div>
                    <div class='sld-affiliate-info'>{$user_nicename}" . sprintf(__('User ID %1$s', 'solid-affiliate'), $id) . "</div>
                    <div class='sld-affiliate-email'>{$user_email}</div>
                    </div>
                </div>
            ";

            return [
                'id' => $id,
                'text' => $data->user_email,
                'result_html' => $results_html
            ];
        }, $user_results);

        $response = [
            'results' => $user_results,
            'pagination' => [
                'more' => false,
            ],
        ];

        wp_die(json_encode($response));
    }

    /**
     * The AJAX endpoint to search for WooCommerce Products. Returns a JSON array of WC_Product objects.
     *
     * @return void
     */
    public static function woocommerce_product_search()
    {
        // if 'q' is not set, return an empty array.
        if (!isset($_REQUEST['q']) || empty($_REQUEST['q'])) {
            wp_die(json_encode([
                'results' => [],
                'pagination' => [
                    'more' => false,
                ],
            ]));
        }

        $search_query = esc_attr((string)$_REQUEST['q']);
        $wc_product_results = [];

        if (is_numeric($search_query)) {
            // Attempt to get the product with this ID.
            $product = wc_get_product(intval($search_query));
            if ($product) {
                $wc_product_results[] = $product;
            }
        }

        $wc_products_from_numeric = Validators::arr_of_woocommerce_product($wc_product_results);

        $wc_product_query = new \WC_Product_Query([
            's' => $search_query,
            'limit' => 10,
        ]);
        $wc_products_from_search = Validators::arr_of_woocommerce_product($wc_product_query->get_products());

        $wc_product_results = array_merge($wc_products_from_numeric, $wc_products_from_search);
        $wc_product_results = Validators::arr_of_woocommerce_product($wc_product_results);

        ///////////////////////////////////
        // ALTERNATIVE
        ///////////////////////////////////
        // $args = array(
        //     'post_type' => 'product',
        //     's' => $_GET['q'],
        //     'fields' => 'ids'
        // );
        // $product_ids = new \WP_Query($args);
        // $wc_product_results = [];
        // foreach ($product_ids->posts as $product_id) {
        //     $wc_product_results[] = wc_get_product($product_id);
        // }
        // $wc_product_results = Validators::arr_of_woocommerce_product($wc_product_results);

        $wc_product_results = array_map(
            /**
             * @param \WC_Product $wc_product
             * @return array
             */
            function ($wc_product) {
                $id = $wc_product->get_id();
                $product_name = $wc_product->get_name();
                $sku = $wc_product->get_sku();
                $product_image = $wc_product->get_image('32px', [], false);

                $results_html = "
                <div class='sld-affiliate-select-result'>
                    <div class='sld-affiliate-avatar sld-woocommerce-product-img'>
                    {$product_image}
                    </div>
                   <div>
                    <div class='sld-affiliate-info'>{$product_name}" . ' ' . sprintf(__('Product ID %1$s', 'solid-affiliate'), $id) . "</div>
                    <div class='sld-affiliate-email'>{$sku}</div>
                    </div>
                </div>
            ";

                return [
                    'id' => $id,
                    'text' => $product_name,
                    'result_html' => $results_html
                ];
            },
            $wc_product_results
        );

        $response = [
            'results' => $wc_product_results,
            'pagination' => [
                'more' => false,
            ],
        ];

        wp_die(json_encode($response));
    }

    /**
     * The AJAX endpoint to search for WooCommerce Coupons.
     *
     * @return void
     */
    public static function woocommerce_coupon_search()
    {
        $q = isset($_REQUEST['q']) ? (string)$_REQUEST['q'] : '';

        $coupon_post_query = new \WP_Query([
            'post_type' => WooCommerceIntegration::COUPON_POST_TYPE,
            's' => esc_attr($q),
            'limit' => 10
        ]);
        $wc_coupon_posts = $coupon_post_query->get_posts();
        $wc_coupons = Validators::arr_of_wp_coupon_from_posts($wc_coupon_posts);

        $wc_coupon_results = array_map(
            /**
             * @param \WC_Coupon $wc_coupon
             * @return array
             */
            function ($wc_coupon) {
                $id = $wc_coupon->get_id();
                $code = $wc_coupon->get_code();
                $desc = $wc_coupon->get_description();

                $results_html = "
                    <div class='sld-affiliate-select-result'>
                    </div>
                    <div>
                        <div class='sld-affiliate-info'>{$code} " . sprintf(__('Coupon ID %1$s', 'solid-affiliate'), (string)$id) . "</div>
                        <div class='sld-affiliate-email'>{$desc}</div>
                    </div>
                ";

                return [
                    'id' => $id,
                    'text' => sprintf('%1$s (%2$s)', $code, $id),
                    'result_html' => $results_html
                ];
            },
            $wc_coupons
        );

        $response = [
            'results' => $wc_coupon_results,
            'pagination' => [
                'more' => false,
            ],
        ];

        wp_die(json_encode($response));
    }
}
