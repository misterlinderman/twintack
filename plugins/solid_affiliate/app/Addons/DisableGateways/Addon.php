<?php

namespace SolidAffiliate\Addons\DisableGateways;

use SolidAffiliate\Addons\AddonInterface;
use SolidAffiliate\Lib\Utils;
use SolidAffiliate\Lib\Validators;
use SolidAffiliate\Lib\VO\AddonDescription;
use SolidAffiliate\Lib\VO\Schema;
use SolidAffiliate\Lib\VO\SchemaEntry;
use WC_Gateway_COD;
use WC_Payment_Gateways;

/**
 * @psalm-import-type EnumOptionsReturnType from \SolidAffiliate\Lib\VO\SchemaEntry
 */
class Addon implements AddonInterface
{
    /** @var string */
    const ADDON_SLUG = 'disable-gateways';

    /**
     * This is the function which gets called when the Addon is loaded.
     * This is the entry point for the Addon.
     * 
     * Register your Addon by using the
     * "solid_affiliate/addons/addon_descriptions" filter.
     * 
     * Then check if your Addon is enabled, and if so do your stuff.
     *
     * @return void
     */
    public static function register_hooks()
    {
        add_filter("solid_affiliate/addons/addon_descriptions", [self::class, "register_addon_description"]);
    }

    /**
     * This is the function which includes a call to Core::is_addon_enabled() to check if the addon is enabled.
     * 
     * Do not put anything in the register_hooks above besides add_filter and add_action calls. 
     *
     * @return void
     */
    public static function register_if_enabled_hooks()
    {
        if (\SolidAffiliate\Addons\Core::is_addon_enabled(self::ADDON_SLUG)) {
            add_filter('solid_affiliate/should_skip_referral_creation_for_order', [self::class, 'should_skip_referral_creation_for_order'], 10, 2);
        }
    }

    /**
     * This function is required.
     * 
     * Return a declatrative description of the addon.
     * 
     * The returned AddonDescription is used by \SolidAffiliate\Addons\Core 
     * to display the addon in the admin panel,
     * handle the settings and so on.
     * 
     * @param AddonDescription[] $addon_descriptions
     * @return AddonDescription[]
     */
    public static function register_addon_description($addon_descriptions)
    {
        try {
            $payment_gateway_options = self::get_payment_gateway_options();
        } catch (\Throwable $e) {
            $payment_gateway_options = [];
        }

        $settings_schema = new Schema(["entries" => [
            "payment-gateways-to-ignore" => new SchemaEntry(array(
                'type' => 'multi_checkbox',
                'is_enum' => true,
                'enum_options' => $payment_gateway_options,
                'display_name' => __('Payment Gateways to Ignore', 'solid-affiliate'),
                'user_default' => [],
                'form_input_description' => __('Select any WooCommerce Payment Gateways which Solid Affiliate should ignore. Orders completed through these gateways will never result in a referral.', 'solid-affiliate'),
                'required' => false,
                'show_on_edit_form' => true,
            )),
        ]]);

        $description = new AddonDescription([
            'slug' => (string)self::ADDON_SLUG,
            'name' => __('Exclude payment gateway referrals', 'solid-affiliate'),
            'description' => __('Exclude customer selected payment gateway(s) from generating referrals.', 'solid-affiliate'),
            'author' => 'Solid Affiliate',
            'graphic_src' => 'https://blog.2checkout.com/wp-content/uploads/2020/07/payment-gateway-security-features-1024x799.png',
            'settings_schema' => $settings_schema,
            'documentation_url' => 'https://docs.solidaffiliate.com/exclude-payment-gateway-referrals/',
        ]);

        $addon_descriptions[] = $description;
        return $addon_descriptions;
    }

    /**
     * @param array{0: bool, 1: string[]} $should_skip_tuple
     * @param \SolidAffiliate\Lib\VO\OrderDescription $order_description
     * 
     * @return array{0: bool, 1: string[]}
     */
    public static function should_skip_referral_creation_for_order($should_skip_tuple, $order_description)
    {
        $gateways_to_ignore = Validators::array_of_string(
            \SolidAffiliate\Addons\Core::get_addon_setting((string)self::ADDON_SLUG, 'payment-gateways-to-ignore')
        );

        $wc_order = wc_get_order($order_description->order_id);

        if ($wc_order instanceof \WC_Order) {
            $payment_method = $wc_order->get_payment_method();
            if (in_array($payment_method, $gateways_to_ignore)) {
                $fail_messages = array_merge($should_skip_tuple[1], [__('Order was placed through a payment gateway that Solid Affiliate is configured to ignore.', 'solid-affiliate')]);
                return [true, $fail_messages];
            }
        }

        return $should_skip_tuple;
    }


    /////////////////////////////////////////////////////////////////////////////////////////////////
    // Helper Functions
    /////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Gets all the available installed WooCommerce Payment Gateways, returns tuples of the form:
     * 
     * [
     *  ['bacs', 'Direct bank transfer'],
     *  ['cheque', 'Check payments'],
     *  ['cod', 'Cash on delivery'],
     * ]
     *
     * @return EnumOptionsReturnType
     */
    private static function get_payment_gateway_options()
    {
        // if WC is installed AND this is an Admin request. We don't need to check the 
        // payment gateways for admin settings unless we're actually in the admin settings.
        // This was in response to https://secure.helpscout.net/conversation/2064881611/5354?folderId=5775442
        try {
            if (function_exists('WC') && is_admin()) {
                /** 
                 * @psalm-suppress MixedMethodCall
                 * @psalm-suppress MixedPropertyFetch
                 * @var array<string, \WC_Payment_Gateway> 
                 **/
                $payment_gateways = WC()->payment_gateways->payment_gateways;



                $payment_gateway_options = [];
                foreach ($payment_gateways as $payment_gateway_slug => $payment_gateway) {
                    $title = $payment_gateway->get_title();
                    if (Utils::is_empty($title)) {
                        $title = $payment_gateway->method_title;
                    }
                    $payment_gateway_options[] = [$payment_gateway_slug, $title];
                }

                return $payment_gateway_options;
            } else {
                return [];
            }
        } catch (\Throwable $e) {
            // Log the error if needed
            return []; // Return empty array in case of any error
        } catch (\Exception $e) { // For PHP 5
            // handle $e
            return []; // Return empty array in case of any error
        }
    }
}
