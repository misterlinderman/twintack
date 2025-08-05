<?php

namespace SolidAffiliate\Lib;

use SolidAffiliate\Addons\StoreCredit\Addon as StoreCreditAddon;
use SolidAffiliate\Lib\Integrations\WooCommerceIntegration;
use SolidAffiliate\Models\Affiliate;
use SolidAffiliate\Lib\VO\Either;
use SolidAffiliate\Models\Referral;

/**
 * @psalm-import-type LoginCredentials from \SolidAffiliate\Lib\GlobalTypes
 * @psalm-import-type AffiliatePortalCouponDataArrayType from \SolidAffiliate\Lib\VO\AffiliatePortalViewInterface
 * @psalm-import-type StoreCreditDataType from \SolidAffiliate\Lib\VO\AffiliatePortalViewInterface
 */
class AffiliatePortalFunctions
{
    /**
     * Checks to see if a logged out user registering has provided matching credentials, and if so logs that user in.
     *
     * @param \WP_User $user
     * @param string $user_pass
     * @param string $user_login
     *
     * @return false|\WP_User
     */
    public static function maybe_login_existing_wp_user_with_registration_creds($user, $user_pass, $user_login)
    {
        $is_password_correct = wp_check_password($user_pass, $user->user_pass, $user->ID);
        if ($is_password_correct) {
            $maybe_logged_in_user = wp_signon(['user_login' => $user_login, 'user_password' => $user_pass]);
            if ($maybe_logged_in_user instanceof \WP_User) {
                return wp_set_current_user($maybe_logged_in_user->ID, $maybe_logged_in_user->user_login);
            }
        }

        return false;
    }

    /**
     * Checks to see if a logged out user, who is registering as an Affiliate, already has a WP user.
     * If so, then return the user so the register actiion can start down the logged in flow.
     * This allows users who have WP accounts to register as an Affiliate without having to log into WP first.
     *
     * @param string $user_login
     * @param string $user_email
     *
     * @return false|\WP_User
     */
    public static function maybe_get_wp_user_from_logged_out_registration($user_login, $user_email)
    {
        $maybe_user_by_email = get_user_by('email', $user_email);
        $maybe_user_by_login = get_user_by('login', $user_login);

        if ($maybe_user_by_email instanceof \WP_User && $maybe_user_by_login instanceof \WP_User) {
            if ($maybe_user_by_email->ID === $maybe_user_by_login->ID) {
                return $maybe_user_by_email;
            }
        }

        return false;
    }

    /**
     * Attempt Affiliate Login
     * 
     * Given login_credentials, checks if there is a User + Affiliate.
     * If there is, logs them in and returns the User ID.
     * Otherwise, returns error messages.
     * 
     * See https://github.com/WordPress/wordpress-develop/blob/924343c8fc1b5878b865aa9109bdca05852470b8/src/wp-login.php#L1104 
     * that's how WordPress login is handled.
     *
     * @param LoginCredentials $login_credentials
     * 
     * @psalm-type UserId = int
     * @return Either<UserId>
     */
    public static function attempt_affiliate_login($login_credentials)
    {
        // Ayman: instead of running this function, you can have the same wordpress login form.
        // it might also be a multisite thing.

        $user_email = $login_credentials['user_email'];
        $user_pass = $login_credentials['user_pass'];


        // Check if there is a user with this email.
        $maybe_user = get_user_by('email', $user_email);
        if (!$maybe_user) {
            return new Either([__('No user found with that email.', 'solid-affiliate')], 0, false);
        }


        // Check if there is a Affiliate with this user_id.
        $maybe_affiliate = Affiliate::find_where(['user_id' => $maybe_user->ID]);
        if (is_null($maybe_affiliate)) {
            return new Either([__('No Affiliate account with that email.', 'solid-affiliate')], 0, false);
        }


        // Check if the email + password combination is correct
        // $wp_user_or_error = wp_authenticate($user_email, $user_pass);

        $is_password_correct = wp_check_password($user_pass, $maybe_user->user_pass, $maybe_user->ID);

        // if ($wp_user_or_error instanceof \WP_Error) {
        //     return new Either(['Incorrect password and email combination.'], 0, false);
        if (!$is_password_correct) {
            return new Either([__('Incorrect password and email combination.', 'solid-affiliate')], 0, false);
        } else {
            $wp_user = $maybe_user;

            wp_set_auth_cookie($wp_user->ID, true);
            wp_set_current_user($wp_user->ID);
            do_action('wp_login', $wp_user->user_login, $wp_user);

            return new Either([''], $wp_user->ID, true);
        }
    }

    /**
     * Queries DBs and prepares data describing the Coupons and any successful Coupon Referrals for an Affiliate.
     *
     * @param int $affiliate_id
     * @return AffiliatePortalCouponDataArrayType
     */
    public static function coupon_data_for_affiliate_id($affiliate_id)
    {
        // find any coupons for this Affiliate ID
        $rd_args = array(
            'post_type' => WooCommerceIntegration::COUPON_POST_TYPE,
            'meta_query' => array(
                array(
                    'key' => WooCommerceIntegration::MISC['coupon_affiliate_id_key'],
                    'value' => $affiliate_id,
                )
            )
        );

        $rd_query = new \WP_Query($rd_args);
        $matching_coupon_posts = Validators::arr_of_wp_coupon_from_posts($rd_query->posts);

        $coupon_data = array_map(
            function (\WC_Coupon $coupon) use ($affiliate_id) {
                $referrals_count = Referral::count(['affiliate_id' => $affiliate_id, 'coupon_id' => $coupon->get_id()]);
                return [
                    $coupon->get_code(),
                    $coupon->get_amount(),
                    __(Formatters::humanize($coupon->get_discount_type(), true, '_'), 'solid-affiliate'),
                    $referrals_count
                ];
            },
            $matching_coupon_posts
        );

        wp_reset_postdata();

        return $coupon_data;
    }

    /**
     * @param int $affiliate_id
     * @param int $page - for paginating store credit transactions
     * 
     * @return StoreCreditDataType
     */
    public static function store_credit_data_for_affiliate_id($affiliate_id, $page)
    {
        return StoreCreditAddon::affiliate_portal_store_credit_data_for_affiliate_id($affiliate_id, $page); 
    }

    /**
     * Checks Settings. Not pure.
     * 
     * @return bool
     */
    public static function should_render_affiliate_registration_form()
    {
        $setting_affiliate_portal_forms = (string)Settings::get(Settings::KEY_AFFILIATE_PORTAL_FORMS);

        $should_render_affiliate_registration_form = ($setting_affiliate_portal_forms === 'registration_and_login' || $setting_affiliate_portal_forms === 'registration');
        return $should_render_affiliate_registration_form;
    }

    /**
     * Checks Settings. Not pure.
     * 
     * @return bool
     */
    public static function should_render_affiliate_login_form()
    {
        $setting_affiliate_portal_forms = (string)Settings::get(Settings::KEY_AFFILIATE_PORTAL_FORMS);

        $should_render_affiliate_login_form = ($setting_affiliate_portal_forms === 'registration_and_login' || $setting_affiliate_portal_forms === 'login');
        return $should_render_affiliate_login_form;
    }

    /**
     * Whether or not an Affiliate Portal form should replace the query params of the referer URL on submit.
     *
     * @return boolean
     */
    public static function should_replace_params_on_form_submit()
    {
        $setting_affiliate_portal_forms = (string)Settings::get(Settings::KEY_AFFILIATE_PORTAL_FORMS);
        return ($setting_affiliate_portal_forms === 'registration_and_login');
    }

/**
 * Checks if dark mode is enabled for the affiliate portal theme.
 * 
 * @return bool
 */
public static function is_affiliate_portal_dark_mode()
{
    $setting_affiliate_portal_theme = strtolower((string)Settings::get(Settings::KEY_AFFILIATE_PORTAL_FORMS_THEME));

    return $setting_affiliate_portal_theme === 'dark';
}

}
